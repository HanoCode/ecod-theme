<?php
/**
 * 站点抽奖（九宫格 / 列表 + guaqi 式数据栏）
 *
 * @package custom
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 *
 * 后台「页面 → 新建页面 → 自定义模板」选「抽奖」。
 * 奖品池与消耗在「设置 → 变现 → 站点抽奖」维护；每抽一次扣 ELotteryCost
 * Credit，支持 N 次保底必中（ELotteryPity）与头奖直接兑换（ELotteryExchange）。
 * 恰好 8 个奖品时渲染九宫格转盘动画，其余数量渲染奖品列表 + 直接开奖。
 * 侧栏挂件版见 components/aside/lottery.php，两者共用接口与前端逻辑。
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$this->need('public/header.php');

$ecodCrName = ecod_credits_name();
$ecodLotteryOn = ecod_lottery_enabled();
$ecodLotteryNote = trim((string) ecod_option('ELotteryNote', ''));
$ecodLotteryBanner = trim((string) ecod_option('ELotteryBanner', ''));

$ecodMe = null;
$ecodLogged = false;
$ecodUid = 0;
try {
    $ecodMe = \Widget\User::alloc();
    $ecodLogged = (bool) $ecodMe->hasLogin();
    $ecodUid = $ecodLogged ? (int) $ecodMe->uid : 0;
} catch (\Throwable $e) {
}

$ecodLot = $ecodLotteryOn ? ecod_lottery_overview($ecodUid) : [];
$ecodIsGrid = $ecodLotteryOn && count($ecodLot['prizes']) === 8;
$ecodLoginUrl = ecod_login_url();
$ecodLotUcUrl = $ecodUid > 0 ? ecod_user_center_url() : '';
$ecodLotCreditsUrl = $ecodLotUcUrl !== '' ? $ecodLotUcUrl . '#credits' : '';
/* 余额不足以抽一次：直接给出「去赚积分」的明确引导（见 draw 接口的同名拦截） */
$ecodLotPoor = $ecodUid > 0 && $ecodLot && $ecodLot['cost'] > 0 && $ecodLot['balance'] < $ecodLot['cost'];
?>
<main class="ecod_main" id="ecod-main">
    <?php ecod_widgets_render('top_content', $this); ?>

    <nav class="ecod_crumb" aria-label="面包屑">
        <a class="ecod_crumb__back" href="javascript:history.back()" aria-label="返回"><?php echo ecod_icon('chevron', 13); ?></a>
        <a href="<?php $this->options->siteUrl(); ?>"><?php echo ecod_icon('home', 12); ?>首页</a>
        <span class="ecod_crumb__sep" aria-hidden="true">/</span>
        <span aria-current="page"><?php $this->title(); ?></span>
    </nav>

    <?php
    ecod_page_hero(
        (string) $this->title,
        trim((string) ecod_option('ELotteryTitle', '幸运抽奖')) ?: '幸运抽奖',
        $ecodLotteryOn
            ? (($ecodLot['cost'] > 0 ? '每次消耗 ' . $ecodLot['cost'] . ' ' . $ecodCrName . '，' : '')
                . ($ecodLot['daily'] > 0 ? '每天 ' . $ecodLot['daily'] . ' 次机会' : '今天不限次数'))
            : ''
    );
    ?>

    <?php if (!$ecodLotteryOn): ?>
        <div class="ecod_uc__hint" style="margin-bottom:24px">
            <?php echo ecod_icon('info', 18); ?>
            <p>抽奖暂未开启，请站长在「设置 → 变现 → 站点抽奖」里开启并配置奖品池。</p>
        </div>
    <?php else: ?>
        <section class="ecod_lottery" data-ecod-lottery
                 data-api="<?php echo htmlspecialchars(ecod_api_url(), ENT_QUOTES, 'UTF-8'); ?>"
                 data-token="<?php echo htmlspecialchars(ecod_account_token(), ENT_QUOTES, 'UTF-8'); ?>"
                 data-grid="<?php echo $ecodIsGrid ? '1' : '0'; ?>"
                 data-sign-url="<?php echo htmlspecialchars($ecodLotCreditsUrl, ENT_QUOTES, 'UTF-8'); ?>">

            <span class="ecod_lottery__ball" data-ecod-lot-ball aria-hidden="true"></span>

            <?php /* 活动横幅（后台「活动横幅图」配置，16:7 裁切） */ ?>
            <?php if ($ecodLotteryBanner !== ''): ?>
                <span class="ecod_lottery__banner">
                    <img src="<?php echo htmlspecialchars($ecodLotteryBanner, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="640" height="280" decoding="async">
                </span>
            <?php endif; ?>

            <?php /* guaqi 式四格数据栏：消耗 / 概率 / 兑换 / 库存（兑换格开放时可直接点） */ ?>
            <div class="ecod_lottery__facts">
                <div class="ecod_lottery__fact">
                    <small><?php echo ecod_icon('zap', 12); ?>积分抽奖</small>
                    <b><?php echo (int) $ecodLot['cost']; ?> <?php echo htmlspecialchars($ecodCrName, ENT_QUOTES, 'UTF-8'); ?>/次</b>
                </div>
                <div class="ecod_lottery__fact">
                    <small><?php echo ecod_icon('trending', 12); ?>中奖概率</small>
                    <b><?php echo (int) $ecodLot['win_rate']; ?>%</b>
                </div>
                <div class="ecod_lottery__fact<?php echo $ecodLot['exchange']['on'] ? ' is-btn' : ''; ?>"
                     <?php if ($ecodLot['exchange']['on']): ?>data-ecod-lottery-exchange<?php endif; ?>>
                    <small><?php echo ecod_icon('gift', 12); ?>直接兑换</small>
                    <b><?php echo $ecodLot['exchange']['on'] ? (int) $ecodLot['exchange']['price'] . ' ' . htmlspecialchars($ecodCrName, ENT_QUOTES, 'UTF-8') : '未开放'; ?></b>
                </div>
                <div class="ecod_lottery__fact">
                    <small><?php echo ecod_icon('layers', 12); ?>库存</small>
                    <b><?php
                        if ($ecodLot['finished']) {
                            echo '已抽完';
                        } elseif ($ecodLot['top'] && $ecodLot['top']['left'] !== null) {
                            echo (int) $ecodLot['top']['left'] . '/' . (int) $ecodLot['top']['stock'];
                        } else {
                            echo '不限';
                        }
                    ?></b>
                </div>
            </div>

            <div class="ecod_lottery__status">
                <span>当前状态</span>
                <b class="ecod_lottery__statetxt" data-ecod-lottery-state><?php
                    if ($ecodLot['finished']) {
                        echo '活动已结束';
                    } elseif (!$ecodLogged) {
                        echo '当前账号不符合参与资格';
                    } elseif ($ecodLot['left'] === 0) {
                        echo '今日次数已用完';
                    } else {
                        echo '进行中';
                    }
                ?></b>
                <em class="ecod_lottery__bal" data-ecod-lottery-balance-badge
                    title="我的<?php echo htmlspecialchars($ecodCrName, ENT_QUOTES, 'UTF-8'); ?>"><?php echo (int) $ecodLot['balance']; ?></em>
            </div>

            <?php if ($ecodLot['pity']['on']): ?>
                <div class="ecod_lottery__pity" data-ecod-lottery-pity data-n="<?php echo (int) $ecodLot['pity']['n']; ?>" data-now="<?php echo (int) $ecodLot['pity']['now']; ?>">
                    <div class="ecod_lottery__pity-hd">
                        <span>保底进度</span>
                        <small data-ecod-lottery-pity-text>已抽 <?php echo (int) $ecodLot['pity']['now']; ?> 次，<?php echo (int) $ecodLot['pity']['n']; ?> 次保底必中</small>
                    </div>
                    <span class="ecod_lottery__pity-bar"><i style="width:<?php echo min(100, (int) round($ecodLot['pity']['now'] / max(1, (int) $ecodLot['pity']['n']) * 100)); ?>%"></i></span>
                </div>
            <?php endif; ?>

            <?php if (!$ecodLogged): ?>
                <div class="ecod_uc__hint" style="margin:16px 0">
                    <?php echo ecod_icon('user', 18); ?>
                    <div>
                        <p>登录后即可抽奖。</p>
                        <p style="margin-top:12px"><a class="ecod-btn" data-ecod-login-open data-no-pjax
                            href="<?php echo htmlspecialchars($ecodLoginUrl, ENT_QUOTES, 'UTF-8'); ?>?referer=<?php echo rawurlencode($this->permalink); ?>">登录</a></p>
                    </div>
                </div>
            <?php elseif ($ecodLotPoor && $ecodLotCreditsUrl !== ''): ?>
                <p class="ecod_lottery__poor">
                    <?php echo htmlspecialchars($ecodCrName, ENT_QUOTES, 'UTF-8'); ?> 不足抽一次，
                    <a href="<?php echo htmlspecialchars($ecodLotCreditsUrl, ENT_QUOTES, 'UTF-8'); ?>">去签到赚积分 →</a>
                </p>
            <?php endif; ?>

            <div class="ecod_lottery__board<?php echo $ecodLogged ? '' : ' is-locked'; ?>"<?php echo $ecodLogged ? '' : ' aria-hidden="true"'; ?>>
                <?php if ($ecodIsGrid): ?>
                    <div class="ecod_lottery__grid" data-ecod-lottery-grid>
                        <?php foreach ($ecodLot['prizes'] as $ecodIndex => $ecodPrize): ?>
                            <?php if ($ecodIndex === 4): ?>
                                <button type="button" class="ecod_lottery__go" data-ecod-lottery-go<?php echo $ecodLogged ? '' : ' disabled'; ?>>
                                    <b>抽奖</b>
                                    <small><?php echo $ecodLot['cost'] > 0 ? (int) $ecodLot['cost'] . ' ' . htmlspecialchars($ecodCrName, ENT_QUOTES, 'UTF-8') . '/次' : '免费'; ?></small>
                                </button>
                            <?php endif; ?>
                            <div class="ecod_lottery__cell" data-cell="<?php echo $ecodIndex; ?>">
                                <span class="ecod_lottery__prize"><?php echo htmlspecialchars($ecodPrize['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if ($ecodPrize['left'] !== null): ?>
                                    <small>剩 <?php echo (int) $ecodPrize['left']; ?> 份</small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($ecodLogged): ?>
                    <ul class="ecod_lottery__list" data-ecod-lottery-grid>
                        <?php foreach ($ecodLot['prizes'] as $ecodIndex => $ecodPrize): ?>
                            <li data-cell="<?php echo $ecodIndex; ?>">
                                <span><?php echo htmlspecialchars($ecodPrize['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if ($ecodPrize['left'] !== null): ?>
                                    <small>剩 <?php echo (int) $ecodPrize['left']; ?> 份</small>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="ecod-btn ecod_lottery__biggo" data-ecod-lottery-go>
                        <?php echo ecod_icon('zap', 15); ?>立即抽奖<?php echo $ecodLot['cost'] > 0 ? '（' . (int) $ecodLot['cost'] . ' ' . htmlspecialchars($ecodCrName, ENT_QUOTES, 'UTF-8') . '/次）' : ''; ?>
                    </button>
                <?php endif; ?>
            </div>

            <p class="ecod_lottery__result" data-ecod-lottery-result role="status"></p>

            <?php if ($ecodLogged): ?>
                <details class="ecod_lottery__records">
                    <summary data-ecod-lottery-records>我的记录</summary>
                    <div class="ecod_lottery__recbox" data-ecod-lottery-recbox hidden></div>
                </details>
            <?php endif; ?>

            <?php if ($ecodLotteryNote !== ''): ?>
                <p class="ecod_lottery__note"><?php echo nl2br(htmlspecialchars($ecodLotteryNote, ENT_QUOTES, 'UTF-8')); ?></p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php ecod_widgets_render('bottom_content', $this); ?>
</main>

<?php $this->need('public/aside.php'); ?>
<?php $this->need('public/footer.php'); ?>
