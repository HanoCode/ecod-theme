<?php
/**
 * 页脚块 · 页脚链接
 *
 * @package Ecod Theme
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodIcp = trim((string) ecod_option('EICP', ''));
if ($ecodIcp === '') {
    /* 兼容早前填在块参数里的备案号 */
    $ecodIcp = trim((string) ecod_p('icp', ''));
}
$ecodPolice = trim((string) ecod_option('EPolice', ''));
$ecodPoliceCode = preg_replace('/\D/', '', $ecodPolice);
$ecodShowHome = (int) ecod_p('home', 1) === 1;
$ecodShowRss = (int) ecod_p('rss', 1) === 1;
?>
<div class="ecod_footer__links">
    <?php if ($ecodIcp !== ''): ?>
        <a href="https://beian.miit.gov.cn" target="_blank" rel="noopener nofollow"><?php echo htmlspecialchars($ecodIcp); ?></a>
    <?php endif; ?>
    <?php if ($ecodPolice !== ''): ?>
        <a href="https://beian.mps.gov.cn/#/query/webSearch?code=<?php echo urlencode($ecodPoliceCode); ?>"
           target="_blank" rel="noopener nofollow"><?php echo htmlspecialchars($ecodPolice); ?></a>
    <?php endif; ?>
    <?php if ($ecodShowHome): ?>
        <a href="<?php $this->options->siteUrl(); ?>">首页</a>
    <?php endif; ?>
    <?php if ($ecodShowRss && $this->options->feedUrl): ?>
        <a href="<?php $this->options->feedUrl(); ?>">RSS</a>
    <?php endif; ?>
</div>
