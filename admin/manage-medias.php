<?php
/**
 * Ecod 资源库管理台
 *
 * 由 Typecho admin/manage-medias.php（Ecod 引导版）在当前主题为
 * Ecod 时引入，接管后台「管理媒体」页面：完整的图片网格、格式
 * 筛选、批量转 WebP / 删除、改名、引用计数与用量统计。
 * 依赖 media.php（上传/删除/改名/转换 API），登录态由 common.php 校验。
 */

if (!defined('__TYPECHO_ADMIN__')) {
    exit;
}

$ecodManageDir  = dirname(__DIR__);
$ecodManageUrl  = (string) $options->themeUrl;
$ecodManageVer  = function (string $rel) use ($ecodManageDir): string {
    $time = @filemtime($ecodManageDir . '/' . $rel);
    return $time ? '?v=' . $time : '';
};
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($ecodManageUrl . '/assets/admin/tokens.css' . $ecodManageVer('assets/admin/tokens.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars($ecodManageUrl . '/assets/admin/media.css' . $ecodManageVer('assets/admin/media.css'), ENT_QUOTES, 'UTF-8'); ?>">
<script>
window.ECOD_MEDIA = {
    api: <?php echo json_encode($ecodManageUrl . '/media.php', JSON_UNESCAPED_SLASHES); ?>,
    token: <?php echo json_encode($security->getToken('ecod-media')); ?>
};
</script>

<main class="main">
    <div class="body container">
        <div id="ecod-media-page"></div>
    </div>
</main>

<script src="<?php echo htmlspecialchars($ecodManageUrl . '/assets/admin/media.js' . $ecodManageVer('assets/admin/media.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
(function () {
    function boot() {
        if (!window.EcodMedia) {
            window.setTimeout(boot, 60);
            return;
        }
        /* multiple 开启批量选择（批量转 WebP / 批量删除依赖选中态） */
        window.EcodMedia.open({ manage: true, multiple: true });

        /* 对话框从 body 尾部挪进内容区占位，避免落在页脚之后 */
        var root = document.querySelector('.ecmd--page');
        var holder = document.getElementById('ecod-media-page');
        if (root && holder && holder !== root.parentNode) {
            holder.appendChild(root);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>

<?php
unset($ecodManageDir, $ecodManageUrl, $ecodManageVer);
include 'copyright.php';
include 'common-js.php';
include 'footer.php';
?>
