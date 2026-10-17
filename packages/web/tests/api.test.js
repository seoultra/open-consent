import { test, eq, assert, freshFrame, uniqueCookie, tick } from './harness.js';
import { createOpenConsent } from '../src/index.js';
import { readCookie } from '../src/storage.js';

async function setup(cfgExtra, html) {
  const { win, doc } = await freshFrame(html);
  const name = uniqueCookie();
  const oc = createOpenConsent(win, doc);
  const cfg = Object.assign({ cookie: { name } }, cfgExtra || {});
  return { win, doc, oc, name, cfg };
}
const shadow = (doc) => doc.getElementById('open-consent').shadowRoot;

test('api: first visit shows banner, nothing granted', async () => {
  const { doc, oc, cfg } = await setup();
  oc.init(cfg);
  const s = oc.getConsent();
  eq(s.decided, false);
  eq(s.categories, { necessary: true, functional: false, analytics: false, marketing: false });
  assert(!shadow(doc).querySelector('[data-oc="banner"]').hidden, 'banner visible');
  eq(oc.hasConsent('analytics'), false);
  eq(oc.hasConsent('necessary'), true);
});

test('api: acceptAll persists and hides banner', async () => {
  const { doc, oc, cfg, name } = await setup();
  oc.init(cfg);
  oc.acceptAll();
  assert(oc.hasConsent('marketing'));
  const stored = JSON.parse(readCookie(document, name));
  eq(stored.method, 'accept-all');
  eq(stored.categories.analytics, true);
  assert(shadow(doc).querySelector('[data-oc="banner"]').hidden);
  assert(!shadow(doc).querySelector('[data-oc="reopen"]').hidden, 'reopen button appears');
});

test('api: stored consent is honoured on next load (no banner)', async () => {
  const a = await setup();
  a.oc.init(a.cfg);
  a.oc.updateConsent({ analytics: true });
  const b = await freshFrame();
  const oc2 = createOpenConsent(b.win, b.doc);
  oc2.init(a.cfg);
  eq(oc2.getConsent().categories.analytics, true);
  eq(oc2.getConsent().categories.marketing, false);
  assert(shadow(b.doc).querySelector('[data-oc="banner"]').hidden);
});

test('api: rejectAll / withdraw / reset', async () => {
  const { oc, cfg, name, doc } = await setup();
  oc.init(cfg);
  oc.acceptAll();
  oc.rejectAll();
  eq(oc.getConsent().categories, { necessary: true, functional: false, analytics: false, marketing: false });
  oc.acceptAll();
  oc.withdrawConsent();
  eq(oc.getConsent().method, 'withdraw');
  eq(oc.hasConsent('analytics'), false);
  oc.resetConsent();
  eq(readCookie(document, name), null);
  eq(oc.getConsent().decided, false);
  assert(!shadow(doc).querySelector('[data-oc="banner"]').hidden, 'banner back after reset');
});

test('api: policy version bump re-prompts', async () => {
  const a = await setup();
  a.oc.init(a.cfg);
  a.oc.acceptAll();
  const b = await freshFrame();
  const oc2 = createOpenConsent(b.win, b.doc);
  oc2.init(Object.assign({}, a.cfg, { policyVersion: '2' }));
  eq(oc2.getConsent().decided, false);
  eq(oc2.getConsent().reason, 'policy');
  eq(oc2.hasConsent('analytics'), false);
});

test('api: corrupted cookie = no consent', async () => {
  const { win, doc, oc, cfg, name } = await setup();
  document.cookie = name + '=' + encodeURIComponent('{"schema":1,"categories":{"analytics":true}}') + '; Path=/';
  oc.init(cfg);
  eq(oc.hasConsent('analytics'), false);
  eq(oc.getConsent().reason, 'corrupt');
});

test('api: second init ignored, single banner', async () => {
  const { doc, oc, cfg } = await setup();
  oc.init(cfg);
  oc.init(Object.assign({}, cfg, { profile: 'opt-out' }));
  eq(oc.getConfig().profile, 'opt-in');
  eq(doc.querySelectorAll('#open-consent').length, 1);
});

test('api: events fire with details; listener errors isolated', async () => {
  const { doc, oc, cfg } = await setup();
  const seen = [];
  oc.on('consent', () => { throw new Error('boom (expected in test)'); });
  oc.on('consent', (d) => seen.push(['consent', d.method]));
  oc.on('change', (c) => seen.push(['change', c.category, c.granted]));
  const off = oc.on('withdraw', () => seen.push(['withdraw']));
  let domEvent = null;
  doc.addEventListener('openconsent:consent', (e) => { domEvent = e.detail.method; });
  oc.init(cfg);
  oc.updateConsent({ analytics: true });
  oc.withdrawConsent();
  off();
  oc.withdrawConsent();
  eq(seen, [['consent', 'custom'], ['change', 'analytics', true], ['consent', 'withdraw'], ['change', 'analytics', false], ['withdraw'], ['consent', 'withdraw']]);
  eq(domEvent, 'withdraw');
});

test('api: UI buttons drive state; preferences modal saves custom choice', async () => {
  const { doc, oc, cfg } = await setup();
  oc.init(cfg);
  const sr = shadow(doc);
  sr.querySelector('[data-oc="banner"] [data-oc-action="customize"]').click();
  const overlay = sr.querySelector('[data-oc="overlay"]');
  assert(!overlay.hidden, 'preferences open');
  const box = sr.querySelector('input[data-oc-category="analytics"]');
  eq(box.checked, false, 'optional categories not pre-selected');
  box.click();
  sr.querySelector('[data-oc="preferences"] [data-oc-action="save"]').click();
  eq(oc.getConsent().categories, { necessary: true, functional: false, analytics: true, marketing: false });
  assert(overlay.hidden);
  assert(sr.querySelector('[data-oc="banner"]').hidden);
});

test('api: data-open-consent-open opens preferences', async () => {
  const { doc, oc, cfg } = await setup({}, '<button data-open-consent-open id="m">Manage</button>');
  oc.init(cfg);
  oc.rejectAll();
  doc.getElementById('m').click();
  assert(!shadow(doc).querySelector('[data-oc="overlay"]').hidden);
});

test('api: texts override + Indonesian auto language', async () => {
  const { doc, oc, cfg } = await setup();
  doc.documentElement.lang = 'id-ID';
  oc.init(Object.assign({}, cfg, { texts: { id: { banner: { title: 'Halo cookie' } } } }));
  const sr = shadow(doc);
  eq(sr.querySelector('.oc-title').textContent, 'Halo cookie');
  eq(sr.querySelector('[data-oc-action="reject-all"]').textContent, 'Tolak semua');
});

test('api: text is never parsed as HTML', async () => {
  const { doc, oc, cfg } = await setup();
  oc.init(Object.assign({}, cfg, { texts: { en: { banner: { title: '<img src=x onerror="window.__xss=1">' } } } }));
  await tick(30);
  eq(doc.defaultView.__xss, undefined);
  eq(shadow(doc).querySelectorAll('img').length, 0);
});

test('api: blocked script runs after accept; cookies cleared on withdrawal', async () => {
  const { win, doc, oc, cfg } = await setup({}, '<script type="text/plain" data-open-consent="analytics">window.__ga = 1;</script>');
  oc.init(cfg);
  await tick(10);
  eq(win.__ga, undefined);
  oc.acceptAll();
  await tick(10);
  eq(win.__ga, 1);
  document.cookie = '_gid=GA1.2.3; Path=/';
  oc.withdrawConsent();
  eq(readCookie(document, '_gid'), null);
});

test('api: Google defaults precede tags; update on choice', async () => {
  const { win, oc, cfg } = await setup({ google: { enabled: true, tags: ['G-TEST12345'] } });
  oc.init(cfg);
  const dl = () => win.dataLayer.map((e) => (e && e.length !== undefined && !Array.isArray(e) ? Array.from(e) : e));
  eq(dl()[0][0], 'consent');
  eq(dl()[0][1], 'default');
  eq(dl().filter((e) => e[0] === 'config').length, 0, 'basic mode: no tag before consent');
  oc.updateConsent({ analytics: true });
  const upd = dl().filter((e) => e[0] === 'consent' && e[1] === 'update');
  eq(upd.length, 1);
  eq(upd[0][2].analytics_storage, 'granted');
  eq(upd[0][2].ad_storage, 'denied');
  eq(dl().filter((e) => e[0] === 'config' && e[1] === 'G-TEST12345').length, 1);
  oc.withdrawConsent();
  const last = dl().filter((e) => e[0] === 'consent' && e[1] === 'update').pop();
  eq(last[2].analytics_storage, 'denied');
});

test('api: opt-out profile applies until opt-out; GPC respected', async () => {
  const { win, oc, cfg } = await setup({ profile: 'opt-out' });
  Object.defineProperty(win.navigator, 'globalPrivacyControl', { value: true, configurable: true });
  oc.init(cfg);
  eq(oc.hasConsent('analytics'), true);
  eq(oc.hasConsent('marketing'), false);
  eq(oc.getConsent().gpc, true);
});

test('api: methods before init are safe', async () => {
  const { oc } = await setup();
  eq(oc.acceptAll(), null);
  eq(oc.hasConsent('analytics'), false);
  let ran = false;
  oc.whenAllowed('analytics', () => { ran = true; });
  eq(ran, false);
});

test('api: language supplied via texts is resolved from <html lang>', async () => {
  const { doc, oc, cfg } = await setup();
  doc.documentElement.lang = 'de-DE';
  oc.init(Object.assign({}, cfg, { texts: { de: { banner: { rejectAll: 'Alle ablehnen' }, categories: { analytics: { title: 'Statistik' } } } } }));
  const sr = shadow(doc);
  eq(sr.querySelector('[data-oc="banner"] [data-oc-action="reject-all"]').textContent, 'Alle ablehnen');
  eq(sr.querySelector('[data-oc="banner"] [data-oc-action="accept-all"]').textContent, 'Accept all', 'missing keys fall back to English');
  eq(sr.querySelector('[data-oc-cat="analytics"] .oc-cat-title').textContent, 'Statistik');
});

test('api: zh-HK resolves to yue via alias', async () => {
  const { resolveLang } = await import('../src/i18n.js');
  eq(resolveLang('auto', { documentElement: { getAttribute: () => 'zh-HK' } }, ['en', 'id', 'yue', 'zh']), 'yue');
  eq(resolveLang('auto', { documentElement: { getAttribute: () => 'zh-CN' } }, ['en', 'id', 'yue', 'zh']), 'zh');
  eq(resolveLang('auto', { documentElement: { getAttribute: () => 'fil' } }, ['en', 'tl']), 'tl');
  eq(resolveLang('auto', { documentElement: { getAttribute: () => 'xx' } }, ['en']), 'en');
});

test('i18n: region files and aliases (pt-br, zh-tw, nb)', async () => {
  const { resolveLang } = await import('../src/i18n.js');
  const avail = ['en', 'id', 'pt', 'pt-br', 'zh', 'zh-tw', 'yue', 'nb'];
  const r = (l) => resolveLang('auto', { documentElement: { getAttribute: () => l } }, avail);
  eq(r('pt-BR'), 'pt-br');
  eq(r('pt-PT'), 'pt');
  eq(r('pt'), 'pt');
  eq(r('zh-TW'), 'zh-tw');
  eq(r('zh-Hant'), 'zh-tw');
  eq(r('zh-HK'), 'yue');
  eq(r('zh-CN'), 'zh');
  eq(r('no'), 'nb');
  eq(r('nb-NO'), 'nb');
});
