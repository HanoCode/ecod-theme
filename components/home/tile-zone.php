<?php
/**
 * 首页/侧栏模块 · 磁贴快捷区（参考 guaqi tileZone）
 *
 * 一排图标磁贴入口：图标 + 名称 + 可选角标。
 * 图标名取 components/icons.php 的白名单（ecod_icon_names()），留空或不认识时用 link 兜底。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodTzItems = [];
$ecodTzRaw = json_decode((string) ecod_p('items', ''), true);
if (is_array($ecodTzRaw)) {
    $ecodTzValidIcons = ecod_icon_names();
    foreach ($ecodTzRaw as $ecodTzRow) {
        $ecodTzLabel = trim((string) ($ecodTzRow['label'] ?? ''));
        $ecodTzHref = trim((string) ($ecodTzRow['href'] ?? ''));
        if ($ecodTzLabel === '' || $ecodTzHref === '') {
            continue;
        }
        $ecodTzIcon = trim((string) ($ecodTzRow['icon'] ?? ''));
        if (!in_array($ecodTzIcon, $ecodTzValidIcons, true)) {
            $ecodTzIcon = 'link';
        }
        $ecodTzColor = strtolower(trim((string) ($ecodTzRow['color'] ?? '')));
        if (!in_array($ecodTzColor, ['blue', 'green', 'amber', 'red', 'violet', 'gray'], true)) {
            $ecodTzColor = 'blue';
        }
        $ecodTzBadge = mb_substr(trim((string) ($ecodTzRow['badge'] ?? '')), 0, 6, 'UTF-8');
        $ecodTzItems[] = [
            'label'   => $ecodTzLabel,
            'href'    => $ecodTzHref,
            'icon'    => $ecodTzIcon,
            'color'   => $ecodTzColor,
            'badge'   => $ecodTzBadge,
            'is_abs'  => (bool) preg_match('#^https?://#i', $ecodTzHref),
        ];
    }
}
if (!$ecodTzItems) {
    return;
}

$ecodTzCols = (string) ecod_p('cols', '5');
$ecodTzCols = in_array($ecodTzCols, ['3', '4', '5', '6'], true) ? $ecodTzCols : '5';
$ecodTzStyle = (string) ecod_p('style', 'card') === 'plain' ? 'plain' : 'card';
/* 动效总开关（F5）：off 时磁贴不加 hover 上浮 */
$ecodTzMotion = ecod_option('ECardMotion', 'on') === 'on' ? ' is-motion' : '';
?>
<section class="ecod_widget ecod_tz" data-cols="<?php echo $ecodTzCols; ?>" data-style="<?php echo $ecodTzStyle; ?>">
    <?php ecod_block_head(); ?>
    <div class="ecod_tz__grid">
        <?php foreach ($ecodTzItems as $ecodTzItem): ?>
            <a class="ecod_tz__tile<?php echo $ecodTzMotion; ?>"
               href="<?php echo htmlspecialchars($ecodTzItem['href'], ENT_QUOTES, 'UTF-8'); ?>"<?php if ($ecodTzItem['is_abs']): ?> target="_blank" rel="noopener nofollow"<?php endif; ?>>
                <?php if ($ecodTzItem['badge'] !== ''): ?>
                    <span class="ecod_tz__badge is-<?php echo $ecodTzItem['badge'] === 'HOT' ? 'hot' : 'new'; ?>"><?php echo htmlspecialchars($ecodTzItem['badge'], ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
                <span class="ecod_tz__icon is-<?php echo $ecodTzItem['color']; ?>" aria-hidden="true"><?php echo ecod_icon($ecodTzItem['icon'], 18); ?></span>
                <span class="ecod_tz__label"><?php echo htmlspecialchars($ecodTzItem['label'], ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
