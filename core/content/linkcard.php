<?php
/**
 * Ecod Theme · 站内链接自动卡片化
 *
 * 正文里「独占一段」的站内地址自动渲染成卡片，无需写短代码：
 *   /posts/12.html        → 文章卡（复用 .ecod_acard，与 [article id=12] 同款）
 *   /cat/dev/             → 分类卡（名称 + 篇数 + 描述）
 *   /tag/php/             → 标签卡（与分类卡同构，图标换成标签）
 *
 * 转换只在段落内「只有一个链接、没有其它文字、不含图片」时发生：
 * 行内链接、图片链接、作者手写的说明文字一律保持原样，避免破坏排版。
 * 需要保留原链接时给链接加 `data-no-card` 属性。
 *
 * 宿主地址与 siteUrl 不一致（含子目录、www 变体）时按外链处理，
 * 交给 core/content/oembed.php 与 core/site/go.php 的原有逻辑。
 *
 * 管线位置：shortcode → oembed → linkcard → keywords → lazy_images → go_rewrite。
 * 放在 oembed 之后，站外地址先被 oEmbed 接管，这里只处理站内剩余链接；
 * 放在 keywords 之前，卡片内部文字不会被关键词内链二次包裹（.ecod_metacard 在 <a> 内）。
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
 * 卡片色调：与首页分类导航（components/home/catnav.php）同一套
 *
 * @return array<int, array{0: string, 1: string}>
 */
function ecod_linkcard_tints(): array
{
    return [
        ['#4f6ef2', '#eef1fe'],
        ['#d9741f', '#fdf1e3'],
        ['#11a37f', '#e6f7f1'],
        ['#a05ad9', '#f6ecfd'],
        ['#d94b6b', '#fdecef'],
        ['#2f8fd9', '#e9f4fd'],
    ];
}

/**
 * 总开关（后台「代码与嵌入」）
 */
function ecod_linkcard_on(): bool
{
    return function_exists('ecod_option') ? ecod_option('ELinkCard', 'on') === 'on' : true;
}

/**
 * 站点主机名集合（含 www 变体），用于判定站内
 *
 * @return array<string, bool>
 */
function ecod_linkcard_hosts(): array
{
    static $hosts = null;
    if (is_array($hosts)) {
        return $hosts;
    }

    $hosts = [];
    try {
        $site = (string) \Utils\Helper::options()->siteUrl;
        $host = parse_url($site, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            $host = strtolower($host);
            $hosts[$host] = true;
            $hosts[str_starts_with($host, 'www.') ? substr($host, 4) : 'www.' . $host] = true;
        }
    } catch (\Throwable $e) {
        /* 取不到站点地址时只认相对路径 */
    }

    return $hosts;
}

/**
 * 解析站内地址
 *
 * @return array{type: string, key: string}|null  type = post / category / tag
 */
function ecod_linkcard_parse(string $url): ?array
{
    $url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));
    if ($url === '' || $url[0] === '#') {
        return null;
    }

    $path = '';
    $query = '';

    if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url)) {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return null;
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || !isset(ecod_linkcard_hosts()[$host])) {
            return null;
        }
        $path = (string) ($parts['path'] ?? '');
        $query = (string) ($parts['query'] ?? '');
    } elseif (str_starts_with($url, '//')) {
        /* 协议相对地址：按外链处理，交给 oEmbed */
        return null;
    } elseif ($url[0] === '/') {
        $qpos = strpos($url, '?');
        $path = $qpos === false ? $url : substr($url, 0, $qpos);
        $query = $qpos === false ? '' : substr($url, $qpos + 1);
    } else {
        return null;
    }

    /* 兼容 index.php/posts/12.html 形态 */
    $path = preg_replace('#/index\.php(?=/|$)#i', '', $path) ?? $path;

    /* 原生 ?p=12 */
    if ($query !== '') {
        parse_str($query, $qs);
        if (isset($qs['p']) && ctype_digit((string) $qs['p'])) {
            return ['type' => 'post', 'key' => (string) $qs['p']];
        }
    }

    if (preg_match('#/posts/(\d+)\.html$#i', $path, $m) || preg_match('#/archives/(\d+)/?$#i', $path, $m)) {
        return ['type' => 'post', 'key' => (string) (int) $m[1]];
    }
    if (preg_match('#/cat/([^/]+)/?$#i', $path, $m)) {
        return ['type' => 'category', 'key' => rawurldecode($m[1])];
    }
    if (preg_match('#/tag/([^/]+)/?$#i', $path, $m)) {
        return ['type' => 'tag', 'key' => rawurldecode($m[1])];
    }

    return null;
}

/**
 * 文章封面：自定义字段 thumb > 正文首图 > 随机图池
 */
function ecod_linkcard_cover(int $cid, string $text): string
{
    $cover = '';

    try {
        $row = \Typecho\Db::get()->fetchRow(
            \Typecho\Db::get()->select('str_value')->from('table.fields')
                ->where('cid = ?', $cid)->where('name = ?', 'thumb')->limit(1)
        );
        $cover = trim((string) strtok((string) ($row['str_value'] ?? ''), "\r\n"));
    } catch (\Throwable $e) {
        $cover = '';
    }

    if ($cover === '' && preg_match('#<img[^>]+src=["\']([^"\']+)["\']#i', $text, $m)) {
        $cover = $m[1];
    }

    if ($cover === '' && function_exists('ecod_option') && function_exists('ecod_pool_pick')) {
        $pool = trim((string) ecod_option('EThumbnail', ''));
        if ($pool !== '') {
            $items = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $pool) ?: [])));
            $picked = ecod_pool_pick($items, 1, (object) ['cid' => $cid]);
            if ($picked) {
                $cover = (string) $picked[0];
            }
        }
    }

    return function_exists('ecod_sc_url') ? ecod_sc_url($cover, '') : $cover;
}

/**
 * 文章卡 HTML（.ecod_acard，与 [article] 短代码同款）
 */
function ecod_linkcard_post_html(int $cid): string
{
    if ($cid <= 0) {
        return '';
    }

    try {
        $row = \Typecho\Db::get()->fetchRow(
            \Typecho\Db::get()->select('cid', 'title', 'text', 'created', 'commentsNum')
                ->from('table.contents')
                ->where('cid = ?', $cid)
                ->where('type = ?', 'post')
                ->where('status = ?', 'publish')
                ->limit(1)
        );
    } catch (\Throwable $e) {
        return '';
    }

    if (!$row || (int) $row['created'] > time()) {
        return '';
    }

    $text = (string) $row['text'];
    $cover = ecod_linkcard_cover($cid, $text);

    if ($cover !== '') {
        $thumb = '<span class="ecod_acard__thumb" style="--ecod-cover:url(&quot;'
            . htmlspecialchars($cover, ENT_QUOTES, 'UTF-8') . '&quot;)"></span>';
    } else {
        $thumb = '<span class="ecod_acard__thumb is-empty">' . ecod_icon('file', 24) . '</span>';
    }

    $plain = trim(preg_replace('#\s+#u', ' ', strip_tags($text)) ?? '');
    if (function_exists('ecod_shortcode_strip')) {
        $plain = ecod_shortcode_strip($plain);
    }
    $desc = mb_substr(trim($plain), 0, 90, 'UTF-8');

    try {
        $url = (string) \Typecho\Router::url('post', $row, \Utils\Helper::options()->index);
    } catch (\Throwable $e) {
        return '';
    }

    return '<a class="ecod_acard" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" data-ecod-card="post">'
        . $thumb
        . '<span class="ecod_acard__main">'
        . '<b>' . htmlspecialchars((string) $row['title'], ENT_QUOTES, 'UTF-8') . '</b>'
        . '<small>' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . ($desc !== '' ? '…' : '') . '</small>'
        . '<span class="ecod_acard__meta">' . date('Y-m-d', (int) $row['created'])
        . ' · ' . ecod_comment_count((int) $row['cid']) . ' 条评论</span>'
        . '</span>'
        . '<span class="ecod_acard__go">' . ecod_icon('arrow-up-right', 13) . '</span>'
        . '</a>';
}

/**
 * 分类 / 标签卡 HTML（.ecod_metacard）
 *
 * @param string $kind category|tag
 * @param string $key  slug 或 mid
 */
function ecod_linkcard_meta_html(string $kind, string $key): string
{
    $kind = $kind === 'tag' ? 'tag' : 'category';
    $key = trim($key);
    if ($key === '') {
        return '';
    }

    try {
        $db = \Typecho\Db::get();
        $select = $db->select('mid', 'name', 'slug', 'description', 'count')
            ->from('table.metas')
            ->where('type = ?', $kind)
            ->limit(1);
        if (ctype_digit($key)) {
            $select->where('mid = ?', (int) $key);
        } else {
            $select->where('slug = ?', $key);
        }
        $row = $db->fetchRow($select);
    } catch (\Throwable $e) {
        return '';
    }

    if (!$row) {
        return '';
    }

    try {
        $url = (string) \Typecho\Router::url($kind, $row, \Utils\Helper::options()->index);
    } catch (\Throwable $e) {
        return '';
    }

    $name = trim((string) $row['name']);
    if ($name === '') {
        return '';
    }

    $tints = ecod_linkcard_tints();
    $tint = $tints[(int) $row['mid'] % count($tints)];

    $desc = trim(preg_replace('#\s+#u', ' ', strip_tags((string) $row['description'])) ?? '');
    $desc = mb_substr($desc, 0, 70, 'UTF-8');

    $isTag = $kind === 'tag';
    $label = $isTag ? '标签' : '分类';

    if ($isTag) {
        $icon = '<span class="ecod_metacard__icon">' . ecod_icon('tag', 16) . '</span>';
    } else {
        $icon = '<span class="ecod_metacard__icon" style="--ecod-cat-tint:' . $tint[0]
            . ';--ecod-cat-soft:' . $tint[1] . '">'
            . htmlspecialchars(mb_substr($name, 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8')
            . '</span>';
    }

    return '<a class="ecod_metacard' . ($isTag ? ' is-tag' : '') . '" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
        . '" data-ecod-card="' . $kind . '">'
        . $icon
        . '<span class="ecod_metacard__main">'
        . '<b>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</b>'
        . '<small>' . $label . ' · ' . (int) $row['count'] . ' 篇文章</small>'
        . ($desc !== '' ? '<em>' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '</em>' : '')
        . '</span>'
        . '<span class="ecod_metacard__go">' . ecod_icon('chevron', 13) . '</span>'
        . '</a>';
}

/**
 * 按地址生成卡片；识别不了返回空串（调用方保持原样）
 */
function ecod_linkcard_render(string $url): string
{
    $parsed = ecod_linkcard_parse($url);
    if ($parsed === null) {
        return '';
    }

    if ($parsed['type'] === 'post') {
        return ecod_linkcard_post_html((int) $parsed['key']);
    }

    return ecod_linkcard_meta_html($parsed['type'], (string) $parsed['key']);
}

/**
 * 正文过滤：独占一段的站内地址 → 卡片
 */
function ecod_linkcard_apply(string $html): string
{
    if ($html === '' || !ecod_linkcard_on() || stripos($html, '<a ') === false) {
        return $html;
    }

    /* 1. 保护代码块：里面的地址原样保留 */
    $blocks = [];
    $html = preg_replace_callback(
        '#<(pre|code)\b[^>]*>.*?</\1>#is',
        static function (array $m) use (&$blocks): string {
            $key = "\x1A" . count($blocks) . "\x1A";
            $blocks[$key] = $m[0];
            return $key;
        },
        $html
    ) ?? $html;

    /* 2. 段落里只有一个链接：地址是「这一段的全部内容」才成卡 */
    $html = preg_replace_callback(
        '#<p>\s*(<a\s[^>]*>.*?</a>)\s*</p>#is',
        static function (array $m): string {
            $anchor = $m[1];

            /* 图片链接 / 显式豁免：保持原样 */
            if (stripos($anchor, '<img') !== false || stripos($anchor, 'data-no-card') !== false) {
                return $m[0];
            }
            if (!preg_match('#\shref\s*=\s*["\']([^"\']+)["\']#i', $anchor, $hm)) {
                return $m[0];
            }

            $card = ecod_linkcard_render(html_entity_decode($hm[1], ENT_QUOTES, 'UTF-8'));
            return $card !== '' ? $card : $m[0];
        },
        $html
    ) ?? $html;

    /* 3. 没被自动成链的裸地址段落 */
    $html = preg_replace_callback(
        '#<p>\s*(https?://[^\s<]+)\s*</p>#i',
        static function (array $m): string {
            $card = ecod_linkcard_render(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
            return $card !== '' ? $card : $m[0];
        },
        $html
    ) ?? $html;

    if ($blocks) {
        $html = strtr($html, $blocks);
    }

    return $html;
}

/* ------------------------------------------------------------------
 * 短代码：[cat slug="dev"] / [tag slug="php"]
 * 自动识别之外的手写入口（同一套渲染，参数给 id 时按 mid 取）
 * ---------------------------------------------------------------- */

/**
 * [cat slug="dev"] / [cat dev] / [cat id=3]
 */
function ecod_sc_cat(string $content): string
{
    if (!function_exists('ecod_sc_self')) {
        return $content;
    }

    return ecod_sc_self('cat', $content, static function (array $attrs): string {
        $key = (string) ($attrs['slug'] ?? $attrs['id'] ?? $attrs['type'] ?? '');
        return ecod_linkcard_meta_html('category', $key);
    });
}

/**
 * [tag slug="php"] / [tag php] / [tag id=7]
 */
function ecod_sc_tag(string $content): string
{
    if (!function_exists('ecod_sc_self')) {
        return $content;
    }

    return ecod_sc_self('tag', $content, static function (array $attrs): string {
        $key = (string) ($attrs['slug'] ?? $attrs['id'] ?? $attrs['type'] ?? '');
        return ecod_linkcard_meta_html('tag', $key);
    });
}
