# Installation

Pick one. All three use the same browser engine (`dist/open-consent.js`).

## 1. Any website (standalone)

Copy `dist/open-consent.js` to your server and add this to `<head>`, **before any
analytics or advertising tag**:

```html
<script type="application/json" id="open-consent-config">
{
  "policyVersion": "1",
  "links": { "privacyPolicy": "/privacy/", "cookiePolicy": "/cookies/" },
  "google": { "enabled": true, "mode": "basic", "tags": ["G-XXXXXXXXXX"] }
}
</script>
<script src="/js/open-consent.js"></script>
```

- Load it **synchronously** (no `async`/`defer`) so Consent Mode defaults exist before
  Google tags run, and so blocked scripts are recognised while the page is parsed.
- Turn every non-essential tag into a blocked one — see [script-blocking.md](script-blocking.md):
  ```html
  <script type="text/plain" data-open-consent="analytics">/* your analytics code */</script>
  ```
- Add a way to reopen the preferences (the floating button is on by default):
  ```html
  <a href="#open-consent">Cookie settings</a>   <!-- or any element with data-open-consent-open -->
  ```
- Subresource Integrity: `dist/open-consent.js.sri` holds the `sha384-…` hash of each build.

A complete page: [examples/standalone-html/index.html](../examples/standalone-html/index.html).

Instead of the JSON element you can set `window.OpenConsentConfig = {...}` before the
script, or call `OpenConsent.init({...})` yourself (only the first call counts).

## 2. WordPress

1. Download the plugin zip (or copy `integrations/wordpress/open-consent/` to
   `wp-content/plugins/open-consent/`).
2. Activate **OpenConsent**. The banner appears immediately with restrictive defaults.
3. Settings → OpenConsent. See [wordpress.md](wordpress.md).

## 3. Composer (PHP applications)

Once the package is published on Packagist:

```sh
composer require seoultra/open-consent
```

Until then, install from a local checkout or a VCS repository:

```json
{
  "repositories": [{ "type": "vcs", "url": "https://github.com/<owner>/open-consent" }],
  "require": { "seoultra/open-consent": "^0.1" }
}
```

Then expose `vendor/seoultra/open-consent/dist/open-consent.js` from your public
directory (copy or symlink it in your deploy step) and render the head tags:

```php
use SeoUltra\OpenConsent\{Config, Frontend, ConsentCookie, Paths};

$config = Config::fromArray(['links' => ['privacyPolicy' => '/privacy/']]);
echo Frontend::head($config, '/assets/open-consent.js', Paths::browserBundleIntegrity());
```

See [examples/composer/](../examples/composer/) and the [API reference](api.md#php).

## 4. Hosted embed (optional)

```html
<script src="https://YOUR-CDN/open-consent/0.1.0/open-consent.js"
        data-site-id="your-site" data-config-url="https://YOUR-API/config-api/index.php?site={site}"></script>
```

You host both URLs yourself; see [hosted-widget.md](hosted-widget.md). There is no
public OpenConsent SaaS.
