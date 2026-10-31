<?php
/**
 * 侧栏块 · 最新评论
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$this->widget('\Widget\Comments\Recent', 'pageSize=' . max(1, (int) ecod_p('count', 5)))->to($ecodCmts);
?>
<section class="ecod_widget">
    <h2 class="ecod_widget__title"><?php echo htmlspecialchars((string) ecod_p('title', '最新评论')); ?></h2>
    <?php if ($ecodCmts->have()): ?>
        <div class="ecod_rcmt">
            <?php while ($ecodCmts->next()): ?>
                <a href="<?php $ecodCmts->permalink(); ?>" class="ecod_rcmt__item">
                    <span class="ecod_rcmt__avatar" aria-hidden="true"><img src="<?php echo htmlspecialchars(ecod_comment_avatar_url((string) $ecodCmts->mail), ENT_QUOTES, 'UTF-8'); ?>" alt="" width="40" height="40" loading="lazy" decoding="async"></span>
                    <span class="ecod_rcmt__body">
                        <span class="ecod_rcmt__row">
                            <b><?php echo htmlspecialchars($ecodCmts->author); ?></b>
                            <time><?php echo $ecodCmts->date('m-d'); ?></time>
                        </span>
                        <span class="ecod_rcmt__text"><?php echo htmlspecialchars(mb_substr(strip_tags($ecodCmts->text), 0, 30, 'UTF-8')); ?></span>
                    </span>
                </a>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p class="ecod_mini-empty">还没有评论，来抢沙发</p>
    <?php endif; ?>
</section>
