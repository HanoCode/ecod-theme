<?php
/**
 * 个人中心：资料 / 收藏 / 点赞 / 历史 / 订单
 *
 * @package custom
 * @author  Ecod
 * @since   2026-10-29
 * @version 2.0.0
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 *
 * 在后台「页面 → 新建页面 → 自定义模板」里选择「个人中心」即可。
 * 左侧菜单 + 右侧内容：个人信息（登录态，支持直接修改资料 / 头像 / 密码）、
 * 我的收藏、我的点赞、浏览历史、订单。收藏 / 历史 / 订单存浏览器本地，
 * 点赞以服务端访客指纹为准；未来支付类插件调用 Ecod.addOrder() 即可把订单写进「订单」。
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$this->need('public/header.php');
require_once __DIR__ . '/core/user/account.php';

/* 登录态与用户资料 */
$ecodUser = null;
$ecodLogged = false;
try {
    $ecodUser = \Widget\User::alloc();
    $ecodLogged = (bool) $ecodUser->hasLogin();
} catch (\Throwable $e) {
}

$ecodAvatar = $ecodLogged ? ecod_user_avatar($ecodUser, 160) : '';
$ecodHasCustomAvatar = false;
if ($ecodLogged) {
    $ecodHasCustomAvatar = ecod_account_avatar_url((int) $ecodUser->uid) !== '';
}
$ecodGroupName = '';
if ($ecodLogged) {
    $ecodGroups = ['subscriber' => '订阅者', 'contributor' => '投稿者', 'editor' => '编辑', 'administrator' => '管理员'];
    $ecodGroup = (string) $ecodUser->group;
    $ecodGroupName = $ecodGroups[$ecodGroup] ?? $ecodGroup;
}

/* 会员状态与订单（服务端读取，换设备也一致） */
$ecodVipInfo = ['active' => false, 'level' => 0, 'name' => '', 'expire_at' => 0];
$ecodPayOrders = [];
if ($ecodLogged && function_exists('ecod_pay_vip')) {
    $ecodVipInfo = ecod_pay_vip((int) $ecodUser->uid);
    if (function_exists('ecod_pay_orders')) {
        $ecodPayOrders = ecod_pay_orders((int) $ecodUser->uid, 20);
    }
}

$ecodLoginUrl = ecod_login_url();
$ecodRegisterUrl = rtrim((string) $this->options->adminUrl, '/') . '/register.php';

$ecodTabs = [
    'profile' => ['个人信息', 'user'],
    'favs'    => ['我的收藏', 'star'],
    'likes'   => ['我的点赞', 'heart'],
    'history' => ['浏览历史', 'clock'],
    'orders'  => ['订单', 'inbox'],
];
?>
<main class="ecod_main" id="ecod-main">
    <?php /* 模块 · 内容区上方（模块管理台） */ ecod_widgets_render("top_content", $this); ?>

    <nav class="ecod_crumb" aria-label="面包屑">
        <a href="<?php $this->options->siteUrl(); ?>"><?php echo ecod_icon('home', 12); ?>首页</a>
        <span class="ecod_crumb__sep" aria-hidden="true"><?php echo ecod_icon('chevron', 11); ?></span>
        <span aria-current="page"><?php $this->title(); ?></span>
    </nav>

    <div class="ecod_uc" data-ecod-uc>

        <!-- 左侧：身份卡 + 菜单 -->
        <aside class="ecod_uc__side">
            <div class="ecod_uc__idcard">
                <?php if ($ecodLogged): ?>
                    <span class="ecod_uc__avatar">
                        <span class="ecod_nav__user-ic"><?php echo ecod_icon('user', 30); ?></span>
                        <img src="<?php echo htmlspecialchars($ecodAvatar, ENT_QUOTES, 'UTF-8'); ?>"
                             alt="" width="72" height="72" onerror="this.remove()" referrerpolicy="no-referrer">
                    </span>
                    <b class="ecod_uc__name"><?php echo htmlspecialchars((string) $ecodUser->screenName, ENT_QUOTES, 'UTF-8'); ?></b>
                    <span class="ecod_uc__mail"><?php echo htmlspecialchars((string) $ecodUser->mail, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="ecod_uc__group"><?php echo htmlspecialchars($ecodGroupName, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php if ($ecodVipInfo['active']): ?>
                        <span class="ecod_uc__vip">
                            <?php echo ecod_icon('medal', 12); ?><?php echo htmlspecialchars($ecodVipInfo['name'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php echo (int) $ecodVipInfo['expire_at'] > 0 ? '· 至 ' . date('Y-m-d', (int) $ecodVipInfo['expire_at']) : '· 长期'; ?>
                        </span>
                    <?php endif; ?>
                    <div class="ecod_uc__idops">
                        <a class="ecod-btn ecod-btn--ghost" href="<?php $this->options->adminUrl(); ?>" target="_blank" rel="noopener">管理后台</a>
                        <a class="ecod-btn ecod-btn--ghost" href="<?php $this->options->logoutUrl(); ?>">退出登录</a>
                    </div>
                <?php else: ?>
                    <span class="ecod_uc__avatar ecod_uc__avatar--guest" aria-hidden="true"><?php echo ecod_icon('user', 30); ?></span>
                    <b class="ecod_uc__name">游客</b>
                    <span class="ecod_uc__mail">登录后可展示账户资料</span>
                    <div class="ecod_uc__idops">
                        <a class="ecod-btn" data-ecod-login-open data-no-pjax href="<?php echo htmlspecialchars($ecodLoginUrl, ENT_QUOTES, 'UTF-8'); ?>?referer=<?php echo rawurlencode($this->permalink); ?>">登录</a>
                        <?php if ((int) $this->options->allowRegister === 1): ?>
                            <a class="ecod-btn ecod-btn--ghost" href="<?php echo htmlspecialchars($ecodRegisterUrl, ENT_QUOTES, 'UTF-8'); ?>">注册</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <nav class="ecod_uc__menu" aria-label="个人中心菜单">
                <?php foreach ($ecodTabs as $ecodKey => $ecodTab): ?>
                    <button type="button" class="ecod_uc__tab<?php echo $ecodKey === 'profile' ? ' is-active' : ''; ?>"
                            data-ecod-uc-tab="<?php echo $ecodKey; ?>">
                        <?php echo ecod_icon($ecodTab[1], 15); ?>
                        <span><?php echo $ecodTab[0]; ?></span>
                        <em data-ecod-uc-count="<?php echo $ecodKey; ?>" hidden>0</em>
                    </button>
                <?php endforeach; ?>
            </nav>
        </aside>

        <!-- 右侧：内容面板 -->
        <section class="ecod_uc__body">

            <!-- 个人信息：支持直接修改资料 / 头像 / 密码 -->
            <div class="ecod_uc__panel is-active" data-ecod-uc-panel="profile">
                <h3 class="ecod_uc__title">个人信息</h3>
                <?php if ($ecodLogged): ?>
                    <div data-ecod-account data-ecod-token="<?php echo htmlspecialchars(ecod_account_token(), ENT_QUOTES, 'UTF-8'); ?>">

                        <!-- 头像 -->
                        <div class="ecod_uc__avatar-edit">
                            <img class="ecod_uc__avatar" src="<?php echo htmlspecialchars($ecodAvatar, ENT_QUOTES, 'UTF-8'); ?>"
                                 alt="头像" width="72" height="72" referrerpolicy="no-referrer" data-ecod-avatar-img>
                            <div class="ecod_uc__avatar-ops">
                                <label class="ecod-btn ecod-btn--ghost ecod_uc__avatar-btn">
                                    <?php echo ecod_icon('image', 14); ?>更换头像
                                    <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" data-ecod-avatar-input hidden>
                                </label>
                                <?php if ($ecodHasCustomAvatar): ?>
                                    <button type="button" class="ecod_uc__linkbtn" data-ecod-avatar-remove>恢复默认头像</button>
                                <?php endif; ?>
                                <span class="ecod_uc__avatar-tip">jpg / png / gif / webp，不超过 4MB</span>
                            </div>
                        </div>
                        <p class="ecod_uc__form-msg" data-ecod-account-msg="avatar" hidden></p>

                        <!-- 基本资料 -->
                        <form class="ecod_uc__form" data-ecod-account-form="profile" novalidate>
                            <label class="ecod_uc__form-row">
                                <span>昵称</span>
                                <input type="text" name="screenName" maxlength="32" required
                                       value="<?php echo htmlspecialchars((string) $ecodUser->screenName, ENT_QUOTES, 'UTF-8'); ?>">
                            </label>
                            <label class="ecod_uc__form-row">
                                <span>邮箱</span>
                                <input type="email" name="mail" required
                                       value="<?php echo htmlspecialchars((string) $ecodUser->mail, ENT_QUOTES, 'UTF-8'); ?>">
                            </label>
                            <label class="ecod_uc__form-row">
                                <span>个人主页</span>
                                <input type="url" name="url" placeholder="https://"
                                       value="<?php echo htmlspecialchars((string) $ecodUser->url, ENT_QUOTES, 'UTF-8'); ?>">
                            </label>
                            <p class="ecod_uc__form-msg" data-ecod-account-msg="profile" hidden></p>
                            <div class="ecod_uc__form-ops">
                                <button type="submit" class="ecod-btn">保存资料</button>
                            </div>
                        </form>

                        <!-- 修改密码 -->
                        <form class="ecod_uc__form" data-ecod-account-form="password" novalidate>
                            <b class="ecod_uc__form-sub">修改密码</b>
                            <label class="ecod_uc__form-row">
                                <span>当前密码</span>
                                <input type="password" name="current" required autocomplete="current-password">
                            </label>
                            <label class="ecod_uc__form-row">
                                <span>新密码</span>
                                <input type="password" name="new" minlength="6" maxlength="64" required autocomplete="new-password">
                            </label>
                            <label class="ecod_uc__form-row">
                                <span>确认新密码</span>
                                <input type="password" name="confirm" minlength="6" maxlength="64" required autocomplete="new-password">
                            </label>
                            <p class="ecod_uc__form-msg" data-ecod-account-msg="password" hidden></p>
                            <div class="ecod_uc__form-ops">
                                <button type="submit" class="ecod-btn ecod-btn--ghost">修改密码</button>
                            </div>
                        </form>

                        <!-- 只读信息 -->
                        <dl class="ecod_uc__fields">
                            <div><dt>用户组</dt><dd><?php echo htmlspecialchars($ecodGroupName, ENT_QUOTES, 'UTF-8'); ?></dd></div>
                            <div><dt>注册时间</dt><dd><?php echo date('Y-m-d', (int) $ecodUser->created); ?></dd></div>
                        </dl>
                    </div>
                <?php else: ?>
                    <div class="ecod_uc__hint">
                        <?php echo ecod_icon('info', 18); ?>
                        <p>登录后这里会显示账户资料。收藏、点赞与浏览历史基于浏览器本地记录，游客也可以直接使用下面几个标签页。</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 我的收藏 / 点赞 / 历史 / 订单：JS 渲染 -->
            <div class="ecod_uc__panel" data-ecod-uc-panel="favs" hidden>
                <h3 class="ecod_uc__title">我的收藏</h3>
                <p class="ecod_uc__desc">收藏保存在当前浏览器中，点击卡片右上角的星标即可收藏文章。</p>
                <div class="ecod_uc__list" data-ecod-uc-list="favs"></div>
            </div>

            <div class="ecod_uc__panel" data-ecod-uc-panel="likes" hidden>
                <h3 class="ecod_uc__title">我的点赞</h3>
                <p class="ecod_uc__desc">以浏览器访客标识为准，与点赞按钮的状态一致。</p>
                <div class="ecod_uc__list" data-ecod-uc-list="likes"></div>
            </div>

            <div class="ecod_uc__panel" data-ecod-uc-panel="history" hidden>
                <h3 class="ecod_uc__title">浏览历史</h3>
                <p class="ecod_uc__desc">最近浏览过的文章（本地保留 50 条），<button type="button" class="ecod_uc__clear" data-ecod-uc-clear="history">清空历史</button></p>
                <div class="ecod_uc__list" data-ecod-uc-list="history"></div>
            </div>

            <div class="ecod_uc__panel" data-ecod-uc-panel="orders" hidden>
                <h3 class="ecod_uc__title">订单</h3>
                <p class="ecod_uc__desc">最近的付费记录，权益绑定账号，换设备登录同样有效。</p>
                <?php if (!$ecodLogged): ?>
                    <div class="ecod_uc__empty">
                        <p>登录后可查看订单与会员状态。</p>
                        <?php if ($ecodLoginUrl !== ''): ?>
                            <a class="ecod-btn ecod-btn--primary" href="<?php echo htmlspecialchars($ecodLoginUrl, ENT_QUOTES, 'UTF-8'); ?>">去登录</a>
                        <?php endif; ?>
                    </div>
                <?php elseif (!$ecodPayOrders): ?>
                    <div class="ecod_uc__empty">
                        <p>还没有付费记录。</p>
                        <?php $ecodVipPage = function_exists('ecod_pay_vip_url') ? ecod_pay_vip_url() : ''; ?>
                        <?php if ($ecodVipPage !== ''): ?>
                            <a class="ecod-btn ecod-btn--ghost" href="<?php echo htmlspecialchars($ecodVipPage, ENT_QUOTES, 'UTF-8'); ?>">看看会员权益</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="ecod_uc__list">
                        <?php foreach ($ecodPayOrders as $ecodOrder): ?>
                            <?php
                            $ecodStatus = (string) $ecodOrder['status'];
                            $ecodStatusText = [
                                'pending'  => '待支付',
                                'paid'     => '已支付',
                                'closed'   => '已关闭',
                                'refunded' => '已退款',
                            ][$ecodStatus] ?? $ecodStatus;
                            ?>
                            <div class="ecod_uc__item" data-state="<?php echo htmlspecialchars($ecodStatus, ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="ecod_uc__main">
                                    <b><?php echo htmlspecialchars(mb_substr((string) $ecodOrder['title'], 0, 40, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></b>
                                    <span>
                                        <?php echo date('Y-m-d H:i', (int) ($ecodOrder['paid_at'] ?: $ecodOrder['created'])); ?>
                                        · <?php echo (string) $ecodOrder['kind'] === 'vip' ? '会员' : '单篇'; ?>
                                        · <?php echo htmlspecialchars((string) $ecodOrder['order_no'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </div>
                                <div class="ecod_uc__order-meta">
                                    <em class="<?php echo $ecodStatus === 'paid' ? 'is-paid' : ($ecodStatus === 'closed' ? 'is-closed' : ''); ?>">
                                        <?php echo htmlspecialchars($ecodStatusText, ENT_QUOTES, 'UTF-8'); ?>
                                    </em>
                                    <strong>¥<?php echo ecod_pay_money((int) $ecodOrder['amount']); ?></strong>
                                    <?php if ($ecodStatus === 'pending'): ?>
                                        <?php $ecodCashier = ecod_pay_cashier_url(['order_no' => (string) $ecodOrder['order_no']]); ?>
                                        <?php if ($ecodCashier !== ''): ?>
                                            <a class="ecod_uc__order-go" href="<?php echo htmlspecialchars($ecodCashier, ENT_QUOTES, 'UTF-8'); ?>">去支付</a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </section>
    </div>


    <?php /* 模块 · 内容区下方（模块管理台） */ ecod_widgets_render("bottom_content", $this); ?>
</main>

<?php $this->need('public/aside.php'); ?>
<?php $this->need('public/footer.php'); ?>
