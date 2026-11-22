<?php
/**
 * Moving to OSPITANO: reads the hotel data that an existing booking plugin stored in this WordPress
 * (room types, rooms, rates, seasons, services, bookings) and writes it as a neutral
 * "ospitano-import/1" JSON file. The file is then imported on the OSPITANO platform.
 *
 * Only reads the database. Nothing on this site is changed.
 *
 * Supported source: MotoPress Hotel Booking data (post types prefixed "mphb_").
 *
 * @package OspitanoBooking
 */

defined( 'ABSPATH' ) || exit;

/** True when this site holds data we can export. */
function ospitano_migrate_available() {
	global $wpdb;
	return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->posts} WHERE post_type = %s LIMIT 1", 'mphb_room_type' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/** @return WP_Post[] */
function ospitano_migrate_posts( $type, $status = array( 'publish' ), $extra = array() ) {
	return get_posts(
		array_merge(
			array(
				'post_type'        => $type,
				'post_status'      => $status,
				'numberposts'      => -1,
				'orderby'          => 'menu_order ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
			),
			$extra
		)
	);
}

function ospitano_migrate_meta( $id, $key, $default = null ) {
	$v = get_post_meta( $id, $key, true );
	return ( '' === $v || null === $v ) ? $default : $v;
}

function ospitano_migrate_cents( $amount ) {
	return (int) round( (float) $amount * 100 );
}

/** The site language as one of the platform's guest languages. */
function ospitano_migrate_lang() {
	$l = substr( get_locale(), 0, 2 );
	return in_array( $l, array( 'bg', 'en', 'ro', 'de', 'ru' ), true ) ? $l : 'en';
}

function ospitano_migrate_text( $text ) {
	return array( ospitano_migrate_lang() => wp_strip_all_tags( (string) $text ) );
}

/** Main photo first, then the gallery, as full-size URLs. */
function ospitano_migrate_images( $id ) {
	$ids   = array();
	$thumb = (int) get_post_thumbnail_id( $id );
	if ( $thumb ) {
		$ids[] = $thumb;
	}
	$gallery = ospitano_migrate_meta( $id, 'mphb_gallery', '' );
	foreach ( is_array( $gallery ) ? $gallery : explode( ',', (string) $gallery ) as $g ) {
		$ids[] = (int) $g;
	}
	$urls = array();
	foreach ( array_unique( array_filter( $ids ) ) as $att ) {
		$u = wp_get_attachment_image_url( $att, 'full' );
		if ( $u ) {
			$urls[] = $u;
		}
	}
	return array_slice( $urls, 0, 30 );
}

/** Room facts shown to guests: amenities, view, size, bed. Labels in the site language. */
function ospitano_migrate_details( $id ) {
	$bg     = 'bg' === ospitano_migrate_lang();
	$label  = function ( $bg_text, $en_text ) use ( $bg ) {
		return ospitano_migrate_text( $bg ? $bg_text : $en_text );
	};
	$out    = array();
	$terms  = get_the_terms( $id, 'mphb_room_type_facility' );
	if ( is_array( $terms ) && $terms ) {
		$out[] = array( 'label' => $label( 'Удобства', 'Amenities' ), 'value' => ospitano_migrate_text( implode( ', ', wp_list_pluck( $terms, 'name' ) ) ) );
	}
	$view = (string) ospitano_migrate_meta( $id, 'mphb_view', '' );
	if ( '' !== $view ) {
		$out[] = array( 'label' => $label( 'Изглед', 'View' ), 'value' => ospitano_migrate_text( $view ) );
	}
	$size = (int) ospitano_migrate_meta( $id, 'mphb_size', 0 );
	if ( $size ) {
		$out[] = array( 'label' => $label( 'Размер', 'Size' ), 'value' => ospitano_migrate_text( $size . ' м²' ) );
	}
	$bed = (string) ospitano_migrate_meta( $id, 'mphb_bed', '' );
	if ( '' !== $bed ) {
		$out[] = array( 'label' => $label( 'Тип легло', 'Bed type' ), 'value' => ospitano_migrate_text( $bed ) );
	}
	return $out;
}

/** The site-wide deposit rule, applied to every rate plan. */
function ospitano_migrate_deposit() {
	$amount_type = (string) get_option( 'mphb_payment_amount_type', 'full' );
	if ( 'deposit' !== $amount_type ) {
		return array( 'deposit_type' => 'full' === $amount_type ? 'full' : 'none' );
	}
	$type   = (string) get_option( 'mphb_payment_deposit_type', 'percent' );
	$amount = (float) get_option( 'mphb_payment_deposit_amount', 0 );
	$days   = get_option( 'mphb_payment_deposit_time_frame', '' );
	$out    = 'percent' === $type
		? array( 'deposit_type' => 'percent', 'deposit_value' => (int) round( $amount ) )
		: array( 'deposit_type' => 'first_night' );
	if ( '' !== (string) $days && is_numeric( $days ) ) {
		$out['deposit_full_within_days'] = (int) $days;
	}
	return $out;
}

function ospitano_migrate_option_bool( $name ) {
	$v = get_option( $name, null );
	return null === $v ? null : in_array( (string) $v, array( '1', 'yes', 'on', 'true' ), true );
}

function ospitano_migrate_time( $v ) {
	return preg_match( '/^(\d{1,2}):(\d{2})/', (string) $v, $m ) ? sprintf( '%02d:%s', $m[1], $m[2] ) : null;
}

/** Discount codes. Percentage codes move as they are; other kinds are listed for a person to check. */
function ospitano_migrate_coupons( &$warnings ) {
	$out = array();
	foreach ( ospitano_migrate_posts( 'mphb_coupon', array( 'publish' ) ) as $p ) {
		$type   = (string) ospitano_migrate_meta( $p->ID, '_mphb_type', 'percentage' );
		$amount = (float) ospitano_migrate_meta( $p->ID, '_mphb_amount', 0 );
		if ( $amount <= 0 || '' === trim( $p->post_title ) ) {
			continue;
		}
		$row = array(
			'code'       => strtoupper( trim( $p->post_title ) ),
			'type'       => 'percentage' === $type ? 'percent' : 'fixed',
			'value'      => 'percentage' === $type ? (int) round( $amount ) : ospitano_migrate_cents( $amount ),
			'label'      => $p->post_excerpt ? ospitano_migrate_text( $p->post_excerpt ) : null,
			'valid_from' => ospitano_migrate_meta( $p->ID, '_mphb_check_in_date_after' ),
			'valid_to'   => ospitano_migrate_meta( $p->ID, '_mphb_check_out_date_before' ),
			'min_nights' => (int) ospitano_migrate_meta( $p->ID, '_mphb_min_nights', 1 ) ?: 1,
			'max_nights' => (int) ospitano_migrate_meta( $p->ID, '_mphb_max_nights', 0 ) ?: null,
			'max_uses'   => (int) ospitano_migrate_meta( $p->ID, '_mphb_usage_limit', 0 ) ?: null,
		);
		if ( ! in_array( $type, array( 'percentage', 'per_accomm' ), true ) ) {
			/* translators: 1: code, 2: kind */
			$warnings[] = sprintf( 'Code %1$s is of kind "%2$s"; it was exported as a fixed amount per booking. Please check it.', $row['code'], $type );
		}
		$out[] = array_filter(
			$row,
			function ( $v ) {
				return null !== $v && '' !== $v;
			}
		);
	}
	return $out;
}

/** Payment and stay options of the old plugin, without anything secret. */
function ospitano_migrate_payment_options() {
	global $wpdb;
	$rows = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'mphb\\_%' AND (option_name LIKE '%deposit%' OR option_name LIKE '%amount_type%' OR option_name LIKE '%check\\_in%' OR option_name LIKE '%check\\_out%' OR option_name LIKE '%\\_enable' OR option_name LIKE '%confirmation%') LIMIT 80" ); // phpcs:ignore WordPress.DB
	$out  = array();
	foreach ( (array) $rows as $r ) {
		if ( preg_match( '/secret|key|token|pass|webhook|signature/i', $r->option_name ) || strlen( (string) $r->option_value ) > 60 ) {
			continue;
		}
		$out[ $r->option_name ] = (string) $r->option_value;
	}
	return $out;
}

/**
 * Builds the export.
 *
 * @param array $warnings Filled with things a person should check by hand.
 * @return array
 */
function ospitano_migrate_build( &$warnings = array() ) {
	$out = array(
		'format'      => 'ospitano-import/1',
		'source'      => 'motopress',
		'currency'    => strtoupper( (string) get_option( 'mphb_currency_symbol', 'EUR' ) ),
		'exported_at' => gmdate( 'c' ),
		'site'        => home_url(),
		'room_types'  => array(),
		'rate_plans'  => array(),
		'services'    => array(),
		'bookings'    => array(),
	);
	if ( 3 !== strlen( $out['currency'] ) ) {
		$out['currency'] = 'EUR';
	}

	// Translations made by WPML/Polylang are separate posts; keep only the originals.
	$is_translation = function ( $post ) {
		if ( function_exists( 'pll_get_post_language' ) && function_exists( 'pll_default_language' ) ) {
			return pll_get_post_language( $post->ID ) && pll_get_post_language( $post->ID ) !== pll_default_language();
		}
		if ( has_filter( 'wpml_element_language_details' ) ) {
			$d = apply_filters( 'wpml_element_language_details', null, array( 'element_id' => $post->ID, 'element_type' => $post->post_type ) );
			return $d && ! empty( $d->source_language_code );
		}
		return false;
	};

	foreach ( ospitano_migrate_posts( 'mphb_room_type' ) as $p ) {
		if ( $is_translation( $p ) ) {
			continue;
		}
		$adults   = (int) ospitano_migrate_meta( $p->ID, 'mphb_adults_capacity', 2 );
		$children = (int) ospitano_migrate_meta( $p->ID, 'mphb_children_capacity', 0 );
		$total    = (int) ospitano_migrate_meta( $p->ID, 'mphb_total_capacity', 0 );
		$out['room_types'][ $p->ID ] = array(
			'id'            => (string) $p->ID,
			'name'          => ospitano_migrate_text( $p->post_title ),
			'description'   => ospitano_migrate_text( $p->post_excerpt ? $p->post_excerpt : wp_trim_words( $p->post_content, 60 ) ),
			'max_adults'    => max( 1, $adults ),
			'max_children'  => max( 0, $children ),
			'max_occupancy' => $total > 0 ? $total : max( 1, $adults ) + max( 0, $children ),
			'images'        => ospitano_migrate_images( $p->ID ),
			'details'       => ospitano_migrate_details( $p->ID ),
			'size_m2'       => (int) ospitano_migrate_meta( $p->ID, 'mphb_size', 0 ) ?: null,
			'url'           => array( ospitano_migrate_lang() => get_permalink( $p ) ),
			'rooms'         => array(),
		);
	}

	foreach ( ospitano_migrate_posts( 'mphb_room' ) as $p ) {
		$type = (int) ospitano_migrate_meta( $p->ID, 'mphb_room_type_id', 0 );
		if ( ! isset( $out['room_types'][ $type ] ) ) {
			continue;
		}
		$out['room_types'][ $type ]['rooms'][] = array(
			'id'     => (string) $p->ID,
			'number' => $p->post_title ? $p->post_title : (string) $p->ID,
		);
	}

	// Seasons: date range plus the weekdays they apply to (stored 0 = Sunday .. 6 = Saturday).
	$seasons = array();
	foreach ( ospitano_migrate_posts( 'mphb_season' ) as $p ) {
		$days = (array) ospitano_migrate_meta( $p->ID, 'mphb_days', array() );
		$iso  = array();
		foreach ( $days as $d ) {
			$iso[] = 0 === (int) $d ? 7 : (int) $d;
		}
		$seasons[ $p->ID ] = array(
			'name'     => $p->post_title,
			'from'     => ospitano_migrate_meta( $p->ID, 'mphb_start_date' ),
			'to'       => ospitano_migrate_meta( $p->ID, 'mphb_end_date' ),
			'weekdays' => $iso ? array_values( array_unique( $iso ) ) : null,
		);
	}

	foreach ( ospitano_migrate_posts( 'mphb_rate' ) as $p ) {
		if ( $is_translation( $p ) ) {
			continue;
		}
		$type = (int) ospitano_migrate_meta( $p->ID, 'mphb_room_type_id', 0 );
		if ( ! isset( $out['room_types'][ $type ] ) ) {
			continue;
		}
		$prices = array();
		foreach ( (array) ospitano_migrate_meta( $p->ID, 'mphb_season_prices', array() ) as $sp ) {
			$season = $seasons[ (int) ( $sp['season'] ?? 0 ) ] ?? null;
			if ( ! $season || ! $season['from'] || ! $season['to'] ) {
				continue;
			}
			// The price is either a number or a structure whose first entry is the base nightly price.
			$price = $sp['price'] ?? null;
			if ( is_array( $price ) ) {
				if ( ! empty( $price['periods'] ) && count( (array) $price['periods'] ) > 1 ) {
					/* translators: 1: rate name, 2: season name */
					$warnings[] = sprintf( 'Rate "%1$s", season "%2$s": length-of-stay discounts are not exported, only the base price.', $p->post_title, $season['name'] );
				}
				$price = is_array( $price['prices'] ?? null ) ? reset( $price['prices'] ) : ( $price['price'] ?? null );
			}
			if ( ! is_numeric( $price ) ) {
				continue;
			}
			$prices[] = array(
				'from'        => $season['from'],
				'to'          => $season['to'],
				'price_cents' => ospitano_migrate_cents( $price ),
				'weekdays'    => $season['weekdays'],
				'season'      => $season['name'],
			);
		}
		// The season at the top of the list wins; on import later entries win, so reverse.
		$out['rate_plans'][] = array(
			'id'           => (string) $p->ID,
			'room_type_id' => (string) $type,
			'name'         => ospitano_migrate_text( $p->post_title ),
			'prices'       => array_reverse( $prices ),
		) + ospitano_migrate_deposit();
	}

	foreach ( ospitano_migrate_posts( 'mphb_room_service' ) as $p ) {
		if ( $is_translation( $p ) ) {
			continue;
		}
		$per_night  = 'per_night' === ospitano_migrate_meta( $p->ID, 'mphb_price_periodicity', 'once' );
		$per_person = 'per_adult' === ospitano_migrate_meta( $p->ID, 'mphb_price_quantity', 'once' );
		$out['services'][] = array(
			'id'          => (string) $p->ID,
			'name'        => ospitano_migrate_text( $p->post_title ),
			'description' => ospitano_migrate_text( $p->post_excerpt ),
			'price_cents' => ospitano_migrate_cents( ospitano_migrate_meta( $p->ID, 'mphb_price', 0 ) ),
			'pricing'     => $per_person ? ( $per_night ? 'per_person_night' : 'per_person' ) : ( $per_night ? 'per_night' : 'per_stay' ),
		);
	}

	// Payments received per booking.
	$paid = array();
	foreach ( ospitano_migrate_posts( 'mphb_payment', array( 'mphb-p-completed' ) ) as $p ) {
		$b          = (int) ospitano_migrate_meta( $p->ID, '_mphb_booking_id', 0 );
		$paid[ $b ] = ( $paid[ $b ] ?? 0 ) + ospitano_migrate_cents( ospitano_migrate_meta( $p->ID, '_mphb_amount', 0 ) );
	}

	$statuses = array( 'confirmed' => 'confirmed', 'cancelled' => 'cancelled' );
	foreach ( ospitano_migrate_posts( 'mphb_booking', 'any' ) as $p ) {
		if ( ! isset( $statuses[ $p->post_status ] ) ) {
			if ( 'abandoned' !== $p->post_status ) {
				/* translators: 1: booking id, 2: status */
				$warnings[] = sprintf( 'Booking #%1$d has status "%2$s" and was not exported. Confirm or cancel it first, then export again.', $p->ID, $p->post_status );
			}
			continue;
		}
		$rooms = array();
		foreach ( ospitano_migrate_posts( 'mphb_reserved_room', 'any', array( 'post_parent' => $p->ID ) ) as $r ) {
			$room_id = (int) ospitano_migrate_meta( $r->ID, '_mphb_room_id', 0 );
			$rate_id = (int) ospitano_migrate_meta( $r->ID, '_mphb_rate_id', 0 );
			$type    = (int) get_post_meta( $room_id, 'mphb_room_type_id', true );
			if ( ! $type && $rate_id ) {
				$type = (int) get_post_meta( $rate_id, 'mphb_room_type_id', true );
			}
			$rooms[] = array_filter(
				array(
					'room_type_id' => $type ? (string) $type : null,
					'rate_plan_id' => $rate_id ? (string) $rate_id : null,
					'room_id'      => $room_id ? (string) $room_id : null,
					'adults'       => max( 1, (int) ospitano_migrate_meta( $r->ID, '_mphb_adults', 1 ) ),
					'children'     => (int) ospitano_migrate_meta( $r->ID, '_mphb_children', 0 ),
				),
				function ( $v ) {
					return null !== $v;
				}
			);
		}
		$rooms = array_values(
			array_filter(
				$rooms,
				function ( $r ) {
					return isset( $r['room_type_id'] );
				}
			)
		);
		if ( ! $rooms ) {
			/* translators: %d: booking id */
			$warnings[] = sprintf( 'Booking #%d has no rooms and was not exported.', $p->ID );
			continue;
		}
		$total = ospitano_migrate_cents( ospitano_migrate_meta( $p->ID, 'mphb_total_price', 0 ) );
		$out['bookings'][] = array(
			'id'         => (string) $p->ID,
			'status'     => $statuses[ $p->post_status ],
			'source'     => 'website',
			'arrival'    => ospitano_migrate_meta( $p->ID, 'mphb_check_in_date' ),
			'departure'  => ospitano_migrate_meta( $p->ID, 'mphb_check_out_date' ),
			'created_at' => get_post_time( 'c', true, $p ),
			'guest'      => array(
				'first_name' => (string) ospitano_migrate_meta( $p->ID, 'mphb_first_name', '' ),
				'last_name'  => (string) ospitano_migrate_meta( $p->ID, 'mphb_last_name', '' ),
				'email'      => (string) ospitano_migrate_meta( $p->ID, 'mphb_email', '' ),
				'phone'      => (string) ospitano_migrate_meta( $p->ID, 'mphb_phone', '' ),
				'country'    => (string) ospitano_migrate_meta( $p->ID, 'mphb_country', '' ),
				'language'   => substr( (string) ospitano_migrate_meta( $p->ID, 'mphb_language', '' ), 0, 2 ),
			),
			'notes'      => (string) ospitano_migrate_meta( $p->ID, 'mphb_note', '' ),
			'total_cents' => $total,
			'paid_cents' => min( $total, $paid[ $p->ID ] ?? 0 ),
			'rooms'      => $rooms,
		);
	}

	$out['offers']   = ospitano_migrate_coupons( $warnings );
	$out['settings'] = array_filter(
		array(
			'pay_at_hotel'   => ospitano_migrate_option_bool( 'mphb_payment_gateway_cash_enable' ),
			'check_in_time'  => ospitano_migrate_time( get_option( 'mphb_check_in_time' ) ),
			'check_out_time' => ospitano_migrate_time( get_option( 'mphb_check_out_time' ) ),
		),
		function ( $v ) {
			return null !== $v;
		}
	);
	// Names and values of the payment options, so the import can be checked by a person. No secrets.
	$out['source_options'] = ospitano_migrate_payment_options();

	$out['room_types'] = array_values( $out['room_types'] );
	$out['warnings']   = array_values( array_unique( $warnings ) );
	return $out;
}

/** Admin download: Settings → OSPITANO Booking → "Download export". */
add_action(
	'admin_post_ospitano_migrate_export',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'ospitano-booking' ), 403 );
		}
		check_admin_referer( 'ospitano_migrate_export' );
		$data = ospitano_migrate_build();
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="ospitano-import-' . gmdate( 'Y-m-d' ) . '.json"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		exit;
	}
);

/** wp ospitano export [--file=<path>] */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'ospitano export',
		function ( $args, $assoc ) {
			if ( ! ospitano_migrate_available() ) {
				WP_CLI::error( 'No hotel booking data found on this site.' );
			}
			$warnings = array();
			$data     = ospitano_migrate_build( $warnings );
			$json     = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			if ( empty( $assoc['file'] ) ) {
				WP_CLI::line( $json );
				return;
			}
			file_put_contents( $assoc['file'], $json ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			foreach ( $warnings as $w ) {
				WP_CLI::warning( $w );
			}
			WP_CLI::success(
				sprintf(
					'%d room types, %d rate plans, %d services, %d bookings written to %s',
					count( $data['room_types'] ),
					count( $data['rate_plans'] ),
					count( $data['services'] ),
					count( $data['bookings'] ),
					$assoc['file']
				)
			);
		}
	);
}
