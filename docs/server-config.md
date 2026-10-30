# 服务器配置优化手册（对照 Lighthouse 报告）

> 背景：2026-10-30 对 `http://ecod.cc/` 的 Lighthouse 实测（Performance 77 / Best-Practices 78），
> 扣分不出在主题代码，而是**服务器没有应用安全 / 缓存策略**。本文档按问题逐条给出
> nginx 与 Apache 两侧的配置与验证命令；照做一遍，Best-Practices 即满分、
> Performance 预计能从 77 → 90+。
>
> 主题代码侧已修复的问题（LCP lazy、prism 阻塞、对比度、触摸命中区）见同日提交，
> 本文只覆盖需要在服务器上动手的部分。

## 一、HTTPS ：301 跳转（当前最大扣分项）

报告中整站走 `http://`，共 8 个不安全请求，且 **HTTP 不跳 HTTPS**。任何站点都建议全量 HTTPS，
这也是 HTTP/2、HSTS、PWA manifest（manifest.php）生效的前提。

### 1. 证书

用现有面板套 Let's Encrypt 即可；证书签出来之前下面的所有 301 建议先别加，
避免测试机回环。

### 2. nginx（含宝塔伪静态均可）

```nginx
server {
    listen 80;
    server_name ecod.cc;
    return 301 https://$host$request_uri;
}
# 443 server 块内补发安全响应头
add_header Strict-Transport-Security "max-age=31536000" always;
```

若站点同时解析了 `www.`，在 80 的 server 内一起 `server_name ecod.cc www.ecod.cc;`
并用 301 统一到主域，避免跳转链多一级。

### 3. Apache（.htaccess，与主题目录的现有规则合并）

```apache
RewriteEngine On
# 放在主题 .htaccess 现有 RewriteRule 之前
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
```

```apache
Header always set Strict-Transport-Security "max-age=31536000" env=HTTPS
```

### 4. 验证

```bash
curl -sI http://ecod.cc/ | head -3
# 期望第一行 HTTP/1.1 301，Location: https://ecod.cc/

curl -sI https://ecod.cc/ | grep -i strict-transport
# 期望出现 Strict-Transport-Security
```

### 5. 注意（进 HTTPS 后必做）

- **统计 / 客服 / CDN 等第三方脚本**必须是 https，`http://` 一律改写（Lighthouse 的
  mixed content 会继续扣分）；
- `functions.php` 及后台「站点地址」应写 `https://`，否则主题输出的 `siteUrl`、
  JSON-LD、manifest 里会混出 http 链接；
- HSTS 建议分档上：先 `max-age=300`，确认无回退问题后再加到一年并加
  `includeSubDomains`。

## 二、静态资源长缓存（报告实测 12h / 部分文件 0 缓存）

报告里 `assets/dist/*.min.css`（文件名带内容哈希）缓存只有 12 小时，
`assets/avatar/avatar18.webp` 是 **0**，等于重复访问者每次重新拉全量 CSS/JS。

主题的 `.htaccess` 已写好一年期 immutable（`FilesMatch css|js|png|...` 分节），
但站点实测没生效——**线上极可能是 nginx，nginx 不读 .htaccess**，需要手工加等效配置。

### 1. nginx

```nginx
# 主题静态资源：内容寻址（dist 哈希文件名 + ?v=filemtime），放心长缓存
location ~* ^/usr/themes/ecod-theme/.+\.(css|js|png|jpe?g|webp|avif|gif|svg|ico|woff2?)$ {
    expires 1y;
    add_header Cache-Control "public, max-age=31536000, immutable";
}

# 用户上传目录里的静态图按需给长缓存（文件不可变，改名即新 URL；
# 若有后台管理能删除/替换同名文件的场景，改用 7d）
location ~* ^/usr/uploads/.+\.(jpe?g|png|webp|gif|svg|avif)$ {
    expires 30d;
    add_header Cache-Control "public, max-age=2592000";
}
```

注意 nginx 的 `add_header` 有「就近覆盖」特性：一旦某 location 里写过 `add_header`，
其它 server 级的 `add_header` 在该 location 内不生效，HSTS 需要在这些 location 里**
重复声明**或改用 `always` 的 include 片段。

### 2. Apache

主题目录 `.htaccess` 已内置（`# 静态资源长缓存` 一节）；若 `.htaccess` 未生效，
确认 `AllowOverride All` 已开启：

```apache
<Directory /var/www/.../usr>
    AllowOverride All
</Directory>
```

### 3. 验证

```bash
curl -sI https://ecod.cc/usr/themes/ecod-theme/assets/dist/css/ecod.<hash>.min.css | grep -i 'cache\|expires'
# 期望: Cache-Control: public, max-age=31536000, immutable

curl -sI https://ecod.cc/usr/themes/ecod-theme/assets/avatar/avatar18.webp | grep -i 'cache'
# 期望同样是长缓存（修改站点/头像文件时 URL 带 ?v=filemtime 会自然失效）
```

## 三、预压缩与文本压缩（省传输）

构建产物已带 `.gz / .br`（`assets/dist/**`），nginx 直接吐预压缩文件、零 CPU：

```nginx
# 需要 ngx_brotli；没有就只开 gzip_static 两行
brotli_static on;
gzip_static on;

# 兜底动态压缩（HTML 等）
gzip on;
gzip_comp_level 5;
gzip_types text/css application/javascript application/json image/svg+xml;
```

Apache 侧主题 `.htaccess` 已有 `AddOutputFilterByType DEFLATE`；想吃到 brotli
需要在 mod_brotli 可用时补一行 `AddOutputFilterByType BROTLI_COMPRESS ...`。

## 四、首屏大图与 LCP（内容编辑建议，非配置）

Lighthouse 实测 LCP 6.2s 的直接原因：首页第一张卡片的封面是**外链占位图**
（`picsum.photos` 的 1200×675 JPG，经两次跳转，占 3.3s）。主题代码已修好另一半
（首卡封面不再 `loading="lazy"`），但要稳定拿下好成绩，编辑内容时：

1. **首屏文章封面用本站 uploads 图**：`core/content/thumbs.php` 会自动把它代理成
   按预设裁剪的 WebP（`api.php?action=thumb&p=cover`），体积和延迟都可控；
   外链图主题不会代理，带宽和首字节延迟都在别人手里。
2. 首屏卡片的封面原始分辨率不必超过卡片显示尺寸的两倍（显示 400×264 的卡，
   800px 宽已足够）。
3. 头像类小图主题已走 `p=side` 预设，无需手工压缩。

## 五、验证清单（改一轮跑一遍）

```bash
# 1. HTTP → HTTPS 301
curl -sI http://ecod.cc/ | head -3

# 2. HSTS 头
curl -sI https://ecod.cc/ | grep -iE 'strict-transport|content-security'

# 3. 静态资源长缓存
curl -sI "https://ecod.cc/usr/themes/ecod-theme/assets/dist/css/ecod.$( \
  curl -s https://ecod.cc/ | grep -o 'assets/dist/css/ecod\.[a-f0-9]*\.min\.css' | head -1 | cut -d/ -f6 )".min.css \
  | grep -i cache

# 4. manifest.php 与 api.php 黑名单外的 PHP 均为 403
curl -sI https://ecod.cc/usr/themes/ecod-theme/functions.php | head -1

# 5. 线上重新跑一次 Lighthouse（本报告工具）
# lighthouse https://ecod.cc/ --view
```
