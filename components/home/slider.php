<?php
/**
 * 首页模块 · M3 轮播
 *
 * 两种来源：
 *   1. 自定义幻灯片（后台逐条填 图片 / 标题 / 副标题 / 链接），填了优先用
 *   2. 自动取文章（置顶或最新），没有自定义条目时兜底
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/* ---------- 自定义幻灯片 ---------- */
$ecodSlides = [];
$ecodSlidesRaw = trim((string) ecod_p('slides', ''));
if ($ecodSlidesRaw !== '') {
    $ecodDecoded = json_decode($ecodSlidesRaw, true);
    if (is_array($ecodDecoded)) {
        foreach ($ecodDecoded as $ecodSlide) {
            if (!is_array($ecodSlide)) {
                continue;
            }
            $ecodTitle = trim((string) ($ecodSlide['title'] ?? ''));
            $ecodImage = trim((string) ($ecodSlide['image'] ?? ''));
            if ($ecodTitle === '' && $ecodImage === '') {
                continue;
            }
            $ecodSlides[] = [
                'title' => $ecodTitle,
                'desc'  => trim((string) ($ecodSlide['desc'] ?? '')),
                'link'  => trim((string) ($ecodSlide['link'] ?? '')),
                'image' => $ecodImage,
            ];
        }
    }
}

if ($ecodSlides) {
    ?>
    <div class="ecod_sliderwrap">
        <span class="ecod_sliderwrap__glow" aria-hidden="true"></span>
        <section class="ecod_slider" data-ecod-slider aria-label="推荐轮播">
            <div class="ecod_slider__track" data-ecod-slider-track>
                <?php foreach ($ecodSlides as $ecodIndex => $ecodSlide): ?>
                    <?php
                    $ecodTag = $ecodSlide['link'] !== '' ? 'a' : 'div';
                    $ecodAttrs = $ecodSlide['link'] !== ''
                        ? ' href="' . htmlspecialchars($ecodSlide['link'], ENT_QUOTES, 'UTF-8') . '"'
                        : '';
                    $ecodStyle = $ecodSlide['image'] !== ''
                        ? ' style="--ecod-cover:url(\'' . htmlspecialchars($ecodSlide['image'], ENT_QUOTES, 'UTF-8') . '\')"'
                        : '';
                    $ecodClass = 'ecod_slider__slide'
                        . ($ecodIndex === 0 ? ' is-active' : '')
                        . ($ecodSlide['image'] === '' ? ' is-plain' : '');
                    ?>
                    <<?php echo $ecodTag; ?> class="<?php echo $ecodClass; ?>"<?php echo $ecodAttrs . $ecodStyle; ?>>
                        <span class="ecod_slider__shade" aria-hidden="true"></span>
                        <span class="ecod_slider__body">
                            <span class="ecod_slider__badge"><?php echo ecod_icon('star', 11); ?>推荐</span>
                            <?php if ($ecodSlide['title'] !== ''): ?>
                                <b class="ecod_slider__title"><?php echo htmlspecialchars($ecodSlide['title'], ENT_QUOTES, 'UTF-8'); ?></b>
                            <?php endif; ?>
                            <?php if ($ecodSlide['desc'] !== ''): ?>
                                <span class="ecod_slider__meta"><?php echo htmlspecialchars($ecodSlide['desc'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </span>
                    </<?php echo $ecodTag; ?>>
                <?php endforeach; ?>
            </div>

            <?php if (count($ecodSlides) > 1): ?>
                <div class="ecod_slider__dots" data-ecod-slider-dots>
                    <?php for ($i = 0; $i < count($ecodSlides); $i++): ?>
                        <button type="button" class="<?php echo $i === 0 ? 'is-active' : ''; ?>" data-index="<?php echo $i; ?>" aria-label="第 <?php echo $i + 1; ?> 张"></button>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
    <?php
    return;
}

/* ---------- 自动取文章 ---------- */
$ecodSliderCount = max(1, (int) ecod_p('count', 3));
$ecodSliderSource = (string) ecod_p('source', 'pin');
$ecodPinned = $ecodSliderSource === 'pin' ? ecod_pinned_cids($ecodSliderCount) : [];
if ($ecodPinned) {
    $this->widget('EcodPostQuery@slider', 'pageSize=' . count($ecodPinned) . '&cids=' . implode(',', $ecodPinned))->to($ecodSlider);
} else {
    $this->widget('EcodPostQuery@slider', 'pageSize=' . $ecodSliderCount)->to($ecodSlider);
}
if (!$ecodSlider->have()) {
    return;
}
?>
<div class="ecod_sliderwrap">
    <span class="ecod_sliderwrap__glow" aria-hidden="true"></span>
    <section class="ecod_slider" data-ecod-slider>
    <div class="ecod_slider__track" data-ecod-slider-track>
        <?php $ecodSlideIndex = 0; while ($ecodSlider->next()): ?>
            <?php
            $ecodImgs = ecod_thumbnails($ecodSlider, 1, 'banner');
            $ecodCover = $ecodImgs[0] ?? '';
            ?>
            <a class="ecod_slider__slide<?php echo $ecodSlideIndex === 0 ? ' is-active' : ''; ?>"
               href="<?php $ecodSlider->permalink(); ?>"
               <?php if ($ecodCover): ?>style="--ecod-cover:url('<?php echo htmlspecialchars($ecodCover); ?>')"<?php endif; ?>>
                <span class="ecod_slider__shade" aria-hidden="true"></span>
                <span class="ecod_slider__body">
                    <span class="ecod_slider__badge"><?php echo ecod_icon('star', 11); ?>精选</span>
                    <b class="ecod_slider__title"><?php $ecodSlider->title(); ?></b>
                    <span class="ecod_slider__meta">
                        <time datetime="<?php $ecodSlider->date('Y-m-d'); ?>"><?php $ecodSlider->date('m 月 d 日'); ?></time>
                        <span><?php echo ecod_icon('eye', 12); ?><?php echo ecod_num_short(ecod_views($ecodSlider)); ?></span>
                        <span><?php echo ecod_icon('comment', 12); ?><?php echo (int) $ecodSlider->commentsNum; ?></span>
                    </span>
                </span>
            </a>
            <?php $ecodSlideIndex++; ?>
        <?php endwhile; ?>
    </div>

    <?php if ($ecodSlideIndex > 1): ?>
        <div class="ecod_slider__dots" data-ecod-slider-dots>
            <?php for ($i = 0; $i < $ecodSlideIndex; $i++): ?>
                <button type="button" class="<?php echo $i === 0 ? 'is-active' : ''; ?>" data-index="<?php echo $i; ?>" aria-label="第 <?php echo $i + 1; ?> 张"></button>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
    </section>
</div>
