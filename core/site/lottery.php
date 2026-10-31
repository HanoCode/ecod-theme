<?php
/**
 * Ecod Theme · 站点抽奖
 *
 * 奖品池在主题设置里维护（文本协议，一行一个奖品）：
 *   名称|权重|库存(0=不限)|类型|值
 *   谢谢参与|60|0|none|
 *   10 积分|25|0|credit|10
 *   会员 7 天|5|10|vip|1:7
 *   定制周边|2|3|text|（中奖后联系站长兑奖）
 *
 * 库存不建表：剩余量 = 配置库存 − 本表按 prize_key 的中奖行数，
 * 中奖行只在抽取成功后写入，博客量级下先查后写的窗口竞态可忽略。
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
 * 抽奖是否开启（开关 + 至少配置一个奖品）
 */
function ecod_lottery_enabled(): bool
{
    if ((string) ecod_option('ELottery', 'off') !== 'on') {
        return false;
    }
    return ecod_lottery_prizes() !== [];
}

/**
 * 奖品池解析（请求内静态缓存）
 *
 * @return array<int, array{key:string,name:string,weight:int,stock:int,type:string,value:string}>
 */
function ecod_lottery_prizes(): array
{
    static $prizes = null;
    if ($prizes !== null) {
        return $prizes;
    }

    $prizes = [];
    $raw = (string) ecod_option('ELotteryPrizes', '');
    if (trim($raw) === '') {
        return $prizes;
    }

    foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $index => $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $parts = array_map('trim', explode('|', $line));
        $name = mb_substr((string) ($parts[0] ?? ''), 0, 60, 'UTF-8');
        if ($name === '') {
            continue;
        }
        $weight = max(0, (int) ($parts[1] ?? 1));
        $stock = max(0, (int) ($parts[2] ?? 0));
        $type = strtolower((string) ($parts[3] ?? 'none'));
        if (!in_array($type, ['none', 'credit', 'vip', 'text'], true)) {
            $type = 'none';
        }
        $prizes[] = [
            'key'    => 'p' . $index,
            'name'   => $name,
            'weight' => $weight > 0 ? $weight : 1,
            'stock'  => $stock,
            'type'   => $type,
            /* vip：等级:天数；credit：积分数；text / none：兑奖说明 */
            'value'  => mb_substr((string) ($parts[4] ?? ''), 0, 60, 'UTF-8'),
        ];
    }

    return $prizes;
}

/** 单次抽取消耗的积分 */
function ecod_lottery_cost(): int
{
    return max(0, (int) ecod_option('ELotteryCost', 10));
}

/** 每人每日次数上限（0 = 不限） */
function ecod_lottery_daily(): int
{
    return max(0, (int) ecod_option('ELotteryDaily', 3));
}

/** 保底次数：连续 N 次未中，下一抽必中（0 = 关闭） */
function ecod_lottery_pity(): int
{
    return max(0, (int) ecod_option('ELotteryPity', 0));
}

/** 直接兑换头奖的价格（Credit，0 = 关闭） */
function ecod_lottery_exchange_price(): int
{
    return max(0, (int) ecod_option('ELotteryExchange', 0));
}

/**
 * 某奖品已消耗的库存（全部历史中奖行数，请求内缓存：
 * 挂件与页面同页渲染、overview 与 draw 多处调用时只查一次）。
 * 侧栏挂件全站渲染时每个奖品各一次 COUNT，游客页也会打到 DB——
 * 再套 5 分钟文件缓存（抽奖 / 兑换落库时主动失效，见 ecod_lottery_draw）。
 */
function ecod_lottery_used(string $key): int
{
    static $cache = [];
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    if (function_exists('ecod_cache_remember')) {
        return $cache[$key] = (int) ecod_cache_remember('lottery_used_' . $key, 300, function () use ($key) {
            return ecod_lottery_used_query($key);
        });
    }
    return $cache[$key] = ecod_lottery_used_query($key);
}

function ecod_lottery_used_query(string $key): int
{
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchObject(
            $db->select('COUNT(*) AS n')->from('table.ecod_lottery_logs')->where('prize_key = ?', $key)
        );
        return $row ? (int) $row->n : 0;
    } catch (\Throwable $e) {
        return 0;
    }
}

/**
 * 某用户今天已抽次数（直接兑换不算抽奖次数）
 */
function ecod_lottery_drawn_today(int $uid): int
{
    if ($uid <= 0) {
        return 0;
    }
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchObject(
            $db->select('COUNT(*) AS n')->from('table.ecod_lottery_logs')
                ->where('uid = ?', $uid)->where('day = ?', (int) date('Ymd'))
                ->where('prize_type <> ?', 'exchange')
        );
        return $row ? (int) $row->n : 0;
    } catch (\Throwable $e) {
        return 0;
    }
}

/**
 * 连续未中次数（从最近一条记录往前数 prize_type = none 的连续长度）。
 * 直接兑换（exchange）视为「中了」，会中断连未计数。
 */
function ecod_lottery_dry_streak(int $uid, int $window = 0): int
{
    if ($uid <= 0) {
        return 0;
    }
    try {
        $db = \Typecho\Db::get();
        $rows = $db->fetchAll(
            $db->select('prize_type')->from('table.ecod_lottery_logs')
                ->where('uid = ?', $uid)
                ->order('id', \Typecho\Db::SORT_DESC)
                ->limit(max(1, $window > 0 ? $window : max(1, ecod_lottery_pity())))
        );
        $n = 0;
        foreach ($rows ?: [] as $row) {
            if ((string) $row['prize_type'] === 'none') {
                $n++;
            } else {
                break;
            }
        }
        return $n;
    } catch (\Throwable $e) {
        return 0;
    }
}

/**
 * 当前可抽的头奖（第一个非「谢谢参与」且未抽完的奖品）
 *
 * @return array{key:string,name:string,type:string,value:string,stock:int,left:int|null}|null
 */
function ecod_lottery_top_available(): ?array
{
    foreach (ecod_lottery_prizes() as $prize) {
        if ($prize['type'] === 'none') {
            continue;
        }
        if ($prize['stock'] > 0 && ecod_lottery_used($prize['key']) >= $prize['stock']) {
            continue;
        }
        return [
            'key'   => $prize['key'],
            'name'  => $prize['name'],
            'type'  => $prize['type'],
            'value' => $prize['value'],
            'stock' => $prize['stock'],
            'left'  => $prize['stock'] > 0 ? max(0, $prize['stock'] - ecod_lottery_used($prize['key'])) : null,
        ];
    }
    return null;
}

/**
 * 发放奖品（抽奖与直接兑换共用）：credit 记中奖流水，vip 走权益授予
 *
 * @return array{delta:int,tip:string}
 */
function ecod_lottery_fulfil(int $uid, array $prize): array
{
    $delta = 0;
    $tip = '';
    if ($prize['type'] === 'credit') {
        $delta = max(0, (int) $prize['value']);
        if ($delta > 0 && function_exists('ecod_credits_add')) {
            ecod_credits_add($uid, $delta, 'lottery_win', $prize['key'], '抽奖中奖：' . $prize['name']);
        }
    } elseif ($prize['type'] === 'vip') {
        $vipBits = explode(':', $prize['value']);
        $level = max(1, min(2, (int) ($vipBits[0] ?? 1)));
        $days = max(1, (int) ($vipBits[1] ?? 7));
        if (function_exists('ecod_pay_grant') && ecod_pay_grant([
            'uid' => $uid, 'kind' => 'vip', 'target' => 0,
            'level' => $level, 'days' => $days, 'source' => 'lottery', 'source_id' => $prize['key'],
        ])) {
            $tip = '已开通 ' . (function_exists('ecod_pay_level_name') ? ecod_pay_level_name($level) : ('Lv.' . $level)) . ' ' . $days . ' 天';
        }
    }
    return ['delta' => $delta, 'tip' => $tip];
}

/**
 * 中奖记录（我的记录）
 *
 * @return array<int, array{name:string,type:string,credits:int,created:int}>
 */
function ecod_lottery_mine(int $uid, int $limit = 10): array
{
    if ($uid <= 0) {
        return [];
    }
    try {
        $db = \Typecho\Db::get();
        $rows = $db->fetchAll(
            $db->select('prize_name', 'prize_type', 'credits', 'created')->from('table.ecod_lottery_logs')
                ->where('uid = ?', $uid)
                ->order('id', \Typecho\Db::SORT_DESC)
                ->limit(max(1, min(50, $limit)))
        );
        $out = [];
        foreach ($rows ?: [] as $row) {
            $out[] = [
                'name'    => (string) $row['prize_name'],
                'type'    => (string) $row['prize_type'],
                'credits' => (int) $row['credits'],
                'created' => (int) $row['created'],
            ];
        }
        return $out;
    } catch (\Throwable $e) {
        return [];
    }
}

/**
 * 前台总览：页面与挂件共用的同一份口径（消耗 / 概率 / 兑换 / 库存 / 保底 / 记录）
 */
function ecod_lottery_overview(int $uid = 0): array
{
    $uid = (int) $uid;

    /* 中奖概率 = 非谢谢参与奖品的权重占可抽池权的比例 */
    $totalWeight = 0;
    $winWeight = 0;
    $finished = true;
    foreach (ecod_lottery_prizes() as $prize) {
        if ($prize['stock'] > 0 && ecod_lottery_used($prize['key']) >= $prize['stock']) {
            continue;
        }
        $finished = false;
        $totalWeight += $prize['weight'];
        if ($prize['type'] !== 'none') {
            $winWeight += $prize['weight'];
        }
    }

    $pityN = ecod_lottery_pity();
    $top = ecod_lottery_top_available();
    $exPrice = ecod_lottery_exchange_price();

    return [
        'on'        => ecod_lottery_enabled(),
        'prizes'    => ecod_lottery_prizes_public(),
        'cost'      => ecod_lottery_cost(),
        'daily'     => ecod_lottery_daily(),
        'left'      => $uid > 0
            ? (ecod_lottery_daily() > 0 ? max(0, ecod_lottery_daily() - ecod_lottery_drawn_today($uid)) : -1)
            : -2,
        'balance'   => $uid > 0 && function_exists('ecod_credits_balance') ? ecod_credits_balance($uid) : 0,
        'credit'    => function_exists('ecod_credits_name') ? ecod_credits_name() : 'Credit',
        'win_rate'  => $totalWeight > 0 ? (int) round($winWeight / $totalWeight * 100) : 0,
        'finished'  => $finished,
        'top'       => $top,
        'exchange'  => ['price' => $exPrice, 'on' => $exPrice > 0 && $top !== null],
        'pity'      => [
            'on'  => $pityN > 0,
            'n'   => $pityN,
            'now' => $pityN > 0 ? ecod_lottery_dry_streak($uid) : 0,
        ],
        'mine'      => ecod_lottery_mine($uid, 10),
    ];
}

/**
 * 前台可见的奖品列表（权重不下发）
 *
 * @return array<int, array{key:string,name:string,type:string,left:int|null}>
 */
function ecod_lottery_prizes_public(): array
{
    $out = [];
    foreach (ecod_lottery_prizes() as $prize) {
        $left = null;
        if ($prize['stock'] > 0) {
            $left = max(0, $prize['stock'] - ecod_lottery_used($prize['key']));
        }
        $out[] = [
            'key'   => $prize['key'],
            'name'  => $prize['name'],
            'type'  => $prize['type'],
            'left'  => $left,
        ];
    }
    return $out;
}

/**
 * 抽一次（先扣积分 → 加权抽签 → 落流水 → 发奖）
 *
 * @return array{ok:bool,error?:string,prize?:array,balance?:int,left?:int}
 */
function ecod_lottery_draw(int $uid): array
{
    if (!ecod_lottery_enabled()) {
        return ['ok' => false, 'error' => '抽奖未开启'];
    }
    if ($uid <= 0) {
        return ['ok' => false, 'error' => '请先登录'];
    }
    if (!function_exists('ecod_credits_balance')) {
        require_once __DIR__ . '/../user/credits.php';
    }

    $daily = ecod_lottery_daily();
    if ($daily > 0 && ecod_lottery_drawn_today($uid) >= $daily) {
        return ['ok' => false, 'error' => '今天的抽奖次数已用完'];
    }

    $cost = ecod_lottery_cost();
    $balance = ecod_credits_balance($uid);
    if ($cost > 0 && $balance < $cost) {
        return ['ok' => false, 'error' => ecod_credits_name() . ' 不足，先去签到赚积分吧'];
    }

    /* 可抽的奖品 = 库存未抽空的 */
    $pool = [];
    foreach (ecod_lottery_prizes() as $prize) {
        if ($prize['stock'] > 0 && ecod_lottery_used($prize['key']) >= $prize['stock']) {
            continue;
        }
        $pool[] = $prize;
    }
    if (!$pool) {
        return ['ok' => false, 'error' => '奖品已抽完，下次再来'];
    }

    /* 保底：连续 N-1 抽未中时，本抽只在「有奖」池里抽（guaqi 式 N 次保底必中） */
    $pityN = ecod_lottery_pity();
    if ($pityN > 0 && ecod_lottery_dry_streak($uid) >= $pityN - 1) {
        $winPool = array_values(array_filter($pool, static function (array $one): bool {
            return $one['type'] !== 'none';
        }));
        if ($winPool) {
            $pool = $winPool;
        }
    }

    /* 扣积分（0 成本活动不记账） */
    if ($cost > 0) {
        $paid = ecod_credits_add($uid, -$cost, 'lottery', 'draw', '抽奖消耗');
        if (empty($paid['ok'])) {
            return ['ok' => false, 'error' => '扣费失败，请重试'];
        }
    }

    /* 加权随机 */
    $total = 0;
    foreach ($pool as $prize) {
        $total += $prize['weight'];
    }
    try {
        $pick = random_int(1, max(1, $total));
    } catch (\Throwable $e) {
        $pick = mt_rand(1, max(1, $total));
    }
    $prize = $pool[0];
    foreach ($pool as $one) {
        $pick -= $one['weight'];
        if ($pick <= 0) {
            $prize = $one;
            break;
        }
    }

    /* 落中奖流水 + 发奖 */
    $fulfil = ecod_lottery_fulfil($uid, $prize);

    try {
        $db = \Typecho\Db::get();
        $db->query($db->insert('table.ecod_lottery_logs')->rows([
            'uid'        => $uid,
            'prize_key'  => $prize['key'],
            'prize_name' => mb_substr($prize['name'], 0, 120, 'UTF-8'),
            'prize_type' => $prize['type'],
            'credits'    => $fulfil['delta'],
            'day'        => (int) date('Ymd'),
            'created'    => time(),
        ]));
        if (function_exists('ecod_cache_forget')) {
            ecod_cache_forget('lottery_used_' . $prize['key']);
        }
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => '记录失败，请重试'];
    }

    return [
        'ok'      => true,
        'prize'   => ['key' => $prize['key'], 'name' => $prize['name'], 'type' => $prize['type'], 'tip' => $fulfil['tip']],
        'balance' => ecod_credits_balance($uid),
        'left'    => max(0, ($daily > 0 ? $daily : 999) - ecod_lottery_drawn_today($uid)),
    ];
}

/**
 * 直接兑换头奖（guaqi 式）：付固定 Credit 跳过概率直接拿第一个可抽的有奖奖品
 *
 * 兑换记 prize_type = exchange，不计抽奖次数、不打断也不推进保底连未计数
 * （视为「中了」，会重置连未）。
 */
function ecod_lottery_exchange(int $uid): array
{
    if (!ecod_lottery_enabled()) {
        return ['ok' => false, 'error' => '抽奖未开启'];
    }
    if ($uid <= 0) {
        return ['ok' => false, 'error' => '请先登录'];
    }
    if (!function_exists('ecod_credits_balance')) {
        require_once __DIR__ . '/../user/credits.php';
    }

    $price = ecod_lottery_exchange_price();
    if ($price <= 0) {
        return ['ok' => false, 'error' => '本活动未开放直接兑换'];
    }

    $top = ecod_lottery_top_available();
    if (!$top) {
        return ['ok' => false, 'error' => '奖品已兑完'];
    }

    if (ecod_credits_balance($uid) < $price) {
        return ['ok' => false, 'error' => ecod_credits_name() . ' 不足，直接兑换需要 ' . $price];
    }

    $paid = ecod_credits_add($uid, -$price, 'lottery', 'exchange', '直接兑换：' . $top['name']);
    if (empty($paid['ok'])) {
        return ['ok' => false, 'error' => '扣费失败，请重试'];
    }

    $fulfil = ecod_lottery_fulfil($uid, $top);

    try {
        $db = \Typecho\Db::get();
        $db->query($db->insert('table.ecod_lottery_logs')->rows([
            'uid'        => $uid,
            'prize_key'  => $top['key'],
            'prize_name' => mb_substr($top['name'], 0, 120, 'UTF-8'),
            'prize_type' => 'exchange',
            'credits'    => $fulfil['delta'],
            'day'        => (int) date('Ymd'),
            'created'    => time(),
        ]));
        if (function_exists('ecod_cache_forget')) {
            ecod_cache_forget('lottery_used_' . $top['key']);
        }
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => '记录失败，请重试'];
    }

    $daily = ecod_lottery_daily();
    return [
        'ok'      => true,
        'prize'   => ['key' => $top['key'], 'name' => $top['name'], 'type' => $top['type'], 'tip' => $fulfil['tip']],
        'balance' => ecod_credits_balance($uid),
        'left'    => $daily > 0 ? max(0, $daily - ecod_lottery_drawn_today($uid)) : -1,
    ];
}
