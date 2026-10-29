<?php
/**
 * Ecod Theme · 区域与模块（Widget）系统
 *
 * 「万物皆插件」：前台每一个功能区域都由模块提供。
 * 模块 = 注册表里的一个条目（声明 + 参数 schema + 一个模板文件），
 * 后台在独立管理台里按「位置」摆放实例、改参数，前台按区域顺序渲染。
 *
 * 与 v1 的区别（设计见 docs/dev/design-proposal.html §31）：
 *   - 位置从 4 个页面级桶扩成「页面类型 × 5 个位置」+ 全局区；
 *   - 区域里存实例（uid），同一模块可放多个实例，参数各自独立；
 *   - 存储搬到 options 表 user=0 的独立行 EWidgets，不再整行覆盖主题配置；
 *   - 旧 EBlocks 只作迁移来源：读时在内存里转 v2，首次保存才落盘。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @since   2026-10-29
 * @version 2.0.0
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 * @license   MIT
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/** 新版存储：options 表 user=0 的独立 JSON 行 */
define('ECOD_WIDGETS_OPTION', 'EWidgets');

/** 旧版存储：主题选项里的 EBlocks（只读，迁移来源） */
define('ECOD_BLOCKS_OPTION', 'EBlocks');

/** 定点区：页脚浮动控件，位置写死在模板里，只开关、不拖动 */
define('ECOD_WIDGET_PINNED', 'pinned');

/** 数据结构版本 */
define('ECOD_WIDGET_SCHEMA', 2);

/** 单个区域的实例上限（旧 EBlocks 首页最多 14 条，故取 20 保证迁移不截断） */
define('ECOD_WIDGET_LIMIT', 20);

/* ==================================================================
 * 一、区域注册表
 * ================================================================ */

/**
 * 页面类型
 *
 * @return array<string,string> key => 名称
 */
function ecod_widget_pages(): array
{
    return [
        'home'   => _t('首页'),
        'single' => _t('文章页'),
        'page'   => _t('独立页面'),
        'cat'    => _t('分类页'),
        'tag'    => _t('标签页'),
        'search' => _t('搜索页'),
        'author' => _t('作者页'),
        'custom' => _t('个性页'),
    ];
}

/**
 * 位置（区域后缀）
 *
 * @return array<string,string> key => 名称
 */
function ecod_widget_slots(): array
{
    return [
        'top_fluid'      => _t('顶部全宽'),
        'top_content'    => _t('内容区上方'),
        'bottom_content' => _t('内容区下方'),
        'bottom_fluid'   => _t('底部全宽'),
        'sidebar'        => _t('侧边栏'),
    ];
}

/**
 * 全局区：叠加在所有页面上的固定位置
 *
 * before = true 表示排在页面自己的同名位置之前（顶部类），false 表示之后（底部类）。
 *
 * @return array<string,array>
 */
function ecod_widget_globals(): array
{
    return [
        'all_top_fluid'      => ['label' => _t('所有页面 · 顶部全宽'), 'slot' => 'top_fluid', 'before' => true],
        'all_sidebar_top'    => ['label' => _t('所有页面 · 侧栏顶部'), 'slot' => 'sidebar', 'before' => true],
        'all_sidebar_bottom' => ['label' => _t('所有页面 · 侧栏底部'), 'slot' => 'sidebar', 'before' => false],
        'all_bottom_fluid'   => ['label' => _t('所有页面 · 底部全宽'), 'slot' => 'bottom_fluid', 'before' => false],
    ];
}

/**
 * 全部区域：全局区 + 定点区 + 页面类型 × 位置
 *
 * id 形如 all_top_fluid / home_top_content / single_sidebar / pinned
 *
 * @return array<string,array> id => [id,label,page,slot,global,pinned]
 */
function ecod_widget_areas(): array
{
    static $areas = null;
    if ($areas !== null) {
        return $areas;
    }

    $areas = [];

    foreach (ecod_widget_globals() as $id => $meta) {
        $areas[$id] = [
            'id'     => $id,
            'label'  => $meta['label'],
            'page'   => 'all',
            'slot'   => $meta['slot'],
            'global' => true,
            'pinned' => false,
        ];
    }

    $areas[ECOD_WIDGET_PINNED] = [
        'id'     => ECOD_WIDGET_PINNED,
        'label'  => _t('页脚与浮动控件'),
        'page'   => 'all',
        'slot'   => '',
        'global' => false,
        'pinned' => true,
    ];

    foreach (ecod_widget_pages() as $page => $pageLabel) {
        foreach (ecod_widget_slots() as $slot => $slotLabel) {
            $id = $page . '_' . $slot;
            $areas[$id] = [
                'id'     => $id,
                'label'  => $pageLabel . ' · ' . $slotLabel,
                'page'   => $page,
                'slot'   => $slot,
                'global' => false,
                'pinned' => false,
            ];
        }
    }

    return $areas;
}

/**
 * 某个位置在某个页面上的区域渲染顺序
 *
 * @param string $slot 位置 key
 * @param string $page 页面类型 key
 * @return array<int,string> 区域 id 列表
 */
function ecod_widget_area_order(string $slot, string $page): array
{
    $areas = ecod_widget_areas();
    if (!isset(ecod_widget_slots()[$slot])) {
        return [];
    }
    if (!isset(ecod_widget_pages()[$page])) {
        $page = 'custom';
    }

    $own = $page . '_' . $slot;
    $list = [];

    switch ($slot) {
        case 'top_fluid':
            $list = ['all_top_fluid', $own];
            break;
        case 'bottom_fluid':
            $list = [$own, 'all_bottom_fluid'];
            break;
        case 'sidebar':
            $list = ['all_sidebar_top', $own, 'all_sidebar_bottom'];
            break;
        default:
            $list = [$own];
    }

    return array_values(array_filter($list, static function ($id) use ($areas) {
        return isset($areas[$id]);
    }));
}

/**
 * 当前页面类型
 *
 * 页面类型决定用哪一组区域。传入 Typecho 组件时按其路由判定；
 * 传入字符串时按传入值（模板可显式指定）；都不传时按 custom 兜底。
 *
 * @param mixed $widget Typecho 组件 / 页面类型字符串 / null
 * @return string home|single|page|cat|tag|search|author|custom
 */
function ecod_page_type($widget = null): string
{
    $pages = ecod_widget_pages();

    if (is_string($widget)) {
        return isset($pages[$widget]) ? $widget : 'custom';
    }

    if (is_object($widget) && method_exists($widget, 'is')) {
        try {
            if ($widget->is('index')) {
                return 'home';
            }
            if ($widget->is('post')) {
                return 'single';
            }
            if ($widget->is('page')) {
                $tpl = isset($widget->template) ? (string) $widget->template : '';
                if ($tpl === '' && isset($widget->themeFile)) {
                    $tpl = (string) $widget->themeFile;
                }
                return ($tpl === '' || $tpl === 'page.php') ? 'page' : 'custom';
            }
            if ($widget->is('category')) {
                return 'cat';
            }
            if ($widget->is('tag')) {
                return 'tag';
            }
            if ($widget->is('search')) {
                return 'search';
            }
            if ($widget->is('author')) {
                return 'author';
            }
        } catch (\Throwable $e) {
            /* 路由状态异常时按个性页处理，不影响渲染 */
        }
    }

    return 'custom';
}

/* ==================================================================
 * 二、模块声明
 * ================================================================ */

/**
 * 定义注册表（引用返回，便于就地写入）
 */
function &ecod_block_registry(): array
{
    static $defs = [];
    return $defs;
}

/**
 * 注册一个模块
 *
 * @param string $id   唯一标识（同时是实例的 type）
 * @param array  $args name/desc/icon/area/order/on/fixed/required/anchor/params/file/pages/slots
 */
function ecod_block_define(string $id, array $args = []): void
{
    $defs = &ecod_block_registry();
    $defs[$id] = array_merge([
        'id'       => $id,
        'name'     => $id,
        'desc'     => '',
        'icon'     => 'layers',
        'area'     => 'home',   // 模板目录：components/{area}/{id}.php
        'areas'    => [],       // 旧字段，v2 不再使用
        'pages'    => [],       // 允许的页面类型；留空 = 全部
        'slots'    => [],       // 允许的位置；留空 = 按 area 推导
        'order'    => 100,
        'on'       => true,
        'fixed'    => false,    // 不可关闭、不可移动（位置锁定在区域首位）
        'required' => false,    // 不可关闭，但可自由拖动排序
        'params'   => [],
        'file'     => '',       // 留空则按 components/{area}/{id}.php 查找
    ], $args, ['id' => $id]);
}

/**
 * 载入所有模块声明（只执行一次）
 */
function ecod_blocks_boot(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    require_once __DIR__ . '/blocks-def.php';
}

/**
 * 全部模块定义
 */
function ecod_block_defs(): array
{
    ecod_blocks_boot();
    return ecod_block_registry();
}

/* ==================================================================
 * 三、存储
 * ================================================================ */

/**
 * 规范化一条实例记录
 *
 * @param mixed $row
 * @return array|null
 */
function ecod_widget_norm_row($row): ?array
{
    if (!is_array($row)) {
        return null;
    }
    $uid = (string) ($row['uid'] ?? '');
    $type = (string) ($row['type'] ?? '');
    if ($uid === '' || $type === '' || !preg_match('/^[a-z0-9-]{1,40}$/', $uid)) {
        return null;
    }
    return [
        'uid'    => $uid,
        'type'   => $type,
        'on'     => !empty($row['on']) ? 1 : 0,
        'params' => is_array($row['params'] ?? null) ? $row['params'] : [],
    ];
}

/**
 * 空文档骨架
 */
function ecod_widget_blank_doc(): array
{
    $doc = ['v' => ECOD_WIDGET_SCHEMA, 'areas' => [], 'aside' => []];
    foreach (array_keys(ecod_widget_areas()) as $id) {
        $doc['areas'][$id] = [];
    }
    return $doc;
}

/**
 * 读取新版存储（EWidgets 独立行）
 *
 * @return array|null 空或损坏时返回 null
 */
function ecod_widgets_read_option(): ?array
{
    $data = ecod_cache_get(ECOD_WIDGETS_OPTION, null);
    if (!is_array($data) || empty($data['areas']) || !is_array($data['areas'])) {
        return null;
    }

    $doc = ecod_widget_blank_doc();

    foreach (array_keys($doc['areas']) as $id) {
        if (empty($data['areas'][$id]) || !is_array($data['areas'][$id])) {
            continue;
        }
        $rows = [];
        foreach ($data['areas'][$id] as $row) {
            $one = ecod_widget_norm_row($row);
            if ($one !== null) {
                $rows[] = $one;
            }
        }
        $doc['areas'][$id] = $rows;
    }

    if (!empty($data['aside']) && is_array($data['aside'])) {
        $doc['aside'] = $data['aside'];
    }

    return $doc;
}

/**
 * 读取旧版 EBlocks（只读）
 *
 * @return array area => rows；place 表原样带回
 */
function ecod_blocks_legacy_raw(): array
{
    $raw = [];
    $json = trim((string) ecod_option(ECOD_BLOCKS_OPTION, ''));
    if ($json === '') {
        return $raw;
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        return $raw;
    }

    foreach (['home', 'aside', 'post', 'footer'] as $area) {
        if (!empty($data[$area]) && is_array($data[$area])) {
            $raw[$area] = array_values($data[$area]);
        }
    }

    if (!empty($data['place']) && is_array($data['place'])) {
        $raw['place'] = $data['place'];
    }

    return $raw;
}

/**
 * 旧配置 → v2 文档（内存转换，不落盘）
 */
function ecod_widgets_legacy_doc(): array
{
    $defs = ecod_block_defs();
    $legacy = ecod_blocks_legacy_raw();
    $doc = ecod_widget_blank_doc();

    /* 旧区域 → 新区域（文章页的 top 锚点在下面单独处理） */
    $map = [
        'home'   => 'home_top_content',
        'aside'  => 'all_sidebar_top',
        'post'   => 'single_bottom_content',
        'footer' => ECOD_WIDGET_PINNED,
    ];

    /* 首页旧顺序（更早的 EHomeModules 文本） */
    if (!isset($legacy['home'])) {
        $legacyOrder = trim((string) ecod_option('EHomeModules', ''));
        if ($legacyOrder !== '') {
            $legacy['home'] = [];
            foreach (preg_split('/\r\n|\r|\n/', $legacyOrder) as $line) {
                $line = trim($line);
                if ($line !== '' && isset($defs[$line])) {
                    $legacy['home'][] = ['id' => $line, 'on' => 1];
                }
            }
        }
    }

    /* 追加实例：uid 按同区域同类型递增 */
    $push = static function (array &$doc, string $area, string $type, int $on, array $params): void {
        if (!isset($doc['areas'][$area]) || count($doc['areas'][$area]) >= ECOD_WIDGET_LIMIT) {
            return;
        }
        $count = 0;
        foreach ($doc['areas'][$area] as $one) {
            if ($one['type'] === $type) {
                $count++;
            }
        }
        $doc['areas'][$area][] = [
            'uid'    => $type . '-' . ($count + 1),
            'type'   => $type,
            'on'     => $on ? 1 : 0,
            'params' => $params,
        ];
    };

    foreach ($map as $oldArea => $target) {
        $rows = $legacy[$oldArea] ?? null;

        /* 从没存过：按声明顺序给一份默认（老站与全新安装都走这里） */
        if ($rows === null) {
            $rows = [];
            $byOrder = [];
            foreach ($defs as $id => $def) {
                if ($def['area'] !== $oldArea) {
                    continue;
                }
                $byOrder[$id] = (int) ($def['order'] ?? 100);
            }
            asort($byOrder);
            foreach (array_keys($byOrder) as $id) {
                $rows[] = ['id' => $id, 'on' => !empty($defs[$id]['on']) ? 1 : 0, 'params' => []];
            }
        }

        $seen = [];

        foreach ($rows as $row) {
            $id = is_array($row) ? (string) ($row['id'] ?? '') : (string) $row;
            if ($id === '' || !isset($defs[$id])) {
                continue;
            }
            $def = $defs[$id];
            $area = $target;

            /* 文章页顶部锚点（朗读条等）→ 内容区上方 */
            if ($oldArea === 'post' && (string) ($def['anchor'] ?? '') === 'top') {
                $area = 'single_top_content';
            }

            $seen[$id] = true;
            $push(
                $doc,
                $area,
                $id,
                is_array($row) && array_key_exists('on', $row) ? (!empty($row['on']) ? 1 : 0) : 1,
                is_array($row) && is_array($row['params'] ?? null) ? $row['params'] : []
            );
        }

        /* 旧存储里没有的新模块：按旧行为补在末尾、默认启用 */
        $missing = [];
        foreach ($defs as $id => $def) {
            if ($def['area'] === $oldArea && !isset($seen[$id])) {
                $missing[$id] = (int) ($def['order'] ?? 100);
            }
        }
        asort($missing);
        foreach (array_keys($missing) as $id) {
            $def = $defs[$id];
            $area = ($oldArea === 'post' && (string) ($def['anchor'] ?? '') === 'top')
                ? 'single_top_content'
                : $target;
            $push($doc, $area, $id, !empty($def['on']) ? 1 : 0, []);
        }
    }

    return $doc;
}

/**
 * 当前文档（存储态）：新版优先，没有就把旧配置转过来
 *
 * 用超全局做请求内缓存，写入后由 ecod_widgets_flush() 清掉，
 * 保证「保存 → 立刻算 rev」拿到的不是旧值。
 */
function ecod_widgets_raw(): array
{
    if (isset($GLOBALS['ecod_widgets_doc']) && is_array($GLOBALS['ecod_widgets_doc'])) {
        return $GLOBALS['ecod_widgets_doc'];
    }

    $doc = ecod_widgets_read_option();
    if ($doc === null) {
        $doc = ecod_widgets_legacy_doc();
    }

    $GLOBALS['ecod_widgets_doc'] = $doc;

    return $doc;
}

/**
 * 清掉请求内缓存（写入后必须调用）
 */
function ecod_widgets_flush(): void
{
    unset($GLOBALS['ecod_widgets_doc'], $GLOBALS['ecod_widgets_state']);
}

/**
 * 是否还在用旧配置（管理台据此显示「尚未迁移」提示）
 */
function ecod_widgets_is_legacy(): bool
{
    return ecod_widgets_read_option() === null;
}

/**
 * 写入新版存储
 *
 * 只写 options 表 user=0 的独立行，不触碰 theme:{slug} 主题配置行。
 */
function ecod_widgets_write(array $doc): bool
{
    $clean = ecod_widget_blank_doc();

    foreach (array_keys($clean['areas']) as $id) {
        $rows = [];
        foreach (($doc['areas'][$id] ?? []) as $row) {
            $one = ecod_widget_norm_row($row);
            if ($one !== null && isset(ecod_block_defs()[$one['type']])) {
                $rows[] = $one;
            }
        }
        $clean['areas'][$id] = array_slice($rows, 0, ECOD_WIDGET_LIMIT);
    }

    if (!empty($doc['aside']) && is_array($doc['aside'])) {
        foreach ($doc['aside'] as $page => $pos) {
            if (isset(ecod_widget_pages()[$page]) && in_array($pos, ['right', 'left', 'none'], true)) {
                $clean['aside'][$page] = $pos;
            }
        }
    }

    ecod_cache_set(ECOD_WIDGETS_OPTION, $clean);
    ecod_widgets_flush();

    return true;
}

/**
 * 存储态指纹：管理台用它做乐观锁
 */
function ecod_widgets_rev(?array $doc = null): string
{
    $doc = $doc ?? ecod_widgets_raw();
    return substr(md5(json_encode($doc, JSON_UNESCAPED_UNICODE)), 0, 12);
}

/* ==================================================================
 * 四、状态解析
 * ================================================================ */

/**
 * 解析后的状态：area => [ 实例... ]（已排序、已合并默认参数、已排序定点块）
 */
function ecod_widgets_state(): array
{
    if (isset($GLOBALS['ecod_widgets_state']) && is_array($GLOBALS['ecod_widgets_state'])) {
        return $GLOBALS['ecod_widgets_state'];
    }

    $defs = ecod_block_defs();
    $doc = ecod_widgets_raw();
    $state = [];

    foreach ($doc['areas'] as $area => $rows) {
        $list = [];

        foreach ($rows as $row) {
            $def = $defs[$row['type']] ?? null;
            if ($def === null) {
                continue;
            }

            $params = ecod_block_defaults($def);
            foreach ($row['params'] as $k => $v) {
                if (array_key_exists($k, $params)) {
                    $params[$k] = $v;
                }
            }

            $list[] = [
                'uid'    => $row['uid'],
                'id'     => $row['type'],   // 兼容旧代码：id 仍等于模块 type
                'type'   => $row['type'],
                'area'   => $area,
                'on'     => (!empty($def['fixed']) || !empty($def['required'])) ? true : !empty($row['on']),
                'params' => $params,
                'def'    => $def,
            ];
        }

        /* 位置锁定的模块（fixed）始终在区域最前 */
        $pinned = [];
        $rest = [];
        foreach ($list as $one) {
            if (!empty($one['def']['fixed'])) {
                $pinned[] = $one;
            } else {
                $rest[] = $one;
            }
        }
        $state[$area] = array_merge($pinned, $rest);
    }

    $GLOBALS['ecod_widgets_state'] = $state;

    return $state;
}

/**
 * 某区域的实例列表
 */
function ecod_widget_instances(string $area): array
{
    $state = ecod_widgets_state();
    return $state[$area] ?? [];
}

/**
 * 跨区域按 uid / type 找实例
 */
function ecod_widget_find(string $key): ?array
{
    foreach (ecod_widgets_state() as $list) {
        foreach ($list as $one) {
            if ($one['uid'] === $key) {
                return $one;
            }
        }
    }
    foreach (ecod_widgets_state() as $list) {
        foreach ($list as $one) {
            if ($one['type'] === $key) {
                return $one;
            }
        }
    }
    return null;
}

/**
 * 模块的参数默认值（含旧 option 兼容回落）
 */
function ecod_block_defaults(array $def): array
{
    $out = [];
    foreach (($def['params'] ?? []) as $key => $param) {
        $value = $param['default'] ?? null;
        if (!empty($param['legacy'])) {
            $legacy = (string) ecod_option($param['legacy'], '');
            if ($legacy !== '') {
                $value = $legacy;
            }
        }
        $out[$key] = $value;
    }
    return $out;
}

/**
 * 某个模块是否启用（任意区域有启用的实例即为真）
 */
function ecod_block_on(string $type): bool
{
    foreach (ecod_widgets_state() as $list) {
        foreach ($list as $one) {
            if ($one['type'] === $type && !empty($one['on'])) {
                return true;
            }
        }
    }
    return false;
}

/**
 * 某个模块的参数（取第一个实例）
 */
function ecod_block_p(string $type, string $key, $default = null)
{
    foreach (ecod_widgets_state() as $list) {
        foreach ($list as $one) {
            if ($one['type'] !== $type) {
                continue;
            }
            if (!array_key_exists($key, $one['params'])) {
                return $default;
            }
            $value = $one['params'][$key];
            return ($value === '' || $value === null) ? $default : $value;
        }
    }
    return $default;
}

/* ==================================================================
 * 五、渲染
 * ================================================================ */

/**
 * 当前正在渲染的实例（模板内部使用）
 */
function ecod_block_current(): ?array
{
    return $GLOBALS['ecod_block_current'] ?? null;
}

/**
 * 当前实例的参数（模板内部简写）
 */
function ecod_p(string $key, $default = null)
{
    $block = ecod_block_current();
    if (!$block || !array_key_exists($key, $block['params'])) {
        return $default;
    }
    $value = $block['params'][$key];
    return ($value === '' || $value === null) ? $default : $value;
}

/**
 * 模块模板文件路径
 *
 * 优先声明里的 file，其次按模块目录找（def['area']），最后才按当前区域找。
 */
function ecod_block_file(array $block): string
{
    $def = $block['def'];
    $type = (string) ($block['type'] ?? $block['id']);

    $candidates = [];
    if (!empty($def['file'])) {
        $candidates[] = $def['file'];
    }
    $candidates[] = 'components/' . $def['area'] . '/' . $type . '.php';
    $candidates[] = 'components/' . ($block['area'] ?? $def['area']) . '/' . $type . '.php';

    foreach (array_unique($candidates) as $rel) {
        $path = dirname(dirname(__DIR__)) . '/' . ltrim($rel, '/');
        if (is_file($path)) {
            return $path;
        }
    }

    return '';
}

/**
 * 渲染一个实例（内部使用）
 */
function ecod_render_block(array $block, $widget = null): void
{
    if (empty($block['on'])) {
        return;
    }

    $file = ecod_block_file($block);
    if ($file === '') {
        return;
    }

    $previous = $GLOBALS['ecod_block_current'] ?? null;
    $GLOBALS['ecod_block_current'] = $block;

    /* 非 static：需要绑定到 Typecho 组件，让模板里能用 $this */
    $render = function () use ($file, $block) {
        $ecodBlock = $block;
        include $file;
    };

    if (is_object($widget)) {
        $render->call($widget);
    } else {
        $render();
    }

    $GLOBALS['ecod_block_current'] = $previous;
}

/**
 * 渲染一个区域（只渲染启用的实例）
 */
function ecod_widgets_render_area(string $area, $widget = null): void
{
    foreach (ecod_widget_instances($area) as $one) {
        ecod_render_block($one, $widget);
    }
}

/**
 * 渲染一个位置（自动叠加全局区）
 *
 * @param string      $slot   位置 key：top_fluid / top_content / bottom_content / bottom_fluid / sidebar
 * @param mixed       $widget Typecho 组件（同时决定页面类型并绑定 $this）；也可传页面类型字符串
 * @param string|null $page   显式指定页面类型（传了就不再从组件推断）
 */
function ecod_widgets_render(string $slot, $widget = null, ?string $page = null): void
{
    if (is_string($widget)) {
        $page = $page ?? $widget;
        $widget = null;
    }

    $page = $page ?? ecod_page_type($widget);

    foreach (ecod_widget_area_order($slot, $page) as $area) {
        ecod_widgets_render_area($area, $widget);
    }
}

/**
 * 按 uid / type 渲染单个实例（页脚浮动控件这类定点块）
 */
function ecod_widget_render(string $key, $widget = null): void
{
    $one = ecod_widget_find($key);
    if ($one) {
        ecod_render_block($one, $widget);
    }
}

/**
 * 渲染一个「通栏区」：位置为空时连包裹层都不输出
 *
 * 全宽位置（top_fluid / bottom_fluid）需要一个出血的包裹层，
 * 由这里统一负责；内容区位置直接用 ecod_widgets_render()。
 *
 * @param string $slot  位置 key：top_fluid / bottom_fluid
 * @param mixed  $widget Typecho 组件
 */
function ecod_widgets_zone(string $slot, $widget = null): void
{
    $page = ecod_page_type($widget);
    $ids = ecod_widget_area_order($slot, $page);
    if (!$ids) {
        return;
    }

    $total = 0;
    foreach ($ids as $id) {
        $total += count(ecod_widget_instances($id));
    }
    if (!$total) {
        return;
    }

    echo '<div class="ecod_zone" data-zone="' . htmlspecialchars($slot, ENT_QUOTES, 'UTF-8') . '">';
    foreach ($ids as $id) {
        ecod_widgets_render_area($id, $widget);
    }
    echo '</div>';
}

/**
 * 旧 API：按 v1 区域名渲染
 *
 * home → 首页内容区上方；aside → 当前页侧栏；post → 文章页（anchor=top 走上方）；
 * footer → 定点区；其余按位置 key 直接透传。
 *
 * @deprecated 2.0.0 用 ecod_widgets_render()，保留一个版本周期
 */
function ecod_blocks_render(string $area, $widget = null, string $anchor = ''): void
{
    switch ($area) {
        case 'home':
            ecod_widgets_render('top_content', $widget, 'home');
            return;
        case 'aside':
            ecod_widgets_render('sidebar', $widget);
            return;
        case 'post':
            ecod_widgets_render($anchor === 'top' ? 'top_content' : 'bottom_content', $widget, 'single');
            return;
        case 'footer':
        case ECOD_WIDGET_PINNED:
            ecod_widgets_render_area(ECOD_WIDGET_PINNED, $widget);
            return;
        default:
            ecod_widgets_render($area, $widget);
    }
}

/**
 * 旧 API：按 id 定点渲染
 *
 * @deprecated 2.0.0 用 ecod_widget_render()
 */
function ecod_block_render(string $id, $widget = null): void
{
    ecod_widget_render($id, $widget);
}

/* ==================================================================
 * 六、侧栏可见性
 * ================================================================ */

/**
 * 当前页面的侧栏位置
 *
 * 判定顺序：单篇 / 单页自定义字段 aside > 按页面类型的 EAsidePages > 全站 EAside。
 *
 * @return string right|left|none
 */
function ecod_aside_pos($widget = null): string
{
    $page = ecod_page_type($widget);

    /* ① 单篇覆盖 */
    if (is_object($widget) && isset($widget->fields)) {
        try {
            $own = (string) $widget->fields->aside;
            if (in_array($own, ['right', 'left', 'none'], true)) {
                return $own;
            }
        } catch (\Throwable $e) {
            /* 没有该字段：继续走页面类型 */
        }
    }

    /* ② 页面类型 */
    $doc = ecod_widgets_raw();
    if (!empty($doc['aside'][$page]) && in_array($doc['aside'][$page], ['right', 'left', 'none'], true)) {
        return (string) $doc['aside'][$page];
    }

    /* ③ 全站默认 */
    $global = (string) ecod_option('EAside', 'right');
    return in_array($global, ['right', 'left', 'none'], true) ? $global : 'right';
}

/**
 * 某页面类型的侧栏位置（后台开关用，未设置时回落到全站默认）
 */
function ecod_aside_map(): array
{
    $doc = ecod_widgets_raw();
    $global = (string) ecod_option('EAside', 'right');
    if (!in_array($global, ['right', 'left', 'none'], true)) {
        $global = 'right';
    }

    $out = [];
    foreach (array_keys(ecod_widget_pages()) as $page) {
        $pos = $doc['aside'][$page] ?? '';
        $out[$page] = in_array($pos, ['right', 'left', 'none'], true) ? $pos : $global;
    }
    return $out;
}

/* ==================================================================
 * 七、后台数据
 * ================================================================ */

/**
 * 管理台数据快照（widget.php 序列化给前端）
 */
function ecod_widgets_admin_data(): array
{
    $defs = [];
    foreach (ecod_block_defs() as $id => $def) {
        $defs[$id] = [
            'id'       => $id,
            'name'     => $def['name'],
            'desc'     => $def['desc'],
            'icon'     => $def['icon'],
            'area'     => $def['area'],
            'order'    => (int) ($def['order'] ?? 100),
            'on'       => (bool) $def['on'],
            'fixed'    => !empty($def['fixed']),
            'required' => !empty($def['required']),
            'params'   => array_map(static function ($p) {
                return [
                    'label'   => $p['label'] ?? '',
                    'type'    => $p['type'] ?? 'text',
                    'default' => $p['default'] ?? '',
                    'desc'    => $p['desc'] ?? '',
                    'options' => $p['options'] ?? [],
                    'min'     => $p['min'] ?? null,
                    'max'     => $p['max'] ?? null,
                    'unit'    => $p['unit'] ?? '',
                    'when'    => $p['when'] ?? '',
                ];
            }, $def['params'] ?? []),
        ];
    }

    $areas = [];
    $instances = [];
    foreach (ecod_widget_areas() as $id => $meta) {
        $areas[$id] = $meta;
        $instances[$id] = [];
        foreach (ecod_widget_instances($id) as $one) {
            $instances[$id][] = [
                'uid'    => $one['uid'],
                'type'   => $one['type'],
                'on'     => (bool) $one['on'],
                'params' => $one['params'],
            ];
        }
    }

    return [
        'defs'      => $defs,
        'areas'     => $areas,
        'instances' => $instances,
        'pages'     => ecod_widget_pages(),
        'slots'     => ecod_widget_slots(),
        'globals'   => ecod_widget_globals(),
        'aside'     => ecod_aside_map(),
        'limit'     => ECOD_WIDGET_LIMIT,
        'rev'       => ecod_widgets_rev(),
        'legacy'    => ecod_widgets_is_legacy(),
    ];
}
