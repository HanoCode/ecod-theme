<?php
/**
 * Ecod Theme · 媒体库接口
 *
 * 给后台的「选图器」提供数据，直接以独立入口运行，不经过 Typecho 路由：
 *   GET media.php?action=list&page=1&size=48&q=关键词
 *   GET media.php?action=ping
 *
 * 只返回已登录管理员（编辑及以上）可见的附件，未登录一律 401。
 * 上传仍复用 Typecho 自带的 /action/upload，这里只负责「读」。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 * @license   MIT
 */

/* 独立 JSON 入口：不让任何 warning/notice 混进响应体 */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');

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
require_once __DIR__ . '/core/site/seo.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');

/**
 * 输出并结束（清掉可能已开启的输出缓冲，保证响应体是纯 JSON）
 */
function ecod_media_out(array $data, int $status = 200): void
{
    /* 状态必须先写进 Response 单例再关缓冲：Typecho 的 ob 回调在缓冲
     * 收尾时用单例状态行覆盖一切（单例还停在 200 就被写成 200），
     * 关闭缓冲之后再补 http_response_code 已经来不及 */
    ecod_http_status($status);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ------------------------------------------------------------------
 * 登录校验：后台任何能发文章的角色都可以用媒体库
 *
 * 注意：不能走 \Widget\Init::alloc()。Init 会用「当前脚本目录」计算
 * cookie 前缀（md5(rootUrl)），本文件在 /usr/themes/<主题>/ 下，前缀与
 * 后台登录 cookie 对不上，hasLogin 永远 false（且 Init 内部会缓存结果）。
 * 这里与 api.php 保持一致：先按前台根目录手动设前缀，再取用户。
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

    /* 插件初始化：保证 Upload::attachmentHandle 兼容对象存储等插件 */
    \Typecho\Plugin::init(\Widget\Options::alloc()->plugins);

    $ecodUser = \Widget\User::alloc();
} catch (\Throwable $e) {
    ecod_media_out(['ok' => false, 'error' => 'Bootstrap failed', 'msg' => $e->getMessage()], 500);
}

if (!$ecodUser->hasLogin() || !$ecodUser->pass('editor', true)) {
    ecod_media_out(['ok' => false, 'error' => 'Unauthorized'], 401);
}

$action = isset($_GET['action']) ? (string) $_GET['action'] : 'list';

if ($action === 'ping') {
    ecod_media_out(['ok' => true, 'time' => time()]);
}

/* ------------------------------------------------------------------
 * 附件解析
 * ---------------------------------------------------------------- */

/**
 * contents.text 里存的是附件元信息（JSON；老数据可能是序列化字符串）
 */
function ecod_media_meta(string $raw): array
{
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = @unserialize($raw, ['allowed_classes' => false]);
    }
    return is_array($data) ? $data : [];
}

/**
 * 附件访问地址：走 Typecho 自己的句柄，兼容对象存储等插件
 */
function ecod_media_url(array $meta): string
{
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
 * 人类可读体积
 */
function ecod_media_bytes(int $size): string
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
 * 列表
 * ---------------------------------------------------------------- */

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$size = isset($_GET['size']) ? (int) $_GET['size'] : 48;
$size = max(1, min(120, $size));
$q    = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

/* 每条条件独立携带参数：type/status/mime 预过滤，精确的 mime 判断在下面 PHP 里做 */
$where = [
    ['type = ?', ['attachment']],
    ['status = ?', ['publish']],
    /* Typecho 存的 JSON 会把 / 转义成 \/（"mime":"image\/jpeg"），
     * LIKE '%image/%' 一条都匹配不上，必须两种写法都兼容（老数据是序列化串，存 image/） */
    ['(text LIKE ? OR text LIKE ?)', ['%image/%', '%image\\\\/%']],
];

if ($q !== '') {
    $where[] = ['title LIKE ?', ['%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%']];
}

try {
    $db = \Typecho\Db::get();

    $countSql = $db->select('COUNT(*) AS num')->from('table.contents');
    $query = $db->select('cid', 'title', 'created', 'text', 'parent')
        ->from('table.contents');
    foreach ($where as [$clause, $params]) {
        $countSql->where($clause, ...$params);
        $query->where($clause, ...$params);
    }
    $total = (int) ($db->fetchObject($countSql)->num ?? 0);

    $query->order('created', \Typecho\Db::SORT_DESC)
        ->limit($size)
        ->offset(($page - 1) * $size);

    $rows = $db->fetchAll($query);
} catch (\Throwable $e) {
    ecod_media_out(['ok' => false, 'error' => 'Query failed', 'msg' => $e->getMessage()], 500);
}

$items = [];
foreach ($rows as $row) {
    $meta = ecod_media_meta((string) $row['text']);
    $url  = ecod_media_url($meta);
    if ($url === '') {
        continue;
    }

    /* mime 判断：优先 meta['mime']；老数据 meta['type'] 存的可能是 mime（image/png）
     * 也可能只是扩展名（png），扩展名按已知图片后缀白名单处理 */
    $mime = strtolower((string) ($meta['mime'] ?? ''));
    if ($mime === '' || strpos($mime, '/') === false) {
        $type = strtolower((string) ($meta['type'] ?? ''));
        if (strpos($type, '/') !== false) {
            $mime = $type;
        } elseif (in_array($type, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'avif'], true)) {
            $mime = 'image/' . $type;
        } else {
            continue; /* 非图片附件 */
        }
    }
    if (strpos($mime, 'image/') !== 0) {
        continue;
    }

    $name = (string) ($meta['name'] ?? '');
    $items[] = [
        'cid'   => (int) $row['cid'],
        'title' => (string) $row['title'],
        'name'  => $name !== '' ? $name : basename((string) ($meta['path'] ?? '')),
        'url'   => $url,
        'mime'  => $mime,
        'size'  => ecod_media_bytes((int) ($meta['size'] ?? 0)),
        'bytes' => (int) ($meta['size'] ?? 0),
        'date'  => date('Y-m-d', (int) $row['created']),
        'time'  => (int) $row['created'],
    ];
}

ecod_media_out([
    'ok'      => true,
    'total'   => $total,
    'page'    => $page,
    'size'    => $size,
    'hasMore' => $page * $size < $total,
    'items'   => $items,
]);
