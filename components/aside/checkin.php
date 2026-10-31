<?php
/**
 * 侧栏块 · 每日打卡（挂件版，参考 guaqi 打卡组件）
 *
 * 今日到账横幅 / 签到按钮 + 全站打卡动态 + 推广返佣引导，就地签到。
 * 签到发放与去重共用积分内核（ecod_credits_checkin，api.php?action=signin），
 * 前端行为由 ecod.global.js 的 Ecod.initCheckin 统一接管；
 * 动态列表受「动态播报」隐私开关（ECrTicker）控制，涉及用户名不对外。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

if (!function_exists('ecod_credits_checkin_feed')) {
    require_once dirname(dirname(__DIR__)) . '/core/user/credits.php';
}
if (!function_exists('ecod_ref_on')) {
    require_once dirname(dirname(__DIR__)) . '/core/user/referral.php';
}
/* 打卡动态要链到作者主页：meta.php 提供 ecod_user_archive_url()，
   它只在作者页 / 个人中心按需加载，挂件这里必须自己带上 */
require_once dirname(dirname(__DIR__)) . '/core/user/meta.php';

/* 积分未开时整块隐藏：签到奖励、动态与推广都依赖积分账本 */
if (!ecod_credits_enabled()) {
    ecod_block_setup_hint('积分系统未开启：先在「设置 → 互动 → 积分与 AI」打开 Credit 积分并配置每日签到奖励，这里会显示打卡挂件（访客看不到本提示）。');
    return;
}

$ecodCkMe = null;
$ecodCkUid = 0;
try {
    $ecodCkMe = \Widget\User::alloc();
    $ecodCkUid = $ecodCkMe->hasLogin() ? (int) $ecodCkMe->uid : 0;
} catch (\Throwable $e) {
}

$ecodCkSigned = $ecodCkUid > 0 && ecod_credits_signed_today($ecodCkUid);
$ecodCkBalance = $ecodCkUid > 0 ? ecod_credits_balance($ecodCkUid) : 0;
$ecodCkReward = max(0, (int) ecod_option('ECrCheckin', 0));
$ecodCkCredit = ecod_credits_name();

/* 连签天数服务端直出：今天签了 = 含今天，没签 = 算到昨天（打卡后 +1），
   前端只在签到成功后把它替换成服务端返回的新值，不靠点击才显示 */
$ecodCkStreak = $ecodCkUid > 0 ? ecod_credits_streak($ecodCkUid) : 0;

/* 打卡成功后前端就地往动态列表里插一条，需要当前用户昵称 */
$ecodCkNick = '';
if ($ecodCkMe) {
    $ecodCkNick = trim((string) $ecodCkMe->screenName);
    if ($ecodCkNick === '') {
        $ecodCkNick = (string) $ecodCkMe->name;
    }
}

$ecodCkBanner = trim((string) ecod_p('banner', '今日份奖励自动到账中～'));
if ($ecodCkBanner === '') {
    $ecodCkBanner = '今日份奖励自动到账中～';
}

/* 打卡动态：模块开关 × 隐私开关（ECrTicker）双闸 */
$ecodCkFeedOn = !empty(ecod_p('feed', 1)) && (string) ecod_option('ECrTicker', 'off') === 'on';
$ecodCkFeed = $ecodCkFeedOn ? ecod_credits_checkin_feed(max(3, min(15, (int) ecod_p('limit', 6)))) : [];

/* 推广引导：模块开关 × 返佣开关双闸；入口指向个人中心积分面板（返佣区在其中） */
$ecodCkRefOn = !empty(ecod_p('ref', 1)) && ecod_ref_on() && (int) ecod_ref_percent() > 0;
$ecodCkUcUrl = $ecodCkUid > 0 ? ecod_user_center_url() : '';
$ecodCkRefUrl = ($ecodCkRefOn && $ecodCkUcUrl !== '') ? $ecodCkUcUrl . '#credits' : '';
$ecodCkLogUrl = $ecodCkUcUrl !== '' ? $ecodCkUcUrl . '#credits' : '';
$ecodCkLoginUrl = ecod_login_url();
?>
<section class="ecod_widget ecod_checkin" data-ecod-checkin
         data-api="<?php echo htmlspecialchars(ecod_api_url(), ENT_QUOTES, 'UTF-8'); ?>"
         data-token="<?php echo htmlspecialchars(ecod_account_token(), ENT_QUOTES, 'UTF-8'); ?>"
         data-banner="<?php echo htmlspecialchars($ecodCkBanner, ENT_QUOTES, 'UTF-8'); ?>"
         data-me="<?php echo htmlspecialchars($ecodCkNick, ENT_QUOTES, 'UTF-8'); ?>"
         data-me-url="<?php echo htmlspecialchars($ecodCkUid > 0 ? ecod_user_archive_url($ecodCkUid) : '', ENT_QUOTES, 'UTF-8'); ?>">
    <?php ecod_block_head(); ?>

    <?php if (!$ecodCkUid): ?>
        <a class="ecod_checkin__banner ecod_checkin__banner--go" href="<?php echo htmlspecialchars($ecodCkLoginUrl, ENT_QUOTES, 'UTF-8'); ?>?referer=<?php echo rawurlencode((string) ($_SERVER['REQUEST_URI'] ?? '/')); ?>" data-no-pjax>
            <?php echo ecod_icon('zap', 15); ?>
            <span>登录后每日打卡领 <?php echo htmlspecialchars($ecodCkCredit, ENT_QUOTES, 'UTF-8'); ?></span>
        </a>
    <?php elseif ($ecodCkSigned): ?>
        <div class="ecod_checkin__banner is-signed">
            <?php echo ecod_icon('zap', 15); ?>
            <span><?php echo htmlspecialchars($ecodCkBanner, ENT_QUOTES, 'UTF-8'); ?></span>
            <b data-ecod-checkin-balance title="我的<?php echo htmlspecialchars($ecodCkCredit, ENT_QUOTES, 'UTF-8'); ?>"><?php echo (int) $ecodCkBalance; ?></b>
        </div>
    <?php else: ?>
        <div class="ecod_checkin__banner" data-ecod-checkin-banner>
            <?php echo ecod_icon('zap', 15); ?>
            <span data-ecod-checkin-hint>
                <?php if ($ecodCkStreak > 0): ?>
                    已连续打卡 <em data-ecod-checkin-streak><?php echo (int) $ecodCkStreak; ?></em> 天，今天还没打卡
                <?php else: ?>
                    今天还没打卡，打卡领 <?php echo (int) $ecodCkReward; ?> <?php echo htmlspecialchars($ecodCkCredit, ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
            </span>
            <b data-ecod-checkin-balance hidden><?php echo (int) $ecodCkBalance; ?></b>
        </div>
        <button type="button" class="ecod-btn ecod_checkin__go" data-ecod-checkin-go>
            <?php echo ecod_icon('calendar', 14); ?>打卡<?php echo $ecodCkReward > 0 ? ' +' . $ecodCkReward : ''; ?>
        </button>
    <?php endif; ?>
    <p class="ecod_checkin__msg" data-ecod-checkin-msg role="status"></p>

    <?php if ($ecodCkFeedOn): ?>
        <div class="ecod_checkin__feed" data-ecod-checkin-feed>
            <ul class="ecod_checkin__list" data-ecod-checkin-list>
                <?php foreach ($ecodCkFeed as $ecodCkItem): ?>
                    <li class="ecod_checkin__item">
                        <a href="<?php echo htmlspecialchars(ecod_user_archive_url((int) $ecodCkItem['uid']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ecodCkItem['name'], ENT_QUOTES, 'UTF-8'); ?></a>
                        <em>打卡成功</em>
                        <b>+<?php echo (int) $ecodCkItem['delta']; ?></b>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="ecod_checkin__empty" data-ecod-checkin-empty<?php echo $ecodCkFeed ? ' hidden' : ''; ?>>暂无打卡动态</p>
            <p class="ecod_checkin__end">没有更多了</p>
        </div>
    <?php endif; ?>

    <?php if ($ecodCkRefOn && $ecodCkRefUrl !== ''): ?>
        <div class="ecod_checkin__ref">
            <div class="ecod_checkin__ref-row">
                <?php echo ecod_icon('link', 14); ?>
                <span>邀请好友</span>
                <b>订单返 <?php echo (int) ecod_ref_percent(); ?>%</b>
            </div>
            <div class="ecod_checkin__ref-row">
                <?php echo ecod_icon('user-add', 14); ?>
                <span>好友注册</span>
                <b>链接 30 天归因</b>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($ecodCkUid > 0 && ($ecodCkRefUrl !== '' || $ecodCkLogUrl !== '')): ?>
        <div class="ecod_checkin__acts">
            <?php if ($ecodCkRefUrl !== ''): ?>
                <a class="ecod_checkin__promote" href="<?php echo htmlspecialchars($ecodCkRefUrl, ENT_QUOTES, 'UTF-8'); ?>">去推广</a>
            <?php endif; ?>
            <?php if ($ecodCkLogUrl !== ''): ?>
                <a class="ecod_checkin__log" href="<?php echo htmlspecialchars($ecodCkLogUrl, ENT_QUOTES, 'UTF-8'); ?>">打卡记录 →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
