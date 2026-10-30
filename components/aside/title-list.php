<?php
/**
 * 侧栏块 · 标题速览（首页「标题速览」放到侧栏时的紧凑版）
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$this->widget('EcodPostQuery@asidetitlelist', 'pageSize=' . max(3, (int) ecod_p('count', 10)))->to($ecodList);
if (!$ecodList->have()) {
    return;
}
?>
<section class="ecod_widget">
    <h2 class="ecod_widget__title"><?php echo htmlspecialchars((string) ecod_p('title', '最新速览')); ?></h2>
    <div class="ecod_wlist">
        <?php $ecodNo = 1; while ($ecodList->next()): ?>
            <a class="ecod_wlist__item" href="<?php $ecodList->permalink(); ?>">
                <span class="ecod_wlist__no"><?php echo $ecodNo++; ?></span>
                <span class="ecod_wlist__main">
                    <b class="ecod_wlist__title"><?php if (ecod_has_password($ecodList)): ?><?php echo ecod_lock_icon(11, 'ecod_wlist__lock'); ?><?php endif; ?><?php $ecodList->title(); ?></b>
                </span>
            </a>
        <?php endwhile; ?>
    </div>
</section>
