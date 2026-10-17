<?php

declare(strict_types=1);

namespace SeoUltra\OpenConsent;

/**
 * HTML helpers for wiring the browser engine into server-rendered pages.
 * The PHP side only produces configuration and inert markup; enforcement
 * happens in the browser engine (dist/open-consent.js).
 */
final class Frontend
{
    /** Inline JSON configuration read by the bundle at load time. Place it BEFORE loaderScript(). */
    public static function configScript(Config $config): string
    {
        return '<script type="application/json" id="open-consent-config">' . $config->toJson() . '</script>';
    }

    /**
     * The engine itself. Load it synchronously in <head> (no async/defer) so
     * Google Consent Mode defaults exist before any Google tag runs.
     *
     * @param array<string, string> $attributes
     */
    public static function loaderScript(string $src, array $attributes = []): string
    {
        self::assertUrl($src);
        return '<script src="' . self::e($src) . '"' . self::attrs($attributes) . '></script>';
    }

    /** config + loader in one call. */
    public static function head(Config $config, string $src, ?string $integrity = null): string
    {
        $attrs = $integrity ? ['integrity' => $integrity, 'crossorigin' => 'anonymous'] : [];
        return self::configScript($config) . "\n" . self::loaderScript($src, $attrs);
    }

    /**
     * Inline code that runs only after consent for every listed category.
     *
     * @param string|list<string> $categories
     * @param array<string, string> $attributes
     */
    public static function blockedScript(string|array $categories, string $code, array $attributes = []): string
    {
        if (preg_match('~</script~i', $code)) {
            throw new \InvalidArgumentException('Blocked script code must not contain "</script"');
        }
        return '<script type="text/plain" data-open-consent="' . self::e(self::cats($categories)) . '"' . self::attrs($attributes) . '>' . $code . '</script>';
    }

    /**
     * External script that is only requested after consent.
     *
     * @param string|list<string> $categories
     * @param array<string, string> $attributes e.g. ['async' => ''] (omit async to keep document order)
     */
    public static function blockedExternalScript(string|array $categories, string $src, array $attributes = []): string
    {
        self::assertUrl($src);
        return '<script type="text/plain" data-open-consent="' . self::e(self::cats($categories)) . '" data-src="' . self::e($src) . '"' . self::attrs($attributes) . '></script>';
    }

    /**
     * Embed (video, map, social) that only loads after consent; shows a placeholder until then.
     *
     * @param string|list<string> $categories
     * @param array<string, string> $attributes
     */
    public static function blockedIframe(string|array $categories, string $src, array $attributes = []): string
    {
        self::assertUrl($src);
        return '<iframe data-open-consent="' . self::e(self::cats($categories)) . '" data-src="' . self::e($src) . '"' . self::attrs($attributes) . '></iframe>';
    }

    /** A link/button that reopens the preferences dialog. */
    public static function preferencesButton(string $label, string $class = ''): string
    {
        return '<button type="button" data-open-consent-open' . ($class !== '' ? ' class="' . self::e($class) . '"' : '') . '>' . self::e($label) . '</button>';
    }

    /** @param string|list<string> $categories */
    private static function cats(string|array $categories): string
    {
        $list = is_array($categories) ? $categories : preg_split('/\s+/', trim($categories));
        $list = array_values(array_filter((array) $list, fn ($c) => $c !== ''));
        if ($list === []) {
            throw new \InvalidArgumentException('At least one category is required');
        }
        foreach ($list as $c) {
            if (!is_string($c) || preg_match(Config::RE_CATEGORY, $c) !== 1) {
                throw new \InvalidArgumentException('Invalid category name');
            }
        }
        return implode(' ', $list);
    }

    private static function assertUrl(string $src): void
    {
        if ($src === '' || preg_match('~^(?![A-Za-z][A-Za-z0-9+.-]*:)(?!//)[^\s"\'<>]+$~D', $src) !== 1 && !Config::isSafeUrl($src)) {
            throw new \InvalidArgumentException('Unsafe URL');
        }
    }

    /** @param array<string, string> $attributes */
    private static function attrs(array $attributes): string
    {
        $out = '';
        foreach ($attributes as $name => $value) {
            $name = strtolower((string) $name);
            if (preg_match('/^[a-z][a-z0-9_-]*$/D', $name) !== 1 || str_starts_with($name, 'on') || in_array($name, ['type', 'src', 'data-open-consent', 'data-src'], true)) {
                throw new \InvalidArgumentException('Attribute not allowed: ' . $name);
            }
            $out .= $value === '' ? ' ' . $name : ' ' . $name . '="' . self::e((string) $value) . '"';
        }
        return $out;
    }

    private static function e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
