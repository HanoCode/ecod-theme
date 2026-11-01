<?php
/**
 * 首页
 *
 * 全部内容由模块系统输出（含顶部大图 banner 模块）；
 * 顺序、开关与参数在「页面构建器 / 模块管理台」里摆放。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @since   2026-10-29
 * @version 2.1.0
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$this->need('public/header.php');
?>
<main class="ecod_main ecod_home" id="ecod-main">

    <?php if (!ecod_layout_render_page($this)): ?>
        <?php /* 页面构建器未接管：走模块管理台的 v4 区域 */ ecod_widgets_render('top_content', $this); ?>
    <?php endif; ?>

</main>

<?php $this->need('public/aside.php'); ?>
<?php $this->need('public/footer.php'); ?>
