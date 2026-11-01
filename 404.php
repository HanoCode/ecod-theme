<?php
/**
 * 404
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
$this->need('public/header.php');
?>
<main class="ecod_main" id="ecod-main">
    <?php /* 模块 · 内容区上方（模块管理台） */ ecod_widgets_render("top_content", $this); ?>
    <div class="ecod_panel ecod_empty" style="padding:72px 24px">
        <div class="ecod_ball404" data-ecod-ball404 aria-hidden="true"></div>
        <p class="ecod_ball404__tip" aria-hidden="true">它一直在看着你 · 点它一下试试</p>
        <?php /* AI 彩蛋（F14）：与快问面板同一开关（EBallAsk），关掉时 404 保持原样 */ ?>
        <?php if (function_exists('ecod_ai_ask_enabled') && ecod_ai_ask_enabled()): ?>
            <button type="button" class="ecod_btn ecod_btn--ghost ecod_404ai"
                    data-ecod-aiask-open="site"
                    data-ecod-aiask-prompt="我访问的页面走丢了，站内有哪些值得去的地方？按你看到的站点概览推荐 2~3 个，并给链接。">
                <?php echo ecod_icon('bulb', 13); ?> 我该去哪？
            </button>
        <?php endif; ?>
        <div style="font-size:56px;font-weight:800;letter-spacing:4px;color:var(--ecod-border-strong);line-height:1.1">404</div>
        <h3 style="font-size:16px;margin:8px 0 4px">页面走丢了</h3>
        <p style="margin-bottom:20px">页面可能已被删除，或者地址有误</p>
        <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;align-items:center">
            <a class="ecod_btn" href="<?php $this->options->siteUrl(); ?>">返回首页</a>
            <form method="post" action="<?php $this->options->siteUrl(); ?>" style="margin:0">
                <input type="search" name="s" placeholder="搜索文章…" aria-label="搜索文章"
                    style="height:36px;padding:0 14px;border:1px solid var(--ecod-border);border-radius:var(--ecod-radius-sm);background:var(--ecod-surface-2);color:var(--ecod-text);font-family:inherit;font-size:13px;outline:none">
                <button type="submit" class="ecod_btn ecod_btn--ghost" style="margin-left:8px">搜索一下</button>
            </form>
        </div>
    </div>

    <?php /* 模块 · 内容区下方（模块管理台） */ ecod_widgets_render("bottom_content", $this); ?>
</main>

<?php $this->need('public/aside.php'); ?>
<?php $this->need('public/footer.php'); ?>
