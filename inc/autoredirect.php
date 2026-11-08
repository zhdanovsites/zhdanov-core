<?php
/**
 * Автопереадресация при смене адреса: у опубликованной страницы, тура, новости поменяли адрес (или адрес
 * родительской страницы) → в «Переезд» сама добавляется строка «старый адрес → новый» (301), для вложенных
 * страниц тоже. Старые ссылки из поиска и мессенджеров не ломаются. Цепочки сворачиваются: прежние
 * перенаправления на старый адрес начинают вести сразу на новый.
 *
 * @package zhdanov-core
 */

defined( 'ABSPATH' ) || exit;

$GLOBALS['zh_ar_old'] = array();

function zhdanov_ar_path( $url ) {
	$p = (string) wp_parse_url( $url, PHP_URL_PATH );
	return '/' . trim( $p, '/' ) . ( '' === trim( $p, '/' ) ? '' : '/' );
}

function zhdanov_ar_watch( $post ) {
	$o = get_post_type_object( $post->post_type );
	return 'publish' === $post->post_status && $o && $o->public && ! in_array( $post->post_type, array( 'attachment', 'zh_lead', 'zh_csnap' ), true );
}

// запоминаем адреса до сохранения: саму запись и вложенные страницы
add_action( 'pre_post_update', function ( $id ) {
	$p = get_post( $id );
	if ( ! $p || ! zhdanov_ar_watch( $p ) ) {
		return;
	}
	$list = array( $id => zhdanov_ar_path( get_permalink( $id ) ) );
	if ( is_post_type_hierarchical( $p->post_type ) ) {
		foreach ( get_pages( array( 'child_of' => $id, 'post_type' => $p->post_type, 'post_status' => 'publish' ) ) as $c ) {
			$list[ $c->ID ] = zhdanov_ar_path( get_permalink( $c ) );
		}
	}
	$GLOBALS['zh_ar_old'][ $id ] = $list;
} );

add_action( 'post_updated', function ( $id, $after ) {
	if ( empty( $GLOBALS['zh_ar_old'][ $id ] ) || ! zhdanov_ar_watch( $after ) ) {
		return;
	}
	clean_post_cache( $id );
	$pairs = array();
	foreach ( $GLOBALS['zh_ar_old'][ $id ] as $pid => $old ) {
		$new = zhdanov_ar_path( get_permalink( $pid ) );
		if ( $old !== $new && '/' !== $old ) {
			$pairs[ $old ] = $new;
		}
	}
	unset( $GLOBALS['zh_ar_old'][ $id ] );
	if ( $pairs ) {
		zhdanov_ar_add( $pairs, get_the_title( $id ) );
	}
}, 20, 2 );

/** Добавить перенаправления [ старый => новый ] в «Переезд», свернуть цепочки и убрать петли. */
function zhdanov_ar_add( $pairs, $title ) {
	$lines = preg_split( '/\r?\n/', (string) get_option( 'zhdanov_redirects', '' ) );
	$out   = array();
	foreach ( $lines as $l ) {
		$t = trim( $l );
		if ( '' !== $t && '#' !== $t[0] ) {
			$parts = preg_split( '/\s+/', $t );
			if ( count( $parts ) >= 2 ) {
				$from = $parts[0];
				$to   = $parts[1];
				if ( isset( $pairs[ rtrim( $to, '/' ) . '/' ] ) ) { // цепочка: старое → старый адрес → теперь сразу на новый
					$to = $pairs[ rtrim( $to, '/' ) . '/' ];
				}
				if ( in_array( rtrim( $from, '/' ) . '/', array_values( $pairs ), true ) ) { // новый адрес не должен никуда уводить
					continue;
				}
				if ( rtrim( $from, '/' ) === rtrim( $to, '/' ) ) {
					continue;
				}
				$l = $from . ' ' . $to;
			}
		}
		$out[] = $l;
	}
	$out[] = '# ' . wp_date( 'd.m.Y' ) . ': сменился адрес «' . wp_strip_all_tags( $title ) . '»';
	foreach ( $pairs as $old => $new ) {
		$out[] = $old . ' ' . $new;
	}
	update_option( 'zhdanov_redirects', trim( implode( "\n", $out ) ), false );
	if ( function_exists( 'zhdanov_log' ) ) {
		zhdanov_log( 'переадресация добавлена', implode( ', ', array_map( function ( $o, $n ) {
			return $o . ' → ' . $n;
		}, array_keys( $pairs ), $pairs ) ) );
	}
}
