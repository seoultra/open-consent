# Security policy

## Supported versions

| Version | Supported |
|---|---|
| 0.1.x | ✅ |

## Reporting a vulnerability

Please report privately — do not open a public issue.

- Preferred: GitHub "Report a vulnerability" (private security advisory) at
  https://github.com/seoultra/open-consent/security/advisories/new
- Otherwise: through the contact options at https://seoultra.id/.

Include affected version, a description, and steps or a proof of concept. You will get an
acknowledgement and, for confirmed issues, a fix and an advisory crediting you (unless
you prefer otherwise).

Particularly interesting: anything that makes protected scripts run without consent,
XSS via configuration or translations, consent-record parsing issues, WordPress
privilege escalation, and config-API CORS/validation bypasses.

The threat model is in [docs/security.md](docs/security.md).
