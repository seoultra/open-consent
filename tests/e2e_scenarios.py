"""End-to-end scenarios against the BUILT bundle (dist/open-consent.js) in Chromium.

The milestone flow: first visit → nothing runs; reject → still blocked;
analytics only → only analytics; withdraw → stopped (as far as possible).
Google's gtag.js is intercepted and replaced by a stub that records what it saw.
"""
import json

GTAG_STUB = """
window.__gtagLoads = (window.__gtagLoads || 0) + 1;
window.__dlAtLoad = (window.dataLayer || []).map(function (e) {
  return (e && typeof e.length === 'number' && !Array.isArray(e)) ? Array.prototype.slice.call(e) : e;
});
"""

results = []


def check(name, cond, info=''):
    results.append((name, bool(cond), info))
    print(('  ok   ' if cond else '  FAIL ') + name + ('' if cond else '  ' + str(info)))


def new_context(browser, **kw):
    ctx = browser.new_context(**kw)
    ctx.gtag_requests = []

    def handle(route):
        ctx.gtag_requests.append(route.request.url)
        route.fulfill(status=200, content_type='application/javascript', body=GTAG_STUB)

    ctx.route('https://www.googletagmanager.com/**', handle)
    # Anything else external must never be contacted by the engine.
    ctx.external = []

    def block(route):
        ctx.external.append(route.request.url)
        route.abort()

    ctx.route(lambda url: not url.startswith('http://127.0.0.1') and 'googletagmanager.com' not in url, block)
    return ctx


def dl(page):
    """gtag commands as lists; GTM-style event objects as dicts are skipped."""
    raw = page.evaluate("""() => (window.dataLayer || []).map(e =>
        (e && typeof e.length === 'number' && !Array.isArray(e)) ? Array.from(e) : e)""")
    return [e for e in raw if isinstance(e, list)]


def banner_visible(page):
    return page.evaluate("() => { const r = document.getElementById('open-consent'); const b = r && r.shadowRoot.querySelector('[data-oc=banner]'); return !!b && !b.hidden; }")


def ran(page):
    return page.evaluate('() => window.__ran || []')


def cookie_names(ctx):
    return sorted(c['name'] for c in ctx.cookies())


def sr_click(page, selector):
    page.locator('#open-consent').locator(selector).first.click()


def scenario_milestone(browser, base):
    url = base + '/tests/fixtures/standalone.html'
    ctx = new_context(browser)
    page = ctx.new_page()
    errors = []
    page.on('pageerror', lambda e: errors.append(str(e)))

    # 1. First visit
    page.goto(url)
    page.wait_for_function('window.OpenConsent && window.OpenConsent.isInitialized()')
    check('first visit: banner shown', banner_visible(page))
    d = dl(page)
    check('first visit: consent default is the FIRST dataLayer entry', d and d[0][:2] == ['consent', 'default'], d[:2])
    check('first visit: all ad/analytics signals denied', d[0][2]['analytics_storage'] == 'denied' and d[0][2]['ad_storage'] == 'denied'
          and d[0][2]['ad_user_data'] == 'denied' and d[0][2]['ad_personalization'] == 'denied', d[0])
    check('first visit: no protected script executed', ran(page) == [], ran(page))
    check('first visit: gtag.js not requested (basic mode)', ctx.gtag_requests == [], ctx.gtag_requests)
    check('first visit: no tracking cookies', not any(n.startswith('_ga') or n == '_fbp' for n in cookie_names(ctx)), cookie_names(ctx))
    check('invalid category script never runs', 'bogus' not in ran(page))

    # 2. Reject all
    sr_click(page, '[data-oc=banner] [data-oc-action=reject-all]')
    check('reject: banner hidden', not banner_visible(page))
    check('reject: scripts still blocked', ran(page) == [])
    upd = [e for e in dl(page) if e[:2] == ['consent', 'update']]
    check('reject: consent update sent, denied', upd and upd[-1][2]['analytics_storage'] == 'denied', upd)
    page.reload()
    page.wait_for_function('window.OpenConsent && window.OpenConsent.isInitialized()')
    check('reject → reload: no banner', not banner_visible(page))
    check('reject → reload: still blocked, no gtag', ran(page) == [] and ctx.gtag_requests == [])

    # 3. Analytics only, via the preferences dialog using the KEYBOARD
    page.locator('#manage').focus()
    page.keyboard.press('Enter')
    page.wait_for_function("() => !document.getElementById('open-consent').shadowRoot.querySelector('[data-oc=overlay]').hidden")
    active = page.evaluate("() => document.getElementById('open-consent').shadowRoot.activeElement?.getAttribute('data-oc')")
    check('preferences: focus moved into dialog', active == 'preferences', active)
    # Tab to the analytics switch and toggle with Space
    for _ in range(12):
        page.keyboard.press('Tab')
        cat = page.evaluate("() => document.getElementById('open-consent').shadowRoot.activeElement?.getAttribute('data-oc-category')")
        if cat == 'analytics':
            break
    check('preferences: analytics switch reachable by Tab', cat == 'analytics', cat)
    page.keyboard.press('Space')
    sr_click(page, '[data-oc=preferences] [data-oc-action=save]')
    page.wait_for_timeout(300)
    check('analytics only: analytics script ran', ran(page) == ['analytics'], ran(page))
    check('analytics only: gtag.js requested once for G- tag', len(ctx.gtag_requests) == 1 and 'G-E2ETEST01' in ctx.gtag_requests[0], ctx.gtag_requests)
    cfgs = [e[1] for e in dl(page) if e and e[0] == 'config']
    check('analytics only: only the analytics tag configured', cfgs == ['G-E2ETEST01'], cfgs)
    at_load = page.evaluate('window.__dlAtLoad')
    check('gtag.js saw default before its config', at_load and at_load[0][:2] == ['consent', 'default'], at_load and at_load[:1])
    last = [e for e in dl(page) if e[:2] == ['consent', 'update']][-1][2]
    check('analytics only: analytics granted, ads denied', last['analytics_storage'] == 'granted' and last['ad_storage'] == 'denied', last)
    focus_back = page.evaluate('() => document.activeElement && document.activeElement.id')
    check('preferences: focus returned to trigger', focus_back == 'manage', focus_back)
    check('analytics cookie set by permitted script', '_ga' in cookie_names(ctx))

    page.reload()
    page.wait_for_function('window.OpenConsent && window.OpenConsent.isInitialized()')
    page.wait_for_timeout(200)
    check('analytics only → reload: analytics runs at load, marketing not', ran(page) == ['analytics'], ran(page))
    d = dl(page)
    check('reload: default then immediate update before config', d[0][:2] == ['consent', 'default'] and
          [i for i, e in enumerate(d) if e[:2] == ['consent', 'update']][0] < [i for i, e in enumerate(d) if e and e[0] == 'config'][0])

    # 4. Withdraw
    page.evaluate('() => window.OpenConsent.withdrawConsent()')
    check('withdraw: analytics cookies deleted', '_ga' not in cookie_names(ctx), cookie_names(ctx))
    last = [e for e in dl(page) if e[:2] == ['consent', 'update']][-1][2]
    check('withdraw: Google update denied', last['analytics_storage'] == 'denied', last)
    before = len(ctx.gtag_requests)
    page.reload()
    page.wait_for_function('window.OpenConsent && window.OpenConsent.isInitialized()')
    page.wait_for_timeout(200)
    check('withdraw → reload: nothing runs, gtag not requested', ran(page) == [] and len(ctx.gtag_requests) == before, (ran(page), ctx.gtag_requests))
    check('withdraw → reload: no banner (choice recorded)', not banner_visible(page))
    check('reopen button visible after choice', page.evaluate("() => !document.getElementById('open-consent').shadowRoot.querySelector('[data-oc=reopen]').hidden"))
    check('engine contacted no external host', ctx.external == [], ctx.external)
    check('no page errors', errors == [], errors)
    ctx.close()


def scenario_keyboard_banner(browser, base):
    ctx = new_context(browser)
    page = ctx.new_page()
    page.goto(base + '/tests/fixtures/standalone.html')
    page.wait_for_function('window.OpenConsent && window.OpenConsent.isInitialized()')
    active = page.evaluate("() => document.getElementById('open-consent').shadowRoot.activeElement?.getAttribute('data-oc')")
    check('banner: receives focus on first show', active == 'banner', active)
    labels = []
    for _ in range(5):
        page.keyboard.press('Tab')
        labels.append(page.evaluate("() => document.getElementById('open-consent').shadowRoot.activeElement?.getAttribute('data-oc-action')"))
    check('banner: Tab order = policy links, then reject, accept, customize', labels == [None, None, 'reject-all', 'accept-all', 'customize'] and 'reject-all' in labels and 'accept-all' in labels and 'customize' in labels, labels)
    # Escape closes preferences and returns to banner
    sr_click(page, '[data-oc=banner] [data-oc-action=customize]')
    page.keyboard.press('Escape')
    hidden = page.evaluate("() => document.getElementById('open-consent').shadowRoot.querySelector('[data-oc=overlay]').hidden")
    check('preferences: Escape closes', hidden)
    check('preferences: Escape without choice keeps banner', banner_visible(page))
    # focus trap
    sr_click(page, '[data-oc=banner] [data-oc-action=customize]')
    inside = True
    for _ in range(15):
        page.keyboard.press('Tab')
        inside = inside and page.evaluate("() => { const sr = document.getElementById('open-consent').shadowRoot; return sr.querySelector('[data-oc=preferences]').contains(sr.activeElement); }")
    check('preferences: focus trapped inside dialog', inside)
    # equal prominence of accept/reject
    styles = page.evaluate("""() => { const sr = document.getElementById('open-consent').shadowRoot;
        const s = (a) => { const c = getComputedStyle(sr.querySelector('[data-oc=banner] [data-oc-action=' + a + ']')); return [c.backgroundColor, c.color, c.fontSize, c.fontWeight, c.minHeight]; };
        return [s('accept-all'), s('reject-all')]; }""")
    check('banner: Accept all and Reject all styled identically', styles[0] == styles[1], styles)
    ctx.close()


def scenario_mobile(browser, base):
    ctx = new_context(browser, viewport={'width': 375, 'height': 667}, device_scale_factor=2, is_mobile=True, has_touch=True)
    page = ctx.new_page()
    page.goto(base + '/tests/fixtures/standalone.html')
    page.wait_for_function('window.OpenConsent && window.OpenConsent.isInitialized()')
    page.wait_for_timeout(400)  # entry animation
    box = page.evaluate("""() => { const b = document.getElementById('open-consent').shadowRoot.querySelector('[data-oc=banner]').getBoundingClientRect();
        const btns = [...document.getElementById('open-consent').shadowRoot.querySelectorAll('[data-oc=banner] button')].map(x => x.getBoundingClientRect());
        return { left: b.left, right: b.right, top: b.top, bottom: b.bottom, vw: innerWidth, vh: innerHeight,
                 btnsInside: btns.every(r => r.left >= 0 && r.right <= innerWidth && r.height >= 44), sw: document.documentElement.scrollWidth }; }""")
    check('mobile: banner within viewport', box['left'] >= 0 and box['right'] <= box['vw'] and box['bottom'] <= box['vh'] + 1 and box['top'] >= 0, box)
    check('mobile: buttons fit and are >=44px tall', box['btnsInside'], box)
    check('mobile: no horizontal scroll', box['sw'] <= box['vw'], box)
    sr_click(page, '[data-oc=banner] [data-oc-action=customize]')
    m = page.evaluate("() => { const r = document.getElementById('open-consent').shadowRoot.querySelector('[data-oc=preferences]').getBoundingClientRect(); return [r.left, r.right, r.top, r.bottom, innerWidth, innerHeight]; }")
    check('mobile: preferences dialog fits viewport', m[0] >= 0 and m[1] <= m[4] and m[2] >= 0 and m[3] <= m[5], m)
    ctx.close()


def scenario_double_load(browser, base):
    ctx = new_context(browser)
    page = ctx.new_page()
    page.goto(base + '/tests/fixtures/double-load.html')
    page.wait_for_function('window.OpenConsent && window.OpenConsent.isInitialized()')
    n = page.evaluate("() => document.querySelectorAll('#open-consent').length")
    d = dl(page)
    check('double load: one widget', n == 1, n)
    check('double load: one consent default', len([e for e in d if e[:2] == ['consent', 'default']]) == 1, d)
    sr_click(page, '[data-oc=banner] [data-oc-action=accept-all]')
    page.wait_for_timeout(200)
    check('double load: each blocked script ran once', ran(page) == ['analytics', 'marketing'], ran(page))
    check('double load: gtag.js loaded once', len(ctx.gtag_requests) == 1, ctx.gtag_requests)
    ctx.close()


def scenario_advanced(browser, base):
    ctx = new_context(browser)
    page = ctx.new_page()
    page.goto(base + '/tests/fixtures/advanced.html')
    page.wait_for_function('window.__gtagLoads === 1', timeout=5000)
    at_load = page.evaluate('window.__dlAtLoad')
    check('advanced: gtag.js loads before consent', len(ctx.gtag_requests) == 1)
    check('advanced: default (denied) precedes js/config', at_load[0][:2] == ['consent', 'default'] and at_load[0][2]['ad_storage'] == 'denied', at_load[:3])
    check('advanced: protected scripts still blocked', ran(page) == [])
    ctx.close()


def scenario_embed(browser, base):
    ctx = new_context(browser)
    page = ctx.new_page()
    page.goto(base + '/tests/fixtures/embed.html')
    page.wait_for_function('window.OpenConsent && window.OpenConsent.isInitialized()', timeout=8000)
    title = page.evaluate("() => document.getElementById('open-consent').shadowRoot.querySelector('.oc-title').textContent")
    check('embed: remote config applied', title == 'REMOTE CONFIG', title)
    d = dl(page)
    check('embed: early restrictive default sent once, before config fetch', len([e for e in d if e[:2] == ['consent', 'default']]) == 1 and d[0][2]['analytics_storage'] == 'denied' and d[0][2]['wait_for_update'] >= 2000, d[:1])
    check('embed: config siteId', page.evaluate('OpenConsent.getConfig().siteId') == 'e2e-demo')

    page2 = ctx.new_page()
    page2.goto(base + '/tests/fixtures/embed-fallback.html')
    page2.wait_for_function('window.OpenConsent && window.OpenConsent.isInitialized()', timeout=8000)
    title = page2.evaluate("() => document.getElementById('open-consent').shadowRoot.querySelector('.oc-title').textContent")
    check('embed: falls back to local config when API fails', title == 'LOCAL FALLBACK', title)
    ctx.close()


def scenario_csp(browser, base):
    ctx = new_context(browser)
    page = ctx.new_page()
    violations = []
    page.on('console', lambda m: violations.append(m.text) if 'Content Security Policy' in m.text else None)
    page.goto(base + '/tests/fixtures/csp.html')
    page.wait_for_function('window.OpenConsent && window.OpenConsent.isInitialized()')
    pos = page.evaluate("() => getComputedStyle(document.getElementById('open-consent').shadowRoot.querySelector('[data-oc=banner]')).position")
    check('strict CSP: widget styled without unsafe-inline', pos == 'fixed', pos)
    sr_click(page, '[data-oc=banner] [data-oc-action=accept-all]')
    page.wait_for_timeout(200)
    check('strict CSP: nonce is carried to the executed blocked script', page.evaluate('window.__cspRan') == 1)
    check('strict CSP: no violations', violations == [], violations)
    ctx.close()


def run(browser, base):
    for fn in (scenario_csp, scenario_milestone, scenario_keyboard_banner, scenario_mobile, scenario_double_load, scenario_advanced, scenario_embed):
        try:
            fn(browser, base)
        except Exception as e:  # noqa: BLE001
            check(fn.__name__ + ' crashed', False, repr(e))
    failed = [r for r in results if not r[1]]
    print(f'e2e: {len(results) - len(failed)}/{len(results)} passed')
    return not failed
