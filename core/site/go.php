<?php
/**
 * Ecod Theme · 外链中转（go 跳转加载页）
 *
 * 站外链接改写为 /go/<token>（或 /index.php/go/<token>），
 * 命中时输出一个主题风格的「即将离开本站」加载页，倒计时后自动跳转。
 * 在 public/header.php 最前面调用 ecod_go_intercept()，无需配置路由。
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
 * 站点 host 集合（siteUrl + home，可能带 www 差异）
 */
function ecod_go_hosts(): array
{
    static $hosts = null;
    if ($hosts !== null) {
        return $hosts;
    }

    $hosts = [];
    $options = \Widget\Options::alloc();
    foreach ([$options->siteUrl, $options->originalSiteUrl ?? '', $options->themeUrl] as $url) {
        $host = parse_url((string) $url, PHP_URL_HOST);
        if ($host) {
            $hosts[] = strtolower($host);
            /* www 互认 */
            if (strpos($host, 'www.') === 0) {
                $hosts[] = strtolower(substr($host, 4));
            } else {
                $hosts[] = 'www.' . strtolower($host);
            }
        }
    }
    return array_values(array_unique($hosts));
}

/**
 * 签名密钥（与评论令牌同一密钥源）
 */
function ecod_go_secret(): string
{
    if (function_exists('ecod_comment_secret')) {
        return ecod_comment_secret();
    }
    try {
        $options = \Widget\Options::alloc();
        return (string) ($options->secret !== '' ? $options->secret : $options->siteUrl);
    } catch (\Throwable $e) {
        return 'ecod';
    }
}

/**
 * URL → 中转 token（base64url + HMAC 签名）
 *
 * 纯 base64 任何人都能构造（开放重定向会被扫描器和钓鱼利用），
 * 加 10 位 HMAC 后，只有本站渲染出的链接才会被中转页放行。
 */
function ecod_go_token(string $url): string
{
    $b64 = rtrim(strtr(base64_encode($url), '+/', '-_'), '=');
    return $b64 . '.' . substr(hash_hmac('sha256', $b64, ecod_go_secret()), 0, 10);
}

/**
 * token → URL（无签名、签名不符或非法返回空）
 */
function ecod_go_untoken(string $token): string
{
    $dot = strrpos($token, '.');
    if ($dot === false) {
        return '';
    }
    $b64 = substr($token, 0, $dot);
    $sig = substr($token, $dot + 1);
    if (!preg_match('/^[A-Za-z0-9_\-]{8,}$/', $b64) || !preg_match('/^[a-f0-9]{10}$/', $sig)) {
        return '';
    }
    if (!hash_equals(substr(hash_hmac('sha256', $b64, ecod_go_secret()), 0, 10), $sig)) {
        return '';
    }

    $pad = strtr($b64, '-_', '+/');
    $pad .= str_repeat('=', (4 - strlen($pad) % 4) % 4);
    $url = base64_decode($pad, true);
    if ($url === false || !preg_match('#^https?://#i', $url)) {
        return '';
    }
    return $url;
}

/**
 * 是否站内地址
 */
function ecod_go_is_local(string $url): bool
{
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) {
        return true;
    }
    return in_array(strtolower($host), ecod_go_hosts(), true);
}

/**
 * 是否指向一张图片（按扩展名判断，忽略查询串）
 */
function ecod_go_is_image(string $url): bool
{
    $path = (string) parse_url($url, PHP_URL_PATH);
    return (bool) preg_match('#\.(?:jpe?g|png|gif|webp|avif|bmp|svg)$#i', $path);
}

/**
 * 中转跳转地址
 *
 * 用 ?go= 查询串而不是 /go/ 路径：路径形态依赖「未匹配路由走到主题
 * 的 404 模板 → header.php 里拦截」，部分 Typecho 版本在进主题前就
 * 输出内置 404，整条链失效；查询串挂在任意正常页面上，必经 header.php。
 */
function ecod_go_url(string $target): string
{
    $options = \Widget\Options::alloc();
    $base = rtrim((string) $options->siteUrl, '/');
    return $base . '/?go=' . ecod_go_token($target);
}

/**
 * 改写正文里的站外链接为中转地址
 *
 * 只处理 http/https 的 <a href>；站内、锚点、协议链接一律跳过。
 * 在 post.php / page.php 的短代码渲染之后调用。
 */
function ecod_go_rewrite(string $html): string
{
    if ($html === '' || strpos($html, '<a ') === false || ecod_option('ELinkGo', 'on') !== 'on') {
        return $html;
    }

    return preg_replace_callback(
        '#<a\s([^>]*?)href=("|\')(https?://[^"\']+)\2([^>]*)>(.*?)</a>#is',
        static function (array $m): string {
            $url = $m[3];

            /* oEmbed 嵌入块里的链接保持原样：X / Instagram / TikTok 的脚本依赖原始地址 */
            if (strpos($m[0], 'data-ecod-oembed') !== false) {
                return $m[0];
            }

            if (ecod_go_is_local($url) || strpos($url, '/go/') !== false || strpos($url, '?go=') !== false) {
                return $m[0];
            }

            /* 图片链接：点开是看图的，不进中转页，保留原样交给前端灯箱。
             * ① href 本身就是图片地址（原图 / 大图）
             * ② <a> 内只有图片没有文字 */
            if (ecod_go_is_image($url)) {
                return $m[0];
            }
            if (stripos($m[5], '<img') !== false) {
                $text = trim(str_replace(['&nbsp;', "\xC2\xA0"], '', strip_tags($m[5])));
                if ($text === '') {
                    return $m[0];
                }
            }

            $go = ecod_go_url($url);
            $attrs = trim((string) preg_replace('#\s+(?:rel|target)\s*=\s*("[^"]*"|\'[^\']*\')#i', '', $m[1] . $m[4]));
            return '<a' . ($attrs !== '' ? ' ' . $attrs : '') . ' href="' . htmlspecialchars($go, ENT_QUOTES, 'UTF-8') . '"'
                . ' target="_blank" rel="nofollow noopener noreferrer" data-go="1"'
                . ' title="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . $m[5] . '</a>';
        },
        $html
    ) ?? $html;
}

/**
 * 拦截 /go/<token>：命中直接输出加载页并终止
 *
 * 在 public/header.php 输出任何内容之前调用。
 */
function ecod_go_intercept(): void
{
    if (ecod_option('ELinkGo', 'on') !== 'on') {
        return;
    }

    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    if ($uri === '' || stripos($uri, '/go/') === false && strpos($uri, 'go=') === false) {
        return;
    }

    $token = '';

    /* /go/<token>（含 /index.php/go/<token>）；token 带 .签名 后缀，须整段捕获 */
    if (preg_match('#/go/([A-Za-z0-9_\-]+\.[a-f0-9]{10})/?$#', parse_url($uri, PHP_URL_PATH) ?? '', $m)) {
        $token = $m[1];
    }

    /* ?go=<token> 备选形态 */
    if ($token === '' && isset($_GET['go'])) {
        $token = (string) $_GET['go'];
    }

    if ($token === '' || !preg_match('#^[A-Za-z0-9_\-]{8,}\.[a-f0-9]{10}$#', $token)) {
        return;
    }

    $target = ecod_go_untoken($token);
    if ($target === '' || ecod_go_is_local($target)) {
        return;
    }

    ecod_go_page($target);
}

/**
 * 输出「即将离开本站」加载页并终止请求
 */
function ecod_go_page(string $target): void
{
    $site = \Widget\Options::alloc();
    $title = (string) $site->title;
    $url = htmlspecialchars($target, ENT_QUOTES, 'UTF-8');
    $home = htmlspecialchars(rtrim((string) $site->siteUrl, '/') . '/', ENT_QUOTES, 'UTF-8');
    $primary = function_exists('ecod_primary') ? ecod_primary() : '#4f6ef2';

    /* 404 渲染路径里可能已被置为 404，重置为 200（Response 单例 + 原生 header 双保险） */
    try {
        \Typecho\Response::getInstance()->setStatus(200);
    } catch (\Throwable $e) {
    }
    header('HTTP/1.1 200 OK', true, 200);
    header('Content-Type: text/html; charset=UTF-8', true);
    header('X-Robots-Tag: noindex', true);

    echo '<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<meta http-equiv="refresh" content="6; url=' . $url . '">
<title>即将离开本站 - ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>
<style>
:root { --go-primary: ' . $primary . '; }
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    min-height: 100vh; display: flex; align-items: center; justify-content: center;
    padding: 24px; font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", sans-serif;
    background: radial-gradient(1200px 600px at 20% -10%, color-mix(in srgb, var(--go-primary) 9%, #f4f6fb), #eef0f6);
    color: #1c2130;
}
@media (prefers-color-scheme: dark) {
    body { background: #151923; color: #e8ebf4; }
    .go-card { background: #1e2430 !important; border-color: #2c3446 !important; }
    .go-url { background: #171c27 !important; color: #9aa4bd !important; }
    .go-sub { color: #8b93a8 !important; }
}
.go-card {
    width: 100%; max-width: 460px; padding: 36px 32px 28px; text-align: center;
    background: #fff; border: 1px solid #e6e9f2; border-radius: 18px;
    box-shadow: 0 20px 50px rgba(23, 32, 63, .1);
}
.go-icon {
    width: 56px; height: 56px; margin: 0 auto 18px; border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    background: color-mix(in srgb, var(--go-primary) 12%, #fff); color: var(--go-primary);
}
.go-card h1 { font-size: 18px; font-weight: 650; margin-bottom: 6px; }
.go-sub { font-size: 12.5px; color: #7d879e; margin-bottom: 18px; }
.go-url {
    padding: 10px 14px; margin-bottom: 20px; text-align: left;
    background: #f5f6fa; border-radius: 10px; color: #5b6479;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 12px; line-height: 1.6; word-break: break-all;
}
.go-actions { display: flex; gap: 10px; justify-content: center; }
.go-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    height: 40px; padding: 0 20px; border-radius: 10px; border: 0;
    font-size: 13.5px; font-weight: 600; cursor: pointer; text-decoration: none;
    transition: transform .14s, box-shadow .14s, background .14s;
}
.go-btn--go { background: var(--go-primary); color: #fff; }
.go-btn--go:hover { transform: translateY(-1px); box-shadow: 0 8px 20px color-mix(in srgb, var(--go-primary) 35%, transparent); }
.go-btn--back { background: transparent; color: inherit; border: 1px solid #d8dce8; }
@media (prefers-color-scheme: dark) { .go-btn--back { border-color: #3a4358; } }
.go-count { margin-top: 16px; font-size: 12px; color: #98a0b4; }
.go-count b { color: var(--go-primary); font-weight: 650; }
</style>
</head>
<body>
<div class="go-card">
    <div class="go-icon">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.6 13.4a3.8 3.8 0 0 0 5.4 0l2.6-2.6a3.8 3.8 0 0 0-5.4-5.4l-1.4 1.4"/>
            <path d="M13.4 10.6a3.8 3.8 0 0 0-5.4 0l-2.6 2.6a3.8 3.8 0 0 0 5.4 5.4l1.4-1.4"/>
        </svg>
    </div>
    <h1>即将离开本站</h1>
    <p class="go-sub">你即将访问的链接不属于 ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '，请注意账号与财产安全</p>
    <div class="go-url">' . $url . '</div>
    <div class="go-actions">
        <a class="go-btn go-btn--go" href="' . $url . '" rel="nofollow noopener noreferrer">继续访问</a>
        <a class="go-btn go-btn--back" href="' . $home . '">返回首页</a>
    </div>
    <p class="go-count"><b id="go-n">5</b> 秒后自动跳转，请确认目标地址无误</p>
</div>
<script>
(function () {
    var n = 5, el = document.getElementById("go-n");
    var t = setInterval(function () {
        n--;
        if (n <= 0) { clearInterval(t); location.replace(' . json_encode($target) . '); return; }
        el.textContent = n;
    }, 1000);
})();
</script>
</body>
</html>';
    exit;
}
