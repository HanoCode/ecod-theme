<?php
/**
 * Ecod Theme · 自助广告
 *
 * 用户在投放页选广告位、天数，下单走既有支付通道（orders.kind = 'ad'，
 * target = 广告 id）；结算（epay 回调 / 管理台确认）后按站点设置
 * 进入「待审核」或直接投放。展示端只读 active 且未到期的记录。
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
 * 可售卖的广告位（key => 名称）
 */
function ecod_ads_slots(): array
{
    return [
        'home_top'    => '首页顶部通栏',
        'sidebar'     => '侧边栏卡片',
        'post_bottom' => '文章底部通栏',
    ];
}

/** 自助广告总开关 */
function ecod_ads_enabled(): bool
{
    return (string) ecod_option('EAdSelf', 'off') === 'on' && ecod_pay_enabled();
}

/** 广告是否需要审核（关 = 支付成功直接投放） */
function ecod_ads_review_on(): bool
{
    return (string) ecod_option('EAdSelfReview', 'on') === 'on';
}

/**
 * 某广告位的单价（分/天），未开放返回 null
 */
function ecod_ads_price(string $slot): ?int
{
    $map = [
        'home_top'    => 'EAdSelfPriceHomeTop',
        'sidebar'     => 'EAdSelfPriceSidebar',
        'post_bottom' => 'EAdSelfPricePostBottom',
    ];
    if (!isset($map[$slot])) {
        return null;
    }
    $yuan = trim((string) ecod_option($map[$slot], ''));
    if ($yuan === '') {
        return null;
    }
    $cents = function_exists('ecod_pay_cents') ? ecod_pay_cents($yuan) : null;
    return $cents !== null && $cents > 0 ? $cents : null;
}

/**
 * 天数限制
 * @return array{min:int,max:int}
 */
function ecod_ads_days_range(): array
{
    return [
        'min' => max(1, (int) ecod_option('EAdSelfMinDays', 3)),
        'max' => max(1, (int) ecod_option('EAdSelfMaxDays', 90)),
    ];
}

/**
 * 图片 / 链接地址清洗（与 widget 参数同规则）
 */
function ecod_ads_clean_url(string $url): string
{
    $url = trim(str_replace(["\r", "\n", "\t"], '', $url));
    if ($url !== '' && !preg_match('#^(https?://|/)#i', $url)) {
        return '';
    }
    return mb_substr($url, 0, 480, 'UTF-8');
}

/**
 * 创建投放申请 + 待支付订单
 *
 * @param array{slot:string,title:string,image:string,link:string,days:int} $in
 * @return array{ok:bool,error?:string,ad_id?:int,order_no?:string,amount?:int}
 */
function ecod_ads_create(int $uid, array $in): array
{
    if (!ecod_ads_enabled()) {
        return ['ok' => false, 'error' => '本站未开放自助广告'];
    }
    if ($uid <= 0) {
        return ['ok' => false, 'error' => '请先登录'];
    }

    $slot = (string) ($in['slot'] ?? '');
    $price = ecod_ads_price($slot);
    if ($price === null) {
        return ['ok' => false, 'error' => '请选择广告位'];
    }

    $title = trim((string) ($in['title'] ?? ''));
    $image = ecod_ads_clean_url((string) ($in['image'] ?? ''));
    $link = ecod_ads_clean_url((string) ($in['link'] ?? ''));
    if ($title === '' || $image === '' || $link === '') {
        return ['ok' => false, 'error' => '标题、图片与跳转链接都要填写'];
    }
    if (!preg_match('#^https?://#i', $link)) {
        return ['ok' => false, 'error' => '跳转链接必须是 http(s) 地址'];
    }

    $range = ecod_ads_days_range();
    $days = (int) ($in['days'] ?? 0);
    if ($days < $range['min'] || $days > $range['max']) {
        return ['ok' => false, 'error' => '投放天数需在 ' . $range['min'] . '–' . $range['max'] . ' 天之间'];
    }

    $amount = $price * $days;
    if ($amount <= 0) {
        return ['ok' => false, 'error' => '金额计算异常'];
    }

    try {
        $db = \Typecho\Db::get();
        /* Typecho 的 insert query 直接返回自增 id（pgsql / sqlite 适配器同样处理） */
        $adId = (int) $db->query($db->insert('table.ecod_ads')->rows([
            'uid'     => $uid,
            'slot'    => $slot,
            'title'   => mb_substr($title, 0, 60, 'UTF-8'),
            'image'   => $image,
            'link'    => $link,
            'days'    => $days,
            'status'  => 'pending_pay',
            'order_no'=> '',
            'created' => time(),
        ]));
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => '创建失败，请稍后重试'];
    }
    if ($adId <= 0) {
        return ['ok' => false, 'error' => '创建失败，请稍后重试'];
    }

    /* 复用付费订单：kind=ad，target=广告 id，days=投放天数。
       未显式指定通道时跟随站点默认支付通道（与收银台口径一致） */
    $channel = trim((string) ($in['channel'] ?? ''));
    if ($channel === '' && function_exists('ecod_pay_default_channel')) {
        $channel = ecod_pay_default_channel();
    }
    $created = ecod_pay_order_create([
        'uid'     => $uid,
        'kind'    => 'ad',
        'target'  => $adId,
        'title'   => '自助广告 · ' . (ecod_ads_slots()[$slot] ?? $slot) . ' · ' . $days . ' 天',
        'amount'  => $amount,
        'days'    => $days,
        'channel' => $channel !== '' ? $channel : 'manual',
    ]);
    if (empty($created['ok'])) {
        /* 订单没建成就把广告行撤掉，避免留下永远待支付的孤儿 */
        try {
            $db = \Typecho\Db::get();
            $db->query($db->delete('table.ecod_ads')->where('id = ?', $adId)->where('status = ?', 'pending_pay'));
        } catch (\Throwable $e) {
        }
        return ['ok' => false, 'error' => (string) ($created['error'] ?? '创建订单失败')];
    }

    try {
        $db = \Typecho\Db::get();
        $db->query($db->update('table.ecod_ads')
            ->rows(['order_no' => (string) $created['order_no']])
            ->where('id = ?', $adId));
    } catch (\Throwable $e) {
    }

    return ['ok' => true, 'ad_id' => $adId, 'order_no' => (string) $created['order_no'], 'amount' => $amount];
}

/**
 * 订单结算回调：待支付 → 待审核 / 直接投放（是否审核由站点设置决定）
 */
function ecod_ads_on_paid(int $adId): bool
{
    $adId = (int) $adId;
    if ($adId <= 0) {
        return false;
    }

    $now = time();
    $status = ecod_ads_review_on() ? 'pending' : 'active';
    $rows = ['status' => $status, 'reviewed_at' => $now];
    if ($status === 'active') {
        /* 直接投放：起止时间在结算时确定 */
        try {
            $db = \Typecho\Db::get();
            $row = $db->fetchRow($db->select('days')->from('table.ecod_ads')->where('id = ?', $adId)->limit(1));
            $days = max(1, (int) ($row['days'] ?? 1));
            $rows['start_at'] = $now;
            $rows['end_at'] = $now + $days * 86400;
        } catch (\Throwable $e) {
        }
    }

    try {
        $db = \Typecho\Db::get();
        $db->query($db->update('table.ecod_ads')->rows($rows)
            ->where('id = ?', $adId)->where('status = ?', 'pending_pay'));
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * 管理员审核（pay-admin 调用）
 *
 * @return array{ok:bool,message:string}
 */
function ecod_ads_review(int $adId, string $op): array
{
    $adId = (int) $adId;
    $op = $op === 'pass' ? 'pass' : 'reject';
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow($db->select('id', 'status', 'days')->from('table.ecod_ads')->where('id = ?', $adId)->limit(1));
        if (!$row || !in_array((string) $row['status'], ['pending', 'active'], true)) {
            return ['ok' => false, 'message' => '广告不存在或当前状态不可审核'];
        }

        if ($op === 'pass') {
            $now = time();
            $days = max(1, (int) $row['days']);
            $db->query($db->update('table.ecod_ads')->rows([
                'status'      => 'active',
                'start_at'    => $now,
                'end_at'      => $now + $days * 86400,
                'reviewed_at' => $now,
            ])->where('id = ?', $adId));
            return ['ok' => true, 'message' => '已通过，投放 ' . $days . ' 天'];
        }

        $db->query($db->update('table.ecod_ads')->rows([
            'status'      => 'rejected',
            'reviewed_at' => time(),
        ])->where('id = ?', $adId));
        return ['ok' => true, 'message' => '已拒绝（订单流水保留，请联系用户退款）'];
    } catch (\Throwable $e) {
        return ['ok' => false, 'message' => '操作失败'];
    }
}

/**
 * 展示中的广告（active 且未到期，按到期时间升序）
 *
 * @return array<int, array{id:int,title:string,image:string,link:string,end_at:int}>
 */
function ecod_ads_active(string $slot, int $limit = 3): array
{
    if (!isset(ecod_ads_slots()[$slot])) {
        return [];
    }
    try {
        $db = \Typecho\Db::get();
        $rows = $db->fetchAll(
            $db->select('id', 'title', 'image', 'link', 'end_at')->from('table.ecod_ads')
                ->where('slot = ?', $slot)->where('status = ?', 'active')
                ->where('start_at <= ?', time())->where('end_at > ?', time())
                ->order('end_at', \Typecho\Db::SORT_ASC)
                ->limit(max(1, min(10, $limit)))
        );
        $out = [];
        foreach ($rows ?: [] as $row) {
            $out[] = [
                'id'     => (int) $row['id'],
                'title'  => (string) $row['title'],
                'image'  => (string) $row['image'],
                'link'   => (string) $row['link'],
                'end_at' => (int) $row['end_at'],
            ];
        }
        return $out;
    } catch (\Throwable $e) {
        return [];
    }
}

/**
 * 我的广告列表（投放页）
 */
function ecod_ads_mine(int $uid, int $limit = 20): array
{
    if ($uid <= 0) {
        return [];
    }
    try {
        $db = \Typecho\Db::get();
        $rows = $db->fetchAll(
            $db->select()->from('table.ecod_ads')->where('uid = ?', $uid)
                ->order('id', \Typecho\Db::SORT_DESC)->limit(max(1, min(50, $limit)))
        );
        $out = [];
        foreach ($rows ?: [] as $row) {
            $out[] = [
                'id'     => (int) $row['id'],
                'slot'   => (string) $row['slot'],
                'title'  => (string) $row['title'],
                'image'  => (string) $row['image'],
                'link'   => (string) $row['link'],
                'days'   => (int) $row['days'],
                'status' => (string) $row['status'],
                'order_no' => (string) $row['order_no'],
                'end_at' => (int) $row['end_at'],
                'note'   => (string) $row['note'],
                'created'=> (int) $row['created'],
            ];
        }
        return $out;
    } catch (\Throwable $e) {
        return [];
    }
}

/** 广告状态中文文案 */
function ecod_ads_status_label(string $status): string
{
    $map = [
        'pending_pay' => '待支付',
        'pending'     => '待审核',
        'active'      => '投放中',
        'rejected'    => '未通过',
        'off'         => '已下线',
    ];
    if ($status === 'active') {
        return '投放中';
    }
    return $map[$status] ?? $status;
}
