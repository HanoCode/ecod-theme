<?php
/**
 * 页脚块 · 阅读进度条
 *
 * 顶部细条，随滚动反映正文阅读进度；行为在 ecod.global.js 的
 * 进度模块里实现（监听正文容器，页脚滚动条不参与计算）。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @since   2026-10-29
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 * @license   MIT
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
?>
<div class="ecod_progress" data-ecod-progress aria-hidden="true"></div>
