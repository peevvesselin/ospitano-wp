<?php
/**
 * Help for a hotel that is new to the panel: a line on top of each section saying what it is for,
 * and a short tour of the menu on the first visit, shown until the hotelier presses "Разбрах".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The note for the screen being shown, or ''. */
function ospa_screen_intro() {
	global $pagenow, $plugin_page, $typenow;
	if ( 'edit.php' === $pagenow && 'page' === $typenow ) {
		return 'Страниците на сайта ви. Натиснете името на страница, за да смените текст или снимки; промените излизат на сайта, след като ги запазите.';
	}
	if ( 'upload.php' === $pagenow ) {
		return 'Всички снимки и файлове на сайта. Плъзнете нови снимки тук, за да ги качите, после ги избирате, когато редактирате страница.';
	}
	if ( 'profile.php' === $pagenow ) {
		return 'Вашето име, имейл и парола за вход. Новата парола се задава най-долу на страницата.';
	}
	$plugins = array(
		'ospitano-ai-log'   => 'Разговорите на асистента с гостите на сайта. Прочетете ги, за да видите какво питат гостите и къде асистентът има нужда от още информация.',
		'ospitano-ai-stats' => 'Колко гости са писали на асистента и за какво питат най-често.',
	);
	return isset( $plugins[ (string) $plugin_page ] ) ? $plugins[ (string) $plugin_page ] : '';
}

// Shown after the other notices are cleared for the hotel (hotel-mode.php clears them at 9999).
add_action(
	'in_admin_header',
	function () {
		if ( ! OSPA::hotel() ) {
			return;
		}
		add_action(
			'all_admin_notices',
			function () {
				$text = ospa_screen_intro();
				if ( '' !== $text ) {
					echo '<div class="ospa-intro"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><p>' . esc_html( $text ) . '</p></div>';
				}
			}
		);
	},
	10000
);

/** Has this user finished (or closed) the tour? */
function ospa_tour_done( $uid = 0 ) {
	return (bool) get_user_meta( $uid ? $uid : get_current_user_id(), 'ospa_tour_done', true );
}

/** Address that starts the tour again (from the Help page). */
function ospa_tour_url() {
	return add_query_arg( 'ospa_tour', '1', admin_url( 'index.php' ) );
}

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'index.php' !== $hook || ! OSPA::hotel() ) {
			return;
		}
		$again = ! empty( $_GET['ospa_tour'] ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $again && ospa_tour_done() ) {
			return;
		}
		$steps = array(
			array(
				'title' => 'Добре дошли в ' . OSPA::hotel_name(),
				'text'  => 'Това е вашият панел за хотела. За минута ще ви покажем къде какво има. Можете да спрете по всяко време.',
			),
			array(
				'el'    => '#adminmenu a[href$="page=ospa-bookings"]',
				'title' => 'Резервации',
				'text'  => 'Календарът на заетостта, всички резервации, цените по дни, стаите, услугите и промокодовете. Тук управлявате хотела всеки ден.',
			),
			array(
				'el'    => '#adminmenu a[href$="page=ospa-assistants"]',
				'title' => 'Асистенти',
				'text'  => 'Асистентът, който отговаря на гостите в сайта денонощно, и Милчо, вашият помощник управител. Тук четете разговорите и ги учите.',
			),
			array(
				'el'    => '.ospa-head-btn',
				'title' => 'Милчо',
				'text'  => 'Питайте Милчо кой пристига днес, колко е заетостта или какви са приходите. На телефона го отваряте като приложение.',
			),
			array(
				'el'    => '#adminmenu a[href="edit.php?post_type=page"]',
				'title' => 'Сайт',
				'text'  => 'Текстовете, страниците и снимките на сайта. Промените излизат на сайта, след като ги запазите.',
			),
			array(
				'el'    => '#adminmenu a[href="profile.php"]',
				'title' => 'Профил и помощ',
				'text'  => 'Вашата парола и отговори на честите въпроси. Оттам можете да пуснете тази обиколка отново.',
			),
		);
		wp_enqueue_script( 'ospa-tour', OSPA::asset( 'tour.js' ), array(), OSPA_VER, true );
		wp_localize_script(
			'ospa-tour',
			'ospaTour',
			array(
				'steps' => $steps,
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'ospa_tour' ),
			)
		);
	}
);

add_action(
	'wp_ajax_ospa_tour_done',
	function () {
		check_ajax_referer( 'ospa_tour' );
		update_user_meta( get_current_user_id(), 'ospa_tour_done', 1 );
		wp_send_json_success();
	}
);
