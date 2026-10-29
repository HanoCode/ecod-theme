<?php
/**
 * 侧栏块 · 时间流（首页「动态流」放到侧栏时的紧凑版）
 *
 * @package Ecod Theme
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$this->widget('EcodPostQuery@asidefeed', 'pageSize=' . max(3, (int) ecod_p('count', 8)))->to($ecodFeed);
if (!$ecodFeed->have()) {
    return;
}
?>
<section class="ecod_widget">
    <h2 class="ecod_widget__title"><?php echo htmlspecialchars((string) ecod_p('title', '动态')); ?></h2>
    <div class="ecod_wlist">
        <?php while ($ecodFeed->next()): ?>
            <?php $ecodIsNew = (time() - (int) $ecodFeed->created) < 86400; ?>
            <a class="ecod_wlist__item" href="<?php $ecodFeed->permalink(); ?>">
                <span class="ecod_wlist__dot<?php echo $ecodIsNew ? ' is-new' : ''; ?>" aria-hidden="true"></span>
                <span class="ecod_wlist__main">
                    <b class="ecod_wlist__title"><?php if (ecod_has_password($ecodFeed)): ?><?php echo ecod_lock_icon(11, 'ecod_wlist__lock'); ?><?php endif; ?><?php $ecodFeed->title(); ?></b>
                    <span class="ecod_wlist__meta">
                        <time datetime="<?php $ecodFeed->date('c'); ?>"><?php echo ecod_relative_time((int) $ecodFeed->created); ?></time>
                    </span>
                </span>
            </a>
        <?php endwhile; ?>
    </div>
</section>
