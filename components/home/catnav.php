<?php
/**
 * 首页模块 · M2 分类导航
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$this->widget('\Widget\Metas\Category\Rows')->to($ecodNavCats);
if (!$ecodNavCats->have()) {
    return;
}
$ecodNavLimit = max(1, (int) ecod_p('limit', 6));
$ecodNavShown = 0;
$ecodTints = [
    ['#4f6ef2', '#eef1fe'],
    ['#d9741f', '#fdf1e3'],
    ['#11a37f', '#e6f7f1'],
    ['#a05ad9', '#f6ecfd'],
    ['#d94b6b', '#fdecef'],
    ['#2f8fd9', '#e9f4fd'],
];
?>
<section class="ecod_catnav">
    <?php $ecodCatIndex = 0; while ($ecodNavCats->next()): ?>
        <?php
        if ($ecodNavShown >= $ecodNavLimit) {
            break;
        }
        $ecodNavShown++;
        $ecodTint = $ecodTints[$ecodCatIndex % count($ecodTints)];
        ?>
        <a class="ecod_catnav__item" href="<?php $ecodNavCats->permalink(); ?>">
            <span class="ecod_catnav__icon" style="--ecod-cat-tint: <?php echo $ecodTint[0]; ?>; --ecod-cat-soft: <?php echo $ecodTint[1]; ?>">
                <?php echo mb_substr($ecodNavCats->name, 0, 1, 'UTF-8'); ?>
            </span>
            <span class="ecod_catnav__body">
                <b><?php $ecodNavCats->name(); ?></b>
                <?php
                /* 不用 metas.count：那是 Typecho 的缓存计数，文章改状态后不会更新，
                   且不含子分类。实时统计与分类页头 / 归档列表同口径。 */
                $ecodNavStat = ecod_cat_stats((int) $ecodNavCats->mid);
                ?>
                <small><?php echo (int) $ecodNavStat['count']; ?> 篇文章</small>
            </span>
            <span class="ecod_catnav__arrow" aria-hidden="true"><?php echo ecod_icon('chevron', 13); ?></span>
        </a>
        <?php $ecodCatIndex++; ?>
    <?php endwhile; ?>
</section>
