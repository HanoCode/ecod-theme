<?php
/**
 * 全量设置审计：定义 / 存储 / 消费 三态比对
 * 找出：A) 定义了但从未消费的死设置  B) 存储值与消费点 default 不一致的
 */
$root = dirname(__DIR__);

/* 1) 定义侧：config.php 的 ecod_admin_field 第 3 参数 + builder-doc.php 白名单 */
$defined = []; // key => [default, label]
$src = file_get_contents($root . '/core/admin/config.php');
preg_match_all("/ecod_admin_field\(\s*\\\$form\s*,\s*'[^']+'\s*,\s*'([A-Za-z0-9_]+)'\s*,\s*('(?:[^'\\\\]|\\\\.)*'|[^\s,]+)\s*,\s*'((?:[^'\\\\]|\\\\.)*)'/", $src, $m, PREG_SET_ORDER);
foreach ($m as $hit) {
    $key = $hit[1];
    $def = trim($hit[2], "'");
    $label = $hit[3];
    $defined[$key] = [$def, $label];
}
/* builder-doc.php 站点样式面板白名单 */
$bd = file_get_contents($root . '/core/admin/builder-doc.php');
preg_match_all("/^\\s*'([A-Za-z0-9_]+)'\s*=>\s*\[/m", $bd, $m2, PREG_SET_ORDER);
foreach ($m2 as $hit) {
    if (!isset($defined[$hit[1]]) && preg_match('/^E[A-Z]/', $hit[1])) {
        $defined[$hit[1]] = ['(builder)', '(builder 面板)'];
    }
}

/* 2) 存储侧：theme:ecod-theme JSON */
$pdo = new PDO('mysql:host=localhost;dbname=ecod;charset=utf8mb4', 'root', 'Hs@12345', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$row = $pdo->query("SELECT value FROM typecho_options WHERE name = 'theme:ecod-theme'")->fetch(PDO::FETCH_ASSOC);
$stored = json_decode($row['value'], true) ?: [];

/* 3) 消费侧：源码 grep ecod_option('KEY' / "KEY" / options->KEY */
$consumed = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $p = $f->getPathname();
    if (strpos($p, '/.') !== false || strpos($p, '/dist/') !== false || strpos($p, '/.backup') !== false || strpos($p, '/temp/') !== false) {
        continue;
    }
    if (!preg_match('/\.(php|js)$/', $p)) {
        continue;
    }
    $txt = file_get_contents($p);
    foreach ($defined as $key => $_) {
        if (isset($consumed[$key])) {
            continue;
        }
        if (preg_match("~ecod_option\(\s*['\"]" . $key . "['\"]~", $txt) || preg_match("~options->" . $key . "\b~", $txt)) {
            $consumed[$key] = $p;
        }
    }
}

/* 4) 报告 */
echo "定义的设置键总数: ", count($defined), "\n";
echo "有消费点的: ", count($consumed), "\n\n";

echo "== A. 定义了但源码中无消费点（死设置） ==\n";
foreach ($defined as $key => [$def, $label]) {
    if (!isset($consumed[$key])) {
        echo str_pad($key, 24), ' default=', str_pad($def, 12), ' label=', $label, "\n";
    }
}

echo "\n== B. 有消费但存储为 null 或缺失（存了空值，走 default） ==\n";
foreach ($defined as $key => [$def, $label]) {
    if (isset($consumed[$key]) && (!array_key_exists($key, $stored) || $stored[$key] === null)) {
        echo str_pad($key, 24), ' default=', str_pad($def, 12), ' stored=', array_key_exists($key, $stored) ? 'null' : '(缺键)', ' label=', $label, "\n";
    }
}
