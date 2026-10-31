<?php
/**
 * 评论区
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/* 后台「评论 → 全站评论」关掉时整块不输出，文章级的 allow('comment') 仍然优先 */
if (ecod_option('EComment', 'on') === 'off') {
    return;
}

/* 第三方评论系统接管：原生列表与表单都不输出，由外部服务渲染 */
$ecodProvider = (string) ecod_option('ECommentProvider', 'native');
if (in_array($ecodProvider, ['twikoo', 'waline', 'artalk', 'giscus'], true)) {
    ?>
    <section class="ecod_comments ecod_comments--third" id="comments">
        <h2 class="ecod_comments__title">评论</h2>
        <?php if ($this->allow('comment')): ?>
            <div class="ecod_third" data-ecod-third="<?php echo htmlspecialchars($ecodProvider, ENT_QUOTES, 'UTF-8'); ?>">
                <noscript>需要启用 JavaScript 才能加载评论。</noscript>
            </div>
            <p class="ecod_third__tip" data-ecod-third-tip hidden></p>
        <?php else: ?>
            <p class="ecod_author__desc" style="margin-top:12px">评论已关闭</p>
        <?php endif; ?>
    </section>
    <?php
    return;
}

/* 评论总数按全站已通过数统计（嵌套评论也计入），Typecho 原生的 commentsNum 只数顶层 */
$ecodCmtStats = ecod_comment_stats((int) $this->cid);
$ecodCmtTotal = (int) $ecodCmtStats['total'];
$ecodTurnstile = ecod_option('ETurnstile', 'off') === 'on'
    ? trim((string) ecod_option('ETurnstileSite', ''))
    : '';

/* 评论点赞：原生评论区启用；一次查全本文所有评论的赞数，渲染零额外查询 */
$GLOBALS['ecodCLikeOn'] = ecod_option('ECLike', 'on') !== 'off';
$GLOBALS['ecodCLikeCounts'] = $GLOBALS['ecodCLikeOn'] ? ecod_comment_like_counts((int) $this->cid) : null;
?>
<section class="ecod_comments" id="comments">
    <?php $this->comments()->to($comments); ?>

    <?php /* 没有 JS 时折叠按钮点不动，直接全部展开 */ ?>
    <noscript><style>.ecod_cmt__fold,.ecod_cmtall{display:none!important}.ecod_cmt__children[hidden]{display:block!important}</style></noscript>

    <?php $ecodExpandMode = ecod_comment_expand_mode(); ?>
    <div class="ecod_comments__head">
        <h2 class="ecod_comments__title">
            <?php echo $ecodCmtTotal > 0 ? '<b>' . (int) $ecodCmtTotal . '</b> 条评论' : '暂无评论'; ?>
        </h2>
        <?php if ($ecodExpandMode === 'button' && $ecodCmtTotal > 0): ?>
            <?php /* 没有折叠节点时由 JS 保持隐藏，避免空按钮 */ ?>
            <button type="button" class="ecod_cmtall" data-ecod-fold-all aria-expanded="false" hidden>
                <span data-ecod-fold-all-text>展开全部回复</span>
            </button>
        <?php endif; ?>
    </div>

    <?php if ($comments->have()): ?>
        <?php $comments->listComments([
            'before' => '<ol class="ecod_cmtlist">',
            'after'  => '</ol>',
        ]); ?>
        <?php $comments->pageNav('‹', '›', 3, '…', [
            'wrapTag'      => 'div',
            'wrapClass'    => 'ecod_pager',
            'itemTag'      => '',
            'textTag'      => 'span',
            'currentClass' => 'is-current',
        ]); ?>
    <?php endif; ?>

    <?php if ($this->allow('comment')): ?>
        <div class="ecod_respond" id="<?php $this->respondId(); ?>">
            <div class="ecod_respond__head">
                <p class="ecod_respond__to" data-ecod-replyto hidden></p>
                <?php $comments->cancelReply('<span class="ecod_respond__cancel">取消回复</span>'); ?>
            </div>

            <form method="post" action="<?php $this->commentUrl(); ?>" id="ecod-comment-form" role="form" data-ecod-comment>
                <?php if ($this->user->hasLogin()): ?>
                    <p class="ecod_respond__login">
                        登录身份：<a href="<?php $this->options->profileUrl(); ?>"><?php $this->user->screenName(); ?></a>
                        · <a href="<?php $this->options->logoutUrl(); ?>">退出</a>
                    </p>
                <?php else: ?>
                    <div class="ecod_field">
                        <label class="ecod_field__item">
                            <span class="ecod_field__label">昵称 *</span>
                            <input class="ecod_input" type="text" name="author" id="author" placeholder="怎么称呼你" value="<?php $this->remember('author'); ?>" required>
                        </label>
                        <label class="ecod_field__item">
                            <span class="ecod_field__label">邮箱<?php if ($this->options->commentsRequireMail): ?> *<?php endif; ?></span>
                            <input class="ecod_input" type="email" name="mail" id="mail" placeholder="不会公开" value="<?php $this->remember('mail'); ?>"<?php if ($this->options->commentsRequireMail): ?> required<?php endif; ?>>
                        </label>
                        <label class="ecod_field__item">
                            <span class="ecod_field__label">网站</span>
                            <input class="ecod_input" type="url" name="url" id="url" placeholder="选填" value="<?php $this->remember('url'); ?>">
                        </label>
                    </div>
                <?php endif; ?>

                <div class="ecod_editor">
                    <textarea class="ecod_textarea" name="text" id="textarea" placeholder="说点什么…支持 Markdown" required data-ecod-textarea><?php $this->remember('text'); ?></textarea>
                    <div class="ecod_editor__foot">
                        <div class="ecod_editor__tools">
                            <button type="button" class="ecod_editor__emoji" data-ecod-emoji-toggle aria-expanded="false" aria-label="插入表情">
                                <?php echo ecod_icon('smile', 14); ?><span>表情</span>
                            </button>
                            <small>支持 Markdown · 邮箱不会被公开</small>
                        </div>
                        <span class="ecod_editor__count" data-ecod-count>0 字</span>
                    </div>
                    <div class="ecod_emojis" data-ecod-emojis hidden></div>
                </div>

                <div class="ecod_formfoot">
                    <p class="ecod_respond__tip" role="alert" data-ecod-tip hidden></p>
                    <button type="submit" class="ecod_btn" data-ecod-submit>发表评论</button>
                </div>

                <?php if ($ecodTurnstile !== ''): ?>
                    <div class="ecod_turnstile" data-ecod-turnstile data-sitekey="<?php echo htmlspecialchars($ecodTurnstile, ENT_QUOTES, 'UTF-8'); ?>"></div>
                <?php endif; ?>

                <div class="ecod_hp" aria-hidden="true">
                    <label>请保持此栏为空<input type="text" name="ecod_hp" tabindex="-1" autocomplete="off"></label>
                </div>
                <input type="hidden" name="ecod_ts" value="<?php echo htmlspecialchars(ecod_comment_token()); ?>">
                <?php /* Typecho 反垃圾令牌：缺失时 Feedback::protect() 会直接退回上一页 */ ?>
                <input type="hidden" name="_" value="<?php echo htmlspecialchars($this->security->getToken($this->request->getRequestUrl())); ?>">
            </form>
        </div>
    <?php else: ?>
        <p class="ecod_author__desc" style="margin-top:12px">评论已关闭</p>
    <?php endif; ?>
</section>
