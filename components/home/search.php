<?php
/**
 * 首页模块 · M1 首屏（标题 / 搜索 / 热搜 / 数据带）
 *
 * 两种形态共用一套结构：
 * - 配了背景图 → 沉浸式：默认 480px 高、左对齐、玻璃数据带
 * - 没配背景图 → 原来的浅色卡片，数据带降级为浅底
 *
 * @package Ecod Theme
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$ecodHotWords = array_filter(array_map('trim', preg_split('/[,，]/', (string) ecod_p('hotwords', ''))));
$ecodOverview = ecod_site_overview();
$ecodSlogan = ecod_option('ESlogan', $this->options->description);
/* 眉标题与副标题不能同源：原来两者都回落到副标题，开启数据带后会出现「同一句话写两遍」 */
$ecodEyebrow = trim((string) ecod_p('eyebrow', ''));
if ($ecodEyebrow === '') {
    $ecodEyebrow = '写作 · 记录 · 分享';
}
$ecodPlaceholder = (string) ecod_p('placeholder', '搜索文章、标签或关键词…');
$ecodShowStats = (int) ecod_p('stats', 1) === 1;

/* 沉浸式首屏参数 */
$ecodHeroBg = trim((string) ecod_p('bg', ''));
$ecodHeroH = (int) ecod_p('height', 480);
$ecodHeroH = max(320, min(720, $ecodHeroH > 0 ? $ecodHeroH : 480));
$ecodHeroAlign = (string) ecod_p('align', 'left') === 'center' ? 'center' : 'left';
$ecodHeroBand = (int) ecod_p('band', 1) === 1;
$ecodImmersive = (string) ecod_p('style', 'immersive') === 'immersive';

$ecodHeroStyle = '';
if ($ecodImmersive) {
    $ecodHeroStyle = '--ecod-hero-h:' . $ecodHeroH . 'px';
    if ($ecodHeroBg !== '') {
        $ecodHeroStyle .= ";--ecod-hero-img:url('" . addcslashes($ecodHeroBg, "'\\") . "')";
    }
}

$ecodHeroStats = [
    ['num' => (string) (int) $ecodOverview['posts'], 'label' => '篇文章'],
    ['num' => ecod_num_short((int) $ecodOverview['views']), 'label' => '次阅读'],
    ['num' => (string) (int) $ecodOverview['days'], 'label' => '天持续更新'],
];
?>
<section class="ecod_hero<?php echo $ecodImmersive ? ' ecod_hero--immersive' : ''; ?> is-<?php echo $ecodHeroAlign; ?>"<?php
    echo $ecodHeroStyle !== '' ? ' style="' . htmlspecialchars($ecodHeroStyle, ENT_QUOTES, 'UTF-8') . '"' : '';
    ?>>
    <div class="ecod_hero__text">
        <p class="ecod_hero__eyebrow"><?php echo ecod_icon('zap', 12); ?><?php echo htmlspecialchars($ecodEyebrow); ?></p>
        <h2 class="ecod_hero__title"><?php $this->options->title(); ?></h2>
        <?php if ($ecodShowStats && !$ecodHeroBand): ?>
            <p class="ecod_hero__desc">
                共 <b><?php echo (int) $ecodOverview['posts']; ?></b> 篇文章 ·
                <b><?php echo number_format((int) $ecodOverview['views']); ?></b> 次阅读 ·
                已持续更新 <b><?php echo (int) $ecodOverview['days']; ?></b> 天
            </p>
        <?php elseif ($ecodSlogan): ?>
            <p class="ecod_hero__desc"><?php echo htmlspecialchars($ecodSlogan); ?></p>
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

    <?php if ($ecodShowStats && $ecodHeroBand): ?>
        <div class="ecod_hero__band">
            <?php foreach ($ecodHeroStats as $ecodStat): ?>
                <div class="ecod_hero__stat">
                    <b><?php echo htmlspecialchars($ecodStat['num']); ?></b>
                    <span><?php echo htmlspecialchars($ecodStat['label']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
