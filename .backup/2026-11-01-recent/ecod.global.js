/**
 * Ecod Theme · 全局脚本
 *
 * 模块：主题切换 / 抽屉 / 导航滚动 / 回到顶部 / 阅读进度 / 目录
 *      内容增强 / 点赞收藏 / 轮播 / 无刷新跳转
 *
 * Copyright (c) 2026 Ecod. All Rights Reserved. · https://ecod.cc
 */
(function () {
    'use strict';

    var Ecod = window.Ecod = window.Ecod || {};
    var CFG = window.ECOD || {};
    var root = document.documentElement;

    /* ---------- 控制台主题信息 ---------- */
    console.log(
        '\n %c Ecod Theme %c v' + (CFG.version || '?') + ' %c https://ecod.cc \n',
        'color:#fff;background:#2f6bff;padding:3px 8px;font-size:12px;border-radius:2px 0 0 2px;',
        'color:#2f6bff;background:#f5f6f9;padding:3px 8px;font-size:12px;',
        'color:#8a93a6;background:transparent;padding:3px 0;font-size:12px;'
    );

    /* ---------- 内联图标（自动生成区，勿手改） ---------- */
    /* 由 build/icons.mjs 依 build/icons.map.json 生成（css.gg 填充风格）；增删图标改映射表后重跑 node build/icons.mjs */
    /* icons:generated:start */
    var ICON_PATHS = {
        'moon': '<path fill-rule="evenodd" clip-rule="evenodd" d="M12.2256 2.00253C9.59172 1.94346 6.93894 2.9189 4.92893 4.92891C1.02369 8.83415 1.02369 15.1658 4.92893 19.071C8.83418 22.9763 15.1658 22.9763 19.0711 19.071C21.0811 17.061 22.0565 14.4082 21.9975 11.7743C21.9796 10.9772 21.8669 10.1818 21.6595 9.40643C21.0933 9.9488 20.5078 10.4276 19.9163 10.8425C18.5649 11.7906 17.1826 12.4053 15.9301 12.6837C14.0241 13.1072 12.7156 12.7156 12 12C11.2844 11.2844 10.8928 9.97588 11.3163 8.0699C11.5947 6.81738 12.2094 5.43511 13.1575 4.08368C13.5724 3.49221 14.0512 2.90664 14.5935 2.34046C13.8182 2.13305 13.0228 2.02041 12.2256 2.00253ZM17.6569 17.6568C18.9081 16.4056 19.6582 14.8431 19.9072 13.2186C16.3611 15.2643 12.638 15.4664 10.5858 13.4142C8.53361 11.362 8.73568 7.63895 10.7814 4.09281C9.1569 4.34184 7.59434 5.09193 6.34315 6.34313C3.21895 9.46732 3.21895 14.5326 6.34315 17.6568C9.46734 20.781 14.5327 20.781 17.6569 17.6568Z" />',
        'sun': '<path fill-rule="evenodd" clip-rule="evenodd" d="M12 16C14.2091 16 16 14.2091 16 12C16 9.79086 14.2091 8 12 8C9.79086 8 8 9.79086 8 12C8 14.2091 9.79086 16 12 16ZM12 18C15.3137 18 18 15.3137 18 12C18 8.68629 15.3137 6 12 6C8.68629 6 6 8.68629 6 12C6 15.3137 8.68629 18 12 18Z" /><path fill-rule="evenodd" clip-rule="evenodd" d="M11 0H13V4.06189C12.6724 4.02104 12.3387 4 12 4C11.6613 4 11.3276 4.02104 11 4.06189V0ZM7.0943 5.68018L4.22173 2.80761L2.80752 4.22183L5.6801 7.09441C6.09071 6.56618 6.56608 6.0908 7.0943 5.68018ZM4.06189 11H0V13H4.06189C4.02104 12.6724 4 12.3387 4 12C4 11.6613 4.02104 11.3276 4.06189 11ZM5.6801 16.9056L2.80751 19.7782L4.22173 21.1924L7.0943 18.3198C6.56608 17.9092 6.09071 17.4338 5.6801 16.9056ZM11 19.9381V24H13V19.9381C12.6724 19.979 12.3387 20 12 20C11.6613 20 11.3276 19.979 11 19.9381ZM16.9056 18.3199L19.7781 21.1924L21.1923 19.7782L18.3198 16.9057C17.9092 17.4339 17.4338 17.9093 16.9056 18.3199ZM19.9381 13H24V11H19.9381C19.979 11.3276 20 11.6613 20 12C20 12.3387 19.979 12.6724 19.9381 13ZM18.3198 7.0943L21.1923 4.22183L19.7781 2.80762L16.9056 5.6801C17.4338 6.09071 17.9092 6.56608 18.3198 7.0943Z" />',
        'heart': '<path fill-rule="evenodd" clip-rule="evenodd" d="M12.0122 5.57169L10.9252 4.48469C8.77734 2.33681 5.29493 2.33681 3.14705 4.48469C0.999162 6.63258 0.999162 10.115 3.14705 12.2629L11.9859 21.1017L11.9877 21.0999L12.014 21.1262L20.8528 12.2874C23.0007 10.1395 23.0007 6.65711 20.8528 4.50923C18.705 2.36134 15.2226 2.36134 13.0747 4.50923L12.0122 5.57169ZM11.9877 18.2715L16.9239 13.3352L18.3747 11.9342L18.3762 11.9356L19.4386 10.8732C20.8055 9.50635 20.8055 7.29028 19.4386 5.92344C18.0718 4.55661 15.8557 4.55661 14.4889 5.92344L12.0133 8.39904L12.006 8.3918L12.005 8.39287L9.51101 5.89891C8.14417 4.53207 5.92809 4.53207 4.56126 5.89891C3.19442 7.26574 3.19442 9.48182 4.56126 10.8487L7.10068 13.3881L7.10248 13.3863L11.9877 18.2715Z" />',
        'star': '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>',
        'check': '<path d="M10.5858 13.4142L7.75735 10.5858L6.34314 12L10.5858 16.2427L17.6568 9.1716L16.2426 7.75739L10.5858 13.4142Z" />',
        'copy': '<path d="M13 7H7V5H13V7Z" /><path d="M13 11H7V9H13V11Z" /><path d="M7 15H13V13H7V15Z" /><path fill-rule="evenodd" clip-rule="evenodd" d="M3 19V1H17V5H21V23H7V19H3ZM15 17V3H5V17H15ZM17 7V19H9V21H19V7H17Z" />',
        'link': '<path d="M14.8284 12L16.2426 13.4142L19.071 10.5858C20.6331 9.02365 20.6331 6.49099 19.071 4.9289C17.509 3.3668 14.9763 3.3668 13.4142 4.9289L10.5858 7.75732L12 9.17154L14.8284 6.34311C15.6095 5.56206 16.8758 5.56206 17.6568 6.34311C18.4379 7.12416 18.4379 8.39049 17.6568 9.17154L14.8284 12Z" /><path d="M12 14.8285L13.4142 16.2427L10.5858 19.0711C9.02372 20.6332 6.49106 20.6332 4.92896 19.0711C3.36686 17.509 3.36686 14.9764 4.92896 13.4143L7.75739 10.5858L9.1716 12L6.34317 14.8285C5.56212 15.6095 5.56212 16.8758 6.34317 17.6569C7.12422 18.4379 8.39055 18.4379 9.1716 17.6569L12 14.8285Z" /><path d="M14.8285 10.5857C15.219 10.1952 15.219 9.56199 14.8285 9.17147C14.4379 8.78094 13.8048 8.78094 13.4142 9.17147L9.1716 13.4141C8.78107 13.8046 8.78107 14.4378 9.1716 14.8283C9.56212 15.2188 10.1953 15.2188 10.5858 14.8283L14.8285 10.5857Z" />',
        'trash': '<path fill-rule="evenodd" clip-rule="evenodd" d="M17 5V4C17 2.89543 16.1046 2 15 2H9C7.89543 2 7 2.89543 7 4V5H4C3.44772 5 3 5.44772 3 6C3 6.55228 3.44772 7 4 7H5V18C5 19.6569 6.34315 21 8 21H16C17.6569 21 19 19.6569 19 18V7H20C20.5523 7 21 6.55228 21 6C21 5.44772 20.5523 5 20 5H17ZM15 4H9V5H15V4ZM17 7H7V18C7 18.5523 7.44772 19 8 19H16C16.5523 19 17 18.5523 17 18V7Z" /><path d="M9 9H11V17H9V9Z" /><path d="M13 9H15V17H13V9Z" />',
        'play': '<path d="M15 12.3301L9 16.6603L9 8L15 12.3301Z" />',
        'pause': '<path d="M8 5h3.2v14H8zM12.8 5H16v14h-3.2z"/>',
        'volume': '<path d="M24 12C24 16.4183 20.4183 20 16 20V18C19.3137 18 22 15.3137 22 12C22 8.68629 19.3137 6 16 6V4C20.4183 4 24 7.58172 24 12Z" /><path d="M20 12C20 14.2091 18.2091 16 16 16V14C17.1046 14 18 13.1046 18 12C18 10.8954 17.1046 10 16 10V8C18.2091 8 20 9.79086 20 12Z" /><path fill-rule="evenodd" clip-rule="evenodd" d="M9 16L15 20V4L9 8H5C2.79086 8 1 9.79086 1 12C1 14.2091 2.79086 16 5 16H9ZM5 10H9L13 7.5V16.5L9 14H5C3.89543 14 3 13.1046 3 12C3 10.8954 3.89543 10 5 10Z" />',
        'search': '<path fill-rule="evenodd" clip-rule="evenodd" d="M18.319 14.4326C20.7628 11.2941 20.542 6.75347 17.6569 3.86829C14.5327 0.744098 9.46734 0.744098 6.34315 3.86829C3.21895 6.99249 3.21895 12.0578 6.34315 15.182C9.22833 18.0672 13.769 18.2879 16.9075 15.8442C16.921 15.8595 16.9351 15.8745 16.9497 15.8891L21.1924 20.1317C21.5829 20.5223 22.2161 20.5223 22.6066 20.1317C22.9971 19.7412 22.9971 19.1081 22.6066 18.7175L18.364 14.4749C18.3493 14.4603 18.3343 14.4462 18.319 14.4326ZM16.2426 5.28251C18.5858 7.62565 18.5858 11.4246 16.2426 13.7678C13.8995 16.1109 10.1005 16.1109 7.75736 13.7678C5.41421 11.4246 5.41421 7.62565 7.75736 5.28251C10.1005 2.93936 13.8995 2.93936 16.2426 5.28251Z" />',
        'file': '<path fill-rule="evenodd" clip-rule="evenodd" d="M3 5C3 3.34315 4.34315 2 6 2H14C17.866 2 21 5.13401 21 9V19C21 20.6569 19.6569 22 18 22H6C4.34315 22 3 20.6569 3 19V5ZM13 4H6C5.44772 4 5 4.44772 5 5V19C5 19.5523 5.44772 20 6 20H18C18.5523 20 19 19.5523 19 19V9H13V4ZM18.584 7C17.9413 5.52906 16.6113 4.4271 15 4.10002V7H18.584Z" />',
        'list': '<path fill-rule="evenodd" clip-rule="evenodd" d="M20 4H4C3.44771 4 3 4.44772 3 5V19C3 19.5523 3.44772 20 4 20H20C20.5523 20 21 19.5523 21 19V5C21 4.44771 20.5523 4 20 4ZM4 2C2.34315 2 1 3.34315 1 5V19C1 20.6569 2.34315 22 4 22H20C21.6569 22 23 20.6569 23 19V5C23 3.34315 21.6569 2 20 2H4ZM6 7H8V9H6V7ZM11 7C10.4477 7 10 7.44772 10 8C10 8.55228 10.4477 9 11 9H17C17.5523 9 18 8.55228 18 8C18 7.44772 17.5523 7 17 7H11ZM8 11H6V13H8V11ZM10 12C10 11.4477 10.4477 11 11 11H17C17.5523 11 18 11.4477 18 12C18 12.5523 17.5523 13 17 13H11C10.4477 13 10 12.5523 10 12ZM8 15H6V17H8V15ZM10 16C10 15.4477 10.4477 15 11 15H17C17.5523 15 18 15.4477 18 16C18 16.5523 17.5523 17 17 17H11C10.4477 17 10 16.5523 10 16Z" />',
        'external': '<path d="M15.6396 7.02527H12.0181V5.02527H19.0181V12.0253H17.0181V8.47528L12.1042 13.3892L10.6899 11.975L15.6396 7.02527Z" /><path d="M10.9819 6.97473H4.98193V18.9747H16.9819V12.9747H14.9819V16.9747H6.98193V8.97473H10.9819V6.97473Z" />',
        'x': '<path d="M6.2253 4.81108C5.83477 4.42056 5.20161 4.42056 4.81108 4.81108C4.42056 5.20161 4.42056 5.83477 4.81108 6.2253L10.5858 12L4.81114 17.7747C4.42062 18.1652 4.42062 18.7984 4.81114 19.1889C5.20167 19.5794 5.83483 19.5794 6.22535 19.1889L12 13.4142L17.7747 19.1889C18.1652 19.5794 18.7984 19.5794 19.1889 19.1889C19.5794 18.7984 19.5794 18.1652 19.1889 17.7747L13.4142 12L19.189 6.2253C19.5795 5.83477 19.5795 5.20161 19.189 4.81108C18.7985 4.42056 18.1653 4.42056 17.7748 4.81108L12 10.5858L6.2253 4.81108Z" />',
        'clock': '<path d="M9 7H11V12H16V14H9V7Z" /><path fill-rule="evenodd" clip-rule="evenodd" d="M22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12ZM20 12C20 16.4183 16.4183 20 12 20C7.58172 20 4 16.4183 4 12C4 7.58172 7.58172 4 12 4C16.4183 4 20 7.58172 20 12Z" />',
        'inbox': '<path fill-rule="evenodd" clip-rule="evenodd" d="M2 5C2 3.34315 3.34315 2 5 2H19C20.6569 2 22 3.34315 22 5V20C22 21.1046 21.1046 22 20 22H8.1477C8.09893 22.0036 8.04968 22.0054 8 22.0054H4C2.89543 22.0054 2 21.1099 2 20.0054V5ZM5 4H19C19.5523 4 20 4.44771 20 5V14H16C14.8954 14 14 14.8954 14 16V17H10V16.0054C10 14.9008 9.10457 14.0054 8 14.0054H4V5C4 4.44772 4.44771 4 5 4Z" />',
        'user': '<path fill-rule="evenodd" clip-rule="evenodd" d="M16 7C16 9.20914 14.2091 11 12 11C9.79086 11 8 9.20914 8 7C8 4.79086 9.79086 3 12 3C14.2091 3 16 4.79086 16 7ZM14 7C14 8.10457 13.1046 9 12 9C10.8954 9 10 8.10457 10 7C10 5.89543 10.8954 5 12 5C13.1046 5 14 5.89543 14 7Z" /><path d="M16 15C16 14.4477 15.5523 14 15 14H9C8.44772 14 8 14.4477 8 15V21H6V15C6 13.3431 7.34315 12 9 12H15C16.6569 12 18 13.3431 18 15V21H16V15Z" />',
        'alert': '<path d="M12 6C12.5523 6 13 6.44772 13 7V13C13 13.5523 12.5523 14 12 14C11.4477 14 11 13.5523 11 13V7C11 6.44772 11.4477 6 12 6Z" /><path d="M12 16C11.4477 16 11 16.4477 11 17C11 17.5523 11.4477 18 12 18C12.5523 18 13 17.5523 13 17C13 16.4477 12.5523 16 12 16Z" /><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2ZM4 12C4 16.4183 7.58172 20 12 20C16.4183 20 20 16.4183 20 12C20 7.58172 16.4183 4 12 4C7.58172 4 4 7.58172 4 12Z" />',
        'left': '<path d="M16.2426 6.34317L14.8284 4.92896L7.75739 12L14.8285 19.0711L16.2427 17.6569L10.5858 12L16.2426 6.34317Z" />',
        'right': '<path d="M10.5858 6.34317L12 4.92896L19.0711 12L12 19.0711L10.5858 17.6569L16.2427 12L10.5858 6.34317Z" />',
        'download': '<path d="M11 5C11 4.44772 11.4477 4 12 4C12.5523 4 13 4.44772 13 5V12.1578L16.2428 8.91501L17.657 10.3292L12.0001 15.9861L6.34326 10.3292L7.75748 8.91501L11 12.1575V5Z" /><path d="M4 14H6V18H18V14H20V18C20 19.1046 19.1046 20 18 20H6C4.89543 20 4 19.1046 4 18V14Z" />',
        'zap': '<path d="M9 21.5L17.5 13L13 10L15 2.5L6.5 11L11 14L9 21.5Z" />',
        'info': '<path d="M11 10.9794C11 10.4271 11.4477 9.97937 12 9.97937C12.5523 9.97937 13 10.4271 13 10.9794V16.9794C13 17.5317 12.5523 17.9794 12 17.9794C11.4477 17.9794 11 17.5317 11 16.9794V10.9794Z" /><path d="M12 6.05115C11.4477 6.05115 11 6.49886 11 7.05115C11 7.60343 11.4477 8.05115 12 8.05115C12.5523 8.05115 13 7.60343 13 7.05115C13 6.49886 12.5523 6.05115 12 6.05115Z" /><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2ZM4 12C4 16.4183 7.58172 20 12 20C16.4183 20 20 16.4183 20 12C20 7.58172 16.4183 4 12 4C7.58172 4 4 7.58172 4 12Z" />',
        'check-circle': '<path fill-rule="evenodd" clip-rule="evenodd" d="M12 2c5.523 0 10 4.477 10 10s-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2zm0 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16z"/><path d="M16.08 9.1l1.34 1.48-5.9 5.35-3.02-3.33 1.48-1.34 1.68 1.85z"/>',
        'x-circle': '<path fill-rule="evenodd" clip-rule="evenodd" d="M12 2c5.523 0 10 4.477 10 10s-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2zm0 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16z"/><path d="M13.414 12l2.121 2.121-1.414 1.415L12 13.414l-2.121 2.122-1.415-1.415L10.586 12 8.464 9.879 9.88 8.464 12 10.586l2.121-2.122 1.414 1.415z"/>',
    };
    /* icons:generated:end */

    Ecod.icon = function (name, size) {
        var p = ICON_PATHS[name];
        if (!p) return '';
        size = size || 16;
        return '<svg class="ecod_icon" width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' + p + '</svg>';
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
        /* 复制成功蹭球：活着的球满意一下（没有球则零开销） */
        function finish() {
            Ecod.ballFlashAll('19', 1200);
            if (done) done();
        }
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
            if (ok) finish();
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(finish, fallback);
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

    /* ---------- 轻提示 toast：统一的非阻断反馈（替代 alert / 散落的成功提示） ----------
       Ecod.toast(msg, type, dur) —— 旧签名兼容，type: 'ok'（默认）| 'bad' | 'info'
       Ecod.toast(msg, opts) —— opts: { type:'ok|bad|warn|info', dur,
           action: { label, href? , onClick? } }（动作药丸，可跳转或回调）；
       带 action 的提示驻留更久，悬停暂停倒计时，点气泡任意处即可提前关掉 */
    var toastWrap = null;

    Ecod.toast = function (msg, type, dur) {
        var o = (type && typeof type === 'object') ? type : { type: type, dur: dur };
        var kind = o.type || 'ok';
        if (kind !== 'ok' && kind !== 'bad' && kind !== 'warn' && kind !== 'info') kind = 'ok';
        if (!toastWrap) {
            toastWrap = document.createElement('div');
            toastWrap.className = 'ecod_toasts';
            toastWrap.setAttribute('aria-live', 'polite');
            document.body.appendChild(toastWrap);
        }
        var t = document.createElement('div');
        t.className = 'ecod_toast' + (kind !== 'ok' ? ' ecod_toast--' + kind : '');
        var icon = kind === 'bad' ? Ecod.icon('x-circle', 15)
            : kind === 'warn' ? Ecod.icon('alert', 15)
            : kind === 'info' ? Ecod.icon('info', 15)
            : Ecod.icon('check-circle', 15);
        t.innerHTML = icon + '<span></span>' + (o.action ? '<button type="button" class="ecod_toast__act"></button>' : '');
        t.querySelector('span').textContent = msg;

        var gone = false;
        var timer = null;
        function dismiss() {
            if (gone) return;
            gone = true;
            if (timer) window.clearTimeout(timer);
            t.classList.remove('is-in');
            t.classList.add('is-out');
            window.setTimeout(function () { t.remove(); }, 240);
        }
        function arm() {
            if (timer) window.clearTimeout(timer);
            /* 有动作的提示需要阅读与点击时间，默认驻留延长 */
            timer = window.setTimeout(dismiss, o.dur || (o.action ? 6000 : 2600));
        }
        var act = t.querySelector('.ecod_toast__act');
        if (act) {
            act.textContent = o.action.label || '查看';
            act.addEventListener('click', function (e) {
                e.stopPropagation();
                dismiss();
                if (o.action.onClick) o.action.onClick();
                else if (o.action.href) window.location.href = o.action.href;
            });
        }
        t.addEventListener('click', dismiss);
        /* 悬停暂停倒计时（触屏无悬停即自动消失，不受影响） */
        t.addEventListener('pointerenter', function () { if (timer) window.clearTimeout(timer); });
        t.addEventListener('pointerleave', arm);
        toastWrap.appendChild(t);
        window.requestAnimationFrame(function () { t.classList.add('is-in'); });
        arm();
    };

    /* ---------- 确认弹窗：替代 window.confirm 的统一二次确认 ----------
       Ecod.confirm({ title, text, ok, cancel, tone }) → Promise<boolean>
       tone: 'danger'（默认，红色确认键，用于删除/关闭等不可逆操作）| 'primary'；
       Esc / 遮罩 / 取消键 → false。复用模态原语：焦点陷阱、滚动锁、焦点还原。
       同一时刻允许多层（确认弹窗之上还能叠裁剪窗等），走 dialogStack。 */
    Ecod.confirm = function (opts) {
        opts = opts || {};
        return new Promise(function (resolve) {
            var settled = false;
            function done(v) {
                if (settled) return;
                settled = true;
                resolve(v);
            }
            var tone = opts.tone === 'primary' ? 'primary' : 'danger';
            var wrap = document.createElement('div');
            wrap.className = 'ecod_cdlg';
            wrap.innerHTML = '<div class="ecod_cdlg__mask" data-ecod-cdlg-mask></div>'
                + '<div class="ecod_cdlg__card" role="dialog" aria-modal="true">'
                + '<div class="ecod_cdlg__badge ecod_cdlg__badge--' + tone + '" aria-hidden="true">'
                + Ecod.icon(tone === 'primary' ? 'info' : 'alert', 20) + '</div>'
                + '<h3 class="ecod_cdlg__title"></h3>'
                + '<div class="ecod_cdlg__ops">'
                + '<button type="button" class="ecod_cdlg__btn" data-ecod-cdlg-cancel></button>'
                + '<button type="button" class="ecod_cdlg__btn ecod_cdlg__btn--' + tone + '" data-ecod-cdlg-ok></button>'
                + '</div></div>';
            wrap.querySelector('.ecod_cdlg__title').textContent = opts.title || '确认执行这个操作？';
            if (opts.text) {
                var p = document.createElement('p');
                p.className = 'ecod_cdlg__text';
                p.textContent = opts.text;
                wrap.querySelector('.ecod_cdlg__title').after(p);
            }
            wrap.querySelector('[data-ecod-cdlg-cancel]').textContent = opts.cancel || '取消';
            wrap.querySelector('[data-ecod-cdlg-ok]').textContent = opts.ok || '确认';
            document.body.appendChild(wrap);
            window.requestAnimationFrame(function () { wrap.classList.add('is-in'); });
            /* 关层统一走 dialogClose：onClose 里收尾（移除 DOM、按需记为取消），
               按钮先 done(v) 再关层，onClose 的 done(false) 因 settled 天然 no-op */
            Ecod.dialogOpen(wrap, wrap.querySelector('[data-ecod-cdlg-cancel]'), function () {
                done(false);
                wrap.classList.add('is-out');
                window.setTimeout(function () { wrap.remove(); }, 180);
            });
            wrap.querySelector('[data-ecod-cdlg-ok]').addEventListener('click', function () {
                done(true);
                Ecod.dialogClose(wrap);
            });
            wrap.querySelector('[data-ecod-cdlg-cancel]').addEventListener('click', function () {
                Ecod.dialogClose(wrap);
            });
            wrap.querySelector('[data-ecod-cdlg-mask]').addEventListener('click', function () {
                Ecod.dialogClose(wrap);
            });
        });
    };

    /* ---------- 无障碍：模态弹层原语 ----------
       焦点陷阱 / Esc 关闭 / 滚动锁 / 焦点还原。滚动锁用栈计数：
       多个弹层叠开（如裁剪弹窗叠在登录弹窗上）时不会互踩，全部关闭才解锁。
       约定：el 为弹层根节点（内部须有 role="dialog" 面板）；
       Ecod.dialogClose(el) 关指定层，缺省关最上层。 */
    var dialogStack = [];

    function dialogItems(el) {
        var sel = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]),'
            + ' textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
        return Array.prototype.filter.call(el.querySelectorAll(sel), function (n) {
            return !!(n.offsetWidth || n.offsetHeight || n.getClientRects().length);
        });
    }

    function dialogTrap(e) {
        var top = dialogStack[dialogStack.length - 1];
        if (!top || e.key !== 'Tab') return;
        var items = dialogItems(top.el);
        if (!items.length) return;
        var first = items[0];
        var last = items[items.length - 1];
        var active = document.activeElement;
        var inside = top.el.contains(active);
        if (e.shiftKey && (active === first || !inside)) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && (active === last || !inside)) {
            e.preventDefault();
            first.focus();
        }
    }

    function dialogKey(e) {
        if (e.key !== 'Escape') return;
        var top = dialogStack[dialogStack.length - 1];
        if (top) {
            e.preventDefault();
            Ecod.dialogClose();
        }
    }

    function dialogLock() {
        var need = dialogStack.some(function (d) { return d.lock !== false; });
        document.documentElement.classList.toggle('ecod-modal-lock', need);
    }

    Ecod.dialogOpen = function (el, target, onClose, opts) {
        if (dialogStack.some(function (d) { return d.el === el; })) return;
        dialogStack.push({
            el: el,
            back: document.activeElement,
            onClose: typeof onClose === 'function' ? onClose : null,
            /* 非模态浮层（AI 快问面板）传 { lock:false }：不锁 html 滚动 */
            lock: !(opts && opts.lock === false)
        });
        document.addEventListener('keydown', dialogTrap, true);
        document.addEventListener('keydown', dialogKey, true);
        dialogLock();
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

    Ecod.dialogClose = function (el) {
        var idx = -1;
        if (el) {
            for (var i = dialogStack.length - 1; i >= 0; i--) {
                if (dialogStack[i].el === el) { idx = i; break; }
            }
        } else {
            idx = dialogStack.length - 1;
        }
        if (idx < 0) return;
        var entry = dialogStack[idx];
        var back = entry.back;
        dialogStack.splice(idx, 1);
        dialogLock();
        /* 焦点还原：下面还有弹层就把焦点还给它的首个控件，否则还给打开前的触发点 */
        var top = dialogStack[dialogStack.length - 1];
        if (top) {
            var node = dialogItems(top.el)[0];
            if (node) {
                try { node.focus({ preventScroll: true }); } catch (e) {}
            }
        } else if (back && document.contains(back)) {
            try { back.focus({ preventScroll: true }); } catch (e) {}
        }
        /* 通知组件自身收尾（收 is-open/hidden、重置状态）。先弹栈后调用，
           组件 close 内部再走 dialogClose 时已查不到自己，天然 no-op */
        if (typeof entry.onClose === 'function') entry.onClose();
    };

    /* ESC 收口：供各处独立 ESC 监听查询。弹层栈非空时，下层组件
       （导航下拉/排序/右键菜单/浮动目录/表情面板等）不得再响应 ESC，
       灯箱只在自己位于栈顶时才处理，保证一次 ESC 只关一层 */
    Ecod.dialogActive = function () { return dialogStack.length > 0; };
    Ecod.dialogTop = function () {
        var top = dialogStack[dialogStack.length - 1];
        return top ? top.el : null;
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
            /* 可访问名必须包含可见文字「深浅」（label-content-name-mismatch） */
            btn.setAttribute('aria-label', mode === 'dark' ? '深浅：当前深色，切换到浅色模式' : '深浅：当前浅色，切换到深色模式');
            /* 只替换图标，保留按钮里的文字标签（如 dock 的「深浅」） */
            var old = btn.querySelector('svg');
            var size = old ? (parseInt(old.getAttribute('width'), 10) || 17) : 17;
            if (old) old.remove();
            btn.insertAdjacentHTML('afterbegin', Ecod.icon(mode === 'dark' ? 'sun' : 'moon', size));
        });
        /* 代码高亮配色跟随明暗（后台选「跟随明暗模式」时输出两套样式）。
           浅色套是 media="print" 异步形态，启用（解除 disabled）时必须把
           media 拉回 all——若它加载完成前就被 disable，onload 不会触发 */
        var prismLight = document.getElementById('ecod-prism-light');
        var prismDark = document.getElementById('ecod-prism-dark');
        if (prismLight && prismDark) {
            prismLight.disabled = mode === 'dark';
            prismDark.disabled = mode !== 'dark';
            if (mode !== 'dark') {
                prismLight.media = 'all';
            }
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

        /* 开合状态同步到汉堡按钮，读屏与键盘用户才知道菜单是否展开 */
        function syncExpanded(open) {
            document.querySelectorAll('[data-ecod-drawer-open]').forEach(function (b) {
                b.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }

        /* Esc / 焦点陷阱 / 滚动锁 / 焦点还原均由弹层原语承担 */
        Ecod.drawerClose = function () {
            drawer.classList.remove('is-open');
            drawer.setAttribute('aria-hidden', 'true');
            syncExpanded(false);
            Ecod.dialogClose(drawer);
        };

        function open() {
            drawer.classList.add('is-open');
            drawer.removeAttribute('aria-hidden');
            syncExpanded(true);
            Ecod.renderFavs();
            Ecod.dialogOpen(drawer, drawer.querySelector('a, button'), Ecod.drawerClose);
        }

        document.querySelectorAll('[data-ecod-drawer-open]').forEach(function (b) {
            once(b, 'drawerOpen', 'click', open);
        });
        document.querySelectorAll('[data-ecod-drawer-close]').forEach(function (b) {
            once(b, 'drawerClose', 'click', Ecod.drawerClose);
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
            /* 隐藏态镜像到 <html>：header 收起后，文章卡内吸顶的工具条
               不能再按「header 高度 + 8」锚定——那会在视口顶部留一条 70px 的悬空，
               看起来像没吸顶。CSS 侧 html.is-hd-off .ecod_readbar 上移贴顶 */
            document.documentElement.classList.toggle('is-hd-off', header.classList.contains('is-hidden'));
        });
    };

    /* ---------- 导航二级：点击固定展开（悬停之外的桌面/键盘兜底） ---------- */
    Ecod.initNavPin = function () {
        var nav = document.querySelector('.ecod_nav__links');
        if (!nav) return;
        document.addEventListener('click', function (e) {
            var a = e.target && e.target.closest ? e.target.closest('.ecod_nav__links li.has-sub > a') : null;
            if (!a) return;
            var li = a.parentNode;
            var href = a.getAttribute('href') || '';
            var onCaret = !!(e.target && e.target.closest && e.target.closest('.ecod_nav__caret'));
            if (!onCaret && href.charAt(0) !== '#' && href !== '' && href !== 'javascript:;') return;
            e.preventDefault();
            var wasOpen = li.classList.contains('is-open');
            document.querySelectorAll('.ecod_nav__links li.has-sub.is-open').forEach(function (x) {
                x.classList.remove('is-open');
                var xa = x.querySelector(':scope > a');
                if (xa) xa.classList.remove('is-open');
            });
            if (!wasOpen) {
                li.classList.add('is-open');
                a.classList.add('is-open');
            }
        }, true);
        document.addEventListener('click', function (e) {
            if (e.target && e.target.closest && e.target.closest('.ecod_nav__links li.has-sub')) return;
            document.querySelectorAll('.ecod_nav__links li.has-sub.is-open').forEach(function (x) {
                x.classList.remove('is-open');
                var xa = x.querySelector(':scope > a');
                if (xa) xa.classList.remove('is-open');
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape' || Ecod.dialogActive()) return;
            document.querySelectorAll('.ecod_nav__links li.has-sub.is-open').forEach(function (x) {
                x.classList.remove('is-open');
                var xa = x.querySelector(':scope > a');
                if (xa) xa.classList.remove('is-open');
            });
        });
    };

    /* ---------- mega 宽面板：缺口三角对准一级项中心 ----------
       面板挂在 .ecod_nav 上全宽定位，缺口位置 CSS 拿不到，
       把一级项中心 x 写入 --ecod-mega-arrow-x 供 ::before 定位 */
    Ecod.initNavMegaArrow = function () {
        var nav = document.querySelector('.ecod_nav');
        if (!nav) return;
        if (!nav.querySelector('.ecod_nav__sub.is-mega-full')) return;
        var update = function () {
            var navLeft = nav.getBoundingClientRect().left;
            nav.querySelectorAll('.ecod_nav__sub.is-mega-full').forEach(function (sub) {
                var r = sub.parentNode.getBoundingClientRect();
                if (!r.width) return;
                sub.style.setProperty('--ecod-mega-arrow-x', (r.left + r.width / 2 - navLeft) + 'px');
            });
        };
        update();
        if (!nav.dataset.megaArrowBound) {
            nav.dataset.megaArrowBound = '1';
            window.addEventListener('resize', update);
            window.addEventListener('load', update);
            if ('ResizeObserver' in window) new ResizeObserver(update).observe(nav);
        }
    };

    /* ---------- 回到顶部 ---------- */
    Ecod.initToTop = function () {
        var btn = document.querySelector('[data-ecod-totop]');
        if (btn) {
            once(btn, 'totop', 'click', function () {
                window.scrollTo({ top: 0, behavior: Ecod.scrollBehavior() });
            });
            Ecod.addScroll(function () {
                btn.classList.toggle('is-show', window.scrollY > 480);
            });
        }

        /* 移动端 dock 的「顶部」按钮 */
        var dockTop = document.querySelector('[data-ecod-dock-top]');
        if (dockTop) {
            once(dockTop, 'dockTop', 'click', function () {
                window.scrollTo({ top: 0, behavior: Ecod.scrollBehavior() });
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

        /* glow 样式：滑动指示器测量定位，点击时跟随（其余样式指示器不显示） */
        var ind = dock.querySelector('.ecod_dock__ind');
        var glow = dock.getAttribute('data-style') === 'glow' && ind;
        var moveInd = function (btn) {
            if (!glow || !btn) return;
            ind.hidden = false;
            ind.style.width = btn.offsetWidth + 'px';
            ind.style.transform = 'translateX(' + btn.offsetLeft + 'px)';
        };
        var active = dock.querySelector('.ecod_dock__btn.is-active');
        if (glow && active) {
            /* 首帧布局稳定后再定位，避免字体加载后错位 */
            var place = function () { moveInd(active); };
            if (window.requestAnimationFrame) {
                window.requestAnimationFrame(place);
            } else {
                place();
            }
            window.addEventListener('resize', function () {
                active = dock.querySelector('.ecod_dock__btn.is-active') || active;
                moveInd(active);
            });
        }
        /* 点击切换选中态（动效反馈；链接条目照常跳转） */
        dock.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('.ecod_dock__btn') : null;
            if (!btn || !dock.contains(btn)) return;
            var prev = dock.querySelector('.ecod_dock__btn.is-active');
            if (prev && prev !== btn) prev.classList.remove('is-active');
            btn.classList.add('is-active');
            if (glow) moveInd(btn);
        });
    };

    /* ---------- 站点公告 ---------- */
    var NKEY = 'ecod-notice-closed';
    var NPKEY = 'ecod-notice-pop';
    Ecod.initNotice = function () {
        /* 弹窗公告：内容变化后每个访客弹一次（关闭记忆按内容 hash） */
        var pop = document.querySelector('[data-ecod-noticepop]');
        if (pop && pop.dataset.noticePopReady !== '1') {
            pop.dataset.noticePopReady = '1';
            var popHash = pop.dataset.ecodNoticeHash || '';
            var popSeen = '';
            try { popSeen = localStorage.getItem(NPKEY) || ''; } catch (e) {}
            if (popSeen !== popHash) {
                var popClose = function () {
                    pop.classList.remove('is-open');
                    pop.setAttribute('aria-hidden', 'true');
                    Ecod.dialogClose(pop);
                    try { localStorage.setItem(NPKEY, popHash); } catch (err) {}
                };
                setTimeout(function () {
                    pop.classList.add('is-open');
                    pop.setAttribute('aria-hidden', 'false');
                    /* Esc / 焦点陷阱 / 滚动锁由弹层原语承担，onClose 负责收面板 */
                    Ecod.dialogOpen(pop, null, popClose);
                }, 350);
            }
            once(pop, 'noticePopClose', 'click', function (e) {
                var closer = e.target.closest ? e.target.closest('[data-ecod-noticepop-close]') : null;
                if (!closer) return;
                popClose();
            });
        }

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

        /* 多条轮播：公告横幅在 PJAX 壳之外，refresh 会重复进入，这里按实例只挂一次 */
        if (bar.dataset.ecodNoticeMode === 'rotate' && bar.dataset.noticeRotateReady !== '1') {
            var slides = bar.querySelectorAll('[data-ecod-notice-slide]');
            if (slides.length > 1) {
                bar.dataset.noticeRotateReady = '1';
                var link = bar.querySelector('[data-ecod-notice-link]');
                var counter = bar.querySelector('[data-ecod-notice-count]');
                var idx = 0;
                setInterval(function () {
                    idx = (idx + 1) % slides.length;
                    slides.forEach(function (s, i) { s.classList.toggle('is-active', i === idx); });
                    if (link) {
                        var href = slides[idx].dataset.link || '';
                        if (href) {
                            link.href = href;
                            link.hidden = false;
                        } else {
                            link.hidden = true;
                        }
                    }
                    if (counter) counter.textContent = (idx + 1) + '/' + slides.length;
                }, 5000);
            }
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
            html += '<a class="ecod_favs__item" href="' + escHtml(f.url) + '">'
                + '<span class="ecod_favs__title">' + escHtml(f.title || '未命名') + '</span>'
                + '<button type="button" class="ecod_favs__del" data-ecod-fav-del="' + escHtml(f.url) + '" aria-label="移除收藏">'
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

        /* Tab 切换（支持 #favs 之类的 hash 深链）
           互动类（favs/likes/follows/history）挂在侧边「互动」组下：激活任一子项时
           点亮侧边组入口并展开子标签行，切到其他菜单时收起 */
        var UC_SOCIAL = { favs: 1, likes: 1, follows: 1, history: 1 };
        function activateTab(key) {
            var isSocial = !!UC_SOCIAL[key];
            root.querySelectorAll('[data-ecod-uc-group-tab]').forEach(function (g) {
                g.classList.toggle('is-active', isSocial);
            });
            var subbar = root.querySelector('[data-ecod-uc-subbar]');
            if (subbar) subbar.hidden = !isSocial;
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
            root.querySelectorAll('[data-ecod-uc-group-tab]').forEach(function (gt) {
                gt.addEventListener('click', function () {
                    activateTab('favs');
                    try { history.replaceState(null, '', '#favs'); } catch (e) {}
                });
            });
        }

        /* 抽奖记录筛选片（全部 / 中奖 / 兑换）：纯客户端过滤，服务端已直出全部数据 */
        var lrecBox = root.querySelector('[data-ecod-lrec]');
        if (lrecBox && !lrecBox.dataset.ecodBound) {
            lrecBox.dataset.ecodBound = '1';
            lrecBox.querySelectorAll('[data-lf]').forEach(function (chip) {
                chip.addEventListener('click', function () {
                    lrecBox.querySelectorAll('[data-lf]').forEach(function (c) {
                        c.classList.toggle('is-active', c === chip);
                    });
                    var f = chip.dataset.lf;
                    lrecBox.querySelectorAll('.ecod_lrec__item').forEach(function (it) {
                        it.hidden = f !== 'all' && it.dataset.type !== f;
                    });
                    var end = lrecBox.querySelector('.ecod_lrec__end');
                    var any = lrecBox.querySelector('.ecod_lrec__item:not([hidden])');
                    if (end) end.hidden = !any;
                });
            });
        }

        /* 我的会员：卡密直兑（api.php?action=pay_redeem），成功后刷新状态卡 */
        if (!root.dataset.ecodVip) {
            root.dataset.ecodVip = '1';
            var vipBox = root.querySelector('[data-ecod-vip]');
            if (vipBox) {
                var vipRedeemBox = vipBox.querySelector('[data-ecod-vip-redeem]');
                var vipToggle = vipBox.querySelector('[data-ecod-vip-redeem-toggle]');
                var vipCode = vipBox.querySelector('[data-ecod-vip-code]');
                var vipBtn = vipBox.querySelector('[data-ecod-vip-redeem]');
                var vipMsg = vipBox.querySelector('[data-ecod-vip-msg]');

                var vipShowMsg = function (text, bad) {
                    if (!vipMsg) return;
                    vipMsg.hidden = false;
                    vipMsg.textContent = text;
                    vipMsg.classList.toggle('is-error', !!bad);
                };

                if (vipToggle && vipRedeemBox) {
                    vipToggle.addEventListener('click', function () {
                        vipRedeemBox.hidden = !vipRedeemBox.hidden;
                        if (!vipRedeemBox.hidden && vipCode) vipCode.focus();
                    });
                }

                var vipSubmit = function () {
                    var code = vipCode ? vipCode.value.trim() : '';
                    if (!code) {
                        vipShowMsg('请输入卡密', true);
                        return;
                    }
                    if (vipBtn) vipBtn.disabled = true;
                    var form = new FormData();
                    form.append('code', code);
                    form.append('token', vipBox.dataset.token || '');
                    fetch((vipBox.dataset.api || '') + '?action=pay_redeem', {
                        method: 'POST',
                        body: form,
                        credentials: 'same-origin'
                    }).then(function (res) {
                        return res.json().catch(function () { throw new Error('请求失败，请稍后重试'); });
                    }).then(function (data) {
                        if (data && data.ok) {
                            vipShowMsg(data.message || '兑换成功，正在刷新…', false);
                            if (vipCode) vipCode.value = '';
                            window.setTimeout(function () { window.location.reload(); }, 1200);
                        } else {
                            vipShowMsg((data && data.error) || '兑换失败', true);
                        }
                    }).catch(function (e) {
                        vipShowMsg(e && e.message ? e.message : '网络异常，请重试', true);
                    }).then(function () {
                        if (vipBtn) vipBtn.disabled = false;
                    });
                };

                if (vipBtn) vipBtn.addEventListener('click', vipSubmit);
                if (vipCode) {
                    vipCode.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            vipSubmit();
                        }
                    });
                }
            }
        }
    };

    /* ---------- 浮层：点空白处 / Esc 收起 ----------
       details 原生弹层只认「再点一次标题」，这里统一补外点关闭；
       抽屉里的手风琴（.ecod_drawer__group 等）不在名单内，不会被误关。 */
    Ecod.initPopDismiss = function () {
        if (Ecod._popDismiss) return;
        Ecod._popDismiss = 1;

        var SEL = 'details.ecod_nav__user[open], details.ecod_nav__bell[open], details.ecod_share[open],'
            + ' details.ecod_reward[open], .ecod_nav__more details[open]';
        var BASE = 'details.ecod_nav__user, details.ecod_nav__bell, details.ecod_share,'
            + ' details.ecod_reward, .ecod_nav__more details';

        function closeAll(except) {
            var restore = null;
            document.querySelectorAll(SEL).forEach(function (d) {
                if (d === except) return;
                /* 焦点正停在这个面板里（↑↓ 导航过）时记下来，收起后还给 summary，
                   否则焦点会掉到 body（排序菜单关闭会还原焦点，这里行为对齐） */
                if (d.contains(document.activeElement)) restore = d;
                d.removeAttribute('open');
            });
            if (restore) {
                var s = restore.querySelector('summary');
                if (s) {
                    try { s.focus({ preventScroll: true }); } catch (err) {}
                }
            }
        }

        /* details 开合同步 summary 的 aria-expanded（details 原生语义不覆盖按钮态）；
           toggle 事件不冒泡，用 capture 接。顺带补 aria-haspopup。 */
        document.addEventListener('toggle', function (e) {
            var d = e.target;
            if (!d || !d.querySelector) return;
            var s = d.querySelector('summary');
            if (s) {
                s.setAttribute('aria-expanded', d.hasAttribute('open') ? 'true' : 'false');
                s.setAttribute('aria-haspopup', 'menu');
            }
        }, true);
        document.querySelectorAll(BASE).forEach(function (d) {
            var s = d.querySelector('summary');
            if (s && !s.hasAttribute('aria-haspopup')) s.setAttribute('aria-haspopup', 'menu');
        });

        /* 打开的下拉里 ↑↓ 在菜单项间循环（焦点在 summary 上时 ↓ 进第一项） */
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
            var active = document.activeElement;
            var d = active && active.closest ? active.closest(SEL) : null;
            if (!d) return;
            var items = Array.prototype.slice.call(d.querySelectorAll('a[href], button:not([disabled])'));
            if (!items.length) return;
            e.preventDefault();
            var cur = items.indexOf(active);
            var next = e.key === 'ArrowDown' ? (cur + 1) % items.length : (cur - 1 + items.length) % items.length;
            items[next].focus();
        });

        document.addEventListener('click', function (e) {
            var t = e.target;
            closeAll(t && t.closest ? t.closest('details') : null);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape' || Ecod.dialogActive()) return;
            closeAll(null);
        });
    };

    /* ---------- 头部用户面板：点外部收起 ---------- */
    /* 头部 details 弹层（用户面板 / 站内消息铃铛）：桌面端 hover 即展开，
       移出延时收起——延时盖过 summary → 弹层之间空隙的穿越时间。
       触屏无 hover，保持原生点击。点外收起 / Esc 由 initPopDismiss 统一负责。
       bindHoverDetails(d, delay, group) 同时供其他 details 弹层复用
       （文末动作条分享/赞赏/报错，见 ecod.post.js）：group 是「同组互斥」
       选择器，hover 展开时关掉组内其他已展开的弹层，缺省为头部两个。 */
    function hoverCapable() {
        return !!(window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches);
    }
    function bindHoverDetails(d, delay, group) {
        if (!d || d.dataset.ecodHoverBound === '1') return;
        d.dataset.ecodHoverBound = '1';
        var timer = null;
        d.addEventListener('mouseenter', function () {
            if (timer) { clearTimeout(timer); timer = null; }
            /* hover 展开不走 click，initPopDismiss 的「点外收起」管不到，
               这里手动关掉同组其他已展开的弹层 */
            document.querySelectorAll(group || 'details.ecod_nav__user[open], details.ecod_nav__bell[open]').forEach(function (o) {
                if (o !== d) o.removeAttribute('open');
            });
            if (!d.open) d.open = true;
        });
        d.addEventListener('mouseleave', function () {
            if (timer) clearTimeout(timer);
            timer = setTimeout(function () { timer = null; d.open = false; }, delay || 160);
        });
    }
    Ecod.hoverCapable = hoverCapable;
    Ecod.bindHoverDetails = bindHoverDetails;

    Ecod.initUserPop = function () {
        /* 下拉面板从触发头像处展开：用 offset 坐标（不受入场 scale 影响），
           summary 与面板同在 .ecod_nav__user 定位上下文内。 */
        document.querySelectorAll('details.ecod_nav__user').forEach(function (d) {
            var panel = d.querySelector('.ecod_nav__upop');
            var summary = d.querySelector('summary');
            if (!panel || !summary) return;
            d.addEventListener('toggle', function () {
                if (!d.open) return;
                panel.style.setProperty('--ecod-ox', (summary.offsetLeft + summary.offsetWidth / 2) + 'px');
                panel.style.setProperty('--ecod-oy', (summary.offsetTop + summary.offsetHeight) + 'px');
            });
            if (hoverCapable()) bindHoverDetails(d);
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
                } else if (el.dataset.ecodLikeNum === 'short') {
                    /* 文末动作条 / 侧栏：0 赞回落「点赞」文案（与服务端渲染一致，不能留空） */
                    el.textContent = count > 0 ? shortNum(count) : '点赞';
                    /* 标签自身的 is-num 也要同步（服务端渲染带在 b 上）：归零不摘的话，
                       桌面「隐文字标签」与移动端「只显数字」两条规则都会让它残留常显 */
                    el.classList.toggle('is-num', count > 0);
                } else {
                    /* 卡片徽标：0 赞清空 + 摘掉 is-num，徽标整体隐藏 */
                    el.textContent = count > 0 ? shortNum(count) : '';
                }
            }
            btn.classList.toggle('is-num', count > 0);
        });
    }

    /* 同步某篇文章的收藏总数（动作条；服务端在 fav_toggle 里回传最新值）。
       只动带 data-ecod-fav-num 的按钮，卡片星标（无计数标签）不参与 */
    function setFavNum(cid, count) {
        count = parseInt(count, 10) || 0;
        document.querySelectorAll('[data-ecod-act="fav"]').forEach(function (btn) {
            var el = btn.querySelector('[data-ecod-fav-num]');
            if (!el) return;
            var card = btn.closest('[data-cid]');
            var id = btn.dataset.cid || (card ? card.dataset.cid : '');
            if (String(id) !== String(cid)) return;
            el.textContent = count > 0 ? shortNum(count) : '收藏';
            el.classList.toggle('is-num', count > 0);
            btn.classList.toggle('is-num', count > 0);
        });
    }

    /* D2 点赞头像堆叠：点赞成功后即时刷新「@xx 觉得不错」行（服务端返回 likers），
       与 components/post/act.php 的服务端渲染同构；likers 为空时整行撤下 */
    Ecod.renderLikers = function (cid, likers, likeNum) {
        if (!Array.isArray(likers)) return;
        document.querySelectorAll('[data-ecod-postact]').forEach(function (bar) {
            if (String(bar.dataset.cid || '') !== String(cid)) return;
            var row = bar.querySelector('.ecod_likers');
            if (!likers.length) {
                if (row) row.remove();
                return;
            }
            var esc = function (s) {
                return String(s).replace(/[&<>"]/g, function (c) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
                });
            };
            var av = likers.map(function (liker) {
                if (liker.avatar) {
                    /* data-initial：头像失效时兜底成首字渐变圆（与服务端渲染同构） */
                    return '<img src="' + esc(liker.avatar) + '" alt="" width="24" height="24" loading="lazy" decoding="async" data-initial="'
                        + esc(String(liker.name || '').slice(0, 1)) + '">';
                }
                return '<i>' + esc(String(liker.name || '').slice(0, 1)) + '</i>';
            }).join('');
            var text = '@' + esc(likers[0].name) + ' 觉得不错';
            if (likers.length > 1) {
                text = '@' + esc(likers[0].name) + ' 等 <b>' + (parseInt(likeNum, 10) || 0) + '</b> 人觉得不错';
            }

            if (!row) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'ecod_likers';
                btn.dataset.ecodLikers = '';
                btn.setAttribute('aria-label', '跳到评论区');
                btn.innerHTML = '<span class="ecod_likers__av" aria-hidden="true"></span><span class="ecod_likers__text"></span>';
                bar.insertBefore(btn, bar.firstElementChild);
                row = btn;
            }
            row.querySelector('.ecod_likers__av').innerHTML = av;
            row.querySelector('.ecod_likers__text').innerHTML = text;
        });
    };

    /* 点赞粒子爆裂：以按钮为圆心撒 8 颗粒子（主色/点赞色交替），方向随机、0.7s 消散 */
    Ecod.likeBurst = function (btn) {
        if (!btn) return;
        var r = btn.getBoundingClientRect();
        var cx = r.left + r.width / 2;
        var cy = r.top + r.height / 2;
        for (var i = 0; i < 8; i++) {
            var p = document.createElement('i');
            p.className = 'ecod_likeburst' + (i % 2 ? ' is-alt' : '');
            var ang = (Math.PI * 2 * i) / 8 + Math.random() * 0.6;
            var dist = 26 + Math.random() * 18;
            p.style.left = cx + 'px';
            p.style.top = cy + 'px';
            p.style.setProperty('--dx', Math.cos(ang) * dist + 'px');
            p.style.setProperty('--dy', Math.sin(ang) * dist + 'px');
            document.body.appendChild(p);
            setTimeout(function (el) { return function () { el.remove(); }; }(p), 750);
        }
    };

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
                        if (typeof data.count === 'number') setFavNum(cid, data.count);
                        if (favRemoteSet) favRemoteSet[String(cid)] = data.fav ? 1 : 0;
                        localSetFav(item, data.fav);
                        if (data.fav) { pop(); Ecod.ballFlashAll('19', 1500); }
                        live(data.fav ? '已加入收藏' : '已取消收藏');
                    } else {
                        /* 无接口（或接口失败）：本地兜底 */
                        var on = toggleFav(item);
                        setFavBtn(btn, on);
                        if (on) { pop(); Ecod.ballFlashAll('19', 1500); }
                        live(on ? '已加入收藏' : '已取消收藏');
                    }
                });
                return;
            }

            if (btn.dataset.ecodAct === 'like') {
                /* toggle：已赞再点 = 取消（is-on 不再拦截，状态以服务端 mine 为准） */
                if (btn.dataset.busy === '1') return;
                btn.dataset.busy = '1';
                fetch((CFG.api || '') + '?action=like&cid=' + encodeURIComponent(cid), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function (r) { return r.json(); }).then(function (data) {
                    btn.dataset.busy = '';
                    if (!data || !data.ok) return;

                    btn.classList.toggle('is-on', !!data.mine);
                    btn.setAttribute('aria-pressed', data.mine ? 'true' : 'false');
                    if (typeof data.count === 'number') setLikeNum(cid, data.count);
                    if (data.likers) Ecod.renderLikers(cid, data.likers, data.count);
                    if (data.liked) {
                        btn.classList.add('is-pop');
                        setTimeout(function () { btn.classList.remove('is-pop'); }, 320);
                        Ecod.likeBurst(btn);
                        Ecod.ballFlashAll('19', 1500);
                    }
                    live(data.mine ? '已点赞' : '已取消点赞');
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
        /* 从光标落点展开：把原点换算成菜单内的 px 偏移（跟随实际 clamp 后的位置） */
        ctxEl.style.setProperty('--ecod-ox', Math.round(x - left) + 'px');
        ctxEl.style.setProperty('--ecod-oy', Math.round(y - top) + 'px');
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
                    fn: function () { window.scrollTo({ top: 0, behavior: Ecod.scrollBehavior() }); }
                });
            }

            /* 全局接管后的兜底：空白处、纯文本上右键也给出通用动作，菜单不会空着 */
            if (!items.length) {
                items.push({
                    icon: 'link',
                    label: '复制本页地址',
                    fn: function () { copyText(location.href); }
                });
                var pageTitle = String(document.title || '').trim();
                if (pageTitle !== '') {
                    items.push({
                        icon: 'file',
                        label: '复制页面标题',
                        fn: function () { copyText(pageTitle); }
                    });
                }
            }

            /* 后台「右键菜单 → 自定义菜单项」：追加在底部，{url} 换成当前页地址 */
            var custom = Array.isArray(CFG.ctxitems) ? CFG.ctxitems : [];
            if (custom.length) {
                items.push({ sep: true });
                custom.forEach(function (it) {
                    items.push({
                        icon: 'link',
                        label: it.label,
                        fn: function () {
                            var url = String(it.url || '').replace(/\{url\}/g, location.href);
                            if (!url) return;
                            if (it.blank) window.open(url, '_blank', 'noopener');
                            else window.location.href = url;
                        }
                    });
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
            if (e.key === 'Escape' && !Ecod.dialogActive()) ctxClose();
        });
        window.addEventListener('scroll', ctxClose, { passive: true });
        window.addEventListener('resize', ctxClose);
        window.addEventListener('blur', ctxClose);
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

    /* ---------- 聚合 Tab 卡（F7）：多分类 Tab 前端切换，预渲染无请求 ---------- */
    Ecod.initTp = function () {
        document.querySelectorAll('.ecod_tp').forEach(function (card) {
            if (card._ecodTp) return;
            card._ecodTp = 1;

            var tabs = card.querySelectorAll('[data-ecod-tp-tab]');
            var panels = card.querySelectorAll('[data-ecod-tp-pane]');
            if (!tabs.length || !panels.length) return;

            var activate = function (tab) {
                var key = tab.getAttribute('data-ecod-tp-tab');
                Array.prototype.forEach.call(tabs, function (one) {
                    var on = one === tab;
                    one.classList.toggle('is-active', on);
                    one.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                Array.prototype.forEach.call(panels, function (panel) {
                    panel.classList.toggle('is-active', panel.getAttribute('data-ecod-tp-pane') === key);
                });
            };

            Array.prototype.forEach.call(tabs, function (tab) {
                tab.addEventListener('click', function () { activate(tab); });
            });
        });
    };

    /* ---------- 关注作者：切换 + 粉丝数就地更新（登录态由服务端渲染的按钮形态区分） ---------- */
    Ecod.initFollow = function () {
        if (!CFG.api) return;

        once(document, 'follow', 'click', function (e) {
            var btn = e.target.closest ? e.target.closest('[data-ecod-follow]') : null;
            if (!btn) return;
            e.preventDefault();
            if (btn.dataset.busy === '1') return;
            btn.dataset.busy = '1';

            var uid = btn.getAttribute('data-ecod-follow');
            var body = 'uid=' + encodeURIComponent(uid)
                + '&token=' + encodeURIComponent(btn.getAttribute('data-ecod-token') || '');

            fetch(CFG.api + '?action=follow_toggle', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            }).then(function (r) { return r.json(); }).then(function (data) {
                btn.dataset.busy = '';
                if (!data || !data.ok) {
                    if (data && data.error) Ecod.toast(data.error, 'bad');
                    return;
                }
                var on = !!data.following;
                btn.classList.toggle('is-on', on);
                btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                var label = btn.querySelector('span');
                if (label) label.textContent = on ? '已关注' : '关注';

                var fans = document.querySelector('[data-ecod-fans="' + uid + '"]');
                if (fans && typeof data.fans === 'number') fans.textContent = String(data.fans);

                /* 个人中心列表：取关后就地移除 */
                var row = btn.closest('.ecod_uc__follow-item');
                if (!on && row) row.remove();
            }).catch(function () {
                btn.dataset.busy = '';
            });
        });
    };

    /* ---------- 侧栏热门 Tab 组：显隐切换（版面已在服务端渲染） ---------- */
    Ecod.initWtabs = function () {
        document.querySelectorAll('[data-ecod-wtabs]').forEach(function (root) {
            if (root._ecodWtab) return;
            root._ecodWtab = 1;

            var tabs = root.querySelectorAll('[data-ecod-wtab]');
            if (!tabs.length) return;

            function activate(tab) {
                var key = tab.getAttribute('data-ecod-wtab');
                Array.prototype.forEach.call(tabs, function (one) {
                    var on = one === tab;
                    one.classList.toggle('is-active', on);
                    one.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                Array.prototype.forEach.call(root.querySelectorAll('[data-ecod-wtab-pane]'), function (pane) {
                    var on = pane.getAttribute('data-ecod-wtab-pane') === key;
                    pane.classList.toggle('is-active', on);
                    pane.hidden = !on;
                });
            }

            Array.prototype.forEach.call(tabs, function (tab, i) {
                tab.addEventListener('click', function () { activate(tab); });
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

    /* ---------- 更新热力图（heatgrid 块）：拖动 / 悬停读某天 ---------- */
    /* 单个 pointermove 监听同时覆盖鼠标悬停与触摸拖动（elementFromPoint
       取当前格），松手 / 移出回到 summary；格子 DOM 由服务端输出，此处纯读。 */
    Ecod.initHeatgrid = function () {
        document.querySelectorAll('[data-ecod-heatgrid]').forEach(function (root) {
            if (root._ecodHg) return;
            root._ecodHg = 1;

            var grid = root.querySelector('.ecod_heatgrid__grid');
            var readout = root.querySelector('.ecod_heatgrid__readout');
            if (!grid || !readout) return;

            var summary = readout.textContent;
            var start = root.getAttribute('data-start') || '';
            var current = null;

            /* 格子按列填充，DOM 序号即自然日序号（周一为首行） */
            var cells = grid.querySelectorAll('.ecod_heatgrid__cell');

            function dayLabel(index) {
                var parts = /^(\d{4})-(\d{2})-(\d{2})$/.exec(start);
                if (!parts) return '';
                var d = new Date(+parts[1], +parts[2] - 1, +parts[3]);
                d.setDate(d.getDate() + index);
                return (d.getMonth() + 1) + '月' + d.getDate() + '日 周' + '日一二三四五六'.charAt(d.getDay());
            }

            function show(cell) {
                if (cell === current) return;
                if (current) current.classList.remove('is-current');
                current = cell;
                if (!cell) {
                    readout.textContent = summary;
                    return;
                }
                cell.classList.add('is-current');
                var i = Array.prototype.indexOf.call(cells, cell);
                var v = cell.getAttribute('data-v') || '0';
                readout.textContent = dayLabel(i) + ' · ' + v + ' 篇';
            }

            grid.addEventListener('pointermove', function (e) {
                var el = document.elementFromPoint(e.clientX, e.clientY);
                show(el && el.closest ? el.closest('.ecod_heatgrid__cell') : null);
            });
            grid.addEventListener('pointerleave', function () {
                show(null);
            });
            document.addEventListener('pointerup', function () {
                show(null);
            });
        });
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

        /* 表情球（可选挂载）：好奇注视光标，检索中思考，出结果满意 / 空手困惑 */
        var sBall = panel.querySelector('[data-ecod-spanel-ball]');
        var ball = sBall ? Ecod.ballMount(sBall, { emotion: '03', size: 30 }) : null;

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

        /* 推荐文章：后端按 ESearchPosts 维度算好，前端只负责渲染成文字行 */
        var posts = [];
        try { posts = JSON.parse(panel.getAttribute('data-posts') || '[]'); } catch (e) {}
        if (!Array.isArray(posts)) posts = [];
        var postsLabel = panel.getAttribute('data-posts-label') || '推荐文章';

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
            /* 01 唤醒短弧线 → 回好奇 base */
            if (ball) ball.flash('01', 1000);
            if (prefill) {
                input.value = prefill;
                onInput();
            }
            /* Esc / 焦点陷阱 / 滚动锁由弹层原语承担；面板有入场过渡，60ms 后补一次焦点 */
            Ecod.dialogOpen(panel, input, close);
            setTimeout(function () { input.focus(); }, 60);
        }

        function close() {
            panel.classList.remove('is-open');
            panel.setAttribute('aria-hidden', 'true');
            Ecod.dialogClose(panel);
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
            /* 兜底：若输入框此前已被聚焦（如 Tab），联想下拉可能已显示，一并收起 */
            var drop = box.querySelector('.ecod_suggest');
            if (drop) drop.hidden = true;
            open();
        }, true);

        /* 导航操作簇的搜索图标按钮（§03 修订：常驻搜索框收敛为图标） */
        document.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('[data-ecod-spanel-open]') : null;
            if (!btn) return;
            e.preventDefault();
            open();
        }, true);

        /* mousedown 阶段就要拦掉默认行为：浏览器在 mousedown 时聚焦输入框，
           click 拦不住 → 聚焦会同时弹出主题联想「最近搜索」和浏览器原生搜索历史 */
        document.addEventListener('mousedown', function (e) {
            var box = e.target.closest ? e.target.closest('.ecod_nav__search') : null;
            if (!box) return;
            e.preventDefault();
        }, true);

        /* 移动端 dock 搜索按钮 → 打开面板；面板关闭（ESearchPanel=off）时保留抽屉 */
        document.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('[data-ecod-dock-search]') : null;
            if (!btn) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            open();
        }, true);

        /* Esc 关闭由弹层原语承担（dialogKey 只响应最上层） */

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

        /* 推荐文章行：标题 + 日期，点击直达（链接按 cid 现场拼，不吃路由差异） */
        function postRows() {
            if (!posts.length) return '';
            var html = '<div class="ecod_spanel__row is-posts"><div class="ecod_spanel__rowlabel">'
                + esc(postsLabel) + '</div><div class="ecod_spanel__posts">';
            posts.forEach(function (it) {
                var cid = parseInt(it.cid, 10);
                if (!cid) return;
                html += '<a class="ecod_spanel__post ecod_spanel__item" href="' + esc(postUrl(cid)) + '">'
                    + '<b>' + esc(it.title) + '</b>'
                    + (it.date ? '<time>' + esc(it.date) + '</time>' : '')
                    + '</a>';
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
            html += postRows();
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
            if (ball) ball.set('30');
            fetch((CFG.api || '') + '?action=search_suggest&q=' + encodeURIComponent(q), {
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (input.value.trim() !== q) return;
                if (!data || !data.ok) return;
                renderResults(data.list || [], q);
            }).catch(function () {});
        }

        function renderResults(items, q) {
            /* 命中的历史记录作为推荐置顶（不含与当前输入完全相同的词） */
            var matched = histMatch(q);
            /* 有历史命中 37 复述回忆（想起来了），有结果 19 满意，空手 20 困惑 */
            if (ball) ball.flash(matched.length ? '37' : (items.length ? '19' : '20'), 1800);
            var html = '';
            if (matched.length) {
                html += '<div class="ecod_spanel__row is-hist"><div class="ecod_spanel__rowlabel">来自历史</div><div class="ecod_spanel__chips">';
                matched.forEach(function (h) {
                    html += '<a class="ecod_spanel__chip ecod_spanel__item is-hist" href="' + esc(searchHref(h)) + '" data-ecod-spanel-q="' + esc(h) + '">'
                        + Ecod.icon('clock', 12) + '<span>' + esc(h) + '</span></a>';
                });
                html += '</div></div>';
            }
            html += '<div class="ecod_spanel__rowlabel">搜索结果 · 回车全站搜索</div>';
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
            html += aiAskRow(q);
            body.innerHTML = html;
            body.classList.add('is-results');
        }

        /* ---- AI 搜索（F9）：列表尾部一条入口，点开就地出结论 + 引用 ---- */
        function aiAskRow(q) {
            if (!CFG.aiSearch || q.length < 2) return '';
            return '<button type="button" class="ecod_spanel__aiask" data-ecod-spanel-ai="' + esc(q) + '">'
                + '<span class="ecod_spanel__aiask-b">AI</span>'
                + '<span>问问 AI：<b>' + esc(q) + '</b></span>'
                + '<span class="ecod_spanel__aiask-go">' + Ecod.icon('arrow-up-right', 12) + '</span>'
                + '</button>';
        }

        function aiAnswerView(q, data, cached) {
            var html = '<div class="ecod_spanel__ai">'
                + '<div class="ecod_spanel__ai-head">'
                + '<span class="ecod_spanel__ai-t">AI 回答</span>'
                + (data.keywords && data.keywords.length
                    ? '<span class="ecod_spanel__ai-kw">检索词：' + esc(data.keywords.join(' / ')) + '</span>' : '')
                + '</div>'
                + '<p class="ecod_spanel__ai-ans">' + esc(data.answer || '') + '</p>';
            var list = Array.isArray(data.items) ? data.items : [];
            if (list.length) {
                html += '<ol class="ecod_spanel__ai-refs">';
                list.forEach(function (it, i) {
                    html += '<li><a href="' + esc(it.url || postUrl(it.cid)) + '">'
                        + '<span class="ecod_spanel__ai-no">' + (i + 1) + '</span>'
                        + '<b>' + esc(it.title) + '</b>'
                        + (it.date ? '<time>' + esc(it.date) + '</time>' : '')
                        + '</a></li>';
                });
                html += '</ol>';
            }
            var price = CFG.aiFree ? 'VIP 免单' : (data.cost > 0 ? data.cost + ' ' + (CFG.credits || '积分') : '免费');
            html += '<div class="ecod_spanel__ai-foot">'
                + '<span>' + (cached ? '命中缓存 · 不扣积分' : '本次消耗 ' + esc(price)) + '</span>'
                + '<button type="button" class="ecod_spanel__ai-back" data-ecod-spanel-aiback>返回搜索结果</button>'
                + '</div></div>';
            body.innerHTML = html;
            body.classList.add('is-results');
        }

        function aiSearch(q) {
            body.innerHTML = '<div class="ecod_spanel__ai"><div class="ecod_spanel__ai-head">'
                + '<span class="ecod_spanel__ai-t">AI 回答</span></div>'
                + '<p class="ecod_spanel__ai-ans is-loading">正在站内检索并归纳…</p></div>';
            body.classList.add('is-results');
            if (ball) ball.set('30');

            var fd = new FormData();
            fd.append('q', q);
            fd.append('token', CFG.aiToken || '');
            fetch((CFG.api || '') + '?action=ai_search', {
                method: 'POST',
                credentials: 'same-origin',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (r) {
                return r.json().then(function (d) { return { d: d, status: r.status }; });
            }).then(function (res) {
                var d = res.d || {};
                if (d.ok) {
                    if (ball) ball.flash(d.items && d.items.length ? '19' : '20', 1800);
                    aiAnswerView(q, d, !!d.cached);
                    return;
                }
                if (ball) ball.flash(res.status === 402 ? '38' : '34', 2200);
                var msg = d.error || '生成失败，请稍后重试';
                if (res.status === 401) msg = '登录后即可用 AI 搜索（<a href="' + esc(CFG.aiPage || '#') + '">去登录</a>）';
                body.innerHTML = '<div class="ecod_spanel__ai"><p class="ecod_spanel__ai-ans is-err">' + msg + '</p>'
                    + '<div class="ecod_spanel__ai-foot"><span></span>'
                    + '<button type="button" class="ecod_spanel__ai-back" data-ecod-spanel-aiback>返回搜索结果</button>'
                    + '</div></div>';
            }).catch(function () {
                if (ball) ball.flash('34', 2200);
                body.innerHTML = '<div class="ecod_spanel__ai"><p class="ecod_spanel__ai-ans is-err">网络异常，请稍后重试</p>'
                    + '<div class="ecod_spanel__ai-foot"><span></span>'
                    + '<button type="button" class="ecod_spanel__ai-back" data-ecod-spanel-aiback>返回搜索结果</button>'
                    + '</div></div>';
            });
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
                if (ball) ball.flash('31', 1200);
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
            var aiBtn = e.target.closest ? e.target.closest('[data-ecod-spanel-ai]') : null;
            if (aiBtn) {
                e.preventDefault();
                aiSearch(aiBtn.getAttribute('data-ecod-spanel-ai') || input.value.trim());
                return;
            }
            var back = e.target.closest ? e.target.closest('[data-ecod-spanel-aiback]') : null;
            if (back) {
                e.preventDefault();
                onInput();
                return;
            }
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
        /* 大小写不敏感去重：同名不同写法（ES6 / es6）只留最新一条 */
        var nq = String(q).toLowerCase();
        var list = readHist().filter(function (x) { return x.toLowerCase() !== nq; });
        list.unshift(q);
        try { localStorage.setItem(SKEY, JSON.stringify(list.slice(0, 8))); } catch (e) {}
    }

    /* 历史 → 推荐：输入时把「包含当前关键词」的历史记录顶上来（搜索面板与导航下拉共用） */
    function histMatch(q) {
        var nq = String(q).toLowerCase();
        if (!nq) return [];
        return readHist().filter(function (h) {
            var nh = String(h).toLowerCase();
            return nh !== nq && nh.indexOf(nq) > -1;
        }).slice(0, 4);
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
            drop.id = 'ecod-suggest-' + Math.random().toString(36).slice(2, 8);
            /* combobox 语义：输入框控制联想列表，展开态跟随显隐 */
            input.setAttribute('role', 'combobox');
            input.setAttribute('aria-controls', drop.id);
            input.setAttribute('aria-expanded', 'false');
            form.appendChild(drop);

            function setDropOpen(open) {
                drop.hidden = !open;
                input.setAttribute('aria-expanded', open ? 'true' : 'false');
            }

            function renderList(items, q) {
                var html = '';
                /* 命中的历史记录作为推荐置顶（点按 → 回填并搜索） */
                var matched = histMatch(q);
                if (q && matched.length) {
                    html += '<div class="ecod_suggest__label">来自历史</div>';
                    matched.forEach(function (h) {
                        html += '<a class="ecod_suggest__item is-hist" href="#" data-ecod-suggest-hist="' + esc(h) + '">'
                            + Ecod.icon('clock', 13) + '<span>' + esc(h) + '</span></a>';
                    });
                }
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
                        setDropOpen(false);
                        return;
                    }
                }
                drop.innerHTML = html;
                setDropOpen(true);
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
                    setDropOpen(false);
                    return;
                }
                /* ↑↓ 在联想项间移动焦点，进结果后回车直达（combobox 键盘约定） */
                if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
                if (drop.hidden) return;
                var items = Array.prototype.slice.call(drop.querySelectorAll('a.ecod_suggest__item'));
                if (!items.length) return;
                e.preventDefault();
                var cur = items.indexOf(document.activeElement);
                var next = cur < 0
                    ? (e.key === 'ArrowDown' ? 0 : items.length - 1)
                    : (e.key === 'ArrowDown' ? (cur + 1) % items.length : (cur - 1 + items.length) % items.length);
                items[next].focus();
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
                if (!form.contains(e.target)) setDropOpen(false);
            });
        });
    };

    /* ---------- 搜索结果页落点补记历史 ---------- */
    /* 任何途径落到搜索结果页（书签 / 分享链接 / 表单回车直达）都补记一次，
       不依赖搜索面板、导航下拉的提交时点；与 pushHist 的去重天然幂等 */
    Ecod.initSearchRecord = function () {
        var q = '';
        var path = location.pathname || '';
        try {
            var params = new URLSearchParams(location.search);
            q = String(params.get('s') || params.get('keywords') || '').trim();
        } catch (e) {
            /* 老引擎没有 URLSearchParams 则不做查询串解析 */
        }
        if (!q) {
            var m = path.match(/\/search\/([^/]+)\/?$/);
            if (m) {
                try { q = decodeURIComponent(m[1]).trim(); } catch (e) { q = ''; }
            }
        }
        if (q) pushHist(q);
    };

    /* ---------- 首页轮播 ---------- */
    Ecod.initSlider = function () {
        document.querySelectorAll('[data-ecod-slider]').forEach(function (slider) {
            var slides = slider.querySelectorAll('.ecod_slider__slide');
            var dots = slider.querySelectorAll('[data-ecod-slider-dots] button');
            var glowWrap = slider.parentElement && slider.parentElement.classList.contains('ecod_sliderwrap')
                ? slider.parentElement
                : null;

            /* 取当前封面做外发光背景，切换时同步（封面在内联变量 --ecod-cover 上） */
            function syncGlow(slide) {
                if (!glowWrap || !slide) return;
                var bg = slide.style.getPropertyValue('--ecod-cover');
                if (bg) glowWrap.style.setProperty('--ecod-slider-bg', bg);
                else glowWrap.style.removeProperty('--ecod-slider-bg');
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

        /* 加载效果变体由后台「外观 → 浏览体验 → 页面加载效果」决定，
           <html data-loadfx> 上只挂值，具体动效全在 ecod.css §16；
           none = 不想要任何指示器，连宿主元素都不建 */
        var bar = document.querySelector('.ecod_pjaxbar');
        var fx = document.documentElement.getAttribute('data-loadfx') || 'bar';
        if (fx === 'none') {
            if (bar) bar.remove();
            bar = null;
        } else if (!bar) {
            bar = document.createElement('div');
            bar.className = 'ecod_pjaxbar';
            bar.setAttribute('aria-hidden', 'true');
            document.body.appendChild(bar);
        }

        /* 指示器时序：点击即同步亮相，不做延迟出现——本站有 90ms 悬停预取，
           命中时换页总耗时只有二三十毫秒，任何「超过阈值才出现」的延迟方案
           都会在完成回调里被取消，快站永远轮不上场（已踩坑）。
           每次换页保底停留 FX_MIN，读作「页面过渡」而非等待，加载越久停越久：
           bar 是细条 200ms 快闪；ring/veil/logo 保底 360ms。
           收尾走 is-done 完成态动画（冲满 / 回弹 / 显影）再整块撤掉。
           data-loadbusy 与 is-loading 同步挂到 <html> 上：logo 变体不经过
           pjaxbar 元素（它在 body 末尾，CSS 够不着它前面的吸顶导航） */
        var FX_MIN = fx === 'bar' ? 200 : 360;
        var fxEndT = null, fxShownAt = 0, fxShown = false;
        function fxStart() {
            if (!bar) return;
            clearTimeout(fxEndT);
            if (fxShown) {
                /* 上次收尾未完又开始新导航：回到进行态 */
                document.documentElement.setAttribute('data-loadbusy', '');
                bar.classList.remove('is-done');
                return;
            }
            fxShown = true;
            fxShownAt = Date.now();
            document.documentElement.setAttribute('data-loadbusy', '');
            bar.classList.add('is-loading');
        }
        function fxDone() {
            if (!bar || !fxShown) return;
            fxEndT = setTimeout(function () {
                document.documentElement.removeAttribute('data-loadbusy');
                bar.classList.add('is-done');
                fxEndT = setTimeout(function () {
                    fxShown = false;
                    bar.classList.remove('is-loading', 'is-done');
                }, 420);
            }, Math.max(0, FX_MIN - (Date.now() - fxShownAt)));
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
            fxStart();
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
                /* 整段替换 + 收尾：包成函数，供 View Transition 快照期统一调用 */
                var applyPage = function () {
                    if (doc.title) document.title = doc.title;
                    cur.replaceWith(next);

                    /* 本页已成为「当前页」，后续离开时位置记到它名下 */
                    lastHref = url.split('#')[0];

                    /* 导航栏在 shell 之外，无刷新跳转不会替换它：
                       目标页把高亮地址放在 shell 的 data-ecod-nav-active 上，据此同步 */
                    syncNav(next);

                    if (push) history.pushState({ ecod: 1 }, '', url);

                    /* 目标页是文章/独立页时，先确保内容页增强包就位再跑初始化；
                       跨族换页先补齐缺失的族 CSS（onload 后）再 refresh */
                    Ecod.ensurePageCss(function () {
                        Ecod.ensurePostBundle(function () {
                            Ecod.refresh();
                        });
                    });

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
                };

                /* 支持 View Transitions 且未开启减动效时进快照期（新旧页交叉淡化）；
                   否则同步执行，行为与旧版完全一致（渐进增强，零降级成本） */
                if (document.startViewTransition && !Ecod.reduceMotion()) {
                    document.startViewTransition(applyPage);
                } else {
                    applyPage();
                }
            }).catch(function (err) {
                if (my !== seq || (err && err.name === 'AbortError')) return;
                if (!jumped) location.href = url;
            }).then(function () {
                if (my !== seq) return;
                busy = false;
                controller = null;
                fxDone();
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

            /* 已完成分两种：真加载完，或已经失败（缓存 404 / 坏地址在脚本执行前就报错）。
               失败态必须这里补判——error 事件不会再发，只靠监听会永久停在裂图。 */
            if (img.complete) {
                img.classList.add(img.naturalWidth > 0 ? 'is-loaded' : 'is-error');
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

    /* ---------- 图片失败兜底：失败图统一打 is-error；头像换渐变首字圆 ---------- */
    /* 单张图的失败处理。头像堆叠无名字可用时退化成纯渐变圆（与无头像 <i> 同构）；
       其余图统一标 is-error，由 CSS 决定隐藏裂图本体（卡片）或整块撤下（正文）。 */
    Ecod._imgFailed = function (img) {
        if (!img || img.tagName !== 'IMG') return;
        if (img.closest('.ecod_likers__av') || img.closest('.ecod_poll__avs')) {
            var ph = document.createElement('i');
            if (img.dataset.initial) ph.textContent = img.dataset.initial;
            img.replaceWith(ph);
            return;
        }
        img.classList.add('is-error');
    };

    Ecod.initImgError = function () {
        if (!Ecod._imgErrorBound) {
            Ecod._imgErrorBound = true;
            /* error 不冒泡，必须 capture；document 级委托对 PJAX 换页后的新图同样生效 */
            document.addEventListener('error', function (e) {
                Ecod._imgFailed(e.target);
            }, true);
        }
        /* 委托绑定前就已失败的图（解析阶段报错 / 缓存 404）补扫一次。
           必须用静态 NodeList：_imgFailed 会把头像 img 换成 <i>，
           document.images 是活集合，边遍历边删会漏掉后一张。 */
        Array.prototype.forEach.call(document.querySelectorAll('img'), function (img) {
            if (img.complete && img.naturalWidth === 0) {
                Ecod._imgFailed(img);
            }
        });
    };

    /* ---------- 列表入场动效 ---------- */
    /* 依赖整包样式表的动画规则（.ecod-reveal 的 opacity:0 等）——
       完整 CSS 异步加载时必须等 data-ecod-cssready 标记就位再初始化，
       否则「加类 → 样式晚到 → 内容先隐再淡入」造成频闪。
       阻塞加载模式（无 dist）在 head 里就已打标记，此处直接执行。 */
    /* 系统「减少动态效果」：所有 JS 驱动的动效统一走这里，勿各自 matchMedia */
    Ecod.reduceMotion = function () {
        return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    };

    /* JS 侧程序化滚动统一走这里：reduce-motion 用户返回 auto，
       避免 behavior:'smooth' 绕过 CSS 的滚动动效降级 */
    Ecod.scrollBehavior = function () {
        return Ecod.reduceMotion() ? 'auto' : 'smooth';
    };

    /* 首屏整页加载不加入场动效：内容已在关键 CSS 下画出第一帧，
       这时再补 .ecod-reveal（opacity:0）等于「先显示 → 藏起来 → 淡入」，
       刷新时就是一次整页闪动。只有站内换页 / 加载更多才铺动画：
       _animArmed 在首屏初始化跑完后置位（见文件末尾 DOMContentLoaded）。 */
    Ecod.initAnimate = function () {
        if (!Ecod._animArmed) return;
        var run = function () { ecodAnimateRun(); };
        if (document.documentElement.getAttribute('data-ecod-cssready') === '1') {
            run();
            return;
        }
        document.addEventListener('ecod:cssready', run, { once: true });
    };

    /* 解除启动期过渡禁用（.ecod-booting 见 header.php / ecod.css §1）。
       关键 CSS 与异步换入的完整样式表在链接色、按钮底色等属性上取值不同，
       完整表到位时这些属性会变一次；此时 transition 若已生效就会整站渐变，
       表现为刷新时「文字/色块闪一下」。等完整样式应用且完成一次绘制后
       （双 rAF）再放开，属性值已落到终态，不会触发过渡。
       1.5s 兜底：完整表迟迟不到也不能永久禁用过渡。 */
    function ecodUnboot() {
        var el = document.documentElement;
        var done = false;
        var unboot = function () {
            if (done) return;
            done = true;
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    el.classList.remove('ecod-booting');
                });
            });
        };
        if (el.getAttribute('data-ecod-cssready') === '1') {
            unboot();
        } else {
            document.addEventListener('ecod:cssready', unboot, { once: true });
        }
        setTimeout(unboot, 1500);
    }

    function ecodAnimateRun() {
        var mode = document.documentElement.getAttribute('data-anim');
        if (!mode || mode === 'none' || !('IntersectionObserver' in window)) {
            return;
        }

        var list = document.querySelectorAll('.ecod_cards > *, .ecod_main > section, .ecod_friendgrid > .ecod_friend, .ecod_aside > *');
        if (!list.length) {
            return;
        }

        /* 级联按「同一帧进入视口的批次」计算序号，而非全列表序号：
           首批六七张卡依次踩点入场；往下滚动时单独进屏的卡片不再背着
           全列表的旧序号（最多 270ms）冷场，批次内 60ms 步长封顶 180ms */
        var io = new IntersectionObserver(function (entries) {
            var batch = 0;
            Array.prototype.forEach.call(entries, function (entry) {
                if (!entry.isIntersecting) {
                    return;
                }
                var el = entry.target;
                var delay = Math.min(batch * 60, 180);
                batch++;
                io.unobserve(el);

                /* delay 必须在同类改动（is-in）同帧写入：两处样式变更同属一次
                   重算，过渡才按该 delay 启动 */
                el.style.transitionDelay = delay + 'ms';
                el.classList.add('is-in');

                /* 入场结束后必须清掉 inline transition-delay：
                   transition-delay 作用于该元素的「所有」过渡，残留会让卡片 hover 的
                   transform / 阴影最多等 180ms 才响应，手感发钝。 */
                var cleared = false;
                var clearDelay = function () {
                    if (cleared) return;
                    cleared = true;
                    el.style.transitionDelay = '';
                    el.removeEventListener('transitionend', onEnd);
                };
                var onEnd = function (e) {
                    if (e.target === el && e.propertyName === 'opacity') clearDelay();
                };
                el.addEventListener('transitionend', onEnd);
                /* 兜底：过渡可能不触发（reduced-motion / 元素瞬间出视口），定时清。
                    余量按最长位移档（blur 位移 .74s）+ 帧余量计 */
                setTimeout(clearDelay, delay + 840);
            });
        }, { rootMargin: '0px 0px -6% 0px', threshold: 0.04 });

        Array.prototype.forEach.call(list, function (el) {
            if (el.classList.contains('is-in') || el.hasAttribute('data-ecod-noanim')) {
                return;
            }
            el.classList.add('ecod-reveal');
            io.observe(el);
        });
    }

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

    /* ---------- 导航溢出自动收纳「更多」 ----------
     * 后台 ENavMax 只是手动上限；这里是兜底：视口放不下时把尾部条目
     * 收进「更多」下拉，窗口变宽再自动还原。 */
    Ecod.initNavOverflow = function () {
        var nav = document.querySelector('.ecod_nav');
        if (!nav) return;
        var links = nav.querySelector('.ecod_nav__links');
        /* 搜索入口两种形态：常驻表单（ESearchPanel=off）或操作簇图标按钮 */
        var search = nav.querySelector('.ecod_nav__search, .ecod_nav__searchbtn');
        if (!links || !search) return;

        var CHEVRON = '<svg class="ecod_icon" width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M10.5858 6.34317L12 4.92896L19.0711 12L12 19.0711L10.5858 17.6569L16.2427 12L10.5858 6.34317Z"/></svg>';
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

        /* 登录页大号陪伴球：卡片右侧空区（≥1320px 显示），好奇注视 + 点击自旋；
           登录提交/失败的情绪反馈也由它承担（表单旁不再放小球） */
        var petEl = document.querySelector('[data-ecod-login-pet]');
        var loginPet = null;
        if (petEl && !petEl.dataset.petReady) {
            petEl.dataset.petReady = '1';
            loginPet = Ecod.ballMount(petEl, { emotion: '03', size: 150 });
            once(petEl, 'loginPetClick', 'click', function () {
                loginPet.spin(2);
                loginPet.flash('13', 1600);
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
                if (loginPet) loginPet.set('32');
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
                    if (loginPet) loginPet.flash('34', 2600);
                    capRefresh(lf);
                    if (btn) { btn.disabled = false; btn.textContent = '登 录'; }
                }).catch(function () {
                    if (msg) { msg.textContent = '网络异常，请重试'; msg.hidden = false; msg.classList.add('is-bad'); }
                    if (loginPet) loginPet.flash('34', 2600);
                    capRefresh(lf);
                    if (btn) { btn.disabled = false; btn.textContent = '登 录'; }
                });
            });
        });

        /* 全站登录弹窗：事件委托，PJAX 换页后入口链接被替换也能继续用。
           Esc / 焦点陷阱 / 滚动锁 / 焦点还原由弹层原语承担 */
        var authModal = document.querySelector('[data-ecod-authmodal]');
        if (authModal && !authModal.dataset.ecodBound) {
            authModal.dataset.ecodBound = '1';
            function authModalClose() {
                authModal.hidden = true;
                Ecod.dialogClose(authModal);
            }
            document.addEventListener('click', function (e) {
                var opener = e.target.closest('[data-ecod-login-open]');
                if (opener && authModal) {
                    e.preventDefault();
                    authModal.hidden = false;
                    /* 打开即换一张验证码，避免用的是上一次的过期图 */
                    var modalForm = authModal.querySelector('[data-ecod-login]');
                    if (modalForm) { capRefresh(modalForm); }
                    Ecod.dialogOpen(authModal, authModal.querySelector('[name="name"]'), authModalClose);
                    return;
                }
                if (!authModal.hidden && e.target.closest('[data-ecod-authmodal-close]')) {
                    authModalClose();
                }
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
                /* 获取邮箱验证码（purpose=register，与找回密码共用 send_code 入口） */
                var regSend = regForm.querySelector('[data-ecod-reg-send]');
                if (regSend) {
                    var regSendTimer = null;
                    regSend.addEventListener('click', function () {
                        if (regSendTimer) return;
                        var mailEl = regForm.querySelector('[name="mail"]');
                        var mail = mailEl ? mailEl.value.trim() : '';
                        if (!mail) {
                            authMsg('register', '请先填写邮箱', false);
                            if (mailEl) mailEl.focus();
                            return;
                        }
                        authMsg('register', '', true);
                        var btn = regSend;
                        btn.disabled = true;
                        btn.textContent = '发送中…';
                        var fd = new FormData();
                        fd.append('mail', mail);
                        fd.append('purpose', 'register');
                        if (authToken) fd.append('token', authToken);
                        accPost('send_code', fd).then(function (data) {
                            if (data && data.ok) {
                                authMsg('register', '验证码已发送，请查收邮箱（注意垃圾箱）', true);
                                var left = 60;
                                regSendTimer = setInterval(function () {
                                    left -= 1;
                                    if (left <= 0) {
                                        clearInterval(regSendTimer);
                                        regSendTimer = null;
                                        btn.disabled = false;
                                        btn.textContent = '重新获取';
                                    } else {
                                        btn.textContent = left + ' 秒';
                                    }
                                }, 1000);
                            } else {
                                authMsg('register', (data && data.error) || '发送失败', false);
                                btn.disabled = false;
                                btn.textContent = '获取验证码';
                            }
                        }).catch(function () {
                            authMsg('register', '网络异常，请重试', false);
                            btn.disabled = false;
                            btn.textContent = '获取验证码';
                        });
                    });
                }
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
                        /* 图形验证码一次一换：失败必须换图，否则重试必然失败 */
                        capRefresh(regForm);
                        if (btn) { btn.disabled = false; btn.textContent = '注 册'; }
                    }).catch(function () {
                        authMsg('register', '网络异常，请重试', false);
                        capRefresh(regForm);
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
                    Ecod.dialogClose(el);
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
                    Ecod.dialogOpen(el, null, close);
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
                    /* Esc 关闭由弹层原语承担 */
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

            /* 个人中心封面：上传 / 移除（hero 区在 data-ecod-account 外，document 级查找） */
            var coverInput = document.querySelector('[data-ecod-cover-input]');
            if (coverInput && !coverInput.dataset.ecodBound) {
                coverInput.dataset.ecodBound = '1';
                coverInput.addEventListener('change', function () {
                    var file = coverInput.files && coverInput.files[0];
                    coverInput.value = '';
                    if (!file) return;
                    if (file.size > 8 * 1024 * 1024) { accMsg('avatar', '封面不能超过 8MB', false); return; }
                    var body = new FormData();
                    body.append('cover', file, file.name || 'cover.jpg');
                    body.append('token', token);
                    accMsg('avatar', '封面上传中…', true);
                    accPost('cover_upload', body).then(function (data) {
                        if (data && data.ok) {
                            accMsg('avatar', '封面已更新，刷新页面…', true);
                            setTimeout(function () { location.reload(); }, 600);
                        } else {
                            accMsg('avatar', (data && data.error) || '上传失败', false);
                        }
                    }).catch(function () {
                        accMsg('avatar', '网络异常，请重试', false);
                    });
                });
            }
            var coverRemove = document.querySelector('[data-ecod-cover-remove]');
            if (coverRemove && !coverRemove.dataset.ecodBound) {
                coverRemove.dataset.ecodBound = '1';
                coverRemove.addEventListener('click', function () {
                    var body = new FormData();
                    body.append('token', token);
                    accPost('cover_remove', body).then(function (data) {
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

    /* ---------- 站内消息：header 铃铛 + 个人中心「我的消息」 ---------- */
    /* 登录用户才有铃铛（header 模板判定）；角标轮询 notif_unread，展开拉 notif_list。
       轮询间隔 90s，够及时又不打扰；个人中心面板复用同一个渲染器。 */
    Ecod.initNotifs = function () {
        var bell = document.querySelector('[data-ecod-bell]');
        var notifListHosts = document.querySelectorAll('[data-ecod-notif-list]');
        if (!bell && !notifListHosts.length) return;
        if (bell && bell.dataset.notifReady === '1') return;
        if (bell) bell.dataset.notifReady = '1';

        var ICONS = { reply: 'comment', friend: 'link', like: 'heart', fav: 'star', notice: 'bell' };
        var api = CFG.api || '';

        function fmtTime(ts) {
            var d = Date.now() / 1000 - (Number(ts) || 0);
            if (!isFinite(d) || d < 0) d = 0;
            if (d < 60) return '刚刚';
            if (d < 3600) return Math.floor(d / 60) + ' 分钟前';
            if (d < 86400) return Math.floor(d / 3600) + ' 小时前';
            if (d < 86400 * 30) return Math.floor(d / 86400) + ' 天前';
            var t = new Date((Number(ts) || Date.now() / 1000) * 1000);
            return (t.getMonth() + 1) + '-' + t.getDate();
        }

        function setBadge(n) {
            Array.prototype.forEach.call(document.querySelectorAll('[data-ecod-bell-badge]'), function (b) {
                if (!n) {
                    b.hidden = true;
                    b.textContent = '';
                    return;
                }
                b.hidden = false;
                b.textContent = n > 99 ? '99+' : String(n);
            });
        }

        /* 未读数同步到个人中心 tab 的角标 */
        function setTabCount(n) {
            Array.prototype.forEach.call(document.querySelectorAll('[data-ecod-uc-count="notifs"]'), function (em) {
                if (!n) {
                    em.hidden = true;
                    em.textContent = '0';
                    return;
                }
                em.hidden = false;
                em.textContent = n > 99 ? '99+' : String(n);
            });
        }

        function renderItems(items) {
            if (!items.length) {
                return '<div class="ecod_notif__empty">还没有消息收到回复、点赞或公告时会出现在这里</div>';
            }
            var html = '';
            items.forEach(function (it) {
                var unread = Number(it.is_read) ? '' : ' is-unread';
                var link = it.link || '';
                html += '<a class="ecod_notif__item' + unread + '"'
                    + (link ? ' href="' + esc(link) + '"' : ' href="javascript:void(0)"')
                    + ' data-ecod-notif-item="' + Number(it.id) + '">'
                    + '<span class="ecod_notif__ic">' + Ecod.icon(ICONS[it.type] || 'bell', 14) + '</span>'
                    + '<span class="ecod_notif__main">'
                    + '<b class="ecod_notif__title">' + escHtml(it.title) + '</b>'
                    + (it.body ? '<span class="ecod_notif__body">' + escHtml(it.body) + '</span>' : '')
                    + '</span>'
                    + '<time class="ecod_notif__time">' + fmtTime(it.created) + '</time>'
                    + '</a>';
            });
            return html;
        }

        function fetchList() {
            fetch(api + '?action=notif_list&limit=20', { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.ok) return;
                    var html = renderItems(data.list || []);
                    Array.prototype.forEach.call(notifListHosts.length ? notifListHosts : document.querySelectorAll('[data-ecod-notif-list]'), function (host) {
                        host.innerHTML = html;
                    });
                    setBadge(data.unread || 0);
                    setTabCount(data.unread || 0);
                })
                .catch(function () {});
        }

        function post(action, params) {
            var body = new URLSearchParams(params || {});
            return fetch(api + '?action=' + action, {
                method: 'POST',
                body: body,
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); }).catch(function () { return null; });
        }

        function refreshBadge() {
            fetch(api + '?action=notif_unread', { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.ok) {
                        setBadge(data.unread || 0);
                        setTabCount(data.unread || 0);
                    }
                })
                .catch(function () {});
        }

        /* 铃铛：展开拉列表 + 顶栏轮询 */
        if (bell) {
            bell.addEventListener('toggle', function () {
                if (bell.open) fetchList();
            });

            /* 桌面 hover 即展开（与用户面板同语言，点外收起由 initPopDismiss 负责） */
            if (hoverCapable()) bindHoverDetails(bell);

            refreshBadge();
            setInterval(refreshBadge, 90000);
        }

        /* 消息条目点击 / 全部已读 / 清空：文档级委托一次注册，
           铃铛下拉与个人中心「我的消息」面板两处通用 */
        if (!Ecod._notifDoc) {
            Ecod._notifDoc = 1;
            document.addEventListener('click', function (e) {
                var readAll = e.target.closest ? e.target.closest('[data-ecod-notif-readall]') : null;
                if (readAll) {
                    e.preventDefault();
                    post('notif_read', { all: 1 }).then(function (data) {
                        if (data && data.ok) {
                            setBadge(0);
                            setTabCount(0);
                            fetchList();
                            if (Ecod.refreshMsgCenter) Ecod.refreshMsgCenter();
                        }
                    });
                    return;
                }
                var clear = e.target.closest ? e.target.closest('[data-ecod-notif-clear]') : null;
                if (clear) {
                    e.preventDefault();
                    post('notif_clear', {}).then(function () {
                        fetchList();
                        refreshBadge();
                        if (Ecod.refreshMsgCenter) Ecod.refreshMsgCenter();
                    });
                    return;
                }
                var item = e.target.closest ? e.target.closest('[data-ecod-notif-item]') : null;
                if (item && item.classList.contains('is-unread')) {
                    item.classList.remove('is-unread');
                    post('notif_read', { id: item.getAttribute('data-ecod-notif-item') }).then(function (data) {
                        if (data && data.ok) setBadge(data.unread);
                    });
                }
            });
        }

        /* 个人中心「我的消息」面板：进入页面就拉一次（tab 隐藏不影响）
           消息中心改版后用独立容器 [data-ecod-msgc-list]，不再共用铃铛的渲染器 */
        if (notifListHosts.length) {
            fetchList();
        }

        /* PJAX 换页后 DOM 可能是新的：重取手势 */
        notifListHosts = [];
    };

    /* ------------------------------------------------------------------
     * 实时动态（右下角槽位步进播报，完全对标 guaqi event-ticker）
     *
     * 结构：单份列表，每张卡绝对定位在 56px 槽位上（translateY），超出
     * 视口的卡被 overflow 裁掉。步进：顶部卡上滑一格 + 淡出模糊出场，
     * 其余卡各上移一格，320ms 后离场卡禁过渡回收至队尾再淡入——全程
     * guaqi 同款过渡 transform .3s cubic-bezier(.22,.86,.2,1) / opacity·filter .22s。
     * 整件 pointer-events:none 纯装饰。站长开启「自动刷新」后根元素才带
     * data-ecod-ticker（秒数），按它轮询 api?action=ticker 换血列表；
     * reduced-motion 不步进（静态展示前几条），页面隐藏时暂停步进。
     * ---------------------------------------------------------------- */
    Ecod.initTicker = function () {
        var root = document.querySelector('.ecod_fticker');
        if (!root || root.dataset.tickerReady === '1') return;
        root.dataset.tickerReady = '1';

        /* 避让右下既有浮动件（站宠球 / 回到顶部）：量它们的布局尺寸 + CSS bottom
           （不用 rect——球引擎加载前后有缩放动画，rect 是瞬态值），留 12px 空隙；
           站宠球按需加载后会换尺寸，3s 后复测一次。都不在时用 guaqi 同款 80px 兜底 */
        function liftBottom() {
            var lift = 0;
            ['.ecod_ballpet', '.ecod_totop'].forEach(function (sel) {
                var el = document.querySelector(sel);
                if (!el) return;
                var cs = getComputedStyle(el);
                if (cs.display === 'none' || cs.visibility === 'hidden') return;
                var h = el.offsetHeight, b = parseFloat(cs.bottom);
                if (h > 0 && !isNaN(b)) lift = Math.max(lift, b + h + 12);
            });
            root.style.bottom = (lift || 80) + 'px';
        }
        liftBottom();
        setTimeout(liftBottom, 3000);

        var list = root.querySelector('.ecod_fticker__list');
        var viewport = root.querySelector('.ecod_fticker__viewport');
        var SLOT = 56;
        var cards = list ? Array.prototype.slice.call(list.children) : [];

        function layout() {
            cards.forEach(function (c, i) {
                c.style.transform = 'translateY(' + (i * SLOT) + 'px)';
                c.style.opacity = '1';
                c.style.filter = 'none';
            });
        }

        function step() {
            if (paused || document.hidden || cards.length < 2) return;
            var first = cards[0];
            first.style.opacity = '0';
            first.style.filter = 'blur(3px)';
            first.style.transform = 'translateY(-' + SLOT + 'px)';
            for (var i = 1; i < cards.length; i++) {
                cards[i].style.transform = 'translateY(' + ((i - 1) * SLOT) + 'px)';
            }
            setTimeout(function () {
                first.style.transition = 'none';
                first.style.transform = 'translateY(' + ((cards.length - 1) * SLOT) + 'px)';
                first.style.filter = 'none';
                void first.offsetWidth;
                first.style.transition = '';
                first.style.opacity = '1';
                cards.push(cards.shift());
            }, 320);
        }

        /* 悬停在可点击卡上时暂停步进：不然正要点击时卡被挪走（委托监听，
           轮询换血重建卡片后依然有效） */
        var paused = false;
        root.addEventListener('mouseover', function (e) {
            if (e.target.closest && e.target.closest('a.ecod_ftick')) paused = true;
        });
        root.addEventListener('mouseout', function (e) {
            if (e.target.closest && e.target.closest('a.ecod_ftick')) paused = false;
        });

        if (cards.length > 1) {
            layout();
            var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (!reduced) {
                /* 可见槽位数换算步进节奏：视口越高转得越从容，4s 为基准 */
                var visible = viewport ? Math.max(1, Math.round(viewport.clientHeight / SLOT)) : 4;
                setInterval(step, Math.max(3000, visible * 1000));
            }
        }

        /* 以下为站长开启「自动刷新」后的轮询换血（未开启时到此为止） */
        var api = root.getAttribute('data-api') || CFG.api || '';
        if (!list || !api) return;
        if (!root.hasAttribute('data-ecod-ticker')) return;

        var secs = parseInt(root.getAttribute('data-ecod-ticker'), 10);
        if (!secs || secs < 60) secs = 60;
        var sources = root.getAttribute('data-sources') || '';
        var limit = root.getAttribute('data-limit') || '8';

        function fmtTime(ts) {
            var d = Date.now() / 1000 - (Number(ts) || 0);
            if (!isFinite(d) || d < 0) d = 0;
            if (d < 60) return '刚刚';
            if (d < 3600) return Math.floor(d / 60) + ' 分钟前';
            if (d < 86400) return Math.floor(d / 3600) + ' 小时前';
            if (d < 86400 * 30) return Math.floor(d / 86400) + ' 天前';
            var t = new Date((Number(ts) || Date.now() / 1000) * 1000);
            return (t.getMonth() + 1) + '-' + t.getDate();
        }

        /* 与 footer/ticker.php 的 SSR 输出同构，换血前后结构一致；
           有 href 出 <a>（PJAX 跳转对应文章），无 href 出 span */
        function itemHtml(it) {
            var ava = it.avatar
                ? '<img src="' + esc(it.avatar) + '" alt="" width="24" height="24" loading="lazy" decoding="async">'
                : '<i>' + esc(String(it.name || '').charAt(0)) + '</i>';
            var text = esc(it.text || '');
            if (Number(it.delta)) text += ' <b class="ecod_ftick__delta">+' + Number(it.delta) + '</b>';
            var inner = '<span class="ecod_ftick__avatar" aria-hidden="true">' + ava + '</span>'
                + '<span class="ecod_ftick__card"><span class="ecod_ftick__meta"><b>' + esc(it.name) + '</b>'
                + '<i aria-hidden="true">·</i><time>' + esc(fmtTime(it.created)) + '</time></span>'
                + '<span class="ecod_ftick__text">' + text + '</span></span>';
            if (!it.href) return '<span class="ecod_ftick">' + inner + '</span>';
            return '<a class="ecod_ftick" href="' + esc(it.href) + '">' + inner + '</a>';
        }

        function build(items) {
            var html = '';
            items.forEach(function (it) { html += itemHtml(it); });
            list.innerHTML = html;
            cards = Array.prototype.slice.call(list.children);
            layout();
        }

        var last = '';
        function tick() {
            fetch(api + '?action=ticker&sources=' + encodeURIComponent(sources) + '&limit=' + encodeURIComponent(limit), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.ok || !Array.isArray(data.items) || !data.items.length) return;
                    var sig = JSON.stringify(data.items);
                    if (sig === last) return;
                    last = sig;
                    build(data.items);
                })
                .catch(function () { });
        }

        tick();
        setInterval(tick, secs * 1000);
    };

    /* ------------------------------------------------------------------
     * 任务中心
     *
     * 服务端直出首屏（进度是实时 COUNT 出来的，没有缓存要失效）；
     * 领取走 api?action=task_claim，成功后重拉 task_list 整体同步视图——
     * 比在 DOM 上算差量少一半分支，多一次请求换来「视图永远等于服务端」。
     * ---------------------------------------------------------------- */
    Ecod.initTasks = function () {
        var root = document.querySelector('[data-ecod-task]');
        if (!root || root.dataset.taskReady === '1') return;
        root.dataset.taskReady = '1';

        var api = root.dataset.api || CFG.api || '';
        var token = root.dataset.token || '';

        function post(action, params) {
            var body = new URLSearchParams(params || {});
            return fetch(api + '?action=' + action, {
                method: 'POST',
                body: body,
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); }).catch(function () { return null; });
        }

        function get(action, params) {
            var qs = new URLSearchParams(params || {});
            return fetch(api + '?action=' + action + '&' + qs.toString(), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); }).catch(function () { return null; });
        }

        function setTabCount(n) {
            Array.prototype.forEach.call(document.querySelectorAll('[data-ecod-uc-count="task"]'), function (em) {
                if (!n) {
                    em.hidden = true;
                    em.textContent = '0';
                    return;
                }
                em.hidden = false;
                em.textContent = n > 99 ? '99+' : String(n);
            });
        }

        function claimBtnHtml(item) {
            if (item.state === 'claimable') {
                return '<button type="button" class="ecod-btn ecod-btn--primary ecod-btn--sm"'
                    + ' data-ecod-task-claim="' + escHtml(item.key) + '">领取 +' + Number(item.reward) + '</button>';
            }
            if (item.state === 'claimed') {
                var doneTxt = item.threshold ? '已领取' : (item.need > 1 ? '已领 ' + item.claimed + '/' + item.need : '已领取');
                return '<em class="ecod_task__done">' + Ecod.icon('check', 13) + doneTxt + '</em>';
            }
            var lack = Math.max(0, item.need - item.progress);
            return '<button type="button" class="ecod-btn ecod-btn--ghost ecod-btn--sm" disabled title="未达标，领取会被拒绝">还差 '
                + lack + '</button>';
        }

        function sync(data) {
            if (!data || !data.ok) return;

            var bal = root.querySelector('[data-ecod-task-balance]');
            if (bal) bal.textContent = Number(data.balance || 0).toLocaleString('zh-CN');
            var streak = root.querySelector('[data-ecod-task-streak]');
            if (streak) streak.textContent = String(data.streak || 0);

            var today = data.today || { claimed: 0, total: 0 };
            var bar = root.querySelector('[data-ecod-task-todaybar]');
            if (bar) {
                var pct = today.total > 0 ? Math.round(today.claimed / today.total * 100) : 0;
                bar.style.width = pct + '%';
            }
            var num = root.querySelector('[data-ecod-task-todaynum]');
            if (num) num.textContent = today.claimed + ' / ' + today.total;

            var sign = root.querySelector('[data-ecod-task-signin]');
            if (sign) {
                sign.disabled = !!data.signed;
                /* 只改「已签」态：闪电换对勾 + 文案；未签到时奖励数由服务端渲染，前端不知道 ECrCheckin */
                if (data.signed) {
                    sign.innerHTML = Ecod.icon('check', 14) + '<span data-ecod-task-signin-text>今日已签到</span>';
                }
            }

            var ready = 0;
            (data.groups || []).forEach(function (group) {
                var box = root.querySelector('[data-group="' + group.key + '"]');
                if (!box) return;
                var doneEl = box.querySelector('[data-ecod-task-gdone]');
                if (doneEl) doneEl.textContent = '已完成 ' + group.done + '/' + group.total;

                (group.items || []).forEach(function (item) {
                    if (item.state === 'claimable') ready++;
                    var card = box.querySelector('.ecod_task__card[data-task="' + item.key + '"]');
                    if (!card) return;
                    card.dataset.state = item.state;
                    card.classList.toggle('is-claimable', item.state === 'claimable');
                    card.classList.toggle('is-claimed', item.state === 'claimed');

                    var pb = card.querySelector('[data-ecod-task-pbar]');
                    if (pb) pb.style.width = Math.round(item.progress / Math.max(1, item.need) * 100) + '%';
                    var pn = card.querySelector('[data-ecod-task-pnum]');
                    if (pn) pn.textContent = item.progress + '/' + item.need;
                    var ops = card.querySelector('[data-ecod-task-ops]');
                    if (ops) ops.innerHTML = claimBtnHtml(item);
                });
            });

            setTabCount(ready);
        }

        function refresh() {
            return get('task_list').then(function (data) {
                if (data && data.ok) sync(data);
                return data;
            });
        }

        root.addEventListener('click', function (e) {
            var claim = e.target.closest ? e.target.closest('[data-ecod-task-claim]') : null;
            if (claim) {
                if (claim.dataset.busy) return;
                claim.dataset.busy = '1';
                claim.disabled = true;
                var key = claim.getAttribute('data-ecod-task-claim');
                post('task_claim', { key: key, token: token }).then(function (data) {
                    delete claim.dataset.busy;
                    claim.disabled = false;
                    if (!data || !data.ok) {
                        Ecod.toast((data && data.error) || '领取失败，请稍后重试', 'bad');
                        /* 已领取 / 未达标都要把视图拉回真实状态 */
                        refresh();
                        return;
                    }
                    Ecod.toast('领取成功 +' + data.delta, 'ok');
                    Ecod.ballCelebrate();
                    refresh();
                });
                return;
            }

            var signin = e.target.closest ? e.target.closest('[data-ecod-task-signin]') : null;
            if (signin && !signin.disabled) {
                signin.disabled = true;
                post('signin', { token: token }).then(function (data) {
                    if (!data || !data.ok) {
                        signin.disabled = false;
                        Ecod.toast((data && data.error) || '签到失败', 'bad');
                        return;
                    }
                    Ecod.toast('签到成功' + (data.delta ? ' +' + data.delta : ''), 'ok');
                    Ecod.ballCelebrate();
                    refresh();
                });
            }
        });
    };

    /* ------------------------------------------------------------------
     * 消息中心
     *
     * 一次拉 50 条，分类筛选在前端做（type 只有 6 种，映射成 4 个 chip）；
     * 「加载更多」用 offset 增量取数 —— 表结构零改动。
     * ---------------------------------------------------------------- */
    Ecod.initMsgCenter = function () {
        var root = document.querySelector('[data-ecod-msgc]');
        if (!root || root.dataset.msgcReady === '1') return;
        root.dataset.msgcReady = '1';

        var api = root.dataset.api || CFG.api || '';
        var token = root.dataset.token || '';
        var listHost = root.querySelector('[data-ecod-msgc-list]');
        var moreBtn = root.querySelector('[data-ecod-msgc-more]');
        var endEl = root.querySelector('[data-ecod-msgc-end]');
        var chips = root.querySelectorAll('[data-ecod-msgc-filter]');

        var ICONS = { reply: 'comment', friend: 'link', like: 'heart', fav: 'star', notice: 'bell', ticket: 'headset' };
        var GROUP = { like: 'interact', fav: 'interact', reply: 'interact', notice: 'system', friend: 'system', ticket: 'system' };
        var LIMIT = 50;

        var state = { items: [], filter: 'all', offset: 0, hasMore: false, counts: {}, loading: false };

        function fmtTime(ts) {
            var d = Date.now() / 1000 - (Number(ts) || 0);
            if (!isFinite(d) || d < 0) d = 0;
            if (d < 60) return '刚刚';
            if (d < 3600) return Math.floor(d / 60) + ' 分钟前';
            if (d < 86400) return Math.floor(d / 3600) + ' 小时前';
            if (d < 86400 * 30) return Math.floor(d / 86400) + ' 天前';
            var t = new Date((Number(ts) || Date.now() / 1000) * 1000);
            return (t.getMonth() + 1) + '-' + t.getDate();
        }

        function paintCounts() {
            chips.forEach(function (chip) {
                var key = chip.getAttribute('data-ecod-msgc-filter');
                chip.classList.toggle('is-active', key === state.filter);
                var u = chip.querySelector('[data-ecod-msgc-count]');
                if (!u) return;
                var n = 0;
                if (key === 'all') n = state.counts.all || 0;
                else if (key === 'unread') n = state.counts.unread || 0;
                else n = state.counts[key] || 0;
                u.textContent = n > 99 ? '99+' : String(n);
            });
        }

        function visible() {
            return state.items.filter(function (it) {
                if (state.filter === 'all') return true;
                if (state.filter === 'unread') return !Number(it.is_read);
                return GROUP[it.type] === state.filter;
            });
        }

        function emptyHtml() {
            var text = state.filter === 'unread' ? '没有未读消息'
                : (state.filter === 'interact' ? '还没有互动消息' : (state.filter === 'system' ? '还没有系统消息' : '还没有消息'));
            return '<div class="ecod_msgc__empty">' + Ecod.icon('bell', 30)
                + '<span>' + text + '</span>'
                + '<span>收到回复、点赞、公告与工单回复后会出现在这里</span></div>';
        }

        function render() {
            if (!listHost) return;
            var items = visible();
            if (!items.length) {
                listHost.innerHTML = emptyHtml();
                if (moreBtn) moreBtn.hidden = true;
                if (endEl) endEl.hidden = true;
                return;
            }
            var html = '';
            items.forEach(function (it) {
                var unread = Number(it.is_read) ? '' : ' is-unread';
                html += '<div class="ecod_msgc__item' + unread + '"'
                    + ' data-ecod-msgc-item="' + Number(it.id) + '"'
                    + ' data-k="' + escHtml(it.type) + '">'
                    + '<span class="ecod_msgc__ic">' + Ecod.icon(ICONS[it.type] || 'bell', 15) + '</span>'
                    + '<span class="ecod_msgc__main">'
                    + '<b>' + escHtml(it.title) + '</b>'
                    + (it.body ? '<span>' + escHtml(it.body) + '</span>' : '')
                    + '</span>'
                    + '<time>' + fmtTime(it.created) + '</time>'
                    + '<div class="ecod_msgc__foot">'
                    + (it.body ? escHtml(it.body) : '')
                    + (it.link ? '<a href="' + escHtml(it.link) + '">查看详情 →</a>' : '')
                    + '</div>'
                    + '</div>';
            });
            listHost.innerHTML = html;

            if (moreBtn) moreBtn.hidden = !state.hasMore;
            if (endEl) endEl.hidden = state.hasMore || state.items.length === 0;
        }

        function refreshBadge(unread) {
            var n = Number(unread) || 0;
            Array.prototype.forEach.call(document.querySelectorAll('[data-ecod-bell-badge]'), function (b) {
                if (!n) { b.hidden = true; b.textContent = ''; return; }
                b.hidden = false;
                b.textContent = n > 99 ? '99+' : String(n);
            });
            Array.prototype.forEach.call(document.querySelectorAll('[data-ecod-uc-count="notifs"]'), function (em) {
                if (!n) { em.hidden = true; em.textContent = '0'; return; }
                em.hidden = false;
                em.textContent = n > 99 ? '99+' : String(n);
            });
        }

        function load(append) {
            if (state.loading) return;
            state.loading = true;
            if (moreBtn) moreBtn.disabled = true;
            var offset = append ? state.offset : 0;
            fetch(api + '?action=notif_list&limit=' + LIMIT + '&offset=' + offset, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    state.loading = false;
                    if (moreBtn) moreBtn.disabled = false;
                    if (!data || !data.ok) return;
                    var list = data.list || [];
                    state.items = append ? state.items.concat(list) : list;
                    state.offset = state.items.length;
                    state.hasMore = list.length >= LIMIT;
                    state.counts = data.counts || {};
                    paintCounts();
                    render();
                    refreshBadge(data.unread);
                })
                .catch(function () {
                    state.loading = false;
                    if (moreBtn) moreBtn.disabled = false;
                });
        }

        function post(action, params) {
            var body = new URLSearchParams(params || {});
            return fetch(api + '?action=' + action, {
                method: 'POST',
                body: body,
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); }).catch(function () { return null; });
        }

        Ecod.refreshMsgCenter = function () { load(false); };

        chips.forEach(function (chip) {
            chip.addEventListener('click', function () {
                state.filter = chip.getAttribute('data-ecod-msgc-filter');
                paintCounts();
                render();
            });
        });

        if (moreBtn) moreBtn.addEventListener('click', function () { load(true); });

        if (listHost) {
            listHost.addEventListener('click', function (e) {
                var item = e.target.closest ? e.target.closest('[data-ecod-msgc-item]') : null;
                if (!item) return;
                var link = e.target.closest ? e.target.closest('a') : null;
                if (link) {
                    /* 跳转前把这条标已读，回来才不会又亮着 */
                    if (item.classList.contains('is-unread')) {
                        post('notif_read', { id: item.getAttribute('data-ecod-msgc-item') }).then(function (data) {
                            if (data && data.ok) refreshBadge(data.unread);
                        });
                    }
                    return;
                }
                item.classList.toggle('is-open');
                if (item.classList.contains('is-unread')) {
                    item.classList.remove('is-unread');
                    state.counts.unread = Math.max(0, (state.counts.unread || 0) - 1);
                    post('notif_read', { id: item.getAttribute('data-ecod-msgc-item') }).then(function (data) {
                        if (data && data.ok) refreshBadge(data.unread);
                    });
                    paintCounts();
                }
            });
        }

        load(false);
    };

    /* ------------------------------------------------------------------
     * 工单
     *
     * 列表服务端直出（首屏即可见），展开时才拉详情；回复 / 关闭 / 新建
     * 之后重拉列表同步状态与统计，避免前端自己算派生状态算错。
     * ---------------------------------------------------------------- */
    Ecod.initTickets = function () {
        var root = document.querySelector('[data-ecod-ticket]');
        if (!root || root.dataset.tkReady === '1') return;
        root.dataset.tkReady = '1';

        var api = root.dataset.api || CFG.api || '';
        var token = root.dataset.token || '';
        var listBox = root.querySelector('[data-ecod-ticket-list]');
        var emptyBox = root.querySelector('[data-ecod-ticket-empty]');
        var form = root.querySelector('[data-ecod-ticket-form]');
        var msg = root.querySelector('[data-ecod-ticket-msg]');

        var STATE_TEXT = { pending: '待处理', replied: '已回复', resolved: '已解决', closed: '已关闭' };

        function post(action, params) {
            var body = new URLSearchParams(params || {});
            return fetch(api + '?action=' + action, {
                method: 'POST',
                body: body,
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); }).catch(function () { return null; });
        }

        function get(action, params) {
            var qs = new URLSearchParams(params || {});
            return fetch(api + '?action=' + action + '&' + qs.toString(), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); }).catch(function () { return null; });
        }

        function fmt(ts) {
            var t = new Date((Number(ts) || 0) * 1000);
            var p = function (n) { return n < 10 ? '0' + n : '' + n; };
            return (t.getMonth() + 1) + '-' + p(t.getDate()) + ' ' + p(t.getHours()) + ':' + p(t.getMinutes());
        }

        function showMsg(text, bad) {
            if (!msg) return;
            msg.hidden = false;
            msg.textContent = text;
            msg.classList.toggle('is-error', !!bad);
        }

        function hideMsg() {
            if (msg) msg.hidden = true;
        }

        function setStats(stats) {
            if (!stats) return;
            ['pending', 'replied', 'resolved'].forEach(function (k) {
                var el = root.querySelector('[data-ecod-ticket-stat="' + k + '"]');
                if (el) el.textContent = String(stats[k] || 0);
            });
        }

        function setTabCount(n) {
            Array.prototype.forEach.call(document.querySelectorAll('[data-ecod-uc-count="ticket"]'), function (em) {
                if (!n) { em.hidden = true; em.textContent = '0'; return; }
                em.hidden = false;
                em.textContent = n > 99 ? '99+' : String(n);
            });
        }

        function itemHtml(t) {
            return '<div class="ecod_ticket__item" data-id="' + Number(t.id) + '" data-derived="' + escHtml(t.derived) + '">'
                + '<button type="button" class="ecod_ticket__hd" data-ecod-ticket-toggle>'
                + '<span class="ecod_ticket__no">' + escHtml(t.no) + '</span>'
                + '<span class="ecod_ticket__main">'
                + '<b>' + escHtml(t.title) + '</b>'
                + '<span class="ecod_ticket__meta">'
                + (t.unread ? '<i class="ecod_ticket__dot"></i>' : '')
                + '<em>' + escHtml(t.catText) + '</em>'
                + ' · ' + Number(t.msgs) + ' 条消息'
                + ' · ' + fmt(t.updated)
                + '</span></span>'
                + '<span class="ecod_ticket__right">'
                + '<em class="ecod_ticket__state" data-s="' + escHtml(t.derived) + '">' + escHtml(STATE_TEXT[t.derived] || t.derived) + '</em>'
                + '<span class="ecod_ticket__chev">' + Ecod.icon('chevron-down', 14) + '</span>'
                + '</span></button>'
                + '<div class="ecod_ticket__thread" data-ecod-ticket-thread hidden></div>'
                + '</div>';
        }

        function reload() {
            return get('ticket_list').then(function (data) {
                if (!data || !data.ok) return;
                setStats(data.stats);
                var items = data.items || [];
                if (listBox) {
                    listBox.innerHTML = items.map(itemHtml).join('');
                    listBox.hidden = !items.length;
                }
                if (emptyBox) emptyBox.hidden = !!items.length;
                /* 未读角标 = 未读条目数（列表里 unread 字段） */
                var unread = items.filter(function (t) { return t.unread; }).length;
                setTabCount(unread);
            });
        }

        function threadHtml(data) {
            var t = data.ticket || {};
            var html = '';
            (data.msgs || []).forEach(function (m) {
                var me = m.side !== 'staff';
                html += '<div class="ecod_ticket__bub ecod_ticket__bub--' + (me ? 'me' : 'staff') + '">'
                    + '<span class="ecod_ticket__bub-meta">' + (me ? '我' : '<b>客服</b>')
                    + ' · ' + fmt(m.created) + '</span>'
                    + '<div class="ecod_ticket__bub-txt">' + escHtml(m.body) + '</div>'
                    + '</div>';
            });

            if (t.status === 'closed') {
                html += '<div class="ecod_ticket__closed">工单已关闭，如需继续请新建工单。</div>';
                return html;
            }

            html += '<form class="ecod_ticket__reply" data-ecod-tk-reply>'
                + '<textarea rows="3" maxlength="2000" placeholder="继续补充说明…" data-ecod-tk-text></textarea>'
                + '<div class="ecod_ticket__reply-ops">'
                + '<button type="submit" class="ecod-btn ecod-btn--primary ecod-btn--sm" data-ecod-tk-send>发送</button>'
                + '<button type="button" class="ecod-btn ecod-btn--ghost ecod-btn--sm ecod-btn--close" data-ecod-tk-close>问题已解决，关闭工单</button>'
                + '</div></form>';
            return html;
        }

        function openItem(item) {
            var id = item.getAttribute('data-id');
            var thread = item.querySelector('[data-ecod-ticket-thread]');
            var willOpen = !item.classList.contains('is-open');
            item.classList.toggle('is-open', willOpen);
            if (!thread) return;
            thread.hidden = !willOpen;
            if (!willOpen) return;

            if (thread.dataset.loaded === '1') return;
            thread.innerHTML = '<div class="ecod_ticket__loading">加载中…</div>';
            get('ticket_detail', { id: id }).then(function (data) {
                if (!data || !data.ok) {
                    thread.innerHTML = '<div class="ecod_ticket__loading">' + ((data && data.error) || '加载失败') + '</div>';
                    return;
                }
                thread.dataset.loaded = '1';
                thread.innerHTML = threadHtml(data);
                var dot = item.querySelector('.ecod_ticket__dot');
                if (dot) dot.remove();
                /* 详情读完服务端已清 unread_user，同步角标 */
                var dots = root.querySelectorAll('.ecod_ticket__dot').length;
                setTabCount(dots);
            });
        }

        root.addEventListener('click', function (e) {
            var toggle = e.target.closest ? e.target.closest('[data-ecod-ticket-toggle]') : null;
            if (toggle) {
                openItem(toggle.closest('.ecod_ticket__item'));
                return;
            }

            var newBtn = e.target.closest ? e.target.closest('[data-ecod-ticket-new]') : null;
            if (newBtn && form) {
                form.hidden = false;
                hideMsg();
                var title = form.querySelector('[data-ecod-ticket-title]');
                if (title) title.focus();
                return;
            }

            var cancel = e.target.closest ? e.target.closest('[data-ecod-ticket-cancel]') : null;
            if (cancel && form) {
                form.hidden = true;
                hideMsg();
                return;
            }

            var closeBtn = e.target.closest ? e.target.closest('[data-ecod-tk-close]') : null;
            if (closeBtn) {
                var row = closeBtn.closest('.ecod_ticket__item');
                if (!row) return;
                Ecod.confirm({
                    title: '关闭这个工单？',
                    text: '关闭后不能再继续回复。',
                    ok: '确认关闭'
                }).then(function (yes) {
                    if (!yes) return;
                    closeBtn.disabled = true;
                    post('ticket_close', { id: row.getAttribute('data-id'), token: token }).then(function (data) {
                        closeBtn.disabled = false;
                        if (!data || !data.ok) {
                            Ecod.toast((data && data.error) || '关闭失败', 'bad');
                            return;
                        }
                        Ecod.toast('工单已关闭', 'ok');
                        reload();
                    });
                });
            }
        });

        root.addEventListener('submit', function (e) {
            var reply = e.target.closest ? e.target.closest('[data-ecod-tk-reply]') : null;
            if (reply) {
                e.preventDefault();
                var row = reply.closest('.ecod_ticket__item');
                var ta = reply.querySelector('[data-ecod-tk-text]');
                var text = ta ? String(ta.value || '').trim() : '';
                if (text.length < 1) {
                    Ecod.toast('请先输入内容', 'bad');
                    return;
                }
                var send = reply.querySelector('[data-ecod-tk-send]');
                if (send) send.disabled = true;
                post('ticket_reply', { id: row.getAttribute('data-id'), body: text, token: token }).then(function (data) {
                    if (send) send.disabled = false;
                    if (!data || !data.ok) {
                        Ecod.toast((data && data.error) || '回复失败', 'bad');
                        return;
                    }
                    if (ta) ta.value = '';
                    /* 正文已变，重开详情拿最新时间线 */
                    var thread = row.querySelector('[data-ecod-ticket-thread]');
                    if (thread) thread.dataset.loaded = '';
                    Ecod.toast('已发送', 'ok');
                    reload();
                    get('ticket_detail', { id: row.getAttribute('data-id') }).then(function (d) {
                        if (d && d.ok && thread) {
                            thread.dataset.loaded = '1';
                            thread.innerHTML = threadHtml(d);
                        }
                    });
                });
                return;
            }

            if (e.target === form) {
                e.preventDefault();
                var cat = form.querySelector('[data-ecod-ticket-cat]');
                var titleEl = form.querySelector('[data-ecod-ticket-title]');
                var bodyEl = form.querySelector('[data-ecod-ticket-text]');
                var title = titleEl ? String(titleEl.value || '').trim() : '';
                var body = bodyEl ? String(bodyEl.value || '').trim() : '';
                if (title.length < 4 || title.length > 60) { showMsg('标题需 4–60 字', true); return; }
                if (body.length < 10 || body.length > 2000) { showMsg('详细描述需 10–2000 字', true); return; }

                var submit = form.querySelector('[data-ecod-ticket-submit]');
                if (submit) submit.disabled = true;
                post('ticket_create', {
                    cat: cat ? cat.value : 'other',
                    title: title,
                    body: body,
                    token: token
                }).then(function (data) {
                    if (submit) submit.disabled = false;
                    if (!data || !data.ok) {
                        showMsg((data && data.error) || '提交失败，请稍后重试', true);
                        return;
                    }
                    hideMsg();
                    form.hidden = true;
                    if (titleEl) titleEl.value = '';
                    if (bodyEl) bodyEl.value = '';
                    Ecod.toast('工单已提交 ' + (data.no || ''), 'ok');
                    reload().then(function () {
                        /* 新单默认展开，省一次点击 */
                        var first = listBox ? listBox.querySelector('.ecod_ticket__item') : null;
                        if (first && String(first.getAttribute('data-id')) === String(data.id)) openItem(first);
                    });
                });
            }
        });

        /* 表单里的回车提交走浏览器默认行为，这里只处理取消按钮的点击委托 */
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
            if (e.key !== 'Escape' || Ecod.dialogActive()) return;
            var open = document.querySelector('[data-ecod-sort].is-open');
            if (!open) return;
            closeAll(null);
            var b = open.querySelector('[data-ecod-sort-btn]');
            if (b) b.focus();
        });
    };

    /* ---------- 列表加载模式（EListLoad，设计稿 §选项卡·通知条·加载与分页） ----------
       CFG.listLoad 三档：auto = 触底自动加载（默认）；click = 点击「加载更多」；
       pager = 数字分页（不做增强）。首页/归档/分类流：把下一页卡片原地追加进流，
       数字分页 .ecod_pager 保留在 DOM（SEO 可抓、无 JS 可用），客户端只做增强劫持。
       排序/分类内搜索参数由服务端 ecod_sort_pager 已补进分页链接，追加不会串序。
       PJAX 换页后 refresh() 重新接线，旧观察者随旧 DOM 一起废弃。 */
    /* 单一滚动分发：PJAX 每次换页都会重新 initInfinite，window 上的滚动监听只绑一次，
       由当前列表的 handler 接管（handler 内部自检 stat.isConnected，离开列表页即空转） */
    if (!Ecod._infScrollBound) {
        Ecod._infScrollBound = true;
        window.addEventListener('scroll', function () {
            if (Ecod._infOnScroll) Ecod._infOnScroll();
        }, { passive: true });
    }

    Ecod.initInfinite = function () {
        var mode = CFG.listLoad || 'auto';
        if (mode === 'pager') return;
        var box = document.querySelector('[data-ecod-infinite]');
        if (!box || box.dataset.infReady === '1') return;
        box.dataset.infReady = '1';

        var pager = box.nextElementSibling;
        if (!pager || !pager.classList.contains('ecod_pager')) return;
        var nextLink = pager.querySelector('a.next');
        if (!nextLink) return;

        /* 数字分页让位给原地追加，但保留在 DOM 并随加载同步刷新 */
        pager.classList.add('is-inf');

        var busy = false;
        var url = nextLink.getAttribute('href');
        /* auto 模式连续自动加载页数上限：攒够后转手动「加载更多」（Google Images 式
           hybrid，页脚不再被无限流挡死）；0 = 不限制 */
        var autoMax = parseInt(CFG.listAutoMax, 10);
        if (isNaN(autoMax) || autoMax < 0) autoMax = 4;
        var autoCount = 0;
        var manual = mode === 'click';
        var bar = null;

        /* click 模式：一直是大按钮；auto 模式：隐形哨兵 + 细加载条，超页数后转按钮 */
        if (manual) {
            bar = document.createElement('button');
            bar.type = 'button';
            bar.className = 'ecod_loadmore';
            bar.textContent = '加载更多';
            /* 可访问名须包含可见文字（WCAG 2.5.3）：文字各状态变化时同步更新 */
            bar.setAttribute('aria-label', '加载更多');
            pager.parentNode.insertBefore(bar, pager.nextSibling);
        }

        /* 状态条（auto 模式）：空态是 1px 哨兵（display:none 会让 IO 永不触发），
           busy = spinner 行，error = 重试按钮，done = 到底分割线 */
        var stat = null;
        if (!manual) {
            stat = document.createElement('div');
            stat.className = 'ecod_loadstat';
            stat.setAttribute('aria-live', 'polite');
            pager.parentNode.insertBefore(stat, pager.nextSibling);
        }

        function setStat(state) {
            if (!stat) return;
            stat.classList.toggle('is-busy', state === 'busy');
            stat.classList.toggle('is-error', state === 'error');
            stat.classList.toggle('is-done', state === 'done');
            stat.innerHTML = state === 'busy'
                ? '<i class="ecod_spin" aria-hidden="true"></i><span>正在加载</span>'
                : state === 'error'
                    ? '<button type="button" class="ecod_loadstat__retry">加载失败，点击重试</button>'
                    : state === 'done' ? '<span>没有更多了</span>' : '';
        }

        /* 自动页数用完：弹出手动按钮接管（此后加载与状态都走按钮，滚动检测随 manual 关停） */
        function switchManual() {
            manual = true;
            bar = document.createElement('button');
            bar.type = 'button';
            bar.className = 'ecod_loadmore';
            bar.textContent = '加载更多';
            bar.setAttribute('aria-label', '加载更多');
            pager.parentNode.insertBefore(bar, stat);
            bar.addEventListener('click', load);
            stat.innerHTML = '';
            stat.className = 'ecod_loadstat';
        }

        function setBar(state) {
            var text = state === 'busy' ? '正在加载…'
                : state === 'error' ? '加载失败，点击重试' : '加载更多';
            bar.classList.toggle('is-busy', state === 'busy');
            bar.classList.toggle('is-error', state === 'error');
            bar.textContent = text;
            bar.setAttribute('aria-label', text);
        }

        function load() {
            if (busy || !url) return;
            busy = true;
            var pageUrl = url;
            if (bar) setBar('busy'); else setStat('busy');
            fetch(url, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (r) {
                if (!r.ok) throw new Error(r.status);
                return r.text();
            }).then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var src = doc.querySelector('[data-ecod-infinite]');
                var newPager = doc.querySelector('.ecod_pager');
                if (!src) throw new Error('no stream');
                /* 逐个搬节点：卡片上的懒加载属性（loading/data-*）原样保留。
                   按 data-cid 去重：分页窗口重叠或极端并发下同一篇可能出现两次，
                   重复卡会让「置顶在首位」看起来像被追加到了列表尾部 */
                var seen = {};
                Array.prototype.forEach.call(box.querySelectorAll('[data-cid]'), function (el) {
                    seen[el.getAttribute('data-cid')] = 1;
                });
                var frag = document.createDocumentFragment();
                var firstNew = null;
                /* 先 slice 固化：src.children 是活 HTMLCollection，
                   循环里 adoptNode 会实时缩短集合，forEach 按下标推进
                   会隔张跳卡（10 篇只显示 8 篇的根因） */
                Array.prototype.forEach.call(Array.prototype.slice.call(src.children), function (card) {
                    var cardCid = card.getAttribute('data-cid');
                    if (cardCid && seen[cardCid]) return;
                    if (cardCid) seen[cardCid] = 1;
                    if (!firstNew) firstNew = card;
                    frag.appendChild(document.adoptNode(card));
                });
                box.appendChild(frag);
                /* 记录本批内容的页码归属，syncUrl 按阅读位置同步地址栏 */
                if (firstNew) marks.push({ url: pageUrl, el: firstNew });

                var link = newPager ? newPager.querySelector('a.next') : null;
                if (link) {
                    url = link.getAttribute('href');
                    pager.innerHTML = newPager.innerHTML;
                    if (bar) {
                        setBar('idle');
                    } else {
                        autoCount++;
                        if (autoMax > 0 && autoCount >= autoMax) switchManual();
                        else setStat('off');
                    }
                } else {
                    url = '';
                    if (bar) {
                        bar.classList.add('is-done');
                        bar.textContent = '已经到底了';
                        bar.setAttribute('aria-label', '已经到底了');
                    } else {
                        setStat('done');
                    }
                    if (pager.parentNode) pager.parentNode.removeChild(pager);
                }
                /* 新卡片补初始化：悬浮操作组归位 / 点赞状态 / 懒加载 / 入场动效 */
                Ecod.initCardAct();
                Ecod.initLikeState();
                Ecod.initImages();
                Ecod.initAnimate();
                /* 地址栏不随加载跳页：交给滚动时的 syncUrl 按阅读位置同步 */
                busy = false;
                /* 用户停在列表末端等下一页时不会再产生滚动事件，就地补一次检测。
                   top > 0：哨兵已被滚过视口顶 = 用户在侧栏/页脚区，不续载
                   （侧栏比流长时这里曾连环把余下页全拉完）；100px 紧边距
                   保证只有真的停在底部才续 */
                if (!bar && url && stat && stat.isConnected) {
                    var stTop = stat.getBoundingClientRect().top;
                    if (stTop > 0 && stTop < window.innerHeight + 100) load();
                }
            }).catch(function () {
                busy = false;
                if (bar) setBar('error'); else setStat('error');
            });
        }

        /* URL 跟随阅读位置而不是加载进度：每批追加的卡片记下页码归属，
           滚动时看「视口上 40% 落进了哪一批」，地址栏就显示那批的页码。
           预取不再改地址——旧实现加载完第 2 页就把地址换成下一页链接
           （page/3），比实际内容超前整整一页 */
        var marks = [];
        var firstPageUrl = location.href;
        var lastSync = location.href;
        function syncUrl() {
            var target = '';
            for (var i = 0; i < marks.length; i++) {
                var m = marks[i];
                if (!m.el || !m.el.isConnected) continue;
                if (m.el.getBoundingClientRect().top <= window.innerHeight * 0.4) target = m.url;
                else break;
            }
            if (target === lastSync) return;
            lastSync = target;
            try { history.replaceState(null, '', target || firstPageUrl); } catch (err) {}
        }

        if (bar) bar.addEventListener('click', load);
        if (stat) stat.addEventListener('click', function () {
            if (stat.classList.contains('is-error')) load();
        });
        /* auto 模式用「真实滚动 + 距离检测」而非 IO 交叉：加载追加后的布局抖动
           （懒加载图片撑高、字体交换）会让哨兵在边距内反复进出，
           IO 补发的旧交叉快照会让页面刚打开就连环加载。
           经典方案每次续载都必须有一次真实滚动驱动，布局位移不触发 */
        var ticking = false;
        Ecod._infOnScroll = function () {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(function () {
                ticking = false;
                if (!stat || !stat.isConnected) return;
                syncUrl();
                if (busy || !url || manual) return;
                var top = stat.getBoundingClientRect().top;
                /* 哨兵被滚到视口顶以上 = 用户已滑出文章流（侧栏/页脚区），停止预取 */
                if (top >= 0 && top <= window.innerHeight + 600) load();
            });
        };
    };

    /* ---------- 我的积分（个人中心 credits 面板） ----------
       余额 / 今日签到 / 流水都来自 credits_state，服务端只渲染骨架 */
    Ecod.initCredits = function () {
        var root = document.querySelector('[data-ecod-credits]');
        if (!root || root.dataset.creditsReady === '1') return;
        root.dataset.creditsReady = '1';
        var api = root.dataset.api || CFG.api || '';
        if (!api) return;

        var btn = root.querySelector('[data-ecod-credits-signin]');
        var msg = root.querySelector('[data-ecod-credits-msg]');
        var balEl = root.querySelector('[data-ecod-credits-balance]');
        var logEl = root.querySelector('[data-ecod-credits-log]');
        var meterEl = root.querySelector('[data-ecod-credits-meter]');
        var factsEl = root.querySelector('[data-ecod-credits-facts]');
        var earnEl = root.querySelector('[data-ecod-credits-earn]');
        var spendEl = root.querySelector('[data-ecod-credits-spend]');
        var tabsEl = root.querySelector('[data-ecod-credits-tabs]');
        var pagerEl = root.querySelector('[data-ecod-credits-pager]');
        var prevEl = root.querySelector('[data-ecod-credits-prev]');
        var nextEl = root.querySelector('[data-ecod-credits-next]');
        var pageinfoEl = root.querySelector('[data-ecod-credits-pageinfo]');
        var LABELS = {
            checkin: '每日签到', comment: '发表评论', like_recv: '文章被赞', post: '发布文章',
            task: '任务奖励',
            ai: 'AI 对话', ai_refund: 'AI 失败退回', ai_ad: '看广告奖励',
            lottery: '抽奖消耗', lottery_win: '抽奖中奖', vip_gift: '开通会员赠送',
            shop: '积分兑换', referral: '推广返佣', admin: '管理员调整'
        };
        /* 明细状态：当前 tab 类型 + 页码；tab 切换回到第 1 页 */
        var view = { type: '', page: 1, pages: 1 };

        function say(text) {
            if (!msg) return;
            msg.textContent = text || '';
            msg.hidden = !text;
        }

        function day(ts) {
            var d = new Date(ts * 1000);
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        }

        /* 余额数字滚动（约 800ms 计数上涌；prefers-reduced-motion 直接落值） */
        var countRaf = 0;
        function countUp(el, target) {
            if (!el) return;
            var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (countRaf) cancelAnimationFrame(countRaf);
            if (reduce || target <= 0) { el.textContent = target; return; }
            var from = parseInt(el.textContent, 10) || 0;
            if (from >= target) { el.textContent = target; return; }
            var t0 = performance.now(), dur = 800;
            function step(t) {
                var p = Math.min(1, (t - t0) / dur);
                p = 1 - Math.pow(1 - p, 3);
                el.textContent = Math.round(from + (target - from) * p);
                if (p < 1) countRaf = requestAnimationFrame(step);
            }
            countRaf = requestAnimationFrame(step);
        }

        /* 近 30 天走势条：净收入亮主色、净支出警示色、无变动灰条 */
        function renderMeter(meter) {
            if (!meterEl) return;
            if (!meter || !meter.length) { meterEl.hidden = true; return; }
            var html = '';
            for (var i = 0; i < meter.length; i++) {
                var m = meter[i];
                var cls = m.net > 0 ? ' is-up' : (m.net < 0 ? ' is-down' : '');
                html += '<i class="ecod_credits__bar' + cls + '" title="' + esc(m.day) + (m.net ? ' · ' + (m.net > 0 ? '+' : '') + m.net : '') + '"></i>';
            }
            meterEl.innerHTML = html;
        }

        function renderStats(stats) {
            if (!factsEl) return;
            if (!stats || (!stats.earn && !stats.spend)) { factsEl.hidden = true; return; }
            factsEl.hidden = false;
            if (earnEl) earnEl.textContent = '+' + (stats.earn || 0);
            if (spendEl) spendEl.textContent = '-' + (stats.spend || 0);
        }

        /* 类型 tab：全部 + 该账本出现过的类型（带笔数） */
        function renderTabs(types) {
            if (!tabsEl) return;
            var seen = {};
            var html = '<button type="button" class="ecod_credits__tab is-active" data-type="">全部</button>';
            (types || []).forEach(function (t) {
                if (!t || !t.type || seen[t.type]) return;
                seen[t.type] = true;
                html += '<button type="button" class="ecod_credits__tab" data-type="' + esc(t.type) + '">'
                    + esc(LABELS[t.type] || t.label || t.type) + '<em>' + t.count + '</em></button>';
            });
            tabsEl.innerHTML = html;
        }

        function renderLog(rows) {
            if (!logEl) return;
            if (!rows || !rows.length) {
                logEl.innerHTML = '<p class="ecod_credits__empty">'
                    + (view.type ? '该类型下暂无记录。' : '还没有积分记录，点上面的「每日签到」开始赚。')
                    + '</p>';
                return;
            }
            var html = '';
            rows.forEach(function (row) {
                var label = LABELS[row.type] || row.note || row.type;
                /* note 与类型文案相同（如「AI 对话」）时不重复展示 */
                var note = row.note && row.note !== label && row.note !== LABELS[row.type] ? row.note : '';
                html += '<div class="ecod_credits__row' + (row.delta < 0 ? ' is-out' : '') + '">'
                    + '<span class="ecod_credits__why">' + esc(label) + '</span>'
                    + (note ? '<span class="ecod_credits__note">' + esc(note) + '</span>' : '')
                    + '<b class="ecod_credits__delta">' + (row.delta > 0 ? '+' : '') + row.delta + '</b>'
                    + '<time>' + day(row.created) + '</time>'
                    + '</div>';
            });
            logEl.innerHTML = html;
        }

        function renderPager() {
            if (!pagerEl) return;
            if (view.pages <= 1) { pagerEl.hidden = true; return; }
            pagerEl.hidden = false;
            if (pageinfoEl) pageinfoEl.textContent = view.page + ' / ' + view.pages;
            if (prevEl) prevEl.disabled = view.page <= 1;
            if (nextEl) nextEl.disabled = view.page >= view.pages;
        }

        function loadLog() {
            if (!logEl) return;
            var url = api + '?action=credits_log&page=' + view.page + (view.type ? '&type=' + encodeURIComponent(view.type) : '');
            fetch(url, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (!data || !data.ok) return;
                view.page = data.page || view.page;
                view.pages = data.pages || 1;
                renderLog(data.rows);
                renderPager();
            }).catch(function () {});
        }

        /* 推广返佣：一键复制专属链接（clipboard 不可用时走 execCommand 兜底） */
        var refCopy = root.querySelector('[data-ecod-ref-copy]');
        if (refCopy && !refCopy.dataset.ecodBound) {
            refCopy.dataset.ecodBound = '1';
            var refLink = root.querySelector('.ecod_ref__link');
            var refLabel = refCopy.querySelector('span');
            refCopy.addEventListener('click', function () {
                var url = refLink ? refLink.value : '';
                if (!url) return;
                copyText(url, function () {
                    if (refLabel) refLabel.textContent = '已复制';
                    refCopy.classList.add('is-copied');
                    live('推广链接已复制');
                    if (refLink) refLink.select();
                    setTimeout(function () {
                        if (refLabel) refLabel.textContent = '复制链接';
                        refCopy.classList.remove('is-copied');
                    }, 1600);
                });
            });
        }

        if (btn) {
            once(btn, 'creditsSignin', 'click', function () {
                if (btn.disabled) return;
                btn.disabled = true;
                var fd = new FormData();
                fd.append('token', root.dataset.token || '');
                fetch(api + '?action=signin', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function (r) { return r.json(); }).then(function (res) {
                    if (res && res.ok) {
                        btn.textContent = '今日已签到';
                        btn.disabled = true;
                        countUp(balEl, res.balance || 0);
                        Ecod.creditsSync(res);
                        say('签到成功' + (res.delta > 0 ? ' +' + res.delta : '')
                            + (res.streak > 1 ? '，已连续 ' + res.streak + ' 天' : ''));
                        Ecod.ballCelebrate();
                        /* 签到入账后刷新走势 / 收支 / 明细（回到当前 tab 第 1 页拿最新一条） */
                        view.page = 1;
                        loadLog();
                        fetch(api + '?action=credits_state', {
                            credentials: 'same-origin',
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        }).then(function (r) { return r.json(); }).then(function (fresh) {
                            if (!fresh || !fresh.ok) return;
                            renderMeter(fresh.meter);
                            renderStats(fresh.stats);
                        }).catch(function () {});
                    } else {
                        if (res && res.signed) btn.textContent = '今日已签到';
                        else btn.disabled = false;
                        say((res && res.error) || '签到失败，请稍后重试');
                    }
                }).catch(function () {
                    btn.disabled = false;
                    say('网络异常，请稍后重试');
                });
            });
        }

        /* tab 与翻页事件（事件委托，tab 列表是 JS 动态渲染的） */
        if (tabsEl && !tabsEl.dataset.ecodBound) {
            tabsEl.dataset.ecodBound = '1';
            tabsEl.addEventListener('click', function (e) {
                var tab = e.target.closest('.ecod_credits__tab');
                if (!tab || tab.classList.contains('is-active')) return;
                var cur = tabsEl.querySelector('.ecod_credits__tab.is-active');
                if (cur) cur.classList.remove('is-active');
                tab.classList.add('is-active');
                view.type = tab.dataset.type || '';
                view.page = 1;
                loadLog();
            });
        }
        if (prevEl && !prevEl.dataset.ecodBound) {
            prevEl.dataset.ecodBound = '1';
            prevEl.addEventListener('click', function () {
                if (view.page > 1) { view.page--; loadLog(); }
            });
        }
        if (nextEl && !nextEl.dataset.ecodBound) {
            nextEl.dataset.ecodBound = '1';
            nextEl.addEventListener('click', function () {
                if (view.page < view.pages) { view.page++; loadLog(); }
            });
        }

        fetch(api + '?action=credits_state', {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data || !data.ok) return;
            var nameEl = root.querySelector('[data-ecod-credits-name]');
            if (nameEl) nameEl.textContent = data.name || 'Credit';
            countUp(balEl, data.balance || 0);
            if (btn) {
                if (data.signed) {
                    btn.textContent = '今日已签到';
                    btn.disabled = true;
                } else {
                    btn.disabled = false;
                }
            }

            renderMeter(data.meter);
            renderStats(data.stats);
            renderTabs(data.types);
            loadLog();
        }).catch(function () {});
    };

    /* ---------- 头部用户菜单的积分 / 签到直达（guaqi 式） ----------
       余额与已签状态服务端直出；这里只接管签到点击 */
    Ecod.initCreditsQuick = function () {
        var row = document.querySelector('[data-ecod-credits-quick]');
        if (!row || row.dataset.quickReady === '1') return;
        row.dataset.quickReady = '1';

        var btn = row.querySelector('[data-ecod-credits-quick-signin]');
        var bal = row.querySelector('[data-ecod-credits-quick-balance]');
        if (!btn || btn.dataset.signed === '1') return;

        once(btn, 'creditsQuick', 'click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (btn.disabled) return;
            btn.disabled = true;
            var fd = new FormData();
            fd.append('token', row.dataset.token || '');
            fetch((row.dataset.api || CFG.api || '') + '?action=signin', {
                method: 'POST',
                credentials: 'same-origin',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (r) { return r.json(); }).then(function (res) {
                if (res && res.ok) {
                    btn.textContent = '已签到';
                    btn.dataset.signed = '1';
                    if (bal && typeof res.balance === 'number') bal.textContent = res.balance;
                    Ecod.creditsSync(res);
                    live('签到成功' + (res.delta > 0 ? ' +' + res.delta : ''));
                } else {
                    if (res && res.signed) {
                        btn.textContent = '已签到';
                        btn.dataset.signed = '1';
                    } else {
                        btn.disabled = false;
                    }
                    live((res && res.error) || '签到失败，请稍后重试');
                }
            }).catch(function () {
                btn.disabled = false;
                live('网络异常，请稍后重试');
            });
        });
    };

    /* ---------- 签到成功的全局同步 ----------
       一页上可能同时挂着「我的积分」面板、头部用户菜单的签到行与侧栏打卡挂件，
       任一入口签到后都要把余额与已签状态同步到整页，避免三处数字互相矛盾 */
    Ecod.creditsSync = function (res) {
        if (!res || !res.ok) return;

        var bals = document.querySelectorAll(
            '[data-ecod-checkin-balance], [data-ecod-credits-balance], [data-ecod-credits-quick-balance]'
        );
        for (var i = 0; i < bals.length; i++) {
            if (typeof res.balance === 'number') {
                bals[i].textContent = res.balance;
                bals[i].hidden = false;
            }
        }

        var signs = document.querySelectorAll('[data-ecod-credits-signin], [data-ecod-credits-quick-signin]');
        for (var j = 0; j < signs.length; j++) {
            signs[j].disabled = true;
            signs[j].setAttribute('data-signed', '1');
            /* 头部用户菜单那一行空间小，用短文案；个人中心按钮自己会改成「今日已签到」 */
            if (signs[j].hasAttribute('data-ecod-credits-quick-signin')) {
                signs[j].textContent = '已签到';
            }
        }
    };

    /* ---------- 每日打卡挂件（components/aside/checkin.php） ----------
       签到状态与连签天数服务端直出；这里接管打卡点击，
       成功后把横幅切成到账态、余额回填、动态列表补一条 */
    Ecod.initCheckin = function () {
        var root = document.querySelector('[data-ecod-checkin]');
        if (!root || root.dataset.checkinReady === '1') return;
        root.dataset.checkinReady = '1';

        var btn = root.querySelector('[data-ecod-checkin-go]');
        if (!btn) return;
        var msg = root.querySelector('[data-ecod-checkin-msg]');
        var streak = root.querySelector('[data-ecod-checkin-streak]');

        /* 签完把横幅切到「已到账」态：文案换配置文案，余额露出来 */
        function markSigned(res) {
            var banner = root.querySelector('[data-ecod-checkin-banner]');
            var hint = root.querySelector('[data-ecod-checkin-hint]');
            var bal = root.querySelector('[data-ecod-checkin-balance]');
            if (banner) banner.classList.add('is-signed');
            if (hint) hint.textContent = root.dataset.banner || '今日份奖励已到账';
            if (bal) {
                bal.hidden = false;
                if (typeof res.balance === 'number') bal.textContent = res.balance;
            }
            if (streak && res.streak) streak.textContent = res.streak;
        }

        /* 动态列表就地补一条，省一次刷新；没有列表（关了动态）就跳过 */
        function prependFeed(res) {
            var list = root.querySelector('[data-ecod-checkin-list]');
            if (!list || !(res.delta > 0)) return;

            var li = document.createElement('li');
            li.className = 'ecod_checkin__item';

            var name = document.createElement('a');
            name.textContent = root.dataset.me || '我';
            if (root.dataset.meUrl) name.href = root.dataset.meUrl;

            var why = document.createElement('em');
            why.textContent = '打卡成功';

            var delta = document.createElement('b');
            delta.textContent = '+' + res.delta;

            li.appendChild(name);
            li.appendChild(why);
            li.appendChild(delta);
            list.insertBefore(li, list.firstChild);

            /* 与服务端一致：最多留 15 条 */
            while (list.children.length > 15) {
                list.removeChild(list.lastElementChild);
            }

            var empty = root.querySelector('[data-ecod-checkin-empty]');
            if (empty) empty.hidden = true;
        }

        once(btn, 'checkin', 'click', function () {
            if (btn.disabled) return;
            btn.disabled = true;
            if (msg) { msg.textContent = ''; msg.classList.remove('is-ok'); }
            var fd = new FormData();
            fd.append('token', root.dataset.token || '');
            fetch((root.dataset.api || CFG.api || '') + '?action=signin', {
                method: 'POST',
                credentials: 'same-origin',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (r) { return r.json(); }).then(function (res) {
                if (res && res.ok) {
                    btn.textContent = '已打卡';
                    markSigned(res);
                    prependFeed(res);
                    Ecod.creditsSync(res);
                    if (msg) {
                        msg.textContent = '打卡成功' + (res.delta > 0 ? ' +' + res.delta : '');
                        msg.classList.add('is-ok');
                    }
                    live('打卡成功' + (res.delta > 0 ? ' +' + res.delta : ''));
                } else {
                    if (res && res.signed) {
                        btn.textContent = '已打卡';
                        markSigned(res || {});
                    } else {
                        btn.disabled = false;
                    }
                    if (msg) msg.textContent = (res && res.error) || '打卡失败，请稍后重试';
                    live((res && res.error) || '打卡失败，请稍后重试');
                }
            }).catch(function () {
                btn.disabled = false;
                if (msg) msg.textContent = '网络异常，请稍后重试';
                live('网络异常，请稍后重试');
            });
        });
    };

    /* ---------- AI 快问面板（全站浮层，见 docs/ai-design.html §4.1） ----------
       一个组件两种作用域：chat（默认）/ post（就本文追问，正文由服务端注入）。
       历史只进 sessionStorage（30 分钟、同标签页）：关闭再开不丢，换页即清。
       登录走整页 reload，所以打开登录前写 pending 标记，回来自动重开。 */
    var AI_STATE_KEY = 'ecod_ai_state';
    var AI_PENDING_KEY = 'ecod_ai_pending';
    var AI_TTL = 30 * 60 * 1000;

    var aiRoot = null;
    var aiIsOpen = false;
    var aiBusy = false;
    var aiAbort = null;
    var aiMsgs = [];
    var aiScope = 'chat';
    var aiCid = 0;
    var aiTitle = '';
    var aiSigned = null;
    var aiBalance = null;
    /* 实际计价（credits_state 回填）：会员免单时 cost=0、free=true */
    var aiPrice = null;
    /* 看广告领点数（F12）：credits_state 只回「有没有 / 还剩几次」，
       观看令牌在点开广告时单独申请（用后即焚） */
    var aiAd = { on: false, left: 0, reward: 0, token: '', timer: 0, open: false };

    function aiQ(name) {
        return aiRoot ? aiRoot.querySelector('[data-ecod-aiask-' + name + ']') : null;
    }

    function aiInline(text) {
        var s = esc(text);
        s = s.replace(/`([^`]+)`/g, '<code>$1</code>');
        s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        s = s.replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>');
        s = s.replace(/\[([^\]]+)\]\((https?:[^)\s]+)\)/g,
            '<a href="$2" target="_blank" rel="noopener nofollow">$1</a>');
        return s;
    }

    function aiCodeBlock(lang, code) {
        return '<div class="ecod_aiask__code">'
            + '<div class="ecod_aiask__codebar"><span>' + esc(lang || 'code') + '</span>'
            + '<button type="button" data-ecod-aiask-copy>复制</button></div>'
            + '<pre>' + esc(code) + '</pre></div>';
    }

    /* 窄面板白名单：段落 / 粗体 / 斜体 / 行内码 / 列表 / 引用 / 链接；
       代码块降级为可复制纯文本，表格不渲染（文档 §14.4） */
    function aiMd(src) {
        var lines = String(src || '').replace(/\r/g, '').split('\n');
        var out = [];
        var buf = [];
        var i = 0;

        function flush() {
            if (buf.length) {
                out.push('<p>' + aiInline(buf.join(' ')) + '</p>');
                buf = [];
            }
        }

        while (i < lines.length) {
            var line = lines[i];

            var fence = line.match(/^\s*```\s*([\w+#.-]*)\s*$/);
            if (fence) {
                flush();
                var code = [];
                i++;
                while (i < lines.length && !/^\s*```/.test(lines[i])) { code.push(lines[i]); i++; }
                i++;
                out.push(aiCodeBlock(fence[1], code.join('\n')));
                continue;
            }
            if (/^\s*\|.+\|\s*$/.test(line)) {
                flush();
                while (i < lines.length && /^\s*\|.+\|\s*$/.test(lines[i])) { i++; }
                out.push('<div class="ecod_aiask__tbl">表格已省略：面板太窄，可在完整对话页查看</div>');
                continue;
            }
            if (/^\s*[-*+]\s+/.test(line)) {
                flush();
                var li = [];
                while (i < lines.length && /^\s*[-*+]\s+/.test(lines[i])) {
                    li.push('<li>' + aiInline(lines[i].replace(/^\s*[-*+]\s+/, '')) + '</li>');
                    i++;
                }
                out.push('<ul>' + li.join('') + '</ul>');
                continue;
            }
            if (/^\s*\d+[.)]\s+/.test(line)) {
                flush();
                var ol = [];
                while (i < lines.length && /^\s*\d+[.)]\s+/.test(lines[i])) {
                    ol.push('<li>' + aiInline(lines[i].replace(/^\s*\d+[.)]\s+/, '')) + '</li>');
                    i++;
                }
                out.push('<ol>' + ol.join('') + '</ol>');
                continue;
            }
            if (/^\s*>\s?/.test(line)) {
                flush();
                var quote = [];
                while (i < lines.length && /^\s*>\s?/.test(lines[i])) {
                    quote.push(aiInline(lines[i].replace(/^\s*>\s?/, '')));
                    i++;
                }
                out.push('<blockquote>' + quote.join('<br>') + '</blockquote>');
                continue;
            }
            if (/^\s*#{1,6}\s+/.test(line)) {
                flush();
                out.push('<p class="ecod_aiask__h">' + aiInline(line.replace(/^\s*#+\s+/, '')) + '</p>');
                i++;
                continue;
            }
            if (line.trim() === '') {
                flush();
                i++;
                continue;
            }
            buf.push(line);
            i++;
        }
        flush();
        return out.join('');
    }

    function aiWelcome() {
        var chips = Array.isArray(CFG.aiChips) ? CFG.aiChips : [];
        var hello = '你好，我是本站的 AI 助手，关于文章内容或建站问题都可以问我。';
        if (aiScope === 'post') hello = '我读完了这篇，关于它的问题都可以问。';
        if (aiScope === 'site') hello = '说说你想看什么，我从站内挑几个地方给你。';
        var html = '<div class="ecod_aiask__welcome">'
            + '<p>' + hello + '</p>';
        if (chips.length) {
            html += '<div class="ecod_aiask__chips">';
            chips.forEach(function (c) {
                html += '<button type="button" data-ecod-aiask-chip="' + esc(c) + '">' + esc(c) + '</button>';
            });
            html += '</div>';
        }
        return html + '</div>';
    }

    function aiLoginCard() {
        return '<div class="ecod_aiask__login">'
            + '<b>登录后即可对话</b>'
            + '<p>AI 回答按次消耗 ' + esc(CFG.credits || '积分') + '，登录还能签到赚回来。</p>'
            + '<button type="button" class="ecod_aiask__lgbtn" data-ecod-login-open>登录 / 注册</button>'
            + '</div>';
    }

    function aiPaintBal() {
        var el = aiQ('bal');
        if (!el) return;
        if (aiBalance === null) {
            el.hidden = true;
            return;
        }
        el.hidden = false;
        el.textContent = (CFG.credits || '积分') + ' ' + aiBalance
            + (aiPrice && aiPrice.free ? ' · VIP 免费' : '');
        el.classList.toggle('is-vip', !!(aiPrice && aiPrice.free));
    }

    /* ---------- 看广告领点数（F12） ---------- */
    function aiPaintEarn() {
        var box = aiQ('earn');
        if (!box) return;
        var show = !!(aiSigned && aiAd.on && aiAd.left > 0 && !aiAd.open);
        box.hidden = !show;
        var amt = aiQ('adamt');
        if (amt) amt.textContent = aiAd.reward || 0;
    }

    function aiAdReset() {
        if (aiAd.timer) { clearInterval(aiAd.timer); aiAd.timer = 0; }
        aiAd.token = '';
        aiAd.open = false;
        var card = aiQ('ad');
        if (card) card.hidden = true;
        aiPaintEarn();
    }

    function aiAdFill(state) {
        var ad = (state && state.ad) || {};
        var link = aiQ('adlink');
        var title = aiQ('adtitle');
        var note = aiQ('adnote');
        var cover = aiQ('adcover');
        if (title) title.textContent = ad.title || '本站推荐';
        if (note) note.textContent = ad.sponsor ? '赞助内容' : '本站精选';
        if (link) {
            /* 没有落地页就退回首页，绝不写 javascript: 之类的伪协议 */
            var href = String(ad.link || '');
            if (!/^https?:\/\//i.test(href) && href.charAt(0) !== '/') href = '/';
            link.setAttribute('href', href);
        }
        if (cover) {
            if (ad.image) {
                cover.style.backgroundImage = "url('" + String(ad.image).replace(/'/g, '%27') + "')";
                cover.hidden = false;
            } else {
                cover.style.backgroundImage = '';
                cover.hidden = true;
            }
        }
    }

    function aiAdStart(state) {
        aiAd.open = true;
        aiAd.token = String((state && state.token) || '');
        aiAd.reward = parseInt(state && state.reward, 10) || aiAd.reward || 1;
        aiAdFill(state);
        aiPaintEarn();
        var card = aiQ('ad');
        if (card) card.hidden = false;
        var btn = aiQ('adclaim');
        var left = Math.max(3, parseInt(state && state.wait, 10) || 10);
        if (btn) { btn.disabled = true; btn.textContent = '广告播放中 ' + left + 's'; }
        if (aiAd.timer) clearInterval(aiAd.timer);
        aiAd.timer = setInterval(function () {
            left--;
            if (left > 0) {
                if (btn) btn.textContent = '广告播放中 ' + left + 's';
                return;
            }
            clearInterval(aiAd.timer);
            aiAd.timer = 0;
            if (btn) { btn.disabled = false; btn.textContent = '领取 +' + aiAd.reward + ' 点'; }
        }, 1000);
    }

    function aiAdLoad() {
        var fd = new FormData();
        fd.append('token', CFG.aiToken || '');
        fetch((CFG.api || '') + '?action=ai_ad', {
            method: 'POST', credentials: 'same-origin', body: fd
        }).then(function (r) { return r.json().catch(function () { return null; }); }).then(function (d) {
            if (!d || !d.ok) {
                Ecod.toast((d && d.error) || '暂时没有可看的广告', 'warn');
                return;
            }
            aiAd.on = !!d.enabled;
            aiAd.left = parseInt(d.left, 10) || 0;
            aiAd.reward = parseInt(d.reward, 10) || aiAd.reward;
            if (aiAd.left <= 0) {
                aiPaintEarn();
                Ecod.toast('今天的次数已经领完', 'info');
                return;
            }
            aiAdStart(d);
        }).catch(function () {
            Ecod.toast('网络异常，请稍后重试', 'bad');
        });
    }

    function aiAdClaim() {
        if (!aiAd.token) return;
        var btn = aiQ('adclaim');
        if (btn) btn.disabled = true;
        var fd = new FormData();
        fd.append('token', CFG.aiToken || '');
        fd.append('ad_token', aiAd.token);
        fetch((CFG.api || '') + '?action=ai_ad_claim', {
            method: 'POST', credentials: 'same-origin', body: fd
        }).then(function (r) { return r.json().catch(function () { return null; }); }).then(function (d) {
            if (!d || !d.ok) {
                if (btn) btn.disabled = false;
                Ecod.toast((d && d.error) || '领取失败，请稍后重试', 'warn');
                return;
            }
            aiBalance = typeof d.balance !== 'undefined' ? d.balance : aiBalance;
            aiAd.left = typeof d.left !== 'undefined' ? d.left : Math.max(0, aiAd.left - 1);
            aiAdReset();
            aiPaintBal();
            Ecod.toast('已领取 +' + (d.delta || aiAd.reward) + ' ' + (CFG.credits || '积分'), 'ok');
            if (window.Ecod && Ecod.ballFlashAll) Ecod.ballFlashAll('19', 1400);
        }).catch(function () {
            if (btn) btn.disabled = false;
            Ecod.toast('网络异常，请稍后重试', 'bad');
        });
    }

    function aiPaintHead() {
        var title = aiQ('title');
        var scope = aiQ('scope');
        var ctx = aiQ('ctx');
        var ctxt = aiQ('ctxt');
        if (title) {
            title.textContent = aiScope === 'post' ? '就本文追问'
                : (aiScope === 'site' ? '站内向导' : 'AI 快问');
        }
        if (scope) {
            scope.hidden = aiScope !== 'post';
            scope.textContent = 'post';
        }
        if (ctx && ctxt) {
            ctx.hidden = aiScope !== 'post';
            ctxt.textContent = '上下文：' + (aiTitle || '本文');
        }
        var tip = aiQ('tip');
        if (tip) {
            var unit = aiPrice ? aiPrice.cost : (CFG.aiCost || 0);
            var price = aiPrice && aiPrice.free
                ? 'VIP 免单'
                : unit + ' ' + (CFG.credits || '积分');
            tip.textContent = aiScope === 'post'
                ? '回答仅基于本文 · 1 条消息 = ' + price
                : '回答由 AI 生成，仅供参考 · 1 条消息 = ' + price;        }
        aiPaintBal();
    }

    function aiScroll(force) {
        var list = aiQ('list');
        if (!list) return;
        var near = list.scrollHeight - list.scrollTop - list.clientHeight < 48;
        if (force || near) list.scrollTop = list.scrollHeight;
    }

    function aiRender() {
        var list = aiQ('list');
        if (!list) return;
        if (aiSigned === false) {
            list.innerHTML = aiLoginCard();
            aiSetBusy(false);
            return;
        }
        if (!aiMsgs.length) {
            list.innerHTML = aiWelcome();
            return;
        }
        var html = '';
        aiMsgs.forEach(function (m, idx) {
            if (m.role === 'user') {
                html += '<div class="ecod_aiask__msg is-me"><div class="ecod_aiask__bub">'
                    + esc(m.content).replace(/\n/g, '<br>') + '</div></div>';
                return;
            }
            if (m.role === 'error') {
                html += '<div class="ecod_aiask__msg"><div class="ecod_aiask__bub is-err">'
                    + esc(m.content)
                    + (m.action ? '<a href="' + esc(m.action.href) + '">' + esc(m.action.text) + '</a>' : '')
                    + (m.retry ? '<button type="button" data-ecod-aiask-retry="' + idx + '">重试</button>' : '')
                    + '</div></div>';
                return;
            }
            html += '<div class="ecod_aiask__msg"><span class="ecod_aiask__ava" aria-hidden="true"></span>'
                + '<div class="ecod_aiask__bub" data-ecod-aiask-body="' + idx + '">'
                + (m.pending ? '<i class="ecod_aiask__dots"><b></b><b></b><b></b></i>' : aiMd(m.content))
                + '</div></div>';
        });
        list.innerHTML = html;
        aiScroll();
    }

    function aiPaintLast(idx) {
        var el = aiRoot ? aiRoot.querySelector('[data-ecod-aiask-body="' + idx + '"]') : null;
        if (!el) return;
        el.innerHTML = aiMd(aiMsgs[idx].content);
        aiScroll();
    }

    function aiSetBusy(on) {
        aiBusy = !!on;
        var btn = aiQ('send');
        var ta = aiQ('ta');
        if (btn) {
            btn.classList.toggle('is-stop', aiBusy);
            btn.setAttribute('aria-label', aiBusy ? '停止生成' : '发送');
            btn.innerHTML = aiBusy
                ? '<span class="ecod_aiask__stopicon" aria-hidden="true"></span>'
                : (Ecod.icon ? Ecod.icon('arrow-up', 15) : '↑');
        }
        if (ta) ta.disabled = false;
    }

    function aiSave() {
        try {
            sessionStorage.setItem(AI_STATE_KEY, JSON.stringify({
                t: Date.now(),
                scope: aiScope,
                cid: aiCid,
                title: aiTitle,
                msgs: aiMsgs.slice(-12)
            }));
        } catch (e) {}
    }

    function aiRestore() {
        try {
            var raw = sessionStorage.getItem(AI_STATE_KEY);
            if (!raw) return null;
            var s = JSON.parse(raw);
            if (!s || Date.now() - (s.t || 0) > AI_TTL) {
                sessionStorage.removeItem(AI_STATE_KEY);
                return null;
            }
            return s;
        } catch (e) {
            return null;
        }
    }

    function aiHistory() {
        var out = [];
        aiMsgs.forEach(function (m) {
            if ((m.role === 'user' || m.role === 'assistant') && m.content && !m.pending) {
                out.push({ role: m.role, content: m.content });
            }
        });
        return out.slice(-8);
    }

    function aiSyncUser() {
        if (!CFG.api) return;
        fetch((CFG.api || '') + '?action=credits_state', { credentials: 'same-origin' })
            .then(function (r) { return r.json().catch(function () { return null; }); })
            .then(function (d) {
                var was = aiSigned;
                aiSigned = !!(d && d.ok);
                if (aiSigned && d && typeof d.balance !== 'undefined') aiBalance = d.balance;
                if (aiSigned && d && typeof d.ai_cost !== 'undefined') {
                    aiPrice = { cost: parseInt(d.ai_cost, 10) || 0, free: !!d.ai_free, vip: !!d.ai_vip };
                }
                if (aiSigned && d && typeof d.ai_ad_on !== 'undefined') {
                    aiAd.on = !!d.ai_ad_on;
                    aiAd.left = parseInt(d.ai_ad_left, 10) || 0;
                    aiAd.reward = parseInt(d.ai_ad_reward, 10) || aiAd.reward;
                } else if (!aiSigned) {
                    aiAd.on = false;
                    aiAd.left = 0;
                }
                aiPaintBal();
                aiPaintEarn();
                if (aiSigned) aiPaintHead();
                if (was !== aiSigned || !aiMsgs.length) aiRender();
            })
            .catch(function () {});
    }

    function aiFocus() {
        var ta = aiQ('ta');
        if (ta) {
            try { ta.focus({ preventScroll: true }); } catch (e) {}
        }
    }

    function aiHide() {
        if (!aiRoot || !aiIsOpen) return;
        aiRoot.hidden = true;
        aiIsOpen = false;
        aiAdReset();
        aiSave();
        document.documentElement.classList.remove('ecod-aiask-on');
    }

    function aiClose() {
        if (!aiRoot || !aiIsOpen) return;
        if (aiAbort) {
            try { aiAbort.abort(); } catch (e) {}
            aiAbort = null;
        }
        Ecod.dialogClose(aiRoot);
        aiHide();
    }

    function aiNeedLogin() {
        try {
            sessionStorage.setItem(AI_PENDING_KEY, JSON.stringify({
                scope: aiScope, cid: aiCid, title: aiTitle, msgs: aiMsgs.slice(-8)
            }));
        } catch (e) {}
        var btn = aiRoot ? aiRoot.querySelector('[data-ecod-login-open]') : null;
        if (btn) {
            btn.click();
            return;
        }
        location.reload();
    }

    function aiFail(idx, msg, retryText, action) {
        aiMsgs[idx] = { role: 'error', content: msg, retry: retryText || '', action: action || null };
        aiRender();
        aiSetBusy(false);
    }

    function aiEvent(data, idx) {
        var asst = aiMsgs[idx];
        if (!asst) return;
        if (data.t === 'meta') {
            if (typeof data.balance !== 'undefined') {
                aiBalance = data.balance;
                aiPaintBal();
            }
            asst.pending = false;
            aiPaintLast(idx);
            return;
        }
        if (data.t === 'delta') {
            asst.pending = false;
            asst.content += String(data.s || '');
            aiPaintLast(idx);
            return;
        }
        if (data.t === 'done') {
            asst.pending = false;
            if (data.reply) asst.content = String(data.reply);
            if (typeof data.balance !== 'undefined') {
                aiBalance = data.balance;
                aiPaintBal();
            }
            aiRender();
            aiSetBusy(false);
            aiSave();
            if (window.Ecod && Ecod.ballFlashAll) Ecod.ballFlashAll('19', 1600);
            return;
        }
        if (data.t === 'err') {
            if (typeof data.balance !== 'undefined') {
                aiBalance = data.balance;
                aiPaintBal();
            }
            aiFail(idx, String(data.error || '生成失败，请稍后重试'), aiLastQuestion());
        }
    }

    function aiLastQuestion() {
        for (var i = aiMsgs.length - 1; i >= 0; i--) {
            if (aiMsgs[i].role === 'user') return aiMsgs[i].content;
        }
        return '';
    }

    function aiReadStream(res, idx) {
        if (!res.body || !res.body.getReader) {
            return res.text().then(function (t) {
                try { aiEvent(JSON.parse(t), idx); } catch (e) { aiFail(idx, '响应异常', aiLastQuestion()); }
            });
        }
        var reader = res.body.getReader();
        var dec = window.TextDecoder ? new TextDecoder() : null;
        var buf = '';
        function pump() {
            return reader.read().then(function (r) {
                if (r.done) {
                    aiSetBusy(false);
                    var m = aiMsgs[idx];
                    if (m && m.role === 'assistant' && m.pending) {
                        /* 流提前结束且零输出：按失败处理（服务端会全额退回） */
                        aiFail(idx, '接口未返回内容，请稍后重试', aiLastQuestion());
                        return;
                    }
                    if (m && m.role === 'assistant') aiSave();
                    return;
                }
                buf += dec ? dec.decode(r.value, { stream: true }) : String.fromCharCode.apply(null, r.value);
                var blocks = buf.split('\n\n');
                buf = blocks.pop();
                blocks.forEach(function (block) {
                    var line = block.trim();
                    if (line.indexOf('data:') !== 0) return;
                    var data;
                    try { data = JSON.parse(line.slice(5).trim()); } catch (e) { return; }
                    aiEvent(data, idx);
                });
                return pump();
            });
        }
        return pump();
    }

    function aiJson(res, idx, question) {
        return res.json().catch(function () {
            return { ok: false, error: '响应异常' };
        }).then(function (d) {
            if (d && d.ok) {
                aiMsgs[idx] = { role: 'assistant', content: String(d.reply || '') };
                if (typeof d.balance !== 'undefined') {
                    aiBalance = d.balance;
                    aiPaintBal();
                }
                aiRender();
                aiSetBusy(false);
                aiSave();
                return;
            }
            if (res.status === 401) {
                aiSigned = false;
                aiRender();
                return;
            }
            if (res.status === 402) {
                aiFail(idx, (d && d.error) || '积分不足', question, { href: CFG.aiPage || '#', text: '去签到' });
                return;
            }
            aiFail(idx, (d && d.error) || '生成失败，请稍后重试', question);
        });
    }

    function aiSend(text) {
        text = String(text || '').trim();
        if (!text || aiBusy) return;
        if (aiSigned === false) {
            aiNeedLogin();
            return;
        }
        aiMsgs.push({ role: 'user', content: text });
        aiMsgs.push({ role: 'assistant', content: '', pending: true });
        var idx = aiMsgs.length - 1;
        aiRender();
        aiSetBusy(true);

        var body = new FormData();
        body.append('message', text);
        body.append('token', CFG.aiToken || '');
        body.append('history', JSON.stringify(aiHistory()));
        if (aiScope === 'post' && aiCid > 0) {
            body.append('scope', 'post');
            body.append('cid', aiCid);
        } else if (aiScope === 'site') {
            body.append('scope', 'site');
        }
        if (CFG.aiStream !== false) body.append('stream', '1');

        aiAbort = window.AbortController ? new AbortController() : null;
        fetch((CFG.api || '') + '?action=ai_chat', {
            method: 'POST',
            credentials: 'same-origin',
            body: body,
            signal: aiAbort ? aiAbort.signal : undefined
        }).then(function (res) {
            var ct = (res.headers.get('content-type') || '');
            if (ct.indexOf('text/event-stream') >= 0) return aiReadStream(res, idx);
            return aiJson(res, idx, text);
        }).catch(function (err) {
            if (err && err.name === 'AbortError') {
                if (aiMsgs[idx] && aiMsgs[idx].role === 'assistant') {
                    aiMsgs[idx].pending = false;
                    if (!aiMsgs[idx].content) aiMsgs[idx].content = '（已停止）';
                }
                aiRender();
                aiSetBusy(false);
                return;
            }
            aiFail(idx, '网络异常，请稍后重试', text);
        });
    }

    function aiOpenPanel(opts) {
        if (!aiRoot) return;
        opts = opts || {};
        var wantScope = opts.scope === 'post' ? 'post' : (opts.scope === 'site' ? 'site' : 'chat');
        var wantCid = wantScope === 'post' ? (parseInt(opts.cid, 10) || 0) : 0;

        /* 带预置问题的入口（404「我该去哪？」）：同 scope 重复点也要把问题补发出去 */
        if (aiIsOpen && wantScope === aiScope && wantCid === aiCid) {
            if (opts.prompt) aiSend(opts.prompt);
            else aiFocus();
            return;
        }

        var saved = aiRestore();
        if (saved && (saved.scope || 'chat') === wantScope && (parseInt(saved.cid, 10) || 0) === wantCid) {
            aiMsgs = Array.isArray(saved.msgs) ? saved.msgs : [];
            aiTitle = saved.title || opts.title || '';
        } else {
            aiMsgs = [];
            aiTitle = opts.title || '';
        }
        aiScope = wantScope;
        aiCid = wantCid;
        if (opts.title) aiTitle = opts.title;

        aiRoot.hidden = false;
        aiIsOpen = true;
        document.documentElement.classList.add('ecod-aiask-on');
        aiPaintHead();
        aiRender();
        aiSyncUser();
        Ecod.dialogOpen(aiRoot, aiQ('ta'), aiHide, { lock: false });
        aiFocus();
        if (opts.draft) {
            var ta = aiQ('ta');
            if (ta) ta.value = opts.draft;
        }
        if (opts.prompt) aiSend(opts.prompt);
    }

    Ecod.aiAskOpen = function (opts) {
        aiOpenPanel(opts || {});
    };
    Ecod.aiAskClose = function () {
        aiClose();
    };
    /* 对话页复用同一套受限 Markdown：一份白名单，避免两套渲染器各漏一处 */
    Ecod.md = aiMd;

    Ecod.initAiAsk = function () {
        aiRoot = document.querySelector('[data-ecod-aiask]');
        if (!aiRoot || !CFG.aiAsk || aiRoot.dataset.ecodBound) return;
        aiRoot.dataset.ecodBound = '1';

        /* 登录回来：pending 标记 → 自动重开并还原草稿 */
        try {
            var pend = sessionStorage.getItem(AI_PENDING_KEY);
            if (pend) {
                sessionStorage.removeItem(AI_PENDING_KEY);
                var p = JSON.parse(pend);
                setTimeout(function () {
                    aiOpenPanel({ scope: p.scope, cid: p.cid, title: p.title, draft: p.draft || '' });
                    if (Array.isArray(p.msgs) && p.msgs.length) {
                        aiMsgs = p.msgs;
                        aiRender();
                    }
                }, 60);
            }
        } catch (e) {}

        /* 头像球：懒加载引擎后挂到面板头部（与站宠同一份资源） */
        var head = aiQ('ball');
        if (head && Ecod.ballMount) {
            Ecod.ballMount(head, { emotion: '03', idle: { standbyAfter: 60000, sleepAfter: 180000 } });
        }

        aiRoot.addEventListener('submit', function (e) {
            e.preventDefault();
            if (aiBusy) {
                if (aiAbort) { try { aiAbort.abort(); } catch (err) {} }
                return;
            }
            var ta = aiQ('ta');
            var text = ta ? ta.value : '';
            if (ta) ta.value = '';
            aiSend(text);
        });

        aiRoot.addEventListener('click', function (e) {
            var t = e.target;

            if (t.closest('[data-ecod-aiask-close]')) { aiClose(); return; }

            if (t.closest('[data-ecod-aiask-send]') && aiBusy) {
                if (aiAbort) { try { aiAbort.abort(); } catch (err) {} }
                return;
            }

            var chip = t.closest('[data-ecod-aiask-chip]');
            if (chip) { aiSend(chip.getAttribute('data-ecod-aiask-chip')); return; }

            var retry = t.closest('[data-ecod-aiask-retry]');
            if (retry) {
                var q = aiLastQuestion();
                var i = parseInt(retry.getAttribute('data-ecod-aiask-retry'), 10);
                if (!isNaN(i)) aiMsgs.splice(i, 1);
                aiRender();
                if (q) aiSend(q);
                return;
            }

            if (t.closest('[data-ecod-aiask-unpin]')) {
                aiMsgs = [];
                aiScope = 'chat';
                aiCid = 0;
                aiTitle = '';
                aiPaintHead();
                aiRender();
                aiSave();
                return;
            }

            var copy = t.closest('[data-ecod-aiask-copy]');
            if (copy) {
                var box = copy.closest('.ecod_aiask__code');
                var pre = box ? box.querySelector('pre') : null;
                if (pre) {
                    Ecod.u.copyText(pre.textContent || '');
                    copy.textContent = '已复制';
                    setTimeout(function () { copy.textContent = '复制'; }, 1400);
                }
                return;
            }

            if (t.closest('[data-ecod-aiask-adopen]')) { aiAdLoad(); return; }
            if (t.closest('[data-ecod-aiask-adclose]')) { aiAdReset(); return; }
            if (t.closest('[data-ecod-aiask-adclaim]')) { aiAdClaim(); return; }

            if (t.closest('[data-ecod-login-open]')) {
                try {
                    sessionStorage.setItem(AI_PENDING_KEY, JSON.stringify({
                        scope: aiScope, cid: aiCid, title: aiTitle, msgs: aiMsgs.slice(-8),
                        draft: aiQ('ta') ? aiQ('ta').value : ''
                    }));
                } catch (err) {}
            }
        });

        document.addEventListener('click', function (e) {
            if (!aiIsOpen) return;
            if (aiRoot.contains(e.target)) return;
            if (e.target.closest('[data-ecod-aiask-open]')) return;
            if (e.target.closest('[data-ecod-authmodal]')) return;
            aiClose();
        });

        /* 摘要卡药丸 / dock 条目 / 球：统一入口，scope 由按钮声明 */
        document.addEventListener('click', function (e) {
            var opener = e.target.closest('[data-ecod-aiask-open]');
            if (!opener) return;
            e.preventDefault();
            var mode = opener.getAttribute('data-ecod-aiask-open') || 'chat';
            aiOpenPanel({
                scope: mode === 'post' ? 'post' : (mode === 'site' ? 'site' : 'chat'),
                cid: opener.getAttribute('data-cid') || 0,
                title: opener.getAttribute('data-title') || '',
                prompt: opener.getAttribute('data-ecod-aiask-prompt') || ''
            });
        });

        var ta = aiQ('ta');
        if (ta) {
            ta.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
                    e.preventDefault();
                    if (!aiBusy) {
                        var v = ta.value;
                        ta.value = '';
                        aiSend(v);
                    }
                }
            });
            ta.addEventListener('input', function () {
                ta.style.height = 'auto';
                ta.style.height = Math.min(96, ta.scrollHeight) + 'px';
            });
        }
    };

    Ecod.refresh = function () {
        /* 换页时先停掉上一页的朗读，避免声音延续到新页面 */
        Ecod.runPost('stopTts');
        Ecod.initNavOverflow();
        Ecod.initNavActive();
        /* PJAX 换页后重绑主题切换：此前靠「切换按钮永远在 shell 外」的隐式约定，
           把 initTheme 纳入 refresh 后，按钮放进 shell 内也不会静默失效
           （applyTheme 幂等，toggle 绑定走 once 不会重复） */
        Ecod.initTheme();
        Ecod.initHistory();
        Ecod.renderUserCenter();
        Ecod.initCredits();
        Ecod.initCreditsQuick();
        Ecod.initCheckin();
        Ecod.resetScroll();
        Ecod.initHeader();
        Ecod.initNavPin();
        Ecod.initNavMegaArrow();
        Ecod.initToTop();
        Ecod.initProgress();
        Ecod.runPost('initToc');
        Ecod.runPost('initContent');
        Ecod.runPost('initEmbeds');
        Ecod.initImages();
        Ecod.runPost('initInline');
        Ecod.runPost('initCashier');
        if (CFG.lightbox !== false) {
            Ecod.runPost('initLightbox');
        }
        Ecod.runPost('initPostAct');
        Ecod.runPost('initQuickBar');
        Ecod.runPost('initShareCard');
        Ecod.runPost('initTts');
        Ecod.runPost('initReadbar');
        Ecod.runPost('initComment');
        Ecod.runPost('initCommentFold');
        Ecod.runPost('initTurnstile');
        Ecod.runPost('initThirdComments');
        Ecod.runPost('initEmoji');
        Ecod.runPost('initTabs');
        Ecod.initCatbar();
        Ecod.initCatTools();
        Ecod.initSort();
        Ecod.initInfinite();
        Ecod.initRank();
        Ecod.initTp();
        Ecod.initWtabs();
        Ecod.initHeatgrid();
        Ecod.initFollow();
        Ecod.runPost('initContribute');
        Ecod.runPost('initUcPosts');
        Ecod.runPost('initClike');
        Ecod.runPost('initExtras');
        Ecod.runPost('initAudio');
        Ecod.initDock();
        Ecod.initNotice();
        Ecod.initAccount();
        Ecod.runPost('initFriendApply');
        Ecod.initSearchSuggest();
        Ecod.initSearchPanel();
        Ecod.initSearchRecord();
        Ecod.initNotifs();
        Ecod.initTicker();
        Ecod.initTasks();
        Ecod.initMsgCenter();
        Ecod.initTickets();
        Ecod.initSlider();
        Ecod.initCardAct();
        Ecod.initLikeState();
        Ecod.initStream();
        Ecod.runPost('initAi');
        Ecod.initAiAsk();
        /* 换页即收起面板：旧页的上下文与滚动位置不该带进新页 */
        Ecod.aiAskClose();
        Ecod.runPost('bindPayComment');
        Ecod.runPost('initShowMore');
        Ecod.runPost('initPoll');
        Ecod.runPost('initAiChat');
        Ecod.runPost('initCmtAi');
        Ecod.runPost('initLottery');
        Ecod.runPost('initAdSelf');
        Ecod.runPost('initShop');
        Ecod.initAnimate();
        Ecod.initCursor();
        Ecod.runPost('initPrism');
        syncFavButtons();
        Ecod.renderFavs();
        Ecod.initBalls();
    };

    /* ------------------------------------------------------------------
     * 内容页增强包调度
     *
     * post/page 专属模块拆在 ecod.post.js（页脚按模板条件输出 defer 标签），
     * PJAX 从列表页进入内容页时由 ensurePostBundle() 动态注入。
     * refresh/boot 对缺失模块一律 runPost 静默跳过，两个文件可独立存在。
     * ---------------------------------------------------------------- */
    Ecod.runPost = function (name) {
        var fn = Ecod[name];
        if (typeof fn === 'function') fn();
    };

    /* ---------- 页面族 CSS 调度（S3 分包） ----------
     * head 只随当前页输出 core + 所属族；PJAX 跨族换页时把缺失的族
     * 注入 head，全部 onload 后才放行 refresh —— 样式不齐不跑入场动画，
     * 语义与直载时的 ecod:cssready 完全一致。
     * 已加载集合：直载时从 head 的 data-ecod-fam 标记收集。 */
    Ecod._fams = {};
    Ecod.ensurePageCss = function (done) {
        var shell = document.getElementById('ecod-shell');
        var want = shell && shell.getAttribute('data-ecod-fams')
            ? shell.getAttribute('data-ecod-fams').split(',')
            : ['core'];
        var need = [];
        want.forEach(function (f) {
            if (f && !Ecod._fams[f]) { Ecod._fams[f] = 1; need.push(f); }
        });
        if (!need.length) { if (done) done(); return; }
        var left = need.length;
        var settle = function () { if (--left === 0 && done) done(); };
        need.forEach(function (f) {
            var url = (CFG.fams || {})[f];
            if (!url) { settle(); return; }
            var l = document.createElement('link');
            l.rel = 'stylesheet';
            l.setAttribute('data-ecod-fam', f);
            l.href = url;
            l.onload = l.onerror = settle;
            document.head.appendChild(l);
        });
    };

    Ecod.ensurePostBundle = function (done) {
        var shell = document.getElementById('ecod-shell');
        /* 内容页整包；例外：任何页面出现抽奖组件（首页/侧栏挂件）都至少要 post.js，
           否则 initLottery 不跑，抽奖按钮与「我的记录」在非内容页全部失灵 */
        var needPost = shell && shell.getAttribute('data-ecod-bundle') === 'post';
        var needLottery = !!document.querySelector('[data-ecod-lottery]');
        if ((!needPost && !needLottery) || Ecod._postLoaded) {
            if (done) done();
            return;
        }
        /* 内容页脚本链：prism（代码高亮库，先于增强包，initPrism 依赖它）
           → 二维码（分享卡依赖）→ 增强包。按序注入保持 defer 的执行顺序；
           正文页直载时页脚已按同序输出这三个标签，这里只补 PJAX 场景。
           抽奖挂件触发的加载只取 post.js（prism / qr 对它无用，别拖慢首屏）。
           配色 CSS 体积小，全站在 head 常驻（见 include.php），无需注入。 */
        var chain = needPost
            ? [CFG.prismUrl, CFG.qrUrl, CFG.post].filter(Boolean)
            : [CFG.post].filter(Boolean);
        if (needPost) Ecod._postLoaded = true;
        (function next() {
            var url = chain.shift();
            if (!url) {
                if (done) done();
                return;
            }
            var s = document.createElement('script');
            s.src = url;
            s.onload = s.onerror = next;
            document.head.appendChild(s);
        })();
    };

    /* ------------------------------------------------------------------
     * 表情球（Grok Ball，assets/js/vendor/grok-ball.js，MIT）
     *
     * 引擎 32KB gzip，绝不进首屏：ensureBall() 与 ensurePostBundle 同一套
     * 注入约定按需加载（AI 对话页 / 404 彩蛋 / 抽奖页 / 搜索面板 / 站宠）。
     * reduced-motion 用户连引擎都不发（ballMount / ballCelebrate 直接短路）。
     * ballMount 返回「壳句柄」：加载完成前方法为空操作，加载后透明升级，
     * 调用方无须关心引擎是否已就位。
     * ---------------------------------------------------------------- */
    Ecod._ballLoaded = false;
    Ecod._ballLoading = false;
    Ecod._ballQ = [];
    Ecod.ensureBall = function (done) {
        if (Ecod._ballLoaded) {
            if (done) done();
            return;
        }
        if (done) Ecod._ballQ.push(done);
        if (Ecod._ballLoading) return;
        Ecod._ballLoading = true;
        var chain = [CFG.ballEngine, CFG.ball].filter(Boolean);
        (function next() {
            var url = chain.shift();
            if (!url) {
                Ecod._ballLoaded = true;
                var q = Ecod._ballQ;
                Ecod._ballQ = [];
                q.forEach(function (cb) { cb(); });
                return;
            }
            var s = document.createElement('script');
            s.src = url;
            s.onload = s.onerror = next;
            document.head.appendChild(s);
        })();
    };
    Ecod.ballMount = function (mount, opts) {
        var stub = {
            set: function () {}, flash: function () {}, celebrate: function () {},
            spin: function () {}, burst: function () {}, resetIdle: function () {},
            wake: function () {}, destroy: function () {}, holding: function () { return false; },
            _h: null
        };
        if (!mount || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) {
            return stub;
        }
        Ecod.ensureBall(function () {
            if (!window.EcodBall || !document.contains(mount)) return;
            stub._h = window.EcodBall.create(mount, opts || {});
            if (!stub._h) return;
            ['set', 'flash', 'celebrate', 'spin', 'burst', 'resetIdle', 'wake', 'holding', 'destroy'].forEach(function (k) {
                stub[k] = function () { return stub._h[k].apply(stub._h, arguments); };
            });
        });
        return stub;
    };
    /* 全员短表情（点赞/收藏/复制成功等「蹭球」联动）：只有已经活着的球响应。
       引擎未加载或无实例时直接跳过——绝不为一次性反馈加载 32KB 引擎 */
    Ecod.ballFlashAll = function (id, ms) {
        if (!Ecod._ballLoaded || !window.EcodBall || !window.EcodBall.count()) return;
        window.EcodBall.flashAll(id, ms);
    };
    /* 站内喜事（签到成功等）：活着的球一起撒花 */
    Ecod.ballCelebrate = function (ms) {
        Ecod.ballFlashAll('33', ms || 2800);
    };

    /* ---------- 页面级挂载：404 彩蛋 + 站宠（refresh 每次换页都跑） ---------- */
    Ecod.initBalls = function () {
        if (window.EcodBall && window.EcodBall.sweep) window.EcodBall.sweep();

        /* 404 彩蛋：好奇注视 + 点击自旋甩彩带 */
        var b404 = document.querySelector('[data-ecod-ball404]');
        if (b404 && !b404.dataset.ballReady) {
            b404.dataset.ballReady = '1';
            var egg = Ecod.ballMount(b404, { emotion: '03' });
            once(b404, 'ball404Click', 'click', function () {
                egg.spin(2);
                egg.flash('13', 1800);
            });
        }

        Ecod.initMascot();
    };

    /* 站宠：右下角常驻表情球（后台「互动 → 积分与 AI → 表情球站宠」开关）。
       桌面端专属（≤680px 隐藏，避免与移动 dock 抢位置）；idle 期懒加载引擎。
       睡眠/待机由引擎内置 idle 生命周期驱动，用户活动经 wake() 唤醒。 */
    Ecod.initMascot = function () {
        if (!CFG.mascot || document.querySelector('.ecod_ballpet')) return;
        var mqReduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (window.matchMedia && window.matchMedia('(max-width: 680px)').matches) return;

        /* 减少动态效果：动画引擎不加载（既有逻辑），但 AI 入口开着时
           降级渲染一个静态圆脸按钮——功能不依赖动画（文档 §14.5） */
        if (mqReduce) {
            if (!CFG.aiAsk) return;
            var bootStatic = function () {
                if (!document.body || document.querySelector('.ecod_ballpet')) return;
                var el = document.createElement('button');
                el.type = 'button';
                el.className = 'ecod_ballpet is-static';
                el.setAttribute('aria-label', '打开 AI 助手');
                el.setAttribute('data-ecod-aiask-open', 'chat');
                el.innerHTML = '<svg viewBox="0 0 64 64" aria-hidden="true">'
                    + '<circle cx="32" cy="32" r="30" fill="currentColor"/>'
                    + '<circle cx="23" cy="27" r="4.5" fill="#fff"/><circle cx="41" cy="27" r="4.5" fill="#fff"/>'
                    + '<path d="M22 41q10 7 20 0" stroke="#fff" stroke-width="3" fill="none" stroke-linecap="round"/>'
                    + '</svg>';
                document.body.appendChild(el);
            };
            if (window.requestIdleCallback) requestIdleCallback(bootStatic, { timeout: 4000 });
            else setTimeout(bootStatic, 2500);
            return;
        }

        var boot = function () {
            if (!document.body || document.querySelector('.ecod_ballpet')) return;
            /* AI 入口开着时球是唯一入口 → 必须是真 button；关着才是纯装饰 */
            var ask = !!CFG.aiAsk;
            var el = document.createElement(ask ? 'button' : 'div');
            el.className = 'ecod_ballpet';
            if (ask) {
                el.type = 'button';
                el.setAttribute('aria-label', '打开 AI 助手');
                el.setAttribute('data-ecod-aiask-open', 'chat');
            } else {
                el.setAttribute('aria-hidden', 'true');
            }
            document.body.appendChild(el);
            var pet = Ecod.ballMount(el, {
                emotion: '02',
                idle: { standbyAfter: 45000, sleepAfter: 150000 }
            });
            once(el, 'ballpetClick', 'click', function () {
                /* 展开动画由面板负责；这里只留一次微自旋作过渡 */
                if (ask) {
                    pet.spin(1);
                    return;
                }
                pet.spin(2);
                pet.flash('13', 1600);
            });
            /* 任意输入都算「还在」：重置待机计时并从瞌睡里唤醒（2s 节流） */
            var last = 0;
            var wake = function () {
                var now = Date.now();
                if (now - last < 2000) return;
                last = now;
                pet.wake();
            };
            ['pointermove', 'keydown', 'scroll', 'touchstart'].forEach(function (ev) {
                window.addEventListener(ev, wake, { passive: true });
            });
        };
        if (window.requestIdleCallback) requestIdleCallback(boot, { timeout: 4000 });
        else setTimeout(boot, 2500);
    };

    /* 内部工具导出：ecod.post.js 经 Ecod.u 取用（函数声明已提升，可安全引用） */
    Ecod.u = {
        once: once,
        copyText: copyText,
        esc: esc,
        shortNum: shortNum,
        syncFavButtons: syncFavButtons
    };

    document.addEventListener('DOMContentLoaded', function () {
        Ecod.initTheme();
        Ecod.initImgError();
        Ecod.initDrawer();
        Ecod.initHotkey();
        Ecod.initUserPop();
        Ecod.initPopDismiss();
        Ecod.initActions();
        Ecod.initCtxMenu();
        Ecod.runPost('initReply');
        Ecod.initPjax();
        /* 直载场景：head 里已有本页的族 link，登记进已加载集合（S3） */
        Array.prototype.forEach.call(document.querySelectorAll('link[data-ecod-fam]'), function (l) {
            Ecod._fams[l.getAttribute('data-ecod-fam')] = 1;
        });
        /* 首屏若有抽奖挂件等 post 包组件，先补脚本再初始化（见 ensurePostBundle） */
        Ecod.ensurePostBundle(function () {
            Ecod.refresh();
        });
        /* 首屏初始化完毕：此后的 refresh（PJAX 换页）与加载更多才铺入场动效 */
        Ecod._animArmed = true;
        /* 完整样式到位后解除启动期过渡禁用 */
        ecodUnboot();
    });
})();
