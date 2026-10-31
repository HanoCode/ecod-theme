<?php
/**
 * Ecod Theme · 后台设置页
 *
 * 由 functions.php 的 themeConfig($form) 载入，$form 在当前作用域可用。
 * 信息架构三层递进：8 个分组（侧栏手风琴）→ 14 个面板 → 面板内小节。
 *   - 分组/面板/小节定义见下方 $ecodGroups / $ecodPanes / $ecodSections；
 *   - 字段用 data-ecod-pane 标记归组，config.js 搬进对应面板并按
 *     $ecodSections 的字段名清单分发进带标题的小节（保存逻辑不动，
 *     走 Typecho 原生表单）；
 *   - 「开始」是默认面板的上手引导页：三步卡、任务清单（完成态由
 *     字段值 / 面板访问记录 / 操作标记判定）与快捷入口。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @since   2026-10-29
 * @version 2.1.0
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/* ------------------------------------------------------------------
 * 信息架构：8 个分组（手风琴）→ 14 个面板
 *
 * 侧栏只露 8 个带图标的分组，点开才见子面板；
 * 面板内部再由 $ecodSections 切成带标题的小节，三层递进：
 * 分组（是什么方面）→ 面板（具体页面/功能）→ 小节（一件事一组字段）。
 * ---------------------------------------------------------------- */
$ecodGroups = [
    'start'    => ['zap',    '开始',       '三步上手与常用任务'],
    'look'     => ['image',  '外观',       '品牌门面、明暗与动效'],
    'layout'   => ['layers', '布局',       '列表卡片、导航与搜索'],
    'content'  => ['file',   '内容',       '文章页阅读体验与专题'],
    'social'   => ['comment','互动',       '评论、点赞与投稿'],
    'connect'  => ['mail',   '通知与登录', '邮件发信与第三方登录'],
    'pay'      => ['medal',  '变现',       '付费阅读与会员'],
    'webmaster'=> ['chart',  '站长',       '收录、性能与工具备份'],
];

$ecodGroupPanes = [
    'start'    => ['start'],
    'look'     => ['brand', 'appearance'],
    'layout'   => ['layout', 'nav', 'search'],
    'content'  => ['post', 'topics'],
    'social'   => ['comments', 'interact', 'credits'],
    'connect'  => ['mail', 'oauth'],
    'pay'      => ['pay', 'lottery', 'shop', 'ads'],
    'webmaster'=> ['seo', 'tools'],
];

/* 面板元信息：标题 + 一句话引导（顶栏与搜索索引用） */
$ecodPanes = [
    'start'      => ['上手引导', '三步把站点调教成形；做完一项勾掉一项'],
    'brand'      => ['品牌与首页', '名字、Logo、图标、主色，以及首页大图与标语'],
    'appearance' => ['视觉与动效', '深色模式、背景、入场动效与自定义样式'],
    'layout'     => ['列表与卡片', '侧栏位置、卡片模式、缩略图与摘要'],
    'media'      => ['媒体资源库', '对象存储驱动、WebP 上传转换与格式白名单'],
    'nav'        => ['导航菜单', '拖拽搭建一二级菜单，配置折叠与搜索面板'],
    'search'     => ['搜索面板', '⌘K 快捷搜索：热词、标签与实时联想'],
    'post'       => ['文章页', '阅读体验、代码嵌入、版权与 AI 摘要'],
    'topics'     => ['专题与分类页', '策展合集的挑稿与展示，分类页页头'],
    'comments'   => ['评论', '评论服务、嵌套折叠、反垃圾与人机验证'],
    'interact'   => ['点赞与投稿', '卡片点赞、阅读量、友链申请与前端投稿'],
    'credits'    => ['积分与 AI', 'Credit 积分、签到任务与 AI 助手对话计费'],
    'mail'       => ['邮件通知', 'SMTP 发信、回复提醒与友链通知模板'],
    'oauth'      => ['第三方登录', 'GitHub / 微信 / QQ / 微博 / Gitee 快捷登录'],
    'pay'        => ['付费与会员', '付费阅读、支付通道与会员档位定价'],
    'lottery'    => ['站点抽奖', '奖品池、消耗与每日次数'],
    'shop'       => ['兑换商城', '积分当钱花：兑换会员、单篇解锁与定制权益'],
    'ads'        => ['自助广告', '广告位定价、审核与投放页'],
    'seo'        => ['收录与性能', '懒加载、CDN、自定义代码与百度推送'],
    'tools'      => ['工具与备份', '配置快照、导入导出、sitemap 与特色页'],
];

/* 面板内小节：[标题, 说明, [字段名…]]，由 config.js 把字段行分发进对应小节 */
$ecodSections = [
    'brand' => [
        ['站点标识', '名字、Logo 与浏览器标签上的图标', ['ELogo', 'ELogoImage', 'ELogoBoth', 'EFavicon']],
        ['品牌主色', '按钮、链接、高亮全部跟随', ['EPrimary']],
        ['首页门面', '首屏大图与一句话标语，留空即关闭', ['ESlogan', 'EBanner']],
        ['备案与信息', '页脚备案标识、站点年龄与网址导航数据', ['EICP', 'EPolice', 'EBirth', 'ESites']],
    ],
    'appearance' => [
        ['明暗与背景', '深色模式、整页背景渐变或自定义背景图', ['EDark', 'EBackdrop', 'EBgImage', 'EBgMask']],
        ['动效与光标', '列表入场动画与鼠标跟随，触屏自动不加载', ['EAnimate', 'ECursor']],
        ['浏览体验', '无刷新跳转、外链中转与右键菜单', ['EPjax', 'ELinkGo', 'ECtxMenu', 'ECtxItems']],
        ['字体与自定义样式', '自定义字体、自定义 CSS 与登录页背景', ['EFont', 'ECustomCss', 'ELoginBg']],
    ],
    'layout' => [
        ['侧栏位置', '全站默认值，可按页面类型在模块管理台覆盖', ['EAside']],
        ['列表与卡片', '文章列表的卡片形态、每页篇数与新文章角标', ['EListPerPage', 'ECardMode', 'EThumbSide', 'ENewDays']],
        ['缩略图与摘要', '封面池、服务端裁剪与摘要长度', ['EThumbnail', 'EThumbServer', 'EExcerpt']],
    ],
    'media' => [
        ['存储驱动', '媒体库上传通道：本地或对象存储', ['EMediaStorage', 'EMediaDualWrite', 'EMediaMaxBytes']],
        ['阿里云 OSS', 'AccessKey、Bucket 与 Endpoint，选 OSS 驱动时填写', ['EMediaOssKey', 'EMediaOssSecret', 'EMediaOssBucket', 'EMediaOssEndpoint', 'EMediaOssDomain']],
        ['腾讯云 COS', 'SecretId、存储桶与地域，选 COS 驱动时填写', ['EMediaCosKey', 'EMediaCosSecret', 'EMediaCosBucket', 'EMediaCosRegion', 'EMediaCosDomain']],
        ['S3 兼容', 'MinIO / Cloudflare R2 / 七牛等 S3 协议存储', ['EMediaS3Key', 'EMediaS3Secret', 'EMediaS3Bucket', 'EMediaS3Region', 'EMediaS3Endpoint', 'EMediaS3Domain']],
        ['WebP 转换', '上传时自动转存为 WebP，动图与矢量图跳过', ['EWebpConvert', 'EWebpQuality', 'EWebpKeepOriginal']],
        ['格式白名单', 'SVG 与额外扩展名的放行开关', ['EMediaAllowSvg', 'EMediaAllowExt']],
    ],
    'nav' => [
        ['菜单行为', '超出折叠进「更多」，以及菜单为空时的兜底链接', ['ENavMax', 'ENavLinks']],
    ],
    'search' => [
        ['快捷搜索面板', '⌘K 唤起居中面板：热词与标签', ['ESearchPanel', 'ESearchHot', 'ESearchTagCount']],
        ['推荐文章', '面板空态多一栏文章列表，按所选维度取前 N 篇；留空不显示', ['ESearchPosts', 'ESearchPostsCount']],
    ],
    'post' => [
        ['阅读体验', '过期提醒、浮动目录与图片灯箱', ['EStale', 'EFloatToc', 'ETocProgress', 'ELightbox']],
        ['显示更多', '长文按高度折叠 + 展开按钮；正文仍在 HTML 里，不影响 SEO', ['EShowMore', 'EShowMoreH', 'EShowMoreText']],
        ['代码与嵌入', '代码高亮与视频 / 社交卡片自动嵌入', ['ECode', 'ECodeTheme', 'EOembed', 'EOembedSocial']],
        ['互动与版权', '点赞头像、快速评论条与版权声明', ['ELikers', 'EQuickBar', 'ECopyrightSet']],
        ['卡片动效', '磁贴 / 聚合卡等新组件的 hover 动效与图片渐入总开关', ['ECardMotion']],
        ['AI 摘要', 'OpenAI 兼容接口，按文章异步生成摘要卡片', ['EAI', 'EAIProvider', 'EAIEndpoint', 'EAIKey', 'EAIModel', 'EAIPrompt', 'EAIMotion']],
    ],
    'topics' => [
        ['专题墙展示', '专题列表页的卡片形态与密度', ['ETopicWallStyle', 'ETopicWallCols', 'ETopicPerPage', 'ETopicMoreCount', 'ETopicCardChip']],
        ['专题页头', '专题详情页顶部大图的统一背景，留空则用各专题自己的封面', ['ETopicHeadBg']],
        ['分类页头', '分类页顶部的名称、封面与相关专题', ['ECatHead', 'ECatHeadBg', 'ECatHeadImage', 'ECatHeadStats', 'ECatHeadTopics', 'ECatTools']],
    ],
    'comments' => [
        ['评论服务', '全站开关与评论系统：原生或 Twikoo / Waline 等第三方', ['EComment', 'ECommentProvider', 'ECommentTwikoo', 'ECommentWaline', 'ECommentArtalk', 'ECommentGiscusRepo', 'ECommentGiscusRepoId', 'ECommentGiscusCategory', 'ECommentGiscusCategoryId', 'ECommentGiscusMapping']],
        ['嵌套与展开', '长讨论怎么折叠、怎么展开', ['ECommentFold', 'ECommentExpandMode']],
        ['反垃圾与人机验证', '敏感词、中文限制、Turnstile 与 Akismet', ['ESensitive', 'ECnOnly', 'EMaxLen', 'ETurnstile', 'ETurnstileSite', 'ETurnstileSecret', 'EAkismet', 'EAkismetKey']],
        ['表情', '评论框旁的表情选择', ['EEmojiStyle', 'EEmojiCdn']],
    ],
    'interact' => [
        ['点赞与数据', '卡片与评论的点赞、在线点与阅读量统计', ['ELike', 'ECLike', 'EAuthorOnline', 'EViews']],
        ['友链与投稿', '友链一键申请与登录用户前端投稿', ['EFriendApply', 'EContribute', 'EContributeGroup', 'EContributeCats', 'EContributeNote']],
        ['个人中心', '顶部默认封面，用户可在个人中心上传自己的封面覆盖', ['EUcCover']],
    ],
    'credits' => [
        ['积分基础', '总开关与积分名称，关闭后签到 / AI 计费全部停用', ['ECrOn', 'ECrName', 'ECrTicker']],
        ['任务奖励', '签到、评论、被赞与投稿的单次奖励与每日上限', ['ECrCheckin', 'ECrComment', 'ECrCommentCap', 'ECrLikeRecv', 'ECrLikeCap', 'ECrPost', 'ECrPostCap', 'ECrVipGift']],
        ['AI 助手', '对话页开关、每条消息的积分消耗与系统提示词', ['EAIChat', 'ECrAICost', 'EAIChatCtx', 'EAIChatPrompt', 'EAIChatWelcome']],
        ['推广返佣', '推荐注册归因与订单返积分', ['ECrRefOn', 'ECrRefPercent']],
    ],
    'mail' => [
        ['发信服务器（SMTP）', '全站邮件都走这套账号：评论提醒、回复通知、验证码', ['ECommentMail', 'ECommentMailHost', 'ECommentMailPort', 'ECommentMailSecure', 'ECommentMailVerify', 'ECommentMailAccount', 'ECommentMailPassword', 'ECommentMailFrom', 'ECommentMailTo']],
        ['评论推送', '新评论推到微信 / 企业微信 / Server酱 / Bark / 自建 webhook，响应结束后异步发送', ['EPush', 'EPushChannel', 'EPushKey', 'EPushExtra']],
        ['通知范围', '回复提醒与账户验证码邮件', ['EReplyMail', 'EAccountMail']],
        ['友链通知模板', '申请送达与审核通过的邮件文案，支持变量', ['EFriendMailSubject', 'EFriendMailBody', 'EFriendPassSubject', 'EFriendPassBody']],
    ],
    'oauth' => [
        ['通用行为', '邮箱自动绑定与自动建号', ['EOAuthBindMail', 'EOAuthRegister']],
        ['GitHub', '个人开发者最友好的登录渠道', ['EOAuthGithub', 'EOAuthGithubId', 'EOAuthGithubSecret']],
        ['微信（开放平台扫码）', '需企业资质的网站应用', ['EOAuthWeixin', 'EOAuthWeixinId', 'EOAuthWeixinSecret']],
        ['QQ', 'QQ 互联创建应用', ['EOAuthQQ', 'EOAuthQQId', 'EOAuthQQSecret']],
        ['微博', '微博开放平台网站接入', ['EOAuthWeibo', 'EOAuthWeiboId', 'EOAuthWeiboSecret']],
        ['Gitee', '个人开发者零门槛', ['EOAuthGitee', 'EOAuthGiteeId', 'EOAuthGiteeSecret']],
    ],
    'pay' => [
        ['付费基础', '总开关、货币、试读与付费卡位置', ['EPay', 'EPayCurrency', 'EPayAuthorFree', 'EPayBox', 'EPayTeaser', 'EPayDays', 'EPayAdminUrl', 'EPayCommentUnlock', 'EPayResourceCard']],
        ['支付通道', '手动确认、卡密或易支付', ['EPayChannel', 'EPayManualDoc', 'EPayEpayUrl', 'EPayEpayPid', 'EPayEpayKey', 'EPayEpayType']],
        ['会员档位', '两档会员的名称与周期定价，留空即不提供', ['EVip1Name', 'EVip1Month', 'EVip1Quarter', 'EVip1Year', 'EVip1Forever', 'EVip2Name', 'EVip2Month', 'EVip2Quarter', 'EVip2Year', 'EVip2Forever']],
    ],
    'lottery' => [
        ['抽奖基础', '总开关、标题、横幅、单次消耗、每日次数与保底 / 直接兑换', ['ELottery', 'ELotteryTitle', 'ELotteryBanner', 'ELotteryCost', 'ELotteryDaily', 'ELotteryPity', 'ELotteryExchange', 'ELotteryNote']],
        ['奖品池', '一行一个奖品：名称|权重|库存|类型|值', ['ELotteryPrizes']],
    ],
    'shop' => [
        ['兑换商城', '总开关与说明，依赖 Credit 积分', ['EShop', 'EShopNote']],
        ['商品列表', '一行一个商品：名称|价格|类型|值|库存|限购', ['EShopItems']],
    ],
    'ads' => [
        ['自助广告', '总开关、审核开关与投放须知', ['EAdSelf', 'EAdSelfReview', 'EAdSelfNote']],
        ['广告位定价', '每个位置的单日价格（元/天），留空即不开放该位', ['EAdSelfPriceHomeTop', 'EAdSelfPriceSidebar', 'EAdSelfPricePostBottom']],
        ['天数限制', '单次投放的天数范围', ['EAdSelfMinDays', 'EAdSelfMaxDays']],
    ],
    'seo' => [
        ['性能与资源', '图片懒加载与静态资源 CDN', ['ELazy', 'ECdn', 'EAssetMode']],
        ['自定义代码与推送', 'head / body 注入与百度推送 Token', ['ECustomHead', 'ECustomBody', 'EBaidu', 'EBaiduAuto']],
        ['关键词内链', '正文关键词自动挂链接：内部词库 + 分类标签名，DOM 级替换不碰代码块', ['EKeywords', 'EKeywordsList', 'EKeywordsLimit', 'EKeywordsAuto']],
    ],
];

/* 由块系统接管的分组（模块已迁到独立管理台）：面板顶部出现管理台入口卡 */
$ecodBlockPanes = [
    'layout' => '首页、侧栏、文章页、页脚的模块都在「模块管理台」摆放',
    'post'   => '文章页的模块（相关文章、作者卡、浮动控件等）也在「模块管理台」摆放',
];

/* 模块管理台入口（独立页面，不在后台菜单里）
   注意：basename(__DIR__ . '/..') 会得到 ".."，必须用 dirname() 取主题目录名 */
$ecodWidgetUrl = rtrim((string) \Widget\Options::alloc()->siteUrl, '/')
    . '/usr/themes/' . basename(dirname(dirname(__DIR__))) . '/widget.php';

/* 分组级条件已不再使用：总开关（如全站评论）都放进了对应面板内部，
   字段级显隐（data-ecod-show-when）配合「另有 N 项已隐藏」提示负责引导 */

/* 默认激活的面板：取第一个（「开始」引导页），避免引用不存在的键导致首屏空白 */
$ecodFirstPane = array_key_first($ecodPanes);

/**
 * 注册一个后台字段并打好分组标记
 */
if (!function_exists('ecod_admin_field')) {
    function ecod_admin_field($form, string $type, string $name, $value, string $label = '', string $desc = '', array $options = [], string $pane = 'basic', array $attrs = [])
    {
        $map = [
            'text'     => 'Text',
            'textarea' => 'Textarea',
            'select'   => 'Select',
            'number'   => 'Number',
            'hidden'   => 'Hidden',
        ];
        $class = '\\Typecho\\Widget\\Helper\\Form\\Element\\' . ($map[$type] ?? 'Text');

        if ($type === 'select') {
            $element = new $class($name, $options, $value, $label, $desc);
        } elseif ($type === 'hidden') {
            $element = new $class($name, null, (string) $value);
        } else {
            $element = new $class($name, null, $value, $label, $desc);
        }

        /* min / max / step 等属性：数字项带范围时前端会渲染成滑块 */
        foreach ($attrs as $attrName => $attrValue) {
            $element->input->setAttribute($attrName, (string) $attrValue);
        }

        $element->setAttribute('data-ecod-pane', $pane);
        $form->addInput($element);
        return $element;
    }
}

/**
 * 带媒体库选图能力的字段
 *
 * data-ecod-media 必须挂在 input / textarea 上：挂在 <ul> 上会让前端
 * 把整个字段容器当成输入控件，出现预览错位。
 */
if (!function_exists('ecod_admin_media')) {
    function ecod_admin_media($form, string $type, string $name, $value, string $label = '', string $desc = '', string $pane = 'basic', string $mode = 'single', array $attrs = [])
    {
        $element = ecod_admin_field($form, $type, $name, $value, $label, $desc, [], $pane, $attrs);
        $element->input->setAttribute('data-ecod-media', $mode);
        return $element;
    }
}
?>

<link rel="stylesheet" href="<?php echo ecod_asset('assets/admin/tokens.css'); ?>">
<link rel="stylesheet" href="<?php echo ecod_asset('assets/admin/config.css'); ?>">
<link rel="stylesheet" href="<?php echo ecod_asset('assets/admin/media.css'); ?>">
<link rel="stylesheet" href="<?php echo ecod_asset('assets/admin/nav.css'); ?>">
<link rel="stylesheet" href="<?php echo ecod_asset('assets/admin/topics.css'); ?>">

<?php /* JS 被禁用或加载失败时，自绘面板无法搬运字段，隐藏外壳让 Typecho 原生表单直接可用 */ ?>
<noscript>
    <style>
        .ecod-admin, .ecod-drawer { display: none !important; }
        .typecho-option { display: block !important; }
    </style>
</noscript>

<div class="ecod-admin" id="ecod-admin">
    <aside class="ecod-admin__aside">
        <div class="ecod-admin__brand">
            <span class="ecod-admin__mark">E</span>
            <span class="ecod-admin__brandtext">
                <b>Ecod</b>
                <small>主题设置</small>
            </span>
        </div>

        <div class="ecod-admin__search">
            <div class="ecod-search__box">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" clip-rule="evenodd" d="M18.319 14.4326C20.7628 11.2941 20.542 6.75347 17.6569 3.86829C14.5327 0.744098 9.46734 0.744098 6.34315 3.86829C3.21895 6.99249 3.21895 12.0578 6.34315 15.182C9.22833 18.0672 13.769 18.2879 16.9075 15.8442C16.921 15.8595 16.9351 15.8745 16.9497 15.8891L21.1924 20.1317C21.5829 20.5223 22.2161 20.5223 22.6066 20.1317C22.9971 19.7412 22.9971 19.1081 22.6066 18.7175L18.364 14.4749C18.3493 14.4603 18.3343 14.4462 18.319 14.4326ZM16.2426 5.28251C18.5858 7.62565 18.5858 11.4246 16.2426 13.7678C13.8995 16.1109 10.1005 16.1109 7.75736 13.7678C5.41421 11.4246 5.41421 7.62565 7.75736 5.28251C10.1005 2.93936 13.8995 2.93936 16.2426 5.28251Z"/></svg>
                <input type="search" data-ecod-search placeholder="搜索设置项" aria-label="搜索设置项" autocomplete="off">
                <kbd data-search-hint>/</kbd>
            </div>
            <div class="ecod-searchres" data-search-results hidden></div>
        </div>

        <nav class="ecod-admin__nav" aria-label="设置分组">
            <?php foreach ($ecodGroups as $gkey => $group): ?>
                <?php
                $ecodGFirst = $ecodGroupPanes[$gkey][0];
                $ecodGActive = in_array($ecodFirstPane, $ecodGroupPanes[$gkey], true);
                ?>
                <div class="ecod-admin__group<?php echo $ecodGActive ? ' is-open' : ''; ?>" data-group="<?php echo $gkey; ?>">
                    <button type="button" class="ecod-admin__grouphd<?php echo $ecodGActive ? ' is-active' : ''; ?>"
                            data-group-toggle="<?php echo $gkey; ?>" aria-expanded="<?php echo $ecodGActive ? 'true' : 'false'; ?>">
                        <span class="ecod-admin__gicon" aria-hidden="true"><?php echo ecod_icon($group[0], 15); ?></span>
                        <span class="ecod-admin__gname">
                            <b><?php echo htmlspecialchars($group[1]); ?></b>
                            <small><?php echo htmlspecialchars($group[2]); ?></small>
                        </span>
                        <span class="ecod-admin__gchev" aria-hidden="true"><?php echo ecod_icon('chevron', 13); ?></span>
                    </button>
                    <div class="ecod-admin__grouplst">
                        <div class="ecod-admin__grouplst-in">
                            <?php foreach ($ecodGroupPanes[$gkey] as $pkey): ?>
                                <button type="button" class="ecod-admin__navitem<?php echo $pkey === $ecodFirstPane ? ' is-active' : ''; ?>"
                                        data-pane="<?php echo $pkey; ?>" data-group="<?php echo $gkey; ?>">
                                    <?php echo htmlspecialchars($ecodPanes[$pkey][0]); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="ecod-admin__sidefoot">
            <button type="button" class="ecod-admin__ghost" data-ecod-export>导出配置</button>
        </div>
    </aside>

    <div class="ecod-admin__main">
        <header class="ecod-admin__head">
            <div>
                <h2 class="ecod-admin__title" data-pane-title><?php echo htmlspecialchars($ecodPanes[$ecodFirstPane][0]); ?></h2>
                <p class="ecod-admin__desc" data-pane-desc><?php echo htmlspecialchars($ecodPanes[$ecodFirstPane][1]); ?></p>
            </div>
            <div class="ecod-admin__actions">
                <span class="ecod-admin__dirty" data-dirty hidden>有未保存的修改</span>
            </div>
        </header>

        <div class="ecod-admin__body" data-admin-body>
            <?php foreach ($ecodPanes as $key => $pane): ?>
                <?php if ($key === 'tools') {
                    continue;
                } ?>
                <section class="ecod-pane<?php echo $key === $ecodFirstPane ? ' is-active' : ''; ?>" data-pane-panel="<?php echo $key; ?>">
                    <?php if ($key === 'start'): ?>
                        <?php /* 引导页：三步上手 + 任务清单 + 快捷入口；勾选状态由 config.js 按字段值与访问记录计算 */ ?>
                        <div class="ecod-start">
                            <div class="ecod-start__hero">
                                <b>把站点调教成你想要的样子</b>
                                <p>配置分四步走：先在「外观」定品牌门面，再到「布局」挑列表版式、搭导航，然后按需开启评论、付费与登录，最后在「站长」做一次备份。想找某个具体设置，直接按 <kbd>/</kbd> 搜索，或点下面的任务卡直达。</p>
                            </div>

                            <div class="ecod-start__steps" aria-label="三步上手">
                                <button type="button" class="ecod-start__step" data-start-jump="brand">
                                    <em>1</em>
                                    <b>定品牌门面</b>
                                    <small>Logo、图标、主色与首页大图</small>
                                </button>
                                <button type="button" class="ecod-start__step" data-start-jump="layout">
                                    <em>2</em>
                                    <b>挑列表版式</b>
                                    <small>卡片模式、侧栏位置与缩略图</small>
                                </button>
                                <button type="button" class="ecod-start__step" data-start-jump="comments">
                                    <em>3</em>
                                    <b>按需开功能</b>
                                    <small>评论、付费、AI 摘要与第三方登录</small>
                                </button>
                            </div>

                            <div class="ecod-start__tasks">
                                <div class="ecod-start__taskshd">
                                    <b>上手清单</b>
                                    <span data-start-progress>0 / 7</span>
                                </div>
                                <div class="ecod-start__tasklst" data-start-tasks>
                                    <?php /* 任务项由 config.js 生成，保证勾选状态与字段联动 */ ?>
                                </div>
                            </div>

                            <div class="ecod-start__quick">
                                <a class="ecod-start__qlink" href="<?php echo htmlspecialchars($ecodWidgetUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" data-start-flag="widgets">
                                    <?php echo ecod_icon('layers', 15); ?>
                                    <span><b>模块管理台</b><small>首页、侧栏、文章页、页脚的模块摆放</small></span>
                                    <em>打开 →</em>
                                </a>
                                <button type="button" class="ecod-start__qlink" data-start-jump="tools" data-start-flag="tools">
                                    <?php echo ecod_icon('check-circle', 15); ?>
                                    <span><b>特色页一键创建</b><small>友链、投稿、网址导航、专题页</small></span>
                                    <em>前往 →</em>
                                </button>
                                <button type="button" class="ecod-start__qlink" data-start-export data-start-flag="backup">
                                    <?php echo ecod_icon('download', 15); ?>
                                    <span><b>导出配置备份</b><small>全部设置存成 JSON，随存随恢复</small></span>
                                    <em>下载 →</em>
                                </button>
                            </div>
                        </div>
                    <?php elseif (isset($ecodBlockPanes[$key])): ?>
                        <?php /* 模块摆放已迁到独立管理台，这里只留常规字段与入口卡 */ ?>
                        <a class="ecod-wgt-entry" href="<?php echo htmlspecialchars($ecodWidgetUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                            <span class="ecod-wgt-entry__ic"><?php echo ecod_icon('layers', 16); ?></span>
                            <span class="ecod-wgt-entry__text">
                                <b><?php echo htmlspecialchars($ecodBlockPanes[$key]); ?></b>
                                <small>拖拽排序、开关、改参数，改完即存，不占用设置页</small>
                            </span>
                            <span class="ecod-wgt-entry__go">打开 →</span>
                        </a>
                        <div class="ecod-fields" data-fields="<?php echo $key; ?>"></div>
                    <?php elseif ($key === 'nav'): ?>
                        <div class="ecod-navb__bar" data-navb-bar>
                            <label class="ecod-navb__pick">
                                <span>当前导航</span>
                                <select data-navb-select></select>
                            </label>
                            <button type="button" class="ecod-admin__ghost" data-navb-new>＋ 新建</button>
                            <button type="button" class="ecod-admin__ghost" data-navb-dup>复制</button>
                            <button type="button" class="ecod-admin__ghost" data-navb-setedit>编辑</button>
                            <button type="button" class="ecod-admin__ghost is-danger" data-navb-del>删除</button>
                            <span class="ecod-navb__bar-hint">选中哪套，前台就用哪套；改完点右下角「保存设置」</span>
                        </div>
                        <div class="ecod-navb__setform" data-navb-setform hidden>
                            <label class="ecod-navb__setfield">
                                <span>名称</span>
                                <input type="text" data-set-name maxlength="24" placeholder="例如 主导航">
                            </label>
                            <label class="ecod-navb__setfield ecod-navb__setfield--wide">
                                <span>备注</span>
                                <input type="text" data-set-note maxlength="60" placeholder="只存后台，例如「春节活动期用」，可留空">
                            </label>
                            <label class="ecod-navb__setfield">
                                <span>二级菜单样式</span>
                                <select data-set-style>
                                    <option value="">跟随全局设置</option>
                                    <option value="auto">自动（有图标显示图标卡片）</option>
                                    <option value="list">纯文字列表</option>
                                    <option value="icons">图标卡片（无图标用首字）</option>
                                    <option value="mega">宽幅图标卡片（两列）</option>
                                    <option value="mega-group">分组宽面板（栏标题 + 描述）</option>
                                </select>
                            </label>
                            <button type="button" class="ecod-admin__ghost" data-set-cancel>取消</button>
                            <button type="button" class="ecod-btn ecod-btn--sm" data-set-save>应用</button>
                        </div>
                        <div class="ecod-navb" data-navb>
                            <div class="ecod-navb__col">
                                <div class="ecod-navb__hd">添加到菜单</div>
                                <div class="ecod-navb__tabs" data-navb-tabs>
                                    <button type="button" class="is-on" data-navb-tab="page">独立页面</button>
                                    <button type="button" data-navb-tab="cat">分类</button>
                                    <button type="button" data-navb-tab="tag">标签</button>
                                    <button type="button" data-navb-tab="link">自定义</button>
                                </div>
                                <div class="ecod-navb__pane" data-navb-pane="page">
                                    <input type="search" class="ecod-navb__filter" data-navb-filter placeholder="筛选页面…" autocomplete="off">
                                    <div class="ecod-navb__src" data-navb-src="page"></div>
                                </div>
                                <div class="ecod-navb__pane" data-navb-pane="cat" hidden>
                                    <input type="search" class="ecod-navb__filter" data-navb-filter placeholder="筛选分类…" autocomplete="off">
                                    <div class="ecod-navb__src" data-navb-src="cat"></div>
                                </div>
                                <div class="ecod-navb__pane" data-navb-pane="tag" hidden>
                                    <input type="search" class="ecod-navb__filter" data-navb-filter placeholder="筛选标签…" autocomplete="off">
                                    <div class="ecod-navb__src" data-navb-src="tag"></div>
                                </div>
                                <div class="ecod-navb__pane" data-navb-pane="link" hidden>
                                    <div class="ecod-navb__form">
                                        <input type="text" data-navb-link-label placeholder="名称，例如 GitHub">
                                        <input type="text" data-navb-link-url placeholder="https://github.com/you" spellcheck="false">
                                        <label class="ecod-navb__check"><input type="checkbox" data-navb-link-blank> 新窗口打开</label>
                                        <button type="button" class="ecod-btn ecod-btn--sm" data-navb-link-add>添加链接</button>
                                    </div>
                                </div>
                            </div>

                            <div class="ecod-navb__col ecod-navb__col--main">
                                <div class="ecod-navb__hd">
                                    <span data-navb-set>主导航</span>
                                    <em data-navb-count>0 项</em>
                                    <span class="ecod-navb__hint">拖动排序 · 向右多拖 28px 缩进为二级</span>
                                    <button type="button" class="ecod-admin__ghost" data-navb-clear>清空</button>
                                </div>
                                <div class="ecod-navb__list" data-navb-list>
                                    <i class="ecod-navb__marker" data-navb-marker hidden aria-hidden="true"></i>
                                </div>
                                <p class="ecod-navb__empty" data-navb-empty>这套导航还没有菜单项。左侧挑一个加进来；整套为空时前台沿用「全部独立页面 + 自定义链接」的自动导航。</p>
                            </div>
                        </div>
                        <div class="ecod-fields" data-fields="<?php echo $key; ?>"></div>
                    <?php elseif ($key === 'topics'): ?>
                        <div class="ecod-tpb__bar" data-tpb-bar>
                            <button type="button" class="ecod-btn ecod-btn--sm" data-tpb-new>＋ 新建专题</button>
                            <button type="button" class="ecod-admin__ghost" data-tpb-page>创建专题页</button>
                            <span class="ecod-tpb__hint" data-tpb-pagehint>专题页：/topics</span>
                            <span class="ecod-tpb__count" data-tpb-count>0 / 30</span>
                        </div>
                        <p class="ecod-tpb__tip">
                            专题是「编辑挑出来的一组文章」：一篇文章通过自定义字段「专题」归入一个专题，
                            也可以直接在这里挑稿加入。改完点右下角「保存设置」。
                        </p>
                        <div class="ecod-tpb" data-tpb>
                            <div class="ecod-tpb__list" data-tpb-list></div>
                            <p class="ecod-tpb__empty" data-tpb-empty>还没有专题。点「＋ 新建专题」开始，建好后到文章里选它即可归档。</p>
                        </div>
                        <div class="ecod-fields" data-fields="<?php echo $key; ?>"></div>
                    <?php else: ?>
                        <div class="ecod-fields" data-fields="<?php echo $key; ?>"></div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>

            <section class="ecod-pane" data-pane-panel="tools">
                <div class="ecod-tool">
                    <div class="ecod-tool__text">
                        <b>配置快照</b>
                        <p>把当前全部设置与模块布局存一份快照，最多保留 10 份。改坏了可以一键回到之前的状态；恢复前会自动把「现在」也存一份。</p>
                    </div>
                    <button type="button" class="ecod-btn" data-ecod-snapshot-save>保存快照</button>
                </div>

                <div class="ecod-snaplist" data-ecod-snaplist>
                    <?php $ecodSnaps = ecod_snapshot_list(); ?>
                    <?php if (!$ecodSnaps): ?>
                        <p class="ecod-snaplist__empty">还没有快照。点右上角「保存快照」创建第一份。</p>
                    <?php else: ?>
                        <?php foreach ($ecodSnaps as $ecodSnap): ?>
                            <div class="ecod-snap" data-name="<?php echo htmlspecialchars($ecodSnap['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="ecod-snap__main">
                                    <b><?php echo htmlspecialchars($ecodSnap['label'], ENT_QUOTES, 'UTF-8'); ?></b>
                                    <small><?php echo (int) $ecodSnap['count']; ?> 项设置 · <?php echo round($ecodSnap['size'] / 1024, 1); ?> KB</small>
                                </div>
                                <div class="ecod-snap__act">
                                    <button type="button" class="ecod-admin__ghost" data-ecod-snap-restore>恢复</button>
                                    <button type="button" class="ecod-admin__ghost is-danger" data-ecod-snap-delete>删除</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="ecod-tool">
                    <div class="ecod-tool__text">
                        <b>导出配置</b>
                        <p>把当前全部设置保存成 JSON 文件，换主题或重装后可原样恢复。模块布局的备份请用「模块管理台」右上角的导出。</p>
                    </div>
                    <button type="button" class="ecod-btn" data-ecod-export>下载 JSON</button>
                </div>

                <div class="ecod-tool">
                    <div class="ecod-tool__text">
                        <b>导入配置</b>
                        <p>选择之前导出的 JSON 文件，设置项会被回填，确认无误后再点「保存设置」。</p>
                    </div>
                    <label class="ecod-btn ecod-btn--ghost">
                        选择文件
                        <input type="file" accept="application/json,.json" data-ecod-import hidden>
                    </label>
                </div>

                <div class="ecod-tool">
                    <div class="ecod-tool__text">
                        <b>sitemap.xml</b>
                        <p>把站点全部文章、页面、分类、标签写成一个静态文件放到网站根目录。未开启伪静态时，PHP 路由无法接管 <code>/sitemap.xml</code>，用这里生成的文件最稳。</p>
                    </div>
                    <button type="button" class="ecod-btn ecod-btn--ghost" data-ecod-build="sitemap">生成文件</button>
                </div>

                <div class="ecod-tool">
                    <div class="ecod-tool__text">
                        <b>robots.txt</b>
                        <p>生成一份带 sitemap 地址的基础 robots.txt。根目录已有同名文件时不会覆盖，避免冲掉你自己的规则。</p>
                    </div>
                    <button type="button" class="ecod-btn ecod-btn--ghost" data-ecod-build="robots">生成文件</button>
                </div>

                <div class="ecod-tool">
                    <div class="ecod-tool__text">
                        <b>百度主动推送</b>
                        <p>把最近 20 篇文章地址推给百度，加快收录。Token 在「站长 → 收录与性能」里填，每天配额有限，不要反复点；24 小时内推过的文章会自动跳过。</p>
                    </div>
                    <button type="button" class="ecod-btn ecod-btn--ghost" data-ecod-build="baidu">推送</button>
                </div>

                <div class="ecod-tool">
                    <div class="ecod-tool__text">
                        <b>自定义短链</b>
                        <p>把固定的推荐 / 活动链接变成 <code>?go=短码</code>。正文里目标地址完全一致的链接会自动改用短码，访问走外链中转页并统计点击。</p>
                    </div>
                </div>

                <div class="ecod-broadcast" data-ecod-golink-form>
                    <input type="text" class="ecod-broadcast__title" data-ecod-golink-key placeholder="短码（留空自动生成，3-32 位字母数字）" maxlength="32">
                    <input type="text" class="ecod-broadcast__link" data-ecod-golink-target placeholder="目标地址，https:// 开头" maxlength="190">
                    <button type="button" class="ecod-btn ecod-broadcast__send" data-ecod-golink-add>保存短链</button>
                </div>

                <div class="ecod-snaplist" data-ecod-golink-list>
                    <?php $ecodGolinks = function_exists('ecod_golink_all') ? ecod_golink_all() : []; ?>
                    <?php if (!$ecodGolinks): ?>
                        <p class="ecod-snaplist__empty">还没有自定义短链。填好目标地址后点「保存短链」创建第一份。</p>
                    <?php else: ?>
                        <?php foreach ($ecodGolinks as $ecodGolink): ?>
                            <div class="ecod-snap" data-key="<?php echo htmlspecialchars((string) $ecodGolink['skey'], ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="ecod-snap__main">
                                    <b>?go=<?php echo htmlspecialchars((string) $ecodGolink['skey'], ENT_QUOTES, 'UTF-8'); ?></b>
                                    <small>→ <?php echo htmlspecialchars((string) $ecodGolink['target'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo (int) $ecodGolink['count']; ?> 次点击</small>
                                </div>
                                <div class="ecod-snap__act">
                                    <button type="button" class="ecod-admin__ghost" data-ecod-golink-copy>复制</button>
                                    <button type="button" class="ecod-admin__ghost is-danger" data-ecod-golink-del>删除</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="ecod-tool ecod-tool--broadcast">
                    <div class="ecod-tool__text">
                        <b>站内公告</b>
                        <p>给全部注册用户的站内消息发一条公告，出现在头部铃铛与个人中心「我的消息」。每人一条，不要频繁发送。</p>
                    </div>
                    <div class="ecod-broadcast">
                        <input type="text" class="ecod-broadcast__title" data-ecod-broadcast-title placeholder="公告标题，例如：今晚 23:00 ~ 24:00 停机维护" maxlength="80">
                        <textarea class="ecod-broadcast__body" data-ecod-broadcast-body rows="3" placeholder="正文（可选），支持多行"></textarea>
                        <input type="text" class="ecod-broadcast__link" data-ecod-broadcast-link placeholder="跳转链接（可选，https:// 或站内 /path）">
                        <button type="button" class="ecod-btn ecod-broadcast__send" data-ecod-broadcast-send>发送公告</button>
                    </div>
                </div>

                <div class="ecod-tool">
                    <div class="ecod-tool__text">
                        <b>特色页</b>
                        <p>一键创建三个独立页面并自动选好模板，不用再手动建页、选「自定义模板」。已存在的不会重复创建。</p>
                    </div>
                </div>

                <div class="ecod-pagelist" data-ecod-pagelist>
                    <?php $ecodNavHidden = array_filter(array_map('trim', explode(',', (string) ecod_option('ENavHide', '')))); ?>
                    <?php foreach (ecod_pages_state() as $ecodPageKey => $ecodPage): ?>
                        <div class="ecod-page-row<?php echo $ecodPage['exists'] ? ' is-exists' : ''; ?>" data-key="<?php echo htmlspecialchars($ecodPageKey, ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="ecod-page-row__icon" aria-hidden="true"><?php echo ecod_icon((string) $ecodPage['icon'], 17); ?></span>
                            <div class="ecod-page-row__main">
                                <b><?php echo htmlspecialchars($ecodPage['pagetitle'], ENT_QUOTES, 'UTF-8'); ?></b>
                                <small><?php echo htmlspecialchars($ecodPage['desc'], ENT_QUOTES, 'UTF-8'); ?></small>
                            </div>
                            <?php if ($ecodPage['exists']): ?>
                                <?php $ecodPageInNav = !in_array((string) $ecodPage['slug'], $ecodNavHidden, true); ?>
                                <label class="ecod-navsw" title="在导航栏显示这个页面">
                                    <span>导航</span>
                                    <span class="ecod-switch">
                                        <input type="checkbox" data-nav-toggle="<?php echo htmlspecialchars((string) $ecodPage['slug'], ENT_QUOTES, 'UTF-8'); ?>"<?php echo $ecodPageInNav ? ' checked' : ''; ?>>
                                        <i></i>
                                    </span>
                                </label>
                            <?php endif; ?>
                            <div class="ecod-page-row__act">
                                <?php if ($ecodPage['exists']): ?>
                                    <a class="ecod-admin__ghost" href="<?php echo htmlspecialchars($ecodPage['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">查看</a>
                                    <a class="ecod-admin__ghost" href="<?php echo htmlspecialchars($ecodPage['edit'], ENT_QUOTES, 'UTF-8'); ?>">编辑</a>
                                <?php else: ?>
                                    <button type="button" class="ecod-btn ecod-btn--sm" data-ecod-page-create>创建</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="ecod-tool">
                    <div class="ecod-tool__text">
                        <b>模块管理台</b>
                        <p>模块的位置、开关与参数已迁到独立管理台（数据存于 <code>EWidgets</code>，旧的 <code>EBlocks</code> 只作升级迁移来源）。侧栏的按页面显示也在管理台里调。</p>
                    </div>
                    <a class="ecod-btn ecod-btn--sm" href="<?php echo htmlspecialchars($ecodWidgetUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">打开管理台</a>
                </div>

                <p class="ecod-tool__status" data-ecod-tool-status hidden></p>
            </section>
        </div>
    </div>
</div>

<?php
/* ------------------------------------------------------------------
 * 字段注册
 * ---------------------------------------------------------------- */

/* 品牌与首页（原「站点」+「首页」两组合并） */
ecod_admin_field($form, 'text', 'ELogo', 'Ecod', '站点标识', '导航栏左侧显示的文字，留空则使用站点标题', [], 'brand');
ecod_admin_media($form, 'text', 'ELogoImage', '', 'Logo 图片', '从媒体库选图或粘贴地址；填了就代替上面的文字', 'brand');
ecod_admin_field($form, 'select', 'ELogoBoth', 'off', '图片旁同时显示文字', '只在填了 Logo 图片时生效，适合「图形 + 站名」并排的排布', [
    'off' => '只显示图片',
    'on'  => '图片 + 文字',
], 'brand', ['data-ecod-show-when' => 'ELogoImage:!empty']);
ecod_admin_media($form, 'text', 'EFavicon', '', '站点图标（Favicon）', '建议 32×32 以上的 PNG / SVG；留空则用主色生成的占位图标', 'brand');
$ecodPrimaryField = ecod_admin_field($form, 'text', 'EPrimary', '#4f6ef2', '主题主色', '六位十六进制色值，按钮、链接、高亮等全部跟随；留空恢复 #4f6ef2', [], 'brand');
$ecodPrimaryField->input->setAttribute('data-ecod-color', '1');
ecod_admin_field($form, 'text', 'ESlogan', '', '副标题 / 一句话', '首页大图与搜索盒下方显示，留空则不显示', [], 'brand');
ecod_admin_media($form, 'text', 'EBanner', '', '首页大图', '从媒体库选图，或直接粘贴图片 URL；留空则关闭首页大图', 'brand');
ecod_admin_field($form, 'text', 'EICP', '', 'ICP 备案号', '例如「京ICP备12345678号-1」；填写后页脚自动显示并链接到工信部', [], 'brand');
ecod_admin_field($form, 'text', 'EPolice', '', '公安备案号', '例如「京公网安备 11010502030123号」；填写后页脚显示并链接到公安备案查询', [], 'brand');
ecod_admin_field($form, 'text', 'EBirth', '', '站点成立日期', '例如 2021-01-01；填写后「数据概览」块会显示已运行天数', [], 'brand');
ecod_admin_field($form, 'textarea', 'ESites', '', '网址导航数据', '「网址导航」页的站点数据。空行分隔分组，每组第一行为「组名丨组描述」，之后每行一个站点：「名称丨链接丨一句话描述」，第 4 段可选（站点图标地址）。需先用「站长 → 工具与备份」创建网址导航页', [], 'brand');

/* 视觉与动效（原「外观」组）：登录页背景 */

/* 版式（侧栏位置、卡片、缩略图、摘要） */
ecod_admin_field($form, 'select', 'EAside', 'right', '侧栏位置（全站默认）', '所有页面类型的默认值；在「模块管理台」里可以按页面类型单独覆盖，文章 / 页面还能用自定义字段 aside 单独覆盖', [
    'right' => '右侧栏',
    'left'  => '左侧栏',
    'none'  => '无侧栏',
], 'layout');
ecod_admin_field($form, 'select', 'ECardMode', 'default', '默认卡片模式', '文章可在编辑页通过自定义字段 mode 单独覆盖', [
    'default' => '列表（文左图右）',
    'feature' => '大图（重点文章）',
    'gallery' => '三图',
    'bare'    => '无图',
    'grid'    => '网格',
    'title'   => '标题式',
    'compact' => '紧凑行（无摘要）',
    'mosaic'  => '混合网格（1 大 + N 小）',
    'item'    => '清单 / 装备（价格·规格·评分）',
], 'layout');
ecod_admin_field($form, 'select', 'EThumbSide', 'right', '缩略图位置', '列表与大图模式下缩略图放左还是放右；文章可用自定义字段 side = left / right 单独覆盖', [
    'right' => '右图（默认）',
    'left'  => '左图',
], 'layout', ['data-ecod-show-when' => 'ECardMode:default|feature']);
ecod_admin_field($form, 'number', 'ENewDays', '7', '「新」角标天数', '文章发布后多少天内显示「新」角标，填 0 关闭该角标', [], 'layout', ['min' => 0, 'max' => 90, 'step' => 1]);
ecod_admin_field($form, 'number', 'EListPerPage', '0', '每页文章数', '首页 / 分类 / 标签 / 作者 / 搜索列表一次加载几篇，分页与「加载更多」按此计算；填 0 跟随 Typecho 后台「阅读 → 每页文章数」', [], 'layout', ['min' => 0, 'max' => 60, 'step' => 1, 'placeholder' => '0 = 跟随后台设置']);
ecod_admin_field($form, 'number', 'EExcerpt', '110', '摘要长度', '单位：字符，默认 110；文章里填了「自定义摘要」时以手填内容为准', [], 'layout', ['min' => 40, 'max' => 200, 'step' => 5, 'data-ecod-show-when' => 'ECardMode:!=bare|title|compact']);
ecod_admin_field($form, 'text', 'EStale', '180', '文章过期提醒', '文章超过这么多天未更新时，正文上方提示内容可能已过时；填 0 关闭', [], 'post');
ecod_admin_field($form, 'select', 'EShowMore', 'off', '显示更多', '长文先折叠到指定高度（底部渐隐遮罩 + 「展开全文」按钮），点击后展开全文。正文始终完整输出在 HTML 里，搜索引擎可正常抓取；密码 / 付费截断的文章不叠加此效果', [
    'off'    => '关闭（默认）',
    'mobile' => '仅移动端',
    'all'    => '全部设备',
], 'post');
ecod_admin_field($form, 'number', 'EShowMoreH', '1200', '折叠高度（px）', '正文超过「折叠高度 + 200px」才启用折叠，短文不受影响', [], 'post', ['min' => 400, 'max' => 6000, 'step' => 100, 'data-ecod-show-when' => 'EShowMore:mobile|all']);
ecod_admin_field($form, 'text', 'EShowMoreText', '展开全文', '按钮文案', '折叠时显示在正文底部的按钮文字', [], 'post', ['data-ecod-show-when' => 'EShowMore:mobile|all']);
ecod_admin_field($form, 'select', 'EFloatToc', 'on', '浮动目录', '文章页目录以浮动弹窗呈现，位置与侧栏互斥：侧栏在右则目录浮在左，侧栏在左则浮在右，不再遮挡侧栏；关闭后回退为侧栏里的静态目录卡', [
    'on'  => '开启（默认）',
    'off' => '关闭',
], 'post');
ecod_admin_field($form, 'select', 'ETocProgress', 'on', '目录进度条', '浮动目录按钮套阅读进度环，弹层头部显示进度条与「x / n 节」，当前节之前的项目标已读；关闭后回到纯按钮形态。页面顶部进度条不受此开关影响', [
    'on'  => '开启（默认）',
    'off' => '关闭',
], 'post');
ecod_admin_field($form, 'select', 'ETocEntry', 'float', '目录入口', '文章页目录的常驻入口取其一：「浮目录胶囊」是悬浮在侧栏对面边的胶囊按钮；「工具条目录按钮」把入口收进阅读工具条（浮目录胶囊隐藏，弹层仍由它弹出）；选「两者都显示」则两处入口并存。需先开启「浮动目录」，其关闭时两者都不显示、目录退回侧栏静态卡', [
    'float' => '浮目录胶囊（默认）',
    'bar'   => '工具条目录按钮',
    'both'  => '两者都显示',
], 'post');
ecod_admin_field($form, 'select', 'ELikers', 'on', '点赞头像堆叠', '互动条点赞按钮下方展示最近点赞的注册用户头像（最多 5 个，超出并入「等 N 人」）；还没有注册用户点赞时自动不输出，只保留数字', [
    'on'  => '开启（默认）',
    'off' => '关闭',
], 'post');
ecod_admin_field($form, 'select', 'EQuickBar', 'on', '快速评论条', '移动端滚过一屏正文且评论区还在下方时，视口底部淡入胶囊条；点击平滑滚到评论区并聚焦输入框。桌面不显示', [
    'on'  => '开启（默认）',
    'off' => '关闭',
], 'post');
ecod_admin_field($form, 'select', 'ECardMotion', 'on', '卡片动效', '磁贴快捷区、聚合 Tab 卡等模块的 hover 上浮阴影与图片渐入总开关（设计稿 guaqi-reference §F5）；关闭后仅保留状态变色。系统「减少动态效果」始终优先', [
    'on'  => '开启（默认）',
    'off' => '关闭',
], 'post');
ecod_admin_field($form, 'select', 'EReadBarMode', 'top', '阅读工具条形态', '文章页「阅读工具条」（目录 / 朗读 / 字号 / 进度）的呈现形态：吸顶横条常驻正文上方，滚动时自动吸顶；侧栏竖栏固定悬浮在侧栏一侧垂直居中，与浮动目录互斥成对（浮目录在侧栏对面边）。窄屏（<900px）两种形态都回落为吸顶横条', [
    'top'  => '吸顶横条（默认）',
    'side' => '侧栏竖栏',
], 'post');
ecod_admin_field($form, 'select', 'EDark', 'auto', '深色模式', '关闭后不加载切换按钮与首帧脚本', [
    'auto' => '跟随系统',
    'on'   => '允许手动切换',
    'off'  => '关闭',
], 'appearance');
ecod_admin_media($form, 'textarea', 'EThumbnail', '', '默认缩略图池', '文章没有封面时从这里随机取一张。每行一个地址，也可以直接从媒体库多选', 'layout', 'multi', ['data-ecod-show-when' => 'ECardMode:!=bare|title|compact']);
ecod_admin_field($form, 'select', 'EThumbServer', 'on', '服务端缩略图裁剪', '列表封面按位置自动裁剪成小尺寸 WebP（需 PHP GD 扩展，仅对本站上传的图片生效）；远程图与生成失败时回退原图', [
    'on'  => '开启',
    'off' => '关闭',
], 'layout', ['data-ecod-show-when' => 'ECardMode:!=bare|title|compact']);

/* 媒体资源库：存储驱动 / WebP / 格式白名单（设计文档 docs/dev/MEDIA-STORAGE.md） */
$ecodMediaTypesWarn = '';
if (function_exists('ecod_media_missing_global_types')) {
    $ecodMissingTypes = ecod_media_missing_global_types();
    if ($ecodMissingTypes) {
        $ecodMediaTypesWarn = '；⚠ Typecho 全局附件类型缺少 ' . implode(' / ', $ecodMissingTypes)
            . '，请到「设置 → 基本」的允许上传类型里补勾，否则部分格式仍会被拒';
    }
}
ecod_admin_field($form, 'select', 'EMediaStorage', 'local', '存储驱动', '媒体库上传与编辑器粘贴统一走主题自建通道：local 落本站 /usr/uploads/（与原生一致）；oss / cos / s3 直传对象存储，正文引用与后台列表零改动；配置不完整自动回退 local' . $ecodMediaTypesWarn, [
    'local' => '本地（默认）',
    'oss'   => '阿里云 OSS',
    'cos'   => '腾讯云 COS',
    's3'    => 'S3 兼容（MinIO / R2 / 七牛等）',
], 'media');
ecod_admin_field($form, 'select', 'EMediaDualWrite', 'off', '云端+本地双写', '开启后本地保留一份拷贝，方便回退与本地缩略图裁剪；代价是双倍磁盘', [
    'off' => '关闭（默认）',
    'on'  => '开启',
], 'media', ['data-ecod-show-when' => 'EMediaStorage:!=local']);
ecod_admin_field($form, 'text', 'EMediaMaxBytes', '16777216', '单文件上限（字节）', '媒体库上传接口的硬上限，默认 16MB；Typecho / PHP 自身的上传限制依然生效', [], 'media');
ecod_admin_field($form, 'select', 'EWebpConvert', 'on', '上传即转 WebP', 'JPG / PNG / BMP / TIFF 上传时自动转存为 WebP（需 PHP GD）；GIF 动图、SVG、AVIF 跳过；转换失败自动回落原格式', [
    'on'  => '开启（默认）',
    'off' => '关闭',
], 'media');
ecod_admin_field($form, 'text', 'EWebpQuality', '82', 'WebP 质量', '1-100，默认 82（与服务端缩略图一致）', [], 'media', ['data-ecod-show-when' => 'EWebpConvert:on']);
ecod_admin_field($form, 'select', 'EWebpKeepOriginal', 'off', '保留原图', '开启后转换时额外保留一份原图（-orig 后缀），便于回退；多占一份存储', [
    'off' => '不保留（默认）',
    'on'  => '保留',
], 'media', ['data-ecod-show-when' => 'EWebpConvert:on']);
ecod_admin_field($form, 'select', 'EMediaAllowSvg', 'off', '允许上传 SVG', 'SVG 是文本格式、可能携带脚本，默认拒绝；开启后仅编辑器角色可上传，前台不做内联渲染', [
    'off' => '拒绝（默认）',
    'on'  => '允许',
], 'media');
ecod_admin_field($form, 'text', 'EMediaAllowExt', '', '追加允许的扩展名', '逗号分隔，如 heic,heif；仅媒体库生效，仍需在 Typecho「设置 → 基本」补全局附件类型', [], 'media');
/* OSS / COS / S3 凭据（按驱动条件显示） */
ecod_admin_field($form, 'text', 'EMediaOssKey', '', 'AccessKeyId', '阿里云 RAM 访问密钥', [], 'media', ['data-ecod-show-when' => 'EMediaStorage:oss']);
ecod_admin_field($form, 'text', 'EMediaOssSecret', '', 'AccessKeySecret', '阿里云访问密钥；建议使用仅含 OSS 写权限的 RAM 子账号', [], 'media', ['data-ecod-show-when' => 'EMediaStorage:oss']);
ecod_admin_field($form, 'text', 'EMediaOssBucket', '', 'Bucket', '存储桶名称', [], 'media', ['data-ecod-show-when' => 'EMediaStorage:oss']);
ecod_admin_field($form, 'text', 'EMediaOssEndpoint', '', 'Endpoint', '如 oss-cn-hangzhou.aliyuncs.com（不带 https:// 与 Bucket 前缀）', [], 'media', ['data-ecod-show-when' => 'EMediaStorage:oss']);
ecod_admin_field($form, 'text', 'EMediaOssDomain', '', '自定义 CDN 域名', '留空用 https://Bucket.Endpoint；填写后缩略图会带上 x-oss-process 图片处理参数', [], 'media', ['data-ecod-show-when' => 'EMediaStorage:oss']);
ecod_admin_field($form, 'text', 'EMediaCosKey', '', 'SecretId', '腾讯云访问密钥', [], 'media', ['data-ecod-show-when' => 'EMediaStorage:cos']);
ecod_admin_field($form, 'text', 'EMediaCosSecret', '', 'SecretKey', '腾讯云访问密钥；建议使用仅含 COS 权限的子账号', [], 'media', ['data-ecod-show-when' => 'EMediaStorage:cos']);
ecod_admin_field($form, 'text', 'EMediaCosBucket', '', '存储桶', '格式：名称-appid，如 blog-1250000000', [], 'media', ['data-ecod-show-when' => 'EMediaStorage:cos']);
ecod_admin_field($form, 'text', 'EMediaCosRegion', '', '地域', '如 ap-guangzhou / ap-shanghai', [], 'media', ['data-ecod-show-when' => 'EMediaStorage:cos']);
ecod_admin_field($form, 'text', 'EMediaCosDomain', '', '自定义 CDN 域名', '留空用默认桶域名；填写后缩略图会带上 imageMogr2 图片处理参数', [], 'media', ['data-ecod-show-when' => 'EMediaStorage:cos']);
ecod_admin_field($form, 'text', 'EMediaS3Key', '', 'Access Key', 'S3 兼容存储的访问密钥', [], 'media', ['data-ecod-show-when' => 'EMediaStorage:s3']);
ecod_admin_field($form, 'text', 'EMediaS3Secret', '', 'Secret Key', 'S3 兼容存储的私有密钥', [], 'media', ['data-ecod-show-when' => 'EMediaStorage:s3']);
ecod_admin_field($form, 'text', 'EMediaS3Bucket', '', 'Bucket', '存储桶名称', [], 'media', ['data-ecod-show-when' => 'EMediaStorage:s3']);
ecod_admin_field($form, 'text', 'EMediaS3Region', '', 'Region', '如 auto（R2）/ us-east-1（AWS）/ 自定义（MinIO）', [], 'media', ['data-ecod-show-when' => 'EMediaStorage:s3']);
ecod_admin_field($form, 'text', 'EMediaS3Endpoint', '', 'Endpoint', '如 xxx.r2.cloudflarestorage.com 或 minio.example.com:9000（不带 https://）', [], 'media', ['data-ecod-show-when' => 'EMediaStorage:s3']);
ecod_admin_field($form, 'text', 'EMediaS3Domain', '', '自定义域名', '留空用 https://Bucket.Endpoint；S3 系无统一图片处理，缩略图回退原图', [], 'media', ['data-ecod-show-when' => 'EMediaStorage:s3']);
ecod_admin_field($form, 'select', 'ELike', 'on', '列表卡片点赞', '首页 / 归档卡片右上角的点赞按钮；文章页互动条在「文章页 → 互动按钮」里单独控制', [
    'on'  => '显示',
    'off' => '隐藏',
], 'interact');
ecod_admin_field($form, 'select', 'EAuthorOnline', 'on', '作者卡在线点', '侧栏作者卡头像右下显示在线呼吸点（博主 5 分钟内登录过即判定在线）与「博主」徽章；博主 uid 在模块管理台的作者卡片参数里配置，留空自动识别', [
    'on'  => '开启（默认）',
    'off' => '关闭',
], 'interact');
ecod_admin_field($form, 'select', 'EFriendApply', 'on', '友链一键申请', '友链页显示「一键申请」按钮与弹窗，访客填完即入库待审；审核通过后自动写入友链页正文。关闭后只保留页面里的说明文字', [
    'on'  => '开启',
    'off' => '关闭',
], 'interact');
ecod_admin_field($form, 'select', 'ECLike', 'on', '评论点赞', '每条评论右下角的「赞」按钮，访客按浏览器指纹去重、登录用户跨设备同步；第三方评论系统接管时不显示', [
    'on'  => '开启（默认）',
    'off' => '关闭',
], 'interact');
ecod_admin_field($form, 'textarea', 'ECopyrightSet', '', '版权模板', '多套版权声明的快捷切换。每行一套：「名称丨声明文本」，文章自定义字段 copyright 填名称即选用对应文本；没填字段的文章仍用「文章页 → 版权声明」模块里的通用说明', [], 'post');
ecod_admin_field($form, 'select', 'EContribute', 'off', '前端投稿', '登录用户在「投稿」页提交文章，进入后台「待审核」列表，管理员点发布即上线。需先用「站长 → 工具与备份」创建投稿页并放入导航', [
    'off' => '关闭',
    'on'  => '开启',
], 'interact');
ecod_admin_field($form, 'select', 'EContributeGroup', 'contributor', '投稿用户组下限', '低于该用户组不能投稿。订阅者=所有登录用户；投稿者=Typecho 后台「投稿者」及以上', [
    'contributor' => '投稿者及以上（默认）',
    'subscriber'  => '所有登录用户',
], 'interact', ['data-ecod-show-when' => 'EContribute:on']);
ecod_admin_field($form, 'text', 'EContributeCats', '', '可投稿分类', '顶级分类的 mid，逗号分隔；留空表示全部顶级分类都可投稿。mid 可在后台「管理 → 分类」的编辑地址里看到', [], 'interact', ['data-ecod-show-when' => 'EContribute:on']);
ecod_admin_field($form, 'textarea', 'EContributeNote', "投稿前请先阅读：\n· 内容须为原创或已获授权，严禁抄袭与拼凑；\n· 排版支持 Markdown，代码请放进代码块；\n· 提交后进入待审核，通过后即发布；不通过可在「个人中心 → 我的文章」里修改后重新提交。", '投稿须知', '显示在投稿表单上方的说明文字，支持换行', [], 'interact', ['data-ecod-show-when' => 'EContribute:on']);
ecod_admin_media($form, 'text', 'EUcCover', '', '个人中心默认封面', '个人中心顶部的背景图，从媒体库选图或直接粘贴图片 URL；留空显示主题色渐变。用户可在个人中心上传自己的封面覆盖', 'interact');
ecod_admin_field($form, 'select', 'ECtxMenu', 'on', '右键菜单', '在页面任意位置右键（输入框、可编辑区除外）给出主题快捷菜单：复制标题 / 复制链接 / 新标签打开 / 搜索选中文字 / 切换深浅色等', [
    'on'  => '开启',
    'off' => '关闭',
], 'appearance');
ecod_admin_field($form, 'textarea', 'ECtxItems', '', '自定义菜单项', '追加到右键菜单底部，一行一项：菜单文字|链接地址|新窗口(1/0)。链接可填站内路径（/about）或完整网址，支持 {url} 占位为当前页地址', [], 'appearance', ['rows' => 4, 'placeholder' => "关于本站|/about|0\n提交反馈|https://github.com/|1", 'data-ecod-rows' => 'ctx', 'data-ecod-show-when' => 'ECtxMenu:on']);

/* 阅读体验（文章页）与收录 */
ecod_admin_field($form, 'select', 'ECode', 'off', '代码高亮', '开启后由本地 Prism 统一给代码块染色，外壳底色同步跟随下方「高亮配色」；关闭则改用内置高亮器（恒深色块），不加载任何额外资源', [
    'off' => '关闭',
    'on'  => '开启',
], 'post');
ecod_admin_field($form, 'select', 'ECodeTheme', 'prism', '高亮配色', 'auto 跟随明暗模式自动切换（浅色 prism / 深色 tomorrow）；也可固定指定一套。代码块底栏与代码区共用同一底色，不会出现深浅拼接', [
    'auto'           => '跟随明暗模式',
    'prism'          => 'prism 浅色',
    'coy'            => 'coy 浅色',
    'solarizedlight' => 'solarized light',
    'tomorrow'       => 'tomorrow 深色',
    'okaidia'        => 'okaidia 深色',
    'dark'           => 'dark 深色',
], 'post', ['data-ecod-show-when' => 'ECode:on']);
ecod_admin_field($form, 'select', 'ELightbox', 'on', '正文图片灯箱', '点开正文图片全屏预览，支持左右切换与 Esc 关闭', [
    'on'  => '开启',
    'off' => '关闭',
], 'post');
ecod_admin_field($form, 'select', 'EOembed', 'on', '正文 oEmbed 自动嵌入', '正文里独占一行的 YouTube / 哔哩哔哩 / X / 网易云等地址自动渲染成播放器或卡片；代码块里的地址不处理，优先于图片懒加载与外链中转', [
    'on'  => '开启',
    'off' => '关闭',
], 'post');
ecod_admin_field($form, 'select', 'EOembedSocial', 'on', '社交卡片第三方脚本', 'X / Instagram / TikTok 需要加载对应站点的脚本才能显示内容；关闭后只保留原始链接，不请求任何第三方脚本', [
    'on'  => '加载',
    'off' => '不加载',
], 'post', ['data-ecod-show-when' => 'EOembed:on']);
ecod_admin_field($form, 'select', 'ELazy', 'on', '图片懒加载', '正文图片进入视口附近才真正请求（data-src 方案），长文首屏更快', [
    'on'  => '开启',
    'off' => '关闭',
], 'seo');
ecod_admin_field($form, 'select', 'EViews', 'on', '阅读量统计', '关闭后不再累加，已记录的数值仍照常显示', [
    'on'  => '统计',
    'off' => '停止统计',
], 'interact');
/* EBirth / ESites 已并入上方「品牌与首页」组 */

/* 互动（点赞 / 投稿 / 友链）；评论开关与审核规则见下方「评论」组 */
/* ---------------- 互动 → 评论：全站开关与审核规则（并入「评论」面板） ---------------- */
ecod_admin_field($form, 'select', 'EComment', 'on', '全站评论', '关闭后所有文章与页面的评论表单、评论列表都不再输出', [
    'on'  => '开启',
    'off' => '关闭',
], 'comments');
ecod_admin_field($form, 'textarea', 'ESensitive', '', '敏感词', '命中任意一个就把评论转为「待审核」。一行一个，也支持用 || 分隔', [], 'comments', ['data-ecod-show-when' => 'EComment:on']);
ecod_admin_field($form, 'select', 'ECnOnly', 'off', '必须包含中文', '不含中文的评论转待审，用来挡纯英文机器人广告', [
    'off' => '不限制',
    'on'  => '必须含中文',
], 'comments', ['data-ecod-show-when' => 'EComment:on']);
ecod_admin_field($form, 'number', 'EMaxLen', '', '评论字数上限', '超出上限的评论转待审；填 0 或留空不限制', [], 'comments', ['min' => 0, 'max' => 3000, 'step' => 50, 'data-ecod-show-when' => 'EComment:on']);

/* ---------------- 通知与登录 → 邮件：SMTP 与通知模板（原属「互动」组） ---------------- */
ecod_admin_field($form, 'select', 'ECommentMail', 'off', '邮件通知总开关', '控制全站发信：评论提醒、回复通知、账户验证码、友链通知都走这里的 SMTP；关闭后以上邮件都不发，发送失败只写日志，不影响评论与申请提交', [
    'off' => '关闭',
    'on'  => '开启',
], 'mail');
$ecodMailWhen = ['data-ecod-show-when' => 'ECommentMail:on'];
ecod_admin_field($form, 'text', 'ECommentMailHost', '', 'SMTP 服务器', '例如 smtp.qq.com', [], 'mail', $ecodMailWhen);
ecod_admin_field($form, 'text', 'ECommentMailPort', '465', 'SMTP 端口', 'SSL 一般 465，STARTTLS 一般 587', [], 'mail', $ecodMailWhen);
ecod_admin_field($form, 'select', 'ECommentMailSecure', 'ssl', '加密方式', '按邮箱服务商的要求选择', [
    'ssl'  => 'SSL',
    'tls'  => 'STARTTLS',
    'none' => '不加密',
], 'mail', $ecodMailWhen);
ecod_admin_field($form, 'select', 'ECommentMailVerify', 'on', '校验 SMTP 证书', '开启后验证邮件服务器的 TLS 证书（防中间人窃取授权码）；个别邮箱中继证书链不全、连不上时再关', [
    'on'  => '校验（推荐）',
    'off' => '不校验',
], 'mail', $ecodMailWhen);
ecod_admin_field($form, 'text', 'ECommentMailAccount', '', '发件邮箱', '例如 yourname@qq.com', [], 'mail', $ecodMailWhen);
ecod_admin_field($form, 'text', 'ECommentMailPassword', '', '邮箱授权码', '注意是授权码，不是邮箱登录密码', [], 'mail', $ecodMailWhen);
ecod_admin_field($form, 'text', 'ECommentMailFrom', '', '发件人昵称', '留空则使用站点标题', [], 'mail', $ecodMailWhen);
ecod_admin_field($form, 'text', 'ECommentMailTo', '', '接收提醒的邮箱', '留空则发给上面填的发件邮箱', [], 'mail', $ecodMailWhen);

/* 评论推送：与邮件提醒并行的旁路通知，响应结束后异步发送（core/site/push.php） */
ecod_admin_field($form, 'select', 'EPush', 'off', '评论推送', '收到新评论时推送到微信或自建接口；发送在评论提交响应结束之后进行，不拖慢评论。失败只写日志不重试', [
    'off' => '关闭',
    'on'  => '开启',
], 'mail');
$ecodPushWhen = ['data-ecod-show-when' => 'EPush:on'];
ecod_admin_field($form, 'select', 'EPushChannel', 'wecom', '推送通道', '企业微信机器人最常用（群里建个机器人拿 webhook 地址）；WxPusher 需再填 UID；通用 webhook 收到 POST JSON {title, body, url, site}', [
    'wecom'      => '企业微信群机器人',
    'wxpusher'   => 'WxPusher（微信）',
    'serverchan' => 'Server酱',
    'bark'       => 'Bark（iOS）',
    'webhook'    => '通用 webhook',
], 'mail', $ecodPushWhen);
ecod_admin_field($form, 'text', 'EPushKey', '', '通道密钥 / 地址', '企业微信 = 机器人 webhook 完整地址；WxPusher = appToken；Server酱 = SendKey；Bark = 设备 key 或自建服务器完整地址；webhook = 接收地址', [], 'mail', $ecodPushWhen);
ecod_admin_field($form, 'text', 'EPushExtra', '', 'WxPusher UID', '仅 WxPusher 通道需要：关注应用后在用户列表里复制自己的 UID', [], 'mail', ['data-ecod-show-when' => 'EPush:on,EPushChannel:wxpusher']);
ecod_admin_field($form, 'select', 'EReplyMail', 'off', '回复通知评论者', '评论收到回复时，用同一套 SMTP 给被回复者发邮件；自己回复自己不发送', [
    'off' => '关闭',
    'on'  => '开启',
], 'mail', $ecodMailWhen);
ecod_admin_field($form, 'select', 'EAccountMail', 'off', '账户邮件（注册 / 找回密码）', '复用上面的 SMTP 账号给用户发验证码邮件；开启后登录页才会出现「找回密码」入口', [
    'off' => '关闭',
    'on'  => '开启',
], 'mail', $ecodMailWhen);
/* 友链通知模板：变量 {站点名称} {站点地址} {一句话简介} {联系邮箱} {头像地址} {本站名称} {本站地址} */
$ecodFriendMailWhen = ['data-ecod-show-when' => 'ECommentMail:on,EFriendApply:on'];
ecod_admin_field($form, 'text', 'EFriendMailSubject', '【友链申请】{站点名称}', '友链申请通知 · 主题', '有人提交友链申请时发给博主自己；需先开启上方「邮件通知总开关」并填好 SMTP。可用变量：{站点名称} {站点地址} {本站名称}', [], 'mail', $ecodFriendMailWhen);
ecod_admin_field($form, 'textarea', 'EFriendMailBody', "收到一条新的友链申请。\n\n站点名称：{站点名称}\n站点地址：{站点地址}\n一句话简介：{一句话简介}\n联系邮箱：{联系邮箱}\n\n到友链页的「待审核申请」面板可以一键通过。", '友链申请通知 · 正文', '纯文本即可，空行分段、链接自动可点。可用变量：{站点名称} {站点地址} {一句话简介} {联系邮箱} {头像地址} {本站名称} {本站地址}', [], 'mail', ['rows' => 8, 'data-ecod-show-when' => 'ECommentMail:on,EFriendApply:on']);
ecod_admin_field($form, 'text', 'EFriendPassSubject', '友链申请已通过 · {本站名称}', '审核通过通知 · 主题', '审核通过时发给申请人填的邮箱。可用变量同上', [], 'mail', $ecodFriendMailWhen);
ecod_admin_field($form, 'textarea', 'EFriendPassBody', "你好，「{站点名称}」的友链申请已通过审核。\n\n站点地址：{站点地址}\n\n已经加入友链列表，感谢分享，欢迎常来。\n{本站地址}", '审核通过通知 · 正文', '发给申请人；留空或某个模板为空时使用内置默认文案', [], 'mail', ['rows' => 6, 'data-ecod-show-when' => 'ECommentMail:on,EFriendApply:on']);
/* ---------------- 通知与登录 → 第三方登录（原属「互动」组） ---------------- */
ecod_admin_field($form, 'select', 'EOAuthGithub', 'off', 'GitHub 登录', '登录页显示「使用 GitHub 登录」；需先在 GitHub 注册 OAuth App（回调地址填：站点地址 + /usr/themes/ecod-theme/api.php?action=oauth_callback&platform=github）', [
    'off' => '关闭',
    'on'  => '开启',
], 'oauth');
$ecodOAuthWhen = ['data-ecod-show-when' => 'EOAuthGithub:on'];
ecod_admin_field($form, 'text', 'EOAuthGithubId', '', 'GitHub Client ID', 'GitHub OAuth App 的 Client ID', [], 'oauth', $ecodOAuthWhen);
ecod_admin_field($form, 'text', 'EOAuthGithubSecret', '', 'GitHub Client Secret', 'GitHub OAuth App 的 Client Secret，注意保密', [], 'oauth', $ecodOAuthWhen);

/* 第三方登录各平台：开关 + Id + Secret，端点协议与 Zibll 一致（docs/social-login.md） */
ecod_admin_field($form, 'select', 'EOAuthWeixin', 'off', '微信登录（开放平台扫码）', '需企业资质的微信开放平台「网站应用」；回调地址填：站点地址 + /usr/themes/ecod-theme/api.php?action=oauth_callback&platform=weixin', [
    'off' => '关闭',
    'on'  => '开启',
], 'oauth');
$ecodOAuthWeixinWhen = ['data-ecod-show-when' => 'EOAuthWeixin:on'];
ecod_admin_field($form, 'text', 'EOAuthWeixinId', '', '微信 AppID', '微信开放平台网站应用的 AppID', [], 'oauth', $ecodOAuthWeixinWhen);
ecod_admin_field($form, 'text', 'EOAuthWeixinSecret', '', '微信 AppSecret', '微信开放平台网站应用的 AppSecret，注意保密', [], 'oauth', $ecodOAuthWeixinWhen);

ecod_admin_field($form, 'select', 'EOAuthQQ', 'off', 'QQ 登录', '需在 QQ 互联创建应用；回调地址填：站点地址 + /usr/themes/ecod-theme/api.php?action=oauth_callback&platform=qq', [
    'off' => '关闭',
    'on'  => '开启',
], 'oauth');
$ecodOAuthQQWhen = ['data-ecod-show-when' => 'EOAuthQQ:on'];
ecod_admin_field($form, 'text', 'EOAuthQQId', '', 'QQ 互联 APP ID', 'QQ 互联应用的 APP ID', [], 'oauth', $ecodOAuthQQWhen);
ecod_admin_field($form, 'text', 'EOAuthQQSecret', '', 'QQ 互联 APP Key', 'QQ 互联应用的 APP Key，注意保密', [], 'oauth', $ecodOAuthQQWhen);

ecod_admin_field($form, 'select', 'EOAuthWeibo', 'off', '微博登录', '需在微博开放平台做网站接入；回调地址填：站点地址 + /usr/themes/ecod-theme/api.php?action=oauth_callback&platform=weibo', [
    'off' => '关闭',
    'on'  => '开启',
], 'oauth');
$ecodOAuthWeiboWhen = ['data-ecod-show-when' => 'EOAuthWeibo:on'];
ecod_admin_field($form, 'text', 'EOAuthWeiboId', '', '微博 App Key', '微博开放平台应用的 App Key', [], 'oauth', $ecodOAuthWeiboWhen);
ecod_admin_field($form, 'text', 'EOAuthWeiboSecret', '', '微博 App Secret', '微博开放平台应用的 App Secret，注意保密', [], 'oauth', $ecodOAuthWeiboWhen);

ecod_admin_field($form, 'select', 'EOAuthGitee', 'off', 'Gitee 登录', '个人开发者即可在 Gitee 创建第三方应用，零门槛；回调地址填：站点地址 + /usr/themes/ecod-theme/api.php?action=oauth_callback&platform=gitee', [
    'off' => '关闭',
    'on'  => '开启',
], 'oauth');
$ecodOAuthGiteeWhen = ['data-ecod-show-when' => 'EOAuthGitee:on'];
ecod_admin_field($form, 'text', 'EOAuthGiteeId', '', 'Gitee Client ID', 'Gitee 第三方应用的 Client ID', [], 'oauth', $ecodOAuthGiteeWhen);
ecod_admin_field($form, 'text', 'EOAuthGiteeSecret', '', 'Gitee Client Secret', 'Gitee 第三方应用的 Client Secret，注意保密', [], 'oauth', $ecodOAuthGiteeWhen);

/* 公共开关：对全部平台生效，不再挂 GitHub 条件显隐 */
ecod_admin_field($form, 'select', 'EOAuthBindMail', 'on', '按邮箱自动绑定', '第三方账号的邮箱与本站用户邮箱一致时，首次登录自动绑定（关闭后需账号密码登录一次才能绑定）；仅 GitHub 等能拿到邮箱的平台生效', [
    'off' => '关闭',
    'on'  => '开启',
], 'oauth');
ecod_admin_field($form, 'select', 'EOAuthRegister', 'on', '第三方自动建号', '第三方账号没有匹配到本站用户时自动创建 subscriber 账号', [
    'off' => '关闭',
    'on'  => '开启',
], 'oauth');

/* ============================================================
 * 评论：嵌套展示 + 外部服务
 *
 * 第三方评论系统接管时，原生评论列表与表单都不再输出；
 * Turnstile / Akismet 只作用于原生评论（第三方由服务端自己处理）。
 * ========================================================== */
ecod_admin_field($form, 'select', 'ECommentProvider', 'native', '评论服务', '选第三方评论系统后，原生评论列表与表单不再输出，由外部服务完整接管（文章页、独立页面通用）', [
    'native' => '原生（Typecho）',
    'twikoo' => 'Twikoo',
    'waline' => 'Waline',
    'artalk' => 'Artalk',
    'giscus' => 'Giscus（GitHub Discussions）',
], 'comments', ['data-ecod-show-when' => 'EComment:on']);
ecod_admin_field($form, 'text', 'ECommentTwikoo', '', 'Twikoo 环境地址', '填 envId（腾讯云开发环境 ID）或自建服务地址 https://twikoo.example.com', [], 'comments', ['data-ecod-show-when' => 'ECommentProvider:twikoo']);
ecod_admin_field($form, 'text', 'ECommentWaline', '', 'Waline 服务地址', '例如 https://waline.example.com，末尾不要带 /', [], 'comments', ['data-ecod-show-when' => 'ECommentProvider:waline']);
ecod_admin_field($form, 'text', 'ECommentArtalk', '', 'Artalk 服务地址', '例如 https://artalk.example.com', [], 'comments', ['data-ecod-show-when' => 'ECommentProvider:artalk']);
ecod_admin_field($form, 'text', 'ECommentGiscusRepo', '', 'Giscus 仓库', 'owner/repo，仓库需公开并已开启 Discussions', [], 'comments', ['data-ecod-show-when' => 'ECommentProvider:giscus']);
ecod_admin_field($form, 'text', 'ECommentGiscusRepoId', '', 'Giscus Repo ID', '在 giscus.app 配置页复制 data-repo-id', [], 'comments', ['data-ecod-show-when' => 'ECommentProvider:giscus']);
ecod_admin_field($form, 'text', 'ECommentGiscusCategory', '', 'Giscus 分类名', '例如 Announcements', [], 'comments', ['data-ecod-show-when' => 'ECommentProvider:giscus']);
ecod_admin_field($form, 'text', 'ECommentGiscusCategoryId', '', 'Giscus 分类 ID', '在 giscus.app 配置页复制 data-category-id', [], 'comments', ['data-ecod-show-when' => 'ECommentProvider:giscus']);
ecod_admin_field($form, 'select', 'ECommentGiscusMapping', 'pathname', 'Giscus 映射方式', '决定哪篇文章对应哪个 Discussion；改这个值等于换一套讨论串', [
    'pathname' => '按路径（推荐）',
    'title'    => '按标题',
    'url'      => '按完整地址',
    'og:title' => '按 og:title',
], 'comments', ['data-ecod-show-when' => 'ECommentProvider:giscus']);
ecod_admin_field($form, 'select', 'ECommentFold', '3', '嵌套折叠深度', '前 N 层始终展开，更深的回复收进「展开 N 条回复」，点开后再显示。Typecho 后台「讨论」里的最大嵌套层数决定上限', [
    '2' => '2 层',
    '3' => '3 层（推荐）',
    '4' => '4 层',
    '5' => '5 层',
], 'comments', ['data-ecod-show-when' => 'ECommentProvider:native,ECommentExpandMode:!all']);
ecod_admin_field($form, 'select', 'ECommentExpandMode', 'manual', '长讨论的展开方式', '决定深层回复怎么呈现：「逐条展开」每条折叠各自有按钮；「顶部总按钮」在评论列表上方给一个「展开全部回复」，一次铺开所有折叠；「全部展开」不再折叠，长讨论会把页面拉得很长', [
    'manual' => '逐条展开（默认）',
    'button' => '顶部总按钮（展开全部回复）',
    'all'    => '全部展开（不折叠）',
], 'comments', ['data-ecod-show-when' => 'ECommentProvider:native']);
ecod_admin_field($form, 'select', 'ETurnstile', 'off', '人机验证 Turnstile', 'Cloudflare 免费人机验证，提交评论时服务端校验；只作用于原生评论', [
    'off' => '关闭',
    'on'  => '开启',
], 'comments', ['data-ecod-show-when' => 'ECommentProvider:native']);
ecod_admin_field($form, 'text', 'ETurnstileSite', '', 'Turnstile Site Key', '在 Cloudflare 控制台 → Turnstile 创建站点后获得（公开值）', [], 'comments', ['data-ecod-show-when' => 'ETurnstile:on']);
ecod_admin_field($form, 'text', 'ETurnstileSecret', '', 'Turnstile Secret Key', '同一页面的私密密钥，只在服务端使用，不会输出到前台', [], 'comments', ['data-ecod-show-when' => 'ETurnstile:on']);
ecod_admin_field($form, 'select', 'EAkismet', 'off', '反垃圾 Akismet', '提交时把评论送 Akismet 检测，判定为垃圾的直接标记为 spam（前台与后台默认列表都不显示）', [
    'off' => '关闭',
    'on'  => '开启',
], 'comments', ['data-ecod-show-when' => 'ECommentProvider:native']);
ecod_admin_field($form, 'text', 'EAkismetKey', '', 'Akismet API Key', '在 akismet.com 获取；填错或网络不通时放行，不影响正常评论', [], 'comments', ['data-ecod-show-when' => 'EAkismet:on']);
ecod_admin_field($form, 'select', 'EEmojiStyle', 'unicode', '评论表情', 'Emoji 是字符，全平台显示最稳；图片表情包用的是主题内置的 31 个表情，可在下面换成外部 CDN', [
    'unicode' => '系统 Emoji（推荐）',
    'smiley'  => '图片表情包',
], 'comments', ['data-ecod-show-when' => 'ECommentProvider:native']);
ecod_admin_field($form, 'text', 'EEmojiCdn', '', '表情包 CDN 前缀', '填了就走外部 CDN，例如 https://cdn.example.com/smiley/；留空用主题本地 assets/img/smiley/', [], 'comments', ['data-ecod-show-when' => 'EEmojiStyle:smiley']);

/* ============================================================
 * 内容 → 文章页：AI 摘要（原「AI 助手」面板并入文章页）
 * 文章页异步生成摘要，结果按正文 hash 缓存；见 core/content/ai.php
 * ========================================================== */
ecod_admin_field($form, 'select', 'EAI', 'off', 'AI 智能摘要', '总开关。开启后文章页顶部会显示 AI 生成的摘要卡片，摘要按正文内容缓存，正文更新后自动重新生成', [
    'on'  => '开启',
    'off' => '关闭（默认）',
], 'post');
$ecodAiWhen = ['data-ecod-show-when' => 'EAI:on'];
ecod_admin_field($form, 'select', 'EAIProvider', 'openai', '接口协议', '均走 OpenAI 兼容的 chat/completions 协议，选「自定义」时按下面的 API 地址原样请求', [
    'openai'   => 'OpenAI 兼容',
    'deepseek' => 'DeepSeek',
    'qwen'     => '通义千问',
    'zhipu'    => '智谱',
    'custom'   => '自定义',
], 'post', $ecodAiWhen);
ecod_admin_field($form, 'text', 'EAIEndpoint', '', 'API 地址', '例如 https://api.deepseek.com/v1/chat/completions', [], 'post', $ecodAiWhen);
ecod_admin_field($form, 'text', 'EAIKey', '', 'API Key', '在服务方控制台获取；仅保存在站点配置里，不会输出到前台页面', [], 'post', $ecodAiWhen);
ecod_admin_field($form, 'text', 'EAIModel', '', '模型名称', '例如 deepseek-chat、qwen-plus、glm-4-flash', [], 'post', $ecodAiWhen);
ecod_admin_field($form, 'textarea', 'EAIPrompt', '', '摘要提示词', '留空使用主题内置提示词；接入后可在这里控制摘要的语气与长度', [], 'post', $ecodAiWhen);
ecod_admin_field($form, 'select', 'EAIMotion', 'off', '摘要打字机回放', '开启后摘要正文按打字机节奏逐步显示（约 1.5 秒播完）；系统开启「减少动态效果」时自动直出', [
    'on'  => '开启',
    'off' => '关闭（默认，直接显示全文）',
], 'post', $ecodAiWhen);

/* ============================================================
 * 互动 → 积分与 AI：Credit 流水（core/user/credits.php）
 * + AI 对话页计费（page-ai.php，复用上面的 EAI* 接口配置）
 * ========================================================== */
ecod_admin_field($form, 'select', 'ECrOn', 'off', 'Credit 积分', '总开关。开启后用户可通过签到、评论、被赞等任务赚取积分，用于 AI 对话与抽奖', [
    'on'  => '开启',
    'off' => '关闭（默认）',
], 'credits');
$ecodCrWhen = ['data-ecod-show-when' => 'ECrOn:on'];
ecod_admin_field($form, 'text', 'ECrName', 'Credit', '积分名称', '全站展示口径，例如 Credit / 积分 / 金币', [], 'credits', $ecodCrWhen);
ecod_admin_field($form, 'select', 'ECrTicker', 'off', '动态播报', '开启后「更新动态」挂件可对外滚动展示最新的积分奖励事件（谁签到 / 中奖 / 被赞，含用户名）；关闭时挂件自动隐藏', [
    'on'  => '开启',
    'off' => '关闭（默认）',
], 'credits', $ecodCrWhen);
ecod_admin_field($form, 'number', 'ECrCheckin', '5', '签到奖励', '每日签到一次；连续天数只做展示，奖励金额固定', [], 'credits', ['min' => 0, 'max' => 100, 'step' => 1] + $ecodCrWhen);
ecod_admin_field($form, 'number', 'ECrComment', '2', '评论奖励', '登录用户发表评论通过后发放', [], 'credits', ['min' => 0, 'max' => 50, 'step' => 1] + $ecodCrWhen);
ecod_admin_field($form, 'number', 'ECrCommentCap', '10', '评论每日上限', '当天累计到上限后不再发；填 0 不限', [], 'credits', ['min' => 0, 'max' => 200, 'step' => 1] + $ecodCrWhen);
ecod_admin_field($form, 'number', 'ECrLikeRecv', '1', '被赞奖励', '文章每被赞一次给作者加分（作者自赞不计）', [], 'credits', ['min' => 0, 'max' => 50, 'step' => 1] + $ecodCrWhen);
ecod_admin_field($form, 'number', 'ECrLikeCap', '20', '被赞每日上限', '当天累计到上限后不再发；填 0 不限', [], 'credits', ['min' => 0, 'max' => 500, 'step' => 1] + $ecodCrWhen);
ecod_admin_field($form, 'number', 'ECrPost', '20', '投稿奖励', '预留：发布文章发放（当前版本先由管理员手动调整）', [], 'credits', ['min' => 0, 'max' => 500, 'step' => 1] + $ecodCrWhen);
ecod_admin_field($form, 'number', 'ECrPostCap', '40', '投稿每日上限', '当天累计到上限后不再发；填 0 不限', [], 'credits', ['min' => 0, 'max' => 2000, 'step' => 1] + $ecodCrWhen);
ecod_admin_field($form, 'number', 'ECrVipGift', '0', '开会员赠送', '购买会员结算成功后一次性赠送的积分（guaqi 式拉新钩子，例如 500）；填 0 关闭', [], 'credits', ['min' => 0, 'max' => 100000, 'step' => 10] + $ecodCrWhen);
ecod_admin_field($form, 'select', 'ECrRefOn', 'off', '推广返佣', '用户带 ?ref=推荐人UID 的链接注册后，其每笔付费订单结算时给推荐人返积分（1 元 = 100 Credit 基准）', [
    'on'  => '开启',
    'off' => '关闭（默认）',
], 'credits');
ecod_admin_field($form, 'number', 'ECrRefPercent', '10', '返佣比例（%）', '按订单金额的百分比返给推荐人，例如 10 = 订单 ¥50 返 500 Credit；范围 1–50', [], 'credits', ['min' => 1, 'max' => 50, 'step' => 1, 'data-ecod-show-when' => 'ECrOn:on,ECrRefOn:on']);
ecod_admin_field($form, 'select', 'EAIChat', 'on', 'AI 助手对话页', '开启后「AI 助手」页面（page-ai.php）可用，复用上面「AI 智能摘要」的接口配置；接口未配置时页面会提示', [
    'on'  => '开启',
    'off' => '关闭',
], 'credits', $ecodCrWhen);
ecod_admin_field($form, 'number', 'ECrAICost', '1', 'AI 每条消息消耗', '用户每发一条消息扣多少积分；填 0 = 免费开放（仍有登录与限速保护）', [], 'credits', ['min' => 0, 'max' => 100, 'step' => 1, 'data-ecod-show-when' => 'ECrOn:on,EAIChat:on']);
ecod_admin_field($form, 'number', 'EAIChatCtx', '4', '对话上下文轮数', '带几轮历史给模型（1 轮 = 一问一答）；越大越连贯也越耗 token', [], 'credits', ['min' => 0, 'max' => 10, 'step' => 1, 'data-ecod-show-when' => 'ECrOn:on,EAIChat:on']);
ecod_admin_field($form, 'textarea', 'EAIChatPrompt', '', 'AI 系统提示词', '定义助手的人设与边界；留空使用主题内置提示词', [], 'credits', ['rows' => 4, 'data-ecod-show-when' => 'ECrOn:on,EAIChat:on']);
ecod_admin_field($form, 'textarea', 'EAIChatWelcome', '', '开场白', '对话页的第一条消息；留空使用主题内置文案', [], 'credits', ['rows' => 2, 'data-ecod-show-when' => 'ECrOn:on,EAIChat:on']);

/* 注入与性能 */
ecod_admin_field($form, 'textarea', 'ECustomCss', '', '自定义 CSS', '不用写 style 标签；圆角、间距、字号都能在这里覆盖', [], 'appearance');
ecod_admin_field($form, 'textarea', 'ECustomHead', '', 'head 追加代码', '原样输出到 </head> 之前，用于站长验证、第三方样式表等', [], 'seo');
ecod_admin_field($form, 'textarea', 'ECustomBody', '', 'body 末尾代码', '原样输出到 </body> 之前，用于统计脚本、客服悬浮窗等', [], 'seo');
ecod_admin_field($form, 'text', 'EFont', '', '自定义字体地址', 'woff / woff2 直链，会以 @font-face 注入并作为全站首选字体', [], 'appearance');
ecod_admin_field($form, 'text', 'ECdn', '', '静态资源 CDN', '把主题 assets 目录传到 CDN 后填前缀，例如 https://cdn.example.com/ecod-theme', [], 'seo');
ecod_admin_field($form, 'select', 'EAssetMode', '', '静态资源模式', '显式选择资源走本地还是 CDN。选 CDN 前需要先填「静态资源 CDN」前缀；留空/未保存过时保持历史行为（填了前缀即走 CDN）', [
    ''      => '自动（跟随后台原有行为）',
    'local' => '本地模式（忽略 CDN）',
    'cdn'   => 'CDN 模式',
], 'seo');
ecod_admin_field($form, 'text', 'EBaidu', '', '百度推送 Token', '填了之后可在「站长 → 工具与备份」里一键把最新文章推给百度，加快收录', [], 'seo');
ecod_admin_field($form, 'select', 'EBaiduAuto', 'off', '发布自动推送', '文章发布或更新时，响应结束后自动把该篇地址单独推给百度（24 小时内推过的自动跳过，不重复耗配额）；需先填上面的 Token', [
    'off' => '关闭',
    'on'  => '开启',
], 'seo');

/* 关键词内链：DOM 文本节点替换（core/content/keywords.php），管线位于短代码 / oEmbed 之后 */
ecod_admin_field($form, 'select', 'EKeywords', 'off', '关键词内链', '把正文里的指定关键词自动替换为链接，提升站内导航与长尾词收录。只处理纯文本，代码块、已有链接与标题一律不动', [
    'off' => '关闭',
    'on'  => '开启',
], 'seo');
ecod_admin_field($form, 'textarea', 'EKeywordsList', '', '关键词库', '每行一个词：「关键词|链接|flags|限次」。flags 可空：n = nofollow，b = 本窗口打开（默认新窗口）；限次 = 该词在单篇内最多替换几次（留空用下面的默认值）。示例：Typecho|https://typecho.org||2', ['rows' => 8], 'seo', ['data-ecod-show-when' => 'EKeywords:on']);
ecod_admin_field($form, 'number', 'EKeywordsLimit', '1', '默认替换次数', '单个关键词在单篇文章内最多替换为链接的次数，1 最克制，超过 3 容易显得堆砌', [], 'seo', ['data-ecod-show-when' => 'EKeywords:on']);
ecod_admin_field($form, 'select', 'EKeywordsAuto', 'off', '分类 / 标签名自动内链', '正文里出现与分类或标签同名的词时，自动链到对应归档页（每词每篇 1 次，只链有文章的）', [
    'off'  => '关闭',
    'cats' => '仅分类名',
    'tags' => '仅标签名',
    'both' => '分类 + 标签',
], 'seo', ['data-ecod-show-when' => 'EKeywords:on']);

/* 外观与行为 */
ecod_admin_field($form, 'select', 'EPjax', 'on', '无刷新跳转（PJAX）', '站内点击链接只替换页面主体，不重新加载整页', [
    'on'  => '开启（推荐）',
    'off' => '关闭',
], 'appearance');
ecod_admin_field($form, 'select', 'ELinkGo', 'on', '外链中转页', '文章 / 页面里的站外链接先经过 /go/ 加载页（显示目标地址并倒计时 5 秒自动跳转），站内链接不受影响', [
    'on'  => '开启（推荐）',
    'off' => '关闭',
], 'appearance');
ecod_admin_field($form, 'select', 'ELinkGoAuthor', 'on', '评论者链接中转', '评论者昵称上的站外主页链接同样先经过外链中转页，避免访客误点离开站点；关闭后只加 nofollow。需开启「外链中转页」才生效', [
    'on'  => '开启（推荐）',
    'off' => '关闭',
], 'appearance', ['data-ecod-show-when' => 'ELinkGo:on']);
/* 导航：菜单为空时才使用的旧版自定义链接（保留用于快速补一两条） */
ecod_admin_field($form, 'textarea', 'ENavLinks', '', '兜底自定义链接', '仅在「菜单结构」为空时生效：一行一个，格式「名称 || 地址 || 新窗口」，第三段可省略。例如：GitHub || https://github.com/you || 1。菜单配好后这里会被忽略', [], 'nav', ['rows' => 4]);
ecod_admin_field($form, 'number', 'ENavMax', '', '导航最多显示', '超过这个数量的条目收进「更多」下拉；填 0 或留空则全部平铺。一级与二级都按这个规则处理', [], 'nav', ['min' => 0, 'max' => 12, 'step' => 1]);
ecod_admin_field($form, 'select', 'ENavStyle', 'auto', '二级菜单样式（全局默认）', '导航套选「跟随全局」或未设样式时使用：自动（有图标显示图标卡片）/ 纯文字列表 / 图标卡片（无图标用首字）/ 宽幅图标卡片（两列）/ 分组宽面板（栏标题 + 条目描述）。一级项还可用「子菜单显示模式」单独覆盖', [
    'auto'        => '自动',
    'list'        => '纯文字列表',
    'icons'       => '图标卡片',
    'mega'        => '宽幅图标卡片',
    'mega-group'  => '分组宽面板',
], 'nav');
ecod_admin_field($form, 'select', 'EAnimate', 'blur', '列表入场动效', '站内换页 / 加载更多时，卡片滚进视口才逐个入场，不影响首屏', [
    'none' => '关闭',
    'blur' => '模糊上浮（推荐，显影+聚焦+落座）',
    'zoom' => '缩放浮现',
    'up'   => '上浮淡入',
    'fade' => '淡入',
], 'appearance');
ecod_admin_field($form, 'select', 'ECursor', 'off', '鼠标跟随光点', '轻量光晕，触屏设备自动不加载', [
    'off' => '关闭',
    'dot' => '光点',
], 'appearance');
ecod_admin_field($form, 'select', 'EBackdrop', 'none', '站点背景', '纯 CSS 渐变不增加请求；选「自定义图片」后由下方图片与蒙层控制', [
    'none'  => '纯色',
    'soft'  => '双色柔光',
    'mesh'  => '网格渐变',
    'image' => '自定义图片',
], 'appearance');
ecod_admin_media($form, 'text', 'EBgImage', '', '背景图', '铺满视口并固定，深浅色共用同一张图；建议 1920px 以上的横图，从媒体库选图或直接粘贴 URL', 'appearance', 'single', ['data-ecod-show-when' => 'EBackdrop:image']);
ecod_admin_field($form, 'number', 'EBgMask', '55', '背景蒙层（%）', '图片上盖一层浅色（深色模式下为深色）半透明遮罩，数值越大文字越清楚、图片越淡', [], 'appearance', ['min' => 0, 'max' => 90, 'step' => 5, 'data-ecod-show-when' => 'EBackdrop:image']);

/* ============================================================
 * 付费与会员（门槛 / 权益 / 交易三层，见 core/pay/pay.php）
 * ========================================================== */
ecod_admin_field($form, 'select', 'EPay', 'off', '付费阅读总开关', '关闭时整条链路零查询、零输出；开启后按文章自定义字段里的「付费模式」生效', [
    'off' => '关闭（默认）',
    'on'  => '开启',
], 'pay');
$ecodPayWhen = ['data-ecod-show-when' => 'EPay:on'];
ecod_admin_field($form, 'text', 'EPayCurrency', '¥', '货币符号', '显示在价格前，例如 ¥ / $ / 积分', [], 'pay', $ecodPayWhen);
ecod_admin_field($form, 'select', 'EPayAuthorFree', 'on', '作者本人免费', '作者浏览自己的付费文章时不锁定；管理员与编辑始终可读', [
    'on'  => '是（推荐）',
    'off' => '否',
], 'pay', $ecodPayWhen);
ecod_admin_field($form, 'select', 'EPayBox', 'top', '付费卡位置', '解锁提示卡放在正文前、正文后，或两处都放（文末那张为精简版）', [
    'top'    => '正文前',
    'bottom' => '正文后',
    'both'   => '两处都放',
    'off'    => '不显示（只隐藏正文）',
], 'pay', $ecodPayWhen);
ecod_admin_field($form, 'number', 'EPayTeaser', '80', '试读字数', '没有 <!--paymore--> 标记时，正文只保留前面这么多可见字；填 0 则完全不试读', [], 'pay', ['min' => 0, 'max' => 2000, 'step' => 10, 'data-ecod-show-when' => 'EPay:on,EPayBox:!=off']);
ecod_admin_field($form, 'number', 'EPayDays', '0', '默认购买有效期（天）', '0 = 永久。作者可在文章里单独填写天数覆盖', [], 'pay', ['min' => 0, 'max' => 3650, 'step' => 1, 'data-ecod-show-when' => 'EPay:on']);

/* 评论解锁（F2）与付费资源卡（F6），设计见 docs/dev/guaqi-reference-design.html */
ecod_admin_field($form, 'select', 'EPayCommentUnlock', 'off', '评论解锁付费', '开启后，文章自定义字段 pay_comment 填 1 时出现「评论解锁卡」：访客发表一条过审评论即可阅读该篇付费正文（读时校验评论表，删评/转待审自动上锁；会员专属内容不参与）', [
    'on'  => '开启',
    'off' => '关闭（默认）',
], 'pay', $ecodPayWhen);
ecod_admin_field($form, 'select', 'EPayResourceCard', 'off', '付费资源卡', '开启后单篇购买类付费文章的解锁卡升级为「资源卡」：预览图 + 价格 + 元数据表（自定义字段 pay_meta_size / pay_meta_updated / pay_meta_sys / pay_meta_lang / pay_meta_refund / pay_meta_term，预览图字段 pay_preview）。字段全空时自动回落普通付费卡', [
    'on'  => '开启',
    'off' => '关闭（默认）',
], 'pay', $ecodPayWhen);

/* 管理台入口：主题无法注册后台菜单，这里给出可直接打开的地址 */
$ecodPayAdminUrl = '';
try {
    $ecodPayAdminUrl = rtrim((string) \Utils\Helper::options()->themeUrl, '/') . '/pay-admin.php';
} catch (\Throwable $e) {
    $ecodPayAdminUrl = '';
}
ecod_admin_field($form, 'text', 'EPayAdminUrl', $ecodPayAdminUrl, '付费管理台', '确认收款、授予权益、生成卡密都在这个页面（仅站长可打开，建议收藏）。地址已填在右侧，复制打开即可', [], 'pay', ['readonly' => 'readonly']);

/* 支付通道 */
ecod_admin_field($form, 'select', 'EPayChannel', 'manual', '默认支付通道', '没有支付账号时用「手动确认」或「卡密」；易支付需要填完整下面三项才会出现在收银台', [
    'manual' => '手动确认（线下 / 转账）',
    'card'   => '卡密兑换',
    'epay'   => '易支付（支付宝 / 微信）',
], 'pay', $ecodPayWhen);
ecod_admin_field($form, 'textarea', 'EPayManualDoc', '', '手动付款说明', '选「手动确认」时显示在收银台，例如收款码说明、联系方式、备注要求。留空使用内置文案', [], 'pay', ['rows' => 4, 'data-ecod-show-when' => 'EPayChannel:manual']);
$ecodEpayWhen = ['data-ecod-show-when' => 'EPayChannel:epay'];
ecod_admin_field($form, 'text', 'EPayEpayUrl', '', '易支付接口地址', '例如 https://pay.example.com（不带 /submit.php）', [], 'pay', $ecodEpayWhen);
ecod_admin_field($form, 'text', 'EPayEpayPid', '', '易支付商户 ID', '易支付后台的 PID', [], 'pay', $ecodEpayWhen);
ecod_admin_field($form, 'text', 'EPayEpayKey', '', '易支付商户密钥', '只保存在站点配置里，不会输出到前台', [], 'pay', $ecodEpayWhen);
ecod_admin_field($form, 'select', 'EPayEpayType', 'alipay', '易支付默认方式', '回调地址会自动带上 channel=epay，无需在易支付后台另填', [
    'alipay' => '支付宝',
    'wxpay'  => '微信支付',
], 'pay', $ecodEpayWhen);

/* 会员档位：名称 + 四个周期价 */
ecod_admin_field($form, 'text', 'EVip1Name', '会员', '会员名称', '显示在付费卡与购买页', [], 'pay', $ecodPayWhen);
ecod_admin_field($form, 'text', 'EVip1Month', '', '会员 · 月价（元）', '留空表示不提供该周期', [], 'pay', $ecodPayWhen);
ecod_admin_field($form, 'text', 'EVip1Quarter', '', '会员 · 季价（元）', '留空表示不提供该周期', [], 'pay', $ecodPayWhen);
ecod_admin_field($form, 'text', 'EVip1Year', '', '会员 · 年价（元）', '留空表示不提供该周期', [], 'pay', $ecodPayWhen);
ecod_admin_field($form, 'text', 'EVip1Forever', '', '会员 · 永久价（元）', '留空表示不提供', [], 'pay', $ecodPayWhen);
ecod_admin_field($form, 'text', 'EVip2Name', '超级会员', '超级会员名称', '显示在付费卡与购买页', [], 'pay', $ecodPayWhen);
ecod_admin_field($form, 'text', 'EVip2Month', '', '超级会员 · 月价（元）', '留空表示不提供该周期', [], 'pay', $ecodPayWhen);
ecod_admin_field($form, 'text', 'EVip2Quarter', '', '超级会员 · 季价（元）', '留空表示不提供该周期', [], 'pay', $ecodPayWhen);
ecod_admin_field($form, 'text', 'EVip2Year', '', '超级会员 · 年价（元）', '留空表示不提供该周期', [], 'pay', $ecodPayWhen);
ecod_admin_field($form, 'text', 'EVip2Forever', '', '超级会员 · 永久价（元）', '留空表示不提供', [], 'pay', $ecodPayWhen);

/* ============================================================
 * 变现 → 站点抽奖（core/site/lottery.php）与自助广告（core/site/ads.php）
 * ========================================================== */
ecod_admin_field($form, 'select', 'ELottery', 'off', '站点抽奖', '总开关。用户消耗 Credit 抽奖，积分 / 会员奖品自动到账；页面模板 page-lottery.php（可在「站长 → 工具与备份」一键创建）', [
    'on'  => '开启',
    'off' => '关闭（默认）',
], 'lottery');
$ecodLotWhen = ['data-ecod-show-when' => 'ELottery:on'];
ecod_admin_field($form, 'text', 'ELotteryTitle', '幸运抽奖', '活动标题', '显示在抽奖页顶部', [], 'lottery', $ecodLotWhen);
ecod_admin_media($form, 'text', 'ELotteryBanner', '', '活动横幅图', '显示在抽奖页数据栏上方（16:7 裁切）；从媒体库选图或直接粘贴 URL，留空不显示', 'lottery', 'single', $ecodLotWhen);
ecod_admin_field($form, 'number', 'ELotteryCost', '10', '单次消耗', '每抽一次扣多少 Credit；填 0 = 免费抽（仍受每日次数限制）', [], 'lottery', ['min' => 0, 'max' => 1000, 'step' => 1] + $ecodLotWhen);
ecod_admin_field($form, 'number', 'ELotteryDaily', '3', '每人每日次数', '每天最多抽几次；填 0 不限', [], 'lottery', ['min' => 0, 'max' => 100, 'step' => 1] + $ecodLotWhen);
ecod_admin_field($form, 'number', 'ELotteryPity', '0', '保底次数', '连续这么多次没中奖后，下一抽必中（guaqi 式保底，进度在抽奖挂件可见）；填 0 关闭', [], 'lottery', ['min' => 0, 'max' => 100, 'step' => 1] + $ecodLotWhen);
ecod_admin_field($form, 'number', 'ELotteryExchange', '0', '头奖直接兑换价', '用户付这么多 Credit 跳过概率直接拿第一个可抽的有奖奖品（0 = 关闭直接兑换）；兑换不占每日抽奖次数', [], 'lottery', ['min' => 0, 'max' => 100000, 'step' => 10] + $ecodLotWhen);
ecod_admin_field($form, 'textarea', 'ELotteryPrizes', "谢谢参与|60|0|none|\n10 Credit|25|0|credit|10\n会员 7 天|5|5|vip|1:7", '奖品池', '逐行添加奖品：权重决定中奖概率，库存 0 = 不限，值随类型变化（积分填数量 / 会员填「等级:天数」/ 实物填兑奖说明）。恰好 8 个奖品时抽奖页显示九宫格转盘', [], 'lottery', ['rows' => 6, 'data-ecod-rows' => 'lottery'] + $ecodLotWhen);
ecod_admin_field($form, 'textarea', 'ELotteryNote', '', '活动说明', '显示在抽奖页底部，例如兑奖方式、活动时间、免责声明', [], 'lottery', ['rows' => 3] + $ecodLotWhen);

/* 兑换商城（core/site/shop.php）：积分当钱花 */
ecod_admin_field($form, 'select', 'EShop', 'off', '兑换商城', '总开关。用户用 Credit 兑换会员、单篇解锁或定制权益，页面模板 page-shop.php（可在「站长 → 工具与备份」一键创建）', [
    'on'  => '开启',
    'off' => '关闭（默认）',
], 'shop');
$ecodShopWhen = ['data-ecod-show-when' => 'EShop:on'];
ecod_admin_field($form, 'textarea', 'EShopItems', "会员 7 天|500|vip|1:7|100|0\n全站文章畅读月卡|2000|vip|2:30|0|0\n定制周边|3000|text|兑换后联系站长发货|5|1", '商品列表', '逐行添加商品：库存 0 = 不限，每人限购 0 = 不限；值随类型变化（会员填「等级:天数」/ 单篇解锁填文章 ID / 待发货填发货说明）', [], 'shop', ['rows' => 6, 'data-ecod-rows' => 'shop'] + $ecodShopWhen);
ecod_admin_field($form, 'textarea', 'EShopNote', '', '商城说明', '显示在商城页底部：发货时效、联系方式、兑换规则等', [], 'shop', ['rows' => 3] + $ecodShopWhen);

ecod_admin_field($form, 'select', 'EAdSelf', 'off', '自助广告', '总开关（依赖付费阅读）。用户在「广告投放」页（page-ads.php）选位、按天下单，走支付通道付款', [
    'on'  => '开启',
    'off' => '关闭（默认）',
], 'ads');
ecod_admin_field($form, 'select', 'EAdSelfReview', 'on', '上线前审核', '开启 = 支付完成后进入「待审核」，站长在付费管理台点通过才上线（起止时间从通过时算）；关闭 = 支付成功自动上线', [
    'on'  => '需要审核（推荐）',
    'off' => '支付后自动上线',
], 'ads', ['data-ecod-show-when' => 'EAdSelf:on']);
ecod_admin_field($form, 'text', 'EAdSelfPriceHomeTop', '', '首页顶部通栏（元/天）', '留空 = 不开放该广告位', [], 'ads', ['data-ecod-show-when' => 'EAdSelf:on']);
ecod_admin_field($form, 'text', 'EAdSelfPriceSidebar', '', '侧边栏卡片（元/天）', '侧栏「投放广告」模块展示的图片卡位', [], 'ads', ['data-ecod-show-when' => 'EAdSelf:on']);
ecod_admin_field($form, 'text', 'EAdSelfPricePostBottom', '', '文章底部通栏（元/天）', '留空 = 不开放该广告位', [], 'ads', ['data-ecod-show-when' => 'EAdSelf:on']);
ecod_admin_field($form, 'number', 'EAdSelfMinDays', '3', '最短投放天数', '', [], 'ads', ['min' => 1, 'max' => 30, 'step' => 1, 'data-ecod-show-when' => 'EAdSelf:on']);
ecod_admin_field($form, 'number', 'EAdSelfMaxDays', '90', '最长投放天数', '', [], 'ads', ['min' => 1, 'max' => 365, 'step' => 1, 'data-ecod-show-when' => 'EAdSelf:on']);
ecod_admin_field($form, 'textarea', 'EAdSelfNote', '', '投放须知', '展示在投放页底部：尺寸建议、内容规范、联系方式等', [], 'ads', ['rows' => 4, 'data-ecod-show-when' => 'EAdSelf:on']);

/* 隐藏：模块状态 */
/* （旧 EBlocks 隐藏字段已移除：模块布局存于独立行 EWidgets，由模块管理台读写） */
/* 隐藏：多套导航 JSON（由「导航」分组的构建器写入，core/site/nav.php 读取） */
ecod_admin_field($form, 'hidden', 'ENavSets', '', '', '', [], 'nav');

/* ---- 专题：构建器写入的 JSON + 展示项 ---- */
/* 隐藏：专题 JSON（由「专题」分组的构建器写入，core/site/topics.php 读取） */
ecod_admin_field($form, 'hidden', 'ETopics', '', '', '', [], 'topics');
ecod_admin_field($form, 'select', 'ETopicWallStyle', 'cover', '专题墙卡片形态', '封面卡信息量大；文字卡更紧凑，适合专题较多的站', [
    'cover' => '封面卡（上图下文）',
    'plain' => '文字卡（紧凑）',
], 'topics');
ecod_admin_field($form, 'select', 'ETopicWallCols', '3', '专题墙列数', '窄屏（≤720px）自动降为单列', [
    '2' => '2 列',
    '3' => '3 列',
    '4' => '4 列',
], 'topics');
ecod_admin_field($form, 'select', 'ETopicPerPage', '12', '专题内每页篇数', '专题详情页一次列多少篇；篇数多时翻页更少，但首屏更重', [
    '6'  => '6 篇',
    '12' => '12 篇',
    '18' => '18 篇',
    '24' => '24 篇',
], 'topics');
ecod_admin_field($form, 'select', 'ETopicMoreCount', '8', '详情页「其它专题」', '专题内容读完后的横向 chips，把读者留在专题层；选 0 整行隐藏（面包屑仍有专题入口）', [
    '0'  => '不显示',
    '4'  => '4 个',
    '8'  => '8 个',
    '12' => '12 个',
], 'topics');
ecod_admin_field($form, 'select', 'ETopicCardChip', 'on', '文章卡片显示专题', '在分类、日期旁加一枚专题标签；已做批量预取，不影响列表性能', [
    'on'  => '显示',
    'off' => '不显示',
], 'topics');

/* 专题详情页头：统一大图，留空回落各专题自己的封面 */
ecod_admin_media($form, 'text', 'ETopicHeadBg', '', '专题页头背景', '所有专题详情页共用的背景大图；留空则用各专题在构建器里设的封面，取不到时用主题色柔光', 'topics');

/* 分类页头（E1） */
$ecodCatHeadWhen = ['data-ecod-show-when' => 'ECatHead:on'];
ecod_admin_field($form, 'select', 'ECatHead', 'on', '分类页页头', '分类页顶部显示名称、描述、统计与相关专题；关闭后回到一行标题', [
    'on'  => '显示',
    'off' => '不显示',
], 'topics');
ecod_admin_field($form, 'select', 'ECatHeadBg', 'cover', '页头背景', '封面取该分类最新一篇文章的首图；也可固定用一张自定义图，或只用主题色柔光', [
    'cover'  => '最新文章封面',
    'custom' => '自定义图片',
    'soft'   => '主题色柔光',
], 'topics', $ecodCatHeadWhen);
ecod_admin_media($form, 'text', 'ECatHeadImage', '', '页头背景图', '所有分类页共用的背景图；某个分类想单独用图，到「分类增强」里给它设封面（优先级更高）', 'topics', 'single', ['data-ecod-show-when' => 'ECatHeadBg:custom']);
ecod_admin_field($form, 'select', 'ECatHeadStats', 'on', '页头数据', '篇数 / 累计阅读 / 最近更新，一条聚合查询取全', [
    'on'  => '显示',
    'off' => '不显示',
], 'topics', $ecodCatHeadWhen);
ecod_admin_field($form, 'select', 'ECatHeadTopics', 'on', '相关专题', '分类下文章命中的专题，按命中篇数排序，最多 5 个', [
    'on'  => '显示',
    'off' => '不显示',
], 'topics', $ecodCatHeadWhen);
ecod_admin_field($form, 'select', 'ECatTools', 'on', '分类工具条', '页头下方的工具条：分类内搜索（GET ?q=，/ 热键聚焦）+ 排序按钮（最新 / 最多浏览 / 最多评论 / 随机一篇）；启用时隐藏分类页重复的分类 Tab 条，关闭后回到旧形态', [
    'on'  => '开启（默认）',
    'off' => '关闭',
], 'topics');

/* ---------------- 搜索（C4 快捷搜索面板） ---------------- */
ecod_admin_field($form, 'select', 'ESearchPanel', 'on', '快捷搜索面板', '⌘K / 点击导航搜索框 / 移动端 dock 搜索按钮唤起居中面板：热词、标签、实时联想与键盘导航；关闭后保留原地的搜索下拉与 ⌘K 聚焦', [
    'on'  => '开启（默认）',
    'off' => '关闭',
], 'search');
ecod_admin_field($form, 'text', 'ESearchHot', '', '热词', '逗号 / 顿号分隔，最多 8 个；留空则面板只显示标签区。热词是编辑推荐，标签是全站概览', [], 'search', ['data-ecod-show-when' => 'ESearchPanel:on', 'placeholder' => 'Typecho, 主题开发, PHP 8.5']);
ecod_admin_field($form, 'select', 'ESearchTagCount', '10', '标签个数', '面板标签区展示的热门标签数量（按引用篇数排序）', [
    '6'  => '6 个',
    '8'  => '8 个',
    '10' => '10 个（默认）',
    '12' => '12 个',
], 'search', ['data-ecod-show-when' => 'ESearchPanel:on']);
ecod_admin_field($form, 'select', 'ESearchPosts', 'off', '推荐文章列表', '在面板空态（没输入关键词时）多一栏文章：标题 + 日期，点即跳转；结果按所选维度实时算，缓存 10 分钟', [
    'off'      => '不显示',
    'hot'      => '热门（阅读 + 评论）',
    'likes'    => '点赞最多',
    'comments' => '评论最多',
    'views'    => '阅读最多',
], 'search', ['data-ecod-show-when' => 'ESearchPanel:on']);
ecod_admin_field($form, 'select', 'ESearchPostsCount', '8', '文章条数', '面板空态展示几篇', [
    '4'  => '4 条',
    '6'  => '6 条',
    '8'  => '8 条（默认）',
    '10' => '10 条',
], 'search', ['data-ecod-show-when' => 'ESearchPanel:on,ESearchPosts:!off']);

/* 隐藏：不进导航的页面 slug（由「站长 → 工具与备份」的特色页开关写入，逗号分隔） */
ecod_admin_field($form, 'hidden', 'ENavHide', '', '', '', [], 'tools');

/* ------------------------------------------------------------------
 * 传给前端的数据（模块布局数据由模块管理台 widget.php 自己输出）
 * ---------------------------------------------------------------- */
$ecodAdminData = [
    'panes' => array_map(static function ($p) {
        return ['title' => $p[0], 'desc' => $p[1]];
    }, $ecodPanes),
    /* 面板内小节：config.js 按字段名把行分发进带标题的小节容器 */
    'sections' => array_map(static function ($sects) {
        return array_map(static function ($s) {
            return ['title' => $s[0], 'desc' => $s[1], 'fields' => $s[2]];
        }, $sects);
    }, $ecodSections),
];

/* 选图器：媒体库列表走主题自带接口，上传复用 Typecho 原生动作 */
$ecodUploadIndex = '';
try {
    $ecodUploadIndex = (string) \Widget\Security::alloc()->getIndex('/action/upload');
} catch (\Throwable $e) {
    $ecodUploadIndex = '';
}
?>
<script>
window.ECOD_BLOCKS = <?php echo json_encode($ecodAdminData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
window.ECOD_NAV = <?php echo json_encode([
    'sources' => ecod_nav_sources(),
    'sets'    => ecod_nav_sets()['sets'],
    'active'  => ecod_nav_sets()['active'],
    'home'    => ecod_nav_home_url(),
    'icons'   => ecod_icon_names(),
    /* 键名 => 完整 svg 标记：构建器图标选择器直接渲染图形，不做文本下拉 */
    'iconSvg' => (static function () {
        $map = [];
        foreach (ecod_icon_names() as $ecodIconName) {
            $map[$ecodIconName] = ecod_icon($ecodIconName, 18);
        }
        return $map;
    })(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
window.ECOD_TOPICS = <?php echo json_encode([
    'items' => ecod_topics(false),
    'index' => ecod_topic_index(),
    'page'  => ecod_topics_page(),
    'max'   => ECOD_TOPICS_MAX,
    'api'   => ecod_asset('api.php'),
    'token' => ecod_tool_token(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
window.ECOD_MEDIA = {
    api: <?php echo json_encode(ecod_asset('media.php'), JSON_UNESCAPED_SLASHES); ?>,
    upload: <?php echo json_encode($ecodUploadIndex, JSON_UNESCAPED_SLASHES); ?>,
    token: <?php echo json_encode(\Widget\Security::alloc()->getToken('ecod-media')); ?>
};
window.ECOD_TOOL = {
    api: <?php echo json_encode(ecod_asset('api.php'), JSON_UNESCAPED_SLASHES); ?>,
    token: <?php echo json_encode(ecod_tool_token(), JSON_UNESCAPED_SLASHES); ?>
};
</script>
<script src="<?php echo ecod_asset('assets/admin/media.js'); ?>" defer></script>
<script src="<?php echo ecod_asset('assets/admin/nav.js'); ?>" defer></script>
<script src="<?php echo ecod_asset('assets/admin/topics.js'); ?>" defer></script>
<script src="<?php echo ecod_asset('assets/admin/config.js'); ?>" defer></script>
<script src="<?php echo ecod_asset('assets/admin/tools.js'); ?>" defer></script>
