<?php
/**
 * Блок «Низ подвала»: © год, политика, пометка о Meta, подпись дизайнера.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
$parts = array( '© ' . wp_date( 'Y' ) . ' ' . esc_html( get_bloginfo( 'name' ) ) );
$pp    = (int) zhdanov_core_opt( 'privacy_page' );
if ( ! $pp ) {
	$pp = (int) get_option( 'wp_page_for_privacy_policy' );
}
if ( $pp && 'publish' === get_post_status( $pp ) ) {
	$parts[] = '<a href="' . esc_url( get_permalink( $pp ) ) . '">Политика конфиденциальности</a>';
}
if ( zhdanov_core_opt( 'cookie_on' ) ) {
	$parts[] = '<button type="button" class="zh-linkbtn" data-zh-cookie-open>Настройки cookie</button>';
}
echo '<div ' . get_block_wrapper_attributes( array( 'class' => 'zh-fmeta' ) ) . '>'; // phpcs:ignore
echo '<p>' . implode( ' · ', $parts ) . '</p>'; // phpcs:ignore
if ( zhdanov_core_opt( 'instagram' ) && 'ru' === zhdanov_core_opt( 'region' ) ) {
	echo '<p class="zh-fmeta__note">* Instagram принадлежит компании Meta, признанной экстремистской и запрещённой в РФ.</p>';
}
if ( zhdanov_core_opt( 'credit_on' ) && zhdanov_core_opt( 'credit_text' ) ) {
	$cu = zhdanov_core_opt( 'credit_url' );
	echo '<p class="zh-fmeta__credit">' . ( $cu ? '<a href="' . esc_url( $cu ) . '">' . esc_html( zhdanov_core_opt( 'credit_text' ) ) . '</a>' : esc_html( zhdanov_core_opt( 'credit_text' ) ) ) . '</p>';
}
echo '</div>';
