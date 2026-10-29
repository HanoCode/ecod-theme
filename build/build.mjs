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
 * @license MIT
 */

import { build } from 'esbuild';
import { createHash } from 'node:crypto';
import { mkdir, readFile, readdir, rm, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const BUILD_DIR = dirname(fileURLToPath(import.meta.url));
const THEME_DIR = resolve(BUILD_DIR, '..');
const OUT_ROOT = join(THEME_DIR, 'assets', 'dist');
const MANIFEST = join(OUT_ROOT, 'manifest.php');

/** 版权横幅：加在每个 minify 产物开头（esbuild 不会保留源文件头注释） */
const BANNER =
    '/*! Ecod Theme (c) 2026 Ecod · MIT License · https://ecod.cc */\n';

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

        manifest[rel] = outRel;
        report.push({
            file: rel,
            src: Buffer.byteLength(source),
            out: Buffer.byteLength(minified),
        });
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
        ' * @license MIT',
        ' */',
        '',
        'return ' + varExport(manifest) + ';',
        '',
    ].join('\n');
    await writeFile(MANIFEST, php);

    /* 清理不在本次 manifest 里的旧产物（哈希变了会留下孤儿文件） */
    const keep = new Set([MANIFEST, ...Object.values(manifest).map((f) => join(THEME_DIR, f))]);
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
