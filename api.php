<?php
/**
 * Ecod Theme · 轻量接口
 *
 * 直接以独立入口运行，不经过 Typecho 路由：
 *   GET  api.php?action=like&cid=12   读取点赞数
 *   POST api.php?action=like&cid=12   点赞 / 取消点赞（幂等切换）
 *   GET  api.php?action=state&cids=1,2,3  批量读取点赞数与「我是否赞过」
 *   GET  api.php?action=ping            健康检查
 *   POST api.php?action=report          文章报错反馈（蜜罐 + 60s 限速，SMTP 通知博主）
 *
 * 去重靠访客 cookie（ecod_vid）+ 唯一索引，不再依赖 IP：
 * NAT / 校园网 / CDN 下每个浏览器仍是独立个体，刷新与爬虫也不会重复计数。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
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

/* JSON 出口不能被警告污染：PHP 8.5 起 curl_close() 之类的 Deprecated
 * 会以 <br /><b>Deprecated</b> 形式直接打进响应体，前端 fetch().json()
 * 立刻解析失败（2026-11-01 端到端自测抓到）。其它独立入口
 * （builder/widget/media/pay-admin）早就这么写了，这里补齐。
 * 需要用警告排查时临时把 display_errors 打开即可。 */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');

require_once __DIR__ . '/core/lib/schema.php';
require_once __DIR__ . '/core/lib/cache.php';
require_once __DIR__ . '/core/site/seo.php';
require_once __DIR__ . '/core/admin/snapshot.php';
require_once __DIR__ . '/core/site/pages.php';
require_once __DIR__ . '/core/user/account.php';
/* 用户扩展表：AI 日额度计数、看广告领点数的令牌都落这里，
   不能等 profile/avatar 分支再懒加载，否则 api 侧静默丢写入 */
require_once __DIR__ . '/core/user/meta.php';
require_once __DIR__ . '/core/user/oauth.php';
require_once __DIR__ . '/core/content/thumbs.php';
require_once __DIR__ . '/core/content/ai.php';
require_once __DIR__ . '/core/site/mail.php';
require_once __DIR__ . '/core/site/push.php';
require_once __DIR__ . '/core/site/golinks.php';
require_once __DIR__ . '/core/site/friends.php';
require_once __DIR__ . '/core/site/notifs.php';
require_once __DIR__ . '/core/pay/pay.php';
require_once __DIR__ . '/core/pay/pay-channel.php';
require_once __DIR__ . '/core/license/license.php';
require_once __DIR__ . '/core/license/selfserve.php';
require_once __DIR__ . '/core/user/credits.php';
require_once __DIR__ . '/core/user/tasks.php';
require_once __DIR__ . '/core/site/activity.php';
require_once __DIR__ . '/core/support/tickets.php';
require_once __DIR__ . '/core/user/referral.php';
require_once __DIR__ . '/core/site/lottery.php';
require_once __DIR__ . '/core/site/ads.php';
require_once __DIR__ . '/core/site/mcp.php';
require_once __DIR__ . '/core/site/shop.php';

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
 * 限流桶指纹：纯来源 IP
 *
 * 只用于频率账本（reg / flink / report / ai / poll 等动作桶），不进任何存储表。
 * 不能掺 ecod_api_visitor()：无 cookie 的脚本每请求都会生成新 vid，
 * 掺进去等于每个请求一个新桶，限流完全失效；纯 IP 下同源请求共享一个桶，
 * 清 cookie / 不带 cookie 都无法重置。
 */
function ecod_api_bucket_fp(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

/**
 * 轻量窗口限流（写操作）：每 IP 每 window 秒最多 limit 次
 *
 * 独立于 ecod_throttle_* 的分级失败账（那张账每 5 分钟返还一次，
 * 适合验证码试错）；这里窗口内只增不减，表不可用时放行不拦正常用户。
 *
 * @return int 需要等待的秒数，0 表示放行
 */
function ecod_api_rate_wait(string $kind, int $limit, int $window): int
{
    if ($limit <= 0 || $window <= 0 || !ecod_throttle_persistent_ready()) {
        return 0;
    }
    try {
        $db = \Typecho\Db::get();
        $key = hash('sha256', 'api:' . $kind . '|' . ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . intdiv(time(), $window));
        $row = $db->fetchRow(
            $db->select('n')->from(ecod_throttle_table())->where('thkey = ?', $key)->limit(1)
        );
        return ($row && (int) $row['n'] >= $limit) ? max(1, $window - (time() % $window)) : 0;
    } catch (\Throwable $e) {
        return 0;
    }
}

/**
 * 窗口限流记一次
 */
function ecod_api_rate_hit(string $kind, int $window): void
{
    if ($window <= 0 || !ecod_throttle_persistent_ready()) {
        return;
    }
    try {
        $db = \Typecho\Db::get();
        $table = ecod_throttle_table();
        $now = time();
        $key = hash('sha256', 'api:' . $kind . '|' . ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . intdiv($now, $window));
        $row = $db->fetchRow($db->select('n')->from($table)->where('thkey = ?', $key)->limit(1));
        $n = ($row ? (int) $row['n'] : 0) + 1;
        if ($row) {
            $db->query($db->update($table)->rows(['n' => $n, 'last' => $now])->where('thkey = ?', $key));
        } else {
            $db->query($db->insert($table)->rows(['thkey' => $key, 'n' => $n, 'last' => $now]));
        }
    } catch (\Throwable $e) {
        /* 落库失败只少一层限流，不影响正常请求 */
    }
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

/* 授权锁死：锁定状态下非白名单写操作直接 403 */
if (function_exists('ecod_license_locked') && ecod_license_locked()
    && function_exists('ecod_license_api_gate') && !ecod_license_api_gate($action)) {
    ecod_api_out(['ok' => false, 'error' => 'license'], 403);
}

/* 缩略图输出：GET + 二进制流，不走 JSON，必须在最前处理 */
if ($action === 'thumb') {
    ecod_api_thumb_serve();
}

/* MCP Server（F11）：JSON-RPC 协议入口，自带输出头与鉴权，
   不经过下面的同源 / 登录校验（客户端是桌面 App 或 CLI） */
if ($action === 'mcp') {
    ecod_mcp_serve();
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
    if (!in_array($platform, ecod_oauth_platforms(), true)) {
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

$ecodToolActions = ['snapshot_list', 'snapshot_save', 'snapshot_restore', 'snapshot_delete', 'build_sitemap', 'build_robots', 'push_baidu', 'push_test', 'golink_list', 'golink_save', 'golink_delete', 'page_list', 'page_create', 'topic_posts', 'ai_related_build'];

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

            /* AI 相关文章：批量让模型润色关键词（F13），每次最多 10 篇防超时 */
            case 'ai_related_build':
                $ecodRelRes = ecod_ai_rel_build((int) ($_POST['limit'] ?? 8));
                if (!empty($ecodRelRes['ok']) && (int) ($ecodRelRes['done'] ?? 0) > 0) {
                    ecod_audit_log('ai_related_build', ['done' => (int) $ecodRelRes['done']]);
                }
                ecod_api_out($ecodRelRes);

            case 'push_baidu':
                ecod_api_out(ecod_baidu_push());

            case 'push_test':
                ecod_api_out(ecod_push_test());

            case 'golink_list':
                ecod_api_out(['ok' => true, 'list' => ecod_golink_all(), 'api' => ecod_golink_url('{key}')]);

            case 'golink_save':
                ecod_api_out(ecod_golink_save(
                    (string) ($_POST['key'] ?? ''),
                    (string) ($_POST['target'] ?? '')
                ));

            case 'golink_delete':
                ecod_api_out(ecod_golink_delete((string) ($_POST['key'] ?? '')));

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

/* 访问统计的停留上报（navigator.sendBeacon 打过来，body 是 JSON）：
   只补停留时长与跳出，不参与 PV/UV 计数（那两个在页面请求时就记了） */
if ($action === 'stats_ping') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    $ecodPing = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($ecodPing)) {
        $ecodPing = [];
    }
    require_once __DIR__ . '/core/site/stats.php';
    ecod_stats_ping($ecodPing);
    /* sendBeacon 不关心响应体，给个最小答复即可 */
    ecod_api_out(['ok' => true]);
}

/* 访问统计台的数据接口（stats.php 的「刷新」与自动刷新走这里）：
   只读、仅管理员；返回的是渲染好的 HTML 片段，与首屏 SSR 共用
   core/site/stats-view.php，前端拿到直接替换对应容器。 */
if ($action === 'stats_data') {
    if (!ecod_api_is_admin()) {
        ecod_api_out(['ok' => false, 'error' => '需要管理员登录'], 403);
    }
    require_once __DIR__ . '/core/site/stats.php';
    require_once __DIR__ . '/core/site/stats-view.php';
    header('Cache-Control: no-store');
    ecod_api_out(['ok' => true, 'html' => ecod_stats_view_all((int) ($_GET['range'] ?? 14))]);
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
    $ecodAiBucket = 'ai:' . ecod_api_bucket_fp();
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

/* 侧栏「实时动态」挂件轮询（公开只读；来源白名单与 60s 缓存在聚合层内） */
if ($action === 'ticker') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    $ecodTkSources = preg_split('/[\s,，]+/', (string) ($_GET['sources'] ?? '')) ?: [];
    $ecodTkLimit = (int) ($_GET['limit'] ?? 8);
    if ($ecodTkLimit <= 0) {
        $ecodTkLimit = 8;
    }
    ecod_api_out([
        'ok'    => true,
        'items' => ecod_activity_feed($ecodTkSources, $ecodTkLimit),
    ]);
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

    $ecodBucket = 'flink:' . ecod_api_bucket_fp();
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
 * 前端投稿：登录用户提交文章（waiting 待审核），管理员后台发布
 *
 * 与友链申请同一套防线：同源 + CSRF 令牌 + 蜜罐 + 图形验证码 + 分级限流。
 * 状态固定 waiting（Typecho 后台「待审核」tab），管理员点发布即上线；
 * 空slug 会产生空路径段，入库后回填 post-{cid}，发布前管理员可自行改。
 * ---------------------------------------------------------------- */

/**
 * 投稿是否对当前用户开放：总开关 + 用户组下限（EContributeGroup）
 */
function ecod_api_contribute_ready($user): bool
{
    if (ecod_option('EContribute', 'off') !== 'on') {
        return false;
    }
    $min = (string) ecod_option('EContributeGroup', 'contributor');
    if (!in_array($min, ['subscriber', 'contributor'], true)) {
        $min = 'contributor';
    }
    try {
        return $user->pass($min === 'subscriber' ? 'subscriber' : 'contributor', true);
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * 投稿可选分类：顶级分类；EContributeCats 指定了 mid 时取交集
 *
 * @return array<int, array{name:string,mid:int}>
 */
function ecod_api_contribute_cats(): array
{
    $allowed = [];
    try {
        $db = \Typecho\Db::get();
        $rows = $db->fetchAll(
            $db->select('mid', 'name')->from('table.metas')
                ->where('type = ?', 'category')
                ->where('parent = ?', 0)
                ->order('order', \Typecho\Db::SORT_ASC)
        );
        $restrict = trim((string) ecod_option('EContributeCats', ''));
        $allowIds = [];
        if ($restrict !== '') {
            foreach (explode(',', $restrict) as $one) {
                $one = (int) trim($one);
                if ($one > 0) {
                    $allowIds[$one] = true;
                }
            }
        }
        foreach ($rows as $row) {
            $mid = (int) $row['mid'];
            if ($allowIds && empty($allowIds[$mid])) {
                continue;
            }
            $allowed[] = ['mid' => $mid, 'name' => (string) $row['name']];
        }
    } catch (\Throwable $e) {
    }
    return $allowed;
}

/**
 * 标签落库：按名字取 mid，不存在则建；返回 mid 列表
 *
 * @param string[] $tags
 * @return int[]
 */
function ecod_api_contribute_tag_ids(array $tags): array
{
    $db = \Typecho\Db::get();
    $ids = [];
    foreach ($tags as $name) {
        $name = trim($name);
        if ($name === '' || mb_strlen($name, 'UTF-8') > 20) {
            continue;
        }
        $row = $db->fetchRow(
            $db->select('mid')->from('table.metas')
                ->where('type = ?', 'tag')->where('name = ?', $name)->limit(1)
        );
        if ($row) {
            $ids[] = (int) $row['mid'];
            continue;
        }
        try {
            $db->query($db->insert('table.metas')->rows([
                'name' => $name,
                'slug' => '',
                'type' => 'tag',
                'description' => '',
                'count' => 0,
                'order' => 0,
                'parent' => 0,
            ]));
            $ids[] = (int) $db->fetchObject(
                $db->select('mid')->from('table.metas')
                    ->where('type = ?', 'tag')->where('name = ?', $name)->limit(1)
            )->mid;
        } catch (\Throwable $e) {
        }
    }
    return array_values(array_unique($ids));
}

if ($action === 'contribute_save') {
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
    if (!ecod_api_contribute_ready($me)) {
        ecod_api_out(['ok' => false, 'error' => '本站暂未开放投稿，或你的用户组暂无投稿权限'], 403);
    }
    if (!ecod_account_token_ok((string) ($_POST['token'] ?? ''))) {
        ecod_api_out(['ok' => false, 'error' => '令牌校验失败，请刷新页面重试'], 403);
    }
    /* 蜜罐：真人看不到，填了按垃圾提交静默吞掉 */
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        ecod_api_out(['ok' => true, 'cid' => 0, 'msg' => '已收到你的投稿，审核通过后会发布。']);
    }

    $ecodBucket = 'ctb:' . (int) $me->uid;
    $ecodWait = ecod_throttle_guard($ecodBucket);
    if ($ecodWait > 0) {
        ecod_api_out(['ok' => false, 'error' => ecod_throttle_error($ecodWait)], 403);
    }
    ecod_throttle_fail($ecodBucket);

    if (!ecod_captcha_verify((string) ($_POST['captcha'] ?? ''))) {
        ecod_api_out(['ok' => false, 'error' => '验证码不正确，请重新输入'], 400);
    }

    $title = trim((string) ($_POST['title'] ?? ''));
    $text = (string) ($_POST['text'] ?? '');
    $text = str_replace("\r\n", "\n", $text);
    $catMid = (int) ($_POST['category'] ?? 0);
    $cover = trim((string) ($_POST['cover'] ?? ''));
    $editCid = (int) ($_POST['cid'] ?? 0);
    $uid = (int) $me->uid;

    /* 标题：3-100 字，去掉首尾空白与不可见控制符 */
    $title = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', '', $title);
    if (mb_strlen($title, 'UTF-8') < 3 || mb_strlen($title, 'UTF-8') > 100) {
        ecod_api_out(['ok' => false, 'error' => '标题需为 3-100 个字符'], 400);
    }
    /* 正文：10-60000 字；剥离零宽字符 */
    $text = preg_replace('/[\x{200b}-\x{200f}\x{feff}]/u', '', $text);
    if (mb_strlen($text, 'UTF-8') < 10 || mb_strlen($text, 'UTF-8') > 60000) {
        ecod_api_out(['ok' => false, 'error' => '正文需为 10-60000 个字符'], 400);
    }
    /* 封面：可选，必须是 http(s) 图片地址 */
    if ($cover !== '' && !preg_match('#^https?://\S+\.(jpe?g|png|gif|webp|avif)(\?\S*)?$#i', $cover)
        && !preg_match('#^https?://#i', $cover)) {
        ecod_api_out(['ok' => false, 'error' => '封面地址需以 http(s):// 开头'], 400);
    }
    if (mb_strlen($cover, 'UTF-8') > 300) {
        ecod_api_out(['ok' => false, 'error' => '封面地址过长'], 400);
    }

    /* 标签：逗号 / 顿号 / 空格分隔，最多 5 个 */
    $tagsRaw = (string) ($_POST['tags'] ?? '');
    $tags = array_values(array_filter(array_map('trim', preg_split('/[,，、\s]+/u', $tagsRaw) ?: [])));
    if (count($tags) > 5) {
        $tags = array_slice($tags, 0, 5);
    }

    /* 分类：必须在允许清单里 */
    $catAllowed = ecod_api_contribute_cats();
    $catOk = false;
    foreach ($catAllowed as $one) {
        if ((int) $one['mid'] === $catMid) {
            $catOk = true;
            break;
        }
    }
    if (!$catOk) {
        ecod_api_out(['ok' => false, 'error' => '请选择一个可投稿的分类'], 400);
    }

    /* 编辑模式：只能改自己的待审核 / 草稿稿 */
    $db = \Typecho\Db::get();
    if ($editCid > 0) {
        $own = $db->fetchRow(
            $db->select('cid', 'status')->from('table.contents')
                ->where('cid = ?', $editCid)
                ->where('authorId = ?', $uid)
                ->where('type = ?', 'post')
                ->limit(1)
        );
        if (!$own || !in_array((string) $own['status'], ['waiting', 'draft'], true)) {
            ecod_api_out(['ok' => false, 'error' => '这篇投稿不存在或已进入审核流程'], 404);
        }
    }

    $now = time();
    try {
        if ($editCid > 0) {
            $db->query($db->update('table.contents')
                ->rows(['title' => $title, 'text' => '<!--markdown-->' . $text, 'modified' => $now])
                ->where('cid = ?', $editCid));
            $cid = $editCid;
            /* 重选分类 / 标签：旧关系清掉再写 */
            $db->query($db->delete('table.relationships')->where('cid = ?', $cid));
        } else {
            $cid = (int) $db->query($db->insert('table.contents')->rows([
                'title'        => $title,
                'slug'         => '',
                'created'      => $now,
                'modified'     => $now,
                'text'         => '<!--markdown-->' . $text,
                'authorId'     => $uid,
                'type'         => 'post',
                'status'       => 'waiting',
                'password'     => '',
                'commentsNum'  => 0,
                'allowComment' => 1,
                'allowPing'    => 1,
                'allowFeed'    => 1,
                'template'     => '',
                'order'        => 0,
            ]));
            /* slug 兜底：空 slug 在自定义路由下会产生空路径段 */
            if ($cid > 0) {
                $db->query($db->update('table.contents')
                    ->rows(['slug' => 'post-' . $cid])->where('cid = ?', $cid));
            }
        }

        if ($cid <= 0) {
            ecod_api_out(['ok' => false, 'error' => '保存失败，请稍后再试'], 500);
        }

        /* 分类关系 */
        $db->query($db->insert('table.relationships')->rows(['cid' => $cid, 'mid' => $catMid]));

        /* 标签关系 */
        foreach (ecod_api_contribute_tag_ids($tags) as $tagMid) {
            try {
                $db->query($db->insert('table.relationships')->rows(['cid' => $cid, 'mid' => $tagMid]));
            } catch (\Throwable $e) {
            }
        }

        /* 封面：写 / 更新自定义字段 thumb；编辑时清空即删字段 */
        $ecodThumbExists = $db->fetchRow(
            $db->select('cid')->from('table.fields')
                ->where('cid = ?', $cid)->where('name = ?', 'thumb')->limit(1)
        );
        if ($cover !== '') {
            if ($ecodThumbExists) {
                $db->query($db->update('table.fields')
                    ->rows(['str_value' => $cover])->where('cid = ?', $cid)->where('name = ?', 'thumb'));
            } else {
                $db->query($db->insert('table.fields')->rows([
                    'cid'       => $cid,
                    'name'      => 'thumb',
                    'type'      => 'str',
                    'str_value' => $cover,
                    'int_value' => 0,
                ]));
            }
        } elseif ($editCid > 0 && $ecodThumbExists) {
            $db->query($db->delete('table.fields')
                ->where('cid = ?', $cid)->where('name = ?', 'thumb'));
        }
    } catch (\Throwable $e) {
        ecod_api_out(['ok' => false, 'error' => '保存失败，请稍后再试'], 500);
    }

    ecod_throttle_clear($ecodBucket);
    ecod_api_out([
        'ok'  => true,
        'cid' => $cid,
        'msg' => $editCid > 0 ? '修改已保存' : '投稿成功，审核通过后会发布在站内',
    ]);
}

if ($action === 'contribute_delete') {
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
        ecod_api_out(['ok' => false, 'error' => '令牌校验失败，请刷新页面重试'], 403);
    }

    $cid = (int) ($_POST['cid'] ?? 0);
    $uid = (int) $me->uid;
    if ($cid <= 0) {
        ecod_api_out(['ok' => false, 'error' => 'Bad request'], 400);
    }

    try {
        $db = \Typecho\Db::get();
        $own = $db->fetchRow(
            $db->select('status')->from('table.contents')
                ->where('cid = ?', $cid)
                ->where('authorId = ?', $uid)
                ->where('type = ?', 'post')
                ->limit(1)
        );
        /* 已发布的不能从前端删：走后台流程，避免误删公开内容 */
        if (!$own || !in_array((string) $own['status'], ['waiting', 'draft'], true)) {
            ecod_api_out(['ok' => false, 'error' => '只能撤回待审核 / 草稿状态的投稿'], 400);
        }
        $db->query($db->delete('table.contents')->where('cid = ?', $cid));
        $db->query($db->delete('table.relationships')->where('cid = ?', $cid));
        $db->query($db->delete('table.fields')->where('cid = ?', $cid));
    } catch (\Throwable $e) {
        ecod_api_out(['ok' => false, 'error' => '删除失败，请稍后再试'], 500);
    }

    ecod_api_out(['ok' => true]);
}

/* 我的投稿列表：个人中心「我的文章」用（当前登录用户自己的全部状态稿） */
if ($action === 'contribute_list') {
    $me = ecod_api_user();
    if (!$me) {
        ecod_api_out(['ok' => false, 'error' => '请先登录'], 401);
    }
    try {
        $db = \Typecho\Db::get();
        $rows = $db->fetchAll(
            $db->select('cid', 'title', 'status', 'created', 'commentsNum', 'views')
                ->from('table.contents')
                ->where('authorId = ?', (int) $me->uid)
                ->where('type = ?', 'post')
                ->order('created', \Typecho\Db::SORT_DESC)
                ->limit(100)
        );
        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'cid'      => (int) $row['cid'],
                'title'    => (string) $row['title'],
                'status'   => (string) $row['status'],
                'created'  => (int) $row['created'],
                'comments' => ecod_comment_count((int) $row['cid']),
                'views'    => (int) ($row['views'] ?? 0),
            ];
        }
        ecod_api_out(['ok' => true, 'list' => $list]);
    } catch (\Throwable $e) {
        ecod_api_out(['ok' => false, 'error' => 'Server error'], 500);
    }
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

    $ecodBucket = 'reg:' . ecod_api_bucket_fp();
    $ecodWait = ecod_throttle_guard($ecodBucket);
    if ($ecodWait > 0) {
        ecod_api_out(['ok' => false, 'error' => ecod_throttle_error($ecodWait)], 403);
    }
    /* 每次尝试都计账（成功后 clear），防止拿接口慢慢探测用户名/邮箱 */
    ecod_throttle_fail($ecodBucket);

    /* 图形验证码：注册必须过人机验证（失败计一次，五次后进入分级冷却） */
    if (!ecod_captcha_verify((string) ($_POST['captcha'] ?? ''))) {
        ecod_api_out(['ok' => false, 'error' => '验证码不正确，请重新输入'], 403);
    }

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
    /* 邮箱验证码：证明邮箱归属，杜绝批量占用他人邮箱注册；
       站点没配邮箱服务时跳过（退回图形验证码 + 限流兜底），否则注册功能整个不可用 */
    if (ecod_account_mail_ready()
        && !ecod_account_mailcode_verify($mail, 'register', trim((string) ($_POST['code'] ?? '')))) {
        ecod_api_out(['ok' => false, 'error' => '邮箱验证码不正确或已过期'], 403);
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

    /* 推广归因：Cookie 里带 ?ref= 来的新用户，把推荐人写进 user_meta（只记第一次） */
    try {
        $ecodRefNew = $db->fetchRow(
            $db->select('uid')->from('table.users')->where('name = ?', $name)->limit(1)
        );
        if ($ecodRefNew) {
            ecod_ref_attribute((int) $ecodRefNew['uid']);
        }
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

/* 资料 / 密码 / 头像 / 封面：必须登录 + 同源 + CSRF 令牌 */
$ecodAccountActions = ['profile_save', 'password_save', 'avatar_upload', 'avatar_remove', 'cover_upload', 'cover_remove'];
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
        /* 一句话简介：存主题自己的 ecod_user_meta 表，作者主页头卡 / 文末作者卡读这里 */
        $intro = trim((string) ($_POST['intro'] ?? ''));
        $intro = preg_replace('/\s+/u', ' ', $intro);
        if (mb_strlen($intro, 'UTF-8') > 120) {
            $intro = mb_substr($intro, 0, 120, 'UTF-8');
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
            require_once __DIR__ . '/core/user/meta.php';
            ecod_user_meta_set($ecodUid, 'intro', $intro);
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

    /* 上传个人中心封面：jpg/png/webp/gif，最大 8MB，存 usr/uploads/ecod-avatar，meta 记 cover */
    if ($action === 'cover_upload') {
        $file = $_FILES['cover'] ?? null;
        if (!$file || !is_array($file) || (int) ($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
            ecod_api_out(['ok' => false, 'error' => '文件上传失败'], 400);
        }
        if ((int) $file['size'] > 8 * 1024 * 1024) {
            ecod_api_out(['ok' => false, 'error' => '图片不能超过 8MB'], 400);
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

        $name = $ecodUid . '-cover-' . time() . '.' . $ext;
        $target = $dir . '/' . $name;
        if (!@move_uploaded_file((string) $file['tmp_name'], $target)) {
            ecod_api_out(['ok' => false, 'error' => '保存文件失败，请检查目录权限'], 500);
        }
        @chmod($target, 0644);

        /* 覆盖旧封面文件 */
        $old = ecod_account_cover_url($ecodUid);
        if ($old !== '') {
            $oldFile = ecod_account_avatar_file($old);
            if ($oldFile !== '' && is_file($oldFile)) {
                @unlink($oldFile);
            }
        }

        require_once __DIR__ . '/core/user/meta.php';
        $rel = '/usr/uploads/ecod-avatar/' . $name;
        if (!ecod_user_meta_set($ecodUid, 'cover', $rel)) {
            @unlink($target);
            ecod_api_out(['ok' => false, 'error' => '写入封面记录失败'], 500);
        }

        ecod_api_out(['ok' => true, 'url' => $rel]);
    }

    /* 移除自定义封面（回落主题默认封面） */
    if ($action === 'cover_remove') {
        ecod_api_out(['ok' => ecod_account_del_cover($ecodUid)]);
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
 *
 * 构造逻辑已下沉到 core/pay/pay.php 的 ecod_pay_post_stub()，供 AI 上下文等
 * 其它独立入口复用；这里保留原函数名，避免改动既有调用点。
 */
function ecod_api_pay_post(int $cid)
{
    return function_exists('ecod_pay_post_stub') ? ecod_pay_post_stub($cid) : null;
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
        $paytype = strtolower(trim((string) ($_POST['paytype'] ?? '')));
        $payload = $channel->create($order, $paytype !== '' ? ['type' => $paytype] : []);
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

/* 为已存在的 pending 订单发起支付（收银台 ?order_no= 直达：广告位「去支付」、支付回跳续付） */
if ($action === 'pay_order') {
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
    $orderNo = trim((string) ($_POST['order_no'] ?? ''));
    $order = $orderNo !== '' ? ecod_pay_order_get($orderNo) : null;
    if (!$order || (int) $order['uid'] !== $uid) {
        ecod_api_out(['ok' => false, 'error' => '订单不存在'], 404);
    }
    if ((string) $order['status'] !== 'pending') {
        ecod_api_out(['ok' => false, 'error' => '订单当前状态不可支付'], 400);
    }

    /* 通道：请求里没带就沿用下单时的通道；卡密只适用于新下单（卡自带权益），不适用已有订单 */
    $channelId = (string) ($_POST['channel'] ?? '');
    if ($channelId === '') {
        $channelId = trim((string) $order['channel']) !== '' ? (string) $order['channel'] : ecod_pay_default_channel();
    }
    if ($channelId === 'card') {
        ecod_api_out(['ok' => false, 'error' => '已有订单不能用卡密支付，请返回重新下单并选卡密通道'], 400);
    }

    try {
        $channel = ecod_pay_channel($channelId);
        $paytype = strtolower(trim((string) ($_POST['paytype'] ?? '')));
        $payload = $channel->create($order, $paytype !== '' ? ['type' => $paytype] : []);
    } catch (\Throwable $e) {
        ecod_api_out(['ok' => false, 'error' => '支付通道不可用'], 500);
    }

    ecod_api_out([
        'ok'       => true,
        'order_no' => (string) $order['order_no'],
        'mode'     => (string) ($payload['mode'] ?? 'manual'),
        'url'      => (string) ($payload['url'] ?? ''),
        'html'     => (string) ($payload['html'] ?? ''),
        'tip'      => (string) ($payload['tip'] ?? ''),
        'amount'   => (int) $order['amount'],
    ]);
}

/* 卡密直兑（个人中心「我的会员」tab）：与收银台 card 通道共用同一核销函数 */
if ($action === 'pay_redeem') {
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

    $bucket = 'vipredeem:' . (int) $me->uid;
    $wait = ecod_throttle_guard($bucket);
    if ($wait > 0) {
        ecod_api_out(['ok' => false, 'error' => ecod_throttle_error($wait)], 429);
    }
    ecod_throttle_fail($bucket);

    $code = trim((string) ($_POST['code'] ?? ''));
    if ($code === '' || mb_strlen($code) > 64) {
        ecod_api_out(['ok' => false, 'error' => '请输入有效的卡密'], 400);
    }

    $result = ecod_pay_card_redeem($code, (int) $me->uid);
    if (empty($result['ok'])) {
        ecod_api_out(['ok' => false, 'error' => (string) ($result['message'] ?? '兑换失败')], 400);
    }

    $vip = ecod_pay_vip((int) $me->uid);
    ecod_api_out([
        'ok'      => true,
        'message' => (string) ($result['message'] ?? '兑换成功'),
        'kind'    => (string) ($result['kind'] ?? ''),
        'level'   => (int) ($result['level'] ?? 0),
        'days'    => (int) ($result['days'] ?? 0),
        'vip'     => $vip,
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

    /* 补单：epay 订单还挂着 pending 时向通道查一次，
       对付「回调丢失 / 用户付完没等回调就关页」的情况 */
    if ((string) $order['status'] === 'pending'
        && (string) $order['channel'] === 'epay'
        && ecod_pay_epay_ready()) {
        $remote = ecod_pay_epay_query_throttled($orderNo);
        if ($remote && !empty($remote['paid'])) {
            $settled = ecod_pay_settle($orderNo, (string) ($remote['trade_no'] ?? ''), 'epay', (int) ($remote['amount'] ?? -1));
            if (!empty($settled['ok'])) {
                ecod_audit_log('pay_settle', ['order' => $orderNo, 'via' => 'status_query']);
            }
            if (!empty($settled['ok']) || $settled['status'] === 'paid') {
                $order = ecod_pay_order_get($orderNo) ?? $order;
            }
        }
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

/* 易支付同步回跳：验签后结算并跳回收银台；
   验签失败但订单还挂着 pending 时再查一次单兜底（有的站 return 不带签名） */
if ($action === 'pay_return') {
    $params = array_merge($_GET, $_POST);
    $channel = new EcodPayEpayChannel();
    $verified = $channel->verify($params);

    $orderNo = (string) ($verified['order_no'] ?? '');
    $ecodPayOk = false;

    if (!empty($verified['ok'])) {
        $ecodSettle = ecod_pay_settle($orderNo, (string) ($verified['trade_no'] ?? ''), 'epay', (int) ($verified['amount'] ?? -1));
        if (!empty($ecodSettle['ok'])) {
            ecod_audit_log('pay_settle', ['order' => $orderNo, 'via' => 'return']);
        }
        $ecodPayOk = !empty($ecodSettle['ok']) || ($ecodSettle['status'] ?? '') === 'paid';
    } elseif ($orderNo !== '' && ecod_pay_epay_ready()) {
        $ecodOrder = ecod_pay_order_get($orderNo);
        if ($ecodOrder && (string) $ecodOrder['status'] === 'pending') {
            $remote = ecod_pay_epay_query($orderNo);
            if ($remote && !empty($remote['paid'])) {
                $ecodSettle = ecod_pay_settle($orderNo, (string) ($remote['trade_no'] ?? ''), 'epay', (int) ($remote['amount'] ?? -1));
                if (!empty($ecodSettle['ok'])) {
                    ecod_audit_log('pay_settle', ['order' => $orderNo, 'via' => 'return_query']);
                }
                $ecodPayOk = !empty($ecodSettle['ok']) || ($ecodSettle['status'] ?? '') === 'paid';
            }
        } elseif ($ecodOrder && (string) $ecodOrder['status'] === 'paid') {
            $ecodPayOk = true;
        }
    }

    $cashierQuery = [];
    if ($orderNo !== '') {
        $cashierQuery['order_no'] = $orderNo;
        $cashierQuery['epay'] = $ecodPayOk ? 'ok' : 'fail';
    }
    $cashier = ecod_pay_cashier_url($cashierQuery);
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
    if (!ecod_api_same_origin()) {
        ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
    }
    if ($cid <= 0) {
        ecod_api_out(['ok' => false, 'error' => 'Invalid cid'], 400);
    }
    /* 每 IP 每分钟 20 次：收藏本身幂等，但插入会触发站内通知，裸奔会被刷爆通知箱 */
    $ecodFavWait = ecod_api_rate_wait('fav', 20, 60);
    if ($ecodFavWait > 0) {
        ecod_api_out(['ok' => false, 'error' => '操作太频繁了，请 ' . $ecodFavWait . ' 秒后再试'], 429);
    }
    ecod_api_rate_hit('fav', 60);

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
                        /* 站内通知：收藏给文章作者打招呼（作者自己收藏不通知） */
                        $ecodFavMe = ecod_api_user();
                        ecod_notif_fav(
                            $cid,
                            $ecodFavMe ? (int) $ecodFavMe->uid : 0,
                            $ecodFavMe ? (string) $ecodFavMe->screenName : null
                        );
                    } catch (\Throwable $e) {
                        /* 唯一索引冲突 = 并发重复收藏，按已收藏处理 */
                        $exists = true;
                    }
                }
            }

            /* 动作条展示收藏总数：切换后前端用这个数就地更新 */
            $row = $db->fetchObject(
                $db->select('COUNT(*) AS num')->from($table)->where('cid = ?', $cid)
            );

            return ['fav' => $exists, 'count' => $row ? (int) $row->num : 0];
        });

        ecod_api_out(['ok' => true, 'cid' => $cid] + $result);
    } catch (\Throwable $e) {
        ecod_api_out(['ok' => false, 'error' => 'Server error'], 500);
    }
}

/* ------------------------------------------------------------------
 * 站内消息通知：登录用户专属
 *
 *   GET  ?action=notif_unread    未读数（header 铃铛角标轮询）
 *   GET  ?action=notif_list      消息列表（铃铛下拉与个人中心共用）
 *   POST ?action=notif_read      标记已读（id=123 单条；all=1 全部）
 *   POST ?action=notif_clear     清空「已读」的消息（未读保留）
 *   POST ?action=notif_broadcast 管理员全站公告（工具台一键发）
 * ---------------------------------------------------------------- */
$ecodNotifAction = (string) ($_GET['action'] ?? $_POST['action'] ?? '');

if ($ecodNotifAction === 'notif_unread') {
    $ecodMe = ecod_api_user();
    ecod_api_out($ecodMe
        ? ['ok' => true, 'unread' => ecod_notif_unread((int) $ecodMe->uid)]
        : ['ok' => true, 'unread' => 0]);
}

if ($ecodNotifAction === 'notif_list') {
    $ecodMe = ecod_api_user();
    if (!$ecodMe) {
        ecod_api_out(['ok' => false, 'error' => '请先登录'], 401);
    }
    $ecodUid = (int) $ecodMe->uid;
    $ecodLimit = (int) ($_GET['limit'] ?? 20) ?: 20;
    $ecodOffset = max(0, (int) ($_GET['offset'] ?? 0));
    ecod_api_out([
        'ok'     => true,
        'unread' => ecod_notif_unread($ecodUid),
        'list'   => ecod_notif_list($ecodUid, $ecodLimit, $ecodOffset),
        'counts' => ecod_notif_counts($ecodUid),
        'offset' => $ecodOffset,
    ]);
}

if ($ecodNotifAction === 'notif_read') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    $ecodMe = ecod_api_user();
    if (!$ecodMe) {
        ecod_api_out(['ok' => false, 'error' => '请先登录'], 401);
    }
    $ecodId = (int) ($_POST['id'] ?? 0);
    ecod_notif_read((int) $ecodMe->uid, $ecodId);
    ecod_api_out(['ok' => true, 'unread' => ecod_notif_unread((int) $ecodMe->uid)]);
}

if ($ecodNotifAction === 'notif_clear') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    $ecodMe = ecod_api_user();
    if (!$ecodMe) {
        ecod_api_out(['ok' => false, 'error' => '请先登录'], 401);
    }
    ecod_notif_clear((int) $ecodMe->uid);
    ecod_api_out(['ok' => true, 'unread' => ecod_notif_unread((int) $ecodMe->uid)]);
}

if ($ecodNotifAction === 'notif_broadcast') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    if (!ecod_api_same_origin()) {
        ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
    }
    if (!ecod_friend_app_is_admin()) {
        ecod_api_out(['ok' => false, 'error' => '需要管理员登录'], 403);
    }
    /* 广播由后台「工具台」发出：校验工具令牌（与快照等工具动作同源） */
    $ecodToken = (string) ($_POST['token'] ?? '');
    if ($ecodToken === '' || !hash_equals(ecod_api_admin_token(), $ecodToken)) {
        ecod_api_out(['ok' => false, 'error' => '令牌校验失败，请刷新页面重试'], 403);
    }

    $ecodTitle = trim((string) ($_POST['title'] ?? ''));
    if ($ecodTitle === '') {
        ecod_api_out(['ok' => false, 'error' => '公告标题不能为空'], 400);
    }
    $ecodLink = trim((string) ($_POST['link'] ?? ''));
    if ($ecodLink !== '' && !preg_match('#^https?://#i', $ecodLink) && !str_starts_with($ecodLink, '/')) {
        ecod_api_out(['ok' => false, 'error' => '链接需要是 http(s) 或站内绝对路径'], 400);
    }

    $ecodCount = ecod_notif_broadcast($ecodTitle, trim((string) ($_POST['body'] ?? '')), $ecodLink);
    ecod_api_out(['ok' => true, 'count' => $ecodCount, 'msg' => '已推送给 ' . $ecodCount . ' 位用户']);
}

/* ------------------------------------------------------------------
 * 任务中心：登录用户专属
 *
 *   GET  ?action=task_list    任务全量（进度实时 COUNT，不落库）
 *   POST ?action=task_claim   领取（key），幂等靠 ecod_task_claims 唯一键
 *
 * 积分总开关关闭时整块不下发——前端 tab 也据此隐藏，避免点了必被拒。
 * ---------------------------------------------------------------- */
if ($action === 'task_list') {
    $ecodMe = ecod_api_user();
    if (!$ecodMe) {
        ecod_api_out(['ok' => false, 'error' => '请先登录'], 401);
    }
    if (!ecod_credits_enabled()) {
        ecod_api_out(['ok' => false, 'error' => '积分未开启'], 403);
    }
    ecod_api_out(['ok' => true] + ecod_tasks_list((int) $ecodMe->uid));
}

if ($action === 'task_claim') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    if (!ecod_api_same_origin()) {
        ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
    }
    $ecodMe = ecod_api_user();
    if (!$ecodMe) {
        ecod_api_out(['ok' => false, 'error' => '请先登录'], 401);
    }
    if (!ecod_account_token_ok((string) ($_POST['token'] ?? ''))) {
        ecod_api_out(['ok' => false, 'error' => '页面已过期，请刷新重试'], 403);
    }
    if (!ecod_credits_enabled()) {
        ecod_api_out(['ok' => false, 'error' => '积分未开启'], 403);
    }

    $ecodKey = trim((string) ($_POST['key'] ?? ''));
    if ($ecodKey === '') {
        ecod_api_out(['ok' => false, 'error' => '缺少任务标识'], 400);
    }

    $ecodResult = ecod_tasks_claim((int) $ecodMe->uid, $ecodKey);
    if (empty($ecodResult['ok'])) {
        ecod_api_out($ecodResult, 400);
    }
    ecod_api_out($ecodResult);
}

/* ------------------------------------------------------------------
 * 工单：用户侧完整闭环（提交 / 回复 / 关闭）
 *
 *   GET  ?action=ticket_list    status? page?
 *   GET  ?action=ticket_detail  id
 *   POST ?action=ticket_create  cat title body token
 *   POST ?action=ticket_reply   id body token
 *   POST ?action=ticket_close   id token
 *
 * 读接口只校验登录（本人 uid 过滤）；写接口额外校验同源 + 令牌。
 * ---------------------------------------------------------------- */
if (in_array($action, ['ticket_list', 'ticket_detail', 'ticket_create', 'ticket_reply', 'ticket_close'], true)) {
    $ecodMe = ecod_api_user();
    if (!$ecodMe) {
        ecod_api_out(['ok' => false, 'error' => '请先登录'], 401);
    }
    $ecodUid = (int) $ecodMe->uid;

    if ($action === 'ticket_list') {
        $ecodStatus = (string) ($_GET['status'] ?? '');
        if (!in_array($ecodStatus, ['', 'pending', 'replied', 'resolved', 'closed'], true)) {
            $ecodStatus = '';
        }
        $ecodPage = max(1, (int) ($_GET['page'] ?? 1));
        $ecodData = ecod_ticket_list($ecodUid, $ecodStatus, $ecodPage, 20);
        ecod_api_out([
            'ok'       => true,
            'items'    => $ecodData['items'],
            'stats'    => $ecodData['stats'],
            'has_more' => $ecodData['has_more'],
            'page'     => $ecodPage,
        ]);
    }

    if ($action === 'ticket_detail') {
        $ecodId = (int) ($_GET['id'] ?? 0);
        if ($ecodId <= 0) {
            ecod_api_out(['ok' => false, 'error' => '缺少工单 id'], 400);
        }
        $ecodData = ecod_ticket_detail($ecodUid, $ecodId);
        ecod_api_out($ecodData, empty($ecodData['ok']) ? 400 : 200);
    }

    /* 以下为写操作 */
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    if (!ecod_api_same_origin()) {
        ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
    }
    if (!ecod_account_token_ok((string) ($_POST['token'] ?? ''))) {
        ecod_api_out(['ok' => false, 'error' => '页面已过期，请刷新重试'], 403);
    }

    if ($action === 'ticket_create') {
        /* 频控在 ecod_ticket_create 内做（24h ≤5 单、未关闭 ≤3 单），
           不再叠 throttle——正常用户一天提 5 单不该被锁 */
        $ecodData = ecod_ticket_create(
            $ecodUid,
            (string) ($_POST['cat'] ?? 'other'),
            (string) ($_POST['title'] ?? ''),
            (string) ($_POST['body'] ?? '')
        );
        if (empty($ecodData['ok'])) {
            ecod_api_out($ecodData, 400);
        }
        ecod_api_out($ecodData);
    }

    if ($action === 'ticket_reply') {
        $ecodId = (int) ($_POST['id'] ?? 0);
        if ($ecodId <= 0) {
            ecod_api_out(['ok' => false, 'error' => '缺少工单 id'], 400);
        }
        $ecodData = ecod_ticket_reply($ecodUid, $ecodId, (string) ($_POST['body'] ?? ''));
        if (empty($ecodData['ok'])) {
            ecod_api_out($ecodData, 400);
        }
        ecod_api_out($ecodData);
    }

    if ($action === 'ticket_close') {
        $ecodId = (int) ($_POST['id'] ?? 0);
        if ($ecodId <= 0) {
            ecod_api_out(['ok' => false, 'error' => '缺少工单 id'], 400);
        }
        $ecodData = ecod_ticket_close($ecodUid, $ecodId);
        if (empty($ecodData['ok'])) {
            ecod_api_out($ecodData, 400);
        }
        ecod_api_out($ecodData);
    }
}

/* ------------------------------------------------------------------
 * 文章报错反馈：前端动作条「报错」弹层提交，直接邮件通知博主
 *
 * 不落库（设计稿约束：不改数据结构），防滥用靠三道：
 *   ① POST + 同源校验；② 蜜罐（真人不可见字段填写 → 假成功静默丢弃）；
 *   ③ 同访客 60 秒内一份（cookie 时间戳）+ 通用分级限流给重复提交加间隔。
 * SMTP 未配置时返回明确文案，引导用户去评论区留言。
 * ---------------------------------------------------------------- */
if ($action === 'report') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    if (!ecod_api_same_origin()) {
        ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
    }

    $ecodRepReasons = [
        'wrong'     => '内容有误',
        'dead-link' => '链接失效',
        'no-image'  => '图片缺失',
        'other'     => '其他问题',
    ];

    /* 蜜罐：字段被人填了就是机器人，返回成功但不做任何事 */
    $ecodRepHp = trim((string) ($_POST['hp'] ?? ''));
    $ecodRepOkFake = static function () {
        ecod_api_out(['ok' => true, 'msg' => '已收到，感谢反馈']);
    };
    if ($ecodRepHp !== '') {
        $ecodRepOkFake();
    }

    $ecodRepReason = (string) ($_POST['reason'] ?? '');
    if (!isset($ecodRepReasons[$ecodRepReason])) {
        ecod_api_out(['ok' => false, 'error' => '请选择问题类型'], 400);
    }
    if ($cid <= 0) {
        ecod_api_out(['ok' => false, 'error' => '文章不存在'], 400);
    }

    /* 同访客 60 秒内只放行一份：cookie 时间戳（api 域独立 cookie，无登录依赖） */
    $ecodRepLast = isset($_COOKIE['ecod_rts']) ? (int) $_COOKIE['ecod_rts'] : 0;
    if ($ecodRepLast > 0 && time() - $ecodRepLast < 60) {
        ecod_api_out(['ok' => false, 'error' => '刚才已收到反馈，请稍后再提交'], 429);
    }

    try {
        $db = \Typecho\Db::get();
        $ecodRepPost = $db->fetchRow(
            $db->select('cid', 'title', 'slug', 'created')->from('table.contents')
                ->where('cid = ?', $cid)
                ->where('type = ?', 'post')
                ->where('status = ?', 'publish')
        );
        if (!$ecodRepPost) {
            ecod_api_out(['ok' => false, 'error' => '文章不存在'], 404);
        }

        /* 分级限流：与登录同一套账本，重复举报越刷越慢（每次提交都记账，成功不清零） */
        $ecodRepBucket = 'report:' . ecod_api_bucket_fp();
        $ecodRepWait = ecod_throttle_guard($ecodRepBucket);
        if ($ecodRepWait > 0) {
            ecod_api_out(['ok' => false, 'error' => ecod_throttle_error($ecodRepWait)], 429);
        }
        ecod_throttle_fail($ecodRepBucket);

        $ecodRepNote = trim(strip_tags((string) ($_POST['note'] ?? '')));
        if (mb_strlen($ecodRepNote, 'UTF-8') > 200) {
            $ecodRepNote = mb_substr($ecodRepNote, 0, 200, 'UTF-8');
        }

        if (!ecod_mail_ready()) {
            ecod_api_out(['ok' => false, 'error' => '站长暂未开启邮件通知，可直接在评论区留言反馈']);
        }

        /* 文章链接：路由表可用就生成 permalink，兜底站点首页 */
        $ecodRepLink = '';
        try {
            $ecodRouting = is_array(\Widget\Options::alloc()->routingTable)
                ? \Widget\Options::alloc()->routingTable
                : json_decode((string) \Widget\Options::alloc()->routingTable, true);
            if (is_array($ecodRouting)) {
                \Typecho\Router::setRoutes($ecodRouting);
                $ecodRepLink = \Typecho\Router::pathTo('post', $ecodRepPost);
            }
        } catch (\Throwable $e) {
            $ecodRepLink = '';
        }
        $ecodRepSite = (string) ecod_mail_opt('siteUrl', '');

        $ecodRepTitle = (string) ecod_mail_opt('title', '站点');
        $ecodRepSubject = '「' . $ecodRepTitle . '」收到文章报错 · ' . $ecodRepReasons[$ecodRepReason];

        $ecodRepRows = [
            '类型'   => $ecodRepReasons[$ecodRepReason],
            '文章'   => $ecodRepPost['title'],
            '时间'   => date('Y-m-d H:i'),
            '补充说明' => $ecodRepNote !== '' ? mb_substr($ecodRepNote, 0, 600, 'UTF-8') : '（无）',
        ];
        $ecodRepHtml = '<div style="font:14px/1.7 -apple-system,Segoe UI,PingFang SC,sans-serif;color:#1e2430">'
            . '<p style="margin:0 0 12px"><b>' . htmlspecialchars($ecodRepSubject, ENT_QUOTES, 'UTF-8') . '</b></p>'
            . '<table cellpadding="6" style="border-collapse:collapse;font-size:13px">';
        foreach ($ecodRepRows as $ecodRepLabel => $ecodRepValue) {
            $ecodRepHtml .= '<tr><td style="color:#5b6472;vertical-align:top;white-space:nowrap">' . $ecodRepLabel . '</td>'
                . '<td>' . nl2br(htmlspecialchars((string) $ecodRepValue, ENT_QUOTES, 'UTF-8')) . '</td></tr>';
        }
        $ecodRepHtml .= '</table>';
        if ($ecodRepLink !== '') {
            $ecodRepHtml .= '<p style="margin:14px 0 0"><a href="'
                . htmlspecialchars($ecodRepLink, ENT_QUOTES, 'UTF-8') . '">查看这篇内容</a></p>';
        }
        $ecodRepHtml .= '</div>';

        if (!ecod_mail_smtp(ecod_mail_config(), $ecodRepSubject, $ecodRepHtml)) {
            error_log('[ecod-mail] report send failed for cid ' . $cid);
            ecod_api_out(['ok' => false, 'error' => '发送失败，请直接在评论区留言反馈']);
        }

        /* 放行成功才写下时间戳：下一份最早在 60 秒后 */
        setcookie('ecod_rts', (string) time(), [
            'expires' => time() + 3600,
            'path' => '/',
            'samesite' => 'Lax',
            'secure' => (isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) === 'on'),
        ]);

        $ecodRepOkFake();
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

/* ------------------------------------------------------------------
 * 评论点赞：与文章点赞同构（指纹去重 + 登录补记 uid）
 *
 * GET  ?action=clike&coid=12   读取一条评论的点赞数与「我是否赞过」
 * POST ?action=clike&coid=12   点赞 / 取消（幂等切换）
 * GET  ?action=cstate&coids=1,2,3  批量回填（评论列表一次查全）
 * ---------------------------------------------------------------- */

if ($action === 'clike' || $action === 'cstate') {
    /* 点赞是写操作（插行 + 站内通知），同源 + 每 IP 每分钟 30 次；cstate 是只读查询不限 */
    $ecodClikePost = $action === 'clike'
        && (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    if ($ecodClikePost) {
        if (!ecod_api_same_origin()) {
            ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
        }
        $ecodClikeWait = ecod_api_rate_wait('clike', 30, 60);
        if ($ecodClikeWait > 0) {
            ecod_api_out(['ok' => false, 'error' => '操作太频繁了，请 ' . $ecodClikeWait . ' 秒后再试'], 429);
        }
        ecod_api_rate_hit('clike', 60);
    }

    try {
        $db = \Typecho\Db::get();
        $table = ecod_clikes_table();
        $fp = ecod_api_fingerprint();
        $me = ecod_api_user();
        $uid = $me ? (int) $me->uid : 0;

        $ensure = static function (callable $fn) use ($db) {
            try {
                return $fn(ecod_clikes_table());
            } catch (\Throwable $e) {
                if (!ecod_schema_ensure_clikes($db)) {
                    throw $e;
                }
                return $fn(ecod_clikes_table());
            }
        };

        if ($action === 'cstate') {
            $raw = isset($_GET['coids']) ? (string) $_GET['coids'] : '';
            $coids = [];
            foreach (explode(',', $raw) as $one) {
                $one = (int) trim($one);
                if ($one > 0 && !in_array($one, $coids, true)) {
                    $coids[] = $one;
                }
                if (count($coids) >= 200) {
                    break;
                }
            }
            if (!$coids) {
                ecod_api_out(['ok' => true, 'counts' => new stdClass(), 'liked' => []]);
            }

            $payload = $ensure(function (string $table) use ($db, $coids, $fp, $uid) {
                $in = implode(',', $coids);
                $counts = array_fill_keys($coids, 0);
                $rows = $db->fetchAll(
                    $db->select('coid', 'COUNT(*) AS num')->from($table)
                        ->where('coid IN (' . $in . ')')->group('coid')
                );
                foreach ($rows as $row) {
                    $counts[(int) $row['coid']] = (int) $row['num'];
                }

                $liked = [];
                $mine = $uid > 0
                    ? $db->fetchAll($db->select('coid')->from($table)->where('coid IN (' . $in . ')')->where('ip = ? OR uid = ?', $fp, $uid))
                    : $db->fetchAll($db->select('coid')->from($table)->where('coid IN (' . $in . ')')->where('ip = ?', $fp));
                foreach ($mine as $row) {
                    $liked[] = (int) $row['coid'];
                }
                return ['counts' => $counts, 'liked' => $liked];
            });

            ecod_api_out(['ok' => true] + $payload);
        }

        /* 单条点赞 */
        $coid = isset($_REQUEST['coid']) ? (int) $_REQUEST['coid'] : 0;
        if ($coid <= 0) {
            ecod_api_out(['ok' => false, 'error' => 'Invalid coid'], 400);
        }

        $comment = $db->fetchRow(
            $db->select('coid', 'cid', 'status')->from('table.comments')
                ->where('coid = ?', $coid)->limit(1)
        );
        if (!$comment || (string) $comment['status'] !== 'approved') {
            ecod_api_out(['ok' => false, 'error' => 'Comment not found'], 404);
        }
        $cid = (int) $comment['cid'];

        $result = $ensure(function (string $table) use ($db, $cid, $coid, $fp, $uid) {
            $isPost = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

            if ($isPost) {
                $uidHit = $uid > 0 ? (bool) $db->fetchRow(
                    $db->select('coid')->from($table)
                        ->where('coid = ?', $coid)->where('uid = ?', $uid)->limit(1)
                ) : false;

                if ($uidHit) {
                    /* 登录用户已赞过（可能是旧设备落的行）：再点 = 取消，按 uid 行删 */
                    $db->query($db->delete($table)->where('coid = ?', $coid)->where('uid = ?', $uid));
                } else {
                    try {
                        $row = ['cid' => $cid, 'coid' => $coid, 'ip' => $fp, 'created' => time()];
                        if ($uid > 0) {
                            $row['uid'] = $uid;
                        }
                        $db->query($db->insert($table)->rows($row));
                        /* 站内通知：评论被点赞 → 提醒评论作者 */
                        $ecodClikeMe = ecod_api_user();
                        ecod_notif_clike($coid, $ecodClikeMe ? (int) $ecodClikeMe->uid : 0);
                    } catch (\Throwable $e) {
                        /* 唯一索引冲突 = 当前指纹已赞过：切一次视为取消 */
                        $db->query($db->delete($table)->where('coid = ?', $coid)->where('ip = ?', $fp));
                    }
                }
            }

            if ($uid > 0) {
                $mine = (bool) $db->fetchRow(
                    $db->select('coid')->from($table)
                        ->where('coid = ?', $coid)
                        ->where('ip = ? OR uid = ?', $fp, $uid)
                        ->limit(1)
                );
            } else {
                $mine = (bool) $db->fetchRow(
                    $db->select('coid')->from($table)
                        ->where('coid = ?', $coid)->where('ip = ?', $fp)->limit(1)
                );
            }

            $count = (int) $db->fetchObject(
                $db->select('COUNT(*) AS num')->from($table)->where('coid = ?', $coid)
            )->num;

            /* 切换语义：返回操作后的真实状态，前端据此点亮按钮 */
            return ['liked' => $mine, 'mine' => $mine, 'count' => $count];
        });

        ecod_api_out(['ok' => true, 'coid' => $coid] + $result);
    } catch (\Throwable $e) {
        ecod_api_out(['ok' => false, 'error' => 'Server error'], 500);
    }
}

/* ------------------------------------------------------------------
 * 关注作者：登录用户关注 / 取关（幂等切换）
 *
 * GET  ?action=follow_state&uids=1,2,3   批量回填「我是否关注」与粉丝数
 * POST ?action=follow_toggle  uid=目标    切换关注
 * GET  ?action=follow_list&type=following 当前登录用户的关注 / 粉丝列表
 * ---------------------------------------------------------------- */
if ($action === 'follow_toggle' || $action === 'follow_state' || $action === 'follow_list') {
    require_once __DIR__ . '/core/user/follow.php';
    $me = ecod_api_user();
    if (!$me) {
        ecod_api_out(['ok' => false, 'error' => '请先登录'], 401);
    }
    $myUid = (int) $me->uid;

    if ($action === 'follow_toggle') {
        if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
        }
        if (!ecod_api_same_origin()) {
            ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
        }
        if (!ecod_account_token_ok((string) ($_POST['token'] ?? ''))) {
            ecod_api_out(['ok' => false, 'error' => '令牌校验失败，请刷新页面重试'], 403);
        }

        $target = (int) ($_POST['uid'] ?? 0);
        if ($target <= 0) {
            ecod_api_out(['ok' => false, 'error' => 'Bad request'], 400);
        }
        if ($target === $myUid) {
            ecod_api_out(['ok' => false, 'error' => '不能关注自己'], 400);
        }

        $exists = \Typecho\Db::get()->fetchRow(
            \Typecho\Db::get()->select('uid')->from('table.users')->where('uid = ?', $target)->limit(1)
        );
        if (!$exists) {
            ecod_api_out(['ok' => false, 'error' => '用户不存在'], 404);
        }

        $bucket = 'flw:' . $myUid;
        $wait = ecod_throttle_guard($bucket);
        if ($wait > 0) {
            ecod_api_out(['ok' => false, 'error' => ecod_throttle_error($wait)], 429);
        }
        ecod_throttle_fail($bucket);

        $result = ecod_follow_toggle($myUid, $target);
        if ($result === false) {
            ecod_api_out(['ok' => false, 'error' => '操作失败，请稍后再试'], 500);
        }
        ecod_throttle_clear($bucket);

        ecod_api_out(['ok' => true, 'uid' => $target] + $result);
    }

    if ($action === 'follow_state') {
        $raw = isset($_GET['uids']) ? (string) $_GET['uids'] : '';
        $uids = [];
        foreach (explode(',', $raw) as $one) {
            $one = (int) trim($one);
            if ($one > 0 && !in_array($one, $uids, true)) {
                $uids[] = $one;
            }
            if (count($uids) >= 100) {
                break;
            }
        }
        if (!$uids) {
            ecod_api_out(['ok' => true, 'following' => [], 'fans' => new stdClass()]);
        }

        try {
            $db = \Typecho\Db::get();
            $payload = ecod_follow_with_table($db, function (string $table) use ($db, $uids, $myUid) {
                $in = implode(',', $uids);
                $fans = array_fill_keys($uids, 0);
                $rows = $db->fetchAll(
                    $db->select('target', 'COUNT(*) AS num')->from($table)
                        ->where('target IN (' . $in . ')')->group('target')
                );
                foreach ($rows as $row) {
                    $fans[(int) $row['target']] = (int) $row['num'];
                }

                $following = [];
                $mine = $db->fetchAll(
                    $db->select('target')->from($table)
                        ->where('uid = ?', $myUid)
                        ->where('target IN (' . $in . ')')
                );
                foreach ($mine as $row) {
                    $following[] = (int) $row['target'];
                }
                return ['following' => $following, 'fans' => $fans];
            });
            ecod_api_out(['ok' => true] + $payload);
        } catch (\Throwable $e) {
            ecod_api_out(['ok' => false, 'error' => 'Server error'], 500);
        }
    }

    /* follow_list */
    $type = (string) ($_GET['type'] ?? 'following') === 'followers' ? 'followers' : 'following';
    ecod_api_out(['ok' => true, 'list' => ecod_follow_rows($myUid, $type, 100)]);
}

/**
 * 点赞者头像堆叠（api 独立上下文版，与 functions.php ecod_post_likers 同口径）：
 * api.php 不含 functions.php，这里紧凑复刻「最近 5 位 uid 点赞者 + 头像」；
 * 头像优先走 ecod_account_avatar_url 的自定义头像，回落本地头像池（glob + crc32，零依赖）。
 *
 * @param \Typecho\Db $db
 * @return array<int, array{name: string, avatar: string}>
 */
function ecod_api_post_likers(\Typecho\Db $db, int $cid, int $limit = 5): array
{
    if ($cid <= 0) {
        return [];
    }
    $limit = max(1, min(10, $limit));
    try {
        $table = ecod_likes_table();
        $rows = $db->fetchAll(
            $db->select('uid', 'MAX(created) AS last')->from($table)
                ->where('cid = ?', $cid)
                ->where('uid > 0')
                ->group('uid')
                ->order('last', \Typecho\Db::SORT_DESC)
                ->limit($limit)
        );
        if (!$rows) {
            return [];
        }
        $uids = [];
        foreach ($rows as $row) {
            $uid = (int) ($row['uid'] ?? 0);
            if ($uid > 0) {
                $uids[$uid] = true;
            }
        }
        if (!$uids) {
            return [];
        }
        $in = implode(',', array_keys($uids));
        $names = [];
        foreach ($db->fetchAll(
            $db->select('uid', 'screenName', 'name')->from('table.users')
                ->where('uid IN (' . $in . ')')
        ) as $u) {
            $names[(int) $u['uid']] = trim((string) ($u['screenName'] ?: $u['name']));
        }
        /* 本地头像池：确定性选图（与 functions.php ecod_local_avatar_url 同算法） */
        $pool = glob(__DIR__ . '/assets/avatar/*.webp');
        if (is_array($pool) && $pool) {
            sort($pool);
        } else {
            $pool = [];
        }
        $out = [];
        foreach (array_keys($uids) as $uid) {
            $name = $names[$uid] ?? '';
            if ($name === '') {
                continue;
            }
            $avatar = ecod_account_avatar_url($uid);
            if ($avatar === '' && $pool) {
                $file = $pool[abs(crc32('uid:' . $uid)) % count($pool)];
                /* 主题内相对段：__DIR__ 距站点根去掉前缀，配 mtime 版本号防缓存 */
                $rel = str_replace(rtrim(__TYPECHO_ROOT_DIR__, '/'), '', __DIR__) . '/assets/avatar/' . basename($file);
                $avatar = $rel . '?v=' . (string) filemtime($file);
            }
            $out[] = ['name' => $name, 'avatar' => $avatar];
        }
        return $out;
    } catch (\Throwable $e) {
        return [];
    }
}

/* ------------------------------------------------------------------
 * 投票（[poll] 短代码渲染的前台交互）
 *
 * poll id 由服务端短代码生成（内容指纹），这里只认格式不认内容；
 * 投票身份与收藏一致：游客 = 浏览器指纹，登录 = 'u'+uid。
 * ---------------------------------------------------------------- */

/** 投票身份：登录优先 */
function ecod_api_poll_fingerprint(): string
{
    return ecod_api_fav_fingerprint();
}

/**
 * 投票者头像堆叠：最近 5 位投票者
 *
 * 登录用户取 uid 对应的昵称 / 头像（口径同 ecod_api_post_likers）；
 * 游客没有身份信息，给一条 guest 占位记录（前端渲染默认占位头像）。
 * 表上有 (poll, fp) 唯一索引，一人（指纹 / uid）只会有一行。
 */
function ecod_api_poll_voters(\Typecho\Db $db, string $poll, int $limit = 5): array
{
    $limit = max(1, min(10, $limit));
    try {
        $rows = $db->fetchAll(
            $db->select('uid', 'fp', 'created')->from('table.ecod_polls_votes')
                ->where('poll = ?', $poll)
                ->order('created', \Typecho\Db::SORT_DESC)
                ->limit($limit * 4)
        );
        if (!$rows) {
            return [];
        }

        /* 按时间倒序挑人：登录按 uid 去重，游客按指纹去重 */
        $picked = [];
        $seenUid = [];
        $seenFp = [];
        foreach ($rows as $row) {
            $uid = (int) ($row['uid'] ?? 0);
            $fp = (string) ($row['fp'] ?? '');
            if ($uid > 0) {
                if (isset($seenUid[$uid])) {
                    continue;
                }
                $seenUid[$uid] = true;
            } else {
                if ($fp === '' || isset($seenFp[$fp])) {
                    continue;
                }
                $seenFp[$fp] = true;
            }
            $picked[] = $uid;
            if (count($picked) >= $limit) {
                break;
            }
        }
        if (!$picked) {
            return [];
        }

        /* 批量取登录用户昵称 */
        $uids = array_values(array_filter($picked, static fn (int $uid): bool => $uid > 0));
        $names = [];
        if ($uids) {
            foreach ($db->fetchAll(
                $db->select('uid', 'screenName', 'name')->from('table.users')
                    ->where('uid IN (' . implode(',', $uids) . ')')
            ) as $u) {
                $names[(int) $u['uid']] = trim((string) ($u['screenName'] ?: $u['name']));
            }
        }

        /* 本地头像池：确定性选图（与 ecod_api_post_likers 同算法） */
        $pool = glob(__DIR__ . '/assets/avatar/*.webp');
        if (is_array($pool) && $pool) {
            sort($pool);
        } else {
            $pool = [];
        }

        $out = [];
        foreach ($picked as $uid) {
            if ($uid <= 0) {
                /* 游客：无昵称、无头像，前端用默认占位头像 */
                $out[] = ['name' => '', 'avatar' => '', 'guest' => true];
                continue;
            }
            $name = $names[$uid] ?? '';
            if ($name === '') {
                $out[] = ['name' => '', 'avatar' => '', 'guest' => true];
                continue;
            }
            $avatar = ecod_account_avatar_url($uid);
            if ($avatar === '' && $pool) {
                $file = $pool[abs(crc32('uid:' . $uid)) % count($pool)];
                /* 主题内相对段：__DIR__ 距站点根去掉前缀，配 mtime 版本号防缓存 */
                $rel = str_replace(rtrim(__TYPECHO_ROOT_DIR__, '/'), '', __DIR__) . '/assets/avatar/' . basename($file);
                $avatar = $rel . '?v=' . (string) filemtime($file);
            }
            $out[] = ['name' => $name, 'avatar' => $avatar];
        }
        return $out;
    } catch (\Throwable $e) {
        return [];
    }
}

/** 读取一次投票的汇总：各选项票数、总人数、我投了谁、最近投票者头像组 */
function ecod_api_poll_tally(\Typecho\Db $db, string $poll, string $fp): array
{
    $rows = $db->fetchAll(
        $db->select('fp', 'opts')->from('table.ecod_polls_votes')->where('poll = ?', $poll)
    );
    $counts = [];
    $total = 0;
    $mine = '';
    foreach ($rows ?: [] as $row) {
        /* 注意：不能 array_filter 直接过，下标 0 是合法选项（会被当假值丢掉），
           这里只滤掉空串与非数字 */
        $opts = [];
        foreach (explode(',', (string) $row['opts']) as $one) {
            $one = trim($one);
            if ($one !== '' && ctype_digit($one)) {
                $opts[] = (int) $one;
            }
        }
        if (!$opts) {
            continue;
        }
        $total++;
        foreach ($opts as $opt) {
            $counts[$opt] = ($counts[$opt] ?? 0) + 1;
        }
        if ((string) $row['fp'] === $fp) {
            $mine = implode(',', $opts);
        }
    }
    ksort($counts);
    return ['total' => $total, 'counts' => $counts, 'mine' => $mine, 'voters' => ecod_api_poll_voters($db, $poll)];
}

if ($action === 'poll_state') {
    $poll = substr(preg_replace('/[^a-z0-9]/', '', (string) ($_GET['poll'] ?? '')), 0, 24);
    if ($poll === '') {
        ecod_api_out(['ok' => false, 'error' => 'Bad request'], 400);
    }
    try {
        $db = \Typecho\Db::get();
        $tally = ecod_api_poll_tally($db, $poll, ecod_api_poll_fingerprint());
        ecod_api_out(['ok' => true, 'poll' => $poll] + $tally);
    } catch (\Throwable $e) {
        ecod_api_out(['ok' => false, 'error' => 'Server error'], 500);
    }
}

if ($action === 'poll_vote') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    if (!ecod_api_same_origin()) {
        ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
    }

    $poll = substr(preg_replace('/[^a-z0-9]/', '', (string) ($_POST['poll'] ?? '')), 0, 24);
    if ($poll === '') {
        ecod_api_out(['ok' => false, 'error' => 'Bad request'], 400);
    }

    /* 选项下标 0–15，最多勾 8 个（multi 由前端限制，这里兜底） */
    $opts = [];
    foreach (explode(',', (string) ($_POST['opts'] ?? '')) as $one) {
        $one = (int) trim($one);
        if ($one >= 0 && $one <= 15 && !in_array($one, $opts, true)) {
            $opts[] = $one;
        }
        if (count($opts) >= 8) {
            break;
        }
    }
    if (!$opts) {
        ecod_api_out(['ok' => false, 'error' => '请先选择一个选项'], 400);
    }
    sort($opts);

    $me = ecod_api_user();
    $uid = $me ? (int) $me->uid : 0;
    $fp = ecod_api_poll_fingerprint();

    $wait = ecod_throttle_guard('poll:' . ecod_api_bucket_fp());
    if ($wait > 0) {
        ecod_api_out(['ok' => false, 'error' => ecod_throttle_error($wait)], 429);
    }
    ecod_throttle_fail('poll:' . ecod_api_bucket_fp());

    try {
        $db = \Typecho\Db::get();
        $row = [
            'poll'    => $poll,
            'fp'      => $fp,
            'uid'     => $uid,
            'opts'    => implode(',', $opts),
            'created' => time(),
        ];
        try {
            $db->query($db->insert('table.ecod_polls_votes')->rows($row));
        } catch (\Throwable $e) {
            ecod_api_out(['ok' => false, 'error' => '你已经投过票了'], 409);
        }

        $tally = ecod_api_poll_tally($db, $poll, $fp);
        ecod_api_out(['ok' => true, 'poll' => $poll] + $tally);
    } catch (\Throwable $e) {
        ecod_api_out(['ok' => false, 'error' => 'Server error'], 500);
    }
}

/* ------------------------------------------------------------------
 * Credit 积分：签到 / 余额 / AI 对话计费
 * ---------------------------------------------------------------- */

if ($action === 'signin') {
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

    $wait = ecod_throttle_guard('signin:' . (int) $me->uid);
    if ($wait > 0) {
        ecod_api_out(['ok' => false, 'error' => ecod_throttle_error($wait)], 429);
    }
    ecod_throttle_fail('signin:' . (int) $me->uid);

    $result = ecod_credits_checkin((int) $me->uid);
    if (empty($result['ok'])) {
        ecod_api_out($result, 400);
    }
    ecod_api_out(['ok' => true] + $result);
}

if ($action === 'credits_state') {
    $me = ecod_api_user();
    if (!$me) {
        ecod_api_out(['ok' => false, 'error' => '请先登录'], 401);
    }
    $ecodCrPrice = ecod_ai_cost((int) $me->uid);
    $ecodAdOn = ecod_ai_ad_enabled();
    ecod_api_out([
        'ok'          => true,
        'name'        => ecod_credits_name(),
        'balance'     => ecod_credits_balance((int) $me->uid),
        'signed'      => ecod_credits_signed_today((int) $me->uid),
        'checkin'     => max(0, (int) ecod_option('ECrCheckin', 0)),
        'log'         => ecod_credits_log((int) $me->uid, 30),
        'stats'       => ecod_credits_stats((int) $me->uid),
        'meter'       => ecod_credits_meter((int) $me->uid),
        'types'       => ecod_credits_log_types((int) $me->uid),
        'ai_cost'     => (int) $ecodCrPrice['cost'],
        'ai_base'     => (int) $ecodCrPrice['base'],
        'ai_free'     => (bool) $ecodCrPrice['free'],
        'ai_vip'      => (bool) $ecodCrPrice['vip'],
        'ai_vip_name' => (string) $ecodCrPrice['vip_name'],
        /* 看广告领点数（F12）：面板据此决定是否显示「赚点数」入口，不在此下发令牌 */
        'ai_ad_on'     => $ecodAdOn,
        'ai_ad_left'   => $ecodAdOn ? max(0, ecod_ai_ad_limit() - ecod_ai_ad_used((int) $me->uid)) : 0,
        'ai_ad_reward' => $ecodAdOn ? ecod_ai_ad_reward() : 0,
    ]);
}

/* 积分明细分页（个人中心 tab + 翻页拉取） */
if ($action === 'credits_log') {
    $me = ecod_api_user();
    if (!$me) {
        ecod_api_out(['ok' => false, 'error' => '请先登录'], 401);
    }
    $ecodCrType = mb_substr(trim((string) ($_GET['type'] ?? '')), 0, 20, 'UTF-8');
    $ecodCrPage = ecod_credits_log_paged(
        (int) $me->uid,
        (int) ($_GET['page'] ?? 1),
        10,
        $ecodCrType
    );
    ecod_api_out([
        'ok'       => true,
        'name'     => ecod_credits_name(),
        'rows'     => $ecodCrPage['rows'],
        'total'    => $ecodCrPage['total'],
        'page'     => $ecodCrPage['page'],
        'pages'    => $ecodCrPage['pages'],
    ]);
}

/* 看广告领点数（F12）：取广告 + 领取令牌；令牌用后即焚，配合最短观看时长防刷 */
if ($action === 'ai_ad' || $action === 'ai_ad_claim') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    if (!ecod_api_same_origin()) {
        ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
    }
    if (!ecod_ai_ad_enabled()) {
        ecod_api_out(['ok' => false, 'error' => '看广告领点数未开启'], 404);
    }
    $ecodAdMe = ecod_api_user();
    if (!$ecodAdMe) {
        ecod_api_out(['ok' => false, 'error' => '请先登录'], 401);
    }
    if (!ecod_account_token_ok((string) ($_POST['token'] ?? ''))) {
        ecod_api_out(['ok' => false, 'error' => '页面已过期，请刷新重试'], 403);
    }

    if ($action === 'ai_ad') {
        ecod_api_out(ecod_ai_ad_state((int) $ecodAdMe->uid));
    }

    $ecodAdRes = ecod_ai_ad_claim((int) $ecodAdMe->uid, (string) ($_POST['ad_token'] ?? ''));
    ecod_api_out($ecodAdRes, empty($ecodAdRes['ok']) ? 400 : 200);
}

/* 评论 AI 舆情摘要（F7）：读者免费可读，生成按评论集合 hash 缓存，不需要登录 */
if ($action === 'ai_comments') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    if (!ecod_api_same_origin()) {
        ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
    }
    $ecodCmtCid = (int) ($_POST['cid'] ?? 0);
    if ($ecodCmtCid <= 0) {
        ecod_api_out(['ok' => false, 'error' => '缺少文章参数'], 400);
    }
    $ecodCmtRes = ecod_ai_comment_summary($ecodCmtCid);
    if (empty($ecodCmtRes['ok'])) {
        ecod_api_out($ecodCmtRes, 200);
    }
    ecod_api_out($ecodCmtRes);
}

/* AI 搜索（F9）：问题 → 站内检索词 → 取 Top N → 带引用的结论；命中缓存免费 */
if ($action === 'ai_search') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    if (!ecod_api_same_origin()) {
        ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
    }
    if (!ecod_ai_enabled() || (string) ecod_option('EAISearch', 'on') !== 'on') {
        ecod_api_out(['ok' => false, 'error' => 'AI 搜索未开启'], 404);
    }

    $me = ecod_api_user();
    if (!$me) {
        ecod_api_out(['ok' => false, 'error' => '请先登录'], 401);
    }
    if (!ecod_account_token_ok((string) ($_POST['token'] ?? ''))) {
        ecod_api_out(['ok' => false, 'error' => '页面已过期，请刷新重试'], 403);
    }

    $ecodSrchUid = (int) $me->uid;
    $ecodSrchQ = trim((string) ($_POST['q'] ?? ''));
    if ($ecodSrchQ === '' || mb_strlen($ecodSrchQ, 'UTF-8') < 2 || mb_strlen($ecodSrchQ, 'UTF-8') > 120) {
        ecod_api_out(['ok' => false, 'error' => '请输入 2–120 字的问题'], 400);
    }

    /* 命中缓存：不收钱、不再调模型（同一句话反复问不该反复计费）
     *
     * 用文件缓存（core/lib/cache.php）而不是 options 行：options.name 只有
     * varchar(32)，而 'theme:ecod_ai_s_' + md5 是 49 字符 —— 严格模式下
     * INSERT 直接被拒，异常又被 try/catch 吞掉，表现为「缓存永远不命中、
     * 同一句话每次都扣 2 点」（2026-11-01 端到端自测抓到的静默失败）。
     * 另外 options 的 user=0 行每个请求都会整体载入，搜索缓存条数不可控，
     * 放文件缓存才不拖慢全站。 */
    $ecodSrchKey = 'ai_search_' . md5(mb_strtolower($ecodSrchQ, 'UTF-8'));
    [$ecodSrchHit, $ecodSrchCache] = ecod_file_cache_read($ecodSrchKey);
    if ($ecodSrchHit && is_array($ecodSrchCache)
        && (string) ($ecodSrchCache['q'] ?? '') === $ecodSrchQ) {
        ecod_api_out([
            'ok'       => true,
            'answer'   => (string) ($ecodSrchCache['answer'] ?? ''),
            'items'    => $ecodSrchCache['items'] ?? [],
            'keywords' => $ecodSrchCache['keywords'] ?? [],
            'balance'  => ecod_credits_balance($ecodSrchUid),
            'cost'     => 0,
            'cached'   => true,
        ]);
    }

    /* 预检按最贵档（两次模型调用）；实际按 calls 结算，找不到就少收一半 */
    $ecodSrchMax = ecod_ai_cost($ecodSrchUid, 2);
    if ((int) $ecodSrchMax['cost'] > 0 && ecod_credits_balance($ecodSrchUid) < (int) $ecodSrchMax['cost']) {
        ecod_api_out(['ok' => false, 'error' => ecod_credits_name() . ' 不足，先去签到赚积分吧'], 402);
    }

    $ecodSrchRes = ecod_ai_search($ecodSrchQ, $ecodSrchUid);
    if (empty($ecodSrchRes['ok'])) {
        ecod_api_out(['ok' => false, 'error' => (string) ($ecodSrchRes['error'] ?? '生成失败')], 502);
    }

    $ecodSrchPrice = ecod_ai_cost($ecodSrchUid, max(1, (int) ($ecodSrchRes['calls'] ?? 2)));
    $ecodSrchCost = (int) $ecodSrchPrice['cost'];
    $ecodSrchBal = ecod_credits_balance($ecodSrchUid);
    if ($ecodSrchCost > 0) {
        $ecodSrchPaid = ecod_credits_add($ecodSrchUid, -$ecodSrchCost, 'ai', 'search', 'AI 搜索');
        if (!empty($ecodSrchPaid['ok'])) {
            $ecodSrchBal = (int) $ecodSrchPaid['balance'];
        }
    }
    ecod_ai_quota_hit($ecodSrchUid);

    /* 只缓存有引用的答案：空结果缓存 10 分钟，避免同一句反复烧额度 */
    $ecodSrchTtl = !empty($ecodSrchRes['items']) ? 86400 : 600;
    ecod_file_cache_write($ecodSrchKey, [
        'q'        => $ecodSrchQ,
        'answer'   => (string) $ecodSrchRes['answer'],
        'items'    => $ecodSrchRes['items'],
        'keywords' => $ecodSrchRes['keywords'],
        'at'       => time(),
    ], $ecodSrchTtl);

    ecod_api_out([
        'ok'       => true,
        'answer'   => (string) $ecodSrchRes['answer'],
        'items'    => $ecodSrchRes['items'],
        'keywords' => $ecodSrchRes['keywords'],
        'balance'  => $ecodSrchBal,
        'cost'     => $ecodSrchCost,
        'cached'   => false,
    ]);
}

/* AI 对话：登录 + 令牌 + 限速，成功后按次扣积分 */
if ($action === 'ai_chat') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    if (!ecod_api_same_origin()) {
        ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
    }
    if (!ecod_ai_enabled() || (string) ecod_option('EAIChat', 'on') !== 'on') {
        ecod_api_out(['ok' => false, 'error' => 'AI 助手未开启'], 404);
    }

    $me = ecod_api_user();
    if (!$me) {
        ecod_api_out(['ok' => false, 'error' => '请先登录'], 401);
    }
    if (!ecod_account_token_ok((string) ($_POST['token'] ?? ''))) {
        ecod_api_out(['ok' => false, 'error' => '页面已过期，请刷新重试'], 403);
    }

    $ecodChatUid = (int) $me->uid;
    $ecodChatBucket = 'aichat:' . $ecodChatUid;
    $ecodChatWait = ecod_throttle_guard($ecodChatBucket);
    if ($ecodChatWait > 0) {
        ecod_api_out(['ok' => false, 'error' => ecod_throttle_error($ecodChatWait)], 429);
    }
    ecod_throttle_fail($ecodChatBucket);

    $ecodChatMsg = trim((string) ($_POST['message'] ?? ''));
    if ($ecodChatMsg === '' || mb_strlen($ecodChatMsg, 'UTF-8') > 2000) {
        ecod_api_out(['ok' => false, 'error' => '请输入 1–2000 字的问题'], 400);
    }

    /* 计价：基础价 × 倍数，会员是否免单由 ecod_ai_cost() 统一决定（F8） */
    $ecodChatPrice = ecod_ai_cost($ecodChatUid);
    $ecodChatCost = (int) $ecodChatPrice['cost'];
    if ($ecodChatCost > 0 && ecod_credits_balance($ecodChatUid) < $ecodChatCost) {
        ecod_api_out(['ok' => false, 'error' => ecod_credits_name() . ' 不足，先去签到赚积分吧'], 402);
    }

    /* 每日次数上限（0 = 不限）：会员免单没有流水可数，统一走 ecod_ai_quota_* */
    $ecodChatDaily = max(0, (int) ecod_option('EAIChatDaily', 0));
    if ($ecodChatDaily > 0 && ecod_ai_quota_used($ecodChatUid) >= $ecodChatDaily) {
        ecod_api_out(['ok' => false, 'error' => '今天的 AI 额度已用完，明天再来'], 402);
    }

    /* 上下文：前端带回最近几轮对话（每轮 user + assistant 各一条） */
    $ecodChatCtx = max(0, min(10, (int) ecod_option('EAIChatCtx', 4)));
    $ecodChatSystem = trim((string) ecod_option('EAIChatPrompt', ''));
    if ($ecodChatSystem === '') {
        $ecodChatSystem = '你是「' . (string) \Widget\Options::alloc()->title . '」的 AI 助手。'
            . '用简体中文回答，口语化、简洁、直接给结论，不要编造站内不存在的信息。';
    }

    $ecodChatMessages = [['role' => 'system', 'content' => $ecodChatSystem]];

    /* 文章作用域：正文只从 ecod_ai_text_allowed() 取，付费未授权只有试读段。
       前端不传正文（防篡改、防绕过），这里也不接受自定义 prompt 注入。 */
    if ((string) ($_POST['scope'] ?? '') === 'post') {
        $ecodChatCid = (int) ($_POST['cid'] ?? 0);
        $ecodChatCtxText = $ecodChatCid > 0 ? ecod_ai_text_allowed($ecodChatCid, $ecodChatUid) : '';
        if ($ecodChatCtxText === '') {
            ecod_api_out(['ok' => false, 'error' => '这篇内容暂时无法追问'], 403);
        }
        $ecodChatTitle = ecod_ai_post_title($ecodChatCid);
        $ecodChatMessages[0]['content'] .= "\n\n下面是与问题相关的文章"
            . ($ecodChatTitle !== '' ? "《{$ecodChatTitle}》" : '')
            . "内容。回答只依据这段内容，不要编造文章里没有的信息；"
            . "若资料不足以回答，直接说明。\n\n---\n" . $ecodChatCtxText . "\n---";
    }
    /* 站点作用域：404「我该去哪？」与面板里的「这个站是干嘛的」。
       只注入公开概览（站点名/简介/分类/最近文章），不碰任何正文与用户数据。 */
    if ((string) ($_POST['scope'] ?? '') === 'site') {
        $ecodChatBrief = ecod_ai_site_brief_text(8);
        if ($ecodChatBrief !== '') {
            $ecodChatMessages[0]['content'] .= "\n\n下面是本站概览（公开信息）。回答「这个站是干嘛的 / "
                . "最近有什么新文章 / 我该去哪」这类问题时以此为准，不要编造站内不存在的页面。"
                . "推荐去处时列 2~3 个具体条目，每条给标题与完整链接。\n\n---\n"
                . $ecodChatBrief . "\n---";
        }
    }
    $ecodChatHistoryRaw = (string) ($_POST['history'] ?? '');
    if ($ecodChatHistoryRaw !== '' && $ecodChatCtx > 0) {
        $ecodChatHistory = json_decode($ecodChatHistoryRaw, true);
        if (is_array($ecodChatHistory)) {
            $ecodChatHistory = array_slice($ecodChatHistory, -$ecodChatCtx * 2);
            foreach ($ecodChatHistory as $ecodChatTurn) {
                if (!is_array($ecodChatTurn)) {
                    continue;
                }
                $ecodChatRole = (string) ($ecodChatTurn['role'] ?? '');
                $ecodChatText = mb_substr(trim((string) ($ecodChatTurn['content'] ?? '')), 0, 2000, 'UTF-8');
                if ($ecodChatText !== '' && in_array($ecodChatRole, ['user', 'assistant'], true)) {
                    $ecodChatMessages[] = ['role' => $ecodChatRole, 'content' => $ecodChatText];
                }
            }
        }
    }
    $ecodChatMessages[] = ['role' => 'user', 'content' => $ecodChatMsg];

    /* 流式：前端带 stream=1 且后台 EAIStream 开着时走 SSE（Eventsource 由 fetch 读） */
    $ecodChatStream = (string) ($_POST['stream'] ?? '') === '1'
        && (string) ecod_option('EAIStream', 'on') === 'on';

    if ($ecodChatStream) {
        /* 预扣：先扣后退（上游零输出才退），避免「首字到就 abort」白嫖 */
        $ecodChatBal = ecod_credits_balance($ecodChatUid);
        if ($ecodChatCost > 0) {
            $ecodChatPaid = ecod_credits_add($ecodChatUid, -$ecodChatCost, 'ai', 'chat', 'AI 对话');
            if (!empty($ecodChatPaid['ok'])) {
                $ecodChatBal = (int) $ecodChatPaid['balance'];
            }
        }

        /* 清缓冲 + 换事件流头（api.php 顶部设的是 application/json） */
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        @ini_set('zlib.output_compression', '0');
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('X-Accel-Buffering: no');
        @set_time_limit(60);

        /* 关键：释放 session 文件锁，否则同一用户其它标签页会被串行化到超时 */
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        @ob_implicit_flush(true);

        $ecodSseSend = static function (array $event): void {
            echo 'data: ' . json_encode($event, JSON_UNESCAPED_UNICODE) . "\n\n";
            @flush();
        };
        $ecodSseSend([
            't'       => 'meta',
            'balance' => $ecodChatBal,
            'cost'    => $ecodChatCost,
            'free'    => (bool) $ecodChatPrice['free'],
            'vip'     => (bool) $ecodChatPrice['vip'],
        ]);

        $ecodChatStreamRes = ecod_ai_chat_stream(
            $ecodChatMessages,
            800,
            static function (string $delta) use ($ecodSseSend): void {
                $ecodSseSend(['t' => 'delta', 's' => $delta]);
            }
        );

        if (empty($ecodChatStreamRes['ok'])) {
            /* 一个字都没出：全额退回（中断不退，由用户自己承担已产生的成本） */
            $ecodChatBal = ecod_credits_balance($ecodChatUid);
            if ($ecodChatCost > 0) {
                $ecodChatRefund = ecod_credits_add($ecodChatUid, $ecodChatCost, 'ai_refund', 'chat', 'AI 对话失败退回');
                if (!empty($ecodChatRefund['ok'])) {
                    $ecodChatBal = (int) $ecodChatRefund['balance'];
                }
            }
            $ecodSseSend([
                't'       => 'err',
                'error'   => (string) ($ecodChatStreamRes['error'] ?? '生成失败，请稍后重试'),
                'refund'  => true,
                'balance' => $ecodChatBal,
            ]);
            exit;
        }

        /* 成功才计入日额度（失败已退回，不该占用户次数） */
        ecod_ai_quota_hit($ecodChatUid);

        $ecodSseSend([
            't'       => 'done',
            'reply'   => (string) $ecodChatStreamRes['reply'],
            'balance' => $ecodChatBal,
        ]);
        exit;
    }

    $ecodChatRes = ecod_ai_chat_call($ecodChatMessages);
    if (empty($ecodChatRes['ok'])) {
        ecod_throttle_fail($ecodChatBucket);
        ecod_api_out(['ok' => false, 'error' => (string) ($ecodChatRes['error'] ?? '生成失败')], 502);
    }

    /* 成功才扣费；扣费失败不影响本次回答（下一次余额校验会拦住） */
    $ecodChatBal = ecod_credits_balance($ecodChatUid);
    if ($ecodChatCost > 0) {
        $ecodChatPaid = ecod_credits_add($ecodChatUid, -$ecodChatCost, 'ai', 'chat', 'AI 对话');
        if (!empty($ecodChatPaid['ok'])) {
            $ecodChatBal = (int) $ecodChatPaid['balance'];
        }
    }
    ecod_ai_quota_hit($ecodChatUid);

    ecod_api_out([
        'ok'      => true,
        'reply'   => (string) $ecodChatRes['reply'],
        'balance' => $ecodChatBal,
        'cost'    => $ecodChatCost,
        'free'    => (bool) $ecodChatPrice['free'],
        'vip'     => (bool) $ecodChatPrice['vip'],
    ]);
}

/* ------------------------------------------------------------------
 * 站点抽奖
 * ---------------------------------------------------------------- */

if ($action === 'lottery_state') {
    if (!ecod_lottery_enabled()) {
        ecod_api_out(['ok' => false, 'error' => '抽奖未开启'], 404);
    }
    $ecodLotMe = ecod_api_user();
    $ecodLotOverview = ecod_lottery_overview($ecodLotMe ? (int) $ecodLotMe->uid : 0);
    ecod_api_out(['ok' => true] + $ecodLotOverview);
}

/* 直接兑换头奖：付固定 Credit 跳过概率（guaqi 式），换到什么返回什么 */
if ($action === 'lottery_exchange') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    if (!ecod_api_same_origin()) {
        ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
    }
    $ecodLotMe = ecod_api_user();
    if (!$ecodLotMe) {
        ecod_api_out(['ok' => false, 'error' => '请先登录'], 401);
    }
    if (!ecod_account_token_ok((string) ($_POST['token'] ?? ''))) {
        ecod_api_out(['ok' => false, 'error' => '页面已过期，请刷新重试'], 403);
    }

    $ecodLotUid = (int) $ecodLotMe->uid;
    /* 抽奖是娱乐操作，阈值比登录类宽：连抽 8 次起 15 秒冷却，16 次起 60 秒 */
    $ecodLotWait = ecod_throttle_guard('lottery:' . $ecodLotUid, 8, 15, 60);
    if ($ecodLotWait > 0) {
        ecod_api_out(['ok' => false, 'error' => ecod_throttle_error($ecodLotWait)], 429);
    }
    ecod_throttle_fail('lottery:' . $ecodLotUid);

    $ecodLotRes = ecod_lottery_exchange($ecodLotUid);
    ecod_api_out($ecodLotRes, empty($ecodLotRes['ok']) ? 400 : 200);
}

/* ------------------------------------------------------------------
 * 积分兑换商城
 * ---------------------------------------------------------------- */

if ($action === 'shop_state') {
    if (!ecod_shop_enabled()) {
        ecod_api_out(['ok' => false, 'error' => '兑换商城未开启'], 404);
    }
    $ecodShopMe = ecod_api_user();
    ecod_api_out(['ok' => true] + ecod_shop_overview($ecodShopMe ? (int) $ecodShopMe->uid : 0));
}

if ($action === 'shop_exchange') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    if (!ecod_api_same_origin()) {
        ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
    }
    $ecodShopMe = ecod_api_user();
    if (!$ecodShopMe) {
        ecod_api_out(['ok' => false, 'error' => '请先登录'], 401);
    }
    if (!ecod_account_token_ok((string) ($_POST['token'] ?? ''))) {
        ecod_api_out(['ok' => false, 'error' => '页面已过期，请刷新重试'], 403);
    }

    $ecodShopUid = (int) $ecodShopMe->uid;
    $ecodShopWait = ecod_throttle_guard('shop:' . $ecodShopUid);
    if ($ecodShopWait > 0) {
        ecod_api_out(['ok' => false, 'error' => ecod_throttle_error($ecodShopWait)], 429);
    }
    ecod_throttle_fail('shop:' . $ecodShopUid);

    $ecodShopRes = ecod_shop_exchange($ecodShopUid, substr(preg_replace('/[^a-z0-9]/', '', (string) ($_POST['item'] ?? '')), 0, 24));
    ecod_api_out($ecodShopRes, empty($ecodShopRes['ok']) ? 400 : 200);
}

if ($action === 'lottery_draw') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    if (!ecod_api_same_origin()) {
        ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
    }
    $ecodLotMe = ecod_api_user();
    if (!$ecodLotMe) {
        ecod_api_out(['ok' => false, 'error' => '请先登录'], 401);
    }
    if (!ecod_account_token_ok((string) ($_POST['token'] ?? ''))) {
        ecod_api_out(['ok' => false, 'error' => '页面已过期，请刷新重试'], 403);
    }

    $ecodLotUid = (int) $ecodLotMe->uid;
    $ecodLotWait = ecod_throttle_guard('lottery:' . $ecodLotUid);
    if ($ecodLotWait > 0) {
        ecod_api_out(['ok' => false, 'error' => ecod_throttle_error($ecodLotWait)], 429);
    }
    ecod_throttle_fail('lottery:' . $ecodLotUid);

    $ecodLotRes = ecod_lottery_draw($ecodLotUid);
    ecod_api_out($ecodLotRes, empty($ecodLotRes['ok']) ? 400 : 200);
}

/* ------------------------------------------------------------------
 * 自助广告：提交投放申请 → 建订单 → 收银台支付
 * ---------------------------------------------------------------- */

if ($action === 'ad_apply') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    if (!ecod_api_same_origin()) {
        ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
    }
    $ecodAdMe = ecod_api_user();
    if (!$ecodAdMe) {
        ecod_api_out(['ok' => false, 'error' => '请先登录'], 401);
    }
    if (!ecod_account_token_ok((string) ($_POST['token'] ?? ''))) {
        ecod_api_out(['ok' => false, 'error' => '页面已过期，请刷新重试'], 403);
    }

    $ecodAdWait = ecod_throttle_guard('ad:' . (int) $ecodAdMe->uid);
    if ($ecodAdWait > 0) {
        ecod_api_out(['ok' => false, 'error' => ecod_throttle_error($ecodAdWait)], 429);
    }
    ecod_throttle_fail('ad:' . (int) $ecodAdMe->uid);

    $ecodAdRes = ecod_ads_create((int) $ecodAdMe->uid, [
        'slot'    => (string) ($_POST['slot'] ?? ''),
        'title'   => (string) ($_POST['title'] ?? ''),
        'image'   => (string) ($_POST['image'] ?? ''),
        'link'    => (string) ($_POST['link'] ?? ''),
        'units'   => (int) ($_POST['units'] ?? ($_POST['days'] ?? 0)),
        'channel' => (string) ($_POST['channel'] ?? 'manual'),
    ]);
    if (empty($ecodAdRes['ok'])) {
        ecod_api_out(['ok' => false, 'error' => (string) ($ecodAdRes['error'] ?? '提交失败')], 400);
    }
    ecod_api_out([
        'ok'       => true,
        'ad_id'    => (int) $ecodAdRes['ad_id'],
        'order_no' => (string) $ecodAdRes['order_no'],
        'amount'   => (int) $ecodAdRes['amount'],
    ]);
}

/* ============================================================
 * 主题授权（license_*）
 * ============================================================ */
if ($action === 'license_state') {
    if (!ecod_license_is_admin()) {
        ecod_api_out(['ok' => false, 'error' => '无权限'], 403);
    }
    $state = ecod_license_state();
    /* 后台面板「环境明细」用的只读字段 */
    $state['host'] = ecod_license_host();
    $state['endpoint'] = ecod_license_endpoint();
    $state['crypto'] = ecod_license_crypto_ok();
    ecod_api_out(['ok' => true, 'state' => $state]);
}

if ($action === 'license_activate') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    if (!ecod_api_same_origin()) {
        ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
    }
    if (!ecod_license_is_admin()) {
        ecod_api_out(['ok' => false, 'error' => '无权限'], 403);
    }
    if (!hash_equals(ecod_tool_token(), (string) ($_POST['token'] ?? ''))) {
        ecod_api_out(['ok' => false, 'error' => '页面已过期，请刷新重试'], 403);
    }
    $res = ecod_license_activate((string) ($_POST['key'] ?? ''), (string) ($_POST['domain'] ?? ''));
    ecod_api_out($res);
}

if ($action === 'license_import') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    if (!ecod_api_same_origin()) {
        ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
    }
    if (!ecod_license_is_admin()) {
        ecod_api_out(['ok' => false, 'error' => '无权限'], 403);
    }
    if (!hash_equals(ecod_tool_token(), (string) ($_POST['token'] ?? ''))) {
        ecod_api_out(['ok' => false, 'error' => '页面已过期，请刷新重试'], 403);
    }
    $res = ecod_license_import((string) ($_POST['file'] ?? ''));
    ecod_api_out($res);
}

if ($action === 'license_recheck') {
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_api_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    if (!ecod_api_same_origin()) {
        ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
    }
    if (!ecod_license_is_admin()) {
        ecod_api_out(['ok' => false, 'error' => '无权限'], 403);
    }
    if (!hash_equals(ecod_tool_token(), (string) ($_POST['token'] ?? ''))) {
        ecod_api_out(['ok' => false, 'error' => '页面已过期，请刷新重试'], 403);
    }
    ecod_license_recheck(true);
    $state = ecod_license_state();
    $ok = empty($state['last_error']);
    ecod_api_out([
        'ok'      => true,
        'message' => $ok ? '复验完成' : '复验失败：' . (string) $state['last_error'],
        'state'   => $state,
    ]);
}

if ($action === 'license_ping') {
    ecod_license_ping();
}

if ($action === 'license_buy') {
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
    if (!ecod_pay_enabled()) {
        ecod_api_out(['ok' => false, 'error' => '本站暂未开启在线支付'], 400);
    }
    if (!ecod_license_ss_ready()) {
        ecod_api_out(['ok' => false, 'error' => '作者端未开启自助发码'], 400);
    }

    $uid = (int) $me->uid;
    $domain = trim((string) ($_POST['domain'] ?? ''));
    $chk = ecod_license_ss_check_domain($domain, $uid);
    if (empty($chk['ok'])) {
        ecod_api_out(['ok' => false, 'error' => (string) ($chk['message'] ?? '域名不可用')], 400);
    }
    $domain = (string) $chk['domain'];

    $level = max(1, min(2, (int) ($_POST['level'] ?? 1)));
    $priceStr = $level === 2
        ? ecod_license_price('team')
        : ecod_license_price('pro');
    if ($priceStr === '') {
        ecod_api_out(['ok' => false, 'error' => '该版本暂未开放购买'], 400);
    }
    $amount = ecod_pay_cents($priceStr);
    if ($amount === null || $amount <= 0) {
        ecod_api_out(['ok' => false, 'error' => '价格配置无效'], 400);
    }

    $channelId = (string) ($_POST['channel'] ?? '');
    if ($channelId === '') {
        $channelId = ecod_pay_default_channel();
    }

    $editionName = $level === 2 ? '团队版' : '专业版';
    $created = ecod_pay_order_create([
        'uid'     => $uid,
        'kind'    => 'theme',
        'target'  => 0,
        'title'   => '主题授权 · ' . $editionName,
        'amount'  => $amount,
        'days'    => 0,
        'level'   => $level,
        'channel' => $channelId,
        'remark'  => 'domain:' . $domain,
    ]);
    if (empty($created['ok'])) {
        ecod_api_out(['ok' => false, 'error' => $created['error'] ?? '创建订单失败'], 500);
    }

    $order = ecod_pay_order_get((string) $created['order_no']);
    if (!$order) {
        ecod_api_out(['ok' => false, 'error' => '订单读取失败'], 500);
    }

    /* 与文章 / 会员购买同一约定：只建单，支付统一交收银台选通道
       （配了易支付就是在在线支付，支付回调 → settle → 自动签发授权码） */
    $cashier = function_exists('ecod_pay_cashier_url')
        ? ecod_pay_cashier_url(['order_no' => (string) $created['order_no']]) : '';
    if ($cashier === '') {
        ecod_api_out(['ok' => false, 'error' => '收银台页面不存在，请先在「特色页」创建收银台'], 500);
    }
    ecod_api_out([
        'ok'       => true,
        'order_no' => (string) $created['order_no'],
        'mode'     => 'cashier',
        'cashier'  => $cashier,
        'amount'   => (int) $order['amount'],
    ]);
}

if ($action === 'license_issue') {
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
    $uid = (int) $me->uid;
    $orderNo = trim((string) ($_POST['order_no'] ?? ''));
    if ($orderNo === '') {
        ecod_api_out(['ok' => false, 'error' => '缺少订单号'], 400);
    }
    $order = ecod_pay_order_get($orderNo);
    if (!$order || (int) $order['uid'] !== $uid || (string) $order['kind'] !== 'theme') {
        ecod_api_out(['ok' => false, 'error' => '订单不存在'], 404);
    }
    /* 未支付时尝试结算（前端轮询会重复调用，幂等） */
    if ((string) $order['status'] === 'pending') {
        ecod_pay_settle($orderNo);
        $order = ecod_pay_order_get($orderNo);
    }
    if ((string) $order['status'] !== 'paid') {
        ecod_api_out(['ok' => false, 'error' => '订单未支付', 'status' => (string) $order['status']]);
    }
    $res = ecod_license_ss_issue([
        'uid'      => $uid,
        'domain'   => ecod_license_order_domain($order),
        'edition'  => ((int) $order['level'] >= 2) ? 'team' : 'pro',
        'order_no' => $orderNo,
        'product'  => 'theme',
        'rebind_limit' => ecod_license_rebind_default(),
    ]);
    ecod_api_out($res);
}

if ($action === 'license_rebind') {
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
    $uid = (int) $me->uid;
    $keyId = (int) ($_POST['key_id'] ?? 0);
    $domain = trim((string) ($_POST['domain'] ?? ''));
    $res = ecod_license_ss_rebind($keyId, $uid, $domain);
    ecod_api_out($res);
}

/* 授权：我的授权列表 */
if ($action === 'license_keys') {
    $me = ecod_api_user();
    if (!$me) {
        ecod_api_out(['ok' => false, 'error' => '请先登录'], 401);
    }
    $uid = (int) $me->uid;
    $keys = function_exists('ecod_license_ss_keys') ? ecod_license_ss_keys($uid) : [];
    ecod_api_out(['ok' => true, 'keys' => $keys]);
}

/* 授权：下载单条授权文件 */
if ($action === 'license_key_file') {
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
    $uid = (int) $me->uid;
    $keyId = (int) ($_POST['key_id'] ?? 0);
    $file = function_exists('ecod_license_ss_key_file') ? ecod_license_ss_key_file($keyId, $uid) : '';
    if ($file === '') {
        ecod_api_out(['ok' => false, 'error' => '无法获取授权文件']);
    }
    ecod_api_out(['ok' => true, 'file' => $file]);
}

if ($action === 'license_check') {
    if (!ecod_api_same_origin()) {
        ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
    }
    $me = ecod_api_user();
    if (!$me) {
        ecod_api_out(['ok' => false, 'error' => '请先登录'], 401);
    }
    $uid = (int) $me->uid;
    $domain = trim((string) ($_GET['domain'] ?? $_POST['domain'] ?? ''));
    $chk = ecod_license_ss_check_domain($domain, $uid);
    ecod_api_out($chk);
}

/* 授权：原地升级（专业版 → 团队版，补差价，同码重签；结算后由
   core/license/selfserve.php 的 upgrade 分支处理，域名与换绑计数不变） */
if ($action === 'license_upgrade') {
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
    if (!ecod_pay_enabled()) {
        ecod_api_out(['ok' => false, 'error' => '本站暂未开启在线支付'], 400);
    }
    if (!ecod_license_ss_ready()) {
        ecod_api_out(['ok' => false, 'error' => '作者端未开启自助发码'], 400);
    }

    $uid = (int) $me->uid;
    $keyId = (int) ($_POST['key_id'] ?? 0);
    $owned = '';
    foreach (ecod_license_ss_keys($uid) as $k) {
        if ((int) $k['id'] === $keyId) {
            $owned = $k;
            break;
        }
    }
    if (!$owned) {
        ecod_api_out(['ok' => false, 'error' => '授权不存在'], 404);
    }
    if ((string) $owned['edition'] !== 'pro' || (string) $owned['status'] !== 'active') {
        ecod_api_out(['ok' => false, 'error' => '该授权不满足升级条件'], 400);
    }

    $proStr = ecod_license_price('pro');
    $teamStr = ecod_license_price('team');
    $pro = $proStr !== '' ? ecod_pay_cents($proStr) : null;
    $team = $teamStr !== '' ? ecod_pay_cents($teamStr) : null;
    if ($pro === null || $team === null || $team <= $pro) {
        ecod_api_out(['ok' => false, 'error' => '升级档未开放，请联系作者'], 400);
    }
    $diff = $team - $pro;

    $channelId = (string) ($_POST['channel'] ?? '');
    if ($channelId === '') {
        $channelId = ecod_pay_default_channel();
    }
    $created = ecod_pay_order_create([
        'uid'     => $uid,
        'kind'    => 'theme',
        'target'  => 0,
        'title'   => '主题授权 · 升级团队版（' . (string) $owned['code'] . '）',
        'amount'  => $diff,
        'days'    => 0,
        'level'   => 2,
        'channel' => $channelId,
        'remark'  => 'upgrade:' . $keyId,
    ]);
    if (empty($created['ok'])) {
        ecod_api_out(['ok' => false, 'error' => $created['error'] ?? '创建订单失败'], 500);
    }

    $order = ecod_pay_order_get((string) $created['order_no']);
    if (!$order) {
        ecod_api_out(['ok' => false, 'error' => '订单读取失败'], 500);
    }
    /* 同 license_buy：只建单，支付交收银台选通道 */
    $cashier = function_exists('ecod_pay_cashier_url')
        ? ecod_pay_cashier_url(['order_no' => (string) $created['order_no']]) : '';
    if ($cashier === '') {
        ecod_api_out(['ok' => false, 'error' => '收银台页面不存在，请先在「特色页」创建收银台'], 500);
    }
    ecod_api_out([
        'ok'       => true,
        'order_no' => (string) $created['order_no'],
        'mode'     => 'cashier',
        'cashier'  => $cashier,
        'amount'   => (int) $order['amount'],
    ]);
}

if ($action !== 'like') {
    ecod_api_out(['ok' => false, 'error' => 'Unknown action'], 400);
}

if ($cid <= 0) {
    ecod_api_out(['ok' => false, 'error' => 'Invalid cid'], 400);
}

/* 点赞写操作：同源 + 每 IP 每分钟 30 次（插入会触发通知与积分，不能裸奔） */
if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!ecod_api_same_origin()) {
        ecod_api_out(['ok' => false, 'error' => '非法请求来源'], 403);
    }
    $ecodLikeWait = ecod_api_rate_wait('like', 30, 60);
    if ($ecodLikeWait > 0) {
        ecod_api_out(['ok' => false, 'error' => '操作太频繁了，请 ' . $ecodLikeWait . ' 秒后再试'], 429);
    }
    ecod_api_rate_hit('like', 60);
}

try {
    $db = \Typecho\Db::get();

    /* 只允许给已发布的文章点赞 */
    $post = $db->fetchRow(
        $db->select('cid', 'authorId')->from('table.contents')
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

    $result = ecod_api_with_table($db, function (string $table) use ($db, $cid, $fp, $uid, $post) {
        $liked = false;

        if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            /* toggle：已赞（ip 指纹行或 uid 行任一命中）→ 撤赞删行；未赞 → 插行。
               登录用户可能同时存在旧指纹行与 uid 行（跨设备），撤赞一并删除避免复点复活 */
            $uidHit = $uid > 0 ? (bool) $db->fetchRow(
                $db->select('cid')->from($table)
                    ->where('cid = ?', $cid)->where('uid = ?', $uid)->limit(1)
            ) : false;
            $fpHit = (bool) $db->fetchRow(
                $db->select('cid')->from($table)
                    ->where('cid = ?', $cid)->where('ip = ?', $fp)->limit(1)
            );

            if ($uidHit || $fpHit) {
                /* 撤赞：指纹行与 uid 行分两条删（Query 无 or()，orWhere 语义不适合 delete） */
                $db->query($db->delete($table)->where('cid = ?', $cid)->where('ip = ?', $fp));
                if ($uid > 0) {
                    $db->query($db->delete($table)->where('cid = ?', $cid)->where('uid = ?', $uid));
                }
            } else {
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
                    /* 站内通知：文章被点赞 → 提醒作者（作者自己赞不通知） */
                    $ecodLikeMe = ecod_api_user();
                    ecod_notif_like($cid, $uid, $ecodLikeMe ? (string) $ecodLikeMe->screenName : null);
                    /* 积分任务：文章被赞给作者加分（作者自赞、积分未开、当日达上限都静默跳过） */
                    $ecodLikeAuthor = (int) ($post['authorId'] ?? 0);
                    if ($ecodLikeAuthor > 0 && $ecodLikeAuthor !== $uid) {
                        ecod_credits_award($ecodLikeAuthor, 'like_recv', 'post:' . $cid, '文章 #' . $cid . ' 被赞');
                    }
                } catch (\Throwable $e) {
                    /* 唯一索引冲突 = 并发双击已插行，按已赞幂等处理 */
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

    $ecodLikeResult = [
        'ok' => true,
        'cid' => $cid,
    ] + $result;
    /* v9 头像堆叠：点赞返回最新 likers（与 functions.php ecod_post_likers 同口径），
       前端据此即时刷新「@xx 觉得不错」行，不再要用户手动刷新页面 */
    $ecodLikeResult['likers'] = ecod_api_post_likers($db, $cid, 5);
    ecod_api_out($ecodLikeResult);
} catch (\Throwable $e) {
    ecod_api_out(['ok' => false, 'error' => 'Server error'], 500);
}
