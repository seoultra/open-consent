# Changelog

All notable changes are documented here. Format: [Keep a Changelog](https://keepachangelog.com/),
versioning: [SemVer](https://semver.org/).

## [0.2.0] — 2026-10-17

### Added
- Widget translations for 98 more languages — 125 in total (`config/translations/`), including region
  variants `pt-br` and `zh-tw`. Machine-assisted; native-speaker review welcome.
- Locale aliases (`no`→`nb`, `zh-Hant`→`zh-tw`, `zh-HK`→`yue`, `iw`→`he`, `ku`→`kmr`, …) in JS and PHP.
- Translation files may use a region suffix (`xx-yy`); the build checks every file's keys against `en.json`.

## [0.1.0] — 2026-10-17

First release.

### Added
- Browser consent engine: banner, preferences dialog, reopen button, accept/reject/custom/withdraw/reset, events.
- Consent record in a first-party cookie with validation, expiry, policy versioning and a schema-migration hook.
- Opt-in / opt-out profiles with Global Privacy Control.
- Script blocking for inline, external and module scripts and iframes; ordered replay; cookie cleanup on revoke.
- Google Consent Mode v2 (Basic/Advanced), tag loading, conflict detection, GTM `open_consent_update` event.
- English and Indonesian translations.
- PHP library `seoultra/open-consent`: config validation, consent-cookie reader, markup helpers, hosted config endpoint.
- WordPress plugin: settings page, enqueued-handle gating, gated snippets, `[open_consent]` shortcode, WP Consent API bridge.
- Hosted embed mode with validated remote config and local fallback.
