<?php
/**
 * 侧栏块 · 作者卡片
 *
 * 版式参考小红书式用户卡：时段问候大标题 → 一句话简介 → 横幅图 →
 * 快捷入口磁贴 → 数据一览 → 社交链接 → 「个人中心」主按钮。
 * 横幅与快捷入口是可选参数，留空即不显示；游客的 CTA 自动变「登录 / 注册」。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodAuAvatar = trim((string) ecod_p('avatar', ''));
$ecodAuName = trim((string) ecod_p('name', ''));
$ecodAuName = $ecodAuName !== '' ? $ecodAuName : (string) $this->options->title;
$ecodAuDesc = trim((string) ecod_p('desc', ''));
$ecodAuDesc = $ecodAuDesc !== '' ? $ecodAuDesc : (string) $this->options->description;
$ecodAuGreet = (int) ecod_p('greet', 1) === 1;
$ecodAuStats = (int) ecod_p('stats', 1) === 1;
$ecodAuBanner = trim((string) ecod_p('banner', ''));

/* CTA：登录用户去个人中心；游客去登录页。两处地址都为空则不渲染按钮 */
$ecodAuCtaOn = (int) ecod_p('cta', 1) === 1;
$ecodAuCtaText = trim((string) ecod_p('ctatext', '个人中心'));
$ecodAuCtaText = $ecodAuCtaText !== '' ? $ecodAuCtaText : '个人中心';

/* 时段问候 */
$ecodAuHour = (int) date('G');
if ($ecodAuHour < 5) {
    $ecodAuHello = '夜深了';
} elseif ($ecodAuHour < 11) {
    $ecodAuHello = '早上好';
} elseif ($ecodAuHour < 14) {
    $ecodAuHello = '中午好';
} elseif ($ecodAuHour < 18) {
    $ecodAuHello = '下午好';
} else {
    $ecodAuHello = '晚上好';
}

/* 社交链接：repeater JSON（§31 第 10 轮） */
$ecodAuLinks = [];
$ecodAuLinksRaw = json_decode((string) ecod_p('links', ''), true);
if (is_array($ecodAuLinksRaw)) {
    foreach ($ecodAuLinksRaw as $ecodAuRow) {
        $ecodAuLabel = trim((string) ($ecodAuRow['label'] ?? ''));
        $ecodAuHref = trim((string) ($ecodAuRow['href'] ?? ''));
        if ($ecodAuLabel !== '' && $ecodAuHref !== '') {
            $ecodAuLinks[] = ['label' => $ecodAuLabel, 'href' => $ecodAuHref];
        }
    }
}

/* 快捷入口磁贴：repeater JSON，最多 4 个，图标填 ecod 图标名（留空用 link） */
$ecodAuApps = [];
$ecodAuAppsRaw = json_decode((string) ecod_p('apps', ''), true);
if (is_array($ecodAuAppsRaw)) {
    foreach ($ecodAuAppsRaw as $ecodAuRow) {
        $ecodAuLabel = trim((string) ($ecodAuRow['label'] ?? ''));
        $ecodAuHref = trim((string) ($ecodAuRow['href'] ?? ''));
        if ($ecodAuLabel === '' || $ecodAuHref === '') {
            continue;
        }
        $ecodAuIcon = trim((string) ($ecodAuRow['icon'] ?? ''));
        $ecodAuApps[] = [
            'label' => $ecodAuLabel,
            'href' => $ecodAuHref,
            'icon' => in_array($ecodAuIcon, ecod_icon_names(), true) ? $ecodAuIcon : 'link',
        ];
        if (count($ecodAuApps) >= 4) {
            break;
        }
    }
}

$ecodAuOv = $ecodAuStats ? ecod_site_overview() : [];

/* D7 在线点 + 博主徽章：块开关 × 全站开关；uid 留空自动取管理员组最早的账号 */
$ecodAuOnlineOn = (int) ecod_p('online', 1) === 1 && ecod_option('EAuthorOnline', 'on') === 'on';
$ecodAuOnline = false;
$ecodAuBadge = false;
if ($ecodAuOnlineOn) {
    $ecodAuUid = (int) trim((string) ecod_p('uid', ''));
    if ($ecodAuUid <= 0) {
        $ecodAuUid = ecod_site_author_id();
    }
    if ($ecodAuUid > 0) {
        $ecodAuOnline = ecod_user_online($ecodAuUid);
        $ecodAuBadge = true;
    }
}

/* 登录态：CTA 按谁点谁跳，登录用户跳个人中心，游客跳登录页 */
$ecodAuLogged = false;
try {
    $ecodAuLogged = (bool) \Widget\User::alloc()->hasLogin();
} catch (\Throwable $e) {
}
if ($ecodAuLogged) {
    $ecodAuCtaHref = ecod_user_center_url();
} else {
    $ecodAuCtaHref = ecod_login_url();
    if ($ecodAuCtaHref === '') {
        $ecodAuCtaHref = ecod_user_center_url();
    }
}
if (!$ecodAuLogged && $ecodAuCtaOn && $ecodAuCtaHref !== '') {
    $ecodAuCtaText = '登录 / 注册';
}
?>
<section class="ecod_widget ecod_authorcard">
    <div class="ecod_authorcard__head">
        <span class="ecod_authorcard__avatar">
            <?php if ($ecodAuAvatar !== ''): ?>
                <img src="<?php echo htmlspecialchars($ecodAuAvatar, ENT_QUOTES, 'UTF-8'); ?>"
                     alt="<?php echo htmlspecialchars($ecodAuName, ENT_QUOTES, 'UTF-8'); ?>"
                     width="64" height="64" loading="lazy" decoding="async">
            <?php else: ?>
                <i aria-hidden="true"><?php echo htmlspecialchars(mb_substr($ecodAuName, 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></i>
            <?php endif; ?>
            <?php if ($ecodAuOnline): ?><i class="ecod_authorcard__dot" aria-hidden="true" title="在线"></i><?php endif; ?>
        </span>
        <div class="ecod_authorcard__id">
            <div class="ecod_authorcard__name">
                <?php echo htmlspecialchars($ecodAuName, ENT_QUOTES, 'UTF-8'); ?>
                <?php if ($ecodAuBadge): ?><b class="ecod_authorcard__badge">博主</b><?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($ecodAuGreet): ?>
        <div class="ecod_authorcard__hi">嗨，<?php echo $ecodAuHello; ?>！</div>
    <?php endif; ?>

    <?php if ($ecodAuDesc !== ''): ?>
        <p class="ecod_authorcard__desc"><?php echo htmlspecialchars($ecodAuDesc, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <?php if ($ecodAuBanner !== ''): ?>
        <figure class="ecod_authorcard__banner">
            <img src="<?php echo htmlspecialchars($ecodAuBanner, ENT_QUOTES, 'UTF-8'); ?>"
                 alt="" width="560" height="280" loading="lazy" decoding="async">
        </figure>
    <?php endif; ?>

    <?php if ($ecodAuApps): ?>
        <nav class="ecod_authorcard__apps" aria-label="快捷入口">
            <?php foreach ($ecodAuApps as $ecodAuApp): ?>
                <a href="<?php echo htmlspecialchars($ecodAuApp['href'], ENT_QUOTES, 'UTF-8'); ?>">
                    <i aria-hidden="true"><?php echo ecod_icon($ecodAuApp['icon'], 18); ?></i>
                    <span><?php echo htmlspecialchars($ecodAuApp['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <?php if ($ecodAuStats): ?>
        <div class="ecod_authorcard__stats">
            <span>
                <b><i aria-hidden="true"><?php echo ecod_icon('hash', 14); ?></i><?php echo ecod_num_short((int) $ecodAuOv['posts']); ?></b>
                <small>文章</small>
            </span>
            <span>
                <b><i aria-hidden="true"><?php echo ecod_icon('comment', 14); ?></i><?php echo ecod_num_short((int) $ecodAuOv['comments']); ?></b>
                <small>评论</small>
            </span>
            <span>
                <b><i aria-hidden="true"><?php echo ecod_icon('calendar', 14); ?></i><?php echo ecod_num_short((int) $ecodAuOv['days']); ?></b>
                <small>天</small>
            </span>
        </div>
    <?php endif; ?>

    <?php if ($ecodAuLinks): ?>
        <div class="ecod_authorcard__links">
            <?php foreach ($ecodAuLinks as $ecodAuLink): ?>
                <a href="<?php echo htmlspecialchars($ecodAuLink['href'], ENT_QUOTES, 'UTF-8'); ?>"
                   target="_blank" rel="noopener nofollow">
                    <?php echo ecod_icon('link', 12); ?><?php echo htmlspecialchars($ecodAuLink['label'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($ecodAuCtaOn && $ecodAuCtaHref !== ''): ?>
        <a class="ecod_authorcard__cta" href="<?php echo htmlspecialchars($ecodAuCtaHref, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars($ecodAuCtaText, ENT_QUOTES, 'UTF-8'); ?>
            <?php echo ecod_icon('chevron', 14); ?>
        </a>
    <?php endif; ?>
</section>
