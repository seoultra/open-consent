# Release checklist

1. Update `VERSION`, `OpenConsent::VERSION`, the plugin header + `OPEN_CONSENT_VERSION`,
   `readme.txt` "Stable tag", and `CHANGELOG.md`.
2. `php bin/build.php` → commit `dist/`, the generated module and the plugin copy.
3. `composer check` (build freshness + PHPUnit + PHPStan) and
   `python3 tests/run_browser_tests.py` (JS unit + e2e in Chromium).
4. WordPress: run `wp-integration-test.php` and `wp_e2e.py` against a scratch install
   (see [wordpress.md](wordpress.md#testing)).
5. `composer validate --strict`.
6. Tag `vX.Y.Z` and push.
7. Plugin zip: `bin/package-wordpress.sh` → `build/open-consent-wordpress-X.Y.Z.zip`.

## Publishing to Packagist (first time)

Requires a **public VCS repository** (e.g. GitHub) containing this repository with
`composer.json` at its root, and access to the `seoultra` Packagist account.

1. Push the repository and a `v0.1.0` tag.
2. Log in at packagist.org as `seoultra` → Submit → paste the repository URL.
   The package name comes from `composer.json`: `seoultra/open-consent`.
3. Enable automatic updates (GitHub: Packagist's GitHub integration or a webhook to
   `https://packagist.org/api/github?username=seoultra` with the account's API token
   stored as a secret — never in a file or a remote URL).
4. Verify: `composer require seoultra/open-consent:^0.1` in an empty project.

## WordPress.org (optional)

Submit the plugin zip for review; after approval, publish via the provided SVN
repository. `readme.txt` is already in WordPress.org format.
