<?php

declare(strict_types=1);

namespace SeoUltra\OpenConsent;

/** Reads the browser engine's first-party consent cookie on the server. */
final class ConsentCookie
{
    /**
     * @param array<string, mixed>|null $cookies defaults to $_COOKIE
     * @param array<string, mixed>|null $server  defaults to $_SERVER (for the Sec-GPC header)
     */
    public static function read(Config $config, ?array $cookies = null, ?array $server = null, ?int $now = null): ConsentState
    {
        $cookies ??= $_COOKIE;
        $server ??= $_SERVER;
        $raw = $cookies[$config->cookieName()] ?? null;
        $gpc = ($server['HTTP_SEC_GPC'] ?? '') === '1';
        if (!is_string($raw) || $raw === '' || strlen($raw) > 4096) {
            return ConsentState::none($config, $raw === null ? 'missing' : 'corrupt', $gpc);
        }
        $state = ConsentState::fromJson($raw, $config, $now);
        return $state->isValid() ? $state : ConsentState::none($config, $state->reason(), $gpc);
    }

    /**
     * Set-Cookie header value for a record, matching what the browser engine
     * writes. Not HttpOnly on purpose: the browser engine must read it.
     */
    public static function headerValue(Config $config, ConsentState $state, bool $secure = true): string
    {
        $c = $config->toArray()['cookie'];
        $v = $c['name'] . '=' . rawurlencode($state->toJson())
            . '; Max-Age=' . ($c['days'] * 86400)
            . '; Path=' . $c['path']
            . '; SameSite=' . $c['sameSite'];
        if ($c['domain'] !== '') {
            $v .= '; Domain=' . $c['domain'];
        }
        if ($secure || $c['sameSite'] === 'None') {
            $v .= '; Secure';
        }
        return $v;
    }
}
