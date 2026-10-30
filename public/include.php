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
<meta name="viewport" content="width=device-width, initial-scale=1">
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
window.ECOD = {
    api: <?php echo json_encode(ecod_api_url(), JSON_UNESCAPED_SLASHES); ?>,
    postUrl: <?php echo json_encode($ecodPostUrl, JSON_UNESCAPED_SLASHES); ?>,
    /* 内容页增强包（ecod.post.js）：页脚按模板条件输出，PJAX 换页时 JS 据此动态注入。
       prismUrl / qrUrl 供注入链使用：正文页才加载 prism.js（库较大），
       二维码库只服务分享卡；配色 CSS 体积小、需全站在 head 常驻，见下方说明 */
    post: <?php echo json_encode(ecod_asset('assets/js/ecod.post.js'), JSON_UNESCAPED_SLASHES); ?>,
    prismUrl: <?php echo $ecodCode ? json_encode(ecod_asset('assets/vendor/prism/prism.js'), JSON_UNESCAPED_SLASHES) : "''"; ?>,
    qrUrl: <?php echo json_encode(ecod_asset('assets/vendor/ecod-qr.js'), JSON_UNESCAPED_SLASHES); ?>,
    pjax: <?php echo ecod_option('EPjax', 'on') === 'on' ? 'true' : 'false'; ?>,
    lightbox: <?php echo $ecodLightbox ? 'true' : 'false'; ?>,
    prism: <?php echo $ecodCode ? 'true' : 'false'; ?>,
    ctxmenu: <?php echo ecod_option('ECtxMenu', 'on') === 'on' ? 'true' : 'false'; ?>,
    tocProgress: <?php echo ecod_option('ETocProgress', 'on') === 'on' ? 'true' : 'false'; ?>,
    searchPanel: <?php echo ecod_option('ESearchPanel', 'on') === 'on' ? 'true' : 'false'; ?>,
    hotwords: <?php echo json_encode(ecod_search_hotwords(), JSON_UNESCAPED_UNICODE); ?>,
    searchUrl: <?php echo json_encode($ecodSearchUrl, JSON_UNESCAPED_SLASHES); ?>,
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
/* 关键 CSS 内联：构建产物存在时，首屏必需的节（令牌/基础/布局/导航/暗色/
   首页流/卡片/侧栏/响应式等，切片清单见 build/build.mjs CRITICAL_SECTIONS）
   直接进 <head>，完整样式表转异步预加载——首屏渲染不被整包 CSS 阻塞。
   完整表 onload 后在 <html> 打 data-ecod-cssready 标记并派发 ecod:cssready
   事件：依赖整包样式的行为（如 reveal 入场动画初始化）必须等标记就位再跑，
   否则出现「内容先隐再淡入」的频闪。
   未构建（无 dist）时维持整包阻塞加载，行为与旧版一致。
   内联块在 brand 之前输出，主色等派生变量的覆盖顺序不变。 */
$ecodFullCssUrl = ecod_asset('assets/css/ecod.css');
$ecodCriticalFile = ecod_dist_resolve('assets/css/ecod.critical.css');
if ($ecodCriticalFile !== null) {
    $ecodCriticalCss = (string) file_get_contents(ECOD_THEME_DIR . '/' . $ecodCriticalFile);
    echo '<style id="ecod-critical">' . $ecodCriticalCss . '</style>' . "\n";
?>
<link rel="preload" href="<?php echo $ecodFullCssUrl; ?>" as="style" onload="this.onload=null;this.rel='stylesheet';document.documentElement.setAttribute('data-ecod-cssready','1');document.dispatchEvent(new Event('ecod:cssready'))">
<noscript><link rel="stylesheet" href="<?php echo $ecodFullCssUrl; ?>"></noscript>
<?php } else { ?>
<link rel="stylesheet" href="<?php echo $ecodFullCssUrl; ?>">
<script>document.documentElement.setAttribute('data-ecod-cssready','1');</script>
<?php } ?>
<style id="ecod-brand">
:root {
    --ecod-primary: <?php echo $ecodPrimary; ?>;
    --ecod-primary-deep: <?php echo ecod_primary_deep(); ?>;
    --ecod-primary-soft: <?php echo ecod_primary_soft(); ?>;
    --ecod-code-bg: <?php echo $ecodCodeLight[0]; ?>;
    --ecod-code-fg: <?php echo $ecodCodeLight[1]; ?>;
}
html[data-theme="dark"] {
    --ecod-code-bg: <?php echo $ecodCodeDark[0]; ?>;
    --ecod-code-fg: <?php echo $ecodCodeDark[1]; ?>;
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
   主题文件命名：默认 prism.css，其余 prism-<name>.css；缺失时回退默认。
   auto：加载浅色 + 深色两套（深色默认 disabled），由前端随明暗模式切换 */
if ($ecodCodeTheme === 'auto'): ?>
<link rel="stylesheet" id="ecod-prism-light" href="<?php echo ecod_asset('assets/vendor/prism/themes/prism.css'); ?>">
<link rel="stylesheet" id="ecod-prism-dark" href="<?php echo ecod_asset('assets/vendor/prism/themes/prism-tomorrow.css'); ?>" disabled>
<?php else:
$ecodCodeFile = $ecodCodeTheme === 'prism' ? 'prism.css' : 'prism-' . $ecodCodeTheme . '.css';
if (!is_file(__DIR__ . '/../assets/vendor/prism/themes/' . $ecodCodeFile)) {
    $ecodCodeFile = 'prism.css';
}
?>
<link rel="stylesheet" href="<?php echo ecod_asset('assets/vendor/prism/themes/' . $ecodCodeFile); ?>">
<?php endif; ?>
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
