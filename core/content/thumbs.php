<?php
/**
 * 服务端缩略图裁剪
 *
 * Typecho 原生不做缩略图，列表页直接原图当封面，流量和首屏都很吃亏。
 * 这里用 GD 按需生成裁剪缩略图并落盘缓存：
 *
 *   ecod_thumb_url($url, 'cover')  →  api.php?action=thumb&p=cover&src=...
 *                                      → 裁剪成 480x300 的 WebP
 *
 * 约束与安全：
 *   - 只处理本站 /usr/uploads/ 下的图片，远程图原样返回（不做 SSRF 代理）
 *   - realpath 前缀校验防目录穿越；扩展名白名单
 *   - 缓存键含源文件 mtime，重新上传同路径图片自动失效
 *   - 生成失败一律 302 回原图，前端永远有图可显示
 *
 * 模板与 api.php 独立入口共用，配置读取走 ecod_account_opt。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 * @license   MIT
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    define('__TYPECHO_ROOT_DIR__', dirname(dirname(dirname(dirname(dirname(__DIR__))))));
}

/**
 * 读一条主题配置；三方兜底：主题上下文 → api 上下文 → 直接查 options
 */
function ecod_thumb_opt(string $name, $default = '')
{
    if (function_exists('ecod_option')) {
        return ecod_option($name, $default);
    }
    if (function_exists('ecod_account_opt')) {
        return ecod_account_opt($name, $default);
    }
    try {
        $options = \Utils\Helper::options();
        return isset($options->{$name}) && (string) $options->{$name} !== ''
            ? $options->{$name} : $default;
    } catch (\Throwable $e) {
        return $default;
    }
}

/**
 * 预设尺寸表：key => [宽, 高]（cover 模式，居中裁满）
 */
function ecod_thumb_presets(): array
{
    return [
        'banner' => [960, 540],  // 焦点图 / 轮播
        'cover'  => [480, 300],  // 列表卡片封面
        'side'   => [240, 180],  // 侧栏 / 相关文章小图
    ];
}

/**
 * 功能是否可用：GD 扩展 + 后台开关 + 缓存目录可写
 */
function ecod_thumb_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    $ready = false;
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagewebp')) {
        return $ready;
    }
    if (ecod_thumb_opt('EThumbServer', 'on') !== 'on') {
        return $ready;
    }

    $dir = ecod_thumb_cache_dir();
    $ready = $dir !== '' && is_dir($dir) && is_writable($dir);
    return $ready;
}

/**
 * 缓存目录：/usr/uploads/.thumbs（不存在则尝试创建）
 */
function ecod_thumb_cache_dir(): string
{
    static $dir = null;
    if ($dir !== null) {
        return $dir;
    }

    $root = rtrim((string) __TYPECHO_ROOT_DIR__, '/');
    $uploads = $root . '/usr/uploads';
    if (!is_dir($uploads)) {
        return $dir = '';
    }

    $dir = $uploads . '/.thumbs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
        /* 空文件防目录列表 */
        if (is_dir($dir) && !is_file($dir . '/index.html')) {
            @file_put_contents($dir . '/index.html', '');
        }
    }
    return is_dir($dir) ? $dir : ($dir = '');
}

/**
 * 把图片地址解析成本站 uploads 内的绝对文件路径
 *
 * 允许相对路径与同站绝对 URL；只认 /usr/uploads/ 前缀，
 * realpath 后仍需落在 uploads 内（防 ../ 穿越）。
 */
function ecod_thumb_local_path(string $url): string
{
    $url = trim($url);
    if ($url === '' || preg_match('/\s/', $url)) {
        return '';
    }

    $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
    if (strpos($path, '/usr/uploads/') !== 0) {
        return '';
    }
    if (!preg_match('/\.(jpe?g|png|gif|webp)$/i', $path)) {
        return '';
    }

    $root = rtrim((string) __TYPECHO_ROOT_DIR__, '/');
    $base = $root . '/usr/uploads/';
    $real = realpath($root . $path);
    if ($real === false || strpos($real, $base) !== 0 || !is_file($real)) {
        return '';
    }
    return $real;
}

/**
 * 把站点相对路径补全成绝对地址（302 兜底用）
 */
function ecod_thumb_abs_url(string $url): string
{
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }
    $base = rtrim((string) ecod_thumb_opt('siteUrl', ''), '/');
    return $base . $url;
}

/**
 * 缩略图地址：本站 uploads 图返回裁剪代理地址，其余原样返回
 */
function ecod_thumb_url(string $url, string $preset = 'cover'): string
{
    $url = trim($url);
    if ($url === '' || !ecod_thumb_ready()) {
        return $url;
    }

    $presets = ecod_thumb_presets();
    if (!isset($presets[$preset])) {
        return $url;
    }
    if (ecod_thumb_local_path($url) === '') {
        return $url;
    }

    $api = function_exists('ecod_api_url') ? ecod_api_url() : '/usr/themes/ecod-theme/api.php';
    return $api . '?action=thumb&p=' . rawurlencode($preset) . '&src=' . rawurlencode($url);
}

/**
 * 缓存文件路径：预设 + 源路径 + 源 mtime 共同决定，二维哈希分桶
 */
function ecod_thumb_cache_file(string $preset, string $srcPath): string
{
    $dir = ecod_thumb_cache_dir();
    if ($dir === '') {
        return '';
    }
    $mtime = @filemtime($srcPath) ?: 0;
    $key = md5($preset . '|' . $srcPath . '|' . $mtime);
    $bucket = $dir . '/' . substr($key, 0, 2);
    if (!is_dir($bucket)) {
        @mkdir($bucket, 0755, true);
    }
    return is_dir($bucket) ? $bucket . '/' . $key . '.webp' : '';
}

/**
 * 生成一张 cover 模式（居中裁满）的 WebP 缩略图
 */
function ecod_thumb_generate(string $srcPath, int $w, int $h, string $dest): bool
{
    $data = @file_get_contents($srcPath);
    if ($data === false || strlen($data) > 32 * 1024 * 1024) {
        return false;
    }
    $img = @imagecreatefromstring($data);
    if ($img === false) {
        return false;
    }

    $sw = imagesx($img);
    $sh = imagesy($img);
    if ($sw < 8 || $sh < 8) {
        imagedestroy($img);
        return false;
    }

    /* cover：等比放大到铺满目标框，再居中裁掉溢出部分 */
    $scale = max($w / $sw, $h / $sh);
    $cw = (int) max(1, round($w / $scale));
    $ch = (int) max(1, round($h / $scale));
    $cx = (int) floor(($sw - $cw) / 2);
    $cy = (int) floor(($sh - $ch) / 2);

    $canvas = imagecreatetruecolor($w, $h);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    $ok = imagecopyresampled($canvas, $img, 0, 0, $cx, $cy, $w, $h, $cw, $ch);
    imagedestroy($img);

    if (!$ok) {
        imagedestroy($canvas);
        return false;
    }

    /* 先写临时文件再原子改名，避免并发读到半张图 */
    $tmp = $dest . '.' . getmypid() . '.tmp';
    $ok = imagewebp($canvas, $tmp, 82);
    imagedestroy($canvas);
    if ($ok && filesize($tmp) > 0) {
        $ok = @rename($tmp, $dest);
    } else {
        @unlink($tmp);
        $ok = false;
    }
    return $ok;
}

/**
 * api.php?action=thumb 的输出端
 *
 * 命中缓存直接回 WebP；未命中现场生成；源图比目标小或生成失败
 * 都 302 回原图，浏览器侧不会出现裂图。
 */
function ecod_api_thumb_serve(): void
{
    $preset = (string) ($_GET['p'] ?? 'cover');
    $src = (string) ($_GET['src'] ?? '');

    $fallback = static function (string $src) use (&$preset): void {
        if ($src !== '') {
            ecod_http_status(302);
            header('Location: ' . ecod_thumb_abs_url($src));
        } else {
            ecod_http_status(404);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'not found';
        }
        exit;
    };

    $presets = ecod_thumb_presets();
    if (!isset($presets[$preset]) || $src === '') {
        $fallback($src);
    }
    [$w, $h] = $presets[$preset];

    $file = ecod_thumb_local_path($src);
    if ($file === '') {
        $fallback($src);
    }

    /* 源图不比目标框大：裁剪无意义，直接给原图 */
    $info = @getimagesize($file);
    if ($info && $info[0] <= $w && $info[1] <= $h) {
        $fallback($src);
    }

    $dest = ecod_thumb_cache_file($preset, $file);
    if ($dest === '') {
        $fallback($src);
    }

    if (!is_file($dest)) {
        $fh = fopen($dest . '.lock', 'c');
        if ($fh) {
            if (flock($fh, LOCK_EX)) {
                if (!is_file($dest)) {
                    ecod_thumb_generate($file, $w, $h, $dest);
                }
                flock($fh, LOCK_UN);
            }
            fclose($fh);
            @unlink($dest . '.lock');
        }
    }

    if (is_file($dest) && filesize($dest) > 0) {
        header('Content-Type: image/webp');
        header('Cache-Control: public, max-age=31536000, immutable');
        header('Content-Length: ' . (string) filesize($dest));
        readfile($dest);
        exit;
    }

    $fallback($src);
}
