<?php
/**
 * 文章页块 · AI 相关文章（F13）
 *
 * 数据来自 core/content/ai.php 的关键词打分：标签 + 标题切片（可被模型润色）
 * → 按「标题命中 3 分 / 正文命中 1 分」排序，命中不足时用同标签相关补齐。
 *
 * 与「相关推荐」的区别：这里是「关键词视角」，卡片上会标出命中的那个词，
 * 让读者知道为什么被推荐；两个模块可以并存，也可以只留一个。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/* 总开关关掉时模块即使被摆放也不渲染（回退不需要去管理台删模块） */
if ((string) ecod_option('EAIRelated', 'off') !== 'on') {
    return;
}

$ecodAiRelCount = max(1, min(6, (int) ecod_p('count', 3)));

$this->widget(
    'EcodPostQuery@airelated',
    'pageSize=' . $ecodAiRelCount . '&airelated=' . (int) $this->cid
)->to($ecodAiRel);

if (!$ecodAiRel->have()) {
    return;
}
?>
<section class="ecod_airel" aria-labelledby="ecod-airel-title">
    <?php ecod_block_head([
        'title_id' => 'ecod-airel-title',
        'note_raw' => '<span class="ecod_airel__hint">' . ecod_icon('bulb', 12) . '按关键词命中挑的</span>',
    ]); ?>

    <div class="ecod_airel__list">
        <?php while ($ecodAiRel->next()): ?>
            <?php
            $ecodAiWhy = isset($ecodAiRel->ai_why) ? trim((string) $ecodAiRel->ai_why) : '';
            $ecodAiCover = ecod_thumbnails($ecodAiRel, 1, 'side')[0] ?? '';
            ?>
            <a class="ecod_airel__item" href="<?php $ecodAiRel->permalink(); ?>">
                <span class="ecod_airel__body">
                    <b class="ecod_airel__title"><?php $ecodAiRel->title(); ?></b>
                    <span class="ecod_airel__meta">
                        <?php if ($ecodAiWhy !== ''): ?>
                            <em class="ecod_airel__why"><?php echo htmlspecialchars($ecodAiWhy); ?></em>
                        <?php endif; ?>
                        <time datetime="<?php $ecodAiRel->date('Y-m-d'); ?>"><?php $ecodAiRel->date('Y-m-d'); ?></time>
                        <span><?php echo ecod_icon('eye', 12); ?><?php echo ecod_num_short(ecod_views($ecodAiRel)); ?></span>
                        <span><?php echo ecod_icon('comment', 12); ?><?php echo ecod_comment_count((int) $ecodAiRel->cid); ?></span>
                    </span>
                </span>
                <span class="ecod_airel__cover"<?php if ($ecodAiCover): ?> style="--ecod-cover:url('<?php echo htmlspecialchars($ecodAiCover); ?>')"<?php endif; ?> aria-hidden="true">
                    <?php if (!$ecodAiCover): ?>
                        <span><?php echo htmlspecialchars(mb_substr((string) $ecodAiRel->title, 0, 1, 'UTF-8')); ?></span>
                    <?php endif; ?>
                </span>
            </a>
        <?php endwhile; ?>
    </div>
</section>
