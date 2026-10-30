<?php
/**
 * 站内消息通知
 *
 * 表 typecho_ecod_notifs：一行一条通知，只投递给登录用户（uid）。
 * 游客不落库——被回复时仍走邮件提醒（core/site/mail.php）。
 *
 * 事件来源：
 *   reply   评论回复（finishComment 钩子，父评论作者为注册用户才投）
 *   friend  友链申请审核结果（申请人邮箱命中注册用户才投）
 *   like    文章 / 评论收到点赞（api.php like / clike 写入分支）
 *   fav     文章被收藏（api.php fav_toggle 写入分支）
 *   notice  管理员全站公告（后台「工具与备份 → 站内公告」一键分发）
 *
 * 读写入口在 api.php：notif_list / notif_unread / notif_read / notif_clear / notif_broadcast。
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
function ecod_notifs_table(): string
{
    try {
        return \Typecho\Db::get()->getPrefix() . 'ecod_notifs';
    } catch (\Throwable $e) {
        return 'typecho_ecod_notifs';
    }
}

/**
 * 建表（首次写入时自愈，与 ecod_friend_apps_ensure 同款套路）
 */
function ecod_notifs_ensure(?\Typecho\Db $db = null): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }

    try {
        $db = $db ?: \Typecho\Db::get();
        $table = ecod_notifs_table();
        $adapter = ecod_schema_adapter($db);

        if ($adapter === 'sqlite') {
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $table . '" ('
                . ' "id" INTEGER PRIMARY KEY AUTOINCREMENT,'
                . ' "uid" INTEGER NOT NULL,'
                . ' "type" VARCHAR(16) NOT NULL DEFAULT \'notice\','
                . ' "title" VARCHAR(190) NOT NULL,'
                . ' "body" VARCHAR(500) NOT NULL DEFAULT \'\','
                . ' "link" VARCHAR(500) NOT NULL DEFAULT \'\','
                . ' "actor" VARCHAR(80) NOT NULL DEFAULT \'\','
                . ' "is_read" SMALLINT NOT NULL DEFAULT 0,'
                . ' "created" INTEGER NOT NULL)'
            );
            $db->query('CREATE INDEX IF NOT EXISTS "' . $table . '_uid" ON "' . $table . '" ("uid", "created")');
            $db->query('CREATE INDEX IF NOT EXISTS "' . $table . '_unread" ON "' . $table . '" ("uid", "is_read")');
        } elseif ($adapter === 'pgsql') {
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $table . '" ('
                . ' "id" SERIAL PRIMARY KEY,'
                . ' "uid" INTEGER NOT NULL,'
                . ' "type" VARCHAR(16) NOT NULL DEFAULT \'notice\','
                . ' "title" VARCHAR(190) NOT NULL,'
                . ' "body" VARCHAR(500) NOT NULL DEFAULT \'\','
                . ' "link" VARCHAR(500) NOT NULL DEFAULT \'\','
                . ' "actor" VARCHAR(80) NOT NULL DEFAULT \'\','
                . ' "is_read" SMALLINT NOT NULL DEFAULT 0,'
                . ' "created" INTEGER NOT NULL)'
            );
            $db->query('CREATE INDEX IF NOT EXISTS "' . $table . '_uid" ON "' . $table . '" ("uid", "created")');
            $db->query('CREATE INDEX IF NOT EXISTS "' . $table . '_unread" ON "' . $table . '" ("uid", "is_read")');
        } else {
            $db->query(
                'CREATE TABLE IF NOT EXISTS `' . $table . '` ('
                . ' `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,'
                . ' `uid` INT(10) UNSIGNED NOT NULL,'
                . ' `type` VARCHAR(16) NOT NULL DEFAULT \'notice\','
                . ' `title` VARCHAR(190) NOT NULL,'
                . ' `body` VARCHAR(500) NOT NULL DEFAULT \'\','
                . ' `link` VARCHAR(500) NOT NULL DEFAULT \'\','
                . ' `actor` VARCHAR(80) NOT NULL DEFAULT \'\','
                . ' `is_read` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,'
                . ' `created` INT(10) UNSIGNED NOT NULL DEFAULT 0,'
                . ' PRIMARY KEY (`id`),'
                . ' KEY `uid` (`uid`, `created`),'
                . ' KEY `unread` (`uid`, `is_read`)'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        }
        $ok = true;
    } catch (\Throwable $e) {
        $ok = false;
    }

    return $ok;
}

/**
 * 统一的「表自愈 + 执行」包装
 */
function ecod_notifs_call(callable $fn)
{
    try {
        $db = \Typecho\Db::get();
        if (!ecod_notifs_ensure($db)) {
            return null;
        }
        return $fn($db);
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * 投递一条通知（类型非法 / uid 非法直接丢弃）
 *
 * 防重：同类型同链接的「未读」通知在 24h 内只保留一条——点赞 / 收藏这种
 * 可反复触发的事件不会把消息列表刷屏；评论回复天然一条一条，不合并。
 *
 * @param int    $uid    接收人
 * @param string $type   reply / friend / like / fav / notice
 * @param string $title  一句话标题（会原样展示）
 * @param string $body   正文摘录，可空
 * @param string $link   点击跳转地址，可空
 * @param string $actor  触发人显示名，可空
 */
function ecod_notif_push(int $uid, string $type, string $title, string $body = '', string $link = '', string $actor = ''): bool
{
    if ($uid <= 0 || !in_array($type, ['reply', 'friend', 'like', 'fav', 'notice'], true)) {
        return false;
    }
    $title = trim($title);
    if ($title === '') {
        return false;
    }

    $row = [
        'uid'     => $uid,
        'type'    => $type,
        'title'   => mb_substr($title, 0, 190, 'UTF-8'),
        'body'    => mb_substr($body, 0, 500, 'UTF-8'),
        'link'    => mb_substr($link, 0, 500, 'UTF-8'),
        'actor'   => mb_substr($actor, 0, 80, 'UTF-8'),
        'is_read' => 0,
        'created' => time(),
    ];

    return (bool) ecod_notifs_call(static function (\Typecho\Db $db) use ($row) {
        /* 24h 内同类同链接的未读通知合并成一条，避免点赞/收藏刷屏 */
        if ($row['type'] !== 'reply' && $row['link'] !== '') {
            $dupe = $db->fetchRow(
                $db->select('id')->from(ecod_notifs_table())
                    ->where('uid = ?', $row['uid'])
                    ->where('type = ?', $row['type'])
                    ->where('link = ?', $row['link'])
                    ->where('is_read = ?', 0)
                    ->where('created > ?', time() - 86400)
                    ->limit(1)
            );
            if ($dupe) {
                return true;
            }
        }

        $db->query($db->insert(ecod_notifs_table())->rows($row));
        return true;
    });
}

/**
 * 未读数
 */
function ecod_notif_unread(int $uid): int
{
    return (int) (ecod_notifs_call(static function (\Typecho\Db $db) use ($uid) {
        return $db->fetchRow(
            $db->select('COUNT(*) AS num')->from(ecod_notifs_table())
                ->where('uid = ?', $uid)
                ->where('is_read = ?', 0)
        );
    })['num'] ?? 0);
}

/**
 * 消息列表（倒序，含最新 $limit 条）
 *
 * @return array<int, array{id:int,type:string,title:string,body:string,link:string,actor:string,is_read:int,created:int}>
 */
function ecod_notif_list(int $uid, int $limit = 20): array
{
    $rows = (array) (ecod_notifs_call(static function (\Typecho\Db $db) use ($uid, $limit) {
        return $db->fetchAll(
            $db->select()->from(ecod_notifs_table())
                ->where('uid = ?', $uid)
                ->order('created', \Typecho\Db::SORT_DESC)
                ->order('id', \Typecho\Db::SORT_DESC)
                ->limit(max(1, min(50, $limit)))
        );
    }) ?? []);

    $list = [];
    foreach ($rows as $row) {
        $list[] = [
            'id'      => (int) $row['id'],
            'type'    => (string) $row['type'],
            'title'   => (string) $row['title'],
            'body'    => (string) $row['body'],
            'link'    => (string) $row['link'],
            'actor'   => (string) $row['actor'],
            'is_read' => (int) $row['is_read'],
            'created' => (int) $row['created'],
        ];
    }
    return $list;
}

/**
 * 标记已读：单条（$id > 0）或全部（$id = 0）
 */
function ecod_notif_read(int $uid, int $id = 0): void
{
    ecod_notifs_call(static function (\Typecho\Db $db) use ($uid, $id) {
        $sql = $db->update(ecod_notifs_table())
            ->rows(['is_read' => 1])
            ->where('uid = ?', $uid)
            ->where('is_read = ?', 0);
        if ($id > 0) {
            $sql->where('id = ?', $id);
        }
        $db->query($sql);
        return true;
    });
}

/**
 * 清空已读的消息（未读保留）
 */
function ecod_notif_clear(int $uid): void
{
    ecod_notifs_call(static function (\Typecho\Db $db) use ($uid) {
        $db->query(
            $db->delete(ecod_notifs_table())
                ->where('uid = ?', $uid)
                ->where('is_read = ?', 1)
        );
        return true;
    });
}

/* ------------------------------------------------------------------
 * 触发器
 * ---------------------------------------------------------------- */

/**
 * 评论回复 → 通知父评论作者（仅注册用户；游客被回复走邮件）
 *
 * 挂在 Widget\Feedback 的 finishComment 钩子上（functions.php 注册）。
 * 剥 HTML 后取 120 字做摘录；自己回复自己不打扰。
 *
 * @param mixed $feedback \Widget\Feedback（评论已落库）
 */
function ecod_notif_comment($feedback = null): void
{
    try {
        if (!$feedback) {
            return;
        }

        $parentCoid = (int) ($feedback->parent ?? 0);
        if ($parentCoid <= 0) {
            return;
        }

        $db = \Typecho\Db::get();
        $parent = $db->fetchRow(
            $db->select('coid', 'ownerId', 'status')->from('table.comments')
                ->where('coid = ?', $parentCoid)->limit(1)
        );
        if (!$parent || (int) $parent['ownerId'] <= 0) {
            return; /* 游客 / 匿名父评论：无 uid 可投 */
        }

        $userId = (int) $parent['ownerId'];
        $replier = (string) ($feedback->author ?? '');
        if ($replier === '') {
            $replier = '有人';
        }

        /* 自己回复自己不打扰 */
        $replyUid = 0;
        try {
            $replyUid = (int) ($feedback->ownerId ?? 0);
        } catch (\Throwable $e) {
        }
        if ($replyUid > 0 && $replyUid === $userId) {
            return;
        }

        try {
            $link = (string) ($feedback->content->permalink ?? '');
        } catch (\Throwable $e) {
            $link = '';
        }
        if ($link === '') {
            $link = (string) ecod_option('siteUrl', '');
        }
        $link .= '#comment-' . $parentCoid;

        ecod_notif_push(
            $userId,
            'reply',
            $replier . ' 回复了你的评论',
            mb_substr(trim(strip_tags((string) ($feedback->text ?? ''))), 0, 120, 'UTF-8'),
            $link,
            $replier
        );
    } catch (\Throwable $e) {
        /* 通知失败不影响评论流程 */
    }
}

/**
 * 友链审核结果 → 通知申请人（申请人邮箱命中注册用户才投）
 *
 * 在 ecod_friend_app_review() 里调用（approve / reject 都发）。
 *
 * @param array  $app      申请行（含 mail / name）
 * @param string $decision approve / reject
 */
function ecod_notif_friend_review(array $app, string $decision): void
{
    try {
        $mail = trim((string) ($app['mail'] ?? ''));
        if ($mail === '') {
            return;
        }

        $db = \Typecho\Db::get();
        $user = $db->fetchRow(
            $db->select('uid')->from('table.users')
                ->where('mail = ?', $mail)->limit(1)
        );
        if (!$user || (int) $user['uid'] <= 0) {
            return;
        }

        $approved = $decision === 'approve';
        ecod_notif_push(
            (int) $user['uid'],
            'friend',
            $approved ? '你的友链申请已通过' : '你的友链申请未通过',
            $approved
                ? '「' . (string) ($app['name'] ?? '') . '」已加入友情链接，感谢支持'
                : '「' . (string) ($app['name'] ?? '') . '」未能通过审核，欢迎调整后再次申请',
            '',
            $approved ? '站长' : ''
        );
    } catch (\Throwable $e) {
    }
}

/**
 * 文章被点赞 → 通知文章作者
 *
 * @param int         $cid       文章
 * @param int         $actorUid  点赞人 uid（0 = 游客）
 * @param string|null $actorName 点赞人显示名
 */
function ecod_notif_like(int $cid, int $actorUid = 0, ?string $actorName = null): void
{
    try {
        $db = \Typecho\Db::get();
        $post = $db->fetchRow(
            $db->select('authorId', 'title')->from('table.contents')
                ->where('cid = ?', $cid)
                ->where('type = ?', 'post')
                ->where('status = ?', 'publish')
                ->limit(1)
        );
        if (!$post || (int) $post['authorId'] <= 0) {
            return;
        }

        $authorUid = (int) $post['authorId'];
        if ($actorUid > 0 && $actorUid === $authorUid) {
            return; /* 给自己的文章点赞不通知 */
        }

        $who = $actorName !== null && $actorName !== '' ? $actorName : '有访客';
        ecod_notif_push($authorUid, 'like', $who . ' 赞了你的文章', (string) ($post['title'] ?? ''), '', $who);
    } catch (\Throwable $e) {
    }
}

/**
 * 文章被收藏 → 通知文章作者
 *
 * @param int         $cid
 * @param int         $actorUid  收藏人 uid（0 = 游客）
 * @param string|null $actorName
 */
function ecod_notif_fav(int $cid, int $actorUid = 0, ?string $actorName = null): void
{
    try {
        $db = \Typecho\Db::get();
        $post = $db->fetchRow(
            $db->select('authorId', 'title')->from('table.contents')
                ->where('cid = ?', $cid)
                ->where('type = ?', 'post')
                ->where('status = ?', 'publish')
                ->limit(1)
        );
        if (!$post || (int) $post['authorId'] <= 0) {
            return;
        }
        $authorUid = (int) $post['authorId'];
        if ($actorUid > 0 && $actorUid === $authorUid) {
            return;
        }

        $who = $actorName !== null && $actorName !== '' ? $actorName : '有访客';
        ecod_notif_push($authorUid, 'fav', $who . ' 收藏了你的文章', (string) ($post['title'] ?? ''), '', $who);
    } catch (\Throwable $e) {
    }
}

/**
 * 评论被点赞 → 通知评论作者（ownerId > 0 才投）
 *
 * @param int $coid
 * @param int $actorUid
 */
function ecod_notif_clike(int $coid, int $actorUid = 0): void
{
    try {
        $db = \Typecho\Db::get();
        $comment = $db->fetchRow(
            $db->select('ownerId', 'author', 'text')->from('table.comments')
                ->where('coid = ?', $coid)->limit(1)
        );
        if (!$comment || (int) $comment['ownerId'] <= 0) {
            return;
        }
        $ownerUid = (int) $comment['ownerId'];
        if ($actorUid > 0 && $actorUid === $ownerUid) {
            return;
        }
        $who = (string) ($comment['author'] ?? '');
        ecod_notif_push($ownerUid, 'like', $who . ' 赞了你的评论', '', '', $who);
    } catch (\Throwable $e) {
    }
}

/**
 * 全站公告：给所有注册用户各投一条
 *
 * @param string $title 公告标题（必填）
 * @param string $body  公告正文
 * @param string $link  可选跳转链接
 * @return int 成功投递的用户数
 */
function ecod_notif_broadcast(string $title, string $body = '', string $link = ''): int
{
    $title = trim($title);
    if ($title === '') {
        return 0;
    }

    $sent = 0;
    ecod_notifs_call(static function (\Typecho\Db $db) use ($title, $body, $link, &$sent) {
        $uids = [];
        foreach ((array) $db->fetchAll($db->select('uid')->from('table.users')) as $row) {
            $uid = (int) $row['uid'];
            if ($uid > 0) {
                $uids[] = $uid;
            }
        }
        /* Typecho 的 insert builder 不接受多行数组，逐行写（用户量级在个位数～百级，可接受） */
        foreach ($uids as $uid) {
            $db->query($db->insert(ecod_notifs_table())->rows([
                'uid'     => $uid,
                'type'    => 'notice',
                'title'   => mb_substr($title, 0, 190, 'UTF-8'),
                'body'    => mb_substr($body, 0, 500, 'UTF-8'),
                'link'    => mb_substr($link, 0, 500, 'UTF-8'),
                'actor'   => '站长',
                'is_read' => 0,
                'created' => time(),
            ]));
            $sent++;
        }
        return true;
    });

    return $sent;
}
