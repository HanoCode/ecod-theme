<?php
/**
 * 站点头部：导航 + 抽屉 + 布局开始
 *
 * @package Ecod Theme
 * @author  Ecod
 * @since   2026-10-29
 * @version 2.0.0
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/* 安全响应头：须在任何输出之前发送
 * - nosniff 防 MIME 嗅探；X-Frame-Options 防点击劫持（本站自身 iframe 不受影响）
 * - Referrer-Policy 只带源不出全 URL；Permissions-Policy 收敛敏感能力
 * （HSTS / CSP 属服务器层配置，见 README「服务器加固」一节） */
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
}

/* 外链中转拦截：命中 /go/<token> 直接输出加载页并终止（须在输出前） */
ecod_go_intercept();

/* PJAX 局部渲染：主题自身的无刷新跳转只回传 shell 段，跳过整页 chrome
   （head 资源 / 导航 / 抽屉 / 页脚块）；高亮地址随 shell 的
   data-ecod-nav-active 输出，JS 换页后据此同步导航高亮 */
/* 内容页标记：post / 独立页模板需要加载 ecod.post.js 内容增强包。
   shell 上的 data-ecod-bundle 同时服务两条链路——页脚据此决定输出
   <script> 标签，PJAX 换页后 JS 据此动态注入增强包。 */
$ecodContentPage = $this->is('post') || $this->is('page');
$ecodBundleAttr = $ecodContentPage ? ' data-ecod-bundle="post"' : '';

if (ecod_is_pjax()) {
    $this->widget('\Widget\Contents\Page\Rows')->to($ecodPjaxPages);
    $ecodNavActive = ecod_nav_active($this, ecod_nav_items($ecodPjaxPages));
    echo '<!DOCTYPE html><html><head><meta charset="'
        . htmlspecialchars((string) $this->options->charset, ENT_QUOTES, 'UTF-8') . '">'
        . '<title>' . htmlspecialchars(ecod_seo_title($this), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</title></head><body>'
        .
        '<div class="ecod_container" id="ecod-shell"'
        . ($ecodNavActive !== '' ? ' data-ecod-nav-active="' . htmlspecialchars($ecodNavActive, ENT_QUOTES, 'UTF-8') . '"' : '')
        . $ecodBundleAttr
        . '><div class="ecod_layout" data-aside="'
        . htmlspecialchars(ecod_aside_pos($this), ENT_QUOTES, 'UTF-8') . '">';
    return;
}

$ecodAside = ecod_aside_pos($this);
$ecodLogo = ecod_option('ELogo', $this->options->title);
$ecodLogoImage = trim((string) ecod_option('ELogoImage', ''));
/* 站内 logo 按 28px 显示高换算宽度占位；取不到尺寸（SVG/站外）退回只给高度 */
$ecodLogoDims = $ecodLogoImage !== '' ? ecod_img_attrs($ecodLogoImage, 28) : '';
if ($ecodLogoDims === '') {
    $ecodLogoDims = ' height="28"';
}
$ecodLogoBoth = ecod_option('ELogoBoth', 'off') === 'on';
$ecodDark = ecod_option('EDark', 'auto');
$ecodAnim = (string) ecod_option('EAnimate', 'none');
$ecodBackdrop = (string) ecod_option('EBackdrop', 'none');
$ecodCursor = (string) ecod_option('ECursor', 'off');

/* 用户区：登录态头像 / 游客登录入口 + 个人中心地址 */
$ecodUcUrl = ecod_user_center_url();
$ecodUserWidget = null;
$ecodUserLogged = false;
$ecodUserAvatar = '';
$ecodUserScreen = '';
try {
    $ecodUserWidget = \Widget\User::alloc();
    $ecodUserLogged = (bool) $ecodUserWidget->hasLogin();
    if ($ecodUserLogged) {
        $ecodUserAvatar = ecod_user_avatar($ecodUserWidget, 80);
        $ecodUserScreen = (string) $ecodUserWidget->screenName;
    }
} catch (\Throwable $e) {
}
$ecodLoginUrl = ecod_login_url();
$ecodRegisterUrl = rtrim((string) $this->options->adminUrl, '/') . '/register.php';
$ecodHere = (string) (\Typecho\Request::getInstance()->getRequestUrl());

/* 导航：独立页面 + 后台自定义链接，超出上限的收进「更多」 */
$this->widget('\Widget\Contents\Page\Rows')->to($ecodPages);
$ecodNav = ecod_nav_items($ecodPages);
/* 当前页对应的导航高亮地址，输出在 shell 上供 PJAX 换页后同步 */
$ecodNavActive = ecod_nav_active($this, $ecodNav);
$ecodNavMax = (int) ecod_option('ENavMax', 0);
$ecodNavHead = $ecodNav;
$ecodNavMore = [];
if ($ecodNavMax > 0 && count($ecodNav) > $ecodNavMax) {
    $ecodCut = max(1, $ecodNavMax - 1);
    $ecodNavHead = array_slice($ecodNav, 0, $ecodCut);
    $ecodNavMore = array_slice($ecodNav, $ecodCut);
}
?>
<!DOCTYPE html>
<html lang="zh-CN" class="ecod-booting" data-theme="light" data-dark="<?php echo $ecodDark; ?>"
      data-anim="<?php echo htmlspecialchars($ecodAnim, ENT_QUOTES, 'UTF-8'); ?>"
      data-backdrop="<?php echo htmlspecialchars($ecodBackdrop, ENT_QUOTES, 'UTF-8'); ?>"
      data-cursor="<?php echo htmlspecialchars($ecodCursor, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <?php $this->need('public/include.php'); ?>
</head>
<body>
<a class="ecod_skip" href="#ecod-main">跳到正文</a>

<header class="ecod_header" data-ecod-header>
    <div class="ecod_container">
        <nav class="ecod_nav" aria-label="主导航">
            <a class="ecod_nav__logo" href="<?php $this->options->siteUrl(); ?>">
                <?php if ($ecodLogoImage !== ''): ?>
                    <img src="<?php echo htmlspecialchars($ecodLogoImage, ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars((string) $this->options->title, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $ecodLogoDims; ?>>
                    <?php if ($ecodLogoBoth): ?>
                        <span><?php echo htmlspecialchars($ecodLogo); ?></span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="ecod_nav__logo-mark" aria-hidden="true"><?php echo htmlspecialchars(mb_substr($ecodLogo !== '' ? $ecodLogo : (string) $this->options->title, 0, 1), ENT_QUOTES, 'UTF-8'); ?></span>
                    <span><?php echo htmlspecialchars($ecodLogo); ?></span>
                <?php endif; ?>
            </a>

            <ul class="ecod_nav__links">
                <?php if (!ecod_nav_has_menu()): ?>
                    <li class="<?php echo $this->is('index') ? 'is-active' : ''; ?>">
                        <a href="<?php $this->options->siteUrl(); ?>"><span class="ecod_nav__licon"><?php echo ecod_icon('home', 15); ?></span>首页</a>
                    </li>
                <?php endif; ?>
                <?php foreach ($ecodNavHead as $ecodItem): ?>
                    <?php
                    $ecodKids = isset($ecodItem['children']) ? $ecodItem['children'] : [];
                    $ecodOn = ecod_nav_item_is_active($this, $ecodItem);
                    if (!$ecodOn) {
                        foreach ($ecodKids as $ecodKid) {
                            if (ecod_nav_item_is_active($this, $ecodKid)) {
                                $ecodOn = true;
                                break;
                            }
                        }
                    }
                    /* 二级超过 6 个自动分两列（设计稿 §03.5） */
                    $ecodCols = count($ecodKids) > 6 ? ' is-cols' : '';
                    /* 一级项可选前置图标与角标（§03.5 icon / badge 字段） */
                    $ecodIconHtml = isset($ecodItem['icon']) && $ecodItem['icon'] !== ''
                        ? '<span class="ecod_nav__licon">' . ecod_icon((string) $ecodItem['icon'], 15) . '</span>'
                        : '';
                    $ecodBadgeHtml = isset($ecodItem['badge']) && $ecodItem['badge'] !== ''
                        ? '<i class="ecod_nav__badge">' . htmlspecialchars((string) $ecodItem['badge'], ENT_QUOTES, 'UTF-8') . '</i>'
                        : '';
                    ?>
                    <li class="<?php echo $ecodOn ? 'is-active' : ''; ?><?php echo $ecodKids ? ' has-sub' : ''; ?>">
                        <a href="<?php echo htmlspecialchars($ecodItem['url'], ENT_QUOTES, 'UTF-8'); ?>"
                            <?php echo ecod_nav_link_attrs($ecodItem); ?>><?php echo $ecodIconHtml; ?><span class="ecod_nav__ltext"><?php echo htmlspecialchars($ecodItem['title'], ENT_QUOTES, 'UTF-8'); ?></span><?php echo $ecodBadgeHtml; ?><?php echo $ecodKids ? '<i class="ecod_nav__caret" aria-hidden="true">' . ecod_icon('chevron', 12) . '</i>' : ''; ?></a>
                        <?php if ($ecodKids): ?>
                            <?php
                            /* 任一子项配了图标 → 面板切图标卡片网格（§03.5） */
                            $ecodKidIcons = false;
                            foreach ($ecodKids as $ecodKid) {
                                if (isset($ecodKid['icon']) && $ecodKid['icon'] !== '') {
                                    $ecodKidIcons = true;
                                    break;
                                }
                            }
                            ?>
                            <ul class="ecod_nav__sub<?php echo $ecodCols; ?><?php echo $ecodKidIcons ? ' has-icons' : ''; ?>">
                                <?php foreach ($ecodKids as $ecodKid): ?>
                                    <li class="<?php echo ecod_nav_item_is_active($this, $ecodKid) ? 'is-active' : ''; ?>">
                                        <a href="<?php echo htmlspecialchars($ecodKid['url'], ENT_QUOTES, 'UTF-8'); ?>"
                                            <?php echo ecod_nav_link_attrs($ecodKid); ?>><?php echo isset($ecodKid['icon']) && $ecodKid['icon'] !== '' ? '<span class="ecod_nav__licon">' . ecod_icon((string) $ecodKid['icon'], 15) . '</span>' : ''; ?><?php echo htmlspecialchars($ecodKid['title'], ENT_QUOTES, 'UTF-8'); ?></a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
                <?php if ($ecodNavMore): ?>
                    <li class="ecod_nav__more">
                        <details>
                            <summary>更多<?php echo ecod_icon('chevron', 12); ?></summary>
                            <div class="ecod_nav__drop">
                                <?php foreach ($ecodNavMore as $ecodItem): ?>
                                    <a href="<?php echo htmlspecialchars($ecodItem['url'], ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo ecod_nav_link_attrs($ecodItem); ?>><?php echo htmlspecialchars($ecodItem['title'], ENT_QUOTES, 'UTF-8'); ?></a>
                                    <?php if (!empty($ecodItem['children'])): ?>
                                        <div class="ecod_nav__dropkids">
                                            <?php foreach ($ecodItem['children'] as $ecodKid): ?>
                                                <a href="<?php echo htmlspecialchars($ecodKid['url'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    <?php echo ecod_nav_link_attrs($ecodKid); ?>><?php echo htmlspecialchars($ecodKid['title'], ENT_QUOTES, 'UTF-8'); ?></a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    </li>
                <?php endif; ?>
            </ul>

            <?php $ecodSPanelOn = ecod_option('ESearchPanel', 'on') === 'on'; ?>
            <div class="ecod_nav__actions">
                <i class="ecod_nav__sep" aria-hidden="true"></i>
                <?php if ($ecodSPanelOn): ?>
                    <button type="button" class="ecod_nav__searchbtn" data-ecod-spanel-open aria-label="搜索"
                            title="搜索（⌘K）"><?php echo ecod_icon('search', 17); ?></button>
                <?php else: ?>
                    <form class="ecod_nav__search" role="search" method="post" action="<?php $this->options->siteUrl(); ?>">
                        <?php echo ecod_icon('search', 14); ?>
                        <input type="search" name="s" placeholder="搜索文章" aria-label="搜索文章" autocomplete="off" data-ecod-search-input>
                        <kbd>↵</kbd>
                    </form>
                <?php endif; ?>
                <button type="button" class="ecod_nav__themebtn" data-ecod-theme-toggle
                        aria-label="切换深浅模式" title="深浅模式"><?php echo ecod_icon('moon', 17); ?></button>

                <?php if ($ecodUserLogged): ?>
                    <details class="ecod_nav__user" data-ecod-user data-ecod-logged="1">
                    <summary aria-label="用户中心">
                        <span class="ecod_nav__user-ic"><?php echo ecod_icon('user', 16); ?></span>
                        <img src="<?php echo htmlspecialchars($ecodUserAvatar, ENT_QUOTES, 'UTF-8'); ?>"
                             alt="<?php echo htmlspecialchars($ecodUserScreen, ENT_QUOTES, 'UTF-8'); ?>" width="30" height="30" referrerpolicy="no-referrer"
                             onerror="this.remove()">
                    </summary>
                    <div class="ecod_nav__upop">
                        <div class="ecod_nav__upop-head">
                            <img src="<?php echo htmlspecialchars($ecodUserAvatar, ENT_QUOTES, 'UTF-8'); ?>"
                                 alt="" width="40" height="40" referrerpolicy="no-referrer">
                            <div>
                                <b><?php echo htmlspecialchars($ecodUserScreen, ENT_QUOTES, 'UTF-8'); ?></b>
                                <span>欢迎回来</span>
                            </div>
                        </div>
                        <?php if ($ecodUcUrl !== ''): ?>
                            <a href="<?php echo htmlspecialchars($ecodUcUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo ecod_icon('user', 14); ?>个人中心</a>
                        <?php endif; ?>
                        <a href="<?php $this->options->adminUrl(); ?>" target="_blank" rel="noopener"><?php echo ecod_icon('edit', 14); ?>管理后台</a>
                        <a href="<?php $this->options->logoutUrl(); ?>"><?php echo ecod_icon('logout', 14); ?>退出登录</a>
                    </div>
                </details>
            <?php else: ?>
                <details class="ecod_nav__user" data-ecod-user>
                    <summary aria-label="登录 / 注册">
                        <?php echo ecod_icon('user', 16); ?>
                    </summary>
                    <div class="ecod_nav__upop">
                        <div class="ecod_nav__upop-head">
                            <span class="ecod_nav__upop-guest" aria-hidden="true"><?php echo ecod_icon('user', 18); ?></span>
                            <div>
                                <b>Hi，游客</b>
                                <span>登录后同步你的足迹</span>
                            </div>
                        </div>
                        <a data-ecod-login-open data-no-pjax href="<?php echo htmlspecialchars($ecodLoginUrl, ENT_QUOTES, 'UTF-8'); ?>?referer=<?php echo rawurlencode($ecodHere); ?>"><?php echo ecod_icon('logout', 14); ?>登录</a>
                        <?php if ((int) $this->options->allowRegister === 1): ?>
                            <a href="<?php echo htmlspecialchars($ecodRegisterUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo ecod_icon('plus', 14); ?>注册</a>
                        <?php endif; ?>
                        <?php if ($ecodUcUrl !== ''): ?>
                            <a href="<?php echo htmlspecialchars($ecodUcUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo ecod_icon('star', 14); ?>我的收藏与足迹</a>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endif; ?>
            </div><!-- /.ecod_nav__actions -->

            <button type="button" class="ecod_nav__burger" data-ecod-drawer-open aria-label="打开菜单" aria-expanded="false"><?php echo ecod_icon('menu', 18); ?></button>
        </nav>
    </div>
</header>

<?php ecod_block_render('notice', $this); ?>

<!-- 移动端抽屉 -->
<div class="ecod_drawer" data-ecod-drawer aria-hidden="true">
    <div class="ecod_drawer__mask" data-ecod-drawer-close></div>
    <div class="ecod_drawer__panel" role="dialog" aria-modal="true" aria-label="站点菜单">
        <div class="ecod_drawer__head">
            <strong><?php echo htmlspecialchars($ecodLogo); ?></strong>
            <button type="button" class="ecod_drawer__close" data-ecod-drawer-close aria-label="关闭菜单"><?php echo ecod_icon('x', 18); ?></button>
        </div>

        <form class="ecod_drawer__search" role="search" method="post" action="<?php $this->options->siteUrl(); ?>">
            <input type="search" name="s" placeholder="搜索文章…" aria-label="搜索文章">
        </form>

        <p class="ecod_drawer__label">导航</p>
        <nav class="ecod_drawer__nav" aria-label="站点导航">
            <?php if (!ecod_nav_has_menu()): ?>
                <a href="<?php $this->options->siteUrl(); ?>">首页</a>
            <?php endif; ?>
            <?php foreach ($ecodNav as $ecodItem): ?>
                <?php $ecodKids = isset($ecodItem['children']) ? $ecodItem['children'] : []; ?>
                <?php if ($ecodKids): ?>
                    <details class="ecod_drawer__group">
                        <summary><?php echo htmlspecialchars($ecodItem['title'], ENT_QUOTES, 'UTF-8'); ?><?php echo ecod_icon('chevron', 12); ?></summary>
                        <div class="ecod_drawer__sub">
                            <a href="<?php echo htmlspecialchars($ecodItem['url'], ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo ecod_nav_link_attrs($ecodItem); ?>><?php echo htmlspecialchars($ecodItem['title'], ENT_QUOTES, 'UTF-8'); ?>（全部）</a>
                            <?php foreach ($ecodKids as $ecodKid): ?>
                                <a href="<?php echo htmlspecialchars($ecodKid['url'], ENT_QUOTES, 'UTF-8'); ?>"
                                    <?php echo ecod_nav_link_attrs($ecodKid); ?>><?php echo htmlspecialchars($ecodKid['title'], ENT_QUOTES, 'UTF-8'); ?></a>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php else: ?>
                    <a href="<?php echo htmlspecialchars($ecodItem['url'], ENT_QUOTES, 'UTF-8'); ?>"
                        <?php echo ecod_nav_link_attrs($ecodItem); ?>><?php echo htmlspecialchars($ecodItem['title'], ENT_QUOTES, 'UTF-8'); ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($ecodUserLogged): ?>
                <?php if ($ecodUcUrl !== ''): ?>
                    <a href="<?php echo htmlspecialchars($ecodUcUrl, ENT_QUOTES, 'UTF-8'); ?>">个人中心</a>
                <?php endif; ?>
                <a href="<?php $this->options->logoutUrl(); ?>">退出登录</a>
            <?php else: ?>
                <a data-ecod-login-open data-no-pjax href="<?php echo htmlspecialchars($ecodLoginUrl, ENT_QUOTES, 'UTF-8'); ?>?referer=<?php echo rawurlencode($ecodHere); ?>">登录</a>
                <?php if ($ecodUcUrl !== ''): ?>
                    <a href="<?php echo htmlspecialchars($ecodUcUrl, ENT_QUOTES, 'UTF-8'); ?>">我的收藏与足迹</a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>

        <p class="ecod_drawer__label">分类</p>
        <div class="ecod_drawer__cats">
            <?php $this->widget('\Widget\Metas\Category\Rows')->to($ecodCats); ?>
            <?php while ($ecodCats->next()): ?>
                <a class="ecod_tag" href="<?php $ecodCats->permalink(); ?>"><?php $ecodCats->name(); ?></a>
            <?php endwhile; ?>
        </div>

        <p class="ecod_drawer__label">我的收藏</p>
        <div class="ecod_favs" data-ecod-favs></div>
    </div>
</div>

<?php if (!$ecodUserLogged): ?>
<!-- 登录弹窗：任意页点「登录」直接弹出；注册 / 找回密码仍走完整登录页 -->
<div class="ecod_authmodal" data-ecod-authmodal hidden>
    <div class="ecod_authmodal__mask" data-ecod-authmodal-close></div>
    <div class="ecod_authmodal__panel" role="dialog" aria-modal="true" aria-label="登录">
        <button type="button" class="ecod_authmodal__close" data-ecod-authmodal-close aria-label="关闭登录弹窗"><?php echo ecod_icon('x', 18); ?></button>
        <form class="ecod_login__form ecod_authmodal__form" method="post" action="#"
              data-ecod-login
              data-ecod-home="<?php echo htmlspecialchars($ecodHere, ENT_QUOTES, 'UTF-8'); ?>">
            <span class="ecod_login__logo" aria-hidden="true"><?php echo ecod_icon('user', 22); ?></span>
            <h2 class="ecod_authmodal__title">登录本站账号</h2>
            <p class="ecod_login__desc">登录后同步你的收藏、点赞与浏览历史。</p>
            <input type="hidden" name="referer" value="<?php echo htmlspecialchars($ecodHere, ENT_QUOTES, 'UTF-8'); ?>">
            <label class="ecod_login__field">
                <span>用户名或邮箱</span>
                <input type="text" name="name" required autocomplete="username" placeholder="用户名或邮箱">
            </label>
            <label class="ecod_login__field">
                <span>密码</span>
                <input type="password" name="password" required autocomplete="current-password" placeholder="密码">
            </label>
            <label class="ecod_login__field">
                <span>验证码</span>
                <span class="ecod_login__cap">
                    <input type="text" name="captcha" required autocomplete="off" spellcheck="false"
                           inputmode="latin" maxlength="4" placeholder="4 位字符">
                    <img class="ecod_login__capimg" data-ecod-captcha
                         src="<?php echo htmlspecialchars(ecod_api_url(), ENT_QUOTES, 'UTF-8'); ?>?action=captcha&amp;t=<?php echo time(); ?>"
                         alt="验证码，点击刷新" width="96" height="40">
                </span>
            </label>
            <label class="ecod_login__remember">
                <input type="checkbox" name="remember" value="1">
                <span>30 天内自动登录</span>
            </label>
            <p class="ecod_login__msg" data-ecod-login-msg hidden></p>
            <button type="submit" class="ecod-btn ecod_login__submit">登 录</button>
            <p class="ecod_login__more"><a data-no-pjax href="<?php echo htmlspecialchars($ecodLoginUrl, ENT_QUOTES, 'UTF-8'); ?>?referer=<?php echo rawurlencode($ecodHere); ?>">注册账号 / 找回密码</a></p>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (ecod_option('ESearchPanel', 'on') === 'on'):
/* C4 居中搜索面板：导航搜索框 / dock 搜索按钮 / ⌘K 三入口唤起。
   热词来自设置项 ESearchHot（前端 CFG.hotwords），
   标签按 ESearchTagCount 截取，序列化给前端渲染空态。 */
$ecodSpanelTagLimit = max(0, min(12, (int) ecod_option('ESearchTagCount', 10)));
$ecodSpanelTags = [];
if ($ecodSpanelTagLimit > 0):
    try {
        $ecodSpanelTagWidget = $this->widget('Widget_Metas_Tag_Cloud@spanel', 'sort=count&ignoreZeroCount=1&desc=1');
        while ($ecodSpanelTagWidget->next() && count($ecodSpanelTags) < $ecodSpanelTagLimit) {
            $ecodSpanelTags[] = [
                'name' => (string) $ecodSpanelTagWidget->name,
                'url'  => (string) $ecodSpanelTagWidget->permalink,
            ];
        }
    } catch (\Throwable $e) {
        $ecodSpanelTags = [];
    }
endif;
?>
<div class="ecod_spanel" data-ecod-spanel aria-hidden="true"
     data-tags="<?php echo htmlspecialchars(json_encode($ecodSpanelTags, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="ecod_spanel__mask" data-ecod-spanel-close></div>
    <div class="ecod_spanel__box" role="dialog" aria-modal="true" aria-label="搜索">
        <div class="ecod_spanel__bar">
            <?php echo ecod_icon('search', 17); ?>
            <input type="search" class="ecod_spanel__input" data-ecod-spanel-input
                   placeholder="搜索文章…" aria-label="搜索文章" autocomplete="off">
            <kbd class="ecod_spanel__esc" aria-hidden="true">Esc</kbd>
        </div>
        <div class="ecod_spanel__body" data-ecod-spanel-body></div>
        <footer class="ecod_spanel__foot">
            <span class="ecod_spanel__keys"><kbd>↑</kbd><kbd>↓</kbd> 选择 <kbd>↵</kbd> 打开</span>
        </footer>
    </div>
</div>
<?php endif; ?>
<div class="ecod_container" id="ecod-shell"<?php echo $ecodNavActive !== '' ? ' data-ecod-nav-active="' . htmlspecialchars($ecodNavActive, ENT_QUOTES, 'UTF-8') . '"' : ''; ?><?php echo $ecodBundleAttr; ?>>
    <?php ecod_widgets_zone('top_fluid', $this); ?>
    <div class="ecod_layout" data-aside="<?php echo $ecodAside; ?>">

