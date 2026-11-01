/**
 * Ecod Theme · 主题字段可视化面板
 *
 * 接管 themeFields() 声明的全部字段：
 * thumb / mode / side / pin / series / topic / excerpt / note / note_tags，
 * 原控件保留在 DOM 中（display:none）作为提交载体，本脚本只负责读写它们的值，
 * 因此保存逻辑与 Typecho 原生完全一致。
 *
 * Copyright (c) 2026 Ecod. All Rights Reserved. · https://ecod.cc
 */
(function () {
    'use strict';

    var CFG = window.ECOD_EDITOR || {};
    var SERIES_KEY = 'ecod:series';
    /* 普通文章最多 3 张封面；笔记模式放宽到「图集张数上限」（1–9） */
    var THUMB_BASE = 3;

    var NOTE_FALLBACK = { on: false, max: 9, ratio: '3:4', ratioCss: '3 / 4', coverText: false };

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

    function noteCfg() {
        var cfg = CFG.note || {};
        return {
            on: !!cfg.on,
            max: Math.max(1, Math.min(9, parseInt(cfg.max, 10) || NOTE_FALLBACK.max)),
            ratio: cfg.ratio || NOTE_FALLBACK.ratio,
            ratioCss: cfg.ratioCss || NOTE_FALLBACK.ratioCss,
            coverText: !!cfg.coverText
        };
    }

    function isNote() {
        return !!(els.note && String(els.note.value || '').trim().toLowerCase() === 'on');
    }

    function maxThumb() {
        return isNote() ? noteCfg().max : THUMB_BASE;
    }

    function currentTitle() {
        var input = document.getElementById('title');
        return input ? String(input.value || '').trim() : '';
    }

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
        /* 不裁剪：超出上限的图也保留在字段里，只在界面上标记「不输出」，
           避免从笔记模式切回普通模式时静默丢图 */
        return String(els.thumb.value || '').split(/[\r\n]+/).map(function (line) {
            return line.trim();
        }).filter(Boolean);
    }

    function writeThumbs(list) {
        els.thumb.value = list.filter(Boolean).join('\n');
    }

    function paintThumbs() {
        els.thumbBox.innerHTML = '';

        var limit = maxThumb();
        var note = isNote();

        thumbs.forEach(function (url, index) {
            var cell = make('div', 'ecod-thumb' + (note ? ' ecod-thumb--note' : ''));
            if (note) {
                cell.style.aspectRatio = noteCfg().ratioCss;
            }
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
                paintPreview();
            });

            cell.appendChild(img);
            cell.appendChild(del);

            /* 笔记模式：首图即封面，超出上限的标黄提示不会输出 */
            if (note && index === 0) {
                cell.appendChild(make('span', 'ecod-thumb__flag', '封面'));
            } else if (note && index >= limit) {
                cell.classList.add('is-over');
                cell.appendChild(make('span', 'ecod-thumb__flag ecod-thumb__flag--over', '不输出'));
                cell.title = '超出图集上限（' + limit + ' 张），不会出现在前台';
            }

            els.thumbBox.appendChild(cell);
        });

        if (thumbs.length < limit) {
            var add = make('div', 'ecod-thumb ecod-thumb--add' + (note ? ' ecod-thumb--note' : ''), '+ 添加');
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
                    thumbs = urls.slice();
                    writeThumbs(thumbs);
                    paintThumbs();
                    paintPreview();
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

        if (thumbs.length >= maxThumb()) {
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
                paintPreview();
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
     * 专题
     * ------------------------------------------------------------------ */

    function paintTopics() {
        if (!els.topicBox) {
            return;
        }

        var topics = (CFG.topics || []).slice();
        var current = String(els.topic ? els.topic.value || '' : '').trim();

        /* 当前值不在列表里（专题被停用 / 删除）时补一个，保证状态可见可清除 */
        var known = topics.some(function (t) {
            return t.slug === current;
        });
        if (current !== '' && !known) {
            topics.push({ slug: current, name: current, on: false });
        }

        els.topicBox.innerHTML = '';

        var none = make('button', 'ecod-topic' + (current === '' ? ' is-on' : ''));
        none.type = 'button';
        none.textContent = '不归入专题';
        none.addEventListener('click', function () {
            els.topic.value = '';
            els.topic.dispatchEvent(new Event('change', { bubbles: true }));
            paintTopics();
        });
        els.topicBox.appendChild(none);

        topics.forEach(function (topic) {
            var btn = make('button', 'ecod-topic' + (current === topic.slug ? ' is-on' : ''));
            btn.type = 'button';
            btn.textContent = topic.name + (topic.on ? '' : '（已停用）');
            btn.addEventListener('click', function () {
                els.topic.value = topic.slug;
                els.topic.dispatchEvent(new Event('change', { bubbles: true }));
                paintTopics();
            });
            els.topicBox.appendChild(btn);
        });
    }

    /* ------------------------------------------------------------------ *
     * 笔记模式（note / note_tags）
     * ------------------------------------------------------------------ */

    function paintNote() {
        if (!els.noteBox) {
            paintThumbs();
            return;
        }

        var cfg = noteCfg();
        var on = isNote();
        els.noteBox.innerHTML = '';

        var choices = [
            { value: '', label: '普通文章' },
            { value: 'on', label: '图文笔记' }
        ];

        choices.forEach(function (item) {
            var btn = make('button', 'ecod-note' + (on === (item.value === 'on') ? ' is-on' : ''));
            btn.type = 'button';
            btn.textContent = item.label;
            if (!cfg.on) {
                btn.disabled = true;
                btn.title = '先到「设置 → 文章页 → 图文笔记」开启总开关';
            }
            btn.addEventListener('click', function () {
                els.note.value = item.value;
                els.note.dispatchEvent(new Event('change', { bubbles: true }));
                paintNote();
            });
            els.noteBox.appendChild(btn);
        });

        /* 笔记模式下卡片模式与封面位置都不生效（详情页版式由笔记模板决定），直接收起避免误配 */
        if (els.modeSec) {
            els.modeSec.style.display = on ? 'none' : '';
        }
        if (els.sideSec) {
            els.sideSec.style.display = on ? 'none' : '';
        }
        if (els.thumbHint) {
            els.thumbHint.textContent = on
                ? '笔记图集 · 最多 ' + cfg.max + ' 张，首图为封面'
                : '最多 ' + THUMB_BASE + ' 张';
        }

        paintThumbs();
        paintPreview();
    }

    /* 封面预览：按「封面比例」画一个真实比例的框，首图 + 标题叠字所见即所得 */
    function paintPreview() {
        if (!els.notePrev) {
            return;
        }

        var cfg = noteCfg();
        els.notePrev.innerHTML = '';

        if (!isNote()) {
            els.notePrev.style.display = 'none';
            return;
        }
        els.notePrev.style.display = '';

        var box = make('div', 'ecod-noteprev__box');
        box.style.aspectRatio = cfg.ratioCss;

        if (thumbs.length) {
            var img = make('img');
            img.src = thumbs[0];
            img.alt = '封面预览';
            box.appendChild(img);
        } else {
            box.appendChild(make('span', 'ecod-noteprev__ph', '首图'));
        }

        var title = currentTitle();
        if (cfg.coverText && title) {
            box.appendChild(make('span', 'ecod-noteprev__cap', title));
        }

        els.notePrev.appendChild(box);
        els.notePrev.appendChild(make('div', 'ecod-noteprev__hd',
            '封面预览 · ' + cfg.ratio + (cfg.coverText ? ' · 标题叠在封面上' : '')));
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
        els.topic = fields.topic ? fields.topic.input : null;
        els.note = fields.note ? fields.note.input : null;
        els.noteTags = fields.note_tags ? fields.note_tags.input : null;

        /* 原控件全部隐藏，只留值参与提交 */
        Object.keys(fields).forEach(function (key) {
            fields[key].li.style.display = 'none';
        });

        var body = make('div', 'ecod-fields__body');

        /* 阅读形态（note）：笔记模式与普通文章互斥，放最上面先定调 */
        if (els.note) {
            var secNote = make('div', 'ecod-fields__sec ecod-fields__sec--note');
            secNote.appendChild(make('div', 'ecod-fields__label',
                '阅读形态<span class="ecod-fields__hint">图文笔记只在详情页生效，列表版式另设</span>'));
            els.noteBox = make('div', 'ecod-notes');
            secNote.appendChild(els.noteBox);

            if (!noteCfg().on) {
                secNote.appendChild(make('div', 'ecod-fields__warn',
                    '总开关未开启：到「设置 → 文章页 → 图文笔记」打开后才能选笔记模式。'));
            }

            /* 话题标签（note_tags）：手填优先，留空则用文章自身标签 */
            if (els.noteTags) {
                secNote.appendChild(make('div', 'ecod-fields__label',
                    '话题标签<span class="ecod-fields__hint">逗号或空格分隔，最多 8 个；留空用文章标签</span>'));
                els.noteTagsInput = make('input', 'ecod-input');
                els.noteTagsInput.type = 'text';
                els.noteTagsInput.placeholder = '例如：排版 主题设计 Typecho';
                els.noteTagsInput.value = els.noteTags.value || '';
                els.noteTagsInput.addEventListener('input', function () {
                    els.noteTags.value = els.noteTagsInput.value;
                });
                secNote.appendChild(els.noteTagsInput);
            }

            els.notePrev = make('div', 'ecod-noteprev');
            secNote.appendChild(els.notePrev);

            body.appendChild(secNote);
        }

        /* 封面位置（side：仅列表 / 大图模式生效） */
        if (els.side) {
            var secSide = make('div', 'ecod-fields__sec');
            els.sideSec = secSide;
            secSide.appendChild(make('div', 'ecod-fields__label',
                '封面位置<span class="ecod-fields__hint">列表 / 大图模式生效</span>'));
            els.sideBox = make('div', 'ecod-sides');
            secSide.appendChild(els.sideBox);
            body.appendChild(secSide);
        }

        /* 缩略图 */
        var secThumb = make('div', 'ecod-fields__sec');
        var thumbLabel = make('div', 'ecod-fields__label', '封面缩略图<span class="ecod-fields__hint">最多 3 张</span>');
        els.thumbHint = thumbLabel.querySelector('.ecod-fields__hint');
        secThumb.appendChild(thumbLabel);
        els.thumbBox = make('div', 'ecod-thumbs');
        secThumb.appendChild(els.thumbBox);
        body.appendChild(secThumb);

        /* 卡片模式 */
        var secMode = make('div', 'ecod-fields__sec');
        els.modeSec = secMode;
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

        /* 专题（单选 chips：来自「设置 → 专题」，与列表卡片角标、专题页共用） */
        if (els.topic && (CFG.topics || []).length) {
            var secTopic = make('div', 'ecod-fields__sec');
            secTopic.appendChild(make('div', 'ecod-fields__label',
                '专题<span class="ecod-fields__hint">单选；也可在「设置 → 专题」里挑稿加入</span>'));
            els.topicBox = make('div', 'ecod-topics');
            secTopic.appendChild(els.topicBox);
            body.appendChild(secTopic);
        }

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
        paintNote();
        paintModes();
        paintSide();
        paintPin();
        paintSeries();
        paintTopics();

        /* 标题改动实时反映到封面预览（笔记叠字模式下尤其直观） */
        var titleInput = document.getElementById('title');
        if (titleInput && els.notePrev) {
            titleInput.addEventListener('input', paintPreview);
        }
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
