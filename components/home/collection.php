<?php
/**
 * 首页模块 · 合集（按分类聚合的专题卡）
 *
 * @package Ecod Theme
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodCollLimit = max(1, min(6, (int) ecod_p('count', 3)));
$ecodCollMids = array_filter(array_map('intval', explode(',', (string) ecod_p('mids', ''))));

$this->widget('\Widget\Metas\Category\Rows')->to($ecodCollCats);
if (!$ecodCollCats->have()) {
    return;
}

$ecodCollItems = [];
$ecodTints = [
    'linear-gradient(135deg, #93a5f5, #c3b3f0 55%, #f0b6d8)',
    'linear-gradient(135deg, #7fc9f2, #93a5f5 55%, #c3b3f0)',
    'linear-gradient(135deg, #f2a9a0, #f2c894 55%, #f5e3a8)',
    'linear-gradient(135deg, #8fd8c0, #9fd0f0 55%, #c3b3f0)',
    'linear-gradient(135deg, #f0a8c8, #c3a0f0 55%, #93a5f5)',
    'linear-gradient(135deg, #a8d8a0, #9fe0d0 55%, #c0ecf0)',
];

while ($ecodCollCats->next()) {
    $ecodCollMid = (int) $ecodCollCats->mid;
    if ($ecodCollMids && !in_array($ecodCollMid, $ecodCollMids, true)) {
        continue;
    }
    if (count($ecodCollItems) >= $ecodCollLimit) {
        break;
    }

    ob_start();
    $ecodCollCats->permalink();
    $ecodCollUrl = trim((string) ob_get_clean());

    $ecodCollCover = '';
    $ecodCollLatest = '';
    $this->widget('EcodPostQuery@coll' . $ecodCollMid, 'pageSize=1&category=' . $ecodCollMid)->to($ecodCollQ);
    if ($ecodCollQ->have()) {
        $ecodCollQ->next();
        $ecodCollCover = ecod_thumbnails($ecodCollQ, 1, 'cover')[0] ?? '';
        $ecodCollLatest = (string) $ecodCollQ->title;
    }

    $ecodCollItems[] = [
        'mid'    => $ecodCollMid,
        'name'   => (string) $ecodCollCats->name,
        'count'  => (int) $ecodCollCats->count,
        'url'    => $ecodCollUrl,
        'desc'   => trim((string) $ecodCollCats->description),
        'cover'  => $ecodCollCover,
        'latest' => $ecodCollLatest,
    ];
}

if (!$ecodCollItems) {
    return;
}
?>
<section class="ecod_section">
    <header class="ecod_section-head">
        <h2 class="ecod_section-head__title">
            <span class="ecod_section-head__dot" aria-hidden="true"></span><?php echo htmlspecialchars((string) ecod_p('title', '合集')); ?>
        </h2>
    </header>

    <div class="ecod_coll">
        <?php foreach ($ecodCollItems as $ecodCollI => $ecodColl): ?>
            <a class="ecod_coll__card" href="<?php echo htmlspecialchars($ecodColl['url'], ENT_QUOTES, 'UTF-8'); ?>">
                <span class="ecod_coll__cover" style="background-image:<?php echo $ecodColl['cover'] !== ''
                    ? "url('" . htmlspecialchars($ecodColl['cover'], ENT_QUOTES, 'UTF-8') . "')"
                    : $ecodTints[$ecodCollI % count($ecodTints)]; ?>">
                    <span class="ecod_coll__shade" aria-hidden="true"></span>
                    <?php if ($ecodColl['cover'] === ''): ?>
                        <span class="ecod_coll__ph" aria-hidden="true"><?php echo htmlspecialchars(mb_substr($ecodColl['name'], 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                    <span class="ecod_coll__badge"><?php echo $ecodColl['count']; ?> 篇</span>
                </span>
                <span class="ecod_coll__body">
                    <b class="ecod_coll__name"><?php echo htmlspecialchars($ecodColl['name'], ENT_QUOTES, 'UTF-8'); ?></b>
                    <?php if ($ecodColl['desc'] !== ''): ?>
                        <small class="ecod_coll__desc"><?php echo htmlspecialchars($ecodColl['desc'], ENT_QUOTES, 'UTF-8'); ?></small>
                    <?php endif; ?>
                    <?php if ($ecodColl['latest'] !== ''): ?>
                        <span class="ecod_coll__latest">最新：<?php echo htmlspecialchars($ecodColl['latest'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
