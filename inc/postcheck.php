<?php
/**
 * Проверка сайта после обновления (как WP Umbrella «safe updates»): после любого обновления
 * WordPress, плагина или темы через минуту открываем главную и до 5 главных страниц глазами
 * посетителя и сравниваем с прошлым разом. Сломалось (ошибка 500, «критическая ошибка»,
 * пропал заголовок, страница сильно похудела): сообщение в Telegram студии.
 *
 * @package zhdanov-core
 */

defined( 'ABSPATH' ) || exit;

/** Какие страницы проверяем: главная + страницы из меню (до 5). */
function zhdanov_pc_urls() {
	$urls = array( home_url( '/' ) );
	$ids  = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 20, 'fields' => 'ids', 'orderby' => 'menu_order', 'order' => 'ASC' ) );
	foreach ( $ids as $id ) {
		$u = get_permalink( $id );
		if ( $u && ! in_array( $u, $urls, true ) && (int) get_option( 'page_on_front' ) !== $id ) {
			$urls[] = $u;
		}
		if ( count( $urls ) >= 6 ) {
			break;
		}
	}
	return (array) apply_filters( 'zhdanov_postcheck_urls', $urls );
}

/** Снимок страницы: код ответа, размер, заголовок, есть ли h1, признаки ошибки. */
function zhdanov_pc_snap( $url ) {
	$r = wp_remote_get( add_query_arg( 'zh_pc', time(), $url ), array( 'timeout' => 20, 'sslverify' => false, 'user-agent' => 'ZhdanovSiteCheck/1.0', 'limit_response_size' => 3 * MB_IN_BYTES ) );
	if ( is_wp_error( $r ) ) {
		return array( 'code' => 0, 'err' => $r->get_error_message() );
	}
	$b = (string) wp_remote_retrieve_body( $r );
	preg_match( '#<title[^>]*>(.*?)</title>#is', $b, $t );
	return array(
		'code'  => (int) wp_remote_retrieve_response_code( $r ),
		'len'   => strlen( wp_strip_all_tags( preg_replace( '#<(script|style)[^>]*>.*?</\1>#is', '', $b ) ) ),
		'title' => isset( $t[1] ) ? trim( html_entity_decode( wp_strip_all_tags( $t[1] ), ENT_QUOTES, 'UTF-8' ) ) : '',
		'h1'    => (bool) preg_match( '#<h1[\s>]#i', $b ),
		'fatal' => (bool) preg_match( '/critical error|критическая ошибка|Fatal error|Parse error|Ошибка базы данных|Error establishing a database/iu', $b ),
	);
}

/** Сравнить с прошлым. Возвращает [проблемы, новый эталон]. */
function zhdanov_pc_run( $why = '' ) {
	$base = (array) get_option( 'zh_pc_base', array() );
	$new  = array();
	$bad  = array();
	foreach ( zhdanov_pc_urls() as $u ) {
		$s    = zhdanov_pc_snap( $u );
		$path = wp_make_link_relative( $u );
		$b    = $base[ $u ] ?? null;
		$p    = array();
		if ( ! $s['code'] ) {
			$p[] = 'не открылась (' . $s['err'] . ')';
		} elseif ( $s['code'] >= 500 ) {
			$p[] = 'ошибка ' . $s['code'];
		} elseif ( $s['code'] >= 400 && ( ! $b || $b['code'] < 400 ) ) {
			$p[] = 'ответ ' . $s['code'];
		}
		if ( ! empty( $s['fatal'] ) ) {
			$p[] = 'на странице «критическая ошибка»';
		}
		if ( $b && $s['code'] && $s['code'] < 400 ) {
			if ( $b['h1'] && ! $s['h1'] ) {
				$p[] = 'пропал главный заголовок (h1)';
			}
			if ( $b['len'] > 800 && $s['len'] < $b['len'] * 0.5 ) {
				$k   = $b['len'] / max( 1, $s['len'] );
				$p[] = $k >= 10 ? 'страница почти пустая' : 'текста стало в ' . round( $k, 1 ) . ' раза меньше';
			}
			if ( $b['title'] && '' === $s['title'] ) {
				$p[] = 'пропал title';
			}
		}
		if ( $p ) {
			$bad[ $path ] = $p;
			$new[ $u ]    = $b ? $b : $s; // эталон не портим сломанной страницей
		} else {
			$new[ $u ] = $s;
		}
	}
	update_option( 'zh_pc_base', $new, false );
	update_option( 'zh_pc_last', array( 'time' => time(), 'why' => $why, 'bad' => $bad, 'n' => count( $new ) ), false );
	if ( $bad ) {
		$lines = array();
		foreach ( $bad as $path => $p ) {
			$lines[] = $path . ': ' . implode( ', ', $p );
		}
		$token = trim( (string) zhdanov_core_opt( 'studio_tg_token' ) );
		$chat  = trim( (string) zhdanov_core_opt( 'studio_tg_chat' ) );
		if ( $token && $chat && function_exists( 'zhdanov_core_tg_send' ) ) {
			zhdanov_core_tg_send( $token, $chat, '⚠️ ' . wp_parse_url( home_url(), PHP_URL_HOST ) . ( $why ? ' после обновления (' . $why . ')' : '' ) . ":\n" . implode( "\n", $lines ) . "\nЖурнал: " . admin_url( 'admin.php?page=zhdanov-activity' ) );
		}
		if ( function_exists( 'zhdanov_log' ) ) {
			zhdanov_log( 'Проверка после обновления: ' . implode( '; ', $lines ) );
		}
	}
	return $bad;
}

/* После любого обновления: проверка через минуту (в фоне) */
add_action( 'upgrader_process_complete', function ( $up, $extra ) {
	if ( empty( $extra['action'] ) || ! in_array( $extra['action'], array( 'update', 'install' ), true ) ) {
		return;
	}
	$what = $extra['type'] ?? '';
	$name = '';
	if ( 'plugin' === $what ) {
		$p    = (array) ( $extra['plugins'] ?? ( isset( $extra['plugin'] ) ? array( $extra['plugin'] ) : array() ) );
		$name = 'плагин ' . implode( ', ', array_map( function ( $x ) { return dirname( $x ); }, $p ) );
	} elseif ( 'theme' === $what ) {
		$name = 'тема ' . implode( ', ', (array) ( $extra['themes'] ?? ( isset( $extra['theme'] ) ? array( $extra['theme'] ) : array() ) ) );
	} elseif ( 'core' === $what ) {
		$name = 'WordPress';
	}
	if ( '' === trim( str_replace( array( 'плагин', 'тема' ), '', $name ) ) ) {
		$name = 'install' === $extra['action'] ? 'установка ' . $what : 'обновление';
	}
	wp_clear_scheduled_hook( 'zhdanov_postcheck', array( $name ) );
	wp_schedule_single_event( time() + 60, 'zhdanov_postcheck', array( $name ) );
}, 20, 2 );

add_action( 'zhdanov_postcheck', function ( $why = '' ) {
	zhdanov_pc_run( (string) $why );
} );

/* Эталон раз в день, чтобы сравнивать со свежим видом сайта */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'zhdanov_postcheck_daily' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'zhdanov_postcheck_daily' );
	}
} );
add_action( 'zhdanov_postcheck_daily', function () {
	zhdanov_pc_run( '' );
} );

/* Блок на странице «Обновить по ссылке» */
add_action( 'zhdanov_su_page_after', function () {
	$l = get_option( 'zh_pc_last' );
	echo '<h2 style="margin-top:28px">Проверка после обновления</h2><p>Через минуту после любого обновления (WordPress, плагины, тема) плагин открывает главную и до 5 страниц и сравнивает с прошлым разом. Если что-то сломалось, студии сразу приходит сообщение в Telegram.</p>';
	if ( is_array( $l ) ) {
		echo '<p>Последняя проверка: ' . esc_html( wp_date( 'd.m.Y H:i', $l['time'] ) . ( $l['why'] ? ' (после: ' . $l['why'] . ')' : '' ) ) . ' · ' . ( $l['bad'] ? '<b style="color:#b32d2e">проблемы: ' . esc_html( implode( '; ', array_map( function ( $k, $v ) { return $k . ' ' . implode( ', ', $v ); }, array_keys( $l['bad'] ), $l['bad'] ) ) ) . '</b>' : '<span style="color:#007017">✓ ' . (int) $l['n'] . ' страниц в порядке</span>' ) . '</p>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'zhdanov_postcheck' );
	echo '<input type="hidden" name="action" value="zhdanov_postcheck_now"><button class="button" id="zh-pc-now">Проверить страницы сейчас</button></form>';
}, 20 );

add_action( 'admin_post_zhdanov_postcheck_now', function () {
	if ( ! current_user_can( 'update_plugins' ) ) {
		wp_die( 'Нет прав.', 403 );
	}
	check_admin_referer( 'zhdanov_postcheck' );
	$bad = zhdanov_pc_run( 'проверка вручную' );
	set_transient( 'zh_su_msg_' . get_current_user_id(), $bad ? array( 'err', 'Есть проблемы: ' . implode( '; ', array_keys( $bad ) ) ) : array( 'ok', 'Страницы в порядке.' ), 300 );
	wp_safe_redirect( admin_url( 'admin.php?page=zhdanov-selfupdate' ) );
	exit;
} );
