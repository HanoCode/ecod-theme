<?php
/**
 * Ecod Theme · 文章编辑器注入
 *
 * 由 admin/ecod-editor.php 引入，运行在 write-post.php / write-page.php 作用域内，
 * 因此可以直接访问 $post、$page、$options、$security 等后台上下文。
 *
 * @package ecod-theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 * @license   MIT
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

/* 资源地址：dist 哈希产物优先（core/lib/asset.php 查构建 manifest，文件名即版本）；
   未构建时回退源文件 + filemtime 版本号，与前台 ecod_asset 同一解析器 */
require_once dirname(__DIR__) . '/core/lib/asset.php';

$ecodAssetUrl = static function (string $path) use ($ecodBase): string {
    $dist = ecod_dist_resolve(ltrim($path, '/'));
    return \Typecho\Common::url($dist ?? $path, $ecodBase);
};

$ecodVer = static function (string $path): string {
    if (ecod_dist_resolve(ltrim($path, '/')) !== null) {
        return '';
    }
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
unset($ecodEmojiFile, $ecodEmojiMap, $ecodItem, $ecodCode, $ecodCode);

/* 专题列表（写作页字段面板的「专题」选择区用）。
   admin 上下文不加载主题 functions.php，给 core/site/topics.php 依赖的
   ecod_option 一个最小实现（function_exists 守卫，避免与主题冲突）。 */
if (!function_exists('ecod_option')) {
    function ecod_option(string $key, $default = '')
    {
        try {
            $value = \Utils\Helper::options()->{$key};
            return ($value === null || $value === '') ? $default : $value;
        } catch (\Throwable $e) {
            return $default;
        }
    }
}

$ecodTopicList = [];
$ecodTopicsFile = dirname(__DIR__) . '/core/site/topics.php';
if ($ecodType === 'post' && is_file($ecodTopicsFile)) {
    require_once $ecodTopicsFile;
    foreach (ecod_topics(false) as $ecodTopicRow) {
        $ecodTopicList[] = [
            'slug' => (string) $ecodTopicRow['slug'],
            'name' => (string) $ecodTopicRow['name'],
            'on'   => !empty($ecodTopicRow['on']),
        ];
    }
}
unset($ecodTopicsFile);

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
    'topics'  => $ecodTopicList,
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
