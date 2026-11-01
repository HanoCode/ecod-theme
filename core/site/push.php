<?php
/**
 * Ecod Theme · 评论推送（微信 / 企业微信 / Server酱 / Bark / 通用 webhook）
 *
 * 新评论时把通知推到外部通道，与邮件提醒（core/site/mail.php）并行、互不依赖。
 *
 * 设计约束：
 *   1. 发送放在响应结束之后（fastcgi_finish_request），绝不拖慢评论提交；
 *   2. 单通道 8 秒超时 + 证书默认校验，失败只写 error_log，不影响评论流程；
 *   3. 不建表、无队列——推送是尽力而为的旁路通知，不做重试。
 *
 * 通道与密钥（后台「站长 → 邮件通知」）：
 *   webhook    EPushKey 为完整 URL，收到 POST JSON {title, body, url, site}
 *   wecom      EPushKey 为企业微信群机器人 webhook 地址
 *   serverchan EPushKey 为 Server酱 SendKey（sct.ftqq.com）
 *   bark       EPushKey 为设备 key（可含自建服务器完整地址）
 *   wxpusher   EPushKey 为 appToken，EPushExtra 为接收 UID（wxpusher.zjiecode.com）
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
 * 读推送配置（api.php 不加载 functions.php，这里自带兜底）
 */
function ecod_push_opt(string $key, string $default = ''): string
{
    try {
        $value = (string) (\Widget\Options::alloc()->{$key} ?? '');
        return trim($value) !== '' ? trim($value) : $default;
    } catch (\Throwable $e) {
        return $default;
    }
}

/**
 * 当前推送通道（off 时返回空）
 */
function ecod_push_channel(): string
{
    if (ecod_push_opt('EPush', 'off') !== 'on') {
        return '';
    }
    $channel = ecod_push_opt('EPushChannel', 'wecom');
    return in_array($channel, ['webhook', 'wecom', 'serverchan', 'bark', 'wxpusher'], true)
        ? $channel : '';
}

/* ------------------------------------------------------------------
 * 单通道发送
 * ---------------------------------------------------------------- */

/**
 * 发送 HTTP 请求（JSON 或表单），8 秒超时，证书默认校验
 *
 * @return array{ok:bool,msg:string}
 */
function ecod_push_http(string $url, array $payload, bool $form = false): array
{
    if (!preg_match('#^https?://#i', $url)) {
        return ['ok' => false, 'msg' => '地址非法'];
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'msg' => 'curl 初始化失败'];
    }

    if ($form) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    } else {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
    ]);

    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($resp === false) {
        $err = (string) curl_error($ch);
        return ['ok' => false, 'msg' => '请求失败: ' . $err];
    }

    if ($code < 200 || $code >= 300) {
        return ['ok' => false, 'msg' => 'HTTP ' . $code . ': ' . mb_substr((string) $resp, 0, 120, 'UTF-8')];
    }
    return ['ok' => true, 'msg' => mb_substr((string) $resp, 0, 120, 'UTF-8')];
}

/**
 * 按通道发送一条通知
 *
 * @param string $title 标题（一行）
 * @param string $body  正文（纯文本，多行）
 * @param string $url   跳转地址（可为空）
 * @return array{ok:bool,msg:string}
 */
function ecod_push_send(string $title, string $body, string $url = ''): array
{
    $channel = ecod_push_channel();
    $key = ecod_push_opt('EPushKey');
    $site = ecod_push_opt('siteUrl', '');

    if ($channel === '' || $key === '') {
        return ['ok' => false, 'msg' => '通道未配置'];
    }

    switch ($channel) {
        case 'webhook':
            return ecod_push_http($key, [
                'title' => $title,
                'body'  => $body,
                'url'   => $url,
                'site'  => $site,
            ]);

        case 'wecom':
            /* 企业微信群机器人 markdown：链接可点击 */
            $content = '**' . $title . "**\n" . $body;
            if ($url !== '') {
                $content .= "\n[查看原文](" . $url . ")";
            }
            $res = ecod_push_http($key, [
                'msgtype'  => 'markdown',
                'markdown' => ['content' => $content],
            ]);
            if ($res['ok'] && strpos($res['msg'], '"errcode":0') === false) {
                return ['ok' => false, 'msg' => $res['msg']];
            }
            return $res;

        case 'serverchan':
            /* Server酱 Turbo：sctapi.ftqq.com/<SendKey>.send */
            $res = ecod_push_http('https://sctapi.ftqq.com/' . rawurlencode($key) . '.send', [
                'title' => mb_substr($title, 0, 32, 'UTF-8'),
                'desp'  => $url !== '' ? $body . "\n\n[查看原文](" . $url . ")" : $body,
            ], true);
            if ($res['ok'] && strpos($res['msg'], '"code":0') === false) {
                return ['ok' => false, 'msg' => $res['msg']];
            }
            return $res;

        case 'bark':
            /* Bark：key 可为设备 key（走官方服务器）或完整自建地址 */
            if (preg_match('#^https?://#i', $key)) {
                $endpoint = rtrim($key, '/') . '/' . rawurlencode($title) . '/' . rawurlencode($body);
            } else {
                $endpoint = 'https://api.day.app/' . rawurlencode($key) . '/'
                    . rawurlencode($title) . '/' . rawurlencode($body);
            }
            if ($url !== '') {
                $endpoint .= '?url=' . rawurlencode($url);
            }
            return ecod_push_http($endpoint, []);

        case 'wxpusher':
            $uid = ecod_push_opt('EPushExtra');
            if ($uid === '') {
                return ['ok' => false, 'msg' => '缺少 WxPusher UID'];
            }
            $res = ecod_push_http('https://wxpusher.zjiecode.com/api/send/message', [
                'appToken'    => $key,
                'content'     => $title . "\n" . $body . ($url !== '' ? "\n" . $url : ''),
                'summary'     => mb_substr($title, 0, 99, 'UTF-8'),
                'contentType' => 1,
                'uids'        => [$uid],
            ]);
            if ($res['ok'] && strpos($res['msg'], '"code":1000') === false) {
                return ['ok' => false, 'msg' => $res['msg']];
            }
            return $res;
    }

    return ['ok' => false, 'msg' => '未知通道'];
}

/* ------------------------------------------------------------------
 * 异步触发
 * ---------------------------------------------------------------- */

/**
 * 响应结束后再发推送（评论提交完全不被拖慢）
 *
 * fastcgi_finish_request 会先把已就绪的响应（含 302 跳转头）交给客户端，
 * 之后 PHP 进程继续把推送发完。CLI / 非 FPM 环境直接同步发。
 */
function ecod_push_dispatch(string $title, string $body, string $url = ''): void
{
    if (ecod_push_channel() === '') {
        return;
    }

    $job = static function () use ($title, $body, $url): void {
        try {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            $res = ecod_push_send($title, $body, $url);
            if (!$res['ok']) {
                error_log('[ecod-push] ' . $res['msg']);
            }
        } catch (\Throwable $e) {
            error_log('[ecod-push] ' . $e->getMessage());
        }
    };

    if (function_exists('fastcgi_finish_request')) {
        register_shutdown_function($job);
    } else {
        $job();
    }
}

/* ------------------------------------------------------------------
 * 钩子与测试入口
 * ---------------------------------------------------------------- */

/**
 * finishComment 钩子：新评论 → 推送
 *
 * 与邮件 / 站内通知同一挂点；垃圾评论在 comment 钩子已被拦截，
 * 待审评论也推（后台能看到，但怕错过就留着）。
 */
function ecod_push_comment($feedback = null): void
{
    if (!$feedback || ecod_push_channel() === '') {
        return;
    }

    try {
        $author = (string) ($feedback->author ?? '');
        $text = trim(strip_tags((string) ($feedback->text ?? '')));
        if ($text === '') {
            return;
        }

        $link = '';
        try {
            $link = (string) ($feedback->content->permalink ?? '');
        } catch (\Throwable $e) {
        }
        if ($link === '') {
            $link = ecod_push_opt('siteUrl', '');
        }

        $site = ecod_push_opt('title', '站点');
        $title = '「' . $site . '」新评论 · ' . ($author !== '' ? $author : '匿名');
        $body = mb_substr($text, 0, 400, 'UTF-8');

        ecod_push_dispatch($title, $body, $link);
    } catch (\Throwable $e) {
        error_log('[ecod-push] ' . $e->getMessage());
    }
}

/**
 * 后台测试：发一条测试推送
 */
function ecod_push_test(): array
{
    if (ecod_push_channel() === '') {
        return ['ok' => false, 'message' => '请先开启「评论推送」并选择通道'];
    }
    $res = ecod_push_send(
        'Ecod 推送测试',
        '这是一条来自站点的测试推送，收到即代表通道配置成功。',
        ecod_push_opt('siteUrl', '')
    );
    return [
        'ok'      => $res['ok'],
        'message' => $res['ok'] ? '测试推送已发送，请查看接收端' : '推送失败：' . $res['msg'],
    ];
}
