/**
 * Ecod Theme · 模块管理台交互
 *
 * 布局照 WordPress「外观 → 小工具」经典设计：
 *  1. 左栏「可用模块」：全部模块卡，可搜索；拖到右栏区域 = 添加实例；
 *     单击卡片展开目标位置列表（列出全部区域，已满的置灰）
 *  2. 右栏「区域盒子」：全部区域按页面类型分组、组内一行多列；区域头即折叠开关
 *     （空区域默认收起，手动状态记 localStorage）；折叠区仍是投放目标；
 *     实例行点击头部内联展开参数表单 + 操作行；有表单展开的区域横跨整行
 *  3. 显式保存：改动先暂存（状态点「未保存」+ 按钮点亮），点「保存改动」
 *     或 Ctrl/Cmd+S 按顺序提交全部脏区域，任一失败即停可重试；
 *     侧栏显示改动走同一队列（伪区域 __aside）
 *  4. 顶部「侧栏显示（按页面）」折叠卡：8 种页面类型的侧栏开关
 *  5. 乐观锁：每次保存带 rev，冲突返回 409 时提示重新载入；
 *     自由投放：不做区域白名单，唯一拦截是区域已满（新添加时）
 *
 * 数据由 widget.php 输出到 window.ECOD_WGT，渲染仍走前台那套块模板。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @since   2026-10-29
 * @version 2.3.1
 * @link    https://ecod.cc
 *
 * Copyright (c) 2026 Ecod. All Rights Reserved. · https://ecod.cc
 */
(function () {
    'use strict';

    var DATA = window.ECOD_WGT;
    if (!DATA) {
        return;
    }

    var AREAS = DATA.areas;
    var DEFS = DATA.defs;
    var PAGES = DATA.pages;
    var LIMIT = DATA.limit || 20;

    var state = {
        rev: DATA.rev,
        saving: 0,
        savingAll: false,
        dirty: false,
        dirtyAreas: {},     /* 暂存区：{areaId:true}，点「保存改动」才提交 */
        drag: null,         /* {kind:'new',type} | {kind:'move',area,uid,type} */
        find: '',
        libOpen: '',        /* 左栏展开目标列表的模块 type */
        open: null,         /* 右栏展开表单的实例 {area,uid} */
        fold: loadFold()    /* 区域折叠状态：{areaId:true/false}，空区域默认折叠 */
    };

    var inst = {};
    Object.keys(AREAS).forEach(function (id) {
        inst[id] = (DATA.instances[id] || []).map(function (row) {
            return {
                uid: String(row.uid),
                type: String(row.type),
                on: !!row.on,
                params: clone(row.params || {})
            };
        });
    });

    /* ---------------- 基础工具 ---------------- */

    function $(sel, root) {
        return (root || document).querySelector(sel);
    }

    function $$(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }

    function clone(obj) {
        return JSON.parse(JSON.stringify(obj || {}));
    }

    function esc(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function el(tag, cls, html) {
        var node = document.createElement(tag);
        if (cls) {
            node.className = cls;
        }
        if (html != null) {
            node.innerHTML = html;
        }
        return node;
    }

    function toast(msg, tone) {
        var box = $('[data-toast]');
        if (!box) {
            return;
        }
        box.textContent = msg;
        box.setAttribute('data-tone', tone || '');
        box.hidden = false;
        window.clearTimeout(toast._t);
        toast._t = window.setTimeout(function () {
            box.hidden = true;
        }, 3200);
    }

    function mark(stateName, text) {
        var box = $('[data-save-state]');
        if (box) {
            box.setAttribute('data-state', stateName);
            box.textContent = text || {
                idle: '已保存',
                saving: '保存中…',
                dirty: '未保存',
                error: '保存失败'
            }[stateName] || '';
        }
        syncSaveBtn(stateName);
    }

    /* 顶部「保存改动」按钮：有暂存改动才点亮 */
    function syncSaveBtn(stateName) {
        var btn = $('[data-save-btn]');
        if (!btn) {
            return;
        }
        var dirty = Object.keys(state.dirtyAreas).length > 0;
        if (stateName === 'saving') {
            btn.disabled = true;
            btn.textContent = '保存中…';
            btn.classList.remove('is-dirty');
        } else {
            btn.disabled = !dirty;
            btn.textContent = dirty ? '保存改动' : '已保存';
            btn.classList.toggle('is-dirty', dirty);
        }
    }

    function iconOf(type) {
        var def = DEFS[type] || {};
        return DATA.icons[def.icon] || '';
    }

    /* ---------------- 数据操作 ---------------- */

    function rows(area) {
        return inst[area] || (inst[area] = []);
    }

    function findRow(area, uid) {
        var list = rows(area);
        for (var i = 0; i < list.length; i++) {
            if (list[i].uid === uid) {
                return list[i];
            }
        }
        return null;
    }

    /* ---------------- 区域折叠（localStorage 记忆） ---------------- */

    var FOLD_KEY = 'ecod-wgt-fold';

    function loadFold() {
        try {
            var raw = window.localStorage.getItem(FOLD_KEY);
            var obj = raw ? JSON.parse(raw) : null;
            return obj && typeof obj === 'object' ? obj : {};
        } catch (err) {
            return {};   /* 隐私模式 / 存储不可用：只是不记忆 */
        }
    }

    function saveFold() {
        try {
            window.localStorage.setItem(FOLD_KEY, JSON.stringify(state.fold));
        } catch (err) {
            /* 同上，静默降级 */
        }
    }

    /* 用户手动改过 → 以手动为准；没动过 → 有实例展开、空区域收起 */
    function isFolded(id) {
        if (Object.prototype.hasOwnProperty.call(state.fold, id)) {
            return !!state.fold[id];
        }
        return rows(id).length === 0;
    }

    function setFold(id, folded) {
        state.fold[id] = !!folded;
        saveFold();
    }

    function toggleFold(id) {
        var next = !isFolded(id);
        setFold(id, next);
        if (next && state.open && state.open.area === id) {
            state.open = null;   /* 折叠顺手收起表单，下次展开不会突兀 */
        }
        renderAreas();
    }

    function foldAll(folded) {
        Object.keys(AREAS).forEach(function (id) {
            state.fold[id] = folded;
        });
        state.open = null;
        saveFold();
        renderAreas();
    }

    function uniqueUid(area, type, preferred) {
        var list = rows(area);
        var taken = {};
        list.forEach(function (row) {
            taken[row.uid] = true;
        });
        if (preferred && !taken[preferred]) {
            return preferred;
        }
        var n = 1;
        while (taken[type + '-' + n]) {
            n++;
        }
        return type + '-' + n;
    }

    function defaultsOf(type) {
        var def = DEFS[type] || {};
        var out = {};
        Object.keys(def.params || {}).forEach(function (key) {
            var param = def.params[key];
            var value = param.default;
            if (typeof value === 'undefined' || value === null) {
                value = param.type === 'switch' ? 1 : '';
            }
            out[key] = value;
        });
        return out;
    }

    function isLocked(row) {
        var def = DEFS[row.type] || {};
        return !!(def.fixed || def.required);
    }

    function areaFull(area) {
        return rows(area).length >= LIMIT;
    }

    /* 自由投放：不做区域白名单，唯一拦截是区域已满（新添加时） */
    function canDrop(area, drag) {
        if (!drag) {
            return false;
        }
        if (drag.kind === 'move') {
            return true; /* 同区域排序、跨区域移动都允许 */
        }
        return !areaFull(area);
    }

    /* ---------------- 保存 ---------------- */

    function saveArea(area) {
        var payload = {
            token: DATA.token,
            rev: state.rev,
            area: area,
            instances: rows(area)
        };
        state.saving++;
        mark('saving');

        return window.fetch(DATA.ajax + '?action=save', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (res) {
            return res.json().then(function (data) {
                return { status: res.status, data: data };
            }).catch(function () {
                return { status: res.status, data: { ok: false, error: '响应不是合法 JSON' } };
            });
        }).then(function (out) {
            state.saving = Math.max(0, state.saving - 1);

            if (out.status === 409) {
                mark('error', '有冲突');
                toast(out.data.error || '已被其他窗口修改，请重新载入', 'bad');
                return false;
            }
            if (!out.data || !out.data.ok) {
                mark('error');
                toast((out.data && out.data.error) || '保存失败', 'bad');
                return false;
            }

            state.rev = out.data.rev || state.rev;
            delete state.dirtyAreas[area];
            state.dirty = Object.keys(state.dirtyAreas).length > 0;
            if (state.saving === 0) {
                mark(state.dirty ? 'dirty' : 'idle');
            }
            return true;
        }).catch(function () {
            state.saving = Math.max(0, state.saving - 1);
            mark('error');
            toast('网络错误，改动未保存', 'bad');
            return false;
        });
    }

    function queueSave(area) {
        /* 只暂存不提交：等「保存改动」按钮 / Ctrl+S 统一提交 */
        state.dirtyAreas[area] = true;
        state.dirty = true;
        mark('dirty');
    }

    /* 统一提交：按顺序保存全部脏区域，任一失败即停（保留暂存，可重试） */
    function saveAll() {
        if (state.savingAll) {
            return;
        }
        var areas = Object.keys(state.dirtyAreas);
        if (!areas.length) {
            return;
        }
        state.savingAll = true;
        mark('saving');

        var failed = 0;
        var chain = Promise.resolve();
        areas.forEach(function (area) {
            chain = chain.then(function () {
                var job = area === '__aside' ? saveAside() : saveArea(area);
                return job.then(function (ok) {
                    if (!ok) {
                        failed++;
                    }
                });
            });
        });

        return chain.then(function () {
            state.savingAll = false;
            var left = Object.keys(state.dirtyAreas).length;
            mark(left ? 'error' : 'idle');
            if (failed) {
                toast('有 ' + failed + ' 项没保存成功，可再点「保存改动」重试', 'bad');
            } else {
                toast('已保存 ' + areas.length + ' 项改动', 'ok');
            }
        });
    }

    /* 跨区域移动：两个区域各存一次，串行避免 rev 冲突 */
    function moveTo(from, to, uid, index) {
        if (!from || !to || from === to) {
            return;
        }
        var row = findRow(from, uid);
        if (!row) {
            return;
        }
        var list = rows(from);
        list.splice(list.indexOf(row), 1);

        row.uid = uniqueUid(to, row.type, uid);
        var target = rows(to);
        if (typeof index === 'number' && index >= 0 && index <= target.length) {
            target.splice(index, 0, row);
        } else {
            target.push(row);
        }

        state.open = { area: to, uid: row.uid };
        setFold(to, false);   /* 移过去要能看见落点 */

        queueSave(from);
        queueSave(to);
        render();
    }

    /* 同区域内排序：把 uid 移到 index（drop 在某行之前） */
    function reorderTo(area, uid, index) {
        var list = rows(area);
        var from = -1;
        list.forEach(function (row, i) {
            if (row.uid === uid) {
                from = i;
            }
        });
        if (from < 0) {
            return;
        }
        var moved = list.splice(from, 1)[0];
        if (index > from) {
            index--;
        }
        if (index < 0) {
            index = 0;
        }
        if (index > list.length) {
            index = list.length;
        }
        list.splice(index, 0, moved);
        queueSave(area);
    }

    /* ---------------- 左栏：可用模块 ---------------- */

    function usedCount(type) {
        var n = 0;
        Object.keys(inst).forEach(function (area) {
            rows(area).forEach(function (row) {
                if (row.type === type) {
                    n++;
                }
            });
        });
        return n;
    }

    function libCard(type) {
        var def = DEFS[type] || { name: type, desc: '' };
        var open = state.libOpen === type;

        var card = el('div', 'wgt-libcard' + (open ? ' is-open' : ''));
        card.setAttribute('data-type', type);
        card.setAttribute('draggable', open ? 'false' : 'true');

        card.appendChild(el('i', 'wgt-grip'));
        card.appendChild(el('span', 'wgt-card__ic', iconOf(type)));

        var main = el('div', 'wgt-libcard__main');
        main.appendChild(el('b', '', esc(def.name) + (usedCount(type) ? ' <i class="wgt-used">× ' + usedCount(type) + '</i>' : '')));
        main.appendChild(el('small', '', esc(def.desc || type)));
        card.appendChild(main);
        card.appendChild(el('span', 'wgt-libcard__go', open ? '收起 ▴' : '添加 ▾'));

        card.addEventListener('click', function () {
            state.libOpen = open ? '' : type;
            renderLib();
        });

        card.addEventListener('dragstart', function (event) {
            state.drag = { kind: 'new', type: type };
            card.classList.add('is-drag');
            try {
                event.dataTransfer.setData('text/plain', type);
                event.dataTransfer.effectAllowed = 'copy';
            } catch (err) {
                /* Safari 旧版忽略 */
            }
        });
        card.addEventListener('dragend', function () {
            state.drag = null;
            card.classList.remove('is-drag');
            $$('.wgt-area').forEach(function (node) {
                node.classList.remove('is-drop');
            });
        });

        if (open) {
            var targets = el('div', 'wgt-targets');
            areaGroups().forEach(function (group) {
                group.ids.forEach(function (id) {
                    var full = areaFull(id);
                    var btn = el('button', 'wgt-target' + (full ? ' off' : ''));
                    btn.type = 'button';
                    btn.innerHTML = '<b>' + esc(AREAS[id].label) + '</b><span>' +
                        (full ? '已满 ' + LIMIT : '+ 添加') + '</span>';
                    if (full) {
                        btn.disabled = true;
                    } else {
                        btn.addEventListener('click', function (event) {
                            event.stopPropagation();
                            createAdd(id, type, rows(id).length);
                        });
                    }
                    targets.appendChild(btn);
                });
            });
            card.appendChild(targets);
        }

        return card;
    }

    function renderLib() {
        var host = $('[data-lib]');
        if (!host) {
            return;
        }
        host.innerHTML = '';

        var find = state.find.trim().toLowerCase();
        var shown = 0;

        Object.keys(DEFS).forEach(function (type) {
            var def = DEFS[type];
            if (find && (def.name + ' ' + (def.desc || '') + ' ' + type).toLowerCase().indexOf(find) === -1) {
                return;
            }
            host.appendChild(libCard(type));
            shown++;
        });

        if (!shown) {
            host.appendChild(el('div', 'wgt-empty', '<b>没有匹配的模块</b><p>换个关键词试试。</p>'));
        }
    }

    /* ---------------- 右栏：区域盒子 ---------------- */

    /* 分组：全局 → 页脚与浮动控件 → 按页面类型（不含「侧栏显示」伪区域） */
    function areaGroups() {
        var groups = [];
        var globals = [];
        var pinned = [];

        Object.keys(AREAS).forEach(function (id) {
            if (AREAS[id].global) {
                globals.push(id);
            } else if (AREAS[id].pinned) {
                pinned.push(id);
            }
        });

        if (globals.length) {
            groups.push({ key: 'global', label: '全局', ids: globals });
        }
        if (pinned.length) {
            groups.push({ key: 'pinned', label: '页脚与浮动控件', ids: pinned });
        }

        Object.keys(PAGES).forEach(function (page) {
            var ids = [];
            Object.keys(AREAS).forEach(function (id) {
                if (AREAS[id].page === page) {
                    ids.push(id);
                }
            });
            if (ids.length) {
                groups.push({ key: page, label: PAGES[page], ids: ids });
            }
        });

        return groups;
    }

    function createAdd(area, type, index) {
        var def = DEFS[type] || {};
        if (areaFull(area)) {
            toast('这个位置最多放 ' + LIMIT + ' 个模块', 'bad');
            return;
        }
        var row = {
            uid: uniqueUid(area, type, ''),
            type: type,
            on: 1,
            params: defaultsOf(type)
        };
        var list = rows(area);
        if (typeof index === 'number' && index >= 0 && index <= list.length) {
            list.splice(index, 0, row);
        } else {
            list.push(row);
        }
        state.libOpen = '';
        state.open = { area: area, uid: row.uid };
        setFold(area, false);   /* 加完要能看见 */
        render();
        queueSave(area);
        toast('已把「' + def.name + '」加到「' + AREAS[area].label + '」', 'ok');
    }

    function areaEl(id) {
        var meta = AREAS[id];
        var list = rows(id);
        var editing = !!state.open && state.open.area === id;
        var folded = isFolded(id);

        var box = el('div', 'wgt-area' + (folded ? ' is-fold' : '') + (editing ? ' is-wide' : ''));
        box.setAttribute('data-area', id);

        /* 区域头 = 折叠开关（同时也是投放目标） */
        var hd = el('div', 'wgt-area__hd');
        hd.setAttribute('role', 'button');
        hd.setAttribute('tabindex', '0');
        hd.setAttribute('aria-expanded', folded ? 'false' : 'true');
        hd.setAttribute('title', (folded ? '展开' : '折叠') + '「' + meta.label + '」');
        hd.appendChild(el('b', '', esc(meta.label)));
        hd.appendChild(el('span', '', list.length + ' / ' + LIMIT + (meta.pinned ? ' · 定点' : '')));
        hd.addEventListener('click', function () {
            toggleFold(id);
        });
        hd.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar') {
                event.preventDefault();
                toggleFold(id);
            }
        });
        box.appendChild(hd);

        function buildBody() {
            var body = el('div', 'wgt-area__list');
            rows(id).forEach(function (row) {
                body.appendChild(itemEl(id, row));
            });
            if (!rows(id).length) {
                body.appendChild(el('div', 'wgt-area__hint', '空空如也 —— 从左边拖一个模块进来'));
            }
            return body;
        }

        if (!folded) {
            box.appendChild(buildBody());
        }

        /* 指针是否落在某个实例行上（行自己处理排序落点） */
        function overItem(event) {
            var node = event.target;
            while (node && node !== box) {
                if (node.classList && node.classList.contains('wgt-item')) {
                    return node;
                }
                node = node.parentNode;
            }
            return null;
        }

        /* 拖到头上自动展开：只就地补 body，不整栏重绘，
           否则拖拽源节点被销毁，浏览器会直接取消这次 drag */
        function unfoldInline() {
            if (!isFolded(id)) {
                return;
            }
            setFold(id, false);
            box.classList.remove('is-fold');
            hd.setAttribute('aria-expanded', 'true');
            hd.setAttribute('title', '折叠「' + meta.label + '」');
            box.appendChild(buildBody());
        }

        /* 区域级投放：拖到空白 = 追加到末尾；只有区域已满才拦截 */
        box.addEventListener('dragover', function (event) {
            if (!state.drag) {
                return;
            }
            if (!canDrop(id, state.drag)) {
                return;
            }
            if (overItem(event)) {
                return;
            }
            event.preventDefault();
            box.classList.add('is-drop');
            if (isFolded(id)) {
                window.clearTimeout(box._unfold);
                box._unfold = window.setTimeout(unfoldInline, 450);
            }
        });
        box.addEventListener('dragleave', function (event) {
            if (box.contains(event.relatedTarget)) {
                return;
            }
            box.classList.remove('is-drop');
            window.clearTimeout(box._unfold);
        });
        box.addEventListener('drop', function (event) {
            box.classList.remove('is-drop');
            window.clearTimeout(box._unfold);
            if (!state.drag) {
                return;
            }
            if (!canDrop(id, state.drag)) {
                event.preventDefault();
                toast('「' + (AREAS[id].label || id) + '」已满 ' + LIMIT + ' 个模块', 'bad');
                return;
            }
            if (overItem(event)) {
                return;
            }
            event.preventDefault();
            finishDrop(id, rows(id).length);
        });

        return box;
    }

    function finishDrop(area, index) {
        var drag = state.drag;
        state.drag = null;
        if (!drag) {
            return;
        }
        if (drag.kind === 'new') {
            createAdd(area, drag.type, index);
            return;
        }
        if (drag.area === area) {
            reorderTo(area, drag.uid, index);
            render();
        } else {
            moveTo(drag.area, area, drag.uid, index);
        }
    }

    function itemEl(area, row) {
        var def = DEFS[row.type] || { name: row.type, desc: '' };
        var locked = isLocked(row);
        var open = !!state.open && state.open.area === area && state.open.uid === row.uid;

        var item = el('div', 'wgt-item' + (open ? ' is-open' : '') + (row.on ? '' : ' is-off'));
        item.setAttribute('data-uid', row.uid);
        item.setAttribute('draggable', locked ? 'false' : 'true');

        var head = el('div', 'wgt-item__head');

        head.appendChild(el('i', 'wgt-grip'));
        head.appendChild(el('span', 'wgt-card__ic', iconOf(row.type)));
        head.appendChild(el('b', 'wgt-item__name', esc(def.name) + (locked ? ' <i class="wgt-lock">锁定</i>' : '')));
        head.appendChild(el('span', 'wgt-item__go', open ? '▾' : '▸'));

        var sw = el('button', 'wgt-sw');
        sw.type = 'button';
        sw.setAttribute('role', 'switch');
        sw.setAttribute('aria-checked', row.on ? 'true' : 'false');
        sw.setAttribute('aria-label', (row.on ? '停用 ' : '启用 ') + def.name);
        if (locked) {
            sw.disabled = true;
        } else {
            sw.addEventListener('click', function (event) {
                event.stopPropagation();
                row.on = !row.on;
                item.classList.toggle('is-off', !row.on);
                sw.setAttribute('aria-checked', row.on ? 'true' : 'false');
                queueSave(area);
            });
        }
        head.appendChild(sw);

        head.addEventListener('click', function () {
            state.open = open ? null : { area: area, uid: row.uid };
            renderAreas();
        });

        item.appendChild(head);

        if (open) {
            item.appendChild(itemBody(area, row, locked));
        }

        /* 拖拽：移动 / 排序（落点在该行之前） */
        item.addEventListener('dragstart', function (event) {
            if (locked) {
                event.preventDefault();
                return;
            }
            state.drag = { kind: 'move', area: area, uid: row.uid, type: row.type };
            item.classList.add('is-drag');
            try {
                event.dataTransfer.setData('text/plain', row.uid);
                event.dataTransfer.effectAllowed = 'move';
            } catch (err) {
                /* Safari 旧版忽略 */
            }
        });
        item.addEventListener('dragend', function () {
            item.classList.remove('is-drag');
            $$('.wgt-area').forEach(function (node) {
                node.classList.remove('is-drop');
            });
        });
        item.addEventListener('dragover', function (event) {
            if (!canDrop(area, state.drag) || state.drag.uid === row.uid) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            item.classList.add('is-before');
        });
        item.addEventListener('dragleave', function () {
            item.classList.remove('is-before');
        });
        item.addEventListener('drop', function (event) {
            item.classList.remove('is-before');
            if (!canDrop(area, state.drag) || state.drag.uid === row.uid) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            var list = rows(area);
            finishDrop(area, list.indexOf(row));
        });

        return item;
    }

    /* 展开的行内表单：参数 + 操作行 */
    function itemBody(area, row, locked) {
        var def = DEFS[row.type] || {};
        var body = el('div', 'wgt-item__body');

        var fields = el('div', 'wgt-fields');
        var keys = Object.keys(def.params || {});
        if (!keys.length) {
            fields.appendChild(el('p', 'wgt-field__desc', '这个模块没有参数，只受开关与顺序影响。'));
        } else {
            keys.forEach(function (key) {
                fields.appendChild(fieldEl(area, row, key, def.params[key]));
            });
            applyWhen(fields, row);
        }
        body.appendChild(fields);

        var ops = el('div', 'wgt-ops');

        function op(label, danger, handler) {
            var btn = el('button', danger ? 'is-danger' : '', esc(label));
            btn.type = 'button';
            btn.addEventListener('click', handler);
            ops.appendChild(btn);
        }

        op('复制一份', false, function () {
            if (areaFull(area)) {
                toast('这个位置最多放 ' + LIMIT + ' 个模块', 'bad');
                return;
            }
            var copy = {
                uid: uniqueUid(area, row.type, ''),
                type: row.type,
                on: row.on,
                params: clone(row.params)
            };
            var list = rows(area);
            list.splice(list.indexOf(row) + 1, 0, copy);
            state.open = { area: area, uid: copy.uid };
            render();
            queueSave(area);
            toast('已复制「' + def.name + '」', 'ok');
        });

        if (!locked) {
            op('重置参数', false, function () {
                if (window.confirm('把「' + def.name + '」的参数恢复成默认值？')) {
                    row.params = defaultsOf(row.type);
                    renderAreas();
                    queueSave(area);
                }
            });
            op('删除', true, function () {
                if (!window.confirm('从「' + AREAS[area].label + '」移除「' + def.name + '」？')) {
                    return;
                }
                var list = rows(area);
                list.splice(list.indexOf(row), 1);
                if (state.open && state.open.uid === row.uid) {
                    state.open = null;
                }
                setFold(area, false);   /* 删完别让区域凭空消失 */
                render();
                queueSave(area);
            });
        }

        ops.appendChild(el('span', 'wgt-ops__tip', '拖到其它区域 = 移动'));
        body.appendChild(ops);

        return body;
    }

    function renderAreas() {
        var host = $('[data-areas]');
        if (!host) {
            return;
        }
        host.innerHTML = '';

        areaGroups().forEach(function (group) {
            var used = 0;
            group.ids.forEach(function (id) {
                used += rows(id).length;
            });

            var sec = el('section', 'wgt-sec');
            sec.appendChild(el('div', 'wgt-grp',
                esc(group.label) + '<em>' + used + '</em>'));

            /* 一行多列：窄屏自动降到 1 列 */
            var grid = el('div', 'wgt-grid');
            grid.setAttribute('data-grid', group.key);
            group.ids.forEach(function (id) {
                grid.appendChild(areaEl(id));
            });
            sec.appendChild(grid);
            host.appendChild(sec);
        });
    }

    /* ---------------- 侧栏显示（按页面类型） ---------------- */

    var asideState = null;

    function renderAsideEditor() {
        var host = $('[data-aside-body]');
        if (!host) {
            return;
        }
        if (!asideState) {
            asideState = clone(DATA.aside || {});
        }

        host.innerHTML = '';
        var wrap = el('div', 'wgt-aside');

        Object.keys(PAGES).forEach(function (page) {
            var row = el('div', 'wgt-aside__row');
            row.appendChild(el('span', 'wgt-aside__label', esc(PAGES[page])));

            var sel = el('select', 'wgt-select wgt-aside__sel');
            [['right', '右侧栏'], ['left', '左侧栏'], ['none', '无侧栏']].forEach(function (pair) {
                var opt = document.createElement('option');
                opt.value = pair[0];
                opt.textContent = pair[1];
                if (asideState[page] === pair[0]) {
                    opt.selected = true;
                }
                sel.appendChild(opt);
            });
            sel.addEventListener('change', function () {
                asideState[page] = sel.value;
                queueSave('__aside');   /* 走统一保存队列 */
            });

            row.appendChild(sel);
            wrap.appendChild(row);
        });

        host.appendChild(wrap);
        host.appendChild(el('p', 'wgt-aside__hint',
            '「独立页面」指 page.php 渲染的普通页面；归档 / 排行 / 专题这类个性页归入「个性页」。单篇文章 / 页面还能用自定义字段 aside 再覆盖。'));
    }

    function saveAside() {
        return window.fetch(DATA.ajax + '?action=aside', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token: DATA.token, aside: asideState })
        }).then(function (res) {
            return res.json().then(function (data) {
                return { status: res.status, data: data };
            }).catch(function () {
                return { status: res.status, data: { ok: false, error: '响应不是合法 JSON' } };
            });
        }).then(function (out) {
            if (!out.data || !out.data.ok) {
                toast((out.data && out.data.error) || '保存失败', 'bad');
                return false;
            }
            state.rev = out.data.rev || state.rev;
            DATA.aside = clone(out.data.aside || asideState);
            delete state.dirtyAreas['__aside'];
            state.dirty = Object.keys(state.dirtyAreas).length > 0;
            return true;
        }).catch(function () {
            toast('网络错误，改动未保存', 'bad');
            return false;
        });
    }

    /* ---------------- 参数控件 ---------------- */

    function setParam(area, row, key, value, opts) {
        row.params[key] = value;
        if (!(opts && opts.noWhen)) {
            var fields = $('.wgt-fields');
            if (fields) {
                applyWhen(fields, row);
            }
        }
        queueSave(area);
    }

    function fieldEl(area, row, key, param) {
        var type = param.type || 'text';
        var wrap = el('div', 'wgt-field');
        if (param.when) {
            wrap.setAttribute('data-when', param.when);
        }

        if (type !== 'switch') {
            var label = el('div', 'wgt-field__label');
            label.appendChild(el('span', '', esc(param.label || key)));
            label.appendChild(el('i', '', esc(key)));
            wrap.appendChild(label);
        }

        var value = row.params[key];
        if (typeof value === 'undefined' || value === null) {
            value = param.default;
        }

        if (type === 'textarea') {
            var ta = el('textarea', 'wgt-textarea');
            ta.value = value == null ? '' : String(value);
            ta.addEventListener('input', function () {
                setParam(area, row, key, ta.value, { noWhen: true });
            });
            wrap.appendChild(ta);
        } else if (type === 'select') {
            var sel = el('select', 'wgt-select');
            Object.keys(param.options || {}).forEach(function (opt) {
                var o = document.createElement('option');
                o.value = opt;
                o.textContent = param.options[opt];
                if (String(value) === opt) {
                    o.selected = true;
                }
                sel.appendChild(o);
            });
            sel.addEventListener('change', function () {
                setParam(area, row, key, sel.value);
            });
            wrap.appendChild(sel);
        } else if (type === 'switch') {
            var toggle = el('div', 'wgt-toggle');
            var sw = el('button', 'wgt-sw');
            sw.type = 'button';
            sw.setAttribute('role', 'switch');
            sw.setAttribute('aria-checked', (Number(value) === 1 || value === true || value === '1') ? 'true' : 'false');
            var text = el('span', '', esc(param.label || key));
            sw.addEventListener('click', function () {
                var next = sw.getAttribute('aria-checked') === 'true' ? 0 : 1;
                sw.setAttribute('aria-checked', next ? 'true' : 'false');
                setParam(area, row, key, next);
            });
            toggle.appendChild(sw);
            toggle.appendChild(text);
            wrap.appendChild(toggle);
        } else if (type === 'number') {
            /* 滑条只用于窄区间：宽区间（如 0–100000）拖不动，回落数字输入（§31 第 5 轮修订） */
            if (param.min != null && param.max != null && (Number(param.max) - Number(param.min)) <= 500) {
                var range = el('div', 'wgt-range');
                var input = el('input');
                input.type = 'range';
                input.min = param.min;
                input.max = param.max;
                input.step = param.step || 1;
                input.value = Number(value) || 0;
                var out = el('output', '', String(input.value) + (param.unit || ''));
                input.addEventListener('input', function () {
                    out.textContent = input.value + (param.unit || '');
                    setParam(area, row, key, Number(input.value));
                });
                range.appendChild(input);
                range.appendChild(out);
                wrap.appendChild(range);
            } else {
                var num = el('input', 'wgt-input');
                num.type = 'number';
                if (param.min != null) {
                    num.min = param.min;
                }
                if (param.max != null) {
                    num.max = param.max;
                }
                num.value = value == null ? '' : Number(value);
                num.addEventListener('change', function () {
                    /* 留空 = 存空串，前台回落声明默认值（如 tags.limit「留空显示全部」） */
                    var v = String(num.value).trim();
                    setParam(area, row, key, v === '' ? '' : (Number(v) || 0));
                });
                wrap.appendChild(num);
            }
        } else if (type === 'term') {
            /* 单选分类：选项由服务端注入（DATA.terms），空选项 = 声明默认语义 */
            var tsel = el('select', 'wgt-select');
            var tBlank = document.createElement('option');
            tBlank.value = '';
            tBlank.textContent = '（默认）';
            /* mid 0 不存在于真实分类：0 与空串都视为「默认」 */
            tBlank.selected = String(value) === '' || String(value) === '0';
            tsel.appendChild(tBlank);
            Object.keys(DATA.terms || {}).forEach(function (mid) {
                var o = document.createElement('option');
                o.value = mid;
                o.textContent = DATA.terms[mid];
                if (String(value) === mid) {
                    o.selected = true;
                }
                tsel.appendChild(o);
            });
            tsel.addEventListener('change', function () {
                setParam(area, row, key, tsel.value);
            });
            wrap.appendChild(tsel);
        } else if (type === 'terms') {
            /* 多选分类：勾选列表，存储保持逗号分隔 mid 字符串 */
            var tbox = el('div', 'wgt-terms');
            var picked = {};
            String(value || '').split(',').forEach(function (one) {
                if (one.trim() !== '') {
                    picked[one.trim()] = true;
                }
            });
            Object.keys(DATA.terms || {}).forEach(function (mid) {
                var lab = el('label', 'wgt-terms__item');
                var cb = el('input');
                cb.type = 'checkbox';
                cb.value = mid;
                cb.checked = !!picked[mid];
                cb.addEventListener('change', function () {
                    if (cb.checked) {
                        picked[mid] = true;
                    } else {
                        delete picked[mid];
                    }
                    /* 按 DATA.terms 的展示顺序输出，保证存储稳定 */
                    var order = Object.keys(DATA.terms).filter(function (m) {
                        return picked[m];
                    });
                    setParam(area, row, key, order.join(','), { noWhen: true });
                });
                lab.appendChild(cb);
                lab.appendChild(el('span', '', esc(DATA.terms[mid])));
                tbox.appendChild(lab);
            });
            wrap.appendChild(tbox);
        } else if (type === 'image') {
            var box = el('div', 'wgt-img');
            var url = el('input', 'wgt-input');
            url.type = 'text';
            url.value = value == null ? '' : String(value);
            var pv = el('div', 'wgt-img__pv');
            pv.style.backgroundImage = url.value ? 'url("' + url.value.replace(/"/g, '%22') + '")' : '';
            var pick = el('button', 'wgt-btn', '媒体库');
            pick.type = 'button';
            pick.addEventListener('click', function () {
                openMediaPicker(function (picked) {
                    url.value = picked;
                    pv.style.backgroundImage = 'url("' + picked.replace(/"/g, '%22') + '")';
                    setParam(area, row, key, picked, { noWhen: true });
                });
            });
            var imgRow = el('div', 'wgt-img__row');
            imgRow.appendChild(url);
            imgRow.appendChild(pick);
            url.addEventListener('input', function () {
                pv.style.backgroundImage = url.value ? 'url("' + url.value.replace(/"/g, '%22') + '")' : '';
                setParam(area, row, key, url.value, { noWhen: true });
            });
            box.appendChild(imgRow);
            wrap.appendChild(box);
            wrap.appendChild(pv);
            wrap.appendChild(el('p', 'wgt-field__desc', '从媒体库选图，或直接粘贴图片地址'));
        } else if (type === 'repeater') {
            /* 结构化行编辑器（第 10 轮）：子字段由声明注入，存储 JSON 数组字符串 */
            wrap.appendChild(repeaterEditor(area, row, key, value, param));
        } else if (type === 'slides') {
            wrap.appendChild(slidesEditor(area, row, key, value));
        } else {
            var input2 = el('input', 'wgt-input');
            input2.type = 'text';
            input2.value = value == null ? '' : String(value);
            input2.addEventListener('input', function () {
                setParam(area, row, key, input2.value, { noWhen: true });
            });
            wrap.appendChild(input2);
        }

        if (param.desc) {
            wrap.appendChild(el('p', 'wgt-field__desc', esc(param.desc)));
        }

        return wrap;
    }

    /* 幻灯片：行内增删改，序列化成 JSON 字符串保存 */
    function slidesEditor(area, row, key, value) {
        var items = [];
        try {
            var parsed = JSON.parse(String(value || '[]'));
            if (Array.isArray(parsed)) {
                parsed.forEach(function (one) {
                    if (one && typeof one === 'object') {
                        items.push({
                            image: String(one.image || ''),
                            title: String(one.title || ''),
                            desc: String(one.desc || ''),
                            link: String(one.link || '')
                        });
                    }
                });
            }
        } catch (err) {
            items = [];
        }

        var box = el('div', 'wgt-slides');

        function flush() {
            var clean = items.filter(function (one) {
                return (one.title || '').trim() !== '' || (one.image || '').trim() !== '';
            });
            setParam(area, row, key, clean.length ? JSON.stringify(clean) : '', { noWhen: true });
        }

        function paint() {
            box.innerHTML = '';

            items.forEach(function (one, index) {
                var card = el('div', 'wgt-slide');
                var hd = el('div', 'wgt-slide__hd');
                hd.appendChild(el('b', '', '第 ' + (index + 1) + ' 张'));

                var up = el('button', 'wgt-iconbtn', '↑');
                up.type = 'button';
                up.addEventListener('click', function () {
                    if (index === 0) {
                        return;
                    }
                    items.splice(index - 1, 0, items.splice(index, 1)[0]);
                    paint();
                    flush();
                });

                var down = el('button', 'wgt-iconbtn', '↓');
                down.type = 'button';
                down.addEventListener('click', function () {
                    if (index === items.length - 1) {
                        return;
                    }
                    items.splice(index + 1, 0, items.splice(index, 1)[0]);
                    paint();
                    flush();
                });

                var del = el('button', 'wgt-iconbtn', '✕');
                del.type = 'button';
                del.addEventListener('click', function () {
                    items.splice(index, 1);
                    paint();
                    flush();
                });

                hd.appendChild(up);
                hd.appendChild(down);
                hd.appendChild(del);
                card.appendChild(hd);

                ['image', 'title', 'desc', 'link'].forEach(function (field) {
                    var input = el('input', 'wgt-input');
                    input.type = 'text';
                    input.value = one[field] || '';
                    input.placeholder = {
                        image: '图片地址',
                        title: '标题',
                        desc: '一句话描述',
                        link: '跳转地址'
                    }[field];
                    input.addEventListener('input', function () {
                        one[field] = input.value;
                        flush();
                    });
                    card.appendChild(input);
                });

                box.appendChild(card);
            });

            var add = el('button', 'wgt-btn', '＋ 新增一张');
            add.type = 'button';
            add.addEventListener('click', function () {
                items.push({ image: '', title: '', desc: '', link: '' });
                paint();
            });
            box.appendChild(add);
        }

        paint();
        return box;
    }

    /* repeater：结构化行编辑器（第 10 轮），子字段由声明注入，序列化成 JSON 数组字符串 */
    function repeaterEditor(area, row, key, value, param) {
        var fields = (param.fields || []).filter(function (one) {
            return one && one.key;
        });
        var maxRows = Math.max(1, parseInt(param.max, 10) || 20);
        var items = [];
        try {
            var parsed = JSON.parse(String(value || '[]'));
            if (Array.isArray(parsed)) {
                parsed.forEach(function (one) {
                    if (one && typeof one === 'object') {
                        var item = {};
                        fields.forEach(function (field) {
                            item[field.key] = String(one[field.key] || '');
                        });
                        items.push(item);
                    }
                });
            }
        } catch (err) {
            items = [];
        }

        var box = el('div', 'wgt-slides');

        function flush() {
            var clean = items.filter(function (one) {
                return fields.some(function (field) {
                    return (one[field.key] || '').trim() !== '';
                });
            });
            setParam(area, row, key, clean.length ? JSON.stringify(clean) : '', { noWhen: true });
        }

        function paint() {
            box.innerHTML = '';

            items.forEach(function (one, index) {
                var card = el('div', 'wgt-slide');
                var hd = el('div', 'wgt-slide__hd');
                hd.appendChild(el('b', '', '第 ' + (index + 1) + ' 条'));

                var up = el('button', 'wgt-iconbtn', '↑');
                up.type = 'button';
                up.addEventListener('click', function () {
                    if (index === 0) {
                        return;
                    }
                    items.splice(index - 1, 0, items.splice(index, 1)[0]);
                    paint();
                    flush();
                });

                var down = el('button', 'wgt-iconbtn', '↓');
                down.type = 'button';
                down.addEventListener('click', function () {
                    if (index === items.length - 1) {
                        return;
                    }
                    items.splice(index + 1, 0, items.splice(index, 1)[0]);
                    paint();
                    flush();
                });

                var del = el('button', 'wgt-iconbtn', '✕');
                del.type = 'button';
                del.addEventListener('click', function () {
                    items.splice(index, 1);
                    paint();
                    flush();
                });

                hd.appendChild(up);
                hd.appendChild(down);
                hd.appendChild(del);
                card.appendChild(hd);

                fields.forEach(function (field) {
                    var input = el('input', 'wgt-input');
                    input.type = 'text';
                    input.value = one[field.key] || '';
                    input.placeholder = field.placeholder || field.label || field.key;
                    input.addEventListener('input', function () {
                        one[field.key] = input.value;
                        flush();
                    });
                    card.appendChild(input);
                });

                box.appendChild(card);
            });

            var add = el('button', 'wgt-btn', '＋ 新增一条');
            add.type = 'button';
            add.disabled = items.length >= maxRows;
            add.addEventListener('click', function () {
                if (items.length >= maxRows) {
                    return;
                }
                var item = {};
                fields.forEach(function (field) {
                    item[field.key] = '';
                });
                items.push(item);
                paint();
            });
            box.appendChild(add);
        }

        paint();
        return box;
    }

    /* 媒体库选图（第 11 轮）：弹层网格，选中回填 URL */
    function post(action, body, done, fail) {
        window.fetch(DATA.ajax + '?action=' + action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ token: DATA.token }, body || {}))
        }).then(function (res) { return res.json(); }).then(function (data) {
            if (!data || !data.ok) {
                (fail || function () {})((data && data.error) || '请求失败');
                return;
            }
            done(data);
        }).catch(function () {
            (fail || function () {})('网络错误');
        });
    }

    function openMediaPicker(onPick) {
        var page = 1;
        var q = '';

        var overlay = el('div', 'wgt-media');
        var panel = el('div', 'wgt-media__panel');
        var head = el('div', 'wgt-media__hd');
        head.appendChild(el('b', '', '从媒体库选图'));

        var search = el('input', 'wgt-input wgt-media__search');
        search.type = 'text';
        search.placeholder = '搜索文件名…';

        var close = el('button', 'wgt-iconbtn', '✕');
        close.type = 'button';
        close.addEventListener('click', shut);

        head.appendChild(search);
        head.appendChild(close);
        panel.appendChild(head);

        var grid = el('div', 'wgt-media__grid');
        var empty = el('p', 'wgt-media__empty', '');
        var foot = el('div', 'wgt-media__ft');
        panel.appendChild(grid);
        panel.appendChild(empty);
        panel.appendChild(foot);
        overlay.appendChild(panel);

        function shut() {
            overlay.remove();
            document.removeEventListener('keydown', onKey);
        }

        function onKey(event) {
            if (event.key === 'Escape') {
                shut();
            }
        }

        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) {
                shut();
            }
        });
        document.addEventListener('keydown', onKey);

        function load() {
            grid.innerHTML = '';
            empty.textContent = '加载中…';
            empty.hidden = false;
            foot.innerHTML = '';
            post('media', { q: q, page: page }, function (res) {
                page = res.page;
                grid.innerHTML = '';
                if (!res.items || !res.items.length) {
                    empty.textContent = q ? '没有匹配的图片' : '媒体库还没有图片，先去后台「管理 → 文件」上传';
                    return;
                }
                empty.hidden = true;
                res.items.forEach(function (item) {
                    var cell = el('button', 'wgt-media__cell');
                    cell.type = 'button';
                    cell.title = item.title;
                    cell.style.backgroundImage = 'url("' + String(item.url).replace(/"/g, '%22') + '")';
                    cell.addEventListener('click', function () {
                        onPick(String(item.url));
                        shut();
                    });
                    grid.appendChild(cell);
                });

                if (res.pages > 1) {
                    var prev = el('button', 'wgt-btn', '‹ 上一页');
                    prev.type = 'button';
                    prev.disabled = res.page <= 1;
                    prev.addEventListener('click', function () {
                        page -= 1;
                        load();
                    });
                    var next = el('button', 'wgt-btn', '下一页 ›');
                    next.type = 'button';
                    next.disabled = res.page >= res.pages;
                    next.addEventListener('click', function () {
                        page += 1;
                        load();
                    });
                    foot.appendChild(prev);
                    foot.appendChild(el('span', 'wgt-media__num', res.page + ' / ' + res.pages + '（共 ' + res.total + ' 张）'));
                    foot.appendChild(next);
                }
            }, function (err) {
                empty.textContent = err || '加载失败';
            });
        }

        var debounce = null;
        search.addEventListener('input', function () {
            clearTimeout(debounce);
            debounce = setTimeout(function () {
                q = search.value.trim();
                page = 1;
                load();
            }, 300);
        });

        document.body.appendChild(overlay);
        load();
    }

    /* 参数条件显隐：key:empty / key:!empty / key:!value / key:a|b */    function whenOk(expr, params) {
        var parts = String(expr).split(':');
        if (parts.length < 2) {
            return true;
        }
        var key = parts[0].trim();
        var cond = parts.slice(1).join(':').trim();
        var value = params[key];
        var empty = value == null || value === '' || value === 0 || value === '0' || value === false;

        if (cond === 'empty') {
            return empty;
        }
        if (cond === '!empty') {
            return !empty;
        }
        if (cond.charAt(0) === '!') {
            return String(value == null ? '' : value) !== cond.slice(1);
        }
        return cond.split('|').some(function (one) {
            return String(value == null ? '' : value) === one.trim();
        });
    }

    function applyWhen(body, row) {
        $$('[data-when]', body).forEach(function (node) {
            node.hidden = !whenOk(node.getAttribute('data-when'), row.params);
        });
    }

    /* ---------------- 渲染汇总 ---------------- */

    function render() {
        renderLib();
        renderAreas();
    }

    /* ---------------- 事件绑定 ---------------- */

    function boot() {
        var find = $('[data-find]');
        if (find) {
            find.addEventListener('input', function () {
                state.find = find.value;
                renderLib();
            });
        }

        renderAsideEditor();

        var saveBtn = $('[data-save-btn]');
        if (saveBtn) {
            saveBtn.addEventListener('click', function () {
                saveAll();
            });
        }
        document.addEventListener('keydown', function (event) {
            if ((event.metaKey || event.ctrlKey) && (event.key === 's' || event.key === 'S')) {
                event.preventDefault();
                saveAll();
            }
        });

        $$('[data-fold-all]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                foldAll(btn.getAttribute('data-fold-all') === '1');
            });
        });

        /* 导出 / 导入（第 9 轮修订） */
        var exportBtn = $('[data-export-btn]');
        var importBtn = $('[data-import-btn]');
        var importFile = $('[data-import-file]');

        if (exportBtn) {
            exportBtn.addEventListener('click', function () {
                window.fetch(DATA.ajax + '?action=export', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ token: DATA.token })
                }).then(function (res) { return res.json(); }).then(function (data) {
                    if (!data || !data.ok) {
                        toast((data && data.error) || '导出失败', 'bad');
                        return;
                    }
                    var blob = new Blob([JSON.stringify(data.doc, null, 2)], { type: 'application/json' });
                    var a = document.createElement('a');
                    a.href = URL.createObjectURL(blob);
                    a.download = 'ecod-widgets-' + new Date().toISOString().slice(0, 10).replace(/-/g, '') + '.json';
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    URL.revokeObjectURL(a.href);
                    toast('布局已导出', 'ok');
                }).catch(function () {
                    toast('导出失败：网络错误', 'bad');
                });
            });
        }

        if (importBtn && importFile) {
            importBtn.addEventListener('click', function () {
                if (state.dirty && !window.confirm('有未保存的改动，导入会整体替换当前布局并丢弃这些改动。继续？')) {
                    return;
                }
                importFile.click();
            });
            importFile.addEventListener('change', function () {
                var file = importFile.files && importFile.files[0];
                importFile.value = '';
                if (!file) {
                    return;
                }
                file.text().then(function (text) {
                    var doc;
                    try {
                        doc = JSON.parse(text);
                    } catch (e) {
                        toast('导入失败：不是合法的 JSON 文件', 'bad');
                        return;
                    }
                    if (!doc || !doc.areas) {
                        toast('导入失败：文件里没有模块布局数据', 'bad');
                        return;
                    }
                    if (!window.confirm('导入将整体替换当前布局（当前布局会先自动备份）。确定继续？')) {
                        return;
                    }
                    return window.fetch(DATA.ajax + '?action=import', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ token: DATA.token, doc: doc })
                    }).then(function (res) { return res.json(); }).then(function (data) {
                        if (!data || !data.ok) {
                            toast((data && data.error) || '导入失败', 'bad');
                            return;
                        }
                        var msg = '已导入 ' + data.imported + ' 个模块';
                        if (data.skipped > 0) {
                            msg += '，跳过 ' + data.skipped + ' 个未知模块';
                        }
                        toast(msg + '，页面即将刷新', 'ok');
                        window.setTimeout(function () { window.location.reload(); }, 800);
                    });
                }).catch(function () {
                    toast('导入失败：文件读取错误', 'bad');
                });
            });
        }

        window.addEventListener('beforeunload', function (event) {
            if (state.dirty) {
                event.preventDefault();
                event.returnValue = '';
            }
        });

        render();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
