# 增长与互动功能参考（积分 / 抽奖 / 商城 / 广告 / 投票 / AI / 返佣）

> 本轮新增的 guaqi 式增长组件全部挂在既有基建上：积分账本（`ecod_credits`）、
> 支付订单（`ecod_orders` kind 扩展）、权益（`ecod_pay_grant`）、限流（`ecod_throttle`）、
> 结构自愈（`core/lib/schema.php`）。本文是配置与接口的速查手册。

## 一、Credit 积分（core/user/credits.php）

- 开关：`设置 → 互动 → 积分与 AI → Credit 积分`（`ECrOn`），名称 `ECrName`。
- 账本只追加：余额 = `SUM(delta)`；新表 `ecod_credits`（schema v12+）。
- 任务与奖励（均有每日上限，0 = 不限）：

| 任务 | 触发点 | 配置 |
| --- | --- | --- |
| 每日签到 | 个人中心 / 头部用户菜单 / AI 页 | `ECrCheckin`（连签天数仅展示） |
| 发表评论 | `Widget\Feedback::finishComment` 钩子 | `ECrComment` + `ECrCommentCap` |
| 文章被赞 | api `like` 插入成功且非自赞 | `ECrLikeRecv` + `ECrLikeCap` |
| 发布文章 | `Widget\Contents\Post\Edit::finishPublish`，同文章只发一次 | `ECrPost` + `ECrPostCap` |
| 开会员赠送 | 会员订单结算成功 | `ECrVipGift`（0 = 关） |

- 流水类型：checkin / comment / like_recv / post / ai / lottery / lottery_win /
  vip_gift / shop / referral / admin（文案见 `ecod_credits_type_label()`）。
- 消费方接入方式：先验余额，再 `ecod_credits_add($uid, -N, '类型', ref, 备注)`，失败不落流水。

## 二、站点抽奖（core/site/lottery.php）

- 开关与参数：`设置 → 变现 → 站点抽奖`（`ELottery` / `ELotteryCost` / `ELotteryDaily`）。
- 奖品池 `ELotteryPrizes`，一行一个：`名称|权重|库存(0=不限)|类型|值`
  - 类型：`none` 谢谢参与 / `credit` 积分（值=数量）/ `vip` 会员（值=`等级:天数`）/ `text` 实物（值=兑奖说明）
  - 恰好 8 个奖品 → 抽奖页九宫格转盘动画，否则列表布局。
- 机制：保底 `ELotteryPity`（连续 N-1 抽未中 → 本抽只在有奖池）；直接兑换
  `ELotteryExchange`（固定积分换头奖，`prize_type=exchange` 不计抽奖次数）。
- 库存 = 配置库存 − `ecod_lottery_logs` 按 prize_key 的行数（惰性，不建库存表）。
- 接口：`lottery_state`（总览：概率/保底/兑换/库存/我的记录）、`lottery_draw`、`lottery_exchange`。
- 入口：页面模板 `page-lottery.php` + 挂件 `站点抽奖`（数据栏/状态行/保底/就地抽奖/我的记录）。

## 三、积分兑换商城（core/site/shop.php）

- 开关与商品：`设置 → 变现 → 兑换商城`（`EShop` / `EShopItems` / `EShopNote`）。
- 商品一行一个：`名称|价格(积分)|类型|值|库存(0=不限)|每人限购(0=不限)`
  - 类型：`vip`（值=`等级:天数`，自动发会员）/ `post`（值=文章ID，自动发单篇权益）/
    `text`（值=发货说明，记录待发货，站长在付费管理台「最近兑换」跟进）。
- 新表 `ecod_exchanges`（schema v13）：库存 / 限购按行数惰性计算；兑换即扣积分（type=shop）。
- 接口：`shop_state`、`shop_exchange`；入口：页面模板 `page-shop.php` + 挂件 `积分兑换`。

## 四、自助广告（core/site/ads.php）

- 状态机：`pending_pay`（下单）→ 支付结算 → `pending`（待审核）或 `active`（`EAdSelfReview` 关）
  → 拒绝 `rejected`；到期按 `end_at` 惰性判定。
- 定价 `EAdSelfPriceHomeTop / Sidebar / PostBottom`（元/天，留空 = 不开放该位）；
  天数范围 `EAdSelfMinDays / EAdSelfMaxDays`。
- 订单：`ecod_orders.kind = 'ad'`，`target` = 广告 id；结算回调 `ecod_ads_on_paid()`。
- 审核：付费管理台（`pay-admin.php`）「广告审核」卡，通过 / 拒绝；「最近兑换」卡看商城发货。
- 展示：挂件 `自助广告`（价目卡，带 4:1 / 16:7 比例角标，点击进投放页预选 `?slot=`）与
  挂件 `投放广告`（已上线广告图，nofollow sponsored + 外链中转）。
- 入口：页面模板 `page-ads.php`（价目 + 表单 + 我的广告）。

## 五、投票（[poll] 短代码）

```
[poll end="2026-12-31"]   可选属性：multi（多选）/ closed（只看结果）/ end（截止）
你最喜欢哪个功能？
选项一
选项二
[/poll]
```

- 身份复用收藏指纹（游客 = 浏览器指纹，登录 = `u{uid}`），一人一票，新表 `ecod_polls_votes`。
- poll id = cid + 选项内容指纹：改选项自动开新票；票数接口 `poll_state` / `poll_vote`。
- 到期自动转只读结果态（前台按 data-end 判定，无需重新保存文章）。
- 正文仍在 HTML 里，对 SEO 无损；`ecod_shortcode_strip()` 与试读平衡标签均已包含 poll。

## 六、AI 助手（page-ai.php）

- 复用「AI 摘要」的 OpenAI 兼容配置（`EAI*`），开关 `EAIChat`，每条扣 `ECrAICost` 积分
  （0 = 免费，仍登录 + 限速），上下文 `EAIChatCtx` 轮，提示词 `EAIChatPrompt`，开场白 `EAIChatWelcome`。
- 服务端 `ecod_ai_chat_call()` 成功才扣费；历史只留在页面内存，刷新即清空。
- 接口：`ai_chat`。

## 七、推广返佣（core/user/referral.php，紧凑版一级返积分）

- 流程：任意页面 `?ref=<推荐人uid>` → 写 30 天 Cookie（themeInit 捕获）→
  该访客注册成功时推荐人写入 `ecod_user_meta.name = 'referrer'`（只记第一次）→
  被推荐人每笔付费订单结算成功，推荐人得 `订单元 × ECrRefPercent% × 100` Credit（type=referral）。
- 开关：`设置 → 互动 → 积分与 AI → 推广返佣`（`ECrRefOn` / `ECrRefPercent`，1–50）。
- 自推自买不计；同一订单只返一次；返佣走积分不涉及现金结算。
- 展示：个人中心「我的积分」面板显示专属链接（点击全选复制）与累计收益。

## 八、挂件与页面清单（模块管理台 / 工具台一键创建）

| 挂件（aside 区） | 说明 |
| --- | --- |
| 站点抽奖 | 数据栏 + 保底 + 就地抽奖 + 我的记录 |
| 积分兑换 | 商品价目卡就地兑换 |
| 自助广告 | 价目卡（比例角标），点击进投放页 |
| 投放广告 | 展示已上线广告（uads） |
| 更新动态 | LIVE 滚动播报最新积分事件（`ECrTicker` 开关） |

所有新挂件在功能未开启时会显示**仅管理员可见的设置提示卡**（`ecod_block_setup_hint()`），
访客端完全隐藏。

| 页面模板 | 工具台 key |
| --- | --- |
| page-ai.php / page-lottery.php / page-shop.php / page-ads.php | ai / lottery / shop / ads |

## 九、其他增强

- 侧栏吸顶：aside 区模块统一新增「滚动吸顶」开关（`data-sticky`，纯 CSS sticky，仅桌面双栏）。
- 显示更多：`EShowMore`（off / mobile / all）+ `EShowMoreH` 折叠高度 + `EShowMoreText` 按钮文案；
  正文完整在 DOM，SEO 无损。
- 互动按钮：竖排图文 + 点赞/收藏双计数（`fav_toggle` 回传 count）。
- 站点公告：notice 块支持 bar / rotate / popup 三种模式与多条列表（repeater）。

## 十、安全与限速

所有写接口统一：POST + 同源校验 + `ecod_account_token` 令牌 + `ecod_throttle_guard`
（桶名 signin / poll / aichat / lottery / shop / ad / pay）。失败不落流水、不发货；
库存 / 限购 / 保底全部服务端裁决，前端状态仅作展示。
