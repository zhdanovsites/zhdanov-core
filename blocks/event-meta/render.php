<?php
/**
 * Блок «Афиша: дата, место, билеты» (на странице события).
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
$pid = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : get_the_ID();
if ( ! $pid || 'zh_event' !== get_post_type( $pid ) ) {
	return;
}
$e    = zhdanov_core_event( $pid );
$addr = $e['address'] ? $e['address'] : zhdanov_core_opt( 'address' );
echo '<div ' . get_block_wrapper_attributes( array( 'class' => 'zh-emeta is-' . esc_attr( $e['status'] ) ) ) . '>'; // phpcs:ignore
if ( 'scheduled' !== $e['status'] ) {
	echo '<p class="zh-status zh-status--' . esc_attr( $e['status'] ) . '">' . esc_html( $e['status_label'] ) . '</p>';
}
echo '<dl class="zh-emeta__list">';
echo '<div><dt>Когда</dt><dd>' . esc_html( $e['date_label'] . ( $e['time'] ? ', ' . $e['time'] : '' ) ) . '</dd></div>';
if ( $e['venue'] || $addr ) {
	echo '<div><dt>Где</dt><dd>' . esc_html( implode( ', ', array_filter( array( $e['venue'], $addr ) ) ) ) . '</dd></div>';
}
if ( $e['price'] ) {
	echo '<div><dt>Цена</dt><dd>' . esc_html( $e['price'] ) . '</dd></div>';
}
if ( $e['age'] ) {
	echo '<div><dt>Возраст</dt><dd><span class="zh-badge">' . esc_html( $e['age'] ) . '</span></dd></div>';
}
echo '</dl>';
if ( $e['tickets'] && 'scheduled' === $e['status'] && ! $e['past'] ) {
	echo '<p><a class="wp-element-button" href="' . esc_url( $e['tickets'] ) . '" target="_blank" rel="noopener" data-zh-goal="tickets">Купить билет</a></p>';
}
echo '</div>';
