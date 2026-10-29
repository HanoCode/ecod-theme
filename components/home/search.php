<?php
/**
 * 首页模块 · M1 搜索盒
 *
 * @package Ecod Theme
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$ecodHotWords = array_filter(array_map('trim', preg_split('/[,，]/', (string) ecod_p('hotwords', ''))));
$ecodOverview = ecod_site_overview();
$ecodSlogan = ecod_option('ESlogan', $this->options->description);
$ecodEyebrow = (string) ecod_p('eyebrow', $ecodSlogan ?: '写作 · 记录 · 分享');
$ecodPlaceholder = (string) ecod_p('placeholder', '搜索文章、标签或关键词…');
$ecodShowStats = (int) ecod_p('stats', 1) === 1;
?>
<section class="ecod_hero">
    <div class="ecod_hero__text">
        <p class="ecod_hero__eyebrow"><?php echo ecod_icon('zap', 12); ?><?php echo htmlspecialchars($ecodEyebrow); ?></p>
        <h2 class="ecod_hero__title"><?php $this->options->title(); ?></h2>
        <?php if ($ecodShowStats): ?>
            <p class="ecod_hero__desc">
                共 <b><?php echo $ecodOverview['posts']; ?></b> 篇文章 ·
                <b><?php echo number_format($ecodOverview['views']); ?></b> 次阅读 ·
                已持续更新 <b><?php echo $ecodOverview['days']; ?></b> 天
            </p>
        <?php endif; ?>
    </div>

    <form class="ecod_hero__search" role="search" method="post" action="<?php $this->options->siteUrl(); ?>">
        <?php echo ecod_icon('search', 17); ?>
        <input type="search" name="s" placeholder="<?php echo htmlspecialchars($ecodPlaceholder); ?>" aria-label="搜索文章" autocomplete="off">
        <button type="submit" class="ecod_btn">搜索</button>
    </form>

    <?php if ($ecodHotWords): ?>
        <div class="ecod_hero__hot">
            <span class="ecod_hero__hot-label">热搜</span>
            <?php foreach ($ecodHotWords as $word): ?>
                <a href="<?php echo \Typecho\Router::url('search', ['keywords' => $word], $this->options->index); ?>"><?php echo htmlspecialchars($word); ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
