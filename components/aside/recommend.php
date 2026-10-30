<?php
/**
 * 侧栏块 · 推荐位（首页「推荐位」放到侧栏时的紧凑版）
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$this->widget('EcodPostQuery@asidereco', 'pageSize=' . max(1, (int) ecod_p('count', 3)))->to($ecodReco);
if (!$ecodReco->have()) {
    return;
}
?>
<section class="ecod_widget">
    <h2 class="ecod_widget__title"><?php echo htmlspecialchars((string) ecod_p('title', '推荐阅读')); ?></h2>
    <div class="ecod_wlist ecod_wlist--thumb">
        <?php while ($ecodReco->next()): ?>
            <?php $ecodRecoImg = ecod_thumbnails($ecodReco, 1, 'side')[0] ?? ''; ?>
            <a class="ecod_wlist__item" href="<?php $ecodReco->permalink(); ?>">
                <span class="ecod_wlist__cover"<?php if ($ecodRecoImg): ?> style="background-image:url('<?php echo htmlspecialchars($ecodRecoImg); ?>')"<?php endif; ?>>
                    <?php if (!$ecodRecoImg): ?><span><?php echo mb_substr($ecodReco->title, 0, 1, 'UTF-8'); ?></span><?php endif; ?>
                </span>
                <span class="ecod_wlist__main">
                    <b class="ecod_wlist__title"><?php if (ecod_has_password($ecodReco)): ?><?php echo ecod_lock_icon(11, 'ecod_wlist__lock'); ?><?php endif; ?><?php $ecodReco->title(); ?></b>
                    <span class="ecod_wlist__meta">
                        <time datetime="<?php $ecodReco->date('Y-m-d'); ?>"><?php $ecodReco->date('m-d'); ?></time>
                        <span><?php echo ecod_icon('eye', 11); ?><?php echo ecod_num_short(ecod_views($ecodReco)); ?></span>
                    </span>
                </span>
            </a>
        <?php endwhile; ?>
    </div>
</section>
