<?php

declare(strict_types=1);

namespace SeoUltra\OpenConsent\Hosted;

use SeoUltra\OpenConsent\Config;
use SeoUltra\OpenConsent\ConfigException;

/**
 * Serves validated per-site configuration to the hosted embed
 * (<script data-site-id="...">). Optional: the browser engine works without it.
 *
 * Site files: <sitesDir>/<site-id>.json
 *   { "allowedOrigins": ["https://www.example.com"], "config": { ...OpenConsent config... } }
 *
 * - The response is data only (validated with Config::fromArrayStrict), never code.
 * - CORS is granted only to the site's allowedOrigins.
 * - Responses are cacheable (Cache-Control + ETag); no visitor data is stored.
 * - Rate limiting is best done at the web server/CDN (see docs/hosted-widget.md);
 *   an optional per-IP limiter callback can be injected.
 */
final class ConfigEndpoint
{
    /** @var (callable(string): bool)|null */
    private $rateLimiter;

    /**
     * @param (callable(string): bool)|null $rateLimiter receives the client IP, returns false to reject
     */
    public function __construct(private readonly string $sitesDir, private readonly int $maxAge = 300, ?callable $rateLimiter = null)
    {
        $this->rateLimiter = $rateLimiter;
    }

    /**
     * @param array<string, mixed> $server $_SERVER-like
     * @param array<string, mixed> $query  $_GET-like
     */
    public function handle(array $server, array $query): Response
    {
        $method = is_string($server['REQUEST_METHOD'] ?? null) ? $server['REQUEST_METHOD'] : 'GET';
        $origin = is_string($server['HTTP_ORIGIN'] ?? null) ? $server['HTTP_ORIGIN'] : null;
        $base = [
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Vary' => 'Origin',
        ];

        if ($method !== 'GET' && $method !== 'HEAD' && $method !== 'OPTIONS') {
            return Response::json(405, ['error' => 'method_not_allowed'], $base + ['Allow' => 'GET, HEAD, OPTIONS']);
        }
        $ip = is_string($server['REMOTE_ADDR'] ?? null) ? $server['REMOTE_ADDR'] : '';
        if ($this->rateLimiter !== null && !($this->rateLimiter)($ip)) {
            return Response::json(429, ['error' => 'rate_limited'], $base + ['Retry-After' => '60', 'Cache-Control' => 'no-store']);
        }

        $site = $query['site'] ?? null;
        if (!is_string($site) || preg_match(Config::RE_SITE_ID, $site) !== 1) {
            return Response::json(400, ['error' => 'invalid_site_id'], $base + ['Cache-Control' => 'no-store']);
        }
        $file = rtrim($this->sitesDir, '/') . '/' . $site . '.json';
        $raw = is_file($file) ? file_get_contents($file) : false;
        if ($raw === false) {
            return Response::json(404, ['error' => 'unknown_site'], $base + ['Cache-Control' => 'public, max-age=60']);
        }
        try {
            $doc = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return Response::json(500, ['error' => 'site_config_unreadable'], $base + ['Cache-Control' => 'no-store']);
        }
        if (!is_array($doc)) {
            return Response::json(500, ['error' => 'site_config_unreadable'], $base + ['Cache-Control' => 'no-store']);
        }
        $allowed = is_array($doc['allowedOrigins'] ?? null) ? array_values(array_filter($doc['allowedOrigins'], 'is_string')) : [];

        $headers = $base;
        if ($origin !== null) {
            if (!in_array($origin, $allowed, true)) {
                return Response::json(403, ['error' => 'origin_not_allowed'], $base + ['Cache-Control' => 'no-store']);
            }
            $headers['Access-Control-Allow-Origin'] = $origin;
            $headers['Access-Control-Allow-Methods'] = 'GET, HEAD, OPTIONS';
            $headers['Access-Control-Max-Age'] = '86400';
        }
        if ($method === 'OPTIONS') {
            return new Response(204, $headers, '');
        }

        try {
            $config = Config::fromArrayStrict(array_replace(is_array($doc['config'] ?? null) ? $doc['config'] : [], ['siteId' => $site]));
        } catch (ConfigException $e) {
            // Never serve a partially-valid config silently; the embed falls back to local config.
            return Response::json(500, ['error' => 'site_config_invalid', 'problems' => $e->problems()], $headers + ['Cache-Control' => 'no-store']);
        }

        $body = $config->toJson();
        $etag = '"' . substr(hash('sha256', $body), 0, 32) . '"';
        $headers['Cache-Control'] = 'public, max-age=' . $this->maxAge . ', stale-while-revalidate=' . ($this->maxAge * 2);
        $headers['ETag'] = $etag;
        $inm = is_string($server['HTTP_IF_NONE_MATCH'] ?? null) ? $server['HTTP_IF_NONE_MATCH'] : '';
        if ($inm !== '' && in_array($etag, array_map('trim', explode(',', $inm)), true)) {
            return new Response(304, $headers, '');
        }
        return new Response(200, $headers, $method === 'HEAD' ? '' : $body);
    }

    /**
     * A small fixed-window limiter backed by files in a private directory.
     * Prefer nginx `limit_req` or your CDN in production.
     *
     * @return callable(string): bool
     */
    public static function fileRateLimiter(string $dir, int $perMinute = 120): callable
    {
        return static function (string $ip) use ($dir, $perMinute): bool {
            if ($ip === '' || (!is_dir($dir) && !@mkdir($dir, 0700, true))) {
                return true;
            }
            $window = (int) floor(time() / 60);
            $path = $dir . '/' . hash('sha256', $ip . '|' . $window) . '.n';
            $fh = @fopen($path, 'c+');
            if ($fh === false) {
                return true;
            }
            flock($fh, LOCK_EX);
            $n = (int) stream_get_contents($fh) + 1;
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, (string) $n);
            flock($fh, LOCK_UN);
            fclose($fh);
            if (random_int(1, 200) === 1) {
                foreach (glob($dir . '/*.n') ?: [] as $f) {
                    if (filemtime($f) < time() - 180) {
                        @unlink($f);
                    }
                }
            }
            return $n <= $perMinute;
        };
    }
}
