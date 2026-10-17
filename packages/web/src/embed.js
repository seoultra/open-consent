import { DEFAULT_POLICY } from './generated/defaults.js';
import { RE, validateConfig } from './config.js';
import { sendDefault } from './google-consent.js';
import { warn, isPlainObject } from './util.js';

/*
 * Hosted-widget mode:
 *
 *   <script src="https://YOUR-CDN/open-consent/1.0.0/open-consent.js"
 *           data-site-id="example-site"
 *           data-config-url="https://YOUR-API/config.php?site={site}"></script>
 *
 * 1. If Google is in play (data-google-consent="on" or the local fallback
 *    enables it) an all-denied Consent Mode default is sent synchronously,
 *    before the config request, so tags never see an undefined state.
 * 2. The JSON config is fetched (no cookies sent, 4 s timeout) and validated
 *    with the same validator as local config — it is data only.
 * 3. On any failure the local config (#open-consent-config / window.OpenConsentConfig)
 *    is used, or restrictive built-in defaults.
 */

export const EMBED_TIMEOUT_MS = 4000;

export function configUrlFor(script, siteId, loc) {
  const attr = script.getAttribute('data-config-url');
  let url;
  try {
    url = attr ? new URL(attr.replace('{site}', encodeURIComponent(siteId)), loc.href) : new URL('config/' + encodeURIComponent(siteId) + '.json', script.src || loc.href);
  } catch (e) {
    return null;
  }
  const localDev = url.protocol === 'http:' && (url.hostname === 'localhost' || url.hostname === '127.0.0.1');
  if (url.protocol !== 'https:' && !localDev && url.origin !== loc.origin) return null;
  return url.href;
}

export function embedInit(win, api, script, localCfg) {
  const siteId = script.getAttribute('data-site-id');
  const fallback = isPlainObject(localCfg) ? localCfg : {};
  if (!RE.siteId.test(siteId || '')) {
    warn('data-site-id is invalid; using local configuration');
    api.init(fallback);
    return;
  }
  const early = validateConfig(fallback).config;
  const wantGoogle = script.getAttribute('data-google-consent') === 'on' || early.google.enabled;
  if (wantGoogle) {
    const g = Object.assign({}, DEFAULT_POLICY.google, early.google);
    g.waitForUpdate = Math.max(g.waitForUpdate, 2000);
    sendDefault(win, g, {});
  }

  const url = configUrlFor(script, siteId, win.location);
  const useFallback = function (why) {
    warn('hosted configuration unavailable (' + why + '); using local configuration');
    if (!api.isInitialized()) api.init(fallback);
  };
  if (!url || typeof win.fetch !== 'function') {
    useFallback(url ? 'fetch unsupported' : 'invalid config URL');
    return;
  }

  let timer = null;
  const ctrl = typeof AbortController === 'function' ? new AbortController() : null;
  if (ctrl) timer = win.setTimeout(function () { ctrl.abort(); }, EMBED_TIMEOUT_MS);

  win.fetch(url, { credentials: 'omit', mode: 'cors', cache: 'default', redirect: 'follow', signal: ctrl ? ctrl.signal : undefined })
    .then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(function (remote) {
      if (timer) win.clearTimeout(timer);
      if (!isPlainObject(remote)) throw new Error('not an object');
      if (remote.siteId !== undefined && remote.siteId !== siteId) throw new Error('siteId mismatch');
      api.init(remote);
    })
    .catch(function (e) {
      if (timer) win.clearTimeout(timer);
      useFallback(e && e.message ? e.message : 'error');
    });
}
