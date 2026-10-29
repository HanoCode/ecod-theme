<?php
/**
 * Ecod Theme · 数据结构自愈
 *
 * 主题依赖以下额外数据：
 *   1. contents.views          阅读量（与 core 表同列，列表页随查询一次取出）
 *   2. {prefix}ecod_likes      点赞明细（每访客每篇一行；v9 起登录用户行补记 uid）
 *   3. {prefix}ecod_user_avatar  用户自定义头像（uid 主键，url 为站点相对路径）
 *   4. {prefix}ecod_orders     付费流水（金额 / 状态，永不删除）
 *   5. {prefix}ecod_entitlements 访问权益（uid+kind+target 唯一）
 *   6. {prefix}ecod_pay_cards  卡密（只存 SHA-256）
 *   7. {prefix}ecod_meta_meta  分类 / 标签增强字段（封面 / 独立 SEO，KV）
 *
 * 这里只在“版本号落后”时执行一次 DDL/迁移，正常请求零额外查询。
 * 文件不依赖主题其他函数，可被 api.php 单独引入。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 * @license   MIT
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/** 当前数据结构版本，改动表结构时 +1 */
define('ECOD_SCHEMA_VERSION', 9);

/**
 * 数据库类型：mysql / pgsql / sqlite
 */
function ecod_schema_adapter(\Typecho\Db $db): string
{
    $name = strtolower((string) $db->getAdapterName());
    if (strpos($name, 'sqlite') !== false) {
        return 'sqlite';
    }
    if (strpos($name, 'pgsql') !== false || strpos($name, 'postgre') !== false) {
        return 'pgsql';
    }
    return 'mysql';
}

/**
 * 点赞表名（api.php 在无主题上下文时也能取到）
 */
function ecod_likes_table(): string
{
    try {
        $db = \Typecho\Db::get();
        return $db->getPrefix() . 'ecod_likes';
    } catch (\Throwable $e) {
        return 'typecho_ecod_likes';
    }
}

/**
 * 当前已安装的数据结构版本
 */
function ecod_schema_version(): int
{
    if (function_exists('ecod_option')) {
        return (int) ecod_option('EcodSchema', 0);
    }
    try {
        $options = \Utils\Helper::options();
        return (int) ($options->EcodSchema ?? 0);
    } catch (\Throwable $e) {
        return 0;
    }
}

/**
 * 写入版本号（options 表 user = 0 一条独立记录）
 */
function ecod_schema_mark(\Typecho\Db $db, int $version): void
{
    try {
        $exists = $db->fetchRow(
            $db->select('name')->from('table.options')
                ->where('name = ?', 'EcodSchema')->where('user = ?', 0)->limit(1)
        );
        if ($exists) {
            $db->query(
                $db->update('table.options')->rows(['value' => (string) $version])
                    ->where('name = ?', 'EcodSchema')->where('user = ?', 0)
            );
        } else {
            $db->query(
                $db->insert('table.options')->rows([
                    'name'  => 'EcodSchema',
                    'user'  => 0,
                    'value' => (string) $version,
                ])
            );
        }
    } catch (\Throwable $e) {
        /* 标记失败只会导致下次重跑，迁移脚本本身幂等 */
    }
}

/**
 * 读取主题缓存行（options 表 user=0 的独立记录，值按 JSON 存）
 *
 * 与主题配置分开存放，避免后台保存设置时整块覆盖。
 */
function ecod_cache_get(string $name, $default = null)
{
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('value')->from('table.options')
                ->where('name = ?', $name)->where('user = ?', 0)->limit(1)
        );
        if (!$row || !isset($row['value']) || (string) $row['value'] === '') {
            return $default;
        }
        $data = json_decode((string) $row['value'], true);
        return $data === null ? $default : $data;
    } catch (\Throwable $e) {
        return $default;
    }
}

/**
 * 写入主题缓存行
 */
function ecod_cache_set(string $name, $value): void
{
    try {
        $db = \Typecho\Db::get();
        $raw = json_encode($value, JSON_UNESCAPED_UNICODE);
        $exists = $db->fetchRow(
            $db->select('name')->from('table.options')
                ->where('name = ?', $name)->where('user = ?', 0)->limit(1)
        );
        if ($exists) {
            $db->query(
                $db->update('table.options')->rows(['value' => $raw])
                    ->where('name = ?', $name)->where('user = ?', 0)
            );
        } else {
            $db->query(
                $db->insert('table.options')->rows([
                    'name'  => $name,
                    'user'  => 0,
                    'value' => $raw,
                ])
            );
        }
    } catch (\Throwable $e) {
        /* 缓存写失败不影响主流程 */
    }
}

/**
 * 判断表里是否已有某列
 */
function ecod_schema_has_column(\Typecho\Db $db, string $table, string $column): bool
{
    $adapter = ecod_schema_adapter($db);
    try {
        if ($adapter === 'sqlite') {
            $rows = $db->fetchAll('PRAGMA table_info(' . $table . ')');
            foreach ($rows as $row) {
                if (isset($row['name']) && strcasecmp((string) $row['name'], $column) === 0) {
                    return true;
                }
            }
            return false;
        }
        if ($adapter === 'pgsql') {
            $rows = $db->fetchAll(
                "SELECT column_name FROM information_schema.columns"
                . " WHERE table_name = '" . $table . "' AND column_name = '" . $column . "'"
            );
            return !empty($rows);
        }
        $rows = $db->fetchAll('SHOW COLUMNS FROM ' . $table . " LIKE '" . $column . "'");
        return !empty($rows);
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * 确保点赞表存在（不存在的场合才建，已存在则只做一次轻查询）
 */
function ecod_schema_ensure_likes(\Typecho\Db $db): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }

    $table = $db->getPrefix() . 'ecod_likes';
    $adapter = ecod_schema_adapter($db);

    try {
        if ($adapter === 'sqlite') {
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $table . '" ('
                . ' "id" INTEGER PRIMARY KEY AUTOINCREMENT,'
                . ' "cid" INTEGER NOT NULL,'
                . ' "ip" VARCHAR(40) NOT NULL,'
                . ' "uid" INTEGER NOT NULL DEFAULT 0,'
                . ' "created" INTEGER NOT NULL)'
            );
            $db->query(
                'CREATE UNIQUE INDEX IF NOT EXISTS "' . $table . '_cid_ip"'
                . ' ON "' . $table . '" ("cid", "ip")'
            );
        } elseif ($adapter === 'pgsql') {
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $table . '" ('
                . ' "id" SERIAL PRIMARY KEY,'
                . ' "cid" INTEGER NOT NULL,'
                . ' "ip" VARCHAR(40) NOT NULL,'
                . ' "uid" INTEGER NOT NULL DEFAULT 0,'
                . ' "created" INTEGER NOT NULL)'
            );
            $db->query(
                'CREATE UNIQUE INDEX IF NOT EXISTS "' . $table . '_cid_ip"'
                . ' ON "' . $table . '" ("cid", "ip")'
            );
        } else {
            $db->query(
                'CREATE TABLE IF NOT EXISTS `' . $table . '` ('
                . ' `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,'
                . ' `cid` INT(10) UNSIGNED NOT NULL,'
                . ' `ip` CHAR(40) NOT NULL,'
                . ' `uid` INT(10) UNSIGNED NOT NULL DEFAULT 0,'
                . ' `created` INT(10) UNSIGNED NOT NULL,'
                . ' PRIMARY KEY (`id`),'
                . ' UNIQUE KEY `cid_ip` (`cid`, `ip`),'
                . ' KEY `cid_uid` (`cid`, `uid`)'
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
 * 点赞表补 uid 列（v9）：登录用户点赞落库到 uid 级，头像堆叠据此取人
 *
 * 老表走 ALTER ADD COLUMN；ecod_schema_has_column 幂等，已加过的直接跳过。
 */
function ecod_schema_migrate_likes_uid(\Typecho\Db $db, string $table): bool
{
    try {
        if (ecod_schema_has_column($db, $table, 'uid')) {
            return true;
        }
        $adapter = ecod_schema_adapter($db);
        if ($adapter === 'mysql') {
            $db->query('ALTER TABLE `' . $table . '` ADD COLUMN `uid` INT(10) UNSIGNED NOT NULL DEFAULT 0');
            $db->query('ALTER TABLE `' . $table . '` ADD INDEX `cid_uid` (`cid`, `uid`)');
        } elseif ($adapter === 'pgsql') {
            $db->query('ALTER TABLE "' . $table . '" ADD COLUMN "uid" INTEGER NOT NULL DEFAULT 0');
            $db->query('CREATE INDEX IF NOT EXISTS "' . $table . '_cid_uid" ON "' . $table . '" ("cid", "uid")');
        } else {
            $db->query('ALTER TABLE "' . $table . '" ADD COLUMN "uid" INTEGER NOT NULL DEFAULT 0');
            $db->query('CREATE INDEX IF NOT EXISTS "' . $table . '_cid_uid" ON "' . $table . '" ("cid", "uid")');
        }
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * 确保用户头像表存在（uid 主键，一人一行）
 */
function ecod_schema_ensure_avatars(\Typecho\Db $db): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }

    $table = $db->getPrefix() . 'ecod_user_avatar';
    $adapter = ecod_schema_adapter($db);

    try {
        if ($adapter === 'sqlite') {
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $table . '" ('
                . ' "uid" INTEGER PRIMARY KEY,'
                . ' "url" VARCHAR(512) NOT NULL DEFAULT \'\','
                . ' "created" INTEGER NOT NULL)'
            );
        } elseif ($adapter === 'pgsql') {
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $table . '" ('
                . ' "uid" INTEGER PRIMARY KEY,'
                . ' "url" VARCHAR(512) NOT NULL DEFAULT \'\','
                . ' "created" INTEGER NOT NULL)'
            );
        } else {
            $db->query(
                'CREATE TABLE IF NOT EXISTS `' . $table . '` ('
                . ' `uid` INT(10) UNSIGNED NOT NULL,'
                . ' `url` VARCHAR(512) NOT NULL DEFAULT \'\','
                . ' `created` INT(10) UNSIGNED NOT NULL,'
                . ' PRIMARY KEY (`uid`)'
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
 * 邮箱验证码表名
 */
function ecod_mailcode_table(): string
{
    try {
        return \Typecho\Db::get()->getPrefix() . 'ecod_mail_code';
    } catch (\Throwable $e) {
        return 'typecho_ecod_mail_code';
    }
}

/**
 * 确保邮箱验证码表存在（注册 / 找回密码的验证码落库，(mail, purpose) 主键）
 */
function ecod_schema_ensure_mailcode(\Typecho\Db $db): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }

    $table = $db->getPrefix() . 'ecod_mail_code';
    $adapter = ecod_schema_adapter($db);

    try {
        if ($adapter === 'sqlite') {
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $table . '" ('
                . ' "mail" VARCHAR(190) NOT NULL,'
                . ' "purpose" VARCHAR(16) NOT NULL,'
                . ' "code" VARCHAR(64) NOT NULL,'
                . ' "expires" INTEGER NOT NULL,'
                . ' "attempts" INTEGER NOT NULL DEFAULT 0,'
                . ' "created" INTEGER NOT NULL,'
                . ' PRIMARY KEY ("mail", "purpose"))'
            );
        } elseif ($adapter === 'pgsql') {
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $table . '" ('
                . ' "mail" VARCHAR(190) NOT NULL,'
                . ' "purpose" VARCHAR(16) NOT NULL,'
                . ' "code" VARCHAR(64) NOT NULL,'
                . ' "expires" INTEGER NOT NULL,'
                . ' "attempts" INTEGER NOT NULL DEFAULT 0,'
                . ' "created" INTEGER NOT NULL,'
                . ' PRIMARY KEY ("mail", "purpose"))'
            );
        } else {
            $db->query(
                'CREATE TABLE IF NOT EXISTS `' . $table . '` ('
                . ' `mail` VARCHAR(190) NOT NULL,'
                . ' `purpose` VARCHAR(16) NOT NULL,'
                . ' `code` CHAR(64) NOT NULL,'
                . ' `expires` INT(10) UNSIGNED NOT NULL,'
                . ' `attempts` INT(10) UNSIGNED NOT NULL DEFAULT 0,'
                . ' `created` INT(10) UNSIGNED NOT NULL,'
                . ' PRIMARY KEY (`mail`, `purpose`)'
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
 * 收藏表名
 */
function ecod_favs_table(): string
{
    try {
        return \Typecho\Db::get()->getPrefix() . 'ecod_favs';
    } catch (\Throwable $e) {
        return 'typecho_ecod_favs';
    }
}

/**
 * 确保收藏表存在
 *
 * fp 为收藏人指纹：游客用浏览器指纹（cookie），登录用户用 'u' + uid
 * 这个固定写法——同一账号在任何设备上指纹一致，收藏自然跨设备同步。
 */
function ecod_schema_ensure_favs(\Typecho\Db $db): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }

    $table = $db->getPrefix() . 'ecod_favs';
    $adapter = ecod_schema_adapter($db);

    try {
        if ($adapter === 'sqlite') {
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $table . '" ('
                . ' "id" INTEGER PRIMARY KEY AUTOINCREMENT,'
                . ' "cid" INTEGER NOT NULL,'
                . ' "fp" VARCHAR(40) NOT NULL,'
                . ' "created" INTEGER NOT NULL)'
            );
            $db->query(
                'CREATE UNIQUE INDEX IF NOT EXISTS "' . $table . '_cid_fp"'
                . ' ON "' . $table . '" ("cid", "fp")'
            );
        } elseif ($adapter === 'pgsql') {
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $table . '" ('
                . ' "id" SERIAL PRIMARY KEY,'
                . ' "cid" INTEGER NOT NULL,'
                . ' "fp" VARCHAR(40) NOT NULL,'
                . ' "created" INTEGER NOT NULL)'
            );
            $db->query(
                'CREATE UNIQUE INDEX IF NOT EXISTS "' . $table . '_cid_fp"'
                . ' ON "' . $table . '" ("cid", "fp")'
            );
        } else {
            $db->query(
                'CREATE TABLE IF NOT EXISTS `' . $table . '` ('
                . ' `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,'
                . ' `cid` INT(10) UNSIGNED NOT NULL,'
                . ' `fp` CHAR(40) NOT NULL,'
                . ' `created` INT(10) UNSIGNED NOT NULL,'
                . ' PRIMARY KEY (`id`),'
                . ' UNIQUE KEY `cid_fp` (`cid`, `fp`)'
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
 * 确保 contents.views 存在，并把旧版存于 fields 的阅读量迁移过来
 */
function ecod_schema_ensure_views(\Typecho\Db $db): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }

    $table = $db->getPrefix() . 'contents';
    $adapter = ecod_schema_adapter($db);

    try {
        if (!ecod_schema_has_column($db, $table, 'views')) {
            if ($adapter === 'sqlite') {
                $db->query('ALTER TABLE "' . $table . '" ADD COLUMN "views" INTEGER NOT NULL DEFAULT 0');
            } elseif ($adapter === 'pgsql') {
                $db->query('ALTER TABLE "' . $table . '" ADD COLUMN "views" INTEGER NOT NULL DEFAULT 0');
            } else {
                $db->query('ALTER TABLE `' . $table . '` ADD COLUMN `views` INT NOT NULL DEFAULT 0');
            }
        }

        ecod_schema_migrate_views($db, $table);
        $ok = true;
    } catch (\Throwable $e) {
        $ok = false;
    }

    return $ok;
}

/**
 * 旧数据迁移：fields(name='views').str_value → contents.views，然后清掉这些伪字段
 *
 * fields 里出现的每一行都会在后台文章编辑页渲染成一个可编辑输入框，
 * 迁移后必须删除，否则作者手滑改一下阅读量就归零。
 */
function ecod_schema_migrate_views(\Typecho\Db $db, string $table): void
{
    try {
        $rows = $db->fetchAll(
            $db->select('cid', 'str_value')->from('table.fields')->where('name = ?', 'views')
        );
    } catch (\Throwable $e) {
        return;
    }

    if (empty($rows)) {
        return;
    }

    foreach ($rows as $row) {
        $cid = (int) $row['cid'];
        $views = (int) $row['str_value'];
        if ($cid <= 0 || $views <= 0) {
            continue;
        }
        try {
            $db->query('UPDATE ' . $table . ' SET views = ' . $views . ' WHERE cid = ' . $cid);
        } catch (\Throwable $e) {
            return; // 写入失败就不删旧数据，下次再试
        }
    }

    try {
        $db->query($db->delete('table.fields')->where('name = ?', 'views'));
    } catch (\Throwable $e) {
        // 忽略
    }
}

/**
 * OAuth 绑定表名
 */
function ecod_oauth_table(): string
{
    try {
        return \Typecho\Db::get()->getPrefix() . 'ecod_oauth_bind';
    } catch (\Throwable $e) {
        return 'typecho_ecod_oauth_bind';
    }
}

/**
 * 确保第三方登录绑定表存在
 *
 * platform+openid 唯一：一个第三方账号只能绑一个站内用户；
 * uid 上加普通索引便于查"当前用户绑了哪些平台"。
 */
function ecod_schema_ensure_oauth(\Typecho\Db $db): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }

    $table = $db->getPrefix() . 'ecod_oauth_bind';
    $adapter = ecod_schema_adapter($db);

    try {
        if ($adapter === 'sqlite') {
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $table . '" ('
                . ' "id" INTEGER PRIMARY KEY AUTOINCREMENT,'
                . ' "platform" VARCHAR(20) NOT NULL,'
                . ' "openid" VARCHAR(120) NOT NULL,'
                . ' "uid" INTEGER NOT NULL,'
                . ' "created" INTEGER NOT NULL)'
            );
            $db->query(
                'CREATE UNIQUE INDEX IF NOT EXISTS "' . $table . '_po"'
                . ' ON "' . $table . '" ("platform", "openid")'
            );
            $db->query(
                'CREATE INDEX IF NOT EXISTS "' . $table . '_uid"'
                . ' ON "' . $table . '" ("uid")'
            );
        } elseif ($adapter === 'pgsql') {
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $table . '" ('
                . ' "id" SERIAL PRIMARY KEY,'
                . ' "platform" VARCHAR(20) NOT NULL,'
                . ' "openid" VARCHAR(120) NOT NULL,'
                . ' "uid" INTEGER NOT NULL,'
                . ' "created" INTEGER NOT NULL)'
            );
            $db->query(
                'CREATE UNIQUE INDEX IF NOT EXISTS "' . $table . '_po"'
                . ' ON "' . $table . '" ("platform", "openid")'
            );
            $db->query('CREATE INDEX IF NOT EXISTS "' . $table . '_uid" ON "' . $table . '" ("uid")');
        } else {
            $db->query(
                'CREATE TABLE IF NOT EXISTS `' . $table . '` ('
                . ' `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,'
                . ' `platform` VARCHAR(20) NOT NULL,'
                . ' `openid` VARCHAR(120) NOT NULL,'
                . ' `uid` INT(10) UNSIGNED NOT NULL,'
                . ' `created` INT(10) UNSIGNED NOT NULL,'
                . ' PRIMARY KEY (`id`),'
                . ' UNIQUE KEY `po` (`platform`, `openid`),'
                . ' KEY `uid` (`uid`)'
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
 * 限流记账表名（跨 session 的持久化防爆破计数）
 */
function ecod_throttle_table(): string
{
    try {
        return \Typecho\Db::get()->getPrefix() . 'ecod_throttle';
    } catch (\Throwable $e) {
        return 'typecho_ecod_throttle';
    }
}

/**
 * 确保限流记账表存在
 *
 * thkey 为 sha256(动作桶 + IP)，主键即哈希键，无增长需求。
 */
function ecod_schema_ensure_throttle(\Typecho\Db $db): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }

    $table = $db->getPrefix() . 'ecod_throttle';
    $adapter = ecod_schema_adapter($db);

    try {
        if ($adapter === 'sqlite') {
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $table . '" ('
                . ' "thkey" VARCHAR(64) PRIMARY KEY,'
                . ' "n" INTEGER NOT NULL DEFAULT 0,'
                . ' "last" INTEGER NOT NULL DEFAULT 0)'
            );
        } elseif ($adapter === 'pgsql') {
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $table . '" ('
                . ' "thkey" VARCHAR(64) PRIMARY KEY,'
                . ' "n" INTEGER NOT NULL DEFAULT 0,'
                . ' "last" INTEGER NOT NULL DEFAULT 0)'
            );
        } else {
            $db->query(
                'CREATE TABLE IF NOT EXISTS `' . $table . '` ('
                . ' `thkey` CHAR(64) NOT NULL,'
                . ' `n` INT(10) UNSIGNED NOT NULL DEFAULT 0,'
                . ' `last` INT(10) UNSIGNED NOT NULL DEFAULT 0,'
                . ' PRIMARY KEY (`thkey`)'
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
 * 确保分类 / 标签增强字段表存在（规格：docs/dev/design-proposal.html §32）
 *
 * mid + mkey 复合主键：metas.mid 全站唯一，无需 type 列；
 * 保存语义 = 先删后插，管理员操作低频，不值得 upsert 兼容层。
 */
function ecod_schema_ensure_metameta(\Typecho\Db $db): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }

    $table = $db->getPrefix() . 'ecod_meta_meta';
    $adapter = ecod_schema_adapter($db);

    try {
        if ($adapter === 'sqlite') {
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $table . '" ('
                . ' "mid" INTEGER NOT NULL,'
                . ' "mkey" VARCHAR(32) NOT NULL,'
                . ' "mvalue" TEXT NULL,'
                . ' PRIMARY KEY ("mid", "mkey"))'
            );
        } elseif ($adapter === 'pgsql') {
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $table . '" ('
                . ' "mid" INTEGER NOT NULL,'
                . ' "mkey" VARCHAR(32) NOT NULL,'
                . ' "mvalue" TEXT NULL,'
                . ' PRIMARY KEY ("mid", "mkey"))'
            );
        } else {
            $db->query(
                'CREATE TABLE IF NOT EXISTS `' . $table . '` ('
                . ' `mid` INT(10) UNSIGNED NOT NULL,'
                . ' `mkey` VARCHAR(32) NOT NULL,'
                . ' `mvalue` TEXT NULL,'
                . ' PRIMARY KEY (`mid`, `mkey`)'
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
 * 付费相关表名（订单 / 权益 / 卡密）
 *
 * api.php、pay-admin.php 在无主题上下文时也会用到，统一在这里取。
 */
function ecod_pay_table(string $which): string
{
    $map = [
        'orders'       => 'ecod_orders',
        'entitlements' => 'ecod_entitlements',
        'cards'        => 'ecod_pay_cards',
    ];
    $suffix = $map[$which] ?? $which;

    try {
        return \Typecho\Db::get()->getPrefix() . $suffix;
    } catch (\Throwable $e) {
        return 'typecho_' . $suffix;
    }
}

/**
 * 确保付费三表存在
 *
 * 三表分工：
 *   orders       金额与状态（流水，永不删）
 *   entitlements 谁在什么时间能看什么（唯一键 uid+kind+target）
 *   pay_cards    卡密只存 SHA-256，明文仅生成时返回一次
 */
function ecod_schema_ensure_pay(\Typecho\Db $db): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }

    $orders = $db->getPrefix() . 'ecod_orders';
    $ents   = $db->getPrefix() . 'ecod_entitlements';
    $cards  = $db->getPrefix() . 'ecod_pay_cards';
    $adapter = ecod_schema_adapter($db);

    try {
        if ($adapter === 'sqlite') {
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $orders . '" ('
                . ' "id" INTEGER PRIMARY KEY AUTOINCREMENT,'
                . ' "order_no" VARCHAR(24) NOT NULL,'
                . ' "uid" INTEGER NOT NULL,'
                . ' "kind" VARCHAR(10) NOT NULL,'
                . ' "target" INTEGER NOT NULL DEFAULT 0,'
                . ' "level" INTEGER NOT NULL DEFAULT 0,'
                . ' "title" VARCHAR(190) NOT NULL DEFAULT \'\','
                . ' "amount" INTEGER NOT NULL DEFAULT 0,'
                . ' "currency" VARCHAR(3) NOT NULL DEFAULT \'CNY\','
                . ' "days" INTEGER NOT NULL DEFAULT 0,'
                . ' "status" VARCHAR(12) NOT NULL DEFAULT \'pending\','
                . ' "channel" VARCHAR(20) NOT NULL DEFAULT \'\','
                . ' "trade_no" VARCHAR(64) NOT NULL DEFAULT \'\','
                . ' "created" INTEGER NOT NULL DEFAULT 0,'
                . ' "paid_at" INTEGER NOT NULL DEFAULT 0,'
                . ' "expire_at" INTEGER NOT NULL DEFAULT 0,'
                . ' "ip" VARCHAR(45) NOT NULL DEFAULT \'\','
                . ' "ua" VARCHAR(255) NOT NULL DEFAULT \'\','
                . ' "remark" VARCHAR(255) NOT NULL DEFAULT \'\')'
            );
            $db->query('CREATE UNIQUE INDEX IF NOT EXISTS "' . $orders . '_no" ON "' . $orders . '" ("order_no")');
            $db->query('CREATE INDEX IF NOT EXISTS "' . $orders . '_us" ON "' . $orders . '" ("uid", "status")');
            $db->query('CREATE INDEX IF NOT EXISTS "' . $orders . '_kt" ON "' . $orders . '" ("kind", "target")');

            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $ents . '" ('
                . ' "id" INTEGER PRIMARY KEY AUTOINCREMENT,'
                . ' "uid" INTEGER NOT NULL,'
                . ' "kind" VARCHAR(10) NOT NULL,'
                . ' "target" INTEGER NOT NULL DEFAULT 0,'
                . ' "level" INTEGER NOT NULL DEFAULT 0,'
                . ' "source" VARCHAR(12) NOT NULL DEFAULT \'\','
                . ' "source_id" VARCHAR(64) NOT NULL DEFAULT \'\','
                . ' "start_at" INTEGER NOT NULL DEFAULT 0,'
                . ' "expire_at" INTEGER NOT NULL DEFAULT 0,'
                . ' "created" INTEGER NOT NULL DEFAULT 0)'
            );
            $db->query('CREATE UNIQUE INDEX IF NOT EXISTS "' . $ents . '_ukt" ON "' . $ents . '" ("uid", "kind", "target")');
            $db->query('CREATE INDEX IF NOT EXISTS "' . $ents . '_ue" ON "' . $ents . '" ("uid", "expire_at")');

            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $cards . '" ('
                . ' "id" INTEGER PRIMARY KEY AUTOINCREMENT,'
                . ' "code_hash" VARCHAR(64) NOT NULL,'
                . ' "batch" VARCHAR(32) NOT NULL DEFAULT \'\','
                . ' "kind" VARCHAR(10) NOT NULL,'
                . ' "target" INTEGER NOT NULL DEFAULT 0,'
                . ' "level" INTEGER NOT NULL DEFAULT 0,'
                . ' "days" INTEGER NOT NULL DEFAULT 0,'
                . ' "status" VARCHAR(10) NOT NULL DEFAULT \'unused\','
                . ' "uid" INTEGER NOT NULL DEFAULT 0,'
                . ' "order_no" VARCHAR(24) NOT NULL DEFAULT \'\','
                . ' "created" INTEGER NOT NULL DEFAULT 0,'
                . ' "used_at" INTEGER NOT NULL DEFAULT 0)'
            );
            $db->query('CREATE UNIQUE INDEX IF NOT EXISTS "' . $cards . '_h" ON "' . $cards . '" ("code_hash")');
            $db->query('CREATE INDEX IF NOT EXISTS "' . $cards . '_b" ON "' . $cards . '" ("batch", "status")');
        } elseif ($adapter === 'pgsql') {
            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $orders . '" ('
                . ' "id" SERIAL PRIMARY KEY,'
                . ' "order_no" VARCHAR(24) NOT NULL,'
                . ' "uid" INTEGER NOT NULL,'
                . ' "kind" VARCHAR(10) NOT NULL,'
                . ' "target" INTEGER NOT NULL DEFAULT 0,'
                . ' "level" INTEGER NOT NULL DEFAULT 0,'
                . ' "title" VARCHAR(190) NOT NULL DEFAULT \'\','
                . ' "amount" INTEGER NOT NULL DEFAULT 0,'
                . ' "currency" VARCHAR(3) NOT NULL DEFAULT \'CNY\','
                . ' "days" INTEGER NOT NULL DEFAULT 0,'
                . ' "status" VARCHAR(12) NOT NULL DEFAULT \'pending\','
                . ' "channel" VARCHAR(20) NOT NULL DEFAULT \'\','
                . ' "trade_no" VARCHAR(64) NOT NULL DEFAULT \'\','
                . ' "created" INTEGER NOT NULL DEFAULT 0,'
                . ' "paid_at" INTEGER NOT NULL DEFAULT 0,'
                . ' "expire_at" INTEGER NOT NULL DEFAULT 0,'
                . ' "ip" VARCHAR(45) NOT NULL DEFAULT \'\','
                . ' "ua" VARCHAR(255) NOT NULL DEFAULT \'\','
                . ' "remark" VARCHAR(255) NOT NULL DEFAULT \'\')'
            );
            $db->query('CREATE UNIQUE INDEX IF NOT EXISTS "' . $orders . '_no" ON "' . $orders . '" ("order_no")');
            $db->query('CREATE INDEX IF NOT EXISTS "' . $orders . '_us" ON "' . $orders . '" ("uid", "status")');
            $db->query('CREATE INDEX IF NOT EXISTS "' . $orders . '_kt" ON "' . $orders . '" ("kind", "target")');

            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $ents . '" ('
                . ' "id" SERIAL PRIMARY KEY,'
                . ' "uid" INTEGER NOT NULL,'
                . ' "kind" VARCHAR(10) NOT NULL,'
                . ' "target" INTEGER NOT NULL DEFAULT 0,'
                . ' "level" INTEGER NOT NULL DEFAULT 0,'
                . ' "source" VARCHAR(12) NOT NULL DEFAULT \'\','
                . ' "source_id" VARCHAR(64) NOT NULL DEFAULT \'\','
                . ' "start_at" INTEGER NOT NULL DEFAULT 0,'
                . ' "expire_at" INTEGER NOT NULL DEFAULT 0,'
                . ' "created" INTEGER NOT NULL DEFAULT 0)'
            );
            $db->query('CREATE UNIQUE INDEX IF NOT EXISTS "' . $ents . '_ukt" ON "' . $ents . '" ("uid", "kind", "target")');
            $db->query('CREATE INDEX IF NOT EXISTS "' . $ents . '_ue" ON "' . $ents . '" ("uid", "expire_at")');

            $db->query(
                'CREATE TABLE IF NOT EXISTS "' . $cards . '" ('
                . ' "id" SERIAL PRIMARY KEY,'
                . ' "code_hash" VARCHAR(64) NOT NULL,'
                . ' "batch" VARCHAR(32) NOT NULL DEFAULT \'\','
                . ' "kind" VARCHAR(10) NOT NULL,'
                . ' "target" INTEGER NOT NULL DEFAULT 0,'
                . ' "level" INTEGER NOT NULL DEFAULT 0,'
                . ' "days" INTEGER NOT NULL DEFAULT 0,'
                . ' "status" VARCHAR(10) NOT NULL DEFAULT \'unused\','
                . ' "uid" INTEGER NOT NULL DEFAULT 0,'
                . ' "order_no" VARCHAR(24) NOT NULL DEFAULT \'\','
                . ' "created" INTEGER NOT NULL DEFAULT 0,'
                . ' "used_at" INTEGER NOT NULL DEFAULT 0)'
            );
            $db->query('CREATE UNIQUE INDEX IF NOT EXISTS "' . $cards . '_h" ON "' . $cards . '" ("code_hash")');
            $db->query('CREATE INDEX IF NOT EXISTS "' . $cards . '_b" ON "' . $cards . '" ("batch", "status")');
        } else {
            $db->query(
                'CREATE TABLE IF NOT EXISTS `' . $orders . '` ('
                . ' `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,'
                . ' `order_no` CHAR(24) NOT NULL,'
                . ' `uid` INT(10) UNSIGNED NOT NULL,'
                . ' `kind` VARCHAR(10) NOT NULL,'
                . ' `target` INT(10) UNSIGNED NOT NULL DEFAULT 0,'
                . ' `level` TINYINT UNSIGNED NOT NULL DEFAULT 0,'
                . ' `title` VARCHAR(190) NOT NULL DEFAULT \'\','
                . ' `amount` INT(10) UNSIGNED NOT NULL DEFAULT 0,'
                . ' `currency` CHAR(3) NOT NULL DEFAULT \'CNY\','
                . ' `days` INT(10) UNSIGNED NOT NULL DEFAULT 0,'
                . ' `status` VARCHAR(12) NOT NULL DEFAULT \'pending\','
                . ' `channel` VARCHAR(20) NOT NULL DEFAULT \'\','
                . ' `trade_no` VARCHAR(64) NOT NULL DEFAULT \'\','
                . ' `created` INT(10) UNSIGNED NOT NULL DEFAULT 0,'
                . ' `paid_at` INT(10) UNSIGNED NOT NULL DEFAULT 0,'
                . ' `expire_at` INT(10) UNSIGNED NOT NULL DEFAULT 0,'
                . ' `ip` VARCHAR(45) NOT NULL DEFAULT \'\','
                . ' `ua` VARCHAR(255) NOT NULL DEFAULT \'\','
                . ' `remark` VARCHAR(255) NOT NULL DEFAULT \'\','
                . ' PRIMARY KEY (`id`),'
                . ' UNIQUE KEY `order_no` (`order_no`),'
                . ' KEY `uid_status` (`uid`, `status`),'
                . ' KEY `kind_target` (`kind`, `target`)'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );

            $db->query(
                'CREATE TABLE IF NOT EXISTS `' . $ents . '` ('
                . ' `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,'
                . ' `uid` INT(10) UNSIGNED NOT NULL,'
                . ' `kind` VARCHAR(10) NOT NULL,'
                . ' `target` INT(10) UNSIGNED NOT NULL DEFAULT 0,'
                . ' `level` TINYINT UNSIGNED NOT NULL DEFAULT 0,'
                . ' `source` VARCHAR(12) NOT NULL DEFAULT \'\','
                . ' `source_id` VARCHAR(64) NOT NULL DEFAULT \'\','
                . ' `start_at` INT(10) UNSIGNED NOT NULL DEFAULT 0,'
                . ' `expire_at` INT(10) UNSIGNED NOT NULL DEFAULT 0,'
                . ' `created` INT(10) UNSIGNED NOT NULL DEFAULT 0,'
                . ' PRIMARY KEY (`id`),'
                . ' UNIQUE KEY `uid_kind_target` (`uid`, `kind`, `target`),'
                . ' KEY `uid_expire` (`uid`, `expire_at`)'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );

            $db->query(
                'CREATE TABLE IF NOT EXISTS `' . $cards . '` ('
                . ' `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,'
                . ' `code_hash` CHAR(64) NOT NULL,'
                . ' `batch` VARCHAR(32) NOT NULL DEFAULT \'\','
                . ' `kind` VARCHAR(10) NOT NULL,'
                . ' `target` INT(10) UNSIGNED NOT NULL DEFAULT 0,'
                . ' `level` TINYINT UNSIGNED NOT NULL DEFAULT 0,'
                . ' `days` INT(10) UNSIGNED NOT NULL DEFAULT 0,'
                . ' `status` VARCHAR(10) NOT NULL DEFAULT \'unused\','
                . ' `uid` INT(10) UNSIGNED NOT NULL DEFAULT 0,'
                . ' `order_no` CHAR(24) NOT NULL DEFAULT \'\','
                . ' `created` INT(10) UNSIGNED NOT NULL DEFAULT 0,'
                . ' `used_at` INT(10) UNSIGNED NOT NULL DEFAULT 0,'
                . ' PRIMARY KEY (`id`),'
                . ' UNIQUE KEY `code_hash` (`code_hash`),'
                . ' KEY `batch_status` (`batch`, `status`)'
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
 * 结构升级入口：版本号落后时才执行，每个请求最多一次
 */
function ecod_schema_upgrade(): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    if (ecod_schema_version() >= ECOD_SCHEMA_VERSION) {
        return;
    }

    try {
        $db = \Typecho\Db::get();
    } catch (\Throwable $e) {
        return;
    }

    $likes = ecod_schema_ensure_likes($db);
    $views = ecod_schema_ensure_views($db);
    $avatars = ecod_schema_ensure_avatars($db);
    $mailcode = ecod_schema_ensure_mailcode($db);
    $favs = ecod_schema_ensure_favs($db);
    $oauth = ecod_schema_ensure_oauth($db);
    $pay = ecod_schema_ensure_pay($db);
    $metameta = ecod_schema_ensure_metameta($db);
    $throttle = ecod_schema_ensure_throttle($db);
    $likesUid = $likes ? ecod_schema_migrate_likes_uid($db, $db->getPrefix() . 'ecod_likes') : false;

    /* 全部成功才记版本，否则下次请求重试 */
    if ($likes && $likesUid && $views && $avatars && $mailcode && $favs && $oauth && $pay && $metameta && $throttle) {
        ecod_schema_mark($db, ECOD_SCHEMA_VERSION);
    }
}
