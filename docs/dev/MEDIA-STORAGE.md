# Ecod Theme · 资源库管理与对象存储设计

> 依据：现状审计（media.php / assets/admin/media.js / core/content/thumbs.php / var/Widget/Upload.php）
> 参考：AliOssForTypecho V2.3.0（kalshen2018）、TEMediaFolder、typecho-plugin-autowebp
> 日期：2026-10-30
> 状态：设计稿（未实施）

---

## 0. 一页结论

主题自建**存储驱动层**（`core/media/storage.php`）+ **上传/管理 API**（扩展既有 `media.php`，不新增独立入口）+ **上传即转 WebP**（复用 thumbs.php GD 管线）+ **资源库管理台**（升级现有选图弹层）。

附件元数据仍写入 Typecho `contents` 表（`type='attachment'`），保证正文引用、`isImage` 判定、原生管理后台列表全部兼容。

### 0.1 已拍板决策

| # | 决策 | 理由 |
|---|---|---|
| D1 | 主题内置存储驱动层，不依赖第三方 OSS 插件 | WebP / 缩略图 / 配额需深度整合；同时保留 `attachmentHandle` 钩子兼容（装了第三方插件仍不冲突） |
| D2 | 不新增独立 PHP 入口，扩展 `media.php` action | 避开 nginx 白名单四同步坑（.htaccess / README / 线上 nginx / ARCHITECTURE.md） |
| D3 | 上传即转 WebP（同步），保留原图可选 | 列表缩略图链路（`thumbs.php`）已验证 GD+imagewebp 管线可靠 |
| D4 | 附件行直写 `contents` 表 | 正文插入、`ecod_extract_images()`、原生后台附件列表零改动 |

---

## 1. 现状与 Gap

| # | 现状 | 证据 |
|---|---|---|
| 1 | `media.php` 只读（list/ping），无上传/删除/重命名 | `media.php:101-105` |
| 2 | 上传完全走 Typecho 原生 `/action/upload`，落 `usr/uploads/YYYY/MM/<crc32>.<ext>` | `admin/editor.php:93` |
| 3 | 已有 WebP 缩略图能力（GD，质量 82，失败 302 回原图），但只服务列表封面 | `core/content/thumbs.php:196-243,248-250` |
| 4 | `ecod_thumb_local_path()` 只认 `/usr/uploads/` 前缀，对象存储 URL 直接失效 | `thumbs.php:117-139` |
| 5 | 格式白名单在 Typecho 全局设置 `attachmentTypes`（默认 `@image@`=gif,jpg,jpeg,png,tiff,bmp,webp），主题无入口 | `var/Widget/Options.php:744-754` |
| 6 | 两套媒体库白名单不一致（media.php 含 svg 不含 tiff 兜底；widget.php 反之） | `media.php:216` / `widget.php:456` |
| 7 | 选图器列表原图直出，无缩略图 | `assets/admin/media.js:431` |
| 8 | 上传流程零图片处理；头像上传亦原样落盘 | `api.php:1362` |
| 9 | `media.php` SELECT 里有 `parent` 但未输出，无「被引用」视图 | `media.php:184` |

---

## 2. 参考项目拆解

| 项目 | 借鉴点 | 不采纳 |
|---|---|---|
| AliOssForTypecho V2.3.0 | 钩子四件套（uploadHandle/modifyHandle/deleteHandle/attachmentHandle）；仅 OSS / OSS+本地双写模式；CDN 自定义域名、内网外网 Endpoint、错误日志 | 阿里云单云厂商绑定；插件式配置面板 |
| TEMediaFolder | 多驱动统一预览（本地/腾讯COS/阿里OSS/兰空图床）；目录分组概念 | 依赖兰空图床等外部服务 |
| typecho-plugin-autowebp | 上传自动转 WebP、质量 80、随机命名、兼容本地和 OSS 队列 | 质量不可配、无原图保留策略 |

**Typecho 1.3 钩子注意**：AliOssForTypecho V2.3.0 专门适配了 1.3 的钩子签名变更。本主题 D4 直写 `contents` 绕过 `Upload::uploadHandle()`，不受钩子签名影响；`media.php:89-90` 已有 `Plugin::init()` 调用，`attachmentHandle` 兼容第三方插件的行为保留。

---

## 3. 存储驱动层

### 3.1 接口定义（新文件 `core/media/storage.php`）

```php
interface EcodMediaStorage
{
    public function id(): string;                 // local | oss | cos | s3
    public function put(string $tmpPath, string $key): string;  // 返回访问 URL
    public function delete(string $key): bool;
    public function url(string $key): string;
    public function thumbUrl(string $url, string $preset, int $w, int $h): string;
    public function available(): bool;            // 配置完整性 + 扩展依赖探测
}
```

`ecod_media_storage()` 返回当前驱动单例（按 `EMediaStorage` option 路由，驱动不可用自动回退 local 并在后台提示）。

### 3.2 驱动矩阵

| 驱动 | 签名/SDK | 缩略图（thumbUrl） | 备注 |
|---|---|---|---|
| `local` | —（现状行为） | 走 `ecod_thumb_url()` 本地 GD 管线 | 默认，零迁移 |
| `oss` 阿里云 | vendored aliyun-oss-php-sdk（约 40 文件，纯 PHP） | `?x-oss-process=image/resize,m_cover,w_W,h_H/format,webp` | 支持内网 Endpoint、自定义 CDN 域名 |
| `cos` 腾讯云 | vendored cos-sdk-v5 精简（或 HTTP 签名直实现） | `?imageMogr2/thumbnail/!WxHr/format/webp` | 同上 |
| `s3` 兼容 | 自实现 SigV4 签名（约 200 行，无 SDK） | 无统一图片处理 → 回退 302 原图（沿用现降级哲学） | 覆盖 MinIO / Cloudflare R2 / 七牛 S3 兼容 / AWS |

又拍云（REST 签名非 S3 系）列入二期。

### 3.3 对象命名与双写

- key 保持 Typecho 风格：`YYYY/MM/<crc32>.<ext>`（转 WebP 后为 `.webp`，meta 记 `source_ext`）。
- 双写模式（`EMediaDualWrite=on`）：本地保留一份 + 云端一份，回滚零成本；默认关。

### 3.4 配置项（主题设置，`EMedia*` 前缀，挂 `core/admin/config.php`）

| 项 | 默认 | 说明 |
|---|---|---|
| `EMediaStorage` | `local` | local / oss / cos / s3 |
| `EMediaOss*` / `EMediaCos*` / `EMediaS3*` | 空 | AccessKeyId / Secret / Bucket / Endpoint / 自定义域名 / 内网开关 |
| `EMediaDualWrite` | `off` | 云端+本地双写 |
| `EMediaMaxBytes` | `16777216` | 单文件上限（16MB），后端硬校验 |
| `EWebpConvert` | `on` | 上传即转 WebP |
| `EWebpQuality` | `82` | 与 thumbs.php 现值一致 |
| `EWebpKeepOriginal` | `off` | 保留原图文件（多占一份存储） |
| `EMediaAllowExt` | 空 | 追加扩展名（见 §5），仅展示+引导，非硬开关 |

---

## 4. 上传链路改造（扩展 `media.php`）

### 4.1 新增 action（全部 POST + CSRF + `pass('editor')`）

| action | 功能 |
|---|---|
| `upload` | 上传（单文件/多文件），返回 `{url, cid, webp, bytes}` |
| `delete` | 删除（先检测正文引用，被引用返回 warning 需 `force=1`） |
| `rename` | 改 `contents.title`（不改文件名，URL 不变） |
| `convert` | 存量图片转 WebP（单张），批量由管理台循环调 |
| `list`（扩展） | 响应增加 `thumb` / `parent` / `refs`（引用计数）字段 |

CSRF：与 `/action/upload` 同源取 `$security->getToken($referer)`（`admin/editor.php:93` 同款），前端 `media.js` 从 `window.ECOD_MEDIA` 携带。

### 4.2 上传处理管线（`ecod_media_upload_handle()`）

```
1. 鉴权 + CSRF + 体积上限
2. 扩展名统一白名单（§5.1）+ finfo 内容嗅探 MIME（修复 Typecho 只验扩展名的缺口）
3. GD 可解码？
   ├─ 是 且 EWebpConvert=on 且 源非 webp/avif/gif(动图)/svg
   │    → imagewebp(quality=EWebpQuality) → 新 tmp → key 用 .webp
   ├─ 是 且 EWebpKeepOriginal=on → 原图额外 put 一份（key 加 -orig 后缀）
   └─ 否（heic/tiff 等）→ 原样上传
4. 驱动 put()
5. 直写 contents 附件行（对齐 Upload::uploadHandle 的行结构：
   type=attachment, title, slug, status=publish, text=JSON{mime,size,driver,key,source_ext}, parent=cid）
6. 返回 JSON
```

直写函数 `ecod_media_attachment_insert()` 放 `core/media/storage.php`，字段结构与 `var/Widget/Upload.php:375-423` 产物逐字段对齐（审计时已核对）。

### 4.3 前端改造（`assets/admin/media.js`）

- 上传按钮/拖拽/粘贴改指 `media.php?action=upload`（带 CSRF），保留原图 `/action/upload` 作为 local 驱动下的兼容路径（EMediaStorage=local 且 EWebpConvert=off 时不改行为）。
- 列表缩略图：`item.thumb` 存在则用之，否则原图（`:431` 直出修复）。
- 新增删除（单项+批量多选）、重命名、详情侧栏增加「被 N 篇文章引用」。

---

## 5. 图片格式扩展

### 5.1 统一白名单（新函数 `ecod_media_ext_allowed()`，收编两处不一致）

| 格式 | 默认 | WebP 转换 | 说明 |
|---|---|---|---|
| jpg/jpeg/png/webp | 允许 | webp 源跳过 | 基础集 |
| gif | 允许 | 跳过（动图） | |
| bmp/tiff | 允许（Typecho 默认集） | 转为 webp | |
| avif | 允许 | 跳过 | GD 有 `imageavif` 时 thumbs 可裁剪，探测降级 |
| svg | **默认拒绝**（管理台可开 `EMediaAllowSvg=on`） | 跳过 | XSS 风险；Typecho `isImage` 白名单本就排除 svg（`Base/Contents.php:741-743`），开启后仅编辑器+可用且前台输出加 `sanitized` 提示 |
| heic/heif | 默认拒绝，`EMediaAllowExt` 显式添加 | GD 不支持解码 → 原样上传 | 需 Imagick 才能预览缩略图，无则列表显示格式占位图标 |
| jxl | 不纳入 | — | 生态不成熟，明确排除 |

### 5.2 Typecho 全局 `attachmentTypes` 引导

主题设置页保存时检测：`EMediaAllowExt` / svg / heic 中任何格式未包含在 Typecho 全局白名单 → 显示检测条 + 「去设置」链接（后台 `options-general.php` 附件类型勾选）。**不代写全局设置**（主题无权限、易踩序列化红线）。

---

## 6. WebP 转换

| 维度 | 方案 |
|---|---|
| 时机 | 上传即转（同步）；单张 ≤16MB 的 GD 解码+编码耗时实测量级 <1s，可接受 |
| 质量 | `EWebpQuality` 默认 82（与 thumbs.php:234 一致） |
| 透明通道 | 复用 thumbs.php:221-224 的 alphablending/savealpha 处理 |
| 动图 gif / svg / avif | 跳过转换 |
| 失败降级 | 转换失败→原格式上传，meta 记 `webp_failed=1`，管理台可重试（`action=convert`） |
| 存量批量 | 管理台「全部转 WebP」入口，逐张调用，带进度与失败清单 |
| 列表缩略图 | `thumbs.php` 管线不动（本地缓存 `.thumbs/`）；OSS/COS 走驱动 `thumbUrl()`，S3 系 302 原图 |
| 宽高防 CLS | OSS/COS 响应头无宽高 → `ecod_img_attrs()`（`components/media.php:352-367`）对远端图返回空，前端 img 需容器固定宽高比（卡片 CSS 已有 `aspect-ratio` 的模式不受影响；本期给无 `aspect-ratio` 的位置补占位背景） |

---

## 7. 资源库管理台 UI（升级 `assets/admin/media.js`）

```
┌──────────────────────────────────────────────────────────────┐
│  媒体库                                          [ × ]       │
├──────────────────────────────────────────────────────────────┤
│ [🔍 搜索…] [格式▾][存储▾] 共 N 张 · 占用 XX MB   [批量][上传] │
├───────────────────────────────────┬──────────────────────────┤
│ ┌────┐ ┌────┐ ┌────┐ ┌────┐      │  预览图（thumb 或占位）   │
│ │img │ │img │ │img │ │img │      │  文件名 / 尺寸 / 大小     │
│ └────┘ └────┘ └────┘ └────┘      │  格式徽标 WEBP / SVG/HEIC │
│ ┌────┐ ┌────┐ ┌────┐ ┌────┐      │  存储: OSS·bucket  [复制] │
│ │img │ │img │ │img │ │img │      │  被 N 篇文章引用          │
│ └────┘ └────┘ └────┘ └────┘      │  日期                     │
│   ☑ 多选角标 · 🔍 放大 · lazy     │  [改名] [转WebP] [删除]   │
├───────────────────────────────────┴──────────────────────────┤
│  已选 3 张                     [取消]  [批量转WebP] [确定]    │
└──────────────────────────────────────────────────────────────┘
```

- 筛选：格式（webp/svg/heic…）、存储来源（local/OSS…）。
- 批量条：多选后浮出「转 WebP / 删除」。
- 用量统计：列表响应带 `total` / `bytes`（按驱动分组）。
- 灯箱、无限滚动、键盘操作全部保留现状。

---

## 8. 安全清单

1. 所有写 action：CSRF token + `pass('editor')` + POST-only。
2. 上传：扩展白名单 **且** finfo 内容嗅探双校验（堵 Typecho 仅验扩展名的口子）。
3. 路径穿越：key 由服务端生成（crc32+date），不接受客户端传 key。
4. svg 默认拒绝；开启后前台输出不做自动内联。
5. 删除：先引用检测（扫 `contents.text` LIKE 该 URL），被引用需 force。
6. 错误日志：上传/云端失败写 `usr/uploads/.ecod-media.log`（参照 AliOssForTypecho 日志特性），后台可查看，自动截断 1MB。
7. 密钥存主题 JSON option（Typecho 1.3 存 JSON），**严禁 serialize 读写**（项目红线）。

---

## 9. 分期

| 期 | 内容 |
|---|---|
| P1 | 驱动层 + local 驱动 + 统一白名单 + 上传/删除/改名 API + 管理台批量/删除/缩略图 |
| P2 | WebP 上传即转 + 存量批量转换 + `thumb` 字段接入列表 |
| P3 | OSS / COS 驱动 + CDN thumbUrl + 双写 |
| P4 | S3 兼容驱动 + 引用计数视图 + 用量统计 |

## 10. 验收矩阵（摘要）

| 场景 | 期望 |
|---|---|
| local 驱动上传 jpg（EWebpConvert=on） | 落 `.webp`，正文插入 URL 为 webp，`contents` 行完整 |
| 上传 gif | 不转换，动图保留 |
| 上传 heic（未开允许） | 400，提示去设置 |
| 开 OSS 上传 png | OSS 有对象，contents.text 含 driver/key，后台原生列表可见 |
| OSS 下列表缩略图 | URL 带 `x-oss-process` 参数，宽高占位不 CLS |
| 删除被引用图片 | 返回 warning，force=1 才删 |
| 卸载第三方 OSS 插件场景 | `attachmentHandle` 行为不回归（media.php Plugin::init 保留） |
