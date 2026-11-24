<?php
/**
 * Room pages in the site's other languages (Polylang).
 *
 * Room pages moved from the previous booking plugin exist only in the main language, so the English
 * site linked to Bulgarian pages. Here room pages become translatable, and once the platform has a room's
 * name in another language, a translated page with that name and description is created and linked to
 * the original. Pages the hotel already translated are left alone.
 *
 * @package OspitanoBooking
 */

defined( 'ABSPATH' ) || exit;

/* Polylang: room pages can have translations. */
add_filter(
	'pll_get_post_types',
	function ( $types ) {
		$types[ OSPITANO_ROOM_PT ] = OSPITANO_ROOM_PT;
		return $types;
	}
);

/**
 * Creates the missing translated room pages.
 *
 * @return array{0:int,1:int} pages created, and rooms still waiting for their translated name on the platform.
 */
function ospitano_rooms_translate_pages() {
	if ( ! function_exists( 'pll_languages_list' ) || ! function_exists( 'pll_set_post_language' ) || ! function_exists( 'pll_save_post_translations' ) ) {
		return array( 0, 0 );
	}
	$main    = (string) pll_default_language( 'slug' );
	$created = 0;
	$waiting = 0;
	$posts   = get_posts(
		array(
			'post_type'      => OSPITANO_ROOM_PT,
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'lang'           => '',
		)
	);
	foreach ( $posts as $post ) {
		$lang = pll_get_post_language( $post->ID, 'slug' );
		if ( ! $lang ) { // Pages from before the post type was translatable carry no language yet.
			pll_set_post_language( $post->ID, $main );
			$lang = $main;
		}
		if ( $lang !== $main ) {
			continue;
		}
		$main_type = ospitano_rooms_find_type( $post->ID, $main );
		if ( ! $main_type ) {
			continue;
		}
		$translations = pll_get_post_translations( $post->ID );
		foreach ( pll_languages_list( array( 'fields' => 'slug' ) ) as $l ) {
			if ( $l === $main || ! empty( $translations[ $l ] ) ) {
				continue;
			}
			delete_transient( 'ospitano_overview_' . sanitize_key( $l ) );
			$type = null;
			foreach ( (array) ( ospitano_site_overview( $l )['room_types'] ?? array() ) as $t ) {
				if ( (int) $t['room_type_id'] === (int) $main_type['room_type_id'] ) {
					$type = $t;
				}
			}
			// Not translated on the platform yet: a page with the main-language name would not help.
			if ( ! $type || '' === trim( (string) $type['name'] ) || $type['name'] === $main_type['name'] ) {
				++$waiting;
				continue;
			}
			$id = wp_insert_post(
				array(
					'post_type'    => OSPITANO_ROOM_PT,
					'post_status'  => 'publish',
					'post_title'   => (string) $type['name'],
					'post_name'    => sanitize_title( (string) $type['name'] ),
					'post_content' => $type['description'] ? '<p>' . esc_html( (string) $type['description'] ) . '</p>' : '',
					'menu_order'   => (int) $post->menu_order,
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				continue;
			}
			$thumb = get_post_thumbnail_id( $post->ID );
			if ( $thumb ) {
				set_post_thumbnail( $id, $thumb );
			}
			foreach ( array( 'mphb_gallery' ) as $meta ) { // The previous plugin's photo gallery, when there is one.
				$v = get_post_meta( $post->ID, $meta, true );
				if ( $v ) {
					update_post_meta( $id, $meta, $v );
				}
			}
			pll_set_post_language( $id, $l );
			$translations[ $l ] = $id;
			pll_save_post_translations( $translations );
			++$created;
		}
	}
	return array( $created, $waiting );
}

/* From the admin, once per plugin version, and again while some room still waits for its translated name. */
add_action(
	'admin_init',
	function () {
		if ( ! current_user_can( 'edit_pages' ) || wp_doing_ajax() ) {
			return;
		}
		$done = get_option( 'ospitano_rooms_translated' );
		if ( $done === OSPITANO_BOOKING_VERSION ) {
			return;
		}
		// Checked at most every 10 minutes while some room still waits for its translated name.
		if ( get_transient( 'ospitano_rooms_translate_wait' ) ) {
			return;
		}
		list( $n, $waiting ) = ospitano_rooms_translate_pages();
		if ( $waiting ) {
			set_transient( 'ospitano_rooms_translate_wait', 1, 10 * MINUTE_IN_SECONDS );
		} else {
			update_option( 'ospitano_rooms_translated', OSPITANO_BOOKING_VERSION, false );
		}
		if ( $n > 0 ) {
			flush_rewrite_rules( false );
			if ( function_exists( 'wp_cache_clear_cache' ) ) { // WP Super Cache: the room lists link to the new pages.
				wp_cache_clear_cache();
			}
			add_action(
				'admin_notices',
				function () use ( $n ) {
					echo '<div class="notice notice-success"><p>' . esc_html( sprintf( /* translators: %d: number of pages */ __( 'OSPITANO: %d room pages were created in your other languages.', 'ospitano-booking' ), $n ) ) . '</p></div>';
				}
			);
		}
	}
);
