import { categoryNames } from './config.js';

/*
 * Policy profiles decide what applies BEFORE a visitor has made a choice.
 *
 *  opt-in  : every optional category is denied until the visitor opts in.
 *            Use where prior consent is required (e.g. GDPR/ePrivacy-style rules).
 *  opt-out : optional categories apply until the visitor opts out. If the
 *            browser sends Global Privacy Control (and `gpc` is on), the
 *            "marketing" category is treated as opted out.
 *
 * Which profile is legally appropriate is the site owner's decision; the
 * engine does not geolocate visitors.
 */

export function gpcEnabled(cfg, nav) {
  return !!(cfg.gpc && nav && nav.globalPrivacyControl === true);
}

export function impliedCategories(cfg, nav) {
  const out = {};
  const optOut = cfg.profile === 'opt-out';
  const gpc = gpcEnabled(cfg, nav);
  categoryNames(cfg).forEach(function (name) {
    if (cfg.categories[name].required) out[name] = true;
    else if (optOut) out[name] = !(gpc && name === 'marketing');
    else out[name] = false;
  });
  return out;
}

export function allGranted(cfg) {
  const out = {};
  categoryNames(cfg).forEach(function (n) {
    out[n] = true;
  });
  return out;
}

export function allDenied(cfg) {
  const out = {};
  categoryNames(cfg).forEach(function (n) {
    out[n] = !!cfg.categories[n].required;
  });
  return out;
}

/** Normalise a partial {category: bool} map: required always true, unknown dropped, missing = false. */
export function normalizeChoice(cfg, prefs, base) {
  const out = {};
  const p = prefs || {};
  const b = base || {};
  categoryNames(cfg).forEach(function (n) {
    if (cfg.categories[n].required) out[n] = true;
    else if (typeof p[n] === 'boolean') out[n] = p[n];
    else out[n] = b[n] === true;
  });
  return out;
}
