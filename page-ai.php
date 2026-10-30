<?php
/**
 * AI 助手（对话页）
 *
 * @package custom
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 *
 * 后台「页面 → 新建页面 → 自定义模板」选「AI 助手」。
 * 依赖「设置 → 互动 → 积分与 AI」：AI 复用摘要的 OpenAI 兼容接口配置，
 * 每条消息按 ECrAICost 扣 Credit，余额不足时提示先做任务。
 * 历史对话只保留在当前页面（不落库），刷新即清空。
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$this->need('public/header.php');

$ecodAiChatOn = ecod_option('EAIChat', 'on') === 'on' && ecod_ai_enabled();
$ecodCrOn = ecod_credits_enabled();
$ecodCrName = ecod_credits_name();
$ecodCrCost = max(0, (int) ecod_option('ECrAICost', 1));
$ecodCtx = max(0, min(10, (int) ecod_option('EAIChatCtx', 4)));
$ecodWelcome = trim((string) ecod_option('EAIChatWelcome', ''));
if ($ecodWelcome === '') {
    $ecodWelcome = '你好，我是本站的 AI 助手，关于文章内容或建站问题都可以问我。';
}

$ecodMe = null;
$ecodLogged = false;
try {
    $ecodMe = \Widget\User::alloc();
    $ecodLogged = (bool) $ecodMe->hasLogin();
} catch (\Throwable $e) {
}
$ecodBalance = $ecodLogged ? ecod_credits_balance((int) $ecodMe->uid) : 0;
$ecodSigned = $ecodLogged ? ecod_credits_signed_today((int) $ecodMe->uid) : false;
$ecodLoginUrl = ecod_login_url();
$ecodUcUrl = ecod_user_center_url();
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
        (string) $this->title(),
        'AI 助手',
        '基于站内接入的大模型，' . ($ecodCrCost > 0 ? '每条消息消耗 ' . $ecodCrCost . ' ' . $ecodCrName : '当前免费使用') . '。'
    );
    ?>

    <?php if (!$ecodAiChatOn || !$ecodCrOn): ?>
        <div class="ecod_uc__hint" style="margin-bottom:24px">
            <?php echo ecod_icon('info', 18); ?>
            <p>AI 助手暂未开启，请站长在「设置 → 互动 → 积分与 AI」里开启积分系统与 AI 助手，并配置好「AI 摘要」的接口信息。</p>
        </div>
    <?php elseif (!$ecodLogged): ?>
        <div class="ecod_uc__hint" style="margin-bottom:24px">
            <?php echo ecod_icon('user', 18); ?>
            <p>登录后即可与 AI 助手对话。</p>
            <p style="margin-top:12px"><a class="ecod-btn" data-ecod-login-open data-no-pjax
                href="<?php echo htmlspecialchars($ecodLoginUrl, ENT_QUOTES, 'UTF-8'); ?>?referer=<?php echo rawurlencode($this->permalink); ?>">登录</a></p>
        </div>
    <?php else: ?>
        <section class="ecod_aichat" data-ecod-aichat
                 data-api="<?php echo htmlspecialchars(ecod_api_url(), ENT_QUOTES, 'UTF-8'); ?>"
                 data-token="<?php echo htmlspecialchars(ecod_account_token(), ENT_QUOTES, 'UTF-8'); ?>"
                 data-cost="<?php echo $ecodCrCost; ?>"
                 data-ctx="<?php echo $ecodCtx; ?>"
                 data-credit-name="<?php echo htmlspecialchars($ecodCrName, ENT_QUOTES, 'UTF-8'); ?>">
            <header class="ecod_aichat__bar">
                <span class="ecod_aichat__who"><?php echo ecod_icon('zap', 15); ?>AI 助手</span>
                <span class="ecod_aichat__bal">
                    <?php echo htmlspecialchars($ecodCrName, ENT_QUOTES, 'UTF-8'); ?>
                    <b data-ecod-aichat-balance><?php echo $ecodBalance; ?></b>
                    <?php if ($ecodUcUrl !== ''): ?>
                        <a href="<?php echo htmlspecialchars($ecodUcUrl, ENT_QUOTES, 'UTF-8'); ?>#credits"
                           data-ecod-aichat-signin<?php echo $ecodSigned ? ' is-signed' : ''; ?>>
                            <?php echo $ecodSigned ? '今日已签到' : '签到 +' . (int) ecod_option('ECrCheckin', 0); ?>
                        </a>
                    <?php endif; ?>
                </span>
            </header>

            <div class="ecod_aichat__list" data-ecod-aichat-list aria-live="polite">
                <div class="ecod_aichat__msg is-ai">
                    <span class="ecod_aichat__ava" aria-hidden="true"><?php echo ecod_icon('zap', 14); ?></span>
                    <div class="ecod_aichat__bubble"><?php echo nl2br(htmlspecialchars($ecodWelcome, ENT_QUOTES, 'UTF-8')); ?></div>
                </div>
            </div>

            <div class="ecod_aichat__input">
                <textarea data-ecod-aichat-input rows="2" maxlength="2000"
                          placeholder="输入你的问题，Enter 发送，Shift + Enter 换行"></textarea>
                <button type="button" class="ecod-btn" data-ecod-aichat-send>
                    <?php echo ecod_icon('zap', 14); ?>发送
                </button>
            </div>
            <p class="ecod_aichat__tip" data-ecod-aichat-msg role="status"></p>
            <p class="ecod_aichat__note">内容由 AI 生成，仅供参考；对话记录仅保留在当前页面。</p>
        </section>
    <?php endif; ?>

    <?php ecod_widgets_render('bottom_content', $this); ?>
</main>

<?php $this->need('public/aside.php'); ?>
<?php $this->need('public/footer.php'); ?>
