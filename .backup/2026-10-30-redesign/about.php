<?php
/**
 * 侧栏块 · 站点名片卡（设计稿 §06.4：shell=card / density=loose / head=none）
 *
 * 头像 + 站名 + 签名 + 统计三格 + RSS 订阅按钮。
 * 去掉「关于本站」小标题头，整卡即名片。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodSiteTitle = $this->options->title;
$ecodSiteDesc = (string) ecod_p('desc', '') ?: $this->options->description;
$ecodShowStats = (int) ecod_p('stats', 1) === 1;
$ecodSiteLogo = trim((string) ecod_option('ELogoImage', ''));

$ecodStats = ['posts' => 0, 'comments' => 0, 'cats' => 0];
if ($ecodShowStats) {
    try {
        $ecodDb = \Typecho\Db::get();
        $ecodStats['posts'] = (int) $ecodDb->fetchObject(
            $ecodDb->select('COUNT(*) AS num')->from('table.contents')
                ->where('type = ?', 'post')->where('status = ?', 'publish')
        )->num;
        $ecodStats['comments'] = (int) $ecodDb->fetchObject(
            $ecodDb->select('COUNT(*) AS num')->from('table.comments')
                ->where('status = ?', 'approved')
        )->num;
        $ecodStats['cats'] = (int) $ecodDb->fetchObject(
            $ecodDb->select('COUNT(*) AS num')->from('table.metas')->where('type = ?', 'category')
        )->num;
    } catch (\Throwable $e) {
        // 统计失败时静默降级
    }
}
?>
<section class="ecod_widget ecod_sitecard">
    <div class="ecod_sitecard__id">
        <span class="ecod_sitecard__avatar" aria-hidden="true">
            <?php if ($ecodSiteLogo !== ''): ?>
                <img src="<?php echo htmlspecialchars($ecodSiteLogo, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="52" height="52" loading="lazy" decoding="async">
            <?php else: ?>
                <i><?php echo mb_substr($ecodSiteTitle, 0, 1, 'UTF-8'); ?></i>
            <?php endif; ?>
        </span>
        <div class="ecod_sitecard__main">
            <div class="ecod_sitecard__name"><?php echo htmlspecialchars($ecodSiteTitle); ?></div>
            <div class="ecod_sitecard__desc"><?php echo htmlspecialchars($ecodSiteDesc ?: '随心而写，遇记而安'); ?></div>
        </div>
    </div>
    <?php if ($ecodShowStats): ?>
        <div class="ecod_sitecard__stats">
            <span><b><?php echo $ecodStats['posts']; ?></b><em>文章</em></span>
            <span><b><?php echo $ecodStats['comments']; ?></b><em>评论</em></span>
            <span><b><?php echo $ecodStats['cats']; ?></b><em>分类</em></span>
        </div>
    <?php endif; ?>
    <?php if ($this->options->feedUrl): ?>
        <a class="ecod_sitecard__rss" href="<?php $this->options->feedUrl(); ?>" target="_blank" rel="noopener">
            <?php echo ecod_icon('feed', 14); ?>RSS 订阅
        </a>
    <?php endif; ?>
</section>
