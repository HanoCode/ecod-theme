<?php
/**
 * Ecod Theme · SEO 元信息与 sitemap
 *
 * 参考项目（Puock / handsome / Joe）在这一点上集体缺失：
 * canonical 全站指向首页、没有 OG/Twitter Card、没有 JSON-LD。
 * 这里补齐单页 canonical、社交卡片、结构化数据，以及实时 sitemap.xml。
 *
 * 约束：
 *   1. 不额外建表、不写缓存文件，全部走主查询或一次轻量 SQL；
 *   2. 只在浅层输出，失败一律静默降级，不影响页面渲染。
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
 * 设置响应状态码
 *
 * Typecho 在 shutdown 时会用 \Typecho\Response 里的状态覆盖 header()，
 * 只调 http_response_code() 不生效，两处都要写。
 */
function ecod_http_status(int $status): void
{
    if (!headers_sent()) {
        http_response_code($status);
        try {
            \Typecho\Response::getInstance()->setStatus($status);
        } catch (\Throwable $e) {
        }
    }
}

/* ------------------------------------------------------------------
 * 基础取值
 * ---------------------------------------------------------------- */

/**
 * 站点根地址（无尾斜杠）
 */
function ecod_seo_site(): string
{
    try {
        return rtrim((string) \Utils\Helper::options()->siteUrl, '/');
    } catch (\Throwable $e) {
        return '';
    }
}

/**
 * 补全为绝对地址（og:image / JSON-LD 不接受相对路径）
 */
function ecod_seo_abs(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }
    if (strpos($url, '//') === 0) {
        $scheme = ecod_seo_is_ssl() ? 'https:' : 'http:';
        return $scheme . $url;
    }
    return ecod_seo_site() . '/' . ltrim($url, '/');
}

/**
 * 当前是否 HTTPS
 */
function ecod_seo_is_ssl(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    return isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
        && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
}

/**
 * 页面标题（与 <title> 共用一套拼装逻辑）
 */
function ecod_seo_title($archive = null): string
{
    $site = '';
    try {
        $site = (string) \Utils\Helper::options()->title;
    } catch (\Throwable $e) {
    }

    $prefix = '';
    if ($archive) {
        try {
            /* 分类 / 标签增强：独立 SEO 标题最优先（规格 §32），空则回退默认格式 */
            $metaTitle = '';
            if (function_exists('ecod_meta_meta_for_archive')) {
                $metaTitle = trim((string) (ecod_meta_meta_for_archive($archive)['seo_title'] ?? ''));
            }

            if ($metaTitle !== '') {
                $prefix = $metaTitle;
            } else {
                ob_start();
                $archive->archiveTitle([
                    'category' => _t('分类 %s 下的文章'),
                    'search'   => _t('包含关键字 %s 的文章'),
                    'tag'      => _t('标签 %s 下的文章'),
                    'author'   => _t('%s 发布的文章'),
                ], '', ' - ');
                $prefix = trim((string) ob_get_clean());
                /* archiveTitle 在末页会多一个空连接符，收一下 */
                $prefix = rtrim($prefix, "-–— \t");
            }

            /* 专题详情：标题用「专题名 · 专题」，否则每个专题都是同一个页面标题 */
            if (function_exists('ecod_topic_current')) {
                $topicNow = ecod_topic_current();
                if ($topicNow !== null) {
                    $prefix = (string) $topicNow['topic']['name'] . ' · ' . _t('专题');
                }
            }
        } catch (\Throwable $e) {
            $prefix = '';
        }
    }

    return $prefix !== '' ? $prefix . ' - ' . $site : $site;
}

/**
 * 规范化当前页地址
 *
 * 文章 / 独立页面用 permalink 最权威；其余页面取当前请求地址，
 * 并剔除营销参数与主题自己的功能参数，避免同一内容出现多个 URL。
 */
function ecod_seo_canonical($archive = null): string
{
    if ($archive) {
        try {
            if (($archive->is('post') || $archive->is('page')) && !empty($archive->permalink)) {
                $url = (string) $archive->permalink;
                /* 专题详情：同一页面下的不同专题是不同内容，canonical 必须带上 ?t= */
                if (function_exists('ecod_topic_current')) {
                    $topicNow = ecod_topic_current();
                    if ($topicNow !== null) {
                        $sep = strpos($url, '?') === false ? '?' : '&';
                        return $url . $sep . 't=' . rawurlencode((string) $topicNow['topic']['slug']);
                    }
                }
                return $url;
            }
        } catch (\Throwable $e) {
        }
    }

    try {
        $url = (string) \Typecho\Request::getInstance()->getRequestUrl();
    } catch (\Throwable $e) {
        return ecod_seo_site() . '/';
    }

    $parts = parse_url($url);
    if (!$parts || empty($parts['host'])) {
        return $url;
    }

    $query = [];
    if (!empty($parts['query'])) {
        parse_str((string) $parts['query'], $query);
    }
    foreach (array_keys($query) as $key) {
        if (preg_match('/^(utm_|ecod[-_]|_pjax$|replyTo$|from$|ref$|_$)/i', (string) $key)) {
            unset($query[$key]);
        }
    }

    $out = ($parts['scheme'] ?? 'http') . '://' . $parts['host']
        . (isset($parts['port']) ? ':' . $parts['port'] : '')
        . ($parts['path'] ?? '/');

    return $query ? $out . '?' . http_build_query($query) : $out;
}

/**
 * 页面描述
 */
function ecod_seo_description($archive = null): string
{
    /* 分类 / 标签增强：独立 SEO 描述最优先（规格 §32） */
    if (function_exists('ecod_meta_meta_for_archive')) {
        $metaDesc = trim((string) (ecod_meta_meta_for_archive($archive)['seo_desc'] ?? ''));
        if ($metaDesc !== '') {
            return $metaDesc;
        }
    }

    /* 专题详情：用专题自己的描述，比页面正文更贴题 */
    if (function_exists('ecod_topic_current')) {
        $topicNow = ecod_topic_current();
        if ($topicNow !== null && trim((string) $topicNow['topic']['desc']) !== '') {
            return trim((string) $topicNow['topic']['desc']);
        }
    }

    if ($archive) {
        try {
            if ($archive->is('post') || $archive->is('page')) {
                $text = trim((string) ecod_excerpt($archive, 150));
                if ($text !== '') {
                    return $text;
                }
                $text = ecod_seo_plain((string) ($archive->content ?? ''), 150);
                if ($text !== '') {
                    return $text;
                }
            }
            if ($archive->is('search')) {
                return '站内搜索结果';
            }
        } catch (\Throwable $e) {
        }
    }

    $slogan = trim((string) ecod_option('ESlogan', ''));
    if ($slogan !== '') {
        return $slogan;
    }

    try {
        return trim((string) \Utils\Helper::options()->description);
    } catch (\Throwable $e) {
        return '';
    }
}

/**
 * 纯文本压缩（去掉 Markdown 记号与多余空白）
 */
function ecod_seo_plain(string $html, int $length = 160): string
{
    $text = strip_tags($html);
    $text = preg_replace('/!\[[^\]]*\]\([^)]*\)/u', '', $text);
    $text = preg_replace('/\[([^\]]*)\]\([^)]*\)/u', '$1', $text);
    $text = preg_replace('/[#>*`_~\-]{2,}/u', ' ', $text);
    $text = preg_replace('/\s+/u', ' ', (string) $text);
    $text = trim((string) $text);
    if ($length > 0 && mb_strlen($text, 'UTF-8') > $length) {
        $text = mb_substr($text, 0, $length, 'UTF-8') . '…';
    }
    return $text;
}

/**
 * 分享图：文章封面 → 首页大图 → 空
 */
function ecod_seo_image($archive = null): string
{
    if ($archive) {
        try {
            /* 分类 / 标签增强：自定义封面最优先（规格 §32） */
            if (function_exists('ecod_meta_meta_for_archive')) {
                $metaCover = trim((string) (ecod_meta_meta_for_archive($archive)['cover'] ?? ''));
                if ($metaCover !== '') {
                    return ecod_seo_abs($metaCover);
                }
            }
            if ($archive->is('post') || $archive->is('page')) {
                $images = ecod_thumbnails($archive, 1);
                if (!empty($images[0])) {
                    return ecod_seo_abs((string) $images[0]);
                }
            }
        } catch (\Throwable $e) {
        }
    }

    $banner = trim((string) ecod_option('EBanner', ''));
    return $banner !== '' ? ecod_seo_abs($banner) : '';
}

/**
 * 正文词数（中文按字算，英文按词算，与阅读时长共用）
 */
function ecod_word_count(string $content): int
{
    $text = trim(strip_tags($content));
    if ($text === '') {
        return 0;
    }
    $cjk = preg_match_all('/[\x{4e00}-\x{9fff}\x{3400}-\x{4dbf}]/u', $text, $m);
    $latin = preg_match_all('/[A-Za-z0-9]+/', $text, $m2);
    return (int) $cjk + (int) $latin;
}

/* ------------------------------------------------------------------
 * <head> 输出
 * ---------------------------------------------------------------- */

/**
 * 输出 canonical / robots / OG / Twitter / JSON-LD
 *
 * 必须在 $this->header() 之后调用：Typecho 自带的 description、keywords
 * 由它输出，这里只补它没有的部分。
 */
function ecod_seo_head($archive = null, bool $hasSocial = false): void
{
    $title = ecod_seo_title($archive);
    $canonical = ecod_seo_canonical($archive);
    $desc = ecod_seo_description($archive);
    $image = ecod_seo_image($archive);
    $site = ecod_seo_site();

    $isSingle = false;
    $isNoIndex = false;
    $metaKeywords = '';
    try {
        $isSingle = $archive && ($archive->is('post') || $archive->is('page'));
        $isNoIndex = $archive && ($archive->is('search') || $archive->is('404'));

        /* 分类 / 标签增强：noindex 开关与独立关键词（规格 §32） */
        if (function_exists('ecod_meta_meta_for_archive')) {
            $metaData = ecod_meta_meta_for_archive($archive);
            if (($metaData['noindex'] ?? '') === '1') {
                $isNoIndex = true;
            }
            $metaKeywords = trim((string) ($metaData['seo_keywords'] ?? ''));
        }
    } catch (\Throwable $e) {
    }

    $e = static function (string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };

    echo "\n" . '<!-- SEO -->' . "\n";

    if ($canonical !== '') {
        echo '<link rel="canonical" href="' . $e($canonical) . '">' . "\n";
    }

    echo '<meta name="robots" content="' . ($isNoIndex ? 'noindex,follow' : 'index,follow,max-image-preview:large') . '">' . "\n";

    if ($metaKeywords !== '') {
        echo '<meta name="keywords" content="' . $e($metaKeywords) . '">' . "\n";
    }

    /* Typecho 1.3 的 $this->header() 已输出一组社交标签，避免重复 */
    if (!$hasSocial) {
        echo '<meta property="og:type" content="' . ($isSingle ? 'article' : 'website') . '">' . "\n";
        echo '<meta property="og:site_name" content="' . $e(ecod_seo_site_name()) . '">' . "\n";
        echo '<meta property="og:title" content="' . $e($title) . '">' . "\n";
        if ($desc !== '') {
            echo '<meta property="og:description" content="' . $e($desc) . '">' . "\n";
        }
        if ($canonical !== '') {
            echo '<meta property="og:url" content="' . $e($canonical) . '">' . "\n";
        }
        echo '<meta name="twitter:card" content="' . ($image !== '' ? 'summary_large_image' : 'summary') . '">' . "\n";
        echo '<meta name="twitter:title" content="' . $e($title) . '">' . "\n";
        if ($desc !== '') {
            echo '<meta name="twitter:description" content="' . $e($desc) . '">' . "\n";
        }
    }

    if ($image !== '') {
        echo '<meta property="og:image" content="' . $e($image) . '">' . "\n";
        echo '<meta name="twitter:image" content="' . $e($image) . '">' . "\n";
    }
    echo '<meta property="og:locale" content="zh_CN">' . "\n";

    if ($isSingle) {
        try {
            $created = (int) $archive->created;
            $modified = (int) ($archive->modified ?: $archive->created);
            echo '<meta property="article:published_time" content="' . date('c', $created) . '">' . "\n";
            echo '<meta property="article:modified_time" content="' . date('c', $modified) . '">' . "\n";
            if (!empty($archive->categories)) {
                foreach ($archive->categories as $cat) {
                    $name = is_array($cat) ? ($cat['name'] ?? '') : ($cat->name ?? '');
                    if ($name !== '') {
                        echo '<meta property="article:section" content="' . $e((string) $name) . '">' . "\n";
                    }
                }
            }
        } catch (\Throwable $e2) {
        }
    }

    ecod_seo_jsonld($archive, $canonical, $desc, $image, $title);
}

/**
 * 站点名（og:site_name 用）
 */
function ecod_seo_site_name(): string
{
    try {
        return (string) \Utils\Helper::options()->title;
    } catch (\Throwable $e) {
        return '';
    }
}

/**
 * 结构化数据
 *
 * 每页输出 WebSite（带 SearchAction），文章页追加 Article + BreadcrumbList。
 * 用 @graph 合并，避免多个 script 标签。
 */
function ecod_seo_jsonld($archive = null, string $canonical = '', string $desc = '', string $image = '', string $title = ''): void
{
    $site = ecod_seo_site();
    $siteName = ecod_seo_site_name();
    $graph = [];

    $graph[] = [
        '@type'       => 'WebSite',
        '@id'         => $site . '/#website',
        'url'         => $site . '/',
        'name'        => $siteName,
        'inLanguage'  => 'zh-CN',
        'publisher'   => [
            '@type' => 'Organization',
            '@id'   => $site . '/#organization',
            'name'  => $siteName,
            'url'   => $site . '/',
        ],
        'potentialAction' => [
            '@type'       => 'SearchAction',
            'target'      => [
                '@type'       => 'EntryPoint',
                'urlTemplate' => $site . '/?s={search_term_string}',
            ],
            'query-input' => 'required name=search_term_string',
        ],
    ];

    $isSingle = false;
    try {
        $isSingle = $archive && ($archive->is('post') || $archive->is('page'));
    } catch (\Throwable $e) {
    }

    if ($isSingle) {
        $author = '';
        $authorUrl = '';
        $bareTitle = $title;
        try {
            $bareTitle = trim((string) $archive->title) ?: $title;
            $author = (string) $archive->author->screenName;
            $authorUrl = (string) $archive->author->permalink;
        } catch (\Throwable $e) {
        }

        $words = 0;
        try {
            $words = ecod_word_count((string) $archive->content);
        } catch (\Throwable $e) {
        }

        $article = [
            '@type'            => 'Article',
            '@id'              => $canonical . '#article',
            'headline'         => mb_substr($bareTitle, 0, 110, 'UTF-8'),
            'description'      => $desc,
            'datePublished'    => date('c', (int) $archive->created),
            'dateModified'     => date('c', (int) ($archive->modified ?: $archive->created)),
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical],
            'inLanguage'       => 'zh-CN',
            'isPartOf'         => ['@id' => $site . '/#website'],
        ];
        if ($image !== '') {
            $article['image'] = [$image];
        }
        if ($author !== '') {
            $article['author'] = ['@type' => 'Person', 'name' => $author];
            if ($authorUrl !== '') {
                $article['author']['url'] = $authorUrl;
            }
        }
        $article['publisher'] = ['@type' => 'Organization', 'name' => $siteName, 'url' => $site . '/'];
        if ($words > 0) {
            $article['wordCount'] = $words;
        }
        try {
            $article['commentCount'] = (int) $archive->commentsNum;
        } catch (\Throwable $e) {
        }
        try {
            if (!empty($archive->categories)) {
                $names = [];
                foreach ($archive->categories as $cat) {
                    $names[] = is_array($cat) ? ($cat['name'] ?? '') : ($cat->name ?? '');
                }
                $names = array_values(array_filter($names));
                if ($names) {
                    $article['articleSection'] = $names[0];
                }
            }
        } catch (\Throwable $e) {
        }

        $graph[] = $article;

        /* 面包屑：首页 → 分类 → 当前 */
        $items = [[
            '@type'    => 'ListItem',
            'position' => 1,
            'name'     => '首页',
            'item'     => $site . '/',
        ]];
        $pos = 2;
        try {
            if (!empty($archive->categories)) {
                $cat = $archive->categories[0];
                $catName = is_array($cat) ? ($cat['name'] ?? '') : ($cat->name ?? '');
                $catUrl = is_array($cat) ? ($cat['permalink'] ?? '') : ($cat->permalink ?? '');
                if ($catName !== '') {
                    $items[] = [
                        '@type'    => 'ListItem',
                        'position' => $pos++,
                        'name'     => (string) $catName,
                        'item'     => (string) $catUrl,
                    ];
                }
            }
        } catch (\Throwable $e) {
        }
        $items[] = ['@type' => 'ListItem', 'position' => $pos, 'name' => $bareTitle, 'item' => $canonical];

        $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
    } else {
        $graph[] = [
            '@type'      => 'WebPage',
            '@id'        => $canonical !== '' ? $canonical : $site . '/',
            'url'        => $canonical,
            'name'       => $title,
            'description' => $desc,
            'isPartOf'   => ['@id' => $site . '/#website'],
            'inLanguage' => 'zh-CN',
        ];
    }

    $json = json_encode(
        ['@context' => 'https://schema.org', '@graph' => $graph],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
    );

    if ($json !== false) {
        echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
    }
}

/**
 * 记录主循环里渲染过的卡片，供页脚输出 ItemList
 *
 * 只收主 Archive 的卡片，首页其余模块（推荐位、热门等）的
 * 小组件不在此列，避免列表混入重复来源。
 */
function ecod_itemlist_push(string $title, string $url): void
{
    if ($title === '' || $url === '') {
        return;
    }

    if (!isset($GLOBALS['ecodItemList'])) {
        $GLOBALS['ecodItemList'] = [];
    }

    /* 同一页同一篇只记一次 */
    foreach ($GLOBALS['ecodItemList'] as $item) {
        if ($item['url'] === $url) {
            return;
        }
    }

    if (count($GLOBALS['ecodItemList']) >= 100) {
        return;
    }

    $GLOBALS['ecodItemList'][] = ['name' => $title, 'url' => $url];
}

/**
 * 页脚输出列表页的 ItemList + CollectionPage
 *
 * 头部的 JSON-LD 渲染时主循环还没跑，拿不到当页条目；
 * Google 对 JSON-LD 的位置不敏感，放在页脚同样生效。
 */
function ecod_itemlist_output(): void
{
    $items = $GLOBALS['ecodItemList'] ?? [];
    if (!$items) {
        return;
    }

    $archive = null;

    try {
        $archive = \Widget\Archive::alloc();

        if ($archive->is('single')) {
            return;
        }
    } catch (\Throwable $e) {
        return;
    }

    $site = ecod_seo_site();
    $canonical = '';

    try {
        $canonical = ecod_seo_canonical($archive);
    } catch (\Throwable $e) {
    }

    $selfUrl = $canonical !== '' ? $canonical : $site . '/';
    $list = [];

    foreach (array_values($items) as $pos => $item) {
        $list[] = [
            '@type'    => 'ListItem',
            'position' => $pos + 1,
            'name'     => $item['name'],
            'url'      => $item['url'],
        ];
    }

    $graph = [
        [
            '@type'      => 'CollectionPage',
            '@id'        => $selfUrl . '#collection',
            'url'        => $selfUrl,
            'name'       => trim((string) (\Widget\Options::alloc()->title)),
            'isPartOf'   => ['@id' => $site . '/#website'],
            'inLanguage' => 'zh-CN',
        ],
        [
            '@type'           => 'ItemList',
            '@id'             => $selfUrl . '#itemlist',
            'mainEntityOfPage'=> $selfUrl,
            'numberOfItems'   => count($list),
            'itemListElement' => $list,
        ],
    ];

    $json = json_encode(
        ['@context' => 'https://schema.org', '@graph' => $graph],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
    );

    if ($json !== false) {
        echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
    }

    unset($GLOBALS['ecodItemList']);
}

/* ------------------------------------------------------------------
 * sitemap.xml / robots.txt
 * ---------------------------------------------------------------- */

/**
 * 命中 /sitemap.xml 或 /robots.txt 时直接输出并结束
 *
 * 由 themeInit 调用。此时尚未有任何输出，可以安全地改写响应头。
 */
function ecod_sitemap_maybe_output(): void
{
    try {
        $path = (string) \Typecho\Request::getInstance()->getPathInfo();
    } catch (\Throwable $e) {
        return;
    }

    $path = '/' . trim(str_replace('\\', '/', $path), '/');
    $path = preg_replace('#/+#', '/', $path);

    if ($path === '/sitemap.xml') {
        ecod_sitemap_output();
    }

    if ($path === '/robots.txt') {
        /* 物理文件优先，避免盖掉站长自己的规则 */
        $physical = __DIR__ . '/../../robots.txt';
        if (is_file($physical)) {
            return;
        }
        ecod_robots_output();
    }
}

/**
 * 输出 sitemap（动态，需 rewrite 才能落到 /sitemap.xml）
 *
 * 内容走文件缓存（TTL 与上面的 max-age=3600 对齐）：爬虫反复抓取
 * 不再每次全表查询；后台「写入静态 sitemap」会主动失效这份缓存。
 */
function ecod_sitemap_output(): void
{
    /* ThemeInit 阶段路由已把未知路径标成 404，这里显式改回 200 */
    ecod_http_status(200);

    if (!headers_sent()) {
        header('Content-Type: application/xml; charset=UTF-8');
        header('Cache-Control: public, max-age=3600');
    }

    echo (string) ecod_cache_remember('sitemap', 3600, 'ecod_sitemap_xml');
    exit;
}

/**
 * 生成 sitemap 内容
 */
function ecod_sitemap_xml(): string
{
    /* 路由表平时由 Widget\Init 装载，独立入口 / CLI 调用时需要显式补上 */
    try {
        \Typecho\Router::setRoutes(\Widget\Options::alloc()->routingTable);
    } catch (\Throwable $e) {
    }

    $site = ecod_seo_site();
    $index = $site;
    try {
        /* rewrite 关闭时前台链接带 index.php，sitemap 必须与之一致 */
        $rewrite = (bool) \Widget\Options::alloc()->rewrite
            || (defined('__TYPECHO_REWRITE__') && __TYPECHO_REWRITE__);
        if (!$rewrite) {
            $index = $site . '/index.php';
        }
    } catch (\Throwable $e) {
    }
    $urls = [];

    /* 首页 */
    $urls[] = ['loc' => $site . '/', 'lastmod' => time(), 'changefreq' => 'daily', 'priority' => '1.0'];

    try {
        $db = \Typecho\Db::get();
    } catch (\Throwable $e) {
        return '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
    }

    /* 文章：最新的 800 篇，加密文章不进 sitemap */
    try {
        $rows = $db->fetchAll(
            $db->select('cid', 'slug', 'created', 'modified', 'password', 'type')
                ->from('table.contents')
                ->where('type = ?', 'post')
                ->where('status = ?', 'publish')
                ->where('created < ?', time())
                ->order('created', \Typecho\Db::SORT_DESC)
                ->limit(800)
        );
        foreach ($rows as $row) {
            if (!empty($row['password'])) {
                continue;
            }
            $link = ecod_sitemap_link('post', $row, $index);
            if ($link === '') {
                continue;
            }
            $urls[] = [
                'loc'        => $link,
                'lastmod'    => (int) ($row['modified'] ?: $row['created']),
                'changefreq' => 'monthly',
                'priority'   => '0.8',
            ];
        }
    } catch (\Throwable $e) {
    }

    /* 独立页面 */
    try {
        $rows = $db->fetchAll(
            $db->select('cid', 'slug', 'created', 'modified', 'password')
                ->from('table.contents')
                ->where('type = ?', 'page')
                ->where('status = ?', 'publish')
                ->order('order', \Typecho\Db::SORT_ASC)
        );
        foreach ($rows as $row) {
            if (!empty($row['password'])) {
                continue;
            }
            $link = ecod_sitemap_link('page', $row, $index);
            if ($link === '') {
                continue;
            }
            $urls[] = [
                'loc'        => $link,
                'lastmod'    => (int) ($row['modified'] ?: $row['created']),
                'changefreq' => 'weekly',
                'priority'   => '0.6',
            ];
        }
    } catch (\Throwable $e) {
    }

    /* 分类与标签 */
    foreach ([['category', '0.7', 'weekly'], ['tag', '0.5', 'weekly']] as $meta) {
        try {
            $rows = $db->fetchAll(
                $db->select('mid', 'slug', 'type', 'count')
                    ->from('table.metas')
                    ->where('type = ?', $meta[0])
                    ->order('count', \Typecho\Db::SORT_DESC)
                    ->limit($meta[0] === 'tag' ? 300 : 100)
            );
            foreach ($rows as $row) {
                if ((int) ($row['count'] ?? 0) <= 0) {
                    continue;
                }
                $link = ecod_sitemap_link($meta[0], $row, $index);
                if ($link === '') {
                    continue;
                }
                $urls[] = ['loc' => $link, 'changefreq' => $meta[2], 'priority' => $meta[1]];
            }
        } catch (\Throwable $e) {
        }
    }

    $e = static function (string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    };

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as $url) {
        $xml .= "  <url>\n";
        $xml .= '    <loc>' . $e((string) $url['loc']) . "</loc>\n";
        if (!empty($url['lastmod'])) {
            $xml .= '    <lastmod>' . date('c', (int) $url['lastmod']) . "</lastmod>\n";
        }
        if (!empty($url['changefreq'])) {
            $xml .= '    <changefreq>' . $url['changefreq'] . "</changefreq>\n";
        }
        if (!empty($url['priority'])) {
            $xml .= '    <priority>' . $url['priority'] . "</priority>\n";
        }
        $xml .= "  </url>\n";
    }
    $xml .= '</urlset>';

    return $xml;
}

/**
 * 把 sitemap 写成站点根目录下的静态文件
 *
 * 没有开启 rewrite 的站点无法把 /sitemap.xml 交给 PHP，
 * 因此提供一键落盘：写成功后 /sitemap.xml 由 Web 服务器直接返回。
 *
 * @return array{ok: bool, message: string, path: string}
 */
function ecod_sitemap_write(): array
{
    $root = rtrim((string) __TYPECHO_ROOT_DIR__, '/');
    $file = $root . '/sitemap.xml';

    if (!is_dir($root) || !is_writable($root)) {
        return ['ok' => false, 'message' => '站点根目录不可写，请检查权限后重试', 'path' => $file];
    }

    $xml = ecod_sitemap_xml();
    if ($xml === '' || strpos($xml, '<urlset') === false) {
        return ['ok' => false, 'message' => 'sitemap 内容生成失败', 'path' => $file];
    }

    $tmp = $file . '.tmp';
    if (@file_put_contents($tmp, $xml) === false) {
        return ['ok' => false, 'message' => '写入临时文件失败', 'path' => $file];
    }
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        return ['ok' => false, 'message' => '替换 sitemap.xml 失败', 'path' => $file];
    }

    @touch($file);
    ecod_cache_set('EcodSitemapBuilt', time());

    /* 静态文件已更新，动态路由那份 sitemap 文件缓存同步失效（seo.php 可能被
       未加载 cache.php 的入口引入，这里按 account.php 先例就地补载） */
    if (!function_exists('ecod_cache_forget')) {
        require_once __DIR__ . '/../lib/cache.php';
    }
    ecod_cache_forget('sitemap');

    $count = substr_count($xml, '<url>');
    return ['ok' => true, 'message' => '已生成 ' . $count . ' 条地址', 'path' => $file];
}

/**
 * 百度主动推送
 *
 * 只推最近发布且未加密的文章，数量可控，避免一次把全站灌进配额。
 * 需要先在「代码」分组里填 Token；失败只返回提示，不影响任何前台页面。
 *
 * @return array{ok: bool, message: string, count: int}
 */
function ecod_baidu_push(int $limit = 20): array
{
    /* api.php 不加载 functions.php，这里直接读选项，避免依赖主题上下文 */
    $token = '';
    try {
        $token = trim((string) (\Widget\Options::alloc()->EBaidu ?? ''));
    } catch (\Throwable $e) {
    }
    $site = ecod_seo_site();

    if ($token === '') {
        return ['ok' => false, 'message' => '请先在「代码 → 百度推送 Token」里填写 Token', 'count' => 0];
    }
    if ($site === '') {
        return ['ok' => false, 'message' => '取不到站点地址', 'count' => 0];
    }

    try {
        \Typecho\Router::setRoutes(\Widget\Options::alloc()->routingTable);
    } catch (\Throwable $e) {
    }

    $index = $site;
    try {
        $rewrite = (bool) \Widget\Options::alloc()->rewrite
            || (defined('__TYPECHO_REWRITE__') && __TYPECHO_REWRITE__);
        if (!$rewrite) {
            $index = $site . '/index.php';
        }
    } catch (\Throwable $e) {
    }

    $urls = [];
    try {
        $db = \Typecho\Db::get();
        $rows = $db->fetchAll(
            $db->select('cid', 'slug', 'created', 'modified', 'password', 'type')
                ->from('table.contents')
                ->where('type = ?', 'post')
                ->where('status = ?', 'publish')
                ->where('created < ?', time())
                ->order('created', \Typecho\Db::SORT_DESC)
                ->limit(max(1, min(100, $limit)))
        );
        foreach ($rows as $row) {
            if (!empty($row['password'])) {
                continue;
            }
            $link = ecod_sitemap_link('post', $row, $index);
            if ($link !== '') {
                $urls[] = $link;
            }
        }
    } catch (\Throwable $e) {
        return ['ok' => false, 'message' => '读取文章失败', 'count' => 0];
    }

    if (!$urls) {
        return ['ok' => false, 'message' => '没有可推送的文章', 'count' => 0];
    }

    /* 增量去重：24 小时内推送成功的地址不再推（主动推送对已收录 URL 无效，
     * 重复推只会白白消耗每日配额） */
    $urls = array_values(array_filter($urls, static function (string $u): bool {
        return !ecod_baidu_recent($u);
    }));
    if (!$urls) {
        return ['ok' => true, 'message' => '候选文章最近 24 小时内都已推送过，跳过（百度主动推送对重复 URL 无效）', 'count' => 0];
    }

    $endpoint = 'http://data.zz.baidu.com/urls?site=' . urlencode($site) . '&token=' . urlencode($token);
    $body = implode("\n", $urls);

    try {
        $client = \Typecho\Http\Client::get();
        $client->setMethod(\Typecho\Http\Client::METHOD_POST)
            ->setData($body)
            ->setMultipart(false)
            ->setHeader('Content-Type', 'text/plain')
            ->setTimeout(8);
        $result = (string) $client->send($endpoint);
        $json = json_decode($result, true);

        if (is_array($json) && isset($json['success'])) {
            $okCount = (int) $json['success'];
            $rest = isset($json['remain']) ? '，今日剩余 ' . (int) $json['remain'] . ' 条' : '';
            foreach ($urls as $u) {
                ecod_baidu_log($u, true, 'batch:' . $okCount);
            }
            return ['ok' => true, 'message' => '已推送 ' . $okCount . ' 条' . $rest, 'count' => $okCount];
        }
        if (is_array($json) && isset($json['error'])) {
            foreach ($urls as $u) {
                ecod_baidu_log($u, false, (string) $json['error']);
            }
            return ['ok' => false, 'message' => '百度返回：' . (string) $json['error'], 'count' => 0];
        }
        return ['ok' => false, 'message' => '百度返回内容无法解析：' . mb_substr($result, 0, 120, 'UTF-8'), 'count' => 0];
    } catch (\Throwable $e) {
        return ['ok' => false, 'message' => '推送请求失败：' . $e->getMessage(), 'count' => 0];
    }
}

/**
 * 把 robots.txt 写到站点根目录（已存在则不覆盖）
 *
 * @return array{ok: bool, message: string, path: string}
 */
function ecod_robots_write(): array
{
    $root = rtrim((string) __TYPECHO_ROOT_DIR__, '/');
    $file = $root . '/robots.txt';

    if (is_file($file)) {
        return ['ok' => false, 'message' => 'robots.txt 已存在，未覆盖', 'path' => $file];
    }
    if (!is_dir($root) || !is_writable($root)) {
        return ['ok' => false, 'message' => '站点根目录不可写', 'path' => $file];
    }

    $site = ecod_seo_site();
    $text = "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /action/\n"
        . "Disallow: /*?s=\nDisallow: /*replyTo=\n\n"
        . 'Sitemap: ' . $site . "/sitemap.xml\n";

    if (@file_put_contents($file, $text) === false) {
        return ['ok' => false, 'message' => '写入 robots.txt 失败', 'path' => $file];
    }

    return ['ok' => true, 'message' => 'robots.txt 已生成', 'path' => $file];
}

/**
 * 用路由表还原 permalink（不初始化 Widget，避免 sitemap 触发多次查询）
 */
function ecod_sitemap_link(string $route, array $row, string $site): string
{
    try {
        $link = \Typecho\Router::url($route, $row, $site);
    } catch (\Throwable $e) {
        return '';
    }
    if ($link === '' || $link === '#') {
        return '';
    }
    if (strpos($link, '{') !== false) {
        return '';
    }
    return $link;
}

/**
 * 输出 robots.txt
 */
function ecod_robots_output(): void
{
    ecod_http_status(200);

    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: public, max-age=3600');
    }

    $site = ecod_seo_site();

    echo "User-agent: *\n";
    echo "Allow: /\n";
    echo "Disallow: /admin/\n";
    echo "Disallow: /action/\n";
    echo "Disallow: /*?s=\n";
    echo "Disallow: /*replyTo=\n";
    echo "\n";
    echo 'Sitemap: ' . $site . "/sitemap.xml\n";

    exit;
}

/* ------------------------------------------------------------------
 * 百度主动推送：去重日志与发布增量推送
 *
 * ecod_push_log：一行一次推送记录（url 唯一），24 小时内推送成功的
 * 地址不再重复推。主动推送只对「新 / 更新」的 URL 有意义，重复推
 * 纯耗每日配额。发布 / 更新文章时经 finishPublish 自动单条增量。
 * ---------------------------------------------------------------- */

/**
 * 日志表名（带前缀）
 */
function ecod_baidu_log_table(): string
{
    try {
        return \Typecho\Db::get()->getPrefix() . 'ecod_push_log';
    } catch (\Throwable $e) {
        return 'typecho_ecod_push_log';
    }
}

/**
 * 建表（首次写入时自愈，与 ecod_notifs_ensure 同款套路）
 */
function ecod_baidu_log_ensure(?\Typecho\Db $db = null): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }

    try {
        $db = $db ?: \Typecho\Db::get();
        $table = ecod_baidu_log_table();
        $adapter = ecod_schema_adapter($db);

        if ($adapter === 'sqlite') {
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $table . '" ('
                . ' "id" INTEGER PRIMARY KEY AUTOINCREMENT,'
                . ' "url" VARCHAR(190) NOT NULL UNIQUE,'
                . ' "ok" INTEGER NOT NULL DEFAULT 0,'
                . ' "resp" VARCHAR(190) NOT NULL DEFAULT \'\','
                . ' "created" INTEGER NOT NULL DEFAULT 0)'
            );
        } elseif ($adapter === 'pgsql') {
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $table . '" ('
                . ' "id" SERIAL PRIMARY KEY,'
                . ' "url" VARCHAR(190) NOT NULL UNIQUE,'
                . ' "ok" INTEGER NOT NULL DEFAULT 0,'
                . ' "resp" VARCHAR(190) NOT NULL DEFAULT \'\','
                . ' "created" INTEGER NOT NULL DEFAULT 0)'
            );
        } else {
            $db->query(
                'CREATE TABLE IF NOT EXISTS `' . $table . '` ('
                . ' `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,'
                . ' `url` VARCHAR(190) NOT NULL,'
                . ' `ok` TINYINT UNSIGNED NOT NULL DEFAULT 0,'
                . ' `resp` VARCHAR(190) NOT NULL DEFAULT \'\','
                . ' `created` INT UNSIGNED NOT NULL DEFAULT 0,'
                . ' PRIMARY KEY (`id`), UNIQUE KEY `url` (`url`)'
                . ') DEFAULT CHARSET=utf8mb4'
            );
        }
        return $ok = true;
    } catch (\Throwable $e) {
        error_log('[ecod-baidu] ensure: ' . $e->getMessage());
        return $ok = false;
    }
}

/**
 * 写一条推送记录（upsert：重推覆盖状态与时间）
 */
function ecod_baidu_log(string $url, bool $ok, string $resp = ''): void
{
    try {
        $db = \Typecho\Db::get();
        if (!ecod_baidu_log_ensure($db)) {
            return;
        }
        $table = ecod_baidu_log_table();
        $row = [
            'url'     => mb_substr($url, 0, 190, 'UTF-8'),
            'ok'      => $ok ? 1 : 0,
            'resp'    => mb_substr($resp, 0, 190, 'UTF-8'),
            'created' => time(),
        ];
        $exist = $db->fetchRow($db->select('id')->from($table)->where('url = ?', $row['url'])->limit(1));
        if ($exist) {
            $db->query($db->update($table)->rows($row)->where('url = ?', $row['url']));
        } else {
            $db->query($db->insert($table)->rows($row));
        }
    } catch (\Throwable $e) {
        error_log('[ecod-baidu] log: ' . $e->getMessage());
    }
}

/**
 * 24 小时内是否推送成功过
 */
function ecod_baidu_recent(string $url): bool
{
    try {
        $db = \Typecho\Db::get();
        if (!ecod_baidu_log_ensure($db)) {
            return false;
        }
        $row = $db->fetchRow(
            $db->select('ok', 'created')->from(ecod_baidu_log_table())
                ->where('url = ?', mb_substr($url, 0, 190, 'UTF-8'))
                ->limit(1)
        );
        return $row && (int) $row['ok'] === 1
            && (time() - (int) $row['created']) < 86400;
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * 单条增量推送（发布 / 更新文章时调用）
 *
 * @return array{ok:bool,message:string}
 */
function ecod_baidu_push_one(string $url): array
{
    $token = '';
    try {
        $token = trim((string) (\Widget\Options::alloc()->EBaidu ?? ''));
    } catch (\Throwable $e) {
    }
    if ($token === '') {
        return ['ok' => false, 'message' => '未配置百度推送 Token，跳过'];
    }

    /* 只允许本站地址：防 SSRF，也防误推外链 */
    $site = ecod_seo_site();
    $host = (string) parse_url($url, PHP_URL_HOST);
    $siteHost = (string) parse_url($site, PHP_URL_HOST);
    $siteHost = strtolower(preg_replace('/^www\./i', '', $siteHost) ?: '');
    $host = strtolower(preg_replace('/^www\./i', '', $host) ?: '');
    if ($host === '' || $host !== $siteHost) {
        return ['ok' => false, 'message' => '非本站地址，跳过'];
    }

    if (ecod_baidu_recent($url)) {
        return ['ok' => true, 'message' => '24 小时内已推送过，跳过'];
    }

    $endpoint = 'http://data.zz.baidu.com/urls?site=' . urlencode($site)
        . '&token=' . urlencode($token);
    try {
        $client = \Typecho\Http\Client::get();
        $client->setMethod(\Typecho\Http\Client::METHOD_POST)
            ->setData($url)
            ->setHeader('Content-Type', 'text/plain')
            ->setTimeout(8);
        $result = (string) $client->send($endpoint);
        $json = json_decode($result, true);

        if (is_array($json) && isset($json['success'])) {
            $rest = isset($json['remain']) ? '，今日剩余 ' . (int) $json['remain'] . ' 条' : '';
            ecod_baidu_log($url, true, 'auto');
            return ['ok' => true, 'message' => '已推送' . $rest];
        }
        if (is_array($json) && isset($json['error'])) {
            ecod_baidu_log($url, false, (string) $json['error']);
            return ['ok' => false, 'message' => '百度返回：' . (string) $json['error']];
        }
        ecod_baidu_log($url, false, mb_substr($result, 0, 60, 'UTF-8'));
        return ['ok' => false, 'message' => '推送返回无法解析'];
    } catch (\Throwable $e) {
        ecod_baidu_log($url, false, $e->getMessage());
        return ['ok' => false, 'message' => '推送请求失败：' . $e->getMessage()];
    }
}

/**
 * finishPublish 钩子：文章发布 / 更新时自动单条增量推送
 *
 * 响应结束后再发（fastcgi_finish_request），保存动作零等待；
 * 自动开关「站长 → 收录与性能 → 发布自动推送」（EBaiduAuto）。
 */
function ecod_baidu_auto_push(array $contents, $edit = null): void
{
    try {
        if (ecod_option('EBaiduAuto', 'off') !== 'on') {
            return;
        }
        if ((string) ($contents['type'] ?? 'post') !== 'post'
            || (string) ($contents['status'] ?? '') !== 'publish'
            || !empty($contents['password'])) {
            return;
        }

        /* 在编辑上下文里取 permalink；取不到则按 sitemap 同款路由规则生成 */
        $link = '';
        try {
            if ($edit !== null && isset($edit->permalink)) {
                $link = (string) $edit->permalink;
            }
        } catch (\Throwable $e) {
        }
        if ($link === '' && (int) ($contents['cid'] ?? 0) > 0) {
            try {
                \Typecho\Router::setRoutes(\Widget\Options::alloc()->routingTable);
            } catch (\Throwable $e) {
            }
            $site = ecod_seo_site();
            try {
                $rewrite = (bool) \Widget\Options::alloc()->rewrite
                    || (defined('__TYPECHO_REWRITE__') && __TYPECHO_REWRITE__);
                $site = $rewrite ? $site : $site . '/index.php';
            } catch (\Throwable $e) {
            }
            $link = ecod_sitemap_link('post', $contents, $site);
        }
        if ($link === '') {
            return;
        }

        $job = static function () use ($link): void {
            try {
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }
                $res = ecod_baidu_push_one($link);
                if (!$res['ok'] && strpos($res['message'], '跳过') === false) {
                    error_log('[ecod-baidu] auto: ' . $res['message']);
                }
            } catch (\Throwable $e) {
                error_log('[ecod-baidu] auto: ' . $e->getMessage());
            }
        };

        if (function_exists('fastcgi_finish_request')) {
            register_shutdown_function($job);
        } else {
            $job();
        }
    } catch (\Throwable $e) {
        error_log('[ecod-baidu] hook: ' . $e->getMessage());
    }
}
