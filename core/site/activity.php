<?php
/**
 * Ecod Theme · 站点实时动态（侧栏「实时动态」挂件数据层）
 *
 * 把多个公开行为流聚合成统一条目（头像 + 名字 + 动作文案 + 时间）：
 *   credit 最新积分事件   task 最新任务奖励   comment 最新评论
 *   post   最新文章       user   新成员加入
 *
 * 条目只含公开信息；积分与任务两源沿用「动态播报」隐私开关 ECrTicker 的
 * 口径（事件涉及用户名与收益），开关关闭时整组跳过，不做部分脱敏。
 *
 * 前台模板（components/aside/ticker.php）与 api.php?action=ticker 共用本层；
 * 聚合查询走 60s 文件缓存，api 场景不含 functions.php，头像 / 文案在
 * 本文件内自足（口径与 functions.php 同源，见各 helper 注释）。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

if (!function_exists('ecod_credits_public_rows')) {
    require_once dirname(__DIR__) . '/user/credits.php';
}
if (!function_exists('ecod_tasks_map')) {
    require_once dirname(__DIR__) . '/user/tasks.php';
}
if (!function_exists('ecod_account_avatar_url')) {
    require_once dirname(__DIR__) . '/user/account.php';
}
if (!function_exists('ecod_user_archive_url')) {
    require_once dirname(__DIR__) . '/user/meta.php';
}

/**
 * 可选内容来源清单（与后台 checks 参数的 options 保持同序）
 *
 * @return array<string,string>
 */
function ecod_activity_sources(): array
{
    return [
        'comment' => '最新评论',
        'credit'  => '积分事件',
        'task'    => '任务奖励',
        'post'    => '新文章',
        'user'    => '新成员',
    ];
}

/**
 * 积分类来源（credit / task）是否允许对外展示
 *
 * 沿用侧栏播报的隐私口径：积分开启且「动态播报」打开才可见。
 */
function ecod_activity_credits_public(): bool
{
    return ecod_credits_enabled()
        && (string) ecod_option('ECrTicker', 'off') === 'on';
}

/**
 * 聚合实时动态
 *
 * @param array<int,string> $sources 来源 key 列表（白名单外的自动剔除）
 * @param int               $limit   返回条数（1–15）
 * @return array<int, array{kind:string,uid:int,name:string,avatar:string,text:string,delta:int,href:string,created:int,ago:string}>
 */
function ecod_activity_feed(array $sources, int $limit = 8): array
{
    $limit = max(1, min(15, $limit));
    $all = array_keys(ecod_activity_sources());
    $sources = array_values(array_intersect($all, array_map('strval', $sources)));
    if (!$sources) {
        return [];
    }

    $run = static function () use ($sources, $limit): array {
        $items = [];
        foreach ($sources as $source) {
            foreach (ecod_activity_collect($source, $limit, $sources) as $row) {
                $items[] = $row;
            }
        }
        if (!$items) {
            return [];
        }
        usort($items, static function (array $a, array $b): int {
            return $b['created'] <=> $a['created'];
        });
        /* 同人同文案只保留最新一条：连签 / 连领的重复播报没有信息量 */
        $seen = [];
        $out = [];
        foreach ($items as $item) {
            $sig = $item['kind'] . '|' . $item['uid'] . '|' . $item['text'];
            if (isset($seen[$sig])) {
                continue;
            }
            $seen[$sig] = true;
            $out[] = $item;
        }
        return array_slice($out, 0, $limit);
    };

    /* 多源合并属贵查询：60s 文件缓存（与搜索建议等公开数据同 TTL 量级） */
    if (function_exists('ecod_cache_remember')) {
        return (array) ecod_cache_remember(
            'ticker_' . md5(implode(',', $sources) . '.' . $limit),
            60,
            $run
        );
    }
    return $run();
}

/**
 * 单源采集（内部）
 *
 * @param array<int,string> $allSources 本次聚合启用的全部来源，
 *                                  用于跨源去重（task 源在选时 credit 源排除 type=task）
 * @return array<int, array{kind:string,uid:int,name:string,avatar:string,text:string,delta:int,href:string,created:int,ago:string}>
 */
function ecod_activity_collect(string $source, int $limit, array $allSources = []): array
{
    try {
        $rows = [];
        switch ($source) {
            case 'credit':
                if (!ecod_activity_credits_public()) {
                    return [];
                }
                /* ecod_credits_public_rows 已带用户名并排除 admin 调账；note 兜底
                   自定义流水类型的文案（如任务奖励的「任务奖励 · 任务名」） */
                $exclude = ['admin'];
                if (in_array('task', $allSources, true)) {
                    $exclude[] = 'task';
                }
                foreach (ecod_credits_public_rows($limit, '', $exclude) as $row) {
                    $rows[] = ecod_activity_item(
                        'credit',
                        (int) $row['uid'],
                        (string) $row['name'],
                        ecod_credits_type_label((string) $row['type'], (string) $row['note']) . '，获得 ' . ecod_credits_name(),
                        (int) $row['delta'],
                        ecod_user_archive_url((int) $row['uid']),
                        (int) $row['created']
                    );
                }
                break;

            case 'task':
                if (!ecod_activity_credits_public()) {
                    return [];
                }
                $titles = [];
                foreach (ecod_tasks_map() as $task) {
                    $titles[(string) $task['key']] = (string) $task['title'];
                }
                $db = \Typecho\Db::get();
                $claims = $db->fetchAll(
                    $db->select('uid', 'task', 'delta', 'created')->from('table.ecod_task_claims')
                        ->order('id', \Typecho\Db::SORT_DESC)->limit($limit)
                );
                $names = ecod_activity_names(array_map('intval', array_column($claims, 'uid')));
                foreach ($claims ?: [] as $row) {
                    $uid = (int) $row['uid'];
                    if (!isset($names[$uid])) {
                        continue;
                    }
                    $title = $titles[(string) $row['task']] ?? (string) $row['task'];
                    $rows[] = ecod_activity_item(
                        'task',
                        $uid,
                        $names[$uid],
                        '完成「' . $title . '」，获得 ' . ecod_credits_name(),
                        (int) $row['delta'],
                        ecod_user_archive_url($uid),
                        (int) $row['created']
                    );
                }
                break;

            case 'comment':
                $db = \Typecho\Db::get();
                $comments = $db->fetchAll(
                    $db->select('coid', 'cid', 'author', 'mail', 'ownerId', 'created')
                        ->from('table.comments')
                        ->where('type = ?', 'comment')
                        ->where('status = ?', 'approved')
                        ->order('coid', \Typecho\Db::SORT_DESC)->limit($limit)
                );
                if (!$comments) {
                    break;
                }
                /* 标题与 permalink 两步查（与积分动态同套路，join 别名不可靠） */
                $cids = array_values(array_unique(array_map('intval', array_column($comments, 'cid'))));
                $posts = [];
                foreach ($db->fetchAll(
                    $db->select('cid', 'title', 'slug', 'type', 'created')->from('table.contents')
                        ->where('cid IN ?', $cids)
                ) as $post) {
                    $posts[(int) $post['cid']] = $post;
                }
                foreach ($comments as $row) {
                    $cid = (int) $row['cid'];
                    $post = $posts[$cid] ?? null;
                    if (!$post || (string) $post['type'] !== 'post') {
                        continue;   /* 被删内容 / 独立页评论不进动态 */
                    }
                    $link = ecod_activity_link('post', $post);
                    if ($link === '') {
                        continue;
                    }
                    $name = trim((string) $row['author']);
                    if ($name === '') {
                        continue;
                    }
                    $uid = (int) $row['ownerId'];
                    $title = mb_substr(trim((string) $post['title']), 0, 18, 'UTF-8');
                    $rows[] = ecod_activity_item(
                        'comment',
                        $uid,
                        $name,
                        '评论了「' . $title . '」',
                        0,
                        $link . '#comment-' . (int) $row['coid'],
                        (int) $row['created'],
                        $uid > 0 ? ecod_activity_avatar($uid) : ecod_activity_local_avatar((string) $row['mail'])
                    );
                }
                break;

            case 'post':
                $db = \Typecho\Db::get();
                $posts = $db->fetchAll(
                    $db->select('cid', 'title', 'slug', 'type', 'created', 'authorId')
                        ->from('table.contents')
                        ->where('type = ?', 'post')
                        ->where('status = ?', 'publish')
                        ->where('created < ?', time())
                        ->order('created', \Typecho\Db::SORT_DESC)->limit($limit)
                );
                $names = ecod_activity_names(array_map('intval', array_column($posts, 'authorId')));
                foreach ($posts ?: [] as $row) {
                    $uid = (int) $row['authorId'];
                    $link = ecod_activity_link('post', $row);
                    if (!isset($names[$uid]) || $link === '') {
                        continue;
                    }
                    $title = mb_substr(trim((string) $row['title']), 0, 18, 'UTF-8');
                    $rows[] = ecod_activity_item(
                        'post',
                        $uid,
                        $names[$uid],
                        '发布了新文章「' . $title . '」',
                        0,
                        $link,
                        (int) $row['created']
                    );
                }
                break;

            case 'user':
                $db = \Typecho\Db::get();
                $users = $db->fetchAll(
                    $db->select('uid', 'screenName', 'name', 'created')->from('table.users')
                        ->where('created > ?', 0)
                        ->order('created', \Typecho\Db::SORT_DESC)->limit($limit)
                );
                foreach ($users ?: [] as $row) {
                    $uid = (int) $row['uid'];
                    $name = trim((string) ($row['screenName'] ?: $row['name']));
                    if ($name === '') {
                        continue;
                    }
                    $rows[] = ecod_activity_item(
                        'user',
                        $uid,
                        $name,
                        '加入了站点',
                        0,
                        ecod_user_archive_url($uid),
                        (int) $row['created']
                    );
                }
                break;
        }
        return $rows;
    } catch (\Throwable $e) {
        return [];
    }
}

/**
 * 统一条目结构（内部）
 */
function ecod_activity_item(
    string $kind,
    int $uid,
    string $name,
    string $text,
    int $delta,
    string $href,
    int $created,
    string $avatar = ''
): array {
    return [
        'kind'    => $kind,
        'uid'     => $uid,
        'name'    => $name,
        /* 头像缺省时由模板兜底首字占位，不在数据层猜 */
        'avatar'  => $avatar !== '' ? $avatar : ecod_activity_avatar($uid),
        'text'    => $text,
        'delta'   => $delta,
        'href'    => $href,
        'created' => $created,
        'ago'     => ecod_activity_ago($created),
    ];
}

/**
 * uid → 昵称映射（两步查询，credits.php 同口径）
 *
 * @param array<int,int> $uids
 * @return array<int,string>
 */
function ecod_activity_names(array $uids): array
{
    $uids = array_values(array_unique(array_filter(array_map('intval', $uids))));
    if (!$uids) {
        return [];
    }
    $names = [];
    foreach (
        \Typecho\Db::get()->fetchAll(
            \Typecho\Db::get()->select('uid', 'screenName', 'name')->from('table.users')
                ->where('uid IN ?', $uids)
        ) as $user
    ) {
        $name = trim((string) ($user['screenName'] ?: $user['name']));
        if ($name !== '') {
            $names[(int) $user['uid']] = $name;
        }
    }
    return $names;
}

/**
 * uid → 头像：自定义头像优先，回落本地头像池（meta.php 同口径）
 */
function ecod_activity_avatar(int $uid): string
{
    if ($uid <= 0) {
        return '';
    }
    $avatar = ecod_account_avatar_url($uid);
    return $avatar !== '' ? $avatar : ecod_activity_local_avatar('uid:' . $uid);
}

/**
 * 本地头像池确定性选图（与 functions.php ecod_local_avatar_url 同算法；
 * api.php 场景不含 functions.php，这里零依赖复刻）
 */
function ecod_activity_local_avatar(string $key): string
{
    if (function_exists('ecod_local_avatar_url')) {
        return (string) ecod_local_avatar_url($key);
    }
    static $pool = null;
    if ($pool === null) {
        /* __DIR__ = core/site，头像池在主题根 assets/avatar */
        $pool = glob(__DIR__ . '/../../assets/avatar/*.webp');
        if (!is_array($pool) || !$pool) {
            $pool = [];
        } else {
            sort($pool);
        }
    }
    if (!$pool) {
        return '';
    }
    $file = $pool[abs(crc32($key)) % count($pool)];
    /* 主题根距站点根去掉前缀（api.php 场景无 $this->themeUrl，与点赞者头像同口径） */
    $themeDir = dirname(dirname(__DIR__));
    $rel = str_replace(rtrim(__TYPECHO_ROOT_DIR__, '/'), '', $themeDir) . '/assets/avatar/' . basename($file);
    return $rel . '?v=' . (string) filemtime($file);
}

/**
 * 路由表还原 permalink（seo.php ecod_sitemap_link 同口径，自带一次 setRoutes）
 *
 * api.php 独立入口不经过 Widget\Init 引导，Router 路由表未注册；
 * 前台重复 setRoutes 是幂等的，首调用补一次即可。
 */
function ecod_activity_link(string $route, array $row): string
{
    static $routed = false;
    if (!$routed) {
        $routed = true;
        try {
            \Typecho\Router::setRoutes(\Widget\Options::alloc()->routingTable);
        } catch (\Throwable $e) {
        }
    }
    try {
        $link = (string) \Typecho\Router::url($route, $row, \Widget\Options::alloc()->index);
    } catch (\Throwable $e) {
        return '';
    }
    if ($link === '' || strpos($link, '{') !== false) {
        return '';
    }
    return $link;
}

/**
 * 相对时间（functions.php ecod_relative_time 的紧凑版，去掉 intl 依赖）
 */
function ecod_activity_ago(int $timestamp): string
{
    $diff = time() - $timestamp;
    if ($diff < 60) {
        return '刚刚';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' 分钟前';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' 小时前';
    }
    if ($diff < 86400 * 30) {
        return floor($diff / 86400) . ' 天前';
    }
    return date('m-d', $timestamp);
}
