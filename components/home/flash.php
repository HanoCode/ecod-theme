<?php
/**
 * 首页模块 · 快讯（时间线形式，日期戳 + 短句，无封面）
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodFlashCount = max(3, min(12, (int) ecod_p('count', 6)));
$ecodFlashMid = max(0, (int) ecod_p('mid', 0));
$ecodFlashDays = max(0, (int) ecod_p('days', 0));
$ecodFlashDesc = (int) ecod_p('desc', 1) === 1;

$ecodFlashQuery = 'pageSize=' . $ecodFlashCount;
if ($ecodFlashMid > 0) {
    $ecodFlashQuery .= '&category=' . $ecodFlashMid;
}
if ($ecodFlashDays > 0) {
    $ecodFlashQuery .= '&days=' . $ecodFlashDays;
}

$this->widget('EcodPostQuery@flash' . $ecodFlashMid, $ecodFlashQuery)->to($ecodFlashQ);
if (!$ecodFlashQ->have()) {
    return;
}

/* 指定分类时给出「查看全部」入口；未指定分类且建了快讯页时指向快讯页 */
$ecodFlashMore = '';
$ecodFlashMoreName = '';
if ($ecodFlashMid > 0) {
    $this->widget('\Widget\Metas\Category\Rows')->to($ecodFlashCats);
    while ($ecodFlashCats->next()) {
        if ((int) $ecodFlashCats->mid !== $ecodFlashMid) {
            continue;
        }
        ob_start();
        $ecodFlashCats->permalink();
        $ecodFlashMore = trim((string) ob_get_clean());
        $ecodFlashMoreName = (string) $ecodFlashCats->name;
        break;
    }
} elseif (function_exists('ecod_template_page_url')) {
    $ecodFlashMore = ecod_template_page_url('flash.php');
    $ecodFlashMoreName = '快讯页';
}
?>
<section class="ecod_section">
    <?php ecod_block_head($ecodFlashMore !== '' ? ['more_url' => $ecodFlashMore, 'more_text' => $ecodFlashMoreName ?: '更多'] : []); ?>

    <div class="ecod_flash">
        <?php while ($ecodFlashQ->next()): ?>
            <?php
            $ecodFlashUrl = (string) $ecodFlashQ->permalink;
            $ecodFlashName = (string) $ecodFlashQ->title;
            $ecodFlashTime = (int) $ecodFlashQ->created;
            $ecodFlashLocked = ecod_has_password($ecodFlashQ);
            $ecodFlashCat = '';
            if (!empty($ecodFlashQ->categories)) {
                $ecodFlashFirst = $ecodFlashQ->categories[0];
                $ecodFlashCat = is_array($ecodFlashFirst)
                    ? (string) ($ecodFlashFirst['name'] ?? '')
                    : (string) ($ecodFlashFirst->name ?? '');
            }
            ?>
            <a class="ecod_flash__item<?php echo $ecodFlashLocked ? ' is-locked' : ''; ?>"
               href="<?php echo htmlspecialchars($ecodFlashUrl, ENT_QUOTES, 'UTF-8'); ?>">
                <span class="ecod_flash__stamp" aria-hidden="true">
                    <b><?php echo date('d', $ecodFlashTime); ?></b>
                    <small><?php echo date('m', $ecodFlashTime); ?> 月</small>
                </span>
                <span class="ecod_flash__main">
                    <span class="ecod_flash__title">
                        <?php if ($ecodFlashLocked): ?><?php echo ecod_lock_icon(11, 'ecod_flash__lock'); ?><?php endif; ?>
                        <?php echo htmlspecialchars($ecodFlashName, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                    <?php if ($ecodFlashDesc): ?>
                        <?php $ecodFlashText = trim(ecod_excerpt($ecodFlashQ)); ?>
                        <?php if ($ecodFlashText !== ''): ?>
                            <span class="ecod_flash__desc"><?php echo htmlspecialchars($ecodFlashText, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                    <span class="ecod_flash__meta">
                        <time datetime="<?php echo date('c', $ecodFlashTime); ?>"><?php echo ecod_relative_time($ecodFlashTime); ?></time>
                        <?php if ($ecodFlashCat !== ''): ?>
                            <i aria-hidden="true">·</i><span class="ecod_flash__cat"><?php echo htmlspecialchars($ecodFlashCat, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                        <i aria-hidden="true">·</i><span><?php echo ecod_icon('eye', 11); ?><?php echo ecod_num_short(ecod_views($ecodFlashQ)); ?></span>
                    </span>
                </span>
            </a>
        <?php endwhile; ?>
    </div>
</section>
