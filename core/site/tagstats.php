<?php
/**
 * Ecod Theme · 标签实时统计
 *
 * metas.count 是 Typecho 的缓存字段：文章改成草稿 / 删除后不一定同步更新，
 * 标签云按它排序、显示、甚至取前 N 个都会和标签归档页的实际条数对不上。
 * 这里一次算出全部标签的已发布篇数，供标签墙 / 标签云 / 数据统计页复用。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 全部标签的实时篇数（只算已发布文章，且不含定时未发布）
 *
 * @return array<int, int> mid => 篇数
 */
function ecod_tag_counts(): array
{
    /** @var array<int, int>|null $cache */
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = [];
    try {
        $db = \Typecho\Db::get();
        $now = (int) (\Widget\Options::alloc()->time ?? time());
        $rows = $db->fetchAll(
            $db->select('table.relationships.mid', 'COUNT(DISTINCT table.contents.cid) AS num')
                ->from('table.relationships')
                ->join('table.contents', 'table.contents.cid = table.relationships.cid')
                ->where('table.contents.type = ?', 'post')
                ->where('table.contents.status = ?', 'publish')
                ->where('table.contents.created < ?', $now)
                ->group('table.relationships.mid')
        );
        foreach ($rows as $row) {
            $cache[(int) $row['mid']] = (int) $row['num'];
        }
    } catch (\Throwable $e) {
    }

    return $cache;
}

/**
 * 用实时篇数覆盖标签行的 count，剔除零篇并降序
 *
 * @param array<int, array<string, mixed>> $rows 每行至少含 mid
 * @param int $limit 0 表示不限
 * @return array<int, array<string, mixed>>
 */
function ecod_tag_live_rows(array $rows, int $limit = 0): array
{
    $counts = ecod_tag_counts();
    $out = [];
    foreach ($rows as $row) {
        $mid = (int) ($row['mid'] ?? 0);
        $row['count'] = $counts[$mid] ?? 0;
        if ($row['count'] <= 0) {
            continue;
        }
        $out[] = $row;
    }
    usort($out, static function ($a, $b) {
        return $b['count'] <=> $a['count'];
    });

    return $limit > 0 ? array_slice($out, 0, $limit) : $out;
}
