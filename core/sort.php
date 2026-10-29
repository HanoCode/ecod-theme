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
    ];
}

/**
 * 当前排序键（非法值回退 recent）
 */
function ecod_sort_current(): string
{
    $raw = '';
    if (isset($_GET['sort'])) {
        $raw = strtolower(trim((string) $_GET['sort']));
    }
    $options = ecod_sort_options();
    return isset($options[$raw]) ? $raw : 'recent';
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
 * 给分页链接补上 sort 参数
 *
 * Typecho 的 pageNav 生成的是干净的分页地址（/page/2/），不会带上当前 query，
 * 不补的话第 2 页以后会悄悄退回「最新」，「加载更多」也会把默认排序的卡片
 * 追加到已排序的列表后面。
 */
function ecod_sort_pager(string $html): string
{
    $sort = ecod_sort_current();
    if ($sort === 'recent' || $html === '') {
        return $html;
    }

    return (string) preg_replace_callback('~href="([^"]+)"~', static function (array $m) use ($sort): string {
        $url = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        if (preg_match('~[?&]sort=~', $url)) {
            return $m[0];
        }
        $sep = strpos($url, '?') === false ? '?' : '&';
        return 'href="' . htmlspecialchars($url . $sep . 'sort=' . $sort, ENT_QUOTES, 'UTF-8') . '"';
    }, $html);
}

/**
 * 主查询排序接管
 *
 * 注意框架的行为：只要 Widget\Archive 上挂了 query 钩子，Archive::query()
 * 就**不再自己取数**（见 var/Widget/Archive.php:1421-1424）。所以这个钩子
 * 必须自己把结果 push 回归档对象，否则列表恒为空——即便不做任何排序改写。
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

        $sort = in_array($type, ['index', 'category', 'tag', 'author', 'search'], true)
            ? ecod_sort_current()
            : 'recent';

        if ($sort !== 'recent') {
            $GLOBALS['ecodSortApplied'] = true;

            try {
                $select->cleanAttribute('order');
            } catch (\Throwable $e) {
                /* 老版本 Query 没有 cleanAttribute 时直接追加，最多变成次级排序 */
            }

            if ($sort === 'views' && ecod_views_available()) {
                $select->order('table.contents.views', \Typecho\Db::SORT_DESC)
                    ->order('table.contents.created', \Typecho\Db::SORT_DESC);
            } elseif ($sort === 'hot') {
                $select->order('table.contents.commentsNum', \Typecho\Db::SORT_DESC)
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
