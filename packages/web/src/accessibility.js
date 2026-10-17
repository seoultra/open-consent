const FOCUSABLE = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

export function focusableIn(container) {
  return Array.prototype.filter.call(container.querySelectorAll(FOCUSABLE), function (el) {
    return !el.hidden && !el.closest('[hidden]');
  });
}

function activeIn(container) {
  const root = container.getRootNode ? container.getRootNode() : null;
  return root && root.activeElement ? root.activeElement : null;
}

/** Keep Tab/Shift+Tab inside `container`; Escape calls onEscape. Returns a release function. */
export function trapFocus(container, onEscape) {
  function onKey(e) {
    if (e.key === 'Escape' || e.key === 'Esc') {
      if (onEscape) {
        e.preventDefault();
        e.stopPropagation();
        onEscape();
      }
      return;
    }
    if (e.key !== 'Tab') return;
    const items = focusableIn(container);
    if (!items.length) {
      e.preventDefault();
      return;
    }
    const first = items[0];
    const last = items[items.length - 1];
    const cur = activeIn(container);
    if (e.shiftKey && (cur === first || cur === container || !container.contains(cur))) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && (cur === last || !container.contains(cur))) {
      e.preventDefault();
      first.focus();
    }
  }
  container.addEventListener('keydown', onKey);
  return function release() {
    container.removeEventListener('keydown', onKey);
  };
}

/** Remember the page element that had focus, to restore it after a dialog closes. */
export function rememberFocus(doc) {
  const prev = doc.activeElement;
  return function restore() {
    if (prev && typeof prev.focus === 'function' && doc.contains(prev) && prev !== doc.body) {
      try {
        prev.focus({ preventScroll: true });
      } catch (e) {
        prev.focus();
      }
    }
  };
}
