# Ecod Theme · 用户任务 / 消息中心 / 工单 设计

> 参考：`https://guaqi.com/account/task` · `/account/notification` · `/account/ticket`
> 真源关联：`docs/dev/design-proposal.html`（全站设计）、`docs/dev/MEMBER-CENTER.md`（会员 tab）
> 原型：`docs/dev/USER-MODULES.html`（可点击、明暗双主题）
> 日期：2026-10-31 · 状态：**P1–P5 全部实施**（任务中心 10 项全量、消息中心、工单用户端上线；管理端在 Console 插件三页，已对接真实接口）

---

## 0. 一页结论

三个模块全部并入 **个人中心 tab**（`page-user.php`），不新增前台路由：

| 模块 | 落点 | 数据 | 复用 |
|---|---|---|---|
| 任务中心 | 新 tab `task`（「我的会员」之后） | 新表 `ecod_task_claims` | `ecod_credits_add()` 记账、`ecod_credits_signed_today()` 签到态 |
| 消息中心 | 升级现有 tab `notifs` | 现有 `ecod_notifs` 表零改动 | `ecod_notif_*` 全部函数、`api.php` 现有 action |
| 工单 | 新 tab `ticket`（「订单」之后） | 新表 `ecod_tickets` + `ecod_ticket_msgs` | 管理端并入 **Ecod Console 插件**（`docs/dev/CONSOLE-PLUGIN.html`「工单处理」页，2026-10-31 已补），回复推 `ecod_notif_push()` |

### 0.1 已拍板决策

| # | 决策 | 理由 |
|---|---|---|
| D1 | 三模块都做 tab，不做 `/account/*` 式独立页 | tab 机制自带 hash 深链（`ecod.global.js:855-878`），无需新路由与 nginx 白名单 |
| D2 | 任务只做 10 项（4 新手 + 4 日常 + 2 累计） | 每一项都要服务端可判定；放弃「收藏」类（存浏览器本地，见 §9 R1） |
| D3 | 任务进度**不落库**，实时 `COUNT` 算 | 10 项查询都走 `authorId + created` 索引，单次 < 5ms；无同步漂移问题 |
| D4 | 领取状态落 `ecod_task_claims`，唯一键兜底幂等 | 先插占位再记账，重复点击物理不可领 |
| D5 | 任务奖励走积分体系（`ecod_credits_add`） | 不新建第二套货币；`ECrOn=off` 时任务 tab 不下发 |
| D6 | 消息中心**不改表**，只加分类映射 + 分页 + 交互 | 现有 type 只有 5 种，分类纯前端映射即可 |
| D7 | 工单新表 + 独立管理台 `ticket-admin.php` | 用户侧要完整闭环，后台不能靠改数据库 |

---

## 1. 参考拆解（guaqi 实采）

### 1.1 任务页 `/account/task`

三段分组，每段「标题 + 一句说明」，卡片式列表：

| 分组 | 说明文案 | 条目数 | 结构 |
|---|---|---|---|
| 新手任务 | 完成新手任务，获得奖励。只能完成一次。 | 9 | 名称 / `+N` 奖励 / `0/1` 进度 |
| 日常任务 | 完成每天日常任务，获得奖励。每天可完成一次 | 9 | 名称 / `+N` / `0/5` |
| 累计任务 | 完成累计任务，获得奖励。累计任务可重复完成 | 9 | 名称 / `+N` / `0/20`；注册天数显示 `0(0/100)` |

观察：
- 条目维度是「行为 × 方向」：发帖 / 评论 / 点赞 / 被赞 / 关注 / 被关注 / 收藏 / 被收藏 / 发帖子。
- 进度是 `当前/目标` 双数字，不是百分比条。
- 共 27 项，靠「被赞 / 被关注」这类**反向行为**撑数量，实际是同一批埋点。

**我方取舍**：不做「被赞 / 被收藏」类（需要额外的收件侧计数维护），不做「发帖子」（本站无此内容类型）。用 10 项覆盖最高价值行为，密度靠 UI 而不是条目数。

### 1.2 消息页 `/account/notification`

前端渲染（fetch 后注入，抓取只能拿到空壳），可见特征：无分类 tab、逐条卡片、日期 `MM/DD`。**信息结构单调是它的弱点**，我方直接做分类 + 未读分离。

### 1.3 工单页 `/account/ticket`

空态一句「您还没有创建任何工单」。即：**列表页 + 新建入口**，详情在二级页。我方改为**列表内联展开详情**（少一次跳转，tab 内不破坏滚动位置）。

---

## 2. 现状与差距

| # | 现状 | 证据 |
|---|---|---|
| 1 | 个人中心 8 个 tab：profile / vip / credits / notifs / posts / social / orders / lottery | `page-user.php:80-89` |
| 2 | tab 支持未读计数徽标 `[data-ecod-uc-count="notifs"]` | `ecod.global.js:3854` |
| 3 | 消息表 `ecod_notifs`：uid / type / title / body / link / actor / is_read / created | `core/site/notifs.php:42-110` |
| 4 | 消息 type 白名单只有 5 种：`reply` `friend` `like` `fav` `notice` | `core/site/notifs.php:139` |
| 5 | 消息列表固定 `limit=20`，无分页、无分类 | `core/site/notifs.php:201-210` |
| 6 | 积分有「每日签到 + 连续天数 + 流水」，且已有 `ecod_credits_award()` 4 类日限额任务 | `core/user/credits.php:101-270` |
| 7 | 收藏 / 点赞 / 历史存浏览器本地；点赞另有服务端 toggle 记录 | `page-user.php` 注释 + `api.php` like |
| 8 | 无工单表、无工单入口 | `grep 工单` 全仓 0 命中 |
| 9 | 管理台独立入口先例：`pay-admin.php`（nginx 白名单单独放行） | `docs/server-config.md` |

---

## 3. 模块一 · 任务中心

### 3.1 任务清单（10 项）

| # | key | 名称 | 组 | 目标 | 奖励 | 判定口径 |
|---|---|---|---|---|---|---|
| 1 | `signup` | 完成注册 | 新手 | 1 | 20 | 用户存在即达成 |
| 2 | `post_first` | 首次投稿 | 新手 | 1 | 30 | `contents` 中 `authorId=uid AND type='post'` ≥ 1 |
| 3 | `comment_first` | 首次评论 | 新手 | 1 | 10 | `comments` 中 `authorId=uid` ≥ 1 |
| 4 | `follow_first` | 首次关注 | 新手 | 1 | 5 | `ecod_follow` 中 `uid` ≥ 1 |
| 5 | `checkin_daily` | 每日签到 | 日常 | 1 | 10 | `ecod_credits_signed_today(uid)` |
| 6 | `post_daily` | 每日投稿 | 日常 | 1 | 15 | 当日 `contents` 发布数 |
| 7 | `comment_daily` | 每日评论 | 日常 | 3 | 5 | 当日 `comments` 数（可领 3 次） |
| 8 | `like_daily` | 每日点赞 | 日常 | 5 | 2 | 当日点赞记录数（可领 5 次） |
| 9 | `days_30` | 注册满 30 天 | 累计 | 30 | 100 | `floor((now - user.created) / 86400)` |
| 10 | `post_10` | 累计投稿 10 篇 | 累计 | 10 | 100 | `authorId=uid` 已发布篇数 |

奖励表集中在 `ecod_tasks_map()` 一个数组里，后续要接设置项只改这一处。

**多档任务**（7/8 项）的进度语义：`need=3` 表示「今天可以领 3 次」，每次领取进度 +1；`period` 固定为当日，领取记录用 `(uid, task, period)` 唯一 —— 所以第 2、3 次领取需要区分档位，`period` 写成 `20261031#2`。简化写法：`period = date('Ymd')`，唯一键改为 `(uid, task, period, seq)`，`seq` 由 `COUNT(*) + 1` 得到。

> 再简：**多档任务不拆 seq**，改动为「`delta` 累加、状态按 `SUM(delta)/reward` 计数判定进度」。即领取时累加一条 `ecod_task_claims`（不设唯一键，改由 `ecod_task_claims_count` 计次），幂等靠「事务内 SELECT FOR UPDATE 计数 + INSERT」。为避免并发复杂度，**P1 只上线单档任务，多档（7/8）P2 上线**，见 §7。

### 3.2 数据表

```sql
CREATE TABLE ecod_task_claims (
  id       INTEGER PRIMARY KEY AUTOINCREMENT,   -- MySQL: INT AUTO_INCREMENT
  uid      INTEGER NOT NULL,
  task     VARCHAR(24) NOT NULL,
  period   VARCHAR(16) NOT NULL,                -- 'once' | '20261031'
  delta    INTEGER NOT NULL DEFAULT 0,          -- 本次实发积分
  created  INTEGER NOT NULL DEFAULT 0,
  UNIQUE (uid, task, period),
  INDEX  (uid, created)
);
```

- 只有「已领取」有行；进度不落库。
- 唯一键 = 幂等闸门：`INSERT` 抛唯一冲突 → 直接返回「已领取」，不加分。
- 失败补偿：先插行、再 `ecod_credits_add()`；若记账失败则删除该行（`ecod_credits_add` 同步返回 `ok`）。

### 3.3 状态机

| 态 | 判定 | 按钮 |
|---|---|---|
| `doing` | 进度 < 目标 | 「还差 N」灰态不可点 |
| `claimable` | 进度 ≥ 目标 且无领取行 | 「领取 +N」主色（唯一可点态） |
| `claimed` | 有领取行 | 「已领取」灰态带勾 |
| 日常跨天 | `period` 变 → 自动回到 `doing` / `claimable` | 无需清理任务，行按 period 自然过期 |

### 3.4 API

```
GET  api.php?action=task_list
200: {ok:1, balance:1280, credit:'积分', streak:7, signed:1,
      groups:[{key:'newbie',title:'新手任务',desc:'...',done:2,total:4,
               items:[{key,title,desc,reward,progress,need,state,period}]}],
      today:{claimed:2,total:4}}

POST api.php?action=task_claim   {key}
200: {ok:1, delta:15, balance:1295, key, state:'claimed', today:{claimed:3,total:4}}
4xx: {ok:0, msg:'未达成'|'已领取'|'积分未开启'|'未登录'}
```

频控：同 uid 同 key 3 秒内重复请求直接复用上次结果（前端连点保护）；服务端靠唯一键兜底。

### 3.5 面板 UI

```
┌ 任务中心 ─────────────────────────────────────────────────┐
│ ┌ 汇总条 ───────────────────────────────────────────────┐ │
│ │ 积分 1,280          连续签到 7 天      今日 2/4 →      │ │
│ │ 完成任务可兑换积分   今天还没签到 [去签到]  ▓▓▓▓▓░░░  │ │
│ └───────────────────────────────────────────────────────┘ │
│  新手任务 · 只能完成一次                    已完成 2/4    │
│  ┌───────────────────────┐ ┌───────────────────────┐      │
│  │ ◆ 完成注册      +20   │ │ ◆ 首次投稿      +30   │      │
│  │ 注册即达成            │ │ 发布第一篇付费/免费文章 │      │
│  │ ✓ 已领取              │ │ [ 领取 +30 ]          │      │
│  └───────────────────────┘ └───────────────────────┘      │
│  日常任务 · 每天 00:00 重置                  今日 2/4     │
│  …                                                       │
└──────────────────────────────────────────────────────────┘
```

- 网格：`repeat(auto-fill, minmax(300px, 1fr))`，间距 `--ecod-sp-4`；窄屏单列。
- 卡片内 4 行：图标方框 + 标题 / 描述（1 行省略）/ 进度条 + `2/3` / 操作位右对齐。
- 领取成功：卡片原地切 `claimed`，汇总条余额与今日进度数字滚动 +N，右上角 toast（复用现有 `.ecod_toast`）。

---

## 4. 模块二 · 消息中心

### 4.1 分类映射（前端常量，后端零改动）

| 分类 | 包含 type | 图标 | 语义 |
|---|---|---|---|
| `all` | 全部 | bell | — |
| `unread` | `is_read=0` | mail-open | — |
| `interact` | `like` `fav` `reply` | heart | 有人对我的内容有动作 |
| `system` | `notice` `friend` `ticket` | shield | 公告 / 友链审核 / 工单回复 |

`ticket` 为本次新增 type，需同步扩 `ecod_notif_push()` 白名单（`core/site/notifs.php:139`）。

### 4.2 交互升级对比

| 能力 | 现状 | 设计 |
|---|---|---|
| 分类筛选 | 无 | 4 个 chip，带计数，本地过滤（一次拉 50 条） |
| 分页 | 固定 20 条 | 「加载更多」`offset` 增量，服务端 `ecod_notif_list($uid, $limit, $offset)` 加一个可选参数 |
| 单条已读 | 点击即已读，无视觉差 | 未读行：主色左条 + 浅底 + 圆点；点击行内展开正文并已读 |
| 批量 | 全部已读 / 清空已读 | 保留，移到工具条右侧 |
| 未读角标 | header 铃铛 + tab 计数 | 保留；分类 chip 上的计数由同一份数据派生 |
| 空态 | 一句话 | 图标 + 分场景文案（全部/某分类/无未读） |

改动量：`notifs.php` 加 1 个参数；`ecod.global.js` 渲染函数重写（约 +120 行）；CSS 新增 `.ecod_msgcenter__*`（约 +90 行）。表结构零改动。

### 4.3 面板 UI

```
┌ 消息中心 ─────────────────────────────────────────────────┐
│ [全部 12] [未读 5] [互动 9] [系统 3]      全部已读  清空已读 │
│ ┃ ● 小明 赞了你的文章                          2 分钟前   │
│ ┃    《Theme 的暗色是怎么调的》                            │
│   ○ 系统公告：11 月起停止支持 IE             昨天         │
│   ○ 你的友链申请已通过                        10-28       │
│                         [ 加载更多 ]                      │
└──────────────────────────────────────────────────────────┘
```

---

## 5. 模块三 · 工单

### 5.1 状态机

```
            用户提交               客服回复
  [open 待处理] ──────────► [open + 有 staff 回复 = 已回复]
        │                            │
        │ 用户关闭                    │ 用户回复 → 回到待处理（unread_staff=1）
        ▼                            ▼
  [closed 已关闭]              [resolved 已解决] ── 用户回复 → 重开 open
```

存储只有 `status ∈ {open, resolved, closed}`；「已回复 / 待你回复」是**派生态**：`status=open AND last_side='staff'` 即「已回复」，`status=open AND last_side='user'` 即「待客服处理」。

### 5.2 表结构

```sql
CREATE TABLE ecod_tickets (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  uid          INTEGER NOT NULL,
  no           VARCHAR(24) NOT NULL,          -- TK20261031-7F3A
  cat          VARCHAR(16) NOT NULL,          -- account|pay|report|idea|other
  title        VARCHAR(120) NOT NULL,
  status       VARCHAR(12) NOT NULL DEFAULT 'open',
  priority     INTEGER NOT NULL DEFAULT 1,    -- 0低 1普通 2高
  created      INTEGER NOT NULL DEFAULT 0,
  updated      INTEGER NOT NULL DEFAULT 0,
  msgs         INTEGER NOT NULL DEFAULT 0,
  last_side    VARCHAR(8) NOT NULL DEFAULT 'user',
  unread_user  INTEGER NOT NULL DEFAULT 0,
  unread_staff INTEGER NOT NULL DEFAULT 0,
  staff_uid    INTEGER NOT NULL DEFAULT 0,
  closed_at    INTEGER NOT NULL DEFAULT 0,
  UNIQUE (no),
  INDEX (uid, status, updated),
  INDEX (status, unread_staff, updated)
);

CREATE TABLE ecod_ticket_msgs (
  id       INTEGER PRIMARY KEY AUTOINCREMENT,
  tid      INTEGER NOT NULL,
  side     VARCHAR(8) NOT NULL,               -- user|staff
  uid      INTEGER NOT NULL DEFAULT 0,
  body     TEXT NOT NULL,
  created  INTEGER NOT NULL DEFAULT 0,
  INDEX (tid, id)
);
```

分类常量（`ecod_ticket_cats()`）：账号与登录 / 付费与订单 / 内容举报 / 功能建议 / 其他。

### 5.3 API

| action | 方法 | 入参 | 返回 |
|---|---|---|---|
| `ticket_list` | GET | `status?` `page?` | `{ok,items:[{id,no,cat,title,status,derived,msgs,updated,unread}],stats:{open,replied,resolved},has_more}` |
| `ticket_detail` | GET | `id` | `{ok,ticket:{…},msgs:[{id,side,body,created,name}]}`，读后 `unread_user=0` |
| `ticket_create` | POST | `cat` `title` `body` | `{ok,id,no}` |
| `ticket_reply` | POST | `id` `body` | `{ok,ticket:{status,derived,msgs,updated}}` |
| `ticket_close` | POST | `id` | `{ok,status:'closed'}` |

约束：
- 标题 4–60 字，正文 10–2000 字，纯文本入库（输出侧 `nl2br` + `htmlspecialchars`）。
- 频控：同 uid 24h 新建 ≤ 5 单；未关闭单 ≤ 3；回复 30s 1 条。
- 关闭后只能「重新提交新工单」，不可续聊（避免无限会话）。

### 5.4 用户端 UI

```
┌ 工单 ────────────────────────────────────── [ + 新建工单 ] ┐
│ ┌ 处理中 2 ┐ ┌ 已回复 1 ┐ ┌ 已解决 4 ┐                    │
│ ┌──────────────────────────────────────────────────────┐ │
│ │ TK20261031-7F3A  付费与订单      ● 已回复      2 条消息 │ │
│ │ 会员续费后到期时间没有累加                  10 分钟前   │ │
│ └──────────────────────────────────────────────────────┘ │
│  ┌ 展开后：时间线 ─────────────────────────────────────┐   │
│  │  我  昨天 20:14                                      │   │
│  │  ┌─────────────────────────────┐                    │   │
│  │  │ 续费了年付，但到期时间还是… │                    │   │
│  │  └─────────────────────────────┘                    │   │
│  │                          客服  今天 09:02            │   │
│  │                    ┌─────────────────────────────┐  │   │
│  │                    │ 已修复，请刷新页面确认      │  │   │
│  │                    └─────────────────────────────┘  │   │
│  │  [ 继续回复…                        ] [ 发送 ]       │   │
│  │  [ 问题已解决，关闭工单 ]                            │   │
│  └─────────────────────────────────────────────────────┘   │
└────────────────────────────────────────────────────────────┘
```

新建工单为行内表单（分类 chips + 标题 + 正文 + 提交），不开弹层 —— 与消息/任务的交互语言一致。

### 5.5 管理端（已并入 Ecod Console 插件）

不新建 `ticket-admin.php`。工单处理页落在控制台插件（`docs/dev/CONSOLE-PLUGIN.html` 新增「工单处理 / 消息推送 / 任务配置」三页，含规格 §08「与主题侧的分工」）：

- **工单处理**：左队列右详情（J/K 键盘流）、时间线气泡、快捷话术、内部备注（side=note，前台接口永不下发）、回复即写 `ticket_msgs(side=staff)` 并推站内信。
- **消息推送**：全站公告 / 定向 / 分群，直接调 `ecod_notif_broadcast()` 写 `ecod_notifs`；发送记录含触达与已读率，撤回=删行。
- **任务配置**：10 项任务的奖励 / 上限 / 显隐，覆写在插件 option（读侧回落主题常量表）；依赖积分总开关 `ECrOn`。

原「独立入口 + nginx 白名单」方案作废：插件走 `/console` 单一路由，鉴权复用插件自己的 administrator 校验。

---

## 6. 样式规范

| 项 | 规则 |
|---|---|
| 类名前缀 | 任务 `.ecod_task__*`、消息 `.ecod_msgc__*`、工单 `.ecod_ticket__*` |
| 颜色 | 只用 `--ecod-*` 变量；状态色 `--ecod-success / --ecod-accent / --ecod-danger`，禁硬编码 |
| 圆角 | 卡面 12（`--ecod-radius`）、嵌套面/图标框 8（`--ecod-radius-sm`）、胶囊 999 |
| 间距 | 只用 4pt 刻度；卡内 `--ecod-card-pad`，行间 `--ecod-sp-3` |
| 按钮 | 复用 `.ecod-btn` / `--primary` / `--ghost`，不加变体 |
| 明暗 | 全部走变量，不写 `html[data-theme=dark]` 特例（除非对比度不足） |
| 动效 | 领取/已读：`transform + opacity` 160ms `ease-out`；尊重 `prefers-reduced-motion` |

---

## 7. 分期

| 期 | 内容 | 交付物 |
|---|---|---|
| P1 | 任务：`ecod_task_claims` + `core/user/tasks.php`（`ecod_tasks_map/progress/list/claim`）+ `api.php` 2 个 action + tab UI + CSS。**先只上单档 6 项**（signup/post_first/comment_first/follow_first/checkin_daily/days_30） | 可领取、幂等、跨天重置 |
| P2 | 任务多档（post_daily/comment_daily/like_daily）+ 汇总条进度 | 次数型任务 |
| P3 | 消息中心：`ecod_notif_list` 加 offset、type 白名单加 `ticket`、JS 渲染重写、CSS | 分类 + 分页 + 行内展开 |
| P4 | 工单用户侧：2 张表 + `core/support/tickets.php` + 5 个 action + tab UI | 提交 / 回复 / 关闭 |
| P5 | 工单管理端：Console 插件「工单处理 / 消息推送 / 任务配置」三页 + 规格补充 | 后台闭环（已实施，见 `usr/plugins/EcodConsole`） |

---

## 8. 验收矩阵

| 场景 | 期望 |
|---|---|
| 未登录点「任务中心 / 工单」 | 登录引导，无数据泄漏、无接口 500 |
| 首次投稿后刷新任务 tab | 「首次投稿」变可领取，进度 1/1 |
| 连点领取 5 次 | 只加一次分，其余返回「已领取」，余额不多 |
| 跨天（改系统时间到次日） | 日常任务回 `doing`，新手/累计保持 `claimed` |
| 积分总开关关闭 | 任务 tab 不下发，深链 `#task` 回落 profile |
| 消息分类切换 | 计数与列表一致，未读分类仅含 `is_read=0` |
| 消息点开 | 单条已读，tab 徽标 -1，展开正文不跳页 |
| 空工单 | 空态 + 新建按钮可点 |
| 提交工单 | 列表出现「待处理」1 条，统计卡 +1 |
| 后台回复 | 用户侧变「已回复」，tab 徽标 +1，点开已读 |
| 用户关闭工单后 | 回复框禁用，提示「已关闭，可新建工单」 |
| 明暗主题 | 三面板无硬编码色，状态胶囊对比度 ≥ 4.5:1 |
| 390px 窄屏 | 卡片单列，汇总条不溢出，工单时间线气泡不横向滚动 |

---

## 9. 风险与待确认

| # | 风险 | 处置 |
|---|---|---|
| R1 | 「收藏」存 localStorage（`page-user.php` 注释），服务端无法判定 | 任务清单里剔除收藏类；若要做，须先把收藏落服务端表 |
| R2 | 「被赞 / 被关注」需在收件侧维护计数 | 本期不做，P2 再评估（可复用 `ecod_notif_*` 埋点时机） |
| R3 | 多档任务并发领取 | P2 用 `SELECT … FOR UPDATE` + 计数校验；单档靠唯一键已够 |
| R4 | 工单正文 XSS | 入库纯文本，输出 `htmlspecialchars` + `nl2br`，禁富文本 |
| R5 | 工单垃圾提交 | 5 单/24h + 未关闭 ≤3 + 登录态 + 令牌（复用 `ecod_account_token()`） |
| R6 | 任务奖励与积分设置耦合（`ECrOn=off` 无积分） | 任务整块隐藏，与 `credits` tab 同策略 |
