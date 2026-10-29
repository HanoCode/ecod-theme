<?php
/**
 * Ecod Theme · 构建产物解析
 *
 * 打包管线（build/）会把 CSS / JS 压缩成文件名带内容哈希的 dist 产物，
 * 并生成 assets/dist/manifest.php 映射表。本文件是 PHP 侧的唯一查询入口：
 *
 *   - 命中 dist 产物 → 返回哈希文件 URL，可发一年期 immutable 长缓存；
 *   - 未构建 / manifest 缺失 / 产物文件被删 → 返回 null，调用方回退源文件。
 *
 * 刻意不依赖 Typecho：api.php / widget.php / admin/editor.php 这些独立
 * 入口没有加载 functions.php，也 require 本文件做同样的解析，
 * 三套资产版本号逻辑由此收敛为一份。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @license MIT
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 */

if (!defined('ECOD_THEME_DIR')) {
    /* 主题根目录。本文件位于 core/ 下；独立入口直接 require 时同样成立 */
    define('ECOD_THEME_DIR', dirname(dirname(__DIR__)));
}

/**
 * 查询源路径对应的 dist 产物
 *
 * @param string $path 相对主题根目录的源文件路径，如 assets/css/ecod.css
 * @return string|null dist 产物相对路径；无构建产物时返回 null
 */
function ecod_dist_resolve(string $path): ?string
{
    static $manifest = null;
    static $loaded = false;

    if (!$loaded) {
        $loaded = true;
        $mf = ECOD_THEME_DIR . '/assets/dist/manifest.php';
        if (is_file($mf)) {
            $manifest = require $mf;
            if (!is_array($manifest)) {
                $manifest = null;
            }
        }
    }

    $rel = $manifest[$path] ?? null;
    if ($rel === null) {
        return null;
    }
    /* manifest 与产物同仓提交，正常不会缺文件；这里兜底防手工误删导致 404 */
    return is_file(ECOD_THEME_DIR . '/' . $rel) ? $rel : null;
}
