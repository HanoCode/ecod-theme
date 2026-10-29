<?php
/**
 * 侧栏块 · 热门文章
 *
 * @package Ecod Theme
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodHotBy = (string) ecod_p('by', 'hot');
$ecodHotBy = in_array($ecodHotBy, ['hot', 'views', 'rand'], true) ? $ecodHotBy : 'hot';

$this->widget(
    'EcodPostQuery@asidehot',
    'pageSize=' . max(1, (int) ecod_p('count', 5)) . '&order=' . $ecodHotBy
)->to($ecodAsideHot);
?>
<section class="ecod_widget">
    <h2 class="ecod_widget__title"><?php echo htmlspecialchars((string) ecod_p('title', '热门文章')); ?></h2>
    <?php if ($ecodAsideHot->have()): ?>
        <div class="ecod_rank">
            <?php $i = 1; while ($ecodAsideHot->next()): ?>
                <a href="<?php $ecodAsideHot->permalink(); ?>">
                    <span class="ecod_rank__no"><?php echo $i++; ?></span>
                    <span class="ecod_rank__text"><?php if (ecod_has_password($ecodAsideHot)): ?><?php echo ecod_lock_icon(11, 'ecod_rank__lock'); ?><?php endif; ?><?php $ecodAsideHot->title(); ?></span>
                </a>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p class="ecod_mini-empty">还没有文章</p>
    <?php endif; ?>
</section>
