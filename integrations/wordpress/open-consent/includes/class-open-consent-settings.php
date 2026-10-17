<?php
/**
 * Option storage, sanitization, and translation of WordPress settings into the
 * shared OpenConsent configuration (validated by SeoUltra\OpenConsent\Config).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Open_Consent_Settings {

	const OPTION = 'open_consent_settings';

	/** Optional categories that can carry admin-provided scripts. */
	const SCRIPT_CATEGORIES = array( 'functional', 'analytics', 'marketing' );

	public static function defaults() {
		return array(
			'enabled'            => true,
			'profile'            => 'opt-in',
			'policy_version'     => '1',
			'privacy_url'        => '',
			'cookie_policy_url'  => '',
			'cookie_days'        => 180,
			'lang'               => 'auto',
			'layout'             => 'bar',
			'position'           => 'bottom',
			'reopen_button'      => true,
			'reopen_position'    => 'left',
			'color_accent'       => '#1a56db',
			'color_background'   => '#ffffff',
			'color_text'         => '#1f2328',
			'gpc'                => true,
			'reload_on_withdraw' => false,
			'banner_title'       => '',
			'banner_text'        => '',
			'disabled_pages'     => '',
			'google_enabled'     => false,
			'google_mode'        => 'basic',
			'google_ga4_ids'     => '',
			'google_ads_ids'     => '',
			'google_region'      => '',
			'google_wait'        => 500,
			'google_url_passthrough' => false,
			'google_ads_data_redaction' => true,
			'blocked_handles'    => '',
			'scripts_functional' => '',
			'scripts_analytics'  => '',
			'scripts_marketing'  => '',
			'delete_on_uninstall' => false,
		);
	}

	public static function get() {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Sanitize callback for register_setting(). Every field is validated against
	 * a whitelist; anything else falls back to the stored or default value.
	 */
	public static function sanitize( $input ) {
		$old = self::get();
		$d   = self::defaults();
		$in  = is_array( $input ) ? wp_unslash( $input ) : array();
		$out = array();

		foreach ( array( 'enabled', 'reopen_button', 'gpc', 'reload_on_withdraw', 'google_enabled', 'google_url_passthrough', 'google_ads_data_redaction', 'delete_on_uninstall' ) as $k ) {
			$out[ $k ] = ! empty( $in[ $k ] );
		}

		$enums = array(
			'profile'         => array( 'opt-in', 'opt-out' ),
			'layout'          => array( 'bar', 'box' ),
			'position'        => array( 'bottom', 'top', 'bottom-left', 'bottom-right', 'center' ),
			'reopen_position' => array( 'left', 'right' ),
			'google_mode'     => array( 'basic', 'advanced' ),
		);
		foreach ( $enums as $k => $allowed ) {
			$v         = isset( $in[ $k ] ) ? (string) $in[ $k ] : $d[ $k ];
			$out[ $k ] = in_array( $v, $allowed, true ) ? $v : $d[ $k ];
		}

		$pv                    = isset( $in['policy_version'] ) ? trim( (string) $in['policy_version'] ) : $old['policy_version'];
		$out['policy_version'] = preg_match( '/^[A-Za-z0-9._-]{1,32}$/D', $pv ) ? $pv : $old['policy_version'];

		$lang        = isset( $in['lang'] ) ? trim( (string) $in['lang'] ) : 'auto';
		$out['lang'] = preg_match( '/^(auto|[a-z]{2,3}(-[A-Za-z0-9]{2,8})?)$/D', $lang ) ? $lang : 'auto';

		$out['cookie_days'] = isset( $in['cookie_days'] ) ? min( 730, max( 1, absint( $in['cookie_days'] ) ) ) : $d['cookie_days'];
		$out['google_wait'] = isset( $in['google_wait'] ) ? min( 10000, absint( $in['google_wait'] ) ) : $d['google_wait'];

		foreach ( array( 'privacy_url', 'cookie_policy_url' ) as $k ) {
			$url       = isset( $in[ $k ] ) ? esc_url_raw( trim( (string) $in[ $k ] ), array( 'http', 'https' ) ) : '';
			$out[ $k ] = \SeoUltra\OpenConsent\Config::isSafeUrl( $url ) ? $url : '';
		}

		foreach ( array( 'color_accent', 'color_background', 'color_text' ) as $k ) {
			$c         = isset( $in[ $k ] ) ? sanitize_hex_color( (string) $in[ $k ] ) : '';
			$out[ $k ] = $c ? $c : $d[ $k ];
		}

		$out['banner_title'] = isset( $in['banner_title'] ) ? mb_substr( sanitize_text_field( (string) $in['banner_title'] ), 0, 200 ) : '';
		$out['banner_text']  = isset( $in['banner_text'] ) ? mb_substr( sanitize_textarea_field( (string) $in['banner_text'] ), 0, 2000 ) : '';

		$out['disabled_pages'] = isset( $in['disabled_pages'] ) ? implode( ',', array_filter( array_map( 'absint', preg_split( '/[\s,]+/', (string) $in['disabled_pages'] ) ) ) ) : '';

		$out['google_ga4_ids'] = self::clean_list( $in['google_ga4_ids'] ?? '', '/^G-[A-Z0-9]{4,24}$/D' );
		$out['google_ads_ids'] = self::clean_list( $in['google_ads_ids'] ?? '', '/^(AW|DC)-[A-Z0-9-]{4,24}$/D' );
		$out['google_region']  = self::clean_list( $in['google_region'] ?? '', '/^[A-Z]{2}(-[A-Z0-9]{1,3})?$/D' );

		$out['blocked_handles'] = self::clean_handles( $in['blocked_handles'] ?? '' );

		// Raw <script> markup is only accepted from users who may post unfiltered HTML.
		foreach ( self::SCRIPT_CATEGORIES as $cat ) {
			$k = 'scripts_' . $cat;
			if ( current_user_can( 'unfiltered_html' ) ) {
				$out[ $k ] = isset( $in[ $k ] ) ? trim( (string) $in[ $k ] ) : '';
			} else {
				$out[ $k ] = $old[ $k ];
				if ( ! empty( $in[ $k ] ) && $in[ $k ] !== $old[ $k ] ) {
					add_settings_error( self::OPTION, 'open_consent_unfiltered', __( 'Scripts were not changed: your account is not allowed to add unfiltered HTML.', 'open-consent' ) );
				}
			}
		}

		$warnings = self::to_config( $out )->warnings();
		if ( $warnings ) {
			add_settings_error( self::OPTION, 'open_consent_config', __( 'Some settings were invalid and were replaced by safe defaults:', 'open-consent' ) . ' ' . implode( '; ', $warnings ) );
		}
		return $out;
	}

	private static function clean_list( $raw, $re ) {
		$items = preg_split( '/[\s,]+/', strtoupper( (string) $raw ) );
		$items = array_values( array_unique( array_filter( $items, static fn( $i ) => '' !== $i && preg_match( $re, $i ) ) ) );
		return implode( ',', $items );
	}

	/** Lines of "handle = category[ category]". */
	private static function clean_handles( $raw ) {
		$lines = array();
		foreach ( preg_split( '/\R/', (string) $raw ) as $line ) {
			if ( preg_match( '/^\s*([A-Za-z0-9_.\-]+)\s*=\s*([a-z][a-z0-9_ -]*)\s*$/D', $line, $m ) ) {
				$lines[] = $m[1] . ' = ' . preg_replace( '/\s+/', ' ', trim( $m[2] ) );
			}
		}
		return implode( "\n", $lines );
	}

	/** @return array<string, string> handle => "cat1 cat2" */
	public static function blocked_handles( $s = null ) {
		$s   = $s ?? self::get();
		$map = array();
		foreach ( preg_split( '/\R/', (string) $s['blocked_handles'] ) as $line ) {
			if ( preg_match( '/^([A-Za-z0-9_.\-]+) = ([a-z][a-z0-9_ -]*)$/D', $line, $m ) ) {
				$map[ $m[1] ] = $m[2];
			}
		}
		return $map;
	}

	/** Build and validate the browser configuration from settings. */
	public static function to_config( $s = null ) {
		$s    = $s ?? self::get();
		$tags = array();
		foreach ( array_filter( explode( ',', $s['google_ga4_ids'] ) ) as $id ) {
			$tags[] = array( 'id' => $id, 'category' => 'analytics' );
		}
		foreach ( array_filter( explode( ',', $s['google_ads_ids'] ) ) as $id ) {
			$tags[] = array( 'id' => $id, 'category' => 'marketing' );
		}
		$privacy = $s['privacy_url'] ? $s['privacy_url'] : (string) get_privacy_policy_url();

		$cfg = array(
			'policyVersion'    => $s['policy_version'],
			'profile'          => $s['profile'],
			'lang'             => $s['lang'],
			'gpc'              => (bool) $s['gpc'],
			'reloadOnWithdraw' => (bool) $s['reload_on_withdraw'],
			'cookie'           => array(
				'days'     => (int) $s['cookie_days'],
				'path'     => self::cookie_path(),
				'sameSite' => 'Lax',
			),
			'links'            => array(
				'privacyPolicy' => \SeoUltra\OpenConsent\Config::isSafeUrl( $privacy ) ? $privacy : '',
				'cookiePolicy'  => $s['cookie_policy_url'],
			),
			'ui'               => array(
				'layout'         => $s['layout'],
				'position'       => $s['position'],
				'reopenButton'   => (bool) $s['reopen_button'],
				'reopenPosition' => $s['reopen_position'],
				'theme'          => array(
					'accent'     => $s['color_accent'],
					'background' => $s['color_background'],
					'text'       => $s['color_text'],
				),
			),
			'google'           => array(
				'enabled'          => (bool) $s['google_enabled'],
				'mode'             => $s['google_mode'],
				'waitForUpdate'    => (int) $s['google_wait'],
				'region'           => array_values( array_filter( explode( ',', $s['google_region'] ) ) ),
				'urlPassthrough'   => (bool) $s['google_url_passthrough'],
				'adsDataRedaction' => (bool) $s['google_ads_data_redaction'],
				'tags'             => $tags,
			),
		);

		// Bundled translation for the site language when it is not compiled into the engine.
		$locale        = (string) get_locale();
		$cfg['texts']  = \SeoUltra\OpenConsent\Translations::textsFor( $locale );
		$code          = \SeoUltra\OpenConsent\Translations::resolve( $locale ) ?? 'en';
		$banner        = array();
		if ( '' !== $s['banner_title'] ) {
			$banner['title'] = $s['banner_title'];
		}
		if ( '' !== $s['banner_text'] ) {
			$banner['text'] = $s['banner_text'];
		}
		if ( $banner ) {
			// Custom copy applies to the site language and to English (the fallback).
			foreach ( array_unique( array( 'en', $code ) ) as $lc ) {
				$cfg['texts'][ $lc ]['banner'] = array_merge( $cfg['texts'][ $lc ]['banner'] ?? array(), $banner );
			}
		}

		/**
		 * Filter the OpenConsent browser configuration before validation.
		 *
		 * @param array $cfg Configuration (see docs/consent-model.md).
		 * @param array $s   Plugin settings.
		 */
		$cfg = apply_filters( 'open_consent_config', $cfg, $s );
		return \SeoUltra\OpenConsent\Config::fromArray( $cfg );
	}

	private static function cookie_path() {
		$path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		return ( is_string( $path ) && preg_match( '~^/[A-Za-z0-9._\~/-]*$~D', $path ) ) ? $path : '/';
	}
}
