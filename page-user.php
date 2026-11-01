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
require_once __DIR__ . '/core/user/meta.php';
require_once __DIR__ . '/core/user/follow.php';

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
/* 顶部封面：用户自定义优先，回落主题默认（EUcCover）；都没有则显示主题色渐变 */
$ecodCover = $ecodLogged ? ecod_account_cover_url((int) $ecodUser->uid) : '';
$ecodHasCover = $ecodCover !== '';
if ($ecodCover === '') {
    $ecodCover = (string) ecod_option('EUcCover', '');
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

/* 会员档位与权益文案（与会员购买页共用 ecod_pay_tier_copy()） */
$ecodVipTiers = ($ecodLogged && function_exists('ecod_pay_vip_tiers')) ? ecod_pay_vip_tiers() : [];
$ecodVipCopy = function_exists('ecod_pay_tier_copy') ? ecod_pay_tier_copy() : [];
$ecodVipDaysLeft = 0;
if ($ecodVipInfo['active'] && (int) $ecodVipInfo['expire_at'] > 0) {
    $ecodVipDaysLeft = max(0, (int) ceil(((int) $ecodVipInfo['expire_at'] - time()) / 86400));
}
/* 当前档位的最优周期：年付优先，否则第一个有价的 */
$ecodVipBestPlan = static function (int $level) use ($ecodVipTiers): array {
    $plans = $ecodVipTiers[$level]['plans'] ?? [];
    if (!$plans) {
        return [];
    }
    return $plans['year'] ?? reset($plans);
};

$ecodLoginUrl = ecod_login_url();
$ecodRegisterUrl = rtrim((string) $this->options->adminUrl, '/') . '/register.php';

$ecodTabs = [
    'profile' => ['个人信息', 'user'],
    'vip'     => ['我的会员', 'medal'],
    'task'    => ['任务中心', 'trophy'],
    'credits' => ['我的积分', 'zap'],
    'notifs'  => ['消息中心', 'bell'],
    'posts'   => ['我的文章', 'edit'],
    'social'  => ['互动', 'comment'],
    'orders'  => ['订单', 'inbox'],
    'ticket'  => ['工单', 'headset'],
    'lottery' => ['抽奖记录', 'gift'],
];


/* 互动类合并成顶部 tab 组：一级「互动」+ 子标签（收藏 / 点赞 / 关注 / 历史） */
$ecodSocial = [
    'favs'    => ['收藏', 'star'],
    'likes'   => ['点赞', 'heart'],
    'follows' => ['关注', 'plus'],
    'history' => ['历史', 'clock'],
];

/* 积分总开关关闭（ECrOn=off）时不下发积分入口：
   否则签到按钮照常显示，点击必然被后端拒（积分未开启 / 400）。
   任务中心同理——奖励走积分账本，没有积分体系就没有任务中心 */
if (!ecod_credits_enabled()) {
    unset($ecodTabs['credits'], $ecodTabs['task']);
}

/* 任务中心：进度实时统计（服务端直出，避免进 tab 才闪一下），
   领取走 api.php?action=task_claim，成功后由 JS 就地刷新 */
$ecodTaskData = ['balance' => 0, 'credit' => ecod_credits_name(), 'streak' => 0, 'signed' => false, 'groups' => [], 'today' => ['claimed' => 0, 'total' => 0]];
$ecodTaskReady = 0;
if ($ecodLogged && ecod_credits_enabled()) {
    $ecodTaskData = ecod_tasks_list((int) $ecodUser->uid);
    $ecodTaskReady = ecod_tasks_ready($ecodTaskData);
}

/* 工单：统计卡 + 首屏 20 条（服务端直出，展开详情再走接口） */
$ecodTicketCats = ecod_ticket_cats();
$ecodTicketStats = ['pending' => 0, 'replied' => 0, 'resolved' => 0, 'closed' => 0];
$ecodTicketRows = [];
$ecodTicketUnread = 0;
if ($ecodLogged) {
    $ecodTicketStats = ecod_ticket_stats((int) $ecodUser->uid);
    $ecodTicketRows = ecod_ticket_list((int) $ecodUser->uid, '', 1, 20)['items'];
    $ecodTicketUnread = ecod_ticket_unread((int) $ecodUser->uid);
}

/* 我的投稿：服务端直出（状态 / 编辑 / 撤回都在这一屏完成） */
$ecodMyPosts = [];
if ($ecodLogged) {
    try {
        $ecodPostsDb = \Typecho\Db::get();
        $ecodMyPosts = $ecodPostsDb->fetchAll(
            $ecodPostsDb->select('cid', 'title', 'status', 'created', 'commentsNum', 'views')
                ->from('table.contents')
                ->where('authorId = ?', (int) $ecodUser->uid)
                ->where('type = ?', 'post')
                ->order('created', \Typecho\Db::SORT_DESC)
                ->limit(100)
        );
    } catch (\Throwable $e) {
        $ecodMyPosts = [];
    }
}
$ecodPostStatusText = [
    'waiting' => '待审核',
    'draft'   => '草稿',
    'publish' => '已发布',
    'hidden'  => '隐藏',
    'private' => '私密',
];
$ecodContributeUrl = function_exists('ecod_contribute_url') ? ecod_contribute_url() : '';

/* 我的关注：服务端直出（头像 / 昵称 / 关注时间，可就地取关） */
$ecodFollowRows = $ecodLogged ? ecod_follow_rows((int) $ecodUser->uid, 'following', 100) : [];

/* 抽奖记录：服务端直出最近 50 条，面板内用筛选片做客户端过滤 */
$ecodLotRows = ($ecodLogged && ecod_lottery_enabled())
    ? ecod_lottery_mine((int) $ecodUser->uid, 50)
    : [];
$ecodLotteryPageUrl = ecod_template_page_url('page-lottery.php');
?>
<main class="ecod_main" id="ecod-main">
    <?php /* 模块 · 内容区上方（模块管理台） */ ecod_widgets_render("top_content", $this); ?>

    <nav class="ecod_crumb" aria-label="面包屑">
        <a class="ecod_crumb__back" href="javascript:history.back()" aria-label="返回"><?php echo ecod_icon('chevron', 13); ?></a>
        <a href="<?php $this->options->siteUrl(); ?>"><?php echo ecod_icon('home', 12); ?>首页</a>
        <span class="ecod_crumb__sep" aria-hidden="true">/</span>
        <span aria-current="page"><?php $this->title(); ?></span>
    </nav>

    <div class="ecod_uc" data-ecod-uc>

        <!-- 顶部：封面（图片或 CSS 渐变占位）+ 身份条 -->
        <section class="ecod_uc__hero has-cover">
            <?php if ($ecodCover !== ''): ?>
                <div class="ecod_uc__cover" aria-hidden="true">
                    <img src="<?php echo htmlspecialchars($ecodCover, ENT_QUOTES, 'UTF-8'); ?>" alt="" referrerpolicy="no-referrer">
                </div>
            <?php else: ?>
                <div class="ecod_uc__cover ecod_uc__cover--ph" aria-hidden="true"></div>
            <?php endif; ?>
            <?php if ($ecodLogged): ?>
                <div class="ecod_uc__cover-ops">
                    <label class="ecod_uc__cover-btn">
                        <?php echo ecod_icon('image', 13); ?>更换封面
                        <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" data-ecod-cover-input hidden>
                    </label>
                    <?php if ($ecodHasCover): ?>
                        <button type="button" class="ecod_uc__cover-btn" data-ecod-cover-remove>移除封面</button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="ecod_uc__hero-id">
                <?php if ($ecodLogged): ?>
                    <span class="ecod_uc__avatar ecod_uc__avatar--sm">
                        <span class="ecod_nav__user-ic"><?php echo ecod_icon('user', 22); ?></span>
                        <img src="<?php echo htmlspecialchars($ecodAvatar, ENT_QUOTES, 'UTF-8'); ?>"
                             alt="" width="56" height="56" onerror="this.remove()" referrerpolicy="no-referrer">
                    </span>
                    <span class="ecod_uc__hero-main">
                        <b class="ecod_uc__name"><?php echo htmlspecialchars((string) $ecodUser->screenName, ENT_QUOTES, 'UTF-8'); ?></b>
                        <span class="ecod_uc__hero-meta">
                            <span class="ecod_uc__group"><?php echo htmlspecialchars($ecodGroupName, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if ($ecodVipInfo['active']): ?>
                                <span class="ecod_uc__vip">
                                    <?php echo ecod_icon('medal', 12); ?><?php echo htmlspecialchars($ecodVipInfo['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php echo (int) $ecodVipInfo['expire_at'] > 0 ? '· 至 ' . date('Y-m-d', (int) $ecodVipInfo['expire_at']) : '· 长期'; ?>
                                </span>
                            <?php endif; ?>
                        </span>
                    </span>
                    <span class="ecod_uc__hero-ops">
                        <a class="ecod-btn ecod-btn--ghost" href="<?php $this->options->adminUrl(); ?>" target="_blank" rel="noopener">管理后台</a>
                        <a class="ecod-btn ecod-btn--ghost" href="<?php $this->options->logoutUrl(); ?>">退出登录</a>
                    </span>
                <?php else: ?>
                    <span class="ecod_uc__avatar ecod_uc__avatar--sm ecod_uc__avatar--guest" aria-hidden="true"><?php echo ecod_icon('user', 22); ?></span>
                    <span class="ecod_uc__hero-main">
                        <b class="ecod_uc__name">游客</b>
                        <span class="ecod_uc__hero-meta"><span class="ecod_uc__mail">登录后可展示账户资料</span></span>
                    </span>
                    <span class="ecod_uc__hero-ops">
                        <a class="ecod-btn" data-ecod-login-open data-no-pjax href="<?php echo htmlspecialchars($ecodLoginUrl, ENT_QUOTES, 'UTF-8'); ?>?referer=<?php echo rawurlencode($this->permalink); ?>">登录</a>
                        <?php if ((int) $this->options->allowRegister === 1): ?>
                            <a class="ecod-btn ecod-btn--ghost" href="<?php echo htmlspecialchars($ecodRegisterUrl, ENT_QUOTES, 'UTF-8'); ?>">注册</a>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>
        </section>

        <!-- 左侧：菜单 -->
        <aside class="ecod_uc__side">
            <nav class="ecod_uc__menu" aria-label="个人中心菜单">
                <?php foreach ($ecodTabs as $ecodKey => $ecodTab): ?>
                    <?php /* 我的文章的数量在服务端已知，直接带上；其余由 JS 回填 */ ?>
                    <?php if ($ecodKey === 'task'): ?>
                        <?php /* 任务中心：待领数在服务端已知，直接带上 */ ?>
                        <button type="button" class="ecod_uc__tab" data-ecod-uc-tab="task">
                            <?php echo ecod_icon($ecodTab[1], 15); ?>
                            <span><?php echo $ecodTab[0]; ?></span>
                            <em data-ecod-uc-count="task"<?php echo $ecodTaskReady > 0 ? '' : ' hidden'; ?>><?php echo $ecodTaskReady > 99 ? '99+' : (int) $ecodTaskReady; ?></em>
                        </button>
                    <?php elseif ($ecodKey === 'ticket'): ?>
                        <button type="button" class="ecod_uc__tab" data-ecod-uc-tab="ticket">
                            <?php echo ecod_icon($ecodTab[1], 15); ?>
                            <span><?php echo $ecodTab[0]; ?></span>
                            <em data-ecod-uc-count="ticket"<?php echo $ecodTicketUnread > 0 ? '' : ' hidden'; ?>><?php echo $ecodTicketUnread > 99 ? '99+' : (int) $ecodTicketUnread; ?></em>
                        </button>
                    <?php elseif ($ecodKey === 'posts'): ?>
                        <button type="button" class="ecod_uc__tab" data-ecod-uc-tab="posts">
                            <?php echo ecod_icon($ecodTab[1], 15); ?>
                            <span><?php echo $ecodTab[0]; ?></span>
                            <?php if ($ecodMyPosts): ?><em data-ecod-uc-count="posts"><?php echo count($ecodMyPosts) > 99 ? '99+' : count($ecodMyPosts); ?></em><?php endif; ?>
                        </button>
                    <?php elseif ($ecodKey === 'social'): ?>
                        <?php /* 互动组：点击后内容区顶部展开子标签（收藏/点赞/关注/历史） */ ?>
                        <button type="button" class="ecod_uc__tab" data-ecod-uc-group-tab="social">
                            <?php echo ecod_icon($ecodTab[1], 15); ?>
                            <span><?php echo $ecodTab[0]; ?></span>
                        </button>
                    <?php else: ?>
                        <button type="button" class="ecod_uc__tab<?php echo $ecodKey === 'profile' ? ' is-active' : ''; ?>"
                                data-ecod-uc-tab="<?php echo $ecodKey; ?>">
                            <?php echo ecod_icon($ecodTab[1], 15); ?>
                            <span><?php echo $ecodTab[0]; ?></span>
                            <em data-ecod-uc-count="<?php echo $ecodKey; ?>" hidden>0</em>
                        </button>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
        </aside>

        <!-- 右侧：内容面板 -->
        <section class="ecod_uc__body">

            <!-- 互动子标签：点侧边「互动」后展开（收藏 / 点赞 / 关注 / 历史） -->
            <div class="ecod_uc__subtabs" data-ecod-uc-subbar hidden>
                <?php foreach ($ecodSocial as $ecodSKey => $ecodS): ?>
                    <button type="button" class="ecod_uc__subtab" data-ecod-uc-tab="<?php echo $ecodSKey; ?>">
                        <?php echo ecod_icon($ecodS[1], 14); ?>
                        <span><?php echo $ecodS[0]; ?></span>
                        <?php if ($ecodSKey === 'follows' && $ecodFollowRows): ?>
                            <em data-ecod-uc-count="follows"><?php echo count($ecodFollowRows) > 99 ? '99+' : count($ecodFollowRows); ?></em>
                        <?php else: ?>
                            <em data-ecod-uc-count="<?php echo $ecodSKey; ?>" hidden>0</em>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </div>

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
                            <label class="ecod_uc__form-row">
                                <span>一句话简介</span>
                                <input type="text" name="intro" maxlength="120" placeholder="展示在作者主页与文章页作者卡上"
                                       value="<?php echo htmlspecialchars(ecod_user_intro((int) $ecodUser->uid), ENT_QUOTES, 'UTF-8'); ?>">
                            </label>
                            <p class="ecod_uc__form-msg" data-ecod-account-msg="profile" hidden></p>
                            <div class="ecod_uc__form-ops">
                                <button type="submit" class="ecod-btn">保存资料</button>
                            </div>
                        </form>

                        <!-- 修改密码 -->
                        <form class="ecod_uc__form" data-ecod-account-form="password" novalidate>
                            <b class="ecod_uc__form-sub">修改密码</b>
                            <?php /* 密码管理器的用户名锚点：表单需要有一个 username 字段，浏览器才认得出「这是谁的密码」 */ ?>
                            <input type="text" name="username" hidden readonly autocomplete="username"
                                   value="<?php echo htmlspecialchars((string) $ecodUser->screenName, ENT_QUOTES, 'UTF-8'); ?>">
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

            <!-- 我的文章：投稿与已发布，服务端直出 -->
            <div class="ecod_uc__panel" data-ecod-uc-panel="posts" hidden>
                <h3 class="ecod_uc__title">我的文章</h3>
                <?php if (!$ecodLogged): ?>
                    <div class="ecod_uc__empty">
                        <p>登录后这里会显示你的投稿与发布记录。</p>
                    </div>
                <?php elseif (!$ecodMyPosts): ?>
                    <div class="ecod_uc__empty">
                        <p>还没有投过稿。</p>
                        <?php if ($ecodContributeUrl !== '' && ecod_option('EContribute', 'off') === 'on'): ?>
                            <a class="ecod-btn ecod-btn--primary" href="<?php echo htmlspecialchars($ecodContributeUrl, ENT_QUOTES, 'UTF-8'); ?>">去投稿</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="ecod_uc__desc">待审核与草稿可以继续修改，已发布的文章请在后台管理。</p>
                    <div class="ecod_uc__list" data-ecod-uc-posts
                         data-ecod-token="<?php echo htmlspecialchars(ecod_account_token(), ENT_QUOTES, 'UTF-8'); ?>"
                         data-ecod-api="<?php echo htmlspecialchars(ecod_api_url(), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php foreach ($ecodMyPosts as $ecodMyPost): ?>
                            <?php
                            $ecodMyStatus = (string) $ecodMyPost['status'];
                            $ecodMyEditable = in_array($ecodMyStatus, ['waiting', 'draft'], true);
                            ?>
                            <div class="ecod_uc__item ecod_uc__item--post" data-state="<?php echo htmlspecialchars($ecodMyStatus, ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="ecod_uc__main">
                                    <b><?php echo htmlspecialchars((string) $ecodMyPost['title'], ENT_QUOTES, 'UTF-8'); ?></b>
                                    <span><?php echo date('Y-m-d', (int) $ecodMyPost['created']); ?>
                                        · <?php echo htmlspecialchars($ecodPostStatusText[$ecodMyStatus] ?? $ecodMyStatus, ENT_QUOTES, 'UTF-8'); ?>
                                        · <?php echo (int) $ecodMyPost['views']; ?> 阅读
                                        · <?php echo ecod_comment_count((int) $ecodMyPost['cid']); ?> 评论</span>
                                </div>
                                <div class="ecod_uc__post-ops">
                                    <?php if ($ecodMyEditable && $ecodContributeUrl !== ''): ?>
                                        <a class="ecod_uc__order-go" href="<?php
                                            echo htmlspecialchars($ecodContributeUrl . (strpos($ecodContributeUrl, '?') === false ? '?' : '&') . 'edit=' . (int) $ecodMyPost['cid'], ENT_QUOTES, 'UTF-8');
                                        ?>">修改</a>
                                        <button type="button" class="ecod_uc__linkbtn" data-ecod-ctb-del="<?php echo (int) $ecodMyPost['cid']; ?>">撤回</button>
                                    <?php elseif ($ecodMyStatus === 'publish'): ?>
                                        <em class="is-paid">已上线</em>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 我的关注：服务端直出，可就地取关 -->
            <div class="ecod_uc__panel" data-ecod-uc-panel="follows" hidden>
                <h3 class="ecod_uc__title">我的关注</h3>
                <?php if (!$ecodLogged): ?>
                    <div class="ecod_uc__empty">
                        <p>登录后这里会显示你关注的作者。</p>
                    </div>
                <?php elseif (!$ecodFollowRows): ?>
                    <div class="ecod_uc__empty">
                        <p>还没有关注任何人。作者主页与文章页的作者卡上点「关注」，就会出现在这里。</p>
                    </div>
                <?php else: ?>
                    <p class="ecod_uc__desc">关注的作者有新文章时会按发布时间出现在首页。</p>
                    <div class="ecod_uc__list">
                        <?php foreach ($ecodFollowRows as $ecodFollowRow): ?>
                            <div class="ecod_uc__item ecod_uc__follow-item">
                                <a class="ecod_uc__follow-user" href="<?php
                                    echo htmlspecialchars(ecod_user_archive_url((int) $ecodFollowRow['uid']), ENT_QUOTES, 'UTF-8');
                                ?>">
                                    <img src="<?php echo htmlspecialchars((string) $ecodFollowRow['avatar'], ENT_QUOTES, 'UTF-8'); ?>"
                                         alt="" width="36" height="36" loading="lazy" referrerpolicy="no-referrer" onerror="this.remove()">
                                    <span class="ecod_uc__main">
                                        <b><?php echo htmlspecialchars((string) $ecodFollowRow['name'], ENT_QUOTES, 'UTF-8'); ?></b>
                                        <span><?php echo date('Y-m-d', (int) $ecodFollowRow['created']); ?> 关注</span>
                                    </span>
                                </a>
                                <button type="button" class="ecod_ucard__follow is-on"
                                        data-ecod-follow="<?php echo (int) $ecodFollowRow['uid']; ?>"
                                        data-ecod-token="<?php echo htmlspecialchars(ecod_account_token(), ENT_QUOTES, 'UTF-8'); ?>"
                                        aria-pressed="true"><span>已关注</span></button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 任务中心：进度服务端直出，领取走 api task_claim 后就地刷新 -->
            <?php if (ecod_credits_enabled()): ?>
            <div class="ecod_uc__panel" data-ecod-uc-panel="task" hidden>
                <h3 class="ecod_uc__title">任务中心</h3>
                <?php if (!$ecodLogged): ?>
                    <div class="ecod_uc__empty">
                        <?php echo ecod_icon('trophy', 34); ?>
                        <p>登录后可以查看任务进度，领取<?php echo htmlspecialchars(ecod_credits_name(), ENT_QUOTES, 'UTF-8'); ?>。</p>
                        <?php if ($ecodLoginUrl !== ''): ?>
                            <a class="ecod-btn ecod-btn--primary" href="<?php echo htmlspecialchars($ecodLoginUrl, ENT_QUOTES, 'UTF-8'); ?>">去登录</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php
                    $ecodTaskTodayPct = 0;
                    if ((int) $ecodTaskData['today']['total'] > 0) {
                        $ecodTaskTodayPct = (int) round($ecodTaskData['today']['claimed'] / $ecodTaskData['today']['total'] * 100);
                    }
                    $ecodCheckinReward = max(0, (int) ecod_option('ECrCheckin', 0));
                    ?>
                    <p class="ecod_uc__desc">完成任务领取<?php echo htmlspecialchars($ecodTaskData['credit'], ENT_QUOTES, 'UTF-8'); ?>，可在「我的积分」兑换会员与卡密。</p>
                    <div class="ecod_task" data-ecod-task
                         data-api="<?php echo htmlspecialchars(ecod_api_url(), ENT_QUOTES, 'UTF-8'); ?>"
                         data-token="<?php echo htmlspecialchars(ecod_account_token(), ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="ecod_task__sum">
                            <div class="ecod_task__cell">
                                <small>我的<?php echo htmlspecialchars($ecodTaskData['credit'], ENT_QUOTES, 'UTF-8'); ?></small>
                                <b data-ecod-task-balance><?php echo (int) $ecodTaskData['balance']; ?></b>
                            </div>
                            <div class="ecod_task__cell">
                                <small>连续签到</small>
                                <b><span data-ecod-task-streak><?php echo (int) $ecodTaskData['streak']; ?></span><em>天</em></b>
                            </div>
                            <div class="ecod_task__cell ecod_task__cell--bar">
                                <small>今日任务</small>
                                <div class="ecod_task__sum-bar"><i data-ecod-task-todaybar style="width:<?php echo $ecodTaskTodayPct; ?>%"></i></div>
                                <span data-ecod-task-todaynum><?php echo (int) $ecodTaskData['today']['claimed']; ?> / <?php echo (int) $ecodTaskData['today']['total']; ?></span>
                            </div>
                            <button type="button" class="ecod-btn ecod-btn--primary" data-ecod-task-signin<?php echo !empty($ecodTaskData['signed']) ? ' disabled' : ''; ?>>
                                <?php echo ecod_icon(!empty($ecodTaskData['signed']) ? 'check' : 'zap', 14); ?><span data-ecod-task-signin-text><?php echo !empty($ecodTaskData['signed']) ? '今日已签到' : '签到 +' . $ecodCheckinReward; ?></span>
                            </button>
                        </div>

                        <?php foreach ($ecodTaskData['groups'] as $ecodGroup): ?>
                            <div class="ecod_task__group" data-group="<?php echo htmlspecialchars($ecodGroup['key'], ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="ecod_task__ghd">
                                    <b><?php echo htmlspecialchars($ecodGroup['title'], ENT_QUOTES, 'UTF-8'); ?></b>
                                    <span>· <?php echo htmlspecialchars($ecodGroup['desc'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <i data-ecod-task-gdone>已完成 <?php echo (int) $ecodGroup['done']; ?>/<?php echo (int) $ecodGroup['total']; ?></i>
                                </div>
                                <div class="ecod_task__grid">
                                    <?php foreach ($ecodGroup['items'] as $ecodItem):
                                        $ecodState = (string) $ecodItem['state'];
                                        $ecodNeed = max(1, (int) $ecodItem['need']);
                                        $ecodProgress = min($ecodNeed, (int) $ecodItem['progress']);
                                        $ecodPct = (int) round($ecodProgress / $ecodNeed * 100);
                                        $ecodLeft = max(0, $ecodNeed - $ecodProgress);
                                    ?>
                                    <div class="ecod_task__card<?php echo $ecodState === 'claimable' ? ' is-claimable' : ($ecodState === 'claimed' ? ' is-claimed' : ''); ?>"
                                         data-task="<?php echo htmlspecialchars($ecodItem['key'], ENT_QUOTES, 'UTF-8'); ?>"
                                         data-state="<?php echo htmlspecialchars($ecodState, ENT_QUOTES, 'UTF-8'); ?>">
                                        <span class="ecod_task__ic"><?php echo ecod_icon((string) $ecodItem['icon'], 17); ?></span>
                                        <div class="ecod_task__body">
                                            <b><?php echo htmlspecialchars($ecodItem['title'], ENT_QUOTES, 'UTF-8'); ?></b>
                                            <span><?php echo htmlspecialchars($ecodItem['desc'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                        <em class="ecod_task__rw">+<?php echo (int) $ecodItem['reward']; ?></em>
                                        <div class="ecod_task__foot">
                                            <div class="ecod_task__pbar"><i data-ecod-task-pbar style="width:<?php echo $ecodPct; ?>%"></i></div>
                                            <span class="ecod_task__num" data-ecod-task-pnum><?php echo $ecodProgress; ?>/<?php echo $ecodNeed; ?></span>
                                            <span class="ecod_task__ops" data-ecod-task-ops>
                                                <?php if ($ecodState === 'claimable'): ?>
                                                    <button type="button" class="ecod-btn ecod-btn--primary ecod-btn--sm"
                                                            data-ecod-task-claim="<?php echo htmlspecialchars($ecodItem['key'], ENT_QUOTES, 'UTF-8'); ?>">领取 +<?php echo (int) $ecodItem['reward']; ?></button>
                                                <?php elseif ($ecodState === 'claimed'): ?>
                                                    <em class="ecod_task__done"><?php echo ecod_icon('check', 13); ?>
                                                        <?php echo (!empty($ecodItem['threshold']) || $ecodNeed <= 1) ? '已领取' : '已领 ' . (int) $ecodItem['claimed'] . '/' . $ecodNeed; ?>
                                                    </em>
                                                <?php else: ?>
                                                    <button type="button" class="ecod-btn ecod-btn--ghost ecod-btn--sm" disabled>还差 <?php echo $ecodLeft; ?></button>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- 我的积分：签到 + 任务 + 流水，JS 拉 credits_state 渲染 -->
            <?php if (ecod_credits_enabled()): ?>
            <div class="ecod_uc__panel" data-ecod-uc-panel="credits" hidden>
                <h3 class="ecod_uc__title">我的积分</h3>
                <?php if (!$ecodLogged): ?>
                    <div class="ecod_uc__empty">
                        <p>登录后可查看积分余额、签到与流水。</p>
                        <?php if ($ecodLoginUrl !== ''): ?>
                            <a class="ecod-btn ecod-btn--primary" href="<?php echo htmlspecialchars($ecodLoginUrl, ENT_QUOTES, 'UTF-8'); ?>">去登录</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="ecod_credits" data-ecod-credits
                         data-api="<?php echo htmlspecialchars(ecod_api_url(), ENT_QUOTES, 'UTF-8'); ?>"
                         data-token="<?php echo htmlspecialchars(ecod_account_token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="ecod_credits__card">
                            <div class="ecod_credits__head">
                                <span class="ecod_credits__ico" aria-hidden="true"><?php echo ecod_icon('zap', 18); ?></span>
                                <b class="ecod_credits__title" data-ecod-credits-name><?php echo htmlspecialchars(ecod_credits_name(), ENT_QUOTES, 'UTF-8'); ?></b>
                                <button type="button" class="ecod_credits__apply" data-ecod-credits-signin>
                                    <?php echo ecod_icon('zap', 13); ?><span>每日签到</span>
                                </button>
                            </div>
                            <div class="ecod_credits__body">
                                <p class="ecod_credits__line">当前拥有 <b data-ecod-credits-balance>—</b> <?php echo htmlspecialchars(ecod_credits_name(), ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="ecod_credits__hint">签到、评论、发文都能赚 <?php echo htmlspecialchars(ecod_credits_name(), ENT_QUOTES, 'UTF-8'); ?>，可在积分商城兑换会员与卡密。</p>
                                <div class="ecod_credits__meter" data-ecod-credits-meter aria-hidden="true"></div>
                            </div>
                        </div>
                        <div class="ecod_credits__facts" data-ecod-credits-facts hidden>
                            <div class="ecod_credits__fact"><small>累计收入</small><b data-ecod-credits-earn>0</b></div>
                            <div class="ecod_credits__fact"><small>累计支出</small><b data-ecod-credits-spend>0</b></div>
                        </div>
                        <p class="ecod_credits__msg" data-ecod-credits-msg role="status"></p>
                        <?php if (ecod_ref_on() && (int) ecod_option('ECrRefPercent', 10) > 0):
                            /* 推广返佣：专属链接 + 累计收益（guaqi 式拉新） */
                            $ecodRefLink = ecod_ref_link((int) $ecodUser->uid);
                            $ecodRefEarned = ecod_ref_earned((int) $ecodUser->uid); ?>
                        <div class="ecod_ref">
                            <div class="ecod_ref__hd">
                                <b>推广返佣</b>
                                <span>好友通过你的链接注册，TA 的每笔订单按 <?php echo (int) ecod_option('ECrRefPercent', 10); ?>% 返给你<?php echo htmlspecialchars(ecod_credits_name(), ENT_QUOTES, 'UTF-8'); ?>（1 元 = 100）</span>
                            </div>
                            <div class="ecod_ref__row">
                                <input class="ecod_ref__link" type="text" readonly
                                       value="<?php echo htmlspecialchars($ecodRefLink, ENT_QUOTES, 'UTF-8'); ?>"
                                       onclick="this.select()" aria-label="我的推广链接">
                                <button type="button" class="ecod_ref__copy" data-ecod-ref-copy>
                                    <?php echo ecod_icon('link', 13); ?><span>复制链接</span>
                                </button>
                            </div>
                            <div class="ecod_ref__earn">累计推广收益 <b><?php echo $ecodRefEarned; ?></b> <?php echo htmlspecialchars(ecod_credits_name(), ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="ecod_credits__detail">
                            <div class="ecod_credits__tabs" data-ecod-credits-tabs role="tablist" aria-label="积分明细类型"></div>
                            <div class="ecod_credits__log" data-ecod-credits-log></div>
                            <div class="ecod_credits__pager" data-ecod-credits-pager hidden>
                                <button type="button" class="ecod_credits__pagebtn" data-ecod-credits-prev aria-label="上一页">‹</button>
                                <span class="ecod_credits__pageinfo" data-ecod-credits-pageinfo></span>
                                <button type="button" class="ecod_credits__pagebtn" data-ecod-credits-next aria-label="下一页">›</button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- 我的会员：状态卡 + 权益对照 + 续费/升级 + 卡密兑换（服务端直出） -->
            <div class="ecod_uc__panel" data-ecod-uc-panel="vip" hidden>
                <h3 class="ecod_uc__title">我的会员</h3>
                <?php if (!$ecodLogged): ?>
                    <div class="ecod_uc__empty">
                        <p>登录后可查看会员状态、权益与卡密兑换。</p>
                        <?php if ($ecodLoginUrl !== ''): ?>
                            <a class="ecod-btn ecod-btn--primary" href="<?php echo htmlspecialchars($ecodLoginUrl, ENT_QUOTES, 'UTF-8'); ?>">去登录</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="ecod_vipcard" data-ecod-vip
                         data-api="<?php echo htmlspecialchars(ecod_api_url(), ENT_QUOTES, 'UTF-8'); ?>"
                         data-token="<?php echo htmlspecialchars(ecod_account_token(), ENT_QUOTES, 'UTF-8'); ?>">

                        <?php
                        $ecodVipActive = (bool) $ecodVipInfo['active'];
                        $ecodVipForever = $ecodVipActive && (int) $ecodVipInfo['expire_at'] <= 0;
                        $ecodVipExpiring = $ecodVipActive && !$ecodVipForever && $ecodVipDaysLeft <= 30;
                        $ecodVipBest = $ecodVipBestPlan($ecodVipActive ? (int) $ecodVipInfo['level'] : 2);
                        ?>
                        <div class="ecod_vipcard__head<?php echo $ecodVipExpiring ? ' is-expiring' : ''; ?>">
                            <span class="ecod_vipcard__ico" aria-hidden="true"><?php echo ecod_icon('medal', 22); ?></span>
                            <div class="ecod_vipcard__main">
                                <b class="ecod_vipcard__name"><?php echo htmlspecialchars($ecodVipActive ? $ecodVipInfo['name'] : '未开通会员', ENT_QUOTES, 'UTF-8'); ?></b>
                                <span class="ecod_vipcard__expire">
                                    <?php if ($ecodVipActive && $ecodVipForever): ?>
                                        长期有效
                                    <?php elseif ($ecodVipActive): ?>
                                        至 <?php echo date('Y-m-d', (int) $ecodVipInfo['expire_at']); ?> · 还剩 <?php echo $ecodVipDaysLeft; ?> 天
                                    <?php else: ?>
                                        开通后会员专属内容随时读
                                    <?php endif; ?>
                                </span>
                            </div>
                            <em class="ecod_vipcard__state"><?php
                                echo !$ecodVipActive ? '未开通'
                                    : ($ecodVipForever ? '永久'
                                    : ($ecodVipExpiring ? '剩 ' . $ecodVipDaysLeft . ' 天' : '生效中'));
                            ?></em>
                        </div>

                        <?php if ($ecodVipActive && !empty($ecodVipCopy[(int) $ecodVipInfo['level']]['perks'])): ?>
                            <p class="ecod_vipcard__summary">本档权益：<?php echo htmlspecialchars(implode(' · ', $ecodVipCopy[(int) $ecodVipInfo['level']]['perks']), ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>

                        <div class="ecod_vipcard__ops">
                            <?php if ($ecodVipBest): ?>
                                <a class="ecod-btn ecod-btn--primary" href="<?php echo htmlspecialchars(ecod_pay_cashier_url([
                                    'kind'  => 'vip',
                                    'level' => $ecodVipActive ? (int) $ecodVipInfo['level'] : 2,
                                    'plan'  => (string) $ecodVipBest['key'],
                                ]), ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo $ecodVipActive ? '立即续费' : '开通会员'; ?>
                                </a>
                                <?php if ($ecodVipActive && (int) $ecodVipInfo['level'] < 2 && !empty($ecodVipTiers[2]['plans'])): ?>
                                    <?php $ecodVipUp = $ecodVipBestPlan(2); ?>
                                    <?php if ($ecodVipUp): ?>
                                        <a class="ecod-btn ecod-btn--ghost" href="<?php echo htmlspecialchars(ecod_pay_cashier_url([
                                            'kind'  => 'vip',
                                            'level' => 2,
                                            'plan'  => (string) $ecodVipUp['key'],
                                        ]), ENT_QUOTES, 'UTF-8'); ?>">升级<?php echo htmlspecialchars($ecodVipTiers[2]['name'], ENT_QUOTES, 'UTF-8'); ?></a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                            <button type="button" class="ecod-btn ecod-btn--ghost" data-ecod-vip-redeem-toggle>卡密兑换</button>
                        </div>

                        <?php if (!empty($ecodVipTiers)): ?>
                            <h4 class="ecod_vipcard__sub">权益对照</h4>
                            <div class="ecod_vipcard__table-wrap">
                                <table class="ecod_vipcard__table">
                                    <thead>
                                        <tr>
                                            <th>权益</th>
                                            <?php foreach ($ecodVipTiers as $ecodVipTierLevel => $ecodVipTier): ?>
                                                <th<?php echo $ecodVipActive && (int) $ecodVipInfo['level'] === (int) $ecodVipTierLevel ? ' class="is-me"' : ''; ?>><?php echo htmlspecialchars($ecodVipTier['name'], ENT_QUOTES, 'UTF-8'); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $ecodVipPerkRows = [];
                                        $ecodVipMaxPerks = 0;
                                        foreach ($ecodVipTiers as $ecodVipTierLevel => $ecodVipTier) {
                                            $ecodVipPerkRows[$ecodVipTierLevel] = $ecodVipCopy[$ecodVipTierLevel]['perks'] ?? [];
                                            $ecodVipMaxPerks = max($ecodVipMaxPerks, count($ecodVipPerkRows[$ecodVipTierLevel]));
                                        }
                                        for ($ecodVipRow = 0; $ecodVipRow < $ecodVipMaxPerks; $ecodVipRow++): ?>
                                            <tr>
                                                <?php foreach ($ecodVipPerkRows as $ecodVipTierLevel => $ecodVipPerks): ?>
                                                    <?php if ($ecodVipTierLevel === 1): ?>
                                                        <td><?php echo htmlspecialchars($ecodVipPerks[$ecodVipRow] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <?php else: ?>
                                                        <td<?php echo $ecodVipActive && (int) $ecodVipInfo['level'] === (int) $ecodVipTierLevel ? ' class="is-me"' : ''; ?>><?php echo htmlspecialchars($ecodVipPerks[$ecodVipRow] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endfor; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($ecodVipTiers as $ecodVipTierLevel => $ecodVipTier): ?>
                            <?php if (empty($ecodVipTier['plans'])) {
                                continue;
                            } ?>
                            <h4 class="ecod_vipcard__sub"><?php echo htmlspecialchars($ecodVipTier['name'], ENT_QUOTES, 'UTF-8'); ?> · 周期价格</h4>
                            <div class="ecod_vipcard__plans">
                                <?php foreach ($ecodVipTier['plans'] as $ecodVipPlan): ?>
                                    <a class="ecod_vipcard__plan" href="<?php echo htmlspecialchars(ecod_pay_cashier_url([
                                        'kind'  => 'vip',
                                        'level' => (int) $ecodVipTierLevel,
                                        'plan'  => (string) $ecodVipPlan['key'],
                                    ]), ENT_QUOTES, 'UTF-8'); ?>">
                                        <b><?php echo htmlspecialchars($ecodVipPlan['label'], ENT_QUOTES, 'UTF-8'); ?></b>
                                        <em>¥<?php echo ecod_pay_money((int) $ecodVipPlan['cents']); ?></em>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>

                        <div class="ecod_vipcard__redeem" data-ecod-vip-redeem hidden>
                            <div class="ecod_vipcard__redeem-row">
                                <input type="text" maxlength="64" placeholder="输入卡密…" data-ecod-vip-code autocomplete="off">
                                <button type="button" class="ecod-btn ecod-btn--primary" data-ecod-vip-redeem>兑换</button>
                            </div>
                            <p class="ecod_uc__form-msg" data-ecod-vip-msg role="status" hidden></p>
                        </div>

                        <?php if ($ecodPayOrders): ?>
                            <h4 class="ecod_vipcard__sub">
                                最近订单
                                <button type="button" class="ecod_vipcard__all" data-ecod-uc-tab="orders">全部订单 →</button>
                            </h4>
                            <div class="ecod_uc__list ecod_vipcard__orders">
                                <?php foreach (array_slice($ecodPayOrders, 0, 3) as $ecodOrder): ?>
                                    <div class="ecod_uc__item">
                                        <div class="ecod_uc__main">
                                            <b><?php echo htmlspecialchars(mb_substr((string) $ecodOrder['title'], 0, 40, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></b>
                                            <span><?php echo date('Y-m-d', (int) ($ecodOrder['paid_at'] ?: $ecodOrder['created'])); ?> · <?php echo htmlspecialchars((string) $ecodOrder['order_no'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                        <strong>¥<?php echo ecod_pay_money((int) $ecodOrder['amount']); ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
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
                <div class="ecod_uc__list" data-ecod-uc-list="likes"></div>
            </div>

            <!-- 消息中心：分类筛选 + 行内展开 + 增量加载（一次拉 50 条，前端分类） -->
            <div class="ecod_uc__panel" data-ecod-uc-panel="notifs" hidden>
                <h3 class="ecod_uc__title">消息中心</h3>
                <p class="ecod_uc__desc">评论回复、点赞收藏、友链审核、工单回复与全站公告都在这里。</p>
                <?php if ($ecodLogged): ?>
                    <div class="ecod_msgc" data-ecod-msgc
                         data-api="<?php echo htmlspecialchars(ecod_api_url(), ENT_QUOTES, 'UTF-8'); ?>"
                         data-token="<?php echo htmlspecialchars(ecod_account_token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="ecod_msgc__bar">
                            <div class="ecod_msgc__chips" role="tablist" aria-label="消息分类">
                                <button type="button" class="ecod_msgc__chip is-active" data-ecod-msgc-filter="all">全部 <u data-ecod-msgc-count="all">0</u></button>
                                <button type="button" class="ecod_msgc__chip" data-ecod-msgc-filter="unread">未读 <u data-ecod-msgc-count="unread">0</u></button>
                                <button type="button" class="ecod_msgc__chip" data-ecod-msgc-filter="interact">互动 <u data-ecod-msgc-count="interact">0</u></button>
                                <button type="button" class="ecod_msgc__chip" data-ecod-msgc-filter="system">系统 <u data-ecod-msgc-count="system">0</u></button>
                            </div>
                            <div class="ecod_msgc__ops">
                                <button type="button" class="ecod-btn ecod-btn--ghost ecod-btn--sm" data-ecod-notif-readall>全部已读</button>
                                <button type="button" class="ecod-btn ecod-btn--ghost ecod-btn--sm" data-ecod-notif-clear>清空已读</button>
                            </div>
                        </div>
                        <div class="ecod_msgc__list" data-ecod-msgc-list aria-live="polite"></div>
                        <div class="ecod_msgc__more">
                            <button type="button" class="ecod-btn ecod-btn--ghost ecod-btn--sm" data-ecod-msgc-more hidden>加载更多</button>
                            <span data-ecod-msgc-end hidden>没有更多了</span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="ecod_uc__empty">
                        <?php echo ecod_icon('bell', 34); ?>
                        <p>登录后可查看站内消息。</p>
                        <?php if ($ecodLoginUrl !== ''): ?>
                            <a class="ecod-btn ecod-btn--primary" href="<?php echo htmlspecialchars($ecodLoginUrl, ENT_QUOTES, 'UTF-8'); ?>">去登录</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
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
                                        · <?php $ecodOrderKind = (string) $ecodOrder['kind'];
                                            echo $ecodOrderKind === 'vip' ? '会员' : ($ecodOrderKind === 'ad' ? '广告' : '单篇'); ?>
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


            <!-- 工单：列表内联展开详情（少一次跳转，tab 内不破坏滚动位置） -->
            <div class="ecod_uc__panel" data-ecod-uc-panel="ticket" hidden>
                <?php if (!$ecodLogged): ?>
                    <h3 class="ecod_uc__title">工单</h3>
                    <div class="ecod_uc__empty">
                        <?php echo ecod_icon('headset', 34); ?>
                        <p>登录后可提交工单，客服回复会推送到消息中心。</p>
                        <?php if ($ecodLoginUrl !== ''): ?>
                            <a class="ecod-btn ecod-btn--primary" href="<?php echo htmlspecialchars($ecodLoginUrl, ENT_QUOTES, 'UTF-8'); ?>">去登录</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                <div class="ecod_ticket" data-ecod-ticket
                     data-api="<?php echo htmlspecialchars(ecod_api_url(), ENT_QUOTES, 'UTF-8'); ?>"
                     data-token="<?php echo htmlspecialchars(ecod_account_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="ecod_ticket__head">
                        <h3 class="ecod_uc__title">工单</h3>
                        <button type="button" class="ecod-btn ecod-btn--primary ecod-btn--sm" data-ecod-ticket-new>
                            <?php echo ecod_icon('plus', 13); ?>新建工单
                        </button>
                    </div>
                    <p class="ecod_uc__desc">账号、付费、内容问题都可以提，客服回复会推送到消息中心。</p>

                    <div class="ecod_ticket__stats">
                        <div class="ecod_ticket__stat" data-s="pending">
                            <small>待处理</small><b data-ecod-ticket-stat="pending"><?php echo (int) $ecodTicketStats['pending']; ?></b>
                        </div>
                        <div class="ecod_ticket__stat" data-s="replied">
                            <small>已回复</small><b data-ecod-ticket-stat="replied"><?php echo (int) $ecodTicketStats['replied']; ?></b>
                        </div>
                        <div class="ecod_ticket__stat" data-s="resolved">
                            <small>已解决</small><b data-ecod-ticket-stat="resolved"><?php echo (int) $ecodTicketStats['resolved']; ?></b>
                        </div>
                    </div>

                    <form class="ecod_ticket__new" data-ecod-ticket-form hidden novalidate>
                        <label class="ecod_ticket__field">
                            <span>问题分类</span>
                            <select data-ecod-ticket-cat>
                                <?php foreach ($ecodTicketCats as $ecodCatKey => $ecodCatName): ?>
                                    <option value="<?php echo htmlspecialchars($ecodCatKey, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ecodCatName, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="ecod_ticket__field">
                            <span>标题</span>
                            <input type="text" maxlength="60" placeholder="一句话说明问题，4–60 字" data-ecod-ticket-title>
                        </label>
                        <label class="ecod_ticket__field">
                            <span>详细描述</span>
                            <textarea maxlength="2000" rows="4" placeholder="复现步骤、订单号、截图链接…（10–2000 字）" data-ecod-ticket-text></textarea>
                        </label>
                        <p class="ecod_ticket__msg" data-ecod-ticket-msg role="status" hidden></p>
                        <div class="ecod_ticket__newops">
                            <button type="button" class="ecod-btn ecod-btn--ghost ecod-btn--sm" data-ecod-ticket-cancel>取消</button>
                            <button type="submit" class="ecod-btn ecod-btn--primary ecod-btn--sm" data-ecod-ticket-submit>提交工单</button>
                        </div>
                    </form>

                    <div class="ecod_ticket__list" data-ecod-ticket-list<?php echo $ecodTicketRows ? '' : ' hidden'; ?>>
                        <?php foreach ($ecodTicketRows as $ecodTk):
                            $ecodDerived = (string) $ecodTk['derived'];
                        ?>
                            <div class="ecod_ticket__item" data-id="<?php echo (int) $ecodTk['id']; ?>" data-derived="<?php echo htmlspecialchars($ecodDerived, ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="button" class="ecod_ticket__hd" data-ecod-ticket-toggle>
                                    <span class="ecod_ticket__no"><?php echo htmlspecialchars($ecodTk['no'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="ecod_ticket__main">
                                        <b><?php echo htmlspecialchars($ecodTk['title'], ENT_QUOTES, 'UTF-8'); ?></b>
                                        <span class="ecod_ticket__meta">
                                            <?php if (!empty($ecodTk['unread'])): ?><i class="ecod_ticket__dot" aria-label="有新回复"></i><?php endif; ?>
                                            <em><?php echo htmlspecialchars($ecodTk['catText'], ENT_QUOTES, 'UTF-8'); ?></em>
                                            · <?php echo (int) $ecodTk['msgs']; ?> 条消息
                                            · <?php echo date('m-d H:i', (int) $ecodTk['updated']); ?>
                                        </span>
                                    </span>
                                    <span class="ecod_ticket__right">
                                        <em class="ecod_ticket__state" data-s="<?php echo htmlspecialchars($ecodDerived, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(ecod_ticket_state_text($ecodDerived), ENT_QUOTES, 'UTF-8'); ?></em>
                                        <span class="ecod_ticket__chev"><?php echo ecod_icon('chevron-down', 14); ?></span>
                                    </span>
                                </button>
                                <div class="ecod_ticket__thread" data-ecod-ticket-thread hidden></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="ecod_ticket__empty" data-ecod-ticket-empty<?php echo $ecodTicketRows ? ' hidden' : ''; ?>>
                        <?php echo ecod_icon('headset', 40); ?>
                        <b>还没有工单</b>
                        <p>遇到问题随时提，客服处理后会推送到消息中心。</p>
                        <button type="button" class="ecod-btn ecod-btn--primary" data-ecod-ticket-new>新建工单</button>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="ecod_uc__panel" data-ecod-uc-panel="lottery" hidden>
                <h3 class="ecod_uc__title">抽奖记录</h3>
                <?php if (!$ecodLogged): ?>
                    <div class="ecod_uc__empty">
                        <p>登录后可查看抽奖记录。</p>
                        <?php if ($ecodLoginUrl !== ''): ?>
                            <a class="ecod-btn ecod-btn--primary" href="<?php echo htmlspecialchars($ecodLoginUrl, ENT_QUOTES, 'UTF-8'); ?>">去登录</a>
                        <?php endif; ?>
                    </div>
                <?php elseif (!$ecodLotRows): ?>
                    <div class="ecod_uc__empty">
                        <?php echo ecod_icon('gift', 34); ?>
                        <p>还没有抽奖记录，先去试试手气。</p>
                        <?php if ($ecodLotteryPageUrl !== ''): ?>
                            <a class="ecod-btn ecod-btn--ghost" href="<?php echo htmlspecialchars($ecodLotteryPageUrl, ENT_QUOTES, 'UTF-8'); ?>">去抽奖</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="ecod_lrec" data-ecod-lrec>
                        <div class="ecod_lrec__tabs" role="tablist" aria-label="记录筛选">
                            <button type="button" class="is-active" data-lf="all">全部</button>
                            <button type="button" data-lf="win">中奖</button>
                            <button type="button" data-lf="exchange">兑换</button>
                        </div>
                        <div class="ecod_lrec__list">
                            <?php foreach ($ecodLotRows as $ecodLotRow):
                                $ecodLotType = (string) $ecodLotRow['type'];
                                $ecodLotKind = $ecodLotType === 'none' ? 'none' : ($ecodLotType === 'exchange' ? 'exchange' : 'win');
                                $ecodLotDelta = (int) $ecodLotRow['credits'];
                                $ecodLotIcon = $ecodLotKind === 'exchange' ? 'zap' : ($ecodLotKind === 'none' ? 'inbox' : 'medal');
                                $ecodLotBadge = $ecodLotKind === 'exchange' ? '兑换' : ($ecodLotKind === 'none' ? '谢谢参与' : '中奖');
                            ?>
                            <div class="ecod_lrec__item is-<?php echo $ecodLotKind; ?>" data-type="<?php echo $ecodLotKind; ?>">
                                <span class="ecod_lrec__ic"><?php echo ecod_icon($ecodLotIcon, 18); ?></span>
                                <div class="ecod_lrec__main">
                                    <b><?php echo htmlspecialchars($ecodLotRow['name'] !== '' ? $ecodLotRow['name'] : '谢谢参与', ENT_QUOTES, 'UTF-8'); ?></b>
                                    <span><?php echo date('Y-m-d H:i', (int) $ecodLotRow['created']); ?><?php echo $ecodLotDelta !== 0 ? ' · ' . ($ecodLotDelta > 0 ? '+' : '') . $ecodLotDelta . ' ' . htmlspecialchars(ecod_credits_name(), ENT_QUOTES, 'UTF-8') : ''; ?></span>
                                </div>
                                <em><?php echo $ecodLotBadge; ?></em>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="ecod_lrec__end">没有更多了</p>
                    </div>
                <?php endif; ?>
            </div>

        </section>
    </div>


    <?php /* 模块 · 内容区下方（模块管理台） */ ecod_widgets_render("bottom_content", $this); ?>
</main>

<?php $this->need('public/aside.php'); ?>
<?php $this->need('public/footer.php'); ?>
