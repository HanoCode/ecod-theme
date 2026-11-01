<?php
/**
 * Ecod Theme · 访问统计的展示片段
 *
 * 只负责「把数据画成 HTML」，不查库、不判断权限：
 *   - stats.php 首屏直接 echo（无 JS 也能看全）
 *   - api.php?action=stats_data 用同一批函数生成片段回给前端做局部替换
 * 两处共用一套标记，避免 SSR 与 Ajax 两套模板慢慢长歪。
 *
 * 所有输出都已转义；调用方只传数据数组。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @since   2026-11-01
 * @version 1.0.0
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/** 设备维度取值的中文名 */
function ecod_stats_device_name(string $key): string
{
    $map = ['desktop' => '桌面', 'mobile' => '手机', 'tablet' => '平板', 'unknown' => '未知'];
    return $map[$key] ?? $key;
}

/** 平均停留（秒，一位小数） */
function ecod_stats_avg_dur(array $row): float
{
    return $row['dur_n'] > 0 ? round($row['dur'] / $row['dur_n'], 1) : 0.0;
}

/** 跳出率（%） */
function ecod_stats_bounce_rate(array $row): float
{
    return $row['sess'] > 0 ? round($row['bounce'] / $row['sess'] * 100, 1) : 0.0;
}

/**
 * 环比文案：[文字, 方向 up|down|flat]
 */
function ecod_stats_delta(int $now, int $prev): array
{
    if ($prev <= 0) {
        return [$now > 0 ? '新增' : '—', $now > 0 ? 'up' : 'flat'];
    }
    $pct = ($now - $prev) / $prev * 100;
    if (abs($pct) < 0.5) {
        return ['持平', 'flat'];
    }
    return [($pct > 0 ? '+' : '') . round($pct, 1) . '%', $pct > 0 ? 'up' : 'down'];
}

/** 概览卡：6 个 KPI */
function ecod_stats_view_kpis(array $ctx): string
{
    $today = $ctx['today'];
    $yday = $ctx['yday'];
    $sum7 = $ctx['sum7'];
    $sum30 = $ctx['sum30'];
    $online = (int) $ctx['online'];

    [$pvDelta, $pvDir] = ecod_stats_delta((int) $today['pv'], (int) $yday['pv']);
    [$uvDelta, $uvDir] = ecod_stats_delta((int) $today['uv'], (int) $yday['uv']);

    $cells = [
        ['今日 PV', number_format((int) $today['pv']), $pvDelta . ' 较昨日', $pvDir],
        ['今日 UV', number_format((int) $today['uv']), $uvDelta . ' 较昨日', $uvDir],
        ['当前在线', number_format($online), '5 分钟窗口', ''],
        ['近 7 天 PV', number_format((int) $sum7['pv']), '日均 ' . number_format((int) round($sum7['pv'] / 7)), ''],
        ['近 7 天 UV', number_format((int) $sum7['uv']), '日均 ' . number_format((int) round($sum7['uv'] / 7)), ''],
        ['近 30 天 PV', number_format((int) $sum30['pv']), 'UV ' . number_format((int) $sum30['uv']), ''],
    ];

    $html = '';
    foreach ($cells as $cell) {
        $html .= '<div class="kpi"><small>' . htmlspecialchars($cell[0], ENT_QUOTES, 'UTF-8') . '</small>'
            . '<b>' . $cell[1] . '</b>'
            . '<i class="' . htmlspecialchars($cell[3], ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($cell[2], ENT_QUOTES, 'UTF-8') . '</i></div>';
    }
    return $html;
}

/**
 * PV/UV 趋势折线（内联 SVG，按序列峰值归一）
 *
 * @param array<int, array<string, mixed>> $series 连续日期序列
 */
function ecod_stats_view_chart(array $series, string $rangeLabel): string
{
    $total = 0;
    $maxV = 1;
    foreach ($series as $row) {
        $total += (int) $row['pv'];
        $maxV = max($maxV, (int) $row['pv'], (int) $row['uv']);
    }
    if ($total === 0) {
        return '<div class="empty">还没有数据。开启「访问统计」后，前台访问会自动累计（先访问几页看看）。</div>';
    }

    $w = 720;
    $h = 200;
    $pad = 14;
    $n = count($series);

    $points = static function (string $field) use ($series, $n, $w, $h, $pad, $maxV): array {
        $pts = [];
        foreach ($series as $i => $row) {
            $x = $n > 1 ? $i / ($n - 1) * $w : $w / 2;
            $y = $h - $pad - ((int) $row[$field] / $maxV) * ($h - $pad * 2);
            $pts[] = [round($x, 1), round($y, 1)];
        }
        return $pts;
    };
    $line = static function (array $pts): string {
        $d = '';
        foreach ($pts as $i => $p) {
            $d .= ($i === 0 ? 'M' : 'L') . $p[0] . ' ' . $p[1];
        }
        return $d;
    };

    $pvPts = $points('pv');
    $uvPts = $points('uv');
    $area = $line($pvPts) . 'L' . $w . ' ' . $h . 'L0 ' . $h . 'Z';

    $html = '<div class="chart"><svg viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" role="img"'
        . ' aria-label="' . htmlspecialchars($rangeLabel, ENT_QUOTES, 'UTF-8') . ' PV/UV 趋势">'
        . '<defs><linearGradient id="ecodAreaFill" x1="0" y1="0" x2="0" y2="1">'
        . '<stop offset="0%" stop-color="#4f6ef2" stop-opacity="0.22"/>'
        . '<stop offset="100%" stop-color="#4f6ef2" stop-opacity="0"/></linearGradient></defs>';

    for ($g = 0; $g <= 3; $g++) {
        $gy = round($pad + $g * ($h - $pad * 2) / 3, 1);
        $html .= '<line x1="0" y1="' . $gy . '" x2="' . $w . '" y2="' . $gy . '" stroke="#eef1f6" stroke-width="1"/>';
    }

    $html .= '<path d="' . $area . '" fill="url(#ecodAreaFill)"/>'
        . '<path d="' . $line($pvPts) . '" fill="none" stroke="#4f6ef2" stroke-width="2"'
        . ' stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>'
        . '<path d="' . $line($uvPts) . '" fill="none" stroke="#7c93f8" stroke-width="2"'
        . ' stroke-dasharray="5 4" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>';

    foreach ($series as $i => $row) {
        $html .= '<circle cx="' . $pvPts[$i][0] . '" cy="' . $pvPts[$i][1] . '" r="2.5" fill="#4f6ef2">'
            . '<title>' . htmlspecialchars($row['day'], ENT_QUOTES, 'UTF-8') . ' · PV ' . (int) $row['pv']
            . ' · UV ' . (int) $row['uv'] . '</title></circle>';
    }

    $html .= '</svg><div class="legend"><span><i></i>PV</span><span><i class="dash"></i>UV</span>'
        . '<span style="margin-left:auto">峰值 ' . number_format($maxV) . '</span></div></div>';

    return $html;
}

/** 排行列表（热门页面 / 来源）：占比条按本列表峰值归一 */
function ecod_stats_view_rank(array $rows, string $valueSuffix = 'PV'): string
{
    if (!$rows) {
        return '<div class="empty">暂无数据</div>';
    }
    $max = 1;
    foreach ($rows as $r) {
        $max = max($max, (int) $r['pv']);
    }

    $html = '<div class="list">';
    foreach ($rows as $row) {
        $k = (string) $row['k'];
        $value = $valueSuffix === 'PV'
            ? number_format((int) $row['pv']) . ' / ' . number_format((int) $row['uv']) . ' UV'
            : number_format((int) $row['pv']) . ' ' . $valueSuffix;
        $html .= '<div class="row">'
            . '<span class="k" title="' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '</span>'
            . '<span class="v">' . $value . '</span>'
            . '<div class="bar"><i style="width:' . round((int) $row['pv'] / $max * 100) . '%"></i></div>'
            . '</div>';
    }
    return $html . '</div>';
}

/** 占比列表（设备 / 浏览器）：显示百分比，条宽按本列表峰值归一 */
function ecod_stats_view_share(array $rows, bool $deviceName = false): string
{
    if (!$rows) {
        return '<div class="empty">暂无数据</div>';
    }
    $total = 0;
    $max = 1;
    foreach ($rows as $r) {
        $total += (int) $r['pv'];
        $max = max($max, (int) $r['pv']);
    }

    $html = '<div class="list">';
    foreach ($rows as $row) {
        $k = (string) $row['k'];
        $label = $deviceName ? ecod_stats_device_name($k) : $k;
        $pct = $total > 0 ? (int) round((int) $row['pv'] / $total * 100) : 0;
        $html .= '<div class="row">'
            . '<span class="k">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>'
            . '<span class="v">' . $pct . '%</span>'
            . '<div class="bar"><i style="width:' . round((int) $row['pv'] / $max * 100) . '%"></i></div>'
            . '</div>';
    }
    return $html . '</div>';
}

/** 日明细表（最新在上） */
function ecod_stats_view_table(array $series): string
{
    $today = ecod_stats_today();
    $rows = '';
    foreach (array_reverse($series) as $row) {
        $rows .= '<tr>'
            . '<td>' . htmlspecialchars((string) $row['day'], ENT_QUOTES, 'UTF-8')
            . ($row['day'] === $today ? ' <span class="hint">今天</span>' : '') . '</td>'
            . '<td class="num">' . number_format((int) $row['pv']) . '</td>'
            . '<td class="num">' . number_format((int) $row['uv']) . '</td>'
            . '<td class="num">' . number_format((int) $row['sess']) . '</td>'
            . '<td class="num">' . ((int) $row['dur_n'] > 0 ? ecod_stats_avg_dur($row) . 's' : '—') . '</td>'
            . '<td class="num">' . ((int) $row['sess'] > 0 ? ecod_stats_bounce_rate($row) . '%' : '—') . '</td>'
            . '</tr>';
    }

    return '<table><thead><tr><th>日期</th><th class="num">PV</th><th class="num">UV</th>'
        . '<th class="num">会话</th><th class="num">平均停留</th><th class="num">跳出率</th>'
        . '</tr></thead><tbody>' . $rows . '</tbody></table>';
}

/**
 * 一次取齐页面需要的全部片段
 *
 * @return array<string, string|int|string>
 */
function ecod_stats_view_all(int $range = 14): array
{
    $range = in_array($range, [7, 14, 30, 90], true) ? $range : 14;
    $labels = [7 => '近 7 天', 14 => '近 14 天', 30 => '近 30 天', 90 => '近 90 天'];

    $series = ecod_stats_days($range);
    $sum7 = ecod_stats_sum(7);
    $sum30 = ecod_stats_sum(30);

    return [
        'range'      => $range,
        'rangeLabel' => $labels[$range],
        'kpis'       => ecod_stats_view_kpis([
            'today'  => ecod_stats_day(ecod_stats_today()),
            'yday'   => ecod_stats_day(ecod_stats_day_ago(1)),
            'sum7'   => $sum7,
            'sum30'  => $sum30,
            'online' => ecod_stats_online(),
        ]),
        'chart'      => ecod_stats_view_chart($series, $labels[$range]),
        'topPage'    => ecod_stats_view_rank(ecod_stats_top('page', 7, 10)),
        'topRef'     => ecod_stats_view_rank(ecod_stats_top('ref', 7, 8), 'PV'),
        'topEntry'   => ecod_stats_view_rank(ecod_stats_top('entry', 7, 6), '次进入'),
        'device'     => ecod_stats_view_share(ecod_stats_top('device', 7, 5), true),
        'browser'    => ecod_stats_view_share(ecod_stats_top('browser', 7, 6)),
        'table'      => ecod_stats_view_table($series),
        'time'       => ecod_stats_date('H:i:s'),
    ];
}
