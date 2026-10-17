<?php

declare(strict_types=1);

namespace SeoUltra\OpenConsent;

/**
 * Banner copy shipped as config/translations/<code>.json. English and
 * Indonesian are compiled into the browser bundle; for any other language,
 * pass the dictionary to the engine through config `texts`:
 *
 *   Config::fromArray(['texts' => Translations::textsFor('de_DE')] + $cfg)
 */
final class Translations
{
    /** Locale aliases → file codes (same table as packages/web/src/i18n.js LANG_ALIASES). */
    public const ALIASES = [
        'zh-hk' => 'yue', 'zh-mo' => 'yue', 'zh-hant' => 'zh-tw', 'zh-hans' => 'zh', 'zh-cn' => 'zh', 'zh-sg' => 'zh',
        'fil' => 'tl', 'es-mx' => 'es', 'in' => 'id', 'zsm' => 'ms', 'no' => 'nb', 'iw' => 'he', 'ji' => 'yi', 'ku' => 'kmr', 'pt-pt' => 'pt', 'mo' => 'ro',
    ];

    /** Languages compiled into dist/open-consent.js. */
    public const BUILTIN = ['en', 'id'];

    /** @return list<string> */
    public static function available(): array
    {
        $out = [];
        foreach (glob(Paths::root() . '/config/translations/*.json') ?: [] as $f) {
            $out[] = basename($f, '.json');
        }
        sort($out);
        return $out;
    }

    /** Map a locale such as "pt_BR", "zh-HK" or "ru" to an available file code, or null. */
    public static function resolve(string $locale): ?string
    {
        $lc = strtolower(str_replace('_', '-', trim($locale)));
        $primary = explode('-', $lc)[0];
        $available = self::available();
        foreach ([$lc, self::ALIASES[$lc] ?? null, $primary, self::ALIASES[$primary] ?? null] as $c) {
            if ($c !== null && in_array($c, $available, true)) {
                return $c;
            }
        }
        return null;
    }

    /** @return array<string, mixed>|null */
    public static function get(string $code): ?array
    {
        if (preg_match('/^[a-z]{2,3}(-[a-z]{2,4})?$/D', $code) !== 1) {
            return null;
        }
        $file = Paths::root() . '/config/translations/' . $code . '.json';
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    /**
     * `texts` entry for a locale that is not built into the bundle (empty
     * array when it is built in or unavailable).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function textsFor(string $locale): array
    {
        $code = self::resolve($locale);
        if ($code === null || in_array($code, self::BUILTIN, true)) {
            return [];
        }
        $t = self::get($code);
        return $t === null ? [] : [$code => $t];
    }
}
