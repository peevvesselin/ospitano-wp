<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'ospitano_booking' );
delete_option( 'ospitano_ai_usage_days' );
wp_clear_scheduled_hook( 'ospitano_ai_usage_send' );
