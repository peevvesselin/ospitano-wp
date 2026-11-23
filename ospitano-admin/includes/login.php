<?php
/**
 * Login screen in OSPITANO style.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'login_enqueue_scripts',
	function () {
		wp_enqueue_style( 'ospa-fonts', OSPA::asset( 'fonts.css' ), array(), OSPA_VER );
		wp_enqueue_style( 'ospa-login', OSPA::asset( 'login.css' ), array( 'login' ), OSPA_VER );
		wp_add_inline_style(
			'ospa-login',
			'#login h1 a{background-image:url(' . esc_url( OSPA::asset( 'img/ospitano-logo.svg' ) ) . ')}'
		);
	}
);

add_filter( 'login_headerurl', function () {
	return home_url( '/' );
} );

add_filter( 'login_headertext', array( 'OSPA', 'hotel_name' ) );

add_filter(
	'login_title',
	function () {
		return 'Вход · ' . OSPA::hotel_name();
	}
);

add_action(
	'login_head',
	function () {
		echo '<link rel="icon" href="' . esc_url( OSPA::asset( 'img/favicon-32.png' ) ) . '" sizes="32x32">' . "\n";
	}
);

add_filter(
	'login_message',
	function ( $message ) {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : 'login'; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'login' !== $action || '' !== $message ) {
			return $message;
		}
		return '<p class="ospa-welcome"><span class="ospa-hotel">' . esc_html( OSPA::hotel_name() ) . '</span>Управление на хотела</p>';
	}
);

add_action(
	'login_footer',
	function () {
		echo '<p class="ospa-powered">OSPITANO · hospitality</p>';
	}
);

// The language switcher under the form is not needed for hotel staff.
add_filter( 'login_display_language_dropdown', '__return_false' );
