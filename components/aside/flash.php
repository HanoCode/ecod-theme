<?php
/**
 * 侧栏块 · 快讯（首页「快讯」放到侧栏时的紧凑版）
 *
 * @package Ecod Theme
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodFlashCount = max(3, min(12, (int) ecod_p('count', 6)));
$ecodFlashMid = max(0, (int) ecod_p('mid', 0));
$ecodFlashDays = max(0, (int) ecod_p('days', 0));

$ecodFlashQuery = 'pageSize=' . $ecodFlashCount;
if ($ecodFlashMid > 0) {
    $ecodFlashQuery .= '&category=' . $ecodFlashMid;
}
if ($ecodFlashDays > 0) {
    $ecodFlashQuery .= '&days=' . $ecodFlashDays;
}

$this->widget('EcodPostQuery@asideflash', $ecodFlashQuery)->to($ecodFlashQ);
if (!$ecodFlashQ->have()) {
    return;
}
?>
<section class="ecod_widget">
    <h2 class="ecod_widget__title"><?php echo htmlspecialchars((string) ecod_p('title', '快讯')); ?></h2>
    <div class="ecod_wlist">
        <?php while ($ecodFlashQ->next()): ?>
            <?php $ecodFlashTime = (int) $ecodFlashQ->created; ?>
            <a class="ecod_wlist__item" href="<?php $ecodFlashQ->permalink(); ?>">
                <span class="ecod_wlist__stamp" aria-hidden="true">
                    <b><?php echo date('d', $ecodFlashTime); ?></b>
                    <small><?php echo date('m', $ecodFlashTime); ?> 月</small>
                </span>
                <span class="ecod_wlist__main">
                    <b class="ecod_wlist__title"><?php if (ecod_has_password($ecodFlashQ)): ?><?php echo ecod_lock_icon(11, 'ecod_wlist__lock'); ?><?php endif; ?><?php $ecodFlashQ->title(); ?></b>
                    <?php if ((int) ecod_p('desc', 1) === 1): ?>
                        <?php $ecodFlashText = trim(ecod_excerpt($ecodFlashQ)); ?>
                        <?php if ($ecodFlashText !== ''): ?>
                            <span class="ecod_wlist__meta ecod_wlist__meta--clip"><?php echo htmlspecialchars($ecodFlashText, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </span>
            </a>
        <?php endwhile; ?>
    </div>
</section>
