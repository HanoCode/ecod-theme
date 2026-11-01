<?php
/** 诊断：theme:{slug} 主题设置单行里的全部键 + EAside 实际值 */
require __DIR__ . '/../../../../config.inc.php';

$db = \Typecho\Db::get();

$row = $db->fetchRow($db->select()->from('table.options')->where('name = ?', 'theme:ecod-theme'));
if (!$row) {
    echo "theme:ecod-theme 行不存在\n";
    exit;
}
$opts = json_decode($row["value"], true);
if (!is_array($opts)) {
    echo "unserialize 失败\n";
    var_dump(substr($row['value'], 0, 200));
    exit;
}

echo "== 键总数: ", count($opts), " ==\n";
ksort($opts);
foreach ($opts as $k => $v) {
    $s = is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE);
    if (mb_strlen($s) > 80) {
        $s = mb_substr($s, 0, 80) . '…';
    }
    echo str_pad($k, 28), ' = ', $s, "\n";
}

echo "\n== EAside 精确值 ==\n";
var_dump($opts['EAside'] ?? '(键不存在)');
