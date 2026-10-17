# OpenConsent

Free, open-source cookie consent for any website, WordPress, and PHP applications —
with **real script blocking** and **Google Consent Mode v2**. No account, no API key,
no remote calls, no telemetry. GPL-2.0-or-later.

- Composer: `composer require seoultra/open-consent` — https://packagist.org/packages/seoultra/open-consent
- Source: https://github.com/seoultra/open-consent
- Project page: https://seoultra.id/plugins/open-consent/

> OpenConsent is a technical tool. It does not by itself make a site compliant with
> GDPR, ePrivacy, CCPA or any other law.

## What works today (v0.1.0)

| Feature | Status |
|---|---|
| Banner: Accept all / Reject all (equal prominence) / Customize; preferences dialog; reopen button; withdraw | ✅ tested (unit + browser) |
| Consent record in a first-party cookie, validation, expiry, policy versioning, schema migration hook | ✅ |
| Opt-in and opt-out profiles, Global Privacy Control | ✅ |
| Script blocking: `type="text/plain" data-open-consent`, external + inline + module scripts, iframes with placeholder, ordering, `whenAllowed`, `loadScript` | ✅ |
| Cookie cleanup on revoke (first-party, JS-visible) | ✅ |
| Google Consent Mode v2: defaults before tags, updates, Basic/Advanced, tag loading, conflict detection, GTM event | ✅ |
| Accessibility: dialog semantics, focus management + trap, keyboard, reduced motion, forced colours, RTL, 44 px targets | ✅ tested in Chromium; no screen-reader audit yet |
| Strict CSP (nonce, no `unsafe-inline`) | ✅ tested |
| 125 widget languages (en + id built in; others via `texts`, auto-injected by WordPress/PHP) | ✅ machine-assisted, review welcome |
| WordPress plugin (settings, handle gating, snippets, shortcode, WP Consent API, Site Kit notes) | ✅ tested on WP 7.1.3 |
| PHP library (config validation, cookie reader, markup helpers) | ✅ PHPUnit + PHPStan level 8 |
| Hosted embed + config API | ✅ tested locally; you host it yourself |
| Hosted dashboard, IAB TCF, cookie scanner, geolocation | ❌ not implemented — see [limitations](docs/limitations.md) |

## Quick start (any website)

```html
<head>
  <script type="application/json" id="open-consent-config">
    { "links": { "privacyPolicy": "/privacy/" },
      "google": { "enabled": true, "tags": ["G-XXXXXXXXXX"] } }
  </script>
  <script src="/js/open-consent.js"></script>   <!-- dist/open-consent.js, before other tags -->

  <script type="text/plain" data-open-consent="marketing">
    /* e.g. an ad pixel — runs only after "marketing" is allowed */
  </script>
</head>
<body>
  …
  <a href="#open-consent">Cookie settings</a>
</body>
```

More: [installation](docs/installation.md) · [consent model](docs/consent-model.md) ·
[script blocking](docs/script-blocking.md) · [Google Consent Mode](docs/google-consent-mode.md) ·
[WordPress](docs/wordpress.md) · [hosted widget](docs/hosted-widget.md) ·
[API](docs/api.md) · [security](docs/security.md) · [accessibility](docs/accessibility.md) ·
[limitations](docs/limitations.md) · [architecture](docs/architecture.md)

## PHP

```php
use SeoUltra\OpenConsent\{Config, ConsentCookie, Frontend, Paths};

$config = Config::fromArray(['policyVersion' => '2', 'links' => ['privacyPolicy' => '/privacy/']]);
echo Frontend::head($config, '/assets/open-consent.js', Paths::browserBundleIntegrity());

$consent = ConsentCookie::read($config);          // from $_COOKIE
if ($consent->allows('marketing')) { /* render the ad slot */ }
```

## Repository layout

```
packages/web/src/        browser engine (ES modules) — the ONLY consent engine
packages/web/styles/     widget CSS (rendered in a shadow root)
packages/web/tests/      JS unit tests (run in Chromium)
packages/core-php/       PHP library (PSR-4: SeoUltra\OpenConsent\) + PHPUnit tests
integrations/wordpress/  WordPress plugin (+ its tests)
hosted/config-api/       optional config endpoint for the embed
config/                  default policy + translations (single source for JS and PHP)
dist/                    built bundle + SRI hash (committed)
examples/                standalone HTML, Composer, WordPress
tests/                   e2e scenarios, shared fixtures, test runner
bin/                     build.php (Node-free bundler), package-wordpress.sh
docs/
```

## Development

Requirements: PHP ≥ 8.1, Composer; for browser tests Python 3 + Playwright with Chromium.
No Node.js is needed.

```sh
composer install
php bin/build.php                       # bundle dist/open-consent.js (+ WP plugin copy)
composer check                          # build freshness, PHPUnit, PHPStan
python3 tests/run_browser_tests.py      # JS unit tests + e2e scenarios in headless Chromium
bin/package-wordpress.sh                # build/open-consent-wordpress-<version>.zip
```

See [CONTRIBUTING.md](CONTRIBUTING.md). Security reports: [SECURITY.md](SECURITY.md).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE). Not affiliated with Complianz or Google;
no code, text or assets from Complianz are used.
