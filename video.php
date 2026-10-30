<?php
/**
 * 视频集
 *
 * @package custom
 * @author  Ecod
 * @since   2026-10-29
 * @version 2.0.0
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$this->need('public/header.php');

ob_start();
$this->content();
$ecodVideoHtml = (string) ob_get_clean();
$ecodVideos = ecod_extract_videos($ecodVideoHtml);
$ecodVideoMeta = '<span>' . ecod_icon('play', 13) . count($ecodVideos) . ' 个视频</span>';
?>
<main class="ecod_main" id="ecod-main">
    <?php /* 模块 · 内容区上方（模块管理台） */ ecod_widgets_render("top_content", $this); ?>

    <nav class="ecod_crumb" aria-label="面包屑">
        <a class="ecod_crumb__back" href="javascript:history.back()" aria-label="返回"><?php echo ecod_icon('chevron', 13); ?></a>
        <a href="<?php $this->options->siteUrl(); ?>"><?php echo ecod_icon('home', 12); ?>首页</a>
        <span class="ecod_crumb__sep" aria-hidden="true">/</span>
        <span aria-current="page"><?php $this->title(); ?></span>
    </nav>

    <?php ecod_page_hero('视频', (string) $this->title, '随手记录与收藏的片段。', $ecodVideoMeta); ?>

    <?php if ($ecodVideos): ?>
        <div class="ecod_videogrid">
            <?php foreach ($ecodVideos as $ecodIndex => $ecodVideo): ?>
                <figure class="ecod_video">
                    <div class="ecod_video__frame">
                        <?php if ($ecodVideo['type'] === 'file'): ?>
                            <video controls preload="none" playsinline
                                <?php echo $ecodVideo['poster'] !== '' ? 'poster="' . htmlspecialchars($ecodVideo['poster'], ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
                                <source src="<?php echo htmlspecialchars($ecodVideo['src'], ENT_QUOTES, 'UTF-8'); ?>">
                            </video>
                        <?php else: ?>
                            <iframe src="<?php echo htmlspecialchars($ecodVideo['src'], ENT_QUOTES, 'UTF-8'); ?>"
                                    title="<?php echo htmlspecialchars($ecodVideo['title'] ?: '视频 ' . ($ecodIndex + 1), ENT_QUOTES, 'UTF-8'); ?>"
                                    loading="lazy" allowfullscreen scrolling="no" frameborder="0"></iframe>
                        <?php endif; ?>
                    </div>
                    <figcaption class="ecod_video__cap">
                        <span class="ecod_video__no"><?php echo str_pad((string) ($ecodIndex + 1), 2, '0', STR_PAD_LEFT); ?></span>
                        <span class="ecod_video__title"><?php echo htmlspecialchars($ecodVideo['title'] ?: '未命名片段', ENT_QUOTES, 'UTF-8'); ?></span>
                    </figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="ecod_empty">
            <div class="ecod_empty__icon" aria-hidden="true"><?php echo ecod_icon('play', 28); ?></div>
            <h3>还没有视频</h3>
            <p>在正文里粘贴 B 站 / YouTube 地址，或按「标题 | 视频地址」每行一个填写</p>
        </div>
    <?php endif; ?>

    <?php $ecodIntro = ecod_intro_text($ecodVideoHtml); ?>
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
