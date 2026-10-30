# Ecod · 社会化登录设计（v1）

> 参考：Zibll 的 `oauth/` 模块（已购商用授权，仅参考端点协议与交互流程；SDK 端点经 Yurun\OAuthLogin 验证）。
> 状态：GitHub 已上线（`core/user/oauth.php`）；本文档记录微信 / QQ / 微博 / Gitee 四平台接入。
> 适用：Typecho 1.3 / PHP 8.1+ / 主题 `ecod-theme`

---

## 1. 复用与不动的部分

既有 GitHub 实现的骨架全部保留，四平台只是「多填几份凭据 + 多几个 fetch 分支」：

- 绑定表 `{prefix}ecod_oauth_bind`（platform + openid 唯一）不动；
- 回调后的三条路径不动：已登录 → 绑定；已绑定 → 直接登录；未绑定 → 邮箱匹配（可关）→ 自动建号（可关）；
- `state` 防 CSRF、回跳地址只收站内路径、登录 Cookie 与账号密码同套，全不动。

## 2. 平台端点（与 Zibll / Yurun\OAuthLogin 一致）

| 平台 | 授权页 | 换 token | 拉用户 | openid 取值 |
| --- | --- | --- | --- | --- |
| weixin | `open.weixin.qq.com/connect/qrconnect`（scope=`snsapi_login`，PC 扫码，URL 尾拼 `#wechat_redirect`） | GET `api.weixin.qq.com/sns/oauth2/access_token?appid&secret&code&grant_type=authorization_code` | GET `sns/userinfo?access_token&openid&lang=zh_CN` → `nickname` / `headimgurl` | `openid` |
| qq | `graph.qq.com/oauth2.0/authorize?response_type=code&client_id&redirect_uri&state` | GET `oauth2.0/token?grant_type&client_id&client_secret&code&state&redirect_uri`（**form 串**；出错时才是 JSON） | 先 GET `oauth2.0/me?access_token`（**JSONP**，取首个 `{` 到末个 `}`）得 `openid`，再 GET `user/get_user_info?access_token&oauth_consumer_key&openid` → `nickname` / `figureurl_qq_2`（100px，缺则 `figureurl_qq_1`） | `openid` |
| weibo | `api.weibo.com/oauth2/authorize?client_id&redirect_uri&state` | **POST** form `oauth2/access_token`（client_id / client_secret / grant_type / code / redirect_uri）→ JSON，`error_code` 即失败 | GET `2/users/show.json?access_token&uid` → `screen_name`（缺则 `name`）/ `profile_image_url` / `description` | `uid` |
| gitee | `gitee.com/oauth/authorize?client_id&redirect_uri&response_type=code&state` | **POST** form `oauth/token`（grant_type=authorization_code / code / client_id / redirect_uri / client_secret），响应带 `error` 即失败 | GET `api/v5/user?access_token` → `id` / `name`（缺则 `login`）/ `avatar_url`（含 `no_portrait` 时丢弃）/ `bio` | `id` |

## 3. 平台差异的处理决策

| 问题 | 决定 |
| --- | --- |
| 微信/QQ/微博/Gitee 都拿不到可靠邮箱 | **跳过邮箱匹配绑定**（GitHub 专属路径）；未登录且未绑定时直接走自动建号开关 |
| 自动建号的 mail 唯一值 | 合成地址 `{platform}_{openid}@oauth.ecod.local`（清洗成 `[a-z0-9_.-]`，每平台每 openid 恒定，永不再命中匹配路径） |
| QQ token 响应格式 | 成功是 form 串、失败是 JSON：先 `json_decode` 探测，失败再 `parse_str` |
| QQ me 端点 JSONP | 去掉 `callback( ... );` 外壳取 JSON |
| 微信头像/昵称 errcode | token 与 userinfo 都判 `errcode != 0` 即失败 |
| HTTP 客户端 | 新增 `ecod_oauth_http_raw()` 返回原始串，JSON 解析放调用方（QQ form 串 / me JSONP 需要） |
| 回调地址 | 统一 `{siteUrl}/usr/themes/ecod-theme/api.php?action=oauth_callback&platform={platform}`，注册应用时照抄 |

## 4. 平台申请门槛（后台帮助文案同步）

| 平台 | 门槛 | 备注 |
| --- | --- | --- |
| 微信 | 微信开放平台「网站应用」，需**企业资质** | PC 扫码登录；`appid` + `secret` |
| QQ | QQ 互联创建应用 | `APP ID` + `APP Key` |
| 微博 | 微博开放平台网站接入 | `App Key` + `App Secret` |
| Gitee | 个人开发者即可创建第三方应用 | 零门槛，推荐先用它验收 |

## 5. 后台配置项

账号分组新增（模式与 GitHub 一致，开关 + Id + Secret）：

`EOAuthWeixin(+Id/Secret)`、`EOAuthQQ(+Id/Secret)`、`EOAuthWeibo(+Id/Secret)`、`EOAuthGitee(+Id/Secret)`。
`EOAuthBindMail` / `EOAuthRegister` 两开关对全部平台生效（前者仅 GitHub 实际用到）。

## 6. 前台

登录页「第三方登录」区由单个 GitHub 按钮改为按启用平台渲染一排品牌按钮（`ecod_login__oauthrow`），
图标来自 `components/icons.php` 品牌组（新增 `wechat` / `qq` / `gitee`，微博已有）。

## 7. api.php

`oauth_start / oauth_callback` 的平台白名单：`github / weixin / qq / weibo / gitee`。
