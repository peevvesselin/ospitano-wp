<?php
/**
 * Settings page: Settings → OSPITANO Booking.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	function () {
		add_options_page(
			__( 'OSPITANO Booking', 'ospitano-booking' ),
			__( 'OSPITANO Booking', 'ospitano-booking' ),
			'manage_options',
			'ospitano-booking',
			'ospitano_booking_settings_page'
		);
	}
);

add_action(
	'admin_init',
	function () {
		register_setting(
			'ospitano_booking',
			'ospitano_booking',
			array(
				'type'              => 'array',
				'sanitize_callback' => function ( $input ) {
					$input = is_array( $input ) ? $input : array();
					$lang  = isset( $input['lang'] ) ? sanitize_key( $input['lang'] ) : 'auto';

					return array(
						'core_url' => esc_url_raw( $input['core_url'] ?? OSPITANO_BOOKING_DEFAULT_CORE, array( 'https' ) ) ?: OSPITANO_BOOKING_DEFAULT_CORE,
						'hotel'    => sanitize_title( $input['hotel'] ?? '' ),
						'lang'     => in_array( $lang, array( 'auto', 'bg', 'en', 'ro', 'de', 'ru' ), true ) ? $lang : 'auto',
						'accent'   => sanitize_hex_color( $input['accent'] ?? '' ) ?: '',
						'button'        => empty( $input['button'] ) ? '' : '1',
						'replace'       => in_array( $input['replace'] ?? 'off', array( 'off', 'preview', 'live' ), true ) ? $input['replace'] : 'off',
						'results_page'  => absint( $input['results_page'] ?? 0 ),
						'checkout_page' => absint( $input['checkout_page'] ?? 0 ),
						'terms_page'    => absint( $input['terms_page'] ?? 0 ),
					);
				},
			)
		);
	}
);

function ospitano_booking_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s = ospitano_booking_settings();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'OSPITANO Booking', 'ospitano-booking' ); ?></h1>
		<p><?php esc_html_e( 'Connect this site to your hotel on the OSPITANO platform. Then add the "OSPITANO Booking" block or the [ospitano_booking] shortcode to any page.', 'ospitano-booking' ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'ospitano_booking' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="osp-hotel"><?php esc_html_e( 'Hotel code', 'ospitano-booking' ); ?></label></th>
					<td><input id="osp-hotel" name="ospitano_booking[hotel]" class="regular-text" value="<?php echo esc_attr( $s['hotel'] ); ?>">
						<p class="description"><?php esc_html_e( 'The short code of your hotel, given by OSPITANO (for example: hotel-sunrise).', 'ospitano-booking' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="osp-lang"><?php esc_html_e( 'Language', 'ospitano-booking' ); ?></label></th>
					<td><select id="osp-lang" name="ospitano_booking[lang]">
						<?php
						foreach ( array(
							'auto' => __( 'Same as the page (WPML, Polylang or site language)', 'ospitano-booking' ),
							'bg'   => 'Български',
							'en'   => 'English',
							'ro'   => 'Română',
							'de'   => 'Deutsch',
							'ru'   => 'Русский',
						) as $code => $label ) {
							printf( '<option value="%s"%s>%s</option>', esc_attr( $code ), selected( $s['lang'], $code, false ), esc_html( $label ) );
						}
						?>
					</select></td>
				</tr>
				<tr>
					<th scope="row"><label for="osp-accent"><?php esc_html_e( 'Button colour', 'ospitano-booking' ); ?></label></th>
					<td><input id="osp-accent" name="ospitano_booking[accent]" type="text" placeholder="#A98A5F" value="<?php echo esc_attr( $s['accent'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Book button', 'ospitano-booking' ); ?></th>
					<td><label><input name="ospitano_booking[button]" type="checkbox" value="1" <?php checked( $s['button'], '1' ); ?>>
						<?php esc_html_e( 'Show a floating "Book" button on every page. Links to #book and #spa open the booking and SPA forms.', 'ospitano-booking' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Booking pages', 'ospitano-booking' ); ?></th>
					<td>
						<?php
						foreach ( array(
							'results_page'  => __( 'Search results ([ospitano_results])', 'ospitano-booking' ),
							'checkout_page' => __( 'Checkout ([ospitano_checkout])', 'ospitano-booking' ),
							'terms_page'    => __( 'Terms and conditions', 'ospitano-booking' ),
						) as $key => $label ) {
							echo '<p><label>' . esc_html( $label ) . '<br>';
							wp_dropdown_pages(
								array(
									'name'              => 'ospitano_booking[' . esc_attr( $key ) . ']',
									'selected'          => (int) $s[ $key ],
									'show_option_none'  => __( '— same as the old booking plugin —', 'ospitano-booking' ),
									'option_none_value' => '0',
									'post_status'       => array( 'publish', 'private' ),
								)
							);
							echo '</label></p>';
						}
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="osp-replace"><?php esc_html_e( 'Replace the old booking plugin', 'ospitano-booking' ); ?></label></th>
					<td><select id="osp-replace" name="ospitano_booking[replace]">
						<?php
						foreach ( array(
							'off'     => __( 'Off: visitors and you see the old booking', 'ospitano-booking' ),
							'preview' => __( 'Preview: only administrators, after opening a page with ?ospitano_preview=1', 'ospitano-booking' ),
							'live'    => __( 'Live: everyone sees the OSPITANO booking', 'ospitano-booking' ),
						) as $code => $label ) {
							printf( '<option value="%s"%s>%s</option>', esc_attr( $code ), selected( $s['replace'], $code, false ), esc_html( $label ) );
						}
						?>
					</select>
					<p class="description"><?php esc_html_e( 'Shows the OSPITANO search, results and checkout in place of the old plugin\'s, on the same pages and in the same design. Nothing in the pages is edited, so "Off" brings the old booking back at once.', 'ospitano-booking' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="osp-core"><?php esc_html_e( 'Platform address', 'ospitano-booking' ); ?></label></th>
					<td><input id="osp-core" name="ospitano_booking[core_url]" type="url" class="regular-text" value="<?php echo esc_attr( $s['core_url'] ); ?>">
						<p class="description"><?php esc_html_e( 'Change only if OSPITANO support asks you to.', 'ospitano-booking' ); ?></p></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php if ( $s['hotel'] ) : ?>
			<h2 id="ospitano-assistants"><?php esc_html_e( 'Assistants', 'ospitano-booking' ); ?></h2>
			<?php
			$link_state = isset( $_GET['ospitano_link'] ) ? sanitize_key( wp_unslash( $_GET['ospitano_link'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$messages   = array(
				'ok'     => array( 'success', __( 'Connected. The assistants now work with the bookings on OSPITANO.', 'ospitano-booking' ) ),
				'noping' => array( 'warning', __( 'Connected, but OSPITANO could not be told where to send new-booking alerts. Press Connect again later.', 'ospitano-booking' ) ),
				'failed' => array( 'error', __( 'The connection was not completed. Please try again.', 'ospitano-booking' ) ),
				'hotel'  => array( 'error', __( 'That OSPITANO account is for another hotel code.', 'ospitano-booking' ) ),
			);
			if ( isset( $messages[ $link_state ] ) ) {
				printf( '<div class="notice notice-%s"><p>%s</p></div>', esc_attr( $messages[ $link_state ][0] ), esc_html( $messages[ $link_state ][1] ) );
			}
			$link = ospitano_core_link();
			?>
			<p><?php esc_html_e( 'Lets the assistants on this site (the manager\'s assistant and the guest chat) see and manage the bookings on OSPITANO, and sends an alert for every new booking.', 'ospitano-booking' ); ?></p>
			<?php if ( ospitano_core_connected() ) : ?>
				<p><strong><?php esc_html_e( 'Connected', 'ospitano-booking' ); ?></strong> — <?php echo esc_html( wp_date( 'd.m.Y H:i', (int) ( $link['connected_at'] ?? 0 ) ) ); ?></p>
				<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ospitano_connect' ), 'ospitano_connect' ) ); ?>"><?php esc_html_e( 'Connect again', 'ospitano-booking' ); ?></a>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ospitano_disconnect' ), 'ospitano_disconnect' ) ); ?>"><?php esc_html_e( 'Disconnect', 'ospitano-booking' ); ?></a></p>
			<?php else : ?>
				<p><a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ospitano_connect' ), 'ospitano_connect' ) ); ?>"><?php esc_html_e( 'Connect the assistants', 'ospitano-booking' ); ?></a></p>
			<?php endif; ?>
		<?php endif; ?>
		<?php if ( ospitano_migrate_available() ) : ?>
			<h2><?php esc_html_e( 'Move your existing bookings', 'ospitano-booking' ); ?></h2>
			<p><?php esc_html_e( 'This site has room types, rates and bookings from another booking plugin. Download them as one file and send it to OSPITANO to import. Nothing on this site is changed.', 'ospitano-booking' ); ?></p>
			<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ospitano_migrate_export' ), 'ospitano_migrate_export' ) ); ?>"><?php esc_html_e( 'Download export', 'ospitano-booking' ); ?></a></p>
		<?php endif; ?>
		<?php if ( $s['hotel'] ) : ?>
			<h2><?php esc_html_e( 'Preview', 'ospitano-booking' ); ?></h2>
			<?php echo ospitano_booking_render(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?>
		<?php endif; ?>
	</div>
	<?php
}

add_filter(
	'plugin_action_links_' . plugin_basename( dirname( __DIR__ ) . '/ospitano-booking.php' ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=ospitano-booking' ) ) . '">' . esc_html__( 'Settings', 'ospitano-booking' ) . '</a>' );

		return $links;
	}
);
