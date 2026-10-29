# Ecod 主题 · 短代码手册

> 适用版本：短代码 37 个标签 + 31 个表情代码
> 全部在正文 HTML 层面替换；`<pre>` / `<code>` 内部不生效，代码示例里可以放心写方括号。
> 编辑器侧边栏「短代码」菜单可一键插入，右侧预览实时查看效果。

## 通用写法

```text
[标签名 属性]内容[/标签名]      成对标签
[标签名 属性]                   自闭合标签
```

属性三种写法，可以混用：

```text
[note warn]                       裸值（等价于 [note type="warn"]）
[progress percentage="60"]        具名属性，值建议加双引号
[progress 60 label="整体进度"]     首段裸值 + 具名属性并存
```

---

## 一、提示与强调

| 标签 | 属性 | 说明 |
| --- | --- | --- |
| `note` | `type` / `title` | 带标题的提示块 |
| `alert` | `type` / `title` | `note` 的别名组，额外接受 `success` / `warning` / `error` |
| `message` | `type` | 单行消息条 |
| `callout` | `color` / `title` / `icon` | 自定义颜色的强调框 |
| `anote` | `author` / `icon` / `href` | 作者注 / 通知条，`href` 为 `http(s)` 时整条可点 |

```text
[note type="tip" title="提示"]正文[/note]
[note warn]裸值写法，等价于 type="warn"[/note]

[alert type="success" title="发布成功"]已上线 v2.0[/alert]
[alert warning]这里用别名也行[/alert]

[message type="error"]构建失败：缺少依赖[/message]

[callout color="#7c4dff" title="延伸阅读" icon="bulb"]内容[/callout]

[anote author="Ecod" icon="info" href="https://example.com"]新版本已发布[/anote]
```

`note` / `alert` 的 `type`：`info`（默认）、`tip`、`warn`、`danger`、`dark`。
`message` 的 `type`：`info`（默认）、`success`、`warning`、`error`。

---

## 二、排版元素

| 标签 | 属性 | 说明 |
| --- | --- | --- |
| `mtitle` | `title` | 带色条的小节标题 |
| `copy` | `label` | 一键复制块（右上角复制按钮） |
| `progress` | `percentage` / `value` / `color` / `label` | 进度条，0–100 |
| `abtn` | `href` / `icon` / `color` / `style` / `radius` | 按钮 |
| `dotted` | — | 虚线分隔 |
| `lamp` | `type` / `color` / `text` | 状态灯（行内） |
| `task` | `done` | 任务勾选框（行内） |
| `mark` | — | 高亮（行内） |
| `kbd` | — | 键盘按键（行内） |
| `tooltip` | `text` / `tip` / `place` | 悬停提示气泡（行内） |
| `badge` | `text` / `color` / `style` | 行内标签胶囊（行内） |
| `spoiler` | `tip` | 剧透遮罩，点击展开（行内） |

```text
[mtitle title="安装步骤"]

[copy label="安装命令"]
npm i ecod-theme
[/copy]

[progress percentage="60" label="完成度"]
[progress 60 label="裸值 + 属性混用"]
[progress value="12.5" color="green"]

[abtn href="https://example.com" icon="download" color="primary"]立即下载[/abtn]
[abtn href="/archives" style="ghost" icon="list"]全部文章[/abtn]

[dotted]

按 [kbd]Ctrl[/kbd] + [kbd]S[/kbd] 保存，这是 [mark]重点[/mark]。
服务状态 [lamp green] 运行中
[lamp red text="离线"]

[task done] 已完成
[task] 未完成

悬停看解释 [tooltip text="这是补充说明的文字"]术语[/tooltip]
[tooltip tip="气泡在下方" place="bottom"]悬停我[/tooltip]

状态 [badge green]稳定[/badge] [badge red style="solid"]HOT[/badge] [badge color="purple" style="outline"]beta[/badge]

结局是 [spoiler]主角其实没死[/spoiler]。
[spoiler tip="点一下看剧透"]他是卧底[/spoiler]
```

- `progress` 数字支持 `60`、`"60%"`、`12.5`；`color` 取 `primary`（跟随后台主题色）、`green`、`red`、`yellow`、`blue` 或 `#hex`。
- `abtn` 的 `style` 取 `fill`（默认）/ `ghost`；`radius` 可写 `8px`、`99px` 等任意半径。
- `lamp` 的 `type` 与 `color` 等价，取颜色名。
- `tooltip` 的 `text`（或 `tip`）为气泡内容，`place` 默认 `top`，可写 `bottom`；气泡贴近视口边缘时会自动横向回弹。
- `badge` 的颜色写法与 `lamp` 相同（裸值即颜色）；`style` 取 `soft`（默认）/ `solid` / `outline`。
- `spoiler` 默认模糊遮罩，点击（或回车 / 空格）展开、再点收起；`tip` 为悬停与无障碍提示文案。

---

## 三、折叠 / 选项卡 / 时间线

| 标签 | 属性 | 说明 |
| --- | --- | --- |
| `fold` | `title` | 原生 `<details>` 折叠，无 JS 依赖 |
| `tabs` + `tab` | `tab.title` | 选项卡，支持键盘左右方向键 |
| `timeline` | — | 时间线，每行「时间 \| 内容」 |

```text
[fold title="点开看答案"]折叠里的内容，支持任意 HTML 与其它短代码[/fold]

[tabs]
[tab title="安装"]第一步…[/tab]
[tab title="配置"]第二步…[/tab]
[/tabs]

[timeline]
2026-01 | 项目启动
2026-03 | 内测
2026-06 | 正式上线
[/timeline]
```

---

## 四、卡片与网格

| 标签 | 属性 | 说明 |
| --- | --- | --- |
| `cards` | `column`(1–4) / `title` | 带标题的卡片组 |
| `grid` | `column`(1–4) / `gap`(0–40) | 纯网格容器，可放任意内容 |
| `card` | `title` / `desc` / `icon` / `href` / `tag` / `color` | 信息卡；有 `href` 时整卡可点 |

```text
[cards column="2" title="相关资源"]
[card title="使用文档" desc="从零开始" icon="file" href="https://example.com"]
[card title="更新日志" desc="变更记录" icon="list" href="/changelog"]
[/cards]

[grid column="3" gap="16"]
[card title="列一" tag="New"]
[card title="列二"]
[card title="列三"]
[/grid]
```

- `href` 仅接受 `http(s)://`、`//`、站内 `/` 开头；其它协议（如 `javascript:`）会降级为不可点卡片。
- 卡片组内的 `[card]` 之间不要留空行，避免 Markdown 拆段。

---

## 五、下载与网盘

| 标签 | 属性 | 说明 |
| --- | --- | --- |
| `download` | `url` / `name` / `size` / `type` | 单条下载卡，自动识别网盘并显示品牌色 |
| `cloud` | `url` / `pwd` / `title`，或多行写法 | 网盘卡（单条 / 多条列表） |

```text
[download url="/usr/uploads/demo.zip" name="演示包" size="12.4 MB"]更新说明[/download]
[download url="https://pan.baidu.com/s/1abc" name="完整包"]提取码见内[/download]

[cloud]
https://pan.baidu.com/s/1abc | 8x2k | 百度网盘完整包
https://pan.quark.cn/s/xyz | | 夸克备用
magnet:?xt=urn:btih:xxxx | | 磁力
[/cloud]
```

多行写法每行 `链接 | 提取码 | 说明`，后两项可留空。

自动识别 15 种：百度网盘、阿里云盘、夸克网盘、蓝奏云、天翼云盘、123 云盘、OneDrive、Google Drive、GitHub、奶牛快传、城通网盘、移动云、文叔叔、迅雷、磁力链接。
`type` 可强制指定（`baidu` / `ali` / `quark` / `lanzou` / `tianyi` / `pan123` / `onedrive` / `gdrive` / `github` / `cow` / `ctfile` / `caiyun` / `wss` / `thunder` / `magnet`）。
站外链接在开启「外链中转」后会自动走 `/go/` 跳转页。

---

## 六、内容引用与可见性

| 标签 | 属性 | 说明 |
| --- | --- | --- |
| `posts` | `category` / `tag` / `limit` / `order` / `mode` / `column` / `title` / `desc` / `thumb` / `related` / `exclude` / `self` | 文章列表，按分类、标签或同分类取多篇 |
| `article` | `id`（文章 cid）/ `img` | 引用文章卡，自动抓标题、摘要、日期 |
| `github` | `repo` / `desc` | GitHub 仓库卡，前端异步取 Star |
| `reply` | — | 评论可见（留下过已审核评论的访客可见） |
| `hide` | `type` / `level` / `tip` / `pw` | 登录 / 评论 / 等级 / 密码可见 |

```text
[posts limit="5" title="延伸阅读"]
[posts category="tech" order="hot" limit="4"]
[posts related="1" mode="grid" column="2" desc="1"]

[article id="4"]
[article id="4" img="https://example.com/cover.jpg"]

[github repo="owner/repo" desc="仓库说明"]

[reply]这段内容只有留下过评论的访客能看到。[/reply]

[hide type="login"]仅登录用户可见[/hide]
[hide level="3"]需要评论等级 Lv.3[/hide]
[hide type="comment" tip="先评论一下就能看"]…[/hide]
[hide type="password" pw="1234"]输入 1234 后可见[/hide]
```

**`posts` 列表**

| 属性 | 取值 | 默认 | 说明 |
| --- | --- | --- | --- |
| `category` | 分类缩略名或 mid | — | 只取该分类 |
| `tag` | 标签缩略名或 mid | — | 只取该标签 |
| `related` | `1` | — | 取当前文章的首个分类，做「相关阅读」 |
| `limit` | `1`–`20` | `5` | 条数 |
| `order` | `date` / `hot` / `comment` / `random` | `date` | 最新 / 阅读量 / 评论数 / 随机 |
| `mode` | `list` / `grid` / `title` | `list` | 横排列表 / 两列网格 / 纯标题行 |
| `column` | `1`–`3` | `2` | `grid` 模式的列数（移动端强制单列） |
| `title` | 文本 | — | 列表上方的分组标题 |
| `desc` | `1` | 关 | 显示两行摘要 |
| `thumb` | `0` | 开 | 关闭封面 |
| `exclude` | `cid,cid` | 当前文章 | 排除指定文章 |
| `self` | `1` | 关 | 允许把当前文章也列进去 |

- `posts` 默认排除当前文章；封面取正文首图 → 自定义字段 `thumb` → 后台缩略图池，都没有时显示标题首字色块。
- `hide` 的 `type`：`login`（默认）、`comment` / `reply`、`level`（配合 `level="1"~"7"`）、`password`（配合 `pw="…"`）。
- 除 `password` 外，条件不满足时**服务端不输出内容 HTML**，查看源码也拿不到。
- `password` 走同页 POST 校验：密码错误时正文根本不会进入 HTML；校验通过后展示。写了 `type="password"` 却漏掉 `pw`，会按 `login` 处理，避免内容明文外泄。
- `hide` / `reply` 的内容不会进入列表摘要与搜索结果，只留空位。
- 同类短代码不可自我嵌套（如 `note` 套 `note`），需要嵌套时改用 HTML。

---

## 七、媒体

| 标签 | 属性 | 说明 |
| --- | --- | --- |
| `gallery` | `column` / `gap` / `ratio` / `mode` / `caption` / `title` / `zoom` | 相册图集，逐行「地址 \| 说明」，点击接灯箱翻页 |
| `bilibili` | `bvid` / `page` | B 站播放器（懒加载 + sandbox） |
| `video` | `url` / `poster` / `name` | 直链视频，或平台链接自动转播放器 |
| `mp3` | `url` / `name` / `cover` | 单曲音频卡，网易云链接自动转外链播放器 |
| `music` | `title`，多行「链接 \| 歌名 \| 封面」 | 播放列表 |

```text
[gallery column="3" ratio="1/1" title="作品集"]
https://example.com/a.jpg | 说明文字
https://example.com/b.jpg
[/gallery]

[gallery mode="masonry" column="3"]…[/gallery]      瀑布流（保持原始宽高比）
[gallery column="2" ratio="4/3" caption="always"]   说明常显
[gallery zoom="0"]                                  不接灯箱

[bilibili bvid="BV1xx411c7mD" page="1"]

[video url="https://example.com/demo.mp4" poster="https://example.com/p.jpg" name="演示"]
[video url="https://v.qq.com/x/page/a0032abcd12.html" name="腾讯视频"]
[video url="https://www.youtube.com/watch?v=dQw4w9WgXcQ"]
[video url="https://v.youku.com/v_show/id_XNTk1MjM0NTY3OA==.html"]

[mp3 url="https://example.com/a.mp3" name="曲名" cover="https://example.com/c.jpg"]
[mp3 url="https://music.163.com/#/song?id=1901371647"]

[music title="本期歌单"]
https://example.com/a.mp3 | 歌名一 | https://example.com/c1.jpg
https://example.com/b.mp3 | 歌名二
[/music]
```

`video` 会按链接自动识别平台：YouTube（`watch?v=` / `youtu.be` / `shorts`）、Vimeo、腾讯视频（`v.qq.com`）、优酷（`v.youku.com`），识别不出才当直链用 `<video>`。
`mp3` 识别网易云：单曲 `song?id=`、专辑 `album?id=`、歌单 `playlist?id=`，其余按直链 `<audio>`。

**`gallery` 相册**

| 属性 | 取值 | 默认 | 说明 |
| --- | --- | --- | --- |
| `column` | `1`–`6` | `3` | 列数（移动端最多两列，`column="1"` 保持单列） |
| `gap` | `2`–`24`（px） | `8` | 格子间距 |
| `ratio` | `1/1` / `4/3` / `3/4` / `16/9` / `9/16` / `4/5` / `auto` | `1/1` | 网格裁切比例；`auto` 按原图比例 |
| `mode` | `grid` / `masonry` | `grid` | 网格（裁切对齐）/ 瀑布流（原始比例，`ratio` 失效） |
| `caption` | `hover` / `always` / `off` | `hover` | 说明浮出 / 常显 / 不显示 |
| `title` | 文本 | — | 相册标题行 |
| `zoom` | `0` | 开 | 关闭灯箱，图片不可点 |

- 每行写「图片地址 | 说明」，说明可省略；正文里已有的 `<img>`（Markdown `![]()`）也会被收进相册，`alt` 即说明。
- 地址支持 `http(s)://`、站内 `/` 开头，或带图片后缀的相对路径。
- 点击图片打开灯箱，同一相册内左右翻页、移动端滑动切换；不同相册之间互不串组。
- 相册正文不进入列表摘要与搜索结果。

---

## 八、表情

正文里直接写代码即可，无需方括号（后台编辑器工具栏的表情按钮可点选插入）：

```text
今天真顺利 :smile: 收工 :beer:
```

| 代码 | 含义 | 代码 | 含义 | 代码 | 含义 |
| --- | --- | --- | --- | --- | --- |
| `:?:` | 疑问 | `:razz:` | 调皮 | `:sad:` | 难过 |
| `:evil:` | 邪恶 | `:naughty:` | 淘气 | `:!:` | 惊恐 |
| `:smile:` | 微笑 | `:oops:` | 尴尬 | `:neutral:` | 无语 |
| `:cry:` | 大哭 | `:mrgreen:` | 坏笑 | `:grin:` | 咧嘴 |
| `:eek:` | 惊讶 | `:shock:` | 震惊 | `:???:` | 晕 |
| `:cool:` | 酷 | `:lol:` | 大笑 | `:mad:` | 生气 |
| `:twisted:` | 扭曲 | `:roll:` | 白眼 | `:wink:` | 眨眼 |
| `:idea:` | 灵感 | `:despise:` | 鄙视 | `:celebrate:` | 庆祝 |
| `:watermelon:` | 吃瓜 | `:xmas:` | 圣诞 | `:warn:` | 警告 |
| `:rainbow:` | 彩虹 | `:loveyou:` | 爱你 | `:love:` | 爱心 |
| `:beer:` | 啤酒 | | | | |

未登记的代码（如 `:heart-less:`）按原样输出，可用于演示或占位。

---

## 九、已知限制

- 同类短代码不可自我嵌套：`ecod_sc_block` 用非贪婪匹配，`[note]…[note]…[/note]…[/note]` 会错切。
- Markdown 模式下，块级短代码（`cards` / `grid` / `tabs` / `timeline` / `gallery`）独占一行、内部不留空行，否则会被拆成多个段落。
