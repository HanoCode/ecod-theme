<?php
/**
 * 侧栏块 · 名片卡（设计稿 §06.4：shell=card / density=loose / head=none）
 *
 * 顶部横幅 + 头像叠压融合 + 名称 + 简介 + 统计三格 + 底部主按钮。
 * 登录用户：头像 / 封面 / 昵称 / 签名 / 统计全部取个人信息，按钮为「个人中心」；
 * 游客：头像用站点 Logo、封面用 cover 参数，按钮为「立即登录」。
 * 横幅未配置时用主题色渐变占位。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

require_once __DIR__ . '/../../core/user/meta.php';

$ecodSiteShowStats = (int) ecod_p('stats', 1) === 1;

/* 登录态：当前用户 uid（0 = 游客） */
$ecodSiteUid = (int) (ecod_viewer_uid() ?? 0);
$ecodSiteCard = $ecodSiteUid > 0 ? ecod_user_card($ecodSiteUid) : null;

if ($ecodSiteCard) {
    /* 登录用户：头像 / 封面 / 昵称 / 签名 / 统计全部取个人信息 */
    $ecodSiteName = $ecodSiteCard['name'] !== '' ? $ecodSiteCard['name'] : (string) $this->options->title;
    $ecodSiteDesc = trim((string) $ecodSiteCard['intro']);
    if ($ecodSiteDesc === '') {
        $ecodSiteDesc = '加入于 ' . date('Y-m-d', (int) $ecodSiteCard['joined']);
    }
    $ecodSiteAvatar = trim((string) $ecodSiteCard['avatar']);
    if ($ecodSiteAvatar === '') {
        $ecodSiteAvatar = trim((string) ecod_option('ELogoImage', ''));
    }
    $ecodSiteCover = trim((string) ecod_account_cover_url($ecodSiteUid));
    $ecodSiteStats = [
        ['num' => (int) $ecodSiteCard['posts'], 'label' => '文章'],
        ['num' => (int) $ecodSiteCard['comments'], 'label' => '评论'],
        ['num' => max(0, (int) floor((time() - (int) $ecodSiteCard['joined']) / 86400)), 'label' => '天'],
    ];
    $ecodSiteCtaHref = ecod_user_center_url();
    $ecodSiteCtaText = '个人中心';

    /* 用户名后的等级徽章：站长显「博主」，其余按全站评论数分档（与评论区同源） */
    $ecodSiteBadge = '';
    if ((int) $ecodSiteCard['uid'] === (int) ecod_site_author_id()) {
        $ecodSiteBadge = '<em class="ecod_cmt__badge is-owner" title="站长">博主</em>';
    } else {
        $ecodSiteLv = ecod_comment_level((string) $ecodSiteCard['mail']);
        if ($ecodSiteLv['level'] > 0) {
            $ecodSiteBadge = '<em class="ecod_cmt__badge ' . $ecodSiteLv['tone'] . '" title="已发表 '
                . (int) $ecodSiteLv['count'] . ' 条评论">'
                . htmlspecialchars($ecodSiteLv['label'], ENT_QUOTES, 'UTF-8') . '</em>';
        }
    }
} else {
    /* 游客：站点品牌资产（Logo + cover 参数）+ 站点统计 */
    $ecodSiteName = (string) $this->options->title;
    $ecodSiteDesc = (string) ecod_p('desc', '') ?: (string) $this->options->description;
    $ecodSiteAvatar = trim((string) ecod_option('ELogoImage', ''));
    $ecodSiteCover = trim((string) ecod_p('cover', ''));
    $ecodSiteStats = [
        ['num' => 0, 'label' => '文章'],
        ['num' => 0, 'label' => '评论'],
        ['num' => 0, 'label' => '分类'],
    ];
    if ($ecodSiteShowStats) {
        try {
            $ecodDb = \Typecho\Db::get();
            $ecodSiteStats[0]['num'] = (int) $ecodDb->fetchObject(
                $ecodDb->select('COUNT(*) AS num')->from('table.contents')
                    ->where('type = ?', 'post')->where('status = ?', 'publish')
            )->num;
            $ecodSiteStats[1]['num'] = (int) $ecodDb->fetchObject(
                $ecodDb->select('COUNT(*) AS num')->from('table.comments')
                    ->where('status = ?', 'approved')
            )->num;
            $ecodSiteStats[2]['num'] = (int) $ecodDb->fetchObject(
                $ecodDb->select('COUNT(*) AS num')->from('table.metas')->where('type = ?', 'category')
            )->num;
        } catch (\Throwable $e) {
            // 统计失败时静默降级
        }
    }
    $ecodSiteCtaHref = ecod_login_url();
    if ($ecodSiteCtaHref === '') {
        $ecodSiteCtaHref = ecod_user_center_url();
    }
    $ecodSiteCtaText = '立即登录';
    $ecodSiteBadge = '';
}

/* 封面为空走 CSS 渐变占位；头像为空用名称首字 */
$ecodSiteCover = trim((string) $ecodSiteCover);
?>
<section class="ecod_widget ecod_sitecard">
    <div class="ecod_sitecard__cover<?php echo $ecodSiteCover === '' ? ' is-ph' : ''; ?>">
        <?php if ($ecodSiteCover !== ''): ?>
            <img src="<?php echo htmlspecialchars($ecodSiteCover, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="560" height="140" loading="lazy" decoding="async">
        <?php endif; ?>
    </div>
    <div class="ecod_sitecard__id">
        <span class="ecod_sitecard__avatar" aria-hidden="true">
            <?php if ($ecodSiteAvatar !== ''): ?>
                <img src="<?php echo htmlspecialchars($ecodSiteAvatar, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="56" height="56" loading="lazy" decoding="async">
            <?php else: ?>
                <i><?php echo htmlspecialchars(mb_substr($ecodSiteName, 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></i>
            <?php endif; ?>
        </span>
        <div class="ecod_sitecard__main">
            <div class="ecod_sitecard__name"><?php echo htmlspecialchars($ecodSiteName, ENT_QUOTES, 'UTF-8'); ?><?php echo $ecodSiteBadge; ?></div>
            <div class="ecod_sitecard__desc"><?php echo htmlspecialchars($ecodSiteDesc !== '' ? $ecodSiteDesc : '随心而写，遇记而安', ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
    </div>
    <?php if ($ecodSiteShowStats): ?>
        <div class="ecod_sitecard__stats">
            <?php foreach ($ecodSiteStats as $ecodSiteStat): ?>
                <span><b><?php echo (int) $ecodSiteStat['num']; ?></b><em><?php echo htmlspecialchars($ecodSiteStat['label'], ENT_QUOTES, 'UTF-8'); ?></em></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($ecodSiteCtaHref !== ''): ?>
        <a class="ecod_sitecard__cta" href="<?php echo htmlspecialchars($ecodSiteCtaHref, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo ecod_icon($ecodSiteCard ? 'chevron' : 'user', 14); ?><?php echo htmlspecialchars($ecodSiteCtaText, ENT_QUOTES, 'UTF-8'); ?>
        </a>
    <?php endif; ?>
</section>
