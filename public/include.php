<?php
/**
 * 头部元信息与资源
 *
 * 顺序约定：主题样式 → 主色变量 → 自定义 CSS → 自定义 head 代码，
 * 保证后台填的样式总能覆盖主题默认值。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodPrimary = ecod_primary();
$ecodFavicon = trim((string) ecod_option('EFavicon', ''));
$ecodFont = trim((string) ecod_option('EFont', ''));
$ecodCss = trim((string) ecod_option('ECustomCss', ''));
$ecodHead = trim((string) ecod_option('ECustomHead', ''));
$ecodCode = ecod_option('ECode', 'off') === 'on';
$ecodCodeTheme = (string) ecod_option('ECodeTheme', 'prism');
$ecodCodeTheme = preg_replace('/[^a-z]/', '', $ecodCodeTheme) ?: 'prism';
$ecodLightbox = ecod_option('ELightbox', 'on') !== 'off';

/* 文章地址模板：把路由模式里的 cid 换成占位符，前端据 cid 拼链接 */
$ecodPostUrl = '';
try {
    $ecodRouting = (array) $this->options->routingTable;
    $ecodPostPattern = (string) ($ecodRouting['post']['url'] ?? '/archives/[cid:digital]/');
    $ecodPostPattern = preg_replace('/\[cid[^\]]*\]/', '{cid}', $ecodPostPattern);
    $ecodPostPattern = preg_replace('/\[[^\]]*\]/', '', (string) $ecodPostPattern);
    $ecodPostPattern = preg_replace('#(?<!:)/{2,}#', '/', (string) $ecodPostPattern);
    $ecodPostUrl = (string) \Typecho\Common::url((string) $ecodPostPattern, (string) $this->options->index);
} catch (\Throwable $e) {
    $ecodPostUrl = '';
}

/* 搜索地址模板：同理把 keywords 段换成 {q}。拿不到路由时退回 ?s=，
   前端两种形态都认（见 ecod.global.js 的 searchHref）。 */
$ecodSearchUrl = '';
try {
    $ecodSearchPattern = (string) ($ecodRouting['search']['url'] ?? '');
    if ($ecodSearchPattern !== '' && strpos($ecodSearchPattern, '[keywords') !== false) {
        $ecodSearchPattern = preg_replace('/\[keywords[^\]]*\]/', '{q}', $ecodSearchPattern);
        $ecodSearchPattern = preg_replace('/\[[^\]]*\]/', '', $ecodSearchPattern);
        $ecodSearchPattern = preg_replace('#(?<!:)/{2,}#', '/', $ecodSearchPattern);
        $ecodSearchUrl = (string) \Typecho\Common::url($ecodSearchPattern, (string) $this->options->index);
    }
} catch (\Throwable $e) {
    $ecodSearchUrl = '';
}
if ($ecodSearchUrl === '') {
    $ecodSearchUrl = rtrim((string) $this->options->siteUrl, '/') . '/?s={q}';
}
?>
<meta charset="<?php $this->options->charset(); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="renderer" content="webkit">
<meta name="theme-color" content="#f5f6f9">
<link rel="manifest" href="<?php echo ecod_asset('manifest.php'); ?>">
<?php if ($ecodFavicon !== ''): ?>
<link rel="icon" href="<?php echo htmlspecialchars($ecodFavicon, ENT_QUOTES, 'UTF-8'); ?>">
<link rel="apple-touch-icon" href="<?php echo htmlspecialchars($ecodFavicon, ENT_QUOTES, 'UTF-8'); ?>">
<?php else: ?>
<?php
/* 没设图标时用主色 + 站名首字生成一张，至少配色跟着主题走 */
$ecodMark = mb_substr(trim((string) $this->options->title), 0, 1, 'UTF-8') ?: 'E';
$ecodSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
    . '<rect width="64" height="64" rx="14" fill="' . $ecodPrimary . '"/>'
    . '<text x="32" y="44" font-size="32" font-weight="700" font-family="sans-serif" fill="#fff" text-anchor="middle">'
    . htmlspecialchars($ecodMark, ENT_QUOTES, 'UTF-8') . '</text></svg>';
?>
<link rel="icon" href="data:image/svg+xml,<?php echo rawurlencode($ecodSvg); ?>">
<?php endif; ?>
<title><?php echo htmlspecialchars(ecod_seo_title($this), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></title>
<script>
/* 首帧主题：读取本地偏好，避免深色模式闪烁 */
(function () {
    try {
        var m = localStorage.getItem('ecod-theme');
        if (location.search.indexOf('ecod-dark=1') > -1) {
            m = 'dark';
        } else if (!m) {
            var p = document.documentElement.getAttribute('data-dark') || 'auto';
            m = p === 'auto'
                ? (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                : 'light';
        }
        document.documentElement.setAttribute('data-theme', m);
        /* theme-color 与页面底色一致，暗色下不能仍是浅色主色 */
        var meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', m === 'dark' ? '#14161d' : '#f5f6f9');
        }
    } catch (e) {}
})();
</script>
<script>
/* 主题运行参数 */
<?php
/* AI 实付单价：必须在这里现算（会员免单时是 0）。
   注意 ecod_viewer_uid() 属于 user/meta 模块，header 阶段不一定已加载，
   所以自己取一次登录态，取不到按游客算。 */
$ecodAiUid = 0;
try {
    $ecodAiUser = \Widget\User::alloc();
    if ($ecodAiUser->hasLogin()) {
        $ecodAiUid = (int) $ecodAiUser->uid;
    }
} catch (\Throwable $e) {
}
$ecodAiPrice = function_exists('ecod_ai_cost')
    ? ecod_ai_cost($ecodAiUid)
    : ['cost' => max(0, (int) ecod_option('ECrAICost', 1)), 'free' => false];

/* 站点背景 = 流光（Lightfall）：前端参数。未选中该项时 enabled = false，
   页脚据此不下发引擎与封装（省两个请求）；颜色只收 #rgb / #rrggbb，
   非法值直接丢弃，前端再用主题主色系兜底 */
$ecodBackdropMode = (string) ecod_option('EBackdrop', 'none');
$ecodLightColors = [];
$ecodLightOn = $ecodBackdropMode === 'lightfall';
if ($ecodLightOn) {
    $ecodLightRaw = (string) ecod_option('EBgLightColors', '#4f6ef2,#8b5cf6');
    foreach (preg_split('/[,\s]+/', $ecodLightRaw) as $ecodLightOne) {
        $ecodLightOne = trim($ecodLightOne);
        if ($ecodLightOne !== '' && preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $ecodLightOne)) {
            $ecodLightColors[] = $ecodLightOne;
        }
        if (count($ecodLightColors) >= 3) {
            break;
        }
    }
}
$ecodLightfall = [
    'enabled' => $ecodLightOn,
    'colors'  => $ecodLightColors,
    'speed'   => max(0.05, min(2, (float) ecod_option('EBgLightSpeed', 0.3))),
    'layers'  => max(1, min(8, (int) ecod_option('EBgLightLayers', 2))),
    'mouse'   => ecod_option('EBgLightMouse', 'on') === 'on',
    'opacity' => max(0.1, min(1, (int) ecod_option('EBgLightOpacity', 70) / 100)),
];
?>
window.ECOD = {
    version: <?php echo json_encode(ECOD_VERSION); ?>,
    api: <?php echo json_encode(ecod_api_url(), JSON_UNESCAPED_SLASHES); ?>,
    postUrl: <?php echo json_encode($ecodPostUrl, JSON_UNESCAPED_SLASHES); ?>,
    /* 内容页增强包（ecod.post.js）：页脚按模板条件输出，PJAX 换页时 JS 据此动态注入。
       prismUrl / qrUrl 供注入链使用：正文页才加载 prism.js（库较大），
       二维码库只服务分享卡；配色 CSS 体积小、需全站在 head 常驻，见下方说明 */
    post: <?php echo json_encode(ecod_asset('assets/js/ecod.post.js'), JSON_UNESCAPED_SLASHES); ?>,
    /* 页面族 CSS 地址映射（S3）：head 已按当前页输出 core+所属族，
       PJAX 跨族换页时 ensurePageCss() 据此注入缺失族；未构建时为空对象 */
    fams: <?php
        $ecodFamMap = [];
        foreach (['core', 'home', 'list', 'post', 'postext', 'user', 'page'] as $ecodF) {
            if (ecod_dist_resolve('assets/css/ecod.fam.' . $ecodF . '.css') !== null) {
                $ecodFamMap[$ecodF] = ecod_asset('assets/css/ecod.fam.' . $ecodF . '.css');
            }
        }
        echo json_encode($ecodFamMap, JSON_UNESCAPED_SLASHES);
    ?>,
    prismUrl: <?php echo $ecodCode ? json_encode(ecod_asset('assets/vendor/prism/prism.js'), JSON_UNESCAPED_SLASHES) : "''"; ?>,
    qrUrl: <?php echo json_encode(ecod_asset('assets/vendor/ecod-qr.js'), JSON_UNESCAPED_SLASHES); ?>,
    /* 表情球（Grok Ball，MIT）：引擎 + 封装一律按需加载（AI 对话页 / 404 /
       抽奖页 / 搜索面板 / 站宠），reduced-motion 与移动端不发引擎。
       mascot = 站宠总开关（后台「互动 → 积分与 AI → 表情球站宠」） */
    ball: <?php echo json_encode(ecod_asset('assets/js/ecod.ball.js'), JSON_UNESCAPED_SLASHES); ?>,
    ballEngine: <?php echo json_encode(ecod_asset('assets/js/vendor/grok-ball.js'), JSON_UNESCAPED_SLASHES); ?>,
    mascot: <?php echo ecod_option('EBall', 'off') === 'on' ? 'true' : 'false'; ?>,
    /* AI 快问面板（全站浮层，见 docs/ai-design.html §4.1）
       aiAsk = 面板可用；aiToken 与 ai_chat 的 CSRF 校验同源；aiChips 服务端渲染，
       避免首帧空白；aiStream 关掉时前端退回一次性 JSON 渲染 */
    aiAsk: <?php echo ecod_ai_ask_enabled() ? 'true' : 'false'; ?>,
    aiToken: <?php echo json_encode(ecod_account_token()); ?>,
    /* 实付单价：会员免单时是 0（与 api 的 ecod_ai_cost() 同源，别在这里另算） */
    aiCost: <?php echo json_encode((int) $ecodAiPrice['cost']); ?>,
    aiFree: <?php echo $ecodAiPrice['free'] ? 'true' : 'false'; ?>,
    aiStream: <?php echo ecod_option('EAIStream', 'on') === 'on' ? 'true' : 'false'; ?>,
    aiChips: <?php echo json_encode(ecod_ai_chips(), JSON_UNESCAPED_UNICODE); ?>,
    aiPage: <?php echo json_encode(ecod_template_page_url('page-ai.php'), JSON_UNESCAPED_SLASHES); ?>,
    /* AI 搜索（F9）：搜索面板尾部「问问 AI」条目是否出现 */
    aiSearch: <?php echo ecod_ai_enabled() && ecod_option('EAISearch', 'on') === 'on' ? 'true' : 'false'; ?>,
    credits: <?php echo json_encode(ecod_credits_name(), JSON_UNESCAPED_UNICODE); ?>,
    pjax: <?php echo ecod_option('EPjax', 'on') === 'on' ? 'true' : 'false'; ?>,
    /* 列表加载模式：auto = 触底自动加载；click = 点击「加载更多」；pager = 数字分页 */
    listLoad: <?php echo json_encode(ecod_option('EListLoad', 'auto')); ?>,
    /* auto 模式连续自动加载页数上限，超过后转手动「加载更多」；0 = 不限制 */
    listAutoMax: <?php echo json_encode((int) ecod_option('EListAutoMax', 4)); ?>,
    lightbox: <?php echo $ecodLightbox ? 'true' : 'false'; ?>,
    prism: <?php echo $ecodCode ? 'true' : 'false'; ?>,
    codeFold: <?php echo ecod_option('ECodeFold', 'on') === 'on' ? 'true' : 'false'; ?>,
    /* 图表 / 公式：按需加载的本地渲染库地址，空串 = 后台关闭（post 包内判空跳过） */
    mermaidUrl: <?php echo ecod_option('EMermaid', 'on') === 'on' ? json_encode(ecod_asset('assets/vendor/mermaid/mermaid.min.js'), JSON_UNESCAPED_SLASHES) : "''"; ?>,
    katexBase: <?php echo ecod_option('EMath', 'on') === 'on' ? json_encode(ecod_asset('assets/vendor/katex/'), JSON_UNESCAPED_SLASHES) : "''"; ?>,
    ctxmenu: <?php echo ecod_option('ECtxMenu', 'on') === 'on' ? 'true' : 'false'; ?>,
    ctxitems: <?php echo json_encode(ecod_ctx_items(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    tocProgress: <?php echo ecod_option('ETocProgress', 'on') === 'on' ? 'true' : 'false'; ?>,
    /* AI 摘要打字机回放（EAIMotion）；卡片 hover 动效总开关供 CSS 属性选择器使用 */
    aiMotion: <?php echo ecod_option('EAIMotion', 'off') === 'on' ? 'true' : 'false'; ?>,
    tocEntry: <?php echo json_encode(ecod_option('ETocEntry', 'float')); ?>,
    /* 流光背景：站点背景选「流光」时 enabled = true，页脚随即下发引擎与封装 */
    lightfall: <?php echo json_encode($ecodLightfall, JSON_UNESCAPED_SLASHES); ?>,
    searchPanel: <?php echo ecod_option('ESearchPanel', 'on') === 'on' ? 'true' : 'false'; ?>,
    hotwords: <?php echo json_encode(ecod_search_hotwords(), JSON_UNESCAPED_UNICODE); ?>,
    searchUrl: <?php echo json_encode($ecodSearchUrl, JSON_UNESCAPED_SLASHES); ?>,
    /* 最近阅读角标有效天数（后台「外观 → 列表与卡片」）；0 = 关闭，前端不跑打标 */
    recent: <?php echo ecod_option('ERecent', 'on') === 'on' ? max(0, (int) ecod_option('ERecentDays', 7)) : 0; ?>,
    comments: <?php echo json_encode(ecod_comments_config(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
};
</script>
<?php
/* 代码块配色：与 assets/vendor/prism/themes 里各套主题的 pre 底色保持一致，
   让外壳（mac 栏 + 圆角）与代码区共用同一底色，不再出现「深壳浅底」的拼接 */
$ecodCodePalettes = [
    'prism'          => ['#f5f2f0', '#000000'],
    'coy'            => ['#fdfdfd', '#000000'],
    'solarizedlight' => ['#fdf6e3', '#657b83'],
    'tomorrow'       => ['#2d2d2d', '#cccccc'],
    'okaidia'        => ['#272822', '#f8f8f2'],
    'dark'           => ['#4c3f33', '#ffffff'],
];
$ecodCodeLight = ['#1e2230', '#dfe5f2'];
$ecodCodeDark  = ['#1e2230', '#dfe5f2'];
if ($ecodCode) {
    if ($ecodCodeTheme === 'auto') {
        $ecodCodeLight = $ecodCodePalettes['prism'];
        $ecodCodeDark  = $ecodCodePalettes['tomorrow'];
    } elseif (isset($ecodCodePalettes[$ecodCodeTheme])) {
        $ecodCodeLight = $ecodCodePalettes[$ecodCodeTheme];
        $ecodCodeDark  = $ecodCodePalettes[$ecodCodeTheme];
    }
}
?>
<?php
/* 页面族 CSS 分发（S3/S4）+ 关键 CSS 内联：
   - head 内联本族关键 CSS（构建期按 CRITICAL_FAMS 抽节：首帧骨架 + 族首屏）；
   - 页面只异步加载 core + 所属族（ecod_css_family()），不再整包下发；
   - PJAX 跨族换页由 ecod.global.js 的 ensurePageCss() 注入缺失族，
     link 全部 onload 后才派发 ecod:cssready，样式不齐不跑入场动画。
   未构建（无 dist 族产物）时回退整包阻塞加载，行为与旧版一致。 */
$ecodFams = ecod_css_family($this);
$ecodFamFiles = [];
foreach ($ecodFams as $ecodFam) {
    $ecodFamDist = ecod_dist_resolve('assets/css/ecod.fam.' . $ecodFam . '.css');
    if ($ecodFamDist === null) {
        $ecodFamFiles = [];
        break;
    }
    $ecodFamFiles[$ecodFam] = ecod_asset('assets/css/ecod.fam.' . $ecodFam . '.css');
}
/* 本页的内联关键 CSS 族：按「最具体族优先」选择——postext 复用 post 份；
   user 页同时带 post/list，但首屏形态是用户中心，应内联 user 份 */
$ecodCriticalFam = 'list';
foreach (['postext', 'user', 'page', 'home', 'post', 'list'] as $ecodF) {
    if (in_array($ecodF, $ecodFams, true)) {
        $ecodCriticalFam = $ecodF === 'postext' ? 'post' : $ecodF;
        break;
    }
}
$ecodCriticalFile = ecod_dist_resolve('assets/css/ecod.critical.' . $ecodCriticalFam . '.css');
if ($ecodCriticalFile !== null && $ecodFamFiles) {
    /* 优先 require 构建期产出的 nowdoc PHP 变体（<同名>.php）：字符串常驻
       opcache，省每请求一次磁盘读；变体缺失（旧构建产物）回退 file_get_contents */
    $ecodCriticalPhp = $ecodCriticalFile . '.php';
    if (is_file(ECOD_THEME_DIR . '/' . $ecodCriticalPhp)) {
        $ecodCriticalCss = (string) require ECOD_THEME_DIR . '/' . $ecodCriticalPhp;
    } else {
        $ecodCriticalCss = (string) file_get_contents(ECOD_THEME_DIR . '/' . $ecodCriticalFile);
    }
    echo '<style id="ecod-critical">' . $ecodCriticalCss . '</style>' . "\n";
?>
<?php foreach ($ecodFamFiles as $ecodFamName => $ecodFamUrl): ?>
<link rel="preload" data-ecod-fam="<?php echo $ecodFamName; ?>" href="<?php echo $ecodFamUrl; ?>" as="style" onload="this.onload=null;this.rel='stylesheet';if(window.__ecodFamReady)window.__ecodFamReady()">
<noscript><link rel="stylesheet" data-ecod-fam="<?php echo $ecodFamName; ?>" href="<?php echo $ecodFamUrl; ?>"></noscript>
<?php endforeach; ?>
<script>
/* 页面族就位计数：所有族 link onload 后打 cssready 标记（与旧整包语义一致） */
window.__ecodFamTotal = <?php echo count($ecodFamFiles); ?>;
window.__ecodFamReady = window.__ecodFamReady || function () {
    if (--window.__ecodFamTotal > 0) return;
    document.documentElement.setAttribute('data-ecod-cssready', '1');
    document.dispatchEvent(new Event('ecod:cssready'));
};
</script>
<script>
/* 启动期过渡禁用（.ecod-booting）的兜底解除：正常路径由 ecod.global.js 在
   ecod:cssready 后双帧移除；主脚本缺失/被拦时这里也必须放开，否则全站过渡失效。 */
setTimeout(function () { document.documentElement.classList.remove('ecod-booting'); }, 1800);
</script>
<?php } else { ?>
<link rel="stylesheet" href="<?php echo ecod_asset('assets/css/ecod.css'); ?>">
<script>document.documentElement.setAttribute('data-ecod-cssready','1');</script>
<?php } ?>
<style id="ecod-brand">
:root {
    --ecod-primary: <?php echo $ecodPrimary; ?>;
    --ecod-primary-deep: <?php echo ecod_primary_deep(); ?>;
    --ecod-primary-btn: <?php echo ecod_primary_btn(); ?>;
    --ecod-primary-soft: <?php echo ecod_primary_soft(); ?>;
    /* 可访问性派生：--ecod-primary-ink 主色填充上的文字色（白或深墨），
       --ecod-primary-text 软底/白面上的主色系文字（按对比度自动微调深浅），
       规则见 functions.php ecod_primary_ink / ecod_primary_text */
    --ecod-primary-ink: <?php echo ecod_primary_ink(); ?>;
    --ecod-primary-text: <?php echo ecod_primary_text(false); ?>;
    --ecod-code-bg: <?php echo $ecodCodeLight[0]; ?>;
    --ecod-code-fg: <?php echo $ecodCodeLight[1]; ?>;
}
html[data-theme="dark"] {
    --ecod-code-bg: <?php echo $ecodCodeDark[0]; ?>;
    --ecod-code-fg: <?php echo $ecodCodeDark[1]; ?>;
    --ecod-primary-text: <?php echo ecod_primary_text(true); ?>;
}
<?php if ($ecodFont !== ''): ?>
/* 自定义字体预加载：@font-face 在下方 brand 样式里声明，浏览器要解析完 CSS
   才开始拉字体，首帧必有一轮交换闪烁；preload 让字体与 CSS 并行加载 */
<link rel="preload" href="<?php echo htmlspecialchars($ecodFont, ENT_QUOTES, 'UTF-8'); ?>" as="font" type="font/woff2" crossorigin>
@font-face {
    font-family: "EcodCustom";
    src: url("<?php echo htmlspecialchars($ecodFont, ENT_QUOTES, 'UTF-8'); ?>") format("woff2"),
         url("<?php echo htmlspecialchars($ecodFont, ENT_QUOTES, 'UTF-8'); ?>") format("woff");
    font-display: swap;
}
:root { --ecod-font: "EcodCustom", -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif; }
<?php endif; ?>
</style>
<?php if ($ecodCode):
/* 代码块配色：每个主题仅 2-3KB，保持全站加载——PJAX 换页不重渲染 head，
   若按页面条件输出，「列表页无刷新进文章页」会缺配色；体积换正确性。
   输出为非阻塞形态（media="print" + onload 换回 all），首页少一个
   渲染阻塞请求；正文代码块在样式到达前短暂使用内置 .tok-* 底色，可接受。
   主题文件命名：默认 prism.css，其余 prism-<name>.css；缺失时回退默认。
   auto：加载浅色 + 深色两套（深色默认 disabled），由前端随明暗模式切换 */
if ($ecodCodeTheme === 'auto'): ?>
<link rel="stylesheet" id="ecod-prism-light" href="<?php echo ecod_asset('assets/vendor/prism/themes/prism.css'); ?>" media="print" onload="this.media='all'">
<link rel="stylesheet" id="ecod-prism-dark" href="<?php echo ecod_asset('assets/vendor/prism/themes/prism-tomorrow.css'); ?>" disabled>
<?php else:
$ecodCodeFile = $ecodCodeTheme === 'prism' ? 'prism.css' : 'prism-' . $ecodCodeTheme . '.css';
if (!is_file(__DIR__ . '/../assets/vendor/prism/themes/' . $ecodCodeFile)) {
    $ecodCodeFile = 'prism.css';
}
?>
<link rel="stylesheet" href="<?php echo ecod_asset('assets/vendor/prism/themes/' . $ecodCodeFile); ?>" media="print" onload="this.media='all'">
<?php endif; ?>
<noscript><link rel="stylesheet" href="<?php echo ecod_asset('assets/vendor/prism/themes/' . ($ecodCodeTheme === 'auto' ? 'prism.css' : $ecodCodeFile)); ?>"></noscript>
<?php endif; ?>
<?php if ($ecodCss !== ''): ?>
<style id="ecod-custom-css">
<?php echo $ecodCss; ?>
</style>
<?php endif; ?>
<?php
/* 捕获 Typecho 自带的头部输出：1.3 已内置一组社交标签，这里只补它没有的部分 */
ob_start();
$this->header();
$ecodHeadHtml = (string) ob_get_clean();
/* Typecho 1.3 自带一条 canonical，与下方 ecod_seo_head 的输出重复；
   专题详情页的 canonical 还带 ?t=，重复会让搜索引擎看到两个互相矛盾的地址。
   统一由主题输出唯一一条。 */
$ecodHeadHtml = (string) preg_replace('#<link\s+rel="canonical"[^>]*>\s*#i', '', $ecodHeadHtml);
echo $ecodHeadHtml;
ecod_seo_head($this, strpos($ecodHeadHtml, 'og:type') !== false);
?>
<?php if ($ecodHead !== ''): ?>
<?php echo $ecodHead; ?>
<?php endif; ?>
