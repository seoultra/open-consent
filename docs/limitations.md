# Limitations

Honest list of what OpenConsent does **not** do.

- **No legal guarantee.** It is a technical tool; categorising cookies, choosing the
  consent model and writing policies remain your responsibility.
- **No automatic cookie scanner / vendor database.** You declare what to block.
- **No geolocation.** One profile per config; serve different configs per region if needed.
- **Client-side records are not proof.** They can be deleted or edited; no server-side
  consent log is kept (deliberately). Forward the `consent` event yourself if you need one.
- **Already-executed code cannot be undone.** Withdrawal stops future execution, sends
  Consent Mode updates, deletes JS-visible first-party cookies matching your patterns and
  unloads gated iframes. `HttpOnly` and third-party cookies cannot be deleted from the page.
- **Replay limits.** Scripts using `document.write` or expecting to run at parse time may
  break when executed after consent.
- **Advanced Consent Mode still contacts Google** (cookieless pings) before consent.
- **IAB TCF v2.2 is not implemented**, so ad networks that require a TCF string will not
  receive one.
- **Hosted dashboard not implemented**; the config API serves hand-edited site files.
- **Translations:** English and Indonesian built in; everything else via `texts`.
- **Toolchain:** the JS build and tests run without Node (PHP build + Chromium via
  Python Playwright). ESLint/Prettier/Vitest are not part of the pipeline; the bundle is
  not minified (≈22 KB gzip).
- **Not yet published**: no GitHub repository, Packagist package, npm package or
  WordPress.org listing exists until the maintainer creates them (see
  [release-checklist.md](release-checklist.md)).
