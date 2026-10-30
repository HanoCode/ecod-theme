<?php
/**
 * Ecod Theme · 模块管理台
 *
 * 独立入口（/usr/themes/<主题>/widget.php），仅站长可用。
 * 布局照 WordPress「外观 → 小工具」经典设计：
 *   - 左栏「可用模块」：全部模块可搜索，拖到右栏区域 = 添加实例，
 *     单击卡片展开目标位置列表（列出全部区域，已满的置灰）
 *   - 右栏「区域盒子」：全部区域按页面类型分组，组内一行多列排布；
 *     区域头即折叠开关（空区域默认收起，手动状态记 localStorage），
 *     折叠区仍可投放、悬停自动展开；实例行点击头部内联展开参数表单
 *     与操作行；有表单展开的区域横跨整行
 *   - 显式保存：改动先暂存（状态点「未保存」+ 按钮点亮），点「保存改动」
 *     或 Ctrl/Cmd+S 一次性按序提交全部脏区域
 *   - 自由投放：不做区域白名单拦截，任何模块可放任何区域，放错由用户
 *     自行调整；唯一拦截是区域已满（20 个）
 *   - 顶部折叠卡：侧栏按页面类型显示（右侧栏 / 左侧栏 / 无侧栏）
 *
 * 为什么不放进主题设置页：主题设置是一张原生表单，改一个参数要整页 POST，
 * 还得防其它分组未保存的内容被一起提交。这里一个动作只写一个区域，
 * 数据落在 options 表 user=0 的独立行 EWidgets（不碰 theme:{slug} 主题配置行）。
 *
 * 设计见 docs/dev/design-proposal.html §31。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @since   2026-10-29
 * @version 2.3.1
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');

/**
 * 设定响应状态码
 *
 * Typecho 收尾时会用 Response 里的状态重设 header，所以只调
 * http_response_code() 会被覆盖回 200，这里两边一起写。
 */
if (!function_exists('ecod_wgt_status')) {
    function ecod_wgt_status(int $code): void
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
    ecod_wgt_status(500);
    echo 'Typecho root not found';
    exit;
}
require_once $ecodRoot . '/config.inc.php';

/* cookie 前缀：脚本在 /usr/themes/<主题>/ 下，必须按前台根目录重算 */
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
    ecod_wgt_status(500);
    echo 'Bootstrap failed';
    exit;
}

$ecodIsAdmin = $ecodUser->hasLogin() && $ecodUser->pass('administrator', true);
if (!$ecodIsAdmin) {
    ecod_wgt_status(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><meta charset="utf-8"><title>403</title>'
        . '<p style="font:15px/1.7 system-ui;padding:40px">需要管理员登录。'
        . '<a href="' . htmlspecialchars((string) \Widget\Options::alloc()->siteUrl . 'admin/login.php') . '">去登录</a></p>';
    exit;
}

require_once __DIR__ . '/core/lib/schema.php';
require_once __DIR__ . '/core/admin/blocks.php';
require_once __DIR__ . '/components/icons.php';

/**
 * 主题选项读取
 *
 * 正常请求里由 functions.php 提供；独立入口不加载整个 functions.php，
 * 这里补一份等价实现，避免 blocks.php 在本页里找不到它。
 */
if (!function_exists('ecod_option')) {
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

/* ------------------------------------------------------------------
 * 令牌与输出助手
 * ---------------------------------------------------------------- */
if (!function_exists('ecod_wgt_token')) {
    /** 取本页表单令牌（Typecho Security 的防 CSRF token） */
    function ecod_wgt_token(): string
    {
        try {
            return (string) \Widget\Security::alloc()->getToken('ecod-widgets');
        } catch (\Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('ecod_wgt_token_ok')) {
    /** 常量时间比对请求令牌；令牌不可用一律拒绝 */
    function ecod_wgt_token_ok(string $token): bool
    {
        $expect = ecod_wgt_token();
        return $expect !== '' && $token !== '' && hash_equals($expect, $token);
    }
}

if (!function_exists('ecod_wgt_json')) {
    /** 以 JSON 输出并终止；状态码统一走 ecod_wgt_status()（见该函数说明） */
    function ecod_wgt_json(array $data, int $status = 200): void
    {
        /* Typecho 在响应收尾时会重设状态码，统一走 ecod_wgt_status() */
        ecod_wgt_status($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

/**
 * 参数清洗：只保留 schema 里声明过的键，按类型收口
 *
 * @param array $def  模块声明
 * @param array $in   前端提交的参数
 */
function ecod_wgt_clean_params(array $def, array $in): array
{
    $out = [];

    foreach (($def['params'] ?? []) as $key => $param) {
        if (!array_key_exists($key, $in)) {
            continue;
        }
        $value = $in[$key];
        $type = (string) ($param['type'] ?? 'text');

        switch ($type) {
            case 'number':
                /* 空值合法：存空串，前台回落声明默认值（§31 第 5 轮修订） */
                if ($value === '' || $value === null) {
                    $out[$key] = '';
                    break;
                }
                $num = (int) $value;
                if (isset($param['min']) && $num < (int) $param['min']) {
                    $num = (int) $param['min'];
                }
                if (isset($param['max']) && $num > (int) $param['max']) {
                    $num = (int) $param['max'];
                }
                $out[$key] = $num;
                break;

            case 'switch':
                $out[$key] = !empty($value) ? 1 : 0;
                break;

            case 'select':
                $options = array_map('strval', array_keys((array) ($param['options'] ?? [])));
                $pick = (string) $value;
                $out[$key] = in_array($pick, $options, true) ? $pick : '';
                break;

            case 'slides':
                $out[$key] = ecod_wgt_clean_slides($value);
                break;

            case 'term':
                /* 单选分类：只放行纯数字 mid，空表示用声明默认 */
                $pick = trim((string) $value);
                $out[$key] = preg_match('/^\d+$/', $pick) ? $pick : '';
                break;

            case 'terms':
                /* 多选分类：逗号分隔 mid，去重去空 */
                $picks = preg_split('/[\s,，]+/', (string) $value) ?: [];
                $picks = array_values(array_unique(array_filter($picks, static function ($one) {
                    return preg_match('/^\d+$/', (string) $one) === 1;
                })));
                $out[$key] = implode(',', $picks);
                break;

            case 'image':
                $url = trim(str_replace(["\r", "\n", "\t"], '', (string) $value));
                /* 只放行 http(s)、站内相对路径与 data:image，挡住 javascript: 之类 */
                if ($url !== '' && !preg_match('#^(https?://|/|\./|data:image/)#i', $url)) {
                    $url = '';
                }
                $out[$key] = mb_substr($url, 0, 500);
                break;

            case 'textarea':
                $out[$key] = mb_substr(trim((string) $value), 0, 20000);
                break;

            case 'repeater':
                $out[$key] = ecod_wgt_clean_repeater($value, (array) ($param['fields'] ?? []), (int) ($param['max'] ?? 20));
                break;

            default:
                $out[$key] = mb_substr(trim((string) $value), 0, 2000);
        }
    }

    return $out;
}

/**
 * repeater 参数：JSON 数组字符串清洗（第 10 轮）
 *
 * 只保留声明过的子字段键；行数截到 max；全空行剔除。
 *
 * @param mixed $value JSON 字符串或数组
 */
function ecod_wgt_clean_repeater($value, array $fields, int $max): string
{
    if (is_string($value)) {
        $value = trim($value);
        $value = $value === '' ? [] : json_decode($value, true);
    }
    if (!is_array($value)) {
        return '';
    }

    $keys = [];
    foreach ($fields as $field) {
        $key = (string) ($field['key'] ?? '');
        if ($key !== '') {
            $keys[] = $key;
        }
    }
    if ($keys === []) {
        return '';
    }

    $out = [];
    foreach ($value as $row) {
        if (!is_array($row)) {
            continue;
        }
        $item = [];
        $filled = false;
        foreach ($keys as $key) {
            $one = mb_substr(trim((string) ($row[$key] ?? '')), 0, 500);
            if (str_contains($one, 'javascript:')) {
                $one = '';
            }
            $item[$key] = $one;
            if ($one !== '') {
                $filled = true;
            }
        }
        if ($filled) {
            $out[] = $item;
        }
        if (count($out) >= max(1, $max)) {
            break;
        }
    }

    return $out === [] ? '' : json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * 幻灯片参数：JSON 字符串 ⇄ 数组，逐行清洗
 *
 * @param mixed $value JSON 字符串或数组
 */
function ecod_wgt_clean_slides($value): string
{
    if (is_string($value)) {
        $value = trim($value);
        $value = $value === '' ? [] : json_decode($value, true);
    }
    if (!is_array($value)) {
        return '';
    }

    $rows = [];
    foreach (array_slice($value, 0, 12) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $item = [
            'image' => mb_substr(trim((string) ($row['image'] ?? '')), 0, 500),
            'title' => mb_substr(trim((string) ($row['title'] ?? '')), 0, 120),
            'desc'  => mb_substr(trim((string) ($row['desc'] ?? '')), 0, 200),
            'link'  => mb_substr(trim((string) ($row['link'] ?? '')), 0, 500),
        ];
        if ($item['image'] === '' && $item['title'] === '') {
            continue;
        }
        $rows[] = $item;
    }

    return $rows ? (string) json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
}

/**
 * 一整组实例的清洗：类型必须存在、uid 唯一（不校验区域白名单，任何模块可放任何区域）
 *
 * @return array<int,array> 可写入的实例行
 */
function ecod_wgt_clean_rows(string $area, array $rows): array
{
    $defs = ecod_block_defs();
    $out = [];
    $seen = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $type = (string) ($row['type'] ?? '');
        if ($type === '' || !isset($defs[$type])) {
            continue;
        }

        $uid = (string) ($row['uid'] ?? '');
        if (!preg_match('/^[a-z0-9-]{1,40}$/', $uid) || isset($seen[$uid])) {
            $uid = $type . '-' . (count($out) + 1);
        }
        if (isset($seen[$uid])) {
            continue;
        }

        $seen[$uid] = true;
        $out[] = [
            'uid'    => $uid,
            'type'   => $type,
            'on'     => !empty($row['on']) ? 1 : 0,
            'params' => ecod_wgt_clean_params($defs[$type], is_array($row['params'] ?? null) ? $row['params'] : []),
        ];

        if (count($out) >= ECOD_WIDGET_LIMIT) {
            break;
        }
    }

    return $out;
}

/* ------------------------------------------------------------------
 * 写接口（JSON，一个动作只动一个区域）
 * ---------------------------------------------------------------- */
$ecodAction = (string) ($_GET['action'] ?? '');
if ($ecodAction !== '') {
    $ecodIn = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($ecodIn)) {
        $ecodIn = [];
    }

    if (!ecod_wgt_token_ok((string) ($ecodIn['token'] ?? ''))) {
        ecod_wgt_json(['ok' => false, 'error' => '令牌校验失败，请刷新页面重试'], 403);
    }

    $ecodDoc = ecod_widgets_raw();
    $ecodRev = (string) ($ecodIn['rev'] ?? '');
    if ($ecodRev !== '' && $ecodRev !== ecod_widgets_rev($ecodDoc)) {
        ecod_wgt_json([
            'ok'    => false,
            'error' => '这一区域已被其他窗口或标签页修改，请重新载入后再改',
            'rev'   => ecod_widgets_rev($ecodDoc),
        ], 409);
    }

    switch ($ecodAction) {
        case 'save':
            $area = (string) ($ecodIn['area'] ?? '');
            $areas = ecod_widget_areas();
            if (!isset($areas[$area])) {
                ecod_wgt_json(['ok' => false, 'error' => '未知的位置：' . $area], 400);
            }

            $rows = is_array($ecodIn['instances'] ?? null) ? $ecodIn['instances'] : [];
            $clean = ecod_wgt_clean_rows($area, $rows);
            $ecodDoc['areas'][$area] = $clean;
            ecod_widgets_write($ecodDoc);

            ecod_wgt_json([
                'ok'    => true,
                'area'  => $area,
                'rev'   => ecod_widgets_rev(ecod_widgets_raw()),
                'count' => count($clean),
            ]);
            break;

        case 'aside':
            $map = is_array($ecodIn['aside'] ?? null) ? $ecodIn['aside'] : [];
            $clean = [];
            foreach (ecod_widget_pages() as $page => $label) {
                $pos = (string) ($map[$page] ?? '');
                if (in_array($pos, ['right', 'left', 'none'], true)) {
                    $clean[$page] = $pos;
                }
            }
            $ecodDoc['aside'] = $clean;
            ecod_widgets_write($ecodDoc);
            ecod_wgt_json(['ok' => true, 'rev' => ecod_widgets_rev(ecod_widgets_raw()), 'aside' => ecod_aside_map()]);
            break;

        case 'export':
            ecod_wgt_json(['ok' => true, 'doc' => $ecodDoc]);
            break;

        case 'media':
            /* 媒体库选图（第 11 轮）：只回图片附件，标题关键词 + 分页
             * 白名单统一走 core/media/storage.php 的 ecod_media_ext_allowed() */
            $ecodMediaQ = trim((string) ($ecodIn['q'] ?? ''));
            $ecodMediaPage = max(1, (int) ($ecodIn['page'] ?? 1));
            $ecodMediaSize = 24;

            $ecodMediaRows = \Typecho\Db::get()->fetchAll(
                \Typecho\Db::get()->select('cid', 'title', 'text')
                    ->from('table.contents')
                    ->where('type = ?', 'attachment')
                    ->order('created', \Typecho\Db::SORT_DESC)
            );

            $ecodMediaItems = [];
            foreach ($ecodMediaRows as $ecodMediaRow) {
                $ecodMediaData = json_decode((string) $ecodMediaRow['text'], true);
                if (!is_array($ecodMediaData) || !ecod_media_ext_allowed((string) ($ecodMediaData['type'] ?? ''))) {
                    continue;
                }
                if ($ecodMediaQ !== '' && mb_stripos($ecodMediaRow['title'] . ' ' . ($ecodMediaData['name'] ?? ''), $ecodMediaQ) === false) {
                    continue;
                }
                $ecodMediaUrl = '';
                try {
                    $ecodMediaUrl = ecod_media_attachment_url($ecodMediaData);
                } catch (\Throwable $e) {
                    continue;
                }
                $ecodMediaItems[] = [
                    'cid'   => (int) $ecodMediaRow['cid'],
                    'title' => (string) $ecodMediaRow['title'],
                    'url'   => $ecodMediaUrl,
                ];
            }

            $ecodMediaTotal = count($ecodMediaItems);
            $ecodMediaPages = max(1, (int) ceil($ecodMediaTotal / $ecodMediaSize));
            $ecodMediaPage = min($ecodMediaPage, $ecodMediaPages);

            ecod_wgt_json([
                'ok'    => true,
                'items' => array_slice($ecodMediaItems, ($ecodMediaPage - 1) * $ecodMediaSize, $ecodMediaSize),
                'total' => $ecodMediaTotal,
                'page'  => $ecodMediaPage,
                'pages' => $ecodMediaPages,
            ]);
            break;

        case 'import':
            $inDoc = is_array($ecodIn['doc'] ?? null) ? $ecodIn['doc'] : null;
            if (!$inDoc || empty($inDoc['areas']) || !is_array($inDoc['areas'])) {
                ecod_wgt_json(['ok' => false, 'error' => '导入文件不是有效的模块布局'], 400);
            }

            /* 导入前备份当前布局（第 9 轮修订） */
            ecod_cache_set('EWidgetsBackupImport', $ecodDoc);

            /* 旧版本布局先过合并映射（纯映射，不动备份与存储） */
            $inDoc = ecod_widgets_migrate_map($inDoc, (int) ($inDoc['v'] ?? 0));

            /* 导入前计数，未知类型跳过并汇报，不静默丢 */
            $inRows = 0;
            $inKnown = 0;
            $defs = ecod_block_defs();
            foreach ($inDoc['areas'] as $rows) {
                foreach ((is_array($rows) ? $rows : []) as $row) {
                    $inRows++;
                    if (isset($defs[(string) ($row['type'] ?? '')])) {
                        $inKnown++;
                    }
                }
            }

            ecod_widgets_write($inDoc);

            ecod_wgt_json([
                'ok'       => true,
                'imported' => $inKnown,
                'skipped'  => $inRows - $inKnown,
                'rev'      => ecod_widgets_rev(ecod_widgets_raw()),
            ]);
            break;

        default:
            ecod_wgt_json(['ok' => false, 'error' => '未知动作'], 400);
    }
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');

/* ------------------------------------------------------------------
 * 页面数据
 * ---------------------------------------------------------------- */
$ecodWgt = ecod_widgets_admin_data();

/* 分类选项（term / terms 字段）：mid => 「名称 [ID:x][共N篇]」，照 Zibll zib_widget_option() 的标注习惯 */
$ecodWgtTerms = new stdClass();
try {
    \Widget\Metas\Category\Rows::alloc()->to($ecodCatsStack);
    while ($ecodCatsStack->next()) {
        $ecodWgtTerms->{(string) $ecodCatsStack->mid} = sprintf(
            '%s [ID:%d][共%d篇]',
            (string) $ecodCatsStack->name,
            (int) $ecodCatsStack->mid,
            (int) $ecodCatsStack->count
        );
    }
} catch (\Throwable $e) {
    /* 分类读取失败时下拉为空，不影响管理台其它功能 */
}

/* 图标：按 defs 里出现过的名字去重生成 */
$ecodIcons = [];
foreach ($ecodWgt['defs'] as $ecodDef) {
    $ecodName = (string) $ecodDef['icon'];
    if (!isset($ecodIcons[$ecodName])) {
        $ecodIcons[$ecodName] = ecod_icon($ecodName, 16);
    }
}

$ecodSite = rtrim((string) \Widget\Options::alloc()->siteUrl, '/');
$ecodBase = $ecodSite . '/usr/themes/' . basename(__DIR__);
$ecodAdminUrl = rtrim((string) \Widget\Options::alloc()->adminUrl, '/');
/* 资源地址：dist 哈希产物优先（文件名即版本，不再拼 ?v=），
   未构建时回退源文件 + filemtime 版本号（与 ecod_asset 同一解析器） */
require_once __DIR__ . '/core/lib/asset.php';
$ecodWgtAsset = static function (string $path) use ($ecodBase): string {
    $dist = ecod_dist_resolve($path);
    return $ecodBase . '/' . ($dist ?? $path);
};
$ecodCss = $ecodWgtAsset('assets/admin/widget.css');
$ecodJs = $ecodWgtAsset('assets/admin/widget.js');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>模块管理台 · <?php echo htmlspecialchars((string) \Widget\Options::alloc()->title, ENT_QUOTES, 'UTF-8'); ?></title>
<link rel="stylesheet" href="<?php echo htmlspecialchars($ecodCss, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>

<header class="wgt-top">
    <div class="wgt-top__brand">
        <b>模块管理台</b>
        <span>按位置摆放模块，改完点「保存改动」或 Ctrl/Cmd+S</span>
    </div>
    <div class="wgt-top__act">
        <?php if ($ecodWgt['legacy']): ?>
            <span class="wgt-chip wgt-chip--warn" title="当前仍读旧的 EBlocks 配置；保存后结果写入新的 EWidgets">旧配置 · 未迁移</span>
        <?php endif; ?>
        <span class="wgt-save" data-save-state data-state="idle">已保存</span>
        <button type="button" class="wgt-btn wgt-btn--primary" data-save-btn disabled>已保存</button>
        <button type="button" class="wgt-btn wgt-btn--ghost" data-export-btn>导出</button>
        <button type="button" class="wgt-btn wgt-btn--ghost" data-import-btn>导入</button>
        <input type="file" accept="application/json,.json" data-import-file hidden>
        <a class="wgt-btn wgt-btn--ghost" href="<?php echo htmlspecialchars($ecodAdminUrl . '/options-theme.php', ENT_QUOTES, 'UTF-8'); ?>">外观设置</a>
        <a class="wgt-btn wgt-btn--ghost" href="<?php echo htmlspecialchars($ecodSite, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">查看站点</a>
    </div>
</header>

<div class="wgt-app" data-app>

    <!-- 左：可用模块（照 WordPress 的 Available Widgets） -->
    <aside class="wgt-col wgt-col--lib">
        <div class="wgt-col__hd">
            <span>可用模块</span>
        </div>
        <input type="search" class="wgt-find" placeholder="搜索模块…" data-find aria-label="搜索模块">
        <div class="wgt-lib" data-lib></div>
    </aside>

    <!-- 右：区域盒子（照 WordPress 的侧栏盒子，拖模块进来） -->
    <section class="wgt-col wgt-col--areas">
        <div class="wgt-col__hd">
            <span>区域位置</span>
            <small class="wgt-col__tip">拖模块进来；点区域标题可折叠</small>
            <span class="wgt-col__act">
                <button type="button" class="wgt-mini" data-fold-all="0">全部展开</button>
                <button type="button" class="wgt-mini" data-fold-all="1">全部折叠</button>
            </span>
        </div>

        <!-- 侧栏显示（按页面）：不占区域，固定折叠卡 -->
        <details class="wgt-asidecard" data-aside-card>
            <summary>侧栏显示（按页面）<small>右侧栏 / 左侧栏 / 无侧栏</small></summary>
            <div data-aside-body></div>
        </details>

        <div class="wgt-areas" data-areas></div>
    </section>

</div>

<!-- 提示条 -->
<div class="wgt-toast" data-toast hidden></div>

<script>
window.ECOD_WGT = <?php echo json_encode([
    'defs'      => $ecodWgt['defs'],
    'areas'     => $ecodWgt['areas'],
    'instances' => $ecodWgt['instances'],
    'pages'     => $ecodWgt['pages'],
    'slots'     => $ecodWgt['slots'],
    'globals'   => $ecodWgt['globals'],
    'aside'     => $ecodWgt['aside'],
    'terms'     => $ecodWgtTerms,
    'icons'     => $ecodIcons,
    'limit'     => $ecodWgt['limit'],
    'rev'       => $ecodWgt['rev'],
    'legacy'    => $ecodWgt['legacy'],
    'token'     => ecod_wgt_token(),
    'ajax'      => $ecodBase . '/widget.php',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
</script>
<script src="<?php echo htmlspecialchars($ecodJs, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
</body>
</html>
