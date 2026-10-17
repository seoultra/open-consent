#!/usr/bin/env python3
"""Run the JS unit tests (packages/web/tests/run.html) and the e2e scenarios in headless Chromium.

Usage: python3 tests/run_browser_tests.py [--unit|--e2e]
Requires: php (built-in server) and Python Playwright with Chromium.
"""
import contextlib, os, socket, subprocess, sys, time

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, 'tests'))


def free_port():
    with contextlib.closing(socket.socket()) as s:
        s.bind(('127.0.0.1', 0))
        return s.getsockname()[1]


@contextlib.contextmanager
def php_server():
    port = free_port()
    proc = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', ROOT], cwd=ROOT,
                            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    for _ in range(50):
        try:
            socket.create_connection(('127.0.0.1', port), 0.2).close()
            break
        except OSError:
            time.sleep(0.1)
    try:
        yield f'http://127.0.0.1:{port}'
    finally:
        proc.terminate()
        proc.wait(5)


def run_unit(browser, base):
    page = browser.new_page()
    errors = []
    page.on('pageerror', lambda e: errors.append(str(e)))
    page.goto(base + '/packages/web/tests/run.html')
    page.wait_for_function('window.__results !== undefined', timeout=30000)
    results = page.evaluate('window.__results')
    failed = [r for r in results if not r['ok']]
    for r in results:
        print(('  ok   ' if r['ok'] else '  FAIL ') + r['name'])
        if not r['ok']:
            print('       ' + r['error'].replace('\n', '\n       '))
    for e in errors:
        print('  PAGE ERROR ' + e)
    print(f'unit: {len(results) - len(failed)}/{len(results)} passed')
    page.close()
    return not failed and not errors


def main():
    from playwright.sync_api import sync_playwright
    import e2e_scenarios
    mode = sys.argv[1] if len(sys.argv) > 1 else '--all'
    ok = True
    with php_server() as base, sync_playwright() as p:
        browser = p.chromium.launch()
        if mode in ('--all', '--unit'):
            ok = run_unit(browser, base) and ok
        if mode in ('--all', '--e2e'):
            ok = e2e_scenarios.run(browser, base) and ok
        browser.close()
    sys.exit(0 if ok else 1)


if __name__ == '__main__':
    main()
