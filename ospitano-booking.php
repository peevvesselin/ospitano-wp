<?php
/**
 * Plugin Name:       OSPITANO Booking
 * Plugin URI:        https://ospitano.eu/
 * Description:       Online booking form for hotels on the OSPITANO booking platform. Adds a shortcode and a block.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            OSPITANO
 * Author URI:        https://ospitano.eu/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ospitano-booking
 * Domain Path:       /languages
 *
 * This plugin only embeds the booking form. Availability, prices, bookings and payments are handled by the
 * OSPITANO platform, which requires an active OSPITANO subscription.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OSPITANO_BOOKING_VERSION', '1.0.0' );
define( 'OSPITANO_BOOKING_DEFAULT_CORE', 'https://core.ospitano.eu' );

/**
 * Settings with defaults.
 *
 * @return array{core_url:string,hotel:string,lang:string,accent:string}
 */
function ospitano_booking_settings() {
	$saved = get_option( 'ospitano_booking', array() );

	return wp_parse_args(
		is_array( $saved ) ? $saved : array(),
		array(
			'core_url' => OSPITANO_BOOKING_DEFAULT_CORE,
			'hotel'    => '',
			'lang'     => 'auto',
			'accent'   => '',
		)
	);
}

/** Language of the current page: WPML, Polylang, then the site locale. Falls back to Bulgarian. */
function ospitano_booking_language( $requested = '' ) {
	$supported = array( 'bg', 'en', 'ro', 'de', 'ru' );
	if ( $requested && 'auto' !== $requested && in_array( $requested, $supported, true ) ) {
		return $requested;
	}
	$lang = '';
	if ( function_exists( 'pll_current_language' ) ) {
		$lang = pll_current_language( 'slug' );
	} elseif ( defined( 'ICL_LANGUAGE_CODE' ) ) {
		$lang = ICL_LANGUAGE_CODE;
	}
	if ( ! $lang ) {
		$lang = substr( determine_locale(), 0, 2 );
	}

	return in_array( $lang, $supported, true ) ? $lang : 'bg';
}

/** Renders the booking form. Used by the shortcode and the block. */
function ospitano_booking_render( $atts = array() ) {
	$settings = ospitano_booking_settings();
	$atts     = shortcode_atts(
		array(
			'hotel' => $settings['hotel'],
			'lang'  => $settings['lang'],
		),
		$atts,
		'ospitano_booking'
	);

	if ( '' === $atts['hotel'] ) {
		return current_user_can( 'manage_options' )
			? '<p><em>' . esc_html__( 'OSPITANO Booking: set your hotel code in Settings → OSPITANO Booking.', 'ospitano-booking' ) . '</em></p>'
			: '';
	}

	$core = untrailingslashit( esc_url_raw( $settings['core_url'] ) );
	wp_enqueue_script( 'ospitano-booking-widget', $core . '/widget/v1/ospitano-booking.js', array(), OSPITANO_BOOKING_VERSION, true );

	$style = '';
	if ( preg_match( '/^#[0-9a-fA-F]{3,6}$/', (string) $settings['accent'] ) ) {
		$style = ' style="--osp-accent:' . esc_attr( $settings['accent'] ) . ';--osp-accent-dark:' . esc_attr( $settings['accent'] ) . '"';
	}

	return sprintf(
		'<ospitano-booking hotel="%s" lang="%s" api="%s"%s></ospitano-booking>',
		esc_attr( sanitize_title( $atts['hotel'] ) ),
		esc_attr( ospitano_booking_language( $atts['lang'] ) ),
		esc_attr( $core ),
		$style
	);
}

add_shortcode( 'ospitano_booking', 'ospitano_booking_render' );

/** The widget loads as a module-free deferred script. */
add_filter(
	'script_loader_tag',
	function ( $tag, $handle ) {
		return 'ospitano-booking-widget' === $handle ? str_replace( ' src=', ' defer src=', $tag ) : $tag;
	},
	10,
	2
);

add_action(
	'init',
	function () {
		load_plugin_textdomain( 'ospitano-booking', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		register_block_type(
			__DIR__ . '/blocks/booking',
			array( 'render_callback' => fn( $attributes ) => ospitano_booking_render( $attributes ) )
		);
	}
);

require_once __DIR__ . '/includes/settings.php';
