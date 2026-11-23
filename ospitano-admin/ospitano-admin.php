<?php
/**
 * Plugin Name: OSPITANO Admin
 * Description: Визия на OSPITANO за административния панел: вход, цветове, шрифтове, лого, начален екран и кратко меню за хотела.
 * Version: 1.0.1
 * Author: OSPITANO
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: ospitano-admin
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OSPA_VER', '1.0.1' );
define( 'OSPA_FILE', __FILE__ );
define( 'OSPA_URL', plugin_dir_url( __FILE__ ) );
define( 'OSPA_DIR', plugin_dir_path( __FILE__ ) );

require_once OSPA_DIR . 'includes/class-ospa.php';
require_once OSPA_DIR . 'includes/login.php';
require_once OSPA_DIR . 'includes/brand.php';
require_once OSPA_DIR . 'includes/hotel-mode.php';
require_once OSPA_DIR . 'includes/pages.php';
require_once OSPA_DIR . 'includes/dashboard.php';
require_once OSPA_DIR . 'includes/settings.php';

register_activation_hook( __FILE__, array( 'OSPA', 'activate' ) );
