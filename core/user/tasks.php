<?php
/**
 * Ecod Theme · 任务中心
 *
 * 设计要点（docs/dev/USER-MODULES.md §3）：
 *   1. 进度不落库，每次实时 COUNT——10 项都走 authorId / uid 索引，单次 < 5ms，
 *      没有「同步漂移」这类问题；跨天自然归零，不需要清理任务；
 *   2. 只有「已领取」才有行（ecod_task_claims），唯一键 (uid, task, period, seq)
 *      是幂等闸门：连点 / 并发重复请求时第二条插入必然冲突，不会重复加分；
 *   3. 奖励走积分体系（ecod_credits_add），不新建第二套货币；积分总开关关闭时
 *      整个模块不下发（与「我的积分」tab 同策略）；
 *   4. 多档任务（每日评论 3 次 / 每日点赞 5 次）用 seq 区分档位，与单档共用一条
 *      代码路径，不做特判。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/* 外部上下文（Console 插件）自举：schema 层可能还没加载 */
if (!function_exists('ecod_schema_adapter')) {
    require_once __DIR__ . '/../lib/schema.php';
}

/**
 * Console 插件的任务覆写（option 行 EcodConsoleTasks，JSON）
 *
 * 结构：{key: {reward: int, on: 0|1}}。插件没装 / 行不存在 / JSON 坏了
 * 一律返回空数组回落主题常量表——主题对插件零依赖。
 *
 * @return array<string, array{reward?:int, on?:int}>
 */
function ecod_tasks_overrides(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('value')->from('table.options')->where('name = ?', 'EcodConsoleTasks')->limit(1)
        );
        $data = json_decode((string) ($row['value'] ?? ''), true);
        if (is_array($data)) {
            $cache = $data;
        }
    } catch (\Throwable $e) {
        /* 插件没写过 / options 表不可读：回落默认 */
    }
    return $cache;
}

/**
 * 任务定义表（唯一真源）
 *
 * 加任务 / 调奖励只改这里；`metric` 决定进度算法，与 key 解耦，
 * Console 插件的「任务配置」通过 ecod_tasks_overrides() 覆写奖励与显隐。
 *
 * period: once = 永久一次；daily = 每天 00:00 重置
 *
 * @return array<int, array{key:string,title:string,desc:string,group:string,need:int,reward:int,icon:string,period:string,metric:string}>
 */
function ecod_tasks_map(): array
{
    $map = [
        /* 新手任务：只能完成一次 */
        [
            'key'    => 'signup',
            'title'  => '完成注册',
            'desc'   => '注册即达成，欢迎加入',
            'group'  => 'newbie',
            'need'   => 1,
            'reward' => 20,
            'icon'   => 'user',
            'period' => 'once',
            'metric' => 'signup',
        ],
        [
            'key'    => 'post_first',
            'title'  => '首次投稿',
            'desc'   => '发布第一篇免费或付费文章',
            'group'  => 'newbie',
            'need'   => 1,
            'reward' => 30,
            'icon'   => 'edit',
            'period' => 'once',
            'metric' => 'post_total',
        ],
        [
            'key'    => 'comment_first',
            'title'  => '首次评论',
            'desc'   => '在任意文章下留第一条评论',
            'group'  => 'newbie',
            'need'   => 1,
            'reward' => 10,
            'icon'   => 'comment',
            'period' => 'once',
            'metric' => 'comment_total',
        ],
        [
            'key'    => 'follow_first',
            'title'  => '首次关注',
            'desc'   => '关注一位你喜欢的作者',
            'group'  => 'newbie',
            'need'   => 1,
            'reward' => 5,
            'icon'   => 'plus',
            'period' => 'once',
            'metric' => 'follow_total',
        ],

        /* 日常任务：每天重置 */
        [
            'key'    => 'checkin_daily',
            'title'  => '每日签到',
            'desc'   => '签到可累计连续天数',
            'group'  => 'daily',
            'need'   => 1,
            'reward' => 10,
            'icon'   => 'zap',
            'period' => 'daily',
            'metric' => 'checkin',
        ],
        [
            'key'    => 'post_daily',
            'title'  => '每日投稿',
            'desc'   => '今天发布一篇文章',
            'group'  => 'daily',
            'need'   => 1,
            'reward' => 15,
            'icon'   => 'edit',
            'period' => 'daily',
            'metric' => 'post_today',
        ],
        [
            'key'    => 'comment_daily',
            'title'  => '每日评论',
            'desc'   => '今天评论 3 条，每条可领一次',
            'group'  => 'daily',
            'need'   => 3,
            'reward' => 5,
            'icon'   => 'comment',
            'period' => 'daily',
            'metric' => 'comment_today',
        ],
        [
            'key'    => 'like_daily',
            'title'  => '每日点赞',
            'desc'   => '今天点赞 5 次，每次可领一次',
            'group'  => 'daily',
            'need'   => 5,
            'reward' => 2,
            'icon'   => 'heart',
            'period' => 'daily',
            'metric' => 'like_today',
        ],

        /* 累计任务：不随日期重置 */
        [
            'key'    => 'days_30',
            'title'  => '注册满 30 天',
            'desc'   => '从注册当日起算，自然日',
            'group'  => 'total',
            'need'   => 30,
            'reward' => 100,
            'icon'   => 'clock',
            'period' => 'once',
            'metric' => 'days',
        ],
        [
            'key'    => 'post_10',
            'title'  => '累计投稿 10 篇',
            'desc'   => '已发布文章计入，草稿不算',
            'group'  => 'total',
            'need'   => 10,
            'reward' => 100,
            'icon'   => 'medal',
            'period' => 'once',
            'metric' => 'post_total',
        ],
    ];

    /* Console 插件覆写：关掉的直接不下发，改了奖励的换数 */
    $overrides = ecod_tasks_overrides();
    if (!$overrides) {
        return $map;
    }
    $out = [];
    foreach ($map as $task) {
        $ov = $overrides[$task['key']] ?? null;
        if (is_array($ov)) {
            if (empty($ov['on'])) {
                continue;
            }
            if (isset($ov['reward']) && (int) $ov['reward'] > 0) {
                $task['reward'] = (int) $ov['reward'];
            }
        }
        $out[] = $task;
    }
    return $out;
}

/**
 * 分组元信息（顺序即渲染顺序）
 *
 * @return array<string, array{title:string,desc:string}>
 */
function ecod_tasks_groups(): array
{
    return [
        'newbie' => ['title' => '新手任务', 'desc' => '只能完成一次，完成后永久保留'],
        'daily'  => ['title' => '日常任务', 'desc' => '每天 00:00 重置，进度按当日行为统计'],
        'total'  => ['title' => '累计任务', 'desc' => '不随日期重置，达成即永久保留'],
    ];
}

/**
 * 阈值型任务判定：once 且 need > 1（注册满 30 天 / 累计投稿 10 篇）
 *
 * 语义是「进度达到 need 才能领一次」，与按档型（每日评论 3 次，
 * 每档消耗 1 点进度）完全不同——混用会让 5/30 的天数任务直接可领。
 */
function ecod_tasks_is_threshold(array $task): bool
{
    return ($task['period'] ?? 'once') === 'once' && max(1, (int) $task['need']) > 1;
}

/**
 * 某个任务在当前周期的 period 值
 *
 * 单档永久任务固定 'once'；日常任务用 Ymd，跨天自然换行、无需清理。
 */
function ecod_tasks_period(array $task): string
{
    return ($task['period'] ?? 'once') === 'daily' ? date('Ymd') : 'once';
}

/**
 * 单个 COUNT 查询，出错一律按 0 处理（表缺失 / SQL 方言差异都不该block页面）
 *
 * @param callable $build 接收 Db，返回 Query
 */
function ecod_tasks_count(callable $build): int
{
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow($build($db));
        if (is_array($row)) {
            return (int) ($row['num'] ?? 0);
        }
        return $row && isset($row->num) ? (int) $row->num : 0;
    } catch (\Throwable $e) {
        return 0;
    }
}

/**
 * 注册天数（自然日差，注册当天算第 1 天）
 */
function ecod_tasks_days(int $uid): int
{
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('created')->from('table.users')->where('uid = ?', $uid)->limit(1)
        );
        $created = (int) ($row['created'] ?? 0);
        if ($created <= 0) {
            return 0;
        }
        /* 按自然日算：昨晚 23:59 注册、今早 00:01 就该显示第 2 天 */
        $days = (int) floor((strtotime('today') - strtotime(date('Y-m-d', $created))) / 86400) + 1;
        return max(0, $days);
    } catch (\Throwable $e) {
        return 0;
    }
}

/**
 * 任务进度（当前周期内的行为次数，未截断）
 *
 * @param string $metric 任务定义里的 metric
 */
function ecod_tasks_progress(int $uid, string $metric): int
{
    if ($uid <= 0) {
        return 0;
    }
    $dayStart = ecod_credits_day_start();

    switch ($metric) {
        case 'signup':
            return 1;

        case 'days':
            return ecod_tasks_days($uid);

        case 'post_total':
            return ecod_tasks_count(static function (\Typecho\Db $db) use ($uid) {
                return $db->select('COUNT(*) AS num')->from('table.contents')
                    ->where('authorId = ?', $uid)
                    ->where('type = ?', 'post')
                    ->where('status = ?', 'publish');
            });

        case 'post_today':
            return ecod_tasks_count(static function (\Typecho\Db $db) use ($uid, $dayStart) {
                return $db->select('COUNT(*) AS num')->from('table.contents')
                    ->where('authorId = ?', $uid)
                    ->where('type = ?', 'post')
                    ->where('status = ?', 'publish')
                    ->where('created >= ?', $dayStart);
            });

        case 'comment_total':
            return ecod_tasks_count(static function (\Typecho\Db $db) use ($uid) {
                return $db->select('COUNT(*) AS num')->from('table.comments')
                    ->where('authorId = ?', $uid);
            });

        case 'comment_today':
            return ecod_tasks_count(static function (\Typecho\Db $db) use ($uid, $dayStart) {
                return $db->select('COUNT(*) AS num')->from('table.comments')
                    ->where('authorId = ?', $uid)
                    ->where('created >= ?', $dayStart);
            });

        case 'like_today':
            return ecod_tasks_count(static function (\Typecho\Db $db) use ($uid, $dayStart) {
                return $db->select('COUNT(*) AS num')->from('table.ecod_likes')
                    ->where('uid = ?', $uid)
                    ->where('created >= ?', $dayStart);
            });

        case 'follow_total':
            if (!function_exists('ecod_follows_table')) {
                require_once __DIR__ . '/follow.php';
            }
            try {
                return ecod_follow_count($uid);
            } catch (\Throwable $e) {
                return 0;
            }

        case 'checkin':
            return ecod_credits_signed_today($uid) ? 1 : 0;
    }

    return 0;
}

/**
 * 当前周期已领取次数
 */
function ecod_tasks_claimed(int $uid, string $key, string $period): int
{
    if ($uid <= 0) {
        return 0;
    }
    try {
        $db = \Typecho\Db::get();
        if (!ecod_schema_ensure_task_claims($db)) {
            return 0;
        }
        $row = $db->fetchRow(
            $db->select('COUNT(*) AS num')->from(ecod_task_claims_table())
                ->where('uid = ?', $uid)
                ->where('task = ?', $key)
                ->where('period = ?', $period)
        );
        return (int) ($row['num'] ?? 0);
    } catch (\Throwable $e) {
        return 0;
    }
}

/**
 * 任务面板全量数据（服务端直出与 api task_list 共用）
 *
 * @return array{balance:int, credit:string, streak:int, signed:bool, groups:array, today:array}
 */
function ecod_tasks_list(int $uid): array
{
    $out = [
        'balance' => ecod_credits_balance($uid),
        'credit'  => ecod_credits_name(),
        'streak'  => ecod_credits_streak($uid),
        'signed'  => ecod_credits_signed_today($uid),
        'groups'  => [],
        'today'   => ['claimed' => 0, 'total' => 0],
    ];

    $groups = ecod_tasks_groups();
    $bucket = [];
    foreach (ecod_tasks_map() as $task) {
        $period = ecod_tasks_period($task);
        $need = max(1, (int) $task['need']);
        $raw = ecod_tasks_progress($uid, (string) $task['metric']);
        $progress = min($need, max(0, $raw));
        $claimed = ecod_tasks_claimed($uid, (string) $task['key'], $period);

        if (ecod_tasks_is_threshold($task)) {
            /* 阈值型：整段进度达标才领一次 */
            $state = $claimed >= 1 ? 'claimed' : ($progress >= $need ? 'claimable' : 'doing');
        } elseif ($claimed >= $need) {
            $state = 'claimed';
        } elseif ($progress > $claimed) {
            $state = 'claimable';
        } else {
            $state = 'doing';
        }

        $g = (string) $task['group'];
        if (!isset($bucket[$g])) {
            $bucket[$g] = [
                'key'   => $g,
                'title' => $groups[$g]['title'] ?? $g,
                'desc'  => $groups[$g]['desc'] ?? '',
                'done'  => 0,
                'total' => 0,
                'items' => [],
            ];
        }
        $bucket[$g]['total']++;
        if ($state === 'claimed') {
            $bucket[$g]['done']++;
        }

        /* 今日任务进度条只统计日常组 */
        if ($g === 'daily') {
            $out['today']['total'] += $need;
            $out['today']['claimed'] += min($need, $claimed);
        }

        $bucket[$g]['items'][] = [
            'key'      => (string) $task['key'],
            'title'    => (string) $task['title'],
            'desc'     => (string) $task['desc'],
            'icon'     => (string) $task['icon'],
            'group'    => $g,
            'reward'   => (int) $task['reward'],
            'progress' => $progress,
            'need'     => $need,
            'claimed'  => $claimed,
            'state'    => $state,
            'period'   => $period,
            'daily'    => ($task['period'] ?? 'once') === 'daily',
            'threshold'=> ecod_tasks_is_threshold($task),
        ];
    }

    /* 按定义顺序输出，避免依赖数组插入顺序 */
    foreach (array_keys($groups) as $g) {
        if (isset($bucket[$g])) {
            $out['groups'][] = $bucket[$g];
        }
    }

    return $out;
}

/**
 * 领取任务奖励
 *
 * 顺序：算进度 → 已领次数比对 → 先插领取行（唯一键兜底）→ 再记账。
 * 记账失败回滚领取行，保证「有行必已到账」。
 *
 * @return array{ok:bool, error?:string, delta?:int, balance?:int, key?:string, state?:string, today?:array, progress?:int, claimed?:int}
 */
function ecod_tasks_claim(int $uid, string $key): array
{
    if ($uid <= 0) {
        return ['ok' => false, 'error' => '请先登录'];
    }
    if (!ecod_credits_enabled()) {
        return ['ok' => false, 'error' => '积分未开启'];
    }

    $task = null;
    foreach (ecod_tasks_map() as $one) {
        if ($one['key'] === $key) {
            $task = $one;
            break;
        }
    }
    if (!$task) {
        return ['ok' => false, 'error' => '任务不存在'];
    }

    $need = max(1, (int) $task['need']);
    $reward = (int) $task['reward'];
    if ($reward <= 0) {
        return ['ok' => false, 'error' => '该任务暂未配置奖励'];
    }

    $period = ecod_tasks_period($task);
    $progress = min($need, max(0, ecod_tasks_progress($uid, (string) $task['metric'])));
    $claimed = ecod_tasks_claimed($uid, $key, $period);

    if (ecod_tasks_is_threshold($task)) {
        /* 阈值型：整段达标才可领一次（修掉 5/30 也能领的口径漏洞） */
        if ($claimed >= 1) {
            return ['ok' => false, 'error' => '已领取', 'state' => 'claimed'];
        }
        if ($progress < $need) {
            return [
                'ok'       => false,
                'error'    => '还没达标，还差 ' . ($need - $progress) . '（当前 ' . $progress . '/' . $need . '）',
                'state'    => 'doing',
                'progress' => $progress,
                'claimed'  => $claimed,
            ];
        }
    } elseif ($claimed >= $need) {
        return ['ok' => false, 'error' => '已领取', 'state' => 'claimed'];
    } elseif ($progress <= $claimed) {
        return ['ok' => false, 'error' => '还没达标', 'state' => 'doing', 'progress' => $progress, 'claimed' => $claimed];
    }

    try {
        $db = \Typecho\Db::get();
        if (!ecod_schema_ensure_task_claims($db)) {
            return ['ok' => false, 'error' => '任务表不可用，请稍后重试'];
        }
        /* 先占位：并发重复请求会在这里撞唯一键 */
        $db->query($db->insert(ecod_task_claims_table())->rows([
            'uid'     => $uid,
            'task'    => $key,
            'period'  => $period,
            'seq'     => $claimed + 1,
            'delta'   => $reward,
            'created' => time(),
        ]));
    } catch (\Throwable $e) {
        /* 唯一键冲突 = 刚好被别人抢先领了，按已领取返回，不加分 */
        return ['ok' => false, 'error' => '已领取', 'state' => 'claimed'];
    }

    $added = ecod_credits_add($uid, $reward, 'task', $key, '任务奖励 · ' . (string) $task['title']);
    if (empty($added['ok'])) {
        /* 记账失败：删掉刚插的占位行，让用户下次还能领 */
        try {
            $db = \Typecho\Db::get();
            $db->query(
                $db->delete(ecod_task_claims_table())
                    ->where('uid = ?', $uid)
                    ->where('task = ?', $key)
                    ->where('period = ?', $period)
                    ->where('seq = ?', $claimed + 1)
            );
        } catch (\Throwable $e) {
        }
        return ['ok' => false, 'error' => '记账失败，请稍后重试'];
    }

    $after = ecod_tasks_claimed($uid, $key, $period);
    $state = $after >= $need ? 'claimed' : ($progress > $after ? 'claimable' : 'doing');

    return [
        'ok'       => true,
        'key'      => $key,
        'delta'    => $reward,
        'balance'  => (int) ($added['balance'] ?? ecod_credits_balance($uid)),
        'progress' => $progress,
        'claimed'  => $after,
        'state'    => $state,
        'today'    => ecod_tasks_today_stat($uid),
    ];
}

/**
 * 今日任务进度（汇总条用）
 *
 * @return array{claimed:int, total:int}
 */
function ecod_tasks_today_stat(int $uid): array
{
    $claimed = 0;
    $total = 0;
    foreach (ecod_tasks_map() as $task) {
        if (($task['period'] ?? 'once') !== 'daily') {
            continue;
        }
        $need = max(1, (int) $task['need']);
        $total += $need;
        $claimed += min($need, ecod_tasks_claimed($uid, (string) $task['key'], ecod_tasks_period($task)));
    }
    return ['claimed' => $claimed, 'total' => $total];
}

/**
 * 从已算好的任务数据里数「现在就能领」的条目（侧栏角标用，避免重复查询）
 */
function ecod_tasks_ready(array $list): int
{
    $n = 0;
    foreach (($list['groups'] ?? []) as $group) {
        foreach (($group['items'] ?? []) as $item) {
            if (($item['state'] ?? '') === 'claimable') {
                $n++;
            }
        }
    }
    return $n;
}
