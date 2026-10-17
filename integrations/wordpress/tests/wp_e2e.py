#!/usr/bin/env python3
"""Browser test of the plugin on a running WordPress site.

Setup (see docs/wordpress.md → "Testing"): plugin active, the test mu-plugin
integrations/wordpress/tests/mu-plugin/oc-e2e.php installed, settings:
  blocked_handles = "fake-analytics = analytics", scripts_marketing = "<script>window.__pixel = 1;</script>",
  google_enabled = true, google_ga4_ids = "G-WPTEST123", cookie_policy_url = "https://example.com/cookies"
and a page containing [open_consent label="Change cookie settings"].

Usage: python3 wp_e2e.py http://127.0.0.1:8792/?page_id=9
"""
import sys
from playwright.sync_api import sync_playwright

ok = True


def check(name, cond, info=''):
    global ok
    ok = ok and bool(cond)
    print(('  ok   ' if cond else '  FAIL ') + name + ('' if cond else '  ' + str(info)))


def main(url):
    with sync_playwright() as p:
        b = p.chromium.launch()
        ctx = b.new_context()
        gt = []
        ctx.route('https://www.googletagmanager.com/**',
                  lambda r: (gt.append(r.request.url), r.fulfill(status=200, content_type='application/javascript', body='window.__gtag=1')))
        pg = ctx.new_page()
        errs = []
        pg.on('pageerror', lambda e: errs.append(str(e)))
        pg.goto(url)
        pg.wait_for_function('window.OpenConsent && OpenConsent.isInitialized()')
        sr = pg.locator('#open-consent')
        flags = lambda: pg.evaluate('() => [window.__fakeAnalytics, window.__fakeInline, window.__pixel]')
        check('banner on first visit', pg.evaluate("() => !document.getElementById('open-consent').shadowRoot.querySelector('[data-oc=banner]').hidden"))
        check('gated enqueued handle, its inline code and the marketing snippet did not run', flags() == [None, None, None], flags())
        first = pg.evaluate("() => { const d = window.dataLayer[0]; return [d[0], d[1], d[2].analytics_storage]; }")
        check('consent default first in dataLayer', first == ['consent', 'default', 'denied'], first)
        check('no gtag.js before consent', gt == [])
        order = pg.evaluate("() => { const s = [...document.querySelectorAll('script[src],script[data-src]')].map(x => x.getAttribute('src') || x.getAttribute('data-src')); return [s.findIndex(x => x.includes('open-consent.js')), s.findIndex(x => x.includes('fake-analytics'))]; }")
        check('engine before the gated script', 0 <= order[0] < order[1], order)
        sr.locator('[data-oc=banner] [data-oc-action=reject-all]').click()
        check('reject: still nothing ran', flags() == [None, None, None], flags())
        pg.get_by_role('button', name='Change cookie settings').click()
        check('[open_consent] button opens preferences', pg.evaluate("() => !document.getElementById('open-consent').shadowRoot.querySelector('[data-oc=overlay]').hidden"))
        sr.locator('input[data-oc-category=analytics]').check()
        sr.locator('[data-oc-action=save]').click()
        pg.wait_for_timeout(500)
        check('analytics only: gated handle + inline ran once, marketing snippet did not', flags() == [1, 1, None], flags())
        check('analytics only: GA4 loaded by the engine', len(gt) == 1 and 'G-WPTEST123' in gt[0], gt)
        links = pg.evaluate("() => [...document.getElementById('open-consent').shadowRoot.querySelectorAll('.oc-links a')].map(a => a.href)")
        check('cookie policy link rendered', 'https://example.com/cookies' in links, links)
        pg.reload()
        pg.wait_for_function('window.OpenConsent && OpenConsent.isInitialized()')
        pg.wait_for_timeout(300)
        check('reload: choice remembered, analytics runs at load, marketing not', flags() == [1, 1, None], flags())
        pg.evaluate('() => OpenConsent.withdrawConsent()')
        pg.reload()
        pg.wait_for_function('window.OpenConsent && OpenConsent.isInitialized()')
        pg.wait_for_timeout(300)
        check('withdraw → reload: nothing runs', flags() == [None, None, None], flags())
        check('no page errors', errs == [], errs)
        b.close()
    print('wordpress e2e:', 'PASS' if ok else 'FAIL')
    sys.exit(0 if ok else 1)


if __name__ == '__main__':
    main(sys.argv[1])
