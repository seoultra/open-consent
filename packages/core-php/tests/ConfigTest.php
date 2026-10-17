<?php

declare(strict_types=1);

namespace SeoUltra\OpenConsent\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SeoUltra\OpenConsent\Config;
use SeoUltra\OpenConsent\ConfigException;
use SeoUltra\OpenConsent\OpenConsent;

final class ConfigTest extends TestCase
{
    /** @return iterable<string, array{mixed, array<string, mixed>, bool}> */
    public static function sharedCases(): iterable
    {
        $cases = json_decode((string) file_get_contents(__DIR__ . '/../../../tests/fixtures/config-cases.json'), true, 64, JSON_THROW_ON_ERROR);
        foreach ($cases as $c) {
            yield $c['name'] => [$c['input'], $c['expect'], $c['warn']];
        }
    }

    /** Same fixture the JS validator is tested against: the two must agree. */
    #[DataProvider('sharedCases')]
    public function testSharedFixture(mixed $input, array $expect, bool $warn): void
    {
        $cfg = Config::fromArray($input);
        $arr = $cfg->toArray();
        foreach ($expect as $path => $value) {
            $this->assertSame($value, self::get($arr, $path), $path);
        }
        $this->assertSame($warn, $cfg->warnings() !== [], implode('; ', $cfg->warnings()));
    }

    public function testStrictModeThrows(): void
    {
        $this->expectException(ConfigException::class);
        Config::fromArrayStrict(['profile' => 'nope']);
    }

    public function testStrictModeAcceptsValid(): void
    {
        $cfg = Config::fromArrayStrict(['profile' => 'opt-out', 'google' => ['enabled' => true, 'tags' => ['G-ABCDE123']]]);
        $this->assertSame('opt-out', $cfg->profile());
        $this->assertSame(['functional', 'analytics', 'marketing'], $cfg->optionalCategories());
    }

    public function testInvalidJson(): void
    {
        try {
            Config::fromJson('{bad');
            $this->fail('expected exception');
        } catch (ConfigException $e) {
            $this->assertStringContainsString('invalid JSON', $e->problems()[0]);
        }
    }

    public function testToJsonIsScriptSafe(): void
    {
        $cfg = Config::fromArray(['texts' => ['en' => ['banner' => ['title' => '</script><script>alert(1)</script>']]]]);
        $json = $cfg->toJson();
        $this->assertStringNotContainsString('</script', $json);
        $this->assertSame('</script><script>alert(1)</script>', json_decode($json, true)['texts']['en']['banner']['title']);
        $this->assertStringContainsString('"texts":{}', Config::fromArray([])->toJson());
    }

    public function testVersionMatchesRepository(): void
    {
        $this->assertSame(trim((string) file_get_contents(__DIR__ . '/../../../VERSION')), OpenConsent::VERSION);
    }

    /** @param array<string, mixed> $arr */
    private static function get(array $arr, string $path): mixed
    {
        $cur = $arr;
        foreach (explode('.', $path) as $k) {
            if (!is_array($cur) || !array_key_exists($k, $cur)) {
                return null;
            }
            $cur = $cur[$k];
        }
        return $cur;
    }
}
