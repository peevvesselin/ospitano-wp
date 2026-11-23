<?php
/**
 * Clean addresses: the panel at /admin/ and the login at /login instead of /wp-admin/ and /wp-login.php.
 *
 * Safety:
 * - The old addresses keep working (fallback), so nobody is locked out.
 * - The rewrite rules go into .htaccess only when switched on in the settings, and the new
 *   addresses are used only after a self-test confirms the server serves /admin/.
 * - Deactivating the plugin removes the rules; define OSPA_NO_CLEAN_URL in wp-config.php to force it off.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OSPA_HT_MARKER = 'OSPITANO Admin';
const OSPA_HT_VER    = '2'; // Bump when the rules below change; sites update on the next admin visit.

/** True when the clean addresses are switched on and verified. */
function ospa_clean_on() {
	if ( defined( 'OSPA_NO_CLEAN_URL' ) && OSPA_NO_CLEAN_URL ) {
		return false;
	}
	return (bool) get_option( 'ospa_clean_url_ok' );
}

function ospa_home_base() {
	$path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	return '/' . trim( $path, '/' ) . ( trim( $path, '/' ) ? '/' : '' );
}

function ospa_htaccess_file() {
	if ( ! function_exists( 'get_home_path' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	return get_home_path() . '.htaccess';
}

/** Our block, placed before the WordPress block so /admin/ never reaches the front end. */
function ospa_htaccess_write( $on ) {
	$file = ospa_htaccess_file();
	if ( ! file_exists( $file ) || ! is_writable( $file ) ) {
		return false;
	}
	$text  = (string) file_get_contents( $file );
	$text  = preg_replace( '/\n?# BEGIN ' . preg_quote( OSPA_HT_MARKER, '/' ) . '.*?# END ' . preg_quote( OSPA_HT_MARKER, '/' ) . "\n?/s", "\n", $text );
	$text  = ltrim( $text, "\n" );
	if ( $on ) {
		$base  = ospa_home_base();
		$block = "# BEGIN " . OSPA_HT_MARKER . "\n"
			. "<IfModule mod_rewrite.c>\n"
			. "RewriteEngine On\n"
			. "RewriteBase {$base}\n"
			. "RewriteRule ^admin$ {$base}admin/ [R=301,L]\n"
			. "RewriteRule ^admin/login/?$ {$base}login [R=301,L,QSA]\n"
			. "RewriteRule ^login/?$ wp-login.php [L,QSA]\n"
			. "RewriteRule ^admin/(.*)$ wp-admin/$1 [L,QSA]\n"
			. "</IfModule>\n"
			. "# END " . OSPA_HT_MARKER . "\n\n";
		$text  = $block . $text;
	}
	$ok = false !== file_put_contents( $file, $text, LOCK_EX );
	if ( $ok ) {
		update_option( 'ospa_ht_ver', $on ? OSPA_HT_VER : '' );
	}
	return $ok;
}

/** Does the server answer on /admin/? */
function ospa_clean_selftest() {
	$r = wp_remote_get(
		home_url( '/admin/admin-ajax.php' ),
		array(
			'timeout'   => 10,
			'sslverify' => false,
		)
	);
	if ( is_wp_error( $r ) ) {
		return false;
	}
	// admin-ajax.php without an action answers 400 (body "0" or empty); a missing route would be 404.
	return 400 === (int) wp_remote_retrieve_response_code( $r ) || '0' === trim( wp_remote_retrieve_body( $r ) );
}

/**
 * Switch on or off. Returns '' on success or a message for the settings page.
 */
function ospa_clean_switch( $on ) {
	if ( ! $on ) {
		delete_option( 'ospa_clean_url_ok' );
		ospa_htaccess_write( false );
		return '';
	}
	if ( ! ospa_htaccess_write( true ) ) {
		delete_option( 'ospa_clean_url_ok' );
		return 'Файлът .htaccess не може да се промени. Адресите остават старите.';
	}
	if ( ! ospa_clean_selftest() ) {
		ospa_htaccess_write( false );
		delete_option( 'ospa_clean_url_ok' );
		return 'Сървърът не отговори на новия адрес. Адресите остават старите.';
	}
	update_option( 'ospa_clean_url_ok', 1 );
	return '';
}

register_deactivation_hook(
	OSPA_FILE,
	function () {
		delete_option( 'ospa_clean_url_ok' );
		ospa_htaccess_write( false );
	}
);

/* ---------- Addresses ---------- */

add_filter(
	'admin_url',
	function ( $url ) {
		return ospa_clean_on() ? str_replace( '/wp-admin/', '/admin/', $url ) : $url;
	},
	99
);

$ospa_login_filter = function ( $url ) {
	if ( ! ospa_clean_on() || false === strpos( $url, 'wp-login.php' ) ) {
		return $url;
	}
	return str_replace( 'wp-login.php', 'login', $url );
};
add_filter( 'site_url', $ospa_login_filter, 99 );
add_filter( 'network_site_url', $ospa_login_filter, 99 );
add_filter( 'login_url', $ospa_login_filter, 99 );
add_filter( 'logout_url', $ospa_login_filter, 99 );
add_filter( 'lostpassword_url', $ospa_login_filter, 99 );
add_filter( 'register_url', $ospa_login_filter, 99 );

/* ---------- Login cookies for the /admin/ path ---------- */

function ospa_cookie_path() {
	return rtrim( SITECOOKIEPATH, '/' ) . '/admin';
}

add_action(
	'set_auth_cookie',
	function ( $cookie, $expire, $expiration, $user_id, $scheme ) {
		if ( ! ospa_clean_on() || headers_sent() ) {
			return;
		}
		$name = 'secure_auth' === $scheme ? SECURE_AUTH_COOKIE : AUTH_COOKIE;
		setcookie( $name, $cookie, $expire, ospa_cookie_path(), COOKIE_DOMAIN, 'secure_auth' === $scheme, true );
	},
	10,
	5
);

add_action(
	'clear_auth_cookie',
	function () {
		if ( headers_sent() ) {
			return;
		}
		$past = time() - YEAR_IN_SECONDS;
		setcookie( AUTH_COOKIE, ' ', $past, ospa_cookie_path(), COOKIE_DOMAIN );
		setcookie( SECURE_AUTH_COOKIE, ' ', $past, ospa_cookie_path(), COOKIE_DOMAIN );
	}
);

/*
 * Someone opens an old /wp-admin/ address while logged in: give the login cookie to /admin/ too
 * (so nobody has to log in again) and move them to the clean address.
 */
add_action(
	'admin_init',
	function () {
		if ( ! ospa_clean_on() || wp_doing_ajax() || ! is_user_logged_in() || headers_sent() ) {
			return;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$old = ospa_home_base() . 'wp-admin/';
		if ( 0 !== strpos( $uri, $old ) ) {
			return;
		}
		ospa_copy_cookie();
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';
		if ( 'GET' === $method ) {
			wp_safe_redirect( home_url( '/admin/' . substr( $uri, strlen( $old ) ) ) );
			exit;
		}
	},
	0
);

/** Copy the current login cookie (set for /wp-admin/) to the /admin/ path. */
function ospa_copy_cookie() {
	if ( headers_sent() ) {
		return;
	}
	$secure = is_ssl();
	$name   = $secure ? SECURE_AUTH_COOKIE : AUTH_COOKIE;
	if ( ! empty( $_COOKIE[ $name ] ) ) {
		$parts = wp_parse_auth_cookie( wp_unslash( $_COOKIE[ $name ] ), $secure ? 'secure_auth' : 'auth' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( $parts && ! empty( $parts['expiration'] ) ) {
			setcookie( $name, wp_unslash( $_COOKIE[ $name ] ), (int) $parts['expiration'] + 12 * HOUR_IN_SECONDS, ospa_cookie_path(), COOKIE_DOMAIN, $secure, true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}
	}
}

// After a plugin update with new rules, refresh our block in .htaccess.
add_action(
	'admin_init',
	function () {
		if ( ospa_clean_on() && OSPA_HT_VER !== get_option( 'ospa_ht_ver' ) && current_user_can( 'manage_options' ) ) {
			ospa_htaccess_write( true );
		}
	},
	1
);
