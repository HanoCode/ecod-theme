<?php
/**
 * Ecod Theme · 相关文章
 *
 * 相关度模型（越靠前越相关）：
 *   1. 同标签命中，每命中一个 +3
 *   2. 同分类命中，每命中一个 +1
 *   3. 仍不足时用「最近发布」补齐，保证区域永远不空
 *
 * 排除：当前文章、草稿/私密/隐藏、设了密码的文章。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

if (!function_exists('ecod_related_rows')) {
    /**
     * 取相关文章的原始行（已按相关度排序）
     *
     * 返回的每一行都带有 contents 表渲染所需字段，可直接交给
     * EcodPostQuery::push() 或 Widget 遍历，因此这里全部字段一次性取出，
     * 避免卡片渲染时再回查。
     *
     * @param int              $cid   当前文章 cid
     * @param int              $limit 需要几条
     * @param \Typecho\Db|null $db
     * @return array<int, array<string, mixed>>
     */
    function ecod_related_rows(int $cid, int $limit = 3, ?\Typecho\Db $db = null): array
    {
        if ($cid <= 0 || $limit <= 0) {
            return [];
        }

        try {
            $db = $db ?: \Typecho\Db::get();
            $prefix = $db->getPrefix();
        } catch (\Throwable $e) {
            return [];
        }

        $picked = [];

        /* ---------- 1. 按标签 / 分类算相关度 ---------- */
        try {
            $rels = $db->fetchAll("SELECT mid FROM {$prefix}relationships WHERE cid = {$cid}");
            $tagMids = [];
            $catMids = [];

            if ($rels) {
                $mids = array_values(array_unique(array_map('intval', array_column($rels, 'mid'))));
                $metas = $db->fetchAll(
                    "SELECT mid, type FROM {$prefix}metas WHERE mid IN (" . implode(',', $mids) . ')'
                );
                foreach ($metas as $meta) {
                    if ($meta['type'] === 'tag') {
                        $tagMids[] = (int) $meta['mid'];
                    } elseif ($meta['type'] === 'category') {
                        $catMids[] = (int) $meta['mid'];
                    }
                }
            }

            $allMids = array_merge($tagMids, $catMids);
            if ($allMids) {
                $tagIn = $tagMids ? implode(',', $tagMids) : '0';
                $catIn = $catMids ? implode(',', $catMids) : '0';
                $now = time();

                $sql = "SELECT c.cid,
                               SUM(CASE WHEN r.mid IN ({$tagIn}) THEN 3 ELSE 0 END) AS tag_hits,
                               SUM(CASE WHEN r.mid IN ({$catIn}) THEN 1 ELSE 0 END) AS cat_hits
                          FROM {$prefix}relationships r
                          JOIN {$prefix}contents c ON c.cid = r.cid
                         WHERE c.type = 'post'
                           AND c.status = 'publish'
                           AND c.created < {$now}
                           AND c.cid <> {$cid}
                           AND (c.password IS NULL OR c.password = '')
                           AND r.mid IN (" . implode(',', $allMids) . ")
                         GROUP BY c.cid
                        HAVING tag_hits + cat_hits > 0
                         ORDER BY tag_hits DESC, cat_hits DESC, c.created DESC
                         LIMIT {$limit}";

                foreach ($db->fetchAll($sql) as $row) {
                    $picked[] = (int) $row['cid'];
                }
            }
        } catch (\Throwable $e) {
            /* 相关度计算失败就退化为「最近发布」，不影响页面 */
        }

        /* ---------- 2. 不足时用最近发布补齐 ---------- */
        $need = $limit - count($picked);
        if ($need > 0) {
            try {
                $notIn = $picked ? ' AND c.cid NOT IN (' . implode(',', $picked) . ')' : '';
                $now = time();
                $rows = $db->fetchAll(
                    "SELECT c.cid FROM {$prefix}contents c
                      WHERE c.type = 'post'
                        AND c.status = 'publish'
                        AND c.created < {$now}
                        AND c.cid <> {$cid}
                        AND (c.password IS NULL OR c.password = '')
                        {$notIn}
                      ORDER BY c.created DESC
                      LIMIT {$need}"
                );
                foreach ($rows as $row) {
                    $picked[] = (int) $row['cid'];
                }
            } catch (\Throwable $e) {
            }
        }

        if (!$picked) {
            return [];
        }

        /* ---------- 3. 一次性取回完整行，并按相关度顺序还原 ---------- */
        $fields = 'cid, title, slug, created, modified, type, status, commentsNum,'
            . ' allowComment, allowPing, allowFeed, template, password, authorId, parent, text';
        if (function_exists('ecod_views_available') && ecod_views_available()) {
            $fields .= ', views';
        }

        try {
            $rows = $db->fetchAll(
                "SELECT {$fields} FROM {$prefix}contents WHERE cid IN (" . implode(',', $picked) . ')'
            );
        } catch (\Throwable $e) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['cid']] = $row;
        }

        $ordered = [];
        foreach ($picked as $one) {
            if (isset($map[$one])) {
                $ordered[] = $map[$one];
            }
        }

        return $ordered;
    }
}
