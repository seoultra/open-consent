<?php

declare(strict_types=1);

namespace SeoUltra\OpenConsent\Tests;

use PHPUnit\Framework\TestCase;
use SeoUltra\OpenConsent\Config;
use SeoUltra\OpenConsent\Frontend;
use SeoUltra\OpenConsent\Paths;

final class FrontendTest extends TestCase
{
    public function testHeadOrderAndEscaping(): void
    {
        $html = Frontend::head(Config::fromArray(['links' => ['privacyPolicy' => '/privacy?a=1&b=2']]), '/assets/open-consent.js', 'sha384-abc');
        $this->assertMatchesRegularExpression('~^<script type="application/json" id="open-consent-config">.*</script>\n<script src="/assets/open-consent.js" integrity="sha384-abc" crossorigin="anonymous"></script>$~s', $html);
        $this->assertStringContainsString('\\u0026', $html);
        $this->assertStringNotContainsString('a=1&b', $html);
    }

    public function testBlockedMarkup(): void
    {
        $this->assertSame(
            '<script type="text/plain" data-open-consent="analytics marketing">gtag("x")</script>',
            Frontend::blockedScript(['analytics', 'marketing'], 'gtag("x")')
        );
        $this->assertSame(
            '<script type="text/plain" data-open-consent="analytics" data-src="https://example.com/a.js?x=1&amp;y=2" async></script>',
            Frontend::blockedExternalScript('analytics', 'https://example.com/a.js?x=1&y=2', ['async' => ''])
        );
        $this->assertStringContainsString('data-src="https://www.youtube-nocookie.com/embed/x"', Frontend::blockedIframe('marketing', 'https://www.youtube-nocookie.com/embed/x', ['width' => '560']));
        $this->assertSame('<button type="button" data-open-consent-open>Cookie &lt;settings&gt;</button>', Frontend::preferencesButton('Cookie <settings>'));
    }

    public function testRejectsScriptBreakout(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Frontend::blockedScript('analytics', 'a</script><script>alert(1)');
    }

    public function testRejectsEventHandlerAttributes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Frontend::blockedExternalScript('analytics', '/a.js', ['onload' => 'alert(1)']);
    }

    public function testRejectsUnsafeUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Frontend::blockedIframe('marketing', 'javascript:alert(1)');
    }

    public function testRejectsBadCategory(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Frontend::blockedScript('Analytics"', 'x');
    }

    public function testBundleShipsWithPackage(): void
    {
        $this->assertFileExists(Paths::browserBundle());
        $this->assertStringStartsWith('sha384-', Paths::browserBundleIntegrity());
        $this->assertSame(Paths::browserBundleIntegrity(), 'sha384-' . base64_encode(hash('sha384', (string) file_get_contents(Paths::browserBundle()), true)));
    }
}
