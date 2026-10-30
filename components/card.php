<?php
/**
 * 文章卡片组件
 *
 * 用法：ecod_card($this) / ecod_card($this, 'grid')
 * 模式：default | feature | gallery | bare | grid | title | compact | mosaic | item
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

if (!function_exists('ecod_card')) {
    /**
     * 占位图渐变色（按 cid 稳定取色）
     */
    function ecod_card_tint($cid): string
    {
        static $tints = [
            'linear-gradient(135deg, #93a5f5, #c3b3f0 55%, #f0b6d8)',
            'linear-gradient(135deg, #7fc9f2, #93a5f5 55%, #c3b3f0)',
            'linear-gradient(135deg, #f2a9a0, #f2c894 55%, #f5e3a8)',
            'linear-gradient(135deg, #8fd8c0, #9fd0f0 55%, #c3b3f0)',
            'linear-gradient(135deg, #f0a8c8, #c3a0f0 55%, #93a5f5)',
            'linear-gradient(135deg, #a8d8a0, #9fe0d0 55%, #c0ecf0)',
        ];
        return $tints[((int) $cid) % count($tints)];
    }

    /**
     * @param \Widget\Base\Contents $archive
     * @param string|null           $mode
     */
    function ecod_card($archive, ?string $mode = null): void
    {
        /* 同一列表只预取一次点赞数，避免每张卡一条 SQL */
        ecod_cards_preload($archive);

        $mode = $mode ?: ecod_card_mode($archive);
        $images = ecod_thumbnails($archive, 3, 'cover');
        /* 注意：Contents 的 __call 会直接 echo，这里必须用属性取值 */
        $permalink = (string) $archive->permalink;
        $rawTitle = (string) $archive->title;

        /* 列表页主循环的卡片记入 ItemList，页脚统一输出 JSON-LD */
        if (function_exists('ecod_itemlist_push') && $archive instanceof \Widget\Archive) {
            ecod_itemlist_push($rawTitle, $permalink);
        }
        $titleAttr = htmlspecialchars($rawTitle, ENT_QUOTES, 'UTF-8');
        $title = $titleAttr;
        $views = ecod_views($archive);
        $comments = (int) $archive->commentsNum;
        $created = (int) $archive->created;
        $category = '';
        $categoryUrl = '';
        if (!empty($archive->categories)) {
            $firstCat = $archive->categories[0];
            $name = is_array($firstCat) ? ($firstCat['name'] ?? '') : ($firstCat->name ?? '');
            $category = htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8');
            $catUrl = is_array($firstCat) ? ($firstCat['permalink'] ?? '') : ($firstCat->permalink ?? '');
            if ($catUrl !== '') {
                $categoryUrl = htmlspecialchars((string) $catUrl, ENT_QUOTES, 'UTF-8');
            }
        }
        /* 标签（最多取 2 个） */
        $tags = [];
        if (!empty($archive->tags)) {
            foreach (array_slice((array) $archive->tags, 0, 2) as $tag) {
                $tagName = is_array($tag) ? ($tag['name'] ?? '') : ($tag->name ?? '');
                $tagUrl = is_array($tag) ? ($tag['permalink'] ?? '') : ($tag->permalink ?? '');
                if ($tagName !== '' && $tagUrl !== '') {
                    $tags[] = '<a class="ecod_card__meta-tag" href="'
                        . htmlspecialchars((string) $tagUrl, ENT_QUOTES, 'UTF-8') . '">'
                        . htmlspecialchars((string) $tagName, ENT_QUOTES, 'UTF-8') . '</a>';
                }
            }
        }
        $excerpt = htmlspecialchars(ecod_excerpt($archive), ENT_QUOTES, 'UTF-8');
        $date = date('m-d', $created);
        $fullDate = date('Y-m-d', $created);
        $tint = ecod_card_tint($archive->cid ?? 0);
        /* 是否加密：与访客身份无关，始终标记 */
        $locked = ecod_has_password($archive);

        /* 搜索页标题高亮 */
        $searchKw = trim((string) ($GLOBALS['ecodSearchKw'] ?? ''));
        if ($searchKw !== '') {
            $title = ecod_highlight((string) $archive->title, $searchKw);
            /* 摘要换成「关键词附近」的命中片段（无命中时函数内部自动退回普通摘要） */
            $searchTerms = (array) ($GLOBALS['ecodSearchTerms'] ?? []);
            if ($searchTerms) {
                $excerpt = ecod_search_snippet($archive, $searchTerms);
            }
        }

        /* 标题式：极简一行 */
        if ($mode === 'title') {
            echo '<a class="ecod_titem' . ($locked ? ' is-locked' : '') . '" href="' . $permalink . '">';
            echo '<span class="ecod_titem__dot"></span>';
            echo '<span class="ecod_titem__title">' . $title . '</span>';
            if ($locked) {
                echo ecod_lock_icon(11, 'ecod_titem__lock');
            }
            echo '<time class="ecod_titem__date" datetime="' . $fullDate . '">' . $date . '</time>';
            echo '</a>';
            return;
        }

        /* 紧凑行卡：无摘要，只留封面 + 标题 + 分类/日期；封面左右交替交给 CSS */
        if ($mode === 'compact') {
            $isNew = (time() - $created) < 7 * 86400;
            echo '<article class="ecod_card ecod_card--compact' . ($locked ? ' is-locked' : '') . '"'
                . ' data-cid="' . (int) $archive->cid . '"'
                . ' data-url="' . htmlspecialchars($permalink, ENT_QUOTES, 'UTF-8') . '"'
                . ' data-title="' . $titleAttr . '">';
            echo '<a class="ecod_card__media" href="' . $permalink . '" aria-hidden="true" tabindex="-1" style="background:' . $tint . '">';
            if (!empty($images)) {
                echo '<img src="' . htmlspecialchars($images[0], ENT_QUOTES, 'UTF-8') . '" alt="' . $titleAttr . '"'
                    . ' width="160" height="112" loading="lazy" decoding="async" data-ecod-img>';
            } else {
                echo '<span class="ecod_card__placeholder">' . mb_substr($rawTitle, 0, 1, 'UTF-8') . '</span>';
            }
            echo '</a>';
            echo '<div class="ecod_card__body">';
            echo '<h3 class="ecod_card__title">';
            if ($isNew) {
                echo '<span class="ecod_card__newdot" title="最近 7 天发布"></span>';
            }
            echo '<a href="' . $permalink . '">' . ($locked ? ecod_lock_icon(12, 'ecod_card__lock-i') : '') . $title . '</a></h3>';
            echo '<div class="ecod_card__meta">';
            if ($category !== '') {
                echo $categoryUrl !== ''
                    ? '<a class="ecod_card__meta-cat" href="' . $categoryUrl . '">' . $category . '</a>'
                    : '<span class="ecod_card__meta-cat">' . $category . '</span>';
            }
            echo ecod_topic_chip($archive);
            echo '<time datetime="' . $fullDate . '">' . $date . '</time>';
            echo '<span>' . ecod_icon('eye', 12) . ecod_num_short($views) . '</span>';
            echo '</div>';
            echo '</div>';
            echo '</article>';
            return;
        }

        /* 清单 / 装备卡：价格、规格、评分从自定义字段读取，缺字段时自然降级 */
        if ($mode === 'item') {
            $f = $archive->fields ?? null;
            $priceRaw = $f && isset($f->price) ? trim((string) $f->price) : '';
            $specRaw = $f && isset($f->spec) ? trim((string) $f->spec) : '';
            $rating = $f && isset($f->rating) ? (float) $f->rating : 0;
            if ($rating > 5) {
                $rating = 5;
            }
            $buyUrl = $f && isset($f->buy) ? trim((string) $f->buy) : '';
            if ($buyUrl !== '' && !preg_match('#^https?://#i', $buyUrl)) {
                $buyUrl = '';
            }
            $specs = [];
            if ($specRaw !== '') {
                $specs = array_slice(array_filter(array_map('trim', preg_split('/[,，、|｜]/u', $specRaw))), 0, 4);
            }
            /* 纯数字价格补 ¥ 并把整数与小分开，便于排版 */
            $priceHtml = '';
            if ($priceRaw !== '') {
                if (preg_match('/^\d+(\.\d+)?$/', $priceRaw)) {
                    $priceHtml = '<i>¥</i><b>' . $priceRaw . '</b>';
                } else {
                    $priceHtml = '<b class="is-text">' . htmlspecialchars($priceRaw, ENT_QUOTES, 'UTF-8') . '</b>';
                }
            }
            echo '<article class="ecod_card ecod_card--item' . ($locked ? ' is-locked' : '') . '"'
                . ' data-cid="' . (int) $archive->cid . '"'
                . ' data-url="' . htmlspecialchars($permalink, ENT_QUOTES, 'UTF-8') . '"'
                . ' data-title="' . $titleAttr . '">';
            echo '<a class="ecod_card__media" href="' . $permalink . '" aria-hidden="true" tabindex="-1" style="background:' . $tint . '">';
            echo ecod_badges($archive);
            if (!empty($images)) {
                echo '<img src="' . htmlspecialchars($images[0], ENT_QUOTES, 'UTF-8') . '" alt="' . $titleAttr . '"'
                    . ' width="400" height="280" loading="lazy" decoding="async" data-ecod-img>';
            } else {
                echo '<span class="ecod_card__placeholder">' . mb_substr($rawTitle, 0, 1, 'UTF-8') . '</span>';
            }
            echo '</a>';
            echo '<div class="ecod_card__body">';
            echo '<h3 class="ecod_card__title"><a href="' . $permalink . '">'
                . ($locked ? ecod_lock_icon(12, 'ecod_card__lock-i') : '') . $title . '</a></h3>';
            if ($excerpt !== '') {
                echo '<p class="ecod_card__excerpt">' . $excerpt . '</p>';
            }
            if ($specs) {
                echo '<div class="ecod_card__specs">';
                foreach ($specs as $specItem) {
                    echo '<span class="ecod_card__spec">' . htmlspecialchars((string) $specItem, ENT_QUOTES, 'UTF-8') . '</span>';
                }
                echo '</div>';
            }
            if ($priceHtml !== '' || $rating > 0 || $buyUrl !== '') {
                echo '<div class="ecod_card__buyrow">';
                if ($priceHtml !== '') {
                    echo '<span class="ecod_card__price">' . $priceHtml . '</span>';
                }
                if ($rating > 0) {
                    $r = max(0, min(100, $rating / 5 * 100));
                    echo '<span class="ecod_card__rating" aria-label="' . rtrim(rtrim(number_format($rating, 1, '.', ''), '0'), '.') . ' 分">'
                        . '<span class="ecod_card__stars" style="--r:' . round($r, 1) . '%">★★★★★</span>'
                        . '<b>' . rtrim(rtrim(number_format($rating, 1, '.', ''), '0'), '.') . '</b></span>';
                }
                echo '<span class="ecod_card__buyspacer"></span>';
                if ($buyUrl !== '') {
                    echo '<a class="ecod_card__buy" href="' . htmlspecialchars($buyUrl, ENT_QUOTES, 'UTF-8') . '"'
                        . ' target="_blank" rel="noopener nofollow">查看' . ecod_icon('arrow-up-right', 11) . '</a>';
                }
                echo '</div>';
            }
            echo '<div class="ecod_card__meta">';
            if ($category !== '') {
                echo $categoryUrl !== ''
                    ? '<a class="ecod_card__meta-cat" href="' . $categoryUrl . '">' . $category . '</a>'
                    : '<span class="ecod_card__meta-cat">' . $category . '</span>';
            }
            echo ecod_topic_chip($archive);
            echo '<time datetime="' . $fullDate . '">' . $date . '</time>';
            echo '<span>' . ecod_icon('eye', 12) . ecod_num_short($views) . '</span>';
            echo '</div>';
            echo '</div>';
            echo '</article>';
            return;
        }

        $hasThumb = !empty($images);

        /* 首屏第一张封面走 eager + 高优先级，其余交给浏览器懒加载；
           两者都带 width/height，避免图片到位后撑开布局 */
        static $imgSeq = 0;
        $isFirstImg = $hasThumb && $imgSeq === 0;
        if ($hasThumb) {
            $imgSeq++;
        }

        $classes = ['ecod_card', 'ecod_card--' . $mode];
        if ($locked) {
            $classes[] = 'is-locked';
        }
        /* 缩略图位置：default / feature 两种横向模式支持左图或右图 */
        if (in_array($mode, ['default', 'feature'], true) && ecod_thumb_side($archive) === 'left') {
            $classes[] = 'ecod_card--media-left';
        }
        if (!$hasThumb && in_array($mode, ['default', 'feature', 'grid', 'mosaic'], true)) {
            $classes[] = 'is-nothumb';
        }

        echo '<article class="' . implode(' ', $classes) . '" data-cid="' . (int) $archive->cid . '"'
            . ' data-url="' . htmlspecialchars($permalink, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-title="' . $titleAttr . '">';

        /* 缩略图区 */
        if (in_array($mode, ['default', 'feature', 'grid', 'mosaic'], true)) {
            /* 渐变色块既是占位图也是加载中的低清替代（图片到位后直接盖住） */
            echo '<a class="ecod_card__media" href="' . $permalink . '" aria-hidden="true" tabindex="-1" style="background:' . $tint . '">';
            echo ecod_badges($archive);
            if ($category !== '') {
                echo '<span class="ecod_card__cat">' . $category . '</span>';
            }
            if ($hasThumb) {
                echo '<img src="' . htmlspecialchars($images[0], ENT_QUOTES, 'UTF-8') . '" alt="' . $titleAttr . '"'
                    . ' width="400" height="264"'
                    . ($isFirstImg ? ' loading="eager" fetchpriority="high"' : ' loading="lazy"')
                    . ' decoding="async" data-ecod-img>';
            } else {
                echo '<span class="ecod_card__placeholder">' . mb_substr($title, 0, 1, 'UTF-8') . '</span>';
            }
            echo '</a>';
        }

        echo '<div class="ecod_card__body">';

        /* 没有缩略图的卡片没有角标位，锁标记改挂在标题前（放进链接内，避免被 line-clamp 拆行） */
        $lockInTitle = $locked && (!$hasThumb || !in_array($mode, ['default', 'feature', 'grid', 'mosaic'], true))
            ? ecod_lock_icon(12, 'ecod_card__lock-i')
            : '';

        if ($mode !== 'gallery') {
            echo '<h3 class="ecod_card__title"><a href="' . $permalink . '">' . $lockInTitle . $title . '</a></h3>';
        }

        if ($mode !== 'title' && $excerpt !== '') {
            echo '<p class="ecod_card__excerpt">' . $excerpt . '</p>';
        }

        /* 三图模式：图片在文字下方 */
        if ($mode === 'gallery') {
            echo '<h3 class="ecod_card__title"><a href="' . $permalink . '">' . $lockInTitle . $title . '</a></h3>';
            echo '<div class="ecod_card__gallery">';
            for ($i = 0; $i < 3; $i++) {
                $src = $images[$i] ?? '';
                if ($src !== '') {
                    echo '<a href="' . $permalink . '" tabindex="-1" aria-hidden="true"><img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="" width="400" height="400" loading="lazy" decoding="async" data-ecod-img></a>';
                } else {
                    echo '<span class="ecod_card__gallery-ph"></span>';
                }
            }
            echo '</div>';
        }

        /* 元信息 */
        echo '<div class="ecod_card__meta">';
        if ($category !== '') {
            echo $categoryUrl !== ''
                ? '<a class="ecod_card__meta-cat" href="' . $categoryUrl . '">' . $category . '</a>'
                : '<span class="ecod_card__meta-cat">' . $category . '</span>';
        }
        echo implode('', $tags);
        echo ecod_topic_chip($archive);
        echo '<time datetime="' . $fullDate . '">' . ecod_icon('calendar', 12) . $date . '</time>';
        echo '<span>' . ecod_icon('eye', 12) . ecod_num_short($views) . '</span>';
        echo '<a href="' . $permalink . '#comments">' . ecod_icon('comment', 12) . $comments . '</a>';
        echo '</div>';

        echo '</div>'; // body

        /* 悬浮操作组：收藏 / 点赞（点赞带数量） */
        $likeOn = ecod_option('ELike', 'on') !== 'off';
        $likeNum = $likeOn ? ecod_likes((int) $archive->cid) : 0;
        echo '<div class="ecod_card__act">'
            . '<button type="button" class="ecod_card__actbtn" data-ecod-act="fav" aria-label="收藏" aria-pressed="false">' . ecod_icon('star', 11) . '</button>'
            . ($likeOn
                ? '<button type="button" class="ecod_card__actbtn' . ($likeNum > 0 ? ' has-num' : '') . '" data-ecod-act="like"'
                    . ' data-cid="' . (int) $archive->cid . '" aria-label="点赞" aria-pressed="false">'
                    . ecod_icon('heart', 11)
                    . '<i data-ecod-like-num>' . ($likeNum > 0 ? ecod_num_short((int) $likeNum) : '') . '</i>'
                    . '</button>'
                : '')
            . '</div>';

        echo '</article>';
    }
}
