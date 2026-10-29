<?php
/**
 * 页脚块 · 站点年龄与里程碑
 *
 * 两个数字都来自 ecod_site_overview()（一次查询、请求内缓存）：
 * - 已运行天数：优先取后台「建站日期」EBirth，未填则取首篇文章时间
 * - 里程碑：目标篇数由块参数给出，进度条只在前端算百分比
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 * @license   MIT
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodAgeDays = (int) ecod_p('days', 1) === 1;
$ecodAgeGoal = (int) ecod_p('goal', 0);
$ecodAgeData = ecod_site_overview();
$ecodAgePosts = (int) $ecodAgeData['posts'];

if (!$ecodAgeDays && $ecodAgeGoal <= 0) {
    return;
}

$ecodAgePercent = 0;
if ($ecodAgeGoal > 0) {
    $ecodAgePercent = min(100, (int) round($ecodAgePosts / $ecodAgeGoal * 100));
}
?>
<div class="ecod_siteage">
    <?php if ($ecodAgeDays): ?>
        <span class="ecod_siteage__days">
            本站已运行 <b><?php echo (int) $ecodAgeData['days']; ?></b> 天
        </span>
    <?php endif; ?>
    <?php if ($ecodAgeGoal > 0): ?>
        <span class="ecod_siteage__goal" title="目标 <?php echo $ecodAgeGoal; ?> 篇">
            <?php if ($ecodAgePosts >= $ecodAgeGoal): ?>
                <span>已达成 <b><?php echo $ecodAgeGoal; ?></b> 篇目标</span>
            <?php else: ?>
                <span>距 <b><?php echo $ecodAgeGoal; ?></b> 篇还有 <b><?php echo $ecodAgeGoal - $ecodAgePosts; ?></b> 篇</span>
            <?php endif; ?>
            <span class="ecod_siteage__bar" role="img"
                  aria-label="已完成 <?php echo $ecodAgePercent; ?>%">
                <i style="width:<?php echo $ecodAgePercent; ?>%"></i>
            </span>
        </span>
    <?php endif; ?>
</div>
