/*
 * First-party cookie storage for the consent record.
 *
 * A cookie (not localStorage) is used so the server — e.g. the PHP library —
 * can read the visitor's choice too. The record is NOT tamper-proof: anyone
 * controlling the browser can edit it. Never use it as authorization.
 */

export function readCookie(doc, name) {
  const all = doc && typeof doc.cookie === 'string' ? doc.cookie : '';
  const parts = all ? all.split(';') : [];
  for (let i = 0; i < parts.length; i++) {
    const p = parts[i].replace(/^\s+/, '');
    const eq = p.indexOf('=');
    if (eq === -1) continue;
    if (p.slice(0, eq) === name) {
      try {
        return decodeURIComponent(p.slice(eq + 1));
      } catch (e) {
        return null;
      }
    }
  }
  return null;
}

export function isSecureContextLike(loc) {
  return !!(loc && loc.protocol === 'https:');
}

export function writeCookie(doc, cookieCfg, value, loc) {
  let s = cookieCfg.name + '=' + encodeURIComponent(value);
  s += '; Max-Age=' + cookieCfg.days * 86400;
  s += '; Path=' + (cookieCfg.path || '/');
  s += '; SameSite=' + (cookieCfg.sameSite || 'Lax');
  if (cookieCfg.domain) s += '; Domain=' + cookieCfg.domain;
  if (cookieCfg.secure || cookieCfg.sameSite === 'None' || isSecureContextLike(loc)) s += '; Secure';
  try {
    doc.cookie = s;
  } catch (e) {
    return false;
  }
  return readCookie(doc, cookieCfg.name) === value;
}

export function deleteCookie(doc, name, path, domain) {
  let s = name + '=; Max-Age=0; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Path=' + (path || '/');
  if (domain) s += '; Domain=' + domain;
  try {
    doc.cookie = s;
  } catch (e) {
    /* ignore */
  }
}

export function cookieNames(doc) {
  const all = doc && typeof doc.cookie === 'string' ? doc.cookie : '';
  if (!all) return [];
  return all.split(';').map(function (p) {
    const t = p.replace(/^\s+/, '');
    const eq = t.indexOf('=');
    return eq === -1 ? t : t.slice(0, eq);
  }).filter(Boolean);
}

export function globToRegExp(pattern) {
  const esc = pattern.replace(/[.+?^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*');
  return new RegExp('^' + esc + '$');
}

/** Domains a script on `hostname` may have set cookies for: host-only, host, and parents. */
export function candidateDomains(hostname) {
  const out = [''];
  if (!hostname || /^[0-9.]+$/.test(hostname) || hostname.indexOf(':') !== -1) return out;
  const labels = hostname.split('.');
  for (let i = 0; i < labels.length - 1; i++) {
    const d = labels.slice(i).join('.');
    out.push(d, '.' + d);
  }
  return out;
}

/**
 * Delete cookies whose names match any pattern. Only cookies visible to
 * JavaScript can be removed (HttpOnly cookies and third-party cookies on
 * other domains cannot) — documented in docs/limitations.md.
 */
export function cleanupCookies(doc, patterns, loc, protectedNames) {
  if (!patterns || !patterns.length) return [];
  const res = patterns.map(globToRegExp);
  const keep = protectedNames || [];
  const removed = [];
  const host = loc && loc.hostname ? loc.hostname : '';
  const pathParts = (loc && loc.pathname ? loc.pathname : '/').split('/').filter(Boolean);
  const paths = ['/'];
  let acc = '';
  pathParts.forEach(function (p) {
    acc += '/' + p;
    paths.push(acc);
  });
  cookieNames(doc).forEach(function (name) {
    if (keep.indexOf(name) !== -1) return;
    if (!res.some(function (re) { return re.test(name); })) return;
    candidateDomains(host).forEach(function (domain) {
      paths.forEach(function (path) {
        deleteCookie(doc, name, path, domain);
      });
    });
    removed.push(name);
  });
  return removed;
}
