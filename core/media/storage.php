<?php
/**
 * Ecod Theme · 媒体存储驱动层
 *
 * 设计文档：docs/dev/MEDIA-STORAGE.md
 *
 *   ecod_media_storage()          → 当前驱动单例（local / oss / cos / s3）
 *   ecod_media_ext_allowed()      → 统一图片格式白名单（收编 media.php 与 widget.php 两处）
 *   ecod_media_upload_handle()    → 上传管线：校验 → WebP 转换 → 驱动落盘 → 直写 contents
 *   ecod_media_attachment_url()   → 附件访问地址（云驱动直取 meta.url）
 *   ecod_media_delete_attachment()→ 删除（含引用检测）
 *   ecod_media_convert_to_webp()  → 存量图片转 WebP（生成新附件，不动旧行）
 *
 * 约束：
 *   - 附件行结构与 var/Widget/Upload::uploadHandle 产物逐字段对齐，
 *     原生后台附件列表 / 正文引用 / isImage 判定全部兼容
 *   - 云驱动用纯 PHP 签名（OSS V1 / COS SHA1 / S3 SigV4），不引第三方 SDK
 *   - 主题 JSON option，严禁 serialize（项目红线）
 *   - 独立入口（media.php）与主题上下文共用：配置读取带三层兜底
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    define('__TYPECHO_ROOT_DIR__', dirname(dirname(dirname(dirname(dirname(__DIR__))))));
}

/* ------------------------------------------------------------------
 * 配置读取（与 thumbs.php 的 ecod_thumb_opt 同一兜底策略）
 * ---------------------------------------------------------------- */

function ecod_media_opt(string $name, $default = '')
{
    if (function_exists('ecod_option')) {
        return ecod_option($name, $default);
    }
    if (function_exists('ecod_thumb_opt')) {
        return ecod_thumb_opt($name, $default);
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
 * 失败日志：usr/uploads/.ecod-media.log（超过 1MB 自动截断）
 */
function ecod_media_log(string $msg): void
{
    $file = rtrim((string) __TYPECHO_ROOT_DIR__, '/') . '/usr/uploads/.ecod-media.log';
    if (is_file($file) && filesize($file) > 1048576) {
        @unlink($file);
    }
    @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND | LOCK_EX);
}

/* ------------------------------------------------------------------
 * 统一格式白名单
 * ---------------------------------------------------------------- */

/**
 * 基础集 = Typecho 默认图片类型（不含 svg）+ avif
 * svg / heic 由主题开关显式放行；jxl 明确不支持
 */
function ecod_media_ext_allowed(string $ext): bool
{
    $ext = strtolower(ltrim($ext, '.'));
    $base = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'avif'];
    if (in_array($ext, $base, true)) {
        return true;
    }
    if ($ext === 'svg' && ecod_media_opt('EMediaAllowSvg', 'off') === 'on') {
        return true;
    }
    if ($ext === 'heic' || $ext === 'heif') {
        $extra = array_map('trim', explode(',', strtolower((string) ecod_media_opt('EMediaAllowExt', ''))));
        return in_array($ext, $extra, true);
    }
    return false;
}

/**
 * 追加扩展名是否未包含在 Typecho 全局 attachmentTypes 里（设置页检测提示用）
 */
function ecod_media_missing_global_types(): array
{
    try {
        $allowed = (array) \Utils\Helper::options()->allowedAttachmentTypes;
    } catch (\Throwable $e) {
        return [];
    }
    $need = ['webp', 'avif'];
    if (ecod_media_opt('EMediaAllowSvg', 'off') === 'on') {
        $need[] = 'svg';
    }
    $extra = array_map('trim', explode(',', strtolower((string) ecod_media_opt('EMediaAllowExt', ''))));
    $need = array_merge($need, array_filter($extra));
    $missing = [];
    foreach ($need as $ext) {
        if ($ext !== '' && !in_array($ext, $allowed, true)) {
            $missing[] = $ext;
        }
    }
    return $missing;
}

/* ------------------------------------------------------------------
 * WebP 转换（GD，与 thumbs.php 同一管线风格）
 * ---------------------------------------------------------------- */

/**
 * GD 可解码的常规位图（webp 转换候选源）
 */
function ecod_media_webp_eligible_ext(string $ext): bool
{
    return in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'bmp', 'tiff'], true);
}

function ecod_media_webp_ready(): bool
{
    return function_exists('imagecreatetruecolor')
        && function_exists('imagewebp')
        && ecod_media_opt('EWebpConvert', 'on') === 'on';
}

/**
 * 把一张本地可读图片转存为 WebP（透明通道保留，动图不适用）
 */
function ecod_media_webp_convert(string $srcPath, string $destPath, int $quality = 82): bool
{
    if (!is_file($srcPath)) {
        return false;
    }
    $data = @file_get_contents($srcPath);
    if ($data === false || strlen($data) > 32 * 1024 * 1024) {
        return false;
    }
    $img = @imagecreatefromstring($data);
    if ($img === false) {
        return false;
    }
    /* 动图：GD 取首帧，转静态 WebP 会丢动画，交给调用方提前排除 gif */
    imagealphablending($img, true);
    imagesavealpha($img, true);

    $tmp = $destPath . '.' . getmypid() . '.tmp';
    $ok = imagewebp($img, $tmp, max(10, min(100, $quality)));
    imagedestroy($img);

    if ($ok && is_file($tmp) && filesize($tmp) > 0) {
        $ok = @rename($tmp, $destPath);
    } else {
        @unlink($tmp);
        $ok = false;
    }
    return $ok;
}

/* ------------------------------------------------------------------
 * 驱动接口与实现
 * ---------------------------------------------------------------- */

interface EcodMediaStorage
{
    public function id(): string;
    public function available(): bool;
    /** 落盘 $key 指向的文件，返回失败原因或 '' */
    public function put(string $srcPath, string $key, string $mime): string;
    public function delete(string $key): string;
    public function url(string $key): string;
    /** CDN 图片处理参数方案；返回 '' 表示走本地 GD / 原图回退 */
    public function thumbUrl(string $url, string $preset, int $w, int $h): string;
    /** 拉取远端内容（存量转换用）；返回 null 表示不支持 */
    public function fetch(string $key);
}

/**
 * 共用 HTTP（curl）：PUT / DELETE
 */
function ecod_media_http(string $method, string $url, array $headers, $body = null): array
{
    $ch = curl_init($url);
    $opt = [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HEADER         => true,
        CURLOPT_SSL_VERIFYPEER => true,
    ];
    if ($method === 'PUT' && is_resource($body)) {
        $opt[CURLOPT_INFILE] = $body;
        $opt[CURLOPT_INFILESIZE] = fstat($body)['size'] ?? 0;
        $opt[CURLOPT_UPLOAD] = true;
    } elseif ($body !== null) {
        $opt[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($ch, $opt);
    if ($headers) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $res = curl_exec($ch);
    if ($res === false) {
        $err = curl_error($ch);
        return [0, $err];
    }
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    return [$status, substr((string) $res, $headerSize)];
}

function ecod_media_gm_date_header(): string
{
    return gmdate('D, d M Y H:i:s') . ' GMT';
}

/* ---------------------------- local ---------------------------- */

class EcodMediaStorage_Local implements EcodMediaStorage
{
    public function id(): string
    {
        return 'local';
    }

    public function available(): bool
    {
        return is_dir(rtrim(__TYPECHO_ROOT_DIR__, '/') . '/usr/uploads')
            || @mkdir(rtrim(__TYPECHO_ROOT_DIR__, '/') . '/usr/uploads', 0755, true);
    }

    private function full(string $key): string
    {
        return rtrim(__TYPECHO_ROOT_DIR__, '/') . '/usr/uploads/' . ltrim($key, '/');
    }

    public function put(string $srcPath, string $key, string $mime): string
    {
        $dest = $this->full($key);
        $dir = dirname($dest);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return '无法创建上传目录 ' . $dir;
        }
        $ok = @rename($srcPath, $dest);
        if (!$ok) {
            $ok = @copy($srcPath, $dest) && @unlink($srcPath);
        }
        if (!$ok) {
            return '本地落盘失败：' . $dest;
        }
        @chmod($dest, 0644);
        return '';
    }

    public function delete(string $key): string
    {
        $file = $this->full($key);
        if (is_file($file) && !@unlink($file)) {
            return '本地文件删除失败：' . $key;
        }
        return '';
    }

    public function url(string $key): string
    {
        return '/usr/uploads/' . ltrim($key, '/');
    }

    public function thumbUrl(string $url, string $preset, int $w, int $h): string
    {
        return ''; /* 走本地 GD 管线（thumbs.php） */
    }

    public function fetch(string $key)
    {
        $file = $this->full($key);
        return is_file($file) ? $file : null;
    }
}

/* ----------------------------- oss ----------------------------- */

class EcodMediaStorage_Oss implements EcodMediaStorage
{
    private string $ak;
    private string $secret;
    private string $bucket;
    private string $endpoint;
    private string $domain;

    public function __construct()
    {
        $this->ak      = trim((string) ecod_media_opt('EMediaOssKey', ''));
        $this->secret  = trim((string) ecod_media_opt('EMediaOssSecret', ''));
        $this->bucket  = trim((string) ecod_media_opt('EMediaOssBucket', ''));
        $endpoint      = trim((string) ecod_media_opt('EMediaOssEndpoint', ''));
        $endpoint      = preg_replace('#^https?://#i', '', $endpoint);
        $this->endpoint = $endpoint;
        $this->domain  = trim((string) ecod_media_opt('EMediaOssDomain', ''));
    }

    public function id(): string
    {
        return 'oss';
    }

    public function available(): bool
    {
        return $this->ak !== '' && $this->secret !== '' && $this->bucket !== '' && $this->endpoint !== '';
    }

    private function sign(string $verb, string $key, string $date, string $ctype): string
    {
        $resource = '/' . $this->bucket . '/' . $key;
        $string = $verb . "\n\n" . $ctype . "\n" . $date . "\n" . $resource;
        return 'OSS ' . $this->ak . ':' . base64_encode(hash_hmac('sha1', $string, $this->secret, true));
    }

    private function request(string $verb, string $key, $body, string $ctype): array
    {
        $date = ecod_media_gm_date_header();
        $url = 'https://' . $this->bucket . '.' . $this->endpoint . '/' . ltrim($key, '/');
        [$status, $resp] = ecod_media_http($verb, $url, [
            'Date: ' . $date,
            'Content-Type: ' . $ctype,
            'Authorization: ' . $this->sign($verb, ltrim($key, '/'), $date, $ctype),
        ], $body);
        if ($status >= 300) {
            ecod_media_log('OSS ' . $verb . ' ' . $key . ' => HTTP ' . $status . ' ' . substr((string) $resp, 0, 300));
        }
        return [$status, $resp];
    }

    public function put(string $srcPath, string $key, string $mime): string
    {
        $fh = fopen($srcPath, 'rb');
        if (!$fh) {
            return '读取临时文件失败';
        }
        [$status] = $this->request('PUT', $key, $fh, $mime);
        fclose($fh);
        return $status >= 200 && $status < 300 ? '' : 'OSS 上传失败（HTTP ' . $status . '）';
    }

    public function delete(string $key): string
    {
        [$status] = $this->request('DELETE', $key, null, 'application/octet-stream');
        return $status >= 200 && $status < 300 ? '' : 'OSS 删除失败（HTTP ' . $status . '）';
    }

    public function url(string $key): string
    {
        if ($this->domain !== '') {
            return rtrim($this->domain, '/') . '/' . ltrim($key, '/');
        }
        return 'https://' . $this->bucket . '.' . $this->endpoint . '/' . ltrim($key, '/');
    }

    public function thumbUrl(string $url, string $preset, int $w, int $h): string
    {
        $sep = strpos($url, '?') === false ? '?' : '&';
        return $url . $sep . 'x-oss-process=image/resize,m_fill,w_' . $w . ',h_' . $h . '/format,webp';
    }

    public function fetch(string $key)
    {
        [$status, $resp] = $this->request('GET', $key, null, 'application/octet-stream');
        return $status >= 200 && $status < 300 ? $resp : null;
    }
}

/* ----------------------------- cos ----------------------------- */

class EcodMediaStorage_Cos implements EcodMediaStorage
{
    private string $ak;
    private string $secret;
    private string $bucket;   /* name-appid */
    private string $region;

    public function __construct()
    {
        $this->ak     = trim((string) ecod_media_opt('EMediaCosKey', ''));
        $this->secret = trim((string) ecod_media_opt('EMediaCosSecret', ''));
        $this->bucket = trim((string) ecod_media_opt('EMediaCosBucket', ''));
        $this->region = trim((string) ecod_media_opt('EMediaCosRegion', 'ap-guangzhou'));
    }

    public function id(): string
    {
        return 'cos';
    }

    public function available(): bool
    {
        return $this->ak !== '' && $this->secret !== '' && $this->bucket !== '' && $this->region !== '';
    }

    private function host(): string
    {
        return $this->bucket . '.cos.' . $this->region . '.myqcloud.com';
    }

    /** COS XML API 签名（sha1） */
    private function auth(string $method, string $key): string
    {
        $start = time() - 60;
        $end = $start + 600;
        $signTime = $start . ';' . $end;
        $signKey = hash_hmac('sha1', $signTime, $this->secret);
        $httpString = strtolower($method) . "\n/" . ltrim($key, '/') . "\n\nhost=" . $this->host() . "\n";
        $stringToSign = 'sha1\n' . $signTime . "\n" . sha1($httpString) . "\n";
        $signature = hash_hmac('sha1', $stringToSign, $signKey);

        return 'q-sign-algorithm=sha1&q-ak=' . $this->ak
            . '&q-sign-time=' . $signTime
            . '&q-key-time=' . $signTime
            . '&q-header-list=host&q-url-param-list='
            . '&q-signature=' . $signature;
    }

    private function request(string $verb, string $key, $body, string $ctype): array
    {
        $url = 'https://' . $this->host() . '/' . ltrim($key, '/');
        [$status, $resp] = ecod_media_http($verb, $url, [
            'Host: ' . $this->host(),
            'Authorization: ' . $this->auth($verb, $key),
            'Content-Type: ' . $ctype,
        ], $body);
        if ($status >= 300) {
            ecod_media_log('COS ' . $verb . ' ' . $key . ' => HTTP ' . $status . ' ' . substr((string) $resp, 0, 300));
        }
        return [$status, $resp];
    }

    public function put(string $srcPath, string $key, string $mime): string
    {
        $fh = fopen($srcPath, 'rb');
        if (!$fh) {
            return '读取临时文件失败';
        }
        [$status] = $this->request('PUT', $key, $fh, $mime);
        fclose($fh);
        return $status >= 200 && $status < 300 ? '' : 'COS 上传失败（HTTP ' . $status . '）';
    }

    public function delete(string $key): string
    {
        [$status] = $this->request('DELETE', $key, null, 'application/octet-stream');
        return $status >= 200 && $status < 300 ? '' : 'COS 删除失败（HTTP ' . $status . '）';
    }

    public function url(string $key): string
    {
        $domain = trim((string) ecod_media_opt('EMediaCosDomain', ''));
        if ($domain !== '') {
            return rtrim($domain, '/') . '/' . ltrim($key, '/');
        }
        return 'https://' . $this->host() . '/' . ltrim($key, '/');
    }

    public function thumbUrl(string $url, string $preset, int $w, int $h): string
    {
        $sep = strpos($url, '?') === false ? '?' : '&';
        return $url . $sep . 'imageMogr2/thumbnail/' . $w . 'x' . $h . '!/format/webp';
    }

    public function fetch(string $key)
    {
        [$status, $resp] = $this->request('GET', $key, null, 'application/octet-stream');
        return $status >= 200 && $status < 300 ? $resp : null;
    }
}

/* ------------------------------ s3 ----------------------------- */

class EcodMediaStorage_S3 implements EcodMediaStorage
{
    private string $ak;
    private string $secret;
    private string $bucket;
    private string $region;
    private string $endpoint;
    private string $domain;

    public function __construct()
    {
        $this->ak      = trim((string) ecod_media_opt('EMediaS3Key', ''));
        $this->secret  = trim((string) ecod_media_opt('EMediaS3Secret', ''));
        $this->bucket  = trim((string) ecod_media_opt('EMediaS3Bucket', ''));
        $this->region  = trim((string) ecod_media_opt('EMediaS3Region', 'auto'));
        $endpoint      = trim((string) ecod_media_opt('EMediaS3Endpoint', ''));
        $this->endpoint = preg_replace('#^https?://#i', '', $endpoint);
        $this->domain  = trim((string) ecod_media_opt('EMediaS3Domain', ''));
    }

    public function id(): string
    {
        return 's3';
    }

    public function available(): bool
    {
        return $this->ak !== '' && $this->secret !== '' && $this->bucket !== '' && $this->endpoint !== '';
    }

    private function host(): string
    {
        return $this->bucket . '.' . $this->endpoint;
    }

    /** AWS SigV4（UNSIGNED-PAYLOAD） */
    private function auth(string $verb, string $key, string $amzDate): array
    {
        $date = substr($amzDate, 0, 8);
        $scope = $date . '/' . $this->region . '/s3/aws4_request';
        $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';
        $canonical = strtoupper($verb) . "\n"
            . '/' . ltrim($key, '/') . "\n\n"
            . 'host:' . $this->host() . "\n"
            . 'x-amz-content-sha256:UNSIGNED-PAYLOAD' . "\n"
            . 'x-amz-date:' . $amzDate . "\n\n"
            . $signedHeaders . "\n"
            . 'UNSIGNED-PAYLOAD';

        $stringToSign = 'AWS4-HMAC-SHA256' . "\n" . $amzDate . "\n" . $scope . "\n" . hash('sha256', $canonical);
        $kDate = hash_hmac('sha256', $date, 'AWS4' . $this->secret, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        return 'AWS4-HMAC-SHA256 Credential=' . $this->ak . '/' . $scope
            . ', SignedHeaders=' . $signedHeaders
            . ', Signature=' . $signature;
    }

    private function request(string $verb, string $key, $body, string $ctype): array
    {
        $amzDate = gmdate('Ymd\THis\Z');
        $url = 'https://' . $this->host() . '/' . ltrim($key, '/');
        [$status, $resp] = ecod_media_http($verb, $url, [
            'Host: ' . $this->host(),
            'x-amz-date: ' . $amzDate,
            'x-amz-content-sha256: UNSIGNED-PAYLOAD',
            'Content-Type: ' . $ctype,
            'Authorization: ' . $this->auth($verb, $key, $amzDate),
        ], $body);
        if ($status >= 300) {
            ecod_media_log('S3 ' . $verb . ' ' . $key . ' => HTTP ' . $status . ' ' . substr((string) $resp, 0, 300));
        }
        return [$status, $resp];
    }

    public function put(string $srcPath, string $key, string $mime): string
    {
        $fh = fopen($srcPath, 'rb');
        if (!$fh) {
            return '读取临时文件失败';
        }
        [$status] = $this->request('PUT', $key, $fh, $mime);
        fclose($fh);
        return $status >= 200 && $status < 300 ? '' : 'S3 上传失败（HTTP ' . $status . '）';
    }

    public function delete(string $key): string
    {
        [$status] = $this->request('DELETE', $key, null, 'application/octet-stream');
        return $status >= 200 && $status < 300 ? '' : 'S3 删除失败（HTTP ' . $status . '）';
    }

    public function url(string $key): string
    {
        if ($this->domain !== '') {
            return rtrim($this->domain, '/') . '/' . ltrim($key, '/');
        }
        return 'https://' . $this->host() . '/' . ltrim($key, '/');
    }

    public function thumbUrl(string $url, string $preset, int $w, int $h): string
    {
        return ''; /* S3 系无统一图片处理，回退原图 */
    }

    public function fetch(string $key)
    {
        [$status, $resp] = $this->request('GET', $key, null, 'application/octet-stream');
        return $status >= 200 && $status < 300 ? $resp : null;
    }
}

/* ------------------------------------------------------------------
 * 工厂
 * ---------------------------------------------------------------- */

function ecod_media_storage()
{
    static $driver = null;
    if ($driver !== null) {
        return $driver;
    }

    $map = [
        'oss' => 'EcodMediaStorage_Oss',
        'cos' => 'EcodMediaStorage_Cos',
        's3'  => 'EcodMediaStorage_S3',
    ];
    $want = strtolower(trim((string) ecod_media_opt('EMediaStorage', 'local')));

    if (isset($map[$want])) {
        $candidate = new $map[$want]();
        if ($candidate->available()) {
            return $driver = $candidate;
        }
        ecod_media_log('驱动 ' . $want . ' 配置不完整，回退 local');
    }
    return $driver = new EcodMediaStorage_Local();
}

/**
 * 生成对象 key：YYYY/MM/<crc32>.<ext>（与 Typecho 命名同风格）
 */
function ecod_media_build_key(string $ext): string
{
    $ext = strtolower(ltrim($ext, '.'));
    return date('Y') . '/' . date('m') . '/' . sprintf('%u', crc32(uniqid('', true))) . '.' . $ext;
}

/* ------------------------------------------------------------------
 * 附件行直写（字段结构与 Upload::uploadHandle 产物对齐）
 * ---------------------------------------------------------------- */

function ecod_media_attachment_insert(array $meta, int $parent = 0): int
{
    $db = \Typecho\Db::get();
    $insert = $db->insert('table.contents')->rows([
        'title'        => (string) $meta['name'],
        'slug'         => (string) $meta['name'],
        'created'      => time(),
        'modified'     => time(),
        'text'         => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'type'         => 'attachment',
        'status'       => 'publish',
        'parent'       => $parent,
        'commentsNum'  => 0,
        'allowComment' => 1,
        'allowPing'    => 0,
        'allowFeed'    => 1,
    ]);
    return (int) $db->query($insert);
}

/**
 * 附件访问地址：云驱动直取 meta.url；本地走 Typecho 句柄（兼容第三方插件）
 */
function ecod_media_attachment_url(array $meta): string
{
    $driver = (string) ($meta['driver'] ?? '');
    if ($driver !== '' && $driver !== 'local' && !empty($meta['url'])) {
        return (string) $meta['url'];
    }
    try {
        return \Widget\Upload::attachmentHandle(new \Typecho\Config([
            'path' => (string) ($meta['path'] ?? ''),
            'type' => (string) ($meta['type'] ?? ''),
            'name' => (string) ($meta['name'] ?? ''),
            'size' => (int) ($meta['size'] ?? 0),
        ]));
    } catch (\Throwable $e) {
        return (string) ($meta['path'] ?? '');
    }
}

/**
 * 附件对象 key（云驱动存 meta.key；本地从 meta.path 还原）
 */
function ecod_media_attachment_key(array $meta): string
{
    $driver = (string) ($meta['driver'] ?? 'local');
    if ($driver !== 'local') {
        return (string) ($meta['key'] ?? '');
    }
    $path = (string) ($meta['path'] ?? '');
    $pos = strpos($path, '/usr/uploads/');
    return $pos === false ? '' : substr($path, $pos + strlen('/usr/uploads/'));
}

/* ------------------------------------------------------------------
 * 上传管线
 * ---------------------------------------------------------------- */

/**
 * 处理一个上传文件，返回 ['error' => 提示] 或附件项
 */
function ecod_media_upload_handle(array $file, int $parent = 0)
{
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['error' => '没有收到有效文件'];
    }

    $origName = (string) ($file['name'] ?? 'image');
    $origName = str_replace(["\0", '/'], '', $origName);
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION) ?: '');
    if ($ext === '' || !ecod_media_ext_allowed($ext)) {
        return ['error' => '不支持的图片格式：.' . $ext];
    }

    $maxBytes = (int) ecod_media_opt('EMediaMaxBytes', '16777216');
    $size = (int) ($file['size'] ?? 0);
    if ($maxBytes > 0 && $size > $maxBytes) {
        return ['error' => '文件超过大小限制（' . round($maxBytes / 1048576) . ' MB）'];
    }

    /* 内容嗅探：堵住只看扩展名的口子（svg 是文本，单独放行） */
    if ($ext !== 'svg') {
        $mime = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            $mime = (string) finfo_file($fi, $file['tmp_name']);
            finfo_close($fi);
        } elseif (function_exists('mime_content_type')) {
            $mime = (string) mime_content_type($file['tmp_name']);
        }
        if (strpos($mime, 'image/') !== 0) {
            return ['error' => '文件内容不是图片（检测到 ' . $mime . '）'];
        }
    }

    $driver = ecod_media_storage();
    $quality = (int) ecod_media_opt('EWebpQuality', '82');
    $converted = false;

    /* WebP 上传即转：源是常规位图、GD 可用、非保原图模式之外的条件 */
    if (ecod_media_webp_eligible_ext($ext) && ecod_media_webp_ready()) {
        $webpTmp = $file['tmp_name'] . '.webp';
        if (ecod_media_webp_convert($file['tmp_name'], $webpTmp, $quality)) {
            if (ecod_media_opt('EWebpKeepOriginal', 'off') === 'on') {
                /* 保留原图：额外 put 一份 -orig 后缀 */
                $origKey = ecod_media_build_key($ext);
                $origKey = preg_replace('/\.([a-z0-9]+)$/i', '-orig.$1', $origKey);
                $err = $driver->put($file['tmp_name'], $origKey, 'image/' . $ext);
                if ($err !== '') {
                    ecod_media_log('保留原图失败 ' . $origKey . ' ' . $err);
                }
            }
            @unlink($file['tmp_name']);
            $file['tmp_name'] = $webpTmp;
            $file['size'] = filesize($webpTmp) ?: $size;
            $ext = 'webp';
            $converted = true;
        }
    }

    $key = ecod_media_build_key($ext);
    $mime = $ext === 'svg' ? 'image/svg+xml' : 'image/' . ($ext === 'jpg' ? 'jpeg' : ($ext === 'tiff' ? 'tiff' : ($ext === 'heic' ? 'heic' : $ext)));
    if ($ext === 'jpeg') {
        $mime = 'image/jpeg';
    }

    $err = $driver->put($file['tmp_name'], $key, $mime);
    if ($err !== '') {
        return ['error' => $err];
    }

    $meta = [
        'name'  => $origName,
        'path'  => '/usr/uploads/' . $key,
        'size'  => (int) $file['size'],
        'type'  => $ext,
        'mime'  => $mime,
    ];
    if ($driver->id() !== 'local') {
        $meta['driver'] = $driver->id();
        $meta['key']    = $key;
        $meta['url']    = $driver->url($key);
    }
    if ($converted) {
        $meta['source_ext'] = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $meta['webp_quality'] = $quality;
    }

    $cid = ecod_media_attachment_insert($meta, $parent);

    return [
        'cid'     => $cid,
        'title'   => $origName,
        'name'    => $origName,
        'url'     => ecod_media_attachment_url($meta),
        'mime'    => $mime,
        'size'    => ecod_media_bytes_fmt((int) $file['size']),
        'bytes'   => (int) $file['size'],
        'webp'    => $converted,
        'driver'  => $driver->id(),
    ];
}

function ecod_media_bytes_fmt(int $size): string
{
    if ($size <= 0) {
        return '';
    }
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $num = (float) $size;
    while ($num >= 1024 && $i < 3) {
        $num /= 1024;
        $i++;
    }
    return ($num >= 10 || $i === 0 ? (string) round($num) : number_format($num, 1)) . ' ' . $units[$i];
}

/* ------------------------------------------------------------------
 * 引用计数 / 删除 / 转换
 * ---------------------------------------------------------------- */

/**
 * 统计某附件对象（按 Y/m/文件名 特征）被多少篇内容引用
 */
function ecod_media_count_refs(string $feature): int
{
    $feature = trim($feature);
    if ($feature === '') {
        return 0;
    }
    try {
        $db = \Typecho\Db::get();
        $sql = $db->select('COUNT(DISTINCT cid) AS num')->from('table.contents')
            ->where('type IN ?', ['post', 'page', 'post_draft', 'page_draft'])
            ->where('text LIKE ?', '%' . str_replace(['%', '_'], ['\%', '\_'], $feature) . '%');
        return (int) ($db->fetchObject($sql)->num ?? 0);
    } catch (\Throwable $e) {
        return 0;
    }
}

/**
 * 删除附件：先引用检测，被引用且未 force 则拒绝
 */
function ecod_media_delete_attachment(int $cid, bool $force = false)
{
    $db = \Typecho\Db::get();
    $row = $db->fetchRow($db->select('cid', 'text')->from('table.contents')
        ->where('cid = ?', $cid)->where('type = ?', 'attachment'));
    if (!$row) {
        return ['error' => '附件不存在'];
    }

    $meta = json_decode((string) $row['text'], true);
    if (!is_array($meta)) {
        $meta = [];
    }

    $driver = ecod_media_storage();
    $driverOfMeta = (string) ($meta['driver'] ?? 'local');
    $key = ecod_media_attachment_key($meta);
    $feature = $key !== '' ? $key : ltrim((string) ($meta['path'] ?? ''), '/');
    /* 引用按文件名特征匹配（路径里的 Y/m/名.ext） */
    $refs = ecod_media_count_refs(basename($feature));
    if ($refs > 0 && !$force) {
        return ['error' => '该图片被 ' . $refs . ' 篇内容引用，请先处理引用或强制删除', 'refs' => $refs];
    }

    if ($key !== '') {
        if ($driverOfMeta !== 'local') {
            $err = ecod_media_storage_by_id($driverOfMeta)->delete($key);
        } else {
            $err = $driver->delete($key);
        }
        if ($err !== '') {
            ecod_media_log('删除失败 cid=' . $cid . ' ' . $err);
        }
        /* 保留原图的伴生文件 */
        $origKey = preg_replace('/\.([a-z0-9]+)$/i', '-orig.$1', $key);
        if ($origKey !== $key) {
            if ($driverOfMeta !== 'local') {
                ecod_media_storage_by_id($driverOfMeta)->delete($origKey);
            } else {
                $driver->delete($origKey);
            }
        }
    }

    $db->query($db->delete('table.contents')->where('cid = ?', $cid));
    return ['ok' => true, 'refs' => $refs];
}

function ecod_media_storage_by_id(string $id): EcodMediaStorage
{
    static $cache = [];
    if (isset($cache[$id])) {
        return $cache[$id];
    }
    $map = ['oss' => 'EcodMediaStorage_Oss', 'cos' => 'EcodMediaStorage_Cos', 's3' => 'EcodMediaStorage_S3'];
    if (isset($map[$id])) {
        $obj = new $map[$id]();
        if ($obj->available()) {
            return $cache[$id] = $obj;
        }
    }
    return $cache[$id] = new EcodMediaStorage_Local();
}

/**
 * 存量图片转 WebP：生成新附件（不动旧行，引用不受影响）
 * 返回 ['error' => 提示] 或新附件项
 */
function ecod_media_convert_to_webp(int $cid)
{
    if (!ecod_media_webp_ready()) {
        return ['error' => 'WebP 转换未开启或 GD 不可用'];
    }

    $db = \Typecho\Db::get();
    $row = $db->fetchRow($db->select('cid', 'title', 'text', 'parent')->from('table.contents')
        ->where('cid = ?', $cid)->where('type = ?', 'attachment'));
    if (!$row) {
        return ['error' => '附件不存在'];
    }

    $meta = json_decode((string) $row['text'], true);
    if (!is_array($meta)) {
        $meta = [];
    }
    $ext = strtolower((string) ($meta['type'] ?? ''));
    if ($ext === 'webp') {
        return ['error' => '已经是 WebP'];
    }
    if (in_array($ext, ['gif', 'svg', 'avif'], true)) {
        return ['error' => '该格式不适合转 WebP（' . $ext . '）'];
    }
    if (!ecod_media_webp_eligible_ext($ext)) {
        return ['error' => '不支持转换的格式：' . $ext];
    }

    /* 取源图字节：本地读文件，云端走驱动 fetch */
    $key = ecod_media_attachment_key($meta);
    $srcPath = '';
    $tmp = '';
    if ((string) ($meta['driver'] ?? 'local') === 'local') {
        $srcPath = rtrim(__TYPECHO_ROOT_DIR__, '/') . '/usr/uploads/' . $key;
        if (!is_file($srcPath)) {
            return ['error' => '本地源文件不存在'];
        }
    } else {
        $data = ecod_media_storage_by_id((string) $meta['driver'])->fetch($key);
        if ($data === null) {
            return ['error' => '云端源文件读取失败'];
        }
        $tmp = tempnam(sys_get_temp_dir(), 'ecodcvt');
        file_put_contents($tmp, $data);
        $srcPath = $tmp;
    }

    $webpTmp = $srcPath . '.webp';
    $quality = (int) ecod_media_opt('EWebpQuality', '82');
    if (!ecod_media_webp_convert($srcPath, $webpTmp, $quality)) {
        if ($tmp !== '') {
            @unlink($tmp);
        }
        return ['error' => 'GD 解码/编码失败'];
    }
    if ($tmp !== '') {
        @unlink($tmp);
    }

    $driver = ecod_media_storage();
    $newKey = ecod_media_build_key('webp');
    $err = $driver->put($webpTmp, $newKey, 'image/webp');
    @unlink($webpTmp);
    if ($err !== '') {
        return ['error' => $err];
    }

    $newMeta = [
        'name'  => preg_replace('/\.[a-z0-9]+$/i', '.webp', (string) ($meta['name'] ?? 'image.webp')),
        'path'  => '/usr/uploads/' . $newKey,
        'size'  => filesize($driver->id() === 'local'
            ? rtrim(__TYPECHO_ROOT_DIR__, '/') . '/usr/uploads/' . $newKey
            : $webpTmp) ?: 0,
        'type'  => 'webp',
        'mime'  => 'image/webp',
    ];
    if ($driver->id() !== 'local') {
        $newMeta['driver'] = $driver->id();
        $newMeta['key']    = $newKey;
        $newMeta['url']    = $driver->url($newKey);
    }
    if ($driver->id() === 'local') {
        $newMeta['size'] = @filesize(rtrim(__TYPECHO_ROOT_DIR__, '/') . '/usr/uploads/' . $newKey) ?: $newMeta['size'];
    }
    $newMeta['source_ext'] = $ext;
    $newMeta['webp_quality'] = $quality;
    $newMeta['converted_from'] = (int) $cid;

    $newCid = ecod_media_attachment_insert($newMeta, (int) ($row['parent'] ?? 0));

    return [
        'cid'    => $newCid,
        'oldCid' => $cid,
        'title'  => $newMeta['name'],
        'name'   => $newMeta['name'],
        'url'    => ecod_media_attachment_url($newMeta),
        'mime'   => 'image/webp',
        'bytes'  => (int) $newMeta['size'],
        'webp'   => true,
        'driver' => $driver->id(),
    ];
}
