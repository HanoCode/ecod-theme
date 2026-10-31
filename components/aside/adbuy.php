<?php
/**
 * 侧栏块 · 自助广告（挂件版，参考 guaqi「自助开通广告」组件）
 *
 * 列出开放售卖的广告位价目卡，点击进入「广告投放」页并预选该位子；
 * 已购上线的广告由「投放广告」（uads）模块展示，两者互不占用。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

if (!function_exists('ecod_ads_price')) {
    require_once dirname(dirname(__DIR__)) . '/core/site/ads.php';
}

if (!ecod_ads_enabled()) {
    ecod_block_setup_hint(ecod_pay_enabled()
        ? '自助广告未开启：在「设置 → 变现 → 自助广告」打开开关后，这里会显示价目卡（访客看不到本提示）。'
        : '自助广告依赖付费阅读：先在「设置 → 变现 → 付费与会员」打开付费阅读总开关，再开启「自助广告」。');
    return;
}

$ecodAbPageUrl = ecod_template_page_url('page-ads.php');
$ecodAbSlots = ecod_ads_slots();
/* 广告位规格比例（guaqi 式「4:1」角标）：通栏 4:1，侧栏卡片 16:7 */
$ecodAbRatios = [
    'home_top'    => '4:1',
    'post_bottom' => '4:1',
    'sidebar'     => '16:7',
];
$ecodAbItems = [];
foreach ($ecodAbSlots as $ecodAbKey => $ecodAbName) {
    $ecodAbCents = ecod_ads_price($ecodAbKey);
    if ($ecodAbCents !== null) {
        $ecodAbItems[] = [
            'key'   => $ecodAbKey,
            'name'  => $ecodAbName,
            'money' => ecod_pay_money($ecodAbCents),
            'ratio' => $ecodAbRatios[$ecodAbKey] ?? '',
        ];
    }
    if (count($ecodAbItems) >= max(1, min(6, (int) ecod_p('limit', 3)))) {
        break;
    }
}
if (!$ecodAbItems) {
    ecod_block_setup_hint('还没有可售卖的广告位：在「设置 → 变现 → 自助广告」里至少给一个广告位定价（留空 = 不开放该位）。');
    return;
}

$ecodAbNote = trim((string) ecod_p('note', ''));
?>
<section class="ecod_widget ecod_adbuy">
    <?php ecod_block_head(['more_url' => $ecodAbPageUrl !== '' ? $ecodAbPageUrl : null]); ?>
    <div class="ecod_adbuy__list">
        <?php foreach ($ecodAbItems as $ecodAbItem): ?>
            <?php if ($ecodAbPageUrl !== ''): ?>
                <a class="ecod_adbuy__item" href="<?php echo htmlspecialchars($ecodAbPageUrl . (strpos($ecodAbPageUrl, '?') === false ? '?' : '&') . 'slot=' . rawurlencode($ecodAbItem['key']), ENT_QUOTES, 'UTF-8'); ?>">
            <?php else: ?>
                <div class="ecod_adbuy__item">
            <?php endif; ?>
                <?php if ($ecodAbItem['ratio'] !== ''): ?>
                    <i class="ecod_adbuy__ratio"><?php echo htmlspecialchars($ecodAbItem['ratio'], ENT_QUOTES, 'UTF-8'); ?></i>
                <?php endif; ?>
                <b><?php echo htmlspecialchars($ecodAbItem['name'], ENT_QUOTES, 'UTF-8'); ?></b>
                <span><?php echo ecod_pay_currency() . $ecodAbItem['money']; ?> / <?php echo ecod_ads_unit()['label']; ?></span>
                <em><?php echo $ecodAbPageUrl !== '' ? '点击开通' : '暂未开放'; ?><?php echo $ecodAbPageUrl !== '' ? ecod_icon('chevron', 12) : ''; ?></em>
            <?php echo $ecodAbPageUrl !== '' ? '</a>' : '</div>'; ?>
        <?php endforeach; ?>
    </div>
    <?php if ($ecodAbNote !== ''): ?>
        <p class="ecod_adbuy__note"><?php echo htmlspecialchars($ecodAbNote, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
</section>
