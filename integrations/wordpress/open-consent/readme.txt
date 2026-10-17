=== OpenConsent ===
Contributors: seoultra
Tags: cookie consent, cookie banner, consent mode, gdpr, privacy
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Free, open-source cookie consent with real script blocking and Google Consent Mode v2. No account, no API key, no remote calls.

== Description ==

OpenConsent shows an accessible cookie banner, stores the visitor's choice in a first-party cookie, and **actually keeps optional scripts from running** until the visitor allows them.

* Accept all, Reject all (same prominence) and per-category choices: Necessary, Functional, Analytics, Marketing.
* Floating "cookie preferences" button and the `[open_consent]` shortcode so visitors can change or withdraw consent at any time.
* Real blocking: scripts you paste in the settings, and any enqueued script handle you list, are printed inert (`type="text/plain"`) and executed only after consent.
* Google Consent Mode v2 (ad_storage, ad_user_data, ad_personalization, analytics_storage, plus functionality/personalization/security storage) in Basic or Advanced mode, with defaults sent before any Google tag.
* WP Consent API support (used by Site Kit and others) when that plugin is installed.
* Opt-in or opt-out model, Global Privacy Control, policy versioning (bump it to ask everyone again).
* Keyboard and screen-reader friendly, respects reduced motion, works on mobile. English and Indonesian built in.
* The same browser engine is available for any website and as a Composer package (`seoultra/open-consent`).

OpenConsent is a technical tool. Installing it does not by itself make a site compliant with GDPR, ePrivacy, CCPA or any other law — you decide which cookies you use, which category they belong to and which consent model applies to you.

== Installation ==

1. Upload the `open-consent` folder to `/wp-content/plugins/` or install the zip via Plugins → Add New → Upload.
2. Activate the plugin. The banner appears with restrictive defaults (opt-in, everything optional off).
3. Go to Settings → OpenConsent: set your privacy/cookie policy links and gate your tracking scripts.

== Frequently Asked Questions ==

= Does it block Google Analytics added by another plugin? =

Only if you tell it how: add that plugin's script handle under "Gate enqueued scripts" (e.g. `my-ga-plugin = analytics`), or let OpenConsent load GA4 itself by entering the measurement ID, or use Consent Mode (Google tags then adjust their behaviour to the consent signals). See the documentation for Site Kit and Google Tag Manager.

= Can it undo scripts that already ran? =

No. When consent is withdrawn OpenConsent sends Consent Mode updates, removes the category's cookies that are visible to JavaScript, unloads gated embeds and stops future execution. Code that already ran in the current page keeps running until the page is reloaded (enable "Reload after withdrawal" if you need that).

= Does it phone home? =

No. There are no remote requests, no telemetry and no account.

== Changelog ==

= 0.1.0 =
* First release.
