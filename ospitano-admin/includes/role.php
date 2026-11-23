<?php
/**
 * "Хотелиер" role: works with pages, photos, the assistants and Milcho, without being a site administrator
 * (no plugins, themes, users, settings or code).
 *
 * The assistants were written for administrators (manage_options). Instead of handing out that right,
 * the hotelier gets it only on the assistants' own everyday screens: conversations, statistics,
 * situations and fixes. Milcho takes its own right through its filter.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OSPA_ROLE     = 'ospitano_hotel';
const OSPA_ROLE_VER = '1';
const OSPA_CAP      = 'ospitano_manage';

/** Assistant screens the hotelier may open (admin.php?page=...). */
const OSPA_HOTEL_PAGES = array( 'ospitano-manager', 'ospitano-ai-log', 'ospitano-ai-stats' );

/** Assistant form actions the hotelier may send (admin-post.php?action=...). */
const OSPA_HOTEL_POSTS = array( 'osp_ai_fix' );

function ospa_role_caps() {
	return array(
		'read'                   => true,
		'upload_files'           => true,
		'edit_pages'             => true,
		'edit_others_pages'      => true,
		'edit_published_pages'   => true,
		'publish_pages'          => true,
		'delete_pages'           => true,
		'delete_published_pages' => true,
		'edit_posts'             => true,
		'edit_others_posts'      => true,
		'edit_published_posts'   => true,
		'publish_posts'          => true,
		'delete_posts'           => true,
		'delete_published_posts' => true,
		OSPA_CAP                 => true,
	);
}

/** Create or refresh the role, and give administrators the Milcho right too. */
function ospa_role_install() {
	remove_role( OSPA_ROLE );
	add_role( OSPA_ROLE, 'Хотелиер', ospa_role_caps() );
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$admin->add_cap( OSPA_CAP );
	}
	update_option( 'ospa_role_ver', OSPA_ROLE_VER );
}

add_action(
	'init',
	function () {
		if ( OSPA_ROLE_VER !== get_option( 'ospa_role_ver' ) ) {
			ospa_role_install();
		}
	}
);

// Milcho opens for anyone with our right (administrators and hoteliers).
add_filter( 'ospm_capability', function () {
	return OSPA_CAP;
} );

function ospa_is_hotelier( $user = null ) {
	$user = $user ? $user : wp_get_current_user();
	return $user && in_array( OSPA_ROLE, (array) $user->roles, true );
}

/** Is this request one of the assistants' everyday screens (or the menu being built)? */
function ospa_hotel_scope() {
	if ( doing_action( 'admin_menu' ) ) {
		return true;
	}
	// phpcs:disable WordPress.Security.NonceVerification
	$page   = isset( $_REQUEST['page'] ) ? sanitize_key( $_REQUEST['page'] ) : '';
	$action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : '';
	// phpcs:enable
	global $pagenow;
	if ( in_array( $pagenow, array( 'admin.php', 'edit.php', 'options.php' ), true ) && in_array( $page, OSPA_HOTEL_PAGES, true ) ) {
		return true;
	}
	return 'admin-post.php' === $pagenow && in_array( $action, OSPA_HOTEL_POSTS, true );
}

add_filter(
	'user_has_cap',
	function ( $allcaps, $caps, $args, $user ) {
		if ( in_array( 'manage_options', (array) $caps, true ) && empty( $allcaps['manage_options'] )
			&& ! empty( $allcaps[ OSPA_CAP ] ) && ospa_is_hotelier( $user ) && is_admin() && ospa_hotel_scope() ) {
			$allcaps['manage_options'] = true;
		}
		return $allcaps;
	},
	10,
	4
);

register_deactivation_hook(
	OSPA_FILE,
	function () {
		delete_option( 'ospa_role_ver' );
	}
);
