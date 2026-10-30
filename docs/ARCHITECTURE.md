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
│   user/     账户：account、oauth
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

### 2.3 关键 CSS 内联（首屏不被 CSS 阻塞）

- 构建时从 `ecod.css` 抽出 §0 令牌 / §1 基础 / §2 布局 / §3 导航抽屉 / §12 暗色
  （`CRITICAL_SECTIONS` 数组，想调覆盖面改这一处），独立 minify 出
  `ecod.critical.<hash>.min.css`（17.5KB，gzip 约 4.3KB）；
- `include.php` 把它内联进 `<head>`，完整样式表转
  `<link rel="preload" as="style" onload="…rel='stylesheet'">` 异步预加载
  （`<noscript>` 兜底）；
- 未构建时回退整包阻塞加载（原行为）。内联块位于 brand 之前，主色覆盖顺序不变。

> 设计取舍：CSS 源码保持单文件不拆 `src/`——分节标记已足够构建期切分，
> 避免为组织性收益重构 9000 行源文件。

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

## 三、性能账本（2026-10-30 实测）

| 指标 | 优化前 | 优化后 |
| --- | --- | --- |
| CSS/JS 源码总量 | 1013 KB | min 后 644 KB（-38%） |
| 首页首屏 JS | 119 KB（min） | **64 KB**（拆包后，-46%）；传输 16.3 KB（brotli） |
| 文章页额外 JS | （含在上面） | 56.5 KB 按需（传输 ~14 KB） |
| 首屏 CSS 阻塞 | 312 KB 全包 | 0（17.5 KB 内联 + 异步补全） |
| 头像池 | 2.7 MB | 216 KB（-92%） |
| 缓存 | 无策略 | 哈希文件名 + 一年期 immutable + 预压缩 |

## 四、后续可选项（均未实施，按收益排序）

1. **按页变体关键 CSS**：home/post/archive 各配一份 critical（当前一份通用），
   内联体积可再省 30%，维护成本增加，建议流量上来再做；
2. **表情图与 smiley 转 WebP**（assets/img/smiley/ 124KB，量小优先级低）；
3. **PWA 预缓存**：manifest.php 已就绪，可加 Service Worker 预缓存 dist 资源；
4. **ecod.global.js 进一步拆「列表页 + 内容页 + 全站」三档**：当前两档已覆盖
   主要收益，三档的边界维护成本开始大于收益。

---

*维护提示：改 JS/CSS 源码后记得 `cd build && npm run build`；不构建也不会坏，
只是浏览器拿不到压缩产物。*
