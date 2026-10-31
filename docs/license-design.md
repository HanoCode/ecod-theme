# 主题授权模式设计（ecod-theme × EcodLicenser）

> 状态：**设计定稿**（2026-10-31 评审通过，待实施）
> 涉及：`usr/themes/ecod-theme`、`usr/plugins/EcodLicenser`
> 已确认决策：**前台锁死降级** · **每码累计换绑 2 次** · **先填域名再付款** · 本轮只交付设计

---

## 1. 目标与边界

**一句话**：买家在自己站点输入授权码即可解锁主题；授权码由作者签发，或在作者站会员中心自助购买生成（绑定域名、累计 2 次换绑）。

| 角色 | 站点 | 装什么 | 能做什么 |
|---|---|---|---|
| 作者 | ecod.cc（主站） | ecod-theme + **EcodLicenser** | 签发 / 续期 / 改绑 / 吊销 / 看客户版本 |
| 买家（付费用户） | 自己的 Typecho | ecod-theme | 输入授权码激活、查看状态、换绑后重新激活 |
| 会员（潜在买家） | ecod.cc | — | 会员中心「我的授权」→ 购买 → 生成授权码 → 换绑 |

**边界**

- 不防有心破解（PHP 源码可见），防的是「一份授权多处部署」与「到期不续费」。
- 校验**永不阻塞前台首屏**：本地验签即时出结果，联网复验走异步。
- 前台锁死**不伤 SEO**：用 `503 + Retry-After`，不用 403 / 200 空白页。

---

## 2. 核心机制：签名授权文件

沿用插件现有格式，结构不变，仅扩展 payload 字段（向后兼容）：

```
ECODLIC1.<base64url(payload)>.<base64url(ed25519-signature)>
```

```json
{
  "v": 1,
  "key": "ECD-7K3M-9PQ2-XY8N",
  "domain": "example.com",
  "edition": "pro",
  "modules": {"pay": true, "member": true, "ads": true, "card": true, "shop": true, "split": false},
  "issued": 1760000000,
  "expire": 1791536000,
  "status": "active",
  "customer": "张三",
  "kid": "a1b2c3d4",
  "grace": 14,
  "terms": 1
}
```

新增 `grace`（离线宽限天数，作者可按客户调）与 `terms`（条款版本，便于以后调整策略）。

**为什么签名而不是在线查库**：买家改不了 `expire` / `domain` / `modules`；换服务器不用重新申请；作者站临时挂掉不影响买家站（有宽限）。

**代价**：吊销感知有延迟，靠周期性在线复验收敛。

---

## 3. 端到端流程

### 3.1 在线激活

```
买家后台「设置 → 授权」
   │ 输入 ECD-XXXX-XXXX-XXXX，点「立即激活」
   ▼
买家站 api.php?action=license_activate        （同源，避免 CORS）
   │ host 归一化 → POST 作者端
   ▼
作者端 /action/ecod-license-verify
   │ 查 key → 校验域名 → 校验状态/到期 → 生成签名文件
   ▼
{ok:1, file:"ECODLIC1…", pub:"…", kid:"a1b2c3d4", expire:1791536000, grace:14}
   │
   ▼
主题 core/license/license.php
   ├ ① payload.kid === 内置公钥 kid              → 否则 reject:kid
   ├ ② Ed25519 验签（内置公钥）                   → 否则 reject:sig
   ├ ③ payload.domain ≡ 当前 host（www 互认）     → 否则 reject:domain
   ├ ④ status=active 且 expire > now             → 否则 reject:expired
   └ ⑤ 落库 option `EcodLicense`，mode=licensed，解除锁死
```

### 3.2 离线激活

作者后台「授权码 → 详情」复制 `ECODLIC1.…` 整串 → 买家后台「授权 → 导入授权文件」粘贴。走**同一套验签**，不联网。

### 3.3 周期复验

```
每次请求 ecod_license_state()   （纯读 option，零 SQL、零网络）
   └ time() >= next_check ?
        ├ 后台请求  → 同步 curl（3s 超时），立刻出结果
        └ 前台请求  → fastcgi_finish_request() 后异步 curl，用户无感
                       成功：更新 file / checked_at / next_check = +7d，更新 site_ver
                       失败：next_check = now + 6h（退避，上限 24h）
```

- 复验失败**不等于吊销**：网络问题只累计 `offline_days`，不改变当前授权。
- `offline_days > grace` → `mode=grace`（**仍可用**，后台黄条）。
- `offline_days > grace + 14` → `mode=degraded`（锁死）。

### 3.4 自助购买 → 生成授权码（先填域，后付款）

```
会员中心「我的授权」
   │ ① 选版本（专业版/团队版）+ 周期（年/永久）
   │ ② 填绑定域名 → 实时预校验（格式 / 是否已被占用）
   ▼
api.php?action=license_buy  → ecod_pay_order_create(kind=theme, target=0, days, level)
   ▼
收银台 page-cashier.php?order_no=…   （复用现有支付通道）
   ▼
支付回调 → ecod_pay_settle() → kind=theme 分支
   ▼
主题桥接层 core/license/selfserve.php
   └ require usr/plugins/EcodLicenser/api.php → ecodl_ss_issue()
        ├ 幂等：同 order_no 已有 key → 直接返回，不重复签发
        ├ 域名唯一：该域已有有效授权 → 拒绝
        └ 建档：按 uid 找/建 customer，写 key(uid, domain, rebind_limit=2)
   ▼
会员中心展示：授权码 + 复制授权文件 + 三步激活说明
```

**失败兜底**：若付款成功但出码失败（域名被抢注、插件停用、sodium 缺失），生成一条 `status=pending` 的占位记录并在后台标红，订单 remark 写入原因；会员中心显示「已付款，出码待处理，请联系作者」——不能出现"付了钱什么都没有"。

### 3.5 换绑域名（累计 2 次）

```
会员中心「我的授权」→ 卡片「更换域名」
   ├ 剩余次数 = rebind_limit - rebind_used = 2 - 已用
   ▼
api.php?action=license_rebind → ecodl_ss_rebind($keyId, $uid, $newDomain)
   ├ 归属校验：key.uid === 当前 uid
   ├ 次数校验：rebind_used < rebind_limit，否则按钮置灰
   ├ 域名唯一：新域不能已被他人有效绑定
   └ rebind_used++ / domain 更新 / 写 ecod_lic_rebind 流水
   ▼
提示：请到站点后台点「重新激活」（旧授权文件里的域名已失效）
```

换绑后**旧站立即失效**（因为旧文件的 `payload.domain` 与新域名不符，本地域名复核直接拒绝），这是设计预期，不是 bug。

---

## 4. 授权状态机

| mode | 判定 | 前台 | 后台 |
|---|---|---|---|
| `off` | 设置项 `ELicOn=off`（作者站 / 开发环境） | 正常 | 正常 |
| `licensed` | 验签通过，`checked_at` ≤ 7 天 | 正常 | 绿色小标 |
| `stale` | 通过但 7~grace 天未复验 | 正常 | 无提示 |
| `grace` | 离线 > grace 天（网络原因） | 正常 | 黄条：请联网确认 |
| `trial` | 首次安装 7 天内未激活 | 正常 | 黄条 + 倒计时 |
| `expired` | `expire < now` | **锁死** | 红条 + 续费入口 |
| `domain` | 域名不匹配 | **锁死** | 红条：换域名需先换绑 |
| `revoked` | 在线校验返回吊销 | **锁死** | 红条 |
| `invalid` | 无文件 / 验签失败 / 时间回拨 | **锁死** | 红条 + 激活表单 |
| `nokey` | 主机无 sodium 且无 openssl | 只在线模式 | 黄条 |

### 4.1 锁死（blocking）规格

**拦截点**：`themeInit($archive)` 第一行。Typecho 在渲染任何前台模板之前调用它，在这里输出锁页并 `exit` 覆盖全部前台路由（首页 / 文章 / 分类 / 搜索 / 专题）。

**白名单（必须放行，否则买家救不活自己）**

| 路径 | 原因 |
|---|---|
| `/admin/*` | 后台全部功能，激活入口在这 |
| `/action/*` | Typecho 登录 / 登出 / 找回密码 |
| `api.php?action=license_*` | 激活、状态、异步复验 |
| `api.php?action=account_*` | 登录态确认（锁页上的管理员登录） |
| `/usr/themes/ecod-theme/assets/*` | 后台依赖的静态资源 |
| 任何 `?ecod_preview=1` 且已登录管理员 | 调试用 |

**管理员豁免**：已登录管理员一律放行，全站可正常浏览，仅顶部固定一条黄条（「本站未授权 / 到期日 2027-01-01 → 去激活」）。理由：买家需要能进站调试主题、截图对比，锁死只针对访客。

**HTTP 语义**

```
HTTP/1.1 503 Service Unavailable
Retry-After: 3600
Cache-Control: no-store
X-Robots-Tag: noindex
```

选 503 而非 403/200：搜索引擎视为「临时不可用」，保留已有收录与权重，恢复后立即回流量；403/404 会被当成死链清理。

**API 拦截**：锁死状态下，`api.php` 除白名单外的写操作（点赞 / 评论 / 投稿 / 下单 / 打卡）一律 `403 {ok:false, error:'license'}`，避免"页面锁了但接口还能刷"。

**锁页内容**（极简、不依赖主题资源，内联 CSS）

```
       [ 站点 Logo ]
          站点名

   本站正在完成授权配置，稍后恢复访问

   ─────────────────────────────
   管理员登录   →（登录后全站可见，可去激活）
```

- 不出现「盗版」「未授权」字样 —— 对外体面，对内（管理员黄条）说清楚原因。
- 跟随明暗：`prefers-color-scheme` 一套内联变量，不加载任何外部文件。
- 页面底部注入 1px `license_ping` 信标：买家续费后，锁页自动复验成功 → 下次刷新即恢复，**不需要人肉通知作者**。

**首次安装宽限（`trial`）**：新装主题后 7 天内不锁死（记 `installed_at`），后台黄条倒计时。避免"刚装完站就白了"。

**到期后是否立即锁**：是。到期即锁，不给额外宽限（离线宽限另算，那是网络问题不惩罚用户）。后台提前 15 / 7 / 1 天黄条提醒。

---

## 5. 模块级授权

插件 6 个模块 → 主题门禁点（锁死模式下不生效，仅授权有效时区分版本能力）：

| 授权模块 | 主题开关 | 门禁实现 |
|---|---|---|
| `pay` | `EPay` | `ecod_pay_enabled()` 首行 `if (!ecod_license_allows('pay')) return false;` |
| `member` | 会员档位 | `ecod_pay_vip()` 返回 level=0（保留登录态） |
| `ads` | `EAdSelf` | 广告投放入口隐藏 + `ecod_ads_*` 拒绝 |
| `card` | `EPayChannel=card` | 卡密兑换接口拒绝 |
| `shop` | `EShop` | 商城入口隐藏 |
| `split` | —（未实现） | 预留 |

原则：**门禁只加在「能力入口函数」第一行**，不散落在模板里，避免遗漏。

---

## 6. 数据结构

### 6.1 插件侧

```sql
-- ecod_lic_key 增加
ALTER TABLE ecod_lic_key
  ADD uid          INT UNSIGNED NOT NULL DEFAULT 0,   -- 归属主站会员（0=作者手动签发）
  ADD order_no     VARCHAR(24)  NOT NULL DEFAULT '',  -- 来源订单（幂等键）
  ADD product      VARCHAR(32)  NOT NULL DEFAULT 'theme',
  ADD rebind_limit TINYINT      NOT NULL DEFAULT 2,
  ADD rebind_used  TINYINT      NOT NULL DEFAULT 0,
  ADD bound_at     INT UNSIGNED NOT NULL DEFAULT 0,
  ADD instance     VARCHAR(64)  NOT NULL DEFAULT '',  -- 站点指纹（可选强绑定，默认关）
  ADD site_ver     VARCHAR(16)  NOT NULL DEFAULT '',  -- 最近上报主题版本
  ADD site_url     VARCHAR(190) NOT NULL DEFAULT '';

-- ecod_lic_customer 增加
ALTER TABLE ecod_lic_customer
  ADD uid INT UNSIGNED NOT NULL DEFAULT 0;

-- 新表：换绑流水（限次与审计双用）
CREATE TABLE IF NOT EXISTS ecod_lic_rebind (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  key_id      INT UNSIGNED NOT NULL DEFAULT 0,
  uid         INT UNSIGNED NOT NULL DEFAULT 0,
  from_domain VARCHAR(120) NOT NULL DEFAULT '',
  to_domain   VARCHAR(120) NOT NULL DEFAULT '',
  ip          VARCHAR(45)  NOT NULL DEFAULT '',
  created     INT UNSIGNED NOT NULL DEFAULT 0,
  KEY key_id (key_id), KEY uid (uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

迁移：`install()` 调 `ecodl_migrate()`，逐条 `ALTER` 包 try/catch（列已存在即报错忽略），幂等，可重复执行。

### 6.2 主题侧

**不建表**。授权状态存独立 option 行 `EcodLicense`（JSON），**不碰 `theme:ecod-theme` 大 JSON**（避免与设置表单互相覆盖）：

```json
{
  "code": "ECD-7K3M-9PQ2-XY8N",
  "file": "ECODLIC1.…",
  "kid": "a1b2c3d4",
  "domain": "example.com",
  "edition": "pro",
  "expire": 1791536000,
  "modules": {"pay": true},
  "activated_at": 1760000000,
  "checked_at": 1760000000,
  "next_check": 1760604800,
  "installed_at": 1760000000,
  "last_error": "",
  "max_seen": 1760000000,
  "instance": "sha1(host|rootUrl) 前 16 位",
  "offline_days": 0
}
```

购买/换绑复用现有 `ecod_orders` + `ecod_entitlements`：
- `kind='theme'`（varchar(10) 够），`target=0`，`level`：1=专业版 / 2=团队版，`days`。
- `entitlements(uid,'theme',0)` 标记「已购」，用于续费判断与会员中心入口显隐。

---

## 7. 接口契约

### 7.1 作者端 → 买家（HTTP）

`POST /action/ecod-license-verify`

| 入参 | 说明 |
|---|---|
| `key` | 授权码 |
| `domain` | 站点 host |
| `ver` | 主题版本（新增，用于后台看客户版本分布） |
| `inst` | 站点指纹（新增，可选） |
| `url` | 站点首页 URL（新增，仅展示用） |

出参：`{ok, file, pub, kid, expire, grace}` / `{ok:0, error}`。
失败文案保持统一口径，不泄露授权码是否存在（现状已如此，保留）。

### 7.2 主题 → 插件（进程内）

插件新增根文件 `usr/plugins/EcodLicenser/api.php`，主题 `require_once` 后调用。**只认传入的 `$uid`，不读 session**，避免与主题登录态耦合。

```php
ecodl_ss_ready(): bool
ecodl_ss_check_domain(string $domain, int $uid): array   // 下单前预校验
ecodl_ss_issue(array $spec): array
  // $spec = uid, domain, edition(pro|team), days, order_no, rebind(默认2), product
  // → ['ok'=>bool, 'message'=>string, 'key'=>['id','code','domain','expire_at'], 'file'=>string]
ecodl_ss_rebind(int $keyId, int $uid, string $domain): array
ecodl_ss_keys(int $uid): array            // 我的授权列表
ecodl_ss_key_file(int $keyId, int $uid): string
```

插件不存在 / 未激活时，主题返回 `{ok:false, message:'作者端未开启自助发码，请联系作者'}`，不白屏。

### 7.3 主题 front API（`api.php`）

| action | 方法 | 权限 | 锁死时 | 说明 |
|---|---|---|---|---|
| `license_state` | GET | 管理员 | ✅ 放行 | 读状态 |
| `license_activate` | POST | 管理员 | ✅ 放行 | 在线激活 |
| `license_import` | POST | 管理员 | ✅ 放行 | 离线导入 |
| `license_ping` | GET | 匿名 | ✅ 放行 | 异步复验（1px 信标） |
| `license_buy` | POST | 登录 | ❌ 403 | 建 theme 订单 |
| `license_issue` | POST | 登录 | ❌ 403 | 支付后取码（幂等） |
| `license_rebind` | POST | 登录 | ❌ 403 | 换绑 |

管理员判定：`Widget\User::alloc()->pass('administrator')`。

---

## 8. 安全要点

1. **内置公钥 + kid 比对**：端点返回的 `pub` 仅用于展示；验签一律用主题内置公钥，`kid` 不符直接拒。作者站被劫持也塞不进自签授权。
2. **本地域名复核**：验签通过后仍要拿 `payload.domain` 与当前 host 比 —— 否则 A 站文件复制到 B 站直接可用。
3. **时间回拨检测**：`max_seen = max(max_seen, now)`；若 `now < max_seen - 86400` → `mode=invalid`（防改系统时间续命）。
4. **换绑限次**：`rebind_used < rebind_limit`（默认 2），流水留痕，后台可查。
5. **一域一有效授权**：签发时校验（现状已有）。
6. **订单幂等**：`order_no` 与 key 一一绑定，重复回调不重复发码。
7. **实例绑定（默认关）**：开启后首个上报的 `inst` 落库，之后不同 `inst` 拒绝。防「一码多站并行」，但会误伤换服务器，作者按客户开。
8. **端点不泄密**：只记 IP 与时间（现状）。

---

## 9. 改动清单

### 插件 EcodLicenser（v1.0.0 → v1.1.0）

| 文件 | 改动 |
|---|---|
| `Plugin.php` | 版本号；`install()` 调 migrate；activate 文案 |
| `core/migrate.php` | **新增**：幂等 ALTER / 建表 |
| `core/selfserve.php` | **新增**：`ecodl_ss_*` 五函数 + 域名唯一 / 归属 / 限次校验 |
| `api.php` | **新增**：稳定桥接入口（只 require bootstrap + selfserve） |
| `Verify.php` | 接收 `ver`/`inst`/`url` 写回；返回 `grace` |
| `core/keys.php` | `ecodl_key_create()` 支持 `uid/order_no/rebind_limit/product`；新增 `ecodl_key_rebind_by_user()` |
| `core/sign.php` | payload 增加 `grace` / `terms` |
| `views/keys.php` | 列表显示归属会员、换绑次数、站点版本 |
| `views/overview.php` | 增加「自助授权数 / 今日换绑数」 |

### 主题 ecod-theme

| 文件 | 改动 |
|---|---|
| `core/license/pubkey.php` | **新增**：内置公钥 + kid |
| `core/license/license.php` | **新增**：状态机、验签、门禁、存储、异步复验、锁死拦截 |
| `core/license/block.php` | **新增**：锁页模板（内联 CSS，无外部依赖） |
| `core/license/selfserve.php` | **新增**：插件桥接 + 购买 / 发码 / 换绑服务端逻辑 |
| `functions.php` | require license；`themeInit` 首行挂锁死；后台横幅；前台 ping 注入 |
| `api.php` | 新增 7 个 action + 锁死白名单 |
| `core/admin/config.php` | 新增 `license` 面板（4 小节 12 字段） |
| `admin/config.js` + `config.css` | 授权面板渲染（状态卡 / 激活 / 导入 / 复制） |
| `page-user.php` | 新增 `license` tab 与面板（购买 / 我的授权 / 换绑） |
| `page-cashier.php` | 支持 `kind=theme` |
| `core/pay/pay.php` | `order_create` 白名单加 `theme`；`settle` 加 theme 分支 |
| `README.md` | 安装后必读：填授权码 |

---

## 10. 分期

| 期 | 内容 | 验收 |
|---|---|---|
| **P0** | 插件迁移 + Verify 增强 + 主题 license 核心 + 锁死拦截 + 后台授权面板 + 激活 / 离线导入 / 复验 | 买家站输入码 → 解锁；改域名 → 立刻 503；管理员登录 → 全站可见 + 黄条；断网 30 天 → grace 仍可用 |
| **P1** | 会员中心「我的授权」+ 购买 + 支付接入 + 换绑限次 | 付款 → 自动出码（已绑域）→ 激活成功；换绑 2 次后按钮置灰 |
| **P2** | 版本分发（插件 releases）+ 客户版本视图 + 一键续费 | 后台能看到「谁在用旧版」，可批量提醒 |

---

## 11. 已确认决策

| # | 问题 | 决策 |
|---|---|---|
| A | 未授权 / 过期怎么降级 | **前台锁死**（503 + Retry-After，管理员豁免，首次安装 7 天宽限） |
| B | 换绑次数规则 | **每码累计 2 次**，后台可按客户单独调整 |
| C | 购买时绑定域名时机 | **先填域再付款**，首绑不消耗换绑次数，下单前预校验域名占用 |
| D | 本轮交付 | **只交付设计**，不改代码 |

### 待你二次确认的两个细节

1. **到期后是否给缓冲**：当前设计是「到期即锁」。若想柔一点，可加 3 天缓冲（`expire + 3d` 才锁），后台提前 15/7/1 天提醒。
2. **锁页文案**：当前是「本站正在完成授权配置，稍后恢复访问」。若要更明确（如「站点授权已到期，请联系管理员」），改一句即可。

---

## 12. 实施顺序（P0，逐文件）

1. `EcodLicenser/core/migrate.php` → `Plugin.php` 接入 → 跑一次确认列齐全
2. `EcodLicenser/core/sign.php`（payload 加 grace/terms）→ `Verify.php` 增强
3. 取公钥写入 `ecod-theme/core/license/pubkey.php`
4. `ecod-theme/core/license/license.php`（验签 / 状态机 / 存储）
5. `core/license/block.php` + `functions.php` 拦截（此时可手动测锁死）
6. `api.php` 三个 license action + `core/admin/config.php` 面板 + `admin/config.js`
7. 端到端：签发 → 激活 → 换域名 → 503 → 改回 → 恢复

> P1 在 P0 验收通过后再动，避免一次改太多难以定位问题。
