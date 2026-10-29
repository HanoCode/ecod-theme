<?php
/**
 * 页脚块 · 移动端底部 dock
 *
 * 手机端底部快捷栏：首页 / 搜索 / 深浅色 / 回顶部。
 * 仅移动端显示（CSS 控制），启用后移动端右下角悬浮按钮由 dock 接管。
 *
 * @package Ecod Theme
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodDockHome = '';
try {
    $ecodDockHome = \Widget\Options::alloc()->siteUrl;
} catch (\Throwable $e) {
}
?>
<nav class="ecod_dock" data-ecod-dock aria-label="快捷操作栏">
    <a class="ecod_dock__btn" href="<?php echo $ecodDockHome; ?>">
        <?php echo ecod_icon('home', 19); ?>
        <span>首页</span>
    </a>
    <button type="button" class="ecod_dock__btn" data-ecod-drawer-open data-ecod-dock-search aria-label="搜索">
        <?php echo ecod_icon('search', 19); ?>
        <span>搜索</span>
    </button>
    <button type="button" class="ecod_dock__btn" data-ecod-theme-toggle aria-label="切换深色模式">
        <?php echo ecod_icon('moon', 19); ?>
        <span>深浅</span>
    </button>
    <button type="button" class="ecod_dock__btn" data-ecod-dock-top aria-label="回到顶部">
        <?php echo ecod_icon('arrow-up', 19); ?>
        <span>顶部</span>
    </button>
</nav>
