# Composer example

```sh
cd examples/composer
composer install          # installs seoultra/open-consent from this repository (path repo)
php -S 127.0.0.1:8000 -t public
```

In a real project drop the `repositories` entry (the package is on Packagist) and copy `vendor/seoultra/open-consent/dist/open-consent.js` into
your public directory during deploy (the `post-install-cmd` here shows one way).
