<?php
/**
 * 留言板
 *
 * @package custom
 * @author  Ecod
 * @since   2026-10-29
 * @version 2.0.0
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$this->need('public/header.php');

$ecodLeaveNum = (int) $this->commentsNum;
$ecodLeaveMeta = '<span>' . ecod_icon('comment', 13) . $ecodLeaveNum . ' 条留言</span>'
    . '<span>' . ecod_icon('calendar', 13) . '更新于 ' . date('Y-m-d', (int) $this->created) . '</span>';
?>
<main class="ecod_main" id="ecod-main">
    <?php /* 模块 · 内容区上方（模块管理台） */ ecod_widgets_render("top_content", $this); ?>

    <nav class="ecod_crumb" aria-label="面包屑">
        <a href="<?php $this->options->siteUrl(); ?>"><?php echo ecod_icon('home', 12); ?>首页</a>
        <span class="ecod_crumb__sep" aria-hidden="true"><?php echo ecod_icon('chevron', 11); ?></span>
        <span aria-current="page"><?php $this->title(); ?></span>
    </nav>

    <?php ecod_page_hero('留言板', (string) $this->title, '想说什么都可以留在这里，看到会回。', $ecodLeaveMeta); ?>

    <div class="ecod_leave__tips">
        <div class="ecod_tipcard">
            <span class="ecod_tipcard__icon"><?php echo ecod_icon('smile', 16); ?></span>
            <strong>友善交流</strong>
            <p>观点可以不同，语气请留余地。</p>
        </div>
        <div class="ecod_tipcard">
            <span class="ecod_tipcard__icon"><?php echo ecod_icon('mail', 16); ?></span>
            <strong>邮箱不公开</strong>
            <p>填写邮箱仅用于接收回复提醒。</p>
        </div>
        <div class="ecod_tipcard">
            <span class="ecod_tipcard__icon"><?php echo ecod_icon('zap', 16); ?></span>
            <strong>可能会慢</strong>
            <p>留言人工审核，回复不一定及时。</p>
        </div>
    </div>

    <?php if (trim(strip_tags((string) $this->content)) !== ''): ?>
        <article class="ecod_article ecod_leave__intro">
            <div class="ecod_content" data-ecod-content>
                <?php $this->content(); ?>
            </div>
        </article>
    <?php endif; ?>

    <?php if ($this->allow('comment')): ?>
        <?php $this->need('comments.php'); ?>
    <?php endif; ?>


    <?php /* 模块 · 内容区下方（模块管理台） */ ecod_widgets_render("bottom_content", $this); ?>
</main>

<?php $this->need('public/aside.php'); ?>
<?php $this->need('public/footer.php'); ?>
