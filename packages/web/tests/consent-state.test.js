import { test, eq, assert } from './harness.js';
import { validateConfig } from '../src/config.js';
import { createRecord, validateRecord, MIGRATIONS, SCHEMA_VERSION } from '../src/consent-state.js';
import { impliedCategories, allGranted, allDenied, normalizeChoice } from '../src/consent-policy.js';

const cfg = validateConfig({}).config;
const NOW = 1800000000;

test('state: opt-in implies only necessary', () => {
  eq(impliedCategories(cfg, {}), { necessary: true, functional: false, analytics: false, marketing: false });
});

test('state: opt-out implies optional granted, GPC denies marketing', () => {
  const c = validateConfig({ profile: 'opt-out' }).config;
  eq(impliedCategories(c, {}), { necessary: true, functional: true, analytics: true, marketing: true });
  eq(impliedCategories(c, { globalPrivacyControl: true }).marketing, false);
  const noGpc = validateConfig({ profile: 'opt-out', gpc: false }).config;
  eq(impliedCategories(noGpc, { globalPrivacyControl: true }).marketing, true);
});

test('state: accept/reject helpers', () => {
  eq(allGranted(cfg), { necessary: true, functional: true, analytics: true, marketing: true });
  eq(allDenied(cfg), { necessary: true, functional: false, analytics: false, marketing: false });
});

test('state: normalizeChoice forces necessary, drops unknown, missing=false', () => {
  eq(normalizeChoice(cfg, { necessary: false, analytics: true, bogus: true }), { necessary: true, functional: false, analytics: true, marketing: false });
  eq(normalizeChoice(cfg, { analytics: 'yes' }).analytics, false, 'non-boolean ignored');
});

test('state: valid record round-trips', () => {
  const rec = createRecord(cfg, allGranted(cfg), 'accept-all', null, NOW);
  const r = validateRecord(JSON.stringify(rec), cfg, NOW + 10);
  assert(r.valid, r.reason);
  eq(r.record.categories.marketing, true);
  eq(r.record.expiresAt, NOW + 180 * 86400);
});

test('state: missing / corrupt never grants', () => {
  eq(validateRecord(null, cfg, NOW).reason, 'missing');
  eq(validateRecord('{not json', cfg, NOW).reason, 'corrupt');
  eq(validateRecord('[]', cfg, NOW).reason, 'corrupt');
  eq(validateRecord(JSON.stringify({ schema: 1, categories: { necessary: true, analytics: 'true' } }), cfg, NOW).valid, false);
  const rec = createRecord(cfg, allGranted(cfg), 'accept-all', null, NOW);
  rec.method = 'sneaky';
  eq(validateRecord(JSON.stringify(rec), cfg, NOW).reason, 'corrupt');
  const rec2 = createRecord(cfg, allGranted(cfg), 'accept-all', null, NOW);
  rec2.categories.necessary = false;
  eq(validateRecord(JSON.stringify(rec2), cfg, NOW).valid, false);
});

test('state: expiry', () => {
  const rec = createRecord(cfg, allGranted(cfg), 'accept-all', null, NOW);
  eq(validateRecord(JSON.stringify(rec), cfg, rec.expiresAt).reason, 'expired');
});

test('state: policy version change invalidates but keeps hint', () => {
  const rec = createRecord(cfg, allGranted(cfg), 'accept-all', null, NOW);
  const v2 = validateConfig({ policyVersion: '2' }).config;
  const r = validateRecord(JSON.stringify(rec), v2, NOW);
  eq(r.valid, false);
  eq(r.reason, 'policy');
  eq(r.hint.analytics, true);
});

test('state: new category added invalidates record', () => {
  const rec = createRecord(cfg, allGranted(cfg), 'accept-all', null, NOW);
  const more = validateConfig({ categories: { social: {} } }).config;
  eq(validateRecord(JSON.stringify(rec), more, NOW).reason, 'categories');
});

test('state: newer schema is rejected, older migrates', () => {
  const rec = createRecord(cfg, allGranted(cfg), 'accept-all', null, NOW);
  rec.schema = SCHEMA_VERSION + 1;
  eq(validateRecord(JSON.stringify(rec), cfg, NOW).reason, 'schema');
  const old = { schema: 0, granted: ['analytics'], ts: NOW };
  eq(validateRecord(JSON.stringify(old), cfg, NOW).reason, 'schema', 'no migration registered');
  MIGRATIONS[0] = (r) => ({
    schema: 1, policy: '1', profile: 'opt-in', method: 'custom',
    categories: { necessary: true, functional: false, analytics: r.granted.includes('analytics'), marketing: false },
    createdAt: r.ts, updatedAt: r.ts, expiresAt: r.ts + 86400
  });
  try {
    const m = validateRecord(JSON.stringify(old), cfg, NOW);
    assert(m.valid, m.reason);
    eq(m.record.categories.analytics, true);
  } finally {
    delete MIGRATIONS[0];
  }
});

test('state: createdAt preserved on update', () => {
  const a = createRecord(cfg, allDenied(cfg), 'reject-all', null, NOW);
  const b = createRecord(cfg, allGranted(cfg), 'accept-all', a, NOW + 50);
  eq(b.createdAt, NOW);
  eq(b.updatedAt, NOW + 50);
});
