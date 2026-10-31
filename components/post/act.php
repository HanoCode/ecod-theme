<?php
/**
 * 文章页块 · 互动按钮
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodActLike = (int) ecod_p('like', 1) === 1;
$ecodActFav = (int) ecod_p('fav', 1) === 1;
$ecodActShare = (int) ecod_p('share', 1) === 1;
$ecodActReward = (int) ecod_p('reward', 0) === 1;
$ecodActAlipay = trim((string) ecod_p('alipay', ''));
$ecodActWechat = trim((string) ecod_p('wechat', ''));
/* 显示位置：bottom 文末动作条 / side 侧边悬浮栏（窄屏回落文末）/ both 两者都出 */
$ecodActMode = (string) ecod_p('mode', 'bottom');
if (!in_array($ecodActMode, ['bottom', 'side', 'both'], true)) {
    $ecodActMode = 'bottom';
}
/* 收款码缺失时不再隐藏按钮：开关打开即占位显示，弹层输出虚线占位框 */

if (!$ecodActLike && !$ecodActFav && !$ecodActShare && !$ecodActReward) {
    return;
}

/* 密码保护：未解锁前不展示互动条 */
if (!empty($this->hidden)) {
    return;
}

/* 模块由 ecod_render_block() 独立作用域渲染，拿不到 post.php 的模板局部变量，本地自算 */
$ecodPay = function_exists('ecod_pay_access') ? ecod_pay_access($this) : ['mode' => 'free', 'allow' => true];
$ecodPayLocked = $ecodPay['mode'] !== 'free' && !$ecodPay['allow'];

/* 发布日期：$this->date() 是 echo 方法，不能取值，改用 date 对象格式化 */
$ecodActDate = is_object($this->date) ? (string) $this->date->format('Y.m.d') : '';
if ($ecodActDate === '' && !empty($this->created)) {
    $ecodActDate = date('Y.m.d', (int) $this->created);
}
?>
<div class="ecod_article__act<?php echo $ecodActMode === 'side' ? ' is-side-mode' : ''; ?>" data-ecod-postact
     data-cid="<?php echo (int) $this->cid; ?>"
     data-url="<?php echo htmlspecialchars((string) $this->permalink, ENT_QUOTES, 'UTF-8'); ?>"
     data-title="<?php echo htmlspecialchars((string) $this->title, ENT_QUOTES, 'UTF-8'); ?>"
     data-site="<?php echo htmlspecialchars((string) $this->options->title, ENT_QUOTES, 'UTF-8'); ?>"
     data-date="<?php echo htmlspecialchars($ecodActDate, ENT_QUOTES, 'UTF-8'); ?>">
    <?php
    /* D2 点赞头像堆叠：取 uid 落库的最近 5 条；无 uid 记录时整块不输出（降级纯数字）。
       整行独立于按钮行（设计稿 §05 动作条） */
    if ($ecodActLike && ecod_option('ELikers', 'on') === 'on'):
        $ecodLikeNum = ecod_likes((int) $this->cid);
        $ecodLikers = ecod_post_likers((int) $this->cid, 5);
        if ($ecodLikers):
            $ecodLikersFirst = $ecodLikers[0];
            /* 名字是用户可控字段必须转义；数字段加粗（参考站文案口径） */
            $ecodLikersText = count($ecodLikers) > 1
                ? '@' . htmlspecialchars($ecodLikersFirst['name'], ENT_QUOTES, 'UTF-8') . ' 等 <b>' . (int) $ecodLikeNum . '</b> 人觉得不错'
                : '@' . htmlspecialchars($ecodLikersFirst['name'], ENT_QUOTES, 'UTF-8') . ' 觉得不错';
    ?>
    <button type="button" class="ecod_likers" data-ecod-likers aria-label="跳到评论区">
        <span class="ecod_likers__av" aria-hidden="true">
            <?php foreach ($ecodLikers as $ecodLiker): ?>
                <?php if ($ecodLiker['avatar'] !== ''): ?>
                    <?php /* data-initial：头像 URL 失效时 JS 兜底替换成同款首字渐变圆 */ ?>
                    <img src="<?php echo htmlspecialchars($ecodLiker['avatar'], ENT_QUOTES, 'UTF-8'); ?>" alt="" width="24" height="24" loading="lazy" decoding="async" data-initial="<?php echo htmlspecialchars(mb_substr($ecodLiker['name'], 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?>">
                <?php else: ?>
                    <i><?php echo htmlspecialchars(mb_substr($ecodLiker['name'], 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></i>
                <?php endif; ?>
            <?php endforeach; ?>
        </span>
        <span class="ecod_likers__text"><?php echo $ecodLikersText; ?></span>
    </button>
    <?php endif; endif; ?>

    <div class="ecod_article__actrow">
    <?php if ($ecodActLike): ?>
        <?php $ecodLikeNum = ecod_likes((int) $this->cid); ?>
        <button type="button" class="ecod_actbtn" data-ecod-act="like" data-cid="<?php echo (int) $this->cid; ?>" aria-pressed="false">
            <span class="ecod_actbtn__ico"><?php echo ecod_icon('heart', 20); ?></span>
            <span class="ecod-sr">点赞</span>
            <b class="ecod_actbtn__lb<?php echo $ecodLikeNum > 0 ? ' is-num' : ''; ?>" data-ecod-like-num="short"><?php echo $ecodLikeNum > 0 ? ecod_num_short($ecodLikeNum) : '点赞'; ?></b>
        </button>
    <?php endif; ?>
    <?php if ($ecodActFav): ?>
        <?php $ecodFavNum = ecod_favs_count((int) $this->cid); ?>
        <button type="button" class="ecod_actbtn" data-ecod-act="fav" aria-pressed="false">
            <span class="ecod_actbtn__ico"><?php echo ecod_icon('star', 20); ?></span>
            <span class="ecod-sr">收藏</span>
            <b class="ecod_actbtn__lb<?php echo $ecodFavNum > 0 ? ' is-num' : ''; ?>" data-ecod-fav-num="short"><?php echo $ecodFavNum > 0 ? ecod_num_short($ecodFavNum) : '收藏'; ?></b>
        </button>
    <?php endif; ?>
    <?php
    /* 分享：单入口弹层 = 社交平台（带 logo）+ 复制链接 + 生成分享图 */
    if ($ecodActShare):
        $ecodShareUrl = rawurlencode((string) $this->permalink);
        $ecodShareTitle = rawurlencode((string) $this->title);
        $ecodShareTargets = [
            'weibo' => ['微博', 'https://service.weibo.com/share/share.php?url=' . $ecodShareUrl . '&title=' . $ecodShareTitle],
            'x-twitter' => ['X', 'https://twitter.com/intent/tweet?url=' . $ecodShareUrl . '&text=' . $ecodShareTitle],
            'telegram' => ['Telegram', 'https://t.me/share/url?url=' . $ecodShareUrl . '&text=' . $ecodShareTitle],
        ];
        ?>
        <details class="ecod_share" data-ecod-sharebox>
            <summary class="ecod_actbtn" aria-label="分享文章">
                <span class="ecod_actbtn__ico"><?php echo ecod_icon('share', 20); ?></span>
                <b class="ecod_actbtn__lb">分享</b>
            </summary>
            <div class="ecod_share__pop">
                <div class="ecod_share__label">分享到</div>
                <div class="ecod_share__apps">
                    <?php foreach ($ecodShareTargets as $ecodShareKey => $ecodShareItem): ?>
                        <a class="ecod_share__app" data-net="<?php echo $ecodShareKey; ?>"
                           href="<?php echo htmlspecialchars($ecodShareItem[1], ENT_QUOTES, 'UTF-8'); ?>"
                           target="_blank" rel="noopener nofollow">
                            <?php echo ecod_icon($ecodShareKey, 22); ?>
                            <span><?php echo htmlspecialchars($ecodShareItem[0], ENT_QUOTES, 'UTF-8'); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div class="ecod_share__rows">
                    <button type="button" class="ecod_share__row" data-ecod-share>
                        <?php echo ecod_icon('link', 15); ?>
                        <span>复制链接</span>
                    </button>
                    <button type="button" class="ecod_share__row" data-ecod-share-wechat aria-haspopup="dialog">
                        <?php echo ecod_icon('wechat', 15); ?>
                        <span>微信扫码</span>
                    </button>
                    <button type="button" class="ecod_share__row" data-ecod-share-native hidden>
                        <?php echo ecod_icon('share', 15); ?>
                        <span>系统分享</span>
                    </button>
                    <button type="button" class="ecod_share__row" data-ecod-sharecard aria-haspopup="dialog">
                        <?php echo ecod_icon('image', 15); ?>
                        <span>生成分享图</span>
                    </button>
                </div>
                <div class="ecod_share__qr" data-ecod-share-qr hidden>
                    <canvas class="ecod_share__qrimg" width="132" height="132" aria-label="文章链接二维码"></canvas>
                    <span>微信扫一扫，在手机上打开<em>· 朋友圈可长按识别</em></span>
                </div>
            </div>
        </details>
    <?php endif; ?>
    <?php if ($ecodActReward): ?>
        <?php
        /* 收款码缺失：两个平台都输出占位框；只配一侧时仅显示已配置的一侧 */
        $ecodRewardEmpty = ($ecodActAlipay === '' && $ecodActWechat === '');
        $ecodRewardItems = [
            ['label' => '支付宝', 'src' => $ecodActAlipay],
            ['label' => '微信', 'src' => $ecodActWechat],
        ];
        ?>
        <details class="ecod_reward">
            <summary class="ecod_actbtn">
                <span class="ecod_actbtn__ico"><?php echo ecod_icon('heart', 20); ?></span>
                <b class="ecod_actbtn__lb">赞赏</b>
            </summary>
            <div class="ecod_reward__pop">
                <?php foreach ($ecodRewardItems as $ecodRewardItem): ?>
                    <?php if ($ecodRewardItem['src'] === ''): ?>
                        <?php if ($ecodRewardEmpty): ?>
                            <figure>
                                <div class="ecod_reward__empty" aria-label="<?php echo $ecodRewardItem['label']; ?>收款码待配置">
                                    <?php echo ecod_icon('image', 20); ?>
                                    <span>待配置</span>
                                </div>
                                <figcaption><?php echo $ecodRewardItem['label']; ?></figcaption>
                            </figure>
                        <?php endif; ?>
                    <?php else: ?>
                        <figure>
                            <img src="<?php echo htmlspecialchars($ecodRewardItem['src'], ENT_QUOTES, 'UTF-8'); ?>"
                                 alt="<?php echo $ecodRewardItem['label']; ?>收款码" width="132" height="132" loading="lazy" decoding="async">
                            <figcaption><?php echo $ecodRewardItem['label']; ?></figcaption>
                        </figure>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endif; ?>
    <?php
    /* 第 5 格 · 评论；第 6 格 · 报错（设计稿 §05 动作条 5 等分） */
    $ecodActComments = empty($this->hidden) && !$ecodPayLocked
        && ecod_option('EComment', 'on') !== 'off'
        && $this->allow('comment');
    $ecodActCmtNum = (int) $this->commentsNum;
    if ($ecodActComments):
    ?>
        <a class="ecod_actbtn" href="#comments">
            <span class="ecod_actbtn__ico"><?php echo ecod_icon('comment', 20); ?></span>
            <span class="ecod-sr">讨论</span>
            <b class="ecod_actbtn__lb<?php echo $ecodActCmtNum > 0 ? ' is-num' : ''; ?>"><?php echo $ecodActCmtNum > 0 ? $ecodActCmtNum . ' 讨论' : '讨论'; ?></b>
        </a>
    <?php endif; ?>
    <details class="ecod_report">
        <summary class="ecod_actbtn" aria-haspopup="dialog" aria-label="报告本文内容问题">
            <span class="ecod_actbtn__ico"><?php echo ecod_icon('alert', 20); ?></span>
            <b class="ecod_actbtn__lb">报错</b>
        </summary>
        <div class="ecod_report__pop">
            <div class="ecod_report__label">问题类型</div>
            <div class="ecod_report__opts" role="radiogroup" aria-label="问题类型">
                <?php
                /* 4 个原因单选（设计稿 §05 C4）：复用分享/赞赏弹层的定位与浮层阴影 */
                foreach (['内容有误' => 'wrong', '链接失效' => 'dead-link', '图片缺失' => 'no-image', '其他问题' => 'other'] as $ecodRepLabel => $ecodRepKey):
                ?>
                <button type="button" class="ecod_report__opt" data-ecod-report-opt="<?php echo $ecodRepKey; ?>" aria-pressed="false"><?php echo $ecodRepLabel; ?></button>
                <?php endforeach; ?>
            </div>
            <textarea class="ecod_report__note" data-ecod-report-note rows="2" maxlength="200" placeholder="补充说明（选填）"></textarea>
            <div class="ecod_report__foot">
                <input type="text" name="ecod_hp" data-ecod-report-hp tabindex="-1" autocomplete="off" aria-hidden="true">
                <button type="button" class="ecod_report__send ecod_btn" data-ecod-report-send disabled>提交反馈</button>
            </div>
            <p class="ecod_report__tip" data-ecod-report-tip hidden role="status"></p>
        </div>
    </details>
    </div>
</div>
<?php
/* 侧边悬浮栏（Nimbus 对标）：宽屏 sticky 竖栏 = 点赞 / 收藏 / 讨论 / 复制链接 /
   阅读进度竖条 / 返回顶部。按钮沿用 data-ecod-act + data-cid 委托，点赞收藏的
   状态回填与计数更新自动同步；窄屏（<1200px）由 CSS 整栏隐藏，操作回落文末动作条 */
if ($ecodActMode === 'side' || $ecodActMode === 'both'):
    $ecodRailCid = (int) $this->cid;
?>
<aside class="ecod_actsiderail" data-ecod-actsiderail aria-label="文章快捷操作">
    <?php if ($ecodActLike): ?>
    <button type="button" class="ecod_actsiderail__btn" data-ecod-act="like" data-cid="<?php echo $ecodRailCid; ?>" aria-pressed="false" title="点赞">
        <?php echo ecod_icon('heart', 18); ?>
        <span data-ecod-like-num="short"><?php echo ecod_num_short(ecod_likes($ecodRailCid)); ?></span>
        <span class="ecod-sr">点赞</span>
    </button>
    <?php endif; ?>
    <?php if ($ecodActFav): ?>
    <button type="button" class="ecod_actsiderail__btn" data-ecod-act="fav" data-cid="<?php echo $ecodRailCid; ?>" aria-pressed="false" title="收藏">
        <?php echo ecod_icon('star', 18); ?>
        <span data-ecod-fav-num="short"><?php echo ecod_num_short(ecod_favs_count($ecodRailCid)); ?></span>
        <span class="ecod-sr">收藏</span>
    </button>
    <?php endif; ?>
    <?php if ($ecodActComments ?? null): ?>
    <a class="ecod_actsiderail__btn" href="#comments" title="讨论">
        <?php echo ecod_icon('comment', 18); ?>
        <span><?php echo $ecodActCmtNum > 0 ? ecod_num_short($ecodActCmtNum) : '讨论'; ?></span>
    </a>
    <?php endif; ?>
    <?php if ($ecodActShare): ?>
    <button type="button" class="ecod_actsiderail__btn" data-ecod-share title="复制链接">
        <?php echo ecod_icon('link', 18); ?>
        <span>复制</span>
    </button>
    <?php endif; ?>
    <span class="ecod_actsiderail__line" aria-hidden="true"><i data-ecod-rail-fill></i></span>
    <button type="button" class="ecod_actsiderail__btn" data-ecod-rail-top title="返回顶部">
        <?php echo ecod_icon('arrow-up', 18); ?>
        <span>顶部</span>
    </button>
</aside>
<?php endif; ?>
