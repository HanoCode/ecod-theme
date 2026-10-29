<?php
/**
 * 侧栏块 · 最新文章
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 * @license   MIT
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$this->widget('\Widget\Contents\Post\Recent', 'pageSize=' . max(1, (int) ecod_p('count', 6)))->to($ecodRecent);
?>
<section class="ecod_widget">
    <h2 class="ecod_widget__title"><?php echo htmlspecialchars((string) ecod_p('title', '最新文章')); ?></h2>
    <?php if ($ecodRecent->have()): ?>
        <div class="ecod_rank">
            <?php $i = 1; while ($ecodRecent->next()): ?>
                <a href="<?php $ecodRecent->permalink(); ?>">
                    <span class="ecod_rank__no"><?php echo $i++; ?></span>
                    <span class="ecod_rank__text"><?php if (ecod_has_password($ecodRecent)): ?><?php echo ecod_lock_icon(11, 'ecod_rank__lock'); ?><?php endif; ?><?php $ecodRecent->title(); ?></span>
                </a>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p class="ecod_mini-empty">还没有文章</p>
    <?php endif; ?>
</section>
