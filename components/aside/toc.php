<?php
/**
 * 侧栏块 · 文章目录
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

if (!$this->is('post')) {
    return;
}
?>
<section class="ecod_widget" data-ecod-toc-wrap>
    <h2 class="ecod_widget__title"><?php echo htmlspecialchars((string) ecod_p('title', '目录')); ?></h2>
    <div class="ecod_toc" data-ecod-toc></div>
</section>
