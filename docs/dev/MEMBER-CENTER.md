# Ecod Theme · 会员中心（个人中心集成）设计

> 依据：现状审计（page-user.php / page-vip.php / core/pay/pay.php / core/lib/schema.php）
> 真源关联：`docs/paywall-design.md`（付费体系）、`docs/dev/design-proposal.html`（全站设计）
> 日期：2026-10-30
> 状态：设计稿（未实施）

---

## 0. 一页结论

在个人信息中心（`page-user.php`）新增 **「我的会员」tab**（`#vip` 深链），服务端直出会员状态卡 + 权益对照 + 续费/升级入口 + 卡密兑换 + 最近订单。`page-vip.php` 保留为公开营销页并互相导流。

数据层零新增：`ecod_pay_vip()` / `ecod_pay_orders()` 已在 `page-user.php:49-54` 读取；唯一新接口是卡密直兑 `api.php?action=pay_redeem`。

### 0.1 已拍板决策

| # | 决策 | 理由 |
|---|---|---|
| D1 | 完整版会员 tab（状态卡+权益+兑换+订单） | tab 机制 hash 深链全自动（`ecod.global.js:855-878`），服务端直出无需新状态接口 |
| D2 | 权益文案抽公共函数 `ecod_pay_tier_copy()` | 现硬编码在 `page-vip.php:29-38`，两页共用 |
| D3 | 新建 `.ecod_uc__vipcard` 样式，不复用 `.ecod_vip__card` | 后者为两栏宽卡设计，塞进 720px 单栏面板需大改，复用得不偿失 |
| D4 | 卡密兑换走新 API `pay_redeem`，不开新页面 | 复用 `ecod_pay_card_redeem()` 抢占式 UPDATE（`pay-channel.php:300`） |

---

## 1. 现状

| # | 现状 | 证据 |
|---|---|---|
| 1 | 个人中心 9 个 tab（profile/credits/notifs/posts/favs/follows/likes/history/orders） | `page-user.php:59-69` |
| 2 | 会员状态已读：`ecod_pay_vip(uid)` + `ecod_pay_orders()`，身份卡有 VIP 徽标 | `page-user.php:46-54, 124-129` |
| 3 | tab 机制：`data-ecod-uc-tab/panel` + hash 深链 | `ecod.global.js:855-878` |
| 4 | 无「会员」tab；订单空态里才有「看看会员权益」链接 | `page-user.php:428-431` |
| 5 | 档位：level 1/2 × 周期 month/quarter/year/forever，价格 `EVip{1|2}{Month|...}` | `core/pay/pay.php:164-197` |
| 6 | 权益仅展示文案（`$ecodTierCopy` 硬编码），无能力判定；生效的只有 level 数值 | `page-vip.php:29-38` |
| 7 | 支付通道：epay（支付宝/微信）/ 卡密 / manual，统一适配器 | `core/pay/pay-channel.php` |
| 8 | 卡密兑换目前只能走收银台 card 通道下单 | `page-cashier.php:266-272` |
| 9 | 会员数据：`ecod_entitlements`（唯一键 uid+kind+target）/ `ecod_orders` / `ecod_pay_cards` | `core/lib/schema.php:993-1199` |

---

## 2. Tab 定义与数据

### 2.1 注册

```php
// page-user.php:59-69 $ecodTabs 追加（排序在 orders 之前）
'vip' => ['我的会员', 'medal'],
```

### 2.2 服务端直出（沿用 orders 面板模式，首屏即有内容）

```php
$ecodVipInfo  // 已有：active/level/name/expire_at（page-user.php:46-54）
$ecodTiers    = ecod_pay_vip_tiers();      // 档位+价格
$ecodTierCopy = ecod_pay_tier_copy();      // 新抽公共函数（D2，core/pay/pay.php）
$ecodVipOrders = ecod_pay_orders($uid, 3); // 最近 3 笔
```

无新状态接口。支付成功后的刷新见 §4。

---

## 3. 面板 UI（`.ecod_uc__vipcard` 体系，720px 单栏）

```
┌ 我的会员 ────────────────────────────────────────────────┐
│ ┌──────────────────────────────────────────────────────┐ │
│ │ 🏅 黄金会员                          状态胶囊         │ │
│ │ 至 2027-03-15 · 还剩 136 天                           │ │
│ │ 本档权益：全站内容·专属徽标·下载加速（一行摘要）       │ │
│ │ [ 立即续费 ]  [ 升级白金 ]        [卡密兑换 →]         │ │
│ └──────────────────────────────────────────────────────┘ │
│                                                          │
│  权益对照                                                │
│  ┌──────────────┬──────────────┬──────────────┐          │
│  │ 权益          │ 黄金 Lv.1    │ 白金 Lv.2    │          │
│  ├──────────────┼──────────────┼──────────────┤          │
│  │ 付费文章      │ Lv.1 可读    │ Lv.2 可读    │          │
│  │ 专属徽标      │ ✓            │ ✓            │          │
│  │ …（来自 tier_copy）│         │              │          │
│  └──────────────┴──────────────┴──────────────┘          │
│                                                          │
│  周期价格（点按直达收银台）                               │
│  [月 ¥xx] [季 ¥xx] [年 ¥xx 立省xx%] [永久 ¥xxx]           │
│                                                          │
│  卡密兑换（点「卡密兑换」展开）                           │
│  [ 输入卡密…        ] [兑换]   成功→状态卡原地刷新        │
│                                                          │
│  最近订单                                    查看全部 →   │
│  EC2026…  黄金·年   ¥xxx   已完成   2026-10-01           │
└──────────────────────────────────────────────────────────┘
```

### 3.1 状态卡四态

| 态 | 判定 | 主按钮 |
|---|---|---|
| 未开通 | `!active` | 「开通会员」→ 收银台 level=2（推荐档） |
| 生效中 | active 且 `expire_at=0` | 隐藏（永久），仅权益对照 |
| 生效中（限期） | active 且剩余 >30 天 | 「立即续费」（续期累加语义已在 `ecod_pay_grant()` 实现） |
| 即将到期 | active 且剩余 ≤30 天 | 「续费」+ 卡片角标「剩 N 天」accent 高亮 |

已过期按未开通渲染，附一行灰色「会员已于 X 日过期」。

### 3.2 与 `page-vip.php` 的关系

- 营销页保留（面包屑/FAQ/双栏大卡不动），头部状态条加「已开通？进入我的会员 →」链接 `个人中心页 permalink#vip`。
- 会员 tab 顶部小字链接「了解会员权益 →」指回营销页。
- `$ecodTierCopy` 抽到 `core/pay/pay.php::ecod_pay_tier_copy()` 后，`page-vip.php:29-38` 改为调用该函数（删硬编码）。

---

## 4. 交互与刷新

| 场景 | 方案 |
|---|---|
| 开通/续费 | 跳 `ecod_pay_cashier_url(['kind'=>'vip','level'=>N,'plan'=>key])`，**回跳链接带 `#vip`**（`page-cashier.php` 成功态回跳地址拼接），回到个人中心即见新状态 |
| 卡密兑换 | `POST api.php?action=pay_redeem {code}`（新增）：登录态 + 频控（同 uid 60s 1 次）+ `ecod_pay_card_redeem()`；成功返回新 `vip` 状态 JSON，前端原地重渲染状态卡，不整页刷新 |
| 升级 | 已有低档 → 高档按钮文案「升级」；cashier 已有 `$ecodOwned` 档位判定（`page-cashier.php:117-136`），主题侧只做按钮文案 |
| 未登录 | 面板整体替换为登录引导（与现有未登录态一致） |

### 4.1 新接口契约

```
POST api.php?action=pay_redeem
  body: code=<卡密明文>
  200: {ok:1, vip:{active,level,name,expire_at}}
  4xx: {ok:0, msg: '卡密无效|已被使用|未登录|操作过频'}
安全：登录态、POST、频控、卡密只进 hash 比对（复用 ecod_pay_card_redeem 的 SHA-256 抢占式 UPDATE）
```

---

## 5. 样式规范

- 前缀 `.ecod_uc__vipcard*`，全部走 `ecod.css` 既有 CSS 变量（accent/表面/描边/圆角 token），明暗两主题由变量自动生效，**不写死颜色**（跨主题一致性要求）。
- 状态胶囊复用 `.ecod_uc__vip` 徽标 token（`ecod.css:9204-9215`）；即将到期用 `--warning` 变量（若无则新增 token，不硬编码色值）。
- 价格按钮复用 `.ecod-btn` / `.ecod-btn--primary` / `--ghost`（`:8675-8705`），不加新按钮变体。
- 权益对照表复用 `.ecod_uc` 表单区表格样式（`:9462-9515` 邻近体系）。
- 面板间距对齐现有 `data-ecod-uc-panel` 通用节奏，不另设 padding。

---

## 6. 权益能力映射（二期，预留）

现权益仅文案。若后续要把权益做成真判定（如资源库转 WebP 额度、对象存储配额）：

```
新表/新字段：ecod_entitlements.level 已够 → 建 ecod_pay_capabilities(level) 映射函数
读侧：ecod_pay_vip($uid)['level'] → capabilities 查表
首个落地候选：MEDIA-STORAGE.md 的配额（EMediaQuota 与 level 联动）
```

本期不实现，只在 `ecod_pay_tier_copy()` 返回结构里预留 `caps` 数组键。

---

## 7. 分期

| 期 | 内容 |
|---|---|
| P1 | tab 注册 + 状态卡四态 + 权益对照 + 价格按钮 + `ecod_pay_tier_copy()` 抽取 + 样式 |
| P2 | 卡密兑换 API + 原地刷新 + 最近订单块 + page-vip.php 导流链接 + 收银台回跳 `#vip` |
| P3（二期） | 权益能力映射 + 与资源库配额联动 |

## 8. 验收矩阵（摘要）

| 场景 | 期望 |
|---|---|
| 未登录访问 / 访问 #vip | 登录引导，无数据泄漏 |
| 未开通用户 | 状态卡「未开通」+ 开通按钮直达收银台 |
| 续费（已 level1 年付） | 到期时间累加（复用 `ecod_pay_grant()` 现语义），无降档 |
| 卡密兑换有效卡 | 状态卡原地刷新，订单列表多一条 card 来源记录 |
| 卡密已用/无效/连点 | 明确 msg，60s 频控拦截 |
| `page-vip.php` 与 tab | 双向导流链接可达，文案同源（tier_copy 单一出处） |
| 明暗主题切换 | 状态卡/表格/按钮无硬编码色，无对比度回归 |
| 深链 `#vip` | 直接激活会员 tab（hash 机制现状即支持，回归验证） |
