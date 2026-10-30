<?php
/**
 * Ecod Theme · 特色页一键创建
 *
 * 主题自带的三个独立页模板（归档时间轴 / 读者墙 / 随便看看）需要
 * 手动在后台建页并选模板，容易漏选模板导致页面变成普通文章页。
 * 这里把「建页 + 指定模板 + 设 slug」打包成一步，后台点一下就完成。
 *
 * 只做新增，不改动已有页面；slug 冲突时自动加后缀。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 三个特色页的定义
 *
 * @return array<string, array<string, string>>
 */
function ecod_pages_blueprint(): array
{
    return [
        'archives' => [
            'title'    => '归档',
            'slug'     => 'archives',
            'template' => 'page-archives.php',
            'text'     => '按年份折叠的时间轴归档，自动汇总全部已发布文章，正文内容不会显示。',
            'desc'     => '按年 / 月折叠的时间轴，一次查询取全量文章',
            'icon'     => 'calendar',
        ],
        'topics' => [
            'title'    => '专题',
            'slug'     => 'topics',
            'template' => 'page-topics.php',
            'text'     => '专题墙：按主题策展的合集，正文内容不会显示；专题本身在「设置 → 专题」里维护。',
            'desc'     => '专题墙 + 专题详情，把散落的文章串成一条线',
            'icon'     => 'layers',
        ],
        'readers' => [
            'title'    => '读者墙',
            'slug'     => 'readers',
            'template' => 'page-rank.php',
            'text'     => '按评论数排序的读者墙，自动统计每位访客的留言次数，正文内容不会显示。',
            'desc'     => '按留言数排行的读者墙，前三名带金银铜牌',
            'icon'     => 'user',
        ],
        'random' => [
            'title'    => '随便看看',
            'slug'     => 'random',
            'template' => 'page-random.php',
            'text'     => '随机跳转到一篇已发布文章，正文内容不会显示。',
            'desc'     => '访问即 302 跳到随机文章，适合放在导航里当彩蛋',
            'icon'     => 'shuffle',
        ],
        'user' => [
            'title'    => '个人中心',
            'slug'     => 'user',
            'template' => 'page-user.php',
            'text'     => '登录用户的个人中心：资料、收藏、点赞、浏览历史与订单。',
            'desc'     => '头部用户按钮默认跳到这里，建议创建后放入导航',
            'icon'     => 'user',
        ],
        'login' => [
            'title'    => '登录',
            'slug'     => 'login',
            'template' => 'page-login.php',
            'text'     => '前台登录页：主题统一风格的登录入口，登录后回跳来源页面。',
            'desc'     => '配合 EcodAuth 插件可屏蔽后台登录页，前台登录以此为准',
            'icon'     => 'user',
        ],
        'live' => [
            'title'    => '直播',
            'slug'     => 'live',
            'template' => 'live.php',
            'text'     => '把直播地址（B 站直播间、YouTube 或 iframe 代码）粘贴到这个页面的正文里，页面会自动识别并置顶播放；其余地址按回放列表展示。',
            'desc'     => '直播 / 回放页，正文里粘贴直播地址即可自动嵌入',
            'icon'     => 'radio',
        ],
        'video' => [
            'title'    => '视频集',
            'slug'     => 'videos',
            'template' => 'video.php',
            'text'     => '把视频地址一行一个粘贴到这个页面的正文里，页面会自动生成视频卡片集。',
            'desc'     => '视频集页，正文里一行一个视频地址即可生成卡片',
            'icon'     => 'video',
        ],
        'wallpaper' => [
            'title'    => '壁纸集',
            'slug'     => 'wallpapers',
            'template' => 'wallpaper.php',
            'text'     => '把图片地址一行一个粘贴到这个页面的正文里，页面会自动排成壁纸墙，支持点开看大图。',
            'desc'     => '壁纸集页，正文里粘贴图片地址即可生成壁纸墙',
            'icon'     => 'image',
        ],
        'friends' => [
            'title'    => '友情链接',
            'slug'     => 'friends',
            'template' => 'friends.php',
            'text'     => "Typecho丨https://typecho.org丨博客程序\nEcod丨https://example.com丨主题作者",
            'desc'     => '友链页，正文按「名称丨链接丨描述」一行一个填写',
            'icon'     => 'link',
        ],
        'census' => [
            'title'    => '数据统计',
            'slug'     => 'census',
            'template' => 'census.php',
            'text'     => '站点数据总览，内容自动汇总，正文不会显示。',
            'desc'     => '文章、评论、标签、分类与阅读量的统计页',
            'icon'     => 'chart',
        ],
        'cashier' => [
            'title'    => '收银台',
            'slug'     => 'cashier',
            'template' => 'page-cashier.php',
            'text'     => '收银台页面，正文内容不会显示；下单与查询状态都由接口完成。',
            'desc'     => '付费阅读的收银台，付费卡与会员页的购买按钮都指向这里',
            'icon'     => 'zap',
        ],
        'vip' => [
            'title'    => '会员',
            'slug'     => 'vip',
            'template' => 'page-vip.php',
            'text'     => '会员购买页，档位与价格来自「设置 → 付费」，正文内容不会显示。',
            'desc'     => '会员 / 超级会员的套餐列表，点击直接进收银台',
            'icon'     => 'medal',
        ],
        'contribute' => [
            'title'    => '投稿',
            'slug'     => 'contribute',
            'template' => 'page-contribute.php',
            'text'     => '前端投稿页：登录用户提交文章进入待审核，管理员在后台点发布。正文内容不会显示。',
            'desc'     => '配合「设置 → 互动 → 前端投稿」开关使用，建议放入导航',
            'icon'     => 'edit',
        ],
        'ai' => [
            'title'    => 'AI 助手',
            'slug'     => 'ai',
            'template' => 'page-ai.php',
            'text'     => 'AI 对话页：登录用户按次消耗 Credit 与站内接入的大模型对话。正文内容不会显示。',
            'desc'     => '配合「设置 → 互动 → 积分与 AI」开关使用，需要先配好 AI 接口',
            'icon'     => 'zap',
        ],
        'lottery' => [
            'title'    => '抽奖',
            'slug'     => 'lottery',
            'template' => 'page-lottery.php',
            'text'     => '站点抽奖页：消耗 Credit 抽奖，积分与会员奖品自动到账。正文内容不会显示。',
            'desc'     => '配合「设置 → 变现 → 站点抽奖」使用；恰好 8 个奖品时显示九宫格',
            'icon'     => 'medal',
        ],
        'shop' => [
            'title'    => '兑换商城',
            'slug'     => 'shop',
            'template' => 'page-shop.php',
            'text'     => '积分兑换商城：用 Credit 兑换会员、单篇解锁与定制权益，vip 类即时到账。正文内容不会显示。',
            'desc'     => '配合「设置 → 变现 → 兑换商城」使用，商品池在设置里维护',
            'icon'     => 'medal',
        ],
        'ads' => [
            'title'    => '广告投放',
            'slug'     => 'ads',
            'template' => 'page-ads.php',
            'text'     => '自助广告投放页：在线选位、按天下单，支付并审核后自动上线。正文内容不会显示。',
            'desc'     => '配合「设置 → 变现 → 自助广告」使用，审核在付费管理台进行',
            'icon'     => 'radio',
        ],
        'flash' => [
            'title'    => '快讯',
            'slug'     => 'flash',
            'template' => 'flash.php',
            'text'     => '按日期分组的时间流快讯页；页面自定义字段 flash_cat 可限定分类，文章自定义字段 source 可带来源外链。正文内容不会显示。',
            'desc'     => '短讯的完整回看页，首页「快讯」模块的「更多」会指向这里',
            'icon'     => 'zap',
        ],
        'sites' => [
            'title'    => '网址导航',
            'slug'     => 'sites',
            'template' => 'sites.php',
            'text'     => '分组式的工具站点导航；站点数据在「设置 → 站点 → 网址导航数据」里维护，这里的正文会显示为导航页说明。',
            'desc'     => '工具书签页，与友链互不占用',
            'icon'     => 'link',
        ],
        'tags' => [
            'title'    => '标签归档',
            'slug'     => 'tags',
            'template' => 'page-tags.php',
            'text'     => '全站标签墙，按文章数排序、字号随热度渐变，正文内容不会显示。',
            'desc'     => '按话题浏览的入口，配合侧栏标签云使用',
            'icon'     => 'tag',
        ],
    ];
}

/**
 * 页面的公开地址
 */
function ecod_pages_url(string $slug): string
{
    try {
        return (string) \Typecho\Router::url('page', ['slug' => $slug], \Widget\Options::alloc()->index);
    } catch (\Throwable $e) {
        return '';
    }
}

/**
 * 按模板查页面地址（取最早创建的已发布页面）
 *
 * 「个人中心 / 登录 / 投稿」这类功能页的共同形态：后台建页选模板，
 * 前端按模板名找地址。找不到（未建页）返回空串，调用方自行降级。
 */
function ecod_template_page_url(string $template): string
{
    static $cache = [];

    if (isset($cache[$template])) {
        return $cache[$template];
    }
    $cache[$template] = '';
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('slug')->from('table.contents')
                ->where('type = ?', 'page')
                ->where('status = ?', 'publish')
                ->where('template = ?', $template)
                ->order('cid', \Typecho\Db::SORT_ASC)
                ->limit(1)
        );
        if ($row) {
            $cache[$template] = (string) \Typecho\Router::url('page', ['slug' => $row['slug']], \Widget\Options::alloc()->index);
        }
    } catch (\Throwable $e) {
    }
    return $cache[$template];
}

/**
 * 投稿页地址（未建页返回空串）
 */
function ecod_contribute_url(): string
{
    return ecod_template_page_url('page-contribute.php');
}

/**
 * 后台编辑地址
 */
function ecod_pages_edit_url(int $cid): string
{
    try {
        $options = \Widget\Options::alloc();
        return rtrim((string) $options->siteUrl, '/') . '/admin/write-page.php?cid=' . $cid;
    } catch (\Throwable $e) {
        return '';
    }
}

/**
 * 现有页面（一次取全量，页面数量通常很少）
 *
 * 只统计已发布的页面：草稿 / 私密页不算「已创建」，
 * 否则后台会一直显示「已存在」而无法补建。
 *
 * @param bool $flush 传 true 丢弃缓存（新建页面后调用）
 * @return array<int, array<string, mixed>>
 */
function ecod_pages_existing(bool $flush = false): array
{
    static $cache = null;

    if ($flush) {
        $cache = null;
    }

    if ($cache !== null) {
        return $cache;
    }

    $cache = [];

    try {
        $db = \Typecho\Db::get();
        $cache = $db->fetchAll(
            $db->select('cid', 'title', 'slug', 'template', 'status', 'created')
                ->from('table.contents')
                ->where('type = ?', 'page')
                ->where('status = ?', 'publish')
                ->order('cid', \Typecho\Db::SORT_ASC)
        );
    } catch (\Throwable $e) {
        $cache = [];
    }

    return $cache;
}

/**
 * slug 是否已被占用（页面与文章都算，避免地址撞车）
 */
function ecod_pages_slug_taken(string $slug, int $exceptCid = 0): bool
{
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('cid')->from('table.contents')
                ->where('slug = ?', $slug)
                ->where('cid <> ?', $exceptCid)
                ->limit(1)
        );
        return (bool) $row;
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * 生成一个未被占用的 slug
 */
function ecod_pages_unique_slug(string $base): string
{
    $base = trim($base) !== '' ? trim($base) : 'page';
    $slug = $base;
    $i = 2;

    while (ecod_pages_slug_taken($slug)) {
        $slug = $base . '-' . $i;
        $i++;
        if ($i > 99) {
            $slug = $base . '-' . substr((string) time(), -5);
            break;
        }
    }

    return $slug;
}

/**
 * 三个特色页当前状态
 *
 * @return array<string, array<string, mixed>>
 */
function ecod_pages_state(bool $flush = false): array
{
    $rows = ecod_pages_existing($flush);
    $state = [];

    foreach (ecod_pages_blueprint() as $key => $def) {
        $found = null;

        foreach ($rows as $row) {
            if ((string) $row['template'] === $def['template']) {
                $found = $row;
                break;
            }
        }

        if ($found) {
            $state[$key] = array_merge($def, [
                'key'       => $key,
                'exists'    => true,
                'cid'       => (int) $found['cid'],
                'slug'      => (string) $found['slug'],
                'pagetitle' => (string) $found['title'],
                'url'       => ecod_pages_url((string) $found['slug']),
                'edit'      => ecod_pages_edit_url((int) $found['cid']),
            ]);
        } else {
            $state[$key] = array_merge($def, [
                'key'       => $key,
                'exists'    => false,
                'cid'       => 0,
                'slug'      => $def['slug'],
                'pagetitle' => $def['title'],
                'url'       => '',
                'edit'      => '',
            ]);
        }
    }

    return $state;
}

/**
 * 创建指定特色页
 *
 * @return array{ok: bool, message: string, cid?: int, url?: string, key?: string}
 */
function ecod_pages_create(string $key): array
{
    $blueprint = ecod_pages_blueprint();

    if (!isset($blueprint[$key])) {
        return ['ok' => false, 'message' => '未知的页面类型'];
    }

    $def = $blueprint[$key];

    /* 已经建过就不再重复插入 */
    $state = ecod_pages_state();
    if (!empty($state[$key]['exists'])) {
        return [
            'ok'      => true,
            'message' => '「' . $def['title'] . '」已存在，未重复创建',
            'cid'     => (int) $state[$key]['cid'],
            'url'     => (string) $state[$key]['url'],
            'key'     => $key,
            'exists'  => true,
        ];
    }

    $slug = ecod_pages_unique_slug($def['slug']);
    $now = time();

    $row = [
        'title'       => $def['title'],
        'slug'        => $slug,
        'created'     => $now,
        'modified'    => $now,
        'text'        => $def['text'],
        'order'       => 0,
        'authorId'    => 1,
        'template'    => $def['template'],
        'type'        => 'page',
        'status'      => 'publish',
        'password'    => null,
        'commentsNum' => 0,
        'allowComment' => '0',
        'allowPing'   => '0',
        'allowFeed'   => '0',
        'parent'      => 0,
    ];

    /* 用当前登录用户的 uid，避免 authorId 指向不存在的用户 */
    try {
        $user = \Widget\User::alloc();
        if ($user->hasLogin() && (int) $user->uid > 0) {
            $row['authorId'] = (int) $user->uid;
        }
    } catch (\Throwable $e) {
    }

    try {
        $db = \Typecho\Db::get();
        $cid = (int) $db->query($db->insert('table.contents')->rows($row));

        if ($cid <= 0) {
            return ['ok' => false, 'message' => '写入失败，请检查数据库权限'];
        }

        /* 丢掉页面列表缓存，同一请求内再查状态能立刻看到新页 */
        ecod_pages_existing(true);

        return [
            'ok'      => true,
            'message' => '已创建「' . $def['title'] . '」，地址 /' . $slug . '.html',
            'cid'     => $cid,
            'url'     => ecod_pages_url($slug),
            'edit'    => ecod_pages_edit_url($cid),
            'key'     => $key,
            'exists'  => false,
        ];
    } catch (\Throwable $e) {
        return ['ok' => false, 'message' => '创建失败：' . $e->getMessage()];
    }
}
