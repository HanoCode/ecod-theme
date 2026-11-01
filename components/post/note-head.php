<?php
/**
 * 文章页 · 图文笔记头部（标题 / 图集 / 作者行 / 元信息）
 *
 * 由 post.php 在笔记模式下 include，与模板共享 $this 与 $ecodNoteData。
 * 正文、标签、互动条、评论仍由 post.php 按原顺序输出——本文件只负责「头部外壳」。
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
if (!isset($ecodNoteData) || !is_array($ecodNoteData)) {
    return;
}

$ecodNoteGallery = (array) ($ecodNoteData['gallery'] ?? []);
$ecodNoteTotal = count($ecodNoteGallery);
$ecodNoteRatioCss = (string) ($ecodNoteData['ratioCss'] ?? '3 / 4');
$ecodNoteImgW = (int) ($ecodNoteData['imgW'] ?? 480);
$ecodNoteImgH = (int) ($ecodNoteData['imgH'] ?? 640);
$ecodNoteTitle = (string) $this->title;
/* 封面叠字：把标题压在首图底部（ENoteCoverText） */
$ecodNoteCoverText = !empty($ecodNoteData['coverText']);
/* 超出上限的图片：最后一张打「+N」，查看器里仍能翻到（ENoteGallery 只是展示量） */
$ecodNoteExtra = (array) ($ecodNoteData['extra'] ?? []);
$ecodNoteExtraCount = (int) ($ecodNoteData['extraCount'] ?? count($ecodNoteExtra));
/* 九宫格形态（ENoteGrid）：方格拼贴，点任意格开查看器 */
$ecodNoteGrid = !empty($ecodNoteData['grid']);
$ecodNoteGridCols = (int) ($ecodNoteData['gridCols'] ?? 0);
$ecodNoteAll = $ecodNoteTotal + $ecodNoteExtraCount;

/* 结构化数据：一段独立的 SocialMediaPosting（图集 + 作者），
   与 seo.php 的 Article 图并存，浏览器与爬虫都按「多段 JSON-LD」处理 */
$ecodNoteLd = function_exists('ecod_note_ld') ? ecod_note_ld($this) : [];
if ($ecodNoteLd) {
    $ecodNoteLdJson = json_encode($ecodNoteLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    if ($ecodNoteLdJson !== false) {
        echo '<script type="application/ld+json">' . $ecodNoteLdJson . '</script>' . "\n";
    }
}
?>
<header class="ecod_noteart__head">
    <h1 class="ecod_noteart__title"><?php $this->title(); ?></h1>

    <?php if ($ecodNoteTotal > 0): ?>
    <div class="ecod_noteart__gal<?php echo $ecodNoteGrid ? ' is-grid' : ($ecodNoteTotal > 1 ? ' is-multi' : ''); ?><?php echo !$ecodNoteGrid && $ecodNoteCoverText ? ' has-cover-text' : ''; ?>"
         data-ecod-note-gal
         data-count="<?php echo $ecodNoteTotal; ?>"
         data-title="<?php echo htmlspecialchars($ecodNoteTitle, ENT_QUOTES, 'UTF-8'); ?>"
         <?php if ($ecodNoteExtra): ?>data-extra="<?php echo htmlspecialchars(implode("\n", $ecodNoteExtra), ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>
         style="--ecod-note-ratio:<?php echo htmlspecialchars($ecodNoteRatioCss, ENT_QUOTES, 'UTF-8'); ?>;<?php echo $ecodNoteGrid ? '--ecod-ng-cols:' . $ecodNoteGridCols . ';' : ''; ?>">
        <?php if ($ecodNoteGrid): ?>
        <div class="ecod_noteart__grid" data-ecod-note-grid role="group"
             aria-label="图集，共 <?php echo $ecodNoteAll; ?> 张，点击查看大图">
            <?php foreach ($ecodNoteGallery as $ecodNoteI => $ecodNoteSrc): ?>
            <?php $ecodNoteIsLast = $ecodNoteI === $ecodNoteTotal - 1; ?>
            <figure class="ecod_noteart__cell">
                <img src="<?php echo htmlspecialchars((string) $ecodNoteSrc, ENT_QUOTES, 'UTF-8'); ?>"
                     alt="<?php echo htmlspecialchars($ecodNoteTitle . ' 配图 ' . ($ecodNoteI + 1), ENT_QUOTES, 'UTF-8'); ?>"
                     width="<?php echo $ecodNoteImgW; ?>" height="<?php echo $ecodNoteImgH; ?>"
                     <?php echo $ecodNoteI === 0 ? 'fetchpriority="high"' : ''; ?>
                     loading="<?php echo $ecodNoteI < 3 ? 'eager' : 'lazy'; ?>" decoding="async">
                <?php if ($ecodNoteIsLast && $ecodNoteExtraCount > 0): ?>
                    <span class="ecod_noteart__more" aria-hidden="true">+<?php echo $ecodNoteExtraCount; ?></span>
                <?php endif; ?>
                <button type="button" class="ecod_noteart__zoom" data-ecod-note-open="<?php echo $ecodNoteI; ?>"
                        aria-label="放大查看第 <?php echo $ecodNoteI + 1; ?> 张，共 <?php echo $ecodNoteAll; ?> 张"></button>
            </figure>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="ecod_noteart__track" data-ecod-note-track tabindex="0" role="group"
             aria-label="图集，共 <?php echo $ecodNoteAll; ?> 张，可左右滑动">
            <?php foreach ($ecodNoteGallery as $ecodNoteI => $ecodNoteSrc): ?>
            <?php $ecodNoteIsLast = $ecodNoteI === $ecodNoteTotal - 1; ?>
            <figure class="ecod_noteart__shot<?php echo $ecodNoteI === 0 ? ' is-first' : ''; ?><?php echo $ecodNoteIsLast && $ecodNoteExtraCount > 0 ? ' has-more' : ''; ?>">
                <img src="<?php echo htmlspecialchars((string) $ecodNoteSrc, ENT_QUOTES, 'UTF-8'); ?>"
                     alt="<?php echo htmlspecialchars($ecodNoteTitle . ' 配图 ' . ($ecodNoteI + 1), ENT_QUOTES, 'UTF-8'); ?>"
                     width="<?php echo $ecodNoteImgW; ?>" height="<?php echo $ecodNoteImgH; ?>"
                     <?php echo $ecodNoteI === 0 ? 'fetchpriority="high"' : ''; ?>
                     loading="<?php echo $ecodNoteI === 0 ? 'eager' : 'lazy'; ?>" decoding="async">
                <?php if ($ecodNoteCoverText && $ecodNoteI === 0): ?>
                    <span class="ecod_noteart__cap"><?php echo htmlspecialchars($ecodNoteTitle, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
                <?php if ($ecodNoteTotal > 1): ?>
                    <span class="ecod_noteart__num" aria-hidden="true"><?php echo ($ecodNoteI + 1) . ' / ' . $ecodNoteTotal; ?></span>
                <?php endif; ?>
                <?php if ($ecodNoteIsLast && $ecodNoteExtraCount > 0): ?>
                    <span class="ecod_noteart__more" aria-hidden="true">+<?php echo $ecodNoteExtraCount; ?></span>
                <?php endif; ?>
                <button type="button" class="ecod_noteart__zoom" data-ecod-note-open="<?php echo $ecodNoteI; ?>"
                        aria-label="放大查看第 <?php echo $ecodNoteI + 1; ?> 张，共 <?php echo $ecodNoteAll; ?> 张"></button>
            </figure>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (!$ecodNoteGrid && $ecodNoteTotal > 1): ?>
        <div class="ecod_noteart__dots" data-ecod-note-dots role="tablist" aria-label="图集切换">
            <?php for ($ecodNoteD = 0; $ecodNoteD < $ecodNoteTotal; $ecodNoteD++): ?>
                <button type="button" role="tab" data-ecod-note-dot="<?php echo $ecodNoteD; ?>"
                        class="<?php echo $ecodNoteD === 0 ? 'is-on' : ''; ?>"
                        aria-selected="<?php echo $ecodNoteD === 0 ? 'true' : 'false'; ?>"
                        aria-label="第 <?php echo $ecodNoteD + 1; ?> 张"></button>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="ecod_noteart__gal is-empty" aria-hidden="true"></div>
    <?php endif; ?>

    <?php if (!empty($ecodHeadCard)): ?>
    <div class="ecod_noteart__author">
        <span class="ecod_noteart__ava" aria-hidden="true">
            <?php if ((string) $ecodHeadCard['avatar'] !== ''): ?>
                <img src="<?php echo htmlspecialchars((string) $ecodHeadCard['avatar'], ENT_QUOTES, 'UTF-8'); ?>" alt="" width="40" height="40" loading="lazy" decoding="async">
            <?php else: ?>
                <i><?php echo htmlspecialchars(mb_substr((string) $ecodHeadCard['name'], 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></i>
            <?php endif; ?>
        </span>
        <span class="ecod_noteart__who">
            <span class="ecod_noteart__nm">
                <a href="<?php echo htmlspecialchars((string) ($ecodHeadAuthorUrl ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $ecodHeadCard['name'], ENT_QUOTES, 'UTF-8'); ?></a>
                <?php if ((string) $ecodHeadCard['group'] !== ''): ?>
                    <em><?php echo htmlspecialchars((string) $ecodHeadCard['group'], ENT_QUOTES, 'UTF-8'); ?></em>
                <?php endif; ?>
            </span>
            <span class="ecod_noteart__meta">
                <time datetime="<?php $this->date('c'); ?>"><?php $this->date('m月d日'); ?></time>
                <?php if (!empty($ecodViews)): ?><i aria-hidden="true">·</i><span><?php echo ecod_num_short((int) $ecodViews); ?> 阅读</span><?php endif; ?>
                <?php if (!empty($ecodWords)): ?><i aria-hidden="true">·</i><span><?php echo (int) $ecodMinutes; ?> 分钟</span><?php endif; ?>
            </span>
        </span>
        <?php if (!empty($ecodHeadFollow) && !empty($ecodHeadUid)) { echo ecod_user_follow_btn((int) $ecodHeadUid, ecod_viewer_uid()); } ?>
    </div>
    <?php endif; ?>
</header>
