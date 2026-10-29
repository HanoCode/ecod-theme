<?php
/**
 * Ecod Theme · 正文短代码
 *
 * 支持：
 *   [note type=tip]…[/note]                    提示块（info / tip / warn / danger / success）
 *   [fold title="标题"]…[/fold]                折叠
 *   [tabs][tab title="A"]…[/tab][/tabs]        选项卡
 *   [timeline]2024-01 | 事件[/timeline]        时间线，每行「时间 | 内容」
 *   [mark]高亮[/mark] / [kbd]Ctrl[/kbd]        行内标记
 *   [reply]…[/reply]                           评论可见
 *   [download url="…" name="…" size="…"]说明[/download]
 *
 * 其余扩展短代码见 core/shortcode-extra.php（alert / callout / cloud / card /
 * grid / bilibili / music / 表情等 20 余个）。
 *
 * 全部在 HTML 层面替换，因此必须避开 <pre> / <code> 里的内容。
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
 * 应用短代码
 */
function ecod_shortcode_apply(string $content, $archive = null): string
{
    if ($content === '') {
        return $content;
    }

    /* 只含表情代码（无方括号）的正文同样需要解析，不能整篇早退 */
    $hasShortcode = strpos($content, '[') !== false;
    if (!$hasShortcode && strpos($content, ':') === false) {
        return $content;
    }

    /* 1. 保护代码块，避免里面的方括号 / 表情代码被误替换 */
    $blocks = [];
    $content = preg_replace_callback(
        '#<(pre|code)\b[^>]*>.*?</\1>#is',
        static function (array $m) use (&$blocks): string {
            $key = "\x1A" . count($blocks) . "\x1A";
            $blocks[$key] = $m[0];
            return $key;
        },
        $content
    ) ?? $content;

    /* 1.1 不含任何方括号的正文（纯表情）：只跑表情替换，还原代码块后返回 */
    if (!$hasShortcode) {
        if (function_exists('ecod_sc_emoji')) {
            $content = ecod_sc_emoji($content);
        }
        if ($blocks) {
            $content = strtr($content, $blocks);
        }
        return $content;
    }

    /* 2. 块级 */
    $content = ecod_sc_note($content);
    $content = ecod_sc_fold($content);
    $content = ecod_sc_tabs($content);
    $content = ecod_sc_timeline($content);
    $content = ecod_sc_reply($content, $archive);
    /* [vip1]…[/vip1] / [vip2]…[/vip2] 是 [hide type=vip level=N] 的语法糖 */
    $content = preg_replace('#\[vip1\]#i', '[hide type="vip" level="1"]', $content) ?? $content;
    $content = preg_replace('#\[/vip1\]#i', '[/hide]', $content) ?? $content;
    $content = preg_replace('#\[vip2\]#i', '[hide type="vip" level="2"]', $content) ?? $content;
    $content = preg_replace('#\[/vip2\]#i', '[/hide]', $content) ?? $content;
    $content = ecod_sc_hide($content, $archive);
    $content = ecod_sc_download($content);

    /* 2.1 扩展短代码（core/shortcode-extra.php） */
    if (function_exists('ecod_sc_extra_block')) {
        /* posts 等列表短代码需要当前文章上下文（排除自身 / 取分类做相关阅读） */
        $GLOBALS['ecod_sc_archive'] = $archive;
        $content = ecod_sc_extra_block($content);
    }

    /* 3. 行内 */
    $content = preg_replace('#\[mark\](.*?)\[/mark\]#is', '<mark class="ecod_mark">$1</mark>', $content) ?? $content;
    $content = preg_replace('#\[kbd\](.*?)\[/kbd\]#is', '<kbd class="ecod_kbd">$1</kbd>', $content) ?? $content;

    /* 3.5 全局清理：块级组件之间 autop / markdown 的残留（<br> 与空段落），避免组件间出现大空隙 */
    $content = preg_replace('#(?:<br\s*/?>\s*)+(<(?:div|details|figure|ul|ol|table|hr|blockquote)\b)#i', '$1', $content) ?? $content;
    $content = preg_replace('#(</(?:div|details|figure|ul|ol|table|blockquote)>)\s*(?:<br\s*/?>\s*)+#i', '$1', $content) ?? $content;
    $content = preg_replace('#<p>(?:\s|&nbsp;|<br\s*/?>)*</p>#i', '', $content) ?? $content;

    /* 4. 还原代码块 */
    if ($blocks) {
        $content = strtr($content, $blocks);
    }

    return $content;
}

/**
 * 摘要里去掉短代码标记，只留文字
 */
function ecod_shortcode_strip(string $excerpt): string
{
    if (strpos($excerpt, '[') === false) {
        return $excerpt;
    }

    /* 隐藏内容与相册的正文一并剥掉：前者会泄漏剧情，后者只剩一串图片地址 */
    $excerpt = preg_replace('#\[hide\b[^\]]*\].*?\[/hide\]#is', '', $excerpt) ?? $excerpt;
    $excerpt = preg_replace('#\[reply\b[^\]]*\].*?\[/reply\]#is', '', $excerpt) ?? $excerpt;
    $excerpt = preg_replace('#\[gallery\b[^\]]*\].*?\[/gallery\]#is', '', $excerpt) ?? $excerpt;
    $excerpt = preg_replace('#\[vip[12]\b[^\]]*\].*?\[/vip[12]\]#is', '', $excerpt) ?? $excerpt;

    $excerpt = preg_replace(
        '#\[/?(?:note|fold|tabs|tab|timeline|mark|kbd|reply|hide|download|alert|message|callout|anote|mtitle|copy|progress|abtn|dotted|lamp|task|card|cards|grid|cloud|article|posts|github|bilibili|video|mp3|music|gallery|tooltip|badge|spoiler|vip1|vip2)\b[^\]]*\]#i',
        '',
        $excerpt
    );
    return $excerpt ?? '';
}

/**
 * 包裹在 <p> 里的块级短代码：连 <p> 一起去掉，避免出现 <p><div> 的非法嵌套
 *
 * @param string $tag   短代码名
 * @param string $attrs 属性部分的正则（可为空字符串）
 * @param callable $fn  回调，参数为 (属性串, 内容)
 * @param string $content
 */
function ecod_sc_block(string $tag, string $attrs, callable $fn, string $content): string
{
    $pattern = '#(?:<p>)?\s*\[' . $tag . $attrs . '\](.*?)\[/' . $tag . '\]\s*(?:</p>)?#is';

    return preg_replace_callback(
        $pattern,
        static function (array $m) use ($attrs, $fn): string {
            $attrRaw = '';
            $inner = $m[1];
            if ($attrs !== '' && preg_match('#\[[a-z]+\s+([^\]]*)\]#i', $m[0], $am)) {
                $attrRaw = $am[1];
            }
            return (string) $fn($attrRaw, $inner);
        },
        $content
    ) ?? $content;
}

/**
 * 解析短代码属性
 *
 * 支持三种写法（可混用）：
 *   [note warn]                       裸值 → type
 *   [note type="warn" title="注意"]    具名属性
 *   [progress 60 label="整体进度"]     首段裸值 + 具名属性并存
 *
 * @return array<string, string>
 */
function ecod_sc_attrs(string $raw): array
{
    $out = [];
    $raw = trim($raw);
    if ($raw === '') {
        return $out;
    }

    /* 首段裸值（紧跟标签名、不带 = 的一个词 / 引号串）先取出，剩余部分再按 key="value" 解析 */
    if (preg_match('/^(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=]+))(\s*=)?/', $raw, $bm) && empty($bm[4])) {
        $bare = $bm[1] !== '' ? $bm[1] : ($bm[2] !== '' ? $bm[2] : ($bm[3] ?? ''));
        $bare = trim($bare);
        if ($bare !== '') {
            $out['type'] = $bare;
        }
        $raw = trim(substr($raw, strlen($bm[0])));
    }

    if ($raw !== '' && preg_match_all('/([a-z][a-z0-9_-]*)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s"\']+))/i', $raw, $m, PREG_SET_ORDER)) {
        foreach ($m as $one) {
            $value = $one[3] !== '' ? $one[3] : ($one[4] !== '' ? $one[4] : ($one[5] ?? ''));
            /* 正文里的属性值可能已被 HTML 转义一次（&amp; → &），这里还原，输出时统一再转义一次 */
            $out[strtolower($one[1])] = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
        }
    }

    return $out;
}

/**
 * 短代码里的 URL 白名单校验（防 javascript: / data: 等协议注入）
 *
 * 允许：http(s) / mailto / tel / ftp / magnet / thunder，以及无协议的相对地址（`/`、`#`、`a/b`）。
 * 其余协议一律回退到 $fallback（传空串表示「不生成链接」）。
 *
 * @param string $url
 * @param string $fallback 不可用时返回的地址，'' 表示调用方应降级为普通元素
 */
function ecod_sc_url(string $url, string $fallback = '#'): string
{
    $url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));
    if ($url === '') {
        return $fallback;
    }

    if (preg_match('#^([a-z][a-z0-9+.\-]*):#i', $url, $m)) {
        static $allow = ['http', 'https', 'mailto', 'tel', 'ftp', 'magnet', 'thunder'];
        if (!in_array(strtolower($m[1]), $allow, true)) {
            return $fallback;
        }
    }

    return $url;
}

/**
 * 提示块
 */
function ecod_sc_note(string $content): string
{
    $map = [
        'info'    => ['info', '说明'],
        'success' => ['check-circle', '成功'],
        'tip'     => ['bulb', '提示'],
        'warn'    => ['alert', '注意'],
        'danger'  => ['x-circle', '警告'],
        'dark'    => ['quote', '备注'],
    ];

    return ecod_sc_block('note', '(?:\s+[^\]]*)?', static function (string $attrRaw, string $inner) use ($map): string {
        $attrs = ecod_sc_attrs($attrRaw);
        $type = strtolower($attrs['type'] ?? $attrs['0'] ?? 'info');
        if (!isset($map[$type])) {
            $type = 'info';
        }
        $label = $attrs['title'] ?? $map[$type][1];

        return '<div class="ecod_note is-' . $type . '">'
            . '<div class="ecod_note__head">' . ecod_icon($map[$type][0], 15)
            . '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></div>'
            . '<div class="ecod_note__body">' . $inner . '</div>'
            . '</div>';
    }, $content);
}

/**
 * 折叠
 */
function ecod_sc_fold(string $content): string
{
    return ecod_sc_block('fold', '(?:\s+[^\]]*)?', static function (string $attrRaw, string $inner): string {
        $attrs = ecod_sc_attrs($attrRaw);
        $title = $attrs['title'] ?? '展开查看';

        return '<details class="ecod_fold">'
            . '<summary>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
            . '<span class="ecod_fold__chev">' . ecod_icon('chevron', 14) . '</span></summary>'
            . '<div class="ecod_fold__body">' . $inner . '</div>'
            . '</details>';
    }, $content);
}

/**
 * 选项卡
 */
function ecod_sc_tabs(string $content): string
{
    return ecod_sc_block('tabs', '', static function (string $attrRaw, string $inner): string {
        if (!preg_match_all('#\[tab\s+([^\]]*)\](.*?)\[/tab\]#is', $inner, $m, PREG_SET_ORDER)) {
            return $inner;
        }

        $nav = '';
        $panels = '';
        foreach ($m as $index => $tab) {
            $attrs = ecod_sc_attrs($tab[1]);
            $title = $attrs['title'] ?? ('选项 ' . ($index + 1));
            $active = $index === 0 ? ' is-active' : '';

            $nav .= '<button type="button" class="ecod_tabs__btn' . $active . '" role="tab"'
                . ' aria-selected="' . ($index === 0 ? 'true' : 'false') . '"'
                . ' data-ecod-tab="' . $index . '">'
                . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</button>';

            /* 清掉 autop 残留的 <p> 开闭标签，避免面板内出现非法嵌套 */
            $panelInner = preg_replace('#^\s*</p>|<p>\s*$#', '', trim($tab[2])) ?? $tab[2];

            $panels .= '<div class="ecod_tabs__panel' . $active . '" role="tabpanel" data-ecod-panel="'
                . $index . '">' . $panelInner . '</div>';
        }

        return '<div class="ecod_ctabs" data-ecod-tabs>'
            . '<div class="ecod_tabs__nav" role="tablist">' . $nav . '</div>'
            . '<div class="ecod_tabs__panels">' . $panels . '</div>'
            . '</div>';
    }, $content);
}

/**
 * 时间线：每行「时间 | 内容」
 */
function ecod_sc_timeline(string $content): string
{
    return ecod_sc_block('timeline', '(?:\s+[^\]]*)?', static function (string $attrRaw, string $inner): string {
        $text = trim(preg_replace('#</p>\s*<p>#i', "\n", $inner) ?? $inner);
        $lines = preg_split('/\r\n|\r|\n|<br\s*\/?>/i', $text);

        $items = '';
        $count = 0;
        foreach ($lines as $line) {
            $line = trim(strip_tags((string) $line));
            if ($line === '') {
                continue;
            }
            $parts = preg_split('/\s*[|｜]\s*/u', $line, 2);
            $time = $parts[0];
            $body = $parts[1] ?? '';
            $count++;

            $items .= '<li class="ecod_timeline__item">'
                . '<span class="ecod_timeline__dot" aria-hidden="true"></span>'
                . '<time class="ecod_timeline__time">' . htmlspecialchars($time, ENT_QUOTES, 'UTF-8') . '</time>'
                . ($body !== '' ? '<p class="ecod_timeline__text">' . htmlspecialchars($body, ENT_QUOTES, 'UTF-8') . '</p>' : '')
                . '</li>';
        }

        if ($count === 0) {
            return $inner;
        }

        return '<ol class="ecod_timeline">' . $items . '</ol>';
    }, $content);
}

/**
 * 评论可见
 */
function ecod_sc_reply(string $content, $archive = null): string
{
    if (strpos($content, '[reply') === false) {
        return $content;
    }

    $visible = ecod_shortcode_reply_visible($archive);

    return ecod_sc_block('reply', '(?:\s+[^\]]*)?', static function (string $attrRaw, string $inner) use ($visible): string {
        if ($visible) {
            return '<div class="ecod_replybox is-open">' . $inner . '</div>';
        }
        return '<div class="ecod_replybox">'
            . '<div class="ecod_replybox__lock">' . ecod_icon('lock', 16) . '</div>'
            . '<b>这部分内容仅评论者可见</b>'
            . '<p>在本站任意文章留下一条通过审核的评论后，刷新页面即可看到。</p>'
            . '</div>';
    }, $content);
}

/**
 * 当前访客是否满足「已评论」
 */
function ecod_shortcode_reply_visible($archive = null): bool
{
    static $cache = [];

    $cid = (int) ($archive->cid ?? 0);
    if ($cid <= 0) {
        return false;
    }
    if (array_key_exists($cid, $cache)) {
        return $cache[$cid];
    }
    $cache[$cid] = false;

    $mail = '';
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    try {
        $user = \Widget\User::alloc();
        if ($user->hasLogin()) {
            /* 博主与管理团队默认可见 */
            if ($user->pass('administrator', true)) {
                return $cache[$cid] = true;
            }
            $mail = (string) $user->mail;
        }
    } catch (\Throwable $e) {
    }

    if ($mail === '' && isset($_COOKIE['__typecho_remember_mail'])) {
        $mail = (string) $_COOKIE['__typecho_remember_mail'];
    }

    try {
        $db = \Typecho\Db::get();
        $select = $db->select('coid')->from('table.comments')
            ->where('status = ?', 'approved')
            ->where('type = ?', 'comment')
            ->limit(1);

        /* 优先按邮箱判定；没有邮箱时退到「IP + 浏览器 UA」，
           同一 Wi-Fi / NAT 下的其他设备不会因此被误放行 */
        if ($mail !== '') {
            $select->where('mail = ?', $mail);
        } elseif ($ip !== '') {
            $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
            $select->where('ip = ?', $ip);
            if ($ua !== '') {
                $select->where('agent = ?', $ua);
            }
        } else {
            return false;
        }

        $cache[$cid] = (bool) $db->fetchRow($select);
    } catch (\Throwable $e) {
    }

    return $cache[$cid];
}

/**
 * 分级隐藏内容
 *
 * 用法（type 缺省为 login）：
 *   [hide]…[/hide]                          仅登录可见
 *   [hide comment]…[/hide]                  评论过可见（同 [reply]）
 *   [hide level=3]…[/hide]                  评论等级 Lv.3 及以上可见
 *   [hide type=level level=4 tip="…"]…[/hide]
 *
 * 未满足条件时整段内容不输出到 HTML，防止查看源码绕过。
 */
function ecod_sc_hide(string $content, $archive = null): string
{
    if (strpos($content, '[hide') === false) {
        return $content;
    }

    return ecod_sc_block('hide', '[^\\]]*', static function (string $attrRaw, string $inner) use ($archive): string {
        $type = 'login';
        $level = 1;
        $tip = '';
        $pw = '';

        if (preg_match('/type\s*=\s*["\']?(login|comment|reply|level|password|pw|pay|vip)/i', $attrRaw, $m)) {
            $type = strtolower($m[1]);
        } elseif (preg_match('/^(login|comment|reply|level|password|pw|pay|vip)\b/i', trim($attrRaw), $m)) {
            $type = strtolower($m[1]);
        }
        if ($type === 'pw') {
            $type = 'password';
        }
        if (preg_match('/(?:pw|password|pass)\s*=\s*["\']([^"\']*)["\']/i', $attrRaw, $m)) {
            $pw = $m[1];
        }
        if (preg_match('/level\s*=\s*["\']?(\d)/i', $attrRaw, $m)) {
            if ($type === 'login') {
                $type = 'level';
            }
            $level = max(1, min(7, (int) $m[1]));
        }
        if (preg_match('/tip\s*=\s*["\']([^"\']*)["\']/i', $attrRaw, $m)) {
            $tip = trim($m[1]);
        }

        /* 写了 type=password 却没给 pw：按登录可见处理，避免把内容当明文直接放出来 */
        if ($type === 'password' && $pw === '') {
            $type = 'login';
        }

        if ($type === 'password') {
            /* 同页 POST 校验：密码不对时正文根本不出现在 HTML 里（比前端校验可靠） */
            $field = 'ecod-hide-pw';
            $posted = isset($_POST[$field]) && is_string($_POST[$field]) ? trim((string) $_POST[$field]) : '';
            $boxId = 'ecod-pw-' . substr(md5($inner . '|' . $tip), 0, 8);

            if ($posted !== '' && hash_equals($pw, $posted)) {
                return '<div class="ecod_replybox is-open" id="' . $boxId . '">' . $inner . '</div>';
            }

            $failed = $posted !== '';

            return '<div class="ecod_pwbox' . ($failed ? ' is-error' : '') . '" id="' . $boxId . '">'
                . '<div class="ecod_pwbox__head">'
                . '<span class="ecod_pwbox__icon">' . ecod_icon('lock', 15) . '</span>'
                . '<b>' . htmlspecialchars($tip !== '' ? $tip : '这部分内容已加密，输入密码后查看', ENT_QUOTES, 'UTF-8') . '</b>'
                . '</div>'
                . '<form class="ecod_pwbox__form" method="post" action="#' . $boxId . '">'
                . '<input type="password" name="' . $field . '" placeholder="请输入密码"'
                . ' autocomplete="off" aria-label="查看密码" required>'
                . '<button type="submit">查看</button>'
                . '</form>'
                . ($failed ? '<p class="ecod_pwbox__err">密码不对，请重新输入</p>' : '')
                . '</div>';
        }

        if (ecod_shortcode_hide_visible($type, $level, $archive)) {
            return '<div class="ecod_replybox is-open">' . $inner . '</div>';
        }

        if ($tip === '') {
            $tips = [
                'login'   => '这部分内容仅登录用户可见',
                'comment' => '这部分内容仅评论者可见',
                'reply'   => '这部分内容仅评论者可见',
                'level'   => '这部分内容需要评论等级 Lv.' . $level . ' 及以上',
                'pay'     => '这部分内容需要购买本文后查看',
                'vip'     => '这部分内容需要' . ecod_pay_level_name($level) . '及以上',
            ];
            $tip = $tips[$type] ?? $tips['login'];
        }

        $sub = [
            'login'   => '登录后刷新页面即可查看。',
            'comment' => '在本站任意文章留下一条通过审核的评论后，刷新页面即可看到。',
            'reply'   => '在本站任意文章留下一条通过审核的评论后，刷新页面即可看到。',
            'level'   => '多参与评论提升等级后即可查看。',
            'pay'     => '购买后可永久回看，权益绑定账号。',
            'vip'     => '开通会员后全站付费内容都能读。',
        ];

        /* 付费 / 会员：给一个可点的去处，而不是只有一句话 */
        $go = '';
        if ($type === 'pay') {
            $href = ecod_pay_cashier_url(['post' => (int) ($archive->cid ?? 0)]);
            if ($href !== '') {
                $go = '<a class="ecod_replybox__go" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">'
                    . ecod_icon('zap', 13) . '立即解锁</a>';
            }
        } elseif ($type === 'vip') {
            $href = ecod_pay_vip_url();
            if ($href !== '') {
                $go = '<a class="ecod_replybox__go" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">'
                    . ecod_icon('star', 13) . '开通' . ecod_pay_level_name($level) . '</a>';
            }
        }

        return '<div class="ecod_replybox' . ($go !== '' ? ' ecod_replybox--pay' : '') . '">'
            . '<div class="ecod_replybox__lock">' . ecod_icon('lock', 16) . '</div>'
            . '<b>' . htmlspecialchars($tip, ENT_QUOTES, 'UTF-8') . '</b>'
            . '<p>' . htmlspecialchars($sub[$type] ?? $sub['login'], ENT_QUOTES, 'UTF-8') . '</p>'
            . $go
            . '</div>';
    }, $content);
}

/**
 * 当前访客是否满足隐藏内容的可见条件
 */
function ecod_shortcode_hide_visible(string $type, int $level = 1, $archive = null): bool
{
    $mail = '';
    try {
        $user = \Widget\User::alloc();
        if ($user->hasLogin()) {
            /* 管理团队全可见 */
            if ($user->pass('administrator', true) || $user->pass('editor', true)) {
                return true;
            }
            if ($type === 'login') {
                return true;
            }
            $mail = (string) $user->mail;
        }
    } catch (\Throwable $e) {
        $mail = '';
    }

    /* 游客兜底：评论邮箱 cookie（与 [reply] 的判定口径一致） */
    if ($mail === '' && isset($_COOKIE['__typecho_remember_mail'])) {
        $mail = (string) $_COOKIE['__typecho_remember_mail'];
    }

    if ($type === 'comment' || $type === 'reply') {
        return ecod_shortcode_reply_visible($archive);
    }

    if ($type === 'level') {
        if ($mail === '') {
            return false;
        }
        $info = ecod_comment_level($mail);
        return (int) $info['level'] >= $level;
    }

    /* 片段付费：与文章门禁共用同一判定，避免「页面已购、短代码还锁」 */
    if ($type === 'pay') {
        if (!function_exists('ecod_pay_access') || !ecod_pay_enabled()) {
            /* 付费未启用：退化为登录可见，避免内容永久锁死 */
            return $mail !== '';
        }
        $state = ecod_pay_access($archive);
        if ($state['mode'] === 'free') {
            return $mail !== '';
        }
        return $state['allow'] && in_array($state['reason'], ['purchased', 'vip', 'admin', 'author'], true);
    }

    if ($type === 'vip') {
        if (!function_exists('ecod_pay_vip') || !ecod_pay_enabled()) {
            return $mail !== '';
        }
        $vip = ecod_pay_vip();
        return $vip['active'] && (int) $vip['level'] >= $level;
    }

    return false;
}

/**
 * 网盘类型表：key => [名称, 品牌色, logo 字符]
 */
function ecod_sc_netdisk_map(): array
{
    static $map = [
        'baidu'    => ['百度网盘', '#06a7ff', '百', '#pan\.baidu\.com#i'],
        'ali'      => ['阿里云盘', '#4e6bf2', '阿', '#(aliyundrive|alipan)\.com#i'],
        'quark'    => ['夸克网盘', '#4c68f5', '夸', '#(pan\.quark|quark)\.cn#i'],
        'lanzou'   => ['蓝奏云', '#00a3e0', '蓝', '#(lanzou|lanpv|lanzn|lanzoui|lanzoux)\S*\.com#i'],
        'tianyi'   => ['天翼云盘', '#e62129', '翼', '#cloud\.189\.cn#i'],
        'pan123'   => ['123云盘', '#1b6dff', '123', '#(123pan|123684)\S*#i'],
        'onedrive' => ['OneDrive', '#0364b8', 'Od', '#(onedrive\.live|1drv)\.com#i'],
        'gdrive'   => ['Google Drive', '#1fa463', 'Gd', '#drive\.google\.com#i'],
        'github'   => ['GitHub', '#181717', 'Git', '#github\.com#i'],
        'cow'      => ['奶牛快传', '#2c6bed', '牛', '#cowtransfer\.com#i'],
        'ctfile'   => ['城通网盘', '#f5a623', '城', '#ctfile\.com#i'],
        'caiyun'   => ['移动云', '#0085d0', '移', '#(caiyun|139)\S*\.com#i'],
        'wss'      => ['文叔叔', '#6c5ce7', '文', '#wenshushu\.cn#i'],
        'thunder'  => ['迅雷', '#2c6df2', '迅', '#thunder\.xunlei\.com#i'],
        'magnet'   => ['磁力链接', '#6e56cf', '磁', '#^magnet:\?#i'],
    ];
    return $map;
}

/**
 * 下载卡片：根据链接识别网盘类型，显示品牌色 logo 与标签
 *
 * 支持：百度网盘 / 阿里云盘 / 夸克网盘 / 蓝奏云 / 天翼云盘 / 123云盘 /
 *       OneDrive / Google Drive / GitHub / 奶牛快传 / 城通网盘 / 移动云 /
 *       文叔叔 / 迅雷 / 磁力链接；无法识别时回退通用下载图标。
 * 可用 type 属性强制指定：[download url="…" type="baidu"]
 */
function ecod_sc_netdisk(string $url): ?array
{
    foreach (ecod_sc_netdisk_map() as $key => [$name, $color, $mark, $re]) {
        if (preg_match($re, $url)) {
            return ['key' => $key, 'name' => $name, 'color' => $color, 'mark' => $mark];
        }
    }
    return null;
}

/**
 * 下载卡片
 */
function ecod_sc_download(string $content): string
{
    return ecod_sc_block('download', '(?:\s+[^\]]*)?', static function (string $attrRaw, string $inner): string {
        $attrs = ecod_sc_attrs($attrRaw);
        $url = trim((string) ($attrs['url'] ?? ($attrs['href'] ?? '')));
        if ($url === '' || !preg_match('#^(https?:)?//|^(magnet|thunder):#i', $url)) {
            return $inner;
        }

        $pan = ecod_sc_netdisk($url);
        $typeKey = strtolower(trim((string) ($attrs['type'] ?? '')));
        if ($typeKey !== '' && ($pan === null || $pan['key'] !== $typeKey)) {
            /* type 属性强制指定类型（覆盖 URL 识别结果） */
            $map = ecod_sc_netdisk_map();
            if (isset($map[$typeKey])) {
                [$n, $c, $mk] = $map[$typeKey];
                $pan = ['key' => $typeKey, 'name' => $n, 'color' => $c, 'mark' => $mk];
            }
        }

        $name = trim((string) ($attrs['name'] ?? '点击下载'));
        $size = trim((string) ($attrs['size'] ?? ''));
        $note = trim(strip_tags($inner));
        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

        $icon = $pan !== null
            ? '<span class="ecod_dl__icon ecod_dl__icon--pan" style="background:' . $pan['color'] . '" aria-hidden="true">'
                . htmlspecialchars($pan['mark'], ENT_QUOTES, 'UTF-8') . '</span>'
            : '<span class="ecod_dl__icon">' . ecod_icon('download', 18) . '</span>';

        $tag = $pan !== null
            ? '<span class="ecod_dl__pan" style="color:' . $pan['color'] . '">' . htmlspecialchars($pan['name'], ENT_QUOTES, 'UTF-8') . '</span>'
            : '';

        return '<a class="ecod_dl" href="' . $safeUrl . '" target="_blank" rel="noopener nofollow" data-no-pjax'
            . ($pan !== null ? ' data-pan="' . $pan['key'] . '"' : '') . '>'
            . $icon
            . '<span class="ecod_dl__main">'
            . '<b>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</b>'
            . ($note !== '' ? '<small>' . htmlspecialchars($note, ENT_QUOTES, 'UTF-8') . '</small>' : '')
            . '</span>'
            . ($size !== '' ? '<span class="ecod_dl__size">' . htmlspecialchars($size, ENT_QUOTES, 'UTF-8') . '</span>' : '')
            . $tag
            . '</a>';
    }, $content);
}

/* 注册到 Typecho 内容过滤链 */
if (class_exists('\Typecho\Plugin')) {
    try {
        \Typecho\Plugin::factory('Widget\Base\Contents')->contentEx = 'ecod_shortcode_apply';
        \Typecho\Plugin::factory('Widget\Base\Contents')->excerptEx = 'ecod_shortcode_strip';
    } catch (\Throwable $e) {
        /* 注册失败只是短代码不生效，不影响正文 */
    }
}
