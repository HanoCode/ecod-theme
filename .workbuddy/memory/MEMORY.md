# Ecod Theme · 长期项目记忆

## 数据与安全红线
- **typecho_options 的 `theme:ecod-theme` 行存 JSON（Typecho 1.3），绝不能用 PHP serialize 读改写**；测试夹具必须 `json_decode` / `json_encode`，且写入前先备份原值（2026-10-29 曾因 serialize 覆盖丢失全部配置，靠 binlog 前镜像恢复）。
- 站点 MySQL 开了 ROW 格式 binlog（/opt/homebrew/var/mysql/），出事可用 `mysqlbinlog -v -v --base64-output=DECODE-ROWS` 的 WHERE 段（旧值）恢复；同 block 的 SET 段是新值，取值前必须按 WHERE/SET 切分。
- `mysql -N -B` 导出含转义字符的字段必须加 `--raw`，否则 JSON 被二次转义损坏。

## 导航菜单（多套）
- 数据：`ENavSets` = `{active, sets:[{id,name,items}]}`，单文件 JSON 存于主题设置；隐藏字段在「导航」分组，构建器 `assets/admin/nav.js` 序列化。
- `active` 即前台生效套，也是后台编辑对象；上限 10 套；至少保留一套；套为空 → 前台回退「全部页面 + ENavLinks」自动导航。
- 兼容：旧单份 `ENavMenu` 存在且无 ENavSets 时，内存提升为「默认导航」；一旦存过 ENavSets 就不再读旧字段。
- 坑：`Router::url` 必须传 prefix（`options->index`），否则未开伪静态的站拼出 /tag/css/ 直接 404。
- 后台 `.ecod-admin__ghost` 基础样式 `width:100%`，在工具条里要 `flex:none;width:auto` 覆盖。

## 工程约定
- 文档先行：design-proposal.html 为真源，先补规格再改代码；§03.5=导航菜单管理器，§10.5=正文 oEmbed。
- 后台构建器交互验证可用「headless Chrome + file:// harness + 模拟点击 + dump-dom 断言」法（agent-browser 在本机 eval 上下文不稳定）。
- agent-browser：JS click 触发导航后 eval 上下文会丢，需 close --all 重开；PJAX 换页后 eval 上下文失联。
- 本站路由只认 `http://ecod.cc/index.php/archives/N/` 形式；`?p=N` 会落到首页；自定义页 query 不能用 `?cid=`（Archive 优先认它导致 404），用 `?post=`。
- 后台设置页测试数据一律走「seed 脚本 + 备份」，测完恢复。

## Typecho 1.3 框架坑（付费模块实测沉淀）
- **Query 表名必须 `table.` 前缀**（`$db->from('table.x')`），裸表名不加 prefix 报 1146。
- **`Db::affectedRows()` 不存在**：`Db::query()` 对 UPDATE/DELETE 直接返回影响行数，INSERT 返回 lastInsertId；错误写法抛 Error 且常被 catch 吞成静默故障。
- **`singleHandle()` 在 `require functions.php` 之前执行**（Archive.php:669）：文章页的 content/plainExcerpt/archiveDescription 在主题钩子注册前就算好，`contentEx`/`excerptEx` 拦不住文章页；meta 描述要在 `themeInit()` 里用 `setArchiveDescription()` 覆盖。
- 登录 cookie 名 = `md5(rootUrl) + '__typecho_uid/__typecho_authCode'`（rootUrl 不带尾斜杠时 md5 值不同）；authCode 被测试脚本改写可从 binlog 前镜像恢复。
- 主题登录走 api.php（图形验证码存 `/var/tmp/sess_<PHPSESSID>`，可直接读明文做自动化测试）。

## 付费阅读（paywall）
- 真源 `docs/paywall-design.md`（§14 实施验收记录）；三表 ecod_orders / ecod_entitlements / ecod_pay_cards；通道 manual+card+epay(配置后启用)；会员两档（1 会员/2 超级会员）。
- 门禁四层：post.php 模板硬门禁、ecod_excerpt 列表层、ecod_pay_feed_guard(contentEx)、搜索剥除；试读必须 `ecod_pay_teaser_balance()` 且「留下最后一块不解锁」。
- 会员价只对真会员生效；游客显示原价 + `.ecod_pay__viphint` 提示。
- pay-admin.php 独立管理台（确认收款/授予撤销/卡密生成）；后台待确认订单 → settle → 幂等发权益。
