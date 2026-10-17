<?php

declare(strict_types=1);

namespace SeoUltra\OpenConsent;

/**
 * Validated OpenConsent configuration.
 *
 * Mirrors packages/web/src/config.js rule for rule; both are tested against
 * tests/fixtures/config-cases.json. Invalid values are replaced by defaults
 * and reported by warnings(); unknown keys are dropped. Use fromArrayStrict()
 * to reject any config that produced a warning.
 */
final class Config
{
    public const RE_CATEGORY = '/^[a-z][a-z0-9_-]{0,31}$/D';
    public const RE_COOKIE_PATTERN = '/^[A-Za-z0-9_.\-*]{1,64}$/D';
    public const RE_COOKIE_NAME = '/^[A-Za-z0-9_-]{1,64}$/D';
    public const RE_DOMAIN = '/^\.?[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)*$/D';
    public const RE_VERSION = '/^[A-Za-z0-9._-]{1,32}$/D';
    public const RE_LANG = '/^(auto|[a-z]{2,3}(-[A-Za-z0-9]{2,8})?)$/D';
    public const RE_COLOR = '/^(#[0-9a-fA-F]{3,8}|(rgb|rgba|hsl|hsla)\([0-9.,%\s]+\)|transparent|currentColor)$/D';
    public const RE_RADIUS = '/^[0-9]{1,2}(\.[0-9]{1,2})?(px|rem|em)$/D';
    public const RE_FONT = '/^[A-Za-z0-9 ,"\'_-]{1,200}$/D';
    public const RE_REGION = '/^[A-Z]{2}(-[A-Z0-9]{1,3})?$/D';
    public const RE_GOOGLE_TAG = '/^(G|AW|DC|GT)-[A-Z0-9-]{4,24}$/D';
    public const RE_TEXT_KEY = '/^[A-Za-z0-9_-]{1,40}$/D';
    public const RE_SITE_ID = '/^[a-z0-9][a-z0-9_-]{2,63}$/D';

    private const MAX_CATEGORIES = 12;
    private const MAX_TEXT = 2000;
    private const KNOWN = ['policyVersion', 'profile', 'lang', 'categories', 'cookie', 'links', 'ui', 'gpc', 'reloadOnWithdraw', 'google', 'texts', 'siteId', 'schemaVersion'];

    /** @var array<string, mixed>|null */
    private static ?array $defaults = null;

    /**
     * @param array<string, mixed> $config
     * @param list<string> $warnings
     */
    private function __construct(private readonly array $config, private readonly array $warnings)
    {
    }

    public static function fromArray(mixed $input): self
    {
        $warnings = [];
        $config = self::validate($input, $warnings);
        return new self($config, $warnings);
    }

    /** @throws ConfigException when the input produced any warning */
    public static function fromArrayStrict(mixed $input): self
    {
        $cfg = self::fromArray($input);
        if ($cfg->warnings !== []) {
            throw new ConfigException($cfg->warnings);
        }
        return $cfg;
    }

    /** @throws ConfigException on invalid JSON */
    public static function fromJson(string $json, bool $strict = false): self
    {
        try {
            $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ConfigException(['config: invalid JSON (' . $e->getMessage() . ')']);
        }
        return $strict ? self::fromArrayStrict($data) : self::fromArray($data);
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        if (self::$defaults === null) {
            $raw = file_get_contents(Paths::defaultPolicy());
            if ($raw === false) {
                throw new \RuntimeException('OpenConsent: default policy file is missing');
            }
            /** @var array<string, mixed> $d */
            $d = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            self::$defaults = $d;
        }
        return self::$defaults;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->config;
    }

    /** JSON for the browser engine. Safe to embed inside a <script> element. */
    public function toJson(): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR;
        $data = $this->config;
        // Empty maps must stay JSON objects.
        $data['texts'] = (object) $data['texts'];
        return json_encode($data, $flags);
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function get(string $key): mixed
    {
        return $this->config[$key] ?? null;
    }

    public function policyVersion(): string
    {
        return $this->config['policyVersion'];
    }

    public function cookieName(): string
    {
        return $this->config['cookie']['name'];
    }

    public function cookieDays(): int
    {
        return $this->config['cookie']['days'];
    }

    public function profile(): string
    {
        return $this->config['profile'];
    }

    /** @return list<string> */
    public function categoryNames(): array
    {
        // PHP turns numeric-string keys into ints; category names never are, but keep the contract exact.
        return array_map('strval', array_keys($this->config['categories']));
    }

    /** @return list<string> */
    public function optionalCategories(): array
    {
        return array_values(array_filter($this->categoryNames(), fn (string $n): bool => !$this->config['categories'][$n]['required']));
    }

    public function isRequired(string $category): bool
    {
        return (bool) ($this->config['categories'][$category]['required'] ?? false);
    }

    // ------------------------------------------------------------------ rules

    public static function isSafeUrl(mixed $v): bool
    {
        if (!is_string($v) || strlen($v) > 2048) {
            return false;
        }
        if ($v === '') {
            return true;
        }
        if (preg_match('~^https?://[^\s"\'<>]+$~iD', $v) === 1) {
            return true;
        }
        return preg_match('~^/(?!/)[^\s"\'<>]*$~D', $v) === 1;
    }

    public static function defaultTagCategory(string $id): ?string
    {
        if (str_starts_with($id, 'G-')) {
            return 'analytics';
        }
        if (str_starts_with($id, 'AW-') || str_starts_with($id, 'DC-')) {
            return 'marketing';
        }
        return null;
    }

    private static function isMap(mixed $v): bool
    {
        return is_array($v) && ($v === [] || !array_is_list($v));
    }

    private static function isInt(mixed $v, int $min, int $max): bool
    {
        if (is_float($v) && floor($v) === $v) {
            $v = (int) $v;
        }
        return is_int($v) && $v >= $min && $v <= $max;
    }

    private static function matches(mixed $v, string $re): bool
    {
        return is_string($v) && preg_match($re, $v) === 1;
    }

    /**
     * @param list<string> $warnings
     */
    private static function check(array &$warnings, string $path, bool $ok, mixed $value, mixed $fallback): mixed
    {
        if ($ok) {
            return $value;
        }
        $warnings[] = $path . ': invalid value ignored';
        return $fallback;
    }

    /**
     * @param array<string, mixed> $defaults
     * @param array<string, callable(mixed): bool> $rules
     * @param list<string> $warnings
     * @return array<string, mixed>
     */
    private static function section(mixed $input, array $defaults, string $path, array &$warnings, array $rules): array
    {
        $out = $defaults;
        if ($input === null) {
            return $out;
        }
        if (!self::isMap($input)) {
            $warnings[] = $path . ': must be an object';
            return $out;
        }
        foreach ($input as $k => $v) {
            $k = (string) $k;
            if (!isset($rules[$k])) {
                $warnings[] = $path . '.' . $k . ': unknown key ignored';
                continue;
            }
            $out[$k] = self::check($warnings, $path . '.' . $k, $rules[$k]($v), $v, $out[$k] ?? null);
            if (is_float($out[$k]) && floor($out[$k]) === $out[$k]) {
                $out[$k] = (int) $out[$k];
            }
        }
        return $out;
    }

    /**
     * @param list<string> $warnings
     * @return array<string, mixed>
     */
    private static function texts(mixed $input, array &$warnings, string $path, int $depth): array
    {
        $out = [];
        if (!self::isMap($input)) {
            $warnings[] = $path . ': must be an object';
            return $out;
        }
        foreach ($input as $k => $v) {
            $k = (string) $k;
            $p = $path . '.' . $k;
            if (!self::matches($k, self::RE_TEXT_KEY)) {
                $warnings[] = $p . ': invalid key';
            } elseif (is_string($v)) {
                if (mb_strlen($v) <= self::MAX_TEXT) {
                    $out[$k] = $v;
                } else {
                    $warnings[] = $p . ': text too long';
                }
            } elseif (self::isMap($v) && $depth < 3) {
                $out[$k] = self::texts($v, $warnings, $p, $depth + 1);
            } else {
                $warnings[] = $p . ': must be a string';
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $base
     * @param list<string> $warnings
     * @return array<string, array{required: bool, cookies: list<string>}>
     */
    private static function categories(mixed $input, array $base, array &$warnings): array
    {
        /** @var array<string, array{required: bool, cookies: list<string>}> $out */
        $out = $base;
        if ($input !== null) {
            if (!self::isMap($input)) {
                $warnings[] = 'categories: must be an object';
            } else {
                foreach ($input as $name => $v) {
                    $name = (string) $name;
                    if (!self::matches($name, self::RE_CATEGORY)) {
                        $warnings[] = 'categories.' . $name . ': invalid category name';
                        continue;
                    }
                    if ($v === false) {
                        if ($name === 'necessary') {
                            $warnings[] = 'categories.necessary: cannot be removed';
                        } else {
                            unset($out[$name]);
                        }
                        continue;
                    }
                    if (!self::isMap($v)) {
                        $warnings[] = 'categories.' . $name . ': must be an object or false';
                        continue;
                    }
                    $cur = $out[$name] ?? ['required' => false, 'cookies' => []];
                    if (array_key_exists('required', $v)) {
                        $cur['required'] = self::check($warnings, 'categories.' . $name . '.required', is_bool($v['required']), $v['required'], $cur['required']);
                    }
                    if (array_key_exists('cookies', $v)) {
                        if (is_array($v['cookies']) && array_is_list($v['cookies']) && count($v['cookies']) <= 50) {
                            $cookies = [];
                            foreach ($v['cookies'] as $c) {
                                if (self::matches($c, self::RE_COOKIE_PATTERN)) {
                                    $cookies[] = $c;
                                } else {
                                    $warnings[] = 'categories.' . $name . '.cookies: invalid pattern dropped';
                                }
                            }
                            $cur['cookies'] = $cookies;
                        } else {
                            $warnings[] = 'categories.' . $name . '.cookies: must be an array (max 50)';
                        }
                    }
                    $out[$name] = ['required' => (bool) $cur['required'], 'cookies' => $cur['cookies']];
                }
            }
        }
        $out['necessary'] = ['required' => true, 'cookies' => $out['necessary']['cookies'] ?? []];
        foreach ($out as $name => $c) {
            if ($name !== 'necessary' && $c['required']) {
                $warnings[] = 'categories.' . $name . '.required: only "necessary" can be required';
                $out[$name]['required'] = false;
            }
        }
        $names = array_merge(['necessary'], array_values(array_filter(array_keys($out), fn ($n) => $n !== 'necessary')));
        if (count($names) > self::MAX_CATEGORIES) {
            $warnings[] = 'categories: more than ' . self::MAX_CATEGORIES . ' categories, extra ignored';
            $names = array_slice($names, 0, self::MAX_CATEGORIES);
        }
        $ordered = [];
        foreach ($names as $n) {
            $ordered[$n] = $out[$n];
        }
        return $ordered;
    }

    /**
     * @param list<string> $warnings
     * @return array<string, mixed>
     */
    private static function validate(mixed $input, array &$warnings): array
    {
        $src = self::isMap($input) ? $input : [];
        if (!self::isMap($input) && $input !== null) {
            $warnings[] = 'config: must be an object';
        }
        foreach (array_keys($src) as $k) {
            if (!in_array($k, self::KNOWN, true)) {
                $warnings[] = $k . ': unknown key ignored';
            }
        }
        $d = self::defaults();
        $cfg = [];

        $cfg['policyVersion'] = $d['policyVersion'];
        if (array_key_exists('policyVersion', $src)) {
            $pv = is_string($src['policyVersion']) || is_int($src['policyVersion']) || is_float($src['policyVersion']) ? (string) $src['policyVersion'] : null;
            $cfg['policyVersion'] = self::check($warnings, 'policyVersion', $pv !== null && preg_match(self::RE_VERSION, $pv) === 1, $pv, $d['policyVersion']);
        }
        $cfg['profile'] = array_key_exists('profile', $src) ? self::check($warnings, 'profile', in_array($src['profile'], ['opt-in', 'opt-out'], true), $src['profile'], $d['profile']) : $d['profile'];
        $cfg['lang'] = array_key_exists('lang', $src) ? self::check($warnings, 'lang', self::matches($src['lang'], self::RE_LANG), $src['lang'], $d['lang']) : $d['lang'];
        $cfg['gpc'] = array_key_exists('gpc', $src) ? self::check($warnings, 'gpc', is_bool($src['gpc']), $src['gpc'], $d['gpc']) : $d['gpc'];
        $cfg['reloadOnWithdraw'] = array_key_exists('reloadOnWithdraw', $src) ? self::check($warnings, 'reloadOnWithdraw', is_bool($src['reloadOnWithdraw']), $src['reloadOnWithdraw'], $d['reloadOnWithdraw']) : $d['reloadOnWithdraw'];
        $cfg['siteId'] = array_key_exists('siteId', $src) ? self::check($warnings, 'siteId', self::matches($src['siteId'], self::RE_SITE_ID), $src['siteId'], '') : '';

        $cfg['categories'] = self::categories($src['categories'] ?? null, $d['categories'], $warnings);

        $cfg['cookie'] = self::section($src['cookie'] ?? null, $d['cookie'], 'cookie', $warnings, [
            'name' => fn ($v) => self::matches($v, self::RE_COOKIE_NAME),
            'days' => fn ($v) => self::isInt($v, 1, 730),
            'domain' => fn ($v) => $v === '' || self::matches($v, self::RE_DOMAIN),
            'path' => fn ($v) => is_string($v) && strlen($v) <= 256 && preg_match('~^/[A-Za-z0-9._\~/-]*$~D', $v) === 1,
            'sameSite' => fn ($v) => in_array($v, ['Lax', 'Strict', 'None'], true),
        ]);

        $cfg['links'] = self::section($src['links'] ?? null, $d['links'], 'links', $warnings, [
            'privacyPolicy' => fn ($v) => self::isSafeUrl($v),
            'cookiePolicy' => fn ($v) => self::isSafeUrl($v),
        ]);

        $uiIn = $src['ui'] ?? null;
        $themeIn = self::isMap($uiIn) ? ($uiIn['theme'] ?? null) : null;
        if (self::isMap($uiIn)) {
            unset($uiIn['theme']);
        }
        $uiDefaults = $d['ui'];
        unset($uiDefaults['theme']);
        $cfg['ui'] = self::section($uiIn, $uiDefaults, 'ui', $warnings, [
            'autoShow' => fn ($v) => is_bool($v),
            'layout' => fn ($v) => in_array($v, ['bar', 'box'], true),
            'position' => fn ($v) => in_array($v, ['bottom', 'top', 'bottom-left', 'bottom-right', 'center'], true),
            'reopenButton' => fn ($v) => is_bool($v),
            'reopenPosition' => fn ($v) => in_array($v, ['left', 'right'], true),
            'zIndex' => fn ($v) => self::isInt($v, 0, 2147483647),
        ]);
        $color = fn ($v) => self::matches($v, self::RE_COLOR);
        $cfg['ui']['theme'] = self::section($themeIn, $d['ui']['theme'], 'ui.theme', $warnings, [
            'background' => $color,
            'text' => $color,
            'muted' => $color,
            'accent' => $color,
            'accentText' => $color,
            'border' => $color,
            'radius' => fn ($v) => self::matches($v, self::RE_RADIUS),
            'fontFamily' => fn ($v) => $v === 'inherit' || self::matches($v, self::RE_FONT),
        ]);

        $gIn = $src['google'] ?? null;
        $tagsIn = self::isMap($gIn) ? ($gIn['tags'] ?? null) : null;
        if (self::isMap($gIn)) {
            unset($gIn['tags']);
        }
        $gDefaults = $d['google'];
        unset($gDefaults['tags']);
        $cfg['google'] = self::section($gIn, $gDefaults, 'google', $warnings, [
            'enabled' => fn ($v) => is_bool($v),
            'mode' => fn ($v) => in_array($v, ['basic', 'advanced'], true),
            'waitForUpdate' => fn ($v) => self::isInt($v, 0, 10000),
            'region' => fn ($v) => is_array($v) && array_is_list($v) && count($v) <= 300 && array_reduce($v, fn ($ok, $r) => $ok && self::matches($r, self::RE_REGION), true),
            'urlPassthrough' => fn ($v) => is_bool($v),
            'adsDataRedaction' => fn ($v) => is_bool($v),
            'overrideExisting' => fn ($v) => is_bool($v),
        ]);
        $cfg['google']['tags'] = [];
        if ($tagsIn !== null) {
            if (!is_array($tagsIn) || !array_is_list($tagsIn) || count($tagsIn) > 10) {
                $warnings[] = 'google.tags: must be an array (max 10)';
            } else {
                foreach ($tagsIn as $i => $t) {
                    $p = 'google.tags[' . $i . ']';
                    $tag = is_string($t) ? ['id' => $t] : $t;
                    if (!self::isMap($tag) || !self::matches($tag['id'] ?? null, self::RE_GOOGLE_TAG)) {
                        $warnings[] = $p . ': invalid tag id';
                        continue;
                    }
                    $category = $tag['category'] ?? self::defaultTagCategory($tag['id']);
                    if (!is_string($category) || !isset($cfg['categories'][$category]) || $category === 'necessary') {
                        $warnings[] = $p . ': needs an optional "category"';
                        continue;
                    }
                    foreach ($cfg['google']['tags'] as $existing) {
                        if ($existing['id'] === $tag['id']) {
                            continue 2;
                        }
                    }
                    $cfg['google']['tags'][] = ['id' => $tag['id'], 'category' => $category];
                }
            }
        }

        $cfg['texts'] = [];
        if (array_key_exists('texts', $src)) {
            if (!self::isMap($src['texts'])) {
                $warnings[] = 'texts: must be an object';
            } else {
                foreach ($src['texts'] as $lang => $t) {
                    $lang = (string) $lang;
                    if ($lang === 'auto' || !self::matches($lang, self::RE_LANG)) {
                        $warnings[] = 'texts.' . $lang . ': invalid language code';
                        continue;
                    }
                    $cfg['texts'][$lang] = self::texts($t, $warnings, 'texts.' . $lang, 1);
                }
            }
        }

        if ($cfg['cookie']['sameSite'] === 'None') {
            $cfg['cookie']['secure'] = true;
        }
        return $cfg;
    }
}
