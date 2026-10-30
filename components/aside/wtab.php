<?php
/**
 * 侧栏块 · 热门 Tab 组
 *
 * 热门（阅读量）/ 热评（评论数）/ 最新 / 随机 四合一选项卡——
 * JustNews 侧栏同款形态，四个独立挂件合并成一个，省侧栏纵向空间。
 * 服务端一次渲染四个版面，前端只做显隐切换（无额外请求）。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR')) {
    exit;
}

$ecodWtabCount = max(3, min(15, (int) ecod_p('count', 8)));
$ecodWtabDays = max(0, (int) ecod_p('days', 0));

$ecodWtabDefs = [];
if ((int) ecod_p('hot', 1) === 1) {
    $ecodWtabDefs['views'] = ['热门', 'views'];
}
if ((int) ecod_p('hotc', 1) === 1) {
    $ecodWtabDefs['hot'] = ['热评', 'hot'];
}
if ((int) ecod_p('recent', 1) === 1) {
    $ecodWtabDefs['recent'] = ['最新', 'recent'];
}
if ((int) ecod_p('rand', 1) === 1) {
    $ecodWtabDefs['rand'] = ['随机', 'rand'];
}
if (!$ecodWtabDefs) {
    return;
}

/* 一次渲染四个版面：每版一条独立查询，alias 区分实例避免 Widget 栈串行 */
$ecodWtabPanes = [];
$ecodWtabFirst = '';
foreach ($ecodWtabDefs as $ecodWtabKey => $ecodWtabDef) {
    $ecodWtabQuery = 'pageSize=' . $ecodWtabCount . '&order=' . $ecodWtabDef[1];
    if ($ecodWtabDays > 0) {
        $ecodWtabQuery .= '&days=' . $ecodWtabDays;
    }
    $this->widget('EcodPostQuery@wtab-' . $ecodWtabKey, $ecodWtabQuery)->to($ecodWtabQ);
    $ecodWtabRows = [];
    while ($ecodWtabQ->next()) {
        $ecodWtabRows[] = [
            'title'    => (string) $ecodWtabQ->title,
            'url'      => (string) $ecodWtabQ->permalink,
            'views'    => ecod_views($ecodWtabQ),
            'comments' => (int) $ecodWtabQ->commentsNum,
            'locked'   => ecod_has_password($ecodWtabQ),
        ];
    }
    if (!$ecodWtabRows) {
        continue;
    }
    $ecodWtabPanes[$ecodWtabKey] = $ecodWtabRows;
    if ($ecodWtabFirst === '') {
        $ecodWtabFirst = $ecodWtabKey;
    }
}
if (!$ecodWtabPanes) {
    return;
}
?>
<section class="ecod_widget ecod_wtab" data-ecod-wtabs>
    <h2 class="ecod_widget__title"><?php echo htmlspecialchars((string) ecod_p('title', '文章看看')); ?></h2>
    <div class="ecod_wtab__tabs" role="tablist">
        <?php $ecodWtabI = 0; ?>
        <?php foreach ($ecodWtabPanes as $ecodWtabKey => $ecodWtabRows): ?>
            <button type="button" role="tab" class="ecod_wtab__tab<?php echo $ecodWtabI === 0 ? ' is-active' : ''; ?>"
                    data-ecod-wtab="<?php echo $ecodWtabKey; ?>" aria-selected="<?php echo $ecodWtabI === 0 ? 'true' : 'false'; ?>"><?php
                        echo htmlspecialchars($ecodWtabDefs[$ecodWtabKey][0], ENT_QUOTES, 'UTF-8');
                    ?></button>
            <?php $ecodWtabI++; ?>
        <?php endforeach; ?>
    </div>
    <?php $ecodWtabI = 0; ?>
    <?php foreach ($ecodWtabPanes as $ecodWtabKey => $ecodWtabRows): ?>
        <div class="ecod_wlist ecod_wtab__pane<?php echo $ecodWtabI === 0 ? ' is-active' : ''; ?>"
             data-ecod-wtab-pane="<?php echo $ecodWtabKey; ?>"<?php echo $ecodWtabI === 0 ? '' : ' hidden'; ?>>
            <?php $ecodWtabRank = 0; ?>
            <?php foreach ($ecodWtabRows as $ecodWtabRow): ?>
                <?php $ecodWtabRank++; ?>
                <a class="ecod_wlist__item" href="<?php echo htmlspecialchars($ecodWtabRow['url'], ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="ecod_wtab__rank ecod_wtab__rank--<?php echo $ecodWtabRank <= 3 ? 'top' : 'rest'; ?>" aria-hidden="true"><?php echo $ecodWtabRank; ?></span>
                    <span class="ecod_wlist__main">
                        <b class="ecod_wlist__title"><?php
                            if ($ecodWtabRow['locked']) {
                                echo ecod_lock_icon(11, 'ecod_wlist__lock');
                            }
                            echo htmlspecialchars($ecodWtabRow['title'], ENT_QUOTES, 'UTF-8');
                        ?></b>
                        <span class="ecod_wlist__meta">
                            <?php if ($ecodWtabKey === 'hot'): ?>
                                <?php echo ecod_icon('comment', 11); ?><?php echo ecod_num_short($ecodWtabRow['comments']); ?>
                            <?php else: ?>
                                <?php echo ecod_icon('eye', 11); ?><?php echo ecod_num_short($ecodWtabRow['views']); ?>
                            <?php endif; ?>
                        </span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php $ecodWtabI++; ?>
    <?php endforeach; ?>
</section>
