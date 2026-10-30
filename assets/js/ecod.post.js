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
 * Copyright (c) 2026 Ecod · MIT License · https://ecod.cc
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
            window.scrollTo({ top: Math.max(y, 0), behavior: 'smooth' });
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
                    list.scrollTo({ top: Math.max(top, 0), behavior: 'smooth' });
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
                + '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/></svg>'
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

            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                setOpen(!floatBox.classList.contains('is-open'));
            });
            /* 点击目录项后收起弹层 */
            once(floatBox, 'ftLink', 'click', function (e) {
                if (e.target.closest('.ecod_floattoc__list a')) setOpen(false);
            });
            /* 点击外部 / Esc 收起；PJAX 换页后容器被替换，监听自动解绑 */
            document.addEventListener('click', function onDoc(e) {
                if (!floatBox.isConnected) { document.removeEventListener('click', onDoc); return; }
                if (floatBox.classList.contains('is-open') && !floatBox.contains(e.target)) setOpen(false);
            });
            document.addEventListener('keydown', function onKey(e) {
                if (!floatBox.isConnected) { document.removeEventListener('keydown', onKey); return; }
                if (e.key === 'Escape' && floatBox.classList.contains('is-open')) setOpen(false);
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
                var m = (code.className || '').match(/language-([\w-]+)/);
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
                    copy.innerHTML = Ecod.icon('check', 12) + '<span>已复制</span>';
                    setTimeout(function () {
                        copy.innerHTML = Ecod.icon('copy', 12) + '<span>复制</span>';
                    }, 1600);
                });
            });
            bar.appendChild(copy);

            pre.parentNode.insertBefore(box, pre);
            box.appendChild(bar);
            box.appendChild(pre);

            /* 后台开着 Prism 时由 Prism 统一染色，内置高亮器不再插手，避免两套配色互相覆盖 */
            if (code && !(CFG.prism && window.Prism)) {
                Ecod.highlightCode(code, lang);
            }
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
                + '    <a class="ecod_lightbox__btn" data-lb-download download target="_blank" rel="noopener">' + Ecod.icon('download', 14) + '下载</a>'
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
            Ecod.dialogClose();
        }

        function open(src, alt, group, start) {
            if (!src) return;
            list = (group && group.length ? group : [{ src: src, alt: alt || '' }]).slice();
            index = Math.max(0, group && group.length ? start || 0 : 0);
            isOpen = true;
            render();
            lb.classList.add('is-open');
            Ecod.dialogOpen(lb, lb.querySelector('[data-lb-close]'));
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

        /* 键盘：ESC 关闭，← → 切换 */
        document.addEventListener('keydown', function (e) {
            if (!isOpen) return;
            if (e.key === 'Escape') {
                e.preventDefault();
                close();
            } else if (e.key === 'ArrowLeft') {
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

    /* ---------- 文章操作条 ---------- */
    Ecod.initPostAct = function () {
        var bar = document.querySelector('[data-ecod-postact]');
        if (!bar) return;
        syncFavButtons();

        /* 系统分享：仅在支持的浏览器（主要移动端）显示入口 */
        if (navigator.share) {
            document.querySelectorAll('[data-ecod-share-native]').forEach(function (btn) {
                btn.hidden = false;
            });
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
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            setTimeout(function () {
                if (window.innerWidth > 768) return;
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
            '      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>' +
            '    </button>' +
            '  </div>' +
            '  <div class="ecod_shcard__body">' +
            '    <div class="ecod_shcard__loading" data-ecod-shcard-loading>正在生成…</div>' +
            '    <img class="ecod_shcard__img" data-ecod-shcard-img alt="文章分享卡片" hidden>' +
            '  </div>' +
            '  <div class="ecod_shcard__ops">' +
            '    <button type="button" class="ecod_shcard__save" data-ecod-shcard-save>' +
            '      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5M12 15V3"/></svg>' +
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
        once(document, 'shcard-esc', 'keydown', function (e) {
            if (e.key === 'Escape' && !modal.hidden) close();
        });
        once(document, 'shcard-save', 'click', function (e) {
            /* 必须点在「保存图片」上且弹层打开，否则页面任意点击都会触发下载 */
            if (modal.hidden || !e.target.closest || !e.target.closest('[data-ecod-shcard-save]')) return;
            var img = modal.querySelector('[data-ecod-shcard-img]');
            if (!img || img.hidden || !img.getAttribute('src') || modal.dataset.busy === '1') return;
            modal.dataset.busy = '1';
            save(img);
        });

        function open() {
            modal.hidden = false;
            document.documentElement.classList.add('ecod-shcard-lock');
            Ecod.dialogOpen(modal.querySelector('.ecod_shcard__panel'), modal.querySelector('.ecod_shcard__x'));
        }
        function close() {
            modal.hidden = true;
            document.documentElement.classList.remove('ecod-shcard-lock');
            modal.dataset.busy = '';
            Ecod.dialogClose();
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
            if (e.key === 'Escape') emojiClose();
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

            nav.addEventListener('click', function (e) {
                var btn = e.target.closest ? e.target.closest('[data-ecod-tab]') : null;
                if (!btn) return;
                e.preventDefault();

                var index = btn.getAttribute('data-ecod-tab');
                Array.prototype.forEach.call(btns, function (one) {
                    var on = one.getAttribute('data-ecod-tab') === index;
                    one.classList.toggle('is-active', on);
                    one.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                Array.prototype.forEach.call(panels, function (one) {
                    one.classList.toggle('is-active', one.getAttribute('data-ecod-panel') === index);
                });
            });

            nav.addEventListener('keydown', function (e) {
                if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
                var list = Array.prototype.slice.call(btns);
                var current = list.indexOf(document.activeElement);
                if (current < 0) return;
                e.preventDefault();
                var next = (current + (e.key === 'ArrowRight' ? 1 : -1) + list.length) % list.length;
                list[next].focus();
                list[next].click();
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
        var loaded = Date.now();
        var sending = false;

        function showTip(msg) {
            if (!tip) return;
            tip.textContent = msg || '';
            tip.hidden = !msg;
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
                            target.scrollIntoView({ block: 'center', behavior: 'smooth' });
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
                box.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
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

    /* ---------- AI 摘要：文章页异步取数，失败静默隐藏 ---------- */
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
                if (body) {
                    body.removeAttribute('aria-busy');
                    body.textContent = data.summary;
                }
                box.hidden = false;
            })
            .catch(function () {
                box.hidden = true;
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
                document.documentElement.classList.toggle('ecod-modal-lock', on);
                if (on) {
                    var first = modal.querySelector('[name="name"]');
                    if (first) { try { first.focus({ preventScroll: true }); } catch (err) {} }
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

            /* Esc：绑在 document 上只做一次，换页后重新查询当前弹窗 */
            if (!document.documentElement.dataset.ecodFlinkEsc) {
                document.documentElement.dataset.ecodFlinkEsc = '1';
                document.addEventListener('keydown', function (e) {
                    if (e.key !== 'Escape') { return; }
                    var m = document.querySelector('[data-ecod-flink]');
                    if (m && !m.hidden) {
                        m.hidden = true;
                        document.documentElement.classList.remove('ecod-modal-lock');
                    }
                });
            }

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
                document.documentElement.classList.add('ecod-modal-lock');
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

        function stopPolling() {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
        }

        function done(message) {
            stopPolling();
            say(message || '支付成功，正在返回…', 'ok');
            window.setTimeout(function () {
                if (kind === 'post' && permalink) {
                    window.location.href = permalink;
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
            fd.append('kind', kind);
            fd.append('cid', cid);
            fd.append('level', level);
            fd.append('plan', plan);
            fd.append('channel', currentChannel());
            fd.append('token', token);

            submit.disabled = true;
            say('正在生成订单…');

            fetch(api + '?action=pay_create', {
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
    };

    /* 标记已加载：ensurePostBundle 据此避免重复注入 */
    Ecod._postLoaded = true;
})();
