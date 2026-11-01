<?php
/**
 * Ecod Theme · 支付通道适配层
 *
 * 三个通道，接口一致：
 *   manual  线下 / 转账 / 朋友代付 —— 后台「确认收款」后结算
 *   card    卡密 —— 事务内核销，卡密只存 SHA-256
 *   epay    易支付（Zibll 同款公开协议）—— 跳转支付 + 异步验签回调
 *
 * 通道只负责「怎么收钱」，一律不碰权益：结算统一走 ecod_pay_settle()。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

interface EcodPayChannel
{
    /** 通道标识 */
    public function id(): string;

    /** 通道名称（收银台展示） */
    public function name(): string;

    /**
     * 发起支付
     *
     * @param array $order ecod_orders 的一行
     * @param array $opts  渠道内细分选项（epay：type = alipay|wxpay）
     * @return array{mode:string,url?:string,qr?:string,html?:string,tip?:string}
     */
    public function create(array $order, array $opts = []): array;

    /**
     * 校验回调 / 核销请求
     *
     * @param array $request 原始请求参数
     * @return array{ok:bool,order_no?:string,trade_no?:string,amount?:int,message?:string}
     */
    public function verify(array $request): array;
}

/* ------------------------------------------------------------------
 * 通用
 * ---------------------------------------------------------------- */

/** 当前启用的通道配置 */
function ecod_pay_epay_config(): array
{
    return [
        'url'  => rtrim(trim((string) ecod_option('EPayEpayUrl', '')), '/'),
        'pid'  => trim((string) ecod_option('EPayEpayPid', '')),
        'key'  => trim((string) ecod_option('EPayEpayKey', '')),
        'type' => (string) ecod_option('EPayEpayType', 'alipay'),
    ];
}

/** 易支付是否配置完整 */
function ecod_pay_epay_ready(): bool
{
    $cfg = ecod_pay_epay_config();
    return $cfg['url'] !== '' && $cfg['pid'] !== '' && $cfg['key'] !== '';
}

/** 默认通道（epay 未配置时自动降级 manual） */
function ecod_pay_default_channel(): string
{
    $channel = (string) ecod_option('EPayChannel', 'manual');
    if (function_exists('ecod_license_allows') && !ecod_license_allows('card')) {
        if ($channel === 'card') {
            $channel = 'manual';
        }
    }
    if ($channel === 'epay' && !ecod_pay_epay_ready()) {
        return 'manual';
    }
    return in_array($channel, ['manual', 'card', 'epay'], true) ? $channel : 'manual';
}

/** 通道实例 */
function ecod_pay_channel(?string $id = null): EcodPayChannel
{
    $id = $id ?: ecod_pay_default_channel();
    switch ($id) {
        case 'card':
            return new EcodPayCardChannel();
        case 'epay':
            return ecod_pay_epay_ready() ? new EcodPayEpayChannel() : new EcodPayManualChannel();
        default:
            return new EcodPayManualChannel();
    }
}

/** 收银台可选的通道列表 */
function ecod_pay_channel_list(): array
{
    $list = [
        'manual' => ['id' => 'manual', 'name' => '联系站长 / 线下付款'],
        'card'   => ['id' => 'card', 'name' => '卡密兑换'],
    ];
    if (function_exists('ecod_license_allows') && !ecod_license_allows('card')) {
        unset($list['card']);
    }
    if (ecod_pay_epay_ready()) {
        $list['epay'] = ['id' => 'epay', 'name' => '支付宝 / 微信'];
    }
    $default = ecod_pay_default_channel();
    if (isset($list[$default])) {
        $list[$default]['default'] = true;
    }
    return $list;
}

/** 站内地址（用于 notify_url / return_url） */
function ecod_pay_site_url(string $path = ''): string
{
    try {
        $options = \Widget\Options::alloc();
        $base = rtrim((string) $options->siteUrl, '/');
    } catch (\Throwable $e) {
        $base = '';
    }
    return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

/**
 * 回调入口地址：主题目录下的 api.php
 *
 * 不能用站点根 —— 根目录没有 api.php，notify/return 会 404（实测发现）。
 * 也刻意不走 ecod_asset()：ECdn 配置会把 URL 指到 CDN，
 * 支付回调必须直达源站，不能被 CDN 劫持域名。
 */
function ecod_pay_api_url(): string
{
    try {
        $options = \Widget\Options::alloc();
        return rtrim((string) $options->themeUrl, '/') . '/api.php';
    } catch (\Throwable $e) {
        return ecod_pay_site_url('usr/themes/ecod-theme/api.php');
    }
}

/* ------------------------------------------------------------------
 * 手动通道
 * ---------------------------------------------------------------- */

class EcodPayManualChannel implements EcodPayChannel
{
    /** 通道标识：manual（无线上回调，订单靠后台确认收款收口） */
    public function id(): string
    {
        return 'manual';
    }

    /** 收银台展示名 */
    public function name(): string
    {
        return '联系站长 / 线下付款';
    }

    /** 渲染线下付款说明：优先用设置项 EPayManualDoc 的文案 */
    public function create(array $order, array $opts = []): array
    {
        $doc = trim((string) ecod_option('EPayManualDoc', ''));
        $tip = $doc !== ''
            ? nl2br(htmlspecialchars($doc, ENT_QUOTES, 'UTF-8'))
            : '订单已提交，请联系站长并备注订单号；确认收款后会自动为你开通。';

        return [
            'mode' => 'manual',
            'tip'  => $tip,
            'html' => '<div class="ecod_cashier__manual">' . $tip . '</div>',
        ];
    }

    /** 手动通道没有回调可验，结算只由后台「确认收款」触发 */
    public function verify(array $request): array
    {
        return ['ok' => false, 'message' => 'manual 通道不支持回调'];
    }
}

/* ------------------------------------------------------------------
 * 卡密通道
 * ---------------------------------------------------------------- */

class EcodPayCardChannel implements EcodPayChannel
{
    /** 通道标识：card */
    public function id(): string
    {
        return 'card';
    }

    /** 收银台展示名 */
    public function name(): string
    {
        return '卡密兑换';
    }

    /** 渲染卡密输入框（卡密由站长在后台生成发放） */
    public function create(array $order, array $opts = []): array
    {
        return [
            'mode' => 'card',
            'html' => '<div class="ecod_cashier__card">'
                . '<p>输入卡密即可开通，卡密由站长发放。</p>'
                . '</div>',
            'tip'  => '输入卡密后立即开通，无需等待。',
        ];
    }

    /** 校验并核销卡密：转发 ecod_pay_card_redeem()，由它负责幂等与状态流转 */
    public function verify(array $request): array
    {
        $code = trim((string) ($request['code'] ?? ''));
        $uid = (int) ($request['uid'] ?? 0);
        if ($code === '' || $uid <= 0) {
            return ['ok' => false, 'message' => '请填写卡密'];
        }

        $result = ecod_pay_card_redeem($code, $uid);
        return $result;
    }
}

/** 卡密规范化：去空格、转大写 */
function ecod_pay_card_normalize(string $code): string
{
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    return $code;
}

/** 卡密哈希（只存这个，明文只在生成时返回一次） */
function ecod_pay_card_hash(string $code): string
{
    return hash('sha256', ecod_pay_card_normalize($code) . '|ecod-pay-card');
}

/**
 * 批量生成卡密
 *
 * @param array{count:int,kind:string,target?:int,level?:int,days?:int,batch?:string} $spec
 * @return array{ok:bool,codes?:string[],batch?:string,error?:string}
 */
function ecod_pay_card_batch(array $spec): array
{
    $count = max(1, min(200, (int) ($spec['count'] ?? 1)));
    $kind = (string) ($spec['kind'] ?? 'vip');
    $target = (int) ($spec['target'] ?? 0);
    $level = (int) ($spec['level'] ?? 1);
    $days = (int) ($spec['days'] ?? 0);
    $batch = trim((string) ($spec['batch'] ?? ''));
    if ($batch === '') {
        $batch = date('Ymd-His');
    }
    if (!in_array($kind, ['post', 'vip'], true)) {
        return ['ok' => false, 'error' => '类型无效'];
    }

    try {
        $db = \Typecho\Db::get();
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => '数据库不可用'];
    }

    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        try {
            $raw = strtoupper(bin2hex(random_bytes(10)));   /* 20 位十六进制 */
        } catch (\Throwable $e) {
            $raw = strtoupper(substr(md5(uniqid('ecodcard', true)), 0, 20));
        }
        $code = 'EC' . $raw;
        try {
            $db->query($db->insert('table.ecod_pay_cards')->rows([
                'code_hash' => ecod_pay_card_hash($code),
                'batch'     => mb_substr($batch, 0, 30, 'UTF-8'),
                'kind'      => $kind,
                'target'    => $target,
                'level'     => $level,
                'days'      => $days,
                'status'    => 'unused',
                'created'   => time(),
            ]));
            $codes[] = $code;
        } catch (\Throwable $e) {
            /* 哈希碰撞（几乎不可能）：跳过这一张 */
            continue;
        }
    }

    return $codes ? ['ok' => true, 'codes' => $codes, 'batch' => $batch] : ['ok' => false, 'error' => '生成失败'];
}

/**
 * 核销卡密
 *
 * 顺序：查未用 → 抢占式 UPDATE（防并发重复核销）→ 写权益 → 补一条已支付订单留痕。
 *
 * @return array{ok:bool,kind?:string,target?:int,level?:int,days?:int,message:string}
 */
function ecod_pay_card_redeem(string $code, int $uid): array
{
    if (function_exists('ecod_license_allows') && !ecod_license_allows('card')) {
        return ['ok' => false, 'message' => '当前授权未包含卡密功能'];
    }
    $hash = ecod_pay_card_hash($code);
    if ($uid <= 0) {
        return ['ok' => false, 'message' => '请先登录'];
    }

    try {
        $db = \Typecho\Db::get();
        $card = $db->fetchRow($db->select()->from('table.ecod_pay_cards')->where('code_hash = ?', $hash)->limit(1));
        if (!$card) {
            return ['ok' => false, 'message' => '卡密不存在或已被使用'];
        }
        if ((string) $card['status'] !== 'unused') {
            return ['ok' => false, 'message' => '这张卡密已经被使用过了'];
        }

        /* Typecho 的 Db::query() 对 UPDATE 直接返回影响行数 */
        $affected = $db->query(
            $db->update('table.ecod_pay_cards')->rows([
                'status'  => 'used',
                'uid'     => $uid,
                'used_at' => time(),
            ])->where('id = ?', (int) $card['id'])->where('status = ?', 'unused')
        );
        if ((int) $affected !== 1) {
            return ['ok' => false, 'message' => '卡密刚刚被使用，请刷新重试'];
        }

        $kind = (string) $card['kind'];
        $target = (int) $card['target'];
        $days = (int) $card['days'];
        $level = (int) $card['level'];

        $granted = ecod_pay_grant([
            'uid'       => $uid,
            'kind'      => $kind,
            'target'    => $target,
            'level'     => $level,
            'days'      => $days,
            'source'    => 'card',
            'source_id' => (string) $card['batch'],
        ]);
        if (!$granted) {
            /* 权益没写进去就把卡退回未用，避免用户白损失 */
            $db->query($db->update('table.ecod_pay_cards')->rows(['status' => 'unused', 'uid' => 0, 'used_at' => 0])
                ->where('id = ?', (int) $card['id']));
            return ['ok' => false, 'message' => '开通失败，请稍后重试'];
        }

        /* 留痕订单：金额 0，状态直接 paid，便于后台统一查看 */
        try {
            $title = $kind === 'post'
                ? '卡密开通：' . ecod_pay_post_title($target)
                : '卡密开通：' . ecod_pay_level_name($level);
            $orderNo = ecod_pay_order_no();
            $db->query($db->insert('table.ecod_orders')->rows([
                'order_no' => $orderNo,
                'uid'      => $uid,
                'kind'     => $kind,
                'target'   => $target,
                'level'    => $level,
                'title'    => mb_substr($title, 0, 180, 'UTF-8'),
                'amount'   => 0,
                'currency' => 'CNY',
                'days'     => $days,
                'status'   => 'paid',
                'channel'  => 'card',
                'trade_no' => mb_substr((string) $card['batch'], 0, 60, 'UTF-8'),
                'created'  => time(),
                'paid_at'  => time(),
            ]));
            $db->query($db->update('table.ecod_pay_cards')->rows(['order_no' => $orderNo])->where('id = ?', (int) $card['id']));
        } catch (\Throwable $e) {
            /* 留痕失败不影响已开通的权益 */
        }

        return [
            'ok'      => true,
            'kind'    => $kind,
            'target'  => $target,
            'level'   => $level,
            'days'    => $days,
            'message' => '兑换成功',
        ];
    } catch (\Throwable $e) {
        return ['ok' => false, 'message' => '兑换失败，请稍后再试'];
    }
}

/** 文章标题（卡密留痕用） */
function ecod_pay_post_title(int $cid): string
{
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow($db->select('title')->from('table.contents')->where('cid = ?', $cid)->limit(1));
        return $row ? (string) $row['title'] : ('#' . $cid);
    } catch (\Throwable $e) {
        return '#' . $cid;
    }
}

/* ------------------------------------------------------------------
 * 易支付（Zibll 同款公开协议）
 * ---------------------------------------------------------------- */

class EcodPayEpayChannel implements EcodPayChannel
{
    /** 通道标识：epay（api.php 据此路由回调） */
    public function id(): string
    {
        return 'epay';
    }

    /** 收银台展示名 */
    public function name(): string
    {
        return '支付宝 / 微信';
    }

    /**
     * 易支付签名：参数按 key 升序 → `k=v&…` → 拼密钥 → md5 小写
     *
     * 剔除 sign / sign_type 与空值（与 Zibll、易支付官方 SDK 一致）。
     */
    public static function sign(array $params, string $key): string
    {
        unset($params['sign'], $params['sign_type']);
        $params = array_filter($params, static function ($v) {
            return $v !== '' && $v !== null;
        });
        ksort($params);
        $pairs = [];
        foreach ($params as $k => $v) {
            $pairs[] = $k . '=' . $v;
        }
        return md5(implode('&', $pairs) . $key);
    }

    /**
     * 组签名参数并跳易支付收银台；三项配置缺任一个则回退手动通道
     *
     * $opts['type'] 允许收银台按按钮细分 alipay / wxpay（缺省用后台配置）。
     */
    public function create(array $order, array $opts = []): array
    {
        $cfg = ecod_pay_epay_config();
        if ($cfg['url'] === '' || $cfg['pid'] === '' || $cfg['key'] === '') {
            return (new EcodPayManualChannel())->create($order, $opts);
        }

        $type = strtolower(trim((string) ($opts['type'] ?? '')));
        if (!in_array($type, ['alipay', 'wxpay'], true)) {
            $type = $cfg['type'] === 'wxpay' ? 'wxpay' : 'alipay';
        }

        $params = [
            'pid'          => $cfg['pid'],
            'type'         => $type,
            'out_trade_no' => (string) $order['order_no'],
            'notify_url'   => ecod_pay_api_url() . '?action=pay_notify&channel=epay',
            'return_url'   => ecod_pay_api_url() . '?action=pay_return&channel=epay',
            'name'         => mb_substr((string) $order['title'], 0, 60, 'UTF-8'),
            'money'        => ecod_pay_money((int) $order['amount']),
        ];
        $params['sign'] = self::sign($params, $cfg['key']);
        $params['sign_type'] = 'MD5';

        $url = $cfg['url'] . '/submit.php?' . http_build_query($params);

        return [
            'mode' => 'redirect',
            'url'  => $url,
            'tip'  => '正在跳转到支付页面…',
        ];
    }

    /** 回调验签 + 交易状态 + 金额解析，全部通过才返回订单信息供上层核账 */
    public function verify(array $request): array
    {
        $cfg = ecod_pay_epay_config();
        if ($cfg['key'] === '') {
            return ['ok' => false, 'message' => '通道未配置'];
        }

        /* 剔除我们自己的路由参数：易支付签名只覆盖它发送的业务参数，
           notify_url 里的 ?action=…&channel=… 属于站点路由，不参与签名 */
        $sign = (string) ($request['sign'] ?? '');
        unset($request['action'], $request['channel'], $request['sign'], $request['sign_type']);

        if ($sign === '' || !hash_equals(self::sign($request, $cfg['key']), strtolower($sign))) {
            return ['ok' => false, 'message' => '签名校验失败'];
        }

        $status = (string) ($request['trade_status'] ?? '');
        if ($status !== 'TRADE_SUCCESS' && $status !== 'TRADE_FINISHED') {
            return ['ok' => false, 'message' => '交易未成功'];
        }

        $orderNo = (string) ($request['out_trade_no'] ?? '');
        $money = (string) ($request['money'] ?? '');
        $cents = ecod_pay_cents($money);
        if ($orderNo === '' || $cents === null) {
            return ['ok' => false, 'message' => '参数不完整'];
        }

        return [
            'ok'       => true,
            'order_no' => $orderNo,
            'trade_no' => (string) ($request['trade_no'] ?? ''),
            'amount'   => $cents,
        ];
    }
}

/**
 * 易支付查单（mapi.php?act=order，Zibll 同款商户接口）
 *
 * 用于「回调丢失 / 用户付完没等回调就关页」的补单：前端轮询订单状态时
 * 顺手向通道查一次，查到已支付就走正常结算（金额校验在 settle 里做）。
 *
 * 返回：null = 查不到 / 网络 / 配置问题（不能当未支付）；
 *      ['paid' => false] = 通道明确说未支付；
 *      ['paid' => true, 'trade_no' => …, 'amount' => 分] = 已支付。
 */
function ecod_pay_epay_query(string $orderNo): ?array
{
    $cfg = ecod_pay_epay_config();
    if ($cfg['url'] === '' || $cfg['pid'] === '' || $cfg['key'] === '' || $orderNo === '') {
        return null;
    }

    $url = $cfg['url'] . '/mapi.php?act=order'
        . '&pid=' . rawurlencode($cfg['pid'])
        . '&key=' . rawurlencode($cfg['key'])
        . '&out_trade_no=' . rawurlencode($orderNo);

    try {
        $ctx = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'timeout'       => 8,
                'ignore_errors' => true,
                'header'        => "User-Agent: EcodPay/1.0\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
    } catch (\Throwable $e) {
        return null;
    }
    if (!is_string($body) || $body === '') {
        return null;
    }

    $json = json_decode($body, true);
    if (!is_array($json) || (int) ($json['code'] ?? -1) !== 1) {
        return null;
    }
    if ((int) ($json['status'] ?? 0) !== 1) {
        return ['paid' => false];
    }

    $cents = ecod_pay_cents((string) ($json['money'] ?? ''));
    return [
        'paid'     => true,
        'trade_no' => (string) ($json['trade_no'] ?? ''),
        'amount'   => $cents ?? -1,
    ];
}

/**
 * 查单节流包装：同一订单 6 秒内只真正查一次
 *
 * 前端每 2 秒轮询一次 pay_status，直接透传会把 mapi.php 打爆。
 * 用临时文件做跨请求节流（独立入口没有 APCu 可依赖）。
 */
function ecod_pay_epay_query_throttled(string $orderNo, int $ttl = 6): ?array
{
    if ($orderNo === '') {
        return null;
    }
    $file = sys_get_temp_dir() . '/ecod-epayq-' . md5($orderNo);
    if (is_file($file) && time() - (int) filemtime($file) < $ttl) {
        return null;
    }
    @touch($file);
    return ecod_pay_epay_query($orderNo);
}
