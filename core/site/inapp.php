<?php
/**
 * Ecod Theme · 内置浏览器引导
 *
 * 微信 / QQ / 企业微信 / 钉钉的内置 WebView 打开站点时，顶部没有地址栏、
 * 也没有「在新浏览器打开」，读者往往卡在这一步。这里按 UA 判定环境，
 * 由 components/header/inapp.php 渲染一个浮层（结构仿小红书：
 * 迷你浏览器示意图 + 标题说明 + 地址栏「复制链接」），引导改用系统浏览器。
 *
 * 后台：外观 → 内置浏览器引导（总开关 / 环境勾选 / 文案 / 弹出频率）。
 * 浮层挂在 #ecod-shell 之外（见 public/header.php），PJAX 换页不重建。
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
 * 当前 UA 命中的内置浏览器环境
 *
 * 返回 wechat / work / qq / ding，非内置浏览器返回空串。
 * 顺序有讲究：企业微信 UA 同时含 MicroMessenger，必须先判 wxwork；
 * QQ 浏览器（MQQBrowser）是独立浏览器，不能按内置环境处理，故只认「 QQ/x.y」。
 */
function ecod_inapp_env(): string
{
    static $env = null;
    if ($env !== null) {
        return $env;
    }
    $ua = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $env = '';
    if ($ua === '') {
        return $env;
    }
    if (strpos($ua, 'wxwork') !== false) {
        $env = 'work';
    } elseif (strpos($ua, 'micromessenger') !== false) {
        $env = 'wechat';
    } elseif (preg_match('#\bqq/[\d.]#', $ua)) {
        $env = 'qq';
    } elseif (strpos($ua, 'dingtalk') !== false) {
        $env = 'ding';
    }
    return $env;
}

/**
 * 环境元信息：名称、品牌图标、右上角菜单的叫法
 */
function ecod_inapp_envs(): array
{
    return [
        'wechat' => ['name' => '微信',     'icon' => 'wechat',  'menu' => '右上角「···」→ 在浏览器打开'],
        'work'   => ['name' => '企业微信', 'icon' => 'comment', 'menu' => '右上角「···」→ 在浏览器打开'],
        'qq'     => ['name' => 'QQ',       'icon' => 'qq',      'menu' => '右上角「···」→ 在浏览器打开'],
        'ding'   => ['name' => '钉钉',     'icon' => 'comment', 'menu' => '右上角「···」→ 在浏览器打开'],
    ];
}

/**
 * 引导浮层的渲染数据；不应弹出时返回 null
 *
 * 判定顺序：总开关 → UA 环境 → 该环境是否勾选。频率（每次会话 / 每天 / 每次都弹）
 * 由前端按 localStorage / sessionStorage 处理，服务端不做记忆。
 */
function ecod_inapp_guide(): ?array
{
    if (ecod_option('EInApp', 'on') !== 'on') {
        return null;
    }
    $env = ecod_inapp_env();
    if ($env === '') {
        return null;
    }
    $envs = ecod_inapp_envs();
    if (!isset($envs[$env])) {
        return null;
    }
    $flagMap = ['wechat' => 'EInAppWechat', 'work' => 'EInAppWork', 'qq' => 'EInAppQQ', 'ding' => 'EInAppDing'];
    if (ecod_option($flagMap[$env], 'on') !== 'on') {
        return null;
    }

    $title = trim((string) ecod_option('EInAppTitle', ''));
    $desc = trim((string) ecod_option('EInAppDesc', ''));
    $rate = (string) ecod_option('EInAppRate', 'session');
    if (!in_array($rate, ['session', 'day', 'always'], true)) {
        $rate = 'session';
    }

    /* 地址栏展示与复制用的都是当前地址：只取 Host + URI，过滤掉异常字符 */
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $host = preg_replace('/[^A-Za-z0-9\.\-:\[\]]/', '', $host) ?: '';
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $uri = preg_replace('/[\x00-\x20\x7f]/', '', $uri) ?: '/';
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;

    return [
        'env'   => $env,
        'name'  => $envs[$env]['name'],
        'icon'  => $envs[$env]['icon'],
        'menu'  => $envs[$env]['menu'],
        'title' => $title !== '' ? $title : '使用默认浏览器访问',
        'desc'  => $desc !== '' ? $desc : '在内置浏览器里功能受限，换到浏览器体验更完整',
        'rate'  => $rate,
        'host'  => $host,
        'url'   => ($https ? 'https://' : 'http://') . $host . $uri,
    ];
}
