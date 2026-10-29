<?php
/**
 * Ecod Theme · 分类 / 标签增强元数据
 *
 * Typecho 的分类 / 标签共用 metas 表，原生没有封面与独立 SEO 字段。
 * 这里提供一张 KV 表 {prefix}ecod_meta_meta（主键 mid + mkey）承载增强字段，
 * 并向原生后台编辑表单追加字段、随原生 action 同步保存。
 *
 * 本文件被核心补丁（Widget/Metas 下两个 Edit.php）在 admin action 上下文里
 * require，此上下文不加载主题 functions.php，因此这里只依赖
 * core/schema.php 与 Typecho 框架自身，任何失败一律静默降级。
 *
 * 规格：design-proposal.html §32
 *
 * @package Ecod Theme
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/* 建表函数在 schema.php（不依赖主题其他文件），缺失时现场引入 */
if (!function_exists('ecod_schema_ensure_metameta')) {
    $ecodMetaSchemaFile = __DIR__ . '/schema.php';
    if (is_file($ecodMetaSchemaFile)) {
        require_once $ecodMetaSchemaFile;
    }
    unset($ecodMetaSchemaFile);
}

/**
 * KV 表名
 */
function ecod_meta_meta_table(): string
{
    try {
        return \Typecho\Db::get()->getPrefix() . 'ecod_meta_meta';
    } catch (\Throwable $e) {
        return 'typecho_ecod_meta_meta';
    }
}

/**
 * 确保表存在（请求内只试一次，失败静默降级为「无增强字段」）
 */
function ecod_meta_meta_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    $ready = false;
    try {
        if (function_exists('ecod_schema_ensure_metameta')) {
            $ready = ecod_schema_ensure_metameta(\Typecho\Db::get());
        }
    } catch (\Throwable $e) {
        $ready = false;
    }

    return $ready;
}

/**
 * 取一个 meta 的全部增强字段（mid → [mkey => mvalue]）
 *
 * @param int $mid
 * @return array<string,string>
 */
function ecod_meta_meta_all(int $mid): array
{
    static $cache = [];
    $mid = (int) $mid;
    if ($mid <= 0) {
        return [];
    }
    if (array_key_exists($mid, $cache)) {
        return $cache[$mid];
    }

    $cache[$mid] = [];
    if (ecod_meta_meta_ready()) {
        try {
            $db = \Typecho\Db::get();
            $rows = $db->fetchAll(
                $db->select('mkey', 'mvalue')->from('table.ecod_meta_meta')
                    ->where('mid = ?', $mid)
            );
            foreach ($rows as $row) {
                $cache[$mid][(string) $row['mkey']] = (string) $row['mvalue'];
            }
        } catch (\Throwable $e) {
            $cache[$mid] = [];
        }
    }

    return $cache[$mid];
}

/**
 * 取单个增强字段
 */
function ecod_meta_meta_get(int $mid, string $key): string
{
    return (string) (ecod_meta_meta_all($mid)[$key] ?? '');
}

/**
 * 按缩略名取增强字段（前台用：Archive 不暴露 mid）
 *
 * @param string $slug
 * @param string $type category|tag
 * @return array<string,string>
 */
function ecod_meta_meta_by_slug(string $slug, string $type): array
{
    static $cache = [];
    $slug = trim($slug);
    $type = $type === 'tag' ? 'tag' : 'category';
    if ($slug === '') {
        return [];
    }
    $ck = $type . ':' . $slug;
    if (array_key_exists($ck, $cache)) {
        return $cache[$ck];
    }

    $cache[$ck] = [];
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('mid')->from('table.metas')
                ->where('type = ?', $type)->where('slug = ?', $slug)->limit(1)
        );
        if ($row) {
            $cache[$ck] = ecod_meta_meta_all((int) $row['mid']);
        }
    } catch (\Throwable $e) {
        $cache[$ck] = [];
    }

    return $cache[$ck];
}

/**
 * 当前归档页对应的 meta 增强字段（分类 / 标签页，其余页返回空）
 *
 * @param mixed $archive
 * @return array<string,string>
 */
function ecod_meta_meta_for_archive($archive): array
{
    if (!$archive) {
        return [];
    }
    try {
        if ($archive->is('category') || $archive->is('tag')) {
            $type = $archive->is('tag') ? 'tag' : 'category';
            return ecod_meta_meta_by_slug((string) $archive->getArchiveSlug(), $type);
        }
    } catch (\Throwable $e) {
    }
    return [];
}

/**
 * 给原生编辑表单追加增强字段（在 render 前调用）
 *
 * 仅编辑模式（请求带 mid）渲染；字段插到提交按钮之前。
 * 表是主题自己的，主题被删时本函数不存在，原生表单照常工作。
 *
 * @param \Typecho\Widget\Helper\Form $form
 */
function ecod_meta_extend_form(\Typecho\Widget\Helper\Form $form): void
{
    try {
        $mid = (int) \Typecho\Request::getInstance()->get('mid', 0);
        if ($mid <= 0) {
            return;
        }

        $values = ecod_meta_meta_all($mid);

        $cover = new \Typecho\Widget\Helper\Form\Element\Text(
            'ecod_cover',
            null,
            (string) ($values['cover'] ?? ''),
            _t('封面图地址'),
            _t('分类 / 标签页头与分享卡片使用，留空则用主题默认逻辑。可填站点相对路径或完整 URL。')
        );
        $form->addInput($cover);

        $seoTitle = new \Typecho\Widget\Helper\Form\Element\Text(
            'ecod_seo_title',
            null,
            (string) ($values['seo_title'] ?? ''),
            _t('SEO 标题'),
            _t('浏览器标题与 og:title 使用，留空则用「分类名 - 站点名」默认格式。')
        );
        $form->addInput($seoTitle);

        $seoDesc = new \Typecho\Widget\Helper\Form\Element\Textarea(
            'ecod_seo_desc',
            null,
            (string) ($values['seo_desc'] ?? ''),
            _t('SEO 描述'),
            _t('meta description 与 og:description 使用，留空则用分类描述。建议 120 字以内。')
        );
        $form->addInput($seoDesc);

        $seoKeywords = new \Typecho\Widget\Helper\Form\Element\Text(
            'ecod_seo_keywords',
            null,
            (string) ($values['seo_keywords'] ?? ''),
            _t('SEO 关键词'),
            _t('多个关键词用英文逗号分隔，留空则不输出。')
        );
        $form->addInput($seoKeywords);

        $noindex = new \Typecho\Widget\Helper\Form\Element\Select(
            'ecod_noindex',
            ['0' => _t('允许收录'), '1' => _t('不收录（noindex）')],
            (string) ($values['noindex'] ?? '0'),
            _t('搜索引擎收录'),
            _t('稀疏标签或聚合页建议关闭收录，避免重复内容影响站点质量。')
        );
        $form->addInput($noindex);

        /* 把提交按钮挪到最后：addInput 追加在 items 末尾，需先找到 Submit 再重排 */
        $submitEl = null;
        foreach ($form->getItems() as $item) {
            if ($item instanceof \Typecho\Widget\Helper\Form\Element\Submit) {
                $submitEl = $item;
            }
        }
        if ($submitEl !== null) {
            $form->removeItem($submitEl);
            $form->addItem($submitEl);
        }
    } catch (\Throwable $e) {
        /* 任何异常都不影响原生表单 */
    }
}

/**
 * 从请求中取增强字段并保存（由核心补丁在 insert / update 后调用）
 *
 * 请求里没有 ecod_ 字段时（未打渲染补丁的原生提交）不做任何事；
 * 字段值为空时删除对应行，实现「清空即清除」。
 *
 * @param int $mid
 */
function ecod_meta_save_from_request(int $mid): void
{
    $mid = (int) $mid;
    if ($mid <= 0 || !ecod_meta_meta_ready()) {
        return;
    }

    try {
        $request = \Typecho\Request::getInstance();

        /* 原生提交（没渲染增强字段）不含任何 ecod_ 键，直接跳过 */
        if (!$request->is('ecod_cover') && !$request->is('ecod_seo_title')
            && !$request->is('ecod_seo_desc') && !$request->is('ecod_seo_keywords')
            && !$request->is('ecod_noindex')) {
            return;
        }

        $clean = static function (string $raw, int $max): string {
            $text = trim(strip_tags($raw));
            $text = str_replace(["\r", "\n", "\t"], ' ', $text);
            $text = preg_replace('/ {2,}/', ' ', $text) ?? $text;
            return mb_substr($text, 0, $max, 'UTF-8');
        };

        $rows = [
            'cover'        => $clean((string) $request->get('ecod_cover', ''), 500),
            'seo_title'    => $clean((string) $request->get('ecod_seo_title', ''), 200),
            'seo_desc'     => $clean((string) $request->get('ecod_seo_desc', ''), 500),
            'seo_keywords' => $clean((string) $request->get('ecod_seo_keywords', ''), 200),
            'noindex'      => (string) $request->get('ecod_noindex', '0') === '1' ? '1' : '',
        ];

        $db = \Typecho\Db::get();
        $table = 'table.ecod_meta_meta';

        foreach ($rows as $key => $value) {
            $db->query($db->delete($table)->where('mid = ?', $mid)->where('mkey = ?', $key));
            if ($value !== '') {
                $db->query($db->insert($table)->rows([
                    'mid'    => $mid,
                    'mkey'   => $key,
                    'mvalue' => $value,
                ]));
            }
        }
    } catch (\Throwable $e) {
        /* 保存失败不影响原生保存流程 */
    }
}

/**
 * 删除一个 meta 的全部增强字段（原生删除分类 / 标签时顺带清理）
 *
 * @param int|array $mids
 */
function ecod_meta_delete($mids): void
{
    if (!ecod_meta_meta_ready()) {
        return;
    }

    $mids = array_filter(array_map('intval', (array) $mids));
    if (!$mids) {
        return;
    }

    try {
        $db = \Typecho\Db::get();
        /* $mids 已全部 intval，直接拼 IN 列表（Query 对数组占位符支持不稳） */
        $db->query($db->delete('table.ecod_meta_meta')
            ->where('mid IN (' . implode(',', $mids) . ')'));
    } catch (\Throwable $e) {
    }
}
