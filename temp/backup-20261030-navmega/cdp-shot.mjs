// CDP 截图：打开首页，强制 热门分析 的 has-sub li 加 is-open + hover 态，截 header 区
import { spawn } from 'node:child_process';
import fs from 'node:fs';

const CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const PORT = 9333;
const chrome = spawn(CHROME, [
  '--headless', '--no-sandbox', '--disable-gpu',
  `--remote-debugging-port=${PORT}`, '--window-size=1440,600', 'about:blank',
]);
const sleep = ms => new Promise(r => setTimeout(r, ms));

// 拿 target webSocket url
let list;
for (let i = 0; i < 30; i++) {
  try {
    const res = await fetch(`http://127.0.0.1:${PORT}/json`);
    list = await res.json();
    if (list.length) break;
  } catch {}
  await sleep(300);
}
const page = list.find(t => t.type === 'page');
const ws = new WebSocket(page.webSocketDebuggerUrl);
await new Promise(r => ws.onopen = r);

let id = 0;
const pending = new Map();
ws.onmessage = e => {
  const m = JSON.parse(e.data);
  if (m.id && pending.has(m.id)) { pending.get(m.id)(m); pending.delete(m.id); }
};
const send = (method, params = {}) => new Promise(res => {
  const mid = ++id;
  pending.set(mid, res);
  ws.send(JSON.stringify({ id: mid, method, params }));
});

await send('Page.enable');
await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 600, deviceScaleFactor: 2, mobile: false });
await send('Page.navigate', { url: 'http://ecod.cc/' });
await sleep(3000);

await send('Runtime.evaluate', { expression: `
  document.querySelectorAll('.ecod_nav__links li.has-sub').forEach(li => {
    li.classList.add('is-open');
    li.style.setProperty('pointer-events', 'none');
  });
  document.querySelectorAll('.ecod_nav__sub').forEach(u => {
    u.style.transition = 'none'; u.style.opacity = '1'; u.style.visibility = 'visible'; u.style.transform = 'none';
  });
  'ok'
`});
await sleep(800);
const shot = await send('Page.captureScreenshot', { format: 'png' });
fs.writeFileSync('header-mega-open.png', Buffer.from(shot.result.data, 'base64'));
console.log('saved');
chrome.kill();
process.exit(0);
