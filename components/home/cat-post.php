<?php
/**
 * 首页模块 · M8 分类精选（取第一个分类）
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

/* 取指定分类；mid 为 0 时取第一个 */
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

$this->widget('EcodPostQuery@catpost', 'pageSize=' . max(1, (int) ecod_p('count', 3)) . '&category=' . $ecodCatMid)->to($ecodCatPosts);
if (!$ecodCatPosts->have()) {
    return;
}
?>
<section class="ecod_section">
    <?php ecod_block_head([
        'title'     => (string) ecod_p('title', '') === '' ? $ecodCatName : null,
        'more_url'  => $ecodCatUrl,
        'more_text' => '全部',
    ]); ?>

    <div class="ecod_section__grid">
        <?php while ($ecodCatPosts->next()): ?>
            <?php $ecodCatImg = ecod_thumbnails($ecodCatPosts, 1, 'side')[0] ?? ''; ?>
            <a class="ecod_section__card" href="<?php $ecodCatPosts->permalink(); ?>">
                <span class="ecod_section__cover"<?php if ($ecodCatImg): ?> style="--ecod-cover:url('<?php echo htmlspecialchars($ecodCatImg); ?>')"<?php endif; ?>>
                    <?php if (!$ecodCatImg): ?><span class="ecod_section__ph"><?php echo mb_substr($ecodCatPosts->title, 0, 1, 'UTF-8'); ?></span><?php endif; ?>
                </span>
                <span class="ecod_section__body">
                    <b class="ecod_section__title"><?php $ecodCatPosts->title(); ?></b>
                    <span class="ecod_section__meta">
                        <time datetime="<?php $ecodCatPosts->date('Y-m-d'); ?>"><?php $ecodCatPosts->date('m-d'); ?></time>
                        <span><?php echo ecod_icon('eye', 12); ?><?php echo ecod_num_short(ecod_views($ecodCatPosts)); ?></span>
                    </span>
                </span>
            </a>
        <?php endwhile; ?>
    </div>
</section>
