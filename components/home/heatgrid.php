<?php
/**
 * 首页模块 · 更新热力图（GitHub contributions 式发文日历）
 *
 * 数据：contents 表按日聚合已发布文章数，文件缓存 1 小时。
 * 渲染：服务端输出全部格子 DOM（JS 只做读数交互，PJAX 换页无闪烁）。
 * 着色：5 档（0 空 + 1-4 按「最忙一天」归一），全部走主题色派生，
 *       亮暗色模式随 --ecod-primary / --ecod-bg 自动适配。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodHgWeeks = max(12, min(52, (int) ecod_p('weeks', 18)));

/* 聚合窗口：以本周为最后一列，向前推 weeks 周，起始对齐周一 */
$ecodHgToday = strtotime('today');
$ecodHgMonday = $ecodHgToday - ((int) date('N', $ecodHgToday) - 1) * 86400;
$ecodHgStart = $ecodHgMonday - ($ecodHgWeeks - 1) * 7 * 86400;
$ecodHgStartStr = date('Y-m-d', $ecodHgStart);
$ecodHgTodayStr = date('Y-m-d', $ecodHgToday);

/* 每日发文数：文件缓存（键走 md5，不受 options.name 32 字符限制），1 小时过期 */
$ecodHgPerDay = ecod_cache_remember('heatgrid:v1:' . $ecodHgWeeks, 3600, static function () use ($ecodHgStart) {
    $days = [];
    try {
        $db = \Typecho\Db::get();
        $rows = $db->fetchAll(
            $db->select('created')->from('table.contents')
                ->where('type = ?', 'post')->where('status = ?', 'publish')
                ->where('created >= ?', $ecodHgStart)
        );
        foreach ($rows as $row) {
            $key = date('Y-m-d', (int) $row['created']);
            $days[$key] = ($days[$key] ?? 0) + 1;
        }
    } catch (\Throwable $e) {
        // 静默降级：无数据时渲染全空网格
    }
    return $days;
});

/* 格子数据：按列（周）填充，周一为每列首行；未来日期标 is-future */
$ecodHgTotal = 0;
$ecodHgMax = 0;
$ecodHgCells = [];
for ($i = 0; $i < $ecodHgWeeks * 7; $i++) {
    $ts = $ecodHgStart + $i * 86400;
    $str = date('Y-m-d', $ts);
    $n = (int) ($ecodHgPerDay[$str] ?? 0);
    if ($str > $ecodHgTodayStr) {
        $ecodHgCells[] = ['v' => 0, 'l' => -1, 'i' => $i, 'future' => true];
        continue;
    }
    $ecodHgTotal += $n;
    if ($n > $ecodHgMax) {
        $ecodHgMax = $n;
    }
    $ecodHgCells[] = ['v' => $n, 'l' => 0, 'i' => $i, 'future' => false];
}
if ($ecodHgMax > 0) {
    foreach ($ecodHgCells as $idx => $cell) {
        if (!$cell['future'] && $cell['v'] > 0) {
            $ecodHgCells[$idx]['l'] = (int) ceil($cell['v'] / $ecodHgMax * 4);
        }
    }
}
$ecodHgActiveDays = 0;
foreach ($ecodHgCells as $cell) {
    if (!$cell['future'] && $cell['v'] > 0) {
        $ecodHgActiveDays++;
    }
}
?>
<section class="ecod_widget ecod_heatgrid" data-ecod-heatgrid data-start="<?php echo $ecodHgStartStr; ?>" style="--hg-cols: <?php echo (int) $ecodHgWeeks; ?>;" role="img" aria-label="<?php echo htmlspecialchars(sprintf('更新日志：近 %d 周 %d 天更新 %d 篇', $ecodHgWeeks, $ecodHgActiveDays, $ecodHgTotal)); ?>">
    <h2 class="ecod_widget__title"><?php echo htmlspecialchars((string) ecod_p('title', '更新日志')); ?></h2>
    <div class="ecod_heatgrid__head">
        <p class="ecod_heatgrid__sub"><?php echo htmlspecialchars(sprintf('近 %d 周 · 拖动查看某天', $ecodHgWeeks)); ?></p>
        <b class="ecod_heatgrid__total"><?php echo number_format($ecodHgTotal); ?></b>
    </div>
    <p class="ecod_heatgrid__readout" aria-live="polite"><?php echo htmlspecialchars(sprintf('%d 天有更新', $ecodHgActiveDays)); ?></p>
    <div class="ecod_heatgrid__wrap">
        <div class="ecod_heatgrid__rows" aria-hidden="true">
            <span>一</span><span></span><span>三</span><span></span><span>五</span><span></span><span></span>
        </div>
        <div class="ecod_heatgrid__grid" aria-hidden="true">
            <?php foreach ($ecodHgCells as $ecodHgCell): ?>
                <?php if ($ecodHgCell['future']): ?>
                    <i class="ecod_heatgrid__cell is-future"></i>
                <?php else: ?>
                    <i class="ecod_heatgrid__cell" data-v="<?php echo (int) $ecodHgCell['v']; ?>" data-l="<?php echo (int) $ecodHgCell['l']; ?>" style="--i: <?php echo (int) $ecodHgCell['i']; ?>"></i>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="ecod_heatgrid__legend" aria-hidden="true">
        <span>少</span>
        <i data-l="0"></i><i data-l="1"></i><i data-l="2"></i><i data-l="3"></i><i data-l="4"></i>
        <span>多</span>
    </div>
</section>
