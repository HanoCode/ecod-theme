<?php
/**
 * Ecod Theme · 工单
 *
 * 用户侧完整闭环：提交 → 客服回复 → 用户追问 → 关闭。
 * 管理端在 Ecod Console 插件（/console#tickets），两侧共用两张表。
 *
 * 存储状态只有三种（open / resolved / closed），「已回复 / 待你回复」是由
 * last_side 派生的展示态——这样状态列不会因为一来一回的对话反复翻转：
 *   open + last_side=user   → 待客服处理（pending）
 *   open + last_side=staff  → 客服已回复（replied）
 *
 * 内部备注（side=note）只给后台看，ecod_ticket_detail() 永不下发。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/* 外部上下文（Console 插件）自举：schema 层可能还没加载 */
if (!function_exists('ecod_schema_adapter')) {
    require_once __DIR__ . '/../lib/schema.php';
}

/**
 * 工单分类（key => 中文名）
 *
 * @return array<string, string>
 */
function ecod_ticket_cats(): array
{
    return [
        'account' => '账号与登录',
        'pay'     => '付费与订单',
        'report'  => '内容举报',
        'idea'    => '功能建议',
        'other'   => '其他',
    ];
}

/**
 * 派生展示态：pending / replied / resolved / closed
 */
function ecod_ticket_derived(array $row): string
{
    $status = (string) ($row['status'] ?? 'open');
    if ($status === 'closed') {
        return 'closed';
    }
    if ($status === 'resolved') {
        return 'resolved';
    }
    return (string) ($row['last_side'] ?? 'user') === 'staff' ? 'replied' : 'pending';
}

/**
 * 展示态中文文案
 */
function ecod_ticket_state_text(string $derived): string
{
    $map = [
        'pending'  => '待处理',
        'replied'  => '已回复',
        'resolved' => '已解决',
        'closed'   => '已关闭',
    ];
    return $map[$derived] ?? $derived;
}

/**
 * 工单号：TK + 日期 + 4 位十六进制，人工可读且足够短
 */
function ecod_ticket_no(): string
{
    try {
        $tail = strtoupper(bin2hex(random_bytes(2)));
    } catch (\Throwable $e) {
        $tail = strtoupper(substr(md5((string) mt_rand()), 0, 4));
    }
    return 'TK' . date('Ymd') . '-' . $tail;
}

/**
 * 表就绪（懒建，schema_upgrade 之外再兜一层）
 */
function ecod_ticket_ready(): bool
{
    try {
        return ecod_schema_ensure_tickets(\Typecho\Db::get());
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * 工单列表 + 统计
 *
 * @param string $status 派生态筛选：'' 全部 / pending / replied / resolved / closed
 * @return array{items:array, stats:array, has_more:bool}
 */
function ecod_ticket_list(int $uid, string $status = '', int $page = 1, int $per = 20): array
{
    $out = [
        'items'    => [],
        'stats'    => ['pending' => 0, 'replied' => 0, 'resolved' => 0, 'closed' => 0],
        'has_more' => false,
    ];
    if ($uid <= 0 || !ecod_ticket_ready()) {
        return $out;
    }

    $per = max(1, min(50, $per));
    $page = max(1, $page);
    $offset = ($page - 1) * $per;

    try {
        $db = \Typecho\Db::get();
        $table = $db->getPrefix() . 'ecod_tickets';

        /* 统计：三条 COUNT（含「全部」共四组），足够便宜 */
        $out['stats'] = ecod_ticket_stats($uid);

        $query = $db->select()->from($table)->where('uid = ?', $uid);
        if ($status === 'closed') {
            $query->where('status = ?', 'closed');
        } elseif ($status === 'resolved') {
            $query->where('status = ?', 'resolved');
        } elseif ($status === 'pending') {
            $query->where('status = ?', 'open')->where('last_side = ?', 'user');
        } elseif ($status === 'replied') {
            $query->where('status = ?', 'open')->where('last_side = ?', 'staff');
        }
        $rows = $db->fetchAll(
            $query->order('updated', \Typecho\Db::SORT_DESC)->order('id', \Typecho\Db::SORT_DESC)
                ->limit($per + 1)->offset($offset)
        );
    } catch (\Throwable $e) {
        return $out;
    }

    $rows = (array) ($rows ?? []);
    $out['has_more'] = count($rows) > $per;
    if ($out['has_more']) {
        $rows = array_slice($rows, 0, $per);
    }

    $cats = ecod_ticket_cats();
    foreach ($rows as $row) {
        $derived = ecod_ticket_derived($row);
        $cat = (string) ($row['cat'] ?? 'other');
        $out['items'][] = [
            'id'      => (int) $row['id'],
            'no'      => (string) $row['no'],
            'cat'     => $cat,
            'catText' => $cats[$cat] ?? '其他',
            'title'   => (string) $row['title'],
            'status'  => (string) $row['status'],
            'derived' => $derived,
            'msgs'    => (int) $row['msgs'],
            'unread'  => (int) $row['unread_user'] > 0 ? 1 : 0,
            'created' => (int) $row['created'],
            'updated' => (int) $row['updated'],
        ];
    }

    return $out;
}

/**
 * 三张统计卡（处理中 / 已回复 / 已解决）
 *
 * @return array{pending:int, replied:int, resolved:int, closed:int}
 */
function ecod_ticket_stats(int $uid): array
{
    $stats = ['pending' => 0, 'replied' => 0, 'resolved' => 0, 'closed' => 0];
    if ($uid <= 0) {
        return $stats;
    }
    try {
        $db = \Typecho\Db::get();
        $table = $db->getPrefix() . 'ecod_tickets';
        $count = static function (callable $build) use ($db, $table) {
            $row = $db->fetchRow($build($db, $table));
            return (int) ($row['num'] ?? 0);
        };
        $stats['pending'] = $count(static function ($db, $table) use ($uid) {
            return $db->select('COUNT(*) AS num')->from($table)
                ->where('uid = ?', $uid)->where('status = ?', 'open')->where('last_side = ?', 'user');
        });
        $stats['replied'] = $count(static function ($db, $table) use ($uid) {
            return $db->select('COUNT(*) AS num')->from($table)
                ->where('uid = ?', $uid)->where('status = ?', 'open')->where('last_side = ?', 'staff');
        });
        $stats['resolved'] = $count(static function ($db, $table) use ($uid) {
            return $db->select('COUNT(*) AS num')->from($table)
                ->where('uid = ?', $uid)->where('status = ?', 'resolved');
        });
        $stats['closed'] = $count(static function ($db, $table) use ($uid) {
            return $db->select('COUNT(*) AS num')->from($table)
                ->where('uid = ?', $uid)->where('status = ?', 'closed');
        });
        return $stats;
    } catch (\Throwable $e) {
        return $stats;
    }
}

/**
 * 工单详情 + 对话（读后清未读）
 *
 * @return array{ok:bool, error?:string, ticket?:array, msgs?:array}
 */
function ecod_ticket_detail(int $uid, int $id): array
{
    if ($uid <= 0) {
        return ['ok' => false, 'error' => '请先登录'];
    }
    if (!ecod_ticket_ready()) {
        return ['ok' => false, 'error' => '工单暂不可用'];
    }

    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select()->from($db->getPrefix() . 'ecod_tickets')
                ->where('id = ?', $id)->where('uid = ?', $uid)->limit(1)
        );
        if (!$row) {
            return ['ok' => false, 'error' => '工单不存在'];
        }

        $msgs = $db->fetchAll(
            $db->select('id', 'side', 'uid', 'body', 'created')
                ->from($db->getPrefix() . 'ecod_ticket_msgs')
                ->where('tid = ?', $id)
                /* 内部备注前台永不下发 */
                ->where('side != ?', 'note')
                ->order('id', \Typecho\Db::SORT_ASC)
                ->limit(200)
        );

        if ((int) $row['unread_user'] > 0) {
            $db->query(
                $db->update($db->getPrefix() . 'ecod_tickets')
                    ->rows(['unread_user' => 0])
                    ->where('id = ?', $id)->where('uid = ?', $uid)
            );
        }
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => '读取失败'];
    }

    $cats = ecod_ticket_cats();
    $derived = ecod_ticket_derived($row);
    $list = [];
    foreach ((array) $msgs as $m) {
        $side = (string) $m['side'];
        $list[] = [
            'id'      => (int) $m['id'],
            'side'    => $side === 'staff' ? 'staff' : 'user',
            'body'    => (string) $m['body'],
            'created' => (int) $m['created'],
            'name'    => $side === 'staff' ? '客服' : '我',
        ];
    }

    return [
        'ok'     => true,
        'ticket' => [
            'id'      => (int) $row['id'],
            'no'      => (string) $row['no'],
            'cat'     => (string) $row['cat'],
            'catText' => $cats[(string) $row['cat']] ?? '其他',
            'title'   => (string) $row['title'],
            'status'  => (string) $row['status'],
            'derived' => $derived,
            'state'   => ecod_ticket_state_text($derived),
            'msgs'    => (int) $row['msgs'],
            'created' => (int) $row['created'],
            'updated' => (int) $row['updated'],
        ],
        'msgs'   => $list,
    ];
}

/**
 * 新建工单
 *
 * 频控：同 uid 24h ≤ 5 单、未关闭 ≤ 3 单（避免把工单当聊天框）。
 *
 * @return array{ok:bool, error?:string, id?:int, no?:string}
 */
function ecod_ticket_create(int $uid, string $cat, string $title, string $body): array
{
    if ($uid <= 0) {
        return ['ok' => false, 'error' => '请先登录'];
    }

    $cats = ecod_ticket_cats();
    if (!isset($cats[$cat])) {
        $cat = 'other';
    }
    $title = trim(preg_replace('/\s+/u', ' ', $title));
    $body = trim($body);

    $titleLen = mb_strlen($title, 'UTF-8');
    if ($titleLen < 4 || $titleLen > 60) {
        return ['ok' => false, 'error' => '标题需 4–60 字'];
    }
    $bodyLen = mb_strlen($body, 'UTF-8');
    if ($bodyLen < 10 || $bodyLen > 2000) {
        return ['ok' => false, 'error' => '详细描述需 10–2000 字'];
    }

    if (!ecod_ticket_ready()) {
        return ['ok' => false, 'error' => '工单暂不可用'];
    }

    try {
        $db = \Typecho\Db::get();
        $table = $db->getPrefix() . 'ecod_tickets';
        $msgTable = $db->getPrefix() . 'ecod_ticket_msgs';

        $dayAgo = time() - 86400;
        $recent = $db->fetchRow(
            $db->select('COUNT(*) AS num')->from($table)
                ->where('uid = ?', $uid)->where('created >= ?', $dayAgo)
        );
        if ((int) ($recent['num'] ?? 0) >= 5) {
            return ['ok' => false, 'error' => '24 小时内最多提交 5 单，请等客服处理'];
        }
        $openRow = $db->fetchRow(
            $db->select('COUNT(*) AS num')->from($table)
                ->where('uid = ?', $uid)->where('status != ?', 'closed')
        );
        if ((int) ($openRow['num'] ?? 0) >= 3) {
            return ['ok' => false, 'error' => '还有 3 单未关闭，请先处理或关闭后再提'];
        }

        $now = time();
        $tid = 0;
        $no = '';
        for ($i = 0; $i < 3; $i++) {
            $no = ecod_ticket_no();
            try {
                $db->query($db->insert($table)->rows([
                    'uid'          => $uid,
                    'no'           => $no,
                    'cat'          => $cat,
                    'title'        => $title,
                    'status'       => 'open',
                    'priority'     => 1,
                    'created'      => $now,
                    'updated'      => $now,
                    'msgs'         => 1,
                    'last_side'    => 'user',
                    'unread_user'  => 0,
                    'unread_staff' => 1,
                    'staff_uid'    => 0,
                    'closed_at'    => 0,
                ]));
                break;
            } catch (\Throwable $e) {
                $no = '';
            }
        }
        if ($no === '') {
            return ['ok' => false, 'error' => '提交失败，请稍后重试'];
        }

        $idRow = $db->fetchRow(
            $db->select('id')->from($table)->where('no = ?', $no)->limit(1)
        );
        $tid = (int) ($idRow['id'] ?? 0);
        if ($tid <= 0) {
            return ['ok' => false, 'error' => '提交失败，请稍后重试'];
        }

        $db->query($db->insert($msgTable)->rows([
            'tid'     => $tid,
            'side'    => 'user',
            'uid'     => $uid,
            'body'    => $body,
            'created' => $now,
        ]));
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => '提交失败，请稍后重试'];
    }

    return ['ok' => true, 'id' => $tid, 'no' => $no, 'stats' => ecod_ticket_stats($uid)];
}

/**
 * 用户回复工单
 *
 * 已关闭的工单拒绝写入；已解决的工单被追问时自动重开。
 *
 * @return array{ok:bool, error?:string, ticket?:array}
 */
function ecod_ticket_reply(int $uid, int $id, string $body): array
{
    if ($uid <= 0) {
        return ['ok' => false, 'error' => '请先登录'];
    }
    $body = trim($body);
    $len = mb_strlen($body, 'UTF-8');
    if ($len < 1 || $len > 2000) {
        return ['ok' => false, 'error' => '回复需 1–2000 字'];
    }
    if (!ecod_ticket_ready()) {
        return ['ok' => false, 'error' => '工单暂不可用'];
    }

    try {
        $db = \Typecho\Db::get();
        $table = $db->getPrefix() . 'ecod_tickets';
        $msgTable = $db->getPrefix() . 'ecod_ticket_msgs';

        $row = $db->fetchRow(
            $db->select('id', 'status', 'msgs')->from($table)
                ->where('id = ?', $id)->where('uid = ?', $uid)->limit(1)
        );
        if (!$row) {
            return ['ok' => false, 'error' => '工单不存在'];
        }
        if ((string) $row['status'] === 'closed') {
            return ['ok' => false, 'error' => '工单已关闭，如需继续请新建工单'];
        }

        /* 30 秒频控：查最后一条自己的消息 */
        $last = $db->fetchRow(
            $db->select('created')->from($msgTable)
                ->where('tid = ?', $id)->where('side = ?', 'user')
                ->order('id', \Typecho\Db::SORT_DESC)->limit(1)
        );
        if ($last && time() - (int) $last['created'] < 30) {
            return ['ok' => false, 'error' => '回复太快了，请 30 秒后再试'];
        }

        $now = time();
        $db->query($db->insert($msgTable)->rows([
            'tid'     => $id,
            'side'    => 'user',
            'uid'     => $uid,
            'body'    => $body,
            'created' => $now,
        ]));

        $status = (string) $row['status'] === 'resolved' ? 'open' : (string) $row['status'];
        $db->query(
            $db->update($table)->rows([
                'status'       => $status,
                'updated'      => $now,
                'msgs'         => (int) $row['msgs'] + 1,
                'last_side'    => 'user',
                'unread_staff' => 1,
                'closed_at'    => 0,
            ])->where('id = ?', $id)
        );
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => '回复失败，请稍后重试'];
    }

    return ['ok' => true] + ecod_ticket_detail_light($uid, $id);
}

/**
 * 轻量回读（回复 / 关闭后的状态同步，不清未读、不带消息体）
 *
 * @return array{ticket:array}
 */
function ecod_ticket_detail_light(int $uid, int $id): array
{
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('id', 'no', 'cat', 'title', 'status', 'last_side', 'msgs', 'unread_user', 'created', 'updated')
                ->from($db->getPrefix() . 'ecod_tickets')
                ->where('id = ?', $id)->where('uid = ?', $uid)->limit(1)
        );
        if (!$row) {
            return ['ticket' => []];
        }
    } catch (\Throwable $e) {
        return ['ticket' => []];
    }

    $derived = ecod_ticket_derived($row);
    $cats = ecod_ticket_cats();
    return [
        'ticket' => [
            'id'      => (int) $row['id'],
            'no'      => (string) $row['no'],
            'cat'     => (string) $row['cat'],
            'catText' => $cats[(string) $row['cat']] ?? '其他',
            'title'   => (string) $row['title'],
            'status'  => (string) $row['status'],
            'derived' => $derived,
            'state'   => ecod_ticket_state_text($derived),
            'msgs'    => (int) $row['msgs'],
            'unread'  => (int) $row['unread_user'] > 0 ? 1 : 0,
            'created' => (int) $row['created'],
            'updated' => (int) $row['updated'],
        ],
    ];
}

/**
 * 用户关闭工单（标记已解决，可再被追问时重开？——关闭是终态，不再重开）
 *
 * @return array{ok:bool, error?:string, ticket?:array}
 */
function ecod_ticket_close(int $uid, int $id): array
{
    if ($uid <= 0) {
        return ['ok' => false, 'error' => '请先登录'];
    }
    if (!ecod_ticket_ready()) {
        return ['ok' => false, 'error' => '工单暂不可用'];
    }

    try {
        $db = \Typecho\Db::get();
        $table = $db->getPrefix() . 'ecod_tickets';
        $row = $db->fetchRow(
            $db->select('id', 'status')->from($table)
                ->where('id = ?', $id)->where('uid = ?', $uid)->limit(1)
        );
        if (!$row) {
            return ['ok' => false, 'error' => '工单不存在'];
        }
        if ((string) $row['status'] === 'closed') {
            return ['ok' => true] + ecod_ticket_detail_light($uid, $id);
        }

        $now = time();
        $db->query(
            $db->update($table)->rows([
                'status'    => 'closed',
                'updated'   => $now,
                'closed_at' => $now,
            ])->where('id = ?', $id)->where('uid = ?', $uid)
        );
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => '关闭失败，请稍后重试'];
    }

    return ['ok' => true] + ecod_ticket_detail_light($uid, $id);
}

/**
 * 用户侧未读总数（有客服回复但还没看），用于侧栏「工单」角标
 */
function ecod_ticket_unread(int $uid): int
{
    if ($uid <= 0) {
        return 0;
    }
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('COUNT(*) AS num')->from($db->getPrefix() . 'ecod_tickets')
                ->where('uid = ?', $uid)->where('unread_user > ?', 0)
        );
        return (int) ($row['num'] ?? 0);
    } catch (\Throwable $e) {
        return 0;
    }
}
