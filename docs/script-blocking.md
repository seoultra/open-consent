# Script blocking

A browser never executes `<script type="text/plain">`. OpenConsent re-creates such
scripts as real ones only after consent — so tracking code stays inert **even if the
OpenConsent file fails to load**.

## Declarative markup

```html
<!-- inline -->
<script type="text/plain" data-open-consent="analytics">
  /* runs once analytics is granted */
</script>

<!-- external: data-src instead of src -->
<script type="text/plain" data-open-consent="marketing" data-src="https://connect.example/pixel.js"></script>

<!-- needs BOTH categories -->
<script type="text/plain" data-open-consent="analytics marketing">...</script>

<!-- ES module -->
<script type="text/plain" data-type="module" data-open-consent="functional" data-src="/js/widget.mjs"></script>

<!-- embeds: a placeholder with an "Allow and show" button is shown until consent -->
<iframe data-open-consent="marketing" data-src="https://www.youtube-nocookie.com/embed/ID" width="560" height="315"></iframe>
```

Rules:

- Category names must exist in your config. **Empty or unknown categories never run**
  (the element gets `data-oc-state="invalid"` and a console warning).
- `necessary` is always granted, so `data-open-consent="necessary"` runs immediately.
- Each element runs **at most once** per page view.
- Attributes are copied to the new script except `type`, `src`, `data-src`,
  `data-open-consent`, `data-type`; the `nonce` is carried over (strict CSP works —
  tested). `data-src` must be http(s) or a relative URL; `javascript:`/`data:` are refused.
- Elements added later (by other scripts) are picked up by a `MutationObserver`.

## Ordering

Activations are serialised in document order. An external script **without** `async`
must finish loading (or fail) before the next blocked element runs, so a library and
the inline code that uses it can be declared one after the other. Add `async` to an
external blocked script if nothing depends on it.

Scripts that relied on `document.write`, on running before `DOMContentLoaded`, or on
being parsed at a specific position may not work when replayed; load those vendors
through their consent-aware API instead, or use `whenAllowed`.

## Programmatic

```js
OpenConsent.whenAllowed('analytics', function () { /* runs once, when granted */ });
OpenConsent.loadScript('https://cdn.example/sdk.js', 'marketing', { crossorigin: 'anonymous' });
```

## Withdrawal

When a category is revoked (reject, withdraw, or a custom change):

1. future execution stops (nothing new runs);
2. Google Consent Mode receives an `update` with `denied`;
3. cookies matching the category's `cookies` patterns are deleted for the current host,
   its parent domains, and the paths of the current URL — only cookies visible to
   JavaScript (not `HttpOnly`, not third-party);
4. gated iframes are unloaded and the placeholder returns;
5. with `reloadOnWithdraw: true` the page reloads, which is the only way to stop code
   that already ran.

Data already sent to a vendor cannot be recalled by the browser. Use the vendor's own
deletion tools for that.

## WordPress

The plugin rewrites enqueued script handles (including their inline `before`/`after`
code) and admin-pasted snippets into this markup. See [wordpress.md](wordpress.md).
