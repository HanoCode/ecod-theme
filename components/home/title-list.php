<?php
/**
 * 首页模块 · M9 标题速览（标题卡片 / 序号列表两种版式，原 recent 已并入）
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$this->widget('EcodPostQuery@titlelist', 'pageSize=' . max(3, (int) ecod_p('count', 10)))->to($ecodTitleList);
if (!$ecodTitleList->have()) {
    return;
}
$ecodTitleListStyle = (string) ecod_p('style', 'card') === 'rank' ? 'rank' : 'card';
?>
<?php if ($ecodTitleListStyle === 'rank'): ?>
<section class="ecod_section ecod_section--plain">
    <?php ecod_block_head(); ?>
    <div class="ecod_rank">
        <?php $ecodTitleListIndex = 1; while ($ecodTitleList->next()): ?>
            <a href="<?php $ecodTitleList->permalink(); ?>">
                <span class="ecod_rank__no"><?php echo $ecodTitleListIndex++; ?></span>
                <span class="ecod_rank__text"><?php if (ecod_has_password($ecodTitleList)): ?><?php echo ecod_lock_icon(11, 'ecod_rank__lock'); ?><?php endif; ?><?php $ecodTitleList->title(); ?></span>
            </a>
        <?php endwhile; ?>
    </div>
</section>
<?php else: ?>
<section class="ecod_section ecod_section--plain">
    <?php ecod_block_head(); ?>
    <div class="ecod_titlelist">
        <?php while ($ecodTitleList->next()): ?>
            <?php ecod_card($ecodTitleList, 'title'); ?>
        <?php endwhile; ?>
    </div>
</section>
<?php endif; ?>
