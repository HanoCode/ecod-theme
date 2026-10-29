<?php
/**
 * 文章页块 · 互动按钮
 *
 * @package Ecod Theme
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
/* 收款码缺失时不再隐藏按钮：开关打开即占位显示，弹层输出虚线占位框 */

if (!$ecodActLike && !$ecodActFav && !$ecodActShare && !$ecodActReward) {
    return;
}

/* 密码保护：未解锁前不展示互动条 */
if (!empty($this->hidden)) {
    return;
}

/* 发布日期：$this->date() 是 echo 方法，不能取值，改用 date 对象格式化 */
$ecodActDate = is_object($this->date) ? (string) $this->date->format('Y.m.d') : '';
if ($ecodActDate === '' && !empty($this->created)) {
    $ecodActDate = date('Y.m.d', (int) $this->created);
}
?>
<div class="ecod_article__act" data-ecod-postact
     data-cid="<?php echo (int) $this->cid; ?>"
     data-url="<?php echo htmlspecialchars((string) $this->permalink, ENT_QUOTES, 'UTF-8'); ?>"
     data-title="<?php echo htmlspecialchars((string) $this->title, ENT_QUOTES, 'UTF-8'); ?>"
     data-site="<?php echo htmlspecialchars((string) $this->options->title, ENT_QUOTES, 'UTF-8'); ?>"
     data-date="<?php echo htmlspecialchars($ecodActDate, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($ecodActLike): ?>
        <?php $ecodLikeNum = ecod_likes((int) $this->cid); ?>
        <button type="button" class="ecod_actbtn" data-ecod-act="like" data-cid="<?php echo (int) $this->cid; ?>" aria-pressed="false">
            <?php echo ecod_icon('heart', 15); ?>
            <span data-ecod-like-num="full"><?php echo $ecodLikeNum > 0 ? (int) $ecodLikeNum . ' 赞' : '点赞'; ?></span>
        </button>
        <?php
        /* D2 点赞头像堆叠：取 uid 落库的最近 5 条；无 uid 记录时整块不输出（降级纯数字） */
        if (ecod_option('ELikers', 'on') === 'on'):
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
                        <img src="<?php echo htmlspecialchars($ecodLiker['avatar'], ENT_QUOTES, 'UTF-8'); ?>" alt="" width="24" height="24" loading="lazy" decoding="async">
                    <?php else: ?>
                        <i><?php echo htmlspecialchars(mb_substr($ecodLiker['name'], 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></i>
                    <?php endif; ?>
                <?php endforeach; ?>
            </span>
            <span class="ecod_likers__text"><?php echo $ecodLikersText; ?></span>
        </button>
        <?php endif; endif; ?>
    <?php endif; ?>
    <?php if ($ecodActFav): ?>
        <button type="button" class="ecod_actbtn" data-ecod-act="fav" aria-pressed="false">
            <?php echo ecod_icon('star', 15); ?>
            <span>收藏</span>
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
                <?php echo ecod_icon('share', 15); ?>
                <span>分享</span>
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
                    <button type="button" class="ecod_share__row" data-ecod-share-native hidden>
                        <?php echo ecod_icon('share', 15); ?>
                        <span>系统分享</span>
                    </button>
                    <button type="button" class="ecod_share__row" data-ecod-sharecard aria-haspopup="dialog">
                        <?php echo ecod_icon('image', 15); ?>
                        <span>生成分享图</span>
                    </button>
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
                <?php echo ecod_icon('heart', 15); ?>
                <span>赞赏</span>
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
</div>
