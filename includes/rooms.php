<?php
/**
 * Room pages.
 *
 * A site that moved here from another booking plugin keeps its room pages: same address, text and photos.
 * When that plugin is switched off, we keep its room post type and its shortcodes alive, so nothing on the
 * site breaks, and every room page gets the OSPITANO box: price "from", a live availability calendar and booking.
 *
 * The box also works on any page with [ospitano_room type="ID"].
 *
 * @package OspitanoBooking
 */

defined( 'ABSPATH' ) || exit;

/** Post type and taxonomy of the previous booking plugin that hold the room pages. */
const OSPITANO_ROOM_PT  = 'mphb_room_type';
const OSPITANO_ROOM_TAX = 'mphb_room_type_facility';

/** True while the previous booking plugin is still running (it then owns the room pages). */
function ospitano_rooms_old_plugin_active() {
	return class_exists( 'HotelBookingPlugin' ) || function_exists( 'MPHB' );
}

/* Keep the room pages online once the previous plugin is switched off. */
add_action(
	'init',
	function () {
		if ( ospitano_rooms_old_plugin_active() || post_type_exists( OSPITANO_ROOM_PT ) ) {
			return;
		}
		register_post_type(
			OSPITANO_ROOM_PT,
			array(
				'labels'        => array(
					'name'          => __( 'Room pages', 'ospitano-booking' ),
					'singular_name' => __( 'Room page', 'ospitano-booking' ),
				),
				'public'        => true,
				'show_in_rest'  => true,
				'menu_icon'     => 'dashicons-building',
				'menu_position' => 26,
				'has_archive'   => false,
				'rewrite'       => array( 'slug' => 'accommodation', 'with_front' => false ),
				'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ),
			)
		);
		register_taxonomy(
			OSPITANO_ROOM_TAX,
			OSPITANO_ROOM_PT,
			array(
				'labels'       => array( 'name' => __( 'Room amenities', 'ospitano-booking' ) ),
				'public'       => false,
				'show_ui'      => true,
				'show_in_rest' => true,
				'hierarchical' => false,
				'rewrite'      => false,
			)
		);
		// The address was in use before: make sure WordPress knows it after the switch.
		if ( get_option( 'ospitano_rooms_flushed' ) !== OSPITANO_BOOKING_VERSION ) {
			update_option( 'ospitano_rooms_flushed', OSPITANO_BOOKING_VERSION, false );
			add_action( 'wp_loaded', 'flush_rewrite_rules' );
		}
	},
	20
);

/* The previous plugin's booking shortcodes keep showing our parts after it is switched off. */
add_action(
	'init',
	function () {
		if ( ospitano_rooms_old_plugin_active() ) {
			return;
		}
		foreach ( ospitano_site_replaced_tags() + array( 'mphb_rooms' => 'rooms' ) as $tag => $kind ) {
			if ( ! shortcode_exists( $tag ) ) {
				add_shortcode( $tag, fn() => ospitano_site_part( $kind ) );
			}
		}
		// Its other shortcodes (room lists, single parts) are drawn by us on the room page or have no use any more.
		foreach ( array( 'mphb_room', 'mphb_availability_calendar', 'mphb_booking_form', 'mphb_services', 'mphb_service', 'mphb_rates', 'mphb_booking_confirmation', 'mphb_account', 'mphb_booking_cancellation', 'mphb_search_results_page', 'mphb_payment_request_checkout' ) as $tag ) {
			if ( ! shortcode_exists( $tag ) ) {
				add_shortcode( $tag, '__return_empty_string' );
			}
		}
	},
	30
);

/** The platform's room type for a room page, in the page's language. */
function ospitano_rooms_core_type( $post_id, $lang ) {
	$t = ospitano_rooms_find_type( $post_id, $lang );
	if ( ! $t ) {
		// Names and addresses may be kept only in the hotel's main language.
		$main = ospitano_rooms_find_type( $post_id, '' );
		foreach ( $main ? (array) ( ospitano_site_overview( $lang )['room_types'] ?? array() ) : array() as $x ) {
			if ( (int) $x['room_type_id'] === (int) $main['room_type_id'] ) {
				return $x;
			}
		}
	}
	return $t;
}

/** Matched on the page address, then on the name. */
function ospitano_rooms_find_type( $post_id, $lang ) {
	$o     = ospitano_site_overview( $lang );
	$types = (array) ( $o['room_types'] ?? array() );
	$link  = untrailingslashit( (string) get_permalink( $post_id ) );
	$path  = untrailingslashit( (string) wp_parse_url( $link, PHP_URL_PATH ) );
	foreach ( $types as $t ) {
		$u = untrailingslashit( (string) ( $t['url'] ?? '' ) );
		if ( '' !== $u && ( $u === $link || untrailingslashit( (string) wp_parse_url( $u, PHP_URL_PATH ) ) === $path || rawurldecode( $u ) === rawurldecode( $link ) ) ) {
			return $t;
		}
	}
	$title = mb_strtolower( trim( wp_strip_all_tags( get_the_title( $post_id ) ) ) );
	foreach ( $types as $t ) {
		if ( mb_strtolower( trim( (string) ( $t['name'] ?? '' ) ) ) === $title ) {
			return $t;
		}
	}
	return null;
}

/** The box: details list, then price, calendar and booking drawn by the script. */
function ospitano_rooms_box( $type, $currency, $with_details = true ) {
	$h = '';
	if ( $with_details && ! empty( $type['details'] ) ) {
		$h .= '<ul class="ospitano-room-details">';
		foreach ( (array) $type['details'] as $d ) {
			if ( '' === trim( (string) ( $d['value'] ?? '' ) ) ) {
				continue;
			}
			$h .= '<li><span class="ospitano-room-details__l">' . esc_html( rtrim( (string) $d['label'], ': ' ) ) . ':</span> ' . esc_html( (string) $d['value'] ) . '</li>';
		}
		if ( ! empty( $type['max_occupancy'] ) ) {
			$h .= '<li><span class="ospitano-room-details__l">' . esc_html__( 'Guests', 'ospitano-booking' ) . ':</span> ' . esc_html( sprintf( /* translators: %d: number of guests */ __( 'up to %d', 'ospitano-booking' ), (int) $type['max_occupancy'] ) ) . '</li>';
		}
		$h .= '</ul>';
	}
	$h .= ospitano_site_part( 'room' );
	// The part is generic; add what the room box needs.
	return str_replace(
		'data-ospitano="room"',
		sprintf(
			'data-ospitano="room" data-type="%d" data-currency="%s" data-max-adults="%d" data-max-children="%d"',
			(int) $type['room_type_id'],
			esc_attr( (string) $currency ),
			(int) ( $type['max_adults'] ?? 0 ),
			(int) ( $type['max_children'] ?? 0 )
		),
		$h
	);
}

/** Language of the page for the platform texts. */
function ospitano_rooms_lang() {
	$l = function_exists( 'pll_current_language' ) ? (string) pll_current_language( 'slug' ) : '';
	return '' !== $l ? $l : ospitano_booking_language( ospitano_booking_settings()['lang'] );
}

/* Room pages: add the box under the room's text once the previous plugin is off. */
add_filter(
	'the_content',
	function ( $content ) {
		if ( ospitano_rooms_old_plugin_active() || ! is_singular( OSPITANO_ROOM_PT ) || get_the_ID() !== get_queried_object_id() || doing_filter( 'get_the_excerpt' ) || false !== strpos( $content, 'data-ospitano="room"' ) ) {
			return $content;
		}
		$lang = ospitano_rooms_lang();
		$type = ospitano_rooms_core_type( get_the_ID(), $lang );
		if ( ! $type ) {
			return $content;
		}
		$o = ospitano_site_overview( $lang );
		return $content . ospitano_rooms_box( $type, $o['currency'] ?? 'EUR' );
	},
	20
);

/* [ospitano_rooms]: all rooms with price "from" and a link to each room page. */
add_shortcode( 'ospitano_rooms', fn() => ospitano_site_part( 'rooms' ) );

/* [ospitano_room type="12" details="0"] anywhere. */
add_shortcode(
	'ospitano_room',
	function ( $atts ) {
		$a    = shortcode_atts( array( 'type' => 0, 'details' => 1 ), $atts, 'ospitano_room' );
		$lang = ospitano_rooms_lang();
		$o    = ospitano_site_overview( $lang );
		foreach ( (array) ( $o['room_types'] ?? array() ) as $t ) {
			if ( (int) $t['room_type_id'] === (int) $a['type'] ) {
				return ospitano_rooms_box( $t, $o['currency'] ?? 'EUR', (bool) $a['details'] );
			}
		}
		return '';
	}
);

add_action(
	'wp_head',
	function () {
		if ( ! is_singular( OSPITANO_ROOM_PT ) ) {
			return;
		}
		echo '<style id="ospitano-room-css">.ospitano-room-details{list-style:none;margin:24px 0;padding:0}.ospitano-room-details li{margin:0 0 6px}.ospitano-room-details__l{font-weight:600}</style>' . "\n";
	}
);
