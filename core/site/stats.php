<?php
/**
 * Ecod Theme · 网站访问统计
 *
 * 口径（先定口径，再谈实现）：
 *   - PV   一次前台页面请求算一次。PJAX / 分类 tab 换页也算（用户确实看到新页面）；
 *          预取、预渲染、爬虫、管理员自己（可关）不算。
 *   - UV   cookie `ecod_vid` 访客 ID + 当天去重表：同一浏览器一天一个，跨天重新计。
 *          不用 IP：NAT / 校园网 / CDN 后面 IP 会重合，也挡不住换 IP 的爬虫。
 *   - 会话 cookie `ecod_ss` 30 分钟滑动窗口；会话首个 PV 记落地页（entry 维度）。
 *   - 停留 / 跳出  由前端 beacon 在 pagehide 时上报：平均停留 = dur / dur_n，
 *          跳出率 = bounce / sess（跳出定义为会话只有一次 PV 且停留 < 15s，近似口径）。
 *   - 来源  站内跳转不算来源；搜索引擎 / 社交按域名归类，其余记域名本身。
 *
 * 存储只做「按天聚合 + 维度计数」，不落逐条访问日志：
 *   ecod_stats_day     每天一行（pv/uv/sess/dur/dur_n/bounce）
 *   ecod_stats_dim     每天每维度每取值一行（page/ref/entry/device/os/browser）
 *   ecod_stats_uv      每天每个访客一行，仅用于 UV 去重，按保留期清理
 *   ecod_stats_online  活跃访客最后心跳，5 分钟窗口算在线人数
 * 一天几十到几百行，取数与清理都不需要索引调优，也不会把库撑大。
 *
 * 写入时机：ecod_stats_hit() 只在内存里攒数据，注册的 shutdown 回调里先
 * fastcgi_finish_request() 把响应发给用户，再落库 —— 前台 TTFB 不受影响。
 * 落库失败一律静默（统计挂了不能拖垮页面）。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @since   2026-11-01
 * @version 1.0.0
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/** 维度取值最大长度（对应 dim 表 k 列） */
define('ECOD_STATS_KEY_MAX', 120);

/**
 * 读设置：独立入口（api.php / stats.php）没有 functions.php 的 ecod_option，
 * 这里自带兜底，两条链路都能取到同一个值。
 */
function ecod_stats_opt(string $key, $default = '')
{
    if (function_exists('ecod_option')) {
        return ecod_option($key, $default);
    }
    try {
        $options = \Utils\Helper::options();
    } catch (\Throwable $e) {
        return $default;
    }
    return isset($options->{$key}) && $options->{$key} !== '' ? $options->{$key} : $default;
}

/** 统计是否开启 */
function ecod_stats_enabled(): bool
{
    return ecod_stats_opt('EStats', 'on') !== 'off';
}

/** 表名 */
function ecod_stats_table(string $name): string
{
    try {
        $db = \Typecho\Db::get();
        return $db->getPrefix() . 'ecod_stats_' . $name;
    } catch (\Throwable $e) {
        return 'typecho_ecod_stats_' . $name;
    }
}

/**
 * 站点本地时间格式化
 *
 * PHP 默认时区是 UTC，而站点时区取自 Typecho 的 timezone（通常 +8）。
 * 直接用 date() 会把北京时间 0:00–8:00 的访问记到前一天，必须按站点偏移算。
 */
function ecod_stats_date(string $fmt = 'Y-m-d', ?int $ts = null): string
{
    static $offset = null;
    if ($offset === null) {
        try {
            $offset = (int) (\Utils\Helper::options()->timezone ?? 0);
        } catch (\Throwable $e) {
            $offset = 0;
        }
    }
    return gmdate($fmt, ($ts ?? time()) + $offset);
}

/** 站点本地的今天（Y-m-d） */
function ecod_stats_today(): string
{
    return ecod_stats_date('Y-m-d');
}

/** 往前 $n 天的站点本地日期（$n = 0 即今天） */
function ecod_stats_day_ago(int $n): string
{
    return ecod_stats_date('Y-m-d', time() - max(0, $n) * 86400);
}

/**
 * 建表（幂等）
 *
 * schema.php 的结构升级里也会调这里；两边都调用是因为独立入口可能在
 * 结构升级之前就被访问（例如 beacon 打点），这里兜底一次。
 */
function ecod_stats_ensure(?\Typecho\Db $db = null): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }

    if ($db === null) {
        try {
            $db = \Typecho\Db::get();
        } catch (\Throwable $e) {
            return $ok = false;
        }
    }

    require_once __DIR__ . '/../lib/schema.php';
    $adapter = ecod_schema_adapter($db);
    $p = $db->getPrefix();
    $day = $p . 'ecod_stats_day';
    $dim = $p . 'ecod_stats_dim';
    $uv  = $p . 'ecod_stats_uv';
    $on  = $p . 'ecod_stats_online';

    try {
        if ($adapter === 'mysql') {
            $db->query(
                'CREATE TABLE IF NOT EXISTS `' . $day . '` ('
                . ' `day` CHAR(10) NOT NULL,'
                . ' `pv` INT UNSIGNED NOT NULL DEFAULT 0,'
                . ' `uv` INT UNSIGNED NOT NULL DEFAULT 0,'
                . ' `sess` INT UNSIGNED NOT NULL DEFAULT 0,'
                . ' `dur` INT UNSIGNED NOT NULL DEFAULT 0,'
                . ' `dur_n` INT UNSIGNED NOT NULL DEFAULT 0,'
                . ' `bounce` INT UNSIGNED NOT NULL DEFAULT 0,'
                . ' PRIMARY KEY (`day`)'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            $db->query(
                'CREATE TABLE IF NOT EXISTS `' . $dim . '` ('
                . ' `day` CHAR(10) NOT NULL,'
                . ' `dim` VARCHAR(12) NOT NULL,'
                . ' `k` VARCHAR(120) NOT NULL,'
                . ' `pv` INT UNSIGNED NOT NULL DEFAULT 0,'
                . ' `uv` INT UNSIGNED NOT NULL DEFAULT 0,'
                . ' PRIMARY KEY (`day`, `dim`, `k`),'
                . ' KEY `dim_day` (`dim`, `day`)'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            $db->query(
                'CREATE TABLE IF NOT EXISTS `' . $uv . '` ('
                . ' `day` CHAR(10) NOT NULL,'
                . ' `vid` CHAR(32) NOT NULL,'
                . ' PRIMARY KEY (`day`, `vid`)'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            $db->query(
                'CREATE TABLE IF NOT EXISTS `' . $on . '` ('
                . ' `vid` CHAR(32) NOT NULL,'
                . ' `touched` INT UNSIGNED NOT NULL DEFAULT 0,'
                . ' PRIMARY KEY (`vid`),'
                . ' KEY `touched` (`touched`)'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        } else {
            /* sqlite / pgsql 用同一套 DDL（双引号标识符 + IF NOT EXISTS） */
            $int = 'INTEGER';
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $day . '" ('
                . ' "day" CHAR(10) NOT NULL,'
                . ' "pv" ' . $int . ' NOT NULL DEFAULT 0,'
                . ' "uv" ' . $int . ' NOT NULL DEFAULT 0,'
                . ' "sess" ' . $int . ' NOT NULL DEFAULT 0,'
                . ' "dur" ' . $int . ' NOT NULL DEFAULT 0,'
                . ' "dur_n" ' . $int . ' NOT NULL DEFAULT 0,'
                . ' "bounce" ' . $int . ' NOT NULL DEFAULT 0,'
                . ' PRIMARY KEY ("day"))'
            );
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $dim . '" ('
                . ' "day" CHAR(10) NOT NULL,'
                . ' "dim" VARCHAR(12) NOT NULL,'
                . ' "k" VARCHAR(120) NOT NULL,'
                . ' "pv" ' . $int . ' NOT NULL DEFAULT 0,'
                . ' "uv" ' . $int . ' NOT NULL DEFAULT 0,'
                . ' PRIMARY KEY ("day", "dim", "k"))'
            );
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $uv . '" ('
                . ' "day" CHAR(10) NOT NULL,'
                . ' "vid" CHAR(32) NOT NULL,'
                . ' PRIMARY KEY ("day", "vid"))'
            );
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $on . '" ('
                . ' "vid" CHAR(32) NOT NULL,'
                . ' "touched" ' . $int . ' NOT NULL DEFAULT 0,'
                . ' PRIMARY KEY ("vid"))'
            );
            $db->query('CREATE INDEX IF NOT EXISTS "' . $dim . '_dim_day" ON "' . $dim . '" ("dim", "day")');
            $db->query('CREATE INDEX IF NOT EXISTS "' . $on . '_touched" ON "' . $on . '" ("touched")');
        }
        $ok = true;
    } catch (\Throwable $e) {
        $ok = false;
    }

    return $ok;
}

/**
 * 原生 SQL 值转义
 *
 * Typecho 的 Db::query() 只接受 (sql, op) 两个参数，不支持占位符绑定，
 * 所有原生 SQL 必须自己转义；整数直接拼，字符串走适配器的 quoteValue()。
 */
function ecod_stats_q(\Typecho\Db $db, $value): string
{
    if (is_int($value)) {
        return (string) $value;
    }
    return $db->getAdapter()->quoteValue((string) $value);
}

/**
 * 一次 upsert 自增：命中主键就把对应列累加
 *
 * @param array<string, mixed> $keys 主键列 => 值
 * @param array<string, int>   $incs 自增列 => 增量
 */
function ecod_stats_inc(\Typecho\Db $db, string $table, array $keys, array $incs): void
{
    static $adapter = null;
    if ($adapter === null) {
        require_once __DIR__ . '/../lib/schema.php';
        $adapter = ecod_schema_adapter($db);
    }

    /* 增量为 0 的列不进 SQL：day 行只涨 pv，不必每次把 sess/dur/bounce 也写一遍 */
    $incs = array_filter($incs, static function ($v) {
        return (int) $v !== 0;
    });
    if (!$incs) {
        return;
    }

    $cols = array_merge(array_keys($keys), array_keys($incs));
    $vals = array_merge(array_values($keys), array_values($incs));
    $list = implode(', ', array_map(static function ($v) use ($db) {
        return ecod_stats_q($db, $v);
    }, $vals));

    if ($adapter === 'mysql') {
        $sets = [];
        foreach ($incs as $c => $delta) {
            $sets[] = '`' . $c . '` = `' . $c . '` + ' . (int) $delta;
        }
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`, `', $cols) . '`) VALUES (' . $list . ')'
            . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $sets);
    } else {
        $sets = [];
        foreach ($incs as $c => $delta) {
            $sets[] = '"' . $c . '" = "' . $c . '" + ' . (int) $delta;
        }
        $sql = 'INSERT INTO "' . $table . '" ("' . implode('", "', $cols) . '") VALUES (' . $list . ')'
            . ' ON CONFLICT ("' . implode('", "', array_keys($keys)) . '") DO UPDATE SET ' . implode(', ', $sets);
    }

    $db->query($sql);
}

/**
 * 一次 upsert 覆盖：命中主键就把列改成新值
 *
 * 心跳时间这类列不能累加（累加会让 touched 跑到未来，在线人数永远算真），
 * 所以和 ecod_stats_inc 分开。
 *
 * @param array<string, mixed> $keys 主键列 => 值
 * @param array<string, mixed> $sets 覆盖列 => 新值
 */
function ecod_stats_set(\Typecho\Db $db, string $table, array $keys, array $sets): void
{
    static $adapter = null;
    if ($adapter === null) {
        require_once __DIR__ . '/../lib/schema.php';
        $adapter = ecod_schema_adapter($db);
    }

    $cols = array_merge(array_keys($keys), array_keys($sets));
    $vals = array_merge(array_values($keys), array_values($sets));
    $list = implode(', ', array_map(static function ($v) use ($db) {
        return ecod_stats_q($db, $v);
    }, $vals));

    $assign = [];
    foreach ($sets as $c => $v) {
        $assign[] = ($adapter === 'mysql' ? '`' . $c . '`' : '"' . $c . '"')
            . ' = ' . ecod_stats_q($db, $v);
    }

    if ($adapter === 'mysql') {
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`, `', $cols) . '`) VALUES (' . $list . ')'
            . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $assign);
    } else {
        $sql = 'INSERT INTO "' . $table . '" ("' . implode('", "', $cols) . '") VALUES (' . $list . ')'
            . ' ON CONFLICT ("' . implode('", "', array_keys($keys)) . '") DO UPDATE SET ' . implode(', ', $assign);
    }

    $db->query($sql);
}

/** 访客 ID：32 位十六进制，与 api.php 的点赞指纹同一枚 cookie */
function ecod_stats_vid(): string
{
    $raw = (string) ($_COOKIE['ecod_vid'] ?? '');
    if (preg_match('/^[a-f0-9]{32}$/i', $raw)) {
        return strtolower($raw);
    }
    return '';
}

/** 生成并下发访客 ID（httponly，一年有效） */
function ecod_stats_vid_issue(): string
{
    $vid = md5(($GLOBALS['ecodStatsSalt'] ?? 'ecod')
        . '|' . ($_SERVER['REMOTE_ADDR'] ?? '')
        . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '')
        . '|' . random_int(100000, 999999)
        . '|' . microtime(true));
    $_COOKIE['ecod_vid'] = $vid;

    if (!headers_sent()) {
        $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;
        setcookie('ecod_vid', $vid, [
            'expires'  => time() + 365 * 86400,
            'path'     => '/',
            'httponly' => true,
            'secure'   => $https,
            'samesite' => 'Lax',
        ]);
    }

    return $vid;
}

/** UA 分类：返回 [device, os, browser] */
function ecod_stats_ua(string $ua): array
{
    $ua = strtolower($ua);
    if ($ua === '') {
        return ['unknown', 'unknown', 'unknown'];
    }

    if (strpos($ua, 'ipad') !== false || (strpos($ua, 'android') !== false && strpos($ua, 'mobile') === false)
        || strpos($ua, 'tablet') !== false) {
        $device = 'tablet';
    } elseif (strpos($ua, 'mobile') !== false || strpos($ua, 'iphone') !== false
        || strpos($ua, 'android') !== false || strpos($ua, 'micromessenger') !== false) {
        $device = 'mobile';
    } else {
        $device = 'desktop';
    }

    if (strpos($ua, 'iphone') !== false || strpos($ua, 'ipad') !== false || strpos($ua, 'ios') !== false) {
        $os = 'iOS';
    } elseif (strpos($ua, 'android') !== false) {
        $os = 'Android';
    } elseif (strpos($ua, 'windows') !== false) {
        $os = 'Windows';
    } elseif (strpos($ua, 'mac os x') !== false || strpos($ua, 'macintosh') !== false) {
        $os = 'macOS';
    } elseif (strpos($ua, 'linux') !== false) {
        $os = 'Linux';
    } else {
        $os = '其它';
    }

    /* 顺序有讲究：微信 / QQ / UC 的 UA 里都带 chrome 或 safari 字样 */
    if (strpos($ua, 'micromessenger') !== false) {
        $browser = '微信';
    } elseif (strpos($ua, 'qqbrowser') !== false) {
        $browser = 'QQ 浏览器';
    } elseif (strpos($ua, 'ucbrowser') !== false) {
        $browser = 'UC';
    } elseif (strpos($ua, 'edg/') !== false) {
        $browser = 'Edge';
    } elseif (strpos($ua, 'firefox') !== false) {
        $browser = 'Firefox';
    } elseif (strpos($ua, 'chrome') !== false || strpos($ua, 'crios') !== false) {
        $browser = 'Chrome';
    } elseif (strpos($ua, 'safari') !== false) {
        $browser = 'Safari';
    } else {
        $browser = '其它';
    }

    return [$device, $os, $browser];
}

/** 来源归类：站内 / 直接访问 / 搜索引擎 / 社交 / 其它域名 */
function ecod_stats_ref(string $ref, string $host): string
{
    $ref = trim($ref);
    if ($ref === '') {
        return '直接访问';
    }

    $refHost = strtolower((string) parse_url($ref, PHP_URL_HOST));
    if ($refHost === '') {
        return '直接访问';
    }
    $host = strtolower($host);
    /* 本站（含 www. 变体）跳转不算来源 */
    if ($refHost === $host || $refHost === 'www.' . $host || 'www.' . $refHost === $host) {
        return '站内跳转';
    }

    $engines = ['baidu.com' => '百度', 'bing.com' => 'Bing', 'google.' => 'Google',
        'sogou.com' => '搜狗', 'so.com' => '360 搜索', 'sm.cn' => '神马',
        'yandex.' => 'Yandex', 'duckduckgo.com' => 'DuckDuckGo'];
    foreach ($engines as $needle => $name) {
        if (strpos($refHost, $needle) !== false) {
            return $name;
        }
    }

    $socials = ['weibo.' => '微博', 'zhihu.com' => '知乎', 'douyin.com' => '抖音',
        'toutiao.com' => '头条', 'twitter.com' => 'X', 'x.com' => 'X',
        'facebook.com' => 'Facebook', 'reddit.com' => 'Reddit', 'news.ycombinator.com' => 'Hacker News',
        'mp.weixin.qq.com' => '微信公众号', 'juejin.cn' => '掘金', 'v2ex.com' => 'V2EX'];
    foreach ($socials as $needle => $name) {
        if (strpos($refHost, $needle) !== false) {
            return $name;
        }
    }

    return function_exists('mb_substr') ? mb_substr($refHost, 0, 60, 'UTF-8') : substr($refHost, 0, 60);
}

/** 当前请求是否爬虫 / 预取 */
function ecod_stats_is_bot(string $ua): bool
{
    if ($ua === '') {
        return true;
    }
    if (preg_match('/bot|spider|crawl|slurp|archiver|curl|wget|python|java\/|go-http|headless|phantom|monitor|preview/i', $ua)) {
        return true;
    }
    return false;
}

/**
 * 静态资源 / 非页面请求：不计 PV
 *
 * favicon、主题资源、订阅源每次都会请求，混进来会把 PV 和 UV 一起拉高；
 * 注意不能把 .html 排除掉 —— 文章固定链接就是 .html 结尾。
 */
function ecod_stats_is_asset(string $path): bool
{
    if ($path === '/favicon.ico' || $path === '/robots.txt' || $path === '/feed' || $path === '/feed/') {
        return true;
    }
    /* /usr/ 是主题与插件的资源目录，/admin/ 是后台 */
    if (strncmp($path, '/usr/', 5) === 0 || strncmp($path, '/admin/', 7) === 0) {
        return true;
    }

    static $skip = [
        'ico' => 1, 'png' => 1, 'jpg' => 1, 'jpeg' => 1, 'gif' => 1, 'webp' => 1, 'avif' => 1, 'svg' => 1, 'bmp' => 1,
        'css' => 1, 'js' => 1, 'mjs' => 1, 'map' => 1,
        'woff' => 1, 'woff2' => 1, 'ttf' => 1, 'otf' => 1, 'eot' => 1,
        'mp4' => 1, 'webm' => 1, 'mp3' => 1, 'ogg' => 1, 'wav' => 1, 'm4a' => 1,
        'zip' => 1, 'gz' => 1, 'br' => 1, 'tar' => 1,
        'txt' => 1, 'xml' => 1, 'webmanifest' => 1,
    ];
    $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

    return $ext !== '' && isset($skip[$ext]);
}

/**
 * 不该计入 PV 的请求
 *
 * 三种要排除：
 *   1. 浏览器预取 / 预渲染（Sec-Purpose 等）；
 *   2. 主题自己的悬停预取（X-Ecod-Prefetch: 1）—— 用户可能根本没点；
 *   3. 主题的分类 tab / 排序局部流（X-Requested-With: Ecod-Ajax）——
 *      一次筛选算一次 PV 会把数字放大两三倍，口径对齐 WordPress 的「一次页面加载 = 1 PV」。
 * PJAX 换页（Ecod-PJAX）不排除：那是真实的整页浏览。
 */
function ecod_stats_is_prefetch(): bool
{
    if ((string) ($_SERVER['HTTP_X_ECOD_PREFETCH'] ?? '') === '1') {
        return true;
    }
    if ((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'Ecod-Ajax') {
        return true;
    }
    foreach (['HTTP_SEC_PURPOSE', 'HTTP_X_PURPOSE', 'HTTP_X_MOZ'] as $k) {
        $v = strtolower((string) ($_SERVER[$k] ?? ''));
        if ($v === 'prefetch' || $v === 'prerender' || $v === 'preview') {
            return true;
        }
    }
    return false;
}

/**
 * 记录一次访问（只攒在内存里，shutdown 时才落库）
 *
 * @param mixed $widget Typecho Archive，用来判断文章页与登录用户
 */
function ecod_stats_hit($widget = null): void
{
    if (!ecod_stats_enabled() || !empty($GLOBALS['ecodStatsDone'])) {
        return;
    }
    $GLOBALS['ecodStatsDone'] = true;

    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || ecod_stats_is_prefetch()) {
        return;
    }

    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (ecod_stats_is_bot($ua)) {
        return;
    }

    /* 管理员自己：默认不计（后台可关掉这条豁免） */
    if (ecod_stats_opt('EStatsMine', 'on') === 'on') {
        try {
            $user = \Widget\User::alloc();
            if ($user->hasLogin() && $user->pass('administrator', true)) {
                return;
            }
        } catch (\Throwable $e) {
        }
    }

    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = (string) (parse_url('http://ecod.local' . $uri, PHP_URL_PATH) ?: '/');
    if ($path === '' || $path[0] !== '/') {
        $path = '/' . ltrim($path, '/');
    }
    if (strlen($path) > ECOD_STATS_KEY_MAX) {
        $path = substr($path, 0, ECOD_STATS_KEY_MAX);
    }

    /* 静态资源 / 订阅源不算 PV：否则 favicon 每次请求都计一次，还会种出假访客 */
    if (ecod_stats_is_asset($path)) {
        return;
    }

    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $host = preg_replace('/:\d+$/', '', $host) ?: '';

    /* 会话：30 分钟滑动窗口，没有会话 cookie 就视为新会话，记落地页 */
    $newSession = empty($_COOKIE['ecod_ss']);
    if ($newSession && !headers_sent()) {
        $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;
        setcookie('ecod_ss', '1', [
            'expires'  => time() + 1800,
            'path'     => '/',
            'httponly' => true,
            'secure'   => $https,
            'samesite' => 'Lax',
        ]);
    }

    [$device, $os, $browser] = ecod_stats_ua($ua);

    /* 访客 ID：没有就趁响应头还没发种一枚（shutdown 阶段 setcookie 已无效，
       放在这里才能保证「首次访问即拿到稳定 vid」，否则 UV 会虚高）。
       与 api.php 点赞指纹是同一枚 ecod_vid，一次生成两处共用。 */
    $vid = ecod_stats_vid();
    if ($vid === '') {
        $vid = ecod_stats_vid_issue();
    }

    $GLOBALS['ecodStatsHit'] = [
        'day'     => ecod_stats_today(),
        'path'    => $path,
        'ref'     => ecod_stats_ref((string) ($_SERVER['HTTP_REFERER'] ?? ''), $host),
        'device'  => $device,
        'os'      => $os,
        'browser' => $browser,
        'sess'    => $newSession ? 1 : 0,
        'vid'     => $vid,
        'ua'      => $ua,
    ];

    if (!empty($GLOBALS['ecodStatsShutdown'])) {
        return;
    }
    $GLOBALS['ecodStatsShutdown'] = true;
    register_shutdown_function('ecod_stats_flush');
}

/**
 * shutdown 落库：先把响应发完再写，前台不等数据库
 */
function ecod_stats_flush(): void
{
    $hit = $GLOBALS['ecodStatsHit'] ?? null;
    if (!is_array($hit) || empty($hit)) {
        return;
    }
    $GLOBALS['ecodStatsHit'] = null;

    /* 响应已完整输出，后面写库的耗时不再计入用户等待 */
    if (function_exists('fastcgi_finish_request')) {
        try {
            fastcgi_finish_request();
        } catch (\Throwable $e) {
        }
    }

    try {
        $db = \Typecho\Db::get();
    } catch (\Throwable $e) {
        return;
    }
    if (!ecod_stats_ensure($db)) {
        return;
    }

    try {
        $day = $hit['day'];
        $vid = (string) $hit['vid'];

        /* UV：当天首次出现才 +1 */
        $isNew = false;
        if ($vid !== '') {
            $exists = $db->fetchRow(
                $db->select('vid')->from('table.ecod_stats_uv')
                    ->where('day = ?', $day)->where('vid = ?', $vid)->limit(1)
            );
            if (!$exists) {
                try {
                    $db->query(
                        'INSERT INTO ' . ecod_stats_table('uv') . ' (day, vid) VALUES ('
                        . ecod_stats_q($db, $day) . ', ' . ecod_stats_q($db, $vid) . ')'
                    );
                    $isNew = true;
                } catch (\Throwable $e) {
                    /* 并发下主键冲突 = 别人刚记过，按老访客处理 */
                }
            }
        }

        ecod_stats_inc($db, ecod_stats_table('day'), ['day' => $day], [
            'pv'    => 1,
            'uv'    => $isNew ? 1 : 0,
            'sess'  => (int) $hit['sess'],
            'dur'   => 0,
            'dur_n' => 0,
            'bounce' => 0,
        ]);

        $dims = [
            'page'    => $hit['path'],
            'ref'     => $hit['ref'],
            'device'  => $hit['device'],
            'os'      => $hit['os'],
            'browser' => $hit['browser'],
        ];
        if ((int) $hit['sess'] === 1) {
            $dims['entry'] = $hit['path'];
        }
        foreach ($dims as $dim => $key) {
            ecod_stats_inc($db, ecod_stats_table('dim'), ['day' => $day, 'dim' => $dim, 'k' => (string) $key], [
                'pv' => 1,
                'uv' => $isNew ? 1 : 0,
            ]);
        }

        /* 在线心跳：覆盖时间戳，不能累加 */
        ecod_stats_set($db, ecod_stats_table('online'), ['vid' => $vid], ['touched' => time()]);

        /* 1/200 概率顺手清理过期数据，省掉定时任务 */
        if (random_int(1, 200) === 1) {
            ecod_stats_prune();
        }
    } catch (\Throwable $e) {
    }
}

/**
 * beacon 上报：停留时长与跳出
 *
 * @param array<string, mixed> $in  d=停留秒数，b=是否跳出，p=页面路径
 */
function ecod_stats_ping(array $in): bool
{
    if (!ecod_stats_enabled()) {
        return false;
    }

    $dur = (int) ($in['d'] ?? 0);
    $dur = max(0, min(3600, $dur));
    /* 跳出：离开时没换过页，且停留不足 15 秒（超过 15 秒的单页阅读是有内容的访问） */
    $bounce = (!empty($in['b']) && $dur < 15) ? 1 : 0;

    try {
        $db = \Typecho\Db::get();
    } catch (\Throwable $e) {
        return false;
    }
    if (!ecod_stats_ensure($db)) {
        return false;
    }

    try {
        ecod_stats_inc($db, ecod_stats_table('day'), ['day' => ecod_stats_today()], [
            'pv'     => 0,
            'uv'     => 0,
            'sess'   => 0,
            'dur'    => $dur,
            'dur_n'  => 1,
            'bounce' => $bounce,
        ]);
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

/** 在线人数：5 分钟内有心跳的访客数 */
function ecod_stats_online(int $window = 300): int
{
    try {
        $db = \Typecho\Db::get();
        if (!ecod_stats_ensure($db)) {
            return 0;
        }
        $row = $db->fetchObject(
            $db->select('COUNT(*) AS num')->from('table.ecod_stats_online')
                ->where('touched > ?', time() - $window)
        );
        return $row ? (int) $row->num : 0;
    } catch (\Throwable $e) {
        return 0;
    }
}

/** 单日汇总，没有记录时返回零值 */
function ecod_stats_day(string $day): array
{
    $out = ['day' => $day, 'pv' => 0, 'uv' => 0, 'sess' => 0, 'dur' => 0, 'dur_n' => 0, 'bounce' => 0];
    try {
        $db = \Typecho\Db::get();
        if (!ecod_stats_ensure($db)) {
            return $out;
        }
        $row = $db->fetchRow($db->select()->from('table.ecod_stats_day')->where('day = ?', $day)->limit(1));
        if ($row) {
            foreach ($out as $k => $v) {
                if (isset($row[$k])) {
                    $out[$k] = is_numeric($row[$k]) ? (int) $row[$k] : $row[$k];
                }
            }
        }
    } catch (\Throwable $e) {
    }
    return $out;
}

/**
 * 近 N 天序列（含今天，缺数据的日期补 0）
 *
 * @return array<int, array<string, mixed>>
 */
function ecod_stats_days(int $days = 7): array
{
    $days = max(1, min(365, $days));
    $map = [];
    try {
        $db = \Typecho\Db::get();
        if (ecod_stats_ensure($db)) {
            $from = ecod_stats_day_ago($days - 1);
            $rows = $db->fetchAll(
                $db->select()->from('table.ecod_stats_day')->where('day >= ?', $from)->order('day', \Typecho\Db::SORT_ASC)
            );
            foreach ($rows ?: [] as $row) {
                $map[(string) $row['day']] = $row;
            }
        }
    } catch (\Throwable $e) {
    }

    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = ecod_stats_day_ago($i);
        $row = $map[$d] ?? [];
        $out[] = [
            'day'    => $d,
            'pv'     => (int) ($row['pv'] ?? 0),
            'uv'     => (int) ($row['uv'] ?? 0),
            'sess'   => (int) ($row['sess'] ?? 0),
            'dur'    => (int) ($row['dur'] ?? 0),
            'dur_n'  => (int) ($row['dur_n'] ?? 0),
            'bounce' => (int) ($row['bounce'] ?? 0),
        ];
    }
    return $out;
}

/**
 * 维度 TOP N
 *
 * @param string $dim page / ref / entry / device / os / browser
 * @return array<int, array{k:string, pv:int, uv:int}>
 */
function ecod_stats_top(string $dim, int $days = 7, int $limit = 10): array
{
    $limit = max(1, min(50, $limit));
    $days = max(1, min(365, $days));

    try {
        $db = \Typecho\Db::get();
        if (!ecod_stats_ensure($db)) {
            return [];
        }
        $from = ecod_stats_day_ago($days - 1);
        $rows = $db->fetchAll(
            $db->select('k', 'SUM(pv) AS pv', 'SUM(uv) AS uv')
                ->from('table.ecod_stats_dim')
                ->where('dim = ?', $dim)
                ->where('day >= ?', $from)
                ->group('k')
                ->order('pv', \Typecho\Db::SORT_DESC)
                ->limit($limit)
        );
        $out = [];
        foreach ($rows ?: [] as $row) {
            $out[] = ['k' => (string) $row['k'], 'pv' => (int) $row['pv'], 'uv' => (int) $row['uv']];
        }
        return $out;
    } catch (\Throwable $e) {
        return [];
    }
}

/** 区间合计（含今天往前 N 天） */
function ecod_stats_sum(int $days = 7): array
{
    $sum = ['pv' => 0, 'uv' => 0, 'sess' => 0, 'dur' => 0, 'dur_n' => 0, 'bounce' => 0];
    foreach (ecod_stats_days($days) as $row) {
        foreach ($sum as $k => $v) {
            $sum[$k] += (int) ($row[$k] ?? 0);
        }
    }
    return $sum;
}

/** 清理超过保留期的数据；返回是否执行成功（0 = 失败或表未就绪） */
function ecod_stats_prune(?int $keepDays = null): int
{
    $keep = $keepDays ?? (int) ecod_stats_opt('EStatsKeep', 90);
    $keep = max(7, min(3650, $keep));
    $cutoff = ecod_stats_day_ago($keep);
    $removed = 0;

    try {
        $db = \Typecho\Db::get();
        if (!ecod_stats_ensure($db)) {
            return 0;
        }
        foreach (['day', 'dim', 'uv'] as $t) {
            $db->query('DELETE FROM ' . ecod_stats_table($t) . ' WHERE day < ' . ecod_stats_q($db, $cutoff));
        }
        $db->query('DELETE FROM ' . ecod_stats_table('online') . ' WHERE touched < ' . (time() - 3600));
        $removed = 1;
    } catch (\Throwable $e) {
        return 0;
    }

    return $removed;
}

/** 清空全部统计数据（后台按钮） */
function ecod_stats_reset(): bool
{
    try {
        $db = \Typecho\Db::get();
        if (!ecod_stats_ensure($db)) {
            return false;
        }
        foreach (['day', 'dim', 'uv', 'online'] as $t) {
            $db->query('DELETE FROM ' . ecod_stats_table($t));
        }
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}
