<?php
/**
 * Ecod Theme · 主题配置快照
 *
 * 直接把 table.options 里 `theme:{当前主题}` 那一行的值复制成一份新快照，
 * 恢复时再写回去。不建表、不动其他主题、不受导出/导入 JSON 的字段差异影响。
 *
 * 快照命名：ecodb-{主题哈希4位}-{YmdHis}
 * （options.name 只有 32 字符，不能用完整的 theme: 前缀）
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 * @license   MIT
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/** 最多保留的快照数量 */
define('ECOD_SNAPSHOT_MAX', 10);

/**
 * 当前主题目录名
 */
function ecod_snapshot_theme(): string
{
    try {
        return (string) \Utils\Helper::options()->theme;
    } catch (\Throwable $e) {
        return '';
    }
}

/**
 * 正在使用的配置行名
 */
function ecod_snapshot_live(): string
{
    return 'theme:' . ecod_snapshot_theme();
}

/**
 * 快照行名前缀
 */
function ecod_snapshot_prefix(): string
{
    $theme = ecod_snapshot_theme();
    return 'ecodb-' . substr(md5($theme), 0, 4) . '-';
}

/**
 * 解码一行配置
 *
 * Typecho 1.3 存 JSON，1.2 及更早存 serialize，两种都接受。
 *
 * @return array|null
 */
function ecod_snapshot_decode(string $raw): ?array
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    if ($raw[0] === '{' || $raw[0] === '[') {
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }
    $data = @unserialize($raw);
    return is_array($data) ? $data : null;
}

/**
 * 全部快照，按时间倒序
 *
 * @return array<int, array{name: string, time: int, label: string, count: int, size: int}>
 */
function ecod_snapshot_list(): array
{
    $theme = ecod_snapshot_theme();
    if ($theme === '') {
        return [];
    }

    try {
        $db = \Typecho\Db::get();
        $rows = $db->fetchAll(
            $db->select('name', 'value')->from('table.options')
                ->where('name LIKE ?', ecod_snapshot_prefix() . '%')
                ->where('user = ?', 0)
        );
    } catch (\Throwable $e) {
        return [];
    }

    $offset = 0;
    try {
        $offset = (int) \Utils\Helper::options()->timezone;
    } catch (\Throwable $e) {
    }

    $out = [];
    foreach ($rows as $row) {
        $name = (string) $row['name'];
        $raw = (string) $row['value'];
        $data = ecod_snapshot_decode($raw);
        if ($data === null) {
            continue;
        }
        $time = 0;
        if (preg_match('/-(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})$/', $name, $m)) {
            $time = (int) mktime((int) $m[4], (int) $m[5], (int) $m[6], (int) $m[2], (int) $m[3], (int) $m[1]);
        }
        $out[] = [
            'name'  => $name,
            'time'  => $time,
            'label' => $time > 0 ? date('Y-m-d H:i:s', $time + $offset) : $name,
            'count' => count($data),
            'size'  => strlen($raw),
        ];
    }

    usort($out, static function (array $a, array $b): int {
        return $b['time'] <=> $a['time'];
    });

    return $out;
}

/**
 * 新建一份快照
 *
 * @return array{ok: bool, message: string}
 */
function ecod_snapshot_create(): array
{
    $live = ecod_snapshot_live();
    if (ecod_snapshot_theme() === '') {
        return ['ok' => false, 'message' => '无法识别当前主题'];
    }

    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('value')->from('table.options')
                ->where('name = ?', $live)->where('user = ?', 0)->limit(1)
        );
    } catch (\Throwable $e) {
        return ['ok' => false, 'message' => '读取当前配置失败'];
    }

    if (!$row || !isset($row['value']) || (string) $row['value'] === '') {
        return ['ok' => false, 'message' => '当前主题还没有保存过配置'];
    }

    $data = ecod_snapshot_decode((string) $row['value']);
    if ($data === null) {
        return ['ok' => false, 'message' => '配置数据格式异常，未创建快照'];
    }

    $name = ecod_snapshot_prefix() . date('YmdHis');

    try {
        /* 同一秒重复点击：覆盖同名快照 */
        $exists = $db->fetchRow(
            $db->select('name')->from('table.options')
                ->where('name = ?', $name)->where('user = ?', 0)->limit(1)
        );
        if ($exists) {
            $db->query(
                $db->update('table.options')->rows(['value' => (string) $row['value']])
                    ->where('name = ?', $name)->where('user = ?', 0)
            );
        } else {
            $db->query(
                $db->insert('table.options')->rows([
                    'name'  => $name,
                    'user'  => 0,
                    'value' => (string) $row['value'],
                ])
            );
        }
    } catch (\Throwable $e) {
        return ['ok' => false, 'message' => '写入快照失败：' . $e->getMessage()];
    }

    ecod_snapshot_trim();

    return ['ok' => true, 'message' => '已保存 ' . count($data) . ' 项配置', 'name' => $name];
}

/**
 * 只保留最近 N 份
 */
function ecod_snapshot_trim(): void
{
    $list = ecod_snapshot_list();
    if (count($list) <= ECOD_SNAPSHOT_MAX) {
        return;
    }

    try {
        $db = \Typecho\Db::get();
        foreach (array_slice($list, ECOD_SNAPSHOT_MAX) as $item) {
            $db->query(
                $db->delete('table.options')
                    ->where('name = ?', $item['name'])->where('user = ?', 0)
            );
        }
    } catch (\Throwable $e) {
        // 清理失败不影响主流程
    }
}

/**
 * 校验快照名是否属于当前主题
 */
function ecod_snapshot_valid(string $name): bool
{
    $prefix = ecod_snapshot_prefix();
    return $prefix !== '' && strpos($name, $prefix) === 0
        && preg_match('/^ecodb-[a-f0-9]{4}-\d{14}$/', $name) === 1;
}

/**
 * 恢复快照
 *
 * @return array{ok: bool, message: string}
 */
function ecod_snapshot_restore(string $name): array
{
    if (!ecod_snapshot_valid($name)) {
        return ['ok' => false, 'message' => '快照名不合法'];
    }

    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('value')->from('table.options')
                ->where('name = ?', $name)->where('user = ?', 0)->limit(1)
        );
    } catch (\Throwable $e) {
        return ['ok' => false, 'message' => '读取快照失败'];
    }

    if (!$row || (string) $row['value'] === '') {
        return ['ok' => false, 'message' => '快照不存在'];
    }

    $data = ecod_snapshot_decode((string) $row['value']);
    if ($data === null) {
        return ['ok' => false, 'message' => '快照内容已损坏'];
    }

    $live = ecod_snapshot_live();

    try {
        /* 先把当前配置存一份，避免恢复错了无法回头 */
        ecod_snapshot_create();

        $exists = $db->fetchRow(
            $db->select('name')->from('table.options')
                ->where('name = ?', $live)->where('user = ?', 0)->limit(1)
        );
        if ($exists) {
            $db->query(
                $db->update('table.options')->rows(['value' => (string) $row['value']])
                    ->where('name = ?', $live)->where('user = ?', 0)
            );
        } else {
            $db->query(
                $db->insert('table.options')->rows([
                    'name'  => $live,
                    'user'  => 0,
                    'value' => (string) $row['value'],
                ])
            );
        }
    } catch (\Throwable $e) {
        return ['ok' => false, 'message' => '恢复失败：' . $e->getMessage()];
    }

    return ['ok' => true, 'message' => '已恢复 ' . count($data) . ' 项配置，刷新后台即可看到'];
}

/**
 * 删除快照
 *
 * @return array{ok: bool, message: string}
 */
function ecod_snapshot_delete(string $name): array
{
    if (!ecod_snapshot_valid($name)) {
        return ['ok' => false, 'message' => '快照名不合法'];
    }

    try {
        \Typecho\Db::get()->query(
            \Typecho\Db::get()->delete('table.options')
                ->where('name = ?', $name)->where('user = ?', 0)
        );
    } catch (\Throwable $e) {
        return ['ok' => false, 'message' => '删除失败'];
    }

    return ['ok' => true, 'message' => '已删除'];
}

/**
 * 后台工具令牌
 *
 * 随登录态与站点 secret 变化，外部无法伪造；用于 api.php 的工具类动作。
 */
function ecod_tool_token(): string
{
    try {
        $options = \Utils\Helper::options();
        $user = \Widget\User::alloc();
        return substr(hash_hmac('sha256', (int) $user->uid . '|ecod-tool', (string) $options->secret), 0, 32);
    } catch (\Throwable $e) {
        return '';
    }
}
