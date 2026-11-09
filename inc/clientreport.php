<?php
/**
 * Отчёт клиенту раз в месяц (как ManageWP / WP Umbrella client reports): простым языком,
 * что сделано на сайте за месяц, чтобы клиент видел пользу сопровождения.
 * 1-го числа: письмо клиенту (и/или в его Telegram-бот заявок), копия студии в Telegram.
 *
 * @package zhdanov-core
 */

defined( 'ABSPATH' ) || exit;

/** Счётчики месяца: zhdanov_stat( 'checks' ). */
function zhdanov_stat( $key, $n = 1 ) {
	$s = (array) get_option( 'zh_month_stats', array() );
	$m = wp_date( 'Y-m' );
	if ( ! isset( $s[ $m ] ) ) {
		$s[ $m ] = array();
	}
	$s[ $m ][ $key ] = ( $s[ $m ][ $key ] ?? 0 ) + $n;
	if ( count( $s ) > 14 ) {
		$s = array_slice( $s, -14, null, true );
	}
	update_option( 'zh_month_stats', $s, false );
}

add_action( 'upgrader_process_complete', function ( $up, $extra ) {
	if ( empty( $extra['action'] ) || 'update' !== $extra['action'] ) {
		return;
	}
	$n = 1;
	if ( ! empty( $extra['plugins'] ) ) {
		$n = count( (array) $extra['plugins'] );
	} elseif ( ! empty( $extra['themes'] ) ) {
		$n = count( (array) $extra['themes'] );
	}
	zhdanov_stat( 'updates', $n );
}, 30, 2 );
add_action( 'update_option_zhdanov_backup_status', function () {
	zhdanov_stat( 'backups' );
} );
add_action( 'add_option_zhdanov_backup_status', function () {
	zhdanov_stat( 'backups' );
} );

/** Месяц для отчёта: прошлый (1-го числа отчёт за прошедший месяц). */
function zhdanov_cr_period( $month = '' ) {
	$m     = $month ? $month : wp_date( 'Y-m', strtotime( 'first day of last month', current_time( 'timestamp' ) ) );
	$tz    = wp_timezone();
	$start = new DateTime( $m . '-01 00:00:00', $tz );
	$end   = ( clone $start )->modify( 'first day of next month' );
	return array( $m, $start->getTimestamp(), $end->getTimestamp() );
}

function zhdanov_cr_month_name( $m ) {
	$names = array( 'январь', 'февраль', 'март', 'апрель', 'май', 'июнь', 'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь' );
	return $names[ (int) substr( $m, 5, 2 ) - 1 ] . ' ' . substr( $m, 0, 4 );
}

function zhdanov_cr_count_posts( $types, $from, $to ) {
	return count( get_posts( array(
		'post_type'   => $types,
		'post_status' => 'publish',
		'numberposts' => 1000,
		'fields'      => 'ids',
		'date_query'  => array( array( 'after' => wp_date( 'Y-m-d H:i:s', $from ), 'before' => wp_date( 'Y-m-d H:i:s', $to ), 'inclusive' => true ) ),
	) ) );
}

/** Текст отчёта клиенту. */
function zhdanov_cr_text( $month = '' ) {
	list( $m, $from, $to ) = zhdanov_cr_period( $month );
	$prev                  = zhdanov_cr_period( wp_date( 'Y-m', $from - DAY_IN_SECONDS ) );
	$st                    = (array) get_option( 'zh_month_stats', array() );
	$s                     = $st[ $m ] ?? array();
	$name                  = get_bloginfo( 'name' );
	$host                  = wp_parse_url( home_url(), PHP_URL_HOST );
	$lines                 = array();

	$leads  = zhdanov_cr_count_posts( 'zh_lead', $from, $to );
	$leads0 = zhdanov_cr_count_posts( 'zh_lead', $prev[1], $prev[2] );
	$diff   = $leads0 ? ( $leads >= $leads0 ? ' (в прошлом месяце ' . $leads0 . ')' : ' (в прошлом месяце ' . $leads0 . ')' ) : '';
	$lines[] = '📩 Заявок с сайта: ' . $leads . $diff;
	if ( ! empty( $s['leads_done'] ) ) {
		$lines[] = '☑️ Заявок отмечено «Готово»: ' . (int) $s['leads_done'];
	}

	$types = array_values( array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) ) );
	$new   = zhdanov_cr_count_posts( $types, $from, $to );
	if ( $new ) {
		$lines[] = '📝 Новых страниц и записей опубликовано: ' . $new;
	}
	if ( ! empty( $s['indexnow'] ) ) {
		$lines[] = '🔎 Сообщили Яндексу о новых и изменённых страницах: ' . (int) $s['indexnow'];
	}
	$checks = (int) ( $s['checks'] ?? 0 );
	if ( $checks ) {
		$lines[] = '✅ Проверок сайта: ' . $checks . ( ! empty( $s['check_bad'] ) ? ', найдено и разобрано проблем: ' . (int) $s['check_bad'] : ', всё работало' );
	}
	if ( ! empty( $s['updates'] ) ) {
		$lines[] = '🔄 Обновлений безопасности и функций поставлено: ' . (int) $s['updates'];
	}
	$bk = 0;
	if ( function_exists( 'zhdanov_backup_dir' ) ) {
		foreach ( (array) glob( zhdanov_backup_dir() . '/recept-*.zip' ) as $f ) {
			$t   = filemtime( $f );
			$bk += ( $t >= $from && $t < $to ) ? 1 : 0;
		}
	}
	$bk = max( $bk, (int) ( $s['backups'] ?? 0 ) );
	if ( $bk ) {
		$lines[] = '💾 Резервных копий сделано: ' . $bk;
	}
	if ( ! empty( $s['notes_done'] ) ) {
		$lines[] = '✏️ Ваших правок выполнено: ' . (int) $s['notes_done'];
	}
	$ssl = function_exists( 'zhdanov_report_ssl_days' ) ? zhdanov_report_ssl_days() : null;
	if ( is_int( $ssl ) && $ssl > 0 ) {
		$lines[] = '🔒 Сертификат безопасности (https) действует ещё ' . $ssl . ' дн.';
	}
	$sup = trim( (string) zhdanov_core_opt( 'support_contact' ) );
	return 'Отчёт о сайте ' . $name . ' (' . $host . ') за ' . zhdanov_cr_month_name( $m ) . "\n\n" . implode( "\n", $lines ) . "\n\n" . 'Сайт на сопровождении Zhdanov Sites.' . ( $sup ? ' Вопросы и правки: ' . $sup . '.' : '' );
}

/** Отправить. Возвращает массив статусов. */
function zhdanov_cr_send( $month = '' ) {
	$text = zhdanov_cr_text( $month );
	$res  = array();
	$to   = trim( (string) zhdanov_core_opt( 'client_report_email' ) );
	if ( $to && is_email( $to ) ) {
		$ok    = wp_mail( $to, strtok( $text, "\n" ), $text );
		$res[] = 'почта ' . $to . ': ' . ( $ok ? 'отправлено' : 'не ушло (причина в Здоровье сайта → «Письма с сайта уходят»)' );
	}
	$o = (array) get_option( 'zhdanov_core', array() );
	if ( ! empty( $o['client_report_tg'] ) && ! empty( $o['tg_token'] ) && ! empty( $o['tg_chat'] ) ) {
		$r     = zhdanov_core_tg_send( $o['tg_token'], $o['tg_chat'], $text );
		$j     = is_wp_error( $r ) ? null : json_decode( wp_remote_retrieve_body( $r ), true );
		$res[] = 'Telegram клиента: ' . ( ! empty( $j['ok'] ) ? 'отправлено' : 'не ушло' );
	}
	if ( ! empty( $o['studio_tg_token'] ) && ! empty( $o['studio_tg_chat'] ) ) {
		zhdanov_core_tg_send( $o['studio_tg_token'], $o['studio_tg_chat'], '📊 Клиенту ' . ( $res ? '(' . implode( '; ', $res ) . ')' : '(не отправлен: нет почты клиента и Telegram)' ) . ":\n\n" . $text );
	}
	update_option( 'zh_cr_last', array( 'time' => time(), 'res' => $res ), false );
	if ( function_exists( 'zhdanov_log' ) ) {
		zhdanov_log( 'Отчёт клиенту: ' . ( $res ? implode( '; ', $res ) : 'некуда отправить' ) );
	}
	return $res;
}

/* Раз в месяц: 1-го числа в 10:00 по времени сайта */
add_action( 'init', function () {
	$on = (bool) zhdanov_core_opt( 'client_report' );
	if ( $on && ! wp_next_scheduled( 'zhdanov_client_report' ) ) {
		$next = new DateTime( 'first day of next month 10:00', wp_timezone() );
		wp_schedule_single_event( $next->getTimestamp(), 'zhdanov_client_report' );
	} elseif ( ! $on && wp_next_scheduled( 'zhdanov_client_report' ) ) {
		wp_clear_scheduled_hook( 'zhdanov_client_report' );
	}
} );
add_action( 'zhdanov_client_report', function () {
	zhdanov_cr_send();
	$next = new DateTime( 'first day of next month 10:00', wp_timezone() );
	wp_schedule_single_event( $next->getTimestamp(), 'zhdanov_client_report' );
} );

/* Страница: предпросмотр и «Отправить сейчас» */
add_action( 'admin_menu', function () {
	add_submenu_page( 'zhdanov-core', 'Отчёт клиенту', 'Отчёт клиенту', 'manage_options', 'zhdanov-creport', 'zhdanov_cr_page' );
}, 28 );

add_action( 'admin_post_zhdanov_creport', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Нет прав.', 403 );
	}
	check_admin_referer( 'zhdanov_creport' );
	$m   = isset( $_POST['zh_m'] ) && preg_match( '/^\d{4}-\d{2}$/', wp_unslash( $_POST['zh_m'] ) ) ? sanitize_text_field( wp_unslash( $_POST['zh_m'] ) ) : '';
	$res = zhdanov_cr_send( $m );
	set_transient( 'zh_cr_msg_' . get_current_user_id(), $res ? implode( '; ', $res ) : 'Некуда отправить: укажите почту клиента или включите отчёт в Telegram клиента (Жданов → «Админка для клиента»). Копия ушла студии.', 120 );
	wp_safe_redirect( admin_url( 'admin.php?page=zhdanov-creport' . ( $m ? '&m=' . $m : '' ) ) );
	exit;
} );

function zhdanov_cr_page() {
	$m    = isset( $_GET['m'] ) && preg_match( '/^\d{4}-\d{2}$/', wp_unslash( $_GET['m'] ) ) ? sanitize_text_field( wp_unslash( $_GET['m'] ) ) : ''; // phpcs:ignore
	$msg  = get_transient( 'zh_cr_msg_' . get_current_user_id() );
	$last = get_option( 'zh_cr_last' );
	$next = wp_next_scheduled( 'zhdanov_client_report' );
	delete_transient( 'zh_cr_msg_' . get_current_user_id() );
	list( $cur ) = zhdanov_cr_period( $m );
	echo '<div class="wrap"><h1>Отчёт клиенту</h1>';
	if ( $msg ) {
		echo '<div class="notice notice-info" id="zh-cr-msg"><p>' . esc_html( $msg ) . '</p></div>';
	}
	echo '<p>Раз в месяц (1-го числа в 10:00) клиент получает короткий отчёт простым языком: заявки, что опубликовано, проверки, обновления, копии. Так видно, за что он платит за сопровождение. Копия всегда приходит студии в Telegram.</p>';
	echo '<p>Куда: ' . esc_html( zhdanov_core_opt( 'client_report_email' ) ? zhdanov_core_opt( 'client_report_email' ) : 'почта клиента не указана' ) . ( zhdanov_core_opt( 'client_report_tg' ) ? ' + Telegram клиента' : '' ) . ' · ' . ( $next ? 'следующий: ' . esc_html( wp_date( 'd.m.Y H:i', $next ) ) : '<b>выключен</b>' ) . ' · <a href="' . esc_url( admin_url( 'admin.php?page=zhdanov-core#client' ) ) . '">настройки</a></p>';
	if ( is_array( $last ) ) {
		echo '<p>Последний: ' . esc_html( wp_date( 'd.m.Y H:i', $last['time'] ) . ( $last['res'] ? ' · ' . implode( '; ', $last['res'] ) : '' ) ) . '</p>';
	}
	echo '<form method="get"><input type="hidden" name="page" value="zhdanov-creport"><label>Месяц: <input type="month" name="m" value="' . esc_attr( $cur ) . '"></label> <button class="button">Показать</button></form>';
	echo '<pre id="zh-cr-text" style="white-space:pre-wrap;background:#fff;border:1px solid #dcdcde;padding:14px 16px;max-width:760px;font:14px/1.6 -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif">' . esc_html( zhdanov_cr_text( $m ) ) . '</pre>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'zhdanov_creport' );
	echo '<input type="hidden" name="action" value="zhdanov_creport"><input type="hidden" name="zh_m" value="' . esc_attr( $cur ) . '">';
	submit_button( 'Отправить этот отчёт сейчас', 'primary', 'zh_cr_send', false );
	echo '</form></div>';
}
