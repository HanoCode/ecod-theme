<?php
/* 一次性验证：v6 norm→clean 闭环（v5 升版 / 卡面 / wpx 互斥 / ov 白名单 / 嵌套行深度 2 / 幂等） */
define('__TYPECHO_ROOT_DIR__', '/tmp');
define('ECOD_WIDGET_LIMIT', 20);

function ecod_block_defs(): array {
    return [
        'banner' => ['name' => '横幅', 'params' => ['title' => ['label' => '标题', 'type' => 'text', 'default' => '']]],
        'feed'   => ['name' => '信息流', 'params' => ['count' => ['label' => '数量', 'type' => 'number', 'default' => 10, 'min' => 1, 'max' => 50]]],
    ];
}
function ecod_widget_pages(): array { return ['home' => 1]; }
function ecod_block_defaults(array $def): array {
    $out = [];
    foreach ((array) ($def['params'] ?? []) as $k => $pr) { $out[$k] = $pr['default'] ?? ''; }
    return $out;
}
function ecod_cache_get(string $name, $default = null) { global $ecod_cache_store; return $ecod_cache_store[$name] ?? $default; }
function ecod_cache_set(string $name, $value): void { global $ecod_cache_store; $ecod_cache_store[$name] = $value; }
function ecod_render_block(array $block, $widget = null): void {
    echo '<div class="stub-block" data-stub="' . htmlspecialchars((string) $block['type']) . '"></div>';
}

require __DIR__ . '/../core/admin/widget-clean.php';
require __DIR__ . '/../core/admin/builder-doc.php';

$fail = 0;
function check(string $name, bool $ok): void {
    global $fail;
    echo ($ok ? 'PASS' : 'FAIL') . "  {$name}\n";
    if (!$ok) { $fail++; }
}

/* 1. v5 文档 → norm → clean：stack + asideW 语义 */
$v5 = ['sections' => [[
    'uid' => 'sec-a', 'on' => 1, 'label' => '主体', 'cols' => 'right', 'asideW' => 320, 'asideSticky' => 1,
    'padY' => 48, 'width' => 'boxed', 'contentMax' => 1200, 'tone' => 'auto', 'ov' => ['sm' => ['padY' => 24]],
    'main' => [['uid' => 'm1', 'type' => 'feed', 'on' => 1, 'params' => ['count' => 12]]],
    'aside' => [['uid' => 'm2', 'type' => 'banner', 'on' => 1, 'params' => []]],
]]];
$c1 = ecod_bld_clean_doc($v5);
$s1 = $c1['sections'][0];
check('v5 升版为单行两列', count($s1['rows']) === 1 && count($s1['rows'][0]['cells']) === 2);
check('v5 asideW→wpx=320 + sticky', $s1['rows'][0]['cells'][1]['wpx'] === 320 && $s1['rows'][0]['cells'][1]['sticky'] === 1);
check('v5 不再有 cols/main/aside 键', !isset($s1['cols'], $s1['main'], $s1['aside']));
check('v5 板块 ov 保留 sm.padY', ($s1['ov']['sm']['padY'] ?? 0) === 24);

/* 2. v6 卡面字段 + wpx/w 互斥 + ov 白名单 */
$v6 = ['sections' => [[
    'uid' => 'sec-b', 'on' => 1,
    'rows' => [[
        'uid' => 'row-b', 'on' => 1, 'gap' => 999, 'valign' => 'middle',
        'ov' => ['md' => ['keep' => 1, 'evil' => 'x'], 'lg' => ['keep' => 1]],
        'cells' => [
            ['uid' => 'cell-b1', 'w' => 5, 'wpx' => 300, 'sticky' => 1,
             'bg' => 'https://cdn.example/x.jpg', 'bgDark' => 'javascript:alert(1)', 'bgc' => '#4f6ef2',
             'radius' => 99, 'pad' => -5,
             'ov' => ['sm' => ['order' => 99, 'hide' => 1, 'w' => 3, 'evil' => 1]],
             'mods' => [['uid' => 'm3', 'type' => 'banner', 'on' => 1, 'params' => ['title' => 'A']]],
            ],
            ['uid' => 'cell-b2', 'w' => 2, 'mods' => []],
        ],
    ]],
]]];
$c2 = ecod_bld_clean_doc($v6);
$cell1 = $c2['sections'][0]['rows'][0]['cells'][0];
$row1 = $c2['sections'][0]['rows'][0];
check('wpx 优先于 w（互斥）', $cell1['wpx'] === 300 && !isset($cell1['w']));
check('卡面 bg 存活 + bgDark 恶意协议被清', $cell1['bg'] === 'https://cdn.example/x.jpg' && $cell1['bgDark'] === '');
check('卡面 bgc 合法 + radius/pad 钳制', $cell1['bgc'] === '#4f6ef2' && $cell1['radius'] === 40 && $cell1['pad'] === 0);
check('行列 ov 白名单收口（evil/lg/order 越界丢弃）',
    !isset($row1['ov']['md']['evil']) && !isset($row1['ov']['lg'])
    && !isset($cell1['ov']['sm']['order']) && ($cell1['ov']['sm']['hide'] ?? 0) === 1 && ($cell1['ov']['sm']['w'] ?? 0) === 3);
check('非法 gap/valign 回落默认', $row1['gap'] === 24 && $row1['valign'] === 'start');

/* 3. 列内嵌套行（深度 2）：mods 并入首嵌套列、内层字段存活、超深整枝 */
$v7 = ['sections' => [[
    'uid' => 'sec-c', 'on' => 1,
    'rows' => [[
        'uid' => 'row-c', 'on' => 1, 'cells' => [[
            'uid' => 'cell-c1', 'w' => 3,
            'mods' => [['uid' => 'm-flat', 'type' => 'feed', 'on' => 1, 'params' => []]],
            'rows' => [[
                'uid' => 'nrow-1', 'on' => 1, 'gap' => 16, 'valign' => 'center',
                'cells' => [
                    ['uid' => 'ncell-1', 'w' => 2, 'pad' => 24, 'bgc' => '#112233',
                     'mods' => [['uid' => 'm-n1', 'type' => 'banner', 'on' => 1, 'params' => []]]],
                    ['uid' => 'ncell-2', 'wpx' => 200,
                     'mods' => [],
                     'rows' => [['uid' => 'deep-row', 'cells' => [['uid' => 'deep-cell', 'mods' => [['uid' => 'm-deep', 'type' => 'feed', 'on' => 1, 'params' => []]]]]]]],
                ],
            ]],
        ]],
    ]],
]]];
$c7 = ecod_bld_clean_doc($v7);
$ncell = $c7['sections'][0]['rows'][0]['cells'][0];
check('嵌套行存在且 mods 并入首嵌套列（不静默丢模块）',
    count($ncell['rows']) === 1 && count($ncell['rows'][0]['cells'][0]['mods']) === 2 && $ncell['mods'] === []);
$nc1 = $ncell['rows'][0]['cells'][0];
$nc2 = $ncell['rows'][0]['cells'][1];
check('嵌套列字段存活（gap/valign/w/wpx/卡面）',
    $ncell['rows'][0]['gap'] === 16 && $ncell['rows'][0]['valign'] === 'center'
    && $nc1['w'] === 2 && $nc1['pad'] === 24 && $nc1['bgc'] === '#112233' && $nc2['wpx'] === 200);
check('深度 3 整枝丢弃（嵌套列内的 rows 不存活）', empty($nc2['rows']));
check('嵌套列 uid 确定性生成', preg_match('/^cell-[a-f0-9]{8}$/', $nc1['uid']) === 1);

/* 4. 幂等：clean(clean(x)) 与 clean(x) 全等（含嵌套） */
$c7b = ecod_bld_clean_doc(json_decode(json_encode($c7), true));
check('clean 幂等（含嵌套行）', json_encode($c7) === json_encode($c7b));

/* 5. 渲染：fr+px 混排、卡面变量、嵌套行 DOM、builder 标记 */
ob_start();
ecod_layout_render_row($row1, null, true);
$html = ob_get_clean();
check('row-cols 混排 px+fr', strpos($html, '--row-cols:300px minmax(0,2fr)') !== false);
check('卡面变量输出（bg/bgc/radius）',
    strpos($html, '--cell-bg:url(\'https://cdn.example/x.jpg\')') !== false
    && strpos($html, '--cell-bgc:#4f6ef2') !== false
    && strpos($html, '--cell-radius:40px') !== false);
check('builder 标记输出 row/cell data-bkind', strpos($html, 'data-bkind="row"') !== false && strpos($html, 'data-bkind="cell"') !== false);

ob_start();
ecod_layout_render_row($c7['sections'][0]['rows'][0], null, true);
$html7 = ob_get_clean();
check('嵌套行渲染为 ecod-sec__row（--row-cols:2fr 200px）',
    substr_count($html7, 'ecod-sec__row') >= 2 && strpos($html7, 'minmax(0,2fr) 200px') !== false);
check('嵌套模块渲染进内层列（banner×1 + 平铺并入的 feed×1）',
    substr_count($html7, 'data-stub="banner"') === 1 && substr_count($html7, 'data-stub="feed"') === 1);

/* 5.5 行级 masonry（P2 实验）：字段存活 + 类名/变量输出 */
$v8 = ['sections' => [[
    'uid' => 'sec-d', 'on' => 1,
    'rows' => [['uid' => 'row-d', 'on' => 1, 'masonry' => 1, 'cells' => [
        ['uid' => 'cell-d1', 'w' => 1, 'mods' => []],
        ['uid' => 'cell-d2', 'w' => 2, 'mods' => []],
        ['uid' => 'cell-d3', 'w' => 1, 'mods' => []],
    ]]],
]]];
$c8 = ecod_bld_clean_doc($v8);
$rowd = $c8['sections'][0]['rows'][0];
check('masonry 字段清洗存活', $rowd['masonry'] === 1);
ob_start();
ecod_layout_render_row($rowd, null, true);
$html8 = ob_get_clean();
check('masonry 类名与 --masonry-cols 输出',
    strpos($html8, 'ecod-sec__row--masonry') !== false && strpos($html8, '--masonry-cols:3') !== false);

/* 6. 行模板库（P2）：保存同名覆盖 + 删除 */
$lib1 = ecod_layout_rowlib_save('测试行', ['uid' => '', 'cells' => [['uid' => '', 'w' => 1, 'mods' => []], ['uid' => '', 'wpx' => 300, 'mods' => []]]]);
$lib2 = ecod_layout_rowlib_save('测试行', ['uid' => '', 'cells' => [['uid' => '', 'w' => 2, 'mods' => []]]]);
check('行模板保存 + 同名覆盖', count($lib1) === 1 && count(reset($lib2)['row']['cells']) === 1);
$lib3 = ecod_layout_rowlib_delete((string) array_key_first($lib2));
check('行模板删除', $lib3 === []);

echo $fail ? "\n{$fail} FAILED\n" : "\nALL PASS\n";
exit($fail ? 1 : 0);
