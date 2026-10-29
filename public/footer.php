<?php
/**
 * 站点页脚
 *
 * 版权 / 链接 / 浮动控件均由后台「页脚」区块管理（块系统）。
 *
 * @package Ecod Theme
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/* PJAX 局部渲染：与 header.php 的局部分支配对，只补 shell 的收尾标签 */
if (ecod_is_pjax()) {
    echo '</div><!-- .ecod_layout --></div><!-- #ecod-shell --></body></html>';
    return;
}

$ecodShowFooterBar = ecod_block_on('credits') || ecod_block_on('links');
?>
    </div><!-- .ecod_layout -->
</div><!-- .ecod_container -->

<?php if ($ecodShowFooterBar): ?>
    <footer class="ecod_footer">
        <div class="ecod_container">
            <div class="ecod_footer__inner">
                <?php ecod_block_render('credits', $this); ?>
                <?php ecod_block_render('links', $this); ?>
            </div>
        </div>
    </footer>
<?php endif; ?>

<?php ecod_block_render('progress', $this); ?>
<?php ecod_block_render('theme-toggle', $this); ?>
<?php ecod_block_render('totop', $this); ?>
<?php ecod_block_render('dock', $this); ?>

<?php if (function_exists('ecod_itemlist_output')) {
    ecod_itemlist_output();
} ?>
<?php if (ecod_option('ECode', 'off') === 'on'): ?>
<script src="<?php echo ecod_asset('assets/vendor/prism/prism.js'); ?>" defer></script>
<?php endif; ?>
<script src="<?php echo ecod_asset('assets/vendor/ecod-qr.js'); ?>" defer></script>
<script src="<?php echo ecod_asset('assets/js/ecod.global.js'); ?>" defer></script>
<?php $this->footer(); ?>
<?php
/* 后台「代码 → body 末尾代码」：统计、客服挂件等，原样输出 */
$ecodBodyCode = trim((string) ecod_option('ECustomBody', ''));
if ($ecodBodyCode !== '') {
    echo $ecodBodyCode;
}
?>
</body>
</html>
