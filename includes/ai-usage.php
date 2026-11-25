<?php
/**
 * Mila and Milcho tell the OSPITANO platform what they spend on Claude, so the OSPITANO team sees the AI cost of
 * each hotel. Each answer from Claude adds its token counts to today's totals here (per assistant and model);
 * once an hour the totals of the last few days go to the platform. A day sent again replaces the earlier numbers,
 * so a lost or repeated send never counts twice. Only counts leave the site, never what guests or staff wrote.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OSPITANO_AI_USAGE_OPTION = 'ospitano_ai_usage_days';

add_action(
	'ospitano_ai_usage',
	function ( $assistant, $model, $usage ) {
		if ( ! in_array( $assistant, array( 'mila', 'milcho' ), true ) || ! preg_match( '/^[a-z0-9.\-]{1,60}$/', (string) $model ) ) {
			return;
		}
		$days = get_option( OSPITANO_AI_USAGE_OPTION, array() );
		$days = is_array( $days ) ? $days : array();
		$day  = current_time( 'Y-m-d' );
		$key  = $assistant . '|' . $model;
		$row  = $days[ $day ][ $key ] ?? array( 'requests' => 0, 'input_tokens' => 0, 'output_tokens' => 0, 'cache_read_tokens' => 0, 'cache_write_tokens' => 0 );
		++$row['requests'];
		$row['input_tokens']       += (int) ( $usage['input_tokens'] ?? 0 );
		$row['output_tokens']      += (int) ( $usage['output_tokens'] ?? 0 );
		$row['cache_read_tokens']  += (int) ( $usage['cache_read_input_tokens'] ?? 0 );
		$row['cache_write_tokens'] += (int) ( $usage['cache_creation_input_tokens'] ?? 0 );
		$days[ $day ][ $key ]       = $row;
		ksort( $days );
		// Keep two weeks on the site: enough to resend after a long outage.
		update_option( OSPITANO_AI_USAGE_OPTION, array_slice( $days, -14, null, true ), false );
	},
	10,
	3
);

/** Sends the last 3 days' totals. Returns true, or WP_Error. */
function ospitano_ai_usage_send() {
	if ( ! function_exists( 'ospitano_core_connected' ) || ! ospitano_core_connected() ) {
		return new WP_Error( 'not_connected', 'The site is not connected to OSPITANO.' );
	}
	$days = get_option( OSPITANO_AI_USAGE_OPTION, array() );
	$rows = array();
	foreach ( array_slice( is_array( $days ) ? $days : array(), -3, null, true ) as $day => $by_key ) {
		foreach ( (array) $by_key as $key => $row ) {
			list( $assistant, $model ) = array_pad( explode( '|', (string) $key, 2 ), 2, '' );
			$rows[] = array_merge( array( 'day' => (string) $day, 'assistant' => $assistant, 'model' => $model ), array_map( 'intval', (array) $row ) );
		}
	}
	if ( ! $rows ) {
		return true;
	}
	$res = ospitano_core_api( 'POST', 'ai-usage', array( 'days' => $rows ) );
	return is_wp_error( $res ) ? $res : true;
}

add_action( 'ospitano_ai_usage_send', 'ospitano_ai_usage_send' );

add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'ospitano_ai_usage_send' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'ospitano_ai_usage_send' );
		}
	}
);
