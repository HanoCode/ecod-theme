<?php
/**
 * 归档：分类 / 标签 / 作者 / 搜索 / 日期
 *
 * @package Ecod Theme
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$this->need('public/header.php');

/* 搜索页：把关键词交给卡片组件做标题高亮与命中片段 */
$GLOBALS['ecodSearchKw'] = '';
$GLOBALS['ecodSearchTerms'] = [];
if ($this->is('search')) {
    try {
        $GLOBALS['ecodSearchKw'] = (string) $this->request->filter('url', 'search')->get('keywords', '');
        $GLOBALS['ecodSearchTerms'] = ecod_search_terms($GLOBALS['ecodSearchKw']);
    } catch (\Throwable $e) {
    }
}
?>
<main class="ecod_main" id="ecod-main">

<?php if ($this->is('category')): ?>
    <?php /* 分类归档复用首页文章流：tab 条 + 列表 + 分页，支持无刷新切换 */ ?>
    <h1 class="ecod_sr"><?php $this->archiveTitle(['category' => _t('分类：%s')], '', ''); ?></h1>
    <?php $this->need('components/home/list.php'); ?>
<?php else: ?>
    <header class="ecod_pagehead">
        <h1>
            <?php $this->archiveTitle([
                'category' => _t('分类：%s'),
                'search'   => _t('搜索：%s'),
                'tag'      => _t('标签：%s'),
                'author'   => _t('作者：%s'),
            ], '', ''); ?>
        </h1>
        <p><?php echo (int) $this->getTotal(); ?> 篇文章<?php if ($this->is('search')): ?> · 关键词「<?php $this->keywords(); ?>」<?php endif; ?></p>
    </header>

    <?php /* 容器与卡片同源：避免网格容器里混入横向卡导致挤压 */ ?>
    <?php $ecodArchiveMode = ecod_list_force_mode(); ?>
    <div class="<?php echo ecod_list_class($ecodArchiveMode); ?>" data-ecod-infinite>
        <?php if ($this->have()): ?>
            <?php if ($this->is('search') && count($GLOBALS['ecodSearchTerms']) > 1): ?>
                <div class="ecod_sugg__row ecod_sugg__row--top">
                    <span class="ecod_sugg__label"><?php echo ecod_icon('list', 13); ?>按词查找：</span>
                    <?php foreach ($GLOBALS['ecodSearchTerms'] as $ecodTerm): ?>
                        <a class="ecod_sugg__chip" href="<?php echo \Typecho\Router::url('search', ['keywords' => $ecodTerm], $this->options->index); ?>"><?php echo htmlspecialchars($ecodTerm, ENT_QUOTES, 'UTF-8'); ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php while ($this->next()): ?>
                <?php ecod_card($this, $ecodArchiveMode); ?>
            <?php endwhile; ?>
        <?php else: ?>
            <?php if ($this->is('search')): ?>
                <?php ecod_search_empty($this); ?>
            <?php else: ?>
                <div class="ecod_empty">
                    <div class="ecod_empty__icon" aria-hidden="true"><?php echo ecod_icon('inbox', 30); ?></div>
                    <h3>这里还没有内容</h3>
                    <p>换个条件试试，或返回首页</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php $this->pageNav('‹', '›', 2, '…', [
        'wrapTag'      => 'div',
        'wrapClass'    => 'ecod_pager',
        'itemTag'      => '',
        'textTag'      => 'span',
        'currentClass' => 'is-current',
    ]); ?>
<?php endif; ?>

</main>

<?php $this->need('public/aside.php'); ?>
<?php $this->need('public/footer.php'); ?>
