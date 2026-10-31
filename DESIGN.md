---
version: alpha
name: Ecod Theme
description: 中文内容型博客/社区主题 · 卡片化信息流 · 靠描边不靠阴影 · 4pt 网格 · 克制动效
colors:
  primary: "#4f6ef2"
  primary-deep: "#3d5be0"
  primary-btn: "#4c6ae8"
  primary-soft: "#eef1fe"
  primary-ink: "#ffffff"
  primary-text: "{colors.primary}"
  accent: "#f59e0b"
  success: "#10b981"
  danger: "#ef4444"
  bg: "#f5f6f9"
  surface: "#ffffff"
  surface-2: "#fafbfc"
  line: "#eef0f5"
  line-2: "#e4e7ee"
  line-strong: "#d3d9e4"
  ink: "#12161d"
  ink-2: "#5b6472"
  ink-3: "#6b7280"
typography:
  body:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif"
    fontSize: 16px
    fontWeight: 400
  caption-xs:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif"
    fontSize: 11px
  caption-sm:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif"
    fontSize: 12px
  label:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif"
    fontSize: 13px
    fontWeight: 500
  list-title:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif"
    fontSize: 14px
    fontWeight: 600
  h3:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif"
    fontSize: 18px
    fontWeight: 700
  h2:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif"
    fontSize: 22px
    fontWeight: 700
  h1:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif"
    fontSize: 27px
    fontWeight: 700
    lineHeight: 1.4
  display:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif"
    fontSize: 36px
    fontWeight: 700
  mono:
    fontFamily: "ui-monospace, SFMono-Regular, Menlo, Consolas, monospace"
    fontSize: 13px
rounded:
  xs: 4px
  sm: 8px
  md: 12px
  full: 999px
spacing:
  1: 4px
  2: 8px
  3: 12px
  4: 16px
  5: 20px
  6: 24px
  7: 28px
  8: 32px
  10: 40px
  12: 48px
components:
  button-primary:
    backgroundColor: "{colors.primary-btn}"
    textColor: "{colors.primary-ink}"
    rounded: "{rounded.sm}"
    padding: 8px 16px
    typography: "{typography.label}"
  button-primary-hover:
    backgroundColor: "{colors.primary-deep}"
  button-primary-active:
    backgroundColor: "{colors.primary-deep}"
  button-soft:
    backgroundColor: "{colors.primary-soft}"
    textColor: "{colors.primary-deep}"
    rounded: "{rounded.sm}"
    padding: 8px 16px
  button-ghost:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink-2}"
    rounded: "{rounded.sm}"
    padding: 8px 16px
  card:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    padding: 24px
  card-hover:
    backgroundColor: "{colors.surface}"
  input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.sm}"
    padding: 8px 12px
  popover:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.md}"
    padding: 16px
  chip:
    backgroundColor: "{colors.surface-2}"
    textColor: "{colors.ink-2}"
    rounded: "{rounded.full}"
    padding: 2px 8px
    typography: "{typography.caption-sm}"
---

# Ecod Theme · Design System

## Overview

Ecod 是一个内容优先的中文博客/社区主题（Typecho），设计语言为「轻卡片 + 描边分层」：

- **靠描边不靠阴影**：静置卡面用极浅描边（`line`）划分层级，阴影只出现在悬停与浮层两处。整体安静、干净，让正文与媒体成为主角。
- **4pt 网格**：所有间距来自 4 的倍数刻度（见 Layout），禁止 5/7/9/10/18/22 等离格值。
- **克制动效**：140–320ms 四档时长 + 两条缓动曲线，入场用 ease-out，弹层才允许轻微 overshoot。
- **深浅双主题同构**：暗色不是补丁，而是同一套语义令牌的镜像覆盖（见 Colors）。
- 信息密度偏高：卡片摘要、元信息、列表都采用 12–14px 小字号 + 三档墨色区分主次。

## Colors

- `primary`（#4f6ef2 靛蓝）是唯一交互驱动色：链接、按钮、激活态、焦点环。
- `ink / ink-2 / ink-3` 三档墨色构成文本层级：标题正文 / 次要说明 / 元信息标注。
- `line / line-2 / line-strong` 三档描边：卡面默认（极浅）/ 卡内分隔 / 悬停加深。
- `bg / surface / surface-2` 三档底色：页面底 / 卡面 / 卡内次级面。
- `accent / success / danger` 仅用于状态语义（角标、徽标、错误提示），不做装饰。
- 暗色主题（`html[data-theme="dark"]`）覆盖同名令牌：bg #14161d、surface #1d2029、ink #e6e9f0 等；主色不变，但描边混合基色改用 #1a1e28。新增组件必须走令牌，禁止在暗色区新写裸 hex。

## Typography

- 系统字体栈（含 PingFang SC / Microsoft YaHei），不引入 webfont；等宽场景用 `mono`。
- 字号 11 → 36 共 11 级（`fs-2xs … fs-5xl`），**禁止裸 px 字号**；`lg` 与 `base` 同为 16px，靠字重区分。
- 层级约定：11–12px 元信息 / 13px 按钮与 chips / 14px 列表标题 / 16px 正文 / 18–22px 小节 / 27px 文章主标题 / 36px 展示数字。

## Layout & Spacing

- 内容容器 1200px，侧栏 300px，栅格间距 24px，吸顶导航高 62px。
- 间距刻度 `spacing.1–12`（4→48px），语义：4 同行元素 / 8 紧凑列表 / 12 表单与列表项 / 16 模块间小节 / 24 卡内边距 regular / 32–48 大区块。
- 响应式断点 680 / 861 / 900 / 1200（无法变量化的断点值，含义见各 `@media` 注释）。
- z-index 十一档刻度（25→9999）：卡内弹层 < 导航下拉 < 浮动件 < 吸顶头 < 抽屉 < 进度条 < 灯箱 < 顶层。

## Elevation

- `rest`：无阴影（`shadow: none`），层级靠描边。
- `card-hover`：单层抬升 `0 8px 28px rgba(18,22,29,.10)`。
- `pop`：浮层唯一明显阴影（下拉/弹窗/灯箱）。
- 键盘焦点统一用 `ring`：`0 0 0 3px rgba(79,110,242,.12)` 外圈。
- 图片/深色底上的白字用 `text-shadow` 压暗两档，不用描边。

## Shapes

- 圆角四档：xs 4px（微元素）/ sm 8px（按钮、输入框）/ md 12px（一切面级容器的默认）/ full 999px（胶囊）。
- 无异形圆角；卡片统一 md，嵌套内层最多降一级到 sm。

## Components

- **button-primary**：主色底 + 白字，hover/active 加深为 primary-deep；按压反馈 `scale(.97)` 或 `translateY(1px)`，140–160ms。
- **主色可访问性派生（运行时由 PHP 按品牌色计算，functions.php）**：
  - `primary-btn`：白字对比 <4.5 时自动向黑混色到刚好达标（默认靛蓝混黑 4% → #4c6ae8，白字 4.64:1）；已达标的品牌色原色返回。所有「主色底 + 白字」承载面（按钮/页码当前项/榜单序号/支付钮等 31 处）一律用它，不用裸 primary。
  - `primary-ink`：主色填充上的文字三档取首个 ≥4.5 者——白 → 深墨 #12161d（浅暖色）→ 纯黑（中明度主色）。
  - `primary-text`：软底上的主色系文字，自动加深至 ≥4.8（默认主色下 ≈ primary-deep，4.96:1）。
  - 静态 CSS 值仅为默认主色的兜底；换品牌色后由 `public/include.php` brand `<style>` 覆盖。
- **card**：白底 + `line` 描边 + md 圆角，静置无阴影；hover 才抬升。
- **input**：surface 底 + sm 圆角，聚焦时套 `ring` 外圈而非改边框色。
- **popover**：surface 底 + pop 阴影 + md 圆角；`transform-origin` 指向触发元素（`--ecod-ox/--ecod-oy`），模态框居中除外。
- **chip**：胶囊圆角 + surface-2 底 + 12px 字号。

## Do's and Don'ts

**Do**

- 间距/字号/圆角/阴影/层级一律引用令牌，禁止裸值。
- hover 态包在 `@media (hover: hover)` 内，防触屏误触发。
- 入场/退出用 `--ecod-ease`（ease-out 语义），仅「弹出来」的元素用 `--ecod-ease-pop`；首次出现用 `--ecod-dur-enter`，勿用 fast 代入场。
- 动态 UI（toast、点赞、弹出）用 transition 而非 keyframes；keyframes 仅限循环指示（pulse/shimmer/ticker）。
- `prefers-reduced-motion` 下关停位移与循环动画，但保留颜色/透明度的柔和过渡。
- PJAX 只替换 `#ecod-shell`；常驻浮层（搜索面板/登录弹层）必须在 shell 外。

**Don't**

- 禁止 `transition: all`；必须显式列出属性。
- 禁止 ease-in 做入场；禁止 scale(0) 起始（从 ≥0.9 + opacity 开始）。
- 禁止 5/7/9/10/18/22px 间距；禁止裸 px 字号。
- 静置卡面禁止加阴影；浮层之外禁止明显阴影。
- 禁止在暗色覆盖区新写裸 hex；先沉淀令牌再引用。
- UI 动画时长禁止超过 320ms（大面积展开除外）。
