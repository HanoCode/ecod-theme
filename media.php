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
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
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
require_once __DIR__ . '/core/media/storage.php';

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
 * 写操作（POST + CSRF）
 *
 * token 与后台注入的 window.ECOD_MEDIA.token 同源：
 * Security::getToken('ecod-media') = md5(secret & authCode & uid & 'ecod-media')，
 * 攻击者拿不到 secret+authCode 就无法伪造，不绑定 Referer（fetch 的
 * Referer 随来源页变化，绑了反而难校验）。
 * ---------------------------------------------------------------- */

function ecod_media_csrf_guard(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ecod_media_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    $token = (string) ($_POST['_'] ?? '');
    try {
        $expected = \Widget\Security::alloc()->getToken('ecod-media');
    } catch (\Throwable $e) {
        ecod_media_out(['ok' => false, 'error' => 'CSRF init failed'], 500);
    }
    if ($token === '' || !hash_equals($expected, $token)) {
        ecod_media_out(['ok' => false, 'error' => 'CSRF token 无效，请刷新后台页面重试'], 403);
    }
}

/* 上传（沿用 Typecho 的逐文件 FormData 模式，前端并发逐个提交） */
if ($action === 'upload') {
    ecod_media_csrf_guard();
    if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
        ecod_media_out(['ok' => false, 'error' => '没有收到文件'], 400);
    }
    $cid = isset($_POST['cid']) ? (int) $_POST['cid'] : 0;
    $result = ecod_media_upload_handle($_FILES['file'], $cid);
    if (isset($result['error'])) {
        ecod_media_out(['ok' => false, 'error' => $result['error']], 400);
    }
    $result['ok'] = true;
    ecod_media_out($result);
}

/* 删除（被引用时需 force=1） */
if ($action === 'delete') {
    ecod_media_csrf_guard();
    $cid = isset($_POST['cid']) ? (int) $_POST['cid'] : 0;
    $force = isset($_POST['force']) && $_POST['force'] === '1';
    if ($cid <= 0) {
        ecod_media_out(['ok' => false, 'error' => '参数缺失'], 400);
    }
    $result = ecod_media_delete_attachment($cid, $force);
    if (isset($result['error'])) {
        ecod_media_out(['ok' => false, 'error' => $result['error'], 'refs' => $result['refs'] ?? 0], 409);
    }
    ecod_media_out(['ok' => true, 'cid' => $cid, 'refs' => $result['refs'] ?? 0]);
}

/* 改名（只改 title，URL 不变） */
if ($action === 'rename') {
    ecod_media_csrf_guard();
    $cid = isset($_POST['cid']) ? (int) $_POST['cid'] : 0;
    $title = trim((string) ($_POST['title'] ?? ''));
    if ($cid <= 0 || $title === '') {
        ecod_media_out(['ok' => false, 'error' => '参数缺失'], 400);
    }
    if (mb_strlen($title) > 150) {
        ecod_media_out(['ok' => false, 'error' => '名称过长'], 400);
    }
    try {
        $db = \Typecho\Db::get();
        $db->query($db->update('table.contents')->rows(['title' => $title])
            ->where('cid = ?', $cid)->where('type = ?', 'attachment'));
    } catch (\Throwable $e) {
        ecod_media_out(['ok' => false, 'error' => '更新失败：' . $e->getMessage()], 500);
    }
    ecod_media_out(['ok' => true, 'cid' => $cid, 'title' => $title]);
}

/* 存量转 WebP（生成新附件，不动旧行） */
if ($action === 'convert') {
    ecod_media_csrf_guard();
    $cid = isset($_POST['cid']) ? (int) $_POST['cid'] : 0;
    if ($cid <= 0) {
        ecod_media_out(['ok' => false, 'error' => '参数缺失'], 400);
    }
    $result = ecod_media_convert_to_webp($cid);
    if (isset($result['error'])) {
        ecod_media_out(['ok' => false, 'error' => $result['error']], 400);
    }
    $result['ok'] = true;
    ecod_media_out($result);
}

/* 用量统计：附件数 / 总体积 / 存储分布 */
if ($action === 'stats') {
    try {
        $db = \Typecho\Db::get();
        $sql = $db->select('text')->from('table.contents')
            ->where('type = ?', 'attachment')
            ->where('status = ?', 'publish')
            ->where('(text LIKE ? OR text LIKE ?)', '%image/%', '%image\\\\/%');
        $rows = $db->fetchAll($sql);
    } catch (\Throwable $e) {
        ecod_media_out(['ok' => false, 'error' => 'Query failed'], 500);
    }
    $total = 0;
    $bytes = 0;
    $drivers = [];
    foreach ($rows as $row) {
        $meta = ecod_media_meta((string) $row['text']);
        if (!is_array($meta) || !$meta) {
            continue;
        }
        $mime = strtolower((string) ($meta['mime'] ?? ($meta['type'] ?? '')));
        if ($mime === '' || strpos($mime, 'image/') !== 0) {
            if (strpos((string) ($meta['type'] ?? ''), '/') !== false && strpos($mime, 'image/') !== 0) {
                continue;
            }
        }
        $total++;
        $bytes += (int) ($meta['size'] ?? 0);
        $d = (string) ($meta['driver'] ?? 'local');
        $drivers[$d] = ($drivers[$d] ?? 0) + 1;
    }
    ecod_media_out(['ok' => true, 'total' => $total, 'bytes' => $bytes, 'size' => ecod_media_bytes($bytes), 'drivers' => $drivers]);
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
 * 附件访问地址：云驱动直取 meta.url，本地走 Typecho 句柄（storage.php 统一实现）
 */
function ecod_media_url(array $meta): string
{
    return ecod_media_attachment_url($meta);
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
$fmt  = isset($_GET['fmt']) ? strtolower(trim((string) $_GET['fmt'])) : '';
if ($fmt === 'jpg') {
    $fmt = 'jpeg';
}

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
$keyByCid = [];
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
        } elseif (ecod_media_ext_allowed($type)) {
            $mime = 'image/' . $type;
        } else {
            continue; /* 非图片附件 */
        }
    }
    if (strpos($mime, 'image/') !== 0) {
        continue;
    }

    $name = (string) ($meta['name'] ?? '');
    $ext  = strtolower((string) ($meta['type'] ?? pathinfo($name, PATHINFO_EXTENSION)));
    if ($ext === 'jpg') {
        $ext = 'jpeg';
    }
    if ($fmt !== '' && $ext !== $fmt) {
        continue;
    }
    $key  = ecod_media_attachment_key($meta);
    $driver = (string) ($meta['driver'] ?? 'local');
    $keyByCid[(int) $row['cid']] = $key;

    /* 列表缩略图：本地走 GD 管线，云驱动走 CDN 图片处理参数 */
    $thumb = '';
    if ($ext !== 'svg' && $ext !== 'heic' && $ext !== 'heif') {
        if ($driver === 'local') {
            $thumb = function_exists('ecod_thumb_url') ? ecod_thumb_url($url, 'side') : '';
        } else {
            $thumb = ecod_media_storage_by_id($driver)->thumbUrl($url, 'side', 240, 180);
        }
    }

    $items[] = [
        'cid'    => (int) $row['cid'],
        'title'  => (string) $row['title'],
        'name'   => $name !== '' ? $name : basename((string) ($meta['path'] ?? '')),
        'url'    => $url,
        'thumb'  => $thumb,
        'mime'   => $mime,
        'ext'    => $ext,
        'driver' => $driver,
        'parent' => (int) $row['parent'],
        'size'   => ecod_media_bytes((int) ($meta['size'] ?? 0)),
        'bytes'  => (int) ($meta['size'] ?? 0),
        'date'   => date('Y-m-d', (int) $row['created']),
        'time'   => (int) $row['created'],
    ];
}

/* 引用计数：按本页附件的 key 特征（Y/m/文件名）做一次批量 LIKE */
if ($items) {
    $features = [];
    foreach ($items as $i => $item) {
        $feature = ltrim((string) ($keyByCid[$item['cid']] ?? ''), '/');
        if ($feature === '') {
            /* 本地附件从 URL path 还原 Y/m/名.ext */
            $path = (string) (parse_url($item['url'], PHP_URL_PATH) ?: '');
            $pos = strpos($path, '/usr/uploads/');
            $feature = $pos === false ? basename($path) : substr($path, $pos + strlen('/usr/uploads/'));
        }
        if ($feature !== '') {
            $features[$i] = $feature;
        }
    }
    if ($features) {
        try {
            $db = \Typecho\Db::get();
            $clauses = [];
            $params = [];
            foreach ($features as $feature) {
                $clauses[] = 'text LIKE ?';
                $params[] = '%' . str_replace(['%', '_'], ['\%', '\_'], $feature) . '%';
            }
            $sql = $db->select('cid', 'text')->from('table.contents')
                ->where('type IN ?', ['post', 'page', 'post_draft', 'page_draft'])
                ->where('(' . implode(' OR ', $clauses) . ')', ...$params);
            $rows = $db->fetchAll($sql);
            foreach ($features as $i => $feature) {
                $n = 0;
                foreach ($rows as $row) {
                    if (strpos((string) $row['text'], $feature) !== false) {
                        $n++;
                    }
                }
                $items[$i]['refs'] = $n;
            }
        } catch (\Throwable $e) {
            /* 引用计数失败不影响列表 */
        }
    }
    foreach ($items as $i => $item) {
        if (!isset($items[$i]['refs'])) {
            $items[$i]['refs'] = 0;
        }
    }
}

ecod_media_out([
    'ok'      => true,
    'total'   => $total,
    'page'    => $page,
    'size'    => $size,
    'hasMore' => $page * $size < $total,
    'items'   => $items,
]);
