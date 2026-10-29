<?php
/**
 * 文章页块 · 上下篇导航（带封面底图）
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 * @license   MIT
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/* 与 Typecho thePrev / theNext 相同的筛选条件，额外取封面与日期 */
$ecodNavPrevQuery = $this->select()
    ->where('table.contents.created < ?', $this->created)
    ->where('table.contents.status = ?', 'publish')
    ->where('table.contents.type = ?', $this->type)
    ->where("table.contents.password IS NULL OR table.contents.password = ''")
    ->order('table.contents.created', \Typecho\Db::SORT_DESC)
    ->limit(1);

$ecodNavNextQuery = $this->select()
    ->where('table.contents.created > ? AND table.contents.created < ?', $this->created, $this->options->time)
    ->where('table.contents.status = ?', 'publish')
    ->where('table.contents.type = ?', $this->type)
    ->where("table.contents.password IS NULL OR table.contents.password = ''")
    ->order('table.contents.created', \Typecho\Db::SORT_ASC)
    ->limit(1);

$ecodNavFetch = static function ($query, string $alias): ?array {
    $obj = \Widget\Contents\From::allocWithAlias($alias, ['query' => $query]);
    if (!$obj->have()) {
        return null;
    }
    $obj->next();

    ob_start();
    $obj->date('m-d');
    $date = trim((string) ob_get_clean());

    ob_start();
    $obj->title();
    $title = trim((string) ob_get_clean());

    return [
        'url'   => (string) $obj->permalink,
        'title' => $title,
        'date'  => $date,
        'cover' => ecod_thumbnails($obj, 1, 'side')[0] ?? '',
    ];
};

$ecodNavPrev = $ecodNavFetch($ecodNavPrevQuery, 'ecodnavprev:' . $this->cid);
$ecodNavNext = $ecodNavFetch($ecodNavNextQuery, 'ecodnavnext:' . $this->cid);
?>
<nav class="ecod_postnav" aria-label="相邻文章">
    <?php if ($ecodNavPrev): ?>
        <a class="ecod_postnav__card is-prev<?php echo $ecodNavPrev['cover'] !== '' ? ' has-cover' : ''; ?>"
           href="<?php echo htmlspecialchars($ecodNavPrev['url'], ENT_QUOTES, 'UTF-8'); ?>"
           <?php if ($ecodNavPrev['cover'] !== ''): ?>style="--ecod-cover:url('<?php echo htmlspecialchars($ecodNavPrev['cover'], ENT_QUOTES, 'UTF-8'); ?>')"<?php endif; ?>>
            <span class="ecod_postnav__shade" aria-hidden="true"></span>
            <span class="ecod_postnav__label"><?php echo ecod_icon('chevron', 11, 'ecod_postnav__icon is-flip'); ?>上一篇 · <?php echo htmlspecialchars($ecodNavPrev['date'], ENT_QUOTES, 'UTF-8'); ?></span>
            <b class="ecod_postnav__title"><?php echo htmlspecialchars($ecodNavPrev['title'], ENT_QUOTES, 'UTF-8'); ?></b>
        </a>
    <?php else: ?>
        <div class="ecod_postnav__card is-prev is-empty">
            <span class="ecod_postnav__label">上一篇</span>
            <b class="ecod_postnav__title">已经是第一篇了</b>
        </div>
    <?php endif; ?>

    <?php if ($ecodNavNext): ?>
        <a class="ecod_postnav__card is-next<?php echo $ecodNavNext['cover'] !== '' ? ' has-cover' : ''; ?>"
           href="<?php echo htmlspecialchars($ecodNavNext['url'], ENT_QUOTES, 'UTF-8'); ?>"
           <?php if ($ecodNavNext['cover'] !== ''): ?>style="--ecod-cover:url('<?php echo htmlspecialchars($ecodNavNext['cover'], ENT_QUOTES, 'UTF-8'); ?>')"<?php endif; ?>>
            <span class="ecod_postnav__shade" aria-hidden="true"></span>
            <span class="ecod_postnav__label">下一篇 · <?php echo htmlspecialchars($ecodNavNext['date'], ENT_QUOTES, 'UTF-8'); ?><?php echo ecod_icon('chevron', 11, 'ecod_postnav__icon'); ?></span>
            <b class="ecod_postnav__title"><?php echo htmlspecialchars($ecodNavNext['title'], ENT_QUOTES, 'UTF-8'); ?></b>
        </a>
    <?php else: ?>
        <div class="ecod_postnav__card is-next is-empty">
            <span class="ecod_postnav__label">下一篇</span>
            <b class="ecod_postnav__title">已经是最新一篇了</b>
        </div>
    <?php endif; ?>
</nav>
