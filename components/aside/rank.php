<?php
/**
 * 侧栏块 · 排行榜（缩略图 + 名次徽章，可切总榜/周榜/月榜）
 *
 * @package Ecod Theme
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodRankCount = max(3, min(10, (int) ecod_p('count', 5)));
$ecodRankBy = (string) ecod_p('by', 'views') === 'hot' ? 'hot' : 'views';
$ecodRankRange = (string) ecod_p('range', 'week');
$ecodRankGroups = [
    'all'   => ['label' => '总榜', 'days' => 0],
    'week'  => ['label' => '周榜', 'days' => 7],
    'month' => ['label' => '月榜', 'days' => 30],
];
if (!isset($ecodRankGroups[$ecodRankRange])) {
    $ecodRankRange = 'week';
}
$ecodRankTabs = (int) ecod_p('tabs', 1) === 1;
$ecodRankKeys = $ecodRankTabs ? array_keys($ecodRankGroups) : [$ecodRankRange];
/* 同一页可能出现多个排行卡，id 需要实例级唯一，否则 aria-controls 会串指 */
$ecodRankUid = 'c' . substr(md5(uniqid('ecod-rank', true)), 0, 6);

$ecodRankData = [];
foreach ($ecodRankKeys as $ecodRankKey) {
    $this->widget(
        'EcodPostQuery@rank' . $ecodRankKey,
        'pageSize=' . $ecodRankCount . '&order=' . $ecodRankBy . '&days=' . $ecodRankGroups[$ecodRankKey]['days']
    )->to($ecodRankQ);

    $ecodRankRows = [];
    while ($ecodRankQ->next()) {
        $ecodRankRows[] = [
            'title'    => (string) $ecodRankQ->title,
            'url'      => (string) $ecodRankQ->permalink,
            'thumb'    => ecod_thumbnails($ecodRankQ, 1, 'side')[0] ?? '',
            'views'    => ecod_views($ecodRankQ),
            'comments' => (int) $ecodRankQ->commentsNum,
            'locked'   => ecod_has_password($ecodRankQ),
        ];
    }
    $ecodRankData[$ecodRankKey] = $ecodRankRows;
}

if (!array_filter($ecodRankData)) {
    return;
}
?>
<section class="ecod_widget ecod_rankcard">
    <h2 class="ecod_widget__title"><?php echo htmlspecialchars((string) ecod_p('title', '热门排行')); ?></h2>

    <?php if ($ecodRankTabs): ?>
        <div class="ecod_rankcard__tabs" role="tablist">
            <?php foreach ($ecodRankGroups as $ecodRankId => $ecodRankMeta): ?>
                <button type="button"
                        class="ecod_rankcard__tab<?php echo $ecodRankId === $ecodRankRange ? ' is-active' : ''; ?>"
                        data-ecod-rank-tab="<?php echo $ecodRankId; ?>"
                        id="ecod-rank-tab-<?php echo $ecodRankUid . '-' . $ecodRankId; ?>"
                        aria-controls="ecod-rank-panel-<?php echo $ecodRankUid . '-' . $ecodRankId; ?>"
                        role="tab"
                        aria-selected="<?php echo $ecodRankId === $ecodRankRange ? 'true' : 'false'; ?>"
                        tabindex="<?php echo $ecodRankId === $ecodRankRange ? '0' : '-1'; ?>">
                    <?php echo $ecodRankMeta['label']; ?>
                </button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php foreach ($ecodRankData as $ecodRankId => $ecodRankRows): ?>
        <div class="ecod_rankcard__panel<?php echo $ecodRankId === $ecodRankRange ? ' is-active' : ''; ?>"
             data-ecod-rank-panel="<?php echo $ecodRankId; ?>"
             id="ecod-rank-panel-<?php echo $ecodRankUid . '-' . $ecodRankId; ?>"
             role="tabpanel"
             aria-labelledby="ecod-rank-tab-<?php echo $ecodRankUid . '-' . $ecodRankId; ?>">
            <?php if (!$ecodRankRows): ?>
                <p class="ecod_mini-empty">该周期还没有文章</p>
            <?php else: ?>
                <ol class="ecod_rankcard__list">
                    <?php foreach ($ecodRankRows as $ecodRankI => $ecodRankRow): ?>
                        <li>
                            <a href="<?php echo htmlspecialchars($ecodRankRow['url'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="ecod_rankcard__no<?php echo $ecodRankI < 3 ? ' is-top' : ''; ?>"><?php echo $ecodRankI + 1; ?></span>
                                <span class="ecod_rankcard__thumb">
                                    <?php if ($ecodRankRow['thumb'] !== ''): ?>
                                        <img src="<?php echo htmlspecialchars($ecodRankRow['thumb'], ENT_QUOTES, 'UTF-8'); ?>"
                                             alt="" width="48" height="48" loading="lazy" decoding="async" data-ecod-img>
                                    <?php else: ?>
                                        <i aria-hidden="true"><?php echo htmlspecialchars(mb_substr($ecodRankRow['title'], 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></i>
                                    <?php endif; ?>
                                </span>
                                <span class="ecod_rankcard__body">
                                    <b class="ecod_rankcard__title">
                                        <?php if ($ecodRankRow['locked']): ?><?php echo ecod_lock_icon(11, 'ecod_rankcard__lock'); ?><?php endif; ?><?php echo htmlspecialchars($ecodRankRow['title'], ENT_QUOTES, 'UTF-8'); ?>
                                    </b>
                                    <small class="ecod_rankcard__meta">
                                        <?php echo ecod_icon('eye', 11); ?><?php echo ecod_num_short($ecodRankRow['views']); ?>
                                        <span><?php echo ecod_icon('comment', 11); ?><?php echo $ecodRankRow['comments']; ?></span>
                                    </small>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</section>
