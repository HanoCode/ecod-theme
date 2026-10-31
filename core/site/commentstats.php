<?php
/**
 * Ecod Theme · 评论实时统计
 *
 * contents.commentsNum 是 Typecho 的缓存字段：口径是「非 spam 评论（含待审）」，
 * 而且导入 / 审核 / 删除后不一定同步，前台显示的评论数会和评论区实际条数对不上
 * （cid 37 显示 9，实际通过 8）。这里一次聚合全站已通过评论，按 cid 取用。
 *
 * 口径与 ecod_comment_stats() 保持一致：status=approved，含嵌套回复。
 *
 * 除了「显示」，排序也走同一口径：ecod_comment_num_table() / ecod_comment_order_join()
 * 给列表查询挂一张按 cid 聚合的派生表，ORDER BY 用实算评论数，避免出现
 * 「卡片写 8 条评论，按评论热门排序却按 9 的位置排」的错位。
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
 * 全站每篇文章的已通过评论数（含嵌套回复）
 *
 * @return array<int, int> cid => 条数
 */
function ecod_comment_counts(): array
{
    /** @var array<int, int>|null $cache */
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = [];
    try {
        $db = \Typecho\Db::get();
        $rows = $db->fetchAll(
            $db->select('table.comments.cid', 'COUNT(*) AS num')
                ->from('table.comments')
                ->where('table.comments.status = ?', 'approved')
                ->group('table.comments.cid')
        );
        foreach ($rows as $row) {
            $cache[(int) $row['cid']] = (int) $row['num'];
        }
    } catch (\Throwable $e) {
    }

    return $cache;
}

/**
 * 「已通过评论数」派生表，供列表按真实评论数排序
 *
 * 返回形如：
 *   (SELECT cid, COUNT(*) AS num FROM {prefix}comments WHERE status = 'approved' GROUP BY cid) AS ecod_cmt
 *
 * 每个 cid 只有一行，LEFT JOIN 不会放大结果集；没有评论的文章该列为 NULL，
 * 所以「纯评论数排序」直接用 num DESC（NULL 在 MySQL 里天然排最后），
 * 参与加权计算时必须 COALESCE(num, 0)，否则高阅读零评论的文章会被算成 NULL 掉到末尾。
 *
 * @param string $alias 派生表别名
 * @return string
 */
function ecod_comment_num_table(string $alias = 'ecod_cmt'): string
{
    static $prefix = null;
    if ($prefix === null) {
        try {
            $prefix = \Typecho\Db::get()->getPrefix();
        } catch (\Throwable $e) {
            $prefix = 'typecho_';
        }
    }

    return '(SELECT cid, COUNT(*) AS num FROM ' . $prefix . "comments WHERE status = 'approved' GROUP BY cid) AS " . $alias;
}

/**
 * 给查询挂上评论数派生表，返回可直接用于 ORDER BY 的表达式
 *
 * 用法：
 *   $col = ecod_comment_order_join($select);              // 'ecod_cmt.num' 或 'MAX(ecod_cmt.num)'
 *   $select->order($col === '' ? 'table.contents.commentsNum' : $col, \Typecho\Db::SORT_DESC);
 *
 * 分类归档的查询带 GROUP BY table.contents.cid，MySQL 的 ONLY_FULL_GROUP_BY 不允许
 * ORDER BY 派生表的非聚合列（1055），所以分组查询改成 MAX(ecod_cmt.num)——
 * 派生表 1:1 JOIN，组内 MAX 就是该文章的真实评论数；计数查询（cleanAttribute('group')）
 * 里 MAX 仍是聚合函数，SQL 依旧合法。
 *
 * @param object $select Typecho Query 对象
 * @param string $alias  派生表别名
 * @return string 排序表达式；挂载失败返回空串（调用方回退 commentsNum）
 */
function ecod_comment_order_join($select, string $alias = 'ecod_cmt'): string
{
    try {
        $select->join(
            ecod_comment_num_table($alias),
            $alias . '.cid = table.contents.cid',
            \Typecho\Db::LEFT_JOIN
        );

        $grouped = false;
        try {
            $grouped = trim((string) $select->getAttribute('group')) !== '';
        } catch (\Throwable $e) {
            $grouped = false;
        }

        return $grouped ? 'MAX(' . $alias . '.num)' : $alias . '.num';
    } catch (\Throwable $e) {
        return '';
    }
}

/**
 * 单篇已通过评论数（走批量缓存，列表循环零额外查询）
 *
 * @param int $cid
 * @return int
 */
function ecod_comment_count(int $cid): int
{
    if ($cid <= 0) {
        return 0;
    }
    $counts = ecod_comment_counts();

    return (int) ($counts[$cid] ?? 0);
}
