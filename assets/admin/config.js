/**
 * Ecod Theme · 后台设置页交互
 *
 * 职责（模块卡片 / 抽屉参数 / EBlocks 序列化已于 v2.3 移除，
 * 模块布局统一在独立入口 widget.php 的模块管理台维护）：
 *  1. 把 Typecho 原生字段按 data-ecod-pane 搬到对应面板，并按
 *     DATA.sections 把字段行分发进面板内带标题的小节（保存逻辑完全不动）
 *  2. 字段增强：选择卡片 / 分段 / 开关 / 媒体 / 颜色 / 滑杆 / 预览 / 条件显隐
 *  3. 侧栏两层导航：分组手风琴（始终只展开一个）→ 面板
 *  4. 「开始」引导页：三步卡、任务清单（字段值 / 访问记录 / 操作标记）、快捷入口
 *  5. 「/」搜索设置项（含面板与小节）、分组切换记忆、字段级 dirty 与离开拦截
 *  6. 配置导出 / 导入（仅设置项；模块布局在模块管理台导出）
 *
 * Copyright (c) 2026 Ecod. All Rights Reserved. · https://ecod.cc
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

    function setDirty(value, count) {
        dirty = value;
        var mark = $('[data-dirty]');
        if (mark) {
            mark.hidden = !value;
            if (value) {
                mark.textContent = count > 0 ? count + ' 项未保存' : '有未保存的修改';
            }
        }
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
        }
    }

    /* ------------------------------------------------------------------
     * 面板内小节：按 DATA.sections（PHP 侧 $ecodSections）把字段行分发进
     * 带标题的小节容器，长面板才有层级可扫。没被任何小节引用的行
     * （隐藏字段等）沉到面板末尾；整节字段为空的小节不渲染标题。
     * ---------------------------------------------------------------- */
    function applySections() {
        var sections = DATA.sections || {};
        Object.keys(sections).forEach(function (pane) {
            var host = $('[data-fields="' + pane + '"]');
            if (!host) {
                return;
            }
            var spec = sections[pane] || [];
            var rows = $$('.typecho-option', host);
            if (!rows.length) {
                return;
            }

            var byName = {};
            spec.forEach(function (one, i) {
                (one.fields || []).forEach(function (name) {
                    byName[name] = i;
                });
            });

            var boxes = spec.map(function (one) {
                var box = document.createElement('div');
                box.className = 'ecod-sect';
                var hd = document.createElement('div');
                hd.className = 'ecod-sect__hd';
                hd.innerHTML = '<b class="ecod-sect__t">' + esc(one.title) + '</b>'
                    + (one.desc ? '<small class="ecod-sect__d">' + esc(one.desc) + '</small>' : '');
                var body = document.createElement('div');
                body.className = 'ecod-sect__bd';
                box.appendChild(hd);
                box.appendChild(body);
                return box;
            });

            var rest = document.createElement('div');
            rest.className = 'ecod-sect ecod-sect--rest';
            var restBody = document.createElement('div');
            restBody.className = 'ecod-sect__bd';
            rest.appendChild(restBody);

            rows.forEach(function (row) {
                var control = row.querySelector('input[name], select[name], textarea[name]');
                var idx = control ? byName[control.name] : undefined;
                var box = (idx === undefined || !boxes[idx]) ? rest : boxes[idx];
                box.querySelector('.ecod-sect__bd').appendChild(row);
            });

            host.innerHTML = '';
            boxes.forEach(function (box) {
                if (box.querySelector('.ecod-sect__bd').children.length) {
                    host.appendChild(box);
                }
            });
            if (restBody.children.length) {
                host.appendChild(rest);
            }
        });
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
        EEmojiStyle: { kind: 'segment' }
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
        paintStart();
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
                scrollElIntoScroller(row, 'smooth');
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
    /* 图标与 components/icons-data.php 同源（css.gg 填充风格，build/icons.mjs 生成） */
    var MD_ICON = '<svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor">'
        + '<path fill-rule="evenodd" clip-rule="evenodd" d="M7 7C5.34315 7 4 8.34315 4 10C4 11.6569 5.34315 13 7 13C8.65685 13 10 11.6569 10 10C10 8.34315 8.65685 7 7 7ZM6 10C6 9.44772 6.44772 9 7 9C7.55228 9 8 9.44772 8 10C8 10.5523 7.55228 11 7 11C6.44772 11 6 10.5523 6 10Z"/>'
        + '<path fill-rule="evenodd" clip-rule="evenodd" d="M3 3C1.34315 3 0 4.34315 0 6V18C0 19.6569 1.34315 21 3 21H21C22.6569 21 24 19.6569 24 18V6C24 4.34315 22.6569 3 21 3H3ZM21 5H3C2.44772 5 2 5.44772 2 6V18C2 18.5523 2.44772 19 3 19H7.31374L14.1924 12.1214C15.364 10.9498 17.2635 10.9498 18.435 12.1214L22 15.6863V6C22 5.44772 21.5523 5 21 5ZM21 19H10.1422L15.6066 13.5356C15.9971 13.145 16.6303 13.145 17.0208 13.5356L21.907 18.4217C21.7479 18.7633 21.4016 19 21 19Z"/></svg>';

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
     * 行表格字段（奖品池 / 商品列表 / 右键自定义项）
     *
     * 底层仍是原生 textarea：一行一项、字段用 | 分隔，后端解析零改动，
     * 旧数据与导出配置完全兼容；JS 不可用时退回原生 textarea 直接编辑。
     * 列定义（顺序 = 存储顺序）在前端登记，PHP 只输出 data-ecod-rows="<key>"。
     * ---------------------------------------------------------------- */
    var ROWSPEC = {
        lottery: {
            cols: [
                { k: 'name', t: '奖品名称', w: 2.0, ph: '谢谢参与' },
                { k: 'weight', t: '权重', w: .8, type: 'num', ph: '60' },
                { k: 'stock', t: '库存', w: .8, type: 'num', ph: '0 = 不限' },
                { k: 'type', t: '类型', w: 1.1, type: 'select', opts: [['none', '谢谢参与'], ['credit', '积分'], ['vip', '会员'], ['text', '实物']] },
                { k: 'value', t: '值', w: 1.6, ph: '数量 / 等级:天数 / 说明' }
            ],
            add: '添加奖品'
        },
        shop: {
            cols: [
                { k: 'name', t: '商品名称', w: 2.0, ph: '会员 7 天' },
                { k: 'price', t: '价格', w: .8, type: 'num', ph: '积分' },
                { k: 'type', t: '类型', w: 1.1, type: 'select', opts: [['vip', '会员'], ['post', '单篇解锁'], ['text', '待发货']] },
                { k: 'value', t: '值', w: 1.6, ph: '等级:天数 / 文章ID / 发货说明' },
                { k: 'stock', t: '库存', w: .8, type: 'num', ph: '0 = 不限' },
                { k: 'limit', t: '限购', w: .8, type: 'num', ph: '0 = 不限' }
            ],
            add: '添加商品'
        },
        ctx: {
            cols: [
                { k: 'label', t: '菜单文字', w: 1.4, ph: '关于本站' },
                { k: 'url', t: '链接地址', w: 2.6, ph: '/about 或 https://…' },
                { k: 'blank', t: '新窗口', w: .8, type: 'select', opts: [['0', '否'], ['1', '是']] }
            ],
            add: '添加菜单项'
        }
    };

    function enhanceRows() {
        $$('[data-ecod-rows]', admin).forEach(function (ta) {
            if (!(ta instanceof HTMLTextAreaElement) || ta.dataset.ecodRowsReady === '1') {
                return;
            }
            var spec = ROWSPEC[ta.getAttribute('data-ecod-rows')];
            if (!spec || !ta.parentNode) {
                return;
            }
            ta.dataset.ecodRowsReady = '1';

            var wrap = document.createElement('div');
            wrap.className = 'ecod-rows';

            var head = document.createElement('div');
            head.className = 'ecod-rows__head';
            head.innerHTML = spec.cols.map(function (c) {
                return '<span style="flex:' + c.w + '">' + esc(c.t) + '</span>';
            }).join('') + '<span class="ecod-rows__hdgap"></span>';
            wrap.appendChild(head);

            var list = document.createElement('div');
            list.className = 'ecod-rows__list';
            wrap.appendChild(list);

            var add = document.createElement('button');
            add.type = 'button';
            add.className = 'ecod-rows__add';
            add.textContent = '+ ' + spec.add;
            wrap.appendChild(add);

            /* 读：按行切，按列序对齐（尾部空字段保留，PHP explode 语义一致） */
            function readRows() {
                return String(ta.value || '').split(/\r?\n/).map(function (line) {
                    return line.replace(/\s+$/, '');
                }).filter(function (line) {
                    return line.trim() !== '';
                }).map(function (line) {
                    var parts = line.split('|');
                    var row = {};
                    spec.cols.forEach(function (c, i) {
                        row[c.k] = (parts[i] == null ? '' : String(parts[i])).trim();
                    });
                    return row;
                });
            }

            function writeRows(rows) {
                ta.value = rows.map(function (row) {
                    return spec.cols.map(function (c) {
                        return row[c.k] == null ? '' : String(row[c.k]);
                    }).join('|');
                }).join('\n');
                ta.dispatchEvent(new Event('input', { bubbles: true }));
                ta.dispatchEvent(new Event('change', { bubbles: true }));
            }

            function paint() {
                var rows = readRows();
                list.innerHTML = '';

                rows.forEach(function (row, index) {
                    var line = document.createElement('div');
                    line.className = 'ecod-rows__row';

                    spec.cols.forEach(function (c) {
                        var input;
                        if (c.type === 'select') {
                            input = document.createElement('select');
                            c.opts.forEach(function (pair) {
                                var opt = document.createElement('option');
                                opt.value = pair[0];
                                opt.textContent = pair[1];
                                input.appendChild(opt);
                            });
                            var known = c.opts.some(function (pair) { return pair[0] === row[c.k]; });
                            input.value = known ? row[c.k] : c.opts[0][0];
                        } else {
                            input = document.createElement('input');
                            input.type = c.type === 'num' ? 'text' : 'text';
                            input.inputMode = c.type === 'num' ? 'numeric' : 'text';
                            input.value = row[c.k] || '';
                            if (c.ph) {
                                input.placeholder = c.ph;
                            }
                        }

                        input.style.flex = c.w;
                        input.className = 'ecod-rows__input';
                        input.setAttribute('aria-label', c.t);
                        input.addEventListener('input', sync);
                        input.addEventListener('change', sync);

                        line.appendChild(input);
                    });

                    var del = document.createElement('button');
                    del.type = 'button';
                    del.className = 'ecod-rows__del';
                    del.title = '删除这一行';
                    del.setAttribute('aria-label', '删除这一行');
                    del.innerHTML = '&times;';
                    line.appendChild(del);

                    list.appendChild(line);
                });

                if (!rows.length) {
                    var empty = document.createElement('p');
                    empty.className = 'ecod-rows__empty';
                    empty.textContent = '还没有内容，点下面的按钮添加一行';
                    list.appendChild(empty);
                }
            }

            /* DOM → textarea：逐行回收当前输入值 */
            function sync() {
                var rows = Array.prototype.map.call(list.querySelectorAll('.ecod-rows__row'), function (line) {
                    var row = {};
                    var fields = line.querySelectorAll('.ecod-rows__input');
                    spec.cols.forEach(function (c, i) {
                        row[c.k] = fields[i] ? String(fields[i].value || '').trim() : '';
                    });
                    return row;
                });
                writeRows(rows);
            }

            list.addEventListener('click', function (e) {
                var del = e.target.closest ? e.target.closest('.ecod-rows__del') : null;
                if (!del) {
                    return;
                }
                var line = del.closest('.ecod-rows__row');
                if (line) {
                    line.parentNode.removeChild(line);
                    sync();
                    if (!list.querySelector('.ecod-rows__row')) {
                        paint();
                    }
                }
            });

            add.addEventListener('click', function () {
                var rows = readRows();
                var blank = {};
                spec.cols.forEach(function (c) {
                    blank[c.k] = c.type === 'select' ? c.opts[0][0] : '';
                });
                rows.push(blank);
                writeRows(rows);
                paint();
                var last = list.querySelector('.ecod-rows__row:last-child .ecod-rows__input');
                if (last) {
                    last.focus();
                }
            });

            ta.parentNode.insertBefore(wrap, ta);
            ta.classList.add('ecod-rows__raw');
            paint();
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
     * 主色实时同步：改主色时把色值覆盖到根容器 --ea-brand，
     * 滑块拇指、选中态等即时跟随（原骨架预览卡已移除）
     * ---------------------------------------------------------------- */
    function syncBrandLive() {
        var primary = $('[name="EPrimary"]', admin);
        if (!primary) {
            return;
        }
        function paint() {
            var v = normalizeHex(primary.value) || '#4f6ef2';
            admin.style.setProperty('--ea-brand', v);
        }
        primary.addEventListener('input', paint);
        primary.addEventListener('change', paint);
        paint();
    }


    /* ------------------------------------------------------------------
     * 分组与面板切换
     * 侧栏两层：分组（手风琴，始终只展开一个）→ 面板
     * ---------------------------------------------------------------- */
    function setGroupOpen(gkey) {
        $$('.ecod-admin__group', admin).forEach(function (group) {
            var open = group.getAttribute('data-group') === gkey;
            group.classList.toggle('is-open', open);
            var hd = $('.ecod-admin__grouphd', group);
            if (hd) {
                hd.classList.toggle('is-active', open);
                hd.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
        });
    }

    function switchPane(pane) {
        if (!pane || !(DATA.panes || {})[pane]) {
            return;
        }
        $$('.ecod-admin__navitem').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.dataset.pane === pane);
        });
        $$('[data-pane-panel]').forEach(function (panel) {
            panel.classList.toggle('is-active', panel.getAttribute('data-pane-panel') === pane);
        });
        var item = $('.ecod-admin__navitem[data-pane="' + pane + '"]', admin);
        setGroupOpen(item ? item.getAttribute('data-group') : null);
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
            /* 面板访问记录：「开始」页的任务清单用它判定完成态 */
            sessionStorage.setItem('ecod-seen-' + pane, '1');
        } catch (err) { /* 忽略隐私模式 */ }
        if (pane === 'start') {
            paintStart();
        }
    }

    /* 分组头：展开/收起；展开时点亮组内激活面板（没有则进第一个） */
    $$('.ecod-admin__grouphd', admin).forEach(function (hd) {
        hd.addEventListener('click', function () {
            var group = hd.closest('.ecod-admin__group');
            var gkey = group.getAttribute('data-group');
            var willOpen = !group.classList.contains('is-open');
            setGroupOpen(willOpen ? gkey : null);
            if (willOpen) {
                var active = $('.ecod-admin__navitem.is-active[data-group="' + gkey + '"]', admin)
                    || $('.ecod-admin__navitem[data-group="' + gkey + '"]', admin);
                if (active) {
                    switchPane(active.dataset.pane);
                }
            }
        });
    });

    $$('.ecod-admin__navitem').forEach(function (btn) {
        btn.addEventListener('click', function () {
            switchPane(btn.dataset.pane);
        });
    });

    /* ------------------------------------------------------------------
     * 「开始」引导页：任务清单
     * 完成态由三类信号判定：字段值 / 面板访问记录（sessionStorage）/
     * 操作标记（localStorage），勾掉一项就少一项焦虑。
     * ---------------------------------------------------------------- */
    var CHECK_SVG = '<svg viewBox="0 0 24 24" width="11" height="11" fill="currentColor">'
        + '<path d="M10.5858 13.4142L7.75735 10.5858L6.34314 12L10.5858 16.2427L17.6568 9.1716L16.2426 7.75739L10.5858 13.4142Z"/></svg>';
    var FLAG_KEY = 'ecod-admin-flags';

    function readFlags() {
        try {
            return JSON.parse(localStorage.getItem(FLAG_KEY) || '{}') || {};
        } catch (err) {
            return {};
        }
    }

    function writeFlag(id) {
        var flags = readFlags();
        flags[id] = 1;
        try {
            localStorage.setItem(FLAG_KEY, JSON.stringify(flags));
        } catch (err) { /* 忽略隐私模式 */ }
    }

    function seenPane(pane) {
        try {
            return sessionStorage.getItem('ecod-seen-' + pane) === '1';
        } catch (err) {
            return false;
        }
    }

    var START_TASKS = [
        { id: 'brand',    pane: 'brand',    label: '品牌门面',   desc: 'Logo、站点图标与主题主色', test: function (v) { return !!(v.ELogoImage || v.EFavicon); } },
        { id: 'layout',   pane: 'layout',   label: '列表版式',   desc: '卡片模式、侧栏位置与缩略图', test: function (v, seen) { return seen('layout'); } },
        /* 导航构建器空值时也会写一套默认空菜单：须解析 JSON 确认有真实菜单项 */
        { id: 'nav',      pane: 'nav',      label: '导航菜单',   desc: '搭建主导航与二级菜单', test: function (v) {
            try {
                var doc = JSON.parse(String(v.ENavSets || '{}') || '{}');
                return (doc.sets || []).some(function (set) {
                    return !!(set.items || []).length;
                });
            } catch (err) {
                return false;
            }
        } },
        { id: 'widgets',  pane: '',         ext: 'widgets',     label: '首页模块',   desc: '去模块管理台摆放首页与侧栏', test: function (v, seen, flags) { return !!flags.widgets; } },
        { id: 'comments', pane: 'comments', label: '评论互动',   desc: '评论服务、反垃圾与人机验证', test: function (v, seen) { return v.EComment === 'off' || seen('comments'); } },
        { id: 'seo',      pane: 'tools',    label: '收录与特色页', desc: 'sitemap、robots 与一键建页', test: function (v, seen, flags) { return seen('tools') || !!flags.tools; } },
        { id: 'backup',   pane: 'tools',    label: '配置备份',   desc: '保存快照或导出 JSON', test: function (v, seen, flags) { return !!flags.backup; } }
    ];

    function paintStart() {
        var host = $('[data-start-tasks]');
        if (!host) {
            return;
        }
        var vals = {};
        $$('input[name], select[name], textarea[name]', admin).forEach(function (el) {
            vals[el.name] = String(el.value == null ? '' : el.value);
        });
        var flags = readFlags();
        var widgetsUrl = ($('.ecod-start__qlink[data-start-flag="widgets"]', admin) || {}).getAttribute
            ? $('.ecod-start__qlink[data-start-flag="widgets"]', admin).getAttribute('href') : '#';

        var done = 0;
        host.innerHTML = START_TASKS.map(function (task) {
            var ok = !!task.test(vals, seenPane, flags);
            if (ok) {
                done++;
            }
            var open = task.ext
                ? '<a class="ecod-start__task' + (ok ? ' is-done' : '') + '" href="' + esc(widgetsUrl) + '" target="_blank" rel="noopener" data-start-flag="' + task.ext + '">'
                : '<button type="button" class="ecod-start__task' + (ok ? ' is-done' : '') + '" data-task-jump="' + esc(task.pane) + '">';
            return open
                + '<span class="ecod-start__tdot" aria-hidden="true">' + CHECK_SVG + '</span>'
                + '<span class="ecod-start__tmain"><b>' + esc(task.label) + '</b><small>' + esc(task.desc) + '</small></span>'
                + '<em>' + (ok ? '已完成' : '去完成 →') + '</em>'
                + (task.ext ? '</a>' : '</button>');
        }).join('');

        var progress = $('[data-start-progress]');
        if (progress) {
            progress.textContent = done + ' / ' + START_TASKS.length + ' 已完成';
        }
    }

    /* 引导页交互：任务卡 / 三步卡跳面板，快捷入口记操作标记 */
    var startBox = $('.ecod-start', admin);
    if (startBox) {
        startBox.addEventListener('click', function (e) {
            var flag = e.target.closest && e.target.closest('[data-start-flag]');
            if (flag) {
                writeFlag(flag.getAttribute('data-start-flag'));
                paintStart();
            }
            var jump = e.target.closest && e.target.closest('[data-start-jump], [data-task-jump]');
            if (jump) {
                switchPane(jump.getAttribute('data-start-jump') || jump.getAttribute('data-task-jump'));
            }
            /* 「导出配置备份」：原地下载 JSON 并勾掉任务，不跳面板 */
            var exp = e.target.closest && e.target.closest('[data-start-export]');
            if (exp) {
                exportConfig();
            }
        });
    }

    /* 模块管理台入口（面板内入口卡）与导出 / 快照动作同样推进任务清单 */
    document.addEventListener('click', function (e) {
        if (!e.target || !e.target.closest) {
            return;
        }
        if (e.target.closest('.ecod-wgt-entry')) {
            writeFlag('widgets');
            paintStart();
        }
        if (e.target.closest('[data-ecod-export], [data-ecod-snapshot-save]')) {
            writeFlag('backup');
            paintStart();
        }
        if (e.target.closest('[data-ecod-build]')) {
            writeFlag('tools');
            paintStart();
        }
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
                /* 所在小节标题一并入索引：搜「SMTP」能命中「发信服务器（SMTP）」小节 */
                var sect = opt.closest('.ecod-sect');
                var sectEl = sect ? sect.querySelector('.ecod-sect__t') : null;
                var sectTitle = sectEl ? sectEl.textContent.trim() : '';
                if (!label && !name) {
                    return;
                }
                searchIdx.push({
                    pane: pane,
                    kind: '设置',
                    label: label || name,
                    hint: sectTitle || paneTitle(pane),
                    text: (label + ' ' + name + ' ' + desc + ' ' + sectTitle + ' ' + paneTitle(pane)).toLowerCase(),
                    el: opt
                });
            });
        });

        /* 面板入口：搜「导航」「付费」这类词即使不记得字段名也能直达面板 */
        Object.keys(DATA.panes || {}).forEach(function (pane) {
            if (pane === 'start') {
                return;
            }
            searchIdx.push({
                pane: pane,
                kind: '面板',
                label: DATA.panes[pane].title,
                hint: DATA.panes[pane].desc,
                text: (DATA.panes[pane].title + ' ' + DATA.panes[pane].desc).toLowerCase(),
                el: $('[data-pane-panel="' + pane + '"]')
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
            var target = hit.el;
            if (!target) {
                return;
            }
            scrollElIntoScroller(target, 'smooth');
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
        var out = {
            app: 'ecod-theme',
            version: 3,
            exportedAt: new Date().toISOString(),
            options: {}
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

            setDirty(true);
        };
        reader.readAsText(file);
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
            admin.style.removeProperty('--ea-admin-h');
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
        admin.style.setProperty('--ea-admin-h', h + 'px');
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
     * 滚动收敛（固定框架模式）
     *
     * body.ecod-frame 把整页锁成 overflow:hidden（禁止滚轮滚动），但 overflow:hidden
     * 本身仍是可编程滚动容器。于是下面任何「滚到某元素可见」的行为——聚焦控件、
     * scrollIntoView（提示条跳转 / 搜索命中）——都会顺着祖先滚动链一次滚到
     * documentElement（窗口）上去。框架模式下窗口本就不该被滚动，
     * 结果就是：点进较低的设置项时内容整页向上弹、吸顶保存 header 随之被顶出屏，
     * 且滚轮被锁死后回不来（表现为「无法显示保存 header」）。
     *
     * 修复：聚焦（focusin，捕获阶段）时只把元素滚动进 admin 自身的 scroller
     * （.ecod-admin__body / 侧栏）内；若在固定框架模式下发现窗口被滚动到了，
     * 立即钉回 0，避免程序化滚动逃逸到 document scroller。
     * ---------------------------------------------------------------- */
    function adminScrollerOf(el) {
        if (!el || !el.closest) {
            return null;
        }
        var sc = el.closest('[data-admin-body]');
        if (!sc) {
            sc = el.closest('.ecod-admin__aside');
        }
        return sc || null;
    }

    function scrollElIntoScroller(el, behavior) {
        var sc = adminScrollerOf(el);
        if (!sc) {
            return;
        }
        var scRect = sc.getBoundingClientRect();
        var r = el.getBoundingClientRect();
        if (r.top >= scRect.top + 4 && r.bottom <= scRect.bottom - 4) {
            /* 已可见：不动 */
            return;
        }
        var top = r.top - scRect.top;
        var bottom = r.bottom - scRect.top;
        var maxScrollTop = Math.max(0, sc.scrollHeight - sc.clientHeight);
        var target = sc.scrollTop;
        if (top < 4) {
            target = Math.max(0, sc.scrollTop - top + 4);
        } else if (bottom > sc.clientHeight - 4) {
            target = Math.min(maxScrollTop, sc.scrollTop + (bottom - sc.clientHeight + 4));
        }
        sc.scrollTo({ top: target, behavior: behavior || 'auto' });
    }

    function confineFrameScroll() {
        if (!document.body.classList.contains('ecod-frame')) {
            return;
        }
        if (window.scrollY > 0) {
            window.scrollTo(0, 0);
        }
    }

    document.addEventListener('focusin', function (e) {
        if (!document.body.classList.contains('ecod-frame')) {
            return;
        }
        var el = e.target;
        if (el && el.closest) {
            scrollElIntoScroller(el, 'auto');
        }
        confineFrameScroll();
    }, true);

    /* scroll 兜底：固定框架下整页被锁死，任何会滚到 document scroller 的滚动
       （聚焦 / scrollIntoView / 锚点 / 脚本）都只准落在 admin scroller 内，
       检测到窗口被移动就钉回顶部，避免整页弹上来把吸顶保存 header 顶出屏 */
    document.addEventListener('scroll', function (e) {
        if (!document.body.classList.contains('ecod-frame')) {
            return;
        }
        if (window.scrollY > 0) {
            window.scrollTo(0, 0);
        }
    }, true);

    /* ------------------------------------------------------------------
     * 提交与离开保护
     * ---------------------------------------------------------------- */
    form.addEventListener('submit', function () {
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
    applySections();
    enhanceFields();
    initConditions();
    enhanceMedia();
    enhanceRows();
    enhanceColor();
    enhanceRange();
    syncBrandLive();

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
