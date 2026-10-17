# API reference

## JavaScript — `window.OpenConsent`

The bundle installs one instance per window (loading it twice is harmless).

| Method | Returns | Notes |
|---|---|---|
| `init(config)` | the API | Validates config, applies Google defaults, starts blocking, renders UI on DOM ready. Only the first call has effect. Called automatically when `#open-consent-config` or `window.OpenConsentConfig` exists. |
| `getConsent()` | snapshot | `{ decided, categories, method, record, policyVersion, profile, gpc, persisted, reason }` |
| `hasConsent(category)` | boolean | `false` before init and for unknown categories |
| `acceptAll()` / `rejectAll()` | snapshot | `rejectAll` keeps only required categories |
| `updateConsent({ analytics: true })` | snapshot | Only booleans count; missing optional categories keep their current value; `necessary` cannot be turned off |
| `withdrawConsent()` | snapshot | All optional categories denied, method `withdraw`; cookies cleaned |
| `resetConsent()` | snapshot | Deletes the record; banner shown again |
| `openPreferences()` | boolean | Also triggered by `[data-open-consent-open]` and `a[href="#open-consent"]` |
| `showBanner()` | boolean | |
| `whenAllowed(categories, fn)` | boolean | Runs `fn` once when all categories are granted |
| `loadScript(src, categories, attrs?)` | boolean | Injects an external script once allowed |
| `on(event, fn)` | unsubscribe fn | see events |
| `getConfig()` | validated config copy | |
| `categories()` | optional category names | |
| `isInitialized()` | boolean | |
| `version` | string | |

### Events

`OpenConsent.on(name, fn)` or `document.addEventListener('openconsent:' + name, e => e.detail)`.

| Event | Detail |
|---|---|
| `ready` | snapshot, after DOM ready |
| `consent` | snapshot + `changed: [{category, granted}]` — after every choice |
| `change` | `{category, granted}` — per changed category |
| `withdraw` | snapshot |
| `reset` | snapshot |
| `banner:show` / `banner:hide` | — |
| `preferences:show` / `preferences:hide` | `{cancelled}` on hide |
| `script:executed` | `{categories, src}` |
| `google:conflict` | — another platform's Consent Mode default was found |

A throwing listener is logged and isolated; it never stops consent processing.

### Configuration

Defaults: [config/default-policy.json](../config/default-policy.json). Top-level keys:
`policyVersion`, `profile` (`opt-in`|`opt-out`), `lang` (`auto` or a code),
`categories`, `cookie` (`name`, `days` 1–730, `domain`, `path`, `sameSite`),
`links` (`privacyPolicy`, `cookiePolicy`), `ui` (`autoShow`, `layout` `bar|box`,
`position`, `reopenButton`, `reopenPosition`, `zIndex`, `theme{background,text,muted,accent,accentText,border,radius,fontFamily}`),
`gpc`, `reloadOnWithdraw`, `google` (see [google-consent-mode.md](google-consent-mode.md)),
`texts` (`{ "<lang>": { banner:{…}, preferences:{…}, categories:{…}, reopen, blockedContent, allowContent } }` —
keys as in [config/translations/en.json](../config/translations/en.json)).

## PHP — namespace `SeoUltra\OpenConsent`

| Class | Purpose |
|---|---|
| `Config::fromArray($in)` / `fromArrayStrict($in)` / `fromJson($json, $strict)` | Validate (same rules as JS). `warnings()`, `toArray()`, `toJson()` (safe inside `<script>`), `policyVersion()`, `cookieName()`, `categoryNames()`, `optionalCategories()`. Strict throws `ConfigException` (`problems()`). |
| `ConsentCookie::read($config, $_COOKIE?, $_SERVER?)` | `ConsentState` from the engine's cookie; honours `Sec-GPC` for the opt-out profile. |
| `ConsentState` | `isValid()`, `reason()`, `allows($cat)`, `categories()`, `method()`, `record()`; `ConsentState::create($config, $choice, $method)` + `ConsentCookie::headerValue()` for server-side flows. |
| `Frontend` | `head($config, $src, $integrity)`, `configScript()`, `loaderScript()`, `blockedScript($cats, $code)`, `blockedExternalScript($cats, $src, $attrs)`, `blockedIframe()`, `preferencesButton()`. Throws on unsafe URLs, `</script` in code, `on*` attributes. |
| `Paths` | `browserBundle()`, `browserBundleIntegrity()`, `defaultPolicy()` |
| `Hosted\ConfigEndpoint` | optional config API (see [hosted-widget.md](hosted-widget.md)) |

Use `ConsentState::allows()` to decide what to *render* (e.g. skip embedding a map).
Never use it to authorise anything: the cookie is controlled by the visitor.

### Versioning

Semantic Versioning. Before 1.0.0, minor versions may change the API; patch versions
will not. The consent-record `schema` changes only with a migration.
