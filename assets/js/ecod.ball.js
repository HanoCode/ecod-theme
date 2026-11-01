/*! Ecod Theme · Grok Ball 表情球封装
 *
 * 引擎：assets/js/vendor/grok-ball.js（Grok Ball standalone engine，MIT License，
 * 附带 grok-ball.LICENSE）。本文件不复制引擎能力，只做三件事：
 *   1. 令牌取色——球身取 --ecod-primary、眼球取 --ecod-surface，
 *      深浅色与后台品牌色实时联动（含 syncBrandLive 的 style 内联改动）；
 *   2. 共享注视——一个 window pointermove 驱动所有 follow 实例的 setGaze，
 *      目标坐标按各球中心换算，引擎内部自带弹簧平滑与 ±1 截断；
 *   3. 实例登记——celebrateAll / sweep 供签到庆祝与 PJAX 换页清理复用。
 *
 * prefers-reduced-motion 时不创建任何实例（create 返回 null），调用方必须判空。
 * 加载由 ecod.global.js 的 ensureBall() 按需注入（engine → 本文件，顺序执行）。
 */
(function () {
    'use strict';
    if (window.EcodBall) return;

    var reduced = window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var insts = [];
    var pointerBound = false;
    /* 同屏跟随鼠标的球上限：超限的球只播 idle 动画 */
    var FOLLOW_MAX = 3;
    function followCount() {
        var n = 0;
        insts.forEach(function (it) { if (it.follow) n++; });
        return n;
    }

    function cssVar(name, fallback) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(name);
        v = (v || '').trim();
        return v || fallback;
    }

    function hexRgb(hex) {
        var h = String(hex || '').replace('#', '');
        if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
        var n = parseInt(h, 16);
        if (isNaN(n)) return [128, 128, 128];
        return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
    }

    /* WCAG 相对亮度：品牌色与眼球色亮度差过小（如品牌色是白/浅色系）时
       眼球会隐形，此时放弃表面色改用硬色兜底 */
    function luminance(rgb) {
        var l = rgb.map(function (v) {
            v /= 255;
            return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
        });
        return 0.2126 * l[0] + 0.7152 * l[1] + 0.0722 * l[2];
    }

    /* 球身用品牌主色；眼球默认用页面表面色——两枚 token 在深浅色与
       自定义品牌色下都保持「有对比」语义，不用另设一套球配色 */
    function themeColors() {
        var body = cssVar('--ecod-primary', '#4f6ef2');
        var eyes = cssVar('--ecod-surface', '#ffffff');
        var lb = luminance(hexRgb(body));
        var le = luminance(hexRgb(eyes));
        if (Math.abs(lb - le) < 0.25) {
            eyes = lb > 0.4 ? '#1A1A1A' : '#ffffff';
        }
        return { body: body, eyes: eyes };
    }

    function bindPointer() {
        if (pointerBound) return;
        pointerBound = true;
        window.addEventListener('pointermove', function (e) {
            for (var i = 0; i < insts.length; i++) {
                var it = insts[i];
                if (!it.follow || !it.el.isConnected) continue;
                var r = it.el.getBoundingClientRect();
                if (!r.width) continue;
                /* 相对球心的偏移比例直接交给引擎（内部截断到 ±1 并弹簧平滑） */
                it.eng.setGaze(
                    (e.clientX - (r.left + r.width / 2)) / (r.width / 2),
                    (e.clientY - (r.top + r.height / 2)) / (r.height / 2)
                );
            }
        }, { passive: true });
    }

    /* 深浅色切换（data-theme）触发换色 + 惊讶小彩蛋；后台品牌色实时预览
       （style 内联）只换色不惊讶 */
    if (!reduced && window.MutationObserver) {
        new MutationObserver(function (muts) {
            var themeChanged = false;
            muts.forEach(function (m) {
                if (m.attributeName === 'data-theme') themeChanged = true;
            });
            var c = themeColors();
            insts.forEach(function (it) {
                /* _theme 是引擎的私有字段，无公开换色 API；引擎每帧合成姿态时
                   会用它覆盖球身色，直接替换即可，无需销毁重建 */
                if (it.eng._theme) it.eng._theme = { body: c.body, eyes: c.eyes };
            });
            if (themeChanged && insts.length) flashAll('13', 1600);
        }).observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-theme', 'style']
        });
    }

    /* 后台标签页零开销：隐藏时停掉共享 rAF，回来恢复并视为「用户回来了」 */
    if (!reduced) {
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                insts.forEach(function (it) { it.eng.setActive(false); });
            } else {
                insts.forEach(function (it) {
                    it.eng.setActive(true);
                    it.eng.resetIdle();
                });
            }
        });
    }

    /* 全员短表情（点赞/复制成功蹭球、主题切换惊讶）：正在表演的球不重复打断 */
    function flashAll(id, ms) {
        insts.slice().forEach(function (it) {
            if (it._timer) { clearTimeout(it._timer); it._timer = 0; }
            it.eng.setEmotion(id);
            it._timer = setTimeout(function () {
                it._timer = 0;
                if (it.el.isConnected) it.eng.setEmotion(it.base);
            }, ms || 2000);
        });
    }

    /**
     * @param {Element|string} mount 容器（球 SVG 会填满它）
     * @param {Object} opts { emotion, size 由 CSS 控制, follow, idle:{standbyAfter,sleepAfter} }
     * @returns {Object|null} 句柄 { set, flash, celebrate, spin, burst, resetIdle, destroy }
     */
    function create(mount, opts) {
        opts = opts || {};
        if (reduced || !window.GrokBall || typeof window.GrokBall.create !== 'function') return null;
        var el = typeof mount === 'string' ? document.querySelector(mount) : mount;
        if (!el) return null;
        var c = themeColors();
        var eng = window.GrokBall.create(el, {
            emotion: opts.emotion || '02',
            color: c.body,
            eyeColor: c.eyes,
            /* lite：砍 orbit 环带 / zzz / 彩带粒子等重渲染层——小尺寸球（≤36px）
               默认开启，同页多球时显著省每帧开销；大球可显式传 lite:false */
            lite: opts.lite != null ? !!opts.lite : opts.size != null && opts.size <= 36,
            idle: opts.idle || null
        });
        if (!eng) return null;
        var it = {
            el: el,
            eng: eng,
            follow: opts.follow !== false && followCount() < FOLLOW_MAX,
            base: opts.emotion || '02',
            _timer: 0
        };
        insts.push(it);
        if (it.follow) bindPointer();

        var h = {
            el: el,
            set: function (id) {
                if (it._timer) { clearTimeout(it._timer); it._timer = 0; }
                eng.setEmotion(id);
                return h;
            },
            /* 临时表情：ms 后回 base（发送中→完成/出错的短情绪弧线） */
            flash: function (id, ms) {
                h.set(id);
                it._timer = setTimeout(function () {
                    it._timer = 0;
                    if (eng.emotionId === id) eng.setEmotion(it.base);
                }, ms || 2400);
                return h;
            },
            celebrate: function (ms) {
                return h.flash('33', ms || 2800);
            },
            /* 是否正在表演短表情（抽奖保底联动等用它避免覆盖庆祝弧线） */
            holding: function () {
                return !!it._timer;
            },
            /* 用户重新活动：从打瞌睡/睡眠里唤醒（01 唤醒短弧线回 base） */
            wake: function () {
                eng.resetIdle();
                var cur = eng.emotionId;
                if (cur === '00' || cur === '04' || cur === '06') {
                    eng.setEmotion('01');
                    setTimeout(function () {
                        if (eng.emotionId === '01') eng.setEmotion(it.base);
                    }, 1200);
                }
                return h;
            },
            spin: function (turns) { eng.spin(turns || 2); return h; },
            burst: function (n) { eng.burst(n || 24); return h; },
            resetIdle: function () { eng.resetIdle(); return h; },
            destroy: function () {
                var i = insts.indexOf(it);
                if (i > -1) insts.splice(i, 1);
                if (it._timer) clearTimeout(it._timer);
                eng.destroy();
            }
        };
        return h;
    }

    window.EcodBall = {
        create: create,
        reduced: reduced,
        count: function () { return insts.length; },
        /* 站内喜事（签到成功等）：所有活着的球一起庆祝 */
        celebrateAll: function (ms) {
            flashAll('33', ms);
        },
        /* 全员短表情：点赞/收藏/复制成功蹭球、深浅色切换惊讶 */
        flashAll: function (id, ms) {
            flashAll(id, ms);
        },
        /* PJAX 换页后清理：宿主已从文档里摘除的实例就地销毁 */
        sweep: function () {
            for (var i = insts.length - 1; i >= 0; i--) {
                if (!insts[i].el.isConnected) {
                    if (insts[i]._timer) clearTimeout(insts[i]._timer);
                    insts[i].eng.destroy();
                    insts.splice(i, 1);
                }
            }
        }
    };
})();
