<?php
/**
 * Ecod Theme · 正文 oEmbed 自动嵌入
 *
 * 正文里独占一行的站外地址自动转成嵌入块，无需写短代码：
 *   - 视频 / 音频：原生 iframe 播放器（YouTube / B 站 / Vimeo / 优酷 / 腾讯视频 /
 *     网易云 / Spotify / SoundCloud）
 *   - 社交贴：官方 blockquote + 前端按需加载的 widgets 脚本（X / Instagram / TikTok）
 *   - 无法外嵌的站（微博 / 短链）：降级为链接卡
 *
 * 全部本地解析：不请求对方 oEmbed 接口、不建缓存表、不需要 API Key。
 * 在 post.php / page.php 的短代码之后、图片懒加载与外链中转之前调用。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

if (!function_exists('ecod_oembed_names')) {
    /**
     * 平台显示名
     */
    function ecod_oembed_names(): array
    {
        return [
            'youtube'    => 'YouTube',
            'bilibili'   => '哔哩哔哩',
            'vimeo'      => 'Vimeo',
            'youku'      => '优酷',
            'qq'         => '腾讯视频',
            'twitter'    => 'X',
            'instagram'  => 'Instagram',
            'tiktok'     => 'TikTok',
            'weibo'      => '微博',
            'netease'    => '网易云音乐',
            'spotify'    => 'Spotify',
            'soundcloud' => 'SoundCloud',
        ];
    }

    /**
     * 转义
     */
    function ecod_oembed_esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * 外链小图标（不依赖 components/icons.php，feed 等场景也能用；css.gg external）
     */
    function ecod_oembed_arrow(): string
    {
        return '<svg class="ecod_icon" width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">'
            . '<path d="M15.6396 7.02527H12.0181V5.02527H19.0181V12.0253H17.0181V8.47528L12.1042 13.3892L10.6899 11.975L15.6396 7.02527Z"/>'
            . '<path d="M10.9819 6.97473H4.98193V18.9747H16.9819V12.9747H14.9819V16.9747H6.98193V8.97473H10.9819V6.97473Z"/></svg>';
    }

    /**
     * iframe 播放器块
     *
     * @param string $kind   video（16:9）/ audio（固定高度）
     * @param int    $height audio 高度，写入 --ecod-oembed-h 由 CSS 消费
     */
    function ecod_oembed_player(string $key, string $src, string $url, string $kind = 'video', int $height = 0): string
    {
        $name = ecod_oembed_names()[$key] ?? $key;
        $style = $height > 0 ? ' style="--ecod-oembed-h:' . $height . 'px"' : '';

        return '<figure class="ecod_oembed ecod_oembed--' . $kind . '" data-ecod-oembed="' . ecod_oembed_esc($key) . '"' . $style . '>'
            . '<div class="ecod_oembed__frame">'
            . '<iframe src="' . ecod_oembed_esc($src) . '" title="' . ecod_oembed_esc($name) . '"'
            . ' loading="lazy" frameborder="0" allowfullscreen scrolling="no"'
            . ' referrerpolicy="strict-origin-when-cross-origin"'
            . ' allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share">'
            . '</iframe>'
            . '</div>'
            . '<figcaption class="ecod_oembed__cap">'
            . '<span class="ecod_oembed__src">' . ecod_oembed_esc($name) . '</span>'
            . '<a class="ecod_oembed__open" href="' . ecod_oembed_esc($url) . '" target="_blank" rel="nofollow noopener"'
            . ' data-ecod-oembed="1">打开原链接' . ecod_oembed_arrow() . '</a>'
            . '</figcaption>'
            . '</figure>';
    }

    /**
     * 社交贴块：输出官方 blockquote，内容由对应站点的脚本注入
     */
    function ecod_oembed_social(string $key, string $url, string $class, array $attrs = []): string
    {
        /* 关掉第三方脚本时退化成链接卡：不输出 blockquote，前端也就不会去加载 widgets */
        if (ecod_option('EOembedSocial', 'on') !== 'on') {
            return ecod_oembed_card($key, $url, '已关闭第三方脚本，点击查看原帖');
        }

        $name = ecod_oembed_names()[$key] ?? $key;
        $attr = '';
        foreach ($attrs as $k => $v) {
            $attr .= ' ' . $k . '="' . ecod_oembed_esc((string) $v) . '"';
        }

        return '<figure class="ecod_oembed ecod_oembed--social" data-ecod-oembed="' . ecod_oembed_esc($key) . '">'
            . '<blockquote class="' . ecod_oembed_esc($class) . '"' . $attr . '>'
            . '<a href="' . ecod_oembed_esc($url) . '" data-ecod-oembed="1">在 ' . ecod_oembed_esc($name) . ' 查看原帖</a>'
            . '</blockquote>'
            . '</figure>';
    }

    /**
     * 降级链接卡：站点禁止外嵌，或短链无法在本地还原目标内容
     */
    function ecod_oembed_card(string $key, string $url, string $note = ''): string
    {
        $name = ecod_oembed_names()[$key] ?? $key;
        $host = (string) parse_url($url, PHP_URL_HOST);
        $sub = $host . ($note !== '' ? ' · ' . $note : '');

        return '<a class="ecod_oembed ecod_oembed--card" data-ecod-oembed="' . ecod_oembed_esc($key) . '"'
            . ' href="' . ecod_oembed_esc($url) . '" target="_blank" rel="nofollow noopener">'
            . '<span class="ecod_oembed__brand">' . ecod_oembed_esc($name) . '</span>'
            . '<span class="ecod_oembed__text"><b>' . ecod_oembed_esc($name) . '</b>'
            . '<small>' . ecod_oembed_esc($sub) . '</small></span>'
            . '<span class="ecod_oembed__arrow">' . ecod_oembed_arrow() . '</span>'
            . '</a>';
    }

    /**
     * 地址 → 嵌入 HTML；不认识的地址返回空串
     */
    function ecod_oembed_render(string $raw): string
    {
        $url = trim(html_entity_decode($raw, ENT_QUOTES, 'UTF-8'));
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return '';
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }

        $host = strtolower($parts['host']);
        $path = (string) ($parts['path'] ?? '');
        $bare = (string) preg_replace('#^(?:www|m|mobile)\.#', '', $host);

        $query = [];
        if (!empty($parts['query'])) {
            parse_str((string) $parts['query'], $query);
        }

        /* ---------- YouTube ---------- */
        if ($bare === 'youtu.be' || $bare === 'youtube.com' || $bare === 'youtube-nocookie.com') {
            $id = '';
            if ($bare === 'youtu.be') {
                $id = trim($path, '/');
            } elseif (!empty($query['v'])) {
                $id = (string) $query['v'];
            } elseif (preg_match('#^/(?:embed|shorts|live|v)/([A-Za-z0-9_-]+)#', $path, $m)) {
                $id = $m[1];
            }
            if ($id !== '' && preg_match('#^[A-Za-z0-9_-]{6,20}$#', $id)) {
                return ecod_oembed_player('youtube', 'https://www.youtube.com/embed/' . $id . '?rel=0', $url);
            }
        }

        /* ---------- 哔哩哔哩 ---------- */
        if ($bare === 'b23.tv') {
            return ecod_oembed_card('bilibili', $url, '短链无法在本地还原视频，点击跳转');
        }
        if ($bare === 'bilibili.com' || $bare === 'bilibili.tv') {
            $bv = '';
            $aid = '';
            if (preg_match('#/video/(BV[A-Za-z0-9]+)#i', $path, $m)) {
                $bv = $m[1];
            } elseif (preg_match('#/video/av(\d+)#i', $path, $m)) {
                $aid = $m[1];
            }
            if ($bv !== '' || $aid !== '') {
                $src = 'https://player.bilibili.com/player.html?'
                    . ($bv !== '' ? 'bvid=' . rawurlencode($bv) : 'aid=' . $aid)
                    . '&page=' . max(1, (int) ($query['p'] ?? 1))
                    . '&autoplay=0&high_quality=1&danmaku=0';
                return ecod_oembed_player('bilibili', $src, $url);
            }
        }

        /* ---------- Vimeo ---------- */
        if ($bare === 'vimeo.com' && preg_match('#/(\d{6,})#', $path, $m)) {
            return ecod_oembed_player('vimeo', 'https://player.vimeo.com/video/' . $m[1], $url);
        }

        /* ---------- 优酷 ---------- */
        if (($bare === 'youku.com' || $bare === 'v.youku.com')
            && (preg_match('#v_show/id_([A-Za-z0-9=]+)#i', $path, $m) || preg_match('#/video/([A-Za-z0-9=]+)#i', $path, $m))) {
            return ecod_oembed_player('youku', 'https://player.youku.com/embed/' . $m[1], $url);
        }

        /* ---------- 腾讯视频 ---------- */
        if (($bare === 'v.qq.com' || $bare === 'qq.com')
            && preg_match('#/(?:x/)?(?:page|cover)/(?:[A-Za-z0-9]+/)?([A-Za-z0-9]{8,})\.html#i', $path, $m)) {
            return ecod_oembed_player('qq', 'https://v.qq.com/txp/iframe/player.html?vid=' . $m[1], $url);
        }

        /* ---------- X (Twitter) ---------- */
        if (($bare === 'x.com' || $bare === 'twitter.com')
            && preg_match('#^/[A-Za-z0-9_]{1,15}/status(?:es)?/\d+#', $path)) {
            return ecod_oembed_social('twitter', $url, 'twitter-tweet', [
                'data-dnt'          => 'true',
                'data-conversation' => 'none',
            ]);
        }

        /* ---------- Instagram ---------- */
        if (($bare === 'instagram.com' || $bare === 'instagr.am')
            && preg_match('#^/(?:p|reel|reels|tv)/[A-Za-z0-9_-]+#', $path)) {
            return ecod_oembed_social('instagram', $url, 'instagram-media', [
                'data-instgrm-permalink' => $url,
                'data-instgrm-version'   => '14',
            ]);
        }

        /* ---------- TikTok ---------- */
        if ($bare === 'vm.tiktok.com') {
            return ecod_oembed_card('tiktok', $url, '短链无法在本地还原视频，点击跳转');
        }
        if ($bare === 'tiktok.com' && preg_match('#^/@([A-Za-z0-9_.]+)/video/(\d+)#', $path, $m)) {
            return ecod_oembed_social('tiktok', $url, 'tiktok-embed', [
                'cite'          => $url,
                'data-video-id' => $m[2],
            ]);
        }

        /* ---------- 微博（禁止外嵌，走链接卡） ---------- */
        if (($bare === 'weibo.com' || $bare === 'weibo.cn')
            && (preg_match('#/(?:detail|status)/\d{6,}#', $path) || preg_match('#^/[^/]+/[A-Za-z0-9]{9,10}$#', $path))) {
            return ecod_oembed_card('weibo', $url, '该站禁止外嵌，点击查看原帖');
        }

        /* ---------- 网易云音乐 ---------- */
        if (preg_match('~^https?://(?:y\.)?music\.163\.com/(?:#/)?(song|album|playlist|djradio)\?id=(\d+)~i', $url, $m)) {
            /* type：0 歌单 / 1 专辑 / 2 歌曲 / 3 电台 */
            $map = ['song' => [2, 66], 'album' => [1, 400], 'playlist' => [0, 430], 'djradio' => [3, 430]];
            [$type, $height] = $map[strtolower($m[1])];
            $src = 'https://music.163.com/outchain/player?type=' . $type . '&id=' . $m[2] . '&auto=0&height=' . $height;
            return ecod_oembed_player('netease', $src, $url, 'audio', $height);
        }

        /* ---------- Spotify ---------- */
        if (preg_match('#^https?://open\.spotify\.com/(?:intl-[a-z\-]+/)?(track|album|playlist|episode|artist|show)/([A-Za-z0-9]+)#i', $url, $m)) {
            $type = strtolower($m[1]);
            $height = in_array($type, ['track', 'episode'], true) ? 152 : ($type === 'artist' ? 380 : 352);
            return ecod_oembed_player('spotify', 'https://open.spotify.com/embed/' . $type . '/' . $m[2], $url, 'audio', $height);
        }

        /* ---------- SoundCloud ---------- */
        if ($bare === 'on.soundcloud.com') {
            return ecod_oembed_card('soundcloud', $url, '短链无法在本地还原音频，点击跳转');
        }
        if ($bare === 'soundcloud.com') {
            $seg = array_values(array_filter(explode('/', $path), 'strlen'));
            $reserved = ['discover', 'search', 'tags', 'you', 'stream', 'upload', 'notifications', 'charts', 'pages', 'terms-of-use', 'imprint', 'jobs', 'settings', 'feed'];
            if (count($seg) >= 2 && !in_array(strtolower($seg[0]), $reserved, true)) {
                $src = 'https://w.soundcloud.com/player/?url=' . rawurlencode($url)
                    . '&color=%23ff5500&auto_play=false&hide_related=true&show_comments=false'
                    . '&show_user=true&show_reposts=false&visual=false';
                return ecod_oembed_player('soundcloud', $src, $url, 'audio', 166);
            }
        }

        return '';
    }

    /**
     * 正文过滤：独占一段的站外地址 → 嵌入块
     */
    function ecod_oembed_apply(string $html): string
    {
        if ($html === '' || ecod_option('EOembed', 'on') !== 'on' || stripos($html, 'http') === false) {
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

        /* 2. 段落里只有一个地址，且链接文字就是地址本身 */
        $html = preg_replace_callback(
            '#<p>\s*<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>\s*</p>#is',
            static function (array $m): string {
                $href = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
                $text = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES, 'UTF-8'));

                /* 作者手写了「点这里」之类，保持原样 */
                if ($text !== '' && $text !== $href && rtrim($text, '/') !== rtrim($href, '/')) {
                    return $m[0];
                }
                $embed = ecod_oembed_render($href);
                return $embed !== '' ? $embed : $m[0];
            },
            $html
        ) ?? $html;

        /* 3. 没被自动成链的裸地址段落 */
        $html = preg_replace_callback(
            '#<p>\s*(https?://[^\s<]+)\s*</p>#i',
            static function (array $m): string {
                $embed = ecod_oembed_render(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
                return $embed !== '' ? $embed : $m[0];
            },
            $html
        ) ?? $html;

        if ($blocks) {
            $html = strtr($html, $blocks);
        }

        return $html;
    }
}
