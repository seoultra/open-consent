# Google Consent Mode v2

Enable with `"google": { "enabled": true }`. Off by default.

## Signal mapping

| OpenConsent category | Consent Mode types |
|---|---|
| `analytics` | `analytics_storage` |
| `marketing` | `ad_storage`, `ad_user_data`, `ad_personalization` |
| `functional` | `functionality_storage`, `personalization_storage` |
| (always) | `security_storage: granted` |

## What happens, in order

1. When the engine script is evaluated (synchronously, in `<head>`), it sends
   `gtag('consent', 'default', {...})` with the **implied** state of the profile
   (opt-in: everything denied), plus `wait_for_update` (default 500 ms) and `region`
   if configured, and `ads_data_redaction` / `url_passthrough` if enabled.
2. If the visitor already has a valid record, `gtag('consent', 'update', {...})`
   follows immediately.
3. Configured tags (`google.tags`) are loaded according to the mode (below).
4. Every later choice sends an `update`, and pushes
   `{ event: 'open_consent_update', open_consent: { categories, method } }` to the
   `dataLayer` for Google Tag Manager triggers.

The e2e suite asserts that the `default` command is the first `dataLayer` entry and
that gtag.js sees it before any `config` command.

## Basic vs Advanced

| | Basic (default) | Advanced |
|---|---|---|
| gtag.js for `google.tags` | injected only once the tag's category is granted | injected right after the default |
| Before consent | Google receives nothing from those tags | tags run in consent-aware mode and may send **cookieless pings** |
| Modelling in GA4/Ads | limited | available (Google's feature, not ours) |

Advanced mode is **not** "blocking": with consent denied, Google tags still make network
requests without cookies. Only use it if that is acceptable for you.

## Configuring tags

```json
"google": {
  "enabled": true,
  "mode": "basic",
  "tags": ["G-XXXXXXX", "AW-1234567", { "id": "GT-ABCDEFG", "category": "marketing" }]
}
```

`G-` defaults to `analytics`, `AW-`/`DC-` to `marketing`; `GT-` needs an explicit
category. The engine injects `https://www.googletagmanager.com/gtag/js?id=…` once and
calls `gtag('js')` once and `gtag('config', id)` once per tag.

## Tags you install yourself

- **gtag.js snippet in your HTML**: place it *after* the OpenConsent script. In Basic
  mode, make it a blocked script so it does not load before consent:
  ```html
  <script type="text/plain" data-open-consent="analytics" data-src="https://www.googletagmanager.com/gtag/js?id=G-XXX"></script>
  <script type="text/plain" data-open-consent="analytics">gtag('js', new Date()); gtag('config', 'G-XXX');</script>
  ```
  (OpenConsent has already defined `window.gtag` and `dataLayer`.)
- **Google Tag Manager**: load the GTM container after OpenConsent. Consent Mode
  defaults are already in the `dataLayer`; tags with built-in consent checks honour
  them, and you can use the `open_consent_update` event as a trigger. Do not also enable
  a CMP template inside GTM.
- **Site Kit (WordPress)**: see [wordpress.md](wordpress.md#site-kit).

## Coexisting with another consent platform

If a `consent default` command is already in the `dataLayer` when OpenConsent starts,
it sends **no** Google signals and loads no Google tags (it emits `google:conflict` and
logs a warning). Set `google.overrideExisting: true` only if you have deliberately
removed the other platform's Google integration. A platform that sends its default
*after* OpenConsent cannot be detected — run only one consent platform.

## What this does not do

It does not guarantee conversion measurement, modelling, or recovery of "lost"
conversions, and it does not make a site compliant with any law or Google policy
(e.g. the EU User Consent Policy). Verify your setup with Google Tag Assistant.
