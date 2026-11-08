<?php
/**
 * Заголовок ленты записей: название страницы записей (Настройки → Чтение), иначе «Новости».
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
$pid   = (int) get_option( 'page_for_posts' );
$title = $pid ? get_the_title( $pid ) : 'Новости';
if ( is_paged() ) {
	$title .= ': страница ' . (int) get_query_var( 'paged' );
}
echo '<h1 ' . get_block_wrapper_attributes( array( 'class' => 'wp-block-heading' ) ) . '>' . esc_html( $title ) . '</h1>'; // phpcs:ignore
