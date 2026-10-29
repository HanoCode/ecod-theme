<?php
/**
 * Ecod Theme · 侧栏
 *
 * 侧栏位置按「单篇覆盖 > 页面类型 > 全站默认」判定（见 ecod_aside_pos）；
 * 模块由模块管理台按区域摆放：全局侧栏顶部 → 本页侧栏 → 全局侧栏底部。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @since   2026-10-29
 * @version 2.0.0
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

if (ecod_aside_pos($this) === 'none') {
    return;
}
?>
<aside class="ecod_aside" aria-label="侧边栏">
<?php ecod_widgets_render('sidebar', $this); ?>
</aside>
