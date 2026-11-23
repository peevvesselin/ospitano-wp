<?php
/**
 * Milcho card on the start page: the same state the platform shows (active, free trial, ended),
 * read from the platform with the site's connection key.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @return array|null {active, on_trial, days_left, until, can_start_trial, trial_days, url} */
function ospa_milcho_state( $fresh = false ) {
	if ( ! function_exists( 'ospitano_core_connected' ) || ! function_exists( 'ospitano_core_api' ) || ! ospitano_core_connected() ) {
		return null;
	}
	$state = $fresh ? false : get_transient( 'ospa_milcho_state' );
	if ( is_array( $state ) ) {
		return $state;
	}
	$res = ospitano_core_api( 'GET', 'milcho' );
	if ( is_wp_error( $res ) || empty( $res['data'] ) || ! is_array( $res['data'] ) ) {
		return null;
	}
	set_transient( 'ospa_milcho_state', $res['data'], 5 * MINUTE_IN_SECONDS );
	return $res['data'];
}

function ospa_milcho_card() {
	$s = ospa_milcho_state();
	if ( ! $s ) {
		return;
	}
	$local = ospa_assistant_url( 'ospitano-manager' );
	$open  = $local ? $local : ( $s['url'] ?? '' );
	echo '<div class="ospa-milcho">';
	echo '<div class="ospa-milcho-text"><p class="ospa-eyebrow">Милчо · асистент на управителя</p>';
	if ( ! empty( $s['active'] ) && ! empty( $s['on_trial'] ) ) {
		$days = (int) ( $s['days_left'] ?? 0 );
		printf( '<h3>Пробен период: %s</h3><p>Милчо работи безплатно до %s. Ползвайте го от админа или от телефона.</p>', esc_html( 1 === $days ? 'остава 1 ден' : 'остават ' . $days . ' дни' ), esc_html( ospa_date( $s['until'] ?? '' ) ) );
	} elseif ( ! empty( $s['active'] ) ) {
		echo '<h3>Милчо е включен</h3><p>Пристигания, стаи, справки и задачи за деня, от админа или от телефона.</p>';
	} elseif ( ! empty( $s['can_start_trial'] ) ) {
		printf( '<h3>Пробвайте Милчо %d дни безплатно</h3><p>Асистент, който подрежда деня: пристигания, разпределение на стаите, цени и справки. Без договор и без карта.</p>', (int) ( $s['trial_days'] ?? 14 ) );
	} else {
		echo '<h3>Пробният период на Милчо приключи</h3><p>Свържете се с нас, за да продължите да го ползвате.</p>';
	}
	echo '</div><div class="ospa-milcho-actions">';
	if ( ! empty( $s['active'] ) ) {
		if ( $open ) {
			echo '<a class="button button-primary" href="' . esc_url( $open ) . '">Отвори Милчо</a>';
		}
		if ( ! empty( $s['url'] ) ) {
			echo '<a class="button" href="' . esc_url( $s['url'] ) . '" target="_blank" rel="noopener">На телефона</a>';
		}
	} elseif ( ! empty( $s['can_start_trial'] ) ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="ospa_milcho_trial">';
		wp_nonce_field( 'ospa_milcho_trial' );
		echo '<button class="button button-primary" type="submit">Започни безплатно</button></form>';
	} else {
		echo '<a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=ospa-help' ) ) . '">Свържи се с нас</a>';
	}
	echo '</div></div>';
}

function ospa_date( $ymd ) {
	$t = strtotime( (string) $ymd );
	return $t ? wp_date( 'j F Y', $t ) : (string) $ymd;
}

add_action(
	'admin_post_ospa_milcho_trial',
	function () {
		check_admin_referer( 'ospa_milcho_trial' );
		if ( ! current_user_can( 'read' ) || ! function_exists( 'ospitano_core_api' ) ) {
			wp_die( '', '', 403 );
		}
		ospitano_core_api( 'POST', 'milcho/trial' ); // 409 if already used: the card then shows the right state.
		delete_transient( 'ospa_milcho_state' );
		wp_safe_redirect( admin_url() );
		exit;
	}
);
