# Ecod Theme · 动效工艺升级任务单

> 依据：`swamimalode07/rare-ui`（fluid-orb）与 https://transitions.dev/ 的技法水位对标
> 审计对象：`assets/css/ecod.css`（9426 行）、`assets/js/ecod.global.js`（3103 行）、`assets/js/ecod.post.js`（2650 行）
> 日期：2026-10-30

---

## 零 · 目标与基线

**结论：不做视觉重构，只补「最后一公里」的动效工艺。** 设计令牌骨架已达标，缺口集中在时序函数的落地率。

### 已达标（不动）

| 项 | 实测 |
|---|---|
| 令牌体系 | 时长三档 `--ecod-dur-fast/--ecod-dur/--ecod-dur-slow`（140/200/320ms）+ z-index 十级刻度 + 双光源阴影 + 圆角四档，定义于 `assets/css/ecod.css:55-80` |
| transition 属性选择 | 165 处，**0 处 `transition:all`**；transform 66 / color 63 / box-shadow 40 —— 性能正确 |
| 视觉层次 | `backdrop-filter` 25 处、`:hover` 204 处、`linear-gradient` 72 处 |
| 可访问性 | `prefers-reduced-motion` CSS 4 处（166 / 4878 / 6102 / 7210），JS 1 处（`ecod.global.js:1649`） |

### 缺口（本单要解决的）

| # | 缺口 | 实测证据 |
|---|---|---|
| G1 | `--ecod-ease` 定义于 `ecod.css:67`，全站仅被引用 **1 次**（`:7028`）；165 处 transition 中 **137 处未写 timing-function**，回落浏览器默认 `ease`（分布 107–9372 行，130+ 选择器） | 曲线是摆设 |
| G2 | 弹层入场仅 140ms（`--ecod-dur-fast`），低于 180ms 的入场显得「跳」 | `.ecod_fmodal__panel` 等 |
| G3 | 全站 0 处弹性曲线，10 个 keyframes 全为 `ease` 直线缩放 | `@keyframes` 2639/7730/8125/8369 |
| G4 | 0 处 `animation-delay` → 列表无级联 | 全文件 |
| G5 | `transform-origin` 全站仅 1 处且值为默认 `50% 50%`（`:3801`）→ 弹层不从触发源展开 | `.ecod_spanel__box:3306` 用固定 `translateX(-50%)` |
| G6 | PJAX（`ecod.global.js:1806`）只有顶部加载条，新旧页面无连续性；无 `startViewTransition` | grep 0 命中 |

### 工程约束

- 源码改 `assets/css/ecod.css` / `assets/js/*.js`，**必须** `cd build && npm run build` 生成 `assets/dist/**`（esbuild + content hash + PHP manifest），否则页面不生效。
- 按项目纪律「文档先行」：先做 **M-00**，再动代码。

---

## 一 · 任务总览

| ID | 优先级 | 任务 | 影响文件 | 规模 |
|---|---|---|---|---|
| M-00 | 前置 | 设计文档补 §33 动效与过渡规范 | `docs/dev/design-proposal.html` | S |
| M-01 | P0 | transition 时序函数全量铺开 | `assets/css/ecod.css` | M（脚本化） |
| M-02 | P0 | 弹层入场时长校准 140→240ms | `assets/css/ecod.css` | XS |
| M-03 | P1 | pop 类弹层换回弹曲线 | `assets/css/ecod.css` | XS |
| M-04 | P1 | 列表级联入场（stagger） | `ecod.css` + `ecod.global.js` | M |
| M-05 | P1 | 弹层触发源展开（origin-aware） | `ecod.css` + `ecod.global.js` | M |
| M-06 | P2 | PJAX 接 View Transitions API | `ecod.global.js` | S |
| M-07 | P2 | 390px 横向溢出复核 | 临时量测页 | S |

---

## 二 · M-00 设计文档补 §33（前置）

**目标**：把本单确立的动效规则写入 `docs/dev/design-proposal.html`，作为后续唯一口径（现最大章节为 §32 分类与标签增强，新章为 §33）。

**章节内容要点**：

1. 时长三档语义：`fast 140ms`（同层状态切换：hover/focus/颜色）｜`dur 200ms`（常规过渡：展开/位移/尺寸）｜`slow 320ms`（大面积展开：面板/抽屉）
2. 新增入场档 `--ecod-dur-enter: 240ms`（首次出现类动画专用，不与上面三档混用）
3. 曲线两条：`--ecod-ease: cubic-bezier(.4,0,.2,1)`（位移/尺寸，标准）｜`--ecod-ease-pop: cubic-bezier(.34,1.56,.64,1)`（仅用于会「弹出来」的元素：pop 提示、弹窗、徽标）
4. **强制规则**：任何 `transition` / `animation` 必须显式声明曲线，禁止回落默认 `ease`
5. 级联节奏：列表入场 `40ms × 序号`，序号上限 12（超出按 12 计）
6. origin 规则：所有从触发点展开的弹层，`transform-origin` 必须由 JS 写入触发元素中心点
7. reduced-motion 契约：CSS 侧动画退化 + JS 侧 `startViewTransition` / 级联入场直接跳过

**验收**：`design-proposal.html` 出现 `§33` 章节，含上述 7 条；后续代码改动逐条对应。

---

## 三 · P0

### M-01 transition 时序函数全量铺开

**目标**：消除 137 处回落默认 `ease` 的 transition，全站统一 `--ecod-ease`。

**现状证据**：`ecod.css:67` 定义曲线；全文件仅 `:7028` 一处使用。无 timing 的写法形如：

```css
transition: color var(--ecod-dur);
transition: color var(--ecod-dur), transform var(--ecod-dur), box-shadow var(--ecod-dur);
```

**改动方案**：脚本化逐段补齐（**不能用 sed 整行追加**——多段 transition 每段各自需要 timing-function，写在一处不会继承）。

```python
import re
p = 'assets/css/ecod.css'
css = open(p, encoding='utf-8').read()
SKIP = re.compile(r'cubic-bezier|steps\(|--ecod-ease|\blinear\b')   # 已有曲线 / linear / steps 一律不碰
EASEKW = re.compile(r'(?<![-\w])(ease-in-out|ease-out|ease-in|ease)(?![-\w])')

def fix(decl):
    out = []
    for seg in decl.split(','):          # 多段必须逐段处理：timing 不会跨段继承
        s = seg.strip()
        if not s or s == 'none':
            out.append(s); continue
        if SKIP.search(s):
            out.append(s); continue
        if EASEKW.search(s):
            s = EASEKW.sub('var(--ecod-ease)', s)   # 字面 ease 收敛为令牌，避免两套曲线
        else:
            s = s + ' var(--ecod-ease)'             # 无曲线：补上
        out.append(s)
    return ', '.join(out)

css = re.sub(r'transition\s*:\s*([^;}]+)', lambda m: 'transition: ' + fix(m.group(1)), css)
open(p, 'w', encoding='utf-8').write(css)
```

**已在副本实测（2026-10-30）**：变更 162 条、改后缺曲线 0 条、花括号平衡、行数不变（格式未破坏）；3 处 `linear` 段（`width .1s linear` 进度条、`visibility 0s linear` 离散属性）完整保留未误伤。

**注意**：脚本只匹配 `transition`，不影响 `animation`（shimmer 的 `linear` 安全）。

**验收**：
```bash
cd build && npm run build
# 期望输出「缺曲线 0 条」（口径与改写脚本一致：linear/steps/none 视为已声明）
python3 -c "
import re
c=open('../assets/css/ecod.css',encoding='utf-8').read()
bad=[d for d in re.findall(r'transition\s*:\s*([^;}]+)',c)
     if '--ecod-ease' not in d and not re.search(r'cubic-bezier|steps\(|\blinear\b|none',d)]
print('缺曲线:',len(bad));[print(' ',b[:80]) for b in bad[:5]]"
```
人工抽验：首页卡片 hover、nav 链接 hover、按钮 hover 三处，过渡应比改前更「收」，不再有尾部拖沓。

**风险**：Material 曲线在短距离变化上比默认 `ease` 更快收尾，可能让 140ms 档显得更急。若观感过冲，先只对 `--ecod-dur` 及以上档位接入，`fast` 档保留默认（回滚即改脚本条件）。

---

### M-02 弹层入场时长校准

**目标**：弹窗/用户下拉/友链申请等「从无到有」的入场从 140ms 提到 240ms。

**现状证据**：`.ecod_fmodal__panel` 使用 `animation: ecod-authmodal-in var(--ecod-dur-fast) ease`（140ms）。`ecod_upop_in`、`ecod-pop` 同类。

**改动方案**：
1. `:root` 增加 `--ecod-dur-enter: 240ms;`（插在 `ecod.css:66` 的 `--ecod-dur-slow` 之后、`:67` 的 `--ecod-ease` 之前）
2. 下列 `animation` 的时长参数 `var(--ecod-dur-fast)` → `var(--ecod-dur-enter)`：
   - `ecod-authmodal-in`（弹窗）
   - `ecod_upop_in`（用户下拉）
   - `ecod-pop`（卡片内弹层）
3. `ecod-pulse` / `ecod-shimmer` / `ecod-breath` 属持续型循环动画，**不动**

**验收**：三个弹层肉眼入场不再「一闪即到」；`grep -c "ecod-dur-enter" assets/css/ecod.css` ≥ 4（1 定义 + 3 使用）。

**风险**：无（单纯时长调整）。

---

## 四 · P1

### M-03 pop 类弹层换回弹曲线

**目标**：让「弹出来」的元素有物理回弹感（对标 transitions.dev 的 spring pop-in）。

**现状证据**：`@keyframes ecod-pop`（:2639）、`ecod_upop_in`（:7730）、`ecod_authmodal-in`（:8125）均为 `ease` 直线缩放，无过冲。

**改动方案**：
1. `:root` 增加 `--ecod-ease-pop: cubic-bezier(.34, 1.56, .64, 1);`
2. 上述三个 animation 的曲线由 `ease` 改为 `var(--ecod-ease-pop)`
3. 对应容器确保 `overflow: visible`（弹窗面板本身可保持 `hidden`，只让外层不裁切）

**验收**：打开弹窗/用户下拉，元素到位有轻微过冲回弹（scale 略超 1 再回落）；`controls` 无裁切、无横向滚动条。

**风险**：过冲曲线在 `scale` 上会短暂超出父容器，若父级有 `overflow:hidden` 会被切角。逐一检查三个弹层的祖先容器；发现裁切则该处退回 `--ecod-ease`。

---

### M-04 列表级联入场（stagger）→ 实施时修正为「修 transitionDelay 泄漏」

> **审计修正（2026-10-30 实施）**：级联入场**已经存在**——`ecod.global.js` 的 `ecodAnimateRun()`（IntersectionObserver + `.ecod-reveal` + `transitionDelay = min(index,6)*45ms`，CSS 在 `ecod.css:6098-6104`，后台开关 `EAnimate`，默认 `none`）。本任务单最初只 grep 了 `animation-delay` 造成漏判。现有参数（45ms / 上限 6）优于原方案（40ms / 上限 12），**不重复实现**。

**真实缺陷（已修）**：`transitionDelay` 设为 inline style 后**从不清理**——`transition-delay` 作用于该元素的**所有**过渡，开启动效后卡片 hover 的 transform / 阴影最多延迟 270ms 才响应，手感发钝。

**修复**（`ecod.global.js` `ecodAnimateRun`）：`transitionend`（`opacity` 属性）触发时清掉 inline delay，另加 `delay + 520ms` 定时兜底（reduced-motion 等场景过渡不触发时也能清）。

**验收**：开启 `EAnimate` 后卡片依次入场；入场结束后 hover 立即响应（无延迟）。

---

### M-05 弹层触发源展开（origin-aware）→ 实施落点修正

> **落点修正（2026-10-30 实施）**：核实后只做两处——
> - **右键菜单 `.ecod_ctx`**：有 `scale(.98)`，origin 直接生效；站点配置 `ECtxMenu` 当前为 `off`，代码就位待开启后生效。
> - **用户下拉 `.ecod_nav__upop`**：原 keyframes 是纯 `translateY`（origin 对位移无视觉效果），已补 `scale(.96)` 让 origin 有意义；origin 用 offset 坐标计算（不受入场 scale 影响）。
>
> **不做**：导航下拉 `.ecod_nav__drop` 是纯 hover 展开、无动画（加动画是新功能，超出本单）；搜索面板是全屏居中面板，从触发点展开反而怪异。

**已实施**：CSS 两处 `transform-origin: var(--ecod-ox, 50%) var(--ecod-oy, 50%)`；JS `ctxPlace()` 写入光标相对菜单的 px 偏移，`initUserPop` 监听 `details` 的 `toggle` 写入 summary 锚点。

**实测（headless 诊断）**：用户下拉展开后 `--ecod-ox=17px / --ecod-oy=34px`，computed `transform-origin: 17px 34px`，动画 `ecod_upop_in 0.24s cubic-bezier(0.34,1.56,0.64,1)`，全部生效。

---

## 五 · P2

### M-06 PJAX 接 View Transitions API

**目标**：换页时旧页淡出、新页淡入，消除「白闪 + 顶部加载条」的断层（对标 card-resize 的连续性）。

**落点**：`ecod.global.js:1806` `Ecod.initPjax` 的 DOM 替换处。

**改动方案**：DOM 替换包一层，特性检测 + reduced-motion 跳过：

```js
function commitSwap(fn) {
    if (!document.startViewTransition || Ecod.reduceMotion()
        || !window.matchMedia('(hover: hover)').matches) { fn(); return; }
    document.startViewTransition(fn);
}
```

配套 CSS：`#ecod-shell { view-transition-name: shell; }` + `@media (prefers-reduced-motion: reduce) { ::view-transition-group(*), ::view-transition-old(*), ::view-transition-new(*) { animation: none !important; } }`

**验收**：站内跳转无白闪；Safari 等不支持环境行为与现在完全一致（降级无副作用）；返回键正常。

**风险**：View Transition 期间页面被快照冻结，若 PJAX 内有异步脚本执行可能卡在中间态——只在 DOM 替换这层包裹，不包裹后续初始化。

---

### M-07 390px 横向溢出复核

**目标**：确认移动端 hero / 分类胶囊是否真溢出（前次截图疑似溢出，但 headless `--window-size=390` 存在视口偏差，不作定论）。

**方法**（项目既有做法）：写临时量测页，把目标页塞进 390px iframe，读 `scrollWidth` 并列出 `right > innerWidth` 的元素；用完即删，放站点根目录（`/Users/bws/blog/ecod/__diag_*.php`，不改 nginx 白名单）。

**产出**：仅出结论 + 问题元素清单。**若确认溢出，另开任务处理，不在本单范围**。

---

## 六 · 不做清单

| 项 | 理由 |
|---|---|
| 引入 fluid-orb 式 WebGL 流体球 | 与本站克制单 accent 风格冲突；Rare UI 为 MIT + Commons Clause + **强制署名链接**，直接移植有合规负担 |
| 引入 Motion / anime.js 等动效库 | 现有原生能力足够；4553 行 JS 已无框架依赖，加库违背轻量定位 |
| 全站 spring 物理引擎 | 只有弹层需要回弹，一条 `cubic-bezier` 即可覆盖，不值上物理引擎 |
| 重做视觉样式 / 换配色 | 视觉底子已达参考水位，本单只补动效工艺 |

---

## 七 · 验收与回归

每个任务完成后：

1. `cd build && npm run build` 成功，`assets/dist/` 有新的 hash 文件
2. PHP 侧无 8.4+ 弃用告警混入 HTML（`~/Library/FlyEnv/server/vhost/logs/1793099469421.error.log` 应无新增）
3. 三档回归：桌面 1440 / 移动 390 / 暗色模式
4. reduced-motion 开启后：无级联、无回弹、无 View Transition，功能不受影响
5. 关键交互回归：搜索面板开关、抽屉、用户下拉、右键菜单、弹层关闭（`Esc` / 点遮罩）、PJAX 前进后退

### 验收记录（2026-10-30）

| 项 | 方法 | 结果 |
|---|---|---|
| 构建 | `npm run build` | ✅ 全量产物重建，CSS -23.5% / JS -52.2% |
| 移动端 390 | iframe 量测 `scrollWidth` | ✅ `innerWidth=390 = scrollWidth=390`，无横向溢出 |
| 暗色模式 | iframe 置 `data-theme=dark` 截图 | ✅ 层次正常、无低对比区、无白底刺眼块 |
| reduced-motion | `--force-prefers-reduced-motion` | ✅ `matchMedia=true`；瞬时时长被压到 `1e-05s`；右键菜单等功能照常 |
| 右键菜单 origin | 临时开 `ECOD.ctxmenu` 后 dispatch `contextmenu` | ✅ `--ecod-ox/oy` 写入，`transform-origin: 0px 0px`（光标即原点） |
| 用户下拉 origin | `details.open = true` 后读 computed | ✅ `transform-origin: 17px 34px`，动画 `0.24s cubic-bezier(.34,1.56,.64,1)` |
| PJAX 前进 | iframe 内 dispatch 点击站内链接 | ✅ 无整页刷新（window 标记存活）、标题与 shell 均更新、加载条收尾正常 |
| PJAX 后退 | 代码路径核查 | ✅ `popstate` → `load(href,false)` → **同一 `.then` 链 → 同一 `applyPage()`**，与前进共用，无独立分支；headless 实跑 `history.back()` 会 OOM，故以代码等价性代替 |
| 错误日志 | `vhost/*.error.log` | ✅ 无 PHP 弃用告警（仅原有 favicon 404） |

---

## 八 · 变更记录

| 日期 | 内容 |
|---|---|
| 2026-10-30 | 初版：基于 fluid-orb / transitions.dev 对标审计，产出 M-00 ~ M-07 |
| 2026-10-30 | **M-00 ~ M-07 全部实施完成**。要点：M-01 脚本化变更 162 条、缺曲线清零、3 处 `linear` 保留；M-02/M-03 新增 `--ecod-dur-enter` 与 `--ecod-ease-pop`（`ecod-pop` 实为点赞图标动画、keyframes 自带超调，故意不改）；M-04 修正为修 `transitionDelay` 泄漏；M-05 落点收敛为右键菜单 + 用户下拉；M-06 用 `applyPage()` 包裹替换逻辑接入 `startViewTransition`，shell 外常驻 UI 不参与淡化；M-07 实测 `scrollWidth=390=innerWidth`，**无横向溢出**（先前截图为 headless 视口偏差假象）。`build` 通过，headless E2E：PJAX 无刷新换页正常（marker 存活、标题更新）、用户下拉 origin 与回弹曲线生效。临时诊断页已删。 |
