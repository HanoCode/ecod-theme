<?php
/**
 * 侧栏块 · 更新动态（guaqi 式 LIVE 滚动播报）
 *
 * 滚动播报最新的积分奖励事件（签到 / 中奖 / 被赞 / 会员赠送…），
 * 数据来自 ecod_credits 账本；总开关在「设置 → 互动 → 积分与 AI → 动态播报」。
 * 纵向无缝滚动用纯 CSS 动画（列表渲染两份），悬停暂停。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

if (!function_exists('ecod_credits_ticker')) {
    require_once dirname(dirname(__DIR__)) . '/core/user/credits.php';
}
/* 播报条目要链到作者主页：meta.php 提供 ecod_user_archive_url() */
require_once dirname(dirname(__DIR__)) . '/core/user/meta.php';

/* 站长没开播报（或积分未开）时整块隐藏：事件涉及用户名，尊重隐私开关 */
if (!ecod_credits_enabled()) {
    ecod_block_setup_hint('积分系统未开启：先在「设置 → 互动 → 积分与 AI」打开 Credit 积分，这里才会滚动播报奖励事件（访客看不到本提示）。');
    return;
}
if ((string) ecod_option('ECrTicker', 'off') !== 'on') {
    ecod_block_setup_hint('动态播报未开启：在「设置 → 互动 → 积分与 AI → 动态播报」打开后，这里会滚动展示最新的积分奖励事件。');
    return;
}

$ecodTkItems = ecod_credits_ticker(max(3, min(15, (int) ecod_p('limit', 8))));
if (count($ecodTkItems) < 2) {
    return;
}

$ecodTkSeconds = max(10, min(120, (int) ecod_p('seconds', 30)));
$ecodTkCredit = ecod_credits_name();
?>
<section class="ecod_widget ecod_ticker">
    <?php ecod_block_head(); ?>
    <div class="ecod_ticker__live" aria-hidden="true"><i></i>LIVE</div>
    <div class="ecod_ticker__viewport">
        <div class="ecod_ticker__list" style="animation-duration:<?php echo $ecodTkSeconds; ?>s">
            <?php for ($ecodTkPass = 0; $ecodTkPass < 2; $ecodTkPass++): ?>
                <?php foreach ($ecodTkItems as $ecodTkItem): ?>
                    <span class="ecod_ticker__item"<?php echo $ecodTkPass === 1 ? ' aria-hidden="true"' : ''; ?>>
                        <a href="<?php echo htmlspecialchars(ecod_user_archive_url((int) $ecodTkItem['uid']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ecodTkItem['name'], ENT_QUOTES, 'UTF-8'); ?></a>
                        <em><?php echo htmlspecialchars(ecod_credits_type_label($ecodTkItem['type']), ENT_QUOTES, 'UTF-8'); ?></em>
                        <b>+<?php echo (int) $ecodTkItem['delta']; ?></b>
                    </span>
                <?php endforeach; ?>
            <?php endfor; ?>
        </div>
    </div>
    <span class="ecod_ticker__sr"><?php echo htmlspecialchars('最新动态：' . $ecodTkItems[0]['name'] . ' ' . ecod_credits_type_label($ecodTkItems[0]['type']) . ' +' . $ecodTkItems[0]['delta'] . ' ' . $ecodTkCredit, ENT_QUOTES, 'UTF-8'); ?></span>
</section>
