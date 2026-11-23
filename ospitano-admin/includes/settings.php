<?php
/**
 * Settings → OSPITANO Admin: who sees the full panel and what the hotel menu holds.
 * Only full users can open it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	function () {
		if ( ! OSPA::is_full() ) {
			return;
		}
		add_options_page( 'OSPITANO Admin', 'OSPITANO Admin', 'manage_options', 'ospitano-admin', 'ospa_settings_page' );
	}
);

// Snapshot of the full top-level menu, taken before hotel mode trims it, for the checkbox list.
add_action(
	'admin_menu',
	function () {
		global $menu, $ospa_full_menu;
		$ospa_full_menu = array();
		foreach ( (array) $menu as $item ) {
			$slug = $item[2] ?? '';
			if ( '' === $slug || false !== strpos( $item[4] ?? '', 'wp-menu-separator' ) ) {
				continue;
			}
			$title = trim( wp_strip_all_tags( preg_replace( '#<span.*$#s', '', $item[0] ) ) );
			$ospa_full_menu[ $slug ] = '' !== $title ? $title : $slug;
		}
	},
	9998
);

add_action(
	'admin_post_ospa_save',
	function () {
		check_admin_referer( 'ospa_save' );
		if ( ! current_user_can( 'manage_options' ) || ! OSPA::is_full() ) {
			wp_die( '', '', 403 );
		}
		$full = array_map( 'intval', (array) ( $_POST['full_users'] ?? array() ) );
		$me   = get_current_user_id();
		if ( ! in_array( $me, $full, true ) ) {
			$full[] = $me; // Never lock yourself out.
		}
		$menu = array_map( 'sanitize_text_field', array_map( 'wp_unslash', (array) ( $_POST['menu'] ?? array() ) ) );
		if ( ! in_array( 'index.php', $menu, true ) ) {
			$menu[] = 'index.php';
		}
		update_option(
			OSPA::OPT,
			array(
				'full_users' => array_values( array_unique( $full ) ),
				'menu'       => array_values( array_unique( $menu ) ),
				'hotel_mode' => ! empty( $_POST['hotel_mode'] ),
			)
		);
		wp_safe_redirect( admin_url( 'options-general.php?page=ospitano-admin&saved=1' ) );
		exit;
	}
);

function ospa_settings_page() {
	global $ospa_full_menu;
	$o     = OSPA::options();
	$users = get_users(
		array(
			'role__in' => array( 'administrator', 'editor', 'shop_manager' ),
			'orderby'  => 'display_name',
		)
	);
	?>
	<div class="wrap ospa-settings">
		<h1>OSPITANO Admin</h1>
		<?php if ( ! empty( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success"><p>Запазено.</p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ospa_save">
			<?php wp_nonce_field( 'ospa_save' ); ?>

			<h2>Кратко меню за хотела</h2>
			<p><label><input type="checkbox" name="hotel_mode" value="1" <?php checked( $o['hotel_mode'] ); ?>> Включено. Хората, които не са отметнати по-долу, виждат само избраните точки от менюто.</label></p>

			<h2>Пълен достъп</h2>
			<p class="description">Тези хора виждат цялото меню и настройките. Всички останали виждат краткото меню.</p>
			<fieldset class="ospa-checks">
				<?php foreach ( $users as $u ) : ?>
					<label><input type="checkbox" name="full_users[]" value="<?php echo (int) $u->ID; ?>" <?php checked( in_array( (int) $u->ID, $o['full_users'], true ) ); ?>>
						<?php echo esc_html( $u->display_name . ' (' . $u->user_login . ')' ); ?></label>
				<?php endforeach; ?>
			</fieldset>

			<h2>Какво вижда хотелът в менюто</h2>
			<fieldset class="ospa-checks">
				<?php foreach ( (array) $ospa_full_menu as $slug => $title ) : ?>
					<label><input type="checkbox" name="menu[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $o['menu'], true ) ); ?> <?php disabled( 'index.php' === $slug ); ?>>
						<?php echo esc_html( $title ); ?></label>
				<?php endforeach; ?>
				<label><input type="checkbox" name="menu[]" value="profile.php" <?php checked( in_array( 'profile.php', $o['menu'], true ) ); ?>> Профил</label>
			</fieldset>
			<?php submit_button( 'Запази' ); ?>
		</form>
	</div>
	<?php
}
