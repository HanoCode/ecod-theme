<?php
/**
 * Ecod Theme · Credit 积分
 *
 * 只追加的流水账（ecod_credits）：余额 = SUM(delta)。
 * 收入来自任务（签到 / 评论 / 被赞 / 投稿），支出由消费方自行插入负数流水
 * （AI 对话、抽奖），插入即记账，消费方先验余额再扣，失败不落流水。
 *
 * 每日任务上限按「当天该 type 的净额」统计，同一天重复触发不再加分。
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
 * 积分系统是否开启
 */
function ecod_credits_enabled(): bool
{
    return (string) ecod_option('ECrOn', 'off') === 'on';
}

/** 积分名称（展示用，默认 Credit） */
function ecod_credits_name(): string
{
    $name = trim((string) ecod_option('ECrName', ''));
    return $name !== '' ? $name : 'Credit';
}

/**
 * 当前余额（请求内缓存）
 */
function ecod_credits_balance(int $uid): int
{
    if ($uid <= 0) {
        return 0;
    }
    $cache = &$GLOBALS['ecod_credits_bal'];
    if (!is_array($cache)) {
        $cache = [];
    }
    if (isset($cache[$uid])) {
        return $cache[$uid];
    }

    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchObject(
            $db->select('SUM(delta) AS total')->from('table.ecod_credits')->where('uid = ?', $uid)
        );
        return $cache[$uid] = $row && $row->total !== null ? (int) $row->total : 0;
    } catch (\Throwable $e) {
        return $cache[$uid] = 0;
    }
}

/**
 * 记一笔流水（正负皆可），返回 {ok, balance}
 *
 * delta = 0 的流水没有意义，直接拒绝。
 */
function ecod_credits_add(int $uid, int $delta, string $type, string $ref = '', string $note = ''): array
{
    $uid = (int) $uid;
    $delta = (int) $delta;
    if ($uid <= 0 || $delta === 0) {
        return ['ok' => false, 'error' => '参数无效'];
    }

    try {
        $db = \Typecho\Db::get();
        $db->query($db->insert('table.ecod_credits')->rows([
            'uid'     => $uid,
            'delta'   => $delta,
            'type'    => mb_substr(trim($type), 0, 20, 'UTF-8'),
            'ref'     => mb_substr(trim($ref), 0, 64, 'UTF-8'),
            'note'    => mb_substr(trim($note), 0, 180, 'UTF-8'),
            'created' => time(),
        ]));
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => '记账失败'];
    }

    /* 失效请求内余额缓存（同请求连续记账要读到新值） */
    if (isset($GLOBALS['ecod_credits_bal'][$uid])) {
        unset($GLOBALS['ecod_credits_bal'][$uid]);
    }

    return ['ok' => true, 'balance' => ecod_credits_balance($uid)];
}

/**
 * 今天 0 点时间戳
 */
function ecod_credits_day_start(): int
{
    return strtotime('today');
}

/**
 * 某任务今天的累计收入（只统计正数流水）
 */
function ecod_credits_today_amount(int $uid, string $type): int
{
    if ($uid <= 0) {
        return 0;
    }
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchObject(
            $db->select('SUM(delta) AS total')->from('table.ecod_credits')
                ->where('uid = ?', $uid)->where('type = ?', $type)
                ->where('delta > 0')->where('created >= ?', ecod_credits_day_start())
        );
        return $row && $row->total !== null ? (int) $row->total : 0;
    } catch (\Throwable $e) {
        return 0;
    }
}

/**
 * 每日任务发奖（签到 / 评论 / 被赞 / 投稿共用）
 *
 * 配置：ECr{Task} 单次奖励，ECr{Task}Cap 每日上限（0 = 不限）。
 * 已达上限、奖励为 0 或积分未开启时静默跳过。
 *
 * @return array{ok:bool, delta?:int, balance?:int}
 */
/**
 * 每日任务发奖（签到 / 评论 / 被赞 / 投稿共用）
 *
 * 配置键显式映射（like_recv 这类带下划线的任务名不能机械拼接，
 * ECrLike_recv 与设置页的 ECrLikeRecv / ECrLikeCap 对不上）。
 * 已达上限、奖励为 0 或积分未开启时静默跳过。
 *
 * @return array{ok:bool, delta?:int, balance?:int}
 */
function ecod_credits_award(int $uid, string $task, string $ref = '', string $note = ''): array
{
    if (!ecod_credits_enabled() || $uid <= 0) {
        return ['ok' => false];
    }

    $keys = [
        'checkin'   => ['ECrCheckin', ''],
        'comment'   => ['ECrComment', 'ECrCommentCap'],
        'like_recv' => ['ECrLikeRecv', 'ECrLikeCap'],
        'post'      => ['ECrPost', 'ECrPostCap'],
    ];
    if (!isset($keys[$task])) {
        return ['ok' => false];
    }
    [$onceKey, $capKey] = $keys[$task];

    $once = (int) ecod_option($onceKey, 0);
    $cap = $capKey !== '' ? (int) ecod_option($capKey, 0) : 0;
    if ($once <= 0) {
        return ['ok' => false];
    }
    if ($cap > 0 && ecod_credits_today_amount($uid, $task) >= $cap) {
        return ['ok' => false];
    }

    $delta = min($once, $cap > 0 ? max(0, $cap - ecod_credits_today_amount($uid, $task)) : $once);
    if ($delta <= 0) {
        return ['ok' => false];
    }

    return ecod_credits_add($uid, $delta, $task, $ref, $note);
}

/**
 * 每日签到（独立的 type=checkin，一天只允许一条）
 *
 * 连续天数按「含今天的连续 checkin 日」计算：从今天往前逐日对账，
 * 最多回看 90 天，中断即停。
 *
 * @return array{ok:bool, error?:string, delta?:int, balance?:int, streak?:int, signed?:bool}
 */
function ecod_credits_checkin(int $uid): array
{
    if (!ecod_credits_enabled() || $uid <= 0) {
        return ['ok' => false, 'error' => '积分未开启'];
    }

    try {
        $db = \Typecho\Db::get();

        /* 签到去重直接数今天的 checkin 流水 */
        $signed = (bool) $db->fetchRow(
            $db->select('id')->from('table.ecod_credits')
                ->where('uid = ?', $uid)->where('type = ?', 'checkin')
                ->where('created >= ?', ecod_credits_day_start())
                ->limit(1)
        );
        if ($signed) {
            return ['ok' => false, 'error' => '今天已经签到过了', 'signed' => true];
        }

        /* 连续天数：今天记一条后，从今天往回数连续有 checkin 的日子。
           日期在 PHP 侧归一化（FROM_UNIXTIME / date() 各库方言不同，不走 SQL） */
        $rows = $db->fetchAll(
            $db->select('created')->from('table.ecod_credits')
                ->where('uid = ?', $uid)->where('type = ?', 'checkin')
                ->where('created >= ?', ecod_credits_day_start() - 89 * 86400)
        );
        $set = [];
        foreach ($rows ?: [] as $one) {
            $set[date('Y-m-d', (int) $one['created'])] = true;
        }
        $streak = 1;
        for ($i = 1; $i <= 90; $i++) {
            if (isset($set[date('Y-m-d', ecod_credits_day_start() - $i * 86400)])) {
                $streak++;
            } else {
                break;
            }
        }

        $result = ecod_credits_award($uid, 'checkin', 'streak:' . $streak, '连续签到 ' . $streak . ' 天');
        if (empty($result['ok'])) {
            /* 签到奖励配成 0 也算签到成功 */
            return ['ok' => true, 'delta' => 0, 'balance' => ecod_credits_balance($uid), 'streak' => $streak];
        }

        $result['streak'] = $streak;
        return $result;
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => '签到失败，请稍后重试'];
    }
}

/**
 * 今天是否已签到
 */
function ecod_credits_signed_today(int $uid): bool
{
    if ($uid <= 0) {
        return false;
    }
    try {
        $db = \Typecho\Db::get();
        return (bool) $db->fetchRow(
            $db->select('id')->from('table.ecod_credits')
                ->where('uid = ?', $uid)->where('type = ?', 'checkin')
                ->where('created >= ?', ecod_credits_day_start())
                ->limit(1)
        );
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * 流水列表（个人中心 / 积分页）
 *
 * @return array<int, array{delta:int,type:string,note:string,created:int}>
 */
function ecod_credits_log(int $uid, int $limit = 30): array
{
    if ($uid <= 0) {
        return [];
    }
    try {
        $db = \Typecho\Db::get();
        $rows = $db->fetchAll(
            $db->select('delta', 'type', 'note', 'created')->from('table.ecod_credits')
                ->where('uid = ?', $uid)
                ->order('id', \Typecho\Db::SORT_DESC)
                ->limit(max(1, min(100, $limit)))
        );
        $out = [];
        foreach ($rows ?: [] as $row) {
            $out[] = [
                'delta'   => (int) $row['delta'],
                'type'    => (string) $row['type'],
                'note'    => (string) $row['note'],
                'created' => (int) $row['created'],
            ];
        }
        return $out;
    } catch (\Throwable $e) {
        return [];
    }
}

/**
 * 积分流水类型的中文文案
 */
function ecod_credits_type_label(string $type, string $note = ''): string
{
    $map = [
        'checkin'    => '每日签到',
        'comment'    => '发表评论',
        'like_recv'  => '文章被赞',
        'post'       => '发布文章',
        'ai'         => 'AI 对话',
        'lottery'    => '抽奖消耗',
        'lottery_win'=> '抽奖中奖',
        'vip_gift'   => '开通会员赠送',
        'shop'       => '积分兑换',
        'referral'   => '推广返佣',
        'admin'      => '管理员调整',
    ];
    return $map[$type] ?? ($note !== '' ? $note : $type);
}

/**
 * 最新积分动态（侧栏「更新动态」滚动播报的数据源，guaqi 式）
 *
 * 只取收入事件（delta > 0）并关联用户名；开关 ECrTicker 控制是否对外展示。
 *
 * @return array<int, array{uid:int,name:string,type:string,delta:int,created:int}>
 */
function ecod_credits_ticker(int $limit = 10): array
{
    try {
        $db = \Typecho\Db::get();
        $creditsTable = ecod_credits_table();
        $usersTable = $db->getPrefix() . 'users';
        $rows = $db->fetchAll(
            'SELECT c.delta, c.type, c.created, u.uid, u.screenName, u.name'
            . ' FROM ' . $creditsTable . ' c'
            . ' INNER JOIN ' . $usersTable . ' u ON u.uid = c.uid'
            . ' WHERE c.delta > 0'
            . ' ORDER BY c.id DESC'
            . ' LIMIT ' . max(1, min(30, $limit))
        );
        $out = [];
        foreach ($rows ?: [] as $row) {
            $name = (string) ($row['screenName'] ?? '');
            if ($name === '') {
                $name = (string) ($row['name'] ?? '');
            }
            if ($name === '') {
                continue;
            }
            $out[] = [
                'uid'     => (int) $row['uid'],
                'name'    => $name,
                'type'    => (string) $row['type'],
                'delta'   => (int) $row['delta'],
                'created' => (int) $row['created'],
            ];
        }
        return $out;
    } catch (\Throwable $e) {
        return [];
    }
}
