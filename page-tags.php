<?php
/**
 * 标签归档
 *
 * @package custom
 * @author  Ecod
 * @since   2026-10-30
 * @version 2.0.0
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 *
 * 在后台「页面 → 新建页面 → 自定义模板」里选择「标签归档」即可（工具 → 特色页可一键创建）。
 * 全站标签墙：按文章数排序，字号随热度渐变，附文章数；点击进入标签文章流。
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$this->need('public/header.php');

/* 全部标签：按实时文章数降序，忽略零文章标签 */
$ecodTagRows = [];
try {
    $ecodTagWidget = $this->widget('Widget_Metas_Tag_Cloud@tagspage', 'sort=count&desc=1');
    while ($ecodTagWidget->next()) {
        $ecodTagRows[] = [
            'mid'   => (int) $ecodTagWidget->mid,
            'name'  => (string) $ecodTagWidget->name,
            'url'   => (string) $ecodTagWidget->permalink,
            'count' => (int) $ecodTagWidget->count,
        ];
    }
} catch (\Throwable $e) {
    $ecodTagRows = [];
}
/* metas.count 会滞后（文章转草稿不更新）：用实时篇数覆盖，顺带剔除零篇并重排 */
$ecodTagItems = ecod_tag_live_rows($ecodTagRows);

/* 字号梯度：sqrt 让热门标签突出但不碾压长尾 */
$ecodTagMax = 0;
foreach ($ecodTagItems as $ecodTagItem) {
    $ecodTagMax = max($ecodTagMax, $ecodTagItem['count']);
}
$ecodTagSize = static function (int $count) use ($ecodTagMax): float {
    if ($ecodTagMax <= 1) {
        return 15.0;
    }
    return round(13 + sqrt($count / $ecodTagMax) * 9, 1);
};
?>
<main class="ecod_main" id="ecod-main">
    <?php /* 模块 · 内容区上方（模块管理台） */ ecod_widgets_render("top_content", $this); ?>

    <nav class="ecod_crumb" aria-label="面包屑">
        <a class="ecod_crumb__back" href="javascript:history.back()" aria-label="返回"><?php echo ecod_icon('chevron', 13); ?></a>
        <a href="<?php $this->options->siteUrl(); ?>"><?php echo ecod_icon('home', 12); ?>首页</a>
        <span class="ecod_crumb__sep" aria-hidden="true">/</span>
        <span aria-current="page"><?php $this->title(); ?></span>
    </nav>

    <?php ecod_page_hero('标签', (string) $this->title, '按话题找文章，字号越大聊得越多。', '<span>' . ecod_icon('tag', 13) . count($ecodTagItems) . ' 个标签</span>'); ?>

    <?php if (!$ecodTagItems): ?>
        <?php ecod_empty('tag', '还没有标签', '发文章时打上标签，这里就会长出来'); ?>
    <?php else: ?>
        <div class="ecod_tagwall">
            <?php foreach ($ecodTagItems as $ecodTagItem): ?>
                <a class="ecod_tagwall__item" href="<?php echo htmlspecialchars($ecodTagItem['url'], ENT_QUOTES, 'UTF-8'); ?>"
                   style="font-size:<?php echo $ecodTagSize($ecodTagItem['count']); ?>px">
                    <?php echo htmlspecialchars($ecodTagItem['name'], ENT_QUOTES, 'UTF-8'); ?>
                    <em><?php echo $ecodTagItem['count']; ?></em>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php /* 模块 · 内容区下方（模块管理台） */ ecod_widgets_render("bottom_content", $this); ?>
</main>

<?php $this->need('public/aside.php'); ?>
<?php $this->need('public/footer.php'); ?>
