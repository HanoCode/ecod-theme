<?php
/**
 * 收银台
 *
 * @package custom
 * @author  Ecod
 * @since   2026-10-29
 * @version 2.0.0
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 * @license   MIT
 *
 * 后台「页面 → 新建页面 → 自定义模板」选「收银台」。
 * 页面本身只读：下单、支付、查状态全部走 api.php，避免 GET 请求产生写操作。
 *
 * 参数：
 *   ?post=12                   购买单篇文章（不能用 cid：Typecho 路由把 cid 当单篇参数）
 *   ?kind=vip&level=1&plan=year 购买会员
 *   ?order_no=EC…              直接查看某笔订单（支付回跳）
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
require_once __DIR__ . '/core/account.php';

$this->need('public/header.php');

$ecodUid = ecod_pay_current_uid();
$ecodKind = (string) ($_GET['kind'] ?? 'post') === 'vip' ? 'vip' : 'post';
/* 注意：查询参数必须叫 post。Typecho 的 Archive 会优先认 cid，
   带上 ?cid= 会让页面路由直接 404（实测）。 */
$ecodCid = (int) ($_GET['post'] ?? 0);
$ecodLevel = max(1, min(2, (int) ($_GET['level'] ?? 1)));
$ecodPlan = (string) ($_GET['plan'] ?? 'month');
$ecodOrderNo = trim((string) ($_GET['order_no'] ?? ''));

$ecodError = '';
$ecodItem = null;      /* ['title'=>..., 'amount'=>0, 'origin'=>0, 'days'=>0, 'kind'=>'post|vip', 'target'=>cid, 'level'=>0, 'plan'=>''] */
$ecodOwned = false;
$ecodPermalink = '';

if (!ecod_pay_enabled()) {
    $ecodError = '本站暂未开启付费阅读';
} elseif ($ecodKind === 'post') {
    if ($ecodCid <= 0) {
        $ecodError = '缺少要购买的文章';
    } else {
        try {
            $ecodDb = \Typecho\Db::get();
            $ecodRow = $ecodDb->fetchRow(
                $ecodDb->select('cid', 'title', 'slug', 'type', 'status', 'authorId', 'created')
                    ->from('table.contents')->where('cid = ?', $ecodCid)->limit(1)
            );
        } catch (\Throwable $e) {
            $ecodRow = null;
        }

        if (!$ecodRow || (string) $ecodRow['status'] !== 'publish') {
            $ecodError = '文章不存在或未发布';
        } else {
            /* 轻量归档对象：只带判定需要的字段，避免动到全局 Archive */
            $ecodPost = new \stdClass();
            $ecodPost->cid = (int) $ecodRow['cid'];
            $ecodPost->title = (string) $ecodRow['title'];
            $ecodPost->authorId = (int) $ecodRow['authorId'];
            $ecodPost->created = (int) $ecodRow['created'];
            $ecodPost->fields = new \stdClass();

            try {
                $ecodFields = $ecodDb->fetchAll(
                    $ecodDb->select('name', 'str_value')->from('table.fields')->where('cid = ?', $ecodCid)
                );
                foreach ((array) $ecodFields as $ecodField) {
                    $ecodPost->fields->{(string) $ecodField['name']} = (string) $ecodField['str_value'];
                }
            } catch (\Throwable $e) {
                /* 没有字段就当免费 */
            }

            try {
                $ecodPermalink = (string) \Typecho\Router::url(
                    'post',
                    ['cid' => $ecodCid, 'slug' => (string) $ecodRow['slug']],
                    \Widget\Options::alloc()->index
                );
            } catch (\Throwable $e) {
                $ecodPermalink = '';
            }

            $ecodState = ecod_pay_access($ecodPost, $ecodUid);
            if ($ecodState['mode'] === 'free') {
                $ecodError = '这篇文章不需要付费';
            } else {
                $ecodOwned = $ecodState['allow'] && in_array($ecodState['reason'], ['purchased', 'vip'], true);
                $ecodPrice = ecod_pay_price($ecodPost, $ecodUid);
                if (!$ecodPrice['purchasable'] && $ecodState['mode'] === 'vip') {
                    $ecodError = '这篇文章仅限会员阅读，请先开通会员';
                } else {
                    $ecodDays = (int) ecod_pay_fields($ecodPost)['days'];
                    if ($ecodDays <= 0) {
                        $ecodDays = (int) ecod_option('EPayDays', 0);
                    }
                    $ecodItem = [
                        'title'  => (string) $ecodRow['title'],
                        'amount' => (int) $ecodPrice['amount'],
                        'origin' => (int) $ecodPrice['origin'],
                        'days'   => $ecodDays,
                        'kind'   => 'post',
                        'target' => $ecodCid,
                        'level'  => (int) $ecodState['level'],
                        'plan'   => '',
                    ];
                }
            }
        }
    }
} else {
    $ecodTiers = ecod_pay_vip_tiers();
    $ecodTier = $ecodTiers[$ecodLevel] ?? null;
    $ecodPlanDef = $ecodTier['plans'][$ecodPlan] ?? null;

    if (!$ecodTier || !$ecodPlanDef) {
        $ecodError = '会员套餐不存在或未配置价格';
    } else {
        $ecodVip = ecod_pay_vip($ecodUid);
        $ecodOwned = $ecodVip['active'] && (int) $ecodVip['level'] >= $ecodLevel;
        $ecodItem = [
            'title'  => $ecodTier['name'] . ' · ' . $ecodPlanDef['label'],
            'amount' => (int) $ecodPlanDef['cents'],
            'origin' => 0,
            'days'   => (int) $ecodPlanDef['days'],
            'kind'   => 'vip',
            'target' => 0,
            'level'  => $ecodLevel,
            'plan'   => $ecodPlan,
        ];
    }
}

$ecodChannels = ecod_pay_channel_list();
$ecodToken = ecod_account_token();
$ecodLoginHref = ecod_pay_login_url((string) ($_SERVER['REQUEST_URI'] ?? ''));
$ecodCurrency = ecod_pay_currency();
$ecodMeta = $ecodItem ? '应付 ' . $ecodCurrency . ecod_pay_money((int) $ecodItem['amount']) : '';
?>
<main class="ecod_main" id="ecod-main">
    <?php /* 模块 · 内容区上方（模块管理台） */ ecod_widgets_render("top_content", $this); ?>

    <nav class="ecod_crumb" aria-label="面包屑">
        <a href="<?php $this->options->siteUrl(); ?>"><?php echo ecod_icon('home', 12); ?>首页</a>
        <span class="ecod_crumb__sep" aria-hidden="true"><?php echo ecod_icon('chevron', 11); ?></span>
        <span aria-current="page">收银台</span>
    </nav>

    <?php ecod_page_hero('收银台', '确认订单', '支付成功后立即解锁，权益绑定当前账号。', $ecodMeta); ?>

    <div class="ecod_cashier" data-ecod-cashier
         data-token="<?php echo htmlspecialchars($ecodToken, ENT_QUOTES, 'UTF-8'); ?>"
         data-api="<?php echo htmlspecialchars(ecod_asset('api.php'), ENT_QUOTES, 'UTF-8'); ?>"
         data-kind="<?php echo htmlspecialchars($ecodKind, ENT_QUOTES, 'UTF-8'); ?>"
         data-cid="<?php echo (int) $ecodCid; ?>"
         data-level="<?php echo (int) $ecodLevel; ?>"
         data-plan="<?php echo htmlspecialchars($ecodPlan, ENT_QUOTES, 'UTF-8'); ?>"
         data-permalink="<?php echo htmlspecialchars($ecodPermalink, ENT_QUOTES, 'UTF-8'); ?>">

        <?php if ($ecodError !== ''): ?>
            <div class="ecod_empty">
                <div class="ecod_empty__icon" aria-hidden="true"><?php echo ecod_icon('alert', 28); ?></div>
                <h3><?php echo htmlspecialchars($ecodError, ENT_QUOTES, 'UTF-8'); ?></h3>
                <p><a href="<?php $this->options->siteUrl(); ?>">返回首页</a></p>
            </div>

        <?php elseif ($ecodOwned): ?>
            <div class="ecod_cashier__box">
                <div class="ecod_cashier__sum">
                    <div class="ecod_cashier__row"><span>商品</span><b><?php echo htmlspecialchars((string) $ecodItem['title'], ENT_QUOTES, 'UTF-8'); ?></b></div>
                    <div class="ecod_cashier__row"><span>状态</span><b>已解锁，无需重复购买</b></div>
                </div>
                <p class="ecod_cashier__status is-ok">你已有这篇内容 / 该等级会员的阅读权益。</p>
                <p style="margin-top:16px">
                    <?php if ($ecodKind === 'post' && $ecodPermalink !== ''): ?>
                        <a class="ecod_pay__btn" href="<?php echo htmlspecialchars($ecodPermalink, ENT_QUOTES, 'UTF-8'); ?>">返回阅读</a>
                    <?php else: ?>
                        <a class="ecod_pay__btn" href="<?php $this->options->siteUrl(); ?>">返回首页</a>
                    <?php endif; ?>
                </p>
            </div>

        <?php else: ?>
            <div class="ecod_cashier__box">
                <div class="ecod_cashier__sum">
                    <div class="ecod_cashier__row">
                        <span>商品</span>
                        <b><?php echo htmlspecialchars((string) $ecodItem['title'], ENT_QUOTES, 'UTF-8'); ?></b>
                    </div>
                    <div class="ecod_cashier__row">
                        <span>有效期</span>
                        <b><?php echo (int) $ecodItem['days'] > 0 ? (int) $ecodItem['days'] . ' 天' : '永久'; ?></b>
                    </div>
                    <div class="ecod_cashier__row ecod_cashier__row--amount">
                        <span>应付</span>
                        <b><?php echo htmlspecialchars($ecodCurrency, ENT_QUOTES, 'UTF-8') . ecod_pay_money((int) $ecodItem['amount']); ?></b>
                    </div>
                </div>

                <?php if ($ecodUid <= 0): ?>
                    <p class="ecod_cashier__status">购买前请先登录：权益绑定账号，换设备也能继续阅读。</p>
                    <p style="margin-top:16px">
                        <a class="ecod_pay__btn" href="<?php echo htmlspecialchars($ecodLoginHref !== '' ? $ecodLoginHref : '#', ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo ecod_icon('user', 14); ?>登录后继续
                        </a>
                    </p>
                <?php else: ?>
                    <?php if (count($ecodChannels) > 1): ?>
                        <div class="ecod_cashier__methods" data-cashier-methods>
                            <?php foreach ($ecodChannels as $ecodCh): ?>
                                <button type="button" class="ecod_cashier__method<?php echo !empty($ecodCh['default']) ? ' is-on' : ''; ?>"
                                        data-channel="<?php echo htmlspecialchars($ecodCh['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($ecodCh['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="ecod_cashier__pane" data-cashier-pane>
                        <p>点击下方按钮生成订单，按提示完成付款后会自动解锁。</p>
                    </div>

                    <div class="ecod_cashier__card" data-cashier-card hidden>
                        <div class="ecod_cashier__code">
                            <input type="text" data-cashier-code maxlength="40" autocomplete="off"
                                   placeholder="粘贴卡密，例如 EC1A2B3C…" aria-label="卡密">
                            <button type="button" data-cashier-redeem>兑换</button>
                        </div>
                    </div>

                    <div class="ecod_cashier__status" data-cashier-status hidden></div>

                    <p style="margin-top:16px">
                        <button type="button" class="ecod_cashier__pay" data-cashier-submit>
                            <?php echo ecod_icon('zap', 14); ?>生成订单
                        </button>
                    </p>
                    <p class="ecod_pay__note" data-cashier-order hidden></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>


    <?php /* 模块 · 内容区下方（模块管理台） */ ecod_widgets_render("bottom_content", $this); ?>
</main>

<?php $this->need('public/aside.php'); ?>
<?php $this->need('public/footer.php'); ?>
