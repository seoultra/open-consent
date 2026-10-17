import { test, eq, assert } from './harness.js';
import { readCookie, writeCookie, cleanupCookies, globToRegExp, candidateDomains } from '../src/storage.js';

test('storage: write/read/encode', () => {
  const name = 'oc_st_' + Math.random().toString(36).slice(2, 7);
  const ok = writeCookie(document, { name, days: 1, path: '/', sameSite: 'Lax', domain: '' }, '{"a":"b; c"}', location);
  assert(ok, 'write ok');
  eq(readCookie(document, name), '{"a":"b; c"}');
  document.cookie = name + '=; Max-Age=0; Path=/';
});

test('storage: malformed encoding returns null', () => {
  document.cookie = 'oc_bad=%E0%A4%A; Path=/';
  eq(readCookie(document, 'oc_bad'), null);
  document.cookie = 'oc_bad=; Max-Age=0; Path=/';
});

test('storage: glob patterns', () => {
  assert(globToRegExp('_ga_*').test('_ga_ABC'));
  assert(!globToRegExp('_ga').test('_gat'));
  assert(globToRegExp('a.b').test('a.b'));
  assert(!globToRegExp('a.b').test('axb'));
});

test('storage: candidate domains', () => {
  eq(candidateDomains('www.example.co.id'), ['', 'www.example.co.id', '.www.example.co.id', 'example.co.id', '.example.co.id', 'co.id', '.co.id']);
  eq(candidateDomains('127.0.0.1'), ['']);
});

test('storage: cleanup removes matching cookies only and protects consent cookie', () => {
  document.cookie = '_ga=GA1.1; Path=/';
  document.cookie = '_ga_XYZ=GS1; Path=/';
  document.cookie = 'keep_me=1; Path=/';
  document.cookie = 'open_consent_x=1; Path=/';
  const removed = cleanupCookies(document, ['_ga', '_ga_*', 'open_consent_*'], location, ['open_consent_x']);
  eq(removed.sort(), ['_ga', '_ga_XYZ']);
  eq(readCookie(document, '_ga'), null);
  eq(readCookie(document, 'keep_me'), '1');
  eq(readCookie(document, 'open_consent_x'), '1');
  document.cookie = 'keep_me=; Max-Age=0; Path=/';
  document.cookie = 'open_consent_x=; Max-Age=0; Path=/';
});
