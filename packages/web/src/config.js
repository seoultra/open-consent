import { DEFAULT_POLICY } from './generated/defaults.js';
import { isPlainObject, hasOwn, clone } from './util.js';

/*
 * Configuration validation. The same rules are implemented in
 * packages/core-php/src/Config.php and exercised by the shared fixture
 * tests/fixtures/config-cases.json — change both together.
 *
 * Invalid values never pass through: they are replaced by the default and
 * reported in `warnings`. Unknown keys are dropped. Nothing in a config can
 * carry executable code.
 */

export const RE = {
  category: /^[a-z][a-z0-9_-]{0,31}$/,
  cookiePattern: /^[A-Za-z0-9_.\-*]{1,64}$/,
  cookieName: /^[A-Za-z0-9_-]{1,64}$/,
  domain: /^\.?[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)*$/,
  version: /^[A-Za-z0-9._-]{1,32}$/,
  lang: /^(auto|[a-z]{2,3}(-[A-Za-z0-9]{2,8})?)$/,
  color: /^(#[0-9a-fA-F]{3,8}|(rgb|rgba|hsl|hsla)\([0-9.,%\s]+\)|transparent|currentColor)$/,
  radius: /^[0-9]{1,2}(\.[0-9]{1,2})?(px|rem|em)$/,
  font: /^[A-Za-z0-9 ,"'_-]{1,200}$/,
  region: /^[A-Z]{2}(-[A-Z0-9]{1,3})?$/,
  googleTag: /^(G|AW|DC|GT)-[A-Z0-9-]{4,24}$/,
  textKey: /^[A-Za-z0-9_-]{1,40}$/,
  siteId: /^[a-z0-9][a-z0-9_-]{2,63}$/
};

const ENUMS = {
  profile: ['opt-in', 'opt-out'],
  layout: ['bar', 'box'],
  position: ['bottom', 'top', 'bottom-left', 'bottom-right', 'center'],
  reopenPosition: ['left', 'right'],
  sameSite: ['Lax', 'Strict', 'None'],
  googleMode: ['basic', 'advanced']
};

const MAX_CATEGORIES = 12;
const MAX_TEXT = 2000;

export function isSafeUrl(v) {
  if (typeof v !== 'string' || v.length > 2048) return false;
  if (v === '') return true;
  if (/^https?:\/\/[^\s"'<>]+$/i.test(v)) return true;
  return /^\/(?!\/)[^\s"'<>]*$/.test(v);
}

export function defaultTagCategory(id) {
  if (/^G-/.test(id)) return 'analytics';
  if (/^(AW|DC)-/.test(id)) return 'marketing';
  return null;
}

function check(warnings, path, ok, value, fallback) {
  if (ok) return value;
  warnings.push(path + ': invalid value ignored');
  return fallback;
}

function validateTexts(input, warnings, path, depth) {
  const out = {};
  if (!isPlainObject(input)) {
    warnings.push(path + ': must be an object');
    return out;
  }
  Object.keys(input).forEach(function (k) {
    const v = input[k];
    const p = path + '.' + k;
    if (!RE.textKey.test(k)) {
      warnings.push(p + ': invalid key');
    } else if (typeof v === 'string') {
      if (v.length <= MAX_TEXT) out[k] = v;
      else warnings.push(p + ': text too long');
    } else if (isPlainObject(v) && depth < 3) {
      out[k] = validateTexts(v, warnings, p, depth + 1);
    } else {
      warnings.push(p + ': must be a string');
    }
  });
  return out;
}

function validateCategories(input, warnings) {
  const base = clone(DEFAULT_POLICY.categories);
  if (input === undefined) return base;
  if (!isPlainObject(input)) {
    warnings.push('categories: must be an object');
    return base;
  }
  const out = base;
  Object.keys(input).forEach(function (name) {
    const v = input[name];
    if (!RE.category.test(name)) {
      warnings.push('categories.' + name + ': invalid category name');
      return;
    }
    if (v === false) {
      if (name === 'necessary') warnings.push('categories.necessary: cannot be removed');
      else delete out[name];
      return;
    }
    if (!isPlainObject(v)) {
      warnings.push('categories.' + name + ': must be an object or false');
      return;
    }
    const cur = out[name] || { required: false, cookies: [] };
    if (hasOwn(v, 'required')) {
      cur.required = check(warnings, 'categories.' + name + '.required', typeof v.required === 'boolean', v.required, cur.required);
    }
    if (hasOwn(v, 'cookies')) {
      if (Array.isArray(v.cookies) && v.cookies.length <= 50) {
        cur.cookies = v.cookies.filter(function (c) {
          const ok = typeof c === 'string' && RE.cookiePattern.test(c);
          if (!ok) warnings.push('categories.' + name + '.cookies: invalid pattern dropped');
          return ok;
        });
      } else {
        warnings.push('categories.' + name + '.cookies: must be an array (max 50)');
      }
    }
    out[name] = { required: cur.required, cookies: cur.cookies };
  });
  // "necessary" always exists and is always required.
  out.necessary = { required: true, cookies: (out.necessary && out.necessary.cookies) || [] };
  // Only "necessary" may be required: optional purposes must stay optional.
  Object.keys(out).forEach(function (name) {
    if (name !== 'necessary' && out[name].required) {
      warnings.push('categories.' + name + '.required: only "necessary" can be required');
      out[name].required = false;
    }
  });
  const names = ['necessary'].concat(Object.keys(out).filter(function (n) {
    return n !== 'necessary';
  }));
  if (names.length > MAX_CATEGORIES) {
    warnings.push('categories: more than ' + MAX_CATEGORIES + ' categories, extra ignored');
    names.length = MAX_CATEGORIES;
  }
  const ordered = {};
  names.forEach(function (n) {
    ordered[n] = out[n];
  });
  return ordered;
}

function validateSection(input, defaults, path, warnings, rules) {
  const out = clone(defaults);
  if (input === undefined) return out;
  if (!isPlainObject(input)) {
    warnings.push(path + ': must be an object');
    return out;
  }
  Object.keys(input).forEach(function (k) {
    if (!hasOwn(rules, k)) {
      warnings.push(path + '.' + k + ': unknown key ignored');
      return;
    }
    out[k] = check(warnings, path + '.' + k, rules[k](input[k]), input[k], out[k]);
  });
  return out;
}

const isBool = function (v) {
  return typeof v === 'boolean';
};
const intIn = function (min, max) {
  return function (v) {
    return typeof v === 'number' && Math.floor(v) === v && v >= min && v <= max;
  };
};
const oneOf = function (list) {
  return function (v) {
    return list.indexOf(v) !== -1;
  };
};
const matches = function (re) {
  return function (v) {
    return typeof v === 'string' && re.test(v);
  };
};

export function validateConfig(input) {
  const warnings = [];
  const src = isPlainObject(input) ? input : {};
  if (!isPlainObject(input) && input !== undefined) warnings.push('config: must be an object');

  const known = ['policyVersion', 'profile', 'lang', 'categories', 'cookie', 'links', 'ui', 'gpc', 'reloadOnWithdraw', 'google', 'texts', 'siteId', 'schemaVersion'];
  Object.keys(src).forEach(function (k) {
    if (known.indexOf(k) === -1) warnings.push(k + ': unknown key ignored');
  });

  const d = DEFAULT_POLICY;
  const cfg = {};
  cfg.policyVersion = d.policyVersion;
  if (hasOwn(src, 'policyVersion')) {
    const pv = typeof src.policyVersion === 'string' || typeof src.policyVersion === 'number' ? String(src.policyVersion) : null;
    cfg.policyVersion = check(warnings, 'policyVersion', pv !== null && RE.version.test(pv), pv, d.policyVersion);
  }
  cfg.profile = hasOwn(src, 'profile') ? check(warnings, 'profile', oneOf(ENUMS.profile)(src.profile), src.profile, d.profile) : d.profile;
  cfg.lang = hasOwn(src, 'lang') ? check(warnings, 'lang', matches(RE.lang)(src.lang), src.lang, d.lang) : d.lang;
  cfg.gpc = hasOwn(src, 'gpc') ? check(warnings, 'gpc', isBool(src.gpc), src.gpc, d.gpc) : d.gpc;
  cfg.reloadOnWithdraw = hasOwn(src, 'reloadOnWithdraw')
    ? check(warnings, 'reloadOnWithdraw', isBool(src.reloadOnWithdraw), src.reloadOnWithdraw, d.reloadOnWithdraw)
    : d.reloadOnWithdraw;
  cfg.siteId = hasOwn(src, 'siteId') ? check(warnings, 'siteId', matches(RE.siteId)(src.siteId), src.siteId, '') : '';

  cfg.categories = validateCategories(src.categories, warnings);

  cfg.cookie = validateSection(src.cookie, d.cookie, 'cookie', warnings, {
    name: matches(RE.cookieName),
    days: intIn(1, 730),
    domain: function (v) {
      return v === '' || matches(RE.domain)(v);
    },
    path: function (v) {
      return typeof v === 'string' && /^\/[A-Za-z0-9._~\/-]*$/.test(v) && v.length <= 256;
    },
    sameSite: oneOf(ENUMS.sameSite)
  });

  cfg.links = validateSection(src.links, d.links, 'links', warnings, {
    privacyPolicy: isSafeUrl,
    cookiePolicy: isSafeUrl
  });

  const uiIn = src.ui;
  const themeIn = isPlainObject(uiIn) ? uiIn.theme : undefined;
  const uiNoTheme = isPlainObject(uiIn) ? Object.assign({}, uiIn) : uiIn;
  if (isPlainObject(uiNoTheme)) delete uiNoTheme.theme;
  const uiDefaults = clone(d.ui);
  delete uiDefaults.theme;
  cfg.ui = validateSection(uiNoTheme, uiDefaults, 'ui', warnings, {
    autoShow: isBool,
    layout: oneOf(ENUMS.layout),
    position: oneOf(ENUMS.position),
    reopenButton: isBool,
    reopenPosition: oneOf(ENUMS.reopenPosition),
    zIndex: intIn(0, 2147483647)
  });
  const colorRule = matches(RE.color);
  cfg.ui.theme = validateSection(themeIn, d.ui.theme, 'ui.theme', warnings, {
    background: colorRule,
    text: colorRule,
    muted: colorRule,
    accent: colorRule,
    accentText: colorRule,
    border: colorRule,
    radius: matches(RE.radius),
    fontFamily: function (v) {
      return v === 'inherit' || matches(RE.font)(v);
    }
  });

  const gIn = isPlainObject(src.google) ? Object.assign({}, src.google) : src.google;
  const tagsIn = isPlainObject(gIn) ? gIn.tags : undefined;
  if (isPlainObject(gIn)) delete gIn.tags;
  const gDefaults = clone(d.google);
  delete gDefaults.tags;
  cfg.google = validateSection(gIn, gDefaults, 'google', warnings, {
    enabled: isBool,
    mode: oneOf(ENUMS.googleMode),
    waitForUpdate: intIn(0, 10000),
    region: function (v) {
      return Array.isArray(v) && v.length <= 300 && v.every(matches(RE.region));
    },
    urlPassthrough: isBool,
    adsDataRedaction: isBool,
    overrideExisting: isBool
  });
  cfg.google.tags = [];
  if (tagsIn !== undefined) {
    if (!Array.isArray(tagsIn) || tagsIn.length > 10) {
      warnings.push('google.tags: must be an array (max 10)');
    } else {
      tagsIn.forEach(function (t, i) {
        const p = 'google.tags[' + i + ']';
        const tag = typeof t === 'string' ? { id: t } : t;
        if (!isPlainObject(tag) || typeof tag.id !== 'string' || !RE.googleTag.test(tag.id)) {
          warnings.push(p + ': invalid tag id');
          return;
        }
        const category = tag.category || defaultTagCategory(tag.id);
        if (!category || !hasOwn(cfg.categories, category) || category === 'necessary') {
          warnings.push(p + ': needs an optional "category"');
          return;
        }
        if (cfg.google.tags.some(function (x) { return x.id === tag.id; })) return;
        cfg.google.tags.push({ id: tag.id, category: category });
      });
    }
  }

  cfg.texts = {};
  if (src.texts !== undefined) {
    if (!isPlainObject(src.texts)) {
      warnings.push('texts: must be an object');
    } else {
      Object.keys(src.texts).forEach(function (lang) {
        if (!RE.lang.test(lang) || lang === 'auto') {
          warnings.push('texts.' + lang + ': invalid language code');
          return;
        }
        cfg.texts[lang] = validateTexts(src.texts[lang], warnings, 'texts.' + lang, 1);
      });
    }
  }

  if (cfg.cookie.sameSite === 'None') cfg.cookie.secure = true;
  return { config: cfg, warnings: warnings };
}

export function categoryNames(cfg) {
  return Object.keys(cfg.categories);
}

export function optionalCategories(cfg) {
  return categoryNames(cfg).filter(function (n) {
    return !cfg.categories[n].required;
  });
}
