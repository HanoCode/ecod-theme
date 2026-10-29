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

## 区域与模块管理器（widget v2）
- 真源 design-proposal.html **§31**；实现 `core/blocks.php`（区域注册表/迁移/渲染）、`widget.php`（独立管理台，三栏工作台）、`assets/admin/widget.{css,js}`。
- 存储 `EWidgets` 独立 option 行（JSON，user=0），**不写 `theme:ecod-theme` 行**；旧 `EBlocks` 只作一次性迁移源，`ecod_widgets_raw()` 在无 EWidgets 时内存迁移、首次保存才落盘 → 删掉 EWidgets 即回滚。
- 区域 id = `{page}_{slot}`（page: home/single/page/cat/tag/search/author/custom；slot: top_fluid/top_content/bottom_content/bottom_fluid/sidebar）+ 全局区 `all_top_fluid/all_sidebar_top/all_sidebar_bottom/all_bottom_fluid` + 定点区 `pinned`。
- 每区上限 `ECOD_WIDGET_LIMIT=20`（旧配置首页就有 14 条，12 会静默截断迁移）；uid 规则 `[a-z0-9-]{1,40}`，同区同类型递增。
- 乐观锁 `ecod_widgets_rev()` = md5(doc) 前 12 位；写入后必须 `ecod_widgets_flush()` 清 `$GLOBALS` 缓存，否则 rev 拿旧值。
- 全宽区必须在 `#ecod-shell` 内（PJAX 只换 shell），出血靠 `width:100vw; margin-inline:calc(50% - 50vw)`；反过来**常驻 UI（搜索面板、登录弹层）必须放在 shell 外**，否则 PJAX 换页后被一起替换掉。
- 独立入口脚本（widget.php / pay-admin.php 型）三件套：定位站点根 → `Cookie::setPrefix()` 校正前缀 → `Widget\User::alloc()->pass('administrator', true)`；状态码要走 `\Typecho\Response::getInstance()->setStatus()`，`http_response_code()` 会被覆盖回 200。

## 工程约定
- 文档先行：design-proposal.html 为真源，先补规格再改代码；§03.5=导航菜单管理器，§10.5=正文 oEmbed，§30=参考站八项决议（第 2 轮专题、第 3 轮 C3 目录进度条 + C4 搜索面板，均含验收记录），§31=区域与模块管理台，§32=分类/标签增强。
- 后台构建器交互验证可用「headless Chrome + file:// harness + 模拟点击 + dump-dom 断言」法（agent-browser 在本机 eval 上下文不稳定）。
- agent-browser：JS click 触发导航后 eval 上下文会丢，需 close --all 重开；PJAX 换页后 eval 上下文失联。
- 本站路由只认 `http://ecod.cc/index.php/archives/N/` 形式；`?p=N` 会落到首页；自定义页 query 不能用 `?cid=`（Archive 优先认它导致 404），用 `?post=`。
- 后台设置页测试数据一律走「seed 脚本 + 备份」，测完恢复。
- 前端地址模板：`public/include.php` 把 `routingTable` 里的 post/search 模式换成占位符注入 `window.ECOD`（`postUrl` 用 `{cid}`、`searchUrl` 用 `{q}`）；独立入口（api.php）拿不到 Router，必须靠前端按 cid/q 拼，不要在后端拼 permalink。
- 临时探针 `__*.php` 会被 IDE 自动 `git add`：用完 `rm` 后还要 `git rm --cached`，否则 `php -l` 遍历 `git ls-files` 时报 "Could not open input file" 假失败。
- **nginx 安全白名单**（2026-10-30 加固，FlyEnv nginx conf `vhost/nginx/1793099469421.conf`）：主题目录 PHP 直连一律 404，白名单只放独立入口 `api.php / media.php / pay-admin.php / manifest.php / widget.php`。**新增独立入口脚本必须同步加进这个白名单**再 `nginx -s reload`（二进制 `~/Library/FlyEnv/app/nginx-1.28.0/sbin/nginx`，带 `-p ~/Library/FlyEnv/server/nginx/common/ -c conf/nginx.conf`）。`design-proposal.html`、主题内 `.md`、`/usr/uploads/*.php` 也在封锁列表。
- 清理数据库测试数据时**绝不能 `DELETE ... WHERE name='EWidgets'`**——那是正式存储行；测试写入要么用专属测试 name，要么先导出再恢复。出事可从 ROW binlog 的 DELETE 前镜像（WHERE 段）原样恢复。
- 管理台是**显式保存**：改动只进 `state.dirtyAreas` 暂存，要点「保存改动」/ Ctrl+S 才提交；排查"前台没生效"先确认用户点过保存。

## 前台交互落点（C3/C4/D2/D3/D7）
- D2 点赞头像堆叠：`ecod_likes.uid`（schema v9 起登录用户行落 uid，游客行 0）→ `ecod_post_likers(cid,5)` 两步查询 → `components/post/act.php` 输出 `.ecod_likers`；无 uid 行整块不输出（降级纯数字）。开关 `ELikers`。
- D3 快速评论条：`post.php` 输出 `.ecod_quickbar`（非锁定/非付费锁/评论开），JS `Ecod.initQuickBar` 走 `addScroll` 判「评论区在视口下方且已滚过一屏」，≤680px 才显示、bottom 70px 避 dock。开关 `EQuickBar`。
- D7 作者卡：在线点 = `users.logged` ≤300s（`ecod_user_online`），博主 uid 走 author 块 `uid` 参数（留空 `ecod_site_author_id()`）。开关 `EAuthorOnline`；`.ecod_authorcard__avatar` 必须 `overflow:visible`，否则在线点被裁。
- C3 目录进度条：`assets/js/ecod.global.js` 的 `initToc` → `syncProgress(next)`（**必须放在 `if (next === activeIndex) return;` 之前**，否则同节内滚动进度不更新）；`Ecod.readProgress()` 是唯一 pct 口径，顶部进度条与目录环共用。
- C4 搜索面板：DOM 在 `public/header.php` 末尾（`.ecod_spanel`，**必须在 `#ecod-shell` 外**，PJAX 不替换）；JS `initSearchPanel`；三入口靠 capture 监听（导航框直接 preventDefault，dock 按钮 `stopImmediatePropagation` 抢在 drawer 之前）；热词 `ESearchHot`、标签数 `ESearchTagCount`、总开关 `ESearchPanel`。

## 运行环境与验收工具
- 站点跑 **PHP 8.5**（nginx `phpwebstudy-php-cgi-85.sock`），本机 CLI 只有 8.1 → `php -l` 只是粗筛；8.4+ 弃用告警（隐式可空参数等）会直接混进 HTML 输出，去 `~/Library/FlyEnv/server/vhost/logs/1793099469421.error.log` 看。
- 后台样式表**没有全局 `box-sizing` reset**：任何 `width:100%` + padding/border 的输入框都会顶出容器，组件要自带 `box-sizing:border-box`。
- 真实窄屏验收不能用 `--window-size=390`（视口有偏差、截图裁切）：写临时量测页把目标页塞进 390px iframe，读 `scrollWidth` 并列出 `right > innerWidth` 的元素。用完即删。
- headless Chrome 三大限制（2026-10-30 实测）：①文章页含 11 个外部播放器 iframe（YouTube/B站/Vimeo/优酷/腾讯/网易云/Spotify/SoundCloud）必然 OOM 被杀（exit 137），要用 `--host-resolver-rules="MAP *.youtube.com 127.0.0.1, ..."` 黑洞；②`--virtual-time-budget` 下 iframe 内 `scrollTo` 不生效（scrollY 恒 0）、`requestAnimationFrame` 不推进（`Ecod.addScroll` 回调不跑）、CSS `transition` 不推进（读到初始值）——诊断页要 `w.requestAnimationFrame = cb => cb(0)` 并注入 `transition:none!important`；③`--dump-dom` 不启动合成器，滚动/动画类断言必须用截图模式。
- 页面 500 排查：临时在 config.inc.php `define('__TYPECHO_EXCEPTION_FILE__', ...)` 挂自己的异常页，`Common::error()` 里 require 时的变量名是 **`$exception`**（不是 `$e`），能拿到 message + stack trace；用完立刻还原并 diff 校验。
- 主题设置页 fatal 的常见原因：`core/config.php` 调用的函数在 `core/blocks.php`（widget v2）里被改名，如 `ecod_blocks_admin_data()` → `ecod_widgets_admin_data()`。
- **开发文档已迁 `docs/dev/`**（2026-10-30 归档：design-proposal / REFERENCE-TEARDOWN / MIRAI-REFERENCE / UI-AUDIT / SHORTCODE-AUDIT）。主题根目录没有这些文件，改文档前先 `find`。归档后 `docs/dev/*.html` 可被公开访问（nginx 只屏蔽了主题根的 html 与全体 md），属待修安全问题。
- **nginx 安全加固（2026-10-30）**：`^/usr/themes/ecod-theme/` 的 PHP 直连只放行 `api / media / pay-admin / manifest`，其余 404。视觉 / 诊断 harness 一律放站点根（`/Users/bws/blog/ecod/__diag*.php`），不要改 nginx 绕过；用完 `rm`。
- 本机 headless 截图用**系统 Chrome** `/Applications/Google Chrome.app/Contents/MacOS/Google Chrome`，必须带 `--no-sandbox`（否则 sandbox 初始化失败 + GPU 进程退出），`--screenshot` 必须写**绝对路径**（chrome 的 cwd 不是项目目录）。
- 配置「按字节还原」校验：`md5 -q 文件` 含尾部换行，与 `hashlib.md5(stdout.strip())` 不是同一口径，会假报不一致；一律比对文件字节（`raw == now`）。

## Typecho 1.3 框架坑（付费模块实测沉淀）
- **Query 表名必须 `table.` 前缀**（`$db->from('table.x')`），裸表名不加 prefix 报 1146。
- **`Query::where()` 是 variadic**：多占位符写 `where('a = ? OR b = ?', $x, $y)`，传数组不行。**`join()` 的表别名不可靠**（条件串过 `filterColumn` 逐字符过滤），需要 JOIN 时改两步查询。
- **`Query::affectedRows()` 不存在**：`Db::query()` 对 UPDATE/DELETE 直接返回影响行数，INSERT 返回 lastInsertId；错误写法抛 Error 且常被 catch 吞成静默故障。
- **`singleHandle()` 在 `require functions.php` 之前执行**（Archive.php:669）：文章页的 content/plainExcerpt/archiveDescription 在主题钩子注册前就算好，`contentEx`/`excerptEx` 拦不住文章页；meta 描述要在 `themeInit()` 里用 `setArchiveDescription()` 覆盖。
- 登录 cookie 名 = `md5(rootUrl) + '__typecho_uid/__typecho_authCode'`（rootUrl 不带尾斜杠时 md5 值不同）；**cookie 里的 authCode 是 `Common::hash(库里明文)`，不是库里的明文**（User.php:210 存哈希、:105 `hashValidate` 校验）；authCode 被测试脚本改写可从 binlog 前镜像恢复。
- 脚本化后台请求时，zsh 拼 Cookie 头必须写 `${P}__typecho_uid`：`"$P__typecho_uid"` 会被当成变量 `P__typecho_uid` 展开成空串，表现为「cookie 没发出去」，极易误判成鉴权代码坏。
- 主题登录走 api.php（图形验证码存 `/var/tmp/sess_<PHPSESSID>`，可直接读明文做自动化测试）。

## 付费阅读（paywall）
- 真源 `docs/paywall-design.md`（§14 实施验收记录）；三表 ecod_orders / ecod_entitlements / ecod_pay_cards；通道 manual+card+epay(配置后启用)；会员两档（1 会员/2 超级会员）。
- 门禁四层：post.php 模板硬门禁、ecod_excerpt 列表层、ecod_pay_feed_guard(contentEx)、搜索剥除；试读必须 `ecod_pay_teaser_balance()` 且「留下最后一块不解锁」。
- 会员价只对真会员生效；游客显示原价 + `.ecod_pay__viphint` 提示。
- pay-admin.php 独立管理台（确认收款/授予撤销/卡密生成）；后台待确认订单 → settle → 幂等发权益。
