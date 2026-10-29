<?php
/**
 * 专题详情页头
 *
 * 调用方准备：
 *   $ecodTopicHead       专题数组
 *   $ecodTopicHeadCover  封面（可为空）
 *   $ecodTopicHeadCount  篇数
 *   $ecodTopicHeadLatest 最近更新时间戳
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 * @license   MIT
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodTopicHead = $ecodTopicHead ?? null;
if (!is_array($ecodTopicHead) || empty($ecodTopicHead['slug'])) {
    return;
}

$ecodTopicHeadCover = (string) ($ecodTopicHeadCover ?? '');
$ecodTopicHeadCount = (int) ($ecodTopicHeadCount ?? 0);
$ecodTopicHeadLatest = (int) ($ecodTopicHeadLatest ?? 0);
?>
<section class="ecod_thead<?php echo $ecodTopicHeadCover === '' ? ' is-nocover' : ''; ?>"
    <?php if ($ecodTopicHeadCover !== ''): ?>style="--ecod-thead-cover:url('<?php echo htmlspecialchars($ecodTopicHeadCover, ENT_QUOTES, 'UTF-8'); ?>')"<?php endif; ?>>
    <div class="ecod_thead__inner">
        <span class="ecod_thead__tag"><?php echo ecod_icon('folder', 12); ?>专题</span>
        <h1 class="ecod_thead__title"><?php echo htmlspecialchars((string) $ecodTopicHead['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if (!empty($ecodTopicHead['desc'])): ?>
            <p class="ecod_thead__desc"><?php echo htmlspecialchars((string) $ecodTopicHead['desc'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <div class="ecod_thead__meta">
            <span><?php echo ecod_icon('list', 13); ?><b><?php echo $ecodTopicHeadCount; ?></b> 篇</span>
            <?php if ($ecodTopicHeadLatest > 0): ?>
                <span><?php echo ecod_icon('calendar', 13); ?>最近更新 <?php echo htmlspecialchars(ecod_relative_time($ecodTopicHeadLatest), ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
        </div>
    </div>
</section>
