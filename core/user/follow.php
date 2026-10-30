<?php
/**
 * Ecod Theme · 关注作者
 *
 * 轻量关注体系：登录用户关注 / 取关作者，作者卡显示粉丝数与关注按钮，
 * 个人中心「我的关注」管理列表。私信不在本期范围（无通知基建，刻意搁置）。
 *
 * 表：{prefix}ecod_follows（uid + target 唯一，结构见 core/lib/schema.php）。
 * 计数口径：粉丝数 = target 指向该作者的行数；关注数 = uid 发出的行数。
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
 * 执行依赖关注表的查询：表不存在时补建一次再重试
 */
function ecod_follow_with_table(\Typecho\Db $db, callable $fn)
{
    try {
        return $fn(ecod_follows_table());
    } catch (\Throwable $e) {
        if (!ecod_schema_ensure_follows($db)) {
            throw $e;
        }
        return $fn(ecod_follows_table());
    }
}

/**
 * 粉丝数：多少人在关注这位作者
 */
function ecod_follow_fans(int $uid): int
{
    if ($uid <= 0) {
        return 0;
    }
    try {
        $row = \Typecho\Db::get()->fetchObject(
            \Typecho\Db::get()->select('COUNT(*) AS num')->from(ecod_follows_table())
                ->where('target = ?', $uid)
        );
        return $row ? (int) $row->num : 0;
    } catch (\Throwable $e) {
        return 0;
    }
}

/**
 * 关注数：这位作者发出了多少个关注
 */
function ecod_follow_count(int $uid): int
{
    if ($uid <= 0) {
        return 0;
    }
    try {
        $row = \Typecho\Db::get()->fetchObject(
            \Typecho\Db::get()->select('COUNT(*) AS num')->from(ecod_follows_table())
                ->where('uid = ?', $uid)
        );
        return $row ? (int) $row->num : 0;
    } catch (\Throwable $e) {
        return 0;
    }
}

/**
 * 当前是否已关注（$uid 关注了 $target）
 */
function ecod_follow_is(int $uid, int $target): bool
{
    if ($uid <= 0 || $target <= 0 || $uid === $target) {
        return false;
    }
    try {
        return (bool) \Typecho\Db::get()->fetchRow(
            \Typecho\Db::get()->select('id')->from(ecod_follows_table())
                ->where('uid = ?', $uid)->where('target = ?', $target)->limit(1)
        );
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * 关注 / 取关（幂等切换），返回操作后的状态与最新粉丝数
 *
 * @return array{following:bool,fans:int}|false  false = 存储失败
 */
function ecod_follow_toggle(int $uid, int $target): array
{
    if ($uid <= 0 || $target <= 0 || $uid === $target) {
        return ['following' => false, 'fans' => ecod_follow_fans($target)];
    }
    $db = \Typecho\Db::get();

    return ecod_follow_with_table($db, function (string $table) use ($db, $uid, $target) {
        $exists = (bool) $db->fetchRow(
            $db->select('id')->from($table)
                ->where('uid = ?', $uid)->where('target = ?', $target)->limit(1)
        );
        if ($exists) {
            $db->query($db->delete($table)->where('uid = ?', $uid)->where('target = ?', $target));
        } else {
            try {
                $db->query($db->insert($table)->rows([
                    'uid'     => $uid,
                    'target'  => $target,
                    'created' => time(),
                ]));
            } catch (\Throwable $e) {
                /* 唯一索引冲突 = 并发重复关注，按已关注处理 */
            }
        }

        $fans = (int) $db->fetchObject(
            $db->select('COUNT(*) AS num')->from($table)->where('target = ?', $target)
        )->num;

        return ['following' => !$exists, 'fans' => $fans];
    });
}

/**
 * 关注 / 粉丝列表（带头像与昵称，个人中心用）
 *
 * @param string $type following=我关注的 | followers=关注我的
 * @return array<int, array{uid:int,name:string,avatar:string,posts:int,created:int}>
 */
function ecod_follow_rows(int $uid, string $type, int $limit = 50): array
{
    if ($uid <= 0) {
        return [];
    }
    $db = \Typecho\Db::get();
    $users = $db->getPrefix() . 'users';

    try {
        $rows = ecod_follow_with_table($db, function (string $table) use ($db, $uid, $type, $limit, $users) {
            $joinCol = $type === 'followers' ? 'f.uid' : 'f.target';
            /* 我关注的：target 是对方；关注我的：uid 是对方 */
            $other = $type === 'followers' ? 'f.uid' : 'f.target';
            return $db->fetchAll(
                'SELECT u.uid, u.name, u.screenName, f.created AS followed_at'
                . ' FROM ' . $table . ' f'
                . ' INNER JOIN ' . $users . ' u ON u.uid = ' . $other
                . ' WHERE ' . $joinCol . ' = ' . (int) $uid
                . ' ORDER BY f.created DESC LIMIT ' . max(1, min(200, (int) $limit))
            );
        });
    } catch (\Throwable $e) {
        return [];
    }

    require_once __DIR__ . '/account.php';

    $out = [];
    foreach ((array) $rows as $row) {
        $rowUid = (int) $row['uid'];
        $custom = ecod_account_avatar_url($rowUid);
        if ($custom === '') {
            /* 本地头像池定义在 functions.php，仅主题上下文可用；api 上下文回落空串（前端按首字渲染） */
            $custom = function_exists('ecod_local_avatar_url') ? ecod_local_avatar_url('uid:' . $rowUid) : '';
        }
        $out[] = [
            'uid'     => $rowUid,
            'name'    => (string) ($row['screenName'] !== '' ? $row['screenName'] : $row['name']),
            'avatar'  => $custom,
            'created' => (int) $row['followed_at'],
        ];
    }
    return $out;
}
