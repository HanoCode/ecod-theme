/**
 * Ecod Theme · 前端构建脚本
 *
 * 职责（esbuild，单依赖，无其它工具链）：
 *   1. 逐文件 minify CSS / JS（1:1 映射，不合并、不改调用方式）；
 *   2. 产物落 assets/dist/，文件名带内容哈希（<name>-<hash8>.min.<ext>）；
 *   3. 生成 assets/dist/manifest.php（源路径 → dist 产物映射），
 *      PHP 侧 core/asset.php 读它优先发哈希产物，缺失时回退源文件；
 *   4. 清理上次构建的陈旧产物，避免 dist 目录无限膨胀。
 *
 * 设计约定：dist 产物连同 manifest 一起提交进 git —— 主题分发给用户的是
 * 源码目录，不要求使用者装 Node；本管线是开发期行为。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */

import { build } from 'esbuild';
import { createHash } from 'node:crypto';
import { mkdir, readFile, readdir, rm, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { brotliCompressSync, constants as zlibConstants, gzipSync } from 'node:zlib';

const BUILD_DIR = dirname(fileURLToPath(import.meta.url));
const THEME_DIR = resolve(BUILD_DIR, '..');
const OUT_ROOT = join(THEME_DIR, 'assets', 'dist');
const MANIFEST = join(OUT_ROOT, 'manifest.php');

/** 版权横幅：加在每个 minify 产物开头（esbuild 不会保留源文件头注释） */
const BANNER =
    '/*! Ecod Theme (c) 2026 Ecod. All Rights Reserved. · https://ecod.cc */\n';

/**
 * 参与构建的源文件清单（相对主题根目录）。
 * vendor 第三方库（prism）不做二次加工；manifest 键 = 调用方传入的路径，
 * 前台走 assets/…，后台编辑器走 admin/…，与 PHP 调用点一字不差。
 */
const ENTRIES = [
    // 前台（ecod.post.js 是内容页增强包，见 JS 内文件头说明）
    'assets/css/ecod.css',
    'assets/js/ecod.global.js',
    'assets/js/ecod.post.js',
    // 后台设置页 / 模块管理台 / 选图器
    'assets/admin/config.css',
    'assets/admin/config.js',
    'assets/admin/media.css',
    'assets/admin/media.js',
    'assets/admin/nav.css',
    'assets/admin/nav.js',
    'assets/admin/topics.css',
    'assets/admin/topics.js',
    'assets/admin/tools.js',
    'assets/admin/widget.css',
    'assets/admin/widget.js',
    // 后台编辑器（admin/ 目录，随 admin/editor.php 注入）
    'admin/editor.css',
    'admin/editor.js',
    'admin/fields.js',
    'admin/frame.css',
];

/**
 * 关键 CSS 抽取的节编号（源码分节标记 `/* ============ N. 名称 ============ `）。
 * 覆盖首屏所需的全部区块：令牌/基础/布局/导航/暗色 + 首页流与模块、卡片、
 * 分页、侧栏、页脚、顶部进度条、底部 dock、公告横幅、搜索面板、懒加载、
 * 站点级设置变体、排序浮层、首屏 Hero、专题/分类页头——
 * 首帧即完整成形，只有文章正文与特色页等深层内容随完整样式表（异步预加载）补齐。
 * 语法：'N' = 该编号全部节；'N@k' = 该编号第 k 次出现（0 起，源码有重复编号时用）。
 */
const CRITICAL_SECTIONS = [
    '0', '1', '2', '3',              // 令牌 / 基础 / 布局 / 导航抽屉
    '4', '4.5', '5', '6', '7',       // 首页 / 首页模块 / 卡片 / 分页 / 侧栏
    '10', '11', '11.4b', '11.4c',    // 页脚 / 进度回顶 / 移动端 dock / 公告横幅
    '12', '13', '16', '18',          // 暗色 / 响应式 / 收藏点赞 / 移动端加固
    '21', '22', '23@0',              // 搜索面板 / 懒加载+reveal / 站点级设置变体
    '24@1', '25@1', '27',            // 列表排序浮层 / 首屏 Hero / 专题与分类页头
];

/**
 * CSS 括号平衡守卫：剥掉注释与字符串字面量后逐字符扫描花括号，
 * 深度不允许变负、结尾必须归零。防「少写一个 }」把 @media 后半段
 * 泄漏到顶层（移动端规则在桌面端生效）这类静默排版事故。
 */
function assertBraceBalance(css, label) {
    const stripped = css.replace(/\/\*[\s\S]*?\*\//g, '').replace(/"(?:[^"\\]|\\.)*"|'(?:[^'\\]|\\.)*'/g, '');
    let depth = 0;
    for (const ch of stripped) {
        if (ch === '{') depth++;
        else if (ch === '}') {
            depth--;
            if (depth < 0) throw new Error(`${label}：花括号深度变负（多余的 }），媒体查询可能提前闭合`);
        }
    }
    if (depth !== 0) throw new Error(`${label}：花括号不配对（差 ${depth} 个 }）`);
}

/** 按分节标记从 CSS 源码中抽取指定编号的节，保持原顺序拼接。
    'N@k' 语法用于区分源码中重复的编号（如两个 23. / 两个 24.）。 */
function extractCssSections(source, numbers) {
    const lines = source.split('\n');
    const marks = [];
    for (let i = 0; i < lines.length; i++) {
        const m = lines[i].match(/^\/\* =+ (\d+(?:\.\d+)?[a-z]?)\.? /);
        if (m) marks.push({ i, num: m[1] });
    }
    /* 展开编号：'N' → 该编号所有出现；'N@k' → 第 k 次出现的下标 */
    const wantedIdx = new Set();
    const seen = {};
    for (const mark of marks) {
        seen[mark.num] = (seen[mark.num] || 0) + 1;
    }
    for (const entry of numbers) {
        const m = entry.match(/^(\d+(?:\.\d+)?[a-z]?)(?:@(\d+))?$/);
        if (!m) throw new Error(`CRITICAL_SECTIONS 条目格式错误：${entry}`);
        const num = m[1];
        const occ = m[2] === undefined ? null : parseInt(m[2], 10);
        const idxs = [];
        let n = 0;
        for (let k = 0; k < marks.length; k++) {
            if (marks[k].num === num) {
                if (occ === null || occ === n) idxs.push(k);
                n++;
            }
        }
        if (!idxs.length) {
            throw new Error(`关键 CSS 抽取：找不到分节 ${entry}`);
        }
        idxs.forEach((k) => wantedIdx.add(k));
    }
    let out = [];
    for (const k of Array.from(wantedIdx).sort((a, b) => a - b)) {
        const end = k + 1 < marks.length ? marks[k + 1].i : lines.length;
        out = out.concat(lines.slice(marks[k].i, end));
    }
    if (!out.length) {
        throw new Error('关键 CSS 抽取为空：分节标记与 CRITICAL_SECTIONS 对不上');
    }
    return out.join('\n');
}

/**
 * 首帧防闪守卫：把「全屏浮层」的隐藏态规则强制并入关键 CSS（构建期自动生成）。
 *
 * 背景：关键 CSS 按分节抽取（CRITICAL_SECTIONS）。任何一个全屏浮层
 * （position:fixed; inset:0，靠 opacity:0 / visibility:hidden 默认隐藏）如果写在
 * 未纳入抽取的分节里，首帧就完全没有它的规则，浏览器按默认样式（opacity:1、
 * visibility:visible）把它整块画出来，等完整样式表异步到位才过渡到隐藏 ——
 * 表现为「整页闪一下」。首页的搜索面板、文章页的图片灯箱都踩过同一个坑。
 *
 * 这里扫描整份源码，挑出这类浮层规则；关键 CSS 里还没有同名选择器就补进去。
 * 只补「隐藏态」定义，不改变浮层自身分节里的视觉样式。
 */
function overlayGuardCss(source, criticalText) {
    const css = source.replace(/\/\*[\s\S]*?\*\//g, '');
    const guards = [];
    let sel = '';
    let body = '';
    let depth = 0;

    for (const ch of css) {
        if (depth === 0) {
            if (ch === '{') {
                depth = 1;
                body = '';
            } else {
                sel += ch;
            }
            continue;
        }
        if (ch === '{') {
            depth += 1;
            body += ch;
            continue;
        }
        if (ch === '}') {
            depth -= 1;
            if (depth > 0) {
                body += ch;
                continue;
            }
            const s = sel.replace(/\s+/g, ' ').trim();
            const full = s && !s.startsWith('@')
                && /position:\s*fixed/.test(body)
                && /inset:\s*0(?=\s|;|$)/.test(body)
                && (/(?:^|[;{\s])opacity:\s*0(?=\s|;|$)/.test(body) || /visibility:\s*hidden/.test(body));
            if (full && !criticalText.includes(s) && !guards.some((g) => g.startsWith(s + ' '))) {
                guards.push(`${s} {${body}}`);
            }
            sel = '';
            body = '';
            continue;
        }
        body += ch;
    }

    if (!guards.length) return '';
    return [
        '/* ---- 首帧防闪守卫：全屏浮层默认隐藏态（build 自动生成，勿手改） ---- */',
        ...guards,
    ].join('\n');
}

/**
 * 内容哈希（sha1 前 8 位）：同名不同内容必然不同 URL，
 * 服务端因此可以放心发一年期 immutable 长缓存。
 */
function hashOf(content) {
    return createHash('sha1').update(content).digest('hex').slice(0, 8);
}

/** 递归列出目录下所有文件（用于清理陈旧产物） */
async function walk(dir) {
    let out = [];
    let items;
    try {
        items = await readdir(dir, { withFileTypes: true });
    } catch {
        return out;
    }
    for (const it of items) {
        const p = join(dir, it.name);
        if (it.isDirectory()) {
            out = out.concat(await walk(p));
        } else {
            out.push(p);
        }
    }
    return out;
}

async function main() {
    await mkdir(OUT_ROOT, { recursive: true });

    const manifest = {};
    const report = [];

    for (const rel of ENTRIES) {
        const srcPath = join(THEME_DIR, rel);
        const source = await readFile(srcPath, 'utf8');
        const ext = rel.endsWith('.css') ? 'css' : 'js';

        /* transform：单文件 minify，不做 bundle / 转译——
           源码本身就是最终语法（ES5 风格 JS、现代 CSS），只压缩不改写 */
        const result = await build({
            stdin: { contents: source, loader: ext, resolveDir: THEME_DIR },
            minify: true,
            bundle: false,
            write: false,
            logLevel: 'silent',
        });
        const minified = BANNER + result.outputFiles[0].text;

        const base = rel.replace(/\.(css|js)$/, '').replace(/^assets\//, '');
        const hash = hashOf(minified);
        const outRel = `assets/dist/${base}.${hash}.min.${ext}`;

        await mkdir(dirname(join(THEME_DIR, outRel)), { recursive: true });
        await writeFile(join(THEME_DIR, outRel), minified);
        /* 预压缩副产物：服务器配 gzip_static / brotli_static 后直接吐静态文件，
           免去在线压缩的 CPU；内容哈希文件名保证副产物永不失配 */
        await writeFile(join(THEME_DIR, outRel) + '.gz', gzipSync(minified, { level: 9 }));
        await writeFile(join(THEME_DIR, outRel) + '.br', brotliCompressSync(minified, {
            params: { [zlibConstants.BROTLI_PARAM_QUALITY]: 11 },
        }));

        manifest[rel] = outRel;
        report.push({
            file: rel,
            src: Buffer.byteLength(source),
            out: Buffer.byteLength(minified),
        });

        /* 关键 CSS：从主样式表抽出首屏必需的节（令牌/基础/布局/导航/暗色），
           单独 minify 成 ecod.critical.<hash>.min.css，由 include.php 内联进
           <head>；完整样式表转异步预加载，首屏不再被 300KB CSS 阻塞。
           节编号对应源码的 /* ============ N. 名称 ============ 分节标记，
           想调整首屏覆盖面改这一个数组即可。 */
        if (rel === 'assets/css/ecod.css') {
            assertBraceBalance(source, 'ecod.css 源码');
            const sectionSource = extractCssSections(source, CRITICAL_SECTIONS);
            assertBraceBalance(sectionSource, '关键 CSS 抽取');
            const criticalSource = sectionSource + '\n' + overlayGuardCss(source, sectionSource);
            const criticalMin = (
                await build({
                    stdin: { contents: criticalSource, loader: 'css', resolveDir: THEME_DIR },
                    minify: true,
                    bundle: false,
                    write: false,
                    logLevel: 'silent',
                })
            ).outputFiles[0].text;
            if (criticalMin.includes('</style')) {
                throw new Error('critical CSS 含 </style，内联会截断 head');
            }
            const criticalBase = 'css/ecod.critical';
            const criticalHash = hashOf(criticalMin);
            const criticalRel = `assets/dist/${criticalBase}.${criticalHash}.min.css`;
            await mkdir(dirname(join(THEME_DIR, criticalRel)), { recursive: true });
            await writeFile(join(THEME_DIR, criticalRel), criticalMin);
            await writeFile(join(THEME_DIR, criticalRel) + '.gz', gzipSync(criticalMin, { level: 9 }));
            await writeFile(join(THEME_DIR, criticalRel) + '.br', brotliCompressSync(criticalMin, {
                params: { [zlibConstants.BROTLI_PARAM_QUALITY]: 11 },
            }));
            /* opcache 变体：nowdoc 无插值无转义，include.php require 它之后
               字符串常驻 opcache 共享内存，省掉每请求一次磁盘读。
               nowdoc 闭合标记独占一行，CSS 内容不可能出现该标记 */
            await writeFile(
                join(THEME_DIR, criticalRel) + '.php',
                "<?php return <<<'ECODCSS'\n" + criticalMin + '\nECODCSS;\n'
            );
            /* 伪源路径键：ecod_dist_resolve('assets/css/ecod.critical.css') 可查到 */
            manifest['assets/css/ecod.critical.css'] = criticalRel;
            report.push({
                file: 'css/ecod.critical.css (派生)',
                src: Buffer.byteLength(criticalSource),
                out: Buffer.byteLength(criticalMin),
            });
        }
    }

    /* manifest 用 PHP 数组而非 JSON：无需解码步骤，opcache 直接加载 */
    const php = [
        '<?php',
        '/**',
        ' * Ecod Theme · 构建产物映射（自动生成，请勿手工修改）',
        ' *',
        ' * 由 build/build.mjs 维护：源路径 => dist 产物路径（文件名含内容哈希）。',
        ' * PHP 侧 core/asset.php 读取本文件优先发哈希产物；删掉 dist 目录即回退源文件。',
        ' *',
        ' * @package Ecod Theme',
        ' * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.',
        ' * @link    https://ecod.cc',
        ' */',
        '',
        'return ' + varExport(manifest) + ';',
        '',
    ].join('\n');
    await writeFile(MANIFEST, php);

    /* 清理不在本次 manifest 里的旧产物（含预压缩副产物与哈希变更的孤儿文件） */
    const keep = new Set([
        MANIFEST,
        ...Object.values(manifest).flatMap((f) => [f, f + '.gz', f + '.br', f + '.php'].map((p) => join(THEME_DIR, p))),
    ]);
    for (const f of await walk(OUT_ROOT)) {
        if (!keep.has(f)) {
            await rm(f);
        }
    }

    /* 体积报告 */
    let srcTotal = 0;
    let outTotal = 0;
    console.log('file                          src        min        Δ');
    for (const r of report) {
        srcTotal += r.src;
        outTotal += r.out;
        const pct = ((1 - r.out / r.src) * 100).toFixed(1);
        console.log(
            `${r.file.padEnd(30)}${fmt(r.src).padEnd(11)}${fmt(r.out).padEnd(11)}-${pct}%`
        );
    }
    console.log('—'.repeat(60));
    console.log(
        `TOTAL                         ${fmt(srcTotal).padEnd(11)}${fmt(outTotal).padEnd(11)}-${((1 - outTotal / srcTotal) * 100).toFixed(1)}%`
    );
}

function fmt(n) {
    return n >= 1024 * 1024
        ? (n / 1024 / 1024).toFixed(2) + ' MB'
        : (n / 1024).toFixed(1) + ' KB';
}

/** 极简 var_export：只处理本场景的 string => string 平铺映射 */
function varExport(map) {
    const inner = Object.entries(map)
        .map(([k, v]) => `    '${k}' => '${v}',`)
        .join('\n');
    return `[\n${inner}\n]`;
}

main().catch((err) => {
    console.error(err);
    process.exit(1);
});
