/* Ecod Theme · 授权面板交互（后台「站长 → 主题授权」） */
(function () {
  'use strict';

  function $(sel, root) { return (root || document).querySelector(sel); }
  function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  var tool = window.ECOD_TOOL || {};
  var API = tool.api || 'api.php';

  function api(action, method, data) {
    var url = API + '?action=' + encodeURIComponent(action);
    var opts = { method: method || 'GET', credentials: 'same-origin', headers: {} };
    if (method === 'POST') {
      var body = new URLSearchParams();
      if (tool.token) body.append('token', tool.token);
      Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
      opts.body = body;
      opts.headers['Content-Type'] = 'application/x-www-form-urlencoded';
    } else if (data) {
      Object.keys(data).forEach(function (k) { url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(data[k]); });
    }
    return fetch(url, opts).then(function (r) { return r.json(); });
  }

  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]; }); }

  function modeLabel(mode) {
    var map = {
      off: '未开启', licensed: '已授权', stale: '待复验', grace: '离线宽限',
      trial: '试用期', expired: '已过期', domain: '域名不匹配',
      revoked: '已吊销', invalid: '授权无效', nokey: '无验签支持', degraded: '离线降级'
    };
    return map[mode] || mode || '—';
  }

  function badgeClass(mode) {
    if (mode === 'licensed' || mode === 'trial') return 'ok';
    if (mode === 'stale' || mode === 'grace' || mode === 'off' || mode === 'nokey') return 'warn';
    return 'bad';
  }

  var root = document.querySelector('[data-lic]');
  if (!root) return;

  var resultEl = $('[data-lic-result]', root);
  var card = $('[data-lic-card]', root);
  var cardEdition = $('[data-lic-card-edition]', root);
  var cardPill = $('[data-lic-pill]', root);
  var cardCode = $('[data-lic-card-code]', root);
  var cardDomain = $('[data-lic-card-domain]', root);
  var cardFoot = $('[data-lic-card-foot]', root);

  /* temp/card 式 3D：页面级鼠标跟随倾斜 + 流光横移 + 翻面。
     只在全站绑一次（document 级），rAF 节流；触屏 / 减弱动效用户跳过。 */
  function bindTilt() {
    if (bindTilt.bound) return;
    if (!window.matchMedia || !window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    bindTilt.bound = true;
    var raf = 0;
    document.addEventListener('mousemove', function (e) {
      if (raf) return;
      raf = requestAnimationFrame(function () {
        raf = 0;
        var w = window.innerWidth, h = window.innerHeight;
        var clamp = function (v) { return Math.max(-12, Math.min(12, v)); };
        var rx = clamp((e.clientY - h * 0.5) / 25).toFixed(2);
        var ry = clamp(-(e.clientX - w * 0.5) / 25).toFixed(2);
        var gx = (-530 + (e.clientX / w) * 500).toFixed(1);
        var cards = document.querySelectorAll('[data-lic3d]');
        for (var i = 0; i < cards.length; i++) {
          var t = cards[i].querySelector('.ecod_lic3d__tilt');
          if (t) t.style.transform = 'perspective(600px) rotateX(' + rx + 'deg) rotateY(' + ry + 'deg)';
          var g = cards[i].querySelector('.ecod_lic3d__glaze');
          if (g) g.style.transform = 'translateX(' + gx + 'px)';
        }
      });
    }, { passive: true });
  }

  /* 翻面点击不依赖 hover 守卫：触屏 / 无 hover 环境同样可用。
     两面显隐用 JS 内联 opacity 控制——CSS 规则匹配在部分渲染器不可靠，
     内联样式优先级最高，任何环境必定生效。 */
  function bindFlip() {
    if (bindFlip.bound) return;
    bindFlip.bound = true;
    document.addEventListener('click', function (e) {
      var btn = e.target.closest ? e.target.closest('[data-lic-flip]') : null;
      if (!btn) return;
      var wrap = btn.closest('[data-lic3d]');
      if (!wrap) return;
      var on = wrap.classList.toggle('is-flipped');
      var front = wrap.querySelector('.ecod_lic3d__face.is-front');
      var back = wrap.querySelector('.ecod_lic3d__face.is-back');
      if (front) {
        front.style.opacity = on ? '0' : '1';
        front.style.pointerEvents = on ? 'none' : '';
      }
      if (back) {
        back.style.opacity = on ? '1' : '0';
        back.style.pointerEvents = on ? '' : 'none';
      }
    });
  }
  bindFlip();

  function showResult(ok, msg) {
    if (!resultEl) return;
    resultEl.hidden = false;
    resultEl.className = 'ecod-license__result ' + (ok ? 'is-ok' : 'is-err');
    resultEl.textContent = msg;
  }

  function renderState(st) {
    var s = st && st.state ? st.state : (st || {});
    var mode = s.mode || '—';
    var stateCls = { licensed: 'licensed', stale: 'licensed', grace: 'licensed', trial: 'trial', off: 'off', nokey: 'off',
                     expired: 'bad', domain: 'bad', revoked: 'bad', invalid: 'bad', degraded: 'bad' };
    var pillText = modeLabel(mode);

    if (card) {
      card.className = 'ecod_lic3d__inner is-' + (stateCls[mode] || 'bad');
      if (cardEdition) cardEdition.textContent = (s.edition === 'team' ? 'TEAM' : 'PRO');
      if (cardPill) cardPill.textContent = pillText;
      var hasFile = !!s.file;
      if (cardCode) cardCode.textContent = hasFile ? (s.key || '—') : '未激活';
      if (cardDomain) cardDomain.textContent = s.host || '—';
      var foot = [];
      if (hasFile && s.expire) {
        foot.push('到期 ' + new Date(s.expire * 1000).toLocaleDateString() +
          (typeof s.days_left === 'number' && s.days_left >= 0 ? ' · 剩 ' + s.days_left + ' 天' : ''));
      } else if (hasFile) {
        foot.push('永久有效');
      } else if (mode === 'trial') {
        var left = 7;
        if (s.installed_at) left = Math.max(0, 7 - Math.floor((Date.now() / 1000 - s.installed_at) / 86400));
        foot.push('试用期剩余 ' + left + ' 天');
      }
      if (s.locked) foot.push('前台已锁定');
      if (cardFoot) cardFoot.textContent = foot.join(' · ');

      /* 翻面：版本权益 + 签名信息 */
      var PERKS = { pay: '付费阅读', member: '会员', ads: '广告', card: '卡密', shop: '商城', split: '多作者分成' };
      var perkBox = $('[data-lic-perks]', root);
      var backfoot = $('[data-lic-backfoot]', root);
      if (perkBox) {
        var mods = s.modules && typeof s.modules === 'object' ? s.modules : null;
        var list = [];
        if (mods && Object.keys(mods).length) {
          Object.keys(PERKS).forEach(function (pk) { if (mods[pk]) list.push(PERKS[pk]); });
        } else if (hasFile) {
          list = s.edition === 'team'
            ? ['付费阅读', '会员', '商城', '广告', '卡密', '多作者分成']
            : ['付费阅读', '会员', '商城', '广告', '卡密'];
        }
        perkBox.innerHTML = list.map(function (pp) { return '<span class="ecod_lic3d__perk">' + esc(pp) + '</span>'; }).join('')
          || '<span class="ecod_lic3d__perk">按授权文件下发</span>';
      }
      if (backfoot) {
        var bf = [];
        if (s.kid) bf.push('签名 ' + s.kid);
        if (s.domain) bf.push('单域授权');
        backfoot.textContent = bf.join(' · ');
      }
    }

    /* 红条 / 黄条：只有需要站长动手的状态才出现，licensed / stale / off 保持安静 */
    var banner = $('[data-lic-banner]', root);
    if (banner) {
      var warnModes = ['trial', 'grace', 'expired', 'domain', 'revoked', 'invalid', 'degraded', 'nokey'];
      var showBanner = warnModes.indexOf(mode) >= 0;
      banner.hidden = !showBanner;
      if (showBanner) {
        var bad = !!s.locked || mode === 'nokey';
        banner.className = 'ecod-license__banner ' + (bad ? 'is-bad' : 'is-warn');
        banner.innerHTML = '<b>' + esc(s.hint || modeLabel(mode)) + '</b>'
          + (s.locked ? '前台已停止访问（管理员仍可见）。输入授权码后立即恢复。' : '');
      }
    }

    /* 域名自动识别：只读展示，不需要手填 */
    var hostEl = $('[data-lic-host]', root);
    if (hostEl) hostEl.textContent = s.host || location.host.replace(/^www\./, '') || '—';

    /* 已激活（有效授权在手）→ 收起激活 / 导入入口；过期换绑等需要重新输入的态保持展开 */
    var activated = mode === 'licensed' || mode === 'stale' || mode === 'grace';
    var actBlock = $('[data-lic-activate]', root);
    var impBlock = $('[data-lic-import]', root);
    if (actBlock) actBlock.hidden = activated;
    if (impBlock) impBlock.hidden = activated;

    if (s.hint) showResult(mode === 'licensed' || mode === 'trial', s.hint);
  }

  function loadState() {
    api('license_state', 'GET').then(function (r) {
      if (r && r.ok) { renderState(r); bindTilt(); }
      else showResult(false, (r && r.error) || '读取状态失败');
    }).catch(function () { showResult(false, '读取状态失败'); });
  }

  var refreshBtn = $('[data-lic-refresh]', root);
  if (refreshBtn) refreshBtn.addEventListener('click', loadState);

  var recheckBtn = $('[data-lic-recheck]', root);
  if (recheckBtn) {
    recheckBtn.addEventListener('click', function () {
      showResult(true, '复验中…');
      api('license_recheck', 'POST', {}).then(function (r) {
        if (r && r.ok) {
          renderState(r);
          showResult(empty(r.state && r.state.last_error), r.message || '复验完成');
        } else {
          showResult(false, (r && r.error) || '复验失败');
        }
      }).catch(function () { showResult(false, '复验失败'); });
    });
  }

  function empty(v) { return !v; }

  var actBtn = $('[data-lic-activate-btn]', root);
  if (actBtn) {
    actBtn.addEventListener('click', function () {
      var key = ($('[data-lic-key]', root).value || '').trim();
      if (!key) { showResult(false, '请输入授权码（形如 ECD-XXXX-XXXX-XXXX，在会员中心「我的授权」获取）'); return; }
      actBtn.disabled = true;
      actBtn.dataset.licLabel = actBtn.textContent;
      actBtn.textContent = '激活中…';
      showResult(true, '正在向作者端验证授权码…');
      /* 绑定域名由后端自动识别当前站点，无需填写 */
      api('license_activate', 'POST', { key: key }).then(function (r) {
        actBtn.disabled = false;
        actBtn.textContent = actBtn.dataset.licLabel || '立即激活';
        if (r && r.ok) {
          var keyInput = $('[data-lic-key]', root);
          if (keyInput) keyInput.value = '';
          showResult(true, r.message || '激活成功，前台已恢复访问');
          loadState();
        } else {
          showResult(false, (r && (r.message || r.error)) || '激活失败，请检查授权码后重试');
        }
      }).catch(function () {
        actBtn.disabled = false;
        actBtn.textContent = actBtn.dataset.licLabel || '立即激活';
        showResult(false, '激活失败：无法连接授权服务器，请稍后重试或使用离线导入');
      });
    });
    /* 回车直接激活 */
    var keyInput = $('[data-lic-key]', root);
    if (keyInput) keyInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); actBtn.click(); }
    });
  }

  var impBtn = $('[data-lic-import-btn]', root);
  if (impBtn) {
    impBtn.addEventListener('click', function () {
      var file = ($('[data-lic-file]', root).value || '').trim();
      if (!file) { showResult(false, '请粘贴授权文件（ECODLIC1.… 整串）'); return; }
      impBtn.disabled = true;
      impBtn.dataset.licLabel = impBtn.textContent;
      impBtn.textContent = '导入中…';
      showResult(true, '正在本地验签…');
      api('license_import', 'POST', { file: file }).then(function (r) {
        impBtn.disabled = false;
        impBtn.textContent = impBtn.dataset.licLabel || '导入';
        if (r && r.ok) {
          showResult(true, r.message || '导入成功，前台已恢复访问');
          loadState();
        } else {
          showResult(false, (r && (r.message || r.error)) || '导入失败：授权文件校验未通过');
        }
      }).catch(function () {
        impBtn.disabled = false;
        impBtn.textContent = impBtn.dataset.licLabel || '导入';
        showResult(false, '导入失败，请重试');
      });
    });
  }

  loadState();
})();
