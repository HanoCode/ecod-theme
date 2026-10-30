# Ecod Theme · 长期项目记忆

## 数据安全红线
- **theme:ecod-theme 行存 JSON，禁用 PHP serialize 读写**；改前备份、还原后按字节比对（备份文件含 mysql CLI 追加的尾部 \n，比对前先 rstrip）。
- mysql 导出必须 `--raw --default-character-set=utf8mb4`；python subprocess 还要传 LANG，否则中文列变 `?`。
- MySQL ROW binlog（/opt/homebrew/var/mysql/）：`mysqlbinlog -v -v --base64-output=DECODE-ROWS` 的 WHERE 段=旧值，可恢复误写。
- 清理测试数据**绝不能 `DELETE ... WHERE name='EWidgets'`**（正式存储行）；测试数据一律「seed + 备份 + 恢复」。

## 架构要点
- 真源 `docs/dev/design-proposal.html`（2026-10-30 起全套设计文档在 docs/dev/，主题根没有），文档先行；§03.5 导航 / §31 widget 管理台 / §32 分类增强。
- 导航：`ENavSets` 在主题 JSON 里是**双层编码 JSON 字符串**；`active` 生效套，≤10 套，套空回退自动导航。header 四分区（品牌块/导航 icon+badge/操作簇 sep+搜索+深浅切换/用户区，commit 0187bbe）；二级下拉子项配 icon → `.has-icons` 图标卡片网格（3c8ba83）；构建器 icon 选择是可视化图标网格浮层（`ECOD_NAV.iconSvg`，66ab9e9）。**构建器 `findItem()` 返回 `{item,parent,index}` 包装，必须取 `.item`**，直接当 item 用属性全 undefined 且写回无效。sprite 无 message/book，用 comment/layers。
- 导航套样式 5 种：auto/list/icons/mega/**mega-group**（2026-10-30 拓展，参考 guaqi：全宽面板 + group 归组分栏 + desc 条目描述 + 行内 badge--item；无 group/desc 退化 is-rows 多列图标行）。**字段白名单有两处**：`ecod_nav_norm_item()` 和 `ecod_nav_resolve_item()`，加字段两处都要加；**构建器 payload() 曾丢套 style 字段**（选样式保存即静默丢失→前台回落 auto）已修。全宽面板锚定：li 用 `:has(> .is-mega-full)` 转 static、面板 `left:0;right:0` 挂 `.ecod_nav`（position:relative），栏数走 `--ecod-mega-cols`。FlyEnv 托管 php-fpm：改 PHP 后须 `kill -USR2 <master pid>` 重载，brew services 管不到。
- Widget v2：`EWidgets` 独立 option 行；区域 id `{page}_{slot}`；每区上限 20；乐观锁 rev=md5(doc) 前 12 位，写入后必须 `ecod_widgets_flush()`；**显式保存**（先确认用户点过保存）；**自由投放**——无区域白名单，勿加回校验。
- PJAX 只换 `#ecod-shell`：全宽区在 shell 内（100vw 出血），常驻 UI（搜索面板/登录弹层）必须在 shell 外。
- 卡片 gallery（三图）模式 = **图上文下**（2026-10-30 改）；列表版式优先级：首页区块参数 `layout` > `ECardMode`；archive 页无参走 `ECardMode`。
- 模块壳/密度/图标头由渲染层注入（模板零改动）：`ecod_render_block()` ob 缓冲 → 首个 `<section class="ecod_widget">` 补 `data-shell`/`data-density`，h2 补 `has-icon`+`.ecod_widget__icon`；默认值 `ecod_block_shell_defaults()`、图标 `ecod_block_head_icons()`。
- **模块模板不得引用主题模板局部变量**：模块在独立闭包里 include，引用会打印 PHP Warning 混进 DOM（act.php 的 `$ecodLocked` 曾把 5 等分动作条挤成 18 列）→ 本地重算或走 `$this`。

## 框架与环境坑
- Query：表名要 `table.` 前缀；`where()` 是 variadic；join 别名不可靠（改两步查询）；`affectedRows()` 不存在（Db::query 对 UPDATE/DELETE 直接返回影响行数）。
- `singleHandle()` 先于 functions.php：文章页 content/描述在钩子注册前算好，meta 描述用 `themeInit()` 的 `setArchiveDescription()` 覆盖。
- 登录 cookie = `md5(rootUrl)+__typecho_uid/__typecho_authCode`；authCode 存 `Common::hash` 非明文；zsh 拼 Cookie 必须写 `${P}__typecho_uid`。主题登录走 api.php（验证码可从 /var/tmp/sess_<PHPSESSID> 读明文）。
- `Router::url` 必须传 prefix（options->index）；`Widget\Metas::permalink()` 是 echo 型返回 void，拿 URL 用 `Router::url('category',...)`。
- 站点跑 PHP 8.5（本机 CLI 8.1 仅粗筛）；nginx 白名单只放 `api/media/pay-admin/manifest/widget.php`，**新增独立入口必须同步白名单**再 reload；诊断 harness 放站点根 `/__diag*.php`，用完 rm。
- headless Chrome（系统 Chrome + `--no-sandbox` + 截图绝对路径）：`--virtual-time-budget` 下 rAF/scrollTo/transition 不推进；播放器 iframe 会 OOM（host-resolver-rules 黑洞）；滚动/动画断言用截图模式；窄屏验收用 390px iframe 量测页。
- 前端地址模板：`public/include.php` 注入 `window.ECOD`（`{cid}`/`{q}`），独立入口不在后端拼 permalink。
- 临时探针 `__*.php` 用完 `rm` + `git rm --cached`，否则 `php -l` 遍历假失败。
- `docs/dev/design-proposal.html` 的 section id 会重复/错号（§31 实际 `id="s8"`）：插入内容用 `<!-- 31 -->` 锚点 + section 配对扫描，按 `id` 定位会 find(-1) 把内容写到 `</html>` 之后。
- 排序内核 `core/site/sort.php`：`rand` 按日种子 `MD5(CONCAT(cid,种子))`（表达式不带空格/引号）；分类内搜索 `?q=` 必须同时补 `getCountSql()` 的 WHERE，否则计数/分页按全量算。

## 功能开关与真源
- 付费阅读：真源 `docs/paywall-design.md`；门禁四层（post.php 硬门禁 / ecod_excerpt 列表层 / ecod_pay_feed_guard / 搜索剥除）；试读 `ecod_pay_teaser_balance()` 留最后一块不解锁；pay-admin.php 管理台。
- 前台开关：`ELikers`（D2 头像堆叠）/ `EQuickBar`（D3）/ `EAuthorOnline`（D7）/ `ESearchPanel`+`ESearchHot`（C4）/ `ECatTools`（F3）；C3 目录进度 `syncProgress` 必须放在 `if (next === activeIndex) return` 之前，`Ecod.readProgress()` 唯一 pct 口径。
