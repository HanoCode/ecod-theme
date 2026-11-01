<?php
/**
 * 站点页脚
 *
 * 版权 / 链接 / 浮动控件均由后台「页脚」区块管理（块系统）。
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

/* PJAX 局部渲染：与 header.php 的局部分支配对，只补 shell 的收尾标签 */
if (ecod_is_pjax()) {
    ob_start();
    ecod_widgets_zone('bottom_fluid', $this);
    $ecodBottomZone = ob_get_clean();
    echo '</div><!-- .ecod_layout -->' . $ecodBottomZone . '</div><!-- #ecod-shell --></body></html>';
    return;
}

$ecodShowFooterBar = ecod_block_on('credits') || ecod_block_on('links') || ecod_block_on('siteage');
?>
    </div><!-- .ecod_layout -->
    <?php ecod_widgets_zone('bottom_fluid', $this); ?>
</div><!-- .ecod_container -->

<?php if ($ecodShowFooterBar): ?>
    <footer class="ecod_footer">
        <div class="ecod_container">
            <div class="ecod_footer__inner">
                <?php ecod_block_render('credits', $this); ?>
                <?php ecod_block_render('links', $this); ?>
            </div>
            <?php ecod_block_render('siteage', $this); ?>
        </div>
    </footer>
<?php endif; ?>

<?php ecod_block_render('progress', $this); ?>
<?php ecod_block_render('totop', $this); ?>
<?php ecod_block_render('ticker', $this); ?>
<?php ecod_block_render('dock', $this); ?>

<?php if (function_exists('ecod_itemlist_output')) {
    ecod_itemlist_output();
} ?>
<?php $ecodIsContentPage = $this->is('post') || $this->is('page'); ?>
<?php if (ecod_option('ECode', 'off') === 'on' && $ecodIsContentPage):
/* 代码高亮只在正文出现：首页 / 列表页不加载 prism（约 -20KB + 1 个请求） */
?>
<script src="<?php echo ecod_asset('assets/vendor/prism/prism.js'); ?>" defer></script>
<?php endif; ?>
<?php if ($ecodIsContentPage):
/* 二维码库只服务文章页的分享卡（ecod.post.js 唯一调用点） */
?>
<script src="<?php echo ecod_asset('assets/vendor/ecod-qr.js'); ?>" defer></script>
<?php endif; ?>
<?php if (ecod_option('EBackdrop', 'none') === 'lightfall'):
/* 流光背景（Lightfall，WebGL2）：引擎 + 封装只在选中该项时下发；
   图层挂在 body 首个子元素（#ecod-shell 之外），PJAX 换页不重建 */
?>
<script src="<?php echo ecod_asset('assets/js/vendor/lightfall.js'); ?>" defer></script>
<script src="<?php echo ecod_asset('assets/js/ecod.lightfall.js'); ?>" defer></script>
<?php endif; ?>
<script src="<?php echo ecod_asset('assets/js/ecod.global.js'); ?>" defer></script>
<?php if (ecod_stats_enabled()):
/* 访问统计停留上报：只补「停留时长 + 是否跳出」，PV/UV 服务端已经记过。
   pagehide / 切后台时用 sendBeacon 发一次（不阻塞卸载，也不影响 BFCache）。
   PJAX 换页会改写 history，这里包一层拿「换过页」的信号并重置计时：
   换过页的不算跳出，停留时长按最后一页算。 */
?>
<script>
(function () {
    var api = window.ECOD && ECOD.api;
    if (!api || !navigator.sendBeacon) return;
    var start = Date.now(), moved = false, sent = false;
    ['pushState', 'replaceState'].forEach(function (m) {
        var orig = history[m];
        history[m] = function () {
            moved = true;
            start = Date.now();
            return orig.apply(this, arguments);
        };
    });
    function send() {
        if (sent) return;
        sent = true;
        var body = JSON.stringify({ d: Math.round((Date.now() - start) / 1000), b: moved ? 0 : 1 });
        try {
            navigator.sendBeacon(api + '?action=stats_ping', new Blob([body], { type: 'application/json' }));
        } catch (e) {}
    }
    addEventListener('pagehide', send);
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) send();
    });
})();
</script>
<?php endif; ?>
<?php if ($ecodIsContentPage):
/* 内容页增强包：目录 / 评论 / 朗读 / 收银台等模块只在 post 与独立页用到，
   首页与列表页不输出这个标签，减少首屏 JS。
   PJAX 从列表页进入内容页时由 global 的 ensurePostBundle() 动态注入。 */
?>
<script src="<?php echo ecod_asset('assets/js/ecod.post.js'); ?>" defer></script>
<?php endif; ?>
<?php $this->footer(); ?>
<?php
/* 后台「代码 → body 末尾代码」：统计、客服挂件等，原样输出。
   第三方悬浮挂件（客服球 / 悬浮广告）注意与主题自带的右下角浮动件共存：
   桌面端右下已被 回到顶部 占用（40px 一档、间距 24px），
   移动端启用 dock 后整个底部 10~70px 区间被占用；
   悬浮件请避开这些区间，z-index 不超过 var(--ecod-z-float)。 */
$ecodBodyCode = trim((string) ecod_option('ECustomBody', ''));
if ($ecodBodyCode !== '') {
    echo $ecodBodyCode;
}
?>
</body>
</html>
