# Ecod Theme · 架构说明与演进记录

> 本文记录主题的分层设计与已落地的架构/性能决策（2026-10-30 梳理并实施）。
> 与 README「目录结构」「前端构建」「服务器加固」三节保持同步。

---

## 一、分层现状

```
┌─ 入口层（Typecho 约定，必须在主题根目录）
│   模板壳：index / archive / post / page / page-* / friends…（26 个）
│   独立入口：api.php / media.php / pay-admin.php / manifest.php / widget.php
│   引导：functions.php（钩子注册 + require 清单 + 视图工具函数）
│
├─ 领域层 core/（按领域分组，一个文件一个职责）
│   content/  内容处理：shortcode、shortcode-extra、oembed、thumbs、related、search、ai
│   site/     站点能力：seo、nav、sort、catstats、topics、meta-meta、go、pages、friends、mail
│   user/     账户：account、oauth、meta（用户扩展资料：一句话简介 + 作者卡数据组装）
│   pay/      交易：pay、pay-channel
│   admin/    后台：config、snapshot、blocks、blocks-def
│   lib/      基建：asset（构建产物解析）、schema（表结构自愈）
│   data/     纯数据：emoji.php（跨组共用，保持平铺）
│
├─ 视图层 components/（卡片、页面头、首页模块、侧栏挂件、页脚块、专题组件）
│   布局壳 public/（header / footer / aside / include）
│
└─ 静态资源 assets/
    源码：css/ecod.css、js/ecod.global.js（全站）、js/ecod.post.js（内容页增强）
    产物：dist/*（构建生成：哈希文件名 + manifest.php + .gz/.br 副产物）
    第三方：vendor/prism（MIT，不二次加工）、vendor/ecod-qr（自研）
```

**关键约束：根目录模板动不了。** Typecho 按文件名发现模板（`index.php` 必需，
`post.php` / `page.php` / `archive.php` 按命名约定解析，自定义页面模板扫描主题根目录
`*.php` 的 docblock）。所以「目录架构化」的正确姿势不是把模板挪进子目录，而是：

- 根目录只留「壳」——模板里不写业务，只组装组件（index.php 41 行）；
- 业务下沉 `core/`（已按六域分组），视图下沉 `components/`；
- 独立入口共用基建（cookie 前缀校正、`ecod_option` 最小实现、`core/lib/asset.php`）。

## 二、已落地的架构与性能决策

### 2.1 前端构建管线（`build/`，esbuild 单依赖）

| 项 | 说明 |
| --- | --- |
| 策略 | 逐文件 minify，1:1 映射——不合并、不改调用方式，PHP 调用点零改动 |
| 产物 | `assets/dist/<name>.<hash8>.min.<ext>` + `.gz`（gzip -9）+ `.br`（brotli q11） |
| 清单 | `assets/dist/manifest.php`（PHP 数组，opcache 友好） |
| 接入 | `core/lib/asset.php` 唯一查询入口；`ecod_asset()` / `widget.php` / `admin/editor.php` 统一走它，**命中发哈希文件，未构建自动回退源文件 + `?v=filemtime`** |
| 分发 | dist 连同 manifest 提交进 git，服务器与用户都不需要 Node |

### 2.2 JS 按页拆包（首屏 JS -46%）

`ecod.global.js` 5647 行拆为两个文件：

- **global**（3080 行，全站加载）：主题切换、抽屉、导航、进度、PJAX、点赞收藏、
  搜索、懒加载、轮播、账户登录等；
- **post**（2638 行，仅 post/page 模板加载）：目录、代码高亮、灯箱、评论体系、
  朗读、分享图、AI 摘要、oEmbed、选项卡、收银台等 21 个模块。

加载机制（三路保险，任一失效都不会白屏）：

1. 页脚按 `$this->is('post') || $this->is('page')` 输出第二个 `<script defer>`；
2. shell 上的 `data-ecod-bundle="post"`（header.php 双分支输出）供 JS 判断；
3. PJAX 从列表页进入内容页时，`ensurePostBundle()` 动态注入 post 包后再跑 refresh；
   `runPost()` 让 refresh/boot 对缺失模块静默跳过。

公共工具经 `Ecod.u` 导出（once / copyText / esc / shortNum / syncFavButtons），
post 包头部做了空实现兜底。实测：首页/文章直载/PJAX 双向导航均零控制台报错。

### 2.3 CSS 按页面族分包 + 分族关键 CSS 内联（S1–S4，2026-10-31）

三段式：先让通用件归位（S1），再按页面族切分产物并按页下发（S2/S3），
最后只把首屏件内联进 `<head>`（S4）。

> 立项依据：CDP CSS coverage 实测证明「直接按节拆包」不可行——按钮、徽标、灯箱、
> 登录弹层、轮播、模块头这些**跨页通用件散落在各业务节里**，按节切分会在某些
> 页面丢样式。所以先做归位，再谈拆包。

**S1 通用组件层归位**：跨族复用件集中到 `ecod.css` §3.5（插在 §3 导航之后、
§4 之前——通用层在业务层之前，业务覆盖关系保持不变）。

**S2 按页面族分包**（`build/build.mjs` 的 `PAGE_SETS`）：

| 族 | 主要内容 | 出现页面 |
| --- | --- | --- |
| core | §0 令牌 …… §40 打卡（含 §3.5 通用层、§4 文章流、§5 卡片、§6 分页、§7 侧栏） | 全站必带 |
| home | §4.5 轮播、§24@1、§25@1 首屏 Hero | 首页 |
| list | §24@1 列表排序浮层（2.4KB） | 首页 / 归档 / 搜索 / 独立页 |
| post | §8 文章、§9 评论、§17 付费、§20 相关、§41 正文增强 … | 文章页 |
| postext | §23@1 扩展短代码、§31 | 正文命中扩展短代码时按需追加（省 44KB） |
| user | §14@1、§24@0、§29、§30 | 个人中心 / 商城 / 收银台 / AI / 排行榜 |
| page | §8.5、§9、§14@0、§15、§19 | 其余独立页 |

构建期 `assertPageSetsCoverAll()` 断言：每个分节都必须有归属、且不得引用不存在的
节——新增分节被漏掉会直接构建失败，不会静默丢样式。产物为
`ecod.fam.<族>.<hash>.min.css` + `.gz` / `.br`。

**S3 按页下发**（`ecod_css_family()` + `public/include.php`）：页面只发 core + 所属
族，`<link rel="preload" as="style" onload="…rel='stylesheet'">` 异步转正
（`<noscript>` 兜底）；shell 上的 `data-ecod-fams` 供 PJAX 的 `ensurePageCss()`
判断缺哪族再注入；所有族 link 的 `onload` 计数归零后才打 `data-ecod-cssready`
并派发 `ecod:cssready`——样式不齐不跑入场动画。未构建（无族产物）时回退整包
阻塞加载，行为与旧版一致。

**S4 分族关键 CSS**（`CRITICAL_BASE` / `CRITICAL_FAMS`）：内联 = 首帧骨架 + 本族
首屏内容块，按「最具体族优先」选用（postext 复用 post 份）。取舍原则是实测出来的：

- **留下**：令牌 / 基础 / 布局 / 导航 / 侧栏 / 暗色 / 响应式 / 移动端加固 /
  移动 dock 与公告 / 进度回顶 / 搜索面板 / 懒加载占位（防首屏图片高度跳变）；
- **让出**：§3.5 通用组件层（27.8KB，首帧多为隐藏态）、页脚装饰、toast；
- **侧栏与搜索面板不能只按体积砍**：侧栏晚到 300ms 内是无样式裸文本列表（观感像坏
  了），搜索面板缺 `display:none` 会在左上角裸奔原生 input——只有限速帧截图能看到。

内联体积（min / gzip）：home 111KB/18.0KB、list 109/17.9、post 118/19.3、
user 92/15.4、page 93/15.5；相比统一 critical 减 20~27%，HTML 原始体积随之
下降 20~25%。

**验证方法**（两个思路，改 critical 后应复跑）：

1. **层叠安全 A/B**：同一次页面加载内移除 `<style id="ecod-critical">` 再量计算
   样式，8 类页面差异必须为 0——证明内联只是加速，不参与最终层叠顺序；
2. **首帧防闪**：1.6Mbps / RTT 40ms 限速下截 150/350/700ms 帧，对照最终态看有
   没有裸奔元素（比「看体积」可靠得多）。

> 设计取舍：CSS 源码保持单文件不拆 `src/`——分节标记已足够构建期切分，
> 避免为组织性收益重构 13000 行源文件。

### 2.3.1 首帧防闪两道守卫（2026-10-30）

关键 CSS 只覆盖一部分分节，「完整样式表晚一点到」这个前提本身会制造两类频闪，
都在构建期 / 启动期加守卫消掉，新增组件不必再靠人肉记规则：

| 频闪 | 成因 | 守卫 |
| --- | --- | --- |
| **整页暗一下**（浮层闪现） | 全屏浮层（`position:fixed; inset:0`，靠 `opacity:0`/`visibility:hidden` 默认隐藏）写在非关键分节 → 首帧没有该规则，按默认 `opacity:1` 整块画出，完整表到位才过渡到隐藏 | `build/build.mjs` 的 `overlayGuardCss()`：构建时扫描整份 `ecod.css`，把这类规则自动补进关键 CSS（已在关键 CSS 中的按选择器去重） |
| **文字/色块渐变一下**（值翻转） | 关键 CSS 与完整表在链接色、按钮底色、输入框边框等取值不同，完整表换入时这些属性变一次；此时 `transition` 已生效 → 逐条渐变 | `<html class="ecod-booting">`（`header.php`）+ `html.ecod-booting * { transition:none !important }`（`ecod.css` §1）；`ecod.global.js` 在 `ecod:cssready` 后**双 rAF** 移除（确保完整样式已绘制、值落到终态），`include.php` 另有 1.8s 内联兜底 |

历史踩坑：首页搜索面板（`.ecod_spanel`，原写在 §8 文章）、文章/独立页图片灯箱
（`.ecod_lightbox`，未纳入关键 CSS 抽取清单）都因第一条各闪一次。
**新增全屏浮层不必改分节号**，但浮层的隐藏态必须能被 `overlayGuardCss()` 识别：
`position: fixed` + `inset: 0` + `opacity: 0` 或 `visibility: hidden` 三者齐全。


### 2.4 静态资源

- 头像池 27 张 1000px PNG → 320px WebP（2.7MB → 216KB，-92%），
  单张约 5KB，`ecod_local_avatar_url()` 的 glob 随之更新，排序序列不变；
- `.htaccess`（Apache）与 README nginx 示例：静态资源一年期 `immutable`、
  `gzip_static` / `brotli_static` 直吐预压缩文件；
- dev 文档（设计稿/审计报告）收进 `docs/dev/`，`.htaccess` 屏蔽，nginx 需手工 deny。

### 2.5 版权与注释

- 根目录 `LICENSE`（专有授权声明：付费主题，仅授权购买者使用）；
- 全部源文件统一文件头：职责描述 + `@package` / `@author` / `@copyright`（All Rights Reserved）/ `@link`；
  minify 产物带版权横幅，压缩不抹署名；
- 函数级注释补齐（pay-channel 三个通道类、工具函数、令牌助手等）。

### 2.6 JustNews 对标批次（2026-10-30）

对照 JustNews 演示站补齐的社区与内容模块，全部复用既有基建（api.php 入口模式、
blocks 声明体系、EcodPostQuery、特色页蓝图、指纹点赞表模式）：

| 模块 | 落点 |
| --- | --- |
| 前端投稿 | `page-contribute.php` + api `contribute_save/delete/list`（状态 waiting = 后台待审核；防刷与友链申请同构）；个人中心新增「我的文章」Tab（服务端直出，待审核/草稿可改可撤） |
| 快讯独立页 | `flash.php`（日期分组时间流 + `?fp=` 分页；文章字段 `source` 出「查看来源」；页面字段 `flash_cat` 限定分类）；首页快讯模块未限定分类时「更多」指向快讯页 |
| 公开作者主页 | 归档页 `components/archive/author.php` 头卡；数据共用 `core/user/meta.php` 的 `ecod_user_card()`（头像/组别/简介/统计），简介存新表 `ecod_user_meta`，个人中心「一句话简介」维护 |
| 文末作者卡 | `components/post/authorbox.php`（post 区新块 `authorbox`，默认启用），同一套作者卡数据 |
| 可见面包屑 | 归档页（标签/作者/搜索/日期）补 `ecod_crumb`；文章页/独立页此前已有 |
| 侧栏热门 Tab 组 | aside 新块 `wtab`：热门/热评/最新/随机四版面服务端一次渲染，`Ecod.initWtabs` 纯显隐切换 |
| 微信扫码分享 | 互动条分享弹层内嵌二维码，`EcodQR.matrix` 本地生成，零外部请求 |
| 网址导航页 | `sites.php` + 设置项 `ESites`（空行分组、`丨` 分列）；与友链互不占用 |
| 标签归档页 | `page-tags.php`：全站标签墙，sqrt 字号梯度 |
| 评论点赞 | 新表 `ecod_clikes`（coid+指纹唯一，uid 补记）+ api `clike/cstate`；`threadedComments` 输出按钮（赞数一次查全），`Ecod.initClike` 批量回填点亮状态；设置项 `ECLike` |

schema v9 → v10（`ecod_user_meta` / `ecod_clikes` 两表，沿用首次访问自愈）。
新增独立页模板全部注册进「工具 → 特色页」一键创建蓝图（contribute/flash/sites/tags）。
新增 CSS 收在 ecod.css §28（编号分节刻意避开关键 CSS 提取清单——
分节区间延伸到下一个编号标记，追加无编号块会被并入 §27 的提取区间）。
注意：避开关键 CSS 清单只对普通板块成立；**全屏浮层（fixed+inset:0+默认
opacity:0/visibility:hidden）例外**——构建期 `overlayGuardCss()` 会把它补进
关键 CSS，否则首帧整块可见、随后淡出，整页闪一下（见 §2.3.1）。

### 2.7 JustNews 对标 · P2 批次（2026-10-30 同日追加）

| 模块 | 落点 |
| --- | --- |
| 关注作者（轻量版） | 新表 `ecod_follows`（uid+target 唯一，仅登录用户）+ `core/user/follow.php` + api `follow_toggle/state/list`；作者卡（head/box 共用）显示粉丝数与关注按钮（游客呈登录引导形态），个人中心「我的关注」可取关；`Ecod.initFollow` 就地更新。私信不在本期（无通知基建，刻意搁置） |
| 版权模板多套 | 设置项 `ECopyrightSet`（「名称丨文本」逐行）+ 文章自定义字段 `copyright` 按名选用，未命中回落版权模块通用说明 |
| 合作伙伴 Logo 墙 | home 区新块 `partners`（repeater：名称/链接/Logo），首字色块兜底，JustNews 同款首页模块 |

schema v10 → v11（`ecod_follows`）。仍搁置：Passkey / 短信登录（Typecho 生态无 SMS 绑定，
WebAuthn 投入产出弱）、私信（需通知与未读体系）。

### 2.8 既有能力对照说明

两处 JustNews 形态在更早批次已存在，本轮只做核对未重复建设：
首页正文流的分类 Tab（`components/home/list.php` catbar + `Ecod.initStream` 无刷新换流）、
任意页登录弹窗（`public/header.php` 的 `ecod_authmodal`）。

## 三、性能账本（2026-10-30 实测）

| 指标 | 优化前 | 优化后 |
| --- | --- | --- |
| CSS/JS 源码总量 | 1013 KB | min 后 644 KB（-38%） |
| 首页首屏 JS | 119 KB（min） | **64 KB**（拆包后，-46%）；传输 16.3 KB（brotli） |
| 文章页额外 JS | （含在上面） | 56.5 KB 按需（传输 ~14 KB） |
| 首屏 CSS 阻塞 | 312 KB 全包阻塞 | **0**（分族内联 92–118 KB + 异步族补齐） |
| 首屏 CSS 传输 | 53 KB gzip（整包，阻塞） | core 29 KB + 所属族 0–12 KB（gzip，异步不阻塞） |
| 关键 CSS 内联量 | 137 KB（一份通用，每页浪费约 60%） | 92–118 KB（分族，gzip 15–19 KB） |
| 头像池 | 2.7 MB | 216 KB（-92%） |
| 缓存 | 无策略 | 哈希文件名 + 一年期 immutable + 预压缩 |

## 四、后续可选项（均未实施，按收益排序）

1. **关键 CSS 再压**：分族内联已从 137KB 降到 92–118KB，剩余大头是 §3 导航
   （22.9KB）与 §7 侧栏（25KB）；再往下压需要把这两节本身拆成「首屏 / 抽屉」
   子块，属结构级改动且收益递减，暂不做；
2. **表情图与 smiley 转 WebP**（assets/img/smiley/ 124KB，量小优先级低）；
3. **PWA 预缓存**：manifest.php 已就绪，可加 Service Worker 预缓存 dist 资源；
4. **ecod.global.js 进一步拆「列表页 + 内容页 + 全站」三档**：当前两档已覆盖
   主要收益，三档的边界维护成本开始大于收益。

---

*维护提示：改 JS/CSS 源码后记得 `cd build && npm run build`；不构建也不会坏，
只是浏览器拿不到压缩产物。*
