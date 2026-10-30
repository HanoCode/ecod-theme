<?php
/**
 * Ecod Theme · 推广返佣（guaqi 式两级佣金的紧凑版：一级返积分）
 *
 * 流程：用户带着 ?ref=<推荐人uid> 访问任意页面 → 写 30 天 Cookie；
 * 该访客注册成功时把推荐人记进 user_meta（referrer，只记一次）；
 * 被推荐人日后每笔付费订单结算成功，推荐人按比例获得 Credit 返利。
 *
 * 返利走积分账本（type=referral），不产生现金；自推自买不计。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/** 推广返佣是否开启 */
function ecod_ref_on(): bool
{
    return (string) ecod_option('ECrRefOn', 'off') === 'on';
}

/** 返佣比例（占订单金额的百分比，1-50） */
function ecod_ref_percent(): int
{
    return max(0, min(50, (int) ecod_option('ECrRefPercent', 10)));
}

/**
 * 捕获推广来源：任意页面带 ?ref=<uid> 即写 30 天 Cookie（themeInit 调用）
 */
function ecod_ref_capture(): void
{
    if (!isset($_GET['ref'])) {
        return;
    }
    $uid = (int) $_GET['ref'];
    if ($uid <= 0) {
        return;
    }

    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow($db->select('uid')->from('table.users')->where('uid = ?', $uid)->limit(1));
        if (!$row) {
            return;
        }
    } catch (\Throwable $e) {
        return;
    }

    if (!headers_sent()) {
        try {
            setcookie('ecod_ref', (string) $uid, [
                'expires'  => time() + 30 * 86400,
                'path'     => '/',
                'samesite' => 'Lax',
            ]);
        } catch (\Throwable $e) {
        }
    }
    $_COOKIE['ecod_ref'] = (string) $uid;
}

/**
 * 读取某用户的推荐人（user_meta referrer，0 = 无）
 */
function ecod_referrer_of(int $uid): int
{
    if ($uid <= 0) {
        return 0;
    }
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('value')->from('table.ecod_user_meta')
                ->where('uid = ?', $uid)->where('name = ?', 'referrer')->limit(1)
        );
        return $row ? max(0, (int) $row['value']) : 0;
    } catch (\Throwable $e) {
        return 0;
    }
}

/**
 * 注册归因：新用户注册成功时把 Cookie 里的推荐人写进 user_meta（只记第一次）
 */
function ecod_ref_attribute(int $uid): void
{
    if ($uid <= 0) {
        return;
    }
    $referrer = isset($_COOKIE['ecod_ref']) ? (int) $_COOKIE['ecod_ref'] : 0;
    if ($referrer <= 0 || $referrer === $uid) {
        return;
    }

    try {
        $db = \Typecho\Db::get();
        /* 推荐人必须真实存在 */
        $row = $db->fetchRow($db->select('uid')->from('table.users')->where('uid = ?', $referrer)->limit(1));
        if (!$row) {
            return;
        }
        /* 已有归因不覆盖：以第一次注册时为准 */
        $exists = $db->fetchRow(
            $db->select('value')->from('table.ecod_user_meta')
                ->where('uid = ?', $uid)->where('name = ?', 'referrer')->limit(1)
        );
        if ($exists) {
            return;
        }
        $db->query($db->insert('table.ecod_user_meta')->rows([
            'uid'     => $uid,
            'name'    => 'referrer',
            'value'   => (string) $referrer,
            'created' => time(),
        ]));
    } catch (\Throwable $e) {
        /* 归因失败不影响注册 */
    }
}

/**
 * 订单结算返佣：被推荐人的付费订单完成时，推荐人按比例得 Credit
 *
 * 从 ecod_pay_settle 调用；幂等靠「同一订单号只发一次」的流水检查。
 */
function ecod_ref_pay_commission(array $order): void
{
    if (!ecod_ref_on()) {
        return;
    }
    $percent = ecod_ref_percent();
    $amount = (int) ($order['amount'] ?? 0);
    if ($percent <= 0 || $amount <= 0) {
        return;
    }

    $buyer = (int) ($order['uid'] ?? 0);
    $referrer = ecod_referrer_of($buyer);
    if ($referrer <= 0 || $referrer === $buyer) {
        return;
    }

    if (!function_exists('ecod_credits_add')) {
        require_once __DIR__ . '/credits.php';
    }

    /* 同一订单只返一次（settle 本身幂等，这里再兜一层） */
    try {
        $db = \Typecho\Db::get();
        $dup = $db->fetchRow(
            $db->select('id')->from('table.ecod_credits')
                ->where('uid = ?', $referrer)->where('type = ?', 'referral')
                ->where('ref = ?', (string) ($order['order_no'] ?? ''))
                ->limit(1)
        );
        if ($dup) {
            return;
        }
    } catch (\Throwable $e) {
        return;
    }

    /* 1 元 = 100 Credit 基准：返佣积分 = 订单分 × 比例 / 100 */
    $credits = intdiv($amount * $percent, 100);
    if ($credits <= 0) {
        return;
    }

    ecod_credits_add(
        $referrer,
        $credits,
        'referral',
        (string) ($order['order_no'] ?? ''),
        '推广返佣：订单 ' . (string) ($order['order_no'] ?? '')
    );
}

/**
 * 累计推广收益（流水合计）
 */
function ecod_ref_earned(int $uid): int
{
    if ($uid <= 0) {
        return 0;
    }
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchObject(
            $db->select('SUM(delta) AS total')->from('table.ecod_credits')
                ->where('uid = ?', $uid)->where('type = ?', 'referral')
        );
        return $row && $row->total !== null ? (int) $row->total : 0;
    } catch (\Throwable $e) {
        return 0;
    }
}

/** 推广链接（带 ref 参数的站点首页地址） */
function ecod_ref_link(int $uid): string
{
    if ($uid <= 0) {
        return '';
    }
    try {
        $site = rtrim((string) \Widget\Options::alloc()->siteUrl, '/');
        return $site . '/?ref=' . $uid;
    } catch (\Throwable $e) {
        return '';
    }
}
