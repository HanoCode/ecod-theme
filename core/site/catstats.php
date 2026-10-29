<?php
/**
 * Ecod Theme · 分类统计
 *
 * 分类页头（E1）需要三个数字：篇数 / 累计阅读 / 最近更新。
 * 一条 JOIN 聚合查询全部拿到，不按文章逐条累加。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 * @license   MIT
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 分类元信息（按 slug 查一次，含描述）
 *
 * Archive 对象不暴露 mid，分类页头只能按 slug 反查。
 *
 * @param string $slug
 * @return array{mid:int, name:string, slug:string, desc:string, url:string}|null
 */
function ecod_cat_meta(string $slug): ?array
{
    static $cache = [];
    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }
    if (array_key_exists($slug, $cache)) {
        return $cache[$slug];
    }

    $cache[$slug] = null;
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('mid', 'name', 'slug', 'description')->from('table.metas')
                ->where('type = ?', 'category')
                ->where('slug = ?', $slug)
                ->limit(1)
        );
        if ($row) {
            $cache[$slug] = [
                'mid'  => (int) $row['mid'],
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
                'desc' => trim((string) $row['description']),
                'url'  => (string) \Typecho\Router::url('category', ['slug' => $slug], \Widget\Options::alloc()->index),
            ];
        }
    } catch (\Throwable $e) {
    }

    return $cache[$slug];
}

/**
 * 分类统计
 *
 * @param int $mid 分类 id
 * @return array{count:int, views:int, latest:int}
 */
function ecod_cat_stats(int $mid): array
{
    static $cache = [];
    if (isset($cache[$mid])) {
        return $cache[$mid];
    }

    $out = ['count' => 0, 'views' => 0, 'latest' => 0];
    if ($mid <= 0) {
        return $out;
    }

    try {
        $db = \Typecho\Db::get();
        $now = (int) (\Widget\Options::alloc()->time ?? time());
        $viewsCol = ecod_views_available() ? 'SUM(COALESCE(table.contents.views, 0))' : '0';
        $row = $db->fetchRow(
            $db->select('COUNT(*) AS num', $viewsCol . ' AS total', 'MAX(table.contents.created) AS latest')
                ->from('table.contents')
                ->join('table.relationships', 'table.relationships.cid = table.contents.cid')
                ->where('table.relationships.mid = ?', $mid)
                ->where('table.contents.type = ?', 'post')
                ->where('table.contents.status = ?', 'publish')
                ->where('table.contents.created < ?', $now)
        );
        if ($row) {
            $out['count'] = (int) $row['num'];
            $out['views'] = (int) $row['total'];
            $out['latest'] = (int) $row['latest'];
        }
    } catch (\Throwable $e) {
    }

    $cache[$mid] = $out;
    return $out;
}

/**
 * 该分类下文章命中的专题（按命中篇数排序）
 *
 * 只返回存在且启用的专题；分类页用来挂一行「相关专题」chips。
 *
 * @param int $mid
 * @param int $limit
 * @return array<int, array<string, mixed>>
 */
function ecod_cat_topics(int $mid, int $limit = 6): array
{
    static $cache = [];
    if (isset($cache[$mid])) {
        return array_slice($cache[$mid], 0, $limit);
    }

    $cache[$mid] = [];
    if ($mid <= 0) {
        return [];
    }

    try {
        /* 不写 GROUP BY：Query::group() 是「覆盖」而非「追加」，多列分组会被
           后一次调用冲掉，再加上 only_full_group_by 直接报 1055。文章量很小，
           取回 cid 后在 PHP 里去重计数更稳。 */
        $db = \Typecho\Db::get();
        $rows = $db->fetchAll(
            $db->select('table.fields.str_value', 'table.fields.int_value', 'table.contents.cid')
                ->from('table.fields')
                ->join('table.relationships', 'table.relationships.cid = table.fields.cid')
                ->join('table.contents', 'table.contents.cid = table.fields.cid')
                ->where('table.fields.name = ?', 'topic')
                ->where('table.relationships.mid = ?', $mid)
                ->where('table.contents.type = ?', 'post')
                ->where('table.contents.status = ?', 'publish')
        );
    } catch (\Throwable $e) {
        return [];
    }

    $hits = [];
    $seen = [];
    foreach ($rows as $row) {
        $raw = trim((string) $row['str_value']);
        if ($raw === '') {
            $raw = (string) (int) $row['int_value'];
        }
        $cid = (int) $row['cid'];
        foreach (preg_split('/[,\s]+/', strtolower($raw)) ?: [] as $slug) {
            $slug = ecod_topic_slug($slug);
            if ($slug === '') {
                continue;
            }
            /* 同一篇文章可能挂多个分类，命中只算一次 */
            $key = $slug . '#' . $cid;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $hits[$slug] = ($hits[$slug] ?? 0) + 1;
        }
    }

    $list = [];
    foreach ($hits as $slug => $num) {
        $topic = ecod_topic_find($slug);
        if ($topic === null) {
            continue;
        }
        $list[] = ['topic' => $topic, 'hit' => (int) $num];
    }
    usort($list, static function ($a, $b) {
        return $b['hit'] <=> $a['hit'];
    });

    $cache[$mid] = $list;
    return array_slice($list, 0, $limit);
}
