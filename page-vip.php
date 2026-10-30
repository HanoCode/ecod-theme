<?php
/**
 * 会员购买页
 *
 * @package custom
 * @author  Ecod
 * @since   2026-10-29
 * @version 2.0.0
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 *
 * 后台「页面 → 新建页面 → 自定义模板」选「会员」。
 * 价格与档位来自「设置 → 付费」里的 EVip1* / EVip2*，这里只负责展示。
 * 设计规格见 docs/paywall-design.md §9.4。
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$this->need('public/header.php');

$ecodTiers = ecod_pay_vip_tiers();
$ecodVip = ecod_pay_vip();
$ecodCurrency = ecod_pay_currency();
$ecodUid = ecod_pay_current_uid();
$ecodLoginHref = ecod_pay_login_url((string) ($_SERVER['REQUEST_URI'] ?? ''));

/* 档位固定文案与权益清单 */
$ecodTierCopy = [
    1 => [
        'desc'  => '畅读会员专属内容，升级之路的第一步。',
        'perks' => ['会员专属内容随时读', '评论等级与标识不受影响', '一次开通，全站生效'],
    ],
    2 => [
        'desc'  => '包含会员全部权益，可读更高等级的内容。',
        'perks' => ['含会员档全部权益', '超级会员专属内容可见', '专属标识与优先支持'],
    ],
];

$ecodHasPrice = false;
foreach ($ecodTiers as $ecodTier) {
    if (!empty($ecodTier['plans'])) {
        $ecodHasPrice = true;
        break;
    }
}

/* 两档都有价时，给第二档挂「推荐」 */
$ecodPick = isset($ecodTiers[2]) && !empty($ecodTiers[2]['plans'])
    && isset($ecodTiers[1]) && !empty($ecodTiers[1]['plans']);
?>
<main class="ecod_main" id="ecod-main">
    <?php /* 模块 · 内容区上方（模块管理台） */ ecod_widgets_render("top_content", $this); ?>

    <nav class="ecod_crumb" aria-label="面包屑">
        <a href="<?php $this->options->siteUrl(); ?>"><?php echo ecod_icon('home', 12); ?>首页</a>
        <span class="ecod_crumb__sep" aria-hidden="true"><?php echo ecod_icon('chevron', 11); ?></span>
        <span aria-current="page">会员</span>
    </nav>

    <?php
    ecod_page_hero(
        '会员',
        '开通会员',
        '全站会员专属内容随时读，已购内容不受影响。',
        $ecodVip['active']
            ? '当前身份：<b>' . htmlspecialchars($ecodVip['name'], ENT_QUOTES, 'UTF-8') . '</b>'
            : ''
    );
    ?>

    <?php if ($ecodVip['active']): ?>
        <div class="ecod_vip__me">
            <span class="ecod_vip__me-main">
                <?php echo ecod_icon('medal', 15); ?>
                你当前是 <b><?php echo htmlspecialchars($ecodVip['name'], ENT_QUOTES, 'UTF-8'); ?></b>
                <em><?php echo (int) $ecodVip['expire_at'] > 0 ? '至 ' . date('Y-m-d', (int) $ecodVip['expire_at']) : '长期有效'; ?></em>
            </span>
            <span class="ecod_vip__me-sub">续费会在剩余时间上累加，不会浪费一天</span>
        </div>
    <?php endif; ?>

    <?php if (!ecod_pay_enabled()): ?>
        <div class="ecod_empty">
            <div class="ecod_empty__icon" aria-hidden="true"><?php echo ecod_icon('alert', 28); ?></div>
            <h3>本站暂未开启付费阅读</h3>
            <p>会员功能随「付费阅读」总开关一起启用</p>
        </div>
    <?php elseif (!$ecodHasPrice): ?>
        <div class="ecod_empty">
            <div class="ecod_empty__icon" aria-hidden="true"><?php echo ecod_icon('star', 28); ?></div>
            <h3>还没有配置会员价格</h3>
            <p>到「设置 → 付费」里填写会员与超级会员的周期价即可开卖</p>
        </div>
    <?php else: ?>
        <div class="ecod_vip__grid">
            <?php foreach ($ecodTiers as $ecodLevel => $ecodTier): ?>
                <?php
                $ecodCopy = $ecodTierCopy[(int) $ecodLevel] ?? ['desc' => '', 'perks' => []];
                /* 最长付费周期，用于推荐推荐 CTA 与月均折算 */
                $ecodYear = $ecodTier['plans']['year'] ?? null;
                ?>
                <section class="ecod_vip__card ecod_vip__card--<?php echo (int) $ecodLevel; ?><?php echo ($ecodPick && (int) $ecodLevel >= 2) ? ' is-pick' : ''; ?>">
                    <header class="ecod_vip__head">
                        <span class="ecod_vip__ico" aria-hidden="true"><?php echo ecod_icon($ecodLevel >= 2 ? 'medal' : 'star', 20); ?></span>
                        <div class="ecod_vip__headmain">
                            <h2 class="ecod_vip__name">
                                <?php echo htmlspecialchars($ecodTier['name'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php if ($ecodPick && (int) $ecodLevel >= 2): ?>
                                    <i class="ecod_vip__pick">推荐</i>
                                <?php endif; ?>
                            </h2>
                            <p class="ecod_vip__desc"><?php echo htmlspecialchars($ecodCopy['desc'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </header>

                    <ul class="ecod_pay__perks ecod_vip__perks">
                        <?php foreach ($ecodCopy['perks'] as $ecodPerk): ?>
                            <li><?php echo ecod_icon('check', 12) . htmlspecialchars($ecodPerk, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if (empty($ecodTier['plans'])): ?>
                        <div class="ecod_vip__empty">暂未开放购买</div>
                    <?php else: ?>
                        <div class="ecod_vip__plans">
                            <?php foreach ($ecodTier['plans'] as $ecodPlan): ?>
                                <?php
                                $ecodForever = (int) $ecodPlan['days'] <= 0;
                                /* 年付折算月均价：days ≥ 360 才展示 */
                                $ecodAvg = (!$ecodForever && (int) $ecodPlan['days'] >= 360)
                                    ? ecod_pay_money((int) round($ecodPlan['cents'] / ((int) $ecodPlan['days'] / 30)))
                                    : '';
                                ?>
                                <a class="ecod_vip__plan<?php echo $ecodAvg !== '' ? ' is-best' : ''; ?>"
                                   href="<?php echo htmlspecialchars(ecod_pay_cashier_url([
                                       'kind'  => 'vip',
                                       'level' => (int) $ecodLevel,
                                       'plan'  => $ecodPlan['key'],
                                   ]), ENT_QUOTES, 'UTF-8'); ?>"
                                   aria-label="<?php echo htmlspecialchars($ecodTier['name'] . ' · ' . $ecodPlan['label'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="ecod_vip__plan-top">
                                        <b><?php echo htmlspecialchars($ecodPlan['label'], ENT_QUOTES, 'UTF-8'); ?></b>
                                        <?php if ($ecodAvg !== ''): ?>
                                            <i class="ecod_vip__plan-save">最划算</i>
                                        <?php endif; ?>
                                    </span>
                                    <span class="ecod_vip__plan-days"><?php echo $ecodForever ? '一次买断' : (int) $ecodPlan['days'] . ' 天'; ?></span>
                                    <em class="ecod_vip__plan-price"><?php echo htmlspecialchars($ecodCurrency, ENT_QUOTES, 'UTF-8') . ecod_pay_money((int) $ecodPlan['cents']); ?></em>
                                    <?php if ($ecodAvg !== ''): ?>
                                        <span class="ecod_vip__plan__avg">折合 <?php echo htmlspecialchars($ecodCurrency, ENT_QUOTES, 'UTF-8') . $ecodAvg; ?>/月</span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <p class="ecod_vip__hint">点击任意周期进入收银台<?php echo $ecodUid <= 0 ? '，购买前需<a href="' . htmlspecialchars($ecodLoginHref !== '' ? $ecodLoginHref : '#', ENT_QUOTES, 'UTF-8') . '">登录</a>' : ''; ?></p>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>

        <div class="ecod_vip__faq">
            <h3 class="ecod_vip__faq-title">常见问题</h3>
            <div class="ecod_vip__faq-grid">
                <div class="ecod_vip__faq-item">
                    <b>如何开通？</b>
                    <p>点击上方任意周期价格进入收银台，按提示完成支付即可；支持人工确认与卡密兑换。</p>
                </div>
                <div class="ecod_vip__faq-item">
                    <b>到期后会怎样？</b>
                    <p>会员到期后自动回落为普通用户，已单独购买的文章仍然可以阅读，不受影响。</p>
                </div>
                <div class="ecod_vip__faq-item">
                    <b>可以升级吗？</b>
                    <p>超级会员包含会员档全部权益；续费会在剩余时间上累加，等级取更高一档。</p>
                </div>
            </div>
        </div>

        <?php if ($ecodUid > 0): ?>
            <p class="ecod_pay__note" style="margin-top:18px">支付成功后立即生效；到期后自动回落到普通用户，已单独购买的文章不受影响。</p>
        <?php endif; ?>
    <?php endif; ?>


    <?php /* 模块 · 内容区下方（模块管理台） */ ecod_widgets_render("bottom_content", $this); ?>
</main>

<?php $this->need('public/aside.php'); ?>
<?php $this->need('public/footer.php'); ?>
