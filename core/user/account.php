<?php
/**
 * Ecod Theme · 账户能力共享层
 *
 * 前台登录（api.php?action=login）与个人中心资料 / 头像修改共用。
 * 这里只放「api.php 独立入口」和「主题模板」两边都要用的东西：
 * CSRF 令牌、自定义头像表读写、头像存储目录。
 *
 * 依赖 Typecho 内核（__TYPECHO_ROOT_DIR__ + Db），不依赖主题其他文件。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * CSRF 令牌：复用 Typecho 内置 Security 机制（secret + 登录态），固定后缀
 */
function ecod_account_token(): string
{
    try {
        return (string) \Widget\Security::alloc()->getToken('ecod-account');
    } catch (\Throwable $e) {
        return '';
    }
}

/**
 * 校验 CSRF 令牌（api.php 侧）
 */
function ecod_account_token_ok(string $token): bool
{
    $expect = ecod_account_token();
    return $expect !== '' && $token !== '' && hash_equals($expect, $token);
}

/**
 * 敏感操作审计日志
 *
 * 支付结算、快照恢复、卡密生成、权益手工授予这类动作写一行
 * error_log（action + 操作者 uid + 来源 IP + 关键参数），出问题时
 * 可在服务器日志里回溯。fail-silent：日志不可用不影响业务。
 */
function ecod_audit_log(string $action, array $ctx = []): void
{
    $uid = 0;
    try {
        $user = \Widget\User::alloc();
        if ($user->hasLogin()) {
            $uid = (int) $user->uid;
        }
    } catch (\Throwable $e) {
    }
    $parts = [];
    foreach ($ctx as $k => $v) {
        $parts[] = $k . '=' . str_replace(["\n", "\r", ' '], '_', (string) $v);
    }
    error_log(sprintf(
        '[ecod-audit] action=%s uid=%d ip=%s%s',
        $action,
        $uid,
        (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        $parts ? ' ' . implode(' ', $parts) : ''
    ));
}

/**
 * 自定义头像表名
 */
function ecod_account_avatar_table(): string
{
    try {
        return \Typecho\Db::get()->getPrefix() . 'ecod_user_avatar';
    } catch (\Throwable $e) {
        return 'typecho_ecod_user_avatar';
    }
}

/**
 * 头像文件目录（usr/uploads/ecod-avatar）
 */
function ecod_account_avatar_dir(): string
{
    return rtrim(__TYPECHO_ROOT_DIR__, '/') . '/usr/uploads/ecod-avatar';
}

/**
 * 确保头像表存在（api.php 独立入口不走主题初始化，这里自愈一次）
 */
function ecod_account_avatars_ready(): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    if (!function_exists('ecod_schema_ensure_avatars')) {
        require_once __DIR__ . '/../lib/schema.php';
    }
    try {
        $ok = ecod_schema_ensure_avatars(\Typecho\Db::get());
    } catch (\Throwable $e) {
        $ok = false;
    }
    return $ok;
}

/**
 * 由头像地址反推服务器文件路径；非本主题上传的地址返回空串
 */
function ecod_account_avatar_file(string $url): string
{
    $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
    if (strpos($path, '/usr/uploads/ecod-avatar/') !== 0) {
        return '';
    }
    return rtrim(__TYPECHO_ROOT_DIR__, '/') . $path;
}

/**
 * 某用户的自定义头像地址（站点相对路径），未设置返回空串
 */
function ecod_account_avatar_url(int $uid): string
{
    if ($uid <= 0) {
        return '';
    }
    static $cache = [];
    if (array_key_exists($uid, $cache)) {
        return $cache[$uid];
    }
    if (!ecod_account_avatars_ready()) {
        return '';
    }
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('url')->from(ecod_account_avatar_table())
                ->where('uid = ?', $uid)->limit(1)
        );
        return $cache[$uid] = ($row && isset($row['url'])) ? (string) $row['url'] : '';
    } catch (\Throwable $e) {
        return '';
    }
}

/**
 * 写入 / 覆盖自定义头像（url 为站点相对路径，如 /usr/uploads/ecod-avatar/1.jpg）
 */
function ecod_account_set_avatar(int $uid, string $url): bool
{
    if (!ecod_account_avatars_ready()) {
        return false;
    }
    try {
        $db = \Typecho\Db::get();
        $table = ecod_account_avatar_table();
        $exists = $db->fetchRow(
            $db->select('uid')->from($table)->where('uid = ?', $uid)->limit(1)
        );
        if ($exists) {
            $db->query(
                $db->update($table)->rows(['url' => $url, 'created' => time()])
                    ->where('uid = ?', $uid)
            );
        } else {
            $db->query(
                $db->insert($table)->rows(['uid' => $uid, 'url' => $url, 'created' => time()])
            );
        }
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * 删除自定义头像（记录 + 文件），回落到本地随机头像池
 */
function ecod_account_del_avatar(int $uid): bool
{
    if (!ecod_account_avatars_ready()) {
        return false;
    }
    $url = ecod_account_avatar_url($uid);
    if ($url !== '') {
        $file = ecod_account_avatar_file($url);
        if ($file !== '' && is_file($file)) {
            @unlink($file);
        }
    }
    try {
        $db = \Typecho\Db::get();
        $db->query($db->delete(ecod_account_avatar_table())->where('uid = ?', $uid));
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

/* ------------------------------------------------------------------
 * 通用工具：读主题配置（独立入口下 ecod_option 不存在，这里自取）
 * ---------------------------------------------------------------- */

/**
 * 读一条主题配置；兼容模板与 api.php 独立入口两种上下文
 */
function ecod_account_opt(string $name, $default = '')
{
    if (function_exists('ecod_option')) {
        return ecod_option($name, $default);
    }
    try {
        $options = \Utils\Helper::options();
        return isset($options->{$name}) && (string) $options->{$name} !== ''
            ? $options->{$name} : $default;
    } catch (\Throwable $e) {
        return $default;
    }
}

/* ------------------------------------------------------------------
 * 分级限流：防爆破 / 防刷验证码
 *
 * 前几名免费，越错越慢：1-5 次不限 → 6-10 次每次要隔 30 秒
 * → 11 次起每次要隔 180 秒；动作成功后计数清零。
 * 记账双写：session（同一访客的即时反馈）+ 数据库（键含 IP）。
 * 只靠 session 会被「每次不带 Cookie 的请求」绕过——每次都是新
 * 会话，计数永远从 0 开始；落库这张账不依赖 cookie，任一超限都拦截。
 * ---------------------------------------------------------------- */

/** 持久化计数的自然衰减：每 300 秒返还一次尝试机会，老计数不会永久封死正常访客 */
define('ECOD_THROTTLE_DECAY', 300);

/**
 * 开启 session（api.php 独立入口与模板两侧都要用）
 */
function ecod_throttle_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
}

/**
 * 动作前检查：超过间隔限制时返回需要等待的秒数，否则返回 0
 */
function ecod_throttle_guard(string $bucket): int
{
    ecod_throttle_session();

    $now = time();
    $wait = 0;

    /* 第一本账：session */
    $log = $_SESSION['ecod_throttle'][$bucket] ?? null;
    if (is_array($log)) {
        $n = max(0, (int) ($log['n'] ?? 0));
        if ($n >= 5) {
            $gap = $n >= 10 ? 180 : 30;
            $w = $gap - ($now - (int) ($log['last'] ?? 0));
            $wait = max($wait, $w > 0 ? $w : 0);
        }
    }

    /* 第二本账：数据库（键含 IP，跨 session 生效） */
    $state = ecod_throttle_persistent_state(ecod_throttle_pkey($bucket), $now);
    if ($state !== null && (int) $state['n'] >= 5) {
        $gap = (int) $state['n'] >= 10 ? 180 : 30;
        $w = $gap - ($now - (int) $state['last']);
        $wait = max($wait, $w > 0 ? $w : 0);
    }

    return $wait;
}

/**
 * 动作失败：记账一次（session + 数据库各记一笔）
 */
function ecod_throttle_fail(string $bucket): void
{
    ecod_throttle_session();
    $log = $_SESSION['ecod_throttle'][$bucket] ?? ['n' => 0, 'last' => 0];
    $log['n'] = (int) $log['n'] + 1;
    $log['last'] = time();
    $_SESSION['ecod_throttle'][$bucket] = $log;

    if (ecod_throttle_persistent_ready()) {
        try {
            $db = \Typecho\Db::get();
            $table = ecod_throttle_table();
            $key = ecod_throttle_pkey($bucket);
            $now = time();
            $state = ecod_throttle_persistent_state($key, $now);
            $n = ($state ? (int) $state['n'] : 0) + 1;

            $exists = $db->fetchRow($db->select('thkey')->from($table)->where('thkey = ?', $key)->limit(1));
            if ($exists) {
                $db->query($db->update($table)->rows(['n' => $n, 'last' => $now])->where('thkey = ?', $key));
            } else {
                $db->query($db->insert($table)->rows(['thkey' => $key, 'n' => $n, 'last' => $now]));
            }
        } catch (\Throwable $e) {
            /* 落库失败退回纯 session 限流 */
        }
    }
}

/**
 * 动作成功：两本账都清零
 */
function ecod_throttle_clear(string $bucket): void
{
    ecod_throttle_session();
    unset($_SESSION['ecod_throttle'][$bucket]);

    if (ecod_throttle_persistent_ready()) {
        try {
            \Typecho\Db::get()->query(
                \Typecho\Db::get()->delete(ecod_throttle_table())
                    ->where('thkey = ?', ecod_throttle_pkey($bucket))
            );
        } catch (\Throwable $e) {
            /* 清理失败只影响计数衰减速度 */
        }
    }
}

/**
 * 统一报错文案
 */
function ecod_throttle_error(int $wait): string
{
    return $wait >= 60
        ? '尝试过于频繁，请 ' . ceil($wait / 60) . ' 分钟后再试'
        : '尝试过于频繁，请 ' . max(1, $wait) . ' 秒后再试';
}

/* ---------- 持久化账本（内部实现） ---------- */

/**
 * 持久化账本键：动作桶 + 来源 IP（REMOTE_ADDR 不可伪造，XFF 可被客户端随意填写）
 */
function ecod_throttle_pkey(string $bucket): string
{
    return hash('sha256', $bucket . '|' . (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
}

/**
 * 限流表是否可用（首次调用时自愈建表；不可用时退回纯 session 限流）
 */
function ecod_throttle_persistent_ready(): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    if (!function_exists('ecod_schema_ensure_throttle')) {
        require_once __DIR__ . '/../lib/schema.php';
    }
    try {
        $ok = ecod_schema_ensure_throttle(\Typecho\Db::get());
    } catch (\Throwable $e) {
        $ok = false;
    }
    return $ok;
}

/**
 * 读取衰减后的持久化计数；表不可用时返回 null（调用方跳过这本账）
 *
 * @return array{n:int,last:int}|null
 */
function ecod_throttle_persistent_state(string $key, int $now): ?array
{
    if (!ecod_throttle_persistent_ready()) {
        return null;
    }
    try {
        $row = \Typecho\Db::get()->fetchRow(
            \Typecho\Db::get()->select('n', 'last')->from(ecod_throttle_table())
                ->where('thkey = ?', $key)->limit(1)
        );
        if (!$row) {
            return ['n' => 0, 'last' => 0];
        }
        $refund = intdiv(max(0, $now - (int) $row['last']), ECOD_THROTTLE_DECAY);
        return ['n' => max(0, (int) $row['n'] - $refund), 'last' => (int) $row['last']];
    } catch (\Throwable $e) {
        return null;
    }
}

/* ------------------------------------------------------------------
 * 邮箱验证码：注册留备用，找回密码必用
 * 表 ecod_mail_code：(mail, purpose) 主键；code 只存哈希
 * ---------------------------------------------------------------- */

/**
 * 账户邮件是否具备发送条件（独立开关 EAccountMail，复用评论邮件的 SMTP 账号）
 */
function ecod_account_mail_ready(): bool
{
    if ((string) ecod_account_opt('EAccountMail', 'off') !== 'on') {
        return false;
    }
    return (string) ecod_account_opt('ECommentMailHost', '') !== ''
        && (string) ecod_account_opt('ECommentMailAccount', '') !== ''
        && (string) ecod_account_opt('ECommentMailPassword', '') !== '';
}

/**
 * 确保验证码表存在
 */
function ecod_account_mailcode_ready(): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    if (!function_exists('ecod_schema_ensure_mailcode')) {
        require_once __DIR__ . '/../lib/schema.php';
    }
    try {
        $ok = ecod_schema_ensure_mailcode(\Typecho\Db::get());
    } catch (\Throwable $e) {
        $ok = false;
    }
    return $ok;
}

/**
 * 生成并落库一个验证码（60 秒内不重复发送）
 *
 * @return array{ok:bool,error?:string}
 */
function ecod_account_mailcode_issue(string $mail, string $purpose): array
{
    if (!ecod_account_mailcode_ready()) {
        return ['ok' => false, 'error' => '验证码服务不可用，请稍后再试'];
    }
    try {
        $db = \Typecho\Db::get();
        $table = ecod_mailcode_table();
        $row = $db->fetchRow(
            $db->select('created')->from($table)
                ->where('mail = ?', $mail)->where('purpose = ?', $purpose)->limit(1)
        );
        if ($row && time() - (int) $row['created'] < 60) {
            $wait = 60 - (time() - (int) $row['created']);
            return ['ok' => false, 'error' => '发送太频繁了，请 ' . $wait . ' 秒后再试'];
        }

        try {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } catch (\Throwable $e) {
            $code = str_pad((string) mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        }

        $data = [
            'mail'     => $mail,
            'purpose'  => $purpose,
            'code'     => hash('sha256', $mail . '|' . $purpose . '|' . $code),
            'expires'  => time() + 900,
            'attempts' => 0,
            'created'  => time(),
        ];
        $db->query($db->delete($table)->where('mail = ?', $mail)->where('purpose = ?', $purpose));
        $db->query($db->insert($table)->rows($data));
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => '验证码生成失败，请稍后再试'];
    }

    return ['ok' => true, 'code' => $code];
}

/**
 * 校验验证码：15 分钟有效，错 5 次作废
 */
function ecod_account_mailcode_verify(string $mail, string $purpose, string $code): bool
{
    if (!preg_match('/^\d{6}$/', $code) || !ecod_account_mailcode_ready()) {
        return false;
    }
    try {
        $db = \Typecho\Db::get();
        $table = ecod_mailcode_table();
        $row = $db->fetchRow(
            $db->select('code', 'expires', 'attempts')->from($table)
                ->where('mail = ?', $mail)->where('purpose = ?', $purpose)->limit(1)
        );
        if (!$row || (int) $row['expires'] < time() || (int) $row['attempts'] >= 5) {
            return false;
        }
        $expect = (string) $row['code'];
        if (!hash_equals($expect, hash('sha256', $mail . '|' . $purpose . '|' . $code))) {
            $db->query(
                $db->update($table)->rows(['attempts' => (int) $row['attempts'] + 1])
                    ->where('mail = ?', $mail)->where('purpose = ?', $purpose)
            );
            return false;
        }
        /* 一次性：用完即删 */
        $db->query($db->delete($table)->where('mail = ?', $mail)->where('purpose = ?', $purpose));
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * 发验证码邮件（复用评论邮件的 SMTP 账号，收件人换成用户邮箱）
 */
function ecod_account_mailcode_send(string $mail, string $code, string $purpose): bool
{
    if (!function_exists('ecod_mail_smtp')) {
        require_once __DIR__ . '/../site/mail.php';
    }

    $site = (string) ecod_account_opt('siteUrl', '');
    $title = (string) ecod_account_opt('title', '站点');
    $scene = $purpose === 'register' ? '注册' : '找回密码';

    $subject = '【' . $title . '】' . $scene . '验证码：' . $code;
    $html = '<div style="font:14px/1.7 -apple-system,Segoe UI,PingFang SC,sans-serif;color:#1e2430">'
        . '<p style="margin:0 0 12px">你在 <b>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</b> '
        . $scene . '的验证码是：</p>'
        . '<p style="margin:0 0 12px"><span style="display:inline-block;padding:8px 18px;'
        . 'font-size:22px;letter-spacing:6px;font-weight:700;background:#f2f4f8;border-radius:8px">'
        . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</span></p>'
        . '<p style="margin:0;color:#5b6472">验证码 15 分钟内有效，请勿泄露给他人。'
        . '如果不是你本人的操作，请忽略本邮件。</p></div>';

    $c = [
        'host'   => (string) ecod_account_opt('ECommentMailHost', ''),
        'port'   => max(1, (int) ecod_account_opt('ECommentMailPort', 465)),
        'secure' => (string) ecod_account_opt('ECommentMailSecure', 'ssl'),
        'user'   => (string) ecod_account_opt('ECommentMailAccount', ''),
        'pass'   => (string) ecod_account_opt('ECommentMailPassword', ''),
        'from'   => (string) ecod_account_opt('ECommentMailAccount', ''),
        'name'   => trim((string) ecod_account_opt('ECommentMailFrom', '')) ?: $title,
        'to'     => $mail,
    ];
    unset($site);

    return ecod_mail_smtp($c, $subject, $html);
}

/* ------------------------------------------------------------------
 * 图形验证码（登录用）
 *
 * 4 位字符，去掉 0/O/1/I/S/5/Z/2/B 等易混字符；5 分钟有效，一次性——
 * 校验时无论对错都立刻作废，避免同一张图被反复试探。
 * 答案存 session（限流已开启 session，这里复用同一通道）。
 * ---------------------------------------------------------------- */

/**
 * 字符池（大写，去掉易混淆项）
 */
function ecod_captcha_pool(): string
{
    return 'ACDEFGHJKLMNPQRTUVWXY34679';
}

/**
 * 生成一个新验证码并写入 session，返回明文（仅图片绘制用）
 */
function ecod_captcha_issue(): string
{
    ecod_throttle_session();
    $pool = ecod_captcha_pool();
    $max = strlen($pool) - 1;
    $code = '';
    for ($i = 0; $i < 4; $i++) {
        try {
            $code .= $pool[random_int(0, $max)];
        } catch (\Throwable $e) {
            $code .= $pool[mt_rand(0, $max)];
        }
    }
    $_SESSION['ecod_captcha'] = ['code' => $code, 'exp' => time() + 300];
    return $code;
}

/**
 * 校验验证码（大小写不敏感、忽略空白），一次性消费
 */
function ecod_captcha_verify(string $input): bool
{
    /* 兜底：服务端没有 GD 时根本出不了图，只能放行（持久化限流仍在兜底） */
    if (!function_exists('imagecreatetruecolor')) {
        return true;
    }
    ecod_throttle_session();
    /* GD 在而 session 起不来属于站点故障：验证码没有可比对的答案，
     * 一律拒绝并留下日志，防止静默退化为「验证码形同虚设」 */
    if (session_status() !== PHP_SESSION_ACTIVE) {
        error_log('[ecod-captcha] session unavailable, captcha check refused');
        return false;
    }
    $row = $_SESSION['ecod_captcha'] ?? null;
    unset($_SESSION['ecod_captcha']);
    if (!is_array($row) || (int) ($row['exp'] ?? 0) < time()) {
        return false;
    }
    $input = strtoupper((string) preg_replace('/\s+/', '', $input));
    return $input !== '' && hash_equals((string) ($row['code'] ?? ''), $input);
}

/**
 * 输出验证码 PNG（php-gd）；缺 GD 时返回 1x1 透明图，前端不至于破图
 */
function ecod_captcha_image(int $width = 96, int $height = 40): void
{
    $code = ecod_captcha_issue();

    if (!function_exists('imagecreatetruecolor')) {
        header('Content-Type: image/png');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        return;
    }

    $img = imagecreatetruecolor($width, $height);
    /* 浅底深字：暗色站点里也保持可读 */
    imagefilledrectangle($img, 0, 0, $width, $height, imagecolorallocate($img, 246, 247, 250));

    /* 干扰线（低对比，不遮字） */
    for ($i = 0; $i < 3; $i++) {
        $line = imagecolorallocate($img, 213, 218, 227);
        imageline($img, 0, mt_rand(2, $height - 2), $width, mt_rand(2, $height - 2), $line);
    }
    /* 噪点 */
    for ($i = 0; $i < 70; $i++) {
        $dot = imagecolorallocate($img, mt_rand(188, 216), mt_rand(188, 216), mt_rand(198, 224));
        imagesetpixel($img, mt_rand(0, $width - 1), mt_rand(0, $height - 1), $dot);
    }

    /* 逐字符绘制：9x15 内置字，再放大 1.6 倍贴到主图，带随机纵向抖动 */
    $len = strlen($code);
    $charW = 14;
    $gap = 4;
    $totalW = $len * $charW + ($len - 1) * $gap;
    $startX = (int) max(4, floor(($width - $totalW) / 2));

    for ($i = 0; $i < $len; $i++) {
        $tile = imagecreatetruecolor(9, 15);
        imagefilledrectangle($tile, 0, 0, 9, 15, imagecolorallocate($tile, 246, 247, 250));
        $ink = imagecolorallocate($tile, mt_rand(38, 66), mt_rand(46, 74), mt_rand(58, 88));
        imagestring($tile, 5, 0, 0, $code[$i], $ink);

        $dstX = $startX + $i * ($charW + $gap);
        $dstY = mt_rand(6, 9);
        imagecopyresampled($img, $tile, $dstX, $dstY, 0, 0, $charW, 24, 9, 15);
    }

    header('Content-Type: image/png');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    imagepng($img);
}
