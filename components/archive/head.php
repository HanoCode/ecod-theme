<?php
/**
 * 分类页运行页头（E1）
 *
 * 分类从「筛选条件」变成「目的地」：名称、描述、三个统计数字、
 * 以及该分类下文章命中的专题 chips。
 *
 * 背景取最新一篇文章的封面；取不到时退到 CSS 特色背景
 * （品牌色极光 + 图纸网格，--ecod-hero-h 随 mid 旋转出每分类不同色调，不额外查库）。
 * 开关与形态由主题设置控制：ECatHead / ECatHeadBg / ECatHeadStats / ECatHeadTopics。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

if (ecod_option('ECatHead', 'on') === 'off') {
    return;
}

$ecodCatSlug = '';
try {
    $ecodCatSlug = (string) $this->getArchiveSlug();
} catch (\Throwable $e) {
}
if ($ecodCatSlug === '') {
    return;
}

$ecodCat = ecod_cat_meta($ecodCatSlug);
if ($ecodCat === null) {
    return;
}

$ecodCatStat = ecod_cat_stats($ecodCat['mid']);
$ecodCatCover = '';

/* 分类增强（规格 §32）：管理员为该分类单独设置的封面最优先；
   其次用设置里固定的一张背景图；ECatHeadBg=soft 是明确的「不用图」选择，尊重它 */
$ecodCatCoverCustom = '';
$ecodCatHeadMode = (string) ecod_option('ECatHeadBg', 'cover');
if (function_exists('ecod_meta_meta_get') && $ecodCatHeadMode !== 'soft') {
    $ecodCatCoverCustom = trim(ecod_meta_meta_get((int) $ecodCat['mid'], 'cover'));
}

if ($ecodCatCoverCustom !== '') {
    $ecodCatCover = $ecodCatCoverCustom;
} elseif ($ecodCatHeadMode === 'custom') {
    $ecodCatCover = trim((string) ecod_option('ECatHeadImage', ''));
} elseif ($ecodCatHeadMode !== 'soft' && $ecodCatStat['count'] > 0) {
    try {
        $this->widget('EcodPostQuery@cathead', 'pageSize=1&category=' . $ecodCat['mid'])->to($ecodCatQ);
        if ($ecodCatQ->have()) {
            $ecodCatQ->next();
            $ecodCatCover = (string) (ecod_thumbnails($ecodCatQ, 1, 'cover')[0] ?? '');
        }
    } catch (\Throwable $e) {
    }
}

$ecodCatTopics = ecod_option('ECatHeadTopics', 'on') === 'off' ? [] : ecod_cat_topics($ecodCat['mid'], 5);

/* 告知 archive.php：页头已输出 h1，不必再补一个屏幕阅读器标题 */
$GLOBALS['ecodCatHeadDone'] = true;
?>
<section class="ecod_chead<?php echo $ecodCatCover === '' ? ' is-nocover' : ''; ?>"
    <?php if ($ecodCatCover !== ''): ?>style="--ecod-chead-cover:url('<?php echo htmlspecialchars($ecodCatCover, ENT_QUOTES, 'UTF-8'); ?>')"<?php else: ?>style="--ecod-hero-h:<?php echo ($ecodCat['mid'] * 137) % 360; ?>"<?php endif; ?>>
    <div class="ecod_chead__inner">
        <span class="ecod_chead__tag"><?php echo ecod_icon('folder', 12); ?>分类</span>
        <h1 class="ecod_chead__title"><?php echo htmlspecialchars($ecodCat['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if ($ecodCat['desc'] !== ''): ?>
            <p class="ecod_chead__desc"><?php echo htmlspecialchars($ecodCat['desc'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <?php if (ecod_option('ECatHeadStats', 'on') !== 'off'): ?>
            <div class="ecod_chead__stats">
                <span><b><?php echo (int) $ecodCatStat['count']; ?></b><em>篇文章</em></span>
                <span><b><?php echo ecod_num_short((int) $ecodCatStat['views']); ?></b><em>累计阅读</em></span>
                <span><b><?php echo $ecodCatStat['latest'] > 0 ? htmlspecialchars(date('Y-m-d', (int) $ecodCatStat['latest']), ENT_QUOTES, 'UTF-8') : '—'; ?></b><em>最近更新</em></span>
            </div>
        <?php endif; ?>

        <?php if ($ecodCatTopics): ?>
            <div class="ecod_chead__topics">
                <span class="ecod_chead__topiclabel"><?php echo ecod_icon('folder', 12); ?>相关专题</span>
                <?php foreach ($ecodCatTopics as $ecodCatTopic): ?>
                    <a class="ecod_chead__chip" href="<?php echo htmlspecialchars(ecod_topic_url((string) $ecodCatTopic['topic']['slug']), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars((string) $ecodCatTopic['topic']['name'], ENT_QUOTES, 'UTF-8'); ?>
                        <em><?php echo (int) $ecodCatTopic['hit']; ?></em>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
