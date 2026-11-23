<?php
/**
 * Start page: greeting, today's numbers from Milcho, and shortcuts to the hotel sections.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

remove_action( 'welcome_panel', 'wp_welcome_panel' );

add_action(
	'wp_dashboard_setup',
	function () {
		global $wp_meta_boxes;
		// Drop every box (news, quick draft, activity, site health, plugin promos) for everyone.
		$wp_meta_boxes['dashboard'] = array();
		remove_action( 'welcome_panel', 'wp_welcome_panel' );
		wp_add_dashboard_widget( 'ospa_home', OSPA::hotel_name(), 'ospa_dashboard_home' );
	},
	9999
);

// Our box takes the full width: one column.
add_filter( 'screen_layout_columns', function ( $cols ) {
	$cols['dashboard'] = 1;
	return $cols;
} );
add_filter( 'get_user_option_screen_layout_dashboard', function () {
	return 1;
} );

/**
 * Today's numbers, from Milcho when it is installed. Cached for five minutes.
 *
 * @return array|null
 */
function ospa_today_numbers() {
	if ( ! class_exists( 'OSPM_Stats' ) || ! method_exists( 'OSPM_Stats', 'today' ) ) {
		return null;
	}
	$date = current_time( 'Y-m-d' );
	$key  = 'ospa_today_' . $date;
	$n    = get_transient( $key );
	if ( is_array( $n ) ) {
		return $n;
	}
	try {
		$t = OSPM_Stats::today( $date );
	} catch ( \Throwable $e ) {
		return null;
	}
	if ( ! is_array( $t ) ) {
		return null;
	}
	$n = array(
		'arrivals'   => count( (array) ( $t['arrivals'] ?? array() ) ),
		'departures' => count( (array) ( $t['departures'] ?? array() ) ),
		'occupancy'  => (int) ( $t['occupancy'] ?? 0 ),
		'occupied'   => (int) ( $t['occupied'] ?? 0 ),
		'rooms'      => (int) ( $t['rooms'] ?? 0 ),
		'pending'    => count( (array) ( $t['pending'] ?? array() ) ),
	);
	set_transient( $key, $n, 5 * MINUTE_IN_SECONDS );
	return $n;
}

function ospa_dashboard_home() {
	$user   = wp_get_current_user();
	$name   = $user->first_name ? $user->first_name : $user->display_name;
	$hour   = (int) current_time( 'G' );
	$hello  = $hour < 11 ? 'Добро утро' : ( $hour < 18 ? 'Добър ден' : 'Добър вечер' );
	$date   = wp_date( 'l, j F Y' );
	$milcho = ospa_assistant_url( 'ospitano-manager' );
	$nums   = ospa_today_numbers();
	?>
	<div class="ospa-home">
		<div class="ospa-home-head">
			<p class="ospa-eyebrow"><?php echo esc_html( $date ); ?></p>
			<h2 class="ospa-hello"><?php echo esc_html( $hello . ( $name ? ', ' . $name : '' ) ); ?></h2>
			<p class="ospa-sub">Всичко за <?php echo esc_html( OSPA::hotel_name() ); ?> на едно място.</p>
			<?php if ( $milcho ) : ?>
				<a class="ospa-head-btn" href="<?php echo esc_url( $milcho ); ?>">Отвори Милчо <span aria-hidden="true">→</span></a>
			<?php endif; ?>
		</div>

		<?php ospa_milcho_card(); ?>

		<?php if ( $nums && $nums['rooms'] ) : ?>
			<div class="ospa-stats">
				<div class="ospa-stat"><span class="ospa-stat-n"><?php echo (int) $nums['arrivals']; ?></span><span class="ospa-stat-l">пристигания днес</span></div>
				<div class="ospa-stat"><span class="ospa-stat-n"><?php echo (int) $nums['departures']; ?></span><span class="ospa-stat-l">заминавания днес</span></div>
				<div class="ospa-stat"><span class="ospa-stat-n"><?php echo (int) $nums['occupancy']; ?>%</span><span class="ospa-stat-l">заетост (<?php echo (int) $nums['occupied']; ?> от <?php echo (int) $nums['rooms']; ?> стаи)</span></div>
				<div class="ospa-stat"><span class="ospa-stat-n"><?php echo (int) $nums['pending']; ?></span><span class="ospa-stat-l">чакат потвърждение</span></div>
			</div>
		<?php endif; ?>

		<?php
		$cards = array(
			array( 'title' => 'Резервации', 'url' => admin_url( 'admin.php?page=ospa-bookings' ), 'icon' => 'dashicons-calendar-alt', 'descr' => 'Календар, цени, наличности, стаи и оферти' ),
			array( 'title' => 'Асистенти', 'url' => admin_url( 'admin.php?page=ospa-assistants' ), 'icon' => 'dashicons-format-chat', 'descr' => 'Асистентът за гости и Милчо' ),
			array( 'title' => 'Сайт', 'url' => admin_url( 'edit.php?post_type=page' ), 'icon' => 'dashicons-admin-site-alt3', 'descr' => 'Текстове, страници и снимки' ),
			array( 'title' => 'Помощ', 'url' => admin_url( 'admin.php?page=ospa-help' ), 'icon' => 'dashicons-sos', 'descr' => 'Отговори на честите въпроси' ),
			array( 'title' => 'Към сайта', 'url' => home_url( '/' ), 'icon' => 'dashicons-external', 'descr' => 'Отваря сайта в нов раздел', 'ext' => true ),
		);
		if ( ! OSPA::hotel() ) {
			// Full users do not have the hotel pages in their menu; send them to the preview instead.
			$cards = array_values(
				array_filter(
					$cards,
					function ( $c ) {
						return false === strpos( $c['url'], 'page=ospa-' );
					}
				)
			);
		}
		ospa_cards( $cards );
		?>
	</div>
	<?php
}
