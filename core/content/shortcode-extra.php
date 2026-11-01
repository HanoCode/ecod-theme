<?php
/**
 * Ecod Theme · 扩展短代码
 *
 * 在 core/content/shortcode.php 的基础上补齐 Joe / Puock 主题的短代码能力，
 * 统一使用方括号语法（不兼容 Joe 的 {} 语法）。
 *
 * 内容类
 *   [alert type=success|info|warning|error|dark]…[/alert]   多色提示框
 *   [callout color="#7c4dff" title="…"]…[/callout]         强调框
 *   [anote icon=info href="…"]…[/anote]                    作者注 / 通知条
 *   [message type=success]…[/message]                      单行消息条
 *   [mtitle]标题[/mtitle] 或 [mtitle title="…"]             小节标题
 *   [copy label="复制"]文本[/copy]                          一键复制块
 *   [progress 60] / [progress percentage=60 color=…]       进度条
 *   [abtn href="…" icon=link]文字[/abtn]                   按钮
 *   [dotted]                                               虚线分隔
 *   [lamp green] / [lamp color=#0f0]                       状态灯
 *   [task done] / [task]                                   任务勾选框
 *   [card title="…" desc="…" icon=… href="…" tag="…"]      信息卡
 *   [cards column=2]…[/cards]                              卡片组
 *   [grid column=3 gap=12]…[/grid]                         网格容器
 *   [cloud]url | 提取码 | 说明[/cloud]                     网盘卡（多行，标准格式 URL 开头；
 *                                                          也容错「名称 | 链接: url 提取码: xx | 说明」）
 *   [cloud url="…" pwd="…" type=baidu]标题[/cloud]          网盘卡（单条）
 *   [posts limit="5" mode="grid"]                          文章列表（mode 同后台「默认卡片模式」）
 *   [article id="15" mode="feature"]                       引用文章卡
 *   [cat slug="dev"] / [cat id=3]                          分类卡
 *   [tag slug="php"] / [tag id=7]                          标签卡
 *   [github repo="owner/name" desc="…"]                    GitHub 仓库卡
 *   [gallery column=3 ratio=1/1 caption=hover]…[/gallery]  相册图集（灯箱翻页）
 *
 * 媒体类
 *   [bilibili bvid="BV1xx" page=1]                         B 站视频
 *   [video url="…" poster="…"]                             原生视频
 *   [mp3 url="…" name="…" cover="…"]                       单曲音频
 *   [music]url | 歌名 | 封面[/music]                        播放列表
 *
 * 行内
 *   :smile: :cry: :lol: …                                  表情（31 个）
 *   [tooltip text="…"]正文[/tooltip]                        悬停提示
 *   [badge green]NEW[/badge]                               行内标签
 *   [spoiler]剧透内容[/spoiler]                             剧透遮罩（点击展开）
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/* ------------------------------------------------------------------
 * 小工具
 * ---------------------------------------------------------------- */

/**
 * 自闭合短代码：[tag attr="…"]
 *
 * @param callable $fn 参数为已解析的属性数组
 */
function ecod_sc_self(string $tag, string $content, callable $fn): string
{
    $cb = static function (array $m) use ($fn): string {
        return (string) $fn(ecod_sc_attrs(trim((string) ($m[1] ?? ''))));
    };

    /* 独占一段（autop 包裹）时连 <p> 一起去掉，避免留下空段落 */
    $content = preg_replace_callback(
        '#<p>\s*\[' . $tag . '(?:\s+([^\]]*))?\]\s*</p>#i',
        $cb,
        $content
    ) ?? $content;

    return preg_replace_callback('#\[' . $tag . '(?:\s+([^\]]*))?\]#i', $cb, $content) ?? $content;
}

/**
 * 多行短代码的正文拆行：兼容 Markdown 换行、`<br>` 与 autop 的 `</p><p>`
 *
 * @return array<int, string> 已去标签、还原实体的非空行
 */
function ecod_sc_lines(string $inner): array
{
    $text = preg_replace('#</p>\s*<p>#i', "\n", $inner) ?? $inner;
    $out = [];
    foreach (preg_split('/\r\n|\r|\n|<br\s*\/?>/i', $text) ?: [] as $line) {
        $line = html_entity_decode(trim(strip_tags((string) $line)), ENT_QUOTES, 'UTF-8');
        if ($line !== '') {
            $out[] = $line;
        }
    }

    return $out;
}

/**
 * 去掉 autop 在容器内侧留下的游离段落标记
 *
 * 编辑器 HTML 模式下逐行写 `[grid …]` / `[card …]` / `[/grid]`，每行都会被 autop 包成
 * `<p>…</p>`；块级替换后容器内会残留 `</p>` 与 `<p>`，导致 DOM 结构错乱。
 */
function ecod_sc_clean_inner(string $html): string
{
    for ($i = 0; $i < 3; $i++) {
        $new = preg_replace('#^\s*(?:</?p>|&nbsp;|<br\s*/?>)\s*#i', '', $html) ?? $html;
        $new = preg_replace('#\s*(?:</?p>|&nbsp;|<br\s*/?>)\s*$#i', '', $new) ?? $new;
        /* 相邻组件之间的 `</p><p>` 也要收掉 */
        $new = preg_replace('#(</(?:div|a|figure)>|</li>)\s*(?:</p>\s*<p>|</p>|<p>)\s*(?=<(?:div|a|figure|ul|ol)\b)#i', '$1', $new) ?? $new;
        if ($new === $html) {
            break;
        }
        $html = $new;
    }

    return $html;
}

/**
 * 十六进制颜色转 rgba()
 */
function ecod_hex_rgba(string $hex, float $alpha = 0.12): string
{
    $hex = trim($hex);
    if (!preg_match('/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i', $hex, $m)) {
        return 'rgba(79, 110, 242, ' . $alpha . ')';
    }
    $h = $m[1];
    if (strlen($h) === 3) {
        $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
    }
    return sprintf(
        'rgba(%d, %d, %d, %s)',
        hexdec(substr($h, 0, 2)),
        hexdec(substr($h, 2, 2)),
        hexdec(substr($h, 4, 2)),
        rtrim(rtrim((string) $alpha, '0'), '.')
    );
}

/**
 * 把颜色名映射为色值
 */
function ecod_color_value(string $name): string
{
    $name = strtolower(trim($name));
    if ($name === '' || $name === 'primary') {
        /* 跟随后台配置的主题色，而不是写死色值 */
        try {
            $primary = trim((string) ecod_primary());
            if ($primary !== '') {
                return $primary;
            }
        } catch (\Throwable $e) {
        }
        return '#4f6ef2';
    }
    $map = [
        'blue'    => '#3b82f6',
        'green'   => '#10b981',
        'teal'    => '#14b8a6',
        'cyan'    => '#06b6d4',
        'yellow'  => '#f59e0b',
        'orange'  => '#f97316',
        'red'     => '#ef4444',
        'pink'    => '#ec4899',
        'purple'  => '#8b5cf6',
        'gray'    => '#94a3b8',
        'dark'    => '#64748b',
    ];
    if (isset($map[$name])) {
        return $map[$name];
    }
    return preg_match('/^#?[0-9a-f]{3,6}$/i', $name) ? (strpos($name, '#') === 0 ? $name : '#' . $name) : '#4f6ef2';
}

/* ------------------------------------------------------------------
 * 提示框 / 消息 / 强调框 / 作者注
 * ---------------------------------------------------------------- */

/**
 * 多色提示框（复用 .ecod_note 的结构与样式）
 */
function ecod_sc_alert(string $content): string
{
    $map = [
        'info'    => ['info', '说明'],
        'primary' => ['info', '说明'],
        'success' => ['check-circle', '成功'],
        'tip'     => ['bulb', '提示'],
        'warning' => ['alert', '注意'],
        'warn'    => ['alert', '注意'],
        'error'   => ['x-circle', '错误'],
        'danger'  => ['x-circle', '错误'],
        'dark'    => ['quote', '备注'],
    ];
    $alias = ['primary' => 'info', 'warning' => 'warn', 'error' => 'danger'];

    return ecod_sc_block('alert', '(?:\s+[^\]]*)?', static function (string $attrRaw, string $inner) use ($map, $alias): string {
        $attrs = ecod_sc_attrs($attrRaw);
        $type = strtolower(trim((string) ($attrs['type'] ?? 'info')));
        if (!isset($map[$type])) {
            $type = 'info';
        }
        $class = $alias[$type] ?? $type;
        $label = (string) ($attrs['title'] ?? $map[$type][1]);

        return '<div class="ecod_note is-' . $class . '">'
            . '<div class="ecod_note__head">' . ecod_icon($map[$type][0], 15)
            . '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></div>'
            . '<div class="ecod_note__body">' . $inner . '</div>'
            . '</div>';
    }, $content);
}

/**
 * 单行消息条
 */
function ecod_sc_message(string $content): string
{
    $map = [
        'success' => ['check-circle', '#10b981'],
        'error'   => ['x-circle', '#ef4444'],
        'warning' => ['alert', '#f59e0b'],
        'info'    => ['info', '#3b82f6'],
    ];

    return ecod_sc_block('message', '(?:\s+[^\]]*)?', static function (string $attrRaw, string $inner) use ($map): string {
        $attrs = ecod_sc_attrs($attrRaw);
        $type = strtolower(trim((string) ($attrs['type'] ?? 'info')));
        if (!isset($map[$type])) {
            $type = 'info';
        }
        [$icon, $color] = $map[$type];

        return '<div class="ecod_msg is-' . $type . '" style="--c:' . $color . ';--c-soft:' . ecod_hex_rgba($color, 0.09) . '">'
            . '<span class="ecod_msg__icon">' . ecod_icon($icon, 15) . '</span>'
            . '<span class="ecod_msg__text">' . trim($inner) . '</span>'
            . '</div>';
    }, $content);
}

/**
 * 强调框（自定义颜色）
 */
function ecod_sc_callout(string $content): string
{
    return ecod_sc_block('callout', '(?:\s+[^\]]*)?', static function (string $attrRaw, string $inner): string {
        $attrs = ecod_sc_attrs($attrRaw);
        $color = ecod_color_value((string) ($attrs['color'] ?? 'primary'));
        $title = trim((string) ($attrs['title'] ?? ''));
        $icon = trim((string) ($attrs['icon'] ?? ''));

        $head = '';
        if ($title !== '' || $icon !== '') {
            $head = '<div class="ecod_callout__head">'
                . ($icon !== '' ? ecod_icon($icon, 15) : '')
                . ($title !== '' ? '<span>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</span>' : '')
                . '</div>';
        }

        return '<div class="ecod_callout" style="--c:' . $color . ';--c-soft:' . ecod_hex_rgba($color, 0.1) . '">'
            . $head . '<div class="ecod_callout__body">' . $inner . '</div></div>';
    }, $content);
}

/**
 * 作者注 / 通知条
 */
function ecod_sc_anote(string $content): string
{
    return ecod_sc_block('anote', '(?:\s+[^\]]*)?', static function (string $attrRaw, string $inner): string {
        $attrs = ecod_sc_attrs($attrRaw);
        $icon = trim((string) ($attrs['icon'] ?? 'info'));
        $href = ecod_sc_url(trim((string) ($attrs['href'] ?? '')), '');
        $author = trim((string) ($attrs['author'] ?? ''));

        $body = '<span class="ecod_anote__icon">' . ecod_icon($icon !== '' ? $icon : 'info', 14) . '</span>'
            . '<span class="ecod_anote__text">' . trim($inner) . '</span>'
            . ($author !== '' ? '<span class="ecod_anote__author">' . htmlspecialchars($author, ENT_QUOTES, 'UTF-8') . '</span>' : '');

        if ($href !== '') {
            return '<a class="ecod_anote is-link" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8')
                . '" target="_blank" rel="noopener nofollow">' . $body . '</a>';
        }

        return '<div class="ecod_anote">' . $body . '</div>';
    }, $content);
}

/* ------------------------------------------------------------------
 * 标题 / 复制 / 进度 / 按钮 / 分隔 / 状态灯 / 任务
 * ---------------------------------------------------------------- */

/**
 * 小节标题
 */
function ecod_sc_mtitle(string $content): string
{
    $content = ecod_sc_block('mtitle', '(?:\s+[^\]]*)?', static function (string $attrRaw, string $inner): string {
        $attrs = ecod_sc_attrs($attrRaw);
        $title = trim((string) ($attrs['title'] ?? '')) ?: trim(strip_tags($inner));
        return ecod_sc_mtitle_html($title);
    }, $content);

    return ecod_sc_self('mtitle', $content, static function (array $attrs): string {
        return ecod_sc_mtitle_html(trim((string) ($attrs['title'] ?? $attrs['type'] ?? '')));
    });
}

/**
 * 小节标题 HTML
 */
function ecod_sc_mtitle_html(string $title): string
{
    if ($title === '') {
        return '';
    }
    return '<div class="ecod_mtitle"><span class="ecod_mtitle__bar"></span>'
        . '<span class="ecod_mtitle__text">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</span></div>';
}

/**
 * 一键复制块
 */
function ecod_sc_copy(string $content): string
{
    return ecod_sc_block('copy', '(?:\s+[^\]]*)?', static function (string $attrRaw, string $inner): string {
        $attrs = ecod_sc_attrs($attrRaw);
        $label = trim((string) ($attrs['label'] ?? '复制'));
        $text = trim(html_entity_decode(strip_tags($inner), ENT_QUOTES, 'UTF-8'));

        return '<div class="ecod_copybox" data-ecod-copybox>'
            . '<div class="ecod_copybox__bar"><span class="ecod_copybox__label">'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>'
            . '<button type="button" class="ecod_copybox__btn" data-ecod-copy="'
            . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '">'
            . ecod_icon('copy', 12) . '<span>复制</span></button></div>'
            . '<div class="ecod_copybox__body">' . trim($inner) . '</div>'
            . '</div>';
    }, $content);
}

/**
 * 进度条
 */
function ecod_sc_progress(string $content): string
{
    return ecod_sc_self('progress', $content, static function (array $attrs): string {
        $raw = $attrs['percentage'] ?? $attrs['value'] ?? $attrs['type'] ?? '0';
        $percent = max(0, min(100, (float) str_replace('%', '', trim((string) $raw))));
        /* 数字展示：60 → "60"，12.5 → "12.5"，0/100 不丢位（不能用 rtrim('0')） */
        $num = rtrim(rtrim(number_format($percent, 1, '.', ''), '0'), '.');
        if ($num === '') {
            $num = '0';
        }
        $color = ecod_color_value((string) ($attrs['color'] ?? 'primary'));
        $label = trim((string) ($attrs['label'] ?? ''));

        return '<div class="ecod_prog" style="--c:' . $color . '" data-ecod-prog="' . $num . '">'
            . ($label !== '' ? '<span class="ecod_prog__label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>' : '')
            . '<span class="ecod_prog__track"><span class="ecod_prog__bar" style="width:' . $num . '%"></span></span>'
            . '<span class="ecod_prog__num">' . $num . '%</span>'
            . '</div>';
    });
}

/**
 * 按钮
 */
function ecod_sc_abtn(string $content): string
{
    return ecod_sc_block('abtn', '(?:\s+[^\]]*)?', static function (string $attrRaw, string $inner): string {
        $attrs = ecod_sc_attrs($attrRaw);
        $href = ecod_sc_url(trim((string) ($attrs['href'] ?? ($attrs['url'] ?? '#'))));
        $text = trim(strip_tags($inner)) ?: trim((string) ($attrs['content'] ?? '按钮'));
        $icon = trim((string) ($attrs['icon'] ?? ''));
        $colorName = strtolower(trim((string) ($attrs['color'] ?? 'primary')));
        $style = strtolower(trim((string) ($attrs['style'] ?? 'fill')));
        $radius = trim((string) ($attrs['radius'] ?? ''));

        $safeHref = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
        $blank = preg_match('#^(https?:)?//#i', $href) ? ' target="_blank" rel="noopener nofollow"' : '';
        $cls = 'ecod_btn' . ($style === 'ghost' ? ' is-ghost' : '');

        if ($colorName === '' || $colorName === 'primary') {
            /* 跟随主题主色但不输出裸色值：白字填充面用 --ecod-primary-btn（对比度
               达标的派生色），ghost 文字用 --ecod-primary-text（软底安全文字色），
               暗色主题自动切换（DESIGN.md · 主色可访问性派生）。浅暖主色（琥珀）
               下裸 primary + 白字只有 2.08:1，是「亮暗两色」不一致的根源 */
            $colorStyle = '--c:var(--ecod-' . ($style === 'ghost' ? 'primary-text' : 'primary-btn') . ')'
                . ';--c-soft:color-mix(in srgb, var(--ecod-primary) 10%, transparent)';
        } else {
            $color = ecod_color_value($colorName);
            $colorStyle = '--c:' . $color . ';--c-soft:' . ecod_hex_rgba($color, 0.1);
        }

        return '<a class="' . $cls . '" href="' . $safeHref . '"' . $blank
            . ' style="' . $colorStyle
            . ($radius !== '' ? ';--r:' . htmlspecialchars($radius, ENT_QUOTES, 'UTF-8') : '') . '">'
            . ($icon !== '' ? ecod_icon($icon, 14) : '')
            . '<span>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span></a>';
    }, $content);
}

/**
 * 虚线分隔
 */
function ecod_sc_dotted(string $content): string
{
    return ecod_sc_self('dotted', $content, static function (): string {
        return '<div class="ecod_dotted" role="separator"></div>';
    });
}

/**
 * 状态灯
 */
function ecod_sc_lamp(string $content): string
{
    return ecod_sc_self('lamp', $content, static function (array $attrs): string {
        $color = ecod_color_value((string) ($attrs['color'] ?? $attrs['type'] ?? 'green'));
        $text = trim((string) ($attrs['text'] ?? ''));
        return '<span class="ecod_lamp" style="--c:' . $color . '"></span>'
            . ($text !== '' ? '<span class="ecod_lamp__text">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>' : '');
    });
}

/**
 * 任务勾选框
 */
function ecod_sc_task(string $content): string
{
    return ecod_sc_self('task', $content, static function (array $attrs): string {
        $state = strtolower(trim((string) ($attrs['type'] ?? $attrs['done'] ?? '')));
        $done = in_array($state, ['done', 'x', 'yes', '1', 'true'], true);
        return '<span class="ecod_task' . ($done ? ' is-done' : '') . '" role="img" aria-label="'
            . ($done ? '已完成' : '未完成') . '">' . ecod_icon('check', 10) . '</span>';
    });
}

/* ------------------------------------------------------------------
 * 卡片 / 卡片组 / 网格
 * ---------------------------------------------------------------- */

/**
 * 信息卡
 */
function ecod_sc_card(string $content): string
{
    return ecod_sc_block('card', '(?:\s+[^\]]*)?', static function (string $attrRaw, string $inner): string {
        return ecod_icard_html(ecod_sc_attrs($attrRaw), trim($inner));
    }, ecod_sc_self('card', $content, static function (array $attrs): string {
        return ecod_icard_html($attrs, '');
    }));
}

/**
 * 单张信息卡 HTML
 */
function ecod_icard_html(array $attrs, string $inner = ''): string
{
    $title = trim((string) ($attrs['title'] ?? ''));
    $desc = trim((string) ($attrs['desc'] ?? ''));
    $icon = trim((string) ($attrs['icon'] ?? 'file'));
    $href = ecod_sc_url(trim((string) ($attrs['href'] ?? ($attrs['url'] ?? ''))), '');
    $tag = trim((string) ($attrs['tag'] ?? ''));
    $color = ecod_color_value((string) ($attrs['color'] ?? 'primary'));

    $body = '<span class="ecod_icard__icon" style="--c:' . $color . ';--c-soft:' . ecod_hex_rgba($color, 0.1) . '">'
        . ecod_icon($icon !== '' ? $icon : 'file', 16) . '</span>'
        . '<span class="ecod_icard__main">'
        . '<b>' . htmlspecialchars($title !== '' ? $title : '卡片标题', ENT_QUOTES, 'UTF-8') . '</b>'
        . ($desc !== '' ? '<small>' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '</small>' : '')
        . '</span>'
        . ($tag !== '' ? '<span class="ecod_icard__tag">' . htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') . '</span>' : '')
        . ($href !== '' ? '<span class="ecod_icard__go">' . ecod_icon('chevron', 13) . '</span>' : '');

    if ($href !== '') {
        return '<a class="ecod_icard is-link" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8')
            . '"' . (preg_match('#^(https?:)?//#i', $href) ? ' target="_blank" rel="noopener nofollow"' : '') . '>'
            . $body . ($inner !== '' ? '<span class="ecod_icard__desc">' . $inner . '</span>' : '') . '</a>';
    }

    return '<div class="ecod_icard">' . $body
        . ($inner !== '' ? '<div class="ecod_icard__desc">' . $inner . '</div>' : '') . '</div>';
}

/**
 * 卡片组
 */
function ecod_sc_cards(string $content): string
{
    return ecod_sc_block('cards', '(?:\s+[^\]]*)?', static function (string $attrRaw, string $inner): string {
        $attrs = ecod_sc_attrs($attrRaw);
        $col = max(1, min(4, (int) ($attrs['column'] ?? $attrs['cols'] ?? 2)));
        $title = trim((string) ($attrs['title'] ?? ''));

        // 先渲染内部卡片，再套容器
        $inner = preg_replace_callback('#\[card(?:\s+([^\]]*))?\](?:(.*?)\[/card\])?#is', static function (array $m) {
            return ecod_icard_html(ecod_sc_attrs(trim((string) ($m[1] ?? ''))), trim((string) ($m[2] ?? '')));
        }, $inner) ?? $inner;
        $inner = ecod_sc_clean_inner($inner);

        return '<div class="ecod_cards" style="--col:' . $col . '">'
            . ($title !== '' ? '<div class="ecod_cards__title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div>' : '')
            . '<div class="ecod_cards__list">' . $inner . '</div></div>';
    }, $content);
}

/**
 * 通用网格容器
 */
function ecod_sc_grid(string $content): string
{
    return ecod_sc_block('grid', '(?:\s+[^\]]*)?', static function (string $attrRaw, string $inner): string {
        $attrs = ecod_sc_attrs($attrRaw);
        $col = max(1, min(4, (int) ($attrs['column'] ?? $attrs['cols'] ?? 2)));
        $gap = max(0, min(40, (int) ($attrs['gap'] ?? 12)));

        return '<div class="ecod_grid" style="--col:' . $col . ';--gap:' . $gap . 'px">'
            . ecod_sc_clean_inner($inner) . '</div>';
    }, $content);
}

/* ------------------------------------------------------------------
 * 网盘卡
 * ---------------------------------------------------------------- */

/**
 * 网盘下载卡（多行 / 单条）
 */
function ecod_sc_cloud(string $content): string
{
    return ecod_sc_block('cloud', '(?:\s+[^\]]*)?', static function (string $attrRaw, string $inner): string {
        $attrs = ecod_sc_attrs($attrRaw);
        $rows = [];

        if (!empty($attrs['url'])) {
            $rows[] = [
                'url'   => ecod_sc_url((string) $attrs['url'], ''),
                'pwd'   => (string) ($attrs['pwd'] ?? ($attrs['password'] ?? '')),
                'title' => trim(strip_tags($inner)) ?: (string) ($attrs['title'] ?? ''),
                'type'  => (string) ($attrs['type'] ?? ''),
            ];
        } else {
            foreach (ecod_sc_lines($inner) as $line) {
                if (!preg_match('#https?://|magnet:\?|thunder://#i', $line)) {
                    continue;
                }
                $parts = array_map('trim', explode('|', $line));

                /* 标准格式：URL 开头（url | 提取码 | 说明） */
                if (preg_match('#^(https?:)?//|^magnet:|^thunder:#i', (string) $parts[0])) {
                    $rows[] = [
                        'url'   => ecod_sc_url($parts[0] ?? '', ''),
                        'pwd'   => $parts[1] ?? '',
                        'title' => $parts[2] ?? '',
                        'type'  => '',
                    ];
                    continue;
                }

                /* 容错格式：名称在前 / 「链接: … 提取码: …」混写——行内抽 URL、提取码与说明 */
                $url = '';
                $pwd = '';
                $leftovers = [];
                foreach ($parts as $part) {
                    if ($url === '' && preg_match('#(?:https?://|magnet:\?|thunder://)\S+#i', $part, $m)) {
                        $url = $m[0];
                        $part = trim(str_replace($m[0], '', $part));
                    }
                    if ($pwd === '' && preg_match('#(?:提取码|密码|访问码)[:：]?\s*(\S+)#u', $part, $m)) {
                        $pwd = $m[1];
                        $part = trim(preg_replace('#(?:提取码|密码|访问码)[:：]?\s*\S+#u', '', $part, 1));
                    }
                    $part = preg_replace('#^(?:链接|地址|下载)[:：]\s*$#u', '', $part);
                    if ($part !== '') {
                        $leftovers[] = $part;
                    }
                }
                if ($url === '') {
                    continue;
                }
                $rows[] = [
                    'url'   => ecod_sc_url($url, ''),
                    'pwd'   => $pwd,
                    'title' => implode(' · ', $leftovers),
                    'type'  => '',
                ];
            }
        }

        if (!$rows) {
            return $inner;
        }

        $html = '';
        foreach ($rows as $row) {
            if ($row['url'] === '' || !preg_match('#^(https?:)?//|^magnet:|^thunder:#i', $row['url'])) {
                continue;
            }
            $pan = function_exists('ecod_sc_netdisk') ? ecod_sc_netdisk($row['url']) : null;
            if ($pan === null && $row['type'] !== '' && function_exists('ecod_sc_netdisk_map')) {
                $map = ecod_sc_netdisk_map();
                $key = strtolower($row['type']);
                if (isset($map[$key])) {
                    [$n, $c, $mk] = $map[$key];
                    $pan = ['key' => $key, 'name' => $n, 'color' => $c, 'mark' => $mk];
                }
            }

            $name = $row['title'] !== '' ? $row['title'] : ($pan['name'] ?? '下载链接');
            $color = $pan['color'] ?? '#4f6ef2';
            $mark = $pan['mark'] ?? '链';

            $html .= '<div class="ecod_netdisk__item">'
                . '<span class="ecod_dl__icon ecod_dl__icon--pan" style="background:' . $color . '">'
                . htmlspecialchars($mark, ENT_QUOTES, 'UTF-8') . '</span>'
                . '<span class="ecod_netdisk__main">'
                . '<b>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</b>'
                . ($pan !== null ? '<small>' . htmlspecialchars($pan['name'], ENT_QUOTES, 'UTF-8') . '</small>' : '')
                . '</span>'
                . ($row['pwd'] !== ''
                    ? '<button type="button" class="ecod_netdisk__pwd" data-ecod-copy="'
                        . htmlspecialchars($row['pwd'], ENT_QUOTES, 'UTF-8') . '" title="点击复制提取码">'
                        . '<span>' . htmlspecialchars($row['pwd'], ENT_QUOTES, 'UTF-8') . '</span>'
                        . ecod_icon('copy', 11) . '</button>'
                    : '')
                . '<a class="ecod_netdisk__go" href="' . htmlspecialchars($row['url'], ENT_QUOTES, 'UTF-8')
                . '" target="_blank" rel="noopener nofollow" data-no-pjax>打开'
                . ecod_icon('arrow-up-right', 12) . '</a>'
                . '</div>';
        }

        return $html !== '' ? '<div class="ecod_netdisk">' . $html . '</div>' : $inner;
    }, $content);
}

/* ------------------------------------------------------------------
 * 文章列表
 * ---------------------------------------------------------------- */

/**
 * 分类 / 标签的缩略名（或 mid）转 mid
 */
function ecod_sc_meta_mid(string $key, string $kind): int
{
    $key = trim($key);
    if ($key === '') {
        return 0;
    }

    try {
        $db = \Typecho\Db::get();
        $select = $db->select('mid')->from('table.metas')->where('type = ?', $kind)->limit(1);
        if (ctype_digit($key)) {
            $select->where('mid = ?', (int) $key);
        } else {
            $select->where('slug = ?', $key);
        }
        $row = $db->fetchRow($select);

        return (int) ($row['mid'] ?? 0);
    } catch (\Throwable $e) {
        return 0;
    }
}

/**
 * 短代码卡片模式：与后台「默认卡片模式」ECardMode 同一套取值
 *
 * mode 缺省 / 非法值时跟随后台默认；旧写法 mode="list" 等价于 default。
 * 取值：default feature gallery bare grid title compact mosaic item note
 *
 * @param mixed $raw 短代码里的 mode 属性
 */
function ecod_sc_card_mode($raw): string
{
    static $modes = ['default', 'feature', 'gallery', 'bare', 'grid', 'title', 'compact', 'mosaic', 'item', 'note'];

    $mode = strtolower(trim((string) $raw));
    if ($mode === 'list') {
        $mode = 'default';
    }
    if (in_array($mode, $modes, true)) {
        return $mode;
    }

    $default = function_exists('ecod_list_force_mode') ? ecod_list_force_mode(null) : 'default';

    return in_array($default, $modes, true) ? $default : 'default';
}

/**
 * 短代码用的文章查询实例（EcodPostQuery）
 *
 * Widget 池按别名缓存：共用别名时第二段列表会拿到已经走完的堆栈，
 * 渲染出来是空白，所以每次调用都取一个新别名。
 *
 * @param array<string, mixed> $params EcodPostQuery 参数
 * @return \Widget\Base\Contents
 */
function ecod_sc_card_query(array $params)
{
    static $seq = 0;
    $seq++;

    $query = [];
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null || $value === 0 || $value === '0') {
            continue;
        }
        $query[$key] = (string) $value;
    }

    return \EcodPostQuery::allocWithAlias('sc' . $seq, http_build_query($query, '', '&', PHP_QUERY_RFC3986));
}

/**
 * 卡片列表容器：列表容器类 + 模式类，并去掉列表壳（背景 / 内边距）
 */
function ecod_sc_card_list_class(string $mode): string
{
    $class = function_exists('ecod_list_class') ? ecod_list_class($mode) : 'ecod_list';

    return $class . ' ecod_list--plain ecod_sc_cards';
}

/**
 * 把查询结果渲染成卡片串
 *
 * @param \Widget\Base\Contents $query 已执行过的查询
 * @param string                $mode  卡片模式
 */
function ecod_sc_card_render($query, string $mode): string
{
    if (!$query->have()) {
        return '';
    }

    /* 渲染期间挂递归保护（见 ecod_shortcode_apply）：卡片摘要会读 $archive->content，
       正文里的 [posts] / [article] 会被再展开一次，不拦住会无限递归 */
    $hadFlag = isset($GLOBALS['ecodScRendering']);
    $GLOBALS['ecodScRendering'] = true;

    $html = '';
    while ($query->next()) {
        ob_start();
        ecod_card($query, $mode);
        $html .= (string) ob_get_clean();
    }

    if (!$hadFlag) {
        unset($GLOBALS['ecodScRendering']);
    }

    return $html;
}

/**
 * 文章列表
 *
 * [posts limit="5" category="tech" mode="grid"] / [posts related="1" mode="note"]
 * mode 与后台「默认卡片模式」同源，留空即跟随该设置。
 */
function ecod_sc_posts(string $content): string
{
    return ecod_sc_self('posts', $content, static function (array $attrs): string {
        $limit = max(1, min(20, (int) ($attrs['limit'] ?? ($attrs['type'] ?? 5))));
        $mode = ecod_sc_card_mode($attrs['mode'] ?? '');

        $order = strtolower(trim((string) ($attrs['order'] ?? 'date')));
        /* 短代码的排序词 → EcodPostQuery 的排序词（hot 历来按阅读量） */
        $orderMap = ['date' => 'recent', 'hot' => 'views', 'comment' => 'hot', 'random' => 'rand'];
        $orderParam = $orderMap[$order] ?? 'recent';

        $current = $GLOBALS['ecod_sc_archive'] ?? null;
        $currentCid = (int) ($current->cid ?? 0);

        $category = trim((string) ($attrs['category'] ?? ($attrs['cat'] ?? '')));
        $tag = trim((string) ($attrs['tag'] ?? ''));

        /* related="1"：沿用当前文章的首个分类，做「相关阅读」 */
        if ($category === '' && !empty($attrs['related']) && (string) $attrs['related'] !== '0' && $current) {
            $first = null;
            if (!empty($current->categories)) {
                $first = $current->categories[0] ?? null;
            }
            $category = is_array($first)
                ? (string) ($first['slug'] ?? ($first['mid'] ?? ''))
                : (string) ($first->slug ?? ($first->mid ?? ''));
        }

        /* 默认排除当前文章，避免「相关阅读」把自己列出来 */
        $exclude = [];
        if (isset($attrs['exclude']) && trim((string) $attrs['exclude']) !== '') {
            $exclude = array_map('intval', explode(',', (string) $attrs['exclude']));
        } elseif ($currentCid > 0 && empty($attrs['self'])) {
            $exclude[] = $currentCid;
        }

        $params = [
            'pageSize' => $limit,
            'order'    => $orderParam,
            'tags'     => $tag,
            'exclude'  => implode(',', array_filter($exclude)),
        ];
        if ($category !== '') {
            $mid = ecod_sc_meta_mid($category, 'category');
            if ($mid <= 0) {
                return '<div class="ecod_posts is-empty">没有符合条件的文章</div>';
            }
            $params['category'] = $mid;
        }

        $cards = ecod_sc_card_render(ecod_sc_card_query($params), $mode);
        if ($cards === '') {
            return '<div class="ecod_posts is-empty">没有符合条件的文章</div>';
        }

        /* 列数只对网格系生效（grid / note / mosaic），其余模式忽略 column */
        $style = '';
        if (isset($attrs['column']) && in_array($mode, ['grid', 'note', 'mosaic'], true)) {
            $col = max(1, min(4, (int) $attrs['column']));
            $style = ' style="grid-template-columns:repeat(' . $col . ',minmax(0,1fr))"';
        }

        $headTitle = trim((string) ($attrs['title'] ?? ''));
        $head = $headTitle !== ''
            ? '<div class="ecod_posts__head"><span class="ecod_posts__title">'
                . htmlspecialchars($headTitle, ENT_QUOTES, 'UTF-8') . '</span><i></i></div>'
            : '';

        return '<div class="ecod_posts">' . $head
            . '<div class="' . ecod_sc_card_list_class($mode) . '"' . $style . '>' . $cards . '</div>'
            . '</div>';
    });
}

/* ------------------------------------------------------------------
 * 内容引用：文章卡 / GitHub 卡
 * ---------------------------------------------------------------- */

/**
 * 引用文章卡
 *
 * [article id="15"] / [article id="15" mode="feature"]
 * mode 同 [posts]，取后台「默认卡片模式」那 10 种；留空跟随该设置。
 */
function ecod_sc_article(string $content): string
{
    return ecod_sc_self('article', $content, static function (array $attrs): string {
        $cid = (int) ($attrs['id'] ?? $attrs['type'] ?? 0);
        if ($cid <= 0) {
            return '';
        }

        $mode = ecod_sc_card_mode($attrs['mode'] ?? '');

        /* img="…"：手动指定封面（交给 ecod_thumbnails 的临时覆盖表） */
        $img = trim((string) ($attrs['img'] ?? ''));
        if ($img !== '') {
            $GLOBALS['ecodCoverOverride'][$cid] = function_exists('ecod_sc_url') ? ecod_sc_url($img, '') : $img;
        }

        $card = ecod_sc_card_render(ecod_sc_card_query(['pageSize' => 1, 'cids' => $cid]), $mode);
        if ($card === '') {
            return '<div class="ecod_acard is-miss">未找到文章（cid=' . $cid . '）</div>';
        }

        return '<div class="ecod_list ecod_list--plain ecod_sc_cards">' . $card . '</div>';
    });
}

/**
 * GitHub 仓库卡
 */
function ecod_sc_github(string $content): string
{
    return ecod_sc_self('github', $content, static function (array $attrs): string {
        $repo = trim((string) ($attrs['repo'] ?? $attrs['type'] ?? ''));
        if (preg_match('#github\.com/([\w.-]+/[\w.-]+)#i', $repo, $m)) {
            $repo = $m[1];
        }
        $repo = trim($repo, '/');
        if (!preg_match('#^[\w.-]+/[\w.-]+$#', $repo)) {
            return '';
        }
        $desc = trim((string) ($attrs['desc'] ?? ''));

        return '<a class="ecod_gh" href="https://github.com/' . htmlspecialchars($repo, ENT_QUOTES, 'UTF-8')
            . '" target="_blank" rel="noopener nofollow" data-ecod-gh="' . htmlspecialchars($repo, ENT_QUOTES, 'UTF-8') . '">'
            . '<span class="ecod_gh__logo">' . ecod_icon('github', 20) . '</span>'
            . '<span class="ecod_gh__main"><b>' . htmlspecialchars($repo, ENT_QUOTES, 'UTF-8') . '</b>'
            . ($desc !== '' ? '<small>' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '</small>' : '')
            . '</span>'
            . '<span class="ecod_gh__star" data-ecod-gh-star>' . ecod_icon('star', 12) . '<i>Star</i></span>'
            . '</a>';
    });
}

/* ------------------------------------------------------------------
 * 媒体
 * ---------------------------------------------------------------- */

/**
 * 视频平台链接 → iframe 播放器地址（识别不出返回空串，按原生 <video> 处理）
 */
function ecod_sc_video_embed(string $url): string
{
    /* YouTube：watch?v= / youtu.be / embed / shorts / live */
    if (preg_match('~(?:youtube\.com/(?:watch\?[^#]*?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{6,})~i', $url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1] . '?rel=0';
    }

    /* Vimeo */
    if (preg_match('~vimeo\.com/(?:video/)?(\d{5,})~i', $url, $m)) {
        return 'https://player.vimeo.com/video/' . $m[1];
    }

    /* 腾讯视频：v.qq.com/x/page/VID.html 或 /x/cover/…/VID.html */
    if (preg_match('~v\.qq\.com/x/(?:page|cover)/.*?([A-Za-z0-9]{8,})\.html~i', $url, $m)) {
        return 'https://v.qq.com/txp/iframe/player.html?vid=' . $m[1];
    }

    /* 优酷：v.youku.com/v_show/id_XXX.html */
    if (preg_match('~youku\.com/v_show/id_([A-Za-z0-9=]+)\.html~i', $url, $m)) {
        return 'https://player.youku.com/embed/' . $m[1];
    }

    return '';
}

/**
 * 音乐平台链接 → iframe 播放器地址（网易云：单曲 / 专辑 / 歌单）
 *
 * @return array{0:string,1:int} 播放器地址与建议高度
 */
function ecod_sc_music_embed(string $url): array
{
    if (preg_match('~music\.163\.com/(?:#/)?(song|album|playlist)\?id=(\d+)~i', $url, $m)) {
        $kind = strtolower($m[1]);
        $type = ['song' => 2, 'album' => 1, 'playlist' => 0][$kind];
        $height = $kind === 'playlist' ? 430 : 66;

        return ['https://music.163.com/outchain/player?type=' . $type . '&id=' . (int) $m[2]
            . '&auto=0&height=' . $height, $height];
    }

    return ['', 0];
}

/**
 * B 站视频
 */
function ecod_sc_bilibili(string $content): string
{
    return ecod_sc_self('bilibili', $content, static function (array $attrs): string {
        $bvid = trim((string) ($attrs['bvid'] ?? $attrs['type'] ?? ''));
        if (preg_match('#(BV[0-9A-Za-z]{10})#', $bvid, $m)) {
            $bvid = $m[1];
        }
        if ($bvid === '') {
            return '';
        }
        $page = max(1, (int) ($attrs['page'] ?? 1));
        $src = 'https://player.bilibili.com/player.html?bvid=' . rawurlencode($bvid) . '&page=' . $page . '&high_quality=1&autoplay=0';

        return '<div class="ecod_embed"><iframe src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8')
            . '" loading="lazy" scrolling="no" frameborder="0" allowfullscreen="true"'
            . ' sandbox="allow-top-navigation allow-same-origin allow-forms allow-scripts"'
            . ' referrerpolicy="no-referrer"></iframe></div>';
    });
}

/**
 * 视频：平台链接自动转 iframe，直链走原生 <video>
 */
function ecod_sc_video(string $content): string
{
    return ecod_sc_self('video', $content, static function (array $attrs): string {
        $url = ecod_sc_url(trim((string) ($attrs['url'] ?? $attrs['src'] ?? $attrs['type'] ?? '')), '');
        if ($url === '') {
            return '';
        }
        $poster = ecod_sc_url(trim((string) ($attrs['poster'] ?? '')), '');
        $name = trim((string) ($attrs['name'] ?? ''));

        /* 腾讯视频 / 优酷 / YouTube / Vimeo：链接直接贴进来即可 */
        $embed = ecod_sc_video_embed($url);
        if ($embed !== '') {
            return '<figure class="ecod_video ecod_video--embed">'
                . '<div class="ecod_embed"><iframe src="' . htmlspecialchars($embed, ENT_QUOTES, 'UTF-8')
                . '" loading="lazy" scrolling="no" frameborder="0" allowfullscreen="true"'
                . ' allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"'
                . ' referrerpolicy="no-referrer"></iframe></div>'
                . ($name !== '' ? '<figcaption>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</figcaption>' : '')
                . '</figure>';
        }

        return '<figure class="ecod_video">'
            . '<video controls preload="metadata" playsinline'
            . ($poster !== '' ? ' poster="' . htmlspecialchars($poster, ENT_QUOTES, 'UTF-8') . '"' : '')
            . ' src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></video>'
            . ($name !== '' ? '<figcaption>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</figcaption>' : '')
            . '</figure>';
    });
}

/**
 * 播放卡控件（进度 / 时间 / 控制键 / 音量），单曲与播放列表共用
 *
 * <audio> 保留 controls：JS 就绪后由脚本摘掉走自绘控件，
 * JS 不可用时回退原生控件，功能不丢。
 *
 * @param string $src  音频地址
 * @param bool   $skip 是否输出上一首/下一首（播放列表用）
 */
function ecod_sc_player_html(string $src, bool $skip): string
{
    $skipHtml = '';
    if ($skip) {
        $skipHtml = '<button type="button" class="ecod_player__btn" data-ecod-prev aria-label="上一首">'
            . '<svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true">'
            . '<path d="M6.8 5h2.3v14H6.8z" /><path d="M18.6 6.6v10.8c0 .95-1.07 1.51-1.85.97l-7.9-5.4a1.2 1.2 0 0 1 0-1.94l7.9-5.4c.78-.54 1.85.02 1.85.97z" /></svg>'
            . '</button>'
            . '<button type="button" class="ecod_player__btn" data-ecod-next aria-label="下一首">'
            . '<svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true">'
            . '<path d="M14.9 5h2.3v14h-2.3z" /><path d="M5.4 6.6v10.8c0 .95 1.07 1.51 1.85.97l7.9-5.4a1.2 1.2 0 0 0 0-1.94l-7.9-5.4C6.47 5.09 5.4 5.65 5.4 6.6z" /></svg>'
            . '</button>';
    }

    return '<span class="ecod_player" data-ecod-player>'
        . '<audio controls preload="none" src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"></audio>'
        . '<span class="ecod_player__bar">'
        . '<span class="ecod_player__time" data-ecod-cur>0:00</span>'
        . '<span class="ecod_player__slider" data-ecod-seek>'
        . '<span class="ecod_player__track"><span class="ecod_player__fill"></span></span>'
        . '</span>'
        . '<span class="ecod_player__time ecod_player__time--rem" data-ecod-rem>-0:00</span>'
        . '</span>'
        . '<span class="ecod_player__bar ecod_player__bar--ctl">'
        . '<span class="ecod_player__btns">' . $skipHtml
        . '<button type="button" class="ecod_player__btn ecod_player__btn--main" data-ecod-play aria-label="播放">'
        . ecod_icon('play', 20)
        . '</button>'
        . '</span>'
        . '<span class="ecod_player__vol">'
        . '<button type="button" class="ecod_player__btn ecod_player__btn--mute" data-ecod-mute aria-label="静音">'
        . '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true">'
        . '<path d="M4 9.5v5c0 .55.45 1 1 1h2.6l3.9 3.2c.65.53 1.5.07 1.5-.77V6.07c0-.84-.85-1.3-1.5-.77L7.6 8.5H5c-.55 0-1 .45-1 1z" /></svg>'
        . '</button>'
        . '<span class="ecod_player__slider ecod_player__slider--vol" data-ecod-vol>'
        . '<span class="ecod_player__track"><span class="ecod_player__fill" style="width:100%"></span></span>'
        . '</span>'
        . '<svg class="ecod_player__volmax" viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">'
        . '<path d="M3 9.5v5c0 .55.45 1 1 1h2.1l3.6 3c.65.53 1.5.07 1.5-.77V6.27c0-.84-.85-1.3-1.5-.77l-3.6 3H4c-.55 0-1 .45-1 1z" />'
        . '<path d="M14.5 8.2a.9.9 0 0 0-.25 1.25 4.6 4.6 0 0 1 0 5.1.9.9 0 1 0 1.5 1 6.4 6.4 0 0 0 0-7.1.9.9 0 0 0-1.25-.25z" />'
        . '<path d="M16.8 5.4a.9.9 0 0 0-.3 1.24 8.3 8.3 0 0 1 0 10.72.9.9 0 1 0 1.44 1.08 10.1 10.1 0 0 0 0-12.88.9.9 0 0 0-1.14-.16z" /></svg>'
        . '</span>'
        . '</span>'
        . '</span>';
}

/**
 * 单曲音频：网易云链接转官方外链播放器，其余渲染成音乐卡（elastic slider）
 */
function ecod_sc_mp3(string $content): string
{
    return ecod_sc_self('mp3', $content, static function (array $attrs): string {
        $url = ecod_sc_url(trim((string) ($attrs['url'] ?? $attrs['src'] ?? $attrs['type'] ?? '')), '');
        if ($url === '') {
            return '';
        }
        $name = trim((string) ($attrs['name'] ?? '音频'));
        $cover = ecod_sc_url(trim((string) ($attrs['cover'] ?? '')), '');

        [$embed, $height] = ecod_sc_music_embed($url);
        if ($embed !== '') {
            return '<div class="ecod_audio ecod_audio--embed">'
                . '<iframe src="' . htmlspecialchars($embed, ENT_QUOTES, 'UTF-8') . '"'
                . ' loading="lazy" frameborder="0" scrolling="no"'
                . ' style="width:100%;height:' . $height . 'px;border:0"'
                . ' sandbox="allow-scripts allow-same-origin allow-forms allow-popups"'
                . ' referrerpolicy="no-referrer" title="'
                . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"></iframe>'
                . '</div>';
        }

        return '<div class="ecod_audio">'
            . ($cover !== '' ? '<span class="ecod_audio__cover" style="--ecod-cover:url(&quot;'
                . htmlspecialchars($cover, ENT_QUOTES, 'UTF-8') . '&quot;)"></span>'
                : '<span class="ecod_audio__cover">' . ecod_icon('music', 26) . '</span>')
            . '<span class="ecod_audio__main"><b>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</b>'
            . ecod_sc_player_html($url, false)
            . '</span></div>';
    });
}

/**
 * 播放列表
 */
function ecod_sc_music(string $content): string
{
    return ecod_sc_block('music', '(?:\s+[^\]]*)?', static function (string $attrRaw, string $inner): string {
        $attrs = ecod_sc_attrs($attrRaw);
        $title = trim((string) ($attrs['title'] ?? '播放列表'));
        $items = [];

        foreach (ecod_sc_lines($inner) as $line) {
            if (stripos($line, 'http') === false) {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $url = ecod_sc_url($parts[0] ?? '', '');
            if ($url === '') {
                continue;
            }
            $items[] = [
                'url'   => $url,
                'name'  => $parts[1] ?? '音轨 ' . (count($items) + 1),
                'cover' => ecod_sc_url($parts[2] ?? '', ''),
            ];
        }
        if (!$items) {
            return $inner;
        }

        $list = '';
        foreach ($items as $i => $it) {
            $list .= '<li class="ecod_playlist__item' . ($i === 0 ? ' is-active' : '') . '"'
                . ' data-ecod-track="' . htmlspecialchars(json_encode($it, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '', ENT_QUOTES, 'UTF-8') . '">'
                . '<span class="ecod_playlist__idx">' . ($i + 1) . '</span>'
                . '<span class="ecod_playlist__name">' . htmlspecialchars($it['name'], ENT_QUOTES, 'UTF-8') . '</span>'
                . '<span class="ecod_playlist__play">' . ecod_icon('play', 13) . '</span>'
                . '</li>';
        }

        $first = $items[0];

        return '<div class="ecod_playlist" data-ecod-playlist>'
            . '<div class="ecod_playlist__head">' . ecod_icon('music', 14)
            . '<span>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</span></div>'
            . '<div class="ecod_playlist__player">'
            . '<span class="ecod_playlist__cover" style="--ecod-cover:url(&quot;'
            . htmlspecialchars($first['cover'], ENT_QUOTES, 'UTF-8') . '&quot;)"></span>'
            . '<span class="ecod_playlist__meta">'
            . '<span class="ecod_playlist__now" data-ecod-now>' . htmlspecialchars($first['name'], ENT_QUOTES, 'UTF-8') . '</span>'
            . ecod_sc_player_html($first['url'], true)
            . '</span>'
            . '</div>'
            . '<ol class="ecod_playlist__list">' . $list . '</ol>'
            . '</div>';
    }, $content);
}

/* ------------------------------------------------------------------
 * 相册图集
 * ---------------------------------------------------------------- */

/**
 * 行内短代码：[tag attr]内容[/tag]
 *
 * 与 ecod_sc_block 的区别：不动前后的空白与 <p>，避免「状态 [badge] 稳定」这类
 * 行内标记把空格吃掉。
 *
 * @param callable $fn 参数为 (属性串, 内容)
 */
function ecod_sc_inline(string $tag, string $attrs, callable $fn, string $content): string
{
    if (stripos($content, '[' . $tag) === false) {
        return $content;
    }

    $pattern = '#\[' . $tag . '(' . $attrs . ')\](.*?)\[/' . $tag . '\]#is';

    return preg_replace_callback(
        $pattern,
        static function (array $m) use ($fn): string {
            return (string) $fn(trim((string) ($m[1] ?? '')), (string) ($m[2] ?? ''));
        },
        $content
    ) ?? $content;
}

/**
 * 相册图片地址校验：站内相对路径 / http(s) / 带图片后缀的相对路径
 */
function ecod_sc_image_src(string $raw): string
{
    $url = ecod_sc_url(trim($raw), '');
    if ($url === '') {
        return '';
    }
    if (preg_match('#^(?:https?:)?//#i', $url) || preg_match('#^\.{0,2}/#', $url)) {
        return $url;
    }
    /* 纯相对路径（如 uploads/a.jpg）必须带图片后缀，避免把说明文字当地址 */
    if (preg_match('#^[^\s]+\.(?:jpe?g|png|gif|webp|avif|bmp|svg)(?:\?[^\s]*)?$#i', $url)) {
        return $url;
    }
    return '';
}

/**
 * 读标签内某个属性的值（用于解析正文里已有的 <img> 标记）
 */
function ecod_sc_tag_attr(string $tag, string $name): string
{
    $pattern = '#\b' . preg_quote($name, '#') . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))#i';
    if (preg_match($pattern, $tag, $m)) {
        $value = ($m[1] ?? '') !== '' ? $m[1] : ((($m[2] ?? '') !== '') ? $m[2] : ($m[3] ?? ''));
        return html_entity_decode(trim($value), ENT_QUOTES, 'UTF-8');
    }
    return '';
}

/**
 * 相册图集
 *
 *   [gallery column="3" ratio="1/1" gap="8" caption="hover" title="…"]
 *   https://a.jpg | 说明
 *   https://b.jpg
 *   [/gallery]
 *
 * 也接受正文里已经写好的 <img src="…" alt="…">（Markdown 的 ![]() 会被 autop 转成 img），
 * 两种写法可以混用。
 */
function ecod_sc_gallery(string $content): string
{
    if (stripos($content, '[gallery') === false) {
        return $content;
    }

    return ecod_sc_block('gallery', '(?:\s+[^\]]*)?', static function (string $attrRaw, string $inner): string {
        $attrs = ecod_sc_attrs($attrRaw);

        $col = (int) ($attrs['column'] ?? ($attrs['col'] ?? 3));
        $col = max(1, min(6, $col));
        $gap = (int) ($attrs['gap'] ?? 8);
        $gap = max(2, min(24, $gap));

        $ratio = strtolower(trim((string) ($attrs['ratio'] ?? '1/1')));
        $ratioMap = ['1/1', '4/3', '3/4', '16/9', '9/16', '4/5', 'auto'];
        if (!in_array($ratio, $ratioMap, true)) {
            $ratio = '1/1';
        }

        $mode = strtolower(trim((string) ($attrs['mode'] ?? 'grid')));
        $mode = in_array($mode, ['masonry', 'waterfall', '瀑布流'], true) ? 'masonry' : 'grid';

        $caption = strtolower(trim((string) ($attrs['caption'] ?? 'hover')));
        $caption = in_array($caption, ['always', 'off', 'hover'], true) ? $caption : 'hover';

        $zoom = !in_array(strtolower(trim((string) ($attrs['zoom'] ?? '1'))), ['0', 'off', 'false', 'no'], true);
        $title = trim((string) ($attrs['title'] ?? ''));

        $items = [];

        /* ① 正文里已有的 <img> 标记（Markdown 图片语法 / 手写 HTML） */
        if (preg_match_all('#<img\b[^>]*>#i', $inner, $imgs)) {
            foreach ($imgs[0] as $tag) {
                $src = ecod_sc_image_src(ecod_sc_tag_attr($tag, 'src'));
                if ($src === '') {
                    continue;
                }
                $items[] = [
                    'src' => $src,
                    'cap' => ecod_sc_tag_attr($tag, 'alt'),
                ];
            }
            $inner = preg_replace('#<img\b[^>]*>#i', '', $inner) ?? $inner;
        }

        /* ② 逐行「图片地址 | 说明」，说明可省略 */
        foreach (ecod_sc_lines($inner) as $line) {
            $parts = preg_split('/\s*[|｜]\s*/u', $line, 2);
            $src = ecod_sc_image_src((string) ($parts[0] ?? ''));
            if ($src === '') {
                continue;
            }
            $items[] = [
                'src' => $src,
                'cap' => trim((string) ($parts[1] ?? '')),
            ];
        }

        if (!$items) {
            return '';
        }

        $gid = 'g' . substr(md5($inner . count($items)), 0, 6);
        $cells = '';
        foreach ($items as $index => $item) {
            $cap = $item['cap'];
            $escSrc = htmlspecialchars($item['src'], ENT_QUOTES, 'UTF-8');
            $escCap = htmlspecialchars($cap, ENT_QUOTES, 'UTF-8');

            $cells .= '<button type="button" class="ecod_gal__item"'
                . ($zoom
                    ? ' data-ecod-lightbox data-lb-group="' . $gid . '"'
                        . ' data-src="' . $escSrc . '" data-alt="' . $escCap . '"'
                    : '')
                . ' aria-label="' . ($cap !== '' ? $escCap : '查看第 ' . ($index + 1) . ' 张大图') . '">'
                . '<img src="' . $escSrc . '" alt="' . $escCap . '" loading="lazy" decoding="async">'
                . ($cap !== '' && $caption !== 'off'
                    ? '<span class="ecod_gal__cap">' . $escCap . '</span>'
                    : '')
                . '</button>';
        }

        return '<figure class="ecod_gal is-' . $mode . ' cap-' . $caption . '"'
            . ' style="--col:' . $col . ';--gap:' . $gap . 'px;--ratio:' . $ratio . '">'
            . ($title !== ''
                ? '<figcaption class="ecod_gal__head">' . ecod_icon('image', 15)
                    . '<span>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</span></figcaption>'
                : '')
            . '<div class="ecod_gal__grid">' . $cells . '</div>'
            . '</figure>';
    }, $content);
}

/* ------------------------------------------------------------------
 * 表情
 * ---------------------------------------------------------------- */

/**
 * 表情代码表（与后台编辑器选择器共用 core/data/emoji.php）
 *
 * @return array<string, array{0:string,1:string}>
 */
function ecod_emoji_map(): array
{
    static $map = null;
    if ($map === null) {
        $file = __DIR__ . '/../data/emoji.php';
        $data = is_file($file) ? require $file : [];
        $map = is_array($data) ? $data : [];
    }
    return $map;
}

/**
 * 表情短代码 :smile: → <img>
 */
function ecod_sc_emoji(string $content): string
{
    if (strpos($content, ':') === false) {
        return $content;
    }

    $replace = [];
    foreach (ecod_emoji_map() as $code => [$file, $label]) {
        $src = function_exists('ecod_asset')
            ? ecod_asset('assets/img/smiley/' . $file)
            : '/usr/themes/ecod-theme/assets/img/smiley/' . $file;
        $replace[$code] = '<img class="ecod_emoji" src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8')
            . '" alt="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            . '" title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '" loading="lazy">';
    }

    return strtr($content, $replace);
}

/* ------------------------------------------------------------------
 * 行内标记：tooltip / badge / spoiler
 * ---------------------------------------------------------------- */

/**
 * 悬停提示
 *
 *   [tooltip text="解释文字"]正文[/tooltip]
 *   [tooltip tip="解释文字" place="bottom"]正文[/tooltip]
 */
function ecod_sc_tooltip(string $content): string
{
    return ecod_sc_inline('tooltip', '(?:\s+[^\]]*)?', static function (string $attrRaw, string $inner): string {
        $attrs = ecod_sc_attrs($attrRaw);
        $tip = trim((string) ($attrs['text'] ?? ($attrs['tip'] ?? ($attrs['title'] ?? ''))));
        if ($tip === '') {
            return $inner;
        }

        $place = strtolower(trim((string) ($attrs['place'] ?? 'top')));
        $place = $place === 'bottom' ? 'bottom' : 'top';

        $label = trim(preg_replace('#</?p>#i', '', $inner) ?? $inner);
        if ($label === '') {
            $label = htmlspecialchars($tip, ENT_QUOTES, 'UTF-8');
        }

        return '<span class="ecod_tip is-' . $place . '" tabindex="0" role="note">'
            . $label
            . '<span class="ecod_tip__pop" role="tooltip">' . htmlspecialchars($tip, ENT_QUOTES, 'UTF-8') . '</span>'
            . '</span>';
    }, $content);
}

/**
 * 行内标签
 *
 *   [badge]NEW[/badge]
 *   [badge green]稳定[/badge]
 *   [badge text="HOT" color="red" style="solid"]
 */
function ecod_sc_badge(string $content): string
{
    return ecod_sc_inline('badge', '(?:\s+[^\]]*)?', static function (string $attrRaw, string $inner): string {
        $attrs = ecod_sc_attrs($attrRaw);

        $text = trim(preg_replace('#</?p>#i', '', $inner) ?? $inner);
        if ($text === '') {
            $text = htmlspecialchars(trim((string) ($attrs['text'] ?? '')), ENT_QUOTES, 'UTF-8');
        }
        if ($text === '') {
            return '';
        }

        $style = strtolower(trim((string) ($attrs['style'] ?? 'soft')));
        $style = in_array($style, ['solid', 'outline'], true) ? ' is-' . $style : '';

        $color = trim((string) ($attrs['color'] ?? ($attrs['type'] ?? '')));
        $colorStyle = $color !== ''
            ? ' style="--c:' . htmlspecialchars(ecod_color_value($color), ENT_QUOTES, 'UTF-8') . '"'
            : '';

        return '<span class="ecod_badge' . $style . '"' . $colorStyle . '>' . $text . '</span>';
    }, $content);
}

/**
 * 剧透遮罩：默认模糊，点击（或回车 / 空格）展开
 *
 *   [spoiler]剧透内容[/spoiler]
 *   [spoiler tip="点一下看剧透"]剧透内容[/spoiler]
 */
function ecod_sc_spoiler(string $content): string
{
    return ecod_sc_inline('spoiler', '(?:\s+[^\]]*)?', static function (string $attrRaw, string $inner): string {
        $attrs = ecod_sc_attrs($attrRaw);
        $body = trim(preg_replace('#</?p>#i', '', $inner) ?? $inner);
        if ($body === '') {
            return '';
        }

        $tip = trim((string) ($attrs['tip'] ?? ''));
        if ($tip === '') {
            $tip = '点击查看剧透';
        }
        $escTip = htmlspecialchars($tip, ENT_QUOTES, 'UTF-8');

        return '<span class="ecod_spoiler" role="button" tabindex="0" aria-expanded="false"'
            . ' title="' . $escTip . '" aria-label="' . $escTip . '" data-ecod-spoiler>'
            . '<span class="ecod_spoiler__body">' . $body . '</span>'
            . '</span>';
    }, $content);
}

/* ------------------------------------------------------------------
 * 执行管线
 * ---------------------------------------------------------------- */

/**
 * 扩展短代码总入口（由 ecod_shortcode_apply 调用）
 */
function ecod_sc_extra_block(string $content): string
{
    /* 块级 */
    $content = ecod_sc_alert($content);
    $content = ecod_sc_message($content);
    $content = ecod_sc_callout($content);
    $content = ecod_sc_anote($content);
    $content = ecod_sc_copy($content);
    $content = ecod_sc_cards($content);
    $content = ecod_sc_grid($content);
    $content = ecod_sc_card($content);
    $content = ecod_sc_cloud($content);
    $content = ecod_sc_music($content);
    $content = ecod_sc_gallery($content);
    $content = ecod_sc_abtn($content);

    /* 自闭合 */
    $content = ecod_sc_mtitle($content);
    $content = ecod_sc_progress($content);
    $content = ecod_sc_dotted($content);
    $content = ecod_sc_lamp($content);
    $content = ecod_sc_task($content);
    $content = ecod_sc_posts($content);
    $content = ecod_sc_article($content);
    /* [cat] / [tag]：站内链接卡片的手写入口（core/content/linkcard.php） */
    if (function_exists('ecod_sc_cat')) {
        $content = ecod_sc_cat($content);
        $content = ecod_sc_tag($content);
    }
    $content = ecod_sc_github($content);
    $content = ecod_sc_bilibili($content);
    $content = ecod_sc_video($content);
    $content = ecod_sc_mp3($content);

    /* 行内 */
    $content = ecod_sc_tooltip($content);
    $content = ecod_sc_badge($content);
    $content = ecod_sc_spoiler($content);
    $content = ecod_sc_emoji($content);

    /* 清理 autop 残留：空段落与「<p> 包裹单个块级组件」的非法嵌套 */
    $content = preg_replace('#<p>(?:\s|&nbsp;)*</p>#i', '', $content) ?? $content;
    $content = preg_replace(
        '#<p>\s*('
        . '<div class="ecod_(?:icard|msg|anote|mtitle|prog|acard|gh|embed|audio|posts|netdisk|playlist|gal)"[\s\S]*?</div>'
        . '|<a class="ecod_(?:icard|acard|gh|anote)"[\s\S]*?</a>'
        . '|<figure class="ecod_(?:video|gal)(?:[\s-][^"]*)?"[\s\S]*?</figure>'
        . ')\s*</p>#i',
        '$1',
        $content
    ) ?? $content;

    /* 卡片组 / 网格内：autop 的 <br> 行分隔会变成多余网格项，清掉组件之间的 br */
    $content = preg_replace(
        '#(</(?:div|a|figure)>)\s*<br\s*/?>\s*(?=<div class="ecod_icard|<a class="ecod_icard|</div>)#i',
        '$1' . "\n",
        $content
    ) ?? $content;
    $content = preg_replace(
        '#(<div class="ecod_cards__list">|<div class="ecod_grid"[^>]*>)\s*<br\s*/?>\s*#i',
        '$1',
        $content
    ) ?? $content;

    /* 卡片组 / 网格内逐行书写时，autop 会在组件之间留下 </p> 与 <p>，这里成对收掉 */
    $componentDiv = '<div class="ecod_(?:icard|cards|grid|acard|gh|embed|audio|prog|mtitle|msg|anote|copybox|callout|note|replybox|playlist|netdisk|dotted|video|gal)\b';
    for ($i = 0; $i < 2; $i++) {
        $content = preg_replace('#</p>\s*<p>\s*(?=' . $componentDiv . ')#i', '', $content) ?? $content;
        $content = preg_replace('#</p>\s*(?=' . $componentDiv . ')#i', '', $content) ?? $content;
        $content = preg_replace('#(</(?:div|a|figure)>)\s*<p>\s*(?=' . $componentDiv . ')#i', '$1', $content) ?? $content;
    }

    return $content;
}
