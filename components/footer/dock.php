<?php
/**
 * 页脚块 · 移动端底部 dock
 *
 * 手机端底部快捷栏：内置条目（首页/搜索/深浅/顶部）可逐个开关，
 * 另支持追加自定义链接条目（icon/label/link）。5 套样式由参数 style
 * 写入 data-style，CSS 按 [data-style] 切换；labels 控制文字标签显隐。
 * 仅移动端显示（CSS 控制），启用后移动端右下角悬浮按钮由 dock 接管。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/* 开关参数归一化：'0'/0/'off' 都算关，其余算开（默认开） */
$ecodDockOn = static function (string $key, bool $default = true): bool {
    $value = ecod_p($key, $default ? '1' : '0');
    return !in_array((string) $value, ['0', 'off', 'false'], true);
};

$ecodDockStyle = (string) ecod_p('style', 'card');
if (!in_array($ecodDockStyle, ['card', 'glass', 'ios', 'float', 'glow'], true)) {
    $ecodDockStyle = 'card';
}
$ecodDockLabels = (string) ecod_p('labels', 'show');
if (!in_array($ecodDockLabels, ['show', 'active', 'hide'], true)) {
    $ecodDockLabels = 'show';
}

/* 组装条目：内置优先（顺序即视觉顺序），自定义条目追加在后 */
$ecodDockItems = [];

if ($ecodDockOn('show_home')) {
    $ecodDockHome = '';
    try {
        $ecodDockHome = \Widget\Options::alloc()->siteUrl;
    } catch (\Throwable $e) {
    }
    $ecodDockItems[] = [
        'kind'  => 'link',
        'href'  => $ecodDockHome,
        'icon'  => 'home',
        'label' => '首页',
        'extra' => '',
        'home'  => true,
    ];
}
if ($ecodDockOn('show_search')) {
    $ecodDockItems[] = [
        'kind'  => 'button',
        'icon'  => 'search',
        'label' => '搜索',
        'extra' => ' data-ecod-drawer-open data-ecod-dock-search',
        'home'  => false,
    ];
}
if ($ecodDockOn('show_theme') && ecod_option('EDark', 'auto') !== 'off') {
    $ecodDockItems[] = [
        'kind'  => 'button',
        'icon'  => 'moon',
        'label' => '深浅',
        'extra' => ' data-ecod-theme-toggle',
        'home'  => false,
    ];
}
if ($ecodDockOn('show_top')) {
    $ecodDockItems[] = [
        'kind'  => 'button',
        'icon'  => 'arrow-up',
        'label' => '顶部',
        'extra' => ' data-ecod-dock-top',
        'home'  => false,
    ];
}

/* 自定义条目（repeater JSON：icon/label/link） */
$ecodDockCustom = (string) ecod_p('items', '');
if ($ecodDockCustom !== '') {
    $decoded = json_decode($ecodDockCustom, true);
    if (is_array($decoded)) {
        foreach ($decoded as $row) {
            $link = trim((string) ($row['link'] ?? ''));
            if ($link === '' || !preg_match('#^(https?:)?//#i', $link)) {
                continue;
            }
            $ecodDockItems[] = [
                'kind'  => 'link',
                'href'  => $link,
                'icon'  => trim((string) ($row['icon'] ?? '')),
                'label' => trim((string) ($row['label'] ?? '')),
                'extra' => '',
                'home'  => false,
            ];
        }
    }
}

if (!$ecodDockItems) {
    return; /* 全部关掉就不输出，避免空壳 */
}

/* 当前地址（用于首页高亮）：拿不到就不标 */
$ecodDockHere = '';
try {
    $ecodDockHere = \Typecho\Request::alloc()->getRequestUrl();
} catch (\Throwable $e) {
}
$ecodDockNorm = static function (string $url): string {
    $url = rtrim(trim($url), '/');
    /* 去掉协议与主机差异，只留路径与查询，兼容 http/https 与带不带 www */
    $url = preg_replace('#^[a-z][a-z0-9+.-]*://[^/]+#i', '', $url);
    return $url === '' ? '/' : $url;
};
$ecodDockHerePath = $ecodDockNorm($ecodDockHere);
$ecodDockActiveSet = false;
?>
<nav class="ecod_dock" data-ecod-dock data-style="<?php echo htmlspecialchars($ecodDockStyle, ENT_QUOTES, 'UTF-8'); ?>"
    data-labels="<?php echo htmlspecialchars($ecodDockLabels, ENT_QUOTES, 'UTF-8'); ?>" aria-label="快捷操作栏">
    <?php foreach ($ecodDockItems as $ecodDockItem):
        $ecodDockTag = $ecodDockItem['kind'] === 'link' ? 'a' : 'button';
        $ecodDockHomePath = $ecodDockNorm($ecodDockItem['href'] ?? '');
        $ecodDockActive = $ecodDockItem['home'] && $ecodDockHerePath !== '' && $ecodDockHerePath === $ecodDockHomePath;
        if ($ecodDockActive) {
            $ecodDockActiveSet = true;
        }
        $ecodDockLabel = $ecodDockItem['label'] !== ''
            ? htmlspecialchars($ecodDockItem['label'], ENT_QUOTES, 'UTF-8')
            : '';
        ?>
        <<?php echo $ecodDockTag; ?> class="ecod_dock__btn<?php echo $ecodDockActive ? ' is-active' : ''; ?>"
            <?php if ($ecodDockTag === 'a'): ?>href="<?php echo htmlspecialchars($ecodDockItem['href'], ENT_QUOTES, 'UTF-8'); ?>"
            <?php echo $ecodDockItem['home'] ? '' : ' target="_blank" rel="noopener"'; ?>
            <?php else: ?>type="button"<?php endif; ?>
            <?php echo $ecodDockLabel !== '' ? ' aria-label="' . $ecodDockLabel . '"' : ' aria-label="dock"'; ?>
            <?php echo $ecodDockItem['extra']; ?>>
            <?php
            if ($ecodDockItem['icon'] !== '') {
                echo ecod_icon($ecodDockItem['icon'], 19);
            } elseif ($ecodDockItem['label'] !== '') {
                /* 没选图标用首字兜底，避免空按钮 */
                echo '<span class="ecod_dock__mark" aria-hidden="true">'
                    . htmlspecialchars(mb_substr($ecodDockItem['label'], 0, 1), ENT_QUOTES, 'UTF-8') . '</span>';
            } else {
                echo ecod_icon('link', 19);
            }
            ?>
            <?php if ($ecodDockLabel !== ''): ?><span><?php echo $ecodDockLabel; ?></span><?php endif; ?>
        </<?php echo $ecodDockTag; ?>>
    <?php endforeach; ?>
    <span class="ecod_dock__ind" aria-hidden="true"
        <?php if (!$ecodDockActiveSet): ?>hidden<?php endif; ?>></span>
</nav>
