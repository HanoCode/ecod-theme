<?php
/**
 * 专题墙 / 专题详情
 *
 * @package custom
 * @author  Ecod
 * @since   2026-10-29
 * @version 2.0.0
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 *
 * 在后台「页面 → 新建页面 → 自定义模板」选择「专题」，
 * 或直接到「设置 → 专题」点「一键创建专题页」。
 *
 * 无参数：专题墙（E2）
 * ?t=slug：专题详情（页头 + 文章流）
 * ?t=slug&tp=N：详情第 N 页
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodTopicCur = ecod_topic_current();
$ecodTopicsAll = ecod_topics();
$ecodTopicStyle = (string) ecod_option('ETopicWallStyle', 'cover');

/* 详情页：先把分页算好，再输出 header，标题与 canonical 由 ecod_topic_current 提供 */
$ecodTopicPage = 1;
if ($ecodTopicCur !== null) {
    try {
        $ecodTopicPage = max(1, (int) \Typecho\Request::getInstance()->get('tp', 1));
    } catch (\Throwable $e) {
        $ecodTopicPage = 1;
    }
}

$this->need('public/header.php');

$ecodTopicIndex = ecod_topic_index();
?>
<main class="ecod_main" id="ecod-main">
    <?php /* 模块 · 内容区上方（模块管理台） */ ecod_widgets_render("top_content", $this); ?>

<?php if ($ecodTopicCur === null): ?>

    <nav class="ecod_crumb" aria-label="面包屑">
        <a class="ecod_crumb__back" href="javascript:history.back()" aria-label="返回"><?php echo ecod_icon('chevron', 13); ?></a>
        <a href="<?php $this->options->siteUrl(); ?>"><?php echo ecod_icon('home', 12); ?>首页</a>
        <span class="ecod_crumb__sep" aria-hidden="true">/</span>
        <span aria-current="page">专题</span>
    </nav>

    <?php
    /* 去重计数：同一篇文章可能出现在多个专题里，直接加总会虚高 */
    $ecodTopicSeen = [];
    foreach ($ecodTopicsAll as $ecodTopicOne) {
        foreach (($ecodTopicIndex[$ecodTopicOne['slug']]['cids'] ?? []) as $ecodTopicCid) {
            $ecodTopicSeen[(int) $ecodTopicCid] = true;
        }
    }
    $ecodTopicTotal = count($ecodTopicSeen);
    $ecodMeta = '<span>' . ecod_icon('layers', 13) . count($ecodTopicsAll) . ' 个专题</span>'
        . '<span>' . ecod_icon('list', 13) . $ecodTopicTotal . ' 篇文章</span>';
    ecod_page_hero('专题', (string) $this->title, '按主题把散落的文章串成一条线：一个专题读一件事，不按时间翻页。', $ecodMeta);
    ?>

    <?php if (!$ecodTopicsAll): ?>
        <div class="ecod_empty">
            <div class="ecod_empty__icon" aria-hidden="true"><?php echo ecod_icon('layers', 30); ?></div>
            <h3>还没有专题</h3>
            <p>到「设置 → 专题」新建一个，再从文章里挑几篇加进来</p>
        </div>
    <?php else: ?>
        <?php
        $ecodWallTopics = $ecodTopicsAll;
        $ecodWallCols = max(2, min(4, (int) ecod_option('ETopicWallCols', 3)));
        $ecodWallStyle = $ecodTopicStyle;
        require __DIR__ . '/components/topic/wall.php';
        ?>
    <?php endif; ?>

<?php else: ?>

    <?php
    $ecodTopic = $ecodTopicCur['topic'];
    $ecodTopicSlug = (string) $ecodTopic['slug'];
    $ecodTopicCids = ecod_topic_cids($ecodTopicSlug);
    $ecodTopicCount = count($ecodTopicCids);
    $ecodPerPage = max(6, min(48, (int) ecod_option('ETopicPerPage', 12)));
    $ecodTopicPages = max(1, (int) ceil($ecodTopicCount / $ecodPerPage));
    if ($ecodTopicPage > $ecodTopicPages) {
        $ecodTopicPage = $ecodTopicPages;
    }
    $ecodOffset = ($ecodTopicPage - 1) * $ecodPerPage;
    $ecodCovers = ecod_topic_covers([$ecodTopicSlug]);
    ?>

    <nav class="ecod_crumb" aria-label="面包屑">
        <a class="ecod_crumb__back" href="javascript:history.back()" aria-label="返回"><?php echo ecod_icon('chevron', 13); ?></a>
        <a href="<?php $this->options->siteUrl(); ?>"><?php echo ecod_icon('home', 12); ?>首页</a>
        <span class="ecod_crumb__sep" aria-hidden="true">/</span>
        <a href="<?php echo htmlspecialchars(ecod_topics_url(), ENT_QUOTES, 'UTF-8'); ?>">专题</a>
        <span class="ecod_crumb__sep" aria-hidden="true">/</span>
        <span aria-current="page"><?php echo htmlspecialchars((string) $ecodTopic['name'], ENT_QUOTES, 'UTF-8'); ?></span>
    </nav>

    <?php
    $ecodTopicHead = $ecodTopic;
    $ecodTopicHeadCover = (string) ($ecodCovers[$ecodTopicSlug] ?? '');
    $ecodTopicHeadCount = $ecodTopicCount;
    $ecodTopicHeadLatest = (int) ($ecodTopicIndex[$ecodTopicSlug]['latest'] ?? 0);
    require __DIR__ . '/components/topic/head.php';
    ?>

    <?php if ($ecodTopicCount === 0): ?>
        <div class="ecod_empty">
            <div class="ecod_empty__icon" aria-hidden="true"><?php echo ecod_icon('inbox', 30); ?></div>
            <h3>这个专题还没有文章</h3>
            <p>在文章的自定义字段里把「专题」设为<?php echo htmlspecialchars((string) $ecodTopic['name'], ENT_QUOTES, 'UTF-8'); ?>，或到后台专题里手动挑稿</p>
        </div>
    <?php else: ?>
        <?php
        $ecodTopicMode = ecod_list_force_mode();
        $this->widget('EcodPostQuery@topic', 'pageSize=' . $ecodPerPage . '&offset=' . $ecodOffset . '&cids=' . implode(',', array_slice($ecodTopicCids, 0, 500)))->to($ecodTopicQ);
        ?>
        <div class="<?php echo ecod_list_class($ecodTopicMode); ?>" data-ecod-infinite>
            <?php if ($ecodTopicQ->have()): ?>
                <?php while ($ecodTopicQ->next()): ?>
                    <?php ecod_card($ecodTopicQ, $ecodTopicMode); ?>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <?php if ($ecodTopicPages > 1): ?>
            <?php
            $ecodPagerLinks = [];
            for ($i = 1; $i <= $ecodTopicPages; $i++) {
                $ecodPagerLinks[] = $i;
            }
            ?>
            <nav class="ecod_pager" aria-label="专题翻页">
                <?php if ($ecodTopicPage > 1): ?>
                    <a class="ecod_pager__step" href="<?php echo htmlspecialchars(ecod_topic_url($ecodTopicSlug) . '&tp=' . ($ecodTopicPage - 1), ENT_QUOTES, 'UTF-8'); ?>">‹</a>
                <?php endif; ?>
                <?php $ecodShownPrev = 0; ?>
                <?php foreach ($ecodPagerLinks as $i): ?>
                    <?php if ($ecodTopicPages > 7 && abs($i - $ecodTopicPage) > 2 && $i !== 1 && $i !== $ecodTopicPages): ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <?php if ($ecodShownPrev > 0 && $i - $ecodShownPrev > 1): ?>
                        <span class="ecod_pager__gap" aria-hidden="true">…</span>
                    <?php endif; ?>
                    <?php $ecodShownPrev = $i; ?>
                    <?php if ($i === $ecodTopicPage): ?>
                        <span class="is-current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="<?php echo htmlspecialchars(ecod_topic_url($ecodTopicSlug) . ($i > 1 ? '&tp=' . $i : ''), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($ecodTopicPage < $ecodTopicPages): ?>
                    <a class="ecod_pager__step" href="<?php echo htmlspecialchars(ecod_topic_url($ecodTopicSlug) . '&tp=' . ($ecodTopicPage + 1), ENT_QUOTES, 'UTF-8'); ?>">›</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

    <?php
    /* 其它专题：一行 chips，把读者留在专题层 */
    $ecodMoreCount = max(0, min(12, (int) ecod_option('ETopicMoreCount', 8)));
    $ecodOtherTopics = $ecodMoreCount > 0 ? array_values(array_filter($ecodTopicsAll, static function ($t) use ($ecodTopicSlug) {
        return $t['slug'] !== $ecodTopicSlug;
    })) : [];
    ?>
    <?php if ($ecodOtherTopics): ?>
        <section class="ecod_tmore">
            <span class="ecod_tmore__label"><?php echo ecod_icon('layers', 13); ?>其它专题</span>
            <div class="ecod_tmore__list">
                <?php foreach (array_slice($ecodOtherTopics, 0, $ecodMoreCount) as $ecodTopicOther): ?>
                    <a class="ecod_tmore__chip" href="<?php echo htmlspecialchars(ecod_topic_url((string) $ecodTopicOther['slug']), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars((string) $ecodTopicOther['name'], ENT_QUOTES, 'UTF-8'); ?>
                        <em><?php echo (int) ($ecodTopicIndex[$ecodTopicOther['slug']]['count'] ?? 0); ?></em>
                    </a>
                <?php endforeach; ?>
                <a class="ecod_tmore__all" href="<?php echo htmlspecialchars(ecod_topics_url(), ENT_QUOTES, 'UTF-8'); ?>">全部专题<?php echo ecod_icon('chevron', 12); ?></a>
            </div>
        </section>
    <?php endif; ?>

<?php endif; ?>


    <?php /* 模块 · 内容区下方（模块管理台） */ ecod_widgets_render("bottom_content", $this); ?>
</main>

<?php $this->need('public/aside.php'); ?>
<?php $this->need('public/footer.php'); ?>
