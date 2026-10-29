<?php
/**
 * Ecod Theme · 导航菜单
 *
 * 后台「导航」分组里配置的一级 + 二级菜单（参考 WordPress 外观 → 菜单）。
 * 支持保存多套导航：整份数据存成一个 option（ENavSets 的 JSON），结构为
 * {active: 套 id, sets: [{id, name, items: [...]}]}，前后台共用同一份数据。
 *
 * 菜单为空时前台回退到旧逻辑（全部独立页面 + ENavLinks 文本链接），
 * 老站升级不做任何配置也不会白屏（回退逻辑在 functions.php 的 ecod_nav_items）。
 * 升级前只存过单份 ENavMenu 的站，读取时在内存里提升为「默认导航」一套。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 * @license   MIT
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/** 多套导航存储的 option 名 */
define('ECOD_NAV_SETS_OPTION', 'ENavSets');

/** 旧版单份菜单的 option 名（只读，用于迁移） */
define('ECOD_NAV_OPTION', 'ENavMenu');

/** 最多保存几套导航 */
define('ECOD_NAV_SETS_MAX', 10);

/** 单套最多几个一级项 */
define('ECOD_NAV_ITEM_MAX', 40);

/** 单个一级项最多展示的二级项数量上限（防止后台误配拖垮导航） */
define('ECOD_NAV_CHILD_MAX', 24);

/**
 * 允许的菜单项类型
 *
 * @return array<int, string>
 */
function ecod_nav_types(): array
{
    return ['home', 'page', 'cat', 'tag', 'link'];
}

/**
 * 清理菜单项附加的 CSS 类
 *
 * 只保留安全片段，避免后台误填把布局搞坏或注入属性。
 *
 * @param mixed $raw
 * @return string 形如 "is-vip is-wide"，最多 5 个
 */
function ecod_nav_clean_cls($raw): string
{
    $raw = (string) $raw;
    if ($raw === '') {
        return '';
    }
    $tokens = preg_split('/\s+/', $raw) ?: [];
    $out = [];
    foreach ($tokens as $token) {
        $token = preg_replace('/[^A-Za-z0-9_-]/', '', $token);
        if ($token === '' || strlen($token) > 24) {
            continue;
        }
        $out[] = $token;
        if (count($out) >= 5) {
            break;
        }
    }

    return implode(' ', $out);
}

/**
 * 归一化单个菜单项
 *
 * @param mixed $row 原始数组
 * @return array<string, mixed>|null 不合法时返回 null
 */
function ecod_nav_norm_item($row): ?array
{
    if (!is_array($row)) {
        return null;
    }

    $type = strtolower(trim((string) ($row['type'] ?? 'link')));
    if (!in_array($type, ecod_nav_types(), true)) {
        $type = 'link';
    }

    $ref   = trim((string) ($row['ref'] ?? ''));
    $label = trim((string) ($row['label'] ?? ''));
    $url   = trim((string) ($row['url'] ?? ''));

    /* link 必须有地址；page / cat / tag 必须有 ref；home 什么都不用 */
    if ($type === 'link' && $url === '') {
        return null;
    }
    if (in_array($type, ['page', 'cat', 'tag'], true) && $ref === '' && $url === '') {
        return null;
    }

    return [
        'type'     => $type,
        'ref'      => $ref,
        'label'    => $label,
        'url'      => $url,
        'blank'    => !empty($row['blank']),
        'attr'     => trim((string) ($row['attr'] ?? '')),
        'cls'      => ecod_nav_clean_cls($row['cls'] ?? ''),
        'children' => [],
    ];
}

/**
 * 把一段原始 JSON 数据归一化成两级菜单树
 *
 * @param mixed $data 解析后的数组，可以是 [item...] 或 {items:[item...]}
 * @return array<int, array<string, mixed>>
 */
function ecod_nav_norm_tree($data): array
{
    if (!is_array($data)) {
        return [];
    }
    /* 兼容 {items:[...]} 包装形态 */
    if (isset($data['items']) && is_array($data['items'])) {
        $data = $data['items'];
    }

    $out = [];
    foreach ($data as $row) {
        if (count($out) >= ECOD_NAV_ITEM_MAX) {
            break;
        }
        $item = ecod_nav_norm_item($row);
        if ($item === null) {
            continue;
        }

        $kids = [];
        $rawKids = isset($row['children']) && is_array($row['children']) ? $row['children'] : [];
        foreach ($rawKids as $kid) {
            if (count($kids) >= ECOD_NAV_CHILD_MAX) {
                break;
            }
            $child = ecod_nav_norm_item($kid);
            if ($child !== null) {
                $kids[] = $child;
            }
        }
        /* 二级项不再接受第三层：多余嵌套直接拍平丢弃 */
        $item['children'] = $kids;
        $out[] = $item;
    }

    return $out;
}

/**
 * 旧版单份菜单（ENavMenu）的内容
 *
 * @return array<int, array<string, mixed>>
 */
function ecod_nav_legacy_items(): array
{
    $raw = trim((string) ecod_option(ECOD_NAV_OPTION, ''));
    if ($raw === '') {
        return [];
    }

    return ecod_nav_norm_tree(json_decode($raw, true));
}

/**
 * 读取全部导航套 + 当前生效的套 id
 *
 * 返回结构：['active' => string, 'sets' => [['id'=>, 'name'=>, 'items'=>[...]], ...]]
 * - sets 为空：从未配置过，前台走旧版自动导航
 * - active 无效：回退到第一套
 *
 * @return array{active:string, sets:array<int, array<string, mixed>>}
 */
function ecod_nav_sets(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = ['active' => '', 'sets' => []];

    $raw = trim((string) ecod_option(ECOD_NAV_SETS_OPTION, ''));
    if ($raw !== '') {
        $data = json_decode($raw, true);
        if (is_array($data) && isset($data['sets']) && is_array($data['sets'])) {
            /* 存过 ENavSets 就以它为准，即使里面一套都没有（不再回读旧 ENavMenu） */
            $cache = ['active' => '', 'sets' => []];
            $sets = [];
            $seen = [];
            foreach ($data['sets'] as $row) {
                if (count($sets) >= ECOD_NAV_SETS_MAX) {
                    break;
                }
                if (!is_array($row)) {
                    continue;
                }
                $id = trim((string) ($row['id'] ?? ''));
                if ($id === '' || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $name = trim((string) ($row['name'] ?? ''));
                $sets[] = [
                    'id'    => $id,
                    'name'  => $name !== '' ? $name : '未命名导航',
                    'note'  => mb_substr(trim((string) ($row['note'] ?? '')), 0, 60),
                    'items' => ecod_nav_norm_tree($row['items'] ?? []),
                ];
            }

            if ($sets) {
                $active = trim((string) ($data['active'] ?? ''));
                $ids = array_column($sets, 'id');
                if ($active === '' || !in_array($active, $ids, true)) {
                    $active = $sets[0]['id'];
                }
            } else {
                $active = '';
            }
            $cache = ['active' => $active, 'sets' => $sets];
            return $cache;
        }
    }

    /* 迁移：升级前只有单份 ENavMenu，提升为「默认导航」一套（内存态，保存后才落库） */
    $legacy = ecod_nav_legacy_items();
    if ($legacy) {
        $cache = [
            'active' => 'default',
            'sets'   => [['id' => 'default', 'name' => '默认导航', 'note' => '', 'items' => $legacy]],
        ];
    }

    return $cache;
}

/**
 * 当前生效的菜单树（不含解析出的标题 / 地址）
 *
 * @return array<int, array<string, mixed>>
 */
function ecod_nav_menu_raw(): array
{
    $state = ecod_nav_sets();
    foreach ($state['sets'] as $set) {
        if ($set['id'] === $state['active']) {
            return $set['items'];
        }
    }

    return [];
}

/**
 * 后台是否配置过菜单
 *
 * 用于决定前台是否还要渲染内置的「首页」项：菜单为空时走旧版回退（内置首页 + 全部页面）。
 */
function ecod_nav_has_menu(): bool
{
    return ecod_nav_menu_raw() !== [];
}

/**
 * 取 DB 句柄（失败返回 null，调用方按空数据处理）
 *
 * @return \Typecho\Db|null
 */
function ecod_nav_db(): ?\Typecho\Db
{
    try {
        return \Typecho\Db::get();
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * 路由地址；路由表缺失时返回空串
 *
 * 必须带 prefix（options->index）：未开伪静态时 Typecho 的地址形如
 * /index.php/tag/css/，只给路由名会拼出 /tag/css/ 导致 404。
 */
function ecod_nav_route(string $name, array $params): string
{
    try {
        $prefix = (string) \Widget\Options::alloc()->index;
    } catch (\Throwable $e) {
        $prefix = null;
    }

    try {
        $url = \Typecho\Router::url($name, $params, $prefix);
        return $url === '#' ? '' : (string) $url;
    } catch (\Throwable $e) {
        return '';
    }
}

/**
 * 分类索引：mid => [name, slug, url]
 *
 * @return array<int, array<string, string>>
 */
function ecod_nav_cat_map(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];

    $db = ecod_nav_db();
    if (!$db) {
        return $cache;
    }

    try {
        $rows = $db->fetchAll(
            $db->select('mid', 'name', 'slug')
                ->from('table.metas')
                ->where('type = ?', 'category')
                ->order('order', \Typecho\Db::SORT_ASC)
                ->order('mid', \Typecho\Db::SORT_ASC)
        );
    } catch (\Throwable $e) {
        return $cache;
    }

    foreach ($rows as $row) {
        $mid  = (int) $row['mid'];
        $slug = (string) $row['slug'];
        $cache[$mid] = [
            'name' => (string) $row['name'],
            'slug' => $slug,
            'url'  => ecod_nav_route('category', ['slug' => $slug]),
        ];
    }

    return $cache;
}

/**
 * 标签索引：mid => [name, slug, url]
 *
 * @return array<int, array<string, string>>
 */
function ecod_nav_tag_map(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];

    $db = ecod_nav_db();
    if (!$db) {
        return $cache;
    }

    try {
        $rows = $db->fetchAll(
            $db->select('mid', 'name', 'slug')
                ->from('table.metas')
                ->where('type = ?', 'tag')
                ->order('mid', \Typecho\Db::SORT_ASC)
        );
    } catch (\Throwable $e) {
        return $cache;
    }

    foreach ($rows as $row) {
        $mid  = (int) $row['mid'];
        $slug = (string) $row['slug'];
        $cache[$mid] = [
            'name' => (string) $row['name'],
            'slug' => $slug,
            'url'  => ecod_nav_route('tag', ['slug' => $slug]),
        ];
    }

    return $cache;
}

/**
 * 站点根地址
 */
function ecod_nav_home_url(): string
{
    try {
        return (string) \Widget\Options::alloc()->siteUrl;
    } catch (\Throwable $e) {
        return '/';
    }
}

/**
 * 把一个菜单项解析成前台可渲染的数据
 *
 * @param array<string, mixed> $item
 * @param array<string, array<string, string>> $pageMap slug => [title, url, slug]
 * @return array<string, mixed>|null 源已删除（页面被删 / 分类被删）时返回 null
 */
function ecod_nav_resolve_item(array $item, array $pageMap): ?array
{
    $type  = (string) $item['type'];
    $ref   = (string) $item['ref'];
    $label = (string) $item['label'];
    $url   = (string) $item['url'];
    $title = $label;
    $kind  = $type;

    if ($type === 'home') {
        $url   = $url !== '' ? $url : ecod_nav_home_url();
        $title = $label !== '' ? $label : '首页';
        $ref   = '';
    } elseif ($type === 'page') {
        if ($ref !== '' && isset($pageMap[$ref])) {
            $url   = $url !== '' ? $url : (string) $pageMap[$ref]['url'];
            $title = $label !== '' ? $label : (string) $pageMap[$ref]['title'];
        } elseif ($url !== '') {
            /* 页面已删除但手工填过地址：保留链接，类型降级为普通链接 */
            $title = $label !== '' ? $label : $ref;
            $kind  = 'link';
            $ref   = '';
        } else {
            return null;
        }
    } elseif ($type === 'cat' || $type === 'tag') {
        $map = $type === 'cat' ? ecod_nav_cat_map() : ecod_nav_tag_map();
        $mid = (int) $ref;
        if ($mid > 0 && isset($map[$mid])) {
            $url   = $url !== '' ? $url : (string) $map[$mid]['url'];
            $title = $label !== '' ? $label : (string) $map[$mid]['name'];
            $ref   = (string) $map[$mid]['slug'];
        } elseif ($url !== '') {
            $title = $label !== '' ? $label : $ref;
            $kind  = 'link';
            $ref   = '';
        } else {
            return null;
        }
    }

    if ($url === '') {
        return null;
    }

    return [
        'title'    => $title !== '' ? $title : $url,
        'url'      => $url,
        'kind'     => $kind,
        'ref'      => $ref,
        'slug'     => $kind === 'page' ? $ref : '',
        'blank'    => !empty($item['blank']),
        'attr'     => (string) ($item['attr'] ?? ''),
        'cls'      => ecod_nav_clean_cls($item['cls'] ?? ''),
        'children' => [],
    ];
}

/**
 * 前台用：解析整棵菜单树
 *
 * @param array<string, array<string, string>> $pageMap
 * @return array<int, array<string, mixed>>
 */
function ecod_nav_menu_resolve(array $pageMap): array
{
    $tree = ecod_nav_menu_raw();
    if (!$tree) {
        return [];
    }

    $out = [];
    foreach ($tree as $item) {
        $resolved = ecod_nav_resolve_item($item, $pageMap);
        if ($resolved === null) {
            continue;
        }

        $kids = [];
        foreach ($item['children'] as $kid) {
            $child = ecod_nav_resolve_item($kid, $pageMap);
            if ($child !== null) {
                $kids[] = $child;
            }
        }
        $resolved['children'] = $kids;
        $out[] = $resolved;
    }

    return $out;
}

/**
 * 当前归档是否命中某个菜单项
 *
 * @param mixed $archive Typecho 归档对象
 * @param array<string, mixed> $item
 */
function ecod_nav_item_is_active($archive, array $item): bool
{
    if (!$archive) {
        return false;
    }

    $kind = (string) ($item['kind'] ?? '');
    $ref  = (string) ($item['ref'] ?? '');

    try {
        if ($kind === 'home') {
            return (bool) $archive->is('index');
        }
        if ($ref === '') {
            return false;
        }
        if ($kind === 'page') {
            return (bool) $archive->is('page', $ref);
        }
        if ($kind === 'cat') {
            return (bool) $archive->is('category', $ref);
        }
        if ($kind === 'tag') {
            return (bool) $archive->is('tag', $ref);
        }
    } catch (\Throwable $e) {
        return false;
    }

    return false;
}

/**
 * 后台用：可选来源（独立页面 / 分类 / 标签）
 *
 * @return array{pages: array<int, array<string, mixed>>, cats: array<int, array<string, mixed>>, tags: array<int, array<string, mixed>>}
 */
function ecod_nav_sources(): array
{
    $out = ['pages' => [], 'cats' => [], 'tags' => []];

    $db = ecod_nav_db();
    if (!$db) {
        return $out;
    }

    try {
        $rows = $db->fetchAll(
            $db->select('cid', 'title', 'slug')
                ->from('table.contents')
                ->where('type = ?', 'page')
                ->where('status = ?', 'publish')
                ->order('order', \Typecho\Db::SORT_ASC)
                ->order('cid', \Typecho\Db::SORT_ASC)
        );
        foreach ($rows as $row) {
            $slug = (string) $row['slug'];
            $out['pages'][] = [
                'ref'   => $slug,
                'label' => (string) $row['title'],
                'url'   => ecod_nav_route('page', ['slug' => $slug]),
            ];
        }
    } catch (\Throwable $e) {
    }

    foreach (ecod_nav_cat_map() as $mid => $cat) {
        $out['cats'][] = [
            'ref'   => (string) $mid,
            'label' => $cat['name'],
            'url'   => $cat['url'],
        ];
    }

    foreach (ecod_nav_tag_map() as $mid => $tag) {
        $out['tags'][] = [
            'ref'   => (string) $mid,
            'label' => $tag['name'],
            'url'   => $tag['url'],
        ];
    }

    return $out;
}
