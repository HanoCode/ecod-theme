<?php
/**
 * 文章页块 · 相关推荐
 *
 * 相关度由 core/content/related.php 计算：同标签命中优先，同分类补齐，
 * 仍不足时用最近发布兜底，因此这个区域不会因为「文章没有标签」而消失。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodRelCount = max(1, min(8, (int) ecod_p('count', 3)));

$this->widget(
    'EcodPostQuery@related',
    'pageSize=' . $ecodRelCount . '&related=' . (int) $this->cid
)->to($ecodRel);

if (!$ecodRel->have()) {
    return;
}

/* 当前文章的分类与标签名，用于给卡片标注「同标签 / 同分类」 */
$ecodRelCats = [];
if (!empty($this->categories)) {
    foreach ($this->categories as $ecodRelOne) {
        $ecodRelCats[] = is_array($ecodRelOne) ? (string) $ecodRelOne['name'] : (string) $ecodRelOne->name;
    }
}
$ecodRelTags = [];
if (!empty($this->tags)) {
    foreach ($this->tags as $ecodRelOne) {
        $ecodRelTags[] = is_array($ecodRelOne) ? (string) $ecodRelOne['name'] : (string) $ecodRelOne->name;
    }
}
?>
<section class="ecod_rel" aria-labelledby="ecod-rel-title">
    <?php ecod_block_head(['title_id' => 'ecod-rel-title', 'note_raw' => '<span class="ecod_rel__hint">' . ecod_icon('bulb', 12) . '按标签与分类匹配</span>']); ?>

    <div class="ecod_rel__grid">
        <?php while ($ecodRel->next()): ?>
            <?php
            $ecodRelImg = ecod_thumbnails($ecodRel, 1, 'side')[0] ?? '';

            /* 命中原因：标签名 > 分类名，都没有就是补位的最近文章 */
            $ecodRelWhy = '';
            if (!empty($ecodRel->tags)) {
                foreach ($ecodRel->tags as $ecodRelTag) {
                    $ecodRelName = is_array($ecodRelTag) ? (string) $ecodRelTag['name'] : (string) $ecodRelTag->name;
                    if (in_array($ecodRelName, $ecodRelTags, true)) {
                        $ecodRelWhy = '#' . $ecodRelName;
                        break;
                    }
                }
            }
            if ($ecodRelWhy === '' && !empty($ecodRel->categories)) {
                foreach ($ecodRel->categories as $ecodRelCat) {
                    $ecodRelName = is_array($ecodRelCat) ? (string) $ecodRelCat['name'] : (string) $ecodRelCat->name;
                    if (in_array($ecodRelName, $ecodRelCats, true)) {
                        $ecodRelWhy = $ecodRelName;
                        break;
                    }
                }
            }
            ?>
            <a class="ecod_rel__card" href="<?php $ecodRel->permalink(); ?>">
                <span class="ecod_rel__cover"<?php if ($ecodRelImg): ?> style="--ecod-cover:url('<?php echo htmlspecialchars($ecodRelImg); ?>')"<?php endif; ?>>
                    <?php if (!$ecodRelImg): ?>
                        <span class="ecod_rel__ph"><?php echo htmlspecialchars(mb_substr((string) $ecodRel->title, 0, 1, 'UTF-8')); ?></span>
                    <?php endif; ?>
                </span>
                <span class="ecod_rel__body">
                    <?php if ($ecodRelWhy !== ''): ?>
                        <span class="ecod_rel__why"><?php echo htmlspecialchars($ecodRelWhy); ?></span>
                    <?php endif; ?>
                    <b class="ecod_rel__title"><?php $ecodRel->title(); ?></b>
                    <span class="ecod_rel__meta">
                        <time datetime="<?php $ecodRel->date('Y-m-d'); ?>"><?php $ecodRel->date('m-d'); ?></time>
                        <span><?php echo ecod_icon('comment', 12); ?><?php echo (int) $ecodRel->commentsNum; ?></span>
                        <span><?php echo ecod_icon('eye', 12); ?><?php echo ecod_num_short(ecod_views($ecodRel)); ?></span>
                    </span>
                </span>
            </a>
        <?php endwhile; ?>
    </div>
</section>
