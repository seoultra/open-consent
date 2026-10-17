import { TRANSLATIONS } from './generated/defaults.js';
import { deepMerge } from './util.js';

/** Locale aliases → translation file codes (same table as PHP Translations::ALIASES). */
export const LANG_ALIASES = {
  'zh-hk': 'yue', 'zh-mo': 'yue', 'zh-hant': 'zh-tw', 'zh-hans': 'zh', 'zh-cn': 'zh', 'zh-sg': 'zh',
  fil: 'tl', 'es-mx': 'es', in: 'id', zsm: 'ms', no: 'nb', iw: 'he', ji: 'yi', ku: 'kmr', 'pt-pt': 'pt', mo: 'ro'
};

/**
 * Resolve 'auto' (or a code) to an available language: built-in ones plus any
 * language the site supplied in config.texts. Falls back to English.
 */
export function resolveLang(requested, doc, available) {
  const langs = (available || Object.keys(TRANSLATIONS)).map(function (l) {
    return String(l).toLowerCase();
  });
  const pick = function (code) {
    if (!code) return null;
    const lc = String(code).toLowerCase().replace('_', '-');
    const candidates = [lc, LANG_ALIASES[lc], lc.split('-')[0], LANG_ALIASES[lc.split('-')[0]]];
    for (let i = 0; i < candidates.length; i++) {
      if (candidates[i] && langs.indexOf(candidates[i]) !== -1) return candidates[i];
    }
    return null;
  };
  if (requested && requested !== 'auto') {
    const r = pick(requested);
    if (r) return r;
  }
  const docLang = doc && doc.documentElement ? doc.documentElement.getAttribute('lang') : '';
  return pick(docLang) || 'en';
}

/**
 * Build the text dictionary for a language: built-in English, then the
 * built-in language, then site overrides (config.texts.en / config.texts[lang]).
 */
export function buildTexts(lang, overrides) {
  const o = overrides || {};
  let t = deepMerge(TRANSLATIONS.en, TRANSLATIONS[lang] || {});
  if (lang !== 'en' && o.en && !TRANSLATIONS[lang]) t = deepMerge(t, o.en);
  return deepMerge(t, o[lang] || {});
}

export function categoryText(texts, name) {
  const c = (texts.categories && texts.categories[name]) || {};
  return {
    title: c.title || name.charAt(0).toUpperCase() + name.slice(1),
    description: c.description || ''
  };
}

export function format(template, vars) {
  return String(template).replace(/\{(\w+)\}/g, function (m, k) {
    return Object.prototype.hasOwnProperty.call(vars, k) ? String(vars[k]) : m;
  });
}
