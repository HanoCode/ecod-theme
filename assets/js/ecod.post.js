/**
 * Ecod Theme · 内容页增强脚本（post / page 模板按需加载）
 *
 * 职责：目录 / 代码高亮 / 灯箱 / 评论体系 / 朗读 / 分享图 / AI 摘要 /
 *      oEmbed / 选项卡 / 行内标记 / 收银台——只在这些内容页用到的模块。
 *      首页与列表页不加载本文件，首屏 JS 体积因此减少约四成。
 *
 * 依赖 ecod.global.js 先行加载（页脚 defer 顺序保证）：公共工具经 Ecod.u
 * 注入，模块仍挂 Ecod.init* 命名空间，由 global 的 refresh()/runPost() 调度；
 * PJAX 从列表页进入内容页时由 global 的 ensurePostBundle() 动态注入本文件。
 *
 * Copyright (c) 2026 Ecod. All Rights Reserved. · https://ecod.cc
 */
(function () {
    'use strict';

    var Ecod = window.Ecod = window.Ecod || {};
    var CFG = window.ECOD || {};
    var root = document.documentElement;
    var U = Ecod.u || {};

    /* global 侧导出的内部工具；缺失时给安全空实现，模块内部各自判空兜底 */
    var once = U.once || function (el) { return el; };
    var copyText = U.copyText || function (t, done) { if (done) done(); };
    var esc = U.esc || function (s) { return String(s == null ? '' : s); };
    var shortNum = U.shortNum || function (n) { return String(n); };
    var syncFavButtons = U.syncFavButtons || function () {};

    /* 按需注入脚本 / 样式（Mermaid、KaTeX 懒加载用）：同地址不重复注入 */
    function loadScript(src, done) {
        if (document.querySelector('script[data-ecod-lazy="' + src + '"]')) {
            if (done) done();
            return;
        }
        var s = document.createElement('script');
        s.src = src;
        s.dataset.ecodLazy = src;
        s.onload = function () { if (done) done(null); };
        s.onerror = function () { if (done) done(new Error('load fail: ' + src)); };
        document.head.appendChild(s);
    }
    function loadCss(href) {
        if (document.querySelector('link[data-ecod-lazy="' + href + '"]')) return;
        var l = document.createElement('link');
        l.rel = 'stylesheet';
        l.href = href;
        l.dataset.ecodLazy = href;
        document.head.appendChild(l);
    }

    /* ---------- 目录生成 + 高亮 ----------
     * 默认走「浮动目录」：位置与侧栏互斥（侧栏在右 → 浮在左，侧栏在左 → 浮在右），
     * 不再占用侧栏版面；关闭浮动（后台 EFloatToc=off，无浮动容器）时回退为
     * 侧栏静态目录卡——不 sticky，避免滚动时盖住下方的侧栏模块。 */
    Ecod.initToc = function () {
        var content = document.querySelector('[data-ecod-content]');
        if (!content) return;

        var box = document.querySelector('[data-ecod-toc]');
        var wrap = box ? (box.closest('[data-ecod-toc-wrap]') || box.closest('.ecod_widget')) : null;
        var floatBox = document.querySelector('[data-ecod-floattoc]');
        var heads = content.querySelectorAll('h2, h3');

        /* 标题太少时两边都不出现 */
        if (heads.length < 2) {
            if (wrap) wrap.style.display = 'none';
            if (floatBox) floatBox.innerHTML = '';
            return;
        }

        var html = '';
        Array.prototype.forEach.call(heads, function (h, i) {
            if (!h.id) h.id = 'ecod-h-' + i;
            var isSub = h.tagName === 'H3';
            html += '<a href="#' + h.id + '" data-toc="' + h.id + '"' + (isSub ? ' class="lv3"' : '') + '>'
                + escapeHtml(h.textContent.trim()) + '</a>';
        });

        function headerOffset() {
            var header = document.querySelector('[data-ecod-header]');
            var h = header && !header.classList.contains('is-hidden') ? header.offsetHeight : 0;
            return h + 28;
        }

        function scrollToHead(id) {
            var t = document.getElementById(id);
            if (!t) return;
            var y = t.getBoundingClientRect().top + window.scrollY - headerOffset() + 12;
            window.scrollTo({ top: Math.max(y, 0), behavior: Ecod.scrollBehavior() });
            if (history.replaceState) history.replaceState(null, '', '#' + id);
        }

        /* 把链接列表装进容器并接上滚动高亮 */
        function mount(list) {
            list.innerHTML = html;
            var links = Array.prototype.slice.call(list.querySelectorAll('a'));
            var targets = links.map(function (a) { return document.getElementById(a.dataset.toc); });

            var activeIndex = -1;

            /* C3：弹层进度条 / 章节计数，与顶部进度条共用同一口径。
               必须在「章节未变化」时也跑 —— 同一节内滚动 pct 仍在变，
               否则进度只在跨章节时跳变。 */
            function syncProgress(next) {
                if (!showProg || !floatBox) return;
                var pct = Math.round(Ecod.readProgress());
                if (meterBar) meterBar.style.width = pct + '%';
                if (stat) stat.textContent = (next + 1) + ' / ' + targets.length + ' 节 · ' + pct + '%';
            }

            function spy() {
                var line = headerOffset();
                var atBottom = window.innerHeight + window.scrollY >= document.body.scrollHeight - 4;
                var next = 0;
                targets.forEach(function (t, i) {
                    if (t && t.getBoundingClientRect().top <= line) next = i;
                });
                if (atBottom) next = targets.length - 1;
                if (next === activeIndex) {
                    syncProgress(next);
                    return;
                }
                activeIndex = next;
                links.forEach(function (a, i) {
                    a.classList.toggle('is-active', i === next);
                    /* C3：当前节之前的项目标已读，读进度在目录里可视化 */
                    a.classList.toggle('is-read', i < next);
                    if (i === next) a.setAttribute('aria-current', 'true');
                    else a.removeAttribute('aria-current');
                });
                syncProgress(next);
                /* 列表过长时把当前项滚进可视区 */
                var cur = links[next];
                if (cur && list.scrollHeight > list.clientHeight + 8) {
                    var top = cur.offsetTop - list.clientHeight / 2 + cur.offsetHeight / 2;
                    list.scrollTo({ top: Math.max(top, 0), behavior: Ecod.scrollBehavior() });
                }
            }

            Ecod.addScroll(spy);
            spy();

            once(list, 'tocClick', 'click', function (e) {
                var a = e.target.closest('a');
                if (!a) return;
                e.preventDefault();
                scrollToHead(a.dataset.toc);
            });
        }

        /* ---- 浮动目录 ---- */
        if (floatBox) {
            if (wrap) wrap.style.display = 'none'; /* 与侧栏互斥：浮动生效时侧栏目录不渲染 */
            var layout = document.querySelector('.ecod_layout');
            var side = layout && layout.getAttribute('data-aside') === 'left' ? 'right' : 'left';
            floatBox.className = 'ecod_floattoc is-' + side;
            /* C3 目录进度条：ETocProgress=on 时弹层头部加进度条与章节计数（按钮不套环——胶囊按钮上圆环会压住图标文字） */
            var showProg = CFG.tocProgress !== false;
            floatBox.innerHTML =
                '<button type="button" class="ecod_floattoc__btn" data-ecod-ft-btn aria-expanded="false" aria-haspopup="true">'
                + '<svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" clip-rule="evenodd" d="M20 4H4C3.44771 4 3 4.44772 3 5V19C3 19.5523 3.44772 20 4 20H20C20.5523 20 21 19.5523 21 19V5C21 4.44771 20.5523 4 20 4ZM4 2C2.34315 2 1 3.34315 1 5V19C1 20.6569 2.34315 22 4 22H20C21.6569 22 23 20.6569 23 19V5C23 3.34315 21.6569 2 20 2H4ZM6 7H8V9H6V7ZM11 7C10.4477 7 10 7.44772 10 8C10 8.55228 10.4477 9 11 9H17C17.5523 9 18 8.55228 18 8C18 7.44772 17.5523 7 17 7H11ZM8 11H6V13H8V11ZM10 12C10 11.4477 10.4477 11 11 11H17C17.5523 11 18 11.4477 18 12C18 12.5523 17.5523 13 17 13H11C10.4477 13 10 12.5523 10 12ZM8 15H6V17H8V15ZM10 16C10 15.4477 10.4477 15 11 15H17C17.5523 15 18 15.4477 18 16C18 16.5523 17.5523 17 17 17H11C10.4477 17 10 16.5523 10 16Z"/></svg>'
                + '<span>目录</span>'
                + '</button>'
                + '<div class="ecod_floattoc__pop" data-ecod-ft-pop role="menu" aria-label="文章目录">'
                + '<p class="ecod_floattoc__head">目录'
                + (showProg ? '<span class="ecod_floattoc__stat" data-ecod-ft-stat></span>' : '') + '</p>'
                + (showProg ? '<div class="ecod_floattoc__meter" aria-hidden="true"><i data-ecod-ft-meter></i></div>' : '')
                + '<nav class="ecod_toc ecod_floattoc__list"></nav>'
                + '</div>';

            var btn = floatBox.querySelector('[data-ecod-ft-btn]');
            var pop = floatBox.querySelector('[data-ecod-ft-pop]');
            var list = floatBox.querySelector('.ecod_floattoc__list');
            var meterBar = floatBox.querySelector('[data-ecod-ft-meter]');
            var stat = floatBox.querySelector('[data-ecod-ft-stat]');
            mount(list);

            function setOpen(open) {
                floatBox.classList.toggle('is-open', open);
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open) {
                    var cur = list.querySelector('a.is-active');
                    if (cur) cur.scrollIntoView({ block: 'center' });
                }
            }

            /* 目录入口（后台 ETocEntry）：bar 模式下藏掉浮目录胶囊，
               弹层改由阅读工具条的目录按钮驱动（Ecod.ftSetOpen）。
               胶囊用 visibility 隐藏而不是 display:none —— 保住浮动容器尺寸，
               弹层仍按原位（胶囊右侧）弹出，滚动展开位置不变 */
            Ecod.ftSetOpen = setOpen;
            if (CFG.tocEntry === 'bar') {
                btn.style.visibility = 'hidden';
                btn.style.pointerEvents = 'none';
                btn.setAttribute('data-ecod-ft-pill', '0');
            }

            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                setOpen(!floatBox.classList.contains('is-open'));
            });
            /* 点击目录项后收起弹层 */
            once(floatBox, 'ftLink', 'click', function (e) {
                if (e.target.closest('.ecod_floattoc__list a')) setOpen(false);
            });
            /* 点击外部 / Esc 收起；↑↓ 在目录项间循环（role=menu 的键盘约定）；
               PJAX 换页后容器被替换，监听自动解绑 */
            document.addEventListener('click', function onDoc(e) {
                if (!floatBox.isConnected) { document.removeEventListener('click', onDoc); return; }
                if (floatBox.classList.contains('is-open') && !floatBox.contains(e.target)) setOpen(false);
            });
            document.addEventListener('keydown', function onKey(e) {
                if (!floatBox.isConnected) { document.removeEventListener('keydown', onKey); return; }
                if (e.key === 'Escape' && floatBox.classList.contains('is-open') && !Ecod.dialogActive()) { setOpen(false); return; }
                if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
                if (!floatBox.classList.contains('is-open')) return;
                var items = Array.prototype.slice.call(list.querySelectorAll('a'));
                if (!items.length) return;
                var active = document.activeElement;
                var cur = items.indexOf(active);
                if (cur < 0 && active !== btn) return;
                e.preventDefault();
                var next = cur < 0
                    ? (e.key === 'ArrowDown' ? 0 : items.length - 1)
                    : (e.key === 'ArrowDown' ? (cur + 1) % items.length : (cur - 1 + items.length) % items.length);
                items[next].focus();
            });
            return;
        }

        /* ---- 回退：侧栏静态目录卡 ---- */
        if (!box) return;
        if (wrap) wrap.style.display = '';
        mount(box);
    };

    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /* ---------- 代码高亮（零依赖轻量实现） ---------- */
    /* 单遍扫描：按「注释 > 字符串 > 变量 > 标签 > 关键字 > 数字 > 函数」优先级
       组合一条正则，命中的片段包上 <span class="tok-*">，其余原样转义输出。
       语言识别：language-xxx 类名优先，否则按内容启发式猜测。 */
    var HL_ALIAS = {
        javascript: 'js', js: 'js', jsx: 'js', ts: 'js', tsx: 'js', typescript: 'js',
        json: 'json', json5: 'json', jsonc: 'json',
        css: 'css', scss: 'css', less: 'css',
        html: 'html', xml: 'html', svg: 'html', vue: 'html', xhtml: 'html',
        php: 'php', 'php3': 'php', 'php5': 'php', phtml: 'php',
        sql: 'sql', mysql: 'sql', sqlite: 'sql',
        bash: 'bash', sh: 'bash', shell: 'bash', zsh: 'bash', console: 'bash',
        python: 'python', py: 'python', py3: 'python',
        go: 'go', golang: 'go', java: 'go', c: 'go', cpp: 'go', 'c++': 'go', cs: 'go', kotlin: 'go', swift: 'go', rust: 'go',
        yaml: 'yaml', yml: 'yaml', toml: 'yaml', ini: 'yaml', conf: 'yaml', nginx: 'yaml', env: 'yaml',
        md: 'generic', markdown: 'generic', diff: 'generic', text: 'generic', plaintext: 'generic'
    };

    var HL_CONF = {
        js:     { kw: 'abstract|as|async|await|break|case|catch|class|const|continue|debugger|declare|default|delete|do|else|enum|export|extends|finally|for|from|function|get|if|implements|import|in|instanceof|interface|keyof|let|new|of|private|protected|public|readonly|return|satisfies|set|static|super|switch|this|throw|try|type|typeof|var|void|while|with|yield', line: ['//'], block: [['/*', '*/']], str: true },
        php:    { kw: 'abstract|and|array|as|break|callable|case|catch|class|clone|const|continue|declare|default|do|echo|else|elseif|empty|enum|extends|final|finally|fn|for|foreach|function|global|goto|if|implements|include|include_once|instanceof|insteadof|interface|isset|list|match|namespace|new|or|print|private|protected|public|readonly|require|require_once|return|static|switch|throw|trait|try|unset|use|var|while|xor|yield', line: ['//', '#'], block: [['/*', '*/']], str: true, variable: true, literal: 'TRUE|FALSE|NULL' },
        css:    { kw: null, line: ['//'], block: [['/*', '*/']], str: true, css: true },
        html:   { kw: null, line: [], block: [['<!--', '-->']], str: true, html: true },
        json:   { kw: 'true|false|null|True|False|Null', line: ['//'], block: [['/*', '*/']], str: true, json: true },
        sql:    { kw: 'add|alter|and|as|asc|begin|between|by|case|commit|count|create|cross|database|delete|desc|distinct|drop|else|end|exists|foreign|from|full|group|having|if|in|index|inner|insert|into|is|join|key|left|like|limit|max|min|not|null|offset|on|or|order|outer|primary|references|right|rollback|select|set|sum|avg|table|then|transaction|truncate|union|update|values|view|when|where|with', line: ['--', '#'], block: [['/*', '*/']], str: true, flag: 'i' },
        bash:   { kw: 'alias|break|case|cat|cd|chmod|chown|composer|continue|cp|curl|do|done|echo|elif|else|esac|exit|export|fi|for|function|git|grep|if|in|local|ls|mkdir|mv|node|npm|php|read|return|rm|sed|select|set|shift|source|sudo|tar|then|touch|until|wget|while|yarn|zip', line: ['#'], block: [], str: true },
        python: { kw: 'and|as|assert|async|await|break|class|continue|def|del|dict|elif|else|except|finally|float|for|from|global|if|import|in|int|is|lambda|len|list|nonlocal|not|open|or|pass|print|raise|range|return|self|set|str|super|tuple|try|while|with|yield', line: ['#'], block: [['"""', '"""'], ["'''", "'''"]], str: true, literal: 'None|True|False' },
        go:     { kw: 'break|case|chan|const|continue|default|defer|else|fallthrough|for|func|go|goto|if|import|interface|map|package|range|return|select|struct|switch|type|var', line: ['//'], block: [['/*', '*/']], str: true },
        yaml:   { kw: 'true|false|null|yes|no|on|off|True|False|Null', line: ['#'], block: [], str: true, yaml: true },
        generic:{ kw: 'break|case|catch|class|const|continue|default|do|else|for|function|if|import|in|new|return|switch|this|try|var|while|true|false|null', line: ['//', '#'], block: [['/*', '*/']], str: true }
    };

    function hlEscape(s) {
        return s.replace(/[&<>]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c];
        });
    }

    /* 定界符转正则：注释边界里的 * / 等都是元字符，必须整体转义 */
    function hlQuote(s) {
        return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function hlBuild(conf) {
        var alts = [];
        var strRe = '"(?:\\\\.|[^"\\\\\\n])*"?|\'(?:\\\\.|[^\'\\\\\\n])*\'?|`(?:\\\\.|[^`\\\\])*`?';

        (conf.block || []).forEach(function (pair) {
            alts.push({ cls: 'cm', re: hlQuote(pair[0]) + '[\\s\\S]*?' + hlQuote(pair[1]) });
        });
        (conf.line || []).forEach(function (lead) {
            alts.push({ cls: 'cm', re: hlQuote(lead) + '[^\\n]*' });
        });
        if (conf.str) {
            alts.push({ cls: 'st', re: strRe });
        }
        if (conf.variable) {
            alts.push({ cls: 'va', re: '\\$[A-Za-z_][\\w]*' });
        }
        if (conf.json) {
            alts.push({ cls: 'pr', re: '"(?:\\\\.|[^"\\\\])*"(?=\\s*:)' });
        }
        if (conf.yaml) {
            alts.push({ cls: 'pr', re: '^[ \\t]*[\\w.\\-]+(?=\\s*:)' });
        }
        if (conf.html) {
            alts.push({ cls: 'at', re: '<!(?:DOCTYPE|doctype)[^>]*>' });
            alts.push({ cls: 'tg', re: '</?[A-Za-z][\\w:-]*|/?>' });
            alts.push({ cls: 'pr', re: '[A-Za-z_:][\\w:.-]*(?=\\s*=)' });
        }
        if (conf.css) {
            alts.push({ cls: 'at', re: '@[\\w-]+' });
            alts.push({ cls: 'nu', re: '#[0-9a-fA-F]{3,8}\\b' });
            alts.push({ cls: 'se', re: '\\.[-\\w]+|#[-\\w]+|::?[a-z-]+(?![\\w-])|&' });
            alts.push({ cls: 'pr', re: '[a-zA-Z-]+(?=\\s*:)' });
        }
        if (conf.literal) {
            alts.push({ cls: 'kw', re: '\\b(?:' + conf.literal + ')\\b' });
        }
        if (conf.kw) {
            alts.push({ cls: 'kw', re: '\\b(?:' + conf.kw + ')\\b' });
        }
        alts.push({ cls: 'nu', re: '\\b0[xX][0-9a-fA-F]+\\b|\\b\\d[\\d_]*(?:\\.\\d+)?(?:[eE][+-]?\\d+)?\\b|\\b\\d+(?:px|em|rem|%|vh|vw|vmin|vmax|s|ms|deg|fr|ch)\\b' });
        alts.push({ cls: 'fn', re: '\\b[A-Za-z_]\\w*(?=\\s*\\()' });
        return alts;
    }

    Ecod.highlightCode = function (codeEl, lang) {
        try {
            var src = codeEl.textContent;
            if (!src || src.length > 60000) return;

            lang = (lang || '').toLowerCase();
            var conf = HL_CONF[HL_ALIAS[lang] || lang] || null;
            if (!conf) {
                if (/^\s*<\?php|<\?=/.test(src)) conf = HL_CONF.php;
                else if (/^\s*[{[]/.test(src) && /["\']\s*:/.test(src)) conf = HL_CONF.json;
                else if (/^\s*#!/.test(src)) conf = HL_CONF.bash;
                else if (/^\s*<(!doctype|html|[a-z][\w-]*)/i.test(src)) conf = HL_CONF.html;
                else if (/\b(select|insert|update|delete|create)\b/i.test(src) && /\b(from|set|into|table)\b/i.test(src)) conf = HL_CONF.sql;
                else conf = HL_CONF.generic;
            }

            var alts = hlBuild(conf);
            if (!alts.length) return;

            var re = new RegExp(alts.map(function (a) { return '(' + a.re + ')'; }).join('|'), 'g' + (conf.flag || ''));
            var out = '';
            var last = 0;
            var m;

            while ((m = re.exec(src)) !== null) {
                if (m[0].length === 0) { re.lastIndex++; continue; }
                if (m.index > last) out += hlEscape(src.slice(last, m.index));

                var cls = 'kw';
                for (var i = 1; i <= alts.length; i++) {
                    if (m[i] !== undefined) { cls = alts[i - 1].cls; break; }
                }
                out += '<span class="tok-' + cls + '">' + hlEscape(m[0]) + '</span>';
                last = m.index + m[0].length;
            }
            out += hlEscape(src.slice(last));

            codeEl.innerHTML = out;
        } catch (e) {
            /* 高亮失败保持原样，不影响阅读与复制 */
        }
    };

    /* ---------- 内容增强：代码块 ---------- */
    Ecod.initContent = function () {
        var content = document.querySelector('[data-ecod-content]');
        if (!content) return;

        content.querySelectorAll('pre').forEach(function (pre) {
            if (pre.closest('.ecod_codeblock')) return;
            var box = document.createElement('div');
            box.className = 'ecod_codeblock';

            var bar = document.createElement('div');
            bar.className = 'ecod_codeblock__bar';
            bar.innerHTML = '<span class="ecod_codeblock__dots"><i></i><i></i><i></i></span>';

            var code = pre.querySelector('code');
            var lang = '';
            if (code) {
                /* HyperDown 输出 lang-xxx，Prism 生态惯例是 language-xxx，两种都认 */
                var m = (code.className || '').match(/(?:language|lang)-([\w-]+)/);
                if (m) lang = m[1];
            }
            if (lang) {
                bar.innerHTML += '<span class="ecod_codeblock__lang">' + lang + '</span>';
            }

            var copy = document.createElement('button');
            copy.type = 'button';
            copy.className = 'ecod_codeblock__copy';
            copy.innerHTML = Ecod.icon('copy', 12) + '<span>复制</span>';
            copy.addEventListener('click', function () {
                copyText(pre.innerText, function () {
                    copy.classList.add('is-done');
                    copy.innerHTML = Ecod.icon('check', 12) + '<span>已复制</span>';
                    setTimeout(function () {
                        copy.classList.remove('is-done');
                        copy.innerHTML = Ecod.icon('copy', 12) + '<span>复制</span>';
                    }, 1600);
                });
            });
            bar.appendChild(copy);

            pre.parentNode.insertBefore(box, pre);
            box.appendChild(bar);
            box.appendChild(pre);

            /* 长代码折叠：超过 40 行收起到固定高度，底部渐隐 + 展开胶囊（ECodeFold）。
               展开按钮挂在 box 上而非 pre 里，pre.innerText 复制不会被带进剪贴板 */
            if (CFG.codeFold) {
                var codeText = code ? code.textContent : pre.innerText;
                var lineCount = codeText.replace(/\n+$/, '').split('\n').length;
                if (lineCount > 40) {
                    /* 折叠高度取整行：max-height 若切在半行上，残行透过渐变遮罩
                       会露出拦腰裁断的字形（看起来像颜色发灰）。按行高取整行数，
                       再补回 pre 的上下 padding */
                    var foldTarget = code || pre;
                    var foldLh = parseFloat(getComputedStyle(foldTarget).lineHeight) || 20;
                    var foldPad = 0;
                    try {
                        var foldCs = getComputedStyle(pre);
                        foldPad = parseFloat(foldCs.paddingTop) + parseFloat(foldCs.paddingBottom) || 0;
                    } catch (eFold) {}
                    var foldRows = Math.max(12, Math.floor((420 - foldPad) / foldLh));
                    box.classList.add('is-folded');
                    box.style.setProperty('--ecod-fold-h', Math.round(foldRows * foldLh + foldPad) + 'px');
                    var expand = document.createElement('button');
                    expand.type = 'button';
                    expand.className = 'ecod_codeblock__expand';
                    expand.setAttribute('aria-expanded', 'false');
                    expand.textContent = '展开全部 · ' + lineCount + ' 行';
                    expand.addEventListener('click', function () {
                        var open = box.classList.toggle('is-open');
                        expand.setAttribute('aria-expanded', open ? 'true' : 'false');
                        expand.textContent = open ? '收起' : '展开全部 · ' + lineCount + ' 行';
                    });
                    box.appendChild(expand);
                }
            }

            /* 后台开着 Prism 时由 Prism 统一染色，内置高亮器不再插手，避免两套配色互相覆盖 */
            if (code && !(CFG.prism && window.Prism)) {
                Ecod.highlightCode(code, lang);
            }
        });

        /* 标题锚点：h2/h3 hover 浮现 # 链接（无 id 自动补，与 initToc 的 ecod-h-N 同命名），
           原生哈希跳转即可（h2/h3 有 scroll-margin-top 防吸顶遮挡） */
        content.querySelectorAll('h2, h3').forEach(function (h, i) {
            if (!h.id) h.id = 'ecod-h-' + (i + 1);
            if (h.querySelector('.ecod_hanchor')) return;
            var a = document.createElement('a');
            a.className = 'ecod_hanchor';
            a.href = '#' + h.id;
            a.textContent = '#';
            a.setAttribute('aria-label', '本节锚点链接');
            h.appendChild(a);
        });

        /* 图表 / 公式：内容驱动按需加载，页面没有 mermaid 代码块或公式时不加载任何资源 */
        Ecod.initMermaid();
        Ecod.initMath();
    };

    /* ---------- Mermaid 图表（EMermaid；懒加载本地渲染库） ----------
       ```mermaid 代码块先摘出来替换成 .ecod_mermaid 容器（不再吃代码块外壳与高亮），
       确认存在后再注入 mermaid.min.js；明暗切换经 MutationObserver 全量重绘 */
    Ecod._mermaidRender = function () {
        if (!window.mermaid) return;
        var els = document.querySelectorAll('.ecod_mermaid');
        if (!els.length) return;
        var dark = document.documentElement.getAttribute('data-theme') === 'dark';
        try {
            window.mermaid.initialize({ startOnLoad: false, theme: dark ? 'dark' : 'default', fontFamily: 'inherit' });
        } catch (e) {
            return;
        }
        els.forEach(function (n) {
            if (!n._src) n._src = n.textContent;
            n.removeAttribute('data-processed');
            n.innerHTML = '';
            n.textContent = n._src;
        });
        try { window.mermaid.run({ nodes: Array.prototype.slice.call(els) }); } catch (e) {}
    };
    Ecod.initMermaid = function () {
        var found = false;
        document.querySelectorAll('[data-ecod-content] pre code.language-mermaid, [data-ecod-content] pre code.lang-mermaid').forEach(function (code) {
            var pre = code.closest('pre');
            var box = pre ? pre.closest('.ecod_codeblock') : null;
            var host = box || pre;
            if (!host) return;
            var div = document.createElement('div');
            div.className = 'ecod_mermaid';
            div._src = code.textContent;
            div.textContent = div._src;
            host.parentNode.replaceChild(div, host);
            found = true;
        });
        if (!found || !CFG.mermaidUrl || Ecod._mermaidLoading) return;
        Ecod._mermaidLoading = 1;
        loadScript(CFG.mermaidUrl, function () {
            Ecod._mermaidRender();
            if (!Ecod._mermaidThemeObs) {
                Ecod._mermaidThemeObs = new MutationObserver(function () {
                    Ecod._mermaidRender();
                });
                Ecod._mermaidThemeObs.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
            }
        });
    };

    /* ---------- KaTeX 数学公式（EMath；懒加载，代码块内不处理） ---------- */
    Ecod.renderMath = function () {
        var content = document.querySelector('[data-ecod-content]');
        if (!content || !window.renderMathInElement) return;
        try {
            window.renderMathInElement(content, {
                delimiters: [
                    { left: '$$', right: '$$', display: true },
                    { left: '\\(', right: '\\)', display: false },
                    { left: '\\[', right: '\\]', display: true }
                ],
                ignoredTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code'],
                throwOnError: false
            });
        } catch (e) {}
    };
    Ecod.initMath = function () {
        var content = document.querySelector('[data-ecod-content]');
        if (!content || !CFG.katexBase || Ecod._mathLoading) return;
        var text = content.textContent || '';
        if (text.indexOf('$$') === -1 && text.indexOf('\\(') === -1 && text.indexOf('\\[') === -1) return;
        Ecod._mathLoading = 1;
        loadCss(CFG.katexBase + 'katex.min.css');
        loadScript(CFG.katexBase + 'katex.min.js', function (err) {
            if (err) return;
            loadScript(CFG.katexBase + 'auto-render.min.js', function (err2) {
                if (err2) return;
                Ecod.renderMath();
            });
        });
    };

    /* ---------- 图片灯箱（正文图片 + 壁纸，WordPress 风格） ---------- */
    Ecod.initLightbox = function () {
        var lb = document.querySelector('.ecod_lightbox');
        if (!lb) {
            lb = document.createElement('div');
            lb.className = 'ecod_lightbox';
            lb.setAttribute('role', 'dialog');
            lb.setAttribute('aria-modal', 'true');
            lb.setAttribute('aria-label', '图片预览');
            lb.innerHTML = ''
                + '<figure class="ecod_lightbox__stage"><img alt=""></figure>'
                + '<button type="button" class="ecod_lightbox__nav ecod_lightbox__prev" data-lb-prev aria-label="上一张">' + Ecod.icon('left', 20) + '</button>'
                + '<button type="button" class="ecod_lightbox__nav ecod_lightbox__next" data-lb-next aria-label="下一张">' + Ecod.icon('right', 20) + '</button>'
                + '<div class="ecod_lightbox__bar">'
                + '  <span class="ecod_lightbox__cap"><b data-lb-name></b><span data-lb-meta></span></span>'
                + '  <span class="ecod_lightbox__actions">'
                + '    <a class="ecod_lightbox__btn" data-lb-download href="#" download target="_blank" rel="noopener">' + Ecod.icon('download', 14) + '下载</a>'
                + '    <button type="button" class="ecod_lightbox__btn" data-lb-close>' + Ecod.icon('x', 14) + '关闭</button>'
                + '  </span>'
                + '</div>';
            document.body.appendChild(lb);
        }
        var img = lb.querySelector('img');
        var name = lb.querySelector('[data-lb-name]');
        var meta = lb.querySelector('[data-lb-meta]');
        var dl = lb.querySelector('[data-lb-download]');
        var prevBtn = lb.querySelector('[data-lb-prev]');
        var nextBtn = lb.querySelector('[data-lb-next]');
        var list = [];      /* 当前组图片：{src, alt} */
        var index = 0;
        var isOpen = false;

        function fileName(src) {
            var tail = String(src || '').split('/').pop().split('?')[0];
            try { tail = decodeURIComponent(tail); } catch (e) {}
            return tail || '图片';
        }

        function render() {
            var cur = list[index];
            if (!cur) {
                close();
                return;
            }
            /* 外链原图：先显示缩略图，后台探测原图，加载成功再换（失败就停在缩略图） */
            if (cur.fallback) {
                img.src = cur.fallback;
                dl.href = cur.fallback;
                var probe = new Image();
                probe.onload = function () {
                    if (!isOpen || list[index] !== cur) return;
                    img.src = cur.src;
                    dl.href = cur.src;
                };
                probe.src = cur.src;
            } else {
                img.src = cur.src;
                dl.href = cur.src;
            }
            img.alt = cur.alt || '';
            var only = list.length < 2;
            prevBtn.hidden = only;
            nextBtn.hidden = only;
            name.textContent = cur.alt || fileName(cur.src);
            meta.textContent = only ? '' : (index + 1) + ' / ' + list.length;
        }

        function close() {
            isOpen = false;
            lb.classList.remove('is-open');
            img.removeAttribute('src');
            Ecod.dialogClose(lb);
        }

        function open(src, alt, group, start) {
            if (!src) return;
            list = (group && group.length ? group : [{ src: src, alt: alt || '' }]).slice();
            index = Math.max(0, group && group.length ? start || 0 : 0);
            isOpen = true;
            render();
            lb.classList.add('is-open');
            /* onClose：Esc 由弹层原语统一收口，触发本组件的视觉关闭 */
            Ecod.dialogOpen(lb, lb.querySelector('[data-lb-close]'), close);
        }

        function nav(step) {
            if (!isOpen || list.length < 2) return;
            index = (index + step + list.length) % list.length;
            render();
        }

        /* PJAX 换页会重复调 initLightbox，灯箱 DOM 持久存在，事件只绑一次 */
        if (lb._ecod_bound) return;
        lb._ecod_bound = 1;

        /* 点遮罩关闭（按钮 / 大图点击不冒泡误关） */
        lb.addEventListener('click', function (e) {
            if (e.target === lb) close();
        });
        lb.querySelector('[data-lb-close]').addEventListener('click', close);
        prevBtn.addEventListener('click', function () { nav(-1); });
        nextBtn.addEventListener('click', function () { nav(1); });

        /* 键盘：← → 切换（Esc 由弹层原语承担，经 onClose 调 close()） */
        document.addEventListener('keydown', function (e) {
            if (!isOpen) return;
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                nav(-1);
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                nav(1);
            }
        });

        /* 移动端左右滑动切换 */
        var touchX = 0;
        lb.addEventListener('touchstart', function (e) {
            touchX = e.touches && e.touches[0] ? e.touches[0].clientX : 0;
        }, { passive: true });
        lb.addEventListener('touchend', function (e) {
            if (!isOpen || !e.changedTouches || !e.changedTouches[0]) return;
            var dx = e.changedTouches[0].clientX - touchX;
            if (dx > 44) nav(-1);
            else if (dx < -44) nav(1);
        }, { passive: true });

        /* 真实图片地址：懒加载图的 src 是占位 GIF，真地址在 data-src */
        function realSrc(im) {
            var lazy = im.getAttribute('data-src');
            var cur = im.getAttribute('src') || '';
            if (lazy && (!cur || cur.indexOf('data:') === 0)) {
                return lazy;
            }
            return im.currentSrc || cur;
        }

        /* href 是否指向一张图（按扩展名） */
        function imageUrl(url) {
            if (!url) return false;
            var path = String(url).split('#')[0].split('?')[0];
            return /\.(?:jpe?g|png|gif|webp|avif|bmp|svg)$/i.test(path);
        }

        /* 是否站外地址 */
        function isOffsite(url) {
            var m = String(url || '').match(/^(?:https?:)?\/\/([^\/?#]+)/i);
            return !!m && m[1].toLowerCase() !== location.host.toLowerCase();
        }

        /* 图片链接：<a> 里只有图没有字，或 href 本身就是图片地址 → 点击意图是看图 */
        function mediaLink(a) {
            if (!a) return true;
            if (imageUrl(a.getAttribute('href'))) return true;
            return a.textContent.replace(/[\s\u00a0]/g, '') === '';
        }

        /* 图片 → 灯箱条目：外链图优先用链接里的原图地址，加载失败回退缩略图 */
        function entry(im) {
            var a = im.closest ? im.closest('a') : null;
            var thumb = realSrc(im);
            var href = a ? (a.getAttribute('href') || '') : '';
            /* 图片链接且站外（如 <a href="原图"><img 缩略图></a>）→ 直接看原图 */
            if (a && mediaLink(a) && href && (imageUrl(href) || isOffsite(href)) && href !== thumb) {
                return { src: href, fallback: thumb, alt: im.alt || a.textContent.trim() || '' };
            }
            return { src: thumb, alt: im.alt || '' };
        }

        document.addEventListener('click', function (e) {
            var trigger = e.target.closest ? e.target.closest('[data-ecod-lightbox]') : null;
            if (trigger) {
                e.preventDefault();
                var group = [];
                var start = 0;
                /* 同一相册（data-lb-group 相同）内翻页；壁纸等无分组的触发器各自成组 */
                var gname = trigger.getAttribute('data-lb-group') || '';
                var pool = gname
                    ? document.querySelectorAll('[data-ecod-lightbox][data-lb-group="' + gname + '"]')
                    : document.querySelectorAll('[data-ecod-lightbox]:not([data-lb-group])');
                pool.forEach(function (el, i) {
                    var src = el.dataset.src || el.getAttribute('href');
                    if (!src) return;
                    if (el === trigger) start = group.length;
                    group.push({ src: src, alt: el.dataset.alt || el.getAttribute('aria-label') || '' });
                });
                open(trigger.dataset.src || trigger.getAttribute('href'), trigger.dataset.alt || '', group, start);
                return;
            }

            /* ① 点击正文图片（含外链图） */
            var hit = e.target.closest ? e.target.closest('.ecod_content img') : null;
            if (hit && mediaLink(hit.closest('a'))) {
                e.preventDefault();
                var imgs = [];
                var at = 0;
                document.querySelectorAll('.ecod_content img').forEach(function (im) {
                    if (!mediaLink(im.closest('a'))) return;
                    if (im === hit) at = imgs.length;
                    imgs.push(entry(im));
                });
                var cur = entry(hit);
                open(cur.src, cur.alt, imgs, at);
                return;
            }

            /* ② 点击指向图片的链接（href 是 .jpg/.png…，链接里可能带文字） */
            var link = e.target.closest ? e.target.closest('.ecod_content a[href]') : null;
            if (link && imageUrl(link.getAttribute('href')) && !link.hasAttribute('data-go')) {
                e.preventDefault();
                var big = link.getAttribute('href');
                var list2 = [];
                var at2 = 0;
                document.querySelectorAll('.ecod_content img').forEach(function (im) {
                    if (!mediaLink(im.closest('a'))) return;
                    list2.push(entry(im));
                });
                var jump = link.querySelector('img');
                if (jump) {
                    list2.forEach(function (it, i) {
                        if (it.src === realSrc(jump) || it.fallback === realSrc(jump)) at2 = i;
                    });
                }
                list2.push({ src: big, alt: link.textContent.replace(/\s+/g, ' ').trim() });
                open(big, link.textContent.replace(/\s+/g, ' ').trim(), list2, jump ? at2 : list2.length - 1);
            }
        });
    };

    /* ---------- 侧边悬浮操作栏（act 块 side 形态） ----------
       位置与侧栏互斥成对：侧栏在右 → 栏在左，其余（左/无侧栏）在右；浮动目录胶囊
       固定在侧栏对面，与本栏同侧时由 body.ecod-rail-* 标记让位（见 ecod.css §11.4b）。
       阅读进度与返回顶部由右下角悬浮件承担，本栏不再重复 */
    Ecod.initActRail = function () {
        var rail = document.querySelector('[data-ecod-actsiderail]');
        document.body.classList.remove('ecod-rail-left', 'ecod-rail-right');
        if (!rail) return;

        var layout = document.querySelector('.ecod_layout');
        var onLeft = !!(layout && layout.getAttribute('data-aside') === 'right');
        rail.classList.toggle('is-left', onLeft);
        document.body.classList.add(onLeft ? 'ecod-rail-left' : 'ecod-rail-right');
    };

    /* ---------- 文章操作条 ---------- */
    Ecod.initPostAct = function () {
        Ecod.initActRail();
        var bar = document.querySelector('[data-ecod-postact]');
        if (!bar) return;
        syncFavButtons();

        /* 系统分享：仅在支持的浏览器（主要移动端）显示入口 */
        if (navigator.share) {
            document.querySelectorAll('[data-ecod-share-native]').forEach(function (btn) {
                btn.hidden = false;
            });
        }

        /* 微信扫码：EcodQR 画当前页二维码（只画一次，之后纯显隐） */
        var qrBox = bar.querySelector('[data-ecod-share-qr]');
        var qrBtn = bar.querySelector('[data-ecod-share-wechat]');
        if (qrBox && qrBtn && !qrBtn._ecodQrBound) {
            qrBtn._ecodQrBound = 1;
            qrBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var open = !qrBox.hidden;
                qrBox.hidden = open;
                qrBtn.classList.toggle('is-open', !open);
                if (open) return;

                var canvas = qrBox.querySelector('canvas');
                var url = bar.getAttribute('data-url') || location.href;
                if (canvas && window.EcodQR && !canvas.dataset.ecodQrDrawn) {
                    var m = EcodQR.matrix(url, 'M');
                    if (m) {
                        var ctx = canvas.getContext('2d');
                        var size = canvas.width;
                        var n = m.length;
                        var quiet = 2;
                        var unit = Math.floor(size / (n + quiet * 2));
                        var total = unit * (n + quiet * 2);
                        var ox = (size - total) / 2;
                        ctx.fillStyle = '#ffffff';
                        ctx.fillRect(0, 0, size, size);
                        ctx.fillStyle = '#1a1a1a';
                        for (var r = 0; r < n; r++) {
                            for (var c = 0; c < n; c++) {
                                if (m[r][c]) ctx.fillRect(ox + (quiet + c) * unit, ox + (quiet + r) * unit, unit, unit);
                            }
                        }
                        canvas.dataset.ecodQrDrawn = '1';
                    }
                }
            });
        }

        /* ---------- 报错反馈（设计稿 §05 C4 第 6 格）：4 原因单选 + 选填说明 ----------
           接口 api.php?action=report（SMTP 未配置时后端返回明确文案引导去评论区）。
           PJAX 每页重建 bar 下的 DOM，直接在 details 上绑定，_ecodReport 防同页重复 */
        var rep = bar.querySelector('details.ecod_report');
        if (rep && !rep._ecodReport) {
            rep._ecodReport = 1;
            var repReason = '';
            var repSend = rep.querySelector('[data-ecod-report-send]');
            var repTip = rep.querySelector('[data-ecod-report-tip]');
            var repReset = function () {
                repReason = '';
                if (repSend) {
                    repSend.disabled = true;
                    repSend.textContent = '提交反馈';
                }
                Array.prototype.forEach.call(rep.querySelectorAll('[data-ecod-report-opt]'), function (o) {
                    o.classList.remove('is-on');
                    o.setAttribute('aria-pressed', 'false');
                });
                var noteEl = rep.querySelector('[data-ecod-report-note]');
                if (noteEl) noteEl.value = '';
                if (repTip) { repTip.hidden = true; repTip.textContent = ''; repTip.classList.remove('is-ok'); }
            };
            var repShowTip = function (msg, ok) {
                if (!repTip) return;
                repTip.hidden = false;
                repTip.textContent = msg;
                repTip.classList.toggle('is-ok', !!ok);
            };

            /* 关闭弹层（含提交成功后自动收起）时重置为初始态 */
            rep.addEventListener('toggle', function () {
                if (!rep.open) repReset();
            });

            /* 单选：互斥选中，选完启用提交钮 */
            rep.addEventListener('click', function (e) {
                var opt = e.target.closest ? e.target.closest('[data-ecod-report-opt]') : null;
                if (!opt) return;
                repReason = opt.getAttribute('data-ecod-report-opt') || '';
                Array.prototype.forEach.call(rep.querySelectorAll('[data-ecod-report-opt]'), function (o) {
                    var on = o === opt;
                    o.classList.toggle('is-on', on);
                    o.setAttribute('aria-pressed', on ? 'true' : 'false');
                });
                if (repSend) repSend.disabled = false;
                if (repTip) { repTip.hidden = true; repTip.textContent = ''; }
            });

            if (repSend) {
                repSend.addEventListener('click', function () {
                    if (repSend.disabled || repSend.dataset.busy === '1') return;
                    if (!repReason) { repShowTip('请先选择问题类型'); return; }
                    var noteEl = rep.querySelector('[data-ecod-report-note]');
                    var hpEl = rep.querySelector('[data-ecod-report-hp]');
                    var fd = new FormData();
                    fd.append('cid', String(bar.getAttribute('data-cid') || ''));
                    fd.append('reason', repReason);
                    fd.append('note', noteEl ? noteEl.value : '');
                    fd.append('hp', hpEl ? hpEl.value : '');
                    repSend.dataset.busy = '1';
                    repSend.disabled = true;
                    repSend.textContent = '提交中…';
                    fetch(CFG.api + '?action=report', {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: fd
                    }).then(function (r) { return r.json(); }).then(function (data) {
                        repSend.dataset.busy = '';
                        repSend.textContent = '提交反馈';
                        if (data && data.ok) {
                            repShowTip('已收到，感谢反馈', true);
                            /* 弹层停留 1.8s 让用户看到提示，再自动收起（toggle 会重置） */
                            window.setTimeout(function () { rep.open = false; }, 1800);
                        } else {
                            repSend.disabled = false;
                            repShowTip((data && data.error) || '提交失败，请稍后再试');
                        }
                    }).catch(function () {
                        repSend.dataset.busy = '';
                        repSend.disabled = false;
                        repSend.textContent = '提交反馈';
                        repShowTip('网络异常，请稍后再试');
                    });
                });
            }
        }
    };

    /* ---------- D3 底部快速评论条（移动端）+ D2 点赞头像堆叠跳评论区 ---------- */
    Ecod.initQuickBar = function () {
        var bar = document.querySelector('[data-ecod-quickbar]');
        var comments = document.getElementById('comments');
        var on = false;

        /* 滚动显隐：评论区整体还在视口下方且已滚过一屏才出现，进入评论区即隐藏 */
        if (bar && comments) {
            var sync = function () {
                var cRect = comments.getBoundingClientRect();
                var vh = window.innerHeight || 1;
                var show = cRect.top > vh && window.scrollY > vh * 0.6;
                if (show !== on) {
                    on = show;
                    bar.classList.toggle('is-on', show);
                }
            };
            Ecod.addScroll(sync);
            sync();
        }

        /* 点击：平滑滚到评论区并聚焦输入框（桌面只滚动不抢焦点）。
           once 防 PJAX 重复绑定；quickbar 与点赞头像堆叠共用该行为 */
        once(document, 'quickbar', 'click', function (e) {
            var btn = e.target.closest ? e.target.closest('[data-ecod-quickbar], [data-ecod-likers]') : null;
            if (!btn) return;
            e.preventDefault();
            var target = document.getElementById('comments');
            if (!target) return;
            target.scrollIntoView({ behavior: Ecod.scrollBehavior(), block: 'start' });
            setTimeout(function () {
                /* 与 quickbar 显示断点（CSS ≤680px）对齐，768 会导致 681~768px 区间
                   quickbar 可见但永远走不进聚焦分支 */
                if (window.innerWidth > 680) return;
                var ta = document.getElementById('textarea');
                if (ta) ta.focus({ preventScroll: true });
            }, 480);
        });
    };

    /* ---------- 分享图：小红书式分享卡片（canvas 生成，扫码读原文） ---------- */
    var shcardModal = null;

    Ecod.initShareCard = function () {
        /* 只建一次：PJAX 每次 refresh 都调用，重复创建会叠出多个弹层 */
        if (shcardModal || !document.querySelector('[data-ecod-sharecard]')) return;

        var modal = shcardModal = document.createElement('div');
        modal.className = 'ecod_shcard';
        modal.hidden = true;
        modal.innerHTML =
            '<div class="ecod_shcard__mask" data-ecod-shcard-close></div>' +
            '<div class="ecod_shcard__panel" role="dialog" aria-modal="true" aria-label="文章分享图片">' +
            '  <div class="ecod_shcard__head">分享图片' +
            '    <button type="button" class="ecod_shcard__x" data-ecod-shcard-close aria-label="关闭">' +
            '      <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M6.2253 4.81108C5.83477 4.42056 5.20161 4.42056 4.81108 4.81108C4.42056 5.20161 4.42056 5.83477 4.81108 6.2253L10.5858 12L4.81114 17.7747C4.42062 18.1652 4.42062 18.7984 4.81114 19.1889C5.20167 19.5794 5.83483 19.5794 6.22535 19.1889L12 13.4142L17.7747 19.1889C18.1652 19.5794 18.7984 19.5794 19.1889 19.1889C19.5794 18.7984 19.5794 18.1652 19.1889 17.7747L13.4142 12L19.189 6.2253C19.5795 5.83477 19.5795 5.20161 19.189 4.81108C18.7985 4.42056 18.1653 4.42056 17.7748 4.81108L12 10.5858L6.2253 4.81108Z"/></svg>' +
            '    </button>' +
            '  </div>' +
            '  <div class="ecod_shcard__body">' +
            '    <div class="ecod_shcard__loading" data-ecod-shcard-loading>正在生成…</div>' +
            '    <img class="ecod_shcard__img" data-ecod-shcard-img alt="文章分享卡片" hidden>' +
            '  </div>' +
            '  <div class="ecod_shcard__ops">' +
            '    <button type="button" class="ecod_shcard__save" data-ecod-shcard-save>' +
            '      <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M11 5C11 4.44772 11.4477 4 12 4C12.5523 4 13 4.44772 13 5V12.1578L16.2428 8.91501L17.657 10.3292L12.0001 15.9861L6.34326 10.3292L7.75748 8.91501L11 12.1575V5Z"/><path d="M4 14H6V18H18V14H20V18C20 19.1046 19.1046 20 18 20H6C4.89543 20 4 19.1046 4 18V14Z"/></svg>' +
            '      保存图片' +
            '    </button>' +
            '    <span class="ecod_shcard__tip">手机端可长按图片保存 / 转发</span>' +
            '  </div>' +
            '</div>';
        document.body.appendChild(modal);

        once(document, 'shcard-close', 'click', function (e) {
            if (modal.hidden || !e.target.closest || !e.target.closest('[data-ecod-shcard-close]')) return;
            close();
        });
        once(document, 'shcard-save', 'click', function (e) {
            /* 必须点在「保存图片」上且弹层打开，否则页面任意点击都会触发下载 */
            if (modal.hidden || !e.target.closest || !e.target.closest('[data-ecod-shcard-save]')) return;
            var img = modal.querySelector('[data-ecod-shcard-img]');
            if (!img || img.hidden || !img.getAttribute('src') || modal.dataset.busy === '1') return;
            modal.dataset.busy = '1';
            save(img);
        });

        /* Esc / 焦点陷阱 / 滚动锁由弹层原语承担 */
        function open() {
            modal.hidden = false;
            Ecod.dialogOpen(modal.querySelector('.ecod_shcard__panel'), modal.querySelector('.ecod_shcard__x'), close);
        }
        function close() {
            modal.hidden = true;
            modal.dataset.busy = '';
            Ecod.dialogClose(modal.querySelector('.ecod_shcard__panel'));
        }

        function save(img) {
            var name = (img.dataset.filename || 'share') + '.png';
            function done() { modal.dataset.busy = ''; }
            if (img.dataset.blobUrl) {
                try { URL.revokeObjectURL(img.dataset.blobUrl); } catch (err) {}
                img.dataset.blobUrl = '';
            }
            /* dataURL → blob，走 download 属性；不支持时开新页长按保存 */
            try {
                var bin = atob(img.src.split(',')[1]);
                var arr = new Uint8Array(bin.length);
                for (var i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
                var blob = new Blob([arr], { type: 'image/png' });
                var url = URL.createObjectURL(blob);
                img.dataset.blobUrl = url;
                var a = document.createElement('a');
                a.href = url;
                a.download = name;
                document.body.appendChild(a);
                a.click();
                a.remove();
                done();
            } catch (err) {
                window.open(img.src, '_blank');
                done();
            }
        }

        /* 文本折行，超出 maxLines 打省略号 */
        function wrap(ctx, text, maxWidth, maxLines) {
            var lines = [], line = '';
            for (var i = 0; i < text.length; i++) {
                var test = line + text.charAt(i);
                if (ctx.measureText(test).width > maxWidth && line !== '') {
                    lines.push(line);
                    line = text.charAt(i);
                    if (lines.length === maxLines) {
                        lines[maxLines - 1] = lines[maxLines - 1].replace(/…?$/, '…');
                        return lines;
                    }
                } else {
                    line = test;
                }
            }
            if (line !== '') lines.push(line);
            return lines.slice(0, maxLines);
        }

        function loadCover(src) {
            return new Promise(function (resolve) {
                if (!src) return resolve(null);
                var img = new Image();
                var settled = false;
                var timer = setTimeout(function () { if (!settled) { settled = true; resolve(null); } }, 4000);
                img.crossOrigin = 'anonymous';
                img.onload = function () {
                    if (settled) return;
                    settled = true;
                    clearTimeout(timer);
                    /* 画布被污染时也退回占位封面 */
                    try {
                        var probe = document.createElement('canvas');
                        probe.width = probe.height = 1;
                        probe.getContext('2d').drawImage(img, 0, 0);
                        probe.toDataURL();
                        resolve(img);
                    } catch (err) { resolve(null); }
                };
                img.onerror = function () {
                    if (settled) return;
                    settled = true;
                    clearTimeout(timer);
                    resolve(null);
                };
                img.src = src;
            });
        }

        function drawQr(ctx, url, x, y, box) {
            var m = (window.EcodQR && EcodQR.matrix(url, 'M')) || null;
            if (!m) return false;
            var n = m.length;
            var quiet = 2;
            var unit = Math.floor(box / (n + quiet * 2));
            var total = unit * (n + quiet * 2);
            var ox = x + (box - total) / 2, oy = y + (box - total) / 2;
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(ox, oy, total, total);
            ctx.fillStyle = '#1a1a1a';
            for (var r = 0; r < n; r++) {
                for (var c = 0; c < n; c++) {
                    if (m[r][c]) ctx.fillRect(ox + (quiet + c) * unit, oy + (quiet + r) * unit, unit, unit);
                }
            }
            return true;
        }

        /* 单行截断，超出加省略号 */
        function clip(ctx, text, maxWidth) {
            text = String(text || '');
            if (ctx.measureText(text).width <= maxWidth) return text;
            var out = text;
            while (out.length > 1 && ctx.measureText(out + '…').width > maxWidth) out = out.slice(0, -1);
            return out + '…';
        }

        function render(data) {
            var W = 640, R = 24, coverH = 340, qrBox = 116, pad = 48;
            var canvas = document.createElement('canvas');
            canvas.width = W;
            canvas.height = 10;
            var ctx = canvas.getContext('2d');

            /* 先量文本：按内容行数算总高，避免摘要只有一行时下方留大片空白 */
            ctx.font = '600 32px ' + data.font;
            var titleLines = wrap(ctx, data.title, W - pad * 2, 2);
            ctx.font = '22px ' + data.font;
            var absLines = wrap(ctx, data.excerpt || '长按识别二维码，阅读全文', W - pad * 2, 3);

            var titleTop = coverH + 48;
            var absTop = titleTop + titleLines.length * 44 + 10;
            var dividerY = absTop + (absLines.length - 1) * 30 + 22;
            var qrY = dividerY + 18;
            var H = qrY + qrBox + 26 + 36;

            /* 重置尺寸会清空 ctx 状态，下面每段都重新设字体与颜色 */
            canvas.height = H;

            /* 圆角画布（导出 PNG 四角透明，转发出去更精致） */
            ctx.beginPath();
            ctx.moveTo(R, 0);
            ctx.arcTo(W, 0, W, H, R);
            ctx.arcTo(W, H, 0, H, R);
            ctx.arcTo(0, H, 0, 0, R);
            ctx.arcTo(0, 0, W, 0, R);
            ctx.closePath();
            ctx.clip();

            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, W, H);

            /* 封面：有图用图（cover 裁切），无图退回主题色平铺块 */
            if (data.cover) {
                var img = data.cover;
                var scale = Math.max(W / img.naturalWidth, coverH / img.naturalHeight);
                var dw = img.naturalWidth * scale, dh = img.naturalHeight * scale;
                ctx.drawImage(img, (W - dw) / 2, (coverH - dh) / 2, dw, dh);
            } else {
                var grad = ctx.createLinearGradient(0, 0, W, coverH);
                grad.addColorStop(0, data.primary);
                grad.addColorStop(1, data.primaryDeep);
                ctx.fillStyle = grad;
                ctx.fillRect(0, 0, W, coverH);
                ctx.fillStyle = 'rgba(255,255,255,.9)';
                ctx.font = '800 120px ' + data.font;
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText((data.site || 'E').charAt(0), W / 2, coverH / 2);
                ctx.textAlign = 'left';
                ctx.textBaseline = 'alphabetic';
            }

            /* 标题：最多两行 */
            ctx.fillStyle = '#1a1a1a';
            ctx.font = '600 32px ' + data.font;
            titleLines.forEach(function (line, i) {
                ctx.fillText(line, pad, titleTop + i * 44);
            });

            /* 摘要：最多三行 */
            ctx.fillStyle = '#7a7a7a';
            ctx.font = '22px ' + data.font;
            absLines.forEach(function (line, i) {
                ctx.fillText(line, pad, absTop + i * 30);
            });

            /* 分隔线 */
            ctx.fillStyle = '#efefef';
            ctx.fillRect(pad, dividerY, W - pad * 2, 1);

            /* 页脚：站名 + 日期居左，二维码居右 */
            var qrX = W - pad - qrBox;
            var hasQr = drawQr(ctx, data.url, qrX, qrY, qrBox);
            if (!hasQr) {
                ctx.fillStyle = '#f2f2f2';
                ctx.fillRect(qrX, qrY, qrBox, qrBox);
            }

            ctx.fillStyle = '#1a1a1a';
            ctx.font = '700 26px ' + data.font;
            ctx.fillText(clip(ctx, data.site, qrX - pad - 24), pad, qrY + 44);
            if (data.date) {
                ctx.fillStyle = '#9a9a9a';
                ctx.font = '20px ' + data.font;
                ctx.fillText(data.date + ' 发布', pad, qrY + 78);
            }

            if (hasQr) {
                ctx.fillStyle = '#9a9a9a';
                ctx.font = '17px ' + data.font;
                ctx.textAlign = 'center';
                ctx.fillText('扫码阅读全文', qrX + qrBox / 2, qrY + qrBox + 24);
                ctx.textAlign = 'left';
            }

            return canvas;
        }

        function gather(bar) {
            var content = document.querySelector('[data-ecod-content]');
            var coverSrc = '';
            if (content) {
                var img = content.querySelector('img[src]');
                if (img && img.src.indexOf('data:') !== 0) coverSrc = img.src;
            }
            var excerpt = '';
            var meta = document.querySelector('meta[name="description"]');
            if (meta && meta.content) excerpt = meta.content;
            if (!excerpt && content) {
                var p = content.querySelector('p');
                if (p) excerpt = (p.textContent || '').replace(/\s+/g, ' ').trim();
            }
            var style = getComputedStyle(document.documentElement);
            return Promise.resolve({
                title: (bar.dataset.title || document.title).trim(),
                url: bar.dataset.url || location.href,
                site: bar.dataset.site || document.title.split(' - ')[0] || '',
                date: bar.dataset.date || '',
                excerpt: excerpt.trim().slice(0, 90),
                coverSrc: coverSrc,
                primary: style.getPropertyValue('--ecod-primary').trim() || '#4f6ef2',
                primaryDeep: style.getPropertyValue('--ecod-primary-deep').trim() || '#3d55c8',
                font: '"PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif'
            }).then(function (info) {
                return loadCover(info.coverSrc).then(function (cover) {
                    info.cover = cover;
                    return info;
                });
            });
        }

        once(document, 'sharecard', 'click', function (e) {
            var btn = e.target.closest ? e.target.closest('[data-ecod-sharecard]') : null;
            if (!btn) return;
            e.preventDefault();

            /* 收起分享弹层，再开图片弹窗 */
            var box = btn.closest('details');
            if (box) box.open = false;

            var bar = btn.closest('[data-ecod-postact]');
            if (!bar) return;

            var loading = modal.querySelector('[data-ecod-shcard-loading]');
            var img = modal.querySelector('[data-ecod-shcard-img]');
            loading.hidden = false;
            loading.textContent = '正在生成…';
            img.hidden = true;
            img.removeAttribute('src');
            open();

            gather(bar).then(function (info) {
                var canvas = render(info);
                img.dataset.filename = (info.site + '-' + info.title).replace(/[\\/:*?"<>|\s]+/g, '-').slice(0, 60) || 'share';
                img.src = canvas.toDataURL('image/png');
                loading.hidden = true;
                img.hidden = false;
            }).catch(function () {
                loading.textContent = '生成失败，请重试';
            });
        });
    };

    /* ---------- 文章：朗读（浏览器语音合成） ---------- */
    var tts = { queue: [], index: 0, state: 'idle', box: null, rate: 1 };

    function ttsSplit(text, max) {
        var out = [];
        var rest = text;
        while (rest.length > max) {
            var cut = -1;
            for (var i = max; i > max * 0.55; i--) {
                if ('。！？；，、.!?;,'.indexOf(rest.charAt(i)) > -1) {
                    cut = i + 1;
                    break;
                }
            }
            if (cut < 0) cut = max;
            out.push(rest.slice(0, cut));
            rest = rest.slice(cut);
        }
        if (rest.replace(/\s/g, '') !== '') out.push(rest);
        return out;
    }

    function ttsBuild() {
        var content = document.querySelector('[data-ecod-content]');
        if (!content) return [];
        var nodes = content.querySelectorAll('p, h2, h3, h4, h5, li, blockquote, td, figcaption');
        var queue = [];
        Array.prototype.forEach.call(nodes, function (el) {
            if (el.closest('pre') || el.closest('[data-ecod-tts]')) return;
            // 只取最内层块，避免 li > p 之类的重复
            if (el.querySelector('p, li, h2, h3, h4, blockquote')) return;
            var text = (el.textContent || '').replace(/\s+/g, ' ').trim();
            if (text.length < 2) return;
            ttsSplit(text, 110).forEach(function (piece) {
                queue.push({ el: el, text: piece });
            });
        });
        return queue;
    }

    function ttsVoice() {
        var list = window.speechSynthesis.getVoices() || [];
        for (var i = 0; i < list.length; i++) {
            if (/^zh/i.test(list[i].lang || '')) return list[i];
        }
        return null;
    }

    function ttsMark(el) {
        Array.prototype.forEach.call(document.querySelectorAll('.is-tts-active'), function (one) {
            if (one !== el) one.classList.remove('is-tts-active');
        });
        if (el && el.classList) el.classList.add('is-tts-active');
    }

    function ttsRender() {
        var box = tts.box;
        if (!box) return;
        var btn = box.querySelector('[data-ecod-tts-toggle]');
        var ic = box.querySelector('[data-ecod-tts-ic]');
        var label = box.querySelector('[data-ecod-tts-label]');
        var bar = box.querySelector('[data-ecod-tts-bar]');
        var hint = box.querySelector('[data-ecod-tts-hint]');
        var stop = box.querySelector('[data-ecod-tts-stop]');
        var playing = tts.state === 'playing';

        box.classList.toggle('is-playing', playing);
        if (btn) btn.setAttribute('aria-pressed', playing ? 'true' : 'false');
        if (ic) ic.innerHTML = Ecod.icon(playing ? 'pause' : 'play', 14);
        if (label) {
            label.textContent = playing ? '暂停' : (tts.state === 'paused' ? '继续' : '朗读本文');
        }
        if (stop) stop.hidden = tts.state === 'idle';

        var total = tts.queue.length || 1;
        var pct = tts.state === 'idle' ? 0 : Math.max(3, Math.round(tts.index / total * 100));
        if (bar) bar.style.width = pct + '%';
        if (hint) {
            var minutes = box.getAttribute('data-ecod-tts-minutes') || '';
            hint.textContent = tts.state === 'idle'
                ? (minutes ? '约 ' + minutes + ' 分钟' : '')
                : '已读 ' + Math.min(100, Math.round(tts.index / total * 100)) + '%';
        }
    }

    function ttsSpeak() {
        if (tts.state !== 'playing') return;
        if (tts.index >= tts.queue.length) {
            tts.index = tts.queue.length;
            ttsRender();
            window.setTimeout(function () {
                if (tts.state === 'playing') Ecod.stopTts();
            }, 260);
            return;
        }
        var item = tts.queue[tts.index];
        ttsMark(item.el);

        var u = new SpeechSynthesisUtterance(item.text);
        u.lang = 'zh-CN';
        u.rate = tts.rate;
        var v = ttsVoice();
        if (v) u.voice = v;
        u.onend = function () {
            if (tts.state !== 'playing') return;
            tts.index++;
            ttsRender();
            ttsSpeak();
        };
        u.onerror = function () {
            if (tts.state !== 'playing') return;
            Ecod.stopTts();
        };
        window.speechSynthesis.speak(u);
    }

    Ecod.stopTts = function () {
        if (window.speechSynthesis) {
            try { window.speechSynthesis.cancel(); } catch (e) {}
        }
        tts.state = 'idle';
        tts.index = 0;
        tts.queue = [];
        Array.prototype.forEach.call(document.querySelectorAll('.is-tts-active'), function (el) {
            el.classList.remove('is-tts-active');
        });
        ttsRender();
    };

    Ecod.initTts = function () {
        var box = document.querySelector('[data-ecod-tts]');
        if (!box || box._ecodTts) return;
        if (!('speechSynthesis' in window) || typeof SpeechSynthesisUtterance === 'undefined') {
            box.hidden = true;
            /* 工具条上既无朗读也无目录/字号时整条隐藏（设计稿 C3 验收：不输出空条） */
            var rbBox0 = box.closest('[data-ecod-readbar]');
            if (rbBox0) {
                var rest = rbBox0.querySelectorAll('[data-ecod-readbar-toc]:not([hidden]), [data-ecod-rb-fs]:not([hidden])');
                if (!rest.length) rbBox0.hidden = true;
            }
            return;
        }
        box._ecodTts = 1;
        tts.box = box;
        tts.state = 'idle';
        tts.index = 0;
        tts.queue = [];

        box.addEventListener('click', function (e) {
            var toggle = e.target.closest ? e.target.closest('[data-ecod-tts-toggle]') : null;
            var stop = e.target.closest ? e.target.closest('[data-ecod-tts-stop]') : null;

            if (stop) {
                Ecod.stopTts();
                return;
            }
            if (!toggle) return;

            if (tts.state === 'playing') {
                try { window.speechSynthesis.pause(); } catch (err) {}
                tts.state = 'paused';
                ttsRender();
                return;
            }
            if (tts.state === 'paused') {
                try { window.speechSynthesis.resume(); } catch (err) {}
                tts.state = 'playing';
                ttsRender();
                return;
            }

            tts.queue = ttsBuild();
            if (!tts.queue.length) return;
            tts.index = 0;
            tts.state = 'playing';
            try { window.speechSynthesis.cancel(); } catch (err) {}
            ttsRender();
            ttsSpeak();
        });

        var sel = box.querySelector('[data-ecod-tts-rate]');
        if (sel) {
            sel.addEventListener('change', function () {
                tts.rate = parseFloat(sel.value) || 1;
                if (tts.state === 'playing') {
                    try { window.speechSynthesis.cancel(); } catch (err) {}
                    window.setTimeout(ttsSpeak, 80);
                }
            });
        }
    };

    /* ---------- 阅读工具条：目录 / 字号 / 进度（设计稿 §05 C3） ----------
       朗读控件由 initTts 接管；这里只补三件事：
       ① 目录按钮触发浮目录（浮目录未启用时 PHP 不输出该按钮）；
       ② 字号 14/16/18 三档步进，改 .ecod_content 的 --ecod-fs-base 局部变量并持久化；
       ③ 进度线复用 Ecod.readProgress()，与顶部进度条同一口径。 */
    var FSK = 'ecod-content-fs';
    Ecod.initReadbar = function () {
        var bar = document.querySelector('[data-ecod-readbar]');
        if (!bar || bar._ecodReadbar) return;
        bar._ecodReadbar = 1;
        var content = document.querySelector('[data-ecod-content]');

        /* 形态：侧栏竖栏（后台 EReadBarMode=side）只在 ≥900px 生效，窄屏回落吸顶横条。
           竖栏贴侧栏边（与浮动目录互斥成对，浮目录按既有规则落侧栏对面边）：
           侧栏左 → 竖栏左，其余 → 竖栏右。
           MQ 监听只挂一次（PJAX 换页不重复累加），asked 通过 Ecod._rbApply 指向当前页工具条 */
        var sideMq = window.matchMedia('(min-width: 900px)');
        var rbLayout = document.querySelector('.ecod_layout');
        var railLeft = rbLayout && rbLayout.getAttribute('data-aside') === 'left';
        var applySide = function () {
            var on = bar.getAttribute('data-ecod-rb-side') === '1' && sideMq.matches;
            bar.classList.toggle('is-side', on);
            bar.classList.toggle('is-left', on && !!railLeft);
            if (pctSync) pctSync(); /* 形态切换后进度线的纵横填充方向立即可视 */
        };
        Ecod._rbApply = applySide;
        if (!Ecod._rbSideBound) {
            Ecod._rbSideBound = 1;
            var rbSideChange = function () { if (Ecod._rbApply) Ecod._rbApply(); };
            if (sideMq.addEventListener) sideMq.addEventListener('change', rbSideChange);
            else if (sideMq.addListener) sideMq.addListener(rbSideChange);
        }
        applySide();

        /* ① 目录：入口跟随后台「目录入口」开关（ETocEntry）——
           float=只留浮目录胶囊（工具条按钮隐藏）；bar/both=工具条按钮驱动弹层。
           无弹层可开（标题 <2 时浮目录已被 initToc 清空）时整枚隐藏 */
        var toc = bar.querySelector('[data-ecod-readbar-toc]');
        var ftBox = document.querySelector('[data-ecod-floattoc]');
        var ftBtn = ftBox ? ftBox.querySelector('[data-ecod-ft-btn]') : null;
        if (toc) {
            var heads = content ? content.querySelectorAll('h2, h3').length : 0;
            if (heads < 2 || !ftBtn || !Ecod.ftSetOpen || CFG.tocEntry === 'float') {
                toc.hidden = true;
            } else {
                toc.addEventListener('click', function (e) {
                    /* stopPropagation：本事件冒泡到 document 会命中「点外关闭」，
                       把刚打开的弹层又关回去（详见 initToc 的 document 监听） */
                    e.stopPropagation();
                    Ecod.ftSetOpen(!ftBox.classList.contains('is-open'));
                });
            }
        }

        /* ② 字号步进：14 / 16 / 18，存 localStorage，应用为局部变量 */
        var fsBox = bar.querySelector('[data-ecod-rb-fs]');
        if (fsBox && content) {
            var steps = [14, 16, 18];
            var cur = 16;
            var down = fsBox.querySelector('[data-ecod-fs="down"]');
            var up = fsBox.querySelector('[data-ecod-fs="up"]');
            try {
                var saved = parseInt(localStorage.getItem(FSK), 10);
                if (steps.indexOf(saved) >= 0) cur = saved;
            } catch (e) { /* 隐私模式无 localStorage，回落默认 */ }
            var edge = function () {
                down.classList.toggle('is-edge', cur <= steps[0]);
                up.classList.toggle('is-edge', cur >= steps[steps.length - 1]);
            };
            var apply = function () {
                /* 正文基准字号现消费 --ecod-prose-fs（后台「正文排版」随行内样式下发，
                   优先级高于 --ecod-fs-base），步进器两处同写保证覆盖生效 */
                content.style.setProperty('--ecod-prose-fs', cur + 'px');
                content.style.setProperty('--ecod-fs-base', cur + 'px');
                try { localStorage.setItem(FSK, String(cur)); } catch (e) {}
                edge();
            };
            var step = function (dir) {
                var i = steps.indexOf(cur);
                if (i < 0) i = 1;
                i = Math.min(steps.length - 1, Math.max(0, i + dir));
                if (steps[i] !== cur) {
                    cur = steps[i];
                    apply();
                }
            };
            down.addEventListener('click', function () { step(-1); });
            up.addEventListener('click', function () { step(1); });
            apply(); /* 换页后沿用上次字号 */
        }

        /* ③ 进度线 + 百分比（吸顶横条纵线横向填充，竖栏纵向填充） */
        var pctTxt = bar.querySelector('[data-ecod-rb-pct]');
        var pctBar = bar.querySelector('[data-ecod-rb-pctbar]');
        if ((pctTxt || pctBar) && content) {
            var pctSync = function () {
                var p = Math.min(100, Math.max(0, Math.round(Ecod.readProgress())));
                if (pctTxt) pctTxt.textContent = '已读 ' + p + '%';
                if (pctBar) {
                    if (bar.classList.contains('is-side')) {
                        pctBar.style.width = '100%';
                        pctBar.style.height = p + '%';
                    } else {
                        pctBar.style.height = '100%';
                        pctBar.style.width = p + '%';
                    }
                }
            };
            Ecod.addScroll(pctSync);
            pctSync();
        }

        /* 兜底：目录与朗读都不可用时整条隐藏（朗读由 initTts 隐藏（浏览器不支持）时
           不会走到这里在它前面还是后面未知，所以两侧都查一遍 visible 元素） */
        if (!toc || toc.hidden) {
            if (!fsBox && !bar.querySelector('[data-ecod-tts]:not([hidden])')) bar.hidden = true;
        }
    };

    /* ---------- 评论表单：字数、自动增高、提交前校验 ---------- */
    /* ---------- 评论：表情面板 ---------- */
    var EMOJIS = [
        '😀', '😄', '😁', '😊', '🙂', '😉', '😍', '🤩',
        '😘', '😜', '🤔', '😅', '😂', '🤣', '😭', '😢',
        '😮', '😱', '😴', '🥱', '😎', '🤯', '🥳', '😇',
        '👍', '👎', '👏', '🙏', '💪', '🤝', '✌️', '👀',
        '❤️', '💔', '🔥', '✨', '🎉', '🌱', '☕', '🍜'
    ];

    /* 表情面板当前指向（document 级监听只挂一次，PJAX 换页后重新指向新面板） */
    var emojiState = { panel: null, toggle: null };

    function emojiClose() {
        var panel = emojiState.panel;
        if (!panel || panel.hidden) return;
        panel.hidden = true;
        if (emojiState.toggle) {
            emojiState.toggle.setAttribute('aria-expanded', 'false');
            emojiState.toggle.classList.remove('is-open');
        }
    }

    Ecod.initEmoji = function () {
        var form = document.querySelector('[data-ecod-comment]');
        if (!form) return;

        var toggle = form.querySelector('[data-ecod-emoji-toggle]');
        var panel = form.querySelector('[data-ecod-emojis]');
        var ta = form.querySelector('[data-ecod-textarea]');
        if (!toggle || !panel || !ta || toggle._ecodEmoji) return;
        toggle._ecodEmoji = 1;

        emojiState.panel = panel;
        emojiState.toggle = toggle;

        /* 图片表情包：后台选了「图片表情包」，由 PHP 输出 31 个表情的 CDN 地址 */
        var smiles = (CFG.comments && CFG.comments.emoji === 'smiley' && CFG.comments.smiles) || null;

        if (!panel.childElementCount) {
            var frag = document.createDocumentFragment();
            if (smiles && smiles.length) {
                smiles.forEach(function (item) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'ecod_emoji ecod_emoji--img';
                    btn.dataset.code = item.code;
                    var img = document.createElement('img');
                    img.src = item.src;
                    img.alt = item.label || item.code;
                    img.loading = 'lazy';
                    btn.appendChild(img);
                    btn.setAttribute('aria-label', '插入 ' + (item.label || item.code));
                    frag.appendChild(btn);
                });
            } else {
                EMOJIS.forEach(function (ch) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'ecod_emoji';
                    btn.textContent = ch;
                    btn.setAttribute('aria-label', '插入 ' + ch);
                    frag.appendChild(btn);
                });
            }
            panel.appendChild(frag);
        }

        function insert(text) {
            var start = typeof ta.selectionStart === 'number' ? ta.selectionStart : ta.value.length;
            var end = typeof ta.selectionEnd === 'number' ? ta.selectionEnd : ta.value.length;
            ta.value = ta.value.slice(0, start) + text + ta.value.slice(end);
            var pos = start + text.length;
            try {
                ta.setSelectionRange(pos, pos);
            } catch (e) {}
            ta.focus();
            ta.dispatchEvent(new Event('input', { bubbles: true }));
        }

        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            var willOpen = panel.hidden;
            panel.hidden = !willOpen;
            toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            toggle.classList.toggle('is-open', willOpen);
            if (willOpen) ta.focus();
        });

        panel.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('.ecod_emoji') : null;
            if (!btn) return;
            e.preventDefault();
            insert(btn.dataset && btn.dataset.code ? btn.dataset.code : btn.textContent);
        });

        /* 点外部 / Esc 收起：监听只挂一次，始终指向 emojiState 里的当前面板 */
        once(document, 'emojiOut', 'click', function (e) {
            if (!emojiState.panel || emojiState.panel.hidden) return;
            if (emojiState.panel.contains(e.target)) return;
            if (emojiState.toggle && emojiState.toggle.contains(e.target)) return;
            emojiClose();
        });

        once(document, 'emojiEsc', 'keydown', function (e) {
            if (e.key === 'Escape' && !Ecod.dialogActive()) emojiClose();
        });
    };

    /* ---------- 正文：选项卡 ---------- */
    Ecod.initTabs = function () {
        var boxes = document.querySelectorAll('[data-ecod-tabs]');
        Array.prototype.forEach.call(boxes, function (box) {
            if (box._ecodTabs) return;
            box._ecodTabs = 1;

            var nav = box.querySelector('.ecod_tabs__nav');
            var btns = box.querySelectorAll('[data-ecod-tab]');
            var panels = box.querySelectorAll('[data-ecod-panel]');
            if (!nav) return;

            function activate(index, moveFocus) {
                var key = btns[index].getAttribute('data-ecod-tab');
                Array.prototype.forEach.call(btns, function (one, i) {
                    var on = i === index;
                    one.classList.toggle('is-active', on);
                    one.setAttribute('aria-selected', on ? 'true' : 'false');
                    /* roving tabindex：Tab 只进当前页签，页签间用方向键 */
                    one.setAttribute('tabindex', on ? '0' : '-1');
                    if (on && moveFocus) one.focus();
                });
                Array.prototype.forEach.call(panels, function (one) {
                    one.classList.toggle('is-active', one.getAttribute('data-ecod-panel') === key);
                });
            }

            nav.addEventListener('click', function (e) {
                var btn = e.target.closest ? e.target.closest('[data-ecod-tab]') : null;
                if (!btn) return;
                e.preventDefault();
                activate(Array.prototype.indexOf.call(btns, btn), false);
            });

            /* 与登录页三合一 tabs 同一套键盘约定：←→↑↓ 循环 + Home/End */
            nav.addEventListener('keydown', function (e) {
                var list = Array.prototype.slice.call(btns);
                var current = list.indexOf(document.activeElement);
                if (current < 0) return;
                var next = -1;
                if (e.key === 'ArrowRight' || e.key === 'ArrowDown') next = (current + 1) % list.length;
                else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') next = (current - 1 + list.length) % list.length;
                else if (e.key === 'Home') next = 0;
                else if (e.key === 'End') next = list.length - 1;
                if (next < 0) return;
                e.preventDefault();
                activate(next, true);
            });
        });
    };

    Ecod.initComment = function () {
        var form = document.querySelector('[data-ecod-comment]');
        if (!form) return;

        var ta = form.querySelector('[data-ecod-textarea]');
        var counter = form.querySelector('[data-ecod-count]');
        var tip = form.querySelector('[data-ecod-tip]');
        var submit = form.querySelector('[data-ecod-submit]');
        var hp = form.querySelector('input[name="ecod_hp"]');
        var cap = form.querySelector('input[name="captcha"]');
        var capImg = form.querySelector('[data-ecod-captcha]');
        var loaded = Date.now();
        var sending = false;

        function showTip(msg) {
            if (!tip) return;
            tip.textContent = msg || '';
            tip.hidden = !msg;
        }

        /* 验证码一次一换：提交失败（含验证码输错）后必须换图，否则重试必然失败 */
        function refreshCap() {
            if (capImg && CFG.api) {
                capImg.src = CFG.api + '?action=captcha&t=' + Date.now();
            }
            if (cap) {
                cap.value = '';
            }
        }

        /* 移动端（≤719px）：验证码行隐藏，提交时以底部弹层出现 */
        var capField = form.querySelector('.ecod_field--cap');
        var capOk = form.querySelector('[data-ecod-cap-ok]');
        var capCancel = form.querySelector('[data-ecod-cap-cancel]');
        var capMq = window.matchMedia('(max-width: 719px)');

        function capSyncReq() {
            /* 弹层态下隐藏的 required 输入会让原生校验静默卡死，改为 JS 手动验 */
            if (cap) {
                cap.required = !capMq.matches;
            }
        }
        capSyncReq();
        if (capMq.addEventListener) {
            capMq.addEventListener('change', capSyncReq);
        }

        function capModalOpen() {
            if (!capField) return;
            /* fixed 会被带 transform 的祖先劫持成局部定位，弹层态挂到 body 下 */
            if (!capField._ecodHome) {
                capField._ecodHome = { parent: capField.parentNode, next: capField.nextSibling };
            }
            if (capField.parentNode !== document.body) {
                document.body.appendChild(capField);
            }
            capField.classList.add('is-modal');
            setTimeout(function () { if (cap) cap.focus(); }, 90);
        }

        function capModalClose() {
            if (!capField) return;
            capField.classList.remove('is-modal');
            /* 归位回表单：FormData(form) 只收集表单内控件，提交前必须放回 */
            var home = capField._ecodHome;
            if (home && capField.parentNode === document.body) {
                home.parent.insertBefore(capField, home.next);
            }
        }

        if (capField) {
            if (capOk) {
                capOk.addEventListener('click', function () {
                    if (cap && cap.value.trim() === '') {
                        cap.focus();
                        return;
                    }
                    capModalClose();
                    /* 回到表单提交：验证码已填，后续校验照常走 */
                    if (submit) {
                        submit.click();
                    } else if (form.requestSubmit) {
                        form.requestSubmit();
                    }
                });
            }
            if (capCancel) {
                capCancel.addEventListener('click', capModalClose);
            }
        }

        function grow() {
            if (!ta) return;
            var n = ta.value.trim().length;
            if (counter) {
                counter.textContent = n + ' 字';
                counter.classList.toggle('is-warn', n > 1200);
            }
            ta.style.height = 'auto';
            ta.style.height = Math.min(Math.max(ta.scrollHeight, 96), 340) + 'px';
        }

        if (ta) {
            once(ta, 'cmtGrow', 'input', grow);
            grow();
        }

        /* 错误页正文里抠提示文案（Typecho 前台异常页是极简 HTML） */
        function extractError(html) {
            if (!html) return '提交失败，请重试';
            var m = html.match(/<h2[^>]*>([\s\S]*?)<\/h2>/i);
            if (m) {
                var text = m[1].replace(/<[^>]*>/g, '').trim();
                if (text) return text;
            }
            return '提交失败，请重试';
        }

        /* 收集一段 HTML 里的评论节点 id（li-comment-123 / comment-123） */
        function collectCoids(root) {
            var ids = [];
            root.querySelectorAll('[id*="comment-"]').forEach(function (el) {
                var m = (el.id || '').match(/(\d+)\s*$/);
                if (m) ids.push(parseInt(m[1], 10));
            });
            return ids;
        }

        once(form, 'cmtSubmit', 'submit', function (e) {
            e.preventDefault();
            if (sending) {
                return;
            }

            var text = ta ? ta.value.trim() : '';

            /* 蜜罐：机器人往往会填写所有字段 */
            if (hp && hp.value.trim() !== '') {
                showTip('提交未通过校验，请刷新页面后重试。');
                return;
            }
            if (cap && cap.value.trim() === '') {
                if (capMq.matches) {
                    /* 移动端：弹层里完成验证码 */
                    showTip('');
                    capModalOpen();
                } else {
                    showTip('请输入图中的验证码。');
                    cap.focus();
                }
                return;
            }
            if (text.length < 2) {
                showTip('内容太短了，至少写两个字吧。');
                if (ta) ta.focus();
                return;
            }
            var links = (text.match(/https?:\/\/|www\./gi) || []).length;
            if (links > 3) {
                showTip('评论里的链接太多了，最多 3 个。');
                return;
            }

            /* Turnstile：没拿到 token 就先别提交，服务端同样会校验 */
            if (CFG.comments && CFG.comments.turnstile) {
                var token = '';
                try {
                    token = window.turnstile && window.turnstile.getResponse ? window.turnstile.getResponse() : '';
                } catch (err) {}
                if (!token) {
                    var field = form.querySelector('input[name="cf-turnstile-response"]');
                    token = field ? field.value : '';
                }
                if (!token) {
                    showTip('请先完成上方的验证。');
                    return;
                }
            }
            if (Date.now() - loaded < 2500 && text.length < 10) {
                showTip('提交太快了，确认内容后再试一次。');
                return;
            }

            showTip('');
            sending = true;
            if (submit) {
                submit.disabled = true;
                submit.textContent = '发送中…';
            }

            /* 走原生评论入口，成功后局部替换评论区，不打断 PJAX */
            fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                redirect: 'follow',
                body: new FormData(form)
            }).then(function (res) {
                return res.text().then(function (html) {
                    return { ok: res.ok, html: html };
                });
            }).then(function (r) {
                sending = false;

                if (!r.ok) {
                    showTip(extractError(r.html));
                    refreshCap();
                    if (submit) { submit.disabled = false; submit.textContent = '发表评论'; }
                    return;
                }

                var oldMax = Math.max.apply(null, [0].concat(collectCoids(document)));
                var doc = new DOMParser().parseFromString(r.html, 'text/html');
                var fresh = doc.getElementById('comments');
                var current = document.getElementById('comments');

                if (!fresh || !current) {
                    /* 页面结构对不上时退回整页刷新 */
                    location.reload();
                    return;
                }

                current.parentNode.replaceChild(document.adoptNode(fresh), current);

                /* 替换后再补 nofollow 等处理，并重跑全部初始化 */
                Ecod.refresh();

                /* 旧闭包里的元素已随替换游离，取新 DOM 里的表单元素来收尾 */
                var newForm = document.querySelector('[data-ecod-comment]');
                var newTip = newForm ? newForm.querySelector('[data-ecod-tip]') : null;
                var newTa = newForm ? newForm.querySelector('[data-ecod-textarea]') : null;
                var newSubmit = newForm ? newForm.querySelector('[data-ecod-submit]') : null;

                function freshTip(msg) {
                    if (!newTip) return;
                    newTip.textContent = msg || '';
                    newTip.hidden = !msg;
                }

                var ids = collectCoids(document);
                var maxId = Math.max.apply(null, [0].concat(ids));
                if (maxId > oldMax) {
                    freshTip('');
                    var target = document.getElementById('li-comment-' + maxId)
                        || document.getElementById('comment-' + maxId);
                    if (target) {
                        try {
                            target.scrollIntoView({ block: 'center', behavior: Ecod.scrollBehavior() });
                        } catch (err) {
                            target.scrollIntoView();
                        }
                    }
                    if (newTa) {
                        newTa.value = '';
                        newTa.style.height = '';
                        newForm.querySelector('[data-ecod-count]').textContent = '0 字';
                    }
                } else {
                    /* 评论进了审核队列，列表里暂时看不到 */
                    freshTip('评论已提交，审核通过后将会显示。');
                }

                if (newSubmit) { newSubmit.disabled = false; newSubmit.textContent = '发表评论'; }
            }).catch(function () {
                sending = false;
                showTip('网络异常，请重试。');
                refreshCap();
                if (submit) { submit.disabled = false; submit.textContent = '发表评论'; }
            });
        });

        /* 评论里的站外链接加 nofollow 并在新窗口打开 */
        document.querySelectorAll('.comment-content a[href]').forEach(function (a) {
            if (a.hostname && a.hostname !== location.hostname) {
                a.setAttribute('rel', 'nofollow noopener');
                a.setAttribute('target', '_blank');
            }
        });
    };

    /* ---------- 评论回复：自己接管，避免 PJAX 后 Typecho 内置脚本里的表单 id 过期 ---------- */
    Ecod.initReply = function () {
        if (document._ecodReply) return;
        document._ecodReply = 1;

        function respondBox() {
            return document.querySelector('.ecod_respond');
        }

        function setParent(coid) {
            var box = respondBox();
            var form = box ? box.querySelector('form') : null;
            if (!form) return null;
            var input = form.querySelector('input[name="parent"]');
            if (!coid) {
                if (input) input.parentNode.removeChild(input);
                return null;
            }
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'parent';
                form.appendChild(input);
            }
            input.value = String(coid);
            return form;
        }

        function showBox(box, on) {
            var cancel = box.querySelector('#cancel-comment-reply-link');
            if (cancel) cancel.style.display = on ? '' : 'none';
            var to = box.querySelector('[data-ecod-replyto]');
            if (to) {
                to.hidden = !on;
                if (!on) to.textContent = '';
            }
        }

        function reply(li, coid) {
            var box = respondBox();
            if (!box) return;

            /* 第一次回复前，先记住表单原来的位置，取消时还原 */
            var holderId = box.id + '-holder';
            if (!document.getElementById(holderId)) {
                var holder = document.createElement('div');
                holder.id = holderId;
                box.parentNode.insertBefore(holder, box);
            }

            if (!setParent(coid)) return;

            var act = li.querySelector('.ecod_cmt__act');
            var replyBar = li.querySelector('.comment-reply');
            if (act && act.parentNode) {
                act.parentNode.insertBefore(box, act.nextSibling);
            } else if (replyBar && replyBar.parentNode === li) {
                li.insertBefore(box, replyBar.nextSibling);
            } else {
                li.appendChild(box);
            }

            var who = li.querySelector('.ecod_cmt__name') || li.querySelector('.comment-author cite');
            var to = box.querySelector('[data-ecod-replyto]');
            if (to) {
                to.textContent = who ? '正在回复 ' + who.textContent.trim() : '正在回复这条评论';
                to.hidden = false;
            }
            showBox(box, true);

            var ta = box.querySelector('textarea[name="text"]');
            if (ta) {
                try { ta.focus({ preventScroll: true }); } catch (err) { ta.focus(); }
            }
            try {
                box.scrollIntoView({ block: 'nearest', behavior: Ecod.scrollBehavior() });
            } catch (err) {
                box.scrollIntoView(false);
            }
        }

        function cancelReply() {
            var box = respondBox();
            if (!box) return;
            setParent(0);
            showBox(box, false);

            var holder = document.getElementById(box.id + '-holder');
            if (holder && holder.parentNode) {
                holder.parentNode.insertBefore(box, holder);
            }
        }

        /* 捕获阶段先拦住，替换掉 Typecho 输出在 onclick 上的旧实现 */
        document.addEventListener('click', function (e) {
            var t = e.target;
            if (!t || !t.closest) return;

            var cancel = t.closest('#cancel-comment-reply-link');
            if (cancel) {
                e.preventDefault();
                e.stopPropagation();
                cancelReply();
                return;
            }

            var link = t.closest('.ecod_cmt__act a, .comment-reply a');
            if (!link || link.classList.contains('ecod_cmt__anchor')) return;

            var li = link.closest('li[id^="comment-"]');
            if (!li) return;
            var coid = (li.id || '').replace('comment-', '');
            if (!coid) return;

            e.preventDefault();
            e.stopPropagation();
            reply(li, coid);
        }, true);
    };

    /* ---------- 评论嵌套：深层回复折叠 / 展开 ---------- */
    Ecod.initCommentFold = function () {
        function setFold(btn, open) {
            var box = btn.nextElementSibling;
            if (!box || !box.classList.contains('ecod_cmt__children')) return;

            box.hidden = !open;
            btn.classList.toggle('is-open', open);
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');

            var label = btn.querySelector('[data-ecod-fold-text]');
            if (label) {
                var more = btn.getAttribute('data-more') || '';
                label.textContent = open ? '收起回复' : '展开 ' + more + ' 条回复';
            }
        }

        /* 顶部总按钮：没有折叠节点（评论少、或已设「全部展开」）时保持隐藏 */
        function syncAll() {
            var master = document.querySelector('[data-ecod-fold-all]');
            if (!master) return;

            var folds = document.querySelectorAll('[data-ecod-fold]');
            if (!folds.length) {
                master.hidden = true;
                return;
            }

            var closed = 0;
            for (var i = 0; i < folds.length; i++) {
                if (folds[i].getAttribute('aria-expanded') !== 'true') {
                    closed++;
                }
            }

            var allOpen = closed === 0;
            master.hidden = false;
            master.classList.toggle('is-open', allOpen);
            master.setAttribute('aria-expanded', allOpen ? 'true' : 'false');

            var text = master.querySelector('[data-ecod-fold-all-text]');
            if (text) {
                text.textContent = allOpen ? '收起全部回复' : '展开全部回复';
            }
        }

        once(document, 'cmtFold', 'click', function (e) {
            var t = e.target;
            if (!t || !t.closest) return;

            var master = t.closest('[data-ecod-fold-all]');
            if (master) {
                e.preventDefault();
                var expand = master.getAttribute('aria-expanded') !== 'true';
                var folds = document.querySelectorAll('[data-ecod-fold]');
                /* 从外到内逐个铺开：父级 hidden 时子级节点仍在 DOM 里 */
                for (var i = 0; i < folds.length; i++) {
                    setFold(folds[i], expand);
                }
                syncAll();
                return;
            }

            var btn = t.closest('[data-ecod-fold]');
            if (!btn) return;
            e.preventDefault();
            setFold(btn, btn.getAttribute('aria-expanded') !== 'true');
            syncAll();
        });

        syncAll();
    };

    /* ---------- 人机验证 Turnstile：按需加载 + 每个表单渲染一次 ---------- */
    Ecod.initTurnstile = function () {
        var box = document.querySelector('[data-ecod-turnstile]');
        if (!box || !box.getAttribute('data-sitekey')) return;
        if (box.dataset.ecodRendered) return;

        function render() {
            if (box.dataset.ecodRendered || !window.turnstile) return;
            box.dataset.ecodRendered = '1';
            try {
                window.turnstile.render(box, {
                    sitekey: box.getAttribute('data-sitekey'),
                    theme: root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light',
                    language: 'zh-cn'
                });
            } catch (err) {
                box.dataset.ecodRendered = '';
            }
        }

        if (window.turnstile) {
            render();
            return;
        }

        var lib = document.getElementById('ecod-turnstile-lib');
        if (!lib) {
            lib = document.createElement('script');
            lib.id = 'ecod-turnstile-lib';
            lib.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
            lib.async = true;
            lib.defer = true;
            document.head.appendChild(lib);
        }
        lib.addEventListener('load', render);
    };

    /* ---------- 第三方评论系统：Twikoo / Waline / Artalk / Giscus ---------- */
    var THIRD_SRC = {
        twikoo: 'https://cdn.jsdelivr.net/npm/twikoo@1.6.44/dist/twikoo.min.js',
        waline: 'https://cdn.jsdelivr.net/npm/@waline/client@3/dist/waline.js',
        artalk: 'https://cdn.jsdelivr.net/npm/artalk@2.9.1/dist/Artalk.js'
    };

    function loadThird(key) {
        return new Promise(function (resolve, reject) {
            var id = 'ecod-third-' + key;
            var old = document.getElementById(id);
            if (old) {
                if (old.dataset.loaded) { resolve(); } else {
                    old.addEventListener('load', function () { resolve(); });
                    old.addEventListener('error', function () { reject(new Error('load')); });
                }
                return;
            }
            var s = document.createElement('script');
            s.id = id;
            s.src = THIRD_SRC[key];
            s.async = true;
            s.addEventListener('load', function () {
                s.dataset.loaded = '1';
                resolve();
            });
            s.addEventListener('error', function () { reject(new Error('load')); });
            document.head.appendChild(s);
        });
    }

    Ecod.initThirdComments = function () {
        var host = document.querySelector('[data-ecod-third]');
        if (!host || host.dataset.ecodInit) return;
        host.dataset.ecodInit = '1';

        var type = host.getAttribute('data-ecod-third') || '';
        var cfg = (CFG.comments && CFG.comments.third) || {};
        var tip = document.querySelector('[data-ecod-third-tip]');

        function fail(msg) {
            if (tip) {
                tip.textContent = msg;
                tip.hidden = false;
            }
            host.classList.add('is-empty');
        }

        if (type === 'giscus') {
            if (!cfg.repo || !cfg.repoId) { fail('Giscus 还没配置完整：仓库与 Repo ID 都是必填。'); return; }
            var s = document.createElement('script');
            s.src = 'https://giscus.app/client.js';
            s.async = true;
            s.crossOrigin = 'anonymous';
            s.setAttribute('data-repo', cfg.repo);
            s.setAttribute('data-repo-id', cfg.repoId);
            s.setAttribute('data-category', cfg.category || '');
            s.setAttribute('data-category-id', cfg.categoryId || '');
            s.setAttribute('data-mapping', cfg.mapping || 'pathname');
            s.setAttribute('data-strict', '0');
            s.setAttribute('data-reactions-enabled', '1');
            s.setAttribute('data-emit-metadata', '0');
            s.setAttribute('data-input-position', 'bottom');
            s.setAttribute('data-theme', root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light');
            s.setAttribute('data-lang', 'zh-CN');
            host.appendChild(s);
            return;
        }

        if (type === 'twikoo') {
            if (!cfg.env) { fail('还没填 Twikoo 环境地址。'); return; }
            loadThird('twikoo').then(function () {
                if (!window.twikoo || !window.twikoo.init) { fail('Twikoo 脚本加载失败，检查网络或 CDN。'); return; }
                window.twikoo.init({ envId: cfg.env, el: host, lang: 'zh-CN' });
            }).catch(function () { fail('Twikoo 脚本加载失败，检查网络或 CDN。'); });
            return;
        }

        if (type === 'waline') {
            if (!cfg.server) { fail('还没填 Waline 服务地址。'); return; }
            loadThird('waline').then(function () {
                if (!window.Waline || !window.Waline.init) { fail('Waline 脚本加载失败，检查网络或 CDN。'); return; }
                window.Waline.init({
                    el: host,
                    serverURL: cfg.server,
                    lang: 'zh-CN',
                    dark: 'html[data-theme="dark"]',
                    login: 'enable',
                    pageview: false,
                    search: false
                });
            }).catch(function () { fail('Waline 脚本加载失败，检查网络或 CDN。'); });
            return;
        }

        if (type === 'artalk') {
            if (!cfg.server) { fail('还没填 Artalk 服务地址。'); return; }
            loadThird('artalk').then(function () {
                if (!window.Artalk) { fail('Artalk 脚本加载失败，检查网络或 CDN。'); return; }
                new window.Artalk({
                    el: host,
                    server: cfg.server,
                    pageKey: location.pathname,
                    pageTitle: document.title,
                    darkMode: root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light'
                });
            }).catch(function () { fail('Artalk 脚本加载失败，检查网络或 CDN。'); });
        }
    };

    /* ---------- 代码高亮（Prism 就绪后重跑，PJAX 换页也生效） ---------- */
    Ecod.initPrism = function () {
        if (!CFG.prism || !window.Prism || !window.Prism.highlightAll) {
            return;
        }

        /* Typecho 的 Markdown 不带语言类名，补一个空语法让配色主题覆盖所有代码块 */
        Array.prototype.forEach.call(
            document.querySelectorAll('.ecod_content pre > code'),
            function (code) {
                if (!/(^|\s)language-/.test(code.className)) {
                    code.classList.add('language-none');
                }
                /* Prism 主题的背景挂在 pre[class*=language-] 上，同步给父级 */
                if (code.parentNode && code.parentNode.tagName === 'PRE'
                    && !/(^|\s)language-/.test(code.parentNode.className)) {
                    code.parentNode.className = code.className;
                }
            }
        );

        try {
            window.Prism.highlightAll();
        } catch (e) { /* 语法不识别时不影响页面 */ }
    };

    /* ---------- 扩展短代码：复制块 / 播放列表 / GitHub 卡 ---------- */
    Ecod.initExtras = function () {
        /* 复制（事件委托，只绑一次） */
        once(document, 'sccopy', 'click', function (e) {
            var btn = e.target.closest ? e.target.closest('[data-ecod-copy]') : null;
            if (!btn) return;
            e.preventDefault();
            copyText(btn.getAttribute('data-ecod-copy') || '', function () {
                var label = btn.querySelector('span');
                var old = label ? label.textContent : '';
                btn.classList.add('is-done');
                if (label) label.textContent = '已复制';
                setTimeout(function () {
                    btn.classList.remove('is-done');
                    if (label) label.textContent = old;
                }, 1600);
            });
        });

        /* 播放列表：点条目切歌 */
        Array.prototype.forEach.call(document.querySelectorAll('[data-ecod-playlist]'), function (box) {
            if (box._ecodPl) return;
            box._ecodPl = 1;

            var audio = box.querySelector('[data-ecod-audio]');
            var now = box.querySelector('[data-ecod-now]');
            var cover = box.querySelector('.ecod_playlist__cover');
            if (!audio) return;

            box.addEventListener('click', function (e) {
                var item = e.target.closest ? e.target.closest('[data-ecod-track]') : null;
                if (!item) return;

                var track;
                try { track = JSON.parse(item.getAttribute('data-ecod-track')); } catch (err) { return; }
                if (!track || !track.url) return;

                Array.prototype.forEach.call(box.querySelectorAll('[data-ecod-track]'), function (one) {
                    one.classList.toggle('is-active', one === item);
                });

                audio.src = track.url;
                if (now) now.textContent = track.name || '';
                if (cover) cover.style.backgroundImage = track.cover ? 'url(' + track.cover + ')' : '';
                audio.play().catch(function () {});
            });
        });

        /* GitHub star 数：sessionStorage 缓存 6 小时，失败静默 */
        Array.prototype.forEach.call(document.querySelectorAll('[data-ecod-gh]'), function (card) {
            if (card._ecodGh) return;
            card._ecodGh = 1;

            var repo = card.getAttribute('data-ecod-gh');
            var badge = card.querySelector('[data-ecod-gh-star] i');
            if (!repo || !badge) return;

            var key = 'ecod:gh:' + repo;
            try {
                var cached = JSON.parse(sessionStorage.getItem(key) || 'null');
                if (cached && Date.now() - cached.t < 21600000) {
                    badge.textContent = shortNum(cached.n);
                    return;
                }
            } catch (err) {}

            fetch('https://api.github.com/repos/' + repo, { headers: { Accept: 'application/vnd.github+json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (!data || typeof data.stargazers_count !== 'number') return;
                    badge.textContent = shortNum(data.stargazers_count);
                    try { sessionStorage.setItem(key, JSON.stringify({ n: data.stargazers_count, t: Date.now() })); } catch (err) {}
                })
                .catch(function () {});
        });
    };

    /* ---------- AI 摘要：文章页异步取数，失败静默隐藏 ----------
       v2（设计稿 guaqi-reference §F1）：模型名角标、字数统计、
       EAIMotion 打字机回放（尊重系统减少动态效果） */
    Ecod.initAi = function () {
        var box = document.querySelector('[data-ecod-ai]');
        if (!box || box.dataset.aiReady === '1') {
            return;
        }
        var cid = box.getAttribute('data-cid');
        if (!cid) {
            return;
        }
        box.dataset.aiReady = '1';

        fetch(CFG.api + '?action=ai&cid=' + encodeURIComponent(cid), { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !data.ok || !data.summary) {
                    box.hidden = true;
                    return;
                }
                var body = box.querySelector('[data-ecod-ai-body]');
                var tag = box.querySelector('[data-ecod-ai-tag]');
                var count = box.querySelector('[data-ecod-ai-count]');
                if (tag && data.model) {
                    tag.textContent = data.model;
                    tag.hidden = false;
                }
                if (count) {
                    var n = data.summary.replace(/\s/g, '').length;
                    count.textContent = n + ' 字';
                    count.hidden = false;
                }
                if (body) {
                    body.removeAttribute('aria-busy');
                    var typeOn = CFG.aiMotion && !Ecod.reduceMotion();
                    if (!typeOn) {
                        body.textContent = data.summary;
                    } else {
                        /* 打字机回放：rAF 步进，2.5s 内播完，期间不阻塞交互 */
                        body.textContent = '';
                        var total = data.summary.length;
                        var start = 0;
                        var step = Math.max(2, Math.ceil(total / 90));
                        var tick = function () {
                            start = Math.min(total, start + step);
                            body.textContent = data.summary.slice(0, start);
                            if (start < total) {
                                requestAnimationFrame(tick);
                            }
                        };
                        requestAnimationFrame(tick);
                    }
                }
                box.hidden = false;
            })
            .catch(function () {
                box.hidden = true;
            });
    };

    /* ---------- 评论解锁卡（F2）：平滑滚到评论区并聚焦输入框 ---------- */
    Ecod.bindPayComment = function () {
        document.querySelectorAll('[data-ecod-paycomment]').forEach(function (btn) {
            if (btn.dataset.ecodBound) { return; }
            btn.dataset.ecodBound = '1';
            btn.addEventListener('click', function () {
                var target = document.getElementById('comments');
                if (!target) { return; }
                target.scrollIntoView({ behavior: Ecod.reduceMotion() ? 'auto' : 'smooth', block: 'start' });
                var input = document.getElementById('textarea');
                if (input && window.matchMedia('(min-width: 769px)').matches) {
                    setTimeout(function () { input.focus({ preventScroll: true }); }, Ecod.reduceMotion() ? 0 : 450);
                }
            });
        });
    };

    /* 友链一键申请：弹窗提交 + 管理员待审核面板 */
    Ecod.initFriendApply = function () {
        var modal = document.querySelector('[data-ecod-flink]');

        if (modal && !modal.dataset.ecodBound) {
            modal.dataset.ecodBound = '1';

            var fform = modal.querySelector('[data-ecod-flink-form]');
            var fmsg = modal.querySelector('[data-ecod-flink-msg]');
            var fsubmit = modal.querySelector('.ecod_fmodal__submit');
            var fdone = modal.querySelector('[data-ecod-flink-done]');
            var fdoneText = modal.querySelector('[data-ecod-flink-donetext]');

            var capSwap = function () {
                if (CFG.api) {
                    modal.querySelectorAll('[data-ecod-captcha]').forEach(function (img) {
                        img.src = CFG.api + '?action=captcha&t=' + Date.now();
                    });
                }
                var input = modal.querySelector('input[name="captcha"]');
                if (input) { input.value = ''; }
            };

            var show = function (on) {
                modal.hidden = !on;
                /* Esc / 焦点陷阱 / 滚动锁 / 焦点还原由弹层原语承担 */
                if (on) {
                    Ecod.dialogOpen(modal, modal.querySelector('[name="name"]'), function () { show(false); });
                } else {
                    Ecod.dialogClose(modal);
                }
            };

            /* 「先加本站」提示条：一键复制本站信息 */
            var fcopy = modal.querySelector('[data-ecod-flink-copy]');
            if (fcopy && !fcopy.dataset.ecodBound) {
                fcopy.dataset.ecodBound = '1';
                fcopy.addEventListener('click', function () {
                    var text = fcopy.getAttribute('data-ecod-flink-copy') || '';
                    var done = function () {
                        fcopy.textContent = '已复制';
                        fcopy.classList.add('is-done');
                        setTimeout(function () {
                            fcopy.textContent = '复制';
                            fcopy.classList.remove('is-done');
                        }, 1600);
                    };
                    var fallback = function () {
                        var ta = document.createElement('textarea');
                        ta.value = text;
                        ta.setAttribute('readonly', '');
                        ta.style.cssText = 'position:fixed;left:-9999px;top:0;opacity:0';
                        document.body.appendChild(ta);
                        ta.select();
                        try { document.execCommand('copy'); done(); } catch (err) {}
                        document.body.removeChild(ta);
                    };
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(done).catch(fallback);
                    } else {
                        fallback();
                    }
                });
            }

            /* 地址失焦：补 https:// + 头像占位提示换成该站图标 */
            var furl = modal.querySelector('input[name="url"]');
            var favatar = modal.querySelector('input[name="avatar"]');
            if (furl && !furl.dataset.ecodBound) {
                furl.dataset.ecodBound = '1';
                furl.addEventListener('blur', function () {
                    var v = (furl.value || '').trim();
                    if (v === '') { return; }
                    if (!/^[a-z][a-z0-9+.-]*:\/\//i.test(v)) {
                        furl.value = 'https://' + v.replace(/^\/+/, '');
                    }
                    if (!favatar) { return; }
                    try {
                        var host = new URL(furl.value).hostname.replace(/^www\./, '');
                        if (host) { favatar.placeholder = 'https://' + host + '/favicon.ico'; }
                    } catch (err) {}
                });
            }

            var reset = function () {
                if (fform) {
                    fform.reset();
                    fform.hidden = false;
                }
                if (favatar) { favatar.placeholder = '留空则使用站点图标'; }
                if (fmsg) { fmsg.hidden = true; fmsg.textContent = ''; fmsg.classList.remove('is-bad'); }
                if (fsubmit) { fsubmit.disabled = false; fsubmit.textContent = '提交申请'; }
                if (fdone) { fdone.hidden = true; }
                capSwap();
            };

            modal.addEventListener('click', function (e) {
                if (e.target.closest('[data-ecod-flink-close]')) {
                    e.preventDefault();
                    show(false);
                }
            });

            /* Esc 关闭由弹层原语承担 */

            if (fform) {
                fform.addEventListener('submit', function (e) {
                    e.preventDefault();
                    if (fsubmit && fsubmit.disabled) { return; }

                    var fd = new FormData(fform);
                    var token = fform.getAttribute('data-ecod-token') || '';
                    if (token) { fd.append('token', token); }
                    if (fmsg) { fmsg.hidden = true; fmsg.classList.remove('is-bad'); }
                    if (fsubmit) { fsubmit.disabled = true; fsubmit.textContent = '提交中…'; }

                    fetch((CFG.api || '') + '?action=friend_apply', {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: fd
                    }).then(function (r) {
                        return r.json().catch(function () { return { ok: false, error: '响应异常，请重试' }; });
                    }).then(function (data) {
                        if (data && data.ok) {
                            fform.hidden = true;
                            if (fdone) { fdone.hidden = false; }
                            if (fdoneText && data.msg) {
                                var mailOn = fdone && fdone.getAttribute('data-ecod-mail') === '1';
                                fdoneText.textContent = data.msg + (mailOn ? '，结果会发到你的邮箱。' : '。');
                            }
                            return;
                        }
                        if (fmsg) {
                            fmsg.textContent = (data && data.error) || '提交失败，请重试';
                            fmsg.hidden = false;
                            fmsg.classList.add('is-bad');
                        }
                        capSwap();
                        if (fsubmit) { fsubmit.disabled = false; fsubmit.textContent = '提交申请'; }
                    }).catch(function () {
                        if (fmsg) {
                            fmsg.textContent = '网络异常，请重试';
                            fmsg.hidden = false;
                            fmsg.classList.add('is-bad');
                        }
                        capSwap();
                        if (fsubmit) { fsubmit.disabled = false; fsubmit.textContent = '提交申请'; }
                    });
                });
            }

            modal._ecodOpen = function () { reset(); show(true); };
        }

        document.querySelectorAll('[data-ecod-flink-open]').forEach(function (btn) {
            if (btn.dataset.ecodBound) { return; }
            btn.dataset.ecodBound = '1';
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var m = document.querySelector('[data-ecod-flink]');
                if (!m) { return; }
                if (m._ecodOpen) { m._ecodOpen(); return; }
                m.hidden = false;
                Ecod.dialogOpen(m, m.querySelector('[name="name"]'), function () {
                    m.hidden = true;
                    Ecod.dialogClose(m);
                });
            });
        });

        /* 管理员审核：通过 / 忽略 */
        var review = document.querySelector('[data-ecod-review]');
        if (review && !review.dataset.ecodBound) {
            review.dataset.ecodBound = '1';
            var rtoken = review.getAttribute('data-ecod-token') || '';

            review.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-ecod-review-act]');
                if (!btn) { return; }
                var item = btn.closest('[data-ecod-review-item]');
                if (!item) { return; }

                var id = item.getAttribute('data-ecod-review-item');
                var decision = btn.getAttribute('data-ecod-review-act');
                var ops = item.querySelector('.ecod_review__ops');
                Array.prototype.forEach.call(item.querySelectorAll('button'), function (b) { b.disabled = true; });
                if (ops) {
                    var tip = document.createElement('span');
                    tip.className = 'ecod_review__meta';
                    tip.textContent = '处理中…';
                    ops.textContent = '';
                    ops.appendChild(tip);
                }

                var body = new FormData();
                body.append('id', id);
                body.append('decision', decision);
                if (rtoken) { body.append('token', rtoken); }

                fetch((CFG.api || '') + '?action=friend_review', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: body
                }).then(function (r) {
                    return r.json().catch(function () { return { ok: false, error: '响应异常' }; });
                }).then(function (data) {
                    if (!data || !data.ok) {
                        if (ops) {
                            ops.textContent = '';
                            var err = document.createElement('span');
                            err.className = 'ecod_review__meta';
                            err.textContent = (data && data.error) || '操作失败';
                            ops.appendChild(err);
                        }
                        return;
                    }

                    item.classList.add('is-done');
                    var badge = document.querySelector('.ecod_review__num');
                    if (badge) { badge.textContent = data.pending; }

                    /* 通过后正文已追加新行，整页刷新让卡片立刻出现 */
                    if (decision === 'approve') {
                        setTimeout(function () { location.reload(); }, 600);
                        return;
                    }
                    item.parentNode.removeChild(item);
                    if (!document.querySelector('[data-ecod-review-item]')) {
                        var panel = document.getElementById('ecod-review');
                        if (panel) { panel.parentNode.removeChild(panel); }
                    }
                }).catch(function () {
                    if (ops) {
                        ops.textContent = '';
                        var err = document.createElement('span');
                        err.className = 'ecod_review__meta';
                        err.textContent = '网络异常，请重试';
                        ops.appendChild(err);
                    }
                });
            });
        }
    };

    /* ---------- 正文 oEmbed：社交卡片脚本按需加载 ---------- */
    var EMBED_JS = {
        twitter: 'https://platform.twitter.com/widgets.js',
        instagram: 'https://www.instagram.com/embed.js',
        tiktok: 'https://www.tiktok.com/embed.js'
    };
    var embedState = {};
    var tiktokReloading = false;

    function refreshEmbed(key) {
        try {
            if (key === 'twitter' && window.twttr && window.twttr.widgets) {
                window.twttr.widgets.load();
            } else if (key === 'instagram' && window.instgrm && window.instgrm.Embeds) {
                window.instgrm.Embeds.process();
            }
        } catch (e) { /* 脚本内部异常不影响页面 */ }
    }

    function loadEmbed(key) {
        var src = EMBED_JS[key];
        if (!src || embedState[key] === 'loading' || embedState[key] === 'ready') return;
        embedState[key] = 'loading';
        var s = document.createElement('script');
        s.src = src;
        s.async = true;
        s.setAttribute('data-ecod-oembed-js', key);
        s.onload = function () {
            embedState[key] = 'ready';
            /* TikTok 的 embed.js 只在加载时扫一遍文档，交给 reloadEmbed 处理 */
            if (key !== 'tiktok') refreshEmbed(key);
        };
        s.onerror = function () { embedState[key] = 'error'; };
        document.body.appendChild(s);
    }

    /* embed.js 只在加载时扫文档，换页后重新注入一次让新卡片生效 */
    function reloadTiktok() {
        if (tiktokReloading) return;
        tiktokReloading = true;
        window.setTimeout(function () { tiktokReloading = false; }, 1500);

        var old = document.querySelector('script[data-ecod-oembed-js="tiktok"]');
        if (old && old.parentNode) old.parentNode.removeChild(old);
        embedState.tiktok = '';
        loadEmbed('tiktok');
    }

    Ecod.initEmbeds = function () {
        var nodes = document.querySelectorAll('.ecod_oembed--social[data-ecod-oembed]');
        if (!nodes.length) return;

        var pending = [];
        for (var i = 0; i < nodes.length; i++) {
            if (nodes[i].getAttribute('data-emb') !== '1') pending.push(nodes[i]);
        }
        if (!pending.length) return;

        function trigger(el) {
            if (el.getAttribute('data-emb') === '1') return;
            el.setAttribute('data-emb', '1');

            var key = el.getAttribute('data-ecod-oembed');
            if (!key || !EMBED_JS[key]) return;

            if (embedState[key] === 'ready') {
                if (key === 'tiktok') { reloadTiktok(); } else { refreshEmbed(key); }
                return;
            }
            loadEmbed(key);
        }

        /* 进入视口前 360px 才请求第三方脚本；不支持 IntersectionObserver 时直接加载 */
        if (!('IntersectionObserver' in window)) {
            for (var j = 0; j < pending.length; j++) trigger(pending[j]);
            return;
        }

        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                io.unobserve(entry.target);
                trigger(entry.target);
            });
        }, { rootMargin: '360px 0px' });

        pending.forEach(function (el) { io.observe(el); });
    };

    /* ---------- 行内标记：剧透展开 / 悬停提示贴边 ---------- */
    Ecod.initInline = function () {
        /* PJAX 换页会重复调用，事件只在 document 上绑一次 */
        if (document._ecodInline) {
            return;
        }
        document._ecodInline = 1;

        function toggleSpoiler(el) {
            var open = el.classList.toggle('is-open');
            el.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        document.addEventListener('click', function (e) {
            var sp = e.target.closest ? e.target.closest('[data-ecod-spoiler]') : null;
            if (!sp) {
                return;
            }
            /* 展开后的内容里如果有链接，点击链接不收回遮罩 */
            var link = e.target.closest ? e.target.closest('a[href]') : null;
            if (link && sp.contains(link)) {
                return;
            }
            toggleSpoiler(sp);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ' && e.key !== 'Spacebar') {
                return;
            }
            var sp = e.target.closest ? e.target.closest('[data-ecod-spoiler]') : null;
            if (!sp || e.target !== sp) {
                return;
            }
            e.preventDefault();
            toggleSpoiler(sp);
        });

        /* 气泡贴着视口边缘时会溢出，按需横向平移（--shift 交给 CSS 用） */
        function fitTip(pop) {
            pop.style.setProperty('--shift', '0px');
            var host = pop.parentNode;
            if (!host || !host.getBoundingClientRect) {
                return;
            }
            var rect = host.getBoundingClientRect();
            var half = pop.offsetWidth / 2;
            var center = rect.left + rect.width / 2;
            var pad = 10;
            var shift = 0;

            if (center - half < pad) {
                shift = pad - (center - half);
            } else if (center + half > window.innerWidth - pad) {
                shift = (window.innerWidth - pad) - (center + half);
            }

            var limit = Math.max(0, half - 16);
            shift = Math.max(-limit, Math.min(limit, shift));
            pop.style.setProperty('--shift', Math.round(shift) + 'px');
        }

        document.addEventListener('mouseover', function (e) {
            var el = e.target.closest ? e.target.closest('.ecod_tip') : null;
            if (!el) {
                return;
            }
            var from = e.relatedTarget && e.relatedTarget.closest ? e.relatedTarget.closest('.ecod_tip') : null;
            if (from === el) {
                return;
            }
            var pop = el.querySelector('.ecod_tip__pop');
            if (pop) {
                fitTip(pop);
            }
        });

        document.addEventListener('focusin', function (e) {
            var el = e.target.closest ? e.target.closest('.ecod_tip') : null;
            if (!el) {
                return;
            }
            var pop = el.querySelector('.ecod_tip__pop');
            if (pop) {
                fitTip(pop);
            }
        });
    };

    /* ------------------------------------------------------------------ *
     * 收银台：下单 / 轮询 / 卡密兑换
     *
     * 页面只读：所有写操作都走 api.php，GET 请求不会产生订单。
     * ------------------------------------------------------------------ */
    Ecod.initCashier = function () {
        var root = document.querySelector('[data-ecod-cashier]');
        if (!root || root._ecodPay) {
            return;
        }
        root._ecodPay = 1;

        var api = root.getAttribute('data-api') || 'api.php';
        var token = root.getAttribute('data-token') || '';
        var kind = root.getAttribute('data-kind') || 'post';
        var cid = root.getAttribute('data-cid') || '0';
        var level = root.getAttribute('data-level') || '1';
        var plan = root.getAttribute('data-plan') || 'month';
        var permalink = root.getAttribute('data-permalink') || '';
        /* ?order_no= 直达：对已有订单续付，不新建订单 */
        var existOrder = root.getAttribute('data-order-no') || '';

        var submit = root.querySelector('[data-cashier-submit]');
        var pane = root.querySelector('[data-cashier-pane]');
        var status = root.querySelector('[data-cashier-status]');
        var orderBox = root.querySelector('[data-cashier-order]');
        var methods = root.querySelector('[data-cashier-methods]');
        var card = root.querySelector('[data-cashier-card]');
        var codeInput = root.querySelector('[data-cashier-code]');
        var redeem = root.querySelector('[data-cashier-redeem]');
        var timer = null;

        function say(text, kindOf) {
            if (!status) return;
            status.hidden = false;
            status.className = 'ecod_cashier__status' + (kindOf ? ' is-' + kindOf : '');
            status.textContent = text;
        }

        function currentChannel() {
            var on = methods ? methods.querySelector('.is-on') : null;
            return on ? on.getAttribute('data-channel') : 'manual';
        }

        function currentPaytype() {
            var on = methods ? methods.querySelector('.is-on') : null;
            return on ? (on.getAttribute('data-paytype') || '') : '';
        }

        function stopPolling() {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
        }

        function done(message) {
            stopPolling();
            say(message || '支付成功，正在返回…', 'ok');
            var ucUrl = root.getAttribute('data-uc') || '';
            window.setTimeout(function () {
                if (kind === 'post' && permalink) {
                    window.location.href = permalink;
                } else if (kind === 'vip' && ucUrl) {
                    /* 会员订单：回个人中心「我的会员」tab 直观看到新身份 */
                    window.location.href = ucUrl + '#vip';
                } else {
                    window.location.reload();
                }
            }, 900);
        }

        function poll(orderNo) {
            stopPolling();
            var started = Date.now();
            timer = window.setInterval(function () {
                /* 最多轮询 5 分钟，避免无人看管的页面一直请求 */
                if (Date.now() - started > 300000) {
                    stopPolling();
                    say('暂时没有收到支付结果，如已付款请联系站长确认。', 'bad');
                    return;
                }
                var fd = new FormData();
                fd.append('order_no', orderNo);
                fetch(api + '?action=pay_status&order_no=' + encodeURIComponent(orderNo), {
                    credentials: 'same-origin'
                }).then(function (r) { return r.json(); }).then(function (json) {
                    if (!json || !json.ok) return;
                    if (json.status === 'paid') {
                        done('支付成功，正在解锁…');
                    } else if (json.status === 'closed') {
                        stopPolling();
                        say('订单已关闭，请重新下单。', 'bad');
                    }
                }).catch(function () { /* 网络抖动，下一轮再试 */ });
            }, 2000);
        }

        function submitOrder() {
            if (!submit || submit.disabled) return;
            var fd = new FormData();
            if (existOrder) {
                fd.append('order_no', existOrder);
            } else {
                fd.append('kind', kind);
                fd.append('cid', cid);
                fd.append('level', level);
                fd.append('plan', plan);
            }
            fd.append('channel', currentChannel());
            var paytype = currentPaytype();
            if (paytype) fd.append('paytype', paytype);
            fd.append('token', token);

            submit.disabled = true;
            say(existOrder ? '正在发起支付…' : '正在生成订单…');

            fetch(api + '?action=' + (existOrder ? 'pay_order' : 'pay_create'), {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); }).then(function (json) {
                submit.disabled = false;
                if (!json || !json.ok) {
                    say((json && json.error) || '下单失败，请稍后再试', 'bad');
                    return;
                }
                if (json.unlocked) {
                    done(json.message || '你已经有权限了');
                    return;
                }
                if (json.mode === 'redirect' && json.url) {
                    say('正在跳转到支付页面…');
                    window.location.href = json.url;
                    return;
                }

                submit.hidden = true;
                if (orderBox) {
                    orderBox.hidden = false;
                    orderBox.textContent = '订单号：' + json.order_no;
                }
                if (pane && json.html) {
                    pane.innerHTML = json.html;
                } else if (pane && json.tip) {
                    pane.innerHTML = '<p>' + json.tip + '</p>';
                }
                say('订单已生成，等待确认收款。确认后本页会自动刷新。');
                poll(json.order_no);
            }).catch(function () {
                submit.disabled = false;
                say('网络异常，请重试', 'bad');
            });
        }

        function redeemCard() {
            if (!codeInput || !redeem || redeem.disabled) return;
            var code = (codeInput.value || '').trim();
            if (!code) {
                say('请先填写卡密', 'bad');
                return;
            }
            var fd = new FormData();
            fd.append('code', code);
            fd.append('token', token);
            redeem.disabled = true;
            say('正在核销…');

            fetch(api + '?action=pay_card', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); }).then(function (json) {
                redeem.disabled = false;
                if (!json || !json.ok) {
                    say((json && json.error) || '兑换失败', 'bad');
                    return;
                }
                done('兑换成功，正在解锁…');
            }).catch(function () {
                redeem.disabled = false;
                say('网络异常，请重试', 'bad');
            });
        }

        if (methods) {
            methods.addEventListener('click', function (e) {
                var btn = e.target.closest ? e.target.closest('[data-channel]') : null;
                if (!btn) return;
                Array.prototype.forEach.call(methods.querySelectorAll('[data-channel]'), function (el) {
                    el.classList.toggle('is-on', el === btn);
                });
                var isCard = btn.getAttribute('data-channel') === 'card';
                if (card) card.hidden = !isCard;
                if (submit) submit.hidden = isCard;
                if (isCard) say('输入卡密即可立即开通。');
                else say('点击下方按钮生成订单。');
            });
        }

        if (submit) {
            submit.addEventListener('click', submitOrder);
        }
        if (redeem) {
            redeem.addEventListener('click', redeemCard);
        }
        if (codeInput) {
            codeInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    redeemCard();
                }
            });
        }

        /* 默认通道就是卡密时直接展开输入框 */
        if (card && currentChannel() === 'card') {
            card.hidden = false;
            if (submit) submit.hidden = true;
        }

        /* 支付回跳后订单仍是 pending：恢复轮询（pay_status 会向通道补单查询） */
        var resume = root.getAttribute('data-resume-order') || '';
        if (resume) {
            if (submit) submit.hidden = true;
            if (orderBox) {
                orderBox.hidden = false;
                orderBox.textContent = '订单号：' + resume;
            }
            say('订单尚未完成支付，正在等待支付结果…');
            poll(resume);
        }
    };

    /* ---------- 前端投稿表单：校验 + 提交 + 封面预览 ---------- */
    Ecod.initContribute = function () {
        var form = document.querySelector('[data-ecod-contribute]');
        if (!form || form.dataset.ecodCtbBound) return;
        form.dataset.ecodCtbBound = '1';

        var api = form.getAttribute('data-ecod-api') || '';
        var token = form.getAttribute('data-ecod-token') || '';
        var msg = form.querySelector('[data-ecod-ctb-msg]');
        var submit = form.querySelector('[data-ecod-ctb-submit]');
        var coverInput = form.querySelector('[name="cover"]');
        var coverBox = form.querySelector('[data-ecod-ctb-cover]');
        var coverImg = coverBox ? coverBox.querySelector('img') : null;

        function say(text, ok) {
            if (!msg) return;
            msg.textContent = text;
            msg.classList.toggle('is-ok', !!ok);
            msg.classList.toggle('is-bad', !ok);
            msg.hidden = false;
        }

        /* 封面预览：地址变化即回显，加载失败自动收起 */
        if (coverInput && coverBox && coverImg) {
            var sync = function () {
                var url = coverInput.value.trim();
                if (!url || !/^https?:\/\//i.test(url)) {
                    coverBox.hidden = true;
                    coverImg.removeAttribute('src');
                    return;
                }
                coverBox.hidden = false;
                coverImg.src = url;
            };
            coverInput.addEventListener('change', sync);
            coverInput.addEventListener('blur', sync);
            coverImg.addEventListener('error', function () { coverBox.hidden = true; });
            if (coverInput.value.trim()) sync();
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!api) return;

            var title = form.querySelector('[name="title"]');
            var cat = form.querySelector('[name="category"]');
            var text = form.querySelector('[name="text"]');
            var cap = form.querySelector('[name="captcha"]');

            /* 服务端强制校验验证码，且一次一换：失败后必须换图重填 */
            function refreshCap() {
                var img = form.querySelector('[data-ecod-captcha]');
                if (img && api) img.src = api + '?action=captcha&t=' + Date.now();
                if (cap) cap.value = '';
            }

            if (!title || title.value.trim().length < 3) { say('标题至少 3 个字', false); title && title.focus(); return; }
            if (!cat || !cat.value) { say('请选择一个分类', false); cat && cat.focus(); return; }
            if (!text || text.value.trim().length < 10) { say('正文至少 10 个字', false); text && text.focus(); return; }
            if (cap && cap.value.trim() === '') { say('请输入图中的验证码', false); cap.focus(); return; }

            var body = new FormData(form);
            body.append('token', token);
            if (submit) submit.disabled = true;
            say('正在提交…', true);

            fetch(api + '?action=contribute_save', {
                method: 'POST',
                credentials: 'same-origin',
                body: body
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (data && data.ok) {
                    say((data.msg || '投稿成功') + '，正在跳转…', true);
                    setTimeout(function () { window.location.reload(); }, 900);
                } else {
                    say((data && data.error) || '提交失败，请稍后再试', false);
                    refreshCap();
                    if (submit) submit.disabled = false;
                }
            }).catch(function () {
                say('网络异常，请重试', false);
                refreshCap();
                if (submit) submit.disabled = false;
            });
        });
    };

    /* ---------- 个人中心「我的文章」：撤回待审核 / 草稿稿 ---------- */
    Ecod.initUcPosts = function () {
        var root = document.querySelector('[data-ecod-uc-posts]');
        if (!root || root.dataset.ecodPostsBound) return;
        root.dataset.ecodPostsBound = '1';

        var api = root.getAttribute('data-ecod-api') || '';
        var token = root.getAttribute('data-ecod-token') || '';

        root.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('[data-ecod-ctb-del]') : null;
            if (!btn) return;
            e.preventDefault();
            if (!window.confirm('确定撤回这篇投稿？撤回后不可恢复。')) return;

            var cid = btn.getAttribute('data-ecod-ctb-del');
            var item = btn.closest('.ecod_uc__item');
            var body = 'cid=' + encodeURIComponent(cid) + '&token=' + encodeURIComponent(token);
            btn.disabled = true;

            fetch(api + '?action=contribute_delete', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (data && data.ok) {
                    if (item) item.remove();
                    Ecod.toast('已撤回');
                } else {
                    Ecod.toast((data && data.error) || '撤回失败，请稍后再试', 'bad');
                    btn.disabled = false;
                }
            }).catch(function () {
                Ecod.toast('网络异常，请重试', 'bad');
                btn.disabled = false;
            });
        });
    };

    /* ---------- 评论点赞：批量回填「我是否赞过」+ 点击切换 ---------- */
    Ecod.initClike = function () {
        var btns = document.querySelectorAll('[data-ecod-clike]');
        if (!btns.length || !CFG.api) return;

        /* 每页一次回填：赞数在服务端已渲染，这里只补点亮状态 */
        var coids = [];
        Array.prototype.forEach.call(btns, function (btn) {
            var coid = parseInt(btn.getAttribute('data-ecod-clike'), 10) || 0;
            if (coid > 0 && coids.indexOf(coid) < 0) coids.push(coid);
        });
        if (!coids.length) return;

        fetch(CFG.api + '?action=cstate&coids=' + encodeURIComponent(coids.slice(0, 200).join(',')), {
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data || !data.ok) return;
            var liked = data.liked || [];
            Array.prototype.forEach.call(btns, function (btn) {
                var coid = parseInt(btn.getAttribute('data-ecod-clike'), 10) || 0;
                if (liked.indexOf(coid) >= 0) {
                    btn.classList.add('is-liked');
                    btn.setAttribute('aria-pressed', 'true');
                }
            });
        }).catch(function () {});

        once(document, 'clike', 'click', function (e) {
            var btn = e.target.closest ? e.target.closest('[data-ecod-clike]') : null;
            if (!btn) return;
            e.preventDefault();
            if (btn.dataset.busy === '1') return;
            btn.dataset.busy = '1';

            var coid = btn.getAttribute('data-ecod-clike');
            var numEl = btn.querySelector('[data-ecod-clike-num]');

            fetch(CFG.api + '?action=clike&coid=' + encodeURIComponent(coid), {
                method: 'POST',
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); }).then(function (data) {
                btn.dataset.busy = '';
                if (!data || !data.ok) return;
                var mine = !!data.mine;
                btn.classList.toggle('is-liked', mine);
                btn.setAttribute('aria-pressed', mine ? 'true' : 'false');
                if (numEl) {
                    var n = parseInt(data.count, 10) || 0;
                    numEl.textContent = n > 0 ? n : '赞';
                }
            }).catch(function () {
                btn.dataset.busy = '';
            });
        });
    };

    /* ---------- 显示更多（§33）：长文按高度折叠，点按钮展开 ----------
       服务端只输出按钮骨架；这里量正文高度决定是否收起（max-height 由 CSS 变量给），
       只在超过「折叠高度 + 200px」时启用，短文不受影响 */
    Ecod.initShowMore = function () {
        var btn = document.querySelector('[data-ecod-morebtn]');
        var content = document.querySelector('[data-ecod-content]');
        if (!btn || !content || btn.dataset.moreReady === '1') return;
        btn.dataset.moreReady = '1';

        var scope = btn.dataset.scope || 'all';
        if (scope === 'mobile' && !window.matchMedia('(max-width: 860px)').matches) return;

        var maxH = parseInt(btn.dataset.h, 10) || 1200;
        var fullH = content.scrollHeight;
        if (fullH < maxH + 200) return;

        content.style.setProperty('--ecod-more-h', maxH + 'px');
        content.classList.add('is-collapsed');
        var label = btn.querySelector('span');
        if (label) label.textContent = btn.dataset.text || '展开全文';
        btn.hidden = false;

        once(btn, 'showMore', 'click', function () {
            content.classList.remove('is-collapsed');
            btn.hidden = true;
        });
    };

    /* ---------- 投票（[poll] 短代码）：选择 / 提交 / 结果条 ----------
       票数与「我投了谁」由 poll_state / poll_vote 提供；投过或 closed 的
       投票直接渲染结果态（选项禁用 + 百分比条） */
    Ecod.initPoll = function () {
        var polls = document.querySelectorAll('[data-ecod-poll]');
        if (!polls.length || !CFG.api) return;

        Array.prototype.forEach.call(polls, function (poll) {
            if (poll.dataset.pollReady === '1') return;
            poll.dataset.pollReady = '1';

            var id = poll.dataset.poll || '';
            var multi = poll.dataset.multi === '1';
            var endTs = parseInt(poll.dataset.end, 10) || 0;
            var closed = poll.dataset.closed === '1' || (endTs > 0 && endTs * 1000 <= Date.now());
            var meta = poll.querySelector('[data-ecod-poll-meta]');
            var voteBtn = poll.querySelector('[data-ecod-poll-vote]');
            var opts = poll.querySelectorAll('.ecod_poll__opt');
            var avs = poll.querySelector('[data-ecod-poll-avs]');

            function picked() {
                var out = [];
                Array.prototype.forEach.call(opts, function (opt) {
                    if (opt.getAttribute('aria-pressed') === 'true') out.push(opt.dataset.opt);
                });
                return out;
            }

            function esc(s) {
                return String(s).replace(/[&<>"]/g, function (c) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
                });
            }

            /* 投票者头像堆叠：最近 5 位（登录用户头像 + 游客默认占位圆） */
            function renderVoters(voters) {
                if (!avs || !Array.isArray(voters) || !voters.length) return;
                var guestAvatar = '<i class="is-guest">'
                    + '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">'
                    + '<circle cx="12" cy="8" r="3.4"></circle><path d="M5 20c0-3.6 3.1-6.2 7-6.2s7 2.6 7 6.2"></path>'
                    + '</svg></i>';
                avs.innerHTML = voters.map(function (v) {
                    var name = String(v.name || '');
                    if (v.avatar) {
                        return '<img src="' + esc(v.avatar) + '" alt="" width="24" height="24" loading="lazy" decoding="async" data-initial="' + esc(name.slice(0, 1)) + '">';
                    }
                    /* 游客 / 无昵称：默认占位头像 */
                    if (v.guest || name === '') return guestAvatar;
                    return '<i>' + esc(name.slice(0, 1)) + '</i>';
                }).join('');
                avs.hidden = false;
            }

            function render(data) {
                var total = parseInt(data.total, 10) || 0;
                var counts = data.counts || {};
                var mine = (data.mine || '').split(',');
                renderVoters(data.voters);
                Array.prototype.forEach.call(opts, function (opt) {
                    var i = opt.dataset.opt;
                    var n = parseInt(counts[i], 10) || 0;
                    var pct = total > 0 ? Math.round((n / total) * 100) : 0;
                    opt.classList.add('is-voted');
                    opt.classList.toggle('is-mine', mine.indexOf(String(i)) > -1);
                    opt.style.setProperty('--ecod-poll-pct', pct + '%');
                    opt.disabled = true;
                    var num = opt.querySelector('[data-ecod-poll-num]');
                    if (num) {
                        num.textContent = total > 0 ? n + ' 票 · ' + pct + '%' : '';
                        num.hidden = total <= 0;
                    }
                });
                if (voteBtn) voteBtn.hidden = true;
                if (meta) {
                    meta.textContent = total > 0
                        ? total + ' 人已投票' + (closed ? ' · 已结束' : '')
                        : (closed ? '投票已结束' : '还没有人投票');
                }
            }

            /* 单选 = radio 行为，多选 = 独立切换 */
            once(poll, 'pollPick', 'click', function (e) {
                var opt = e.target.closest ? e.target.closest('.ecod_poll__opt') : null;
                if (!opt || opt.disabled) return;
                if (multi) {
                    var on = opt.getAttribute('aria-pressed') === 'true';
                    opt.setAttribute('aria-pressed', on ? 'false' : 'true');
                } else {
                    Array.prototype.forEach.call(opts, function (o) {
                        o.setAttribute('aria-pressed', o === opt ? 'true' : 'false');
                    });
                }
                if (voteBtn) voteBtn.disabled = picked().length === 0;
            });

            if (voteBtn && !closed) {
                once(voteBtn, 'pollVote', 'click', function () {
                    var sel = picked();
                    if (!sel.length || voteBtn.dataset.busy === '1') return;
                    voteBtn.dataset.busy = '1';
                    var fd = new FormData();
                    fd.append('poll', id);
                    fd.append('opts', sel.join(','));
                    fetch(CFG.api + '?action=poll_vote', {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: fd,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    }).then(function (r) { return r.json(); }).then(function (data) {
                        voteBtn.dataset.busy = '';
                        if (!data || !data.ok) {
                            if (meta && data && data.error) meta.textContent = data.error;
                            return;
                        }
                        render(data);
                    }).catch(function () { voteBtn.dataset.busy = ''; });
                });
            }

            /* 初始：投过 / 只看结果 → 直接出结果；否则回填人数并等待选择 */
            fetch(CFG.api + '?action=poll_state&poll=' + encodeURIComponent(id), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); }).then(function (data) {
                    if (!data || !data.ok) {
                        if (meta) meta.textContent = '';
                        return;
                    }
                    if ((data.mine || '') !== '' || closed) {
                        render(data);
                        return;
                    }
                    renderVoters(data.voters);
                    if (meta) {
                        meta.textContent = (parseInt(data.total, 10) || 0) > 0
                            ? data.total + ' 人已投票'
                            : '还没有人投票，来投第一票';
                    }
                }).catch(function () {});
        });
    };

    /* ---------- AI 助手对话页（page-ai.php） ----------
       历史只保留在页面内存（history 数组），按 EAIChatCtx 截断后随请求带回；
       服务端成功才扣积分，余额就地更新 */
    Ecod.initAiChat = function () {
        var root = document.querySelector('[data-ecod-aichat]');
        if (!root || root.dataset.aiChatReady === '1') return;
        root.dataset.aiChatReady = '1';

        var api = root.dataset.api || CFG.api || '';
        var list = root.querySelector('[data-ecod-aichat-list]');
        var input = root.querySelector('[data-ecod-aichat-input]');
        var send = root.querySelector('[data-ecod-aichat-send]');
        var balEl = root.querySelector('[data-ecod-aichat-balance]');
        var msgEl = root.querySelector('[data-ecod-aichat-msg]');
        var cost = parseInt(root.dataset.cost, 10) || 0;
        var ctx = parseInt(root.dataset.ctx, 10) || 0;
        var creditName = root.dataset.creditName || 'Credit';
        if (!api || !list || !input || !send) return;

        var history = [];
        var busy = false;

        function say(text) {
            if (!msgEl) return;
            msgEl.textContent = text || '';
            msgEl.hidden = !text;
        }

        function scrollBottom() { list.scrollTop = list.scrollHeight; }

        function addMsg(role, text) {
            var div = document.createElement('div');
            div.className = 'ecod_aichat__msg is-' + role;
            div.innerHTML = '<span class="ecod_aichat__ava" aria-hidden="true">'
                + (role === 'ai' ? Ecod.icon('zap', 14) : '我') + '</span>'
                + '<div class="ecod_aichat__bubble"></div>';
            div.querySelector('.ecod_aichat__bubble').textContent = text;
            list.appendChild(div);
            scrollBottom();
            return div;
        }

        function setBusy(on) {
            busy = on;
            send.disabled = on;
            say(on ? (cost > 0 ? '思考中…（本次消耗 ' + cost + ' ' + creditName + '）' : '思考中…') : '');
        }

        function ask() {
            var text = input.value.trim();
            if (!text || busy) return;
            input.value = '';
            addMsg('me', text);
            /* 当前这条随 message 单独发，history 只带之前的轮次，避免重复 */
            var fd = new FormData();
            fd.append('token', root.dataset.token || '');
            fd.append('message', text);
            fd.append('history', JSON.stringify(history.slice(0, Math.max(0, ctx * 2))));
            history.push({ role: 'user', content: text });
            var think = addMsg('ai', '…');
            think.classList.add('is-typing');
            setBusy(true);

            fetch(api + '?action=ai_chat', {
                method: 'POST',
                credentials: 'same-origin',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (r) { return r.json(); }).then(function (data) {
                setBusy(false);
                if (!data || !data.ok) {
                    think.remove();
                    history.pop();
                    say((data && data.error) || '生成失败，请稍后重试');
                    return;
                }
                think.classList.remove('is-typing');
                think.querySelector('.ecod_aichat__bubble').textContent = data.reply || '（空回复）';
                history.push({ role: 'assistant', content: data.reply || '' });
                if (balEl && typeof data.balance === 'number') balEl.textContent = data.balance;
                scrollBottom();
            }).catch(function () {
                setBusy(false);
                think.remove();
                history.pop();
                say('网络异常，请稍后重试');
            });
        }

        once(send, 'aiChatSend', 'click', ask);
        once(input, 'aiChatKey', 'keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                ask();
            }
        });
        scrollBottom();
    };

    /* ---------- 站点抽奖（page-lottery.php + 侧栏挂件，同页可多实例） ----------
       guaqi 式组件：数据栏（消耗/概率/兑换/库存）+ 保底进度 + 就地抽奖 +
       直接兑换 + 我的记录。九宫格跑马灯落格，列表布局高亮滚动。 */
    Ecod.initLottery = function () {
        var roots = document.querySelectorAll('[data-ecod-lottery]');
        if (!roots.length) return;

        /* 结果弹窗：document 级单例，同页多个抽奖组件共用 */
        var lotModal = null;
        function lotModalOpen(prize) {
            if (!lotModal) {
                lotModal = document.createElement('div');
                lotModal.className = 'ecod_lotmodal';
                lotModal.hidden = true;
                lotModal.innerHTML =
                    '<div class="ecod_lotmodal__mask" data-lm-close></div>'
                    + '<div class="ecod_lotmodal__panel" role="dialog" aria-modal="true" aria-label="抽奖结果">'
                    + '<div class="ecod_lotmodal__head"><span data-lm-title>抽奖结果</span>'
                    + '<button type="button" class="ecod_lotmodal__x" data-lm-close aria-label="关闭">'
                    + '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>'
                    + '<div class="ecod_lotmodal__card"><b data-lm-name></b><span data-lm-tip></span></div>'
                    + '<div class="ecod_lotmodal__ops">'
                    + '<button type="button" class="ecod-btn ecod-btn--ghost" data-lm-cancel>取消</button>'
                    + '<button type="button" class="ecod-btn" data-lm-ok>领取</button>'
                    + '</div></div>';
                document.body.appendChild(lotModal);
            }
            var panel = lotModal.querySelector('.ecod_lotmodal__panel');
            var win = prize && prize.type !== 'none';
            lotModal.querySelector('[data-lm-title]').textContent = win ? '领取抽奖奖励' : '抽奖结果';
            lotModal.querySelector('[data-lm-name]').textContent = win ? '恭喜获得奖励：' + (prize.name || '') : '谢谢参与';
            lotModal.querySelector('[data-lm-tip]').textContent = prize && prize.tip ? prize.tip : (win ? '' : '再抽一次说不定就中了');
            var ok = lotModal.querySelector('[data-lm-ok]');
            var cancel = lotModal.querySelector('[data-lm-cancel]');
            ok.hidden = !win;
            cancel.textContent = win ? '取消' : '知道了';
            var close = function () { Ecod.dialogClose(panel); };
            if (!panel.dataset.lmBound) {
                panel.dataset.lmBound = '1';
                lotModal.addEventListener('click', function (e) {
                    if (e.target.closest('[data-lm-close]') || e.target.closest('[data-lm-cancel]') || e.target.closest('[data-lm-ok]')) close();
                });
            }
            lotModal.hidden = false;
            Ecod.dialogOpen(panel, win ? ok : cancel, function () { lotModal.hidden = true; });
        }

        Array.prototype.forEach.call(roots, function (root) {
            if (root.dataset.lotteryReady === '1') return;
            root.dataset.lotteryReady = '1';

            var api = root.dataset.api || CFG.api || '';
            if (!api) return;

            var go = root.querySelector('[data-ecod-lottery-go]');
            var exBtn = root.querySelector('[data-ecod-lottery-exchange]');
            var result = root.querySelector('[data-ecod-lottery-result]');
            var balEl = root.querySelector('[data-ecod-lottery-balance]');
            var balBadge = root.querySelector('[data-ecod-lottery-balance-badge]');
            var leftEl = root.querySelector('[data-ecod-lottery-left]');
            var stateEl = root.querySelector('[data-ecod-lottery-state]');
            var pity = root.querySelector('[data-ecod-lottery-pity]');
            var recToggle = root.querySelector('[data-ecod-lottery-records]');
            var recBox = root.querySelector('[data-ecod-lottery-recbox]');
            var cells = root.querySelectorAll('[data-cell]');
            var isGrid = root.dataset.grid === '1';
            var busy = false;

            function say(text, win) {
                if (!result) return;
                result.textContent = text || '';
                result.hidden = !text;
                result.classList.toggle('is-win', !!win);
            }

            function setBalance(v) {
                if (balEl) balEl.textContent = v;
                if (balBadge) balBadge.textContent = v;
            }

            function setPity(now, n) {
                if (!pity) return;
                pity.dataset.now = now;
                var fill = pity.querySelector('i');
                var txt = pity.querySelector('[data-ecod-lottery-pity-text]');
                if (fill) fill.style.width = Math.min(100, Math.round((now / Math.max(1, n)) * 100)) + '%';
                if (txt) txt.textContent = now >= n - 1 ? '下一抽必中！' : '已抽 ' + now + ' 次，' + n + ' 次保底必中';
            }

            function clearHot() {
                Array.prototype.forEach.call(cells, function (c) { c.classList.remove('is-hot'); });
            }

            function spin() {
                if (!cells.length) return null;
                var cur = 0;
                return setInterval(function () {
                    clearHot();
                    cells[cur % cells.length].classList.add('is-hot');
                    cur++;
                }, 90);
            }

            /* 抽奖 / 兑换共用：转足够时长再揭示结果，随后后台刷一次状态（保底 / 库存） */
            function request(action, onDone) {
                var timer = spin();
                var startedAt = Date.now();
                var fd = new FormData();
                fd.append('token', root.dataset.token || '');
                return fetch(api + '?action=' + action, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function (r) { return r.json(); }).then(function (data) {
                    var least = action === 'lottery_draw' ? 1200 : 700;
                    setTimeout(function () {
                        clearHot();
                        if (timer) clearInterval(timer);
                        onDone(data);
                        fetch(api + '?action=lottery_state', { credentials: 'same-origin' })
                            .then(function (r) { return r.json(); }).then(function (state) {
                                if (!state || !state.ok) return;
                                setBalance(state.balance);
                                if (state.pity && state.pity.on) setPity(state.pity.now, state.pity.n);
                            }).catch(function () {});
                    }, Math.max(0, least - (Date.now() - startedAt)));
                }).catch(function () {
                    clearHot();
                    if (timer) clearInterval(timer);
                    busy = false;
                    if (go) { go.classList.remove('is-busy'); go.disabled = false; }
                    if (exBtn) exBtn.classList.remove('is-busy');
                    say('网络异常，请稍后重试');
                });
            }

            function settle(data, verb, showModal) {
                busy = false;
                if (go) { go.classList.remove('is-busy'); go.disabled = false; }
                if (exBtn) exBtn.classList.remove('is-busy');
                if (!data || !data.ok) {
                    say((data && data.error) || '操作失败，请稍后重试');
                    return;
                }
                var prize = data.prize || {};
                if (isGrid && cells.length && prize.key) {
                    var landed = String(parseInt(prize.key.replace(/^\D+/, ''), 10) || 0);
                    Array.prototype.forEach.call(cells, function (c) {
                        c.classList.toggle('is-win', c.dataset.cell === landed && prize.type !== 'none');
                    });
                }
                say((prize.type === 'none' ? '🎲 ' : '🎉 ' + verb + '：') + (prize.name || '谢谢参与')
                    + (prize.tip ? '（' + prize.tip + '）' : ''), prize.type !== 'none');
                /* 抽奖结果弹窗（兑换走行内提示，不打断） */
                if (showModal) lotModalOpen(prize);
                setBalance(data.balance);
                if (leftEl && typeof data.left === 'number') {
                    leftEl.textContent = data.left < 0 ? '剩余次数不限' : '今日剩余 ' + data.left + ' 次';
                }
                if (stateEl) stateEl.textContent = data.left === 0 ? '今日次数已用完' : '进行中';
            }

            if (go) {
                once(go, 'lotteryGo', 'click', function () {
                    if (busy || go.disabled) return;
                    busy = true;
                    go.classList.add('is-busy');
                    go.disabled = true;
                    say('');
                    request('lottery_draw', function (data) { settle(data, '抽中', true); });
                });
            }

            if (exBtn) {
                once(exBtn, 'lotteryEx', 'click', function () {
                    if (busy || exBtn.classList.contains('is-busy')) return;
                    busy = true;
                    exBtn.classList.add('is-busy');
                    say('');
                    request('lottery_exchange', function (data) { settle(data, '直接兑换成功'); });
                });
            }

            /* 我的记录：首次展开时拉一次（之后复用，不重复请求） */
            function loadRecords() {
                if (!recBox || recBox.dataset.recLoaded === '1') return;
                recBox.dataset.recLoaded = '1';
                recBox.innerHTML = '<p class="ecod_lottery__recempty">加载中…</p>';
                recBox.hidden = false;
                fetch(api + '?action=lottery_state', { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); }).then(function (state) {
                        if (!state || !state.ok) {
                            recBox.innerHTML = '<p class="ecod_lottery__recempty">暂无记录</p>';
                            return;
                        }
                        if (state.pity && state.pity.on) setPity(state.pity.now, state.pity.n);
                        setBalance(state.balance);
                        var rows = state.mine || [];
                        if (!rows.length) {
                            recBox.innerHTML = '<p class="ecod_lottery__recempty">还没有记录，抽一次试试手气。</p>';
                            return;
                        }
                        var html = '';
                        rows.forEach(function (row) {
                            var d = new Date(row.created * 1000);
                            html += '<div class="ecod_lottery__rec' + (row.type === 'none' ? ' is-none' : '') + '">'
                                + '<span>' + esc(row.name || '谢谢参与') + '</span>'
                                + (row.type === 'exchange' ? '<i>兑换</i>' : (row.type !== 'none' ? '<i>中奖</i>' : ''))
                                + '<time>' + d.getMonth() + 1 + '-' + d.getDate() + ' ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0') + '</time>'
                                + '</div>';
                        });
                        recBox.innerHTML = html;
                    }).catch(function () {
                        recBox.innerHTML = '<p class="ecod_lottery__recempty">加载失败</p>';
                    });
            }

            /* 页面版：原生 summary 展开（details 自带开合），首次点开时拉记录 */
            if (recBox) {
                Array.prototype.forEach.call(root.querySelectorAll('[data-ecod-lottery-records]'), function (recEl) {
                    once(recEl, 'lotteryRec', 'click', loadRecords);
                });
            }

            /* 挂件版：标题行「我的记录」只是个人中心 #lottery 深链 —— 记录统一在
               个人中心「抽奖记录」tab 展示，侧栏不再就地展开面板 */
            var isWidget = root.classList.contains('ecod_lottery--widget');
            var widgetTitle = root.querySelector('.ecod_widget__title');
            var recUcUrl = root.dataset.uc || '';
            if (isWidget && widgetTitle && recUcUrl && !root.dataset.recHead) {
                root.dataset.recHead = '1';
                var recBtn = document.createElement('a');
                recBtn.className = 'ecod_widget__more ecod_lottery__recbtn';
                recBtn.textContent = '我的记录';
                recBtn.href = recUcUrl;
                var moreLink = widgetTitle.querySelector('.ecod_widget__more');
                if (moreLink) {
                    widgetTitle.insertBefore(recBtn, moreLink);
                    moreLink.style.marginLeft = '8px';
                } else {
                    widgetTitle.appendChild(recBtn);
                }
                recBtn.style.marginLeft = 'auto';
            }
        });
    };

    /* ---------- 自助广告表单（page-ads.php）：金额联动 + 提交进收银台 ---------- */
    Ecod.initAdSelf = function () {
        var root = document.querySelector('[data-ecod-adself]');
        if (!root || root.dataset.adSelfReady === '1') return;
        root.dataset.adSelfReady = '1';

        var api = root.dataset.api || CFG.api || '';
        var form = root.querySelector('[data-ecod-adself-form]');
        if (!form || !api) return;

        var slot = form.querySelector('[data-ecod-adself-slot]');
        var units = form.querySelector('[data-ecod-adself-units]');
        var amount = form.querySelector('[data-ecod-adself-amount]');
        var msg = form.querySelector('[data-ecod-adself-msg]');
        var submit = form.querySelector('[data-ecod-adself-submit]');
        var cashier = root.dataset.cashier || '';

        function say(text) {
            if (!msg) return;
            msg.textContent = text || '';
            msg.hidden = !text;
        }

        /* 选项文本形如「侧边栏卡片（¥3.00/周）」，从中提取单价 */
        function unitPrice() {
            if (!slot || !slot.options || !slot.options[slot.selectedIndex]) return 0;
            var m = slot.options[slot.selectedIndex].textContent.match(/([0-9.]+)\s*\/\s*([天周月])/);
            return m ? parseFloat(m[1]) : 0;
        }

        function update() {
            var u = parseInt(units && units.value, 10) || 0;
            if (amount) amount.textContent = '¥' + (unitPrice() * u).toFixed(2);
        }

        once(form, 'adSelfInput', 'input', update);
        once(form, 'adSelfChange', 'change', update);
        update();

        once(form, 'adSelfSubmit', 'submit', function (e) {
            e.preventDefault();
            if (!submit || submit.dataset.busy === '1') return;

            /* 本地逐字段校验：缺哪个、格式错哪个分开通提示，不等到服务端才报「都要填写」 */
            function fieldVal(name) {
                var el = form.querySelector('[name="' + name + '"]');
                return el ? el.value.trim() : '';
            }
            var vTitle = fieldVal('title');
            var vImage = fieldVal('image');
            var vLink = fieldVal('link');
            if (vTitle === '') { say('请填写广告标题'); return; }
            if (vImage === '') { say('请填写图片地址'); return; }
            if (!/^(https?:\/\/|\/)/i.test(vImage)) { say('图片地址需以 http(s):// 或 / 开头'); return; }
            if (vLink === '') { say('请填写跳转链接'); return; }
            if (!/^https?:\/\//i.test(vLink)) { say('跳转链接必须是 http(s) 地址'); return; }

            submit.dataset.busy = '1';
            say('');

            var fd = new FormData(form);
            fd.append('token', root.dataset.token || '');
            fetch(api + '?action=ad_apply', {
                method: 'POST',
                credentials: 'same-origin',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (r) { return r.json(); }).then(function (data) {
                submit.dataset.busy = '';
                if (!data || !data.ok) {
                    say((data && data.error) || '提交失败，请稍后重试');
                    return;
                }
                if (cashier) {
                    window.location.href = cashier
                        + (cashier.indexOf('?') < 0 ? '?' : '&')
                        + 'order_no=' + encodeURIComponent(data.order_no);
                    return;
                }
                say('已提交，订单号 ' + data.order_no + '，请前往收银台支付。');
            }).catch(function () {
                submit.dataset.busy = '';
                say('网络异常，请重试');
            });
        });
    };

    /* ---------- 积分兑换商城（page-shop.php + 挂件，同页可多实例） ----------
       商品价目卡就地兑换：shop_exchange 扣积分 → vip/post 自动发货；余额与记录就地刷新 */
    Ecod.initShop = function () {
        var roots = document.querySelectorAll('[data-ecod-shop]');
        if (!roots.length) return;

        Array.prototype.forEach.call(roots, function (root) {
            if (root.dataset.shopReady === '1') return;
            root.dataset.shopReady = '1';

            var api = root.dataset.api || CFG.api || '';
            if (!api) return;

            var balEl = root.querySelector('[data-ecod-shop-balance]');
            var msgEl = root.querySelector('[data-ecod-shop-msg]');
            var recToggle = root.querySelector('[data-ecod-shop-records]');
            var recBox = root.querySelector('[data-ecod-shop-recbox]');

            function say(text, ok) {
                if (!msgEl) return;
                msgEl.textContent = text || '';
                msgEl.hidden = !text;
                msgEl.classList.toggle('is-ok', !!ok);
            }

            function setBalance(v) {
                if (balEl) balEl.textContent = v;
            }

            function day(ts) {
                var d = new Date(ts * 1000);
                return (d.getMonth() + 1) + '-' + d.getDate() + ' ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
            }

            /* 兑换按钮 */
            once(root, 'shopBuy', 'click', function (e) {
                var btn = e.target.closest ? e.target.closest('[data-ecod-shop-buy]') : null;
                if (!btn || btn.dataset.busy === '1') return;
                btn.dataset.busy = '1';
                say('');

                var fd = new FormData();
                fd.append('token', root.dataset.token || '');
                fd.append('item', btn.dataset.ecodShopBuy || '');
                fetch(api + '?action=shop_exchange', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function (r) { return r.json(); }).then(function (data) {
                    btn.dataset.busy = '';
                    if (!data || !data.ok) {
                        say((data && data.error) || '兑换失败，请稍后重试');
                        return;
                    }
                    var item = data.item || {};
                    say('🎉 ' + (item.name || '商品') + ' 兑换成功' + (item.note ? '（' + item.note + '）' : ''), true);
                    setBalance(data.balance);
                    /* 已达限购的就地变为不可点 */
                    var card = btn.closest('[data-item]');
                    btn.remove();
                    if (card) {
                        var off = document.createElement('em');
                        off.className = 'ecod_shop__off';
                        off.textContent = '已兑换';
                        card.appendChild(off);
                    }
                    if (recBox && recBox.dataset.recLoaded === '1') loadRecords(true);
                }).catch(function () {
                    btn.dataset.busy = '';
                    say('网络异常，请重试');
                });
            });

            /* 我的兑换：展开时拉取 */
            function loadRecords(force) {
                if (!recBox) return;
                if (recBox.dataset.recLoaded === '1' && !force) return;
                recBox.dataset.recLoaded = '1';
                recBox.innerHTML = '<p class="ecod_lottery__recempty">加载中…</p>';
                recBox.hidden = false;
                fetch(api + '?action=shop_state', { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); }).then(function (state) {
                        if (!state || !state.ok) {
                            recBox.innerHTML = '<p class="ecod_lottery__recempty">暂无记录</p>';
                            return;
                        }
                        setBalance(state.balance);
                        var rows = state.mine || [];
                        if (!rows.length) {
                            recBox.innerHTML = '<p class="ecod_lottery__recempty">还没有兑换记录。</p>';
                            return;
                        }
                        var html = '';
                        rows.forEach(function (row) {
                            html += '<div class="ecod_lottery__rec">'
                                + '<span>' + esc(row.name || '') + '</span>'
                                + '<i>' + esc(row.note || '') + '</i>'
                                + '<b>-' + row.cost + '</b>'
                                + '<time>' + day(row.created) + '</time>'
                                + '</div>';
                        });
                        recBox.innerHTML = html;
                    }).catch(function () {
                        recBox.innerHTML = '<p class="ecod_lottery__recempty">加载失败</p>';
                    });
            }

            if (recToggle && recBox) {
                once(recToggle, 'shopRec', 'click', function () {
                    loadRecords(false);
                });
            }
        });
    };

    /* 标记已加载：ensurePostBundle 据此避免重复注入 */
    Ecod._postLoaded = true;
})();
