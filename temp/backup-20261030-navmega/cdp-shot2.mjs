import { spawn } from 'node:child_process';
import fs from 'node:fs';
const CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const PORT = 9334;
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
// 等 load 完成
for (let i = 0; i < 20; i++) {
  const r = await send('Runtime.evaluate', { expression: 'document.readyState + "|" + document.documentElement.className' , returnByValue: true });
  if (String(r.result.result.value).startsWith('complete')) break;
  await sleep(500);
}
await sleep(1000);
await send('Runtime.evaluate', { expression: `
  document.documentElement.classList.remove('ecod-booting');
  document.documentElement.setAttribute('data-anim','none');
  document.querySelectorAll('*').forEach(el => { el.style.animation = 'none'; el.style.transition = 'none'; });
  document.querySelectorAll('.ecod_nav__links li.has-sub').forEach(li => li.classList.add('is-open'));
  document.querySelectorAll('.ecod_nav__sub').forEach(u => { u.style.opacity='1'; u.style.visibility='visible'; u.style.transform='none'; });
  'ok'
`});
await sleep(600);
const r2 = await send('Runtime.evaluate', { expression: "document.querySelector('.ecod_nav__sub') ? 'sub-exists' : 'no-sub'", returnByValue: true });
console.log('check:', r2.result.result.value);
const shot = await send('Page.captureScreenshot', { format: 'png' });
fs.writeFileSync('header-mega-open2.png', Buffer.from(shot.result.data, 'base64'));
console.log('saved');
chrome.kill(); process.exit(0);
