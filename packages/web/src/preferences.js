import { h, uid } from './dom.js';
import { categoryText } from './i18n.js';
import { trapFocus } from './accessibility.js';

export function createPreferences(doc, cfg, texts, actions) {
  const t = texts.preferences;
  const titleId = uid('oc-pref-title');
  const textId = uid('oc-pref-text');
  const inputs = {};
  let release = null;

  const cats = Object.keys(cfg.categories).map(function (name) {
    const ct = categoryText(texts, name);
    const headId = uid('oc-cat');
    const descId = uid('oc-cat-desc');
    let control;
    if (cfg.categories[name].required) {
      control = h(doc, 'span', { className: 'oc-always', text: t.alwaysActive });
    } else {
      const state = h(doc, 'span', { className: 'oc-state', 'aria-hidden': 'true', text: t.off });
      const input = h(doc, 'input', {
        type: 'checkbox',
        role: 'switch',
        name: name,
        'data-oc-category': name,
        'aria-labelledby': headId,
        'aria-describedby': descId,
        onchange: function () {
          state.textContent = input.checked ? t.on : t.off;
        }
      });
      inputs[name] = { input: input, state: state };
      control = h(doc, 'label', { className: 'oc-switch' }, [
        input,
        h(doc, 'span', { className: 'oc-track', 'aria-hidden': 'true' }),
        state,
        h(doc, 'span', { className: 'oc-sr', text: ct.title })
      ]);
    }
    return h(doc, 'div', { className: 'oc-cat', 'data-oc-cat': name }, [
      h(doc, 'div', { className: 'oc-cat-head' }, [h(doc, 'h3', { className: 'oc-cat-title', id: headId, text: ct.title }), control]),
      ct.description ? h(doc, 'p', { className: 'oc-cat-desc', id: descId, text: ct.description }) : null
    ]);
  });

  function collect() {
    const out = {};
    Object.keys(inputs).forEach(function (n) {
      out[n] = inputs[n].input.checked;
    });
    return out;
  }

  const modal = h(doc, 'div', {
    className: 'oc-modal',
    role: 'dialog',
    'aria-modal': 'true',
    'aria-labelledby': titleId,
    'aria-describedby': textId,
    tabindex: '-1',
    'data-oc': 'preferences'
  }, [
    h(doc, 'div', { className: 'oc-modal-head' }, [
      h(doc, 'h2', { className: 'oc-title', id: titleId, text: t.title }),
      h(doc, 'button', { type: 'button', className: 'oc-close', 'aria-label': t.close, 'data-oc-action': 'close', text: '×', onclick: actions.close })
    ]),
    h(doc, 'div', { className: 'oc-modal-body' }, [h(doc, 'p', { className: 'oc-text', id: textId, text: t.text })].concat(cats)),
    h(doc, 'div', { className: 'oc-modal-foot' }, [
      h(doc, 'div', { className: 'oc-actions' }, [
        h(doc, 'button', { type: 'button', className: 'oc-btn oc-btn-choice', 'data-oc-action': 'reject-all', text: t.rejectAll, onclick: actions.rejectAll }),
        h(doc, 'button', { type: 'button', className: 'oc-btn oc-btn-choice', 'data-oc-action': 'accept-all', text: t.acceptAll, onclick: actions.acceptAll }),
        h(doc, 'button', { type: 'button', className: 'oc-btn oc-btn-secondary', 'data-oc-action': 'save', text: t.save, onclick: function () { actions.save(collect()); } })
      ])
    ])
  ]);

  const overlay = h(doc, 'div', {
    className: 'oc-overlay',
    'data-oc': 'overlay',
    hidden: true,
    onclick: function (e) {
      if (e.target === overlay) actions.close();
    }
  }, [modal]);

  return {
    el: overlay,
    show: function (current) {
      Object.keys(inputs).forEach(function (n) {
        inputs[n].input.checked = current[n] === true;
        inputs[n].state.textContent = inputs[n].input.checked ? t.on : t.off;
      });
      overlay.hidden = false;
      if (release) release();
      release = trapFocus(modal, actions.close);
      modal.focus({ preventScroll: true });
    },
    hide: function () {
      overlay.hidden = true;
      if (release) release();
      release = null;
    },
    visible: function () {
      return !overlay.hidden;
    }
  };
}
