<?php
/**
 * 首页模块 · 专题（E2 专题墙的首页切片）
 *
 * 取后台「专题」里启用的前 N 个，按专题墙同一套卡片渲染；
 * 有专题页时右上角带「全部专题」入口。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 * @license   MIT
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodHomeTopics = array_slice(ecod_topics(), 0, max(1, min(12, (int) ecod_p('count', 3))));
if (!$ecodHomeTopics) {
    return;
}

$ecodWallTopics = $ecodHomeTopics;
$ecodWallCols = max(2, min(4, (int) ecod_p('cols', 3)));
$ecodWallStyle = (string) ecod_p('style', 'cover');
$ecodHomeTopicsPage = ecod_topics_page();
?>
<section class="ecod_section ecod_section--topics">
    <header class="ecod_section-head">
        <h2 class="ecod_section-head__title">
            <span class="ecod_section-head__dot" aria-hidden="true"></span><?php echo htmlspecialchars((string) ecod_p('title', '专题'), ENT_QUOTES, 'UTF-8'); ?>
        </h2>
        <?php if ($ecodHomeTopicsPage['url'] !== ''): ?>
            <a class="ecod_section-head__more" href="<?php echo htmlspecialchars($ecodHomeTopicsPage['url'], ENT_QUOTES, 'UTF-8'); ?>">
                全部专题<?php echo ecod_icon('chevron', 12); ?>
            </a>
        <?php endif; ?>
    </header>

    <?php require __DIR__ . '/../topic/wall.php'; ?>
</section>
