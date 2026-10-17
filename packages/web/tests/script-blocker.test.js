import { test, eq, assert, freshFrame, tick } from './harness.js';
import { createScriptBlocker, parseCategoryAttr } from '../src/script-blocker.js';
import { buildTexts } from '../src/i18n.js';

const NAMES = ['necessary', 'functional', 'analytics', 'marketing'];

function blocker(win, doc, state) {
  return createScriptBlocker(win, doc, {
    categoryNames: () => NAMES,
    current: () => state,
    texts: () => buildTexts('en', {}),
    grant: (c) => { state[c] = true; },
    emit: () => {}
  });
}

test('blocker: parse categories', () => {
  eq(parseCategoryAttr('analytics', NAMES), ['analytics']);
  eq(parseCategoryAttr(' analytics  marketing ', NAMES), ['analytics', 'marketing']);
  eq(parseCategoryAttr('', NAMES), null);
  eq(parseCategoryAttr('analytics bogus', NAMES), null);
  eq(parseCategoryAttr(null, NAMES), null);
});

test('blocker: nothing runs without consent; runs once when granted', async () => {
  const { win, doc } = await freshFrame(
    '<script type="text/plain" data-open-consent="analytics">window.__a = (window.__a || 0) + 1;</script>' +
    '<script type="text/plain" data-open-consent="marketing">window.__m = 1;</script>'
  );
  const state = { necessary: true, analytics: false, marketing: false };
  const b = blocker(win, doc, state);
  await b.refresh();
  eq(win.__a, undefined);
  state.analytics = true;
  await b.refresh();
  await b.refresh();
  eq(win.__a, 1, 'executed exactly once');
  eq(win.__m, undefined, 'marketing still blocked');
});

test('blocker: multiple categories require all', async () => {
  const { win, doc } = await freshFrame('<script type="text/plain" data-open-consent="analytics marketing">window.__both = 1;</script>');
  const state = { necessary: true, analytics: true, marketing: false };
  const b = blocker(win, doc, state);
  await b.refresh();
  eq(win.__both, undefined);
  state.marketing = true;
  await b.refresh();
  eq(win.__both, 1);
});

test('blocker: invalid / missing category never runs', async () => {
  const { win, doc } = await freshFrame(
    '<script type="text/plain" data-open-consent="">window.__e = 1;</script>' +
    '<script type="text/plain" data-open-consent="analytcs">window.__t = 1;</script>'
  );
  const state = { necessary: true, functional: true, analytics: true, marketing: true };
  await blocker(win, doc, state).refresh();
  eq(win.__e, undefined);
  eq(win.__t, undefined);
  eq(doc.querySelector('[data-open-consent="analytcs"]').getAttribute('data-oc-state'), 'invalid');
});

test('blocker: external scripts keep document order and attributes', async () => {
  const { win, doc } = await freshFrame(
    '<script type="text/plain" data-open-consent="analytics" data-src="fixtures/lib.js" id="lib" crossorigin="anonymous"></script>' +
    '<script type="text/plain" data-open-consent="analytics">window.__order = (window.__order || []).concat("inline:" + (typeof window.__lib));</script>'
  );
  const state = { necessary: true, analytics: true };
  await blocker(win, doc, state).refresh();
  await tick(50);
  eq(win.__order, ['inline:function'], 'inline ran after external lib loaded');
  const s = doc.querySelector('script[data-open-consent-executed][src]');
  eq(s.getAttribute('crossorigin'), 'anonymous');
  eq(s.getAttribute('id'), 'lib');
});

test('blocker: unsafe data-src rejected', async () => {
  const { win, doc } = await freshFrame('<script type="text/plain" data-open-consent="analytics" data-src="javascript:window.__x=1"></script>');
  await blocker(win, doc, { necessary: true, analytics: true }).refresh();
  eq(doc.querySelectorAll('script[src^="javascript"]').length, 0);
  eq(win.__x, undefined);
});

test('blocker: dynamically added blocked script is picked up', async () => {
  const { win, doc } = await freshFrame();
  const b = blocker(win, doc, { necessary: true, analytics: true });
  b.observe();
  const s = doc.createElement('script');
  s.type = 'text/plain';
  s.setAttribute('data-open-consent', 'analytics');
  s.text = 'window.__dyn = 1;';
  doc.body.appendChild(s);
  await tick(20);
  await b.pending();
  eq(win.__dyn, 1);
});

test('blocker: iframe placeholder, load, and unload on withdrawal', async () => {
  const { win, doc } = await freshFrame('<iframe data-open-consent="marketing" data-src="blank.html?embed" width="300" height="150"></iframe>');
  const state = { necessary: true, marketing: false };
  const b = blocker(win, doc, state);
  await b.refresh();
  const f = doc.querySelector('iframe');
  eq(f.getAttribute('src'), null);
  const ph = doc.querySelector('.open-consent-placeholder');
  assert(ph && !ph.hidden, 'placeholder shown');
  assert(ph.textContent.indexOf('Marketing') !== -1);
  ph.querySelector('button').click();
  eq(state.marketing, true, 'placeholder button grants the category');
  await b.refresh();
  eq(f.getAttribute('src'), 'blank.html?embed');
  assert(ph.hidden);
  state.marketing = false;
  await b.refresh();
  eq(f.getAttribute('src'), 'about:blank');
  assert(!ph.hidden);
});

test('blocker: whenAllowed runs once', async () => {
  const { win, doc } = await freshFrame();
  const state = { necessary: true, analytics: false };
  const b = blocker(win, doc, state);
  let n = 0;
  assert(b.whenAllowed('analytics', () => n++));
  eq(b.whenAllowed('nope', () => n++), false);
  await b.refresh();
  eq(n, 0);
  state.analytics = true;
  await b.refresh();
  await b.refresh();
  eq(n, 1);
});
