import { categoryNames } from './config.js';
import { isPlainObject, hasOwn, nowSeconds } from './util.js';

export const SCHEMA_VERSION = 1;

/*
 * Migrations from older record schemas: MIGRATIONS[n] turns a schema-n record
 * into a schema-(n+1) record. A record whose schema has no migration path, or
 * is newer than this build understands, is treated as "no consent".
 */
export const MIGRATIONS = {};

export const METHODS = ['accept-all', 'reject-all', 'custom', 'withdraw'];

export function createRecord(cfg, categories, method, previous, now) {
  const t = now === undefined ? nowSeconds() : now;
  return {
    schema: SCHEMA_VERSION,
    policy: cfg.policyVersion,
    profile: cfg.profile,
    method: method,
    categories: categories,
    createdAt: previous && typeof previous.createdAt === 'number' ? previous.createdAt : t,
    updatedAt: t,
    expiresAt: t + cfg.cookie.days * 86400
  };
}

function migrate(record) {
  let r = record;
  let guard = 0;
  while (r.schema < SCHEMA_VERSION && guard < 20) {
    const step = MIGRATIONS[r.schema];
    if (typeof step !== 'function') return null;
    try {
      r = step(r);
    } catch (e) {
      return null;
    }
    if (!isPlainObject(r) || typeof r.schema !== 'number') return null;
    guard++;
  }
  return r.schema === SCHEMA_VERSION ? r : null;
}

/**
 * Validate a stored record against the current configuration.
 * Returns { valid, reason, record, hint } where `hint` is the previously
 * stored category map (used only to pre-fill the preferences UI after a
 * policy change — never as consent).
 */
export function validateRecord(raw, cfg, now) {
  const t = now === undefined ? nowSeconds() : now;
  if (raw === null || raw === undefined || raw === '') return { valid: false, reason: 'missing', record: null, hint: null };
  let rec = raw;
  if (typeof raw === 'string') {
    try {
      rec = JSON.parse(raw);
    } catch (e) {
      return { valid: false, reason: 'corrupt', record: null, hint: null };
    }
  }
  if (!isPlainObject(rec) || typeof rec.schema !== 'number' || Math.floor(rec.schema) !== rec.schema) {
    return { valid: false, reason: 'corrupt', record: null, hint: null };
  }
  if (rec.schema > SCHEMA_VERSION) return { valid: false, reason: 'schema', record: null, hint: null };
  if (rec.schema < SCHEMA_VERSION) {
    rec = migrate(rec);
    if (!rec) return { valid: false, reason: 'schema', record: null, hint: null };
  }
  if (!isPlainObject(rec.categories)) return { valid: false, reason: 'corrupt', record: null, hint: null };

  const names = categoryNames(cfg);
  const cats = {};
  let complete = true;
  let typesOk = true;
  names.forEach(function (n) {
    if (!hasOwn(rec.categories, n)) {
      complete = false;
      return;
    }
    if (typeof rec.categories[n] !== 'boolean') typesOk = false;
    else cats[n] = rec.categories[n];
  });
  if (!typesOk) return { valid: false, reason: 'corrupt', record: null, hint: null };
  const hint = cats;

  const numeric = ['createdAt', 'updatedAt', 'expiresAt'].every(function (k) {
    return typeof rec[k] === 'number' && isFinite(rec[k]);
  });
  if (!numeric || METHODS.indexOf(rec.method) === -1 || typeof rec.policy !== 'string') {
    return { valid: false, reason: 'corrupt', record: null, hint: null };
  }
  if (rec.policy !== cfg.policyVersion) return { valid: false, reason: 'policy', record: null, hint: hint };
  if (rec.expiresAt <= t) return { valid: false, reason: 'expired', record: null, hint: hint };
  if (!complete) return { valid: false, reason: 'categories', record: null, hint: hint };
  if (cats.necessary !== true) return { valid: false, reason: 'corrupt', record: null, hint: null };

  return {
    valid: true,
    reason: 'ok',
    hint: hint,
    record: {
      schema: SCHEMA_VERSION,
      policy: rec.policy,
      profile: typeof rec.profile === 'string' ? rec.profile : cfg.profile,
      method: rec.method,
      categories: cats,
      createdAt: rec.createdAt,
      updatedAt: rec.updatedAt,
      expiresAt: rec.expiresAt
    }
  };
}

export function diffCategories(before, after) {
  const changed = [];
  Object.keys(after).forEach(function (n) {
    if (!!before[n] !== !!after[n]) changed.push({ category: n, granted: !!after[n] });
  });
  return changed;
}
