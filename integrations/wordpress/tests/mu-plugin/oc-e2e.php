<?php
// Test-only: a fake analytics plugin that enqueues its script normally.
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_script( 'fake-analytics', content_url( 'mu-plugins/fake-analytics.js' ), array(), '1', array( 'in_footer' => false ) );
	wp_add_inline_script( 'fake-analytics', 'window.__fakeInline = 1;', 'after' );
} );
