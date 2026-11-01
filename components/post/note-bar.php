<?php
/**
 * 文章页 · 图文笔记互动条
 *
 * 移动端吸附底部通栏（评论入口 + 点赞 + 收藏 + 分享），
 * 桌面端由 CSS 转为正文左侧竖排；形态由设置 → 文章页 → 互动条 控制。
 *
 * 点赞 / 收藏复用全站事件委托（data-ecod-act），计数回写走同一条链路，
 * 不重复实现接口。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @since   2026-11-01
 * @version 1.0.0
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodBarMode = (string) ($ecodNoteData['bar'] ?? 'float');
if ($ecodBarMode === 'hide' || !empty($this->hidden) || !empty($ecodPayLocked)) {
    return;
}

$ecodBarCid = (int) $this->cid;
$ecodBarLike = function_exists('ecod_likes') ? (int) ecod_likes($ecodBarCid) : 0;
$ecodBarFav = function_exists('ecod_favs_count') ? (int) ecod_favs_count($ecodBarCid) : 0;
$ecodBarCmt = function_exists('ecod_comment_count') ? (int) ecod_comment_count($ecodBarCid) : 0;
$ecodBarAllowCmt = ecod_option('EComment', 'on') !== 'off' && $this->allow('comment');
/* dock 开启时底部已被占用，互动条上移让位（与 quickbar 同一处理） */
$ecodBarDock = function_exists('ecod_block_on') && ecod_block_on('dock');
$ecodBarUrl = (string) $this->permalink;
$ecodBarTitle = (string) $this->title;
?>
<div class="ecod_noteart__barwrap">
<div class="ecod_noteart__bar is-<?php echo htmlspecialchars($ecodBarMode, ENT_QUOTES, 'UTF-8'); ?>"
     data-ecod-note-bar
     data-cid="<?php echo $ecodBarCid; ?>"
     data-url="<?php echo htmlspecialchars($ecodBarUrl, ENT_QUOTES, 'UTF-8'); ?>"
     data-title="<?php echo htmlspecialchars($ecodBarTitle, ENT_QUOTES, 'UTF-8'); ?>"
     <?php echo $ecodBarDock ? 'style="--ecod-note-bar-b:60px"' : ''; ?>>
    <?php if ($ecodBarAllowCmt): ?>
    <button type="button" class="ecod_noteart__bar-cmt" data-ecod-note-comment>
        <?php echo ecod_icon('comment', 15); ?>
        <span>说点什么…</span>
        <?php if ($ecodBarCmt > 0): ?><b><?php echo ecod_num_short($ecodBarCmt); ?></b><?php endif; ?>
    </button>
    <?php else: ?>
    <span class="ecod_noteart__bar-cmt is-static" aria-hidden="true"><?php echo ecod_icon('comment', 15); ?><span>评论已关闭</span></span>
    <?php endif; ?>

    <div class="ecod_noteart__bar-acts">
        <button type="button" class="ecod_noteart__bar-btn" data-ecod-act="like" data-cid="<?php echo $ecodBarCid; ?>"
                data-url="<?php echo htmlspecialchars($ecodBarUrl, ENT_QUOTES, 'UTF-8'); ?>"
                data-title="<?php echo htmlspecialchars($ecodBarTitle, ENT_QUOTES, 'UTF-8'); ?>"
                aria-pressed="false" aria-label="点赞">
            <span class="ecod_noteart__bar-ico"><?php echo ecod_icon('heart', 19); ?></span>
            <b data-ecod-like-num="short"><?php echo $ecodBarLike > 0 ? ecod_num_short($ecodBarLike) : '赞'; ?></b>
        </button>
        <button type="button" class="ecod_noteart__bar-btn" data-ecod-act="fav" data-cid="<?php echo $ecodBarCid; ?>"
                data-url="<?php echo htmlspecialchars($ecodBarUrl, ENT_QUOTES, 'UTF-8'); ?>"
                data-title="<?php echo htmlspecialchars($ecodBarTitle, ENT_QUOTES, 'UTF-8'); ?>"
                aria-pressed="false" aria-label="收藏">
            <span class="ecod_noteart__bar-ico"><?php echo ecod_icon('star', 19); ?></span>
            <b data-ecod-fav-num="short"><?php echo $ecodBarFav > 0 ? ecod_num_short($ecodBarFav) : '收藏'; ?></b>
        </button>
        <button type="button" class="ecod_noteart__bar-btn" data-ecod-note-share aria-label="分享">
            <span class="ecod_noteart__bar-ico"><?php echo ecod_icon('share', 19); ?></span>
            <b data-ecod-note-share-lb>分享</b>
        </button>
    </div>
</div>
</div>
