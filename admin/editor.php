<?php
/**
 * Ecod Theme · 文章编辑器注入
 *
 * 由 admin/ecod-editor.php 引入，运行在 write-post.php / write-page.php 作用域内，
 * 因此可以直接访问 $post、$page、$options、$security 等后台上下文。
 *
 * @package ecod-theme
 */

if (!defined('__TYPECHO_ADMIN__')) {
    exit;
}

$ecodContent = isset($post) && is_object($post) ? $post : $page;
$ecodType    = isset($post) ? 'post' : 'page';

/* 与 editor-js.php 的判定保持一致：已有文章若不是 markdown 写的，仍按 HTML 处理 */
$ecodIsMarkdown = !empty($options->markdown)
    && (!$ecodContent->have() || !empty($ecodContent->isMarkdown));

$ecodCid  = !empty($ecodContent->cid) ? (int) $ecodContent->cid : 0;
$ecodBase = (string) $options->themeUrl;

$ecodAssetUrl = static function (string $path) use ($ecodBase): string {
    return \Typecho\Common::url($path, $ecodBase);
};

$ecodVer = static function (string $path): string {
    $file = dirname(__DIR__) . '/' . ltrim($path, '/');
    return file_exists($file) ? '?v=' . filemtime($file) : '';
};

/* 表情代码表与前台解析共用同一份数据（core/data/emoji.php） */
$ecodEmojiFile = dirname(__DIR__) . '/core/data/emoji.php';
$ecodEmojiMap  = is_file($ecodEmojiFile) ? (array) require $ecodEmojiFile : [];
$ecodSmiley    = $ecodAssetUrl('assets/img/smiley/');
$ecodEmojis    = [];
foreach ($ecodEmojiMap as $ecodCode => $ecodItem) {
    $ecodEmojis[] = [
        'code'  => (string) $ecodCode,
        'label' => (string) ($ecodItem[1] ?? ''),
        'src'   => $ecodSmiley . (string) ($ecodItem[0] ?? ''),
    ];
}
unset($ecodEmojiFile, $ecodEmojiMap, $ecodItem, $ecodCode);

$ecodConfig = [
    'mode'    => $ecodIsMarkdown ? 'markdown' : 'html',
    'type'    => $ecodType,
    'cid'     => $ecodCid,
    'draft'   => (bool) $ecodContent->draft,
    'upload'  => $security->getIndex('/action/upload'),
    'frame'   => $ecodAssetUrl('admin/frame.css') . $ecodVer('admin/frame.css'),
    'home'    => (string) $options->siteUrl,
    'title'   => $ecodType === 'post' ? '文章' : '页面',
    'thumb'   => '',
    'emojis'  => $ecodEmojis,
];
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($ecodAssetUrl('admin/editor.css') . $ecodVer('admin/editor.css')); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars($ecodAssetUrl('assets/admin/media.css') . $ecodVer('assets/admin/media.css')); ?>">
<?php if ($ecodIsMarkdown): ?>
    <script src="<?php $options->adminStaticUrl('js', 'hyperdown.js'); ?>"></script>
    <script src="<?php $options->adminStaticUrl('js', 'purify.js'); ?>"></script>
<?php endif; ?>
<script>
window.ECOD_EDITOR = <?php echo json_encode($ecodConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
window.ECOD_MEDIA = {
    api: <?php echo json_encode($ecodAssetUrl('media.php'), JSON_UNESCAPED_SLASHES); ?>,
    upload: <?php echo json_encode($ecodConfig['upload'], JSON_UNESCAPED_SLASHES); ?>
};
</script>
<script src="<?php echo htmlspecialchars($ecodAssetUrl('assets/admin/media.js') . $ecodVer('assets/admin/media.js')); ?>"></script>
<script src="<?php echo htmlspecialchars($ecodAssetUrl('admin/editor.js') . $ecodVer('admin/editor.js')); ?>"></script>
<script src="<?php echo htmlspecialchars($ecodAssetUrl('admin/fields.js') . $ecodVer('admin/fields.js')); ?>"></script>
