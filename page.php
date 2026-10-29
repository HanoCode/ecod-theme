<?php
/**
 * 独立页面
 *
 * @package Ecod Theme
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$this->need('public/header.php');
?>
<main class="ecod_main" id="ecod-main">
    <nav class="ecod_crumb" aria-label="面包屑">
        <a href="<?php $this->options->siteUrl(); ?>">首页</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page"><?php $this->title(); ?></span>
    </nav>

    <article class="ecod_article" data-ecod-article>
        <h1 class="ecod_article__title"><?php $this->title(); ?></h1>
        <div class="ecod_content" data-ecod-content>
            <?php
            ob_start();
            $this->content();
            echo ecod_go_rewrite(ecod_lazy_images(ecod_oembed_apply(ecod_shortcode_apply((string) ob_get_clean(), $this)), (string) $this->title));
            ?>
        </div>
    </article>

    <?php if ($this->allow('comment')): ?>
        <?php $this->need('comments.php'); ?>
    <?php endif; ?>
</main>

<?php $this->need('public/aside.php'); ?>
<?php $this->need('public/footer.php'); ?>
