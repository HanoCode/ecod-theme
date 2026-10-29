<?php
/**
 * 页脚块 · 站点公告（渲染在页头下方，全站顶部横幅）
 *
 * @package Ecod Theme
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodNoticeContent = trim((string) ($ecodBlock['params']['content'] ?? ''));
if ($ecodNoticeContent === '') {
    return;
}

$ecodNoticeTone = (string) ($ecodBlock['params']['tone'] ?? 'info');
if (!in_array($ecodNoticeTone, ['info', 'success', 'warn'], true)) {
    $ecodNoticeTone = 'info';
}

$ecodNoticeLink = trim((string) ($ecodBlock['params']['link'] ?? ''));
$ecodNoticeClosable = !empty($ecodBlock['params']['closable']);

/* 关闭记忆的 key 跟随内容变化：改了公告，旧记忆自动失效 */
$ecodNoticeHash = substr(md5($ecodNoticeTone . $ecodNoticeLink . $ecodNoticeContent), 0, 10);
?>
<aside class="ecod_notice ecod_notice--<?php echo $ecodNoticeTone; ?>"
    data-ecod-notice data-ecod-notice-hash="<?php echo $ecodNoticeHash; ?>" role="status">
    <div class="ecod_container">
        <div class="ecod_notice__inner">
            <span class="ecod_notice__icon" aria-hidden="true"><?php echo ecod_icon('alert', 15); ?></span>
            <div class="ecod_notice__text"><?php echo $ecodNoticeContent; ?></div>
            <?php if ($ecodNoticeLink !== ''): ?>
                <a class="ecod_notice__link" href="<?php echo htmlspecialchars($ecodNoticeLink, ENT_QUOTES, 'UTF-8'); ?>">
                    查看<?php echo ecod_icon('chevron', 13); ?>
                </a>
            <?php endif; ?>
            <?php if ($ecodNoticeClosable): ?>
                <button type="button" class="ecod_notice__close" data-ecod-notice-close aria-label="关闭公告">
                    <?php echo ecod_icon('x', 14); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
</aside>
