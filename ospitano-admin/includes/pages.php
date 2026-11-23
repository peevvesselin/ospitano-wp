<?php
/**
 * Hotel sections that live in this plugin: Резервации, Асистенти, Помощ.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Address of the hotel's booking administration on the OSPITANO platform.
 */
function ospa_platform_url( $path = '' ) {
	$b    = get_option( 'ospitano_booking', array() );
	$core = is_array( $b ) && ! empty( $b['core_url'] ) ? $b['core_url'] : ( defined( 'OSPITANO_BOOKING_DEFAULT_CORE' ) ? OSPITANO_BOOKING_DEFAULT_CORE : 'https://core.ospitano.eu' );
	$code = is_array( $b ) && ! empty( $b['hotel'] ) ? sanitize_title( $b['hotel'] ) : '';
	$url  = untrailingslashit( $core ) . '/admin';
	if ( $code ) {
		$url .= '/' . rawurlencode( $code );
		if ( $path ) {
			$url .= '/' . ltrim( $path, '/' );
		}
	}
	return $url;
}

/**
 * Cards grid used by the section pages and the start page.
 *
 * @param array<int,array{title:string,url:string,icon:string,descr?:string,ext?:bool}> $cards
 */
function ospa_cards( $cards ) {
	echo '<div class="ospa-cards">';
	foreach ( $cards as $c ) {
		$ext = ! empty( $c['ext'] );
		printf(
			'<a class="ospa-card%s" href="%s"%s><span class="dashicons %s" aria-hidden="true"></span><span class="ospa-card-title">%s%s</span>%s</a>',
			$ext ? ' ospa-card-ext' : '',
			esc_url( $c['url'] ),
			$ext ? ' target="_blank" rel="noopener"' : '',
			esc_attr( $c['icon'] ),
			esc_html( $c['title'] ),
			$ext ? ' <span class="dashicons dashicons-external ospa-ext" aria-label="отваря се в нов раздел"></span>' : '',
			empty( $c['descr'] ) ? '' : '<span class="ospa-card-descr">' . esc_html( $c['descr'] ) . '</span>'
		);
	}
	echo '</div>';
}

function ospa_section_head( $eyebrow, $title, $sub ) {
	?>
	<div class="ospa-section-head">
		<p class="ospa-eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
		<h1 class="ospa-title"><?php echo esc_html( $title ); ?></h1>
		<p class="ospa-lead"><?php echo esc_html( $sub ); ?></p>
	</div>
	<?php
}

/** Booking screens, in the order of the tabs. Path is relative to the hotel's panel on the platform. */
function ospa_booking_views() {
	return array(
		'calendar' => array( 'Календар', 'calendar', 'dashicons-calendar-alt', 'Кои стаи са заети и кои свободни по дни' ),
		'bookings' => array( 'Резервации', 'bookings', 'dashicons-clipboard', 'Нови, потвърдени и отменени резервации' ),
		'rates'    => array( 'Цени и наличности', 'rates', 'dashicons-tag', 'Цени по дати, минимален престой, затворени дни' ),
		'rooms'    => array( 'Стаи', 'room-types', 'dashicons-admin-multisite', 'Типове стаи, снимки, описания и капацитет' ),
		'services' => array( 'Услуги и екстри', 'services', 'dashicons-star-filled', 'Закуска, паркинг, СПА и други добавки' ),
		'coupons'  => array( 'Оферти и промокодове', 'coupons', 'dashicons-megaphone', 'Отстъпки и кодове за директни резервации' ),
	);
}

/**
 * One-time sign-in link to a booking screen on the platform, for the current WordPress user.
 * The platform answers only to this site's own connection key, so the link cannot be made elsewhere.
 *
 * @return string URL, or '' when the site is not connected or the platform does not offer it yet.
 */
function ospa_sso_url( $path ) {
	if ( ! function_exists( 'ospitano_core_connected' ) || ! function_exists( 'ospitano_core_api' ) || ! ospitano_core_connected() ) {
		return '';
	}
	$user = wp_get_current_user();
	$res  = ospitano_core_api(
		'POST',
		'sso-links',
		array(
			'path'    => $path,
			'embed'   => true,
			'wp_user' => (int) $user->ID,
			'name'    => $user->display_name,
			'email'   => $user->user_email,
			'site'    => home_url( '/' ),
		)
	);
	if ( is_wp_error( $res ) || empty( $res['url'] ) ) {
		return '';
	}
	$url = esc_url_raw( (string) $res['url'] );
	// Only ever frame the platform we are connected to.
	$core = wp_parse_url( ospitano_core_base(), PHP_URL_HOST );
	return ( $core && wp_parse_url( $url, PHP_URL_HOST ) === $core ) ? $url : '';
}

function ospa_page_bookings() {
	$views = ospa_booking_views();
	$view  = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : 'calendar'; // phpcs:ignore WordPress.Security.NonceVerification
	$view  = isset( $views[ $view ] ) ? $view : 'calendar';
	$src   = ospa_sso_url( $views[ $view ][1] );

	if ( '' === $src ) {
		ospa_page_bookings_links();
		return;
	}
	echo '<div class="wrap ospa-section ospa-embed-wrap">';
	echo '<nav class="ospa-tabs" aria-label="Резервации">';
	foreach ( $views as $key => $v ) {
		printf(
			'<a class="ospa-tab%s" href="%s"%s><span class="dashicons %s" aria-hidden="true"></span>%s</a>',
			$key === $view ? ' is-active' : '',
			esc_url( admin_url( 'admin.php?page=ospa-bookings&view=' . $key ) ),
			$key === $view ? ' aria-current="page"' : '',
			esc_attr( $v[2] ),
			esc_html( $v[0] )
		);
	}
	echo '</nav>';
	printf(
		'<div class="ospa-embed"><div class="ospa-embed-loading">Зареждане…</div><iframe src="%s" title="%s" allow="clipboard-write" referrerpolicy="strict-origin" onload="this.parentNode.classList.add(\'is-loaded\')"></iframe></div>',
		esc_url( $src ),
		esc_attr( $views[ $view ][0] )
	);
	echo '</div>';
}

/** Before the site is connected (or if the platform is unreachable): plain links that open the platform. */
function ospa_page_bookings_links() {
	$milcho = ospa_assistant_url( 'ospitano-manager' );
	$cards  = array();
	foreach ( ospa_booking_views() as $v ) {
		$cards[] = array( 'title' => $v[0], 'url' => ospa_platform_url( $v[1] ), 'icon' => $v[2], 'descr' => $v[3], 'ext' => true );
	}
	echo '<div class="wrap ospa-section">';
	ospa_section_head( 'Резервации', 'Управление на хотела', 'Календарът, цените, наличностите и офертите се управляват в системата за резервации на OSPITANO. Всяка промяна там важи веднага за сайта.' );
	ospa_cards( $cards );
	if ( $milcho ) {
		echo '<div class="ospa-tip"><span class="dashicons dashicons-lightbulb" aria-hidden="true"></span><div><strong>По-бързо през Милчо.</strong> Днешните пристигания, разпределение на стаите и справки са в <a href="' . esc_url( $milcho ) . '">Милчо</a>.</div></div>';
	}
	echo '</div>';
}

/** URL of an assistant page that exists on this site, or ''. */
function ospa_assistant_url( $page ) {
	global $_registered_pages;
	foreach ( (array) $_registered_pages as $hook => $on ) {
		if ( substr( $hook, -strlen( '_page_' . $page ) ) === '_page_' . $page ) {
			return admin_url( 'admin.php?page=' . $page );
		}
	}
	return '';
}

function ospa_page_assistants() {
	global $ospa_assistants;
	$list = (array) $ospa_assistants;
	if ( ! $list ) {
		// Full users previewing nothing, or the menu was not rebuilt: look them up again.
		global $menu, $submenu;
		$list = ospa_find_assistants( (array) $menu, (array) $submenu );
	}
	$descr = array(
		'ospitano-manager' => 'Вашият помощник за управлението: пристигания, стаи, справки и чат върху резервациите',
		'ospitano-ai-log'  => 'Какво питат гостите и какво им отговаря асистентът',
		'ospitano-ai-stats' => 'Колко разговори и запитвания идват от сайта',
	);
	$cards = array();
	foreach ( $list as $a ) {
		$cards[] = array(
			'title' => $a['title'],
			'url'   => admin_url( $a['slug'] ),
			'icon'  => 'ospitano-manager' === $a['page'] ? 'dashicons-chart-area' : 'dashicons-format-chat',
			'descr' => $descr[ $a['page'] ] ?? '',
		);
	}
	echo '<div class="wrap ospa-section">';
	ospa_section_head( 'Асистенти', 'Вашите асистенти', 'Асистентът за гости отговаря в сайта денонощно, а Милчо помага на управителя. Тук виждате разговорите и обучавате асистентите с информация за хотела.' );
	if ( $cards ) {
		ospa_cards( $cards );
	} else {
		echo '<p class="ospa-empty">Все още няма свързани асистенти. Свържете се с OSPITANO, за да ги включим.</p>';
	}
	echo '</div>';
}

function ospa_page_help() {
	$o = OSPA::options();
	echo '<div class="wrap ospa-section">';
	ospa_section_head( 'Помощ', 'Как да работите с панела', 'Кратки отговори на най-честите въпроси.' );
	$faq = array(
		'Как да сменя цена или да затворя дата?' => 'Отворете Резервации → Цени и наличности. Промяната важи веднага за сайта.',
		'Как да видя кой пристига днес?'         => 'На Начало виждате днешните пристигания и заминавания. Подробности има в Милчо.',
		'Как да сменя текст или снимка в сайта?'  => 'Отворете Сайт → Страници, изберете страницата и редактирайте текста. Снимките се качват от Сайт → Снимки и файлове.',
		'Как да науча асистента на нещо ново?'    => 'Отворете Асистенти и добавете въпроса и отговора в обучението на асистента за гости.',
		'Как да сменя паролата си?'               => 'Отворете Профил и помощ → Профил и натиснете „Нова парола“ най-долу.',
	);
	echo '<p class="ospa-tour-again"><a class="button" href="' . esc_url( ospa_tour_url() ) . '">Покажи ми панела отново</a></p>';
	echo '<div class="ospa-faq">';
	foreach ( $faq as $q => $a ) {
		echo '<details><summary>' . esc_html( $q ) . '</summary><p>' . esc_html( $a ) . '</p></details>';
	}
	echo '</div>';
	if ( $o['help_email'] || $o['help_phone'] ) {
		echo '<div class="ospa-contact"><h2>Връзка с OSPITANO</h2><p>';
		if ( $o['help_phone'] ) {
			echo '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $o['help_phone'] ) ) . '"><span class="dashicons dashicons-phone" aria-hidden="true"></span> ' . esc_html( $o['help_phone'] ) . '</a>';
		}
		if ( $o['help_email'] ) {
			echo '<a href="mailto:' . esc_attr( $o['help_email'] ) . '"><span class="dashicons dashicons-email" aria-hidden="true"></span> ' . esc_html( $o['help_email'] ) . '</a>';
		}
		echo '</p></div>';
	}
	echo '</div>';
}
