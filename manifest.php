<?php
/**
 * Ecod Theme · PWA manifest
 *
 * 独立入口输出 application/manifest+json，主题设置里的站点名、
 * 主色与 Favicon 自动带入。浏览器据此提供「添加到主屏幕」。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 * @license   MIT
 */

/* 向上查找 Typecho 根目录（与 api.php 同一套引导） */
$ecodRoot = __DIR__;
while ($ecodRoot !== dirname($ecodRoot) && !is_file($ecodRoot . '/config.inc.php')) {
    $ecodRoot = dirname($ecodRoot);
}
if (!is_file($ecodRoot . '/config.inc.php')) {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error' => 'Typecho root not found']);
    exit;
}
require_once $ecodRoot . '/config.inc.php';

header('Content-Type: application/manifest+json; charset=UTF-8');
header('Cache-Control: public, max-age=3600');

try {
    $options = \Utils\Helper::options();
} catch (\Throwable $e) {
    $options = null;
}

$ecodTitle = $options ? (string) $options->title : 'Blog';
$ecodPrimary = function_exists('ecod_primary') ? ecod_primary() : '#4f6ef2';
$ecodFavicon = function_exists('ecod_option') ? trim((string) ecod_option('EFavicon', '')) : '';
$ecodStart = $options ? (string) $options->siteUrl : '/';

/* 图标：优先 Favicon（PNG/SVG 皆可），否则生成主色 + 站名首字的 SVG */
$ecodMark = mb_substr($ecodTitle, 0, 1, 'UTF-8') ?: 'E';
$ecodSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
    . '<rect width="64" height="64" rx="14" fill="' . htmlspecialchars($ecodPrimary, ENT_QUOTES, 'UTF-8') . '"/>'
    . '<text x="32" y="44" font-size="32" font-weight="700" font-family="sans-serif" fill="#fff" text-anchor="middle">'
    . htmlspecialchars($ecodMark, ENT_QUOTES, 'UTF-8') . '</text></svg>';
$ecodSvgUrl = 'data:image/svg+xml,' . rawurlencode($ecodSvg);

$ecodIcons = [];
if ($ecodFavicon !== '') {
    $ecodIcons[] = ['src' => $ecodFavicon, 'sizes' => '192x192 512x512', 'type' => 'image/png', 'purpose' => 'any'];
} else {
    $ecodIcons[] = ['src' => $ecodSvgUrl, 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'];
}

$ecodManifest = [
    'name'             => $ecodTitle,
    'short_name'       => mb_substr($ecodTitle, 0, 12, 'UTF-8'),
    'description'      => $options ? (string) $options->description : '',
    'start_url'        => $ecodStart,
    'scope'            => $ecodStart,
    'display'          => 'standalone',
    'background_color' => '#f5f6f9',
    'theme_color'      => $ecodPrimary,
    'icons'            => $ecodIcons,
];

echo json_encode($ecodManifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
