<?php
/**
 * Ecod Theme · 页面构建器（画布编辑器）
 *
 * 独立入口（/usr/themes/<主题>/builder.php），仅站长可用。
 * 布局文档（v5）的所见即所得编辑器，数据层在 core/admin/builder-doc.php，
 * 设计见 docs/builder-design.html。
 *
 * 画布 = iframe 加载带 ?ecod_builder=1 标记的真实前台页面（真实数据、真实渲染）。
 * 编辑器与前台同源，交互（点击选中 / 拖放 / 键盘）由 assets/admin/builder.js
 * 直接接管 iframe 文档；前台路径不注入任何编辑资源。
 *
 * 接口（JSON，action 分发）：
 *   doc.get    {key}                 → 文档 sections + rev
 *   doc.save   {key, sections, rev}  → 清洗落盘（ELayouts 行或覆盖表），rev 乐观锁
 *   doc.delete {key}                 → 删除文档（回退到回退链下一级）
 *
 * @package Ecod Theme
 * @author  Ecod
 * @since   2026-10-31
 * @version 1.0.0
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');

if (!function_exists('ecod_bld_status')) {
    /** 设定响应状态码（Typecho 收尾会用 Response 重设，两边一起写） */
    function ecod_bld_status(int $code): void
    {
        try {
            \Typecho\Response::getInstance()->setStatus($code);
        } catch (\Throwable $e) {
        }
        http_response_code($code);
    }
}

/* ------------------------------------------------------------------
 * 引导：定位站点根 → 载入内核 → 校正 cookie 前缀 → 管理员校验
 * ---------------------------------------------------------------- */
$ecodRoot = __DIR__;
while ($ecodRoot !== dirname($ecodRoot) && !is_file($ecodRoot . '/config.inc.php')) {
    $ecodRoot = dirname($ecodRoot);
}
if (!is_file($ecodRoot . '/config.inc.php')) {
    ecod_bld_status(500);
    echo 'Typecho root not found';
    exit;
}
require_once $ecodRoot . '/config.inc.php';

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
    $ecodUser = \Widget\User::alloc();
} catch (\Throwable $e) {
    ecod_bld_status(500);
    echo 'Bootstrap failed';
    exit;
}

$ecodIsAdmin = $ecodUser->hasLogin() && $ecodUser->pass('administrator', true);
if (!$ecodIsAdmin) {
    ecod_bld_status(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><meta charset="utf-8"><title>403</title>'
        . '<p style="font:15px/1.7 system-ui;padding:40px">需要管理员登录。'
        . '<a href="' . htmlspecialchars((string) \Widget\Options::alloc()->siteUrl . 'admin/login.php') . '">去登录</a></p>';
    exit;
}

require_once __DIR__ . '/core/lib/schema.php';
require_once __DIR__ . '/core/lib/asset.php';
require_once __DIR__ . '/core/admin/blocks.php';
require_once __DIR__ . '/core/admin/builder-doc.php';
require_once __DIR__ . '/components/icons.php';

/* 路由表显式初始化：管理端上下文默认没有跑 Widget\Init，
   Router::url 会退化成 '#'——文档预览地址依赖它，这里补齐 */
try {
    \Typecho\Router::setRoutes(\Widget\Options::alloc()->routingTable);
} catch (\Throwable $e) {
}

if (!function_exists('ecod_option')) {
    /** 主题选项读取（独立入口不加载 functions.php，补等价实现） */
    function ecod_option(string $key, $default = '')
    {
        try {
            $options = \Utils\Helper::options();
        } catch (\Throwable $e) {
            return $default;
        }
        return isset($options->{$key}) && $options->{$key} !== '' ? $options->{$key} : $default;
    }
}

header('X-Robots-Tag: noindex, nofollow');

if (!function_exists('ecod_bld_token')) {
    /** 表单令牌（与画布模式 ecod_builder_mode() 用同一命名空间） */
    function ecod_bld_token(): string
    {
        try {
            return (string) \Widget\Security::alloc()->getToken('ecod-widgets');
        } catch (\Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('ecod_bld_json')) {
    /** JSON 输出并终止 */
    function ecod_bld_json(array $data, int $status = 200): void
    {
        ecod_bld_status($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

/* ------------------------------------------------------------------
 * 写接口
 * ---------------------------------------------------------------- */
$ecodAction = (string) ($_GET['action'] ?? '');
if ($ecodAction !== '') {
    $ecodIn = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($ecodIn)) {
        $ecodIn = [];
    }

    $ecodExpect = ecod_bld_token();
    $ecodToken = (string) ($ecodIn['token'] ?? '');
    if ($ecodExpect === '' || $ecodToken === '' || !hash_equals($ecodExpect, $ecodToken)) {
        ecod_bld_json(['ok' => false, 'error' => '令牌校验失败，请刷新页面重试'], 403);
    }

    /* 脚本版本守卫：dist 哈希不匹配 = 旧标签页持旧脚本，写操作一律拒绝
       （旧代码不认识 v6 新字段，静默改坏数据——「删了又回来」的常见来源） */
    $ecodVer = (string) ($ecodIn['ver'] ?? '');
    $ecodVerNow = basename((string) ecod_dist_resolve('assets/admin/builder.js'));
    if ($ecodVer !== '' && $ecodVer !== $ecodVerNow && $ecodAction !== 'doc.get') {
        ecod_bld_json([
            'ok'    => false,
            'stale' => true,
            'error' => '编辑器脚本已更新：旧脚本不兼容新数据结构，请刷新页面后继续',
        ], 409);
    }

    $ecodRevNow = ecod_layouts_rev();
    $ecodRev = (string) ($ecodIn['rev'] ?? '');
    if (in_array($ecodAction, ['doc.save', 'doc.delete'], true) && $ecodRev !== '' && $ecodRev !== $ecodRevNow) {
        ecod_bld_json([
            'ok'    => false,
            'error' => '这份布局已被其他窗口或标签页修改，请刷新后重试',
            'rev'   => $ecodRevNow,
        ], 409);
    }

    switch ($ecodAction) {
        case 'doc.get':
            $key = (string) ($ecodIn['key'] ?? '');
            $meta = ecod_layout_admin_docs();
            if (!isset($meta[$key])) {
                ecod_bld_json(['ok' => false, 'error' => '未知文档：' . $key], 400);
            }
            $doc = ecod_layout_doc_for_key($key);
            ecod_bld_json([
                'ok'       => true,
                'key'      => $key,
                'sections' => $doc ? $doc['sections'] : [],
                'lock'     => $doc ? (int) ($doc['lock'] ?? 0) : 0,
                'rev'      => ecod_layouts_rev(),
    'ver'      => basename((string) ecod_dist_resolve('assets/admin/builder.js')),
            ]);
            break;

        case 'doc.lock':
            $key = (string) ($ecodIn['key'] ?? '');
            $meta = ecod_layout_admin_docs();
            if (!isset($meta[$key])) {
                ecod_bld_json(['ok' => false, 'error' => '未知文档：' . $key], 400);
            }
            $doc = ecod_layout_doc_for_key($key) ?: ['sections' => [], 'lock' => 0];
            $doc['lock'] = !empty($ecodIn['lock']) ? 1 : 0;
            if (!ecod_layout_save_doc($key, $doc)) {
                ecod_bld_json(['ok' => false, 'error' => '操作失败，请稍后重试'], 500);
            }
            ecod_bld_json(['ok' => true, 'lock' => $doc['lock'], 'rev' => ecod_layouts_rev()]);
            break;

        case 'doc.save':
            $key = (string) ($ecodIn['key'] ?? '');
            $meta = ecod_layout_admin_docs();
            if (!isset($meta[$key])) {
                ecod_bld_json(['ok' => false, 'error' => '未知文档：' . $key], 400);
            }
            $sections = is_array($ecodIn['sections'] ?? null) ? $ecodIn['sections'] : [];
            if (!ecod_layout_save_doc($key, ['sections' => $sections, 'lock' => !empty($ecodIn['lock']) ? 1 : 0])) {
                ecod_bld_json(['ok' => false, 'error' => '保存失败，请稍后重试'], 500);
            }
            $ecodSaved = ecod_layout_doc_for_key($key);
            ecod_layout_snap_push($key, $ecodSaved ? $ecodSaved['sections'] : []);
            ecod_layout_stage_clear($key);
            ecod_bld_json([
                'ok'       => true,
                'key'      => $key,
                'sections' => $ecodSaved ? $ecodSaved['sections'] : [],
                'lock'     => $ecodSaved ? (int) ($ecodSaved['lock'] ?? 0) : 0,
                'rev'      => ecod_layouts_rev(),
            ]);
            break;

        case 'settings.get':
            ecod_bld_json(['ok' => true,
                'defs' => ecod_bld_settings_defs(),
                'values' => ecod_bld_settings_values()]);
            break;

        case 'settings.save':
            $ecodSavedN = ecod_bld_settings_save(
                is_array($ecodIn['values'] ?? null) ? $ecodIn['values'] : []
            );
            ecod_bld_json(['ok' => true, 'saved' => $ecodSavedN,
                'values' => ecod_bld_settings_values()]);
            break;

        case 'rowlib.save':
            $ecodRow = is_array($ecodIn['row'] ?? null) ? $ecodIn['row'] : null;
            if ($ecodRow === null || empty($ecodRow['cells'])) {
                ecod_bld_json(['ok' => false, 'error' => '行内容为空，无法保存为模板'], 400);
            }
            ecod_bld_json(['ok' => true,
                'savedRows' => ecod_layout_rowlib_save(
                    (string) ($ecodIn['name'] ?? ''), $ecodRow)]);
            break;

        case 'rowlib.delete':
            ecod_bld_json(['ok' => true,
                'savedRows' => ecod_layout_rowlib_delete((string) ($ecodIn['id'] ?? ''))]);
            break;

        case 'doclib.save':
            $ecodLibSecs = is_array($ecodIn['sections'] ?? null) ? $ecodIn['sections'] : null;
            if ($ecodLibSecs === null || count($ecodLibSecs) === 0) {
                ecod_bld_json(['ok' => false, 'error' => '当前布局为空，无法存为模板'], 400);
            }
            ecod_bld_json(['ok' => true,
                'savedDocs' => ecod_layout_doclib_save(
                    (string) ($ecodIn['name'] ?? ''),
                    $ecodLibSecs,
                    !empty($ecodIn['lock']) ? 1 : 0)]);
            break;

        case 'doclib.delete':
            ecod_bld_json(['ok' => true,
                'savedDocs' => ecod_layout_doclib_delete((string) ($ecodIn['id'] ?? ''))]);
            break;

        case 'doc.adopt':
            $ecodAdoptKey = (string) ($ecodIn['key'] ?? '');
            $ecodAdoptMeta = ecod_layout_admin_docs();
            if (!isset($ecodAdoptMeta[$ecodAdoptKey])) {
                ecod_bld_json(['ok' => false, 'error' => '未知文档：' . $ecodAdoptKey], 400);
            }
            $ecodAdopted = ecod_layout_adopt_doc($ecodAdoptKey);
            if ($ecodAdopted === null) {
                ecod_bld_json(['ok' => false, 'error' => '没有可转换的旧版区域内容，或转换失败'], 400);
            }
            ecod_bld_json(['ok' => true, 'sections' => $ecodAdopted, 'rev' => ecod_layouts_rev()]);
            break;

        case 'snap.list':
            $key = (string) ($ecodIn['key'] ?? '');
            ecod_bld_json(['ok' => true, 'items' => ecod_layout_snap_index($key)]);
            break;

        case 'snap.restore':
            $key = (string) ($ecodIn['key'] ?? '');
            $ecodSnap = ecod_layout_snap_get($key, (string) ($ecodIn['id'] ?? ''));
            if ($ecodSnap === null) {
                ecod_bld_json(['ok' => false, 'error' => '快照不存在或已被滚动清理'], 404);
            }
            ecod_bld_json(['ok' => true, 'sections' => $ecodSnap, 'rev' => ecod_layouts_rev()]);
            break;

        case 'doc.delete':
            $key = (string) ($ecodIn['key'] ?? '');
            if (!ecod_layout_delete_doc($key)) {
                ecod_bld_json(['ok' => false, 'error' => '删除失败（可能本来就不存在）'], 400);
            }
            ecod_layout_stage_clear($key);
            ecod_bld_json(['ok' => true, 'rev' => ecod_layouts_rev()]);
            break;

        case 'stage.save':
            /* 编辑自动暂存（不触生产）：画布以暂存态渲染 */
            $key = (string) ($ecodIn['key'] ?? '');
            if (!isset(ecod_layout_admin_docs()[$key])) {
                ecod_bld_json(['ok' => false, 'error' => '未知文档：' . $key], 400);
            }
            if (is_array($ecodIn['sections'] ?? null)) {
                ecod_layout_stage_set($key, [
                    'sections' => $ecodIn['sections'],
                    'lock' => !empty($ecodIn['lock']) ? 1 : 0,
                ]);
            }
            if (is_array($ecodIn['wgtAreas'] ?? null)) {
                /* 只传脏区域：服务端按合并语义覆盖，未动区域不受影响 */
                ecod_wgt_stage_set(
                    $ecodIn['wgtAreas'],
                    is_array($ecodIn['wgtAside'] ?? null) ? $ecodIn['wgtAside'] : null
                );
            }
            ecod_bld_json(['ok' => true]);
            break;

        case 'stage.discard':
            $key = (string) ($ecodIn['key'] ?? '');
            ecod_layout_stage_clear($key);
            ecod_wgt_stage_clear();
            ecod_bld_json(['ok' => true]);
            break;

        case 'publish':
            /* 发布：暂存/本地态一次性写入生产（布局文档 + 全部 v4 区域），清空暂存 */
            $key = (string) ($ecodIn['key'] ?? '');
            if (!isset(ecod_layout_admin_docs()[$key])) {
                ecod_bld_json(['ok' => false, 'error' => '未知文档：' . $key], 400);
            }
            $ecodRevNow = ecod_layouts_rev();
            if ((string) ($ecodIn['rev'] ?? '') !== $ecodRevNow) {
                ecod_bld_json(['ok' => false, 'conflict' => 'doc', 'rev' => $ecodRevNow,
                    'error' => '布局已被其他窗口修改'], 409);
            }
            $ecodWgtRevNow = ecod_widgets_rev();
            if ((string) ($ecodIn['wgtRev'] ?? '') !== $ecodWgtRevNow) {
                ecod_bld_json(['ok' => false, 'conflict' => 'wgt', 'rev' => $ecodWgtRevNow,
                    'error' => '模块布局已被其他窗口修改'], 409);
            }
            $ecodSections = is_array($ecodIn['sections'] ?? null) ? $ecodIn['sections'] : [];
            ecod_layout_save_doc($key, [
                'sections' => $ecodSections,
                'lock' => !empty($ecodIn['lock']) ? 1 : 0,
            ]);
            $ecodSaved = ecod_layout_doc_for_key($key);
            ecod_layout_snap_push($key, $ecodSaved ? $ecodSaved['sections'] : []);
            $ecodWgtDoc = ecod_widgets_raw();
            foreach ((array) ($ecodIn['wgtAreas'] ?? []) as $ecodAreaId => $ecodAreaRows) {
                if (!isset(ecod_widget_areas()[$ecodAreaId])) {
                    continue;
                }
                $ecodWgtDoc['areas'][$ecodAreaId] = ecod_wgt_clean_rows(
                    $ecodAreaId, is_array($ecodAreaRows) ? $ecodAreaRows : []
                );
            }
            if (is_array($ecodIn['wgtAside'] ?? null)) {
                $ecodAsideClean = [];
                foreach (ecod_widget_pages() as $ecodPageKey => $ecodPageLabel) {
                    $ecodPos = (string) ($ecodIn['wgtAside'][$ecodPageKey] ?? '');
                    if (in_array($ecodPos, ['right', 'left', 'none'], true)) {
                        $ecodAsideClean[$ecodPageKey] = $ecodPos;
                    }
                }
                $ecodWgtDoc['aside'] = $ecodAsideClean;
            }
            ecod_widgets_write($ecodWgtDoc);
            ecod_layout_stage_clear($key);
            ecod_wgt_stage_clear();
            $ecodInst = [];
            foreach (ecod_widget_areas() as $ecodAreaId => $ecodAreaMeta) {
                $ecodInst[$ecodAreaId] = [];
                foreach (ecod_widget_instances($ecodAreaId) as $ecodOne) {
                    $ecodInst[$ecodAreaId][] = [
                        'uid' => $ecodOne['uid'], 'type' => $ecodOne['type'],
                        'on' => (bool) $ecodOne['on'], 'params' => $ecodOne['params'],
                    ];
                }
            }
            ecod_bld_json([
                'ok' => true,
                'sections' => $ecodSaved ? $ecodSaved['sections'] : [],
                'lock' => $ecodSaved ? (int) ($ecodSaved['lock'] ?? 0) : 0,
                'areas' => $ecodInst,
                'aside' => ecod_aside_map(),
                'rev' => ecod_layouts_rev(),
                'wgtRev' => ecod_widgets_rev(),
            ]);
            break;

        case 'area.save':
            /* v4 区域实例保存：与 widget.php 写同一份 EWidgets、同一套清洗 */
            $ecodArea = (string) ($ecodIn['area'] ?? '');
            if (!isset(ecod_widget_areas()[$ecodArea])) {
                ecod_bld_json(['ok' => false, 'error' => '未知的位置：' . $ecodArea], 400);
            }
            $ecodWgtRev = ecod_widgets_rev();
            $ecodWgtRevIn = (string) ($ecodIn['rev'] ?? '');
            if ($ecodWgtRevIn !== '' && $ecodWgtRevIn !== $ecodWgtRev) {
                ecod_bld_json([
                    'ok'    => false,
                    'error' => '模块布局已被其他窗口修改，请刷新后重试',
                    'rev'   => $ecodWgtRev,
                ], 409);
            }
            $ecodWgtDoc = ecod_widgets_raw();
            $ecodWgtDoc['areas'][$ecodArea] = ecod_wgt_clean_rows(
                $ecodArea,
                is_array($ecodIn['instances'] ?? null) ? $ecodIn['instances'] : []
            );
            ecod_widgets_write($ecodWgtDoc);
            ecod_bld_json([
                'ok'        => true,
                'area'      => $ecodArea,
                'instances' => ecod_widget_instances($ecodArea),
                'wgtRev'    => ecod_widgets_rev(),
            ]);
            break;

        case 'wgt.get':
            $ecodInst = [];
            foreach (ecod_widget_areas() as $ecodAreaId => $ecodAreaMeta) {
                $ecodInst[$ecodAreaId] = [];
                foreach (ecod_widget_instances($ecodAreaId) as $ecodOne) {
                    $ecodInst[$ecodAreaId][] = [
                        'uid'    => $ecodOne['uid'],
                        'type'   => $ecodOne['type'],
                        'on'     => (bool) $ecodOne['on'],
                        'params' => $ecodOne['params'],
                    ];
                }
            }
            ecod_bld_json(['ok' => true, 'areas' => $ecodInst, 'aside' => ecod_aside_map(), 'wgtRev' => ecod_widgets_rev()]);
            break;

        default:
            ecod_bld_json(['ok' => false, 'error' => '未知动作'], 400);
    }
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');

/* ------------------------------------------------------------------
 * 页面数据
 * ---------------------------------------------------------------- */
$ecodBldDocs = ecod_layout_admin_docs();
$ecodBldDefs = ecod_layout_admin_defs();

/* 图标：defs 里出现过的名字 + 编辑器 UI 固定图标（工具条/面板按钮用），
   缺了 UI 图标会让按钮渲染成空白方块 */
$ecodBldIcons = [];
foreach ($ecodBldDefs as $ecodDef) {
    $ecodName = (string) $ecodDef['icon'];
    if (!isset($ecodBldIcons[$ecodName])) {
        $ecodBldIcons[$ecodName] = ecod_icon($ecodName, 16);
    }
}
foreach (['copy', 'eye', 'trash', 'arrow-up', 'arrow-down', 'plus', 'check', 'x', 'image', 'edit', 'repeat', 'swap'] as $ecodUiIcon) {
    if (!isset($ecodBldIcons[$ecodUiIcon])) {
        $ecodBldIcons[$ecodUiIcon] = ecod_icon($ecodUiIcon, 16);
    }
}

/* 分类表：term / terms 字段的可视化选择数据 */
$ecodBldCats = new stdClass();
try {
    $ecodBldCatRows = \Typecho\Db::get()->fetchAll(
        \Typecho\Db::get()->select('mid', 'name')->from('table.metas')
            ->where('type = ?', 'category')
            ->order('order', \Typecho\Db::SORT_ASC)
            ->limit(200)
    );
    foreach ($ecodBldCatRows as $ecodCatRow) {
        $ecodBldCats->{(string) $ecodCatRow['mid']} = (string) $ecodCatRow['name'];
    }
} catch (\Throwable $e) {
}

$ecodBldKey = (string) ($_GET['doc'] ?? 'home');
if (!isset($ecodBldDocs[$ecodBldKey])) {
    $ecodBldKey = 'home';
}
$ecodBldDoc = ecod_layout_doc_for_key($ecodBldKey);

/* v4 区域模型数据：区域注册表 + 各区域实例（widget.php 同一份 EWidgets 存储） */
$ecodWgtData = ecod_widgets_admin_data();

$ecodSite = rtrim((string) \Widget\Options::alloc()->siteUrl, '/');
$ecodBase = $ecodSite . '/usr/themes/' . basename(__DIR__);
$ecodBldAsset = static function (string $path) use ($ecodBase): string {
    $dist = ecod_dist_resolve($path);
    return $ecodBase . '/' . ($dist ?? $path);
};

/* 画布地址：文档 url + builder 标记 + token（ecod_builder_mode() 校验同一令牌） */
$ecodBldCanvasUrl = '';
if (($ecodBldDocs[$ecodBldKey]['url'] ?? '') !== '') {
    $ecodBldSep = strpos((string) $ecodBldDocs[$ecodBldKey]['url'], '?') === false ? '?' : '&';
    $ecodBldCanvasUrl = $ecodBldDocs[$ecodBldKey]['url'] . $ecodBldSep . 'ecod_builder=1&token=' . urlencode(ecod_bld_token())
        . '&blddoc=' . urlencode($ecodBldKey);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>页面构建器 · <?php echo htmlspecialchars((string) \Widget\Options::alloc()->title, ENT_QUOTES, 'UTF-8'); ?></title>
<link rel="stylesheet" href="<?php echo htmlspecialchars($ecodBldAsset('assets/admin/tokens.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars($ecodBldAsset('assets/admin/media.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars($ecodBldAsset('assets/admin/builder.css'), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>

<header class="bld-top">
    <div class="bld-top__side">
        <button type="button" class="bld-chip" data-exit>← 退出</button>
        <button type="button" class="bld-chip bld-chip--on" data-docbtn>
            <span data-doclabel></span> <?php echo ecod_icon('chevron-down', 14); ?>
        </button>
        <div class="bld-menu" data-docmenu hidden></div>
    </div>
    <div class="bld-top__mid">
        <div class="bld-seg3" data-devseg>
            <button type="button" data-dev="desktop" aria-pressed="true">桌面</button>
            <button type="button" data-dev="tablet" aria-pressed="false">平板</button>
            <button type="button" data-dev="phone" aria-pressed="false">手机</button>
        </div>
        <button type="button" class="bld-chip" data-fit>fit</button>
        <button type="button" class="bld-chip" data-undo disabled title="撤销（⌘/Ctrl+Z）"><?php echo ecod_icon('undo', 14); ?><span data-undocount></span></button>
        <button type="button" class="bld-chip" data-redo disabled title="重做（⌘/Ctrl+Y）"><?php echo ecod_icon('redo', 14); ?><span data-redocount></span></button>
    </div>
    <div class="bld-top__side bld-top__side--r">
        <button type="button" class="bld-chip" data-setbtn>样式</button>
        <button type="button" class="bld-chip" data-snapbtn>历史 <?php echo ecod_icon('chevron-down', 14); ?></button>
        <div class="bld-menu" data-snapmenu hidden style="left:auto;right:0"></div>
        <button type="button" class="bld-chip" data-lockbtn title="锁定后画布只允许修改模块参数，结构（增删移/栏式/背景等）全部只读">未锁定</button>
        <button type="button" class="bld-chip" data-tplbtn>模板 <?php echo ecod_icon('chevron-down', 14); ?></button>
        <div class="bld-menu" data-tplmenu hidden style="left:auto;right:0"></div>
        <input type="file" accept="application/json,.json" data-tplfile hidden>
        <button type="button" class="bld-chip" data-preview>预览</button>
        <button type="button" class="bld-chip" data-manage>模块管理台</button>
        <button type="button" class="bld-btn-pri" data-save disabled>已保存</button>
    </div>
</header>

<div class="bld-app">
    <aside class="bld-left">
        <div class="bld-tabs">
            <button type="button" data-tab="lib" aria-pressed="true">模块库</button>
            <button type="button" data-tab="areas" aria-pressed="false">区域</button>
            <button type="button" data-tab="ol" aria-pressed="false">大纲</button>
        </div>
        <div class="bld-lib" data-libpane>
            <div class="bld-lib__search"><input type="search" placeholder="搜索模块…" data-find aria-label="搜索模块"></div>
            <div class="bld-lib__scroll" data-lib></div>
        </div>
        <div class="bld-lib" data-areapane hidden>
            <div class="bld-lib__scroll" data-areas></div>
            <div class="bld-wtrash" data-wtrash><?php echo ecod_icon('trash', 13); ?> 拖模块到此处删除</div>
        </div>
        <div class="bld-lib" data-olpane hidden>
            <div class="bld-lib__scroll" data-outline></div>
        </div>
        <div class="bld-left__foot">
            <button type="button" class="bld-mini" data-delldoc disabled>删除此布局</button>
        </div>
    </aside>

    <section class="bld-center" data-center>
        <div class="bld-loading" data-loading hidden>渲染中…</div>
        <div class="bld-view" data-view>
            <iframe class="bld-frame" data-frame title="画布"
                sandbox="allow-same-origin"
                <?php if ($ecodBldCanvasUrl !== ''): ?>src="<?php echo htmlspecialchars($ecodBldCanvasUrl, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>></iframe>
        </div>
        <div class="bld-empty" data-empty hidden>
            <p>这份布局还没有地址可预览（该页面类型没有可打开的实例）。</p>
            <p class="bld-empty__sub">仍可以在右侧搭建结构，保存后到对应页面查看效果。</p>
        </div>
    </section>

    <aside class="bld-insp" data-inspector></aside>
</div>

<footer class="bld-status">
    <span>缩放 <input type="range" min="50" max="150" value="100" data-zoom> <b data-zoomval>100%</b></span>
    <span data-readout></span>
    <span class="bld-status__sp"></span>
    <span class="bld-status__dirty" data-dirty hidden>● 未保存</span>
    <span data-saved>已保存</span>
    <span class="bld-status__rev" data-rev></span>
</footer>

<div class="bld-modal" data-setmodal hidden>
        <div class="bld-modal__card bld-setcard">
            <h4>站点样式</h4>
            <p>与「主题设置 → 外观 / 布局」是同一份数据，双处修改互通；保存后画布即时刷新，前台立即生效。</p>
            <div class="bld-setbody" data-setbody></div>
            <div class="bld-modal__row">
                <button type="button" class="bld-mini" data-setclose>取消</button>
                <button type="button" class="bld-btn-pri" data-setsave>保存样式</button>
            </div>
        </div>
    </div>

<div class="bld-modal" data-modal hidden>
    <div class="bld-modal__card">
        <h4 data-modal-title>提示</h4>
        <p data-modal-desc></p>
        <div class="bld-modal__row">
            <button type="button" class="bld-mini" data-guard="cancel">取消</button>
            <button type="button" class="bld-mini" data-guard="discard">放弃改动</button>
            <button type="button" class="bld-btn-pri" data-guard="save">保存并继续</button>
        </div>
    </div>
</div>

<div class="bld-toast" data-toast hidden></div>

<script>
window.ECOD_BLD = <?php echo json_encode([
    'defs'     => $ecodBldDefs,
    'icons'    => $ecodBldIcons,
    'cats'     => $ecodBldCats,
    'docs'     => $ecodBldDocs,
    'templates'=> ecod_layout_templates(),
    'savedRows'=> ecod_layout_rowlib_all(),
    'savedDocs' => ecod_layout_doclib_all(),
    'docKey'   => $ecodBldKey,
    'sections' => $ecodBldDoc ? $ecodBldDoc['sections'] : [],
    'canvas'   => $ecodBldCanvasUrl,
    'lock'     => $ecodBldDoc ? (int) ($ecodBldDoc['lock'] ?? 0) : 0,
    'wgt'      => [
        'areas'   => $ecodWgtData['instances'],
        'areaMeta'=> $ecodWgtData['areas'],
        'pages'   => $ecodWgtData['pages'],
        'aside'   => ecod_aside_map(),
        'rev'     => $ecodWgtData['rev'],
    ],
    'rev'      => ecod_layouts_rev(),
    'token'    => ecod_bld_token(),
    'ajax'     => $ecodBase . '/builder.php',
    'site'     => $ecodSite . '/',
    'maxSections' => ECOD_LAYOUT_SECTIONS_MAX,
    'maxRows'  => ECOD_LAYOUT_ROWS_MAX,
    'maxCells' => ECOD_LAYOUT_CELLS_MAX,
    'maxCols'  => ECOD_WIDGET_LIMIT,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
</script>
<script src="<?php echo htmlspecialchars($ecodBldAsset('assets/admin/media.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<script src="<?php echo htmlspecialchars($ecodBldAsset('assets/admin/builder.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
</body>
</html>
