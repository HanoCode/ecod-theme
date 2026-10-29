/**
 * Ecod Theme · 主题字段可视化面板
 *
 * 接管 themeFields() 声明的全部六个字段：
 * thumb / mode / side / pin / series / excerpt，
 * 原控件保留在 DOM 中（display:none）作为提交载体，本脚本只负责读写它们的值，
 * 因此保存逻辑与 Typecho 原生完全一致。
 */
(function () {
    'use strict';

    var CFG = window.ECOD_EDITOR || {};
    var MAX_THUMB = 3;
    var SERIES_KEY = 'ecod:series';

    var MODES = [
        { value: '', label: '跟随', view: 'default' },
        { value: 'default', label: '列表', view: 'default' },
        { value: 'feature', label: '大图', view: 'feature' },
        { value: 'gallery', label: '三图', view: 'gallery' },
        { value: 'bare', label: '无图', view: 'bare' },
        { value: 'grid', label: '网格', view: 'grid' },
        { value: 'title', label: '标题', view: 'title' }
    ];

    var SIDES = [
        { value: '', label: '跟随主题' },
        { value: 'left', label: '封面在左' },
        { value: 'right', label: '封面在右' }
    ];

    var els = {};
    var thumbs = [];

    function make(tag, cls, html) {
        var node = document.createElement(tag);
        if (cls) {
            node.className = cls;
        }
        if (html != null) {
            node.innerHTML = html;
        }
        return node;
    }

    function readSeries() {
        try {
            var raw = localStorage.getItem(SERIES_KEY);
            return raw ? JSON.parse(raw) : [];
        } catch (err) {
            return [];
        }
    }

    function rememberSeries(name) {
        name = String(name || '').trim();
        if (!name) {
            return;
        }
        var list = readSeries().filter(function (item) {
            return item !== name;
        });
        list.unshift(name);
        try {
            localStorage.setItem(SERIES_KEY, JSON.stringify(list.slice(0, 8)));
        } catch (err) {
            /* 忽略 */
        }
    }

    /* ------------------------------------------------------------------ *
     * 字段采集
     * ------------------------------------------------------------------ */

    function collect() {
        var box = document.getElementById('custom-field');
        if (!box) {
            return null;
        }

        var found = {};
        var items = box.querySelectorAll('ul.fields > li.field');

        Array.prototype.forEach.call(items, function (li) {
            var input = li.querySelector('[name^="fields["]');
            if (!input) {
                return;
            }

            var match = input.name.match(/^fields\[([^\]]+)\]$/);
            if (!match) {
                return;
            }

            found[match[1]] = { li: li, input: input };
        });

        return { box: box, fields: found };
    }

    /* ------------------------------------------------------------------ *
     * 缩略图
     * ------------------------------------------------------------------ */

    function parseThumbs() {
        return String(els.thumb.value || '').split(/[\r\n]+/).map(function (line) {
            return line.trim();
        }).filter(Boolean).slice(0, MAX_THUMB);
    }

    function writeThumbs(list) {
        els.thumb.value = list.slice(0, MAX_THUMB).join('\n');
    }

    function paintThumbs() {
        els.thumbBox.innerHTML = '';

        thumbs.forEach(function (url, index) {
            var cell = make('div', 'ecod-thumb');
            var img = make('img');
            img.src = url;
            img.alt = '缩略图 ' + (index + 1);
            img.loading = 'lazy';

            var del = make('button', 'ecod-thumb__del', '×');
            del.type = 'button';
            del.title = '移除';
            del.addEventListener('click', function () {
                thumbs.splice(index, 1);
                writeThumbs(thumbs);
                paintThumbs();
            });

            cell.appendChild(img);
            cell.appendChild(del);
            els.thumbBox.appendChild(cell);
        });

        if (thumbs.length < MAX_THUMB) {
            var add = make('div', 'ecod-thumb ecod-thumb--add', '+ 添加');
            add.title = '上传图片';
            add.addEventListener('click', pickImage);
            els.thumbBox.appendChild(add);
        }
    }

    function pickImage() {
        /* 优先走媒体库选图器：既能选已有图片，也能当场上传或填 URL */
        if (window.EcodMedia) {
            window.EcodMedia.pick({
                multiple: true,
                title: '选择封面缩略图',
                value: thumbs.slice(),
                onPick: function (urls) {
                    thumbs = urls.slice(0, MAX_THUMB);
                    writeThumbs(thumbs);
                    paintThumbs();
                }
            });
            return;
        }

        var input = make('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.multiple = thumbs.length === 0;
        input.style.display = 'none';
        document.body.appendChild(input);

        input.addEventListener('change', function () {
            Array.prototype.forEach.call(input.files || [], function (file) {
                upload(file);
            });
            document.body.removeChild(input);
        });

        input.click();
    }

    function upload(file) {
        if (!/^image\//i.test(file.type)) {
            return;
        }

        if (thumbs.length >= MAX_THUMB) {
            return;
        }

        var url = CFG.upload || '/action/upload';
        if (CFG.cid) {
            url += (url.indexOf('?') > -1 ? '&' : '?') + 'cid=' + encodeURIComponent(CFG.cid);
        }

        var placeholder = make('div', 'ecod-thumb');
        placeholder.innerHTML = '<span style="position:absolute;inset:0;display:flex;align-items:center;'
            + 'justify-content:center;font-size:11px;color:#8b93a8">上传中…</span>';
        els.thumbBox.insertBefore(placeholder, els.thumbBox.querySelector('.ecod-thumb--add'));

        var form = new FormData();
        form.append('file', file);

        fetch(url, { method: 'POST', body: form, credentials: 'same-origin' })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.json();
            })
            .then(function (data) {
                placeholder.parentNode.removeChild(placeholder);
                if (!data || !data[1]) {
                    return;
                }
                thumbs.push(data[1].url);
                writeThumbs(thumbs);
                paintThumbs();
            })
            .catch(function () {
                if (placeholder.parentNode) {
                    placeholder.parentNode.removeChild(placeholder);
                }
            });
    }

    /* ------------------------------------------------------------------ *
     * 卡片模式
     * ------------------------------------------------------------------ */

    function paintModes() {
        els.modeBox.innerHTML = '';

        MODES.forEach(function (mode) {
            var btn = make('button', 'ecod-mode' + (els.mode.value === mode.value ? ' is-on' : ''));
            btn.type = 'button';
            btn.innerHTML = '<span class="ecod-mode__view" data-v="' + mode.view + '"><i></i><i></i><i></i></span>'
                + '<span>' + mode.label + '</span>';

            btn.addEventListener('click', function () {
                els.mode.value = mode.value;
                els.mode.dispatchEvent(new Event('change', { bubbles: true }));
                paintModes();
            });

            els.modeBox.appendChild(btn);
        });
    }

    /* ------------------------------------------------------------------ *
     * 封面位置
     * ------------------------------------------------------------------ */

    function paintSide() {
        if (!els.sideBox) {
            return;
        }

        var current = String(els.side.value || '').trim();
        els.sideBox.innerHTML = '';

        SIDES.forEach(function (side) {
            var btn = make('button', 'ecod-side' + (current === side.value ? ' is-on' : ''));
            btn.type = 'button';
            btn.textContent = side.label;

            btn.addEventListener('click', function () {
                els.side.value = side.value;
                els.side.dispatchEvent(new Event('change', { bubbles: true }));
                paintSide();
            });

            els.sideBox.appendChild(btn);
        });
    }

    /* ------------------------------------------------------------------ *
     * 置顶
     * ------------------------------------------------------------------ */

    function paintPin() {
        els.pinSwitch.classList.toggle('is-on', String(els.pin.value || '').trim() !== '');
    }

    /* ------------------------------------------------------------------ *
     * 系列
     * ------------------------------------------------------------------ */

    function paintSeries() {
        var list = readSeries().filter(function (item) {
            return item !== String(els.series.value || '').trim();
        }).slice(0, 5);

        els.seriesTags.innerHTML = '';

        list.forEach(function (name) {
            var tag = make('button', 'ecod-series__tag', name);
            tag.type = 'button';
            tag.addEventListener('click', function () {
                els.series.value = name;
                els.series.dispatchEvent(new Event('change', { bubbles: true }));
                paintSeries();
            });
            els.seriesTags.appendChild(tag);
        });
    }

    /* ------------------------------------------------------------------ *
     * 构建
     * ------------------------------------------------------------------ */

    function build(scope) {
        var box = scope.box;
        var fields = scope.fields;

        els.thumb = fields.thumb.input;
        els.mode = fields.mode.input;
        els.pin = fields.pin.input;
        els.series = fields.series.input;
        els.side = fields.side ? fields.side.input : null;
        els.excerpt = fields.excerpt ? fields.excerpt.input : null;

        /* 原控件全部隐藏，只留值参与提交 */
        Object.keys(fields).forEach(function (key) {
            fields[key].li.style.display = 'none';
        });

        var body = make('div', 'ecod-fields__body');

        /* 封面位置（side：仅列表 / 大图模式生效） */
        if (els.side) {
            var secSide = make('div', 'ecod-fields__sec');
            secSide.appendChild(make('div', 'ecod-fields__label',
                '封面位置<span class="ecod-fields__hint">列表 / 大图模式生效</span>'));
            els.sideBox = make('div', 'ecod-sides');
            secSide.appendChild(els.sideBox);
            body.appendChild(secSide);
        }

        /* 缩略图 */
        var secThumb = make('div', 'ecod-fields__sec');
        secThumb.appendChild(make('div', 'ecod-fields__label',
            '封面缩略图<span class="ecod-fields__hint">最多 3 张</span>'));
        els.thumbBox = make('div', 'ecod-thumbs');
        secThumb.appendChild(els.thumbBox);
        body.appendChild(secThumb);

        /* 卡片模式 */
        var secMode = make('div', 'ecod-fields__sec');
        secMode.appendChild(make('div', 'ecod-fields__label', '卡片模式'));
        els.modeBox = make('div', 'ecod-modes');
        secMode.appendChild(els.modeBox);
        body.appendChild(secMode);

        /* 置顶 */
        var secPin = make('div', 'ecod-fields__sec');
        els.pinSwitch = make('label', 'ecod-switch',
            '<span>置顶这篇文章</span><span class="ecod-switch__track"></span>');
        els.pinSwitch.addEventListener('click', function (e) {
            e.preventDefault();
            var on = String(els.pin.value || '').trim() !== '';
            els.pin.value = on ? '' : '1';
            els.pin.dispatchEvent(new Event('change', { bubbles: true }));
            paintPin();
        });
        secPin.appendChild(els.pinSwitch);
        body.appendChild(secPin);

        /* 系列 */
        var secSeries = make('div', 'ecod-fields__sec');
        secSeries.appendChild(make('div', 'ecod-fields__label',
            '所属系列<span class="ecod-fields__hint">可留空</span>'));
        els.seriesInput = make('input', 'ecod-input');
        els.seriesInput.type = 'text';
        els.seriesInput.placeholder = '例如：Typecho 主题开发';
        els.seriesInput.value = els.series.value || '';
        els.seriesInput.addEventListener('input', function () {
            els.series.value = els.seriesInput.value;
            paintSeries();
        });
        els.seriesInput.addEventListener('blur', function () {
            rememberSeries(els.seriesInput.value);
            paintSeries();
        });
        secSeries.appendChild(els.seriesInput);
        els.seriesTags = make('div', 'ecod-series');
        secSeries.appendChild(els.seriesTags);
        body.appendChild(secSeries);

        /* 自定义摘要（excerpt：列表 / 搜索 / SEO 描述优先使用） */
        if (els.excerpt) {
            var secEx = make('div', 'ecod-fields__sec');
            secEx.appendChild(make('div', 'ecod-fields__label',
                '自定义摘要<span class="ecod-fields__hint">留空则自动截取正文</span>'));
            els.excerptInput = make('textarea', 'ecod-input ecod-textarea');
            els.excerptInput.rows = 3;
            els.excerptInput.placeholder = '填写后列表、搜索结果与 SEO 描述优先使用这段文字';
            els.excerptInput.value = els.excerpt.value || '';
            els.excerptInput.addEventListener('input', function () {
                els.excerpt.value = els.excerptInput.value;
            });
            secEx.appendChild(els.excerptInput);
            body.appendChild(secEx);
        }

        box.classList.add('ecod-fields');
        box.setAttribute('open', 'open');

        var list = box.querySelector('ul.fields');
        list.parentNode.insertBefore(body, list);

        thumbs = parseThumbs();
        paintThumbs();
        paintModes();
        paintSide();
        paintPin();
        paintSeries();
    }

    function init() {
        var scope = collect();
        if (!scope) {
            return;
        }

        var fields = scope.fields;
        if (!fields.thumb || !fields.mode || !fields.pin || !fields.series) {
            return;
        }

        build(scope);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
