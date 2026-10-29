<?php
/**
 * 特色页公共组件：媒体提取 / 友链解析 / 页头
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 * @license   MIT
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

if (!function_exists('ecod_extract_images')) {
    /**
     * 从正文提取图片
     */
    function ecod_extract_images(string $html, int $limit = 60): array
    {
        $images = [];
        if (preg_match_all('/<img[^>]+>/i', $html, $tags)) {
            foreach ($tags[0] as $tag) {
                if (!preg_match('/src=["\']([^"\']+)["\']/i', $tag, $src)) {
                    continue;
                }
                $alt = preg_match('/alt=["\']([^"\']*)["\']/i', $tag, $m) ? trim($m[1]) : '';
                /* 作者在正文里写了的宽高直接沿用，站外图片也能占位防抖 */
                $w = preg_match('/\bwidth=["\'](\d+)["\']/i', $tag, $wm) ? (int) $wm[1] : 0;
                $h = preg_match('/\bheight=["\'](\d+)["\']/i', $tag, $hm) ? (int) $hm[1] : 0;
                $images[] = ['src' => $src[1], 'alt' => $alt, 'w' => $w, 'h' => $h];
            }
        }

        /* 纯文本里的图片直链 */
        if (!$images && preg_match_all('#https?://[^\s"\'<>]+\.(?:jpe?g|png|gif|webp|avif)#i', $html, $m)) {
            foreach ($m[0] as $src) {
                $images[] = ['src' => $src, 'alt' => '', 'w' => 0, 'h' => 0];
            }
        }

        $seen = [];
        $out = [];
        foreach ($images as $img) {
            if (isset($seen[$img['src']])) {
                continue;
            }
            $seen[$img['src']] = true;
            $out[] = $img;
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /**
     * 视频地址标准化：B 站 / YouTube / 优酷 → 可嵌入地址
     */
    function ecod_embed_src(string $url): string
    {
        $url = trim($url);

        if (preg_match('#^https?://(?:www\.)?bilibili\.com/video/(BV[\w]+)#i', $url, $m)) {
            return '//player.bilibili.com/player.html?bvid=' . $m[1] . '&high_quality=1&danmaku=0';
        }
        if (preg_match('#^https?://b23\.tv/([\w]+)#i', $url, $m)) {
            return '//player.bilibili.com/player.html?bvid=' . $m[1] . '&high_quality=1&danmaku=0';
        }
        if (preg_match('#^https?://(?:www\.)?youtube\.com/watch\?v=([\w-]+)#i', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }
        if (preg_match('#^https?://youtu\.be/([\w-]+)#i', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }

        return $url;
    }

    /**
     * 从正文提取视频（iframe / video / 视频直链 / 「标题 | 地址」行）
     */
    function ecod_extract_videos(string $html, int $limit = 24): array
    {
        $videos = [];

        /* 已有 iframe */
        if (preg_match_all('/<iframe[^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
            foreach ($m[1] as $src) {
                $videos[] = ['type' => 'embed', 'src' => $src, 'title' => '', 'poster' => ''];
            }
        }

        /* HTML5 video */
        if (preg_match_all('/<video[^>]*>.*?<\/video>/is', $html, $blocks)) {
            foreach ($blocks[0] as $block) {
                $poster = preg_match('/poster=["\']([^"\']+)["\']/i', $block, $p) ? $p[1] : '';
                $src = '';
                if (preg_match('/<source[^>]+src=["\']([^"\']+)["\']/i', $block, $s)) {
                    $src = $s[1];
                } elseif (preg_match('/<video[^>]+src=["\']([^"\']+)["\']/i', $block, $s)) {
                    $src = $s[1];
                }
                if ($src !== '') {
                    $videos[] = ['type' => 'file', 'src' => $src, 'title' => '', 'poster' => $poster];
                }
            }
        }

        /* 「标题 | 地址」行 */
        foreach (preg_split('/\r\n|\r|\n/', strip_tags($html, '<br>')) as $line) {
            $line = trim(str_replace('<br>', "\n", $line));
            foreach (preg_split('/\n/', $line) as $one) {
                $one = trim($one);
                if ($one === '' || strpos($one, '|') === false) {
                    continue;
                }
                $parts = array_map('trim', explode('|', $one));
                if (count($parts) < 2) {
                    continue;
                }
                $url = $parts[1];
                if (!preg_match('#^https?://#i', $url)) {
                    continue;
                }
                if (!preg_match('#(bilibili\.com/video|b23\.tv|youtube\.com|youtu\.be|\.mp4|\.m3u8|\.webm|player\.)#i', $url)) {
                    continue;
                }
                $videos[] = [
                    'type'   => preg_match('#\.(mp4|m3u8|webm)$#i', $url) ? 'file' : 'embed',
                    'src'    => ecod_embed_src($url),
                    'title'  => $parts[0],
                    'poster' => $parts[2] ?? '',
                ];
            }
        }

        /* 去重 */
        $seen = [];
        $out = [];
        foreach ($videos as $v) {
            if (isset($seen[$v['src']])) {
                continue;
            }
            $seen[$v['src']] = true;
            $out[] = $v;
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /**
     * 友链解析：优先「名称 | 链接 | 描述 | 头像」行，其次 <a> 标签
     */
    function ecod_friend_links(string $html): array
    {
        $links = [];
        $flat = preg_replace('#<br\s*/?>|</p>|<p[^>]*>#i', "\n", $html);

        /* 行格式：名称 | 链接 | 描述 | 头像（Markdown 渲染后 URL 可能已变成 <a>，strip 后仍是纯文本） */
        $text = trim(strip_tags($flat));
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '|') === false) {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 2 || !preg_match('#^https?://#i', $parts[1])) {
                continue;
            }
            $links[] = [
                'name'   => $parts[0],
                'url'    => $parts[1],
                'desc'   => $parts[2] ?? '',
                'avatar' => $parts[3] ?? '',
            ];
        }

        /* 兜底：正文里的链接 */
        if (!$links && preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $item) {
                $url = trim($item[1]);
                if (!preg_match('#^https?://#i', $url)) {
                    continue;
                }
                $name = trim(strip_tags($item[2]));
                if ($name === '') {
                    continue;
                }
                $desc = preg_match('/title=["\']([^"\']*)["\']/', $item[0], $t) ? trim($t[1]) : '';
                $links[] = ['name' => $name, 'url' => $url, 'desc' => $desc, 'avatar' => ''];
            }
        }

        /* 按域名去重 */
        $seen = [];
        $out = [];
        foreach ($links as $link) {
            $host = parse_url($link['url'], PHP_URL_HOST) ?: $link['url'];
            if (isset($seen[$host])) {
                continue;
            }
            $seen[$host] = true;
            $out[] = $link;
        }
        return $out;
    }

    /**
     * 提取正文说明：拆行后去掉「xx | xx」数据行（可选去掉图片）
     */
    function ecod_intro_text(string $html, bool $stripImages = false): string
    {
        $flat = preg_replace('#<br\s*/?>|</p>|<p[^>]*>|</li>|<li[^>]*>|</h[1-6]>|<h[1-6][^>]*>#i', "\n", $html);
        $flat = preg_replace('/^[^\n]*\|[^\n]*$/m', '', $flat);
        if ($stripImages) {
            $flat = preg_replace('/<img[^>]*>/i', '', $flat);
        }
        return trim(preg_replace("/\n{3,}/", "\n\n", $flat));
    }

    /**
     * 特色页页头
     */
    function ecod_page_hero(string $tag, string $title, string $desc = '', string $meta = ''): void
    {
        echo '<section class="ecod_pagehero">';
        echo '<span class="ecod_pagehero__tag">' . htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') . '</span>';
        echo '<h1 class="ecod_pagehero__title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
        if ($desc !== '') {
            echo '<p class="ecod_pagehero__desc">' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        if ($meta !== '') {
            echo '<div class="ecod_pagehero__meta">' . $meta . '</div>';
        }
        echo '</section>';
    }

    /**
     * 把图片地址解析为本站文件路径
     *
     * 只接受同域地址（绝对 URL / 协议相对 URL / 以 / 开头的绝对路径），
     * 站外图片返回 null —— 渲染期绝不发起远程请求。
     */
    function ecod_image_local_path(string $src): ?string
    {
        $src = html_entity_decode(trim($src), ENT_QUOTES, 'UTF-8');
        if ($src === '') {
            return null;
        }

        /* 去掉查询串与锚点：a.jpg?v=2#x → a.jpg */
        $split = preg_split('/[?#]/', $src, 2);
        $path  = (string) ($split[0] ?? '');
        if ($path === '') {
            return null;
        }

        $siteHost = '';
        $sitePath = '';
        try {
            $siteUrl  = (string) \Utils\Helper::options()->siteUrl;
            $siteHost = strtolower((string) parse_url($siteUrl, PHP_URL_HOST));
            $sitePath = rtrim((string) parse_url($siteUrl, PHP_URL_PATH), '/');
        } catch (\Throwable $e) {
        }

        if (preg_match('#^(?:https?:)?//#i', $path)) {
            $abs   = strpos($path, '//') === 0 ? 'http:' . $path : $path;
            $parts = parse_url($abs);
            if (!$parts || empty($parts['path'])) {
                return null;
            }
            $host = strtolower((string) ($parts['host'] ?? ''));
            $self = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
            $same = $host !== '' && (
                ($siteHost !== '' && $host === $siteHost)
                || ($self !== '' && $host === $self)
                || ($siteHost !== '' && preg_replace('/^www\./', '', $host) === preg_replace('/^www\./', '', $siteHost))
            );
            if (!$same) {
                return null;
            }
            $path = (string) $parts['path'];
        } elseif (strpos($path, '/') !== 0) {
            /* 相对路径无法可靠定位，交给浏览器按原比例布局 */
            return null;
        }

        /* 站点装在子目录时，URL 里会多带一层前缀 */
        if ($sitePath !== '' && $sitePath !== '/' && strpos($path, $sitePath . '/') === 0) {
            $path = substr($path, strlen($sitePath));
        }

        $root = rtrim(str_replace('\\', '/', (string) __TYPECHO_ROOT_DIR__), '/');
        $file = realpath($root . '/' . ltrim($path, '/'));
        if ($file === false || !is_file($file)) {
            return null;
        }
        /* 越界防护：解析后必须仍在站点根内（站点根本身可能是软链，先归一） */
        $rootReal = realpath($root);
        $rootReal = $rootReal !== false ? str_replace('\\', '/', $rootReal) : $root;
        if (strpos(str_replace('\\', '/', $file), rtrim($rootReal, '/') . '/') !== 0) {
            return null;
        }

        return $file;
    }

    /**
     * 读取图片真实宽高（同请求内按地址缓存，只读文件头，不加载整图）
     *
     * @return array{w:int,h:int}|null 读不到（站外 / SVG / 非图片）为 null
     */
    function ecod_image_size(string $src): ?array
    {
        static $cache = [];

        $key = trim($src);
        if ($key === '') {
            return null;
        }
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $cache[$key] = null;

        $file = ecod_image_local_path($key);
        if ($file === null) {
            return null;
        }

        $info = @getimagesize($file);
        if (!$info || empty($info[0]) || empty($info[1])) {
            return null;
        }
        if (!empty($info['mime']) && strpos((string) $info['mime'], 'image/') !== 0) {
            return null;
        }

        $cache[$key] = ['w' => (int) $info[0], 'h' => (int) $info[1]];
        return $cache[$key];
    }

    /**
     * 生成 width/height 属性，用于占位防 CLS
     *
     * $scaleTo 给定时按该高度等比换算宽度，用于 CSS 已固定高度的场景（如 logo）。
     */
    function ecod_img_attrs(string $src, int $scaleTo = 0): string
    {
        $size = ecod_image_size($src);
        if ($size === null) {
            return '';
        }

        $w = $size['w'];
        $h = $size['h'];
        if ($scaleTo > 0 && $h > 0) {
            $w = max(1, (int) round($w * $scaleTo / $h));
            $h = $scaleTo;
        }

        return ' width="' . $w . '" height="' . $h . '"';
    }
}
