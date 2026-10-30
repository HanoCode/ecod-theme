/**
 * Ecod Theme · 后台文章编辑器
 *
 * 无构建、无框架依赖。以原生 textarea 为唯一数据源：
 *  - 叠一层语法高亮（overlay），文本内容与 textarea 逐字符对齐；
 *  - 右侧 iframe 实时预览，Markdown 用 Typecho 自带 HyperDown 渲染，与前台一致；
 *  - 图片支持粘贴 / 拖拽上传，复用 /action/upload；
 *  - 本地草稿、字数统计、大纲定位、全屏专注。
 *
 * Copyright (c) 2026 Ecod. All Rights Reserved. · https://ecod.cc
 */
(function () {
    'use strict';

    var CFG = window.ECOD_EDITOR || {};
    var IS_MD = CFG.mode === 'markdown';
    var ta = null;
    var shell = null;
    var preview = null;
    var hl = null;
    var state = {
        full: false,
        previewOn: true,
        mview: 'edit',
        ratio: 50,
        uploading: 0
    };

    /* ------------------------------------------------------------------ *
     * 基础工具
     * ------------------------------------------------------------------ */

    function $(sel, ctx) {
        return (ctx || document).querySelector(sel);
    }

    function $$(sel, ctx) {
        return Array.prototype.slice.call((ctx || document).querySelectorAll(sel));
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

    var ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

    function esc(str) {
        return String(str).replace(/[&<>"']/g, function (ch) {
            return ESC[ch];
        });
    }

    function debounce(fn, wait) {
        var timer = null;
        return function () {
            var args = arguments;
            var self = this;
            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(self, args);
            }, wait);
        };
    }

    function toast(text, type) {
        var box = $('.ec-ed__toast');
        if (!box) {
            box = make('div', 'ec-ed__toast');
            shell.appendChild(box);
        }
        var item = make('div', 'ec-ed__toast-item' + (type ? ' is-' + type : ''));
        item.textContent = text;
        box.appendChild(item);
        setTimeout(function () {
            item.classList.add('is-out');
        }, 2100);
        setTimeout(function () {
            if (item.parentNode) {
                item.parentNode.removeChild(item);
            }
        }, 2500);
    }

    var store = {
        get: function (key, fallback) {
            try {
                var raw = localStorage.getItem(key);
                return raw ? JSON.parse(raw) : fallback;
            } catch (err) {
                return fallback;
            }
        },
        set: function (key, val) {
            try {
                localStorage.setItem(key, JSON.stringify(val));
            } catch (err) {
                /* 隐私模式下静默失败 */
            }
        },
        del: function (key) {
            try {
                localStorage.removeItem(key);
            } catch (err) {
                /* 忽略 */
            }
        }
    };

    /* ------------------------------------------------------------------ *
     * 语法高亮
     * ------------------------------------------------------------------ */

    function inlineTokens(text) {
        var out = '';
        var i = 0;
        var n = text.length;

        function wrap(cls, chunk) {
            return '<span class="' + cls + '">' + esc(chunk) + '</span>';
        }

        while (i < n) {
            var ch = text.charAt(i);
            var close;

            /* 行内代码 `code` */
            if (ch === '`') {
                close = text.indexOf('`', i + 1);
                if (close > i) {
                    out += wrap('tk-code', text.slice(i, close + 1));
                    i = close + 1;
                    continue;
                }
            }

            /* 图片 ![alt](url) */
            if (ch === '!' && text.charAt(i + 1) === '[') {
                var imgEnd = text.indexOf(')', i);
                var imgMid = text.indexOf('](', i);
                if (imgMid > i && imgEnd > imgMid) {
                    out += wrap('tk-img', text.slice(i, imgMid + 2))
                        + wrap('tk-url', text.slice(imgMid + 2, imgEnd))
                        + esc(')');
                    i = imgEnd + 1;
                    continue;
                }
            }

            /* 链接 [text](url) */
            if (ch === '[') {
                var linkEnd = text.indexOf(')', i);
                var linkMid = text.indexOf('](', i);
                if (linkMid > i && linkEnd > linkMid) {
                    out += wrap('tk-bracket', '[')
                        + wrap('tk-link', text.slice(i + 1, linkMid))
                        + wrap('tk-bracket', '](')
                        + wrap('tk-url', text.slice(linkMid + 2, linkEnd))
                        + esc(')');
                    i = linkEnd + 1;
                    continue;
                }
            }

            /* 粗体 **text** */
            if (ch === '*' && text.charAt(i + 1) === '*') {
                close = text.indexOf('**', i + 2);
                if (close > i) {
                    out += '<span class="tk-strong">' + esc(text.slice(i, close + 2)) + '</span>';
                    i = close + 2;
                    continue;
                }
            }

            /* 斜体 *text* */
            if (ch === '*') {
                close = text.indexOf('*', i + 1);
                if (close > i) {
                    out += '<span class="tk-em">' + esc(text.slice(i, close + 1)) + '</span>';
                    i = close + 1;
                    continue;
                }
            }

            /* 删除线 ~~text~~ */
            if (ch === '~' && text.charAt(i + 1) === '~') {
                close = text.indexOf('~~', i + 2);
                if (close > i) {
                    out += '<span class="tk-del">' + esc(text.slice(i, close + 2)) + '</span>';
                    i = close + 2;
                    continue;
                }
            }

            /* 摘要分隔 <!--more--> */
            if (ch === '<' && text.substr(i, 11) === '<!--more-->') {
                out += wrap('tk-more', '<!--more-->');
                i += 11;
                continue;
            }

            out += esc(ch);
            i++;
        }

        return out;
    }

    function highlightLine(line) {
        if (line === '') {
            return '';
        }

        var m;

        /* 标题 */
        if ((m = line.match(/^(\s*)(#{1,6})(\s+)(.*)$/))) {
            return esc(m[1]) + '<span class="tk-hash">' + m[2] + '</span>' + m[3]
                + '<span class="tk-h' + m[2].length + '">' + inlineTokens(m[4]) + '</span>';
        }

        /* 引用 */
        if ((m = line.match(/^(\s*)(&gt;|>)+(\s?)(.*)$/))) {
            return '<span class="tk-quote">' + esc(m[1] + m[2] + m[3]) + '</span>'
                + '<span class="tk-quote-text">' + inlineTokens(m[4]) + '</span>';
        }

        /* 分割线 */
        if (/^\s*(-{3,}|\*{3,}|_{3,})\s*$/.test(line)) {
            return '<span class="tk-hr">' + esc(line) + '</span>';
        }

        /* 列表 */
        if ((m = line.match(/^(\s*)([-*+]|\d+\.)(\s+)(.*)$/))) {
            return esc(m[1]) + '<span class="tk-mark">' + esc(m[2]) + '</span>' + m[3]
                + inlineTokens(m[4]);
        }

        /* 表格 */
        if (/^\s*\|/.test(line)) {
            return '<span class="tk-table">' + inlineTokens(line) + '</span>';
        }

        /* 裸 HTML 标签行 */
        if (/^\s*<[a-zA-Z\/!]/.test(line)) {
            return '<span class="tk-html">' + esc(line) + '</span>';
        }

        return inlineTokens(line);
    }

    function highlight(src) {
        if (!IS_MD) {
            return esc(src);
        }

        var lines = src.split('\n');
        var out = [];
        var inFence = false;
        var fenceChar = '';

        for (var i = 0; i < lines.length; i++) {
            var line = lines[i];
            var fence = line.match(/^\s*(```+|~~~+)/);

            if (fence) {
                if (!inFence) {
                    inFence = true;
                    fenceChar = fence[1].charAt(0);
                    out.push('<span class="tk-fence">' + esc(line) + '</span>');
                    continue;
                }
                if (line.indexOf(fenceChar) > -1) {
                    inFence = false;
                    out.push('<span class="tk-fence">' + esc(line) + '</span>');
                    continue;
                }
            }

            if (inFence) {
                out.push('<span class="tk-code-block">' + esc(line) + '</span>');
                continue;
            }

            out.push(highlightLine(line));
        }

        var html = out.join('\n');

        /* 结尾换行在 pre-wrap 下会被吞掉，补一个零宽字符占位 */
        if (/\n$/.test(src)) {
            html += ' ';
        }

        return html;
    }

    /* ------------------------------------------------------------------ *
     * 预览
     * ------------------------------------------------------------------ */

    var mdConverter = null;

    function getConverter() {
        if (!mdConverter && typeof window.HyperDown !== 'undefined') {
            mdConverter = new window.HyperDown();
            mdConverter.enableHtml(true);
            mdConverter.enableLine(true);
        }
        return mdConverter;
    }

    function renderMarkdown(src) {
        var conv = getConverter();

        if (!conv) {
            return '<p>' + esc(src).replace(/\n/g, '<br>') + '</p>';
        }

        var html = conv.makeHtml(src);
        html = html.replace(/<p>\s*<!--more-->\s*<\/p>/g, '<div class="ecod-more">摘要分割线</div>');
        html = html.replace(/<!--more-->/g, '<div class="ecod-more">摘要分割线</div>');

        if (window.DOMPurify) {
            html = window.DOMPurify.sanitize(html, { USE_PROFILES: { html: true } });
        }

        return html;
    }

    function PreviewBox(frame) {
        this.frame = frame;
        this.doc = null;
    }

    PreviewBox.prototype.boot = function () {
        var doc = this.frame.contentDocument;
        if (!doc) {
            return;
        }
        doc.open();
        doc.write('<!DOCTYPE html><html><head><meta charset="utf-8">'
            + '<link rel="stylesheet" href="' + esc(CFG.frame || '') + '"></head><body></body></html>');
        doc.close();
        this.doc = this.frame.contentDocument;
    };

    PreviewBox.prototype.update = function (html, empty) {
        if (!this.doc || !this.doc.body) {
            this.boot();
            if (!this.doc) {
                return;
            }
        }

        var scroll = this.doc.documentElement.scrollTop || 0;
        this.doc.body.innerHTML = empty
            ? '<div class="ecod-empty"><b>还没有内容</b><span>在左侧开始写作，这里会同步显示排版效果</span></div>'
            : html;

        var max = this.doc.documentElement.scrollHeight - this.doc.documentElement.clientHeight;
        this.doc.documentElement.scrollTop = Math.min(scroll, Math.max(0, max));
    };

    PreviewBox.prototype.follow = function (ratio) {
        if (!this.doc) {
            return;
        }
        var max = this.doc.documentElement.scrollHeight - this.doc.documentElement.clientHeight;
        if (max > 0) {
            this.doc.documentElement.scrollTop = max * ratio;
        }
    };

    /* ------------------------------------------------------------------ *
     * 图标
     * ------------------------------------------------------------------ */

    var ICON = {
        bold: '<path d="M7 4.5h5.6a3.4 3.4 0 0 1 0 6.8H7zM7 11.3h6.4a3.6 3.6 0 0 1 0 7.2H7z"/>',
        italic: '<path d="M10 4.5h6M8 19.5h6M14.5 4.5 9.5 19.5"/>',
        strike: '<path d="M5 11.8h14M8.4 6.6A3.4 3.4 0 0 1 11.6 4.5h2a3.3 3.3 0 0 1 2.6 1.3M15.6 17.4a3.4 3.4 0 0 1-3.2 2.1h-2a3.3 3.3 0 0 1-2.6-1.3"/>',
        code: '<path d="M9 7.2 4.2 12 9 16.8M15 7.2 19.8 12 15 16.8"/>',
        quote: '<path d="M6 6.5h3.2v4.2a3.6 3.6 0 0 1-3.2 3.6M14.8 6.5H18v4.2a3.6 3.6 0 0 1-3.2 3.6"/>',
        h2: '<text x="12" y="17.5" font-size="13" font-weight="700" text-anchor="middle" fill="currentColor" stroke="none" font-family="inherit">H2</text>',
        h3: '<text x="12" y="17.5" font-size="13" font-weight="700" text-anchor="middle" fill="currentColor" stroke="none" font-family="inherit">H3</text>',
        ul: '<circle cx="6" cy="7.5" r="1.3"/><circle cx="6" cy="12" r="1.3"/><circle cx="6" cy="16.5" r="1.3"/><path d="M10.5 7.5h9M10.5 12h9M10.5 16.5h9"/>',
        ol: '<path d="M4.6 6.6h1.8v3.2M4.6 12h1.8a1.4 1.4 0 0 1 0 2.8H4.6l1.8 2.6H4.6M10.5 7.5h9M10.5 12h9M10.5 16.5h9"/>',
        link: '<path d="M10.6 13.4a3.8 3.8 0 0 0 5.4 0l2.6-2.6a3.8 3.8 0 0 0-5.4-5.4l-1.4 1.4"/><path d="M13.4 10.6a3.8 3.8 0 0 0-5.4 0l-2.6 2.6a3.8 3.8 0 0 0 5.4 5.4l1.4-1.4"/>',
        image: '<rect x="3.2" y="4.8" width="17.6" height="14.4" rx="2.4"/><circle cx="8.6" cy="10" r="1.5"/><path d="M20.8 15.6 16 10.8l-8.4 8.4"/>',
        table: '<rect x="3.2" y="4.8" width="17.6" height="14.4" rx="2.4"/><path d="M3.2 9.6h17.6M9.4 9.6v9.6M15 9.6v9.6"/>',
        hr: '<path d="M3.4 12h17.2"/>',
        more: '<path d="M3.2 12h4.2l1.8-4.4 2.4 8.8 1.8-4.4h7.4"/>',
        eye: '<path d="M2.2 12S6 5.9 12 5.9 21.8 12 21.8 12 18 18.1 12 18.1 2.2 12 2.2 12z"/><circle cx="12" cy="12" r="2.6"/>',
        full: '<path d="M8.4 3.2H3.2v5.2M15.6 3.2h5.2v5.2M20.8 15.6v5.2h-5.2M3.2 15.6v5.2h5.2"/>',
        outline: '<path d="M3.4 6h17.2M3.4 12h10.6M3.4 18h6.6"/>',
        undo: '<path d="M4 9.6h9.6a5.4 5.4 0 0 1 0 10.8H8.4"/><path d="M7.6 5.6 3.6 9.6l4 4"/>',
        redo: '<path d="M20 9.6h-9.6a5.4 5.4 0 0 0 0 10.8h5.2"/><path d="M16.4 5.6l4 4-4 4"/>',
        sc: '<path d="M8.4 4.5H6.2A1.7 1.7 0 0 0 4.5 6.2v3.2c0 1-.7 1.8-1.6 1.8v1.6c.9 0 1.6.8 1.6 1.8v3.2a1.7 1.7 0 0 0 1.7 1.7h2.2M15.6 4.5h2.2a1.7 1.7 0 0 1 1.7 1.7v3.2c0 1 .7 1.8 1.6 1.8v1.6c-.9 0-1.6.8-1.6 1.8v3.2a1.7 1.7 0 0 1-1.7 1.7h-2.2"/>',
        note: '<circle cx="12" cy="12" r="8.6"/><path d="M12 8v.2M12 11.4V16"/>',
        alert: '<path d="M12 4.2 21 19.4H3z"/><path d="M12 10v4.2M12 16.9v.2"/>',
        message: '<path d="M4 6.8A2.8 2.8 0 0 1 6.8 4h10.4A2.8 2.8 0 0 1 20 6.8v6.4a2.8 2.8 0 0 1-2.8 2.8H9.8l-3.9 3.2c-.6.5-1.9.1-1.9-.8z"/>',
        callout: '<path d="M8.4 4.6H6.2A1.7 1.7 0 0 0 4.5 6.3v2.3M15.6 4.6h2.2a1.7 1.7 0 0 1 1.7 1.7v2.3M8.4 19.4H6.2a1.7 1.7 0 0 1-1.7-1.7v-2.3M15.6 19.4h2.2a1.7 1.7 0 0 0 1.7-1.7v-2.3"/><path d="M12 8.6v4M12 15.2v.2"/>',
        anote: '<circle cx="12" cy="7.8" r="3.4"/><path d="M5.4 19.6a6.8 6.8 0 0 1 13.2 0"/>',
        mtitle: '<path d="M5 6.5h14M12 6.5V17M8.6 17h6.8"/>',
        copy: '<rect x="9" y="9" width="10.6" height="10.6" rx="2.2"/><path d="M5.4 14.8V6.8a2.4 2.4 0 0 1 2.4-2.4h8"/>',
        progress: '<rect x="3.4" y="9" width="17.2" height="6" rx="3"/><path d="M7.4 12h6.2"/>',
        abtn: '<rect x="3.6" y="8" width="12.6" height="8" rx="4"/><path d="M16.6 12h4.2M18.4 9.4 20.8 12l-2.4 2.6"/>',
        dotted: '<path d="M4.4 12h.2M9.4 12h.2M14.4 12h.2M19.4 12h.2"/>',
        lamp: '<circle cx="12" cy="12" r="4.8"/><circle cx="12" cy="12" r="1.2"/>',
        task: '<rect x="4" y="4" width="16" height="16" rx="4.2"/><path d="m8.4 12.2 2.6 2.6 4.8-5.4"/>',
        mark: '<path d="M4.4 7h15.2M4.4 11.6h8.4"/><rect x="4.4" y="14.8" width="15.2" height="4.2" rx="1.6"/>',
        kbd: '<rect x="3.4" y="7" width="17.2" height="10" rx="2.4"/><path d="M7 10.4h.2M11 10.4h.2M15 10.4h.2M8 13.8h8"/>',
        fold: '<rect x="4" y="4.6" width="16" height="14.8" rx="2.4"/><path d="m8.8 10.8 3.2 3.2 3.2-3.2"/>',
        tabs: '<path d="M4 8.4V6.8A1.8 1.8 0 0 1 5.8 5h4l1.8 2.2h6.6A1.8 1.8 0 0 1 20 9v8.2a1.8 1.8 0 0 1-1.8 1.8H5.8A1.8 1.8 0 0 1 4 17.2z"/><path d="M8 13h8"/>',
        timeline: '<path d="M7 4.6v14.8"/><circle cx="7" cy="8" r="1.2"/><circle cx="7" cy="12.4" r="1.2"/><circle cx="7" cy="16.8" r="1.2"/><path d="M10.8 8H20M10.8 12.4H20M10.8 16.8H20"/>',
        cards: '<rect x="7.4" y="4.6" width="12.6" height="10.8" rx="2"/><path d="M4.2 8.4v9a2.2 2.2 0 0 0 2.2 2.2h9.4"/>',
        grid: '<rect x="4" y="4" width="7" height="7" rx="1.8"/><rect x="13" y="4" width="7" height="7" rx="1.8"/><rect x="4" y="13" width="7" height="7" rx="1.8"/><rect x="13" y="13" width="7" height="7" rx="1.8"/>',
        card: '<rect x="4" y="5.4" width="16" height="13.2" rx="2.2"/><path d="M4 9.8h16M7.8 13.6h6.4"/>',
        download: '<path d="M12 4.4v9.6M8.2 10.6l3.8 3.8 3.8-3.8"/><path d="M4.6 15.4v2.4a2 2 0 0 0 2 2h10.8a2 2 0 0 0 2-2v-2.4"/>',
        cloud: '<path d="M7 18.6h10.2a3.8 3.8 0 0 0 .6-7.6 5.4 5.4 0 0 0-10.5-1.2A4.2 4.2 0 0 0 7 18.6z"/>',
        article: '<path d="M6.4 3.6h7.8l4.6 4.6v11a1.8 1.8 0 0 1-1.8 1.8H6.4a1.8 1.8 0 0 1-1.8-1.8V5.4a1.8 1.8 0 0 1 1.8-1.8z"/><path d="M14 3.8V8.4h4.6M8.4 12.6h7M8.4 16h7"/>',
        github: '<circle cx="6.4" cy="6.4" r="2.2"/><circle cx="6.4" cy="17.6" r="2.2"/><circle cx="17.6" cy="6.4" r="2.2"/><path d="M6.4 8.6v6.8M17.6 8.6v1.2a4.2 4.2 0 0 1-4.2 4.2H8.6"/>',
        reply: '<rect x="5" y="10.4" width="14" height="9.6" rx="2.2"/><path d="M8.2 10.4V7.8a3.8 3.8 0 0 1 7.6 0v2.6M12 14v2.4"/>',
        bilibili: '<rect x="3.6" y="7" width="16.8" height="12" rx="2.6"/><path d="m8.4 3.2 3 3.4M15.6 3.2l-3 3.4M10 11.4v4.2M14 11.4v4.2"/>',
        video: '<rect x="3.6" y="5" width="16.8" height="14" rx="2.6"/><path d="m10.2 9.2 4.8 2.8-4.8 2.8z"/>',
        mp3: '<path d="M9.6 17.4V6.2l8.6-1.8v10.4"/><circle cx="7.4" cy="17.6" r="2.4"/><circle cx="16" cy="15" r="2.4"/>',
        music: '<path d="M4 6.6h9M4 11h9M4 15.4h5"/><circle cx="16.4" cy="15.8" r="2.4"/><path d="M18.8 15.8V8l2.6.9"/>',
        gallery: '<rect x="3.4" y="4.6" width="17.2" height="14.8" rx="2.4"/><circle cx="8.2" cy="9.4" r="1.4"/><path d="M4.6 16.8 9.6 12l3 3 2.4-2.2 4.4 4"/><path d="M17.4 4.2v5.2M15 6.8h4.8"/>',
        tooltip: '<path d="M4 6.2A2.2 2.2 0 0 1 6.2 4h11.6A2.2 2.2 0 0 1 20 6.2v7.6a2.2 2.2 0 0 1-2.2 2.2h-4.4l-3.2 3v-3H6.2A2.2 2.2 0 0 1 4 13.8z"/><path d="M12 7.6v.2M12 10.4v2"/>',
        badge: '<path d="M8.6 3.6h6.8l4.2 4.2v6.8l-4.2 4.2H8.6L4.4 14.6V7.8z"/><path d="M9.6 12h4.8"/>',
        spoiler: '<path d="M2.6 12S6.4 6.2 12 6.2 21.4 12 21.4 12 17.6 17.8 12 17.8 2.6 12 2.6 12z"/><circle cx="12" cy="12" r="2.6"/><path d="m4.2 19.8 15.6-15.6"/>',
        emoji: '<circle cx="12" cy="12" r="8.6"/><path d="M8.6 14.4a4.4 4.4 0 0 0 6.8 0"/><path d="M9.2 9.6v.2M14.8 9.6v.2"/>'
    };

    function icon(name) {
        return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" '
            + 'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            + (ICON[name] || '') + '</svg>';
    }

    /* ------------------------------------------------------------------ *
     * 工具栏定义
     * ------------------------------------------------------------------ */

    function toolsFor() {
        if (IS_MD) {
            return [
                { act: 'undo', icon: 'undo', title: '撤销 Ctrl+Z' },
                { act: 'redo', icon: 'redo', title: '重做 Ctrl+Shift+Z' },
                { sep: true },
                { act: 'bold', icon: 'bold', title: '加粗 Ctrl+B' },
                { act: 'italic', icon: 'italic', title: '斜体 Ctrl+I' },
                { act: 'strike', icon: 'strike', title: '删除线' },
                { act: 'code', icon: 'code', title: '行内代码' },
                { sep: true },
                { act: 'h2', icon: 'h2', title: '二级标题' },
                { act: 'h3', icon: 'h3', title: '三级标题' },
                { act: 'quote', icon: 'quote', title: '引用' },
                { sep: true },
                { act: 'ul', icon: 'ul', title: '无序列表' },
                { act: 'ol', icon: 'ol', title: '有序列表' },
                { act: 'link', icon: 'link', title: '链接 Ctrl+K' },
                { act: 'image', icon: 'image', title: '图片' },
                { act: 'table', icon: 'table', title: '表格' },
                { act: 'hr', icon: 'hr', title: '分割线' },
                { act: 'emoji', icon: 'emoji', title: '插入表情' },
                { act: 'more', icon: 'more', title: '摘要分割 <!--more-->' }
            ];
        }

        return [
            { act: 'undo', icon: 'undo', title: '撤销 Ctrl+Z' },
            { act: 'redo', icon: 'redo', title: '重做 Ctrl+Shift+Z' },
            { sep: true },
            { act: 'bold', icon: 'bold', title: '加粗 Ctrl+B' },
            { act: 'italic', icon: 'italic', title: '斜体 Ctrl+I' },
            { act: 'code', icon: 'code', title: '行内代码' },
            { sep: true },
            { act: 'h2', icon: 'h2', title: '二级标题' },
            { act: 'h3', icon: 'h3', title: '三级标题' },
            { act: 'quote', icon: 'quote', title: '引用' },
            { sep: true },
            { act: 'ul', icon: 'ul', title: '无序列表' },
            { act: 'ol', icon: 'ol', title: '有序列表' },
            { act: 'link', icon: 'link', title: '链接 Ctrl+K' },
            { act: 'image', icon: 'image', title: '图片' },
            { act: 'table', icon: 'table', title: '表格' },
            { act: 'hr', icon: 'hr', title: '分割线' },
            { act: 'emoji', icon: 'emoji', title: '插入表情' },
            { act: 'more', icon: 'more', title: '摘要分割' }
        ];
    }

    /* ------------------------------------------------------------------ *
     * 短代码菜单（与 core/content/shortcode.php + shortcode-extra.php 语法一致）
     * 模板中的 | 为插入后的光标/选区占位
     * ------------------------------------------------------------------ */

    var SHORTCODES = [
        { name: '提示与强调', items: [
            { tag: 'note', name: '提示块', hint: 'info / tip / warn / danger', tpl: '[note type="info" title="提示"]|内容[/note]' },
            { tag: 'alert', name: '彩色提示框', hint: 'success / warning / error / dark', tpl: '[alert type="success" title="标题"]|内容[/alert]' },
            { tag: 'message', name: '消息条', hint: 'info / success / warning / error', tpl: '[message type="info"]|内容[/message]' },
            { tag: 'callout', name: '强调框', hint: 'color="#7c4dff" title=', tpl: '[callout color="#7c4dff" title="标题"]|内容[/callout]' },
            { tag: 'anote', name: '作者注', hint: 'author= href= icon=', tpl: '[anote author="Ecod"]|内容[/anote]' }
        ]},
        { name: '排版元素', items: [
            { tag: 'mtitle', name: '小节标题', hint: 'title=', tpl: '[mtitle title="|小节标题"]' },
            { tag: 'copy', name: '一键复制块', hint: 'label=', tpl: '[copy label="安装命令"]|内容[/copy]' },
            { tag: 'progress', name: '进度条', hint: 'percentage= color= label=', tpl: '[progress percentage="60" label="|进度"]' },
            { tag: 'abtn', name: '按钮', hint: 'href= icon= style=ghost', tpl: '[abtn href="https://" icon="link" color="primary"]|按钮文字[/abtn]' },
            { tag: 'dotted', name: '虚线分隔', hint: '[dotted]', tpl: '[dotted]' },
            { tag: 'lamp', name: '状态灯', hint: 'green / red / yellow / …', tpl: '[lamp green]', inline: true },
            { tag: 'task', name: '任务勾选框', hint: '[task done] 已完成', tpl: '[task done]', inline: true },
            { tag: 'mark', name: '高亮标记', hint: '[mark]…[/mark]', tpl: '[mark]|高亮[/mark]', inline: true },
            { tag: 'kbd', name: '键盘按键', hint: '[kbd]…[/kbd]', tpl: '[kbd]|Ctrl[/kbd]', inline: true },
            { tag: 'tooltip', name: '悬停提示', hint: 'text= place=bottom', tpl: '[tooltip text="|解释文字"]正文[/tooltip]', inline: true },
            { tag: 'badge', name: '行内标签', hint: '颜色 / solid / outline', tpl: '[badge green]|NEW[/badge]', inline: true },
            { tag: 'spoiler', name: '剧透遮罩', hint: '点击展开', tpl: '[spoiler]|剧透内容[/spoiler]', inline: true }
        ]},
        { name: '折叠 / 选项卡 / 时间线', items: [
            { tag: 'fold', name: '折叠块', hint: 'title=', tpl: '[fold title="|点击展开"]内容[/fold]' },
            { tag: 'tabs', name: '选项卡', hint: '[tab title=]', tpl: '[tabs]\n[tab title="|标签一"]内容[/tab]\n[tab title="标签二"]内容[/tab]\n[/tabs]' },
            { tag: 'timeline', name: '时间线', hint: '每行「时间 | 内容」', tpl: '[timeline]|2026-01 | 发布 v1.0\n2026-06 | 发布 v2.0[/timeline]' }
        ]},
        { name: '卡片与网格', items: [
            { tag: 'cards', name: '卡片组', hint: 'column=2~4 title=', tpl: '[cards column="2" title="|相关资源"]\n[card title="使用文档" desc="说明" icon="file" href="https://"]\n[card title="更新日志" desc="变更" icon="list" href="https://"]\n[/cards]' },
            { tag: 'grid', name: '卡片网格', hint: 'column= gap=', tpl: '[grid column="3"]\n[card title="|列一"]\n[card title="列二"]\n[card title="列三"]\n[/grid]' },
            { tag: 'card', name: '单张信息卡', hint: 'title= desc= icon= href= tag=', tpl: '[card title="|卡片标题" desc="描述" icon="file" href="https://" tag="标签"]' }
        ]},
        { name: '下载与网盘', items: [
            { tag: 'download', name: '下载卡片', hint: '自动识别 15 种网盘', tpl: '[download url="https://" name="|文件名" size=""]说明[/download]' },
            { tag: 'cloud', name: '网盘卡（多行）', hint: '每行「链接 | 提取码 | 说明」', tpl: '[cloud]|https://pan.baidu.com/s/xxx | 提取码 | 说明文字\nhttps://pan.quark.cn/s/xxx | | 夸克网盘[/cloud]' }
        ]},
        { name: '内容引用', items: [
            { tag: 'posts', name: '文章列表', hint: 'category= tag= limit= order=', tpl: '[posts title="|相关阅读" limit="5"]' },
            { tag: 'article', name: '引用文章卡', hint: 'id= 文章 cid', tpl: '[article id="|1"]' },
            { tag: 'github', name: 'GitHub 仓库卡', hint: 'repo= desc=', tpl: '[github repo="|owner/repo" desc="仓库描述"]' },
            { tag: 'reply', name: '评论可见', hint: '[reply]…[/reply]', tpl: '[reply]|评论后可见的内容[/reply]' },
            { tag: 'hide', name: '登录 / 等级可见', hint: 'login / comment / level=', tpl: '[hide type="login"]|需要隐藏的内容[/hide]' },
            { tag: 'hide', name: '密码可见', hint: 'type=password pw=', tpl: '[hide type="password" pw="|1234"]加密内容[/hide]' }
        ]},
        { name: '媒体嵌入', items: [
            { tag: 'gallery', name: '相册图集', hint: '多选图片，自动接灯箱', tpl: '[gallery column="3"]\nhttps:// | 说明\n[/gallery]', pick: 'gallery' },
            { tag: 'bilibili', name: 'B 站视频', hint: 'bvid= page=', tpl: '[bilibili bvid="|BV1xx411c7mD" page="1"]' },
            { tag: 'video', name: '视频', hint: 'url= poster=', tpl: '[video url="|https://" poster=""]' },
            { tag: 'mp3', name: '音频（单曲）', hint: 'url= name= cover=', tpl: '[mp3 url="|https://" name="歌名"]' },
            { tag: 'music', name: '播放列表', hint: '每行「链接 | 歌名 | 封面」', tpl: '[music title="|本期歌单"]\nhttps://a.mp3 | 歌名一\nhttps://b.mp3 | 歌名二\n[/music]' }
        ]}
    ];

    function insertSnippet(tpl, inline) {
        var start = ta.selectionStart;
        var end = ta.selectionEnd;
        var sel = ta.value.slice(start, end);
        var hasSel = sel.length > 0;
        var at = tpl.indexOf('|');
        var text = at > -1 ? tpl.replace('|', hasSel ? sel : '') : tpl;
        var val = ta.value;
        var needBefore = '';
        var needAfter = '';

        if (!inline) {
            if (start > 0 && val.charAt(start - 1) !== '\n') {
                needBefore = '\n\n';
            }
            needAfter = '\n';
        }

        var payload = needBefore + text + needAfter;
        replaceRange(start, end, payload);

        /* 选中占位内容，方便直接输入覆盖 */
        if (at > -1) {
            var selStart = start + needBefore.length + at;
            var selEnd = selStart;

            if (hasSel) {
                selEnd = selStart + sel.length;
            } else {
                /* 占位终点：下一个闭标签 [/ 或属性行内的引号 */
                var rest = text.slice(selStart);
                var closeIdx = rest.indexOf('[/');
                var quoteIdx = rest.indexOf('"');
                var stop = rest.length - needAfter.length;

                if (closeIdx > -1 && (quoteIdx < 0 || closeIdx < quoteIdx)) {
                    stop = closeIdx;
                } else if (quoteIdx > -1) {
                    stop = quoteIdx;
                }
                selEnd = selStart + stop;
            }

            ta.setSelectionRange(selStart, selEnd);
        }
        sync();
    }

    /* ---------------- 短代码图标行 ---------------- */

    function buildScBar(row) {
        SHORTCODES.forEach(function (group, gi) {
            if (gi > 0) {
                row.appendChild(make('span', 'ec-ed__sep'));
            }

            group.items.forEach(function (it) {
                var btn = make('button', 'ec-ed__btn ec-ed__sbtn', icon(it.tag));
                btn.type = 'button';
                btn.setAttribute('data-tip', it.name);
                if (it.hint) {
                    btn.setAttribute('data-tip-sub', it.hint);
                }
                btn.setAttribute('aria-label', it.name);
                btn.addEventListener('click', function () {
                    if (it.pick === 'gallery') {
                        insertGallery();
                        return;
                    }
                    insertSnippet(it.tpl, it.inline);
                });
                row.appendChild(btn);
            });
        });
    }

    /* ---------------- 自定义 tooltip ---------------- */

    var tip = null;
    var tipTimer = null;

    function showTip(target) {
        if (!tip) {
            tip = make('div', 'ec-ed__tip');
            tip.setAttribute('role', 'tooltip');
            document.body.appendChild(tip);
        }

        var main = target.getAttribute('data-tip') || '';
        var sub = target.getAttribute('data-tip-sub') || '';
        tip.innerHTML = esc(main) + (sub ? '<i>' + esc(sub) + '</i>' : '');
        tip.classList.add('is-on');

        var r = target.getBoundingClientRect();
        var tw = tip.offsetWidth;
        var th = tip.offsetHeight;
        var left = Math.min(Math.max(8, r.left + r.width / 2 - tw / 2), window.innerWidth - tw - 8);
        var top = r.bottom + 9;
        var place = 'is-below';

        if (top + th > window.innerHeight - 8) {
            place = 'is-above';
            top = r.top - th - 9;
        }

        tip.className = 'ec-ed__tip is-on ' + place;
        tip.style.left = left + 'px';
        tip.style.top = top + 'px';
    }

    function hideTip() {
        if (tip) {
            tip.classList.remove('is-on');
        }
    }

    function bindTips(root) {
        root.addEventListener('mouseover', function (e) {
            var t = e.target.closest('[data-tip]');
            if (!t) {
                return;
            }
            if (tipTimer) {
                clearTimeout(tipTimer);
            }
            tipTimer = setTimeout(function () {
                showTip(t);
            }, 380);
        });

        root.addEventListener('mouseout', function (e) {
            var t = e.target.closest('[data-tip]');
            if (!t) {
                return;
            }
            if (e.relatedTarget && t.contains(e.relatedTarget)) {
                return;
            }
            if (tipTimer) {
                clearTimeout(tipTimer);
                tipTimer = null;
            }
            hideTip();
        });

        root.addEventListener('mousedown', function () {
            if (tipTimer) {
                clearTimeout(tipTimer);
                tipTimer = null;
            }
            hideTip();
        }, true);
    }

    /* ------------------------------------------------------------------ *
     * 编辑动作
     * ------------------------------------------------------------------ */

    function replaceRange(start, end, text, selStart, selEnd) {
        ta.focus();
        ta.setSelectionRange(start, end);

        /* execCommand 能保留原生撤销栈；失败时退回到直接赋值 */
        var ok = false;
        try {
            ok = document.execCommand('insertText', false, text);
        } catch (err) {
            ok = false;
        }

        if (!ok) {
            var val = ta.value;
            ta.value = val.slice(0, start) + text + val.slice(end);
        }

        var from = selStart == null ? start + text.length : selStart;
        var to = selEnd == null ? from : selEnd;
        ta.setSelectionRange(from, to);
        sync();
    }

    function wrapInline(before, after, placeholder) {
        var start = ta.selectionStart;
        var end = ta.selectionEnd;
        var sel = ta.value.slice(start, end);
        var body = sel || placeholder || '';

        replaceRange(start, end, before + body + after, start + before.length, start + before.length + body.length);
    }

    function lineBounds() {
        var val = ta.value;
        var start = ta.selectionStart;
        var end = ta.selectionEnd;
        var lineStart = val.lastIndexOf('\n', start - 1) + 1;
        var lineEnd = val.indexOf('\n', end);
        if (lineEnd < 0) {
            lineEnd = val.length;
        }
        return { start: lineStart, end: lineEnd, text: val.slice(lineStart, lineEnd) };
    }

    function eachLine(fn) {
        var range = lineBounds();
        var lines = range.text.split('\n');
        lines = lines.map(fn);
        var text = lines.join('\n');
        replaceRange(range.start, range.end, text, range.start, range.start + text.length);
    }

    function togglePrefix(prefix, altPrefix) {
        eachLine(function (line) {
            if (line.indexOf(prefix) === 0) {
                return line.slice(prefix.length);
            }
            if (altPrefix) {
                for (var i = 0; i < altPrefix.length; i++) {
                    if (line.indexOf(altPrefix[i]) === 0) {
                        return prefix + line.slice(altPrefix[i].length);
                    }
                }
            }
            return prefix + line;
        });
    }

    function heading(level) {
        eachLine(function (line) {
            var stripped = line.replace(/^#{1,6}\s+/, '');
            return new Array(level + 1).join('#') + ' ' + stripped;
        });
    }

    function insertBlock(text) {
        var start = ta.selectionStart;
        var val = ta.value;
        var needBefore = start > 0 && val.charAt(start - 1) !== '\n' ? '\n\n' : '';
        var needAfter = '\n';
        var payload = needBefore + text + needAfter;
        replaceRange(start, ta.selectionEnd, payload, start + payload.length - needAfter.length);
    }

    var ACTIONS = {
        undo: function () { document.execCommand('undo'); sync(); },
        redo: function () { document.execCommand('redo'); sync(); },
        bold: function () { IS_MD ? wrapInline('**', '**', '加粗文字') : wrapInline('<strong>', '</strong>', '加粗文字'); },
        italic: function () { IS_MD ? wrapInline('*', '*', '斜体文字') : wrapInline('<em>', '</em>', '斜体文字'); },
        strike: function () { IS_MD ? wrapInline('~~', '~~', '删除文字') : wrapInline('<del>', '</del>', '删除文字'); },
        code: function () { IS_MD ? wrapInline('`', '`', '代码') : wrapInline('<code>', '</code>', '代码'); },
        h2: function () {
            if (IS_MD) {
                heading(2);
            } else {
                wrapInline('<h2>', '</h2>', '标题');
            }
        },
        h3: function () {
            if (IS_MD) {
                heading(3);
            } else {
                wrapInline('<h3>', '</h3>', '标题');
            }
        },
        quote: function () { IS_MD ? togglePrefix('> ') : wrapInline('<blockquote>', '</blockquote>', '引用内容'); },
        ul: function () { IS_MD ? togglePrefix('- ') : wrapInline('<ul>\n  <li>', '</li>\n</ul>', '列表项'); },
        ol: function () { IS_MD ? togglePrefix('1. ') : wrapInline('<ol>\n  <li>', '</li>\n</ol>', '列表项'); },
        link: function () {
            var sel = ta.value.slice(ta.selectionStart, ta.selectionEnd) || '链接文字';
            IS_MD ? wrapInline('[', '](https://)', sel) : wrapInline('<a href="https://">', '</a>', sel);
        },
        image: function () { pickImage(); },
        table: function () {
            if (IS_MD) {
                insertBlock('| 列一 | 列二 |\n| --- | --- |\n| 内容 | 内容 |');
            } else {
                insertBlock('<table>\n  <thead><tr><th>列一</th><th>列二</th></tr></thead>\n  <tbody><tr><td>内容</td><td>内容</td></tr></tbody>\n</table>');
            }
        },
        hr: function () { insertBlock(IS_MD ? '---' : '<hr>'); },
        more: function () { insertBlock('<!--more-->'); },
        emoji: function (btn) { toggleEmoji(btn); }
    };

    /* ------------------------------------------------------------------ *
     * 图片上传
     * ------------------------------------------------------------------ */

    function uploadUrl() {
        var url = CFG.upload || '/action/upload';
        if (CFG.cid) {
            url += (url.indexOf('?') > -1 ? '&' : '?') + 'cid=' + encodeURIComponent(CFG.cid);
        }
        return url;
    }

    function uploadImage(file, done) {
        if (!/^image\//i.test(file.type)) {
            toast('只支持图片文件', 'warn');
            return;
        }

        if (file.size > 8 * 1024 * 1024) {
            toast('图片超过 8MB，上传可能失败', 'warn');
        }

        state.uploading++;
        paintStatus();

        /* 优先走主题媒体库上传（WebP 转换 + 对象存储驱动）；
         * ECOD_MEDIA.token 不存在时回退 Typecho 原生 /action/upload */
        var media = window.ECOD_MEDIA;
        var viaTheme = !!(media && media.token && media.api);
        var form = new FormData();
        form.append('file', file);
        var url;

        if (viaTheme) {
            form.append('_', media.token);
            if (CFG.cid) {
                form.append('cid', CFG.cid);
            }
            url = media.api + (media.api.indexOf('?') > -1 ? '&' : '?') + 'action=upload';
        } else {
            url = uploadUrl();
        }

        fetch(url, {
            method: 'POST',
            body: form,
            credentials: 'same-origin'
        }).then(function (res) {
            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }
            return res.json();
        }).then(function (data) {
            state.uploading--;
            paintStatus();
            if (viaTheme) {
                if (!data || !data.ok || !data.url) {
                    toast((data && data.error) || '上传失败', 'error');
                    return;
                }
                done({ url: data.url, title: data.title || file.name });
                return;
            }
            if (!data || !data[1]) {
                toast('上传失败', 'error');
                return;
            }
            done(data[1]);
        }).catch(function () {
            state.uploading--;
            paintStatus();
            toast('上传失败，请检查登录状态或附件设置', 'error');
        });
    }

    function insertImage(att) {
        var alt = (att.title || '图片').replace(/[[\]]/g, '');
        var snippet = IS_MD
            ? '![' + alt + '](' + att.url + ')'
            : '<img src="' + att.url + '" alt="' + esc(alt) + '">';
        var start = ta.selectionStart;
        var val = ta.value;
        var before = start > 0 && val.charAt(start - 1) !== '\n' ? '\n' : '';
        replaceRange(start, ta.selectionEnd, before + snippet + '\n');
    }

    function pickImage() {
        /* 优先用媒体库选图器：已有图片、上传、填 URL 三条路都在一个弹层里 */
        if (window.EcodMedia) {
            window.EcodMedia.pick({
                multiple: true,
                title: '插入图片',
                onPick: function (urls) {
                    urls.forEach(function (url) {
                        var name = '';
                        try {
                            name = decodeURIComponent(String(url).split('?')[0].split('/').pop() || '');
                        } catch (err) {
                            name = '';
                        }
                        insertImage({ url: url, title: name.replace(/\.[a-z0-9]+$/i, '') || '图片' });
                    });
                }
            });
            return;
        }

        var input = make('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.style.display = 'none';
        document.body.appendChild(input);
        input.addEventListener('change', function () {
            if (input.files && input.files[0]) {
                uploadImage(input.files[0], insertImage);
            }
            document.body.removeChild(input);
        });
        input.click();
    }

    /* ------------------------------------------------------------------ *
     * 相册图集
     * ------------------------------------------------------------------ */

    /**
     * 从媒体库多选图片，按「地址 | 说明」逐行生成 [gallery]
     */
    function insertGallery() {
        if (window.EcodMedia) {
            window.EcodMedia.pick({
                multiple: true,
                title: '选择相册图片（可多选）',
                onPick: function (urls) {
                    if (!urls || !urls.length) {
                        return;
                    }
                    var lines = urls.map(function (url) {
                        var name = '';
                        try {
                            name = decodeURIComponent(String(url).split('?')[0].split('/').pop() || '');
                        } catch (err) {
                            name = '';
                        }
                        name = name.replace(/\.[a-z0-9]+$/i, '');
                        return name ? url + ' | ' + name : url;
                    });
                    var col = urls.length >= 5 ? 3 : (urls.length <= 2 ? 2 : 3);
                    insertBlock('[gallery column="' + col + '" ratio="1/1"]\n'
                        + lines.join('\n')
                        + '\n[/gallery]');
                }
            });
            return;
        }

        insertBlock('[gallery column="3"]\nhttps://example.com/a.jpg | 说明\nhttps://example.com/b.jpg\n[/gallery]');
    }

    /* ------------------------------------------------------------------ *
     * 表情选择器
     * ------------------------------------------------------------------ */

    var EMOJIS = Array.isArray(CFG.emojis) ? CFG.emojis : [];
    var emojiBox = null;
    var emojiOpen = false;

    function buildEmojiBox() {
        var box = make('div', 'ec-ed__emoji');
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-label', '插入表情');

        var head = make('div', 'ec-ed__emoji-head', '点击插入到光标处');
        box.appendChild(head);

        var grid = make('div', 'ec-ed__emoji-grid');
        EMOJIS.forEach(function (it) {
            var btn = make('button', 'ec-ed__emoji-item');
            btn.type = 'button';
            btn.setAttribute('data-emoji', it.code);
            btn.setAttribute('title', it.label + '  ' + it.code);
            btn.setAttribute('aria-label', it.label);

            var img = make('img');
            img.src = it.src;
            img.alt = it.label;
            img.width = 24;
            img.height = 24;
            img.loading = 'lazy';
            btn.appendChild(img);

            btn.addEventListener('click', function () {
                insertAtCursor(it.code);
            });
            grid.appendChild(btn);
        });

        box.appendChild(grid);
        box.hidden = true;
        document.body.appendChild(box);
        return box;
    }

    function insertAtCursor(text) {
        var start = ta.selectionStart;
        replaceRange(start, ta.selectionEnd, text, start + text.length, start + text.length);
        ta.focus();
    }

    function emojiOutside(e) {
        if (!emojiBox) {
            return;
        }
        if (emojiBox.contains(e.target)) {
            return;
        }
        if (e.target.closest && e.target.closest('[data-act="emoji"]')) {
            return;
        }
        closeEmoji();
    }

    function emojiKey(e) {
        if (e.key === 'Escape') {
            e.preventDefault();
            closeEmoji();
            ta.focus();
        }
    }

    function closeEmoji() {
        if (!emojiBox || !emojiOpen) {
            return;
        }
        emojiOpen = false;
        emojiBox.hidden = true;
        emojiBox.classList.remove('is-on');
        document.removeEventListener('mousedown', emojiOutside, true);
        document.removeEventListener('keydown', emojiKey, true);
        window.removeEventListener('scroll', closeEmoji, true);
    }

    function toggleEmoji(anchor) {
        if (!EMOJIS.length) {
            toast('没有可用的表情', 'warn');
            return;
        }
        if (!emojiBox) {
            emojiBox = buildEmojiBox();
        }
        if (emojiOpen) {
            closeEmoji();
            return;
        }

        emojiBox.hidden = false;
        emojiBox.style.visibility = 'hidden';
        emojiBox.style.left = '0px';
        emojiBox.style.top = '0px';

        var rect = anchor.getBoundingClientRect();
        var w = emojiBox.offsetWidth;
        var h = emojiBox.offsetHeight;
        var left = Math.min(Math.max(8, rect.left + rect.width / 2 - w / 2), window.innerWidth - w - 8);
        var top = rect.bottom + 8;
        if (top + h > window.innerHeight - 8) {
            top = Math.max(8, rect.top - h - 8);
        }

        emojiBox.style.left = left + 'px';
        emojiBox.style.top = top + 'px';
        emojiBox.style.visibility = '';
        emojiBox.classList.add('is-on');
        emojiOpen = true;

        document.addEventListener('mousedown', emojiOutside, true);
        document.addEventListener('keydown', emojiKey, true);
        window.addEventListener('scroll', closeEmoji, true);
    }

    /* ------------------------------------------------------------------ *
     * 本地草稿
     * ------------------------------------------------------------------ */

    var draftKey = 'ecod:ed:' + (CFG.type || 'post') + ':' + (CFG.cid || 'new');

    function draftData() {
        return {
            text: ta.value,
            title: (function () {
                var t = $('#title');
                return t ? t.value : '';
            })(),
            ts: Date.now()
        };
    }

    function saveDraft(silent) {
        if (!ta.value.trim()) {
            return;
        }
        var data = draftData();
        store.set(draftKey, data);
        if (!silent) {
            setSaved('草稿已存 ' + timeLabel(data.ts));
        }
    }

    function timeLabel(ts) {
        var d = new Date(ts);
        var pad = function (n) {
            return n < 10 ? '0' + n : String(n);
        };
        return pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    function setSaved(text) {
        var node = $('[data-save]');
        if (node) {
            node.textContent = text;
        }
    }

    /* ------------------------------------------------------------------ *
     * 状态栏 / 大纲
     * ------------------------------------------------------------------ */

    function countWords(text) {
        var cn = (text.match(/[\u4e00-\u9fa5]/g) || []).length;
        var rest = text.replace(/[\u4e00-\u9fa5]/g, ' ').match(/[A-Za-z0-9_'-]+/g);
        return cn + (rest ? rest.length : 0);
    }

    function paintStatus() {
        var node = $('[data-stat]');
        if (!node) {
            return;
        }

        var text = ta.value;
        var words = countWords(text);
        var lines = text === '' ? 0 : text.split('\n').length;
        var minutes = Math.max(1, Math.ceil(words / 350));

        var parts = [
            '<b>' + words + '</b> 字',
            lines + ' 行',
            '约 ' + minutes + ' 分钟'
        ];

        if (state.uploading > 0) {
            parts.push('<span class="ec-ed__up">正在上传 ' + state.uploading + ' 张…</span>');
        }

        node.innerHTML = parts.join('<i>·</i>');
    }

    function parseOutline(text) {
        var lines = text.split('\n');
        var items = [];
        var inFence = false;
        var offset = 0;

        for (var i = 0; i < lines.length; i++) {
            var line = lines[i];

            if (/^\s*(```|~~~)/.test(line)) {
                inFence = !inFence;
            } else if (!inFence) {
                var m = IS_MD
                    ? line.match(/^(#{1,4})\s+(.+)$/)
                    : line.match(/<h([1-4])[^>]*>(.*?)<\/h\1>/i);
                if (m) {
                    var level = IS_MD ? m[1].length : parseInt(m[1], 10);
                    var label = IS_MD ? m[2] : m[2].replace(/<[^>]+>/g, '');
                    items.push({ level: level, text: label.trim(), pos: offset, line: i });
                }
            }

            offset += line.length + 1;
        }

        return items;
    }

    function paintOutline() {
        var box = $('[data-outline]');
        if (!box) {
            return;
        }

        var items = parseOutline(ta.value);

        if (!items.length) {
            box.innerHTML = '<p class="ec-ed__outline-empty">还没有小标题。用「二级标题」「三级标题」组织一下结构吧。</p>';
            return;
        }

        var html = items.map(function (item) {
            return '<button type="button" class="ec-ed__olink lv' + item.level + '" data-pos="' + item.pos + '" data-line="' + item.line + '">'
                + esc(item.text) + '</button>';
        }).join('');

        box.innerHTML = html;
    }

    function gotoPos(pos, line) {
        ta.focus();
        ta.setSelectionRange(pos, pos);

        var lh = parseFloat(getComputedStyle(ta).lineHeight) || 26;
        var target = Math.max(0, line * lh - ta.clientHeight / 3);
        ta.scrollTop = target;
        sync();
    }

    /* ------------------------------------------------------------------ *
     * 预览短代码渲染（与 PHP 端语法一致，仅用于预览观感）
     * ------------------------------------------------------------------ */

    var PV_COLORS = {
        info: '#3b82f6', primary: '#4f6ef2', tip: '#10b981', success: '#10b981',
        warn: '#f59e0b', warning: '#f59e0b', danger: '#ef4444', error: '#ef4444',
        dark: '#64748b'
    };

    var PV_NAMED = {
        primary: '#4f6ef2', blue: '#3b82f6', green: '#10b981', teal: '#14b8a6',
        cyan: '#06b6d4', yellow: '#f59e0b', orange: '#f97316', red: '#ef4444',
        pink: '#ec4899', purple: '#8b5cf6', gray: '#94a3b8', dark: '#64748b'
    };

    var PV_PANS = [
        ['百度网盘', '#06a7ff', '百', /pan\.baidu\.com/i],
        ['阿里云盘', '#4e6bf2', '阿', /aliyundrive\.com|alipan\.com/i],
        ['夸克网盘', '#4c68f5', '夸', /pan\.quark\.cn/i],
        ['蓝奏云', '#00a3e0', '蓝', /lanzou|lanpv|lanzn|lanzoui|lanzoux/i],
        ['天翼云盘', '#e62129', '翼', /cloud\.189\.cn/i],
        ['123云盘', '#1b6dff', '123', /123pan/i],
        ['OneDrive', '#0364b8', 'Od', /onedrive\.live\.com|1drv/i],
        ['Google Drive', '#1fa463', 'Gd', /drive\.google\.com/i],
        ['GitHub', '#181717', 'Git', /github\.com/i],
        ['奶牛快传', '#2c6bed', '牛', /cowtransfer\.com/i],
        ['城通网盘', '#f5a623', '城', /ctfile\.com/i],
        ['移动云', '#0085d0', '移', /caiyun\.139\.com/i],
        ['文叔叔', '#6c5ce7', '文', /wenshushu\.cn/i],
        ['迅雷', '#2c6df2', '迅', /xunlei\.com/i]
    ];

    function scAttrs(raw) {
        raw = String(raw)
            .replace(/&quot;/g, '"').replace(/&#0?39;/g, "'")
            .replace(/&apos;/g, "'").replace(/&amp;/g, '&')
            .trim();
        var attrs = {};
        /* 首段裸值：[progress 60] / [task done] / [lamp green]，可与具名属性混用（与 PHP 端 ecod_sc_attrs 一致） */
        var bare = raw.match(/^("[^"]*"|'[^']*'|[^\s"'=]+)(\s*=)?/);
        if (bare && !bare[2]) {
            attrs.type = bare[1].replace(/^["']|["']$/g, '');
            raw = raw.slice(bare[0].length);
        }
        raw.replace(/([a-zA-Z_-]+)\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s\]]+))/g, function (m, k, a, b, c) {
            attrs[k.toLowerCase()] = a != null ? a : (b != null ? b : c);
            return '';
        });
        return attrs;
    }

    function scBlock(html, tag, fn) {
        /* 与 PHP 端 ecod_sc_block 一致：连包裹的 <p> 一起替换，避免 <p><div> 非法嵌套留下空段落 */
        var re = new RegExp('(?:<p>)?\\s*\\[' + tag + '((?:\\s[^\\]]*)?)\\]([\\s\\S]*?)\\[/' + tag + '\\]\\s*(?:</p>)?', 'gi');
        return html.replace(re, function (m, raw, body) {
            /* markdown 会把跨行的开/闭标签拆进相邻两个 <p>，清掉 body 内残留的段落边界 */
            body = String(body || '')
                .replace(/<\/p>\s*<p>/gi, '\n')
                .replace(/^\s*<\/p>\s*/i, '')
                .replace(/\s*<p>\s*$/i, '');
            return fn(scAttrs(raw), body);
        });
    }

    function scSelfRe(html, tag, fn) {
        var re = new RegExp('(?:<p>)?\\s*\\[' + tag + '((?:\\s[^\\]]*)?)\\]\\s*(?:</p>)?', 'gi');
        return html.replace(re, function (m, raw) {
            return fn(scAttrs(raw));
        });
    }

    function pvColor(name, fallback) {
        name = String(name || '').toLowerCase();
        if (PV_NAMED[name]) {
            return PV_NAMED[name];
        }
        if (/^#?[0-9a-f]{3,6}$/i.test(name)) {
            return name.charAt(0) === '#' ? name : '#' + name;
        }
        return fallback || '#4f6ef2';
    }

    function pvNetdisk(url) {
        if (/^magnet:/i.test(url)) {
            return { name: '磁力链接', color: '#6e56cf', mark: '磁' };
        }
        if (/^thunder:/i.test(url)) {
            return { name: '迅雷', color: '#2c6df2', mark: '迅' };
        }
        for (var i = 0; i < PV_PANS.length; i++) {
            if (PV_PANS[i][3].test(url)) {
                return { name: PV_PANS[i][0], color: PV_PANS[i][1], mark: PV_PANS[i][2] };
            }
        }
        return null;
    }

    function pvPlain(s) {
        return String(s).replace(/<[^>]+>/g, '').replace(/&nbsp;/g, ' ').trim();
    }

    /* markdown 预览会把多行块转成 <br> 连接，按行解析需兼容两种形态 */
    function pvLines(body) {
        return String(body).split(/<br\s*\/?>|\r?\n/i).map(pvPlain).filter(function (s) {
            return s !== '';
        });
    }

    function renderShortcodes(html) {
        if (!html || html.indexOf('[') < 0) {
            return html;
        }

        /* tabs（含嵌套 tab，最先处理） */
        html = scBlock(html, 'tabs', function (a, body) {
            var tabs = [];
            var re = /\[tab((?:\s[^\]]*)?)\]([\s\S]*?)\[\/tab\]/gi;
            var m;
            while ((m = re.exec(body))) {
                var ta2 = scAttrs(m[1]);
                tabs.push({
                    title: ta2.title || ta2.type || ('标签 ' + (tabs.length + 1)),
                    body: m[2]
                });
            }
            if (!tabs.length) {
                return body;
            }
            return '<div class="pv-tabs"><div class="pv-tabs__nav">'
                + tabs.map(function (t, i) {
                    return '<span class="pv-tabs__tab' + (i === 0 ? ' is-active' : '') + '">' + esc(t.title) + '</span>';
                }).join('')
                + '</div>'
                + tabs.map(function (t, i) {
                    return '<div class="pv-tabs__pane' + (i === 0 ? ' is-active' : '') + '">' + t.body + '</div>';
                }).join('')
                + '</div>';
        });

        html = scBlock(html, 'fold', function (a, body) {
            return '<details class="pv-fold"><summary>' + esc(a.title || a.type || '点击展开')
                + '</summary><div class="pv-fold__body">' + body + '</div></details>';
        });

        /* 提示类 */
        function pvNote(a, body) {
            var type = String(a.type || 'info').toLowerCase();
            var c = PV_COLORS[type] || PV_COLORS.info;
            return '<div class="pv-note" style="--c:' + c + '">'
                + '<div class="pv-note__head">' + esc(a.title || '提示') + '</div>'
                + '<div class="pv-note__body">' + body + '</div></div>';
        }
        html = scBlock(html, 'note', pvNote);
        html = scBlock(html, 'alert', pvNote);

        html = scBlock(html, 'message', function (a, body) {
            var type = String(a.type || 'info').toLowerCase();
            return '<div class="pv-msg" style="--c:' + (PV_COLORS[type] || PV_COLORS.info) + '">' + body + '</div>';
        });

        html = scBlock(html, 'callout', function (a, body) {
            return '<div class="pv-callout" style="--c:' + pvColor(a.color) + '">'
                + (a.title ? '<div class="pv-callout__head">' + esc(a.title) + '</div>' : '')
                + '<div class="pv-callout__body">' + body + '</div></div>';
        });

        html = scBlock(html, 'anote', function (a, body) {
            return '<div class="pv-anote"><span>' + body + '</span>'
                + (a.author ? '<em>' + esc(a.author) + '</em>' : '') + '</div>';
        });

        html = scBlock(html, 'copy', function (a, body) {
            return '<div class="pv-copy"><div class="pv-copy__bar"><span>' + esc(a.label || '复制')
                + '</span><i>前台可一键复制</i></div><pre class="pv-copy__body">' + esc(pvPlain(body)) + '</pre></div>';
        });

        /* 排版 */
        html = scBlock(html, 'mtitle', function (a, body) {
            return '<div class="pv-mtitle">' + esc(a.title || pvPlain(body) || '标题') + '</div>';
        });
        html = scSelfRe(html, 'mtitle', function (a) {
            return a.title || a.type ? '<div class="pv-mtitle">' + esc(a.title || a.type) + '</div>' : '';
        });

        html = scSelfRe(html, 'progress', function (a) {
            var p = Math.max(0, Math.min(100, parseFloat(String(a.percentage || a.value || a.type || '0').replace('%', '')) || 0));
            var c = pvColor(a.color);
            return '<div class="pv-prog">'
                + (a.label ? '<span class="pv-prog__label">' + esc(a.label) + '</span>' : '')
                + '<span class="pv-prog__track"><span class="pv-prog__bar" style="width:' + p + '%;background:' + c + '"></span></span>'
                + '<span class="pv-prog__num" style="color:' + c + '">' + p + '%</span></div>';
        });

        html = scBlock(html, 'abtn', function (a, body) {
            var c = pvColor(a.color);
            var ghost = String(a.style || 'fill') === 'ghost';
            return '<a class="pv-btn' + (ghost ? ' is-ghost' : '') + '" style="--c:' + c
                + '" href="' + esc(a.href || a.url || '#') + '" target="_blank" rel="noopener">'
                + esc(pvPlain(body) || '按钮') + '</a>';
        });

        html = scSelfRe(html, 'dotted', function () {
            return '<hr class="pv-dotted">';
        });

        html = scSelfRe(html, 'lamp', function (a) {
            return '<span class="pv-lamp" style="background:' + pvColor(a.color || a.type, '#10b981') + '"></span>';
        });

        html = scSelfRe(html, 'task', function (a) {
            var done = ['done', 'x', 'yes', '1', 'true'].indexOf(String(a.type || a.done || '').toLowerCase()) > -1;
            return '<span class="pv-task' + (done ? ' is-done' : '') + '">' + (done ? '✓' : '') + '</span>';
        });

        html = html.replace(/\[mark\]([\s\S]*?)\[\/mark\]/gi, '<mark>$1</mark>');
        html = html.replace(/\[kbd\]([\s\S]*?)\[\/kbd\]/gi, '<kbd class="pv-kbd">$1</kbd>');

        /* 卡片 */
        function pvCard(a, inner) {
            return '<div class="pv-card"><b>' + esc(a.title || '卡片标题') + '</b>'
                + (a.desc ? '<span>' + esc(a.desc) + '</span>' : '')
                + (a.tag ? '<i>' + esc(a.tag) + '</i>' : '')
                + (inner ? '<p>' + inner + '</p>' : '') + '</div>';
        }
        html = scBlock(html, 'cards', function (a, body) {
            var col = Math.min(4, Math.max(1, parseInt(a.column || a.cols || '2', 10) || 2));
            var inn = body.replace(/\[card((?:\s[^\]]*)?)\](?:([\s\S]*?)\[\/card\])?/gi, function (m, raw, b) {
                return pvCard(scAttrs(raw), b || '');
            });
            return '<div class="pv-cards" style="--col:' + col + '">'
                + (a.title ? '<div class="pv-cards__title">' + esc(a.title) + '</div>' : '')
                + '<div class="pv-cards__list">' + inn + '</div></div>';
        });
        html = scBlock(html, 'grid', function (a, body) {
            var col = Math.min(4, Math.max(1, parseInt(a.column || a.cols || '2', 10) || 2));
            var gap = Math.min(40, Math.max(0, parseInt(a.gap || '12', 10) || 12));
            return '<div class="pv-grid" style="--col:' + col + ';--gap:' + gap + 'px">' + body + '</div>';
        });
        html = scBlock(html, 'card', pvCard);
        html = scSelfRe(html, 'card', function (a) {
            return pvCard(a, '');
        });

        /* 下载 */
        function pvDlIcon(pan) {
            return '<span class="pv-dl__icon"' + (pan ? ' style="background:' + pan.color + '"' : '')
                + '>' + (pan ? pan.mark : '↓') + '</span>';
        }
        html = scBlock(html, 'download', function (a, body) {
            var url = a.url || a.href || '';
            if (!url) {
                return body;
            }
            var pan = pvNetdisk(url);
            return '<a class="pv-dl" href="' + esc(url) + '" target="_blank" rel="noopener">'
                + pvDlIcon(pan)
                + '<span class="pv-dl__main"><b>' + esc(a.name || '点击下载') + '</b>'
                + (pvPlain(body) ? '<small>' + esc(pvPlain(body)) + '</small>' : '')
                + (pan ? '<small>' + esc(pan.name) + '</small>' : '')
                + '</span>'
                + (a.size ? '<span class="pv-dl__size">' + esc(a.size) + '</span>' : '')
                + '</a>';
        });

        html = scBlock(html, 'cloud', function (a, body) {
            function row(url, pwd, title) {
                var pan = pvNetdisk(url);
                return '<div class="pv-cloud__item">' + pvDlIcon(pan)
                    + '<span class="pv-cloud__main"><b>' + esc(title || (pan && pan.name) || '下载链接') + '</b>'
                    + (pan ? '<small>' + esc(pan.name) + '</small>' : '') + '</span>'
                    + (pwd ? '<span class="pv-cloud__pwd">' + esc(pwd) + '</span>' : '')
                    + '<a class="pv-cloud__go" href="' + esc(url) + '" target="_blank" rel="noopener">打开 ↗</a></div>';
            }
            var rows = '';
            if (a.url) {
                rows = row(a.url, a.pwd || a.password || '', pvPlain(body) || a.title || '');
            } else {
                pvLines(body).forEach(function (line) {
                    if (line.toLowerCase().indexOf('http') < 0) {
                        return;
                    }
                    var parts = line.split('|').map(function (s) { return s.trim(); });
                    rows += row(parts[0], parts[1] || '', parts[2] || '');
                });
            }
            return rows ? '<div class="pv-cloud">' + rows + '</div>' : body;
        });

        /* 时间线 / 引用 */
        html = scBlock(html, 'timeline', function (a, body) {
            var items = '';
            pvLines(body).forEach(function (line) {
                var parts = line.split('|');
                items += '<li><time>' + esc(parts[0].trim()) + '</time><span>'
                    + esc(parts.slice(1).join('|').trim()) + '</span></li>';
            });
            return items ? '<ul class="pv-timeline">' + items + '</ul>' : body;
        });

        html = scBlock(html, 'reply', function (a, body) {
            return '<div class="pv-reply">🔒 此处内容评论后可见（前台展示）'
                + (pvPlain(body) ? '<div class="pv-reply__hint">' + body + '</div>' : '') + '</div>';
        });

        /* 可见性：login / comment / level / password（与 PHP 端 ecod_sc_hide 同口径） */
        html = scBlock(html, 'hide', function (a, body) {
            var type = String(a.type || 'login').toLowerCase();
            if (type === 'pw') {
                type = 'password';
            }
            if (['login', 'comment', 'reply', 'level', 'password'].indexOf(type) < 0) {
                type = 'login';
            }
            var lvl = Math.max(1, Math.min(7, parseInt(a.level, 10) || 1));
            if (a.level && type === 'login') {
                type = 'level';
            }
            /* 密码可见：前台是同页 POST 校验，内容不进 HTML；没写 pw 时按登录可见处理 */
            var pw = a.pw || a.password || a.pass || '';
            if (type === 'password') {
                if (!pw) {
                    type = 'login';
                } else {
                    return '<div class="pv-reply">🔑 ' + esc(a.tip || '密码可见')
                        + '<div class="pv-reply__hint">已设置密码，前台显示输入框；校验通过后才输出内容（源码里不含正文）</div></div>';
                }
            }
            var tips = {
                login: '仅登录用户可见',
                comment: '仅评论者可见',
                reply: '仅评论者可见',
                level: '需评论等级 Lv.' + lvl + ' 及以上'
            };
            return '<div class="pv-reply">🔒 ' + esc(a.tip || tips[type]) + '（前台展示）'
                + (pvPlain(body) ? '<div class="pv-reply__hint">' + body + '</div>' : '') + '</div>';
        });

        html = scSelfRe(html, 'posts', function (a) {
            var bits = [];
            if (a.category) {
                bits.push('分类 ' + a.category);
            }
            if (a.tag) {
                bits.push('标签 ' + a.tag);
            }
            if (a.related && String(a.related) !== '0') {
                bits.push('同分类相关');
            }
            if (a.order) {
                bits.push('排序 ' + a.order);
            }
            bits.push('最多 ' + (parseInt(a.limit, 10) || 5) + ' 篇');
            return '<div class="pv-acard">📚 文章列表' + (a.title ? '：' + esc(a.title) : '')
                + '<span>' + esc(bits.join(' · ')) + '，保存后按真实文章渲染</span></div>';
        });

        html = scSelfRe(html, 'article', function (a) {
            var cid = parseInt(a.id || a.type || '0', 10) || 0;
            return cid ? '<div class="pv-acard">📄 引用文章 #' + cid
                + '<span>保存后前台自动抓取标题、摘要与封面</span></div>' : '';
        });

        html = scSelfRe(html, 'github', function (a) {
            var repo = String(a.repo || a.type || '').trim();
            if (!/^[\w.-]+\/[\w.-]+$/.test(repo)) {
                return '';
            }
            return '<a class="pv-gh" href="https://github.com/' + esc(repo) + '" target="_blank" rel="noopener">'
                + '<b>' + esc(repo) + '</b>' + (a.desc ? '<span>' + esc(a.desc) + '</span>' : '')
                + '<i>★ Star</i></a>';
        });

        /* 媒体 */
        html = scSelfRe(html, 'bilibili', function (a) {
            var bv = String(a.bvid || a.type || '').match(/(BV[0-9A-Za-z]{10})/);
            if (!bv) {
                return '';
            }
            return '<div class="pv-embed"><iframe src="https://player.bilibili.com/player.html?bvid='
                + bv[1] + '&page=' + (parseInt(a.page, 10) || 1) + '&autoplay=0" scrolling="no" frameborder="0" allowfullscreen></iframe></div>';
        });

        html = scSelfRe(html, 'video', function (a) {
            var url = a.url || a.src || '';
            if (!url) {
                return '';
            }
            return '<figure class="pv-video"><video controls preload="metadata" src="' + esc(url) + '"'
                + (a.poster ? ' poster="' + esc(a.poster) + '"' : '') + '></video>'
                + (a.name ? '<figcaption>' + esc(a.name) + '</figcaption>' : '') + '</figure>';
        });

        html = scSelfRe(html, 'mp3', function (a) {
            var url = a.url || a.src || '';
            if (!url) {
                return '';
            }
            return '<div class="pv-audio"><b>' + esc(a.name || '音频') + '</b>'
                + '<audio controls preload="none" src="' + esc(url) + '"></audio></div>';
        });

        html = scBlock(html, 'music', function (a, body) {
            var items = [];
            pvLines(body).forEach(function (line) {
                if (line.toLowerCase().indexOf('http') < 0) {
                    return;
                }
                items.push(line.split('|').map(function (s) { return s.trim(); }));
            });
            if (!items.length) {
                return body;
            }
            return '<div class="pv-playlist"><div class="pv-playlist__head">♪ ' + esc(a.title || '播放列表') + '</div>'
                + '<div class="pv-playlist__player"><b>' + esc(items[0][1] || '') + '</b>'
                + '<audio controls preload="none" src="' + esc(items[0][0]) + '"></audio></div>'
                + '<ol>' + items.map(function (p, i) {
                    return '<li' + (i === 0 ? ' class="is-active"' : '') + '>' + (i + 1) + '. ' + esc(p[1] || '音轨 ' + (i + 1)) + '</li>';
                }).join('') + '</ol></div>';
        });

        /* 兜底：清掉替换后残留的空段落与多余 <br>，避免组件间出现大空隙 */
        html = html.replace(/<p>(?:\s|&nbsp;|<br\s*\/?>)*<\/p>/gi, '');
        html = html.replace(/(?:\s*<br\s*\/?>)+\s*(<(?:div|details|figure|ul|ol|hr)\b)/gi, '$1');
        html = html.replace(/(<\/(?:div|details|figure|ul|ol|table|blockquote)>)\s*(?:<br\s*\/?>\s*)+/gi, '$1');

        return html;
    }

    /* ------------------------------------------------------------------ *
     * 核心同步
     * ------------------------------------------------------------------ */

    var renderPreview = debounce(function () {
        if (!preview) {
            return;
        }

        var text = ta.value;

        if (!text.trim()) {
            preview.update('', true);
            return;
        }

        var html = IS_MD ? renderMarkdown(text) : text;
        html = renderShortcodes(html);
        preview.update(html, false);
    }, 220);

    function paintHighlight() {
        if (!hl) {
            return;
        }

        var text = ta.value;

        /* 超大文本关闭语法着色，优先保证输入流畅 */
        if (text.length > 60000) {
            hl.innerHTML = esc(text);
        } else {
            hl.innerHTML = highlight(text);
        }

        hl.style.width = ta.clientWidth + 'px';
        hl.style.transform = 'translate3d(0,' + (-ta.scrollTop) + 'px,0)';
    }

    var frameQueued = false;

    function sync() {
        if (!frameQueued) {
            frameQueued = true;
            window.requestAnimationFrame(function () {
                frameQueued = false;
                paintHighlight();
            });
        }

        paintStatus();
        renderPreview();
    }

    var saveDraftLazy = debounce(function () {
        saveDraft(true);
        setSaved('草稿已存 ' + timeLabel(Date.now()));
    }, 3000);

    /* ------------------------------------------------------------------ *
     * 工具栏构建
     * ------------------------------------------------------------------ */

    function buildToolbar(bar) {
        var tools = toolsFor();

        tools.forEach(function (tool) {
            if (tool.sep) {
                bar.appendChild(make('span', 'ec-ed__sep'));
                return;
            }

            var btn = make('button', 'ec-ed__btn', icon(tool.icon));
            btn.type = 'button';
            btn.setAttribute('data-tip', tool.title);
            btn.setAttribute('data-act', tool.act);
            btn.setAttribute('aria-label', tool.title);

            btn.addEventListener('click', function () {
                if (ACTIONS[tool.act]) {
                    ACTIONS[tool.act](btn);
                }
            });

            bar.appendChild(btn);
        });
    }

    /* ------------------------------------------------------------------ *
     * 启动
     * ------------------------------------------------------------------ */

    function buildShell() {
        var host = ta.parentNode;
        var form = ta.closest('form');

        shell = make('div', 'ec-ed');
        shell.setAttribute('data-mode', IS_MD ? 'markdown' : 'html');

        /* 工具栏 */
        var bar = make('div', 'ec-ed__bar');
        var tools = make('div', 'ec-ed__tools');
        buildToolbar(tools);
        bar.appendChild(tools);

        var spacer = make('span', 'ec-ed__grow');
        bar.appendChild(spacer);
        var side = make('div', 'ec-ed__side');

        var btnPreview = make('button', 'ec-ed__btn ec-ed__btn--wide is-on', icon('eye') + '<span>预览</span>');
        btnPreview.type = 'button';
        btnPreview.setAttribute('data-tip', '切换预览 Ctrl+Shift+P');
        btnPreview.setAttribute('data-preview-toggle', '1');

        /* 移动端：编辑 / 预览单栏切换 */
        var seg = make('div', 'ec-ed__seg');
        seg.setAttribute('role', 'tablist');

        var segEdit = make('button', 'is-on', '编辑');
        segEdit.type = 'button';
        segEdit.setAttribute('role', 'tab');
        segEdit.setAttribute('data-mview', 'edit');
        segEdit.setAttribute('aria-selected', 'true');

        var segPrev = make('button', '', '预览');
        segPrev.type = 'button';
        segPrev.setAttribute('role', 'tab');
        segPrev.setAttribute('data-mview', 'preview');
        segPrev.setAttribute('aria-selected', 'false');

        seg.appendChild(segEdit);
        seg.appendChild(segPrev);
        bar.appendChild(seg);

        var btnOutline = make('button', 'ec-ed__btn ec-ed__btn--wide', icon('outline') + '<span>大纲</span>');
        btnOutline.type = 'button';
        btnOutline.setAttribute('data-tip', '文章大纲');
        btnOutline.setAttribute('data-outline-toggle', '1');

        var btnFull = make('button', 'ec-ed__btn', icon('full'));
        btnFull.type = 'button';
        btnFull.setAttribute('data-tip', '全屏专注 Ctrl+Shift+F');

        side.appendChild(btnPreview);
        side.appendChild(btnOutline);
        side.appendChild(btnFull);
        bar.appendChild(side);

        shell.appendChild(bar);

        /* 短代码图标行（独立一行，按组分区） */
        var scrow = make('div', 'ec-ed__scbar');
        buildScBar(scrow);
        shell.appendChild(scrow);

        /* 主体 */
        var main = make('div', 'ec-ed__main');

        var colEdit = make('div', 'ec-ed__col ec-ed__col--edit');
        var scroll = make('div', 'ec-ed__scroll');
        hl = make('div', 'ec-ed__hl');
        hl.setAttribute('aria-hidden', 'true');

        scroll.appendChild(hl);
        scroll.appendChild(ta);
        colEdit.appendChild(scroll);

        var gutter = make('div', 'ec-ed__gutter');
        gutter.setAttribute('data-tip', '拖动调整宽度');

        var colPreview = make('div', 'ec-ed__col ec-ed__col--preview');
        var phead = make('div', 'ec-ed__phead');
        phead.innerHTML = '<span class="ec-ed__pdot"></span><span>实时预览</span>';
        var pbody = make('div', 'ec-ed__pbody');
        var frame = make('iframe', 'ec-ed__frame');
        frame.setAttribute('title', '文章预览');
        frame.setAttribute('sandbox', 'allow-same-origin');
        pbody.appendChild(frame);
        colPreview.appendChild(phead);
        colPreview.appendChild(pbody);

        main.appendChild(colEdit);
        main.appendChild(gutter);
        main.appendChild(colPreview);
        shell.appendChild(main);

        /* 大纲浮层 */
        var outline = make('div', 'ec-ed__outline');
        outline.setAttribute('data-outline', '');
        shell.appendChild(outline);

        /* 底栏 */
        var foot = make('div', 'ec-ed__foot');
        var stat = make('span', 'ec-ed__stat');
        stat.setAttribute('data-stat', '');
        var saved = make('span', 'ec-ed__saved');
        saved.setAttribute('data-save', '');
        saved.textContent = '自动保存已开启';
        foot.appendChild(stat);
        foot.appendChild(make('span', 'ec-ed__grow'));
        foot.appendChild(saved);
        shell.appendChild(foot);

        /* 替换原 <p> */
        host.parentNode.insertBefore(shell, host);
        host.parentNode.removeChild(host);

        ta.removeAttribute('style');
        ta.classList.add('ec-ed__input');

        preview = new PreviewBox(frame);

        bindTips(shell);

        bindEvents(btnPreview, btnOutline, btnFull, gutter);

        window.addEventListener('resize', function () {
            hideTip();
        });

        window.addEventListener('scroll', function () {
            hideTip();
        }, true);
    }

    function bindEvents(btnPreview, btnOutline, btnFull, gutter) {
        /* 输入 */
        ta.addEventListener('input', function () {
            sync();
            saveDraftLazy();
            var form = ta.closest('form');
            if (form && window.jQuery) {
                window.jQuery(form).trigger('write');
            }
        });

        /* 滚动同步 */
        ta.addEventListener('scroll', function () {
            if (hl) {
                hl.style.transform = 'translate3d(0,' + (-ta.scrollTop) + 'px,0)';
            }
            var max = ta.scrollHeight - ta.clientHeight;
            if (state.previewOn && max > 0) {
                preview.follow(ta.scrollTop / max);
            }
        });

        /* 尺寸变化 */
        var onResize = debounce(function () {
            if (hl) {
                hl.style.width = ta.clientWidth + 'px';
            }
        }, 120);
        window.addEventListener('resize', onResize);

        /* 快捷键 */
        ta.addEventListener('keydown', function (e) {
            var mod = e.metaKey || e.ctrlKey;

            if (e.key === 'Tab') {
                e.preventDefault();
                if (e.shiftKey) {
                    var start = ta.selectionStart;
                    var lineStart = ta.value.lastIndexOf('\n', start - 1) + 1;
                    if (ta.value.substr(lineStart, 2) === '  ') {
                        replaceRange(lineStart, lineStart + 2, '');
                    }
                } else {
                    replaceRange(ta.selectionStart, ta.selectionEnd, '  ');
                }
                return;
            }

            if (!mod) {
                /* 列表续行 */
                if (e.key === 'Enter' && IS_MD) {
                    var val = ta.value;
                    var pos = ta.selectionStart;
                    var lineStart = val.lastIndexOf('\n', pos - 1) + 1;
                    var line = val.slice(lineStart, pos);
                    var m = line.match(/^(\s*)([-*+]|\d+\.)\s+(.*)$/);

                    if (m) {
                        e.preventDefault();
                        if (m[3].trim() === '') {
                            replaceRange(lineStart, pos, '');
                        } else {
                            var next = m[2].match(/^\d+\.$/)
                                ? (parseInt(m[2], 10) + 1) + '. '
                                : m[2] + ' ';
                            replaceRange(pos, pos, '\n' + m[1] + next);
                        }
                        return;
                    }
                }
                return;
            }

            var key = e.key.toLowerCase();

            if (key === 'b') {
                e.preventDefault();
                ACTIONS.bold();
            } else if (key === 'i') {
                e.preventDefault();
                ACTIONS.italic();
            } else if (key === 'k') {
                e.preventDefault();
                ACTIONS.link();
            } else if (key === 's') {
                e.preventDefault();
                saveDraft(false);
                toast('已保存本地草稿');
            } else if (key === 'p' && e.shiftKey) {
                e.preventDefault();
                togglePreview();
            } else if (key === 'f' && e.shiftKey) {
                e.preventDefault();
                toggleFull();
            }
        });

        /* 粘贴图片 */
        ta.addEventListener('paste', function (e) {
            var items = e.clipboardData && e.clipboardData.items;
            if (!items) {
                return;
            }

            for (var i = 0; i < items.length; i++) {
                if (items[i].kind === 'file' && /^image\//.test(items[i].type)) {
                    e.preventDefault();
                    var file = items[i].getAsFile();
                    if (file) {
                        toast('正在上传图片…');
                        uploadImage(file, insertImage);
                    }
                    return;
                }
            }
        });

        /* 拖拽上传 */
        shell.addEventListener('dragover', function (e) {
            e.preventDefault();
            shell.classList.add('is-drop');
        });

        shell.addEventListener('dragleave', function (e) {
            if (!shell.contains(e.relatedTarget)) {
                shell.classList.remove('is-drop');
            }
        });

        shell.addEventListener('drop', function (e) {
            e.preventDefault();
            shell.classList.remove('is-drop');
            var files = e.dataTransfer && e.dataTransfer.files;
            if (!files || !files.length) {
                return;
            }
            Array.prototype.forEach.call(files, function (file) {
                if (/^image\//.test(file.type)) {
                    toast('正在上传 ' + file.name + '…');
                    uploadImage(file, insertImage);
                }
            });
        });

        /* 预览开关 */
        function togglePreview() {
            state.previewOn = !state.previewOn;
            shell.classList.toggle('is-nopreview', !state.previewOn);
            btnPreview.classList.toggle('is-on', state.previewOn);
            if (state.previewOn) {
                renderPreview();
            }
        }

        btnPreview.addEventListener('click', togglePreview);

        /* 移动端单栏：编辑 / 预览 互斥 */
        var segBtns = $$('.ec-ed__seg button', shell);

        function setMobileView(view) {
            state.mview = view === 'preview' ? 'preview' : 'edit';
            shell.classList.toggle('is-mpreview', state.mview === 'preview');

            segBtns.forEach(function (b) {
                var on = b.getAttribute('data-mview') === state.mview;
                b.classList.toggle('is-on', on);
                b.setAttribute('aria-selected', on ? 'true' : 'false');
            });

            if (state.mview === 'preview') {
                if (!state.previewOn) {
                    togglePreview();
                } else {
                    renderPreview();
                }
            }
        }

        segBtns.forEach(function (b) {
            b.addEventListener('click', function () {
                setMobileView(b.getAttribute('data-mview'));
            });
        });

        /* 回到双栏宽度时清掉单栏状态 */
        if (window.matchMedia) {
            var mqWide = window.matchMedia('(min-width: 901px)');
            var onWide = function (e) {
                if (e.matches) {
                    shell.classList.remove('is-mpreview');
                }
            };

            if (mqWide.addEventListener) {
                mqWide.addEventListener('change', onWide);
            } else if (mqWide.addListener) {
                mqWide.addListener(onWide);
            }
        }

        /* 全屏 */
        function toggleFull() {
            state.full = !state.full;
            shell.classList.toggle('is-full', state.full);
            document.documentElement.classList.toggle('ec-ed-locked', state.full);
            btnFull.classList.toggle('is-on', state.full);
        }

        btnFull.addEventListener('click', toggleFull);

        /* 大纲 */
        btnOutline.addEventListener('click', function () {
            paintOutline();
            shell.classList.toggle('is-outline');
            btnOutline.classList.toggle('is-on', shell.classList.contains('is-outline'));
        });

        document.addEventListener('click', function (e) {
            if (shell.classList.contains('is-outline')
                && !e.target.closest('.ec-ed__outline')
                && !e.target.closest('[data-outline-toggle]')) {
                shell.classList.remove('is-outline');
                btnOutline.classList.remove('is-on');
            }
        });

        var outlineBox = $('[data-outline]');
        outlineBox.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-pos]');
            if (btn) {
                gotoPos(parseInt(btn.getAttribute('data-pos'), 10), parseInt(btn.getAttribute('data-line'), 10));
            }
        });

        /* 分栏拖拽 */
        var dragging = false;
        gutter.addEventListener('mousedown', function (e) {
            e.preventDefault();
            dragging = true;
            document.body.classList.add('ec-ed-dragging');
        });

        document.addEventListener('mousemove', function (e) {
            if (!dragging) {
                return;
            }
            var rect = shell.getBoundingClientRect();
            var ratio = ((e.clientX - rect.left) / rect.width) * 100;
            ratio = Math.max(28, Math.min(74, ratio));
            shell.style.setProperty('--split', ratio + '%');
            if (hl) {
                hl.style.width = ta.clientWidth + 'px';
            }
        });

        document.addEventListener('mouseup', function () {
            if (dragging) {
                dragging = false;
                document.body.classList.remove('ec-ed-dragging');
            }
        });

        /* 离开提醒 */
        window.addEventListener('beforeunload', function (e) {
            saveDraft(true);
            if (shell.classList.contains('is-dirty')) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }

    /* ------------------------------------------------------------------ *
     * 草稿恢复
     * ------------------------------------------------------------------ */

    function offerDraft() {
        var saved = store.get(draftKey, null);
        if (!saved || !saved.text || saved.text === ta.value) {
            return;
        }
        if (!saved.text.trim()) {
            return;
        }

        var bar = make('div', 'ec-ed__notice');
        bar.innerHTML = '<span>发现 <b>' + timeLabel(saved.ts) + '</b> 的本地草稿</span>'
            + '<button type="button" class="ec-ed__nbtn is-primary" data-do="restore">恢复</button>'
            + '<button type="button" class="ec-ed__nbtn" data-do="drop">忽略</button>';

        shell.insertBefore(bar, shell.firstChild.nextSibling);

        bar.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-do]');
            if (!btn) {
                return;
            }
            if (btn.getAttribute('data-do') === 'restore') {
                ta.value = saved.text;
                var t = $('#title');
                if (t && saved.title && !t.value) {
                    t.value = saved.title;
                }
                sync();
                toast('已恢复本地草稿');
            } else {
                store.del(draftKey);
            }
            bar.parentNode.removeChild(bar);
        });
    }

    /* ------------------------------------------------------------------ *
     * 与 Typecho 原生功能的兼容
     * ------------------------------------------------------------------ */

    function patchTypecho() {
        window.Typecho = window.Typecho || {};

        /* 附件面板「点击插入」走这里 */
        window.Typecho.insertFileToEditor = function (file, url, isImage) {
            if (isImage) {
                var alt = String(file || '图片').replace(/[[\]]/g, '');
                var snippet = IS_MD ? '![' + alt + '](' + url + ')' : '<img src="' + url + '" alt="' + esc(alt) + '">';
                replaceRange(ta.selectionStart, ta.selectionEnd, snippet);
            } else {
                var text = String(file || url);
                var link = IS_MD ? '[' + text + '](' + url + ')' : '<a href="' + url + '">' + esc(text) + '</a>';
                replaceRange(ta.selectionStart, ta.selectionEnd, link);
            }
        };
    }

    /* ------------------------------------------------------------------ *
     * 初始化
     * ------------------------------------------------------------------ */

    function init() {
        ta = document.getElementById('text');
        if (!ta || ta.dataset.ecodReady) {
            return;
        }
        ta.dataset.ecodReady = '1';

        buildShell();
        patchTypecho();
        sync();
        offerDraft();

        /* 标题变化也进草稿 */
        var title = document.getElementById('title');
        if (title) {
            title.addEventListener('input', saveDraftLazy);
        }

        /* 首次进入不提示离开 */
        ta.addEventListener('input', function () {
            shell.classList.add('is-dirty');
        }, { once: true });

        /* 窄屏自动收起预览（btnPreview 是 buildShell 的局部变量，这里按标记找） */
        if (shell.clientWidth < 1000 && state.previewOn) {
            var btnPreview = shell.querySelector('[data-preview-toggle]');
            if (btnPreview) {
                btnPreview.click();
            }
        }

        /* 提交时把本地草稿对齐为提交内容，避免下次打开误报「发现草稿」 */
        document.addEventListener('submit', function () {
            saveDraft(true);
            shell.classList.remove('is-dirty');
        }, true);

        setInterval(function () {
            if (document.activeElement === ta) {
                saveDraft(true);
            }
        }, 30000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.EcodEditor = {
        sync: function () {
            if (ta) {
                sync();
            }
        }
    };
})();
