<?php
/**
 * 侧栏块 · 站点抽奖（挂件版，参考 guaqi 抽奖组件）
 *
 * 数据栏（消耗 / 中奖概率 / 直接兑换 / 库存）+ 奖品列表 + 就地抽奖。
 * 与抽奖页共用同一套配置与接口（lottery_state / lottery_draw / lottery_exchange），
 * 前端行为由 ecod.post.js 的 Ecod.initLottery 统一接管。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

if (!function_exists('ecod_lottery_overview')) {
    require_once dirname(dirname(__DIR__)) . '/core/site/lottery.php';
}

if (!ecod_lottery_enabled()) {
    ecod_block_setup_hint('站点抽奖未开启或奖品池为空：在「设置 → 变现 → 站点抽奖」打开开关并配置奖品池后，这里会显示抽奖挂件（访客看不到本提示）。');
    return;
}

$ecodLbMe = null;
$ecodLbUid = 0;
try {
    $ecodLbMe = \Widget\User::alloc();
    $ecodLbUid = $ecodLbMe->hasLogin() ? (int) $ecodLbMe->uid : 0;
} catch (\Throwable $e) {
}

$ecodLb = ecod_lottery_overview($ecodLbUid);
$ecodLbPageUrl = ecod_template_page_url('page-lottery.php');
$ecodLbBanner = trim((string) ecod_p('banner', ''));
$ecodLbFacts = !empty(ecod_p('facts', 1));
$ecodLbBoard = !empty(ecod_p('board', 1));
/* 余额不足以抽一次：给「去赚积分」的明确引导（个人中心积分页） */
$ecodLbUcUrl = $ecodLbUid > 0 ? ecod_user_center_url() : '';
$ecodLbPoor = $ecodLbUid > 0 && $ecodLb['cost'] > 0 && $ecodLb['balance'] < $ecodLb['cost'];
$ecodLbCreditsUrl = $ecodLbUcUrl !== '' ? $ecodLbUcUrl . '#credits' : '';
?>
<section class="ecod_widget ecod_lottery ecod_lottery--widget" data-ecod-lottery
         data-api="<?php echo htmlspecialchars(ecod_api_url(), ENT_QUOTES, 'UTF-8'); ?>"
         data-token="<?php echo htmlspecialchars(ecod_account_token(), ENT_QUOTES, 'UTF-8'); ?>"
         data-grid="0">
    <?php ecod_block_head(['more_url' => $ecodLbPageUrl !== '' ? $ecodLbPageUrl : null]); ?>

    <?php if ($ecodLbBanner !== '' && $ecodLbPageUrl !== ''): ?>
        <a class="ecod_lottery__banner" href="<?php echo htmlspecialchars($ecodLbPageUrl, ENT_QUOTES, 'UTF-8'); ?>">
            <img src="<?php echo htmlspecialchars($ecodLbBanner, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="640" height="280" loading="lazy" decoding="async">
        </a>
    <?php elseif ($ecodLbBanner !== ''): ?>
        <span class="ecod_lottery__banner">
            <img src="<?php echo htmlspecialchars($ecodLbBanner, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="640" height="280" loading="lazy" decoding="async">
        </span>
    <?php endif; ?>

    <?php if ($ecodLbFacts): ?>
        <div class="ecod_lottery__facts">
            <div class="ecod_lottery__fact">
                <small>积分抽奖</small>
                <b><?php echo (int) $ecodLb['cost']; ?> <?php echo htmlspecialchars($ecodLb['credit'], ENT_QUOTES, 'UTF-8'); ?>/次</b>
            </div>
            <div class="ecod_lottery__fact">
                <small>中奖概率</small>
                <b><?php echo (int) $ecodLb['win_rate']; ?>%</b>
            </div>
            <div class="ecod_lottery__fact<?php echo $ecodLb['exchange']['on'] ? ' is-btn' : ''; ?>"
                 <?php if ($ecodLb['exchange']['on']): ?>data-ecod-lottery-exchange<?php endif; ?>>
                <small>直接兑换</small>
                <b><?php echo $ecodLb['exchange']['on'] ? (int) $ecodLb['exchange']['price'] . ' ' . htmlspecialchars($ecodLb['credit'], ENT_QUOTES, 'UTF-8') : '未开放'; ?></b>
            </div>
            <div class="ecod_lottery__fact">
                <small>库存</small>
                <b><?php
                    if ($ecodLb['finished']) {
                        echo '已抽完';
                    } elseif ($ecodLb['top'] && $ecodLb['top']['left'] !== null) {
                        echo (int) $ecodLb['top']['left'] . '/' . (int) $ecodLb['top']['stock'];
                    } else {
                        echo '不限';
                    }
                ?></b>
            </div>
        </div>
    <?php endif; ?>

    <div class="ecod_lottery__status">
        <span>当前状态</span>
        <b class="ecod_lottery__statetxt" data-ecod-lottery-state><?php
            if ($ecodLb['finished']) {
                echo '活动已结束';
            } elseif (!$ecodLbUid) {
                echo '登录后可参与';
            } elseif ($ecodLb['left'] === 0) {
                echo '今日次数已用完';
            } else {
                echo '进行中';
            }
        ?></b>
        <em class="ecod_lottery__bal" data-ecod-lottery-balance-badge title="我的<?php echo htmlspecialchars($ecodLb['credit'], ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo (int) $ecodLb['balance']; ?>
        </em>
    </div>

    <?php if ($ecodLbPoor && $ecodLbCreditsUrl !== ''): ?>
        <p class="ecod_lottery__poor">
            <?php echo htmlspecialchars($ecodLb['credit'], ENT_QUOTES, 'UTF-8'); ?> 不足抽一次，
            <a href="<?php echo htmlspecialchars($ecodLbCreditsUrl, ENT_QUOTES, 'UTF-8'); ?>">去签到赚积分 →</a>
        </p>
    <?php endif; ?>

    <?php if ($ecodLb['pity']['on']): ?>
        <div class="ecod_lottery__pity" data-ecod-lottery-pity data-n="<?php echo (int) $ecodLb['pity']['n']; ?>" data-now="<?php echo (int) $ecodLb['pity']['now']; ?>">
            <div class="ecod_lottery__pity-hd">
                <span>保底进度</span>
                <small data-ecod-lottery-pity-text>已抽 <?php echo (int) $ecodLb['pity']['now']; ?> 次，<?php echo (int) $ecodLb['pity']['n']; ?> 次保底必中</small>
            </div>
            <span class="ecod_lottery__pity-bar"><i style="width:<?php echo min(100, (int) round($ecodLb['pity']['now'] / max(1, (int) $ecodLb['pity']['n']) * 100)); ?>%"></i></span>
        </div>
    <?php endif; ?>

    <?php if ($ecodLbBoard && $ecodLb['prizes']): ?>
        <ul class="ecod_lottery__list ecod_lottery__list--widget" data-ecod-lottery-grid>
            <?php foreach ($ecodLb['prizes'] as $ecodLbIndex => $ecodLbPrize): ?>
                <li data-cell="<?php echo $ecodLbIndex; ?>">
                    <span><?php echo htmlspecialchars($ecodLbPrize['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (!$ecodLbUid): ?>
        <a class="ecod-btn ecod_lottery__biggo" data-ecod-login-open data-no-pjax
           href="<?php echo htmlspecialchars(ecod_login_url(), ENT_QUOTES, 'UTF-8'); ?>?referer=<?php echo rawurlencode((string) ($_SERVER['REQUEST_URI'] ?? '/')); ?>">
            <?php echo ecod_icon('user', 14); ?>登录参与
        </a>
    <?php else: ?>
        <button type="button" class="ecod-btn ecod_lottery__biggo" data-ecod-lottery-go>
            <?php echo ecod_icon('zap', 14); ?>立即抽奖<?php echo $ecodLb['cost'] > 0 ? '（' . (int) $ecodLb['cost'] . ' ' . htmlspecialchars($ecodLb['credit'], ENT_QUOTES, 'UTF-8') . '/次）' : ''; ?>
        </button>
    <?php endif; ?>

    <p class="ecod_lottery__result" data-ecod-lottery-result role="status"></p>

    <?php if ($ecodLbUid): ?>
        <details class="ecod_lottery__records">
            <summary data-ecod-lottery-records>我的记录</summary>
            <div class="ecod_lottery__recbox" data-ecod-lottery-recbox hidden></div>
        </details>
    <?php endif; ?>
</section>
