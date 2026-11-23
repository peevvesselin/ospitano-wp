<?php
/**
 * Short hotel panel: only the menu items the hotel needs, no update notices,
 * no system screens. Full users keep everything and can preview the hotel view.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The hotel menu: five sections, built from scratch.
//   Начало · Резервации · Асистенти · Сайт · Профил и помощ
add_action(
	'admin_menu',
	function () {
		if ( ! OSPA::hotel() ) {
			return;
		}
		global $menu, $submenu, $ospa_assistants;
		$old_menu = (array) $menu;
		$old_sub  = (array) $submenu;

		$ospa_assistants = ospa_find_assistants( $old_menu, $old_sub );

		$menu    = array();
		$submenu = array();

		$menu[2] = array( 'Начало', 'read', 'index.php', '', 'menu-top menu-top-first menu-icon-dashboard', 'menu-dashboard', 'dashicons-admin-home' );

		add_menu_page( 'Резервации', 'Резервации', 'read', 'ospa-bookings', 'ospa_page_bookings', 'dashicons-calendar-alt', 3 );

		add_menu_page( 'Асистенти', 'Асистенти', 'read', 'ospa-assistants', 'ospa_page_assistants', 'dashicons-format-chat', 4 );
		add_submenu_page( 'ospa-assistants', 'Асистенти', 'Всички асистенти', 'read', 'ospa-assistants', 'ospa_page_assistants' );
		foreach ( $ospa_assistants as $a ) {
			$submenu['ospa-assistants'][] = array( $a['title'], 'read', $a['slug'], $a['title'] );
		}

		add_menu_page( 'Сайт', 'Сайт', 'edit_pages', 'edit.php?post_type=page', '', 'dashicons-admin-site-alt3', 5 );
		$submenu['edit.php?post_type=page'] = array(
			array( 'Страници', 'edit_pages', 'edit.php?post_type=page' ),
			array( 'Нова страница', 'edit_pages', 'post-new.php?post_type=page' ),
			array( 'Снимки и файлове', 'upload_files', 'upload.php' ),
		);

		add_menu_page( 'Профил и помощ', 'Профил и помощ', 'read', 'profile.php', '', 'dashicons-admin-users', 6 );
		$submenu['profile.php'] = array( array( 'Профил', 'read', 'profile.php' ) );
		add_submenu_page( 'profile.php', 'Помощ', 'Помощ', 'read', 'ospa-help', 'ospa_page_help' );
	},
	9999
);

/**
 * The hotel's assistants as they are registered on this site (guest assistant, Milcho),
 * without their technical pages (keys, models, costs, logs).
 *
 * @return array<int,array{slug:string,page:string,title:string}>
 */
function ospa_find_assistants( $old_menu, $old_sub ) {
	$skip = '/(настрой|setting|ключ|api|модел|model|разход|cost|token|тест|test|debug|диагност|license|лиценз)/iu';
	$out  = array();
	$add  = function ( $page, $title ) use ( &$out, $skip ) {
		$title = trim( wp_strip_all_tags( preg_replace( '#<span.*$#s', '', $title ) ) );
		if ( '' === $page || '' === $title || preg_match( $skip, $title ) || isset( $out[ $page ] ) ) {
			return;
		}
		$out[ $page ] = array(
			'slug'  => false === strpos( $page, '.php' ) ? 'admin.php?page=' . $page : $page,
			'page'  => $page,
			'title' => $title,
		);
	};
	// Everything under the "OSPITANO AI" family of menus (guest assistant and friends).
	foreach ( array( 'ospitano-ai', 'ospitano-assistant', 'ospitano-chat', 'ospitano-mila', 'mila' ) as $parent ) {
		foreach ( (array) ( $old_sub[ $parent ] ?? array() ) as $it ) {
			$add( $it[2] ?? '', $it[0] ?? '' );
		}
	}
	// Milcho, wherever it hangs.
	foreach ( $old_menu as $it ) {
		if ( 'ospitano-manager' === ( $it[2] ?? '' ) ) {
			$add( 'ospitano-manager', 'Милчо (управител)' );
		}
	}
	foreach ( $old_sub as $items ) {
		foreach ( (array) $items as $it ) {
			if ( 'ospitano-manager' === ( $it[2] ?? '' ) ) {
				$add( 'ospitano-manager', 'Милчо (управител)' );
			}
		}
	}
	return array_values( $out );
}

// Keep the right section highlighted on the assistants' own pages.
add_filter(
	'parent_file',
	function ( $parent ) {
		global $plugin_page, $submenu_file, $ospa_assistants, $typenow, $pagenow;
		if ( ! OSPA::hotel() ) {
			return $parent;
		}
		if ( 'ospa-help' === $plugin_page ) {
			$submenu_file = 'ospa-help'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			return 'profile.php';
		}
		$here = $plugin_page ? $plugin_page : ( $typenow && in_array( $pagenow, array( 'edit.php', 'post.php', 'post-new.php' ), true ) ? 'edit.php?post_type=' . $typenow : '' );
		if ( '' === $here ) {
			return $parent;
		}
		foreach ( (array) $ospa_assistants as $a ) {
			if ( $a['page'] === $here ) {
				$submenu_file = $a['slug']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
				return 'ospa-assistants';
			}
		}
		return $parent;
	}
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

// Plugins' own notices and promos (upgrade offers, surveys, compatibility hints) are not for the hotel.
add_action(
	'in_admin_header',
	function () {
		if ( ! OSPA::hotel() ) {
			return;
		}
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'network_admin_notices' );
		remove_all_actions( 'user_admin_notices' );
	},
	9999
);

// Top bar for the hotel: our mark, the hotel's name, the account menu and nothing else.
add_action(
	'admin_bar_menu',
	function ( $bar ) {
		if ( ! OSPA::hotel() ) {
			return;
		}
		$keep = array( 'ospa-brand', 'site-name', 'view-site', 'top-secondary', 'my-account', 'user-actions', 'user-info', 'edit-profile', 'logout', 'ospa-preview', 'menu-toggle' );
		foreach ( $bar->get_nodes() as $node ) {
			if ( ! in_array( $node->id, $keep, true ) ) {
				$bar->remove_node( $node->id );
			}
		}
	},
	PHP_INT_MAX
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

// A screen the hotel may not open leads back to the start page instead of an error.
add_action(
	'admin_page_access_denied',
	function () {
		if ( OSPA::hotel() ) {
			wp_safe_redirect( admin_url() );
			exit;
		}
	}
);
