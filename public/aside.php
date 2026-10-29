<?php
/**
 * 侧栏
 *
 * 具体模块由后台「侧栏」区块管理（块系统）。
 *
 * @package Ecod Theme
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

if (ecod_option('EAside', 'right') === 'none') {
    return;
}
?>
<aside class="ecod_aside" aria-label="侧边栏">
<?php ecod_blocks_render('aside', $this); ?>
</aside>
