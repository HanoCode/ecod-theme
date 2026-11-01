<?php
/**
 * 侧栏块 · 访问统计
 *
 * 今日 PV / UV / 在线 + 近 N 天 PV 趋势折线（内联 SVG，无第三方库）。
 * 数据与后台 stats.php 同源（core/site/stats.php 按天聚合表）；
 * 统计总开关（EStats）关闭时整块不渲染。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @since   2026-11-01
 * @version 1.1.0
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

if (!ecod_stats_enabled()) {
    return;
}

$ecodTrafficDays = (int) ecod_p('days', 7);
$ecodTrafficDays = in_array($ecodTrafficDays, [7, 14, 30], true) ? $ecodTrafficDays : 7;
$ecodTrafficShow = ecod_p('online', 1) == 1;
$ecodTrafficToday = ecod_stats_day(ecod_stats_today());
$ecodTrafficSeries = ecod_stats_days($ecodTrafficDays);

/* 峰值与合计：峰值决定折线纵向比例，合计写进脚注 */
$ecodTrafficMax = 1;
$ecodTrafficSum = 0;
$ecodTrafficPts = [];
foreach ($ecodTrafficSeries as $ecodRow) {
    $ecodTrafficMax = max($ecodTrafficMax, (int) $ecodRow['pv']);
    $ecodTrafficSum += (int) $ecodRow['pv'];
}

/* 坐标：viewBox 100×34，纵向有效区 3（顶部留白）～30（基线） */
$ecodTrafficN = max(1, count($ecodTrafficSeries));
foreach (array_values($ecodTrafficSeries) as $ecodI => $ecodRow) {
    $ecodX = $ecodTrafficN > 1 ? $ecodI * 100 / ($ecodTrafficN - 1) : 50;
    $ecodY = 30 - (int) $ecodRow['pv'] / $ecodTrafficMax * 27;
    $ecodTrafficPts[] = [round($ecodX, 2), round($ecodY, 2)];
}

/* Catmull-Rom 转三次贝塞尔：折线拐角变成顺滑趋势线 */
$ecodTrafficF = static function (float $v): string {
    return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
};
$ecodTrafficLine = 'M' . $ecodTrafficF($ecodTrafficPts[0][0]) . ',' . $ecodTrafficF($ecodTrafficPts[0][1]);
for ($ecodI = 1; $ecodI < $ecodTrafficN; $ecodI++) {
    $ecodP0 = $ecodTrafficPts[$ecodI - 2] ?? $ecodTrafficPts[$ecodI - 1];
    $ecodP1 = $ecodTrafficPts[$ecodI - 1];
    $ecodP2 = $ecodTrafficPts[$ecodI];
    $ecodP3 = $ecodTrafficPts[$ecodI + 1] ?? $ecodP2;

    $ecodC1x = $ecodP1[0] + ($ecodP2[0] - $ecodP0[0]) / 6;
    $ecodC1y = max(2, min(30, $ecodP1[1] + ($ecodP2[1] - $ecodP0[1]) / 6));
    $ecodC2x = $ecodP2[0] - ($ecodP3[0] - $ecodP1[0]) / 6;
    $ecodC2y = max(2, min(30, $ecodP2[1] - ($ecodP3[1] - $ecodP1[1]) / 6));

    $ecodTrafficLine .= 'C' . $ecodTrafficF($ecodC1x) . ',' . $ecodTrafficF($ecodC1y)
        . ' ' . $ecodTrafficF($ecodC2x) . ',' . $ecodTrafficF($ecodC2y)
        . ' ' . $ecodTrafficF($ecodP2[0]) . ',' . $ecodTrafficF($ecodP2[1]);
}
$ecodTrafficArea = $ecodTrafficLine . 'L100,33 L0,33 Z';

/* 渐变 id 需唯一：同页放多个本组件时不互相串色 */
$ecodTrafficSeq = (int) ($GLOBALS['ecodTrafficSeq'] ?? 0) + 1;
$GLOBALS['ecodTrafficSeq'] = $ecodTrafficSeq;
$ecodTrafficGid = 'ecodTrafficGrad' . $ecodTrafficSeq;

$ecodTrafficStep = $ecodTrafficN > 1 ? 100 / ($ecodTrafficN - 1) : 100;
?>
<section class="ecod_widget">
    <h2 class="ecod_widget__title"><?php echo htmlspecialchars((string) ecod_p('title', '访问统计')); ?></h2>
    <div class="ecod_traffic">
        <div class="ecod_traffic__kpis" style="grid-template-columns:repeat(<?php echo $ecodTrafficShow ? 3 : 2; ?>,minmax(0,1fr))">
            <div class="ecod_traffic__kpi">
                <b><?php echo number_format((int) $ecodTrafficToday['pv']); ?></b>
                <span>今日 PV</span>
            </div>
            <div class="ecod_traffic__kpi">
                <b><?php echo number_format((int) $ecodTrafficToday['uv']); ?></b>
                <span>今日 UV</span>
            </div>
            <?php if ($ecodTrafficShow): ?>
                <div class="ecod_traffic__kpi">
                    <b><?php echo number_format(ecod_stats_online()); ?></b>
                    <span>在线</span>
                </div>
            <?php endif; ?>
        </div>
        <div class="ecod_traffic__chart">
            <svg class="ecod_traffic__svg" viewBox="0 0 100 34" preserveAspectRatio="none"
                 role="img" aria-label="近 <?php echo $ecodTrafficDays; ?> 天 PV 趋势">
                <defs>
                    <linearGradient id="<?php echo $ecodTrafficGid; ?>" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" class="ecod_traffic__stop-a"/>
                        <stop offset="100%" class="ecod_traffic__stop-b"/>
                    </linearGradient>
                </defs>
                <path class="ecod_traffic__area" fill="url(#<?php echo $ecodTrafficGid; ?>)"
                      d="<?php echo $ecodTrafficArea; ?>"/>
                <path class="ecod_traffic__line" vector-effect="non-scaling-stroke"
                      d="<?php echo $ecodTrafficLine; ?>"/>
                <?php foreach (array_values($ecodTrafficSeries) as $ecodI => $ecodRow): ?>
                    <?php
                    $ecodXa = max(0, $ecodTrafficPts[$ecodI][0] - $ecodTrafficStep / 2);
                    $ecodXb = min(100, $ecodTrafficPts[$ecodI][0] + $ecodTrafficStep / 2);
                    ?>
                    <rect class="ecod_traffic__hit" x="<?php echo $ecodTrafficF($ecodXa); ?>"
                          y="0" width="<?php echo $ecodTrafficF($ecodXb - $ecodXa); ?>" height="34">
                        <title><?php echo htmlspecialchars($ecodRow['day']); ?> · <?php echo number_format((int) $ecodRow['pv']); ?> PV · <?php echo number_format((int) $ecodRow['uv']); ?> UV</title>
                    </rect>
                <?php endforeach; ?>
            </svg>
        </div>
        <div class="ecod_traffic__foot">
            <span class="ecod_traffic__note">近 <?php echo $ecodTrafficDays; ?> 天 PV</span>
            <span class="ecod_traffic__sum"><?php echo number_format($ecodTrafficSum); ?></span>
        </div>
    </div>
</section>
