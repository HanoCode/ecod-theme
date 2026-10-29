/**
 * Ecod Theme · 后台工具
 *
 * 配置快照、sitemap / robots 生成。所有请求都带管理员令牌，
 * 服务端会再校验一次登录态，双重保险。
 */
(function () {
    'use strict';

    var cfg = window.ECOD_TOOL || {};
    if (!cfg.api || !cfg.token) {
        return;
    }

    var listBox = document.querySelector('[data-ecod-snaplist]');
    var statusEl = document.querySelector('[data-ecod-tool-status]');

    function status(message, isError) {
        if (!statusEl) {
            return;
        }
        statusEl.hidden = false;
        statusEl.textContent = message;
        statusEl.classList.toggle('is-error', !!isError);
        clearTimeout(status._timer);
        status._timer = setTimeout(function () {
            statusEl.hidden = true;
        }, 6000);
    }

    function request(action, data) {
        var body = new FormData();
        body.append('token', cfg.token);
        Object.keys(data || {}).forEach(function (key) {
            body.append(key, data[key]);
        });

        return fetch(cfg.api + '?action=' + action, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (response) {
            return response.json().catch(function () {
                throw new Error('返回内容无法解析');
            });
        });
    }

    function esc(text) {
        return String(text === undefined || text === null ? '' : text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function render(list) {
        if (!listBox) {
            return;
        }

        if (!list || !list.length) {
            listBox.innerHTML = '<p class="ecod-snaplist__empty">还没有快照。点右上角「保存快照」创建第一份。</p>';
            return;
        }

        listBox.innerHTML = list.map(function (item) {
            return '<div class="ecod-snap" data-name="' + esc(item.name) + '">'
                + '<div class="ecod-snap__main">'
                + '<b>' + esc(item.label) + '</b>'
                + '<small>' + (item.count || 0) + ' 项设置 · ' + (Math.round((item.size || 0) / 1024 * 10) / 10) + ' KB</small>'
                + '</div>'
                + '<div class="ecod-snap__act">'
                + '<button type="button" class="ecod-admin__ghost" data-ecod-snap-restore>恢复</button>'
                + '<button type="button" class="ecod-admin__ghost is-danger" data-ecod-snap-delete>删除</button>'
                + '</div>'
                + '</div>';
        }).join('');
    }

    function reload() {
        return request('snapshot_list').then(function (res) {
            if (res && res.ok) {
                render(res.list);
            }
        }).catch(function () {});
    }

    /* 保存快照 */
    document.addEventListener('click', function (event) {
        var saveBtn = event.target.closest('[data-ecod-snapshot-save]');
        if (saveBtn) {
            saveBtn.disabled = true;
            request('snapshot_save').then(function (res) {
                status(res.message || (res.ok ? '已保存' : '保存失败'), !res.ok);
                return reload();
            }).catch(function (error) {
                status(error.message || '请求失败', true);
            }).then(function () {
                saveBtn.disabled = false;
            });
            return;
        }

        var restoreBtn = event.target.closest('[data-ecod-snap-restore]');
        if (restoreBtn) {
            var row = restoreBtn.closest('[data-name]');
            var name = row ? row.getAttribute('data-name') : '';
            if (!name) {
                return;
            }
            var label = row.querySelector('b') ? row.querySelector('b').textContent : name;
            if (!window.confirm('确定恢复到「' + label + '」？\n当前配置会先自动存一份快照。')) {
                return;
            }
            restoreBtn.disabled = true;
            request('snapshot_restore', { name: name }).then(function (res) {
                status(res.message || (res.ok ? '已恢复' : '恢复失败'), !res.ok);
                return reload();
            }).catch(function (error) {
                status(error.message || '请求失败', true);
            }).then(function () {
                restoreBtn.disabled = false;
            });
            return;
        }

        var deleteBtn = event.target.closest('[data-ecod-snap-delete]');
        if (deleteBtn) {
            var snapRow = deleteBtn.closest('[data-name]');
            var snapName = snapRow ? snapRow.getAttribute('data-name') : '';
            if (!snapName || !window.confirm('删除这份快照？删除后无法找回。')) {
                return;
            }
            deleteBtn.disabled = true;
            request('snapshot_delete', { name: snapName }).then(function (res) {
                status(res.message || (res.ok ? '已删除' : '删除失败'), !res.ok);
                return reload();
            }).catch(function (error) {
                status(error.message || '请求失败', true);
            }).then(function () {
                deleteBtn.disabled = false;
            });
            return;
        }

        var buildBtn = event.target.closest('[data-ecod-build]');
        if (buildBtn) {
            var kind = buildBtn.getAttribute('data-ecod-build');
            var action = kind === 'robots' ? 'build_robots'
                : (kind === 'baidu' ? 'push_baidu' : 'build_sitemap');
            buildBtn.disabled = true;
            request(action).then(function (res) {
                status(res.message || (res.ok ? '已生成' : '生成失败'), !res.ok);
            }).catch(function (error) {
                status(error.message || '请求失败', true);
            }).then(function () {
                buildBtn.disabled = false;
            });
            return;
        }

        var pageBtn = event.target.closest('[data-ecod-page-create]');
        if (pageBtn) {
            var pageRow = pageBtn.closest('[data-key]');
            var pageKey = pageRow ? pageRow.getAttribute('data-key') : '';
            if (!pageKey) {
                return;
            }
            pageBtn.disabled = true;
            pageBtn.textContent = '创建中…';
            request('page_create', { key: pageKey }).then(function (res) {
                status(res.message || (res.ok ? '已创建' : '创建失败'), !res.ok);
                if (res.ok && pageRow) {
                    markCreated(pageRow, res);
                }
            }).catch(function (error) {
                status(error.message || '请求失败', true);
                pageBtn.disabled = false;
                pageBtn.textContent = '创建';
            });
        }
    });

    /* 创建成功后把按钮换成「查看 / 编辑」，无需刷新后台 */
    function markCreated(row, res) {
        row.classList.add('is-exists');
        var act = row.querySelector('.ecod-page-row__act');
        if (!act) {
            return;
        }
        var edit = res.edit || '';
        act.innerHTML = (res.url ? '<a class="ecod-admin__ghost" href="' + esc(res.url) + '" target="_blank" rel="noopener">查看</a>' : '')
            + (edit ? '<a class="ecod-admin__ghost" href="' + esc(edit) + '">编辑</a>' : '');
    }
})();
