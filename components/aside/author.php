<?php
/**
 * 侧栏块 · 作者卡片
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
            <?php if ($ecodAuGreet): ?>
                <div class="ecod_authorcard__greet"><?php echo $ecodAuHello; ?>，很高兴见到你</div>
            <?php endif; ?>
            <div class="ecod_authorcard__name">
                <?php echo htmlspecialchars($ecodAuName, ENT_QUOTES, 'UTF-8'); ?>
                <?php if ($ecodAuBadge): ?><b class="ecod_authorcard__badge">博主</b><?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($ecodAuDesc !== ''): ?>
        <p class="ecod_authorcard__desc"><?php echo htmlspecialchars($ecodAuDesc, ENT_QUOTES, 'UTF-8'); ?></p>
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

    <?php if ($ecodAuStats): ?>
        <div class="ecod_authorcard__stats">
            <span><b><?php echo (int) $ecodAuOv['posts']; ?></b>文章</span>
            <span><b><?php echo (int) $ecodAuOv['comments']; ?></b>评论</span>
            <span><b><?php echo (int) $ecodAuOv['days']; ?></b>天</span>
        </div>
    <?php endif; ?>
</section>
