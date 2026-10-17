<?php
/**
 * Front-end wiring: loads the shared browser engine, passes it the validated
 * configuration, gates registered script handles, and provides the shortcode.
 * No consent logic lives here — the browser engine enforces everything.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Open_Consent_Frontend {

	const HANDLE = 'open-consent';

	/** Script handles printed by Google Site Kit's gtag integration. */
	const GOOGLE_HANDLES = array( 'google_gtagjs' );

	public static function init() {
		add_shortcode( 'open_consent', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 1 );
		add_filter( 'script_loader_tag', array( __CLASS__, 'gate_handles' ), 20, 3 );
		add_action( 'wp_print_scripts', array( __CLASS__, 'load_before_google' ), 1 );
		add_action( 'wp_footer', array( __CLASS__, 'print_admin_scripts' ), 5 );
		self::wp_consent_api();
	}

	/** Whether the banner/engine runs on the current request. */
	public static function is_active() {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_feed() || is_embed() ) {
			return false;
		}
		$s       = Open_Consent_Settings::get();
		$enabled = (bool) $s['enabled'];
		if ( $enabled && '' !== $s['disabled_pages'] && is_singular() ) {
			$ids     = array_map( 'absint', explode( ',', $s['disabled_pages'] ) );
			$enabled = ! in_array( (int) get_queried_object_id(), $ids, true );
		}
		/**
		 * Filter whether OpenConsent runs on this request.
		 *
		 * @param bool $enabled
		 */
		return (bool) apply_filters( 'open_consent_enabled', $enabled );
	}

	public static function enqueue() {
		if ( ! self::is_active() ) {
			return;
		}
		$config = Open_Consent_Settings::to_config();
		// Loaded in <head>, synchronously: Consent Mode defaults must exist before Google tags run.
		wp_register_script( self::HANDLE, OPEN_CONSENT_URL . 'assets/js/open-consent.js', array(), OPEN_CONSENT_VERSION, array( 'in_footer' => false ) );
		wp_add_inline_script( self::HANDLE, 'window.OpenConsentConfig = ' . $config->toJson() . ';', 'before' );
		wp_enqueue_script( self::HANDLE );

		if ( function_exists( 'wp_has_consent' ) ) {
			wp_enqueue_script( 'open-consent-wp-consent-api', OPEN_CONSENT_URL . 'assets/js/wp-consent-bridge.js', array( self::HANDLE ), OPEN_CONSENT_VERSION, array( 'in_footer' => false ) );
		}
	}

	/** Make known Google tag handles depend on the engine so it always prints first. */
	public static function load_before_google() {
		if ( ! self::is_active() ) {
			return;
		}
		$scripts = wp_scripts();
		$handles = array_merge( self::GOOGLE_HANDLES, array_keys( Open_Consent_Settings::blocked_handles() ) );
		foreach ( $handles as $h ) {
			if ( isset( $scripts->registered[ $h ] ) && self::HANDLE !== $h && ! in_array( self::HANDLE, $scripts->registered[ $h ]->deps, true ) ) {
				$scripts->registered[ $h ]->deps[] = self::HANDLE;
			}
		}
	}

	/** Rewrite configured handles (and their inline before/after code) into consent-gated markup. */
	public static function gate_handles( $tag, $handle, $src ) {
		if ( self::HANDLE === $handle || ! self::is_active() ) {
			return $tag;
		}
		$map = Open_Consent_Settings::blocked_handles();
		if ( ! isset( $map[ $handle ] ) ) {
			return $tag;
		}
		return Open_Consent_Blocker::block( $tag, $map[ $handle ] );
	}

	/** Admin-provided snippets per category, printed inert. */
	public static function print_admin_scripts() {
		if ( ! self::is_active() ) {
			return;
		}
		$s = Open_Consent_Settings::get();
		foreach ( Open_Consent_Settings::SCRIPT_CATEGORIES as $cat ) {
			$html = (string) $s[ 'scripts_' . $cat ];
			if ( '' !== trim( $html ) ) {
				// Saved only by users with unfiltered_html; made inert before output.
				echo Open_Consent_Blocker::block( $html, $cat ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
	}

	/**
	 * [open_consent] — a button that reopens the preferences dialog.
	 * Attributes: label, class, type="button|link".
	 */
	public static function shortcode( $atts ) {
		$a = shortcode_atts(
			array(
				'label' => __( 'Cookie preferences', 'open-consent' ),
				'class' => '',
				'type'  => 'button',
			),
			$atts,
			'open_consent'
		);
		$class = implode( ' ', array_map( 'sanitize_html_class', preg_split( '/\s+/', 'open-consent-manage ' . $a['class'] ) ) );
		if ( 'link' === $a['type'] ) {
			return '<a href="#open-consent" class="' . esc_attr( trim( $class ) ) . '" data-open-consent-open>' . esc_html( $a['label'] ) . '</a>';
		}
		return '<button type="button" class="' . esc_attr( trim( $class ) ) . '" data-open-consent-open>' . esc_html( $a['label'] ) . '</button>';
	}

	/**
	 * WP Consent API (https://wordpress.org/plugins/wp-consent-api/) integration:
	 * declare the consent type and register this plugin; the JS bridge forwards
	 * choices with wp_set_consent(). Site Kit's Consent Mode uses this API.
	 */
	private static function wp_consent_api() {
		$plugin = plugin_basename( OPEN_CONSENT_FILE );
		add_filter( "wp_consent_api_registered_{$plugin}", '__return_true' );
		add_filter(
			'wp_get_consent_type',
			static function ( $type ) {
				$s = Open_Consent_Settings::get();
				return 'opt-out' === $s['profile'] ? 'optout' : 'optin';
			}
		);
	}
}
