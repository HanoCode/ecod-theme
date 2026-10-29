/**
 * Ecod Theme · 选图器
 *
 * 后台统一入口：媒体库（读 Typecho 附件表）+ 上传 + 直接填 URL。
 * 上传复用 Typecho 自带 /action/upload，列表走主题自带的 media.php。
 *
 * 用法：
 *   EcodMedia.pick({ multiple:false, value:'', onPick:function(urls){...} });
 *
 * @package ecod-theme
 */
(function () {
    'use strict';

    var CFG = window.ECOD_MEDIA || {};
    var CURRENT = document.currentScript || (function () {
        var all = document.getElementsByTagName('script');
        return all[all.length - 1];
    })();

    var PAGE_SIZE = 48;

    var ICON = {
        search: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>',
        close: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>',
        check: '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>',
        image: '<svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="M21 16l-4.5-4.5L6 20"/></svg>',
        upload: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4"/><path d="M7 9l5-5 5 5"/><path d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>',
        zoom: '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/><path d="M11 8v6M8 11h6"/></svg>',
        prev: '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg>',
        next: '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"/></svg>'
    };

    /* ------------------------------------------------------------------ *
     * 配置
     * ------------------------------------------------------------------ */

    function apiUrl() {
        var url = CFG.api;
        if (!url) {
            var src = CURRENT && CURRENT.src ? String(CURRENT.src) : '';
            if (/media\.js(\?.*)?$/.test(src)) {
                url = src.replace(/media\.js(\?.*)?$/, 'media.php');
            } else {
                url = 'media.php';
            }
        }

        /* 强制同源：后台一定和主题文件在同一台服务器上。
         * 若 Typecho 配置的 siteUrl 与实际访问域名不一致（带/不带 www、IP 访问），
         * 绝对地址会变成跨域请求，登录 Cookie 不会随请求携带，导致永远 401。 */
        try {
            var a = document.createElement('a');
            a.href = url;
            return a.pathname + (a.search || '');
        } catch (err) {
            return url;
        }
    }

    function uploadUrl() {
        var url = CFG.upload || '/action/upload';
        try {
            var a = document.createElement('a');
            a.href = url;
            return a.pathname + (a.search || '');
        } catch (err) {
            return url;
        }
    }

    function esc(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
        });
    }

    /* ------------------------------------------------------------------ *
     * 状态
     * ------------------------------------------------------------------ */

    var ui = null;
    var state = {
        open: false,
        multiple: false,
        selected: [],
        items: [],
        page: 0,
        hasMore: false,
        keyword: '',
        loading: false,
        tab: 'library',
        onPick: null,
        returnFocus: null,
        detail: null,      /* 详情侧栏当前展示的图片对象 */
        lb: false,         /* 灯箱是否打开 */
        lbList: [],        /* 灯箱可切换的图片列表 */
        lbIndex: 0
    };

    /* ------------------------------------------------------------------ *
     * 骨架
     * ------------------------------------------------------------------ */

    function build() {
        var root = document.createElement('div');
        root.className = 'ecmd';
        root.setAttribute('hidden', 'hidden');
        root.innerHTML = ''
            + '<div class="ecmd__mask" data-close></div>'
            + '<div class="ecmd__dialog" role="dialog" aria-modal="true" aria-label="选择图片">'
            + '  <header class="ecmd__head">'
            + '    <h3 class="ecmd__title" data-title>选择图片</h3>'
            + '    <div class="ecmd__tabs">'
            + '      <button type="button" class="ecmd__tab is-on" data-tab="library">媒体库</button>'
            + '      <button type="button" class="ecmd__tab" data-tab="link">链接地址</button>'
            + '    </div>'
            + '    <button type="button" class="ecmd__close" data-close aria-label="关闭">' + ICON.close + '</button>'
            + '  </header>'
            + '  <div class="ecmd__bar" data-bar>'
            + '    <label class="ecmd__search">' + ICON.search
            + '      <input type="search" placeholder="搜索图片名称…" data-search autocomplete="off">'
            + '    </label>'
            + '    <span class="ecmd__count" data-count></span>'
            + '    <span class="ecmd__spacer"></span>'
            + '    <button type="button" class="ecmd__btn" data-upload>' + ICON.upload + '上传图片</button>'
            + '  </div>'
            + '  <div class="ecmd__main">'
            + '    <div class="ecmd__body" data-body>'
            + '      <div class="ecmd__pane" data-pane="library">'
            + '        <div class="ecmd__grid" data-grid></div>'
            + '        <div class="ecmd__empty" data-empty hidden></div>'
            + '        <div class="ecmd__loading" data-loading hidden><i class="ecmd__spin"></i>正在读取媒体库…</div>'
            + '        <button type="button" class="ecmd__more" data-more hidden>加载更多</button>'
            + '        <input type="file" accept="image/*" multiple data-file hidden>'
            + '      </div>'
            + '      <div class="ecmd__pane" data-pane="link" hidden>'
            + '        <div class="ecmd__link">'
            + '          <label for="ecmd-link-input">图片地址</label>'
            + '          <input id="ecmd-link-input" type="url" placeholder="https://example.com/cover.jpg" data-link autocomplete="off">'
            + '          <div class="ecmd__preview" data-preview>粘贴或输入图片地址，下方会显示预览</div>'
            + '        </div>'
            + '      </div>'
            + '    </div>'
            + '    <aside class="ecmd__side" data-side hidden>'
            + '      <figure class="ecmd__side-view" data-side-view></figure>'
            + '      <dl class="ecmd__side-meta" data-side-meta></dl>'
            + '      <div class="ecmd__side-url">'
            + '        <input type="text" readonly data-side-link aria-label="图片地址">'
            + '        <button type="button" class="ecmd__btn" data-side-copy>复制</button>'
            + '      </div>'
            + '      <div class="ecmd__side-actions">'
            + '        <button type="button" class="ecmd__btn" data-side-zoom>' + ICON.zoom + '预览大图</button>'
            + '      </div>'
            + '    </aside>'
            + '  </div>'
            + '  <footer class="ecmd__foot">'
            + '    <span class="ecmd__picked" data-picked>未选择</span>'
            + '    <div class="ecmd__foot-actions">'
            + '      <button type="button" class="ecmd__btn" data-cancel>取消</button>'
            + '      <button type="button" class="ecmd__btn ecmd__btn--primary" data-ok disabled>确定</button>'
            + '    </div>'
            + '  </footer>'
            + '</div>'
            + '<div class="ecmd__lb" data-lb hidden>'
            + '  <div class="ecmd__lb-mask" data-lb-close></div>'
            + '  <button type="button" class="ecmd__lb-nav ecmd__lb-prev" data-lb-prev aria-label="上一张">' + ICON.prev + '</button>'
            + '  <figure class="ecmd__lb-stage"><img data-lb-img alt=""></figure>'
            + '  <button type="button" class="ecmd__lb-nav ecmd__lb-next" data-lb-next aria-label="下一张">' + ICON.next + '</button>'
            + '  <figcaption class="ecmd__lb-cap"><b data-lb-name></b><span data-lb-meta></span></figcaption>'
            + '  <button type="button" class="ecmd__lb-close" data-lb-close aria-label="关闭">' + ICON.close + '</button>'
            + '</div>';

        document.body.appendChild(root);

        ui = {
            root: root,
            title: root.querySelector('[data-title]'),
            bar: root.querySelector('[data-bar]'),
            search: root.querySelector('[data-search]'),
            count: root.querySelector('[data-count]'),
            body: root.querySelector('[data-body]'),
            grid: root.querySelector('[data-grid]'),
            empty: root.querySelector('[data-empty]'),
            loading: root.querySelector('[data-loading]'),
            more: root.querySelector('[data-more]'),
            file: root.querySelector('[data-file]'),
            link: root.querySelector('[data-link]'),
            preview: root.querySelector('[data-preview]'),
            picked: root.querySelector('[data-picked]'),
            ok: root.querySelector('[data-ok]'),
            side: root.querySelector('[data-side]'),
            sideView: root.querySelector('[data-side-view]'),
            sideMeta: root.querySelector('[data-side-meta]'),
            sideLink: root.querySelector('[data-side-link]'),
            lb: root.querySelector('[data-lb]'),
            lbImg: root.querySelector('[data-lb-img]'),
            lbName: root.querySelector('[data-lb-name]'),
            lbMeta: root.querySelector('[data-lb-meta]'),
            lbPrev: root.querySelector('[data-lb-prev]'),
            lbNext: root.querySelector('[data-lb-next]')
        };

        bind();
    }

    /* ------------------------------------------------------------------ *
     * 数据
     * ------------------------------------------------------------------ */

    function request(page, keyword) {
        var url = apiUrl() + (apiUrl().indexOf('?') > -1 ? '&' : '?')
            + 'action=list&page=' + page + '&size=' + PAGE_SIZE;
        if (keyword) {
            url += '&q=' + encodeURIComponent(keyword);
        }

        return fetch(url, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (res) {
            if (res.status === 401) {
                throw new Error('auth');
            }
            if (!res.ok) {
                throw new Error('http');
            }
            return res.json();
        });
    }

    function load(reset) {
        if (state.loading) {
            return;
        }

        state.loading = true;
        var page = reset ? 1 : state.page + 1;

        ui.loading.hidden = false;
        ui.more.hidden = true;

        request(page, state.keyword).then(function (data) {
            state.loading = false;
            ui.loading.hidden = true;

            if (!data || !data.ok) {
                throw new Error('bad');
            }

            state.page = data.page || page;
            state.hasMore = !!data.hasMore;
            state.total = data.total || 0;
            state.items = reset ? (data.items || []) : state.items.concat(data.items || []);

            ui.count.textContent = state.total ? '共 ' + state.total + ' 张' : '';
            paintGrid();
        }).catch(function (err) {
            state.loading = false;
            ui.loading.hidden = true;
            ui.more.hidden = true;

            var detail = err && err.message === 'auth'
                ? '登录状态已失效，请刷新后台页面后重试。'
                : '可以先用「链接地址」直接填图。若持续失败，请在浏览器开发者工具的 Network 里查看 media.php 请求的具体状态码。';
            ui.empty.hidden = false;
            ui.empty.innerHTML = ICON.image + '<b>媒体库读取失败</b><p>' + esc(detail) + '</p>';
        });
    }

    /* ------------------------------------------------------------------ *
     * 详情侧栏（WordPress 风格）
     * ------------------------------------------------------------------ */

    function findItem(url) {
        for (var i = 0; i < state.items.length; i++) {
            if (state.items[i].url === url) {
                return state.items[i];
            }
        }
        return null;
    }

    function metaRow(label, value) {
        return '<div class="ecmd__side-row"><dt>' + label + '</dt><dd>' + (value || '—') + '</dd></div>';
    }

    function showDetail(item) {
        state.detail = item || null;

        if (!item) {
            ui.side.hidden = true;
            ui.sideView.innerHTML = '';
            ui.sideMeta.innerHTML = '';
            return;
        }

        ui.side.hidden = false;
        ui.sideView.innerHTML = '<img src="' + esc(item.url) + '" alt="">';
        ui.sideMeta.innerHTML = ''
            + metaRow('文件名', esc(item.name || item.title || ''))
            + '<div class="ecmd__side-row"><dt>尺寸</dt><dd data-side-dims>读取中…</dd></div>'
            + metaRow('大小', esc(item.size || ''))
            + metaRow('类型', esc(item.mime || ''))
            + metaRow('上传日期', esc(item.date || ''));
        ui.sideLink.value = item.url;

        /* 原始尺寸：服务端不存宽高，客户端加载后读取 */
        var probe = new Image();
        probe.onload = function () {
            if (state.detail === item) {
                var el = ui.sideMeta.querySelector('[data-side-dims]');
                if (el) {
                    el.textContent = probe.naturalWidth + ' × ' + probe.naturalHeight + ' 像素';
                }
            }
        };
        probe.src = item.url;
    }

    function copyText(text, done) {
        var ok = false;
        try {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.cssText = 'position:fixed;opacity:0;';
            document.body.appendChild(ta);
            ta.select();
            ok = document.execCommand('copy');
            document.body.removeChild(ta);
        } catch (err) {
            ok = false;
        }
        if (!ok && navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                done(true);
            }, function () {
                done(false);
            });
            return;
        }
        done(ok);
    }

    /* ------------------------------------------------------------------ *
     * 灯箱预览
     * ------------------------------------------------------------------ */

    function openLightbox(item) {
        if (!item) {
            return;
        }

        var index = -1;
        state.items.forEach(function (it, i) {
            if (it.url === item.url) {
                index = i;
            }
        });

        if (index > -1) {
            state.lbList = state.items;
            state.lbIndex = index;
        } else {
            state.lbList = [item];
            state.lbIndex = 0;
        }

        state.lb = true;
        renderLightbox();
        ui.lb.hidden = false;
    }

    function renderLightbox() {
        var item = state.lbList[state.lbIndex];
        if (!item) {
            closeLightbox();
            return;
        }

        ui.lbImg.src = item.url;
        ui.lbImg.alt = item.name || item.title || '';
        ui.lbName.textContent = item.name || item.title || '';
        ui.lbMeta.textContent = [item.date, item.size].filter(Boolean).join(' · ');
        ui.lbPrev.hidden = state.lbList.length < 2;
        ui.lbNext.hidden = state.lbList.length < 2;
    }

    function lbNav(step) {
        if (!state.lb || state.lbList.length < 2) {
            return;
        }
        var len = state.lbList.length;
        state.lbIndex = (state.lbIndex + step + len) % len;
        renderLightbox();
    }

    function closeLightbox() {
        state.lb = false;
        ui.lb.hidden = true;
        ui.lbImg.src = '';
    }

    /* ------------------------------------------------------------------ *
     * 渲染
     * ------------------------------------------------------------------ */

    function paintGrid() {
        ui.grid.innerHTML = '';

        if (!state.items.length) {
            ui.empty.hidden = false;
            ui.empty.innerHTML = state.keyword
                ? ICON.image + '<b>没有匹配的图片</b><p>换个关键词，或上传一张新的。</p>'
                : ICON.image + '<b>媒体库还是空的</b><p>点右上角「上传图片」，或把文件拖进这个窗口。</p>';
            ui.more.hidden = true;
            paintPicked();
            return;
        }

        ui.empty.hidden = true;

        state.items.forEach(function (item) {
            var tile = document.createElement('button');
            tile.type = 'button';
            tile.className = 'ecmd__tile' + (state.selected.indexOf(item.url) > -1 ? ' is-on' : '');
            tile.title = item.title || item.name || item.url;
            tile.dataset.url = item.url;
            tile.innerHTML = '<img src="' + esc(item.url) + '" alt="' + esc(item.name || item.title || '') + '" loading="lazy">'
                + '<span class="ecmd__tile-name">' + esc(item.name || item.title || '未命名') + '</span>'
                + '<span class="ecmd__check">' + ICON.check + '</span>'
                + '<span class="ecmd__zoom" title="预览大图">' + ICON.zoom + '</span>';

            tile.addEventListener('click', function () {
                choose(item.url, tile);
            });

            tile.addEventListener('dblclick', function () {
                openLightbox(item);
            });

            var zoom = tile.querySelector('.ecmd__zoom');
            zoom.addEventListener('click', function (e) {
                e.stopPropagation();
                choose(item.url, tile);
                openLightbox(item);
            });

            ui.grid.appendChild(tile);
        });

        ui.more.hidden = !state.hasMore;
        paintPicked();
    }

    function choose(url, tile) {
        if (state.multiple) {
            var index = state.selected.indexOf(url);
            if (index > -1) {
                state.selected.splice(index, 1);
            } else {
                state.selected.push(url);
            }
        } else {
            state.selected = [url];
        }

        Array.prototype.forEach.call(ui.grid.querySelectorAll('.ecmd__tile'), function (node) {
            node.classList.toggle('is-on', state.selected.indexOf(node.dataset.url) > -1);
        });

        if (tile && state.multiple) {
            tile.classList.toggle('is-on', state.selected.indexOf(url) > -1);
        }

        /* 点击即更新右侧详情 */
        showDetail(findItem(url));

        paintPicked();
    }

    function paintPicked() {
        if (!state.selected.length) {
            ui.picked.textContent = state.multiple ? '未选择图片' : '未选择';
            ui.ok.disabled = true;
            return;
        }

        ui.picked.innerHTML = state.multiple
            ? '已选 <b>' + state.selected.length + '</b> 张'
            : '已选 <b>1</b> 张';
        ui.ok.disabled = false;
    }

    /* ------------------------------------------------------------------ *
     * 上传
     * ------------------------------------------------------------------ */

    function upload(files) {
        var list = Array.prototype.slice.call(files || []).filter(function (file) {
            return /^image\//i.test(file.type);
        });

        if (!list.length) {
            return;
        }

        var done = [];
        var left = list.length;

        ui.loading.hidden = false;
        ui.loading.innerHTML = '<i class="ecmd__spin"></i>正在上传 ' + list.length + ' 张…';

        list.forEach(function (file) {
            var form = new FormData();
            form.append('file', file);

            fetch(uploadUrl(), { method: 'POST', body: form, credentials: 'same-origin' })
                .then(function (res) {
                    return res.ok ? res.json() : null;
                })
                .then(function (data) {
                    var att = data && data[1];
                    if (!att || !att.url) {
                        return;
                    }

                    var item = {
                        cid: att.cid || 0,
                        title: att.title || file.name,
                        name: file.name,
                        url: att.url,
                        date: ''
                    };

                    done.push(item);

                    if (state.multiple) {
                        state.selected.push(item.url);
                    } else {
                        state.selected = [item.url];
                    }
                })
                .catch(function () {
                    /* 单个失败不打断其余文件 */
                })
                .then(function () {
                    left--;
                    if (left > 0) {
                        return;
                    }

                    ui.loading.hidden = true;
                    ui.loading.innerHTML = '<i class="ecmd__spin"></i>正在读取媒体库…';

                    if (!done.length) {
                        notice('上传失败，请检查登录状态或附件设置');
                        return;
                    }

                    state.items = done.concat(state.items);
                    state.total = (state.total || 0) + done.length;
                    ui.count.textContent = '共 ' + state.total + ' 张';
                    paintGrid();
                });
        });
    }

    function notice(message) {
        var old = ui.picked.innerHTML;
        ui.picked.textContent = message;
        ui.picked.style.color = '#c0392b';
        window.setTimeout(function () {
            ui.picked.innerHTML = old;
            ui.picked.style.color = '';
        }, 3200);
    }

    /* ------------------------------------------------------------------ *
     * Tab
     * ------------------------------------------------------------------ */

    function switchTab(tab) {
        state.tab = tab;

        Array.prototype.forEach.call(ui.root.querySelectorAll('.ecmd__tab'), function (btn) {
            btn.classList.toggle('is-on', btn.dataset.tab === tab);
        });

        Array.prototype.forEach.call(ui.root.querySelectorAll('[data-pane]'), function (pane) {
            pane.hidden = pane.dataset.pane !== tab;
        });

        ui.bar.hidden = tab !== 'library';

        if (tab === 'link') {
            ui.side.hidden = true;
            state.selected = ui.link.value.trim() ? [ui.link.value.trim()] : [];
            paintPicked();
            window.setTimeout(function () {
                ui.link.focus();
                ui.link.select();
            }, 30);
        } else {
            ui.side.hidden = !state.detail;
            if (!state.items.length && !state.loading) {
                load(true);
            }
        }
    }

    /* ------------------------------------------------------------------ *
     * 打开 / 关闭
     * ------------------------------------------------------------------ */

    function open(options) {
        options = options || {};

        if (!ui) {
            build();
        }

        state.open = true;
        state.multiple = !!options.multiple;
        state.onPick = typeof options.onPick === 'function' ? options.onPick : null;
        state.keyword = '';
        state.page = 0;
        state.hasMore = false;
        state.items = [];   /* 每次打开都重新拉取，保证看到刚上传的图 */
        state.returnFocus = document.activeElement;
        state.detail = null;
        state.lb = false;

        ui.side.hidden = true;
        ui.sideView.innerHTML = '';
        ui.sideMeta.innerHTML = '';
        ui.lb.hidden = true;

        state.selected = [];
        if (options.value) {
            state.selected = Array.isArray(options.value)
                ? options.value.filter(Boolean)
                : [String(options.value)].filter(Boolean);
        }

        ui.title.textContent = options.title || (state.multiple ? '选择图片（可多选）' : '选择图片');
        ui.search.value = '';
        ui.link.value = '';
        ui.preview.innerHTML = '粘贴或输入图片地址，下方会显示预览';
        ui.root.removeAttribute('hidden');
        document.documentElement.style.overflow = 'hidden';

        switchTab('library');
        load(true);

        window.setTimeout(function () {
            ui.search.focus();
        }, 40);
    }

    function close() {
        if (!ui) {
            return;
        }
        closeLightbox();
        state.open = false;
        ui.root.setAttribute('hidden', 'hidden');
        ui.root.classList.remove('is-drag');
        document.documentElement.style.overflow = '';

        if (state.returnFocus && state.returnFocus.focus) {
            try {
                state.returnFocus.focus();
            } catch (err) {
                /* 忽略 */
            }
        }
    }

    function confirm() {
        var value = state.selected.slice();

        if (state.tab === 'link') {
            var url = ui.link.value.trim();
            value = url ? [url] : [];
        }

        if (!value.length) {
            return;
        }

        var cb = state.onPick;
        close();

        if (cb) {
            cb(value);
        }
    }

    /* ------------------------------------------------------------------ *
     * 事件
     * ------------------------------------------------------------------ */

    var timer = null;

    function bind() {
        Array.prototype.forEach.call(ui.root.querySelectorAll('[data-close], [data-cancel]'), function (el) {
            el.addEventListener('click', close);
        });

        ui.ok.addEventListener('click', confirm);

        Array.prototype.forEach.call(ui.root.querySelectorAll('.ecmd__tab'), function (btn) {
            btn.addEventListener('click', function () {
                switchTab(btn.dataset.tab);
            });
        });

        ui.search.addEventListener('input', function () {
            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                state.keyword = ui.search.value.trim();
                load(true);
            }, 260);
        });

        ui.more.addEventListener('click', function () {
            load(false);
        });

        ui.body.addEventListener('scroll', function () {
            if (state.tab !== 'library' || !state.hasMore || state.loading) {
                return;
            }
            var rest = ui.body.scrollHeight - ui.body.scrollTop - ui.body.clientHeight;
            if (rest < 220) {
                load(false);
            }
        });

        ui.root.querySelector('[data-upload]').addEventListener('click', function () {
            ui.file.click();
        });

        ui.file.addEventListener('change', function () {
            upload(ui.file.files);
            ui.file.value = '';
        });

        ui.link.addEventListener('input', function () {
            var url = ui.link.value.trim();
            if (!url) {
                ui.preview.innerHTML = '粘贴或输入图片地址，下方会显示预览';
                state.selected = [];
                paintPicked();
                return;
            }

            ui.preview.innerHTML = '<img src="' + esc(url) + '" alt="预览">';
            var img = ui.preview.querySelector('img');
            img.addEventListener('error', function () {
                ui.preview.innerHTML = '这张图片无法加载，请检查地址是否正确';
                state.selected = [];
                paintPicked();
            });

            state.selected = [url];
            paintPicked();
        });

        /* 详情侧栏 */
        ui.root.querySelector('[data-side-copy]').addEventListener('click', function () {
            var btn = this;
            copyText(ui.sideLink.value, function (ok) {
                if (!ok) {
                    return;
                }
                btn.textContent = '已复制';
                window.setTimeout(function () {
                    btn.textContent = '复制';
                }, 1600);
            });
        });
        ui.root.querySelector('[data-side-zoom]').addEventListener('click', function () {
            openLightbox(state.detail);
        });

        /* 灯箱 */
        Array.prototype.forEach.call(ui.root.querySelectorAll('[data-lb-close]'), function (el) {
            el.addEventListener('click', closeLightbox);
        });
        ui.lbPrev.addEventListener('click', function () {
            lbNav(-1);
        });
        ui.lbNext.addEventListener('click', function () {
            lbNav(1);
        });

        /* 拖拽上传 */
        var depth = 0;
        ui.root.addEventListener('dragenter', function (e) {
            e.preventDefault();
            depth++;
            ui.root.classList.add('is-drag');
        });
        ui.root.addEventListener('dragover', function (e) {
            e.preventDefault();
        });
        ui.root.addEventListener('dragleave', function () {
            depth--;
            if (depth <= 0) {
                depth = 0;
                ui.root.classList.remove('is-drag');
            }
        });
        ui.root.addEventListener('drop', function (e) {
            e.preventDefault();
            depth = 0;
            ui.root.classList.remove('is-drag');
            if (e.dataTransfer && e.dataTransfer.files) {
                upload(e.dataTransfer.files);
            }
        });

        /* 键盘 */
        document.addEventListener('keydown', function (e) {
            if (!state.open) {
                return;
            }

            /* 灯箱优先：ESC 关灯箱，左右键切换 */
            if (state.lb) {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    closeLightbox();
                } else if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    lbNav(-1);
                } else if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    lbNav(1);
                }
                return;
            }

            if (e.key === 'Escape') {
                e.preventDefault();
                close();
            } else if (e.key === 'Enter') {
                var tag = (e.target.tagName || '').toLowerCase();
                if ((tag === 'input' || tag === 'textarea') && !e.target.hasAttribute('data-side-link')) {
                    e.preventDefault();
                    confirm();
                }
            }
        });
    }

    /* ------------------------------------------------------------------ *
     * 对外接口
     * ------------------------------------------------------------------ */

    var EcodMedia = {
        pick: open,
        open: open,
        close: close,
        config: {
            api: apiUrl,
            upload: uploadUrl
        }
    };

    window.EcodMedia = EcodMedia;
})();
