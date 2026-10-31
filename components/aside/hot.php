<?php
/**
 * 侧栏块 · 热门（首页「热门四宫格」放到侧栏时的排行版）
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
$ecodHotSort = in_array($ecodHotSortRaw, ['views', 'weighted'], true) ? $ecodHotSortRaw : 'hot';
$this->widget('EcodPostQuery@asidehot', 'pageSize=' . max(2, (int) ecod_p('count', 4)) . '&order=' . $ecodHotSort)->to($ecodHot);
if (!$ecodHot->have()) {
    return;
}
?>
<section class="ecod_widget">
    <h2 class="ecod_widget__title"><?php echo htmlspecialchars((string) ecod_p('title', '热门')); ?></h2>
    <div class="ecod_wlist ecod_wlist--thumb">
        <?php $ecodHotNo = 1; while ($ecodHot->next()): ?>
            <?php $ecodHotImg = ecod_thumbnails($ecodHot, 1, 'side')[0] ?? ''; ?>
            <a class="ecod_wlist__item" href="<?php $ecodHot->permalink(); ?>">
                <span class="ecod_wlist__cover"<?php if ($ecodHotImg): ?> style="background-image:url('<?php echo htmlspecialchars($ecodHotImg); ?>')"<?php endif; ?>>
                    <?php if (!$ecodHotImg): ?><span><?php echo mb_substr($ecodHot->title, 0, 1, 'UTF-8'); ?></span><?php endif; ?>
                </span>
                <span class="ecod_wlist__main">
                    <b class="ecod_wlist__title"><?php if (ecod_has_password($ecodHot)): ?><?php echo ecod_lock_icon(11, 'ecod_wlist__lock'); ?><?php endif; ?><?php $ecodHot->title(); ?></b>
                    <span class="ecod_wlist__meta">
                        <span class="ecod_wlist__no<?php echo $ecodHotNo <= 3 ? ' is-top' : ''; ?>"><?php echo $ecodHotNo; ?></span>
                        <?php if ($ecodHotSort === 'weighted'): ?>
                            <span><?php echo ecod_num_short((int) $ecodHot->views); ?> 阅读 · <?php echo (int) $ecodHot->commentsNum; ?> 评论</span>
                        <?php else: ?>
                            <span><?php echo $ecodHotSort === 'views' ? ecod_num_short((int) $ecodHot->views) . ' 阅读' : (int) $ecodHot->commentsNum . ' 评论'; ?></span>
                        <?php endif; ?>
                    </span>
                </span>
            </a>
            <?php $ecodHotNo++; ?>
        <?php endwhile; ?>
    </div>
</section>
