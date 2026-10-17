import { test, eq, assert, freshFrame } from './harness.js';
import { googleSignals, sendDefault, sendUpdate, loadTag, loadPermittedTags, gcmState } from '../src/google-consent.js';
import { validateConfig } from '../src/config.js';

const entries = (win) => win.dataLayer.map((e) => (e && typeof e.length === 'number' && !Array.isArray(e) ? Array.from(e) : e));

test('gcm: signal mapping', () => {
  eq(googleSignals({ necessary: true, functional: true, analytics: false, marketing: true }), {
    ad_storage: 'granted', ad_user_data: 'granted', ad_personalization: 'granted', analytics_storage: 'denied',
    functionality_storage: 'granted', personalization_storage: 'granted', security_storage: 'granted'
  });
  const none = googleSignals({});
  ['ad_storage', 'ad_user_data', 'ad_personalization', 'analytics_storage', 'functionality_storage', 'personalization_storage'].forEach((k) => eq(none[k], 'denied', k));
});

test('gcm: default pushes Arguments, once, with wait_for_update and region', async () => {
  const { win } = await freshFrame();
  const g = validateConfig({ google: { enabled: true, region: ['DE'] } }).config.google;
  assert(sendDefault(win, g, {}));
  assert(sendDefault(win, g, { analytics: true }), 'second call is a no-op but ok');
  const e = entries(win);
  eq(e.filter((x) => x[0] === 'consent' && x[1] === 'default').length, 1);
  eq(e[0][2].analytics_storage, 'denied');
  eq(e[0][2].wait_for_update, 500);
  eq(e[0][2].region, ['DE']);
  assert(Object.prototype.toString.call(win.dataLayer[0]) === '[object Arguments]', 'gtag needs Arguments objects');
  eq(e[1], ['set', 'ads_data_redaction', true]);
});

test('gcm: existing third-party default -> no signals at all', async () => {
  const { win } = await freshFrame();
  win.dataLayer = [];
  win.gtag = function () { win.dataLayer.push(arguments); };
  win.gtag('consent', 'default', { analytics_storage: 'granted' });
  const g = validateConfig({ google: { enabled: true } }).config.google;
  eq(sendDefault(win, g, {}), false);
  eq(sendUpdate(win, { analytics: true }), false);
  eq(loadTag(win, win.document, 'G-ABC12345'), false);
  eq(win.dataLayer.length, 1);
  assert(gcmState(win).conflict);
});

test('gcm: overrideExisting sends anyway', async () => {
  const { win } = await freshFrame();
  win.dataLayer = [['consent', 'default', {}]];
  const g = validateConfig({ google: { enabled: true, overrideExisting: true } }).config.google;
  eq(sendDefault(win, g, {}), true);
});

test('gcm: update + GTM event', async () => {
  const { win } = await freshFrame();
  const g = validateConfig({ google: { enabled: true } }).config.google;
  sendDefault(win, g, {});
  sendUpdate(win, { analytics: true, marketing: false }, 'custom');
  const e = entries(win);
  const upd = e.find((x) => x[0] === 'consent' && x[1] === 'update');
  eq(upd[2].analytics_storage, 'granted');
  eq(upd[2].ad_storage, 'denied');
  const ev = e.find((x) => x && x.event === 'open_consent_update');
  eq(ev.open_consent.method, 'custom');
});

test('gcm: basic mode loads tag only for granted category; loader injected once', async () => {
  const { win, doc } = await freshFrame();
  const g = validateConfig({ google: { enabled: true, tags: ['G-ABC12345', 'AW-1234567'] } }).config.google;
  sendDefault(win, g, {});
  eq(loadPermittedTags(win, doc, g, { analytics: false, marketing: false }), []);
  eq(doc.querySelectorAll('script[data-open-consent-loader]').length, 0);
  eq(loadPermittedTags(win, doc, g, { analytics: true, marketing: false }), ['G-ABC12345']);
  eq(loadPermittedTags(win, doc, g, { analytics: true, marketing: true }), ['AW-1234567']);
  eq(doc.querySelectorAll('script[data-open-consent-loader]').length, 1);
  const e = entries(win);
  const iDefault = e.findIndex((x) => x[0] === 'consent' && x[1] === 'default');
  const iConfig = e.findIndex((x) => x[0] === 'config');
  assert(iDefault === 0 && iConfig > iDefault, 'default before config');
  eq(e.filter((x) => x[0] === 'js').length, 1);
});

test('gcm: advanced mode loads tags immediately after default', async () => {
  const { win, doc } = await freshFrame();
  const g = validateConfig({ google: { enabled: true, mode: 'advanced', tags: ['G-ABC12345'] } }).config.google;
  eq(loadTag(win, doc, 'G-ABC12345'), false, 'never before default');
  sendDefault(win, g, {});
  eq(loadPermittedTags(win, doc, g, { analytics: false }), ['G-ABC12345']);
});
