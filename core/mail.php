<?php
/**
 * Ecod Theme · 评论邮件提醒
 *
 * 只用 PHP 自带的 socket，不引入 PHPMailer：
 *   1. 只支持最常见的 LOGIN 认证（QQ / 163 / Gmail / 企业邮都支持）
 *   2. 任何一步失败都只写 error_log，绝不打断评论提交
 *   3. 默认关闭，后台「评论 → 评论邮件通知」打开后才生效
 *
 * @package Ecod Theme
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 读主题选项
 *
 * 后台主题设置页里有 functions.php 提供的 ecod_option，但 api.php 独立入口
 * 不加载 functions.php（友链申请通知邮件就在那边发），这里做一个自适应包装。
 */
function ecod_mail_opt(string $key, $default = '')
{
    if (function_exists('ecod_option')) {
        return ecod_option($key, $default);
    }
    try {
        $options = \Utils\Helper::options();
        return isset($options->{$key}) && (string) $options->{$key} !== ''
            ? $options->{$key} : $default;
    } catch (\Throwable $e) {
        return $default;
    }
}

/**
 * 读取 SMTP 配置
 *
 * @return array{host:string,port:int,secure:string,user:string,pass:string,from:string,name:string,to:string}
 */
function ecod_mail_config(): array
{
    $host = trim((string) ecod_mail_opt('ECommentMailHost', ''));
    $user = trim((string) ecod_mail_opt('ECommentMailAccount', ''));
    $pass = (string) ecod_mail_opt('ECommentMailPassword', '');
    $to = trim((string) ecod_mail_opt('ECommentMailTo', ''));

    return [
        'host'   => $host,
        'port'   => max(1, (int) ecod_mail_opt('ECommentMailPort', 465)),
        'secure' => (string) ecod_mail_opt('ECommentMailSecure', 'ssl'),
        'user'   => $user,
        'pass'   => $pass,
        'from'   => $user,
        'name'   => trim((string) ecod_mail_opt('ECommentMailFrom', '')) ?: (string) ecod_mail_opt('ELogo', ''),
        'to'     => $to !== '' ? $to : $user,
    ];
}

/**
 * 是否具备发信条件
 */
function ecod_mail_ready(): bool
{
    if (ecod_mail_opt('ECommentMail', 'off') !== 'on') {
        return false;
    }
    $c = ecod_mail_config();
    return $c['host'] !== '' && $c['user'] !== '' && $c['pass'] !== '' && $c['to'] !== '';
}

/**
 * 发一封 HTML 邮件
 *
 * @param array $c 由 ecod_mail_config() 提供
 */
function ecod_mail_smtp(array $c, string $subject, string $html): bool
{
    $remote = ($c['secure'] === 'ssl' ? 'ssl://' : '') . $c['host'] . ':' . $c['port'];
    /* 证书校验默认开启，防中间人截获授权码；个别邮箱中继证书链不全、
     * 连不上时可在后台「邮件通知 → 校验 SMTP 证书」关闭（ECommentMailVerify） */
    $verify = null;
    if (function_exists('ecod_option')) {
        $verify = ecod_option('ECommentMailVerify', 'on') === 'on';
    } elseif (class_exists('\Utils\Helper')) {
        try {
            $opt = \Utils\Helper::options();
            $verify = !isset($opt->ECommentMailVerify) || (string) $opt->ECommentMailVerify !== 'off';
        } catch (\Throwable $e) {
            $verify = true;
        }
    }
    $context = stream_context_create([
        'ssl' => [
            'verify_peer'       => $verify !== false,
            'verify_peer_name'  => $verify !== false,
            'allow_self_signed' => $verify === false,
        ],
    ]);

    $fp = @stream_socket_client($remote, $errno, $errstr, 8, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) {
        error_log('[ecod-mail] connect failed: ' . $errstr);
        return false;
    }
    stream_set_timeout($fp, 8);

    $read = static function () use ($fp): string {
        $out = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $out .= $line;
            if (preg_match('/^\d{3} /', $line)) {
                break;
            }
        }
        return $out;
    };

    $talk = static function (string $command, array $expect) use ($fp, $read): bool {
        if ($command !== '') {
            fwrite($fp, $command . "\r\n");
        }
        $response = $read();
        $code = (int) substr(ltrim($response), 0, 3);
        return in_array($code, $expect, true);
    };

    $quit = static function () use ($fp, $talk) {
        $talk('QUIT', [221, 250]);
        @fclose($fp);
    };

    /* 握手 */
    if (!$talk('', [220])) {
        @fclose($fp);
        error_log('[ecod-mail] no greeting');
        return false;
    }

    $ehlo = 'EHLO ' . (parse_url((string) ecod_mail_opt('siteUrl', 'localhost'), PHP_URL_HOST) ?: 'localhost');
    if (!$talk($ehlo, [250])) {
        $quit();
        error_log('[ecod-mail] EHLO rejected');
        return false;
    }

    /* STARTTLS */
    if ($c['secure'] === 'tls') {
        if (!$talk('STARTTLS', [220])) {
            $quit();
            error_log('[ecod-mail] STARTTLS rejected');
            return false;
        }
        $method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (!@stream_socket_enable_crypto($fp, true, $method)) {
            $quit();
            error_log('[ecod-mail] crypto failed');
            return false;
        }
        if (!$talk($ehlo, [250])) {
            $quit();
            return false;
        }
    }

    /* AUTH LOGIN */
    if (!$talk('AUTH LOGIN', [334])) {
        $quit();
        error_log('[ecod-mail] AUTH not accepted');
        return false;
    }
    if (!$talk(base64_encode($c['user']), [334])) {
        $quit();
        error_log('[ecod-mail] user rejected');
        return false;
    }
    if (!$talk(base64_encode($c['pass']), [235])) {
        $quit();
        error_log('[ecod-mail] password rejected');
        return false;
    }

    /* 信封 */
    if (!$talk('MAIL FROM:<' . $c['from'] . '>', [250])) {
        $quit();
        return false;
    }
    foreach (array_filter(array_map('trim', explode(',', $c['to']))) as $rcpt) {
        if (!$talk('RCPT TO:<' . $rcpt . '>', [250, 251])) {
            $quit();
            error_log('[ecod-mail] rcpt rejected: ' . $rcpt);
            return false;
        }
    }
    if (!$talk('DATA', [354])) {
        $quit();
        return false;
    }

    $name = $c['name'] !== '' ? $c['name'] : 'Ecod';
    $headers = [
        'From: =?UTF-8?B?' . base64_encode($name) . '?= <' . $c['from'] . '>',
        'To: <' . $c['to'] . '>',
        'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
        'Date: ' . date('r'),
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        'X-Mailer: Ecod Theme',
    ];

    $body = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($html), 76, "\r\n");
    fwrite($fp, $body . "\r\n.\r\n");

    $accepted = $talk('', [250]);
    $quit();

    return $accepted;
}

/**
 * 评论写入后发提醒
 *
 * @param mixed $feedback \Widget\Feedback
 */
function ecod_mail_notify($feedback = null): void
{
    if (!ecod_mail_ready() || !$feedback) {
        return;
    }

    try {
        $author = (string) ($feedback->author ?? '');
        $mail = (string) ($feedback->mail ?? '');
        $text = trim(strip_tags((string) ($feedback->text ?? '')));
        $link = '';
        try {
            $link = (string) ($feedback->content->permalink ?? '');
        } catch (\Throwable $e) {
            $link = '';
        }
        if ($link === '') {
            $link = (string) ecod_mail_opt('siteUrl', '');
        }

        $title = (string) ecod_mail_opt('title', '站点');
        $subject = '「' . $title . '」收到新评论 · ' . ($author !== '' ? $author : '匿名');

        $rows = [
            '作者' => $author,
            '邮箱' => $mail,
            '内容' => mb_substr($text, 0, 600, 'UTF-8'),
        ];
        $html = '<div style="font:14px/1.7 -apple-system,Segoe UI,PingFang SC,sans-serif;color:#1e2430">'
            . '<p style="margin:0 0 12px"><b>' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</b></p>'
            . '<table cellpadding="6" style="border-collapse:collapse;font-size:13px">';
        foreach ($rows as $label => $value) {
            $html .= '<tr><td style="color:#5b6472;vertical-align:top">' . $label . '</td>'
                . '<td>' . nl2br(htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8')) . '</td></tr>';
        }
        $html .= '</table><p style="margin:14px 0 0"><a href="'
            . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">查看这条评论</a></p></div>';

        $c = ecod_mail_config();
        if (!ecod_mail_smtp($c, $subject, $html)) {
            error_log('[ecod-mail] send failed for comment by ' . $author);
        }
    } catch (\Throwable $e) {
        error_log('[ecod-mail] ' . $e->getMessage());
    }
}

/**
 * 评论收到回复时，给被回复者发提醒
 *
 * 与 ecod_mail_notify 共用同一套 SMTP；同一邮箱（自己回复自己）不发，
 * 父评论必须是已通过审核的，且对方填了合法邮箱。
 *
 * @param mixed $feedback \Widget\Feedback（已 push 新评论）
 */
function ecod_mail_notify_reply($feedback = null): void
{
    try {
        if (!ecod_mail_ready() || !$feedback) {
            return;
        }
        if (ecod_mail_opt('EReplyMail', 'off') !== 'on') {
            return;
        }

        $parentCoid = (int) ($feedback->parent ?? 0);
        if ($parentCoid <= 0) {
            return;
        }

        $db = \Typecho\Db::get();
        $parent = $db->fetchRow($db->select('coid', 'author', 'mail', 'text', 'status')
            ->from('table.comments')->where('coid = ?', $parentCoid)->limit(1));
        if (!$parent || $parent['status'] !== 'approved') {
            return;
        }

        $toMail = trim((string) ($parent['mail'] ?? ''));
        if ($toMail === '' || !filter_var($toMail, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        /* 自己回复自己不打扰 */
        if (strcasecmp($toMail, (string) ($feedback->mail ?? '')) === 0) {
            return;
        }

        $title = (string) ecod_mail_opt('title', '站点');
        $replier = (string) ($feedback->author ?? '匿名');
        $replyText = trim(strip_tags((string) ($feedback->text ?? '')));
        $origText = trim(strip_tags((string) ($parent['text'] ?? '')));

        try {
            $link = (string) ($feedback->content->permalink ?? '');
        } catch (\Throwable $e) {
            $link = '';
        }
        if ($link === '') {
            $link = (string) ecod_mail_opt('siteUrl', '');
        }
        /* 锚点优先定位到父评论（回复在它的楼中楼下） */
        $link .= '#comment-' . $parentCoid;

        $subject = '您在「' . $title . '」的评论收到了新回复';
        $status = (string) ($feedback->status ?? 'approved');
        $tip = $status === 'approved'
            ? ''
            : '<p style="color:#b45309;margin:10px 0 0">（该回复正在等待审核，通过后才会公开显示）</p>';

        $html = '<div style="font:14px/1.7 -apple-system,Segoe UI,PingFang SC,sans-serif;color:#1e2430">'
            . '<p style="margin:0 0 12px"><b>' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</b></p>'
            . '<p style="margin:0 0 6px;color:#5b6472">您的评论：</p>'
            . '<blockquote style="margin:0 0 12px;padding:8px 12px;border-left:3px solid #e4e7ee;color:#5b6472">'
            . nl2br(htmlspecialchars(mb_substr($origText, 0, 300, 'UTF-8'), ENT_QUOTES, 'UTF-8')) . '</blockquote>'
            . '<p style="margin:0 0 6px;color:#5b6472">' . htmlspecialchars($replier, ENT_QUOTES, 'UTF-8') . ' 回复了您：</p>'
            . '<blockquote style="margin:0 0 12px;padding:8px 12px;border-left:3px solid #4f6ef2">'
            . nl2br(htmlspecialchars(mb_substr($replyText, 0, 600, 'UTF-8'), ENT_QUOTES, 'UTF-8')) . '</blockquote>'
            . $tip
            . '<p style="margin:14px 0 0"><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">查看回复</a></p>'
            . '<p style="margin:14px 0 0;color:#9aa1ad;font-size:12px">如果不想再收到此类邮件，请忽略本邮件或联系站长。</p>'
            . '</div>';

        $c = ecod_mail_config();
        $c['to'] = $toMail;
        if (!ecod_mail_smtp($c, $subject, $html)) {
            error_log('[ecod-mail] reply notice failed for coid ' . $parentCoid);
        }
    } catch (\Throwable $e) {
        error_log('[ecod-mail] reply: ' . $e->getMessage());
    }
}
