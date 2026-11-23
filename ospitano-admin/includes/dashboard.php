<?php
/**
 * Start page: a welcome with shortcuts instead of the WordPress dashboard boxes.
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
 * Shortcut cards come from the menu the current user actually sees.
 */
function ospa_dashboard_items() {
	global $menu;
	$descr = array(
		'ospitano-ai'             => 'Асистентите на хотела',
		'ospitano-manager'        => 'Справки, разпределение на стаи и гости',
		'osai'                    => 'Запитвания и разговори с гости',
		'edit.php?post_type=page' => 'Текстовете и страниците на сайта',
		'upload.php'              => 'Снимки и файлове',
		'edit.php'                => 'Новини и оферти',
		'profile.php'             => 'Име, имейл и парола',
	);
	$keep  = OSPA::options()['menu']; // Same shortcuts for everyone: the hotel's everyday screens.
	$items = array();
	foreach ( (array) $menu as $item ) {
		$slug = $item[2] ?? '';
		if ( ! in_array( $slug, $keep, true ) ) {
			continue;
		}
		if ( '' === $slug || 'index.php' === $slug || false !== strpos( $item[4] ?? '', 'wp-menu-separator' ) ) {
			continue;
		}
		if ( ! current_user_can( $item[1] ) ) {
			continue;
		}
		$title = trim( wp_strip_all_tags( preg_replace( '#<span.*$#s', '', $item[0] ) ) );
		if ( '' === $title ) {
			continue;
		}
		$url = ( false !== strpos( $slug, '.php' ) ) ? admin_url( $slug ) : admin_url( 'admin.php?page=' . $slug );
		$icon = $item[6] ?? '';
		$items[] = array(
			'title' => $title,
			'url'   => $url,
			'icon'  => ( 0 === strpos( $icon, 'dashicons-' ) ) ? $icon : 'dashicons-arrow-right-alt',
			'descr' => $descr[ $slug ] ?? '',
		);
	}
	return $items;
}

function ospa_dashboard_home() {
	$user  = wp_get_current_user();
	$name  = $user->first_name ? $user->first_name : $user->display_name;
	$hour  = (int) current_time( 'G' );
	$hello = $hour < 11 ? 'Добро утро' : ( $hour < 18 ? 'Добър ден' : 'Добър вечер' );
	$date  = wp_date( 'l, j F Y' );
	?>
	<div class="ospa-home">
		<div class="ospa-home-head">
			<p class="ospa-eyebrow"><?php echo esc_html( $date ); ?></p>
			<h2 class="ospa-hello"><?php echo esc_html( $hello . ( $name ? ', ' . $name : '' ) ); ?></h2>
			<p class="ospa-sub">Всичко за <?php echo esc_html( OSPA::hotel_name() ); ?> на едно място.</p>
		</div>
		<div class="ospa-cards">
			<?php foreach ( ospa_dashboard_items() as $it ) : ?>
				<a class="ospa-card" href="<?php echo esc_url( $it['url'] ); ?>">
					<span class="dashicons <?php echo esc_attr( $it['icon'] ); ?>" aria-hidden="true"></span>
					<span class="ospa-card-title"><?php echo esc_html( $it['title'] ); ?></span>
					<?php if ( $it['descr'] ) : ?>
						<span class="ospa-card-descr"><?php echo esc_html( $it['descr'] ); ?></span>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
			<a class="ospa-card ospa-card-site" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener">
				<span class="dashicons dashicons-external" aria-hidden="true"></span>
				<span class="ospa-card-title">Към сайта</span>
				<span class="ospa-card-descr">Отваря сайта в нов раздел</span>
			</a>
		</div>
	</div>
	<?php
}
