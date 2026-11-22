<?php
/**
 * Booking right in the site's pages: search bar, results and checkout, drawn by the OSPITANO script in the page itself
 * so they follow the site's own design.
 *
 * Shortcodes: [ospitano_search], [ospitano_results], [ospitano_checkout].
 *
 * Moving from another booking plugin: the "Replace" setting shows our parts in place of the old plugin's search,
 * results and checkout shortcodes, without editing any page.
 * - "preview": only for administrators who opened any page with ?ospitano_preview=1 (until ?ospitano_preview=0).
 *   Visitors keep seeing the old booking, so the switch can be checked page by page.
 * - "live": everyone sees the new booking. Setting it back to "off" restores the old one at once.
 *
 * @package OspitanoBooking
 */

defined( 'ABSPATH' ) || exit;

/** Shortcodes of the old booking plugin that we can stand in for, mapped to our parts. */
function ospitano_site_replaced_tags() {
	return array(
		'mphb_availability_search' => 'search',
		'mphb_search_results'      => 'results',
		'mphb_checkout'            => 'checkout',
	);
}

/** Page URL from a page ID, or ''. */
function ospitano_site_page_url( $id ) {
	$id = (int) $id;
	return $id > 0 && 'page' === get_post_type( $id ) ? (string) get_permalink( $id ) : '';
}

/** Where results, checkout and terms live: our settings first, then the old plugin's pages. */
function ospitano_site_pages() {
	$s    = ospitano_booking_settings();
	$pick = function ( $own, $old_option ) use ( $s ) {
		$url = ospitano_site_page_url( $s[ $own ] ?? 0 );
		return $url ? $url : ospitano_site_page_url( get_option( $old_option ) );
	};
	return array(
		'results'  => $pick( 'results_page', 'mphb_search_results_page' ),
		'checkout' => $pick( 'checkout_page', 'mphb_checkout_page' ),
		'terms'    => $pick( 'terms_page', 'mphb_terms_and_conditions_page' ),
	);
}

/** True when this request should show the new booking in place of the old plugin. */
function ospitano_site_replacing() {
	$mode = ospitano_booking_settings()['replace'] ?? 'off';
	if ( 'live' === $mode ) {
		return true;
	}
	return 'preview' === $mode && current_user_can( 'manage_options' ) && ! empty( $_COOKIE['ospitano_preview'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
}

/* Administrators turn the preview on and off with ?ospitano_preview=1 / 0 on any page. */
add_action(
	'init',
	function () {
		if ( ! isset( $_GET['ospitano_preview'] ) || ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$on = '1' === sanitize_key( wp_unslash( $_GET['ospitano_preview'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		setcookie( 'ospitano_preview', $on ? '1' : '', $on ? time() + 7 * DAY_IN_SECONDS : time() - 3600, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
		$_COOKIE['ospitano_preview'] = $on ? '1' : '';
	}
);

/** Markup for one part; the script is added once to the footer. */
function ospitano_site_part( $kind ) {
	$s = ospitano_booking_settings();
	if ( '' === $s['hotel'] ) {
		return current_user_can( 'manage_options' )
			? '<p><em>' . esc_html__( 'OSPITANO Booking: set your hotel code in Settings → OSPITANO Booking.', 'ospitano-booking' ) . '</em></p>'
			: '';
	}
	$core = untrailingslashit( esc_url_raw( $s['core_url'] ) );
	$p    = ospitano_site_pages();
	wp_enqueue_script( 'ospitano-site', $core . '/widget/v1/ospitano-site.js', array(), OSPITANO_BOOKING_VERSION, true );
	wp_add_inline_script(
		'ospitano-site',
		'window.OSPITANO_SITE=' . wp_json_encode(
			array(
				'api'      => $core,
				'hotel'    => sanitize_title( $s['hotel'] ),
				'lang'     => ospitano_booking_language( $s['lang'] ),
				'results'  => $p['results'] ? wp_make_link_relative( $p['results'] ) : '',
				'checkout' => $p['checkout'] ? wp_make_link_relative( $p['checkout'] ) : '',
				'terms'    => $p['terms'],
			)
		) . ';',
		'before'
	);
	return '<div class="ospitano-site ospitano-site--' . esc_attr( $kind ) . '" data-ospitano="' . esc_attr( $kind ) . '"></div>';
}

add_shortcode( 'ospitano_search', fn() => ospitano_site_part( 'search' ) );
add_shortcode( 'ospitano_results', fn() => ospitano_site_part( 'results' ) );
add_shortcode( 'ospitano_checkout', fn() => ospitano_site_part( 'checkout' ) );

/* Stand in for the old plugin's shortcodes (also inside page builders, which run shortcodes the same way). */
add_filter(
	'pre_do_shortcode_tag',
	function ( $output, $tag ) {
		$map = ospitano_site_replaced_tags();
		if ( false !== $output || ! isset( $map[ $tag ] ) || ! ospitano_site_replacing() ) {
			return $output;
		}
		return ospitano_site_part( $map[ $tag ] );
	},
	10,
	2
);

/* The old plugin's search widget (sidebar or page builder) gets the same treatment. */
add_filter(
	'widget_display_callback',
	function ( $instance, $widget, $args ) {
		if ( ! is_object( $widget ) || 0 !== strpos( (string) $widget->id_base, 'mphb_search_availability' ) || ! ospitano_site_replacing() ) {
			return $instance;
		}
		echo $args['before_widget'] . ospitano_site_part( 'search' ) . $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
		return false;
	},
	10,
	3
);

/* A clear sign in the admin bar while the preview is on. */
add_action(
	'admin_bar_menu',
	function ( $bar ) {
		$mode = ospitano_booking_settings()['replace'] ?? 'off';
		if ( 'off' === $mode || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$on = ospitano_site_replacing();
		$bar->add_node(
			array(
				'id'    => 'ospitano-preview',
				'title' => 'live' === $mode ? 'OSPITANO: на живо' : ( $on ? 'OSPITANO преглед: ВКЛ (изключи)' : 'OSPITANO преглед: изкл. (включи)' ),
				'href'  => 'live' === $mode ? admin_url( 'options-general.php?page=ospitano-booking' ) : add_query_arg( 'ospitano_preview', $on ? '0' : '1' ),
				'meta'  => array( 'title' => __( 'Only administrators see the new booking while the preview is on.', 'ospitano-booking' ) ),
			)
		);
	},
	100
);

add_filter(
	'script_loader_tag',
	function ( $tag, $handle ) {
		return 'ospitano-site' === $handle ? str_replace( ' src=', ' defer src=', $tag ) : $tag;
	},
	10,
	2
);
