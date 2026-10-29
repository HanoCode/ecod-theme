<?php
/**
 * 404
 *
 * @package Ecod Theme
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$this->need('public/header.php');
?>
<main class="ecod_main" id="ecod-main">
    <div class="ecod_panel ecod_empty" style="padding:72px 24px">
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
</main>

<?php $this->need('public/aside.php'); ?>
<?php $this->need('public/footer.php'); ?>
