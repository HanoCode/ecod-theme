/**
 * Ecod Theme · 后台「导航」菜单构建器
 *
 * 参考 WordPress 外观 → 菜单：左列挑项加入，右列拖动排序与调层级，行尾「⌄」展开项的信息。
 * 站点可保存多套导航，顶部切换条选中的那套 = 前台生效的导航 = 当前编辑对象。
 * 整份数据（多套 + active）序列化进隐藏字段 ENavSets（JSON），由 core/site/nav.php 读取。
 *
 * 设计约束：
 *  - 只有两级：二级项不能再挂子项，因此「已有子项的一级项」拖动时锁死为一级
 *  - 拖动判定：上下决定顺序，水平位移 ≥ 28px 决定缩进；落点用指示线预览层级
 *  - 数据操作先摊平成 [{item, level}] 序列，摘出被拖区段后按落点插回，再按 level 重建树
 *  - 切换导航不清空任何数据，各套改动随一次「保存设置」提交
 *  - 任何改动都写回隐藏字段并派发 input 事件，复用设置页原有的「未保存」提示
 *
 * Copyright (c) 2026 Ecod. All Rights Reserved. · https://ecod.cc
 */
(function () {
    'use strict';

    var DATA = window.ECOD_NAV;
    if (!DATA) {
        return;
    }

    var root = document.querySelector('[data-navb]');
    if (!root) {
        return;
    }

    var field = document.querySelector('input[name="ENavSets"]');
    var barEl = document.querySelector('[data-navb-bar]');
    var setFormEl = document.querySelector('[data-navb-setform]');
    var selEl = barEl ? barEl.querySelector('[data-navb-select]') : null;
    var setNameEl = root.querySelector('[data-navb-set]');
    var listEl = root.querySelector('[data-navb-list]');
    var emptyEl = root.querySelector('[data-navb-empty]');
    var countEl = root.querySelector('[data-navb-count]');
    var tabsEl = root.querySelector('[data-navb-tabs]');

    var KIND = { home: '首页', page: '页面', cat: '分类', tag: '标签', link: '链接' };
    var SET_MAX = 10;
    var sources = DATA.sources || { pages: [], cats: [], tags: [] };

    /* type:ref → {label, url}，用于显示源标题与只读地址 */
    var urlMap = {};
    ['pages', 'cats', 'tags'].forEach(function (key) {
        (sources[key] || []).forEach(function (row) {
            var type = key === 'pages' ? 'page' : (key === 'cats' ? 'cat' : 'tag');
            urlMap[type + ':' + row.ref] = { label: row.label, url: row.url };
        });
    });

    function esc(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /* 运行时 key：只用于 DOM 定位与展开状态，不写回数据 */
    var seq = 0;
    function newKey() {
        seq++;
        return 'n' + seq.toString(36) + Math.random().toString(36).slice(2, 5);
    }

    function norm(row) {
        var type = row && row.type ? String(row.type) : 'link';
        if (!KIND[type]) {
            type = 'link';
        }
        var item = {
            _k: row && row._k ? String(row._k) : newKey(),
            type: type,
            ref: row && row.ref != null ? String(row.ref) : '',
            label: row && row.label != null ? String(row.label) : '',
            url: row && row.url != null ? String(row.url) : '',
            blank: !!(row && row.blank),
            attr: row && row.attr != null ? String(row.attr) : '',
            cls: row && row.cls != null ? String(row.cls) : '',
            icon: row && row.icon != null ? String(row.icon) : '',
            badge: row && row.badge != null ? String(row.badge) : '',
            children: []
        };
        if (row && row.children && row.children.length) {
            item.children = row.children.map(norm);
            /* 只保留一层：第二层起丢弃 */
            item.children.forEach(function (kid) {
                kid.children = [];
            });
        }
        return item;
    }

    /* 序列化时剥掉运行时 key */
    function cleanItem(item) {
        return {
            type: item.type,
            ref: item.ref,
            label: item.label,
            url: item.url || '',
            blank: !!item.blank,
            attr: item.attr || '',
            cls: item.cls || '',
            icon: item.icon || '',
            badge: item.badge || '',
            children: (item.children || []).map(cleanItem)
        };
    }

    function newId() {
        return 's' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
    }

    function normSet(row) {
        return {
            id: row && row.id ? String(row.id) : newId(),
            name: row && row.name ? String(row.name).slice(0, 24) : '未命名导航',
            note: row && row.note ? String(row.note).slice(0, 60) : '',
            items: (row && row.items ? row.items : []).map(norm)
        };
    }

    function findSet(id) {
        for (var i = 0; i < state.sets.length; i++) {
            if (state.sets[i].id === id) {
                return state.sets[i];
            }
        }
        return null;
    }

    var state = {
        active: DATA.active ? String(DATA.active) : '',
        sets: (DATA.sets || []).map(normSet)
    };

    /* 从未配置过：先给一套空的，保存后前台按「菜单为空 → 自动导航」处理 */
    if (!state.sets.length) {
        state.sets.push({ id: newId(), name: '主导航', note: '', items: [] });
        state.active = state.sets[0].id;
    }
    if (!findSet(state.active)) {
        state.active = state.sets[0].id;
    }

    /* 当前编辑的项数组（与当前套的 items 是同一个引用） */
    var items = findSet(state.active).items;

    /* 展开编辑面板的项 key（空串 = 全部收起） */
    var openKey = '';
    var marker = null;

    function rowEl(key) {
        return listEl.querySelector('.ecod-navb__row[data-k="' + key + '"]');
    }

    function findItem(key) {
        for (var i = 0; i < items.length; i++) {
            if (items[i]._k === key) {
                return { item: items[i], parent: null, index: i };
            }
            var kids = items[i].children || [];
            for (var j = 0; j < kids.length; j++) {
                if (kids[j]._k === key) {
                    return { item: kids[j], parent: items[i], index: j };
                }
            }
        }
        return null;
    }

    /* ------------------------------------------------------------------
     * 序列化
     * ---------------------------------------------------------------- */
    function payload() {
        return {
            active: state.active,
            sets: state.sets.map(function (s) {
                return {
                    id: s.id,
                    name: s.name,
                    note: s.note || '',
                    items: s.items.map(cleanItem)
                };
            })
        };
    }

    function sync() {
        var cur = findSet(state.active);
        if (cur) {
            cur.items = items;
        }
        if (setNameEl) {
            setNameEl.textContent = cur ? cur.name : '未命名导航';
            setNameEl.title = cur && cur.note ? cur.note : '';
        }
        if (selEl) {
            Array.prototype.forEach.call(selEl.options, function (opt) {
                var hit = findSet(opt.value);
                if (hit) {
                    opt.textContent = hit.name;
                }
            });
            selEl.value = state.active;
        }
        if (!field) {
            return;
        }
        field.value = state.sets.length ? JSON.stringify(payload()) : '';
        try {
            field.dispatchEvent(new Event('input', { bubbles: true }));
        } catch (e) {
            /* 老浏览器忽略 */
        }
    }

    /* ------------------------------------------------------------------
     * 顶部：多套导航切换
     * ---------------------------------------------------------------- */
    function renderBar() {
        if (!selEl) {
            return;
        }
        selEl.innerHTML = state.sets.map(function (s) {
            return '<option value="' + esc(s.id) + '">' + esc(s.name) + '</option>';
        }).join('');
        selEl.value = state.active;
        var cur = findSet(state.active);
        if (setNameEl) {
            setNameEl.textContent = cur ? cur.name : '未命名导航';
            setNameEl.title = cur && cur.note ? cur.note : '';
        }
    }

    function switchTo(id) {
        if (id === state.active || !findSet(id)) {
            return;
        }
        var cur = findSet(state.active);
        if (cur) {
            cur.items = items;
        }
        state.active = id;
        items = findSet(id).items;
        openKey = '';
        toggleSetForm(false);
        render();
        renderAllSources();
        renderBar();
        sync();
    }

    function addSet(name, fromItems) {
        if (state.sets.length >= SET_MAX) {
            navbModal({
                title: '无法新建',
                message: '最多保存 ' + SET_MAX + ' 套导航，先删掉不用的再新建。',
                ok: '知道了'
            });
            return;
        }
        var cur = findSet(state.active);
        if (cur) {
            cur.items = items;
        }
        var set = {
            id: newId(),
            name: String(name || '').trim().slice(0, 24) || '未命名导航',
            note: '',
            items: fromItems || []
        };
        state.sets.push(set);
        state.active = set.id;
        items = set.items;
        openKey = '';
        render();
        renderAllSources();
        renderBar();
        sync();
    }

    /* 轻量弹窗：替代 window.prompt / window.alert，复用后台 --ea-* 令牌 */
    function navbModal(opts) {
        closeNavbModal();
        var wrap = document.createElement('div');
        wrap.className = 'ecod-navb-modal';
        var card = document.createElement('div');
        card.className = 'ecod-navb-modal__card';
        card.setAttribute('role', 'dialog');
        card.setAttribute('aria-modal', 'true');

        var hd = document.createElement('p');
        hd.className = 'ecod-navb-modal__hd';
        hd.textContent = opts.title || '';
        card.appendChild(hd);

        var input = null;
        if (opts.label) {
            var lab = document.createElement('label');
            lab.className = 'ecod-navb-modal__field';
            var sp = document.createElement('span');
            sp.textContent = opts.label;
            input = document.createElement('input');
            input.type = 'text';
            input.maxLength = 24;
            input.value = opts.value || '';
            lab.appendChild(sp);
            lab.appendChild(input);
            card.appendChild(lab);
        } else if (opts.message) {
            var msg = document.createElement('p');
            msg.className = 'ecod-navb-modal__msg';
            msg.textContent = opts.message;
            card.appendChild(msg);
        }

        var ft = document.createElement('div');
        ft.className = 'ecod-navb-modal__ft';
        var cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'ecod-admin__ghost';
        cancel.textContent = '取消';
        var ok = document.createElement('button');
        ok.type = 'button';
        ok.className = 'ecod-btn ecod-btn--sm';
        ok.textContent = opts.ok || '确定';
        ft.appendChild(cancel);
        ft.appendChild(ok);
        card.appendChild(ft);
        wrap.appendChild(card);
        document.body.appendChild(wrap);

        function onKey(e) {
            if (e.key === 'Escape') {
                e.stopPropagation();
                closeNavbModal();
            } else if (e.key === 'Enter' && input && e.target === input) {
                e.preventDefault();
                var val = input.value;
                closeNavbModal();
                if (opts.onOk) {
                    opts.onOk(val);
                }
            }
        }
        ok.addEventListener('click', function () {
            var val = input ? input.value : '';
            closeNavbModal();
            if (opts.onOk) {
                opts.onOk(val);
            }
        });
        cancel.addEventListener('click', closeNavbModal);
        wrap.addEventListener('mousedown', function (e) {
            if (e.target === wrap) {
                closeNavbModal();
            }
        });
        document.addEventListener('keydown', onKey, true);
        if (input) {
            input.focus();
            input.select();
        } else {
            ok.focus();
        }
    }

    function closeNavbModal() {
        var old = document.querySelector('.ecod-navb-modal');
        if (old) {
            old.remove();
        }
    }

    /* 套信息表单：名称 + 备注 */
    function toggleSetForm(open) {
        if (!setFormEl) {
            return;
        }
        if (!open) {
            setFormEl.hidden = true;
            return;
        }
        var cur = findSet(state.active);
        if (!cur) {
            return;
        }
        var nameInput = setFormEl.querySelector('[data-set-name]');
        var noteInput = setFormEl.querySelector('[data-set-note]');
        if (nameInput) {
            nameInput.value = cur.name;
        }
        if (noteInput) {
            noteInput.value = cur.note || '';
        }
        setFormEl.hidden = false;
        if (nameInput) {
            nameInput.focus();
            nameInput.select();
        }
    }

    if (barEl) {
        barEl.addEventListener('change', function (e) {
            if (e.target.matches('[data-navb-select]')) {
                switchTo(e.target.value);
            }
        });

        barEl.addEventListener('click', function (e) {
            var cur = findSet(state.active);

            if (e.target.closest('[data-navb-new]')) {
                if (state.sets.length >= SET_MAX) {
                    navbModal({
                        title: '无法新建',
                        message: '最多保存 ' + SET_MAX + ' 套导航，先删掉不用的再新建。',
                        ok: '知道了'
                    });
                    return;
                }
                navbModal({
                    title: '新建导航',
                    label: '名称',
                    value: '导航 ' + (state.sets.length + 1),
                    ok: '创建',
                    onOk: function (name) {
                        addSet(name, []);
                    }
                });
                return;
            }

            if (e.target.closest('[data-navb-dup]')) {
                if (!cur) {
                    return;
                }
                addSet(cur.name + ' 副本', JSON.parse(JSON.stringify(items.map(cleanItem))));
                return;
            }

            if (e.target.closest('[data-navb-setedit]')) {
                toggleSetForm(setFormEl ? setFormEl.hidden : true);
                return;
            }

            if (e.target.closest('[data-navb-del]')) {
                if (!cur) {
                    return;
                }
                if (state.sets.length <= 1) {
                    navbModal({
                        title: '无法删除',
                        message: '至少保留一套导航。想清空内容，用右侧的「清空」。',
                        ok: '知道了'
                    });
                    return;
                }
                if (!window.confirm('删除导航「' + cur.name + '」？这套的全部菜单项会一起删除，保存后不可恢复。')) {
                    return;
                }
                state.sets = state.sets.filter(function (s) {
                    return s.id !== cur.id;
                });
                state.active = state.sets[0].id;
                items = findSet(state.active).items;
                openKey = '';
                toggleSetForm(false);
                render();
                renderAllSources();
                renderBar();
                sync();
            }
        });
    }

    if (setFormEl) {
        setFormEl.addEventListener('click', function (e) {
            if (e.target.closest('[data-set-cancel]')) {
                toggleSetForm(false);
                return;
            }
            if (e.target.closest('[data-set-save]')) {
                var cur = findSet(state.active);
                if (!cur) {
                    return;
                }
                var nameInput = setFormEl.querySelector('[data-set-name]');
                var noteInput = setFormEl.querySelector('[data-set-note]');
                var name = nameInput ? nameInput.value.trim().slice(0, 24) : '';
                cur.name = name || cur.name;
                cur.note = noteInput ? noteInput.value.trim().slice(0, 60) : '';
                toggleSetForm(false);
                renderBar();
                sync();
            }
        });

        setFormEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.target.matches('input')) {
                e.preventDefault();
                var save = setFormEl.querySelector('[data-set-save]');
                if (save) {
                    save.click();
                }
            }
            if (e.key === 'Escape') {
                toggleSetForm(false);
            }
        });
    }

    /* ------------------------------------------------------------------
     * 树 ⇄ 扁平序列（拖动的基础）
     * ---------------------------------------------------------------- */
    function flatten(list) {
        var out = [];
        list.forEach(function (item) {
            out.push({ item: item, level: 0 });
            (item.children || []).forEach(function (kid) {
                out.push({ item: kid, level: 1 });
            });
        });
        return out;
    }

    /* level 为 1 的节点挂到它前面最近的一级项下；没有父项时自动升为一级 */
    function rebuild(flat) {
        var out = [];
        var parent = null;
        flat.forEach(function (node) {
            node.item.children = [];
        });
        flat.forEach(function (node) {
            if (node.level === 1 && parent) {
                parent.children.push(node.item);
            } else {
                out.push(node.item);
                parent = node.item;
            }
        });
        return out;
    }

    /* ------------------------------------------------------------------
     * 右列：菜单结构
     * ---------------------------------------------------------------- */
    function itemUri(item) {
        if (item.url) {
            return item.url;
        }
        var hit = urlMap[item.type + ':' + item.ref];
        return hit ? hit.url : '';
    }

    function displayName(item) {
        if (item.label) {
            return item.label;
        }
        var hit = urlMap[item.type + ':' + item.ref];
        if (hit) {
            return hit.label;
        }
        return item.type === 'link' ? item.url : (KIND[item.type] || '菜单项');
    }

    function fieldHtml(label, key, value, placeholder, wide) {
        return '<label class="ecod-navb__field' + (wide ? ' is-wide' : '') + '">' +
            '<span>' + label + '</span>' +
            '<input type="text" data-e="' + key + '" value="' + esc(value) + '"' +
            ' placeholder="' + esc(placeholder) + '" spellcheck="false" autocomplete="off">' +
            '</label>';
    }

    /* 图标下拉：DATA.icons 来自后端 sprite 键名清单 */
    var ICONS = Array.isArray(DATA.icons) ? DATA.icons : [];

    /* 菜单项编辑面板（等价于 WP 的菜单项折叠面板） */
    function panelHtml(item, isSub) {
        var src = urlMap[item.type + ':' + item.ref] || null;
        var srcUrl = src ? src.url : '';
        var srcName = src ? src.label : (item.type === 'link' ? '手动填写' : (KIND[item.type] || ''));
        var ph = item.type === 'link'
            ? '必填，例如 https://github.com/you'
            : (srcUrl || '留空跟随源地址');

        var iconOpts = '<option value="">不使用</option>' + ICONS.map(function (name) {
            return '<option value="' + esc(name) + '"' + (item.icon === name ? ' selected' : '') + '>' + esc(name) + '</option>';
        }).join('');

        return '<div class="ecod-navb__edit' + (isSub ? ' is-sub' : '') + '" data-edit="' + esc(item._k) + '">' +
            '<div class="ecod-navb__grid">' +
            fieldHtml('名称', 'label', item.label, src ? '默认：' + srcName : '留空用源标题') +
            fieldHtml('标题属性', 'attr', item.attr, '鼠标悬停提示，可留空') +
            fieldHtml('链接地址', 'url', item.url, ph, true) +
            fieldHtml('CSS 类', 'cls', item.cls, '可选，空格分隔，最多 5 个') +
            '<label class="ecod-navb__field">' +
            '<span>前置图标</span>' +
            '<select data-e="icon">' + iconOpts + '</select>' +
            '</label>' +
            fieldHtml('角标', 'badge', item.badge, '如「热」「NEW」，最长 6 字') +
            '<label class="ecod-navb__check"><input type="checkbox" data-e="blank"' +
            (item.blank ? ' checked' : '') + '> 新窗口打开</label>' +
            '</div>' +
            '<div class="ecod-navb__editft">' +
            '<span>来源：' + esc(srcName) + (srcUrl ? ' · ' + esc(srcUrl) : '') + '</span>' +
            '<button type="button" class="ecod-navb__mini" data-act="reset">清空自定义</button>' +
            '</div>' +
            '</div>';
    }

    function rowHtml(item, isSub, canIndent) {
        var kind = KIND[item.type] || '链接';
        var uri = itemUri(item);
        var open = openKey === item._k;
        var im = '';

        if (isSub) {
            im = '<button type="button" data-act="outdent" title="升为一级">←</button>';
        } else {
            im = '<button type="button" data-act="indent" title="缩进为上一个一级项的二级"' +
                (canIndent ? '' : ' disabled') + '>→</button>';
        }

        return '<div class="ecod-navb__row' + (isSub ? ' is-sub' : '') + (open ? ' is-open' : '') +
            '" data-k="' + esc(item._k) + '">' +
            '<i class="ecod-navb__grip" data-grip title="按住拖动：上下改顺序，向右多拖一点变成二级" aria-hidden="true"></i>' +
            '<span class="ecod-navb__name" data-act="open" title="点击编辑这一项">' + esc(displayName(item)) + '</span>' +
            '<span class="ecod-navb__kind ecod-navb__kind--' + item.type + '">' + kind + '</span>' +
            (uri ? '<span class="ecod-navb__uri" title="' + esc(uri) + '">' + esc(uri) + '</span>' : '') +
            '<span class="ecod-navb__act">' +
            '<button type="button" data-act="blank" class="' + (item.blank ? 'is-on' : '') + '"' +
            ' title="' + (item.blank ? '取消新窗口打开' : '新窗口打开') + '">↗</button>' +
            im +
            '<button type="button" data-act="toggle" class="' + (open ? 'is-on' : '') + '"' +
            ' title="' + (open ? '收起' : '编辑这一项') + '">' + (open ? '⌃' : '⌄') + '</button>' +
            '<button type="button" data-act="del" class="is-danger" title="删除">×</button>' +
            '</span>' +
            '</div>' + (open ? panelHtml(item, isSub) : '');
    }

    function ensureMarker() {
        marker = listEl.querySelector('[data-navb-marker]');
        if (!marker) {
            marker = document.createElement('i');
            marker.className = 'ecod-navb__marker';
            marker.setAttribute('data-navb-marker', '');
            marker.setAttribute('aria-hidden', 'true');
            listEl.appendChild(marker);
        }
        marker.hidden = true;
        return marker;
    }

    function render() {
        var html = '';
        var total = 0;

        items.forEach(function (item, i) {
            total++;
            var canIndent = i > 0 && !(item.children || []).length;
            html += rowHtml(item, false, canIndent);
            (item.children || []).forEach(function (kid) {
                total++;
                html += rowHtml(kid, true, false);
            });
        });

        listEl.innerHTML = html;
        ensureMarker();

        if (countEl) {
            countEl.textContent = total + ' 项';
        }
        if (emptyEl) {
            emptyEl.hidden = total > 0;
        }
    }

    /* 行内名称实时刷新（不重绘，避免丢焦点） */
    function refreshName(item) {
        var row = rowEl(item._k);
        if (!row) {
            return;
        }
        var name = row.querySelector('.ecod-navb__name');
        if (name) {
            name.textContent = displayName(item);
        }
    }

    /* ------------------------------------------------------------------
     * 行内交互：按钮 / 面板输入
     * ---------------------------------------------------------------- */
    listEl.addEventListener('click', function (e) {
        /* 点名称即展开面板 */
        var open = e.target.closest('[data-act="open"]');
        if (open) {
            var openRow = open.closest('.ecod-navb__row');
            var openHit = openRow ? findItem(openRow.getAttribute('data-k')) : null;
            if (openHit) {
                openKey = openKey === openHit.item._k ? '' : openHit.item._k;
                render();
                var panelInput = listEl.querySelector('[data-edit="' + openKey + '"] [data-e="label"]');
                if (panelInput) {
                    panelInput.focus();
                    panelInput.select();
                }
            }
            return;
        }

        var btn = e.target.closest('button[data-act]');
        if (!btn) {
            return;
        }
        var row = btn.closest('.ecod-navb__row');
        /* 面板里的按钮不在行内，靠 data-edit 定位 */
        var hit = row ? findItem(row.getAttribute('data-k')) : editTarget(btn);
        if (!hit) {
            return;
        }
        var act = btn.getAttribute('data-act');

        if (act === 'del') {
            if (hit.parent) {
                hit.parent.children.splice(hit.index, 1);
            } else {
                items.splice(hit.index, 1);
            }
            if (openKey === hit.item._k) {
                openKey = '';
            }
        } else if (act === 'blank') {
            hit.item.blank = !hit.item.blank;
        } else if (act === 'toggle') {
            openKey = openKey === hit.item._k ? '' : hit.item._k;
        } else if (act === 'reset') {
            hit.item.label = '';
            hit.item.attr = '';
            hit.item.cls = '';
            hit.item.icon = '';
            hit.item.badge = '';
            /* 自定义链接没有源可跟随，地址必须留着，否则保存后这一项会被丢弃 */
            if (hit.item.type !== 'link') {
                hit.item.url = '';
            }
            render();
            sync();
            return;
        } else if (act === 'indent') {
            /* 变成上一个一级项的二级；带子项的项不允许（会变三层） */
            if (hit.parent || hit.index === 0 || (hit.item.children || []).length) {
                return;
            }
            var prev = items[hit.index - 1];
            if (!prev) {
                return;
            }
            items.splice(hit.index, 1);
            prev.children = prev.children || [];
            prev.children.push(hit.item);
        } else if (act === 'outdent') {
            if (!hit.parent) {
                return;
            }
            var owner = hit.parent;
            owner.children.splice(hit.index, 1);
            var at = items.indexOf(owner);
            items.splice(at + 1, 0, hit.item);
        }

        render();
        renderAllSources();
        sync();
    });

    function editTarget(node) {
        var panel = node.closest ? node.closest('[data-edit]') : null;
        if (!panel) {
            return null;
        }
        return findItem(panel.getAttribute('data-edit'));
    }

    listEl.addEventListener('input', function (e) {
        var input = e.target;
        var key = input.getAttribute && input.getAttribute('data-e');
        if (!key || key === 'blank' || !input.matches('input[type="text"]')) {
            return;
        }
        var hit = editTarget(input);
        if (!hit) {
            return;
        }
        hit.item[key] = input.value;
        if (key === 'label') {
            refreshName(hit.item);
        }
        sync();
    });

    listEl.addEventListener('change', function (e) {
        var input = e.target;
        if (input.matches && input.matches('select[data-e="icon"]')) {
            var selHit = editTarget(input);
            if (selHit) {
                selHit.item.icon = input.value;
                sync();
            }
            return;
        }
        if (!input.matches || !input.matches('input[data-e="blank"]')) {
            return;
        }
        var hit = editTarget(input);
        if (!hit) {
            return;
        }
        hit.item.blank = !!input.checked;
        /* 行内按钮态需要同步，重绘这一行的图标开销可接受 */
        render();
        sync();
    });

    /* ------------------------------------------------------------------
     * 拖动：上下改顺序，水平位移改层级
     * ---------------------------------------------------------------- */
    var THRESHOLD = 28;
    var drag = null;

    function startDrag(row, ev) {
        var flat = flatten(items);
        var key = row.getAttribute('data-k');
        var from = -1;
        var i;
        for (i = 0; i < flat.length; i++) {
            if (flat[i].item._k === key) {
                from = i;
                break;
            }
        }
        if (from < 0) {
            return;
        }

        var to = from;
        /* 只有一级项会带走紧随其后的二级项；二级项拖动时只动自己（后面的是它的兄弟） */
        if (flat[from].level === 0) {
            while (to + 1 < flat.length && flat[to + 1].level === 1) {
                to++;
            }
        }

        drag = {
            flat: flat,
            from: from,
            to: to,
            hasKids: to > from,
            startX: ev.clientX,
            startY: ev.clientY,
            x: ev.clientX,
            y: ev.clientY,
            drop: null,
            moved: false
        };

        for (i = from; i <= to; i++) {
            var el = rowEl(flat[i].item._k);
            if (el) {
                el.classList.add('is-dragging');
            }
        }
        document.body.classList.add('is-navb-dragging');
        ensureMarker();

        window.addEventListener('pointermove', onMove, { passive: false });
        window.addEventListener('pointerup', onUp);
        window.addEventListener('pointercancel', onUp);
    }

    function onMove(e) {
        if (!drag) {
            return;
        }
        e.preventDefault();
        if (!drag.moved
            && (Math.abs(e.clientX - drag.startX) > 2 || Math.abs(e.clientY - drag.startY) > 2)) {
            drag.moved = true;
        }
        drag.x = e.clientX;
        drag.y = e.clientY;
        computeDrop();
    }

    function computeDrop() {
        var listRect = listEl.getBoundingClientRect();
        var rows = Array.prototype.slice.call(listEl.querySelectorAll('.ecod-navb__row'));
        var ghost = {};
        var i;

        for (i = drag.from; i <= drag.to; i++) {
            ghost[drag.flat[i].item._k] = true;
        }

        var anchorKey = null;
        var after = false;
        var edge = null;
        var lastKey = null;
        var lastEdge = null;

        for (i = 0; i < rows.length; i++) {
            var key = rows[i].getAttribute('data-k');
            if (ghost[key]) {
                continue;
            }
            var rect = rows[i].getBoundingClientRect();
            if (drag.y < rect.top + rect.height / 2) {
                anchorKey = key;
                after = false;
                edge = rect.top - listRect.top;
                break;
            }
            lastKey = key;
            lastEdge = rect.bottom - listRect.top;
        }

        if (!anchorKey && lastKey) {
            anchorKey = lastKey;
            after = true;
            edge = lastEdge;
        }

        /* 水平位移决定层级；带子项的一级项锁死为一级 */
        var dx = drag.x - drag.startX;
        var level = (!drag.hasKids && dx >= THRESHOLD) ? 1 : 0;

        /* 摘出被拖区段后的序列，按锚点算插入位 */
        var rest = drag.flat.slice(0, drag.from).concat(drag.flat.slice(drag.to + 1));
        var at = rest.length;
        if (anchorKey) {
            for (i = 0; i < rest.length; i++) {
                if (rest[i].item._k === anchorKey) {
                    at = after ? i + 1 : i;
                    break;
                }
            }
        }
        /* 落在最前面没有父项可挂：退回一级 */
        if (level === 1 && at === 0) {
            level = 0;
        }

        drag.drop = { at: at, level: level, rest: rest };
        paintMarker(edge == null ? (drag.y - listRect.top) : edge, level);
    }

    function paintMarker(top, level) {
        if (!marker) {
            return;
        }
        marker.hidden = false;
        marker.style.top = Math.max(0, Math.round(top)) + 'px';
        marker.style.left = (level ? 34 : 8) + 'px';
        marker.classList.toggle('is-sub', level === 1);
    }

    function endDrag() {
        if (!drag) {
            return;
        }
        var d = drag;
        drag = null;

        window.removeEventListener('pointermove', onMove);
        window.removeEventListener('pointerup', onUp);
        window.removeEventListener('pointercancel', onUp);
        document.body.classList.remove('is-navb-dragging');
        if (marker) {
            marker.hidden = true;
        }

        if (!d.moved || !d.drop) {
            /* 只是点了一下手柄：清掉拖动态 */
            render();
            return;
        }

        var seg = d.flat.slice(d.from, d.to + 1).map(function (node, i) {
            return { item: node.item, level: i === 0 ? d.drop.level : 1 };
        });
        var rest = d.drop.rest.slice();
        Array.prototype.splice.apply(rest, [d.drop.at, 0].concat(seg));

        items = rebuild(rest);
        openKey = '';
        render();
        renderAllSources();
        sync();
    }

    function onUp() {
        endDrag();
    }

    listEl.addEventListener('pointerdown', function (e) {
        var grip = e.target.closest('[data-grip]');
        if (!grip || (e.pointerType === 'mouse' && e.button !== 0)) {
            return;
        }
        var row = grip.closest('.ecod-navb__row');
        if (!row) {
            return;
        }
        e.preventDefault();
        startDrag(row, e);
    });

    /* ------------------------------------------------------------------
     * 左列：挑项
     * ---------------------------------------------------------------- */
    function isAdded(type, ref) {
        return items.some(function (item) {
            return item.type === type && item.ref === ref;
        });
    }

    function renderSources(type) {
        var host = root.querySelector('[data-navb-src="' + type + '"]');
        if (!host) {
            return;
        }
        var rows = sources[type === 'page' ? 'pages' : (type === 'cat' ? 'cats' : 'tags')] || [];
        var filter = root.querySelector('[data-navb-pane="' + type + '"] [data-navb-filter]');
        var keyword = filter ? filter.value.trim().toLowerCase() : '';

        var out = '';
        var shown = 0;
        rows.forEach(function (row) {
            if (keyword && String(row.label).toLowerCase().indexOf(keyword) === -1
                && String(row.ref).toLowerCase().indexOf(keyword) === -1) {
                return;
            }
            shown++;
            var added = isAdded(type, row.ref);
            out += '<button type="button" class="ecod-navb__src-item' + (added ? ' is-added' : '') + '"' +
                ' data-add="' + type + '" data-ref="' + esc(row.ref) + '"' +
                ' data-label="' + esc(row.label) + '"' +
                (added ? ' disabled' : '') + '>' +
                '<span>' + esc(row.label) + '</span><i>' + (added ? '✓' : '+') + '</i></button>';
        });

        host.innerHTML = shown ? out : '<p class="ecod-navb__src-empty">' +
            (rows.length ? '没有匹配的条目' : '这里还没有可选内容') + '</p>';
    }

    function renderAllSources() {
        renderSources('page');
        renderSources('cat');
        renderSources('tag');
    }

    root.addEventListener('click', function (e) {
        var tab = e.target.closest('[data-navb-tab]');
        if (tab) {
            var name = tab.getAttribute('data-navb-tab');
            Array.prototype.forEach.call(tabsEl.querySelectorAll('[data-navb-tab]'), function (btn) {
                btn.classList.toggle('is-on', btn === tab);
            });
            Array.prototype.forEach.call(root.querySelectorAll('[data-navb-pane]'), function (pane) {
                pane.hidden = pane.getAttribute('data-navb-pane') !== name;
            });
            return;
        }

        var add = e.target.closest('[data-add]');
        if (add && !add.disabled) {
            items.push({
                _k: newKey(),
                type: add.getAttribute('data-add'),
                ref: add.getAttribute('data-ref'),
                label: add.getAttribute('data-label') || '',
                url: '',
                blank: false,
                attr: '',
                cls: '',
                children: []
            });
            render();
            renderAllSources();
            sync();
            return;
        }

        if (e.target.closest('[data-navb-link-add]')) {
            var labelInput = root.querySelector('[data-navb-link-label]');
            var urlInput = root.querySelector('[data-navb-link-url]');
            var blankInput = root.querySelector('[data-navb-link-blank]');
            var label = labelInput ? labelInput.value.trim() : '';
            var url = urlInput ? urlInput.value.trim() : '';
            if (!url) {
                if (urlInput) {
                    urlInput.focus();
                }
                return;
            }
            /* 只填了地址时用地址当名称 */
            items.push({
                _k: newKey(),
                type: 'link',
                ref: '',
                label: label || url,
                url: url,
                blank: !!(blankInput && blankInput.checked),
                attr: '',
                cls: '',
                children: []
            });
            if (labelInput) {
                labelInput.value = '';
            }
            if (urlInput) {
                urlInput.value = '';
            }
            if (blankInput) {
                blankInput.checked = false;
            }
            render();
            sync();
            return;
        }

        if (e.target.closest('[data-navb-clear]')) {
            if (!items.length) {
                return;
            }
            var owner = findSet(state.active);
            if (!window.confirm('清空导航「' + (owner ? owner.name : '当前导航') + '」的全部菜单项？保存后前台会回到「全部独立页面 + 自定义链接」的自动导航。')) {
                return;
            }
            items = [];
            openKey = '';
            render();
            renderAllSources();
            sync();
        }
    });

    root.addEventListener('input', function (e) {
        if (e.target.matches('[data-navb-filter]')) {
            var pane = e.target.closest('[data-navb-pane]');
            if (pane) {
                renderSources(pane.getAttribute('data-navb-pane'));
            }
        }
    });

    root.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.matches('[data-navb-link-url], [data-navb-link-label]')) {
            e.preventDefault();
            var addBtn = root.querySelector('[data-navb-link-add]');
            if (addBtn) {
                addBtn.click();
            }
        }
    });

    render();
    renderAllSources();
    renderBar();
    sync();
}());
