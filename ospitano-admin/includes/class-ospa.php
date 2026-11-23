<?php
/**
 * Shared state: who sees the full panel and which menu items the hotel sees.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OSPA {

	const OPT = 'ospitano_admin';

	/** Top-level menu slugs a hotel user sees by default (others are hidden). */
	const DEFAULT_MENU = array(
		'index.php',
		'ospitano-ai',
		'ospitano-manager',
		'osai',
		'edit.php?post_type=page',
		'upload.php',
		'profile.php',
	);

	/** Screens a hotel user cannot open even by typing the address. */
	const BLOCKED_SCREENS = array(
		'about.php',
		'credits.php',
		'freedoms.php',
		'privacy.php',
		'contribute.php',
		'update-core.php',
		'update.php',
		'plugins.php',
		'plugin-install.php',
		'plugin-editor.php',
		'theme-editor.php',
		'theme-install.php',
		'themes.php',
		'tools.php',
		'import.php',
		'export.php',
		'site-health.php',
		'export-personal-data.php',
		'erase-personal-data.php',
		'options-general.php',
		'options-writing.php',
		'options-reading.php',
		'options-discussion.php',
		'options-media.php',
		'options-permalink.php',
		'options-privacy.php',
		'users.php',
		'user-new.php',
	);

	public static function activate() {
		$o = self::options();
		$uid = get_current_user_id();
		if ( $uid && ! in_array( $uid, $o['full_users'], true ) ) {
			$o['full_users'][] = $uid;
		}
		update_option( self::OPT, $o );
	}

	public static function options() {
		$o = get_option( self::OPT, array() );
		$o = is_array( $o ) ? $o : array();
		return array(
			'full_users' => array_values( array_map( 'intval', (array) ( $o['full_users'] ?? array() ) ) ),
			'menu'       => isset( $o['menu'] ) ? array_values( (array) $o['menu'] ) : self::DEFAULT_MENU,
			'hotel_mode' => isset( $o['hotel_mode'] ) ? (bool) $o['hotel_mode'] : true,
		);
	}

	/** Full panel: listed users, network admins, and everyone if no one is listed yet. */
	public static function is_full( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}
		if ( is_multisite() && is_super_admin( $user_id ) ) {
			return true;
		}
		$o = self::options();
		if ( empty( $o['full_users'] ) || ! $o['hotel_mode'] ) {
			return true;
		}
		return in_array( (int) $user_id, $o['full_users'], true );
	}

	/** Previewing the hotel view: a full user who switched it on from the top bar. */
	public static function is_preview() {
		return self::is_full() && get_user_meta( get_current_user_id(), 'ospa_preview', true );
	}

	/** Should the current request show the short hotel panel? */
	public static function hotel() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		return ! self::is_full() || self::is_preview();
	}

	public static function asset( $path ) {
		return OSPA_URL . 'assets/' . $path;
	}

	public static function hotel_name() {
		$name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		return '' !== trim( $name ) ? $name : 'OSPITANO';
	}
}
