<?php

/**
 * Optional hosted configuration endpoint for the embed widget.
 *
 *   GET /config-api/index.php?site=<site-id>
 *
 * Deploy behind HTTPS. Site files live in ./sites (or OPEN_CONSENT_SITES_DIR)
 * and must not be web-readable themselves if they hold anything private
 * (they normally don't: the served config is public by nature).
 */

declare(strict_types=1);

use SeoUltra\OpenConsent\Hosted\ConfigEndpoint;

$autoload = getenv('OPEN_CONSENT_AUTOLOAD') ?: dirname(__DIR__, 2) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'SeoUltra\\OpenConsent\\';
        if (str_starts_with($class, $prefix)) {
            $file = dirname(__DIR__, 2) . '/packages/core-php/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    });
}

$sites = getenv('OPEN_CONSENT_SITES_DIR') ?: __DIR__ . '/sites';
$limitDir = getenv('OPEN_CONSENT_RATE_DIR') ?: '';
$limiter = $limitDir !== '' ? ConfigEndpoint::fileRateLimiter($limitDir, 120) : null;

(new ConfigEndpoint($sites, 300, $limiter))->handle($_SERVER, $_GET)->send();
