# Architecture

OpenConsent has **one consent engine**, written in plain JavaScript, that runs in the
visitor's browser. Every distribution format is a thin wrapper around it:

```
                    config/default-policy.json + config/translations/*.json
                                   │  (single source of defaults & copy)
               ┌───────────────────┼─────────────────────────┐
               ▼                   ▼                         ▼
  packages/web/src/*.js     packages/core-php/src      hosted/config-api
  (browser engine, ES       (PHP library: validates     (optional PHP endpoint
   modules) ──bin/build.php──▶ config, reads the consent   that serves validated
   dist/open-consent.js      cookie, renders the config    per-site JSON config)
               │              <script> + blocked-script
               │              markup)
   ┌───────────┼──────────────────┬────────────────────┐
   ▼           ▼                  ▼                    ▼
 standalone   WordPress plugin   Composer apps        hosted embed
 <script>     (enqueues dist,    (vendor/seoultra/    <script data-site-id>
              settings → JSON)   open-consent/dist)   (same bundle, embed mode)
```

## Rules the design enforces

- **No second engine.** PHP never decides what runs in the browser. The WordPress plugin
  and the PHP library only produce *configuration* and *markup*; the browser engine
  enforces it.
- **No remote dependency.** The engine works from an inline JSON config. The hosted config
  API is optional; when it is unreachable the engine falls back to the local config or to
  restrictive built-in defaults.
- **Remote config is data only.** The validator whitelists keys and types. Nothing in a
  config can carry executable code: blocked scripts are always markup on the page itself.
- **Missing, corrupt, expired, wrong-policy-version or unknown-schema consent = no consent.**
- **Idempotent.** Loading the bundle twice, or calling `init()` twice, never creates a second
  banner, a second listener set, or a second Google Consent Mode `default` command.

## Browser modules (`packages/web/src`)

| Module | Responsibility |
|---|---|
| `config.js` | Validate + normalise configuration (whitelist, types, safe colours/URLs). |
| `consent-state.js` | The consent record: create, validate, migrate, accept/reject/update/withdraw. |
| `consent-policy.js` | Policy profiles (`opt-in`, `opt-out`), implied defaults, GPC handling. |
| `storage.js` | First-party cookie read/write/delete, vendor-cookie cleanup by pattern. |
| `events.js` | Small event emitter + DOM `CustomEvent` bridge. |
| `i18n.js` | Translation lookup with per-site overrides. |
| `google-consent.js` | Google Consent Mode v2 defaults/updates, basic/advanced tag loading. |
| `script-blocker.js` | Executes `type="text/plain" data-open-consent` scripts/iframes when allowed. |
| `banner.js`, `preferences.js`, `accessibility.js` | UI (shadow DOM, focus management). |
| `styles.js` | Generated CSS (from `packages/web/styles/open-consent.css`). |
| `embed.js` | Hosted-widget mode (`data-site-id`): early restrictive defaults, config fetch, fallback. |
| `index.js` | Public `OpenConsent` API + auto-init. |

`bin/build.php` concatenates the modules in dependency order into one IIFE
(`dist/open-consent.js`) — no Node toolchain is required. Tests run the *unbundled* ES
modules and the *bundle* in headless Chromium.

## Consent record (cookie `open_consent`, first-party)

```json
{"schema":1,"policy":"1","profile":"opt-in","method":"custom",
 "categories":{"necessary":true,"functional":false,"analytics":true,"marketing":false},
 "createdAt":1760000000,"updatedAt":1760000000,"expiresAt":1775552000}
```

No identifier, IP address or user agent is stored. See [consent-model.md](consent-model.md).
