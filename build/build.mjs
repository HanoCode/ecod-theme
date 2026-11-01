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
import { mkdir, readFile, readdir, rm, stat, writeFile } from 'node:fs/promises';
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
    // 表情球（Grok Ball，MIT，见 vendor/grok-ball.LICENSE）：
    // 引擎 + 封装都由 ecod.global.js 的 ensureBall() 按需注入，不走页脚标签
    'assets/js/vendor/grok-ball.js',
    'assets/js/ecod.ball.js',
    // 流光背景（Lightfall，MIT，见 vendor/lightfall.js 文件头）：
    // 后台站点背景选「流光」时由页脚下发，引擎 + 封装按此顺序加载
    'assets/js/vendor/lightfall.js',
    'assets/js/ecod.lightfall.js',
    // 后台设置页 / 模块管理台 / 选图器
    'assets/admin/tokens.css',
    'assets/admin/config.css',
    'assets/admin/config.js',
    'assets/admin/license.css',
    'assets/admin/license.js',
    'assets/admin/media.css',
    'assets/admin/media.js',
    'assets/admin/nav.css',
    'assets/admin/nav.js',
    'assets/admin/topics.css',
    'assets/admin/topics.js',
    'assets/admin/tools.js',
    'assets/admin/widget.css',
    'assets/admin/widget.js',
    // 页面构建器（builder.php 画布编辑器，见 docs/builder-design.html）
    'assets/admin/builder.css',
    'assets/admin/builder.js',
    // 后台编辑器（admin/ 目录，随 admin/editor.php 注入）
    'admin/editor.css',
    'admin/editor.js',
    'admin/fields.js',
    'admin/frame.css',
];

/**
 * 页面族 CSS 分包（S2）：按「节的宿主模板族」把整份样式表切成若干个
 * 可独立下发的 CSS 文件，页面只加载 CORE + 自己所属族。
 *
 * 归属依据（S1 归位之后节边界 ≈ 依赖边界）：
 *   - core    全站常驻：框架 / 导航 / 布局 / 侧栏 / 卡片 / 暗色 / 响应式 /
 *             搜索 / 站点设置 / toast / 页脚，以及侧栏可选挂件（抽奖、商城、
 *             打卡、自助广告、专题墙、作者卡）与 JustNews 杂项；
 *   - home    index.php 首页流（模块 / Hero / 排序入口）；
 *   - list    归档 / 分类 / 标签 / 搜索列表（首页也带，分页翻页用）；
 *   - post    post.php 文章正文相关（评论、正文短代码、相关阅读、分享图、
 *             付费组件——付费按钮出现在文章页，cashier 页由 user 族补齐）；
 *   - postext 正文含扩展短代码时按需追加（网盘卡 / 媒体 / 投票等），
 *             由 PHP 检测正文决定是否输出；
 *   - user    page-user / page-shop / page-vip / page-cashier / page-ai 个人中心族；
 *   - page    独立页（特色页、留言板、归档时间轴等）兜底族。
 *
 * 断言：所有族的节并集必须恰好覆盖源码全部分节 —— 防止改节号后漏掉
 * 某节导致整站某类页面静默丢样式。
 */
const PAGE_SETS = {
    core: [
        '0', '1', '2', '3', '3.5',                   // 令牌/基础/布局/导航 + 通用组件层
        '4', '4.5', '5', '6', '7', '10', '11',       // 文章流/首页模块(可拖拽块皮肤)/卡片/分页/侧栏/页脚/工具
        '11.4b', '11.4c', '12', '13', '16', '18',    // dock/公告/暗色/响应式/互动/加固
        '21', '22', '23@0', '26', '27',              // 搜索/懒加载/站点设置/页脚年龄/专题
        '28', '32', '33', '35', '36', '37',          // JustNews 杂项/抽奖页/广告/公告弹窗/挂件/播报
        '38', '39', '40',                            // 商城/toast/打卡
        '42', '43', '44', '45', '46', '47',          // 页面构建器板块壳 / 表情球（站宠·404·搜索·AI·抽奖·评论）/ AI 快问面板 / 评论 AI 摘要 / AI 搜索 / 404 向导与看广告领点数
        '51',                                        // 内置浏览器引导（微信 / QQ 等 WebView 提示改用浏览器，全站浮层）
        '52',                                        // 笔记精选模块（可摆在首页与任意页面侧栏，故进共享族）
    ],
    home: ['24@1', '25@1'],
    list: ['24@1', '50'],
    post: ['8', '9', '9.5', '11.5', '11.6', '14@1', '17', '19.5', '20', '23.5', '25@0', '34', '41', '48', '49', '49.5'],
    postext: ['23@1', '31'],
    user: ['8.5', '14@0', '15', '23.5', '24@0', '29', '30', '41.5'],
    page: ['8.5', '9', '14@0', '15', '19'],
};

/**
 * 分族关键 CSS（S4）：每个页面族一份内联关键样式 = 全站首帧骨架 +
 * 该族首屏内容块。postext 页面复用 post 份（扩展短代码不进首帧）。
 *
 * 取舍原则（S2/S3 之后 CSS 已是「小体积 + 异步非阻塞」，内联只该为首帧防塌服务）：
 *   留下——首帧可见且会撑骨架的：令牌 / 基础 / 布局 / 导航 / 侧栏 / 暗色 / 响应式 /
 *          移动端加固 / 移动 dock+公告 / 顶部进度条 / 懒加载占位（防图跳）/
 *          搜索面板（默认 display:none，缺了会在左上角裸奔原生 input）；
 *   让出——首帧不可见或只是加速的：通用组件层（按钮·徽标·灯箱·登录弹层，
 *          27.8KB，首帧多为隐藏态）、页脚装饰、toast。
 *   ※ 侧栏（§7）实测不能让：晚到 300ms 内是无样式裸文本列表，视觉上像坏了；
 *     其列宽虽由 §2 布局预留不引发全页重排，但裸文本观感不可接受。
 * 内联体积实测：home 143.8KB→约 118KB、post 156.5KB→约 118KB（gzip 约 -25%）。
 */
const CRITICAL_BASE = [
    '0', '1', '2', '3', '7',                     // 令牌 / 基础 / 布局 / 导航抽屉 / 侧栏
    '11', '11.4b', '11.4c',                      // 进度回顶 / 移动端 dock / 公告横幅
    '12', '13', '18',                            // 暗色 / 响应式 / 移动端加固
    '21', '22',                                  // 搜索面板 / 懒加载占位（防首屏图片高度跳变）
];
const CRITICAL_FAMS = {
    home: [...CRITICAL_BASE, '4', '4.5', '5', '6', '25@1'],
    list: [...CRITICAL_BASE, '4', '5', '6', '24@1', '27'],
    post: [...CRITICAL_BASE, '8', '25@0', '9', '49'],
    /* §49 是图文笔记（note）文章的首屏骨架：头卡/竖版图集/作者行/互动条，
       缺了首帧图集按原图尺寸铺满、作者行竖排堆叠，属于「防塌」范畴；
       §49.5 是全屏查看器浮层（默认不存在于 DOM，JS 点开才注入），让出去。 */
    user: [...CRITICAL_BASE, '8.5', '14@0', '15', '24@0'],
    page: [...CRITICAL_BASE, '8.5', '15'],
};

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
        if (!m) throw new Error(`节条目格式错误：${entry}`);
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
        throw new Error('关键 CSS 抽取为空：分节标记与抽取清单对不上');
    }
    return out.join('\n');
}

/** 全部分节编号（含 @k 去重形式），供 PAGE_SETS 覆盖断言使用。
    与 extractCssSections 的 'N@k' 语法一致：重复编号的键为 N@0 / N@1 / … */
function allSectionKeys(source) {
    const nums = [...source.matchAll(/^\/\* =+ (\d+(?:\.\d+)?[a-z]?)\.? /gm)].map((m) => m[1]);
    const total = {};
    for (const n of nums) total[n] = (total[n] || 0) + 1;
    const seen = {};
    return nums.map((n) => {
        seen[n] = (seen[n] || 0) + 1;
        return total[n] > 1 ? n + '@' + (seen[n] - 1) : n;
    });
}

/** PAGE_SETS 覆盖断言：族并集必须恰好等于全部分节（不重不漏） */
function assertPageSetsCoverAll(source) {
    const all = allSectionKeys(source);
    const used = new Set();
    const dup = [];
    for (const [fam, secs] of Object.entries(PAGE_SETS)) {
        for (const s of secs) {
            if (used.has(s)) dup.push(`${fam}:${s}`);
            used.add(s);
        }
    }
    const missing = all.filter((k) => !used.has(k));
    const unknown = [...used].filter((s) => !all.includes(s));
    if (missing.length) throw new Error(`PAGE_SETS 漏节：${missing.join(', ')} —— 这些节的样式不会随任何页面族下发`);
    if (unknown.length) throw new Error(`PAGE_SETS 引用了不存在的节：${unknown.join(', ')}`);
    /* 重复引用是合法的（如 §24@1 同属 home/list、§9 同属 post/page） */
}

/**
 * 首帧防闪守卫：把「全屏浮层」的隐藏态规则强制并入关键 CSS（构建期自动生成）。
 *
 * 背景：关键 CSS 按分节抽取（CRITICAL_BASE / CRITICAL_FAMS）。任何一个全屏浮层
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

        /* 关键 CSS（S4 分族）：每个页面族一份「首帧骨架 + 族首屏区块」，
           由 include.php 按当前页族内联进 <head>；其余样式随族 CSS 异步预加载。
           postext 页面复用 post 份（扩展短代码不进首帧）。
           节编号对应源码的 /* ============ N. 名称 ============ 分节标记。 */
        if (rel === 'assets/css/ecod.css') {
            assertBraceBalance(source, 'ecod.css 源码');
            assertPageSetsCoverAll(source);

            for (const [fam, famSections] of Object.entries(CRITICAL_FAMS)) {
                const sectionSource = extractCssSections(source, famSections);
                assertBraceBalance(sectionSource, `关键 CSS（${fam}）抽取`);
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
                const criticalBase = `css/ecod.critical.${fam}`;
                const criticalRel = `assets/dist/${criticalBase}.${hashOf(criticalMin)}.min.css`;
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
                /* 伪源路径键：ecod_dist_resolve('assets/css/ecod.critical.<fam>.css') 可查到 */
                manifest[`assets/css/ecod.critical.${fam}.css`] = criticalRel;
                report.push({
                    file: `css/ecod.critical.${fam}.css (派生)`,
                    src: Buffer.byteLength(criticalSource),
                    out: Buffer.byteLength(criticalMin),
                });
            }

            /* 页面族分包（S2）：每族一个可独立下发的 CSS 文件。
               页面只加载 core + 所属族（见 public/include.php ecod_css_family()）；
               PJAX 跨族换页时由 ecod.global.js 的 ensurePageCss() 注入缺失族。 */
            for (const [fam, secs] of Object.entries(PAGE_SETS)) {
                const famSource = extractCssSections(source, secs);
                assertBraceBalance(famSource, `页面族 ${fam} 抽取`);
                const famMin = (
                    await build({
                        stdin: { contents: famSource, loader: 'css', resolveDir: THEME_DIR },
                        minify: true,
                        bundle: false,
                        write: false,
                        logLevel: 'silent',
                    })
                ).outputFiles[0].text;
                const famRel = `assets/dist/css/ecod.fam.${fam}.${hashOf(famMin)}.min.css`;
                await mkdir(dirname(join(THEME_DIR, famRel)), { recursive: true });
                await writeFile(join(THEME_DIR, famRel), famMin);
                await writeFile(join(THEME_DIR, famRel) + '.gz', gzipSync(famMin, { level: 9 }));
                await writeFile(join(THEME_DIR, famRel) + '.br', brotliCompressSync(famMin, {
                    params: { [zlibConstants.BROTLI_PARAM_QUALITY]: 11 },
                }));
                manifest[`assets/css/ecod.fam.${fam}.css`] = famRel;
                report.push({
                    file: `css/ecod.fam.${fam}.css (派生)`,
                    src: Buffer.byteLength(famSource),
                    out: Buffer.byteLength(famMin),
                });
            }
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

    /* 清理不在本次 manifest 里的旧产物（含预压缩副产物与哈希变更的孤儿文件）。
       宽限期保留：hash 一换旧 URL 即死——rebuild 前已打开/缓存的 HTML 仍引用旧
       hash，当场删除会让那些页面 fam CSS/JS 404（CSS 版=整页裸奔、面板叠罗汉；
       JS 版=PJAX 瘫痪）。孤儿按 mtime 保留 48h，盖过缓存 HTML 的存活窗口，
       期间 dist 会暂存中间代产物，属预期膨胀。 */
    const keep = new Set([
        MANIFEST,
        ...Object.values(manifest).flatMap((f) => [f, f + '.gz', f + '.br', f + '.php'].map((p) => join(THEME_DIR, p))),
    ]);
    const ORPHAN_GRACE_MS = 48 * 3600 * 1000;
    const nowMs = Date.now();
    for (const f of await walk(OUT_ROOT)) {
        if (keep.has(f)) continue;
        const mtimeMs = (await stat(f)).mtimeMs;
        if (nowMs - mtimeMs < ORPHAN_GRACE_MS) continue;
        await rm(f);
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
