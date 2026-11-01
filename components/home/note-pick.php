<?php
/**
 * 模块 · 笔记精选（图文笔记的竖版封面网格）
 *
 * 数据只取「字段 note=on」的文章（EcodPostQuery 的 noteonly），
 * 版式与列表笔记卡同源：3:4 封面 + 两行标题 + 图数角标 + 点赞数。
 *
 * 为什么不复用 ecod_card('note')：那个卡的内依赖（ItemList 记账、作者行、
 * 专题角标）是列表页的语义，摆在首页/侧栏的模块不需要，而且它的样式
 * （§50）只在 list 族下发——首页加载不到。这里自绘一份紧凑卡，
 * 样式放 core 族（§52），侧栏与首页都能用。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @since   2026-11-01
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodNpCount = max(2, min(12, (int) ecod_p('count', 6)));
$ecodNpCols = (int) ecod_p('cols', 3);
if (!in_array($ecodNpCols, [2, 3, 4], true)) {
    $ecodNpCols = 3;
}
$ecodNpSource = (string) ecod_p('source', 'latest');
$ecodNpOrder = $ecodNpSource === 'hot' ? 'weighted' : ($ecodNpSource === 'rand' ? 'rand' : 'recent');
$ecodNpTags = trim((string) ecod_p('tags', ''));
$ecodNpMeta = (int) ecod_p('meta', 1) === 1;

/* 总开关没开时笔记字段不会被写入，模块天然为空；这里提前返回省一条查询 */
if (function_exists('ecod_note_enabled') && !ecod_note_enabled()) {
    return;
}

$ecodNpQuery = 'pageSize=' . $ecodNpCount . '&noteonly=1&order=' . $ecodNpOrder;
if ($ecodNpTags !== '') {
    $ecodNpQuery .= '&tags=' . rawurlencode($ecodNpTags);
}
$this->widget('EcodPostQuery@notepick', $ecodNpQuery)->to($ecodNpQ);
if (!$ecodNpQ->have()) {
    return;
}

list($ecodNpRatio) = function_exists('ecod_note_ratio') ? ecod_note_ratio() : ['3 / 4', 480, 640];
$ecodNpRatio = (string) $ecodNpRatio;
?>
<section class="ecod_widget ecod_notepick" style="--ecod-np-cols: <?php echo $ecodNpCols; ?>; --ecod-np-ratio: <?php echo htmlspecialchars($ecodNpRatio, ENT_QUOTES, 'UTF-8'); ?>;">
    <?php ecod_block_head(); ?>

    <div class="ecod_notepick__grid">
        <?php while ($ecodNpQ->next()): ?>
        <?php
        $ecodNpCid = (int) $ecodNpQ->cid;
        $ecodNpUrl = (string) $ecodNpQ->permalink;
        $ecodNpTitle = (string) $ecodNpQ->title;
        $ecodNpCover = function_exists('ecod_thumbnails') ? (ecod_thumbnails($ecodNpQ, 1, 'note')[0] ?? '') : '';
        $ecodNpShots = function_exists('ecod_note_total') ? ecod_note_total($ecodNpQ) : 0;
        $ecodNpLikes = $ecodNpMeta && function_exists('ecod_likes') ? (int) ecod_likes($ecodNpCid) : 0;
        $ecodNpLocked = function_exists('ecod_has_password') ? ecod_has_password($ecodNpQ) : false;
        ?>
        <a class="ecod_notepick__item<?php echo $ecodNpLocked ? ' is-locked' : ''; ?>" href="<?php echo htmlspecialchars($ecodNpUrl, ENT_QUOTES, 'UTF-8'); ?>">
            <span class="ecod_notepick__cover">
                <?php if ($ecodNpCover !== ''): ?>
                    <img src="<?php echo htmlspecialchars($ecodNpCover, ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($ecodNpTitle, ENT_QUOTES, 'UTF-8'); ?>"
                         width="480" height="640" loading="lazy" decoding="async">
                <?php else: ?>
                    <span class="ecod_ph ecod_notepick__ph" aria-hidden="true"><?php echo htmlspecialchars(mb_substr($ecodNpTitle, 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
                <?php if ($ecodNpShots > 1): ?>
                    <span class="ecod_notepick__shots" aria-hidden="true"><?php echo $ecodNpShots; ?> 图</span>
                <?php endif; ?>
                <?php if ($ecodNpLocked): ?>
                    <span class="ecod_notepick__lock" aria-hidden="true"><?php echo ecod_icon('lock', 11); ?></span>
                <?php endif; ?>
            </span>
            <span class="ecod_notepick__body">
                <b class="ecod_notepick__title"><?php echo htmlspecialchars($ecodNpTitle, ENT_QUOTES, 'UTF-8'); ?></b>
                <?php if ($ecodNpMeta): ?>
                <span class="ecod_notepick__meta">
                    <span class="ecod_notepick__likes">
                        <?php echo ecod_icon('heart', 12); ?><b><?php echo $ecodNpLikes > 0 ? ecod_num_short($ecodNpLikes) : '赞'; ?></b>
                    </span>
                </span>
                <?php endif; ?>
            </span>
        </a>
        <?php endwhile; ?>
    </div>
</section>
