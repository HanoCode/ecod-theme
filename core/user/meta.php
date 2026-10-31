<?php
/**
 * Ecod Theme · 用户扩展资料
 *
 * Typecho 的 users 表字段固定，签名 / 简介这类扩展资料落在主题自己的表：
 *   {prefix}ecod_user_meta（uid + name 唯一）
 *
 * 目前承载：intro（公开作者主页头卡与文章页文末作者卡的一句话简介）。
 * 表结构在 core/lib/schema.php；这里只放读写与「作者卡」数据组装。
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
 * 用户扩展资料表名
 *
 * 同名函数在 core/lib/schema.php 已有一份（那里建表要用，且 functions.php /
 * 各独立入口都会先加载它）。这里必须留 function_exists 守卫：随主题模板加载时
 * 它早已声明，裸声明会直接 "Cannot redeclare function" 致命错误（作者页 /
 * 个人中心 / 文末作者卡都会中招）。
 */
if (!function_exists('ecod_usermeta_table')) {
    function ecod_usermeta_table(): string
    {
        return \Typecho\Db::get()->getPrefix() . 'ecod_user_meta';
    }
}

/**
 * 读取一条用户扩展资料；不存在或出错返回 $default
 */
function ecod_user_meta_get(int $uid, string $name, string $default = ''): string
{
    if ($uid <= 0 || $name === '') {
        return $default;
    }
    try {
        $row = \Typecho\Db::get()->fetchRow(
            \Typecho\Db::get()->select('value')->from(ecod_usermeta_table())
                ->where('uid = ?', $uid)->where('name = ?', $name)->limit(1)
        );
        return $row ? (string) $row['value'] : $default;
    } catch (\Throwable $e) {
        return $default;
    }
}

/**
 * 写入一条用户扩展资料（upsert）；$value 为空串时删除该条
 */
function ecod_user_meta_set(int $uid, string $name, string $value): bool
{
    if ($uid <= 0 || $name === '') {
        return false;
    }
    try {
        $db = \Typecho\Db::get();
        $table = ecod_usermeta_table();

        if ($value === '') {
            $db->query($db->delete($table)->where('uid = ?', $uid)->where('name = ?', $name));
            return true;
        }

        $exists = $db->fetchRow(
            $db->select('id')->from($table)
                ->where('uid = ?', $uid)->where('name = ?', $name)->limit(1)
        );
        if ($exists) {
            $db->query($db->update($table)->rows(['value' => $value])->where('id = ?', (int) $exists['id']));
        } else {
            $db->query($db->insert($table)->rows([
                'uid'     => $uid,
                'name'    => $name,
                'value'   => $value,
                'created' => time(),
            ]));
        }
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * 用户一句话简介
 */
function ecod_user_intro(int $uid): string
{
    return trim(ecod_user_meta_get($uid, 'intro'));
}

/**
 * 用户组中文名
 */
function ecod_user_group_label(string $group): string
{
    $map = [
        'subscriber'   => '订阅者',
        'contributor'  => '投稿者',
        'editor'       => '编辑',
        'administrator' => '管理员',
    ];
    return $map[$group] ?? $group;
}

/**
 * 组装「作者卡」数据：公开作者主页头卡与文章页文末作者卡共用
 *
 * @return array{uid:int,name:string,url:string,group:string,avatar:string,intro:string,posts:int,comments:int,joined:int,mail:string}|null
 */
function ecod_user_card(int $uid): ?array
{
    if ($uid <= 0) {
        return null;
    }
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('uid', 'name', 'screenName', 'url', 'mail', 'group', 'created')
                ->from('table.users')->where('uid = ?', $uid)->limit(1)
        );
        if (!$row) {
            return null;
        }

        /* 头像：自定义头像优先，回落本地头像池（与个人中心同源） */
        require_once __DIR__ . '/account.php';
        $avatar = ecod_account_avatar_url($uid);
        if ($avatar === '') {
            $avatar = ecod_local_avatar_url('uid:' . $uid);
        }

        /* 文章数：仅已发布；评论数：仅已通过且按注册身份（ownerId）归属 */
        $posts = (int) $db->fetchObject(
            $db->select('COUNT(*) AS num')->from('table.contents')
                ->where('authorId = ?', $uid)
                ->where('type = ?', 'post')
                ->where('status = ?', 'publish')
        )->num;
        $comments = (int) $db->fetchObject(
            $db->select('COUNT(*) AS num')->from('table.comments')
                ->where('ownerId = ?', $uid)
                ->where('status = ?', 'approved')
        )->num;

        return [
            'uid'      => $uid,
            'name'     => (string) ($row['screenName'] !== '' ? $row['screenName'] : $row['name']),
            'url'      => (string) $row['url'],
            'group'    => ecod_user_group_label((string) $row['group']),
            'avatar'   => $avatar,
            'intro'    => ecod_user_intro($uid),
            'posts'    => $posts,
            'comments' => $comments,
            'joined'   => (int) $row['created'],
            'mail'     => (string) $row['mail'],
        ];
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * 当前登录用户 uid；未登录返回 null
 */
function ecod_viewer_uid(): ?int
{
    try {
        $user = \Widget\User::alloc();
        return $user->hasLogin() ? (int) $user->uid : null;
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * 作者卡上的关注按钮 HTML
 *
 * 游客：走登录引导（与全站登录入口同一形态，点击弹登录框）；
 * 本人：不输出；其他登录用户：关注 / 已关注切换按钮（JS 接管）。
 */
function ecod_user_follow_btn(int $uid, ?int $viewerUid): string
{
    if ($uid <= 0) {
        return '';
    }
    $esc = static function (string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    };

    /* 游客：登录后关注 */
    if (!$viewerUid) {
        try {
            $loginUrl = ecod_login_url();
        } catch (\Throwable $e) {
            $loginUrl = '';
        }
        if ($loginUrl === '') {
            return '';
        }
        return '<a class="ecod_ucard__follow" data-ecod-login-open data-no-pjax href="'
            . $esc($loginUrl) . '">' . ecod_icon('plus', 12) . '关注</a>';
    }

    if ($viewerUid === $uid) {
        return '';
    }

    require_once __DIR__ . '/follow.php';
    require_once __DIR__ . '/account.php';
    $following = ecod_follow_is($viewerUid, $uid);
    return '<button type="button" class="ecod_ucard__follow' . ($following ? ' is-on' : '')
        . '" data-ecod-follow="' . $uid . '" aria-pressed="' . ($following ? 'true' : 'false') . '"'
        . ' data-ecod-token="' . $esc(ecod_account_token()) . '">'
        . ecod_icon('plus', 12) . '<span>' . ($following ? '已关注' : '关注') . '</span></button>';
}

/**
 * 公开作者主页地址
 *
 * 走 Typecho 的 author 路由（默认 /author/{uid}/，自定义路由同样按名字解析）；
 * 失败时退回 siteUrl 拼接。
 */
function ecod_user_archive_url(int $uid): string
{
    if ($uid <= 0) {
        return '';
    }
    try {
        return (string) \Typecho\Router::url('author', ['uid' => $uid], \Widget\Options::alloc()->index);
    } catch (\Throwable $e) {
        try {
            return rtrim((string) \Widget\Options::alloc()->siteUrl, '/') . '/author/' . $uid . '/';
        } catch (\Throwable $e2) {
            return '';
        }
    }
}

/**
 * 渲染「作者卡」HTML：公开作者主页头卡与文章页文末作者卡共用一套结构
 *
 * $mode = head   归档页大卡（含加入时间）
 * $mode = box    文章页文末紧凑卡
 * $viewerUid     当前登录用户 uid（null = 游客），用于关注按钮状态
 */
function ecod_user_card_html(array $card, string $mode = 'box', ?int $viewerUid = null): string
{
    $esc = static function (string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    };
    $archiveUrl = ecod_user_archive_url((int) $card['uid']);

    /* 粉丝数：表缺失等异常按 0 展示，不打断卡片 */
    require_once __DIR__ . '/follow.php';
    $fans = ecod_follow_fans((int) $card['uid']);

    $html = '<div class="ecod_ucard ecod_ucard--' . $esc($mode) . '">';
    $html .= '<span class="ecod_ucard__avatar" aria-hidden="true">'
        . '<img src="' . $esc((string) $card['avatar']) . '" alt="" width="' . ($mode === 'head' ? 76 : 52)
        . '" height="' . ($mode === 'head' ? 76 : 52) . '" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.remove()">'
        . '</span>';
    $html .= '<div class="ecod_ucard__main">';

    $html .= '<div class="ecod_ucard__name">';
    if ($archiveUrl !== '') {
        $html .= '<a href="' . $esc($archiveUrl) . '">' . $esc((string) $card['name']) . '</a>';
    } else {
        $html .= $esc((string) $card['name']);
    }
    if ((string) $card['group'] !== '') {
        $html .= '<span class="ecod_ucard__group">' . $esc((string) $card['group']) . '</span>';
    }
    $html .= '</div>';

    if ((string) $card['intro'] !== '') {
        $html .= '<p class="ecod_ucard__intro">' . $esc((string) $card['intro']) . '</p>';
    }

    $html .= '<div class="ecod_ucard__stats">'
        . '<span><b>' . (int) $card['posts'] . '</b><em>文章</em></span>'
        . '<span><b>' . (int) $card['comments'] . '</b><em>评论</em></span>'
        . '<span><b data-ecod-fans="' . (int) $card['uid'] . '">' . (int) $fans . '</b><em>粉丝</em></span>';
    if ($mode === 'head' && (int) $card['joined'] > 0) {
        $html .= '<span><b>' . $esc(date('Y-m-d', (int) $card['joined'])) . '</b><em>加入</em></span>';
    }
    $html .= '</div></div>';

    $links = ecod_user_follow_btn((int) $card['uid'], $viewerUid);
    if ($archiveUrl !== '') {
        $links .= '<a class="ecod_ucard__go" href="' . $esc($archiveUrl) . '">'
            . ecod_icon('user', 13) . 'TA 的文章</a>';
    }
    if ((string) $card['url'] !== '' && preg_match('#^https?://#i', (string) $card['url'])) {
        $links .= '<a class="ecod_ucard__go" href="' . $esc((string) $card['url'])
            . '" target="_blank" rel="noopener nofollow">' . ecod_icon('link', 13) . '个人主页</a>';
    }
    if ($links !== '') {
        $html .= '<div class="ecod_ucard__ops">' . $links . '</div>';
    }

    $html .= '</div>';
    return $html;
}
