<?php
/**
 * 页脚块 · 版权信息
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 * @license   MIT
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodFooterHtml = trim((string) ecod_p('html', ''));
?>
<p>
    <?php if ($ecodFooterHtml !== ''): ?>
        <?php echo $ecodFooterHtml; ?>
    <?php else: ?>
        © <?php echo date('Y'); ?> <a href="<?php $this->options->siteUrl(); ?>"><?php $this->options->title(); ?></a>
        · Powered by <a href="https://typecho.org" target="_blank" rel="noopener">Typecho</a>
        · Theme <b>Ecod</b>
    <?php endif; ?>
</p>
