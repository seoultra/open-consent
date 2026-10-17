# Security

## Threat model

| Threat | Mitigation |
|---|---|
| XSS through configured or translated text | All text is set with `textContent`; no HTML string is ever parsed by the engine. Tested with an `<img onerror>` payload. |
| CSS injection through theme values | Colours, radius and font validated by strict patterns; set via `style.setProperty` inside a shadow root. |
| `javascript:` / `data:` URLs in links or blocked `data-src` | Only `http(s)` and relative URLs are accepted (JS and PHP validators share a fixture). |
| Remote config executing code | Config is data-only; unknown keys are dropped; blocked scripts must be markup on the page. The engine never `eval`s or injects code from config. |
| Malicious or broken hosted config | Strict validation server-side (not served if invalid) and client-side (same validator as local); fallback to local config. |
| Tracking before consent | Gated code is `type="text/plain"` — inert even if the engine fails to load. Missing/corrupt/expired records are never consent. Only `necessary` can be required. |
| Consent cookie tampering | Possible by design (client-side). Never use it for authorization. The PHP reader validates structure and size (≤ 4 KB). |
| Script breakout in server-rendered config | `Config::toJson()` uses `JSON_HEX_TAG|AMP|APOS|QUOT`; `Frontend::blockedScript()` refuses `</script`; attribute names are whitelisted (no `on*`). |
| WordPress privilege issues | Settings API (nonce + `manage_options`); raw script snippets only from users with `unfiltered_html`; all output escaped; settings sanitised by whitelist. |
| CORS abuse of the config API | Per-site origin allow-list, no credentials, GET only, rate limiting hook. |
| Supply chain | No runtime dependencies in the browser bundle or PHP library. Dev dependencies: PHPUnit, PHPStan. SRI hash published per build. |

## Content-Security-Policy

Works under `script-src 'self' 'nonce-…'; style-src 'self'` (tested):

- styles use constructable stylesheets (with a `<style>` fallback for old browsers);
- give the engine `<script>` your nonce; blocked inline scripts that carry the nonce
  keep it when executed;
- Google tags need `https://www.googletagmanager.com` (and their own hosts) in
  `script-src`/`connect-src`/`img-src` per Google's CSP guide.

## Reporting

See [../SECURITY.md](../SECURITY.md).
