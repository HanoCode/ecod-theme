# Ecod 主题 · 短代码体系审计报告

> 日期：2026-10-29
> 方法：源码走读（core/shortcode.php 559 行 + core/shortcode-extra.php 914 行）+ 线上演示页实测（ecod.cc）+ PHP 沙箱用例复现 + agent-browser 桌面/移动/暗色三端截图 + 与 Zibll 源码（/Users/bws/code/theme/zibll）对照。
> 分级：P1 = 功能错误/明显缺口，P2 = 边界缺陷/设计不一致，P3 = 体验打磨。
> **修复状态（2026-10-29 当日）：P1-1 ~ P1-4、P2-1 ~ P2-5 已全部修复**，另顺带修复 cloud 多行拆行 / 磁力行被丢弃 / `[reply ]` `[timeline ]` 带空格标签不命中，详见文末「修复记录」。
> **能力扩展（同日第二轮）：P2-6 已落地 4 项** —— `posts` 文章列表、`hide type="password"` 密码可见、`video` 平台解析（YouTube/腾讯/优酷/Vimeo）、`mp3` 网易云外链；并修复 `[article]` 封面字段列名错误与 `[hide]`/`[reply]` 内容进摘要的泄漏。
> **能力扩展（同日第三轮）：P3 已落地 4 项** —— `gallery` 相册图集（网格/瀑布流 + 灯箱分组）、`tooltip`、`badge`、`spoiler`，以及编辑器表情选择器；详见文末「修复记录」。付费/会员可见待支付体系分析后再排期。

---

## 一、能力清单（32 个标签）

| 分组 | 标签 | 编辑器按钮 | 编辑器预览 | README 文档 | 状态 |
| --- | --- | --- | --- | --- | --- |
| 提示强调 | note / alert / message / callout / anote | ✅ | ✅ | ⚠️ 仅 note | 正常 |
| 排版元素 | mtitle / copy / progress / abtn / dotted / lamp / task | ✅ | ✅ | ❌ | progress 有 P1 bug |
| 行内 | mark / kbd | ✅ | ✅ | ✅ | 正常 |
| 折叠/选项卡/时间线 | fold / tabs(+tab) / timeline | ✅ | ✅ | ✅ | 正常 |
| 卡片网格 | card / cards / grid | ✅ | ✅ | ❌ | 独立成段时有残留标签 |
| 下载网盘 | download / cloud（15 盘识别 + /go 中转） | ✅ | ✅（缺 magnet） | ✅ download | 正常 |
| 内容引用 | article / github / reply | ✅ | ✅ | ✅ reply | 正常 |
| 可见性 | hide（login/comment/level） | ❌ 无按钮 | ❌ 无预览 | ❌ | 可用但编辑器盲区 |
| 媒体 | bilibili / video / mp3 / music | ✅ | ✅ | ❌ | 正常 |
| 表情 | :smile: 等 31 个 | ❌ 正文无入口 | ❌ | ❌ | **P1：纯表情正文不渲染** |

架构面（好评）：代码块保护（pre/code 内方括号不误替换）、autop `<p>`/`<br>` 残留清理、`ecod_sc_block`/`ecod_sc_self` 统一解析、article 按 cid 静态缓存、hide 内容**服务端剥离不输出 HTML**（防查看源码绕过，比部分主题前端隐藏可靠）。

---

## 二、问题清单

### P1（功能错误）

**P1-1 纯表情正文不渲染表情**
- 位置：`core/shortcode.php:31` 早退守卫 `strpos($content, '[') === false`。
- 复现：`ecod_shortcode_apply('<p>a :smile: b</p>')` 原样返回（已用 PHP 沙箱确认）；正文只要不含 `[`，`:smile:` 永远不解析。演示页因含其他短代码而“看起来正常”。
- 修复：守卫改为 `strpos($content,'[') === false && strpos($content,':') === false`（或把 emoji 独立于守卫）。

**P1-2 进度条数字被 rtrim 吃掉**
- 位置：`core/shortcode-extra.php:308` `rtrim(rtrim((string)$percent,'0'),'.')`。
- 实测：`[progress 60]` → 显示 **6%**；`percentage="100"` → **1%**；`[progress 0]` → 光秃 **%**（沙箱复现）。
- 附带：裸值与属性混用 `[progress 60 label="整体进度"]` → **0%**（`ecod_sc_attrs` 只在整串无 `=` 时才认裸值，shortcode.php:136-141）。
- 修复：数字格式化改 `(int)` 输出；属性解析支持「首段裸值 + 具名属性」并存。

**P1-3 编辑器对 hide 完全盲区**
- `[hide]`（登录/评论/等级可见）无插入按钮、无预览渲染。作者只能手打，预览时看到的是未解析原文，易误判为“坏了”。
- 修复：编辑器菜单加按钮（tpl：`[hide type="login" tip=""]|内容[/hide]`），预览加锁定块渲染（与 reply 同款）。

**P1-4 短代码文档缺口**
- README 第五节只写了 7 个（note/fold/tabs/timeline/mark/kbd/reply/download），**24+ 个标签零文档**。作者侧根本不知道有 copy/progress/card/cloud/music/hide 这些能力。
- 修复：README 补全或单独出 `docs/shortcodes.md`；编辑器 tooltip 的 hint 已有，可作为素材。

### P2（边界缺陷 / 一致性）

**P2-1 `[abtn]`/`[card]` href 未校验协议 → `javascript:` 可注入**
- 位置：shortcode-extra.php:327-335（abtn）、412-416（icard）。仅 `htmlspecialchars`，未像 cloud/download 那样校验 `^(https?:)?//|^/`。
- 单作者博客风险低，但开放投稿/多作者时是存储型 XSS 入口。Zibll 走 WordPress `esc_url()`。
- 修复：与 anote 一致——非 `http(s)://`、`/`、`#` 开头一律回退 `#`。

**P2-2 `&amp;` 双重转义**
- 沙箱复现：`[abtn href="https://a.com/?a=1&amp;b=2"]` 输出 `href="...&amp;amp;b=2"`。HTML 模式粘贴带参数链接时 URL 损坏。
- 修复：属性值先 `html_entity_decode` 再输出时统一转义一次。

**P2-3 grid/cards 各自成段时残留 `<p>`/`</p>`**
- 沙箱复现：`<p>[grid column="2"]</p><p>[card …]</p>…<p>[/grid]</p>`（编辑器 HTML 模式逐行空行的自然结果）输出 `<div class="ecod_grid">` 后跟游离 `</p>`。
- 现有清理正则只处理「`<p>` 紧贴组件」与 `<br>` 分隔两种形态，漏掉 `</p>` 开头、`<p>` 收尾形态。
- 修复：ecod_sc_extra_block 收尾清理中补充对 grid/cards 内侧 `</p>`/`<p>` 的剥离。

**P2-4 `.ecod_mtitle__bar` 渐变尾色硬编码**
- `linear-gradient(180deg, var(--ecod-primary), #7d93f7)`（ecod.css:5291）。当前站点主色是橙 `#f59e0b`，小节标题条呈现橙→蓝的脏渐变，与单 accent 色策略冲突。
- 修复：改 `color-mix(in srgb, var(--ecod-primary) 55%, #fff)` 或独立 token。

**P2-5 封面变量 `url()` 未加引号**
- `--ecod-cover:url(https://…)`（article/mp3/music/playlist 四处）。URL 含 `(`/`)`/`,` 时整条 background 失效，图片静默消失。
- 修复：输出 `url("…")`。

**P2-6 与主流主题的能力缺口**（按收益排序）
| 能力 | Zibll 等主流 | 本主题 | 建议 |
| --- | --- | --- | --- |
| 文章列表/循环短代码 | postslists（分类/排序/条数/minicard） | ❌ 仅单篇 article | 高价值，可复用列表卡片组件 |
| 密码可见 hide | hidecontent type=password | ❌ | 与 hide 体系顺路补齐 |
| 付费/会员可见 | hidecontent payshow/vip1/vip2 | ❌（无会员体系） | 等支付体系后再议 |
| 视频平台解析 | 通用视频块 | 仅 bilibili + 本地 | 补腾讯/优酷/YouTube iframe |
| 音乐平台外链 | 网易云/QQ 外链播放器 | 仅本地直链 mp3 | iframe 嵌入成本极低 |
| 相册/图片组 | 图集排版 | ❌（有 lightbox 无布局） | 中价值 |
| tooltip / badge / spoiler | 常见行内件 | ❌ | 低优先 |
| 评分/广告位短代码 | 有 | ❌ | 视需求 |

### P3（打磨）

- 编辑器预览网盘表缺 `magnet`（PHP 15 种、预览 14 种），磁力链接在预览中落回通用样式。
- ~~编辑器无表情插入入口~~（第三轮已落地：工具栏表情按钮 + 31 个表情面板）。
- ~~相册/图集无布局~~（第三轮已落地：`[gallery]` 网格 / 瀑布流 + 灯箱分组）。
- `[article]` 元信息只有日期+评论数，可补阅读量（若有统计）。
- `ecod_sc_block` 非贪婪匹配，同标签嵌套（note 套 note）会错切——罕见，可在文档标注「同类短代码不可嵌套」。
- `.ecod_dl:hover .ecod_dl__icon { background:#fff }` 等少量硬编码色，暗色下无碍但不走 token，与 266 项 token 配对纪律不一致。
- `copybox__btn.is-done` 成功态文字 `#0f9d6f` 未配暗色变量（有底色补偿，可读）。

---

## 三、实测通过项（无需改动）

- 32 个标签在三端（1280 桌面 / 390 移动 / 暗色）渲染无重叠、无溢出、无横向滚动；移动端 netdisk 换行、cards 降单列、playlist 音频全宽均正确。
- tabs：`role=tablist/tab/tabpanel` + `aria-selected` + 左右方向键导航齐备。
- fold 用原生 `<details>`，无 JS 依赖。
- copy（事件委托 + is-done 反馈）、playlist 切歌、GitHub star（sessionStorage 6h 缓存 + 失败静默）工作正常。
- netdisk 15 盘识别 + `/go` 中转 + `data-no-pjax` + `nofollow`，比 Zibll（无网盘短代码）更强。
- hide/reply 服务端剥离内容，源码不可绕过。
- demo 页 HTML 结构校验：无 `<aclass` 粘连、无空段落、无非法嵌套（前两轮修复后回归正常）。

---

## 四、结论与建议顺序

整体评价：**短代码体系完成度约 80%，明显高于 Typecho 主题平均水平，设计语言统一（token 化、圆角/边距/字号一致性好），三端与暗色适配扎实**。与 Zibll 的差距不在已有组件质量，而在「可见性高级形态（付费/密码）、内容聚合（文章列表/相册）、外部内容解析（视频/音乐平台）」三块，以及编辑器/文档对已有能力的暴露不足。

建议修复顺序：
1. **P1-1/P1-2**（表情守卫 + 进度条数字）——几行代码，立即修；
2. **P1-3** hide 进编辑器（按钮 + 预览）；
3. **P1-4** 文档补全；
4. **P2-1/P2-2**（href 协议校验 + 双重转义）；
5. **P2-3/P2-4/P2-5**（结构残留 + 硬编码渐变 + url 引号）；
6. 能力扩展按 P2-6 表逐项排期（文章列表短代码优先级最高）。

---

## 五、修复记录（2026-10-29）

| 项 | 修复内容 | 位置 |
| --- | --- | --- |
| P1-1 | 入口守卫支持无方括号的纯表情正文（含 `<pre>` 保护），表情替换独立分支 | `core/shortcode.php` `ecod_shortcode_apply()` |
| P1-2 | 进度条数字改 `number_format` 方案（60/100/0/12.5 全对）；属性解析支持「首段裸值 + 具名属性」并存 | `core/shortcode-extra.php` `ecod_sc_progress()`；`core/shortcode.php` `ecod_sc_attrs()` |
| P1-3 | 编辑器新增「登录 / 等级可见」按钮 + 锁定块预览（与 PHP 同口径的 type/level/tip 解析）；`scAttrs` 同步支持裸值+具名混用 | `admin/editor.js` |
| P1-4 | 新增 `docs/shortcodes.md` 全量手册（32 标签 + 31 表情）；README §五改为速查索引 + 链接 | `docs/shortcodes.md`、`README.md` |
| P2-1 | 新增 `ecod_sc_url()` 白名单校验（http/https/mailto/tel/ftp/magnet/thunder + 相对路径），abtn/icard/anote/video/mp3/cloud/music 全部接入，非法协议降级 | `core/shortcode.php`、`core/shortcode-extra.php` |
| P2-2 | 属性值统一先 `html_entity_decode` 再输出转义，`&amp;` 双重转义消除 | `ecod_sc_attrs()` + 各输出点 |
| P2-3 | 新增 `ecod_sc_clean_inner()` 与组件间 `</p><p>` 清理，grid/cards 逐行书写不再残留段落标记 | `core/shortcode-extra.php` |
| P2-4 | `.ecod_mtitle__bar` 渐变尾改 `color-mix(in srgb, var(--ecod-primary) 45%, #fff)` | `assets/css/ecod.css` |
| P2-5 | `--ecod-cover:url("…")` 加引号（article/mp3/music 三处，组件模板本就用单引号） | `core/shortcode-extra.php` |
| 附带 | cloud/music/timeline 多行正文兼容 `</p><p>` 拆行（`ecod_sc_lines()`）；cloud 识别磁力/迅雷行；`[reply ]`、`[timeline ]` 带空格可命中 | 同上 |
| P2-6a | 新增 `[posts]` 文章列表：分类/标签/同分类相关（related）、date/hot/comment/random 排序、list/grid/title 三种模式、limit/column/desc/thumb/title/exclude/self 属性；默认排除当前文章；随机排序 PHP 侧洗牌；缩略图一次批量查询 | `core/shortcode-extra.php` `ecod_sc_posts()`；CSS §23.21 |
| P2-6b | `hide` 新增 `type="password"`：同页 POST + `hash_equals` 校验，密码错误时正文不进 HTML（与 Zibll 同思路，强于前端校验）；漏写 `pw` 时自动降级 login；摘要/搜索同步剥除 | `core/shortcode.php` `ecod_sc_hide()`；CSS §23.22 |
| P2-6c | `video` 平台解析：YouTube（watch/youtu.be/shorts/live）、Vimeo、腾讯视频、优酷 → iframe 播放器，直链仍走原生 `<video>` | `ecod_sc_video_embed()` |
| P2-6d | `mp3` 网易云外链：song/album/playlist 自动转官方 outchain 播放器（歌单 430px 高） | `ecod_sc_music_embed()` |
| 连带 | `[article]` 自定义字段列名 `value` → `str_value`（Typecho 1.3 schema，此前永远读不到）；`ecod_shortcode_strip()` 对 hide/reply 整段剥除（摘要与搜索不再泄漏隐藏内容）；`ecod_sc_archive` 上下文注入；编辑器新增 posts 按钮+预览、hide 密码预览；`.ecod_titem` 正文隔离；移动端网格降单列 | 多处 |

第二轮验证：PHP 沙箱平台解析 8 例 + 真实 DB 沙箱 posts 7 例（含坏分类、tag、随机、hot）+ 本地站临时文章实测（list/grid/title 渲染、真实链接、密码正确显示/错误不泄漏、YouTube/网易云 iframe 加载、桌面+390px 截图），31 组旧用例回归无告警；测试文章已删除。

验证：PHP 沙箱 31 组用例全过（含 `javascript:` 注入、`&amp;` 链接、逐行 grid/cards、磁力行、纯表情、`<pre>` 保护）；线上演示页实测进度条 72%/35% 正确、无 `<aclass` 粘连、无 `<p><div>` 非法嵌套；截图确认 mtitle 色条纯主题色渐变。

| 项 | 修复内容 | 位置 |
| --- | --- | --- |
| P3-相册 | 新增 `[gallery]` 相册图集：逐行「地址 \| 说明」或正文已有 `<img>`（Markdown 语法兼容），`column`(1–6)/`gap`/`ratio`(1/1、4/3、3/4、16/9、auto)/`mode=grid\|masonry`/`caption=hover\|always\|off`/`title`/`zoom`；每张图接入灯箱并按 `data-lb-group` 分组（同相册内翻页，与壁纸等互不串组）；地址走 `ecod_sc_image_src()` 白名单（防止把说明文字当图片）；空相册输出空串；摘要/搜索整段剥除 | `core/shortcode-extra.php` `ecod_sc_gallery()`；JS 灯箱分组；CSS §23.23 |
| P3-tooltip | `[tooltip text="…" place="top\|bottom"]`：纯 CSS 悬停/聚焦气泡，贴视口边缘时 JS 计算 `--shift` 横向回弹（箭头跟随） | `ecod_sc_tooltip()`；CSS §23.24；`Ecod.initInline()` |
| P3-badge | `[badge 颜色]` / `[badge text= color= style=soft\|solid\|outline]`：行内胶囊，颜色复用 `ecod_color_value()`（含主题色跟随） | `ecod_sc_badge()`；CSS §23.24 |
| P3-spoiler | `[spoiler tip="…"]`：模糊遮罩，点击/回车/空格展开再收起（`aria-expanded` 同步），悬停半透预览 | `ecod_sc_spoiler()`；CSS §23.24；`Ecod.initInline()` |
| P3-表情入口 | 后台编辑器工具栏新增表情按钮 → 弹出 8 列表情面板（31 个），点击把 `:code:` 插入光标处，面板可连续插入，Esc/滚轮/点外部关闭；表情表抽到 `core/data/emoji.php` 单一数据源（前台解析与后台选择器共用） | `admin/editor.js`、`admin/editor.php`、`admin/editor.css`、`core/data/emoji.php` |
| 连带 | 新增 `ecod_sc_inline()` 行内替换器（不吃前后空格与 `<p>`，区别于块级 `ecod_sc_block`）；灯箱分组选择器；`prefers-reduced-motion` 降级 | `core/shortcode-extra.php`、`assets/js/ecod.global.js` |

第三轮验证：PHP 沙箱 12 例（三种相册写法/空相册/非法地址、tooltip、badge 三态、spoiler、emoji 回归、摘要剥除）+ 临时文章线上实测（`/archives/68`，已删）：桌面 1280px 网格三列/两列 4:3 常显说明/瀑布流三列渲染正确，390px 移动端相册降两列无横向溢出，暗色对比度正常；agent-browser 实测灯箱按相册分组（1/6 与 1/2 互不串组）、剧透点击展开收起、悬停气泡显示；后台编辑器实测表情面板开合/插入（31 项，图片 31/31 命中）、四个新按钮插入、相册按钮拉起媒体库多选并生成代码；测试文章、测试图片、临时管理员均已清理。
