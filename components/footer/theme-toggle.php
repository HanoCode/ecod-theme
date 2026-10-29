<?php
/**
 * 页脚块 · 深浅色切换
 *
 * @package Ecod Theme
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

if (ecod_option('EDark', 'auto') === 'off') {
    return;
}
?>
<button type="button" class="ecod_theme-toggle" data-ecod-theme-toggle aria-label="切换深色模式"><?php echo ecod_icon('moon', 17); ?></button>
