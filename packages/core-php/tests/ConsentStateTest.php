<?php

declare(strict_types=1);

namespace SeoUltra\OpenConsent\Tests;

use PHPUnit\Framework\TestCase;
use SeoUltra\OpenConsent\Config;
use SeoUltra\OpenConsent\ConsentCookie;
use SeoUltra\OpenConsent\ConsentState;

final class ConsentStateTest extends TestCase
{
    private const NOW = 1800000000;

    private function record(array $over = []): array
    {
        return array_replace([
            'schema' => 1, 'policy' => '1', 'profile' => 'opt-in', 'method' => 'custom',
            'categories' => ['necessary' => true, 'functional' => false, 'analytics' => true, 'marketing' => false],
            'createdAt' => self::NOW, 'updatedAt' => self::NOW, 'expiresAt' => self::NOW + 1000,
        ], $over);
    }

    public function testValidRecord(): void
    {
        $s = ConsentState::fromJson(json_encode($this->record()), Config::fromArray([]), self::NOW);
        $this->assertTrue($s->isValid());
        $this->assertTrue($s->allows('analytics'));
        $this->assertFalse($s->allows('marketing'));
        $this->assertFalse($s->allows('unknown'));
        $this->assertSame('custom', $s->method());
    }

    /** @return iterable<string, array{string|null, string}> */
    public static function invalid(): iterable
    {
        $base = ['schema' => 1, 'policy' => '1', 'method' => 'custom', 'createdAt' => 1, 'updatedAt' => 1, 'expiresAt' => self::NOW + 10,
            'categories' => ['necessary' => true, 'functional' => false, 'analytics' => true, 'marketing' => false]];
        yield 'missing' => [null, 'missing'];
        yield 'not json' => ['{x', 'corrupt'];
        yield 'list' => ['[1,2]', 'corrupt'];
        yield 'string bool' => [json_encode(array_replace_recursive($base, ['categories' => ['analytics' => 'true']])), 'corrupt'];
        yield 'bad method' => [json_encode(array_replace($base, ['method' => 'implied'])), 'corrupt'];
        yield 'necessary false' => [json_encode(array_replace_recursive($base, ['categories' => ['necessary' => false]])), 'corrupt'];
        yield 'newer schema' => [json_encode(array_replace($base, ['schema' => 2])), 'schema'];
        yield 'old policy' => [json_encode(array_replace($base, ['policy' => '0'])), 'policy'];
        yield 'expired' => [json_encode(array_replace($base, ['expiresAt' => self::NOW])), 'expired'];
        $partial = $base;
        unset($partial['categories']['marketing']);
        yield 'missing category' => [json_encode($partial), 'categories'];
    }

    /** @dataProvider invalid */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalid')]
    public function testInvalidNeverGrants(?string $raw, string $reason): void
    {
        $s = ConsentState::fromJson($raw, Config::fromArray([]), self::NOW);
        $this->assertFalse($s->isValid());
        $this->assertSame($reason, $s->reason());
        $this->assertFalse($s->allows('analytics'));
        $this->assertTrue($s->allows('necessary'));
    }

    public function testOptOutImpliedAndGpc(): void
    {
        $cfg = Config::fromArray(['profile' => 'opt-out']);
        $s = ConsentCookie::read($cfg, [], [], self::NOW);
        $this->assertTrue($s->allows('marketing'));
        $s = ConsentCookie::read($cfg, [], ['HTTP_SEC_GPC' => '1'], self::NOW);
        $this->assertFalse($s->allows('marketing'));
        $this->assertTrue($s->allows('analytics'));
    }

    public function testCookieRoundTrip(): void
    {
        $cfg = Config::fromArray(['cookie' => ['name' => 'oc', 'days' => 10]]);
        $state = ConsentState::create($cfg, ['analytics' => true, 'marketing' => 'yes'], 'custom', self::NOW);
        $this->assertSame(['necessary' => true, 'functional' => false, 'analytics' => true, 'marketing' => false], $state->categories());
        $header = ConsentCookie::headerValue($cfg, $state);
        $this->assertStringStartsWith('oc=', $header);
        $this->assertStringContainsString('Max-Age=864000', $header);
        $this->assertStringContainsString('; Secure', $header);
        // PHP url-decodes cookie values into $_COOKIE.
        $value = rawurldecode(substr(explode(';', $header)[0], 3));
        $read = ConsentCookie::read($cfg, ['oc' => $value], [], self::NOW + 5);
        $this->assertTrue($read->isValid());
        $this->assertTrue($read->allows('analytics'));
    }

    public function testRecordWrittenByBrowserFormat(): void
    {
        // Exactly what dist/open-consent.js stores (JSON, then encodeURIComponent).
        $js = '{"schema":1,"policy":"1","profile":"opt-in","method":"accept-all","categories":{"necessary":true,"functional":true,"analytics":true,"marketing":true},"createdAt":1800000000,"updatedAt":1800000000,"expiresAt":1815552000}';
        $s = ConsentCookie::read(Config::fromArray([]), ['open_consent' => $js], [], self::NOW);
        $this->assertTrue($s->allows('marketing'));
    }

    public function testOversizedCookieRejected(): void
    {
        $s = ConsentCookie::read(Config::fromArray([]), ['open_consent' => str_repeat('a', 5000)], [], self::NOW);
        $this->assertSame('corrupt', $s->reason());
    }

    public function testUnknownMethodOnCreate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ConsentState::create(Config::fromArray([]), [], 'implied');
    }
}
