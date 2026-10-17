<?php
/**
 * WordPress integration checks for the OpenConsent plugin, run INSIDE a real
 * WordPress install with the plugin active:
 *
 *   wp eval-file integrations/wordpress/tests/wp-integration-test.php
 *
 * Exits non-zero on failure. Uses only public WordPress APIs.
 */

$GLOBALS['oc_results'] = array();
function oc_check( $name, $cond, $info = '' ) {
	$GLOBALS['oc_results'][] = array( $name, (bool) $cond );
	echo ( $cond ? '  ok   ' : '  FAIL ' ) . $name . ( $cond ? '' : '  ' . ( is_string( $info ) ? $info : wp_json_encode( $info ) ) ) . "\n";
}
function oc_reset_scripts() {
	$GLOBALS['wp_scripts'] = new WP_Scripts();
}
function oc_enqueue_front() {
	oc_reset_scripts();
	do_action( 'wp_enqueue_scripts' );
}

$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0];
$sub_id = wp_insert_user( array( 'user_login' => 'oc_sub_' . wp_generate_password( 6, false ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$editor_id = wp_insert_user( array( 'user_login' => 'oc_ed_' . wp_generate_password( 6, false ), 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );

// 1. Activation (fresh: deactivate, forget settings, activate again)
deactivate_plugins( 'open-consent/open-consent.php', true );
delete_option( 'open_consent_settings' );
$act = activate_plugin( 'open-consent/open-consent.php' );
oc_check( 'activation without errors', null === $act, is_wp_error( $act ) ? $act->get_error_message() : '' );
oc_check( 'plugin active', is_plugin_active( 'open-consent/open-consent.php' ) );
oc_check( 'defaults stored on activation', is_array( get_option( 'open_consent_settings' ) ) && 'opt-in' === get_option( 'open_consent_settings' )['profile'] );
oc_check( 'shared PHP library loaded', class_exists( '\\SeoUltra\\OpenConsent\\Config' ) && is_file( \SeoUltra\OpenConsent\Paths::defaultPolicy() ) );

// 2. Asset enqueuing + config
oc_enqueue_front();
oc_check( 'engine enqueued on the front end', wp_script_is( 'open-consent', 'enqueued' ) );
$reg = wp_scripts()->registered['open-consent'];
oc_check( 'engine printed in <head> (not footer)', empty( $reg->extra['group'] ) );
$before = implode( "\n", (array) ( $reg->extra['before'] ?? array() ) );
oc_check( 'config passed inline before the engine', false !== strpos( $before, 'window.OpenConsentConfig = {' ) );
$json = json_decode( trim( substr( $before, strpos( $before, '{' ) ), " ;\n" ), true );
oc_check( 'inline config is valid JSON with defaults', is_array( $json ) && 'opt-in' === $json['profile'] && false === $json['google']['enabled'] );
oc_check( 'cookie path follows home_url', '/' === $json['cookie']['path'] );

// 3. Shortcode
$sc = do_shortcode( '[open_consent label="Manage <b>cookies</b>"]' );
oc_check( 'shortcode renders a reopen button', false !== strpos( $sc, 'data-open-consent-open' ) && 0 === strpos( $sc, '<button type="button"' ) );
oc_check( 'shortcode escapes its label', false === strpos( $sc, '<b>' ) && false !== strpos( $sc, '&lt;b&gt;' ) );
$sc = do_shortcode( '[open_consent type="link" class="x&quot;y"]' );
oc_check( 'shortcode link variant', 0 === strpos( $sc, '<a href="#open-consent"' ) && false === strpos( $sc, 'x"y' ) );

// 4. Settings validation
wp_set_current_user( $admin->ID );
$in = array(
	'enabled'            => '1',
	'profile'            => 'assume-consent',
	'layout'             => 'box',
	'privacy_url'        => 'javascript:alert(1)',
	'cookie_policy_url'  => 'https://example.com/cookies',
	'color_accent'       => 'red;}body{display:none',
	'cookie_days'        => '99999',
	'policy_version'     => '2 b',
	'google_ga4_ids'     => 'g-abc12345, junk, G-<x>',
	'google_ads_ids'     => 'AW-12345678',
	'blocked_handles'    => "oc-test-tracker = analytics\nbad handle!! = x\nfoo = Marketing",
	'scripts_marketing'  => '<script>window.__pixel=1</script>',
	'banner_title'       => '<script>x</script>Hello',
);
$out = Open_Consent_Settings::sanitize( $in );
oc_check( 'invalid enum falls back', 'opt-in' === $out['profile'] && 'box' === $out['layout'] );
oc_check( 'javascript: URL rejected, https kept', '' === $out['privacy_url'] && 'https://example.com/cookies' === $out['cookie_policy_url'] );
oc_check( 'invalid colour falls back', '#1a56db' === $out['color_accent'] );
oc_check( 'cookie days clamped', 730 === $out['cookie_days'] );
oc_check( 'invalid policy version keeps old', '1' === $out['policy_version'] );
oc_check( 'Google IDs normalised and filtered', 'G-ABC12345' === $out['google_ga4_ids'] && 'AW-12345678' === $out['google_ads_ids'] );
oc_check( 'handle map parsed, junk dropped', "oc-test-tracker = analytics" === $out['blocked_handles'], $out['blocked_handles'] );
oc_check( 'banner title stripped of tags', 'Hello' === $out['banner_title'], $out['banner_title'] );
oc_check( 'admin (unfiltered_html) may save scripts', ! is_multisite() ? '<script>window.__pixel=1</script>' === $out['scripts_marketing'] : true );

wp_set_current_user( $editor_id );
$ed_can = current_user_can( 'unfiltered_html' );
wp_set_current_user( $sub_id );
$out2 = Open_Consent_Settings::sanitize( array( 'scripts_marketing' => '<script>evil()</script>' ) );
oc_check( 'user without unfiltered_html cannot change scripts', '' === $out2['scripts_marketing'] );

// 5. Permission checks
oc_check( 'subscriber cannot manage options', ! current_user_can( 'manage_options' ) );
add_filter( 'wp_die_handler', static fn() => static function ( $m ) { throw new RuntimeException( 'wp_die' ); } );
$died = false;
try {
	ob_start();
	Open_Consent_Admin::render();
} catch ( RuntimeException $e ) {
	$died = true;
} finally {
	ob_end_clean();
}
oc_check( 'settings page refuses non-admins', $died );
global $new_allowed_options, $allowed_options;
Open_Consent_Admin::register();
$allowed = $allowed_options ?? $new_allowed_options;
oc_check( 'option registered only in our settings group', isset( $allowed['open-consent'] ) && in_array( 'open_consent_settings', $allowed['open-consent'], true ) );
oc_check( 'options.php capability for the group is manage_options', 'manage_options' === apply_filters( 'option_page_capability_open-consent', 'manage_options' ) );
wp_set_current_user( $admin->ID );

// 6. Gating enqueued handles + admin snippets
update_option( 'open_consent_settings', array_merge( Open_Consent_Settings::get(), $out ) );
oc_reset_scripts();
do_action( 'wp_enqueue_scripts' );
wp_enqueue_script( 'oc-test-tracker', 'https://tracker.example/t.js', array(), '1', array( 'in_footer' => false ) );
wp_add_inline_script( 'oc-test-tracker', 'window.__trackerInline = 1;', 'after' );
do_action( 'wp_print_scripts' );
ob_start();
wp_scripts()->do_items( array( 'oc-test-tracker' ) );
$html = ob_get_clean();
$p_engine  = strpos( $html, 'open-consent.js' );
$p_tracker = strpos( $html, 'tracker.example' );
oc_check( 'engine printed before the gated handle', false !== $p_engine && false !== $p_tracker && $p_engine < $p_tracker, $html );
oc_check( 'gated handle printed inert with data-src', (bool) preg_match( '~<script[^>]*type="text/plain"[^>]*data-src="https://tracker.example/t.js\?ver=1"~', $html ) || (bool) preg_match( '~<script[^>]*data-src="https://tracker.example/t.js\?ver=1"[^>]*type="text/plain"~', $html ), $html );
oc_check( 'gated handle has no live src', ! preg_match( '~(?<!data-)src="https://tracker\.example~', $html ), $html );
oc_check( 'inline "after" code gated too', (bool) preg_match( '~<script[^>]*type="text/plain"[^>]*data-open-consent="analytics"[^>]*>\s*window.__trackerInline~', $html ) || (bool) preg_match( '~<script[^>]*data-open-consent="analytics"[^>]*type="text/plain"[^>]*>\s*window.__trackerInline~', $html ), $html );
ob_start();
Open_Consent_Frontend::print_admin_scripts();
$snip = ob_get_clean();
oc_check( 'admin snippet printed inert', false !== strpos( $snip, 'type="text/plain"' ) && false !== strpos( $snip, 'data-open-consent="marketing"' ) && false !== strpos( $snip, 'window.__pixel=1' ), $snip );

// 7. Blocker unit behaviour
$b = Open_Consent_Blocker::block( '<script type="module" src="/m.js" onload="x()"></script><script type="application/json">{"a":1}</script><iframe src="https://www.youtube-nocookie.com/embed/x"></iframe>', 'marketing' );
oc_check( 'blocker: module keeps data-type, handlers removed', false !== strpos( $b, 'data-type="module"' ) && false === strpos( $b, 'onload' ) );
oc_check( 'blocker: JSON blocks untouched', false !== strpos( $b, '<script type="application/json">' ) );
oc_check( 'blocker: iframe gated', false !== strpos( $b, '<iframe data-src="https://www.youtube-nocookie.com/embed/x"' ) || ( false !== strpos( $b, 'data-src="https://www.youtube-nocookie.com/embed/x"' ) && false === strpos( $b, ' src="https://www.youtube' ) ) );
oc_check( 'blocker: invalid category emits nothing', '' === Open_Consent_Blocker::block( '<script>x()</script>', 'Bad"' ) );

// 8. Incomplete / corrupted configuration (written past the sanitize callback, as a broken DB value would be)
remove_all_filters( 'sanitize_option_open_consent_settings' );
update_option( 'open_consent_settings', 'garbage' );
oc_enqueue_front();
oc_check( 'corrupted option still yields a safe config', wp_script_is( 'open-consent', 'enqueued' ) && 'opt-in' === Open_Consent_Settings::to_config()->profile() );
update_option( 'open_consent_settings', array( 'google_enabled' => true ) );
$cfg = Open_Consent_Settings::to_config();
oc_check( 'partial option merges with defaults', true === $cfg->toArray()['google']['enabled'] && 'basic' === $cfg->toArray()['google']['mode'] && array() === $cfg->warnings() );
update_option( 'open_consent_settings', array( 'disabled_pages' => '' . wp_insert_post( array( 'post_title' => 'No banner', 'post_status' => 'publish', 'post_type' => 'page' ) ) ) );
oc_check( 'filter can disable', ( add_filter( 'open_consent_enabled', '__return_false' ) && ! Open_Consent_Frontend::is_active() ) );
remove_filter( 'open_consent_enabled', '__return_false' );

// 9. WP Consent API declarations
oc_check( 'consent type declared (optin)', 'optin' === apply_filters( 'wp_get_consent_type', false ) );
oc_check( 'registered with WP Consent API', true === apply_filters( 'wp_consent_api_registered_open-consent/open-consent.php', false ) );

// 10. Bundled translation injected for a site language that is not compiled into the engine
$oc_locale = static fn() => 'ru_RU';
add_filter( 'locale', $oc_locale );
update_option( 'open_consent_settings', array( 'banner_title' => 'Custom' ) );
$texts = Open_Consent_Settings::to_config()->toArray()['texts'];
oc_check( 'site-language translation injected (ru_RU → ru)', isset( $texts['ru']['banner']['acceptAll'] ) && 'Accept all' !== $texts['ru']['banner']['acceptAll'], array_keys( $texts ) );
oc_check( 'custom banner title overrides the translation', 'Custom' === ( $texts['ru']['banner']['title'] ?? '' ) && 'Custom' === ( $texts['en']['banner']['title'] ?? '' ) );
remove_filter( 'locale', $oc_locale );
$texts = Open_Consent_Settings::to_config()->toArray()['texts'];
oc_check( 'built-in site language injects nothing extra', ! isset( $texts['ru'] ) );

// cleanup
update_option( 'open_consent_settings', Open_Consent_Settings::defaults() );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $sub_id );
wp_delete_user( $editor_id );

$failed = array_filter( $GLOBALS['oc_results'], static fn( $r ) => ! $r[1] );
printf( "wordpress: %d/%d passed\n", count( $GLOBALS['oc_results'] ) - count( $failed ), count( $GLOBALS['oc_results'] ) );
if ( $failed ) {
	exit( 1 );
}
