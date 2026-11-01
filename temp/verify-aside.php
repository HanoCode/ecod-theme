<?php
/** 端到端验证：切换 EAside → 请求前台 → 检查 data-aside 跟随 */
$pdo = new PDO('mysql:host=localhost;dbname=ecod;charset=utf8mb4', 'root', 'Hs@12345', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

function theme_opt($pdo, $key)
{
    $row = $pdo->query("SELECT value FROM typecho_options WHERE name = 'theme:ecod-theme'")->fetch(PDO::FETCH_ASSOC);
    $cfg = json_decode($row['value'], true);
    return $cfg[$key] ?? null;
}
function set_theme_opt($pdo, $key, $val)
{
    $row = $pdo->query("SELECT value FROM typecho_options WHERE name = 'theme:ecod-theme'")->fetch(PDO::FETCH_ASSOC);
    $cfg = json_decode($row['value'], true);
    $cfg[$key] = $val;
    $stmt = $pdo->prepare("UPDATE typecho_options SET value = ? WHERE name = 'theme:ecod-theme'");
    $stmt->execute([json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
}

$orig = theme_opt($pdo, 'EAside');
echo "当前 EAside = ", var_export($orig, true), "\n";

foreach (['left', 'none', 'right'] as $test) {
    set_theme_opt($pdo, 'EAside', $test);
    $html = file_get_contents('http://ecod.cc/');
    preg_match('/data-aside="([a-z]+)"/', $html, $m);
    $got = $m[1] ?? '(无 data-aside 属性)';
    $ok = $got === $test ? '✓' : '✗✗✗';
    echo "EAside=$test → data-aside=$got $ok\n";
}

set_theme_opt($pdo, 'EAside', $orig);
echo "已还原 EAside = ", var_export(theme_opt($pdo, 'EAside'), true), "\n";
