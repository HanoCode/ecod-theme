<?php
/**
 * 独立页面
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

    <?php /* 模块 · 内容区下方（模块管理台） */ ecod_widgets_render("bottom_content", $this); ?>
</main>

<?php $this->need('public/aside.php'); ?>
<?php $this->need('public/footer.php'); ?>
