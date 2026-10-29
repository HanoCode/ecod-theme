<?php
/**
 * 数据统计
 *
 * @package custom
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$this->need('public/header.php');

$ecodStats = ecod_site_stats();
$ecodDb = \Typecho\Db::get();

/* 分类分布 */
$ecodCats = [];
try {
    $ecodCats = $ecodDb->fetchAll(
        $ecodDb->select('name', 'mid', 'count')->from('table.metas')
            ->where('type = ?', 'category')->order('table.metas.count', \Typecho\Db::SORT_DESC)->limit(8)
    );
} catch (\Throwable $e) {
}

/* 近 8 个月发文量 */
$ecodMonths = [];
try {
    $rows = $ecodDb->fetchAll(
        $ecodDb->select('created')->from('table.contents')
            ->where('type = ?', 'post')->where('status = ?', 'publish')->limit(1000)
    );
    $bucket = [];
    foreach ($rows as $row) {
        $key = date('Y-m', (int) $row['created']);
        $bucket[$key] = ($bucket[$key] ?? 0) + 1;
    }
    for ($i = 7; $i >= 0; $i--) {
        $key = date('Y-m', strtotime('-' . $i . ' month'));
        $ecodMonths[$key] = $bucket[$key] ?? 0;
    }
} catch (\Throwable $e) {
}
$ecodMonthMax = $ecodMonths ? max(1, max($ecodMonths)) : 1;
$ecodCatMax = 1;
foreach ($ecodCats as $ecodCat) {
    $ecodCatMax = max($ecodCatMax, (int) $ecodCat['count']);
}

$ecodCards = [
    ['file', '文章', number_format($ecodStats['posts']), '篇'],
    ['comment', '评论', number_format($ecodStats['comments']), '条'],
    ['eye', '阅读', number_format($ecodStats['views']), '次'],
    ['heart', '点赞', number_format($ecodStats['likes']), '次'],
    ['edit', '字数', number_format($ecodStats['words']), '字'],
    ['clock', '运行', number_format($ecodStats['days']), '天'],
];
?>
<main class="ecod_main" id="ecod-main">

    <nav class="ecod_crumb" aria-label="面包屑">
        <a href="<?php $this->options->siteUrl(); ?>"><?php echo ecod_icon('home', 12); ?>首页</a>
        <span class="ecod_crumb__sep" aria-hidden="true"><?php echo ecod_icon('chevron', 11); ?></span>
        <span aria-current="page"><?php $this->title(); ?></span>
    </nav>

    <?php ecod_page_hero('统计', (string) $this->title, '关于这个站的一些数字。', '<span>' . ecod_icon('chart', 13) . '更新于 ' . date('Y-m-d H:i') . '</span>'); ?>

    <div class="ecod_statgrid">
        <?php foreach ($ecodCards as $ecodCard): ?>
            <div class="ecod_stat">
                <span class="ecod_stat__icon"><?php echo ecod_icon($ecodCard[0], 15); ?></span>
                <strong class="ecod_stat__num">
                    <?php echo $ecodCard[2]; ?>
                </strong>
                <span class="ecod_stat__label"><?php echo $ecodCard[1]; ?><i><?php echo $ecodCard[3]; ?></i></span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="ecod_stat2col">
        <section class="ecod_panel">
            <header class="ecod_panel__head">
                <h2><?php echo ecod_icon('folder', 14); ?>分类分布</h2>
                <span><?php echo count($ecodCats); ?> 个分类</span>
            </header>
            <?php if ($ecodCats): ?>
                <ul class="ecod_bars">
                    <?php foreach ($ecodCats as $ecodCat): ?>
                        <?php $ecodPct = round((int) $ecodCat['count'] / $ecodCatMax * 100); ?>
                        <li>
                            <span class="ecod_bars__name"><?php echo htmlspecialchars((string) $ecodCat['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="ecod_bars__track"><i style="width:<?php echo $ecodPct; ?>%"></i></span>
                            <span class="ecod_bars__num"><?php echo (int) $ecodCat['count']; ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="ecod_panel__empty">暂无分类数据</p>
            <?php endif; ?>
        </section>

        <section class="ecod_panel">
            <header class="ecod_panel__head">
                <h2><?php echo ecod_icon('chart', 14); ?>近 8 个月</h2>
                <span>发文量</span>
            </header>
            <div class="ecod_cols">
                <?php foreach ($ecodMonths as $ecodKey => $ecodNum): ?>
                    <?php $ecodH = max(4, round($ecodNum / $ecodMonthMax * 100)); ?>
                    <div class="ecod_cols__item" title="<?php echo $ecodKey; ?> · <?php echo $ecodNum; ?> 篇">
                        <span class="ecod_cols__num"><?php echo $ecodNum ?: ''; ?></span>
                        <span class="ecod_cols__bar" style="height:<?php echo $ecodH; ?>%"></span>
                        <span class="ecod_cols__label"><?php echo substr($ecodKey, 5); ?>月</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <div class="ecod_stat2col">
        <section class="ecod_panel">
            <header class="ecod_panel__head">
                <h2><?php echo ecod_icon('comment', 14); ?>最新评论</h2>
                <span>最近 6 条</span>
            </header>
            <?php
            $this->widget('\Widget\Comments\Recent', [
                'pageSize'     => 6,
                'ignoreAuthor' => false,
            ])->to($ecodRecent);
            ?>
            <?php if ($ecodRecent->have()): ?>
                <ul class="ecod_rlist">
                    <?php while ($ecodRecent->next()): ?>
                        <li>
                            <span class="ecod_rlist__avatar"><?php $ecodRecent->gravatar(30); ?></span>
                            <div>
                                <a class="ecod_rlist__title" href="<?php echo htmlspecialchars((string) $ecodRecent->permalink, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars((string) $ecodRecent->author, ENT_QUOTES, 'UTF-8'); ?>
                                    <i>· <?php echo ecod_relative_time((int) $ecodRecent->created); ?></i>
                                </a>
                                <p><?php $ecodRecent->excerpt(46, '…'); ?></p>
                            </div>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php else: ?>
                <p class="ecod_panel__empty">还没有评论</p>
            <?php endif; ?>
        </section>

        <section class="ecod_panel">
            <header class="ecod_panel__head">
                <h2><?php echo ecod_icon('hash', 14); ?>标签云</h2>
                <span><?php echo (int) $ecodStats['tags']; ?> 个标签</span>
            </header>
            <div class="ecod_tagcloud">
                <?php
                $this->widget('\Widget\Metas\Tag\Cloud')->to($ecodTags);
                if ($ecodTags->have()):
                    while ($ecodTags->next()):
                        ?>
                        <a class="ecod_tagcloud__item" href="<?php $ecodTags->permalink(); ?>">
                            <?php $ecodTags->name(); ?><i><?php $ecodTags->count(); ?></i>
                        </a>
                        <?php
                    endwhile;
                else:
                    ?>
                    <p class="ecod_panel__empty">还没有标签</p>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <?php if (trim(strip_tags((string) $this->content)) !== ''): ?>
        <article class="ecod_article ecod_leave__intro">
            <div class="ecod_content">
                <?php $this->content(); ?>
            </div>
        </article>
    <?php endif; ?>

</main>

<?php $this->need('public/aside.php'); ?>
<?php $this->need('public/footer.php'); ?>
