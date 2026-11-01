<?php
/**
 * Ecod Theme · 图文笔记（文章类型）
 *
 * 让单篇文章以「小红书笔记」形态呈现：竖版封面 + 图集 + 短段落 + 话题标签 + 互动条。
 * 设计真源 docs/dev/NOTE-ARTICLE-DESIGN.html。
 *
 * 约定：
 *   - 不建表。开关与标签走 Typecho 原生自定义字段（note / note_tags），
 *     图集复用 thumb 字段，空则回退正文图片与全站图池（ecod_thumbnails 已实现）。
 *   - 门禁复用：锁定 / 付费未授权时图集返回空数组，模板层不得再自取封面。
 *   - 只在文章页生效；列表卡片不读本文件的开关（列表版式由 ecod_list_force_mode 决定）。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @since   2026-11-01
 * @version 1.0.0
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 全局开关：设置 → 文章页 → 图文笔记
 */
function ecod_note_enabled(): bool
{
    return ecod_option('ENote', 'off') === 'on';
}

/**
 * 本篇是否为笔记模式（字段 note=on 且全局开关打开）
 */
function ecod_note_on($archive): bool
{
    if (!ecod_note_enabled() || !is_object($archive)) {
        return false;
    }
    $fields = $archive->fields ?? null;
    if (!$fields || !isset($fields->note)) {
        return false;
    }

    return strtolower(trim((string) $fields->note)) === 'on';
}

/**
 * 封面比例：返回 [css 值, 宽, 高]（宽高用于 img 显式尺寸，防加载跳动）
 *
 * @return array{0:string,1:int,2:int}
 */
function ecod_note_ratio(): array
{
    $ratio = (string) ecod_option('ENoteRatio', '3:4');
    switch ($ratio) {
        case '1:1':
            return ['1 / 1', 480, 480];
        case '4:5':
            return ['4 / 5', 480, 600];
        default:
            return ['3 / 4', 480, 640];
    }
}

/**
 * 九宫格形态开关：设置 → 文章页 → 图文笔记 → 图集九宫格
 *
 * 只影响「多图首屏怎么摆」：关（默认）是横滑大图，开是方格拼贴。
 * 单图不受影响——方格拼贴对单图没有意义。
 */
function ecod_note_grid_on(): bool
{
    return ecod_option('ENoteGrid', 'off') === 'on';
}

/**
 * 九宫格列数：≤4 张用 2 列（避免 3 列留空位），5 张起 3 列
 *
 * 与小红书的拼贴一致：2 张并排、3 张一行、4 张 2×2、9 张 3×3。
 */
function ecod_note_grid_cols(int $count): int
{
    return $count <= 4 ? 2 : 3;
}

/**
 * 图集上限：1–9，超出按 9 处理
 */
function ecod_note_limit(): int
{
    $n = (int) ecod_option('ENoteGallery', 9);
    if ($n < 1) {
        $n = 1;
    }
    if ($n > 9) {
        $n = 9;
    }

    return $n;
}

/**
 * 图集地址：thumb 字段 → 正文图片 → 全站图池（ecod_thumbnails 内部回退），
 * 统一走 note 裁剪预设（480×640 一档，列表笔记卡复用同一比例）。
 *
 * 锁定 / 付费未授权时返回空数组：图片本身即内容，未授权不得下发。
 */
function ecod_note_gallery($archive, int $limit = 0): array
{
    if (!is_object($archive)) {
        return [];
    }
    if ($limit <= 0) {
        $limit = ecod_note_limit();
    }

    if (!empty($archive->hidden)) {
        return [];
    }
    if (function_exists('ecod_pay_access')) {
        $pay = ecod_pay_access($archive);
        if (is_array($pay) && ($pay['mode'] ?? 'free') !== 'free' && empty($pay['allow'])) {
            return [];
        }
    }
    if (!function_exists('ecod_thumbnails')) {
        return [];
    }

    $urls = ecod_thumbnails($archive, $limit, 'note');

    return is_array($urls) ? array_values(array_filter($urls)) : [];
}

/**
 * 话题标签：[['name' => ..., 'url' => ...], ...]
 *
 * note_tags 手填优先（最多 8 个，手填项没有对应归档，url 为空）；
 * 留空则回退文章自带标签（带归档地址）。
 */
function ecod_note_tags($archive): array
{
    if (!is_object($archive)) {
        return [];
    }

    $out = [];
    $fields = $archive->fields ?? null;
    $raw = $fields && isset($fields->note_tags) ? (string) $fields->note_tags : '';
    if ($raw !== '') {
        $parts = preg_split('/[,，、\s#]+/u', $raw);
        foreach ((array) $parts as $part) {
            $name = trim((string) $part);
            if ($name === '') {
                continue;
            }
            $dup = false;
            foreach ($out as $item) {
                if ($item['name'] === $name) {
                    $dup = true;
                    break;
                }
            }
            if ($dup) {
                continue;
            }
            $out[] = ['name' => $name, 'url' => ''];
            if (count($out) >= 8) {
                break;
            }
        }
    }

    if (!$out && !empty($archive->tags)) {
        try {
            foreach ($archive->tags as $tag) {
                $name = is_array($tag) ? ($tag['name'] ?? '') : ($tag->name ?? '');
                $url = is_array($tag) ? ($tag['permalink'] ?? '') : ($tag->permalink ?? '');
                $name = trim((string) $name);
                if ($name === '') {
                    continue;
                }
                $out[] = [
                    'name' => $name,
                    'url'  => (string) $url,
                ];
                if (count($out) >= 8) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            return [];
        }
    }

    return $out;
}

/**
 * 本文真实拥有的图片（thumb 字段优先 → 正文图片），去重后按原顺序返回。
 * 不补全站图池：图池是「兜底填充」，不该算进「这篇有几张图」。
 *
 * @return string[]
 */
function ecod_note_candidates($archive): array
{
    if (!is_object($archive) || !empty($archive->hidden)) {
        return [];
    }

    $urls = [];
    $fields = $archive->fields ?? null;
    $thumb = $fields && isset($fields->thumb) ? (string) $fields->thumb : '';
    if ($thumb !== '') {
        foreach (preg_split('/\r\n|\r|\n/', $thumb) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $urls[] = $line;
            }
        }
    }
    if (!empty($archive->content) && preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', (string) $archive->content, $m)) {
        $urls = array_merge($urls, $m[1]);
    }

    return array_values(array_unique(array_filter($urls)));
}

/**
 * 图集张数（不超过上限）：列表笔记卡的「N 张」角标用，避免把兜底图池算成作者图片
 */
function ecod_note_total($archive): int
{
    return min(ecod_note_limit(), count(ecod_note_candidates($archive)));
}

/**
 * 超出图集上限的图片：thumb 字段（优先）→ 正文图片，按上限切掉前 N 张后剩下的部分。
 *
 * 用途有二：① 头图集最后一张打「+N」角标；② 查看器里补全，点开仍能翻到全部
 *（上限只是首屏展示量，不是内容裁剪）。最多取 12 张，避免超长图集把 DOM 撑爆。
 *
 * @return array{urls:string[],count:int} count 是真实超出张数（可大于 urls 条数）
 */
function ecod_note_extra($archive): array
{
    $empty = ['urls' => [], 'count' => 0];
    if (!is_object($archive) || !empty($archive->hidden)) {
        return $empty;
    }
    /* 锁定 / 付费未授权：图集都不下发，更不该下发超限图 */
    if (!ecod_note_gallery($archive)) {
        return $empty;
    }

    $urls = ecod_note_candidates($archive);
    $limit = ecod_note_limit();
    $rest = array_slice($urls, $limit);
    if (!$rest) {
        return $empty;
    }

    $count = count($rest);
    $rest = array_slice($rest, 0, 12);
    if (function_exists('ecod_thumb_url')) {
        foreach ($rest as $i => $u) {
            $rest[$i] = ecod_thumb_url($u, 'note');
        }
    }

    return ['urls' => array_values($rest), 'count' => $count];
}

/**
 * 结构化数据：SocialMediaPosting + ImageObject 列表 + 作者
 *
 * 为什么另起一段而不并进 seo.php 的 Article 图：seo.php 不认识笔记模式，
 * 强行并进去要把 note 的数据层拖进 SEO 模块；JSON-LD 允许多段，
 * 这里补的正是 Article 表达不了的「这页是一组图」。
 *
 * 只描述真实下发的图片（锁定 / 付费未授权时 ecod_note_gallery() 返回空，
 * 这里也就没有 image 字段），不会把付费内容结构化成可抓取的元信息。
 *
 * @return array 空数组表示不输出
 */
function ecod_note_ld($archive): array
{
    if (!is_object($archive) || !ecod_note_on($archive)) {
        return [];
    }

    $gallery = ecod_note_gallery($archive);
    if (!$gallery) {
        return [];
    }
    list(, $imgW, $imgH) = ecod_note_ratio();

    $url = (string) $archive->permalink;
    $title = trim((string) $archive->title);

    $images = [];
    foreach ($gallery as $i => $src) {
        $images[] = [
            '@type'      => 'ImageObject',
            'contentUrl' => (string) $src,
            'width'      => $imgW,
            'height'     => $imgH,
            'name'       => $title . ' 配图 ' . ($i + 1),
        ];
    }

    $ld = [
        '@context'      => 'https://schema.org',
        '@type'         => 'SocialMediaPosting',
        '@id'           => $url . '#note',
        'url'           => $url,
        'headline'      => mb_substr($title, 0, 110, 'UTF-8'),
        'datePublished' => date('c', (int) $archive->created),
        'dateModified'  => date('c', (int) ($archive->modified ?: $archive->created)),
        'image'         => $images,
        'inLanguage'    => 'zh-CN',
        /* 不挂 isPartOf 指向 seo.php 的 Article @id：canonical 与 permalink
           可能不同（带参 / 尾斜杠），悬空引用比不引用更糟 */
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
    ];

    /* 作者：站内主页地址即 sameAs（笔记页的作者行指向同一处） */
    $authorName = '';
    $authorUrl = '';
    try {
        $author = $archive->author;
        $authorName = trim((string) ($author->screenName ?: $author->name));
        if ($authorName !== '' && (int) ($author->uid ?? 0) > 0) {
            if (!function_exists('ecod_user_archive_url')) {
                require_once __DIR__ . '/../user/meta.php';
            }
            $authorUrl = (string) ecod_user_archive_url((int) $author->uid);
        }
    } catch (\Throwable $e) {
    }
    if ($authorName !== '') {
        $ld['author'] = ['@type' => 'Person', 'name' => $authorName];
        if ($authorUrl !== '') {
            $ld['author']['url'] = $authorUrl;
            $ld['author']['sameAs'] = [$authorUrl];
        }
    }

    /* 话题标签即 keywords（手填项没有归档地址，只给名字） */
    $names = [];
    foreach (ecod_note_tags($archive) as $tag) {
        if ((string) ($tag['name'] ?? '') !== '') {
            $names[] = (string) $tag['name'];
        }
    }
    if ($names) {
        $ld['keywords'] = implode(',', $names);
    }

    return $ld;
}

/**
 * 模板需要的全部渲染参数（一次算完，避免模板里散读选项）
 */
function ecod_note_options($archive): array
{
    list($ratioCss, $imgW, $imgH) = ecod_note_ratio();
    $width = (int) ecod_option('ENoteWidth', 640);
    if (!in_array($width, [600, 640, 720], true)) {
        $width = 640;
    }

    $bar = (string) ecod_option('ENoteBar', 'float');
    if (!in_array($bar, ['float', 'bottom', 'hide'], true)) {
        $bar = 'float';
    }

    $extra = ecod_note_extra($archive);
    /* 九宫格：总张数 ≥2 才有意义，单图继续走大图形态 */
    $gallery = ecod_note_gallery($archive);
    $grid = ecod_note_grid_on() && count($gallery) > 1;

    return [
        'gallery'    => $gallery,
        'grid'       => $grid,
        'gridCols'   => $grid ? ecod_note_grid_cols(count($gallery)) : 0,
        'extra'      => $extra['urls'],
        'extraCount' => $extra['count'],
        'tags'       => ecod_note_tags($archive),
        'tagsOn'     => ecod_option('ENoteTags', 'on') === 'on',
        'coverText'  => ecod_option('ENoteCoverText', 'off') === 'on',
        'ratioCss'   => $ratioCss,
        'imgW'       => $imgW,
        'imgH'       => $imgH,
        'width'      => $width,
        'bar'        => $bar,
    ];
}
