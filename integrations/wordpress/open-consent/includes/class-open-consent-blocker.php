<?php
/**
 * Turns <script> markup into inert, consent-gated markup the browser engine
 * understands (type="text/plain" data-open-consent="..."). Uses the HTML API
 * (WP_HTML_Tag_Processor) rather than regular expressions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Open_Consent_Blocker {

	const JS_TYPES = array( '', 'text/javascript', 'application/javascript', 'module' );

	/**
	 * @param string $html       Markup containing one or more <script> (and optionally <iframe>) tags.
	 * @param string $categories Space-separated category names.
	 * @return string Markup in which every JavaScript <script> is inert until consent.
	 */
	public static function block( $html, $categories ) {
		if ( ! preg_match( '/^[a-z][a-z0-9_-]*( [a-z][a-z0-9_-]*)*$/D', (string) $categories ) ) {
			return ''; // Never emit an un-gated script because of a bad category.
		}
		$p = new WP_HTML_Tag_Processor( (string) $html );
		while ( $p->next_tag() ) {
			$tag = $p->get_tag();
			if ( 'SCRIPT' === $tag ) {
				$type = strtolower( trim( (string) $p->get_attribute( 'type' ) ) );
				if ( ! in_array( $type, self::JS_TYPES, true ) ) {
					continue; // JSON data blocks, templates, etc. are not executable.
				}
				if ( 'module' === $type ) {
					$p->set_attribute( 'data-type', 'module' );
				}
				$p->set_attribute( 'type', 'text/plain' );
				$p->set_attribute( 'data-open-consent', $categories );
				$src = $p->get_attribute( 'src' );
				if ( is_string( $src ) && '' !== $src ) {
					$p->set_attribute( 'data-src', $src );
					$p->remove_attribute( 'src' );
				}
				foreach ( $p->get_attribute_names_with_prefix( 'on' ) ?? array() as $attr ) {
					$p->remove_attribute( $attr );
				}
			} elseif ( 'IFRAME' === $tag ) {
				$src = $p->get_attribute( 'src' );
				if ( is_string( $src ) && '' !== $src ) {
					$p->set_attribute( 'data-src', $src );
					$p->remove_attribute( 'src' );
					$p->set_attribute( 'data-open-consent', $categories );
				}
			}
		}
		return $p->get_updated_html();
	}
}
