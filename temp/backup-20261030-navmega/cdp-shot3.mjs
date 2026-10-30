import { spawn } from 'node:child_process';
import fs from 'node:fs';
const CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const PORT = 9336;
const chrome = spawn(CHROME, ['--headless','--no-sandbox','--disable-gpu',
  `--remote-debugging-port=${PORT}`,'--window-size=1440,600',
  '--host-resolver-rules=MAP ecod.cc 127.0.0.1','about:blank']);
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
// 导航重试：直到不是 chrome-error
for (let i = 0; i < 8; i++) {
  await send('Page.navigate', { url: 'http://ecod.cc/' });
  await sleep(2500);
  const r = await send('Runtime.evaluate', { expression: 'location.href', returnByValue: true });
  if (!String(r.result.result.value).includes('chrome-error')) break;
  console.log('retry nav', i);
}
for (let i = 0; i < 20; i++) {
  const r = await send('Runtime.evaluate', { expression: 'document.readyState', returnByValue: true });
  if (r.result.result.value === 'complete') break;
  await sleep(500);
}
await sleep(800);
await send('Runtime.evaluate', { expression: `
  document.documentElement.classList.remove('ecod-booting');
  document.documentElement.setAttribute('data-anim','none');
  document.querySelectorAll('.ecod_nav__links li.has-sub').forEach(li => li.classList.add('is-open'));
  'ok'
`});
await sleep(500);
const chk = await send('Runtime.evaluate', { expression: 'document.querySelectorAll(".ecod_nav__sub").length', returnByValue: true });
console.log('subs:', chk.result.result.value);
const shot = await send('Page.captureScreenshot', { format: 'png' });
fs.writeFileSync('header-mega-open3.png', Buffer.from(shot.result.data, 'base64'));
console.log('saved');
chrome.kill(); process.exit(0);
