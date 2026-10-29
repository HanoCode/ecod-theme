<?php
/**
 * Ecod Theme · 付费管理台
 *
 * 独立入口（/usr/themes/<主题>/pay-admin.php），仅站长可用：
 *   - 待确认订单 → 确认收款（结算发权益）
 *   - 手动授予 / 撤销权益（会员、单篇）
 *   - 卡密批次生成（明文只显示这一次，库里只存 SHA-256）
 *
 * 为什么不放进 Typecho 后台：主题无法注册后台菜单，独立入口更轻；
 * 鉴权沿用后台登录 cookie + 账户令牌，与其他写接口同一套。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 * @license   MIT
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
require_once __DIR__ . '/core/schema.php';
require_once __DIR__ . '/core/seo.php';
require_once __DIR__ . '/core/account.php';
require_once __DIR__ . '/core/pay.php';
require_once __DIR__ . '/core/pay-channel.php';

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

$ecodIsAdmin = $ecodUser->hasLogin() && $ecodUser->pass('administrator', true);
if (!$ecodIsAdmin) {
    /* Typecho 引导后原生 http_response_code 会被 ob 回调重写回 200，走双写 */
    ecod_http_status(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><meta charset="utf-8"><title>403</title>'
        . '<p style="font:15px/1.7 system-ui;padding:40px">需要管理员登录。'
        . '<a href="' . htmlspecialchars((string) \Widget\Options::alloc()->siteUrl . 'admin/login.php') . '">去登录</a></p>';
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$ecodDb = \Typecho\Db::get();
$ecodToken = ecod_account_token();
$ecodFlash = '';
$ecodFlashBad = false;
$ecodGenerated = [];

/* ------------------------------------------------------------------
 * 动作
 * ---------------------------------------------------------------- */
$ecodAction = (string) ($_POST['do'] ?? '');
if ($ecodAction !== '') {
    if (!ecod_account_token_ok((string) ($_POST['token'] ?? ''))) {
        $ecodFlash = '令牌校验失败，请刷新页面重试';
        $ecodFlashBad = true;
    } elseif ($ecodAction === 'settle') {
        $orderNo = trim((string) ($_POST['order_no'] ?? ''));
        $result = ecod_pay_settle($orderNo, 'admin-' . (int) $ecodUser->uid, 'manual');
        if (!empty($result['ok'])) {
            ecod_audit_log('pay_settle', ['order' => $orderNo, 'via' => 'admin']);
        }
        $ecodFlash = !empty($result['ok'])
            ? '已确认收款：' . $orderNo . '（权益已发放）'
            : '结算失败：' . ($result['message'] ?? '未知错误');
        $ecodFlashBad = empty($result['ok']);
    } elseif ($ecodAction === 'close') {
        $orderNo = trim((string) ($_POST['order_no'] ?? ''));
        try {
            $ecodDb->query($ecodDb->update('table.ecod_orders')->rows(['status' => 'closed'])
                ->where('order_no = ?', $orderNo)->where('status = ?', 'pending'));
            $ecodFlash = '订单已关闭：' . $orderNo;
        } catch (\Throwable $e) {
            $ecodFlash = '关闭失败';
            $ecodFlashBad = true;
        }
    } elseif ($ecodAction === 'grant') {
        $targetUser = trim((string) ($_POST['user'] ?? ''));
        $uid = 0;
        if (ctype_digit($targetUser)) {
            $uid = (int) $targetUser;
        } elseif ($targetUser !== '') {
            $row = $ecodDb->fetchRow($ecodDb->select('uid')->from('table.users')->where('name = ?', $targetUser)->limit(1));
            $uid = $row ? (int) $row['uid'] : 0;
        }
        $kind = (string) ($_POST['kind'] ?? 'vip') === 'post' ? 'post' : 'vip';
        $target = (int) ($_POST['target'] ?? 0);
        $level = max(1, min(2, (int) ($_POST['level'] ?? 1)));
        $days = max(0, (int) ($_POST['days'] ?? 0));

        if ($uid <= 0) {
            $ecodFlash = '用户不存在';
            $ecodFlashBad = true;
        } elseif ($kind === 'post' && $target <= 0) {
            $ecodFlash = '请填写文章 ID';
            $ecodFlashBad = true;
        } elseif (ecod_pay_grant([
            'uid' => $uid, 'kind' => $kind, 'target' => $target,
            'level' => $level, 'days' => $days, 'source' => 'admin', 'source_id' => (string) $ecodUser->uid,
        ])) {
            $ecodFlash = '已授予：uid=' . $uid . ' / ' . ($kind === 'vip' ? '会员 Lv.' . $level : '文章 #' . $target)
                . ($days > 0 ? ' / ' . $days . ' 天' : ' / 永久');
            ecod_audit_log('pay_grant', ['to' => $uid, 'kind' => $kind, 'target' => $target, 'level' => $level, 'days' => $days]);
        } else {
            $ecodFlash = '授予失败';
            $ecodFlashBad = true;
        }
    } elseif ($ecodAction === 'revoke') {
        $uid = (int) ($_POST['uid'] ?? 0);
        $kind = (string) ($_POST['kind'] ?? 'vip') === 'post' ? 'post' : 'vip';
        $target = (int) ($_POST['target'] ?? 0);
        $ecodRevoked = ecod_pay_revoke($uid, $kind, $target);
        if ($ecodRevoked) {
            ecod_audit_log('pay_revoke', ['from' => $uid, 'kind' => $kind, 'target' => $target]);
        }
        $ecodFlash = $ecodRevoked ? '已撤销' : '撤销失败';
        $ecodFlashBad = !$ecodRevoked;
    } elseif ($ecodAction === 'cards') {
        $result = ecod_pay_card_batch([
            'count'  => (int) ($_POST['count'] ?? 1),
            'kind'   => (string) ($_POST['kind'] ?? 'vip'),
            'target' => (int) ($_POST['target'] ?? 0),
            'level'  => (int) ($_POST['level'] ?? 1),
            'days'   => (int) ($_POST['days'] ?? 0),
            'batch'  => (string) ($_POST['batch'] ?? ''),
        ]);
        if (!empty($result['ok'])) {
            $ecodGenerated = $result['codes'];
            $ecodFlash = '已生成 ' . count($result['codes']) . ' 张卡密（批次 ' . $result['batch'] . '），请立即复制保存';
            ecod_audit_log('card_batch', ['n' => count($result['codes']), 'kind' => $result['kind'] ?? '', 'batch' => $result['batch'] ?? '']);
        } else {
            $ecodFlash = '生成失败：' . ($result['error'] ?? '未知错误');
            $ecodFlashBad = true;
        }
    }
}

/* ------------------------------------------------------------------
 * 数据
 * ---------------------------------------------------------------- */
$ecodPending = [];
$ecodRecent = [];
$ecodUnused = 0;
try {
    $ecodPending = $ecodDb->fetchAll(
        $ecodDb->select()->from('table.ecod_orders')->where('status = ?', 'pending')
            ->order('id', \Typecho\Db::SORT_DESC)->limit(50)
    ) ?: [];
    $ecodRecent = $ecodDb->fetchAll(
        $ecodDb->select()->from('table.ecod_orders')->where('status <> ?', 'pending')
            ->order('id', \Typecho\Db::SORT_DESC)->limit(30)
    ) ?: [];
    $row = $ecodDb->fetchObject(
        $ecodDb->select('COUNT(*) AS n')->from('table.ecod_pay_cards')->where('status = ?', 'unused')
    );
    $ecodUnused = $row ? (int) $row->n : 0;
} catch (\Throwable $e) {
    $ecodPending = [];
}

/** 用户名缓存 */
$ecodNames = [];
function ecod_pay_admin_name(\Typecho\Db $db, int $uid)
{
    global $ecodNames;
    if ($uid <= 0) {
        return '—';
    }
    if (!isset($ecodNames[$uid])) {
        try {
            $row = $db->fetchRow($db->select('name', 'screenName')->from('table.users')->where('uid = ?', $uid)->limit(1));
            $ecodNames[$uid] = $row ? ((string) $row['screenName'] !== '' ? (string) $row['screenName'] : (string) $row['name']) : ('#' . $uid);
        } catch (\Throwable $e) {
            $ecodNames[$uid] = '#' . $uid;
        }
    }
    return $ecodNames[$uid];
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>付费管理台 · <?php echo htmlspecialchars((string) \Widget\Options::alloc()->title); ?></title>
<style>
:root { --c: #4f6ef2; --bd: #e4e7ee; --bg: #f5f6f9; --sf: #fff; --tx: #1e2430; --tx3: #6d7581; --ok: #10b981; --bad: #ef4444; }
* { box-sizing: border-box; }
body { margin: 0; padding: 28px 18px 60px; background: var(--bg); color: var(--tx); font: 14px/1.7 -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif; }
.wrap { max-width: 1080px; margin: 0 auto; }
h1 { margin: 0 0 4px; font-size: 22px; }
.sub { margin: 0 0 20px; color: var(--tx3); font-size: 13px; }
.card { margin-bottom: 18px; padding: 18px; background: var(--sf); border: 1px solid var(--bd); border-radius: 12px; }
.card h2 { margin: 0 0 12px; font-size: 15px; }
.flash { margin-bottom: 16px; padding: 10px 14px; border-radius: 8px; background: #eafaf3; color: #067a55; font-size: 13px; }
.flash.bad { background: #fdecec; color: #b42318; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
th, td { padding: 8px 10px; text-align: left; border-bottom: 1px solid var(--bd); vertical-align: middle; }
th { color: var(--tx3); font-weight: 600; font-size: 12px; }
td.num, th.num { text-align: right; }
.tag { display: inline-block; padding: 0 8px; border-radius: 999px; background: #eef1fe; color: var(--c); font-size: 11px; }
.tag.ok { background: #eafaf3; color: #067a55; }
.tag.bad { background: #fdecec; color: #b42318; }
button, .btn { padding: 5px 12px; border: 1px solid var(--bd); border-radius: 7px; background: var(--sf); color: var(--tx); font: inherit; font-size: 12px; cursor: pointer; }
button.primary { border-color: var(--c); background: var(--c); color: #fff; }
button.ghost { color: var(--tx3); }
form.inline { display: inline; }
.grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)) auto; gap: 8px; align-items: end; }
.grid label { display: block; font-size: 12px; color: var(--tx3); }
.grid input, .grid select { width: 100%; height: 34px; padding: 0 10px; border: 1px solid var(--bd); border-radius: 7px; font: inherit; font-size: 13px; background: #fff; }
.codes { margin-top: 12px; padding: 12px; border: 1px dashed var(--bd); border-radius: 8px; background: #fafbfc; font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 12px; white-space: pre-wrap; word-break: break-all; }
.empty { padding: 16px; color: var(--tx3); font-size: 13px; text-align: center; }
.hint { margin: 8px 0 0; color: var(--tx3); font-size: 12px; }
@media (max-width: 720px) { .grid { grid-template-columns: 1fr 1fr; } table { font-size: 12px; } th:nth-child(4), td:nth-child(4) { display: none; } }
</style>
</head>
<body>
<div class="wrap">
    <h1>付费管理台</h1>
    <p class="sub">确认收款、授予权益、生成卡密。未用卡密：<?php echo $ecodUnused; ?> 张</p>

    <?php if ($ecodFlash !== ''): ?>
        <div class="flash<?php echo $ecodFlashBad ? ' bad' : ''; ?>"><?php echo htmlspecialchars($ecodFlash, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($ecodGenerated): ?>
        <div class="card">
            <h2>新生成的卡密（只显示这一次）</h2>
            <div class="codes"><?php echo htmlspecialchars(implode("\n", $ecodGenerated), ENT_QUOTES, 'UTF-8'); ?></div>
            <p class="hint">库里只保存 SHA-256，关闭页面后无法找回明文。</p>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>待确认收款（<?php echo count($ecodPending); ?>）</h2>
        <?php if (!$ecodPending): ?>
            <div class="empty">没有待处理的订单</div>
        <?php else: ?>
            <table>
                <thead><tr>
                    <th>订单号</th><th>用户</th><th>商品</th><th>时间</th><th class="num">金额</th><th>通道</th><th>操作</th>
                </tr></thead>
                <tbody>
                <?php foreach ($ecodPending as $ecodOrder): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars((string) $ecodOrder['order_no'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                        <td><?php echo htmlspecialchars(ecod_pay_admin_name($ecodDb, (int) $ecodOrder['uid']), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?php echo htmlspecialchars(mb_substr((string) $ecodOrder['title'], 0, 24, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?>
                            <span class="tag"><?php echo (string) $ecodOrder['kind'] === 'vip' ? '会员 Lv.' . (int) $ecodOrder['level'] : '单篇 #' . (int) $ecodOrder['target']; ?></span>
                        </td>
                        <td><?php echo date('m-d H:i', (int) $ecodOrder['created']); ?></td>
                        <td class="num">¥<?php echo ecod_pay_money((int) $ecodOrder['amount']); ?></td>
                        <td><?php echo htmlspecialchars((string) $ecodOrder['channel'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <form class="inline" method="post">
                                <input type="hidden" name="token" value="<?php echo htmlspecialchars($ecodToken, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="do" value="settle">
                                <input type="hidden" name="order_no" value="<?php echo htmlspecialchars((string) $ecodOrder['order_no'], ENT_QUOTES, 'UTF-8'); ?>">
                                <button class="primary" type="submit">确认收款</button>
                            </form>
                            <form class="inline" method="post">
                                <input type="hidden" name="token" value="<?php echo htmlspecialchars($ecodToken, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="do" value="close">
                                <input type="hidden" name="order_no" value="<?php echo htmlspecialchars((string) $ecodOrder['order_no'], ENT_QUOTES, 'UTF-8'); ?>">
                                <button class="ghost" type="submit">关闭</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>手动授予 / 撤销</h2>
        <form method="post" class="grid">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($ecodToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="do" value="grant">
            <label>用户（UID 或用户名）<input type="text" name="user" required placeholder="1 或 admin"></label>
            <label>类型
                <select name="kind">
                    <option value="vip">会员</option>
                    <option value="post">单篇文章</option>
                </select>
            </label>
            <label>文章 ID（单篇时填）<input type="number" name="target" min="0" value="0"></label>
            <label>等级（会员）<input type="number" name="level" min="1" max="2" value="1"></label>
            <label>天数（0 = 永久）<input type="number" name="days" min="0" value="0"></label>
            <button class="primary" type="submit">授予</button>
        </form>
        <p class="hint">撤销：把下面的表单改成对应参数即可；撤销会员会清掉该用户全部会员权益。</p>
        <form method="post" class="grid" style="margin-top:12px">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($ecodToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="do" value="revoke">
            <label>UID<input type="number" name="uid" min="1" required></label>
            <label>类型
                <select name="kind">
                    <option value="vip">会员</option>
                    <option value="post">单篇文章</option>
                </select>
            </label>
            <label>文章 ID<input type="number" name="target" min="0" value="0"></label>
            <label>&nbsp;</label>
            <label>&nbsp;</label>
            <button class="ghost" type="submit">撤销</button>
        </form>
    </div>

    <div class="card">
        <h2>生成卡密</h2>
        <form method="post" class="grid">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($ecodToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="do" value="cards">
            <label>数量（1–200）<input type="number" name="count" min="1" max="200" value="5"></label>
            <label>类型
                <select name="kind">
                    <option value="vip">会员</option>
                    <option value="post">单篇文章</option>
                </select>
            </label>
            <label>文章 ID<input type="number" name="target" min="0" value="0"></label>
            <label>等级 / 天数<input type="text" name="level" value="1" aria-label="等级"></label>
            <label>天数<input type="text" name="days" value="30"></label>
            <button class="primary" type="submit">生成</button>
        </form>
        <p class="hint">会员卡：等级 1/2，天数 0 表示永久；单篇文章卡：填文章 ID，天数同样 0 = 永久。</p>
    </div>

    <div class="card">
        <h2>最近订单</h2>
        <?php if (!$ecodRecent): ?>
            <div class="empty">还没有已完成的订单</div>
        <?php else: ?>
            <table>
                <thead><tr><th>订单号</th><th>用户</th><th>商品</th><th>时间</th><th class="num">金额</th><th>状态</th></tr></thead>
                <tbody>
                <?php foreach ($ecodRecent as $ecodOrder): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars((string) $ecodOrder['order_no'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                        <td><?php echo htmlspecialchars(ecod_pay_admin_name($ecodDb, (int) $ecodOrder['uid']), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(mb_substr((string) $ecodOrder['title'], 0, 24, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo date('m-d H:i', (int) ($ecodOrder['paid_at'] ?: $ecodOrder['created'])); ?></td>
                        <td class="num">¥<?php echo ecod_pay_money((int) $ecodOrder['amount']); ?></td>
                        <td>
                            <?php if ((string) $ecodOrder['status'] === 'paid'): ?>
                                <span class="tag ok">已支付</span>
                            <?php elseif ((string) $ecodOrder['status'] === 'refunded'): ?>
                                <span class="tag bad">已退款</span>
                            <?php else: ?>
                                <span class="tag"><?php echo htmlspecialchars((string) $ecodOrder['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <p class="hint">退款流程：先在支付通道方退款，再到这里「撤销」对应权益，订单状态保持 paid（流水不改）。</p>
    </div>

    <p class="hint">此页仅站长可见，请勿把地址发给他人；所有写操作都校验账户令牌。</p>
</div>
</body>
</html>
