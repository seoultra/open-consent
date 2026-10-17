/**
 * Minimal event emitter. Listener errors are isolated so one broken
 * integration can never stop consent from being applied elsewhere.
 */
export function createEmitter(target) {
  const listeners = new Map();

  function on(name, callback) {
    if (typeof callback !== 'function') return function noop() {};
    if (!listeners.has(name)) listeners.set(name, new Set());
    listeners.get(name).add(callback);
    return function off() {
      const set = listeners.get(name);
      if (set) set.delete(callback);
    };
  }

  function emit(name, detail) {
    const set = listeners.get(name);
    if (set) {
      Array.from(set).forEach(function (cb) {
        try {
          cb(detail);
        } catch (err) {
          if (typeof console !== 'undefined') console.error('[OpenConsent] listener for "' + name + '" failed', err);
        }
      });
    }
    // DOM bridge: document.addEventListener('openconsent:consent', ...)
    if (target && typeof target.dispatchEvent === 'function' && typeof CustomEvent === 'function') {
      try {
        target.dispatchEvent(new CustomEvent('openconsent:' + name, { detail: detail }));
      } catch (err) {
        /* non-fatal */
      }
    }
  }

  function clear() {
    listeners.clear();
  }

  return { on: on, emit: emit, clear: clear };
}
