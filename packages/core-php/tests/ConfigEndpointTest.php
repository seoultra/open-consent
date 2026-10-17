<?php

declare(strict_types=1);

namespace SeoUltra\OpenConsent\Tests;

use PHPUnit\Framework\TestCase;
use SeoUltra\OpenConsent\Hosted\ConfigEndpoint;

final class ConfigEndpointTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/oc-endpoint-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        file_put_contents($this->dir . '/good-site.json', json_encode(['allowedOrigins' => ['https://example.com'], 'config' => ['profile' => 'opt-out']]));
        file_put_contents($this->dir . '/bad-site.json', json_encode(['allowedOrigins' => ['https://example.com'], 'config' => ['onLoad' => 'alert(1)']]));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    private function get(array $server, array $query): \SeoUltra\OpenConsent\Hosted\Response
    {
        return (new ConfigEndpoint($this->dir))->handle($server + ['REQUEST_METHOD' => 'GET'], $query);
    }

    public function testServesValidatedConfigWithCors(): void
    {
        $r = $this->get(['HTTP_ORIGIN' => 'https://example.com'], ['site' => 'good-site']);
        $this->assertSame(200, $r->status);
        $this->assertSame('https://example.com', $r->headers['Access-Control-Allow-Origin']);
        $this->assertStringContainsString('max-age=300', $r->headers['Cache-Control']);
        $data = json_decode($r->body, true);
        $this->assertSame('opt-out', $data['profile']);
        $this->assertSame('good-site', $data['siteId']);
        $this->assertArrayNotHasKey('allowedOrigins', $data);
    }

    public function testRejectsForeignOrigin(): void
    {
        $r = $this->get(['HTTP_ORIGIN' => 'https://evil.example'], ['site' => 'good-site']);
        $this->assertSame(403, $r->status);
        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $r->headers);
    }

    public function testInvalidSiteConfigIsNotServed(): void
    {
        $r = $this->get([], ['site' => 'bad-site']);
        $this->assertSame(500, $r->status);
        $this->assertStringContainsString('site_config_invalid', $r->body);
    }

    public function testPathTraversalAndUnknown(): void
    {
        $this->assertSame(400, $this->get([], ['site' => '../etc/passwd'])->status);
        $this->assertSame(400, $this->get([], ['site' => ['x']])->status);
        $this->assertSame(404, $this->get([], ['site' => 'nope-site'])->status);
    }

    public function testEtag304(): void
    {
        $first = $this->get([], ['site' => 'good-site']);
        $second = $this->get(['HTTP_IF_NONE_MATCH' => $first->headers['ETag']], ['site' => 'good-site']);
        $this->assertSame(304, $second->status);
        $this->assertSame('', $second->body);
    }

    public function testMethodsAndRateLimit(): void
    {
        $this->assertSame(405, $this->get(['REQUEST_METHOD' => 'POST'], ['site' => 'good-site'])->status);
        $limited = (new ConfigEndpoint($this->dir, 300, fn (string $ip) => false))->handle(['REQUEST_METHOD' => 'GET', 'REMOTE_ADDR' => '1.2.3.4'], ['site' => 'good-site']);
        $this->assertSame(429, $limited->status);
        $limiter = ConfigEndpoint::fileRateLimiter($this->dir . '/rl', 2);
        $this->assertTrue($limiter('1.2.3.4'));
        $this->assertTrue($limiter('1.2.3.4'));
        $this->assertFalse($limiter('1.2.3.4'));
        array_map('unlink', glob($this->dir . '/rl/*') ?: []);
        rmdir($this->dir . '/rl');
    }
}
