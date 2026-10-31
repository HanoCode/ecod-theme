<?php
/**
 * 首页模块 · M5 热门（四宫格 / 排行列表两种版式，原 popular 已并入）
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$ecodHotSortRaw = (string) ecod_p('sort', 'comments');
$ecodHotSort = in_array($ecodHotSortRaw, ['views', 'weighted', 'rand'], true) ? $ecodHotSortRaw : 'hot';
$this->widget('EcodPostQuery@hot', 'pageSize=' . max(2, (int) ecod_p('count', 4)) . '&order=' . $ecodHotSort)->to($ecodHot);
if (!$ecodHot->have()) {
    return;
}
$ecodHotNotes = ['views' => '按阅读量', 'weighted' => '按加权热度', 'rand' => '随机推荐', 'hot' => '按讨论热度'];
$ecodHotStyle = (string) ecod_p('style', 'grid') === 'rank' ? 'rank' : 'grid';
?>
<?php if ($ecodHotStyle === 'rank'): ?>
<section class="ecod_hot ecod_hot--rank" aria-label="热门文章">
    <?php ecod_block_head(['note' => $ecodHotNotes[$ecodHotSort]]); ?>
    <div class="ecod_rank">
        <?php $ecodHotIndex = 1; while ($ecodHot->next()): ?>
            <a href="<?php $ecodHot->permalink(); ?>">
                <span class="ecod_rank__no"><?php echo $ecodHotIndex++; ?></span>
                <span class="ecod_rank__text"><?php if (ecod_has_password($ecodHot)): ?><?php echo ecod_lock_icon(11, 'ecod_rank__lock'); ?><?php endif; ?><?php $ecodHot->title(); ?></span>
            </a>
        <?php endwhile; ?>
    </div>
</section>
<?php else: ?>
<section class="ecod_hot" aria-label="热门文章">
    <?php ecod_block_head(['note' => $ecodHotNotes[$ecodHotSort]]); ?>
    <div class="ecod_hot__grid">
        <?php $ecodHotIndex = 1; while ($ecodHot->next()): ?>
            <?php $ecodHotImg = ecod_thumbnails($ecodHot, 1, 'side')[0] ?? ''; ?>
            <a class="ecod_hot__item" href="<?php $ecodHot->permalink(); ?>">
                <span class="ecod_hot__cover"<?php if ($ecodHotImg): ?> style="--ecod-cover:url('<?php echo htmlspecialchars($ecodHotImg); ?>')"<?php endif; ?>>
                    <?php if (!$ecodHotImg): ?><span class="ecod_ph ecod_hot__ph"><?php echo mb_substr($ecodHot->title, 0, 1, 'UTF-8'); ?></span><?php endif; ?>
                    <span class="ecod_hot__rank"><?php echo $ecodHotIndex; ?></span>
                </span>
                <b class="ecod_hot__text"><?php if (ecod_has_password($ecodHot)): ?><?php echo ecod_lock_icon(11, 'ecod_hot__lock'); ?><?php endif; ?><?php $ecodHot->title(); ?></b>
                <span class="ecod_hot__meta"><?php echo $ecodHotSort === 'views' ? ecod_num_short((int) $ecodHot->views) . ' 阅读' : ecod_comment_count((int) $ecodHot->cid) . ' 评论'; ?> · <?php $ecodHot->date('m-d'); ?></span>
            </a>
            <?php $ecodHotIndex++; ?>
        <?php endwhile; ?>
    </div>
</section>
<?php endif; ?>
