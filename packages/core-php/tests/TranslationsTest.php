<?php

declare(strict_types=1);

namespace SeoUltra\OpenConsent\Tests;

use PHPUnit\Framework\TestCase;
use SeoUltra\OpenConsent\Config;
use SeoUltra\OpenConsent\Translations;

final class TranslationsTest extends TestCase
{
    public function testEveryFileHasEnglishKeysAndValidates(): void
    {
        $en = Translations::get('en');
        $this->assertNotNull($en);
        foreach (Translations::available() as $code) {
            $t = Translations::get($code);
            $this->assertNotNull($t, $code);
            $this->assertSame(self::paths($en), self::paths($t), "key parity: $code");
            $cfg = Config::fromArray(['texts' => [$code => $t]]);
            $this->assertSame([], $cfg->warnings(), "$code validates as config texts");
        }
    }

    public function testResolveAndTextsFor(): void
    {
        $this->assertSame('id', Translations::resolve('id_ID'));
        $this->assertSame('en', Translations::resolve('en-GB'));
        $this->assertNull(Translations::resolve('xx_YY'));
        $this->assertSame([], Translations::textsFor('en_US'), 'built-in: nothing to inject');
        $this->assertSame([], Translations::textsFor('id_ID'));
        $this->assertNull(Translations::get('../en'));
    }

    /** @return list<string> */
    private static function paths(array $a, string $p = ''): array
    {
        $out = [];
        foreach ($a as $k => $v) {
            $out = array_merge($out, is_array($v) ? self::paths($v, "$p$k.") : ["$p$k"]);
        }
        sort($out);
        return $out;
    }
}
