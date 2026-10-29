<?php
/**
 * 首页
 *
 * 模块顺序、开关与参数由后台「首页」区块统一管理（块系统）。
 *
 * @package Ecod Theme
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$this->need('public/header.php');

$ecodBanner = trim((string) ecod_option('EBanner', ''));
$ecodSlogan = ecod_option('ESlogan', $this->options->description);
?>
<main class="ecod_main ecod_home" id="ecod-main">

    <?php if ($ecodBanner !== ''): ?>
        <section class="ecod_banner" style="--ecod-banner:url('<?php echo htmlspecialchars($ecodBanner, ENT_QUOTES, 'UTF-8'); ?>')">
            <div class="ecod_banner__inner">
                <h1><?php $this->options->title(); ?></h1>
                <?php if ($ecodSlogan): ?>
                    <p><?php echo htmlspecialchars($ecodSlogan); ?></p>
                <?php endif; ?>
            </div>
            <span class="ecod_banner__wave" aria-hidden="true"></span>
        </section>
    <?php endif; ?>

    <?php ecod_blocks_render('home', $this); ?>

</main>

<?php $this->need('public/aside.php'); ?>
<?php $this->need('public/footer.php'); ?>
