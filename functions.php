<?php
/**
 * Ecod Theme · 主题核心函数与配置
 *
 * @package Ecod Theme
 * @author  Ecod
 * @version 1.0.0
 * @link    https://example.com
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/* 表结构定义（点赞表 / contents.views），模板与接口共用 */
require_once __DIR__ . '/core/schema.php';

/* SEO 元信息、结构化数据与 sitemap */
require_once __DIR__ . '/core/seo.php';

/* 正文短代码 */
require_once __DIR__ . '/core/shortcode.php';

/* 扩展短代码（内容卡 / 网盘 / 媒体 / 表情） */
require_once __DIR__ . '/core/shortcode-extra.php';

/* 正文 oEmbed 自动嵌入（YouTube / B 站 / X 等地址自动转嵌入块） */
require_once __DIR__ . '/core/oembed.php';

/* 主题配置快照 */
require_once __DIR__ . '/core/snapshot.php';

/* 相关文章相关度计算 */
require_once __DIR__ . '/core/related.php';

/* 服务端缩略图裁剪 */
require_once __DIR__ . '/core/thumbs.php';

/* AI 智能摘要（后台 AI 助手配置接入后生效） */
require_once __DIR__ . '/core/ai.php';

/* 搜索增强（分词 / 命中片段 / 空结果建议） */
require_once __DIR__ . '/core/search.php';

/* 特色页一键创建 */
require_once __DIR__ . '/core/pages.php';

/* 外链中转（go 跳转加载页） */
require_once __DIR__ . '/core/go.php';

/* 导航菜单（后台配置的一级 / 二级菜单，含旧版自动导航回退） */
require_once __DIR__ . '/core/nav.php';

/* 付费与会员可见：门槛 / 权益 / 交易三层分离（见 docs/paywall-design.md） */
require_once __DIR__ . '/core/pay.php';
require_once __DIR__ . '/core/pay-channel.php';

/* ------------------------------------------------------------------
 * 后台配置项
 * ---------------------------------------------------------------- */
function themeConfig($form)
{
    /* 具体字段与设置页界面在 core/config.php */
    require_once __DIR__ . '/core/config.php';
}
/* ------------------------------------------------------------------
 * 文章自定义字段
 * ---------------------------------------------------------------- */
function themeFields($layout)
{
    $excerpt = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'excerpt',
        null,
        '',
        _t('自定义摘要'),
        _t('填写后列表、搜索结果与 SEO 描述优先使用这段文字；留空则按「阅读」分组里设置的字符数自动截取正文')
    );
    $layout->addItem($excerpt);

    $thumb = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'thumb',
        null,
        '',
        _t('自定义缩略图'),
        _t('每行一个图片地址，第一张作为封面，最多读取三张')
    );
    $layout->addItem($thumb);

    $mode = new \Typecho\Widget\Helper\Form\Element\Select(
        'mode',
        [
            ''         => _t('跟随主题默认'),
            'default'  => _t('列表'),
            'feature'  => _t('大图'),
            'gallery'  => _t('三图'),
            'bare'     => _t('无图'),
            'grid'     => _t('网格'),
            'title'    => _t('标题式'),
        ],
        '',
        _t('卡片模式'),
        _t('只影响这篇在列表里的样式；选「跟随主题默认」时用主题设置里的默认模式')
    );
    $layout->addItem($mode);

    $pin = new \Typecho\Widget\Helper\Form\Element\Text(
        'pin',
        null,
        '',
        _t('置顶标记'),
        _t('任意非空值即显示「置顶」角标')
    );
    $layout->addItem($pin);

    $series = new \Typecho\Widget\Helper\Form\Element\Text(
        'series',
        null,
        '',
        _t('系列名称'),
        _t('填写后显示「系列」角标')
    );
    $layout->addItem($series);

    $side = new \Typecho\Widget\Helper\Form\Element\Select(
        'side',
        [
            ''      => _t('跟随主题设置'),
            'left'  => _t('封面在左'),
            'right' => _t('封面在右'),
        ],
        '',
        _t('封面位置'),
        _t('仅「列表」「大图」两种卡片模式生效；跟随主题设置时使用主题选项里的默认值')
    );
    $layout->addItem($side);

    /* ---- 付费 / 会员可见 ---- */
    $payMode = new \Typecho\Widget\Helper\Form\Element\Select(
        'pay_mode',
        [
            ''         => _t('免费（不启用）'),
            'vip'      => _t('仅会员可读'),
            'paid'     => _t('仅单篇购买'),
            'paid_vip' => _t('可购买，也可会员免费'),
        ],
        '',
        _t('付费模式'),
        _t('选「仅会员可读」时不单卖；「仅单篇购买」时会员也要购买；会员价对「可购买」的两种模式生效')
    );
    $layout->addItem($payMode);

    $payPrice = new \Typecho\Widget\Helper\Form\Element\Text(
        'pay_price',
        null,
        '',
        _t('单篇价格（元）'),
        _t('例如 9.90。留空或 0 视为没有单篇购买入口；会员价必须低于这个价格才生效')
    );
    $layout->addItem($payPrice);

    $payLevel = new \Typecho\Widget\Helper\Form\Element\Select(
        'pay_level',
        [
            '1' => _t('会员'),
            '2' => _t('超级会员'),
        ],
        '1',
        _t('会员门槛'),
        _t('「仅会员可读」与「可购买，也可会员免费」时生效：达到该等级即可阅读')
    );
    $layout->addItem($payLevel);

    $payVip1 = new \Typecho\Widget\Helper\Form\Element\Text(
        'pay_vip1',
        null,
        '',
        _t('会员价（元）'),
        _t('会员购买本篇文章的价格；留空 = 无优惠，0 = 会员免费')
    );
    $layout->addItem($payVip1);

    $payVip2 = new \Typecho\Widget\Helper\Form\Element\Text(
        'pay_vip2',
        null,
        '',
        _t('超级会员价（元）'),
        _t('超级会员购买本篇文章的价格；留空 = 无优惠，0 = 免费')
    );
    $layout->addItem($payVip2);

    $payDays = new \Typecho\Widget\Helper\Form\Element\Text(
        'pay_days',
        null,
        '',
        _t('购买有效期（天）'),
        _t('留空或 0 = 永久可读；填 365 则一年后重新锁定')
    );
    $layout->addItem($payDays);

    $payDoc = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'pay_doc',
        null,
        '',
        _t('购买说明'),
        _t('显示在付费卡与列表摘要里；留空使用「付费内容，购买后可阅读全文。」')
    );
    $layout->addItem($payDoc);
}

/* ------------------------------------------------------------------
 * 请求初始化：数据结构自愈（建表 / 加列 / 迁移旧数据）
 * ---------------------------------------------------------------- */
function themeInit($archive = null)
{
    ecod_schema_upgrade();
    ecod_sitemap_maybe_output();

    /* 付费内容的 Feed / 第三方消费方兜底。
       文章页正文与描述在 themeInit 之前已被 singleHandle 算好，钩子对文章页无效，
       真正的门禁在 post.php（见 docs/paywall-design.md §2.2），描述用 ecod_pay_describe 覆盖。 */
    if (ecod_pay_enabled()) {
        \Typecho\Plugin::factory('Widget\Base\Contents')->contentEx = 'ecod_pay_feed_guard';
        \Typecho\Plugin::factory('Widget\Base\Contents')->excerptEx = 'ecod_pay_excerpt_guard';
        ecod_pay_describe($archive);
    }
}

/* ------------------------------------------------------------------
 * 工具函数
 * ---------------------------------------------------------------- */
function ecod_option(string $key, $default = '')
{
    try {
        $options = \Utils\Helper::options();
    } catch (\Throwable $e) {
        return $default;
    }
    return isset($options->{$key}) && $options->{$key} !== '' ? $options->{$key} : $default;
}

/* ------------------------------------------------------------------
 * 站点身份：配色 / 图标 / 头像源
 * ---------------------------------------------------------------- */

/**
 * 规范化十六进制颜色
 *
 * 接受 #abc / abc / #aabbcc / aabbcc，非法值一律回退，
 * 避免后台填错一个字符导致整站配色变成透明。
 */
function ecod_color(string $hex, string $fallback = '#4f6ef2'): string
{
    $hex = trim($hex);
    if (preg_match('/^#?([0-9a-f])([0-9a-f])([0-9a-f])$/i', $hex, $m)) {
        return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
    }
    if (preg_match('/^#?([0-9a-f]{6})$/i', $hex, $m)) {
        return '#' . strtolower($m[1]);
    }
    return $fallback;
}

/**
 * 两色按比例混合（$ratio = 0 取 $hex，1 取 $with）
 */
function ecod_color_mix(string $hex, string $with, float $ratio): string
{
    $hex = ecod_color($hex);
    $with = ecod_color($with, '#ffffff');
    $ratio = max(0.0, min(1.0, $ratio));

    $out = '#';
    for ($i = 1; $i <= 5; $i += 2) {
        $a = hexdec(substr($hex, $i, 2));
        $b = hexdec(substr($with, $i, 2));
        $out .= str_pad(dechex((int) round($a + ($b - $a) * $ratio)), 2, '0', STR_PAD_LEFT);
    }
    return $out;
}

/** 主题主色（后台可改，未设置时用设计默认值） */
function ecod_primary(): string
{
    return ecod_color((string) ecod_option('EPrimary', ''), '#4f6ef2');
}

/** 主色对应的浅色底 / 深色字，供 CSS 派生变量使用 */
function ecod_primary_soft(): string
{
    return ecod_color_mix(ecod_primary(), '#ffffff', 0.92);
}

function ecod_primary_deep(): string
{
    return ecod_color_mix(ecod_primary(), '#000000', 0.16);
}

/**
 * 头像源前缀
 *
 * Typecho 支持 __TYPECHO_GRAVATAR_PREFIX__ 常量覆盖内置 gravatar 域名。
 * 优先级：自定义头像源（互动 → 头像源） > 评论 → 头像服务预设 > 默认 cravatar.cn。
 * gravatar.com 国内无法直连，挂起的头像请求会让浏览器标签页一直转圈。
 */
function ecod_avatar_prefix(): string
{
    $custom = trim((string) ecod_option('EAvatar', ''));
    $preset = (string) ecod_option('EAvatarPreset', 'auto');

    $presets = [
        'cravatar' => 'https://cravatar.cn/avatar/',
        'weavatar' => 'https://weavatar.com/avatar/',
        'gravatar' => 'https://s.gravatar.com/avatar/',
    ];

    if ($preset === 'custom') {
        $prefix = $custom;
    } elseif (isset($presets[$preset])) {
        $prefix = $presets[$preset];
    } else {
        /* auto：填了自定义源就用自定义，否则 Cravatar */
        $prefix = $custom !== '' ? $custom : $presets['cravatar'];
    }

    if ($prefix === '') {
        $prefix = 'https://cravatar.cn/avatar/';
    }
    if (!preg_match('#^https?://#i', $prefix)) {
        $prefix = 'https://' . ltrim($prefix, '/');
    }
    if (substr($prefix, -1) !== '/') {
        $prefix .= '/';
    }
    return $prefix;
}

if (!defined('__TYPECHO_GRAVATAR_PREFIX__')) {
    $ecodAvatarPrefix = ecod_avatar_prefix();
    if ($ecodAvatarPrefix !== '') {
        define('__TYPECHO_GRAVATAR_PREFIX__', $ecodAvatarPrefix);
    }
}

/**
 * 个人中心页面地址
 *
 * 找已发布、模板为 page-user.php 的页面；没有则返回空串，
 * 头部用户按钮据此决定是否展示「个人中心」入口。
 */
function ecod_user_center_url(): string
{
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('slug')->from('table.contents')
                ->where('type = ?', 'page')
                ->where('status = ?', 'publish')
                ->where('template = ?', 'page-user.php')
                ->order('cid', \Typecho\Db::SORT_ASC)
                ->limit(1)
        );
        if (!$row) {
            return '';
        }
        return (string) \Typecho\Router::url('page', ['slug' => $row['slug']], \Widget\Options::alloc()->index);
    } catch (\Throwable $e) {
        return '';
    }
}

/**
 * 当前登录用户头像地址：自定义头像优先，未设置走后台配置的头像源（Gravatar）
 */
function ecod_user_avatar(object $user, int $size = 80): string
{
    require_once __DIR__ . '/core/account.php';
    $custom = ecod_account_avatar_url((int) $user->uid);
    if ($custom !== '') {
        return $custom;
    }
    try {
        return \Typecho\Common::gravatarUrl(
            (string) $user->mail,
            $size,
            'X',
            null,
            \Typecho\Request::getInstance()->isSecure()
        );
    } catch (\Throwable $e) {
        return '';
    }
}

/**
 * 登录页面地址
 *
 * 找已发布、模板为 page-login.php 的页面；没有则回落后台登录页。
 */
function ecod_login_url(): string
{
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('slug')->from('table.contents')
                ->where('type = ?', 'page')
                ->where('status = ?', 'publish')
                ->where('template = ?', 'page-login.php')
                ->order('cid', \Typecho\Db::SORT_ASC)
                ->limit(1)
        );
        if ($row) {
            return (string) \Typecho\Router::url('page', ['slug' => $row['slug']], \Widget\Options::alloc()->index);
        }
    } catch (\Throwable $e) {
    }
    try {
        return rtrim((string) \Widget\Options::alloc()->adminUrl, '/') . '/login.php';
    } catch (\Throwable $e) {
        return '/admin/login.php';
    }
}

/**
 * 导航条目
 *
 * 优先级：后台「导航」分组里配置的菜单（core/nav.php，支持二级）→ 旧逻辑回退
 * （全部独立页面 + 自定义链接「标题 || 地址 || 新窗口」）。
 * 返回结构统一，模板只负责渲染，不再关心来源。
 *
 * @param \Widget\Contents\Page\Rows|null $pages 已 to() 的页面集合
 * @return array<int, array<string, mixed>>
 */
function ecod_nav_items($pages = null): array
{
    /* 先把页面集合读成索引：菜单解析与旧逻辑回退都用它，避免 widget 被消费两次 */
    $pageMap = [];
    if ($pages) {
        try {
            while ($pages->next()) {
                $slug = (string) $pages->slug;
                $pageMap[] = [
                    'title' => (string) $pages->title,
                    'url'   => (string) $pages->permalink,
                    'slug'  => $slug,
                ];
            }
        } catch (\Throwable $e) {
        }
    }

    $bySlug = [];
    foreach ($pageMap as $row) {
        if ($row['slug'] !== '') {
            $bySlug[$row['slug']] = $row;
        }
    }

    /* 后台菜单配过东西就完全接管 */
    $menu = ecod_nav_menu_resolve($bySlug);
    if ($menu) {
        return $menu;
    }

    $items = [];

    /* 导航开关：后台「工具 → 特色页」里关掉的页面不进导航，页面本身仍可访问 */
    $hidden = array_filter(array_map('trim', explode(',', (string) ecod_option('ENavHide', ''))));

    foreach ($pageMap as $row) {
        if ($row['slug'] !== '' && in_array($row['slug'], $hidden, true)) {
            continue;
        }
        $items[] = [
            'title'    => $row['title'],
            'url'      => $row['url'],
            'slug'     => $row['slug'],
            'kind'     => 'page',
            'ref'      => $row['slug'],
            'blank'    => false,
            'children' => [],
        ];
    }

    $raw = trim((string) ecod_option('ENavLinks', ''));
    if ($raw !== '') {
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '||') === false) {
                continue;
            }
            $parts = array_map('trim', explode('||', $line));
            $title = $parts[0] ?? '';
            $url = $parts[1] ?? '';
            if ($title === '' || $url === '') {
                continue;
            }
            $flag = strtolower($parts[2] ?? '');
            $items[] = [
                'title'    => $title,
                'url'      => $url,
                'slug'     => '',
                'kind'     => 'link',
                'ref'      => '',
                'blank'    => in_array($flag, ['1', '_blank', 'blank', 'new'], true),
                'children' => [],
            ];
        }
    }

    return $items;
}

/**
 * 导航链接的附加属性
 *
 * 统一输出 target / title / class，模板里只写 echo，不再各自拼串。
 * attr 与 cls 来自后台菜单项编辑面板，已在 core/nav.php 归一化时清理过。
 *
 * @param array<string, mixed> $item
 */
function ecod_nav_link_attrs(array $item): string
{
    $attrs = '';

    $attr = (string) ($item['attr'] ?? '');
    if ($attr !== '') {
        $attrs .= ' title="' . htmlspecialchars($attr, ENT_QUOTES, 'UTF-8') . '"';
    }

    $cls = (string) ($item['cls'] ?? '');
    if ($cls !== '') {
        $attrs .= ' class="' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '"';
    }

    if (!empty($item['blank'])) {
        $attrs .= ' target="_blank" rel="noopener"';
    }

    return $attrs;
}

/**
 * 是否为主题自身的 PJAX 局部渲染请求
 *
 * 前端无刷新跳转带 X-Requested-With: Ecod-PJAX，此时 header/footer
 * 只回传 #ecod-shell 段，跳过整页 chrome，换页流量大幅下降。
 */
function ecod_is_pjax(): bool
{
    return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'Ecod-PJAX';
}

/**
 * 当前页对应的导航高亮地址
 *
 * 全量渲染与 PJAX 局部渲染共用：地址随 shell 的 data-ecod-nav-active 输出，
 * 前端换页后拿它与导航链接比对，同步 is-active（导航栏在 shell 之外，不会被替换）。
 * 命中二级项时返回二级项的地址，前端据此同时点亮父项。
 */
function ecod_nav_active($archive = null, array $items = []): string
{
    try {
        if ($archive && $archive->is('index')) {
            return (string) \Widget\Options::alloc()->siteUrl;
        }
    } catch (\Throwable $e) {
    }

    if (!$archive) {
        return '';
    }

    foreach ($items as $item) {
        if (ecod_nav_item_is_active($archive, $item)) {
            return (string) $item['url'];
        }
        foreach (($item['children'] ?? []) as $child) {
            if (ecod_nav_item_is_active($archive, $child)) {
                return (string) $child['url'];
            }
        }
    }

    return '';
}

/**
 * 主题内静态资源地址
 *
 * 自动附带文件修改时间作为版本号，避免改样式后浏览器仍读缓存。
 */
function ecod_asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = __DIR__ . '/' . $path;
    $ver = is_file($file) ? (string) filemtime($file) : '';
    $suffix = $ver !== '' ? '?v=' . $ver : '';

    $cdn = trim((string) ecod_option('ECdn', ''));
    if ($cdn !== '' && preg_match('#^https?://#i', $cdn)) {
        return rtrim($cdn, '/') . '/' . $path . $suffix;
    }

    try {
        $options = \Utils\Helper::options();
        $base = rtrim((string) $options->themeUrl, '/');
    } catch (\Throwable $e) {
        $base = '/usr/themes/ecod-theme';
    }

    return $base . '/' . $path . $suffix;
}

/**
 * 提取文章图片（自定义缩略图优先，其次正文图片）
 */
function ecod_thumbnails($archive, int $limit = 3, string $preset = ''): array
{
    /* 同一篇文章在同一页出现多次（正文列表 + 侧栏/热门）时取同一组图 */
    static $memo = [];
    $cid = (int) ($archive->cid ?? 0);
    $memoKey = $cid . ':' . $limit . ':' . $preset;
    if ($cid > 0 && isset($memo[$memoKey])) {
        return $memo[$memoKey];
    }

    $result = [];
    $fields = $archive->fields;
    $thumb = $fields && isset($fields->thumb) ? (string) $fields->thumb : '';
    if ($thumb) {
        foreach (preg_split('/\r\n|\r|\n/', $thumb) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $result[] = $line;
            }
        }
    }

    if (count($result) < $limit && empty($archive->hidden) && !empty($archive->content)) {
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $archive->content, $m)) {
            $result = array_merge($result, $m[1]);
        }
    }

    $result = array_values(array_unique(array_filter($result)));

    if (count($result) < $limit) {
        $pool = trim((string) ecod_option('EThumbnail', ''));
        if ($pool !== '') {
            $items = array_values(array_unique(array_filter(array_map(
                'trim',
                preg_split('/\r\n|\r|\n/', $pool)
            ))));
            if ($items) {
                $result = array_merge($result, ecod_pool_pick($items, $limit - count($result), $archive, $result));
            }
        }
    }

    $result = array_slice($result, 0, $limit);

    /* 服务端裁剪：preset 传入且功能可用时，本站图换成裁剪代理地址 */
    if ($preset !== '' && function_exists('ecod_thumb_url')) {
        foreach ($result as $i => $u) {
            $result[$i] = ecod_thumb_url($u, $preset);
        }
    }

    if ($cid > 0) {
        $memo[$memoKey] = $result;
    }

    return $result;
}

/**
 * 从默认图池取图
 *
 * 两条约束：同一篇文章每次刷新取到的图固定（按 cid+标题做种子），
 * 同一页内不同文章尽量不撞图（请求级轮转记录）。
 *
 * @param string[] $items   图池
 * @param int      $need    还差几张
 * @param mixed    $archive 当前文章
 * @param string[] $exclude 本文已有的图（正文图 / 自定义缩略图）
 */
function ecod_pool_pick(array $items, int $need, $archive, array $exclude = []): array
{
    if ($need <= 0 || empty($items)) {
        return [];
    }

    /* 请求内每张图被用了几次：优先给没出现过的，池子不够时才轮到用得最少的 */
    static $counts = [];

    $n = count($items);
    $cid = (int) ($archive->cid ?? 0);
    $seed = $n > 0
        ? abs((int) (crc32($cid . '|' . (string) ($archive->title ?? '')) % $n))
        : 0;

    /* 从种子位置开始轮转，排序键：使用次数 → 轮转顺序 */
    $order = [];
    for ($i = 0; $i < $n; $i++) {
        $url = $items[($seed + $i) % $n];
        $order[] = ['url' => $url, 'pos' => $i, 'use' => (int) ($counts[$url] ?? 0)];
    }
    usort($order, function (array $a, array $b): int {
        if ($a['use'] !== $b['use']) {
            return $a['use'] <=> $b['use'];
        }
        return $a['pos'] <=> $b['pos'];
    });

    $picked = [];
    foreach ($order as $row) {
        if (count($picked) >= $need) {
            break;
        }
        if (in_array($row['url'], $exclude, true) || in_array($row['url'], $picked, true)) {
            continue;
        }
        $picked[] = $row['url'];
        $counts[$row['url']] = (int) ($counts[$row['url']] ?? 0) + 1;
    }

    return $picked;
}

/**
 * 纯文本摘要
 */
function ecod_excerpt($archive, ?int $length = null): string
{
    if (!empty($archive->hidden)) {
        return '此内容受密码保护，输入密码后可查看。';
    }

    /* 付费内容：列表 / 搜索 / meta description / OG 一律不参与摘要 */
    if (function_exists('ecod_pay_access')) {
        try {
            $payState = ecod_pay_access($archive);
            if ($payState['mode'] !== 'free' && !$payState['allow']) {
                return ecod_pay_excerpt_text($archive);
            }
        } catch (\Throwable $e) {
            /* 判定失败时按免费处理，避免整站列表报错 */
        }
    }

    /* 文章里手填的摘要优先：列表、搜索结果、SEO 描述都走这里 */
    try {
        $fields = $archive->fields ?? null;
        if ($fields && isset($fields->excerpt)) {
            $manual = trim((string) $fields->excerpt);
            if ($manual !== '') {
                return $manual;
            }
        }
    } catch (\Throwable $e) {
        /* 取不到自定义字段就退回自动截取 */
    }

    $length = $length ?: (int) ecod_option('EExcerpt', 110);
    $text = strip_tags((string) $archive->content);
    $text = function_exists('ecod_shortcode_strip') ? ecod_shortcode_strip($text) : $text;
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = trim($text);
    return mb_substr($text, 0, $length, 'UTF-8') . (mb_strlen($text, 'UTF-8') > $length ? '…' : '');
}

/**
 * 给正文图片做真正的懒加载
 *
 * 原生 loading="lazy" 对没有 width/height 的图片基本不生效（浏览器为避免布局
 * 偏移会立即加载）。这里把真实地址挪进 data-src，src 换成 1px 透明占位，
 * 由前端脚本在图片接近视口时才发起请求；noscript 兜底保证禁 JS 时仍能显示。
 * 顺带补全无障碍与解码提示：无 alt 的图用文章标题兜底，缺 decoding 的补 async。
 */
function ecod_lazy_images(string $html, string $title = ''): string
{
    if ($html === '' || stripos($html, '<img') === false) {
        return $html;
    }

    if (ecod_option('ELazy', 'on') === 'off') {
        return $html;
    }

    $title = trim($title);
    $placeholder = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    return preg_replace_callback('/<img\b[^>]*>/i', static function (array $m) use ($placeholder, $title): string {
        $tag = $m[0];

        /* 已处理过或没有 src 的不动 */
        if (stripos($tag, 'data-src=') !== false) {
            return $tag;
        }
        if (!preg_match('/\ssrc=(["\'])(.+?)\1/i', $tag, $s)) {
            return $tag;
        }

        $src = trim($s[2]);
        /* data URI（表情等内联图）不懒加载，但仍补 alt/decoding */
        if ($src !== '' && stripos($src, 'data:') !== 0) {
            /* 禁 JS 兜底：原图原样输出，脚本可用时浏览器会忽略 noscript */
            $noscript = '<noscript>' . $tag . '</noscript>';

            $lazy = str_ireplace($s[0], ' data-src="' . $src . '"', $tag);
            $lazy = (string) preg_replace('/<img\b/i', '<img src="' . $placeholder . '" data-ecod-img', $lazy, 1);
        } else {
            $noscript = '';
            $lazy = $tag;
        }

        /* 无障碍：无 alt / 空 alt 的图用文章标题兜底 */
        if (!preg_match('/\salt=(["\'])(.*?)\1/i', $tag) && $title !== '') {
            $lazy = (string) preg_replace('/<img\b/i', '<img alt="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '"', $lazy, 1);
        }
        /* 解码提示：主线程不阻塞 */
        if (stripos($lazy, 'decoding=') === false) {
            $lazy = (string) preg_replace('/<img\b/i', '<img decoding="async"', $lazy, 1);
        }

        return $noscript . $lazy;
    }, $html) ?? $html;
}

/**
 * 搜索结果关键词高亮
 *
 * 输入纯文本、输出已转义的 HTML，调用方不要再套 htmlspecialchars()。
 */
function ecod_highlight(string $text, string $keyword): string
{
    $keyword = trim($keyword);
    if ($keyword === '') {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $kw = preg_quote(htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8'), '/');

    return preg_replace('/(' . $kw . ')/iu', '<mark>$1</mark>', $escaped) ?? $escaped;
}

/**
 * 阅读量
 *
 * 数据存在 contents.views：列表页随主查询一并取出，不产生额外 SQL；
 * 单篇首次访问执行 `views = views + 1`，并按 cookie 记录已读 cid 去重。
 *
 * @param \Widget\Base\Contents $archive
 * @param bool                  $increment 是否计数（仅文章页）
 */
function ecod_views($archive, bool $increment = false): int
{
    $cid = (int) ($archive->cid ?? 0);
    if ($cid <= 0) {
        return 0;
    }

    $views = ecod_views_value($archive, $cid);

    /* 关闭统计后不再累加，已记录的数值仍照常显示 */
    if (!$increment || ecod_option('EViews', 'on') === 'off' || !ecod_views_should_count($archive)) {
        return $views;
    }

    $views = ecod_views_bump($cid, $views);
    ecod_views_mark_seen($cid);

    return $views;
}

/**
 * contents.views 是否可用（结构升级失败时降级为 0，不拖垮页面查询）
 */
function ecod_views_available(): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }

    require_once __DIR__ . '/core/schema.php';

    /* 结构版本已升级 => 列一定存在，省掉一次探测查询 */
    if (ecod_schema_version() >= 1) {
        return $ok = true;
    }

    try {
        $db = \Typecho\Db::get();
        $ok = ecod_schema_has_column($db, $db->getPrefix() . 'contents', 'views');
    } catch (\Throwable $e) {
        $ok = false;
    }

    return $ok;
}

/**
 * 取阅读量：优先用行数据（零查询），否则查请求内缓存 / 单条查询
 */
function ecod_views_value($archive, int $cid): int
{
    $raw = isset($archive->views) ? $archive->views : null;
    if ($raw !== null && is_numeric($raw)) {
        return ecod_views_seed($cid, (int) $raw);
    }

    $cache = &ecod_views_cache();
    if (array_key_exists($cid, $cache)) {
        return $cache[$cid];
    }

    if (!ecod_views_available()) {
        return ecod_views_seed($cid, 0);
    }

    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('views')->from('table.contents')->where('cid = ?', $cid)->limit(1)
        );
        return ecod_views_seed($cid, $row ? (int) $row['views'] : 0);
    } catch (\Throwable $e) {
        return ecod_views_seed($cid, 0);
    }
}

function ecod_views_seed(int $cid, int $views): int
{
    $cache = &ecod_views_cache();
    $cache[$cid] = $views;
    return $views;
}

function &ecod_views_cache(): array
{
    if (!isset($GLOBALS['ecodViewsCache']) || !is_array($GLOBALS['ecodViewsCache'])) {
        $GLOBALS['ecodViewsCache'] = [];
    }
    return $GLOBALS['ecodViewsCache'];
}

/**
 * 原子自增，避免「先读后写」丢计数；失败时静默保持原值
 */
function ecod_views_bump(int $cid, int $current): int
{
    static $broken = false;
    if ($broken || !ecod_views_available()) {
        return $current;
    }

    try {
        $db = \Typecho\Db::get();
        $db->query('UPDATE ' . $db->getPrefix() . 'contents SET views = views + 1 WHERE cid = ' . $cid);
        return ecod_views_seed($cid, $current + 1);
    } catch (\Throwable $e) {
        $broken = true;
        return $current;
    }
}

/**
 * 是否计一次阅读：GET、非密码文章、cookie 里没记过
 */
function ecod_views_should_count($archive): bool
{
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return false;
    }
    if (!empty($archive->hidden)) {
        return false;
    }
    return !ecod_views_seen((int) ($archive->cid ?? 0));
}

/** cookie 里保存最近 24 篇已读 cid，避免刷新 / 爬虫刷量 */
function ecod_views_seen(int $cid): bool
{
    if ($cid <= 0) {
        return false;
    }
    $raw = (string) ($_COOKIE['ecod_seen'] ?? '');
    if ($raw === '') {
        return false;
    }
    return in_array($cid, array_map('intval', explode('.', $raw)), true);
}

function ecod_views_mark_seen(int $cid): void
{
    if ($cid <= 0) {
        return;
    }

    $raw = (string) ($_COOKIE['ecod_seen'] ?? '');
    $list = $raw === '' ? [] : array_map('intval', explode('.', $raw));
    $list[] = $cid;
    $list = array_slice(array_values(array_unique(array_filter($list))), -24);
    $value = implode('.', $list);
    $_COOKIE['ecod_seen'] = $value;

    if (!headers_sent()) {
        setcookie('ecod_seen', $value, [
            'expires'  => time() + 30 * 86400,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

/**
 * 内容是否设置了访问密码
 *
 * 不能直接用 $archive->hidden：那个属性表示「当前访客此刻是否能看正文」，
 * 管理员/编辑登录后恒为 false，列表里就完全看不出这篇文章是加密的。
 * 这里只看 password 字段本身，任何身份下都能正确标记。
 */
function ecod_has_password($archive = null): bool
{
    if (!$archive) {
        return false;
    }

    return strlen(trim((string) ($archive->password ?? ''))) > 0;
}

/**
 * 小尺寸锁标记（供列表行内使用）
 */
function ecod_lock_icon(int $size = 11, string $cls = 'ecod_lock-i'): string
{
    return '<span class="' . $cls . '">' . ecod_icon('lock', $size) . '</span>';
}

/**
 * 文章角标
 */
function ecod_badges($archive): string
{
    $badges = [];
    $ecodNewDays = (int) ecod_option('ENewDays', 7);
    $fields = $archive->fields;
    $pin = $fields && isset($fields->pin) ? $fields->pin : '';
    $series = $fields && isset($fields->series) ? $fields->series : '';

    if (!empty($pin)) {
        $badges[] = '<span class="ecod_badge ecod_badge--top">置顶</span>';
    }
    if (!empty($series)) {
        $badges[] = '<span class="ecod_badge ecod_badge--vip">系列</span>';
    }
    if (empty($pin) && $ecodNewDays > 0 && (time() - (int) $archive->created) < $ecodNewDays * 86400) {
        $badges[] = '<span class="ecod_badge ecod_badge--new">新</span>';
    }
    if (ecod_has_password($archive)) {
        array_unshift($badges, '<span class="ecod_badge ecod_badge--lock" title="需要密码访问">' . ecod_icon('lock', 10) . '密码</span>');
    }

    /* 付费 / 会员角标优先级最高，插到最前 */
    if (function_exists('ecod_pay_badge')) {
        $payBadge = ecod_pay_badge($archive);
        if ($payBadge !== '') {
            array_unshift($badges, $payBadge);
        }
    }

    /* 容器最多三个，避免窄屏卡片角标挤成两行 */
    $badges = array_slice($badges, 0, 3);

    return $badges ? '<div class="ecod_card__badges">' . implode('', $badges) . '</div>' : '';
}

/**
 * 缩略图位置解析：left | right
 *
 * 优先取文章自定义字段 side，其次取主题设置 EThumbSide，默认右图。
 */
function ecod_thumb_side($archive = null): string
{
    $fields = $archive ? ($archive->fields ?? null) : null;
    $side = $fields && isset($fields->side) ? strtolower(trim((string) $fields->side)) : '';
    if ($side === 'left' || $side === 'right') {
        return $side;
    }

    return strtolower((string) ecod_option('EThumbSide', 'right')) === 'left' ? 'left' : 'right';
}

/**
 * 卡片模式解析
 */
function ecod_card_mode($archive): string
{
    $allowed = ['default', 'feature', 'gallery', 'bare', 'grid', 'title', 'compact', 'mosaic', 'item'];
    $fields = $archive->fields;
    $mode = $fields && isset($fields->mode) ? (string) $fields->mode : '';
    if (in_array($mode, $allowed, true)) {
        return $mode;
    }
    $default = ecod_option('ECardMode', 'default');
    return in_array($default, $allowed, true) ? $default : 'default';
}

/**
 * 列表卡片模式：容器与卡片必须同源，否则「网格容器 + 横向卡」会被压扁
 *
 * 版式由列表决定，整列统一，不再理会文章自定义字段 mode：
 * - 版式参数明确指定（grid / feature / bare …）时用该版式
 * - 版式为「跟随文章设置」时回落到主题默认卡片模式 ECardMode
 *
 * @param string|null $layout 列表版式参数
 * @return string 需要传给 ecod_card() 的模式
 */
function ecod_list_force_mode(?string $layout = null): string
{
    $layout = (string) $layout;
    if ($layout !== '' && $layout !== 'default') {
        return $layout;
    }

    $mode = (string) ecod_option('ECardMode', 'default');
    return $mode !== '' ? $mode : 'default';
}

/**
 * 列表容器类名：与卡片模式保持一致（网格 / 标题式需要容器配合） *
 * @param string|null $mode 不传则取主题默认卡片模式
 */
function ecod_list_class(?string $mode = null): string
{
    $mode = $mode ?: (string) ecod_option('ECardMode', 'default');
    if ($mode === 'grid') {
        return 'ecod_list ecod_list--grid';
    }
    if ($mode === 'title') {
        return 'ecod_list ecod_list--title';
    }
    if ($mode === 'compact') {
        return 'ecod_list ecod_list--compact';
    }
    if ($mode === 'mosaic') {
        return 'ecod_list ecod_list--mosaic';
    }
    if ($mode === 'item') {
        return 'ecod_list ecod_list--item';
    }
    return 'ecod_list';
}

/**
 * 相对时间
 */
function ecod_relative_time(int $timestamp): string
{
    $diff = time() - $timestamp;
    if ($diff < 60) {
        return '刚刚';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' 分钟前';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' 小时前';
    }
    if ($diff < 86400 * 30) {
        return floor($diff / 86400) . ' 天前';
    }
    /* 格式交给 intl（跟随站点时区与地区），主机未装 intl 时回退 */
    $tz = date_default_timezone_get();
    $date = new \DateTime('@' . $timestamp);
    $date->setTimezone(new \DateTimeZone($tz));
    if (class_exists('\IntlDateFormatter')) {
        static $fmt = null;
        if ($fmt === null) {
            $fmt = new \IntlDateFormatter('zh_CN', \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, $tz, \IntlDateFormatter::GREGORIAN, 'MM-dd');
        }
        $out = $fmt->format($date);
        if (is_string($out) && $out !== '') {
            return $out;
        }
    }
    return $date->format('m-d');
}

/**
 * 置顶文章 cid（fields.pin 非空）
 */
function ecod_pinned_cids(int $limit = 3): array
{
    try {
        $db = \Typecho\Db::get();
        $rows = $db->fetchAll(
            $db->select('cid')->from('table.fields')
                ->where('name = ?', 'pin')->where('str_value <> ?', '')
                ->limit($limit)
        );
        return array_map(static fn($r) => (int) $r['cid'], $rows);
    } catch (\Throwable $e) {
        return [];
    }
}

/**
 * 站点概览数据（同一请求内复用，首页 search + overview 两块只跑一次）
 */
function ecod_site_overview(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $data = ['posts' => 0, 'comments' => 0, 'tags' => 0, 'days' => 1, 'views' => 0];
    try {
        $db = \Typecho\Db::get();
        $data['posts'] = (int) $db->fetchObject(
            $db->select('COUNT(*) AS num')->from('table.contents')
                ->where('type = ?', 'post')->where('status = ?', 'publish')
        )->num;
        $data['comments'] = (int) $db->fetchObject(
            $db->select('COUNT(*) AS num')->from('table.comments')->where('status = ?', 'approved')
        )->num;
        $data['tags'] = (int) $db->fetchObject(
            $db->select('COUNT(*) AS num')->from('table.metas')->where('type = ?', 'tag')
        )->num;
        $first = $db->fetchObject(
            $db->select('MIN(created) AS first')->from('table.contents')->where('type = ?', 'post')
        );
        if ($first && (int) $first->first > 0) {
            $data['days'] = max(1, (int) ceil((time() - (int) $first->first) / 86400));
        }
        /* 后台填了建站日期就以它为准：迁移站点的首篇文章时间往往比真实建站晚 */
        $birth = trim((string) ecod_option('EBirth', ''));
        if ($birth !== '') {
            $birthTs = strtotime($birth);
            if ($birthTs && $birthTs <= time()) {
                $data['days'] = max(1, (int) ceil((time() - $birthTs) / 86400));
            }
        }
        if (ecod_views_available()) {
            $views = $db->fetchObject(
                $db->select('SUM(views) AS total')->from('table.contents')->where('type = ?', 'post')
            );
            $data['views'] = $views ? (int) ($views->total ?? 0) : 0;
        }
    } catch (\Throwable $e) {
        // 静默降级
    }

    return $cached = $data;
}

/**
 * 接口地址（点赞等前端请求，取相对路径保证任意域名同源可用）
 */
function ecod_api_url(): string
{
    try {
        $options = \Utils\Helper::options();
        $path = parse_url(rtrim((string) $options->themeUrl, '/'), PHP_URL_PATH) ?: '';
        return $path . '/api.php';
    } catch (\Throwable $e) {
        return '/usr/themes/ecod-theme/api.php';
    }
}

/**
 * 数字缩写：千位以上显示为 k（如 1k、1.2k、123k）
 */
function ecod_num_short(int $n): string
{
    if ($n < 1000) {
        return (string) $n;
    }
    $v = $n / 1000;
    $v = $v >= 100 ? round($v) : round($v * 10) / 10;
    return rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.') . 'k';
}

/**
 * 文章点赞数（单篇，带请求内缓存）
 */
function ecod_likes(int $cid): int
{
    $cache = &ecod_likes_cache();
    if (array_key_exists($cid, $cache)) {
        return $cache[$cid];
    }
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchObject(
            $db->select('COUNT(*) AS num')->from(ecod_likes_table())->where('cid = ?', $cid)
        );
        return $cache[$cid] = $row ? (int) $row->num : 0;
    } catch (\Throwable $e) {
        return $cache[$cid] = 0;
    }
}

/**
 * 点赞缓存引用（批量预取与单篇读取共用）
 */
function &ecod_likes_cache(): array
{
    if (!isset($GLOBALS['ecodLikesCache']) || !is_array($GLOBALS['ecodLikesCache'])) {
        $GLOBALS['ecodLikesCache'] = [];
    }
    return $GLOBALS['ecodLikesCache'];
}

/**
 * 批量预取点赞数：一次 IN 查询填满缓存，避免列表页 N+1
 *
 * @param array<int> $cids
 */
function ecod_likes_preload(array $cids): void
{
    $cache = &ecod_likes_cache();
    $need = [];
    foreach ($cids as $cid) {
        $cid = (int) $cid;
        if ($cid > 0 && !array_key_exists($cid, $cache)) {
            $need[$cid] = $cid;
        }
    }
    if (!$need) {
        return;
    }

    $map = array_fill_keys($need, 0);
    try {
        $db = \Typecho\Db::get();
        $in = implode(',', array_map('intval', array_values($need)));
        $rows = $db->fetchAll(
            $db->select('cid', 'COUNT(*) AS num')->from(ecod_likes_table())
                ->where('cid IN (' . $in . ')')
                ->group('cid')
        );
        foreach ($rows as $row) {
            $map[(int) $row['cid']] = (int) $row['num'];
        }
    } catch (\Throwable $e) {
        // 表尚未建立时按 0 计
    }

    foreach ($map as $cid => $num) {
        $cache[(int) $cid] = (int) $num;
    }
}

/**
 * 取出当前列表组件里所有行的 cid（模板迭代前一次性拿到，用于批量预取）
 *
 * @return array<int>
 */
function ecod_archive_cids($archive): array
{
    if (!is_object($archive)) {
        return [];
    }

    try {
        /* $stack 是 protected，用闭包绑到对象作用域读取 */
        $rows = (function () {
            return $this->stack ?? [];
        })->call($archive);
    } catch (\Throwable $e) {
        return [];
    }

    if (!is_array($rows)) {
        return [];
    }

    $cids = [];
    foreach ($rows as $row) {
        if (!empty($row['cid'])) {
            $cids[] = (int) $row['cid'];
        }
    }

    return $cids;
}

/**
 * 卡片渲染前的批量预取：同一列表只跑一次，
 * 阅读量直接用行内值（零查询），点赞数一次 IN 查询。
 */
function ecod_cards_preload($archive): void
{
    if (!is_object($archive)) {
        return;
    }

    /* 关闭卡片点赞时不必查点赞表 */
    if (ecod_option('ELike', 'on') === 'off') {
        return;
    }

    $id = spl_object_id($archive);
    if (!empty($GLOBALS['ecodPreloaded'][$id])) {
        return;
    }
    $GLOBALS['ecodPreloaded'][$id] = true;

    $cids = ecod_archive_cids($archive);
    if (!$cids) {
        return;
    }

    ecod_likes_preload($cids);
}

/**
 * 全站字数（去标签、去空白后按字符计）
 *
 * 全量统计，不再截断前 500 篇；为避免每次访问都扫全表，
 * 用「文章数 + 修改时间之和」做指纹，内容没变就直接用缓存值。
 * 分批取 text，防止超长文章一次载入占满内存。
 */
function ecod_words_total(?\Typecho\Db $db = null): int
{
    static $memo = null;
    if ($memo !== null) {
        return $memo;
    }

    try {
        $db = $db ?: \Typecho\Db::get();

        $sig = $db->fetchObject(
            $db->select('COUNT(*) AS num', 'SUM(modified) AS sig')
                ->from('table.contents')
                ->where('type = ?', 'post')->where('status = ?', 'publish')
        );
        $posts = $sig ? (int) $sig->num : 0;
        /* 用 SUM 而不是 MAX：改早期文章时也要能失效 */
        $stamp = $sig ? (int) $sig->sig : 0;

        $cache = ecod_cache_get('EcodWords', []);
        if (
            is_array($cache)
            && isset($cache['posts'], $cache['sig'], $cache['words'])
            && (int) $cache['posts'] === $posts
            && (int) $cache['sig'] === $stamp
        ) {
            return $memo = (int) $cache['words'];
        }

        $words = 0;
        $batch = 200;
        $offset = 0;
        while (true) {
            $rows = $db->fetchAll(
                $db->select('text')->from('table.contents')
                    ->where('type = ?', 'post')->where('status = ?', 'publish')
                    ->order('cid', \Typecho\Db::SORT_ASC)
                    ->offset($offset)->limit($batch)
            );
            if (empty($rows)) {
                break;
            }
            foreach ($rows as $row) {
                $text = preg_replace('/\s+/u', '', strip_tags((string) $row['text']));
                $words += mb_strlen($text, 'UTF-8');
            }
            $offset += $batch;
            if (count($rows) < $batch) {
                break;
            }
        }

        ecod_cache_set('EcodWords', ['posts' => $posts, 'sig' => $stamp, 'words' => $words]);
        return $memo = $words;
    } catch (\Throwable $e) {
        return $memo = 0;
    }
}

/**
 * 站点聚合数据（统计页 / 概览用）
 */
function ecod_site_stats(): array
{
    $stats = [
        'posts'    => 0,
        'pages'    => 0,
        'comments' => 0,
        'tags'     => 0,
        'cats'     => 0,
        'views'    => 0,
        'likes'    => 0,
        'words'    => 0,
        'days'     => 1,
    ];

    try {
        $db = \Typecho\Db::get();

        $stats['posts'] = (int) $db->fetchObject(
            $db->select('COUNT(*) AS num')->from('table.contents')
                ->where('type = ?', 'post')->where('status = ?', 'publish')
        )->num;
        $stats['pages'] = (int) $db->fetchObject(
            $db->select('COUNT(*) AS num')->from('table.contents')
                ->where('type = ?', 'page')->where('status = ?', 'publish')
        )->num;
        $stats['comments'] = (int) $db->fetchObject(
            $db->select('COUNT(*) AS num')->from('table.comments')->where('status = ?', 'approved')
        )->num;
        $stats['tags'] = (int) $db->fetchObject(
            $db->select('COUNT(*) AS num')->from('table.metas')->where('type = ?', 'tag')
        )->num;
        $stats['cats'] = (int) $db->fetchObject(
            $db->select('COUNT(*) AS num')->from('table.metas')->where('type = ?', 'category')
        )->num;

        if (ecod_views_available()) {
            $views = $db->fetchObject(
                $db->select('SUM(views) AS total')->from('table.contents')->where('type = ?', 'post')
            );
            $stats['views'] = $views ? (int) ($views->total ?? 0) : 0;
        }

        $likes = $db->fetchObject(
            $db->select('COUNT(*) AS num')->from(ecod_likes_table())
        );
        $stats['likes'] = $likes ? (int) $likes->num : 0;

        $first = $db->fetchObject(
            $db->select('MIN(created) AS first')->from('table.contents')->where('type = ?', 'post')
        );
        if ($first && (int) $first->first > 0) {
            $stats['days'] = max(1, (int) ceil((time() - (int) $first->first) / 86400));
        }

        /* 全站字数（去标签后估算，见 ecod_words_total） */
        $stats['words'] = ecod_words_total($db);
    } catch (\Throwable $e) {
        // 静默降级
    }

    return $stats;
}

/* ------------------------------------------------------------------
 * 评论服务端校验
 *
 * 前端已有蜜罐、字数、链接数、提交间隔检查，但直接 POST 可以全部绕过。
 * Typecho 的 Feedback 组件在写库前会走 `comment` 过滤器，这里挂上去做同样的事：
 * 蜜罐有值、正文过短、链接过多、提交过快（签名时间戳令牌）一律拒绝。
 * ---------------------------------------------------------------- */
function ecod_comment_secret(): string
{
    $secret = (string) ecod_option('secret', '');
    if ($secret === '') {
        $secret = (string) ecod_option('siteUrl', 'ecod');
    }
    return $secret;
}

/**
 * 表单令牌：时间戳 + HMAC，无需 session
 */
function ecod_comment_token(): string
{
    $ts = (string) time();
    return $ts . '.' . substr(hash_hmac('sha256', $ts, ecod_comment_secret()), 0, 16);
}

/**
 * 令牌年龄（秒）；令牌缺失或签名不符返回 null，表示跳过速度判断
 */
function ecod_comment_token_age(string $token): ?int
{
    if ($token === '' || strpos($token, '.') === false) {
        return null;
    }
    [$ts, $sig] = explode('.', $token, 2);
    if (!ctype_digit($ts) || strlen($sig) !== 16) {
        return null;
    }
    $expect = substr(hash_hmac('sha256', $ts, ecod_comment_secret()), 0, 16);
    if (!hash_equals($expect, $sig)) {
        return null;
    }
    $age = time() - (int) $ts;
    /* 有效期两天，过期只当作拿不到年龄（页面被缓存等情况） */
    if ($age < 0 || $age > 172800) {
        return null;
    }
    return $age;
}

/* ------------------------------------------------------------------
 * 评论外部服务：表情 CDN / 统计 / 第三方评论配置 / Turnstile / Akismet
 * ---------------------------------------------------------------- */

/**
 * 表情图片地址
 *
 * 后台填了「表情包 CDN 前缀」就走外部 CDN，否则用主题本地图片。
 */
function ecod_smiley_url(string $file): string
{
    $cdn = trim((string) ecod_option('EEmojiCdn', ''));
    if ($cdn !== '') {
        if (!preg_match('#^https?://#i', $cdn)) {
            $cdn = 'https://' . ltrim($cdn, '/');
        }
        if (substr($cdn, -1) !== '/') {
            $cdn .= '/';
        }
        return $cdn . rawurlencode($file);
    }

    return function_exists('ecod_asset')
        ? ecod_asset('assets/img/smiley/' . $file)
        : '/usr/themes/ecod-theme/assets/img/smiley/' . $file;
}

/**
 * 深层回复的展开方式：manual（逐条）/ button（顶部总按钮）/ all（不折叠）
 *
 * 折叠深度与展开方式拆成两个设置：深度管「从第几层开始收」，
 * 方式管「收起来之后怎么铺开」，这样长讨论不会把页面拉成一长条。
 */
function ecod_comment_expand_mode(): string
{
    static $mode = null;

    if ($mode === null) {
        $mode = (string) ecod_option('ECommentExpandMode', 'manual');
        if (!in_array($mode, ['manual', 'button', 'all'], true)) {
            $mode = 'manual';
        }
    }

    return $mode;
}

/**
 * 评论统计：已通过评论总数 + 每个评论的后代数量
 *
 * Typecho 的 commentsNum / total 只统计顶层，嵌套后标题上的数字会偏小；
 * 这里一次查出整篇文章的 parent 关系，向上累加得到每个节点的后代数，
 * 折叠按钮的「展开 N 条回复」也用它。
 *
 * @return array{total:int, desc:array<int,int>}
 */
function ecod_comment_stats(int $cid): array
{
    static $cache = [];

    if ($cid <= 0) {
        return ['total' => 0, 'desc' => []];
    }
    if (isset($cache[$cid])) {
        return $cache[$cid];
    }

    $stats = ['total' => 0, 'desc' => []];
    try {
        $db = \Typecho\Db::get();
        $rows = $db->fetchAll(
            $db->select('coid', 'parent')->from('table.comments')
                ->where('cid = ?', $cid)
                ->where('status = ?', 'approved')
        );

        $parentOf = [];
        foreach ($rows as $row) {
            $parentOf[(int) $row['coid']] = (int) $row['parent'];
        }
        $stats['total'] = count($parentOf);

        /* 每往上走一层就给祖先累加一次，父子链异常时用 $guard 兜底 */
        foreach ($parentOf as $parent) {
            $guard = 0;
            while ($parent > 0 && isset($parentOf[$parent]) && $guard++ < 60) {
                $stats['desc'][$parent] = (int) ($stats['desc'][$parent] ?? 0) + 1;
                $parent = $parentOf[$parent];
            }
        }
    } catch (\Throwable $e) {
    }

    return $cache[$cid] = $stats;
}

/**
 * 极简 POST（curl 优先，退回 stream），只用于服务端调用外部接口
 */
function ecod_http_post(string $url, array $fields, int $timeout = 8): ?string
{
    $body = http_build_query($fields, '', '&', PHP_QUERY_RFC1738);
    $ua = 'EcodTheme/1.0 (+' . (string) ecod_option('siteUrl', '') . ')';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT      => $ua,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $out = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return ($out !== false && $code >= 200 && $code < 300) ? (string) $out : null;
    }

    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/x-www-form-urlencoded\r\nUser-Agent: " . $ua . "\r\n",
        'content'       => $body,
        'timeout'       => $timeout,
        'ignore_errors' => true,
    ]]);
    $out = @file_get_contents($url, false, $ctx);

    return $out === false ? null : (string) $out;
}

/**
 * Cloudflare Turnstile 校验
 *
 * 接口不通时放行：验证服务抖动不该让整站评不了论。
 */
function ecod_turnstile_ok(string $secret, string $token, string $ip = ''): bool
{
    if ($secret === '' || $token === '') {
        return false;
    }

    $fields = ['secret' => $secret, 'response' => $token];
    if ($ip !== '') {
        $fields['remoteip'] = $ip;
    }

    $res = ecod_http_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', $fields);
    if ($res === null) {
        return true;
    }

    $json = json_decode($res, true);

    return is_array($json) && !empty($json['success']);
}

/**
 * Akismet 垃圾判定
 *
 * 返回 true 表示判定为垃圾。接口异常一律放行。
 */
function ecod_akismet_spam(string $key, array $comment): bool
{
    $key = preg_replace('/[^a-z0-9]/i', '', $key);
    if ($key === '') {
        return false;
    }

    try {
        $request = \Typecho\Request::getInstance();
    } catch (\Throwable $e) {
        return false;
    }

    $fields = [
        'blog'                  => (string) ecod_option('siteUrl', ''),
        'user_ip'               => (string) $request->getIp(),
        'user_agent'            => (string) $request->getServer('HTTP_USER_AGENT'),
        'referrer'              => (string) $request->getServer('HTTP_REFERER'),
        'comment_type'          => 'comment',
        'comment_author'        => (string) ($comment['author'] ?? ''),
        'comment_author_email'  => (string) ($comment['mail'] ?? ''),
        'comment_author_url'    => (string) ($comment['url'] ?? ''),
        'comment_content'       => strip_tags((string) ($comment['text'] ?? '')),
        'blog_lang'             => 'zh_cn',
        'blog_charset'          => 'UTF-8',
    ];

    $res = ecod_http_post('https://' . $key . '.rest.akismet.com/1.1/comment-check', $fields, 6);

    return $res !== null && strtolower(trim($res)) === 'true';
}

/**
 * 评论前端配置（输出到 window.ECOD.comments）
 */
function ecod_comments_config(): array
{
    $provider = (string) ecod_option('ECommentProvider', 'native');
    if (!in_array($provider, ['twikoo', 'waline', 'artalk', 'giscus'], true)) {
        $provider = 'native';
    }

    $emoji = ecod_option('EEmojiStyle', 'unicode') === 'smiley' ? 'smiley' : 'unicode';
    $cfg = [
        'provider'  => $provider,
        'emoji'     => $emoji,
        'smiles'    => [],
        'turnstile' => '',
    ];

    if ($emoji === 'smiley' && function_exists('ecod_emoji_map')) {
        foreach (ecod_emoji_map() as $code => $item) {
            $file = (string) ($item[0] ?? '');
            if ($file === '') {
                continue;
            }
            $cfg['smiles'][] = [
                'code'  => (string) $code,
                'label' => (string) ($item[1] ?? ''),
                'src'   => ecod_smiley_url($file),
            ];
        }
    }

    if ($provider === 'native') {
        if (ecod_option('ETurnstile', 'off') === 'on') {
            $cfg['turnstile'] = trim((string) ecod_option('ETurnstileSite', ''));
        }
        return $cfg;
    }

    $cfg['third'] = ['type' => $provider];
    switch ($provider) {
        case 'twikoo':
            $cfg['third']['env'] = trim((string) ecod_option('ECommentTwikoo', ''));
            break;
        case 'waline':
            $cfg['third']['server'] = rtrim(trim((string) ecod_option('ECommentWaline', '')), '/');
            break;
        case 'artalk':
            $cfg['third']['server'] = rtrim(trim((string) ecod_option('ECommentArtalk', '')), '/');
            break;
        case 'giscus':
            $cfg['third'] = [
                'type'       => 'giscus',
                'repo'       => trim((string) ecod_option('ECommentGiscusRepo', '')),
                'repoId'     => trim((string) ecod_option('ECommentGiscusRepoId', '')),
                'category'   => trim((string) ecod_option('ECommentGiscusCategory', '')),
                'categoryId' => trim((string) ecod_option('ECommentGiscusCategoryId', '')),
                'mapping'    => (string) ecod_option('ECommentGiscusMapping', 'pathname'),
            ];
            break;
    }

    return $cfg;
}

/**
 * Feedback:comment 过滤器
 *
 * @param array $comment 待写入的评论行
 * @param mixed $archive 当前文章（Typecho 会额外再传一次 $comment，忽略）
 * @return array
 * @throws \Typecho\Widget\Exception
 */
function ecod_comment_guard($comment, $archive = null)
{
    /* 登录用户（作者/编辑）不受限制 */
    try {
        if (\Widget\User::alloc()->hasLogin()) {
            return $comment;
        }
    } catch (\Throwable $e) {
        /* 取不到登录态就按访客处理 */
    }

    try {
        $request = \Typecho\Request::getInstance();
    } catch (\Throwable $e) {
        return $comment;
    }

    $hp = $request->get('ecod_hp');
    if (is_array($hp) || trim((string) $hp) !== '') {
        throw new \Typecho\Widget\Exception(_t('提交未通过校验，请刷新页面后重试。'), 403);
    }

    $text = isset($comment['text']) ? (string) $comment['text'] : '';
    $plain = trim(strip_tags($text));

    if (mb_strlen($plain, 'UTF-8') < 2) {
        throw new \Typecho\Widget\Exception(_t('评论内容太短了，至少写两个字。'), 403);
    }

    if (preg_match_all('#https?://|www\.#i', $text) > 3) {
        throw new \Typecho\Widget\Exception(_t('评论里的链接太多了，最多 3 个。'), 403);
    }

    $token = $request->get('ecod_ts');
    $age = ecod_comment_token_age(is_string($token) ? $token : '');
    if ($age !== null && $age < 2) {
        throw new \Typecho\Widget\Exception(_t('提交太快了，请确认内容后再试一次。'), 403);
    }

    /* ---- 后台可配的三条审核规则：命中转待审，不直接拒绝 ---- */

    /* 1. 敏感词：命中任意一个就进审核队列 */
    $sensitive = trim((string) ecod_option('ESensitive', ''));
    if ($sensitive !== '') {
        $words = preg_split('/\r\n|\r|\n|\|\|/u', $sensitive) ?: [];
        foreach ($words as $word) {
            $word = trim($word);
            if ($word !== '' && mb_stripos($plain, $word, 0, 'UTF-8') !== false) {
                $comment['status'] = 'waiting';
                break;
            }
        }
    }

    /* 2. 必须含中文：挡纯英文机器人广告 */
    if (ecod_option('ECnOnly', 'off') === 'on' && !preg_match('/\p{Han}/u', $plain)) {
        $comment['status'] = 'waiting';
    }

    /* 3. 字数上限：超长评论转审核 */
    $maxLen = (int) ecod_option('EMaxLen', 0);
    if ($maxLen > 0 && mb_strlen($plain, 'UTF-8') > $maxLen) {
        $comment['status'] = 'waiting';
    }

    /* ---- 外部服务：人机验证 & 反垃圾 ---- */

    /* 4. Cloudflare Turnstile：只作用于原生评论（第三方评论系统不经过这里） */
    if (ecod_option('ETurnstile', 'off') === 'on') {
        $secret = trim((string) ecod_option('ETurnstileSecret', ''));
        if ($secret !== '') {
            $token = $request->get('cf-turnstile-response');
            $ok = is_string($token) && $token !== ''
                && ecod_turnstile_ok($secret, $token, (string) $request->getIp());
            if (!$ok) {
                throw new \Typecho\Widget\Exception(_t('人机验证未通过，请刷新页面重试。'), 403);
            }
        }
    }

    /* 5. Akismet：判定为垃圾直接进 spam，不给访客报错 */
    if (ecod_option('EAkismet', 'off') === 'on' && ($comment['status'] ?? '') === 'approved') {
        if (ecod_akismet_spam((string) ecod_option('EAkismetKey', ''), $comment)) {
            $comment['status'] = 'spam';
        }
    }

    return $comment;
}

if (class_exists('\Typecho\Plugin')) {
    try {
        \Typecho\Plugin::factory('Widget\Feedback')->comment = 'ecod_comment_guard';
    } catch (\Throwable $e) {
        /* 注册失败只是少一层校验，不影响页面 */
    }
}

/* 评论邮件提醒（默认关闭，后台开启后才会真正连 SMTP） */
require_once __DIR__ . '/core/mail.php';

if (class_exists('\Typecho\Plugin')) {
    try {
        \Typecho\Plugin::factory('Widget\Feedback')->finishComment = 'ecod_mail_notify';
        \Typecho\Plugin::factory('Widget\Feedback')->finishComment = 'ecod_mail_notify_reply';
    } catch (\Throwable $e) {
        /* 注册失败只是收不到提醒 */
    }
}

/* 块（插件）系统 */
require_once __DIR__ . '/core/blocks.php';

/* 友链申请：落库、审核写回友链页 */
require_once __DIR__ . '/core/friends.php';

/* 特色页组件 */
require_once __DIR__ . '/components/media.php';

/* 载入组件 */
require_once __DIR__ . '/components/postquery.php';
require_once __DIR__ . '/components/icons.php';
require_once __DIR__ . '/components/card.php';

/* ------------------------------------------------------------------
 * 评论区：等级徽章 / @回复 / 自定义列表结构
 * ---------------------------------------------------------------- */

/**
 * 全站评论者活跃度表（一次查询，按邮箱聚合）
 *
 * 只取前 500 名，足够覆盖绝大多数站点的等级判定；
 * 页面内多次调用走静态缓存，不重复查库。
 *
 * @return array<string, int>
 */
function ecod_comment_level_map(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $map = [];
    try {
        $db = \Typecho\Db::get();
        $rows = $db->fetchAll(
            $db->select('mail', 'COUNT(*) AS n')->from('table.comments')
                ->where('status = ?', 'approved')
                ->group('mail')
                ->order('n', \Typecho\Db::SORT_DESC)
                ->limit(500)
        );
        foreach ($rows as $row) {
            $mail = strtolower(trim((string) $row['mail']));
            if ($mail !== '') {
                $map[$mail] = (int) $row['n'];
            }
        }
    } catch (\Throwable $e) {
    }

    return $map;
}

/**
 * 评论者等级：按全站已通过评论数分 7 档
 *
 * @return array{level: int, label: string, tone: string}
 */
function ecod_comment_level(string $mail): array
{
    $mail = strtolower(trim($mail));
    $count = $mail !== '' ? (int) (ecod_comment_level_map()[$mail] ?? 0) : 0;

    $steps = [160, 80, 40, 20, 10, 5, 1];
    $level = 0;
    foreach ($steps as $index => $need) {
        if ($count >= $need) {
            $level = 7 - $index;
            break;
        }
    }

    $labels = [
        1 => '初见',
        2 => '常来',
        3 => '熟客',
        4 => '老友',
        5 => '资深',
        6 => '元老',
        7 => '镇站',
    ];

    return [
        'level' => $level,
        'label' => $labels[$level] ?? '',
        'tone'  => 'lv' . max(1, $level),
        'count' => $count,
    ];
}

/**
 * 评论徽章 HTML（博主 / 等级）
 */
function ecod_comment_badge($comments): string
{
    $html = '';

    $authorId = (int) ($comments->authorId ?? 0);
    $ownerId = (int) ($comments->ownerId ?? 0);
    if ($authorId > 0 && $authorId === $ownerId) {
        return '<em class="ecod_cmt__badge is-owner">博主</em>';
    }

    $level = ecod_comment_level((string) ($comments->mail ?? ''));
    if ($level['level'] > 0) {
        $html = '<em class="ecod_cmt__badge ' . $level['tone'] . '" title="已发表 ' . (int) $level['count']
            . ' 条评论">' . htmlspecialchars($level['label'], ENT_QUOTES, 'UTF-8') . '</em>';
    }

    return $html;
}

/**
 * 父评论作者名（用于 @ 提示）
 */
function ecod_comment_parent_author(int $coid): string
{
    static $cache = [];
    if ($coid <= 0) {
        return '';
    }
    if (array_key_exists($coid, $cache)) {
        return $cache[$coid];
    }

    $cache[$coid] = '';
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('author')->from('table.comments')
                ->where('coid = ?', $coid)->limit(1)
        );
        if ($row && isset($row['author'])) {
            $cache[$coid] = (string) $row['author'];
        }
    } catch (\Throwable $e) {
    }

    return $cache[$coid];
}

if (!function_exists('threadedComments')) {
    /**
     * 评论列表单条渲染
     *
     * Typecho 的 listComments() 检测到这个函数就不再输出默认结构，
     * 因此这里同时承担「结构 + 等级 + @回复」三件事。
     *
     * @param \Widget\Comments\Archive $comments
     * @param mixed                    $options
     */
    function threadedComments($comments, $options = null)
    {
        $levels = (int) ($comments->levels ?? 0);
        $status = (string) ($comments->status ?? 'approved');
        $isOwner = (int) ($comments->authorId ?? 0) > 0
            && (int) ($comments->authorId ?? 0) === (int) ($comments->ownerId ?? 0);

        $classes = ['ecod_cmt'];
        if ($levels > 0) {
            $classes[] = 'is-child';
        }
        if ($isOwner) {
            $classes[] = 'is-owner';
        }
        if ($status !== 'approved') {
            $classes[] = 'is-pending';
        }
        ?>
        <li id="<?php $comments->theId(); ?>" class="<?php echo implode(' ', $classes); ?>"
            itemscope itemtype="http://schema.org/UserComments">
            <div class="ecod_cmt__aside" itemprop="creator" itemscope itemtype="http://schema.org/Person">
                <span itemprop="image"><?php $comments->gravatar(40); ?></span>
            </div>
            <div class="ecod_cmt__main">
                <div class="ecod_cmt__head">
                    <cite class="ecod_cmt__name" itemprop="name"><?php $comments->author(); ?></cite>
                    <?php echo ecod_comment_badge($comments); ?>
                    <a class="ecod_cmt__time" href="<?php $comments->permalink(); ?>" rel="nofollow">
                        <time itemprop="commentTime" datetime="<?php $comments->date('c'); ?>"><?php $comments->date('Y-m-d H:i'); ?></time>
                    </a>
                </div>

                <div class="ecod_cmt__body" itemprop="commentText">
                    <?php
                    $parentAuthor = ecod_comment_parent_author((int) ($comments->parent ?? 0));
                    if ($parentAuthor !== '') {
                        echo '<span class="ecod_cmt__at">@' . htmlspecialchars($parentAuthor, ENT_QUOTES, 'UTF-8') . '</span> ';
                    }
                    $comments->content();
                    ?>
                </div>

                <?php if ($status !== 'approved'): ?>
                    <em class="ecod_cmt__pending">等待审核</em>
                <?php endif; ?>

                <div class="ecod_cmt__act">
                    <?php $comments->reply('回复'); ?>
                    <a href="#<?php $comments->theId(); ?>" class="ecod_cmt__anchor" aria-label="复制本楼链接">#</a>
                </div>

                <?php
                $ecodKids = $comments->children;
                if ($ecodKids):
                    $ecodStats = ecod_comment_stats((int) ($comments->cid ?? 0));
                    $ecodDesc = (int) ($ecodStats['desc'][(int) $comments->coid] ?? 0);
                    $ecodFoldAt = max(2, min(5, (int) ecod_option('ECommentFold', 3)));
                    /* 前 N 层始终展开：父级已经在第 N 层时，子级收进折叠区 */
                    $ecodFold = $levels >= $ecodFoldAt - 1;
                    /* 「全部展开」模式下不产出折叠，长讨论会完整铺开 */
                    if (ecod_comment_expand_mode() === 'all') {
                        $ecodFold = false;
                    }
                    ?>
                    <?php if ($ecodFold): ?>
                        <button type="button" class="ecod_cmt__fold" data-ecod-fold data-more="<?php echo max(1, $ecodDesc ?: count($ecodKids)); ?>" aria-expanded="false">
                            <span data-ecod-fold-text>展开 <?php echo max(1, $ecodDesc ?: count($ecodKids)); ?> 条回复</span>
                        </button>
                    <?php endif; ?>
                    <div class="ecod_cmt__children<?php echo $ecodFold ? ' is-folded' : ''; ?>" itemprop="discusses"<?php echo $ecodFold ? ' hidden' : ''; ?>>
                        <?php $comments->threadedComments(); ?>
                    </div>
                <?php endif; ?>
            </div>
        </li>
        <?php
    }
}

/* ------------------------------------------------------------------
 * Feed（RSS/Atom）里的正文
 *
 * 订阅器拿到的 content 同样要过一遍短代码：
 *   1. 否则 [note]…[/note] 会以原文出现在订阅器里
 *   2. [reply] 的隐藏内容绝对不能进 feed，这里会替换成提示框
 * 模板层的接管仍在 post.php / page.php，二者按顺序执行是幂等的。
 * ---------------------------------------------------------------- */
if (!function_exists('ecod_feed_content')) {
    function ecod_feed_content(string $content, $widget = null): string
    {
        return ecod_shortcode_apply($content, $widget);
    }

    function ecod_feed_excerpt(string $excerpt, $widget = null): string
    {
        return ecod_shortcode_strip($excerpt);
    }
}

try {
    \Typecho\Plugin::factory('Widget\Base\Contents')->contentEx = 'ecod_feed_content';
    \Typecho\Plugin::factory('Widget\Base\Contents')->excerptEx = 'ecod_feed_excerpt';
} catch (\Throwable $e) {
    /* 注册失败不影响正常渲染，模板层会兜底 */
}
