<?php
/**
 * 首页模块 · M10 数据概览
 *
 * @package Ecod Theme
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$ecodOv = ecod_site_overview();
$ecodOvItems = [
    ['icon' => 'file', 'num' => $ecodOv['posts'], 'label' => '文章'],
    ['icon' => 'comment', 'num' => $ecodOv['comments'], 'label' => '评论'],
    ['icon' => 'tag', 'num' => $ecodOv['tags'], 'label' => '标签'],
    ['icon' => 'clock', 'num' => $ecodOv['days'], 'label' => '运行天数'],
];
if ((int) ecod_p('views', 0) === 1) {
    $ecodOvItems[] = ['icon' => 'eye', 'num' => $ecodOv['views'], 'label' => '阅读'];
}
?>
<section class="ecod_overview" aria-label="站点数据">
    <?php foreach ($ecodOvItems as $item): ?>
        <div class="ecod_overview__item">
            <span class="ecod_overview__icon"><?php echo ecod_icon($item['icon'], 16); ?></span>
            <b class="ecod_overview__num"><?php echo number_format((int) $item['num']); ?></b>
            <span class="ecod_overview__label"><?php echo htmlspecialchars($item['label']); ?></span>
        </div>
    <?php endforeach; ?>
</section>
