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

/* 访问统计打点：整页与 PJAX 都算一次 PV（PJAX 换页用户确实看到了新页面）。
   这里只把这次访问攒进内存，落库在 shutdown 且 fastcgi_finish_request 之后，
   不占前台响应时间；开关见后台「互动 → 点赞与数据 → 访问统计」。 */
ecod_stats_hit($this);

/* PJAX 局部渲染：主题自身的无刷新跳转只回传 shell 段，跳过整页 chrome
   （head 资源 / 导航 / 抽屉 / 页脚块）；高亮地址随 shell 的
   data-ecod-nav-active 输出，JS 换页后据此同步导航高亮 */
/* 内容页标记：post / 独立页模板需要加载 ecod.post.js 内容增强包。
   shell 上的 data-ecod-bundle 同时服务两条链路——页脚据此决定输出
   <script> 标签，PJAX 换页后 JS 据此动态注入增强包。 */
$ecodContentPage = $this->is('post') || $this->is('page');
$ecodBundleAttr = $ecodContentPage ? ' data-ecod-bundle="post"' : '';
/* 页面族标记：PJAX 换页后 ensurePageCss() 据此注入缺失的族 CSS（S3） */
$ecodFamAttr = ' data-ecod-fams="' . htmlspecialchars(implode(',', ecod_css_family($this)), ENT_QUOTES, 'UTF-8') . '"';

if (ecod_is_pjax()) {
    $this->widget('\Widget\Contents\Page\Rows')->to($ecodPjaxPages);
    $ecodNavActive = ecod_nav_active($this, ecod_nav_items($ecodPjaxPages));
    /* 顶部全宽区与全量渲染对齐（shell 开标签内、.ecod_layout 之前）：
       footer.php 的 PJAX 分支配对补渲染 bottom_fluid，这里同样不能漏，
       否则「所有页面 · 顶部全宽」挂件只在整页刷新时出现 */
    ob_start();
    ecod_widgets_zone('top_fluid', $this);
    $ecodTopZone = ob_get_clean();
    echo '<!DOCTYPE html><html><head><meta charset="'
        . htmlspecialchars((string) $this->options->charset, ENT_QUOTES, 'UTF-8') . '">'
        . '<title>' . htmlspecialchars(ecod_seo_title($this), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</title></head><body>'
        .
        '<div class="ecod_container" id="ecod-shell"'
        . ($ecodNavActive !== '' ? ' data-ecod-nav-active="' . htmlspecialchars($ecodNavActive, ENT_QUOTES, 'UTF-8') . '"' : '')
        . $ecodBundleAttr
        . $ecodFamAttr
        . '>' . $ecodTopZone
        . '<div class="ecod_layout" data-aside="'
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
$ecodAnim = (string) ecod_option('EAnimate', 'blur');
/* 页面加载效果（PJAX 换页等待时的过渡），值须与 ecod.css §16 的 data-loadfx 变体一致 */
$ecodLoadFx = (string) ecod_option('ELoadFx', 'bar');
if (!in_array($ecodLoadFx, ['bar', 'ring', 'logo', 'veil', 'none'], true)) {
    $ecodLoadFx = 'bar';
}
/* 卡片动效总开关（设计稿 guaqi-reference §F5）：off 时磁贴/聚合卡等不加 hover 动效与渐入 */
$ecodMotion = (string) ecod_option('ECardMotion', 'on') === 'on' ? 'on' : 'off';
$ecodBackdrop = (string) ecod_option('EBackdrop', 'none');
$ecodCursor = (string) ecod_option('ECursor', 'off');

/* 自定义背景图：没配图时静默退回纯色，避免整站变成空白块 */
$ecodBgImage = '';
$ecodBgMask = 55;
if ($ecodBackdrop === 'image') {
    $ecodBgImage = trim((string) ecod_option('EBgImage', ''));
    $ecodBgMask = max(0, min(90, (int) ecod_option('EBgMask', 55)));
    if ($ecodBgImage === '') {
        $ecodBackdrop = 'none';
    }
}
$ecodBgStyle = '';
if ($ecodBgImage !== '') {
    $ecodBgStyle = "--ecod-bg-img:url('" . str_replace(["'", '"'], '', $ecodBgImage) . "');"
        . '--ecod-bg-mask:' . number_format($ecodBgMask / 100, 2, '.', '') . ';';
}

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
/* 当前导航套的展示样式（auto/list/icons/mega/mega-group），菜单为空时回退全局 ENavStyle。
   一级项可带 sub_style 项级覆盖（空 = 跟随套），在循环内逐项计算生效样式 */
$ecodNavStyle = ecod_nav_style();
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

/* 浮动件坐标标记：窄屏下浮动目录据此让开 dock（见 ecod.css §11.4b） */
$ecodBodyClass = [];
if (ecod_block_on('dock')) {
    $ecodBodyClass[] = 'ecod-has-dock';
}
?>
<!DOCTYPE html>
<html lang="zh-CN" class="ecod-booting" data-theme="light" data-dark="<?php echo $ecodDark; ?>"
      data-anim="<?php echo htmlspecialchars($ecodAnim, ENT_QUOTES, 'UTF-8'); ?>"
      data-loadfx="<?php echo $ecodLoadFx; ?>"
      data-backdrop="<?php echo htmlspecialchars($ecodBackdrop, ENT_QUOTES, 'UTF-8'); ?>"
      data-cursor="<?php echo htmlspecialchars($ecodCursor, ENT_QUOTES, 'UTF-8'); ?>"
      data-motion="<?php echo $ecodMotion; ?>"
      <?php if ($ecodBgStyle !== ''): ?>style="<?php echo htmlspecialchars($ecodBgStyle, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>>
<head>
    <?php $this->need('public/include.php'); ?>
</head>
<body class="<?php echo implode(' ', $ecodBodyClass); ?>">
<?php ecod_license_admin_bar(); ?>
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
                    /* 生效样式：项级 sub_style 覆盖套级样式（§03.5 项级显示模式） */
                    $ecodStyle = (string) ($ecodItem['sub_style'] ?? '');
                    if ($ecodStyle === '') {
                        $ecodStyle = $ecodNavStyle;
                    }
                    /* 二级超过 6 个自动分两列；mega 样式强制两列（设计稿 §03.5） */
                    $ecodForceCols = ($ecodStyle === 'mega');
                    $ecodCols = ($ecodForceCols || count($ecodKids) > 6) ? ' is-cols' : '';
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
                            /* mega-group 分组形态：任一子项配了 group 或 desc 即启用 */
                            $ecodHasGroup = $ecodHasDesc = false;
                            if ($ecodStyle === 'mega-group') {
                                foreach ($ecodKids as $ecodKid) {
                                    if (($ecodKid['group'] ?? '') !== '') { $ecodHasGroup = true; }
                                    if (($ecodKid['desc'] ?? '') !== '') { $ecodHasDesc = true; }
                                }
                            }
                            if ($ecodStyle === 'mega-group' && ($ecodHasGroup || $ecodHasDesc)):
                                /* 按 group 名归组，保持首次出现顺序；无组名的归入末尾空组（不渲染栏标题） */
                                $ecodGroups = [];
                                foreach ($ecodKids as $ecodKid) {
                                    $ecodGroups[(string) ($ecodKid['group'] ?? '')][] = $ecodKid;
                                }
                                if (isset($ecodGroups['']) && count($ecodGroups) > 1) {
                                    $ecodEmptyG = $ecodGroups[''];
                                    unset($ecodGroups['']);
                                    $ecodGroups[''] = $ecodEmptyG;
                                }
                                $ecodGCols = min(max(count($ecodGroups), 1), 4); ?>
                                <ul class="ecod_nav__sub is-mega-full has-icons" style="--ecod-mega-cols:<?php echo $ecodGCols; ?>">
                                    <?php foreach ($ecodGroups as $ecodGName => $ecodGKids): ?>
                                        <li class="ecod_nav__group">
                                            <?php if ($ecodGName !== ''): ?>
                                                <p class="ecod_nav__group-t"><?php echo htmlspecialchars($ecodGName, ENT_QUOTES, 'UTF-8'); ?></p>
                                            <?php endif; ?>
                                            <ul class="ecod_nav__group-list">
                                                <?php foreach ($ecodGKids as $ecodKid): ?>
                                                    <li class="<?php echo ecod_nav_item_is_active($this, $ecodKid) ? 'is-active' : ''; ?>">
                                                        <a href="<?php echo htmlspecialchars($ecodKid['url'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            <?php echo ecod_nav_link_attrs($ecodKid); ?>>
                                                            <span class="ecod_nav__licon<?php echo ($ecodKid['icon'] ?? '') === '' ? ' ecod_nav__licon--text' : ''; ?>"><?php echo ($ecodKid['icon'] ?? '') !== '' ? ecod_icon((string) $ecodKid['icon'], 16) : htmlspecialchars(mb_substr((string) $ecodKid['title'], 0, 1), ENT_QUOTES, 'UTF-8'); ?></span>
                                                            <span class="ecod_nav__item-body">
                                                                <span class="ecod_nav__item-t"><?php echo htmlspecialchars($ecodKid['title'], ENT_QUOTES, 'UTF-8'); ?><?php if (($ecodKid['badge'] ?? '') !== ''): ?><i class="ecod_nav__badge ecod_nav__badge--item"><?php echo htmlspecialchars((string) $ecodKid['badge'], ENT_QUOTES, 'UTF-8'); ?></i><?php endif; ?></span>
                                                                <?php if (($ecodKid['desc'] ?? '') !== ''): ?>
                                                                    <span class="ecod_nav__item-d"><?php echo htmlspecialchars((string) $ecodKid['desc'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                                <?php endif; ?>
                                                            </span>
                                                        </a>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php elseif ($ecodStyle === 'mega-group'):
                                /* mega-group 退化形态：全宽多列图标行（3~5 列自适应） */
                                $ecodRowsCount = count($ecodKids);
                                $ecodRowsCols = $ecodRowsCount <= 5 ? $ecodRowsCount : min(5, (int) ceil($ecodRowsCount / 2)); ?>
                                <ul class="ecod_nav__sub is-mega-full is-rows has-icons" style="--ecod-mega-cols:<?php echo max(1, $ecodRowsCols); ?>">
                                    <?php foreach ($ecodKids as $ecodKid): ?>
                                        <li class="<?php echo ecod_nav_item_is_active($this, $ecodKid) ? 'is-active' : ''; ?>">
                                            <a href="<?php echo htmlspecialchars($ecodKid['url'], ENT_QUOTES, 'UTF-8'); ?>"
                                                <?php echo ecod_nav_link_attrs($ecodKid); ?>>
                                                <span class="ecod_nav__licon<?php echo ($ecodKid['icon'] ?? '') === '' ? ' ecod_nav__licon--text' : ''; ?>"><?php echo ($ecodKid['icon'] ?? '') !== '' ? ecod_icon((string) $ecodKid['icon'], 16) : htmlspecialchars(mb_substr((string) $ecodKid['title'], 0, 1), ENT_QUOTES, 'UTF-8'); ?></span>
                                                <span class="ecod_nav__ltext"><?php echo htmlspecialchars($ecodKid['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php if (($ecodKid['badge'] ?? '') !== ''): ?><i class="ecod_nav__badge ecod_nav__badge--item"><?php echo htmlspecialchars((string) $ecodKid['badge'], ENT_QUOTES, 'UTF-8'); ?></i><?php endif; ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else:
                            /* 根据生效样式决定是否启用图标卡片面板 */
                            if ($ecodStyle === 'list') {
                                $ecodKidIcons = false;
                            } elseif ($ecodStyle === 'icons' || $ecodStyle === 'mega') {
                                $ecodKidIcons = true;
                            } else {
                                /* auto：任一子项配了图标 → 图标卡片网格 */
                                $ecodKidIcons = false;
                                foreach ($ecodKids as $ecodKid) {
                                    if (isset($ecodKid['icon']) && $ecodKid['icon'] !== '') {
                                        $ecodKidIcons = true;
                                        break;
                                    }
                                }
                            }
                            $ecodForceIcons = ($ecodStyle === 'icons' || $ecodStyle === 'mega');
                            ?>
                            <ul class="ecod_nav__sub<?php echo $ecodCols; ?><?php echo $ecodKidIcons ? ' has-icons' : ''; ?>">
                                <?php foreach ($ecodKids as $ecodKid): ?>
                                    <li class="<?php echo ecod_nav_item_is_active($this, $ecodKid) ? 'is-active' : ''; ?>">
                                        <a href="<?php echo htmlspecialchars($ecodKid['url'], ENT_QUOTES, 'UTF-8'); ?>"
                                            <?php echo ecod_nav_link_attrs($ecodKid); ?>><?php
                                            $ecodKidIconHtml = '';
                                            if (isset($ecodKid['icon']) && $ecodKid['icon'] !== '') {
                                                $ecodKidIconHtml = '<span class="ecod_nav__licon">' . ecod_icon((string) $ecodKid['icon'], 15) . '</span>';
                                            } elseif ($ecodForceIcons) {
                                                $ecodKidIconHtml = '<span class="ecod_nav__licon ecod_nav__licon--text">' . htmlspecialchars(mb_substr((string) $ecodKid['title'], 0, 1), ENT_QUOTES, 'UTF-8') . '</span>';
                                            }
                                            echo $ecodKidIconHtml;
                                            ?><?php echo htmlspecialchars($ecodKid['title'], ENT_QUOTES, 'UTF-8'); ?></a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; /* mega-group 分支收口 */ ?>
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
                <?php if ($ecodSPanelOn || $ecodDark !== 'off'): ?>
                <div class="ecod_nav__tools">
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
                    <?php if ($ecodSPanelOn && $ecodDark !== 'off'): ?>
                        <i class="ecod_nav__sep" aria-hidden="true"></i>
                    <?php endif; ?>
                    <?php if ($ecodDark !== 'off'): /* 后台关掉深色模式后，头部的切换按钮一并隐藏（与页脚 / dock 保持一致） */ ?>
                        <button type="button" class="ecod_nav__themebtn" data-ecod-theme-toggle
                                aria-label="切换深浅模式" title="深浅模式"><?php echo ecod_icon('moon', 17); ?></button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($ecodUserLogged): ?>
                    <?php /* 站内消息铃铛：角标由 JS 拉 notif_unread 回填 */ ?>
                    <details class="ecod_nav__bell" data-ecod-bell>
                        <summary aria-label="站内消息" title="站内消息">
                            <?php echo ecod_icon('bell', 17); ?>
                            <i class="ecod_nav__bell-badge" data-ecod-bell-badge hidden></i>
                        </summary>
                        <div class="ecod_nav__bellpop" role="dialog" aria-label="站内消息列表">
                            <div class="ecod_nav__bellpop-head">
                                <b>站内消息</b>
                                <div class="ecod_nav__bellpop-ops">
                                    <button type="button" class="ecod_nav__bellpop-op" data-ecod-notif-readall>全部已读</button>
                                    <button type="button" class="ecod_nav__bellpop-op" data-ecod-notif-clear>清空</button>
                                </div>
                            </div>
                            <div class="ecod_nav__bellpop-list" data-ecod-notif-list aria-live="polite"></div>
                            <?php if ($ecodUcUrl !== ''): ?>
                                <a class="ecod_nav__bellpop-foot" href="<?php echo htmlspecialchars($ecodUcUrl, ENT_QUOTES, 'UTF-8'); ?>#notifs">在个人中心查看全部 →</a>
                            <?php endif; ?>
                        </div>
                    </details>
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
                        <?php if (ecod_credits_enabled()):
                            /* 积分余额 + 每日签到直达（guaqi 式）：服务端直出，签到点击走 api */
                            $ecodHdSigned = ecod_credits_signed_today((int) $ecodUserWidget->uid); ?>
                        <div class="ecod_nav__upop-credit" data-ecod-credits-quick
                             data-api="<?php echo htmlspecialchars(ecod_api_url(), ENT_QUOTES, 'UTF-8'); ?>"
                             data-token="<?php echo htmlspecialchars(ecod_account_token(), ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="ecod_nav__upop-credit-label"><?php echo ecod_icon('zap', 13); ?><?php echo htmlspecialchars(ecod_credits_name(), ENT_QUOTES, 'UTF-8'); ?></span>
                            <b data-ecod-credits-quick-balance><?php echo ecod_credits_balance((int) $ecodUserWidget->uid); ?></b>
                            <button type="button" class="ecod_nav__upop-signin" data-ecod-credits-quick-signin
                                    data-signed="<?php echo $ecodHdSigned ? '1' : '0'; ?>">
                                <?php echo $ecodHdSigned ? '已签到' : '签到 +' . max(0, (int) ecod_option('ECrCheckin', 0)); ?>
                            </button>
                        </div>
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
                <input class="ecod_input" type="text" name="name" required autocomplete="username">
            </label>
            <label class="ecod_login__field">
                <span>密码</span>
                <input class="ecod_input" type="password" name="password" required autocomplete="current-password">
            </label>
            <label class="ecod_login__field">
                <span>验证码</span>
                <span class="ecod_login__cap">
                    <input class="ecod_input" type="text" name="captcha" required autocomplete="off" spellcheck="false"
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
   标签按 ESearchTagCount 截取，推荐文章按 ESearchPosts 维度取前 N 篇，
   两者序列化给前端渲染空态。 */
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

$ecodSpanelPostOrder = (string) ecod_option('ESearchPosts', 'off');
$ecodSpanelPostLabels = [
    'hot'      => '热门文章',
    'likes'    => '最多点赞',
    'comments' => '最多评论',
    'views'    => '最多阅读',
];
$ecodSpanelPosts = [];
$ecodSpanelPostLabel = '';
if (isset($ecodSpanelPostLabels[$ecodSpanelPostOrder])) {
    $ecodSpanelPostCount = max(1, min(12, (int) ecod_option('ESearchPostsCount', 8)));
    $ecodSpanelPosts = ecod_search_featured($ecodSpanelPostCount, $ecodSpanelPostOrder);
    $ecodSpanelPostLabel = $ecodSpanelPostLabels[$ecodSpanelPostOrder];
}
?>
<div class="ecod_spanel" data-ecod-spanel aria-hidden="true"
     data-tags="<?php echo htmlspecialchars(json_encode($ecodSpanelTags, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
     data-posts="<?php echo htmlspecialchars(json_encode($ecodSpanelPosts, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
     data-posts-label="<?php echo htmlspecialchars($ecodSpanelPostLabel, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="ecod_spanel__mask" data-ecod-spanel-close></div>
    <div class="ecod_spanel__box" role="dialog" aria-modal="true" aria-label="搜索">
        <div class="ecod_spanel__bar">
            <?php echo ecod_icon('search', 17); ?>
            <input type="search" class="ecod_spanel__input" data-ecod-spanel-input
                   placeholder="搜索文章…" aria-label="搜索文章" autocomplete="off">
            <span class="ecod_spanel__ball" data-ecod-spanel-ball aria-hidden="true"></span>
            <kbd class="ecod_spanel__esc" aria-hidden="true">Esc</kbd>
        </div>
        <div class="ecod_spanel__body" data-ecod-spanel-body></div>
        <footer class="ecod_spanel__foot">
            <span class="ecod_spanel__keys"><kbd>↑</kbd><kbd>↓</kbd> 选择 <kbd>↵</kbd> 打开</span>
        </footer>
    </div>
</div>
<?php endif; ?>
<?php /* AI 快问面板：放在 #ecod-shell 之外，PJAX 换页不会被替换（与搜索面板同一约定） */ ?>
<?php if (ecod_ai_ask_enabled()): ?>
<div class="ecod_aiask" data-ecod-aiask hidden>
    <div class="ecod_aiask__panel" role="dialog" aria-modal="false" aria-label="AI 快问" data-ecod-aiask-panel>
        <header class="ecod_aiask__head">
            <span class="ecod_aiask__ava" data-ecod-aiask-ball aria-hidden="true"></span>
            <b class="ecod_aiask__title" data-ecod-aiask-title>AI 快问</b>
            <span class="ecod_aiask__scope" data-ecod-aiask-scope hidden></span>
            <button type="button" class="ecod_aiask__bal" data-ecod-aiask-bal hidden></button>
            <button type="button" class="ecod_aiask__x" data-ecod-aiask-close aria-label="关闭">
                <?php echo ecod_icon('x', 14); ?>
            </button>
        </header>
        <div class="ecod_aiask__ctx" data-ecod-aiask-ctx hidden>
            <span data-ecod-aiask-ctxt></span>
            <button type="button" data-ecod-aiask-unpin aria-label="不再针对这篇文章"><?php echo ecod_icon('x', 12); ?></button>
        </div>
        <div class="ecod_aiask__list" data-ecod-aiask-list aria-live="polite">
            <div class="ecod_aiask__welcome" data-ecod-aiask-welcome>
                <p>你好，我是本站的 AI 助手，关于文章内容或建站问题都可以问我。</p>
                <div class="ecod_aiask__chips" data-ecod-aiask-chips>
                    <?php foreach (ecod_ai_chips() as $ecodChip): ?>
                        <button type="button" data-ecod-aiask-chip="<?php echo htmlspecialchars($ecodChip, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ecodChip, ENT_QUOTES, 'UTF-8'); ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php /* 看广告领点数（F12）：登录 + 后台开启时由前端点亮，默认整块隐藏 */ ?>
        <div class="ecod_aiask__earn" data-ecod-aiask-earn hidden>
            <div class="ecod_aiask__earnrow">
                <span><?php echo ecod_icon('play', 11); ?> 积分不够？看一段广告 +<b data-ecod-aiask-adamt>1</b> 点</span>
                <button type="button" data-ecod-aiask-adopen>去看看</button>
            </div>
            <div class="ecod_aiask__ad" data-ecod-aiask-ad hidden>
                <a class="ecod_aiask__adcard" data-ecod-aiask-adlink href="<?php $this->options->siteUrl(); ?>"
                   target="_blank" rel="noopener nofollow sponsored">
                    <span class="ecod_aiask__adcover" data-ecod-aiask-adcover hidden></span>
                    <span class="ecod_aiask__adbody">
                        <b data-ecod-aiask-adtitle>本站推荐</b>
                        <small data-ecod-aiask-adnote>赞助内容</small>
                    </span>
                </a>
                <div class="ecod_aiask__adfoot">
                    <button type="button" class="ecod_aiask__adbtn" data-ecod-aiask-adclaim disabled>广告播放中</button>
                    <button type="button" class="ecod_aiask__adx" data-ecod-aiask-adclose aria-label="收起广告">收起</button>
                </div>
            </div>
        </div>
        <form class="ecod_aiask__form" data-ecod-aiask-form>
            <textarea class="ecod_aiask__ta" data-ecod-aiask-ta rows="1" maxlength="2000"
                      placeholder="问点什么…" aria-label="输入问题"></textarea>
            <button type="submit" class="ecod_aiask__send" data-ecod-aiask-send aria-label="发送">
                <span data-ecod-aiask-sendicon><?php echo ecod_icon('arrow-up', 15); ?></span>
            </button>
        </form>
        <footer class="ecod_aiask__foot">
            <span data-ecod-aiask-tip>回答由 AI 生成，仅供参考</span>
            <a href="<?php echo htmlspecialchars(ecod_template_page_url('page-ai.php'), ENT_QUOTES, 'UTF-8'); ?>"
               data-ecod-aiask-more>完整对话<?php echo ecod_icon('arrow-up-right', 11); ?></a>
        </footer>
    </div>
</div>
<?php endif; ?>
<?php /* 内置浏览器引导：微信 / QQ 等 WebView 中提示改用浏览器。
   同样放在 #ecod-shell 之外，PJAX 换页不重建（非内置 UA 时组件不产出标记） */ ?>
<?php $this->need('components/header/inapp.php'); ?>
<div class="ecod_container" id="ecod-shell"<?php echo $ecodNavActive !== '' ? ' data-ecod-nav-active="' . htmlspecialchars($ecodNavActive, ENT_QUOTES, 'UTF-8') . '"' : ''; ?><?php echo $ecodBundleAttr . $ecodFamAttr; ?>>
    <?php ecod_widgets_zone('top_fluid', $this); ?>
    <div class="ecod_layout" data-aside="<?php echo $ecodAside; ?>">

