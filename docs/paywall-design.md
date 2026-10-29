# Ecod · 付费与会员可见设计方案（v1）

> 参考对象：Zibll 4.x 的 `zibpay` 模块（已购商用授权，仅参考功能与交互，不复制代码）
> 状态：**P0 + manual/card 通道已实施**（§13 三条决策已拍板，见 §0.1）。
> 适用：Typecho 1.3 / PHP 8.1+ / 主题 `ecod-theme`

---

## 0. 一页结论

| 能力 | 本项目的做法 | 复用的现有设施 |
| --- | --- | --- |
| 谁可以看 | `ecod_pay_access()` 单一判定入口，返回 `allow / reason / price` | `[hide]` 的判定口径、`Widget\User` |
| 整篇付费 | 文章自定义字段 `pay_mode` + 模板层硬门禁 | `themeFields`、`post.php` |
| 片段付费 / 会员 | `[hide type="pay"]`、`[hide type="vip" level="N"]` | `ecod_sc_block()` |
| 定价 | 文章价格 + 会员价覆盖，**金额一律整数分** | 自定义字段 |
| 交易 | `ecod_orders`（钱）+ `ecod_entitlements`（权）分离 | `core/lib/schema.php` 自愈建表 |
| 支付 | `manual` / `card` / `epay` 三个适配器 | 无 jQuery，纯 PHP + 原生 JS |
| 收银台 | 独立页面模板 `page-cashier.php`，支持刷新、回跳、轮询 | 「特色页」一键创建（`core/site/pages.php`） |
| 订单中心 | 个人中心「订单」由 localStorage 改为服务端列表 | `page-user.php`、`api.php` |
| 防泄漏 | 模板层 + 摘要层 + Feed 层，三处独立兜底 | `ecod_excerpt()`、`contentEx` |

**一句话**：Zibll 把「权限、价格、订单、支付通道」揉在一个大模块里；本项目把它们拆成四个可独立测试的薄层，判定只读权益，写权益只发生在「结算 / 授予 / 核销」三处。

### 0.1 已拍板的决策（2026-10-29）

| 问题 | 决定 | 落地影响 |
| --- | --- | --- |
| 支付通道 | 暂无支付账号；**先上 `manual`（后台确认收款）+ `card`（卡密）**，`epay` 按 Zibll 同款易支付协议实现，配置了才出现在收银台 | 没有支付账号也能完整跑通「下单 → 确认 → 解锁」；`epay` 只需填 PID/KEY/网关即可启用 |
| 会员档位 | **会员（level 1）/ 超级会员（level 2）** | 两档固定，价格按「月 / 季 / 年 / 永久」四周期配置；等级只用于门槛比较，不叠加折扣 |
| 单篇有效期 | **永久**（`pay_days` 默认 0，`EPayDays` 默认 0） | 权益 `expire_at = 0`；作者仍可单篇填天数覆盖 |

其余默认值按 §13 原建议执行：作者直通**开**、游客不可购买（必须先登录）、试读 **80 字**。

> 注意：现有 `ecod_badge--vip` 已被「系列」角标占用（`functions.php` 的 `ecod_badges()`），
> 会员角标改用 `ecod_badge--member`，避免语义串味。


---

## 1. Zibll 拆解：哪些值得学，哪些不能照搬

### 1.1 值得学的四件事

| Zibll 机制 | 实现要点 | 采纳 |
| --- | --- | --- |
| 文章级付费元数据 `posts_zibpay` | `pay_type` / `pay_price` / `vip_1_price` / `vip_2_price` / `pay_doc` / `pay_details` / `pay_extra_hide` | 采纳「价格 + 会员价 + 说明」，砍掉演示链接、商品属性 |
| 短代码 `[hidecontent]` | `reply` / `payshow` / `logged` / `password` / `vip1` / `vip2`，六种门槛互斥 | 本项目已有 `[hide]` 的 login/comment/level/password，**扩展 pay/vip** 即可，不另造标签 |
| 付费卡 `pay-box` | 封面 + 标题 + 价格 + 会员价 + 销量 + 购买按钮 + 权益列表，四档插入位置 | 采纳视觉结构与三档位置，改用本主题 token |
| 订单 → 权益判定 `zibpay_is_paid()` | 先查订单，再查「免费/会员免费」，带静态缓存 | 采纳思路，但**权益独立成表**，不把「是否已购」散落在订单查询里 |

### 1.2 明确不采纳的

| Zibll 做法 | 不采纳的原因 |
| --- | --- |
| 6 种 `pay_type`（视频 / 图集 / 下载 / 产品 / 付费阅读…） | 本期只做「整篇 + 片段」；媒体类可以用现有 `[gallery]` `[video]` 叠加 `[hide type=pay]`，不必单列商品类型 |
| 游客 Cookie 订单（`zibpay_{post_id}`） | 换浏览器即失效、易被复制；本项目统一要求登录购买，权益绑 `uid` |
| 会员等级写 `user_meta` | 到期清理、退款回收、批量授予都难审计；改用权益表 |
| jQuery + Vue 后台 + Element Plus | 违反本项目技术栈纪律；后台继续用 Typecho 原生表单 + 现有自绘设置面板 |
| 9+ 支付 SDK | 只做 `manual` / `card` / `epay`，其余按接口后补，避免维护负担 |

---

## 2. 本项目的约束与两个已验证事实

### 2.1 约束

- 主题自包含：不新增必装插件；`EcodAuth` 这类可选插件不承载付费逻辑。
- 前端零框架：原生 JS 模块（`Ecod.initX`），CSS 变量 + `color-mix()`。
- 所有新表走 `core/lib/schema.php` 的「版本号 + 幂等 DDL」自愈流程。
- 后台字段走 `ecod_admin_field()`，归入左侧分组；不引入新的样式体系。

### 2.2 事实 A：单篇正文在 `themeInit` 之前就被缓存

实测调用栈（临时插桩 `Widget\Base\Contents::___content` 得到）：

```
Widget\Base\Contents->___content
Typecho\Widget->__get            ← 读 content
Widget\Base\Contents->___excerpt
Typecho\Widget->__get            ← 读 excerpt
Widget\Base\Contents->___plainExcerpt
Typecho\Widget->__get
Widget\Archive->singleHandle     ← Archive.php:1705，为 meta description 取摘要
```

结论：`Widget\Archive::singleHandle()` 在 `themeInit()`（Archive.php:675）**之前**读取了 `plainExcerpt`，于是 `#content` 已被算好并缓存。此时 `contentEx` 钩子尚未注册。

**所以：`contentEx` 不能作为文章页的门禁。** 这解释了 `post.php` 里那句「只注册基类会漏掉」的注释。

### 2.3 事实 B：Feed / 列表会在钩子注册之后重新计算正文

同一套插桩在 `/feed/` 上得到 14 次 `contentEx` 命中；首页卡片摘要同样命中。

结论：`contentEx` 适合做**第二道防线**（Feed、第三方调用、未来新增的内容消费方），但不负责文章页。

### 2.4 由此确定门禁分层（D-PAY-03）

| 层 | 位置 | 覆盖 | 职责 |
| --- | --- | --- | --- |
| L1 渲染层 | `post.php` 调 `ecod_pay_access()` | 文章页正文 | 唯一权威门禁，未授权直接不进入短代码管线 |
| L2 摘要层 | `ecod_excerpt()` | 列表卡片、搜索片段、`meta description`、OG | 付费正文不参与摘要，改用试读片段或购买说明 |
| L3 Feed 层 | `contentEx`（`Widget\Base\Contents`） | RSS / Atom / 其他内容消费方 | 兜底剥离，防止「页面看不到、订阅能看全文」 |
| L4 搜索层 | `ecod_shortcode_strip()` | 搜索结果片段 | `[hide type=pay]` 连同内容整段剥除（现有逻辑已覆盖 `hide`） |

---

## 3. 领域模型

### 3.1 三层分离（D-PAY-01）

```
门槛 Gate            权益 Entitlement             交易 Order
─────────           ─────────────────           ─────────────
文章 pay_mode   →    uid + kind + target    ←    订单支付成功
片段 hide type        expire_at                  后台手动授予
                                                 卡密核销
```

- **判定只读权益**（+ 管理员/作者直通）。
- **写权益只有三个入口**：订单结算、后台授予、卡密核销。
- 订单是流水，退款只改订单状态并回收权益，不删记录。

### 3.2 访客状态机

| 身份 | 判定 | 显示 |
| --- | --- | --- |
| 游客 | 未登录 | 登录后可购买（按钮跳登录，带 `referer` 回跳） |
| 登录 · 未购 | 无权益 | 价格 + 立即购买 + 会员价入口 |
| 登录 · 已购 | `post` 权益有效 | 正文 + 「已购买 · 永久可读 / 至 YYYY-MM-DD」 |
| 登录 · 已购过期 | `post` 权益过期 | 正文截断 + 「已过期，续费继续阅读」 |
| 会员 · 等级足够 | `vip` 权益有效且 `level >= 要求` | 正文 + 「会员权益 · 至 YYYY-MM-DD」 |
| 会员 · 等级不足 | 等级低 / 过期 | 正文截断 + 「升级会员」与「单独购买」两个入口 |
| 管理员 / 编辑 / 作者 | `pass('editor')` 或 `authorId == uid` | 正文 + 「管理视角：已解锁」角标 |

> 作者直通建议保留（Zibll 同），但后台留开关 `EPayAuthorFree`，默认开。

### 3.3 价格规则（D-PAY-04）

| 文章 `pay_mode` | 含义 | 会员价 | 单篇价 |
| --- | --- | --- | --- |
| 空 / `free` | 免费 | 不显示 | 不显示 |
| `vip` | 仅会员 | 必须配置至少一档 | 不显示 |
| `paid` | 仅单篇购买 | 可空（空 = 无会员优惠） | 必填 |
| `paid_vip` | 可买可会员 | 可空 | 必填 |

- `pay_vip1` / `pay_vip2`：**空** = 该档不适用；**0** = 该档免费；**>0** 必须小于原价，否则忽略该档。
- 生效价 = 访客等级对应会员价（有效且 < 原价），否则原价。
- 金额在**下单瞬间**由服务端重算并写入订单快照；前端传的价格一律丢弃。
- 元 → 分：`(int) round($yuan * 100)`；展示：`number_format($cents / 100, 2)`。

---

## 4. 数据模型

### 4.1 新表（D-PAY-02）

`core/lib/schema.php` 增加 `ecod_schema_ensure_pay()`，版本号 `ECOD_SCHEMA_VERSION` 由 5 → 6；沿用三适配（MySQL / PostgreSQL / SQLite）。

```sql
-- 交易流水
CREATE TABLE `{prefix}ecod_orders` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_no`   CHAR(24)     NOT NULL,
  `uid`        INT UNSIGNED NOT NULL,
  `kind`       VARCHAR(10)  NOT NULL,          -- post | vip
  `target`     INT UNSIGNED NOT NULL,          -- cid 或 0（vip）
  `level`      TINYINT UNSIGNED NOT NULL DEFAULT 0, -- vip 等级
  `title`      VARCHAR(190) NOT NULL,
  `amount`     INT UNSIGNED NOT NULL,          -- 分
  `currency`   CHAR(3)      NOT NULL DEFAULT 'CNY',
  `days`       INT UNSIGNED NOT NULL DEFAULT 0,-- 0 = 永久/按文章
  `status`     VARCHAR(12)  NOT NULL DEFAULT 'pending', -- pending|paid|closed|refunded
  `channel`    VARCHAR(20)  NOT NULL DEFAULT '',
  `trade_no`   VARCHAR(64)  NOT NULL DEFAULT '',
  `created`    INT UNSIGNED NOT NULL,
  `paid_at`    INT UNSIGNED NOT NULL DEFAULT 0,
  `expire_at`  INT UNSIGNED NOT NULL DEFAULT 0,
  `ip`         VARCHAR(45)  NOT NULL DEFAULT '',
  `ua`         VARCHAR(255) NOT NULL DEFAULT '',
  `remark`     VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_no` (`order_no`),
  KEY `uid_status` (`uid`, `status`),
  KEY `kind_target` (`kind`, `target`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 访问权益
CREATE TABLE `{prefix}ecod_entitlements` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uid`       INT UNSIGNED NOT NULL,
  `kind`      VARCHAR(10)  NOT NULL,           -- post | vip
  `target`    INT UNSIGNED NOT NULL,           -- cid 或 0
  `level`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `source`    VARCHAR(12)  NOT NULL,           -- order|admin|card|gift|trial
  `source_id` VARCHAR(64)  NOT NULL DEFAULT '',
  `start_at`  INT UNSIGNED NOT NULL,
  `expire_at` INT UNSIGNED NOT NULL DEFAULT 0, -- 0 = 永久
  `created`   INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid_kind_target` (`uid`, `kind`, `target`),
  KEY `uid_expire` (`uid`, `expire_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

设计说明：

- `entitlements` 的 `UNIQUE (uid, kind, target)` 让「一人一篇一条」「一人一个会员状态」成立；会员升级/续期是 **UPDATE** 而非插新行，历史留在 `orders`。
- `orders.target` 对会员为 0、等级放 `level`，避免 `(kind= vip, target=1)` 与「目标 id」语义混淆。
- `expire_at = 0` 表示永久，读取时不做时间比较；**过期不在读取时改写数据**，避免 GET 请求产生写操作。

### 4.2 文章字段（`themeFields`，`functions.php`）

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `pay_mode` | select：`''` / `vip` / `paid` / `paid_vip` | 空 = 免费 |
| `pay_price` | text | 单篇价（元），如 `9.90` |
| `pay_vip1` | text | 会员 1 价；空 = 不适用，0 = 免费 |
| `pay_vip2` | text | 会员 2 价；同上 |
| `pay_days` | text | 购买有效期（天），0/空 = 永久 |
| `pay_doc` | textarea | 购买说明（显示在付费卡） |

> 字段落在 `table.fields.str_value`（Typecho 1.3 的列名，`[article]` 曾踩过 `value` 的坑）。

### 4.3 用户维度

不写 `user_meta`。会员状态由 `entitlements(kind='vip', target=0)` 唯一一行表达；展示层用 `ecod_pay_vip($uid)` 取 `level / expire_at`，带请求内静态缓存。

### 4.4 主题设置新增分组 `pay`

| 键 | 默认 | 说明 |
| --- | --- | --- |
| `EPay` | `off` | 总开关；关闭时全链路零查询、零输出 |
| `EPayCurrency` | `¥` | 货币符号 |
| `EPayAuthorFree` | `on` | 作者本人直通 |
| `EPayTeaser` | `80` | 无 `<!--paymore-->` 时的试读字数，0 = 不试读 |
| `EPayBox` | `top` | 付费卡位置：`top` / `bottom` / `both` / `off` |
| `EPayDays` | `0` | 默认购买有效期（天） |
| `EPayChannel` | `manual` | `manual` / `card` / `epay` |
| `EPayEpayUrl` | — | 易支付接口地址 |
| `EPayEpayPid` | — | 商户 ID |
| `EPayEpayKey` | — | 商户密钥（不回显明文） |
| `EVip1Name` / `EVip1Price` | `会员` / — | 等级 1 名称与月价 |
| `EVip2Name` / `EVip2Price` | `超级会员` / — | 等级 2 |

---

## 5. 判定与渲染

### 5.1 单一判定入口（D-PAY-05）

```php
/**
 * @return array{
 *   enabled:bool, mode:string, allow:bool, reason:string,
 *   price:int, vip:int, level:int, expire_at:int, purchased:bool
 * }
 */
function ecod_pay_access($archive = null, ?int $uid = null): array
```

`reason` 取值：`free` / `admin` / `author` / `purchased` / `vip` / `expired` / `login` / `none` / `disabled`。

判定顺序（短路）：

1. 主题开关关闭或文章 `pay_mode` 为空 → `allow=true, reason=free`（零查询）
2. 管理员 / 编辑 → `allow=true, reason=admin`
3. 作者且 `EPayAuthorFree` → `allow=true, reason=author`
4. 未登录 → `allow=false, reason=login`
5. `post` 权益有效 → `allow=true, reason=purchased`
6. 会员权益有效且 `level >= 文章要求` → `allow=true, reason=vip`
7. 其它 → `allow=false`，`reason` 区分 `expired`（有权益但过期）/ `none`

> `[hide]`、付费卡、摘要、Feed 全部调用同一函数，避免「页面已购、短代码仍锁」的错位。

### 5.2 文章页渲染（`post.php`）

```php
$state = ecod_pay_access($this);

if ($state['allow']) {
    // 现有管线，一字不改
} else {
    $preview = ecod_pay_teaser_html((string) $this->content, (int) ecod_option('EPayTeaser', 80));
    $body    = ecod_shortcode_apply($preview, $this);   // 只处理试读部分
    echo $body . ecod_pay_box($this, $state);           // 付费卡
}
```

要点：

- **先截断再跑短代码**：付费段里的 `[gallery]`、`[video]` 不会被解析，既省性能又避免旁路。
- 试读切分优先级：`<!--paymore-->` → `EPayTeaser` 字数 → 0（只显示 `pay_doc`）。
- 密码文章（`$this->hidden`）优先走密码流程，解锁后再判付费，两个门槛不叠加展示。
- 付费卡位置由 `EPayBox` 决定：`top` 在正文前、`bottom` 在正文后、`both` 两处（文末卡不带封面）。

### 5.3 片段级门槛（`core/content/shortcode.php`）

```text
[hide type="pay"]…[/hide]              未购买 → 行内解锁条
[hide type="vip" level="1"]…[/hide]    会员等级不足 → 升级提示
[vip1]…[/vip1] / [vip2]…[/vip2]        语法糖，等价上一行
```

- 复用 `ecod_sc_block()`，未授权时**只输出解锁条**，正文不进 HTML。
- 解锁条按钮：已登录 → 滚动到文末付费卡 / 跳收银台；游客 → 登录。
- 管理员/作者走同一 `ecod_pay_access()`，不再单独写一套判断。

### 5.4 摘要与 Feed（D-PAY-06）

`ecod_excerpt()` 增加前置分支：

```php
$state = ecod_pay_access($archive);
if ($state['enabled'] && !$state['allow']) {
    $doc = trim((string) ($archive->fields->pay_doc ?? ''));
    return $doc !== '' ? $doc : '付费内容，购买后可阅读全文。';
}
```

- Feed：`contentEx` 兜底（已验证在 Feed 生效），把未授权正文替换为摘要 + 链接。
- `meta description` / OG：走 `ecod_seo_description()` → `ecod_excerpt()`，自动被上一层覆盖。
- 搜索：`ecod_shortcode_strip()` 已整段剥除 `[hide]`，无需额外改动。
- sitemap：只输出链接，无正文，不需要处理。

---

## 6. 接口契约（`api.php`）

| action | 方法 | 登录 | 入参 | 说明 |
| --- | --- | --- | --- | --- |
| `pay_create` | POST | 必须 | `cid`, `token` | 创建/复用 pending 订单，返回收银台地址 |
| `vip_create` | POST | 必须 | `level`, `plan`, `token` | 创建会员订单 |
| `pay_status` | GET | 必须 | `order_no` | 仅本人可查；返回 `status / paid_at` |
| `pay_notify` | POST/GET | 否 | 通道参数 | 验签 → 结算 → 输出 `success` |
| `pay_return` | GET | 否 | 通道参数 | 验签 → 跳回文章/会员页 |

通用约束：

- 所有写操作：`same-origin` + `ecod_account_token()`（复用个人中心的令牌机制）。
- 限流：`ecod_throttle_guard('pay')`，10 次 / 10 分钟 / uid。
- 订单号：`EC + YmdHis + 6 位随机`，`UNIQUE` 兜底；重复创建时 15 分钟内复用 pending 单。
- 返回体不含价格计算过程，只有最终金额。

---

## 7. 支付通道适配层（D-PAY-07）

```php
interface EcodPayChannel
{
    public function id(): string;
    /** @return array{mode:'redirect'|'qrcode'|'html', url?:string, qr?:string, html?:string} */
    public function create(array $order): array;
    /** @return array{ok:bool, order_no?:string, trade_no?:string, amount?:int} */
    public function verify(array $request): array;
}
```

| 通道 | 场景 | 结算方式 |
| --- | --- | --- |
| `manual` | 没有支付账号、线下转账、朋友代付 | 后台「待确认」列表 → 管理员点「确认收款」→ 结算 |
| `card` | 卡密分发（淘宝/发卡平台/活动赠送） | 输入卡密 → 事务内核销 → 结算，卡密只存哈希 |
| `epay` | 个人可申请的易支付，聚合支付宝/微信 | 跳转支付 → 异步回调验签 → 结算 |

易支付参数与验签（与 Zibll 同协议，仅沿用公开协议，不复制实现）：

- 下单：`pid` `type` `out_trade_no` `notify_url` `return_url` `name` `money` `sign` `sign_type=MD5`
- 签名：参数按 key 升序拼接 `k=v&…`（剔除 `sign`、`sign_type` 与空值）+ 商户密钥 → `md5` 小写
- 回调：验签 + `trade_status=TRADE_SUCCESS` + `money` 与本地 `amount` 一致 + 订单存在

### 7.1 结算幂等（必须）

```php
/* Typecho 的 Db::query() 对 UPDATE 直接返回影响行数（没有 affectedRows() 方法） */
$affected = $db->query($db->update('table.ecod_orders')
    ->rows(['status' => 'paid', 'paid_at' => $now, 'trade_no' => $tradeNo])
    ->where('order_no = ?', $orderNo)
    ->where('status = ?', 'pending'));

if ((int) $affected !== 1) {
    return true;   // 已处理过，直接回 success
}
ecod_pay_grant($order);   // 同一事务内写权益
```

重复回调、并发回调、前端轮询都不会重复发放权益。

---

## 8. 后台

### 8.1 设置分组

左侧新增「付费」：总开关 → 定价 → 支付通道 → 展示。字段见 §4.4。

### 8.2 文章编辑侧栏

`themeFields()` 增加 §4.2 的六个字段，编辑器右侧原生渲染；`pay_mode` 选择「付费」后其余字段才有意义的提示写在字段描述里。

### 8.3 订单与会员管理

| 阶段 | 能力 |
| --- | --- |
| P1 | 「订单」页：最近 50 条、按订单号/用户搜索、手动补单、标记退款 |
| P2 | 筛选（状态/通道/时间）、导出 CSV、会员列表（授予/延长/降级）、卡密批次生成与导出 |

后台页面沿用 `css/admin/*` 现有样式，不引入表格库。

---

## 9. 前台 UI 规范

### 9.1 付费卡结构

```html
<section class="ecod_pay" data-ecod-pay data-cid="67" data-state="locked">
  <div class="ecod_pay__head">
    <span class="ecod_pay__badge">付费</span>
    <b class="ecod_pay__title">解锁全文</b>
  </div>
  <p class="ecod_pay__doc">…购买说明…</p>
  <ul class="ecod_pay__perks">
    <li>永久可读 · 后续更新免费</li>
    <li>支持支付宝 / 微信</li>
  </ul>
  <div class="ecod_pay__foot">
    <div class="ecod_pay__price"><i>¥</i>9.90<span>会员 ¥5.00</span></div>
    <a class="ecod-btn ecod-btn--primary" href="/cashier.html?post=67">立即购买</a>
    <a class="ecod-btn ecod-btn--ghost" href="/vip/">开通会员</a>
  </div>
</section>
```

- 视觉语言与 `ecod_replybox` / `ecod_pwbox` 一致：同圆角、同 1px 边框、同锁图标。
- 三态：`locked`（可买）、`owned`（已购，绿色对勾）、`expired`（续费，琥珀色）。
- 移动端：价格与按钮同排，按钮最小高度 44px；窄屏时按钮换行占满。
- 暗色：用 `color-mix(in srgb, var(--ecod-primary), #000 12%)` 生成强调色，不写死颜色。
- 无 JS 降级：购买按钮是真实 `<a href>`，指向收银台页面；不依赖弹层。

### 9.2 列表角标

`ecod_badges()` 增加：

- `pay` → `.ecod_badge--pay`（`¥` 图标 + 价格，或「付费」）
- `vip` → `.ecod_badge--vip`（`medal` 图标 + 会员）

与「置顶 / 系列 / 新 / 密码」同一容器 `.ecod_card__badges`，最多显示 3 个，付费优先。

### 9.3 收银台

`page-cashier.php`（「特色页」一键创建，`noindex`）：

- 订单摘要（标题、金额、有效期）
- 支付区：二维码 / 跳转按钮 / 卡密输入（按通道切换）
- 状态轮询：`pay_status` 每 2 秒，最多 5 分钟；成功后展示「已解锁，返回阅读」
- 失败/关闭：显示「重新下单」，旧单保持 `pending`，15 分钟后自动失效

### 9.4 会员购买页（page-vip.php）

复用 `ecod_page_hero`；主体为两档 Bento 卡（`.ecod_vip__card`），单 accent 色，暗色全走 token：

- **档位卡头部**：档位图标 + 名称 + 一句话说明；两档都有价时 Lv.2 加「推荐」pill（`.ecod_vip__pick`）
- **权益清单**：复用付费卡 `.ecod_pay__perks`（check 图标），Lv.1 三条、Lv.2 三条（含会员全部权益、可读更高等级、专属标识）
- **周期价 tile**（`.ecod_vip__plan`）：2 列网格，tile 内三行——周期名 + 天数 + 大号 accent 价格；`Forever` 显示「长期有效」；年付（days ≥ 360）加「折合 ¥x/月」小字（`.ecod_vip__plan__avg`）；整个 tile 是链接，直达收银台
- **当前身份**：已登录会员显示状态条 `.ecod_vip__me`（身份 + 到期时间 + 续费累加说明），非会员不显示
- **FAQ**（`.ecod_vip__faq`）：三条静态问答——如何开通（点价格进收银台）、到期会怎样（自动回落、已购文章不受影响）、能否升级（超级会员含会员全部权益）
- **移动端 640px**：档位卡单列，价格 tile 保持 2 列（价格短，不需降列）

---

## 10. 安全与防泄漏清单

| 风险 | 处置 |
| --- | --- |
| 查看源码 / 抓包绕过 | 未授权正文**不进入 HTML**，不是 `display:none` |
| Feed 泄露全文 | `contentEx` 兜底（§2.3） |
| 摘要 / OG / 搜索结果泄露 | `ecod_excerpt()` 前置判定（§5.4） |
| 短代码执行旁路 | 先截断后解析（§5.2） |
| 价格篡改 | 前端不传价格；下单时服务端按 cid + 等级重算 |
| 越权查订单 | `pay_status` 校验 `uid`，不匹配返回 404 而非 403 |
| 回调伪造 | 验签 + 金额比对 + 订单存在性 |
| 回调重放 / 并发 | `UPDATE … WHERE status='pending'` + 影响行数判断 |
| 重复发权益 | 权益表唯一索引 + 事务 |
| 刷单 / 暴力下单 | 限流 + pending 单复用，不自建无主订单 |
| 卡密爆破 | 长随机码（≥16）+ 哈希存储 + 失败计数 |
| 时区错乱 | 统一 `time()`，展示用 `$options->timezone` |
| 日志泄露 | 不记录密钥、卡密明文、完整 UA |
| 作者/管理员误判 | 直通规则显式配置，且后台显示「管理视角」 |

---

## 11. 分期实施

### P0 · 地基（无支付也能用）

| 文件 | 改动 |
| --- | --- |
| `core/pay/pay.php` | 新建：`ecod_pay_access()`、`ecod_pay_price()`、`ecod_pay_vip()`、`ecod_pay_grant()`、`ecod_pay_box()`、`ecod_pay_teaser_html()` |
| `core/lib/schema.php` | 两张表 + 版本号 6 |
| `functions.php` | `require core/pay/pay.php`；`themeFields` 增 6 字段；`ecod_badges()` 增角标；`ecod_excerpt()` 增付费分支 |
| `core/admin/config.php` | 新增 `pay` 分组与字段 |
| `core/content/shortcode.php` | `hide` 支持 `pay` / `vip`；`[vip1]` `[vip2]` 糖 |
| `post.php` | 门禁分支 + 付费卡插入 |
| `page-user.php` + `assets/js/ecod.global.js` | 订单 tab 改服务端列表（先只读） |
| `assets/css/ecod.css` | §23.25 付费卡 / 角标 / 收银台 |

验收：管理员用后台「授予」给测试账号开通，能完整走通「锁定 → 授予 → 解锁 → 过期 → 再锁」。

### P1 · 交易闭环

订单表落地、`pay.php` 收银台、`api.php` 五个 action、`manual` + `card` + `epay` 三通道、回调幂等、个人中心可查订单。

### P2 · 会员与运营

会员套餐（月/季/年/永久）、续费与升级、后台订单与会员管理、销量统计、付费文章列表页、到期提醒邮件（复用现有 SMTP）。

### P3 · 媒体与营销

付费附件 / 付费图集（叠加现有 `[gallery]`）、付费视频、优惠券、邀请返利。

---

## 12. 验收矩阵

| 身份 \ 模式 | free | vip | paid | paid_vip |
| --- | --- | --- | --- | --- |
| 游客 | 全文 | 锁定 + 登录 | 锁定 + 登录 | 锁定 + 登录 |
| 登录未购 | 全文 | 锁定 + 开会员 | 锁定 + 购买 | 锁定 + 两个入口 |
| 已购 | 全文 | 锁定 + 开会员 | 全文 | 全文 |
| 已购过期 | 全文 | 锁定 | 锁定 + 续费 | 锁定 + 续费 |
| 会员（等级足） | 全文 | 全文 | 全文 | 全文 |
| 会员（等级不足） | 全文 | 锁定 + 升级 | 锁定 + 购买/升级 | 同左 |
| 管理员 / 作者 | 全文 | 全文 | 全文 | 全文 |

附加用例：

1. `curl` 文章页，付费正文关键串出现次数为 0；`/feed/` 同样为 0。
2. 重复回调同一 `order_no` 两次，权益只有一条，`paid_at` 不变。
3. 把 `amount` 改成 0.01 再回调，验签失败 / 金额不符，订单仍 `pending`。
4. `pay_status` 查他人订单返回 404。
5. `expire_at = now - 1` 与 `now + 1` 两侧边界各测一次。
6. 390px 与暗色模式下，付费卡、角标、收银台无溢出、对比度达标。

---

## 13. 决策记录（原开放问题）

| # | 问题 | 结论 | 说明 |
| --- | --- | --- | --- |
| 1 | 支付通道 | 无易支付账号 → `manual` + `card` 先行，`epay` 按协议实现、配置后启用 | 与 Zibll 易支付协议完全一致（参数 / 签名 / 回调字段），拿到账号只需填三项 |
| 2 | 会员档位 | 会员（1）/ 超级会员（2） | 周期价：月 / 季 / 年 / 永久；不引入等级折扣、不按周期分档 |
| 3 | 作者直通 | 保留，默认开（`EPayAuthorFree=on`） | 后台可关；直通时正文带「作者视角」标记，避免误判成已购买 |
| 4 | 游客购买 | 禁止 | 权益必须绑 `uid`；未登录点购买跳登录页并带 `referer` 回跳 |
| 5 | 单篇默认有效期 | **永久** | `pay_days` 为空/0 即永久；填天数则过期后再锁 |
| 6 | 试读 | 默认 80 字，支持 `<!--paymore-->` 精确截断；0 = 不试读 | 截断发生在短代码解析之前 |

实施拆分（按此顺序提交）：

1. **P0** 地基：两张表 + `core/pay/pay.php` + 文章字段 + 付费卡 + `[hide type=pay|vip]` + `[vip1] [vip2]` + 角标 + 配置分组。
2. **P1a** 手动闭环：`manual` 下单 → 后台「确认收款」→ 结算发权益 → 解锁；后台管理台 `pay-admin.php`。
3. **P1b** 卡密：批次生成（只存 SHA-256）→ 收银台核销 → 事务内发权益。
4. **P1c** 易支付：下单跳转 + 验签回调 + 幂等结算。
5. **P2** 会员购买页、续费升级、订单中心筛选、到期提醒。

---

## 14. 实施验收记录（2026-10-29，P0 + P1a + P1b 已落地）

### 14.1 端到端实测（全部通过）

| 场景 | 结果 |
| --- | --- |
| 游客看 71（paid） | 付费卡 + 试读；BETA/GAMMA/VIP1 = 0；无裸短代码 |
| 游客 meta/OG 描述 | 输出 `pay_doc` 文案，不再泄漏正文 |
| 登录未购 | 锁定；下单金额 = 原价 9.90（非会员价） |
| 后台确认收款 | 订单 `paid` → 权益写入 → 文章全文可见（`data-state="owned"`） |
| 会员授予 Lv.1 | 71 内 `[vip1]` 解锁；72（Lv.2）仍锁 |
| 卡密核销（Lv.2 永久） | 权益升级 Lv.2、`expire_at=0`；0 元留痕订单生成；72 解锁 |
| 权益过期 | `data-state="expired"` + 「重新购买」+ 到期日期提示 |
| RSS / 首页 / 列表 | 关键串 0 次；列表显示付费角标 |
| 截图 | 1280 / 390 / 暗色（`?ecod-dark=1`）均无溢出、对比度正常 |

### 14.2 实测发现并修复的坑（比设计稿多出来的）

1. **表名前缀**：Query 构建器的表名必须写 `table.ecod_orders` 形式，裸 `ecod_orders` 不会自动加前缀（报 1146）。
2. **`Db::affectedRows()` 不存在**：`Db::query()` 对 UPDATE/DELETE 直接返回影响行数，取返回值判断；原先的写法抛 Error 被 catch 吞掉，导致「结算成功但权益没发」「卡密核销失败」两个静默故障。
3. **meta 描述泄漏**：`Archive::singleHandle()` 在 `require functions.php` **之前**就把 `archiveDescription` 设为 `plainExcerpt`，主题钩子拦不住 → 在 `themeInit()` 里用 `setArchiveDescription()` 覆盖（`ecod_pay_describe()`），另挂 `excerptEx` 兜底 Feed。
4. **试读切开短代码**：80 字截断可能落在 `[hide]` 内部，闭合标签丢失后整段标签裸露 → `ecod_pay_teaser_balance()` 把落单开标签连同其后内容截掉。
5. **短文整篇泄出**：全文不足试读字数时会把 100% 正文当试读 → 规则改为「必须留下最后一块不解锁」。
6. **游客按会员价结算**：`ecod_pay_price()` 原逻辑把展示提示价当结算价 → 会员价只对真会员生效，游客显示原价 + 「会员价 ¥x」提示（`.ecod_pay__viphint`）。
7. **收银台不能用 `?cid=`**：Typecho Archive 把 `cid` 当单篇参数，页面直接 404；统一用 `?post=`。

### 14.3 测试数据清理

测试文章（71/72）、临时页（73/74/75）、订单、权益、卡密、测试用户（uid=10）已全部删除；
管理员 `authCode` 曾被测试脚本临时改写，已从 binlog 前镜像恢复原值。
「收银台」（cid=69）与「会员」（cid=70）两个产品页保留。
