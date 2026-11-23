<?php
/**
 * Link between this site and the hotel on the OSPITANO platform, for the site's own assistants
 * (the manager's assistant and the guest chat). Connected with one click from the settings page:
 * the platform asks the manager to confirm, then posts a key back here. The key never shows on screen.
 *
 * Other plugins use:
 *   ospitano_core_connected()                       is the link set up
 *   ospitano_core_api( 'GET', 'bookings', $query )  staff API of this hotel, array or WP_Error
 *   ospitano_core_public( 'availability', $query )  public booking API, array or WP_Error
 *   do_action( 'ospitano_core_booking_event', $event, $reference )  when a booking is made or cancelled
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OSPITANO_CORE_LINK = 'ospitano_core_link';

function ospitano_core_link() {
	$l = get_option( OSPITANO_CORE_LINK, array() );
	return is_array( $l ) ? $l : array();
}

function ospitano_core_connected() {
	$s = ospitano_booking_settings();
	return '' !== $s['hotel'] && ! empty( ospitano_core_link()['token'] );
}

/** The platform is where bookings live once the site is switched to it. */
function ospitano_core_is_live() {
	return 'live' === ospitano_booking_settings()['replace'];
}

function ospitano_core_base() {
	return untrailingslashit( ospitano_booking_settings()['core_url'] );
}

/** Decoded JSON, or WP_Error with the platform's own message. */
function ospitano_core_request( $method, $url, $body = null, $token = '' ) {
	$args = array(
		'method'  => $method,
		'timeout' => 15,
		'headers' => array( 'Accept' => 'application/json' ),
	);
	if ( $token ) {
		$args['headers']['Authorization'] = 'Bearer ' . $token;
	}
	if ( null !== $body ) {
		$args['headers']['Content-Type'] = 'application/json';
		$args['body']                    = wp_json_encode( $body );
	}
	$res = wp_remote_request( $url, $args );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	if ( $code >= 400 ) {
		$msg = is_array( $data ) ? ( $data['message'] ?? ( $data['error'] ?? '' ) ) : '';
		return new WP_Error( is_array( $data ) && ! empty( $data['error'] ) ? (string) $data['error'] : 'http_' . $code, $msg ? $msg : 'HTTP ' . $code, array( 'status' => $code, 'data' => $data ) );
	}
	return is_array( $data ) ? $data : array();
}

function ospitano_core_api( $method, $path, $data = array() ) {
	if ( ! ospitano_core_connected() ) {
		return new WP_Error( 'not_connected', 'The site is not connected to OSPITANO.' );
	}
	$url = ospitano_core_base() . '/api/v1/properties/' . rawurlencode( ospitano_booking_settings()['hotel'] ) . '/' . ltrim( $path, '/' );
	if ( 'GET' === $method || 'DELETE' === $method ) {
		if ( $data ) {
			$url = add_query_arg( array_map( 'rawurlencode', $data ), $url );
		}
		$data = null;
	}
	return ospitano_core_request( $method, $url, $data, ospitano_core_link()['token'] );
}

function ospitano_core_public( $path, $query = array() ) {
	$hotel = ospitano_booking_settings()['hotel'];
	if ( '' === $hotel ) {
		return new WP_Error( 'no_hotel', 'No hotel code.' );
	}
	$url = ospitano_core_base() . '/api/v1/public/' . rawurlencode( $hotel ) . '/' . ltrim( $path, '/' );
	return ospitano_core_request( 'GET', add_query_arg( array_map( 'rawurlencode', $query ), $url ) );
}

/* ---------- connecting ---------- */

/** Step 1: the manager presses "Connect" here; we go to the platform with a one-time state. */
add_action(
	'admin_post_ospitano_connect',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'ospitano-booking' ), 403 );
		}
		check_admin_referer( 'ospitano_connect' );
		$s = ospitano_booking_settings();
		if ( '' === $s['hotel'] ) {
			wp_safe_redirect( admin_url( 'options-general.php?page=ospitano-booking' ) );
			exit;
		}
		$state = wp_generate_password( 40, false, false );
		set_transient( 'ospitano_connect_' . $state, get_current_user_id(), 15 * MINUTE_IN_SECONDS );
		$url = add_query_arg(
			array(
				'return' => rawurlencode( admin_url( 'admin-post.php?action=ospitano_connected' ) ),
				'state'  => $state,
			),
			ospitano_core_base() . '/admin/' . rawurlencode( $s['hotel'] ) . '/connect-site'
		);
		wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect -- the platform address is set by the admin.
		exit;
	}
);

/**
 * Step 2: the platform posts the key back. Browsers do not send the login cookie on this cross-site post,
 * so it is accepted without a login, only with the one-time state the logged-in admin created in step 1.
 */
function ospitano_core_connected_callback() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the one-time state is the check.
	$state = isset( $_POST['ospitano_state'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_POST['ospitano_state'] ) ) : '';
	$token = isset( $_POST['ospitano_token'] ) ? trim( (string) wp_unslash( $_POST['ospitano_token'] ) ) : '';
	$hotel = isset( $_POST['ospitano_hotel'] ) ? sanitize_title( wp_unslash( $_POST['ospitano_hotel'] ) ) : '';
	// phpcs:enable
	$back = admin_url( 'options-general.php?page=ospitano-booking' );
	if ( '' === $state || ! get_transient( 'ospitano_connect_' . $state ) || ! preg_match( '/^\d+\|[A-Za-z0-9]{20,}$/', $token ) ) {
		wp_safe_redirect( add_query_arg( 'ospitano_link', 'failed', $back ) );
		exit;
	}
	delete_transient( 'ospitano_connect_' . $state );
	if ( $hotel && $hotel !== ospitano_booking_settings()['hotel'] ) {
		wp_safe_redirect( add_query_arg( 'ospitano_link', 'hotel', $back ) );
		exit;
	}
	update_option( OSPITANO_CORE_LINK, array( 'token' => $token, 'connected_at' => time() ), false );
	$ok = ospitano_core_register_callback();
	wp_safe_redirect( add_query_arg( 'ospitano_link', is_wp_error( $ok ) ? 'noping' : 'ok', $back ) );
	exit;
}
add_action( 'admin_post_ospitano_connected', 'ospitano_core_connected_callback' );
add_action( 'admin_post_nopriv_ospitano_connected', 'ospitano_core_connected_callback' );

/** Tell the platform where to ping on new and cancelled bookings. */
function ospitano_core_register_callback() {
	$r = ospitano_core_api( 'PUT', 'integration', array( 'callback_url' => rest_url( 'ospitano/v1/ping' ) ) );
	if ( ! is_wp_error( $r ) ) {
		$l          = ospitano_core_link();
		$l['admin'] = (string) ( $r['admin_url'] ?? '' );
		$l['name']  = (string) ( $r['name'] ?? '' );
		$l['currency'] = (string) ( $r['currency'] ?? 'EUR' );
		update_option( OSPITANO_CORE_LINK, $l, false );
	}
	return $r;
}

add_action(
	'admin_post_ospitano_disconnect',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'ospitano-booking' ), 403 );
		}
		check_admin_referer( 'ospitano_disconnect' );
		if ( ospitano_core_connected() ) {
			ospitano_core_api( 'PUT', 'integration', array( 'callback_url' => null ) );
		}
		delete_option( OSPITANO_CORE_LINK );
		wp_safe_redirect( admin_url( 'options-general.php?page=ospitano-booking' ) );
		exit;
	}
);

/* ---------- pings from the platform ---------- */

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'ospitano/v1',
			'/ping',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true', // carries only a reference; listeners read the booking back with the key.
				'callback'            => function ( WP_REST_Request $req ) {
					$ref   = (string) $req->get_param( 'reference' );
					$event = (string) $req->get_param( 'event' );
					if ( ! ospitano_core_connected() || ! preg_match( '/^[A-Z0-9-]{4,40}$/', $ref ) || ! in_array( $event, array( 'booking.confirmed', 'booking.cancelled' ), true ) ) {
						return new WP_REST_Response( array( 'ok' => false ), 400 );
					}
					$seen = 'ospitano_ping_' . md5( $event . $ref );
					if ( get_transient( $seen ) ) {
						return array( 'ok' => true, 'duplicate' => true );
					}
					set_transient( $seen, 1, DAY_IN_SECONDS );
					do_action( 'ospitano_core_booking_event', $event, $ref );
					return array( 'ok' => true );
				},
			)
		);
	}
);

/* ---------- guest chat: free rooms and prices from the platform ---------- */

/**
 * The guest chat asks "check_availability"; once bookings live on the platform, answer from there
 * (same shape the chat already understands, with the price the guest will really pay).
 */
add_filter(
	'osp_ai_run_tool',
	function ( $result, $name, $input ) {
		if ( null !== $result || 'check_availability' !== $name || ! ospitano_core_is_live() ) {
			return $result;
		}
		$in       = sanitize_text_field( $input['check_in'] ?? '' );
		$out      = sanitize_text_field( $input['check_out'] ?? '' );
		$adults   = max( 1, (int) ( $input['adults'] ?? 2 ) );
		$children = max( 0, (int) ( $input['children'] ?? 0 ) );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $in ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $out ) ) {
			return array( 'error' => 'Невалидни дати. Използвай формат YYYY-MM-DD.' );
		}
		if ( $in < wp_date( 'Y-m-d' ) || $out <= $in ) {
			return array( 'error' => 'Датата на настаняване трябва да е днес или по-късно, а напускането след нея.' );
		}
		$lang = function_exists( 'pll_current_language' ) && pll_current_language( 'slug' ) ? pll_current_language( 'slug' ) : 'bg';
		$data = ospitano_core_public(
			'availability',
			array(
				'arrival'   => $in,
				'departure' => $out,
				'adults'    => $adults,
				'children'  => $children,
				'lang'      => $lang,
			)
		);
		if ( is_wp_error( $data ) ) {
			return array( 'error' => 'Системата за резервации не отговори. Предложи на госта да провери на сайта или да се свърже с рецепцията.' );
		}
		$s      = ospitano_booking_settings();
		$page   = (int) $s['results_page'] ? get_permalink( (int) $s['results_page'] ) : ( get_page_by_path( 'rezultati' ) ? get_permalink( get_page_by_path( 'rezultati' ) ) : home_url( '/' ) );
		$link   = add_query_arg(
			array(
				'mphb_check_in_date'  => $in,
				'mphb_check_out_date' => $out,
				'mphb_adults'         => $adults,
				'mphb_children'       => $children,
			),
			$page
		);
		$offer  = $data['offer'] ?? null;
		$pct    = is_array( $offer ) && 'percent' === ( $offer['type'] ?? '' ) ? (float) $offer['value'] : 0;
		$rooms  = array();
		foreach ( (array) ( $data['room_types'] ?? array() ) as $rt ) {
			if ( empty( $rt['bookable'] ) || empty( $rt['offers'] ) ) {
				continue;
			}
			$best = null;
			foreach ( $rt['offers'] as $o ) {
				if ( null === $best || $o['total_cents'] < $best['total_cents'] ) {
					$best = $o;
				}
			}
			$total   = $best['total_cents'] / 100;
			$rooms[] = array(
				'room_type'              => $rt['name'] ?? '',
				'free_units'             => (int) ( $rt['available'] ?? 0 ),
				'max_guests'             => (int) ( $rt['max_occupancy'] ?? 0 ),
				'total_price_eur'        => round( $total, 2 ),
				'price_after_offer_eur'  => $pct ? round( $total * ( 100 - $pct ) / 100, 2 ) : null,
				'rate'                   => $best['name'] ?? '',
				'page'                   => $rt['url'] ?? null,
			);
		}
		return array(
			'check_in'     => $in,
			'check_out'    => $out,
			'nights'       => (int) ( $data['nights'] ?? 0 ),
			'adults'       => $adults,
			'children'     => $children,
			'available'    => $rooms,
			'booking_link' => $link,
			'offer'        => $offer ? array(
				'title'    => $offer['label'] ?? $offer['code'] ?? '',
				'discount' => $pct ? $pct . '% от цената на настаняването' : '',
				'applied'  => 'автоматично при резервация през сайта',
			) : null,
			'check_out_rules' => $data['checkout_note'] ?? null,
			'pay_at_hotel'    => ! empty( $data['pay_at_hotel'] ),
			'note'         => 'total_price_eur е общо за престоя с ДДС и туристически данък, без допълнителни услуги. price_after_offer_eur е цената след офертата, ако има такава за тези дати: кажи я точно и че се приспада автоматично при резервация през сайта. Ако offer е null, не споменавай отстъпка. Ако списъкът е празен, няма свободни стаи. check_out_rules са правилата за късно напускане, кажи ги ако гостът пита. booking_link е обикновен линк към свободните стаи. Преведи имената на стаите на езика на госта и отговори на неговия език.',
		);
	},
	10,
	3
);
