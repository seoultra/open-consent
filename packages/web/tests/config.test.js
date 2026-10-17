import { test, eq, assert } from './harness.js';
import { validateConfig, isSafeUrl } from '../src/config.js';

function get(obj, path) {
  // Paths are dot-separated; category names may contain spaces but not dots.
  return path.split('.').reduce((o, k) => (o && Object.prototype.hasOwnProperty.call(o, k) ? o[k] : null), obj);
}

const cases = await (await fetch('../../../tests/fixtures/config-cases.json')).json();
cases.forEach((c) => {
  test('config fixture: ' + c.name, () => {
    const { config, warnings } = validateConfig(c.input);
    Object.keys(c.expect).forEach((p) => eq(get(config, p), c.expect[p], p));
    eq(warnings.length > 0, c.warn, 'warnings ' + JSON.stringify(warnings));
  });
});

test('config: necessary is always first', () => {
  const { config } = validateConfig({ categories: { social: {} } });
  eq(Object.keys(config.categories)[0], 'necessary');
});

test('config: isSafeUrl', () => {
  assert(isSafeUrl('https://a.b/c'));
  assert(isSafeUrl('/x'));
  assert(!isSafeUrl('//x'));
  assert(!isSafeUrl('data:text/html,1'));
  assert(!isSafeUrl('https://a.b/"onmouseover=1'));
});
