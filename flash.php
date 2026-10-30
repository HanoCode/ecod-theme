<?php
/**
 * 快讯
 *
 * @package custom
 * @author  Ecod
 * @since   2026-10-30
 * @version 2.0.0
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 *
 * 在后台「页面 → 新建页面 → 自定义模板」里选择「快讯」即可（工具 → 特色页可一键创建）。
 * 按日期分组的时间流：日期戳 + 标题 + 一行摘要 + 来源外链，?fp=2 翻页。
 * 可选：页面自定义字段 flash_cat 填分类 mid，只取该分类（配合「快讯」投稿分类）。
 * 文章自定义字段 source 填 http(s) 地址时，条目末尾出现「查看来源」外链。
 */
if (!defined('__TYPECHO_ROOT_DIR')) {
    exit;
}
$this->need('public/header.php');

$ecodFlashPageSize = 20;
$ecodFlashPage = max(1, min(500, (int) $this->request->filter('int')->get('fp', 1)));

/* 可选：限定分类（页面自定义字段 flash_cat = mid） */
$ecodFlashMid = 0;
try {
    $ecodFlashMid = (int) ($this->fields->flash_cat ?? 0);
} catch (\Throwable $e) {
}

$ecodFlashQuery = 'pageSize=' . $ecodFlashPageSize . '&offset=' . (($ecodFlashPage - 1) * $ecodFlashPageSize);
if ($ecodFlashMid > 0) {
    $ecodFlashQuery .= '&category=' . $ecodFlashMid;
}
$this->widget('EcodPostQuery@flashpage' . $ecodFlashPage, $ecodFlashQuery)->to($ecodFlashQ);

/* 汇总成按日分组的数组，一轮输出 */
$ecodFlashGroups = [];
$ecodFlashFetched = 0;
while ($ecodFlashQ->next()) {
    $ecodFlashFetched++;
    $ecodFlashTime = (int) $ecodFlashQ->created;
    $ecodFlashDay = date('Y-m-d', $ecodFlashTime);
    /* 自定义字段 source：来源外链 */
    $ecodFlashSource = '';
    try {
        $ecodFlashSource = trim((string) ($ecodFlashQ->fields->source ?? ''));
    } catch (\Throwable $e) {
    }
    if (!preg_match('#^https?://#i', $ecodFlashSource)) {
        $ecodFlashSource = '';
    }
    $ecodFlashGroups[$ecodFlashDay][] = [
        'time'    => $ecodFlashTime,
        'title'   => (string) $ecodFlashQ->title,
        'url'     => (string) $ecodFlashQ->permalink,
        'excerpt' => trim(ecod_excerpt($ecodFlashQ)),
        'views'   => ecod_views($ecodFlashQ),
        'locked'  => ecod_has_password($ecodFlashQ),
        'source'  => $ecodFlashSource,
    ];
}

$ecodFlashWeek = ['日', '一', '二', '三', '四', '五', '六'];
?>
<main class="ecod_main" id="ecod-main">
    <?php /* 模块 · 内容区上方（模块管理台） */ ecod_widgets_render("top_content", $this); ?>

    <nav class="ecod_crumb" aria-label="面包屑">
        <a class="ecod_crumb__back" href="javascript:history.back()" aria-label="返回"><?php echo ecod_icon('chevron', 13); ?></a>
        <a href="<?php $this->options->siteUrl(); ?>"><?php echo ecod_icon('home', 12); ?>首页</a>
        <span class="ecod_crumb__sep" aria-hidden="true">/</span>
        <span aria-current="page"><?php $this->title(); ?></span>
    </nav>

    <?php ecod_page_hero('快讯', (string) $this->title, '短平快的最新动态，按天回看。', '<span>' . ecod_icon('zap', 13) . '第 ' . $ecodFlashPage . ' 页</span>'); ?>

    <?php if (!$ecodFlashGroups): ?>
        <div class="ecod_empty">
            <div class="ecod_empty__icon" aria-hidden="true"><?php echo ecod_icon('zap', 30); ?></div>
            <h3>还没有快讯</h3>
            <p>发布文章后这里会按天汇总</p>
        </div>
    <?php else: ?>
        <div class="ecod_flashpage">
            <?php foreach ($ecodFlashGroups as $ecodFlashDay => $ecodFlashItems): ?>
                <?php
                $ecodFlashTs = (int) strtotime($ecodFlashDay . ' 00:00:00');
                $ecodFlashLabel = date('Y年n月j日', $ecodFlashTs) . ' · 周' . $ecodFlashWeek[(int) date('w', $ecodFlashTs)];
                ?>
                <section class="ecod_flashpage__day">
                    <h2 class="ecod_flashpage__date">
                        <span><?php echo htmlspecialchars($ecodFlashLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                        <em><?php echo count($ecodFlashItems); ?> 条</em>
                    </h2>
                    <div class="ecod_flashpage__list">
                        <?php foreach ($ecodFlashItems as $ecodFlashItem): ?>
                            <article class="ecod_flashpage__item">
                                <time class="ecod_flashpage__time" datetime="<?php echo date('c', $ecodFlashItem['time']); ?>"
                                      aria-hidden="true"><?php echo date('H:i', $ecodFlashItem['time']); ?></time>
                                <div class="ecod_flashpage__main">
                                    <h3>
                                        <?php if ($ecodFlashItem['locked']): ?><?php echo ecod_lock_icon(12, 'ecod_flash__lock'); ?><?php endif; ?>
                                        <a href="<?php echo htmlspecialchars($ecodFlashItem['url'], ENT_QUOTES, 'UTF-8'); ?>"><?php
                                            echo htmlspecialchars($ecodFlashItem['title'], ENT_QUOTES, 'UTF-8');
                                        ?></a>
                                    </h3>
                                    <?php if ($ecodFlashItem['excerpt'] !== ''): ?>
                                        <p><?php echo htmlspecialchars($ecodFlashItem['excerpt'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <?php endif; ?>
                                    <footer>
                                        <span><?php echo ecod_icon('eye', 12); ?><?php echo ecod_num_short($ecodFlashItem['views']); ?></span>
                                        <?php if ($ecodFlashItem['source'] !== ''): ?>
                                            <a href="<?php echo htmlspecialchars($ecodFlashItem['source'], ENT_QUOTES, 'UTF-8'); ?>"
                                               target="_blank" rel="noopener nofollow"><?php echo ecod_icon('link', 12); ?>查看来源</a>
                                        <?php else: ?>
                                            <a href="<?php echo htmlspecialchars($ecodFlashItem['url'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo ecod_icon('chevron', 12); ?>阅读全文</a>
                                        <?php endif; ?>
                                    </footer>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>

        <nav class="ecod_flashpage__pager" aria-label="快讯翻页">
            <?php if ($ecodFlashPage > 1): ?>
                <a class="ecod-btn ecod-btn--ghost" href="<?php
                    echo htmlspecialchars((string) $this->permalink . ($ecodFlashPage - 1 > 1 ? '?fp=' . ($ecodFlashPage - 1) : ''), ENT_QUOTES, 'UTF-8');
                ?>"><?php echo ecod_icon('chevron', 12); ?>前一页</a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
            <?php /* 本页取满一页才可能有下一页；最后一页的空跳转由「还没有快讯」空态承接 */ ?>
            <?php if ($ecodFlashFetched >= $ecodFlashPageSize): ?>
                <a class="ecod-btn ecod-btn--ghost" href="<?php
                    echo htmlspecialchars((string) $this->permalink . '?fp=' . ($ecodFlashPage + 1), ENT_QUOTES, 'UTF-8');
                ?>">更早的快讯<?php echo ecod_icon('chevron', 12); ?></a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

    <?php /* 模块 · 内容区下方（模块管理台） */ ecod_widgets_render("bottom_content", $this); ?>
</main>

<?php $this->need('public/aside.php'); ?>
<?php $this->need('public/footer.php'); ?>
