<?php
/**
 * 模块 · 顶部大图（原 index.php 硬编码 Banner，组件化后可删/可拖/可进 v5 板块）
 *
 * bg 留空时整个模块不渲染（与原「EBanner 为空不显示」行为一致）；
 * bg / slogan 经 legacy 回落读旧的 EBanner / ESlogan 选项，老站零配置过渡。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @since   2026-10-31
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodBannerBg = trim((string) ecod_p('bg', ''));
if ($ecodBannerBg === '') {
    return;
}
$ecodBannerSlogan = trim((string) ecod_p('slogan', ''));
$ecodBannerH = (int) ecod_p('height', 0);
?>
<section class="ecod_banner" style="--ecod-banner:url('<?php echo htmlspecialchars($ecodBannerBg, ENT_QUOTES, 'UTF-8'); ?>');<?php if ($ecodBannerH > 0) echo 'min-height:' . $ecodBannerH . 'px'; ?>">
    <div class="ecod_banner__inner">
        <h1><?php $this->options->title(); ?></h1>
        <?php if ($ecodBannerSlogan !== ''): ?>
            <p><?php echo htmlspecialchars($ecodBannerSlogan, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
    </div>
    <span class="ecod_banner__wave" aria-hidden="true"></span>
</section>
