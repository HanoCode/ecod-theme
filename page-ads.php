<?php
/**
 * 广告投放（自助广告）
 *
 * @package custom
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 *
 * 后台「页面 → 新建页面 → 自定义模板」选「广告投放」。
 * 用户选广告位与天数 → 创建待支付订单 → 收银台付款 →（按设置）站长在
 * 付费管理台审核后上线。定价在「设置 → 变现 → 自助广告」维护。
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$this->need('public/header.php');

$ecodAdsOn = ecod_ads_enabled();
$ecodSlots = ecod_ads_slots();
$ecodRange = ecod_ads_units_range();
$ecodUnit = ecod_ads_unit();
$ecodUnitLabel = $ecodUnit['label'];
$ecodNote = trim((string) ecod_option('EAdSelfNote', ''));

$ecodMe = null;
$ecodLogged = false;
try {
    $ecodMe = \Widget\User::alloc();
    $ecodLogged = (bool) $ecodMe->hasLogin();
} catch (\Throwable $e) {
}
$ecodLoginUrl = ecod_login_url();

/* 各广告位对外的价格（分 → 元展示）；未定价的位子不出现在表单里 */
$ecodPriceList = [];
foreach ($ecodSlots as $ecodSlotKey => $ecodSlotName) {
    $ecodSlotCents = ecod_ads_price($ecodSlotKey);
    if ($ecodSlotCents !== null) {
        $ecodPriceList[$ecodSlotKey] = [
            'name'  => $ecodSlotName,
            'cents' => $ecodSlotCents,
            'money' => ecod_pay_money($ecodSlotCents),
        ];
    }
}

$ecodMyAds = $ecodLogged ? ecod_ads_mine((int) $ecodMe->uid, 20) : [];

/* 挂件价目卡带 ?slot= 进来时预选对应广告位 */
$ecodPreSlot = preg_replace('/[^a-z_]/', '', (string) ($_GET['slot'] ?? ''));
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
        '自助广告',
        '在线选择广告位、按' . $ecodUnitLabel . '购买，支付后经审核即可上线。'
    );
    ?>

    <?php if (!$ecodAdsOn): ?>
        <div class="ecod_uc__hint" style="margin-bottom:24px">
            <?php echo ecod_icon('info', 18); ?>
            <p>本站暂未开放自助广告，请站长在「设置 → 变现 → 自助广告」里开启并定价。</p>
        </div>
    <?php else: ?>
        <div class="ecod_adself" data-ecod-adself
             data-api="<?php echo htmlspecialchars(ecod_api_url(), ENT_QUOTES, 'UTF-8'); ?>"
             data-token="<?php echo htmlspecialchars(ecod_account_token(), ENT_QUOTES, 'UTF-8'); ?>"
             data-cashier="<?php echo htmlspecialchars(ecod_pay_cashier_url(), ENT_QUOTES, 'UTF-8'); ?>">

            <?php if ($ecodPriceList): ?>
                <section class="ecod_adself__card">
                    <h3 class="ecod_adself__hd"><?php echo ecod_icon('chart', 15); ?>广告位与价格</h3>
                    <div class="ecod_adself__prices">
                        <?php foreach ($ecodPriceList as $ecodSlotKey => $ecodSlot): ?>
                            <div class="ecod_adself__price">
                                <b><?php echo htmlspecialchars($ecodSlot['name'], ENT_QUOTES, 'UTF-8'); ?></b>
                                <span><?php echo ecod_pay_currency(); ?><?php echo $ecodSlot['money']; ?> / <?php echo $ecodUnitLabel; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="ecod_adself__tip">投放 <?php echo $ecodRange['min']; ?>–<?php echo $ecodRange['max']; ?> <?php echo $ecodUnitLabel; ?>，按所选数量一次性结算。<?php echo ecod_ads_review_on() ? '支付完成后需站长审核，审核通过起开始计时。' : '支付成功后自动上线。'; ?></p>
                </section>
            <?php endif; ?>

            <?php if (!$ecodLogged): ?>
                <section class="ecod_adself__card">
                    <h3 class="ecod_adself__hd"><?php echo ecod_icon('user', 15); ?>开始投放</h3>
                    <div class="ecod_uc__hint" style="margin:0">
                        <?php echo ecod_icon('info', 16); ?>
                        <div>
                            <p>登录后即可提交广告。</p>
                            <p style="margin-top:12px"><a class="ecod-btn" data-ecod-login-open data-no-pjax
                                href="<?php echo htmlspecialchars($ecodLoginUrl, ENT_QUOTES, 'UTF-8'); ?>?referer=<?php echo rawurlencode($this->permalink); ?>">登录</a></p>
                        </div>
                    </div>
                </section>
            <?php elseif ($ecodPriceList): ?>
                <section class="ecod_adself__card">
                    <h3 class="ecod_adself__hd"><?php echo ecod_icon('zap', 15); ?>提交广告</h3>
                    <form class="ecod_adself__form" data-ecod-adself-form novalidate>
                        <label class="ecod_adself__row">
                            <span>广告位</span>
                            <select name="slot" data-ecod-adself-slot>
                                <?php foreach ($ecodPriceList as $ecodSlotKey => $ecodSlot): ?>
                                    <option value="<?php echo $ecodSlotKey; ?>"<?php echo $ecodPreSlot === $ecodSlotKey ? ' selected' : ''; ?>><?php echo htmlspecialchars($ecodSlot['name'], ENT_QUOTES, 'UTF-8'); ?>（<?php echo ecod_pay_currency() . $ecodSlot['money']; ?>/<?php echo $ecodUnitLabel; ?>）</option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="ecod_adself__row">
                            <span>广告标题<i class="is-req" aria-hidden="true">*</i></span>
                            <input type="text" name="title" maxlength="20" required placeholder="展示用文案，20 字以内">
                        </label>
                        <label class="ecod_adself__row">
                            <span>图片地址<i class="is-req" aria-hidden="true">*</i></span>
                            <input type="url" name="image" required placeholder="https://…（建议 640×280）">
                        </label>
                        <label class="ecod_adself__row">
                            <span>跳转链接<i class="is-req" aria-hidden="true">*</i></span>
                            <input type="url" name="link" required placeholder="https://…">
                        </label>
                        <label class="ecod_adself__row">
                            <span>投放时长（<?php echo $ecodUnitLabel; ?>）</span>
                            <input type="number" name="units" min="<?php echo $ecodRange['min']; ?>" max="<?php echo $ecodRange['max']; ?>"
                                   value="<?php echo $ecodRange['min']; ?>" data-ecod-adself-units>
                        </label>
                        <div class="ecod_adself__amount">
                            应付金额：<b data-ecod-adself-amount><?php echo ecod_pay_currency() . $ecodPriceList[array_key_first($ecodPriceList)]['money']; ?></b>
                        </div>
                        <p class="ecod_adself__msg" data-ecod-adself-msg role="status" hidden></p>
                        <button type="submit" class="ecod-btn ecod_adself__submit" data-ecod-adself-submit>
                            <?php echo ecod_icon('zap', 14); ?>提交并去支付
                        </button>
                    </form>
                </section>
            <?php endif; ?>

            <?php if ($ecodLogged && $ecodMyAds): ?>
                <section class="ecod_adself__card">
                    <h3 class="ecod_adself__hd"><?php echo ecod_icon('clock', 15); ?>我的广告</h3>
                    <div class="ecod_adself__mine">
                        <?php foreach ($ecodMyAds as $ecodMyAd): ?>
                            <div class="ecod_adself__ad" data-state="<?php echo htmlspecialchars($ecodMyAd['status'], ENT_QUOTES, 'UTF-8'); ?>">
                                <img src="<?php echo htmlspecialchars((string) $ecodMyAd['image'], ENT_QUOTES, 'UTF-8'); ?>"
                                     alt="<?php echo htmlspecialchars((string) $ecodMyAd['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                     width="160" height="70" loading="lazy" decoding="async">
                                <div class="ecod_adself__admain">
                                    <b><?php echo htmlspecialchars((string) $ecodMyAd['title'], ENT_QUOTES, 'UTF-8'); ?></b>
                                    <span><?php echo htmlspecialchars($ecodSlots[$ecodMyAd['slot']] ?? $ecodMyAd['slot'], ENT_QUOTES, 'UTF-8'); ?>
                                        · <?php echo max(1, (int) round((int) $ecodMyAd['days'] / $ecodUnit['days'])); ?> <?php echo $ecodUnitLabel; ?>
                                        · <?php echo date('Y-m-d H:i', (int) $ecodMyAd['created']); ?></span>
                                </div>
                                <div class="ecod_adself__adops">
                                    <em class="<?php echo $ecodMyAd['status'] === 'active' ? 'is-ok' : ''; ?>"><?php echo ecod_ads_status_label($ecodMyAd['status']); ?></em>
                                    <?php if ($ecodMyAd['status'] === 'active' && (int) $ecodMyAd['end_at'] > 0): ?>
                                        <small>至 <?php echo date('Y-m-d', (int) $ecodMyAd['end_at']); ?></small>
                                    <?php elseif ($ecodMyAd['status'] === 'pending_pay' && (string) $ecodMyAd['order_no'] !== ''):
                                        $ecodAdCashier = ecod_pay_cashier_url(['order_no' => (string) $ecodMyAd['order_no']]); ?>
                                        <?php if ($ecodAdCashier !== ''): ?>
                                            <a class="ecod-btn ecod-btn--primary" href="<?php echo htmlspecialchars($ecodAdCashier, ENT_QUOTES, 'UTF-8'); ?>">去支付</a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($ecodNote !== ''): ?>
                <section class="ecod_adself__card">
                    <h3 class="ecod_adself__hd"><?php echo ecod_icon('info', 15); ?>投放须知</h3>
                    <div class="ecod_adself__note"><?php echo nl2br(htmlspecialchars($ecodNote, ENT_QUOTES, 'UTF-8')); ?></div>
                </section>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php ecod_widgets_render('bottom_content', $this); ?>
</main>

<?php $this->need('public/aside.php'); ?>
<?php $this->need('public/footer.php'); ?>
