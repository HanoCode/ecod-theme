<?php
/**
 * Ecod Theme · 访问统计台
 *
 * 独立入口（/usr/themes/<主题>/stats.php），仅站长可用：
 *   - 今日 / 昨日 / 近 7 天 / 近 30 天 概览
 *   - PV / UV 趋势折线（内联 SVG，不引第三方图表库）
 *   - 热门页面、来源渠道、设备 / 浏览器占比
 *   - 近 N 天明细表、CSV 导出、清理过期与清空
 *
 * 首屏走服务端渲染（无 JS 也能看全）；「刷新」按钮与「自动刷新」开关
 * 走 api.php?action=stats_data 拉同一套 view 片段做局部替换，
 * 片段生成在 core/site/stats-view.php，SSR 与 Ajax 共用一份模板。
 *
 * 数据来自 core/site/stats.php 的按天聚合表（采集点在 public/header.php，
 * 停留与跳出由页脚 beacon 上报到 api.php?action=stats_ping）。
 *
 * 为什么不放进 Typecho 后台：主题无法注册后台菜单，独立入口更轻；
 * 鉴权沿用后台登录 cookie + 账户令牌，与 pay-admin.php 同一套。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @since   2026-11-01
 * @version 1.1.0
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');

$ecodRoot = __DIR__;
while ($ecodRoot !== dirname($ecodRoot) && !is_file($ecodRoot . '/config.inc.php')) {
    $ecodRoot = dirname($ecodRoot);
}
if (!is_file($ecodRoot . '/config.inc.php')) {
    http_response_code(500);
    echo 'Typecho root not found';
    exit;
}
require_once $ecodRoot . '/config.inc.php';
require_once __DIR__ . '/core/lib/schema.php';
require_once __DIR__ . '/core/site/seo.php';
require_once __DIR__ . '/core/user/account.php';
require_once __DIR__ . '/core/site/stats.php';
require_once __DIR__ . '/core/site/stats-view.php';

/* cookie 前缀：脚本在 /usr/themes/<主题>/ 下，必须按前台根目录重算 */
try {
    if (defined('__TYPECHO_ROOT_URL__')) {
        $ecodRootUrl = __TYPECHO_ROOT_URL__;
    } else {
        $ecodSecure = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;
        $ecodPrefixUrl = ($ecodSecure ? 'https' : 'http') . '://'
            . ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
        if (defined('__TYPECHO_URL_PREFIX__')) {
            $ecodPrefixUrl = rtrim(__TYPECHO_URL_PREFIX__, '/');
        }
        $ecodSuffix = '/usr/themes/' . basename(__DIR__);
        $ecodScriptDir = rtrim(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        $ecodRootUrl = $ecodPrefixUrl
            . (substr($ecodScriptDir, -strlen($ecodSuffix)) === $ecodSuffix
                ? substr($ecodScriptDir, 0, -strlen($ecodSuffix))
                : '');
    }
    \Typecho\Cookie::setPrefix($ecodRootUrl);
    if (defined('__TYPECHO_COOKIE_OPTIONS__')) {
        \Typecho\Cookie::setOptions(__TYPECHO_COOKIE_OPTIONS__);
    }
    $ecodUser = \Widget\User::alloc();
} catch (\Throwable $e) {
    http_response_code(500);
    echo 'Bootstrap failed';
    exit;
}

if (!$ecodUser->hasLogin() || !$ecodUser->pass('administrator', true)) {
    ecod_http_status(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><meta charset="utf-8"><title>403</title>'
        . '<p style="font:15px/1.7 system-ui;padding:40px">需要管理员登录。'
        . '<a href="' . htmlspecialchars((string) \Widget\Options::alloc()->siteUrl . 'admin/login.php') . '">去登录</a></p>';
    exit;
}

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$ecodToken = ecod_account_token();
$ecodFlash = '';
$ecodFlashBad = false;
$ecodSelf = (string) ($_SERVER['SCRIPT_NAME'] ?? 'stats.php');
$ecodApi = rtrim($ecodRootUrl, '/') . '/usr/themes/' . basename(__DIR__) . '/api.php';

/* ------------------------------------------------------------------
 * 动作：清理过期 / 清空 / 导出
 * ---------------------------------------------------------------- */
$ecodDo = (string) ($_GET['do'] ?? $_POST['do'] ?? '');
if ($ecodDo !== '' && !ecod_account_token_ok((string) ($_GET['token'] ?? $_POST['token'] ?? ''))) {
    $ecodFlash = '令牌校验失败，请刷新页面重试';
    $ecodFlashBad = true;
    $ecodDo = '';
}

if ($ecodDo === 'prune') {
    $ecodKeep = max(7, (int) (ecod_stats_opt('EStatsKeep', 90)));
    $ok = ecod_stats_prune($ecodKeep) > 0;
    $ecodFlash = $ok ? '已清理 ' . $ecodKeep . ' 天之前的数据' : '清理失败：统计表未就绪';
    $ecodFlashBad = !$ok;
    ecod_audit_log('stats_prune', ['keep' => $ecodKeep]);
} elseif ($ecodDo === 'reset') {
    $ok = ecod_stats_reset();
    $ecodFlash = $ok ? '统计数据已清空' : '清空失败';
    $ecodFlashBad = !$ok;
    ecod_audit_log('stats_reset', []);
} elseif ($ecodDo === 'export') {
    $rows = ecod_stats_days(90);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="ecod-stats-' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF" . "日期,PV,UV,会话,平均停留(秒),跳出率\n";
    foreach ($rows as $row) {
        $avg = $row['dur_n'] > 0 ? round($row['dur'] / $row['dur_n'], 1) : 0;
        $br = $row['sess'] > 0 ? round($row['bounce'] / $row['sess'] * 100) . '%' : '-';
        echo $row['day'] . ',' . $row['pv'] . ',' . $row['uv'] . ',' . $row['sess'] . ',' . $avg . ',' . $br . "\n";
    }
    exit;
}

/* ------------------------------------------------------------------
 * 数据（首屏 SSR，片段与 Ajax 共用 stats-view.php）
 * ---------------------------------------------------------------- */
$ecodRange = (int) ($_GET['range'] ?? 14);
$ecodRange = in_array($ecodRange, [7, 14, 30, 90], true) ? $ecodRange : 14;
$ecodView = ecod_stats_view_all($ecodRange);
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>访问统计 · <?php echo htmlspecialchars((string) \Widget\Options::alloc()->title); ?></title>
<style>
:root { --c: #4f6ef2; --c2: #7c93f8; --bd: #e4e7ee; --bg: #f5f6f9; --sf: #fff; --tx: #1e2430; --tx3: #6d7581; --ok: #10b981; --bad: #ef4444; }
* { box-sizing: border-box; }
body { margin: 0; padding: 28px 18px 60px; background: var(--bg); color: var(--tx); font: 14px/1.7 -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif; }
.wrap { max-width: 1080px; margin: 0 auto; }
.head { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 20px; }
h1 { margin: 0 0 4px; font-size: 22px; }
.sub { margin: 0; color: var(--tx3); font-size: 13px; }
.tools { margin-left: auto; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
.tools .stamp { color: var(--tx3); font-size: 12px; }
.card { margin-bottom: 18px; padding: 18px; background: var(--sf); border: 1px solid var(--bd); border-radius: 12px; }
.card h2 { margin: 0 0 14px; font-size: 15px; display: flex; align-items: center; gap: 8px; }
.card h2 .hint { margin-left: auto; font-weight: 400; }
.flash { margin-bottom: 16px; padding: 10px 14px; border-radius: 8px; background: #eafaf3; color: #067a55; font-size: 13px; }
.flash.bad { background: #fdecec; color: #b42318; }
.kpis { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 12px; margin-bottom: 18px; }
.kpi { padding: 14px 16px; background: var(--sf); border: 1px solid var(--bd); border-radius: 12px; }
.kpi small { display: block; color: var(--tx3); font-size: 12px; }
.kpi b { display: block; margin-top: 2px; font-size: 24px; font-weight: 650; letter-spacing: -0.02em; font-variant-numeric: tabular-nums; }
.kpi i { font-size: 12px; font-style: normal; color: var(--tx3); }
.kpi i.up { color: #0f9d6b; }
.kpi i.down { color: #d9534f; }
.tabs { display: flex; gap: 6px; margin-left: auto; }
.tabs a { padding: 3px 10px; border: 1px solid var(--bd); border-radius: 999px; color: var(--tx3); font-size: 12px; text-decoration: none; }
.tabs a.on { border-color: var(--c); background: var(--c); color: #fff; }
.chart { position: relative; }
.chart svg { display: block; width: 100%; height: auto; overflow: visible; }
.legend { display: flex; gap: 16px; margin-top: 6px; color: var(--tx3); font-size: 12px; }
.legend span { display: inline-flex; align-items: center; gap: 6px; }
.legend i { width: 12px; height: 3px; border-radius: 2px; background: var(--c); }
.legend i.dash { background: repeating-linear-gradient(90deg, var(--c2) 0 4px, transparent 4px 7px); }
.cols { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
th, td { padding: 8px 10px; text-align: left; border-bottom: 1px solid var(--bd); vertical-align: middle; }
th { color: var(--tx3); font-weight: 600; font-size: 12px; }
td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
tr:last-child td { border-bottom: 0; }
.bar { position: relative; height: 6px; border-radius: 999px; background: #eef1f6; overflow: hidden; }
.bar i { position: absolute; inset: 0 auto 0 0; border-radius: 999px; background: var(--c); }
.list { display: grid; gap: 10px; }
.list .row { display: grid; grid-template-columns: 1fr auto; gap: 8px; font-size: 13px; }
.list .row .k { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.list .row .v { color: var(--tx3); font-variant-numeric: tabular-nums; }
.list .bar { margin-top: 4px; grid-column: 1 / -1; }
.empty { padding: 14px; color: var(--tx3); font-size: 13px; text-align: center; }
.hint { color: var(--tx3); font-size: 12px; }
.acts { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
button, .btn { padding: 5px 12px; border: 1px solid var(--bd); border-radius: 8px; background: var(--sf); color: var(--tx); font: inherit; font-size: 12px; cursor: pointer; text-decoration: none; }
button.primary { border-color: var(--c); background: var(--c); color: #fff; }
button.danger { border-color: #f3c6c6; color: #b42318; }
button[disabled] { opacity: .55; cursor: default; }
.switch { display: inline-flex; align-items: center; gap: 6px; color: var(--tx3); font-size: 12px; cursor: pointer; user-select: none; }
.switch input { accent-color: var(--c); }
code { padding: 1px 5px; border-radius: 5px; background: #f2f4f8; font-size: 12px; }
#ecod-chart.is-refreshing, #ecod-kpis.is-refreshing { opacity: .55; transition: opacity .15s; }
@media (max-width: 900px) { .kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .cols { grid-template-columns: 1fr; } .head { flex-direction: column; } .tools { margin-left: 0; } }
</style>
</head>
<body>
<div class="wrap">
    <div class="head">
        <div>
            <h1>访问统计</h1>
            <p class="sub">按天聚合口径：PV 一次页面请求计一次（预取、爬虫、管理员不计）；UV 按访客 cookie 当天去重；在线为 5 分钟内活跃访客。</p>
        </div>
        <div class="tools">
            <span class="stamp" id="ecod-updated">首次加载 <?php echo $ecodView['time']; ?></span>
            <label class="switch"><input type="checkbox" id="ecod-auto"> 自动刷新</label>
            <button type="button" id="ecod-refresh">刷新</button>
        </div>
    </div>

    <?php if ($ecodFlash !== ''): ?>
        <div class="flash<?php echo $ecodFlashBad ? ' bad' : ''; ?>"><?php echo htmlspecialchars($ecodFlash, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="kpis" id="ecod-kpis"><?php echo $ecodView['kpis']; ?></div>

    <div class="card">
        <h2>PV / UV 趋势
            <span class="tabs">
                <?php foreach ([7, 14, 30, 90] as $ecodR): ?>
                    <a class="<?php echo $ecodR === $ecodRange ? 'on' : ''; ?>" href="?range=<?php echo $ecodR; ?>"><?php echo $ecodR; ?> 天</a>
                <?php endforeach; ?>
            </span>
        </h2>
        <div id="ecod-chart"><?php echo $ecodView['chart']; ?></div>
    </div>

    <div class="cols">
        <div class="card">
            <h2>热门页面 <span class="hint">近 7 天</span></h2>
            <div id="ecod-topPage"><?php echo $ecodView['topPage']; ?></div>
        </div>
        <div class="card">
            <h2>来源渠道 <span class="hint">近 7 天</span></h2>
            <div id="ecod-topRef"><?php echo $ecodView['topRef']; ?></div>
        </div>
    </div>

    <div class="card">
        <h2>落地页 <span class="hint">近 7 天新会话的第一页</span></h2>
        <div id="ecod-topEntry"><?php echo $ecodView['topEntry']; ?></div>
    </div>

    <div class="cols">
        <div class="card">
            <h2>设备 <span class="hint">近 7 天</span></h2>
            <div id="ecod-device"><?php echo $ecodView['device']; ?></div>
        </div>
        <div class="card">
            <h2>浏览器 <span class="hint">近 7 天</span></h2>
            <div id="ecod-browser"><?php echo $ecodView['browser']; ?></div>
        </div>
    </div>

    <div class="card">
        <h2>日明细 <span class="hint" id="ecod-range-label"><?php echo $ecodView['rangeLabel']; ?></span></h2>
        <div id="ecod-table"><?php echo $ecodView['table']; ?></div>
    </div>

    <div class="card">
        <h2>数据维护</h2>
        <div class="acts">
            <a class="btn" href="?do=export&amp;token=<?php echo urlencode($ecodToken); ?>">导出 CSV（近 90 天）</a>
            <a class="btn" href="?do=prune&amp;token=<?php echo urlencode($ecodToken); ?>"
               onclick="return confirm('清理超过 <?php echo (int) ecod_stats_opt('EStatsKeep', 90); ?> 天的数据？此操作不可撤销。')">清理过期数据</a>
            <a class="btn" href="?do=reset&amp;token=<?php echo urlencode($ecodToken); ?>"
               onclick="return confirm('清空全部统计数据？此操作不可撤销。')">清空全部</a>
            <span class="hint" style="margin-left:auto">保留期 <?php echo (int) ecod_stats_opt('EStatsKeep', 90); ?> 天，可在主题设置「互动 → 访问统计」里改</span>
        </div>
        <p class="hint" style="margin:12px 0 0">
            口径说明：PV 计数排除预取与爬虫；UV 靠 <code>ecod_vid</code> cookie 当天去重，换浏览器或清 cookie 会算新访客。
            平均停留与跳出率来自前端 beacon（离开页面时上报），关闭「停留上报」后这两列为 —。
            页面地址：<code><?php echo htmlspecialchars($ecodSelf, ENT_QUOTES, 'UTF-8'); ?></code>。
        </p>
    </div>
</div>
<script>
(function () {
    var api = <?php echo json_encode($ecodApi, JSON_UNESCAPED_SLASHES); ?>;
    var range = <?php echo (int) $ecodRange; ?>;
    var btn = document.getElementById('ecod-refresh');
    var auto = document.getElementById('ecod-auto');
    var stamp = document.getElementById('ecod-updated');
    var label = document.getElementById('ecod-range-label');
    var timer = null;
    var busy = false;

    /* 自动刷新的间隔与开关都记在本地：站长自己的偏好，不必进数据库 */
    var KEY = 'ecodStatsAuto';
    var GAP = 30000;

    function paint(d) {
        if (!d || !d.ok || !d.html) return false;
        Object.keys(d.html).forEach(function (k) {
            var el = document.getElementById('ecod-' + k);
            if (el && typeof d.html[k] === 'string') el.innerHTML = d.html[k];
        });
        if (stamp) stamp.textContent = '最后更新 ' + d.html.time;
        if (label && d.html.rangeLabel) label.textContent = d.html.rangeLabel;
        return true;
    }

    function load(silent) {
        if (busy) return;
        busy = true;
        if (!silent) {
            btn.disabled = true;
            var chart = document.getElementById('ecod-chart');
            var kpis = document.getElementById('ecod-kpis');
            if (chart) chart.classList.add('is-refreshing');
            if (kpis) kpis.classList.add('is-refreshing');
        }
        fetch(api + '?action=stats_data&range=' + range, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'Ecod-Stats' }
        }).then(function (r) {
            return r.json();
        }).then(function (d) {
            paint(d);
        }).catch(function () {
            /* 网络抖动：静默，等下一次刷新 */
        }).then(function () {
            busy = false;
            btn.disabled = false;
            var chart = document.getElementById('ecod-chart');
            var kpis = document.getElementById('ecod-kpis');
            if (chart) chart.classList.remove('is-refreshing');
            if (kpis) kpis.classList.remove('is-refreshing');
        });
    }

    function stop() {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    }

    function start() {
        stop();
        timer = setInterval(function () {
            /* 标签页在后台时不刷：省请求，回来时立刻补一次 */
            if (!document.hidden) load(true);
        }, GAP);
    }

    btn.addEventListener('click', function () {
        load(false);
    });

    auto.addEventListener('change', function () {
        try {
            localStorage.setItem(KEY, auto.checked ? '1' : '0');
        } catch (e) {}
        if (auto.checked) start(); else stop();
    });

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden && auto.checked) load(true);
    });

    /* 记住上次的选择；默认关闭，避免一进来就持续请求 */
    try {
        if (localStorage.getItem(KEY) === '1') {
            auto.checked = true;
            start();
        }
    } catch (e) {}
})();
</script>
</body>
</html>
