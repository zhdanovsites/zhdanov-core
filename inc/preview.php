<?php
/**
 * Ссылка на черновик для клиента (как Public Post Preview): неопубликованную страницу, тур, новость
 * можно показать по секретной ссылке без входа. Ссылка живёт 14 дней, её можно отозвать.
 * Страница по такой ссылке закрыта от поисковиков. После публикации ссылка ведёт на обычный адрес.
 *
 * @package zhdanov-core
 */

defined( 'ABSPATH' ) || exit;

function zhdanov_pp_link( $id, $make = false ) {
	$tok = (array) get_post_meta( $id, '_zh_pp', true );
	if ( ( empty( $tok['k'] ) || $tok['until'] < time() ) && $make ) {
		$tok = array( 'k' => wp_generate_password( 16, false ), 'until' => time() + 14 * DAY_IN_SECONDS );
		update_post_meta( $id, '_zh_pp', $tok );
	}
	if ( empty( $tok['k'] ) || $tok['until'] < time() ) {
		return '';
	}
	return add_query_arg( 'zh_pp', $id . '.' . $tok['k'], home_url( '/' ) );
}

function zhdanov_pp_ok( $raw ) {
	if ( ! preg_match( '/^(\d+)\.([A-Za-z0-9]{16})$/', (string) $raw, $m ) ) {
		return 0;
	}
	$tok = (array) get_post_meta( (int) $m[1], '_zh_pp', true );
	return ! empty( $tok['k'] ) && $tok['until'] >= time() && hash_equals( $tok['k'], $m[2] ) ? (int) $m[1] : 0;
}

/* Показ черновика по ссылке */
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() || empty( $_GET['zh_pp'] ) ) { // phpcs:ignore
		return;
	}
	$id = zhdanov_pp_ok( sanitize_text_field( wp_unslash( $_GET['zh_pp'] ) ) ); // phpcs:ignore
	if ( ! $id ) {
		return;
	}
	$p = get_post( $id );
	if ( ! $p ) {
		return;
	}
	if ( 'publish' === $p->post_status ) {
		wp_safe_redirect( get_permalink( $p ), 302 );
		exit;
	}
	$q->init();
	$q->set( 'p', $id );
	$q->set( 'post_type', $p->post_type );
	$q->set( 'post_status', array( 'draft', 'pending', 'future', 'private' ) );
	$q->is_home     = false;
	$q->is_singular = true;
	$q->is_single   = 'page' !== $p->post_type;
	$q->is_page     = 'page' === $p->post_type;
	$GLOBALS['zh_pp_id'] = $id;
	add_filter( 'posts_results', function ( $posts ) use ( $id ) {
		foreach ( $posts as $x ) {
			if ( (int) $x->ID === $id ) {
				$x->post_status = 'publish'; // только для показа в этом запросе
			}
		}
		return $posts;
	} );
	add_filter( 'wp_robots', 'wp_robots_no_robots' );
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow' );
} );

/* Плашка «Черновик» поверх страницы, чтобы клиент понимал, что видит */
add_action( 'wp_footer', function () {
	if ( empty( $GLOBALS['zh_pp_id'] ) ) {
		return;
	}
	echo '<div style="position:fixed;top:0;left:50%;transform:translateX(-50%);z-index:2147483000;background:#dba617;color:#1d2327;font:600 13px/1.4 -apple-system,Segoe UI,Roboto,sans-serif;padding:6px 14px;border-radius:0 0 8px 8px">Черновик: страница ещё не опубликована</div>';
} );

/* Кнопка в списке записей и в редакторе */
add_filter( 'post_row_actions', 'zhdanov_pp_row', 10, 2 );
add_filter( 'page_row_actions', 'zhdanov_pp_row', 10, 2 );
function zhdanov_pp_row( $a, $p ) {
	if ( in_array( $p->post_status, array( 'draft', 'pending', 'future' ), true ) && current_user_can( 'edit_post', $p->ID ) && 'zh_lead' !== $p->post_type ) {
		$a['zh_pp'] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=zhdanov_pp&id=' . $p->ID ), 'zhdanov_pp_' . $p->ID ) ) . '">Ссылка для клиента</a>';
	}
	return $a;
}

add_action( 'admin_post_zhdanov_pp', function () {
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
		wp_die( 'Нет прав.', 403 );
	}
	check_admin_referer( 'zhdanov_pp_' . $id );
	if ( ! empty( $_GET['off'] ) ) {
		delete_post_meta( $id, '_zh_pp' );
		$msg = 'Ссылка для клиента отозвана.';
	} else {
		$l   = zhdanov_pp_link( $id, true );
		$msg = 'Ссылка для клиента (14 дней, без входа): ' . $l;
		if ( function_exists( 'zhdanov_log' ) ) {
			zhdanov_log( 'Ссылка на черновик для клиента', get_the_title( $id ) );
		}
	}
	set_transient( 'zh_pp_msg_' . get_current_user_id(), $msg, 300 );
	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'post.php?post=' . $id . '&action=edit' ) );
	exit;
} );

add_action( 'admin_notices', function () {
	$m = get_transient( 'zh_pp_msg_' . get_current_user_id() );
	if ( ! $m ) {
		return;
	}
	delete_transient( 'zh_pp_msg_' . get_current_user_id() );
	$link = preg_match( '~https?://\S+~', $m, $x ) ? $x[0] : '';
	echo '<div class="notice notice-success is-dismissible" id="zh-pp-msg"><p>' . esc_html( $link ? str_replace( $link, '', $m ) : $m ) . ( $link ? '<input type="text" readonly class="large-text code" value="' . esc_attr( $link ) . '" onclick="this.select()" style="margin-top:6px">' : '' ) . '</p></div>';
} );

/* Блок в редакторе (боковая панель «Ссылка для клиента») */
add_action( 'add_meta_boxes', function ( $type, $post ) {
	if ( ! $post || 'zh_lead' === $type || ! post_type_supports( $type, 'editor' ) || ! is_post_type_viewable( $type ) ) {
		return;
	}
	add_meta_box( 'zh_pp', 'Ссылка для клиента', function ( $p ) {
		if ( 'publish' === $p->post_status ) {
			echo '<p>Страница опубликована, её видят все.</p>';
			return;
		}
		$l = zhdanov_pp_link( $p->ID );
		if ( $l ) {
			echo '<p>Клиент видит черновик без входа (до ' . esc_html( wp_date( 'd.m', ( (array) get_post_meta( $p->ID, '_zh_pp', true ) )['until'] ) ) . '):</p><input type="text" readonly class="widefat code" value="' . esc_attr( $l ) . '" onclick="this.select()">';
			echo '<p><a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=zhdanov_pp&off=1&id=' . $p->ID ), 'zhdanov_pp_' . $p->ID ) ) . '">Отозвать ссылку</a></p>';
		} else {
			echo '<p>Показать черновик клиенту до публикации, без входа в админку.</p><p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=zhdanov_pp&id=' . $p->ID ), 'zhdanov_pp_' . $p->ID ) ) . '">Сделать ссылку</a></p><p style="color:#646970">Сначала сохраните черновик.</p>';
		}
	}, $type, 'side', 'low' );
}, 10, 2 );
