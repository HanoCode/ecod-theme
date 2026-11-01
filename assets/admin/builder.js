/**
 * Ecod Theme · 页面构建器 · 画布编辑器
 *
 * 运行在 builder.php（管理员）。画布是同源 iframe 加载的真实前台页面，
 * 编辑器直接接管 iframe 文档：点击选中 / 拖放 / 键盘都通过挂在
 * iframe 文档上的监听完成，不需要向页面注入任何编辑脚本。
 *
 * 组件划分（对应 docs/builder-design.html §4.3）：
 *   Store      布局文档状态 + 撤销栈 + 脏跟踪
 *   Library    左栏模块库（blocks-def 数据 + 指针式拖拽）
 *   Canvas     iframe 加载、选中态、投放指示、结构变更后重载
 *   Inspector  右侧属性面板（EcodFields 渲染 params schema）
 *   Outline    左栏大纲树（显隐 / 上下移 / 删除，与画布双向高亮）
 *   Saver      保存队列 + rev 乐观锁 + 冲突弹窗
 *
 * @package Ecod Theme
 * @author  Ecod
 * @since   2026-10-31
 * @version 1.0.0
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 */

/* global EcodMedia */
(function () {
    'use strict';

    var CFG = window.ECOD_BLD || {};
    if (!CFG.docKey) {
        return;
    }

    /* ==================================================================
     * Store
     * ================================================================ */
    var S = {
        docKey: CFG.docKey,
        docs: CFG.docs || {},
        sections: Array.isArray(CFG.sections) ? CFG.sections : [],
        sel: null,            /* {kind:'module'|'section', id} */
        device: 'desktop',
        zoom: 1,
        dirty: false,
        saving: false,
        lock: !!CFG.lock,
        /* v4 区域模型（widget 管理台同一份 EWidgets 存储） */
        wgt: {
            areas: (CFG.wgt && CFG.wgt.areas) || {},
            areaMeta: (CFG.wgt && CFG.wgt.areaMeta) || {},
            pages: (CFG.wgt && CFG.wgt.pages) || {},
            aside: (CFG.wgt && CFG.wgt.aside) || {}
        },
        wgtRev: (CFG.wgt && CFG.wgt.rev) || '',
        wgtDirty: {},          /* areaId => 1（距上次「发布」的脏区域） */
        wgtStaged: {},         /* areaId => 1（距上次「暂存同步」的脏区域） */
        stageBusy: false,
        stageQueued: false,          /* areaId => 1（脏区域） */
        dirtyDoc: false,
        dirtyWgt: false,
        areaPage: 'home',
        rev: CFG.rev || '',
        savedRows: CFG.savedRows || {},   /* 我的行模板（P2 可复用行） */
        savedDocs: CFG.savedDocs || {},   /* 我的整页模板（P2 模板中心） */
        undo: [],
        redo: [],
        tab: 'lib',
        itab: 'content',
        idoc: null,
        iwin: null,
        reloadTimer: 0,
        reloadSeq: 0,
        frameSel: null,       /* 重载前记录的选中 */
        frameScroll: 0
    };

    var MAX_SECTIONS = CFG.maxSections || 30;
    var MAX_ROWS = CFG.maxRows || 12;
    var MAX_CELLS = CFG.maxCells || 6;
    var MAX_COLS = CFG.maxCols || 20;
    var DEVW = { desktop: 'min(100%, 1100px)', tablet: '768px', phone: '390px' };
    var DEVNAME = { desktop: '桌面', tablet: '平板', phone: '手机' };
    /* 行结构预设（docs/builder-v6-design.md §6.6）：既作为库里的「结构」分组
       （点击插入 / 拖入行间隙），也供行工具条对既有行套用（模块按权重顺序重分配） */
    var ROW_PRESETS = [
        { id: '1', name: '单栏', cells: [{ w: 1 }] },
        { id: '1:1', name: '两栏 1:1', cells: [{ w: 1 }, { w: 1 }] },
        { id: '2:1', name: '两栏 2:1', cells: [{ w: 2 }, { w: 1 }] },
        { id: '1:2', name: '两栏 1:2', cells: [{ w: 1 }, { w: 2 }] },
        { id: '1:1:1', name: '三栏 1:1:1', cells: [{ w: 1 }, { w: 1 }, { w: 1 }] },
        { id: '2:1:1', name: '三栏 2:1:1', cells: [{ w: 2 }, { w: 1 }, { w: 1 }] },
        { id: '1:1:1:1', name: '四栏', cells: [{ w: 1 }, { w: 1 }, { w: 1 }, { w: 1 }] },
        { id: 'side-r', name: '侧栏右 320px', cells: [{ w: 1 }, { wpx: 320, sticky: 1 }] },
        { id: 'side-l', name: '侧栏左 320px', cells: [{ wpx: 320, sticky: 1 }, { w: 1 }] }
    ];
    function presetById(id) {
        for (var i = 0; i < ROW_PRESETS.length; i++) {
            if (ROW_PRESETS[i].id === id) { return ROW_PRESETS[i]; }
        }
        return null;
    }
    /* 库卡片 / 预设菜单共用的 data-new 值前缀（与模块 id 空间隔离） */
    var PRESET_PREFIX = 'row:';
    function isPresetType(type) {
        return String(type || '').indexOf(PRESET_PREFIX) === 0;
    }
    /* 由预设建行：空模块列；既有行的套用走 applyPresetToRow（保留模块） */
    function presetRow(p) {
        return {
            uid: uid('row'), on: 1, gap: 24, valign: 'start', ov: {},
            cells: p.cells.map(function (c) {
                var cell = newCell([]);
                if (c.wpx) { delete cell.w; cell.wpx = c.wpx; cell.sticky = c.sticky ? 1 : 0; }
                else { cell.w = c.w || 1; }
                return cell;
            })
        };
    }
    /* 行内是否含嵌套行结构（预设套用的拒绝条件：重分配会丢嵌套行） */
    function rowHasNested(row) {
        return (row.cells || []).some(function (c) { return (c.rows || []).length > 0; });
    }
    /* 把行的模块按列权重比例顺序重分配进预设的新列（uid 全部保留，可撤销） */
    function applyPresetToRow(row, p) {
        if (rowHasNested(row)) { return false; }
        var mods = [];
        (row.cells || []).forEach(function (c) { mods = (mods || []).concat(c.mods || []); });
        var weights = p.cells.map(function (c) { return c.wpx ? 2 : (c.w || 1); });
        var total = weights.reduce(function (a, b) { return a + b; }, 0) || 1;
        var take = weights.map(function (w) { return Math.floor(mods.length * w / total); });
        var used = take.reduce(function (a, b) { return a + b; }, 0);
        take[take.length - 1] += mods.length - used; /* 余数给最后一列 */
        var at = 0;
        row.cells = p.cells.map(function (c, i) {
            var cell = newCell(mods.slice(at, at + take[i]));
            at += take[i];
            if (c.wpx) { delete cell.w; cell.wpx = c.wpx; cell.sticky = c.sticky ? 1 : 0; }
            else { cell.w = c.w || 1; }
            return cell;
        });
    }

    /* ----------------------------------------------------------------
     * 行模板统一入口（P2 可复用行）：预置预设（row:<id>）与用户保存的
     * 行模板（srow:<id>）共用同一套落点/插入/点击逻辑
     * ---------------------------------------------------------------- */
    var SROW_PREFIX = 'srow:';
    function isRowTemplateType(type) {
        return isPresetType(type) || String(type || '').indexOf(SROW_PREFIX) === 0;
    }
    function rowTemplateDef(type) {
        if (isPresetType(type)) {
            var p = presetById(type.slice(PRESET_PREFIX.length));
            return p ? { name: p.name, preset: p } : null;
        }
        if (String(type || '').indexOf(SROW_PREFIX) === 0) {
            var one = (S.savedRows || {})[type.slice(SROW_PREFIX.length)];
            return one ? { name: one.name, saved: one.row } : null;
        }
        return null;
    }
    /* 由模板建行（新 uid 全套重发；保存模板可能携带嵌套行与 ov，一并保留） */
    function tplRow(def) {
        if (def.preset) { return presetRow(def.preset); }
        var row = JSON.parse(JSON.stringify(def.saved));
        row.uid = uid('row');
        row.on = 1;
        (row.cells || []).forEach(function (c) {
            c.uid = uid('cell');
            reissueCellUids(c);
        });
        return row;
    }
    /* 列宽均分（§6.4 双击手柄复位等分的等价按钮路径）：全列转 1fr，
       热更新 --row-cols 不重载 */
    function equalizeRow(rowId) {
        var p = findPath(rowId);
        if (!p || !p.row || !(p.row.cells || []).length) { return; }
        captureUndo('roweq' + rowId + Date.now());
        p.row.cells.forEach(function (c) { delete c.wpx; c.w = 1; });
        var node = nodeFor('row', rowId);
        if (node) {
            var parts = p.row.cells.map(function () { return 'minmax(0,1fr)'; });
            node.style.setProperty('--row-cols', parts.join(' '));
        }
        markDirty();
        renderOutline();
        renderInspector();
        stageSync(true);
        toast('已均分列宽：固定像素列转为 1fr（⌘/Ctrl+Z 可撤销）');
    }
    /* 行 → 我的行模板（P2 synced rows 单站版）；name 空则服务端自动命名 */
    function saveRowTemplate(rowId, name) {
        if (S.lock) { toast('布局已锁定：顶栏可解锁后再存模板'); return; }
        var p = findPath(rowId);
        if (!p || !p.row) { return; }
        var row = JSON.parse(JSON.stringify(p.row));
        api('rowlib.save', { name: String(name || ''), row: row }, function (j) {
            if (!j.ok) { toast(j.error || '保存失败，请稍后重试'); return; }
            S.savedRows = j.savedRows || S.savedRows;
            buildLib();
            toast('已存为行模板，在左栏「我的行模板」取用');
        });
    }
    /* 模块「移动到」（§6.5 键盘可达等价路径）：落到目标列末尾；
       嵌套行结构的列不收模块（该列渲染走内层行） */
    function moveModuleTo(modUid, cellUid) {
        var src = findPath(modUid);
        var dst = findPath(cellUid);
        if (!src || !src.mod || !dst || !dst.cell) { return; }
        if (src.cell.uid === dst.cell.uid) { return; }
        if ((dst.cell.rows || []).length) { toast('目标列是嵌套行结构，请选它的内层列'); return; }
        captureUndo('mvto' + modUid + Date.now());
        var si = src.modsArr.indexOf(src.mod);
        if (si > -1) { src.modsArr.splice(si, 1); }
        dst.cell.mods = dst.cell.mods || [];
        dst.cell.mods.push(src.mod);
        S.sel = { kind: 'module', id: modUid };
        markDirty();
        renderAll();
        scheduleReload();
        toast('已移动到目标列末尾（⌘/Ctrl+Z 可撤销）');
    }

    function $(sel, el) { return (el || document).querySelector(sel); }
    function $$(sel, el) { return Array.prototype.slice.call((el || document).querySelectorAll(sel)); }
    function ico(name) {
        return (CFG.icons && CFG.icons[name]) || '';
    }
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function uid(prefix) {
        return prefix + '-' + Math.random().toString(36).slice(2, 8);
    }
    function toast(msg) {
        var el = $('[data-toast]');
        el.textContent = msg;
        el.hidden = false;
        clearTimeout(toast.t);
        toast.t = setTimeout(function () { el.hidden = true; }, 2200);
    }
    function findSec(id) {
        for (var i = 0; i < S.sections.length; i++) {
            if (S.sections[i].uid === id) { return S.sections[i]; }
        }
        return null;
    }
    /* v6 定位（深度无关）：板块 → 行 → 列 → 模块，列可再带嵌套行（深度 2）。
       返回节点引用 + 所在容器数组（rowsArr/cellsArr/modsArr）——结构操作统一
       用「容器数组 + indexOf」定位，顶层与嵌套行走同一套代码，不再依赖 index.r/c。
       嵌套命中时附 hostCell/hostRow（Esc 上逃链：模块 → 列 → 行 → 宿主列 → 板块）。 */
    function findPath(id) {
        for (var s = 0; s < S.sections.length; s++) {
            var sec = S.sections[s];
            var hit = findPathInRows(sec, sec.rows || [], id, s, null, null);
            if (hit) { return hit; }
            if (sec.uid === id) {
                return { sec: sec, row: null, cell: null, mod: null,
                    rowsArr: null, cellsArr: null, modsArr: null,
                    hostCell: null, hostRow: null, index: { s: s } };
            }
        }
        return null;
    }
    function findPathInRows(sec, rowsArr, id, s, hostCell, hostRow) {
        for (var r = 0; r < rowsArr.length; r++) {
            var row = rowsArr[r];
            if (row.uid === id) {
                return { sec: sec, row: row, cell: null, mod: null,
                    rowsArr: rowsArr, cellsArr: row.cells || [], modsArr: null,
                    hostCell: hostCell, hostRow: hostRow, index: { s: s } };
            }
            var cells = row.cells || [];
            for (var c = 0; c < cells.length; c++) {
                var cell = cells[c];
                if (cell.uid === id) {
                    return { sec: sec, row: row, cell: cell, mod: null,
                        rowsArr: rowsArr, cellsArr: cells, modsArr: cell.mods || [],
                        hostCell: hostCell, hostRow: hostRow, index: { s: s } };
                }
                var arr = cell.mods || [];
                for (var m = 0; m < arr.length; m++) {
                    if (arr[m].uid === id) {
                        return { sec: sec, row: row, cell: cell, mod: arr[m],
                            rowsArr: rowsArr, cellsArr: cells, modsArr: arr,
                            hostCell: hostCell, hostRow: hostRow, index: { s: s } };
                    }
                }
                var sub = findPathInRows(sec, cell.rows || [], id, s, cell, row);
                if (sub) { return sub; }
            }
        }
        return null;
    }
    /* 兼容别名：只关心模块时的快捷入口（arr = 模块所在 mods 数组，index 已换算） */
    function findMod(id) {
        var p = findPath(id);
        if (!p || !p.mod) { return null; }
        return { sec: p.sec, row: p.row, cell: p.cell, arr: p.modsArr,
            index: p.modsArr.indexOf(p.mod) };
    }
    /* sel 的父级（Esc 逐级上逃：module → cell → row →（嵌套时）宿主列 → section → 取消） */
    function selParent() {
        if (!S.sel || S.sel.kind === 'section') { return null; } /* 板块是顶层：Esc 直接取消选中 */
        var p = findPath(S.sel.id);
        if (!p) { return null; }
        if (S.sel.kind === 'module' && p.cell) { return { kind: 'cell', id: p.cell.uid }; }
        if ((S.sel.kind === 'module' || S.sel.kind === 'cell') && p.row) { return { kind: 'row', id: p.row.uid }; }
        if (S.sel.kind === 'row' && p.hostCell) { return { kind: 'cell', id: p.hostCell.uid }; }
        if (p.sec) { return { kind: 'section', id: p.sec.uid }; }
        return null;
    }
    /* 板块的扁平模块列表（大纲树 / 批量操作用；含嵌套行内模块） */
    function secMods(sec) {
        var out = [];
        var scan = function (rows) {
            (rows || []).forEach(function (row) {
                (row.cells || []).forEach(function (cell) {
                    out = out.concat(cell.mods || []);
                    scan(cell.rows);
                });
            });
        };
        scan(sec.rows);
        return out;
    }
    /* 板块最后一列（点击加模块的默认落点；空板块自动补行补列） */
    function lastCell(sec) {
        if (!sec.rows || !sec.rows.length) { sec.rows = [newRow([])]; }
        var row = sec.rows[sec.rows.length - 1];
        if (!row.cells || !row.cells.length) { row.cells = [newCell([])]; }
        return row.cells[row.cells.length - 1];
    }
    function findCell(sec, cellUid) {
        var hit = null;
        var scan = function (rows) {
            (rows || []).forEach(function (row) {
                (row.cells || []).forEach(function (cell) {
                    if (cell.uid === cellUid) { hit = cell; }
                    scan(cell.rows); /* 列内嵌套行（深度 2） */
                });
            });
        };
        scan(sec.rows);
        return hit;
    }

    function captureUndo(tag) {
        var now = Date.now();
        if (captureUndo.tag === tag && now - captureUndo.t < 500) {
            captureUndo.t = now;
            return false;
        }
        captureUndo.tag = tag;
        captureUndo.t = now;
        S.undo.push(snapAll());
        if (S.undo.length > 50) { S.undo.shift(); }
        S.redo = [];
        return true;
    }
    /* 撤销快照 = 布局文档 + v4 区域实例（两套模型一起回滚） */
    function snapAll() {
        return JSON.stringify({ sections: S.sections, wgt: S.wgt.areas });
    }
    function restoreSnapshot(json) {
        var st = JSON.parse(json);
        if (st && typeof st === 'object' && !Array.isArray(st) && ('sections' in st)) {
            S.sections = st.sections || [];
            S.wgt.areas = st.wgt || {};
        } else {
            S.sections = st;   /* 旧格式：仅布局文档 */
        }
        S.sel = null;
        markDirty('doc');
        markDirty('wgt');
        renderAll();
        scheduleReload();
    }
    function doUndo() {
        if (!S.undo.length) { return; }
        S.redo.push(snapAll());
        restoreSnapshot(S.undo.pop());
    }
    function doRedo() {
        if (!S.redo.length) { return; }
        S.undo.push(snapAll());
        restoreSnapshot(S.redo.pop());
    }
    function markDirty(kind) {
        if (kind === 'wgt') { S.dirtyWgt = true; }
        else { S.dirtyDoc = true; scheduleDraft(); }
        renderTop();
        renderStatus();
    }
    function isDirty() {
        return S.dirtyDoc || S.dirtyWgt || Object.keys(S.wgtDirty).length > 0;
    }
    function clearDirty() {
        S.dirtyDoc = false;
        S.dirtyWgt = false;
        S.wgtDirty = {};
        S.wgtStaged = {};
    }

    /* ==================================================================
     * 模块 / 板块 工厂
     * ================================================================ */
    function newMod(type) {
        var def = CFG.defs[type] || {};
        var params = {};
        Object.keys(def.params || {}).forEach(function (k) {
            params[k] = def.params[k].default;
        });
        return { uid: uid(type), type: type, on: 1, params: params, hide: {} };
    }
    function newCell(mods) {
        return { uid: uid('cell'), w: 1, sticky: 0, ov: {}, mods: mods || [] };
    }
    /* 复制列/行后重发全部 uid：列内嵌套行的行/列/模块一并重发，防 Store uid 冲突
       （调用方先发容器自身的 uid） */
    function reissueCellUids(cell) {
        (cell.rows || []).forEach(function (nr) {
            nr.uid = uid('row');
            (nr.cells || []).forEach(function (nc) {
                nc.uid = uid('cell');
                (nc.mods || []).forEach(function (m) { m.uid = uid(m.type); });
            });
        });
        (cell.mods || []).forEach(function (m) { m.uid = uid(m.type); });
    }
    function newRow(mods) {
        return { uid: uid('row'), on: 1, gap: 24, valign: 'start', ov: {}, cells: [newCell(mods)] };
    }
    function newSection(withModule) {
        var sec = {
            uid: uid('sec'), on: 1, label: '新板块', width: 'full', contentMax: 0,
            padY: 48, bg: '', bgDark: '', radius: 0, tone: 'auto',
            ov: {}, rows: [newRow(withModule ? [withModule] : [])]
        };
        return sec;
    }
    /* v5 → v6 客户端兜底规范化（本地草稿 / 模板导入可能仍是 v5；服务端读路径
       也会 norm，这里是双保险，映射与 ecod_bld_norm_section 一致） */
    function normSections(list) {
        if (!Array.isArray(list)) { return []; }
        return list.map(function (sec) {
            if (!sec || typeof sec !== 'object') { return null; }
        if (Array.isArray(sec.rows)) {
            sec.rows.forEach(function (row) {
                if (!Array.isArray(row.cells)) { row.cells = []; }
                row.cells.forEach(function (cell) {
                    if (!Array.isArray(cell.mods)) { cell.mods = []; }
                    /* 列内嵌套行（深度 2）：形状兜底，内层列只保 mods */
                    if (Array.isArray(cell.rows)) {
                        cell.rows.forEach(function (nr) {
                            if (!Array.isArray(nr.cells)) { nr.cells = []; }
                            nr.cells.forEach(function (nc) {
                                if (!Array.isArray(nc.mods)) { nc.mods = []; }
                            });
                        });
                    }
                });
            });
            return sec;
        }
            var main = Array.isArray(sec.main) ? sec.main : [];
            var aside = Array.isArray(sec.aside) ? sec.aside : [];
            var wpx = Math.min(640, Math.max(160, parseInt(sec.asideW, 10) || 320));
            var sticky = sec.asideSticky ? 1 : 0;
            var cells;
            if (sec.cols === 'left') {
                cells = [newCell(aside), newCell(main)];
                cells[0].wpx = wpx; cells[0].sticky = sticky;
            } else if (sec.cols === 'right') {
                cells = [newCell(main), newCell(aside)];
                cells[1].wpx = wpx; cells[1].sticky = sticky;
            } else {
                cells = [newCell(main.concat(aside))];
            }
            sec.rows = [{ uid: uid('row'), on: 1, gap: 24, valign: 'start', ov: {}, cells: cells }];
            delete sec.cols; delete sec.asideW; delete sec.asideSticky;
            delete sec.main; delete sec.aside;
            return sec;
        }).filter(Boolean);
    }
    function modLabel(m) {
        var def = CFG.defs[m.type] || {};
        return def.name || m.type;
    }

    /* ==================================================================
     * Canvas：iframe 装载与画布样式注入
     * ================================================================ */
    var CANVAS_CSS = [
        'html.ecod-builder .ecod-b-mod{display:block;position:relative}',
        'html.ecod-builder .ecod-b-cta{display:block;border:2px dashed rgba(59,110,240,.55);border-radius:10px;',
        '  padding:14px;text-align:center;color:#3b6ef0;font:12.5px/1.5 system-ui;background:rgba(59,110,240,.06)}',
        'html.ecod-builder .ecod-b-offhint{display:block;border:1px dashed rgba(120,130,150,.6);border-radius:10px;',
        '  padding:14px;text-align:center;color:#888;font:12.5px/1.5 system-ui}',
        'html.ecod-builder .ecod-b-seclabel{display:block;position:absolute;top:-11px;left:12px;z-index:5;',
        '  background:#3b6ef0;color:#fff;border-radius:5px;padding:1px 8px;font:11px/1.7 system-ui;white-space:nowrap}',
        'html.ecod-builder [data-bkind]{}',
        'html.ecod-builder .ecod-b-mod:not(.ecod-b-cur):hover{outline:1.5px dashed rgba(59,110,240,.7);outline-offset:1px}',
        'html.ecod-builder .ecod-sec:not(.ecod-b-cur):hover{outline:1.5px dashed rgba(59,110,240,.4);outline-offset:2px}',
        'html.ecod-builder .ecod-sec__row:not(.ecod-b-cur):hover{outline:1px dashed rgba(59,110,240,.35);outline-offset:4px}',
        'html.ecod-builder .ecod-sec__cell{position:relative}',
        'html.ecod-builder .ecod-sec__cell:not(.ecod-b-cur):hover{outline:1px dashed rgba(59,110,240,.5);outline-offset:3px}',
        'html.ecod-builder .ecod-b-cur{outline:2px solid #3b6ef0 !important;outline-offset:2px}',
        'html.ecod-builder .ecod-sec{scroll-margin-top:80px}',
        'html.ecod-builder .ecod-b-mod{scroll-margin-top:80px}',
        '.ecod-b-toolbar{position:absolute;top:-13px;right:8px;z-index:6;display:flex;gap:3px}',
        '.ecod-b-toolbar button{width:23px;height:23px;border-radius:6px;border:1px solid #d6dbe4;',
        '  background:#fff;color:#5c6675;font:12px/1 system-ui;cursor:pointer;display:flex;align-items:center;justify-content:center}',
        '.ecod-b-toolbar button:hover{color:#3b6ef0;border-color:#3b6ef0}',
        '.ecod-b-toolbar button svg{width:13px;height:13px}',
        'html.ecod-builder .ecod-b-selcta{cursor:pointer}',
        'html.ecod-builder .ecod-b-cta.ecod-b-cta-on{border-style:solid;background:rgba(59,110,240,.16)}',
        'html.ecod-builder .ecod-b-selcta:hover{border-style:solid;background:rgba(59,110,240,.12)}',
        'html[data-theme="dark"] .ecod-b-toolbar button{background:#1d2029;border-color:#2c303b;color:#98a1ad}',
        '.ecod-b-ind{height:0;border-top:3px solid #3b6ef0;box-shadow:0 0 0 3px rgba(59,110,240,.18);',
        '  border-radius:2px;pointer-events:none;margin:1px 0;position:relative;z-index:5}',
        '.ecod-b-ind--sec{border-top-width:3px;margin:6px 0}',
        '.ecod-b-ind--row{margin:9px 0}',
        '.ecod-b-ind--row::after{content:"＋ 行";position:absolute;right:8px;top:-10px;background:#3b6ef0;color:#fff;',
        '  border-radius:4px;padding:1px 7px;font:11px/15px system-ui}',
        '.ecod-b-ind--split{position:absolute;top:-6px;bottom:-6px;width:0;border-left:3px solid #3b6ef0;',
        '  box-shadow:0 0 0 3px rgba(59,110,240,.18);z-index:6;pointer-events:none}',
        '.ecod-b-colres{position:absolute;top:0;bottom:0;right:-10px;width:20px;cursor:col-resize;z-index:7}',
        '.ecod-b-colres::after{content:"";position:absolute;top:0;bottom:0;left:9px;width:2px;border-radius:2px;',
        '  background:rgba(59,110,240,0);transition:background .15s}',
        'html.ecod-builder .ecod-sec__row:hover .ecod-b-colres::after{background:rgba(59,110,240,.4)}',
        '.ecod-b-colres--on::after{background:#3b6ef0 !important;box-shadow:0 0 0 3px rgba(59,110,240,.15)}',
        '@media (max-width:900px){.ecod-b-colres{display:none !important}}',
        '.ecod-b-toolbar select{height:23px;border-radius:6px;border:1px solid #d6dbe4;background:#fff;color:#5c6675;',
        '  font:12px/1 system-ui;padding:0 2px;cursor:pointer;max-width:110px}',
        'html[data-theme="dark"] .ecod-b-toolbar select{background:#1d2029;border-color:#2c303b;color:#98a1ad}',
        'html.ecod-builder .ecod-sec{animation:ecod-b-in .25s ease}',
        '.ecod-b-adoptbar{position:fixed;top:14px;left:50%;transform:translateX(-50%);z-index:80;',
        '  display:flex;gap:12px;align-items:center;max-width:92%;box-sizing:border-box;',
        '  background:#fff;border:1px solid #3b6ef0;box-shadow:0 10px 34px rgba(20,30,60,.2);',
        '  border-radius:10px;padding:10px 14px;font:13px/1.6 system-ui;color:#3a4150}',
        '.ecod-b-adoptbar button{flex:none;border:none;background:#3b6ef0;color:#fff;border-radius:7px;',
        '  padding:6px 12px;font:12.5px/1.5 system-ui;cursor:pointer}',
        '.ecod-b-adoptbar__x{flex:none;cursor:pointer;color:#98a1ad;font:16px/1 system-ui;padding:2px 4px}',
        'html[data-theme="dark"] .ecod-b-adoptbar{background:#1d2029;border-color:#3b6ef0;color:#c9d2de}'
    ].join('\n');

    var frame = $('[data-frame]');
    var viewEl = $('[data-view]');

    function canvasUrl() {
        var meta = S.docs[S.docKey] || {};
        if (!meta.url) { return ''; }
        var sep = meta.url.indexOf('?') === -1 ? '?' : '&';
        return meta.url + sep + 'ecod_builder=1&token=' + encodeURIComponent(CFG.token)
            + '&r=' + (++S.reloadSeq);
    }

    /* 结构/参数变更 → 暂存自动保存 → 成功后重载画布（渲染暂存态 = 本地状态）。
       旧的重载渲染的是服务器已保存状态，会让删除/移动「复活」——已废弃。 */
    function scheduleReload() {
        clearTimeout(S.reloadTimer);
        S.reloadTimer = setTimeout(function () { stageSync(); }, 380);
    }
    /* 暂存同步：串行执行（在途时只排队），保证暂存写入单调——
       并发乱序会把旧状态后写进去，删除/移动就会「复活/跑位」。
       noReload=true 是列宽拖拽的热更新通道（§6.4）：只落暂存不重载，
       画布当前视觉已经是拖完的终态，重载只会闪一下。 */
    function stageSync(noReload) {
        if (S.stageBusy) { S.stageQueued = true; return; }
        S.stageBusy = true;
        var dirtyAreas = {};
        Object.keys(S.wgtStaged).forEach(function (id) {
            dirtyAreas[id] = S.wgt.areas[id] || [];
        });
        api('stage.save', {
            key: S.docKey,
            sections: S.sections,
            lock: S.lock ? 1 : 0,
            wgtAreas: dirtyAreas,
            wgtAside: S.wgt.aside
        }, function (j) {
            S.stageBusy = false;
            if (j.ok) {
                S.wgtStaged = {};
                if (!noReload) { reloadFrame(); }
                if (S.stageQueued) { S.stageQueued = false; stageSync(noReload); }
            } else {
                if (j.error) { toast(j.error); }
                S.stageQueued = false;
            }
        });
    }
    function dirtyArea(areaId) {
        S.wgtDirty[areaId] = 1;
        S.wgtStaged[areaId] = 1;
    }

    function reloadFrame() {
        if (!frame) { return; }
        if (!canvasUrl()) { $('[data-empty]').hidden = false; return; }
        /* 记录滚动与选中，重载后恢复 */
        try {
            if (S.idoc && S.idoc.scrollingElement) {
                S.frameScroll = S.idoc.scrollingElement.scrollTop;
            }
        } catch (e) { /* cross-origin 时忽略 */ }
        S.frameSel = S.sel;
        $('[data-loading]').hidden = false;
        frame.src = canvasUrl();
    }

    function onFrameLoad() {
        $('[data-loading]').hidden = true;
        var emptyEl = $('[data-empty]');
        emptyEl.hidden = !!frame.getAttribute('src');
        try {
            S.idoc = frame.contentDocument;
            S.iwin = frame.contentWindow;
        } catch (e) {
            S.idoc = null;
            S.iwin = null;
            toast('画布加载失败：编辑器与站点必须同源访问');
            return;
        }
        if (!S.idoc) { return; }

        S.idoc.documentElement.classList.add('ecod-builder');
        var style = S.idoc.createElement('style');
        style.textContent = CANVAS_CSS;
        S.idoc.head.appendChild(style);

        /* v4 回退页提示：画布内容不在构建器文档里（没有 cell），行列拖放无处命中。
           一键采纳把旧版区域内容转成 v6 文档后，自由布局才真正可用 */
        if (!S.sections.length) {
            var adoptBar = S.idoc.createElement('div');
            adoptBar.className = 'ecod-b-adoptbar';
            adoptBar.innerHTML = '<span>当前页面还是「旧版区域」布局：内容不在构建器文档里，'
                + '拖放与行列操作没有落点。</span>'
                + '<button type="button" data-badopt>一键转换为可视化布局</button>'
                + '<span class="ecod-b-adoptbar__x" data-badoptclose title="本次不再提示">×</span>';
            S.idoc.body.appendChild(adoptBar);
            adoptBar.querySelector('[data-badopt]').addEventListener('click', function () {
                if (S.adopting) { return; }
                S.adopting = true;
                api('doc.adopt', { key: S.docKey }, function (j) {
                    S.adopting = false;
                    if (!j.ok) { toast(j.error || '转换失败'); return; }
                    S.sections = normSections(j.sections || []);
                    clearDirty();
                    renderAll();
                    reloadFrame();
                    toast('已转换为可视化布局：现在可以随意拖放、分行分列了');
                });
            });
            adoptBar.querySelector('[data-badoptclose]').addEventListener('click', function () {
                adoptBar.remove();
            });
        }

        /* 交互接管：捕获阶段拦截（同源直接绑，不需要 postMessage 桥） */
        S.idoc.addEventListener('click', onCanvasClick, true);
        S.idoc.addEventListener('dblclick', function (e) { e.preventDefault(); }, true);
        S.idoc.addEventListener('submit', function (e) { e.preventDefault(); }, true);
        S.idoc.addEventListener('keydown', onKeydown, true);
        S.idoc.addEventListener('contextmenu', onCanvasContext, true);
        S.idoc.addEventListener('scroll', function () { closeCtxMenu(); }, true);
        bindFrameDrag();
        injectColResize();

        applySelection();
        try {
            if (S.idoc.scrollingElement) {
                S.idoc.scrollingElement.scrollTop = S.frameScroll || 0;
            }
        } catch (e) { /* ignore */ }
    }

    function nodeFor(kind, id) {
        if (!S.idoc) { return null; }
        return S.idoc.querySelector('[data-buid="' + id + '"]');
    }

    function applySelection() {
        if (!S.idoc) { return; }
        $$('.ecod-b-cur', S.idoc).forEach(function (el) {
            el.classList.remove('ecod-b-cur');
        });
        $$('.ecod-b-toolbar', S.idoc).forEach(function (el) { el.remove(); });
        $$('.ecod-b-selcta', S.idoc).forEach(function (el) { el.remove(); });
        if (!S.sel) { return; }
        var node = nodeFor(S.sel.kind, S.sel.id);
        if (!node) { return; }
        node.classList.add('ecod-b-cur');
        var bar = S.idoc.createElement('span');
        bar.className = 'ecod-b-toolbar';
        if (S.sel.kind === 'row') {
            /* 行工具条（§6.3）：＋列 / 预设 / 间距 / 上下移 / 复制 / 显隐 / 删除 */
            bar.innerHTML = '<button type="button" data-bact="addcell" title="＋列（最后一列对半拆）"' + (S.lock ? ' disabled' : '') + '>＋列</button>'
                + '<select data-btb="preset" title="套用行结构预设（模块按列宽顺序重分配）"' + (S.lock ? ' disabled' : '') + '>'
                + '<option value="">预设…</option>'
                + ROW_PRESETS.map(function (p) { return '<option value="' + esc(p.id) + '">' + esc(p.name) + '</option>'; }).join('')
                + '</select>'
                + '<select data-btb="gap" title="列间距"' + (S.lock ? ' disabled' : '') + '>'
                + '<option value="">间距…</option>'
                + [0, 8, 16, 24, 32, 48].map(function (g) { return '<option value="' + g + '">' + g + 'px</option>'; }).join('')
                + '</select>'
                + '<button type="button" data-bact="up" title="上移行"' + (S.lock ? ' disabled' : '') + '>' + ico('arrow-up') + '</button>'
                + '<button type="button" data-bact="down" title="下移行"' + (S.lock ? ' disabled' : '') + '>' + ico('arrow-down') + '</button>'
                + '<button type="button" data-bact="dup" title="复制行"' + (S.lock ? ' disabled' : '') + '>' + ico('copy') + '</button>'
                + '<button type="button" data-bact="eye" title="显隐行">' + ico('eye') + '</button>'
                + '<button type="button" data-bact="del" title="删除行"' + (S.lock ? ' disabled' : '') + '>' + ico('trash') + '</button>';
        } else if (S.sel.kind === 'cell') {
            bar.innerHTML = '<button type="button" data-bact="dup" title="复制列"' + (S.lock ? ' disabled' : '') + '>' + ico('copy') + '</button>'
                + '<button type="button" data-bact="del" title="删除列（模块并入左列）"' + (S.lock ? ' disabled' : '') + '>' + ico('trash') + '</button>';
        } else {
            bar.innerHTML = '<button type="button" data-bact="dup" title="复制">' + ico('copy') + '</button>'
                + '<button type="button" data-bact="eye" title="显隐">' + ico('eye') + '</button>'
                + '<button type="button" data-bact="del" title="删除">' + ico('trash') + '</button>';
        }
        node.appendChild(bar);
        if (S.sel.kind === 'row' && !S.lock) {
            var preSel = bar.querySelector('[data-btb="preset"]');
            if (preSel) {
                preSel.addEventListener('change', function () {
                    var pre = presetById(preSel.value);
                    preSel.value = '';
                    var preRow = pre && findPath(S.sel.id);
                    if (!pre || !preRow || !preRow.row) { return; }
                    if (rowHasNested(preRow.row)) {
                        toast('行内含嵌套行结构：请先在列上「拆掉嵌套行」再套用预设');
                        return;
                    }
                    captureUndo('rpre' + S.sel.id + Date.now());
                    applyPresetToRow(preRow.row, pre);
                    markDirty(); renderOutline(); scheduleReload();
                    toast('已套用预设「' + pre.name + '」：模块按列宽顺序重分配（⌘/Ctrl+Z 可撤销）');
                });
            }
            var gapSel = bar.querySelector('[data-btb="gap"]');
            if (gapSel) {
                gapSel.addEventListener('change', function () {
                    var v = parseInt(gapSel.value, 10);
                    gapSel.value = '';
                    var gapRow = isNaN(v) ? null : findPath(S.sel.id);
                    if (!gapRow || !gapRow.row) { return; }
                    captureUndo('rgap' + S.sel.id + Date.now());
                    gapRow.row.gap = v;
                    markDirty(); scheduleReload();
                });
            }
        }
        if (S.sel.kind === 'section') {
            var firstCell = node.querySelector('.ecod-sec__cell');
            if (firstCell && !S.lock) {
                var cta = S.idoc.createElement('div');
                cta.className = 'ecod-b-cta ecod-b-selcta';
                cta.textContent = '＋ 从左栏拖入或点击添加模块';
                firstCell.appendChild(cta);
            }
        }
        if (S.sel.kind === 'cell' && !S.lock) {
            /* 空列 CTA（§6.3）：统一的「拖入模块」提示 */
            var modsInCell = node.querySelectorAll('.ecod-b-mod[data-btype]');
            if (!modsInCell.length) {
                var cta2 = S.idoc.createElement('div');
                cta2.className = 'ecod-b-cta ecod-b-selcta';
                cta2.textContent = '拖入或点击添加模块';
                node.appendChild(cta2);
            }
        }
        try { node.scrollIntoView({ block: 'nearest' }); } catch (e) { /* ignore */ }
    }

    function onCanvasClick(e) {
        /* 画布内一切点击都拦在捕获阶段：不让链接跳转 / 站点 JS 产生副作用 */
        e.preventDefault();
        closeCtxMenu();
        var selcta = e.target.closest && e.target.closest('.ecod-b-selcta');
        if (selcta) {
            e.stopPropagation();
            setTab('lib');
            toast('在左栏点击或拖拽模块加入这个板块');
            return;
        }
        var act = e.target.closest && e.target.closest('[data-bact]');
        if (act) {
            e.stopPropagation();
            var host = act.closest('[data-buid]');
            if (host) {
                /* 路由：v4 区域模块（data-barea）≠ 布局模块 ≠ 板块——
                   之前只认 data-bkind，区域模块的工具条全部静默失效 */
                canvasAct(act.getAttribute('data-bact'), host.getAttribute('data-buid'), hostKind(host));
            }
            return;
        }
        var areaCta = e.target.closest && e.target.closest('[data-bareadrop]');
        if (areaCta) {
            e.stopPropagation();
            setTab('areas');
            toast('把「模块库」卡片拖进区域即可添加，或点区域行编辑');
            return;
        }
        var sel = e.target.closest && e.target.closest('[data-buid]');
        if (sel) {
            var wareaAttr = sel.getAttribute('data-barea');
            S.sel = wareaAttr
                ? { kind: 'wmod', id: sel.getAttribute('data-buid'), area: wareaAttr }
                : {
                    kind: sel.getAttribute('data-bkind') || 'module',
                    id: sel.getAttribute('data-buid')
                };
            S.itab = 'content';
            applySelection();
            renderOutline();
            renderAreasTab();
            renderInspector();
            return;
        }
        /* 空白处：取消选中 */
        S.sel = null;
        applySelection();
        renderOutline();
        renderInspector();
    }

    /* 乐观 DOM：结构操作先改快照容器，防抖重载随后做服务端确认 */
    function optimisticDom(act, id, newId) {
        if (!S.idoc) { return; }
        var node = S.idoc.querySelector('[data-buid="' + id + '"]');
        if (!node) { return; }
        try {
            if (act === 'dup' && newId) {
                var clone = node.cloneNode(true);
                clone.setAttribute('data-buid', newId);
                clone.classList.remove('ecod-b-cur');
                var tb = clone.querySelector('.ecod-b-toolbar');
                if (tb) { tb.remove(); }
                var cta = clone.querySelector('.ecod-b-selcta');
                if (cta) { cta.remove(); }
                node.parentNode.insertBefore(clone, node.nextSibling);
            } else if (act === 'eye') {
                node.style.opacity = node.style.opacity === '0.35' ? '' : '0.35';
            } else if (act === 'del') {
                node.remove();
            } else if ((act === 'up' || act === 'down') && node.parentNode) {
                var sib = act === 'up' ? node.previousElementSibling : node.nextElementSibling;
                while (sib && !(sib.classList && sib.classList.contains('ecod-b-mod'))) {
                    sib = act === 'up' ? sib.previousElementSibling : sib.nextElementSibling;
                }
                if (sib) {
                    if (act === 'up') { node.parentNode.insertBefore(node, sib); }
                    else { node.parentNode.insertBefore(sib, node); }
                }
            }
        } catch (err) { /* DOM 更新失败不阻断 Store，重载会兜底 */ }
    }
    function canvasAct(act, id, kind) {
        if (S.lock) {
            toast('布局已锁定：只允许修改模块参数，顶栏可解锁');
            return;
        }
        if (kind === 'wmod') {
            var wloc = findW(id);
            if (!wloc) { return; }
            var warr = S.wgt.areas[wloc.area];
            if (act === 'dup') {
                captureUndo('wdup' + id + Date.now());
                var cp = JSON.parse(JSON.stringify(warr[wloc.index]));
                cp.uid = uid(cp.type);
                warr.splice(wloc.index + 1, 0, cp);
                S.sel = { kind: 'wmod', id: cp.uid, area: wloc.area };
                optimisticDom('dup', id, cp.uid);
            } else if (act === 'eye') {
                captureUndo('weye' + id + Date.now());
                warr[wloc.index].on = warr[wloc.index].on ? 0 : 1;
                optimisticDom('eye', id);
            } else if (act === 'del') {
                captureUndo('wdel' + id + Date.now());
                var delName = modLabel(warr[wloc.index]);
                warr.splice(wloc.index, 1);
                if (S.sel && S.sel.id === id) { S.sel = null; }
                optimisticDom('del', id);
                toast('已移除「' + delName + '」（⌘/Ctrl+Z 可找回）');
            } else if (act === 'up' || act === 'down') {
                var wj = wloc.index + (act === 'up' ? -1 : 1);
                if (wj < 0 || wj >= warr.length) { return; }
                captureUndo('wmv' + id + Date.now());
                var wtmp = warr[wloc.index];
                warr[wloc.index] = warr[wj];
                warr[wj] = wtmp;
                optimisticDom(act, id);
            } else { return; }
            dirtyArea(wloc.area);
            markDirty('wgt');
            renderAreasTab(); renderInspector(); applySelection(); scheduleReload();
            return;
        }
        if (kind === 'module') {
            var loc = findMod(id);
            if (!loc) { return; }
            var arr = loc.arr;
            if (act === 'dup') {
                captureUndo('dup' + id + Date.now());
                var copy = JSON.parse(JSON.stringify(arr[loc.index]));
                copy.uid = uid(copy.type);
                arr.splice(loc.index + 1, 0, copy);
                S.sel = { kind: 'module', id: copy.uid };
                optimisticDom('dup', id, copy.uid);
            } else if (act === 'eye') {
                captureUndo('eye' + id + Date.now());
                arr[loc.index].on = arr[loc.index].on ? 0 : 1;
                optimisticDom('eye', id);
            } else if (act === 'del') {
                captureUndo('del' + id + Date.now());
                var delName = modLabel(arr[loc.index]);
                arr.splice(loc.index, 1);
                if (S.sel && S.sel.id === id) { S.sel = null; }
                optimisticDom('del', id);
                toast('已移除「' + delName + '」（⌘/Ctrl+Z 可找回）');
            } else if (act === 'up' || act === 'down') {
                var step = act === 'up' ? -1 : 1;
                var j = loc.index + step;
                if (j >= 0 && j < arr.length) {
                    captureUndo('mv' + id + Date.now());
                    var tmp = arr[loc.index];
                    arr[loc.index] = arr[j];
                    arr[j] = tmp;
                    optimisticDom(act, id);
                } else {
                    return; /* 已到边界，不算改动 */
                }
            }
            markDirty();
            renderAll();
            scheduleReload();
            return;
        }
        if (kind === 'row') {
            var prow = findPath(id);
            if (!prow || !prow.row || !prow.rowsArr) { return; }
            var rowsR = prow.rowsArr;
            var rIdx = rowsR.indexOf(prow.row);
            if (rIdx < 0) { return; }
            if (act === 'dup') {
                captureUndo('rdup' + id + Date.now());
                var rowCopy = JSON.parse(JSON.stringify(rowsR[rIdx]));
                rowCopy.uid = uid('row');
                (rowCopy.cells || []).forEach(function (cell) {
                    cell.uid = uid('cell');
                    reissueCellUids(cell);
                });
                rowsR.splice(rIdx + 1, 0, rowCopy);
                S.sel = { kind: 'row', id: rowCopy.uid };
                optimisticDom('dup', id, rowCopy.uid);
            } else if (act === 'eye') {
                captureUndo('reye' + id + Date.now());
                rowsR[rIdx].on = rowsR[rIdx].on ? 0 : 1;
                optimisticDom('eye', id);
            } else if (act === 'del') {
                captureUndo('rdel' + id + Date.now());
                rowsR.splice(rIdx, 1);
                if (S.sel && S.sel.id === id) { S.sel = null; }
                optimisticDom('del', id);
                toast('已删除行（⌘/Ctrl+Z 可找回）');
            } else if (act === 'up' || act === 'down') {
                var rj = rIdx + (act === 'up' ? -1 : 1);
                if (rj < 0 || rj >= rowsR.length) { return; }
                captureUndo('rmv' + id + Date.now());
                var rtmp = rowsR[rIdx];
                rowsR[rIdx] = rowsR[rj];
                rowsR[rj] = rtmp;
                optimisticDom(act, id);
            } else if (act === 'insertAbove' || act === 'insertBelow') {
                if (rowsR.length >= MAX_ROWS) { toast('单板块最多 ' + MAX_ROWS + ' 行'); return; }
                captureUndo('rins' + id + Date.now());
                var rNew = newRow([]);
                rowsR.splice(act === 'insertAbove' ? rIdx : rIdx + 1, 0, rNew);
                S.sel = { kind: 'row', id: rNew.uid };
            } else if (act === 'addcell') {
                /* ＋列：最后一列对半拆出新列（§6.3），上限 MAX_CELLS */
                var lastRow = rowsR[rIdx];
                var cellsR = lastRow.cells || (lastRow.cells = []);
                if (cellsR.length >= MAX_CELLS) { toast('单行最多 ' + MAX_CELLS + ' 列'); return; }
                captureUndo('raddc' + id + Date.now());
                var donorIdx = cellsR.length - 1;
                var donor = cellsR[donorIdx];
                var newC = newCell([]);
                if (!donor.wpx && donor.w >= 2) {
                    var half = Math.floor(donor.w / 2);
                    newC.w = donor.w - half;
                    donor.w = half;
                } else if (!donor.wpx && donor.w === 1) {
                    newC.w = 1; /* 1fr 拆不动：新列同权重（1:1） */
                } else {
                    /* px 列对半拆出 fr 列：px 减半但不低于下限 */
                    var newPx = Math.max(160, Math.floor(donor.wpx / 2));
                    newC.w = 1;
                    donor.wpx = newPx;
                }
                cellsR.push(newC);
                S.sel = { kind: 'cell', id: newC.uid };
                markDirty(); renderAll(); scheduleReload();
                return;
            } else { return; }
            markDirty();
            renderAll(); scheduleReload();
            return;
        }
        if (kind === 'cell') {
            var pcell = findPath(id);
            if (!pcell || !pcell.cell || !pcell.cellsArr) { return; }
            var cellsC = pcell.cellsArr;
            var cIdx = cellsC.indexOf(pcell.cell);
            if (cIdx < 0) { return; }
            var cell0 = cellsC[cIdx];
            if (act === 'up' || act === 'down') {
                /* 列在行内左右移动（大纲树 / Inspector 等价路径） */
                var cj = cIdx + (act === 'up' ? -1 : 1);
                if (cj < 0 || cj >= cellsC.length) { return; }
                captureUndo('cmv' + id + Date.now());
                var ctmp = cellsC[cIdx];
                cellsC[cIdx] = cellsC[cj];
                cellsC[cj] = ctmp;
            } else if (act === 'insertL' || act === 'insertR') {
                if (cellsC.length >= MAX_CELLS) { toast('单行最多 ' + MAX_CELLS + ' 列'); return; }
                captureUndo('cins' + id + Date.now());
                var cIns = newCell([]);
                cellsC.splice(act === 'insertL' ? cIdx : cIdx + 1, 0, cIns);
                S.sel = { kind: 'cell', id: cIns.uid };
            } else if (act === 'eye') {
                /* 列显隐 = 断点覆盖（md+sm 同步；桌面端列恒显示，折叠后隐藏） */
                captureUndo('ceye' + id + Date.now());
                var hidden = !!(cell0.ov && cell0.ov.md && cell0.ov.md.hide) || !!(cell0.ov && cell0.ov.sm && cell0.ov.sm.hide);
                cell0.ov = cell0.ov || {};
                ['md', 'sm'].forEach(function (dev) {
                    cell0.ov[dev] = cell0.ov[dev] || {};
                    if (hidden) { delete cell0.ov[dev].hide; } else { cell0.ov[dev].hide = 1; }
                    if (!Object.keys(cell0.ov[dev]).length) { delete cell0.ov[dev]; }
                });
                toast(hidden ? '已取消断点隐藏' : '该列将在平板/手机上隐藏（桌面端不受影响）');
            } else if (act === 'torows' || act === 'untorows') {
                /* 列 ⇄ 嵌套行（深度 2）：两个方向都无损——转回去时全部模块平铺回该列 */
                var hasRows = (cell0.rows || []).length > 0;
                if (act === 'torows') {
                    if (hasRows) { toast('该列已经是嵌套行结构'); return; }
                    if (!(cell0.mods || []).length) { toast('空列不需要嵌套行，先拖入模块'); return; }
                    captureUndo('ctor' + id + Date.now());
                    cell0.rows = [newRow(cell0.mods)];
                    cell0.mods = [];
                    S.sel = { kind: 'row', id: cell0.rows[0].uid };
                    markDirty(); renderAll(); scheduleReload();
                    toast('已转为嵌套行：选中该行用 ＋列 / 预设 拆分栏（深度上限 2）');
                    return;
                }
                if (!hasRows) { return; }
                captureUndo('cunt' + id + Date.now());
                var flatMods = [];
                (cell0.rows || []).forEach(function (nr) {
                    (nr.cells || []).forEach(function (nc) { flatMods = flatMods.concat(nc.mods || []); });
                });
                cell0.mods = flatMods;
                cell0.rows = [];
                S.sel = { kind: 'cell', id: cell0.uid };
                markDirty(); renderAll(); scheduleReload();
                toast('已拆掉嵌套行，' + flatMods.length + ' 个模块平铺回该列');
                return;
            } else if (act === 'dup') {
                if (cellsC.length >= MAX_CELLS) { toast('单行最多 ' + MAX_CELLS + ' 列'); return; }
                captureUndo('cdup' + id + Date.now());
                var cellCopy = JSON.parse(JSON.stringify(cell0));
                cellCopy.uid = uid('cell');
                reissueCellUids(cellCopy);
                cellsC.splice(cIdx + 1, 0, cellCopy);
                S.sel = { kind: 'cell', id: cellCopy.uid };
                optimisticDom('dup', id, cellCopy.uid);
            } else if (act === 'del') {
                if (cellsC.length <= 1) {
                    /* 最后一列：删列 = 删整行（不产生空行容器） */
                    canvasAct('del', pcell.row.uid, 'row');
                    return;
                }
                captureUndo('cdel' + id + Date.now());
                var merged = cell0.mods || [];
                var mergedRows = cell0.rows || [];
                cellsC.splice(cIdx, 1);
                /* 模块自动并入左列（首列并入右列）；嵌套行一并并入，
                   混合态由服务端 norm 归一（mods 并入首个嵌套列），不丢数据 */
                var into = cellsC[Math.max(0, cIdx - 1)];
                into.mods = (into.mods || []).concat(merged);
                if (mergedRows.length) { into.rows = (into.rows || []).concat(mergedRows); }
                if (S.sel && S.sel.id === id) { S.sel = null; }
                markDirty(); renderAll(); scheduleReload();
                toast('已删除列，' + merged.length + ' 个模块'
                    + (mergedRows.length ? '及 ' + mergedRows.length + ' 个嵌套行' : '')
                    + '并入相邻列（⌘/Ctrl+Z 可找回）');
                return;
            } else { return; }
            markDirty();
            renderAll(); scheduleReload();
            return;
        }
        if (kind === 'section') {
            var idx = -1;
            for (var i = 0; i < S.sections.length; i++) {
                if (S.sections[i].uid === id) { idx = i; break; }
            }
            if (idx < 0) { return; }
            if (act === 'dup') {
                captureUndo('sdup' + id + Date.now());
                var secCopy = JSON.parse(JSON.stringify(S.sections[idx]));
                secCopy.uid = uid('sec');
                secCopy.label = (secCopy.label || '板块') + ' 副本';
                /* v6：行/列/模块（含列内嵌套行）全部重发 uid，避免跨板块 uid 冲突 */
                (secCopy.rows || []).forEach(function (row) {
                    row.uid = uid('row');
                    (row.cells || []).forEach(function (cell) {
                        cell.uid = uid('cell');
                        reissueCellUids(cell);
                    });
                });
                S.sections.splice(idx + 1, 0, secCopy);
                S.sel = { kind: 'section', id: secCopy.uid };
                optimisticDom('dup', id, secCopy.uid);
            } else if (act === 'eye') {
                captureUndo('seye' + id + Date.now());
                S.sections[idx].on = S.sections[idx].on ? 0 : 1;
                optimisticDom('eye', id);
            } else if (act === 'del') {
                captureUndo('sdel' + id + Date.now());
                var secName = S.sections[idx] ? S.sections[idx].label : '';
                S.sections.splice(idx, 1);
                if (S.sel && S.sel.id === id) { S.sel = null; }
                optimisticDom('del', id);
                toast('已删除板块「' + secName + '」（⌘/Ctrl+Z 可找回）');
            } else if (act === 'up' || act === 'down') {
                var sStep = act === 'up' ? -1 : 1;
                var sj = idx + sStep;
                if (sj >= 0 && sj < S.sections.length) {
                    captureUndo('smv' + id + Date.now());
                    var sTmp = S.sections[idx];
                    S.sections[idx] = S.sections[sj];
                    S.sections[sj] = sTmp;
                    optimisticDom(act, id);
                } else {
                    return;
                }
            }
        }
        markDirty();
        renderAll();
        scheduleReload();
    }

    /* ==================================================================
     * 组件操作助手（Inspector 与右键菜单共用）
     * ================================================================ */
    function transformModule(kind, id, to) {
        if (S.lock) { toast('布局已锁定：只允许修改模块参数，顶栏可解锁'); return; }
        var toDef = CFG.defs[to] || { name: to, params: {} };
        if (kind === 'wmod') {
            var wloc = findW(id);
            if (!wloc) { return; }
            var wm = S.wgt.areas[wloc.area][wloc.index];
            var np = {};
            Object.keys(toDef.params || {}).forEach(function (k) {
                if (k in wm.params) { np[k] = wm.params[k]; }
            });
            captureUndo('xf' + id + Date.now());
            wm.type = to;
            wm.params = np;
            dirtyArea(wloc.area);
            markDirty('wgt');
            renderAreasTab(); renderInspector(); scheduleReload();
        } else {
            var loc = findMod(id);
            if (!loc) { return; }
            var m = loc.arr[loc.index];
            var np2 = {};
            Object.keys(toDef.params || {}).forEach(function (k) {
                if (k in m.params) { np2[k] = m.params[k]; }
            });
            captureUndo('xf' + id + Date.now());
            m.type = to;
            m.params = np2;
            markDirty();
            renderAll(); scheduleReload();
        }
        toast('已替换为「' + (toDef.name || to) + '」，同名参数已保留');
    }
    function moveW(uidKey, to) {
        var wloc = findW(uidKey);
        if (!wloc || to === wloc.area) { return; }
        captureUndo('wmoveSel' + uidKey + Date.now());
        var inst = S.wgt.areas[wloc.area].splice(wloc.index, 1)[0];
        (S.wgt.areas[to] = S.wgt.areas[to] || []).push(inst);
        dirtyArea(wloc.area); dirtyArea(to);
        S.sel = { kind: 'wmod', id: uidKey, area: to };
        markDirty('wgt');
        renderAreasTab(); renderInspector(); applySelection(); scheduleReload();
        toast('已移动到「' + wgtAreaLabel(to) + '」');
    }
    function clearSectionModules(id) {
        var sec = findSec(id);
        if (!sec) { return; }
        captureUndo('sclear' + id + Date.now());
        (sec.rows || []).forEach(function (row) {
            (row.cells || []).forEach(function (cell) { cell.mods = []; });
        });
        markDirty();
        renderAll(); scheduleReload();
        toast('板块已清空（⌘/Ctrl+Z 可找回）');
    }

    /* ==================================================================
     * 画布右键菜单（编辑器侧渲染；iframe 内只拦截事件）
     * ================================================================ */
    var ctxEl = null;
    var ctxState = null;
    function closeCtxMenu() {
        if (ctxEl) { ctxEl.hidden = true; }
        ctxState = null;
    }
    function hostKind(host) {
        if (host.getAttribute('data-barea')) { return 'wmod'; }
        return host.getAttribute('data-bkind') || 'module';
    }
    function ctxItemHtml(act, label, icon, opts) {
        opts = opts || {};
        var dis = opts.disabled || (S.lock && opts.structural);
        return '<button type="button" class="bld-ctx__it' + (opts.danger ? ' danger' : '')
            + (dis ? ' disabled' : '') + (opts.sub ? ' has-sub' : '')
            + '" data-ctxact="' + act + '" data-ctxarg="' + esc(opts.arg || '') + '">'
            + (icon ? '<span class="bld-ctx__ic">' + icon + '</span>' : '')
            + '<span class="bld-ctx__lb">' + esc(label) + '</span>'
            + (opts.hint ? '<small>' + esc(opts.hint) + '</small>' : '')
            + (opts.sub ? '<span class="bld-ctx__arr">▸</span>' : '') + '</button>';
    }
    function renderCtx() {
        var st = ctxState;
        if (!st) { return; }
        var body = $('[data-ctxbody]', ctxEl);
        var html = '';

        if (st.view === 'replace') {
            html = '<div class="bld-ctx__hd"><button type="button" class="bld-ctx__back" data-ctxact="back">‹ 返回</button>替换为</div>'
                + '<input type="search" class="bld-ctx__find" placeholder="搜索模块…">';
            var curType = '';
            if (st.kind === 'wmod') {
                var wcur = findW(st.id);
                curType = wcur ? S.wgt.areas[wcur.area][wcur.index].type : '';
            } else {
                var mcur = findMod(st.id);
                curType = mcur ? mcur.arr[mcur.index].type : '';
            }
            html += Object.keys(CFG.defs).map(function (tid) {
                if (tid === curType) { return ''; }
                return '<button type="button" class="bld-ctx__it" data-ctxact="doReplace" data-ctxarg="' + esc(tid) + '">'
                    + '<span class="bld-ctx__ic">' + ico(CFG.defs[tid].icon || 'layers') + '</span>'
                    + '<span class="bld-ctx__lb">' + esc(CFG.defs[tid].name) + '</span></button>';
            }).join('');
        } else if (st.view === 'moveTo') {
            html = '<div class="bld-ctx__hd"><button type="button" class="bld-ctx__back" data-ctxact="back">‹ 返回</button>移动到区域</div>';
            html += Object.keys(S.wgt.areaMeta).map(function (aid) {
                var cur = S.sel && S.sel.kind === 'wmod' && S.sel.area === aid;
                return '<button type="button" class="bld-ctx__it' + (cur ? ' disabled' : '') + '" data-ctxact="doMove" data-ctxarg="' + esc(aid) + '">'
                    + '<span class="bld-ctx__lb">' + esc(wgtAreaLabel(aid)) + '</span></button>';
            }).join('');
        } else {
            if (st.kind === 'section') {
                html = ctxItemHtml('edit', '编辑板块', ico('edit'))
                    + ctxItemHtml('insert', '插入模块', ico('plus'), { structural: true })
                    + ctxItemHtml('dup', '复制板块', ico('copy'), { structural: true })
                    + ctxItemHtml('up', '上移板块', ico('arrow-up'), { structural: true })
                    + ctxItemHtml('down', '下移板块', ico('arrow-down'), { structural: true })
                    + ctxItemHtml('eye', '隐藏板块', ico('eye'), { structural: true })
                    + ctxItemHtml('clear', '清空板块模块', ico('x'), { structural: true, danger: true })
                    + '<div class="bld-ctx__sep"></div>'
                    + ctxItemHtml('del', '删除板块', ico('trash'), { structural: true, danger: true });
            } else if (st.kind === 'row') {
                html = ctxItemHtml('edit', '编辑行', ico('edit'))
                    + ctxItemHtml('addcell', '＋列（对半拆）', ico('plus'), { structural: true })
                    + ctxItemHtml('saveRow', '存为行模板', ico('copy'), { structural: true })
                    + ctxItemHtml('dup', '复制行', ico('copy'), { structural: true })
                    + ctxItemHtml('up', '上移行', ico('arrow-up'), { structural: true })
                    + ctxItemHtml('down', '下移行', ico('arrow-down'), { structural: true })
                    + ctxItemHtml('eye', '隐藏行', ico('eye'), { structural: true })
                    + '<div class="bld-ctx__sep"></div>'
                    + ctxItemHtml('del', '删除行', ico('trash'), { structural: true, danger: true });
            } else if (st.kind === 'cell') {
                var ctxCell = findPath(st.id);
                var ctxHasRows = !!(ctxCell && ctxCell.cell && (ctxCell.cell.rows || []).length);
                html = ctxItemHtml('edit', '编辑列', ico('edit'))
                    + ctxItemHtml('insert', '插入模块', ico('plus'), { structural: true })
                    + (ctxHasRows
                        ? ctxItemHtml('untorows', '拆掉嵌套行（模块平铺回列）', ico('repeat'), { structural: true })
                        : ctxItemHtml('torows', '转为嵌套行（列内分栏）', ico('repeat'), { structural: true }))
                    + ctxItemHtml('dup', '复制列', ico('copy'), { structural: true })
                    + '<div class="bld-ctx__sep"></div>'
                    + ctxItemHtml('del', '删除列（模块并入左列）', ico('trash'), { structural: true, danger: true });
            } else {
                html = ctxItemHtml('edit', '编辑组件', ico('edit'))
                    + ctxItemHtml('replace', '替换组件', ico('repeat'), { structural: true, sub: true })
                    + ctxItemHtml('insert', '在此插入模块', ico('plus'), { structural: true })
                    + ctxItemHtml('dup', '复制组件', ico('copy'), { structural: true })
                    + ctxItemHtml('eye', st.kind === 'wmod' ? '启停' : '显隐', ico('eye'), { structural: true })
                    + ctxItemHtml('up', '上移', ico('arrow-up'), { structural: true })
                    + ctxItemHtml('down', '下移', ico('arrow-down'), { structural: true });
                if (st.kind === 'wmod') {
                    html += ctxItemHtml('moveTo', '移动到区域', ico('swap'), { structural: true, sub: true });
                }
                html += '<div class="bld-ctx__sep"></div>'
                    + ctxItemHtml('del', st.kind === 'wmod' ? '移除组件' : '删除组件', ico('trash'), { structural: true, danger: true });
            }
            if (S.lock) {
                html += '<div class="bld-ctx__lock">布局已锁定：结构操作不可用</div>';
            }
        }
        body.innerHTML = html;
        var find = $('.bld-ctx__find', ctxEl);
        if (find) {
            find.addEventListener('input', function () {
                var q = find.value.trim().toLowerCase();
                $$('[data-ctxact="doReplace"]', body).forEach(function (b) {
                    var t = CFG.defs[b.getAttribute('data-ctxarg')] || { name: '' };
                    b.style.display = !q || (t.name || '').toLowerCase().indexOf(q) > -1 ? '' : 'none';
                });
            });
            setTimeout(function () { find.focus(); }, 30);
        }
    }
    function openCtxAt(x, y) {
        ctxEl.hidden = false;
        ctxEl.style.left = '0px';
        ctxEl.style.top = '0px';
        var w = ctxEl.offsetWidth, h = ctxEl.offsetHeight;
        ctxEl.style.left = Math.max(8, Math.min(x, window.innerWidth - w - 8)) + 'px';
        ctxEl.style.top = Math.max(8, Math.min(y, window.innerHeight - h - 8)) + 'px';
    }
    function openCtxMenu(kind, id, x, y) {
        if (!ctxEl) {
            ctxEl = document.createElement('div');
            ctxEl.className = 'bld-ctxmenu';
            ctxEl.hidden = true;
            ctxEl.innerHTML = '<div class="bld-ctxmenu__body" data-ctxbody></div>';
            document.body.appendChild(ctxEl);
            ctxEl.addEventListener('click', function (e) {
                var back = e.target.closest && e.target.closest('[data-ctxact="back"]');
                if (back) { ctxState.view = 'root'; renderCtx(); return; }
                var it = e.target.closest && e.target.closest('[data-ctxact]');
                if (!it || it.classList.contains('disabled')) { return; }
                var act = it.getAttribute('data-ctxact');
                var arg = it.getAttribute('data-ctxarg');
                var st = ctxState;
                closeCtxMenu();
                if (!st) { return; }
                if (act === 'replace') { ctxState = st; st.view = 'replace'; renderCtx(); openCtxAt(st.x, st.y); }
                else if (act === 'moveTo') { ctxState = st; st.view = 'moveTo'; renderCtx(); openCtxAt(st.x, st.y); }
                else if (act === 'doReplace') { transformModule(st.kind, st.id, arg); }
                else if (act === 'doMove') { moveW(st.id, arg); }
                else if (act === 'edit') {
                    S.sel = { kind: st.kind, id: st.id, area: st.kind === 'wmod' ? (findW(st.id) || {}).area : undefined };
                    S.itab = 'content';
                    applySelection(); renderOutline(); renderAreasTab(); renderInspector();
                }
                else if (act === 'insert') {
                    S.pendingInsert = { kind: st.kind, id: st.id };
                    setTab('lib');
                    toast('在左栏点击模块，插入到该位置（Esc 取消）');
                }
                else if (act === 'dup') { canvasAct('dup', st.id, st.kind); }
                else if (act === 'addcell') { canvasAct('addcell', st.id, st.kind); }
                else if (act === 'torows') { canvasAct('torows', st.id, st.kind); }
                else if (act === 'untorows') { canvasAct('untorows', st.id, st.kind); }
                else if (act === 'saveRow') { saveRowTemplate(st.id, ''); }
                else if (act === 'eye') { canvasAct('eye', st.id, st.kind); }
                else if (act === 'up') { canvasAct('up', st.id, st.kind); }
                else if (act === 'down') { canvasAct('down', st.id, st.kind); }
                else if (act === 'del') { canvasAct('del', st.id, st.kind); }
                else if (act === 'clear') {
                    showModal('清空这个板块？', '板块内的全部模块将被移除（可用撤销找回）。', function (pick, done) {
                        if (pick === 'save') { clearSectionModules(st.id); }
                        done();
                    });
                }
            });
        }
        ctxState = { kind: kind, id: id, view: 'root', x: x, y: y };
        renderCtx();
        openCtxAt(x, y);
    }
    function onCanvasContext(e) {
        e.preventDefault();
        var host = e.target.closest && e.target.closest('[data-buid]');
        var x = e.clientX + frameRect().left;
        var y = e.clientY + frameRect().top;
        if (host) {
            var kind = hostKind(host);
            var id = host.getAttribute('data-buid');
            S.sel = { kind: kind, id: id, area: host.getAttribute('data-barea') || undefined };
            S.itab = 'content';
            applySelection(); renderOutline(); renderAreasTab(); renderInspector();
            openCtxMenu(kind, id, x, y);
        } else {
            closeCtxMenu();
        }
    }

    /* ==================================================================
     * 拖拽（指针式，横跨编辑器与画布两个文档）
     * ================================================================ */
    var drag = null;
    var ghost = document.createElement('div');
    ghost.className = 'bld-ghost';
    ghost.hidden = true;
    document.body.appendChild(ghost);

    function evToFrame(e) {
        var r = frame.getBoundingClientRect();
        var inFrame = e.view === S.iwin;
        return { x: e.clientX - r.left, y: e.clientY - r.top,
            px: e.clientX + (inFrame ? r.left : 0),
            py: e.clientY + (inFrame ? r.top : 0), inFrame: inFrame };
    }
    function frameRect() { return frame.getBoundingClientRect(); }

    /* rAF 节流：一帧最多一次命中计算，ghost 跟手不受影响 */
    var dragRaf = 0;
    var dragLastEv = null;
    function dragMove(e) {
        if (!drag) { return; }
        dragLastEv = e;
        if (dragRaf) { return; }
        dragRaf = (S.iwin && e.view === S.iwin ? S.iwin : window).requestAnimationFrame(function () {
            dragRaf = 0;
            if (drag && dragLastEv) { dragMoveNow(dragLastEv); }
        });
    }
    function dragMoveNow(e) {
        if (!drag) { return; }
        var p = evToFrame(e);
        ghost.style.left = (p.px + 12) + 'px';
        ghost.style.top = (p.py + 10) + 'px';
        /* 父文档目标：区域行（添加/移动）、回收条（删除） */
        var pt = null;
        if (drag.src.mode === 'new' || drag.src.mode === 'wmove') {
            var tEl = e.target;
            if (tEl && tEl.closest) {
                if (tEl.closest('[data-wtrash]')) { pt = { kind: 'wdel' }; }
                else {
                    var rowEl = tEl.closest('[data-areadrop]');
                    if (rowEl) { pt = { kind: 'warea-row', area: rowEl.getAttribute('data-areadrop') }; }
                }
            }
        }
        if (pt) {
            drag.tg = pt;
        } else {
            var over = p.x >= -40 && p.y >= -40
                && p.x <= frameRect().width + 40 && p.y <= frameRect().height + 40;
            drag.tg = over ? computeDrop(p.x, p.y) : null;
        }
        highlightDropTargets(pt);
    }
    function highlightDropTargets(pt) {
        $$('.bld-warea').forEach(function (el) {
            el.classList.toggle('bld-drop-on',
                !!pt && pt.kind === 'warea-row' && el.getAttribute('data-areadrop') === pt.area);
        });
        var trash = $('[data-wtrash]');
        if (trash) { trash.classList.toggle('bld-drop-on', !!pt && pt.kind === 'wdel'); }
        if (S.idoc) {
            $$('[data-bareadrop]', S.idoc).forEach(function (el) {
                el.classList.toggle('ecod-b-cta-on',
                    !!pt && pt.kind === 'warea' && el.getAttribute('data-bareadrop') === pt.area);
            });
        }
    }
    function dragEnd() {
        if (!drag) { return; }
        var dg = drag;
        var tg = drag.tg;
        drag = null;
        ghost.hidden = true;
        clearInd();
        highlightDropTargets(null);
        document.body.classList.remove('bld-dragging');
        document.body.style.userSelect = '';
        if (S.idoc) { S.idoc.documentElement.style.userSelect = ''; }
        if (!tg) { return; }
        /* performDrop 内部读取 drag.src——临时恢复引用执行，结束后必定清空 */
        drag = dg;
        try {
            performDrop(tg);
        } catch (err) {
            toast('拖放执行出错：' + (err && err.message ? err.message : err));
            if (window.console && console.error) { console.error('[builder] performDrop', err); }
            renderAll();
        } finally {
            drag = null;
        }
    }

    function bindDragSources() {
        /* 模块库（编辑器文档） */
        document.addEventListener('pointerdown', function (e) {
            /* 行模板卡上的「删除」钮不触发拖拽 */
            if (e.target.closest && e.target.closest('[data-srowdel]')) { return; }
            var card = e.target.closest && e.target.closest('[data-new]');
            if (card && e.button === 0 && CFG.canvas) {
                if (S.lock) { toast('布局已锁定：只允许修改模块参数，顶栏可解锁'); return; }
                startDrag(e, {
                    mode: 'new', type: card.getAttribute('data-new'),
                    label: card.getAttribute('data-label') || card.textContent.trim()
                });
                return;
            }
            /* 区域实例行：拖动 = 移动 / 拖到回收条 = 删除 */
            var wrow = e.target.closest && e.target.closest('[data-wuid]');
            if (wrow && e.button === 0) {
                if (S.lock) { toast('布局已锁定：拖动排序不可用，顶栏可解锁'); return; }
                var nm = wrow.querySelector('.bld-wrow__nm');
                startDrag(e, {
                    mode: 'wmove', uid: wrow.getAttribute('data-wuid'),
                    area: wrow.getAttribute('data-warea'),
                    label: nm ? nm.textContent : '模块'
                });
            }
        });
        /* 指针 move/up 常驻挂在两个文档上：落在哪个文档就由哪个文档派发 */
        window.addEventListener('pointermove', dragMove, true);
        window.addEventListener('pointerup', dragEnd, true);
    }
    function bindFrameDrag() {
        if (!S.idoc) { return; }
        /* 列宽拖拽手柄（§6.4）：桌面段、未锁定时拖动改相邻列宽（热更新通道）。
           手柄不是 .ecod-b-mod 后代，与模块拖拽监听天然无冲突 */
        S.idoc.addEventListener('pointerdown', function (e) {
            var h = e.target.closest && e.target.closest('.ecod-b-colres');
            if (!h || e.button !== 0) { return; }
            if (S.lock) { toast('布局已锁定：列宽不可调整，顶栏可解锁'); return; }
            e.preventDefault();
            startColResize(h, e);
        }, true);
        /* 双击列宽手柄：复位等分（§6.4） */
        S.idoc.addEventListener('dblclick', function (e) {
            var h = e.target.closest && e.target.closest('.ecod-b-colres');
            if (!h || S.lock) { return; }
            var rEl = h.parentNode && h.parentNode.closest ? h.parentNode.closest('.ecod-sec__row') : null;
            if (rEl && rEl.getAttribute('data-buid')) { equalizeRow(rEl.getAttribute('data-buid')); }
        }, true);
        /* 板块拖动：抓板块左上角的标签整块移动 */
        S.idoc.addEventListener('pointerdown', function (e) {
            var label = e.target.closest && e.target.closest('.ecod-b-seclabel');
            if (!label || e.button !== 0) { return; }
            if (S.lock) { toast('布局已锁定：拖动排序不可用，顶栏可解锁'); return; }
            e.preventDefault();
            var secEl = label.closest('.ecod-sec[data-buid]');
            if (!secEl) { return; }
            startDrag(e, {
                mode: 'section-move', uid: secEl.getAttribute('data-buid'),
                label: '板块 · ' + (label.textContent || '').split(' · ')[0]
            });
        }, true);
        S.idoc.addEventListener('pointerdown', function (e) {
            var mod = e.target.closest && e.target.closest('.ecod-b-mod[data-btype]');
            if (!mod || e.button !== 0) { return; }
            if (S.lock) { toast('布局已锁定：拖动排序不可用，顶栏可解锁'); return; }
            e.preventDefault();
            var barea = mod.getAttribute('data-barea');
            if (barea) {
                /* v4 区域模块：拖到其他区域 / 回收条 */
                startDrag(e, {
                    mode: 'wmove', uid: mod.getAttribute('data-buid'), area: barea,
                    type: mod.getAttribute('data-btype'),
                    label: (CFG.defs[mod.getAttribute('data-btype')] || {}).name || mod.getAttribute('data-btype'),
                    iframe: true
                });
            } else {
                startDrag(e, {
                    mode: 'move', uid: mod.getAttribute('data-buid'),
                    type: mod.getAttribute('data-btype'),
                    label: mod.getAttribute('data-btype') in CFG.defs
                        ? CFG.defs[mod.getAttribute('data-btype')].name : mod.getAttribute('data-btype'),
                    iframe: true
                });
            }
        }, true);
        S.iwin.addEventListener('pointermove', dragMove, true);
        S.iwin.addEventListener('pointerup', dragEnd, true);
    }
    function startDrag(e, src) {
        if (drag) { return; }
        drag = { tg: null, src: src };
        ghost.textContent = src.label;
        ghost.hidden = false;
        document.body.style.userSelect = 'none';
        document.body.classList.add('bld-dragging');
        if (S.idoc) { S.idoc.documentElement.style.userSelect = 'none'; }
        dragMove(e);
    }

    /* ==================================================================
     * 列宽拖拽（§6.4，唯一热更新通道）
     * pointermove 只改 iframe 内该行 --row-cols 内联值（rAF 节流，零往返）；
     * pointerup 才提交 Store + 只暂存不重载。期间若发生结构变更重载，
     * 重载后以 Store 为准，热更新状态自然清空。
     * ================================================================ */
    var colDrag = null;
    function injectColResize() {
        if (!S.idoc) { return; }
        $$('.ecod-sec__row', S.idoc).forEach(function (rowEl) {
            if (rowEl.getAttribute('data-bres') === '1') { return; }
            rowEl.setAttribute('data-bres', '1');
            /* 瀑布流行列宽由 columns 均分，拖拽手柄不适用 */
            if (rowEl.classList.contains('ecod-sec__row--masonry')) { return; }
            var rCells = $$('.ecod-sec__cell', rowEl);
            if (rCells.length < 2) { return; }
            rCells.forEach(function (cellEl, ci) {
                if (ci === rCells.length - 1) { return; }
                var h = S.idoc.createElement('span');
                h.className = 'ecod-b-colres';
                cellEl.appendChild(h);
            });
        });
    }
    function startColResize(handle, e) {
        if (S.device !== 'desktop') {
            toast('列宽拖拽仅在桌面段可用（断点折叠行为由行/列的断点覆盖控制）');
            return;
        }
        var cellEl = handle.parentNode;
        var rowEl = cellEl.closest ? cellEl.closest('.ecod-sec__row') : null;
        if (!rowEl) { return; }
        var cellEls = $$('.ecod-sec__cell', rowEl);
        var di = cellEls.indexOf(cellEl);
        if (di < 0 || di >= cellEls.length - 1) { return; }
        var pa = findPath(cellEls[di].getAttribute('data-buid'));
        var pb = findPath(cellEls[di + 1].getAttribute('data-buid'));
        if (!pa || !pa.cell || !pb || !pb.cell || !pa.row || pa.row !== pb.row) { return; }
        /* Store 下标统一从容器数组换算（findPath 不再携带 index.c） */
        var ai = pa.cellsArr.indexOf(pa.cell);
        var bi = pb.cellsArr.indexOf(pb.cell);
        if (ai < 0 || bi < 0) { return; }
        var inFrame = e.view === S.iwin;
        colDrag = {
            handle: handle,
            rowEl: rowEl,
            elA: cellEls[di], elB: cellEls[di + 1],
            cellsStore: pa.cellsArr,
            i: ai, j: bi,
            a: pa.cell, b: pb.cell,
            startX: e.clientX + (inFrame ? 0 : frameRect().left),
            vals: null, raf: 0
        };
        handle.classList.add('ecod-b-colres--on');
        document.body.style.userSelect = 'none';
        window.addEventListener('pointermove', colDragMove, true);
        window.addEventListener('pointerup', colDragEnd, true);
        S.iwin.addEventListener('pointermove', colDragMove, true);
        S.iwin.addEventListener('pointerup', colDragEnd, true);
    }
    function colDragMove(e) {
        if (!colDrag) { return; }
        var inFrame = e.view === S.iwin;
        var fx = e.clientX + (inFrame ? 0 : frameRect().left);
        var dx = fx - colDrag.startX;
        var A = colDrag.a, B = colDrag.b;
        var live = [];
        for (var k = 0; k < colDrag.cellsStore.length; k++) { live.push(colDrag.cellsStore[k]); }
        if (!A.wpx && !B.wpx) {
            var wa = A.w || 1, wb = B.w || 1;
            var ra = colDrag.elA.getBoundingClientRect().width;
            var rb = colDrag.elB.getBoundingClientRect().width;
            var pxPerFr = (ra + rb) / (wa + wb);
            if (!(pxPerFr > 0)) { return; }
            var newWa = Math.min(wa + wb - 0.2, Math.max(0.2, wa + dx / pxPerFr));
            live[colDrag.i] = { w: newWa };
            live[colDrag.j] = { w: wa + wb - newWa };
        } else if (A.wpx) {
            live[colDrag.i] = { wpx: Math.min(640, Math.max(160, Math.round(A.wpx + dx))) };
        } else {
            live[colDrag.j] = { wpx: Math.min(640, Math.max(160, Math.round(B.wpx - dx))) };
        }
        colDrag.vals = live;
        if (!colDrag.raf) {
            colDrag.raf = S.iwin.requestAnimationFrame(function () {
                colDrag.raf = 0;
                if (!colDrag || !colDrag.vals) { return; }
                var parts = colDrag.vals.map(function (c) {
                    return c.wpx ? c.wpx + 'px' : 'minmax(0,' + (Math.round(c.w * 100) / 100) + 'fr)';
                });
                colDrag.rowEl.style.setProperty('--row-cols', parts.join(' '));
            });
        }
    }
    function colDragEnd() {
        if (!colDrag) { return; }
        var st = colDrag;
        colDrag = null;
        window.removeEventListener('pointermove', colDragMove, true);
        window.removeEventListener('pointerup', colDragEnd, true);
        S.iwin.removeEventListener('pointermove', colDragMove, true);
        S.iwin.removeEventListener('pointerup', colDragEnd, true);
        document.body.style.userSelect = '';
        st.handle.classList.remove('ecod-b-colres--on');
        if (!st.vals) { return; } /* 原地松手：无变更不提交 */
        var A = st.a, B = st.b;
        captureUndo('colres' + Date.now());
        if (!A.wpx && !B.wpx) {
            var sum = (A.w || 1) + (B.w || 1);
            var na = Math.min(sum - 1, Math.max(1, Math.round(st.vals[st.i].w)));
            A.w = na;
            B.w = sum - na;
        } else if (A.wpx) {
            A.wpx = Math.min(640, Math.max(160, Math.round(st.vals[st.i].wpx)));
        } else {
            B.wpx = Math.min(640, Math.max(160, Math.round(st.vals[st.j].wpx)));
        }
        markDirty();
        renderOutline();
        renderInspector();
        stageSync(true); /* 只暂存不重载：画布视觉已是终态（§6.4 step2） */
    }

    var ind = null;
    function clearInd() {
        if (ind && ind.parentNode) { ind.parentNode.removeChild(ind); }
    }
    function computeDrop(fx, fy) {
        if (!S.idoc) { return null; }
        clearInd();
        /* v4 区域投放点（空区域 CTA）：优先于布局栏命中（行模板不进 v4 区域） */
        if ((drag.src.mode === 'new' && !isRowTemplateType(drag.src.type)) || drag.src.mode === 'wmove') {
            var ctaHit = null;
            $$('[data-bareadrop]', S.idoc).forEach(function (el) {
                var r = el.getBoundingClientRect();
                if (fx >= r.left - 10 && fx <= r.right + 10 && fy >= r.top - 10 && fy <= r.bottom + 10) {
                    ctaHit = el;
                }
            });
            if (ctaHit) {
                return { kind: 'warea', area: ctaHit.getAttribute('data-bareadrop') };
            }
        }
        /* v4 区域模块之间的插入定位：画布只渲染启用实例，
           DOM 启用序在 performDrop 里映射回 Store（Store 含停用实例） */
        if ((drag.src.mode === 'new' && !isRowTemplateType(drag.src.type)) || drag.src.mode === 'wmove') {
            var best4 = null;
            $$('.ecod-b-mod[data-barea]', S.idoc).forEach(function (el) {
                if (drag.src.mode === 'wmove' && el.getAttribute('data-buid') === drag.src.uid) { return; }
                var r4 = el.getBoundingClientRect();
                var dx4 = fx < r4.left ? r4.left - fx : fx > r4.right ? fx - r4.right : 0;
                var dy4 = fy < r4.top ? r4.top - fy : fy > r4.bottom ? fy - r4.bottom : 0;
                var d4 = Math.hypot(dx4, dy4);
                if (!best4 || d4 < best4.d) { best4 = { el: el, d: d4 }; }
            });
            if (best4 && best4.d < 110) {
                var areaId = best4.el.getAttribute('data-barea');
                var r5 = best4.el.getBoundingClientRect();
                var above = fy < r5.top + r5.height / 2;
                ind = S.idoc.createElement('div');
                ind.className = 'ecod-b-ind';
                if (above) { best4.el.parentNode.insertBefore(ind, best4.el); }
                else { best4.el.parentNode.insertBefore(ind, best4.el.nextSibling); }
                return { kind: 'warea', area: areaId, beforeUid: best4.el.getAttribute('data-buid'), above: above };
            }
        }

        var isSection = drag.src.type === 'section' || drag.src.mode === 'section-move';

        if (isSection) {
            var allSecs = $$('.ecod-sec[data-buid]', S.idoc);
            /* 移动既有板块时把它自己从参照列表里摘掉，避免指示条抖动 */
            var secs = allSecs.filter(function (el) {
                return drag.src.mode !== 'section-move' || el.getAttribute('data-buid') !== drag.src.uid;
            });
            var idx = secs.length;
            for (var i = 0; i < secs.length; i++) {
                var r = secs[i].getBoundingClientRect();
                if (fy < r.top + r.height / 2) { idx = i; break; }
            }
            ind = S.idoc.createElement('div');
            ind.className = 'ecod-b-ind ecod-b-ind--sec';
            if (idx < secs.length) {
                secs[idx].parentNode.insertBefore(ind, secs[idx]);
            } else if (secs.length) {
                secs[secs.length - 1].parentNode.insertBefore(ind, secs[secs.length - 1].nextSibling);
            } else {
                var mainEl = S.idoc.getElementById('ecod-main') || S.idoc.body;
                mainEl.appendChild(ind);
            }
            return drag.src.mode === 'section-move'
                ? { kind: 'section-move', idx: idx }
                : { kind: 'section', idx: idx };
        }

        /* 行模板拖放（§6.2 row-new；预置预设 + 我的行模板）：命中板块后按行间隙定位插入点 */
        if (isRowTemplateType(drag.src.type)) {
            var pSecs = $$('.ecod-sec[data-buid]', S.idoc);
            if (!pSecs.length) {
                return { kind: 'section-new-row' };
            }
            var pBest = null;
            pSecs.forEach(function (el) {
                var pr = el.getBoundingClientRect();
                var pdx = fx < pr.left ? pr.left - fx : fx > pr.right ? fx - pr.right : 0;
                var pdy = fy < pr.top ? pr.top - fy : fy > pr.bottom ? fy - pr.bottom : 0;
                var pd = Math.hypot(pdx, pdy);
                if (!pBest || pd < pBest.d) { pBest = { el: el, d: pd }; }
            });
            if (!pBest || pBest.d > 140) { return null; }
            /* 只认板块直属行：列内嵌套行不参与顶层 rownew 定位（§9 嵌套纪律） */
            var pRows = [];
            $$('.ecod-sec__inner > .ecod-sec__row[data-buid]', pBest.el).forEach(function (el) {
                pRows.push(el);
            });
            var pIdx = pRows.length;
            for (var ri = 0; ri < pRows.length; ri++) {
                var rr = pRows[ri].getBoundingClientRect();
                if (fy < rr.top + rr.height / 2) { pIdx = ri; break; }
            }
            ind = S.idoc.createElement('div');
            ind.className = 'ecod-b-ind ecod-b-ind--row';
            var pInner = pBest.el.querySelector('.ecod-sec__inner');
            if (pInner) {
                if (pIdx < pRows.length) { pInner.insertBefore(ind, pRows[pIdx]); }
                else { pInner.appendChild(ind); }
            }
            return { kind: 'rownew', secUid: pBest.el.getAttribute('data-buid'), idx: pIdx };
        }

        var best = null;
        $$('.ecod-sec__cell', S.idoc).forEach(function (col) {
            var r = col.getBoundingClientRect();
            var dx = fx < r.left ? r.left - fx : fx > r.right ? fx - r.right : 0;
            var dy = fy < r.top ? r.top - fy : fy > r.bottom ? fy - r.bottom : 0;
            var d = Math.hypot(dx, dy);
            if (!best || d < best.d) { best = { col: col, d: d }; }
        });
        if (!best || best.d > 110) {
            /* 一个板块都没有：模块直接落成新板块 */
            if (!$$('.ecod-sec[data-buid]', S.idoc).length) {
                return { kind: 'section-new-mod' };
            }
            /* 兜底（「拖到随意位置」）：落在行间隙 / 板块留白时，就近建一行一列承接，
               不再静默取消——拖到板块附近任何位置都有确定结果 */
            var aBest = null;
            $$('.ecod-sec[data-buid]', S.idoc).forEach(function (el) {
                var ar0 = el.getBoundingClientRect();
                var adx = fx < ar0.left ? ar0.left - fx : fx > ar0.right ? fx - ar0.right : 0;
                var ady = fy < ar0.top ? ar0.top - fy : fy > ar0.bottom ? fy - ar0.bottom : 0;
                var ad = Math.hypot(adx, ady);
                if (!aBest || ad < aBest.d) { aBest = { el: el, d: ad }; }
            });
            if (aBest && aBest.d <= 320) {
                var aRows = $$('.ecod-sec__inner > .ecod-sec__row[data-buid]', aBest.el);
                var aIdx = aRows.length;
                for (var ai2 = 0; ai2 < aRows.length; ai2++) {
                    var ar2 = aRows[ai2].getBoundingClientRect();
                    if (fy < ar2.top + ar2.height / 2) { aIdx = ai2; break; }
                }
                ind = S.idoc.createElement('div');
                ind.className = 'ecod-b-ind ecod-b-ind--row';
                var aInner = aBest.el.querySelector('.ecod-sec__inner');
                if (aInner) {
                    if (aIdx < aRows.length) { aInner.insertBefore(ind, aRows[aIdx]); }
                    else { aInner.appendChild(ind); }
                }
                return { kind: 'cell-autorow', secUid: aBest.el.getAttribute('data-buid'), idx: aIdx };
            }
            return null;
        }
        var colEl = best.col;
        var secEl = colEl.closest('.ecod-sec[data-buid]');
        if (!secEl) { return null; }
        /* cell-split（§6.2）：指针进入列左/右边缘 ≤24px 竖向热区 → 分裂新列；
           行已满列时退回普通插入，避免拖到边上才报错 */
        var spSide = null;
        var rCell2 = colEl.getBoundingClientRect();
        if (fy >= rCell2.top - 8 && fy <= rCell2.bottom + 8) {
            if (fx <= rCell2.left + 24) { spSide = 'left'; }
            else if (fx >= rCell2.right - 24) { spSide = 'right'; }
        }
        if (spSide) {
            var spProbe = findPath(colEl.getAttribute('data-buid'));
            if (!spProbe || !spProbe.row || (spProbe.row.cells || []).length >= MAX_CELLS) { spSide = null; }
        }
        if (spSide) {
            ind = S.idoc.createElement('div');
            ind.className = 'ecod-b-ind--split';
            if (spSide === 'left') { ind.style.left = '-1.5px'; } else { ind.style.right = '-1.5px'; }
            colEl.appendChild(ind);
            return { kind: 'cell-split', secUid: secEl.getAttribute('data-buid'),
                cellUid: colEl.getAttribute('data-buid'), side: spSide };
        }
        var mods = $$('.ecod-b-mod[data-btype]', colEl).filter(function (el) {
            return drag.src.mode !== 'move' || el.getAttribute('data-buid') !== drag.src.uid;
        });
        var mIdx = mods.length;
        for (var m = 0; m < mods.length; m++) {
            var mr = mods[m].getBoundingClientRect();
            if (fy < mr.top + mr.height / 2) { mIdx = m; break; }
        }
        ind = S.idoc.createElement('div');
        ind.className = 'ecod-b-ind';
        if (mIdx < mods.length) {
            mods[mIdx].parentNode.insertBefore(ind, mods[m]);
        } else {
            colEl.appendChild(ind);
        }
        return { kind: 'cell', secUid: secEl.getAttribute('data-buid'),
            cellUid: colEl.getAttribute('data-buid'), idx: mIdx };
    }
    function performDrop(tg) {
        /* 板块拖动（拖板块标签整块移动）：此分支在早前的 warea 重写中被
           覆盖丢失，导致拖板块静默无效——补回 */
        if (tg.kind === 'section-move') {
            var oldIdx = -1;
            for (var s = 0; s < S.sections.length; s++) {
                if (S.sections[s].uid === drag.src.uid) { oldIdx = s; break; }
            }
            if (oldIdx < 0) { return; }
            var moved = S.sections.splice(oldIdx, 1)[0];
            S.sections.splice(Math.min(tg.idx, S.sections.length), 0, moved);
            S.sel = { kind: 'section', id: moved.uid };
            markDirty();
            renderAll(); scheduleReload();
            toast('板块已移动到第 ' + (Math.min(tg.idx, S.sections.length) + 1) + ' 位');
            return;
        }

        /* v4 区域目标：添加 / 移动 / 删除 */
        if (tg.kind === 'warea' || tg.kind === 'warea-row') {
            if (S.lock) { toast('布局已锁定：只允许修改模块参数，顶栏可解锁'); return; }
            var insertIntoArea = function (areaId, mod, beforeUid, above) {
                var arr = (S.wgt.areas[areaId] = S.wgt.areas[areaId] || []);
                var at = arr.length;
                if (beforeUid) {
                    for (var i = 0; i < arr.length; i++) {
                        if (arr[i].uid === beforeUid && arr[i].on) {
                            at = i + (above ? 0 : 1);
                            if (!above) {
                                for (var j = i + 1; j < arr.length; j++) {
                                    if (arr[j].on) { at = j; break; }
                                }
                            }
                            break;
                        }
                    }
                }
                arr.splice(Math.min(at, arr.length), 0, mod);
            };
            if (drag.src.mode === 'wmove') {
                var wloc = findW(drag.src.uid);
                if (!wloc) { return; }
                if (wloc.area === tg.area && (tg.beforeUid === drag.src.uid || tg.beforeUid == null)) { return; }
                captureUndo('wmove' + Date.now());
                var inst = S.wgt.areas[wloc.area].splice(wloc.index, 1)[0];
                var refUid = tg.beforeUid === drag.src.uid ? null : tg.beforeUid;
                insertIntoArea(tg.area, inst, refUid, tg.above);
                dirtyArea(wloc.area); dirtyArea(tg.area);
                S.sel = { kind: 'wmod', id: inst.uid, area: tg.area };
                markDirty('wgt');
                renderAreasTab(); renderInspector(); applySelection(); scheduleReload();
                toast(wloc.area === tg.area
                    ? '已在「' + wgtAreaLabel(tg.area) + '」内调整顺序'
                    : '已移动到「' + wgtAreaLabel(tg.area) + '」');
                return;
            }
            if (drag.src.mode === 'new') {
                captureUndo('wnew' + Date.now());
                var inst2 = newMod(drag.src.type);
                insertIntoArea(tg.area, inst2, tg.beforeUid, tg.above);
                dirtyArea(tg.area);
                S.sel = { kind: 'wmod', id: inst2.uid, area: tg.area };
                markDirty('wgt');
                renderAreasTab(); renderInspector(); applySelection(); scheduleReload();
                toast('已添加到「' + wgtAreaLabel(tg.area) + '」');
                return;
            }
            return;
        }
        if (tg.kind === 'wdel') {
            if (drag.src.mode !== 'wmove' || S.lock) { return; }
            var dloc = findW(drag.src.uid);
            if (!dloc) { return; }
            captureUndo('wdel2' + Date.now());
            S.wgt.areas[dloc.area].splice(dloc.index, 1);
            dirtyArea(dloc.area);
            if (S.sel && S.sel.id === drag.src.uid) { S.sel = null; }
            markDirty('wgt');
            renderAreasTab(); renderInspector(); applySelection(); scheduleReload();
            toast('已移除模块（⌘/Ctrl+Z 可找回）');
            return;
        }
        /* 行模板：拖入行间隙 / 空文档落成新板块 */
        if (tg.kind === 'rownew' || tg.kind === 'section-new-row') {
            var pre = rowTemplateDef(drag.src.type);
            if (!pre) { return; }
            captureUndo('rownew' + Date.now());
            var rowNew = tplRow(pre);
            if (tg.kind === 'section-new-row') {
                var newSecRow = newSection(null);
                newSecRow.rows = [rowNew];
                S.sections.push(newSecRow);
                S.sel = { kind: 'row', id: rowNew.uid };
            } else {
                var tSecRow = findSec(tg.secUid);
                if (!tSecRow) { return; }
                if ((tSecRow.rows || []).length >= MAX_ROWS) { toast('单板块最多 ' + MAX_ROWS + ' 行'); return; }
                tSecRow.rows = tSecRow.rows || [];
                tSecRow.rows.splice(Math.max(0, Math.min(tg.idx, tSecRow.rows.length)), 0, rowNew);
                S.sel = { kind: 'row', id: rowNew.uid };
            }
            markDirty();
            renderAll();
            scheduleReload();
            toast('已插入行：' + pre.name);
            return;
        }
        /* autorow：就近建一行一列承接（随意拖放兜底） */
        if (tg.kind === 'cell-autorow') {
            var arSec = findSec(tg.secUid);
            if (!arSec) { return; }
            if ((arSec.rows || []).length >= MAX_ROWS) { toast('单板块最多 ' + MAX_ROWS + ' 行'); return; }
            captureUndo('autorow' + Date.now());
            var arMod;
            if (drag.src.mode === 'move') {
                var arLoc = findMod(drag.src.uid);
                if (!arLoc) { return; }
                arMod = arLoc.arr.splice(arLoc.index, 1)[0];
            } else {
                arMod = newMod(drag.src.type);
            }
            var arRow = newRow([arMod]);
            arSec.rows = arSec.rows || [];
            arSec.rows.splice(Math.max(0, Math.min(tg.idx, arSec.rows.length)), 0, arRow);
            S.sel = { kind: 'module', id: arMod.uid };
            markDirty();
            renderAll();
            scheduleReload();
            toast('已在该位置新建一行承接模块（⌘/Ctrl+Z 可撤销）');
            return;
        }
        /* cell-split（§6.2）：目标列分裂出新列，被拖模块进入新列 */
        if (tg.kind === 'cell-split') {
            var spPath = findPath(tg.cellUid);
            if (!spPath || !spPath.cell || !spPath.row) { return; }
            var spCells = spPath.cellsArr || (spPath.row.cells = []);
            var spIdx = spCells.indexOf(spPath.cell);
            if (spCells.length >= MAX_CELLS) { toast('单行最多 ' + MAX_CELLS + ' 列'); return; }
            captureUndo('csplit' + Date.now());
            var spMod;
            if (drag.src.mode === 'move') {
                var spLoc = findMod(drag.src.uid);
                if (!spLoc) { return; }
                spMod = spLoc.arr.splice(spLoc.index, 1)[0];
            } else {
                spMod = newMod(drag.src.type);
            }
            var spDonor = spCells[spIdx];
            var spNew = newCell([spMod]);
            /* 权重对半拆（与 ＋列 同款数学）：fr≥2 对半；fr=1 新列同权重；
               px 列减半（下限 160）拆出 1fr 新列 */
            if (!spDonor.wpx && spDonor.w >= 2) {
                var spHalf = Math.floor(spDonor.w / 2);
                spNew.w = spDonor.w - spHalf;
                spDonor.w = spHalf;
            } else if (!spDonor.wpx) {
                spNew.w = 1;
            } else {
                spDonor.wpx = Math.max(160, Math.floor(spDonor.wpx / 2));
            }
            spCells.splice(tg.side === 'left' ? spIdx : spIdx + 1, 0, spNew);
            S.sel = { kind: 'cell', id: spNew.uid };
            markDirty();
            renderAll();
            scheduleReload();
            toast('已分裂出新列（⌘/Ctrl+Z 可撤销）');
            return;
        }
        captureUndo('drop' + Date.now());
        var mod = null;
        if (drag.src.mode === 'section-move') {
            var oldIdx = -1;
            for (var s = 0; s < S.sections.length; s++) {
                if (S.sections[s].uid === drag.src.uid) { oldIdx = s; break; }
            }
            if (oldIdx < 0) { return; }
            var moved = S.sections.splice(oldIdx, 1)[0];
            S.sections.splice(Math.min(tg.idx, S.sections.length), 0, moved);
            S.sel = { kind: 'section', id: moved.uid };
            markDirty();
            renderAll();
            scheduleReload();
            return;
        }
        if (drag.src.mode === 'new') {
            if (drag.src.type === 'section') {
                var sec = newSection(null);
                S.sections.splice(Math.min(tg.idx, S.sections.length), 0, sec);
                S.sel = { kind: 'section', id: sec.uid };
                markDirty(); renderAll(); scheduleReload();
                return;
            }
            mod = newMod(drag.src.type);
        } else {
            var loc = findMod(drag.src.uid);
            if (!loc) { return; }
            mod = loc.arr.splice(loc.index, 1)[0];
            /* 同列后移：摘除后目标索引左移一格 */
            if (tg.kind === 'cell' && tg.cellUid === loc.cell.uid && tg.idx > loc.index) {
                tg.idx--;
            }
        }
        if (tg.kind === 'section-new-mod') {
            S.sections.push(newSection(mod));
        } else {
            var sec = findSec(tg.secUid);
            if (!sec) { return; }
            var targetCell = (tg.cellUid && findCell(sec, tg.cellUid)) || lastCell(sec);
            var arr = targetCell.mods || (targetCell.mods = []);
            arr.splice(Math.min(tg.idx, arr.length), 0, mod);
        }
        S.sel = { kind: 'module', id: mod.uid };
        if (drag.src.mode === 'move' && ind && ind.parentNode && S.idoc) {
            /* 乐观搬移：指示条位置就是落点，先挪节点再等重载确认 */
            var mvNode = S.idoc.querySelector('[data-buid="' + drag.src.uid + '"]');
            if (mvNode) { ind.parentNode.insertBefore(mvNode, ind); }
        }
        markDirty();
        renderAll();
        scheduleReload();
    }

    /* ==================================================================
     * Library（左栏模块库）
     * ================================================================ */
    var AREA_LABELS = {
        home: '内容模块', aside: '侧栏挂件', post: '文章页模块',
        footer: '页脚模块', header: '头部模块', archive: '列表页模块', topic: '专题模块'
    };
    function buildLib() {
        var groups = {};
        Object.keys(CFG.defs).forEach(function (id) {
            var area = CFG.defs[id].area || 'home';
            (groups[area] = groups[area] || []).push(id);
        });
        var html = '<div class="bld-card bld-card--sec" data-new="section" data-label="板块">'
            + '<span class="bld-ic"></span><div><b>板块<span class="bld-newtag">新</span></b>'
            + '<small>全宽布局容器：宽度 / 间距 / 背景 / 栏结构</small></div></div>';
        html += '<div class="bld-grp">结构（行模板）</div>';
        ROW_PRESETS.forEach(function (p) {
            var bars = p.cells.map(function (c) {
                return '<i style="flex:' + (c.wpx ? 2 : (c.w || 1)) + '"' + (c.wpx ? ' class="px"' : '') + '></i>';
            }).join('');
            html += '<div class="bld-card bld-card--row" data-new="' + PRESET_PREFIX + esc(p.id) + '"'
                + ' data-label="行·' + esc(p.name) + '">'
                + '<span class="bld-ic bld-preset">' + bars + '</span>'
                + '<div><b>' + esc(p.name) + '</b><small>拖到板块内建行，或点击追加</small></div></div>';
        });
        var savedKeys = Object.keys(S.savedRows || {});
        if (savedKeys.length) {
            html += '<div class="bld-grp">我的行模板</div>';
            savedKeys.forEach(function (k) {
                var one = S.savedRows[k] || {};
                var libCells = (one.row && one.row.cells) || [];
                var libBars = libCells.slice(0, 6).map(function (c) {
                    return '<i style="flex:' + (c.wpx ? 2 : (c.w || 1)) + '"' + (c.wpx ? ' class="px"' : '') + '></i>';
                }).join('');
                html += '<div class="bld-card bld-card--row" data-new="' + SROW_PREFIX + esc(k) + '"'
                    + ' data-label="行模板·' + esc(one.name || k) + '">'
                    + '<span class="bld-ic bld-preset">' + (libBars || '<i></i>') + '</span>'
                    + '<div><b>' + esc(one.name || '行模板') + '</b>'
                    + '<small>拖入建行 · <button type="button" class="bld-srowdel" data-srowdel="' + esc(k) + '">删除</button></small></div></div>';
            });
        }
        Object.keys(AREA_LABELS).forEach(function (area) {
            if (!groups[area]) { return; }
            html += '<div class="bld-grp">' + AREA_LABELS[area] + '</div>';
            groups[area].forEach(function (id) {
                var d = CFG.defs[id];
                var icon = CFG.icons[d.icon] ? CFG.icons[d.icon]
                    : (CFG.icons.layers || '');
                html += '<div class="bld-card" data-new="' + esc(id) + '" data-label="' + esc(d.name) + '">'
                    + '<span class="bld-ic">' + icon + '</span>'
                    + '<div><b>' + esc(d.name) + '</b><small>' + esc(d.desc || '') + '</small></div></div>';
            });
        });
        $('[data-lib]').innerHTML = html;
    }

    /* ==================================================================
     * Outline（大纲树）
     * ================================================================ */
    function olTools(kind, id) {
        return '<span class="bld-ol__tls">'
            + '<button type="button" data-olact="eye" data-olkind="' + kind + '" data-olid="' + esc(id) + '" title="显隐">' + ico('eye') + '</button>'
            + '<button type="button" data-olact="up" data-olkind="' + kind + '" data-olid="' + esc(id) + '" title="上移">' + ico('arrow-up') + '</button>'
            + '<button type="button" data-olact="down" data-olkind="' + kind + '" data-olid="' + esc(id) + '" title="下移">' + ico('arrow-down') + '</button>'
            + '<button type="button" data-olact="del" data-olkind="' + kind + '" data-olid="' + esc(id) + '" title="删除">' + ico('trash') + '</button></span>';
    }
    function renderOutline() {
        var el = $('[data-outline]');
        if (!S.sections.length) {
            el.innerHTML = '<div class="bld-tip">空文档。到「模块库」拖入板块或模块开始搭建。</div>';
            return;
        }
        /* 四层树（§6.7）+ 列内嵌套行：行/列的 eye/up/down/del 走 canvasAct 对应操作
           （列的 up/down = 左右移，eye = 断点隐藏）；嵌套层用更深的缩进类 */
        var olLine = function (kind, id, cls, badge, name, note) {
            var on3 = S.sel && S.sel.kind === kind && S.sel.id === id;
            return '<div class="bld-ol ' + cls + (on3 ? ' on' : '') + '" data-olsel="' + kind + ':' + esc(id) + '">'
                + '<span class="bld-ol__bdg">' + esc(badge) + '</span>'
                + '<span class="bld-ol__nm">' + esc(name) + (note ? '<em>' + esc(note) + '</em>' : '') + '</span>'
                + olTools(kind, id) + '</div>';
        };
        var olMods = function (cell, depth) {
            return (cell.mods || []).map(function (m) {
                var on2 = S.sel && S.sel.kind === 'module' && S.sel.id === m.uid;
                return '<div class="bld-ol ' + (depth === 0 ? 'bld-ol--sub' : 'bld-ol--sub2') + (on2 ? ' on' : '')
                    + '" data-olsel="module:' + esc(m.uid) + '">'
                    + '<span class="bld-ol__nm">' + esc(modLabel(m)) + (m.on ? '' : '（停用）') + '</span>'
                    + olTools('module', m.uid) + '</div>';
            }).join('');
        };
        var olRow = function (row, depth) {
            var cells = (row.cells || []).map(function (cell) {
                var cHidden = (cell.ov && cell.ov.md && cell.ov.md.hide) || (cell.ov && cell.ov.sm && cell.ov.sm.hide);
                var note = (cell.rows || []).length
                    ? '嵌套行 ×' + cell.rows.length + (cell.sticky ? ' · 吸顶' : '') + (cHidden ? ' · 断点隐藏' : '')
                    : (cell.mods || []).length + ' 模块' + (cell.sticky ? ' · 吸顶' : '') + (cHidden ? ' · 断点隐藏' : '');
                return olLine('cell', cell.uid, depth === 0 ? 'bld-ol--cell' : 'bld-ol--ncell',
                    cell.wpx ? cell.wpx + 'px' : (cell.w || 1) + 'fr', '列', note)
                    + olMods(cell, depth)
                    + (cell.rows || []).map(function (nr) { return olRow(nr, depth + 1); }).join('');
            }).join('');
            return olLine('row', row.uid, depth === 0 ? 'bld-ol--row' : 'bld-ol--nrow',
                (row.cells || []).length + '列' + (row.gap != null && row.gap !== 24 ? ' · gap ' + row.gap : '')
                    + (row.masonry ? ' · 瀑布流' : ''),
                depth === 0 ? '行' : '嵌套行', row.on ? '' : '（隐藏）') + cells;
        };
        el.innerHTML = S.sections.map(function (sec) {
            var on = S.sel && S.sel.kind === 'section' && S.sel.id === sec.uid;
            var rows = (sec.rows || []).map(function (row) { return olRow(row, 0); }).join('');
            return '<div class="bld-ol' + (on ? ' on' : '') + '" data-olsel="section:' + esc(sec.uid) + '">'
                + '<span>▾</span><span class="bld-ol__nm">' + esc(sec.label || '板块')
                + (sec.on ? '' : '（隐藏）') + '</span>' + olTools('section', sec.uid) + '</div>' + rows;
        }).join('');
    }
    $('[data-olpane]').addEventListener('click', function (e) {
        var act = e.target.closest && e.target.closest('[data-olact]');
        if (act) {
            canvasAct(act.getAttribute('data-olact'),
                act.getAttribute('data-olid'), act.getAttribute('data-olkind'));
            return;
        }
        var sel = e.target.closest && e.target.closest('[data-olsel]');
        if (sel) {
            var parts = sel.getAttribute('data-olsel').split(':');
            S.sel = { kind: parts[0], id: parts[1] };
            applySelection(); renderOutline(); renderInspector();
        }
    });

    /* ==================================================================
     * Inspector（EcodFields 注册表 + 属性面板）
     * ================================================================ */
    var EcodFields = {
        /**
         * @param {object} field {label,type,default,desc,options,min,max,placeholder,fields}
         * @param {*} value
         * @param {function} onChange (value)=>void
         */
        render: function (field, value, onChange) {
            var wrap = document.createElement('div');
            wrap.className = 'bld-frow';
            var type = field.type || 'text';
            var label = document.createElement('span');
            label.className = 'bld-frow__l';
            label.textContent = field.label || '';
            wrap.appendChild(label);

            var ctl;
            if (type === 'switch') {
                ctl = document.createElement('label');
                ctl.className = 'bld-switch';
                var box = document.createElement('input');
                box.type = 'checkbox';
                box.checked = !!value;
                box.addEventListener('change', function () { onChange(box.checked ? 1 : 0); });
                var slider = document.createElement('i');
                ctl.appendChild(box); ctl.appendChild(slider);
            } else if (type === 'select') {
                ctl = document.createElement('select');
                ctl.className = 'bld-select';
                Object.keys(field.options || {}).forEach(function (val) {
                    var opt = document.createElement('option');
                    opt.value = val;
                    opt.textContent = field.options[val];
                    if (String(value) === String(val)) { opt.selected = true; }
                    ctl.appendChild(opt);
                });
                ctl.addEventListener('change', function () { onChange(ctl.value); });
            } else if (type === 'number') {
                ctl = document.createElement('input');
                ctl.type = 'number';
                ctl.className = 'bld-input bld-input--num';
                if (field.min != null) { ctl.min = field.min; }
                if (field.max != null) { ctl.max = field.max; }
                ctl.value = value === '' || value == null ? '' : value;
                ctl.addEventListener('input', function () {
                    var v = ctl.value === '' ? '' : parseInt(ctl.value, 10);
                    if (v !== '' && field.min != null && v < field.min) { v = field.min; }
                    if (v !== '' && field.max != null && v > field.max) { v = field.max; }
                    onChange(v);
                });
            } else if (type === 'textarea') {
                ctl = document.createElement('textarea');
                ctl.className = 'bld-input bld-input--area';
                ctl.rows = 3;
                ctl.value = value == null ? '' : value;
                ctl.addEventListener('input', function () { onChange(ctl.value); });
            } else if (type === 'image') {
                ctl = document.createElement('div');
                ctl.className = 'bld-imgfield';
                var imgInput = document.createElement('input');
                imgInput.type = 'text';
                imgInput.className = 'bld-input';
                imgInput.placeholder = 'https://…';
                imgInput.value = value == null ? '' : value;
                imgInput.addEventListener('input', function () { onChange(imgInput.value); });
                var pick = document.createElement('button');
                pick.type = 'button';
                pick.className = 'bld-mini';
                pick.textContent = '选图';
                pick.addEventListener('click', function () {
                    if (window.EcodMedia && EcodMedia.pick) {
                        EcodMedia.pick({
                            value: imgInput.value,
                            onPick: function (url) {
                                imgInput.value = url;
                                onChange(url);
                            }
                        });
                    } else {
                        toast('媒体库组件未加载');
                    }
                });
                ctl.appendChild(imgInput); ctl.appendChild(pick);
            } else if (type === 'repeater' || type === 'slides') {
                ctl = document.createElement('div');
                ctl.className = 'bld-repeater';
                var fields = type === 'slides'
                    ? [{ key: 'image', label: '图片' }, { key: 'title', label: '标题' },
                       { key: 'desc', label: '描述' }, { key: 'link', label: '链接' }]
                    : (field.fields || []);
                var rows = [];
                try {
                    rows = typeof value === 'string' && value.trim() !== ''
                        ? JSON.parse(value) : (Array.isArray(value) ? value : []);
                } catch (err) { rows = []; }
                if (!Array.isArray(rows)) { rows = []; }

                var sync = function () {
                    var clean = rows.filter(function (r) {
                        return Object.keys(r).some(function (k) { return String(r[k] || '') !== ''; });
                    });
                    onChange(clean.length ? JSON.stringify(clean) : '');
                };
                var renderRows = function () {
                    ctl.innerHTML = '';
                    rows.forEach(function (row, ri) {
                        var line = document.createElement('div');
                        line.className = 'bld-repeater__row';
                        fields.forEach(function (f) {
                            var inp = document.createElement('input');
                            inp.type = 'text';
                            inp.className = 'bld-input';
                            inp.placeholder = f.label || f.key;
                            inp.value = row[f.key] || '';
                            inp.addEventListener('input', function () {
                                row[f.key] = inp.value; sync();
                            });
                            line.appendChild(inp);
                        });
                        var del = document.createElement('button');
                        del.type = 'button';
                        del.className = 'bld-mini';
                        del.textContent = '×';
                        del.addEventListener('click', function () {
                            rows.splice(ri, 1); sync(); renderRows();
                        });
                        line.appendChild(del);
                        ctl.appendChild(line);
                    });
                    var add = document.createElement('button');
                    add.type = 'button';
                    add.className = 'bld-mini bld-mini--block';
                    add.textContent = '＋ 添加一行';
                    add.addEventListener('click', function () {
                        if (rows.length >= (field.max || 20)) { return; }
                        var fresh = {};
                        fields.forEach(function (f) { fresh[f.key] = ''; });
                        rows.push(fresh); sync(); renderRows();
                    });
                    ctl.appendChild(add);
                };
                renderRows();
            } else if (type === 'term') {
                /* 分类单选：服务端注入 cats 表，空值 = 模块声明默认 */
                ctl = document.createElement('select');
                ctl.className = 'bld-select';
                var opts = { '': '（默认 / 不限）' };
                Object.keys(CFG.cats || {}).forEach(function (mid) { opts[mid] = CFG.cats[mid]; });
                Object.keys(opts).forEach(function (val) {
                    var opt = document.createElement('option');
                    opt.value = val;
                    opt.textContent = opts[val];
                    if (String(value || '') === String(val)) { opt.selected = true; }
                    ctl.appendChild(opt);
                });
                ctl.addEventListener('change', function () { onChange(ctl.value); });
            } else if (type === 'terms') {
                /* 分类多选：逗号分隔 mid，与 widget 台清洗口径一致 */
                ctl = document.createElement('div');
                ctl.className = 'bld-checks';
                var picked = String(value || '').split(',').filter(Boolean);
                Object.keys(CFG.cats || {}).forEach(function (mid) {
                    var lab = document.createElement('label');
                    lab.className = 'bld-check';
                    var box = document.createElement('input');
                    box.type = 'checkbox';
                    box.checked = picked.indexOf(String(mid)) > -1;
                    box.addEventListener('change', function () {
                        var set = {};
                        picked.forEach(function (p) { set[p] = 1; });
                        if (box.checked) { set[mid] = 1; } else { delete set[mid]; }
                        picked = Object.keys(set);
                        onChange(picked.join(','));
                    });
                    var txt = document.createElement('span');
                    txt.textContent = CFG.cats[mid];
                    lab.appendChild(box);
                    lab.appendChild(txt);
                    ctl.appendChild(lab);
                });
            } else if (type === 'icon') {
                /* 可视化图标选择：预览 svg + 手填图标名 + 弹层网格（css.gg 管线同源） */
                ctl = document.createElement('div');
                ctl.className = 'bld-iconpick';
                var prev = document.createElement('span');
                prev.className = 'bld-iconpick__prev';
                prev.innerHTML = (CFG.icons && CFG.icons[value]) || '';
                var nameInp = document.createElement('input');
                nameInp.type = 'text';
                nameInp.className = 'bld-input';
                nameInp.placeholder = '图标名，如 zap';
                nameInp.value = value || '';
                nameInp.addEventListener('input', function () {
                    prev.innerHTML = (CFG.icons && CFG.icons[nameInp.value]) || '';
                    onChange(nameInp.value);
                });
                var pickBtn = document.createElement('button');
                pickBtn.type = 'button';
                pickBtn.className = 'bld-mini';
                pickBtn.textContent = '选择';
                pickBtn.addEventListener('click', function () {
                    openIconPicker(function (name) {
                        nameInp.value = name;
                        prev.innerHTML = (CFG.icons && CFG.icons[name]) || '';
                        onChange(name);
                    });
                });
                ctl.appendChild(prev);
                ctl.appendChild(nameInp);
                ctl.appendChild(pickBtn);
            } else {
                ctl = document.createElement('input');
                ctl.type = 'text';
                ctl.className = 'bld-input';
                if (field.placeholder) { ctl.placeholder = field.placeholder; }
                ctl.value = value == null ? '' : value;
                ctl.addEventListener('input', function () { onChange(ctl.value); });
            }
            wrap.appendChild(ctl);
            return wrap;
        }
    };
    window.EcodFields = EcodFields;

    /* 图标选择弹层（懒建单例，数据 = builder.php 注入的 icons svg 表） */
    var iconPickEl = null;
    function openIconPicker(cb) {
        if (!iconPickEl) {
            iconPickEl = document.createElement('div');
            iconPickEl.className = 'bld-iconpick__overlay';
            iconPickEl.hidden = true;
            iconPickEl.innerHTML = '<div class="bld-iconpick__card">'
                + '<div class="bld-iconpick__hd"><input type="search" class="bld-input" placeholder="搜索图标名…">'
                + '<button type="button" class="bld-mini" data-iconclose>关闭</button></div>'
                + '<div class="bld-iconpick__grid"></div></div>';
            document.body.appendChild(iconPickEl);
            iconPickEl.addEventListener('click', function (e) {
                if (e.target === iconPickEl
                    || (e.target.closest && e.target.closest('[data-iconclose]'))) {
                    iconPickEl.hidden = true;
                    return;
                }
                var b = e.target.closest && e.target.closest('[data-icon]');
                if (b) {
                    iconPickEl.hidden = true;
                    if (typeof iconPickEl._cb === 'function') {
                        iconPickEl._cb(b.getAttribute('data-icon'));
                    }
                }
            });
            var search = iconPickEl.querySelector('input');
            search.addEventListener('input', function () {
                var q = search.value.trim().toLowerCase();
                $$('[data-icon]', iconPickEl).forEach(function (b) {
                    b.style.display = !q || b.getAttribute('data-icon').indexOf(q) > -1 ? '' : 'none';
                });
            });
        }
        var grid = $('.bld-iconpick__grid', iconPickEl);
        grid.innerHTML = Object.keys(CFG.icons || {}).map(function (name) {
            return '<button type="button" class="bld-iconpick__it" data-icon="' + esc(name)
                + '" title="' + esc(name) + '">' + CFG.icons[name] + '</button>';
        }).join('');
        iconPickEl._cb = cb;
        iconPickEl.hidden = false;
    }

    function seg2(field, value, vals, onChange) {
        var box = document.createElement('span');
        box.className = 'bld-seg2';
        vals.forEach(function (pair) {
            var b = document.createElement('button');
            b.type = 'button';
            b.textContent = pair[1];
            if (String(value) === String(pair[0])) { b.classList.add('on'); }
            b.addEventListener('click', function () { onChange(pair[0]); });
            box.appendChild(b);
        });
        return box;
    }
    function frow(labelText, ctl) {
        var row = document.createElement('div');
        row.className = 'bld-frow';
        var l = document.createElement('span');
        l.className = 'bld-frow__l';
        l.textContent = labelText;
        row.appendChild(l);
        row.appendChild(ctl);
        return row;
    }
    function fgrp(text) {
        var g = document.createElement('div');
        g.className = 'bld-fgrp';
        g.textContent = text;
        return g;
    }
    function tip(text) {
        var t = document.createElement('div');
        t.className = 'bld-tip';
        t.textContent = text;
        return t;
    }

    function touchModule(mid, fn) {
        var loc = findMod(mid);
        if (!loc) { return; }
        if (captureUndo(loc.sec.uid + mid + Date.now())) { /* 新快照 */ }
        fn(loc.arr, loc.index);
        markDirty();
        renderOutline();
        scheduleReload();
    }

    function renderInspector() {
        var el = $('[data-inspector]');
        el.innerHTML = '';
        if (!S.sel) {
            var meta = S.docs[S.docKey] || {};
            el.appendChild(fgrp('当前文档'));
            var info = document.createElement('div');
            info.className = 'bld-tip';
            info.innerHTML = '文档：<b>' + esc(meta.label || S.docKey) + '</b>（' + esc(meta.badge || '') + '）<br>'
                + '回退链：单页/单分类覆盖 → 页面类型默认 → 旧版区域。<br><br>'
                + '快捷键：Esc 取消选中 · Del 删除 · ⌘/Ctrl+D 复制 · ⌘/Ctrl+Z 撤销 · ⌘/Ctrl+S 保存';
            el.appendChild(info);
            return;
        }

        if (S.sel.kind === 'wmod') {
            var wloc = findW(S.sel.id);
            if (!wloc) { S.sel = null; return renderInspector(); }
            var wm = S.wgt.areas[wloc.area][wloc.index];
            var wdef = CFG.defs[wm.type] || { params: {}, name: wm.type };
            var whead = document.createElement('div');
            whead.className = 'bld-ihead';
            whead.innerHTML = '<span class="bld-ic">' + (CFG.icons[wdef.icon] || '') + '</span>'
                + '<b>' + esc(wdef.name || wm.type) + '</b>'
                + '<span class="bld-typetag">' + esc(wgtAreaLabel(wloc.area)) + '</span>';
            el.appendChild(whead);
            var wbody = document.createElement('div');
            wbody.className = 'bld-iscroll';
            wbody.appendChild(fgrp('内容参数'));
            var wkeys = Object.keys(wdef.params || {});
            if (!wkeys.length) { wbody.appendChild(tip('该模块没有可配参数。')); }
            wkeys.forEach(function (k) {
                var wfield = wdef.params[k];
                wbody.appendChild(EcodFields.render(wfield, wm.params[k], function (v) {
                    touchW(wm.uid, function (arr, i) { arr[i].params[k] = v; });
                }));
            });
            wbody.appendChild(fgrp('管理'));
            wbody.appendChild(frow('启用', (function () {
                var sw = document.createElement('label');
                sw.className = 'bld-switch';
                var box = document.createElement('input');
                box.type = 'checkbox';
                box.checked = !!wm.on;
                box.addEventListener('change', function () {
                    if (S.lock) { toast('布局已锁定'); box.checked = !box.checked; return; }
                    touchW(wm.uid, function (arr, i) { arr[i].on = box.checked ? 1 : 0; });
                });
                var slider = document.createElement('i');
                sw.appendChild(box); sw.appendChild(slider);
                return sw;
            })()));
            wbody.appendChild(frow('移动到', (function () {
                var mv = document.createElement('select');
                mv.className = 'bld-select';
                Object.keys(S.wgt.areaMeta).forEach(function (aid) {
                    var opt = document.createElement('option');
                    opt.value = aid;
                    opt.textContent = wgtAreaLabel(aid);
                    if (aid === wloc.area) { opt.selected = true; }
                    mv.appendChild(opt);
                });
                mv.addEventListener('change', function () {
                    var to = mv.value;
                    mv.value = wloc.area;
                    if (!to || to === wloc.area) { return; }
                    if (S.lock) { toast('布局已锁定'); return; }
                    moveW(wm.uid, to);
                });
                return mv;
            })()));
            var wdel = document.createElement('button');
            wdel.type = 'button';
            wdel.className = 'bld-mini';
            wdel.textContent = '移除该模块';
            wdel.addEventListener('click', function () {
                if (S.lock) { toast('布局已锁定'); return; }
                wgtAct('del', wm.uid);
            });
            wbody.appendChild(frow('删除', wdel));
            el.appendChild(wbody);
            return;
        }
        if (S.sel.kind === 'module') {
            var loc = findMod(S.sel.id);
            if (!loc) { S.sel = null; return renderInspector(); }
            var m = loc.arr[loc.index];
            var def = CFG.defs[m.type] || { params: {}, name: m.type };

            var head = document.createElement('div');
            head.className = 'bld-ihead';
            head.innerHTML = '<span class="bld-ic">' + (CFG.icons[def.icon] || '') + '</span>'
                + '<b>' + esc(def.name || m.type) + '</b><span class="bld-typetag">模块</span>';
            var batchBtn = document.createElement('button');
            batchBtn.type = 'button';
            batchBtn.className = 'bld-mini';
            batchBtn.textContent = '批量';
            batchBtn.title = '把当前参数套用到本文档的所有同类模块';
            batchBtn.addEventListener('click', function () {
                var n = 0;
                S.sections.forEach(function (sec) {
                    secMods(sec).forEach(function (m2) {
                        if (m2.type === m.type && m2.uid !== m.uid) {
                            m2.params = JSON.parse(JSON.stringify(m.params));
                            m2.on = m.on;
                            n++;
                        }
                    });
                });
                if (!n) { toast('本文档没有其他同类模块'); return; }
                captureUndo('batch' + m.uid + Date.now());
                markDirty();
                renderAll();
                scheduleReload();
                toast('已把参数套用到另外 ' + n + ' 个同类模块');
            });
            var xf = document.createElement('select');
            xf.className = 'bld-select bld-select--xf';
            xf.title = '转换为其他模块类型，同名参数将保留';
            xf.innerHTML = '<option value="">转换为…</option>' + Object.keys(CFG.defs).map(function (id) {
                return '<option value="' + esc(id) + '">' + esc(CFG.defs[id].name) + '</option>';
            }).join('');
            xf.addEventListener('change', function () {
                var to = xf.value;
                xf.value = '';
                if (!to || to === m.type) { return; }
                transformModule('module', m.uid, to);
            });
            head.appendChild(batchBtn);
            head.appendChild(xf);
            el.appendChild(head);

            var tabs = document.createElement('div');
            tabs.className = 'bld-itabs';
            [['content', '内容'], ['style', '样式']].forEach(function (pair) {
                var b = document.createElement('button');
                b.type = 'button';
                b.textContent = pair[1];
                b.setAttribute('aria-pressed', S.itab === pair[0] ? 'true' : 'false');
                b.addEventListener('click', function () { S.itab = pair[0]; renderInspector(); });
                tabs.appendChild(b);
            });
            el.appendChild(tabs);

            var body = document.createElement('div');
            body.className = 'bld-iscroll';
            if (S.itab === 'content') {
                body.appendChild(fgrp('内容参数'));
                var keys = Object.keys(def.params || {});
                if (!keys.length) {
                    body.appendChild(tip('该模块没有可配参数。'));
                }
                keys.forEach(function (k) {
                    var field = def.params[k];
                    body.appendChild(EcodFields.render(field, m.params[k], function (v) {
                        touchModule(m.uid, function (arr, i) { arr[i].params[k] = v; });
                    }));
                });
            } else {
                body.appendChild(fgrp('断点显示'));
                ['md', 'sm'].forEach(function (dev) {
                    var devName = dev === 'md' ? '平板隐藏' : '手机隐藏';
                    var cur = !!(m.hide && m.hide[dev]);
                    body.appendChild(frow(devName, (function (devKey) {
                        var sw = document.createElement('label');
                        sw.className = 'bld-switch';
                        var box = document.createElement('input');
                        box.type = 'checkbox';
                        box.checked = cur;
                        box.addEventListener('change', function () {
                            touchModule(m.uid, function (arr, i) {
                                arr[i].hide = arr[i].hide || {};
                                arr[i].hide[devKey] = box.checked ? 1 : 0;
                            });
                        });
                        var slider = document.createElement('i');
                        sw.appendChild(box); sw.appendChild(slider);
                        return sw;
                    })(dev)));
                });
                body.appendChild(frow('模块停用', (function () {
                    var sw = document.createElement('label');
                    sw.className = 'bld-switch';
                    var box = document.createElement('input');
                    box.type = 'checkbox';
                    box.checked = !m.on;
                    box.addEventListener('change', function () {
                        touchModule(m.uid, function (arr, i) { arr[i].on = box.checked ? 0 : 1; });
                    });
                    var slider = document.createElement('i');
                    sw.appendChild(box); sw.appendChild(slider);
                    return sw;
                })()));
                body.appendChild(tip('切换顶栏设备按钮可预览断点效果；断点在 900px / 680px。'));
                /* 移动到（§6.5）：拖拽跨列/跨板块的键盘可达等价路径 */
                body.appendChild(fgrp('移动到'));
                var mvSel = document.createElement('select');
                mvSel.className = 'bld-select';
                var mvHtml = '<option value="">选择目标列（落到末尾）…</option>';
                S.sections.forEach(function (sec2, si2) {
                    mvHtml += '<optgroup label="' + esc((sec2.label || '板块') + ' · ' + (si2 + 1)) + '">';
                    (sec2.rows || []).forEach(function (row2, ri2) {
                        (row2.cells || []).forEach(function (cell2, ci2) {
                            if ((cell2.rows || []).length) { return; }
                            mvHtml += '<option value="' + esc(cell2.uid) + '">行' + (ri2 + 1) + ' 列' + (ci2 + 1)
                                + '（' + (cell2.mods || []).length + ' 模块）</option>';
                        });
                    });
                    mvHtml += '</optgroup>';
                });
                mvSel.innerHTML = mvHtml;
                mvSel.addEventListener('change', function () {
                    var to = mvSel.value;
                    mvSel.value = '';
                    if (to) { moveModuleTo(m.uid, to); }
                });
                body.appendChild(mvSel);
            }
            el.appendChild(body);
            return;
        }

        /* ---- 行 / 列（docs/builder-v6-design.md §6.5）----
           之前没有分支：选中行/列后 renderInspector 走 findSec 落空，
           S.sel 被静默清空、选中态随之丢失。 */
        if (S.sel.kind === 'row' || S.sel.kind === 'cell') {
            var rcPath = findPath(S.sel.id);
            var rcNode = S.sel.kind === 'row' ? rcPath && rcPath.row : rcPath && rcPath.cell;
            if (!rcNode) { S.sel = null; return renderInspector(); }
            var rcDev = S.device === 'desktop' ? null : S.device;
            var rcOv = function (obj, dev) {
                obj.ov = obj.ov || {};
                obj.ov[dev] = obj.ov[dev] || {};
                return obj.ov[dev];
            };
            var touchRC = function (fn) {
                captureUndo(S.sel.kind + S.sel.id + Date.now());
                fn();
                markDirty();
                renderOutline();
                scheduleReload();
            };
            var ovMetaBar = function (obj, onClear) {
                var count = obj.ov && obj.ov[rcDev] ? Object.keys(obj.ov[rcDev]).length : 0;
                var bar = document.createElement('div');
                bar.className = 'bld-ovbar';
                bar.innerHTML = '📱 ' + DEVNAME[S.device] + '：已覆盖 ' + count + ' 项';
                var clr = document.createElement('button');
                clr.type = 'button';
                clr.textContent = '清除';
                clr.addEventListener('click', function () {
                    touchRC(function () { if (obj.ov) { delete obj.ov[rcDev]; } });
                    renderInspector();
                });
                bar.appendChild(clr);
                return bar;
            };
            var actBtn = function (label, act, targetKind, targetId) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'bld-mini';
                b.textContent = label;
                b.addEventListener('click', function () { canvasAct(act, targetId, targetKind); });
                return b;
            };

            if (S.sel.kind === 'row') {
                var rRow = rcPath.row;
                var rHead = document.createElement('div');
                rHead.className = 'bld-ihead';
                rHead.innerHTML = '<span class="bld-ic"></span><b>行</b>'
                    + '<span class="bld-typetag">' + (rRow.cells || []).length + ' 列</span>';
                el.appendChild(rHead);
                var rBody = document.createElement('div');
                rBody.className = 'bld-iscroll';
                if (S.lock) {
                    rBody.appendChild(tip('布局已锁定：行结构只读，模块参数仍可修改；顶栏可解锁。'));
                    el.appendChild(rBody);
                    return;
                }
                rBody.appendChild(fgrp('列结构'));
                (rRow.cells || []).forEach(function (c, ci) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'bld-mini bld-cellpick';
                    btn.textContent = '列 ' + (ci + 1) + ' · ' + (c.wpx ? c.wpx + 'px' : (c.w || 1) + 'fr')
                        + ' · ' + (c.mods || []).length + ' 模块'
                        + ((c.ov && c.ov.md && c.ov.md.hide) || (c.ov && c.ov.sm && c.ov.sm.hide) ? ' · 断点隐藏' : '');
                    btn.addEventListener('click', function () {
                        S.sel = { kind: 'cell', id: c.uid };
                        applySelection(); renderOutline(); renderInspector();
                    });
                    rBody.appendChild(btn);
                });
                rBody.appendChild(frow('加列', actBtn('＋列（最后一列对半拆）', 'addcell', 'row', rRow.uid)));

                rBody.appendChild(fgrp('间距与对齐'));
                var gapIn = document.createElement('input');
                gapIn.type = 'number';
                gapIn.className = 'bld-input bld-input--num';
                gapIn.min = 0; gapIn.max = 96;
                gapIn.value = (rcDev && rRow.ov && rRow.ov[rcDev] && rRow.ov[rcDev].gap != null)
                    ? rRow.ov[rcDev].gap : rRow.gap;
                gapIn.addEventListener('input', function () {
                    var v = Math.min(96, Math.max(0, parseInt(gapIn.value, 10) || 0));
                    touchRC(function () {
                        if (rcDev) { rcOv(rRow, rcDev).gap = v; } else { rRow.gap = v; }
                    });
                });
                rBody.appendChild(frow('列间距' + (rcDev ? '（' + DEVNAME[S.device] + '）' : ''), gapIn));
                rBody.appendChild(frow('垂直对齐', seg2('valign', rRow.valign || 'start',
                    [['start', '顶部'], ['center', '居中'], ['stretch', '等高']],
                    function (v) { touchRC(function () { rRow.valign = v; }); })));
                rBody.appendChild(frow('布局流', seg2('flow', rRow.masonry ? 'masonry' : 'grid',
                    [['grid', '网格'], ['masonry', '瀑布流（实验）']],
                    function (v) { touchRC(function () { rRow.masonry = v === 'masonry' ? 1 : 0; }); })));
                if (rRow.masonry) {
                    rBody.appendChild(tip('瀑布流按列纵向填充（CSS columns 实验）：阅读顺序为列优先；'
                        + '平板折 2 列 / 手机 1 列，可在断点覆盖里用「保持并排」锁定列数。列宽手柄在瀑布流行不可用。'));
                }

                rBody.appendChild(fgrp('断点覆盖'));
                if (rcDev) {
                    var keepRow = rcOv(rRow, rcDev);
                    rBody.appendChild(frow('保持并排（不折叠）', (function () {
                        var sw = document.createElement('label');
                        sw.className = 'bld-switch';
                        var box = document.createElement('input');
                        box.type = 'checkbox';
                        box.checked = !!keepRow.keep;
                        box.addEventListener('change', function () {
                            touchRC(function () { rcOv(rRow, rcDev).keep = box.checked ? 1 : 0; });
                        });
                        var slider = document.createElement('i');
                        sw.appendChild(box); sw.appendChild(slider);
                        return sw;
                    })()));
                    var rHide = document.createElement('label');
                    rHide.className = 'bld-switch';
                    var rHideBox = document.createElement('input');
                    rHideBox.type = 'checkbox';
                    rHideBox.checked = !!keepRow.hide;
                    rHideBox.addEventListener('change', function () {
                        touchRC(function () { rcOv(rRow, rcDev).hide = rHideBox.checked ? 1 : 0; });
                    });
                    var rHideSlider = document.createElement('i');
                    rHide.appendChild(rHideBox); rHide.appendChild(rHideSlider);
                    rBody.appendChild(frow('隐藏整行', rHide));
                    rBody.appendChild(ovMetaBar(rRow));
                    rBody.appendChild(tip('keep=该断点保持并排（自己确认挤压效果）；hide=整行隐藏；间距留空沿用基础值。'));
                } else {
                    rBody.appendChild(tip('切换顶栏设备段（平板/手机）编辑断点覆盖：保持并排、间距覆盖、整行隐藏。'));
                }

                rBody.appendChild(fgrp('操作'));
                rBody.appendChild(frow('列宽', (function () {
                    var eq = document.createElement('button');
                    eq.type = 'button';
                    eq.className = 'bld-mini';
                    eq.textContent = '均分（全部转 1fr，双击列宽手柄同款）';
                    eq.addEventListener('click', function () { equalizeRow(rRow.uid); });
                    return eq;
                })()));
                rBody.appendChild(frow('插行',
                    actBtn('上方插行', 'insertAbove', 'row', rRow.uid)));
                rBody.appendChild(frow('',
                    actBtn('下方插行', 'insertBelow', 'row', rRow.uid)));
                rBody.appendChild(frow('其他',
                    actBtn('复制行', 'dup', 'row', rRow.uid)));
                rBody.appendChild(frow('',
                    actBtn('删除行', 'del', 'row', rRow.uid)));
                rBody.appendChild(fgrp('存为行模板（P2 可复用行）'));
                var rowTplName = document.createElement('input');
                rowTplName.type = 'text';
                rowTplName.className = 'bld-input';
                rowTplName.placeholder = '模板名称（留空自动命名，同名覆盖）';
                rBody.appendChild(frow('名称', rowTplName));
                rBody.appendChild(frow('', (function () {
                    var st = document.createElement('button');
                    st.type = 'button';
                    st.className = 'bld-mini';
                    st.textContent = '保存到「我的行模板」';
                    st.addEventListener('click', function () { saveRowTemplate(rRow.uid, rowTplName.value); });
                    return st;
                })()));
                el.appendChild(rBody);
                return;
            }

            /* ---- 列 ---- */
            var cCell = rcPath.cell;
            var cRow = rcPath.row;
            var cHead = document.createElement('div');
            cHead.className = 'bld-ihead';
            cHead.innerHTML = '<span class="bld-ic"></span><b>列</b>'
                + '<span class="bld-typetag">' + (cCell.wpx ? cCell.wpx + 'px' : (cCell.w || 1) + 'fr') + '</span>';
            el.appendChild(cHead);
            var cBody = document.createElement('div');
            cBody.className = 'bld-iscroll';
            if (S.lock) {
                cBody.appendChild(tip('布局已锁定：列结构只读，模块参数仍可修改；顶栏可解锁。'));
                el.appendChild(cBody);
                return;
            }
            cBody.appendChild(fgrp('宽度与吸附'));
            var cMode = cCell.wpx ? 'px' : 'fr';
            cBody.appendChild(frow('宽度模式', seg2('wmode', cMode,
                [['fr', '比例权重'], ['px', '固定像素']],
                function (v) {
                    if (v === cMode) { return; }
                    touchRC(function () {
                        if (v === 'px') { delete cCell.w; cCell.wpx = 320; }
                        else { delete cCell.wpx; cCell.w = 1; }
                    });
                    renderInspector();
                })));
            var wIn = document.createElement('input');
            wIn.type = 'number';
            wIn.className = 'bld-input bld-input--num';
            wIn.min = cMode === 'px' ? 160 : 1;
            wIn.max = cMode === 'px' ? 640 : 12;
            if (rcDev && !cCell.wpx) {
                var ovW = cCell.ov && cCell.ov[rcDev] ? cCell.ov[rcDev].w : null;
                wIn.value = ovW != null ? ovW : cCell.w;
            } else {
                wIn.value = cCell.wpx || cCell.w || 1;
            }
            wIn.addEventListener('input', function () {
                var v = parseInt(wIn.value, 10) || 0;
                touchRC(function () {
                    if (rcDev && !cCell.wpx) {
                        if (v >= 1 && v <= 12) { rcOv(cCell, rcDev).w = v; }
                    } else if (cCell.wpx) {
                        cCell.wpx = Math.min(640, Math.max(160, v));
                    } else {
                        cCell.w = Math.min(12, Math.max(1, v));
                    }
                });
            });
            cBody.appendChild(frow(cMode === 'px' ? '像素宽' : (rcDev ? '权重（平板并排覆盖）' : '权重 1–12'), wIn));
            cBody.appendChild(frow('吸顶', (function () {
                var sw = document.createElement('label');
                sw.className = 'bld-switch';
                var box = document.createElement('input');
                box.type = 'checkbox';
                box.checked = !!cCell.sticky;
                box.addEventListener('change', function () {
                    touchRC(function () { cCell.sticky = box.checked ? 1 : 0; });
                });
                var slider = document.createElement('i');
                sw.appendChild(box); sw.appendChild(slider);
                return sw;
            })()));

            cBody.appendChild(fgrp('卡面（背景 / 圆角 / 内边距）'));
            ['bg', 'bgDark'].forEach(function (ck) {
                var crow = document.createElement('div');
                crow.className = 'bld-frow';
                var cl = document.createElement('span');
                cl.className = 'bld-frow__l';
                cl.textContent = ck === 'bg' ? '浅色背景' : '深色背景';
                crow.appendChild(cl);
                var cbox = document.createElement('div');
                cbox.className = 'bld-imgfield';
                var cinp = document.createElement('input');
                cinp.type = 'text';
                cinp.className = 'bld-input';
                cinp.placeholder = 'https://…（留空不设置）';
                cinp.value = cCell[ck] || '';
                cinp.addEventListener('input', function () {
                    cCell[ck] = cinp.value; markDirty(); scheduleReload();
                });
                var cbtn = document.createElement('button');
                cbtn.type = 'button';
                cbtn.className = 'bld-mini';
                cbtn.textContent = '选图';
                cbtn.addEventListener('click', function () {
                    if (window.EcodMedia && EcodMedia.pick) {
                        EcodMedia.pick({ value: cCell[ck], onPick: function (url) {
                            cCell[ck] = url; cinp.value = url; markDirty(); scheduleReload();
                        } });
                    } else { toast('媒体库组件未加载'); }
                });
                cbox.appendChild(cinp); cbox.appendChild(cbtn);
                crow.appendChild(cbox);
                cBody.appendChild(crow);
            });
            var bgcIn = document.createElement('input');
            bgcIn.type = 'text';
            bgcIn.className = 'bld-input bld-input--num';
            bgcIn.placeholder = '#4f6ef2';
            bgcIn.value = cCell.bgc || '';
            bgcIn.addEventListener('input', function () {
                var v = bgcIn.value.trim();
                cCell.bgc = /^#[0-9a-fA-F]{6}$/.test(v) ? v : '';
                markDirty(); scheduleReload();
            });
            cBody.appendChild(frow('背景色', bgcIn));
            var crIn = document.createElement('input');
            crIn.type = 'number';
            crIn.className = 'bld-input bld-input--num';
            crIn.min = 0; crIn.max = 40;
            crIn.value = cCell.radius || 0;
            crIn.addEventListener('input', function () {
                cCell.radius = Math.min(40, Math.max(0, parseInt(crIn.value, 10) || 0));
                markDirty(); scheduleReload();
            });
            cBody.appendChild(frow('圆角 px', crIn));
            var cpIn = document.createElement('input');
            cpIn.type = 'number';
            cpIn.className = 'bld-input bld-input--num';
            cpIn.min = 0; cpIn.max = 120;
            cpIn.value = cCell.pad || 0;
            cpIn.addEventListener('input', function () {
                cCell.pad = Math.min(120, Math.max(0, parseInt(cpIn.value, 10) || 0));
                markDirty(); scheduleReload();
            });
            cBody.appendChild(frow('内边距 px', cpIn));

            cBody.appendChild(fgrp('断点覆盖'));
            if (rcDev) {
                var cOvRef = rcOv(cCell, rcDev);
                var cHideSw = document.createElement('label');
                cHideSw.className = 'bld-switch';
                var cHideBox = document.createElement('input');
                cHideBox.type = 'checkbox';
                cHideBox.checked = !!cOvRef.hide;
                cHideBox.addEventListener('change', function () {
                    touchRC(function () { rcOv(cCell, rcDev).hide = cHideBox.checked ? 1 : 0; });
                });
                var cHideSlider = document.createElement('i');
                cHideSw.appendChild(cHideBox); cHideSw.appendChild(cHideSlider);
                cBody.appendChild(frow('隐藏该列', cHideSw));
                if (!cCell.wpx) {
                    var ordIn = document.createElement('input');
                    ordIn.type = 'number';
                    ordIn.className = 'bld-input bld-input--num';
                    ordIn.min = -6; ordIn.max = 6;
                    ordIn.value = cOvRef.order != null ? cOvRef.order : '';
                    ordIn.placeholder = '默认';
                    ordIn.addEventListener('input', function () {
                        var v = parseInt(ordIn.value, 10);
                        touchRC(function () {
                            var devOv = rcOv(cCell, rcDev);
                            if (v >= -6 && v <= 6) { devOv.order = v; }
                            else { delete devOv.order; }
                        });
                    });
                    cBody.appendChild(frow('折叠后顺序（-6–6）', ordIn));
                }
                cBody.appendChild(ovMetaBar(cCell));
                cBody.appendChild(tip('隐藏/换序只影响 ' + DEVNAME[S.device] + ' 折叠态；权重覆盖仅在「保持并排」的行里生效。'));
            } else {
                cBody.appendChild(tip('切换顶栏设备段（平板/手机）编辑断点覆盖：隐藏、换序、权重覆盖。'));
            }

            cBody.appendChild(fgrp('操作'));
            var cHasRows = (cCell.rows || []).length > 0;
            if (cHasRows) {
                cBody.appendChild(tip('该列是嵌套行结构（深度 2）：宽度/卡面作用于整列，'
                    + '内部行/列在画布与大纲中直接编辑；上层行不能再用预设拖放重建。'));
            }
            cBody.appendChild(frow('分栏', (function () {
                var conv = document.createElement('button');
                conv.type = 'button';
                conv.className = 'bld-mini';
                conv.textContent = cHasRows ? '拆掉嵌套行（模块平铺回列）' : '转为嵌套行（列内分栏）';
                conv.addEventListener('click', function () {
                    canvasAct(cHasRows ? 'untorows' : 'torows', cCell.uid, 'cell');
                });
                return conv;
            })()));
            cBody.appendChild(frow('插列',
                actBtn('左侧插列', 'insertL', 'cell', cCell.uid)));
            cBody.appendChild(frow('',
                actBtn('右侧插列', 'insertR', 'cell', cCell.uid)));
            cBody.appendChild(frow('移动',
                actBtn('左移', 'up', 'cell', cCell.uid)));
            cBody.appendChild(frow('',
                actBtn('右移', 'down', 'cell', cCell.uid)));
            cBody.appendChild(frow('其他',
                actBtn('复制列', 'dup', 'cell', cCell.uid)));
            cBody.appendChild(frow('',
                actBtn('删除列（模块并入左列）', 'del', 'cell', cCell.uid)));
            var cSelRow = document.createElement('button');
            cSelRow.type = 'button';
            cSelRow.className = 'bld-mini';
            cSelRow.textContent = '选整行';
            cSelRow.addEventListener('click', function () {
                S.sel = { kind: 'row', id: cRow.uid };
                applySelection(); renderOutline(); renderInspector();
            });
            cBody.appendChild(frow('所属行', cSelRow));
            el.appendChild(cBody);
            return;
        }

        /* ---- 板块 ---- */
        var sec = findSec(S.sel.id);
        if (!sec) { S.sel = null; return renderInspector(); }
        var shead = document.createElement('div');
        shead.className = 'bld-ihead';
        shead.innerHTML = '<span class="bld-ic"></span>'
            + '<b>' + esc(sec.label || '板块') + '</b><span class="bld-typetag">板块</span>';
        el.appendChild(shead);
        if (S.lock) {
            el.appendChild(tip('布局已锁定：板块结构参数（栏式 / 宽度 / 间距 / 背景）只读，'
                + '模块参数仍可在画布中选中模块修改；需要调整结构请点顶栏「已锁定」解锁。'));
            return;
        }

        var stabs = document.createElement('div');
        stabs.className = 'bld-itabs';
        [['content', '板块设置'], ['style', '背景']].forEach(function (pair) {
            var b = document.createElement('button');
            b.type = 'button';
            b.textContent = pair[1];
            b.setAttribute('aria-pressed', S.itab === pair[0] ? 'true' : 'false');
            b.addEventListener('click', function () { S.itab = pair[0]; renderInspector(); });
            stabs.appendChild(b);
        });
        el.appendChild(stabs);

        var sbody = document.createElement('div');
        sbody.className = 'bld-iscroll';
        var touchSec = function (fn) {
            if (captureUndo(sec.uid + 'sec' + Date.now())) { /* 新快照 */ }
            fn(sec);
            markDirty();
            renderOutline();
            renderCanvasMeta();
            scheduleReload();
        };
        if (S.itab === 'content') {
            sbody.appendChild(fgrp('结构'));
            var labelInput = document.createElement('input');
            labelInput.type = 'text';
            labelInput.className = 'bld-input';
            labelInput.value = sec.label || '';
            labelInput.addEventListener('input', function () {
                sec.label = labelInput.value;
                markDirty(); renderOutline(); renderCanvasMeta();
            });
            sbody.appendChild(frow('名称', labelInput));
            var anchorInput = document.createElement('input');
            anchorInput.type = 'text';
            anchorInput.className = 'bld-input';
            anchorInput.placeholder = '如 hero（字母开头）';
            anchorInput.value = sec.anchor || '';
            anchorInput.addEventListener('input', function () {
                sec.anchor = anchorInput.value;
                markDirty();
            });
            sbody.appendChild(frow('锚点 ID', anchorInput));
            /* 行列结构（v6）：P0 用三个预设表达，均作用于第一行；
               'custom' = 服务端模板/导入产生的多行或多固定列，面板不提供重建入口
               （整行重建会丢用户已摆好的结构，P1 提供行级工具条后再放开） */
            var row0 = (sec.rows || [])[0] || null;
            var cells0 = row0 ? (row0.cells || []) : [];
            var layView = 'custom';
            if (!sec.rows || sec.rows.length <= 1) {
                if (cells0.length <= 1) { layView = 'stack'; }
                else if (cells0.length === 2 && cells0[0].wpx && !cells0[1].wpx) { layView = 'left'; }
                else if (cells0.length === 2 && cells0[1].wpx && !cells0[0].wpx) { layView = 'right'; }
            }
            var layBox = document.createElement('span');
            [['stack', '堆叠'], ['right', '主体+右栏'], ['left', '左栏+主体']].forEach(function (pair) {
                var b = document.createElement('button');
                b.type = 'button';
                b.textContent = pair[1];
                if (layView === pair[0]) { b.classList.add('on'); }
                b.addEventListener('click', function () {
                    if (layView === pair[0] || layView === 'custom') { return; }
                    touchSec(function (s) {
                        if (!Array.isArray(s.rows) || !s.rows.length) { s.rows = [newRow([])]; }
                        var r = s.rows[0];
                        var mods = [];
                        (r.cells || []).forEach(function (c) { mods = mods.concat(c.mods || []); });
                        if (pair[0] === 'stack') {
                            r.cells = [newCell(mods)];
                        } else {
                            var mainMods = [];
                            var asideMods = [];
                            (r.cells || []).forEach(function (c) {
                                if (c.wpx) { asideMods = asideMods.concat(c.mods || []); }
                                else { mainMods = mainMods.concat(c.mods || []); }
                            });
                            var w = 320;
                            (r.cells || []).forEach(function (c) { if (c.wpx) { w = c.wpx; } });
                            var asideCell = newCell(asideMods);
                            asideCell.wpx = w;
                            r.cells = pair[0] === 'left' ? [asideCell, newCell(mainMods)]
                                : [newCell(mainMods), asideCell];
                        }
                    });
                    renderInspector();
                });
                layBox.appendChild(b);
            });
            if (layView === 'custom') {
                var layTip = document.createElement('small');
                layTip.className = 'bld-tip';
                layTip.style.padding = '0 2px';
                layTip.textContent = '多行/自定义结构：在画布与大纲中直接编辑';
                sbody.appendChild(frow('行列结构', layTip));
            } else {
                sbody.appendChild(frow('行列结构', layBox));
                if (layView !== 'stack') {
                    var pxCell = cells0[layView === 'left' ? 0 : 1];
                    var wInput = document.createElement('input');
                    wInput.type = 'number';
                    wInput.className = 'bld-input bld-input--num';
                    wInput.min = 160; wInput.max = 640;
                    wInput.value = pxCell.wpx || 320;
                    wInput.addEventListener('input', function () {
                        pxCell.wpx = Math.min(640, Math.max(160, parseInt(wInput.value, 10) || 320));
                        markDirty(); scheduleReload();
                    });
                    sbody.appendChild(frow('侧栏宽度', wInput));
                    sbody.appendChild(frow('侧栏吸顶', (function () {
                        var sw = document.createElement('label');
                        sw.className = 'bld-switch';
                        var box = document.createElement('input');
                        box.type = 'checkbox';
                        box.checked = !!pxCell.sticky;
                        box.addEventListener('change', function () {
                            touchSec(function () { pxCell.sticky = box.checked ? 1 : 0; });
                        });
                        var slider = document.createElement('i');
                        sw.appendChild(box); sw.appendChild(slider);
                        return sw;
                    })()));
                }
            }
            sbody.appendChild(frow('宽度', seg2('width', sec.width,
                [['full', '全宽'], ['boxed', '盒式']],
                function (v) { touchSec(function (s) { s.width = v; }); renderInspector(); })));

            sbody.appendChild(fgrp('间距与明暗'));
            var devKey = S.device === 'desktop' ? null : S.device;
            var padInput = document.createElement('input');
            padInput.type = 'number';
            padInput.className = 'bld-input bld-input--num';
            padInput.min = 0; padInput.max = 200;
            padInput.value = secPadOf(sec);
            padInput.addEventListener('input', function () {
                var v = Math.min(200, Math.max(0, parseInt(padInput.value, 10) || 0));
                if (devKey) {
                    sec.ov = sec.ov || {};
                    sec.ov[devKey] = sec.ov[devKey] || {};
                    sec.ov[devKey].padY = v;
                } else {
                    sec.padY = v;
                }
                markDirty(); renderCanvasMeta(); scheduleReload();
            });
            sbody.appendChild(frow('上/下距' + (devKey ? '（' + DEVNAME[S.device] + '）' : ''), padInput));
            sbody.appendChild(frow('明暗', seg2('tone', sec.tone,
                [['auto', '自动'], ['light', '亮'], ['dark', '暗']],
                function (v) { touchSec(function (s) { s.tone = v; }); })));

            if (devKey) {
                var ovCount = sec.ov && sec.ov[devKey] ? Object.keys(sec.ov[devKey]).length : 0;
                var ovBar = document.createElement('div');
                ovBar.className = 'bld-ovbar';
                ovBar.innerHTML = '📱 ' + DEVNAME[S.device] + '：已覆盖 ' + ovCount + ' 项';
                var clearBtn = document.createElement('button');
                clearBtn.type = 'button';
                clearBtn.textContent = '清除';
                clearBtn.addEventListener('click', function () {
                    touchSec(function (s) { if (s.ov) { delete s.ov[devKey]; } });
                    renderInspector();
                });
                ovBar.appendChild(clearBtn);
                sbody.appendChild(ovBar);
                sbody.appendChild(tip('桌面段修改的是基础值；平板/手机段的修改写入断点覆盖（ov 白名单）。'));
            }
        } else {
            sbody.appendChild(fgrp('背景图'));
            ['bg', 'bgDark'].forEach(function (key) {
                var row = document.createElement('div');
                row.className = 'bld-frow';
                var l = document.createElement('span');
                l.className = 'bld-frow__l';
                l.textContent = key === 'bg' ? '浅色背景' : '深色背景';
                row.appendChild(l);
                var box = document.createElement('div');
                box.className = 'bld-imgfield';
                var inp = document.createElement('input');
                inp.type = 'text';
                inp.className = 'bld-input';
                inp.placeholder = 'https://…（留空不设置）';
                inp.value = sec[key] || '';
                inp.addEventListener('input', function () {
                    sec[key] = inp.value; markDirty(); scheduleReload();
                });
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'bld-mini';
                btn.textContent = '选图';
                btn.addEventListener('click', function () {
                    if (window.EcodMedia && EcodMedia.pick) {
                        EcodMedia.pick({ value: sec[key], onPick: function (url) {
                            sec[key] = url; inp.value = url; markDirty(); scheduleReload();
                        } });
                    } else { toast('媒体库组件未加载'); }
                });
                box.appendChild(inp); box.appendChild(btn);
                row.appendChild(box);
                sbody.appendChild(row);
            });
            var rInput = document.createElement('input');
            rInput.type = 'number';
            rInput.className = 'bld-input bld-input--num';
            rInput.min = 0; rInput.max = 40;
            rInput.value = sec.radius || 0;
            rInput.addEventListener('input', function () {
                sec.radius = Math.min(40, Math.max(0, parseInt(rInput.value, 10) || 0));
                markDirty(); scheduleReload();
            });
            sbody.appendChild(fgrp('盒式圆角'));
            sbody.appendChild(frow('圆角 px', rInput));
        }
        el.appendChild(sbody);
    }
    function secPadOf(sec) {
        var dk = S.device === 'desktop' ? null : S.device;
        if (dk && sec.ov && sec.ov[dk] && sec.ov[dk].padY != null) { return sec.ov[dk].padY; }
        return sec.padY;
    }
    /* 板块级内联样式直改（不重载，参数面板即时反馈由重载兜底） */
    function renderCanvasMeta() { /* v1：视觉反馈统一走 scheduleReload */ }

    /* ==================================================================
     * 顶栏 / 状态栏
     * ================================================================ */
    function renderTop() {
        var meta = S.docs[S.docKey] || {};
        $('[data-doclabel]').textContent = meta.label || S.docKey;
        var ub = $('[data-undo]'), rb = $('[data-redo]');
        ub.disabled = !S.undo.length;
        rb.disabled = !S.redo.length;
        $('[data-undocount]').textContent = S.undo.length || '';
        $('[data-redocount]').textContent = S.redo.length || '';
        var sb = $('[data-save]');
        sb.disabled = S.saving || !isDirty();
        sb.textContent = S.saving ? '发布中…' : (isDirty() ? '发布 ●' : '已发布');
        sb.classList.toggle('bld-btn-pri--dirty', isDirty() && !S.saving);
        $('[data-delldoc]').disabled = !meta.exists;
        var lb = $('[data-lockbtn]');
        lb.textContent = S.lock ? '🔒 已锁定' : '未锁定';
        lb.classList.toggle('bld-chip--on', !!S.lock);
        lb.title = S.lock
            ? '结构已锁定（增删移/栏式/背景只读，模块参数可改）。点击解锁'
            : '锁定后画布只允许修改模块参数，结构全部只读。点击锁定';
    }
    function renderStatus() {
        $('[data-zoomval]').textContent = Math.round(S.zoom * 100) + '%';
        $('[data-readout]').textContent = (S.device === 'desktop' ? '1100px' : DEVW[S.device])
            + ' × ' + Math.round(S.zoom * 100) + '%（' + DEVNAME[S.device] + '）';
        var d = $('[data-dirty]');
        d.hidden = !isDirty();
        $('[data-saved]').hidden = isDirty();
        $('[data-rev]').textContent = 'rev ' + S.rev;
    }
    function renderDocsMenu() {
        var menu = $('[data-docmenu]');
        menu.innerHTML = Object.keys(S.docs).map(function (key) {
            var d = S.docs[key];
            return '<button type="button" class="bld-mi' + (key === S.docKey ? ' on' : '') + '" data-doc="' + esc(key) + '">'
                + '<span class="bld-mi__check">' + (key === S.docKey ? '✓' : '') + '</span>'
                + esc(d.label) + '<small>' + esc(d.badge) + (d.exists ? ' · 已建' : '') + '</small></button>';
        }).join('');
    }

    /* ==================================================================
     * Saver
     * ================================================================ */
    function api(action, payload, cb) {
        payload = payload || {};
        payload.token = CFG.token;
        payload.ver = CFG.ver;
        fetch(CFG.ajax + '?action=' + action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (r) {
            return r.json().then(function (j) { j.__status = r.status; return j; });
        }).then(function (j) {
            if (j.rev) { S.rev = j.rev; }
            if (j.stale && !S.staleWarned) {
                /* 旧标签页持旧脚本：拒绝继续写，弹确认后强刷 */
                S.staleWarned = true;
                showModal('编辑器脚本已更新',
                    '当前标签页运行的是旧脚本，继续编辑可能损坏数据。'
                    + '刷新后重试（未发布的修改会丢失，可先到「模板」菜单导出 JSON 备份）。',
                    function (pick, done) {
                        done();
                        if (pick === 'save') { window.location.reload(); }
                    });
            }
            cb(j);
        }).catch(function () {
            cb({ ok: false, error: '网络错误，请重试' });
        });
    }
    function doSave() {
        if (S.saving || !isDirty()) { return; }
        /* 文档体积软警告（§4.5/§6.9）：超 48KB 提示，不阻塞保存 */
        try {
            var sizeKB = Math.round(JSON.stringify({ sections: S.sections }).length / 1024);
            if (sizeKB > 48) { toast('文档已达 ' + sizeKB + 'KB（建议 ≤48KB），过大可能影响保存与加载'); }
        } catch (e) { /* ignore */ }
        S.saving = true;
        renderTop();
        api('publish', {
            key: S.docKey,
            sections: S.sections,
            lock: S.lock ? 1 : 0,
            wgtAreas: S.wgt.areas,
            wgtAside: S.wgt.aside,
            rev: S.rev,
            wgtRev: S.wgtRev
        }, function (j) {
            S.saving = false;
            if (!j.ok && j.__status === 409) {
                if (j.conflict === 'wgt') {
                    S.wgtRev = j.rev || S.wgtRev;
                    wgtConflictModal();
                } else {
                    S.rev = j.rev || S.rev;
                    conflictModal(j.error || '布局已被其他窗口修改');
                }
                renderTop();
                return;
            }
            if (!j.ok) { toast(j.error || '发布失败'); renderTop(); return; }
            if (Array.isArray(j.sections)) { S.sections = j.sections; }
            if (typeof j.lock !== 'undefined') { S.lock = !!j.lock; }
            if (j.areas) { S.wgt.areas = j.areas; }
            if (j.aside) { S.wgt.aside = j.aside; }
            S.rev = j.rev || S.rev;
            S.wgtRev = j.wgtRev || S.wgtRev;
            clearDirty();
            S.wgtStaged = {};
            clearDraft();
            renderTop(); renderStatus(); renderAreasTab(); renderInspector();
            toast('已发布，前台已生效');
        });
    }
    function wgtConflictModal() {
        showModal('模块布局冲突', '模块布局已被其他窗口（或模块管理台）修改。「保存并继续」= 以当前内容覆盖；「放弃改动」= 取回服务器版本。', function (pick, done) {
            if (pick === 'save') {
                S.wgtDirty = {};
                Object.keys(S.wgt.areas).forEach(function (k) { dirtyArea(k); });
                doSave();
            } else if (pick === 'discard') {
                api('wgt.get', {}, function (j) {
                    if (j.ok) {
                        S.wgt.areas = j.areas;
                        if (j.aside) { S.wgt.aside = j.aside; }
                        S.wgtRev = j.wgtRev;
                        S.wgtDirty = {};
                        S.wgtStaged = {};
                        S.dirtyWgt = false;
                        S.sel = null;
                        api('stage.discard', { key: S.docKey }, function () {
                            renderAreasTab(); renderInspector(); renderTop(); applySelection(); reloadFrame();
                            toast('已取回服务器版本');
                        });
                    }
                    done();
                });
            } else { done(); }
        });
    }
    function conflictModal(msg) {
        showModal('保存冲突', msg + '。可以选择「取回远端」放弃本地改动，或「覆盖」强制保存。', function (pick, done) {
            if (pick === 'discard') {
                api('doc.get', { key: S.docKey }, function (j) {
                    if (j.ok) {
                        S.sections = j.sections || [];
                        clearDirty(); S.sel = null;
                        api('stage.discard', { key: S.docKey }, function () {
                            renderAll(); reloadFrame();
                        });
                    }
                    done();
                });
            } else if (pick === 'save') {
                api('doc.save', { key: S.docKey, sections: S.sections, lock: S.lock ? 1 : 0, rev: S.rev }, function (j) {
                    if (j.ok) {
                        if (Array.isArray(j.sections)) { S.sections = j.sections; }
                        S.dirtyDoc = false;
                        renderTop(); renderStatus();
                        toast('已覆盖发布');
                    } else { toast(j.error || '保存失败'); }
                    done();
                });
            } else { done(); }
        });
    }

    /* ==================================================================
     * 通用弹窗 / 守卫
     * ================================================================ */
    var guardCb = null;
    function showModal(title, desc, cb) {
        $('[data-modal-title]').textContent = title;
        $('[data-modal-desc]').textContent = desc;
        guardCb = cb;
        $('[data-modal]').hidden = false;
    }
    function hideModal() { $('[data-modal]').hidden = true; guardCb = null; }
    $$('[data-guard]').forEach(function (b) {
        b.addEventListener('click', function () {
            var pick = b.getAttribute('data-guard');
            var cb = guardCb;
            hideModal();
            if (cb) {
                if (pick === 'cancel') { cb('cancel', function () {}); }
                else { cb(pick, function () {}); }
            }
        });
    });
    function guardUnsaved(title, then) {
        if (!isDirty()) { then(); return; }
        showModal(title, '当前有未保存的改动，继续将丢失。', function (pick, done) {
            if (pick === 'save') { doSave(); then(); }
            else if (pick === 'discard') {
                clearDirty();
                api('stage.discard', { key: S.docKey }, function () { reloadFrame(); });
                renderTop(); then();
            }
            done();
        });
    }

    /* ==================================================================
     * 文档切换 / 删除
     * ================================================================ */
    function switchDoc(key) {
        guardUnsaved('切换文档', function () {
            api('doc.get', { key: key }, function (j) {
                if (!j.ok) { toast(j.error || '读取失败'); return; }
                S.docKey = key;
                S.sections = j.sections || [];
                S.lock = !!j.lock;
                S.sel = null; clearDirty(); S.undo = []; S.redo = [];
                try { history.replaceState(null, '', '?doc=' + encodeURIComponent(key)); } catch (e) { /* ignore */ }
                renderAll(); renderDocsMenu();
                reloadFrame();
                checkDraft();
            });
        });
    }

    /* ==================================================================
     * 事件绑定（编辑器侧）
     * ================================================================ */
    function setTab(tab) {
        S.tab = tab;
        $$('[data-tab]').forEach(function (x) {
            x.setAttribute('aria-pressed', x.getAttribute('data-tab') === tab ? 'true' : 'false');
        });
        $('[data-libpane]').hidden = tab !== 'lib';
        $('[data-areapane]').hidden = tab !== 'areas';
        $('[data-olpane]').hidden = tab !== 'ol';
    }
    $$('[data-tab]').forEach(function (b) {
        b.addEventListener('click', function () { setTab(b.getAttribute('data-tab')); });
    });
    $('[data-lib]').addEventListener('click', function (e) {
        var card = e.target.closest && e.target.closest('[data-new]');
        if (!card) { return; }
        if (S.lock) { toast('布局已锁定：只允许修改模块参数，顶栏可解锁'); return; }
        /* 点击即追加：板块 → 文档末尾；模块 → 选中板块 main 末尾 */
        var type = card.getAttribute('data-new');
        var pending = S.pendingInsert;
        if (pending) {
            S.pendingInsert = null;
            insertAtPending(pending, type);
            return;
        }
        /* 我的行模板：卡片上的删除钮优先于「点击追加」 */
        var srowdel = e.target.closest && e.target.closest('[data-srowdel]');
        if (srowdel) {
            var delId = srowdel.getAttribute('data-srowdel');
            api('rowlib.delete', { id: delId }, function (j) {
                if (!j.ok) { toast(j.error || '删除失败'); return; }
                S.savedRows = j.savedRows || {};
                buildLib();
                toast('已删除行模板');
            });
            return;
        }
        captureUndo('click' + type + Date.now());
        if (isRowTemplateType(type)) {
            /* 行模板点击追加：目标板块与模块一致（选中板块 → 选中模块所在板块 → 末位板块） */
            var preset = rowTemplateDef(type);
            if (!preset) { return; }
            var tSec = null;
            if (S.sel && S.sel.kind === 'section') { tSec = findSec(S.sel.id); }
            if (!tSec && S.sel && (S.sel.kind === 'module' || S.sel.kind === 'cell' || S.sel.kind === 'row')) {
                var pLoc = findPath(S.sel.id);
                if (pLoc && pLoc.sec) { tSec = pLoc.sec; }
            }
            if (!tSec) { tSec = S.sections[S.sections.length - 1]; }
            if (!tSec) { tSec = newSection(null); S.sections.push(tSec); }
            if ((tSec.rows || []).length >= MAX_ROWS) { toast('单板块最多 ' + MAX_ROWS + ' 行'); return; }
            var pRow = tplRow(preset);
            tSec.rows.push(pRow);
            S.sel = { kind: 'row', id: pRow.uid };
            markDirty(); renderAll(); scheduleReload();
            toast('已追加行：' + preset.name);
            return;
        }
        if (type === 'section') {
            var sec = newSection(null);
            S.sections.push(sec);
            S.sel = { kind: 'section', id: sec.uid };
        } else {
            var target = null;
            if (S.sel && S.sel.kind === 'section') { target = findSec(S.sel.id); }
            if (!target && S.sel && S.sel.kind === 'module') {
                var loc = findMod(S.sel.id);
                if (loc) { target = loc.sec; }
            }
            if (!target) { target = S.sections[S.sections.length - 1]; }
            if (!target) { target = newSection(null); S.sections.push(target); }
            var mod = newMod(type);
            lastCell(target).mods.push(mod);
            S.sel = { kind: 'module', id: mod.uid };
        }
        markDirty(); renderAll(); scheduleReload();
    });
    function insertAtPending(pending, type) {
        captureUndo('ins' + type + Date.now());
        if (isRowTemplateType(type)) {
            var preset = rowTemplateDef(type);
            if (!preset) { return; }
            /* 行预设插入定位：板块行 → 该板块末尾；行/列/模块 → 所在行之后 */
            var pSec = null, pR = -1;
            if (pending.kind === 'section') {
                pSec = findSec(pending.id);
                pR = pSec && pSec.rows ? pSec.rows.length - 1 : -1;
            } else if (pending.kind !== 'wmod') {
                var pp = findPath(pending.id);
                if (pp && pp.sec) {
                    pSec = pp.sec;
                    pR = pp.row && pp.rowsArr ? pp.rowsArr.indexOf(pp.row) : -1;
                }
            }
            if (!pSec) { pSec = S.sections[S.sections.length - 1]; }
            if (!pSec) { pSec = newSection(null); S.sections.push(pSec); pR = -1; }
            if ((pSec.rows || []).length >= MAX_ROWS) { toast('单板块最多 ' + MAX_ROWS + ' 行'); return; }
            var pRow2 = tplRow(preset);
            pSec.rows.splice(pR + 1, 0, pRow2);
            S.sel = { kind: 'row', id: pRow2.uid };
            markDirty(); renderAll(); scheduleReload();
            toast('已在该位置插入行：' + preset.name);
            return;
        }
        if (type === 'section') {
            var newSec = newSection(null);
            var at = -1;
            if (pending.kind === 'section') {
                for (var i = 0; i < S.sections.length; i++) {
                    if (S.sections[i].uid === pending.id) { at = i; break; }
                }
            }
            if (at > -1) { S.sections.splice(at, 0, newSec); }
            else { S.sections.push(newSec); }
            S.sel = { kind: 'section', id: newSec.uid };
            markDirty();
            renderAll(); scheduleReload();
            toast('板块已插入到该位置');
            return;
        }
        if (pending.kind === 'wmod') {
            var wloc = findW(pending.id);
            if (!wloc) { toast('原位置已不存在，已取消'); return; }
            var inst = newMod(type);
            S.wgt.areas[wloc.area].splice(wloc.index, 0, inst);
            dirtyArea(wloc.area);
            S.sel = { kind: 'wmod', id: inst.uid, area: wloc.area };
            markDirty('wgt');
            renderAreasTab(); renderInspector(); applySelection(); scheduleReload();
            toast('已插入到「' + wgtAreaLabel(wloc.area) + '」该位置');
            return;
        }
        var loc = findMod(pending.id);
        if (!loc) { toast('原位置已不存在，已取消'); return; }
        var mod = newMod(type);
        loc.arr.splice(loc.index, 0, mod);
        S.sel = { kind: 'module', id: mod.uid };
        markDirty();
        renderAll(); scheduleReload();
        toast('已插入到该位置');
    }
    $('[data-find]').addEventListener('input', function (e) {
        var q = e.target.value.trim();
        $$('.bld-card', $('[data-lib]')).forEach(function (c) {
            var hit = !q || (c.getAttribute('data-label') || '').indexOf(q) > -1
                || (c.textContent || '').indexOf(q) > -1;
            c.style.display = hit ? '' : 'none';
        });
    });
    $$('[data-devseg] button').forEach(function (b) {
        b.addEventListener('click', function () {
            S.device = b.getAttribute('data-dev');
            $$('[data-devseg] button').forEach(function (x) {
                x.setAttribute('aria-pressed', x === b ? 'true' : 'false');
            });
            viewEl.style.width = DEVW[S.device];
            renderStatus(); renderInspector();
        });
    });
    function doFit() {
        S.zoom = 1;
        $('[data-zoom]').value = 100;
        viewEl.style.zoom = 1;
        renderStatus();
    }
    $('[data-fit]').addEventListener('click', doFit);
    $('[data-zoom]').addEventListener('input', function (e) {
        S.zoom = e.target.value / 100;
        viewEl.style.zoom = S.zoom;
        renderStatus();
    });
    $('[data-lockbtn]').addEventListener('click', function () {
        api('doc.lock', { key: S.docKey, lock: S.lock ? 0 : 1, rev: S.rev }, function (j) {
            if (!j.ok) { toast(j.error || '操作失败'); return; }
            S.lock = !!j.lock;
            renderTop();
            renderInspector();
            applySelection();
            toast(S.lock ? '结构已锁定：只能修改模块参数' : '已解锁：结构与参数均可编辑');
        });
    });
    $('[data-undo]').addEventListener('click', doUndo);
    $('[data-redo]').addEventListener('click', doRedo);
    $('[data-save]').addEventListener('click', doSave);
    $('[data-preview]').addEventListener('click', function () {
        var meta = S.docs[S.docKey] || {};
        if (meta.url) { window.open(meta.url, '_blank', 'noopener'); }
        else { toast('该文档类型没有独立地址'); }
    });
    /* 提示：画布是未发布草稿的预览，点「发布」前台才生效 */
    $('[data-manage]').addEventListener('click', function () {
        guardUnsaved('前往模块管理台', function () {
            window.location.href = 'widget.php';
        });
    });
    $('[data-exit]').addEventListener('click', function () {
        guardUnsaved('退出构建器', function () {
            window.location.href = 'widget.php';
        });
    });
    $('[data-docbtn]').addEventListener('click', function (e) {
        e.stopPropagation();
        var menu = $('[data-docmenu]');
        var wasHidden = menu.hidden;
        hideMenus();
        menu.hidden = !wasHidden;
    });
    document.addEventListener('click', function (e) {
        if (!e.target.closest || !e.target.closest('[data-docmenu],[data-docbtn],[data-tplmenu],[data-tplbtn],[data-snapmenu],[data-snapbtn]')) {
            hideMenus();
        }
    });
    function hideMenus() {
        $$('[data-docmenu],[data-tplmenu],[data-snapmenu]').forEach(function (m) { m.hidden = true; });
    }
    $('[data-docmenu]').addEventListener('click', function (e) {
        var mi = e.target.closest && e.target.closest('[data-doc]');
        if (mi) {
            $('[data-docmenu]').hidden = true;
            switchDoc(mi.getAttribute('data-doc'));
        }
    });
    $('[data-delldoc]').addEventListener('click', function () {
        showModal('删除这份布局', '删除后 ' + (($('[data-doclabel]').textContent) || '') + ' 将回退到回退链下一级（页面类型默认或旧版区域）。', function (pick, done) {
            if (pick === 'save') {
                api('doc.delete', { key: S.docKey, rev: S.rev }, function (j) {
                    if (j.ok) {
                        S.docs[S.docKey].exists = false;
                        S.sections = []; clearDirty(); S.sel = null;
                        renderAll(); renderDocsMenu(); reloadFrame();
                        toast('已删除，页面回退到下一级布局');
                    } else { toast(j.error || '删除失败'); }
                    done();
                });
            }
            done();
        });
    });

    function onKeydown(e) {
        if (e.target && /^(INPUT|SELECT|TEXTAREA)$/.test(e.target.tagName)) { return; }
        var mod = e.metaKey || e.ctrlKey;
        var k = (e.key || '').toLowerCase();
        if (mod && k === 's') { e.preventDefault(); doSave(); }
        else if (mod && k === 'z' && !e.shiftKey) { e.preventDefault(); doUndo(); }
        else if (mod && (k === 'y' || (k === 'z' && e.shiftKey))) { e.preventDefault(); doRedo(); }
        else if (mod && k === 'd') {
            e.preventDefault();
            if (S.sel) { canvasAct('dup', S.sel.id, S.sel.kind); }
        } else if (e.key === 'Escape') {
            if (ctxEl && !ctxEl.hidden) { closeCtxMenu(); return; }
            if (S.pendingInsert) { S.pendingInsert = null; toast('已取消插入定位'); return; }
            /* Esc 逐级上逃：module → cell → row → section → 取消选中 */
            var up = selParent();
            if (up) { S.sel = up; } else { S.sel = null; }
            applySelection(); renderOutline(); renderInspector();
        } else if (e.key === 'Delete' || e.key === 'Backspace') {
            if (S.sel) {
                e.preventDefault();
                if (S.sel.kind === 'cell') {
                    /* 删列有模块合并副作用：确认（§6.8） */
                    var pDel = findPath(S.sel.id);
                    var nMods = pDel && pDel.cell ? (pDel.cell.mods || []).length : 0;
                    var nRows = pDel && pDel.cell ? (pDel.cell.rows || []).length : 0;
                    var single = pDel && pDel.row && (pDel.row.cells || []).length <= 1;
                    showModal('删除这一列？', single
                        ? '这是该行最后一列，删除后整行移除。'
                        : (nMods || nRows
                            ? '列内 ' + nMods + ' 个模块' + (nRows ? '及 ' + nRows + ' 个嵌套行' : '') + '将并入左列。'
                            : '列内没有内容。'), function (pick, done) {
                        if (pick === 'save') { canvasAct('del', S.sel.id, 'cell'); }
                        done();
                    });
                    return;
                }
                canvasAct('del', S.sel.id, S.sel.kind);
            }
        }
    }
    document.addEventListener('keydown', onKeydown);
    window.addEventListener('beforeunload', function (e) {
        if (isDirty()) { e.preventDefault(); e.returnValue = ''; }
    });

    /* ==================================================================
     * 区域（v4 模块位置：页面类型×槽位 + 全局 + 定点，widget 台同一存储）
     * ================================================================ */
    function wgtAreaLabel(id) {
        return (S.wgt.areaMeta[id] && S.wgt.areaMeta[id].label) || id;
    }
    function findW(uidKey) {
        for (var area in S.wgt.areas) {
            var arr = S.wgt.areas[area] || [];
            for (var i = 0; i < arr.length; i++) {
                if (arr[i].uid === uidKey) { return { area: area, index: i }; }
            }
        }
        return null;
    }
    /* v4 实例变更统一入口：快照 → 变更 → 脏区域 → 重载 */
    function touchW(uidKey, fn) {
        var loc = findW(uidKey);
        if (!loc) { return; }
        captureUndo('w' + uidKey + Date.now());
        fn(S.wgt.areas[loc.area], loc.index);
        dirtyArea(loc.area);
        markDirty('wgt');
        renderAreasTab(); renderInspector();
        scheduleReload();
    }
    function wgtAct(act, uidKey) {
        if (S.lock) { toast('布局已锁定：只允许修改模块参数，顶栏可解锁'); return; }
        var loc = findW(uidKey);
        if (!loc) { return; }
        var arr = S.wgt.areas[loc.area];
        if (act === 'del') {
            captureUndo('wdel' + uidKey + Date.now());
            var delName2 = modLabel(arr[loc.index]);
            arr.splice(loc.index, 1);
            if (S.sel && S.sel.id === uidKey) { S.sel = null; }
            optimisticDom('del', uidKey);
            toast('已移除「' + delName2 + '」（⌘/Ctrl+Z 可找回）');
        } else if (act === 'eye') {
            captureUndo('weye' + uidKey + Date.now());
            arr[loc.index].on = arr[loc.index].on ? 0 : 1;
            optimisticDom('eye', uidKey);
        } else if (act === 'up' || act === 'down') {
            var j = loc.index + (act === 'up' ? -1 : 1);
            if (j < 0 || j >= arr.length) { return; }
            captureUndo('wmv' + uidKey + Date.now());
            var tmp = arr[loc.index]; arr[loc.index] = arr[j]; arr[j] = tmp;
            optimisticDom(act, uidKey);
        } else { return; }
        dirtyArea(loc.area);
        markDirty('wgt');
        renderAreasTab(); renderInspector(); applySelection(); scheduleReload();
    }
    function renderAreasTab() {
        var el = $('[data-areas]');
        if (!el) { return; }
        var pages = S.wgt.pages || {};
        var type = S.areaPage;
        var own = [], globals = [], pinned = [];
        Object.keys(S.wgt.areaMeta).forEach(function (id) {
            var m = S.wgt.areaMeta[id];
            if (m.pinned) { pinned.push(id); }
            else if (m.global) { globals.push(id); }
            else if (m.page === type) { own.push(id); }
        });
        var order = { top_fluid: 0, top_content: 1, bottom_content: 2, bottom_fluid: 3, sidebar: 4 };
        own.sort(function (a, b) {
            return ((order[S.wgt.areaMeta[a].slot] || 9) - (order[S.wgt.areaMeta[b].slot] || 9));
        });

        function areaCard(id) {
            var arr = S.wgt.areas[id] || [];
            var offN = arr.filter(function (m) { return !m.on; }).length;
            var cleanBtn = offN ? '<button type="button" class="bld-mini" data-wclean="' + esc(id)
                + '" title="移除该区域全部已停用实例">清理停用 ' + offN + '</button>' : '';
            var rows = arr.length ? arr.map(function (m) {
                var on = S.sel && S.sel.kind === 'wmod' && S.sel.id === m.uid;
                return '<div class="bld-wrow' + (on ? ' on' : '') + (m.on ? '' : ' off')
                    + '" data-wuid="' + esc(m.uid) + '" data-warea="' + esc(id) + '">'
                    + '<span class="bld-wrow__nm">' + esc(modLabel(m)) + (m.on ? '' : '（停用）') + '</span>'
                    + '<span class="bld-wrow__tls">'
                    + '<button type="button" data-wact="up" title="上移">' + ico('arrow-up') + '</button>'
                    + '<button type="button" data-wact="down" title="下移">' + ico('arrow-down') + '</button>'
                    + '<button type="button" data-wact="eye" title="启停">' + ico('eye') + '</button>'
                    + '<button type="button" data-wact="del" title="移除">' + ico('trash') + '</button>'
                    + '</span></div>';
            }).join('') : '<div class="bld-tip" style="padding:2px 0 4px">空区域 · 从「模块库」拖入</div>';
            return '<div class="bld-warea" data-areadrop="' + esc(id) + '">'
                + '<div class="bld-warea__hd">' + esc(wgtAreaLabel(id))
                + cleanBtn
                + '<small>' + (arr.length - offN) + '/' + arr.length + '</small></div>'
                + rows + '</div>';
        }
        function group(title, ids) {
            return ids.length ? '<div class="bld-fgrp">' + esc(title) + '</div>' + ids.map(areaCard).join('') : '';
        }

        var asideOpts = [['right', '右侧栏'], ['left', '左侧栏'], ['none', '无侧栏']];
        var asideCard = '<div class="bld-warea" data-asidecard><div class="bld-warea__hd">侧栏显示（按页面类型）<small>覆盖「样式」里的全站默认</small></div>'
            + Object.keys(pages).map(function (pk) {
                var cur = S.wgt.aside[pk] || '';
                return '<div class="bld-wrow" style="cursor:default"><span class="bld-wrow__nm">' + esc(pages[pk]) + '</span>'
                    + '<select class="bld-select bld-select--xs" data-asidepage="' + esc(pk) + '">'
                    + asideOpts.map(function (o) {
                        return '<option value="' + o[0] + '"' + (o[0] === cur ? ' selected' : '') + '>' + o[1] + '</option>';
                    }).join('') + '</select></div>';
            }).join('') + '</div>';

        el.innerHTML = '<div class="bld-fgrp">页面类型</div>'
            + '<select class="bld-select" data-areapage style="width:100%;margin-bottom:6px">'
            + Object.keys(pages).map(function (pk) {
                return '<option value="' + esc(pk) + '"' + (pk === type ? ' selected' : '') + '>' + esc(pages[pk]) + '</option>';
            }).join('')
            + '</select>'
            + asideCard
            + group((pages[type] || type) + ' · 位置', own)
            + group('所有页面 · 全局', globals)
            + group('页脚与浮动（定点）', pinned)
            + '<div class="bld-tip">拖「模块库」卡片进区域 = 添加；拖模块到其他区域 = 移动；拖到下方回收条 = 删除。画布里点选模块也可直接改参数。</div>';
    }
    $('[data-areas]').addEventListener('change', function (e) {
        if (e.target.matches && e.target.matches('[data-areapage]')) {
            S.areaPage = e.target.value;
            renderAreasTab();
            return;
        }
        var asideSel = e.target.matches && e.target.matches('[data-asidepage]');
        if (asideSel) {
            S.wgt.aside[e.target.getAttribute('data-asidepage')] = e.target.value;
            S.wgtDirty.__aside = 1;
            S.wgtStaged.__aside = 1;
            markDirty('wgt');
            scheduleReload();
        }
    });
    $('[data-areas]').addEventListener('click', function (e) {
        var clean = e.target.closest && e.target.closest('[data-wclean]');
        if (clean) {
            var areaId = clean.getAttribute('data-wclean');
            var arr = S.wgt.areas[areaId] || [];
            var kept = arr.filter(function (m) { return !!m.on; });
            if (kept.length === arr.length) { return; }
            captureUndo('wclean' + areaId + Date.now());
            S.wgt.areas[areaId] = kept;
            dirtyArea(areaId);
            markDirty('wgt');
            renderAreasTab(); scheduleReload();
            toast('已清理 ' + (arr.length - kept.length) + ' 个停用实例（⌘/Ctrl+Z 可找回）');
            return;
        }
        var act = e.target.closest && e.target.closest('[data-wact]');
        if (act) {
            var row = act.closest('[data-wuid]');
            if (row) { wgtAct(act.getAttribute('data-wact'), row.getAttribute('data-wuid')); }
            return;
        }
        var row2 = e.target.closest && e.target.closest('[data-wuid]');
        if (row2) {
            S.sel = { kind: 'wmod', id: row2.getAttribute('data-wuid'), area: row2.getAttribute('data-warea') };
            S.itab = 'content';
            renderAreasTab(); renderInspector(); applySelection();
        }
    });

    /* ==================================================================
     * 模板：预置套用 / 导出 / 导入（P2）
     * ================================================================ */
    function applySections(sections, note) {
        if (S.lock) { toast('布局已锁定：套用模板 / 导入前请先解锁'); return; }
        captureUndo('apply' + Date.now());
        /* 模板/导入文件可能是 v5 结构：统一走客户端兜底规范化 */
        S.sections = normSections(sections);
        S.sel = null;
        markDirty();
        renderAll();
        scheduleReload();
        if (note) { toast(note); }
    }
    function renderTplMenu() {
        var tpls = CFG.templates || {};
        var mine = S.savedDocs || {};
        $('[data-tplmenu]').innerHTML = '<div class="bld-menu__hd">存当前布局为模板</div>'
            + '<div class="bld-tplsave"><input type="text" class="bld-input" data-tplname placeholder="模板名称（同名覆盖）" maxlength="40">'
            + '<button type="button" class="bld-mini" data-tpldost>保存</button></div>'
            + '<div class="bld-menu__hd">预置模板（覆盖当前布局）</div>'
            + Object.keys(tpls).map(function (k) {
                var t = tpls[k];
                return '<button type="button" class="bld-mi" data-tpl="' + esc(k) + '">'
                    + '<span class="bld-mi__check"></span>' + esc(t.name)
                    + '<small>' + esc(t.desc || '') + '</small></button>';
            }).join('')
            + (Object.keys(mine).length
                ? '<div class="bld-menu__hd">我的模板</div>'
                    + Object.keys(mine).map(function (k) {
                        var d = mine[k];
                        return '<button type="button" class="bld-mi" data-tplmine="' + esc(k) + '">'
                            + '<span class="bld-mi__check"></span>' + esc(d.name || k)
                            + '<small>' + (d.count || 0) + ' 板块 · 点击套用</small>'
                            + '<span class="bld-tpldel" data-tpldel="' + esc(k) + '" title="删除模板">×</span></button>';
                    }).join('')
                : '')
            + '<div class="bld-menu__div"></div>'
            + '<button type="button" class="bld-mi" data-tpladopt><span class="bld-mi__check"></span>从旧版区域导入<small>覆盖当前布局</small></button>'
            + '<button type="button" class="bld-mi" data-tplexport><span class="bld-mi__check"></span>导出当前布局<small>JSON</small></button>'
            + '<button type="button" class="bld-mi" data-tplimport><span class="bld-mi__check"></span>导入布局 JSON<small>覆盖当前</small></button>';
    }
    function exportLayout() {
        var payload = {
            app: 'ecod-builder', version: 6, doc: S.docKey,
            exportedAt: new Date().toISOString(), sections: S.sections
        };
        var blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'ecod-layout-' + S.docKey.replace(/:/g, '-') + '.json';
        a.click();
        setTimeout(function () { URL.revokeObjectURL(a.href); }, 3000);
        toast('已导出当前布局');
    }
    function importLayoutFile(file) {
        var reader = new FileReader();
        reader.onload = function () {
            var obj = null;
            try { obj = JSON.parse(String(reader.result)); } catch (err) { obj = null; }
            if (!obj || obj.app !== 'ecod-builder' || !Array.isArray(obj.sections)) {
                toast('不是有效的布局文件（需要本构建器导出的 JSON）');
                return;
            }
            guardUnsaved('导入布局', function () {
                applySections(normSections(JSON.parse(JSON.stringify(obj.sections))),
                    '已导入 ' + obj.sections.length + ' 个板块，记得保存');
            });
        };
        reader.readAsText(file);
    }
    $('[data-tplbtn]').addEventListener('click', function (e) {
        e.stopPropagation();
        renderTplMenu();
        var menu = $('[data-tplmenu]');
        var wasHidden = menu.hidden;
        hideMenus();
        menu.hidden = !wasHidden;
    });
    $('[data-tplmenu]').addEventListener('click', function (e) {
        /* 我的模板：删除钮优先于整行套用 */
        var tpldel = e.target.closest && e.target.closest('[data-tpldel]');
        if (tpldel) {
            api('doclib.delete', { id: tpldel.getAttribute('data-tpldel') }, function (j) {
                if (!j.ok) { toast(j.error || '删除失败'); return; }
                S.savedDocs = j.savedDocs || {};
                renderTplMenu();
                toast('已删除页面模板');
            });
            return;
        }
        var tpldost = e.target.closest && e.target.closest('[data-tpldost]');
        if (tpldost) {
            if (!S.sections.length) { toast('当前布局为空，无可保存'); return; }
            var nameIn = $('[data-tplname]');
            api('doclib.save', {
                name: nameIn ? nameIn.value : '',
                sections: S.sections, lock: S.lock ? 1 : 0
            }, function (j) {
                if (!j.ok) { toast(j.error || '保存失败'); return; }
                S.savedDocs = j.savedDocs || S.savedDocs;
                renderTplMenu();
                toast('已存为页面模板，在「我的模板」一键套用');
            });
            return;
        }
        var tpl = e.target.closest && e.target.closest('[data-tpl]');
        if (tpl) {
            var t = (CFG.templates || {})[tpl.getAttribute('data-tpl')];
            if (t && Array.isArray(t.sections)) {
                guardUnsaved('套用模板「' + t.name + '」', function () {
                    applySections(JSON.parse(JSON.stringify(t.sections)), '已套用模板，记得保存');
                });
            }
            return;
        }
        var mine = e.target.closest && e.target.closest('[data-tplmine]');
        if (mine) {
            var md = (S.savedDocs || {})[mine.getAttribute('data-tplmine')];
            if (md && Array.isArray(md.sections)) {
                guardUnsaved('套用模板「' + (md.name || '') + '」', function () {
                    applySections(normSections(JSON.parse(JSON.stringify(md.sections))),
                        '已套用「' + (md.name || '模板') + '」，记得保存');
                });
            }
            return;
        }
        if (e.target.closest && e.target.closest('[data-tpladopt]')) {
            showModal('从旧版区域导入？',
                '把该页面类型的旧版区域内容转换成可视化布局：现有板块保留，'
                + '顶部区域并入最前、正文区（用「正文流」承接）与底部区域接在后面；'
                + '转换后旧区域清空（不重复渲染）。未保存的修改会丢失，可先导出 JSON 备份。',
                function (pick, done) {
                    done();
                    if (pick !== 'save') { return; }
                    api('doc.adopt', { key: S.docKey }, function (j) {
                        if (!j.ok) { toast(j.error || '转换失败'); return; }
                        applySections(normSections(j.sections || []), '已从旧版区域转换为可视化布局');
                    });
                });
            return;
        }
        if (e.target.closest && e.target.closest('[data-tplexport]')) { exportLayout(); return; }
        if (e.target.closest && e.target.closest('[data-tplimport]')) { $('[data-tplfile]').click(); }
    });
    $('[data-tplfile]').addEventListener('change', function (e) {
        if (e.target.files && e.target.files[0]) { importLayoutFile(e.target.files[0]); }
        e.target.value = '';
    });

    /* ==================================================================
     * 本地草稿（WP autosave 惯例：崩溃 / 断网最多丢最后一次操作）
     * ================================================================ */
    function draftKey() { return 'ecod-bld-draft:' + S.docKey; }
    function scheduleDraft() {
        clearTimeout(scheduleDraft.t);
        scheduleDraft.t = setTimeout(function () {
            try {
                localStorage.setItem(draftKey(),
                    JSON.stringify({ t: Date.now(), sections: S.sections }));
            } catch (err) { /* 隐私模式等场景静默降级 */ }
        }, 800);
    }
    function clearDraft() {
        try { localStorage.removeItem(draftKey()); } catch (err) { /* ignore */ }
    }
    function checkDraft() {
        var raw = null;
        try { raw = localStorage.getItem(draftKey()); } catch (err) { return; }
        if (!raw) { return; }
        var d = null;
        try { d = JSON.parse(raw); } catch (err) { return; }
        if (!d || !Array.isArray(d.sections)) { return; }
        if (JSON.stringify(d.sections) === JSON.stringify(S.sections)) { return; }
        var when = d.t ? new Date(d.t).toTimeString().slice(0, 5) : '';
        showModal('发现本地草稿',
            '这份文档有一个未保存的本地草稿（' + when + '）。「恢复」使用草稿内容，'
            + '「放弃改动」丢弃草稿并继续用服务器版本。',
            function (pick, done) {
                if (pick === 'save') {
                    /* 草稿可能写于 v5 时代：恢复时兜底规范化 */
                    S.sections = normSections(d.sections);
                    S.sel = null;
                    markDirty();
                    renderAll();
                    scheduleReload();
                    toast('已恢复本地草稿');
                } else if (pick === 'discard') {
                    clearDraft();
                }
                done();
            });
    }

    /* ==================================================================
     * 保存历史（每次保存的快照，可恢复）
     * ================================================================ */
    function loadSnapMenu() {
        var menu = $('[data-snapmenu]');
        menu.innerHTML = '<div class="bld-menu__hd">本布局的保存历史（最多 8 份）</div>'
            + '<div class="bld-tip" style="padding:4px 10px 8px">载入中…</div>';
        api('snap.list', { key: S.docKey }, function (j) {
            if (!j.ok) { menu.innerHTML = '<div class="bld-tip">读取失败</div>'; return; }
            if (!j.items.length) {
                menu.innerHTML = '<div class="bld-menu__hd">本布局的保存历史</div>'
                    + '<div class="bld-tip" style="padding:4px 10px 8px">还没有历史：每次保存会自动留一份快照。</div>';
                return;
            }
            menu.innerHTML = '<div class="bld-menu__hd">本布局的保存历史（恢复后需再保存）</div>'
                + j.items.map(function (it) {
                    var d = new Date(it.t * 1000);
                    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
                    var when = (d.getMonth() + 1) + '-' + d.getDate() + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
                    return '<button type="button" class="bld-mi" data-snap="' + esc(it.id) + '">'
                        + '<span class="bld-mi__check"></span>' + esc(when)
                        + '<small>' + it.count + ' 个板块</small></button>';
                }).join('');
        });
    }
    $('[data-snapbtn]').addEventListener('click', function (e) {
        e.stopPropagation();
        var menu = $('[data-snapmenu]');
        var wasHidden = menu.hidden;
        hideMenus();
        if (wasHidden) { menu.hidden = false; loadSnapMenu(); }
    });
    $('[data-snapmenu]').addEventListener('click', function (e) {
        var mi = e.target.closest && e.target.closest('[data-snap]');
        if (!mi) { return; }
        $('[data-snapmenu]').hidden = true;
        showModal('恢复到这份历史？', '画布将载入该快照内容（当前未保存的改动仍在撤销栈里，可 ⌘/Ctrl+Z 找回）。恢复后需点「保存」才会写入。', function (pick, done) {
            if (pick === 'save') {
                api('snap.restore', { key: S.docKey, id: mi.getAttribute('data-snap'), rev: S.rev }, function (j) {
                    if (!j.ok) { toast(j.error || '恢复失败'); done(); return; }
                    applySections(j.sections, '已恢复到历史版本，记得保存');
                });
            }
            done();
        });
    });

    /* ==================================================================
     * 站点样式（主题设置外观/布局子集，双端同源）
     * ================================================================ */
    var setVals = {};
    var setDefs = null;
    function openSettings() {
        api('settings.get', {}, function (j) {
            if (!j.ok) { toast(j.error || '读取失败'); return; }
            setDefs = j.defs;
            setVals = j.values || {};
            renderSettings();
            $('[data-setmodal]').hidden = false;
        });
    }
    function renderSettings() {
        var body = $('[data-setbody]');
        var html = '';
        (setDefs.groups || []).forEach(function (g) {
            html += '<div class="bld-fgrp">' + esc(g.title) + '</div>';
            g.fields.forEach(function (key) {
                var f = setDefs.fields[key];
                if (!f) { return; }
                html += '<div data-setfield="' + esc(key) + '"></div>';
            });
        });
        body.innerHTML = html;
        (setDefs.groups || []).forEach(function (g) {
            g.fields.forEach(function (key) {
                var f = setDefs.fields[key];
                var holder = body.querySelector('[data-setfield="' + key + '"]');
                if (!f || !holder) { return; }
                holder.appendChild(EcodFields.render(f, setVals[key], function (v) {
                    setVals[key] = v;
                }));
            });
        });
    }
    $('[data-setbtn]').addEventListener('click', openSettings);
    $('[data-setclose]').addEventListener('click', function () { $('[data-setmodal]').hidden = true; });
    $('[data-setmodal]').addEventListener('click', function (e) {
        if (e.target === $('[data-setmodal]')) { $('[data-setmodal]').hidden = true; }
    });
    $('[data-setsave]').addEventListener('click', function () {
        var btn = $('[data-setsave]');
        btn.disabled = true;
        api('settings.save', { values: setVals }, function (j) {
            btn.disabled = false;
            if (!j.ok) { toast(j.error || '保存失败'); return; }
            setVals = j.values || setVals;
            $('[data-setmodal]').hidden = true;
            toast('样式已保存，前台立即生效');
            reloadFrame();
        });
    });

    /* ==================================================================
     * 启动
     * ================================================================ */
    function renderAll() {
        renderTop();
        renderStatus();
        renderOutline();
        renderAreasTab();
        renderInspector();
    }
    frame.addEventListener('load', onFrameLoad);
    S.areaPage = ({ home: 'home', page: 'page', single: 'single', cat: 'cat', tag: 'tag',
        search: 'search', author: 'author', custom: 'custom' })[S.docKey.split(':')[0]] || 'home';
    buildLib();
    renderDocsMenu();
    viewEl.style.width = DEVW.desktop;
    renderAll();
    checkDraft();
    if (!CFG.canvas) {
        $('[data-empty]').hidden = false;
        frame.removeAttribute('src');
    }
    bindDragSources();
})();
