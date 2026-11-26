<?php
/**
 * Mila books appointments in the guest chat (salon, beauty studio, SPA): finds free times, books one, or puts the
 * guest on the waiting list when the day is full. Uses the public appointment API of the platform, so it works on
 * any site with a hotel code, also before room bookings move to the platform.
 *
 * @package OspitanoBooking
 */

defined( 'ABSPATH' ) || exit;

/** The place's appointment services (cached 10 minutes); empty when it has none, and then Mila gets no tools. */
function ospitano_appointment_services( $lang = 'bg' ) {
	$key  = 'ospitano_spa_services_' . $lang;
	$list = get_transient( $key );
	if ( false === $list ) {
		$r    = ospitano_core_public( 'spa', array( 'lang' => $lang ) );
		$list = is_wp_error( $r ) ? array() : array_values( (array) ( $r['services'] ?? array() ) );
		set_transient( $key, $list, is_wp_error( $r ) ? MINUTE_IN_SECONDS : 10 * MINUTE_IN_SECONDS );
	}
	return $list;
}

/** A service by id, or by a name the guest used ("маникюр"); null if none matches. */
function ospitano_appointment_find_service( $services, $input ) {
	$id = (int) ( $input['service_id'] ?? 0 );
	foreach ( $services as $s ) {
		if ( $id && (int) $s['id'] === $id ) {
			return $s;
		}
	}
	$name = mb_strtolower( trim( (string) ( $input['service'] ?? '' ) ) );
	if ( '' === $name ) {
		return null;
	}
	foreach ( $services as $s ) {
		if ( false !== mb_strpos( mb_strtolower( (string) $s['name'] ), $name ) ) {
			return $s;
		}
	}
	return null;
}

/** What Mila tells the guest about the services, when it could not tell which one is meant. */
function ospitano_appointment_menu( $services ) {
	return array_map(
		function ( $s ) {
			return array(
				'service_id'       => (int) $s['id'],
				'name'             => $s['name'],
				'category'         => $s['category'] ?? null,
				'duration_minutes' => $s['duration_minutes'],
				'price'            => ospitano_appointment_money( (int) $s['price_cents'], $s['currency'] ?? 'EUR' ),
				'specialists'      => array_map(
					function ( $p ) use ( $s ) {
						return array(
							'specialist_id' => (int) $p['id'],
							'name'          => $p['name'],
							'title'         => $p['title'] ?? null,
							'price'         => ospitano_appointment_money( (int) $p['price_cents'], $s['currency'] ?? 'EUR' ),
						);
					},
					(array) ( $s['specialists'] ?? array() )
				),
			);
		},
		$services
	);
}

function ospitano_appointment_money( $cents, $currency ) {
	return number_format( $cents / 100, 2, ',', ' ' ) . ' ' . $currency;
}

add_filter(
	'osp_ai_tools',
	function ( $tools ) {
		if ( '' === ospitano_booking_settings()['hotel'] || ! ospitano_appointment_services() ) {
			return $tools;
		}
		$tools[] = array(
			'name'         => 'find_appointment_times',
			'description'  => 'Свободни часове за услуга с час (подстригване, маникюр, масаж, СПА) в даден ден. Дай service_id (или име на услугата в service) и date. specialist_id само ако гостът иска конкретен служител. Ако денят е пълен, резултатът показва следващите дни със свободни часове; ако гостът иска точно този ден, предложи му списъка на чакащите (join_waitlist). Ако не е ясно коя услуга, резултатът връща списъка с услугите.',
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'service_id'    => array( 'type' => 'integer' ),
					'service'       => array( 'type' => 'string', 'description' => 'Името на услугата, ако нямаш service_id' ),
					'date'          => array( 'type' => 'string', 'description' => 'YYYY-MM-DD' ),
					'specialist_id' => array( 'type' => 'integer' ),
				),
				'required'   => array( 'date' ),
			),
		);
		$tools[] = array(
			'name'         => 'book_appointment',
			'description'  => 'Записва истински час. Извиквай САМО след като си показал на госта обобщение (услуга, ден и час, служител, цена, име, имейл, телефон) и той изрично е потвърдил. starts_at вземи точно от find_appointment_times. Ако резултатът има payment_url, дай го на госта и кажи до кога да плати, иначе часът се освобождава. Дай и guest_url: оттам гостът отменя или добавя часа в календара. Ако има error, обясни с прости думи и предложи друг час.',
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'service_id'    => array( 'type' => 'integer' ),
					'starts_at'     => array( 'type' => 'string', 'description' => 'starts_at от find_appointment_times' ),
					'specialist_id' => array( 'type' => 'integer', 'description' => 'Само ако гостът е избрал служител' ),
					'first_name'    => array( 'type' => 'string' ),
					'last_name'     => array( 'type' => 'string' ),
					'email'         => array( 'type' => 'string' ),
					'phone'         => array( 'type' => 'string' ),
					'pass_code'     => array( 'type' => 'string', 'description' => 'Код на карта за посещения или ваучер, ако гостът има' ),
					'notes'         => array( 'type' => 'string' ),
				),
				'required'   => array( 'service_id', 'starts_at', 'first_name', 'last_name', 'email' ),
			),
		);
		$tools[] = array(
			'name'         => 'join_waitlist',
			'description'  => 'Записва госта в списъка на чакащите, когато желаният ден е пълен. Щом се освободи час, гостът получава имейл и го взима с едно натискане. Питай за име и имейл; time_from/time_to само ако гостът може само в определени часове.',
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'service_id'    => array( 'type' => 'integer' ),
					'date_from'     => array( 'type' => 'string', 'description' => 'YYYY-MM-DD' ),
					'date_to'       => array( 'type' => 'string', 'description' => 'YYYY-MM-DD, ако гостът може няколко дни' ),
					'specialist_id' => array( 'type' => 'integer' ),
					'time_from'     => array( 'type' => 'string', 'description' => 'HH:MM, най-рано' ),
					'time_to'       => array( 'type' => 'string', 'description' => 'HH:MM, най-късно' ),
					'first_name'    => array( 'type' => 'string' ),
					'email'         => array( 'type' => 'string' ),
				),
				'required'   => array( 'service_id', 'date_from', 'first_name', 'email' ),
			),
		);
		return $tools;
	}
);

add_filter(
	'osp_ai_run_tool',
	function ( $result, $name, $input, $ctx = array() ) {
		if ( null !== $result || ! in_array( $name, array( 'find_appointment_times', 'book_appointment', 'join_waitlist' ), true ) ) {
			return $result;
		}
		$lang     = ospitano_chat_lang( $ctx );
		$services = ospitano_appointment_services( $lang );
		$service  = ospitano_appointment_find_service( $services, (array) $input );
		if ( ! $service ) {
			return array(
				'error'    => 'Не разбрах коя услуга. Ето услугите: попитай госта коя иска.',
				'services' => ospitano_appointment_menu( $services ),
			);
		}
		$names = array();
		foreach ( (array) ( $service['specialists'] ?? array() ) as $p ) {
			$names[ (int) $p['id'] ] = $p['name'];
		}

		if ( 'find_appointment_times' === $name ) {
			$date = sanitize_text_field( $input['date'] ?? '' );
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				return array( 'error' => 'Невалидна дата. Използвай формат YYYY-MM-DD.' );
			}
			$query = array( 'date' => $date );
			if ( ! empty( $input['specialist_id'] ) ) {
				$query['specialist_id'] = (int) $input['specialist_id'];
			}
			$day = function ( $d ) use ( $service, $query, $names ) {
				$r = ospitano_core_public( 'spa/' . (int) $service['id'] . '/slots', array( 'date' => $d ) + $query );
				if ( is_wp_error( $r ) ) {
					return array();
				}
				return array_map(
					function ( $s ) use ( $names ) {
						return array(
							'time'        => $s['time'],
							'starts_at'   => $s['starts_at'],
							'specialists' => array_values( array_filter( array_map( fn ( $id ) => $names[ (int) $id ] ?? null, (array) ( $s['specialists'] ?? array() ) ) ) ),
						);
					},
					(array) ( $r['slots'] ?? array() )
				);
			};
			$out = array(
				'service'          => $service['name'],
				'service_id'       => (int) $service['id'],
				'duration_minutes' => $service['duration_minutes'],
				'price'            => ospitano_appointment_money( (int) $service['price_cents'], $service['currency'] ?? 'EUR' ),
				'date'             => $date,
				'times'            => $day( $date ),
			);
			if ( ! empty( $service['specialists'] ) ) {
				$out['specialists'] = ospitano_appointment_menu( array( $service ) )[0]['specialists'];
			}
			if ( 'none' !== ( $service['online_payment'] ?? 'none' ) ) {
				$out['payment_note'] = 'deposit' === $service['online_payment']
					? 'При записване се плаща онлайн капаро ' . (int) $service['deposit_percent'] . '%' . ( ! empty( $service['online_payment_after_no_show'] ) ? ', само ако гостът е пропускал час преди' : '' ) . '.'
					: 'Цялата сума се плаща онлайн при записване' . ( ! empty( $service['online_payment_after_no_show'] ) ? ', само ако гостът е пропускал час преди' : '' ) . '.';
			}
			if ( ! $out['times'] ) {
				// The next days that still have times, so Mila can offer them right away.
				$next = array();
				for ( $i = 1, $t = strtotime( $date . ' 12:00' ); $i <= 14 && count( $next ) < 3; $i++ ) {
					$d     = gmdate( 'Y-m-d', $t + $i * DAY_IN_SECONDS );
					$times = $day( $d );
					if ( $times ) {
						$next[] = array( 'date' => $d, 'times' => array_slice( array_column( $times, 'time' ), 0, 6 ) );
					}
				}
				$out['next_days_with_times'] = $next;
				$out['note']                 = 'Денят е пълен. Предложи следващите дни или списъка на чакащите (join_waitlist).';
			}
			return $out;
		}

		if ( ! empty( $ctx['test'] ) ) {
			return array( 'ok' => true, 'reference' => 'SPA-TEST', 'note' => 'Тестов режим: нищо не е записано.' );
		}
		$email = sanitize_email( $input['email'] ?? '' );
		if ( ! is_email( $email ) ) {
			return array( 'error' => 'Имейлът не изглежда верен. Помоли госта да го напише отново.' );
		}
		$base = ospitano_core_base() . '/api/v1/public/' . rawurlencode( ospitano_booking_settings()['hotel'] );

		if ( 'join_waitlist' === $name ) {
			$body = array_filter(
				array(
					'service_id'    => (int) $service['id'],
					'specialist_id' => ! empty( $input['specialist_id'] ) ? (int) $input['specialist_id'] : null,
					'date_from'     => sanitize_text_field( $input['date_from'] ?? '' ),
					'date_to'       => sanitize_text_field( $input['date_to'] ?? '' ),
					'time_from'     => sanitize_text_field( $input['time_from'] ?? '' ),
					'time_to'       => sanitize_text_field( $input['time_to'] ?? '' ),
					'language'      => $lang,
				)
			);
			$body['guest'] = array( 'first_name' => sanitize_text_field( $input['first_name'] ?? '' ), 'email' => $email );
			$r             = ospitano_core_request( 'POST', $base . '/spa/waitlist', $body );
			if ( is_wp_error( $r ) ) {
				return array( 'error' => 'invalid_dates' === $r->get_error_code() ? 'Тези дни не могат да се чакат (до 60 дни напред, не в миналото).' : 'Не успях да запиша госта в списъка. Предложи да се обади.' );
			}
			return array( 'ok' => true, 'note' => 'Гостът е в списъка на чакащите. Щом се освободи час, ще получи имейл и ще го вземе с едно натискане.' );
		}

		$body = array(
			'service_id' => (int) $service['id'],
			'starts_at'  => sanitize_text_field( $input['starts_at'] ?? '' ),
			'language'   => $lang,
			'notes'      => sanitize_textarea_field( $input['notes'] ?? '' ),
			'guest'      => array_filter(
				array(
					'first_name' => sanitize_text_field( $input['first_name'] ?? '' ),
					'last_name'  => sanitize_text_field( $input['last_name'] ?? '' ),
					'email'      => $email,
					'phone'      => sanitize_text_field( $input['phone'] ?? '' ),
				)
			),
		);
		if ( ! empty( $input['specialist_id'] ) ) {
			$body['specialist_id'] = (int) $input['specialist_id'];
		}
		if ( ! empty( $input['pass_code'] ) ) {
			$body['pass_code'] = sanitize_text_field( $input['pass_code'] );
		}
		$a = ospitano_core_request( 'POST', $base . '/spa/appointments', $body );
		if ( is_wp_error( $a ) ) {
			$why = array(
				'not_available'      => 'Този час току-що беше зает. Провери отново с find_appointment_times и предложи друг.',
				'invalid_slot'       => 'Този час не се предлага. Провери отново с find_appointment_times.',
				'too_late'           => 'Този час вече не може да се запише онлайн. Предложи по-късен.',
				'pass_not_found'     => 'Кодът на картата или ваучера не е валиден. Помоли госта да го провери.',
				'pass_used_up'       => 'Картата или ваучерът вече са изчерпани.',
				'pass_wrong_service' => 'Тази карта е за друга услуга.',
			);
			return array( 'error' => $why[ $a->get_error_code() ] ?? 'Часът не можа да бъде записан. Предложи гостът да се обади.' );
		}
		$out = array(
			'ok'         => true,
			'reference'  => $a['reference'] ?? '',
			'service'    => $service['name'],
			'starts_at'  => $a['starts_at'] ?? '',
			'specialist' => $a['specialist']['name'] ?? null,
			'price'      => ospitano_appointment_money( (int) ( $a['price_cents'] ?? 0 ), $service['currency'] ?? 'EUR' ),
			'guest_url'  => $a['guest_url'] ?? null,
		);
		if ( ! empty( $a['pass_cents'] ) ) {
			$out['covered_by_card_or_voucher'] = ospitano_appointment_money( (int) $a['pass_cents'], $service['currency'] ?? 'EUR' );
		}
		if ( ! empty( $a['payment']['url'] ) ) {
			$out['payment_url'] = $a['payment']['url'];
			$out['pay_now']     = ospitano_appointment_money( (int) ( $a['due_now_cents'] ?? 0 ), $service['currency'] ?? 'EUR' );
			$out['pay_by']      = $a['pay_by'] ?? null;
			$out['note']        = 'Часът се пази до pay_by. Без плащане се освобождава.';
		}
		return $out;
	},
	10,
	4
);
