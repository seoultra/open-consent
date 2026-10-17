import { warn } from './util.js';

/*
 * Google Consent Mode v2.
 *
 * Category → consent type mapping (fixed, documented in docs/google-consent-mode.md):
 *   analytics  → analytics_storage
 *   marketing  → ad_storage, ad_user_data, ad_personalization
 *   functional → functionality_storage, personalization_storage
 *   (always)   → security_storage = granted
 *
 * basic    : gtag.js for configured tags is only injected once the tag's
 *            category is granted. Defaults are still sent first.
 * advanced : gtag.js is injected right after the defaults; Google tags then
 *            run in their consent-aware mode. Denied consent does NOT mean
 *            no network requests in this mode (Google sends cookieless pings).
 */

const GTAG_HOST = 'https://www.googletagmanager.com/gtag/js?id=';

export function googleSignals(categories) {
  const g = function (b) {
    return b === true ? 'granted' : 'denied';
  };
  const c = categories || {};
  return {
    ad_storage: g(c.marketing),
    ad_user_data: g(c.marketing),
    ad_personalization: g(c.marketing),
    analytics_storage: g(c.analytics),
    functionality_storage: g(c.functional),
    personalization_storage: g(c.functional),
    security_storage: 'granted'
  };
}

function isConsentDefault(entry) {
  return !!entry && typeof entry === 'object' && entry[0] === 'consent' && entry[1] === 'default';
}

/** Shared per-window state so a second copy of the bundle never re-sends defaults. */
export function gcmState(win) {
  if (!win.__openConsentGcm) {
    Object.defineProperty(win, '__openConsentGcm', {
      value: { defaultSent: false, conflict: false, loaderInjected: false, jsSent: false, configured: {} },
      enumerable: false,
      configurable: false,
      writable: false
    });
  }
  return win.__openConsentGcm;
}

function gtagFn(win) {
  win.dataLayer = win.dataLayer || [];
  if (typeof win.gtag === 'function') return win.gtag;
  // Must push the `arguments` object itself — that is what gtag.js expects.
  win.gtag = function gtag() {
    win.dataLayer.push(arguments);
  };
  return win.gtag;
}

/**
 * Send the restrictive/implied default. Returns false (and does nothing) when
 * another consent platform already sent a default and overrideExisting is off.
 */
export function sendDefault(win, gcfg, categories) {
  const st = gcmState(win);
  if (st.defaultSent) return !st.conflict;
  const existing = (win.dataLayer || []).some(isConsentDefault);
  if (existing && !gcfg.overrideExisting) {
    st.conflict = true;
    st.defaultSent = true;
    warn('another consent platform already set a Google Consent Mode default; OpenConsent will not send Google consent signals (set google.overrideExisting to change this).');
    return false;
  }
  const gtag = gtagFn(win);
  const params = googleSignals(categories);
  if (gcfg.waitForUpdate > 0) params.wait_for_update = gcfg.waitForUpdate;
  if (gcfg.region && gcfg.region.length) params.region = gcfg.region.slice();
  gtag('consent', 'default', params);
  if (gcfg.adsDataRedaction) gtag('set', 'ads_data_redaction', true);
  if (gcfg.urlPassthrough) gtag('set', 'url_passthrough', true);
  st.defaultSent = true;
  return true;
}

export function sendUpdate(win, categories, method) {
  const st = gcmState(win);
  if (st.conflict || !st.defaultSent) return false;
  const gtag = gtagFn(win);
  gtag('consent', 'update', googleSignals(categories));
  win.dataLayer.push({ event: 'open_consent_update', open_consent: { categories: Object.assign({}, categories), method: method || null } });
  return true;
}

function hasLoader(doc) {
  return !!doc.querySelector('script[src^="https://www.googletagmanager.com/gtag/js"]');
}

/** Inject gtag.js (once) and configure a tag (once). */
export function loadTag(win, doc, tagId) {
  const st = gcmState(win);
  if (st.conflict || !st.defaultSent) return false;
  if (st.configured[tagId]) return false;
  const gtag = gtagFn(win);
  if (!st.loaderInjected && !hasLoader(doc)) {
    const s = doc.createElement('script');
    s.async = true;
    s.src = GTAG_HOST + encodeURIComponent(tagId);
    s.setAttribute('data-open-consent-loader', 'gtag');
    (doc.head || doc.documentElement).appendChild(s);
  }
  st.loaderInjected = true;
  if (!st.jsSent) {
    gtag('js', new Date());
    st.jsSent = true;
  }
  gtag('config', tagId);
  st.configured[tagId] = true;
  return true;
}

/** Load whichever configured tags the current mode + consent allow. */
export function loadPermittedTags(win, doc, gcfg, categories) {
  const loaded = [];
  (gcfg.tags || []).forEach(function (t) {
    const allowed = gcfg.mode === 'advanced' || categories[t.category] === true;
    if (allowed && loadTag(win, doc, t.id)) loaded.push(t.id);
  });
  return loaded;
}
