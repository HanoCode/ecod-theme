/**
 * Ecod Theme · 后台设置页交互
 *
 * 职责：
 *  1. 把 Typecho 原生字段按 data-ecod-pane 搬到对应面板（保存逻辑完全不动）
 *  2. 模块卡片：启用开关、拖拽排序、齿轮抽屉改参数
 *  3. 状态序列化到隐藏字段 EBlocks
 *  4. 配置导出 / 导入 / 分区恢复默认
 */
(function () {
    'use strict';

    var DATA = window.ECOD_BLOCKS;
    if (!DATA) {
        return;
    }

    var admin = document.getElementById('ecod-admin');
    if (!admin) {
        return;
    }

    var form = admin.nextElementSibling;
    while (form && form.tagName !== 'FORM') {
        form = form.nextElementSibling;
    }
    if (!form) {
        form = document.querySelector('.typecho-page-main form');
    }
    if (!form) {
        return;
    }

    var AREAS = ['home', 'aside', 'post', 'footer'];
    var defs = DATA.defs || {};
    var icons = DATA.icons || {};
    var state = {};
    var dirty = false;

    function $(sel, root) {
        return (root || document).querySelector(sel);
    }

    function $$(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }

    function esc(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /* ------------------------------------------------------------------
     * 状态
     * ---------------------------------------------------------------- */
    AREAS.forEach(function (area) {
        state[area] = (DATA.areas[area] || []).map(function (row) {
            var params = {};
            Object.keys(row.params || {}).forEach(function (k) {
                params[k] = row.params[k];
            });
            return { id: row.id, on: !!row.on, params: params };
        });
    });

    function findRow(area, id) {
        for (var i = 0; i < state[area].length; i++) {
            if (state[area][i].id === id) {
                return state[area][i];
            }
        }
        return null;
    }

    /* ------------------------------------------------------------------
     * 放置表：块 → 显示在哪些区域（参数共用，顺序按各自区域排）
     * ---------------------------------------------------------------- */
    var place = {};

    Object.keys(defs).forEach(function (id) {
        var saved = (DATA.place || {})[id];
        var areas = defs[id].areas || [defs[id].area];
        place[id] = (Array.isArray(saved) ? saved : []).filter(function (a) {
            return areas.indexOf(a) > -1;
        });
        if (!place[id].length) {
            place[id] = areas.slice(0, 1);
        }
    });

    function areaName(area) {
        var meta = (DATA.panes || {})[area];
        return meta ? meta.title : area;
    }

    function placeLabel(id) {
        var list = place[id] || [];
        return list.length > 1
            ? list.map(areaName).join(' · ')
            : areaName(list[0] || 'home');
    }

    function copyParams(from) {
        var out = {};
        Object.keys(from || {}).forEach(function (k) {
            out[k] = from[k];
        });
        return out;
    }

    function sync() {
        var input = form.querySelector('[name="EBlocks"]');
        if (input) {
            var payload = {};
            AREAS.forEach(function (area) {
                payload[area] = state[area];
            });
            payload.place = place;
            input.value = JSON.stringify(payload, null, 0);
        }
    }

    function setDirty(value, count) {
        dirty = value;
        var mark = $('[data-dirty]');
        if (mark) {
            mark.hidden = !value;
            if (value) {
                mark.textContent = count > 0 ? count + ' 项未保存' : '有未保存的修改';
            }
        }
        paintFloat();
    }

    /* ------------------------------------------------------------------
     * 兜底保存：顶栏按钮滚出视野且有未保存修改时，右下角浮动按钮出现
     * （整页滚动时顶栏可能不吸顶，保证任何滚动位置都能保存）
     * ---------------------------------------------------------------- */
    var floatBtn = null;

    function paintFloat() {
        if (!floatBtn) {
            return;
        }
        var head = $('.ecod-admin__head', admin);
        var box = head ? head.getBoundingClientRect() : null;
        var headVisible = box ? (box.bottom > 0 && box.top < window.innerHeight) : true;
        floatBtn.hidden = !(dirty && !headVisible);
    }

    function mountFloatSave(submit) {
        floatBtn = document.createElement('button');
        floatBtn.type = 'submit';
        floatBtn.className = 'ecod-save-float';
        floatBtn.setAttribute('form', form.id);
        floatBtn.textContent = submit.textContent || submit.value || '保存设置';
        floatBtn.hidden = true;
        document.body.appendChild(floatBtn);

        var ticking = false;
        var onScroll = function () {
            if (ticking) {
                return;
            }
            ticking = true;
            window.requestAnimationFrame(function () {
                ticking = false;
                paintFloat();
            });
        };
        /* 捕获阶段监听：整页滚动与内容区内部滚动都能覆盖 */
        document.addEventListener('scroll', onScroll, { passive: true, capture: true });
        window.addEventListener('resize', paintFloat);
    }

    /* ------------------------------------------------------------------
     * 布局：字段归组 + 表单入位
     * ---------------------------------------------------------------- */
    function layout() {
        var col = admin.parentElement;
        if (col && /col-(tb|mb)-/.test(col.className)) {
            col.classList.add('ecod-wide');
        }

        $$('ul[data-ecod-pane]', form).forEach(function (ul) {
            var pane = ul.getAttribute('data-ecod-pane');
            var host = $('[data-fields="' + pane + '"]');
            if (host) {
                host.appendChild(ul);
            } else {
                /* 无面板宿主的分组（如未接入的 AI）：字段保留在表单里可保存，但不展示 */
                ul.style.display = 'none';
            }
        });

        var body = $('[data-admin-body]');
        if (body) {
            body.appendChild(form);
        }

        /* 字段被搬到了面板里、已不在 form 内，用 form 属性把归属补回来，
           否则提交时这些值不会被带上（Typecho 只收表单内的字段） */
        form.id = form.id || 'ecod-settings-form';
        $$('[data-fields] input, [data-fields] select, [data-fields] textarea', admin).forEach(function (el) {
            el.setAttribute('form', form.id);
        });

        /* 保存按钮移进顶栏右侧操作区；Typecho 渲染的是 button[type=submit] */
        var submit = $('input[type="submit"], button[type="submit"]', form);
        var actions = $('.ecod-admin__actions', admin);
        if (submit && actions) {
            var btn = document.createElement('button');
            btn.type = 'submit';
            btn.className = 'btn primary ecod-save';
            btn.setAttribute('form', form.id);
            btn.textContent = submit.textContent || submit.value || '保存设置';
            submit.style.display = 'none';
            var submitWrap = submit.closest('.typecho-option-submit');
            if (submitWrap) {
                submitWrap.style.display = 'none';
            }
            actions.appendChild(btn);
            mountFloatSave(btn);
        }
    }

    /* ------------------------------------------------------------------
     * 选择类字段 → 视觉卡片 / 分段 / 开关
     * 原生 select 保留为值载体（只做 display:none），保存与导出都不受影响
     * ---------------------------------------------------------------- */
    var CHOICE = {
        ECardMode: { kind: 'visual' },
        EAside: { kind: 'visual' },
        EThumbSide: { kind: 'visual' },
        EDark: { kind: 'segment' },
        ELike: { kind: 'switch' },
        EPjax: { kind: 'switch' },
        ELogoBoth: { kind: 'switch' },
        EComment: { kind: 'switch' },
        ECode: { kind: 'switch' },
        ELightbox: { kind: 'switch' },
        ELazy: { kind: 'switch' },
        EViews: { kind: 'switch' },
        ECnOnly: { kind: 'switch' },
        ECommentMail: { kind: 'switch' },
        ECursor: { kind: 'switch' },
        ECodeTheme: { kind: 'segment' },
        EAnimate: { kind: 'segment' },
        EBackdrop: { kind: 'segment' },
        ECommentMailSecure: { kind: 'segment' },
        ECommentProvider: { kind: 'segment' },
        ETurnstile: { kind: 'switch' },
        EAkismet: { kind: 'switch' },
        EEmojiStyle: { kind: 'segment' },
        EAvatarPreset: { kind: 'segment' }
    };

    var VIEWS = {
        ECardMode: {
            default: 'list',
            feature: 'feature',
            gallery: 'gallery',
            bare: 'bare',
            grid: 'grid',
            title: 'title',
            compact: 'compact',
            mosaic: 'mosaic',
            item: 'item'
        },
        EAside: { right: 'aside-right', left: 'aside-left', none: 'aside-none' },
        EThumbSide: { right: 'thumb-right', left: 'thumb-left' }
    };

    /* "列表（文左图右）" → ["列表", "文左图右"] */
    function splitLabel(text) {
        var match = String(text).match(/^([^（(]+)[（(]\s*(.+?)\s*[)）]$/);
        return match
            ? [match[1].trim(), match[2].trim()]
            : [String(text).trim(), ''];
    }

    function enhanceFields() {
        $$('[data-fields] select').forEach(function (select) {
            var options = Array.prototype.map.call(select.options, function (opt) {
                var pair = splitLabel(opt.textContent);
                return { value: opt.value, label: pair[0], hint: pair[1] };
            });

            var conf = CHOICE[select.name];
            if (!conf) {
                /* 未登记的下拉：on/off 二值给开关，其余给分段，避免露出生硬的原生下拉 */
                var values = options.map(function (opt) { return opt.value; });
                var binary = options.length === 2 && values.indexOf('on') > -1 && values.indexOf('off') > -1;
                conf = { kind: binary ? 'switch' : 'segment' };
            }

            var wrap = document.createElement('div');
            wrap.setAttribute('role', 'group');
            wrap.setAttribute('aria-label', select.name);

            function commit(value) {
                select.value = value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }

            /* 二值开关（值不一定是 on/off，如 ECursor 是 off / dot；
               关闭值优先取 off，开启值取另一个，避免两者被推断成同一个值导致开关点不动） */
            if (conf.kind === 'switch' && options.length === 2) {
                var byValue = {};
                options.forEach(function (opt) { byValue[opt.value] = opt.label; });
                var offVal = Object.prototype.hasOwnProperty.call(byValue, 'off') ? 'off' : options[0].value;
                var onVal = options[0].value === offVal ? options[1].value : options[0].value;

                wrap.className = 'ecod-choice ecod-choice--switch';
                wrap.innerHTML = '<label class="ecod-toggle"><input type="checkbox">'
                    + '<i></i><span data-word></span></label>';

                var box = wrap.querySelector('input');
                var word = wrap.querySelector('[data-word]');

                var paintSwitch = function () {
                    var on = select.value !== offVal;
                    box.checked = on;
                    word.textContent = on ? (byValue[onVal] || '开启') : (byValue[offVal] || '关闭');
                };

                box.addEventListener('change', function () {
                    commit(box.checked ? onVal : offVal);
                    paintSwitch();
                });

                select.parentNode.insertBefore(wrap, select.nextSibling);
                select.style.display = 'none';
                select.addEventListener('change', paintSwitch);
                paintSwitch();
                return;
            }

            /* 卡片 / 分段 */
            wrap.className = 'ecod-choice ecod-choice--' + conf.kind;

            var paint = function () {
                $$('.ecod-choice__item', wrap).forEach(function (item) {
                    item.classList.toggle('is-on', item.dataset.val === select.value);
                });
            };

            options.forEach(function (opt) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'ecod-choice__item';
                btn.dataset.val = opt.value;

                var view = (VIEWS[select.name] || {})[opt.value];
                var html = '';

                if (view) {
                    html += '<span class="ecod-choice__view" data-v="' + view + '">'
                        + '<i></i><i></i><i></i></span>';
                }

                html += '<b>' + esc(opt.label) + '</b>';

                if (opt.hint) {
                    html += '<small>' + esc(opt.hint) + '</small>';
                }

                btn.innerHTML = html;
                btn.title = opt.hint ? opt.label + '（' + opt.hint + '）' : opt.label;

                btn.addEventListener('click', function () {
                    commit(opt.value);
                    paint();
                });

                wrap.appendChild(btn);
            });

            select.parentNode.insertBefore(wrap, select.nextSibling);
            select.style.display = 'none';
            select.addEventListener('change', paint);
            paint();
        });
    }

    /* ------------------------------------------------------------------
     * 条件联动：字段级 + 分组级
     *
     * 字段控件带 data-ecod-show-when="控制字段:取值"
     *   逗号分隔 = 且；单个条件内 | = 任一取值；取值前置 ! = 不等于；
     *   !empty / empty = 非空 / 为空。
     *   例：ELogoBoth → "ELogoImage:!empty"
     *       ECommentFold → "ECommentProvider:native,ECommentExpandMode:!all|button"
     * 分组面板带 data-ecod-pane-when（同一套语法），
     * 另附 data-ecod-pane-note（提示文案）与 data-ecod-pane-jump（分组:字段）。
     * ---------------------------------------------------------------- */
    var COND = 'data-ecod-show-when';
    var condRows = [];

    function condRow(el) {
        return el ? (el.closest('ul.typecho-option') || el.closest('.typecho-option')) : null;
    }

    function condVal(name) {
        var el = document.querySelector('[name="' + name + '"]');
        return el ? String(el.value == null ? '' : el.value) : null;
    }

    function condOne(expr) {
        var at = expr.indexOf(':');
        if (at < 1) {
            return true;
        }
        var val = condVal(expr.slice(0, at).trim());
        if (val === null) {
            return true;
        }
        var want = expr.slice(at + 1).trim();
        if (want === '!empty') {
            return val.trim() !== '';
        }
        if (want === 'empty') {
            return val.trim() === '';
        }
        var negate = want.charAt(0) === '!';
        if (negate) {
            want = want.slice(1);
        }
        var hit = want.split('|').indexOf(val) > -1;
        return negate ? !hit : hit;
    }

    function condMatch(expr) {
        var parts = String(expr).split(',');
        for (var i = 0; i < parts.length; i++) {
            if (!condOne(parts[i])) {
                return false;
            }
        }
        return true;
    }

    /* 条件里引用到的控制字段名 */
    function condNames(expr) {
        var out = [];
        String(expr).split(',').forEach(function (one) {
            var at = one.indexOf(':');
            var name = at < 1 ? '' : one.slice(0, at).trim();
            if (name) {
                out.push(name);
            }
        });
        return out;
    }

    /* 隐藏提示的措辞：填了才有 / 开了才有 / 选对选项才有 */
    function condHint(expr) {
        if (/(^|,)[^:,]+:!?empty(,|$)/.test(expr)) {
            return '填写后显示';
        }
        if (/(^|,)[^:,]+:on(,|$)/.test(expr)) {
            return '开启后显示';
        }
        return '选择对应项后显示';
    }

    function collectCondRows() {
        condRows = $$('[' + COND + ']', admin).map(function (el) {
            var expr = String(el.getAttribute(COND));
            return { row: condRow(el), expr: expr, names: condNames(expr), open: true };
        }).filter(function (item) {
            return !!item.row;
        });
    }

    /**
     * 求值 + 落位
     *
     * 链式：父字段被隐藏时，它控制的下级字段一并隐藏；
     * 反复求值到稳定（最多 4 轮），避免「评论关闭 → 邮件开关隐藏 → SMTP 仍露着」。
     */
    function applyConditions() {
        for (var round = 0; round < 4; round++) {
            var changed = false;
            condRows.forEach(function (item) {
                var open = condMatch(item.expr);
                if (open) {
                    for (var i = 0; i < item.names.length; i++) {
                        var ctrlRow = condRow(document.querySelector('[name="' + item.names[i] + '"]'));
                        if (ctrlRow && ctrlRow.hidden) {
                            open = false;
                            break;
                        }
                    }
                }
                if (open !== item.open) {
                    item.open = open;
                    changed = true;
                }
                item.row.hidden = !item.open;
                item.row.style.display = item.open ? '' : 'none';
            });
            if (!changed) {
                break;
            }
        }

        paintCondNotes();
        paintPaneNotices();
        buildSearchIndex();
    }

    /* 父项下方的「另有 N 项」提示：让用户知道关掉一个开关顺手关了什么 */
    function paintCondNotes() {
        var counts = {};
        var hints = {};

        condRows.forEach(function (item) {
            if (item.open || !item.names.length) {
                return;
            }
            var key = item.names[0];
            counts[key] = (counts[key] || 0) + 1;
            if (!hints[key]) {
                hints[key] = condHint(item.expr);
            }
        });

        $$('[data-condnote]', admin).forEach(function (note) {
            var key = note.getAttribute('data-condnote');
            if (!counts[key]) {
                note.parentNode.removeChild(note);
                return;
            }
            note.textContent = '另有 ' + counts[key] + ' 项关联设置已隐藏（' + (hints[key] || '满足条件后') + '）';
        });

        Object.keys(counts).forEach(function (key) {
            var row = condRow(document.querySelector('[name="' + key + '"]'));
            if (!row || row.querySelector('[data-condnote]')) {
                return;
            }
            var note = document.createElement('p');
            note.className = 'ecod-condnote';
            note.setAttribute('data-condnote', key);
            note.textContent = '另有 ' + counts[key] + ' 项关联设置已隐藏（' + (hints[key] || '满足条件后') + '）';
            row.appendChild(note);
        });
    }

    /* 分组级提示条：整组被外部开关关掉时，顶部分组里给出原因与去处 */
    function paintPaneNotices() {
        $$('[data-pane-panel]', admin).forEach(function (pane) {
            var expr = pane.getAttribute('data-ecod-pane-when');
            if (!expr) {
                return;
            }
            var bar = pane.querySelector('[data-condbar]');
            if (condMatch(expr)) {
                if (bar) {
                    bar.parentNode.removeChild(bar);
                }
                return;
            }
            if (!bar) {
                bar = document.createElement('div');
                bar.className = 'ecod-condbar';
                bar.setAttribute('data-condbar', '');
                pane.insertBefore(bar, pane.firstChild);
            }
            var jump = pane.getAttribute('data-ecod-pane-jump') || '';
            var note = pane.getAttribute('data-ecod-pane-note') || '本组设置当前不生效';
            bar.innerHTML = '<span>' + esc(note) + '</span>'
                + (jump ? '<button type="button" class="ecod-condbar__go" data-cond-jump="' + esc(jump) + '">去开启</button>' : '');
        });
    }

    function initConditions() {
        collectCondRows();
        applyConditions();

        var watched = {};
        condRows.forEach(function (item) {
            item.names.forEach(function (name) {
                if (watched[name]) {
                    return;
                }
                var ctrl = document.querySelector('[name="' + name + '"]');
                if (!ctrl) {
                    return;
                }
                watched[name] = 1;
                ctrl.addEventListener('change', applyConditions);
                ctrl.addEventListener('input', applyConditions);
            });
        });

        /* 提示条上的「去开启」：跳到控制该组的字段并高亮 */
        document.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('[data-cond-jump]') : null;
            if (!btn) {
                return;
            }
            var parts = String(btn.getAttribute('data-cond-jump')).split(':');
            switchPane(parts[0]);
            window.setTimeout(function () {
                var row = condRow(document.querySelector('[name="' + parts[1] + '"]'));
                if (!row) {
                    return;
                }
                row.scrollIntoView({ block: 'center', behavior: 'smooth' });
                row.classList.remove('ecod-hit');
                void row.offsetWidth;
                row.classList.add('ecod-hit');
                window.setTimeout(function () {
                    row.classList.remove('ecod-hit');
                }, 1900);
            }, 120);
        });
    }

    /* ------------------------------------------------------------------
     * 图片字段 → 媒体库选图
     * 原生 input / textarea 仍是值载体，选完只是写值 + 触发事件
     * ---------------------------------------------------------------- */
    var MD_ICON = '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" '
        + 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
        + '<rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="8.5" cy="9.5" r="1.5"/>'
        + '<path d="M21 16l-4.5-4.5L6 20"/></svg>';

    /**
     * 给一个文本控件套上「预览 + 选择图片」
     *
     * @param {HTMLElement} input 原生控件（single 用 input，multi 用 textarea）
     */
    function wrapMedia(input) {
        var mode = input.getAttribute('data-ecod-media') === 'multi' ? 'multi' : 'single';

        var wrap = document.createElement('div');
        wrap.className = 'ecod-media-field';

        function fire() {
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function openPicker(pickOpts) {
            if (!window.EcodMedia) {
                return;
            }
            window.EcodMedia.pick(pickOpts);
        }

        /* 多图：缩略图池 + 追加 */
        if (mode === 'multi') {
            var grid = document.createElement('div');
            grid.className = 'ecod-pool';

            var add = document.createElement('button');
            add.type = 'button';
            add.className = 'ecod-pool__add';

            var readList = function () {
                return String(input.value || '').split(/[\r\n]+/).map(function (line) {
                    return line.trim();
                }).filter(Boolean);
            };

            var paintPool = function () {
                grid.innerHTML = '';
                var items = readList();

                items.forEach(function (url, index) {
                    var cell = document.createElement('div');
                    cell.className = 'ecod-pool__item';

                    var img = document.createElement('img');
                    img.src = url;
                    img.alt = '';
                    img.loading = 'lazy';

                    var del = document.createElement('button');
                    del.type = 'button';
                    del.className = 'ecod-pool__del';
                    del.title = '移除';
                    del.innerHTML = '&times;';
                    del.addEventListener('click', function () {
                        var next = readList();
                        next.splice(index, 1);
                        input.value = next.join('\n');
                        fire();
                        paintPool();
                    });

                    cell.appendChild(img);
                    cell.appendChild(del);
                    grid.appendChild(cell);
                });

                add.textContent = items.length ? '+ 继续添加' : '+ 从媒体库选择';
            };

            add.addEventListener('click', function () {
                openPicker({
                    multiple: true,
                    title: '选择默认缩略图',
                    value: readList(),
                    onPick: function (urls) {
                        input.value = urls.slice(0, 24).join('\n');
                        fire();
                        paintPool();
                    }
                });
            });

            input.parentNode.insertBefore(wrap, input);
            input.style.display = 'none';
            wrap.appendChild(grid);
            wrap.appendChild(add);
            paintPool();
            return;
        }

        /* 单图：缩略图预览 + URL 输入 + 选图按钮 */
        var preview = document.createElement('figure');
        preview.className = 'ecod-media-field__preview';
        preview.hidden = true;

        var row = document.createElement('div');
        row.className = 'ecod-media-field__row';

        var pick = document.createElement('button');
        pick.type = 'button';
        pick.className = 'ecod-media-field__pick';
        pick.innerHTML = MD_ICON + '<span>选择图片</span>';

        function paintSingle() {
            var url = String(input.value || '').trim();
            preview.innerHTML = '';

            if (!url) {
                preview.hidden = true;
                return;
            }

            preview.hidden = false;

            var img = document.createElement('img');
            img.src = url;
            img.alt = '图片预览';

            var cap = document.createElement('figcaption');

            var name = document.createElement('span');
            name.textContent = decodeURIComponent((url.split('?')[0].split('/').pop() || url)).slice(0, 48);

            var swap = document.createElement('button');
            swap.type = 'button';
            swap.className = 'ecod-media-field__act';
            swap.textContent = '更换';
            swap.addEventListener('click', picker);

            var clear = document.createElement('button');
            clear.type = 'button';
            clear.className = 'ecod-media-field__act';
            clear.textContent = '移除';
            clear.addEventListener('click', function () {
                input.value = '';
                fire();
                paintSingle();
            });

            cap.appendChild(name);
            cap.appendChild(swap);
            cap.appendChild(clear);
            preview.appendChild(img);
            preview.appendChild(cap);
        }

        function picker() {
            openPicker({
                multiple: false,
                title: '选择图片',
                value: String(input.value || '').trim(),
                onPick: function (urls) {
                    if (!urls[0]) {
                        return;
                    }
                    input.value = urls[0];
                    fire();
                    paintSingle();
                }
            });
        }

        input.parentNode.insertBefore(wrap, input);
        row.appendChild(input);
        row.appendChild(pick);
        wrap.appendChild(preview);
        wrap.appendChild(row);

        pick.addEventListener('click', picker);
        input.addEventListener('input', paintSingle);
        paintSingle();
    }

    function enhanceMedia() {
        $$('[data-ecod-media]', admin).forEach(function (input) {
            /* 容错：属性挂在容器上时向下找真正的控件 */
            if (!(input instanceof HTMLInputElement) && !(input instanceof HTMLTextAreaElement)) {
                var inner = input.querySelector('input, textarea');
                if (!inner) {
                    return;
                }
                input = inner;
            }
            if (!input.parentNode || input.dataset.ecodMediaReady === '1') {
                return;
            }
            input.dataset.ecodMediaReady = '1';
            wrapMedia(input);
        });
    }

    /* ------------------------------------------------------------------
     * 颜色字段 → 取色器 + 十六进制文本双向同步
     * 原生 input 仍是值载体，导入导出与保存都不受影响
     * ---------------------------------------------------------------- */
    var HEX = /^#?([0-9a-f]{3}|[0-9a-f]{6})$/i;

    function normalizeHex(value) {
        var v = String(value == null ? '' : value).trim();
        if (!HEX.test(v)) {
            return '';
        }
        v = v.charAt(0) === '#' ? v.toLowerCase() : '#' + v.toLowerCase();
        if (v.length === 4) {
            v = '#' + v[1] + v[1] + v[2] + v[2] + v[3] + v[3];
        }
        return v;
    }

    function enhanceColor() {
        $$('[data-ecod-color]', admin).forEach(function (input) {
            if (input.dataset.ecodColorReady === '1') {
                return;
            }
            input.dataset.ecodColorReady = '1';

            var wrap = document.createElement('div');
            wrap.className = 'ecod-color';

            var pick = document.createElement('input');
            pick.type = 'color';
            pick.className = 'ecod-color__pick';
            pick.setAttribute('aria-label', '选择颜色');
            pick.value = normalizeHex(input.value) || '#4f6ef2';

            var label = document.createElement('span');
            label.className = 'ecod-color__hex';

            var reset = document.createElement('button');
            reset.type = 'button';
            reset.className = 'ecod-color__reset';
            reset.textContent = '恢复默认';

            /* 预设色板：一键套用常用主色 */
            var PRESETS = ['#4f6ef2', '#2563eb', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
            var swatches = document.createElement('div');
            swatches.className = 'ecod-color__swatches';
            swatches.setAttribute('role', 'group');
            swatches.setAttribute('aria-label', '预设颜色');
            PRESETS.forEach(function (hex) {
                var sw = document.createElement('button');
                sw.type = 'button';
                sw.className = 'ecod-color__sw';
                sw.style.background = hex;
                sw.title = hex;
                sw.setAttribute('aria-label', '使用 ' + hex);
                sw.addEventListener('click', function () {
                    input.value = hex;
                    pick.value = hex;
                    sync(true);
                });
                swatches.appendChild(sw);
            });

            function sync(fire) {
                var value = normalizeHex(input.value);
                if (value) {
                    pick.value = value;
                    label.textContent = value;
                } else {
                    label.textContent = input.value ? '格式应为 #rrggbb' : '默认';
                }
                if (fire) {
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }

            pick.addEventListener('input', function () {
                input.value = pick.value;
                sync(true);
            });
            input.addEventListener('input', function () {
                sync(false);
            });
            reset.addEventListener('click', function () {
                input.value = '#4f6ef2';
                sync(true);
            });

            input.parentNode.insertBefore(wrap, input);
            input.classList.add('ecod-color__text');
            wrap.appendChild(pick);
            wrap.appendChild(input);
            wrap.appendChild(label);
            wrap.appendChild(reset);
            wrap.appendChild(swatches);
            sync(false);
        });
    }


    /* ------------------------------------------------------------------
     * 数字项：带 min/max 的输入框增强为「滑块 + 数值」联动
     * ---------------------------------------------------------------- */
    function enhanceRange() {
        $$('[data-fields] input[type="number"][min][max]', admin).forEach(function (input) {
            if (input.dataset.ecodRangeReady === '1') {
                return;
            }
            input.dataset.ecodRangeReady = '1';

            var min = parseFloat(input.getAttribute('min'));
            var max = parseFloat(input.getAttribute('max'));
            var step = parseFloat(input.getAttribute('step')) || 1;
            if (isNaN(min) || isNaN(max) || max <= min) {
                return;
            }

            var wrap = document.createElement('div');
            wrap.className = 'ecod-range';

            var range = document.createElement('input');
            range.type = 'range';
            range.className = 'ecod-range__slider';
            range.min = min;
            range.max = max;
            range.step = step;
            range.setAttribute('aria-label', (input.closest('li') && input.closest('li').querySelector('label') ? input.closest('li').querySelector('label').textContent : '数值') + ' 滑块');

            function clamp(v) {
                if (v === '' || isNaN(v)) {
                    return min;
                }
                return Math.min(Math.max(v, min), max);
            }

            function syncFromInput() {
                range.value = clamp(parseFloat(input.value));
            }

            range.addEventListener('input', function () {
                input.value = range.value;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
            input.addEventListener('input', syncFromInput);
            input.addEventListener('change', function () {
                if (input.value !== '') {
                    input.value = clamp(parseFloat(input.value));
                }
                syncFromInput();
            });

            input.parentNode.insertBefore(wrap, input);
            input.classList.add('ecod-range__num');
            wrap.appendChild(range);
            wrap.appendChild(input);
            syncFromInput();
        });
    }

    /* ------------------------------------------------------------------
     * 卡片模式预览：版式分组顶部放迷你列表，切换模式即时换骨架
     * ---------------------------------------------------------------- */
    function enhanceCardPreview() {
        var host = $('[data-fields="layout"]');
        if (!host || host.querySelector('[data-ecod-card-preview]')) {
            return;
        }
        /* 字段已被 layout() 移出 form，必须从 admin 面板里找 */
        var select = $('[name="ECardMode"]', admin);

        var box = document.createElement('div');
        box.className = 'ecod-cprev';
        box.setAttribute('data-ecod-card-preview', '1');
        box.setAttribute('aria-hidden', 'true');
        box.innerHTML =
            '<div class="ecod-cprev__list">' +
            '<span class="ecod-cprev__item"><i class="ecod-cprev__thumb"></i><span><b></b><em></em></span></span>' +
            '<span class="ecod-cprev__item"><i class="ecod-cprev__thumb"></i><span><b></b><em></em></span></span>' +
            '<span class="ecod-cprev__item"><i class="ecod-cprev__thumb"></i><span><b></b><em></em></span></span>' +
            '</div>' +
            '<small>预览：切换卡片模式即时看到骨架变化，保存后应用到全站</small>';

        function paint() {
            box.setAttribute('data-mode', select ? select.value : 'default');
        }

        host.insertBefore(box, host.firstChild);
        if (select) {
            select.addEventListener('change', paint);
        }
        paint();
    }

    /* ------------------------------------------------------------------
     * 站点背景预览：外观分组顶部，切换背景即时看效果
     * ---------------------------------------------------------------- */
    function enhanceBackdropPreview() {
        var host = $('[data-fields="appearance"]');
        if (!host || host.querySelector('[data-ecod-bd-preview]')) {
            return;
        }
        /* 字段已被 layout() 移出 form，必须从 admin 面板里找 */
        var select = $('[name="EBackdrop"]', admin);

        var box = document.createElement('div');
        box.className = 'ecod-bdprev';
        box.setAttribute('data-ecod-bd-preview', '1');
        box.setAttribute('aria-hidden', 'true');
        box.innerHTML =
            '<div class="ecod-bdprev__stage"><span class="ecod-bdprev__card"><b></b><i></i><i></i></span></div>' +
            '<small>预览：站点背景为纯 CSS 渐变，不产生额外请求</small>';

        function paint() {
            box.setAttribute('data-bd', select ? select.value : 'none');
        }

        host.insertBefore(box, host.firstChild);
        if (select) {
            select.addEventListener('change', paint);
        }
        paint();
    }

    /* ------------------------------------------------------------------
     * 主色迷你预览：站点分组顶部放一张迷你卡片，改主色即时看到效果
     * ---------------------------------------------------------------- */
    function enhancePreview() {
        var host = $('[data-fields="site"]');
        if (!host || host.querySelector('[data-ecod-preview]')) {
            return;
        }
        /* 字段已被 layout() 移出 form，必须从 admin 面板里找 */
        var primary = $('[name="EPrimary"]', admin);

        var box = document.createElement('div');
        box.className = 'ecod-preview';
        box.setAttribute('data-ecod-preview', '1');
        box.setAttribute('aria-hidden', 'true');
        box.innerHTML =
            '<div class="ecod-preview__card">' +
            '<span class="ecod-preview__thumb"></span>' +
            '<span class="ecod-preview__body">' +
            '<b>文章标题示例</b>' +
            '<i></i><i></i>' +
            '<em>按钮 · 链接 · 高亮都会跟随主色</em>' +
            '</span></div>' +
            '<small>预览：修改主色即时生效，保存后应用到全站</small>';

        function paint() {
            var v = normalizeHex(primary && primary.value) || '#4f6ef2';
            box.style.setProperty('--ecod-primary', v);
            /* 同步给其他预览（卡片模式骨架）与滑块拇指色 */
            admin.style.setProperty('--ecod-primary', v);
        }

        host.insertBefore(box, host.firstChild);
        if (primary) {
            primary.addEventListener('input', paint);
            primary.addEventListener('change', paint);
        }
        paint();
    }


    /* ------------------------------------------------------------------
     * 模块卡片
     * ---------------------------------------------------------------- */
    var GRIP = '<svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor"><circle cx="9" cy="6" r="1.6"/><circle cx="15" cy="6" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="9" cy="18" r="1.6"/><circle cx="15" cy="18" r="1.6"/></svg>';
    var GEAR = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="3.2"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1 1.56V21a2 2 0 1 1-4 0v-.06a1.7 1.7 0 0 0-1.11-1.56 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.7 1.7 0 0 0 .34-1.87 1.7 1.7 0 0 0-1.56-1H3a2 2 0 1 1 0-4h.06a1.7 1.7 0 0 0 1.56-1.11 1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.7 1.7 0 0 0 1.87.34H9a1.7 1.7 0 0 0 1-1.56V3a2 2 0 1 1 4 0v.06a1.7 1.7 0 0 0 1 1.56 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.7 1.7 0 0 0-.34 1.87V9a1.7 1.7 0 0 0 1.56 1H21a2 2 0 1 1 0 4h-.06a1.7 1.7 0 0 0-1.54 1z"/></svg>';

    var PIN = '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/></svg>';

    function cardHtml(row) {
        var def = defs[row.id] || {};
        var hasParams = Object.keys(def.params || {}).length > 0;
        var multiArea = (def.areas || []).length > 1;
        /* fixed：位置锁定；required：位置自由拖动，但不可关闭 */
        var movable = !def.fixed;
        var mandatory = !!def.fixed || !!def.required;
        var tag = def.fixed ? '固定' : (def.required ? '必选' : '');
        return '' +
            '<span class="ecod-block__grip" title="' + (movable ? '拖动排序' : '该模块位置已锁定') + '">' + (movable ? GRIP : '') + '</span>' +
            '<span class="ecod-block__icon">' + (icons[def.icon] || '') + '</span>' +
            '<span class="ecod-block__body">' +
            '<b>' + esc(def.name || row.id) + (tag ? '<em class="ecod-block__tag">' + tag + '</em>' : '') + '</b>' +
            '<small>' + esc(def.desc || '') + '</small>' +
            '</span>' +
            (multiArea
                ? '<button type="button" class="ecod-block__place" data-place title="选择显示位置">' + PIN +
                    '<span>' + esc(placeLabel(row.id)) + '</span></button>'
                : '') +
            '<label class="ecod-switch" title="' + (mandatory ? '该模块不可关闭' : '启用 / 停用') + '">' +
            '<input type="checkbox" data-on' + (row.on ? ' checked' : '') + (mandatory ? ' disabled' : '') + '>' +
            '<i></i></label>' +
            '<button type="button" class="ecod-block__gear" data-gear title="模块参数"' + (hasParams ? '' : ' disabled') + '>' + GEAR + '</button>';
    }

    function renderArea(area) {
        var host = $('[data-blocks="' + area + '"]');
        if (!host) {
            return;
        }
        host.innerHTML = '';
        state[area].forEach(function (row) {
            var def = defs[row.id] || {};
            var el = document.createElement('div');
            el.className = 'ecod-block' + (row.on ? '' : ' is-off') + (def.fixed ? ' ecod-block--fixed' : (def.required ? ' ecod-block--required' : ''));
            el.dataset.id = row.id;
            el.draggable = !def.fixed;
            el.innerHTML = cardHtml(row);
            host.appendChild(el);
        });
    }

    /* ------------------------------------------------------------------
     * 显示位置：一个模块可同时出现在多个区域，参数共用、顺序各自排
     * ---------------------------------------------------------------- */
    var placePop = null;

    function closePlacePop() {
        if (placePop && placePop.parentNode) {
            placePop.parentNode.removeChild(placePop);
        }
        placePop = null;
    }

    function moveBlock(id, area, on) {
        var def = defs[id] || {};
        var allowed = def.areas || [];
        var next = place[id].filter(function (a) {
            return a !== area;
        });
        if (on) {
            next.push(area);
        }
        if (!next.length) {
            return;
        }
        next.sort(function (a, b) {
            return allowed.indexOf(a) - allowed.indexOf(b);
        });
        place[id] = next;

        AREAS.forEach(function (a) {
            var row = findRow(a, id);
            var should = next.indexOf(a) > -1;
            if (should && !row) {
                /* 复制现有参数，保证多区域共用同一份配置 */
                var source = null;
                AREAS.forEach(function (x) {
                    var found = findRow(x, id);
                    if (!source && found) {
                        source = found;
                    }
                });
                state[a].push({
                    id: id,
                    on: source ? source.on : !!def.on,
                    params: copyParams(source ? source.params : {})
                });
            } else if (!should && row) {
                state[a].splice(state[a].indexOf(row), 1);
            }
        });

        AREAS.forEach(renderArea);
        updateCounts();
        sync();
        setDirty(true);
    }

    function openPlacePop(anchor) {
        var card = anchor.closest('.ecod-block');
        var id = card.dataset.id;
        var def = defs[id] || {};
        closePlacePop();

        var pop = document.createElement('div');
        pop.className = 'ecod-placepop';

        var title = document.createElement('p');
        title.className = 'ecod-placepop__title';
        title.textContent = '显示位置';
        pop.appendChild(title);

        (def.areas || []).forEach(function (area) {
            var label = document.createElement('label');
            label.className = 'ecod-placepop__row';

            var box = document.createElement('input');
            box.type = 'checkbox';
            box.checked = place[id].indexOf(area) > -1;
            box.addEventListener('change', function () {
                if (!box.checked && place[id].length === 1) {
                    box.checked = true;
                    return;
                }
                moveBlock(id, area, box.checked);
                $$('.ecod-block[data-id="' + id + '"] [data-place] span').forEach(function (span) {
                    span.textContent = placeLabel(id);
                });
            });

            var text = document.createElement('span');
            text.textContent = areaName(area);

            label.appendChild(box);
            label.appendChild(text);
            pop.appendChild(label);
        });

        var note = document.createElement('p');
        note.className = 'ecod-placepop__note';
        note.textContent = '参数共用；取消勾选即从该区域移除';
        pop.appendChild(note);

        document.body.appendChild(pop);
        placePop = pop;

        var rect = anchor.getBoundingClientRect();
        var width = pop.offsetWidth;
        var left = Math.min(rect.left, window.innerWidth - width - 12);
        pop.style.top = (rect.bottom + window.scrollY + 6) + 'px';
        pop.style.left = Math.max(12, left + window.scrollX) + 'px';
    }

    document.addEventListener('click', function (e) {
        if (!placePop) {
            return;
        }
        if (e.target.closest && (e.target.closest('.ecod-placepop') || e.target.closest('[data-place]'))) {
            return;
        }
        closePlacePop();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closePlacePop();
        }
    });

    function updateCounts() {
        var total = 0;
        AREAS.forEach(function (area) {
            var on = state[area].filter(function (r) {
                return r.on;
            }).length;
            total += on;
            var badge = document.querySelector('[data-count="' + area + '"]');
            if (badge) {
                badge.textContent = on;
            }
        });
        var tag = $('[data-blocks-total]');
        if (tag) {
            tag.textContent = total + ' 个模块启用';
        }
    }

    /* ------------------------------------------------------------------
     * 卡片交互
     * ---------------------------------------------------------------- */
    var dragCard = null;

    function commitOrder(area) {
        var host = $('[data-blocks="' + area + '"]');
        if (!host) {
            return;
        }
        var map = {};
        state[area].forEach(function (row) {
            map[row.id] = row;
        });
        state[area] = $$('.ecod-block', host).map(function (el) {
            return map[el.dataset.id];
        }).filter(Boolean);
        sync();
        setDirty(true);
    }

    function bindArea(area) {
        var host = $('[data-blocks="' + area + '"]');
        if (!host) {
            return;
        }

        host.addEventListener('change', function (e) {
            var box = e.target.closest('[data-on]');
            if (!box) {
                return;
            }
            var el = box.closest('.ecod-block');
            var id = el.dataset.id;
            /* 同一模块在多区域共用启用状态，改一处要同步全部副本 */
            AREAS.forEach(function (a) {
                var copy = findRow(a, id);
                if (copy) {
                    copy.on = box.checked;
                }
            });
            $$('.ecod-block[data-id="' + id + '"]').forEach(function (card) {
                card.classList.toggle('is-off', !box.checked);
                var input = card.querySelector('[data-on]');
                if (input && input !== box) {
                    input.checked = box.checked;
                }
            });
            updateCounts();
            sync();
            setDirty(true);
        });

        host.addEventListener('click', function (e) {
            var placeBtn = e.target.closest('[data-place]');
            if (placeBtn) {
                openPlacePop(placeBtn);
                return;
            }
            var gear = e.target.closest('[data-gear]');
            if (!gear || gear.disabled) {
                return;
            }
            openDrawer(area, gear.closest('.ecod-block').dataset.id);
        });

        host.addEventListener('dragstart', function (e) {
            var el = e.target.closest('.ecod-block');
            if (!el || el.classList.contains('ecod-block--fixed')) {
                e.preventDefault();
                return;
            }
            dragCard = el;
            el.classList.add('is-dragging');
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', el.dataset.id);
            }
        });

        host.addEventListener('dragend', function () {
            if (dragCard) {
                dragCard.classList.remove('is-dragging');
            }
            dragCard = null;
        });

        host.addEventListener('dragover', function (e) {
            if (!dragCard) {
                return;
            }
            e.preventDefault();
            var before = null;
            var cards = $$('.ecod-block:not(.is-dragging)', host);
            for (var i = 0; i < cards.length; i++) {
                /* 固定块位置不动，其它块只能排在它后面 */
                if (cards[i].classList.contains('ecod-block--fixed')) {
                    continue;
                }
                var box = cards[i].getBoundingClientRect();
                if (e.clientY < box.top + box.height / 2) {
                    before = cards[i];
                    break;
                }
            }
            if (before) {
                host.insertBefore(dragCard, before);
            } else {
                host.appendChild(dragCard);
            }
        });

        host.addEventListener('drop', function (e) {
            if (!dragCard) {
                return;
            }
            e.preventDefault();
            commitOrder(area);
        });
    }

    /* ------------------------------------------------------------------
     * 参数抽屉
     * ---------------------------------------------------------------- */
    var drawer = $('[data-drawer]');
    var drawerBody = $('[data-drawer-body]');
    var editing = null;

    function closeDrawer() {
        if (drawer) {
            drawer.hidden = true;
        }
        editing = null;
    }

    /* ------------------------------------------------------------------
     * 幻灯片编辑器（轮播的自定义图文）
     * 值以 JSON 字符串存进同一个参数，前台解析后优先于自动取文章
     * ---------------------------------------------------------------- */
    var SLIDE_FIELDS = [
        { key: 'image', ph: '图片地址（可选，点「选图」）', media: true },
        { key: 'title', ph: '标题' },
        { key: 'desc', ph: '副标题 / 说明（可选）' },
        { key: 'link', ph: '跳转链接（留空则不跳转）' }
    ];

    function slidesEditor(value, onChange) {
        var items = [];
        try {
            var parsed = JSON.parse(String(value || '[]'));
            if (Array.isArray(parsed)) {
                parsed.forEach(function (row) {
                    if (row && typeof row === 'object') {
                        items.push({
                            image: String(row.image || ''),
                            title: String(row.title || ''),
                            desc: String(row.desc || ''),
                            link: String(row.link || '')
                        });
                    }
                });
            }
        } catch (err) {
            items = [];
        }

        var box = document.createElement('div');
        box.className = 'ecod-slides';

        function flush() {
            var clean = items.filter(function (item) {
                return (item.title || '').trim() !== '' || (item.image || '').trim() !== '';
            });
            onChange(clean.length ? JSON.stringify(clean) : '');
        }

        function move(index, delta) {
            var next = index + delta;
            if (next < 0 || next >= items.length) {
                return;
            }
            var tmp = items[index];
            items[index] = items[next];
            items[next] = tmp;
            paint();
            flush();
        }

        function paintThumb(thumb, item, index) {
            thumb.style.backgroundImage = item.image ? 'url("' + item.image.replace(/"/g, '%22') + '")' : '';
            thumb.textContent = item.image ? '' : String(index + 1);
        }

        function paint() {
            box.innerHTML = '';

            if (!items.length) {
                var empty = document.createElement('p');
                empty.className = 'ecod-slides__empty';
                empty.textContent = '还没有自定义幻灯片，下面按文章来源自动取文章。';
                box.appendChild(empty);
            }

            items.forEach(function (item, index) {
                var row = document.createElement('div');
                row.className = 'ecod-slide';

                var thumb = document.createElement('span');
                thumb.className = 'ecod-slide__thumb';
                paintThumb(thumb, item, index);

                var fields = document.createElement('div');
                fields.className = 'ecod-slide__fields';

                SLIDE_FIELDS.forEach(function (field) {
                    var input = document.createElement('input');
                    input.type = 'text';
                    input.className = 'ecod-input';
                    input.placeholder = field.ph;
                    input.value = item[field.key] || '';
                    input.addEventListener('input', function () {
                        item[field.key] = input.value;
                        if (field.key === 'image') {
                            paintThumb(thumb, item, index);
                        }
                        flush();
                    });

                    if (!field.media) {
                        fields.appendChild(input);
                        return;
                    }

                    var row = document.createElement('div');
                    row.className = 'ecod-slide__row';
                    row.appendChild(input);

                    var pick = document.createElement('button');
                    pick.type = 'button';
                    pick.className = 'ecod-slide__pick';
                    pick.textContent = '选图';
                    pick.addEventListener('click', function () {
                        if (!window.EcodMedia) {
                            return;
                        }
                        window.EcodMedia.pick({
                            multiple: false,
                            title: '选择幻灯片图片',
                            value: input.value,
                            onPick: function (urls) {
                                if (!urls[0]) {
                                    return;
                                }
                                input.value = urls[0];
                                item.image = urls[0];
                                paintThumb(thumb, item, index);
                                flush();
                            }
                        });
                    });
                    row.appendChild(pick);
                    fields.appendChild(row);
                });

                var act = document.createElement('div');
                act.className = 'ecod-slide__act';

                [['↑', -1, '上移'], ['↓', 1, '下移'], ['×', 0, '删除']].forEach(function (conf) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'ecod-slide__btn' + (conf[1] === 0 ? ' is-danger' : '');
                    btn.textContent = conf[0];
                    btn.title = conf[2];
                    btn.setAttribute('aria-label', conf[2]);
                    btn.addEventListener('click', function () {
                        if (conf[1] === 0) {
                            items.splice(index, 1);
                            paint();
                            flush();
                        } else {
                            move(index, conf[1]);
                        }
                    });
                    act.appendChild(btn);
                });

                row.appendChild(thumb);
                row.appendChild(fields);
                row.appendChild(act);
                box.appendChild(row);
            });

            var add = document.createElement('button');
            add.type = 'button';
            add.className = 'ecod-slides__add';
            add.textContent = '+ 添加幻灯片';
            add.addEventListener('click', function () {
                items.push({ image: '', title: '', desc: '', link: '' });
                paint();
            });
            box.appendChild(add);
        }

        paint();
        return box;
    }

    function paramRow(key, param, value) {
        var wrap = document.createElement('div');
        wrap.className = 'ecod-param';

        /* 参数级条件：blocks-def.php 里用 when 声明，语法同设置页 */
        if (param.when) {
            wrap.setAttribute('data-param-when', String(param.when));
        }

        var label = document.createElement('div');
        label.className = 'ecod-param__label';
        label.innerHTML = '<b>' + esc(param.label || key) + '</b>' + (param.unit ? '<span>' + esc(param.unit) + '</span>' : '');
        wrap.appendChild(label);

        var input;
        var type = param.type || 'text';

        if (type === 'slides') {
            wrap.appendChild(slidesEditor(value, function (json) {
                write(key, json);
                paintParamConds();
            }));
        } else if (type === 'switch') {
            var box = document.createElement('label');
            box.className = 'ecod-switch ecod-switch--wide';
            box.innerHTML = '<span>' + (Number(value) === 1 ? '已启用' : '已关闭') + '</span>' +
                '<input type="checkbox" ' + (Number(value) === 1 ? 'checked' : '') + '><i></i>';
            input = box.querySelector('input');
            wrap.appendChild(box);

            var text = box.querySelector('span');
            input.addEventListener('change', function () {
                text.textContent = input.checked ? '已启用' : '已关闭';
                write(key, input.checked ? 1 : 0);
            });
        } else {
            if (type === 'select') {
                input = document.createElement('select');
                Object.keys(param.options || {}).forEach(function (optValue) {
                    var opt = document.createElement('option');
                    opt.value = optValue;
                    opt.textContent = param.options[optValue];
                    if (String(value) === String(optValue)) {
                        opt.selected = true;
                    }
                    input.appendChild(opt);
                });
            } else if (type === 'textarea') {
                input = document.createElement('textarea');
                input.rows = 3;
                input.value = value == null ? '' : value;
            } else if (type === 'number') {
                input = document.createElement('input');
                input.type = 'number';
                if (param.min != null) {
                    input.min = param.min;
                }
                if (param.max != null) {
                    input.max = param.max;
                }
                input.value = value == null ? '' : value;
            } else {
                input = document.createElement('input');
                input.type = 'text';
                input.value = value == null ? '' : value;
            }

            input.className = 'ecod-input';
            wrap.appendChild(input);

            /* 图片参数：加「选择图片」与缩略图预览，值仍写回同一个 input */
            if (type === 'image') {
                input.setAttribute('data-ecod-media', 'single');
                wrapMedia(input);
            }

            input.addEventListener('input', function () {
                var v = input.value;
                if (type === 'number') {
                    v = v === '' ? '' : Number(v);
                }
                write(key, v);
            });
        }

        if (param.desc) {
            var desc = document.createElement('p');
            desc.className = 'ecod-param__desc';
            desc.textContent = param.desc;
            wrap.appendChild(desc);
        }

        return wrap;
    }

    function write(key, value) {
        if (!editing) {
            return;
        }
        var hit = false;
        /* 参数在多区域间共用：一处修改要同步到该模块的所有副本 */
        AREAS.forEach(function (area) {
            var row = findRow(area, editing.id);
            if (row) {
                row.params[key] = value;
                hit = true;
            }
        });
        if (!hit) {
            return;
        }
        sync();
        setDirty(true);
    }

    /**
     * 抽屉里的参数级条件
     *
     * 取值取当前模块的 params，不读 DOM——switch 参数存 0/1，
     * 文本参数存字符串，所以 on / off / empty / !empty / 等于 / 不等于
     * 都在这里按「值的语义」判断，不靠控件类型。
     */
    function paintParamConds() {
        if (!editing || !drawerBody) {
            return;
        }
        var row = findRow(editing.area, editing.id);
        if (!row) {
            return;
        }

        $$('[data-param-when]', drawerBody).forEach(function (wrap) {
            var expr = wrap.getAttribute('data-param-when');

            var open = true;
            String(expr).split(',').forEach(function (one) {
                var at = one.indexOf(':');
                if (at < 1) {
                    return;
                }
                var key = one.slice(0, at).trim();
                var want = one.slice(at + 1).trim();
                var raw = row.params[key];
                var val = raw == null ? '' : String(raw);
                var truthy = val !== '' && val !== '0';

                if (want === 'empty') {
                    open = open && val === '';
                    return;
                }
                if (want === '!empty') {
                    open = open && val !== '';
                    return;
                }
                if (want === 'on') {
                    open = open && truthy;
                    return;
                }
                if (want === 'off') {
                    open = open && !truthy;
                    return;
                }

                var negate = want.charAt(0) === '!';
                if (negate) {
                    want = want.slice(1);
                }
                var hit = want.split('|').indexOf(val) > -1;
                open = open && (negate ? !hit : hit);
            });

            wrap.hidden = !open;
            wrap.style.display = open ? '' : 'none';
        });
    }

    function openDrawer(area, id) {
        var def = defs[id];
        var row = findRow(area, id);
        if (!def || !row || !drawer) {
            return;
        }
        editing = { area: area, id: id };

        var title = $('[data-drawer-title]');
        var desc = $('[data-drawer-desc]');
        if (title) {
            title.textContent = def.name;
        }
        if (desc) {
            desc.textContent = def.desc || '';
        }

        drawerBody.innerHTML = '';
        var keys = Object.keys(def.params || {});
        if (!keys.length) {
            drawerBody.innerHTML = '<p class="ecod-param__desc">该模块没有可配置的参数。</p>';
        } else {
            keys.forEach(function (key) {
                drawerBody.appendChild(paramRow(key, def.params[key], row.params[key]));
            });
            paintParamConds();
        }
        drawer.hidden = false;
    }

    if (drawer) {
        $$('[data-drawer-close]', drawer).forEach(function (el) {
            el.addEventListener('click', closeDrawer);
        });
        /* 抽屉内的值变了就重算参数条件，不用关掉重开 */
        if (drawerBody) {
            drawerBody.addEventListener('input', paintParamConds);
            drawerBody.addEventListener('change', paintParamConds);
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !drawer.hidden) {
                closeDrawer();
            }
        });
    }

    /* ------------------------------------------------------------------
     * 分组切换
     * ---------------------------------------------------------------- */
    function switchPane(pane) {
        $$('.ecod-admin__navitem').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.dataset.pane === pane);
        });
        $$('[data-pane-panel]').forEach(function (panel) {
            panel.classList.toggle('is-active', panel.getAttribute('data-pane-panel') === pane);
        });
        var meta = (DATA.panes || {})[pane] || {};
        var title = $('[data-pane-title]');
        var desc = $('[data-pane-desc]');
        if (title) {
            title.textContent = meta.title || '';
        }
        if (desc) {
            desc.textContent = meta.desc || '';
        }
        /* 换组后内容区回到顶部，避免停留在上一组的中段 */
        var body = $('[data-admin-body]');
        if (body) {
            body.scrollTop = 0;
        }
        try {
            sessionStorage.setItem('ecod-admin-pane', pane);
        } catch (err) { /* 忽略隐私模式 */ }
    }

    $$('.ecod-admin__navitem').forEach(function (btn) {
        btn.addEventListener('click', function () {
            switchPane(btn.dataset.pane);
        });
    });

    /* ------------------------------------------------------------------
     * 设置搜索：索引「字段 + 模块」，命中后跳到所属分组并高亮目标
     * ---------------------------------------------------------------- */
    var searchInput = $('[data-ecod-search]');
    var searchRes = $('[data-search-results]');
    var searchHint = $('[data-search-hint]');
    var searchIdx = [];
    var searchHits = [];
    var searchFocus = -1;

    function buildSearchIndex() {
        searchIdx = [];

        var paneTitle = function (pane) {
            return (DATA.panes || {})[pane] ? DATA.panes[pane].title : '';
        };

        $$('[data-fields]').forEach(function (host) {
            var pane = host.getAttribute('data-fields');
            $$('.typecho-option', host).forEach(function (opt) {
                /* 当前条件下不生效的字段不入索引，避免搜到却跳进一个看不见的行 */
                if (opt.hidden) {
                    return;
                }
                var labelEl = opt.querySelector('.typecho-label');
                var descEl = opt.querySelector('.description');
                var control = opt.querySelector('input[name], select[name], textarea[name]');
                var label = labelEl ? labelEl.textContent.trim() : '';
                var name = control ? String(control.name || '') : '';
                var desc = descEl ? descEl.textContent.trim() : '';
                if (!label && !name) {
                    return;
                }
                searchIdx.push({
                    pane: pane,
                    kind: '设置',
                    label: label || name,
                    hint: paneTitle(pane),
                    text: (label + ' ' + name + ' ' + desc + ' ' + paneTitle(pane)).toLowerCase(),
                    el: opt
                });
            });
        });

        Object.keys(defs).forEach(function (id) {
            var def = defs[id];
            var params = Object.keys(def.params || {}).map(function (k) {
                return (def.params[k].label || k) + ' ' + (def.params[k].desc || '');
            }).join(' ');
            searchIdx.push({
                pane: def.area,
                kind: '模块',
                label: def.name,
                hint: paneTitle(def.area),
                text: (def.name + ' ' + def.desc + ' ' + id + ' ' + params + ' ' + paneTitle(def.area)).toLowerCase(),
                block: id
            });
        });
    }

    function markHit(text, tokens) {
        var out = esc(text == null ? '' : text);
        tokens.forEach(function (token) {
            if (!token) {
                return;
            }
            var re = new RegExp('(' + token.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig');
            out = out.replace(re, '<em>$1</em>');
        });
        return out;
    }

    function closeSearch() {
        if (searchRes) {
            searchRes.hidden = true;
        }
        searchFocus = -1;
    }

    function paintFocus() {
        $$('.ecod-searchres__item', searchRes).forEach(function (btn, i) {
            btn.classList.toggle('is-focus', i === searchFocus);
        });
    }

    function goToHit(hit) {
        if (!hit) {
            return;
        }
        switchPane(hit.pane);
        closeSearch();

        window.setTimeout(function () {
            var target = hit.block
                ? $('[data-blocks="' + hit.pane + '"] .ecod-block[data-id="' + hit.block + '"]')
                : hit.el;
            if (!target) {
                return;
            }
            target.scrollIntoView({ block: 'center', behavior: 'smooth' });
            target.classList.remove('ecod-hit');
            /* 强制回流，让同一元素连续命中时动画能重放 */
            void target.offsetWidth;
            target.classList.add('ecod-hit');
            window.setTimeout(function () {
                target.classList.remove('ecod-hit');
            }, 1900);
        }, 60);
    }

    function renderSearch(tokens) {
        if (!searchRes) {
            return;
        }
        searchRes.innerHTML = '';

        if (!searchHits.length) {
            searchRes.innerHTML = '<p class="ecod-searchres__empty">没有匹配的设置项</p>';
            searchRes.hidden = false;
            return;
        }

        searchHits.forEach(function (hit, i) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ecod-searchres__item';
            btn.innerHTML = '<span class="ecod-searchres__kind">' + hit.kind + '</span>'
                + '<b>' + markHit(hit.label, tokens) + '</b>'
                + '<small>' + esc(hit.hint || '') + '</small>';
            btn.addEventListener('click', function () {
                goToHit(hit);
            });
            searchRes.appendChild(btn);
        });

        searchRes.hidden = false;
        searchFocus = -1;
    }

    function runSearch(raw) {
        var q = String(raw || '').trim().toLowerCase();
        if (searchHint) {
            searchHint.hidden = q !== '';
        }
        if (!q) {
            closeSearch();
            if (searchRes) {
                searchRes.innerHTML = '';
            }
            searchHits = [];
            return;
        }

        var tokens = q.split(/\s+/).filter(Boolean);
        searchHits = searchIdx.filter(function (row) {
            return tokens.every(function (token) {
                return row.text.indexOf(token) > -1;
            });
        }).sort(function (a, b) {
            var aStart = a.label.toLowerCase().indexOf(tokens[0]) === 0 ? 0 : 1;
            var bStart = b.label.toLowerCase().indexOf(tokens[0]) === 0 ? 0 : 1;
            return aStart - bStart;
        }).slice(0, 24);

        renderSearch(tokens);
    }

    if (searchInput && searchRes) {
        searchInput.addEventListener('input', function () {
            runSearch(searchInput.value);
        });

        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                if (!searchHits.length) {
                    return;
                }
                e.preventDefault();
                searchFocus += e.key === 'ArrowDown' ? 1 : -1;
                if (searchFocus < 0) {
                    searchFocus = searchHits.length - 1;
                }
                if (searchFocus >= searchHits.length) {
                    searchFocus = 0;
                }
                paintFocus();
                return;
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                goToHit(searchHits[searchFocus > -1 ? searchFocus : 0]);
                return;
            }
            if (e.key === 'Escape') {
                searchInput.value = '';
                runSearch('');
                searchInput.blur();
            }
        });

        document.addEventListener('click', function (e) {
            if (!e.target.closest || !e.target.closest('.ecod-admin__search')) {
                closeSearch();
            }
        });
    }

    /* ------------------------------------------------------------------
     * 备份：导出 / 导入 / 恢复默认
     * ---------------------------------------------------------------- */
    function collect() {
        var blocks = { place: place };
        AREAS.forEach(function (area) {
            blocks[area] = state[area];
        });

        var out = {
            app: 'ecod-theme',
            version: 2,
            exportedAt: new Date().toISOString(),
            options: {},
            blocks: blocks
        };
        $$('input[name], textarea[name], select[name]', form).forEach(function (el) {
            if (!el.name || el.name === 'EBlocks' || el.type === 'file' || el.type === 'button') {
                return;
            }
            if (el.type === 'radio') {
                if (el.checked) {
                    out.options[el.name] = el.value;
                }
                return;
            }
            if (el.type === 'checkbox') {
                out.options[el.name] = el.checked ? (el.value || 'on') : '';
                return;
            }
            out.options[el.name] = el.value;
        });
        return out;
    }

    function exportConfig() {
        sync();
        var blob = new Blob([JSON.stringify(collect(), null, 2)], { type: 'application/json' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'ecod-config-' + new Date().toISOString().slice(0, 10) + '.json';
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(function () {
            URL.revokeObjectURL(url);
        }, 1500);
    }

    function defaultsFor(def, params) {
        var out = {};
        Object.keys(def.params || {}).forEach(function (key) {
            var has = params && Object.prototype.hasOwnProperty.call(params, key);
            var fallback = def.params[key].default;
            out[key] = has ? params[key] : (fallback == null ? '' : fallback);
        });
        return out;
    }

    function importConfig(file) {
        var reader = new FileReader();
        reader.onload = function () {
            var data;
            try {
                data = JSON.parse(String(reader.result));
            } catch (err) {
                window.alert('文件不是合法的 JSON，请检查后重试。');
                return;
            }

            if (data.options) {
                Object.keys(data.options).forEach(function (name) {
                    var value = data.options[name];
                    $$('[name="' + name + '"]', form).forEach(function (el) {
                        if (el.type === 'radio') {
                            el.checked = String(el.value) === String(value);
                        } else if (el.type === 'checkbox') {
                            el.checked = !!value && value !== '';
                    } else {
                        el.value = value;
                    }
                    /* 派发事件，让卡片 / 媒体预览等增强控件跟着重绘 */
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                });
                });
            }

            if (data.blocks) {
                /* 新格式带 place：按导入的放置表重建，旧格式按块声明区域兜底 */
                if (data.blocks.place && typeof data.blocks.place === 'object') {
                    Object.keys(defs).forEach(function (id) {
                        var saved = data.blocks.place[id];
                        var allowed = defs[id].areas || [defs[id].area];
                        if (!Array.isArray(saved)) {
                            return;
                        }
                        var picked = saved.filter(function (a) {
                            return allowed.indexOf(a) > -1;
                        });
                        if (picked.length) {
                            place[id] = picked;
                        }
                    });
                }

                AREAS.forEach(function (area) {
                    var incoming = data.blocks[area];
                    if (!Array.isArray(incoming)) {
                        return;
                    }
                    var seen = {};
                    var next = [];
                    incoming.forEach(function (row) {
                        var def = defs[row.id];
                        var allowed = def ? (def.areas || [def.area]) : [];
                        if (!def || allowed.indexOf(area) === -1 || seen[row.id]) {
                            return;
                        }
                        seen[row.id] = true;
                        next.push({
                            id: row.id,
                            on: (def.fixed || def.required) ? true : !!row.on,
                            params: defaultsFor(def, row.params)
                        });
                    });
                    state[area].forEach(function (row) {
                        if (!seen[row.id]) {
                            next.push(row);
                        }
                    });
                    state[area] = next;
                    renderArea(area);
                });
                updateCounts();
            }

            sync();
            setDirty(true);
        };
        reader.readAsText(file);
    }

    function resetArea(area) {
        var nativeIds = {};

        /* 原生模块回到自己的区域（顺序与参数都取默认） */
        Object.keys(defs).forEach(function (id) {
            if (defs[id].area === area) {
                nativeIds[id] = true;
                place[id] = [area];
            }
        });

        /* 从别的区域借调来的模块撤回原区域 */
        Object.keys(place).forEach(function (id) {
            if (nativeIds[id] || place[id].indexOf(area) === -1) {
                return;
            }
            place[id] = place[id].filter(function (a) {
                return a !== area;
            });
            if (!place[id].length) {
                place[id] = [defs[id].area];
            }
        });

        state[area] = Object.keys(nativeIds).sort(function (a, b) {
            return (defs[a].order || 100) - (defs[b].order || 100);
        }).map(function (id) {
            return {
                id: id,
                on: (defs[id].fixed || defs[id].required) ? true : !!defs[id].on,
                params: defaultsFor(defs[id], null)
            };
        });

        /* 其它区域跟着放置表对齐 */
        AREAS.forEach(function (a) {
            if (a === area) {
                return;
            }
            state[a] = state[a].filter(function (row) {
                return place[row.id].indexOf(a) > -1;
            });
            Object.keys(place).forEach(function (id) {
                if (place[id].indexOf(a) === -1 || findRow(a, id)) {
                    return;
                }
                state[a].push({
                    id: id,
                    on: !!defs[id].on,
                    params: defaultsFor(defs[id], null)
                });
            });
        });

        AREAS.forEach(renderArea);
        updateCounts();
        sync();
        setDirty(true);
    }

    $$('[data-ecod-export]').forEach(function (btn) {
        btn.addEventListener('click', exportConfig);
    });

    /* ------------------------------------------------------------------
     * 特色页的导航开关：写入隐藏字段 ENavHide（逗号分隔的 slug）
     * ---------------------------------------------------------------- */
    var navField = form.querySelector('[name="ENavHide"]');

    $$('[data-nav-toggle]').forEach(function (box) {
        box.addEventListener('change', function () {
            if (!navField) {
                return;
            }
            var hiddenSlugs = $$('[data-nav-toggle]').filter(function (el) {
                return !el.checked;
            }).map(function (el) {
                return el.getAttribute('data-nav-toggle');
            }).filter(Boolean);

            navField.value = hiddenSlugs.join(',');
            setDirty(true);
        });
    });

    $$('[data-ecod-reset]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var area = btn.getAttribute('data-ecod-reset');
            var meta = (DATA.panes || {})[area] || {};
            if (window.confirm('把「' + (meta.title || area) + '」的模块恢复为默认布局与参数？确认后仍需点「保存设置」才会生效。')) {
                resetArea(area);
            }
        });
    });

    var fileInput = $('[data-ecod-import]');
    if (fileInput) {
        fileInput.addEventListener('change', function () {
            if (fileInput.files && fileInput.files[0]) {
                importConfig(fileInput.files[0]);
                fileInput.value = '';
            }
        });
    }

    /* ------------------------------------------------------------------
     * 固定框架：侧栏与顶栏常驻，仅内容区滚动
     * ---------------------------------------------------------------- */
    var frameRaf = 0;

    function fitFrame() {
        frameRaf = 0;

        /* 窄屏是上下堆叠布局，交回整页滚动 */
        if (window.innerWidth <= 900) {
            admin.style.removeProperty('--ecod-admin-h');
            document.body.classList.remove('ecod-frame');
            return;
        }

        document.body.classList.add('ecod-frame');

        /* 整页被锁滚动后不应残留滚动位置，否则量到的 top 是负值 */
        if (window.pageYOffset > 0) {
            window.scrollTo(0, 0);
        }

        var top = admin.getBoundingClientRect().top;
        var gap = 14;                     /* 底部留白，别贴着视口底边 */
        var min = 420;                    /* 太矮时保底，避免内容区被压扁 */
        var h = Math.max(min, Math.round(window.innerHeight - top - gap));
        admin.style.setProperty('--ecod-admin-h', h + 'px');
    }

    function scheduleFrame() {
        if (frameRaf) {
            cancelAnimationFrame(frameRaf);
        }
        frameRaf = requestAnimationFrame(fitFrame);
    }

    window.addEventListener('resize', scheduleFrame);
    window.addEventListener('orientationchange', scheduleFrame);
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(scheduleFrame);
    }

    /* ------------------------------------------------------------------
     * 提交与离开保护
     * ---------------------------------------------------------------- */
    form.addEventListener('submit', function () {
        sync();
        setDirty(false);

        /* 保存中态：主按钮转 loading 并禁用，防止重复提交 */
        var save = $('.ecod-save');
        if (save) {
            save.disabled = true;
            save.classList.add('is-loading');
            save.dataset.label = save.textContent;
            save.textContent = '保存中…';
        }
    });

    /* ⌘/Ctrl + S 快捷保存 */
    document.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && !e.shiftKey && !e.altKey && String(e.key).toLowerCase() === 's') {
            var save = $('.ecod-save');
            if (save) {
                e.preventDefault();
                save.click();
            }
        }
    });

    window.addEventListener('beforeunload', function (e) {
        if (dirty) {
            e.preventDefault();
            e.returnValue = '';
            return '';
        }
    });

    /* 普通字段（含图片字段）改动计入未保存状态：与初始快照比对，
       得到修改计数，改回原值会自动清除 */
    ['input', 'change'].forEach(function (type) {
        document.addEventListener(type, function (e) {
            var el = e.target;
            if (!el || !el.closest) {
                return;
            }
            if (el.closest('[data-fields]') || el.closest('[data-ecod-pane]')) {
                refreshDirty();
            }
        }, true);
    });

    /* ------------------------------------------------------------------
     * 面板宽度自适应
     * 手机与「后台内容列被挤窄」都会让面板变窄，但视口宽度未必小于断点，
     * 所以按 #ecod-admin 实测宽度切换 is-narrow / is-tiny。
     * ---------------------------------------------------------------- */
    function watchWidth() {
        var apply = function () {
            var width = admin.clientWidth;
            admin.classList.toggle('is-narrow', width > 0 && width < 760);
            admin.classList.toggle('is-tiny', width > 0 && width < 520);
        };
        apply();
        if (window.ResizeObserver) {
            new window.ResizeObserver(apply).observe(admin);
        } else {
            window.addEventListener('resize', apply);
        }
    }

    /* ------------------------------------------------------------------
     * 启动
     * ---------------------------------------------------------------- */
    layout();
    enhanceFields();
    initConditions();
    enhanceMedia();
    enhanceColor();
    enhanceRange();
    enhancePreview();
    enhanceCardPreview();
    enhanceBackdropPreview();

    AREAS.forEach(function (area) {
        renderArea(area);
        bindArea(area);
    });
    updateCounts();
    sync();
    buildSearchIndex();
    watchWidth();

    /* 「/」聚焦搜索，输入框内不拦截 */
    document.addEventListener('keydown', function (e) {
        if (e.key !== '/' || e.metaKey || e.ctrlKey || e.altKey) {
            return;
        }
        var active = document.activeElement;
        if (active && /^(INPUT|TEXTAREA|SELECT)$/.test(active.tagName)) {
            return;
        }
        if (searchInput) {
            e.preventDefault();
            searchInput.focus();
        }
    });

    fitFrame();
    setTimeout(scheduleFrame, 150);

    var remembered = null;
    try {
        remembered = sessionStorage.getItem('ecod-admin-pane');
    } catch (err) { /* 忽略 */ }

    /* 支持 #home 这类锚点直达分组 */
    var hashPane = (location.hash || '').replace('#', '');
    if (hashPane && (DATA.panes || {})[hashPane]) {
        switchPane(hashPane);
    } else if (remembered) {
        switchPane(remembered);
    }

    /* ------------------------------------------------------------------
     * 字段级 dirty：与初始快照比对，驱动置底保存条 / 离开拦截 / ⌘S。
     * 快照在全部初始化（含控件增强的批量回填）之后采集，避免误报。
     * ---------------------------------------------------------------- */
    var initialFields = null;

    function fieldMap() {
        var map = {};
        /* 字段被 layout() 移出了 <form>（靠 form 属性关联），要从整个面板取 */
        $$('input[name], select[name], textarea[name]', admin).forEach(function (el) {
            if (el.type === 'file' || el.disabled) {
                return;
            }
            map[el.name] = el.type === 'checkbox' ? (el.checked ? '1' : '0') : String(el.value);
        });
        return map;
    }

    function refreshDirty() {
        if (!initialFields) {
            return;
        }
        var current = fieldMap();
        var changed = 0;
        Object.keys(current).forEach(function (name) {
            if (current[name] !== initialFields[name]) {
                changed++;
            }
        });
        setDirty(changed > 0, changed);
    }

    /* 提交中禁用按钮防止双击重复提交；成功与否由 Typecho 整页刷新反馈 */
    form.addEventListener('submit', function () {
        var btn = $('.ecod-save', admin);
        if (btn) {
            btn.disabled = true;
            btn.textContent = '保存中…';
        }
    });

    initialFields = fieldMap();
}());
