# Hosted widget (optional)

The hosted mode lets many sites share one script URL and fetch their configuration
from an endpoint you operate. **It is optional**: the engine never needs it, and there
is no OpenConsent cloud service — you host both parts.

```html
<script src="https://cdn.example.com/open-consent/0.1.0/open-consent.js"
        integrity="sha384-…" crossorigin="anonymous"
        data-site-id="shop-example"
        data-config-url="https://consent-api.example.com/config-api/index.php?site={site}"
        data-google-consent="on"></script>
```

| Attribute | Meaning |
|---|---|
| `data-site-id` | public, non-secret id: `^[a-z0-9][a-z0-9_-]{2,63}$` |
| `data-config-url` | optional; `{site}` is replaced. Default: `config/<site-id>.json` next to the script. Must be HTTPS (or same-origin / localhost) |
| `data-google-consent="on"` | send an all-denied Consent Mode default *before* the config request |

Flow: early restrictive Google default (if requested) → `fetch` with
`credentials: 'omit'`, 4 s timeout → the response goes through the same validator as
local config (unknown keys dropped, no code possible) → `init()`. On any failure
(network, non-200, invalid JSON, `siteId` mismatch) it initialises from the local
fallback (`#open-consent-config` / `window.OpenConsentConfig`) or from restrictive
built-in defaults. Visitors' choices are stored only in their browser.

## Config endpoint

`hosted/config-api/index.php` is a front controller for
`SeoUltra\OpenConsent\Hosted\ConfigEndpoint` (part of the Composer package).

Site file `sites/<site-id>.json`:

```json
{ "allowedOrigins": ["https://www.shop-example.com"],
  "config": { "policyVersion": "3", "links": { "privacyPolicy": "https://www.shop-example.com/privacy" } } }
```

Behaviour (unit-tested):

- `GET`/`HEAD`/`OPTIONS` only; site id validated (no path traversal).
- The config is validated **strictly**: a site file with any invalid value is not served
  (500 `site_config_invalid`), so the embed falls back rather than running a half-valid config.
- CORS: `Access-Control-Allow-Origin` echoes only origins in `allowedOrigins`; other
  origins get 403. No credentials are accepted.
- `Cache-Control: public, max-age=300, stale-while-revalidate=600` + `ETag`/304.
- Optional file-based per-IP limiter (`OPEN_CONSENT_RATE_DIR`); prefer nginx
  `limit_req` or your CDN.
- No visitor data is logged or stored by the endpoint (your web server's access log is
  your responsibility).

Environment: `OPEN_CONSENT_SITES_DIR`, `OPEN_CONSENT_RATE_DIR`, `OPEN_CONSENT_AUTOLOAD`.

## Operating it

- **HTTPS only**, HSTS on both hosts.
- **Versioned, immutable script URLs** (`/open-consent/0.1.0/open-consent.js`,
  `Cache-Control: public, max-age=31536000, immutable`) plus SRI; never overwrite a
  published version. Config responses stay short-lived (5 min) so changes propagate.
- **Cache invalidation**: changing a site file changes the ETag; CDNs serve the new
  config after `max-age`. Purge only if you need it faster.
- **Rate limiting** at the edge; the endpoint is cheap but public.
- **Backups**: the `sites/` directory is the only state. Keep it in version control.
- **Availability**: if the endpoint is down, sites fall back to their local config — keep
  a sensible local config on every site that uses the embed.
- Keep `sites/` outside the document root or deny direct access (an `.htaccess` is
  included for Apache; for nginx add a `location` deny rule).

## Dashboard

Not implemented. Site files are edited by hand (or by your own tooling). A dashboard
would be a separate, optional application and must never be required for consent
enforcement.
