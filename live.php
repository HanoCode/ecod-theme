<?php
/**
 * 直播
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
$ecodLiveHtml = (string) ob_get_clean();
$ecodLives = ecod_extract_videos($ecodLiveHtml);
$ecodStage = $ecodLives ? array_shift($ecodLives) : null;
?>
<main class="ecod_main" id="ecod-main">
    <?php /* 模块 · 内容区上方（模块管理台） */ ecod_widgets_render("top_content", $this); ?>

    <nav class="ecod_crumb" aria-label="面包屑">
        <a href="<?php $this->options->siteUrl(); ?>"><?php echo ecod_icon('home', 12); ?>首页</a>
        <span class="ecod_crumb__sep" aria-hidden="true"><?php echo ecod_icon('chevron', 11); ?></span>
        <span aria-current="page"><?php $this->title(); ?></span>
    </nav>

    <?php
    ecod_page_hero(
        '直播',
        (string) $this->title,
        '有直播时这里会亮起来，平时放回放。',
        '<span class="ecod_live__status">' . ecod_icon('radio', 13) . '待开播</span>'
    );
    ?>

    <?php if ($ecodStage): ?>
        <section class="ecod_livestage">
            <div class="ecod_livestage__frame">
                <?php if ($ecodStage['type'] === 'file'): ?>
                    <video controls preload="none" playsinline src="<?php echo htmlspecialchars($ecodStage['src'], ENT_QUOTES, 'UTF-8'); ?>"></video>
                <?php else: ?>
                    <iframe src="<?php echo htmlspecialchars($ecodStage['src'], ENT_QUOTES, 'UTF-8'); ?>"
                            title="<?php echo htmlspecialchars($ecodStage['title'] ?: '直播画面', ENT_QUOTES, 'UTF-8'); ?>"
                            loading="lazy" allowfullscreen scrolling="no" frameborder="0"></iframe>
                <?php endif; ?>
            </div>
            <div class="ecod_livestage__bar">
                <span class="ecod_livestage__dot" aria-hidden="true"></span>
                <strong><?php echo htmlspecialchars($ecodStage['title'] ?: '直播 / 回放', ENT_QUOTES, 'UTF-8'); ?></strong>
                <span class="ecod_livestage__hint">如画面空白，可能是尚未开播</span>
            </div>
        </section>
    <?php else: ?>
        <div class="ecod_empty">
            <div class="ecod_empty__icon" aria-hidden="true"><?php echo ecod_icon('radio', 28); ?></div>
            <h3>当前没有直播</h3>
            <p>在正文里粘贴直播地址（B 站直播间 / YouTube / iframe）即可自动嵌入</p>
        </div>
    <?php endif; ?>

    <?php if ($ecodLives): ?>
        <section class="ecod_panel">
            <header class="ecod_panel__head">
                <h2><?php echo ecod_icon('play', 14); ?>往期回放</h2>
                <span><?php echo count($ecodLives); ?> 个</span>
            </header>
            <div class="ecod_replay">
                <?php foreach ($ecodLives as $ecodIndex => $ecodLive): ?>
                    <a class="ecod_replay__item" href="<?php echo htmlspecialchars($ecodLive['src'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                        <span class="ecod_replay__no"><?php echo str_pad((string) ($ecodIndex + 1), 2, '0', STR_PAD_LEFT); ?></span>
                        <span class="ecod_replay__title"><?php echo htmlspecialchars($ecodLive['title'] ?: '未命名回放', ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="ecod_replay__go" aria-hidden="true"><?php echo ecod_icon('arrow-up-right', 13); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php $ecodIntro = ecod_intro_text($ecodLiveHtml); ?>
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
