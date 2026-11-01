<?php

/**
 * Ecod Theme · 授权自助桥接（插件桥接 + 购买 / 发码 / 换绑）
 *
 * @package ecod-theme
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

require_once __DIR__ . '/license.php';

/** 插件桥接入口路径 */
function ecod_license_plugin_path(): string
{
    return rtrim((string) __TYPECHO_ROOT_DIR__, '/') . '/usr/plugins/EcodLicenser/api.php';
}

/** 加载插件桥接（幂等） */
function ecod_license_plugin_ready(): bool
{
    static $loaded = false;
    static $ready = false;
    if ($loaded) {
        return $ready;
    }
    $loaded = true;
    $path = ecod_license_plugin_path();
    if (!is_file($path)) {
        return $ready = false;
    }
    try {
        require_once $path;
    } catch (\Throwable $e) {
        return $ready = false;
    }
    return $ready = function_exists('ecodl_ss_ready');
}

/** 封装：ready */
function ecod_license_ss_ready(): bool
{
    return ecod_license_plugin_ready() && ecodl_ss_ready();
}

/** 从订单 remark 取绑定域名（新购单 remark = domain:<host>） */
function ecod_license_order_domain(array $order): string
{
    $remark = (string) ($order['remark'] ?? '');
    if (strpos($remark, 'domain:') !== 0) {
        return '';
    }
    return ecod_license_host_normalize(substr($remark, strlen('domain:')));
}

/** 售价（元）：优先读作者端插件设置，回落主题存量配置（兼容未升级插件） */
function ecod_license_price(string $edition): string
{
    if (ecod_license_ss_ready()) {
        $v = ecodl_ss_price($edition === 'team' ? 'team' : 'pro');
        if ($v !== '') {
            return $v;
        }
    }
    return trim((string) ecod_option($edition === 'team' ? 'ELicTeam' : 'ELicPro', ''));
}

/** 新授权码默认换绑次数：优先作者端插件设置 */
function ecod_license_rebind_default(): int
{
    if (ecod_license_ss_ready()) {
        return max(0, ecodl_ss_rebind_default());
    }
    return max(1, (int) ecod_option('ELicRebind', ECOD_LIC_REBIND_LIMIT_DEFAULT));
}

/** 封装：check_domain */
function ecod_license_ss_check_domain(string $domain, int $uid): array
{
    if (!ecod_license_plugin_ready()) {
        return ['ok' => false, 'message' => '作者端未开启自助发码', 'domain' => $domain];
    }
    return ecodl_ss_check_domain($domain, $uid);
}

/** 封装：issue */
function ecod_license_ss_issue(array $spec): array
{
    if (!ecod_license_plugin_ready()) {
        return ['ok' => false, 'message' => '作者端未开启自助发码', 'key' => [], 'file' => ''];
    }
    return ecodl_ss_issue($spec);
}

/** 封装：upgrade */
function ecod_license_ss_upgrade(int $keyId, int $uid, string $edition): array
{
    if (!ecod_license_plugin_ready()) {
        return ['ok' => false, 'message' => '作者端未开启自助发码', 'file' => ''];
    }
    return ecodl_ss_upgrade($keyId, $uid, $edition);
}

/** 封装：rebind */
function ecod_license_ss_rebind(int $keyId, int $uid, string $domain): array
{
    if (!ecod_license_plugin_ready()) {
        return ['ok' => false, 'message' => '作者端未开启自助发码', 'file' => '', 'rebind_used' => 0, 'rebind_limit' => 0];
    }
    return ecodl_ss_rebind($keyId, $uid, $domain);
}

/** 封装：keys */
function ecod_license_ss_keys(int $uid): array
{
    if (!ecod_license_plugin_ready()) {
        return [];
    }
    return ecodl_ss_keys($uid);
}

/** 封装：key_file */
function ecod_license_ss_key_file(int $keyId, int $uid): string
{
    if (!ecod_license_plugin_ready()) {
        return '';
    }
    return ecodl_ss_key_file($keyId, $uid);
}

/**
 * 订单结算后的授权签发（幂等：按 order_no）
 *
 * 由 core/pay/pay.php 的 settle 在 kind='theme' 分支调用。
 */
function ecod_license_settle_order(array $order): void
{
    if (!ecod_license_ss_ready()) {
        return;
    }
    $uid = (int) ($order['uid'] ?? 0);
    $orderNo = (string) ($order['order_no'] ?? '');
    if ($uid <= 0 || $orderNo === '') {
        return;
    }

    $level = (int) ($order['level'] ?? 1);
    $edition = $level >= 2 ? 'team' : 'pro';
    $remark = (string) ($order['remark'] ?? '');

    /* 升级单：remark = 'upgrade:<keyId>' */
    if (strpos($remark, 'upgrade:') === 0) {
        $keyId = (int) substr($remark, strlen('upgrade:'));
        if ($keyId > 0) {
            ecod_license_ss_upgrade($keyId, $uid, $edition);
        }
        return;
    }

    /* 新购单：remark = 'domain:<host>' */
    $domain = '';
    if (strpos($remark, 'domain:') === 0) {
        $domain = substr($remark, strlen('domain:'));
    }
    if ($domain === '') {
        return;
    }

    $rebindLimit = ecod_license_rebind_default();
    $res = ecod_license_ss_issue([
        'uid'           => $uid,
        'domain'        => $domain,
        'edition'       => $edition,
        'order_no'      => $orderNo,
        'product'       => 'theme',
        'rebind_limit'  => $rebindLimit,
    ]);

    /* 付款成功但发码失败：登记 pending 占位行，会员中心显示「待处理」，
       作者解决域名冲突后再次取码会自动补发，避免「付了钱什么都没有」 */
    if (empty($res['ok']) && function_exists('ecodl_ss_issue_pending')) {
        ecodl_ss_issue_pending([
            'uid'          => $uid,
            'domain'       => $domain,
            'edition'      => $edition,
            'order_no'     => $orderNo,
            'product'      => 'theme',
            'rebind_limit' => $rebindLimit,
        ], (string) ($res['message'] ?? ''));
    }
}
