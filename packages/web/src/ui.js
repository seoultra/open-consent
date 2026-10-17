import { CSS } from './generated/defaults.js';
import { h } from './dom.js';
import { createBanner } from './banner.js';
import { createPreferences } from './preferences.js';
import { rememberFocus } from './accessibility.js';

const THEME_VARS = {
  background: '--oc-bg',
  text: '--oc-text',
  muted: '--oc-muted',
  accent: '--oc-accent',
  accentText: '--oc-accent-text',
  border: '--oc-border',
  radius: '--oc-radius',
  fontFamily: '--oc-font'
};

const COOKIE_ICON = 'M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5zm-3.5 7a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm6 6a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm-7 1a1 1 0 1 1 0 2 1 1 0 0 1 0-2z';

/**
 * Mounts the widget in a shadow root on <body>. `actions` comes from the
 * controller: { acceptAll, rejectAll, save(prefs), current() }.
 */
export function createUI(doc, cfg, texts, actions) {
  const host = h(doc, 'div', { id: 'open-consent', 'data-open-consent-ui': '' });
  const dir = doc.documentElement.getAttribute('dir') || (doc.body && doc.body.getAttribute('dir'));
  if (dir === 'rtl') host.setAttribute('dir', 'rtl');
  const theme = cfg.ui.theme;
  Object.keys(THEME_VARS).forEach(function (k) {
    if (k === 'fontFamily' && theme[k] === 'inherit') return;
    host.style.setProperty(THEME_VARS[k], theme[k]);
  });
  host.style.setProperty('--oc-z', String(cfg.ui.zIndex));

  const shadow = host.attachShadow({ mode: 'open' });
  // Constructable stylesheets are not subject to CSP style-src 'unsafe-inline'.
  let adopted = false;
  try {
    const Sheet = doc.defaultView && doc.defaultView.CSSStyleSheet;
    if (Sheet && 'adoptedStyleSheets' in shadow) {
      const sheet = new Sheet();
      sheet.replaceSync(CSS);
      shadow.adoptedStyleSheets = [sheet];
      adopted = true;
    }
  } catch (e) {
    adopted = false;
  }
  if (!adopted) {
    const style = doc.createElement('style');
    style.textContent = CSS;
    shadow.appendChild(style);
  }
  const root = h(doc, 'div', { className: 'oc-root', lang: texts.__lang || null });
  shadow.appendChild(root);

  let restoreBannerFocus = null;
  let restorePrefFocus = null;
  let prefsFromBanner = false;

  const banner = createBanner(doc, cfg, texts, {
    acceptAll: actions.acceptAll,
    rejectAll: actions.rejectAll,
    customize: function () {
      openPreferences(true);
    }
  });

  const prefs = createPreferences(doc, cfg, texts, {
    acceptAll: actions.acceptAll,
    rejectAll: actions.rejectAll,
    save: actions.save,
    close: function () {
      closePreferences(true);
    }
  });

  const reopen = cfg.ui.reopenButton
    ? h(doc, 'button', {
        type: 'button',
        className: 'oc-reopen oc-reopen-' + cfg.ui.reopenPosition,
        'aria-label': texts.reopen,
        title: texts.reopen,
        'data-oc': 'reopen',
        hidden: true,
        onclick: function () {
          openPreferences(false);
        }
      })
    : null;
  if (reopen) {
    const svgNs = 'http://www.w3.org/2000/svg';
    const svg = doc.createElementNS(svgNs, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('focusable', 'false');
    const path = doc.createElementNS(svgNs, 'path');
    path.setAttribute('fill', 'currentColor');
    path.setAttribute('d', COOKIE_ICON);
    svg.appendChild(path);
    reopen.appendChild(svg);
  }

  root.appendChild(banner.el);
  root.appendChild(prefs.el);
  if (reopen) root.appendChild(reopen);
  doc.body.appendChild(host);

  function showBanner() {
    if (banner.visible()) return;
    restoreBannerFocus = rememberFocus(doc);
    banner.show(true);
    if (reopen) reopen.hidden = true;
    actions.emit('banner:show');
  }

  function hideBanner() {
    if (!banner.visible()) return;
    banner.hide();
    actions.emit('banner:hide');
  }

  function openPreferences(fromBanner) {
    prefsFromBanner = !!fromBanner && banner.visible();
    restorePrefFocus = prefsFromBanner ? null : rememberFocus(doc);
    if (reopen) reopen.hidden = true;
    prefs.show(actions.current());
    actions.emit('preferences:show');
  }

  function closePreferences(cancelled) {
    if (!prefs.visible()) return;
    prefs.hide();
    actions.emit('preferences:hide', { cancelled: !!cancelled });
    if (cancelled && prefsFromBanner && banner.visible()) {
      const b = banner.el.querySelector('[data-oc-action="customize"]');
      if (b) b.focus();
    } else if (restorePrefFocus) {
      restorePrefFocus();
    }
    syncReopen();
  }

  /** Called by the controller after a choice is saved. */
  function settled() {
    const hadBanner = banner.visible();
    closePreferences(false);
    hideBanner();
    if (hadBanner && restoreBannerFocus) restoreBannerFocus();
    syncReopen();
  }

  function syncReopen() {
    if (reopen) reopen.hidden = banner.visible() || prefs.visible() || !actions.hasDecision();
  }

  return {
    host: host,
    showBanner: showBanner,
    hideBanner: hideBanner,
    openPreferences: openPreferences,
    closePreferences: closePreferences,
    settled: settled,
    syncReopen: syncReopen,
    bannerVisible: banner.visible,
    preferencesVisible: prefs.visible
  };
}
