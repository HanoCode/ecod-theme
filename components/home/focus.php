<?php
/**
 * 首页模块 · M7 焦点（CMS 双栏）
 *
 * @package Ecod Theme
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$this->widget('EcodPostQuery@focus', 'pageSize=' . max(3, (int) ecod_p('count', 6)))->to($ecodFocus);
if (!$ecodFocus->have()) {
    return;
}
$ecodFocusTitle = (string) ecod_p('title', '本期焦点');

/* 先收集数据，避免循环中拼不闭合的标签 */
$ecodFocusItems = [];
while ($ecodFocus->next()) {
    $ecodCover = ecod_thumbnails($ecodFocus, 1, 'banner');
    $ecodFocusItems[] = [
        'permalink' => (string) $ecodFocus->permalink,
        'title'     => (string) $ecodFocus->title,
        'date'      => date('Y-m-d', (int) $ecodFocus->created),
        'mdate'     => date('m-d', (int) $ecodFocus->created),
        'views'     => ecod_views($ecodFocus),
        'comments'  => (int) $ecodFocus->commentsNum,
        'cover'     => $ecodCover[0] ?? '',
    ];
}
$ecodLead = array_shift($ecodFocusItems);
?>
<section class="ecod_focus">
    <header class="ecod_section-head">
        <h2 class="ecod_section-head__title"><?php echo ecod_icon('zap', 15); ?><?php echo htmlspecialchars($ecodFocusTitle); ?></h2>
        <a class="ecod_section-head__more" href="<?php $this->options->siteUrl(); ?>">全部 <?php echo ecod_icon('chevron', 12); ?></a>
    </header>

    <div class="ecod_focus__grid">
        <a class="ecod_focus__lead" href="<?php echo htmlspecialchars($ecodLead['permalink']); ?>">
            <span class="ecod_focus__cover"<?php if ($ecodLead['cover']): ?> style="--ecod-cover:url('<?php echo htmlspecialchars($ecodLead['cover']); ?>')"<?php endif; ?>>
                <?php if (!$ecodLead['cover']): ?><span class="ecod_focus__ph"><?php echo htmlspecialchars(mb_substr($ecodLead['title'], 0, 1, 'UTF-8')); ?></span><?php endif; ?>
                <span class="ecod_focus__shade" aria-hidden="true"></span>
                <span class="ecod_focus__lead-body">
                    <b class="ecod_focus__lead-title"><?php echo htmlspecialchars($ecodLead['title']); ?></b>
                    <span class="ecod_focus__lead-meta">
                        <time datetime="<?php echo $ecodLead['date']; ?>"><?php echo $ecodLead['date']; ?></time>
                        <span><?php echo ecod_num_short((int) $ecodLead['views']); ?> 阅读</span>
                        <span><?php echo $ecodLead['comments']; ?> 评论</span>
                    </span>
                </span>
            </span>
        </a>

        <?php if ($ecodFocusItems): ?>
            <ol class="ecod_focus__list">
                <?php foreach ($ecodFocusItems as $i => $item): ?>
                    <li>
                        <a href="<?php echo htmlspecialchars($item['permalink']); ?>">
                            <span class="ecod_focus__no"><?php echo str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT); ?></span>
                            <span class="ecod_focus__item-title"><?php echo htmlspecialchars($item['title']); ?></span>
                            <time datetime="<?php echo $item['date']; ?>"><?php echo $item['mdate']; ?></time>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</section>
