# Ecod 页面构建器 v6 · 自由行列布局设计

> 本文是 `docs/builder-design.html`（v5 设计）的**续篇与修正案**，只覆盖「布局自由度」这一条主线：
> 数据模型 v5 → v6（板块 → **行** → **列** → 模块），渲染层与编辑器的对应改造，以及分期落地。
> 状态：**P0 已实现（2026-11-01，schema v6 / norm / 渲染器 / cleaner / 快照表 v16 / 模板 v6）；
> P1 全量实现**——行列 Inspector、四层大纲树、结构预设库 + rownew 拖放、行工具条、
> cell-split 列边缘分裂、列宽拖拽手柄 + `--row-cols` 热更新（stageSync(noReload) 通道）、
> 列级卡面、**列内嵌行（深度 2：norm/clean 深度参数、render_cell 嵌套渲染、
> findPath 容器数组引用化→全操作面深度无关、列⇄嵌套行转换、大纲递归渲染）**。
> **P2 全部落地**：可复用行（我的行模板：rowlib.save/delete + 库分组 + 拖入/点击/同名覆盖）、
> 列宽均分（Inspector 按钮 + 双击手柄热复位）、行级 masonry 瀑布流（`masonry` 0|1，CSS columns 实验）、
> 模块「移动到…」级联选择（§6.5 键盘可达等价路径）、**模板中心（我的整页模板：
> doclib.save/delete + 模板菜单「存当前布局为模板 / 我的模板」一键套用，与 rowlib 同构）**；
> 预置模板套用与 JSON 导入导出此前已有。
> 回归 harness：① `php temp/test-v6-clean.php`（norm→clean→render 24 断言）；
> ② `node temp/test-builder-js.mjs`（**幻影 DOM 跑真实 builder.js**，16 断言：findPath 引用/Esc 链/
> 嵌套增删移/torows 回环/预设分配/深拷贝 uid/rownew 落点/undo/stage 通道）。改编辑器必跑②。
> 对旧决策的修订：§1.3 非目标（撤回「任意行列嵌套」禁令 → 受控嵌套）、§8.3（细化）；
> 维持不变：§8.1（单数据源 + ov 白名单，不做三设备树）、§8.2（原地升级）、§8.4（混合 chrome）、§8.5（整页替换）。

---

## 0 摘要（TL;DR）

现状 v5 的布局自由度被锁死在「板块 → 主体/侧栏二分 → 栏内线性堆叠」上：一个板块最多 2 栏、
栏宽只有固定像素、模块永远独占一行。想做「三栏不等宽」「两个卡片并排」「窄标题 + 宽内容」
这类最普通的版式都做不到——这就是「中间预览区域没法随意布局」的根因，它**不是画布 UI 的问题，
是数据模型没有行/列这层结构**。

v6 的解法：在板块与模块之间插入**行（row）与列（cell）**两层结构节点：

```
板块 section（宽度/内边距/背景/明暗 —— 不变）
 └─ 行 row（N 列横排：gap、对齐、断点折叠策略）
     └─ 列 cell（宽度 = 整数权重 fr 或固定 px；吸顶；断点隐藏/覆盖/换序）
         └─ 模块 module（叶子 —— 仍无 children，完全不变）
```

- **宽度**：列权重整数化（`minmax(0,2fr) minmax(0,1fr)`），可对个别列改固定 px（还原现有 320px 侧栏）；
- **响应式**：维持「单数据源 + 容器查询 + ov 白名单」，白名单从板块级扩展到行/列级；
- **编辑器**：拖放目标从「栏内插模块」扩展为「列内插模块 / 列边缘分裂新列 / 行间隙建行」，
  新增行工具条、列宽拖拽手柄、结构预设库、四层大纲树；
- **兼容**：v5 文档**读时规范化**为 v6（渲染视觉零变化），保存时升版为 v6；模块 54 个零改动；
- **纪律**：前台仍然零 JS、服务端渲染是唯一真源；结构变更重载画布，只有列宽拖拽走 CSS 变量热更新。

---

## 1 现状分析：为什么现在「不能随意布局」

### 1.1 现有架构（v5 已实现，2026-10-31）

| 层 | 文件 | 职责 |
|---|---|---|
| 入口 / API | `builder.php` | 管理员校验 + JSON API（doc.get / doc.save / stage.save / publish / area.save …） |
| 数据层 | `core/admin/builder-doc.php` | 布局文档清洗（`ecod_bld_clean_section` :66）、存储（ELayouts 行 + `ecod_page_layout` 表）、回退链、渲染器、快照、暂存 |
| 编辑器 | `assets/admin/builder.js`（~3500 行） | Store / Library / Canvas / Inspector / Outline / Saver 六组件；同源 iframe 直接接管交互 |
| 模块注册 | `core/admin/blocks-def.php` | 54 个模块的 params schema |
| 壳样式 | `assets/css/ecod.css` §42 | `.ecod-sec` / `__inner` / `__cols` / `__col--aside` + 900/680 媒体查询 |

画布 = iframe 加载带 `?ecod_builder=1` 的**真实前台页面**（真实数据、真实渲染），
builder.js 以捕获阶段监听直接操作 iframe 文档（点击选中 / 指针式拖放 / 键盘），
结构变更后 `scheduleReload()`（builder.js:246）重载 iframe 并恢复选中与滚动。

### 1.2 现有数据模型（v5）

```
sections[]                                    ← 板块，垂直堆叠
  ├─ box 语义（平铺在板块上）：width/contentMax/padY/bg/radius/tone
  ├─ ov（断点覆盖白名单：仅 padY/hide/tone）
  ├─ cols: 'stack' | 'left' | 'right'        ← 三选一，写死的二分栏
  ├─ asideW: 200–420px（固定像素） asideSticky
  ├─ main[]  ← 模块线性数组（≤20）
  └─ aside[] ← 模块线性数组（stack 时强制为空）
      module = {uid, type, on, params, hide{md,sm}}   ← 叶子
```

### 1.3 瓶颈清单（逐条对应代码）

| # | 瓶颈 | 位置 | 后果 |
|---|---|---|---|
| B1 | 栏模型只有 `stack/left/right` 三选一，**最多 2 栏** | `ecod_bld_clean_section` :73、`ecod_layout_render_section` :602 | 三栏、四栏、不等宽多栏完全做不出来 |
| B2 | 侧栏宽度只有固定 px（200–420），主栏永远 `1fr` | :103、ecod.css §42 `grid-template-columns: minmax(0,1fr) var(--sec-aside-w)` | 无法做 2:1、1:2、等分等比例版式 |
| B3 | 模块在栏内**只能纵向线性堆叠**，永远独占一行 | `ecod_layout_render_col` :665、`computeDrop` 落点只有 `beforeUid/idx`（builder.js:1074） | 「两张卡片并排」「图标 + 文字并排」等必须靠模块内部自带布局参数硬凑 |
| B4 | 无嵌套：模块无 children，栏内也不能再分栏 | 旧决策 §8.3 | 「左标题右内容的卡面」做不出来——板块级二分太重（带背景/内边距），粒度不对 |
| B5 | 断点覆盖只能改 padY/hide/tone，**不能改列数/宽度/顺序** | `ECOD_LAYOUT_OV_KEYS`（builder-doc.php:42） | 手机上只能整块隐藏，无法「侧栏折叠到下方但仍显示」以外的重排 |
| B6 | 编辑器拖放目标语义贫乏 | `computeDrop`（builder.js:972）只返回 `{kind:'col', secUid, col:'main'|'aside', idx}` | 没有「放到两列之间」「分裂列」「跨行移动」这些自由布局必需的落点 |
| B7 | 大纲树只有 板块→栏→模块 三层 | Outline 组件 | 结构稍复杂就失去可导航性 |

结论：**问题出在数据模型（B1–B5），画布 UI（B6–B7）是它的投影**。只改 UI 无法获得自由布局，
必须先给数据模型补上行/列结构，再让编辑器围绕新结构提供直接操作。

---

## 2 目标与非目标（对旧 §1.3 的修正案）

### 2.1 目标

1. **多列自由排列**：板块内任意行、每行 1–6 列、列宽按整数权重或固定 px 自由分配；
2. **模块并排**：通过「多列」表达，不再要求模块自带并排参数；
3. **受控嵌套**（P1）：列内可再嵌一行（深度 ≤ 2），覆盖「卡片内分栏」类版式；
4. **响应式重排**：行/列级 ov 白名单（断点隐藏 / 权重覆盖 / 折叠保持并排 / 顺序换位）；
5. **所见即所得**：列宽拖拽、行工具条、投放指示全部画在 iframe 内（沿用混合 chrome §8.4）；
6. **零破坏**：54 个模块零改动；v5 文档读时规范化后**渲染视觉零变化**；v4 兜底出口不变。

### 2.2 非目标（明确不做，沿用并收紧）

- **自由画布 / 绝对定位 / 任意像素坐标**：Elementor、瓜奇都不是这种模式，维护成本与收益完全不成比例；
- **无限深度容器树**：嵌套深度硬性 ≤ 2（见决策 v6.1）；
- **设备级三套数据树**：维持旧 §8.1，ov 白名单仍然是硬约束；
- **模块获得 children**：并排=多列，嵌套=列内嵌行，模块永远是叶子（维持 §8.3 并细化）；
- **画布内联改文**：内容仍归 Typecho 编辑器（维持 §5.2.1）。

---

## 3 范式对照与取舍（对旧 §9 的增补）

### 3.1 六家对照

| 构建器 | 布局模型 | 对 v6 的启示 | 取舍 |
|---|---|---|---|
| Elementor | Container（flex/grid）任意嵌套树；子元素 width%；每容器可拖宽；Navigator 面板 | 列宽拖拽手柄、投放到列边缘=分裂新列、Navigator ↔ 大纲树双向高亮 | 吸收交互，**拒绝**无限嵌套（复杂度随深度递归爆炸） |
| Gutenberg | `columns` 块（1–6 列、flex-grow 权重）+ `group` 块嵌套；`layout` 支持（contentSize/wideSize）；templateLock | 整数权重宽度模型（就是 Gutenberg 的 flex-grow 语义）、结构预设=Pattern、锁定/内容 Only 语义 | 吸收权重模型与锁定语义 |
| Beaver Builder | Row → Column（1/2、1/3、2/3…分数宽度）→ Module；数据模型十年几乎不变 | 印证「行/列/模块三段受控模型」是可长期维护的最稳形态 | 印证 |
| Bricks / Oxygen | 统一 Container 树 + CSS grid 可视化控件 | 列面板提供 grid 对齐/间距 token 化控件可借鉴 | 部分吸收（仅行级 gap/valign） |
| Divi | 列宽拖拽、Wireframe 视图、Extend Styles 批量改 | 列宽拖拽；wireframe 由大纲树承担 | 部分吸收 |
| 瓜奇 guaqi.com/builder | 板块堆叠 + 模块可视化放置；容器查询响应式；**三设备独立 UI**；模板中心/一键换装/组件分享；本地草稿+导入导出 | 印证板块堆叠骨架；其「构建器可以搭建出无数种布局」的底气正是板块内行/列自由；换装=模板 P2；三设备树**拒绝**（维护成本差一个数量级） | 部分吸收 |

### 3.2 与旧设计的关键分歧及理由

旧 §1.3 把「自由画布 / 任意行列嵌套」整体列为非目标，理由是「参考对象瓜奇也不是这种模式」。
本次修正为：**拒绝的是自由画布与无限嵌套，不是行列自由**。理由：

1. 现实需求的最小完备集就是「N 栏不等宽 + 模块并排 + 卡内分栏」，三者都只要行/列两层结构 + 深度 2 嵌套；
2. Elementor/Beaver 的事故经验表明，布局自由度的维护成本拐点在**嵌套深度**上，不在列数上——
   列数只是 `grid-template-columns` 一个字符串，深度才递归污染清洗器、渲染器、大纲树、拖放命中；
3. 瓜奇能在板块模式下宣称「无数种布局」，靠的同样是板块内多列自由，而非自由画布。

---

## 4 数据模型 v6

### 4.1 结构总览

```
文档 { v: 6, docs: { key: { sections[] } } }        ← 存储外壳不变
板块 section
  ├─ 身份/外观：uid,on,label,anchor,width,contentMax,padY,bg,bgDark,radius,tone   ← 不变
  ├─ ov{md,sm}: {padY,hide,tone}                     ← 不变
  └─ rows[]
      行 row
        ├─ uid,on,gap(0–96px),valign(start|center|stretch)
        ├─ ov{md,sm}: {keep,gap,hide}                ← 新增白名单
        └─ cells[]
            列 cell
              ├─ uid
              ├─ w: 1–12（整数权重）| wpx: 160–640（固定像素，二选一）
              ├─ sticky: 0|1
              ├─ ov{md,sm}: {hide,w,order}           ← 新增白名单
              └─ mods[] ← 模块实例，形状完全不变 {uid,type,on,params,hide}
```

### 4.2 字段字典

| 节点 | 字段 | 类型 | 默认 | 约束 / 说明 |
|---|---|---|---|---|
| section | （全部现有字段） | — | — | 不变；`cols/asideW/asideSticky/main/aside` **移除**（由 rows 取代） |
| row | `uid` | string | 自动 | `row-xxxxxxxx` |
| row | `on` | 0\|1 | 1 | 前台只渲染 on=1（画布例外，沿用现有规则） |
| row | `gap` | int | 24 | 列间距 px，0–96；预设 0/8/16/24/32/48 |
| row | `valign` | enum | `start` | start\|center\|stretch → `align-items` |
| row | `masonry` | 0\|1 | 0 | 行级瀑布流（P2 实验）：CSS columns 纵向填充、列优先阅读序；md 折 2 列 / sm 折 1 列，keep 可锁列数；瀑布流行不提供列宽拖拽手柄 |
| row | `ov` | object | {} | 白名单：`md/sm × {keep:0\|1, gap:int, hide:0\|1}`；`keep=1` 表示该断点不折叠、保持并排 |
| cell | `uid` | string | 自动 | `cell-xxxxxxxx` |
| cell | `w` | int | 1 | 1–12 整数权重（与 wpx 互斥） |
| cell | `wpx` | int | — | 160–640 固定像素（还原侧栏语义）；与 w 互斥，同时出现时 wpx 优先并丢弃 w |
| cell | `sticky` | 0\|1 | 0 | 吸顶（现有 `--sec-aside-w` 侧栏的对应物） |
| cell | `ov` | object | {} | 白名单：`md/sm × {hide:0\|1, w:int 1–12, order:-6..6}` |
| cell | `mods` | array | [] | 模块实例，复用 `ecod_bld_clean_rows()`（含 hide 白名单），≤ ECOD_WIDGET_LIMIT(20) |
| module | — | — | — | **零变化** |

### 4.3 宽度模型：整数权重（fr）+ 固定像素双模

- 权重渲染为 `grid-template-columns: minmax(0,2fr) minmax(0,1fr)`——权重只在**行内**相对比较，
  不存在百分比取整漂移，拖拽调宽时比例即时可见；
- 固定 px 只为**还原现有侧栏**（v5 的 asideW 语义）与「固定宽导航列」场景保留，编辑器默认引导用权重；
- 同一行内 px 列与 fr 列可混排（`minmax(0,3fr) 320px minmax(0,1fr)`，grid 原生支持）；
- 单列行 = `grid-template-columns: minmax(0,1fr)`，与今天的 `.ecod-sec__cols`（column flex）视觉等价。

### 4.4 响应式：三层模型不变，ov 白名单下沉

沿用旧 §9.3 的三层模型，只做白名单扩展：

1. **结构性层**：≤900px（md）行默认折叠为单列（`grid-template-columns: minmax(0,1fr)`），
   按 DOM 顺序纵排；≤680px（sm）沿用 md 折叠结果；
2. **声明性层**：
   - 行级 `ov.md.keep=1` → 平板保持并排（用户在画布设备段预览里自己看到挤压效果并承担责任）；
   - 列级 `ov.md.hide=1` → 平板隐藏该列；
   - 列级 `ov.sm.order=n` → 手机上调整列的显示顺序（折叠纵排后的 `order`）；
   - 列级 `ov.md.w=n` → 平板并排时的权重覆盖；
3. **拒绝层**：三设备独立数据树，维持旧 §8.1 结论。

服务端清洗对行/列的 ov 与板块级同等待遇：**白名单外的键一律丢弃**（§7.1）。

### 4.5 约束与上限

| 项 | 上限 | 说明 |
|---|---|---|
| 板块数/文档 | 30 | 不变（ECOD_LAYOUT_SECTIONS_MAX） |
| 行数/板块 | 12 | 新增（ECOD_LAYOUT_ROWS_MAX） |
| 列数/行 | 6 | 新增（ECOD_LAYOUT_CELLS_MAX），与 Gutenberg columns 上限一致 |
| 模块数/列 | 20 | 不变（ECOD_WIDGET_LIMIT 语义平移） |
| 嵌套深度 | 2 | P0 禁止嵌套（cell.mods 只有模块）；P1 允许 cell 内嵌 rows，嵌套行内**不得再嵌套** |
| 文档 JSON 软上限 | ~48KB | 超限时编辑器出黄条警告；服务端硬截断保护 options 行（现有 24KB 快照上限语义对应放大） |

### 4.6 JSON 完整示例

```json
{
  "v": 6,
  "docs": {
    "home": {
      "sections": [
        {
          "uid": "sec-hero", "on": 1, "label": "首屏", "anchor": "",
          "width": "full", "contentMax": 1200, "padY": 64,
          "bg": "", "bgDark": "", "radius": 0, "tone": "auto",
          "ov": { "sm": { "padY": 32 } },
          "rows": [
            {
              "uid": "row-hero", "on": 1, "gap": 32, "valign": "center",
              "ov": { "md": { "keep": 0 } },
              "cells": [
                { "uid": "cell-hero-a", "w": 2, "sticky": 0, "ov": { "sm": { "order": 1 } },
                  "mods": [ { "uid": "banner-1", "type": "banner", "on": 1, "params": {} } ] },
                { "uid": "cell-hero-b", "w": 1, "sticky": 0, "ov": { "sm": { "hide": 1 } },
                  "mods": [ { "uid": "hot-1", "type": "hot", "on": 1, "params": {} } ] }
              ]
            }
          ]
        },
        {
          "uid": "sec-main", "on": 1, "label": "主体", "anchor": "",
          "width": "boxed", "contentMax": 1200, "padY": 48,
          "bg": "", "bgDark": "", "radius": 16, "tone": "auto",
          "ov": {},
          "rows": [
            {
              "uid": "row-main", "on": 1, "gap": 24, "valign": "start",
              "ov": {},
              "cells": [
                { "uid": "cell-main", "w": 3, "sticky": 0, "ov": {},
                  "mods": [ { "uid": "feed-1", "type": "feed", "on": 1, "params": {} } ] },
                { "uid": "cell-side", "wpx": 320, "sticky": 1, "ov": {},
                  "mods": [ { "uid": "title-list-1", "type": "title-list", "on": 1, "params": {} } ] }
              ]
            }
          ]
        }
      ]
    }
  }
}
```

### 4.7 版本与迁移：读时规范化，写时升版

- 常量 `ECOD_LAYOUT_SCHEMA` 5 → **6**；
- 新增 `ecod_bld_norm_doc(array $doc): array`：接受 v5 或 v6 输入，输出**规范 v6**。挂在所有读路径上
  （`ecod_layout_doc_for_key`、`ecod_layout_stage_get`、`ecod_layout_snap_get`、渲染入口），保存路径同样先 norm 再 clean；
- **v5 → v6 映射表**：

| v5 | v6 |
|---|---|
| `cols:'stack'` | `rows=[{gap:24,valign:'start',cells:[{w:1,mods:main}]}]` |
| `cols:'right'` | 同上，cells=`[{w:1,mods:main},{wpx:asideW,sticky:asideSticky,mods:aside}]` |
| `cols:'left'` | cells=`[{wpx:asideW,sticky:asideSticky,mods:aside},{w:1,mods:main}]`（aside 在前，DOM 顺序即视觉顺序，弃用 `order:-1` hack） |
| 其余字段 | 原样保留 |

- **视觉零变化论证**：v5 渲染为 `gap:24 flex/grid`，v6 单行两列的默认 `gap:24` + 列内模块间距 20 完全复刻
  现有 `.ecod-sec__cols` / `.ecod-sec__col` 的度量；md 折叠顺序 aside→main 与现有 `order:2` 一致；
- **存储策略**：v5 文档**落盘内容不动**，直到用户首次保存才以 v6 写回（读时规范化 = 零迁移脚本）；
  回滚逃生舱 = 快照（每文档 8 份）+ rev 乐观锁；明确记录 v6 为**单向升版**（不做 v6→v5 降级转换，
  fr 权重在 v5 无损表达不了，硬降级只会静默改变线上版式）。

---

## 5 渲染层设计

### 5.1 DOM 结构

```html
<section class="ecod-sec ecod-sec--boxed" id="anchor" data-buid="sec-x" data-bkind="section"
         style="--sec-pad:48px;--sec-max:1200px;--sec-radius:16px">
  <div class="ecod-sec__inner">
    <div class="ecod-sec__row" data-buid="row-x" data-bkind="row"
         style="--row-gap:24px;--row-cols:minmax(0,3fr) 320px;--row-valign:start">
      <div class="ecod-sec__cell ecod-sec__cell--sticky" data-buid="cell-y" data-bkind="cell"
           style="--cell-w:3fr">…模块渲染，复用 ecod_layout_render_col() 的循环体…</div>
      <div class="ecod-sec__cell" data-buid="cell-z" data-bkind="cell" style="--cell-w:320px">…</div>
    </div>
    <!-- 更多行 … -->
  </div>
  <!-- builder 模式专属：板块标签 ecod-b-seclabel 沿用 -->
</section>
```

- `--row-cols` 由服务端按 cells 序列直接拼出（fr 列 → `minmax(0,Nfr)`；px 列 → `Npx`）；
- 断点折叠用类而不是重算变量：`.ecod-sec__row--keep-md` 缺席时媒体查询把 `--row-cols` 覆盖为单列；
- 列级 ov 输出类，复用现有 `ecod-hide-md/sm` 基建 + 新增 `ecod-cell--hide-md/sm`、
  `style="--cell-order:1"`（仅折叠态生效）；
- 现有 `ecod_layout_render_col()` 改造为 `ecod_layout_render_cell()`：循环体（on/off 提示、
  data-buid/data-btype、hide 类）原样保留，只是容器从 `.ecod-sec__col` 换成 `.ecod-sec__cell`。

### 5.2 CSS（ecod.css §42 增量，不新开分节）

```css
/* 行：N 列网格；默认单列（stack 语义的延续） */
.ecod-sec__row {
    display: grid;
    grid-template-columns: var(--row-cols, minmax(0, 1fr));
    gap: var(--row-gap, 24px);
    align-items: var(--row-valign, start);
    min-width: 0;
}
.ecod-sec__cell { min-width: 0; display: flex; flex-direction: column; gap: 20px; }
.ecod-sec__cell--sticky { position: sticky; top: 76px; }

/* md 折叠：整行塌成单列（除非显式 keep），列按 DOM 顺序纵排 */
@media (max-width: 900px) {
    .ecod-sec__row { grid-template-columns: minmax(0, 1fr); }
    .ecod-sec__row--keep-md { grid-template-columns: var(--row-cols); }
    .ecod-sec__cell--hide-md { display: none !important; }
    .ecod-sec__cell--sticky { position: static; }
}
@media (max-width: 680px) {
    .ecod-sec__cell--hide-sm { display: none !important; }
    .ecod-sec__row--hide-sm { display: none !important; }
}
```

- 删除 `.ecod-sec__cols` / `--left/--right/--aside` 规则族，由行/列规则取代（v5 DOM 不再产生）；
- **visual 零变化**依赖 §4.7 的度量复刻：`gap:24 / 列内 gap:20 / asideW px / sticky top:76px` 全部原值；
- critical CSS 纪律不变：全部规则留在 §42 分节，构建管线照常提取；
- 模块内部的容器查询（列表减列）不受影响——列天然是 `container-type` 感知宽度的盒。

### 5.3 性能与安全

- 前台**仍然零 JS、纯服务端渲染**；新增的只是几个 CSS 变量与类名，无运行时成本；
- builder 模式标记（data-bkind）与旧规则相同：仅 `?ecod_builder=1` 时输出；
- 无新写接口、无新注入面；px/权重/gap 全部服务端钳制（§7.1）。

---

## 6 编辑器设计（让「随意布局」真正可用）

自由布局的可用性全在编辑器。以下按组件展开，函数名均对应 builder.js 现状。

### 6.1 选择模型

`S.sel` 从 `{kind:'module'|'section', id}` 扩展为四类：
`{kind:'module'|'cell'|'row'|'section', id}`。

- 命中规则：iframe 内点击 → `closest('[data-buid]')` 沿祖先链取**最近**宿主，按 `data-bkind` 分发
  （模块壳是 `display:contents`，点模块内容天然命中模块自身；点行间空隙命中 cell；点列间沟槽命中 row）；
- Esc 逐级上逃（module → cell → row → section → 取消选中）；
- 悬停描边同理分四类（1px hover 框 + 左上角类型小签）。

### 6.2 拖放模型 v2（computeDrop 扩展，builder.js:972）

`computeDrop(fx, fy)` 返回的落点类型从 4 种扩展为 8 种：

| kind | 含义 | 命中区域 | performDrop 行为 |
|---|---|---|---|
| `cell` | 插入某列第 idx 个模块位 | 列内模块间隙（现有逻辑，作用域改为 cell） | splice 进 `cell.mods` |
| `cell-split` | **在列左/右边缘分裂出新列** | 距 cell 左/右边缘 ≤24px 的热区 | 该列与相邻列权重对半拆，插入新 cell 并放入模块（Elementor 拖容器同款手感） |
| `cell-move` | 模块跨列移动 | 落到另一个 cell | 原列摘除 → 目标列插入 |
| `row-new` | 在行与行之间新建行（可带预设列结构） | 板块内两行之间 / 行上下边缘 | 插入新 row，1 列或按拖入的预设展开 |
| `row-move` | 行在板块内移动 | 拖行工具条的移动柄 | 行数组重排 |
| `section` / `section-move` | 板块插入/移动 | 现有逻辑不变 | 不变 |
| `warea` / `warea-row` | v4 区域 | 现有逻辑不变 | 不变 |

指示器：`cell`/`cell-move` 用现有横条 `.ecod-b-ind`；`cell-split` 用竖向主色分隔条；
`row-new` 用带「＋行」提示的横条（`.ecod-b-ind--row`）。全部画在 iframe 内（§8.4 混合 chrome 不变）。

`findMod()`（builder.js:101）升级为 `findPath(uid)`：返回
`{sec,row,cell,mod,index:{s,r,c,m}}` 的完整路径，Store 的一切结构操作以路径为参数。

### 6.3 画布 chrome

- **行工具条**（悬停行时浮出，iframe 内 `.ecod-b-rowtb`）：
  `[＋列] [预设▾] [间距▾] [↕移动] [⧉复制] [🗑删除]`——「＋列」把最后一列对半拆出新列（上限 6）；
- **列宽拖拽手柄**（悬停列间沟槽 / px 列右缘）：`cursor:col-resize` 竖线手柄，
  拖动实时改相邻两列权重（和恒定）或 px 值（160–640 钳制），双击复位为等分；
- **空列 CTA**：空 cell 渲染虚线框「拖入模块」（取代现在「空栏 CTA」与「空板块 CTA」两种状态，统一为一种）；
- **板块级**：现有标签、插入按钮全部保留。

### 6.4 列宽拖拽的平滑时序（唯一的热更新通道）

结构变更（增删行列、移动模块）走现有 `scheduleReload()` 重载画布——服务器渲染仍是唯一真源；
**只有权重/px/gap/valign 这类纯 CSS 变量变更**走热更新，避免拖宽时整页重载的闪烁：

1. pointerdown 记录初始权重 → pointermove 直接改 iframe 内该行 `--row-cols` 内联值（rAF 节流，零往返）；
2. pointerup → Store 更新 + markDirty + 暂存 debounce（现有 stage.save 通道），**不重载**；
3. 若期间发生其他结构性操作触发了重载，重载后自然以 Store 为准，热更新状态被清空。

### 6.5 Inspector（右栏）

选中行 / 选中列新增两个面板（字段全部走 EcodFields registry，旧 §4.2 机制）：

**行面板**

| 组 | 控件 |
|---|---|
| 结构 | 列数 stepper（1–6，增减即拆/并列，拆分权重对半）；每列权重滑条列表（可视化条形，可拖） |
| 间距 | gap 预设（0/8/16/24/32/48 + 自定义 0–96）；垂直对齐 start/center/stretch |
| 断点 | 设备三段（桌面/平板/手机）：平板保持并排 keep、行隐藏 hide、gap 覆盖 |
| 操作 | 前插行 / 后插行 / 复制行 / 删除（确认）/ 上移 / 下移 |

**列面板**

| 组 | 控件 |
|---|---|
| 宽度 | 模式二选一：比例（w 滑条 1–12）/ 固定（wpx 数值 160–640）；吸顶开关 |
| 断点 | 设备三段：hide / w 覆盖 / order 换序（手机段专属提示「折叠后的显示顺序」） |
| 卡面（P1） | 背景色/图片、圆角、内边距——把「板块级 boxed」下沉为「列级卡面」，这是卡片内分栏版式的最后一块拼图 |
| 操作 | 前插列 / 后插列 / 与左合并 / 删除（模块自动并入左列）/ 跨行移动（选目标行） |

模块面板：现有「内容/样式」不变；新增「移动到…」（选行选列的级联 select）作为拖拽的键盘可达等价路径。

### 6.6 结构预设库（左栏模块库新增「结构」分组）

行模板作为一类特殊「可拖放项」（不是模块，不进文档，drop 时展开成 row+cells）：

`单栏 · 两栏 1:1 · 两栏 2:1 · 两栏 1:2 · 三栏 1:1:1 · 三栏 2:1:1 · 四栏 · 侧栏右(px320) · 侧栏左(px320) · 主宽窄边(2fr+240px)`

- 来源对照：Gutenberg columns 块的预设列数 / Beaver 的 Column 设置 / 瓜奇的布局演示；
- 同时作为**点击插入**路径（点预设 → 追加到当前板块末尾，键盘可达）；
- Starter 模板（`ecod_layout_templates()`）改用 v6 结构重写，`classic-home` 的「双栏推荐」行升级为
  2:1 行 + px 侧栏列并存的示范版式。

### 6.7 大纲树 v2

层级 `板块 → 行 → 列 → 模块` 四层，行内控件：

- 行：显隐、上移/下移、复制、删除；
- 列：权重徽标（`2fr` / `320px`）、显隐、左移/右移、删除；
- 拖拽：模块可拖到任意列（跨行跨板块），列可拖到其他行，行可重排——全部在编辑器文档内 HTML5 DnD，
  落点只改 Store + 重载画布，不与 iframe 实时交互（旧 §5.4 第三条链路原样适用）；
- 与画布双向高亮（现有机制复用，key 扩展到 row/cell uid）。

### 6.8 撤销 / 锁定 / 键盘 / 触达

- **撤销栈**：粒度仍是「整文档 JSON 快照 diff」（现有 captureUndo），四类对象的操作天然被覆盖，零新机制；
- **锁定模式**：`S.lock` 语义扩展——锁定时行/列的一切结构操作（增删移、改权重、分裂）只读，
  仅模块参数与显隐可改（与现有「锁定后只许改模块参数」完全同构）；
- **快捷键**：现有 Esc/Del/⌘D/⌘Z/⌘Y/⌘S 全部保留，Del 的删除对象跟随 sel.kind（删列时确认并提示
  「列内 N 个模块将并入左列」）；
- **触达**：行/列的全部操作在大纲树与 Inspector 都有等价按钮，拖拽永远只是快捷路径（旧 §5.2.1 纪律延续）。

### 6.9 Store 与保存时序

- `S.sections` 结构变为 v6（§4.1），序列化即文档本体，保存/暂存/发布**payload 形状不变**；
- `markDirty` / stage debounce / publish 双 rev 校验 / 冲突弹窗全部沿用，零改动；
- 文档体积监视：序列化后 >48KB 时状态栏出黄条（§4.5）。

---

## 7 数据层与接口

### 7.1 cleaner：递归白名单收口

`ecod_bld_clean_section()` 重写为 v6 口径（保留函数名与调用面）：

```
ecod_bld_clean_doc(doc):
    sections = take(doc.sections, 0..ECOD_LAYOUT_SECTIONS_MAX)
        ecod_bld_clean_section(sec, i):
            既有字段清洗原样（uid/box/anchor/ov…）
            rows = take(sec.rows, 0..ROWS_MAX)
                ecod_bld_clean_row(r):           # 新增
                    uid/on/gap(0..96)/valign 枚举
                    ov 白名单: md/sm × {keep:0|1, gap:int, hide:0|1}
                    cells = take(r.cells, 0..CELLS_MAX)
                        ecod_bld_clean_cell(c):   # 新增
                            uid；w(1..12) 与 wpx(160..640) 二选一（wpx 优先）
                            sticky 0|1
                            ov 白名单: md/sm × {hide, w(1..12), order(-6..6)}
                            mods = ecod_bld_clean_rows(c.mods)   # 现有函数原样复用
            # v5 输入先经 ecod_bld_norm_doc() 展开成 rows 再走本流程
```

- 白名单外的键**服务端硬丢弃**——与板块级 ov（旧 §9.3）同等待遇，防止行/列级覆盖被滥用；
- 嵌套（P1）：`cell.rows` 允许出现时，内部行/列清洗复用同一对函数，深度参数 ≤2，超深整枝丢弃。

### 7.2 API 面：零新增端点

| action | 变化 |
|---|---|
| doc.get / doc.save / doc.delete | payload 形状不变（sections 换成 v6 内容）；save 前 norm+clean |
| stage.save / stage.discard / publish | 不变 |
| snap.list / snap.restore | 不变（restore 走 norm） |
| settings.* / area.save / wgt.get | 不变 |
| canvas | 不变（iframe 直载真实页面 + blddoc） |

### 7.3 快照体积治理（先行基建项）

v6 文档更大，现有 `ELayoutSnaps` 单条 24KB / 全局 24 份的 options 行方案会成为瓶颈
（builder-doc.php:912 注释已警示 64KB 列限制）。P0 顺带把快照迁到独立表：

```
ecod_layout_snap: id, doc_key, t, doc(JSON), size     ← schema v16，ensure 自愈模式照抄 ecod_page_layout
每文档保留 8 份、超 64KB 的快照跳过并记日志
```

`ecod_layout_snap_push/get/index` 三个函数换存储后端，调用面不变。

---

## 8 分期路线

| 期 | 交付 | 主要文件 | 粗估 |
|---|---|---|---|
| **P0 行列模型成立** | v6 schema + `ecod_bld_norm_doc`（v5 读时规范化）+ 渲染器行/列 + CSS §42 增量 + cleaner 重写 + 快照迁表 + 模板 v6 重写；**编辑器维持现状可编辑**（v6 文档经 norm 后旧 UI 仍能改 main 语义列——实现上让旧「栏=单列行」映射） | `core/admin/builder-doc.php`、`core/lib/schema.php`、`assets/css/ecod.css` §42 | 数据/渲染侧约 2–3 天 |
| **P1 编辑器自由布局** | 选择模型四类 + computeDrop v2（cell / cell-split / row-new…）+ 行工具条 + 列宽拖拽热更新 + 行/列 Inspector + 结构预设库 + 大纲树 v2 + 锁定/键盘适配 + **列内嵌行（深度 2）** + 列级卡面 | `assets/admin/builder.js`、`builder.css`、`builder.php`（图标/CFG 小改） | 约 4–6 天 |
| **P2 生态与效率** | 可复用行（Gutenberg synced patterns 语义）、批量改列（Divi Extend Styles 式）、模板中心/一键换装（瓜奇范式，扩展现有模板菜单 + 导入导出）、行级 masonry 流（CSS columns 实验）、文档同步 | `builder.js`、`builder-doc.php`、`docs/` | 按需 |

P0 完成即可解锁「N 栏不等宽」的**数据能力**（配旧 UI 仍只能整块改结构，但版式已可由模板/导入表达）；
P1 才是「随意布局」的体验闭环。两期之间不存在数据返工——P0 的 schema 就是终态。

---

## 9 风险与对策

| 风险 | 影响 | 对策 |
|---|---|---|
| v5→v6 迁移改变线上版式 | 已发布页面视觉漂移 | 读时规范化 + 度量逐项复刻（§4.7）；norm 后 diff 一次真实站点文档做验收；快照兜底 |
| 文档体积撑爆 options 行 | 保存失败/丢数据 | 行列模块三级上限 + 48KB 软警告 + 快照迁表（§7.3）；必要时布局文档整体迁 `ecod_page_layout` 同构表（预留，不进 P0） |
| 拖放命中复杂度上升 | computeDrop 维护成本 | 落点类型表（§6.2）显式枚举 + 每类型独立纯函数；指示器按 kind 走独立 CSS |
| 列宽拖拽热更新与重载竞态 | 拖到一半触发重载丢状态 | 热更新只写内联 CSS 变量、不改 Store 中间态；重载后以 Store 为准（§6.4 step3） |
| 嵌套行引发递归失控 | cleaner/渲染/大纲全递归 | 深度硬上限 2、超深整枝丢弃；P1 才放开，且编辑器不提供「行内再建行」入口，只提供「列内嵌套行」预设 |
| 旧文档/旧 UI 缓存并存 | 编辑中的浏览器缓存了 v5 builder.js | save 前 norm，任何版本输入都收敛为 v6；builder.php 强刷版本参数（现有 dist 哈希管线覆盖） |
| 锁定语义被新操作绕过 | 多作者站点结构被改 | 全部行列操作入口（工具条/Inspector/大纲/拖放）统一过 `S.lock` 门禁，与现有单点判断同构 |

---

## 10 决策记录

| # | 决策 | 理由 / 放弃项 |
|---|---|---|
| v6.1 | **行/列受控模型，嵌套深度 ≤ 2**；拒绝 Elementor/Bricks 式无限容器树 | 自由度的成本拐点在深度不在列数：列数只是 `grid-template-columns` 一个字符串，深度才递归污染清洗/渲染/大纲/拖放。深度 2 覆盖「卡片内分栏」全部真实需求 |
| v6.2 | **宽度 = 整数权重（fr）+ 可选固定 px 双模** | 百分比有取整漂移（WPBakery 时代的老坑），权重只在行内相对比较；px 仅保侧栏还原与固定宽导航列 |
| v6.3 | **维持单数据源 + ov 白名单**，白名单下沉到行/列级 | 维持旧 §8.1：瓜奇三设备树需要整套同步工具与冲突处理，单人维护成本差一个数量级；白名单是防滥用的硬约束 |
| v6.4 | **模块仍是叶子**（维持并细化旧 §8.3） | 并排=多列、嵌套=列内嵌行；模块一旦有 children，54 个模块的清洗与渲染全部递归化，且「模块套文档」的递归歧义无解 |
| v6.5 | **结构变更重载画布；纯 CSS 变量变更（列宽/gap）热更新** | 服务器渲染是唯一真源（前台零 JS 纪律）；唯有权重拖拽需要逐帧反馈，内联 `--row-cols` 是零往返通道 |
| v6.6 | **v6 单向升版：读时规范化，写时升版，不做降级** | fr 权重在 v5 无损表达不了，硬降级=静默改版式；逃生舱是每文档 8 份快照 + rev 锁，不是降级器 |
| v6.7 | **修正旧 §1.3**：撤回「任意行列嵌套」禁令 → 「受控行列自由 + 受控嵌套」 | 用户核心诉求就是「中间预览区域随意布局」；瓜奇「无数种布局」的底气同样来自板块内多列自由而非自由画布（§3.2） |
| v6.8 | **快照迁独立表**（与布局文档升版同批） | v6 文档更大，options 单行方案（24KB/条）必然先于文档本身爆掉；迁移只换三个函数的存储后端，调用面零变化 |

---

相关文档：`docs/builder-design.html`（v5 总设计，本文是其布局自由度修正案）·
`docs/ARCHITECTURE.md`（主题总体架构）· `core/admin/blocks.php`（v4 区域兜底体系）
