<?php
/**
 * Ecod Theme · 积分兑换商城（guaqi「点数当钱花」）
 *
 * 商品池在主题设置里维护（文本协议，一行一个商品）：
 *   名称|价格(积分)|类型|值|库存(0=不限)|每人限购(0=不限)
 *   会员 7 天|500|vip|1:7|100|0
 *   单篇《文章标题》|300|post|128|0|1
 *   定制周边|3000|text|中奖后联系站长发货|5|1
 *
 * 发货：vip 走权益授予、post 走单篇权益、text 仅记录待发货（联系站长）。
 * 兑换即扣积分（type=shop），库存 / 限购按 ecod_exchanges 行数惰性计算。
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
 * 兑换商城是否开启（开关 + 至少一个商品）
 */
function ecod_shop_enabled(): bool
{
    if ((string) ecod_option('EShop', 'off') !== 'on') {
        return false;
    }
    return ecod_shop_items() !== [];
}

/**
 * 商品池解析（请求内静态缓存）
 *
 * @return array<int, array{key:string,name:string,cost:int,type:string,value:string,stock:int,limit:int}>
 */
function ecod_shop_items(): array
{
    static $items = null;
    if ($items !== null) {
        return $items;
    }

    $items = [];
    $raw = (string) ecod_option('EShopItems', '');
    if (trim($raw) === '') {
        return $items;
    }

    foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $index => $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $parts = array_map('trim', explode('|', $line));
        $name = mb_substr((string) ($parts[0] ?? ''), 0, 60, 'UTF-8');
        $cost = max(0, (int) ($parts[1] ?? 0));
        if ($name === '' || $cost <= 0) {
            continue;
        }
        $type = strtolower((string) ($parts[2] ?? 'text'));
        if (!in_array($type, ['vip', 'post', 'text'], true)) {
            $type = 'text';
        }
        $items[] = [
            'key'   => 'g' . $index,
            'name'  => $name,
            'cost'  => $cost,
            'type'  => $type,
            /* vip：等级:天数；post：cid；text：发货说明 */
            'value' => mb_substr((string) ($parts[3] ?? ''), 0, 60, 'UTF-8'),
            'stock' => max(0, (int) ($parts[4] ?? 0)),
            'limit' => max(0, (int) ($parts[5] ?? 0)),
        ];
    }

    return $items;
}

/** 某商品已兑换数量（全部历史，请求内缓存） */
function ecod_shop_used(string $key): int
{
    static $cache = [];
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchObject(
            $db->select('COUNT(*) AS n')->from('table.ecod_exchanges')->where('item_key = ?', $key)
        );
        return $cache[$key] = $row ? (int) $row->n : 0;
    } catch (\Throwable $e) {
        return $cache[$key] = 0;
    }
}

/** 某用户已兑换某商品的次数（限购判定） */
function ecod_shop_bought(int $uid, string $key): int
{
    if ($uid <= 0) {
        return 0;
    }
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchObject(
            $db->select('COUNT(*) AS n')->from('table.ecod_exchanges')
                ->where('uid = ?', $uid)->where('item_key = ?', $key)
        );
        return $row ? (int) $row->n : 0;
    } catch (\Throwable $e) {
        return 0;
    }
}

/** 发货类型中文文案 */
function ecod_shop_type_label(string $type): string
{
    $map = ['vip' => '会员', 'post' => '单篇解锁', 'text' => '待发货'];
    return $map[$type] ?? $type;
}

/**
 * 前台总览：商城页与挂件共用
 */
function ecod_shop_overview(int $uid = 0, int $limit = 0): array
{
    $uid = (int) $uid;
    $items = [];
    foreach (ecod_shop_items() as $item) {
        $used = $item['stock'] > 0 ? ecod_shop_used($item['key']) : 0;
        if ($item['stock'] > 0 && $used >= $item['stock']) {
            continue; /* 抽完 / 兑完的商品不再展示 */
        }
        $items[] = [
            'key'    => $item['key'],
            'name'   => $item['name'],
            'cost'   => $item['cost'],
            'type'   => $item['type'],
            'left'   => $item['stock'] > 0 ? $item['stock'] - $used : null,
            'bought' => $uid > 0 ? ecod_shop_bought($uid, $item['key']) : 0,
            'limit'  => $item['limit'],
            'soldout_for_me' => $item['limit'] > 0 && $uid > 0 && ecod_shop_bought($uid, $item['key']) >= $item['limit'],
        ];
        if ($limit > 0 && count($items) >= $limit) {
            break;
        }
    }

    return [
        'on'      => ecod_shop_enabled(),
        'items'   => $items,
        'credit'  => function_exists('ecod_credits_name') ? ecod_credits_name() : 'Credit',
        'balance' => $uid > 0 && function_exists('ecod_credits_balance') ? ecod_credits_balance($uid) : 0,
        'mine'    => ecod_shop_mine($uid, 10),
    ];
}

/**
 * 我的兑换记录
 *
 * @return array<int, array{name:string,type:string,cost:int,note:string,created:int}>
 */
function ecod_shop_mine(int $uid, int $limit = 10): array
{
    if ($uid <= 0) {
        return [];
    }
    try {
        $db = \Typecho\Db::get();
        $rows = $db->fetchAll(
            $db->select('item_name', 'deliver', 'cost', 'note', 'created')->from('table.ecod_exchanges')
                ->where('uid = ?', $uid)
                ->order('id', \Typecho\Db::SORT_DESC)
                ->limit(max(1, min(50, $limit)))
        );
        $out = [];
        foreach ($rows ?: [] as $row) {
            $out[] = [
                'name'    => (string) $row['item_name'],
                'type'    => (string) $row['deliver'],
                'cost'    => (int) $row['cost'],
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
 * 兑换一个商品（先扣积分 → 发货 → 落记录）
 *
 * @return array{ok:bool,error?:string,item?:array,balance?:int}
 */
function ecod_shop_exchange(int $uid, string $key): array
{
    if (!ecod_shop_enabled()) {
        return ['ok' => false, 'error' => '兑换商城未开启'];
    }
    if ($uid <= 0) {
        return ['ok' => false, 'error' => '请先登录'];
    }
    if (!function_exists('ecod_credits_balance')) {
        require_once __DIR__ . '/../user/credits.php';
    }

    $item = null;
    foreach (ecod_shop_items() as $one) {
        if ($one['key'] === $key) {
            $item = $one;
            break;
        }
    }
    if (!$item) {
        return ['ok' => false, 'error' => '商品不存在'];
    }

    if ($item['stock'] > 0 && ecod_shop_used($key) >= $item['stock']) {
        return ['ok' => false, 'error' => '该商品已兑完'];
    }
    if ($item['limit'] > 0 && ecod_shop_bought($uid, $key) >= $item['limit']) {
        return ['ok' => false, 'error' => '已达每人限购次数'];
    }
    if (ecod_credits_balance($uid) < $item['cost']) {
        return ['ok' => false, 'error' => ecod_credits_name() . ' 不足，先去签到赚积分吧'];
    }

    $paid = ecod_credits_add($uid, -$item['cost'], 'shop', $key, '兑换：' . $item['name']);
    if (empty($paid['ok'])) {
        return ['ok' => false, 'error' => '扣费失败，请重试'];
    }

    /* 发货 */
    $note = '';
    if ($item['type'] === 'vip') {
        $vipBits = explode(':', $item['value']);
        $level = max(1, min(2, (int) ($vipBits[0] ?? 1)));
        $days = max(1, (int) ($vipBits[1] ?? 7));
        if (function_exists('ecod_pay_grant') && ecod_pay_grant([
            'uid' => $uid, 'kind' => 'vip', 'target' => 0,
            'level' => $level, 'days' => $days, 'source' => 'shop', 'source_id' => $key,
        ])) {
            $note = '已开通 ' . (function_exists('ecod_pay_level_name') ? ecod_pay_level_name($level) : ('Lv.' . $level)) . ' ' . $days . ' 天';
        } else {
            $note = '会员权益发放异常，请联系站长处理';
        }
    } elseif ($item['type'] === 'post') {
        $cid = (int) $item['value'];
        if ($cid > 0 && function_exists('ecod_pay_grant') && ecod_pay_grant([
            'uid' => $uid, 'kind' => 'post', 'target' => $cid,
            'source' => 'shop', 'source_id' => $key,
        ])) {
            $note = '已解锁文章 #' . $cid;
        } else {
            $note = '权益发放异常，请联系站长处理';
        }
    } else {
        $note = $item['value'] !== '' ? $item['value'] : '兑换成功，请联系站长发货';
    }

    try {
        $db = \Typecho\Db::get();
        $db->query($db->insert('table.ecod_exchanges')->rows([
            'uid'       => $uid,
            'item_key'  => $item['key'],
            'item_name' => mb_substr($item['name'], 0, 120, 'UTF-8'),
            'cost'      => $item['cost'],
            'deliver'   => $item['type'],
            'ref'       => $item['value'],
            'note'      => mb_substr($note, 0, 180, 'UTF-8'),
            'created'   => time(),
        ]));
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => '记录失败，请重试'];
    }

    return [
        'ok'      => true,
        'item'    => ['key' => $item['key'], 'name' => $item['name'], 'note' => $note, 'type' => $item['type']],
        'balance' => ecod_credits_balance($uid),
    ];
}
