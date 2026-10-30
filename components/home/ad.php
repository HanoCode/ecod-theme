<?php
/**
 * 首页模块 · M11 广告位（配置了图片才显示）
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$ecodAdImage = trim((string) ecod_p('image', ''));
if ($ecodAdImage === '') {
    return;
}
$ecodAdLink = trim((string) ecod_p('link', ''));
$ecodAdLabel = trim((string) ecod_p('label', '广告'));
$ecodAdTag = $ecodAdLink !== '' ? 'a' : 'div';
/* 站内图片取真实宽高占位，避免图片加载后撑开页面 */
$ecodAdDims = ecod_img_attrs($ecodAdImage);
?>
<<?php echo $ecodAdTag; ?> class="ecod_ad"
    <?php if ($ecodAdLink !== ''): ?>href="<?php echo htmlspecialchars($ecodAdLink); ?>" target="_blank" rel="noopener nofollow"<?php endif; ?>>
    <img src="<?php echo htmlspecialchars($ecodAdImage); ?>" alt="推广" loading="lazy"<?php echo $ecodAdDims; ?>>
    <?php if ($ecodAdLabel !== ''): ?><span class="ecod_ad__label"><?php echo htmlspecialchars($ecodAdLabel); ?></span><?php endif; ?>
</<?php echo $ecodAdTag; ?>>
