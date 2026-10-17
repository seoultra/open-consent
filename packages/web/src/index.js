import { VERSION, TRANSLATIONS } from './generated/defaults.js';
import { warn, clone, isPlainObject } from './util.js';
import { validateConfig, optionalCategories, categoryNames, isSafeUrl } from './config.js';
import { impliedCategories, allGranted, allDenied, normalizeChoice, gpcEnabled } from './consent-policy.js';
import { createRecord, validateRecord, diffCategories } from './consent-state.js';
import { readCookie, writeCookie, deleteCookie, cleanupCookies } from './storage.js';
import { createEmitter } from './events.js';
import { resolveLang, buildTexts } from './i18n.js';
import { sendDefault, sendUpdate, loadPermittedTags, gcmState } from './google-consent.js';
import { createScriptBlocker } from './script-blocker.js';
import { createUI } from './ui.js';
import { embedInit } from './embed.js';

/**
 * Create an OpenConsent instance bound to a window/document. The bundle calls
 * install() once per window; tests create isolated instances directly.
 */
export function createOpenConsent(win, doc) {
  const emitter = createEmitter(doc);
  let cfg = null;
  let texts = null;
  let record = null;
  let current = {};
  let ui = null;
  let blocker = null;
  let initialized = false;
  let storageOk = true;
  let lastReason = 'missing';
  const queuedWhen = [];

  function snapshot() {
    return {
      decided: !!record,
      categories: clone(current),
      method: record ? record.method : null,
      record: clone(record),
      policyVersion: cfg ? cfg.policyVersion : null,
      profile: cfg ? cfg.profile : null,
      gpc: cfg ? gpcEnabled(cfg, win.navigator) : false,
      persisted: !!record && storageOk,
      reason: record ? 'ok' : lastReason
    };
  }

  function emit(name, detail) {
    emitter.emit(name, detail);
  }

  function mountUI() {
    if (ui || !doc.body) return;
    ui = createUI(doc, cfg, texts, {
      acceptAll: acceptAll,
      rejectAll: rejectAll,
      save: updateConsent,
      current: function () {
        return clone(current);
      },
      hasDecision: function () {
        return !!record;
      },
      emit: emit
    });
    if (!record && cfg.ui.autoShow) ui.showBanner();
    ui.syncReopen();
  }

  function onClick(e) {
    const t = e.target && e.target.closest ? e.target.closest('[data-open-consent-open], a[href="#open-consent"]') : null;
    if (!t) return;
    e.preventDefault();
    openPreferences();
  }

  function whenReady(fn) {
    if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', fn, { once: true });
    else fn();
  }

  function init(input) {
    if (initialized) {
      warn('init() called more than once; the second call was ignored');
      return api;
    }
    initialized = true;
    const v = validateConfig(input);
    cfg = v.config;
    v.warnings.forEach(warn);
    const lang = resolveLang(cfg.lang, doc, Object.keys(TRANSLATIONS).concat(Object.keys(cfg.texts)));
    texts = buildTexts(lang, cfg.texts);
    texts.__lang = lang;

    const res = validateRecord(readCookie(doc, cfg.cookie.name), cfg);
    lastReason = res.reason;
    record = res.valid ? res.record : null;
    const implied = impliedCategories(cfg, win.navigator);
    current = record ? record.categories : implied;

    // 1. Google Consent Mode default — synchronously, before any tag we load.
    if (cfg.google.enabled) {
      const preSent = gcmState(win).defaultSent;
      if (sendDefault(win, cfg.google, preSent ? allDenied(cfg) : implied)) {
        if (record || preSent) sendUpdate(win, current, record ? record.method : null);
        loadPermittedTags(win, doc, cfg.google, current);
      } else {
        emit('google:conflict', {});
      }
    }

    // 2. Declarative blocking: observe now (catches elements as they are parsed).
    blocker = createScriptBlocker(win, doc, {
      categoryNames: function () {
        return categoryNames(cfg);
      },
      current: function () {
        return current;
      },
      texts: function () {
        return texts;
      },
      grant: function (cat) {
        const p = {};
        p[cat] = true;
        updateConsent(p);
      },
      emit: emit
    });
    blocker.observe();
    queuedWhen.splice(0).forEach(function (q) {
      blocker.whenAllowed(q[0], q[1]);
    });

    whenReady(function () {
      blocker.refresh();
      mountUI();
      doc.addEventListener('click', onClick);
      emit('ready', snapshot());
    });
    return api;
  }

  function requireInit(name) {
    if (!initialized) {
      warn(name + '() called before init()');
      return false;
    }
    return true;
  }

  function commit(categories, method) {
    const before = current;
    const rec = createRecord(cfg, categories, method, record);
    storageOk = writeCookie(doc, cfg.cookie, JSON.stringify(rec), win.location);
    if (!storageOk) warn('the consent cookie could not be stored; the choice applies to this page view only');
    record = rec;
    current = clone(categories);
    const changed = diffCategories(before, current);
    const revoked = changed.filter(function (c) { return !c.granted; }).map(function (c) { return c.category; });
    revoked.forEach(function (cat) {
      cleanupCookies(doc, cfg.categories[cat].cookies, win.location, [cfg.cookie.name]);
    });
    if (cfg.google.enabled) {
      sendUpdate(win, current, method);
      loadPermittedTags(win, doc, cfg.google, current);
    }
    if (blocker) blocker.refresh();
    if (ui) ui.settled();
    const detail = snapshot();
    detail.changed = changed;
    emit('consent', detail);
    changed.forEach(function (c) {
      emit('change', c);
    });
    if (method === 'withdraw') emit('withdraw', detail);
    if (revoked.length && cfg.reloadOnWithdraw && win.location && typeof win.location.reload === 'function') {
      win.location.reload();
    }
    return detail;
  }

  function acceptAll() {
    if (!requireInit('acceptAll')) return null;
    return commit(allGranted(cfg), 'accept-all');
  }

  function rejectAll() {
    if (!requireInit('rejectAll')) return null;
    return commit(allDenied(cfg), 'reject-all');
  }

  function updateConsent(prefs) {
    if (!requireInit('updateConsent')) return null;
    if (!isPlainObject(prefs)) {
      warn('updateConsent() expects an object like {analytics: true}');
      return null;
    }
    return commit(normalizeChoice(cfg, prefs, current), 'custom');
  }

  function withdrawConsent() {
    if (!requireInit('withdrawConsent')) return null;
    return commit(allDenied(cfg), 'withdraw');
  }

  /** Forget the stored choice entirely; the banner is shown again. */
  function resetConsent() {
    if (!requireInit('resetConsent')) return null;
    const before = current;
    deleteCookie(doc, cfg.cookie.name, cfg.cookie.path, cfg.cookie.domain);
    record = null;
    lastReason = 'missing';
    current = impliedCategories(cfg, win.navigator);
    diffCategories(before, current).forEach(function (c) {
      if (!c.granted) cleanupCookies(doc, cfg.categories[c.category].cookies, win.location, [cfg.cookie.name]);
    });
    if (cfg.google.enabled) sendUpdate(win, current, null);
    if (blocker) blocker.refresh();
    if (ui) {
      ui.closePreferences(false);
      ui.showBanner();
    }
    const detail = snapshot();
    emit('reset', detail);
    return detail;
  }

  function hasConsent(category) {
    return initialized && current[category] === true;
  }

  function openPreferences() {
    if (!requireInit('openPreferences')) return false;
    if (!ui) mountUI();
    if (!ui) return false;
    ui.openPreferences(false);
    return true;
  }

  function showBanner() {
    if (!requireInit('showBanner')) return false;
    if (!ui) mountUI();
    if (ui) ui.showBanner();
    return !!ui;
  }

  function whenAllowed(categories, fn) {
    if (!blocker) {
      queuedWhen.push([categories, fn]);
      return true;
    }
    return blocker.whenAllowed(categories, fn);
  }

  /** Inject an external script once its categories are granted. */
  function loadScript(src, categories, attrs) {
    if (!isSafeUrl(src) || src === '') {
      warn('loadScript: unsafe or empty src rejected');
      return false;
    }
    return whenAllowed(categories, function () {
      const s = doc.createElement('script');
      s.src = src;
      s.async = true;
      Object.keys(attrs || {}).forEach(function (k) {
        if (/^(data-[a-z0-9-]+|crossorigin|referrerpolicy|integrity|nonce|id)$/.test(k)) s.setAttribute(k, String(attrs[k]));
      });
      (doc.head || doc.documentElement).appendChild(s);
    });
  }

  const api = {
    version: VERSION,
    init: init,
    getConsent: function () {
      return snapshot();
    },
    hasConsent: hasConsent,
    acceptAll: acceptAll,
    rejectAll: rejectAll,
    updateConsent: updateConsent,
    withdrawConsent: withdrawConsent,
    resetConsent: resetConsent,
    openPreferences: openPreferences,
    showBanner: showBanner,
    whenAllowed: whenAllowed,
    loadScript: loadScript,
    on: emitter.on,
    getConfig: function () {
      return clone(cfg);
    },
    categories: function () {
      return cfg ? optionalCategories(cfg) : [];
    },
    isInitialized: function () {
      return initialized;
    }
  };
  Object.defineProperty(api, '__openConsent', { value: true });
  return api;
}

function readInlineConfig(doc) {
  const el = doc.getElementById('open-consent-config');
  if (!el || el.tagName !== 'SCRIPT') return undefined;
  try {
    return JSON.parse(el.textContent || '{}');
  } catch (e) {
    warn('#open-consent-config contains invalid JSON; restrictive defaults are used');
    return {};
  }
}

/** Install the singleton on `window` (idempotent) and auto-initialise. */
export function install(win, doc) {
  if (win.OpenConsent && win.OpenConsent.__openConsent) return win.OpenConsent;
  const api = createOpenConsent(win, doc);
  Object.defineProperty(win, 'OpenConsent', { value: api, writable: false, configurable: false, enumerable: true });
  const script = doc.currentScript;
  const local = readInlineConfig(doc);
  const globalCfg = isPlainObject(win.OpenConsentConfig) ? win.OpenConsentConfig : undefined;
  if (script && script.getAttribute('data-site-id')) {
    embedInit(win, api, script, local !== undefined ? local : globalCfg);
  } else if (local !== undefined || globalCfg !== undefined) {
    api.init(local !== undefined ? local : globalCfg);
  }
  return api;
}
