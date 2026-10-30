<?php
/**
 * 侧栏块 · 积分兑换（挂件版，guaqi「点数当钱花」）
 *
 * 与商城页共用商品配置与接口（shop_state / shop_exchange），
 * 前端行为由 ecod.post.js 的 Ecod.initShop 统一接管。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

if (!function_exists('ecod_shop_overview')) {
    require_once dirname(dirname(__DIR__)) . '/core/site/shop.php';
}

if (!ecod_credits_enabled()) {
    ecod_block_setup_hint('积分系统未开启：先在「设置 → 互动 → 积分与 AI」打开 Credit 积分，这里才能展示兑换商品（访客看不到本提示）。');
    return;
}
if (!ecod_shop_enabled()) {
    ecod_block_setup_hint('兑换商城未开启或商品列表为空：在「设置 → 变现 → 兑换商城」打开开关并配置商品后生效（访客看不到本提示）。');
    return;
}

$ecodSpMe = null;
$ecodSpUid = 0;
try {
    $ecodSpMe = \Widget\User::alloc();
    $ecodSpUid = $ecodSpMe->hasLogin() ? (int) $ecodSpMe->uid : 0;
} catch (\Throwable $e) {
}

$ecodSp = ecod_shop_overview($ecodSpUid, max(1, min(6, (int) ecod_p('limit', 4))));
$ecodSpPageUrl = ecod_template_page_url('page-shop.php');
?>
<section class="ecod_widget ecod_shop ecod_shop--widget" data-ecod-shop
         data-api="<?php echo htmlspecialchars(ecod_api_url(), ENT_QUOTES, 'UTF-8'); ?>"
         data-token="<?php echo htmlspecialchars(ecod_account_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <?php ecod_block_head(['more_url' => $ecodSpPageUrl !== '' ? $ecodSpPageUrl : null]); ?>

    <div class="ecod_shop__balance">
        <span>我的<?php echo htmlspecialchars($ecodSp['credit'], ENT_QUOTES, 'UTF-8'); ?></span>
        <b data-ecod-shop-balance><?php echo (int) $ecodSp['balance']; ?></b>
    </div>

    <div class="ecod_shop__list">
        <?php foreach ($ecodSp['items'] as $ecodSpItem): ?>
            <div class="ecod_shop__item" data-item="<?php echo htmlspecialchars($ecodSpItem['key'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="ecod_shop__main">
                    <b><?php echo htmlspecialchars($ecodSpItem['name'], ENT_QUOTES, 'UTF-8'); ?></b>
                    <span><?php echo htmlspecialchars(ecod_shop_type_label($ecodSpItem['type']), ENT_QUOTES, 'UTF-8'); ?>
                        <?php if ($ecodSpItem['left'] !== null): ?>· 剩 <?php echo (int) $ecodSpItem['left']; ?><?php endif; ?>
                        <?php if ($ecodSpItem['limit'] > 0): ?>· 限 <?php echo (int) $ecodSpItem['limit']; ?> 次<?php endif; ?>
                    </span>
                </div>
                <?php if (!$ecodSpUid): ?>
                    <a class="ecod_shop__btn" data-ecod-login-open data-no-pjax
                       href="<?php echo htmlspecialchars(ecod_login_url(), ENT_QUOTES, 'UTF-8'); ?>?referer=<?php echo rawurlencode((string) ($_SERVER['REQUEST_URI'] ?? '/')); ?>">登录</a>
                <?php elseif ($ecodSpItem['soldout_for_me']): ?>
                    <em class="ecod_shop__off">已兑换</em>
                <?php else: ?>
                    <button type="button" class="ecod_shop__btn" data-ecod-shop-buy="<?php echo htmlspecialchars($ecodSpItem['key'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo (int) $ecodSpItem['cost']; ?>
                    </button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <p class="ecod_shop__msg" data-ecod-shop-msg role="status"></p>

    <?php if ($ecodSpUid): ?>
        <details class="ecod_lottery__records">
            <summary data-ecod-shop-records>我的兑换</summary>
            <div class="ecod_lottery__recbox" data-ecod-shop-recbox hidden></div>
        </details>
    <?php endif; ?>
</section>
