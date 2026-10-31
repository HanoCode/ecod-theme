<?php
/**
 * Ecod Theme · 付费与会员可见
 *
 * 三层分离（设计见 docs/paywall-design.md）：
 *   门槛 Gate        文章 pay_mode / 片段 [hide type=pay|vip]
 *   权益 Entitlement  uid + kind + target（唯一一行，判定只读这里）
 *   交易 Order        金额与状态；写权益只发生在「结算 / 后台授予 / 卡密核销」
 *
 * 关键约定：
 *   - 金额一律整数分，元只在输入输出时转换。
 *   - expire_at = 0 表示永久；GET 请求绝不改写过期数据。
 *   - 所有门禁共用 ecod_pay_access()，避免「页面已购、短代码仍锁」。
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
 * 独立入口（api.php / pay-admin.php）没有加载 functions.php，这里补一个同名实现
 *
 * 主题上下文里 functions.php 的 ecod_option 在编译期已声明，不会走到这里。
 */
if (!function_exists('ecod_option')) {
    function ecod_option(string $key, $default = '')
    {
        try {
            $options = \Utils\Helper::options();
        } catch (\Throwable $e) {
            return $default;
        }
        return isset($options->{$key}) && $options->{$key} !== '' ? $options->{$key} : $default;
    }
}

/* ------------------------------------------------------------------
 * 基础：开关 / 金额 / 字段
 * ---------------------------------------------------------------- */

/** 总开关 */
function ecod_pay_enabled(): bool
{
    return (string) ecod_option('EPay', 'off') === 'on';
}

/** 货币符号 */
function ecod_pay_currency(): string
{
    $cur = trim((string) ecod_option('EPayCurrency', '¥'));
    return $cur !== '' ? $cur : '¥';
}

/**
 * 元 → 分
 *
 * 接受 "9.9" / "9.90" / "12" / 9.9；非法值返回 null（区别于 0 = 免费）。
 */
function ecod_pay_cents($yuan): ?int
{
    if (is_int($yuan)) {
        return $yuan * 100;
    }
    $raw = trim((string) $yuan);
    if ($raw === '' || !preg_match('/^\d+(?:\.\d{1,2})?$/', $raw)) {
        return null;
    }
    return (int) round((float) $raw * 100);
}

/** 分 → 展示金额（9.90） */
function ecod_pay_money(int $cents): string
{
    return number_format(max(0, $cents) / 100, 2, '.', '');
}

/**
 * 读取文章付费字段（统一入口，字段名集中在这里）
 *
 * 返回原始字符串，不做类型转换；调用方按需用 ecod_pay_cents() 解析。
 */
function ecod_pay_fields($archive): array
{
    $out = [
        'mode'    => '',
        'price'   => '',
        'vip1'    => '',
        'vip2'    => '',
        'level'   => '',
        'days'    => '',
        'doc'     => '',
        'comment' => '',
    ];
    if (!$archive) {
        return $out;
    }

    try {
        $fields = $archive->fields ?? null;
    } catch (\Throwable $e) {
        return $out;
    }
    if (!$fields) {
        return $out;
    }

    $map = [
        'mode'    => 'pay_mode',
        'price'   => 'pay_price',
        'vip1'    => 'pay_vip1',
        'vip2'    => 'pay_vip2',
        'level'   => 'pay_level',
        'days'    => 'pay_days',
        'doc'     => 'pay_doc',
        'comment' => 'pay_comment',
    ];
    foreach ($map as $key => $field) {
        try {
            if (isset($fields->{$field})) {
                $out[$key] = trim((string) $fields->{$field});
            }
        } catch (\Throwable $e) {
            /* 单个字段取不到不影响其它字段 */
        }
    }

    return $out;
}

/** 文章门槛模式：free / vip / paid / paid_vip */
function ecod_pay_mode($archive): string
{
    if (!ecod_pay_enabled()) {
        return 'free';
    }
    $mode = strtolower(ecod_pay_fields($archive)['mode']);
    return in_array($mode, ['vip', 'paid', 'paid_vip'], true) ? $mode : 'free';
}

/** 文章要求的会员等级（vip / paid_vip 生效） */
function ecod_pay_required_level($archive): int
{
    $level = (int) ecod_pay_fields($archive)['level'];
    return $level === 2 ? 2 : 1;
}

/** 是否允许单独购买 */
function ecod_pay_purchasable(string $mode): bool
{
    return $mode === 'paid' || $mode === 'paid_vip';
}

/**
 * 会员档位表（名称 + 周期价）
 *
 * 配置项：EVip1Name / EVip1Month / EVip1Quarter / EVip1Year / EVip1Forever
 *        EVip2Name / EVip2Month / …（同上）
 *
 * @return array<int, array{level:int,name:string,plans:array<string,array{key:string,label:string,days:int,cents:int}>}>
 */
function ecod_pay_vip_tiers(): array
{
    $planDefs = [
        'month'   => ['月度', 30],
        'quarter' => ['季度', 90],
        'year'    => ['年度', 365],
        'forever' => ['永久', 0],
    ];

    $tiers = [];
    foreach ([1, 2] as $level) {
        $prefix = 'EVip' . $level;
        $plans = [];
        foreach ($planDefs as $key => [$label, $days]) {
            $cents = ecod_pay_cents((string) ecod_option($prefix . ucfirst($key), ''));
            if ($cents === null || $cents <= 0) {
                continue;
            }
            $plans[$key] = [
                'key'   => $key,
                'label' => $label,
                'days'  => $days,
                'cents' => $cents,
            ];
        }
        $name = trim((string) ecod_option($prefix . 'Name', ''));
        if ($name === '') {
            $name = $level === 1 ? '会员' : '超级会员';
        }
        $tiers[$level] = ['level' => $level, 'name' => $name, 'plans' => $plans];
    }

    return $tiers;
}

/** 会员档位名称 */
function ecod_pay_level_name(int $level): string
{
    $tiers = ecod_pay_vip_tiers();
    return $tiers[$level]['name'] ?? ($level >= 2 ? '超级会员' : '会员');
}

/**
 * 档位固定文案与权益清单（会员购买页 / 个人中心会员 tab 共用）
 *
 * caps 键为二期预留：把权益变成真判定时按 level 查能力表。
 *
 * @return array<int, array{desc:string,perks:string[],caps:string[]}>
 */
function ecod_pay_tier_copy(): array
{
    return [
        1 => [
            'desc'  => '畅读会员专属内容，升级之路的第一步。',
            'perks' => ['会员专属内容随时读', '评论等级与标识不受影响', '一次开通，全站生效'],
            'caps'  => [],
        ],
        2 => [
            'desc'  => '包含会员全部权益，可读更高等级的内容。',
            'perks' => ['含会员档全部权益', '超级会员专属内容可见', '专属标识与优先支持'],
            'caps'  => [],
        ],
    ];
}

/* ------------------------------------------------------------------
 * 权益
 * ---------------------------------------------------------------- */

/**
 * 读取一条权益（含过期判断）
 *
 * @return array{level:int,expire_at:int,active:bool}|null
 */
function ecod_pay_entitlement(int $uid, string $kind, int $target): ?array
{
    if ($uid <= 0) {
        return null;
    }

    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('level', 'expire_at', 'start_at')->from('table.ecod_entitlements')
                ->where('uid = ?', $uid)->where('kind = ?', $kind)->where('target = ?', $target)
                ->limit(1)
        );
    } catch (\Throwable $e) {
        return null;
    }
    if (!$row) {
        return null;
    }

    $expire = (int) $row['expire_at'];
    return [
        'level'     => (int) $row['level'],
        'expire_at' => $expire,
        'active'    => $expire === 0 || $expire > time(),
    ];
}

/**
 * 当前会员状态（请求内静态缓存）
 *
 * @return array{level:int,expire_at:int,active:bool,name:string}
 */
function ecod_pay_vip(?int $uid = null): array
{
    if ($uid === null) {
        $uid = ecod_pay_current_uid();
    }
    if ($uid <= 0) {
        return ['level' => 0, 'expire_at' => 0, 'active' => false, 'name' => ''];
    }

    $cache = $GLOBALS['__ecod_pay_vip_cache'] ?? [];
    if (isset($cache[$uid])) {
        return $cache[$uid];
    }

    $row = ecod_pay_entitlement($uid, 'vip', 0);
    $level = $row ? (int) $row['level'] : 0;
    $active = $row ? (bool) $row['active'] : false;

    $out = [
        'level'     => $active ? $level : 0,
        'expire_at' => $row ? (int) $row['expire_at'] : 0,
        'active'    => $active,
        'name'      => $active && $level > 0 ? ecod_pay_level_name($level) : '',
    ];
    $GLOBALS['__ecod_pay_vip_cache'][$uid] = $out;

    return $out;
}

/**
 * 写权益（结算 / 授予 / 核销 三个入口共用）
 *
 * 规则：
 *   - 已有永久权益不会再被改成限时；
 *   - 续期在「剩余时间」上累加，而不是从今天重算；
 *   - 会员等级取较大值（升级不会因买低价档降级）。
 *
 * @param array{uid:int,kind:string,target:int,level?:int,days?:int,source?:string,source_id?:string} $spec
 */
function ecod_pay_grant(array $spec): bool
{
    $uid = (int) ($spec['uid'] ?? 0);
    $kind = (string) ($spec['kind'] ?? '');
    $target = (int) ($spec['target'] ?? 0);
    $level = (int) ($spec['level'] ?? 0);
    $days = (int) ($spec['days'] ?? 0);
    $source = (string) ($spec['source'] ?? 'admin');
    $sourceId = (string) ($spec['source_id'] ?? '');

    if ($uid <= 0 || !in_array($kind, ['post', 'vip'], true)) {
        return false;
    }

    $now = time();
    $add = $days > 0 ? $days * 86400 : 0;

    try {
        $db = \Typecho\Db::get();
        $table = 'table.ecod_entitlements';
        $row = $db->fetchRow(
            $db->select('id', 'level', 'expire_at')->from($table)
                ->where('uid = ?', $uid)->where('kind = ?', $kind)->where('target = ?', $target)
                ->limit(1)
        );

        if (!$row) {
            try {
                $db->query($db->insert($table)->rows([
                    'uid'       => $uid,
                    'kind'      => $kind,
                    'target'    => $target,
                    'level'     => $level,
                    'source'    => $source,
                    'source_id' => $sourceId,
                    'start_at'  => $now,
                    'expire_at' => $add > 0 ? $now + $add : 0,
                    'created'   => $now,
                ]));
            } catch (\Throwable $e) {
                /* 并发下唯一键冲突：退化为更新 */
                $db->query($db->update($table)->rows([
                    'level'     => max($level, 0),
                    'expire_at' => $add > 0 ? $now + $add : 0,
                    'source'    => $source,
                    'source_id' => $sourceId,
                ])->where('uid = ?', $uid)->where('kind = ?', $kind)->where('target = ?', $target));
            }

            ecod_pay_cache_flush();
            return true;
        }

        $oldExpire = (int) $row['expire_at'];
        if ($add === 0) {
            $expire = 0;                                   /* 永久 */
        } elseif ($oldExpire === 0) {
            $expire = 0;                                   /* 原本就是永久 */
        } else {
            $expire = max($now, $oldExpire) + $add;        /* 续期在剩余时间上累加 */
        }

        $db->query($db->update($table)->rows([
            'level'     => max((int) $row['level'], $level),
            'expire_at' => $expire,
            'source'    => $source,
            'source_id' => $sourceId,
        ])->where('id = ?', (int) $row['id']));

        ecod_pay_cache_flush();
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

/** 收回权益（退款 / 后台撤销） */
function ecod_pay_revoke(int $uid, string $kind, int $target = 0): bool
{
    if ($uid <= 0) {
        return false;
    }
    try {
        $db = \Typecho\Db::get();
        $db->query($db->delete('table.ecod_entitlements')
            ->where('uid = ?', $uid)->where('kind = ?', $kind)->where('target = ?', $target));
        ecod_pay_cache_flush();
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

/** 缓存清理：结算 / 授予 / 核销后同一请求内重新判定 */
function ecod_pay_cache_flush(): void
{
    $GLOBALS['__ecod_pay_vip_cache'] = [];
    $GLOBALS['__ecod_pay_access_cache'] = [];
}

/* ------------------------------------------------------------------
 * 访客与判定
 * ---------------------------------------------------------------- */

/** 当前登录 uid（无登录返回 0） */
function ecod_pay_current_uid(): int
{
    static $uid = null;
    if ($uid !== null) {
        return $uid;
    }
    $uid = 0;
    try {
        $user = \Widget\User::alloc();
        if ($user->hasLogin()) {
            $uid = (int) $user->uid;
        }
    } catch (\Throwable $e) {
        $uid = 0;
    }
    return $uid;
}

/** 当前访客是否管理团队 */
function ecod_pay_is_staff(): bool
{
    static $staff = null;
    if ($staff !== null) {
        return $staff;
    }
    $staff = false;
    try {
        $user = \Widget\User::alloc();
        if ($user->hasLogin() && ($user->pass('administrator', true) || $user->pass('editor', true))) {
            $staff = true;
        }
    } catch (\Throwable $e) {
        $staff = false;
    }
    return $staff;
}

/**
 * 单一判定入口
 *
 * @return array{
 *   enabled:bool,mode:string,allow:bool,reason:string,
 *   price:int,origin:int,vip_price:?int,level:int,expire_at:int,
 *   purchased:bool,vip_level:int,cid:int,purchasable:bool
 * }
 */
function ecod_pay_access($archive = null, ?int $uid = null): array
{
    $cid = (int) ($archive->cid ?? 0);
    $uid = $uid === null ? ecod_pay_current_uid() : $uid;
    $key = $cid . ':' . $uid;
    if (isset($GLOBALS['__ecod_pay_access_cache'][$key])) {
        return $GLOBALS['__ecod_pay_access_cache'][$key];
    }

    $mode = ecod_pay_mode($archive);
    $out = [
        'enabled'     => ecod_pay_enabled(),
        'mode'        => $mode,
        'allow'       => true,
        'reason'      => 'free',
        'price'       => 0,
        'origin'      => 0,
        'vip_price'   => null,
        'level'       => 0,
        'expire_at'   => 0,
        'purchased'   => false,
        'vip_level'   => 0,
        'cid'         => $cid,
        'purchasable' => false,
    ];

    if (!$out['enabled']) {
        $out['reason'] = 'disabled';
        return $GLOBALS['__ecod_pay_access_cache'][$key] = $out;
    }
    if ($mode === 'free' || $cid <= 0) {
        return $GLOBALS['__ecod_pay_access_cache'][$key] = $out;
    }

    $price = ecod_pay_price($archive, $uid);
    $out['price'] = $price['amount'];
    $out['origin'] = $price['origin'];
    $out['vip_price'] = $price['vip_price'];
    $out['level'] = ecod_pay_required_level($archive);
    $out['purchasable'] = $price['purchasable'];

    /* 管理团队直通 */
    if (ecod_pay_is_staff()) {
        $out['reason'] = 'admin';
        return $GLOBALS['__ecod_pay_access_cache'][$key] = $out;
    }

    /* 作者直通（可关） */
    if ($uid > 0 && (string) ecod_option('EPayAuthorFree', 'on') === 'on'
        && (int) ($archive->authorId ?? 0) === $uid) {
        $out['reason'] = 'author';
        return $GLOBALS['__ecod_pay_access_cache'][$key] = $out;
    }

    if ($uid <= 0) {
        $out['allow'] = false;
        $out['reason'] = 'login';
        return $GLOBALS['__ecod_pay_access_cache'][$key] = $out;
    }

    /* 单篇权益 */
    $ent = ecod_pay_entitlement($uid, 'post', $cid);
    if ($ent && $ent['active']) {
        $out['allow'] = true;
        $out['reason'] = 'purchased';
        $out['purchased'] = true;
        $out['expire_at'] = $ent['expire_at'];
        return $GLOBALS['__ecod_pay_access_cache'][$key] = $out;
    }
    $expiredAt = $ent ? (int) $ent['expire_at'] : 0;

    /* 会员权益：paid 模式明确「仅单篇购买」，会员也不自动解锁 */
    $vip = ecod_pay_vip($uid);
    $out['vip_level'] = (int) $vip['level'];
    if (in_array($mode, ['vip', 'paid_vip'], true) && $vip['active'] && (int) $vip['level'] >= $out['level']) {
        $out['allow'] = true;
        $out['reason'] = 'vip';
        $out['expire_at'] = (int) $vip['expire_at'];
        return $GLOBALS['__ecod_pay_access_cache'][$key] = $out;
    }

    /* 评论解锁（设计稿 guaqi-reference §F2）：本篇留过一条过审评论即放行。
       读时验证 comments 表，不落自定义凭证——评论被删 / 转待审自动回收；
       vip 模式是会员专属内容，不走评论解锁。 */
    if ($mode !== 'vip' && ecod_pay_comment_unlocked($archive, $cid, $uid)) {
        $out['allow'] = true;
        $out['reason'] = 'comment';
        return $GLOBALS['__ecod_pay_access_cache'][$key] = $out;
    }

    $out['allow'] = false;
    $out['reason'] = $expiredAt > 0 ? 'expired' : 'none';
    $out['expire_at'] = $expiredAt;

    return $GLOBALS['__ecod_pay_access_cache'][$key] = $out;
}

/**
 * 价格计算（下单与展示共用同一套规则）
 *
 * - origin：原价（分）
 * - vip_price：当前等级可享的会员价（仅当 < 原价时有效）
 * - vip_hint：给游客看的「最低会员价」，用于「会员 ¥5.00」提示
 *
 * @return array{amount:int,origin:int,vip_price:?int,vip_hint:?int,purchasable:bool}
 */
function ecod_pay_price($archive, ?int $uid = null): array
{
    $fields = ecod_pay_fields($archive);
    $mode = ecod_pay_mode($archive);

    $origin = ecod_pay_cents($fields['price']);
    $origin = $origin === null ? 0 : $origin;

    $vip1 = ecod_pay_cents($fields['vip1']);
    $vip2 = ecod_pay_cents($fields['vip2']);

    $uid = $uid === null ? ecod_pay_current_uid() : $uid;
    $level = $uid > 0 ? (int) ecod_pay_vip($uid)['level'] : 0;

    /** @var array<int, int> $tiers 等级 => 会员价（分） */
    $tiers = [];
    if ($vip1 !== null) {
        $tiers[1] = $vip1;
    }
    if ($vip2 !== null) {
        $tiers[2] = $vip2;
    }

    /* 结算价：只给真会员，且按等级从高到低取；超级会员没配 vip2 时退到 vip1 */
    $effective = null;
    foreach ([2, 1] as $need) {
        if ($level >= $need && isset($tiers[$need])) {
            $effective = $tiers[$need];
            break;
        }
    }

    /* 展示提示价：非会员看最低一档，用来告诉游客「开会员能省多少」 */
    $vipHint = $level >= 1 ? $effective : ($tiers ? min($tiers) : null);

    /* 会员价必须低于原价才生效（0 = 会员免费），否则不参与结算 */
    if ($effective !== null && $effective !== 0 && $effective >= $origin) {
        $effective = null;
    }

    $purchasable = ecod_pay_purchasable($mode) && $origin > 0;
    $amount = $purchasable ? ($effective !== null ? $effective : $origin) : 0;

    return [
        'amount'      => $amount,
        'origin'      => $origin,
        'vip_price'   => $effective,
        'vip_hint'    => $vipHint,
        'purchasable' => $purchasable,
    ];
}

/* ------------------------------------------------------------------
 * 订单与结算
 * ---------------------------------------------------------------- */

/** 订单号：EC + 年月日时分秒 + 6 位随机（22 位，唯一索引兜底） */
function ecod_pay_order_no(): string
{
    try {
        $rand = (string) random_int(100000, 999999);
    } catch (\Throwable $e) {
        $rand = substr((string) mt_rand(100000, 999999), 0, 6);
    }
    return 'EC' . date('YmdHis') . $rand;
}

/**
 * 创建（或复用）待支付订单
 *
 * 复用规则：同 uid + kind + target + 金额 + 通道，15 分钟内且仍是 pending，
 * 直接返回旧单，避免用户连点生成一堆垃圾订单。
 *
 * @param array{uid:int,kind:string,target:int,title:string,amount:int,days?:int,level?:int,channel?:string} $spec
 * @return array{ok:bool,order_no?:string,order?:array,error?:string}
 */
function ecod_pay_order_create(array $spec): array
{
    $uid = (int) ($spec['uid'] ?? 0);
    $kind = (string) ($spec['kind'] ?? '');
    $target = (int) ($spec['target'] ?? 0);
    $amount = (int) ($spec['amount'] ?? 0);
    $channel = (string) ($spec['channel'] ?? 'manual');
    $days = (int) ($spec['days'] ?? 0);
    $level = (int) ($spec['level'] ?? 0);
    $title = mb_substr(trim((string) ($spec['title'] ?? '')), 0, 180, 'UTF-8');

    if ($uid <= 0 || !in_array($kind, ['post', 'vip', 'ad'], true)) {
        return ['ok' => false, 'error' => '参数不完整'];
    }
    if ($amount <= 0) {
        return ['ok' => false, 'error' => '金额无效'];
    }

    try {
        $db = \Typecho\Db::get();

        $recent = $db->fetchRow(
            $db->select('order_no', 'amount', 'created')->from('table.ecod_orders')
                ->where('uid = ?', $uid)->where('kind = ?', $kind)->where('target = ?', $target)
                ->where('status = ?', 'pending')->where('channel = ?', $channel)
                ->order('id', \Typecho\Db::SORT_DESC)->limit(1)
        );
        if ($recent && (int) $recent['amount'] === $amount && time() - (int) $recent['created'] < 900) {
            return ['ok' => true, 'order_no' => (string) $recent['order_no'], 'reused' => true];
        }

        $orderNo = ecod_pay_order_no();
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $ua = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 240, 'UTF-8');

        $db->query($db->insert('table.ecod_orders')->rows([
            'order_no' => $orderNo,
            'uid'      => $uid,
            'kind'     => $kind,
            'target'   => $target,
            'level'    => $level,
            'title'    => $title,
            'amount'   => $amount,
            'currency' => 'CNY',
            'days'     => $days,
            'status'   => 'pending',
            'channel'  => $channel,
            'created'  => time(),
            'ip'       => $ip,
            'ua'       => $ua,
            'remark'   => '',
        ]));

        return ['ok' => true, 'order_no' => $orderNo];
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => '创建订单失败'];
    }
}

/** 按订单号读取订单 */
function ecod_pay_order_get(string $orderNo): ?array
{
    $orderNo = trim($orderNo);
    if ($orderNo === '') {
        return null;
    }
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow($db->select()->from('table.ecod_orders')->where('order_no = ?', $orderNo)->limit(1));
        return $row ? (array) $row : null;
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * 结算：把 pending 改成 paid 并发权益
 *
 * 幂等靠 `UPDATE … WHERE status='pending'` + 影响行数：
 * 重复回调、并发回调、前端轮询都不会重复发权益。
 *
 * @param int $paidAmount 支付通道回传金额（分）；-1 表示不校验（后台手动确认）
 * @return array{ok:bool,status:string,message:string}
 */
function ecod_pay_settle(string $orderNo, string $tradeNo = '', string $channel = '', int $paidAmount = -1): array
{
    $order = ecod_pay_order_get($orderNo);
    if (!$order) {
        return ['ok' => false, 'status' => 'missing', 'message' => '订单不存在'];
    }
    if ((string) $order['status'] === 'paid') {
        return ['ok' => true, 'status' => 'paid', 'message' => '已处理'];
    }
    if ((string) $order['status'] !== 'pending') {
        return ['ok' => false, 'status' => (string) $order['status'], 'message' => '订单状态不可结算'];
    }
    if ($paidAmount >= 0 && $paidAmount !== (int) $order['amount']) {
        return ['ok' => false, 'status' => 'amount', 'message' => '金额不符'];
    }

    /* trade_no 防重放：同一笔渠道交易号只允许挂在一笔订单上 */
    $tradeNo = trim($tradeNo);
    if ($tradeNo !== '') {
        try {
            $db = \Typecho\Db::get();
            $dupe = $db->fetchRow(
                $db->select('order_no')->from('table.ecod_orders')
                    ->where('trade_no = ?', $tradeNo)
                    ->where('order_no <> ?', $orderNo)
                    ->where('status = ?', 'paid')
                    ->limit(1)
            );
        } catch (\Throwable $e) {
            $dupe = null;
        }
        if ($dupe) {
            return ['ok' => false, 'status' => 'replay', 'message' => '交易号已被其它订单使用'];
        }
    }

    try {
        $db = \Typecho\Db::get();
        $rows = ['status' => 'paid', 'paid_at' => time()];
        if ($tradeNo !== '') {
            $rows['trade_no'] = mb_substr($tradeNo, 0, 60, 'UTF-8');
        }
        if ($channel !== '') {
            $rows['channel'] = mb_substr($channel, 0, 20, 'UTF-8');
        }

        /* Typecho 的 Db::query() 对 UPDATE 直接返回影响行数，没有 affectedRows() 方法 */
        $affected = $db->query(
            $db->update('table.ecod_orders')->rows($rows)
                ->where('order_no = ?', $orderNo)->where('status = ?', 'pending')
        );

        if ((int) $affected !== 1) {
            /* 别的进程刚结算完，直接当成功 */
            $fresh = ecod_pay_order_get($orderNo);
            return ['ok' => $fresh && (string) $fresh['status'] === 'paid',
                'status' => $fresh ? (string) $fresh['status'] : 'unknown', 'message' => '已处理'];
        }
    } catch (\Throwable $e) {
        return ['ok' => false, 'status' => 'error', 'message' => '结算失败'];
    }

    /* 发权益：post / vip 走权益表；ad 走广告结算（支付完成 ≠ 上线，还差审核） */
    if ((string) $order['kind'] === 'ad') {
        if (!function_exists('ecod_ads_on_paid')) {
            require_once __DIR__ . '/../site/ads.php';
        }
        $granted = ecod_ads_on_paid((int) $order['target']);
    } else {
        $granted = ecod_pay_grant([
            'uid'       => (int) $order['uid'],
            'kind'      => (string) $order['kind'],
            'target'    => (int) $order['target'],
            'level'     => (int) $order['level'],
            'days'      => (int) $order['days'],
            'source'    => $channel !== '' ? 'order' : 'order',
            'source_id' => $orderNo,
        ]);
    }

    /* 开会员送积分（guaqi 式拉新钩子）：仅 vip 订单、积分系统开启、配置大于 0 时发放 */
    if ($granted && (string) $order['kind'] === 'vip') {
        $ecodVipGift = max(0, (int) ecod_option('ECrVipGift', 0));
        if ($ecodVipGift > 0) {
            if (!function_exists('ecod_credits_add')) {
                require_once __DIR__ . '/../user/credits.php';
            }
            ecod_credits_add((int) $order['uid'], $ecodVipGift, 'vip_gift', $orderNo, '开通会员赠送');
        }
    }

    /* 推广返佣：被推荐人的订单结算成功，推荐人按比例得 Credit（见 core/user/referral.php） */
    if ($granted && function_exists('ecod_ref_pay_commission')) {
        ecod_ref_pay_commission($order);
    }

    if (!$granted) {
        return ['ok' => false, 'status' => 'grant', 'message' => '权益写入失败'];
    }

    /* 订单上顺手记下权益到期时间，方便后台查看 */
    try {
        $expire = 0;
        $days = (int) $order['days'];
        if ($days > 0) {
            $expire = time() + $days * 86400;
        }
        $db = \Typecho\Db::get();
        $db->query($db->update('table.ecod_orders')->rows(['expire_at' => $expire])->where('order_no = ?', $orderNo));
    } catch (\Throwable $e) {
        /* 非关键字段 */
    }

    return ['ok' => true, 'status' => 'paid', 'message' => 'ok'];
}

/** 关闭超时未支付订单（15 分钟） */
function ecod_pay_order_gc(int $uid = 0): void
{
    try {
        $db = \Typecho\Db::get();
        $update = $db->update('table.ecod_orders')->rows(['status' => 'closed'])
            ->where('status = ?', 'pending')->where('created < ?', time() - 900);
        if ($uid > 0) {
            $update->where('uid = ?', $uid);
        }
        $db->query($update);
    } catch (\Throwable $e) {
        /* 忽略 */
    }
}

/** 用户订单列表 */
function ecod_pay_orders(int $uid, int $limit = 20, int $offset = 0): array
{
    if ($uid <= 0) {
        return [];
    }
    try {
        $db = \Typecho\Db::get();
        $rows = $db->fetchAll(
            $db->select()->from('table.ecod_orders')->where('uid = ?', $uid)
                ->order('id', \Typecho\Db::SORT_DESC)->offset(max(0, $offset))->limit(max(1, min(100, $limit)))
        );
        return array_map(static function ($row) {
            return (array) $row;
        }, $rows ?: []);
    } catch (\Throwable $e) {
        return [];
    }
}

/* ------------------------------------------------------------------
 * 渲染：试读 / 付费卡 / 角标
 * ---------------------------------------------------------------- */

/**
 * 试读切片不能把成对短代码切开
 *
 * 切点落在 `[hide type=pay]…` 内部时，闭合标签被丢掉，整段标签会以裸文本
 * 出现在正文里（短代码管线匹配不到 `[/hide]` 就不会处理）。这里把落单的
 * 开头标签连同其后内容一起截掉，宁可少给几行试读。
 */
function ecod_pay_teaser_balance(string $html): string
{
    static $tags = ['hide', 'reply', 'note', 'fold', 'tabs', 'timeline', 'download', 'gallery', 'vip1', 'vip2', 'poll'];

    for ($guard = 0; $guard < 12; $guard++) {
        $cut = null;
        foreach ($tags as $tag) {
            if (!preg_match_all('#\[/?' . $tag . '\b[^\]]*\]#i', $html, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            /** @var array<int, int> $stack 尚未闭合的开标签位置 */
            $stack = [];
            foreach ($m[0] as $hit) {
                if (strncmp($hit[0], '[/', 2) === 0) {
                    array_pop($stack);
                } else {
                    $stack[] = (int) $hit[1];
                }
            }
            if ($stack) {
                $pos = (int) end($stack);
                $cut = $cut === null ? $pos : min($cut, $pos);
            }
        }
        if ($cut === null) {
            break;
        }
        $html = rtrim(substr($html, 0, $cut));
    }

    /* 兜底：切点落在标签中间（如 "[hi"）时把残片去掉 */
    return rtrim((string) preg_replace('#\[[^\[\]]*$#', '', $html));
}

/**
 * 试读片段
 *
 * 优先 `<!--paymore-->` 精确切分；否则按可见字数在段落边界截断
 * （绝不切断标签，避免半截 HTML 破坏版面与短代码配对）。
 */
function ecod_pay_teaser_html(string $content, int $chars = 80): string
{
    $content = (string) $content;

    $pos = stripos($content, '<!--paymore-->');
    if ($pos !== false) {
        return ecod_pay_teaser_balance(rtrim(substr($content, 0, $pos)));
    }
    if ($chars <= 0) {
        return '';
    }

    /* 按段落累加可见字数，超过阈值就停 */
    $parts = preg_split('#(</p>|</h[1-6]>|</li>|</blockquote>|\n\n)#i', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!$parts) {
        return '';
    }

    $out = '';
    $count = 0;
    $total = count($parts);
    $stopped = false;
    for ($i = 0; $i < $total; $i++) {
        $chunk = $parts[$i];
        $out .= $chunk;
        $count += mb_strlen(trim(strip_tags($chunk)), 'UTF-8');
        if ($count >= $chars) {
            $stopped = true;
            break;
        }
    }

    /* 全文都没超过阈值：说明整篇都会被当成试读，必须留下最后一块锁着，
       否则几百字的付费短文等于免费送出去 */
    if (!$stopped && $out !== '') {
        $blocks = preg_split('#(?<=</p>|</h[1-6]>|</li>|</blockquote>)#i', $out, -1, PREG_SPLIT_NO_EMPTY);
        if ($blocks && count($blocks) > 1) {
            array_pop($blocks);
            $out = implode('', $blocks);
        } elseif (!$blocks) {
            $out = '';
        }
    }

    /* 没有任何块级标签时，按字面截断并补省略号 */
    if (strpos($out, '<') === false) {
        $plain = trim(strip_tags($content));
        return ecod_pay_teaser_balance(mb_substr($plain, 0, $chars, 'UTF-8') . '…');
    }

    return ecod_pay_teaser_balance($out);
}

/** 收银台地址（页面不存在时落到会员页 / 空串） */
function ecod_pay_cashier_url(array $query = []): string
{
    $url = '';
    if (function_exists('ecod_pages_url')) {
        $url = ecod_pages_url('cashier');
    }
    /* 独立入口（api.php）没加载路由表，Router::url 不抛异常而是返回 '#'，
       必须当「解析失败」处理，否则回跳会落到 主题目录/#?order_no=… */
    if ($url === '' || str_contains($url, '#')) {
        /* 兜底：按模板直查收银台页面，拼 index.php 形式地址（rewrite 开关下均有效） */
        try {
            $db = \Typecho\Db::get();
            $row = $db->fetchRow(
                $db->select('slug')->from('table.contents')
                    ->where('template = ?', 'page-cashier.php')->where('type = ?', 'page')
                    ->where('status = ?', 'publish')->limit(1)
            );
            $url = $row ? ecod_pay_site_url('index.php/' . $row['slug'] . '.html') : '';
        } catch (\Throwable $e) {
            $url = '';
        }
    }
    if ($url === '') {
        return '';
    }
    return $query ? $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($query) : $url;
}

/** 会员购买页地址 */
function ecod_pay_vip_url(): string
{
    if (function_exists('ecod_pages_url')) {
        $url = ecod_pages_url('vip');
        if ($url !== '') {
            return $url;
        }
    }
    $cashier = ecod_pay_cashier_url(['kind' => 'vip']);
    return $cashier !== '' ? $cashier : '';
}

/** 前台登录地址（带回到原页） */
function ecod_pay_login_url(string $referer = ''): string
{
    $login = function_exists('ecod_pages_url') ? ecod_pages_url('login') : '';
    if ($login === '') {
        return '';
    }
    $referer = trim($referer);
    return $referer !== '' ? $login . (strpos($login, '?') === false ? '?' : '&') . 'referer=' . rawurlencode($referer) : $login;
}

/**
 * 列表角标（付费 / 会员）
 *
 * 与「置顶 / 系列 / 新 / 密码」共用 .ecod_card__badges 容器；
 * 会员用 --member，避免与「系列」的 --vip 撞名。
 */
function ecod_pay_badge($archive): string
{
    $mode = ecod_pay_mode($archive);
    if ($mode === 'free') {
        return '';
    }
    if ($mode === 'vip') {
        return '<span class="ecod_badge ecod_badge--member" title="会员可读">' . ecod_icon('star', 10) . '会员</span>';
    }

    $price = ecod_pay_price($archive);
    $label = $price['origin'] > 0 ? ecod_pay_money($price['origin']) : '付费';
    return '<span class="ecod_badge ecod_badge--pay" title="付费阅读">¥' . $label . '</span>';
}

/**
 * 付费卡
 *
 * @param array $state   ecod_pay_access() 的返回值
 * @param bool  $compact 精简版（只留标题、价格与按钮），用于「文末再放一张」
 */
function ecod_pay_box($archive, array $state, bool $compact = false): string
{
    if (!$state['enabled'] || $state['mode'] === 'free') {
        return '';
    }

    $cid = (int) $state['cid'];
    $mode = (string) $state['mode'];
    $owned = in_array($state['reason'], ['purchased', 'vip', 'admin', 'author', 'comment'], true);
    $doc = ecod_pay_fields($archive)['doc'];

    /* 已解锁：只留一条极简状态，不占版面 */
    if ($owned) {
        $expire = (int) $state['expire_at'];
        $text = $state['reason'] === 'comment'
            ? '已解锁 · 凭本篇过审评论'
            : ($state['reason'] === 'vip'
                ? '会员权益 · ' . ($expire > 0 ? '至 ' . date('Y-m-d', $expire) : '长期有效')
                : ($state['reason'] === 'purchased'
                    ? '已购买 · ' . ($expire > 0 ? '至 ' . date('Y-m-d', $expire) : '永久可读')
                    : '管理视角 · 已解锁'));
        $icon = $state['reason'] === 'admin' || $state['reason'] === 'author' ? 'eye' : 'check';
        return '<div class="ecod_pay ecod_pay--owned" data-ecod-pay data-state="owned">'
            . '<span class="ecod_pay__owned">' . ecod_icon($icon, 13) . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>'
            . '</div>';
    }

    /* 展示层分流（设计稿 guaqi-reference §F2 / §F6）：
       评论解锁卡优先（有免费解锁途径时直给转化路径），其次付费资源卡；
       compact（both 位置的第二张）不重复大卡 */
    if (ecod_pay_comment_enabled($archive)) {
        return $compact ? '' : ecod_pay_comment_card($archive, $state);
    }
    if (!$compact) {
        $ecodResourceCard = ecod_pay_resource_card($archive, $state);
        if ($ecodResourceCard !== '') {
            return $ecodResourceCard;
        }
    }

    $price = ecod_pay_price($archive);
    $currency = ecod_pay_currency();
    $vipUrl = ecod_pay_vip_url();
    $cashier = ecod_pay_cashier_url(['post' => $cid]);
    $buyHref = $cashier !== '' ? $cashier : $vipUrl;
    $loginHref = ecod_pay_login_url((string) ($archive->permalink ?? ''));

    $isGuest = ecod_pay_current_uid() <= 0;
    $stateName = $state['reason'] === 'expired' ? 'expired' : 'locked';

    $title = $mode === 'vip'
        ? '开通' . ecod_pay_level_name((int) $state['level']) . '后阅读'
        : '解锁全文';

    $perks = [];
    if ($mode === 'vip') {
        $perks[] = '全站付费内容都能读';
    } else {
        $perks[] = $state['mode'] === 'paid_vip' ? '买断后可永久回看' : '一次购买，长期有效';
        if ($state['mode'] === 'paid_vip') {
            $perks[] = ecod_pay_level_name((int) $state['level']) . '及以上可直接阅读';
        }
    }
    /* 卖点按实际可用通道生成，不误导用户 */
    $channels = array_keys(ecod_pay_channel_list());
    if (in_array('epay', $channels, true)) {
        $perks[] = '支持支付宝 / 微信';
    } elseif (in_array('manual', $channels, true)) {
        $perks[] = '人工确认收款，付款后开通';
    }
    if (in_array('card', $channels, true)) {
        $perks[] = '支持卡密兑换';
    }

    $html = '<section class="ecod_pay' . ($compact ? ' ecod_pay--mini' : '') . '" data-ecod-pay data-cid="' . $cid . '" data-state="' . $stateName . '">'
        . '<div class="ecod_pay__head">'
        . '<span class="ecod_pay__flag">' . ($mode === 'vip' ? '会员' : '付费') . '</span>'
        . '<b class="ecod_pay__title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</b>'
        . '</div>';

    if (!$compact && trim((string) $doc) !== '') {
        $html .= '<p class="ecod_pay__doc">' . nl2br(htmlspecialchars(trim((string) $doc), ENT_QUOTES, 'UTF-8')) . '</p>';
    }

    if (!$compact) {
        $html .= '<ul class="ecod_pay__perks">';
        foreach ($perks as $perk) {
            $html .= '<li>' . ecod_icon('check', 12) . htmlspecialchars($perk, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        $html .= '</ul>';
    }

    $html .= '<div class="ecod_pay__foot">';

    if ($price['purchasable']) {
        $html .= '<div class="ecod_pay__price">'
            . '<em>' . htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') . '</em>'
            . '<b>' . ecod_pay_money($price['amount']) . '</b>';
        if ($price['origin'] > $price['amount']) {
            $html .= '<s>' . htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') . ecod_pay_money($price['origin']) . '</s>';
        } elseif ($price['vip_hint'] !== null && $price['vip_hint'] < $price['amount']) {
            /* 非会员：主价是原价，旁边提示开会员能便宜多少 */
            $html .= '<i class="ecod_pay__viphint">'
                . ecod_pay_level_name(1) . '价 '
                . htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') . ecod_pay_money((int) $price['vip_hint'])
                . '</i>';
        }
        $html .= '</div>';
    } elseif ($price['vip_hint'] !== null) {
        $html .= '<div class="ecod_pay__price ecod_pay__price--vip">'
            . '<span>' . ecod_pay_level_name((int) $state['level']) . '价</span>'
            . '<em>' . htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') . '</em>'
            . '<b>' . ecod_pay_money((int) $price['vip_hint']) . '</b>'
            . '</div>';
    }

    $html .= '<div class="ecod_pay__acts">';

    if ($isGuest) {
        $html .= '<a class="ecod_pay__btn" href="' . htmlspecialchars($loginHref !== '' ? $loginHref : '#', ENT_QUOTES, 'UTF-8') . '">'
            . ecod_icon('user', 14) . '登录后解锁</a>';
    } elseif ($price['purchasable'] && $buyHref !== '') {
        $html .= '<a class="ecod_pay__btn" href="' . htmlspecialchars($buyHref, ENT_QUOTES, 'UTF-8') . '">'
            . ecod_icon('zap', 14) . ($stateName === 'expired' ? '重新购买' : '立即购买') . '</a>';
    }

    if ($vipUrl !== '' && $mode !== 'paid') {
        $html .= '<a class="ecod_pay__vip" href="' . htmlspecialchars($vipUrl, ENT_QUOTES, 'UTF-8') . '">'
            . ecod_icon('star', 13) . '开通' . ecod_pay_level_name((int) $state['level']) . '</a>';
    }

    $html .= '</div></div>';

    if ($stateName === 'expired') {
        $html .= '<p class="ecod_pay__note">你的阅读权益已于 ' . date('Y-m-d', (int) $state['expire_at']) . ' 到期。</p>';
    } elseif ($isGuest) {
        $html .= '<p class="ecod_pay__note">购买后权益绑定账号，换设备登录也能继续阅读。</p>';
    }

    return $html . '</section>';
}

/* ------------------------------------------------------------------
 * 评论解锁（F2）与付费资源卡（F6）
 * ---------------------------------------------------------------- */

/** 本篇是否启用「评论解锁」途径（全局开关 + 文章级 pay_comment=1 + 付费模式） */
function ecod_pay_comment_enabled($archive): bool
{
    if (!ecod_pay_enabled() || (string) ecod_option('EPayCommentUnlock', 'off') !== 'on') {
        return false;
    }
    if (!in_array(ecod_pay_mode($archive), ['paid', 'paid_vip'], true)) {
        return false;
    }
    return trim((string) ecod_pay_fields($archive)['comment']) === '1';
}

/**
 * 当前访客在本篇是否已有过审评论（读时验证，无凭证落地）
 *
 * 登录用户按 ownerId 匹配；游客用 Typecho 的「记住邮箱」cookie 匹配——
 * 邮箱只在本人提交评论时写入，他人无法冒用。
 */
function ecod_pay_comment_unlocked($archive, ?int $cid = null, ?int $uid = null): bool
{
    if (!ecod_pay_comment_enabled($archive)) {
        return false;
    }
    $cid = $cid ?? (int) ($archive->cid ?? 0);
    if ($cid <= 0) {
        return false;
    }

    try {
        $db = \Typecho\Db::get();
        if ($uid === null) {
            $uid = ecod_pay_current_uid();
        }
        $select = $db->select('coid')->from('table.comments')
            ->where('cid = ?', $cid)->where('status = ?', 'approved')->limit(1);

        if ($uid > 0) {
            $row = $db->fetchRow($select->where('ownerId = ?', $uid));
        } else {
            $mail = trim((string) ($_COOKIE['__typecho_remember_mail'] ?? ''));
            if ($mail === '' || !str_contains($mail, '@')) {
                return false;
            }
            $row = $db->fetchRow($select->where('mail = ?', $mail));
        }
        return !empty($row);
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * 评论解锁卡（F2）：未解锁且本篇启用评论解锁时替代默认付费卡
 *
 * 不内嵌表单——评论提交必须走原生路由（防垃圾令牌 / Turnstile / 时间戳
 * 都在评论区表单里），这里只做引导：平滑滚动到评论区并聚焦输入框。
 */
function ecod_pay_comment_card($archive, array $state): string
{
    $cid = (int) $state['cid'];
    $price = ecod_pay_price($archive);
    $currency = ecod_pay_currency();
    $cashier = ecod_pay_cashier_url(['post' => $cid]);
    $loginHref = ecod_pay_login_url((string) ($archive->permalink ?? ''));
    $isGuest = ecod_pay_current_uid() <= 0;

    $html = '<section class="ecod_pay ecod_payc" data-ecod-pay data-cid="' . $cid . '" data-state="locked">'
        . '<div class="ecod_payc__lock">' . ecod_icon('lock', 19) . '</div>'
        . '<b class="ecod_payc__title">评论后解锁全文</b>'
        . '<p class="ecod_payc__sub">本文为付费内容，发表一条评论并通过审核即可阅读全文</p>';

    if ($price['purchasable']) {
        $html .= '<div class="ecod_payc__price">'
            . '<em>' . htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') . '</em>'
            . '<b>' . ecod_pay_money($price['amount']) . '</b>';
        if ($price['origin'] > $price['amount']) {
            $html .= '<s>' . htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') . ecod_pay_money($price['origin']) . '</s>';
        }
        $html .= '<i>或评论解锁</i></div>';
    }

    $html .= '<div class="ecod_payc__acts">';
    if ($isGuest) {
        $html .= '<a class="ecod_pay__btn" href="' . htmlspecialchars($loginHref !== '' ? $loginHref : '#comments', ENT_QUOTES, 'UTF-8') . '">'
            . ecod_icon('user', 14) . '登录后参与评论</a>';
    } else {
        $html .= '<button type="button" class="ecod_pay__btn ecod_payc__go" data-ecod-paycomment>'
            . ecod_icon('comment', 14) . '去发表评论</button>';
    }
    if ($price['purchasable'] && $cashier !== '') {
        $html .= '<a class="ecod_pay__vip" href="' . htmlspecialchars($cashier, ENT_QUOTES, 'UTF-8') . '">'
            . ecod_icon('zap', 13) . '直接购买</a>';
    }
    $html .= '</div>'
        . '<p class="ecod_pay__note">评论通过审核后本篇自动解锁，删除评论或评论转待审会重新上锁。</p>'
        . '</section>';

    return $html;
}

/**
 * 付费资源卡元数据（F6）：pay_meta_* 自定义字段，全部可缺省
 *
 * @return array<string, string> label => 值（空值项已剔除）
 */
function ecod_pay_resource_meta($archive): array
{
    $map = [
        'size'    => '资源大小',
        'updated' => '更新时间',
        'sys'     => '适用系统',
        'lang'    => '语言',
        'refund'  => '是否可退款',
        'term'    => '有效期',
    ];
    $out = [];
    try {
        $fields = $archive->fields ?? null;
        if ($fields) {
            foreach ($map as $key => $label) {
                $val = trim((string) ($fields->{'pay_meta_' . $key} ?? ''));
                if ($val !== '') {
                    $out[$label] = $val;
                }
            }
        }
    } catch (\Throwable $e) {
        /* 取不到就当没填 */
    }
    return $out;
}

/**
 * 付费资源卡（F6，参考 zibll 付费下载卡）：purchase 门禁的展示层升级
 *
 * 启用条件：EPayResourceCard 开 + 单篇可购买 + 有预览图或任一元数据；
 * 否则回落默认付费卡（ecod_pay_box 主体），老文章零成本。
 * 只改展示层，门禁判定（ecod_pay_access）零改动。
 */
function ecod_pay_resource_card($archive, array $state): string
{
    if ((string) ecod_option('EPayResourceCard', 'off') !== 'on') {
        return '';
    }
    if (!in_array($state['mode'], ['paid', 'paid_vip'], true) || !$state['purchasable']) {
        return '';
    }
    $meta = ecod_pay_resource_meta($archive);
    $preview = '';
    try {
        $raw = trim((string) ($archive->fields->pay_preview ?? ''));
        if (preg_match('#^https?://#i', $raw)) {
            $preview = $raw;
        }
    } catch (\Throwable $e) {
    }
    if ($preview === '' && !$meta) {
        return '';
    }

    $cid = (int) $state['cid'];
    $price = ecod_pay_price($archive);
    $currency = ecod_pay_currency();
    $cashier = ecod_pay_cashier_url(['post' => $cid]);
    $loginHref = ecod_pay_login_url((string) ($archive->permalink ?? ''));
    $isGuest = ecod_pay_current_uid() <= 0;
    $doc = trim((string) ecod_pay_fields($archive)['doc']);

    $html = '<section class="ecod_pay ecod_payrc" data-ecod-pay data-cid="' . $cid . '" data-state="locked">';

    if ($preview !== '') {
        $html .= '<a class="ecod_payrc__thumb" href="' . htmlspecialchars($preview, ENT_QUOTES, 'UTF-8') . '" data-ecod-lightbox>'
            . '<img src="' . htmlspecialchars($preview, ENT_QUOTES, 'UTF-8') . '" alt="" loading="lazy" decoding="async" data-ecod-img>'
            . '<span class="ecod_payrc__zoom">' . ecod_icon('image', 12) . '预览</span></a>';
    }

    $html .= '<div class="ecod_payrc__body">';

    $badges = '<span class="ecod_payrc__flag">付费资源</span>';
    if (isset($meta['是否可退款']) && in_array($meta['是否可退款'], ['可', '支持', '是', '1'], true)) {
        $badges .= '<span class="ecod_payrc__badge">支持退款</span>';
        unset($meta['是否可退款']);
    }
    if ($price['vip_hint'] !== null && $price['vip_hint'] < $price['origin']) {
        $badges .= '<span class="ecod_payrc__badge">' . ecod_pay_level_name(1) . '免费</span>';
    }
    $html .= '<div class="ecod_payrc__badges">' . $badges . '</div>';

    $html .= '<div class="ecod_payrc__price">'
        . '<em>' . htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') . '</em>'
        . '<b>' . ecod_pay_money($price['amount']) . '</b>';
    if ($price['origin'] > $price['amount']) {
        $html .= '<s>' . htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') . ecod_pay_money($price['origin']) . '</s>';
    }
    $html .= '</div>';

    if ($doc !== '') {
        $html .= '<p class="ecod_payrc__doc">' . nl2br(htmlspecialchars($doc, ENT_QUOTES, 'UTF-8')) . '</p>';
    }

    $html .= '<div class="ecod_payrc__acts">';
    if ($isGuest) {
        $html .= '<a class="ecod_pay__btn" href="' . htmlspecialchars($loginHref !== '' ? $loginHref : '#', ENT_QUOTES, 'UTF-8') . '">'
            . ecod_icon('user', 14) . '登录后购买</a>';
    } elseif ($cashier !== '') {
        $html .= '<a class="ecod_payrc__buy" href="' . htmlspecialchars($cashier, ENT_QUOTES, 'UTF-8') . '">'
            . ecod_icon('zap', 14) . '立即购买</a>';
    }
    $html .= '<span class="ecod_payrc__sold">' . ecod_icon('eye', 12) . ecod_num_short(ecod_views($archive)) . ' 浏览</span></div>';

    if ($meta) {
        $html .= '<dl class="ecod_payrc__meta">';
        foreach ($meta as $label => $val) {
            $html .= '<div><dt>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</dt><dd>'
                . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '</dd></div>';
        }
        $html .= '</dl>';
    }

    $html .= '</div></section>';
    return $html;
}

/* ------------------------------------------------------------------
 * 摘要 / Feed 兜底
 * ---------------------------------------------------------------- */

/** 未授权时的摘要文案 */
function ecod_pay_excerpt_text($archive): string
{
    $doc = trim((string) ecod_pay_fields($archive)['doc']);
    if ($doc !== '') {
        return $doc;
    }
    return ecod_pay_mode($archive) === 'vip' ? '会员专属内容，开通会员后可阅读全文。' : '付费内容，购买后可阅读全文。';
}

/**
 * 锁定态下改写归档描述
 *
 * Typecho 的 `Archive::singleHandle()` 在 `require_once functions.php` **之前**
 * 就把 `$archiveDescription` 设成了 `plainExcerpt`（Archive.php:1705），那时
 * 主题的任何钩子都还没注册，`excerptEx` 拦不住。所以在 themeInit 里直接覆盖：
 * 否则 `<meta name="description">` 与 OG 卡会泄漏试读之外的正文。
 */
function ecod_pay_describe($archive): void
{
    if (!ecod_pay_enabled() || !is_object($archive)) {
        return;
    }
    try {
        if (!$archive->is('single')) {
            return;
        }
        $state = ecod_pay_access($archive);
        if ($state['mode'] === 'free' || $state['allow']) {
            return;
        }
        if (method_exists($archive, 'setArchiveDescription')) {
            $archive->setArchiveDescription(ecod_pay_excerpt_text($archive));
        }
    } catch (\Throwable $e) {
        /* 判定失败保持原描述，避免整页 500 */
    }
}

/**
 * 摘要兜底：未授权时不给任何正文
 *
 * 挂 `Widget\Base\Contents->excerptEx`，覆盖 Feed、搜索与其它直接读
 * `excerpt` 的消费方（文章页的 meta 走 ecod_pay_describe()）。
 */
function ecod_pay_excerpt_guard($excerpt, $archive = null)
{
    if (!is_object($archive) || !ecod_pay_enabled()) {
        return $excerpt;
    }
    $state = ecod_pay_access($archive);
    if ($state['mode'] === 'free' || $state['allow']) {
        return $excerpt;
    }
    return ecod_pay_excerpt_text($archive);
}

/**
 * Feed / 第三方消费方的正文兜底
 *
 * 挂 \Widget\Base\Contents->contentEx：文章页正文在此之前已被缓存，
 * 这里主要挡住 RSS 与其它直接读 content 的地方。
 */
function ecod_pay_feed_guard($content, $archive = null)
{
    if (!is_object($archive) || !ecod_pay_enabled()) {
        return $content;
    }
    $state = ecod_pay_access($archive);
    if ($state['mode'] === 'free' || $state['allow']) {
        return $content;
    }

    $link = htmlspecialchars((string) ($archive->permalink ?? ''), ENT_QUOTES, 'UTF-8');
    $tip = htmlspecialchars(ecod_pay_excerpt_text($archive), ENT_QUOTES, 'UTF-8');

    return '<p>' . $tip . '</p><p><a href="' . $link . '">前往文章页解锁全文</a></p>';
}
