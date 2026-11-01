# 内置浏览器引导（微信 / QQ / 企业微信 / 钉钉）

> 状态：已实现。参考小红书网页版「使用默认浏览器访问」弹层。

## 要解决的问题

微信公众号、QQ 聊天窗口、企业微信、钉钉点开链接时用的是 App 内置 WebView：没有地址栏、
没有「在新标签页打开」，长文阅读、复制外链、下载都不顺手，读者常常直接退出。

## 触发判定

`core/site/inapp.php` 按 UA 判定，顺序不能换：

| 环境 | UA 特征 | 键 |
|---|---|---|
| 企业微信 | `wxwork` | `work` |
| 微信 | `micromessenger`（且不含 wxwork） | `wechat` |
| QQ | ` QQ/x.y` | `qq` |
| 钉钉 | `dingtalk` | `ding` |

- 企业微信 UA 同时含 `MicroMessenger`，必须先判 `wxwork`；
- QQ 浏览器（`MQQBrowser`）是独立 App，**不算**内置环境，只认 ` QQ/数字`。

不命中任何环境时组件直接 `return`，HTML 里没有任何浮层标记 —— 桌面与普通浏览器零影响，
桌面端因此也不需要额外做响应式兜底（CSS 仍保留了一条窄屏微调）。

## 结构（components/header/inapp.php）

```
.ecod_inapp                 遮罩 + 居中（fixed inset:0，与公告弹窗同一套写法）
└ .ecod_inapp__panel        352px 卡片
  ├ .ecod_inapp__x          右上角关闭（压在图上方预留的 40px 空带里）
  ├ .ecod_inapp__demo       CSS 画的迷你浏览器：地址胶囊 + 右上角菜单（主色小方块）+ 手指
  ├ .ecod_inapp__title      弹窗标题（后台可改，默认「使用默认浏览器访问」）
  ├ .ecod_inapp__desc       副标题（后台可改）
  ├ .ecod_inapp__hint       环境相关的操作路径：在微信里：右上角「···」→ 在浏览器打开
  └ .ecod_inapp__bar        地址栏：锁图标 + host +「复制链接」（整行可点）
```

示意图是纯 CSS + 内联 SVG，没有截图资源，跟随主题主色与明暗令牌。

## 交互

- 打开：`Ecod.dialogOpen()` 统一承担 Esc、焦点陷阱、滚动锁（`assets/js/ecod.global.js` 的 `Ecod.initInApp`）；
- 关闭：X、遮罩点击、Esc 三条路径都走同一个 `close()`，同时记频率；
- 复制：`Ecod.u.copyText()`（clipboard 失败回退 `execCommand`），成功后按钮变「已复制」并弹 toast；
- 浮层挂在 `#ecod-shell` 之外（`public/header.php`），PJAX 换页不重建、也不重复弹。

## 后台

外观 → 内置浏览器引导：

| 字段 | 默认 | 说明 |
|---|---|---|
| `EInApp` | on | 总开关 |
| `EInAppWechat` / `EInAppQQ` / `EInAppWork` / `EInAppDing` | on | 分环境勾选 |
| `EInAppTitle` / `EInAppDesc` | 空 | 留空用默认文案 |
| `EInAppRate` | session | session / day / always，前端用 sessionStorage / localStorage 记忆 |

## 验收清单

- 微信 UA（iPhone / Android）→ 有浮层；桌面 Chrome → 无浮层；
- QQ / 企业微信 / 钉钉 UA → 有浮层且 hint 文案跟随环境；MQQBrowser UA → 无浮层；
- 关一次后同会话刷新不再弹；`day` 改按自然日；`always` 每次刷新都弹；
- 暗色模式文字与卡片底色对比正常；
- 后台关总开关或取消某环境后，前台对应 UA 不再输出标记。

## 相关文件

- `core/site/inapp.php`（UA 判定 + 渲染数据）
- `components/header/inapp.php`（标记）
- `public/header.php`（在 `#ecod-shell` 之前 include）
- `assets/css/ecod.css` §51（样式）
- `assets/js/ecod.global.js` `Ecod.initInApp`（弹层与复制）
- `core/admin/config.php`（字段与分组）
