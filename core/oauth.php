<?php
/**
 * Ecod Theme · 第三方登录（OAuth）
 *
 * 当前支持 GitHub；微信等平台预留同框架（填凭据 + 加 fetch 分支即可接入）。
 * 绑定关系存 {prefix}ecod_oauth_bind（platform+openid 唯一）：
 *   - 已登录用户走回调 → 直接绑定
 *   - 未登录且已绑定 → 直接登录
 *   - 未绑定 → 按邮箱匹配站内账号自动绑定（可关）；匹配不到则自动建号（可关）
 *
 * @package Ecod Theme
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 平台配置（凭据全部走后台主题设置）
 *
 * @return array{id:string,secret:string}|null 未启用返回 null
 */
function ecod_oauth_config(string $platform): ?array
{
    if ($platform !== 'github') {
        return null; /* 其他平台暂未开放 */
    }
    if (ecod_account_opt('EOAuthGithub', 'off') !== 'on') {
        return null;
    }
    $id = trim((string) ecod_account_opt('EOAuthGithubId', ''));
    $secret = trim((string) ecod_account_opt('EOAuthGithubSecret', ''));
    if ($id === '' || $secret === '') {
        return null;
    }
    return ['id' => $id, 'secret' => $secret];
}

/**
 * 回调地址（注册 GitHub OAuth App 时填同一个）
 */
function ecod_oauth_redirect_uri(): string
{
    $base = rtrim((string) ecod_account_opt('siteUrl', ''), '/');
    return $base . '/usr/themes/ecod-theme/api.php?action=oauth_callback&platform=github';
}

/**
 * 简单 HTTP 客户端（GET/POST JSON，8 秒超时，够 OAuth 用）
 */
function ecod_oauth_http(string $method, string $url, array $headers = [], string $body = ''): ?array
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
    if ($raw === false) {
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

    if ($platform === 'github') {
        $url = 'https://github.com/login/oauth/authorize?' . http_build_query([
            'client_id'    => $cfg['id'],
            'redirect_uri' => ecod_oauth_redirect_uri(),
            'scope'        => 'read:user user:email',
            'state'        => $state,
        ]);
    } else {
        return '该第三方登录未启用';
    }

    ecod_http_status(302);
    header('Location: ' . $url);
    return '';
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

    /* code 换 access_token */
    if ($platform !== 'github') {
        return $fail('该第三方登录未启用');
    }
    $token = ecod_oauth_http('POST', 'https://github.com/login/oauth/access_token', [
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
    ], http_build_query([
        'client_id'     => $cfg['id'],
        'client_secret' => $cfg['secret'],
        'code'          => $code,
        'redirect_uri'  => ecod_oauth_redirect_uri(),
    ]));
    $accessToken = (string) ($token['access_token'] ?? '');
    if ($accessToken === '') {
        return $fail('换取令牌失败，请重试');
    }

    /* 拉用户资料与邮箱 */
    $gh = ecod_oauth_http('GET', 'https://api.github.com/user', [
        'Authorization: Bearer ' . $accessToken,
        'Accept: application/vnd.github+json',
        'User-Agent: ecod-theme',
    ]);
    $openid = (string) ($gh['id'] ?? '');
    if ($openid === '') {
        return $fail('获取用户信息失败，请重试');
    }

    $login = (string) ($gh['login'] ?? '');
    $name = (string) ($gh['name'] ?? '');
    $avatar = (string) ($gh['avatar_url'] ?? '');

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
        /* 没有已验证的主邮箱就退而求其次 */
        if ($email === '' && !empty($emails[0]['email'])) {
            $email = (string) $emails[0]['email'];
        }
    }
    if ($email === '') {
        /* 未授权邮箱场景：GitHub 官方 noreply 地址，仍可作唯一标识 */
        $email = (int) $openid . '+' . $login . '@users.noreply.github.com';
    }

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
            /* 刷新头像缓存列（可选便利：GitHub 头像直接进自定义头像表） */
            try {
                if ($avatar !== '' && function_exists('ecod_account_set_avatar')) {
                    ecod_account_set_avatar((int) $uid, $avatar);
                }
            } catch (\Throwable $e) {
            }
        } else {
            $currentUser = null;
            try {
                $u = \Widget\User::alloc();
                if ($u->hasLogin()) {
                    $currentUser = $u;
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
                /* 未登录未绑定：先按邮箱找站内账号 */
                $row = $db->fetchRow(
                    $db->select('uid')->from('table.users')->where('mail = ?', $email)->limit(1)
                );
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
                    $base = $login !== '' ? $login : ('user' . $openid);
                    $base = preg_replace('/[^A-Za-z0-9_]/', '', (string) substr($base, 0, 20));
                    if ($base === '' || strlen($base) < 2) {
                        $base = 'user' . $openid;
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
                    $uid = (int) $db->query($db->insert('table.users')->rows([
                        'name'       => $uname,
                        'mail'       => $email,
                        'screenName' => $name !== '' ? mb_substr($name, 0, 32, 'UTF-8') : $uname,
                        'password'   => '', /* OAuth 建号无密码；需密码时走找回流程 */
                        'url'        => (string) ($gh['blog'] ?? ''),
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
