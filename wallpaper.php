<?php
/**
 * 壁纸集
 *
 * @package custom
 * @author  Ecod
 * @since   2026-10-29
 * @version 2.0.0
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$this->need('public/header.php');

ob_start();
$this->content();
$ecodWallHtml = (string) ob_get_clean();
$ecodWalls = ecod_extract_images($ecodWallHtml, 60);
$ecodWallMeta = '<span>' . ecod_icon('image', 13) . count($ecodWalls) . ' 张图片</span>';
?>
<main class="ecod_main" id="ecod-main">
    <?php /* 模块 · 内容区上方（模块管理台） */ ecod_widgets_render("top_content", $this); ?>

    <nav class="ecod_crumb" aria-label="面包屑">
        <a href="<?php $this->options->siteUrl(); ?>"><?php echo ecod_icon('home', 12); ?>首页</a>
        <span class="ecod_crumb__sep" aria-hidden="true"><?php echo ecod_icon('chevron', 11); ?></span>
        <span aria-current="page"><?php $this->title(); ?></span>
    </nav>

    <?php ecod_page_hero('壁纸', (string) $this->title, '点开看原图，按需自取。', $ecodWallMeta); ?>

    <?php if ($ecodWalls): ?>
        <div class="ecod_wallgrid" data-ecod-gallery>
            <?php foreach ($ecodWalls as $ecodWall): ?>
                <?php
                /* 优先用正文里作者标注的宽高；没有则读站内文件真实尺寸 */
                $ecodWallDims = (!empty($ecodWall['w']) && !empty($ecodWall['h']))
                    ? ' width="' . (int) $ecodWall['w'] . '" height="' . (int) $ecodWall['h'] . '"'
                    : ecod_img_attrs($ecodWall['src']);
                ?>
                <figure class="ecod_wall">
                    <button type="button" class="ecod_wall__open" data-ecod-lightbox
                            data-src="<?php echo htmlspecialchars($ecodWall['src'], ENT_QUOTES, 'UTF-8'); ?>"
                            aria-label="查看大图">
                        <img src="<?php echo htmlspecialchars($ecodWall['src'], ENT_QUOTES, 'UTF-8'); ?>"
                             alt="<?php echo htmlspecialchars($ecodWall['alt'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" decoding="async"<?php echo $ecodWallDims; ?>>
                    </button>
                    <figcaption class="ecod_wall__cap">
                        <span><?php echo htmlspecialchars($ecodWall['alt'] ?: '未命名图片', ENT_QUOTES, 'UTF-8'); ?></span>
                        <a class="ecod_wall__dl" href="<?php echo htmlspecialchars($ecodWall['src'], ENT_QUOTES, 'UTF-8'); ?>"
                           download target="_blank" rel="noopener" aria-label="下载原图"><?php echo ecod_icon('download', 13); ?></a>
                    </figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="ecod_empty">
            <div class="ecod_empty__icon" aria-hidden="true"><?php echo ecod_icon('image', 28); ?></div>
            <h3>还没有图片</h3>
            <p>在正文里插入图片即可自动排成瀑布流</p>
        </div>
    <?php endif; ?>

    <?php $ecodIntro = ecod_intro_text($ecodWallHtml, true); ?>
    <?php if (trim(strip_tags($ecodIntro)) !== ''): ?>
        <article class="ecod_article ecod_leave__intro">
            <div class="ecod_content">
                <?php echo $ecodIntro; ?>
            </div>
        </article>
    <?php endif; ?>


    <?php /* 模块 · 内容区下方（模块管理台） */ ecod_widgets_render("bottom_content", $this); ?>
</main>

<?php $this->need('public/aside.php'); ?>
<?php $this->need('public/footer.php'); ?>
