<?php
/**
 * 侧栏块 · 广告位
 *
 * @package Ecod Theme
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodAsideAdImage = trim((string) ecod_p('image', ''));
if ($ecodAsideAdImage === '') {
    return;
}
$ecodAsideAdLink = trim((string) ecod_p('link', ''));
$ecodAsideAdTag = $ecodAsideAdLink !== '' ? 'a' : 'div';
$ecodAsideAdLabel = trim((string) ecod_p('label', '广告'));
/* 站内图片取真实宽高占位，避免图片加载后撑开页面 */
$ecodAsideAdDims = ecod_img_attrs($ecodAsideAdImage);
?>
<<?php echo $ecodAsideAdTag; ?> class="ecod_aside-ad"
    <?php if ($ecodAsideAdLink !== ''): ?>href="<?php echo htmlspecialchars($ecodAsideAdLink); ?>" target="_blank" rel="noopener nofollow"<?php endif; ?>>
    <img src="<?php echo htmlspecialchars($ecodAsideAdImage); ?>" alt="<?php echo htmlspecialchars($ecodAsideAdLabel ?: '推广'); ?>" loading="lazy"<?php echo $ecodAsideAdDims; ?>>
    <?php if ($ecodAsideAdLabel !== ''): ?>
        <span class="ecod_ad__label"><?php echo htmlspecialchars($ecodAsideAdLabel); ?></span>
    <?php endif; ?>
</<?php echo $ecodAsideAdTag; ?>>
