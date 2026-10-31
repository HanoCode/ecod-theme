<?php
/**
 * 评论防刷：纯 IP 频率 / 图形验证码 / 首评必审
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 *
 * 为什么不用 Typecho 自带的 commentsPostInterval：它的判定条件是
 * `cid = ? AND ip = ?`，换一篇文章就是新桶，脚本顺着文章列表每篇发一条即可绕过。
 * 这里补三件事：
 *
 * 1. 纯 IP 频率：全站 N 秒一条（不分文章）+ 每小时 / 每天上限；
 * 2. 图形验证码：复用 core/user/account.php 的 ecod_captcha_*（session 一次性消费）；
 * 3. 首评必审：这个邮箱（没填邮箱时按 IP）没有已通过的评论 → 先审后发。
 *
 * 计数写进 ecod_throttle 表（thkey / n / last），键按窗口切分
 * （gap / hour|窗口序号 / day|窗口序号），窗口内只增不减；读取时不走
 * ecod_throttle_persistent_state()，那张表的「每 5 分钟返还一次」是给
 * 验证码试错用的，用来做频率账会把上限慢慢放空。
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 读主题设置（带默认值）
 */
function ecod_cmt_opt(string $key, string $default = ''): string
{
    $val = ecod_option($key, $default);
    return is_scalar($val) ? (string) $val : $default;
}

/**
 * 来源 IP：只用 REMOTE_ADDR，X-Forwarded-For 客户端可以随便填
 */
function ecod_cmt_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

/**
 * 这个请求要不要过图形验证码（登录用户直接放行，与评论过滤器一致）
 */
function ecod_cmt_captcha_on(): bool
{
    if (ecod_cmt_opt('ECommentCaptcha', 'on') !== 'on') {
        return false;
    }

    try {
        if (\Widget\User::alloc()->hasLogin()) {
            return false;
        }
    } catch (\Throwable $e) {
        /* 取不到登录态就按访客处理，宁可多验一次 */
    }

    return true;
}

/* ------------------------------------------------------------------
 * 频率账本
 * ---------------------------------------------------------------- */

/**
 * 账本键：动作 + 来源 IP（+ 窗口序号），sha256 后正好 64 位，贴合 thkey CHAR(64)
 */
function ecod_cmt_key(string $kind, int $window = 0): string
{
    return hash('sha256', 'cmt:' . $kind . '|' . ecod_cmt_ip() . ($window > 0 ? '|' . $window : ''));
}

/**
 * 读原始计数（不衰减）；表不可用返回 null，调用方按「无记录」处理
 *
 * @return array{n:int,last:int}|null
 */
function ecod_cmt_row(string $key): ?array
{
    if (!ecod_throttle_persistent_ready()) {
        return null;
    }

    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('n', 'last')->from(ecod_throttle_table())->where('thkey = ?', $key)->limit(1)
        );
        return $row ? ['n' => (int) $row['n'], 'last' => (int) $row['last']] : null;
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * 写计数（存在则更新，不存在则插入）
 */
function ecod_cmt_put(string $key, int $n, int $last): void
{
    if (!ecod_throttle_persistent_ready()) {
        return;
    }

    try {
        $db = \Typecho\Db::get();
        $table = ecod_throttle_table();
        $exists = $db->fetchRow($db->select('thkey')->from($table)->where('thkey = ?', $key)->limit(1));
        if ($exists) {
            $db->query($db->update($table)->rows(['n' => $n, 'last' => $last])->where('thkey = ?', $key));
        } else {
            $db->query($db->insert($table)->rows(['thkey' => $key, 'n' => $n, 'last' => $last]));
        }
    } catch (\Throwable $e) {
        /* 落库失败就少一层频率保护，不该影响正常评论 */
    }
}

/**
 * 随手清理两天前的行（只承担体积，不影响判定）
 */
function ecod_cmt_prune(): void
{
    if (!ecod_throttle_persistent_ready()) {
        return;
    }

    try {
        $db = \Typecho\Db::get();
        $db->query($db->delete(ecod_throttle_table())->where('last < ?', time() - 172800));
    } catch (\Throwable $e) {
        /* 清理失败无所谓 */
    }
}

/**
 * 提交前的频率检查：返回还需要等待的秒数，0 表示放行
 *
 * 三条线：两条评论之间的间隔、每小时条数、每天条数。
 */
function ecod_comment_rate_wait(): int
{
    $now = time();
    $wait = 0;

    $gap = max(0, (int) ecod_cmt_opt('ECmtGap', '60'));
    if ($gap > 0) {
        $row = ecod_cmt_row(ecod_cmt_key('gap'));
        if ($row && $row['last'] > 0) {
            $left = $gap - ($now - $row['last']);
            if ($left > 0) {
                $wait = max($wait, $left);
            }
        }
    }

    $hour = max(0, (int) ecod_cmt_opt('ECmtHour', '10'));
    if ($hour > 0) {
        $row = ecod_cmt_row(ecod_cmt_key('hour', intdiv($now, 3600)));
        if ($row && $row['n'] >= $hour) {
            $wait = max($wait, 3600 - ($now % 3600));
        }
    }

    $day = max(0, (int) ecod_cmt_opt('ECmtDay', '30'));
    if ($day > 0) {
        $row = ecod_cmt_row(ecod_cmt_key('day', intdiv($now, 86400)));
        if ($row && $row['n'] >= $day) {
            $wait = max($wait, 86400 - ($now % 86400));
        }
    }

    return $wait;
}

/**
 * 记一条评论：间隔账写时间戳，小时 / 天窗各加一
 */
function ecod_comment_rate_hit(): void
{
    $now = time();

    ecod_cmt_put(ecod_cmt_key('gap'), 1, $now);

    if (max(0, (int) ecod_cmt_opt('ECmtHour', '10')) > 0) {
        $key = ecod_cmt_key('hour', intdiv($now, 3600));
        $row = ecod_cmt_row($key);
        ecod_cmt_put($key, ($row ? $row['n'] : 0) + 1, $now);
    }

    if (max(0, (int) ecod_cmt_opt('ECmtDay', '30')) > 0) {
        $key = ecod_cmt_key('day', intdiv($now, 86400));
        $row = ecod_cmt_row($key);
        ecod_cmt_put($key, ($row ? $row['n'] : 0) + 1, $now);
    }

    /* 约 2% 的请求带一次清理，避免每次提交都多一条 DELETE */
    try {
        if (random_int(1, 50) === 1) {
            ecod_cmt_prune();
        }
    } catch (\Throwable $e) {
        /* 无随机源就跳过 */
    }
}

/**
 * 等待时长的中文表述
 */
function ecod_comment_rate_error(int $wait): string
{
    if ($wait >= 3600) {
        return '评论太频繁了，请 ' . (int) ceil($wait / 3600) . ' 小时后再来。';
    }
    if ($wait >= 60) {
        return '评论太频繁了，请 ' . (int) ceil($wait / 60) . ' 分钟后再来。';
    }
    return '评论太频繁了，请 ' . max(1, $wait) . ' 秒后再试。';
}

/* ------------------------------------------------------------------
 * 首评必审
 * ---------------------------------------------------------------- */

/**
 * 是否首次评论：这个邮箱没有一条已通过的评论
 *
 * 有邮箱按邮箱认人（同一个人换昵称不必重审），没填邮箱退回按 IP 认人。
 * 查询失败时按老访客处理——网络抖动不该把所有人拦进审核队列。
 *
 * @param array $comment 待写入的评论行
 */
function ecod_comment_is_first(array $comment, string $ip): bool
{
    $mail = strtolower(trim((string) ($comment['mail'] ?? '')));

    try {
        $db = \Typecho\Db::get();
        $query = $db->select('coid')->from('table.comments')
            ->where('status = ?', 'approved')
            ->where('type = ?', 'comment')
            ->limit(1);

        if ($mail !== '') {
            $query->where('LOWER(mail) = ?', $mail);
        } else {
            $query->where('ip = ?', $ip);
        }

        return !$db->fetchRow($query);
    } catch (\Throwable $e) {
        return false;
    }
}
