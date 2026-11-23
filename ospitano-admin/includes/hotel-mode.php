<?php
/**
 * Short hotel panel: only the menu items the hotel needs, no update notices,
 * no system screens. Full users keep everything and can preview the hotel view.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Hide every top-level menu item that is not on the hotel list.
add_action(
	'admin_menu',
	function () {
		if ( ! OSPA::hotel() ) {
			return;
		}
		global $menu, $submenu;
		$keep = OSPA::options()['menu'];
		unset( $submenu['index.php'] ); // Only "Начало" and "Updates" live there.
		foreach ( (array) $menu as $pos => $item ) {
			$slug = $item[2] ?? '';
			if ( '' === $slug || 0 === strpos( $item[4] ?? '', 'wp-menu-separator' ) ) {
				unset( $menu[ $pos ] );
				continue;
			}
			if ( ! in_array( $slug, $keep, true ) ) {
				unset( $menu[ $pos ] );
			}
		}
		// The hotel's own profile lives under Users for admins; give it its own item.
		if ( in_array( 'profile.php', $keep, true ) && current_user_can( 'list_users' ) ) {
			add_menu_page( 'Профил', 'Профил', 'read', 'profile.php', '', 'dashicons-admin-users', 98 );
		}
	},
	9999
);

// Typing the address of a system screen leads back to the start page.
add_action(
	'admin_init',
	function () {
		if ( ! OSPA::hotel() || wp_doing_ajax() ) {
			return;
		}
		global $pagenow;
		$blocked = OSPA::BLOCKED_SCREENS;
		if ( in_array( $pagenow, $blocked, true ) ) {
			// Settings pages of our own plugins stay reachable (options-general.php?page=ospitano-...).
			$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			if ( 'options-general.php' === $pagenow && 0 === strpos( $page, 'ospitano' ) ) {
				return;
			}
			wp_safe_redirect( admin_url() );
			exit;
		}
	}
);

// No update, maintenance or core nags for the hotel.
add_action(
	'admin_head',
	function () {
		if ( ! OSPA::hotel() ) {
			return;
		}
		remove_action( 'admin_notices', 'update_nag', 3 );
		remove_action( 'network_admin_notices', 'update_nag', 3 );
		remove_action( 'admin_notices', 'maintenance_nag', 10 );
		remove_action( 'admin_notices', 'site_admin_notice' );
		echo '<style>.update-nag,.plugin-update-tr,.update-plugins,.update-count,#wp-admin-bar-updates,.notice.is-dismissible.wpml-notice{display:none!important}</style>';
	},
	1
);

// Hotel users get the short top bar menu and no "Visit site / W" clutter.
add_filter(
	'admin_body_class',
	function ( $classes ) {
		$classes .= ' ospa';
		if ( OSPA::hotel() ) {
			$classes .= ' ospa-hotel';
		}
		if ( OSPA::is_preview() ) {
			$classes .= ' ospa-preview';
		}
		return $classes;
	}
);

// Preview switch in the top bar, for full users only.
add_action(
	'admin_bar_menu',
	function ( $bar ) {
		if ( ! OSPA::is_full() || ! is_admin() ) {
			return;
		}
		$on = OSPA::is_preview();
		$bar->add_node(
			array(
				'id'     => 'ospa-preview',
				'parent' => 'top-secondary',
				'title'  => $on ? 'Изход от изгледа на хотела' : 'Изглед на хотела',
				'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=ospa_preview&on=' . ( $on ? '0' : '1' ) ), 'ospa_preview' ),
				'meta'   => array( 'class' => $on ? 'ospa-preview-on' : '' ),
			)
		);
	},
	100
);

add_action(
	'admin_post_ospa_preview',
	function () {
		check_admin_referer( 'ospa_preview' );
		if ( ! OSPA::is_full() ) {
			wp_die( '', '', 403 );
		}
		$uid = get_current_user_id();
		if ( ! empty( $_GET['on'] ) ) {
			update_user_meta( $uid, 'ospa_preview', 1 );
		} else {
			delete_user_meta( $uid, 'ospa_preview' );
		}
		wp_safe_redirect( admin_url() );
		exit;
	}
);
