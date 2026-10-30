<?php
/**
 * 侧栏块 · 分类精选（首页「分类精选」放到侧栏时的紧凑版）
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$this->widget('\Widget\Metas\Category\Rows')->to($ecodCatPick);
if (!$ecodCatPick->have()) {
    return;
}

$ecodCatWant = (int) ecod_p('mid', 0);
$ecodCatMid = 0;
$ecodCatName = '';
$ecodCatUrl = '';

while ($ecodCatPick->next()) {
    if ($ecodCatWant === 0 || (int) $ecodCatPick->mid === $ecodCatWant) {
        $ecodCatMid = (int) $ecodCatPick->mid;
        $ecodCatName = (string) $ecodCatPick->name;
        ob_start();
        $ecodCatPick->permalink();
        $ecodCatUrl = trim(ob_get_clean());
        break;
    }
}

if ($ecodCatMid === 0) {
    return;
}

$this->widget('EcodPostQuery@asidecatpost', 'pageSize=' . max(1, (int) ecod_p('count', 3)) . '&category=' . $ecodCatMid)->to($ecodCatPosts);
if (!$ecodCatPosts->have()) {
    return;
}
?>
<section class="ecod_widget">
    <h2 class="ecod_widget__title">
        <?php echo htmlspecialchars($ecodCatName); ?>
        <a class="ecod_widget__more" href="<?php echo htmlspecialchars($ecodCatUrl, ENT_QUOTES, 'UTF-8'); ?>">全部</a>
    </h2>
    <div class="ecod_wlist ecod_wlist--thumb">
        <?php while ($ecodCatPosts->next()): ?>
            <?php $ecodCatImg = ecod_thumbnails($ecodCatPosts, 1, 'side')[0] ?? ''; ?>
            <a class="ecod_wlist__item" href="<?php $ecodCatPosts->permalink(); ?>">
                <span class="ecod_wlist__cover"<?php if ($ecodCatImg): ?> style="background-image:url('<?php echo htmlspecialchars($ecodCatImg); ?>')"<?php endif; ?>>
                    <?php if (!$ecodCatImg): ?><span><?php echo mb_substr($ecodCatPosts->title, 0, 1, 'UTF-8'); ?></span><?php endif; ?>
                </span>
                <span class="ecod_wlist__main">
                    <b class="ecod_wlist__title"><?php if (ecod_has_password($ecodCatPosts)): ?><?php echo ecod_lock_icon(11, 'ecod_wlist__lock'); ?><?php endif; ?><?php $ecodCatPosts->title(); ?></b>
                    <span class="ecod_wlist__meta">
                        <time datetime="<?php $ecodCatPosts->date('Y-m-d'); ?>"><?php $ecodCatPosts->date('m-d'); ?></time>
                        <span><?php echo ecod_icon('eye', 11); ?><?php echo ecod_num_short(ecod_views($ecodCatPosts)); ?></span>
                    </span>
                </span>
            </a>
        <?php endwhile; ?>
    </div>
</section>
