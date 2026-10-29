/**
 * Ecod Theme · 全局脚本
 *
 * 模块：主题切换 / 抽屉 / 导航滚动 / 回到顶部 / 阅读进度 / 目录
 *      内容增强 / 点赞收藏 / 轮播 / 无刷新跳转
 *
 * Copyright (c) 2026 Ecod · MIT License · https://ecod.cc
 */
(function () {
    'use strict';

    var Ecod = window.Ecod = window.Ecod || {};
    var CFG = window.ECOD || {};
    var root = document.documentElement;

    /* ---------- 内联图标 ---------- */
    var ICON_PATHS = {
        moon: '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
        sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32l1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>',
        heart: '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>',
        star: '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>',
        check: '<path d="M20 6L9 17l-5-5"/>',
        copy: '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        link: '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        trash: '<path d="M3 6h18M8 6V4h8v2m-9 0l1 14h8l1-14"/>',
        play: '<circle cx="12" cy="12" r="9"/><path d="M10 8.5l6 3.5-6 3.5z"/>',
        pause: '<circle cx="12" cy="12" r="9"/><path d="M10 9v6M14 9v6"/>',
        volume: '<path d="M11 5L6 9H2v6h4l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7M18.5 5.5a9 9 0 0 1 0 13"/>',
        search: '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/>',
        file: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
        list: '<path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/>',
        external: '<path d="M7 17L17 7M8 7h9v9"/>',
        x: '<path d="M18 6L6 18M6 6l12 12"/>',
        clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
        inbox: '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        user: '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        alert: '<circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/>',
        left: '<path d="M15 5l-7 7 7 7"/>',
        right: '<path d="M9 5l7 7-7 7"/>',
        download: '<path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M4 21h16"/>'
    };

    Ecod.icon = function (name, size) {
        var p = ICON_PATHS[name];
        if (!p) return '';
        size = size || 16;
        return '<svg class="ecod_icon" width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + p + '</svg>';
    };

    /* ---------- 小工具 ---------- */
    function once(el, key, ev, fn) {
        if (!el) return;
        var flag = '_ecod_' + key;
        if (el[flag]) return;
        el[flag] = 1;
        el.addEventListener(ev, fn);
    }

    /* 滚动订阅：统一一个监听，页面切换时可重置，避免重复绑定 */
    var scrollFns = [];
    var ticking = false;

    function onScroll() {
        if (ticking) return;
        ticking = true;
        window.requestAnimationFrame(function () {
            ticking = false;
            for (var i = 0; i < scrollFns.length; i++) {
                try { scrollFns[i](); } catch (e) {}
            }
        });
    }
    window.addEventListener('scroll', onScroll, { passive: true });

    Ecod.addScroll = function (fn) {
        scrollFns.push(fn);
        fn();
    };
    Ecod.resetScroll = function () {
        scrollFns = [];
    };

    function copyText(text, done) {
        function fallback() {
            var ok = false;
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { ok = document.execCommand('copy'); } catch (e) {}
            document.body.removeChild(ta);
            if (ok) done();
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done, fallback);
            return;
        }
        fallback();
    }

    /* ---------- 无障碍：屏幕阅读器播报 ---------- */
    function live(msg) {
        var el = document.getElementById('ecod-live');
        if (!el) {
            el = document.createElement('div');
            el.id = 'ecod-live';
            el.className = 'ecod_sr';
            el.setAttribute('aria-live', 'polite');
            el.setAttribute('aria-atomic', 'true');
            document.body.appendChild(el);
        }
        el.textContent = msg;
    }
    Ecod.live = live;

    /* ---------- 无障碍：模态弹层焦点管理 ---------- */
    var dialogState = null;

    function dialogItems(el) {
        var sel = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]),'
            + ' textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
        return Array.prototype.filter.call(el.querySelectorAll(sel), function (n) {
            return !!(n.offsetWidth || n.offsetHeight || n.getClientRects().length);
        });
    }

    function dialogTrap(e) {
        if (!dialogState || e.key !== 'Tab') return;
        var items = dialogItems(dialogState.el);
        if (!items.length) return;
        var first = items[0];
        var last = items[items.length - 1];
        var active = document.activeElement;
        var inside = dialogState.el.contains(active);
        if (e.shiftKey && (active === first || !inside)) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && (active === last || !inside)) {
            e.preventDefault();
            first.focus();
        }
    }

    Ecod.dialogOpen = function (el, target) {
        Ecod.dialogClose();
        dialogState = { el: el, back: document.activeElement };
        document.addEventListener('keydown', dialogTrap, true);
        var node = target || dialogItems(el)[0] || el;
        if (node === el && !el.hasAttribute('tabindex')) el.setAttribute('tabindex', '-1');
        /* 弹层刚从 visibility:hidden / display:none 切出来时同步 focus 会失败，
           先同步试一次，下一轮事件循环再补一次（后台标签页 rAF 会被节流，故用定时器） */
        var doFocus = function () {
            try { node.focus({ preventScroll: true }); } catch (e) {}
        };
        doFocus();
        window.setTimeout(doFocus, 0);
    };

    Ecod.dialogClose = function () {
        if (!dialogState) return;
        document.removeEventListener('keydown', dialogTrap, true);
        var back = dialogState.back;
        dialogState = null;
        if (back && document.contains(back)) {
            try { back.focus({ preventScroll: true }); } catch (e) {}
        }
    };

    /* ---------- 主题切换 ---------- */
    var KEY = 'ecod-theme';

    function systemDark() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    function applyTheme(mode) {
        root.setAttribute('data-theme', mode);
        /* theme-color 跟随页面底色，避免移动端地址栏与暗色页面割裂 */
        var meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            var bg = window.getComputedStyle(root).getPropertyValue('--ecod-bg').trim();
            if (bg) meta.setAttribute('content', bg);
        }
        document.querySelectorAll('[data-ecod-theme-toggle]').forEach(function (btn) {
            btn.setAttribute('aria-label', mode === 'dark' ? '切换到浅色模式' : '切换到深色模式');
            /* 只替换图标，保留按钮里的文字标签（如 dock 的「深浅」） */
            var old = btn.querySelector('svg');
            var size = old ? (parseInt(old.getAttribute('width'), 10) || 17) : 17;
            if (old) old.remove();
            btn.insertAdjacentHTML('afterbegin', Ecod.icon(mode === 'dark' ? 'sun' : 'moon', size));
        });
        /* 代码高亮配色跟随明暗（后台选「跟随明暗模式」时输出两套样式） */
        var prismLight = document.getElementById('ecod-prism-light');
        var prismDark = document.getElementById('ecod-prism-dark');
        if (prismLight && prismDark) {
            prismLight.disabled = mode === 'dark';
            prismDark.disabled = mode !== 'dark';
        }
    }

    Ecod.initTheme = function () {
        var saved = null;
        try { saved = localStorage.getItem(KEY); } catch (e) {}
        var policy = root.dataset.dark || 'auto';
        var urlDark = location.search.indexOf('ecod-dark=1') > -1;
        var mode;
        if (urlDark) {
            mode = 'dark';
        } else if (saved) {
            mode = saved;
        } else {
            mode = policy === 'off' || policy === 'on' ? 'light' : (systemDark() ? 'dark' : 'light');
        }
        applyTheme(mode);

        document.querySelectorAll('[data-ecod-theme-toggle]').forEach(function (btn) {
            once(btn, 'theme', 'click', function () {
                var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                applyTheme(next);
                try { localStorage.setItem(KEY, next); } catch (e) {}
            });
        });
    };

    /* ---------- 抽屉 ---------- */
    Ecod.initDrawer = function () {
        var drawer = document.querySelector('[data-ecod-drawer]');
        if (!drawer) return;
        var panel = drawer.querySelector('.ecod_drawer__panel');
        var lastFocus = null;

        /* 开合状态同步到汉堡按钮，读屏与键盘用户才知道菜单是否展开 */
        function syncExpanded(open) {
            document.querySelectorAll('[data-ecod-drawer-open]').forEach(function (b) {
                b.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }

        Ecod.drawerClose = function () {
            drawer.classList.remove('is-open');
            drawer.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('is-locked');
            syncExpanded(false);
            if (lastFocus) {
                try { lastFocus.focus({ preventScroll: true }); } catch (e) {}
            }
        };

        function open() {
            lastFocus = document.activeElement;
            drawer.classList.add('is-open');
            drawer.removeAttribute('aria-hidden');
            document.body.classList.add('is-locked');
            syncExpanded(true);
            Ecod.renderFavs();
            var first = drawer.querySelector('a, button');
            if (first) first.focus({ preventScroll: true });
        }

        document.querySelectorAll('[data-ecod-drawer-open]').forEach(function (b) {
            once(b, 'drawerOpen', 'click', open);
        });
        document.querySelectorAll('[data-ecod-drawer-close]').forEach(function (b) {
            once(b, 'drawerClose', 'click', Ecod.drawerClose);
        });
        once(drawer, 'drawerKey', 'keydown', function (e) {
            if (e.key === 'Escape') { Ecod.drawerClose(); return; }
            if (e.key !== 'Tab' || !panel) return;
            var items = panel.querySelectorAll('a, button');
            if (!items.length) return;
            var first = items[0], last = items[items.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        });
    };

    /* ---------- 导航：下滑隐藏 ---------- */
    Ecod.initHeader = function () {
        var header = document.querySelector('[data-ecod-header]');
        if (!header) return;
        var last = window.scrollY;

        Ecod.addScroll(function () {
            var y = window.scrollY;
            if (y > 160 && y > last + 4) header.classList.add('is-hidden');
            else if (y < last - 4) header.classList.remove('is-hidden');
            last = y;
        });
    };

    /* ---------- 回到顶部 ---------- */
    Ecod.initToTop = function () {
        var btn = document.querySelector('[data-ecod-totop]');
        if (btn) {
            once(btn, 'totop', 'click', function () {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
            Ecod.addScroll(function () {
                btn.classList.toggle('is-show', window.scrollY > 480);
            });
        }

        /* 移动端 dock 的「顶部」按钮 */
        var dockTop = document.querySelector('[data-ecod-dock-top]');
        if (dockTop) {
            once(dockTop, 'dockTop', 'click', function () {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }
    };

    /* ---------- 移动端底部 dock ---------- */
    Ecod.initDock = function () {
        var dock = document.querySelector('[data-ecod-dock]');
        if (!dock) return;
        var search = dock.querySelector('[data-ecod-dock-search]');
        if (search) {
            once(search, 'dockSearch', 'click', function () {
                /* 抽屉已由 data-ecod-drawer-open 打开，这里把焦点交给搜索框 */
                setTimeout(function () {
                    var input = document.querySelector('.ecod_drawer__search input');
                    if (input) input.focus({ preventScroll: true });
                }, 260);
            });
        }
    };

    /* ---------- 站点公告 ---------- */
    var NKEY = 'ecod-notice-closed';
    Ecod.initNotice = function () {
        var bar = document.querySelector('[data-ecod-notice]');
        if (!bar) return;
        var hash = bar.dataset.ecodNoticeHash || '';

        /* 曾被关闭且内容未变：直接收起（无动画，避免闪现） */
        try {
            if (hash && (localStorage.getItem(NKEY) || '') === hash) {
                bar.hidden = true;
                return;
            }
        } catch (e) {}

        var close = bar.querySelector('[data-ecod-notice-close]');
        if (close) {
            once(close, 'noticeClose', 'click', function () {
                bar.classList.add('is-closed');
                setTimeout(function () { bar.hidden = true; }, 240);
                try { if (hash) localStorage.setItem(NKEY, hash); } catch (e) {}
            });
        }
    };

    /* ---------- 阅读进度 ---------- */
    /* 进度计算：0-100 的阅读百分比。顶部进度条与目录进度环共用这一份口径 */
    Ecod.readProgress = function () {
        var article = document.querySelector('[data-ecod-article]');
        if (!article) return 0;
        var total = article.offsetHeight - window.innerHeight + 120;
        var rect = article.getBoundingClientRect();
        var read = Math.min(Math.max(-rect.top + 120, 0), Math.max(total, 1));
        return Math.min(read / Math.max(total, 1) * 100, 100);
    };

    Ecod.initProgress = function () {
        var bar = document.querySelector('[data-ecod-progress]');
        if (!bar) return;
        if (!document.querySelector('[data-ecod-article]')) {
            bar.style.width = '0%';
            return;
        }

        Ecod.addScroll(function () {
            var pct = Ecod.readProgress();
            bar.style.width = pct + '%';
            bar.classList.toggle('is-done', pct >= 99);
        });
    };

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

            /* C3：进度环 / 弹层进度条 / 章节计数，与顶部进度条共用同一口径。
               必须在「章节未变化」时也跑 —— 同一节内滚动 pct 仍在变，
               否则进度只在跨章节时跳变。 */
            function syncProgress(next) {
                if (!showProg || !floatBox) return;
                var pct = Math.round(Ecod.readProgress());
                if (ringBar) ringBar.style.strokeDashoffset = String(100 - pct);
                if (meterBar) meterBar.style.width = pct + '%';
                if (stat) stat.textContent = (next + 1) + ' / ' + targets.length + ' 节 · ' + pct + '%';
                floatBox.classList.toggle('is-done', pct >= 99);
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
            /* C3 目录进度条：ETocProgress=on 时按钮套进度环、弹层头部加进度条与章节计数 */
            var showProg = CFG.tocProgress !== false;
            var RING = '<svg class="ecod_floattoc__ring" viewBox="0 0 36 36" aria-hidden="true">'
                + '<circle class="ecod_floattoc__ring-t" cx="18" cy="18" r="16"/>'
                + '<circle class="ecod_floattoc__ring-b" cx="18" cy="18" r="16" pathLength="100"/></svg>';
            floatBox.innerHTML =
                '<button type="button" class="ecod_floattoc__btn" data-ecod-ft-btn aria-expanded="false" aria-haspopup="true">'
                + '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/></svg>'
                + '<span>目录</span>'
                + (showProg ? RING : '') + '</button>'
                + '<div class="ecod_floattoc__pop" data-ecod-ft-pop role="menu" aria-label="文章目录">'
                + '<p class="ecod_floattoc__head">目录'
                + (showProg ? '<span class="ecod_floattoc__stat" data-ecod-ft-stat></span>' : '') + '</p>'
                + (showProg ? '<div class="ecod_floattoc__meter" aria-hidden="true"><i data-ecod-ft-meter></i></div>' : '')
                + '<nav class="ecod_toc ecod_floattoc__list"></nav>'
                + '</div>';

            var btn = floatBox.querySelector('[data-ecod-ft-btn]');
            var pop = floatBox.querySelector('[data-ecod-ft-pop]');
            var list = floatBox.querySelector('.ecod_floattoc__list');
            var ringBar = floatBox.querySelector('.ecod_floattoc__ring-b');
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

    /* ---------- 收藏（服务端优先，本地镜像兜底） ----------
     * 服务端指纹：游客 = 浏览器指纹，登录用户 = 账号级指纹（跨设备同步）。
     * localStorage 始终保留一份镜像：侧栏收藏组件用它，接口失败时兜底。 */
    var FKEY = 'ecod-favs';
    var FKEY_SYNCED = 'ecod-favs-synced';
    var favRemoteSet = null; /* 服务端已确认的收藏 {cid: 0|1}；null = 尚未获取 */

    function readFavs() {
        try {
            var list = JSON.parse(localStorage.getItem(FKEY) || '[]');
            return Array.isArray(list) ? list : [];
        } catch (e) {
            return [];
        }
    }

    function writeFavs(list) {
        try { localStorage.setItem(FKEY, JSON.stringify(list.slice(0, 60))); } catch (e) {}
        Ecod.renderFavs();
    }

    function isFav(url) {
        return readFavs().some(function (f) { return f.url === url; });
    }

    /* 本地镜像同步（不重复渲染侧栏外的状态） */
    function localSetFav(item, on) {
        var list = readFavs().filter(function (f) { return f.url !== item.url; });
        if (on) list.unshift({ cid: item.cid, title: item.title, url: item.url, time: Date.now() });
        writeFavs(list);
    }

    /* 本地兜底切换：无接口（或接口失败）时使用 */
    function toggleFav(item) {
        var list = readFavs();
        var idx = -1;
        list.forEach(function (f, i) { if (f.url === item.url) idx = i; });
        if (idx > -1) {
            list.splice(idx, 1);
            writeFavs(list);
            return false;
        }
        list.unshift({ cid: item.cid, title: item.title, url: item.url, time: Date.now() });
        writeFavs(list);
        return true;
    }

    /* 服务端收藏切换；失败返回 null，调用方回退本地 */
    function favToggleRemote(cid) {
        if (!CFG.api || !cid) return Promise.resolve(null);
        return fetch(CFG.api + '?action=fav_toggle&cid=' + encodeURIComponent(cid), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json(); }).then(function (data) {
            return data && data.ok ? data : null;
        }).catch(function () { return null; });
    }

    Ecod.renderFavs = function () {
        var box = document.querySelector('[data-ecod-favs]');
        if (!box) return;
        var list = readFavs();
        if (!list.length) {
            box.innerHTML = '<p class="ecod_favs__empty">还没有收藏，点击卡片右上角的星标收藏</p>';
            return;
        }
        var html = '';
        list.forEach(function (f) {
            html += '<a class="ecod_favs__item" href="' + escapeHtml(f.url) + '">'
                + '<span class="ecod_favs__title">' + escapeHtml(f.title || '未命名') + '</span>'
                + '<button type="button" class="ecod_favs__del" data-ecod-fav-del="' + escapeHtml(f.url) + '" aria-label="移除收藏">'
                + Ecod.icon('x', 12) + '</button></a>';
        });
        box.innerHTML = html;

        box.querySelectorAll('[data-ecod-fav-del]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var url = btn.dataset.ecodFavDel;
                writeFavs(readFavs().filter(function (f) { return f.url !== url; }));
                syncFavButtons();
            });
        });
    };

    function setFavBtn(btn, on) {
        btn.classList.toggle('is-on', on);
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    }

    /* 同步页面上某篇文章所有收藏按钮（卡片星标 + 文章操作条） */
    function applyFavState(cid, on) {
        document.querySelectorAll('[data-ecod-act="fav"]').forEach(function (btn) {
            var card = btn.closest('[data-cid]');
            var id = btn.dataset.cid || (card ? card.dataset.cid : '');
            if (String(id) === String(cid)) setFavBtn(btn, on);
        });
    }

    /* 同步页面上所有收藏按钮状态：服务端结果与本地镜像取并集 */
    function syncFavButtons() {
        document.querySelectorAll('[data-ecod-act="fav"]').forEach(function (btn) {
            var card = btn.closest('[data-cid]');
            var cid = btn.dataset.cid || (card ? card.dataset.cid : '');
            var url = btn.dataset.url || (card ? card.dataset.url : '');
            var on = false;
            if (favRemoteSet && cid) on = !!favRemoteSet[String(cid)];
            if (!on && url) on = isFav(url);
            setFavBtn(btn, on);
        });
    }

    /* ---------- 浏览历史（本地，文章页自动记录） ---------- */
    var HKEY = 'ecod-history';

    function readStore(key) {
        try {
            var list = JSON.parse(localStorage.getItem(key) || '[]');
            return Array.isArray(list) ? list : [];
        } catch (e) {
            return [];
        }
    }

    function writeStore(key, list) {
        try { localStorage.setItem(key, JSON.stringify(list)); } catch (e) {}
    }

    Ecod.initHistory = function () {
        var article = document.querySelector('[data-ecod-article][data-cid]');
        if (!article || article.classList.contains('is-locked')) return;

        var item = {
            cid: article.dataset.cid || '',
            title: article.dataset.title || document.title,
            url: article.dataset.url || location.href,
            time: Date.now()
        };
        if (!item.cid) return;

        var list = readStore(HKEY).filter(function (h) { return h.url !== item.url; });
        list.unshift(item);
        writeStore(HKEY, list.slice(0, 50));
    };

    /* ---------- 订单（本地，供支付类插件写入） ----------
     * 格式：{ no, title, amount, status, time }
     * status: pending / paid / closed，插件按需扩展。 */
    var OKEY = 'ecod-orders';

    Ecod.addOrder = function (order) {
        if (!order || !order.title) return false;
        var list = readStore(OKEY).filter(function (o) { return o.no !== order.no; });
        list.unshift({
            no: order.no || 'EC' + Date.now(),
            title: String(order.title),
            amount: order.amount || '',
            status: order.status || 'pending',
            time: order.time || Date.now()
        });
        writeStore(OKEY, list.slice(0, 50));
        Ecod.renderUserCenter();
        return true;
    };

    /* ---------- 个人中心页面 ---------- */
    function fmtTime(ts) {
        var d = new Date(ts);
        var now = Date.now();
        var diff = Math.floor((now - ts) / 1000);
        if (diff < 60) return '刚刚';
        if (diff < 3600) return Math.floor(diff / 60) + ' 分钟前';
        if (diff < 86400) return Math.floor(diff / 3600) + ' 小时前';
        if (diff < 86400 * 30) return Math.floor(diff / 86400) + ' 天前';
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function esc(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function emptyBox(iconName, text) {
        return '<div class="ecod_uc__empty">' + Ecod.icon(iconName, 26) + '<p>' + esc(text) + '</p></div>';
    }

    function postUrl(cid) {
        if (CFG.postUrl) return CFG.postUrl.replace('{cid}', encodeURIComponent(cid));
        return '#';
    }

    function setCount(key, num) {
        var el = document.querySelector('[data-ecod-uc-count="' + key + '"]');
        if (!el) return;
        if (num > 0) {
            el.textContent = num > 99 ? '99+' : num;
            el.hidden = false;
        } else {
            el.hidden = true;
        }
    }

    function renderList(key, html) {
        var box = document.querySelector('[data-ecod-uc-list="' + key + '"]');
        if (box) box.innerHTML = html;
    }

    Ecod.renderUserCenter = function () {
        var root = document.querySelector('[data-ecod-uc]');
        if (!root) return;

        /* 收藏：服务端（登录用户跨设备同步），接口失败回退本地镜像 */
        var favsBox = document.querySelector('[data-ecod-uc-list="favs"]');
        if (favsBox && CFG.api && !favsBox.dataset.ecodLoaded) {
            favsBox.dataset.ecodLoaded = '1';
            favsBox.innerHTML = '<p class="ecod_uc__loading">加载中…</p>';

            function renderFavsList(list) {
                setCount('favs', list.length);
                if (!list.length) {
                    renderList('favs', emptyBox('star', '还没有收藏，点击卡片右上角的星标收藏文章'));
                    return;
                }
                var html = '';
                list.forEach(function (f) {
                    html += '<div class="ecod_uc__item">'
                        + '<a class="ecod_uc__main" href="' + esc(postUrl(f.cid)) + '"><b>' + esc(f.title || ('文章 #' + f.cid)) + '</b>'
                        + '<span>' + fmtTime((f.fav_at || 0) * 1000) + '收藏</span></a>'
                        + '<button type="button" class="ecod_uc__del" data-ecod-uc-delfav="' + esc(f.cid) + '" aria-label="取消收藏">' + Ecod.icon('x', 13) + '</button>'
                        + '</div>';
                });
                renderList('favs', html);
            }

            /* 旧本地收藏一次性合并到服务端（仅登录用户；游客 403 忽略） */
            var localFavs = readFavs();
            var logged = document.querySelector('[data-ecod-user][data-ecod-logged]');
            if (logged && localFavs.length) {
                try {
                    if (!localStorage.getItem(FKEY_SYNCED)) {
                        fetch(CFG.api + '?action=favs_sync', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'cids=' + encodeURIComponent(localFavs.map(function (f) { return f.cid; }).join(','))
                        }).then(function (r) { return r.json(); }).then(function (data) {
                            if (data && data.ok) {
                                try { localStorage.setItem(FKEY_SYNCED, '1'); } catch (e) {}
                            }
                        }).catch(function () {});
                    }
                } catch (e) {}
            }

            fetch(CFG.api + '?action=my_favs', { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.ok) throw new Error('bad');
                    renderFavsList(data.list || []);
                })
                .catch(function () {
                    renderFavsList(readFavs().map(function (f) {
                        return { cid: f.cid, title: f.title, fav_at: Math.floor((f.time || 0) / 1000) };
                    }));
                });
        }

        /* 浏览历史 */
        var history = readStore(HKEY);
        setCount('history', history.length);
        if (!history.length) {
            renderList('history', emptyBox('clock', '暂无浏览记录'));
        } else {
            var html = '';
            history.forEach(function (h) {
                html += '<a class="ecod_uc__item" href="' + esc(h.url) + '">'
                    + '<span class="ecod_uc__main"><b>' + esc(h.title || '未命名') + '</b>'
                    + '<span>' + fmtTime(h.time) + '浏览</span></span>'
                    + '</a>';
            });
            renderList('history', html);
        }

        /* 订单 */
        var orders = readStore(OKEY);
        setCount('orders', orders.length);
        if (!orders.length) {
            renderList('orders', emptyBox('inbox', '暂无订单；支付类插件提交的订单会显示在这里'));
        } else {
            var statusMap = { pending: '待支付', paid: '已完成', closed: '已关闭' };
            var html = '';
            orders.forEach(function (o) {
                html += '<div class="ecod_uc__item is-order">'
                    + '<span class="ecod_uc__main"><b>' + esc(o.title) + '</b>'
                    + '<span>' + esc(o.no) + ' · ' + fmtTime(o.time) + '</span></span>'
                    + '<span class="ecod_uc__order-meta"><em class="is-' + esc(o.status) + '">' + esc(statusMap[o.status] || o.status) + '</em>'
                    + (o.amount !== '' ? '<strong>' + esc(o.amount) + '</strong>' : '')
                    + '</span></div>';
            });
            renderList('orders', html);
        }

        /* 我的点赞（服务端指纹） */
        var likeBox = document.querySelector('[data-ecod-uc-list="likes"]');
        if (likeBox && !likeBox.dataset.ecodLikesLoaded && CFG.api) {
            likeBox.dataset.ecodLikesLoaded = '1';
            likeBox.innerHTML = '<p class="ecod_uc__loading">加载中…</p>';
            fetch(CFG.api + '?action=my_likes', { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var list = (data && data.ok && data.list) || [];
                    setCount('likes', list.length);
                    if (!list.length) {
                        likeBox.innerHTML = emptyBox('heart', '还没有赞过的文章，去给喜欢的文章点个赞吧');
                        return;
                    }
                    var html = '';
                    list.forEach(function (l) {
                        html += '<a class="ecod_uc__item" href="' + esc(postUrl(l.cid)) + '">'
                            + '<span class="ecod_uc__main"><b>' + esc(l.title || ('文章 #' + l.cid)) + '</b>'
                            + '<span>' + fmtTime((l.liked_at || 0) * 1000) + '赞过</span></span>'
                            + '</a>';
                    });
                    likeBox.innerHTML = html;
                })
                .catch(function () {
                    likeBox.innerHTML = emptyBox('alert', '点赞记录加载失败，请刷新重试');
                });
        }

        /* 取消收藏：事件委托绑定一次（收藏列表是异步渲染的） */
        if (!root.dataset.ecodFavDel) {
            root.dataset.ecodFavDel = '1';
            root.addEventListener('click', function (e) {
                var btn = e.target.closest ? e.target.closest('[data-ecod-uc-delfav]') : null;
                if (!btn) return;
                e.preventDefault();
                var cid = btn.dataset.ecodUcDelfav;
                var item = btn.closest('.ecod_uc__item');
                var link = item ? item.querySelector('a.ecod_uc__main') : null;
                var url = link ? link.getAttribute('href') : '';
                /* 本地镜像同步移除 */
                writeFavs(readFavs().filter(function (f) { return f.url !== url; }));
                if (item) item.remove();
                if (CFG.api && cid) {
                    if (favRemoteSet) favRemoteSet[String(cid)] = 0;
                    fetch(CFG.api + '?action=fav_toggle&cid=' + encodeURIComponent(cid), {
                        method: 'POST',
                        credentials: 'same-origin'
                    }).catch(function () {});
                }
                syncFavButtons();
            });
        }

        /* 清空历史 */
        var clearBtn = root.querySelector('[data-ecod-uc-clear="history"]');
        if (clearBtn && !clearBtn.dataset.ecodBound) {
            clearBtn.dataset.ecodBound = '1';
            clearBtn.addEventListener('click', function () {
                writeStore(HKEY, []);
                Ecod.renderUserCenter();
            });
        }

        /* Tab 切换（支持 #favs 之类的 hash 深链） */
        function activateTab(key) {
            root.querySelectorAll('[data-ecod-uc-tab]').forEach(function (t) {
                t.classList.toggle('is-active', t.dataset.ecodUcTab === key);
            });
            root.querySelectorAll('[data-ecod-uc-panel]').forEach(function (p) {
                var on = p.dataset.ecodUcPanel === key;
                p.classList.toggle('is-active', on);
                p.hidden = !on;
            });
        }

        if (!root.dataset.ecodTabs) {
            root.dataset.ecodTabs = '1';
            var hashKey = (location.hash || '').replace('#', '');
            if (hashKey && root.querySelector('[data-ecod-uc-tab="' + hashKey + '"]')) {
                activateTab(hashKey);
            }
            root.querySelectorAll('[data-ecod-uc-tab]').forEach(function (tab) {
                tab.addEventListener('click', function () {
                    activateTab(tab.dataset.ecodUcTab);
                    try { history.replaceState(null, '', '#' + tab.dataset.ecodUcTab); } catch (e) {}
                });
            });
        }
    };

    /* ---------- 头部用户面板：点外部收起 ---------- */
    Ecod.initUserPop = function () {
        document.addEventListener('click', function (e) {
            document.querySelectorAll('details.ecod_nav__user[open]').forEach(function (d) {
                if (!d.contains(e.target)) {
                    d.removeAttribute('open');
                }
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('details.ecod_nav__user[open]').forEach(function (d) {
                    d.removeAttribute('open');
                });
            }
        });
    };

    /* 数字缩写：千位以上显示为 k（如 1k、1.2k、123k） */
    function shortNum(n) {
        if (n < 1000) return String(n);
        var v = n / 1000;
        v = v >= 100 ? Math.round(v) : Math.round(v * 10) / 10;
        return v + 'k';
    }

    /* 同步某篇文章在所有位置（卡片 / 正文操作条）的点赞数 */
    function setLikeNum(cid, count) {
        count = parseInt(count, 10) || 0;
        document.querySelectorAll('[data-ecod-act="like"]').forEach(function (btn) {
            var card = btn.closest('[data-cid]');
            var id = btn.dataset.cid || (card ? card.dataset.cid : '');
            if (String(id) !== String(cid)) return;
            var el = btn.querySelector('[data-ecod-like-num]');
            if (el) {
                if (el.dataset.ecodLikeNum === 'full') {
                    el.textContent = count > 0 ? shortNum(count) + ' 赞' : '点赞';
                } else {
                    el.textContent = count > 0 ? shortNum(count) : '';
                }
            }
            btn.classList.toggle('has-num', count > 0);
        });
    }

    /* 卡片操作组移入缩略图内定位（与角标同基准，随图片尺寸自适应） */
    Ecod.initCardAct = function () {
        document.querySelectorAll('.ecod_card__act').forEach(function (act) {
            if (act.closest('.ecod_card__media')) return;
            var card = act.closest('.ecod_card');
            var media = card ? card.querySelector(':scope > .ecod_card__media') : null;
            if (!media) return;
            media.removeAttribute('aria-hidden');
            media.appendChild(act);
        });
    };

    /* 回填点赞数 + 已赞状态（服务端按 IP 判定） */
    Ecod.initLikeState = function () {
        if (!CFG.api) return;
        var cids = [];
        document.querySelectorAll('[data-ecod-act="like"]').forEach(function (btn) {
            var card = btn.closest('[data-cid]');
            var id = btn.dataset.cid || (card ? card.dataset.cid : '');
            if (id && cids.indexOf(String(id)) === -1) cids.push(String(id));
        });
        if (cids.length) {
            fetch((CFG.api || '') + '?action=state&cids=' + encodeURIComponent(cids.join(',')), {
                credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data || !data.ok) return;
            var counts = data.counts || {};
            Object.keys(counts).forEach(function (cid) { setLikeNum(cid, counts[cid]); });
            (data.liked || []).forEach(function (cid) {
                document.querySelectorAll('[data-ecod-act="like"]').forEach(function (btn) {
                    var card = btn.closest('[data-cid]');
                    var id = btn.dataset.cid || (card ? card.dataset.cid : '');
                    if (String(id) !== String(cid)) return;
                    btn.classList.add('is-on');
                    btn.setAttribute('aria-pressed', 'true');
                });
            });
        }).catch(function () {});
        }

        /* 收藏状态回填：favs_state 一次拿到「我是否收藏过」 */
        var favCids = [];
        document.querySelectorAll('[data-ecod-act="fav"]').forEach(function (btn) {
            var card = btn.closest('[data-cid]');
            var id = btn.dataset.cid || (card ? card.dataset.cid : '');
            if (id && favCids.indexOf(String(id)) === -1) favCids.push(String(id));
        });
        if (favCids.length) {
            fetch((CFG.api || '') + '?action=favs_state&cids=' + encodeURIComponent(favCids.join(',')), {
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (!data || !data.ok) return;
                favRemoteSet = {};
                (data.favs || []).forEach(function (cid) {
                    favRemoteSet[String(cid)] = 1;
                });
                syncFavButtons();
            }).catch(function () {});
        }
    };

    /* ---------- 点赞 / 收藏 / 分享：事件委托 ---------- */
    Ecod.initActions = function () {
        once(document, 'act', 'click', function (e) {
            var btn = e.target.closest ? e.target.closest('[data-ecod-act]') : null;
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();

            var card = btn.closest('[data-cid]');
            var cid = btn.dataset.cid || (card ? card.dataset.cid : '');
            var url = btn.dataset.url || (card ? card.dataset.url : location.href);
            var title = btn.dataset.title || (card ? card.dataset.title : document.title);

            if (btn.dataset.ecodAct === 'fav') {
                if (btn.dataset.busy === '1') return;
                btn.dataset.busy = '1';
                var item = { cid: cid, title: title, url: url };
                var pop = function () {
                    btn.classList.add('is-pop');
                    setTimeout(function () { btn.classList.remove('is-pop'); }, 320);
                };

                favToggleRemote(cid).then(function (data) {
                    btn.dataset.busy = '';
                    if (data) {
                        /* 服务端为准 */
                        setFavBtn(btn, data.fav);
                        applyFavState(cid, data.fav);
                        if (favRemoteSet) favRemoteSet[String(cid)] = data.fav ? 1 : 0;
                        localSetFav(item, data.fav);
                        if (data.fav) pop();
                        live(data.fav ? '已加入收藏' : '已取消收藏');
                    } else {
                        /* 无接口（或接口失败）：本地兜底 */
                        var on = toggleFav(item);
                        setFavBtn(btn, on);
                        if (on) pop();
                        live(on ? '已加入收藏' : '已取消收藏');
                    }
                });
                return;
            }

            if (btn.dataset.ecodAct === 'like') {
                if (btn.dataset.busy === '1' || btn.classList.contains('is-on')) return;
                btn.dataset.busy = '1';
                fetch((CFG.api || '') + '?action=like&cid=' + encodeURIComponent(cid), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function (r) { return r.json(); }).then(function (data) {
                    btn.dataset.busy = '';
                    if (!data || !data.ok) return;

                    /* 状态以服务端为准：mine = 这个访客确实赞过 */
                    if (data.mine) {
                        btn.classList.add('is-on');
                        btn.setAttribute('aria-pressed', 'true');
                    }
                    if (typeof data.count === 'number') setLikeNum(cid, data.count);
                    if (data.liked) {
                        btn.classList.add('is-pop');
                        setTimeout(function () { btn.classList.remove('is-pop'); }, 320);
                    }
                    if (data.mine) live('已点赞');
                }).catch(function () { btn.dataset.busy = ''; });
            }
        });

        /* 分享：复制当前文章链接 */
        once(document, 'share', 'click', function (e) {
            var btn = e.target.closest ? e.target.closest('[data-ecod-share]') : null;
            if (!btn) return;
            e.preventDefault();
            var label = btn.querySelector('span');
            copyText(location.href, function () {
                if (!label) return;
                var old = label.textContent;
                label.textContent = '已复制';
                var box = btn.closest('details');
                setTimeout(function () {
                    label.textContent = old;
                    if (box) box.open = false;
                }, 900);
            });
        });

        /* 系统分享（移动端 / 支持的浏览器） */
        once(document, 'shareNative', 'click', function (e) {
            var btn = e.target.closest ? e.target.closest('[data-ecod-share-native]') : null;
            if (!btn || !navigator.share) return;
            e.preventDefault();
            var host = btn.closest('[data-ecod-postact]');
            navigator.share({
                title: host ? (host.dataset.title || document.title) : document.title,
                url: host ? (host.dataset.url || location.href) : location.href
            }).catch(function () {});
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

    /* ---------- F3 分类工具条：/ 热键聚焦分类内搜索 ---------- */
    Ecod.initCatTools = function () {
        var box = document.querySelector('[data-ecod-cattools]');
        if (!box || box.dataset.catToolsReady === '1') return;
        box.dataset.catToolsReady = '1';
        var input = box.querySelector('input[type="search"]');
        if (!input) return;

        once(document, 'cattools', 'keydown', function (e) {
            /* / 聚焦：正在输入时（输入框/文本域/可编辑元素）不抢 */
            var t = e.target;
            var typing = t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable);
            if (e.key === '/' && !typing && !e.ctrlKey && !e.metaKey && !e.altKey) {
                e.preventDefault();
                input.focus();
                input.select();
            } else if (e.key === 'Escape' && document.activeElement === input) {
                input.blur();
            }
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

    /* ---------- 右键菜单 ---------- */
    var ctxEl = null;

    function escHtml(str) {
        return String(str).replace(/[&<>"']/g, function (ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
        });
    }

    function ctxClose() {
        if (!ctxEl || ctxEl.hidden) return;
        ctxEl.hidden = true;
        ctxEl.classList.remove('is-open');
    }

    function ctxRender(items) {
        if (!ctxEl) return;
        ctxEl.innerHTML = '';
        items.forEach(function (it) {
            if (it.sep) {
                var sep = document.createElement('div');
                sep.className = 'ecod_ctx__sep';
                ctxEl.appendChild(sep);
                return;
            }
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ecod_ctx__item';
            btn.setAttribute('role', 'menuitem');
            btn.innerHTML = (it.icon ? Ecod.icon(it.icon, 14) : '<span class="ecod_ctx__noic"></span>')
                + '<span class="ecod_ctx__label">' + it.label + '</span>'
                + (it.hint ? '<i class="ecod_ctx__hint">' + it.hint + '</i>' : '');
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                ctxClose();
                try { it.fn(); } catch (err) {}
            });
            ctxEl.appendChild(btn);
        });
    }

    function ctxPlace(x, y) {
        ctxEl.hidden = false;
        ctxEl.classList.add('is-open');
        var r = ctxEl.getBoundingClientRect();
        var left = Math.max(8, Math.min(x, window.innerWidth - r.width - 8));
        var top = Math.max(8, Math.min(y, window.innerHeight - r.height - 8));
        ctxEl.style.left = left + 'px';
        ctxEl.style.top = top + 'px';
    }

    Ecod.initCtxMenu = function () {
        if (CFG.ctxmenu === false || CFG.ctxmenu === 'false') return;
        if (document._ecodCtx) return;
        document._ecodCtx = 1;

        ctxEl = document.createElement('div');
        ctxEl.className = 'ecod_ctx';
        ctxEl.setAttribute('role', 'menu');
        ctxEl.hidden = true;
        document.body.appendChild(ctxEl);

        /* 构建菜单项：鼠标右键与键盘（Shift+F10 / 菜单键）共用同一套逻辑 */
        function buildItems(t) {
            var link = t.closest('a[href]');
            var host = t.closest('.ecod_card, .ecod_titem, .ecod_slider, .ecod_rankcard, .ecod_flash__item, .ecod_article, .ecod_coll__card');
            var sel = window.getSelection ? String(window.getSelection()) : '';
            sel = sel.replace(/\s+/g, ' ').trim();

            if (!link && !host && sel === '') return null;

            var items = [];
            var isArticle = !!(host && host.classList.contains('ecod_article'));

            if (sel !== '') {
                var shortSel = sel.length > 14 ? sel.slice(0, 14) + '…' : sel;
                items.push({
                    icon: 'copy',
                    label: '复制「' + escHtml(shortSel) + '」',
                    fn: function () { copyText(sel); }
                });
                items.push({
                    icon: 'search',
                    label: '搜索这段文字',
                    fn: function () {
                        window.open('https://www.bing.com/search?q=' + encodeURIComponent(sel.slice(0, 100)), '_blank', 'noopener');
                    }
                });
            }

            if (link) {
                if (items.length) items.push({ sep: true });
                items.push({
                    icon: 'external',
                    label: '在新标签打开',
                    fn: function () { window.open(link.href, '_blank', 'noopener'); }
                });
                items.push({
                    icon: 'link',
                    label: '复制链接地址',
                    fn: function () { copyText(link.href); }
                });
            }

            if (host && sel === '' && !link) {
                var titleNode = host.querySelector('.ecod_article__title, .ecod_card__title, .ecod_flash__title, .ecod_titem__title');
                var hostTitle = host.getAttribute('data-title') || (titleNode ? titleNode.textContent : '');
                hostTitle = (hostTitle || '').trim();
                if (hostTitle !== '') {
                    items.push({
                        icon: 'file',
                        label: '复制标题',
                        fn: function () { copyText(hostTitle); }
                    });
                }
            }

            if (isArticle) {
                items.push({
                    icon: 'list',
                    label: '回到顶部',
                    fn: function () { window.scrollTo({ top: 0, behavior: 'smooth' }); }
                });
            }

            items.push({ sep: true });
            items.push({
                icon: root.getAttribute('data-theme') === 'dark' ? 'sun' : 'moon',
                label: '切换深浅色',
                fn: function () {
                    var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                    applyTheme(next);
                    try { localStorage.setItem(KEY, next); } catch (err) {}
                }
            });

            return items;
        }

        function openMenu(target, x, y) {
            if (!target || !target.closest) return false;
            if (target.closest('input, textarea, select, [contenteditable="true"], [data-ecod-noctx]')) return false;
            var items = buildItems(target);
            if (!items) return false;
            ctxRender(items);
            ctxPlace(x, y);
            var first = ctxEl.querySelector('.ecod_ctx__item');
            if (first) first.focus({ preventScroll: true });
            return true;
        }

        document.addEventListener('contextmenu', function (e) {
            if (openMenu(e.target, e.clientX, e.clientY)) e.preventDefault();
        });

        /* 键盘等价入口：Shift+F10 与专用菜单键，锚点是当前焦点元素的包围盒 */
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'ContextMenu' && !(e.shiftKey && e.key === 'F10')) return;
            var t = document.activeElement;
            if (!t || t === document.body) return;
            var r = t.getBoundingClientRect();
            if (openMenu(t, r.left + Math.min(r.width, 24), r.top + Math.min(r.height, 24))) e.preventDefault();
        });

        /* 菜单内 ↑↓ Home End 移动，符合菜单惯用键位 */
        ctxEl.addEventListener('keydown', function (e) {
            if (ctxEl.hidden) return;
            var items = Array.prototype.slice.call(ctxEl.querySelectorAll('.ecod_ctx__item'));
            if (!items.length) return;
            var at = items.indexOf(document.activeElement);
            var to = -1;
            if (e.key === 'ArrowDown') to = at < 0 ? 0 : (at + 1) % items.length;
            else if (e.key === 'ArrowUp') to = at <= 0 ? items.length - 1 : at - 1;
            else if (e.key === 'Home') to = 0;
            else if (e.key === 'End') to = items.length - 1;
            if (to < 0) return;
            e.preventDefault();
            items[to].focus();
        });

        document.addEventListener('click', function (e) {
            if (ctxEl && !ctxEl.hidden && !ctxEl.contains(e.target)) ctxClose();
        }, true);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') ctxClose();
        });
        window.addEventListener('scroll', ctxClose, { passive: true });
        window.addEventListener('resize', ctxClose);
        window.addEventListener('blur', ctxClose);
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

    /* ---------- 首页分类条：溢出时折叠 ---------- */
    Ecod.initCatbar = function () {
        var bars = document.querySelectorAll('[data-ecod-catbar]');
        Array.prototype.forEach.call(bars, function (bar) {
            if (bar._ecodCatbar) return;
            bar._ecodCatbar = 1;

            var list = bar.querySelector('.ecod_tabs');
            var btn = bar.querySelector('[data-ecod-catbar-more]');
            if (!list || !btn) return;

            bar._ecodCheck = function () {
                if (bar.classList.contains('is-expanded')) return;
                bar.classList.toggle('is-overflow', list.scrollWidth - list.clientWidth > 4);
            };
            bar._ecodCheck();

            btn.addEventListener('click', function () {
                var open = bar.classList.toggle('is-expanded');
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                btn.textContent = open ? '收起' : '更多';
                if (!open) bar._ecodCheck();
            });
        });

        if (Ecod._catbarResize) return;
        Ecod._catbarResize = 1;
        var timer = null;
        window.addEventListener('resize', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                Array.prototype.forEach.call(document.querySelectorAll('[data-ecod-catbar]'), function (bar) {
                    if (bar._ecodCheck) bar._ecodCheck();
                });
            }, 150);
        });
    };

    /* ---------- 侧栏排行榜：周期切换 ---------- */
    Ecod.initRank = function () {
        document.querySelectorAll('.ecod_rankcard').forEach(function (card) {
            if (card._ecodRank) return;
            card._ecodRank = 1;

            var tabs = card.querySelectorAll('[data-ecod-rank-tab]');
            var panels = card.querySelectorAll('[data-ecod-rank-panel]');
            if (!tabs.length || !panels.length) return;

            function activate(tab) {
                var key = tab.getAttribute('data-ecod-rank-tab');
                Array.prototype.forEach.call(tabs, function (one) {
                    var on = one === tab;
                    one.classList.toggle('is-active', on);
                    one.setAttribute('aria-selected', on ? 'true' : 'false');
                    one.setAttribute('tabindex', on ? '0' : '-1');
                });
                Array.prototype.forEach.call(panels, function (panel) {
                    panel.classList.toggle('is-active', panel.getAttribute('data-ecod-rank-panel') === key);
                });
            }

            Array.prototype.forEach.call(tabs, function (tab, i) {
                tab.addEventListener('click', function () { activate(tab); });
                /* tabs 模式：左右/上下移动焦点即切换，Home/End 跳首尾 */
                tab.addEventListener('keydown', function (e) {
                    var next = -1;
                    if (e.key === 'ArrowRight' || e.key === 'ArrowDown') next = (i + 1) % tabs.length;
                    else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') next = (i - 1 + tabs.length) % tabs.length;
                    else if (e.key === 'Home') next = 0;
                    else if (e.key === 'End') next = tabs.length - 1;
                    if (next < 0) return;
                    e.preventDefault();
                    tabs[next].focus();
                    activate(tabs[next]);
                });
            });
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

    /* ---------- 快捷键：Cmd/Ctrl + K ---------- */
    /* ESearchPanel=on 时唤起居中搜索面板；off 时退回旧行为：聚焦导航搜索框 */
    Ecod.initHotkey = function () {
        once(document, 'hotkey', 'keydown', function (e) {
            if ((e.metaKey || e.ctrlKey) && (e.key === 'k' || e.key === 'K')) {
                e.preventDefault();
                if (Ecod.searchPanelOpen) {
                    Ecod.searchPanelOpen();
                    return;
                }
                var input = document.querySelector('[data-ecod-search-input]');
                if (input) input.focus();
            }
        });
    };

    /* ---------- 居中搜索面板：热词 / 标签 / 实时结果 / 键盘导航 ---------- */
    Ecod.initSearchPanel = function () {
        var panel = document.querySelector('[data-ecod-spanel]');
        if (!panel || panel.dataset.ecodPanel) return;
        panel.dataset.ecodPanel = '1';

        var input = panel.querySelector('[data-ecod-spanel-input]');
        var body = panel.querySelector('[data-ecod-spanel-body]');
        var all = panel.querySelector('[data-ecod-spanel-all]');
        if (!input || !body) return;

        var searchTpl = CFG.searchUrl || '/?s={q}';
        /* 搜索地址：路由模板（/search/{q}/）优先，退化为 ?s= 查询串 */
        function searchHref(q) {
            var enc = encodeURIComponent(q);
            if (searchTpl.indexOf('{q}') > -1) return searchTpl.replace('{q}', enc);
            return searchTpl + (searchTpl.indexOf('?') > -1 ? '&' : '?') + 's=' + enc;
        }
        var hotwords = Array.isArray(CFG.hotwords) ? CFG.hotwords : [];
        var tags = [];
        try { tags = JSON.parse(panel.getAttribute('data-tags') || '[]'); } catch (e) {}
        if (!Array.isArray(tags)) tags = [];

        function isOpen() { return panel.classList.contains('is-open'); }

        function open(prefill) {
            /* 已经打开时只补焦点，不要把用户正在输入的内容重置回空态 */
            if (isOpen()) {
                input.focus();
                return;
            }
            panel.classList.add('is-open');
            panel.removeAttribute('aria-hidden');
            renderIdle(prefill || '');
            if (prefill) {
                input.value = prefill;
                onInput();
            }
            setTimeout(function () { input.focus(); }, 60);
        }

        function close() {
            panel.classList.remove('is-open');
            panel.setAttribute('aria-hidden', 'true');
            input.blur();
        }

        Ecod.searchPanelOpen = open;
        Ecod.searchPanelClose = close;

        panel.querySelectorAll('[data-ecod-spanel-close]').forEach(function (el) {
            once(el, 'spanelClose', 'click', close);
        });

        /* 导航搜索框点击 → 打开面板（capture 拦截，原表单不再聚焦弹下拉） */
        document.addEventListener('click', function (e) {
            var box = e.target.closest ? e.target.closest('.ecod_nav__search') : null;
            if (!box) return;
            e.preventDefault();
            open();
        }, true);

        /* 移动端 dock 搜索按钮 → 打开面板；面板关闭（ESearchPanel=off）时保留抽屉 */
        document.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('[data-ecod-dock-search]') : null;
            if (!btn) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            open();
        }, true);

        /* 遮罩外的点击落在面板外也关闭（只拦一次点击，避免误伤） */
        document.addEventListener('keydown', function (e) {
            if (!isOpen()) return;
            if (e.key === 'Escape') {
                e.preventDefault();
                close();
            }
        });

        /* ---- 空态：最近搜索 + 热词 + 标签 ---- */
        function chipRow(label, cls, items, withIcon) {
            if (!items.length) return '';
            var html = '<div class="ecod_spanel__row ' + cls + '"><div class="ecod_spanel__rowlabel">' + label + '</div><div class="ecod_spanel__chips">';
            items.forEach(function (it) {
                var text = typeof it === 'string' ? it : it.name;
                var href = typeof it === 'string' ? searchHref(it) : it.url;
                html += '<a class="ecod_spanel__chip ecod_spanel__item" href="' + esc(href) + '" data-ecod-spanel-q="' + esc(typeof it === 'string' ? it : it.name) + '">'
                    + (withIcon ? Ecod.icon('zap', 12) : '')
                    + '<span>' + esc(text) + '</span></a>';
            });
            return html + '</div></div>';
        }

        function renderIdle() {
            var html = '';
            var hist = readHist();
            if (hist.length) {
                html += '<div class="ecod_spanel__row"><div class="ecod_spanel__rowlabel">最近搜索</div><div class="ecod_spanel__chips">';
                hist.forEach(function (h) {
                    html += '<a class="ecod_spanel__chip ecod_spanel__item is-hist" href="' + esc(searchHref(h)) + '" data-ecod-spanel-q="' + esc(h) + '">'
                        + Ecod.icon('clock', 12) + '<span>' + esc(h) + '</span></a>';
                });
                html += '</div></div>';
            }
            html += chipRow('热词', 'is-hot', hotwords, false);
            html += chipRow('标签', 'is-tags', tags, false);
            if (!html) html = '<div class="ecod_spanel__hint">输入关键词，回车全站搜索</div>';
            body.innerHTML = html;
            body.classList.remove('is-results');
        }

        /* ---- 实时结果 ---- */
        var timer = 0;
        function onInput() {
            var q = input.value.trim();
            clearTimeout(timer);
            if (!q) {
                renderIdle();
                return;
            }
            timer = setTimeout(function () { fetchResults(q); }, 180);
        }

        function fetchResults(q) {
            fetch((CFG.api || '') + '?action=search_suggest&q=' + encodeURIComponent(q), {
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (input.value.trim() !== q) return;
                if (!data || !data.ok) return;
                renderResults(data.list || [], q);
            }).catch(function () {});
        }

        function renderResults(items, q) {
            var html = '<div class="ecod_spanel__rowlabel">搜索结果 · 回车全站搜索</div>';
            if (items.length) {
                html += '<div class="ecod_spanel__results">';
                items.forEach(function (it) {
                    html += '<a class="ecod_spanel__result ecod_spanel__item" href="' + esc(postUrl(it.cid)) + '" data-ecod-spanel-q="' + esc(q) + '">'
                        + '<span class="ecod_spanel__result-t">' + highlightTitle(it.title, q) + '</span>'
                        + (it.date ? '<time>' + esc(it.date) + '</time>' : '')
                        + '</a>';
                });
                html += '</div>';
            } else {
                html += '<div class="ecod_spanel__empty">没有匹配「' + esc(q) + '」的文章，回车全站搜索</div>';
            }
            body.innerHTML = html;
            body.classList.add('is-results');
        }

        input.addEventListener('input', onInput);

        /* ---- 键盘：↑↓ 移动高亮，Enter 打开， Esc 已由全局接管 ---- */
        var activeIdx = -1;
        function items() {
            return Array.prototype.slice.call(body.querySelectorAll('.ecod_spanel__item'));
        }
        function setActive(i) {
            var list = items();
            if (!list.length) return;
            activeIdx = (i + list.length) % list.length;
            list.forEach(function (el, k) { el.classList.toggle('is-hl', k === activeIdx); });
            var cur = list[activeIdx];
            if (cur && cur.scrollIntoView) cur.scrollIntoView({ block: 'nearest' });
        }
        input.addEventListener('keydown', function (e) {
            var list = items();
            if (e.key === 'ArrowDown') { e.preventDefault(); setActive(activeIdx + 1); return; }
            if (e.key === 'ArrowUp') { e.preventDefault(); setActive(activeIdx - 1); return; }
            if (e.key === 'Enter') {
                e.preventDefault();
                var q = input.value.trim();
                if (activeIdx > -1 && list[activeIdx]) {
                    var a = list[activeIdx];
                    if (a.getAttribute('data-ecod-spanel-q')) pushHist(a.getAttribute('data-ecod-spanel-q'));
                    window.location.href = a.href;
                    return;
                }
                if (q) {
                    pushHist(q);
                    window.location.href = searchHref(q);
                }
            }
        });

        /* 点击热词 / 标签 / 最近搜索 → 回填并即时搜索；点结果记历史 */
        body.addEventListener('click', function (e) {
            var chip = e.target.closest ? e.target.closest('.ecod_spanel__chip') : null;
            if (chip) {
                e.preventDefault();
                input.value = chip.getAttribute('data-ecod-spanel-q') || chip.textContent.trim();
                activeIdx = -1;
                onInput();
                return;
            }
            var result = e.target.closest ? e.target.closest('.ecod_spanel__result') : null;
            if (result) {
                var q = result.getAttribute('data-ecod-spanel-q');
                if (q) pushHist(q);
            }
        });

        if (all) {
            all.addEventListener('click', function () {
                var q = input.value.trim();
                if (q) pushHist(q);
            });
        }
    };

    /* ---------- 搜索下拉：最近搜索 + 标题联想 ---------- */
    var SKEY = 'ecod-search-history';

    /* 最近搜索：导航下拉与搜索面板共用一份 localStorage */
    function readHist() {
        try {
            var list = JSON.parse(localStorage.getItem(SKEY) || '[]');
            return Array.isArray(list) ? list.filter(function (q) { return typeof q === 'string' && q; }) : [];
        } catch (e) {
            return [];
        }
    }

    function pushHist(q) {
        var list = readHist().filter(function (x) { return x !== q; });
        list.unshift(q);
        try { localStorage.setItem(SKEY, JSON.stringify(list.slice(0, 8))); } catch (e) {}
    }

    function highlightTitle(title, q) {
        var t = esc(title);
        if (!q) return t;
        try {
            var re = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'i');
            t = t.replace(re, '<mark>$1</mark>');
        } catch (e) {}
        return t;
    }

    Ecod.initSearchSuggest = function () {
        document.querySelectorAll('[data-ecod-search-input]').forEach(function (input) {
            if (input.dataset.ecodSuggest) return;
            input.dataset.ecodSuggest = '1';
            var form = input.closest('form');
            if (!form) return;
            if (getComputedStyle(form).position === 'static') form.style.position = 'relative';

            var drop = document.createElement('div');
            drop.className = 'ecod_suggest';
            drop.hidden = true;
            form.appendChild(drop);

            function renderList(items, q) {
                var html = '';
                if (q && items.length) {
                    html += '<div class="ecod_suggest__label">搜索结果</div>';
                    items.forEach(function (it) {
                        html += '<a class="ecod_suggest__item" href="' + esc(postUrl(it.cid)) + '">' + highlightTitle(it.title, q) + '</a>';
                    });
                }
                if (!q) {
                    var hist = readHist();
                    if (hist.length) {
                        html += '<div class="ecod_suggest__label">最近搜索</div>';
                        hist.forEach(function (h) {
                            html += '<a class="ecod_suggest__item is-hist" href="#" data-ecod-suggest-hist="' + esc(h) + '">'
                                + Ecod.icon('clock', 13) + '<span>' + esc(h) + '</span></a>';
                        });
                    }
                }
                if (!html) {
                    if (q) {
                        html = '<div class="ecod_suggest__empty">没有匹配「' + esc(q) + '」的文章，回车全站搜索</div>';
                    } else {
                        drop.hidden = true;
                        return;
                    }
                }
                drop.innerHTML = html;
                drop.hidden = false;
            }

            function fetchSuggest(q) {
                fetch((CFG.api || '') + '?action=search_suggest&q=' + encodeURIComponent(q), {
                    credentials: 'same-origin'
                }).then(function (r) { return r.json(); }).then(function (data) {
                    if (!data || !data.ok) return;
                    if (input.value.trim() !== q) return; /* 输入已变化，丢弃过期结果 */
                    renderList(data.list || [], q);
                }).catch(function () {});
            }

            var timer = 0;
            function onInput() {
                var q = input.value.trim();
                clearTimeout(timer);
                if (!q) {
                    renderList([], '');
                    return;
                }
                timer = setTimeout(function () { fetchSuggest(q); }, 180);
            }

            input.addEventListener('focus', function () {
                renderList([], input.value.trim());
            });
            input.addEventListener('input', onInput);
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    drop.hidden = true;
                }
            });
            form.addEventListener('submit', function () {
                var q = input.value.trim();
                if (q) pushHist(q);
            });
            drop.addEventListener('click', function (e) {
                var hist = e.target.closest ? e.target.closest('[data-ecod-suggest-hist]') : null;
                if (!hist) return;
                e.preventDefault();
                input.value = hist.getAttribute('data-ecod-suggest-hist') || '';
                input.focus();
                onInput();
            });
            document.addEventListener('click', function (e) {
                if (!form.contains(e.target)) drop.hidden = true;
            });
        });
    };

    /* ---------- 首页轮播 ---------- */
    Ecod.initSlider = function () {
        document.querySelectorAll('[data-ecod-slider]').forEach(function (slider) {
            var slides = slider.querySelectorAll('.ecod_slider__slide');
            var dots = slider.querySelectorAll('[data-ecod-slider-dots] button');
            var glowWrap = slider.parentElement && slider.parentElement.classList.contains('ecod_sliderwrap')
                ? slider.parentElement
                : null;

            /* 取当前封面做外发光背景，切换时同步 */
            function syncGlow(slide) {
                if (!glowWrap || !slide) return;
                var bg = slide.style.backgroundImage;
                if (bg) glowWrap.style.setProperty('--ecod-slider-bg', bg);
            }

            if (slides.length < 2) {
                syncGlow(slides[0]);
                return;
            }

            var INTERVAL = 6000;
            var current = 0;
            var userPaused = false;
            var hovering = false;
            var lastTick = Date.now();
            var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            /* 换页重跑时先停旧心跳，避免叠加 */
            if (slider._ecodTimer) { clearInterval(slider._ecodTimer); slider._ecodTimer = null; }

            function show(i) {
                current = (i + slides.length) % slides.length;
                slides.forEach(function (s, k) { s.classList.toggle('is-active', k === current); });
                dots.forEach(function (d, k) { d.classList.toggle('is-active', k === current); });
                syncGlow(slides[current]);
            }

            function arm() { lastTick = Date.now(); }

            /* 1 秒心跳 + 时间戳对齐，替代单个 6 秒 interval。
               浏览器会对后台标签 / 被遮挡窗口深度节流 timer（最长每分钟一次），
               旧实现的单个 interval 一旦被节流或清除就永久停摆；
               心跳模式在恢复可见后 1 秒内自动续播，暂停 / 悬停 / 减弱动态仍然生效。 */
            slider._ecodTimer = setInterval(function () {
                /* PJAX 换页后游离节点自清理 */
                if (!slider.isConnected) { clearInterval(slider._ecodTimer); slider._ecodTimer = null; return; }
                if (userPaused || reduce || hovering || document.hidden) { arm(); return; }
                if (Date.now() - lastTick >= INTERVAL) { arm(); show(current + 1); }
            }, 1000);

            /* 自动播放必须有暂停入口；reduced-motion 下默认不自动播 */
            var oldPause = slider.querySelector('.ecod_slider__pause');
            if (oldPause) oldPause.remove(); /* PJAX 换页会重跑本函数，避免按钮叠加 */
            var pauseBtn = document.createElement('button');
            pauseBtn.type = 'button';
            pauseBtn.className = 'ecod_slider__pause';
            pauseBtn.setAttribute('aria-label', '暂停自动轮播');
            slider.appendChild(pauseBtn);

            function syncPause() {
                var paused = userPaused || reduce;
                pauseBtn.setAttribute('aria-pressed', paused ? 'true' : 'false');
                pauseBtn.setAttribute('aria-label', paused ? '播放自动轮播' : '暂停自动轮播');
                pauseBtn.innerHTML = Ecod.icon(paused ? 'play' : 'pause', 13);
            }

            pauseBtn.addEventListener('click', function () {
                userPaused = !userPaused;
                arm();
                syncPause();
            });
            syncPause();

            dots.forEach(function (dot) {
                dot.addEventListener('click', function () {
                    show(parseInt(dot.dataset.index, 10) || 0);
                    arm();
                });
            });

            slider.addEventListener('mouseenter', function () { hovering = true; });
            slider.addEventListener('mouseleave', function () { hovering = false; arm(); });

            /* 从后台回到前台时对齐计时，避免节流期累积造成瞬间连切 */
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) arm();
            });

            show(0);
            arm();
        });
    };

    /* ---------- 文章流局部切换（tab / 分页，换整个主内容区，与整页访问一致） ---------- */
    Ecod.initStream = function () {
        var stream = document.getElementById('ecod-stream');
        if (!stream || stream.dataset.ecodStream === '1') return;
        stream.dataset.ecodStream = '1';

        var busy = false;

        function load(url, push) {
            if (busy) return;
            busy = true;
            stream.classList.add('is-loading');
            var keepY = window.pageYOffset;

            fetch(url, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'Ecod-Ajax' }
            }).then(function (res) {
                if (!res.ok || res.redirected) throw new Error(res.status || 'redirect');
                return res.text();
            }).then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                /* DOMParser 关闭脚本时 noscript 会被解析成真实节点，插回页面后
                   兜底原图会被全部下载——必须先剔除，懒加载才不失效 */
                Array.prototype.forEach.call(doc.querySelectorAll('noscript'), function (n) {
                    n.remove();
                });
                /* 换多少由两侧骨架决定（分类 tab 只是筛选文章流，不能连页面骨架一起换走）：
                   ① 首页 → 分类：只换 #ecod-stream，首页横幅与流后面的块保持原位；
                   ② 分类 → 分类：页头跟着换（标题/统计要变）+ 只换文章流；
                   ③ 分类 → 首页：换整个主区，首页横幅与流后面的块才回得来 */
                var nextStream = doc.getElementById('ecod-stream');
                var curStream = document.getElementById('ecod-stream');
                if (!nextStream || !curStream) {
                    location.href = url;
                    return;
                }
                var nextHead = doc.querySelector('.ecod_chead');
                var curHead = document.querySelector('.ecod_chead');

                if (curHead && !nextHead) {
                    var nextMain = doc.getElementById('ecod-main');
                    var curMain = document.getElementById('ecod-main');
                    if (!nextMain || !curMain) {
                        location.href = url;
                        return;
                    }
                    curMain.replaceWith(nextMain);
                } else {
                    if (curHead && nextHead) {
                        curHead.replaceWith(nextHead);
                    }
                    curStream.replaceWith(nextStream);
                }
                if (doc.title) document.title = doc.title;
                if (push) history.pushState({ ecodStream: 1 }, '', url);

                Ecod.refresh();
                live('已加载：' + (document.title || ''));
                /* 保持切换前的滚动位置（不重置、不滚到列表顶部）；页面变短时浏览器会自动夹取 */
                if (window.pageYOffset !== keepY) window.scrollTo(0, keepY);
            }).catch(function () {
                location.href = url;
            }).then(function () {
                busy = false;
                var s = document.getElementById('ecod-stream');
                if (s) s.classList.remove('is-loading');
            });
        }

        stream.addEventListener('click', function (e) {
            if (e.defaultPrevented) return;
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
            var a = e.target.closest ? e.target.closest('a[href]') : null;
            if (!a) return;
            if (a.target && a.target !== '_self') return;
            if (!a.closest('.ecod_tabs') && !a.closest('.ecod_pager')) return;

            var url = new URL(a.href, location.href);
            if (url.origin !== location.origin) return;
            if (/\/(admin|action|install|api\.php)/.test(url.pathname)) return;

            e.preventDefault();
            if (url.href === location.href) return;
            load(url.href, true);
        });
    };

    /* ---------- 无刷新跳转（PJAX）+ 悬停预取 ---------- */
    Ecod.initPjax = function () {
        if (!CFG.pjax) return;

        var bar = document.querySelector('.ecod_pjaxbar');
        if (!bar) {
            bar = document.createElement('div');
            bar.className = 'ecod_pjaxbar';
            document.body.appendChild(bar);
        }

        var busy = false;
        var seq = 0;           /* 加载代数：新导航接管后，旧加载的收尾不再生效 */
        var controller = null; /* 进行中的导航请求，新导航可中断它 */

        /* 自己管理前进/后退的滚动位置（浏览器自动恢复在内容异步替换下不可靠） */
        if ('scrollRestoration' in history) {
            try { history.scrollRestoration = 'manual'; } catch (e) {}
        }
        var scrollMem = {};
        /* 记录「正在浏览」的地址：popstate 触发时 URL 已变成目标页，
           离开前位置必须记到旧地址名下，不能直接用 location.href。
           全局 scroll-behavior: smooth 下瞬时读值会是动画中间值，
           所以改为滚动过程中持续记录，load 前的 memScroll 只兜底。 */
        var lastHref = location.href.split('#')[0];
        function memScroll() {
            scrollMem[lastHref] = window.pageYOffset;
            var keys = Object.keys(scrollMem);
            if (keys.length > 40) delete scrollMem[keys[0]];
        }
        window.addEventListener('scroll', memScroll, { passive: true });
        function popScroll(url) {
            return scrollMem[String(url).split('#')[0]];
        }
        function jumpTo(y) {
            try {
                window.scrollTo({ top: y, left: 0, behavior: 'instant' });
            } catch (e) {
                window.scrollTo(0, y);
            }
        }

        /* 预取缓存：href -> { p: fetch Promise, t: 时间 }，最多留 12 条，2 分钟过期 */
        var cache = new Map();
        var CACHE_TTL = 120000;
        var prefetching = 0;
        var PREFETCH_MAX = 2;
        var hoverTimer = null;

        /* 这个链接是否走 PJAX（点击与悬停共用同一套判断） */
        function eligible(url, a) {
            if (a && (a.hasAttribute('download') || a.dataset.noPjax !== undefined)) return false;
            if (a && a.target && a.target !== '_self') return false;
            if (url.origin !== location.origin) return false;
            if (/\/(admin|action|install)(\/|$)/.test(url.pathname)) return false;
            if (/\/(api\.php|go\/)/.test(url.pathname)) return false;
            if (/\.(zip|rar|pdf|png|jpe?g|gif|webp|svg|mp4|mp3|xml|txt)$/i.test(url.pathname)) return false;
            return true;
        }

        function doFetch(url, isPrefetch, signal) {
            return fetch(url, {
                credentials: 'same-origin',
                signal: signal,
                headers: {
                    'X-Requested-With': 'Ecod-PJAX',
                    'X-Ecod-Prefetch': isPrefetch ? '1' : '0'
                }
            });
        }

        function cacheGet(url) {
            var e = cache.get(url);
            if (e && Date.now() - e.t > CACHE_TTL) {
                cache.delete(url);
                return null;
            }
            return e ? e.p : null;
        }

        function prefetch(url) {
            if (busy || prefetching >= PREFETCH_MAX) return;
            if (cache.has(url)) return;

            prefetching++;
            var p = doFetch(url, true).catch(function () { return null; });
            cache.set(url, { p: p, t: Date.now() });

            p.then(function (res) {
                prefetching--;
                /* 失败的请求从缓存里踢掉，下次点击重新走正常加载 */
                if (!res || !res.ok) {
                    cache.delete(url);
                }
            });

            if (cache.size > 12) {
                cache.delete(cache.keys().next().value);
            }
        }

        function load(url, push) {
            memScroll(); /* 记录离开前位置，供前进/后退恢复 */

            /* 新导航直接接管：中断进行中的请求，旧加载的收尾通过代数校验失效 */
            var my = ++seq;
            if (controller) {
                try { controller.abort(); } catch (e) {}
            }
            controller = 'AbortController' in window ? new AbortController() : null;

            busy = true;
            bar.classList.add('is-loading');
            if (Ecod.drawerClose) Ecod.drawerClose();

            /* 服务端 302（例如「随机文章」页）时不能做无刷新替换：
               fetch 会自动跟随跳转，拿回来的是目标文章，地址栏却还是随机页 */
            var jumped = false;

            var cached = cacheGet(url);
            cache.delete(url);

            var request = cached || doFetch(url, false, controller ? controller.signal : undefined);

            request.then(function (res) {
                if (my !== seq) return null;
                if (!res) throw new Error('prefetch failed');
                if (!res.ok) throw new Error(res.status);
                if (res.redirected) {
                    jumped = true;
                    location.href = res.url;
                    throw new Error('redirect');
                }
                return res.text();
            }).then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                /* 同 initStream：剔除 noscript，防止兜底原图被全量下载 */
                Array.prototype.forEach.call(doc.querySelectorAll('noscript'), function (n) {
                    n.remove();
                });
                var next = doc.getElementById('ecod-shell');
                var cur = document.getElementById('ecod-shell');
                if (!next || !cur) {
                    location.href = url;
                    return;
                }
                if (doc.title) document.title = doc.title;
                cur.replaceWith(next);

                /* 本页已成为「当前页」，后续离开时位置记到它名下 */
                lastHref = url.split('#')[0];

                /* 导航栏在 shell 之外，无刷新跳转不会替换它：
                   目标页把高亮地址放在 shell 的 data-ecod-nav-active 上，据此同步 */
                syncNav(next);

                if (push) history.pushState({ ecod: 1 }, '', url);

                Ecod.refresh();

                /* 无刷新换页对读屏不可见，播报新页面标题 */
                live('已加载：' + (document.title || ''));

                /* 滚动目标：hash 锚点优先；前进/后退恢复离开时位置；否则回顶部 */
                var main = document.getElementById('ecod-main');
                if (main) {
                    main.setAttribute('tabindex', '-1');
                    try { main.focus({ preventScroll: true }); } catch (e) {}
                }
                var anchor = null;
                var hash = url.split('#')[1];
                if (hash) {
                    try { anchor = document.getElementById(decodeURIComponent(hash)); } catch (e) { anchor = null; }
                }
                if (anchor) {
                    anchor.scrollIntoView();
                } else if (!push) {
                    var y = popScroll(url);
                    jumpTo(typeof y === 'number' ? y : 0);
                } else {
                    jumpTo(0);
                }
            }).catch(function (err) {
                if (my !== seq || (err && err.name === 'AbortError')) return;
                if (!jumped) location.href = url;
            }).then(function () {
                if (my !== seq) return;
                busy = false;
                controller = null;
                bar.classList.remove('is-loading');
            });
        }

        /* 高亮地址 → 导航 li 的 is-active（「更多」下拉里的链接不参与） */
        function syncNav(shell) {
            var nav = document.querySelector('.ecod_nav__links');
            if (!nav) return;
            var target = shell ? (shell.getAttribute('data-ecod-nav-active') || '') : '';
            var path = '';
            if (target) {
                try {
                    var u = new URL(target, location.href);
                    path = u.pathname + u.search;
                } catch (e) {
                    return;
                }
            }
            Array.prototype.forEach.call(nav.querySelectorAll('li > a'), function (a) {
                var li = a.parentNode;
                if (li.closest && li.closest('.ecod_nav__drop')) return;
                var on = false;
                if (path) {
                    try {
                        var au = new URL(a.getAttribute('href'), location.href);
                        on = au.pathname + au.search === path;
                    } catch (e) {}
                }
                li.classList.toggle('is-active', on);
            });

            /* 命中二级项时父项同时高亮 */
            Array.prototype.forEach.call(nav.querySelectorAll('li.has-sub'), function (li) {
                var hit = li.querySelector('.ecod_nav__sub li.is-active');
                li.classList.toggle('is-active', !!hit);
            });
        }

        document.addEventListener('click', function (e) {
            if (e.defaultPrevented) return;
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
            var a = e.target.closest ? e.target.closest('a[href]') : null;
            if (!a) return;

            var href = a.getAttribute('href');
            if (!href || href.charAt(0) === '#') return;

            var url = new URL(a.href, location.href);
            if (!eligible(url, a)) return;
            if (url.pathname === location.pathname && url.search === location.search) return;

            e.preventDefault();
            load(url.href, true);
        });

        /* 悬停 90ms 后预取，点下时多半已经拿到整页 */
        document.addEventListener('mouseover', function (e) {
            var a = e.target.closest ? e.target.closest('a[href]') : null;
            if (!a) return;

            var href = a.getAttribute('href');
            if (!href || href.charAt(0) === '#') return;

            var url = new URL(a.href, location.href);
            if (!eligible(url, a)) return;
            if (url.href === location.href) return;
            if (url.pathname === location.pathname && url.search === location.search) return;

            clearTimeout(hoverTimer);
            hoverTimer = setTimeout(function () {
                prefetch(url.href);
            }, 90);
        });

        document.addEventListener('mouseout', function () {
            clearTimeout(hoverTimer);
        });

        window.addEventListener('popstate', function () {
            load(location.href, false);
        });
    };

    /* ---------- 图片懒加载：data-src 进视口才请求 + 占位微光/blur-up 动效 ---------- */
    Ecod.initImages = function () {
        /* ① data-src 真懒加载：距视口 400px 内才把真实地址换回 src */
        var lazy = document.querySelectorAll('img[data-src]:not([data-ecod-ready])');

        Array.prototype.forEach.call(lazy, function (img) {
            img.setAttribute('data-ecod-ready', '1');
        });

        if (lazy.length) {
            if ('IntersectionObserver' in window) {
                var io = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (!entry.isIntersecting) return;
                        var img = entry.target;
                        io.unobserve(img);

                        /* 换源后到加载完成前保持骨架块，避免塌陷闪烁 */
                        img.classList.add('ecod-img-loading');

                        img.addEventListener('load', function () {
                            img.classList.remove('ecod-img-loading');
                            img.classList.add('is-loaded', 'ecod-img-anim');
                        }, { once: true });
                        img.addEventListener('error', function () {
                            img.classList.remove('ecod-img-loading');
                            img.classList.add('is-error');
                        }, { once: true });

                        img.src = img.getAttribute('data-src');
                        img.removeAttribute('data-src');
                    });
                }, { rootMargin: '400px 0px' });

                Array.prototype.forEach.call(lazy, function (img) {
                    io.observe(img);
                });
            } else {
                /* 老浏览器兜底：直接全部加载 */
                Array.prototype.forEach.call(lazy, function (img) {
                    img.src = img.getAttribute('data-src');
                    img.removeAttribute('data-src');
                    img.classList.add('is-loaded');
                });
            }
        }

        /* ② 原生 lazy 的图（卡片缩略图等，带宽高）：到位后 blur-up */
        var imgs = document.querySelectorAll('img[data-ecod-img]:not([data-ecod-ready])');
        Array.prototype.forEach.call(imgs, function (img) {
            img.setAttribute('data-ecod-ready', '1');

            if (img.complete && img.naturalWidth > 0) {
                img.classList.add('is-loaded');
                return;
            }

            img.addEventListener('load', function () {
                img.classList.add('is-loaded', 'ecod-img-anim');
            }, { once: true });

            img.addEventListener('error', function () {
                img.classList.add('is-error');
            }, { once: true });
        });
    };

    /* ---------- 列表入场动效 ---------- */
    Ecod.initAnimate = function () {
        var mode = document.documentElement.getAttribute('data-anim');
        if (!mode || mode === 'none' || !('IntersectionObserver' in window)) {
            return;
        }

        var list = document.querySelectorAll('.ecod_cards > *, .ecod_main > section, .ecod_aside > *');
        if (!list.length) {
            return;
        }

        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-in');
                    io.unobserve(entry.target);
                }
            });
        }, { rootMargin: '0px 0px -6% 0px', threshold: 0.04 });

        Array.prototype.forEach.call(list, function (el, index) {
            if (el.classList.contains('is-in') || el.hasAttribute('data-ecod-noanim')) {
                return;
            }
            el.classList.add('ecod-reveal');
            el.style.transitionDelay = Math.min(index, 6) * 45 + 'ms';
            io.observe(el);
        });
    };

    /* ---------- 鼠标跟随光点 ---------- */
    Ecod.initCursor = function () {
        if (document.documentElement.getAttribute('data-cursor') !== 'dot') {
            return;
        }
        if (window.matchMedia && !window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
            return;
        }

        var dot = document.querySelector('.ecod_cursor-dot');
        if (!dot) {
            dot = document.createElement('div');
            dot.className = 'ecod_cursor-dot';
            dot.setAttribute('aria-hidden', 'true');
            document.body.appendChild(dot);
        }
        if (dot.getAttribute('data-bound')) {
            return;
        }
        dot.setAttribute('data-bound', '1');

        var tx = 0, ty = 0, x = 0, y = 0, started = false;

        document.addEventListener('mousemove', function (e) {
            tx = e.clientX;
            ty = e.clientY;
            if (!started) {
                started = true;
                x = tx;
                y = ty;
                loop();
            }
        }, { passive: true });

        document.addEventListener('mousedown', function () {
            dot.classList.add('is-pressed');
        });
        document.addEventListener('mouseup', function () {
            dot.classList.remove('is-pressed');
        });

        function loop() {
            x += (tx - x) * 0.18;
            y += (ty - y) * 0.18;
            dot.style.transform = 'translate3d(' + x + 'px,' + y + 'px,0)';
            window.requestAnimationFrame(loop);
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

    /* ---------- 导航溢出自动收纳「更多」 ----------
     * 后台 ENavMax 只是手动上限；这里是兜底：视口放不下时把尾部条目
     * 收进「更多」下拉，窗口变宽再自动还原。 */
    Ecod.initNavOverflow = function () {
        var nav = document.querySelector('.ecod_nav');
        if (!nav) return;
        var links = nav.querySelector('.ecod_nav__links');
        var search = nav.querySelector('.ecod_nav__search');
        if (!links || !search) return;

        var CHEVRON = '<svg class="ecod_icon" width="12" height="12" viewBox="0 0 24 24" fill="none"'
            + ' stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            + '<path d="M9 18l6-6-6-6"/></svg>';
        var MIN_HEAD = 3; /* 至少保住：首页 + 2 个链接 */

        /* 摊平成有序池：平铺项在前、已收纳项在后，保证多次执行顺序稳定 */
        /* 以整个 li 为单位搬运，二级下拉（li > ul.ecod_nav__sub）随之保留；
           「更多」里服务端渲染的 <a>（可能带 .ecod_nav__dropkids）包成 li 带走 */
        function pool() {
            var out = [];
            Array.prototype.forEach.call(links.children, function (li) {
                if (li.classList.contains('ecod_nav__more')) {
                    var drop = li.querySelector('.ecod_nav__drop');
                    if (!drop) return;
                    Array.prototype.forEach.call(drop.children, function (node) {
                        if (node.nodeType !== 1) return;
                        if (node.tagName === 'LI') {
                            out.push({ li: node });
                        } else if (node.tagName === 'A') {
                            var wrap = document.createElement('li');
                            wrap.appendChild(node);
                            /* 服务端渲染的二级块（.ecod_nav__dropkids）跟着所属链接走 */
                            while (node.nextSibling && node.nextSibling.nodeType === 1
                                && node.nextSibling.classList.contains('ecod_nav__dropkids')) {
                                wrap.appendChild(node.nextSibling);
                            }
                            out.push({ li: wrap });
                        }
                    });
                } else {
                    out.push({ li: li });
                }
            });
            return out;
        }

        function rebuild(items) {
            links.textContent = '';
            items.forEach(function (it) {
                links.appendChild(it.li);
            });
        }

        function fits() {
            return links.scrollWidth <= links.clientWidth + 1;
        }

        function flatLis() {
            return Array.prototype.filter.call(links.children, function (li) {
                return !li.classList.contains('ecod_nav__more');
            });
        }

        function collapse() {
            var more = document.createElement('li');
            more.className = 'ecod_nav__more';
            more.innerHTML = '<details><summary>更多' + CHEVRON + '</summary><div class="ecod_nav__drop"></div></details>';
            var drop = more.querySelector('.ecod_nav__drop');
            links.appendChild(more);

            /* 放不下就一直从尾部收，直到够放或到达保底数量；当前页不收进「更多」 */
            while (!fits()) {
                var lis = flatLis();
                if (lis.length <= MIN_HEAD) break;
                var last = lis[lis.length - 1];
                if (last.classList.contains('is-active')) break;
                drop.insertBefore(last, drop.firstChild);
            }

            /* 还有富余就逐个还回去，放不下再退回下拉 */
            while (drop.children.length) {
                var li = drop.children[0];
                links.insertBefore(li, more);
                if (!fits()) {
                    drop.insertBefore(li, drop.firstChild);
                    break;
                }
            }

            /* 下拉空了就整体撤掉 */
            if (!drop.children.length) {
                links.removeChild(more);
            }
        }

        var items = pool();
        rebuild(items);
        if (!fits()) {
            collapse();
        }

        /* 视口变化重算；字体加载完成后尺寸会变，load 后再跑一次 */
        if (!nav.dataset.ecodNavResize) {
            nav.dataset.ecodNavResize = '1';
            var timer = 0;
            window.addEventListener('resize', function () {
                clearTimeout(timer);
                timer = setTimeout(Ecod.initNavOverflow, 160);
            });
            window.addEventListener('load', Ecod.initNavOverflow);
        }
    };

    /* ---------- 账户：前台登录 / 个人资料 ---------- */
    function accMsg(name, text, ok) {
        var el = document.querySelector('[data-ecod-account-msg="' + name + '"]');
        if (!el) return;
        el.textContent = text;
        el.hidden = false;
        el.classList.toggle('is-ok', !!ok);
        el.classList.toggle('is-bad', !ok);
    }

    function accPost(action, body) {
        return fetch((CFG.api || '') + '?action=' + action, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        }).then(function (r) {
            return r.json().catch(function () { return { ok: false, error: '响应异常' }; });
        });
    }

    Ecod.initAccount = function () {
        /* 图形验证码：换图并清空已填内容（一次性验证码，提交后必须换） */
        function capRefresh(scope) {
            var box = scope || document;
            if (!CFG.api) return;
            box.querySelectorAll('[data-ecod-captcha]').forEach(function (img) {
                img.src = CFG.api + '?action=captcha&t=' + Date.now();
            });
            box.querySelectorAll('input[name="captcha"]').forEach(function (input) { input.value = ''; });
        }

        if (!document.documentElement.dataset.ecodCapBound) {
            document.documentElement.dataset.ecodCapBound = '1';
            document.addEventListener('click', function (e) {
                var img = e.target.closest('[data-ecod-captcha]');
                if (!img) return;
                e.preventDefault();
                capRefresh(img.closest('form'));
            });
        }

        /* 登录表单（登录页 + 全站登录弹窗，逐个绑定） */
        var loginForm = document.querySelector('[data-ecod-login]');
        document.querySelectorAll('[data-ecod-login]').forEach(function (lf) {
            if (lf.dataset.ecodBound) { return; }
            lf.dataset.ecodBound = '1';
            lf.addEventListener('submit', function (e) {
                e.preventDefault();
                var msg = lf.querySelector('[data-ecod-login-msg]');
                var btn = lf.querySelector('[type="submit"]');
                if (msg) { msg.hidden = true; }
                if (btn) { btn.disabled = true; btn.textContent = '登录中…'; }
                var body = new FormData(lf);
                var authCard = document.querySelector('[data-ecod-auth]');
                var refInput = authCard ? authCard.querySelector('[data-ecod-auth-referer]') : null;
                if (refInput && refInput.value) { body.append('referer', refInput.value); }
                accPost('login', body).then(function (data) {
                    if (data && data.ok) {
                        location.replace(data.redirect || lf.dataset.ecodHome || '/');
                        return;
                    }
                    if (msg) {
                        msg.textContent = (data && data.error) || '登录失败';
                        msg.hidden = false;
                        msg.classList.add('is-bad');
                    }
                    capRefresh(lf);
                    if (btn) { btn.disabled = false; btn.textContent = '登 录'; }
                }).catch(function () {
                    if (msg) { msg.textContent = '网络异常，请重试'; msg.hidden = false; msg.classList.add('is-bad'); }
                    capRefresh(lf);
                    if (btn) { btn.disabled = false; btn.textContent = '登 录'; }
                });
            });
        });

        /* 全站登录弹窗：事件委托，PJAX 换页后入口链接被替换也能继续用 */
        var authModal = document.querySelector('[data-ecod-authmodal]');
        if (authModal && !authModal.dataset.ecodBound) {
            authModal.dataset.ecodBound = '1';
            function authModalClose() {
                authModal.hidden = true;
                document.documentElement.classList.remove('ecod-modal-lock');
            }
            document.addEventListener('click', function (e) {
                var opener = e.target.closest('[data-ecod-login-open]');
                if (opener && authModal) {
                    e.preventDefault();
                    authModal.hidden = false;
                    document.documentElement.classList.add('ecod-modal-lock');
                    /* 打开即换一张验证码，避免用的是上一次的过期图 */
                    var modalForm = authModal.querySelector('[data-ecod-login]');
                    if (modalForm) { capRefresh(modalForm); }
                    var first = authModal.querySelector('[name="name"]');
                    if (first) { try { first.focus({ preventScroll: true }); } catch (err) {} }
                    return;
                }
                if (!authModal.hidden && e.target.closest('[data-ecod-authmodal-close]')) {
                    authModalClose();
                }
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !authModal.hidden) { authModalClose(); }
            });
        }

        /* 登录页三合一：登录 / 注册 / 找回密码 */
        var auth = document.querySelector('[data-ecod-auth]');
        if (auth && !auth.dataset.ecodAuthBound) {
            auth.dataset.ecodAuthBound = '1';
            var authToken = auth.getAttribute('data-ecod-token') || '';
            var authHome = (loginForm && loginForm.dataset.ecodHome) || '/';
            var refEl = auth.querySelector('[data-ecod-auth-referer]');

            function authBody(form) {
                var fd = new FormData(form);
                if (authToken) fd.append('token', authToken);
                if (refEl && refEl.value) fd.append('referer', refEl.value);
                return fd;
            }

            function authMsg(name, text, ok) { accMsg(name, text, ok); }

            /* 标签页切换 */
            var authTabs = auth.querySelectorAll('[data-ecod-auth-tab]');
            function authSwitch(key) {
                Array.prototype.forEach.call(authTabs, function (btn) {
                    var on = btn.getAttribute('data-ecod-auth-tab') === key;
                    btn.classList.toggle('is-active', on);
                    btn.setAttribute('aria-selected', on ? 'true' : 'false');
                    btn.setAttribute('tabindex', on ? '0' : '-1');
                });
                auth.querySelectorAll('[data-ecod-auth-panel]').forEach(function (panel) {
                    panel.hidden = panel.getAttribute('data-ecod-auth-panel') !== key;
                });
            }
            Array.prototype.forEach.call(authTabs, function (btn, i) {
                btn.addEventListener('click', function () {
                    authSwitch(btn.getAttribute('data-ecod-auth-tab'));
                });
                /* tabs 模式：左右箭头切换并移动焦点 */
                btn.addEventListener('keydown', function (e) {
                    var next = -1;
                    if (e.key === 'ArrowRight' || e.key === 'ArrowDown') next = (i + 1) % authTabs.length;
                    else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') next = (i - 1 + authTabs.length) % authTabs.length;
                    else if (e.key === 'Home') next = 0;
                    else if (e.key === 'End') next = authTabs.length - 1;
                    if (next < 0) return;
                    e.preventDefault();
                    authTabs[next].focus();
                    authSwitch(authTabs[next].getAttribute('data-ecod-auth-tab'));
                });
            });
            auth.querySelectorAll('[data-ecod-auth-goto]').forEach(function (link) {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    authSwitch(link.getAttribute('data-ecod-auth-goto'));
                });
            });

            /* 注册 */
            var regForm = auth.querySelector('[data-ecod-auth-form="register"]');
            if (regForm) {
                regForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    if (regForm.querySelector('[name="password"]').value
                        !== regForm.querySelector('[name="confirm"]').value) {
                        authMsg('register', '两次输入的密码不一致', false);
                        return;
                    }
                    var btn = regForm.querySelector('[type="submit"]');
                    if (btn) { btn.disabled = true; btn.textContent = '注册中…'; }
                    accPost('register', authBody(regForm)).then(function (data) {
                        if (data && data.ok) {
                            authMsg('register', '注册成功，正在进入…', true);
                            location.replace(data.redirect || authHome);
                            return;
                        }
                        authMsg('register', (data && data.error) || '注册失败', false);
                        if (btn) { btn.disabled = false; btn.textContent = '注 册'; }
                    }).catch(function () {
                        authMsg('register', '网络异常，请重试', false);
                        if (btn) { btn.disabled = false; btn.textContent = '注 册'; }
                    });
                });
            }

            /* 找回密码 · 第一步：发验证码 */
            var sendForm = auth.querySelector('[data-ecod-auth-form="forgot_send"]');
            var resetForm = auth.querySelector('[data-ecod-auth-form="forgot_reset"]');
            var step2 = auth.querySelector('[data-ecod-forgot-step2]');
            if (sendForm && resetForm) {
                sendForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    var btn = sendForm.querySelector('[type="submit"]');
                    if (btn) { btn.disabled = true; btn.textContent = '发送中…'; }
                    var fd = new FormData(sendForm);
                    fd.append('purpose', 'reset');
                    if (authToken) fd.append('token', authToken);
                    accPost('send_code', fd).then(function (data) {
                        if (data && data.ok) {
                            authMsg('forgot_send', '验证码已发送，请查收邮箱（注意垃圾箱）', true);
                            if (step2) step2.hidden = false;
                            resetForm.querySelector('[name="code"]').focus();
                        } else {
                            authMsg('forgot_send', (data && data.error) || '发送失败', false);
                        }
                        if (btn) { btn.disabled = false; btn.textContent = '发送验证码'; }
                    }).catch(function () {
                        authMsg('forgot_send', '网络异常，请重试', false);
                        if (btn) { btn.disabled = false; btn.textContent = '发送验证码'; }
                    });
                });

                /* 第二步：校验验证码并重置 */
                resetForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    if (resetForm.querySelector('[name="password"]').value
                        !== resetForm.querySelector('[name="confirm"]').value) {
                        authMsg('forgot_reset', '两次输入的密码不一致', false);
                        return;
                    }
                    var btn = resetForm.querySelector('[type="submit"]');
                    if (btn) { btn.disabled = true; btn.textContent = '提交中…'; }
                    accPost('reset_password', authBody(resetForm)).then(function (data) {
                        if (data && data.ok) {
                            authMsg('forgot_reset', '密码已重置', true);
                            if (loginForm) {
                                loginForm.querySelector('[name="name"]').value
                                    = resetForm.querySelector('[name="mail"]').value;
                            }
                            setTimeout(function () { authSwitch('login'); }, 800);
                            if (btn) { btn.disabled = false; btn.textContent = '重置密码'; }
                            return;
                        }
                        authMsg('forgot_reset', (data && data.error) || '重置失败', false);
                        if (btn) { btn.disabled = false; btn.textContent = '重置密码'; }
                    }).catch(function () {
                        authMsg('forgot_reset', '网络异常，请重试', false);
                        if (btn) { btn.disabled = false; btn.textContent = '重置密码'; }
                    });
                });
            }
        }

        /* 个人中心：资料 / 密码 / 头像 */
        var root = document.querySelector('[data-ecod-account]');
        if (root && !root.dataset.ecodBound) {
            root.dataset.ecodBound = '1';
            var token = root.getAttribute('data-ecod-token') || '';

            var profileForm = root.querySelector('[data-ecod-account-form="profile"]');
            if (profileForm && !profileForm.dataset.ecodBound) {
                profileForm.dataset.ecodBound = '1';
                profileForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    var body = new FormData(profileForm);
                    body.append('token', token);
                    var btn = profileForm.querySelector('[type="submit"]');
                    if (btn) btn.disabled = true;
                    accPost('profile_save', body).then(function (data) {
                        if (data && data.ok) {
                            accMsg('profile', '资料已保存，刷新页面…', true);
                            setTimeout(function () { location.reload(); }, 600);
                        } else {
                            accMsg('profile', (data && data.error) || '保存失败', false);
                            if (btn) btn.disabled = false;
                        }
                    }).catch(function () {
                        accMsg('profile', '网络异常，请重试', false);
                        if (btn) btn.disabled = false;
                    });
                });
            }

            var pwdForm = root.querySelector('[data-ecod-account-form="password"]');
            if (pwdForm && !pwdForm.dataset.ecodBound) {
                pwdForm.dataset.ecodBound = '1';
                pwdForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    var nw = pwdForm.querySelector('[name="new"]').value;
                    var cf = pwdForm.querySelector('[name="confirm"]').value;
                    if (nw !== cf) { accMsg('password', '两次输入的新密码不一致', false); return; }
                    var body = new FormData(pwdForm);
                    body.append('token', token);
                    var btn = pwdForm.querySelector('[type="submit"]');
                    if (btn) btn.disabled = true;
                    accPost('password_save', body).then(function (data) {
                        if (data && data.ok) {
                            accMsg('password', '密码已修改', true);
                            pwdForm.reset();
                        } else {
                            accMsg('password', (data && data.error) || '修改失败', false);
                        }
                        if (btn) btn.disabled = false;
                    }).catch(function () {
                        accMsg('password', '网络异常，请重试', false);
                        if (btn) btn.disabled = false;
                    });
                });
            }

            var avatarInput = root.querySelector('[data-ecod-avatar-input]');
            var avatarImg = root.querySelector('[data-ecod-avatar-img]');
            var removeBtn = root.querySelector('[data-ecod-avatar-remove]');

            var avatarUpload = function (file, name) {
                var body = new FormData();
                body.append('avatar', file, name);
                body.append('token', token);
                accMsg('avatar', '上传中…', true);
                accPost('avatar_upload', body).then(function (data) {
                    if (data && data.ok) {
                        accMsg('avatar', '头像已更新，刷新页面…', true);
                        if (avatarImg) avatarImg.src = data.url + '?t=' + Date.now();
                        setTimeout(function () { location.reload(); }, 600);
                    } else {
                        accMsg('avatar', (data && data.error) || '上传失败', false);
                    }
                }).catch(function () {
                    accMsg('avatar', '网络异常，请重试', false);
                });
            };

            /* 头像裁剪弹窗：选图 → 拖动/缩放取景 → canvas 裁成正方形再上传（服务端零改动） */
            var avatarCrop = (function () {
                var el = null, img = null, stage = null, zoomSlider = null;
                var cover = 1;                  /* 图像恰好铺满取景框的基准缩放 */
                var state = { s: 1, tx: 0, ty: 0 };
                var drag = null;
                var onDone = null;

                function ensure() {
                    if (el) return;
                    el = document.createElement('div');
                    el.className = 'ecod_fmodal';
                    el.hidden = true;
                    el.innerHTML = '<div class="ecod_fmodal__mask" data-ecod-crop-close></div>'
                        + '<div class="ecod_fmodal__panel ecod_cmodal__panel" role="dialog" aria-modal="true" aria-label="裁剪头像">'
                        + '<button type="button" class="ecod_fmodal__close" data-ecod-crop-close aria-label="关闭裁剪弹窗">' + Ecod.icon('x', 17) + '</button>'
                        + '<header class="ecod_fmodal__head"><h2>裁剪头像</h2><p>拖动图片调整位置，滚轮或滑杆缩放。</p></header>'
                        + '<div class="ecod_cmodal__stage" data-ecod-crop-stage>'
                        + '<img data-ecod-crop-img alt="裁剪预览" draggable="false">'
                        + '<span class="ecod_cmodal__ring" aria-hidden="true"></span></div>'
                        + '<input class="ecod_cmodal__zoom" data-ecod-crop-zoom type="range" min="1" max="4" step="0.01" value="1" aria-label="缩放">'
                        + '<div class="ecod_cmodal__ops">'
                        + '<button type="button" class="ecod-btn ecod-btn--ghost" data-ecod-crop-close>取消</button>'
                        + '<button type="button" class="ecod-btn" data-ecod-crop-ok>使用这张</button></div></div>';
                    document.body.appendChild(el);
                    img = el.querySelector('[data-ecod-crop-img]');
                    stage = el.querySelector('[data-ecod-crop-stage]');
                    zoomSlider = el.querySelector('[data-ecod-crop-zoom]');
                    bind();
                }

                function clampPos() {
                    var V = stage.clientWidth;
                    var w = img.naturalWidth * cover * state.s;
                    var h = img.naturalHeight * cover * state.s;
                    var limX = Math.max(0, (w - V) / 2);
                    var limY = Math.max(0, (h - V) / 2);
                    state.tx = Math.min(limX, Math.max(-limX, state.tx));
                    state.ty = Math.min(limY, Math.max(-limY, state.ty));
                }

                function apply() {
                    clampPos();
                    img.style.transform = 'translate(-50%, -50%) translate(' + state.tx + 'px, ' + state.ty + 'px) scale(' + (cover * state.s) + ')';
                }

                function setScale(s) {
                    state.s = Math.min(4, Math.max(1, s));
                    zoomSlider.value = String(state.s);
                    apply();
                }

                function close() {
                    el.hidden = true;
                    document.documentElement.classList.remove('ecod-modal-lock');
                }

                function open(file, done) {
                    ensure();
                    onDone = done || null;
                    img.dataset.cropType = file.type || '';
                    var url = URL.createObjectURL(file);
                    img.onload = function () {
                        URL.revokeObjectURL(url);
                        cover = stage.clientWidth / Math.min(img.naturalWidth, img.naturalHeight);
                        state.s = 1; state.tx = 0; state.ty = 0;
                        zoomSlider.value = '1';
                        apply();
                    };
                    img.onerror = close;
                    img.src = url;
                    el.hidden = false;
                    document.documentElement.classList.add('ecod-modal-lock');
                }

                /* 按当前取景把可见区域裁成正方形，短边最大 512px，PNG 保留透明通道 */
                function cropBlob(cb) {
                    var V = stage.clientWidth;
                    var ratio = cover * state.s;
                    var c = V / ratio;
                    var sx = img.naturalWidth / 2 - state.tx / ratio - c / 2;
                    var sy = img.naturalHeight / 2 - state.ty / ratio - c / 2;
                    var out = Math.max(1, Math.min(512, Math.round(c)));
                    var canvas = document.createElement('canvas');
                    canvas.width = out;
                    canvas.height = out;
                    canvas.getContext('2d').drawImage(img, sx, sy, c, c, 0, 0, out, out);
                    canvas.toBlob(cb, img.dataset.cropType === 'image/png' ? 'image/png' : 'image/jpeg', 0.92);
                }

                function bind() {
                    stage.addEventListener('pointerdown', function (e) {
                        if (e.pointerType === 'mouse' && e.button !== 0) return;
                        drag = { x: e.clientX, y: e.clientY, tx: state.tx, ty: state.ty };
                        stage.classList.add('is-dragging');
                        try { stage.setPointerCapture(e.pointerId); } catch (err) {}
                    });
                    stage.addEventListener('pointermove', function (e) {
                        if (!drag) return;
                        state.tx = drag.tx + (e.clientX - drag.x);
                        state.ty = drag.ty + (e.clientY - drag.y);
                        apply();
                    });
                    stage.addEventListener('pointerup', function () { drag = null; stage.classList.remove('is-dragging'); });
                    stage.addEventListener('pointercancel', function () { drag = null; stage.classList.remove('is-dragging'); });
                    stage.addEventListener('wheel', function (e) {
                        e.preventDefault();
                        setScale(state.s * Math.exp(-e.deltaY * 0.0015));
                    }, { passive: false });
                    zoomSlider.addEventListener('input', function () {
                        setScale(parseFloat(zoomSlider.value) || 1);
                    });
                    document.addEventListener('keydown', function (e) {
                        if (e.key === 'Escape' && !el.hidden) close();
                    });
                    el.addEventListener('click', function (e) {
                        if (e.target.closest('[data-ecod-crop-close]')) { close(); return; }
                        if (e.target.closest('[data-ecod-crop-ok]')) {
                            cropBlob(function (blob) {
                                close();
                                if (blob && onDone) onDone(blob);
                            });
                        }
                    });
                }

                return { open: open };
            })();

            if (avatarInput && !avatarInput.dataset.ecodBound) {
                avatarInput.dataset.ecodBound = '1';
                avatarInput.addEventListener('change', function () {
                    var file = avatarInput.files && avatarInput.files[0];
                    avatarInput.value = '';
                    if (!file) return;
                    if (file.size > 4 * 1024 * 1024) { accMsg('avatar', '图片不能超过 4MB', false); return; }
                    /* GIF 裁剪会丢动画，保持原样直传 */
                    if (file.type === 'image/gif') { avatarUpload(file, file.name || 'avatar.gif'); return; }
                    avatarCrop.open(file, function (blob) {
                        avatarUpload(blob, blob.type === 'image/png' ? 'avatar.png' : 'avatar.jpg');
                    });
                });
            }
            if (removeBtn && !removeBtn.dataset.ecodBound) {
                removeBtn.dataset.ecodBound = '1';
                removeBtn.addEventListener('click', function () {
                    var body = new FormData();
                    body.append('token', token);
                    accPost('avatar_remove', body).then(function (data) {
                        if (data && data.ok) {
                            location.reload();
                        } else {
                            accMsg('avatar', (data && data.error) || '操作失败', false);
                        }
                    });
                });
            }
        }
    };

    /* ---------- 首次 / 切换后初始化 ---------- */
    /* ---------- 导航高亮：按当前 URL 算，挂到 <a> 上 ---------- */
    Ecod.initNavActive = function () {
        var links = document.querySelectorAll('.ecod_nav__links a[href]');
        if (!links.length) {
            return;
        }

        /* 去掉 index.php 与结尾斜杠，统一成可比较的路径 */
        function norm(path) {
            var p = path.replace(/\/index\.php\//i, '/').replace(/\/index\.php$/i, '/');
            if (p.length > 1) p = p.replace(/\/+$/, '');
            return p;
        }

        var here = norm(location.pathname);

        Array.prototype.forEach.call(links, function (a) {
            if (a.target && a.target !== '_self') {
                return;
            }
            a.classList.toggle('is-active', norm(a.pathname) === here);
        });
    };

    /* ---------- 加载更多：把分页换成原地追加（无 JS 退回普通分页） ---------- */
    Ecod.initLoadMore = function () {
        var wrap = document.querySelector('[data-ecod-infinite]');
        var pager = document.querySelector('.ecod_pager');
        if (!wrap || !pager || wrap.dataset.loadMoreReady === '1') {
            return;
        }

        var next = null;
        Array.prototype.forEach.call(pager.querySelectorAll('a'), function (a) {
            if (String(a.textContent).trim() === '›') {
                next = a;
            }
        });
        if (!next) {
            return; /* 只有一页 */
        }

        wrap.dataset.loadMoreReady = '1';
        pager.style.display = 'none';

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ecod_loadmore';
        btn.textContent = '加载更多';
        btn.setAttribute('aria-label', '加载下一页文章');
        pager.parentNode.insertBefore(btn, pager.nextSibling);

        btn.addEventListener('click', function () {
            if (btn.disabled || !next) {
                return;
            }
            btn.disabled = true;
            btn.textContent = '加载中…';

            fetch(next.href, { credentials: 'same-origin' })
                .then(function (res) { return res.text(); })
                .then(function (html) {
                    var prevY = window.pageYOffset;
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var newWrap = doc.querySelector('[data-ecod-infinite]');
                    var newPager = doc.querySelector('.ecod_pager');

                    if (newWrap) {
                        Array.prototype.forEach.call(newWrap.children, function (card) {
                            wrap.appendChild(document.adoptNode(card));
                        });
                        /* 浏览器滚动锚定会把视口钉在按钮上，追加后还原滚动位置，
                           让新卡片停在当前视口下方而不是跑到上面去 */
                        window.scrollTo(0, prevY);
                    }

                    /* 新页的「下一页」存在则继续，否则到底收摊 */
                    var newNext = null;
                    if (newPager) {
                        Array.prototype.forEach.call(newPager.querySelectorAll('a'), function (a) {
                            if (String(a.textContent).trim() === '›') {
                                newNext = a;
                            }
                        });
                    }
                    if (newNext && newWrap) {
                        next.href = newNext.href;
                        btn.disabled = false;
                        btn.textContent = '加载更多';
                    } else {
                        next = null;
                        btn.textContent = '已经到底了';
                        btn.classList.add('is-end');
                    }

                    /* 追加的卡片重新走一遍增强（懒加载 / 点赞态 / 入场动效） */
                    Ecod.initImages();
                    Ecod.initCardAct();
                    Ecod.initLikeState();
                    Ecod.initAnimate();
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.textContent = '加载失败，点击重试';
                });
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

    /* ---------- 列表排序：浮层展开 / 收起 ----------
     * 选项本身是链接，关掉 JS 也能跳转；这里只做展开与关闭。
     * 外部点击与 Esc 用一次注册的委托监听，PJAX 换页不会重复绑定。 */
    Ecod.initSort = function () {
        var boxes = document.querySelectorAll('[data-ecod-sort]');
        Array.prototype.forEach.call(boxes, function (box) {
            if (box._ecodSort) return;
            box._ecodSort = 1;

            var btn = box.querySelector('[data-ecod-sort-btn]');
            if (!btn) return;

            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var open = !box.classList.contains('is-open');
                box.classList.toggle('is-open', open);
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        });

        if (Ecod._sortDoc) return;
        Ecod._sortDoc = 1;

        function closeAll(except) {
            Array.prototype.forEach.call(document.querySelectorAll('[data-ecod-sort].is-open'), function (box) {
                if (box === except) return;
                box.classList.remove('is-open');
                var b = box.querySelector('[data-ecod-sort-btn]');
                if (b) b.setAttribute('aria-expanded', 'false');
            });
        }

        document.addEventListener('click', function (e) {
            closeAll(e.target.closest ? e.target.closest('[data-ecod-sort]') : null);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            var open = document.querySelector('[data-ecod-sort].is-open');
            if (!open) return;
            closeAll(null);
            var b = open.querySelector('[data-ecod-sort-btn]');
            if (b) b.focus();
        });
    };

    Ecod.refresh = function () {
        /* 换页时先停掉上一页的朗读，避免声音延续到新页面 */
        Ecod.stopTts();
        Ecod.initNavOverflow();
        Ecod.initNavActive();
        Ecod.initHistory();
        Ecod.renderUserCenter();
        Ecod.resetScroll();
        Ecod.initHeader();
        Ecod.initToTop();
        Ecod.initProgress();
        Ecod.initToc();
        Ecod.initContent();
        Ecod.initEmbeds();
        Ecod.initImages();
        Ecod.initInline();
        Ecod.initCashier();
        if (CFG.lightbox !== false) {
            Ecod.initLightbox();
        }
        Ecod.initPostAct();
        Ecod.initQuickBar();
        Ecod.initShareCard();
        Ecod.initTts();
        Ecod.initComment();
        Ecod.initCommentFold();
        Ecod.initTurnstile();
        Ecod.initThirdComments();
        Ecod.initEmoji();
        Ecod.initTabs();
        Ecod.initCatbar();
        Ecod.initCatTools();
        Ecod.initSort();
        Ecod.initRank();
        Ecod.initExtras();
        Ecod.initDock();
        Ecod.initNotice();
        Ecod.initAccount();
        Ecod.initFriendApply();
        Ecod.initSearchSuggest();
        Ecod.initSearchPanel();
        Ecod.initSlider();
        Ecod.initCardAct();
        Ecod.initLikeState();
        Ecod.initStream();
        Ecod.initLoadMore();
        Ecod.initAi();
        Ecod.initAnimate();
        Ecod.initCursor();
        Ecod.initPrism();
        syncFavButtons();
        Ecod.renderFavs();
    };

    document.addEventListener('DOMContentLoaded', function () {
        Ecod.initTheme();
        Ecod.initDrawer();
        Ecod.initHotkey();
        Ecod.initUserPop();
        Ecod.initActions();
        Ecod.initCtxMenu();
        Ecod.initReply();
        Ecod.initPjax();
        Ecod.refresh();
    });
})();
