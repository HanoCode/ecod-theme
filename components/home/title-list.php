<?php
/**
 * 首页模块 · M9 标题速览
 *
 * @package Ecod Theme
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$this->widget('EcodPostQuery@titlelist', 'pageSize=' . max(3, (int) ecod_p('count', 10)))->to($ecodTitleList);
if (!$ecodTitleList->have()) {
    return;
}
$ecodTitleListTitle = (string) ecod_p('title', '最新速览');
?>
<section class="ecod_section ecod_section--plain">
    <header class="ecod_section-head">
        <h2 class="ecod_section-head__title"><?php echo ecod_icon('file', 15); ?><?php echo htmlspecialchars($ecodTitleListTitle); ?></h2>
        <a class="ecod_section-head__more" href="<?php $this->options->siteUrl(); ?>">全部 <?php echo ecod_icon('chevron', 12); ?></a>
    </header>
    <div class="ecod_titlelist">
        <?php while ($ecodTitleList->next()): ?>
            <?php ecod_card($ecodTitleList, 'title'); ?>
        <?php endwhile; ?>
    </div>
</section>
