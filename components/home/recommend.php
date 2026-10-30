<?php
/**
 * 首页模块 · M4 推荐位
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$this->widget('EcodPostQuery@reco', 'pageSize=' . max(1, (int) ecod_p('count', 3)))->to($ecodReco);
if (!$ecodReco->have()) {
    return;
}
?>
<section class="ecod_reco" aria-label="推荐阅读">
    <?php while ($ecodReco->next()): ?>
        <?php $ecodRecoImg = ecod_thumbnails($ecodReco, 1, 'cover')[0] ?? ''; ?>
        <a class="ecod_reco__item" href="<?php $ecodReco->permalink(); ?>">
            <span class="ecod_reco__cover"<?php if ($ecodRecoImg): ?> style="--ecod-cover:url('<?php echo htmlspecialchars($ecodRecoImg); ?>')"<?php endif; ?>>
                <?php if (!$ecodRecoImg): ?><span class="ecod_reco__ph"><?php echo mb_substr($ecodReco->title, 0, 1, 'UTF-8'); ?></span><?php endif; ?>
            </span>
            <span class="ecod_reco__body">
                <b class="ecod_reco__title"><?php $ecodReco->title(); ?></b>
                <span class="ecod_reco__meta">
                    <time datetime="<?php $ecodReco->date('Y-m-d'); ?>"><?php $ecodReco->date('m-d'); ?></time>
                    <span><?php echo ecod_icon('eye', 12); ?><?php echo ecod_num_short(ecod_views($ecodReco)); ?></span>
                </span>
            </span>
        </a>
    <?php endwhile; ?>
</section>
