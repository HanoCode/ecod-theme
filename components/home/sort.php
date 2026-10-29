<?php
/**
 * 列表工具条 · 排序入口
 *
 * 形态：右侧一枚紧凑按钮 + 浮层（不并列铺开，避免与分类 Tab 抢位置）。
 * 选项是真实链接（?sort=views），关掉 JS 也能用；JS 只负责展开/收起。
 *
 * @package Ecod Theme
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodSortOpts = ecod_sort_options();
$ecodSortCur = ecod_sort_current();
?>
<div class="ecod_sort" data-ecod-sort>
    <button type="button" class="ecod_sort__btn" data-ecod-sort-btn
            aria-expanded="false" aria-haspopup="true" aria-controls="ecod-sort-pop">
        <?php echo ecod_icon($ecodSortOpts[$ecodSortCur]['icon'] ?? 'clock', 13); ?>
        <span class="ecod_sort__label"><?php echo htmlspecialchars(ecod_sort_label($ecodSortCur)); ?></span>
        <span class="ecod_sort__caret" aria-hidden="true"><?php echo ecod_icon('chevron', 11); ?></span>
    </button>
    <div class="ecod_sort__pop" id="ecod-sort-pop" role="menu">
        <p class="ecod_sort__head">排序方式</p>
        <?php foreach ($ecodSortOpts as $ecodSortKey => $ecodSortItem): ?>
            <a class="ecod_sort__opt<?php echo $ecodSortKey === $ecodSortCur ? ' is-active' : ''; ?>"
               role="menuitemradio"
               aria-checked="<?php echo $ecodSortKey === $ecodSortCur ? 'true' : 'false'; ?>"
               href="<?php echo htmlspecialchars(ecod_sort_url($ecodSortKey), ENT_QUOTES, 'UTF-8'); ?>"
               <?php echo $ecodSortKey === $ecodSortCur ? 'aria-current="true"' : ''; ?>>
                <?php echo ecod_icon($ecodSortItem['icon'], 13); ?>
                <span><?php echo htmlspecialchars($ecodSortItem['label']); ?></span>
                <i class="ecod_sort__tick" aria-hidden="true"><?php echo ecod_icon('check', 12); ?></i>
            </a>
        <?php endforeach; ?>
    </div>
</div>
