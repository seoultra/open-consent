// Minimal test harness for running ES-module unit tests in a real browser.
const cases = [];
export function test(name, fn) {
  cases.push({ name, fn });
}
export function assert(cond, msg) {
  if (!cond) throw new Error(msg || 'assertion failed');
}
export function eq(actual, expected, msg) {
  const a = JSON.stringify(actual);
  const e = JSON.stringify(expected);
  if (a !== e) throw new Error((msg ? msg + ': ' : '') + 'expected ' + e + ' got ' + a);
}
let frameSeq = 0;
/** A fresh same-origin window (own dataLayer, globals, DOM). Cookies are shared — use unique cookie names. */
export function freshFrame(html) {
  return new Promise((resolve) => {
    const f = document.createElement('iframe');
    f.src = 'blank.html?' + ++frameSeq;
    f.style.cssText = 'width:800px;height:600px;position:absolute;left:-9999px';
    f.onload = () => {
      if (html) f.contentDocument.body.innerHTML = html;
      resolve({ win: f.contentWindow, doc: f.contentDocument, frame: f });
    };
    document.body.appendChild(f);
  });
}
export function uniqueCookie() {
  return 'oc_t' + Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
}
export const tick = (ms) => new Promise((r) => setTimeout(r, ms || 0));

export async function run() {
  const results = [];
  for (const c of cases) {
    try {
      await c.fn();
      results.push({ name: c.name, ok: true });
    } catch (e) {
      results.push({ name: c.name, ok: false, error: String(e && e.stack ? e.stack : e) });
    }
  }
  return results;
}
