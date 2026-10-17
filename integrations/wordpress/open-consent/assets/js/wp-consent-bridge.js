/* OpenConsent → WP Consent API bridge. Maps OpenConsent categories to the
   WP Consent API categories and calls wp_set_consent() on every change. */
(function () {
  'use strict';
  var MAP = {
    functional: ['functional', 'preferences'],
    analytics: ['statistics', 'statistics-anonymous'],
    marketing: ['marketing']
  };
  function sync(categories) {
    if (typeof window.wp_set_consent !== 'function' || !categories) return;
    Object.keys(MAP).forEach(function (cat) {
      MAP[cat].forEach(function (wpCat) {
        window.wp_set_consent(wpCat, categories[cat] === true ? 'allow' : 'deny');
      });
    });
  }
  function current() {
    if (window.OpenConsent && window.OpenConsent.isInitialized()) sync(window.OpenConsent.getConsent().categories);
  }
  if (window.OpenConsent && window.OpenConsent.isInitialized()) {
    var p = window.OpenConsent.getConfig().profile;
    window.wp_consent_type = p === 'opt-out' ? 'optout' : 'optin';
  }
  document.addEventListener('openconsent:ready', function (e) { sync(e.detail.categories); });
  document.addEventListener('openconsent:consent', function (e) { sync(e.detail.categories); });
  document.addEventListener('openconsent:reset', function (e) { sync(e.detail.categories); });
  current();
})();
