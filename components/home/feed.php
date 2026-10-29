<?php
/**
 * 首页模块 · M6 动态流
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 * @license   MIT
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$this->widget('EcodPostQuery@feed', 'pageSize=' . max(3, (int) ecod_p('count', 8)))->to($ecodFeed);
if (!$ecodFeed->have()) {
    return;
}
$ecodFeedTitle = (string) ecod_p('title', '动态');
?>
<section class="ecod_feed">
    <header class="ecod_section-head">
        <h2 class="ecod_section-head__title"><?php echo ecod_icon('clock', 15); ?><?php echo htmlspecialchars($ecodFeedTitle); ?></h2>
        <span class="ecod_section-head__note">最近更新</span>
    </header>
    <div class="ecod_feed__list">
        <?php while ($ecodFeed->next()): ?>
            <?php $ecodIsNew = (time() - (int) $ecodFeed->created) < 86400; ?>
            <a class="ecod_feed__item" href="<?php $ecodFeed->permalink(); ?>">
                <span class="ecod_feed__dot<?php echo $ecodIsNew ? ' is-new' : ''; ?>" aria-hidden="true"></span>
                <span class="ecod_feed__tag"><?php echo $ecodIsNew ? '发布' : '更新'; ?></span>
                <span class="ecod_feed__title"><?php if (ecod_has_password($ecodFeed)): ?><?php echo ecod_lock_icon(11, 'ecod_feed__lock'); ?><?php endif; ?><?php $ecodFeed->title(); ?></span>
                <time class="ecod_feed__time" datetime="<?php $ecodFeed->date('c'); ?>"><?php echo ecod_relative_time((int) $ecodFeed->created); ?></time>
            </a>
        <?php endwhile; ?>
    </div>
</section>
