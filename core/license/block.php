<?php

/**
 * Ecod Theme · 授权锁死页面（纯内联 CSS，不加载主题资源）
 *
 * 设计约束：
 *   - 不出现「盗版 / 未授权」等对外不体面的字样；
 *   - 跟随明暗（prefers-color-scheme），零外部文件；
 *   - 提供管理员登录入口，登录后全站可见并去激活；
 *   - 底部 1px license_ping 信标：续费后锁页自动复验，下次刷新即恢复。
 *
 * 注意：ecod_license_block_html() 会在「输出缓冲回调」（display handler）
 * 里被调用，PHP 8 禁止在回调内再开 ob —— 必须纯字符串拼接返回。
 *
 * @package ecod-theme
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/** 独立锁页 HTML（纯字符串返回，可在 ob 回调内安全调用） */
function ecod_license_block_html(): string
{
    try {
        $options = \Utils\Helper::options();
        $siteUrl = rtrim((string) $options->siteUrl, '/');
        $siteName = trim((string) $options->title);
        $adminUrl = rtrim((string) $options->adminUrl, '/');
    } catch (\Throwable $e) {
        $siteUrl = ecod_license_site_url();
        $siteName = '';
        $adminUrl = '';
    }
    if ($siteName === '') {
        $siteName = '本站';
    }
    $loginUrl = $adminUrl !== ''
        ? $adminUrl . '/login.php?referer=' . rawurlencode($siteUrl . '/admin/options-theme.php#license')
        : '';
    $pingUrl = $siteUrl . '/api.php?action=license_ping&_=' . time();

    $tpl = <<<'HTML'
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{siteName} · 正在完成授权配置</title>
<style>
  :root{
    --bg:#f5f6f8; --card:#ffffff; --ink:#1f2937; --muted:#6b7280; --faint:#9ca3af;
    --brand:#4f46e5; --brand-soft:#eef2ff; --line:rgba(15,23,42,.08);
    --shadow:0 10px 40px rgba(0,0,0,.08);
  }
  @media (prefers-color-scheme: dark){
    :root{
      --bg:#0b1020; --card:#141a2b; --ink:#e5e7eb; --muted:#9aa4b2; --faint:#6b7280;
      --brand:#8b8cf7; --brand-soft:rgba(139,140,247,.14); --line:rgba(255,255,255,.08);
      --shadow:0 10px 40px rgba(0,0,0,.45);
    }
  }
  html,body{margin:0;padding:0;min-height:100%;background:var(--bg);color:var(--ink);
    font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,"PingFang SC","Microsoft YaHei",sans-serif;}
  .ecod-lock{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;box-sizing:border-box;}
  .ecod-lock__card{max-width:460px;width:100%;background:var(--card);border:1px solid var(--line);border-radius:16px;
    box-shadow:var(--shadow);padding:40px 32px;text-align:center;}
  .ecod-lock__brand{font-size:13px;font-weight:600;letter-spacing:.04em;color:var(--muted);margin:0 0 22px;}
  .ecod-lock__icon{width:56px;height:56px;margin:0 auto 18px;border-radius:50%;background:var(--brand-soft);
    display:flex;align-items:center;justify-content:center;}
  .ecod-lock__icon svg{width:28px;height:28px;stroke:var(--brand);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
  .ecod-lock__title{font-size:18px;font-weight:600;margin:0 0 10px;line-height:1.5;}
  .ecod-lock__desc{font-size:14px;color:var(--muted);line-height:1.7;margin:0 0 22px;}
  .ecod-lock__actions{margin:0 0 22px;}
  .ecod-lock__actions a{display:inline-block;padding:9px 18px;border-radius:999px;border:1px solid var(--line);
    background:var(--card);color:var(--ink);font-size:13px;text-decoration:none;}
  .ecod-lock__actions a:hover{border-color:var(--brand);color:var(--brand);}
  .ecod-lock__foot{font-size:12px;color:var(--faint);margin:0;}
  @media (max-width:480px){.ecod-lock__card{padding:32px 22px;border-radius:14px;}}
</style>
</head>
<body>
<div class="ecod-lock">
  <div class="ecod-lock__card">
    <p class="ecod-lock__brand">{siteName}</p>
    <div class="ecod-lock__icon">
      <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
    </div>
    <h1 class="ecod-lock__title">本站正在完成授权配置，稍后恢复访问</h1>
    <p class="ecod-lock__desc">我们正在完成站点授权校验，服务将在几分钟内自动恢复。感谢您的耐心等待。</p>
    <p class="ecod-lock__actions">{loginLink}</p>
    <p class="ecod-lock__foot">如长时间未恢复，请联系站点管理员。</p>
  </div>
</div>
<img src="{pingUrl}" width="1" height="1" alt="" style="position:absolute;left:-9999px;top:-9999px;" />
</body>
</html>
HTML;

    return strtr($tpl, [
        '{siteName}'  => htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'),
        '{loginLink}' => $loginUrl !== ''
            ? '<a href="' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '">管理员登录</a>'
            : '',
        '{pingUrl}'   => htmlspecialchars($pingUrl, ENT_QUOTES, 'UTF-8'),
    ]);
}

/** 兼容旧调用：直接输出锁页 */
function ecod_license_render_block(): void
{
    echo ecod_license_block_html();
}
