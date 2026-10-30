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
 *
 * Copyright (c) 2026 Ecod. All Rights Reserved. · https://ecod.cc
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
        fmt: '',           /* 格式筛选：webp / png / svg / heic … */
        loading: false,
        tab: 'library',
        onPick: null,
        returnFocus: null,
        detail: null,      /* 详情侧栏当前展示的图片对象 */
        lb: false,         /* 灯箱是否打开 */
        lbList: [],        /* 灯箱可切换的图片列表 */
        lbIndex: 0,
        stats: null        /* 用量统计 {total, size, drivers} */
    };

    /* 后台 token：Security::getToken('ecod-media')，写操作 CSRF 用 */
    function token() {
        return CFG.token || '';
    }

    function post(action, data) {
        var form = data instanceof FormData ? data : new FormData();
        if (!(data instanceof FormData)) {
            Object.keys(data || {}).forEach(function (k) {
                form.append(k, data[k]);
            });
        }
        form.append('_', token());
        return fetch(apiUrl() + '?action=' + action, {
            method: 'POST',
            body: form,
            credentials: 'same-origin'
        }).then(function (res) {
            if (res.status === 401) {
                throw new Error('auth');
            }
            return res.json().catch(function () {
                throw new Error('http');
            });
        });
    }

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
            + '    <select class="ecmd__fmt" data-fmt aria-label="格式筛选">'
            + '      <option value="">全部格式</option>'
            + '      <option value="webp">WebP</option>'
            + '      <option value="jpg">JPG</option>'
            + '      <option value="png">PNG</option>'
            + '      <option value="gif">GIF</option>'
            + '      <option value="avif">AVIF</option>'
            + '      <option value="svg">SVG</option>'
            + '      <option value="heic">HEIC</option>'
            + '    </select>'
            + '    <span class="ecmd__count" data-count></span>'
            + '    <span class="ecmd__stats" data-stats></span>'
            + '    <span class="ecmd__spacer"></span>'
            + '    <button type="button" class="ecmd__btn" data-manage>批量管理</button>'
            + '    <button type="button" class="ecmd__btn" data-upload>' + ICON.upload + '上传图片</button>'
            + '  </div>'
            + '  <div class="ecmd__batch" data-batchbar hidden>'
            + '    <span data-batch-tip>已选 0 张</span>'
            + '    <span class="ecmd__spacer"></span>'
            + '    <button type="button" class="ecmd__btn" data-batch-convert>批量转 WebP</button>'
            + '    <button type="button" class="ecmd__btn ecmd__btn--danger" data-batch-delete>删除所选</button>'
            + '    <button type="button" class="ecmd__btn" data-batch-exit>退出批量</button>'
            + '  </div>'
            + '  <div class="ecmd__main">'
            + '    <div class="ecmd__body" data-body>'
            + '      <div class="ecmd__pane" data-pane="library">'
            + '        <div class="ecmd__grid" data-grid></div>'
            + '        <div class="ecmd__empty" data-empty hidden></div>'
            + '        <div class="ecmd__loading" data-loading hidden><i class="ecmd__spin"></i>正在读取媒体库…</div>'
            + '        <button type="button" class="ecmd__more" data-more hidden>加载更多</button>'
            + '        <input type="file" accept="image/*,.heic,.heif,.svg,.avif,.tif,.tiff" multiple data-file hidden>'
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
            + '        <button type="button" class="ecmd__btn" data-side-rename>改名</button>'
            + '        <button type="button" class="ecmd__btn" data-side-convert>转WebP</button>'
            + '        <button type="button" class="ecmd__btn ecmd__btn--danger" data-side-delete>删除</button>'
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
            fmt: root.querySelector('[data-fmt]'),
            count: root.querySelector('[data-count]'),
            stats: root.querySelector('[data-stats]'),
            batchbar: root.querySelector('[data-batchbar]'),
            batchTip: root.querySelector('[data-batch-tip]'),
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
        if (state.fmt) {
            url += '&fmt=' + encodeURIComponent(state.fmt);
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

    function requestStats() {
        return fetch(apiUrl() + (apiUrl().indexOf('?') > -1 ? '&' : '?') + 'action=stats', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (res) {
            return res.ok ? res.json() : null;
        }).then(function (data) {
            if (data && data.ok) {
                state.stats = data;
                paintStats();
            }
            return data;
        }).catch(function () {
            return null;
        });
    }

    function paintStats() {
        if (!ui.stats) {
            return;
        }
        var s = state.stats;
        var bits = [];
        if (s && s.total) {
            bits.push('共 ' + s.total + ' 张');
            if (s.size) {
                bits.push(s.size);
            }
            var drv = s.drivers || {};
            var nonLocal = Object.keys(drv).filter(function (k) {
                return k !== 'local' && drv[k] > 0;
            });
            nonLocal.forEach(function (k) {
                bits.push(k.toUpperCase() + ' ' + drv[k] + ' 张');
            });
        }
        ui.stats.textContent = bits.join(' · ');
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

        var ext = (item.ext || (item.name || '').split('.').pop() || '').toUpperCase();
        var driverLabel = { local: '本地', oss: '阿里云 OSS', cos: '腾讯云 COS', s3: 'S3 兼容' }[item.driver] || item.driver || '本地';

        ui.side.hidden = false;
        ui.sideView.innerHTML = '<img src="' + esc(item.thumb || item.url) + '" alt="">';
        ui.sideMeta.innerHTML = ''
            + metaRow('文件名', esc(item.name || item.title || ''))
            + '<div class="ecmd__side-row"><dt>尺寸</dt><dd data-side-dims>读取中…</dd></div>'
            + metaRow('大小', esc(item.size || ''))
            + metaRow('类型', esc(item.mime || ''))
            + (ext ? metaRow('格式', esc(ext) + (item.ext === 'webp' && item.driver ? '' : '')) : '')
            + metaRow('存储', esc(driverLabel))
            + metaRow('引用', item.refs > 0 ? '<b class="ecmd__refs">' + item.refs + ' 篇内容</b>' : '未被引用')
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
            var ext = (item.ext || '').toUpperCase();
            tile.innerHTML = '<img src="' + esc(item.thumb || item.url) + '" alt="' + esc(item.name || item.title || '') + '" loading="lazy">'
                + (ext && ext !== 'JPG' && ext !== 'JPEG'
                    ? '<span class="ecmd__badge">' + esc(ext) + '</span>' : '')
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
            if (ui.batchTip) {
                ui.batchTip.textContent = '已选 0 张';
            }
            return;
        }

        ui.picked.innerHTML = state.multiple
            ? '已选 <b>' + state.selected.length + '</b> 张'
            : '已选 <b>1</b> 张';
        ui.ok.disabled = false;
        if (ui.batchTip) {
            ui.batchTip.innerHTML = '已选 <b>' + state.selected.length + '</b> 张';
        }
    }

    /* ------------------------------------------------------------------ *
     * 上传
     * ------------------------------------------------------------------ */

    function upload(files) {
        var list = Array.prototype.slice.call(files || []).filter(function (file) {
            return !file.type || /^image\//i.test(file.type) || /\.(heic|heif|svg|avif|tiff?)$/i.test(file.name);
        });

        if (!list.length) {
            return;
        }

        var left = list.length;
        var failed = 0;

        ui.loading.hidden = false;
        ui.loading.innerHTML = '<i class="ecmd__spin"></i>正在上传 ' + list.length + ' 张…';

        list.forEach(function (file) {
            var form = new FormData();
            form.append('file', file);
            if (CFG.cid) {
                form.append('cid', CFG.cid);
            }

            post('upload', form)
                .then(function (data) {
                    if (!data || !data.ok || !data.url) {
                        failed++;
                        if (data && data.error) {
                            notice(data.error);
                        }
                        return;
                    }
                    if (state.multiple) {
                        state.selected.push(data.url);
                    } else {
                        state.selected = [data.url];
                    }
                })
                .catch(function (err) {
                    failed++;
                    if (err && err.message === 'auth') {
                        notice('登录状态已失效，请刷新页面');
                    }
                })
                .then(function () {
                    left--;
                    if (left > 0) {
                        return;
                    }

                    /* 全部结束后重新拉列表：拿到 cid / 缩略图 / 引用数 */
                    ui.loading.hidden = true;
                    ui.loading.innerHTML = '<i class="ecmd__spin"></i>正在读取媒体库…';
                    if (failed && !state.selected.length) {
                        notice('上传失败，请检查登录状态或文件格式');
                    }
                    state.total = 0;
                    load(true);
                    requestStats();
                });
        });
    }

    function notice(message) {
        if (state.manage) {
            toast(message);
            return;
        }
        var old = ui.picked.innerHTML;
        ui.picked.textContent = message;
        ui.picked.style.color = '#c0392b';
        window.setTimeout(function () {
            ui.picked.innerHTML = old;
            ui.picked.style.color = '';
        }, 3200);
    }

    /* 页面模式下底部选择栏被隐藏，提示改走浮动 toast */
    function toast(message) {
        if (!ui) {
            return;
        }
        var box = ui.root.querySelector('[data-toast]');
        if (!box) {
            box = document.createElement('div');
            box.className = 'ecmd__toast';
            box.setAttribute('data-toast', '');
            var dialog = ui.root.querySelector('.ecmd__dialog');
            if (dialog) {
                dialog.appendChild(box);
            }
        }
        box.textContent = message;
        box.classList.add('is-on');
        window.setTimeout(function () {
            box.classList.remove('is-on');
        }, 3200);
    }

    /* ------------------------------------------------------------------ *
     * 管理：删除 / 改名 / 转 WebP（单项 + 批量）
     * ------------------------------------------------------------------ */

    function removeLocal(url) {
        var index = -1;
        state.items.forEach(function (it, i) {
            if (it.url === url) {
                index = i;
            }
        });
        if (index > -1) {
            state.items.splice(index, 1);
        }
        var sel = state.selected.indexOf(url);
        if (sel > -1) {
            state.selected.splice(sel, 1);
        }
        if (state.detail && state.detail.url === url) {
            showDetail(null);
        }
        if (state.total > 0) {
            state.total--;
        }
        ui.count.textContent = state.total ? '共 ' + state.total + ' 张' : '';
        paintGrid();
    }

    function deleteOne(item, force) {
        return post('delete', { cid: item.cid, force: force ? '1' : '' }).then(function (data) {
            if (data && data.ok) {
                removeLocal(item.url);
                requestStats();
                return true;
            }
            if (data && data.refs > 0 && !force) {
                if (window.confirm('「' + (item.name || item.title) + '」被 ' + data.refs + ' 篇内容引用，仍要删除吗？\n删除后引用处图片会失效。')) {
                    return deleteOne(item, true);
                }
                return false;
            }
            notice((data && data.error) || '删除失败');
            return false;
        }).catch(function (err) {
            notice(err && err.message === 'auth' ? '登录状态已失效' : '删除失败');
            return false;
        });
    }

    function convertOne(item) {
        return post('convert', { cid: item.cid }).then(function (data) {
            if (data && data.ok) {
                notice('已生成 WebP 副本（原图保留）');
                requestStats();
                return true;
            }
            notice((data && data.error) || '转换失败');
            return false;
        }).catch(function (err) {
            notice(err && err.message === 'auth' ? '登录状态已失效' : '转换失败');
            return false;
        });
    }

    function renameOne(item, title) {
        return post('rename', { cid: item.cid, title: title }).then(function (data) {
            if (data && data.ok) {
                item.title = title;
                paintGrid();
                if (state.detail === item) {
                    showDetail(item);
                }
                return true;
            }
            notice((data && data.error) || '改名失败');
            return false;
        }).catch(function () {
            notice('改名失败');
            return false;
        });
    }

    function runBatch(kind) {
        var targets = state.selected.map(function (url) {
            return findItem(url);
        }).filter(Boolean);
        if (!targets.length) {
            return;
        }
        if (kind === 'delete' && !window.confirm('确定删除所选 ' + targets.length + ' 张图片吗？')) {
            return;
        }

        ui.loading.hidden = false;
        ui.loading.innerHTML = '<i class="ecmd__spin"></i>正在处理 ' + targets.length + ' 张…';

        var done = 0;
        var chain = Promise.resolve();
        targets.forEach(function (item) {
            chain = chain.then(function () {
                return kind === 'delete' ? deleteOne(item, false) : convertOne(item);
            }).then(function () {
                done++;
                ui.loading.innerHTML = '<i class="ecmd__spin"></i>已处理 ' + done + ' / ' + targets.length + '…';
            });
        });
        chain.then(function () {
            ui.loading.hidden = true;
            ui.loading.innerHTML = '<i class="ecmd__spin"></i>正在读取媒体库…';
            if (kind === 'convert') {
                load(true);
            }
            paintStats();
        });
    }

    function toggleBatch(on) {
        state.batch = typeof on === 'boolean' ? on : !state.batch;
        if (state.batch) {
            state.prevMultiple = state.multiple;
            state.multiple = true;
        } else {
            state.multiple = !!state.prevMultiple;
        }
        if (ui.batchbar) {
            ui.batchbar.hidden = !state.batch;
        }
        paintPicked();
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
        state.manage = !!options.manage;
        ui.root.classList.toggle('ecmd--page', state.manage);
        state.multiple = !!options.multiple;
        state.onPick = typeof options.onPick === 'function' ? options.onPick : null;
        state.keyword = '';
        state.fmt = '';
        state.page = 0;
        state.hasMore = false;
        state.items = [];   /* 每次打开都重新拉取，保证看到刚上传的图 */
        state.returnFocus = document.activeElement;
        state.detail = null;
        state.lb = false;
        if (state.batch) {
            toggleBatch(false);
        }

        if (ui.fmt) {
            ui.fmt.value = '';
        }
        if (ui.stats) {
            ui.stats.textContent = '';
        }

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

        ui.title.textContent = options.title || (state.manage ? '资源库' : (state.multiple ? '选择图片（可多选）' : '选择图片'));
        ui.search.value = '';
        ui.link.value = '';
        ui.preview.innerHTML = '粘贴或输入图片地址，下方会显示预览';
        ui.root.removeAttribute('hidden');
        document.documentElement.style.overflow = 'hidden';

        switchTab('library');
        load(true);
        requestStats();

        window.setTimeout(function () {
            ui.search.focus();
        }, 40);
    }

    function close() {
        if (!ui || state.manage) {
            return; /* 页面模式下对话框即页面本身，不允许关闭 */
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

        if (ui.fmt) {
            ui.fmt.addEventListener('change', function () {
                state.fmt = ui.fmt.value;
                load(true);
            });
        }

        ui.root.querySelector('[data-manage]').addEventListener('click', function () {
            toggleBatch();
        });
        ui.root.querySelector('[data-batch-exit]').addEventListener('click', function () {
            toggleBatch(false);
        });
        ui.root.querySelector('[data-batch-delete]').addEventListener('click', function () {
            runBatch('delete');
        });
        ui.root.querySelector('[data-batch-convert]').addEventListener('click', function () {
            runBatch('convert');
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
        ui.root.querySelector('[data-side-rename]').addEventListener('click', function () {
            var item = state.detail;
            if (!item) {
                return;
            }
            var title = window.prompt('新的图片名称', item.title || item.name || '');
            if (title && title.trim()) {
                renameOne(item, title.trim());
            }
        });
        ui.root.querySelector('[data-side-convert]').addEventListener('click', function () {
            if (state.detail) {
                convertOne(state.detail);
            }
        });
        ui.root.querySelector('[data-side-delete]').addEventListener('click', function () {
            var item = state.detail;
            if (!item) {
                return;
            }
            if (window.confirm('确定删除「' + (item.name || item.title) + '」吗？')) {
                deleteOne(item, false);
            }
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
