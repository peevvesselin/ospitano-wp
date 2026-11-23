<?php
/**
 * OSPITANO look for the whole admin: colours, fonts, logo, titles, footer, top bar, editor.
 * Applies to every user, including full administrators.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_enqueue_scripts',
	function () {
		wp_enqueue_style( 'ospa-fonts', OSPA::asset( 'fonts.css' ), array(), OSPA_VER );
		wp_enqueue_style( 'ospa-admin', OSPA::asset( 'admin.css' ), array(), OSPA_VER );
		wp_add_inline_style(
			'ospa-admin',
			':root{--ospa-logo:url(' . esc_url( OSPA::asset( 'img/ospitano-logo-on-dark.svg' ) ) . ');--ospa-mark:url(' . esc_url( OSPA::asset( 'img/ospitano-mark-on-dark.svg' ) ) . ');--ospa-mark-light:url(' . esc_url( OSPA::asset( 'img/ospitano-mark.svg' ) ) . ')}'
		);
	},
	99
);

// Top bar on the public site (logged-in users) gets the same colours.
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( is_admin_bar_showing() ) {
			wp_enqueue_style( 'ospa-fonts', OSPA::asset( 'fonts.css' ), array(), OSPA_VER );
			wp_enqueue_style( 'ospa-bar', OSPA::asset( 'admin-bar.css' ), array( 'admin-bar' ), OSPA_VER );
			wp_add_inline_style( 'ospa-bar', ':root{--ospa-mark:url(' . esc_url( OSPA::asset( 'img/ospitano-mark-on-dark.svg' ) ) . ')}' );
		}
	}
);

// Block editor: our fonts, and the OSPITANO mark instead of the W in the top-left corner.
add_action(
	'enqueue_block_editor_assets',
	function () {
		wp_enqueue_style( 'ospa-editor', OSPA::asset( 'editor.css' ), array(), OSPA_VER );
		wp_add_inline_style( 'ospa-editor', ':root{--ospa-mark:url(' . esc_url( OSPA::asset( 'img/ospitano-mark-on-dark.svg' ) ) . ')}' );
		// Skip the "Welcome to the editor" tour.
		wp_add_inline_script(
			'wp-edit-post',
			'window.addEventListener("load",function(){try{var p=wp.data.select("core/preferences");if(p&&p.get("core/edit-post","welcomeGuide")){wp.data.dispatch("core/preferences").set("core/edit-post","welcomeGuide",false);}}catch(e){}});'
		);
	}
);

// Browser tab: "Резервации ‹ Хотел" instead of "... — WordPress".
add_filter(
	'admin_title',
	function ( $admin_title, $title ) {
		global $pagenow;
		$title = trim( wp_strip_all_tags( (string) $title ) );
		if ( 'index.php' === $pagenow && empty( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$title = 'Начало';
		}
		return ( '' !== $title ? $title . ' ‹ ' : '' ) . OSPA::hotel_name();
	},
	99,
	2
);

// Our icon in the browser tab for the admin and login pages.
remove_action( 'admin_head', 'wp_site_icon' );
remove_action( 'login_head', 'wp_site_icon', 99 );
add_action(
	'admin_head',
	function () {
		echo '<link rel="icon" href="' . esc_url( OSPA::asset( 'img/favicon-32.png' ) ) . '" sizes="32x32">' . "\n";
	}
);

// Footer.
add_filter(
	'admin_footer_text',
	function () {
		return '<span class="ospa-foot">OSPITANO · ' . esc_html( OSPA::hotel_name() ) . '</span>';
	},
	99
);
add_filter( 'update_footer', '__return_empty_string', 99 );

// One colour scheme for everyone; our stylesheet sits on top of the default one.
add_filter( 'get_user_option_admin_color', function () {
	return 'fresh';
}, 99 );
remove_action( 'admin_color_scheme_picker', 'admin_color_scheme_picker' );

// "Табло" becomes "Начало".
add_action(
	'admin_menu',
	function () {
		global $menu, $submenu;
		if ( isset( $menu[2] ) && 'index.php' === $menu[2][2] ) {
			$menu[2][0] = 'Начало';
			$menu[2][6] = 'dashicons-admin-home';
		}
		if ( isset( $submenu['index.php'][0] ) ) {
			$submenu['index.php'][0][0] = 'Начало';
		}
	},
	999
);

// Help tabs talk about WordPress and link to wordpress.org.
add_action(
	'admin_head',
	function () {
		$screen = get_current_screen();
		if ( $screen ) {
			$screen->remove_help_tabs();
			$screen->set_help_sidebar( '' );
		}
	},
	999
);

// Top bar: OSPITANO mark instead of the W menu, no update or comment counters.
add_action(
	'admin_bar_menu',
	function ( $bar ) {
		$bar->add_node(
			array(
				'id'    => 'ospa-brand',
				'title' => '<img class="ospa-bar-mark" src="' . esc_url( OSPA::asset( 'img/ospitano-mark-on-dark.svg' ) ) . '" alt="OSPITANO" width="20" height="20">',
				'href'  => admin_url(),
				'meta'  => array( 'title' => 'OSPITANO' ),
			)
		);
	},
	1
);
add_action(
	'admin_bar_menu',
	function ( $bar ) {
		foreach ( array( 'wp-logo', 'updates', 'comments' ) as $node ) {
			$bar->remove_node( $node );
		}
		if ( OSPA::hotel() ) {
			$bar->remove_node( 'new-content' );
			$bar->remove_node( 'command-palette' );
			$bar->remove_node( 'customize' );
			$bar->remove_node( 'search' );
		}
	},
	999
);
