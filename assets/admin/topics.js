/**
 * Ecod Theme · 后台「专题」构建器
 *
 * 列表式管理：一行一个专题，行内展开编辑（名称 / 别名 / 描述 / 封面 / 挑稿）。
 * 整份数据序列化进隐藏字段 ETopics（JSON），由 core/topics.php 读取。
 *
 * 设计约束：
 *  - 顺序即前台顺序，拖动行调整；拖动必须从手柄开始，避免选中文字就触发拖拽
 *  - 别名（slug）留空时按「topic-N」补齐，保证地址永远可用；中文不转拼音，手填更可控
 *  - 挑稿只决定「谁属于这个专题」，不决定顺序（专题内一律按发布时间倒序）
 *  - 篇数 = 字段命中的文章（服务端给）+ 本地手动挑的篇数，改一下就实时变
 *  - 任何改动都写回隐藏字段并派发 input 事件，复用设置页原有的「未保存」提示
 *
 * Copyright (c) 2026 Ecod · MIT License · https://ecod.cc
 */
(function () {
    'use strict';

    var DATA = window.ECOD_TOPICS;
    if (!DATA) {
        return;
    }

    var root = document.querySelector('[data-tpb]');
    if (!root) {
        return;
    }

    var listEl = root.querySelector('[data-tpb-list]');
    var emptyEl = root.querySelector('[data-tpb-empty]');
    var countEl = document.querySelector('[data-tpb-count]');
    var field = document.querySelector('input[name="ETopics"]');
    var pageBtn = document.querySelector('[data-tpb-page]');
    var pageHint = document.querySelector('[data-tpb-pagehint]');

    var MAX = parseInt(DATA.max, 10) || 30;
    var INDEX = DATA.index || {};
    var API = DATA.api || '';
    var TOKEN = DATA.token || '';

    /* 文章标题缓存：cid -> title（挑稿器与已选 chips 共用） */
    var titles = {};
    var openKey = '';
    var seq = 0;

    function esc(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function newKey() {
        seq++;
        return 'k' + seq.toString(36) + Math.random().toString(36).slice(2, 5);
    }

    function newId() {
        return 't' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
    }

    function slugify(text) {
        return String(text || '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/-{2,}/g, '-')
            .replace(/^-|-$/g, '')
            .slice(0, 40);
    }

    function norm(row, i) {
        var cids = [];
        (row && row.cids ? row.cids : []).forEach(function (cid) {
            cid = parseInt(cid, 10);
            if (cid > 0 && cids.indexOf(cid) < 0) {
                cids.push(cid);
            }
        });
        return {
            _k: newKey(),
            id: row && row.id ? String(row.id) : newId(),
            name: row && row.name ? String(row.name) : '',
            slug: row && row.slug ? String(row.slug) : '',
            desc: row && row.desc ? String(row.desc) : '',
            cover: row && row.cover ? String(row.cover) : '',
            cids: cids,
            on: !row || row.on === undefined ? true : !!row.on,
            _i: i
        };
    }

    var state = {
        items: (DATA.items || []).map(norm)
    };

    function find(key) {
        for (var i = 0; i < state.items.length; i++) {
            if (state.items[i]._k === key) {
                return state.items[i];
            }
        }
        return null;
    }

    /* 篇数：字段命中（服务端）+ 当前手动（本地） */
    function countOf(item) {
        var idx = INDEX[item.slug] || {};
        var base = Math.max(0, (idx.count || 0) - (idx.manual || []).length);
        return base + item.cids.length;
    }

    function sync() {
        if (!field) {
            return;
        }
        var payload = {
            items: state.items.map(function (it, i) {
                return {
                    id: it.id,
                    name: it.name,
                    slug: it.slug,
                    desc: it.desc,
                    cover: it.cover,
                    cids: it.cids,
                    on: !!it.on
                };
            })
        };
        field.value = JSON.stringify(payload);
        try {
            field.dispatchEvent(new Event('input', { bubbles: true }));
        } catch (e) {
        }
        if (countEl) {
            countEl.textContent = state.items.length + ' / ' + MAX;
        }
        if (emptyEl) {
            emptyEl.hidden = state.items.length > 0;
        }
    }

    /* ------------------------------------------------------------------
     * 渲染
     * ---------------------------------------------------------------- */

    function rowHtml(item) {
        var count = countOf(item);
        var slugText = item.slug || '（自动生成）';
        var isOpen = openKey === item._k;
        var thumb = item.cover
            ? ' style="background-image:url(\'' + esc(item.cover) + '\')"'
            : '';

        return '<div class="ecod-tpb__row' + (isOpen ? ' is-open' : '') + '" data-k="' + item._k + '">'
            + '<span class="ecod-tpb__handle" data-handle title="拖动排序">⠿</span>'
            + '<span class="ecod-tpb__thumb' + (item.cover ? ' has-img' : '') + '"' + thumb + ' data-thumb>'
            + (item.cover ? '' : esc((item.name || '专').slice(0, 1)))
            + '</span>'
            + '<span class="ecod-tpb__main">'
            + '<b data-name>' + (esc(item.name) || '<i>未命名专题</i>') + '</b>'
            + '<small><code data-slug>' + esc(slugText) + '</code> · <em data-count>' + count + '</em> 篇'
            + (item.on ? '' : ' · <span class="is-off">已停用</span>') + '</small>'
            + '</span>'
            + '<label class="ecod-tpb__on"><input type="checkbox" data-f="on"' + (item.on ? ' checked' : '') + '><i></i><span>启用</span></label>'
            + '<span class="ecod-tpb__acts">'
            + '<button type="button" class="ecod-admin__ghost" data-act="edit">' + (isOpen ? '收起' : '编辑') + '</button>'
            + '<button type="button" class="ecod-admin__ghost is-danger" data-act="del">删除</button>'
            + '</span>'
            + panelHtml(item, isOpen)
            + '</div>';
    }

    function panelHtml(item, isOpen) {
        return '<div class="ecod-tpb__panel"' + (isOpen ? '' : ' hidden') + '>'
            + '<div class="ecod-tpb__grid">'
            + '<label class="ecod-tpb__field"><span>名称</span>'
            + '<input type="text" data-f="name" maxlength="40" value="' + esc(item.name) + '" placeholder="例如 Typecho 深潜"></label>'
            + '<label class="ecod-tpb__field"><span>别名</span>'
            + '<input type="text" data-f="slug" maxlength="40" value="' + esc(item.slug) + '" placeholder="留空自动生成，用于地址 ?t=…" spellcheck="false"></label>'
            + '<label class="ecod-tpb__field ecod-tpb__field--wide"><span>描述</span>'
            + '<textarea data-f="desc" rows="2" maxlength="200" placeholder="一句话说清这个专题在读什么，显示在专题页头">' + esc(item.desc) + '</textarea></label>'
            + '</div>'
            + '<div class="ecod-tpb__cover">'
            + '<span class="ecod-tpb__coverlabel">封面</span>'
            + '<span class="ecod-tpb__coverpv" data-coverpv' + (item.cover ? ' style="background-image:url(\'' + esc(item.cover) + '\')"' : '') + '></span>'
            + '<input type="text" data-f="cover" value="' + esc(item.cover) + '" placeholder="封面图地址；留空自动取最新文章首图" spellcheck="false">'
            + '<button type="button" class="ecod-admin__ghost" data-act="pickimg">选图</button>'
            + '<button type="button" class="ecod-admin__ghost" data-act="uncover">清除</button>'
            + '</div>'
            + '<div class="ecod-tpb__picker">'
            + '<div class="ecod-tpb__pickhd"><b>挑稿</b><em data-pickcount>' + item.cids.length + ' 篇手动加入</em>'
            + '<span class="ecod-tpb__pickhint">字段里选了本专题的文章也会自动进来；顺序一律按发布时间</span></div>'
            + '<div class="ecod-tpb__search">'
            + '<input type="search" data-pick-q placeholder="搜文章标题，回车搜索…" autocomplete="off">'
            + '<button type="button" class="ecod-btn ecod-btn--sm" data-act="picksearch">搜索</button>'
            + '</div>'
            + '<div class="ecod-tpb__results" data-pick-results hidden></div>'
            + '<div class="ecod-tpb__chosen" data-pick-chosen></div>'
            + '</div>'
            + '</div>';
    }

    function render() {
        listEl.innerHTML = state.items.map(rowHtml).join('');
        sync();
    }

    function renderRow(key) {
        var item = find(key);
        if (!item) {
            return;
        }
        var row = listEl.querySelector('.ecod-tpb__row[data-k="' + key + '"]');
        if (!row) {
            render();
            return;
        }
        var wrap = document.createElement('div');
        wrap.innerHTML = rowHtml(item);
        row.parentNode.replaceChild(wrap.firstChild, row);
    }

    /* 只更新摘要区，输入时不重建 DOM（避免焦点丢失） */
    function refreshSummary(item) {
        var row = listEl.querySelector('.ecod-tpb__row[data-k="' + item._k + '"]');
        if (!row) {
            return;
        }
        var nameEl = row.querySelector('[data-name]');
        var slugEl = row.querySelector('[data-slug]');
        var countEl2 = row.querySelector('[data-count]');
        var thumb = row.querySelector('[data-thumb]');
        if (nameEl) {
            nameEl.innerHTML = esc(item.name) || '<i>未命名专题</i>';
        }
        if (slugEl) {
            slugEl.textContent = item.slug || '（自动生成）';
        }
        if (countEl2) {
            countEl2.textContent = countOf(item);
        }
        if (thumb) {
            if (item.cover) {
                thumb.classList.add('has-img');
                thumb.style.backgroundImage = 'url("' + item.cover + '")';
                thumb.textContent = '';
            } else {
                thumb.classList.remove('has-img');
                thumb.style.backgroundImage = '';
                thumb.textContent = (item.name || '专').slice(0, 1);
            }
        }
        var pv = row.querySelector('[data-coverpv]');
        if (pv) {
            if (item.cover) {
                pv.style.backgroundImage = 'url("' + item.cover + '")';
                pv.classList.add('has-img');
            } else {
                pv.style.backgroundImage = '';
                pv.classList.remove('has-img');
            }
        }
    }

    /* ------------------------------------------------------------------
     * 挑稿
     * ---------------------------------------------------------------- */

    function request(action, data) {
        var body = new FormData();
        body.append('token', TOKEN);
        Object.keys(data || {}).forEach(function (k) {
            body.append(k, data[k]);
        });
        return fetch(API + '?action=' + action, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            return res.json();
        });
    }

    function renderChosen(item) {
        var row = listEl.querySelector('.ecod-tpb__row[data-k="' + item._k + '"]');
        if (!row) {
            return;
        }
        var box = row.querySelector('[data-pick-chosen]');
        var label = row.querySelector('[data-pickcount]');
        if (label) {
            label.textContent = item.cids.length + ' 篇手动加入';
        }
        var cnt = row.querySelector('[data-count]');
        if (cnt) {
            cnt.textContent = countOf(item);
        }
        if (!box) {
            return;
        }
        if (!item.cids.length) {
            box.innerHTML = '<span class="ecod-tpb__none">还没有手动加入的文章</span>';
            return;
        }
        box.innerHTML = item.cids.map(function (cid) {
            var title = titles[cid] || ('#' + cid);
            return '<span class="ecod-tpb__chip">' + esc(title)
                + '<button type="button" data-act="unpick" data-cid="' + cid + '" aria-label="移出">×</button></span>';
        }).join('');
    }

    function loadTitles(item) {
        var miss = item.cids.filter(function (cid) {
            return !titles[cid];
        });
        if (!miss.length) {
            renderChosen(item);
            return;
        }
        request('topic_posts', { ids: miss.join(',') }).then(function (res) {
            (res && res.list ? res.list : []).forEach(function (row) {
                titles[row.cid] = row.title;
            });
            renderChosen(item);
        }).catch(function () {
            renderChosen(item);
        });
    }

    function renderResults(item, rows, error) {
        var row = listEl.querySelector('.ecod-tpb__row[data-k="' + item._k + '"]');
        if (!row) {
            return;
        }
        var box = row.querySelector('[data-pick-results]');
        if (!box) {
            return;
        }
        box.hidden = false;
        if (error) {
            /* 令牌失效 / 登录失效 / 网络失败：明说，不要伪装成「没有匹配」 */
            box.innerHTML = '<span class="ecod-tpb__none is-error">' + esc(error) + '，请刷新页面重试</span>';
            return;
        }
        if (!rows.length) {
            box.innerHTML = '<span class="ecod-tpb__none">没有匹配的文章</span>';
            return;
        }
        box.innerHTML = rows.map(function (r) {
            titles[r.cid] = r.title;
            var has = item.cids.indexOf(r.cid) >= 0;
            return '<span class="ecod-tpb__hit">'
                + '<b>' + esc(r.title) + '</b><time>' + esc(r.date || '') + '</time>'
                + (has
                    ? '<em>已加入</em>'
                    : '<button type="button" data-act="addpost" data-cid="' + r.cid + '">加入</button>')
                + '</span>';
        }).join('');
    }

    function searchPosts(item, keyword) {
        request('topic_posts', { q: keyword }).then(function (res) {
            if (res && res.ok === false) {
                renderResults(item, [], (res && res.error) || '接口返回错误');
                return;
            }
            renderResults(item, (res && res.list) ? res.list : []);
        }).catch(function () {
            renderResults(item, [], '网络请求失败');
        });
    }

    /* ------------------------------------------------------------------
     * 事件
     * ---------------------------------------------------------------- */

    /* 拖动排序：只有按住手柄才允许拖 */
    var dragKey = '';

    listEl.addEventListener('mousedown', function (e) {
        var handle = e.target.closest('[data-handle]');
        var row = e.target.closest('.ecod-tpb__row');
        if (handle && row) {
            row.setAttribute('draggable', 'true');
        }
    });

    document.addEventListener('mouseup', function () {
        Array.prototype.forEach.call(listEl.querySelectorAll('.ecod-tpb__row[draggable]'), function (row) {
            row.removeAttribute('draggable');
        });
    });

    listEl.addEventListener('dragstart', function (e) {
        var row = e.target.closest('.ecod-tpb__row');
        if (!row || !row.hasAttribute('draggable')) {
            e.preventDefault();
            return;
        }
        dragKey = row.getAttribute('data-k');
        row.classList.add('is-dragging');
        try {
            e.dataTransfer.setData('text/plain', dragKey);
            e.dataTransfer.effectAllowed = 'move';
        } catch (err) {
        }
    });

    listEl.addEventListener('dragover', function (e) {
        var row = e.target.closest('.ecod-tpb__row');
        if (!dragKey || !row || row.getAttribute('data-k') === dragKey) {
            return;
        }
        e.preventDefault();
        row.classList.add('is-drop');
    });

    listEl.addEventListener('dragleave', function (e) {
        var row = e.target.closest('.ecod-tpb__row');
        if (row) {
            row.classList.remove('is-drop');
        }
    });

    listEl.addEventListener('drop', function (e) {
        var row = e.target.closest('.ecod-tpb__row');
        if (!dragKey || !row) {
            return;
        }
        e.preventDefault();
        row.classList.remove('is-drop');
        var from = state.items.findIndex(function (it) {
            return it._k === dragKey;
        });
        var to = state.items.findIndex(function (it) {
            return it._k === row.getAttribute('data-k');
        });
        if (from < 0 || to < 0 || from === to) {
            return;
        }
        var moved = state.items.splice(from, 1)[0];
        state.items.splice(to, 0, moved);
        dragKey = '';
        render();
    });

    listEl.addEventListener('dragend', function () {
        dragKey = '';
        Array.prototype.forEach.call(listEl.querySelectorAll('.ecod-tpb__row'), function (row) {
            row.classList.remove('is-dragging', 'is-drop');
            row.removeAttribute('draggable');
        });
    });

    listEl.addEventListener('click', function (e) {
        var row = e.target.closest('.ecod-tpb__row');
        if (!row) {
            return;
        }
        var item = find(row.getAttribute('data-k'));
        if (!item) {
            return;
        }
        var actEl = e.target.closest('[data-act]');
        var act = actEl ? actEl.getAttribute('data-act') : '';

        if (act === 'edit') {
            openKey = openKey === item._k ? '' : item._k;
            render();
            if (openKey) {
                loadTitles(item);
            }
            return;
        }

        if (act === 'del') {
            if (!window.confirm('删除专题「' + (item.name || '未命名') + '」？文章不会被删除，只是不再归入该专题。')) {
                return;
            }
            state.items = state.items.filter(function (it) {
                return it._k !== item._k;
            });
            if (openKey === item._k) {
                openKey = '';
            }
            render();
            return;
        }

        if (act === 'addpost' && actEl) {
            var cid = parseInt(actEl.getAttribute('data-cid'), 10);
            if (cid > 0 && item.cids.indexOf(cid) < 0) {
                item.cids.push(cid);
                renderChosen(item);
                refreshSummary(item);
                var em = document.createElement('em');
                em.textContent = '已加入';
                if (actEl.parentNode) {
                    actEl.parentNode.replaceChild(em, actEl);
                }
                sync();
            }
            return;
        }

        if (act === 'unpick' && actEl) {
            var rm = parseInt(actEl.getAttribute('data-cid'), 10);
            item.cids = item.cids.filter(function (x) {
                return x !== rm;
            });
            renderChosen(item);
            refreshSummary(item);
            sync();
            return;
        }

        if (act === 'picksearch') {
            var qEl = row.querySelector('[data-pick-q]');
            searchPosts(item, qEl ? qEl.value.trim() : '');
            return;
        }

        if (act === 'uncover') {
            item.cover = '';
            var coverInput = row.querySelector('[data-f="cover"]');
            if (coverInput) {
                coverInput.value = '';
            }
            refreshSummary(item);
            sync();
            return;
        }
    });

    /* 选图按钮单独绑：EcodMedia 是全局异步加载的，点击时再判断 */
    listEl.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-act="pickimg"]');
        if (!btn) {
            return;
        }
        var row = e.target.closest('.ecod-tpb__row');
        var item = row ? find(row.getAttribute('data-k')) : null;
        if (!item || !window.EcodMedia) {
            return;
        }
        window.EcodMedia.pick({
            value: item.cover,
            multiple: false,
            onPick: function (urls) {
                item.cover = urls && urls[0] ? urls[0] : '';
                var input = row.querySelector('[data-f="cover"]');
                if (input) {
                    input.value = item.cover;
                }
                refreshSummary(item);
            }
        });
    });

    listEl.addEventListener('input', function (e) {
        var row = e.target.closest('.ecod-tpb__row');
        if (!row) {
            return;
        }
        var item = find(row.getAttribute('data-k'));
        var f = e.target.getAttribute('data-f');
        if (!item || !f) {
            return;
        }
        if (f === 'name') {
            item.name = e.target.value;
            refreshSummary(item);
        } else if (f === 'slug') {
            item.slug = slugify(e.target.value) || e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
            refreshSummary(item);
        } else if (f === 'desc') {
            item.desc = e.target.value;
        } else if (f === 'cover') {
            item.cover = e.target.value.trim();
            refreshSummary(item);
        }
        sync();
    });

    listEl.addEventListener('change', function (e) {
        var row = e.target.closest('.ecod-tpb__row');
        if (!row) {
            return;
        }
        var item = find(row.getAttribute('data-k'));
        if (!item) {
            return;
        }
        if (e.target.getAttribute('data-f') === 'on') {
            item.on = !!e.target.checked;
            renderRow(item._k);
        }
    });

    listEl.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') {
            return;
        }
        var q = e.target.closest('[data-pick-q]');
        if (!q) {
            return;
        }
        e.preventDefault();
        var row = e.target.closest('.ecod-tpb__row');
        var item = row ? find(row.getAttribute('data-k')) : null;
        if (item) {
            searchPosts(item, q.value.trim());
        }
    });

    listEl.addEventListener('blur', function (e) {
        /* 别名留空：保存前补一个稳定的 topic-N，避免前台地址找不到 */
        var f = e.target.getAttribute && e.target.getAttribute('data-f');
        if (f !== 'slug') {
            return;
        }
        var row = e.target.closest('.ecod-tpb__row');
        var item = row ? find(row.getAttribute('data-k')) : null;
        if (!item) {
            return;
        }
        if (!item.slug) {
            var n = state.items.indexOf(item) + 1;
            item.slug = 'topic-' + n;
            e.target.value = item.slug;
            refreshSummary(item);
            sync();
        }
    }, true);

    /* 新建 */
    var newBtn = document.querySelector('[data-tpb-new]');
    if (newBtn) {
        newBtn.addEventListener('click', function () {
            if (state.items.length >= MAX) {
                window.alert('最多 ' + MAX + ' 个专题');
                return;
            }
            var n = state.items.length + 1;
            var item = norm({
                name: '新专题 ' + n,
                slug: 'topic-' + n,
                on: true
            }, n);
            /* 别名去重 */
            var taken = state.items.map(function (it) {
                return it.slug;
            });
            var base = item.slug;
            var k = 2;
            while (taken.indexOf(item.slug) >= 0) {
                item.slug = base + '-' + k;
                k++;
            }
            state.items.push(item);
            openKey = item._k;
            render();
            var input = listEl.querySelector('.ecod-tpb__row[data-k="' + item._k + '"] [data-f="name"]');
            if (input) {
                input.focus();
                input.select();
            }
        });
    }

    /* 创建专题页 */
    if (pageBtn) {
        if (DATA.page && DATA.page.url) {
            pageHint.innerHTML = '专题页：<a href="' + esc(DATA.page.url) + '" target="_blank" rel="noopener">'
                + esc(DATA.page.url.replace(/^https?:\/\/[^/]+/i, '')) + '</a>';
        }
        pageBtn.addEventListener('click', function () {
            pageBtn.disabled = true;
            request('page_create', { key: 'topics' }).then(function (res) {
                pageBtn.disabled = false;
                if (res && res.ok) {
                    if (res.url) {
                        DATA.page = DATA.page || {};
                        DATA.page.url = res.url;
                        pageHint.innerHTML = '专题页：<a href="' + esc(res.url) + '" target="_blank" rel="noopener">'
                            + esc(res.url.replace(/^https?:\/\/[^/]+/i, '')) + '</a>';
                    }
                    pageBtn.textContent = res.exists ? '专题页已存在' : '已创建专题页';
                } else {
                    window.alert((res && res.message) || '创建失败');
                }
            }).catch(function () {
                pageBtn.disabled = false;
                window.alert('请求失败，请刷新页面重试');
            });
        });
    }

    render();
})();
