<?php
/**
 * IndexNow: при публикации, правке или снятии страницы сайт сразу сообщает её адрес Яндексу
 * (Яндекс передаёт и Bing, Seznam, Naver). Новые туры, новости, события попадают в поиск за часы, а не недели.
 * Ключ подтверждения создаётся сам и отдаётся по адресу /<ключ>.txt. Google протокол не поддерживает.
 *
 * @package zhdanov-core
 */

defined( 'ABSPATH' ) || exit;

function zhdanov_indexnow_key() {
	$k = (string) get_option( 'zh_indexnow_key' );
	if ( ! preg_match( '/^[a-f0-9]{32}$/', $k ) ) {
		$k = md5( wp_generate_password( 40, true, true ) . home_url() );
		update_option( 'zh_indexnow_key', $k, false );
	}
	return $k;
}

/** Можно ли сообщать поисковикам: включено, сайт открыт для поиска, не заглушка. */
function zhdanov_indexnow_on() {
	return zhdanov_core_opt( 'indexnow_on' ) && get_option( 'blog_public' ) && ! zhdanov_core_opt( 'maint_on' ) && 0 === strpos( home_url(), 'http' ) && false === strpos( home_url(), 'localhost' ) && false === strpos( home_url(), '127.0.0.1' );
}

// файл ключа: /<ключ>.txt
add_action( 'init', function () {
	$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : ''; // phpcs:ignore
	$home = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	if ( $path && $home . '/' . zhdanov_indexnow_key() . '.txt' === $path ) {
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo zhdanov_indexnow_key(); // phpcs:ignore
		exit;
	}
}, 0 );

$GLOBALS['zh_indexnow_q'] = array();

function zhdanov_indexnow_queue( $url ) {
	if ( ! $url || ! zhdanov_indexnow_on() ) {
		return;
	}
	$t = 'zh_inow_' . md5( $url );
	if ( get_transient( $t ) ) { // один адрес не чаще раза в 10 минут (просьба Яндекса)
		return;
	}
	set_transient( $t, 1, 10 * MINUTE_IN_SECONDS );
	$GLOBALS['zh_indexnow_q'][ $url ] = 1;
}

function zhdanov_indexnow_public( $post ) {
	$post = get_post( $post );
	if ( ! $post || wp_is_post_revision( $post ) || in_array( $post->post_type, array( 'zh_lead', 'zh_csnap', 'wp_block', 'wp_navigation', 'wp_template', 'wp_template_part', 'wp_global_styles' ), true ) ) {
		return false;
	}
	$o = get_post_type_object( $post->post_type );
	return $o && $o->public && ! post_password_required( $post );
}

add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( ( 'publish' === $new || 'publish' === $old ) && zhdanov_indexnow_public( $post ) ) {
		zhdanov_indexnow_queue( get_permalink( $post ) ); // и новая, и снятая страница: поисковик обновит или уберёт
	}
}, 10, 3 );
add_action( 'post_updated', function ( $id, $after ) {
	if ( 'publish' === $after->post_status && zhdanov_indexnow_public( $after ) ) {
		zhdanov_indexnow_queue( get_permalink( $id ) );
	}
}, 10, 2 );

add_action( 'shutdown', function () {
	$urls = array_keys( $GLOBALS['zh_indexnow_q'] );
	if ( ! $urls ) {
		return;
	}
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	$r    = wp_remote_post( 'https://yandex.com/indexnow', array(
		'timeout' => 5,
		'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
		'body'    => wp_json_encode( array( 'host' => $host, 'key' => zhdanov_indexnow_key(), 'keyLocation' => home_url( '/' . zhdanov_indexnow_key() . '.txt' ), 'urlList' => array_slice( $urls, 0, 1000 ) ) ),
	) );
	$code = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
	update_option( 'zh_indexnow_last', array( 'time' => time(), 'n' => count( $urls ), 'code' => $code, 'err' => is_wp_error( $r ) ? $r->get_error_message() : ( $code >= 400 ? mb_substr( wp_remote_retrieve_body( $r ), 0, 160 ) : '' ) ), false );
}, 5 );

/** Строка для «Здоровья сайта». */
function zhdanov_indexnow_status() {
	$l = get_option( 'zh_indexnow_last' );
	if ( ! $l ) {
		return 'Пока ничего не отправлялось: отправка начнётся при следующей публикации или правке страницы. Файл ключа: ' . home_url( '/' . zhdanov_indexnow_key() . '.txt' );
	}
	$ok = in_array( (int) $l['code'], array( 200, 202 ), true );
	return 'Последняя отправка ' . wp_date( 'd.m H:i', $l['time'] ) . ': адресов ' . (int) $l['n'] . ', ' . ( $ok ? 'Яндекс принял ✅' : 'ошибка ' . ( $l['code'] ? $l['code'] : '' ) . ' ' . $l['err'] );
}
