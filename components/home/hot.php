<?php
/**
 * 首页模块 · M5 热门四宫格
 *
 * @package Ecod Theme
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$ecodHotSort = (string) ecod_p('sort', 'comments') === 'views' ? 'views' : 'hot';
$this->widget('EcodPostQuery@hot', 'pageSize=' . max(2, (int) ecod_p('count', 4)) . '&order=' . $ecodHotSort)->to($ecodHot);
if (!$ecodHot->have()) {
    return;
}
$ecodHotTitle = (string) ecod_p('title', '热门');
$ecodHotNote = $ecodHotSort === 'views' ? '按阅读量' : '按讨论热度';
?>
<section class="ecod_hot" aria-label="热门文章">
    <div class="ecod_hot__head">
        <h2 class="ecod_hot__title"><?php echo ecod_icon('zap', 15); ?><?php echo htmlspecialchars($ecodHotTitle); ?></h2>
        <span class="ecod_hot__note"><?php echo $ecodHotNote; ?></span>
    </div>
    <div class="ecod_hot__grid">
        <?php $ecodHotIndex = 1; while ($ecodHot->next()): ?>
            <?php $ecodHotImg = ecod_thumbnails($ecodHot, 1, 'side')[0] ?? ''; ?>
            <a class="ecod_hot__item" href="<?php $ecodHot->permalink(); ?>">
                <span class="ecod_hot__cover"<?php if ($ecodHotImg): ?> style="--ecod-cover:url('<?php echo htmlspecialchars($ecodHotImg); ?>')"<?php endif; ?>>
                    <?php if (!$ecodHotImg): ?><span class="ecod_hot__ph"><?php echo mb_substr($ecodHot->title, 0, 1, 'UTF-8'); ?></span><?php endif; ?>
                    <span class="ecod_hot__rank"><?php echo $ecodHotIndex; ?></span>
                </span>
                <b class="ecod_hot__text"><?php if (ecod_has_password($ecodHot)): ?><?php echo ecod_lock_icon(11, 'ecod_hot__lock'); ?><?php endif; ?><?php $ecodHot->title(); ?></b>
                <span class="ecod_hot__meta"><?php echo $ecodHotSort === 'views' ? ecod_num_short((int) $ecodHot->views) . ' 阅读' : (int) $ecodHot->commentsNum . ' 评论'; ?> · <?php $ecodHot->date('m-d'); ?></span>
            </a>
            <?php $ecodHotIndex++; ?>
        <?php endwhile; ?>
    </div>
</section>
