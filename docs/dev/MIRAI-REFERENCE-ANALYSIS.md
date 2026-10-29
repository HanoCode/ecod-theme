# Mirai / Sukuy 参考站拆解 · 可集成组件与布局优化

> 参考对象：
> 1. **https://mirai.sukuy.com/** — Mirai 主题官方演示站（Typecho，同生态竞品）
> 2. **https://www.sukuy.com/** — 同主题的运营站（作者本人内容站）
>
> 拆解方式：抓取两站首页 / 文章页 HTML，提取 DOM 骨架 + 解析 `mirai.css`(101KB) / `hero.css`(11KB) / `modal.css`(11KB) / `mirai-skeleton.css`(79KB) 的实际选择器与参数，再与本项目 `components/`、`public/`、`core/` 逐项对照。
> 日期：2026-10-29

---

## 一、两个站说明的是同一件事：组件与布局解耦

| | mirai.sukuy.com | www.sukuy.com |
|---|---|---|
| 首屏 | 全屏 hero（swiper + 玻璃搜索 + 4 悬浮卡） | **无 hero**，直接进列表 |
| 列表 | 4 列网格 `data-grid="4"` | 单列宽卡 `.single-column` |
| 尾部 | 分类推荐网格 `gt-cms-grid` | 分类网格 + **友链墙** `gt-home-links-section` |
| 侧栏 | 6 块固定（user / 文章榜 ×2 / 标签 / 评论 / 时间线） | 3 块 |

**结论：值得学的不是"某个组件"，而是让同一套组件在 hero 开/关、网格/单列、排序 tab 之间自由组合的能力。**
本项目已有块系统（`core/blocks-def.php`），骨架比它更灵活，差距集中在**首屏整合度**与**小区块形态**。

---

## 二、优先级总表

| # | Mirai 组件 | 落在哪 | Ecod 现状 | 优先级 |
|---|---|---|---|---|
| 1 | 排序 Tab（最新/浏览/点赞/评论） | 首页列表 | 只有**分类** tab | **P1** |
| 2 | 分类推荐卡（封面+描述+篇数+该分类最近文章） | 首页 | catnav 与 cat-post 是两个独立块 | **P1** |
| 3 | TOC 阅读进度条 + 当前章节高亮 + 移动抽屉 | 文章页 | 只有浮动弹窗，无进度 | **P1** |
| 4 | 搜索面板（热词 + 热门标签 + ⌘K） | 全局 | nav 内联框 + drawer 内表单 | **P1** |
| 5 | Hero 沉浸首屏（swiper+搜索+热搜+数据卡一体） | 首页 | banner / search / slider 三块独立 | **P1** |
| 6 | 登录弹窗 tab 化 + OAuth 入口 | 全局 | 仅账号密码+验证码，注册/找回跳页 | P2 |
| 7 | 相关文章网格变体 | 文章页 | 已有列表式（带同标签/分类标注） | P2 |
| 8 | 分享面板 + 海报生成 | 文章页 | details 弹层，无海报 | P2 |
| 9 | 页脚二维码组 | 页脚 | 无 | P2 |
| 10 | 导航二级面板（图标 + 描述） | 导航 | details 纯文字二级 | P2 |
| 11 | 广告位（多图轮播 + "广告"标识 + 列表内插入） | 全站 | ad 块单图 | P2 |
| 12 | 图片交付（fetchpriority / sizes / noscript 回退） | 全站 | 已有真实尺寸占位 | P2 |
| 13 | 列表布局前台切换（列表 ↔ 4 列） | 首页 | 只能后台选 | P3 |
| 14 | 骨架屏体系（79KB CSS） | 全站 | 无（用真实尺寸防 CLS） | P3 |

---

## 三、P1 详情（可直接动工）

### 1. 排序 Tab
- **Mirai**：`gt-article-tabs-header` 四个按钮 —— 最新发布 / 最多浏览 / 最多点赞 / 最多评论，`data-page-size="16"`，AJAX 原地替换列表。
- **Ecod 现状**：`components/home/list.php` 的 `ecod_tabs` 是**分类切换**（最新 + 前 6 个顶级分类 + 更多）。
- **关键**：`components/postquery.php` 已支持 `order=views`（阅读量）与 `order=hot`（`commentsNum` 降序）。
  → 前三项**零 SQL 改动**即可上线；「最多点赞」需点赞表 join 或给 contents 加计数字段，可后置。
- **落点**：`components/home/list.php`（新增 `data-ecod-sort` 按钮组）+ `assets/js/ecod.global.js`（AJAX 或整页 `?sort=` 参数）。
- **建议形态**：分类 tab 与排序 tab 并存会拥挤 → 分类走左侧 tab，排序做成右侧 `<select>` 或分段控件（移动端折叠）。

### 2. 分类推荐卡
- **Mirai** `gt-cms-card` 一个卡内包含：分类封面图 + 浏览数角标 → 分类名 + 「更多文章」+ 分类描述 + `N 篇文章` → 该分类最近 4 篇（标题 / 日期 / 阅读数）。数据一次查完，是完整的"栏目单元"。
- **Ecod 现状**：`catnav`（图标 + 计数，无内容）与 `cat-post`（按分类输出文章）割裂。
- **落点**：新增 `components/home/catgrid.php` + `ecod_block_define('catgrid', …)`（`core/blocks-def.php`）。数据：`\Widget\Metas\Category\Rows` 取分类（含 `count`、`description`）+ `EcodPostQuery category=X pageSize=4`。封面图走现有 `ecod_option` 自定义字段或分类缩略图池。

### 3. TOC 阅读进度 + 高亮 + 移动抽屉
- **Mirai**：三处联动 —— 桌面 `gt-toc-aside`（独立右栏，sticky）底部 `gt-toc-progress-bar`；移动端 `gt-toc-mobile-fab`（悬浮按钮）+ `gt-toc-mobile-overlay` + `gt-toc-mobile-drawer`（含同一进度条）。
- **Ecod 现状**：`components/aside/toc.php` 仅输出空壳 `<div data-ecod-toc>`，`post.php:176` 有 `ecod_floattoc` 浮动弹窗；**无阅读进度、无当前章节高亮联动**（`ecod.css:2939` 注释明确侧栏 TOC 不做 sticky）。
- **落点**：
  - PHP：`components/aside/toc.php` 追加进度条骨架；`post.php` 浮动 TOC 内追加同一进度条。
  - CSS：`--ecod-progress` 变量驱动宽度，顶部 2px。
  - JS：IntersectionObserver 监听 `h2/h3` → 高亮 + `scrollTop / (scrollHeight - innerHeight)` 驱动进度。
- **价值判断**：这是**阅读体验硬差距**，比任何视觉改造收益都高。

### 4. 搜索面板
- **Mirai**：`gt-search-modal`（overlay + panel）→ 表单 + `gt-search-tags`（热门标签胶囊），导航栏 `search-btn` 唤起。
- **Ecod 现状**：nav 内联 `<form class="ecod_nav__search">` + `ecod_drawer__search`（抽屉里顺带一个输入框），**没有独立搜索面板，也没有热词/标签入口**。
- **落点**：复用现有 `ecod_drawer` 的 mask / 焦点管理（这套已比 Mirai 严谨），新增 `data-ecod-search-panel` 变体：搜索框 + 热搜词（`EHotWords` 已有）+ 热门标签云（`\Widget\Metas\Tag\Rows` 按 count 排序取前 12）+ 最近浏览（`localStorage`，`ecod_favs` 已有类似存储可直接复用）。
- **顺带**：`⌘K / Ctrl+K` 唤起 —— 现无任何快捷键（`ecod.global.js` 未匹配 `metaKey`）。

### 5. Hero 沉浸首屏
- **Mirai** `hero.css` 实际参数（可直接抄）：

| 项 | 值 |
|---|---|
| 高度 | PC `calc(550px + header)`，移动 `calc(280px + header)` |
| 遮罩 | `linear-gradient(180deg, .35 → .08(40%) → .06(60%) → .42)` |
| 底雾 `hero-fog` | 3 层 radial-gradient 白色椭圆 + `blur(12px)`，移动降为 180px/`blur(6px)` |
| 玻璃卡 | `rgba(255,255,255,.85)` + `backdrop-filter: blur(12px) saturate(180%)`，`margin-top:-100px` 压在 banner 上 |
| 搜索框 | 圆角 56px，内边距 `5px 5px 5px 22px`，按钮 44×44 圆形主色 |
| 热搜词 | 13px / `5px 16px` / 半透明白底 + 1px 白边 / 圆角 20px |
| 移动卡片 | `flex: 1 1 calc(50% - 5px)` → 2×2 |
| header 联动 | `body:has(.hero-container) header { background: transparent }` + 导航字变白 |

- **Ecod 现状**：`banner`（单图 + h1 + 副标题 + 波浪）与 `search` 块（大搜索盒 + 热搜 + 站点数据）、`slider` 块各自独立，**首屏被拆成三段，视觉冲击力弱于整屏**。
- **落点**：新增 home 块 `hero`（`components/home/hero.php`）＝ slider + 标题 + 搜索 + 热词 + 3~4 张数据卡。**不引入 Swiper**，复用现有 `slider` 的切换逻辑；header 透明态用 `data-ecod-hero="1"` 挂 `<body>` + CSS 属性选择器，比 `:has()` 兼容性好。
- **注意**：`ecod_banner` 现有 wave 装饰与 fog 雾化二选一，不要叠加。

---

## 四、P2 详情（机制说明 + 成本）

| # | 组件 | Mirai 做法 | Ecod 落点 | 成本 |
|---|---|---|---|---|
| 6 | 登录弹窗 | `login-tab-header` 三 tab（登录/注册/找回）+ `login-oauth-buttons` + 左侧配图 + 短信验证码 | `public/header.php:287` 的 `ecod_authmodal` → 加 tab 容器；`core/oauth.php` 已支持 GitHub，只在弹窗里没入口 | 中 |
| 7 | 相关文章网格 | `gt-related-grid` + `gt-related-card`（缩略图 + 标题，3×2） | `components/post/related.php` 加 `layout=grid` 参数 | 低 |
| 8 | 分享海报 | `share-options` 6 项，含 `icon-poster` 生成整图 | `components/post/act.php` 的 `ecod_share__pop` 加一项；canvas 拼装可复用 `assets/vendor/ecod-qr.js` | 中 |
| 9 | 页脚二维码 | `gt-footer-qrcodes` 双码（公众号 / 客服） | `components/footer/credits.php` 或新增块 | 低 |
| 10 | 导航二级面板 | `nav-dropdown-menu` + `nav-dropdown-item`（图标 + 标题 + 描述，分组标题） | `core/nav.php` items 增加 `icon`/`desc` 字段；`public/header.php:150` 二级渲染改面板 | 中 |
| 11 | 广告位 | `gt-ad-slot` + `gt-ad-images`（多图轮播）+ `gt-ad-badge`（"广告"角标）+ `gt-ad-layout-content/sidebar` | `components/home/ad.php`、`components/aside/ad.php` 扩参数：多图、标识、插入第 N 条 | 中 |
| 12 | 图片交付 | 每图 `width/height` + `sizes` + 前 4 张 `fetchpriority="high"` + `<noscript>` 回退 + `onerror` 兜底 | `components/card.php` / `components/media.php` 统一补 | 低 |

### 12 的补充说明
Ecod 已经解决了 Mirai 最在意的 CLS（真实尺寸占位），但缺三项：**首屏图优先级提示**（`fetchpriority`）、**响应式 `sizes`**（减少移动端下载量）、**`<noscript>` 回退**（关 JS 时不出空图）。这三项是纯收益、无副作用。

---

## 五、不建议采纳

| 项 | 理由 |
|---|---|
| **Swiper + swiper.min.css** | Ecod slider 自研且已适配 PJAX；引入 Swiper（~140KB）换不来功能增量，反而多一套生命周期要跟 PJAX 对齐 |
| **RemixIcon 4.9.1 字体图标整包** | 字体包加载即下全部字形；Ecod 用 sprite（`components/icons.php`）按需内联，明显更优 |
| **骨架屏全套（79KB CSS）** | Ecod 用真实尺寸 + 渐变占位已消除 CLS；骨架屏收益仅剩"首屏即时感"，成本却要覆盖 6 种卡片模式 |
| **公告强制弹层** | 首访打断阅读。Ecod 的顶部横幅 + 关闭记忆（`ecod_notice_hash` 随内容变化）是更克制的做法，保留现状 |
| **积分 / 刮刮卡 / 下载商城** | 与博客定位不符，且 Ecod paywall 已覆盖变现需求 |

---

## 六、可迁移的设计参数（不涉及结构，纯样式）

1. **玻璃拟态三档**：卡片 `blur(12px) saturate(180%)`、导航下拉 `blur(24px) saturate(200%)`、搜索框 `blur(20px) saturate(180%)`，底色统一 `color-mix(in srgb, var(--card-bg) 75%, transparent)`（暗色降到 70%）。
2. **进场动画阶梯**：侧栏块 `animation-delay` 从 `0.08s` 起，每块 +`0.08s`（Mirai `style="animation-delay: .16s"`），比统一延迟更有节奏。
3. **标题字阶 `sky-h1~h6`**：本质是 6 级语义标题类 → Ecod 已有字体令牌，只需确认卡片标题与侧栏小标题用的是语义类而非裸 `font-size`（对照 `UI-AUDIT-REPORT.md` 3.1 的"字号体系断层"是否已闭环）。
4. **序号徽章**：`gt-aside-article-rank` 前三名单独配色 —— Ecod `aside/rank.php` 已有名次徽章，可核对前三名是否做了差异化配色。

---

## 七、建议执行顺序

1. **第 1 轮（体验硬伤）**：#3 TOC 进度与高亮 → #4 搜索面板（含 ⌘K）
2. **第 2 轮（首页信息架构）**：#5 Hero 块 → #1 排序 Tab → #2 分类推荐卡
3. **第 3 轮（细节补齐）**：#12 图片交付 → #9 页脚二维码 → #7 相关网格
4. **第 4 轮（可选）**：#6 登录 tab 化 / #10 导航面板 / #11 广告位

每轮遵守项目既有纪律：先补 `design-proposal.html` 对应章节（真源）→ 再改 PHP/SCSS → build → self-check。
