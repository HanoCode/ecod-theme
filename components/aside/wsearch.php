<?php
/**
 * 侧栏块 · 搜索框
 *
 * @package Ecod Theme
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
?>
<section class="ecod_widget">
    <h2 class="ecod_widget__title"><?php echo htmlspecialchars((string) ecod_p('title', '搜索')); ?></h2>
    <form class="ecod_wsearch" role="search" method="post" action="<?php $this->options->siteUrl(); ?>">
        <?php echo ecod_icon('search', 15); ?>
        <input type="search" name="s" autocomplete="off" aria-label="搜索文章"
               placeholder="<?php echo htmlspecialchars((string) ecod_p('placeholder', '输入关键词回车搜索…')); ?>">
        <button type="submit">搜索</button>
    </form>
</section>
