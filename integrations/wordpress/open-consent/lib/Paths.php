<?php

declare(strict_types=1);

namespace SeoUltra\OpenConsent;

/** Locations of the files shipped inside the Composer package. */
final class Paths
{
    public static function root(): string
    {
        // Bundled copies (e.g. the WordPress plugin) point this at their own root.
        if (defined('OPEN_CONSENT_PACKAGE_ROOT') && is_string(constant('OPEN_CONSENT_PACKAGE_ROOT'))) {
            return rtrim((string) constant('OPEN_CONSENT_PACKAGE_ROOT'), '/');
        }
        return dirname(__DIR__, 3);
    }

    public static function defaultPolicy(): string
    {
        return self::root() . '/config/default-policy.json';
    }

    /** The browser bundle — copy or symlink it into your public web root. */
    public static function browserBundle(): string
    {
        return self::root() . '/dist/open-consent.js';
    }

    /** Subresource Integrity hash of the bundle ("sha384-..."). */
    public static function browserBundleIntegrity(): string
    {
        $sri = @file_get_contents(self::root() . '/dist/open-consent.js.sri');
        return $sri === false ? '' : trim($sri);
    }
}
