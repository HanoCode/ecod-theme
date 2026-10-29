<?php
/**
 * 侧栏块 · 站点统计
 *
 * @package Ecod Theme
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodStatOv = ecod_site_overview();
$ecodStatItems = [
    ['num' => $ecodStatOv['posts'], 'label' => '文章'],
    ['num' => $ecodStatOv['comments'], 'label' => '评论'],
    ['num' => $ecodStatOv['tags'], 'label' => '标签'],
    ['num' => $ecodStatOv['days'], 'label' => '运行天数'],
];
if ((int) ecod_p('views', 1) === 1) {
    $ecodStatItems[] = ['num' => $ecodStatOv['views'], 'label' => '总阅读'];
}
?>
<section class="ecod_widget">
    <h2 class="ecod_widget__title"><?php echo htmlspecialchars((string) ecod_p('title', '站点统计')); ?></h2>
    <div class="ecod_wstats">
        <?php foreach ($ecodStatItems as $item): ?>
            <div class="ecod_wstats__item">
                <b class="ecod_wstats__num"><?php echo number_format((int) $item['num']); ?></b>
                <span class="ecod_wstats__label"><?php echo htmlspecialchars($item['label']); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>
