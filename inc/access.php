<?php
/**
 * Доступ к закрытым урокам курса: после оплаты (ссылка в письме ставит отметку в браузере) или по коду курса.
 * Отметка в cookie подписана ключом сайта: подделать нельзя, при смене кода курса старые коды перестают работать.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zhdanov_access_sig( $cid, $who ) {
	return wp_hash( 'zh_acc_' . $cid . '_' . $who . '_' . zhdanov_core_m( $cid, '_zh_access' ) );
}

function zhdanov_access_has( $cid ) {
	$c = isset( $_COOKIE[ 'zh_acc_' . $cid ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ 'zh_acc_' . $cid ] ) ) : ''; // phpcs:ignore
	if ( ! $c || false === strpos( $c, '.' ) ) {
		return false;
	}
	list( $who, $sig ) = explode( '.', $c, 2 );
	if ( ! hash_equals( zhdanov_access_sig( $cid, $who ), $sig ) ) {
		return false;
	}
	if ( 'code' === $who ) {
		return '' !== zhdanov_core_m( $cid, '_zh_access' );
	}
	$o = (int) $who;
	return $o && 'paid' === get_post_meta( $o, '_zh_st', true ) && (int) get_post_meta( $o, '_zh_item', true ) === (int) $cid && ! get_post_meta( $o, '_zh_revoked', true );
}

function zhdanov_access_set( $cid, $who ) {
	$v = $who . '.' . zhdanov_access_sig( $cid, $who );
	setcookie( 'zh_acc_' . $cid, $v, array( 'expires' => time() + YEAR_IN_SECONDS, 'path' => COOKIEPATH ? COOKIEPATH : '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
	$_COOKIE[ 'zh_acc_' . $cid ] = $v;
}

function zhdanov_access_link( $order ) {
	return add_query_arg( array( 'dostup' => $order, 'k' => wp_hash( 'zh_link_' . $order ) ), get_permalink( (int) get_post_meta( $order, '_zh_item', true ) ) );
}

/* Ссылка из письма и ввод кода. */
add_action( 'template_redirect', function () {
	if ( ! empty( $_GET['dostup'] ) && ! empty( $_GET['k'] ) ) { // phpcs:ignore
		$o = (int) $_GET['dostup']; // phpcs:ignore
		if ( hash_equals( wp_hash( 'zh_link_' . $o ), (string) wp_unslash( $_GET['k'] ) ) && 'paid' === get_post_meta( $o, '_zh_st', true ) ) { // phpcs:ignore
			zhdanov_access_set( (int) get_post_meta( $o, '_zh_item', true ), $o );
			wp_safe_redirect( remove_query_arg( array( 'dostup', 'k' ) ) );
			exit;
		}
	}
	if ( ! empty( $_POST['zh_code'] ) && ! empty( $_POST['zh_lc'] ) && isset( $_POST['_zhcode'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_zhcode'] ) ), 'zh_code' ) ) {
		$cid  = (int) $_POST['zh_lc'];
		$code = trim( (string) zhdanov_core_m( $cid, '_zh_access' ) );
		$key  = 'zh_codetry_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
		if ( (int) get_transient( $key ) < 10 && $code && hash_equals( mb_strtolower( $code ), mb_strtolower( trim( sanitize_text_field( wp_unslash( $_POST['zh_code'] ) ) ) ) ) ) {
			zhdanov_access_set( $cid, 'code' );
			wp_safe_redirect( get_permalink( $cid ) );
			exit;
		}
		set_transient( $key, (int) get_transient( $key ) + 1, 15 * MINUTE_IN_SECONDS );
		$GLOBALS['zh_code_wrong'] = 1;
	}
} );

/* После оплаты курса: письмо со ссылкой доступа. */
add_action( 'zhdanov_paid', function ( $order ) {
	$cid   = (int) get_post_meta( $order, '_zh_item', true );
	$email = get_post_meta( $order, '_zh_email', true );
	if ( 'zh_course' !== get_post_type( $cid ) || ! $email ) {
		return;
	}
	wp_mail( $email, zhdanov_core__( 'Доступ к курсу' ) . ': ' . get_the_title( $cid ), 'Здравствуйте, ' . get_post_meta( $order, '_zh_name', true ) . "!\n\nСпасибо за оплату. Уроки курса «" . get_the_title( $cid ) . "» открываются по ссылке (откройте её на каждом устройстве, где будете учиться):\n" . zhdanov_access_link( $order ) . "\n\nСохраните это письмо.\n\n" . get_bloginfo( 'name' ) . ', ' . home_url( '/' ) );
}, 20 );

/* Возврат с оплаты курса: сразу открываем уроки в этом браузере. */
add_action( 'template_redirect', function () {
	if ( empty( $_GET['oplata'] ) || empty( $_GET['k'] ) ) { // phpcs:ignore
		return;
	}
	$o = (int) $_GET['oplata']; // phpcs:ignore
	if ( hash_equals( wp_hash( 'zh_order_' . $o ), (string) wp_unslash( $_GET['k'] ) ) && 'paid' === zhdanov_pay_check( $o ) && 'zh_course' === get_post_type( (int) get_post_meta( $o, '_zh_item', true ) ) ) { // phpcs:ignore
		zhdanov_access_set( (int) get_post_meta( $o, '_zh_item', true ), $o );
	}
}, 3 );

/* В «Оплатах»: ссылка доступа ученику и «Закрыть доступ» (например, при возврате денег). */
add_filter( 'post_row_actions', function ( $a, $p ) {
	if ( 'zh_order' === $p->post_type && 'paid' === get_post_meta( $p->ID, '_zh_st', true ) && 'zh_course' === get_post_type( (int) get_post_meta( $p->ID, '_zh_item', true ) ) ) {
		$a['zh_acc']    = '<a href="' . esc_url( zhdanov_access_link( $p->ID ) ) . '" target="_blank">Ссылка доступа</a>';
		$a['zh_revoke'] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=zhdanov_revoke&id=' . $p->ID ), 'zh_revoke_' . $p->ID ) ) . '">' . ( get_post_meta( $p->ID, '_zh_revoked', true ) ? 'Вернуть доступ' : 'Закрыть доступ' ) . '</a>';
	}
	return $a;
}, 10, 2 );
add_action( 'admin_post_zhdanov_revoke', function () {
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore
	if ( ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'zh_revoke_' . $id ) ) {
		wp_die( 'Нет доступа.' );
	}
	update_post_meta( $id, '_zh_revoked', get_post_meta( $id, '_zh_revoked', true ) ? '' : 1 );
	wp_safe_redirect( admin_url( 'edit.php?post_type=zh_order' ) );
	exit;
} );

/* Страница с закрытыми уроками не кэшируется у ученика как общая. */
add_action( 'template_redirect', function () {
	if ( is_singular() && has_block( 'zhdanov/locked', get_queried_object_id() ) ) {
		nocache_headers();
	}
}, 20 );

/* Код доступа не отдаём через REST никому, кроме редакторов курса. */
add_filter( 'rest_prepare_zh_course', function ( $res, $post ) {
	if ( ! current_user_can( 'edit_post', $post->ID ) ) {
		$d = $res->get_data();
		if ( isset( $d['meta']['_zh_access'] ) ) {
			unset( $d['meta']['_zh_access'] );
			$res->set_data( $d );
		}
	}
	return $res;
}, 10, 2 );
