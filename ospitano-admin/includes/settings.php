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
		$want = ! empty( $_POST['clean_url'] );
		$msg  = '';
		if ( $want !== ospa_clean_on() ) {
			$msg = ospa_clean_switch( $want );
			if ( '' === $msg && $want ) {
				ospa_copy_cookie(); // Stay logged in on the new address.
			}
		}
		update_option(
			OSPA::OPT,
			array(
				'full_users' => array_values( array_unique( $full ) ),
				'hotel_mode' => ! empty( $_POST['hotel_mode'] ),
				'help_email' => sanitize_email( wp_unslash( $_POST['help_email'] ?? '' ) ),
				'help_phone' => sanitize_text_field( wp_unslash( $_POST['help_phone'] ?? '' ) ),
			)
		);
		if ( $msg ) {
			set_transient( 'ospa_msg_' . $me, $msg, 120 );
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=ospitano-admin&saved=1' ) );
		exit;
	}
);

function ospa_settings_page() {
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
		<?php
		$msg = get_transient( 'ospa_msg_' . get_current_user_id() );
		if ( $msg ) :
			delete_transient( 'ospa_msg_' . get_current_user_id() );
			?>
			<div class="notice notice-error"><p><?php echo esc_html( $msg ); ?></p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ospa_save">
			<?php wp_nonce_field( 'ospa_save' ); ?>

			<h2>Кратко меню за хотела</h2>
			<p><label><input type="checkbox" name="hotel_mode" value="1" <?php checked( $o['hotel_mode'] ); ?>> Включено. Хората, които не са отметнати по-долу, виждат менюто на хотела: Начало, Резервации, Асистенти, Сайт, Профил и помощ.</label></p>

			<h2>Адрес на панела</h2>
			<p><label><input type="checkbox" name="clean_url" value="1" <?php checked( ospa_clean_on() ); ?>> Кратък адрес: <code><?php echo esc_html( home_url( '/admin' ) ); ?></code> за панела и <code><?php echo esc_html( home_url( '/admin/login' ) ); ?></code> за вход.</label></p>
			<p class="description">Старите адреси продължават да работят като резервен вход.</p>

			<h2>Пълен достъп</h2>
			<p class="description">Тези хора виждат цялото меню и настройките. Всички останали виждат краткото меню.</p>
			<fieldset class="ospa-checks">
				<?php foreach ( $users as $u ) : ?>
					<label><input type="checkbox" name="full_users[]" value="<?php echo (int) $u->ID; ?>" <?php checked( in_array( (int) $u->ID, $o['full_users'], true ) ); ?>>
						<?php echo esc_html( $u->display_name . ' (' . $u->user_login . ')' ); ?></label>
				<?php endforeach; ?>
			</fieldset>

			<h2>Връзка с OSPITANO на страница „Помощ“</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="ospa-help-phone">Телефон</label></th><td><input id="ospa-help-phone" class="regular-text" name="help_phone" value="<?php echo esc_attr( $o['help_phone'] ); ?>"></td></tr>
				<tr><th scope="row"><label for="ospa-help-email">Имейл</label></th><td><input id="ospa-help-email" type="email" class="regular-text" name="help_email" value="<?php echo esc_attr( $o['help_email'] ); ?>"></td></tr>
			</table>
			<?php submit_button( 'Запази' ); ?>
		</form>
	</div>
	<?php
}
