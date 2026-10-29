# Ecod Theme · 架构说明与演进路线

> 本文是 2026-10-30 架构梳理的落点：先讲清现状的分层与约束，再给出已落地的改进
> （构建管线 / 文档归位 / 版权规范），最后是下一步可选的演进方向与理由。

---

## 一、分层现状

```
┌─ 入口层（Typecho 约定，必须在主题根目录）
│   模板壳：index / archive / post / page / page-* / friends…（26 个）
│   独立入口：api.php / media.php / pay-admin.php / manifest.php / widget.php
│   引导：functions.php（钩子注册 + require 清单 + 视图工具函数）
│
├─ 领域层 core/（25 个文件，一个文件一个职责）
│   内容：shortcode / shortcode-extra / oembed / thumbs / related / search / ai
│   站点：seo / nav / sort / catstats / topics / meta-meta / go / pages / friends / mail
│   用户：account / oauth
│   交易：pay / pay-channel
│   后台：config / snapshot / blocks / blocks-def
│   基建：asset（构建产物解析）/ schema（表结构自愈）/ data/emoji（纯数据）
│
├─ 视图层 components/（卡片、页面头、首页模块、侧栏挂件、页脚块、专题组件）
│   布局壳 public/（header / footer / aside / include）
│
└─ 静态资源 assets/
    源码：css/ecod.css、js/ecod.global.js、admin/*（构建前的真身）
    产物：dist/*（构建生成，哈希文件名 + manifest.php）
    第三方：vendor/prism（MIT，不二次加工）、vendor/ecod-qr（自研）
```

**关键约束：根目录模板动不了。** Typecho 按文件名发现模板（`index.php` 必需，
`post.php` / `page.php` / `archive.php` 按命名约定解析，自定义页面模板扫描主题根目录
`*.php` 的 docblock）。所以「目录架构化」的正确姿势不是把模板挪进子目录，而是：

- 根目录只留「壳」——模板里不写业务，只组装组件（现状已基本如此，index.php 41 行）；
- 业务下沉 `core/`，视图下沉 `components/`，壳与逻辑分离；
- 独立入口共用基建（cookie 前缀校正、ecod_option 最小实现、asset 解析）。

## 二、本次落地的改进

### 2.1 前端构建管线（`build/`）

| 项 | 说明 |
| --- | --- |
| 工具 | esbuild 单依赖（`build/package.json`），`npm run build` 一条命令 |
| 策略 | **逐文件 minify，1:1 映射**——不合并、不改调用方式，PHP 调用点零改动 |
| 产物 | `assets/dist/<name>.<hash8>.min.<ext>`，内容哈希命名，内容变 → URL 变 |
| 清单 | `assets/dist/manifest.php`（PHP 数组，opcache 友好），源路径 → 产物路径 |
| 接入 | `core/asset.php` 唯一查询入口；`ecod_asset()` / `widget.php` / `admin/editor.php` 三处统一走它，**命中发哈希文件，未构建自动回退源文件 + `?v=filemtime`** |
| 缓存 | `.htaccess`（Apache）+ README nginx 示例：静态资源一年期 `immutable` |
| 分发 | dist 与 manifest 提交进 git，服务器与用户都不需要 Node |

实测（2026-10-30，17 个文件）：**1012.9 KB → 623.4 KB（-38.5%）**，
前台主包 `ecod.css` -23.4%、`ecod.global.js` -50.4%，后台 JS 均在 -40% 上下。
叠加 gzip 后前台主包传输体积约 55–60 KB。

**收敛了三套版本号逻辑**：此前 `ecod_asset()`（filemtime）、`widget.php`（内联 filemtime）、
`admin/editor.php`（内联 filemtime）各自为政；现在统一由 `core/asset.php` 解析。

### 2.2 目录清理

- 6 个开发文档（design-proposal.html 204KB、REFERENCE-TEARDOWN.html 116KB 等）
  从主题根目录移入 `docs/dev/`——它们不是模板，混在 26 个 Typecho 模板旁边
  既干扰阅读也是多余的公开暴露面；
- 代码内的规格引用同步改为新路径；
- `.htaccess` 屏蔽规则同步更新（nginx 场景记得在 server 块 deny `docs/dev`）。

### 2.3 版权与注释规范

- 根目录新增 `LICENSE`（MIT，含中文摘要）；README「许可」一节同步改写；
- 全部 133 个源文件（PHP/JS/CSS，vendor 的 prism 除外）统一文件头：
  职责描述 + `@package` / `@author` / `@copyright` / `@license`；
- 补齐了此前完全裸奔的 2 个文件与 20 余处无注释函数
  （pay-channel 三个通道类的方法、functions.php 工具函数、widget 令牌助手等）；
- 构建产物统一带版权横幅（`/*! Ecod Theme (c) 2026 Ecod · MIT License */`），
  minify 不会抹掉署名。

## 三、性能现状与下一步路线

已具备：minify + 内容哈希 + immutable 长缓存 + filemtime 版本回退、defer 加载、
PJAX、首帧主题脚本（防深色闪烁）内联、主色变量内联、prism 按需加载、图片 lazy。

按收益排序的候选演进（均为增量改动，可独立做）：

1. **JS 按页拆包**（最大头）：`ecod.global.js` 里文章页专属逻辑
   （TTS、代码块增强、评论表情、打赏/付费墙交互）约占三分之一，
   拆成 `ecod.post.js` 仅在 `post.php` / `page.php` 输出，
   首页/列表页首屏 JS 再省约 40 KB（min 后 ~20 KB）。
   做法：esbuild 多入口 + manifest 加 `pages` 字段 + 模板按 `$this->is()` 选择性输出。
2. **关键 CSS 内联**：把设计令牌 + 布局骨架（~5 KB）内联进 `<head>`，
   `ecod.css` 转 `media="print" onload` 异步，消除首屏渲染阻塞。
   维护成本中等，建议在拆包之后做。
3. **头像池转 WebP/AVIF**：`assets/avatar/` 2.7 MB（27 张 PNG），
   转 WebP 预计 -60% 以上；保留 PNG 回退或直接全量替换（现代浏览器无兼容问题）。
4. **precompress 静态产物**：构建时顺手产出 `.gz`（以及服务器支持时的 `.br`），
   nginx `gzip_static on;` 直接吐预压缩文件，省在线压缩 CPU。
5. **CSS 分层治理**：`ecod.css` 8800+ 行单文件，可按 §0-§13 切成源码多文件、
   构建时合并——源码可维护性与产物体积兼得，运行时无变化。

## 四、后续目录演进（可选，未实施）

当前 `core/` 25 个文件平铺尚可接受（单一职责、文件头清晰、require 路径全部硬编码，
移动收益纯属组织性、风险却是全局的）。若文件数继续增长，建议按以下分组一次性迁移：

```
core/
├── content/   shortcode*、oembed、thumbs、related、search、ai
├── site/      seo、nav、sort、catstats、topics、meta-meta、go、pages、friends、mail
├── user/      account、oauth
├── pay/       pay、pay-channel
├── admin/     config、snapshot、blocks、blocks-def
└── lib/       asset、schema、data/
```

迁移时同步改动点：`functions.php` 与 `api.php` 的 require 清单、各文件互相
`require_once __DIR__ . '/…'` 的路径、`.htaccess` 不受影响（只拦根目录 PHP 直连）。
建议放在一个大版本里做，配合 `php -l` 全量校验 + 前台四页回归。

---

*维护提示：本文与 README「目录结构」「前端构建」两节保持同步更新。*
