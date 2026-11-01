<?php
/**
 * 积分兑换商城（guaqi「点数当钱花」）
 *
 * @package custom
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 *
 * 后台「页面 → 新建页面 → 自定义模板」选「兑换商城」。
 * 商品池在「设置 → 变现 → 兑换商城」维护；vip / post 商品自动发货，
 * text 商品记录待发货（联系站长）。侧栏挂件版见 components/aside/shop.php。
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$this->need('public/header.php');

$ecodSpOn = ecod_shop_enabled();
$ecodSpNote = trim((string) ecod_option('EShopNote', ''));

$ecodMe = null;
$ecodLogged = false;
$ecodUid = 0;
try {
    $ecodMe = \Widget\User::alloc();
    $ecodLogged = (bool) $ecodMe->hasLogin();
    $ecodUid = $ecodLogged ? (int) $ecodMe->uid : 0;
} catch (\Throwable $e) {
}

$ecodSp = $ecodSpOn ? ecod_shop_overview($ecodUid) : [];
$ecodCrName = ecod_credits_name();
$ecodLoginUrl = ecod_login_url();
/* 余额不足 toast 的「去签到」动作落点：个人中心 #credits（未登录为空，不渲染动作） */
$ecodSpSignUrl = $ecodUid > 0 ? ecod_user_center_url() . '#credits' : '';
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
        '兑换商城',
        $ecodSpOn ? '点数当钱花：用 ' . $ecodCrName . ' 兑换会员、单篇解锁与定制权益，vip 类商品即时到账。' : ''
    );
    ?>

    <?php if (!$ecodSpOn): ?>
        <div class="ecod_uc__hint" style="margin-bottom:24px">
            <?php echo ecod_icon('info', 18); ?>
            <p>兑换商城暂未开启，请站长在「设置 → 变现 → 兑换商城」里开启并配置商品。</p>
        </div>
    <?php else: ?>
        <section class="ecod_shop" data-ecod-shop
                 data-api="<?php echo htmlspecialchars(ecod_api_url(), ENT_QUOTES, 'UTF-8'); ?>"
                 data-token="<?php echo htmlspecialchars(ecod_account_token(), ENT_QUOTES, 'UTF-8'); ?>"
                 data-sign-url="<?php echo htmlspecialchars($ecodSpSignUrl, ENT_QUOTES, 'UTF-8'); ?>">

            <span class="ecod_shop__ball" data-ecod-shop-ball aria-hidden="true"></span>

            <div class="ecod_shop__balance ecod_shop__balance--page">
                <span>我的<?php echo htmlspecialchars($ecodCrName, ENT_QUOTES, 'UTF-8'); ?></span>
                <b data-ecod-shop-balance><?php echo (int) $ecodSp['balance']; ?></b>
                <?php if (!$ecodLogged): ?>
                    <a class="ecod-btn" data-ecod-login-open data-no-pjax
                       href="<?php echo htmlspecialchars($ecodLoginUrl, ENT_QUOTES, 'UTF-8'); ?>?referer=<?php echo rawurlencode($this->permalink); ?>">登录后兑换</a>
                <?php endif; ?>
            </div>

            <div class="ecod_shop__grid">
                <?php foreach ($ecodSp['items'] as $ecodSpItem): ?>
                    <?php
                    $ecodSpIcon = ['vip' => 'crown', 'post' => 'file', 'text' => 'gift'];
                    $ecodSpType = (string) $ecodSpItem['type'];
                    ?>
                    <div class="ecod_shop__card<?php echo $ecodSpItem['soldout_for_me'] ? ' is-off' : ''; ?>"
                         data-item="<?php echo htmlspecialchars($ecodSpItem['key'], ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="ecod_shop__media">
                            <?php if ($ecodSpItem['cover'] !== ''): ?>
                                <img src="<?php echo htmlspecialchars($ecodSpItem['cover'], ENT_QUOTES, 'UTF-8'); ?>"
                                     alt="<?php echo htmlspecialchars($ecodSpItem['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                     loading="lazy" decoding="async">
                            <?php else: ?>
                                <span class="ecod_shop__ph ecod_shop__ph--<?php echo htmlspecialchars($ecodSpType, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo ecod_icon($ecodSpIcon[$ecodSpType] ?? 'gift', 30); ?>
                                </span>
                            <?php endif; ?>
                            <span class="ecod_shop__flag"><?php echo htmlspecialchars(ecod_shop_type_label($ecodSpType), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <b class="ecod_shop__name"><?php echo htmlspecialchars($ecodSpItem['name'], ENT_QUOTES, 'UTF-8'); ?></b>
                        <div class="ecod_shop__meta">
                            <?php if ($ecodSpItem['left'] !== null): ?><span>剩 <?php echo (int) $ecodSpItem['left']; ?></span><?php endif; ?>
                            <?php if ($ecodSpItem['limit'] > 0): ?><span>限 <?php echo (int) $ecodSpItem['limit']; ?> 次</span><?php endif; ?>
                        </div>
                        <div class="ecod_shop__foot">
                            <?php if (!$ecodLogged): ?>
                                <em class="ecod_shop__price"><i>兑换</i><b><?php echo (int) $ecodSpItem['cost']; ?></b><?php echo htmlspecialchars($ecodCrName, ENT_QUOTES, 'UTF-8'); ?></em>
                                <a class="ecod-btn ecod-btn--ghost" data-ecod-login-open data-no-pjax
                                   href="<?php echo htmlspecialchars($ecodLoginUrl, ENT_QUOTES, 'UTF-8'); ?>?referer=<?php echo rawurlencode($this->permalink); ?>">登录</a>
                            <?php elseif ($ecodSpItem['soldout_for_me']): ?>
                                <em class="ecod_shop__off">已达限购</em>
                            <?php else: ?>
                                <button type="button" class="ecod_shop__price" data-ecod-shop-buy="<?php echo htmlspecialchars($ecodSpItem['key'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <i>兑换</i><b><?php echo (int) $ecodSpItem['cost']; ?></b><?php echo htmlspecialchars($ecodCrName, ENT_QUOTES, 'UTF-8'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($ecodLogged): ?>
                <details class="ecod_lottery__records">
                    <summary data-ecod-shop-records>我的兑换</summary>
                    <div class="ecod_lottery__recbox" data-ecod-shop-recbox hidden></div>
                </details>
            <?php endif; ?>

            <?php if ($ecodSpNote !== ''): ?>
                <p class="ecod_lottery__note"><?php echo nl2br(htmlspecialchars($ecodSpNote, ENT_QUOTES, 'UTF-8')); ?></p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php ecod_widgets_render('bottom_content', $this); ?>
</main>

<?php $this->need('public/aside.php'); ?>
<?php $this->need('public/footer.php'); ?>
