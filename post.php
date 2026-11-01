<?php
/**
 * 文章页
 *
 * 互动按钮 / 上下篇 / 相关推荐在「模块管理台」里按位置摆放（见 docs/dev/design-proposal.html §31）。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @since   2026-10-29
 * @version 2.0.0
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$this->need('public/header.php');

$ecodLocked = (bool) $this->hidden;
/* 已设密码（与当前身份无关），用于始终给出锁标记 */
$ecodHasPwd = ecod_has_password($this);
/* 付费门禁：与短代码、摘要、Feed 共用同一判定 */
$ecodPay = ecod_pay_access($this);
$ecodPayLocked = $ecodPay['mode'] !== 'free' && !$ecodPay['allow'];
/* 密码文章、非 GET 请求与 cookie 已记录的访问不计数 */
$ecodViews = ecod_views($this, true);

/* 字数与阅读时长：中文按字、英文按词，400 字/分钟估计；受限内容不统计，避免暴露篇幅 */
$ecodWords = ($ecodLocked || $ecodPayLocked) ? 0 : ecod_word_count((string) $this->content);
$ecodMinutes = $ecodWords > 0 ? max(1, (int) ceil($ecodWords / 400)) : 0;

/* 长期未更新提醒，0 表示关闭 */
$ecodStaleDays = (int) ecod_option('EStale', 180);
$ecodStale = 0;
if ($ecodStaleDays > 0) {
    $ecodStale = (int) floor((time() - (int) ($this->modified ?: $this->created)) / 86400);
    if ($ecodStale < $ecodStaleDays) {
        $ecodStale = 0;
    }
}

/* 头卡封面（设计稿 §05）：thumb 字段 / 正文首图 / 图池，banner 预裁 960×540；
   锁定或付费锁不泄封面（内容未授权） */
$ecodCover = (!$ecodLocked && !$ecodPayLocked) ? (ecod_thumbnails($this, 1, 'banner')[0] ?? '') : '';

/* 图文笔记（docs/dev/NOTE-ARTICLE-DESIGN.html）：单篇字段开关 + 头部外壳换装。
   笔记模式下封面并入图集，不再单独输出横幅封面 */
$ecodNote = ecod_note_on($this);
$ecodNoteData = $ecodNote ? ecod_note_options($this) : [];

/* 头卡作者条：与文末 authorbox、作者主页共用同一数据源 */
if (!function_exists('ecod_user_card')) {
    require_once __DIR__ . '/core/user/meta.php';
}
$ecodHeadUid = 0;
try {
    $ecodHeadUid = (int) $this->author->uid;
} catch (\Throwable $e) {
}
$ecodHeadCard = $ecodHeadUid > 0 ? ecod_user_card($ecodHeadUid) : null;
$ecodHeadAuthorUrl = $ecodHeadUid > 0 ? ecod_user_archive_url($ecodHeadUid) : '';

/* 文末 authorbox 模块启用时，头卡不再输出关注按钮（设计稿 §06：同一作者只留一个关注入口） */
$ecodHeadFollow = true;
foreach (ecod_widget_area_order('bottom_content', 'single') as $ecodStackArea) {
    foreach (ecod_widget_instances($ecodStackArea) as $ecodStackInst) {
        if (($ecodStackInst['type'] ?? '') === 'authorbox' && !empty($ecodStackInst['on'])) {
            $ecodHeadFollow = false;
            break 2;
        }
    }
}
/* 面包屑：吸顶（ECrumbSticky）；阅读工具条形态 crumb 时整条内嵌到面包屑右侧 */
$ecodCrumbSticky = ecod_option('ECrumbSticky', 'off') === 'on' ? ' is-sticky' : '';
$ecodCrumbEmbed = ecod_option('EReadBarMode', 'top') === 'crumb';
?>
<main class="ecod_main" id="ecod-main">
    <?php if (ecod_layout_render_page($this)): ?>
        <?php /* 页面构建器布局文档接管：整页由板块文档输出 */ ?>
    <?php else: ?>

    <nav class="ecod_crumb<?php echo $ecodCrumbSticky; ?>" aria-label="面包屑">
        <a class="ecod_crumb__back" href="javascript:history.back()" aria-label="返回"><?php echo ecod_icon('chevron', 13); ?></a>
        <a href="<?php $this->options->siteUrl(); ?>"><?php echo ecod_icon('home', 12); ?>首页</a>
        <?php if (!empty($this->categories)): ?>
            <?php $ecodCat = $this->categories[0]; ?>
            <span class="ecod_crumb__sep" aria-hidden="true">/</span>
            <a href="<?php echo htmlspecialchars(is_array($ecodCat) ? $ecodCat['permalink'] : $ecodCat->permalink); ?>">
                <?php echo htmlspecialchars(is_array($ecodCat) ? $ecodCat['name'] : $ecodCat->name); ?>
            </a>
        <?php endif; ?>
        <span class="ecod_crumb__sep" aria-hidden="true">/</span>
        <span aria-current="page">正文</span>
        <?php if ($ecodCrumbEmbed): ?>
            <?php $ecodReadbarEmbed = true; include __DIR__ . '/components/post/tts.php'; ?>
        <?php endif; ?>
    </nav>

    <article class="ecod_article<?php echo $ecodNote ? ' ecod_noteart' : ''; ?><?php echo $ecodLocked ? ' is-locked' : ''; ?>" data-ecod-article
             data-cid="<?php echo (int) $this->cid; ?>"
             data-url="<?php echo htmlspecialchars((string) $this->permalink, ENT_QUOTES, 'UTF-8'); ?>"
             data-title="<?php echo htmlspecialchars((string) $this->title, ENT_QUOTES, 'UTF-8'); ?>">

        <?php if ($ecodCover !== '' && !$ecodNote): ?>
            <figure class="ecod_article__cover">
                <img src="<?php echo htmlspecialchars($ecodCover, ENT_QUOTES, 'UTF-8'); ?>" alt=""
                     width="960" height="540" fetchpriority="high" decoding="async">
            </figure>
        <?php endif; ?>

        <?php if ($ecodNote): ?>
            <?php /* 互动条锚点须在卡内最前：桌面 sticky 竖排从顶部开始跟随滚动 */ ?>
            <?php include __DIR__ . '/components/post/note-bar.php'; ?>
            <?php include __DIR__ . '/components/post/note-head.php'; ?>
        <?php else: ?>
        <header class="ecod_article__head">
            <?php if (!empty($this->categories) || $ecodHasPwd || $ecodPay['mode'] !== 'free'): ?>
                <div class="ecod_article__badges">
                    <?php if (!empty($this->categories)): ?>
                        <?php foreach ((array) $this->categories as $ecodCatItem): ?>
                            <?php
                            $ecodCatName = is_array($ecodCatItem) ? ($ecodCatItem['name'] ?? '') : ($ecodCatItem->name ?? '');
                            $ecodCatHref = is_array($ecodCatItem) ? ($ecodCatItem['permalink'] ?? '') : ($ecodCatItem->permalink ?? '');
                            if ($ecodCatName === '') {
                                continue;
                            }
                            ?>
                            <a class="ecod_article__cat" href="<?php echo htmlspecialchars((string) $ecodCatHref, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $ecodCatName, ENT_QUOTES, 'UTF-8'); ?></a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if ($ecodHasPwd): ?>
                        <span class="ecod_article__locked"><?php echo ecod_icon('lock', 13); ?><?php echo $ecodLocked ? '密码保护' : '含访问密码'; ?></span>
                    <?php endif; ?>
                    <?php if ($ecodPay['mode'] !== 'free'): ?>
                        <span class="ecod_article__paid"><?php echo $ecodPay['mode'] === 'vip' ? ecod_icon('star', 13) . '会员' : '¥ 付费'; ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <h1 class="ecod_article__title">
                <?php if ($ecodLocked): ?><span class="ecod_article__locktitle" aria-hidden="true"><?php echo ecod_icon('lock', 20); ?></span><?php endif; ?>
                <?php $this->title(); ?>
            </h1>

            <?php if ($ecodHeadCard): ?>
                <div class="ecod_article__author">
                    <span class="ecod_article__ava" aria-hidden="true">
                        <?php if ($ecodHeadCard['avatar'] !== ''): ?>
                            <img src="<?php echo htmlspecialchars((string) $ecodHeadCard['avatar'], ENT_QUOTES, 'UTF-8'); ?>" alt="" width="36" height="36" loading="lazy" decoding="async">
                        <?php else: ?>
                            <i><?php echo htmlspecialchars(mb_substr((string) $ecodHeadCard['name'], 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></i>
                        <?php endif; ?>
                    </span>
                    <span class="ecod_article__who">
                        <span class="ecod_article__nm">
                            <a href="<?php echo htmlspecialchars($ecodHeadAuthorUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $ecodHeadCard['name'], ENT_QUOTES, 'UTF-8'); ?></a>
                            <?php if ((string) $ecodHeadCard['group'] !== ''): ?>
                                <em><?php echo htmlspecialchars((string) $ecodHeadCard['group'], ENT_QUOTES, 'UTF-8'); ?></em>
                            <?php endif; ?>
                        </span>
                        <?php if ((string) $ecodHeadCard['intro'] !== ''): ?>
                            <span class="ecod_article__bio"><?php echo htmlspecialchars((string) $ecodHeadCard['intro'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </span>
                    <?php if ($ecodHeadFollow) { echo ecod_user_follow_btn($ecodHeadUid, ecod_viewer_uid()); } ?>
                </div>
            <?php endif; ?>

            <?php
            /* 元信息两行（设计稿 §06 C1）：主行只留 时间 / 阅读 / 评论（可点）；
               次行收纳 字数·时长·分类·更新于，纯口径文字，锁定 / 付费锁时缺字数不出该项 */
            $ecodSecItems = [];
            if ($ecodWords > 0) {
                $ecodSecItems[] = '<span title="' . $ecodWords . ' 字"><b>' . ecod_num_short($ecodWords) . '</b> 字 · 约 <b>' . $ecodMinutes . '</b> 分钟</span>';
            }
            $ecodSecCatName = '';
            $ecodSecCatHref = '';
            if (!empty($this->categories)) {
                $ecodSecCat = $this->categories[0];
                $ecodSecCatName = (string) (is_array($ecodSecCat) ? ($ecodSecCat['name'] ?? '') : ($ecodSecCat->name ?? ''));
                $ecodSecCatHref = (string) (is_array($ecodSecCat) ? ($ecodSecCat['permalink'] ?? '') : ($ecodSecCat->permalink ?? ''));
            }
            if ($ecodSecCatName !== '') {
                $ecodSecItems[] = $ecodSecCatHref !== ''
                    ? '<span>分类：<a href="' . htmlspecialchars($ecodSecCatHref, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($ecodSecCatName, ENT_QUOTES, 'UTF-8') . '</a></span>'
                    : '<span>分类：' . htmlspecialchars($ecodSecCatName, ENT_QUOTES, 'UTF-8') . '</span>';
            }
            $ecodSecUpdated = ((int) ($this->modified ?: 0) > (int) $this->created)
                ? date('m-d', (int) $this->modified) : '';
            if ($ecodSecUpdated !== '') {
                $ecodSecItems[] = '<span>更新于 ' . $ecodSecUpdated . '</span>';
            }
            ?>
            <div class="ecod_article__meta">
                <span><?php echo ecod_icon('calendar', 13); ?><time datetime="<?php $this->date('c'); ?>"><?php $this->date('Y年m月d日'); ?></time></span>
                <i class="ecod_meta-sep" aria-hidden="true">·</i>
                <span><?php echo ecod_icon('eye', 13); ?><?php echo ecod_num_short($ecodViews); ?> 阅读</span>
                <i class="ecod_meta-sep" aria-hidden="true">·</i>
                <a href="#comments"><?php echo ecod_icon('comment', 13); ?><?php echo ecod_comment_count((int) $this->cid); ?> 评论</a>
            </div>
            <?php if ($ecodSecItems): ?>
            <div class="ecod_article__metasec">
                <?php foreach ($ecodSecItems as $ecodSecIndex => $ecodSecItem): ?>
                    <?php if ($ecodSecIndex > 0): ?><i class="ecod_meta-sep" aria-hidden="true">·</i><?php endif; ?>
                    <?php echo $ecodSecItem; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </header>
        <?php endif; /* 笔记模式 / 普通头卡 */ ?>

        <?php if ($ecodLocked): ?>
            <div class="ecod_locknote">
                <?php echo ecod_icon('lock', 16); ?>
                <div>
                    <b>这是一篇受保护的文章</b>
                    <p>正文已加密，输入正确的密码后即可继续阅读。</p>
                </div>
            </div>
        <?php elseif ($ecodHasPwd): ?>
            <div class="ecod_lockhint">
                <?php echo ecod_icon('lock', 14); ?>
                <span>该文章设有访问密码：你当前以管理员/作者身份浏览所以直接可见，普通访客需输入密码后才能阅读（可用无痕窗口验证）。</span>
            </div>
        <?php endif; ?>

        <?php if ($ecodStale > 0): ?>
            <aside class="ecod_stale" role="note">
                <?php echo ecod_icon('alert', 16); ?>
                <p>这篇写于 <?php echo max(1, (int) floor((time() - (int) $this->created) / 86400)); ?> 天前，最近一次更新是 <?php echo $ecodStale; ?> 天前。
                    <?php if ($ecodWords > 0): ?>文中提到的工具、价格或做法可能已经变化，请酌情参考。<?php endif; ?>
                </p>
            </aside>
        <?php endif; ?>

        <?php /* 密码锁与付费锁都隐藏摘要卡：付费未授权时不展示由全文生成的摘要 */ ?>
        <?php if (!$ecodLocked && !$ecodPayLocked && ecod_option('EAI', 'off') === 'on'): ?>
            <section class="ecod_ai ecod_ai--sum" data-ecod-ai data-cid="<?php echo (int) $this->cid; ?>" hidden>
                <header class="ecod_ai__head">
                    <b><span class="ecod_ai__logo" aria-hidden="true">AI</span>AI 摘要</b>
                    <small data-ecod-ai-tag hidden></small>
                    <?php if (ecod_ai_ask_enabled()): ?>
                        <button type="button" class="ecod_ai__ask" data-ecod-aiask-open="post" data-cid="<?php echo (int) $this->cid; ?>">就本文追问<?php echo ecod_icon('arrow-right', 12); ?></button>
                    <?php endif; ?>
                    <span class="ecod_ai__ball" data-ecod-ai-ball aria-hidden="true"></span>
                </header>
                <div class="ecod_ai__body" data-ecod-ai-body aria-busy="true">
                    <i></i><i></i><i style="width:62%"></i>
                </div>
                <footer class="ecod_ai__foot">
                    <span>由 AI 生成，仅供参考</span>
                    <span data-ecod-ai-count hidden></span>
                </footer>
            </section>
        <?php endif; ?>

        <?php ecod_widgets_render('top_content', $this); ?>

        <?php
        /* 已解锁：只显示一条「已购买 / 会员权益」状态 */
        if (!$ecodPayLocked && $ecodPay['mode'] !== 'free') {
            echo ecod_pay_box($this, $ecodPay);
        }

        /* 付费卡位置：top 正文前 / bottom 正文后 / both 两处 / off 不显示 */
        $ecodPayBoxPos = (string) ecod_option('EPayBox', 'top');
        if ($ecodPayLocked && in_array($ecodPayBoxPos, ['top', 'both'], true)) {
            echo ecod_pay_box($this, $ecodPay);
        }
        ?>
        <div class="ecod_content<?php echo $ecodLocked ? ' is-locked' : ''; ?><?php echo $ecodPayLocked ? ' is-paylocked' : ''; ?><?php echo $ecodNote ? ' is-note' : ''; ?>"<?php
            /* 正文排版变量：阅读设置的字号/行高，笔记模式再补栏宽上限 */
            $ecodProseStyle = ecod_prose_style();
            if ($ecodNote) {
                $ecodProseStyle .= '--ecod-note-w:' . (int) $ecodNoteData['width'] . 'px;';
            }
            if ($ecodProseStyle !== '') {
                echo ' style="' . $ecodProseStyle . '"';
            }
        ?> data-ecod-content>
            <?php
            /* 短代码在主题层处理：Typecho 的 contentEx 钩子按运行时子类分发，
               只注册基类会漏掉，这里直接接管输出更可靠。
               付费未授权时先按试读截断，再进管线 —— 付费段里的 [gallery] [video] 不会被解析。 */
            ob_start();
            $this->content();
            $ecodRaw = (string) ob_get_clean();

            if ($ecodPayLocked) {
                $ecodRaw = ecod_pay_teaser_html($ecodRaw, (int) ecod_option('EPayTeaser', 80));
            }

            echo ecod_go_rewrite(ecod_lazy_images(ecod_keywords_apply(ecod_linkcard_apply(ecod_oembed_apply(ecod_shortcode_apply($ecodRaw, $this)))), (string) $this->title));
            ?>
        </div>

        <?php
        /* 显示更多（§33）：长文按高度折叠 + 渐隐遮罩，点按钮展开。
           正文仍完整在 HTML 里（只是 CSS 收起），SEO 可正常抓取；
           锁定 / 付费截断的文章已有各自的展开入口，不叠加。 */
        $ecodMoreScope = (string) ecod_option('EShowMore', 'off');
        if (!$ecodLocked && !$ecodPayLocked && in_array($ecodMoreScope, ['mobile', 'all'], true)):
            $ecodMoreH = max(400, (int) ecod_option('EShowMoreH', 1200));
        ?>
        <button type="button" class="ecod_morebtn" data-ecod-morebtn
                data-scope="<?php echo htmlspecialchars($ecodMoreScope, ENT_QUOTES, 'UTF-8'); ?>"
                data-h="<?php echo $ecodMoreH; ?>"
                data-text="<?php echo htmlspecialchars((string) ecod_option('EShowMoreText', '展开全文'), ENT_QUOTES, 'UTF-8'); ?>"
                hidden>
            <span>展开全文</span><?php echo ecod_icon('chevron', 14); ?>
        </button>
        <?php endif; ?>

        <?php
        if ($ecodPayLocked && in_array($ecodPayBoxPos, ['bottom', 'both'], true)) {
            echo ecod_pay_box($this, $ecodPay, $ecodPayBoxPos === 'both');
        }
        ?>

        <?php if ($ecodNote && !$ecodLocked && !$ecodPayLocked && !empty($ecodNoteData['tagsOn']) && !empty($ecodNoteData['tags'])): ?>
            <div class="ecod_noteart__tags">
                <?php foreach ($ecodNoteData['tags'] as $ecodNoteTag): ?>
                    <?php $ecodNoteTagUrl = (string) ($ecodNoteTag['url'] ?? ''); ?>
                    <?php if ($ecodNoteTagUrl !== ''): ?>
                        <a href="<?php echo htmlspecialchars($ecodNoteTagUrl, ENT_QUOTES, 'UTF-8'); ?>">#<?php echo htmlspecialchars((string) $ecodNoteTag['name'], ENT_QUOTES, 'UTF-8'); ?></a>
                    <?php else: ?>
                        <span>#<?php echo htmlspecialchars((string) $ecodNoteTag['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php elseif (!$ecodLocked && $this->tags): ?>
            <div class="ecod_article__tags">
                <?php $this->tags(' ', true); ?>
            </div>
        <?php endif; ?>

        <?php
        /* 反馈 / 关联模块（动作条、相关阅读、上下篇、版权、作者卡……）共用分组卡：
           组内共享边框不出双线、首尾才保留圆角，模块间 16px 呼吸交给上方 tags 的 margin 之下
           ——正文（.ecod_content）之后的这些模块本就同属一行排布（设计稿 §4.5 演示④） */
        ?>
        <div class="ecod_stack">
            <?php ecod_widgets_render('bottom_content', $this); ?>
        </div>
    </article>

    <?php $this->need('comments.php'); ?>

    <?php
    /* D3 底部快速评论条（仅移动端显示，见 CSS 媒体查询）：
       锁定 / 付费锁 / 评论关闭的文章不输出；第三方评论区也允许（滚动到 #comments） */
    if (ecod_option('EQuickBar', 'on') === 'on'
        && !$ecodNote
        && !$ecodLocked && !$ecodPayLocked
        && ecod_option('EComment', 'on') !== 'off'
        && $this->allow('comment')):
    ?>
    <button type="button" class="ecod_quickbar" data-ecod-quickbar aria-label="写评论">
        <?php echo ecod_icon('comment', 15); ?>
        <span>我来说说…</span>
    </button>
    <?php endif; ?>

    <?php if (ecod_option('EFloatToc', 'on') === 'on' && !$ecodNote): ?>
        <div class="ecod_floattoc" data-ecod-floattoc></div>
    <?php endif; ?>

    <?php endif; /* 页面构建器接管分支结束 */ ?>
</main>

<?php $this->need('public/aside.php'); ?>
<?php $this->need('public/footer.php'); ?>
