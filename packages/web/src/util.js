export function isPlainObject(v) {
  return v !== null && typeof v === 'object' && !Array.isArray(v);
}

export function hasOwn(obj, key) {
  return Object.prototype.hasOwnProperty.call(obj, key);
}

export function deepMerge(base, extra) {
  const out = {};
  Object.keys(base || {}).forEach(function (k) {
    out[k] = base[k];
  });
  Object.keys(extra || {}).forEach(function (k) {
    const v = extra[k];
    if (isPlainObject(v) && isPlainObject(out[k])) {
      out[k] = deepMerge(out[k], v);
    } else if (v !== undefined) {
      out[k] = v;
    }
  });
  return out;
}

export function clone(v) {
  return v === undefined ? v : JSON.parse(JSON.stringify(v));
}

export function nowSeconds() {
  return Math.floor(Date.now() / 1000);
}

export function warn(message) {
  if (typeof console !== 'undefined' && console.warn) console.warn('[OpenConsent] ' + message);
}
