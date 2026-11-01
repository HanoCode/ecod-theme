<?php
/** 数据修复：备份 EWidgets / EWidgetsStaging 后清空 aside 覆盖表（恢复「跟随全局」语义） */
$pdo = new PDO('mysql:host=localhost;dbname=ecod;charset=utf8mb4', 'root', 'Hs@12345', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

/* 备份原样行 */
$backup = [];
foreach (['EWidgets', 'EWidgetsStaging'] as $name) {
    $row = $pdo->query("SELECT name, value FROM typecho_options WHERE name = " . $pdo->quote($name))->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $backup[] = $row;
    }
}
file_put_contents(__DIR__ . '/ewidgets-backup-aside-20261101.json', json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo "备份完成: ", count($backup), " 行 → temp/ewidgets-backup-aside-20261101.json\n";

/* 清空 aside 表 */
foreach (['EWidgets', 'EWidgetsStaging'] as $name) {
    $row = $pdo->query("SELECT value FROM typecho_options WHERE name = " . $pdo->quote($name))->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo "$name 不存在，跳过\n";
        continue;
    }
    $doc = json_decode($row['value'], true);
    if (!is_array($doc)) {
        echo "$name 解析失败，跳过\n";
        continue;
    }
    $before = json_encode($doc['aside'] ?? null);
    $doc['aside'] = new stdClass(); /* 空对象 → JSON {} */
    $stmt = $pdo->prepare("UPDATE typecho_options SET value = ? WHERE name = ?");
    $stmt->execute([json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $name]);
    echo "$name aside: $before → {}（已清空，全部跟随全局）\n";
}
