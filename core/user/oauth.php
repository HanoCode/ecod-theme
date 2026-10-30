<?php
/**
 * Ecod Theme · 第三方登录（OAuth）
 *
 * 支持 GitHub / 微信（开放平台扫码）/ QQ / 微博 / Gitee。
 * 端点协议与 Zibll（Yurun\OAuthLogin）一致，见 docs/social-login.md。
 * 绑定关系存 {prefix}ecod_oauth_bind（platform+openid 唯一）：
 *   - 已登录用户走回调 → 直接绑定
 *   - 未登录且已绑定 → 直接登录
 *   - 未绑定 → 按邮箱匹配站内账号自动绑定（可关，仅 GitHub 有邮箱）；
 *     匹配不到则自动建号（可关）
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/** 全部支持的平台（api.php 白名单与此保持一致） */
function ecod_oauth_platforms(): array
{
    return ['github', 'weixin', 'qq', 'weibo', 'gitee'];
}

/**
 * 平台配置（凭据全部走后台主题设置）
 *
 * @return array{id:string,secret:string}|null 未启用返回 null
 */
function ecod_oauth_config(string $platform): ?array
{
    static $map = [
        'github' => 'EOAuthGithub',
        'weixin' => 'EOAuthWeixin',
        'qq'     => 'EOAuthQQ',
        'weibo'  => 'EOAuthWeibo',
        'gitee'  => 'EOAuthGitee',
    ];
    if (!isset($map[$platform])) {
        return null;
    }
    if (ecod_account_opt($map[$platform], 'off') !== 'on') {
        return null;
    }
    $id = trim((string) ecod_account_opt($map[$platform] . 'Id', ''));
    $secret = trim((string) ecod_account_opt($map[$platform] . 'Secret', ''));
    if ($id === '' || $secret === '') {
        return null;
    }
    return ['id' => $id, 'secret' => $secret];
}

/**
 * 回调地址（注册各平台 OAuth 应用时填同一个）
 */
function ecod_oauth_redirect_uri(string $platform): string
{
    $base = rtrim((string) ecod_account_opt('siteUrl', ''), '/');
    return $base . '/usr/themes/ecod-theme/api.php?action=oauth_callback&platform=' . rawurlencode($platform);
}

/**
 * 简单 HTTP 客户端（返回原始响应串，8 秒超时）
 *
 * QQ 换 token 成功时返回 form 串、oauth2.0/me 返回 JSONP，
 * 都不能按 JSON 解析，所以留这个底层出口。
 */
function ecod_oauth_http_raw(string $method, string $url, array $headers = [], string $body = ''): ?string
{
    $context = stream_context_create([
        'http' => [
            'method'        => $method,
            'timeout'       => 8,
            'ignore_errors' => true,
            'header'        => implode("\r\n", $headers),
            'content'       => $body,
        ],
        'ssl'  => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    return $raw === false ? null : $raw;
}

/**
 * JSON 版 HTTP 客户端（GET/POST JSON）
 */
function ecod_oauth_http(string $method, string $url, array $headers = [], string $body = ''): ?array
{
    $raw = ecod_oauth_http_raw($method, $url, $headers, $body);
    if ($raw === null || $raw === '') {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/**
 * 发起授权：302 跳到平台授权页
 *
 * @return string 错误消息；返回空串表示已输出跳转
 */
function ecod_oauth_start(string $platform): string
{
    $cfg = ecod_oauth_config($platform);
    if (!$cfg) {
        return '该第三方登录未启用';
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    /* state 防 CSRF：随机值进 session，回调时比对 */
    $state = bin2hex(random_bytes(16));
    $_SESSION['ecod_oauth_state'] = $state;
    $_SESSION['ecod_oauth_platform'] = $platform;

    /* 回跳地址：只收站内路径 */
    $referer = (string) ($_GET['referer'] ?? '');
    if ($referer === '' || $referer[0] !== '/' || substr($referer, 0, 2) === '//') {
        $referer = '/';
    }
    $_SESSION['ecod_oauth_referer'] = $referer;

    $uri = ecod_oauth_redirect_uri($platform);
    switch ($platform) {
        case 'github':
            $url = 'https://github.com/login/oauth/authorize?' . http_build_query([
                'client_id'    => $cfg['id'],
                'redirect_uri' => $uri,
                'scope'        => 'read:user user:email',
                'state'        => $state,
            ]);
            break;
        case 'weixin':
            /* PC 扫码登录，URL 尾必须拼 #wechat_redirect（与 Zibll 一致） */
            $url = 'https://open.weixin.qq.com/connect/qrconnect?' . http_build_query([
                'appid'         => $cfg['id'],
                'redirect_uri'  => $uri,
                'response_type' => 'code',
                'scope'         => 'snsapi_login',
                'state'         => $state,
            ]) . '#wechat_redirect';
            break;
        case 'qq':
            $url = 'https://graph.qq.com/oauth2.0/authorize?' . http_build_query([
                'response_type' => 'code',
                'client_id'     => $cfg['id'],
                'redirect_uri'  => $uri,
                'state'         => $state,
            ]);
            break;
        case 'weibo':
            $url = 'https://api.weibo.com/oauth2/authorize?' . http_build_query([
                'client_id'    => $cfg['id'],
                'redirect_uri' => $uri,
                'state'        => $state,
            ]);
            break;
        case 'gitee':
            $url = 'https://gitee.com/oauth/authorize?' . http_build_query([
                'client_id'     => $cfg['id'],
                'redirect_uri'  => $uri,
                'response_type' => 'code',
                'state'         => $state,
            ]);
            break;
        default:
            return '该第三方登录未启用';
    }

    ecod_http_status(302);
    header('Location: ' . $url);
    return '';
}

/**
 * 用授权码拉平台用户资料，归一化输出
 *
 * @return array{ok:bool,msg?:string,openid?:string,name?:string,avatar?:string,description?:string,email?:string,url?:string}
 */
function ecod_oauth_fetch_user(string $platform, string $code, array $cfg): array
{
    $uri = ecod_oauth_redirect_uri($platform);

    /* ---- 换 token + 拉用户，按平台走 ---- */
    if ($platform === 'github') {
        $token = ecod_oauth_http('POST', 'https://github.com/login/oauth/access_token', [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ], http_build_query([
            'client_id'     => $cfg['id'],
            'client_secret' => $cfg['secret'],
            'code'          => $code,
            'redirect_uri'  => $uri,
        ]));
        $accessToken = (string) ($token['access_token'] ?? '');
        if ($accessToken === '') {
            return ['ok' => false, 'msg' => '换取令牌失败，请重试'];
        }

        $gh = ecod_oauth_http('GET', 'https://api.github.com/user', [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/vnd.github+json',
            'User-Agent: ecod-theme',
        ]);
        $openid = (string) ($gh['id'] ?? '');
        if ($openid === '') {
            return ['ok' => false, 'msg' => '获取用户信息失败，请重试'];
        }

        /* 只信已验证的邮箱：GitHub 允许添加任意未验证邮箱，
         * 若拿未验证邮箱做站内匹配，攻击者可借此登录他人账号 */
        $email = '';
        $emails = ecod_oauth_http('GET', 'https://api.github.com/user/emails', [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/vnd.github+json',
            'User-Agent: ecod-theme',
        ]);
        if (is_array($emails)) {
            foreach ($emails as $em) {
                if (!empty($em['primary']) && !empty($em['verified'])) {
                    $email = (string) $em['email'];
                    break;
                }
            }
            if ($email === '') {
                foreach ($emails as $em) {
                    if (!empty($em['verified']) && !empty($em['email'])) {
                        $email = (string) $em['email'];
                        break;
                    }
                }
            }
        }
        if ($email === '') {
            /* 未授权邮箱场景：GitHub 官方 noreply 地址，仍可作唯一标识 */
            $email = (int) $openid . '+' . (string) ($gh['login'] ?? '') . '@users.noreply.github.com';
        }

        return [
            'ok'      => true,
            'openid'  => $openid,
            'name'    => (string) ($gh['name'] ?? ''),
            'avatar'  => (string) ($gh['avatar_url'] ?? ''),
            'email'   => $email,
            'url'     => (string) ($gh['blog'] ?? ''),
            'login'   => (string) ($gh['login'] ?? ''),
        ];
    }

    if ($platform === 'weixin') {
        $token = ecod_oauth_http('GET', 'https://api.weixin.qq.com/sns/oauth2/access_token?' . http_build_query([
            'appid'      => $cfg['id'],
            'secret'     => $cfg['secret'],
            'code'       => $code,
            'grant_type' => 'authorization_code',
        ]));
        if (!is_array($token) || (isset($token['errcode']) && (int) $token['errcode'] !== 0)) {
            return ['ok' => false, 'msg' => '换取令牌失败，请重试'];
        }
        $accessToken = (string) ($token['access_token'] ?? '');
        $openid = (string) ($token['openid'] ?? '');
        if ($accessToken === '' || $openid === '') {
            return ['ok' => false, 'msg' => '换取令牌失败，请重试'];
        }

        $wx = ecod_oauth_http('GET', 'https://api.weixin.qq.com/sns/userinfo?' . http_build_query([
            'access_token' => $accessToken,
            'openid'       => $openid,
            'lang'         => 'zh_CN',
        ]));
        if (!is_array($wx) || (isset($wx['errcode']) && (int) $wx['errcode'] !== 0)) {
            return ['ok' => false, 'msg' => '获取用户信息失败，请重试'];
        }

        return [
            'ok'          => true,
            'openid'      => $openid,
            'name'        => (string) ($wx['nickname'] ?? ''),
            'avatar'      => (string) ($wx['headimgurl'] ?? ''),
            'email'       => '',
            'description' => '',
        ];
    }

    if ($platform === 'qq') {
        /* token 成功返回 form 串，失败才是 JSON，先探测 */
        $raw = ecod_oauth_http_raw('GET', 'https://graph.qq.com/oauth2.0/token?' . http_build_query([
            'grant_type'    => 'authorization_code',
            'client_id'     => $cfg['id'],
            'client_secret' => $cfg['secret'],
            'code'          => $code,
            'redirect_uri'  => $uri,
        ]));
        $accessToken = '';
        $errJson = $raw !== null ? json_decode($raw, true) : null;
        if (is_array($errJson)) {
            return ['ok' => false, 'msg' => '换取令牌失败，请重试'];
        }
        if ($raw !== null) {
            parse_str($raw, $out);
            $accessToken = (string) ($out['access_token'] ?? '');
        }
        if ($accessToken === '') {
            return ['ok' => false, 'msg' => '换取令牌失败，请重试'];
        }

        /* oauth2.0/me 返回 JSONP：callback( {...} ); 取首末大括号之间 */
        $meRaw = ecod_oauth_http_raw('GET', 'https://graph.qq.com/oauth2.0/me?access_token=' . rawurlencode($accessToken));
        $openid = '';
        if ($meRaw !== null) {
            $l = strpos($meRaw, '{');
            $r = strrpos($meRaw, '}');
            if ($l !== false && $r !== false && $r > $l) {
                $me = json_decode(substr($meRaw, $l, $r - $l + 1), true);
                $openid = (string) ($me['openid'] ?? '');
            }
        }
        if ($openid === '') {
            return ['ok' => false, 'msg' => '获取用户信息失败，请重试'];
        }

        $qq = ecod_oauth_http('GET', 'https://graph.qq.com/user/get_user_info?' . http_build_query([
            'access_token'       => $accessToken,
            'oauth_consumer_key' => $cfg['id'],
            'openid'             => $openid,
        ]));
        if (!is_array($qq) || (isset($qq['ret']) && (int) $qq['ret'] !== 0)) {
            return ['ok' => false, 'msg' => '获取用户信息失败，请重试'];
        }

        return [
            'ok'     => true,
            'openid' => $openid,
            'name'   => (string) ($qq['nickname'] ?? ''),
            'avatar' => (string) ($qq['figureurl_qq_2'] ?? '') !== ''
                ? (string) $qq['figureurl_qq_2']
                : (string) ($qq['figureurl_qq_1'] ?? ''),
            'email'  => '',
        ];
    }

    if ($platform === 'weibo') {
        $token = ecod_oauth_http('POST', 'https://api.weibo.com/oauth2/access_token', [
            'Content-Type: application/x-www-form-urlencoded',
        ], http_build_query([
            'client_id'     => $cfg['id'],
            'client_secret' => $cfg['secret'],
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $uri,
        ]));
        if (!is_array($token) || isset($token['error_code'])) {
            return ['ok' => false, 'msg' => '换取令牌失败，请重试'];
        }
        $accessToken = (string) ($token['access_token'] ?? '');
        $openid = (string) ($token['uid'] ?? '');
        if ($accessToken === '' || $openid === '') {
            return ['ok' => false, 'msg' => '换取令牌失败，请重试'];
        }

        $wb = ecod_oauth_http('GET', 'https://api.weibo.com/2/users/show.json?' . http_build_query([
            'access_token' => $accessToken,
            'uid'          => $openid,
        ]));
        if (!is_array($wb) || isset($wb['error_code'])) {
            return ['ok' => false, 'msg' => '获取用户信息失败，请重试'];
        }

        return [
            'ok'          => true,
            'openid'      => $openid,
            'name'        => (string) ($wb['screen_name'] ?? '') !== '' ? (string) $wb['screen_name'] : (string) ($wb['name'] ?? ''),
            'avatar'      => (string) ($wb['profile_image_url'] ?? ''),
            'description' => mb_substr((string) ($wb['description'] ?? ''), 0, 100, 'UTF-8'),
            'email'       => '',
        ];
    }

    if ($platform === 'gitee') {
        $token = ecod_oauth_http('POST', 'https://gitee.com/oauth/token', [
            'Content-Type: application/x-www-form-urlencoded',
        ], http_build_query([
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'client_id'     => $cfg['id'],
            'redirect_uri'  => $uri,
            'client_secret' => $cfg['secret'],
        ]));
        if (!is_array($token) || isset($token['error']) || (string) ($token['access_token'] ?? '') === '') {
            return ['ok' => false, 'msg' => '换取令牌失败，请重试'];
        }

        $gt = ecod_oauth_http('GET', 'https://gitee.com/api/v5/user?access_token=' . rawurlencode((string) $token['access_token']));
        $openid = (string) ($gt['id'] ?? '');
        if (!is_array($gt) || $openid === '') {
            return ['ok' => false, 'msg' => '获取用户信息失败，请重试'];
        }

        /* Gitee 默认头像 URL 带 no_portrait，视为无头像（与 Zibll 一致） */
        $avatar = (string) ($gt['avatar_url'] ?? '');
        if ($avatar !== '' && stripos($avatar, 'no_portrait') !== false) {
            $avatar = '';
        }

        return [
            'ok'          => true,
            'openid'      => $openid,
            'name'        => (string) ($gt['name'] ?? '') !== '' ? (string) $gt['name'] : (string) ($gt['login'] ?? ''),
            'avatar'      => $avatar,
            'description' => mb_substr((string) ($gt['bio'] ?? ''), 0, 100, 'UTF-8'),
            'email'       => '',
            'login'       => (string) ($gt['login'] ?? ''),
        ];
    }

    return ['ok' => false, 'msg' => '该第三方登录未启用'];
}

/**
 * 平台+openid 合成唯一邮箱（自动建号用；永不参与邮箱匹配）
 */
function ecod_oauth_synth_mail(string $platform, string $openid): string
{
    $local = preg_replace('/[^A-Za-z0-9_.-]/', '', $platform . '_' . $openid) ?? '';
    return $local . '@oauth.ecod.local';
}

/**
 * 授权回调：换 token → 拉用户 → 绑定/登录/注册 → 跳回
 *
 * @return string 错误消息；返回空串表示已输出跳转
 */
function ecod_oauth_callback(string $platform): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    /* 失败统一跳回登录页，错误随参数展示 */
    $loginUrl = (string) ecod_account_opt('siteUrl', '/') . '/login.html';
    $fail = static function (string $msg) use ($loginUrl): string {
        ecod_http_status(302);
        header('Location: ' . $loginUrl . '?oauth_error=' . rawurlencode($msg));
        return '';
    };

    $cfg = ecod_oauth_config($platform);
    if (!$cfg) {
        return $fail('该第三方登录未启用');
    }

    $state = (string) ($_GET['state'] ?? '');
    $expect = (string) ($_SESSION['ecod_oauth_state'] ?? '');
    $expectPlatform = (string) ($_SESSION['ecod_oauth_platform'] ?? '');
    unset($_SESSION['ecod_oauth_state'], $_SESSION['ecod_oauth_platform']);
    if ($state === '' || $expect === '' || !hash_equals($expect, $state) || $expectPlatform !== $platform) {
        return $fail('授权状态校验失败，请重试');
    }

    $code = (string) ($_GET['code'] ?? '');
    if ($code === '') {
        return $fail('未获取到授权码');
    }

    $u = ecod_oauth_fetch_user($platform, $code, $cfg);
    if (empty($u['ok'])) {
        return $fail((string) ($u['msg'] ?? '登录失败，请重试'));
    }
    $openid = (string) $u['openid'];
    $name = (string) ($u['name'] ?? '');
    $avatar = (string) ($u['avatar'] ?? '');
    $email = (string) ($u['email'] ?? '');

    try {
        $db = \Typecho\Db::get();
        $bindTable = ecod_oauth_table();

        /* 已绑定？ */
        $bound = $db->fetchRow(
            $db->select('uid')->from($bindTable)
                ->where('platform = ? AND openid = ?', $platform, $openid)->limit(1)
        );

        $uid = (int) ($bound['uid'] ?? 0);

        if ($uid > 0) {
            /* 刷新头像缓存列（可选便利：第三方头像直接进自定义头像表） */
            try {
                if ($avatar !== '' && function_exists('ecod_account_set_avatar')) {
                    ecod_account_set_avatar((int) $uid, $avatar);
                }
            } catch (\Throwable $e) {
            }
        } else {
            $currentUser = null;
            try {
                $widget = \Widget\User::alloc();
                if ($widget->hasLogin()) {
                    $currentUser = $widget;
                }
            } catch (\Throwable $e) {
            }

            if ($currentUser) {
                /* 已登录：把第三方账号绑到当前用户 */
                $taken = $db->fetchRow(
                    $db->select('uid')->from($bindTable)
                        ->where('platform = ? AND openid = ?', $platform, $openid)->limit(1)
                );
                if ($taken) {
                    return $fail('该第三方账号已绑定其他用户');
                }
                $db->query($db->insert($bindTable)->rows([
                    'platform' => $platform,
                    'openid'   => $openid,
                    'uid'      => (int) $currentUser->uid,
                    'created'  => time(),
                ]));
                $uid = (int) $currentUser->uid;
            } else {
                /* 未登录未绑定：先按邮箱找站内账号（仅 GitHub 等有邮箱的平台） */
                $row = null;
                if ($email !== '') {
                    $row = $db->fetchRow(
                        $db->select('uid')->from('table.users')->where('mail = ?', $email)->limit(1)
                    );
                }
                if ($row) {
                    if (ecod_account_opt('EOAuthBindMail', 'on') !== 'on') {
                        return $fail('该邮箱已注册，请先用账号密码登录后到个人中心绑定');
                    }
                    $uid = (int) $row['uid'];
                } else {
                    /* 自动建号 */
                    if (ecod_account_opt('EOAuthRegister', 'on') !== 'on') {
                        return $fail('该第三方账号未绑定本站用户');
                    }
                    $login = (string) ($u['login'] ?? '');
                    $base = $login !== '' ? $login : $name;
                    if ($base === '') {
                        $base = $platform . $openid;
                    }
                    $base = preg_replace('/[^A-Za-z0-9_]/', '', (string) substr($base, 0, 20));
                    if ($base === '' || strlen($base) < 2) {
                        $base = $platform . $openid;
                    }
                    $uname = $base;
                    for ($i = 0; $i < 20; $i++) {
                        $clash = $db->fetchRow(
                            $db->select('uid')->from('table.users')
                                ->where('name = ? OR screenName = ?', $uname, $uname)->limit(1)
                        );
                        if (!$clash) {
                            break;
                        }
                        $uname = $base . random_int(10, 99);
                    }
                    if ($email === '') {
                        $email = ecod_oauth_synth_mail($platform, $openid);
                    }
                    $uid = (int) $db->query($db->insert('table.users')->rows([
                        'name'       => $uname,
                        'mail'       => $email,
                        'screenName' => $name !== '' ? mb_substr($name, 0, 32, 'UTF-8') : $uname,
                        'password'   => '', /* OAuth 建号无密码；需密码时走找回流程 */
                        'url'        => (string) ($u['url'] ?? ''),
                        'created'    => time(),
                        'activated'  => time(),
                        'logged'     => time(),
                        'group'      => 'subscriber',
                    ]));
                }

                $db->query($db->insert($bindTable)->rows([
                    'platform' => $platform,
                    'openid'   => $openid,
                    'uid'      => $uid,
                    'created'  => time(),
                ]));

                try {
                    if ($avatar !== '' && function_exists('ecod_account_set_avatar')) {
                        ecod_account_set_avatar($uid, $avatar);
                    }
                } catch (\Throwable $e) {
                }
            }
        }

        if ($uid <= 0) {
            return $fail('登录失败，请重试');
        }

        /* 持久登录（30 天），与账号密码登录同一套 Cookie 机制 */
        $user = \Widget\User::alloc();
        $user->simpleLogin($uid, false, 30 * 24 * 3600);

        $referer = (string) ($_SESSION['ecod_oauth_referer'] ?? '/');
        unset($_SESSION['ecod_oauth_referer']);
        if ($referer === '' || $referer[0] !== '/' || substr($referer, 0, 2) === '//') {
            $referer = '/';
        }

        $base = rtrim((string) ecod_account_opt('siteUrl', ''), '/');
        ecod_http_status(302);
        header('Location: ' . $base . $referer);
        return '';
    } catch (\Throwable $e) {
        error_log('[ecod-oauth] ' . $e->getMessage());
        return $fail('登录失败，请稍后重试');
    }
}
