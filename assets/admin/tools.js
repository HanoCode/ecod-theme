/**
 * Ecod Theme · 后台工具
 *
 * 配置快照、sitemap / robots 生成。所有请求都带管理员令牌，
 * 服务端会再校验一次登录态，双重保险。
 *
 * Copyright (c) 2026 Ecod. All Rights Reserved. · https://ecod.cc
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

        /* 全站公告：notif_broadcast，成功后清空输入框 */
        var sendBtn = event.target.closest('[data-ecod-broadcast-send]');
        if (sendBtn) {
            var wrap = sendBtn.closest('.ecod-broadcast');
            var title = wrap ? wrap.querySelector('[data-ecod-broadcast-title]') : null;
            var body = wrap ? wrap.querySelector('[data-ecod-broadcast-body]') : null;
            var link = wrap ? wrap.querySelector('[data-ecod-broadcast-link]') : null;
            var text = title ? String(title.value || '').trim() : '';
            if (!text) {
                status('公告标题不能为空', true);
                if (title) title.focus();
                return;
            }
            sendBtn.disabled = true;
            sendBtn.textContent = '发送中…';
            request('notif_broadcast', {
                title: text,
                body: body ? String(body.value || '') : '',
                link: link ? String(link.value || '').trim() : ''
            }).then(function (res) {
                status(res.message || res.msg || (res.ok ? '公告已发送' : '发送失败'), !res.ok);
                if (res.ok && wrap) {
                    if (title) title.value = '';
                    if (body) body.value = '';
                    if (link) link.value = '';
                }
            }).catch(function (error) {
                status(error.message || '请求失败', true);
            }).then(function () {
                sendBtn.disabled = false;
                sendBtn.textContent = '发送公告';
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

    /* ------------------------------------------------------------------
     * 自定义短链（ecod_shortlinks 表）
     * ---------------------------------------------------------------- */
    var golinkBox = document.querySelector('[data-ecod-golink-list]');
    var golinkBase = '';

    function renderGolinks(list) {
        if (!golinkBox) {
            return;
        }
        if (!list || !list.length) {
            golinkBox.innerHTML = '<p class="ecod-snaplist__empty">还没有自定义短链。填好目标地址后点「保存短链」创建第一份。</p>';
            return;
        }
        golinkBox.innerHTML = list.map(function (item) {
            return '<div class="ecod-snap" data-key="' + esc(item.skey) + '">'
                + '<div class="ecod-snap__main">'
                + '<b>?go=' + esc(item.skey) + '</b>'
                + '<small>→ ' + esc(item.target) + ' · ' + (item.count || 0) + ' 次点击</small>'
                + '</div>'
                + '<div class="ecod-snap__act">'
                + '<button type="button" class="ecod-admin__ghost" data-ecod-golink-copy>复制</button>'
                + '<button type="button" class="ecod-admin__ghost is-danger" data-ecod-golink-del>删除</button>'
                + '</div>'
                + '</div>';
        });
    }

    function reloadGolinks() {
        request('golink_list').then(function (res) {
            if (res.ok) {
                golinkBase = res.api || golinkBase;
                renderGolinks(res.list || []);
            }
        }).catch(function () {});
    }

    if (golinkBox) {
        document.addEventListener('click', function (event) {
            var addBtn = event.target.closest('[data-ecod-golink-add]');
            if (addBtn) {
                var form = document.querySelector('[data-ecod-golink-form]');
                var key = form ? (form.querySelector('[data-ecod-golink-key]') || {}).value || '' : '';
                var target = form ? (form.querySelector('[data-ecod-golink-target]') || {}).value || '' : '';
                if (!target) {
                    status('请先填写目标地址', true);
                    return;
                }
                addBtn.disabled = true;
                request('golink_save', { key: key.trim(), target: target.trim() }).then(function (res) {
                    status(res.message || (res.ok ? '已保存' : '保存失败'), !res.ok);
                    if (res.ok) {
                        if (form) {
                            var k = form.querySelector('[data-ecod-golink-key]');
                            var t = form.querySelector('[data-ecod-golink-target]');
                            if (k) k.value = '';
                            if (t) t.value = '';
                        }
                        reloadGolinks();
                    }
                }).catch(function (error) {
                    status(error.message || '请求失败', true);
                }).then(function () {
                    addBtn.disabled = false;
                });
                return;
            }

            var delBtn = event.target.closest('[data-ecod-golink-del]');
            if (delBtn) {
                var row = delBtn.closest('[data-key]');
                var keyVal = row ? row.getAttribute('data-key') : '';
                if (!keyVal) {
                    return;
                }
                delBtn.disabled = true;
                request('golink_delete', { key: keyVal }).then(function (res) {
                    status(res.message || (res.ok ? '已删除' : '删除失败'), !res.ok);
                    if (res.ok && row.parentNode) {
                        row.parentNode.removeChild(row);
                    } else {
                        delBtn.disabled = false;
                    }
                }).catch(function (error) {
                    status(error.message || '请求失败', true);
                    delBtn.disabled = false;
                });
                return;
            }

            var copyBtn = event.target.closest('[data-ecod-golink-copy]');
            if (copyBtn) {
                var copyRow = copyBtn.closest('[data-key]');
                var copyKey = copyRow ? copyRow.getAttribute('data-key') : '';
                var link = golinkBase ? golinkBase.replace('{key}', copyKey) : location.origin + '/?go=' + copyKey;
                var done = function () {
                    copyBtn.textContent = '已复制';
                    setTimeout(function () {
                        copyBtn.textContent = '复制';
                    }, 1500);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(link).then(done);
                } else {
                    var input = document.createElement('input');
                    input.value = link;
                    document.body.appendChild(input);
                    input.select();
                    document.execCommand('copy');
                    document.body.removeChild(input);
                    done();
                }
            }
        });

        reloadGolinks();
    }
})();
