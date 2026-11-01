<?php

/**
 * Ecod Theme · 授权核心（状态机 / 验签 / 激活 / 复验 / 锁死门禁）
 *
 * 状态 mode：off / licensed / stale / grace / trial / expired / domain /
 *           revoked / invalid / nokey
 * 其中 expired / domain / revoked / invalid 锁站（管理员豁免）。
 *
 * @package ecod-theme
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

require_once __DIR__ . '/pubkey.php';
require_once __DIR__ . '/block.php';

/* ------------------------------------------------------------------
 * 工具
 * ---------------------------------------------------------------- */

/** base64url 解码 */
function ecod_lic_b64d(string $value): string
{
    $value = strtr($value, '-_', '+/');
    $pad = strlen($value) % 4;
    if ($pad) {
        $value .= str_repeat('=', 4 - $pad);
    }
    $raw = base64_decode($value, true);
    return $raw === false ? '' : $raw;
}

/** 主机归一化：去协议 / 路径 / 端口 / www / 小写 */
function ecod_license_host_normalize(string $host): string
{
    $host = trim($host);
    $host = preg_replace('#^https?://#i', '', $host);
    $host = preg_replace('#/.*$#', '', (string) $host);
    $host = preg_replace('#:\d+$#', '', (string) $host);
    $host = strtolower(trim((string) $host));
    $host = preg_replace('#^www\.#', '', $host);
    return $host;
}

/** 当前站点 host（裸域） */
function ecod_license_host(): string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '' && isset($_SERVER['SERVER_NAME'])) {
        $host = (string) $_SERVER['SERVER_NAME'];
    }
    return ecod_license_host_normalize($host);
}

/** 是否管理员 */
function ecod_license_is_admin(): bool
{
    try {
        $user = \Typecho\Widget::widget('Widget_User');
    } catch (\Throwable $e) {
        try {
            $user = \Widget\User::alloc();
        } catch (\Throwable $e2) {
            return false;
        }
    }
    try {
        return (bool) $user->hasLogin() && (string) $user->group === 'administrator';
    } catch (\Throwable $e) {
        return false;
    }
}

/** 是否本地开发环境（localhost / 内网 IP / .local .test 等保留域） */
function ecod_license_is_local_host(): bool
{
    $h = ecod_license_host();
    if ($h === '' || $h === 'localhost' || !str_contains($h, '.')) {
        return true;
    }
    if (filter_var($h, FILTER_VALIDATE_IP)) {
        /* 回环 / 内网 / 链路本地地址视为本地环境；公网 IP 仍走授权 */
        return !filter_var($h, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
    $parts = explode('.', $h);
    $tld = end($parts);
    return in_array($tld, ['local', 'test', 'example', 'invalid', 'localhost', 'dev'], true);
}

/**
 * 授权模式是否开启
 *
 * 默认开启（买家新装即进入授权模式：7 天试用 → 未激活锁死前台）。
 * 两个豁免口：
 *   - 设置项 ELicOn 显式 off（作者站 / 自建分发站）；
 *   - 本地开发环境自动豁免（localhost / 内网 IP / 保留域，参考子比 is_local 的惯例）。
 */
function ecod_license_enabled(): bool
{
    /* 作者站 / 自建分发站的关闭途径：config.inc.php 里 define('ECOD_LIC_DISABLED', true)，
       不在买家可见的主题设置里提供开关 —— 买家关掉开关等于锁死形同虚设 */
    if (defined('ECOD_LIC_DISABLED') && ECOD_LIC_DISABLED) {
        return false;
    }
    $v = strtolower(trim((string) ecod_option('ELicOn', '')));
    if ($v === 'off' || $v === '0') {
        return false;
    }
    if ($v === 'on' || $v === '1') {
        return true;
    }
    /* 未设置：默认 on，本地环境豁免 */
    return !ecod_license_is_local_host();
}

/** Ed25519 支持是否可用 */
function ecod_license_crypto_ok(): bool
{
    return function_exists('sodium_crypto_sign_verify_detached')
        || (function_exists('openssl_verify') && defined('OPENSSL_ALGO_ED25519'));
}

/** 内置公钥转 PEM（openssl_verify 的 Ed25519 分支需要 PEM，不能直接喂 32 字节裸公钥） */
function ecod_license_pubkey_pem(): string
{
    static $pem = null;
    if ($pem !== null) {
        return $pem;
    }
    $raw = base64_decode(ECOD_LIC_PUB, true);
    if ($raw === false || strlen($raw) !== 32) {
        return $pem = '';
    }
    /* SubjectPublicKeyInfo：302a300506032b6570032100 + 32 字节公钥 */
    $der = hex2bin('302a300506032b6570032100') . $raw;
    return $pem = "-----BEGIN PUBLIC KEY-----\n"
        . chunk_split(base64_encode($der), 64, "\n")
        . "-----END PUBLIC KEY-----\n";
}

/** 端点 */
function ecod_license_endpoint(): string
{
    $e = trim((string) ecod_option('ELicApi', ''));
    if ($e !== '') {
        return rtrim($e, '/');
    }
    return ECOD_LIC_ENDPOINT_DEFAULT;
}

/** 读 option 原文 */
function ecod_license_option_value(): string
{
    try {
        $v = \Utils\Helper::options()->EcodLicense;
    } catch (\Throwable $e) {
        return '';
    }
    if (is_array($v)) {
        return (string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    return (string) $v;
}

/** 写 option */
function ecod_license_save_raw(array $state): void
{
    $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return;
    }
    try {
        $db = \Typecho\Db::get();
        $exists = $db->fetchRow(
            $db->select('name')->from('table.options')
                ->where('name = ?', 'EcodLicense')->where('user = ?', 0)
        );
        if ($exists) {
            $db->query($db->update('table.options')->rows(['value' => $json])
                ->where('name = ?', 'EcodLicense')->where('user = ?', 0));
        } else {
            $db->query($db->insert('table.options')->rows([
                'name'  => 'EcodLicense',
                'user'  => 0,
                'value' => $json,
            ]));
        }
    } catch (\Throwable $e) {
        /* 忽略 */
    }
    try {
        \Utils\Helper::options()->EcodLicense = $json;
    } catch (\Throwable $e) {
        /* 忽略 */
    }
}

/** 读取并规范化状态 */
function ecod_license_raw_state(): array
{
    $raw = ecod_license_option_value();
    $state = [];
    if ($raw !== '') {
        $d = json_decode($raw, true);
        if (is_array($d)) {
            $state = $d;
        }
    }
    $defaults = [
        'file'          => '',
        'key'           => '',
        'domain'        => '',
        'edition'       => '',
        'expire'        => 0,
        'modules'       => [],
        'issued'        => 0,
        'status'        => '',
        'customer'      => '',
        'kid'           => '',
        'grace'         => ECOD_LIC_GRACE_DEFAULT,
        'terms'         => 1,
        'activated_at'  => 0,
        'checked_at'    => 0,
        'next_check'    => 0,
        'installed_at'  => 0,
        'max_seen'      => 0,
        'offline_days'  => 0,
        'last_error'    => '',
    ];
    foreach ($defaults as $k => $v) {
        if (!array_key_exists($k, $state)) {
            $state[$k] = $v;
        }
    }
    if (empty($state['installed_at'])) {
        $state['installed_at'] = time();
    }
    if (empty($state['grace'])) {
        $state['grace'] = ECOD_LIC_GRACE_DEFAULT;
    }
    return $state;
}

/** 对外状态（含 mode） */
function ecod_license_state(): array
{
    $state = ecod_license_raw_state();
    $mode = ecod_license_mode();
    $state['mode'] = $mode;
    $state['locked'] = in_array($mode, ['expired', 'domain', 'revoked', 'invalid', 'degraded'], true);
    $state['days_left'] = ecod_license_days_left();
    $state['hint'] = function_exists('ecod_license_mode_hint') ? ecod_license_mode_hint($mode) : '';
    return $state;
}

/** 本地五步验签 */
function ecod_license_verify_file(string $file, int $maxSeen = 0): array
{
    $file = trim($file);
    $parts = explode('.', $file);
    if (count($parts) !== 3 || $parts[0] !== 'ECODLIC1') {
        return ['ok' => false, 'reason' => 'sig', 'payload' => null];
    }
    $json = ecod_lic_b64d($parts[1]);
    $sig = ecod_lic_b64d($parts[2]);
    if ($json === '' || $sig === '') {
        return ['ok' => false, 'reason' => 'sig', 'payload' => null];
    }
    $payload = json_decode($json, true);
    if (!is_array($payload)) {
        return ['ok' => false, 'reason' => 'sig', 'payload' => null];
    }

    /* 1. kid */
    if ((string) ($payload['kid'] ?? '') !== ECOD_LIC_KID) {
        return ['ok' => false, 'reason' => 'kid', 'payload' => $payload];
    }

    /* 2. 签名 */
    $pub = base64_decode(ECOD_LIC_PUB, true);
    $sigOk = false;
    if (function_exists('sodium_crypto_sign_verify_detached')) {
        try {
            $sigOk = sodium_crypto_sign_verify_detached($sig, $json, $pub);
        } catch (\Throwable $e) {
            $sigOk = false;
        }
    } elseif (function_exists('openssl_verify')) {
        $pem = ecod_license_pubkey_pem();
        $sigOk = false;
        if ($pem !== '') {
            $r = @openssl_verify($json, $sig, $pem, OPENSSL_ALGO_ED25519);
            $sigOk = ($r === 1);
        }
    } else {
        return ['ok' => false, 'reason' => 'kid', 'payload' => $payload];
    }
    if (!$sigOk) {
        return ['ok' => false, 'reason' => 'sig', 'payload' => $payload];
    }

    /* 3. 域名 */
    $licensed = ecod_license_host_normalize((string) ($payload['domain'] ?? ''));
    $current = ecod_license_host();
    if ($licensed === '' || $licensed !== $current) {
        return ['ok' => false, 'reason' => 'domain', 'payload' => $payload];
    }

    /* 4. status */
    if ((string) ($payload['status'] ?? '') !== 'active') {
        return ['ok' => false, 'reason' => 'revoked', 'payload' => $payload];
    }

    /* 5. 到期 */
    $expire = (int) ($payload['expire'] ?? 0);
    $now = time();
    if ($expire > 0 && $expire < $now) {
        return ['ok' => false, 'reason' => 'expired', 'payload' => $payload];
    }

    /* 6. 时间回拨 */
    if ($maxSeen > 0 && $now < $maxSeen - 86400) {
        return ['ok' => false, 'reason' => 'rollback', 'payload' => $payload];
    }

    return ['ok' => true, 'reason' => '', 'payload' => $payload];
}

/** 计算 mode */
function ecod_license_mode(): string
{
    $state = ecod_license_raw_state();

    if (!ecod_license_enabled()) {
        return 'off';
    }
    if (!ecod_license_crypto_ok()) {
        return 'nokey';
    }

    $file = trim((string) $state['file']);
    if ($file === '') {
        $installed = (int) $state['installed_at'];
        if (time() < $installed + ECOD_LIC_TRIAL_DAYS * 86400) {
            return 'trial';
        }
        return 'expired';
    }

    $v = ecod_license_verify_file($file, (int) $state['max_seen']);
    if (!$v['ok']) {
        $r = $v['reason'];
        if ($r === 'domain') {
            return 'domain';
        }
        if ($r === 'revoked') {
            return 'revoked';
        }
        if ($r === 'expired') {
            return 'expired';
        }
        return 'invalid';
    }

    /* 在线复验发现的吊销 / 到期要立刻生效：本地文件还是旧的，但状态已经不可信 */
    $stateStatus = (string) ($state['status'] ?? 'active');
    if ($stateStatus !== '' && $stateStatus !== 'active') {
        if ($stateStatus === 'revoked' || $stateStatus === 'domain' || $stateStatus === 'expired') {
            return $stateStatus;
        }
        return 'invalid';
    }
    $stateExpire = (int) $state['expire'];
    if ($stateExpire > 0 && $stateExpire < time()) {
        return 'expired';
    }

    $offline = (int) $state['offline_days'];
    $grace = (int) $state['grace'];
    if ($grace <= 0) {
        $grace = ECOD_LIC_GRACE_DEFAULT;
    }
    $lastErr = (string) $state['last_error'];

    /* 联网复验失败：宽限期内照常可用（后台黄条提醒）；
       超过 grace 天仍未连上作者端，再给 14 天（ECOD_LIC_OFFLINE_EXTRA），
       之后进入 degraded 降级锁站 —— 网络问题不该立刻罚站，也不能无限期离线。 */
    if ($lastErr !== '' && $offline > 0) {
        if ($offline > $grace + 14) {
            return 'degraded';
        }
        if ($offline > $grace) {
            return 'grace';
        }
    }

    $next = (int) $state['next_check'];
    if ($next > 0 && time() > $next) {
        return 'stale';
    }

    return 'licensed';
}

/** 是否锁站 */
function ecod_license_locked(): bool
{
    return in_array(ecod_license_mode(), ['expired', 'domain', 'revoked', 'invalid', 'degraded'], true);
}

/**
 * 模块级授权（payload.modules）
 *
 * - 授权模式关闭、试用期、锁死状态：不在这里拦截（锁死由 gate 负责）；
 * - 旧授权文件没有 modules 字段：视为全开，保证向后兼容；
 * - 只有 payload 明确写 false 的模块才禁用。
 */
function ecod_license_allows(string $module): bool
{
    if (!ecod_license_enabled()) {
        return true;
    }
    if (!in_array(ecod_license_mode(), ['licensed', 'stale', 'grace'], true)) {
        return true;
    }
    $state = ecod_license_raw_state();
    $mods = $state['modules'] ?? [];
    if (!is_array($mods) || !$mods) {
        return true;
    }
    return !empty($mods[$module]);
}

/** 距到期天数（永久 = -1） */
function ecod_license_days_left(): int
{
    $state = ecod_license_raw_state();
    $expire = (int) $state['expire'];
    if ($expire <= 0) {
        return -1;
    }
    return (int) floor(($expire - time()) / 86400);
}

/** 是否处于「即将到期」提醒窗口（15 / 7 / 1 天） */
function ecod_license_expiring(): bool
{
    $days = ecod_license_days_left();
    if ($days < 0) {
        return false;
    }
    return $days <= 15;
}

/* ------------------------------------------------------------------
 * HTTP
 * ---------------------------------------------------------------- */

function ecod_license_http_post(string $url, array $data, int $timeout = 10): ?string
{
    if (!function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $out = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    /* curl_close 自 PHP 8.0 起为无效果空操作、8.5 起弃用（Deprecated 会污染 JSON 响应） */
    if ($out === false || $code < 200 || $code >= 300) {
        return null;
    }
    return (string) $out;
}

/** 端点 host 与当前站点一致时跟随当前请求协议（本地 http 环境连自己不能走 https） */
function ecod_license_endpoint_resolved(): string
{
    $url = ecod_license_endpoint();
    $host = parse_url($url, PHP_URL_HOST);
    $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?: 'https');
    if ($host && strcasecmp($host, ecod_license_host()) === 0) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
            || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $scheme = $isHttps ? 'https' : 'http';
        $url = preg_replace('#^[a-z]+://#i', $scheme . '://', $url);
    }
    return $url;
}

function ecod_license_instance(): string
{
    try {
        $url = (string) \Utils\Helper::options()->siteUrl;
    } catch (\Throwable $e) {
        $url = '';
    }
    return md5($url);
}

function ecod_license_site_url(): string
{
    try {
        return rtrim((string) \Utils\Helper::options()->siteUrl, '/');
    } catch (\Throwable $e) {
        return '';
    }
}

/* ------------------------------------------------------------------
 * 激活 / 导入
 * ---------------------------------------------------------------- */

function ecod_license_activate(string $key, string $hostOverride = ''): array
{
    if (!ecod_license_crypto_ok()) {
        return ['ok' => false, 'message' => '本服务器缺少 Ed25519 支持，无法验签'];
    }
    if (!ecod_license_enabled()) {
        return ['ok' => false, 'message' => '本站未开启授权模式'];
    }
    $key = strtoupper(trim($key));
    if ($key === '') {
        return ['ok' => false, 'message' => '请输入授权码'];
    }
    $host = $hostOverride !== '' ? ecod_license_host_normalize($hostOverride) : ecod_license_host();
    if ($host === '') {
        return ['ok' => false, 'message' => '无法识别本站域名'];
    }

    $state = ecod_license_raw_state();
    $now = time();
    $ver = defined('ECOD_VERSION') ? ECOD_VERSION : '1.0';

    $resp = ecod_license_http_post(ecod_license_endpoint_resolved(), [
        'key'    => $key,
        'domain' => $host,
        'ver'    => $ver,
        'inst'   => ecod_license_instance(),
        'url'    => ecod_license_site_url(),
    ], 10);

    if ($resp === null) {
        return ['ok' => false, 'message' => '无法连接授权服务器，请稍后重试'];
    }
    $data = json_decode($resp, true);
    if (!is_array($data) || empty($data['ok'])) {
        $err = is_array($data) ? (string) ($data['error'] ?? '激活失败') : '激活失败';
        return ['ok' => false, 'message' => $err];
    }

    $file = (string) ($data['file'] ?? '');
    $v = ecod_license_verify_file($file, (int) $state['max_seen']);
    if (!$v['ok']) {
        return ['ok' => false, 'message' => '授权文件校验失败：' . $v['reason']];
    }
    $payload = $v['payload'];
    $grace = (int) ($data['grace'] ?? ($payload['grace'] ?? ECOD_LIC_GRACE_DEFAULT));
    if ($grace <= 0) {
        $grace = ECOD_LIC_GRACE_DEFAULT;
    }

    $expire = (int) ($payload['expire'] ?? 0);
    $newState = [
        'file'         => $file,
        'key'          => (string) ($payload['key'] ?? $key),
        'domain'       => (string) ($payload['domain'] ?? $host),
        'edition'      => (string) ($payload['edition'] ?? 'pro'),
        'expire'       => $expire,
        'modules'      => is_array($payload['modules'] ?? null) ? $payload['modules'] : [],
        'issued'       => (int) ($payload['issued'] ?? 0),
        'status'       => (string) ($payload['status'] ?? 'active'),
        'customer'     => (string) ($payload['customer'] ?? ''),
        'kid'          => (string) ($payload['kid'] ?? ECOD_LIC_KID),
        'grace'        => $grace,
        'terms'        => (int) ($payload['terms'] ?? 1),
        'activated_at' => $now,
        'checked_at'   => $now,
        'next_check'   => $now + 7 * 86400,
        'installed_at' => (int) $state['installed_at'] ?: $now,
        'max_seen'     => max((int) $state['max_seen'], $now),
        'offline_days' => 0,
        'last_error'   => '',
    ];
    ecod_license_save_raw($newState);

    return ['ok' => true, 'message' => '激活成功'];
}

function ecod_license_import(string $file): array
{
    if (!ecod_license_crypto_ok()) {
        return ['ok' => false, 'message' => '本服务器缺少 Ed25519 支持，无法验签'];
    }
    $file = trim($file);
    if ($file === '') {
        return ['ok' => false, 'message' => '请粘贴授权文件'];
    }
    $state = ecod_license_raw_state();
    $v = ecod_license_verify_file($file, (int) $state['max_seen']);
    if (!$v['ok']) {
        return ['ok' => false, 'message' => '授权文件校验失败：' . $v['reason']];
    }
    $payload = $v['payload'];
    $now = time();
    $expire = (int) ($payload['expire'] ?? 0);
    $grace = (int) ($payload['grace'] ?? ECOD_LIC_GRACE_DEFAULT);
    if ($grace <= 0) {
        $grace = ECOD_LIC_GRACE_DEFAULT;
    }
    $newState = [
        'file'         => $file,
        'key'          => (string) ($payload['key'] ?? ''),
        'domain'       => (string) ($payload['domain'] ?? ''),
        'edition'      => (string) ($payload['edition'] ?? 'pro'),
        'expire'       => $expire,
        'modules'      => is_array($payload['modules'] ?? null) ? $payload['modules'] : [],
        'issued'       => (int) ($payload['issued'] ?? 0),
        'status'       => (string) ($payload['status'] ?? 'active'),
        'customer'     => (string) ($payload['customer'] ?? ''),
        'kid'          => (string) ($payload['kid'] ?? ECOD_LIC_KID),
        'grace'        => $grace,
        'terms'        => (int) ($payload['terms'] ?? 1),
        'activated_at' => $now,
        'checked_at'   => $now,
        'next_check'   => $now + 7 * 86400,
        'installed_at' => (int) $state['installed_at'] ?: $now,
        'max_seen'     => max((int) $state['max_seen'], $now),
        'offline_days' => 0,
        'last_error'   => '',
    ];
    ecod_license_save_raw($newState);

    return ['ok' => true, 'message' => '导入成功'];
}

/* ------------------------------------------------------------------
 * 复验
 * ---------------------------------------------------------------- */

function ecod_license_recheck(bool $force = false): void
{
    $state = ecod_license_raw_state();
    $file = trim((string) $state['file']);
    if ($file === '' || !ecod_license_enabled()) {
        return;
    }
    $now = time();
    $next = (int) $state['next_check'];
    if (!$force && $next > 0 && $now < $next) {
        return;
    }
    if (!$force && $next === 0) {
        return;
    }

    $key = (string) $state['key'];
    $currentHost = ecod_license_host();
    $ver = defined('ECOD_VERSION') ? ECOD_VERSION : '1.0';

    $resp = ecod_license_http_post(ecod_license_endpoint_resolved(), [
        'key'    => $key,
        'domain' => $currentHost,
        'ver'    => $ver,
        'inst'   => ecod_license_instance(),
        'url'    => ecod_license_site_url(),
    ], 3);

    if ($resp === null) {
        $offline = ecod_license_offline_days($state, $now);
        $backoff = 6 * 3600;
        if ($offline >= 2) {
            $backoff = 12 * 3600;
        }
        if ($offline >= 4) {
            $backoff = 24 * 3600;
        }
        $state['offline_days'] = $offline;
        $state['last_error'] = 'network';
        $state['next_check'] = $now + $backoff;
        $state['max_seen'] = max((int) $state['max_seen'], $now);
        ecod_license_save_raw($state);
        return;
    }

    $data = json_decode($resp, true);
    if (!is_array($data)) {
        $state['offline_days'] = ecod_license_offline_days($state, $now);
        $state['last_error'] = 'bad_response';
        $state['next_check'] = $now + 6 * 3600;
        $state['max_seen'] = max((int) $state['max_seen'], $now);
        ecod_license_save_raw($state);
        return;
    }

    if (empty($data['ok'])) {
        $err = (string) ($data['error'] ?? 'invalid');
        $state['last_error'] = $err;
        if (stripos($err, '吊销') !== false) {
            $state['status'] = 'revoked';
        } elseif (stripos($err, '到期') !== false) {
            $state['expire'] = 1;
        } elseif (stripos($err, '域名') !== false) {
            $state['status'] = 'domain';
        }
        $state['offline_days'] = 0;
        $state['next_check'] = $now + 6 * 3600;
        $state['max_seen'] = max((int) $state['max_seen'], $now);
        ecod_license_save_raw($state);
        return;
    }

    $file = (string) ($data['file'] ?? $state['file']);
    $v = ecod_license_verify_file($file, (int) $state['max_seen']);
    if ($v['ok']) {
        $payload = $v['payload'];
        $state['file'] = $file;
        $state['key'] = (string) ($payload['key'] ?? $state['key']);
        $state['domain'] = (string) ($payload['domain'] ?? $state['domain']);
        $state['edition'] = (string) ($payload['edition'] ?? $state['edition']);
        $state['expire'] = (int) ($payload['expire'] ?? $state['expire']);
        $state['status'] = (string) ($payload['status'] ?? 'active');
        $grace = (int) ($data['grace'] ?? ($payload['grace'] ?? $state['grace']));
        if ($grace <= 0) {
            $grace = ECOD_LIC_GRACE_DEFAULT;
        }
        $state['grace'] = $grace;
        $state['checked_at'] = $now;
        $state['offline_days'] = 0;
        $state['last_error'] = '';
        $state['max_seen'] = max((int) $state['max_seen'], $now);
        /* 每 7 天复验一次（到期由本地验签即时判定，不依赖联网） */
        $state['next_check'] = $now + 7 * 86400;
    } else {
        $state['last_error'] = 'verify_failed';
        $state['next_check'] = $now + 6 * 3600;
    }
    ecod_license_save_raw($state);
}

/** 离线天数：按最后一次复验成功的时间算，避免重试次数把宽限期提前耗尽 */
function ecod_license_offline_days(array $state, int $now): int
{
    $base = (int) ($state['checked_at'] ?? 0);
    if ($base <= 0) {
        $base = (int) ($state['activated_at'] ?? 0);
    }
    if ($base <= 0) {
        $base = (int) ($state['installed_at'] ?? 0);
    }
    if ($base <= 0) {
        return max(0, (int) ($state['offline_days'] ?? 0));
    }
    return max(0, (int) floor(($now - $base) / 86400));
}

function ecod_license_schedule_recheck(): void
{
    $mode = ecod_license_mode();
    if (in_array($mode, ['stale', 'grace', 'degraded'], true)) {
        register_shutdown_function(function () {
            /* php-fpm：先把响应发给用户，再在后台复验（用户无感） */
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            ecod_license_recheck(false);
        });
    }
}

/* ------------------------------------------------------------------
 * 锁死门禁
 * ---------------------------------------------------------------- */

function ecod_license_gate($archive = null): void
{
    $state = ecod_license_raw_state();
    if (empty($state['installed_at'])) {
        $state['installed_at'] = time();
        ecod_license_save_raw($state);
    }

    $mode = ecod_license_mode();
    $admin = ecod_license_is_admin();

    if (in_array($mode, ['expired', 'domain', 'revoked', 'invalid', 'degraded'], true)) {
        if ($admin) {
            $GLOBALS['ecod_license_admin_exempt'] = true;
            /* 管理员豁免但保留感知：全屏大弹窗（可关闭）+ 底部提示条，不挡自救浏览 */
            ecod_license_start_overlay(true);
        } else {
            /* 状态码必须走 Typecho Response：渲染阶段 sendHeaders() 会用
               Response 单例的 status（默认 200）重发状态行，裸
               http_response_code(503) 会被它覆盖成 200 */
            try {
                \Typecho\Response::getInstance()->setStatus(503);
            } catch (\Throwable $e) {
                http_response_code(503);
            }
            header('Retry-After: 3600');
            header('X-Robots-Tag: noindex');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            /* 全页锁定：页面照常渲染，输出前注入全屏遮罩（背景虚化）+ 大弹窗；
               非 HTML / PJAX 碎片回退为独立锁页 */
            ecod_license_start_overlay(false);
        }
    }

    ecod_license_schedule_recheck();
}

/* ------------------------------------------------------------------
 * 全页锁定遮罩：页面照常渲染，输出前注入全屏遮罩（背景虚化）+ 大弹窗
 * ---------------------------------------------------------------- */

/** 锁死模式 → 弹窗标题 */
function ecod_license_overlay_title(string $mode): string
{
    switch ($mode) {
        case 'expired':    return '主题授权已到期';
        case 'domain':     return '授权域名与站点不匹配';
        case 'revoked':    return '主题授权已被吊销';
        case 'degraded':   return '授权长期离线，已降级锁定';
        default:           return '主题授权校验失败';
    }
}

/** 开启输出缓冲，渲染结束后注入遮罩（$admin=true 时弹窗可关闭） */
function ecod_license_start_overlay(bool $admin): void
{
    ob_start(function (string $html) use ($admin): string {
        try {
            return ecod_license_overlay_callback($html, $admin);
        } catch (\Throwable $e) {
            header('X-Lic-Err: ' . substr(str_replace(array("\n", "\r"), ' ', $e->getMessage()), 0, 200) . ' @' . basename($e->getFile()) . ':' . $e->getLine());
            return '';
        }
    });
}

/** 遮罩回调：按访客 / 管理员、完整 HTML / PJAX 碎片分流 */
function ecod_license_overlay_callback(string $html, bool $admin): string
{
    $isPjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'Ecod-PJAX';
    $hasBody = stripos($html, '</body>') !== false;

    /* 访客 + PJAX 碎片 / 非 HTML（feed 等）：整页替换为独立锁页，
       PJAX 端发现没有 #ecod-shell 会自动整页刷新到锁页。
       注意回调是 display handler，内部禁止再开 ob，必须用纯字符串版本 */
    if (!$admin && ($isPjax || !$hasBody)) {
        return ecod_license_block_html();
    }
    /* 管理员 + 非 HTML：原样放行，别把 feed 弄坏 */
    if ($admin && !$hasBody && !$isPjax) {
        return $html;
    }
    return ecod_license_inject_overlay($html, $admin, $isPjax);
}

/** 生成遮罩（样式 + 弹窗）并注入 HTML */
function ecod_license_inject_overlay(string $html, bool $admin, bool $isPjax): string
{
    $mode = ecod_license_mode();
    $siteName = '';
    $adminUrl = '';
    try {
        $options = \Utils\Helper::options();
        $siteName = trim((string) $options->title);
        $adminUrl = rtrim((string) $options->adminUrl, '/');
    } catch (\Throwable $e) {
    }
    $siteName = $siteName !== '' ? $siteName : '本站';
    $pingUrl = ecod_license_site_url() . '/api.php?action=license_ping&_=' . time();

    $style = <<<'CSS'
    #ecod-licov{position:fixed;inset:0;z-index:2147483000;display:flex;align-items:center;justify-content:center;
      padding:24px;box-sizing:border-box;background:rgba(15,18,28,.38);
      -webkit-backdrop-filter:blur(18px) saturate(1.05);backdrop-filter:blur(18px) saturate(1.05);
      font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Hiragino Sans GB","Microsoft YaHei",sans-serif;}
    #ecod-licov *{box-sizing:border-box}
    @media (prefers-color-scheme: dark){#ecod-licov{background:rgba(5,7,14,.5)}}
    #ecod-licov.is-visitor{position:fixed}
    html.ecod-licov-lock,html.ecod-licov-lock body{overflow:hidden!important}
    #ecod-licov__card{max-width:520px;width:100%;background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:22px;
      box-shadow:0 24px 80px rgba(0,0,0,.28),0 4px 16px rgba(0,0,0,.12);padding:46px 42px 38px;text-align:center;color:#1f2937}
    @media (prefers-color-scheme: dark){#ecod-licov__card{background:#161b29;border-color:rgba(255,255,255,.09);color:#e5e7eb}}
    @media (max-width:480px){#ecod-licov__card{padding:36px 24px 30px;border-radius:18px}}
    #ecod-licov__brand{font-size:12.5px;font-weight:600;letter-spacing:.06em;color:#6b7280;margin:0 0 20px}
    @media (prefers-color-scheme: dark){#ecod-licov__brand{color:#9aa4b2}}
    #ecod-licov__ic{width:64px;height:64px;margin:0 auto 18px;border-radius:50%;display:flex;align-items:center;justify-content:center;
      background:#fdecec;color:#dc2626}
    #ecod-licov.is-warn #ecod-licov__ic{background:#fef3e2;color:#d97706}
    #ecod-licov__ic svg{width:30px;height:30px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
    #ecod-licov__title{font-size:19px;font-weight:700;margin:0 0 10px;letter-spacing:-.01em;line-height:1.4}
    #ecod-licov__desc{font-size:13.5px;color:#6b7280;line-height:1.75;margin:0 0 24px}
    @media (prefers-color-scheme: dark){#ecod-licov__desc{color:#9aa4b2}}
    #ecod-licov__ops{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
    #ecod-licov__ops a,#ecod-licov__ops button{display:inline-flex;align-items:center;justify-content:center;gap:6px;
      height:40px;padding:0 22px;border-radius:999px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;
      border:1px solid rgba(15,23,42,.12);background:transparent;color:#374151;font-family:inherit}
    #ecod-licov__ops a:hover,#ecod-licov__ops button:hover{border-color:rgba(15,23,42,.3);color:#111827}
    #ecod-licov__ops .is-primary{background:#dc2626;border-color:#dc2626;color:#fff}
    #ecod-licov__ops .is-primary:hover{background:#b91c1c;border-color:#b91c1c;color:#fff}
    @media (prefers-color-scheme: dark){#ecod-licov__ops a,#ecod-licov__ops button{border-color:rgba(255,255,255,.16);color:#cbd5e1}
      #ecod-licov__ops a:hover,#ecod-licov__ops button:hover{color:#fff;border-color:rgba(255,255,255,.4)}}
    #ecod-licov__foot{font-size:11.5px;color:#9ca3af;margin:22px 0 0}
    @media (prefers-color-scheme: dark){#ecod-licov__foot{color:#6b7280}}
    CSS;
    $style = '<style>' . preg_replace('/\s+/', ' ', $style) . '</style>';

    $ic = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';

    if ($admin) {
        $hint = htmlspecialchars(ecod_license_mode_hint($mode), ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars(ecod_license_admin_url(), ENT_QUOTES, 'UTF-8');
        $body = '<p id="ecod-licov__brand">' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<div id="ecod-licov__ic">' . $ic . '</div>'
            . '<h1 id="ecod-licov__title">' . htmlspecialchars(ecod_license_overlay_title($mode), ENT_QUOTES, 'UTF-8') . '</h1>'
            . '<p id="ecod-licov__desc">' . $hint . '<br>访客看到的是全页锁定；你已登录管理员，可关闭弹窗继续浏览与调试。</p>'
            . '<div id="ecod-licov__ops">'
            . '<a class="is-primary" href="' . $url . '">前往激活</a>'
            . '<button type="button" id="ecod-licov__x">继续浏览</button>'
            . '</div>'
            . '<p id="ecod-licov__foot">激活成功后刷新本页即恢复访问。</p>';
        $script = '<script>(function(){var o=document.getElementById("ecod-licov");if(!o)return;'
            . 'try{if(sessionStorage.getItem("ecodLicOv")==="1"){o.style.display="none";}}catch(e){}'
            . 'var x=document.getElementById("ecod-licov__x");if(x)x.onclick=function(){o.style.display="none";'
            . 'try{sessionStorage.setItem("ecodLicOv","1")}catch(e){}};})();</script>';
    } else {
        $login = $adminUrl !== ''
            ? $adminUrl . '/login.php?referer=' . rawurlencode(ecod_license_site_url() . '/admin/options-theme.php#license')
            : '';
        $body = '<p id="ecod-licov__brand">' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<div id="ecod-licov__ic">' . $ic . '</div>'
            . '<h1 id="ecod-licov__title">本站正在完成授权配置，稍后恢复访问</h1>'
            . '<p id="ecod-licov__desc">我们正在完成站点授权校验，服务将在几分钟内自动恢复。感谢您的耐心等待。</p>'
            . '<div id="ecod-licov__ops">'
            . ($login !== '' ? '<a href="' . htmlspecialchars($login, ENT_QUOTES, 'UTF-8') . '">管理员登录</a>' : '')
            . '</div>'
            . '<p id="ecod-licov__foot">如长时间未恢复，请联系站点管理员。</p>';
        $script = '';
    }

    $overlay = $style
        . '<div id="ecod-licov" class="' . ($admin ? 'is-warn' : 'is-visitor') . '" role="dialog" aria-modal="true" aria-label="站点授权提示">'
        . '<div id="ecod-licov__card">' . $body . '</div></div>' . $script
        . '<img src="' . htmlspecialchars($pingUrl, ENT_QUOTES, 'UTF-8') . '" width="1" height="1" alt="" style="position:absolute;left:-9999px;top:-9999px" />';

    if ($isPjax) {
        /* PJAX 碎片没有 body：注入到 shell 容器开头，替换后照样生效 */
        $pos = strpos($html, 'id="ecod-shell"');
        if ($pos !== false) {
            $tagEnd = strpos($html, '>', $pos);
            if ($tagEnd !== false) {
                return substr($html, 0, $tagEnd + 1) . $overlay . substr($html, $tagEnd + 1);
            }
        }
        return $html . $overlay;
    }

    /* 完整 HTML：访客补 noindex + 锁滚动；弹窗放 body 尾部 */
    if (stripos($html, '</head>') !== false) {
        $meta = $admin ? '' : '<meta name="robots" content="noindex, nofollow">';
        $html = substr_replace($html, $meta, strripos($html, '</head>'), 0);
    }
    if (!$admin) {
        /* 访客：禁止滚动，页面彻底锁住 */
        $overlay = '<script>try{document.documentElement.className+=" ecod-licov-lock"}catch(e){}</script>' . $overlay;
    }
    if (strripos($html, '</body>') !== false) {
        return substr_replace($html, $overlay, strripos($html, '</body>'), 0);
    }
    return $html . $overlay;
}

function ecod_license_admin_exempt(): bool
{
    return !empty($GLOBALS['ecod_license_admin_exempt']);
}

/** 后台主题设置页（授权面板锚点） */
function ecod_license_admin_url(): string
{
    try {
        $base = rtrim((string) \Utils\Helper::options()->adminUrl, '/');
    } catch (\Throwable $e) {
        $base = '';
    }
    return $base . '/options-theme.php#license';
}

/** 给管理员看的状态说明 */
function ecod_license_mode_hint(string $mode): string
{
    $state = ecod_license_raw_state();
    switch ($mode) {
        case 'trial':
            $left = max(0, ECOD_LIC_TRIAL_DAYS - (int) floor((time() - (int) $state['installed_at']) / 86400));
            return '试用期剩余 ' . $left . ' 天，请尽快激活授权。';
        case 'stale':
            return '授权待复验，系统会在后台自动重试。';
        case 'grace':
            return '已离线 ' . (int) $state['offline_days'] . ' 天未连上授权服务器，请检查网络或授权端点。';
        case 'degraded':
            return '离线超过宽限期，前台已降级锁定，请恢复联网后点「重新复验」。';
        case 'expired':
            return '授权已到期（或试用期结束），前台已锁定。';
        case 'domain':
            return '授权域名与当前站点不一致，请换绑后重新激活。';
        case 'revoked':
            return '授权已被作者吊销，前台已锁定。';
        case 'invalid':
            return '授权文件无效或系统时间异常，请重新激活 / 导入授权文件。';
        case 'nokey':
            return '服务器缺少 Ed25519 扩展（sodium / openssl），无法本地验签。';
        case 'licensed':
            $days = ecod_license_days_left();
            return $days >= 0 ? '授权有效，剩余 ' . $days . ' 天。' : '授权有效（永久）。';
    }
    return '';
}

function ecod_license_admin_bar(): void
{
    if (!ecod_license_is_admin()) {
        return;
    }
    $mode = ecod_license_mode();
    if ($mode === 'off') {
        return;
    }
    /* licensed / stale 只在临近到期时提醒，避免长期黄条骚扰 */
    if (($mode === 'licensed' || $mode === 'stale') && !ecod_license_expiring()) {
        return;
    }
    $locked = ecod_license_locked();
    $bg = $locked ? '#fde8e8' : '#fff3cd';
    $fg = $locked ? '#842029' : '#664d03';
    $bd = $locked ? '#f5c2c7' : '#ffecb5';
    $url = htmlspecialchars(ecod_license_admin_url(), ENT_QUOTES, 'UTF-8');
    echo '<div style="position:fixed;left:0;right:0;bottom:0;z-index:99999;background:' . $bg . ';color:' . $fg
        . ';border-top:1px solid ' . $bd . ';padding:10px 16px;font-size:13px;text-align:center;">'
        . htmlspecialchars('【授权】' . ecod_license_mode_hint($mode), ENT_QUOTES, 'UTF-8')
        . ' <a href="' . $url . '" style="color:inherit;font-weight:600;text-decoration:underline;">前往处理</a>'
        . '</div>';
}

function ecod_license_api_gate(string $action): bool
{
    if (!ecod_license_locked()) {
        return true;
    }
    if (strpos($action, 'license_') === 0 || strpos($action, 'account_') === 0 || $action === 'ping') {
        return true;
    }
    $method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method === 'GET') {
        return true;
    }
    return false;
}

/* ------------------------------------------------------------------
 * ping
 * ---------------------------------------------------------------- */

function ecod_license_ping(): void
{
    ecod_license_recheck(true);
    header('Content-Type: image/gif');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}
