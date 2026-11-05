// Minimal Chrome DevTools Protocol driver for the instruction screenshots.
// Usage (from the project root, Herd running, demo data seeded):
//   node docs/changes_requested/instructions/capture/cdp.mjs docs/changes_requested/instructions/capture/steps.mjs docs/changes_requested/instructions/screenshots
import { spawn } from 'node:child_process';
import { writeFileSync, mkdirSync } from 'node:fs';
import { setTimeout as sleep } from 'node:timers/promises';
import { pathToFileURL } from 'node:url';
import { tmpdir } from 'node:os';

const [, , stepsFile, outDir] = process.argv;
mkdirSync(outDir, { recursive: true });

const port = 9333;
const chrome = spawn('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', [
  '--headless=new', `--remote-debugging-port=${port}`, `--user-data-dir=${tmpdir()}/avantti-shots-profile`,
  '--window-size=1680,1000', '--hide-scrollbars', '--no-first-run', '--no-default-browser-check',
], { stdio: 'ignore' });

let target;
for (let i = 0; i < 50; i++) {
  try { target = await (await fetch(`http://127.0.0.1:${port}/json/new?about:blank`, { method: 'PUT' })).json(); break; }
  catch { await sleep(200); }
}
const ws = new WebSocket(target.webSocketDebuggerUrl);
await new Promise(r => ws.addEventListener('open', r));
let id = 0; const pending = new Map(); const listeners = [];
ws.addEventListener('message', ev => {
  const m = JSON.parse(ev.data);
  if (m.id && pending.has(m.id)) { const p = pending.get(m.id); pending.delete(m.id); m.error ? p.rej(new Error(m.error.message)) : p.res(m.result); }
  else listeners.forEach(l => l(m));
});
const send = (method, params = {}) => new Promise((res, rej) => { const i = ++id; pending.set(i, { res, rej }); ws.send(JSON.stringify({ id: i, method, params })); });

await send('Page.enable'); await send('Runtime.enable');
await send('Page.addScriptToEvaluateOnNewDocument', { source: `setInterval(() => document.querySelectorAll('#herd-ai-host, #herd-ai-root').forEach(el => el.remove()), 100);` });
await send('Emulation.setDeviceMetricsOverride', { width: 1680, height: 1000, deviceScaleFactor: 1, mobile: false });

const api = {
  base: 'http://avantti-expenses.test',
  async go(path, wait = 1500) {
    const loaded = new Promise(r => { const l = m => { if (m.method === 'Page.loadEventFired') { listeners.splice(listeners.indexOf(l), 1); r(); } }; listeners.push(l); });
    await send('Page.navigate', { url: api.base + path }); await loaded; await sleep(wait); await api.clean();
  },
  // Remove dev-only overlays (the Herd badge) so they never reach a screenshot.
  async clean() {
    await api.js(`document.querySelectorAll('#herd-ai-host, #herd-ai-root').forEach(el => el.remove());`);
  },
  // Tag the element (or its ancestor) for an element screenshot.
  async mark(expr) {
    return api.js(`document.querySelectorAll('[data-shot]').forEach(e => e.removeAttribute('data-shot')); const el = (() => { ${expr} })(); if (el) el.setAttribute('data-shot', '1'); return !!el;`);
  },
  async js(expr, wait = 0) {
    const r = await send('Runtime.evaluate', { expression: `(async () => { ${expr} })()`, awaitPromise: true, returnByValue: true });
    if (r.exceptionDetails) throw new Error('JS: ' + (r.exceptionDetails.exception?.description || r.exceptionDetails.text));
    if (wait) await sleep(wait);
    return r.result.value;
  },
  // Capture the whole page, the viewport, or one element (selector), with padding.
  async shot(name, { selector, full = false, view = false, pad = 12, maxHeight = null } = {}) {
    await api.clean(); await sleep(150);
    let clip;
    if (selector) {
      const rect = await api.js(`const el = document.querySelector(${JSON.stringify(selector)}); if (!el) return null; el.scrollIntoView({block:'start'}); await new Promise(r=>setTimeout(r,300)); const b = el.getBoundingClientRect(); return {x:b.left+scrollX, y:b.top+scrollY, w:b.width, h:b.height};`);
      if (!rect) throw new Error('No element for ' + selector);
      clip = { x: Math.max(0, rect.x - pad), y: Math.max(0, rect.y - pad), width: rect.w + pad * 2, height: Math.min(rect.h + pad * 2, maxHeight ?? Infinity), scale: 1 };
    } else if (view) {
      const y = await api.js('return scrollY');
      clip = { x: 260, y: y + 64, width: 1680 - 260, height: 1000 - 64, scale: 1 };
    } else if (full) {
      const h = await api.js('return document.documentElement.scrollHeight');
      clip = { x: 260, y: 64, width: 1680 - 260, height: h - 64, scale: 1 };
    }
    const r = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: !!clip, ...(clip ? { clip } : {}) });
    writeFileSync(`${outDir}/${name}.png`, Buffer.from(r.data, 'base64'));
    console.log('saved', name);
  },
  sleep,
};

try {
  const steps = (await import(pathToFileURL(stepsFile).href)).default;
  await steps(api);
} catch (e) { console.error(e.message); process.exitCode = 1; }
finally { ws.close(); chrome.kill(); }
