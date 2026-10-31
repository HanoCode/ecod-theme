<?php
/**
 * 列表排序：最新 / 最多浏览 / 最多评论
 *
 * 归档主查询的 ORDER BY 由 Typecho 在 Archive::execute() 里写死为 created DESC，
 * 主题无法在模板里改。这里用框架公开的插件钩子 Widget\Archive:query 接管：
 * 钩子在 order/page 之后、真正取数之前触发，用 Query::cleanAttribute('order')
 * 抹掉默认排序再重排，分页 limit / offset 不受影响。
 *
 * 同一页的计数查询（countSql）在 order 之前就 clone 走了，因此总数仍是全量。
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
 * 可选排序
 *
 * likes 暂不提供：点赞按行存储（ecod_likes 表一人一行），排序需要子查询或
 * 冗余计数列，先留白，避免为了一个排序项改表。
 *
 * @return array<string,array{label:string,icon:string}>
 */
function ecod_sort_options(): array
{
    return [
        'recent' => ['label' => '最新发布', 'icon' => 'clock'],
        'views'  => ['label' => '最多浏览', 'icon' => 'eye'],
        'hot'    => ['label' => '最多评论', 'icon' => 'comment'],
        'rand'   => ['label' => '随机一篇', 'icon' => 'shuffle'],
        /* 点赞按行存储（ecod_likes 一人一行），排序需子查询/冗余列，先留白（§30.5） */
        'likes'  => ['label' => '最多点赞', 'icon' => 'heart', 'soon' => true],
    ];
}

/**
 * 当前排序键（非法值 / 未接入的留白项回退 recent）
 */
function ecod_sort_current(): string
{
    $raw = '';
    if (isset($_GET['sort'])) {
        $raw = strtolower(trim((string) $_GET['sort']));
    }
    $options = ecod_sort_options();
    if (!isset($options[$raw]) || !empty($options[$raw]['soon'])) {
        return 'recent';
    }
    return $raw;
}

/**
 * 分类内搜索词（F3）：GET ?q=，去首尾空白、限长；只在分类归档消费。
 * 传 $archive 时校验归档类型，模板直接取词时传 null（调用方自行保证上下文）。
 */
function ecod_cat_search_q($archive = null): string
{
    if (!isset($_GET['q'])) {
        return '';
    }
    $q = trim((string) $_GET['q']);
    if ($q === '') {
        return '';
    }
    if ($archive !== null) {
        try {
            if (!is_object($archive) || !$archive->is('category')) {
                return '';
            }
        } catch (\Throwable $e) {
            return '';
        }
    }
    return mb_substr($q, 0, 60, 'UTF-8');
}

/** 当前排序的文案 */
function ecod_sort_label(?string $key = null): string
{
    $key = $key ?: ecod_sort_current();
    $options = ecod_sort_options();
    return $options[$key]['label'] ?? $options['recent']['label'];
}

/**
 * 生成带排序参数的当前地址
 *
 * 只保留与当前视图有关的 query（分类/标签/搜索词都在 path 或 query 里），
 * 切换排序时丢掉 page，回到第一页。
 */
function ecod_sort_url(string $key): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');
    $query = (string) (parse_url($uri, PHP_URL_QUERY) ?: '');

    $params = [];
    if ($query !== '') {
        parse_str($query, $params);
    }
    unset($params['sort'], $params['page']);

    if ($key !== 'recent') {
        $params['sort'] = $key;
    }

    $qs = http_build_query($params);
    return $path . ($qs !== '' ? '?' . $qs : '');
}

/**
 * 是否当前排序
 */
function ecod_sort_is(string $key): bool
{
    return ecod_sort_current() === $key;
}

/**
 * 给分页链接补上 sort / q 参数
 *
 * Typecho 的 pageNav 生成的是干净的分页地址（/page/2/），不会带上当前 query，
 * 不补的话第 2 页以后会悄悄退回「最新」/ 丢掉分类内搜索词，「加载更多」也会把
 * 默认排序的卡片追加到已排序的列表后面。
 */
function ecod_sort_pager(string $html): string
{
    $extra = [];
    $sort = ecod_sort_current();
    if ($sort !== 'recent') {
        $extra['sort'] = $sort;
    }
    $q = ecod_cat_search_q();
    if ($q !== '') {
        $extra['q'] = $q;
    }
    if ($extra === [] || $html === '') {
        return $html;
    }

    $suffix = http_build_query($extra);
    return (string) preg_replace_callback('~href="([^"]+)"~', static function (array $m) use ($suffix): string {
        $url = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        if (preg_match('~[?&](sort|q)=~', $url)) {
            return $m[0];
        }
        $sep = strpos($url, '?') === false ? '?' : '&';
        return 'href="' . htmlspecialchars($url . $sep . $suffix, ENT_QUOTES, 'UTF-8') . '"';
    }, $html);
}

/**
 * 主查询排序接管
 *
 * 注意框架的行为：只要 Widget\Archive 上挂了 query 钩子，Archive::query()
 * 就**不再自己取数**（见 var/Widget/Archive.php:1421-1424）。所以这个钩子
 * 必须自己把结果 push 回归档对象，否则列表恒为空——即便不做任何排序改写。
 *
 * 置顶优先：fields.pin 非空的文章在 index/category/tag/author 四种归档里
 * 浮到流的最上面（search 不置顶——搜索要的是相关度）。LEFT JOIN 条件全部
 * 收在 ON 里，非置顶行为 NULL、行数不变；因为 fields 表 (cid,name) 是主键，
 * 不会产生重复行。fields 必须显式 select('table.contents.*')——框架 fields
 * 默认 '*'，JOIN 后会把 fields 表的同名列（cid）一并取出覆盖正文数据。
 * NULL 在 DESC 排序里排最后，正好落回原序；置顶内部按 created DESC 排。
 *
 * @param mixed $archive Widget\Archive
 * @param mixed $select  Typecho\Db\Query
 */
function ecod_sort_query($archive, $select): void
{
    if (!is_object($archive) || !is_object($select)) {
        return;
    }

    if (empty($GLOBALS['ecodSortApplied'])) {
        $type = '';
        try {
            $type = (string) ($archive->parameter->type ?? '');
        } catch (\Throwable $e) {
            $type = '';
        }

        /* 分类内搜索（F3）：?q= 只对分类归档生效。
           计数查询是 countSql = clone $select 之后才进本钩子的，getTotal() 又是
           惰性的（渲染期才执行），所以这里把同样的 WHERE 补进 getCountSql()
           返回的对象，「共 N 篇」和分页都按过滤后计数。 */
        $ecodQ = ecod_cat_search_q($archive);
        if ($ecodQ !== '') {
            $ecodLike = '%' . $ecodQ . '%';
            $select->where('(table.contents.title LIKE ? OR table.contents.text LIKE ?)', $ecodLike, $ecodLike);
            try {
                $countSql = $archive->getCountSql();
                if ($countSql !== null && $countSql !== $select) {
                    $countSql->where('(table.contents.title LIKE ? OR table.contents.text LIKE ?)', $ecodLike, $ecodLike);
                }
            } catch (\Throwable $e) {
                /* 计数同步失败只影响总数展示，不影响列表 */
            }
        }

        $pinFirst = in_array($type, ['index', 'category', 'tag', 'author'], true);

        $sort = in_array($type, ['index', 'category', 'tag', 'author', 'search'], true)
            ? ecod_sort_current()
            : 'recent';

        /* 置顶优先在任何排序下都生效，所以 recent 也要进改写分支 */
        if ($pinFirst || $sort !== 'recent') {
            $GLOBALS['ecodSortApplied'] = true;

            try {
                $select->cleanAttribute('order');
            } catch (\Throwable $e) {
                /* 老版本 Query 没有 cleanAttribute 时直接追加，最多变成次级排序 */
            }

            if ($pinFirst) {
                try {
                    $select->select('table.contents.*')
                        ->join(
                            'table.fields',
                            "table.fields.cid = table.contents.cid AND table.fields.name = 'pin' AND table.fields.str_value <> ''",
                            \Typecho\Db::LEFT_JOIN
                        )
                        ->order('table.fields.str_value', \Typecho\Db::SORT_DESC);
                } catch (\Throwable $e) {
                    /* JOIN 失败就退回无置顶的普通排序 */
                }
            }

            if ($sort === 'views' && ecod_views_available()) {
                $select->order('table.contents.views', \Typecho\Db::SORT_DESC)
                    ->order('table.contents.created', \Typecho\Db::SORT_DESC);
            } elseif ($sort === 'hot') {
                /* 评论热门按「已通过」实算的条数排（commentsNum 含待审且有滞后），
                   派生表 1:1 JOIN，不影响计数与分页 */
                $cmtCol = function_exists('ecod_comment_order_join') ? ecod_comment_order_join($select) : '';
                $select->order($cmtCol !== '' ? $cmtCol : 'table.contents.commentsNum', \Typecho\Db::SORT_DESC)
                    ->order('table.contents.created', \Typecho\Db::SORT_DESC);
            } elseif ($sort === 'rand') {
                /* 随机一篇（F3）：按日种子的稳定随机——同一天内顺序不变，
                   翻页/加载更多不重排，跨天换一批；置顶优先仍在此前生效。
                   MD5(CONCAT(cid, 数字种子)) 全程无空格无引号，能活着通过
                   Query::order() 的 filterColumn 逐字符过滤 */
                $seed = (int) date('Ymd');
                $select->order('MD5(CONCAT(table.contents.cid,' . $seed . '))', \Typecho\Db::SORT_ASC)
                    ->order('table.contents.created', \Typecho\Db::SORT_DESC);
            } else {
                /* views 列不可用时降级为最新 */
                $select->order('table.contents.created', \Typecho\Db::SORT_DESC);
            }
        }
    }

    /* 框架原本的那一步：取数并逐行压入 */
    $rows = \Typecho\Db::get()->fetchAll($select);
    foreach ($rows as $row) {
        $archive->push($row);
    }
}
