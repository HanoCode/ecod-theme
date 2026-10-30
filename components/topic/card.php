<?php
/**
 * 专题卡（C2b）
 *
 * 两种形态：
 *   cover —— 上图下文，无封面时用主题色渐变 + 首字占位
 *   plain —— 纯文字卡，左侧一条主色竖线，密度更高
 *
 * 调用方负责准备变量：
 *   $ecodTopicCard  专题数组（name/slug/desc）
 *   $ecodTopicCover 封面地址（可为空）
 *   $ecodTopicCount 篇数
 *   $ecodTopicLatest 最新一篇标题（可为空）
 *   $ecodTopicStyle cover | plain
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodTopicCard = $ecodTopicCard ?? null;
if (!is_array($ecodTopicCard) || empty($ecodTopicCard['slug'])) {
    return;
}

$ecodTopicCover = (string) ($ecodTopicCover ?? '');
$ecodTopicCount = (int) ($ecodTopicCount ?? 0);
$ecodTopicLatest = (string) ($ecodTopicLatest ?? '');
$ecodTopicStyle = (string) ($ecodTopicStyle ?? 'cover') === 'plain' ? 'plain' : 'cover';
$ecodTopicUrl = ecod_topic_url((string) $ecodTopicCard['slug']);
$ecodTopicName = (string) $ecodTopicCard['name'];
$ecodTopicDesc = (string) ($ecodTopicCard['desc'] ?? '');
?>
<a class="ecod_tcard ecod_tcard--<?php echo $ecodTopicStyle; ?><?php echo $ecodTopicCover === '' ? ' is-nocover' : ''; ?>"
   href="<?php echo htmlspecialchars($ecodTopicUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($ecodTopicStyle === 'cover'): ?>
        <span class="ecod_tcard__media"<?php if ($ecodTopicCover !== ''): ?> style="background-image:url('<?php echo htmlspecialchars($ecodTopicCover, ENT_QUOTES, 'UTF-8'); ?>')"<?php endif; ?>>
            <span class="ecod_tcard__shade" aria-hidden="true"></span>
            <?php if ($ecodTopicCover === ''): ?>
                <span class="ecod_tcard__ph" aria-hidden="true"><?php echo htmlspecialchars(mb_substr($ecodTopicName, 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
            <span class="ecod_tcard__count"><?php echo $ecodTopicCount; ?> 篇</span>
        </span>
    <?php endif; ?>

    <span class="ecod_tcard__body">
        <b class="ecod_tcard__name">
            <?php if ($ecodTopicStyle === 'plain'): ?>
                <em class="ecod_tcard__num"><?php echo $ecodTopicCount; ?></em>
            <?php endif; ?>
            <?php echo htmlspecialchars($ecodTopicName, ENT_QUOTES, 'UTF-8'); ?>
        </b>
        <?php if ($ecodTopicDesc !== ''): ?>
            <small class="ecod_tcard__desc"><?php echo htmlspecialchars($ecodTopicDesc, ENT_QUOTES, 'UTF-8'); ?></small>
        <?php endif; ?>
        <span class="ecod_tcard__foot">
            <?php if ($ecodTopicLatest !== ''): ?>
                <span class="ecod_tcard__latest">最新 · <?php echo htmlspecialchars($ecodTopicLatest, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php else: ?>
                <span class="ecod_tcard__latest is-empty">还没有文章</span>
            <?php endif; ?>
            <i class="ecod_tcard__go" aria-hidden="true"><?php echo ecod_icon('arrow-up-right', 12); ?></i>
        </span>
    </span>
</a>
