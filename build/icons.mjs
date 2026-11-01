/**
 * Ecod Theme · 图标库生成器
 *
 * 数据源：build/icons.map.json（键 = 主题图标名，值 = css.gg 源名或自绘路径）。
 * 职责：
 *   1. 按映射表拉取 css.gg 官方 SVG（jsDelivr，本地缓存于 temp/icons-cache/）；
 *   2. 提取 <svg> 内部图形标记并压缩空白；
 *   3. 生成 components/icons-data.php（键名 => 内部图形，24×24 填充风格）。
 *
 * 重新生成：node build/icons.mjs
 * 图形版权：css.gg —— MIT License (c) Astrit Schmitt et al.
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */

import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const BUILD_DIR = dirname2(fileURLToPath(import.meta.url));
const THEME_DIR = resolve(BUILD_DIR, '..');
const MAP_FILE = join(BUILD_DIR, 'icons.map.json');
const OUT_FILE = join(THEME_DIR, 'components', 'icons-data.php');
const CACHE_DIR = join(THEME_DIR, 'temp', 'icons-cache');
const CSSGG_VERSION = '2.1.1';
const CSSGG_SVG_BASE = `https://cdn.jsdelivr.net/npm/css.gg@${CSSGG_VERSION}/icons/svg/`;

function dirname2(p) {
    const i = p.lastIndexOf('/');
    return i < 0 ? '.' : p.slice(0, i);
}

/** 拉取并缓存单个 css.gg SVG（命中缓存不发请求） */
async function fetchSvg(name) {
    const cacheFile = join(CACHE_DIR, `${name}.svg`);
    if (existsSync(cacheFile)) {
        return readFile(cacheFile, 'utf8');
    }
    const res = await fetch(CSSGG_SVG_BASE + encodeURIComponent(name) + '.svg');
    if (!res.ok) {
        throw new Error(`css.gg 拉取失败 ${name}: HTTP ${res.status}`);
    }
    const svg = await res.text();
    await mkdir(CACHE_DIR, { recursive: true });
    await writeFile(cacheFile, svg);
    return svg;
}

/** 提取 <svg> 内部标记并压缩标签间空白 */
function extractInner(svg, name) {
    const openEnd = svg.indexOf('>');
    const closeStart = svg.lastIndexOf('</svg>');
    if (openEnd < 0 || closeStart < 0) {
        throw new Error(`css.gg 解析失败 ${name}: 非法 SVG`);
    }
    return svg.slice(openEnd + 1, closeStart)
        .replace(/ fill="currentColor"/g, '')       // 包装层统一注入，去掉冗余
        .replace(/>\s+</g, '><')
        .replace(/\s+/g, ' ')
        .trim();
}

function phpQuote(s) {
    return "'" + s.replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
}

const map = JSON.parse(await readFile(MAP_FILE, 'utf8'));

const entries = [];
for (const [name, src] of Object.entries(map)) {
    if (name === '_readme') {
        continue;
    }
    if (typeof src === 'string') {
        const svg = await fetchSvg(src);
        entries.push([name, extractInner(svg, src)]);
    } else if (src && typeof src.path === 'string') {
        entries.push([name, src.path]);
    } else {
        throw new Error(`映射表非法条目: ${name}`);
    }
}

const lines = entries.map(([name, inner]) => `    ${phpQuote(name)} => ${phpQuote(inner)},`);

const php = `<?php
/**
 * 图标形状数据（自动生成，勿手改）
 *
 * 由 build/icons.mjs 依据 build/icons.map.json 生成：
 * 键 = 主题图标名（ecod_icon() 的 $name），值 = 24×24 视图下的内部图形标记，
 * 均为填充风格（fill=currentColor 由 ecod_icon() 包装层统一注入）。
 * css.gg 部分图形版权归 css.gg 项目（MIT License）。
 *
 * 重新生成：node build/icons.mjs
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

return [
${lines.join('\n')}
];
`;

await writeFile(OUT_FILE, php);
console.log(`icons-data.php 已生成：${entries.length} 个图标`);
const missing = entries.filter(([, inner]) => !inner);
if (missing.length) {
    console.error('空图形条目：', missing.map(([n]) => n).join(', '));
}

/* ---------- 同步 JS 子表：ecod.global.js 的 ICON_PATHS（标记注释之间整体重写） ---------- */

const JS_FILE = join(THEME_DIR, 'assets', 'js', 'ecod.global.js');
const JS_START = '/* icons:generated:start */';
const JS_END = '/* icons:generated:end */';
/* JS 动态 DOM 用到的图标子集（键名 = ICON_PATHS 键，值取自映射表数据） */
const JS_KEYS = [
    'moon', 'sun', 'heart', 'star', 'check', 'copy', 'link', 'trash',
    'play', 'pause', 'volume', 'search', 'file', 'list', 'external',
    'x', 'clock', 'inbox', 'user', 'alert', 'left', 'right', 'download', 'zap',
    'info', 'check-circle', 'x-circle',
];

const byName = new Map(entries);
const jsMissing = JS_KEYS.filter((k) => !byName.has(k));
if (jsMissing.length) {
    throw new Error(`映射表缺少 JS 子集所需图标: ${jsMissing.join(', ')}`);
}

/* 键名统一加引号：带连字符的键（check-circle 等）不加引号是非法 JS */
const jsLines = JS_KEYS.map((name) => `        ${jsQuote(name)}: ${jsQuote(byName.get(name))},`);
const jsBlock =
    '    ' + JS_START + '\n    var ICON_PATHS = {\n'
    + jsLines.join('\n') + '\n    };\n    ' + JS_END;

const jsSource = await readFile(JS_FILE, 'utf8');
const iStart = jsSource.indexOf(JS_START);
const j = jsSource.indexOf(JS_END);
if (iStart < 0 || j < 0 || j < iStart) {
    throw new Error('ecod.global.js 缺少 icons:generated 标记注释，无法同步 JS 子表');
}
/* 从 START 标记所在行行首替换到 END 标记结尾，保证两个标记始终完整保留 */
const i = jsSource.lastIndexOf('\n', iStart) + 1;
await writeFile(JS_FILE, jsSource.slice(0, i) + jsBlock + jsSource.slice(j + JS_END.length));
console.log(`ecod.global.js ICON_PATHS 已同步：${JS_KEYS.length} 个图标`);

function jsQuote(s) {
    return "'" + s.replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
}
