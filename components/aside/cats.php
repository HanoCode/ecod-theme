<?php
/**
 * 侧栏块 · 分类目录
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$this->widget('\Widget\Metas\Category\Rows')->to($ecodAsideCats);
if (!$ecodAsideCats->have()) {
    return;
}

$ecodCatsLimit = max(0, (int) ecod_p('limit', 0));
$ecodShowCount = (int) ecod_p('count', 1) === 1;
$ecodCatIdx = 0;
?>
<section class="ecod_widget">
    <h2 class="ecod_widget__title"><?php echo htmlspecialchars((string) ecod_p('title', '分类目录')); ?></h2>
    <div class="ecod_wlist">
        <?php while ($ecodAsideCats->next()): ?>
            <?php
            if ($ecodCatsLimit > 0 && $ecodCatIdx >= $ecodCatsLimit) {
                break;
            }
            $ecodCatIdx++;
            ?>
            <a href="<?php $ecodAsideCats->permalink(); ?>" class="ecod_wlist__item">
                <span class="ecod_wlist__name"><?php $ecodAsideCats->name(); ?></span>
                <?php if ($ecodShowCount): ?>
                    <?php
                    /* 不用 metas.count：Typecho 的缓存计数会滞后，且不含子分类；
                       实时统计与分类页头 / 归档列表同口径。 */
                    $ecodAsideStat = ecod_cat_stats((int) $ecodAsideCats->mid);
                    ?>
                    <b class="ecod_wlist__num"><?php echo (int) $ecodAsideStat['count']; ?></b>
                <?php endif; ?>
            </a>
        <?php endwhile; ?>
    </div>
</section>
