<?php
/**
 * 归档：分类 / 标签 / 作者 / 搜索 / 日期
 *
 * @package Ecod Theme
 * @author  Ecod
 * @since   2026-10-29
 * @version 2.0.0
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
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
    <?php /* 模块 · 内容区上方（模块管理台） */ ecod_widgets_render("top_content", $this); ?>

<?php if ($this->is('category')): ?>
    <?php /* 分类页头（E1）：名称 / 描述 / 三个统计 / 相关专题；关掉或取不到分类时退回屏幕阅读器标题 */ ?>
    <?php $this->need('components/archive/head.php'); ?>
    <?php if (empty($GLOBALS['ecodCatHeadDone'])): ?>
        <h1 class="ecod_sr"><?php $this->archiveTitle(['category' => _t('分类：%s')], '', ''); ?></h1>
    <?php endif; ?>
    <?php /* 分类工具条（F3）：分类内搜索 + 排序按钮；启用时接管 catbar */ ?>
    <?php $this->need('components/archive/cattools.php'); ?>
    <?php /* 分类归档复用首页文章流：tab 条 + 列表 + 分页，支持无刷新切换 */ ?>
    <?php $this->need('components/home/list.php'); ?>
<?php else: ?>
    <?php /* 标签 / 作者 / 搜索 / 日期归档：可见面包屑（SEO JSON-LD 里已有同源数据） */ ?>
    <?php ob_start();
    $this->archiveTitle([
        'search' => _t('搜索：%s'),
        'tag'    => _t('标签：%s'),
        'author' => _t('%s 的文章'),
    ], '', '');
    $ecodArcCrumb = trim((string) ob_get_clean()); ?>
    <nav class="ecod_crumb" aria-label="面包屑">
        <a class="ecod_crumb__back" href="javascript:history.back()" aria-label="返回"><?php echo ecod_icon('chevron', 13); ?></a>
        <a href="<?php $this->options->siteUrl(); ?>"><?php echo ecod_icon('home', 12); ?>首页</a>
        <span class="ecod_crumb__sep" aria-hidden="true">/</span>
        <span aria-current="page"><?php echo $ecodArcCrumb !== '' ? $ecodArcCrumb : '归档'; ?></span>
    </nav>

    <?php if ($this->is('author')): ?>
        <?php /* 公开作者主页头卡：头像 / 用户组 / 简介 / 统计（components/archive/author.php） */ ?>
        <?php $this->need('components/archive/author.php'); ?>
        <?php if (empty($GLOBALS['ecodAuthorHeadDone'])): ?>
            <h1 class="ecod_sr"><?php $this->archiveTitle(['author' => _t('作者：%s')], '', ''); ?></h1>
        <?php endif; ?>
    <?php else: ?>
        <header class="ecod_pagehead">
            <h1>
                <?php $this->archiveTitle([
                    'search' => _t('搜索：%s'),
                    'tag'    => _t('标签：%s'),
                ], '', ''); ?>
            </h1>
            <p><?php echo (int) $this->getTotal(); ?> 篇文章<?php if ($this->is('search')): ?> · 关键词「<?php $this->keywords(); ?>」<?php endif; ?></p>
        </header>
    <?php endif; ?>

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

    <?php ob_start(); $this->pageNav('‹', '›', 2, '…', [
        'wrapTag'      => 'div',
        'wrapClass'    => 'ecod_pager',
        'itemTag'      => '',
        'textTag'      => 'span',
        'currentClass' => 'is-current',
    ]); echo ecod_sort_pager((string) ob_get_clean()); ?>
<?php endif; ?>


    <?php /* 模块 · 内容区下方（模块管理台） */ ecod_widgets_render("bottom_content", $this); ?>
</main>

<?php $this->need('public/aside.php'); ?>
<?php $this->need('public/footer.php'); ?>
