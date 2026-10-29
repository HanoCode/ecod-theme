<?php
/**
 * Ecod Theme · 轻量接口
 *
 * 直接以独立入口运行，不经过 Typecho 路由：
 *   GET  api.php?action=like&cid=12   读取点赞数
 *   POST api.php?action=like&cid=12   点赞（同访客同文章只计一次）
 *   GET  api.php?action=state&cids=1,2,3  批量读取点赞数与「我是否赞过」
 *   GET  api.php?action=ping          健康检查
 *
 * 去重靠访客 cookie（ecod_vid）+ 唯一索引，不再依赖 IP：
 * NAT / 校园网 / CDN 下每个浏览器仍是独立个体，刷新与爬虫也不会重复计数。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 * @license   MIT
 */

/* 向上查找 Typecho 根目录 */
$ecodRoot = __DIR__;
while ($ecodRoot !== dirname($ecodRoot) && !is_file($ecodRoot . '/config.inc.php')) {
    $ecodRoot = dirname($ecodRoot);
}
if (!is_file($ecodRoot . '/config.inc.php')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Typecho root not found']);
    exit;
}
require_once $ecodRoot . '/config.inc.php';
require_once __DIR__ . '/core/lib/schema.php';
require_once __DIR__ . '/core/site/seo.php';
require_once __DIR__ . '/core/admin/snapshot.php';
require_once __DIR__ . '/core/site/pages.php';
require_once __DIR__ . '/core/user/account.php';
require_once __DIR__ . '/core/user/oauth.php';
require_once __DIR__ . '/core/content/thumbs.php';
require_once __DIR__ . '/core/content/ai.php';
require_once __DIR__ . '/core/site/mail.php';
require_once __DIR__ . '/core/site/friends.php';
require_once __DIR__ . '/core/pay/pay.php';
require_once __DIR__ . '/core/pay/pay-channel.php';

/* 独立入口不经过 Widget\Init 引导，补上 PasswordHash 的类别名
 * （个人中心改密码 / 注册 / 找回密码都依赖它） */
if (!class_exists('\PasswordHash') && class_exists('\Utils\PasswordHash')) {
    class_alias('\Utils\PasswordHash', '\PasswordHash');
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

/* ------------------------------------------------------------------
 * 独立入口的 cookie 初始化
 *
 * 正常请求由 Widget\Init 调 Cookie::setPrefix(rootUrl)，登录 cookie
 * 名都带 md5(rootUrl) 前缀。rootUrl 是「前台入口所在目录」的 URL，
 * 而这里脚本在 /usr/themes/... 下，必须去掉主题目录再算前缀，
 * 否则与前台/后台的 cookie 名对不上，登录态全部失效。
 * ---------------------------------------------------------------- */
try {
    if (defined('__TYPECHO_ROOT_URL__')) {
        $ecodRootUrl = __TYPECHO_ROOT_URL__;
    } else {
        $ecodSecure = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;
        $ecodPrefixUrl = ($ecodSecure ? 'https' : 'http') . '://'
            . ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
        if (defined('__TYPECHO_URL_PREFIX__')) {
            $ecodPrefixUrl = rtrim(__TYPECHO_URL_PREFIX__, '/');
        }
        /* 前台根目录 = 当前脚本目录去掉 /usr/themes/<主题名> */
        $ecodSuffix = '/usr/themes/' . basename(__DIR__);
        $ecodScriptDir = rtrim(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        $ecodRootUrl = $ecodPrefixUrl
            . (substr($ecodScriptDir, -strlen($ecodSuffix)) === $ecodSuffix
                ? substr($ecodScriptDir, 0, -strlen($ecodSuffix))
                : '');
    }
    \Typecho\Cookie::setPrefix($ecodRootUrl);
    if (defined('__TYPECHO_COOKIE_OPTIONS__')) {
        \Typecho\Cookie::setOptions(__TYPECHO_COOKIE_OPTIONS__);
    }
    unset($ecodRootUrl, $ecodSecure, $ecodPrefixUrl, $ecodSuffix, $ecodScriptDir);
} catch (\Throwable $e) {
}

/**
 * 输出并结束
 */
function ecod_api_out(array $data, int $status = 200): void
{
    ecod_http_status($status);
    /* 登录等动作的 Set-Cookie 暂存在 Response 里，离开前统一发给浏览器 */
    try {
        \Typecho\Response::getInstance()->sendHeaders();
    } catch (\Throwable $e) {
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * 访客标识：优先读 cookie，缺失则生成并下发（一年有效）
 */
function ecod_api_visitor(): string
{
    $vid = '';
    if (isset($_COOKIE['ecod_vid'])) {
        $vid = preg_replace('/[^a-f0-9]/', '', strtolower((string) $_COOKIE['ecod_vid']));
    }

    if (strlen($vid) < 16) {
        try {
            $vid = bin2hex(random_bytes(12));
        } catch (\Throwable $e) {
            $vid = substr(md5(uniqid('ecod', true)), 0, 24);
        }
        if (!headers_sent()) {
            /* HTTPS 站点给 cookie 补 secure 标志（判定方式与引导段一致） */
            $ecodHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
                || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;
            setcookie('ecod_vid', $vid, [
                'expires'  => time() + 31536000,
                'path'     => '/',
                'httponly' => true,
                'secure'   => $ecodHttps,
                'samesite' => 'Lax',
            ]);
        }
        $_COOKIE['ecod_vid'] = $vid;
    }

    return $vid;
}

/**
 * 写入点赞表用的访客指纹
 */
function ecod_api_fingerprint(): string
{
    return substr(hash('sha256', ecod_api_visitor() . '|ecod'), 0, 40);
}

/**
 * 读取点赞数
 */
function ecod_api_count(\Typecho\Db $db, string $table, int $cid): int
{
    $row = $db->fetchObject(
        $db->select('COUNT(*) AS num')->from($table)->where('cid = ?', $cid)
    );
    return $row ? (int) $row->num : 0;
}

/**
 * 执行依赖点赞表的查询：表不存在时补建一次再重试
 *
 * 正常请求不产生额外检查，DDL 只在首次（或表被删）时发生。
 */
function ecod_api_with_table(\Typecho\Db $db, callable $fn)
{
    try {
        return $fn(ecod_likes_table());
    } catch (\Throwable $e) {
        if (!ecod_schema_ensure_likes($db)) {
            throw $e;
        }
        return $fn(ecod_likes_table());
    }
}

$action = isset($_GET['action']) ? (string) $_GET['action'] : '';

/* 缩略图输出：GET + 二进制流，不走 JSON，必须在最前处理 */
if ($action === 'thumb') {
    ecod_api_thumb_serve();
}

/* 第三方登录：GET + 302 跳转，不走下面的 JSON 输出，必须在最前处理 */
if ($action === 'oauth_start' || $action === 'oauth_callback') {
    $ecodLoginRedirect = static function (string $msg): void {
        $base = rtrim((string) ecod_account_opt('siteUrl', '/'), '/');
        ecod_http_status(302); /* Typecho 的 ob 回调会重写状态行，必须显式进 Response */
        header('Location: ' . $base . '/login.html?oauth_error=' . rawurlencode($msg));
        exit;
    };
    $platform = (string) ($_GET['platform'] ?? 'github');
    if (!in_array($platform, ['github'], true)) {
        $ecodLoginRedirect('暂不支持该平台');
    }
    $err = $action === 'oauth_start'
        ? ecod_oauth_start($platform)
        : ecod_oauth_callback($platform);
    if ($err !== '') {
        /* 未启用等前置错误：跳回登录页带提示 */
        $ecodLoginRedirect($err);
    }
    exit;
}
$cid = isset($_REQUEST['cid']) ? (int) $_REQUEST['cid'] : 0;

/* ------------------------------------------------------------------
 * 后台工具：配置快照 / sitemap 生成
 * 仅管理员 + 一次性令牌可用
 * ---------------------------------------------------------------- */

/**
 * 当前访客是否为管理员
 */
function ecod_api_is_admin(): bool
{
    try {
        $user = \Widget\User::alloc();
        return $user->hasLogin() && $user->pass('administrator', true);
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * 工具令牌：随登录态变化，无法被外部复用
 */
function ecod_api_admin_token(): string
{
    return ecod_tool_token();
}

$ecodToolActions = ['snapshot_list', 'snapshot_save', 'snapshot_restore', 'snapshot_delete', 'build_sitemap', 'build_robots', 'push_baidu', 'page_list', 'page_create', 'topic_posts'];

if (in_array($action, $ecodToolActions, true)) {
    if (!ecod_api_is_admin()) {
        ecod_api_out(['ok' => false, 'error' => '需要管理员登录'], 403);
    }
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }

    $token = isset($_POST['token']) ? (string) $_POST['token'] : '';
    if ($token === '' || !hash_equals(ecod_api_admin_token(), $token)) {
        ecod_api_out(['ok' => false, 'error' => '令牌校验失败，请刷新页面重试'], 403);
    }

    try {
        switch ($action) {
            case 'snapshot_list':
                ecod_api_out(['ok' => true, 'list' => ecod_snapshot_list(), 'max' => ECOD_SNAPSHOT_MAX]);

            case 'snapshot_save':
                ecod_api_out(ecod_snapshot_create());

            case 'snapshot_restore':
                $name = isset($_POST['name']) ? (string) $_POST['name'] : '';
                $ecodSnapRes = ecod_snapshot_restore($name);
                if (!empty($ecodSnapRes['ok'])) {
                    ecod_audit_log('snapshot_restore', ['name' => $name]);
                }
                ecod_api_out($ecodSnapRes);

            case 'snapshot_delete':
                $name = isset($_POST['name']) ? (string) $_POST['name'] : '';
                ecod_api_out(ecod_snapshot_delete($name));

            case 'build_sitemap':
                ecod_api_out(ecod_sitemap_write());

            case 'build_robots':
                ecod_api_out(ecod_robots_write());

            case 'push_baidu':
                ecod_api_out(ecod_baidu_push());

            case 'page_list':
                ecod_api_out(['ok' => true, 'list' => array_values(ecod_pages_state())]);

            case 'page_create':
                $key = isset($_POST['key']) ? (string) $_POST['key'] : '';
                ecod_api_out(ecod_pages_create($key));

            /* 专题构建器的挑稿器：按标题搜文章，或按 cid 批量取回已选中的标题 */
            case 'topic_posts':
                $kw = trim((string) ($_POST['q'] ?? ''));
                $ids = trim((string) ($_POST['ids'] ?? ''));
                $list = [];
                try {
                    $db = \Typecho\Db::get();
                    $select = $db->select('cid', 'title', 'created')
                        ->from('table.contents')
                        ->where('type = ?', 'post')
                        ->where('status = ?', 'publish')
                        ->order('created', \Typecho\Db::SORT_DESC);

                    if ($kw !== '') {
                        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], mb_substr($kw, 0, 50, 'UTF-8')) . '%';
                        $select->where('title LIKE ?', $like)->limit(20);
                    } else {
                        $cids = array_values(array_unique(array_filter(array_map('intval', explode(',', $ids)))));
                        if (!$cids) {
                            ecod_api_out(['ok' => true, 'list' => []]);
                        }
                        $select->where('cid IN (' . implode(',', array_slice($cids, 0, 200)) . ')')->limit(200);
                    }

                    foreach ($db->fetchAll($select) as $row) {
                        $list[] = [
                            'cid'   => (int) $row['cid'],
                            'title' => (string) $row['title'],
                            'date'  => date('Y-m-d', (int) $row['created']),
                        ];
                    }
                } catch (\Throwable $e) {
                }
                ecod_api_out(['ok' => true, 'list' => $list]);
        }
    } catch (\Throwable $e) {
        ecod_api_out(['ok' => false, 'error' => 'Server error'], 500);
    }

    ecod_api_out(['ok' => false, 'error' => 'Unknown action'], 400);
}

if ($action === 'ping') {
    ecod_api_out(['ok' => true, 'time' => time()]);
}

/* AI 智能摘要：文章页异步获取，生成失败时静默（前端隐藏区块） */
if ($action === 'ai') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    if ($cid <= 0) {
        ecod_api_out(['ok' => false, 'error' => 'Bad request'], 400);
    }
    if (!ecod_ai_enabled()) {
        ecod_api_out(['ok' => false, 'error' => 'AI 摘要未开启'], 404);
    }

    /* 模型调用按次计费：命中缓存的请求不限，真正触发生成的才计一次 */
    $ecodAiBucket = 'ai:' . ecod_api_fingerprint();
    $ecodAiWait = ecod_throttle_guard($ecodAiBucket);
    if ($ecodAiWait > 0) {
        ecod_api_out(['ok' => false, 'error' => ecod_throttle_error($ecodAiWait)], 403);
    }

    try {
        $ecodAiRes = ecod_ai_summary($cid);
    } catch (\Throwable $e) {
        ecod_api_out(['ok' => false, 'error' => '生成失败，请稍后重试'], 500);
    }
    if (empty($ecodAiRes['cached'])) {
        ecod_throttle_fail($ecodAiBucket);
    }
    ecod_api_out($ecodAiRes);
}

/* 搜索联想：标题匹配前 8 条，导航下拉 / 搜索面板实时结果共用 */
if ($action === 'search_suggest') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }

    $q = trim((string) ($_GET['q'] ?? ''));
    if (mb_strlen($q, 'UTF-8') < 1 || mb_strlen($q, 'UTF-8') > 50) {
        ecod_api_out(['ok' => true, 'list' => []]);
    }

    try {
        $db = \Typecho\Db::get();
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        $rows = $db->fetchAll(
            $db->select('cid', 'title', 'created')->from('table.contents')
                ->where('type = ?', 'post')
                ->where('status = ?', 'publish')
                ->where('title LIKE ?', $like)
                ->order('created', \Typecho\Db::SORT_DESC)
                ->limit(8)
        );

        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'cid'   => (int) $row['cid'],
                'title' => (string) $row['title'],
                'date'  => date('Y-m-d', (int) $row['created']),
            ];
        }
        ecod_api_out(['ok' => true, 'list' => $list]);
    } catch (\Throwable $e) {
        ecod_api_out(['ok' => false, 'error' => 'Server error'], 500);
    }
}

/* ------------------------------------------------------------------
 * 账户：前台登录 / 个人资料 / 密码 / 头像
 * ---------------------------------------------------------------- */

/**
 * 同源校验：POST 请求必须来自本站页面（Origin / Referer 任一命中）
 */
function ecod_api_same_origin(): bool
{
    $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    if ($host === '') {
        return false;
    }
    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $key) {
        $raw = (string) ($_SERVER[$key] ?? '');
        if ($raw === '') {
            continue;
        }
        $part = parse_url($raw);
        if (empty($part['host'])) {
            continue;
        }
        /* HTTP_HOST 带端口、Origin host 不带，比较前都去掉端口 */
        $refHost = strtolower(preg_replace('/:\d+$/', '', (string) $part['host']));
        if ($refHost !== '' && $refHost === $host) {
            return true;
        }
    }
    return false;
}

/**
 * 当前登录用户；未登录返回 null
 */
function ecod_api_user()
{
    try {
        $user = \Widget\User::alloc();
        return $user->hasLogin() ? $user : null;
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * 回跳地址清洗：只允许站内绝对路径（/ 开头且非 //）
 */
function ecod_api_safe_referer(string $referer): string
{
    $referer = trim($referer);
    if ($referer === '' || $referer[0] !== '/' || substr($referer, 0, 2) === '//') {
        return '';
    }
    return $referer;
}

/**
 * 密码强度：8-64 位，拒绝常见弱密码（注册 / 改密 / 找回共用）
 */
function ecod_api_password_ok(string $password): bool
{
    $len = strlen($password);
    if ($len < 8 || $len > 64) {
        return false;
    }
    $weak = [
        '12345678', '123456789', '1234567890', 'password', 'password1',
        'qwerty123', 'qwertyui', '11111111', '00000000', '88888888',
        '66666666', '12341234', '11223344', 'abc12345', 'abcd1234',
        'iloveyou', 'admin123', 'qq123456', '1qaz2wsx', 'asdfghjk',
    ];
    return !in_array(strtolower($password), $weak, true);
}

/* 登录图形验证码（独立出口：返回 PNG，不走 JSON） */
if ($action === 'captcha') {
    /* 图片流不能被任何警告 / BOM 污染 */
    @ini_set('display_errors', '0');
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header_remove('Content-Type');
    ecod_captcha_image();
    exit;
}

/* 前台登录：与后台登录同一套校验逻辑（Widget\User::login） */
if ($action === 'login') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    if (!ecod_api_same_origin()) {
        ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $remember = !empty($_POST['remember']);

    if ($name === '' || $password === '') {
        ecod_api_out(['ok' => false, 'error' => '请输入账号和密码'], 400);
    }

    /* 分级限流：越错越慢（1-5 次不限 → 30s → 180s），成功后清零 */
    $ecodBucket = 'login:' . strtolower($name);
    $ecodWait = ecod_throttle_guard($ecodBucket);
    if ($ecodWait > 0) {
        ecod_api_out(['ok' => false, 'error' => ecod_throttle_error($ecodWait)], 403);
    }

    /* 图形验证码：一次性消费，先于密码校验；试错同样计入限流，防止无成本猜码 */
    if (!ecod_captcha_verify((string) ($_POST['captcha'] ?? ''))) {
        ecod_throttle_fail($ecodBucket);
        ecod_api_out(['ok' => false, 'error' => '验证码不正确，请重新输入'], 400);
    }

    try {
        $user = \Widget\User::alloc();
        $valid = $user->login($name, $password, false, $remember ? 30 * 24 * 3600 : 0);
    } catch (\Throwable $e) {
        ecod_api_out(['ok' => false, 'error' => '登录失败，请稍后重试'], 500);
    }

    if (!$valid) {
        ecod_throttle_fail($ecodBucket);
        /* 与后台一致：失败睡 2 秒防穷举 */
        sleep(2);
        ecod_api_out(['ok' => false, 'error' => '账号或密码错误'], 401);
    }

    ecod_throttle_clear($ecodBucket);
    ecod_api_out(['ok' => true, 'redirect' => ecod_api_safe_referer((string) ($_POST['referer'] ?? ''))]);
}

/* ------------------------------------------------------------------
 * 友链申请：访客提交 / 管理员审核
 *
 * 访客提交只落库（pending），博主在友链页的面板里通过后才写进正文；
 * 审核动作必须有管理员登录 + 账户令牌，避免被直接构造请求通过。
 * ---------------------------------------------------------------- */

if ($action === 'friend_apply') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    if (!ecod_api_same_origin()) {
        ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
    }
    if (!ecod_friend_apply_enabled()) {
        ecod_api_out(['ok' => false, 'error' => '本站暂未开放友链申请'], 403);
    }
    if (!ecod_account_token_ok((string) ($_POST['token'] ?? ''))) {
        ecod_api_out(['ok' => false, 'error' => '令牌校验失败，请刷新页面重试'], 403);
    }
    /* 蜜罐字段：真人看不到，填了就按垃圾提交静默丢弃 */
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        ecod_api_out(['ok' => true, 'id' => 0, 'msg' => '审核通过后会出现在友链列表里']);
    }

    $ecodBucket = 'flink:' . ecod_api_fingerprint();
    $ecodWait = ecod_throttle_guard($ecodBucket);
    if ($ecodWait > 0) {
        ecod_api_out(['ok' => false, 'error' => ecod_throttle_error($ecodWait)], 403);
    }
    ecod_throttle_fail($ecodBucket);

    if (!ecod_captcha_verify((string) ($_POST['captcha'] ?? ''))) {
        ecod_api_out(['ok' => false, 'error' => '验证码不正确，请重新输入'], 400);
    }

    $ecodApplied = ecod_friend_app_submit($_POST, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if (empty($ecodApplied['ok'])) {
        ecod_api_out(['ok' => false, 'error' => (string) ($ecodApplied['error'] ?? '提交失败，请稍后再试')], 400);
    }

    ecod_api_out([
        'ok'  => true,
        'id'  => (int) ($ecodApplied['id'] ?? 0),
        'msg' => '审核通过后会出现在友链列表里',
    ]);
}

if ($action === 'friend_review') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    if (!ecod_api_same_origin()) {
        ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
    }
    if (!ecod_friend_app_is_admin()) {
        ecod_api_out(['ok' => false, 'error' => '需要管理员登录'], 403);
    }
    if (!ecod_account_token_ok((string) ($_POST['token'] ?? ''))) {
        ecod_api_out(['ok' => false, 'error' => '令牌校验失败，请刷新页面重试'], 403);
    }

    $ecodId = (int) ($_POST['id'] ?? 0);
    $ecodDecision = (string) ($_POST['decision'] ?? '');
    if (!in_array($ecodDecision, ['approve', 'reject'], true)) {
        ecod_api_out(['ok' => false, 'error' => '未知操作'], 400);
    }

    $ecodReviewed = ecod_friend_app_review($ecodId, $ecodDecision);
    if (empty($ecodReviewed['ok'])) {
        ecod_api_out(['ok' => false, 'error' => (string) ($ecodReviewed['error'] ?? '操作失败')], 400);
    }

    ecod_api_out([
        'ok'      => true,
        'pending' => ecod_friend_apps_count('pending'),
        'msg'     => $ecodDecision === 'approve' ? '已通过，友链已写入页面' : '已忽略这条申请',
    ]);
}

/* ------------------------------------------------------------------
 * 账户：前台注册 / 邮箱验证码 / 找回密码
 * 前置条件：后台「允许注册」+ 账户邮件开关（EAccountMail）
 * ---------------------------------------------------------------- */

/** 站点是否开放注册 */
function ecod_api_allow_register(): bool
{
    return (int) ecod_account_opt('allowRegister', 0) === 1;
}

/* 发送验证码：注册（邮箱查重）与找回（邮箱必须存在）共用一个入口 */
if ($action === 'send_code') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    if (!ecod_api_same_origin()) {
        ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
    }
    if (!ecod_account_mail_ready()) {
        ecod_api_out(['ok' => false, 'error' => '站点未开启邮箱验证'], 400);
    }
    if (!ecod_account_token_ok((string) ($_POST['token'] ?? ''))) {
        ecod_api_out(['ok' => false, 'error' => '令牌校验失败，请刷新页面重试'], 403);
    }

    $mail = trim((string) ($_POST['mail'] ?? ''));
    $purpose = (string) ($_POST['purpose'] ?? '');
    if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
        ecod_api_out(['ok' => false, 'error' => '请输入正确的邮箱地址'], 400);
    }
    if (!in_array($purpose, ['register', 'reset'], true)) {
        ecod_api_out(['ok' => false, 'error' => 'Bad request'], 400);
    }

    /* 防刷：同一邮箱的发送动作也走分级限流 */
    $ecodBucket = 'code:' . strtolower($mail);
    $ecodWait = ecod_throttle_guard($ecodBucket);
    if ($ecodWait > 0) {
        ecod_api_out(['ok' => false, 'error' => ecod_throttle_error($ecodWait)], 403);
    }

    try {
        $db = \Typecho\Db::get();
        $exists = $db->fetchRow(
            $db->select('uid')->from('table.users')->where('mail = ?', $mail)->limit(1)
        );
    } catch (\Throwable $e) {
        ecod_api_out(['ok' => false, 'error' => 'Server error'], 500);
    }

    if ($purpose === 'register') {
        if (!ecod_api_allow_register()) {
            ecod_api_out(['ok' => false, 'error' => '站点未开放注册'], 403);
        }
        if ($exists) {
            ecod_throttle_fail($ecodBucket);
            ecod_api_out(['ok' => false, 'error' => '该邮箱已注册过账号'], 400);
        }
    } else {
        if (!$exists) {
            ecod_throttle_fail($ecodBucket);
            ecod_api_out(['ok' => false, 'error' => '该邮箱没有绑定任何账号'], 404);
        }
    }

    $issued = ecod_account_mailcode_issue($mail, $purpose);
    if (!$issued['ok']) {
        ecod_api_out(['ok' => false, 'error' => $issued['error']], 403);
    }
    if (!ecod_account_mailcode_send($mail, $issued['code'], $purpose)) {
        ecod_api_out(['ok' => false, 'error' => '邮件发送失败，请稍后再试'], 500);
    }

    ecod_throttle_clear($ecodBucket);
    ecod_api_out(['ok' => true]);
}

/* 前台注册：建立 subscriber 账号并自动登录 */
if ($action === 'register') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    if (!ecod_api_same_origin()) {
        ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
    }
    if (!ecod_api_allow_register()) {
        ecod_api_out(['ok' => false, 'error' => '站点未开放注册'], 403);
    }
    if (!ecod_account_token_ok((string) ($_POST['token'] ?? ''))) {
        ecod_api_out(['ok' => false, 'error' => '令牌校验失败，请刷新页面重试'], 403);
    }

    $ecodBucket = 'reg:' . ecod_api_fingerprint();
    $ecodWait = ecod_throttle_guard($ecodBucket);
    if ($ecodWait > 0) {
        ecod_api_out(['ok' => false, 'error' => ecod_throttle_error($ecodWait)], 403);
    }
    ecod_throttle_fail($ecodBucket);

    $name = trim((string) ($_POST['name'] ?? ''));
    $mail = trim((string) ($_POST['mail'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    if ($name === '' || mb_strlen($name, 'UTF-8') < 2 || mb_strlen($name, 'UTF-8') > 32) {
        ecod_api_out(['ok' => false, 'error' => '用户名需为 2-32 个字符'], 400);
    }
    if (!preg_match('/^[\x{4e00}-\x{9fa5}A-Za-z0-9_]+$/u', $name)) {
        ecod_api_out(['ok' => false, 'error' => '用户名只能包含中文、字母、数字或下划线'], 400);
    }
    if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
        ecod_api_out(['ok' => false, 'error' => '请输入正确的邮箱地址'], 400);
    }
    if (!ecod_api_password_ok($password)) {
        ecod_api_out(['ok' => false, 'error' => '密码需为 8-64 位且不能是常见弱密码'], 400);
    }
    if ($password !== $confirm) {
        ecod_api_out(['ok' => false, 'error' => '两次输入的密码不一致'], 400);
    }

    try {
        $db = \Typecho\Db::get();
        $clash = $db->fetchRow(
            $db->select('uid')->from('table.users')
                ->where('name = ? OR screenName = ?', $name, $name)->limit(1)
        );
        if ($clash) {
            ecod_api_out(['ok' => false, 'error' => '该用户名已被占用'], 400);
        }
        $clash = $db->fetchRow(
            $db->select('uid')->from('table.users')->where('mail = ?', $mail)->limit(1)
        );
        if ($clash) {
            ecod_api_out(['ok' => false, 'error' => '该邮箱已注册过账号'], 400);
        }

        $hasher = new \PasswordHash(8, true);
        $db->query(
            $db->insert('table.users')->rows([
                'name'       => $name,
                'mail'       => $mail,
                'screenName' => $name,
                'password'   => $hasher->hashPassword($password),
                'url'        => '',
                'created'    => time(),
                'activated'  => time(),
                'logged'     => time(),
                'group'      => 'subscriber',
            ])
        );
    } catch (\Throwable $e) {
        ecod_api_out(['ok' => false, 'error' => '注册失败，请稍后重试'], 500);
    }

    ecod_throttle_clear($ecodBucket);

    /* 注册即登录，与 zibll 等商用主题的行为一致 */
    try {
        $user = \Widget\User::alloc();
        $user->login($name, $password, false, 0);
    } catch (\Throwable $e) {
    }

    ecod_api_out(['ok' => true, 'redirect' => ecod_api_safe_referer((string) ($_POST['referer'] ?? ''))]);
}

/* 找回密码：邮箱验证码校验通过后重置 */
if ($action === 'reset_password') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    if (!ecod_api_same_origin()) {
        ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
    }
    if (!ecod_account_mail_ready()) {
        ecod_api_out(['ok' => false, 'error' => '站点未开启邮箱验证'], 400);
    }
    if (!ecod_account_token_ok((string) ($_POST['token'] ?? ''))) {
        ecod_api_out(['ok' => false, 'error' => '令牌校验失败，请刷新页面重试'], 403);
    }

    $mail = trim((string) ($_POST['mail'] ?? ''));
    $code = trim((string) ($_POST['code'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
        ecod_api_out(['ok' => false, 'error' => '请输入正确的邮箱地址'], 400);
    }

    $ecodBucket = 'reset:' . strtolower($mail);
    $ecodWait = ecod_throttle_guard($ecodBucket);
    if ($ecodWait > 0) {
        ecod_api_out(['ok' => false, 'error' => ecod_throttle_error($ecodWait)], 403);
    }

    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('uid')->from('table.users')->where('mail = ?', $mail)->limit(1)
        );
    } catch (\Throwable $e) {
        ecod_api_out(['ok' => false, 'error' => 'Server error'], 500);
    }
    if (!$row) {
        ecod_api_out(['ok' => false, 'error' => '该邮箱没有绑定任何账号'], 404);
    }
    $uid = (int) $row['uid'];

    if (!ecod_api_password_ok($password)) {
        ecod_api_out(['ok' => false, 'error' => '新密码需为 8-64 位且不能是常见弱密码'], 400);
    }
    if ($password !== $confirm) {
        ecod_api_out(['ok' => false, 'error' => '两次输入的密码不一致'], 400);
    }

    if (!ecod_account_mailcode_verify($mail, 'reset', $code)) {
        ecod_throttle_fail($ecodBucket);
        ecod_api_out(['ok' => false, 'error' => '验证码错误或已过期'], 400);
    }

    try {
        $hasher = new \PasswordHash(8, true);
        $db->query(
            $db->update('table.users')
                ->rows(['password' => $hasher->hashPassword($password), 'activated' => time()])
                ->where('uid = ?', $uid)
        );
    } catch (\Throwable $e) {
        ecod_api_out(['ok' => false, 'error' => '重置失败，请稍后重试'], 500);
    }

    ecod_throttle_clear($ecodBucket);
    ecod_api_out(['ok' => true]);
}

/* 资料 / 密码 / 头像：必须登录 + 同源 + CSRF 令牌 */
$ecodAccountActions = ['profile_save', 'password_save', 'avatar_upload', 'avatar_remove'];
if (in_array($action, $ecodAccountActions, true)) {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    if (!ecod_api_same_origin()) {
        ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
    }

    $ecodMe = ecod_api_user();
    if (!$ecodMe) {
        ecod_api_out(['ok' => false, 'error' => '请先登录'], 403);
    }
    if (!ecod_account_token_ok((string) ($_POST['token'] ?? ''))) {
        ecod_api_out(['ok' => false, 'error' => '令牌校验失败，请刷新页面重试'], 403);
    }

    $ecodUid = (int) $ecodMe->uid;

    /* 修改昵称 / 邮箱 / 个人主页 */
    if ($action === 'profile_save') {
        $screenName = trim((string) ($_POST['screenName'] ?? ''));
        $mail = trim((string) ($_POST['mail'] ?? ''));
        $url = trim((string) ($_POST['url'] ?? ''));

        if ($screenName === '' || mb_strlen($screenName, 'UTF-8') > 32) {
            ecod_api_out(['ok' => false, 'error' => '昵称不能为空且不超过 32 字'], 400);
        }
        if (preg_match('/[<>"\']/', $screenName)) {
            ecod_api_out(['ok' => false, 'error' => '昵称不能包含特殊字符'], 400);
        }
        if ($mail === '' || !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
            ecod_api_out(['ok' => false, 'error' => '请输入正确的邮箱地址'], 400);
        }
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            ecod_api_out(['ok' => false, 'error' => '个人主页需以 http(s):// 开头'], 400);
        }

        try {
            $db = \Typecho\Db::get();
            $clash = $db->fetchRow(
                $db->select('uid')->from('table.users')
                    ->where('uid <> ?', $ecodUid)
                    ->where('screenName = ? OR name = ?', $screenName, $screenName)
                    ->limit(1)
            );
            if ($clash) {
                ecod_api_out(['ok' => false, 'error' => '该昵称已被占用'], 400);
            }
            $clash = $db->fetchRow(
                $db->select('uid')->from('table.users')
                    ->where('uid <> ?', $ecodUid)
                    ->where('mail = ?', $mail)
                    ->limit(1)
            );
            if ($clash) {
                ecod_api_out(['ok' => false, 'error' => '该邮箱已被其他账户使用'], 400);
            }

            $db->query(
                $db->update('table.users')
                    ->rows(['screenName' => $screenName, 'mail' => $mail, 'url' => $url])
                    ->where('uid = ?', $ecodUid)
            );
        } catch (\Throwable $e) {
            ecod_api_out(['ok' => false, 'error' => '保存失败，请稍后重试'], 500);
        }

        ecod_api_out(['ok' => true]);
    }

    /* 修改密码：校验原密码，hash 方式与 Typecho 后台一致（phpass） */
    if ($action === 'password_save') {
        $current = (string) ($_POST['current'] ?? '');
        $new = (string) ($_POST['new'] ?? '');

        if (!ecod_api_password_ok($new)) {
            ecod_api_out(['ok' => false, 'error' => '新密码需为 8-64 位且不能是常见弱密码'], 400);
        }

        try {
            $db = \Typecho\Db::get();
            $row = $db->fetchRow(
                $db->select('password')->from('table.users')->where('uid = ?', $ecodUid)->limit(1)
            );
            $stored = $row ? (string) $row['password'] : '';

            if ('$P$' === substr($stored, 0, 3)) {
                $hasher = new \PasswordHash(8, true);
                $valid = $hasher->checkPassword($current, $stored);
            } else {
                $valid = \Typecho\Common::hashValidate($current, $stored);
            }
            if (!$valid) {
                ecod_api_out(['ok' => false, 'error' => '当前密码不正确'], 400);
            }

            $hasher = new \PasswordHash(8, true);
            $db->query(
                $db->update('table.users')
                    ->rows(['password' => $hasher->hashPassword($new)])
                    ->where('uid = ?', $ecodUid)
            );
        } catch (\Throwable $e) {
            ecod_api_out(['ok' => false, 'error' => '修改失败，请稍后重试'], 500);
        }

        ecod_api_out(['ok' => true]);
    }

    /* 上传头像：jpg/png/webp/gif，最大 4MB，存 usr/uploads/ecod-avatar */
    if ($action === 'avatar_upload') {
        $file = $_FILES['avatar'] ?? null;
        if (!$file || !is_array($file) || (int) ($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
            ecod_api_out(['ok' => false, 'error' => '文件上传失败'], 400);
        }
        if ((int) $file['size'] > 4 * 1024 * 1024) {
            ecod_api_out(['ok' => false, 'error' => '图片不能超过 4MB'], 400);
        }

        $info = @getimagesize((string) $file['tmp_name']);
        $map = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_GIF  => 'gif',
            IMAGETYPE_WEBP => 'webp',
        ];
        $ext = $info ? ($map[$info[2]] ?? '') : '';
        if ($ext === '') {
            ecod_api_out(['ok' => false, 'error' => '仅支持 jpg / png / gif / webp 图片'], 400);
        }

        $dir = ecod_account_avatar_dir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            ecod_api_out(['ok' => false, 'error' => '存储目录创建失败，请检查权限'], 500);
        }

        $name = $ecodUid . '-' . time() . '.' . $ext;
        $target = $dir . '/' . $name;
        if (!@move_uploaded_file((string) $file['tmp_name'], $target)) {
            ecod_api_out(['ok' => false, 'error' => '保存文件失败，请检查目录权限'], 500);
        }
        @chmod($target, 0644);

        /* 覆盖旧文件 */
        $old = ecod_account_avatar_url($ecodUid);
        if ($old !== '') {
            $oldFile = ecod_account_avatar_file($old);
            if ($oldFile !== '' && is_file($oldFile)) {
                @unlink($oldFile);
            }
        }

        $rel = '/usr/uploads/ecod-avatar/' . $name;
        if (!ecod_account_set_avatar($ecodUid, $rel)) {
            @unlink($target);
            ecod_api_out(['ok' => false, 'error' => '写入头像记录失败'], 500);
        }

        ecod_api_out(['ok' => true, 'url' => $rel]);
    }

    /* 恢复默认头像（删除自定义，回落到本地随机头像池） */
    if ($action === 'avatar_remove') {
        ecod_api_out(['ok' => ecod_account_del_avatar($ecodUid)]);
    }
}

/* 批量状态：返回若干文章的点赞数与「我是否赞过」，用于列表页回填 */
/* ------------------------------------------------------------------
 * 付费：下单 / 查状态 / 卡密兑换 / 易支付回调
 *
 * 金额一律服务端重算，前端传什么都不看；写权益只发生在结算与核销里。
 * ---------------------------------------------------------------- */

/**
 * 由 cid 构造轻量对象（api.php 没有 Archive 上下文，只带判定需要的字段）
 */
function ecod_api_pay_post(int $cid)
{
    if ($cid <= 0) {
        return null;
    }
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('cid', 'title', 'slug', 'status', 'authorId', 'created')
                ->from('table.contents')->where('cid = ?', $cid)->limit(1)
        );
        if (!$row || (string) $row['status'] !== 'publish') {
            return null;
        }

        $post = new \stdClass();
        $post->cid = (int) $row['cid'];
        $post->title = (string) $row['title'];
        $post->slug = (string) $row['slug'];
        $post->authorId = (int) $row['authorId'];
        $post->created = (int) $row['created'];
        $post->fields = new \stdClass();

        $fields = $db->fetchAll(
            $db->select('name', 'str_value')->from('table.fields')->where('cid = ?', $cid)
        );
        foreach ((array) $fields as $field) {
            $post->fields->{(string) $field['name']} = (string) $field['str_value'];
        }

        return $post;
    } catch (\Throwable $e) {
        return null;
    }
}

if ($action === 'pay_create') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    if (!ecod_api_same_origin()) {
        ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
    }
    if (!ecod_pay_enabled()) {
        ecod_api_out(['ok' => false, 'error' => '本站暂未开启付费阅读'], 400);
    }

    $me = ecod_api_user();
    if (!$me) {
        ecod_api_out(['ok' => false, 'error' => '请先登录'], 401);
    }
    if (!ecod_account_token_ok((string) ($_POST['token'] ?? ''))) {
        ecod_api_out(['ok' => false, 'error' => '页面已过期，请刷新重试'], 403);
    }

    $bucket = 'pay:' . (int) $me->uid;
    $wait = ecod_throttle_guard($bucket);
    if ($wait > 0) {
        ecod_api_out(['ok' => false, 'error' => ecod_throttle_error($wait)], 429);
    }
    ecod_throttle_fail($bucket);

    $uid = (int) $me->uid;
    $kind = (string) ($_POST['kind'] ?? 'post') === 'vip' ? 'vip' : 'post';
    $channelId = (string) ($_POST['channel'] ?? '');
    if ($channelId === '') {
        $channelId = ecod_pay_default_channel();
    }

    /* 卡密不产生订单：卡本身带着「开通什么」 */
    if ($channelId === 'card') {
        ecod_api_out(['ok' => true, 'mode' => 'card', 'tip' => '输入卡密后立即开通，无需等待。']);
    }

    if ($kind === 'post') {
        $cid = (int) ($_POST['cid'] ?? 0);
        $post = ecod_api_pay_post($cid);
        if (!$post) {
            ecod_api_out(['ok' => false, 'error' => '文章不存在或未发布'], 404);
        }

        $state = ecod_pay_access($post, $uid);
        if ($state['mode'] === 'free') {
            ecod_api_out(['ok' => false, 'error' => '这篇文章不需要付费'], 400);
        }
        if ($state['allow']) {
            ecod_api_out(['ok' => true, 'unlocked' => true, 'message' => '你已经有阅读权限']);
        }

        $price = ecod_pay_price($post, $uid);
        if (!$price['purchasable'] || $price['amount'] <= 0) {
            ecod_api_out(['ok' => false, 'error' => '这篇文章不单独售卖，请开通会员'], 400);
        }

        $days = (int) ecod_pay_fields($post)['days'];
        if ($days <= 0) {
            $days = (int) ecod_option('EPayDays', 0);
        }

        $created = ecod_pay_order_create([
            'uid'     => $uid,
            'kind'    => 'post',
            'target'  => $cid,
            'title'   => (string) $post->title,
            'amount'  => (int) $price['amount'],
            'days'    => $days,
            'level'   => 0,
            'channel' => $channelId,
        ]);
    } else {
        $level = max(1, min(2, (int) ($_POST['level'] ?? 1)));
        $planKey = (string) ($_POST['plan'] ?? 'month');
        $tiers = ecod_pay_vip_tiers();
        $plan = $tiers[$level]['plans'][$planKey] ?? null;
        if (!$plan) {
            ecod_api_out(['ok' => false, 'error' => '会员套餐不存在'], 400);
        }

        $vip = ecod_pay_vip($uid);
        if ($vip['active'] && (int) $vip['level'] >= $level && (int) $plan['days'] === 0) {
            ecod_api_out(['ok' => true, 'unlocked' => true, 'message' => '你已经是该等级会员']);
        }

        $created = ecod_pay_order_create([
            'uid'     => $uid,
            'kind'    => 'vip',
            'target'  => 0,
            'title'   => $tiers[$level]['name'] . ' · ' . $plan['label'],
            'amount'  => (int) $plan['cents'],
            'days'    => (int) $plan['days'],
            'level'   => $level,
            'channel' => $channelId,
        ]);
    }

    if (empty($created['ok'])) {
        ecod_api_out(['ok' => false, 'error' => $created['error'] ?? '创建订单失败'], 500);
    }

    $order = ecod_pay_order_get((string) $created['order_no']);
    if (!$order) {
        ecod_api_out(['ok' => false, 'error' => '订单读取失败'], 500);
    }

    try {
        $channel = ecod_pay_channel($channelId);
        $payload = $channel->create($order);
    } catch (\Throwable $e) {
        ecod_api_out(['ok' => false, 'error' => '支付通道不可用'], 500);
    }

    ecod_api_out([
        'ok'       => true,
        'order_no' => (string) $created['order_no'],
        'mode'     => (string) ($payload['mode'] ?? 'manual'),
        'url'      => (string) ($payload['url'] ?? ''),
        'html'     => (string) ($payload['html'] ?? ''),
        'tip'      => (string) ($payload['tip'] ?? ''),
        'amount'   => (int) $order['amount'],
    ]);
}

if ($action === 'pay_status') {
    $me = ecod_api_user();
    if (!$me) {
        ecod_api_out(['ok' => false, 'error' => '请先登录'], 401);
    }

    $orderNo = trim((string) ($_GET['order_no'] ?? $_POST['order_no'] ?? ''));
    $order = ecod_pay_order_get($orderNo);
    /* 越权查询一律 404，不暴露订单是否存在 */
    if (!$order || (int) $order['uid'] !== (int) $me->uid) {
        ecod_api_out(['ok' => false, 'error' => '订单不存在'], 404);
    }

    ecod_api_out([
        'ok'       => true,
        'status'   => (string) $order['status'],
        'paid_at'  => (int) $order['paid_at'],
        'expire_at' => (int) $order['expire_at'],
        'amount'   => (int) $order['amount'],
    ]);
}

if ($action === 'pay_card') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    if (!ecod_api_same_origin()) {
        ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
    }
    $me = ecod_api_user();
    if (!$me) {
        ecod_api_out(['ok' => false, 'error' => '请先登录'], 401);
    }
    if (!ecod_account_token_ok((string) ($_POST['token'] ?? ''))) {
        ecod_api_out(['ok' => false, 'error' => '页面已过期，请刷新重试'], 403);
    }

    $bucket = 'paycard:' . (int) $me->uid;
    $wait = ecod_throttle_guard($bucket);
    if ($wait > 0) {
        ecod_api_out(['ok' => false, 'error' => ecod_throttle_error($wait)], 429);
    }

    $result = ecod_pay_card_redeem((string) ($_POST['code'] ?? ''), (int) $me->uid);
    if (empty($result['ok'])) {
        ecod_throttle_fail($bucket);
        ecod_api_out(['ok' => false, 'error' => (string) ($result['message'] ?? '兑换失败')], 400);
    }
    ecod_throttle_clear($bucket);

    ecod_api_out([
        'ok'      => true,
        'kind'    => (string) ($result['kind'] ?? ''),
        'target'  => (int) ($result['target'] ?? 0),
        'days'    => (int) ($result['days'] ?? 0),
        'message' => '兑换成功',
    ]);
}

/* 易支付异步回调：只输出 success / fail，不返回 JSON */
if ($action === 'pay_notify') {
    $params = array_merge($_GET, $_POST);
    $channel = new EcodPayEpayChannel();
    $verified = $channel->verify($params);
    if (empty($verified['ok'])) {
        echo 'fail';
        exit;
    }

    $result = ecod_pay_settle(
        (string) $verified['order_no'],
        (string) ($verified['trade_no'] ?? ''),
        'epay',
        (int) ($verified['amount'] ?? -1)
    );
    if (!empty($result['ok'])) {
        ecod_audit_log('pay_settle', [
            'order' => (string) $verified['order_no'],
            'trade' => (string) ($verified['trade_no'] ?? ''),
            'cents' => (int) ($verified['amount'] ?? -1),
        ]);
    }
    echo !empty($result['ok']) ? 'success' : 'fail';
    exit;
}

/* 易支付同步回跳：验签后结算并跳回收银台 */
if ($action === 'pay_return') {
    $params = array_merge($_GET, $_POST);
    $channel = new EcodPayEpayChannel();
    $verified = $channel->verify($params);

    $orderNo = (string) ($verified['order_no'] ?? '');
    if (!empty($verified['ok'])) {
        $ecodSettle = ecod_pay_settle($orderNo, (string) ($verified['trade_no'] ?? ''), 'epay', (int) ($verified['amount'] ?? -1));
        if (!empty($ecodSettle['ok'])) {
            ecod_audit_log('pay_settle', ['order' => $orderNo, 'via' => 'return']);
        }
    }

    $cashier = ecod_pay_cashier_url($orderNo !== '' ? ['order_no' => $orderNo] : []);
    if ($cashier === '') {
        $cashier = ecod_pay_site_url();
    }
    header('Location: ' . $cashier, true, 302);
    exit;
}

if ($action === 'state') {
    $raw = isset($_GET['cids']) ? (string) $_GET['cids'] : '';
    $cids = [];
    foreach (explode(',', $raw) as $one) {
        $one = (int) trim($one);
        if ($one > 0 && !in_array($one, $cids, true)) {
            $cids[] = $one;
        }
        if (count($cids) >= 200) {
            break;
        }
    }
    if (!$cids) {
        ecod_api_out(['ok' => true, 'counts' => new stdClass(), 'liked' => []]);
    }

    try {
        $db = \Typecho\Db::get();
        $fp = ecod_api_fingerprint();

        $payload = ecod_api_with_table($db, function (string $table) use ($db, $cids, $fp) {
            $in = implode(',', $cids);

            $counts = array_fill_keys($cids, 0);
            $rows = $db->fetchAll(
                $db->select('cid', 'COUNT(*) AS num')->from($table)
                    ->where('cid IN (' . $in . ')')->group('cid')
            );
            foreach ($rows as $row) {
                $counts[(int) $row['cid']] = (int) $row['num'];
            }

            $liked = [];
            $mine = $db->fetchAll(
                $db->select('cid')->from($table)
                    ->where('cid IN (' . $in . ')')->where('ip = ?', $fp)
            );
            foreach ($mine as $row) {
                $liked[] = (int) $row['cid'];
            }

            return ['counts' => $counts, 'liked' => $liked];
        });

        ecod_api_out(['ok' => true] + $payload);
    } catch (\Throwable $e) {
        ecod_api_out(['ok' => false, 'error' => 'Server error'], 500);
    }
}

/* 我的点赞：当前访客（浏览器指纹）赞过的文章，个人中心使用 */
if ($action === 'my_likes') {
    try {
        $db = \Typecho\Db::get();
        $fp = ecod_api_fingerprint();
        $contentsTable = $db->getPrefix() . 'contents';

        $payload = ecod_api_with_table($db, function (string $table) use ($db, $fp, $contentsTable) {
            /* $fp 只含十六进制字符（fingerprint 已清洗），可以直接内联 */
            return $db->fetchAll(
                'SELECT l.cid, l.created AS liked_at, c.title'
                . ' FROM ' . $table . ' l'
                . ' INNER JOIN ' . $contentsTable . ' c ON c.cid = l.cid'
                . " WHERE l.ip = '" . $fp . "'"
                . " AND c.type = 'post' AND c.status = 'publish'"
                . ' ORDER BY l.created DESC LIMIT 100'
            );
        });

        /* 地址由前端用 ECOD.postUrl 模板拼（独立入口下 Router 不可用） */
        $list = [];
        foreach ($payload as $row) {
            $list[] = [
                'cid'      => (int) $row['cid'],
                'title'    => (string) $row['title'],
                'liked_at' => (int) $row['liked_at'],
            ];
        }

        ecod_api_out(['ok' => true, 'list' => $list]);
    } catch (\Throwable $e) {
        ecod_api_out(['ok' => false, 'error' => 'Server error'], 500);
    }
}

/* ------------------------------------------------------------------
 * 收藏：服务端存储，登录用户跨设备同步
 *
 * 指纹规则：游客 = 浏览器指纹（cookie ecod_vid），
 * 登录用户 = 'u' + uid（固定写法，任何设备登录同一账号指纹一致）。
 * ---------------------------------------------------------------- */

/**
 * 收藏身份指纹：登录优先，游客退回设备指纹
 */
function ecod_api_fav_fingerprint(): string
{
    $user = ecod_api_user();
    return $user ? 'u' . (int) $user->uid : ecod_api_fingerprint();
}

/**
 * 执行依赖收藏表的查询：表不存在时补建一次再重试
 */
function ecod_api_with_favs(\Typecho\Db $db, callable $fn)
{
    try {
        return $fn(ecod_favs_table());
    } catch (\Throwable $e) {
        if (!ecod_schema_ensure_favs($db)) {
            throw $e;
        }
        return $fn(ecod_favs_table());
    }
}

/* 我的收藏：个人中心 / 头部面板共用 */
if ($action === 'my_favs') {
    try {
        $db = \Typecho\Db::get();
        $fp = ecod_api_fav_fingerprint();
        $contentsTable = $db->getPrefix() . 'contents';

        $payload = ecod_api_with_favs($db, function (string $table) use ($db, $fp, $contentsTable) {
            /* $fp 只含十六进制字符或 u+数字（fingerprint 已清洗），可以直接内联 */
            return $db->fetchAll(
                'SELECT f.cid, f.created AS fav_at, c.title'
                . ' FROM ' . $table . ' f'
                . ' INNER JOIN ' . $contentsTable . ' c ON c.cid = f.cid'
                . " WHERE f.fp = '" . $fp . "'"
                . " AND c.type = 'post' AND c.status = 'publish'"
                . ' ORDER BY f.created DESC LIMIT 200'
            );
        });

        /* 地址由前端用 ECOD.postUrl 模板拼（独立入口下 Router 不可用） */
        $list = [];
        foreach ($payload as $row) {
            $list[] = [
                'cid'    => (int) $row['cid'],
                'title'  => (string) $row['title'],
                'fav_at' => (int) $row['fav_at'],
            ];
        }

        ecod_api_out(['ok' => true, 'list' => $list]);
    } catch (\Throwable $e) {
        ecod_api_out(['ok' => false, 'error' => 'Server error'], 500);
    }
}

/* 收藏切换：已收藏则取消，未收藏则加入（幂等） */
if ($action === 'fav_toggle') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    if ($cid <= 0) {
        ecod_api_out(['ok' => false, 'error' => 'Invalid cid'], 400);
    }

    try {
        $db = \Typecho\Db::get();

        /* 只允许收藏已发布的文章 */
        $post = $db->fetchRow(
            $db->select('cid')->from('table.contents')
                ->where('cid = ?', $cid)
                ->where('type = ?', 'post')
                ->where('status = ?', 'publish')
        );
        if (!$post) {
            ecod_api_out(['ok' => false, 'error' => 'Post not found'], 404);
        }

        $fp = ecod_api_fav_fingerprint();
        $result = ecod_api_with_favs($db, function (string $table) use ($db, $cid, $fp) {
            $exists = (bool) $db->fetchRow(
                $db->select('id')->from($table)
                    ->where('cid = ?', $cid)->where('fp = ?', $fp)->limit(1)
            );

            if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                if ($exists) {
                    $db->query($db->delete($table)->where('cid = ?', $cid)->where('fp = ?', $fp));
                    $exists = false;
                } else {
                    try {
                        $db->query($db->insert($table)->rows([
                            'cid'     => $cid,
                            'fp'      => $fp,
                            'created' => time(),
                        ]));
                        $exists = true;
                    } catch (\Throwable $e) {
                        /* 唯一索引冲突 = 并发重复收藏，按已收藏处理 */
                        $exists = true;
                    }
                }
            }

            return ['fav' => $exists];
        });

        ecod_api_out(['ok' => true, 'cid' => $cid] + $result);
    } catch (\Throwable $e) {
        ecod_api_out(['ok' => false, 'error' => 'Server error'], 500);
    }
}

/* 批量状态：页面上所有收藏按钮一次回填「我是否收藏过」 */
if ($action === 'favs_state') {
    $raw = isset($_GET['cids']) ? (string) $_GET['cids'] : '';
    $cids = [];
    foreach (explode(',', $raw) as $one) {
        $one = (int) trim($one);
        if ($one > 0 && !in_array($one, $cids, true)) {
            $cids[] = $one;
        }
        if (count($cids) >= 200) {
            break;
        }
    }
    if (!$cids) {
        ecod_api_out(['ok' => true, 'favs' => []]);
    }

    try {
        $db = \Typecho\Db::get();
        $fp = ecod_api_fav_fingerprint();

        $favs = ecod_api_with_favs($db, function (string $table) use ($db, $cids, $fp) {
            $rows = $db->fetchAll(
                $db->select('cid')->from($table)
                    ->where('cid IN (' . implode(',', $cids) . ')')->where('fp = ?', $fp)
            );
            $out = [];
            foreach ($rows as $row) {
                $out[] = (int) $row['cid'];
            }
            return $out;
        });

        ecod_api_out(['ok' => true, 'favs' => $favs]);
    } catch (\Throwable $e) {
        ecod_api_out(['ok' => false, 'error' => 'Server error'], 500);
    }
}

/* 旧收藏迁移：把 localStorage 里的收藏合并到服务端（仅登录用户） */
if ($action === 'favs_sync') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    $me = ecod_api_user();
    if (!$me) {
        ecod_api_out(['ok' => false, 'error' => '请先登录'], 403);
    }

    $raw = isset($_POST['cids']) ? (string) $_POST['cids'] : '';
    $cids = [];
    foreach (explode(',', $raw) as $one) {
        $one = (int) trim($one);
        if ($one > 0 && !in_array($one, $cids, true)) {
            $cids[] = $one;
        }
        if (count($cids) >= 200) {
            break;
        }
    }
    if (!$cids) {
        ecod_api_out(['ok' => true, 'added' => 0]);
    }

    try {
        $db = \Typecho\Db::get();
        $fp = 'u' . (int) $me->uid;

        $added = ecod_api_with_favs($db, function (string $table) use ($db, $cids, $fp) {
            /* 只收已发布文章，防止垃圾 cid 混进收藏 */
            $valid = $db->fetchAll(
                $db->select('cid')->from('table.contents')
                    ->where('cid IN (' . implode(',', $cids) . ')')
                    ->where('type = ?', 'post')->where('status = ?', 'publish')
            );
            $n = 0;
            foreach ($valid as $row) {
                try {
                    $db->query($db->insert($table)->rows([
                        'cid'     => (int) $row['cid'],
                        'fp'      => $fp,
                        'created' => time(),
                    ]));
                    $n++;
                } catch (\Throwable $e) {
                    /* 已收藏过，跳过 */
                }
            }
            return $n;
        });

        ecod_api_out(['ok' => true, 'added' => $added]);
    } catch (\Throwable $e) {
        ecod_api_out(['ok' => false, 'error' => 'Server error'], 500);
    }
}

if ($action !== 'like') {
    ecod_api_out(['ok' => false, 'error' => 'Unknown action'], 400);
}

if ($cid <= 0) {
    ecod_api_out(['ok' => false, 'error' => 'Invalid cid'], 400);
}

try {
    $db = \Typecho\Db::get();

    /* 只允许给已发布的文章点赞 */
    $post = $db->fetchRow(
        $db->select('cid')->from('table.contents')
            ->where('cid = ?', $cid)
            ->where('type = ?', 'post')
            ->where('status = ?', 'publish')
    );
    if (!$post) {
        ecod_api_out(['ok' => false, 'error' => 'Post not found'], 404);
    }

    $fp = ecod_api_fingerprint();
    $me = ecod_api_user();
    $uid = $me ? (int) $me->uid : 0;

    $result = ecod_api_with_table($db, function (string $table) use ($db, $cid, $fp, $uid) {
        $liked = false;

        if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            /* 登录用户跨设备幂等：该 uid 已有行（旧设备落的）就不再插，避免多计一票 */
            $uidHit = $uid > 0 ? (bool) $db->fetchRow(
                $db->select('cid')->from($table)
                    ->where('cid = ?', $cid)->where('uid = ?', $uid)->limit(1)
            ) : false;

            if (!$uidHit) {
                try {
                    $row = [
                        'cid'     => $cid,
                        'ip'      => $fp,
                        'created' => time(),
                    ];
                    /* v9 起登录用户行补记 uid，头像堆叠据此取人 */
                    if ($uid > 0) {
                        $row['uid'] = $uid;
                    }
                    $db->query($db->insert($table)->rows($row));
                    $liked = true;
                } catch (\Throwable $e) {
                    /* 唯一索引冲突 = 已经赞过，幂等处理 */
                }
            }
        }

        /* 以库里的记录为准，前端据此点亮按钮：指纹行或 uid 行任一命中 */
        if ($uid > 0) {
            $mine = (bool) $db->fetchRow(
                $db->select('cid')->from($table)
                    ->where('cid = ?', $cid)
                    ->where('ip = ? OR uid = ?', $fp, $uid)
                    ->limit(1)
            );
        } else {
            $mine = (bool) $db->fetchRow(
                $db->select('cid')->from($table)
                    ->where('cid = ?', $cid)->where('ip = ?', $fp)->limit(1)
            );
        }

        return [
            'liked' => $liked,
            'mine'  => $mine,
            'count' => ecod_api_count($db, $table, $cid),
        ];
    });

    ecod_api_out(['ok' => true, 'cid' => $cid] + $result);
} catch (\Throwable $e) {
    ecod_api_out(['ok' => false, 'error' => 'Server error'], 500);
}
