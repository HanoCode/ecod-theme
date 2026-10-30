<?php
/**
 * 首页模块 · 合作伙伴（Logo 墙）
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR')) {
    exit;
}

/* Logo 墙数据：repeater JSON（label / href / img），与侧栏作者卡社交链接同构 */
$ecodPnLinks = [];
$ecodPnRaw = json_decode((string) ecod_p('links', ''), true);
if (is_array($ecodPnRaw)) {
    foreach ($ecodPnRaw as $ecodPnRow) {
        $ecodPnLabel = trim((string) ($ecodPnRow['label'] ?? ''));
        $ecodPnHref = trim((string) ($ecodPnRow['href'] ?? ''));
        if ($ecodPnLabel === '' || !preg_match('#^https?://#i', $ecodPnHref)) {
            continue;
        }
        $ecodPnImg = trim((string) ($ecodPnRow['img'] ?? ''));
        $ecodPnLinks[] = [
            'label' => $ecodPnLabel,
            'href'  => $ecodPnHref,
            'img'   => preg_match('#^https?://#i', $ecodPnImg) ? $ecodPnImg : '',
        ];
    }
}
if (!$ecodPnLinks) {
    return;
}
?>
<section class="ecod_section">
    <?php ecod_block_head(); ?>
    <div class="ecod_partners">
        <?php foreach ($ecodPnLinks as $ecodPnIndex => $ecodPnLink): ?>
            <a class="ecod_partner" href="<?php echo htmlspecialchars($ecodPnLink['href'], ENT_QUOTES, 'UTF-8'); ?>"
               target="_blank" rel="noopener nofollow" title="<?php echo htmlspecialchars($ecodPnLink['label'], ENT_QUOTES, 'UTF-8'); ?>">
                <span class="ecod_partner__logo" style="background:<?php echo ecod_card_tint($ecodPnIndex + 1); ?>">
                    <?php if ($ecodPnLink['img'] !== ''): ?>
                        <img src="<?php echo htmlspecialchars($ecodPnLink['img'], ENT_QUOTES, 'UTF-8'); ?>"
                             alt="" loading="lazy" decoding="async" onerror="this.remove()">
                        <i aria-hidden="true"><?php echo htmlspecialchars(mb_substr($ecodPnLink['label'], 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></i>
                    <?php else: ?>
                        <i aria-hidden="true"><?php echo htmlspecialchars(mb_substr($ecodPnLink['label'], 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></i>
                    <?php endif; ?>
                </span>
                <span class="ecod_partner__name"><?php echo htmlspecialchars($ecodPnLink['label'], ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
