# WordPress example

1. Zip the plugin: `bin/package-wordpress.sh` → `build/open-consent-wordpress-0.1.0.zip`.
2. WordPress admin → Plugins → Add New → Upload Plugin → activate **OpenConsent**.
3. Settings → OpenConsent:
   - Privacy policy URL (empty = the page from Settings → Privacy).
   - *Script blocking → Gate enqueued scripts*, one per line, e.g.
     ```
     google_gtagjs = analytics
     facebook-pixel = marketing
     ```
     (find handles in the page source: `id="<handle>-js"`).
   - Or paste a pixel into *Marketing scripts*.
4. Put `[open_consent]` in your footer or privacy page so visitors can change their choice.
5. Test in a private window: no gated script runs until you allow its category.
