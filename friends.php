<?php
/**
 * 友情链接
 *
 * @package custom
 * @author  Ecod
 * @since   2026-10-29
 * @version 2.0.0
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$this->need('public/header.php');

ob_start();
$this->content();
$ecodFriendHtml = (string) ob_get_clean();

$ecodFriends = ecod_friend_links($ecodFriendHtml);
/* 说明文字：去掉「名称 | 链接 | 描述」数据行后剩下的内容 */
$ecodNoteHtml = ecod_intro_text($ecodFriendHtml);
$ecodHasNote = trim(strip_tags($ecodNoteHtml)) !== '';

/* 申请入口开关：页头小入口 + 底部申请区共用 */
$ecodApplyOpen = ecod_friend_apply_enabled();

$ecodFriendMeta = '<span>' . ecod_icon('link', 13) . count($ecodFriends) . ' 个站点</span>';
if ($ecodApplyOpen) {
    $ecodFriendMeta .= '<button type="button" class="ecod_pagehero__apply" data-ecod-flink-open>'
        . ecod_icon('plus', 12) . '申请友链</button>';
}

/* 审核面板只给管理员看 */
$ecodIsAdmin = ecod_friend_app_is_admin();
$ecodPending = $ecodIsAdmin ? ecod_friend_apps_list('pending', 20) : [];
$ecodApiUrl = ecod_api_url();
$ecodToken = ecod_account_token();

/* 本站信息：弹窗里给申请人一键复制，省得手抄 */
$ecodSiteName = trim((string) ecod_option('ELogo', ''));
if ($ecodSiteName === '') {
    $ecodSiteName = trim((string) $this->options->title);
}
$ecodSiteUrl = rtrim((string) $this->options->siteUrl, '/');
$ecodSiteDesc = trim((string) $this->options->description);
$ecodSiteHost = (string) parse_url($ecodSiteUrl, PHP_URL_HOST);
$ecodSiteIcon = trim((string) ecod_option('EFavicon', ''));
$ecodCopyLine = implode(' | ', array_values(array_filter([$ecodSiteName, $ecodSiteUrl, $ecodSiteDesc], static function ($v) {
    return trim((string) $v) !== '';
})));
/* 邮件没配置好就别承诺「结果发到邮箱」 */
$ecodMailReady = function_exists('ecod_mail_ready') && ecod_mail_ready();
?>
<main class="ecod_main" id="ecod-main">
    <?php /* 模块 · 内容区上方（模块管理台） */ ecod_widgets_render("top_content", $this); ?>

    <nav class="ecod_crumb" aria-label="面包屑">
        <a href="<?php $this->options->siteUrl(); ?>"><?php echo ecod_icon('home', 12); ?>首页</a>
        <span class="ecod_crumb__sep" aria-hidden="true"><?php echo ecod_icon('chevron', 11); ?></span>
        <span aria-current="page"><?php $this->title(); ?></span>
    </nav>

    <?php ecod_page_hero('友链', (string) $this->title, '一些常去的地方，也欢迎交换。', $ecodFriendMeta); ?>

    <?php if ($ecodFriends): ?>
        <div class="ecod_friendgrid">
            <?php foreach ($ecodFriends as $ecodIndex => $ecodFriend): ?>
                <?php
                $ecodHost = parse_url($ecodFriend['url'], PHP_URL_HOST) ?: $ecodFriend['url'];
                $ecodTint = ecod_card_tint($ecodIndex + 3);
                $ecodName = htmlspecialchars($ecodFriend['name'], ENT_QUOTES, 'UTF-8');
                ?>
                <a class="ecod_friend" href="<?php echo htmlspecialchars($ecodFriend['url'], ENT_QUOTES, 'UTF-8'); ?>"
                   target="_blank" rel="noopener nofollow" title="<?php echo $ecodName; ?>">
                    <span class="ecod_friend__avatar" style="background:<?php echo $ecodTint; ?>">
                        <span aria-hidden="true"><?php echo mb_substr($ecodFriend['name'], 0, 1, 'UTF-8'); ?></span>
                        <?php if (!empty($ecodFriend['avatar'])): ?>
                            <img src="<?php echo htmlspecialchars($ecodFriend['avatar'], ENT_QUOTES, 'UTF-8'); ?>"
                                 alt="<?php echo $ecodName; ?>" width="42" height="42" loading="lazy"
                                 onerror="this.remove()">
                        <?php endif; ?>
                    </span>
                    <span class="ecod_friend__main">
                        <strong class="ecod_friend__name"><?php echo $ecodName; ?></strong>
                        <span class="ecod_friend__host"><?php echo htmlspecialchars($ecodHost, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php if (!empty($ecodFriend['desc'])): ?>
                            <span class="ecod_friend__desc"><?php echo htmlspecialchars($ecodFriend['desc'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="ecod_friend__go" aria-hidden="true"><?php echo ecod_icon('arrow-up-right', 14); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="ecod_empty">
            <div class="ecod_empty__icon" aria-hidden="true"><?php echo ecod_icon('link', 28); ?></div>
            <h3>还没有添加友链</h3>
            <p>点下方「一键申请」提交站点信息，通过后会出现在这里</p>
        </div>
    <?php endif; ?>

    <?php if ($ecodHasNote || $ecodApplyOpen): ?>
        <section class="ecod_apply" id="ecod-apply">
            <div class="ecod_apply__head">
                <div class="ecod_apply__htext">
                    <h2><?php echo ecod_icon('plus', 15); ?>申请友链</h2>
                    <p>原创内容、可正常访问、无弹窗广告。通过后会尽快添加。</p>
                </div>
                <?php if ($ecodApplyOpen): ?>
                    <button type="button" class="ecod-btn ecod_apply__btn" data-ecod-flink-open>
                        <?php echo ecod_icon('link', 13); ?>一键申请
                    </button>
                <?php endif; ?>
            </div>
            <?php if ($ecodHasNote): ?>
                <div class="ecod_apply__body ecod_content" data-ecod-content>
                    <?php echo $ecodNoteHtml; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($ecodPending): ?>
        <section class="ecod_apply ecod_review" id="ecod-review">
            <div class="ecod_apply__head">
                <div class="ecod_apply__htext">
                    <h2><?php echo ecod_icon('check-circle', 15); ?>待审核申请
                        <span class="ecod_review__num"><?php echo count($ecodPending); ?></span>
                    </h2>
                    <p>只有管理员能看到这块。通过后会按「名称 | 链接 | 描述 | 头像」写进页面正文，好友刷新即见。</p>
                </div>
            </div>
            <ul class="ecod_review__list" data-ecod-review data-ecod-token="<?php echo htmlspecialchars($ecodToken, ENT_QUOTES, 'UTF-8'); ?>">
                <?php foreach ($ecodPending as $ecodApp): ?>
                    <li class="ecod_review__item" data-ecod-review-item="<?php echo (int) $ecodApp['id']; ?>">
                        <span class="ecod_review__avatar" aria-hidden="true">
                            <?php echo htmlspecialchars(mb_substr((string) $ecodApp['name'], 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?>
                            <?php if (!empty($ecodApp['avatar'])): ?>
                                <img src="<?php echo htmlspecialchars((string) $ecodApp['avatar'], ENT_QUOTES, 'UTF-8'); ?>"
                                     alt="" width="34" height="34" loading="lazy" onerror="this.remove()">
                            <?php endif; ?>
                        </span>
                        <span class="ecod_review__main">
                            <strong><?php echo htmlspecialchars((string) $ecodApp['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <a href="<?php echo htmlspecialchars((string) $ecodApp['url'], ENT_QUOTES, 'UTF-8'); ?>"
                               target="_blank" rel="noopener nofollow"><?php echo htmlspecialchars((string) $ecodApp['host'], ENT_QUOTES, 'UTF-8'); ?></a>
                            <?php if ((string) $ecodApp['descr'] !== ''): ?>
                                <em><?php echo htmlspecialchars((string) $ecodApp['descr'], ENT_QUOTES, 'UTF-8'); ?></em>
                            <?php endif; ?>
                            <span class="ecod_review__meta"><?php
                                echo htmlspecialchars((string) $ecodApp['mail'], ENT_QUOTES, 'UTF-8')
                                    . ' · ' . (new \Typecho\Date((int) $ecodApp['created']))->format('m-d H:i');
                            ?></span>
                        </span>
                        <span class="ecod_review__ops">
                            <button type="button" class="ecod-btn" data-ecod-review-act="approve">通过</button>
                            <button type="button" class="ecod-btn ecod-btn--ghost" data-ecod-review-act="reject">忽略</button>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>


    <?php /* 模块 · 内容区下方（模块管理台） */ ecod_widgets_render("bottom_content", $this); ?>
</main>

<?php if ($ecodApplyOpen): ?>
<div class="ecod_fmodal" data-ecod-flink hidden>
    <div class="ecod_fmodal__mask" data-ecod-flink-close></div>
    <div class="ecod_fmodal__panel" role="dialog" aria-modal="true" aria-labelledby="ecod-flink-title">
        <button type="button" class="ecod_fmodal__close" data-ecod-flink-close aria-label="关闭申请弹窗">
            <?php echo ecod_icon('x', 17); ?>
        </button>

        <form class="ecod_fmodal__form" data-ecod-flink-form method="post" action="#"
              data-ecod-token="<?php echo htmlspecialchars($ecodToken, ENT_QUOTES, 'UTF-8'); ?>">
            <header class="ecod_fmodal__head">
                <h2 id="ecod-flink-title">申请友链</h2>
                <p>填好信息即可提交，通过后自动出现在列表里。</p>
            </header>

            <div class="ecod_fmodal__self">
                <span class="ecod_fmodal__selficon" aria-hidden="true">
                    <?php if ($ecodSiteIcon !== ''): ?>
                        <img src="<?php echo htmlspecialchars($ecodSiteIcon, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="22" height="22" loading="lazy" onerror="this.remove()">
                    <?php else: ?>
                        <?php echo ecod_icon('link', 13); ?>
                    <?php endif; ?>
                </span>
                <span class="ecod_fmodal__selftext">
                    <b>请先添加本站</b>
                    <em><?php echo htmlspecialchars($ecodSiteName, ENT_QUOTES, 'UTF-8'); ?><?php echo $ecodSiteHost !== '' ? ' · ' . htmlspecialchars($ecodSiteHost, ENT_QUOTES, 'UTF-8') : ''; ?></em>
                </span>
                <button type="button" class="ecod_fmodal__selfcopy"
                        data-ecod-flink-copy="<?php echo htmlspecialchars($ecodCopyLine, ENT_QUOTES, 'UTF-8'); ?>">复制</button>
            </div>

            <label class="ecod_fmodal__field">
                <span>站点名称 <i>*</i></span>
                <input type="text" name="name" required maxlength="40" autocomplete="off"
                       placeholder="例如：小魏先生的博客">
            </label>

            <label class="ecod_fmodal__field">
                <span>站点地址 <i>*</i></span>
                <input type="text" name="url" required maxlength="200" inputmode="url" autocomplete="off"
                       placeholder="example.com 或 https://example.com">
            </label>

            <label class="ecod_fmodal__field">
                <span>一句话简介 <em>选填</em></span>
                <input type="text" name="desc" maxlength="60" autocomplete="off" placeholder="显示在名称下方，10 字内最好">
            </label>

            <label class="ecod_fmodal__field">
                <span>头像地址 <em>选填</em></span>
                <input type="text" name="avatar" maxlength="200" inputmode="url" autocomplete="off"
                       placeholder="留空则使用站点图标">
            </label>

            <label class="ecod_fmodal__field">
                <span>联系邮箱 <i>*</i></span>
                <input type="email" name="mail" required maxlength="120" inputmode="email" autocomplete="off"
                       placeholder="必填，用于接收审核结果通知">
            </label>

            <label class="ecod_fmodal__field">
                <span>验证码 <i>*</i></span>
                <span class="ecod_login__cap">
                    <input type="text" name="captcha" required maxlength="4" autocomplete="off" spellcheck="false"
                           inputmode="latin" placeholder="4 位字符">
                    <img class="ecod_login__capimg" data-ecod-captcha
                         src="<?php echo htmlspecialchars($ecodApiUrl, ENT_QUOTES, 'UTF-8'); ?>?action=captcha&amp;t=<?php echo time(); ?>"
                         alt="验证码，点击刷新" width="96" height="40">
                </span>
            </label>

            <input type="text" name="website" class="ecod_fmodal__hp" tabindex="-1" autocomplete="off" aria-hidden="true">

            <p class="ecod_login__msg" data-ecod-flink-msg hidden></p>

            <button type="submit" class="ecod-btn ecod_fmodal__submit">提交申请</button>
        </form>

        <div class="ecod_fmodal__done" data-ecod-flink-done data-ecod-mail="<?php echo $ecodMailReady ? '1' : '0'; ?>" hidden>
            <span class="ecod_fmodal__doneicon" aria-hidden="true"><?php echo ecod_icon('check-circle', 26); ?></span>
            <h2>申请已提交</h2>
            <p data-ecod-flink-donetext><?php echo $ecodMailReady
                ? '审核通过后会出现在友链列表里，结果会发到你的邮箱。'
                : '审核通过后就会出现在友链列表里。'; ?></p>
            <button type="button" class="ecod-btn ecod-btn--ghost ecod_fmodal__donebtn" data-ecod-flink-close>知道了</button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php $this->need('public/aside.php'); ?>
<?php $this->need('public/footer.php'); ?>
