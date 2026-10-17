/*
 * Tiny element builder. Text is always assigned with textContent and
 * attributes with setAttribute — no HTML string is ever parsed, so
 * translated or configured copy cannot inject markup.
 */
export function h(doc, tag, attrs, children) {
  const el = doc.createElement(tag);
  const a = attrs || {};
  Object.keys(a).forEach(function (k) {
    const v = a[k];
    if (v === null || v === undefined || v === false) return;
    if (k === 'text') el.textContent = v;
    else if (k === 'className') el.className = v;
    else if (k.slice(0, 2) === 'on' && typeof v === 'function') el.addEventListener(k.slice(2), v);
    else el.setAttribute(k, v === true ? '' : String(v));
  });
  (children || []).forEach(function (c) {
    if (c === null || c === undefined || c === false) return;
    el.appendChild(typeof c === 'string' ? doc.createTextNode(c) : c);
  });
  return el;
}

let uidCounter = 0;
export function uid(prefix) {
  uidCounter += 1;
  return prefix + '-' + uidCounter;
}
