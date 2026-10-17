<?php
/**
 * Uninstall: settings are kept unless the admin explicitly opted in to
 * deletion (Settings → OpenConsent → Advanced). Visitors' consent cookies
 * live in their browsers and are not touched.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$open_consent_settings = get_option( 'open_consent_settings', array() );
if ( is_array( $open_consent_settings ) && ! empty( $open_consent_settings['delete_on_uninstall'] ) ) {
	delete_option( 'open_consent_settings' );
}
