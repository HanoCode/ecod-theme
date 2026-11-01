/* editor.js 短代码表单测试台：图标覆盖 + 表单 schema 完整性 + build() 产物抽检。
   跑法：node temp/test-editor-forms.mjs */
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.dirname(fileURLToPath(import.meta.url)) + '/..';
const src = readFileSync(root + '/admin/editor.js', 'utf8');

const fail = [];
const check = (name, ok) => { console.log((ok ? 'PASS' : 'FAIL') + '  ' + name); if (!ok) fail.push(name); };

/* 幻影环境：IIFE 顶层只做定义，init 挂在 DOMContentLoaded 上永不触发 */
const sandbox = `
globalThis.__EXPORT = (function () {
    'use strict';
    ${src.replace("(function () {\n    'use strict';", '').replace(/\}\)\(\);\s*$/, '')}
    return { ICON, SHORTCODES, ED_COLORS, attrPart };
})();
`;
const fakeWin = {
    addEventListener() {}, removeEventListener() {}, matchMedia: null,
    requestAnimationFrame() {}, EcodEditor: null
};
const fakeDoc = {
    readyState: 'loading',
    addEventListener() {}, removeEventListener() {},
    getElementById() { return null; }, querySelector() { return null; },
    querySelectorAll() { return []; }, createElement() { return {}; }, body: {}
};
const fn = new Function('window', 'document', 'localStorage', 'navigator', sandbox);
fn(fakeWin, fakeDoc, { getItem() { return null; }, setItem() {}, removeItem() {} }, { userAgent: 'node' });
const X = globalThis.__EXPORT;

/* 1. 每个 SHORTCODES 条目的 tag 必须有图标（否则短代码栏出现空白按钮） */
const missing = [];
X.SHORTCODES.forEach((g) => g.items.forEach((it) => {
    if (!X.ICON[it.tag]) { missing.push(g.name + ' / ' + it.tag); }
}));
check('图标覆盖：所有 tag 均有 SVG（含 posts / hide）', missing.length === 0);
if (missing.length) { console.log('      缺失: ' + missing.join(', ')); }

/* 2. schema 完整性：有 form 必有 build；无 form 必有 tpl */
let schemaBad = [];
X.SHORTCODES.forEach((g) => g.items.forEach((it) => {
    if (it.form && typeof it.build !== 'function') { schemaBad.push(it.tag + ' 缺 build'); }
    if (!it.form && !it.tpl) { schemaBad.push(it.tag + ' 缺 tpl'); }
    if (it.form) {
        it.form.forEach((f) => {
            if (!f.key || !f.label) { schemaBad.push(it.tag + ' 字段缺 key/label'); }
            if (f.type === 'select' && (!f.opts || !f.opts.length)) { schemaBad.push(it.tag + ' select 缺 opts'); }
            if (f.type === 'rows' && (!f.fields || !f.fields.length)) { schemaBad.push(it.tag + ' rows 缺 fields'); }
        });
    }
}));
check('schema 完整性（form↔build / tpl / 字段必备项）', schemaBad.length === 0);
if (schemaBad.length) { console.log('      问题: ' + schemaBad.join(', ')); }

/* 3. build() 产物抽检 */
const get = (tag) => {
    for (const g of X.SHORTCODES) { for (const it of g.items) { if (it.tag === tag && it.build) { return it; } } }
    return null;
};

const music = get('music').build({
    title: '本期歌单',
    tracks: [
        { url: 'https://a.mp3', name: '歌名一', cover: '' },
        { url: 'https://b.mp3', name: '歌名二', cover: 'https://c.jpg' },
        { url: '', name: 'x', cover: '' }
    ]
});
check('music：标题属性 + 行格式「url | 名 | 封面」+ 空行剔除',
    music.text === '[music title="本期歌单"]\nhttps://a.mp3 | 歌名一\nhttps://b.mp3 | 歌名二 | https://c.jpg\n[/music]');

const hide = get('hide').build({ type: 'password', pw: 'ab"c', level: '', tip: '', body: '内容' });
check('hide：password 带pw + 引号转义为 &quot;', hide.text === '[hide type="password" pw="ab&quot;c"]内容[/hide]');

const hideLv = get('hide').build({ type: 'login', pw: 'x', level: '3', tip: '', body: '' });
check('hide：login 时忽略 pw/level', hideLv.text === '[hide type="login"][/hide]');

const posts = get('posts').build({ title: '相关阅读', limit: '5', category: '', tag: 'dev', order: 'date', mode: 'list', related: '' });
check('posts：默认值不输出（limit=5/order=date/mode=list）', posts.text === '[posts title="相关阅读" tag="dev"]');

const lamp = get('lamp').build({ color: 'green', text: '' });
check('lamp：无文字走裸值形态', lamp.text === '[lamp green]' && lamp.inline === true);

const lampT = get('lamp').build({ color: 'red', text: '进行中' });
check('lamp：带文字走 color= 属性形态', lampT.text === '[lamp color="red" text="进行中"]');

const badge = get('badge').build({ color: 'red', style: '', body: 'NEW' });
const badgeS = get('badge').build({ color: 'red', style: 'solid', body: 'HOT' });
check('badge：裸色 / solid 属性两种形态', badge.text === '[badge red]NEW[/badge]' && badgeS.text === '[badge color="red" style="solid"]HOT[/badge]');

const tabs = get('tabs').build({ tabs: [{ title: '标签一', body: '内容A' }, { title: '标签二', body: '内容B' }] });
check('tabs：多行 [tab] 结构', tabs.text === '[tabs]\n[tab title="标签一"]内容A[/tab]\n[tab title="标签二"]内容B[/tab]\n[/tabs]');

const cloud = get('cloud').build({ rows: [{ url: 'https://pan.baidu.com/s/x', pwd: '4321', desc: '' }, { url: 'https://pan.quark.cn/s/y', pwd: '', desc: '夸克' }] });
check('cloud：空提取码占位、尾段不拖竖线',
    cloud.text === '[cloud]\nhttps://pan.baidu.com/s/x | 4321\nhttps://pan.quark.cn/s/y | | 夸克\n[/cloud]');

const note = get('note').build({ type: 'info', title: '', body: '正文' });
check('note：空标题省略 title 属性', note.text === '[note type="info"]正文[/note]');

const card = get('card').build({ title: 'T', desc: '', icon: '', href: '', tag: '', body: '' });
const cardB = get('card').build({ title: 'T', desc: '', icon: '', href: '', tag: '', body: '补充' });
check('card：无补充文字自闭合，有则成对', card.text === '[card title="T"]' && cardB.text === '[card title="T"]补充[/card]');

const dl = get('download').build({ url: 'https://pan.baidu.com/s/1', name: '包.zip', size: '2 MB', body: '' });
check('download：url/name/size 属性齐全', dl.text === '[download url="https://pan.baidu.com/s/1" name="包.zip" size="2 MB"][/download]');

const prog = get('progress').build({ percentage: '', color: '', label: '' });
check('progress：百分比缺省回落 60', prog.text.includes('percentage="60"'));

/* 4. attrPart 转义与空值跳过 */
check('attrPart：空值返回空串、单引号原样保留',
    X.attrPart('t', '') === '' && X.attrPart('t', "it's") === ' t="it\'s"');

/* 5. 无 form 的简单条目（dotted/mark/kbd/spoiler/reply/gallery）仍走 tpl 直插 */
const noForm = [];
X.SHORTCODES.forEach((g) => g.items.forEach((it) => { if (!it.form) { noForm.push(it.tag); } }));
check('简单条目保留 tpl 直插：' + noForm.join(','),
    ['dotted', 'mark', 'kbd', 'spoiler', 'reply', 'gallery'].every((t) => noForm.includes(t)));

console.log(fail.length ? '\n' + fail.length + ' 项失败' : '\n全部通过');
process.exit(fail.length ? 1 : 0);
