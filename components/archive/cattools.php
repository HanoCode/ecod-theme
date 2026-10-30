<?php
/**
 * 分类页工具条（F3）
 *
 * 挂在 E1 页头下方的一条 62px 工具条：左侧「在『分类名』中搜索」占 1fr
 * （GET ?q= 提交到当前分类地址，无 JS 也可用，/ 热键聚焦），右侧固定一枚
 * 排序按钮（复用 components/home/sort.php，与首页同一套浮层）。
 *
 * 分类页的 catbar tabs 在这里是重复导航（页头已表明当前分类），工具条启用时
 * 通过 $GLOBALS['ecodCatToolsDone'] 告知 home/list.php 隐藏整条 streambar。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

if (ecod_option('ECatTools', 'on') === 'off' || !$this->is('category')) {
    return;
}

$ecodCtSlug = '';
try {
    $ecodCtSlug = (string) $this->getArchiveSlug();
} catch (\Throwable $e) {
}
if ($ecodCtSlug === '') {
    return;
}

/* 分类基础地址（不带任何 query）：搜索表单的 action，提交即回第一页。
   注意 Metas 的 permalink() 是输出型方法（echo / 返回 void），这里走 Router
   拼接（同 core/site/catstats.php 的既有做法），拿得到返回值 */
try {
    $ecodCtUrl = (string) \Typecho\Router::url('category', ['slug' => $ecodCtSlug], \Widget\Options::alloc()->index);
} catch (\Throwable $e) {
    $ecodCtUrl = '';
}
if ($ecodCtUrl === '') {
    return;
}

$ecodCtName = '';
$ecodCtMeta = ecod_cat_meta($ecodCtSlug);
if ($ecodCtMeta !== null) {
    $ecodCtName = (string) ($ecodCtMeta['name'] ?? '');
}

$ecodCtQ = ecod_cat_search_q();

/* 告知 home/list.php：工具条已接管分类页，隐藏重复的 catbar streambar */
$GLOBALS['ecodCatToolsDone'] = true;
?>
<div class="ecod_cattools" data-ecod-cattools>
    <form class="ecod_cattools__search" method="get"
          action="<?php echo htmlspecialchars($ecodCtUrl, ENT_QUOTES, 'UTF-8'); ?>" role="search">
        <span class="ecod_cattools__icon" aria-hidden="true"><?php echo ecod_icon('search', 15); ?></span>
        <input type="search" name="q" value="<?php echo htmlspecialchars($ecodCtQ, ENT_QUOTES, 'UTF-8'); ?>"
               placeholder="在「<?php echo htmlspecialchars($ecodCtName, ENT_QUOTES, 'UTF-8'); ?>」中搜索"
               aria-label="在 <?php echo htmlspecialchars($ecodCtName, ENT_QUOTES, 'UTF-8'); ?> 分类内搜索"
               autocomplete="off">
        <kbd class="ecod_cattools__kbd" aria-hidden="true">/</kbd>
    </form>
    <?php $this->need('components/home/sort.php'); ?>
</div>
