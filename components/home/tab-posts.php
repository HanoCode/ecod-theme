<?php
/**
 * 首页/侧栏模块 · 聚合 Tab 卡（参考 zibll 聚合文章卡）
 *
 * 多个分类作为 Tab，全部 Tab 一次性预渲染，切换纯前端 display、无二次请求。
 * 空 Tab 自动隐藏；全部为空时整个模块不输出。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodTpMids = array_slice(array_filter(array_map('intval', explode(',', (string) ecod_p('mids', '')))), 0, 5);
if (!$ecodTpMids) {
    return;
}

$ecodTpCount = max(2, min(8, (int) ecod_p('count', 4)));
$ecodTpCols = (string) ecod_p('cols', '2') === '1' ? '1' : '2';
$ecodTpShowMeta = (int) ecod_p('meta', 1) === 1;

/* 分类名（Tab 文案）；按声明顺序查询，忽略不存在的 mid */
try {
    $ecodTpDb = \Typecho\Db::get();
    $ecodTpNameRows = $ecodTpDb->fetchAll(
        $ecodTpDb->select('mid', 'name')->from('table.metas')
            ->where('type = ?', 'category')
            ->where('mid IN (' . implode(',', $ecodTpMids) . ')')
    );
} catch (\Throwable $e) {
    $ecodTpNameRows = [];
}
$ecodTpNames = [];
foreach ($ecodTpNameRows as $ecodTpNameRow) {
    $ecodTpNames[(int) $ecodTpNameRow['mid']] = (string) $ecodTpNameRow['name'];
}

/* 逐 Tab 预渲染数据；分类无文章或 mid 不存在时跳过 */
$ecodTpPanes = [];
foreach ($ecodTpMids as $ecodTpMid) {
    if (!isset($ecodTpNames[$ecodTpMid])) {
        continue;
    }
    $ecodTpRows = [];
    $this->widget('EcodPostQuery@tp' . $ecodTpMid, 'pageSize=' . $ecodTpCount . '&category=' . $ecodTpMid)->to($ecodTpQ);
    while ($ecodTpQ->next()) {
        $ecodTpRows[] = [
            'title'    => (string) $ecodTpQ->title,
            'url'      => (string) $ecodTpQ->permalink,
            'thumb'    => ecod_thumbnails($ecodTpQ, 1, 'side')[0] ?? '',
            'views'    => ecod_views($ecodTpQ),
            'comments' => (int) $ecodTpQ->commentsNum,
            'locked'   => ecod_has_password($ecodTpQ),
        ];
    }
    if ($ecodTpRows) {
        $ecodTpPanes[$ecodTpMid] = $ecodTpRows;
    }
}
if (!$ecodTpPanes) {
    return;
}

$ecodTpUid = 'tp' . substr(md5(uniqid('ecod-tp', true)), 0, 6);
$ecodTpFirst = array_key_first($ecodTpPanes);
?>
<section class="ecod_widget ecod_tp" data-cols="<?php echo $ecodTpCols; ?>">
    <?php ecod_block_head(); ?>
    <div class="ecod_tp__hd" role="tablist">
        <?php $ecodTpI = 0; foreach ($ecodTpPanes as $ecodTpMid => $ecodTpRows): $ecodTpI++; ?>
            <button type="button" role="tab"
                    class="ecod_tp__tab<?php echo $ecodTpMid === $ecodTpFirst ? ' is-active' : ''; ?>"
                    data-ecod-tp-tab="<?php echo htmlspecialchars($ecodTpUid . '-' . $ecodTpMid, ENT_QUOTES, 'UTF-8'); ?>"
                    aria-selected="<?php echo $ecodTpMid === $ecodTpFirst ? 'true' : 'false'; ?>">
                <?php echo htmlspecialchars($ecodTpNames[$ecodTpMid], ENT_QUOTES, 'UTF-8'); ?>
            </button>
        <?php endforeach; ?>
    </div>
    <?php foreach ($ecodTpPanes as $ecodTpMid => $ecodTpRows): ?>
        <div class="ecod_tp__pane<?php echo $ecodTpMid === $ecodTpFirst ? ' is-active' : ''; ?>"
             data-ecod-tp-pane="<?php echo htmlspecialchars($ecodTpUid . '-' . $ecodTpMid, ENT_QUOTES, 'UTF-8'); ?>">
            <?php foreach ($ecodTpRows as $ecodTpRow): ?>
                <a class="ecod_tp__item" href="<?php echo htmlspecialchars($ecodTpRow['url'], ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="ecod_tp__thumb">
                        <?php if ($ecodTpRow['thumb'] !== ''): ?>
                            <img src="<?php echo htmlspecialchars($ecodTpRow['thumb'], ENT_QUOTES, 'UTF-8'); ?>"
                                 alt="" width="64" height="48" loading="lazy" decoding="async" data-ecod-img>
                        <?php else: ?>
                            <i aria-hidden="true"><?php echo htmlspecialchars(mb_substr($ecodTpRow['title'], 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></i>
                        <?php endif; ?>
                    </span>
                    <span class="ecod_tp__body">
                        <b class="ecod_tp__title">
                            <?php if ($ecodTpRow['locked']): ?><?php echo ecod_lock_icon(11, 'ecod_tp__lock'); ?><?php endif; ?>
                            <?php echo htmlspecialchars($ecodTpRow['title'], ENT_QUOTES, 'UTF-8'); ?>
                        </b>
                        <?php if ($ecodTpShowMeta): ?>
                            <small class="ecod_tp__meta">
                                <?php echo ecod_icon('eye', 11); ?><?php echo ecod_num_short($ecodTpRow['views']); ?>
                                <span><?php echo ecod_icon('comment', 11); ?><?php echo $ecodTpRow['comments']; ?></span>
                            </small>
                        <?php endif; ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</section>
