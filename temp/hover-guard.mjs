/**
 * temp/hover-guard.mjs · 一次性源码转换（跑完即可删）
 *
 * 把 assets/css/ecod.css 中含 :hover 的选择器规则收进 @media (hover: hover)，
 * 触屏设备不再出现「点按后 hover 样式粘滞」。桌面端行为完全不变（媒体查询命中）。
 *
 * 规则：
 *  - 混合选择器列表拆分：非 hover 选择器留在原地（键盘 :focus 等不受影响），
 *    hover 选择器进媒体查询；纯 hover 规则整体搬移、格式不动
 *  - 递归处理 @media / @supports / @container；@keyframes 等原样保留
 *  - 已在 (hover: hover) 内的不再包（可重复执行）
 *  - 相邻 hover 包装块合并（注释会中断合并，保持在媒体块外）；空行原样保留
 *
 * 校验：非拆分规则的「声明体多重集」转换前后一致、拆分体各多出一份、
 * 输出的每个 hover 选择器都位于 (hover: hover) 内。
 */
import { readFileSync, writeFileSync } from 'node:fs';

const FILE = process.argv[2] || 'assets/css/ecod.css';
const source = readFileSync(FILE, 'utf8');

const HOVER = /:hover(?![\w-])/;
const HOVER_MEDIA = /hover\s*:\s*hover/;
const NO_RECURSE = /^@(?:keyframes|-webkit-keyframes|font-face|page|property|counter-style|viewport|font-feature-values)\b/i;

function skipStr(s, i) {
    const q = s[i];
    let j = i + 1;
    while (j < s.length) {
        if (s[j] === '\\') { j += 2; continue; }
        if (s[j] === q) return j + 1;
        j++;
    }
    return j;
}

/** 扫描块体：注释 / at-rule / 规则。pre = 前置空白与注释（附着到后续项） */
function parseBlocks(body) {
    const items = [];
    let i = 0;
    const n = body.length;
    while (i < n) {
        let pre = '';
        while (i < n) {
            if (/\s/.test(body[i])) { pre += body[i]; i++; }
            else if (body[i] === '/' && body[i + 1] === '*') {
                const end = body.indexOf('*/', i + 2);
                const stop = end === -1 ? n : end + 2;
                pre += body.slice(i, stop);
                i = stop;
            } else break;
        }
        if (i >= n) { if (pre) items.push({ kind: 'pre', pre }); break; }

        let j = i;
        while (j < n && body[j] !== '{') {
            if (body[j] === '/' && body[j + 1] === '*') {
                const e = body.indexOf('*/', j + 2);
                j = e === -1 ? n : e + 2;
                continue;
            }
            if (body[j] === '"' || body[j] === "'") { j = skipStr(body, j); continue; }
            j++;
        }
        if (j >= n) { items.push({ kind: 'pre', pre }); break; }

        const header = body.slice(i, j);
        let depth = 1;
        let k = j + 1;
        while (k < n && depth > 0) {
            const c = body[k];
            if (c === '/' && body[k + 1] === '*') {
                const e = body.indexOf('*/', k + 2);
                k = e === -1 ? n : e + 2;
                continue;
            }
            if (c === '"' || c === "'") { k = skipStr(body, k); continue; }
            if (c === '{') depth++;
            else if (c === '}') depth--;
            k++;
        }
        items.push({
            kind: header.trim().startsWith('@') ? 'at' : 'rule',
            pre, header, inner: body.slice(j + 1, k - 1),
        });
        i = k;
    }
    return items;
}

/** 顶层逗号拆分选择器列表（括号 / 方括号 / 字符串 / 注释感知） */
function splitSelectors(header) {
    const sels = [];
    let cur = '';
    let depth = 0;
    let i = 0;
    while (i < header.length) {
        const c = header[i];
        if (c === '/' && header[i + 1] === '*') {
            const e = header.indexOf('*/', i + 2);
            i = e === -1 ? header.length : e + 2;
            continue;
        }
        if (c === '"' || c === "'") {
            const j = skipStr(header, i);
            cur += header.slice(i, j);
            i = j;
            continue;
        }
        if (c === '(' || c === '[') depth++;
        if (c === ')' || c === ']') depth--;
        if (c === ',' && depth === 0) { sels.push(cur); cur = ''; i++; continue; }
        cur += c;
        i++;
    }
    sels.push(cur);
    return sels.map((s) => s.trim()).filter(Boolean);
}

function indentBlock(text, pad) {
    return text.split('\n').map((l) => (l.trim() ? pad + l : l)).join('\n');
}

/** 处理一个块体；inHoverHover=true 时规则原样透传 */
function processBody(body, inHoverHover, stats) {
    const out = [];
    let openWrap = null; // 打开中的 @media (hover: hover) 的缩进
    const closeWrap = () => {
        if (openWrap !== null) {
            out.push({ text: '\n' + openWrap + '}' });
            openWrap = null;
        }
    };

    for (const it of parseBlocks(body)) {
        if (it.kind === 'pre') { closeWrap(); out.push({ text: it.pre }); continue; }

        if (it.kind === 'at') {
            closeWrap();
            const head = it.header.trim();
            const recurse = !NO_RECURSE.test(head);
            const inner = inHoverHover || !recurse
                ? it.inner
                : processBody(it.inner, HOVER_MEDIA.test(head), stats);
            out.push({ text: it.pre + it.header + '{' + inner + '}' });
            continue;
        }

        // ---- 普通规则 ----
        const ruleText = it.header + '{' + it.inner + '}';
        if (inHoverHover || !HOVER.test(it.header)) {
            closeWrap();
            out.push({ text: it.pre + ruleText });
            continue;
        }

        stats.moved++;
        const sels = splitSelectors(it.header);
        const hoverSels = sels.filter((s) => HOVER.test(s));
        const keepSels = sels.filter((s) => !HOVER.test(s));
        const indent = (it.pre.match(/[ \t]*$/) || [''])[0];
        /* pre 里的注释要留在媒体块外：拆成 注释部分 + 纯空白部分 */
        let pre = it.pre;
        let preComments = '';
        if (pre.includes('/*')) {
            const cut = pre.lastIndexOf('*/') + 2;
            preComments = pre.slice(0, cut);
            pre = pre.slice(cut);
        }

        if (!keepSels.length) {
            /* 纯 hover 规则：整体原样搬移；能合并就并入上一个开放块 */
            if (openWrap !== null && !preComments) {
                out.push({ text: '\n' + indentBlock(ruleText, '    ') });
            } else {
                closeWrap();
                if (preComments) out.push({ text: preComments });
                openWrap = indent;
                out.push({
                    text: pre + '@media (hover: hover) {\n'
                        + indentBlock(ruleText, '    '),
                });
            }
            continue;
        }

        /* 混合列表：非 hover 留原地，hover 进媒体查询（声明体合法出现两份） */
        stats.split++;
        stats.splitInners.push(it.inner.replace(/\s+/g, ' ').trim());
        closeWrap();
        if (preComments) out.push({ text: preComments });
        const keepText = keepSels.join(',\n' + indent) + ' {' + it.inner + '}';
        const hoverText = hoverSels.join(',\n' + indent + '    ') + ' {' + it.inner + '}';
        out.push({ text: pre + '@media (hover: hover) {\n'
            + indentBlock(indent + hoverText, '    ') + '\n' + indent + '}' });
        out.push({ text: '\n' + keepText });
    }
    closeWrap();
    return out.map((o) => o.text).join('');
}

/* ---------- 校验 ---------- */
function collect(body, inHoverHover, acc) {
    for (const it of parseBlocks(body)) {
        if (it.kind === 'pre') continue;
        if (it.kind === 'at') {
            if (!NO_RECURSE.test(it.header.trim())) {
                collect(it.inner, inHoverHover || HOVER_MEDIA.test(it.header.trim()), acc);
            }
            continue;
        }
        acc.decls.push(it.inner.replace(/\s+/g, ' ').trim());
        for (const s of splitSelectors(it.header)) {
            acc.sels.push(s);
            if (HOVER.test(s)) acc.hoverSelLocs.push({ inHoverHover, sel: s });
        }
    }
    return acc;
}

const before = { decls: [], sels: [], hoverSelLocs: [] };
collect(source, false, before);
const stats = { moved: 0, split: 0, splitInners: [] };
const result = processBody(source, false, stats);
const after = { decls: [], sels: [], hoverSelLocs: [] };
collect(result, false, after);

/* 期望多重集：原声明体 + 每个拆分体额外一份 */
const expectedDecls = before.decls.concat(stats.splitInners);
const tally = (a) => a.reduce((m, x) => { m[x] = (m[x] || 0) + 1; return m; }, {});
const et = tally(expectedDecls);
const at = tally(after.decls);
const declsOk = Object.keys(et).length === Object.keys(at).length
    && Object.entries(et).every(([k, v]) => at[k] === v);
const selsOk = after.sels.every((s) => before.sels.includes(s));
const hoverCountSame = before.hoverSelLocs.length === after.hoverSelLocs.length;
const allWrapped = after.hoverSelLocs.every((x) => x.inHoverHover);

console.log(`hover 选择器: ${before.hoverSelLocs.length} -> ${after.hoverSelLocs.length}`);
console.log(`搬移规则 ${stats.moved} 个，拆分混合列表 ${stats.split} 个`);
console.log(`声明体保留一致(含拆分体双份): ${declsOk ? 'OK' : 'FAIL'}`);
console.log(`选择器均为原有子集: ${selsOk ? 'OK' : 'FAIL'}`);
console.log(`hover 选择器全部在 (hover: hover) 内: ${allWrapped ? 'OK' : 'FAIL'}`);

if (!declsOk || !selsOk || !hoverCountSame || !allWrapped) {
    if (!allWrapped) {
        console.error('未包裹的 hover 选择器：');
        for (const x of after.hoverSelLocs.filter((x) => !x.inHoverHover)) console.error('  ', x.sel);
    }
    console.error('校验失败，不写回！');
    process.exit(1);
}

writeFileSync(FILE, result);
console.log(`已写回 ${FILE}（${source.length} -> ${result.length} 字节）`);
