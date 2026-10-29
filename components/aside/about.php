<?php
/**
 * 侧栏块 · 关于本站
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 * @license   MIT
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodSiteTitle = $this->options->title;
$ecodSiteDesc = (string) ecod_p('desc', '') ?: $this->options->description;
$ecodShowStats = (int) ecod_p('stats', 1) === 1;

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
<section class="ecod_widget">
    <h2 class="ecod_widget__title"><?php echo htmlspecialchars((string) ecod_p('title', '关于本站')); ?></h2>
    <div class="ecod_author">
        <span class="ecod_author__avatar" aria-hidden="true"><?php echo mb_substr($ecodSiteTitle, 0, 1, 'UTF-8'); ?></span>
        <div>
            <div class="ecod_author__name"><?php echo htmlspecialchars($ecodSiteTitle); ?></div>
            <div class="ecod_author__desc"><?php echo htmlspecialchars($ecodSiteDesc ?: '随心而写，遇记而安'); ?></div>
        </div>
    </div>
    <?php if ($ecodShowStats): ?>
        <div class="ecod_author__data">
            <span><b><?php echo $ecodStats['posts']; ?></b>文章</span>
            <span><b><?php echo $ecodStats['comments']; ?></b>评论</span>
            <span><b><?php echo $ecodStats['cats']; ?></b>分类</span>
        </div>
    <?php endif; ?>
</section>
