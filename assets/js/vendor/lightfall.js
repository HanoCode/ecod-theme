/**
 * Lightfall · WebGL2 流光背景引擎（无框架移植版）
 *
 * 来源：React Bits 的 <Lightfall /> 组件（https://reactbits.dev），
 * 按其 JavaScript + CSS 变体移植：React 外壳（hooks / refs / JSX）已剥离，
 * 渲染核心（着色器、帧循环、指针镜头、warp、intro、bloom 链）逐行保留，
 * 改为 create(container, options) → { set / destroy } 的原生接口。
 *
 * 用法：
 *   var fx = EcodLightfall.create(el, { colors: ['#8ab4ff'], lightMode: true });
 *   fx.set({ lightMode: false });   // 运行时改参数，不必重建
 *   fx.destroy();                    // 释放 GL 资源与监听
 *
 * 注意：
 *   - 仅 WebGL2 可用时创建；返回 null 表示不支持，调用方须静默降级。
 *   - 源码风格保持 ES5（主题 JS 约定，构建只压缩不转译）。
 *
 * Licensed under the MIT License（同 React Bits）。
 */

(function (root) {
    'use strict';

    var MAX_COLORS = 8;
    var BLOOM_LEVELS = 4;
    var INTRO_SECONDS = 2.6;
    var WARP_SPEED = 9;
    var TAU = Math.PI * 2;

    var VERTEX = [
        '#version 300 es',
        'in vec2 aPosition;',
        'void main() {',
        '  gl_Position = vec4(aPosition, 0.0, 1.0);',
        '}'
    ].join('\n');

    var STREAKS = [
        '#version 300 es',
        'precision highp float;',
        'uniform vec2 uView;',
        'uniform float uZoom;',
        'uniform float uPhase;',
        'uniform float uTwinkleClock;',
        'uniform float uRings;',
        'uniform vec3 uColors[8];',
        'uniform int uColorCount;',
        'uniform int uStreakCount;',
        'uniform int uColumns;',
        'uniform float uWidth;',
        'uniform float uTail;',
        'uniform float uTwinkle;',
        'uniform float uHead;',
        'uniform float uDepth;',
        'uniform vec4 uMouse;',
        'uniform vec3 uLens;',
        'uniform float uLightMode;',
        'uniform float uEncode;',
        'out vec4 outColor;',
        '',
        'const vec3 CAMERA = vec3(0.0, -0.8888889, -0.2222222);',
        'const float TAU = 6.28318530718;',
        'const float SPACING = 0.005;',
        'const float REACH = 0.32;',
        '',
        'uint scramble(uint x) {',
        '  x ^= x >> 16;',
        '  x *= 0x7feb352du;',
        '  x ^= x >> 15;',
        '  x *= 0x846ca68bu;',
        '  x ^= x >> 16;',
        '  return x;',
        '}',
        '',
        'float unit(uint x) {',
        '  return float(x & 0xffffu) / 65535.0;',
        '}',
        '',
        'float stroke(float d, float w) {',
        '  float s = max(0.75 * w, 0.55);',
        '  return 0.75 * w / s * exp(-0.5 * d * d / (s * s));',
        '}',
        '',
        'float orb(float d, float r) {',
        '  float s = max(0.6 * r, 0.7);',
        '  float e = 0.6 * r / s;',
        '  return e * e * exp(-0.5 * d * d / (s * s));',
        '}',
        '',
        'void main() {',
        '  vec2 frag = gl_FragCoord.xy;',
        '  vec2 screen = (2.0 * frag - uView) / uView.x;',
        '  vec2 p = screen;',
        '  mat2 warp = mat2(1.0);',
        '  if (uLens.z > 0.0) {',
        '    vec2 o = p - uLens.xy;',
        '    float e = uLens.z * exp(-dot(o, o) / (REACH * REACH));',
        '    p -= o * e;',
        '    warp = mat2(1.0 - e) + outerProduct(o, o) * (2.0 * e / (REACH * REACH));',
        '  }',
        '',
        '  vec3 dir = vec3(p, uZoom);',
        '  vec3 exits = (1.0 - CAMERA * sign(dir)) / max(abs(dir), vec3(1e-6));',
        '  float u = min(exits.x, min(exits.y, exits.z));',
        '  for (int i = 0; i < 5; i++) {',
        '    vec3 h = CAMERA + u * dir;',
        '    vec3 h2 = h * h;',
        '    u -= (dot(h2, h2) - 1.0) / (4.0 * dot(dir, h2 * h));',
        '  }',
        '  vec3 hit = CAMERA + u * dir;',
        '  vec3 normal = hit * hit * hit;',
        '  float facing = dot(normal, dir);',
        '  vec3 stepX = u * (vec3(1.0, 0.0, 0.0) - dir * (normal.x / facing));',
        '  vec3 stepY = u * (vec3(0.0, 1.0, 0.0) - dir * (normal.y / facing));',
        '  float ring = hit.y * hit.y + hit.z * hit.z;',
        '  float theta = atan(hit.z, hit.y);',
        '  mat2 jacobian = mat2(',
        '    stepX.x,',
        '    (hit.y * stepX.z - hit.z * stepX.y) / ring,',
        '    stepY.x,',
        '    (hit.y * stepY.z - hit.z * stepY.y) / ring',
        '  ) * warp * (2.0 / uView.x);',
        '',
        '  vec2 gradX = vec2(jacobian[0][0], jacobian[1][0]);',
        '  vec2 gradTheta = vec2(jacobian[0][1], jacobian[1][1]);',
        '  float scale = max(length(gradX), 1e-8);',
        '  float det = max(abs(gradX.x * gradTheta.y - gradX.y * gradTheta.x), 1e-12);',
        '  float along = scale / det;',
        '  float skew = dot(gradX, gradTheta) / det;',
        '  float spacing = SPACING / scale;',
        '  float detail = smoothstep(1.6, 3.2, spacing);',
        '  if (detail <= 0.0) {',
        '    outColor = vec4(0.0, 0.0, 0.0, 1.0);',
        '    return;',
        '  }',
        '  float halfWidth = min(uWidth / scale, 0.2 * spacing);',
        '  float headRadius = min(2.0 * uWidth / scale, 0.35 * spacing);',
        '  float headReach = 4.0 * max(0.6 * headRadius, 0.7);',
        '  float edge = uColumns > 1 ? 1.0 : 0.5;',
        '  float period = TAU / uRings;',
        '  float fog = exp(-max(u * length(dir) - 0.6, 0.0) * 1.6 * uDepth);',
        '',
        '  float boost = 1.0;',
        '  if (uMouse.z > 0.0) {',
        '    vec2 m = screen - uMouse.xy;',
        '    boost += 2.0 * uMouse.z * exp(-dot(m, m) / max(uMouse.w * uMouse.w, 1e-4));',
        '  }',
        '',
        '  int count = max(uColorCount, 1);',
        '  vec3 total = vec3(0.0);',
        '  for (int layer = 0; layer < 16; layer++) {',
        '    if (layer >= uStreakCount) break;',
        '    float x = hit.x + float(layer) * SPACING / 8.0;',
        '    float nearest = floor(x / SPACING + 0.5);',
        '    float side = x >= nearest * SPACING ? 1.0 : -1.0;',
        '    for (int k = 0; k < 2; k++) {',
        '      if (k >= uColumns) break;',
        '      float column = nearest + side * float(k);',
        '      float across = (x - column * SPACING) / scale;',
        '      uint seed = scramble(uint(int(column) + 65536) + uint(layer) * 0x9e3779b9u);',
        '      uint next = scramble(seed);',
        '      float hue = unit(seed >> 16);',
        '      float delta = theta - uPhase * (1.0 + floor(unit(seed) * 16.0) / 16.0) - unit(next) * period;',
        '      delta -= period * floor(delta / period + 0.5);',
        '      float ahead = delta * along - across * skew;',
        '      if (abs(ahead) > headReach && (delta > 0.0 || -delta * uTail > 3.2)) continue;',
        '      float behind = max(-delta, 0.0);',
        '      float fall = exp(-uTail * behind) * (1.0 - smoothstep(0.5, 0.95, behind / (0.5 * period)));',
        '      fall *= 1.0 - smoothstep(1.2, 3.2, uTail * behind);',
        '      float reach = length(vec2(across, ahead));',
        '      float trail = stroke(ahead > 0.0 ? reach : abs(across), halfWidth * mix(0.4, 1.0, fall)) * fall;',
        '      float glint = orb(reach, headRadius) * mix(3.0, 1.8, uLightMode);',
        '      float weight = mix(1.5, 1.0 + sin(uTwinkleClock + 7.0 * hue + 4.0), uTwinkle);',
        '      weight *= 1.0 - smoothstep(0.72 * edge, edge, abs(across) / spacing);',
        '      vec3 color = uColors[min(int(hue * float(count)), count - 1)];',
        '      vec3 hot = mix(color, vec3(1.0), uHead * (1.0 - uLightMode));',
        '      total += (trail * color + glint * hot) * weight;',
        '    }',
        '  }',
        '',
        '  outColor = vec4(total * fog * boost * detail * uEncode, 1.0);',
        '}'
    ].join('\n');

    var DOWNSAMPLE = [
        '#version 300 es',
        'precision highp float;',
        'uniform sampler2D uSource;',
        'uniform vec2 uTarget;',
        'uniform vec2 uTexel;',
        'uniform float uLimit;',
        'out vec4 outColor;',
        '',
        'void main() {',
        '  vec2 uv = gl_FragCoord.xy / uTarget;',
        '  vec3 color = texture(uSource, uv - uTexel).rgb;',
        '  color += texture(uSource, uv + uTexel).rgb;',
        '  color += texture(uSource, uv + vec2(uTexel.x, -uTexel.y)).rgb;',
        '  color += texture(uSource, uv + vec2(-uTexel.x, uTexel.y)).rgb;',
        '  outColor = vec4(min(color * 0.25, vec3(uLimit)), 1.0);',
        '}'
    ].join('\n');

    var BLUR = [
        '#version 300 es',
        'precision highp float;',
        'uniform sampler2D uSource;',
        'uniform vec2 uTarget;',
        'uniform vec2 uStep;',
        'out vec4 outColor;',
        '',
        'void main() {',
        '  vec2 uv = gl_FragCoord.xy / uTarget;',
        '  vec3 color = texture(uSource, uv).rgb * 0.181862;',
        '  color += (texture(uSource, uv + uStep * 1.423135).rgb + texture(uSource, uv - uStep * 1.423135).rgb) * 0.284316;',
        '  color += (texture(uSource, uv + uStep * 3.326702).rgb + texture(uSource, uv - uStep * 3.326702).rgb) * 0.106598;',
        '  color += (texture(uSource, uv + uStep * 5.242989).rgb + texture(uSource, uv - uStep * 5.242989).rgb) * 0.018156;',
        '  outColor = vec4(color, 1.0);',
        '}'
    ].join('\n');

    var COMPOSITE = [
        '#version 300 es',
        'precision highp float;',
        'uniform sampler2D uScene;',
        'uniform sampler2D uBloom0;',
        'uniform sampler2D uBloom1;',
        'uniform sampler2D uBloom2;',
        'uniform sampler2D uBloom3;',
        'uniform vec2 uView;',
        'uniform float uDecode;',
        'uniform float uGlow;',
        'uniform float uBloom;',
        'uniform float uPresence;',
        'uniform float uOpacity;',
        'uniform float uFade;',
        'uniform float uLightMode;',
        'uniform vec3 uTopColor;',
        'uniform float uTopGlow;',
        'out vec4 outColor;',
        '',
        'float smootherstep01(float t) {',
        '  t = clamp(t, 0.0, 1.0);',
        '  return t * t * t * (t * (t * 6.0 - 15.0) + 10.0);',
        '}',
        '',
        'void main() {',
        '  vec2 uv = gl_FragCoord.xy / uView;',
        '  vec3 light = texelFetch(uScene, ivec2(gl_FragCoord.xy), 0).rgb;',
        '  if (uBloom > 0.0) {',
        '    vec3 halo = texture(uBloom0, uv).rgb * 0.42;',
        '    halo += texture(uBloom1, uv).rgb * 0.3;',
        '    halo += texture(uBloom2, uv).rgb * 0.18;',
        '    halo += texture(uBloom3, uv).rgb * 0.1;',
        '    light += halo * uBloom * (uLightMode > 0.5 ? 0.2 : 1.0);',
        '  }',
        '  light *= uDecode * uGlow * uPresence;',
        '',
        '  if (uLightMode < 0.5 && uTopGlow > 0.0) {',
        '    float aspect = uView.x / uView.y;',
        '    vec2 q = vec2((uv.x - 0.5) * aspect, 1.04 - uv.y);',
        '    float spread = 0.34 * max(aspect, 0.75);',
        '    float core = exp(-q.x * q.x / (spread * spread) - q.y * q.y / 0.06);',
        '    float halo = exp(-q.x * q.x / (3.6 * spread * spread) - q.y * q.y / 0.18);',
        '    light += uTopColor * uTopGlow * (core * 0.85 + halo * 0.3) * uPresence;',
        '  }',
        '',
        '  vec3 lit = max(light, 0.0);',
        '  vec3 mapped = sqrt(1.0 - exp(-1.3 * lit * lit / (lit + 0.025)));',
        '  float peak = max(mapped.r, max(mapped.g, mapped.b));',
        '',
        '  float mask = uOpacity;',
        '  float edge = uFade * 0.5;',
        '  if (edge > 0.0) {',
        '    mask *= smootherstep01(uv.x / edge) * smootherstep01((1.0 - uv.x) / edge);',
        '    mask *= smootherstep01(uv.y / edge) * smootherstep01((1.0 - uv.y) / edge);',
        '  }',
        '  float grain = (fract(52.9829189 * fract(dot(gl_FragCoord.xy, vec2(0.06711056, 0.00583715)))) - 0.5) / 255.0;',
        '',
        '  if (uLightMode > 0.5) {',
        '    float coverage = clamp(smoothstep(0.0, 0.5, peak) * mask * 0.94 + grain, 0.0, 1.0);',
        '    vec3 chroma = sqrt(clamp(lit / max(max(lit.r, max(lit.g, lit.b)), 1e-6), 0.0, 1.0));',
        '    chroma = pow(chroma, vec3(1.35));',
        '    chroma /= max(max(chroma.r, max(chroma.g, chroma.b)), 1e-4);',
        '    float saturation = 1.0 - min(chroma.r, min(chroma.g, chroma.b));',
        '    chroma = mix(vec3(0.3), chroma, smoothstep(0.08, 0.35, saturation));',
        '    outColor = vec4(chroma * coverage, coverage);',
        '  } else {',
        '    vec3 color = clamp(mapped + grain, 0.0, 1.0) * mask;',
        '    outColor = vec4(color, max(color.r, max(color.g, color.b)));',
        '  }',
        '}'
    ].join('\n');

    /* 组件默认值（与 React 版 props 表一致；后台未暴露的参数走这里） */
    var DEFAULTS = {
        colors: ['#ffffff'],
        backgroundColor: '#94a3b8',
        speed: 0.3,
        streakCount: 2,
        streakWidth: 0.8,
        streakLength: 1,
        density: 0.6,
        twinkle: 0.3,
        glow: 1,
        backgroundGlow: 0.1,
        headGlow: 0.6,
        depth: 0.5,
        bloom: 0.35,
        zoom: 3,
        opacity: 1,
        mouseInteraction: true,
        mouseStrength: 0.4,
        mouseRadius: 0.45,
        mouseDampening: 0.15,
        lens: 0.5,
        holdToWarp: true,
        intro: true,
        fade: 0,
        lightMode: false,
        paused: false,
        dpr: null
    };

    function clamp(value, min, max) {
        return Math.min(max, Math.max(min, value));
    }

    function merge(base, patch) {
        var out = {};
        var key;
        for (key in base) {
            if (Object.prototype.hasOwnProperty.call(base, key)) {
                out[key] = base[key];
            }
        }
        if (patch) {
            for (key in patch) {
                if (Object.prototype.hasOwnProperty.call(patch, key) && patch[key] !== undefined) {
                    out[key] = patch[key];
                }
            }
        }
        return out;
    }

    /* props → 内部设置（范围钳制与 React 版一致） */
    function normalize(raw) {
        var s = merge(DEFAULTS, raw);
        var colors = Array.isArray(s.colors) && s.colors.length ? s.colors : ['#ffffff'];
        var list = [];
        var i;
        for (i = 0; i < Math.min(colors.length, MAX_COLORS); i++) {
            list.push(String(colors[i]));
        }
        return {
            colors: list,
            backgroundColor: String(s.backgroundColor),
            speed: s.speed,
            streakCount: Math.round(clamp(s.streakCount, 1, 16)),
            streakWidth: Math.max(0.05, s.streakWidth),
            streakLength: Math.max(0.05, s.streakLength),
            density: s.density,
            twinkle: clamp(s.twinkle, 0, 1),
            glow: Math.max(0, s.glow),
            backgroundGlow: Math.max(0, s.backgroundGlow),
            headGlow: clamp(s.headGlow, 0, 1),
            depth: Math.max(0, s.depth),
            bloom: Math.max(0, s.bloom),
            zoom: Math.max(0.1, s.zoom),
            opacity: clamp(s.opacity, 0, 1),
            mouseInteraction: !!s.mouseInteraction,
            mouseStrength: Math.max(0, s.mouseStrength),
            mouseRadius: Math.max(0.01, s.mouseRadius),
            mouseDampening: Math.max(0, s.mouseDampening),
            lens: clamp(s.lens, 0, 1),
            holdToWarp: !!s.holdToWarp,
            intro: !!s.intro,
            fade: clamp(s.fade, 0, 1),
            lightMode: !!s.lightMode,
            paused: !!s.paused,
            dpr: s.dpr
        };
    }

    function compile(gl, type, source) {
        var shader = gl.createShader(type);
        if (!shader) {
            return null;
        }
        gl.shaderSource(shader, source);
        gl.compileShader(shader);
        if (gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
            return shader;
        }
        gl.deleteShader(shader);
        return null;
    }

    function link(gl, fragmentSource) {
        var vertex = compile(gl, gl.VERTEX_SHADER, VERTEX);
        var fragment = compile(gl, gl.FRAGMENT_SHADER, fragmentSource);
        var program = gl.createProgram();
        if (!vertex || !fragment || !program) {
            return null;
        }
        gl.attachShader(program, vertex);
        gl.attachShader(program, fragment);
        gl.bindAttribLocation(program, 0, 'aPosition');
        gl.linkProgram(program);
        gl.deleteShader(vertex);
        gl.deleteShader(fragment);
        if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
            gl.deleteProgram(program);
            return null;
        }
        var uniforms = {};
        var count = gl.getProgramParameter(program, gl.ACTIVE_UNIFORMS);
        for (var i = 0; i < count; i++) {
            var info = gl.getActiveUniform(program, i);
            if (info) {
                uniforms[info.name.replace(/\[0\]$/, '')] = gl.getUniformLocation(program, info.name);
            }
        }
        return { program: program, uniforms: uniforms };
    }

    function create(container, options) {
        if (!container) {
            return null;
        }
        var canvas = document.createElement('canvas');
        canvas.className = 'ecod_lightfall__canvas';
        canvas.setAttribute('aria-hidden', 'true');
        container.appendChild(canvas);

        var gl = canvas.getContext('webgl2', {
            alpha: true,
            premultipliedAlpha: true,
            antialias: false,
            depth: false,
            stencil: false,
            powerPreference: 'high-performance'
        });
        var onRestore = options && options.onContextRestored;
        if (!gl) {
            container.removeChild(canvas);
            return null;
        }

        var settings = normalize(options);
        var floatTargets = !!gl.getExtension('EXT_color_buffer_float');
        var streakProgram = link(gl, STREAKS);
        var downProgram = link(gl, DOWNSAMPLE);
        var blurProgram = link(gl, BLUR);
        var compositeProgram = link(gl, COMPOSITE);
        if (!streakProgram || !downProgram || !blurProgram || !compositeProgram) {
            container.removeChild(canvas);
            return null;
        }

        var onContextLost = function (event) {
            event.preventDefault();
        };
        var onContextRestored = function () {
            if (typeof onRestore === 'function') {
                onRestore();
            }
        };
        canvas.addEventListener('webglcontextlost', onContextLost);
        canvas.addEventListener('webglcontextrestored', onContextRestored);

        var buffer = gl.createBuffer();
        var vao = gl.createVertexArray();
        gl.bindVertexArray(vao);
        gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
        gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 3, -1, -1, 3]), gl.STATIC_DRAW);
        gl.enableVertexAttribArray(0);
        gl.vertexAttribPointer(0, 2, gl.FLOAT, false, 0, 0);

        var format = floatTargets ? gl.RGBA16F : gl.RGBA8;
        var encode = floatTargets ? 1 : 0.25;

        var createTarget = function () {
            return { texture: null, framebuffer: gl.createFramebuffer(), width: 0, height: 0 };
        };
        var scene = createTarget();
        var levels = [];
        for (var bi = 0; bi < BLOOM_LEVELS; bi++) {
            levels.push([createTarget(), createTarget()]);
        }

        var allocate = function (target, width, height) {
            if (target.texture && target.width === width && target.height === height) {
                return;
            }
            if (target.texture) {
                gl.deleteTexture(target.texture);
            }
            target.texture = gl.createTexture();
            target.width = width;
            target.height = height;
            gl.bindTexture(gl.TEXTURE_2D, target.texture);
            gl.texStorage2D(gl.TEXTURE_2D, 1, format, width, height);
            gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
            gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
            gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
            gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
            gl.bindFramebuffer(gl.FRAMEBUFFER, target.framebuffer);
            gl.framebufferTexture2D(gl.FRAMEBUFFER, gl.COLOR_ATTACHMENT0, gl.TEXTURE_2D, target.texture, 0);
        };

        var pass = function (target, program) {
            gl.bindFramebuffer(gl.FRAMEBUFFER, target ? target.framebuffer : null);
            gl.viewport(0, 0, target ? target.width : canvas.width, target ? target.height : canvas.height);
            gl.useProgram(program.program);
            return program.uniforms;
        };

        var bind = function (unit, texture) {
            gl.activeTexture(gl.TEXTURE0 + unit);
            gl.bindTexture(gl.TEXTURE_2D, texture);
        };

        var probe = document.createElement('canvas').getContext('2d', { willReadFrequently: true });
        var colorCache = {};
        var toRgb = function (value) {
            if (Object.prototype.hasOwnProperty.call(colorCache, value)) {
                return colorCache[value];
            }
            var rgb = [1, 1, 1];
            if (probe) {
                probe.clearRect(0, 0, 1, 1);
                probe.fillStyle = '#ffffff';
                probe.fillStyle = value;
                probe.fillRect(0, 0, 1, 1);
                var data = probe.getImageData(0, 0, 1, 1).data;
                rgb = [data[0] / 255, data[1] / 255, data[2] / 255];
            }
            colorCache[value] = rgb;
            return rgb;
        };
        var toLinear = function (value) {
            var rgb = toRgb(value);
            return [rgb[0] * rgb[0], rgb[1] * rgb[1], rgb[2] * rgb[2]];
        };

        var reduce = false;
        if (window.matchMedia) {
            reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        }
        var palette = new Float32Array(MAX_COLORS * 3);
        var pointer = { x: 0, y: 0, inside: false, held: false };
        var state = {
            travel: 0,
            intro: settings.intro && !reduce ? 0 : 1,
            mouseX: 0,
            mouseY: 0,
            lensX: 0,
            lensY: 0,
            touch: 0,
            warp: 0
        };
        var raf = 0;
        var last = 0;
        var visible = true;
        var destroyed = false;

        var render = function () {
            var s = settings;
            var width = canvas.width;
            var height = canvas.height;
            var cssWidth = Math.max(1, container.clientWidth);
            var cssHeight = Math.max(1, container.clientHeight);
            gl.bindVertexArray(vao);
            allocate(scene, width, height);
            for (var li = 0; li < levels.length; li++) {
                var levelWidth = Math.max(1, Math.round(cssWidth / Math.pow(2, li + 1)));
                var levelHeight = Math.max(1, Math.round(cssHeight / Math.pow(2, li + 1)));
                allocate(levels[li][0], levelWidth, levelHeight);
                allocate(levels[li][1], levelWidth, levelHeight);
            }

            var intro = state.intro;
            var presence = Math.min(1, intro * 2.2);
            var stretch = 1 + 5 * Math.pow(1 - intro, 2) + 2.5 * state.warp;
            var rings = Math.max(1, Math.floor(TAU * Math.max(s.density, 0.05) + 0.5));
            var period = TAU / rings;
            var clock = 9 + state.travel;

            var i;
            var list = [];
            for (i = 0; i < s.colors.length; i++) {
                list.push(toLinear(s.colors[i]));
            }
            palette.fill(0);
            for (i = 0; i < MAX_COLORS; i++) {
                var linear = list[Math.min(i, list.length - 1)];
                palette[i * 3] = linear[0];
                palette[i * 3 + 1] = linear[1];
                palette[i * 3 + 2] = linear[2];
            }

            var toScreenX = function (value) {
                return (2 * value - width) / width;
            };
            var toScreenY = function (value) {
                return (2 * value - height) / width;
            };

            var r = pass(scene, streakProgram);
            gl.uniform2f(r.uView, width, height);
            gl.uniform1f(r.uZoom, s.zoom);
            gl.uniform1f(r.uPhase, clock % (16 * period));
            gl.uniform1f(r.uTwinkleClock, clock % TAU);
            gl.uniform1f(r.uRings, rings);
            gl.uniform3fv(r.uColors, palette);
            gl.uniform1i(r.uColorCount, list.length);
            gl.uniform1i(r.uStreakCount, s.streakCount);
            gl.uniform1i(r.uColumns, s.streakWidth > 1 ? 2 : 1);
            gl.uniform1f(r.uWidth, 5e-4 * s.streakWidth);
            gl.uniform1f(r.uTail, 19 / (s.streakLength * stretch));
            gl.uniform1f(r.uTwinkle, s.twinkle);
            gl.uniform1f(r.uHead, s.headGlow);
            gl.uniform1f(r.uDepth, s.depth);
            gl.uniform4f(
                r.uMouse,
                toScreenX(state.mouseX),
                toScreenY(state.mouseY),
                s.mouseInteraction ? s.mouseStrength * state.touch : 0,
                s.mouseRadius
            );
            gl.uniform3f(
                r.uLens,
                toScreenX(state.lensX),
                toScreenY(state.lensY),
                s.mouseInteraction ? 0.5 * s.lens * state.touch : 0
            );
            gl.uniform1f(r.uLightMode, s.lightMode ? 1 : 0);
            gl.uniform1f(r.uEncode, encode);
            gl.drawArrays(gl.TRIANGLES, 0, 3);

            if (s.bloom > 0) {
                var source = scene;
                for (var bl = 0; bl < levels.length; bl++) {
                    var front = levels[bl][0];
                    var back = levels[bl][1];
                    var d = pass(front, downProgram);
                    bind(0, source.texture);
                    gl.uniform1i(d.uSource, 0);
                    gl.uniform2f(d.uTarget, front.width, front.height);
                    gl.uniform2f(d.uTexel, 1 / source.width, 1 / source.height);
                    gl.uniform1f(d.uLimit, 4 * encode);
                    gl.drawArrays(gl.TRIANGLES, 0, 3);

                    var b = pass(back, blurProgram);
                    bind(0, front.texture);
                    gl.uniform1i(b.uSource, 0);
                    gl.uniform2f(b.uTarget, back.width, back.height);
                    gl.uniform2f(b.uStep, 1 / front.width, 0);
                    gl.drawArrays(gl.TRIANGLES, 0, 3);

                    b = pass(front, blurProgram);
                    bind(0, back.texture);
                    gl.uniform1i(b.uSource, 0);
                    gl.uniform2f(b.uTarget, front.width, front.height);
                    gl.uniform2f(b.uStep, 0, 1 / back.height);
                    gl.drawArrays(gl.TRIANGLES, 0, 3);
                    source = front;
                }
            }

            var c = pass(null, compositeProgram);
            bind(0, scene.texture);
            gl.uniform1i(c.uScene, 0);
            for (var ci = 0; ci < levels.length; ci++) {
                bind(ci + 1, levels[ci][0].texture);
                gl.uniform1i(c['uBloom' + ci], ci + 1);
            }
            gl.uniform2f(c.uView, width, height);
            gl.uniform1f(c.uDecode, 1 / encode);
            gl.uniform1f(c.uGlow, s.glow);
            gl.uniform1f(c.uBloom, s.bloom);
            gl.uniform1f(c.uPresence, presence);
            gl.uniform1f(c.uOpacity, s.opacity);
            gl.uniform1f(c.uFade, s.fade);
            gl.uniform1f(c.uLightMode, s.lightMode ? 1 : 0);
            gl.uniform3fv(c.uTopColor, toLinear(s.backgroundColor));
            gl.uniform1f(c.uTopGlow, s.backgroundGlow);
            gl.drawArrays(gl.TRIANGLES, 0, 3);
            for (var unit = BLOOM_LEVELS; unit >= 0; unit--) {
                bind(unit, null);
            }
        };

        var frame = function (now) {
            raf = 0;
            if (!visible || document.hidden || destroyed) {
                return;
            }
            var s = settings;
            var dt = last ? Math.min(0.05, (now - last) / 1000) : 1 / 60;
            last = now;
            if (!s.intro || reduce) {
                state.intro = 1;
            } else if (state.intro < 1) {
                state.intro = Math.min(1, state.intro + dt / INTRO_SECONDS);
            }

            var holding = s.holdToWarp && pointer.held && pointer.inside && !reduce;
            state.warp += ((holding ? 1 : 0) - state.warp) * (1 - Math.exp(-dt * (holding ? 2.2 : 1.6)));
            var rush = WARP_SPEED * Math.pow(1 - state.intro, 2) + 6 * state.warp;
            var running = !s.paused && !reduce;
            if (running) {
                state.travel += dt * 0.1 * s.speed * (1 + rush);
            }

            var ratio = canvas.width / Math.max(1, container.clientWidth);
            var targetX = pointer.x * ratio;
            var targetY = (container.clientHeight - pointer.y) * ratio;
            var engaged = s.mouseInteraction && pointer.inside && !reduce;
            if (engaged && state.touch < 0.01) {
                state.mouseX = targetX;
                state.mouseY = targetY;
                state.lensX = targetX;
                state.lensY = targetY;
            }
            var follow = s.mouseDampening > 0 ? 1 - Math.exp(-dt / s.mouseDampening) : 1;
            state.mouseX += (targetX - state.mouseX) * follow;
            state.mouseY += (targetY - state.mouseY) * follow;
            var lensFollow = 1 - Math.exp(-dt * 6);
            state.lensX += (targetX - state.lensX) * lensFollow;
            state.lensY += (targetY - state.lensY) * lensFollow;
            state.touch += ((engaged ? 1 : 0) - state.touch) * (1 - Math.exp(-dt * (engaged ? 4 : 2)));

            render();

            var settling = state.intro < 1 || state.warp > 0.001 || Math.abs((engaged ? 1 : 0) - state.touch) > 0.002;
            if ((running && s.speed !== 0) || settling) {
                raf = requestAnimationFrame(frame);
            } else {
                last = 0;
            }
        };

        var wake = function () {
            if (!raf && visible && !document.hidden && !destroyed) {
                raf = requestAnimationFrame(frame);
            }
        };

        var resize = function () {
            var width = Math.max(1, container.clientWidth);
            var height = Math.max(1, container.clientHeight);
            var ratio = Math.min(settings.dpr || window.devicePixelRatio || 1, 2);
            canvas.width = Math.max(1, Math.round(width * ratio));
            canvas.height = Math.max(1, Math.round(height * ratio));
            if (!raf) {
                render();
            }
            wake();
        };

        var locate = function (event) {
            var rect = container.getBoundingClientRect();
            var x = event.clientX - rect.left;
            var y = event.clientY - rect.top;
            return { x: x, y: y, inside: x >= 0 && y >= 0 && x <= rect.width && y <= rect.height };
        };

        var onPointerMove = function (event) {
            var point = locate(event);
            pointer.x = point.x;
            pointer.y = point.y;
            pointer.inside = point.inside;
            wake();
        };

        var onPointerDown = function (event) {
            var point = locate(event);
            if (!point.inside) {
                return;
            }
            pointer.x = point.x;
            pointer.y = point.y;
            pointer.inside = true;
            pointer.held = true;
            wake();
        };

        var onPointerUp = function () {
            pointer.held = false;
            wake();
        };

        var onPointerLeave = function () {
            pointer.inside = false;
            pointer.held = false;
            wake();
        };

        var onVisibility = function () {
            last = 0;
            wake();
        };

        var onWindowResize = function () {
            resize();
        };

        window.addEventListener('pointermove', onPointerMove, { passive: true });
        window.addEventListener('pointerdown', onPointerDown, { passive: true });
        window.addEventListener('pointerup', onPointerUp, { passive: true });
        window.addEventListener('pointercancel', onPointerUp, { passive: true });
        window.addEventListener('resize', onWindowResize);
        document.documentElement.addEventListener('pointerleave', onPointerLeave);
        document.addEventListener('visibilitychange', onVisibility);
        resize();

        var lastDpr = settings.dpr;
        window.setTimeout(function () {
            if (!destroyed) {
                resize();
            }
        }, 0);

        return {
            canvas: canvas,
            /* 运行时改参数：合并后立刻重绘（dpr 变了会重新分配尺寸） */
            set: function (patch) {
                if (destroyed) {
                    return;
                }
                settings = normalize(merge(settings, patch));
                if (settings.dpr !== lastDpr) {
                    lastDpr = settings.dpr;
                    resize();
                    return;
                }
                if (!raf) {
                    render();
                }
                wake();
            },
            destroy: function () {
                if (destroyed) {
                    return;
                }
                destroyed = true;
                cancelAnimationFrame(raf);
                raf = 0;
                canvas.removeEventListener('webglcontextlost', onContextLost);
                canvas.removeEventListener('webglcontextrestored', onContextRestored);
                window.removeEventListener('pointermove', onPointerMove);
                window.removeEventListener('pointerdown', onPointerDown);
                window.removeEventListener('pointerup', onPointerUp);
                window.removeEventListener('pointercancel', onPointerUp);
                window.removeEventListener('resize', onWindowResize);
                document.documentElement.removeEventListener('pointerleave', onPointerLeave);
                document.removeEventListener('visibilitychange', onVisibility);
                var targets = [scene];
                for (var ti = 0; ti < levels.length; ti++) {
                    targets.push(levels[ti][0], levels[ti][1]);
                }
                for (var zi = 0; zi < targets.length; zi++) {
                    if (targets[zi].texture) {
                        gl.deleteTexture(targets[zi].texture);
                    }
                    gl.deleteFramebuffer(targets[zi].framebuffer);
                }
                var programs = [streakProgram, downProgram, blurProgram, compositeProgram];
                for (var pi = 0; pi < programs.length; pi++) {
                    gl.deleteProgram(programs[pi].program);
                }
                gl.deleteBuffer(buffer);
                gl.deleteVertexArray(vao);
                if (!gl.isContextLost() && gl.getExtension('WEBGL_lose_context')) {
                    gl.getExtension('WEBGL_lose_context').loseContext();
                }
                if (canvas.parentNode) {
                    canvas.parentNode.removeChild(canvas);
                }
            }
        };
    }

    root.EcodLightfall = { create: create };
})(window);
