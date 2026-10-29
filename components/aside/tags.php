<?php
/**
 * 侧栏块 · 标签云
 *
 * 标签按热度（文章数）流式排布，带文章数徽章；
 * 字号随文章数轻微放大（WordPress wp_tag_cloud 的做法）。
 * 数量直接用 Typecho metas 表现成的 count 列，不做额外统计查询。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 * @license   MIT
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodTagLimit = max(0, (int) ecod_p('limit', 0));
$this->widget('\Widget\Metas\Tag\Cloud', [
    'sort'           => 'count',
    'desc'           => true,
    'ignoreZeroCount'=> true,
    'limit'          => $ecodTagLimit,
])->to($ecodTags);

/* 先收进数组算最大/最小文章数，用于字号梯度 */
$ecodTagRows = [];
while ($ecodTags->next()) {
    $ecodTagRows[] = [
        'name'  => (string) $ecodTags->name,
        'permalink' => (string) $ecodTags->permalink,
        'count' => (int) $ecodTags->count,
    ];
}
$ecodTagMax = 0;
$ecodTagMin = 0;
foreach ($ecodTagRows as $ecodTagRow) {
    if ($ecodTagRow['count'] > $ecodTagMax) {
        $ecodTagMax = $ecodTagRow['count'];
    }
    if ($ecodTagMin === 0 || $ecodTagRow['count'] < $ecodTagMin) {
        $ecodTagMin = $ecodTagRow['count'];
    }
}
$ecodTagSpan = max(1, $ecodTagMax - $ecodTagMin);
?>
<section class="ecod_widget">
    <h2 class="ecod_widget__title"><?php echo htmlspecialchars((string) ecod_p('title', '标签云')); ?></h2>
    <?php if ($ecodTagRows): ?>
        <div class="ecod_cloud">
            <?php foreach ($ecodTagRows as $ecodTagRow): ?>
                <?php
                /* 字号 11.5 ~ 14px，按文章数线性插值；只有 1 个量级时全部取小号 */
                $ecodTagSize = $ecodTagMax === $ecodTagMin
                    ? 11.5
                    : round(11.5 + 2.5 * ($ecodTagRow['count'] - $ecodTagMin) / $ecodTagSpan, 1);
                ?>
                <a href="<?php echo $ecodTagRow['permalink']; ?>" style="font-size:<?php echo $ecodTagSize; ?>px">
                    <?php echo htmlspecialchars($ecodTagRow['name'], ENT_QUOTES, 'UTF-8'); ?>
                    <i><?php echo $ecodTagRow['count']; ?></i>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="ecod_mini-empty">还没有标签</p>
    <?php endif; ?>
</section>
