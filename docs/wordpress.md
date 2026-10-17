# WordPress plugin

`integrations/wordpress/open-consent/` — requires WordPress 6.2+ and PHP 8.1+
(tested on WordPress 7.1.3 / PHP 8.4).

## What it does

- Enqueues the shared engine in `<head>` (synchronously, early) and passes the
  configuration as `window.OpenConsentConfig`, validated by the bundled
  `SeoUltra\OpenConsent\Config` (the same library as the Composer package).
- **Gates enqueued scripts**: list `handle = category` lines under *Script blocking*.
  The handle's `<script>` and its inline `before`/`after` code are rewritten with the
  WordPress HTML API to `type="text/plain" data-open-consent="…" data-src="…"`, and the
  handle is made to depend on the engine so the engine prints first.
- **Gated snippets**: per-category textareas for pixels/tags (requires the
  `unfiltered_html` capability to save; printed inert in the footer).
- Optional Google Consent Mode v2 and GA4/Ads tag loading (see below).
- `[open_consent]` shortcode → a "Cookie preferences" button
  (`[open_consent label="Cookie settings" type="link" class="my-class"]`).
- Floating reopen button (setting), page/post IDs to skip, `open_consent_enabled` filter.
- WP Consent API: declares the consent type (`optin`/`optout`), registers the plugin,
  and — when the WP Consent API plugin is active — forwards every choice with
  `wp_set_consent()` (`functional`→functional+preferences, `analytics`→statistics +
  statistics-anonymous, `marketing`→marketing).
- Admin notice if another consent plugin (Complianz, CookieYes, Cookiebot, Cookie
  Notice, iubenda, Real Cookie Banner, GDPR Cookie Compliance) is active. Nothing fatal.

Finding a handle: view the page source; WordPress prints `id="<handle>-js"` on each
script tag.

## Site Kit

Two valid setups — pick one:

1. **Site Kit's Consent Mode ON** + WP Consent API plugin installed: leave OpenConsent's
   "Send Consent Mode v2 signals" **off**. Site Kit sends the Google signals from the
   WP Consent API values that OpenConsent sets.
2. **OpenConsent's Consent Mode ON**, Site Kit's Consent Mode **off**: OpenConsent sends
   defaults/updates; Site Kit's gtag (`google_gtagjs` handle) is forced to print after
   the engine. To *block* Site Kit's tag before consent (Basic behaviour), add
   `google_gtagjs = analytics` under "Gate enqueued scripts".

Never enable both Consent Mode integrations — OpenConsent loads first and cannot
detect a default that is sent after it.

## Google Tag Manager / manual tags

Put GTM or gtag snippets in the per-category snippet fields (they become blocked
scripts), or add them in your theme *after* `wp_head()` priority 1. See
[google-consent-mode.md](google-consent-mode.md).

## Filters

| Filter | Purpose |
|---|---|
| `open_consent_config` | modify the config array before validation |
| `open_consent_enabled` | return false to skip OpenConsent on a request |

## Data & uninstall

One option, `open_consent_settings`. Uninstalling keeps it unless "Delete settings when
the plugin is uninstalled" is checked. Visitors' consent cookies are in their browsers.
No remote requests are made.

## Testing

`integrations/wordpress/tests/`:

- `wp-integration-test.php` — run inside a WordPress install with the plugin active:
  `wp eval-file integrations/wordpress/tests/wp-integration-test.php` (activation,
  enqueuing, shortcode, settings validation, capability checks, handle gating,
  corrupted/partial options, WP Consent API hooks).
- `wp_e2e.py` + `mu-plugin/` — browser test of the front end (banner, gated handle,
  Google ordering, shortcode, reload, withdrawal). Instructions in the file header.
