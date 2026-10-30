<?php
/**
 * Ecod Theme · 自定义短链
 *
 * ecod_shortlinks 表：一行一条「key → 目标地址」，带点击计数。
 * 与外链中转共用 ?go=<值> 入口（go.php ecod_go_intercept）：
 *   ?go=<base64>.<sig>  带签名的自动中转 token（不变）
 *   ?go=<key>           自定义短链（3-32 位字母数字），命中表后跳目标并 +1 点击
 *
 * 正文改写：ecod_go_rewrite 里优先命中短链映射（目标地址完全一致才命中），
 * 适合把淘宝客 / 推荐链接换成固定短码；管理台在后台「站长 → 工具与备份」。
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
 * 表名（带前缀）
 */
function ecod_golink_table(): string
{
    try {
        return \Typecho\Db::get()->getPrefix() . 'ecod_shortlinks';
    } catch (\Throwable $e) {
        return 'typecho_ecod_shortlinks';
    }
}

/**
 * 建表（首次写入时自愈，与 ecod_notifs_ensure 同款套路）
 */
function ecod_golink_ensure(?\Typecho\Db $db = null): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }

    try {
        $db = $db ?: \Typecho\Db::get();
        $table = ecod_golink_table();
        $adapter = ecod_schema_adapter($db);

        if ($adapter === 'sqlite') {
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $table . '" ('
                . ' "id" INTEGER PRIMARY KEY AUTOINCREMENT,'
                . ' "skey" VARCHAR(32) NOT NULL UNIQUE,'
                . ' "target" VARCHAR(190) NOT NULL,'
                . ' "count" INTEGER NOT NULL DEFAULT 0,'
                . ' "created" INTEGER NOT NULL DEFAULT 0)'
            );
        } elseif ($adapter === 'pgsql') {
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $table . '" ('
                . ' "id" SERIAL PRIMARY KEY,'
                . ' "skey" VARCHAR(32) NOT NULL UNIQUE,'
                . ' "target" VARCHAR(190) NOT NULL,'
                . ' "count" INTEGER NOT NULL DEFAULT 0,'
                . ' "created" INTEGER NOT NULL DEFAULT 0)'
            );
        } else {
            $db->query(
                'CREATE TABLE IF NOT EXISTS `' . $table . '` ('
                . ' `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,'
                . ' `skey` VARCHAR(32) NOT NULL,'
                . ' `target` VARCHAR(190) NOT NULL,'
                . ' `count` INT UNSIGNED NOT NULL DEFAULT 0,'
                . ' `created` INT UNSIGNED NOT NULL DEFAULT 0,'
                . ' PRIMARY KEY (`id`), UNIQUE KEY `skey` (`skey`)'
                . ') DEFAULT CHARSET=utf8mb4'
            );
        }
        return $ok = true;
    } catch (\Throwable $e) {
        error_log('[ecod-golink] ensure: ' . $e->getMessage());
        return $ok = false;
    }
}

/**
 * 合法 key：3-32 位字母数字（下划线中划线），且不能像签名 token（禁含点）
 */
function ecod_golink_valid_key(string $key): bool
{
    return (bool) preg_match('#^[A-Za-z0-9_\-]{3,32}$#', $key);
}

/**
 * 全部短链（请求内缓存；正文改写每次都要查映射）
 */
function ecod_golink_all(): array
{
    static $rows = null;
    if ($rows !== null) {
        return $rows;
    }

    $rows = [];
    try {
        $db = \Typecho\Db::get();
        if (ecod_golink_ensure($db)) {
            $rows = $db->fetchAll(
                $db->select('id', 'skey', 'target', 'count', 'created')
                    ->from(ecod_golink_table())
                    ->order('id', \Typecho\Db::SORT_ASC)
                    ->limit(500)
            );
        }
    } catch (\Throwable $e) {
    }
    return $rows;
}

/**
 * 取单条
 */
function ecod_golink_get(string $key): ?array
{
    if (!ecod_golink_valid_key($key)) {
        return null;
    }
    foreach (ecod_golink_all() as $row) {
        if (hash_equals((string) $row['skey'], $key)) {
            return $row;
        }
    }
    return null;
}

/**
 * 目标地址 → key 映射（完全一致才命中，供正文改写）
 */
function ecod_golink_map(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $map = [];
    foreach (ecod_golink_all() as $row) {
        $map[(string) $row['target']] = (string) $row['skey'];
    }
    return $map;
}

/**
 * URL 是否命中短链，命中返回 key
 */
function ecod_golink_key_for(string $url): string
{
    $map = ecod_golink_map();
    if ($map === []) {
        return '';
    }
    return (string) ($map[$url] ?? '');
}

/**
 * 短链跳转地址（与自动中转共用 ?go= 入口，避开 /go/ 路由的 404 兼容问题）
 */
function ecod_golink_url(string $key): string
{
    try {
        $base = rtrim((string) \Widget\Options::alloc()->siteUrl, '/');
    } catch (\Throwable $e) {
        $base = '';
    }
    return $base . '/?go=' . rawurlencode($key);
}

/**
 * 点击 +1（原子自增）
 */
function ecod_golink_hit(string $key): void
{
    try {
        $db = \Typecho\Db::get();
        if (ecod_golink_ensure($db)) {
            $db->query(
                $db->update(ecod_golink_table())
                    ->expression('count', 'count + 1', false)
                    ->where('skey = ?', $key)
            );
        }
    } catch (\Throwable $e) {
    }
}

/**
 * 新增 / 修改（后台管理台调用）
 */
function ecod_golink_save(string $key, string $target): array
{
    $key = trim($key);
    $target = trim($target);

    if ($key === '') {
        $key = substr(strtr(base64_encode(random_bytes(9)), '+/', '-_'), 0, 8);
    }
    if (!ecod_golink_valid_key($key)) {
        return ['ok' => false, 'message' => '短码须为 3-32 位字母、数字、下划线或中划线'];
    }
    if (!preg_match('#^https?://#i', $target)) {
        return ['ok' => false, 'message' => '目标地址必须以 http:// 或 https:// 开头'];
    }
    if (mb_strlen($target, 'UTF-8') > 190) {
        return ['ok' => false, 'message' => '目标地址过长（≤190 字符）'];
    }

    try {
        $db = \Typecho\Db::get();
        if (!ecod_golink_ensure($db)) {
            return ['ok' => false, 'message' => '数据表创建失败，请查看站点日志'];
        }
        $table = ecod_golink_table();

        /* 目标地址与其它短链重复时提示（改写映射会二义） */
        $dup = $db->fetchRow($db->select('skey')->from($table)->where('target = ?', $target)->limit(1));
        if ($dup && !hash_equals((string) $dup['skey'], $key)) {
            return ['ok' => false, 'message' => '该目标地址已存在短码「' . $dup['skey'] . '」'];
        }

        $exist = $db->fetchRow($db->select('id')->from($table)->where('skey = ?', $key)->limit(1));
        if ($exist) {
            $db->query($db->update($table)->rows(['target' => $target])->where('skey = ?', $key));
        } else {
            $db->query($db->insert($table)->rows([
                'skey'    => $key,
                'target'  => $target,
                'count'   => 0,
                'created' => time(),
            ]));
        }
        return ['ok' => true, 'message' => '已保存「' . $key . '」→ ' . $target];
    } catch (\Throwable $e) {
        return ['ok' => false, 'message' => '保存失败：' . $e->getMessage()];
    }
}

/**
 * 删除（后台管理台调用）
 */
function ecod_golink_delete(string $key): array
{
    if (!ecod_golink_valid_key($key)) {
        return ['ok' => false, 'message' => '短码非法'];
    }
    try {
        $db = \Typecho\Db::get();
        if (!ecod_golink_ensure($db)) {
            return ['ok' => false, 'message' => '数据表不可用'];
        }
        $db->query($db->delete(ecod_golink_table())->where('skey = ?', $key));
        return ['ok' => true, 'message' => '已删除「' . $key . '」'];
    } catch (\Throwable $e) {
        return ['ok' => false, 'message' => '删除失败：' . $e->getMessage()];
    }
}
