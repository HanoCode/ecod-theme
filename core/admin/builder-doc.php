<?php
/**
 * Ecod Theme · 页面构建器 · 布局文档（v6）
 *
 * v5 的「板块 → 主体/侧栏二分 → 栏内线性堆叠」升级为
 * 「板块 → 行（row）→ 列（cell）→ 模块实例」的行列模型（docs/builder-v6-design.md）：
 * 行内任意 1–6 列、宽度 = 整数权重（fr）或固定 px（wpx）、行/列级断点覆盖
 * （ov 白名单 keep/gap/hide/w/order）。模块仍是叶子，v4 数据继续作为兜底出口。
 *
 * v5 文档读时规范化（ecod_bld_norm_*）为 v6，渲染视觉零变化；保存一律写 v6。
 *
 * 数据分两层存（不变）：
 *   - 页面类型默认布局（home/page/…）→ options 表 user=0 独立行 ELayouts；
 *   - cat:<mid> / cid:<cid> 覆盖 → 表 ecod_page_layout（schema v15）。
 *
 * 渲染回退链不变：cid:<cid> → cat:<mid> → 页面类型默认 → v4 旧区域。
 * 任一级命中即整页接管；一级都没有时行为与升级前完全一致。
 *
 * 响应式：单数据源 + 容器查询 + ov 断点覆盖白名单（板块/行/列三级，
 * 服务端清洗强制丢弃白名单外的键，见 docs/builder-v6-design.md §4.4）。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @since   2026-10-31
 * @version 2.0.0
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/** 布局文档存储：options 表 user=0 的独立 JSON 行（页面类型默认布局） */
define('ECOD_LAYOUTS_OPTION', 'ELayouts');

/** 布局文档结构版本（5 = 主体/侧栏二分；6 = 行列模型） */
define('ECOD_LAYOUT_SCHEMA', 6);

/** 单文档板块数上限 / 单列模块数上限（沿用 ECOD_WIDGET_LIMIT 语义） */
define('ECOD_LAYOUT_SECTIONS_MAX', 30);

/** 行列上限（docs/builder-v6-design.md §4.5） */
define('ECOD_LAYOUT_ROWS_MAX', 12);
define('ECOD_LAYOUT_CELLS_MAX', 6);

/** 固定像素列宽钳制范围（整数权重列见 ecod_bld_clean_cell） */
define('ECOD_LAYOUT_WPX_MIN', 160);
define('ECOD_LAYOUT_WPX_MAX', 640);

/** ov 断点覆盖白名单（硬约束，清洗时丢弃白名单外的键） */
define('ECOD_LAYOUT_SEC_OV_KEYS', ['padY', 'hide', 'tone']);
define('ECOD_LAYOUT_ROW_OV_KEYS', ['keep', 'gap', 'hide']);
define('ECOD_LAYOUT_CELL_OV_KEYS', ['hide', 'w', 'order']);
define('ECOD_LAYOUT_OV_DEVICES', ['md', 'sm']);

/* ==================================================================
 * 一、清洗（构建器文档专用；模块参数复用 widget.php 的 ecod_wgt_clean_* 口径）
 * ================================================================ */

require_once __DIR__ . '/widget-clean.php';

/**
 * 图片地址清洗（与 widget.php 的 image 字段同一白名单）
 */
function ecod_bld_clean_url($value): string
{
    $url = trim(str_replace(["\r", "\n", "\t"], '', (string) $value));
    if ($url !== '' && !preg_match('#^(https?://|/|\./|data:image/)#i', $url)) {
        return '';
    }
    return mb_substr($url, 0, 500);
}

/* ------------------------------------------------------------------
 * 规范化：v5（主体/侧栏二分）→ v6（行列模型），幂等
 *
 * 挂在所有读路径与清洗入口（docs/builder-v6-design.md §4.7）：
 * v5 映射 stack → 单行单列；left/right → 单行两列（侧栏列 = 固定 px wpx），
 * 度量与 v5 渲染逐项一致（gap 24 / 列内 20 / sticky top 76），视觉零变化。
 * ---------------------------------------------------------------- */

/**
 * 新建一行（编辑器与规范化共用的默认值）
 */
/* 行/列是 v6 新增层：v5 文档经 norm 补齐 on=1（可见）；显式 0 才隐藏 */
function ecod_bld_norm_row(?array $row, array $cells): array
{
    $row = is_array($row) ? $row : [];
    return [
        'uid'    => (string) ($row['uid'] ?? ''),
        /* 行是 v6 新增层：v5 文档没有 on 键，默认可见；显式 0 才隐藏 */
        'on'     => array_key_exists('on', $row) ? (!empty($row['on']) ? 1 : 0) : 1,
        'gap'    => (int) ($row['gap'] ?? 24),
        'valign' => (string) ($row['valign'] ?? 'start'),
        'masonry' => !empty($row['masonry']) ? 1 : 0,
        'ov'     => is_array($row['ov'] ?? null) ? $row['ov'] : [],
        'cells'  => $cells,
    ];
}

/**
 * 嵌套行规范化（深度 2 的内层）：形状与顶层行一致，但内层列剥掉再嵌套的 rows
 * （超深整枝在 clean 兜底；norm 先收敛形状保证幂等）
 */
function ecod_bld_norm_nested_rows(array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $cells = [];
        foreach ((array) ($row['cells'] ?? []) as $cell) {
            if (!is_array($cell)) {
                continue;
            }
            $cell['rows'] = []; /* 深度 2：内层列不再嵌套 */
            $cells[] = ecod_bld_norm_cell($cell, is_array($cell['mods'] ?? null) ? $cell['mods'] : []);
        }
        if ($cells === []) {
            continue;
        }
        $out[] = ecod_bld_norm_row($row, $cells);
    }
    return $out;
}

/**
 * 新建一列（w = 整数权重 / wpx = 固定像素，二选一，wpx 优先；
 * 卡面字段 bg/bgDark/bgc/radius/pad 原样携带，保持 norm 幂等；
 * rows = 列内嵌套行（深度 2），与 mods 互斥——双有时 mods 并入首个嵌套列
 * （与 v5 stack 把 aside 并进 main 同一原则：读路径不静默丢模块））
 */
function ecod_bld_norm_cell(?array $cell, array $mods): array
{
    $cell = is_array($cell) ? $cell : [];
    $out = [
        'uid'    => (string) ($cell['uid'] ?? ''),
        'sticky' => !empty($cell['sticky']) ? 1 : 0,
        'ov'     => is_array($cell['ov'] ?? null) ? $cell['ov'] : [],
        'mods'   => $mods,
        'bg'     => (string) ($cell['bg'] ?? ''),
        'bgDark' => (string) ($cell['bgDark'] ?? ''),
        'bgc'    => (string) ($cell['bgc'] ?? ''),
        'radius' => (int) ($cell['radius'] ?? 0),
        'pad'    => (int) ($cell['pad'] ?? 0),
    ];
    if (!empty($cell['wpx'])) {
        $out['wpx'] = (int) $cell['wpx'];
    } else {
        $out['w'] = (int) ($cell['w'] ?? 1);
    }

    if (!empty($cell['rows']) && is_array($cell['rows'])) {
        $nested = ecod_bld_norm_nested_rows($cell['rows']);
        if ($nested !== []) {
            if ($mods !== []) {
                $nested[0]['cells'][0]['mods'] = array_merge($nested[0]['cells'][0]['mods'], $mods);
            }
            $out['rows'] = $nested;
            $out['mods'] = [];
        }
    }
    return $out;
}

/**
 * 单板块规范化：v5 形状（cols/main/aside）展开成 v6 rows；v6 幂等兜底
 */
function ecod_bld_norm_section(array $sec): array
{
    if (isset($sec['rows']) && is_array($sec['rows'])) {
        /* v6：兜底空行 / 缺 mods 的列 */
        $rows = [];
        foreach ($sec['rows'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $cells = [];
            foreach ((array) ($row['cells'] ?? []) as $cell) {
                if (!is_array($cell)) {
                    continue;
                }
                $cells[] = ecod_bld_norm_cell($cell, is_array($cell['mods'] ?? null) ? $cell['mods'] : []);
            }
            if ($cells === []) {
                continue;
            }
            $rows[] = ecod_bld_norm_row($row, $cells);
        }
        if ($rows === []) {
            $rows[] = ecod_bld_norm_row(null, [ecod_bld_norm_cell(null, [])]);
        }
        $sec['rows'] = $rows;
        unset($sec['main'], $sec['aside'], $sec['cols'], $sec['asideW'], $sec['asideSticky']);
        return $sec;
    }

    /* v5 → v6：cols 三态映射为单行结构 */
    $main = is_array($sec['main'] ?? null) ? $sec['main'] : [];
    $aside = is_array($sec['aside'] ?? null) ? $sec['aside'] : [];
    $asideW = min(ECOD_LAYOUT_WPX_MAX, max(ECOD_LAYOUT_WPX_MIN, (int) ($sec['asideW'] ?? 320)));
    $sticky = !empty($sec['asideSticky']) ? 1 : 0;
    $cols = (string) ($sec['cols'] ?? 'stack');

    $mainCell = ecod_bld_norm_cell(null, $main);
    $mainCell['uid'] = '';
    $asideCell = ecod_bld_norm_cell(null, $aside);
    $asideCell['uid'] = '';
    $asideCell['wpx'] = $asideW;
    $asideCell['sticky'] = $sticky;
    unset($asideCell['w']); /* wpx 与 w 二选一：norm_cell 默认填的 w 要删掉，保证二次 norm 幂等 */

    if ($aside === []) {
        $cells = [$mainCell];
    } elseif ($cols === 'stack') {
        /* v5 stack 下 aside 模块虽不渲染但仍留在文档里：norm 时并入主体列，
           避免读路径静默丢模块（切回 right 想找回侧栏内容的能力换成不丢数据） */
        $mainCell['mods'] = array_merge($main, $aside);
        $cells = [$mainCell];
    } elseif ($cols === 'left') {
        $cells = [$asideCell, $mainCell];
    } else {
        $cells = [$mainCell, $asideCell];
    }
    $sec['rows'] = [ecod_bld_norm_row(null, $cells)];
    unset($sec['main'], $sec['aside'], $sec['cols'], $sec['asideW'], $sec['asideSticky']);
    return $sec;
}

/**
 * 整份文档规范化（幂等；sections 缺失时空数组）
 */
function ecod_bld_norm_doc(array $doc): array
{
    $sections = [];
    foreach ((array) ($doc['sections'] ?? []) as $i => $sec) {
        if (!is_array($sec)) {
            continue;
        }
        $sections[] = ecod_bld_norm_section($sec);
        if (count($sections) >= ECOD_LAYOUT_SECTIONS_MAX) {
            break;
        }
    }
    $doc['sections'] = $sections;
    return $doc;
}

/* ------------------------------------------------------------------
 * 清洗（白名单收口；入口先过规范化，v5 输入同样收敛为 v6）
 * ---------------------------------------------------------------- */

/**
 * 清洗一行
 *
 * $index 是「区-行」定位串（如 0r0）：调用方传 section 行号拼出来的字符串，
 * 只用作 uid 生成种子，不能收 int（PHP 8 非数字串传 int 直接 TypeError）。
 * $depth 是行层级（1 = 板块顶层行，2 = 列内嵌套行），其列继承同一深度：
 * 只有 depth=1 的列才允许再带 rows（嵌套深度硬上限 2，docs §4.5/§7.1）。
 */
function ecod_bld_clean_row(array $row, int|string $index, int $depth = 1): array
{
    $uid = (string) ($row['uid'] ?? '');
    if (!preg_match('/^row-[a-z0-9-]{3,20}$/', $uid)) {
        /* 确定性生成：同一文档每次读取（doc.get 与画布渲染是两次请求）必须
           得到同一 uid，否则编辑器 Store 与画布 DOM 的 data-buid 失配，
           拖放/选中会静默失败（microtime 方案会让每次读取都换 uid） */
        $uid = 'row-' . substr(md5($uid . '|' . $index), 0, 8);
    }

    $gap = (int) ($row['gap'] ?? 24);
    if ($gap < 0 || $gap > 96) {
        $gap = 24;
    }
    $valign = (string) ($row['valign'] ?? 'start');
    if (!in_array($valign, ['start', 'center', 'stretch'], true)) {
        $valign = 'start';
    }

    $out = [
        'uid'    => $uid,
        'on'     => !empty($row['on']) ? 1 : 0,
        'gap'    => $gap,
        'valign' => $valign,
        'masonry' => !empty($row['masonry']) ? 1 : 0,
        'ov'     => [],
        'cells'  => [],
    ];

    /* ov 白名单：md/sm × keep/gap/hide */
    $ov = is_array($row['ov'] ?? null) ? $row['ov'] : [];
    foreach (ECOD_LAYOUT_OV_DEVICES as $dev) {
        $devIn = is_array($ov[$dev] ?? null) ? $ov[$dev] : [];
        $devOut = [];
        if (array_key_exists('keep', $devIn)) {
            $devOut['keep'] = !empty($devIn['keep']) ? 1 : 0;
        }
        if (array_key_exists('gap', $devIn)) {
            $g = (int) $devIn['gap'];
            if ($g >= 0 && $g <= 96) {
                $devOut['gap'] = $g;
            }
        }
        if (array_key_exists('hide', $devIn)) {
            $devOut['hide'] = !empty($devIn['hide']) ? 1 : 0;
        }
        if ($devOut !== []) {
            $out['ov'][$dev] = $devOut;
        }
    }

    foreach (array_slice((array) ($row['cells'] ?? []), 0, ECOD_LAYOUT_CELLS_MAX) as $j => $cell) {
        if (!is_array($cell)) {
            continue;
        }
        $out['cells'][] = ecod_bld_clean_cell($cell, $index . '-' . $j, $depth);
    }

    return $out;
}

/**
 * 清洗一列（权重 fr / 固定 px 二选一；断点覆盖 hide/w/order；卡面 bg/bgDark/bgc/radius/pad）
 *
 * $depth = 所在行层级：1 = 顶层列（允许 rows 嵌套行），2 = 嵌套列（rows 一律
 * 丢弃——嵌套深度硬上限 2，docs/builder-v6-design.md §4.5/§7.1）。 */
function ecod_bld_clean_cell(array $cell, string $path, int $depth = 1): array
{
    $uid = (string) ($cell['uid'] ?? '');
    if (!preg_match('/^cell-[a-z0-9-]{3,20}$/', $uid)) {
        /* 确定性生成（同 ecod_bld_clean_row 的说明） */
        $uid = 'cell-' . substr(md5($uid . '|' . $path), 0, 8);
    }

    $bgc = (string) ($cell['bgc'] ?? '');
    if ($bgc !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $bgc)) {
        $bgc = '';
    }

    $out = [
        'uid'    => $uid,
        'sticky' => !empty($cell['sticky']) ? 1 : 0,
        'ov'     => [],
        'mods'   => ecod_bld_clean_rows((array) ($cell['mods'] ?? [])),
        'bg'     => ecod_bld_clean_url($cell['bg'] ?? ''),
        'bgDark' => ecod_bld_clean_url($cell['bgDark'] ?? ''),
        'bgc'    => $bgc,
        'radius' => min(40, max(0, (int) ($cell['radius'] ?? 0))),
        'pad'    => min(120, max(0, (int) ($cell['pad'] ?? 0))),
    ];
    if (!empty($cell['wpx'])) {
        $out['wpx'] = min(ECOD_LAYOUT_WPX_MAX, max(ECOD_LAYOUT_WPX_MIN, (int) $cell['wpx']));
    } else {
        $out['w'] = min(12, max(1, (int) ($cell['w'] ?? 1)));
    }

    /* 列内嵌套行（深度 2）：仅顶层列清洗；嵌套行复用同一清洗器，
       其列 depth=2，再深的 rows 直接整枝丢弃 */
    if ($depth === 1 && !empty($cell['rows']) && is_array($cell['rows'])) {
        $nested = [];
        foreach (array_slice($cell['rows'], 0, ECOD_LAYOUT_ROWS_MAX) as $k => $nrow) {
            if (!is_array($nrow)) {
                continue;
            }
            $one = ecod_bld_clean_row($nrow, $path . 'r' . $k, 2);
            if ($one['cells'] !== []) {
                $nested[] = $one;
            }
        }
        if ($nested !== []) {
            $out['rows'] = $nested;
        }
    }

    /* ov 白名单：md/sm × hide/w/order */
    $ov = is_array($cell['ov'] ?? null) ? $cell['ov'] : [];
    foreach (ECOD_LAYOUT_OV_DEVICES as $dev) {
        $devIn = is_array($ov[$dev] ?? null) ? $ov[$dev] : [];
        $devOut = [];
        if (array_key_exists('hide', $devIn)) {
            $devOut['hide'] = !empty($devIn['hide']) ? 1 : 0;
        }
        if (array_key_exists('w', $devIn)) {
            $w = (int) $devIn['w'];
            if ($w >= 1 && $w <= 12) {
                $devOut['w'] = $w;
            }
        }
        if (array_key_exists('order', $devIn)) {
            $o = (int) $devIn['order'];
            if ($o >= -6 && $o <= 6) {
                $devOut['order'] = $o;
            }
        }
        if ($devOut !== []) {
            $out['ov'][$dev] = $devOut;
        }
    }

    return $out;
}

/**
 * 清洗一个板块（白名单收口，脏数据一律丢弃或回落默认）
 */
function ecod_bld_clean_section(array $sec, int $index): array
{
    /* 入口先规范化：v5 输入（cols/main/aside）展开成 v6 rows */
    $sec = ecod_bld_norm_section($sec);

    $uid = (string) ($sec['uid'] ?? '');
    if (!preg_match('/^sec-[a-z0-9-]{3,20}$/', $uid)) {
        /* 确定性生成（同 ecod_bld_clean_row 的说明） */
        $uid = 'sec-' . substr(md5($uid . '|' . $index), 0, 8);
    }

    $tone = (string) ($sec['tone'] ?? 'auto');
    if (!in_array($tone, ['auto', 'light', 'dark'], true)) {
        $tone = 'auto';
    }

    /* 锚点 ID：只留字母数字下划线连字符，字母开头，供菜单/按钮 #锚点 跳转 */
    $anchor = preg_replace('/[^A-Za-z0-9_-]/', '', trim((string) ($sec['anchor'] ?? '')));
    if ($anchor !== '' && !preg_match('/^[A-Za-z]/', $anchor)) {
        $anchor = 'a' . $anchor;
    }
    $anchor = mb_substr($anchor, 0, 60);

    $out = [
        'uid'       => $uid,
        'on'        => !empty($sec['on']) ? 1 : 0,
        'label'     => mb_substr(trim((string) ($sec['label'] ?? '')), 0, 40),
        'anchor'    => $anchor,
        'width'     => (string) ($sec['width'] ?? 'full') === 'boxed' ? 'boxed' : 'full',
        'contentMax'=> min(1920, max(0, (int) ($sec['contentMax'] ?? 0))),
        'padY'      => min(200, max(0, (int) ($sec['padY'] ?? 48))),
        'bg'        => ecod_bld_clean_url($sec['bg'] ?? ''),
        'bgDark'    => ecod_bld_clean_url($sec['bgDark'] ?? ''),
        'radius'    => min(40, max(0, (int) ($sec['radius'] ?? 0))),
        'tone'      => $tone,
        'ov'        => [],
        'rows'      => [],
    ];

    /* ov 白名单：设备 md/sm × 键 padY/hide/tone，白名单外的键直接丢弃 */
    $ov = is_array($sec['ov'] ?? null) ? $sec['ov'] : [];
    foreach (ECOD_LAYOUT_OV_DEVICES as $dev) {
        $devIn = is_array($ov[$dev] ?? null) ? $ov[$dev] : [];
        $devOut = [];
        if (array_key_exists('padY', $devIn)) {
            $devOut['padY'] = min(200, max(0, (int) $devIn['padY']));
        }
        if (array_key_exists('hide', $devIn)) {
            $devOut['hide'] = !empty($devIn['hide']) ? 1 : 0;
        }
        if (array_key_exists('tone', $devIn)) {
            $t = (string) $devIn['tone'];
            if (in_array($t, ['auto', 'light', 'dark'], true)) {
                $devOut['tone'] = $t;
            }
        }
        if ($devOut !== []) {
            $out['ov'][$dev] = $devOut;
        }
    }

    foreach (array_slice((array) ($sec['rows'] ?? []), 0, ECOD_LAYOUT_ROWS_MAX) as $i => $row) {
        if (!is_array($row)) {
            continue;
        }
        $cleaned = ecod_bld_clean_row($row, $index . 'r' . $i);
        if ($cleaned['cells'] !== []) {
            $out['rows'][] = $cleaned;
        }
    }
    if ($out['rows'] === []) {
        $out['rows'][] = ecod_bld_clean_row([], $index . 'r0');
    }

    return $out;
}

/**
 * 清洗一栏模块实例（与 ecod_wgt_clean_rows 同口径，多了断点隐藏 hide）
 *
 * @return array<int,array>
 */
function ecod_bld_clean_rows(array $rows): array
{
    $defs = ecod_block_defs();
    $out = [];
    $seen = [];
    $n = 0;

    foreach ($rows as $row) {
        $n++;
        if (!is_array($row)) {
            continue;
        }
        $type = (string) ($row['type'] ?? '');
        if ($type === '' || !isset($defs[$type])) {
            continue;
        }

        $uid = (string) ($row['uid'] ?? '');
        if (!preg_match('/^[a-z0-9-]{1,40}$/', $uid) || isset($seen[$uid])) {
            /* 确定性生成：同一文档每次读取必须得到同一 uid（见 ecod_bld_clean_row）；
               盐里带位置序号，重复 uid 的两个实例会得到不同的新 uid */
            $uid = $type . '-' . substr(md5($uid . '|' . $type . '|' . $n), 0, 6);
        }
        if (isset($seen[$uid])) {
            continue;
        }
        $seen[$uid] = true;

        /* 断点隐藏：只认 md / sm 两个键 */
        $hide = [];
        $hideIn = is_array($row['hide'] ?? null) ? $row['hide'] : [];
        foreach (ECOD_LAYOUT_OV_DEVICES as $dev) {
            if (array_key_exists($dev, $hideIn)) {
                $hide[$dev] = !empty($hideIn[$dev]) ? 1 : 0;
            }
        }

        $one = [
            'uid'    => $uid,
            'type'   => $type,
            'on'     => !empty($row['on']) ? 1 : 0,
            'params' => ecod_wgt_clean_params($defs[$type], is_array($row['params'] ?? null) ? $row['params'] : []),
        ];
        if ($hide !== []) {
            $one['hide'] = $hide;
        }
        $out[] = $one;

        if (count($out) >= ECOD_WIDGET_LIMIT) {
            break;
        }
    }

    return $out;
}

/**
 * 清洗整份文档（保留 sections 与文档级 lock，长度截断）
 */
function ecod_bld_clean_doc(array $doc): array
{
    $sections = [];
    foreach ((array) ($doc['sections'] ?? []) as $i => $sec) {
        if (!is_array($sec)) {
            continue;
        }
        $sections[] = ecod_bld_clean_section($sec, $i);
        if (count($sections) >= ECOD_LAYOUT_SECTIONS_MAX) {
            break;
        }
    }
    return ['sections' => $sections, 'lock' => !empty($doc['lock']) ? 1 : 0];
}

/* ==================================================================
 * 二、存储（ELayouts 行 + ecod_page_layout 表）
 * ================================================================ */

/**
 * 读取页面类型默认布局（ELayouts 行）
 */
function ecod_layouts_read(): array
{
    if (isset($GLOBALS['ecod_layouts_doc']) && is_array($GLOBALS['ecod_layouts_doc'])) {
        return $GLOBALS['ecod_layouts_doc'];
    }

    $data = ecod_cache_get(ECOD_LAYOUTS_OPTION, null);
    $doc = ['v' => ECOD_LAYOUT_SCHEMA, 'docs' => []];
    if (is_array($data) && isset($data['docs']) && is_array($data['docs'])) {
        foreach ($data['docs'] as $key => $one) {
            if (!is_array($one)) {
                continue;
            }
            if (preg_match('/^(cat|cid):\d+$/', (string) $key) || isset(ecod_widget_pages()[(string) $key])) {
                $doc['docs'][(string) $key] = ecod_bld_clean_doc($one);
            }
        }
    }

    $GLOBALS['ecod_layouts_doc'] = $doc;
    return $doc;
}

/**
 * 写入页面类型默认布局
 */
function ecod_layouts_write(array $doc): void
{
    $clean = ['v' => ECOD_LAYOUT_SCHEMA, 'docs' => []];
    foreach (($doc['docs'] ?? []) as $key => $one) {
        if (!is_array($one)) {
            continue;
        }
        if (preg_match('/^(cat|cid):\d+$/', (string) $key) || isset(ecod_widget_pages()[(string) $key])) {
            $clean['docs'][(string) $key] = ecod_bld_clean_doc($one);
        }
    }

    ecod_cache_set(ECOD_LAYOUTS_OPTION, $clean);
    unset($GLOBALS['ecod_layouts_doc'], $GLOBALS['ecod_layout_active']);
}

/**
 * 覆盖文档读取（表）：kind = cat|cid
 */
function ecod_layout_override_get(string $kind, int $ref): ?array
{
    try {
        $row = \Typecho\Db::get()->fetchRow(
            \Typecho\Db::get()->select('doc')->from('table.ecod_page_layout')
                ->where('kind = ?', $kind)->where('ref = ?', $ref)->limit(1)
        );
    } catch (\Throwable $e) {
        return null;
    }
    if (!$row || (string) $row['doc'] === '') {
        return null;
    }
    $data = json_decode((string) $row['doc'], true);
    if (!is_array($data) || empty($data['sections'])) {
        return null;
    }
    return ecod_bld_clean_doc($data);
}

/**
 * 覆盖文档写入（表，upsert）
 */
function ecod_layout_override_set(string $kind, int $ref, array $doc): bool
{
    if (!in_array($kind, ['cat', 'cid'], true) || $ref <= 0) {
        return false;
    }
    $clean = ecod_bld_clean_doc($doc);
    if (empty($clean['sections'])) {
        return ecod_layout_override_delete($kind, $ref);
    }

    try {
        $db = \Typecho\Db::get();
        $exists = $db->fetchRow(
            $db->select('id')->from('table.ecod_page_layout')
                ->where('kind = ?', $kind)->where('ref = ?', $ref)->limit(1)
        );
        $json = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($exists) {
            $db->query($db->update('table.ecod_page_layout')
                ->rows(['doc' => $json, 'updated' => time()])
                ->where('id = ?', $exists['id']));
        } else {
            $db->query($db->insert('table.ecod_page_layout')
                ->rows(['kind' => $kind, 'ref' => $ref, 'doc' => $json, 'updated' => time()]));
        }
    } catch (\Throwable $e) {
        return false;
    }

    unset($GLOBALS['ecod_layout_active']);
    return true;
}

/**
 * 覆盖文档删除
 */
function ecod_layout_override_delete(string $kind, int $ref): bool
{
    try {
        \Typecho\Db::get()->query(\Typecho\Db::get()->delete('table.ecod_page_layout')
            ->where('kind = ?', $kind)->where('ref = ?', $ref));
    } catch (\Throwable $e) {
        return false;
    }
    unset($GLOBALS['ecod_layout_active']);
    return true;
}

/**
 * 全部存储的指纹（ELayouts + 覆盖表），编辑器乐观锁用
 */
function ecod_layouts_rev(): string
{
    $doc = ecod_layouts_read();
    $overrides = [];
    try {
        $rows = \Typecho\Db::get()->fetchAll(
            \Typecho\Db::get()->select('kind', 'ref', 'doc')->from('table.ecod_page_layout')
        );
        foreach ($rows as $row) {
            $overrides[$row['kind'] . ':' . $row['ref']] = (string) $row['doc'];
        }
    } catch (\Throwable $e) {
    }
    return substr(md5(json_encode([$doc, $overrides], JSON_UNESCAPED_UNICODE)), 0, 12);
}

/**
 * 按文档 key 取 sections（覆盖表优先语义由回退链负责，这里只做单点查询）
 */
function ecod_layout_doc_for_key(string $key): ?array
{
    if (preg_match('/^(cat|cid):(\d+)$/', $key, $m)) {
        $one = ecod_layout_override_get($m[1], (int) $m[2]);
        return $one && !empty($one['sections']) ? $one : null;
    }
    if (isset(ecod_widget_pages()[$key])) {
        $docs = ecod_layouts_read();
        $one = $docs['docs'][$key] ?? null;
        return $one && !empty($one['sections']) ? $one : null;
    }
    return null;
}

/**
 * 保存文档（按 key 自动路由：覆盖 → 表；页面类型 → ELayouts）
 */
function ecod_layout_save_doc(string $key, array $doc): bool
{
    if (preg_match('/^(cat|cid):(\d+)$/', $key, $m)) {
        return ecod_layout_override_set($m[1], (int) $m[2], $doc);
    }
    if (isset(ecod_widget_pages()[$key])) {
        $all = ecod_layouts_read();
        $all['docs'][$key] = ecod_bld_clean_doc($doc);
        ecod_layouts_write($all);
        return true;
    }
    return false;
}

/**
 * 删除文档（回退到下一级）
 */
function ecod_layout_delete_doc(string $key): bool
{
    if (preg_match('/^(cat|cid):(\d+)$/', $key, $m)) {
        return ecod_layout_override_delete($m[1], (int) $m[2]);
    }
    if (isset(ecod_widget_pages()[$key])) {
        $all = ecod_layouts_read();
        unset($all['docs'][$key]);
        ecod_layouts_write($all);
        return true;
    }
    return false;
}

/* ==================================================================
 * 三、解析（回退链）
 * ================================================================ */

/**
 * 当前请求命中的文档 key（cid:<cid> → cat:<mid> → 页面类型）
 *
 * @return string|null 未命中返回 null（走 v4 旧区域）
 */
function ecod_layout_resolve_key($widget = null): ?string
{
    $page = ecod_page_type($widget);
    $candidates = [];

    if (is_object($widget)) {
        try {
            if ($page === 'page' || $page === 'custom') {
                $cid = (int) ($widget->cid ?? 0);
                if ($cid > 0) {
                    $candidates[] = 'cid:' . $cid;
                }
            } elseif ($page === 'cat') {
                $slug = '';
                try {
                    $slug = (string) $widget->getArchiveSlug();
                } catch (\Throwable $e) {
                }
                if ($slug !== '' && function_exists('ecod_cat_meta')) {
                    $cat = ecod_cat_meta($slug);
                    if ($cat && (int) $cat['mid'] > 0) {
                        $candidates[] = 'cat:' . (int) $cat['mid'];
                    }
                }
            }
        } catch (\Throwable $e) {
        }
    }

    $candidates[] = $page;

    foreach ($candidates as $key) {
        if (ecod_layout_doc_for_key($key) !== null) {
            return $key;
        }
    }
    return null;
}

/**
 * 当前请求生效的布局文档（请求内缓存）
 *
 * @return array|null {key, sections, has_aside}；未启用 v5 时 null
 */
function ecod_layout_active_doc($widget = null): ?array
{
    if (isset($GLOBALS['ecod_layout_active'])) {
        return $GLOBALS['ecod_layout_active'];
    }
    if (!function_exists('ecod_page_type') || !function_exists('ecod_block_defs')) {
        return null;
    }

    $key = ecod_layout_resolve_key($widget);

    /* 画布模式：blddoc 明确指定正在编辑的文档，且暂存草稿优先于生产 */
    if ($key !== null && function_exists('ecod_builder_mode') && ecod_builder_mode()) {
        $stageKey = (string) ($_GET['blddoc'] ?? '');
        if (preg_match('/^(cat|cid):\d+$/', $stageKey) || isset(ecod_widget_pages()[$stageKey])) {
            $key = $stageKey;
        }
        $staged = ecod_layout_stage_get($key);
        if ($staged !== null) {
            return $GLOBALS['ecod_layout_active'] = [
                'key' => $key, 'sections' => $staged['sections'],
                'has_aside' => ecod_layout_doc_has_aside($staged['sections']),
            ];
        }
    }

    if ($key === null) {
        return $GLOBALS['ecod_layout_active'] = null;
    }

    $doc = ecod_layout_doc_for_key($key);
    if ($doc === null) {
        return $GLOBALS['ecod_layout_active'] = null;
    }

    return $GLOBALS['ecod_layout_active'] = [
        'key'      => $key,
        'sections' => $doc['sections'],
        'has_aside'=> ecod_layout_doc_has_aside($doc['sections']),
    ];
}

/**
 * 布局文档是否自带分栏（v6：任一行多于一列）
 *
 * 有分栏时全局 v4 侧栏让位（ecod_aside_pos 返回 none）——
 * 与 v5 的「cols !== stack」判定同语义。
 */
function ecod_layout_doc_has_aside(array $sections): bool
{
    foreach ($sections as $sec) {
        foreach ((array) ($sec['rows'] ?? []) as $row) {
            if (count((array) ($row['cells'] ?? [])) > 1) {
                return true;
            }
        }
    }
    return false;
}

/* ==================================================================
 * 四、画布模式（?ecod_builder=1&token=…，仅管理员可用）
 * ================================================================ */

/**
 * 当前是否画布模式（iframe 内的真实页面加载）
 *
 * 编辑器（builder.php）与前台同源，画布直接由编辑器侧接管交互
 * （点击选中 / 拖放 / 键盘），页面本身只多输出 data-buid 标记。
 */
function ecod_builder_mode(): bool
{
    static $mode = null;
    if ($mode !== null) {
        return $mode;
    }
    if (($_GET['ecod_builder'] ?? '') !== '1' || !ecod_block_admin()) {
        return $mode = false;
    }
    $token = (string) ($_GET['token'] ?? '');
    try {
        $expect = (string) \Widget\Security::alloc()->getToken('ecod-widgets');
    } catch (\Throwable $e) {
        return $mode = false;
    }
    return $mode = ($expect !== '' && $token !== '' && hash_equals($expect, $token));
}

/* ==================================================================
 * 五、渲染
 * ================================================================ */

/**
 * 整页接管渲染入口（模板调用）
 *
 * 有生效文档：输出全部板块并返回 true（模板跳过旧内容）；
 * 没有文档：什么都不输出并返回 false（模板走 v4 原路径）。
 */
function ecod_layout_render_page($widget = null): bool
{
    $doc = ecod_layout_active_doc($widget);
    if ($doc === null || !function_exists('ecod_render_block')) {
        return false;
    }
    ecod_layout_render_sections($doc['sections'], $widget);
    return true;
}

/**
 * 输出一组板块
 */
function ecod_layout_render_sections(array $sections, $widget = null): void
{
    $builder = ecod_builder_mode();
    foreach ($sections as $sec) {
        ecod_layout_render_section($sec, $widget, $builder);
    }
}

/**
 * 输出一个板块（板块壳：宽度 / 间距 / 背景 / 明暗 → 行 → 列 → 模块）
 *
 * 间距用 CSS 变量 + 断点媒体查询承接（--sec-pad 桌面、--sec-pad-md/sm 覆盖），
 * 行列结构同样走 CSS 变量（--row-cols / --row-gap），内联样式里只放值，
 * 断点折叠在 §42 的样式表里完成。
 */
function ecod_layout_render_section(array $sec, $widget = null, bool $builder = false): void
{
    if (empty($sec['on']) && !$builder) {
        return;
    }

    $uid = (string) ($sec['uid'] ?? '');
    $pad = (int) ($sec['padY'] ?? 48);
    $max = (int) ($sec['contentMax'] ?? 0);
    $ov = is_array($sec['ov'] ?? null) ? $sec['ov'] : [];

    $vars = ['--sec-pad:' . $pad . 'px', '--sec-max:' . ($max > 0 ? $max : 1200) . 'px'];
    foreach (['md', 'sm'] as $dev) {
        if (isset($ov[$dev]['padY'])) {
            $vars[] = '--sec-pad-' . $dev . ':' . (int) $ov[$dev]['padY'] . 'px';
        }
    }

    $class = 'ecod-sec';
    if (($sec['width'] ?? 'full') === 'boxed') {
        $class .= ' ecod-sec--boxed';
    }
    if (($sec['tone'] ?? 'auto') !== 'auto') {
        $class .= ' ecod-sec--' . $sec['tone'];
    }
    foreach (['md', 'sm'] as $dev) {
        if (!empty($ov[$dev]['hide'])) {
            $class .= ' ecod-sec--hide-' . $dev;
        }
        $devTone = $ov[$dev]['tone'] ?? '';
        if (in_array($devTone, ['light', 'dark'], true)) {
            $class .= ' ecod-sec--tone-' . $dev . '-' . $devTone;
        }
    }

    $attrs = '';
    if (($sec['anchor'] ?? '') !== '') {
        $attrs .= ' id="' . htmlspecialchars((string) $sec['anchor'], ENT_QUOTES, 'UTF-8') . '"';
    }
    if ($builder) {
        $attrs .= ' data-buid="' . htmlspecialchars($uid, ENT_QUOTES, 'UTF-8') . '" data-bkind="section"';
    }

    $bgStyle = '';
    if (($sec['bg'] ?? '') !== '') {
        $bgStyle .= '--sec-bg:url(\'' . htmlspecialchars((string) $sec['bg'], ENT_QUOTES, 'UTF-8') . '\');';
    }
    if (($sec['bgDark'] ?? '') !== '') {
        $bgStyle .= '--sec-bg-dark:url(\'' . htmlspecialchars((string) $sec['bgDark'], ENT_QUOTES, 'UTF-8') . '\');';
    }
    $radius = (int) ($sec['radius'] ?? 0);
    if ($radius > 0) {
        $vars[] = '--sec-radius:' . $radius . 'px';
    }

    echo '<section class="' . $class . '"' . $attrs
        . ' style="' . implode(';', $vars) . ($bgStyle !== '' ? ';' . $bgStyle : '') . '">';
    if ($builder) {
        echo '<span class="ecod-b-seclabel">' . htmlspecialchars((string) ($sec['label'] ?? ''), ENT_QUOTES, 'UTF-8')
            . ' · ' . ($sec['width'] === 'boxed' ? '盒式' : '全宽') . '</span>';
    }
    echo '<div class="ecod-sec__inner">';

    foreach ((array) ($sec['rows'] ?? []) as $row) {
        ecod_layout_render_row((array) $row, $widget, $builder);
    }

    echo '</div></section>';
}

/**
 * 输出一行（N 列网格：--row-cols 由列序列拼出，断点折叠由类名承接）
 */
function ecod_layout_render_row(array $row, $widget = null, bool $builder = false): void
{
    if (empty($row['on']) && !$builder) {
        return;
    }

    $cells = (array) ($row['cells'] ?? []);
    if ($cells === []) {
        return;
    }

    $ov = is_array($row['ov'] ?? null) ? $row['ov'] : [];
    $vars = [
        '--row-gap:' . (int) ($row['gap'] ?? 24) . 'px',
        '--row-cols:' . ecod_layout_row_cols($cells),
        '--row-valign:' . (in_array($row['valign'] ?? '', ['start', 'center', 'stretch'], true) ? $row['valign'] : 'start'),
    ];
    if (isset($ov['md']['gap'])) {
        $vars[] = '--row-gap-md:' . (int) $ov['md']['gap'] . 'px';
    }
    if (isset($ov['sm']['gap'])) {
        $vars[] = '--row-gap-sm:' . (int) $ov['sm']['gap'] . 'px';
    }

    $class = 'ecod-sec__row';
    if (!empty($row['masonry'])) {
        /* 行级瀑布流（P2 实验）：CSS columns 纵向填充，列数 = 当前行列数 */
        $class .= ' ecod-sec__row--masonry';
        $vars[] = '--masonry-cols:' . count($cells);
    }
    foreach (['md', 'sm'] as $dev) {
        if (!empty($ov[$dev]['keep'])) {
            $class .= ' ecod-sec__row--keep-' . $dev;
        }
        if (!empty($ov[$dev]['hide'])) {
            $class .= ' ecod-sec__row--hide-' . $dev;
        }
    }

    $attrs = '';
    if ($builder) {
        $attrs .= ' data-buid="' . htmlspecialchars((string) ($row['uid'] ?? ''), ENT_QUOTES, 'UTF-8')
            . '" data-bkind="row"';
    }

    echo '<div class="' . $class . '"' . $attrs . ' style="' . implode(';', $vars) . '">';
    foreach ($cells as $cell) {
        ecod_layout_render_cell((array) $cell, $widget, $builder);
    }
    echo '</div>';
}

/**
 * 一行的 grid-template-columns 片段：fr 列 → minmax(0,Nfr)；px 列 → Npx
 */
function ecod_layout_row_cols(array $cells): string
{
    $parts = [];
    foreach ($cells as $cell) {
        if (!empty($cell['wpx'])) {
            $parts[] = (int) $cell['wpx'] . 'px';
        } else {
            $parts[] = 'minmax(0,' . max(1, (int) ($cell['w'] ?? 1)) . 'fr)';
        }
    }
    return $parts !== [] ? implode(' ', $parts) : 'minmax(0,1fr)';
}

/**
 * 输出一列（列壳 + 列内模块堆叠；断点覆盖输出类与顺序变量）
 */
function ecod_layout_render_cell(array $cell, $widget = null, bool $builder = false): void
{
    $ov = is_array($cell['ov'] ?? null) ? $cell['ov'] : [];

    $class = 'ecod-sec__cell';
    if (!empty($cell['sticky'])) {
        $class .= ' ecod-sec__cell--sticky';
    }
    foreach (['md', 'sm'] as $dev) {
        if (!empty($ov[$dev]['hide'])) {
            $class .= ' ecod-sec__cell--hide-' . $dev;
        }
    }

    $vars = [];
    if (!empty($cell['wpx'])) {
        $vars[] = '--cw-px:' . (int) $cell['wpx'] . 'px';
    } else {
        $vars[] = '--cw:' . max(1, (int) ($cell['w'] ?? 1));
    }
    if (isset($ov['md']['w'])) {
        $vars[] = '--cw-md:' . max(1, (int) $ov['md']['w']);
    }
    if (isset($ov['sm']['w'])) {
        $vars[] = '--cw-sm:' . max(1, (int) $ov['sm']['w']);
    }
    if (isset($ov['sm']['order'])) {
        $vars[] = '--cell-order:' . (int) $ov['sm']['order'];
    }
    if (isset($ov['md']['order']) && !isset($ov['sm']['order'])) {
        $vars[] = '--cell-order:' . (int) $ov['md']['order'];
    }

    /* 列级卡面（v6 P1）：有值才输出变量，§42 里变量缺席时零样式影响 */
    if (($cell['bg'] ?? '') !== '') {
        $vars[] = '--cell-bg:url(\'' . htmlspecialchars((string) $cell['bg'], ENT_QUOTES, 'UTF-8') . '\')';
    }
    if (($cell['bgDark'] ?? '') !== '') {
        $vars[] = '--cell-bg-dark:url(\'' . htmlspecialchars((string) $cell['bgDark'], ENT_QUOTES, 'UTF-8') . '\')';
    }
    if (($cell['bgc'] ?? '') !== '') {
        $vars[] = '--cell-bgc:' . htmlspecialchars((string) $cell['bgc'], ENT_QUOTES, 'UTF-8');
    }
    if ((int) ($cell['radius'] ?? 0) > 0) {
        $vars[] = '--cell-radius:' . (int) $cell['radius'] . 'px';
    }
    if ((int) ($cell['pad'] ?? 0) > 0) {
        $vars[] = '--cell-pad:' . (int) $cell['pad'] . 'px';
    }

    $attrs = '';
    if ($builder) {
        $attrs .= ' data-buid="' . htmlspecialchars((string) ($cell['uid'] ?? ''), ENT_QUOTES, 'UTF-8')
            . '" data-bkind="cell"';
    }

    echo '<div class="' . $class . '"' . $attrs
        . ($vars !== [] ? ' style="' . implode(';', $vars) . '"' : '') . '>';
    if (!empty($cell['rows']) && is_array($cell['rows'])) {
        /* 列内嵌套行（深度 2）：复用行渲染器，cell 的 flex gap 20 承接行间距 */
        foreach ($cell['rows'] as $nrow) {
            ecod_layout_render_row((array) $nrow, $widget, $builder);
        }
    } else {
        ecod_layout_render_col((array) ($cell['mods'] ?? []), $widget, $builder);
    }
    echo '</div>';
}

/**
 * 输出一栏模块实例
 */
function ecod_layout_render_col(array $rows, $widget = null, bool $builder = false): void
{
    $defs = ecod_block_defs();
    $empty = true;

    foreach ($rows as $row) {
        $type = (string) ($row['type'] ?? '');
        $def = $defs[$type] ?? null;
        if ($def === null) {
            continue;
        }
        $empty = false;

        $hide = is_array($row['hide'] ?? null) ? $row['hide'] : [];
        $wrapClass = 'ecod-b-mod ecod-b-mod--' . $type;
        if (!empty($hide['md'])) {
            $wrapClass .= ' ecod-hide-md';
        }
        if (!empty($hide['sm'])) {
            $wrapClass .= ' ecod-hide-sm';
        }

        echo '<div class="' . $wrapClass . '" data-buid="' . htmlspecialchars((string) $row['uid'], ENT_QUOTES, 'UTF-8')
            . '" data-btype="' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '">';

        if (!empty($row['on']) || $builder) {
            $params = ecod_block_defaults($def);
            foreach ((array) ($row['params'] ?? []) as $k => $v) {
                if (array_key_exists($k, $params)) {
                    $params[$k] = $v;
                }
            }
            $block = [
                'uid'    => (string) $row['uid'],
                'id'     => $type,
                'type'   => $type,
                'area'   => 'layout',
                'on'     => !empty($row['on']),
                'params' => $params,
                'def'    => $def,
            ];
            if (empty($block['on'])) {
                echo '<div class="ecod-b-offhint">' . htmlspecialchars((string) $def['name'], ENT_QUOTES, 'UTF-8') . ' · 已停用</div>';
            } else {
                ecod_render_block($block, $widget);
            }
        }

        echo '</div>';
    }

    if ($empty && $builder) {
        echo '<div class="ecod-b-cta">空栏 · 拖入模块</div>';
    }
}

/* ==================================================================
 * 六、编辑器后台数据（builder.php 用）
 * ================================================================ */

/**
 * 路由生成 URL（带校验）：路由表未初始化或参数不齐时 Router 会返回 '#'，
 * 编辑器预览地址不能是这种坏值——统一在这里兜成空串（画布出「无地址」提示）。
 */
function ecod_bld_route_url(string $route, array $row, string $base): string
{
    try {
        $url = (string) \Typecho\Router::url($route, $row, $base);
    } catch (\Throwable $e) {
        return '';
    }
    if ($url === '' || $url === '#') {
        return '';
    }
    return $url;
}

/**
 * 文档选择器元数据：key => {label, badge, url, exists}
 */
function ecod_layout_admin_docs(): array
{
    $stored = ecod_layouts_read()['docs'] ?? [];
    $existsOverride = [];
    try {
        $rows = \Typecho\Db::get()->fetchAll(
            \Typecho\Db::get()->select('kind', 'ref')->from('table.ecod_page_layout')
        );
        foreach ($rows as $row) {
            $existsOverride[$row['kind'] . ':' . $row['ref']] = true;
        }
    } catch (\Throwable $e) {
    }

    $options = \Widget\Options::alloc();
    $siteUrl = rtrim((string) $options->siteUrl, '/');
    $docs = [];

    $docs['home'] = [
        'key' => 'home', 'label' => '首页', 'badge' => '页面类型默认',
        'url' => $siteUrl . '/', 'exists' => isset($stored['home']),
    ];

    /* 独立页（默认模板才能被 page.php 接管） */
    $docs['page'] = [
        'key' => 'page', 'label' => '独立页 · 默认', 'badge' => '页面类型默认',
        'url' => '', 'exists' => isset($stored['page']),
    ];
    try {
        $pages = \Typecho\Db::get()->fetchAll(
            \Typecho\Db::get()->select('cid', 'title', 'slug', 'template', 'type')
                ->from('table.contents')
                ->where('type = ?', 'page')
                ->where('status = ?', 'publish')
                ->order('created', \Typecho\Db::SORT_DESC)
                ->limit(200)
        );
        foreach ($pages as $p) {
            $tpl = (string) $p['template'];
            if ($tpl !== '' && $tpl !== 'page.php') {
                continue;
            }
            $url = '';
            $url = ecod_bld_route_url('page', $p, $siteUrl . '/');
            if ($url === '') {
                $url = $siteUrl . '/?p=' . (int) $p['cid'];
            }
            $docs['cid:' . $p['cid']] = [
                'key' => 'cid:' . $p['cid'], 'label' => (string) $p['title'], 'badge' => '单页覆盖',
                'url' => $url, 'exists' => isset($existsOverride['cid:' . $p['cid']]),
            ];
        }
    } catch (\Throwable $e) {
    }

    /* 分类 */
    try {
        $cats = \Typecho\Db::get()->fetchAll(
            \Typecho\Db::get()->select('mid', 'name', 'slug', 'type')->from('table.metas')
                ->where('type = ?', 'category')
                ->order('order', \Typecho\Db::SORT_ASC)
                ->limit(200)
        );
    } catch (\Throwable $e) {
        $cats = [];
    }
    foreach ($cats as $c) {
        $url = ecod_bld_route_url('category', $c, $siteUrl . '/');
        $docs['cat:' . $c['mid']] = [
            'key' => 'cat:' . $c['mid'], 'label' => (string) $c['name'], 'badge' => '分类覆盖',
            'url' => $url, 'exists' => isset($existsOverride['cat:' . $c['mid']]),
        ];
    }

    /* 其余页面类型：找一个真实实例做预览地址；找不到则 url 留空（编辑器显示提示） */
    $docs['single'] = ['key' => 'single', 'label' => '文章页', 'badge' => '页面类型默认', 'url' => '', 'exists' => isset($stored['single'])];
    $docs['cat'] = ['key' => 'cat', 'label' => '分类页 · 默认', 'badge' => '页面类型默认', 'url' => '', 'exists' => isset($stored['cat'])];
    $docs['tag'] = ['key' => 'tag', 'label' => '标签页', 'badge' => '页面类型默认', 'url' => '', 'exists' => isset($stored['tag'])];
    $docs['search'] = ['key' => 'search', 'label' => '搜索页', 'badge' => '页面类型默认', 'url' => '', 'exists' => isset($stored['search'])];
    $docs['author'] = ['key' => 'author', 'label' => '作者页', 'badge' => '页面类型默认', 'url' => '', 'exists' => isset($stored['author'])];
    $docs['custom'] = ['key' => 'custom', 'label' => '个性页', 'badge' => '页面类型默认', 'url' => '', 'exists' => isset($stored['custom'])];

    try {
        $db = \Typecho\Db::get();

        /* 文章页：最新一篇文章 */
        $lastPost = $db->fetchRow($db->select('cid', 'title', 'slug')->from('table.contents')
            ->where('type = ?', 'post')->where('status = ?', 'publish')
            ->order('created', \Typecho\Db::SORT_DESC)->limit(1));
        if ($lastPost) {
            $docs['single']['url'] = ecod_bld_route_url('post', $lastPost, $siteUrl . '/');
        }

        /* 分类页默认：第一个分类 */
        if ($cats) {
            $docs['cat']['url'] = ecod_bld_route_url('category', $cats[0], $siteUrl . '/');
        }

        /* 标签页：第一个有文章的标签（随便第一个即可，画布只是快照） */
        $firstTag = $db->fetchRow($db->select('mid', 'name', 'slug')->from('table.metas')
            ->where('type = ?', 'tag')->order('count', \Typecho\Db::SORT_DESC)->limit(1));
        if ($firstTag) {
            $docs['tag']['url'] = ecod_bld_route_url('tag', $firstTag, $siteUrl . '/');
        }

        /* 搜索页：路由直拼（搜索词给个占位） */
        $docs['search']['url'] = ecod_bld_route_url('search', ['keywords' => 'demo', 'type' => 'search'], $siteUrl . '/');

        /* 作者页：uid=1 */
        $docs['author']['url'] = ecod_bld_route_url('author', ['uid' => 1], $siteUrl . '/');

        /* 个性页：第一个自定义模板页 */
        $customTpl = $db->fetchRow($db->select('cid', 'title', 'slug', 'template')->from('table.contents')
            ->where('type = ?', 'page')->where('status = ?', 'publish')
            ->where('template <> ?', '')->where('template <> ?', 'page.php')
            ->order('created', \Typecho\Db::SORT_DESC)->limit(1));
        if ($customTpl) {
            $docs['custom']['url'] = ecod_bld_route_url('page', $customTpl, $siteUrl . '/');
        }
    } catch (\Throwable $e) {
    }

    return $docs;
}

/**
 * 编辑器模块库数据（defs 精简 + 图标 svg）
 */
function ecod_layout_admin_defs(): array
{
    $defs = [];
    foreach (ecod_block_defs() as $id => $def) {
        $defs[$id] = [
            'id'     => $id,
            'name'   => $def['name'],
            'desc'   => $def['desc'],
            'icon'   => $def['icon'],
            'area'   => $def['area'],
            'params' => array_map(static function ($p) {
                return [
                    'label'      => (string) ($p['label'] ?? ''),
                    'type'       => (string) ($p['type'] ?? 'text'),
                    'default'    => ecod_block_param_default($p),
                    'desc'       => (string) ($p['desc'] ?? ''),
                    'options'    => (array) ($p['options'] ?? []),
                    'min'        => $p['min'] ?? null,
                    'max'        => $p['max'] ?? null,
                    'placeholder'=> (string) ($p['placeholder'] ?? ''),
                    'fields'     => (array) ($p['fields'] ?? []),
                ];
            }, $def['params'] ?? []),
        ];
    }
    return $defs;
}

/* ==================================================================
 * 七、保存历史（布局快照）
 *
 * 每次成功保存都落一份该文档的完整快照，编辑器「历史」菜单可恢复。
 * v6 起存独立表 ecod_layout_snap（schema v16）：v6 文档更大，原 options
 * 单行 ELayoutSnaps 方案（24KB/条）会先于文档本身撞上列上限。
 * 每文档 8 份、单份超 64KB 跳过。
 * ================================================================ */

define('ECOD_LAYOUT_SNAPS_PER_KEY', 8);
define('ECOD_LAYOUT_SNAPS_ENTRY_MAX', 65536);

/**
 * 落一份快照（超大文档跳过，防止把存储撑爆）
 */
function ecod_layout_snap_push(string $key, array $sections): void
{
    $json = json_encode(['sections' => $sections], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || strlen($json) > ECOD_LAYOUT_SNAPS_ENTRY_MAX) {
        return;
    }

    try {
        $db = \Typecho\Db::get();
        $db->query($db->insert('table.ecod_layout_snap')->rows([
            'doc_key' => mb_substr($key, 0, 64),
            't'       => time(),
            'size'    => strlen($json),
            'doc'     => $json,
        ]));

        /* 每文档只留前 8 份（新→旧） */
        $ids = $db->fetchAll(
            $db->select('id')->from('table.ecod_layout_snap')
                ->where('doc_key = ?', mb_substr($key, 0, 64))
                ->order('t', \Typecho\Db::SORT_DESC)
                ->limit(200)
        );
        if (count($ids) > ECOD_LAYOUT_SNAPS_PER_KEY) {
            $dead = [];
            foreach (array_slice($ids, ECOD_LAYOUT_SNAPS_PER_KEY) as $one) {
                $dead[] = (int) $one['id'];
            }
            $db->query($db->delete('table.ecod_layout_snap')->where('id IN ?', $dead));
        }
    } catch (\Throwable $e) {
        /* 建表未完成 / 库不可写：快照静默降级，不影响保存主流程 */
    }
}

/**
 * 取一份快照并走清洗（恢复时与保存同口径，清洗入口自带 v5→v6 规范化）
 *
 * @return array<int,array>|null sections
 */
function ecod_layout_snap_get(string $key, string $id): ?array
{
    try {
        $row = \Typecho\Db::get()->fetchRow(
            \Typecho\Db::get()->select('doc')->from('table.ecod_layout_snap')
                ->where('doc_key = ?', mb_substr($key, 0, 64))
                ->where('id = ?', (int) $id)->limit(1)
        );
    } catch (\Throwable $e) {
        return null;
    }
    if (!$row || (string) $row['doc'] === '') {
        return null;
    }
    $data = json_decode((string) $row['doc'], true);
    if (!is_array($data)) {
        return null;
    }
    $clean = ecod_bld_clean_doc(['sections' => (array) ($data['sections'] ?? [])]);
    return $clean['sections'];
}

/**
 * 某文档的快照索引（编辑器菜单用，不带正文）
 *
 * @return array<int,array> {id,t,count}
 */
function ecod_layout_snap_index(string $key): array
{
    try {
        $rows = \Typecho\Db::get()->fetchAll(
            \Typecho\Db::get()->select('id', 't', 'doc')->from('table.ecod_layout_snap')
                ->where('doc_key = ?', mb_substr($key, 0, 64))
                ->order('t', \Typecho\Db::SORT_DESC)
                ->limit(ECOD_LAYOUT_SNAPS_PER_KEY)
        );
    } catch (\Throwable $e) {
        return [];
    }
    $out = [];
    foreach ($rows as $row) {
        $data = json_decode((string) $row['doc'], true);
        $out[] = [
            'id'    => (string) $row['id'],
            't'     => (int) $row['t'],
            'count' => is_array($data) ? count((array) ($data['sections'] ?? [])) : 0,
        ];
    }
    return $out;
}

/* ==================================================================
 * 八、预置模板（Starter，P2）
 * ================================================================ */

/**
 * 内置起步模板：板块骨架 + 行列结构 + 模块占位，参数留默认，
 * 套用后按需调整。结构与布局文档同 schema（保存时走同一套清洗）。
 *
 * v6 形态（docs/builder-v6-design.md §4）：单行单列，或「fr 主列 + 固定 px 侧列」，
 * 均可被编辑器的栏结构视图无损映射。
 */
function ecod_layout_templates(): array
{
    $cell = static function (array $mods, array $over = []): array {
        return array_merge(['uid' => '', 'w' => 1, 'sticky' => 0, 'ov' => [], 'mods' => $mods], $over);
    };
    $row = static function (array $cells, array $over = []): array {
        return array_merge(['uid' => '', 'on' => 1, 'gap' => 24, 'valign' => 'start', 'ov' => [], 'cells' => $cells], $over);
    };
    $sec = static function (string $uid, string $label, array $rows, array $over = []): array {
        return array_merge([
            'uid' => $uid, 'on' => 1, 'label' => $label, 'anchor' => '',
            'width' => 'full', 'contentMax' => 0, 'padY' => 48, 'radius' => 0,
            'bg' => '', 'bgDark' => '', 'tone' => 'auto', 'ov' => [],
            'rows' => $rows,
        ], $over);
    };
    $mod = static function (string $uid, string $type, array $params = []): array {
        return ['uid' => $uid, 'type' => $type, 'on' => 1, 'params' => $params, 'hide' => []];
    };

    return [
        'classic-home' => [
            'name' => '经典博客首页',
            'desc' => '搜索首屏 · 双栏推荐 · 磁贴导航 · 文章流',
            'sections' => [
                $sec('sec-tpl-hero', '搜索首屏', [
                    $row([$cell([
                        $mod('banner-t1', 'banner'), $mod('search-t1', 'search'),
                    ])]),
                ], ['padY' => 64]),
                $sec('sec-tpl-main', '精选推荐', [
                    $row([
                        $cell([$mod('recommend-t1', 'recommend'), $mod('feed-t1', 'feed')]),
                        $cell([$mod('hot-t1', 'hot'), $mod('title-list-t1', 'title-list')],
                            ['wpx' => 320]),
                    ]),
                ]),
                $sec('sec-tpl-tiles', '快捷导航', [
                    $row([$cell([$mod('tile-zone-t1', 'tile-zone')])]),
                ], ['width' => 'boxed', 'padY' => 40]),
                $sec('sec-tpl-topics', '专题墙', [
                    $row([$cell([$mod('topics-t1', 'topics')])]),
                ], ['padY' => 40]),
            ],
        ],
        'landing' => [
            'name' => '产品落地页',
            'desc' => '全宽轮播 · 磁贴特性 · 聚合内容 · 关于卡',
            'sections' => [
                $sec('sec-tpl-slider', '头图轮播', [
                    $row([$cell([$mod('slider-t2', 'slider')])]),
                ], ['padY' => 0]),
                $sec('sec-tpl-features', '核心特性', [
                    $row([$cell([$mod('tile-zone-t2', 'tile-zone')])]),
                ], ['width' => 'boxed', 'padY' => 56]),
                $sec('sec-tpl-social', '内容聚合', [
                    $row([$cell([$mod('tab-posts-t2', 'tab-posts')])]),
                ], ['tone' => 'dark', 'padY' => 56]),
                $sec('sec-tpl-about', '关于我们', [
                    $row([
                        $cell([$mod('about-t2', 'about')]),
                        $cell([$mod('overview-t2', 'overview')], ['wpx' => 300]),
                    ]),
                ], ['width' => 'boxed']),
            ],
        ],
        'minimal-feed' => [
            'name' => '极简单栏',
            'desc' => '杂志列表一条道，适合内容为主的轻站',
            'sections' => [
                $sec('sec-tpl-list', '最新内容', [
                    $row([$cell([$mod('list-t3', 'list')])]),
                ], ['padY' => 40]),
                $sec('sec-tpl-flash', '快讯', [
                    $row([$cell([$mod('flash-t3', 'flash')])]),
                ], ['width' => 'boxed', 'padY' => 32]),
                $sec('sec-tpl-friends', '友情链接', [
                    $row([$cell([$mod('partners-t3', 'partners')])]),
                ], ['width' => 'boxed', 'padY' => 32]),
            ],
        ],
    ];
}


/* ==================================================================
 * 八·二、可复用行（我的行模板，P2 synced patterns 的单站版）
 *
 * 行模板 = 清洗后的单行（深度 2 内的嵌套行合法），存 options 独立行。
 * 套用时编辑器整体深拷贝并重发 uid（tplRow），与预置预设共用落点管线。
 * ================================================================ */

define('ECOD_LAYOUT_ROWLIB', 'ELayoutRowLib');

/** 全部行模板：id => {id, name, t, row} */
function ecod_layout_rowlib_all(): array
{
    $data = ecod_cache_get(ECOD_LAYOUT_ROWLIB, null);
    return is_array($data) ? $data : [];
}

/**
 * 保存行模板（走与文档同一套行清洗；同名覆盖）。返回全部列表。
 */
function ecod_layout_rowlib_save(string $name, array $row): array
{
    $all = ecod_layout_rowlib_all();
    $clean = ecod_bld_clean_row($row, 'lib' . count($all));

    $name = mb_substr(trim($name), 0, 30);
    if ($name === '') {
        $name = '行模板 ' . date('H:i');
    }
    $id = '';
    foreach ($all as $k => $one) {
        if ((string) ($one['name'] ?? '') === $name) {
            $id = (string) $k; /* 同名覆盖：模板按名字取用，保留最新 */
        }
    }
    if ($id === '') {
        $id = 'srow-' . substr(md5($name . '|' . json_encode($clean) . '|' . time()), 0, 8);
    }
    $all[$id] = ['id' => $id, 'name' => $name, 't' => time(), 'row' => $clean];
    ecod_cache_set(ECOD_LAYOUT_ROWLIB, $all);
    return $all;
}

/** 删除行模板。返回全部列表。 */
function ecod_layout_rowlib_delete(string $id): array
{
    $all = ecod_layout_rowlib_all();
    if (isset($all[$id])) {
        unset($all[$id]);
        ecod_cache_set(ECOD_LAYOUT_ROWLIB, $all);
    }
    return $all;
}

/* ==================================================================
 * 八·三、模板中心（我的整页模板，P2；与 rowlib 同构，存整份 sections）
 * ================================================================ */

define('ECOD_LAYOUT_DOCLIB', 'ELayoutDocLib');

/** 全部页面模板：id => {id, name, t, count, sections} */
function ecod_layout_doclib_all(): array
{
    $data = ecod_cache_get(ECOD_LAYOUT_DOCLIB, null);
    return is_array($data) ? $data : [];
}

/**
 * 保存整页模板（走与文档同一套清洗，嵌套行/卡面/瀑布流一并合法；同名覆盖）。
 * 返回全部列表。
 */
function ecod_layout_doclib_save(string $name, array $sections, int $lock = 0): array
{
    $all = ecod_layout_doclib_all();
    $clean = ecod_bld_clean_doc(['sections' => $sections, 'lock' => $lock]);

    $name = mb_substr(trim($name), 0, 40);
    if ($name === '') {
        $name = '我的模板 ' . date('m-d H:i');
    }
    $id = '';
    foreach ($all as $k => $one) {
        if ((string) ($one['name'] ?? '') === $name) {
            $id = (string) $k; /* 同名覆盖：模板按名字取用，保留最新 */
        }
    }
    if ($id === '') {
        $id = 'dtpl-' . substr(md5($name . '|' . json_encode($clean) . '|' . time()), 0, 8);
    }
    $all[$id] = [
        'id'       => $id,
        'name'     => $name,
        't'        => time(),
        'count'    => count($clean['sections']),
        'sections' => $clean['sections'],
    ];
    ecod_cache_set(ECOD_LAYOUT_DOCLIB, $all);
    return $all;
}

/** 删除页面模板。返回全部列表。 */
function ecod_layout_doclib_delete(string $id): array
{
    $all = ecod_layout_doclib_all();
    if (isset($all[$id])) {
        unset($all[$id]);
        ecod_cache_set(ECOD_LAYOUT_DOCLIB, $all);
    }
    return $all;
}

/* ==================================================================
 * 八·四、v4 → v6 一键采纳（旧版区域 → 可视化布局文档）
 *
 * 回退页的画布内容不在构建器文档里（没有 cell 可命中），行列拖放
 * 无处落点。adopt 把页面自身的旧版区域实例转成 v6 文档：
 * 顶部/底部全宽 → 单列板块；内容区 → feed 占位 + 旧区域实例；
 * 侧栏三个区 → wpx 320 吸顶侧列。定点区顺序与模块参数原样保留，
 * 页脚与浮动控件（pinned）不迁移，仍走旧体系。
 * ================================================================ */

function ecod_layout_adopt_doc(string $key): ?array
{
    if (!function_exists('ecod_widget_areas') || !function_exists('ecod_widget_instances') || !function_exists('ecod_block_defs')) {
        return null;
    }
    $page = $key;
    if (preg_match('/^cat:\d+$/', $key)) {
        $page = 'cat';
    } elseif (preg_match('/^cid:\d+$/', $key)) {
        $page = 'page';
    }
    if (!isset(ecod_widget_pages()[$page])) {
        return null;
    }

    $modsOf = static function (string $area): array {
        $out = [];
        foreach (ecod_widget_instances($area) as $one) {
            $out[] = [
                'uid'    => (string) $one['uid'],
                'type'   => (string) $one['type'],
                'on'     => (int) $one['on'],
                'params' => is_array($one['params'] ?? null) ? $one['params'] : [],
            ];
        }
        return $out;
    };
    $sec = static function (string $label, array $cells, array $over = []): array {
        return array_merge([
            'uid' => '', 'on' => 1, 'label' => $label, 'anchor' => '',
            'width' => 'full', 'contentMax' => 0, 'padY' => 40, 'radius' => 0,
            'bg' => '', 'bgDark' => '', 'tone' => 'auto', 'ov' => [],
            'rows' => [['uid' => '', 'on' => 1, 'gap' => 24, 'valign' => 'start', 'ov' => [], 'cells' => $cells]],
        ], $over);
    };
    $cell = static function (array $mods): array {
        return ['uid' => '', 'w' => 1, 'sticky' => 0, 'ov' => [], 'mods' => $mods];
    };

    $sections = [];

    /* 只转换页面自身区域；全局区（all_*）不转换不清理——它们仍在文档之外的
       独立通栏区渲染，转进来会同页重复。侧栏例外：文档自带侧列后主题侧栏
       让位（has_aside），全局侧栏实例复制进侧列才不丢，其他页面不受影响 */
    $top = $modsOf($page . '_top_fluid');
    if ($top) {
        $sections[] = $sec('顶部全宽', [$cell($top)], ['padY' => 0]);
    }

    /* 正文主体：旧版正文是主题主循环，不在区域里——用 feed 模块占位承接 */
    $mainMods = array_merge(
        $modsOf($page . '_top_content'),
        [['uid' => '', 'type' => 'feed', 'on' => 1, 'params' => []]],
        $modsOf($page . '_bottom_content')
    );
    $sideMods = array_merge(
        $modsOf('all_sidebar_top'),
        $modsOf($page . '_sidebar'),
        $modsOf('all_sidebar_bottom')
    );
    $contentCells = [$cell($mainMods)];
    if ($sideMods) {
        $aside = $cell($sideMods);
        unset($aside['w']);
        $aside['wpx'] = 320;
        $aside['sticky'] = 1;
        $contentCells[] = $aside;
    }
    $sections[] = $sec('内容区', $contentCells, ['width' => 'boxed', 'contentMax' => 1200]);

    $bottom = $modsOf($page . '_bottom_fluid');
    if ($bottom) {
        $sections[] = $sec('底部全宽', [$cell($bottom)], ['padY' => 0]);
    }

    if (!$sections) {
        return null;
    }

    /* 合并语义：页面可能已有部分 v6 板块（如只搭了一个 hero）。转换出的
       「顶部全宽」排最前（它原本就渲染在最上），内容区/底部接在现有板块后 */
    $existing = ecod_layout_doc_for_key($key);
    $existingSecs = $existing && !empty($existing['sections']) ? $existing['sections'] : [];
    $convertedTop = [];
    if ($sections[0]['label'] === '顶部全宽') {
        $convertedTop = [$sections[0]];
        array_shift($sections);
    }
    $sections = array_merge($convertedTop, $existingSecs, $sections);

    if (!ecod_layout_save_doc($key, ['sections' => $sections, 'lock' => 0])) {
        return null;
    }
    ecod_layout_stage_clear($key);

    /* 移动语义：文档接管后，页面自身的旧区域必须清空，否则同批模块
       会在文档之外再渲染一遍（重复）。全局区/定点区（页脚浮动件）不动 */
    try {
        $prod = ecod_widgets_read_option();
        if (is_array($prod) && isset($prod['areas']) && is_array($prod['areas'])) {
            foreach (['top_fluid', 'top_content', 'bottom_content', 'bottom_fluid', 'sidebar'] as $slot) {
                $prod['areas'][$page . '_' . $slot] = [];
            }
            ecod_widgets_write($prod);
        }
    } catch (\Throwable $e) {
        /* 清理失败最坏情况 = 旧区域再渲染一遍，不阻塞转换主流程 */
    }

    $saved = ecod_layout_doc_for_key($key);
    return $saved ? $saved['sections'] : null;
}


/* ==================================================================
 * 九、暂存区（staging）：画布预览的是未发布的本地草稿
 *
 * 编辑动作先落暂存（不触生产），画布以暂存态渲染；
 * 「发布」才把暂存写入 ELayouts / EWidgets 并清空暂存。
 * ================================================================ */

define('ECOD_LAYOUT_STAGING', 'ELayoutStaging');
define('ECOD_WGT_STAGING', 'EWidgetsStaging');

function ecod_layout_stage_all(): array
{
    $data = ecod_cache_get(ECOD_LAYOUT_STAGING, null);
    return is_array($data) ? $data : [];
}

/** 某文档的暂存草稿（已清洗）；无暂存返回 null */
function ecod_layout_stage_get(string $key): ?array
{
    $one = ecod_layout_stage_all()[$key] ?? null;
    if (!is_array($one) || empty($one['sections'])) {
        return null;
    }
    return ecod_bld_clean_doc($one);
}

function ecod_layout_stage_set(string $key, array $doc): void
{
    $all = ecod_layout_stage_all();
    $clean = ecod_bld_clean_doc($doc);
    if (empty($clean['sections'])) {
        ecod_layout_stage_clear($key);
        return;
    }
    $all[$key] = $clean;
    ecod_cache_set(ECOD_LAYOUT_STAGING, $all);
    unset($GLOBALS['ecod_layout_active']);
}

function ecod_layout_stage_clear(string $key): void
{
    $all = ecod_layout_stage_all();
    if (isset($all[$key])) {
        unset($all[$key]);
        ecod_cache_set(ECOD_LAYOUT_STAGING, $all);
    }
    unset($GLOBALS['ecod_layout_active']);
}

/** v4 区域暂存：on=1 表示有暂存（全部删空也是合法暂存态，用 on 区分「无暂存」） */
function ecod_wgt_stage_read(): ?array
{
    $data = ecod_cache_get(ECOD_WGT_STAGING, null);
    if (!is_array($data) || empty($data['on']) || !isset($data['areas']) || !is_array($data['areas'])) {
        return null;
    }
    return $data;
}

/** 暂存态 → v4 文档（供画布渲染覆写 EWidgets） */
function ecod_wgt_stage_doc(): ?array
{
    $staged = ecod_wgt_stage_read();
    if ($staged === null) {
        return null;
    }
    $doc = ecod_widget_blank_doc();
    foreach (array_keys($doc['areas']) as $id) {
        $rows = [];
        foreach ((array) ($staged['areas'][$id] ?? []) as $row) {
            $one = ecod_widget_norm_row($row);
            if ($one !== null) {
                $rows[] = $one;
            }
        }
        $doc['areas'][$id] = $rows;
    }
    /* 侧栏位置映射：暂存里有就用暂存的（区域面板可编辑），
       没有则沿用生产——否则删个模块会让整个侧栏翻面 */
    try {
        if (isset($staged['aside']) && is_array($staged['aside'])) {
            $doc['aside'] = $staged['aside'];
        } else {
            $prod = ecod_widgets_read_option();
            $doc['aside'] = is_array($prod['aside'] ?? null) ? $prod['aside'] : [];
        }
    } catch (\Throwable $e) {
    }
    return $doc;
}

function ecod_wgt_stage_set(array $areas, ?array $aside = null): void
{
    /* 合并语义：只覆盖传入的（脏）区域，未动的区域沿用首次暂存时的生产快照。
       全量盲写会把编辑器打开期间生产侧的变化（迁移 / 其他窗口修改）一并回滚——
       那正是「删个组件，别的组件跑到别处」的来源。 */
    $staged = ecod_wgt_stage_read();
    if ($staged === null) {
        $seed = [];
        foreach (ecod_widget_areas() as $id => $meta) {
            $rows = [];
            foreach (ecod_widget_instances($id) as $one) {
                $rows[] = [
                    'uid'    => $one['uid'],
                    'type'   => $one['type'],
                    'on'     => (int) $one['on'],
                    'params' => $one['params'],
                ];
            }
            $seed[$id] = $rows;
        }
        $staged = ['on' => 1, 'areas' => $seed];
    }
    foreach ($areas as $id => $rows) {
        if (!isset(ecod_widget_areas()[$id])) {
            continue;
        }
        $staged['areas'][$id] = is_array($rows) ? $rows : [];
    }
    if ($aside !== null) {
        $staged['aside'] = $aside;
    }
    ecod_cache_set(ECOD_WGT_STAGING, $staged);
    if (function_exists('ecod_widgets_flush')) {
        ecod_widgets_flush();
    }
}

function ecod_wgt_stage_clear(): void
{
    ecod_cache_set(ECOD_WGT_STAGING, ['on' => 0, 'areas' => []]);
    if (function_exists('ecod_widgets_flush')) {
        ecod_widgets_flush();
    }
}

/* ==================================================================
 * 十、站点样式（主题设置的外观 / 布局子集，双端同源）
 *
 * 构建器「站点样式」面板与「主题设置」读写同一批 options 行，
 * 任意一侧修改互通。白名单之外一律拒收。
 * ================================================================ */

function ecod_bld_settings_defs(): array
{
    return [
        'groups' => [
            ['title' => '品牌门面', 'fields' => ['EPrimary', 'ELogo', 'ELogoImage', 'EFavicon', 'EBackdrop', 'EBgImage', 'EBgMask', 'EBgLightColors', 'EBgLightSpeed', 'EBgLightLayers', 'EBgLightMouse', 'EBgLightOpacity']],
            ['title' => '明暗与动效', 'fields' => ['EDark', 'EAnimate', 'ECursor', 'ECardMotion']],
            ['title' => '列表与布局', 'fields' => ['EAside', 'ECardMode', 'EThumbSide', 'EListPerPage', 'EListLoad']],
        ],
        'fields' => [
            'EPrimary' => [
                'label' => '主题主色', 'type' => 'text', 'default' => '#4f6ef2',
                'placeholder' => '#4f6ef2',
                'desc' => '六位十六进制色值，按钮、链接、高亮全部跟随；留空恢复默认',
            ],
            'ELogo' => [
                'label' => '站点名称（顶部展示用）', 'type' => 'text', 'default' => '',
                'desc' => '导航与页头展示的名称；留空用 Typecho 站点标题',
            ],
            'ELogoImage' => [
                'label' => 'Logo 图', 'type' => 'image', 'default' => '',
                'desc' => '设置后页头以图片展示 Logo',
            ],
            'EFavicon' => [
                'label' => '站点图标（Favicon）', 'type' => 'image', 'default' => '',
                'desc' => '建议 32×32 以上 PNG / SVG；留空用主色生成的占位图标',
            ],
            'EBackdrop' => [
                'label' => '站点背景', 'type' => 'select', 'default' => 'none',
                'options' => ['none' => '纯色', 'soft' => '双色柔光', 'mesh' => '网格渐变', 'image' => '自定义图片', 'lightfall' => '流光（动效）'],
            ],
            'EBgImage' => [
                'label' => '背景图', 'type' => 'image', 'default' => '',
                'desc' => '铺满视口固定，深浅色共用；建议 1920px 以上横图',
            ],
            'EBgMask' => [
                'label' => '背景蒙层（%）', 'type' => 'number', 'default' => 55, 'min' => 0, 'max' => 90,
                'desc' => '越大文字越清楚、图片越淡',
            ],
            'EBgLightColors' => [
                'label' => '流光颜色', 'type' => 'text', 'default' => '#4f6ef2,#8b5cf6',
                'desc' => '最多 3 个色值，英文逗号分隔；留空用主题主色系',
            ],
            'EBgLightSpeed' => [
                'label' => '流光速度', 'type' => 'number', 'default' => 0.3, 'min' => 0.05, 'max' => 2,
                'desc' => '光线落下的快慢',
            ],
            'EBgLightLayers' => [
                'label' => '流光层数', 'type' => 'number', 'default' => 2, 'min' => 1, 'max' => 8,
                'desc' => '层数越多隧道越满；移动端封顶 2 层',
            ],
            'EBgLightMouse' => [
                'label' => '跟随鼠标', 'type' => 'select', 'default' => 'on',
                'options' => ['on' => '开启', 'off' => '关闭'],
            ],
            'EBgLightOpacity' => [
                'label' => '流光浓度（%）', 'type' => 'number', 'default' => 70, 'min' => 10, 'max' => 100,
                'desc' => '整体透明度，建议 50~80',
            ],
            'EDark' => [
                'label' => '深色模式', 'type' => 'select', 'default' => 'auto',
                'options' => ['auto' => '跟随系统', 'on' => '允许手动切换', 'off' => '关闭'],
            ],
            'EAnimate' => [
                'label' => '列表入场动效', 'type' => 'select', 'default' => 'blur',
                'options' => ['none' => '关闭', 'blur' => '模糊上浮（推荐）', 'zoom' => '缩放浮现', 'up' => '上浮淡入', 'fade' => '淡入'],
            ],
            'ECursor' => [
                'label' => '鼠标跟随光点', 'type' => 'select', 'default' => 'off',
                'options' => ['off' => '关闭', 'dot' => '光点'],
            ],
            'ECardMotion' => [
                'label' => '卡片动效', 'type' => 'select', 'default' => 'on',
                'options' => ['on' => '开启', 'off' => '关闭'],
                'desc' => '磁贴 / 聚合卡等模块的 hover 上浮与图片渐入总开关',
            ],
            'EAside' => [
                'label' => '侧栏位置（全站默认）', 'type' => 'select', 'default' => 'right',
                'options' => ['right' => '右侧栏', 'left' => '左侧栏', 'none' => '无侧栏'],
            ],
            'ECardMode' => [
                'label' => '默认卡片模式', 'type' => 'select', 'default' => 'default',
                'options' => [
                    'default' => '列表（文左图右）', 'feature' => '大图（重点文章）', 'gallery' => '三图（封面轮播）',
                    'bare' => '无图', 'grid' => '网格', 'title' => '标题式',
                    'compact' => '紧凑行（无摘要）', 'mosaic' => '混合网格（1 大 + N 小）', 'item' => '清单 / 装备',
                    'note' => '笔记卡（竖版网格）',
                ],
                'desc' => '文章可在编辑页用自定义字段 mode 单独覆盖',
            ],
            'EThumbSide' => [
                'label' => '缩略图位置', 'type' => 'select', 'default' => 'right',
                'options' => ['right' => '右图（默认）', 'left' => '左图'],
                'desc' => '列表 / 大图模式生效',
            ],
            'EListPerPage' => [
                'label' => '每页文章数', 'type' => 'number', 'default' => 0, 'min' => 0, 'max' => 60,
                'desc' => '0 = 跟随后台「阅读」设置',
            ],
            'EListLoad' => [
                'label' => '列表加载方式', 'type' => 'select', 'default' => 'auto',
                'options' => ['auto' => '触底自动加载（默认）', 'click' => '点击「加载更多」', 'pager' => '数字分页'],
            ],
        ],
    ];
}

function ecod_bld_settings_values(): array
{
    $out = [];
    foreach (ecod_bld_settings_defs()['fields'] as $key => $f) {
        $v = ecod_option($key, '');
        $out[$key] = ($v === '' || $v === null) ? (string) $f['default'] : (string) $v;
    }
    return $out;
}

/** 白名单校验后写回主题配置（Typecho 把整套主题配置序列化在 theme:{slug} 一行 JSON） */
function ecod_bld_settings_save(array $values): int
{
    $defs = ecod_bld_settings_defs();
    $db = \Typecho\Db::get();
    $theme = (string) ecod_option('theme', '');
    $rowName = 'theme:' . $theme;
    $row = $db->fetchRow(
        $db->select('value')->from('table.options')
            ->where('name = ?', $rowName)->where('user = ?', 0)->limit(1)
    );
    if (!$row) {
        return 0;
    }
    $cfg = json_decode((string) $row['value'], true);
    if (!is_array($cfg)) {
        return 0;
    }

    $saved = 0;
    foreach ($defs['fields'] as $key => $f) {
        if (!array_key_exists($key, $values)) {
            continue;
        }
        $v = $values[$key];
        $type = (string) $f['type'];

        if ($type === 'select') {
            $v = (string) $v;
            if (!isset($f['options'][$v])) {
                $v = (string) $f['default'];
            }
        } elseif ($type === 'number') {
            $v = $v === '' ? null : min(max((int) $v, (int) ($f['min'] ?? 0)), (int) ($f['max'] ?? 100));
        } elseif ($type === 'image') {
            $v = ecod_bld_clean_url($v);
        } else {
            $v = mb_substr(trim((string) $v), 0, 300);
            if ($key === 'EPrimary' && $v !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $v)) {
                $v = '';
            }
        }

        /* 空值存 null（与 Typecho 表单空值一致，主题代码按默认回落） */
        $cfg[$key] = ($v === '' || $v === null) ? null : $v;
        $saved++;
    }

    $db->query($db->update('table.options')
        ->rows(['value' => json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)])
        ->where('name = ?', $rowName)->where('user = ?', 0));

    return $saved;
}

