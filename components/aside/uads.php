<?php
/**
 * 侧栏块 · 投放广告
 *
 * 展示用户在「广告投放」页自助购买（支付 + 审核通过）且未到期的广告。
 * 广告位与定价在「设置 → 变现 → 自助广告」维护；无广告时整块不输出。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

if (!function_exists('ecod_ads_active')) {
    require_once dirname(dirname(__DIR__)) . '/core/site/ads.php';
}

/* 未开放自助广告（总开关 / 付费未开）时模块直接隐藏 */
if (!ecod_ads_enabled()) {
    ecod_block_setup_hint(ecod_pay_enabled()
        ? '自助广告未开启：在「设置 → 变现 → 自助广告」打开开关后，这里会展示已上线的广告（访客看不到本提示）。'
        : '自助广告依赖付费阅读：先在「设置 → 变现 → 付费与会员」打开付费阅读总开关，再开启「自助广告」。');
    return;
}

$ecodUadsSlot = (string) ecod_p('slot', 'sidebar');
if (!isset(ecod_ads_slots()[$ecodUadsSlot])) {
    $ecodUadsSlot = 'sidebar';
}
$ecodUadsItems = ecod_ads_active($ecodUadsSlot, max(1, min(5, (int) ecod_p('limit', 2))));
if (!$ecodUadsItems) {
    ecod_block_setup_hint('暂无投放中的广告：广告在「广告投放」页下单、支付并审核通过后才会出现在这里（访客看不到本模块）。');
    return;
}

$ecodUadsLabel = trim((string) ecod_p('label', '广告'));
?>
<section class="ecod_widget ecod_uads">
    <?php if (ecod_block_current() && (string) ecod_p('title', '') !== ''): ?>
        <h2 class="ecod_widget__title"><?php echo htmlspecialchars((string) ecod_p('title', ''), ENT_QUOTES, 'UTF-8'); ?></h2>
    <?php endif; ?>
    <div class="ecod_uads__list">
        <?php foreach ($ecodUadsItems as $ecodUad): ?>
            <a class="ecod_uads__item" href="<?php echo htmlspecialchars(ecod_go_url((string) $ecodUad['link']), ENT_QUOTES, 'UTF-8'); ?>"
               target="_blank" rel="noopener nofollow sponsored">
                <img src="<?php echo htmlspecialchars((string) $ecodUad['image'], ENT_QUOTES, 'UTF-8'); ?>"
                     alt="<?php echo htmlspecialchars((string) $ecodUad['title'], ENT_QUOTES, 'UTF-8'); ?>"
                     width="640" height="280" loading="lazy" decoding="async">
                <?php if ($ecodUadsLabel !== ''): ?>
                    <i class="ecod_uads__flag"><?php echo htmlspecialchars($ecodUadsLabel, ENT_QUOTES, 'UTF-8'); ?></i>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>
