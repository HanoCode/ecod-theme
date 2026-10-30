<?php
/**
 * 页脚块 · 站点公告（渲染在页头下方，全站顶部）
 *
 * 三种展示方式（参数 mode）：
 *   bar    单条横幅（多条时取第一条）
 *   rotate 多条定时轮播（JS 每 5 秒切换，「查看」跟随当前条）
 *   popup  公告弹窗（内容变化后每个访客弹一次，关闭记忆在 localStorage）
 *
 * 内容来源：参数 items（多条列表，repeater JSON）优先；
 * 老配置的单条 content / link 参数继续兼容。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/* 组装公告列表：items repeater 优先，回落单条 content */
$ecodNoticeItems = [];
$ecodNoticeItemsRaw = (string) ($ecodBlock['params']['items'] ?? '');
if ($ecodNoticeItemsRaw !== '') {
    $decoded = json_decode($ecodNoticeItemsRaw, true);
    if (is_array($decoded)) {
        foreach ($decoded as $row) {
            $text = trim((string) ($row['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $ecodNoticeItems[] = [
                'text' => $text,
                'link' => trim((string) ($row['link'] ?? '')),
            ];
        }
    }
}
if (!$ecodNoticeItems) {
    $fallback = trim((string) ($ecodBlock['params']['content'] ?? ''));
    if ($fallback === '') {
        return;
    }
    $ecodNoticeItems[] = [
        'text' => $fallback,
        'link' => trim((string) ($ecodBlock['params']['link'] ?? '')),
    ];
}

$ecodNoticeTone = (string) ($ecodBlock['params']['tone'] ?? 'info');
if (!in_array($ecodNoticeTone, ['info', 'success', 'warn'], true)) {
    $ecodNoticeTone = 'info';
}

$ecodNoticeMode = (string) ($ecodBlock['params']['mode'] ?? 'bar');
if (!in_array($ecodNoticeMode, ['bar', 'rotate', 'popup'], true)) {
    $ecodNoticeMode = 'bar';
}
$ecodNoticeClosable = !empty($ecodBlock['params']['closable']);

/* 关闭记忆的 key 跟随内容变化：改了公告，旧记忆自动失效 */
$ecodNoticeHash = substr(md5($ecodNoticeTone . '|' . $ecodNoticeMode . '|' . json_encode($ecodNoticeItems, JSON_UNESCAPED_UNICODE)), 0, 10);

/* 弹窗模式独立渲染，不占顶部横幅位 */
if ($ecodNoticeMode === 'popup') {
    ?>
<div class="ecod_noticepop" data-ecod-noticepop data-ecod-notice-hash="<?php echo $ecodNoticeHash; ?>" aria-hidden="true">
    <div class="ecod_noticepop__mask" data-ecod-noticepop-close></div>
    <div class="ecod_noticepop__panel" role="dialog" aria-modal="true" aria-label="站点公告">
        <button type="button" class="ecod_noticepop__close" data-ecod-noticepop-close aria-label="关闭公告">
            <?php echo ecod_icon('x', 16); ?>
        </button>
        <span class="ecod_noticepop__flag"><?php echo ecod_icon('alert', 14); ?>站点公告</span>
        <div class="ecod_noticepop__body ecod_noticepop__body--<?php echo $ecodNoticeTone; ?>">
            <?php foreach ($ecodNoticeItems as $ecodNoticeItem): ?>
                <div class="ecod_noticepop__item">
                    <div class="ecod_noticepop__text"><?php echo $ecodNoticeItem['text']; ?></div>
                    <?php if ($ecodNoticeItem['link'] !== ''): ?>
                        <a href="<?php echo htmlspecialchars($ecodNoticeItem['link'], ENT_QUOTES, 'UTF-8'); ?>">查看详情<?php echo ecod_icon('chevron', 12); ?></a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="ecod_noticepop__ok ecod_btn" data-ecod-noticepop-close>我知道了</button>
    </div>
</div>
    <?php
    return;
}

/* bar / rotate：顶部横幅。多条且非轮播时只显示第一条 */
$ecodNoticeVisible = $ecodNoticeMode === 'rotate' ? $ecodNoticeItems : [$ecodNoticeItems[0]];
$ecodNoticeRotate = $ecodNoticeMode === 'rotate' && count($ecodNoticeVisible) > 1;
?>
<aside class="ecod_notice ecod_notice--<?php echo $ecodNoticeTone; ?>"
    data-ecod-notice data-ecod-notice-hash="<?php echo $ecodNoticeHash; ?>"
    data-ecod-notice-mode="<?php echo $ecodNoticeRotate ? 'rotate' : 'bar'; ?>" role="status">
    <div class="ecod_container">
        <div class="ecod_notice__inner">
            <span class="ecod_notice__icon" aria-hidden="true"><?php echo ecod_icon('alert', 15); ?></span>
            <div class="ecod_notice__stage"<?php echo $ecodNoticeRotate ? ' data-ecod-notice-stage' : ''; ?>>
                <?php foreach ($ecodNoticeVisible as $ecodNoticeIndex => $ecodNoticeItem): ?>
                    <div class="ecod_notice__slide<?php echo $ecodNoticeIndex === 0 ? ' is-active' : ''; ?>"
                        data-ecod-notice-slide
                        data-link="<?php echo htmlspecialchars($ecodNoticeItem['link'], ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="ecod_notice__text"><?php echo $ecodNoticeItem['text']; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($ecodNoticeRotate): ?>
                <span class="ecod_notice__count" data-ecod-notice-count>1/<?php echo count($ecodNoticeVisible); ?></span>
            <?php endif; ?>
            <a class="ecod_notice__link" data-ecod-notice-link
               href="<?php echo htmlspecialchars((string) $ecodNoticeVisible[0]['link'], ENT_QUOTES, 'UTF-8'); ?>"
               <?php echo (string) $ecodNoticeVisible[0]['link'] === '' ? 'hidden' : ''; ?>>
                查看<?php echo ecod_icon('chevron', 13); ?>
            </a>
            <?php if ($ecodNoticeClosable): ?>
                <button type="button" class="ecod_notice__close" data-ecod-notice-close aria-label="关闭公告">
                    <?php echo ecod_icon('x', 14); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
</aside>
