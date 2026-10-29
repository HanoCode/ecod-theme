<?php
/**
 * 文章页块 · 版权声明
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 * @license   MIT
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/* 密码保护：未解锁前不展示 */
if (!empty($this->hidden)) {
    return;
}

$ecodCpLicense = (string) ecod_p('license', 'by-nc-sa');
$ecodCpMap = [
    'by-nc-sa' => ['CC BY-NC-SA 4.0', 'https://creativecommons.org/licenses/by-nc-sa/4.0/deed.zh'],
    'by'       => ['CC BY 4.0', 'https://creativecommons.org/licenses/by/4.0/deed.zh'],
    'reserved' => ['保留所有权利', ''],
];
if (!isset($ecodCpMap[$ecodCpLicense])) {
    $ecodCpLicense = 'by-nc-sa';
}
[$ecodCpName, $ecodCpUrl] = $ecodCpMap[$ecodCpLicense];

ob_start();
$this->author();
$ecodCpAuthor = trim((string) ob_get_clean());

$ecodCpNotice = trim((string) ecod_p('notice', ''));
$ecodCpLink = (string) $this->permalink;
?>
<aside class="ecod_copyright" role="note">
    <div class="ecod_copyright__head">
        <?php echo ecod_icon('file', 14); ?>
        <span><?php echo htmlspecialchars((string) ecod_p('title', '版权声明'), ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <ul class="ecod_copyright__list">
        <li>
            <em>作者</em>
            <span><?php echo htmlspecialchars($ecodCpAuthor, ENT_QUOTES, 'UTF-8'); ?></span>
        </li>
        <li>
            <em>链接</em>
            <a href="<?php echo htmlspecialchars($ecodCpLink, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ecodCpLink, ENT_QUOTES, 'UTF-8'); ?></a>
        </li>
        <li>
            <em>协议</em>
            <?php if ($ecodCpUrl !== ''): ?>
                <a href="<?php echo $ecodCpUrl; ?>" target="_blank" rel="noopener nofollow"><?php echo $ecodCpName; ?></a>
            <?php else: ?>
                <span><?php echo $ecodCpName; ?></span>
            <?php endif; ?>
        </li>
    </ul>
    <?php if ($ecodCpNotice !== ''): ?>
        <p class="ecod_copyright__notice"><?php echo nl2br(htmlspecialchars($ecodCpNotice, ENT_QUOTES, 'UTF-8')); ?></p>
    <?php endif; ?>
</aside>
