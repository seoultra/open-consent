import { h, uid } from './dom.js';

export function createBanner(doc, cfg, texts, actions) {
  const titleId = uid('oc-banner-title');
  const textId = uid('oc-banner-text');
  const t = texts.banner;

  const links = [];
  if (cfg.links.privacyPolicy) links.push(h(doc, 'a', { href: cfg.links.privacyPolicy, text: t.privacyPolicy }));
  if (cfg.links.cookiePolicy) links.push(h(doc, 'a', { href: cfg.links.cookiePolicy, text: t.cookiePolicy }));

  const btnAccept = h(doc, 'button', { type: 'button', className: 'oc-btn oc-btn-choice', 'data-oc-action': 'accept-all', text: t.acceptAll, onclick: actions.acceptAll });
  const btnReject = h(doc, 'button', { type: 'button', className: 'oc-btn oc-btn-choice', 'data-oc-action': 'reject-all', text: t.rejectAll, onclick: actions.rejectAll });
  const btnCustom = h(doc, 'button', { type: 'button', className: 'oc-btn oc-btn-secondary', 'data-oc-action': 'customize', 'aria-haspopup': 'dialog', text: t.customize, onclick: actions.customize });

  const el = h(doc, 'div', {
    className: 'oc-banner oc-layout-' + cfg.ui.layout + ' oc-pos-' + cfg.ui.position,
    role: 'dialog',
    'aria-modal': 'false',
    'aria-labelledby': titleId,
    'aria-describedby': textId,
    tabindex: '-1',
    'data-oc': 'banner',
    hidden: true
  }, [
    h(doc, 'div', { className: 'oc-inner' }, [
      h(doc, 'div', { className: 'oc-copy' }, [
        h(doc, 'h2', { className: 'oc-title', id: titleId, text: t.title }),
        h(doc, 'p', { className: 'oc-text', id: textId, text: t.text }),
        links.length ? h(doc, 'p', { className: 'oc-links' }, links) : null
      ]),
      // Reject sits before Accept in DOM/tab order so it is never the harder one to reach.
      h(doc, 'div', { className: 'oc-actions' }, [btnReject, btnAccept, btnCustom])
    ])
  ]);

  return {
    el: el,
    show: function (focus) {
      el.hidden = false;
      if (focus) el.focus({ preventScroll: true });
    },
    hide: function () {
      el.hidden = true;
    },
    visible: function () {
      return !el.hidden;
    }
  };
}
