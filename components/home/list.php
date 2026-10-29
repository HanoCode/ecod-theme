<?php
/**
 * 首页块 · 正文流
 *
 * @package Ecod Theme
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$ecodShowTabs = (int) ecod_p('tabs', 1) === 1;
/* 当前是否处于分类归档页（用于高亮对应 tab） */
$ecodCurCat = $this->is('category') ? (string) $this->getArchiveSlug() : '';

/* 版式：容器与卡片必须同源。列表版式决定整列卡片形态，不再理会文章自定义 mode，
   避免形态与容器不匹配（卡片被压成窄条、标题竖排） */
$ecodLayout = (string) ecod_p('layout', 'default');
$ecodLayoutMode = ecod_list_force_mode($ecodLayout);
$ecodListClass = ecod_list_class($ecodLayoutMode);
?>
<section class="ecod_stream" id="ecod-stream">
    <div class="ecod_streambar">
        <?php if ($ecodShowTabs): ?>
            <div class="ecod_catbar" data-ecod-catbar>
                <ul class="ecod_tabs">
                    <li<?php echo $ecodCurCat === '' ? ' class="is-active"' : ''; ?>><a href="<?php $this->options->siteUrl(); ?>">最新</a></li>
                    <?php $this->widget('\Widget\Metas\Category\Rows')->to($ecodCats); ?>
                    <?php $ecodTabI = 0; ?>
                    <?php while ($ecodCats->next()): ?>
                        <?php if ((int) $ecodCats->parent !== 0) { continue; } ?>
                        <?php if (++$ecodTabI > 6) { break; } ?>
                        <li<?php echo $ecodCurCat !== '' && $ecodCats->slug === $ecodCurCat ? ' class="is-active"' : ''; ?>><a href="<?php $ecodCats->permalink(); ?>"><?php $ecodCats->name(); ?></a></li>
                    <?php endwhile; ?>
                </ul>
                <button type="button" class="ecod_catbar__more" data-ecod-catbar-more aria-expanded="false">更多</button>
            </div>
        <?php else: ?>
            <span class="ecod_streambar__note">最新文章</span>
        <?php endif; ?>
        <span class="ecod_streambar__note">共 <?php echo (int) $this->getTotal(); ?> 篇</span>
    </div>

    <div class="<?php echo $ecodListClass; ?>" data-ecod-infinite>
        <?php if ($this->have()): ?>
            <?php while ($this->next()): ?>
                <?php ecod_card($this, $ecodLayoutMode); ?>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="ecod_empty">
                <div class="ecod_empty__icon" aria-hidden="true"><?php echo ecod_icon('inbox', 30); ?></div>
                <h3>还没有文章</h3>
                <p>去后台写第一篇吧</p>
            </div>
        <?php endif; ?>
    </div>

    <?php $this->pageNav('‹', '›', 2, '…', [
        'wrapTag'      => 'div',
        'wrapClass'    => 'ecod_pager',
        'itemTag'      => '',
        'textTag'      => 'span',
        'currentClass' => 'is-current',
    ]); ?>
</section>
