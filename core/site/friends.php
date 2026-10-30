<?php
/**
 * Ecod Theme · 友链申请
 *
 * 访客在友链页弹窗提交申请 → 落库（pending）→ 邮件提醒博主
 * 博主在友链页的「待审核」面板里一键通过 → 自动写入友链页正文
 *
 * 表 ecod_friend_apps：一行一条申请，status = pending / approved / rejected
 * 友链本身仍以页面正文「名称 | 链接 | 描述 | 头像」为准，审核通过只是往正文追加一行，
 * 因此后台编辑器里的内容始终是可读、可手改的唯一真源。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/* 令牌与验证码来自账户层（friends.php 模板要用 ecod_account_token） */
require_once __DIR__ . '/../user/account.php';

/**
 * 读主题选项（模板侧有 functions.php 的 ecod_option，api.php 侧没有）
 */
function ecod_friend_opt(string $key, $default = '')
{
    if (function_exists('ecod_option')) {
        return ecod_option($key, $default);
    }
    try {
        $options = \Utils\Helper::options();
        return isset($options->{$key}) && (string) $options->{$key} !== ''
            ? $options->{$key} : $default;
    } catch (\Throwable $e) {
        return $default;
    }
}

/**
 * 申请表名
 */
function ecod_friend_apps_table(): string{
    try {
        return \Typecho\Db::get()->getPrefix() . 'ecod_friend_apps';
    } catch (\Throwable $e) {
        return 'typecho_ecod_friend_apps';
    }
}

/**
 * 建表（首次写入时自愈，之后每次调用只做一次静态返回）
 */
function ecod_friend_apps_ensure(?\Typecho\Db $db = null): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }

    try {
        $db = $db ?: \Typecho\Db::get();
        $table = ecod_friend_apps_table();
        $adapter = ecod_schema_adapter($db);

        if ($adapter === 'sqlite') {
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $table . '" ('
                . ' "id" INTEGER PRIMARY KEY AUTOINCREMENT,'
                . ' "name" VARCHAR(80) NOT NULL,'
                . ' "url" VARCHAR(255) NOT NULL,'
                . ' "host" VARCHAR(120) NOT NULL,'
                . ' "descr" VARCHAR(255) NOT NULL DEFAULT \'\','
                . ' "avatar" VARCHAR(255) NOT NULL DEFAULT \'\','
                . ' "mail" VARCHAR(120) NOT NULL,'
                . ' "status" VARCHAR(16) NOT NULL DEFAULT \'pending\','
                . ' "ip" VARCHAR(64) NOT NULL DEFAULT \'\','
                . ' "created" INTEGER NOT NULL,'
                . ' "updated" INTEGER NOT NULL)'
            );
            $db->query('CREATE INDEX IF NOT EXISTS "' . $table . '_status" ON "' . $table . '" ("status")');
            $db->query('CREATE INDEX IF NOT EXISTS "' . $table . '_host" ON "' . $table . '" ("host")');
        } elseif ($adapter === 'pgsql') {
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $table . '" ('
                . ' "id" SERIAL PRIMARY KEY,'
                . ' "name" VARCHAR(80) NOT NULL,'
                . ' "url" VARCHAR(255) NOT NULL,'
                . ' "host" VARCHAR(120) NOT NULL,'
                . ' "descr" VARCHAR(255) NOT NULL DEFAULT \'\','
                . ' "avatar" VARCHAR(255) NOT NULL DEFAULT \'\','
                . ' "mail" VARCHAR(120) NOT NULL,'
                . ' "status" VARCHAR(16) NOT NULL DEFAULT \'pending\','
                . ' "ip" VARCHAR(64) NOT NULL DEFAULT \'\','
                . ' "created" INTEGER NOT NULL,'
                . ' "updated" INTEGER NOT NULL)'
            );
            $db->query('CREATE INDEX IF NOT EXISTS "' . $table . '_status" ON "' . $table . '" ("status")');
            $db->query('CREATE INDEX IF NOT EXISTS "' . $table . '_host" ON "' . $table . '" ("host")');
        } else {
            $db->query(
                'CREATE TABLE IF NOT EXISTS `' . $table . '` ('
                . ' `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,'
                . ' `name` VARCHAR(80) NOT NULL,'
                . ' `url` VARCHAR(255) NOT NULL,'
                . ' `host` VARCHAR(120) NOT NULL,'
                . ' `descr` VARCHAR(255) NOT NULL DEFAULT \'\','
                . ' `avatar` VARCHAR(255) NOT NULL DEFAULT \'\','
                . ' `mail` VARCHAR(120) NOT NULL,'
                . ' `status` VARCHAR(16) NOT NULL DEFAULT \'pending\','
                . ' `ip` VARCHAR(64) NOT NULL DEFAULT \'\','
                . ' `created` INT(10) UNSIGNED NOT NULL DEFAULT 0,'
                . ' `updated` INT(10) UNSIGNED NOT NULL DEFAULT 0,'
                . ' PRIMARY KEY (`id`),'
                . ' KEY `status` (`status`),'
                . ' KEY `host` (`host`)'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        }
        $ok = true;
    } catch (\Throwable $e) {
        $ok = false;
    }

    return $ok;
}

/**
 * 执行依赖申请表的查询：表缺失时补建一次再重试
 */
function ecod_friend_apps_call(callable $fn)
{
    $db = \Typecho\Db::get();
    try {
        return $fn($db);
    } catch (\Throwable $e) {
        if (!ecod_friend_apps_ensure($db)) {
            throw $e;
        }
        return $fn($db);
    }
}

/**
 * 申请入口是否开放（后台「互动 → 友链申请」开关，默认开启）
 */
function ecod_friend_apply_enabled(): bool
{
    return (string) ecod_friend_opt('EFriendApply', 'on') === 'on';
}

/* ------------------------------------------------------------------
 * 输入规整
 * ---------------------------------------------------------------- */

/**
 * 补全协议头：example.com → https://example.com
 */
function ecod_friend_norm_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
        $url = 'https://' . ltrim($url, '/');
    }
    return $url;
}

/**
 * 站点地址是否可用：只收 http/https、必须带域名点号
 */
function ecod_friend_url_ok(string $url): bool
{
    if (!preg_match('#^https?://#i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $host = (string) parse_url($url, PHP_URL_HOST);
    return $host !== '' && strpos($host, '.') !== false;
}

/**
 * 取域名（小写、去 www.），用于查重
 */
function ecod_friend_host(string $url): string
{
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    return preg_replace('#^www\.#', '', $host);
}

/**
 * 单行文本清洗：去标签、去竖线（正文行分隔符）、压缩空白
 */
function ecod_friend_text(string $text, int $max = 80): string
{
    $text = trim(strip_tags($text));
    $text = str_replace(['|', "\r", "\n", "\t"], ['｜', ' ', ' ', ' '], $text);
    $text = trim((string) preg_replace('/\s{2,}/u', ' ', $text));
    if (mb_strlen($text, 'UTF-8') > $max) {
        $text = mb_substr($text, 0, $max, 'UTF-8');
    }
    return $text;
}

/* ------------------------------------------------------------------
 * 提交
 * ---------------------------------------------------------------- */

/**
 * 校验并落库一条申请
 *
 * @return array{ok:bool,error?:string,id?:int,app?:array}
 */
function ecod_friend_app_submit(array $in, string $ip = ''): array
{
    $name   = ecod_friend_text((string) ($in['name'] ?? ''), 40);
    $url    = ecod_friend_norm_url((string) ($in['url'] ?? ''));
    $desc   = ecod_friend_text((string) ($in['desc'] ?? ''), 60);
    $avatar = trim((string) ($in['avatar'] ?? ''));
    $mail   = trim((string) ($in['mail'] ?? ''));

    if ($name === '') {
        return ['ok' => false, 'error' => '请填写站点名称'];
    }
    if ($url === '' || !ecod_friend_url_ok($url)) {
        return ['ok' => false, 'error' => '站点地址格式不正确，例如 https://example.com'];
    }
    if ($avatar !== '') {
        $avatar = ecod_friend_norm_url($avatar);
        if (!preg_match('#^https?://#i', $avatar) || !filter_var($avatar, FILTER_VALIDATE_URL)) {
            return ['ok' => false, 'error' => '头像地址需要是完整的 http(s) 链接'];
        }
        if (mb_strlen($avatar, 'UTF-8') > 255) {
            return ['ok' => false, 'error' => '头像地址太长了'];
        }
    }
    if ($mail === '' || !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => '请填写正确的联系邮箱，用于接收审核结果'];
    }

    $host = ecod_friend_host($url);
    if ($host === '') {
        return ['ok' => false, 'error' => '站点地址格式不正确'];
    }

    $selfHost = ecod_friend_host((string) ecod_friend_opt('siteUrl', ''));
    if ($selfHost !== '' && $host === $selfHost) {
        return ['ok' => false, 'error' => '不能申请本站自己的链接'];
    }

    $row = [
        'name'    => $name,
        'url'     => $url,
        'host'    => $host,
        'descr'   => $desc,
        'avatar'  => $avatar,
        'mail'    => $mail,
        'status'  => 'pending',
        'ip'      => substr($ip, 0, 64),
        'created' => time(),
        'updated' => time(),
    ];

    try {
        $dup = ecod_friend_apps_call(static function (\Typecho\Db $db) use ($host) {
            return $db->fetchRow(
                $db->select('id')->from(ecod_friend_apps_table())
                    ->where('host = ?', $host)
                    ->where('status IN ?', ['pending', 'approved'])
                    ->limit(1)
            );
        });
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => '提交失败，请稍后再试'];
    }

    if ($dup) {
        return ['ok' => false, 'error' => '这个站点已经提交过，或已在友链列表中'];
    }

    try {
        $newId = (int) ecod_friend_apps_call(static function (\Typecho\Db $db) use ($row) {
            return $db->query($db->insert(ecod_friend_apps_table())->rows($row));
        });
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => '提交失败，请稍后再试'];
    }

    $app = $row;
    $app['id'] = $newId;

    ecod_friend_mail_admin($app);

    return ['ok' => true, 'id' => $app['id'], 'app' => $app];
}

/* ------------------------------------------------------------------
 * 读取 / 审核
 * ---------------------------------------------------------------- */

/**
 * 按状态取申请列表
 */
function ecod_friend_apps_list(string $status = 'pending', int $limit = 30): array
{
    try {
        return (array) ecod_friend_apps_call(static function (\Typecho\Db $db) use ($status, $limit) {
            return $db->fetchAll(
                $db->select()->from(ecod_friend_apps_table())
                    ->where('status = ?', $status)
                    ->order('id', \Typecho\Db::SORT_DESC)
                    ->limit(max(1, $limit))
            );
        });
    } catch (\Throwable $e) {
        return [];
    }
}

/**
 * 待审核数量
 */
function ecod_friend_apps_count(string $status = 'pending'): int
{
    try {
        $row = ecod_friend_apps_call(static function (\Typecho\Db $db) use ($status) {
            return $db->fetchRow(
                $db->select('COUNT(*) AS num')->from(ecod_friend_apps_table())
                    ->where('status = ?', $status)
            );
        });
        return $row ? (int) $row['num'] : 0;
    } catch (\Throwable $e) {
        return 0;
    }
}

/**
 * 当前访客是否为可审核的管理员
 */
function ecod_friend_app_is_admin(): bool
{
    try {
        $user = \Widget\User::alloc();
        return $user->hasLogin() && ($user->pass('administrator', true) || $user->pass('editor', true));
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * 单条申请
 */
function ecod_friend_app_get(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    try {
        $row = ecod_friend_apps_call(static function (\Typecho\Db $db) use ($id) {
            return $db->fetchRow(
                $db->select()->from(ecod_friend_apps_table())->where('id = ?', $id)->limit(1)
            );
        });
        return $row ? (array) $row : null;
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * 写回状态
 */
function ecod_friend_app_set_status(int $id, string $status): bool
{
    try {
        ecod_friend_apps_call(static function (\Typecho\Db $db) use ($id, $status) {
            $db->query(
                $db->update(ecod_friend_apps_table())
                    ->rows(['status' => $status, 'updated' => time()])
                    ->where('id = ?', $id)
            );
            return true;
        });
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * 审核一条申请
 *
 * @param string $decision approve / reject
 * @return array{ok:bool,error?:string}
 */
function ecod_friend_app_review(int $id, string $decision): array
{
    $app = ecod_friend_app_get($id);
    if (!$app) {
        return ['ok' => false, 'error' => '申请不存在'];
    }
    if ((string) $app['status'] !== 'pending') {
        return ['ok' => false, 'error' => '这条申请已经处理过了'];
    }

    if ($decision === 'reject') {
        if (!ecod_friend_app_set_status($id, 'rejected')) {
            return ['ok' => false, 'error' => '操作失败，请稍后再试'];
        }
        /* 站内消息：申请人邮箱命中注册用户时投一条 */
        ecod_notif_friend_review($app, 'reject');
        return ['ok' => true];
    }

    if ($decision !== 'approve') {
        return ['ok' => false, 'error' => '未知操作'];
    }

    $line = ecod_friend_line($app);
    if (!ecod_friend_page_append($line)) {
        return ['ok' => false, 'error' => '写入友链页失败，请检查页面是否还存在'];
    }
    if (!ecod_friend_app_set_status($id, 'approved')) {
        return ['ok' => false, 'error' => '已写入友链页，但状态更新失败，请手动核对'];
    }

    ecod_friend_mail_applicant($app, 'approved');

    /* 站内消息：申请人邮箱命中注册用户时投一条 */
    ecod_notif_friend_review($app, 'approved');

    return ['ok' => true];
}

/**
 * 申请 → 友链页正文的一行
 */
function ecod_friend_line(array $app): string
{
    $parts = [
        (string) $app['name'],
        (string) $app['url'],
        (string) ($app['descr'] ?? ''),
        (string) ($app['avatar'] ?? ''),
    ];
    while (count($parts) > 2 && end($parts) === '') {
        array_pop($parts);
    }
    return implode(' | ', $parts);
}

/* ------------------------------------------------------------------
 * 友链页正文读写
 * ---------------------------------------------------------------- */

/**
 * 找使用 friends.php 模板的页面 cid（优先已发布）
 */
function ecod_friend_page_cid(): int
{
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('cid')->from('table.contents')
                ->where('template = ?', 'friends.php')
                ->where('type = ?', 'page')
                ->order('cid', \Typecho\Db::SORT_DESC)
                ->limit(1)
        );
        return $row ? (int) $row['cid'] : 0;
    } catch (\Throwable $e) {
        return 0;
    }
}

/**
 * 把一行友链插到「最后一个数据行」后面（保持说明文字在底部）
 */
function ecod_friend_page_append(string $line): bool
{
    $cid = ecod_friend_page_cid();
    if ($cid <= 0 || trim($line) === '') {
        return false;
    }

    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('text')->from('table.contents')->where('cid = ?', $cid)->limit(1)
        );
        if (!$row) {
            return false;
        }
        $text = (string) $row['text'];

        /* 已在正文里（同域名）就不重复写 */
        $host = '';
        if (preg_match('/\|\s*(https?:\/\/[^\s|]+)/i', $line, $m)) {
            $host = ecod_friend_host($m[1]);
            if ($host !== '' && stripos($text, $host) !== false) {
                return true;
            }
        }

        $lines = preg_split('/\r\n|\r|\n/', $text);
        $insertAt = null;
        foreach ($lines as $i => $one) {
            if (preg_match('#\|\s*https?://#i', (string) $one)) {
                $insertAt = $i;
            }
        }

        if ($insertAt === null) {
            $text = rtrim($text, "\r\n") . "\n" . $line . "\n";
        } else {
            array_splice($lines, $insertAt + 1, 0, [$line]);
            $text = implode("\n", $lines);
        }

        $db->query(
            $db->update('table.contents')
                ->rows(['text' => $text, 'modified' => time()])
                ->where('cid = ?', $cid)
        );
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

/* ------------------------------------------------------------------
 * 邮件通知（模板可在「互动 → 友链通知邮件模板」里改；未配置 SMTP 时静默跳过）
 * ---------------------------------------------------------------- */

/**
 * 模板正文内置默认值（设置里留空时使用）
 */
function ecod_friend_mail_default(string $which, string $part): string
{
    $map = [
        'admin' => [
            'subject' => '【友链申请】{站点名称}',
            'body'    => "收到一条新的友链申请。\n\n"
                . "站点名称：{站点名称}\n"
                . "站点地址：{站点地址}\n"
                . "一句话简介：{一句话简介}\n"
                . "联系邮箱：{联系邮箱}\n\n"
                . "到友链页的「待审核申请」面板可以一键通过。",
        ],
        'pass' => [
            'subject' => '友链申请已通过 · {本站名称}',
            'body'    => "你好，「{站点名称}」的友链申请已通过审核。\n\n"
                . "站点地址：{站点地址}\n\n"
                . "已经加入友链列表，感谢分享，欢迎常来。\n"
                . "{本站地址}",
        ],
    ];
    return $map[$which][$part] ?? '';
}

/**
 * 取模板（设置优先，空则用默认）
 */
function ecod_friend_mail_tpl(string $key, string $which, string $part): string
{
    $v = trim((string) ecod_friend_opt($key, ''));
    return $v !== '' ? $v : ecod_friend_mail_default($which, $part);
}

/**
 * 模板可用变量
 */
function ecod_friend_mail_vars(array $app): array
{
    $siteName = trim((string) ecod_friend_opt('ELogo', ''));
    if ($siteName === '') {
        $siteName = trim((string) ecod_friend_opt('title', ''));
    }
    return [
        '{站点名称}'   => (string) ($app['name'] ?? ''),
        '{站点地址}'   => (string) ($app['url'] ?? ''),
        '{一句话简介}' => (string) ($app['descr'] ?? ''),
        '{联系邮箱}'   => (string) ($app['mail'] ?? ''),
        '{头像地址}'   => (string) ($app['avatar'] ?? ''),
        '{本站名称}'   => $siteName,
        '{本站地址}'   => rtrim((string) ecod_friend_opt('siteUrl', ''), '/'),
    ];
}

/**
 * 纯文本模板 → HTML：变量替换、自动转义、空行分段、URL 自动成链
 */
function ecod_friend_mail_html(string $body, array $vars): string
{
    $text = strtr($body, $vars);
    $html = '';
    foreach (preg_split('/\n{2,}/', trim($text)) as $para) {
        $para = trim((string) $para);
        if ($para === '') {
            continue;
        }
        $safe = nl2br(htmlspecialchars($para, ENT_QUOTES, 'UTF-8'), false);
        $safe = (string) preg_replace(
            '#(https?://[^\s<]+)#i',
            '<a href="$1" style="color:#4f6ef2">$1</a>',
            $safe
        );
        $html .= '<p style="margin:0 0 12px">' . $safe . '</p>';
    }
    return '<div style="font:14px/1.75 -apple-system,Segoe UI,PingFang SC,sans-serif;color:#1e2430">' . $html . '</div>';
}

/**
 * 通知博主有新申请
 */
function ecod_friend_mail_admin(array $app): void
{
    if (!function_exists('ecod_mail_ready') || !ecod_mail_ready()) {
        return;
    }
    try {
        $c = ecod_mail_config();
        $vars = ecod_friend_mail_vars($app);
        $subject = strtr(ecod_friend_mail_tpl('EFriendMailSubject', 'admin', 'subject'), $vars);
        $html = ecod_friend_mail_html(ecod_friend_mail_tpl('EFriendMailBody', 'admin', 'body'), $vars);

        ecod_mail_smtp($c, $subject, $html);
    } catch (\Throwable $e) {
        error_log('[ecod-friend] mail admin failed: ' . $e->getMessage());
    }
}

/**
 * 通知申请人审核结果（仅通过时发送，避免被当成退信轰炸）
 */
function ecod_friend_mail_applicant(array $app, string $status): void
{
    if ($status !== 'approved' || !function_exists('ecod_mail_ready') || !ecod_mail_ready()) {
        return;
    }
    $mail = trim((string) ($app['mail'] ?? ''));
    if ($mail === '' || !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    try {
        $c = ecod_mail_config();
        $c['to'] = $mail;
        $vars = ecod_friend_mail_vars($app);
        $subject = strtr(ecod_friend_mail_tpl('EFriendPassSubject', 'pass', 'subject'), $vars);
        $html = ecod_friend_mail_html(ecod_friend_mail_tpl('EFriendPassBody', 'pass', 'body'), $vars);

        ecod_mail_smtp($c, $subject, $html);
    } catch (\Throwable $e) {
        error_log('[ecod-friend] mail applicant failed: ' . $e->getMessage());
    }
}

