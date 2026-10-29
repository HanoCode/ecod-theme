<?php
/**
 * 专题墙（E2）
 *
 * 调用方准备：
 *   $ecodWallTopics 专题数组列表
 *   $ecodWallCols   列数 2|3|4（窄屏自动单列）
 *   $ecodWallStyle  cover|plain
 *
 * @package Ecod Theme
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodWallTopics = $ecodWallTopics ?? [];
if (!$ecodWallTopics) {
    return;
}

$ecodWallCols = max(2, min(4, (int) ($ecodWallCols ?? 3)));
$ecodWallStyle = (string) ($ecodWallStyle ?? 'cover') === 'plain' ? 'plain' : 'cover';

$ecodWallSlugs = array_column($ecodWallTopics, 'slug');
$ecodWallCovers = ecod_topic_covers($ecodWallSlugs);
$ecodWallIndex = ecod_topic_index();
?>
<div class="ecod_twall ecod_twall--c<?php echo $ecodWallCols; ?> ecod_twall--<?php echo $ecodWallStyle; ?>">
    <?php foreach ($ecodWallTopics as $ecodTopicCard):
        $ecodTopicSlug = (string) $ecodTopicCard['slug'];
        $ecodTopicCover = (string) ($ecodWallCovers[$ecodTopicSlug] ?? '');
        $ecodTopicCount = (int) ($ecodWallIndex[$ecodTopicSlug]['count'] ?? 0);
        $ecodTopicLatest = (string) ($ecodWallIndex[$ecodTopicSlug]['latest_title'] ?? '');
        $ecodTopicStyle = $ecodWallStyle;
        require __DIR__ . '/card.php';
    endforeach; ?>
</div>
