<?php
/**
 * 侧栏块 · 按月归档
 *
 * @package Ecod Theme
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$this->widget(
    '\Widget\Contents\Post\Date',
    'type=month&format=Y-m&limit=' . max(1, (int) ecod_p('limit', 6))
)->to($ecodAsideArch);
if (!$ecodAsideArch->have()) {
    return;
}
?>
<section class="ecod_widget">
    <h2 class="ecod_widget__title"><?php echo htmlspecialchars((string) ecod_p('title', '归档')); ?></h2>
    <div class="ecod_wlist">
        <?php while ($ecodAsideArch->next()): ?>
            <a href="<?php echo htmlspecialchars($ecodAsideArch->permalink); ?>" class="ecod_wlist__item">
                <span class="ecod_wlist__name"><?php
                    $ecodArchY = (string) $ecodAsideArch->year;
                    $ecodArchM = (string) $ecodAsideArch->month;
                    echo htmlspecialchars($ecodArchY . ' 年 ' . (int) $ecodArchM . ' 月');
                ?></span>
                <b class="ecod_wlist__num"><?php echo (int) $ecodAsideArch->count; ?></b>
            </a>
        <?php endwhile; ?>
    </div>
</section>
