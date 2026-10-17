<?php
/**
 * Plugin Name:       OpenConsent
 * Plugin URI:        https://seoultra.id/plugins/open-consent/
 * Description:       Free, open-source cookie consent with real script blocking and Google Consent Mode v2. No account, no API key, no remote calls.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      8.1
 * Author:            SEOULTRA
 * Author URI:        https://seoultra.id
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       open-consent
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OPEN_CONSENT_VERSION', '0.1.0' );
define( 'OPEN_CONSENT_FILE', __FILE__ );
define( 'OPEN_CONSENT_DIR', plugin_dir_path( __FILE__ ) );
define( 'OPEN_CONSENT_URL', plugin_dir_url( __FILE__ ) );

// The shared PHP library (copied from packages/core-php/src by bin/build.php).
// Skip our copy if a site already loads seoultra/open-consent via Composer.
if ( ! class_exists( '\\SeoUltra\\OpenConsent\\Config' ) ) {
	if ( ! defined( 'OPEN_CONSENT_PACKAGE_ROOT' ) ) {
		define( 'OPEN_CONSENT_PACKAGE_ROOT', OPEN_CONSENT_DIR );
	}
	spl_autoload_register(
		static function ( $class ) {
			$prefix = 'SeoUltra\\OpenConsent\\';
			if ( 0 !== strpos( $class, $prefix ) ) {
				return;
			}
			$file = OPEN_CONSENT_DIR . 'lib/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
			if ( is_readable( $file ) ) {
				require $file;
			}
		}
	);
}

require_once OPEN_CONSENT_DIR . 'includes/class-open-consent-settings.php';
require_once OPEN_CONSENT_DIR . 'includes/class-open-consent-blocker.php';
require_once OPEN_CONSENT_DIR . 'includes/class-open-consent-frontend.php';
require_once OPEN_CONSENT_DIR . 'includes/class-open-consent-admin.php';

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'open-consent', false, dirname( plugin_basename( OPEN_CONSENT_FILE ) ) . '/languages' );
		Open_Consent_Frontend::init();
		if ( is_admin() ) {
			Open_Consent_Admin::init();
		}
	}
);

register_activation_hook(
	__FILE__,
	static function () {
		// Store defaults once; never overwrite an existing configuration.
		if ( false === get_option( Open_Consent_Settings::OPTION, false ) ) {
			add_option( Open_Consent_Settings::OPTION, Open_Consent_Settings::defaults() );
		}
	}
);
