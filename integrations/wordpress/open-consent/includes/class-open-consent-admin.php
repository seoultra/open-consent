<?php
/**
 * Settings → OpenConsent. Uses the Settings API (options.php handles the
 * nonce and the manage_options capability check).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Open_Consent_Admin {

	const PAGE = 'open-consent';

	/** Other consent plugins whose banner/Consent Mode would compete with ours. */
	const KNOWN_CMPS = array(
		'complianz-gdpr/complianz-gpdr.php'          => 'Complianz',
		'complianz-gdpr-premium/complianz-gpdr-premium.php' => 'Complianz Premium',
		'cookie-law-info/cookie-law-info.php'        => 'CookieYes',
		'cookiebot/cookiebot.php'                    => 'Cookiebot',
		'cookie-notice/cookie-notice.php'            => 'Cookie Notice',
		'gdpr-cookie-compliance/moove-gdpr.php'      => 'GDPR Cookie Compliance',
		'iubenda-cookie-law-solution/iubenda_cookie_solution.php' => 'iubenda',
		'real-cookie-banner/index.php'               => 'Real Cookie Banner',
	);

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( OPEN_CONSENT_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function menu() {
		add_options_page( __( 'OpenConsent', 'open-consent' ), __( 'OpenConsent', 'open-consent' ), 'manage_options', self::PAGE, array( __CLASS__, 'render' ) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Settings', 'open-consent' ) . '</a>' );
		return $links;
	}

	public static function register() {
		register_setting(
			self::PAGE,
			Open_Consent_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'Open_Consent_Settings', 'sanitize' ),
				'default'           => Open_Consent_Settings::defaults(),
			)
		);

		$sections = array(
			'general'  => __( 'Banner & policy', 'open-consent' ),
			'look'     => __( 'Appearance', 'open-consent' ),
			'google'   => __( 'Google Consent Mode v2', 'open-consent' ),
			'blocking' => __( 'Script blocking', 'open-consent' ),
			'advanced' => __( 'Advanced', 'open-consent' ),
		);
		foreach ( $sections as $id => $title ) {
			add_settings_section( 'oc_' . $id, $title, array( __CLASS__, 'section_' . $id ), self::PAGE );
		}

		$f = array(
			array( 'enabled', __( 'Show the consent banner', 'open-consent' ), 'checkbox', 'general' ),
			array( 'profile', __( 'Consent model', 'open-consent' ), 'select', 'general', array(
				'opt-in'  => __( 'Opt-in — optional cookies off until the visitor agrees', 'open-consent' ),
				'opt-out' => __( 'Opt-out — optional cookies on until the visitor objects', 'open-consent' ),
			) ),
			array( 'policy_version', __( 'Policy version', 'open-consent' ), 'text', 'general', null, __( 'Change this (e.g. 1 → 2) after a material change to your cookie use. Every visitor is asked again.', 'open-consent' ) ),
			array( 'privacy_url', __( 'Privacy policy URL', 'open-consent' ), 'url', 'general', null, __( 'Empty = the page set in Settings → Privacy.', 'open-consent' ) ),
			array( 'cookie_policy_url', __( 'Cookie policy URL', 'open-consent' ), 'url', 'general' ),
			array( 'cookie_days', __( 'Remember the choice for (days)', 'open-consent' ), 'number', 'general' ),
			array( 'gpc', __( 'Honour Global Privacy Control', 'open-consent' ), 'checkbox', 'general', null, __( 'Opt-out model only: browsers sending GPC are treated as having opted out of marketing.', 'open-consent' ) ),
			array( 'banner_title', __( 'Custom banner title', 'open-consent' ), 'text', 'general', null, __( 'Optional. Leave empty to use the translated default.', 'open-consent' ) ),
			array( 'banner_text', __( 'Custom banner text', 'open-consent' ), 'textarea', 'general' ),
			array( 'layout', __( 'Layout', 'open-consent' ), 'select', 'look', array( 'bar' => __( 'Full-width bar', 'open-consent' ), 'box' => __( 'Box', 'open-consent' ) ) ),
			array( 'position', __( 'Position', 'open-consent' ), 'select', 'look', array(
				'bottom'       => __( 'Bottom', 'open-consent' ),
				'top'          => __( 'Top', 'open-consent' ),
				'bottom-left'  => __( 'Bottom left (box)', 'open-consent' ),
				'bottom-right' => __( 'Bottom right (box)', 'open-consent' ),
				'center'       => __( 'Center (box)', 'open-consent' ),
			) ),
			array( 'color_accent', __( 'Accent colour', 'open-consent' ), 'color', 'look' ),
			array( 'color_background', __( 'Background colour', 'open-consent' ), 'color', 'look' ),
			array( 'color_text', __( 'Text colour', 'open-consent' ), 'color', 'look' ),
			array( 'reopen_button', __( 'Floating "cookie preferences" button', 'open-consent' ), 'checkbox', 'look', null, __( 'Visitors can always reopen the preferences. Alternatively place the [open_consent] shortcode, e.g. in your footer.', 'open-consent' ) ),
			array( 'reopen_position', __( 'Button position', 'open-consent' ), 'select', 'look', array( 'left' => __( 'Left', 'open-consent' ), 'right' => __( 'Right', 'open-consent' ) ) ),
			array( 'google_enabled', __( 'Send Consent Mode v2 signals', 'open-consent' ), 'checkbox', 'google' ),
			array( 'google_mode', __( 'Mode', 'open-consent' ), 'select', 'google', array(
				'basic'    => __( 'Basic — Google tags load only after consent', 'open-consent' ),
				'advanced' => __( 'Advanced — tags load immediately; denied = cookieless pings', 'open-consent' ),
			) ),
			array( 'google_ga4_ids', __( 'GA4 measurement IDs', 'open-consent' ), 'text', 'google', null, __( 'Optional, comma-separated (G-XXXXXXX). OpenConsent then loads gtag.js itself, gated by the Analytics category. Leave empty if Site Kit or another plugin already adds the tag.', 'open-consent' ) ),
			array( 'google_ads_ids', __( 'Google Ads IDs', 'open-consent' ), 'text', 'google', null, __( 'Optional, comma-separated (AW-XXXXXXX), gated by the Marketing category.', 'open-consent' ) ),
			array( 'google_region', __( 'Limit defaults to regions', 'open-consent' ), 'text', 'google', null, __( 'Optional ISO 3166-2 codes (e.g. DE,FR,US-CA). Empty = everywhere.', 'open-consent' ) ),
			array( 'google_wait', __( 'wait_for_update (ms)', 'open-consent' ), 'number', 'google' ),
			array( 'google_ads_data_redaction', __( 'ads_data_redaction', 'open-consent' ), 'checkbox', 'google' ),
			array( 'google_url_passthrough', __( 'url_passthrough', 'open-consent' ), 'checkbox', 'google' ),
			array( 'blocked_handles', __( 'Gate enqueued scripts', 'open-consent' ), 'textarea', 'blocking', null, __( 'One per line: script-handle = category. Example: "jetpack-stats = analytics". The script (and its inline code) is printed inert and only runs after consent.', 'open-consent' ) ),
			array( 'scripts_functional', __( 'Functional scripts', 'open-consent' ), 'code', 'blocking' ),
			array( 'scripts_analytics', __( 'Analytics scripts', 'open-consent' ), 'code', 'blocking' ),
			array( 'scripts_marketing', __( 'Marketing scripts', 'open-consent' ), 'code', 'blocking' ),
			array( 'disabled_pages', __( 'Do not load on page/post IDs', 'open-consent' ), 'text', 'advanced', null, __( 'Comma-separated IDs. Gated scripts stay inert on these pages.', 'open-consent' ) ),
			array( 'reload_on_withdraw', __( 'Reload the page after consent is withdrawn', 'open-consent' ), 'checkbox', 'advanced', null, __( 'Scripts that already ran cannot be stopped; a reload guarantees they are gone.', 'open-consent' ) ),
			array( 'lang', __( 'Banner language', 'open-consent' ), 'text', 'advanced', null, __( '"auto" follows the page language. 125 languages are available; the site language is added automatically.', 'open-consent' ) ),
			array( 'delete_on_uninstall', __( 'Delete settings when the plugin is uninstalled', 'open-consent' ), 'checkbox', 'advanced' ),
		);
		foreach ( $f as $field ) {
			add_settings_field(
				'oc_' . $field[0],
				$field[1],
				array( __CLASS__, 'field' ),
				self::PAGE,
				'oc_' . $field[3],
				array(
					'key'         => $field[0],
					'type'        => $field[2],
					'options'     => $field[4] ?? null,
					'description' => $field[5] ?? '',
					'label_for'   => 'oc_' . $field[0],
				)
			);
		}
	}

	public static function section_general() {
		echo '<p>' . esc_html__( 'OpenConsent stores only the visitor\'s choice, in a first-party cookie. It makes no remote requests and needs no account.', 'open-consent' ) . '</p>';
	}

	public static function section_look() {
	}

	public static function section_google() {
		echo '<p>' . esc_html__( 'Use this only if no other plugin sends Consent Mode signals. If Site Kit\'s own Consent Mode is on, leave this off — Site Kit reads choices through the WP Consent API, which OpenConsent feeds automatically when that plugin is installed.', 'open-consent' ) . '</p>';
	}

	public static function section_blocking() {
		echo '<p>' . esc_html__( 'Snippets pasted below (e.g. a pixel or a tag manager) are printed with type="text/plain" and only executed after the visitor allows the category. Requires the unfiltered_html capability.', 'open-consent' ) . '</p>';
	}

	public static function section_advanced() {
	}

	public static function field( $args ) {
		$s    = Open_Consent_Settings::get();
		$key  = $args['key'];
		$id   = 'oc_' . $key;
		$name = Open_Consent_Settings::OPTION . '[' . $key . ']';
		$val  = $s[ $key ];
		switch ( $args['type'] ) {
			case 'checkbox':
				printf( '<input type="checkbox" id="%s" name="%s" value="1" %s>', esc_attr( $id ), esc_attr( $name ), checked( (bool) $val, true, false ) );
				break;
			case 'select':
				printf( '<select id="%s" name="%s">', esc_attr( $id ), esc_attr( $name ) );
				foreach ( $args['options'] as $v => $label ) {
					printf( '<option value="%s" %s>%s</option>', esc_attr( $v ), selected( $val, $v, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;
			case 'textarea':
				printf( '<textarea id="%s" name="%s" rows="4" class="large-text">%s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( (string) $val ) );
				break;
			case 'code':
				$disabled = current_user_can( 'unfiltered_html' ) ? '' : ' readonly';
				printf( '<textarea id="%s" name="%s" rows="5" class="large-text code" spellcheck="false"%s>%s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_attr( $disabled ), esc_textarea( (string) $val ) );
				break;
			case 'number':
				printf( '<input type="number" id="%s" name="%s" value="%s" class="small-text" min="0">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $val ) );
				break;
			case 'color':
				printf( '<input type="color" id="%s" name="%s" value="%s">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $val ) );
				break;
			case 'url':
				printf( '<input type="url" id="%s" name="%s" value="%s" class="regular-text">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $val ) );
				break;
			default:
				printf( '<input type="text" id="%s" name="%s" value="%s" class="regular-text">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $val ) );
		}
		if ( '' !== $args['description'] ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage these settings.', 'open-consent' ), 403 );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'OpenConsent', 'open-consent' ) . '</h1>';
		echo '<p>' . esc_html__( 'Free, open-source cookie consent. This plugin is a technical tool: it does not by itself make a site legally compliant. Review your cookie use and your local rules.', 'open-consent' ) . '</p>';
		echo '<form action="options.php" method="post">';
		settings_fields( self::PAGE );
		do_settings_sections( self::PAGE );
		submit_button();
		echo '</form></div>';
	}

	public static function notices() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$active = (array) get_option( 'active_plugins', array() );
		$others = array();
		foreach ( self::KNOWN_CMPS as $file => $name ) {
			if ( in_array( $file, $active, true ) ) {
				$others[] = $name;
			}
		}
		if ( $others ) {
			echo '<div class="notice notice-warning"><p>' . esc_html(
				sprintf(
					/* translators: %s: list of plugin names */
					__( 'OpenConsent: another consent plugin is active (%s). Running two consent banners confuses visitors and can send conflicting Google Consent Mode signals. Deactivate one of them.', 'open-consent' ),
					implode( ', ', $others )
				)
			) . '</p></div>';
		}
		$s = Open_Consent_Settings::get();
		if ( $s['google_enabled'] && in_array( 'google-site-kit/google-site-kit.php', $active, true ) ) {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( $screen && 'settings_page_' . self::PAGE === $screen->id ) {
				echo '<div class="notice notice-info"><p>' . esc_html__( 'Site Kit is active. If Site Kit\'s own Consent Mode setting is also on, both send Consent Mode defaults. Turn one of them off (OpenConsent loads first, so it cannot detect a default sent later by another plugin).', 'open-consent' ) . '</p></div>';
			}
		}
	}
}
