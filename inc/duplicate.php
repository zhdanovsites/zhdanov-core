<?php
/**
 * «Копия» для любых записей: туров, услуг, событий, новостей, товаров, обычных страниц.
 * Копируются текст, отрывок, фото записи, рубрики и метки, все поля (цена, даты, ссылка на оплату…).
 * Копия создаётся черновиком и сразу открывается в редакторе. Страницы концепта копирует «Копия страницы».
 *
 * @package zhdanov-core
 */

defined( 'ABSPATH' ) || exit;

function zhdanov_dup_ok( $post ) {
	$skip = array( 'attachment', 'zh_lead', 'zh_csnap', 'zh_booking', 'zh_order', 'zh_wait', 'user_request' );
	return $post && ! in_array( $post->post_type, $skip, true ) && 0 !== strpos( $post->post_type, 'wp_' ) && ! get_post_meta( $post->ID, '_zh_concept', true ) && current_user_can( 'edit_post', $post->ID );
}

$zhdanov_dup_link = function ( $actions, $post ) {
	if ( zhdanov_dup_ok( $post ) && 'trash' !== $post->post_status ) {
		$url               = wp_nonce_url( admin_url( 'admin-post.php?action=zh_dup&post=' . $post->ID ), 'zh_dup_' . $post->ID );
		$actions['zh_dup'] = '<a href="' . esc_url( $url ) . '" aria-label="' . esc_attr( 'Сделать копию «' . get_the_title( $post ) . '»' ) . '">Копия</a>';
	}
	return $actions;
};
add_filter( 'post_row_actions', $zhdanov_dup_link, 10, 2 );
add_filter( 'page_row_actions', $zhdanov_dup_link, 10, 2 );

add_action( 'admin_post_zh_dup', function () {
	$id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; // phpcs:ignore
	check_admin_referer( 'zh_dup_' . $id );
	$src = get_post( $id );
	if ( ! zhdanov_dup_ok( $src ) ) {
		wp_die( 'Нет доступа' );
	}
	$new = wp_insert_post( wp_slash( array(
		'post_type'    => $src->post_type,
		'post_status'  => 'draft',
		'post_title'   => $src->post_title . ' (копия)',
		'post_content' => $src->post_content,
		'post_excerpt' => $src->post_excerpt,
		'post_parent'  => $src->post_parent,
		'menu_order'   => $src->menu_order,
		'post_author'  => get_current_user_id(),
		'comment_status' => $src->comment_status,
	) ) );
	if ( ! $new || is_wp_error( $new ) ) {
		wp_die( 'Не получилось сделать копию' );
	}
	foreach ( get_object_taxonomies( $src->post_type ) as $tax ) {
		$terms = wp_get_object_terms( $id, $tax, array( 'fields' => 'ids' ) );
		if ( $terms && ! is_wp_error( $terms ) ) {
			wp_set_object_terms( $new, $terms, $tax );
		}
	}
	$skip = array( '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date', '_zh_ihash', '_wp_trash_meta_status', '_wp_trash_meta_time', '_wp_desired_post_slug' );
	foreach ( get_post_meta( $id ) as $k => $vals ) {
		if ( in_array( $k, $skip, true ) ) {
			continue;
		}
		foreach ( $vals as $v ) {
			add_post_meta( $new, $k, wp_slash( maybe_unserialize( $v ) ) );
		}
	}
	if ( function_exists( 'zhdanov_log' ) ) {
		zhdanov_log( 'копия', get_the_title( $src ) );
	}
	wp_safe_redirect( admin_url( 'post.php?post=' . $new . '&action=edit' ) );
	exit;
} );
