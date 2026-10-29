<?php
/**
 * Ecod Theme · 块（插件）系统
 *
 * 「万物皆插件」：前台每一个功能区域都由块提供。
 * 块 = 注册表里的一个条目（声明 + 参数 schema + 一个模板文件），
 * 后台以卡片形式管理启用状态、顺序与参数，前台按区域顺序渲染。
 *
 * @package Ecod Theme
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/** 块状态存储的 option 名 */
define('ECOD_BLOCKS_OPTION', 'EBlocks');

/** 支持的区域 */
function ecod_block_areas(): array
{
    return [
        'home'   => _t('首页'),
        'aside'  => _t('侧栏'),
        'post'   => _t('文章页'),
        'footer' => _t('页脚'),
    ];
}

/**
 * 定义注册表（引用返回，便于就地写入）
 */
function &ecod_block_registry(): array
{
    static $defs = [];
    return $defs;
}

/**
 * 注册一个块
 *
 * @param string $id   唯一标识，同时决定模板文件名
 * @param array  $args name/desc/icon/area/order/on/fixed/required/params/file
 */
function ecod_block_define(string $id, array $args = []): void
{
    $defs = &ecod_block_registry();
    $defs[$id] = array_merge([
        'id'     => $id,
        'name'   => $id,
        'desc'   => '',
        'icon'   => 'layers',
        'area'   => 'home',
        'order'  => 100,
        'on'     => true,
        'fixed'    => false, // 不可关闭、不可移动（位置锁定）
        'required' => false, // 不可关闭，但可自由拖动排序
        'params' => [],
        'file'   => '',      // 留空则按 components/{area}/{id}.php 查找
    ], $args, ['id' => $id]);
}

/**
 * 载入所有块声明（只执行一次）
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
 * 全部块定义
 */
function ecod_block_defs(): array
{
    ecod_blocks_boot();
    return ecod_block_registry();
}

/**
 * 读取后台保存的原始状态
 */
function ecod_blocks_raw(): array
{
    static $raw = null;
    if ($raw !== null) {
        return $raw;
    }

    $raw = [];
    $json = trim((string) ecod_option(ECOD_BLOCKS_OPTION, ''));
    if ($json === '') {
        return $raw;
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        return $raw;
    }

    foreach (array_keys(ecod_block_areas()) as $area) {
        if (!empty($data[$area]) && is_array($data[$area])) {
            $raw[$area] = array_values($data[$area]);
        }
    }

    /* 放置表：块 → 允许显示的多个区域（缺省时按块声明的区域推导） */
    if (!empty($data['place']) && is_array($data['place'])) {
        $raw['place'] = $data['place'];
    }

    return $raw;
}

/**
 * 某个块允许放置的区域（声明 > 单区域默认）
 */
function ecod_block_allowed_areas(array $def): array
{
    $all = array_keys(ecod_block_areas());
    $areas = array_values(array_intersect((array) ($def['areas'] ?? [$def['area']]), $all));

    return $areas ?: [$def['area']];
}

/**
 * 块 → 实际显示的区域列表
 *
 * 优先级：后台保存的 place > 旧配置里出现过的区域 > 块声明的首个区域。
 * 旧配置（没有 place）升级后行为不变，仍然是「一个块一个区域」。
 */
function ecod_blocks_place_map(array $defs, array $raw): array
{
    $stored = is_array($raw['place'] ?? null) ? $raw['place'] : [];
    $map = [];

    foreach ($defs as $id => $def) {
        $allowed = ecod_block_allowed_areas($def);
        $chosen = [];

        if (isset($stored[$id]) && is_array($stored[$id])) {
            $chosen = array_values(array_intersect($stored[$id], $allowed));
        }

        if (!$chosen) {
            foreach (array_keys(ecod_block_areas()) as $area) {
                foreach (($raw[$area] ?? []) as $row) {
                    $rowId = is_array($row) ? (string) ($row['id'] ?? '') : (string) $row;
                    if ($rowId === $id) {
                        $chosen[] = $area;
                        break;
                    }
                }
            }
            $chosen = array_values(array_intersect($chosen, $allowed));
        }

        $map[$id] = $chosen ?: [$def['area']];
    }

    return $map;
}

/**
 * 某个块在任意区域里保存过的参数（跨区域的唯一真相来源）
 */
function ecod_block_stored_params(array $raw, string $id): array
{
    foreach (array_keys(ecod_block_areas()) as $area) {
        foreach (($raw[$area] ?? []) as $row) {
            if (is_array($row) && (string) ($row['id'] ?? '') === $id && is_array($row['params'] ?? null)) {
                return $row['params'];
            }
        }
    }

    return [];
}

/**
 * 块的参数默认值（含旧 option 兼容回落）
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
 * 解析后的完整状态：area => [ {id,on,params,def}, ... ]（已排序、已合并默认）
 */
function ecod_blocks_state(): array
{
    static $state = null;
    if ($state !== null) {
        return $state;
    }

    $defs = ecod_block_defs();
    $raw  = ecod_blocks_raw();
    $place = ecod_blocks_place_map($defs, $raw);
    $state = [];

    foreach (array_keys(ecod_block_areas()) as $area) {
        $items = [];
        foreach ($defs as $id => $def) {
            if (in_array($area, $place[$id] ?? [$def['area']], true)) {
                $items[$id] = $def;
            }
        }
        uasort($items, static function ($a, $b) {
            return ($a['order'] ?? 100) <=> ($b['order'] ?? 100);
        });

        $stored = $raw[$area] ?? null;

        /* 首次启用：首页顺序沿用旧版 EHomeModules，避免升级后布局错乱 */
        if ($stored === null && $area === 'home') {
            $legacyOrder = trim((string) ecod_option('EHomeModules', ''));
            if ($legacyOrder !== '') {
                $stored = [];
                foreach (preg_split('/\r\n|\r|\n/', $legacyOrder) as $line) {
                    $line = trim($line);
                    if ($line !== '' && isset($items[$line])) {
                        $stored[] = ['id' => $line, 'on' => 1];
                    }
                }
            }
        }

        $list = [];
        $seen = [];

        if (is_array($stored)) {
            foreach ($stored as $row) {
                $id = is_array($row) ? (string) ($row['id'] ?? '') : (string) $row;
                if ($id === '' || !isset($items[$id]) || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $def = $items[$id];
                $params = ecod_block_defaults($def);
                /* 参数在多个区域间共用：本区域没存过就取别的区域存的值 */
                $saved = is_array($row['params'] ?? null) ? $row['params'] : ecod_block_stored_params($raw, $id);
                foreach ($saved as $k => $v) {
                    if (array_key_exists($k, $params)) {
                        $params[$k] = $v;
                    }
                }
                $list[] = [
                    'id'     => $id,
                    'area'   => $area,
                    'place'  => $place[$id] ?? [$area],
                    'on'     => (!empty($def['fixed']) || !empty($def['required'])) ? true : !empty($row['on']),
                    'params' => $params,
                    'def'    => $def,
                ];
            }
        }

        /* 补上未出现在存储里的新块 */
        foreach ($items as $id => $def) {
            if (isset($seen[$id])) {
                continue;
            }
            $params = ecod_block_defaults($def);
            foreach (ecod_block_stored_params($raw, $id) as $k => $v) {
                if (array_key_exists($k, $params)) {
                    $params[$k] = $v;
                }
            }
            $list[] = [
                'id'     => $id,
                'area'   => $area,
                'place'  => $place[$id] ?? [$area],
                'on'     => (!empty($def['fixed']) || !empty($def['required'])) ? true : !empty($def['on']),
                'params' => $params,
                'def'    => $def,
            ];
        }

        /* 位置锁定的块（fixed）始终锚定在区域最前，历史保存的顺序不再生效；
           required 只表示不可关闭，位置随用户拖动顺序 */
        $pinned = [];
        $rest   = [];
        foreach ($list as $block) {
            if (!empty($block['def']['fixed'])) {
                $pinned[] = $block;
            } else {
                $rest[] = $block;
            }
        }
        $list = array_merge($pinned, $rest);

        $state[$area] = $list;
    }

    return $state;
}

/**
 * 某区域的块列表
 */
function ecod_blocks_area(string $area): array
{
    $state = ecod_blocks_state();
    return $state[$area] ?? [];
}

/**
 * 单个块（跨区域查找）
 */
function ecod_block(string $id): ?array
{
    foreach (ecod_blocks_state() as $list) {
        foreach ($list as $block) {
            if ($block['id'] === $id) {
                return $block;
            }
        }
    }
    return null;
}

/**
 * 块是否启用
 */
function ecod_block_on(string $id): bool
{
    $block = ecod_block($id);
    return $block ? !empty($block['on']) : false;
}

/**
 * 指定块的参数
 */
function ecod_block_p(string $id, string $key, $default = null)
{
    $block = ecod_block($id);
    if (!$block || !array_key_exists($key, $block['params'])) {
        return $default;
    }
    $value = $block['params'][$key];
    return ($value === '' || $value === null) ? $default : $value;
}

/**
 * 当前正在渲染的块（模板内部使用）
 */
function ecod_block_current(): ?array
{
    return $GLOBALS['ecod_block_current'] ?? null;
}

/**
 * 当前块的参数（模板内部简写）
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
 * 块模板文件路径
 */
function ecod_block_file(array $block): string
{
    $def  = $block['def'];
    $area = (string) ($block['area'] ?? $def['area']);
    /* 跨区域显示时优先找该区域的模板，没有才回落到声明里的默认模板 */
    $candidates = [];
    if ($def['file'] !== '') {
        $candidates[] = $def['file'];
    }
    $candidates[] = 'components/' . $area . '/' . $block['id'] . '.php';
    if ($area !== $def['area']) {
        $candidates[] = 'components/' . $def['area'] . '/' . $block['id'] . '.php';
    }

    foreach (array_unique($candidates) as $rel) {
        $path = dirname(__DIR__) . '/' . ltrim($rel, '/');
        if (is_file($path)) {
            return $path;
        }
    }

    return '';
}

/**
 * 渲染单个块（内部使用）
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
 * 渲染指定 id 的块（页脚浮动控件这类需要单独摆放的块）
 */
function ecod_block_render(string $id, $widget = null): void
{
    $block = ecod_block($id);
    if ($block) {
        ecod_render_block($block, $widget);
    }
}

/**
 * 渲染一个区域（按顺序、仅启用的块）
 *
 * @param string      $area   区域名
 * @param object|null $widget Typecho 组件，绑定为模板里的 $this
 * @param string      $anchor 只渲染声明了该锚点的块（如文章页顶部块）；留空渲染未锚定的块
 */
function ecod_blocks_render(string $area, $widget = null, string $anchor = ''): void
{
    foreach (ecod_blocks_area($area) as $block) {
        if (($block['def']['anchor'] ?? '') !== $anchor) {
            continue;
        }
        ecod_render_block($block, $widget);
    }
}

/**
 * 后台管理用的数据快照
 */
function ecod_blocks_admin_data(): array
{
    $defs = [];
    foreach (ecod_block_defs() as $id => $def) {
        $defs[$id] = [
            'id'     => $id,
            'name'   => $def['name'],
            'desc'   => $def['desc'],
            'icon'   => $def['icon'],
            'area'   => $def['area'],
            'order'  => (int) ($def['order'] ?? 100),
            'on'     => (bool) $def['on'],
            'fixed'  => !empty($def['fixed']),
            'required' => !empty($def['required']),
            'areas'  => ecod_block_allowed_areas($def),
            'params' => array_map(static function ($p) {
                return [
                    'label'   => $p['label'] ?? '',
                    'type'    => $p['type'] ?? 'text',
                    'default' => $p['default'] ?? '',
                    'desc'    => $p['desc'] ?? '',
                    'options' => $p['options'] ?? [],
                    'min'     => $p['min'] ?? null,
                    'max'     => $p['max'] ?? null,
                    'unit'    => $p['unit'] ?? '',
                ];
            }, $def['params'] ?? []),
        ];
    }

    $areas = [];
    $place = [];
    foreach (ecod_blocks_area_keys() as $area) {
        $areas[$area] = [];
        foreach (ecod_blocks_area($area) as $block) {
            $place[$block['id']] = $block['place'];
            $areas[$area][] = [
                'id'     => $block['id'],
                'on'     => (bool) $block['on'],
                'params' => $block['params'],
            ];
        }
    }

    return ['defs' => $defs, 'areas' => $areas, 'place' => $place];
}

/**
 * 区域键名列表
 */
function ecod_blocks_area_keys(): array
{
    return ['home', 'aside', 'post', 'footer'];
}

/**
 * 把当前状态序列化为可保存的 JSON
 */
function ecod_blocks_encode(array $areas): string
{
    $out = [];
    $place = is_array($areas['place'] ?? null) ? $areas['place'] : [];

    foreach (ecod_blocks_area_keys() as $area) {
        $out[$area] = [];
        foreach (($areas[$area] ?? []) as $row) {
            $id = (string) ($row['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $out[$area][] = [
                'id'     => $id,
                'on'     => !empty($row['on']) ? 1 : 0,
                'params' => is_array($row['params'] ?? null) ? $row['params'] : [],
            ];
        }
    }

    if ($place) {
        $out['place'] = $place;
    }

    return (string) json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
