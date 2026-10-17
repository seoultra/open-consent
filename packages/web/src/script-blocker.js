import { warn } from './util.js';
import { isSafeUrl } from './config.js';
import { categoryText, format } from './i18n.js';

/*
 * Declarative blocking. The page ships tracking code inert:
 *
 *   <script type="text/plain" data-open-consent="analytics">...</script>
 *   <script type="text/plain" data-open-consent="marketing" data-src="https://..."></script>
 *   <iframe data-open-consent="marketing" data-src="https://www.youtube-nocookie.com/embed/..."></iframe>
 *
 * A browser never executes type="text/plain", so nothing runs before consent
 * even if this library fails to load. When every listed category is granted,
 * the element is re-created as a real script (once) or the iframe gets its src.
 *
 * - Several categories ("analytics marketing") require ALL of them.
 * - Missing/unknown categories never run.
 * - Executed scripts cannot be "un-run" on withdrawal; iframes are unloaded.
 * - Activations are serialised: a non-async external script finishes loading
 *   before the next blocked element runs, so document order is preserved.
 */

const SELECTOR = 'script[type="text/plain"][data-open-consent], iframe[data-open-consent]';
const SKIP_ATTRS = ['type', 'data-open-consent', 'data-src', 'data-type', 'data-oc-state', 'src'];
const SCRIPT_TYPES = ['module', 'text/javascript', 'application/javascript'];

/** http(s), root-relative, or scheme-less relative URL — never javascript:, data:, etc. */
export function isSafeResourceUrl(v) {
  if (typeof v !== 'string' || v === '' || v.length > 2048) return false;
  return isSafeUrl(v) || /^(?![A-Za-z][A-Za-z0-9+.-]*:)(?!\/\/)[^\s"'<>]+$/.test(v);
}

export function parseCategoryAttr(value, validNames) {
  if (typeof value !== 'string') return null;
  const list = value.trim().split(/\s+/).filter(Boolean);
  if (!list.length) return null;
  for (let i = 0; i < list.length; i++) {
    if (validNames.indexOf(list[i]) === -1) return null;
  }
  return list;
}

export function createScriptBlocker(win, doc, opts) {
  // opts: { categoryNames(): string[], current(): {cat: bool}, texts(): object, grant(cat), emit(name, detail) }
  const done = typeof WeakSet === 'function' ? new WeakSet() : null;
  const placeholders = typeof WeakMap === 'function' ? new WeakMap() : null;
  const programmatic = [];
  let observer = null;
  let queue = Promise.resolve();

  function isDone(el) {
    return done ? done.has(el) : el.getAttribute('data-oc-state') === 'executed';
  }

  function markDone(el) {
    if (done) done.add(el);
    el.setAttribute('data-oc-state', 'executed');
  }

  function allowed(cats) {
    const cur = opts.current();
    return cats.every(function (c) {
      return cur[c] === true;
    });
  }

  function runScript(el) {
    return new Promise(function (resolve) {
      const s = doc.createElement('script');
      for (let i = 0; i < el.attributes.length; i++) {
        const a = el.attributes[i];
        if (SKIP_ATTRS.indexOf(a.name) === -1 && a.name !== 'nonce') s.setAttribute(a.name, a.value);
      }
      const nonce = el.nonce || el.getAttribute('nonce');
      if (nonce) s.nonce = nonce;
      const t = el.getAttribute('data-type');
      if (t && SCRIPT_TYPES.indexOf(t) !== -1) s.type = t;
      s.setAttribute('data-open-consent-executed', el.getAttribute('data-open-consent'));
      const src = el.getAttribute('data-src');
      let waitForLoad = false;
      if (src) {
        if (!isSafeResourceUrl(src)) {
          warn('blocked script has an unsafe data-src and was not executed: ' + src);
          resolve();
          return;
        }
        s.src = src;
        if (el.hasAttribute('async')) {
          s.async = true;
        } else {
          s.async = false;
          waitForLoad = true;
          s.addEventListener('load', function () { resolve(); });
          s.addEventListener('error', function () { resolve(); });
        }
      } else {
        s.text = el.text;
      }
      markDone(el);
      el.parentNode.insertBefore(s, el.nextSibling);
      opts.emit('script:executed', { categories: el.getAttribute('data-open-consent'), src: src || null });
      if (!waitForLoad) resolve();
    });
  }

  function placeholderFor(el, cats) {
    if (el.getAttribute('data-open-consent-placeholder') === 'off' || !placeholders) return;
    if (placeholders.has(el)) {
      placeholders.get(el).hidden = false;
      return;
    }
    const texts = opts.texts();
    const names = cats.map(function (c) { return categoryText(texts, c).title; }).join(', ');
    const box = doc.createElement('div');
    box.className = 'open-consent-placeholder';
    box.setAttribute('role', 'note');
    box.style.cssText = 'display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;padding:16px;text-align:center;border:1px dashed #8c959f;border-radius:8px;background:#f6f8fa;color:#1f2328;font:14px/1.5 system-ui,sans-serif;box-sizing:border-box;';
    const w = el.getAttribute('width');
    const h = el.getAttribute('height');
    if (w && /^\d+$/.test(w)) box.style.maxWidth = w + 'px';
    if (h && /^\d+$/.test(h)) box.style.minHeight = h + 'px';
    const p = doc.createElement('p');
    p.style.margin = '0';
    p.textContent = format(texts.blockedContent, { category: names });
    const b = doc.createElement('button');
    b.type = 'button';
    b.textContent = texts.allowContent;
    b.style.cssText = 'font:inherit;padding:6px 14px;border-radius:6px;border:1px solid #1f2328;background:#fff;color:#1f2328;cursor:pointer;';
    b.addEventListener('click', function () {
      cats.forEach(function (c) { opts.grant(c); });
    });
    box.appendChild(p);
    box.appendChild(b);
    el.parentNode.insertBefore(box, el);
    placeholders.set(el, box);
  }

  function processIframe(el, cats) {
    const src = el.getAttribute('data-src');
    if (!isSafeResourceUrl(src)) return;
    if (allowed(cats)) {
      if (el.getAttribute('src') !== src) el.setAttribute('src', src);
      el.hidden = false;
      el.setAttribute('data-oc-state', 'loaded');
      if (placeholders && placeholders.has(el)) placeholders.get(el).hidden = true;
    } else {
      if (el.getAttribute('src') && el.getAttribute('src') !== 'about:blank') el.setAttribute('src', 'about:blank');
      el.hidden = true;
      el.setAttribute('data-oc-state', 'blocked');
      placeholderFor(el, cats);
    }
  }

  function processElement(el) {
    const cats = parseCategoryAttr(el.getAttribute('data-open-consent'), opts.categoryNames());
    if (!cats) {
      if (el.getAttribute('data-oc-state') !== 'invalid') {
        el.setAttribute('data-oc-state', 'invalid');
        warn('element with missing/unknown data-open-consent category will never run: "' + el.getAttribute('data-open-consent') + '"');
      }
      return;
    }
    if (el.tagName === 'IFRAME') {
      processIframe(el, cats);
      return;
    }
    if (el.getAttribute('type') !== 'text/plain' || isDone(el)) return;
    if (!allowed(cats)) {
      el.setAttribute('data-oc-state', 'blocked');
      return;
    }
    el.setAttribute('data-oc-state', 'queued');
    if (done) done.add(el);
    queue = queue.then(function () {
      return runScript(el);
    });
  }

  function scan(root) {
    const scope = root || doc;
    if (scope.nodeType === 1 && scope.matches && scope.matches(SELECTOR)) processElement(scope);
    if (scope.querySelectorAll) Array.prototype.forEach.call(scope.querySelectorAll(SELECTOR), processElement);
  }

  function runProgrammatic() {
    programmatic.forEach(function (entry) {
      if (entry.ran || !allowed(entry.categories)) return;
      entry.ran = true;
      try {
        entry.run();
      } catch (e) {
        if (typeof console !== 'undefined') console.error('[OpenConsent] whenAllowed callback failed', e);
      }
    });
  }

  function refresh() {
    scan(doc);
    runProgrammatic();
    return queue;
  }

  function observe() {
    if (observer || typeof MutationObserver !== 'function') return;
    observer = new MutationObserver(function (records) {
      records.forEach(function (r) {
        Array.prototype.forEach.call(r.addedNodes, function (n) {
          if (n.nodeType === 1) scan(n);
        });
      });
    });
    observer.observe(doc.documentElement, { childList: true, subtree: true });
  }

  /** Run `fn` once, as soon as every category in `categories` is granted. */
  function whenAllowed(categories, fn) {
    const list = Array.isArray(categories) ? categories : [categories];
    const valid = parseCategoryAttr(list.join(' '), opts.categoryNames());
    if (!valid || typeof fn !== 'function') {
      warn('whenAllowed: unknown category, callback will never run');
      return false;
    }
    programmatic.push({ categories: valid, run: fn, ran: false });
    runProgrammatic();
    return true;
  }

  function pending() {
    return queue;
  }

  return { refresh: refresh, observe: observe, whenAllowed: whenAllowed, pending: pending };
}
