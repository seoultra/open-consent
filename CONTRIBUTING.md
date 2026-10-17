# Contributing

Thanks for helping. Ground rules:

1. **One engine.** Consent logic lives only in `packages/web/src`. PHP and WordPress code
   produce configuration and markup; they never decide consent.
2. **Validators stay in sync.** A config rule changes in `packages/web/src/config.js`
   *and* `packages/core-php/src/Config.php`, with a case in `tests/fixtures/config-cases.json`.
3. **Never fail open.** Missing/invalid data must result in *less* tracking, not more.
4. **No new runtime dependencies** in the bundle or the PHP library without discussion.
5. **No telemetry, no remote calls** in the engine or the plugin.

## Workflow

```sh
composer install
php bin/build.php
composer check
python3 tests/run_browser_tests.py
```

Bundler constraints (enforced by `bin/build.php`): single-line
`import { a, b } from './x.js';`, `export function|const|let|class` only, unique
top-level names across modules. New modules must be added to `$ORDER` in `bin/build.php`.

Commit the regenerated `dist/`, `packages/web/src/generated/` and the WordPress copies —
CI fails if they are stale.

Translations: add `config/translations/<lang>.json` with the same keys as `en.json`.

By contributing you agree your contribution is licensed under GPL-2.0-or-later.
