/**
 * Ecod Theme · 流光背景（Lightfall）封装
 *
 * 后台「外观 → 视觉与动效 → 站点背景 = 流光」启用时，页脚才下发本文件
 * （与 vendor/lightfall.js 一起），避免不启用时的额外请求。
 *
 * 职责：
 *   1. 建全屏固定图层（body 首个子元素，PJAX 换页不重建、不闪烁）；
 *   2. 深浅色自动切换：浅色走 lightMode（白底彩色光），深色走发光模式；
 *   3. 移动端降级：dpr 锁 1、层数封顶 2、关掉指针交互（触屏无 hover）；
 *   4. 上下文丢失后自动重建；不支持 WebGL2 时静默移除图层（露出纯色底）。
 *
 * 参数来自 window.ECOD.lightfall（见 public/include.php），
 * 后续用 fx.set() 热更新，不重建 GL 资源。
 *
 * Copyright (c) 2026 Ecod. All Rights Reserved. · https://ecod.cc
 */
(function () {
    'use strict';

    var CFG = window.ECOD || {};
    var conf = CFG.lightfall;
    var engine = window.EcodLightfall;

    if (!conf || !conf.enabled || !engine || !window.WebGL2RenderingContext) {
        return;
    }

    var root = document.documentElement;
    var layer = null;
    var fx = null;
    var phoneQuery = window.matchMedia ? window.matchMedia('(max-width: 767px)') : null;

    /* 光色兜底：后台留空 / 非法值统一退回主题主色系 */
    function palette() {
        var list = conf.colors && conf.colors.length ? conf.colors : ['#4f6ef2', '#8b5cf6'];
        return list.slice(0, 3);
    }

    function isPhone() {
        return !!(phoneQuery && phoneQuery.matches);
    }

    function opts() {
        var dark = root.getAttribute('data-theme') === 'dark';
        var phone = isPhone();
        var colors = palette();
        var layers = Math.max(1, Math.min(16, parseInt(conf.layers, 10) || 2));
        return {
            colors: colors,
            speed: typeof conf.speed === 'number' ? conf.speed : 0.3,
            streakCount: phone ? Math.min(layers, 2) : layers,
            opacity: typeof conf.opacity === 'number' ? conf.opacity : 0.7,
            /* 触屏没有 hover，指针交互只会白烧帧 */
            mouseInteraction: !!conf.mouse && !phone,
            lightMode: !dark,
            /* 深色用首个光色做隧道顶部的柔光；浅色模式下该项不参与合成 */
            backgroundColor: dark ? colors[0] : '#ffffff',
            backgroundGlow: dark ? 0.12 : 0,
            /* 移动端像素密度锁 1，省一半填充率 */
            dpr: phone ? 1 : null
        };
    }

    function mount() {
        var base = opts();
        base.onContextRestored = function () {
            /* 上下文恢复后旧资源已失效，整体重建 */
            window.setTimeout(function () {
                if (fx) {
                    fx.destroy();
                    fx = null;
                }
                mount();
            }, 0);
        };
        fx = engine.create(layer, base);
        if (!fx) {
            if (layer && layer.parentNode) {
                layer.parentNode.removeChild(layer);
                layer = null;
            }
            return;
        }
        document.body.classList.add('ecod-has-lightfall');
    }

    function sync() {
        if (fx) {
            fx.set(opts());
        }
    }

    function boot() {
        if (!document.body) {
            return;
        }
        layer = document.createElement('div');
        layer.className = 'ecod_lightfall';
        document.body.insertBefore(layer, document.body.firstChild);
        mount();

        /* 深浅色切换：主题按钮改 data-theme，直接热更新 uniforms */
        if (window.MutationObserver) {
            new MutationObserver(sync).observe(root, {
                attributes: true,
                attributeFilter: ['data-theme']
            });
        }
        /* 跨 768px 时重算层数与 dpr */
        if (phoneQuery) {
            if (phoneQuery.addEventListener) {
                phoneQuery.addEventListener('change', sync);
            } else if (phoneQuery.addListener) {
                phoneQuery.addListener(sync);
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
