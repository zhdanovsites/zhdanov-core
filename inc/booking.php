<?php
/**
 * Своя онлайн-запись по расписанию (без YCLIENTS).
 * Слот = занятие из «Расписания» на конкретную дату. Места: _zh_cap. Запись: тип zh_booking (виден только в админке).
 * Свободные места всегда считаются на сервере в момент записи.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** «9 ноября, понедельник» (день недели строчными внутри фразы). */
function zhdanov_booking_day( $ts ) {
	return wp_date( 'j F', $ts ) . ', ' . mb_strtolower( wp_date( 'l', $ts ) );
}

function zhdanov_booking_on() {
	return zhdanov_core_opt( 'booking_on' ) && zhdanov_core_type_on( 'zh_class' );
}

add_action( 'init', function () {
	register_post_type(
		'zh_booking',
		array(
			'labels'       => array( 'name' => 'Записи на занятия', 'singular_name' => 'Запись', 'menu_name' => 'Записи' ),
			'public'       => false,
			'show_ui'      => zhdanov_booking_on(),
			'show_in_menu' => 'zhdanov-core',
			'supports'     => array( 'title' ),
			'capabilities' => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap' => true,
		)
	);
} );

/** Занятые места на слот (класс + дата). */
function zhdanov_booking_taken( $class_id, $date ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore
		"SELECT COUNT(*) FROM {$wpdb->posts} p
		 JOIN {$wpdb->postmeta} a ON a.post_id = p.ID AND a.meta_key = '_zh_class' AND a.meta_value = %s
		 JOIN {$wpdb->postmeta} d ON d.post_id = p.ID AND d.meta_key = '_zh_bdate' AND d.meta_value = %s
		 JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = '_zh_bst' AND s.meta_value != 'canceled'
		 WHERE p.post_type = 'zh_booking'",
		(string) $class_id,
		$date
	) );
}

/** Слоты на N дней вперёд: [['id','date','time','ts','title','cap','free','who','place','dur']]. */
function zhdanov_booking_slots( $only_class = 0 ) {
	$tz     = wp_timezone();
	$now    = new DateTimeImmutable( 'now', $tz );
	$days   = max( 1, min( 90, (int) zhdanov_core_opt( 'booking_days' ) ? (int) zhdanov_core_opt( 'booking_days' ) : 14 ) );
	$cutoff = max( 0, (float) str_replace( ',', '.', (string) zhdanov_core_opt( 'booking_cutoff' ) ) );
	$limit  = $now->getTimestamp() + (int) ( $cutoff * HOUR_IN_SECONDS );
	$q      = array( 'post_type' => 'zh_class', 'post_status' => 'publish', 'numberposts' => 300 );
	if ( $only_class ) {
		$q['include'] = array( (int) $only_class );
	}
	$out = array();
	foreach ( get_posts( $q ) as $p ) {
		$cap = (int) zhdanov_core_m( $p->ID, '_zh_cap' );
		if ( $cap < 1 ) {
			continue; // занятие без мест для онлайн-записи
		}
		$time  = preg_match( '/^\d{1,2}:\d{2}$/', zhdanov_core_m( $p->ID, '_zh_time' ) ) ? zhdanov_core_m( $p->ID, '_zh_time' ) : '10:00';
		$day   = (int) zhdanov_core_m( $p->ID, '_zh_day' );
		$dates = array();
		if ( $day >= 1 && $day <= 7 ) {
			for ( $i = 0; $i <= $days; $i++ ) {
				$d = $now->modify( '+' . $i . ' day' );
				if ( (int) $d->format( 'N' ) === $day ) {
					$dates[] = $d->format( 'Y-m-d' );
				}
			}
		} elseif ( zhdanov_core_m( $p->ID, '_zh_date' ) ) {
			$dates[] = zhdanov_core_m( $p->ID, '_zh_date' );
		}
		foreach ( $dates as $date ) {
			$dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $date . ' ' . $time, $tz );
			if ( ! $dt || $dt->getTimestamp() < $limit || $dt->getTimestamp() > $now->getTimestamp() + ( $days + 1 ) * DAY_IN_SECONDS ) {
				continue;
			}
			$free  = max( 0, $cap - zhdanov_booking_taken( $p->ID, $date ) );
			$out[] = array( 'id' => $p->ID, 'date' => $date, 'time' => $time, 'ts' => $dt->getTimestamp(), 'title' => get_the_title( $p ), 'cap' => $cap, 'free' => $free, 'who' => zhdanov_core_m( $p->ID, '_zh_who' ), 'place' => zhdanov_core_m( $p->ID, '_zh_place' ), 'dur' => zhdanov_core_m( $p->ID, '_zh_duration' ) );
		}
	}
	usort( $out, function ( $a, $b ) {
		return $a['ts'] - $b['ts'];
	} );
	return $out;
}

/* ---------- REST: записаться ---------- */
add_action( 'rest_api_init', function () {
	register_rest_route( 'zhdanov/v1', '/book', array( 'methods' => 'POST', 'callback' => 'zhdanov_booking_create', 'permission_callback' => '__return_true' ) );
} );

function zhdanov_booking_create( $req ) {
	if ( ! zhdanov_booking_on() ) {
		return new WP_Error( 'zh_book', 'Онлайн-запись выключена.', array( 'status' => 400 ) );
	}
	$name  = sanitize_text_field( $req->get_param( 'name' ) );
	$phone = sanitize_text_field( $req->get_param( 'phone' ) );
	$email = sanitize_email( $req->get_param( 'email' ) );
	$cid   = (int) $req->get_param( 'class' );
	$date  = sanitize_text_field( $req->get_param( 'date' ) );
	if ( $req->get_param( 'website' ) || ! $name || strlen( preg_replace( '/\D/', '', $phone ) ) < 10 || ! $req->get_param( 'consent' ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
		return new WP_Error( 'zh_book', zhdanov_core__( 'Укажите имя, телефон и отметьте согласие.' ), array( 'status' => 400 ) );
	}
	$ipk = 'zh_bk_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
	if ( (int) get_transient( $ipk ) > 8 ) {
		return new WP_Error( 'zh_book', 'Слишком много записей подряд. Позвоните нам.', array( 'status' => 429 ) );
	}
	set_transient( $ipk, (int) get_transient( $ipk ) + 1, HOUR_IN_SECONDS );
	// Слот существует, ещё не прошёл и в нём есть места: проверяем заново на сервере.
	$slot = null;
	foreach ( zhdanov_booking_slots( $cid ) as $s ) {
		if ( $s['date'] === $date ) {
			$slot = $s;
		}
	}
	if ( ! $slot ) {
		return new WP_Error( 'zh_book', zhdanov_core__( 'На это время уже нельзя записаться. Выберите другое.' ), array( 'status' => 409 ) );
	}
	// Один человек дважды на один слот не записывается.
	$dup = get_posts( array( 'post_type' => 'zh_booking', 'post_status' => 'private', 'numberposts' => 1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_zh_class', 'value' => (string) $cid ), array( 'key' => '_zh_bdate', 'value' => $date ), array( 'key' => '_zh_tel', 'value' => preg_replace( '/\D/', '', $phone ) ), array( 'key' => '_zh_bst', 'value' => 'canceled', 'compare' => '!=' ) ) ) );
	if ( $dup ) {
		return new WP_Error( 'zh_book', zhdanov_core__( 'Вы уже записаны на это время.' ), array( 'status' => 409 ) );
	}
	$lock = 'zh_bklock_' . $cid . '_' . $date;
	if ( get_transient( $lock ) ) {
		usleep( 400000 );
	}
	set_transient( $lock, 1, 5 );
	if ( zhdanov_booking_taken( $cid, $date ) >= $slot['cap'] ) {
		delete_transient( $lock );
		return new WP_Error( 'zh_book', zhdanov_core__( 'Места на это время только что закончились. Выберите другое.' ), array( 'status' => 409 ) );
	}
	$when = zhdanov_booking_day( $slot['ts'] ) . ', ' . $slot['time'];
	$id   = wp_insert_post( array( 'post_type' => 'zh_booking', 'post_status' => 'private', 'post_title' => $slot['title'] . ' · ' . $when . ' · ' . $name ) );
	$key  = wp_generate_password( 20, false );
	foreach ( array( '_zh_class' => (string) $cid, '_zh_bdate' => $date, '_zh_btime' => $slot['time'], '_zh_ts' => $slot['ts'], '_zh_name' => $name, '_zh_phone' => $phone, '_zh_tel' => preg_replace( '/\D/', '', $phone ), '_zh_email' => $email, '_zh_bst' => 'new', '_zh_key' => $key, '_zh_note' => sanitize_textarea_field( $req->get_param( 'note' ) ) ) as $k => $v ) {
		update_post_meta( $id, $k, $v );
	}
	delete_transient( $lock );
	$cancel = add_query_arg( array( 'zapis' => $id, 'k' => $key, 'otmena' => 1 ), home_url( '/' ) );
	$text   = 'Новая запись: ' . $slot['title'] . "\n" . $when . ( $slot['place'] ? ', ' . $slot['place'] : '' ) . "\n" . $name . ', ' . $phone . ( $email ? ', ' . $email : '' ) . ( get_post_meta( $id, '_zh_note', true ) ? "\nКомментарий: " . get_post_meta( $id, '_zh_note', true ) : '' ) . "\nСвободно мест: " . max( 0, $slot['free'] - 1 ) . ' из ' . $slot['cap'] . "\nВсе записи: " . admin_url( 'edit.php?post_type=zh_booking' );
	$to     = zhdanov_core_opt( 'form_email' ) ? zhdanov_core_opt( 'form_email' ) : get_option( 'admin_email' );
	wp_mail( $to, 'Запись: ' . $slot['title'] . ', ' . $when, $text );
	if ( zhdanov_core_opt( 'tg_token' ) && zhdanov_core_opt( 'tg_chat' ) ) {
		zhdanov_core_tg_send( zhdanov_core_opt( 'tg_token' ), zhdanov_core_opt( 'tg_chat' ), $text );
	}
	if ( $email ) {
		wp_mail( $email, zhdanov_core__( 'Вы записаны' ) . ': ' . $slot['title'] . ', ' . $when, zhdanov_booking_client_text( $id, $slot, $when, $cancel ) );
	}
	return array(
		'ok'      => true,
		'message' => sprintf( zhdanov_core__( 'Вы записаны: %s.' ), $slot['title'] . ', ' . $when ),
		'ics'     => add_query_arg( array( 'zapis' => $id, 'k' => $key, 'ics' => 1 ), home_url( '/' ) ),
		'cancel'  => $cancel,
	);
}

function zhdanov_booking_client_text( $id, $slot, $when, $cancel ) {
	$c = zhdanov_core_contacts();
	return 'Здравствуйте, ' . get_post_meta( $id, '_zh_name', true ) . "!\n\nВы записаны: " . $slot['title'] . "\n" . $when . ( $slot['place'] ? "\n" . $slot['place'] : '' ) . ( zhdanov_core_opt( 'address' ) ? "\n" . zhdanov_core_opt( 'address' ) : '' ) .
		"\n\nНе получается прийти? Отмените запись, чтобы место досталось другому: " . $cancel .
		( ! empty( $c['phone'] ) ? "\nТелефон: " . zhdanov_core_opt( 'phone' ) : '' ) . "\n\n" . get_bloginfo( 'name' ) . ', ' . home_url( '/' );
}

/* ---------- Отмена и файл календаря по ссылке ---------- */
add_action( 'template_redirect', function () {
	if ( empty( $_GET['zapis'] ) || empty( $_GET['k'] ) ) { // phpcs:ignore
		return;
	}
	$id = (int) $_GET['zapis']; // phpcs:ignore
	if ( 'zh_booking' !== get_post_type( $id ) || ! hash_equals( (string) get_post_meta( $id, '_zh_key', true ), (string) wp_unslash( $_GET['k'] ) ) ) { // phpcs:ignore
		return;
	}
	$cls   = (int) get_post_meta( $id, '_zh_class', true );
	$ts    = (int) get_post_meta( $id, '_zh_ts', true );
	$title = get_the_title( $cls );
	if ( ! empty( $_GET['ics'] ) ) { // phpcs:ignore
		$min = (int) preg_replace( '/\D/', '', zhdanov_core_m( $cls, '_zh_duration' ) );
		$min = $min ? $min : 60;
		$f   = function ( $t ) {
			return gmdate( 'Ymd\THis\Z', $t );
		};
		$esc = function ( $s ) {
			return str_replace( array( '\\', ';', ',', "\n" ), array( '\\\\', '\;', '\,', '\n' ), $s );
		};
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="zapis.ics"' );
		echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Zhdanov//RU\r\nBEGIN:VEVENT\r\nUID:zh-" . (int) $id . '@' . esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ) . "\r\nDTSTAMP:" . $f( time() ) . "\r\nDTSTART:" . $f( $ts ) . "\r\nDTEND:" . $f( $ts + $min * 60 ) . "\r\nSUMMARY:" . $esc( $title . ' · ' . get_bloginfo( 'name' ) ) . "\r\nLOCATION:" . $esc( trim( zhdanov_core_m( $cls, '_zh_place' ) . ' ' . zhdanov_core_opt( 'address' ) ) ) . "\r\nBEGIN:VALARM\r\nTRIGGER:-PT2H\r\nACTION:DISPLAY\r\nDESCRIPTION:" . $esc( $title ) . "\r\nEND:VALARM\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"; // phpcs:ignore
		exit;
	}
	if ( ! empty( $_GET['otmena'] ) ) { // phpcs:ignore
		$st   = get_post_meta( $id, '_zh_bst', true );
		$done = false;
		if ( 'canceled' !== $st && $ts > time() && ! empty( $_POST['zh_cancel'] ) && check_admin_referer( 'zh_cancel_' . $id ) ) { // подтверждение кнопкой, а не переходом по ссылке
			update_post_meta( $id, '_zh_bst', 'canceled' );
			do_action( 'zhdanov_booking_canceled', $id );
			$done = true;
			$txt  = 'Отмена записи: ' . $title . ', ' . wp_date( 'j F, H:i', $ts ) . ', ' . get_post_meta( $id, '_zh_name', true ) . ', ' . get_post_meta( $id, '_zh_phone', true );
			wp_mail( zhdanov_core_opt( 'form_email' ) ? zhdanov_core_opt( 'form_email' ) : get_option( 'admin_email' ), 'Отмена записи: ' . $title, $txt );
			if ( zhdanov_core_opt( 'tg_token' ) && zhdanov_core_opt( 'tg_chat' ) ) {
				zhdanov_core_tg_send( zhdanov_core_opt( 'tg_token' ), zhdanov_core_opt( 'tg_chat' ), $txt );
			}
		}
		$st = get_post_meta( $id, '_zh_bst', true );
		header( 'X-Robots-Tag: noindex' );
		wp_die(
			'<h1>' . esc_html( $title ) . '</h1><p>' . esc_html( wp_date( 'j F Y, l, H:i', $ts ) ) . '</p>' .
			( 'canceled' === $st ? '<p><b>' . esc_html( $done ? zhdanov_core__( 'Запись отменена. Спасибо, что предупредили!' ) : zhdanov_core__( 'Эта запись уже отменена.' ) ) . '</b></p>' :
			( $ts <= time() ? '<p>' . esc_html( zhdanov_core__( 'Занятие уже прошло.' ) ) . '</p>' :
			'<form method="post">' . wp_nonce_field( 'zh_cancel_' . $id, '_wpnonce', true, false ) . '<input type="hidden" name="zh_cancel" value="1"><p><button type="submit" class="button">' . esc_html( zhdanov_core__( 'Отменить запись' ) ) . '</button></p></form>' ) ) .
			'<p><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( get_bloginfo( 'name' ) ) . '</a></p>',
			esc_html( zhdanov_core__( 'Ваша запись' ) ),
			array( 'response' => 200 )
		);
	}
}, 2 );

/* ---------- Напоминание за день (письмо клиенту, если оставил почту) ---------- */
add_action( 'init', function () {
	if ( zhdanov_booking_on() && ! wp_next_scheduled( 'zhdanov_booking_remind' ) ) {
		wp_schedule_event( time() + 300, 'hourly', 'zhdanov_booking_remind' );
	}
} );
add_action( 'zhdanov_booking_remind', function () {
	$soon = get_posts( array( 'post_type' => 'zh_booking', 'post_status' => 'private', 'numberposts' => 200, 'meta_query' => array( array( 'key' => '_zh_ts', 'value' => array( time() + 20 * HOUR_IN_SECONDS, time() + 26 * HOUR_IN_SECONDS ), 'compare' => 'BETWEEN', 'type' => 'NUMERIC' ), array( 'key' => '_zh_bst', 'value' => 'canceled', 'compare' => '!=' ), array( 'key' => '_zh_reminded', 'compare' => 'NOT EXISTS' ) ) ) );
	foreach ( $soon as $b ) {
		update_post_meta( $b->ID, '_zh_reminded', 1 );
		$email = get_post_meta( $b->ID, '_zh_email', true );
		if ( ! $email ) {
			continue;
		}
		$cls    = (int) get_post_meta( $b->ID, '_zh_class', true );
		$ts     = (int) get_post_meta( $b->ID, '_zh_ts', true );
		$when   = zhdanov_booking_day( $ts ) . ', ' . get_post_meta( $b->ID, '_zh_btime', true );
		$slot   = array( 'title' => get_the_title( $cls ), 'place' => zhdanov_core_m( $cls, '_zh_place' ) );
		$cancel = add_query_arg( array( 'zapis' => $b->ID, 'k' => get_post_meta( $b->ID, '_zh_key', true ), 'otmena' => 1 ), home_url( '/' ) );
		wp_mail( $email, zhdanov_core__( 'Напоминание: завтра' ) . ' ' . $slot['title'], zhdanov_booking_client_text( $b->ID, $slot, $when, $cancel ) );
	}
} );

/* ---------- Админка: колонки, пришёл/отменить ---------- */
add_filter( 'manage_zh_booking_posts_columns', function () {
	return array( 'cb' => '<input type="checkbox">', 'title' => 'Запись', 'zh_who' => 'Кто', 'zh_st' => 'Статус', 'date' => 'Создана' );
} );
add_action( 'manage_zh_booking_posts_custom_column', function ( $col, $id ) {
	$st = array( 'new' => 'записан(а)', 'came' => '✅ пришёл(ла)', 'noshow' => 'не пришёл(ла)', 'canceled' => 'отменено' );
	if ( 'zh_who' === $col ) {
		echo esc_html( get_post_meta( $id, '_zh_name', true ) ) . '<br><a href="tel:' . esc_attr( get_post_meta( $id, '_zh_tel', true ) ) . '">' . esc_html( get_post_meta( $id, '_zh_phone', true ) ) . '</a>';
		if ( get_post_meta( $id, '_zh_note', true ) ) {
			echo '<br><small>' . esc_html( get_post_meta( $id, '_zh_note', true ) ) . '</small>';
		}
	} elseif ( 'zh_st' === $col ) {
		$s = get_post_meta( $id, '_zh_bst', true );
		echo esc_html( isset( $st[ $s ] ) ? $st[ $s ] : $s );
	}
}, 10, 2 );
add_filter( 'post_row_actions', function ( $a, $p ) {
	if ( 'zh_booking' !== $p->post_type ) {
		return $a;
	}
	foreach ( array( 'came' => 'Пришёл(ла)', 'noshow' => 'Не пришёл(ла)', 'canceled' => 'Отменить' ) as $k => $l ) {
		$a[ 'zh_' . $k ] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=zhdanov_bst&st=' . $k . '&id=' . $p->ID ), 'zh_bst_' . $p->ID ) ) . '">' . esc_html( $l ) . '</a>';
	}
	return $a;
}, 10, 2 );
add_action( 'admin_post_zhdanov_bst', function () {
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore
	$st = isset( $_GET['st'] ) ? sanitize_key( $_GET['st'] ) : ''; // phpcs:ignore
	if ( ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'zh_bst_' . $id ) || ! in_array( $st, array( 'came', 'noshow', 'canceled' ), true ) ) {
		wp_die( 'Нет доступа.' );
	}
	$was = get_post_meta( $id, '_zh_bst', true );
	update_post_meta( $id, '_zh_bst', $st );
	if ( 'canceled' === $st && 'canceled' !== $was ) {
		do_action( 'zhdanov_booking_canceled', $id );
	}
	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=zh_booking' ) );
	exit;
} );
/* Ближайшие записи сверху. */
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() && $q->is_main_query() && 'zh_booking' === $q->get( 'post_type' ) && ! $q->get( 'orderby' ) ) {
		$q->set( 'meta_key', '_zh_ts' );
		$q->set( 'orderby', 'meta_value_num' );
		$q->set( 'order', 'ASC' );
		if ( empty( $_GET['vse'] ) ) { // phpcs:ignore
			$q->set( 'meta_query', array( array( 'key' => '_zh_ts', 'value' => time() - DAY_IN_SECONDS, 'compare' => '>=', 'type' => 'NUMERIC' ) ) );
		}
	}
} );
add_action( 'admin_notices', function () {
	$s = get_current_screen();
	if ( $s && 'edit-zh_booking' === $s->id ) {
		echo '<div class="notice notice-info"><p>' . ( empty( $_GET['vse'] ) ? 'Показаны предстоящие записи. <a href="' . esc_url( add_query_arg( 'vse', 1 ) ) . '">Показать все, включая прошлые</a>' : '<a href="' . esc_url( remove_query_arg( 'vse' ) ) . '">Только предстоящие</a>' ) . '</p></div>'; // phpcs:ignore
	}
} );

/** Интерфейс записи: дни (вкладки) и время со свободными местами. */
function zhdanov_booking_html( $class_id = 0, $title = '', $wrap = 'class="zh-booking"' ) {
	$slots = zhdanov_booking_slots( $class_id );
	$h     = '<div ' . $wrap . ' data-zh-booking="1">';
	if ( $title ) {
		$h .= '<h2 class="zh-booking__title">' . esc_html( zhdanov_core__( $title ) ) . '</h2>';
	}
	if ( ! $slots ) {
		return $h . '<p class="zh-empty">' . esc_html( zhdanov_core__( 'Ближайших свободных окон нет. Напишите нам: подберём время.' ) ) . '</p></div>';
	}
	$days = array();
	foreach ( $slots as $s ) {
		$days[ $s['date'] ][] = $s;
	}
	$today = wp_date( 'Y-m-d' );
	$tom   = wp_date( 'Y-m-d', time() + DAY_IN_SECONDS );
	$h    .= '<div class="zh-filter zh-booking__days" role="tablist" aria-label="' . esc_attr( zhdanov_core__( 'Дата' ) ) . '">';
	$i     = 0;
	foreach ( $days as $d => $list ) {
		$ts    = strtotime( $d . ' 12:00' );
		$label = $d === $today ? zhdanov_core__( 'Сегодня' ) : ( $d === $tom ? zhdanov_core__( 'Завтра' ) : wp_date( 'D, j M', $ts ) );
		$free  = array_sum( array_map( function ( $x ) {
			return $x['free'] > 0 ? 1 : 0;
		}, $list ) );
		$h    .= '<button type="button" role="tab" class="zh-chip' . ( 0 === $i ? ' is-active' : '' ) . '" aria-selected="' . ( 0 === $i ? 'true' : 'false' ) . '" data-day="' . esc_attr( $d ) . '"' . ( $free ? '' : ' data-full="1"' ) . '>' . esc_html( $label ) . '</button>';
		$i++;
	}
	$h .= '</div>';
	$i  = 0;
	foreach ( $days as $d => $list ) {
		$h .= '<div class="zh-booking__slots" role="tabpanel" data-day="' . esc_attr( $d ) . '"' . ( $i ? ' hidden' : '' ) . '><ul class="zh-booking__ul" role="list">';
		foreach ( $list as $s ) {
			$when = zhdanov_booking_day( $s['ts'] ) . ', ' . $s['time'];
			$meta = array_filter( array( $s['who'], $s['place'], $s['dur'] ) );
			$h   .= '<li class="zh-booking__slot' . ( $s['free'] ? '' : ' is-full' ) . '"><span class="zh-booking__time">' . esc_html( $s['time'] ) . '</span><span class="zh-booking__info"><b>' . esc_html( $s['title'] ) . '</b>' . ( $meta ? '<small>' . esc_html( implode( ' · ', $meta ) ) . '</small>' : '' ) . '</span>';
			if ( $s['free'] ) {
				$left = $s['cap'] > 1 ? '<small class="zh-booking__left">' . esc_html( sprintf( zhdanov_core__( 'свободно %d из %d' ), $s['free'], $s['cap'] ) ) . '</small>' : '';
				$h   .= '<span class="zh-booking__act">' . $left . '<button type="button" class="wp-element-button zh-btn-sm" data-zh-slot="' . esc_attr( wp_json_encode( array( 'class' => $s['id'], 'date' => $s['date'], 'label' => $s['title'] . ', ' . $when ), JSON_UNESCAPED_UNICODE ) ) . '">' . esc_html( zhdanov_core__( 'Записаться' ) ) . '<span class="screen-reader-text">: ' . esc_html( $s['title'] . ', ' . $when ) . '</span></button></span>';
			} else {
				$h .= '<span class="zh-booking__act"><small>' . esc_html( zhdanov_core__( 'мест нет' ) ) . '</small><button type="button" class="zh-btn-ghost zh-btn-sm" data-zh-slot="' . esc_attr( wp_json_encode( array( 'class' => $s['id'], 'date' => $s['date'], 'label' => $s['title'] . ', ' . $when, 'wait' => 1 ), JSON_UNESCAPED_UNICODE ) ) . '">' . esc_html( zhdanov_core__( 'Сообщить, если освободится' ) ) . '<span class="screen-reader-text">: ' . esc_html( $s['title'] . ', ' . $when ) . '</span></button></span>';
			}
			$h .= '</li>';
		}
		$h .= '</ul></div>';
		$i++;
	}
	return $h . '</div>';
}

/* Окно записи (одно на страницу). */
add_action( 'wp_footer', function () {
	if ( ! zhdanov_booking_on() ) {
		return;
	}
	$pp = (int) zhdanov_core_opt( 'privacy_page' );
	?>
	<dialog class="zh-paydlg zh-bookdlg" aria-labelledby="zh-bookdlg-t">
		<form class="zh-form zh-bookform" data-rest="<?php echo esc_url( rest_url( 'zhdanov/v1/book' ) ); ?>" novalidate>
			<h2 id="zh-bookdlg-t" class="zh-form__title"><?php echo esc_html( zhdanov_core__( 'Запись' ) ); ?></h2>
			<p class="zh-paydlg__what"></p>
			<input type="hidden" name="class"><input type="hidden" name="date"><input type="hidden" name="wait" value="">
			<p class="zh-bookdlg__waitnote" hidden><?php echo esc_html( zhdanov_core__( 'Мест сейчас нет. Оставьте почту: если кто-то отменит запись, мы сразу напишем, и вы сможете записаться.' ) ); ?></p>
			<p class="zh-hp" aria-hidden="true"><label>Сайт<input type="text" name="website" tabindex="-1" autocomplete="off"></label></p>
			<p class="zh-field"><label for="zhb-name"><?php echo esc_html( zhdanov_core__( 'Имя' ) ); ?></label><input id="zhb-name" name="name" autocomplete="name" required></p>
			<p class="zh-field"><label for="zhb-phone"><?php echo esc_html( zhdanov_core__( 'Телефон' ) ); ?></label><input id="zhb-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="+7"></p>
			<p class="zh-field"><label for="zhb-email"><?php echo esc_html( zhdanov_core__( 'Почта для напоминания (необязательно)' ) ); ?></label><input id="zhb-email" name="email" type="email" autocomplete="email"></p>
			<p class="zh-field"><label for="zhb-note"><?php echo esc_html( zhdanov_core__( 'Комментарий (необязательно)' ) ); ?></label><input id="zhb-note" name="note"></p>
			<p class="zh-consent"><input id="zhb-ok" name="consent" type="checkbox" value="1" required><label for="zhb-ok"><?php echo esc_html( zhdanov_core__( 'Согласен(на) на обработку персональных данных' ) ); ?><?php echo $pp && 'publish' === get_post_status( $pp ) ? ' (<a href="' . esc_url( get_permalink( $pp ) ) . '" target="_blank">' . esc_html( zhdanov_core__( 'политика' ) ) . '</a>)' : ''; // phpcs:ignore ?></label></p>
			<p class="zh-form__submit"><button type="submit" class="wp-element-button"><?php echo esc_html( zhdanov_core__( 'Записаться' ) ); ?></button> <button type="button" class="zh-linkbtn zh-paydlg__x"><?php echo esc_html( zhdanov_core__( 'Закрыть' ) ); ?></button></p>
			<p class="zh-form__status" role="status" aria-live="polite"></p>
		</form>
	</dialog>
	<?php
}, 31 );


/* ---------- Лист ожидания ---------- */
add_action( 'init', function () {
	register_post_type( 'zh_wait', array( 'labels' => array( 'name' => 'Лист ожидания', 'singular_name' => 'Ожидание', 'menu_name' => 'Лист ожидания' ), 'public' => false, 'show_ui' => zhdanov_booking_on(), 'show_in_menu' => 'zhdanov-core', 'supports' => array( 'title' ), 'capabilities' => array( 'create_posts' => 'do_not_allow' ), 'map_meta_cap' => true ) );
} );
add_action( 'rest_api_init', function () {
	register_rest_route( 'zhdanov/v1', '/wait', array( 'methods' => 'POST', 'callback' => 'zhdanov_wait_create', 'permission_callback' => '__return_true' ) );
} );
function zhdanov_wait_create( $req ) {
	$name  = sanitize_text_field( $req->get_param( 'name' ) );
	$email = sanitize_email( $req->get_param( 'email' ) );
	$phone = sanitize_text_field( $req->get_param( 'phone' ) );
	$cid   = (int) $req->get_param( 'class' );
	$date  = sanitize_text_field( $req->get_param( 'date' ) );
	if ( $req->get_param( 'website' ) || ! zhdanov_booking_on() || ! $name || ! is_email( $email ) || ! $req->get_param( 'consent' ) || 'zh_class' !== get_post_type( $cid ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
		return new WP_Error( 'zh_wait', zhdanov_core__( 'Укажите имя, почту и отметьте согласие.' ), array( 'status' => 400 ) );
	}
	$dup = get_posts( array( 'post_type' => 'zh_wait', 'post_status' => 'private', 'numberposts' => 1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_zh_class', 'value' => (string) $cid ), array( 'key' => '_zh_bdate', 'value' => $date ), array( 'key' => '_zh_email', 'value' => $email ) ) ) );
	if ( ! $dup ) {
		$id = wp_insert_post( array( 'post_type' => 'zh_wait', 'post_status' => 'private', 'post_title' => get_the_title( $cid ) . ' · ' . $date . ' · ' . $name ) );
		foreach ( array( '_zh_class' => (string) $cid, '_zh_bdate' => $date, '_zh_name' => $name, '_zh_email' => $email, '_zh_phone' => $phone ) as $k => $v ) {
			update_post_meta( $id, $k, $v );
		}
	}
	return array( 'ok' => true, 'message' => zhdanov_core__( 'Готово: напишем на почту, как только освободится место.' ) );
}
/* Отменили запись: пишем всем в листе ожидания на этот слот (кто первый, тот записался). */
add_action( 'zhdanov_booking_canceled', function ( $bid ) {
	$cid  = (int) get_post_meta( $bid, '_zh_class', true );
	$date = get_post_meta( $bid, '_zh_bdate', true );
	$ws   = get_posts( array( 'post_type' => 'zh_wait', 'post_status' => 'private', 'numberposts' => 50, 'meta_query' => array( array( 'key' => '_zh_class', 'value' => (string) $cid ), array( 'key' => '_zh_bdate', 'value' => $date ), array( 'key' => '_zh_notified', 'compare' => 'NOT EXISTS' ) ) ) );
	if ( ! $ws ) {
		return;
	}
	$page = get_post_type_archive_link( 'zh_class' );
	$when = zhdanov_booking_day( strtotime( $date . ' 12:00' ) ) . ', ' . zhdanov_core_m( $cid, '_zh_time' );
	foreach ( $ws as $w ) {
		update_post_meta( $w->ID, '_zh_notified', current_time( 'd.m.Y H:i' ) );
		wp_mail( get_post_meta( $w->ID, '_zh_email', true ), zhdanov_core__( 'Освободилось место' ) . ': ' . get_the_title( $cid ) . ', ' . $when, 'Здравствуйте, ' . get_post_meta( $w->ID, '_zh_name', true ) . "!\n\nНа «" . get_the_title( $cid ) . '», ' . $when . " освободилось место. Записаться: " . $page . "\n\nПисьмо получили все, кто ждал: место достанется тому, кто запишется первым.\n\n" . get_bloginfo( 'name' ) );
	}
} );
