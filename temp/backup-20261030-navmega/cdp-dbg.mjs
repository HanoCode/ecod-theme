import { spawn } from 'node:child_process';
const CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const PORT = 9335;
const chrome = spawn(CHROME, ['--headless','--no-sandbox','--disable-gpu',`--remote-debugging-port=${PORT}`,'--window-size=1440,600','about:blank']);
const sleep = ms => new Promise(r => setTimeout(r, ms));
let list;
for (let i = 0; i < 30; i++) {
  try { const res = await fetch(`http://127.0.0.1:${PORT}/json`); list = await res.json(); if (list.length) break; } catch {}
  await sleep(300);
}
const page = list.find(t => t.type === 'page');
const ws = new WebSocket(page.webSocketDebuggerUrl);
await new Promise(r => ws.onopen = r);
let id = 0; const pending = new Map();
ws.onmessage = e => { const m = JSON.parse(e.data); if (m.id && pending.has(m.id)) { pending.get(m.id)(m); pending.delete(m.id); } };
const send = (method, params = {}) => new Promise(res => { const mid = ++id; pending.set(mid, res); ws.send(JSON.stringify({ id: mid, method, params })); });
await send('Page.enable');
await send('Page.navigate', { url: 'http://ecod.cc/' });
for (let i = 0; i < 20; i++) {
  const r = await send('Runtime.evaluate', { expression: 'document.readyState', returnByValue: true });
  if (r.result.result.value === 'complete') break;
  await sleep(500);
}
await sleep(1500);
const r = await send('Runtime.evaluate', { expression: `
  JSON.stringify({
    url: location.href,
    title: document.title,
    header: !!document.querySelector('.ecod_header'),
    links: document.querySelectorAll('.ecod_nav__links > li').length,
    hasSub: document.querySelectorAll('.ecod_nav__links li.has-sub').length,
    subs: document.querySelectorAll('.ecod_nav__sub').length,
    navStyleDebug: (document.querySelector('.ecod_nav__links')||{}).className
  })
`, returnByValue: true });
console.log(r.result.result.value);
chrome.kill(); process.exit(0);
