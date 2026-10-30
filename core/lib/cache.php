<?php
/**
 * Ecod Theme · 文件型 TTL 缓存
 *
 * Typecho 没有对象缓存（无 Redis/Memcached 可依赖），而 options 表的
 * user=0 行会被 Widget\Init 在每个请求整体载入内存——大对象放那里反而
 * 拖慢所有页面。本文件用 usr/uploads/.ecod-cache/ 下的文件做跨请求
 * TTL 缓存，定位与 core/lib/schema.php 的 ecod_cache_get/set 相反：
 * options 那张 KV 只放小配置，贵重产物（sitemap、聚合统计、生成物）放这里。
 *
 *   ecod_cache_remember(key, ttl, fn)   读缓存，未命中则生成并落盘
 *   ecod_cache_forget(key)              主动失效（如后台重新落盘 sitemap 时）
 *
 * 未命中时用 flock 非阻塞锁防击穿：抢到锁的进程生成，其余进程短暂等待
 * 重读；缓存目录不可写、读写失败、序列化失败一律退化为直接执行生成函数
 * ——缓存层自身永远不构成故障点。
 *
 * 文件不依赖主题其他函数，可被独立入口单独引入。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

if (!function_exists('ecod_file_cache_dir')) {
    /**
     * 缓存目录：/usr/uploads/.ecod-cache（不存在则尝试创建）
     *
     * 与缩略图缓存 .thumbs 同层的隐藏目录，空 index.html 防目录列表。
     *
     * @return string 目录全路径；uploads 不存在/不可用时返回空串（整体降级）
     */
    function ecod_file_cache_dir(): string
    {
        static $dir = null;
        if ($dir !== null) {
            return $dir;
        }

        $uploads = rtrim((string) __TYPECHO_ROOT_DIR__, '/') . '/usr/uploads';
        if (!is_dir($uploads)) {
            return $dir = '';
        }

        $dir = $uploads . '/.ecod-cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
            if (is_dir($dir) && !is_file($dir . '/index.html')) {
                @file_put_contents($dir . '/index.html', '');
            }
        }
        return is_dir($dir) ? $dir : ($dir = '');
    }
}

if (!function_exists('ecod_file_cache_read')) {
    /**
     * 读缓存文件（内部用）
     *
     * @param string $key 缓存键
     * @return array{0: bool, 1: mixed} [是否命中, 值]；过期文件顺手清掉
     */
    function ecod_file_cache_read(string $key): array
    {
        try {
            $file = ecod_file_cache_dir() . '/' . md5($key) . '.json';
            $raw = is_file($file) ? (string) @file_get_contents($file) : '';
            if ($raw === '') {
                return [false, null];
            }
            $data = json_decode($raw, true);
            if (!is_array($data) || !array_key_exists('v', $data)) {
                return [false, null];
            }
            if ((int) ($data['e'] ?? 0) < time()) {
                @unlink($file);
                return [false, null];
            }
            return [true, $data['v']];
        } catch (\Throwable $e) {
            return [false, null];
        }
    }
}

if (!function_exists('ecod_file_cache_write')) {
    /**
     * 落盘（内部用）：临时文件 + rename 原子替换，并发读不会拿到半截内容
     */
    function ecod_file_cache_write(string $key, $value, int $ttl): void
    {
        try {
            $file = ecod_file_cache_dir() . '/' . md5($key) . '.json';
            $raw = json_encode(
                ['e' => time() + max(1, $ttl), 'v' => $value],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
            if ($raw === false) {
                return;
            }
            $tmp = $file . '.' . (string) getmypid() . '.tmp';
            if (@file_put_contents($tmp, $raw) !== false) {
                @rename($tmp, $file);
            }
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        } catch (\Throwable $e) {
            /* 写失败不影响主流程，下次未命中重新生成 */
        }
    }
}

if (!function_exists('ecod_cache_remember')) {
    /**
     * 读缓存，未命中则执行 $fn 生成、落盘并返回
     *
     * $fn 的返回值必须可 JSON 序列化；$fn 抛出的异常原样上抛，
     * 与不经缓存直接调用语义一致。
     *
     * @param string   $key 缓存键（如 'sitemap'）
     * @param int      $ttl 存活秒数
     * @param callable $fn  未命中时的生成函数
     * @return mixed 缓存值，或 $fn 的返回值
     */
    function ecod_cache_remember(string $key, int $ttl, callable $fn)
    {
        $dir = ecod_file_cache_dir();
        if ($dir === '') {
            return $fn();
        }

        [$hit, $value] = ecod_file_cache_read($key);
        if ($hit) {
            return $value;
        }

        /* 防击穿：锁内复检后生成；抢不到锁说明另一进程正在生成 */
        $fh = @fopen($dir . '/' . md5($key) . '.lock', 'c');
        if ($fh && flock($fh, LOCK_EX | LOCK_NB)) {
            try {
                [$hit, $value] = ecod_file_cache_read($key);
                if ($hit) {
                    return $value;
                }
                $value = $fn();
                ecod_file_cache_write($key, $value, $ttl);
                return $value;
            } finally {
                flock($fh, LOCK_UN);
                fclose($fh);
            }
        }
        if ($fh) {
            fclose($fh);
        }

        /* 锁被占：等最多约 0.6s 拿对方刚写的值；等不到就自行生成兜底 */
        for ($i = 0; $i < 3; $i++) {
            usleep(200000);
            [$hit, $value] = ecod_file_cache_read($key);
            if ($hit) {
                return $value;
            }
        }
        return $fn();
    }
}

if (!function_exists('ecod_cache_forget')) {
    /**
     * 主动失效一份缓存（键不变下次未命中即重新生成）
     *
     * @param string $key 缓存键
     */
    function ecod_cache_forget(string $key): void
    {
        $dir = ecod_file_cache_dir();
        if ($dir === '') {
            return;
        }
        @unlink($dir . '/' . md5($key) . '.json');
    }
}
