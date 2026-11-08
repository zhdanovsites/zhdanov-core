<?php
/**
 * Блок «Контакты»: круглые иконки, список или крупные кнопки.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
$items = zhdanov_core_contacts();
if ( ! $items ) {
	if ( current_user_can( 'manage_options' ) && wp_is_serving_rest_request() ) {
		echo '<p>Заполните контакты: Консоль → Жданов.</p>';
	}
	return;
}
$style = isset( $attributes['style'] ) ? $attributes['style'] : 'icons';
echo '<div ' . get_block_wrapper_attributes( array( 'class' => 'zh-contacts zh-contacts--' . esc_attr( $style ) ) ) . '><ul role="list">'; // phpcs:ignore
foreach ( $items as $key => $it ) {
	$ext   = 0 === strpos( $it['url'], 'http' );
	$color = $it['color'] ? ' style="--zh-brand:' . esc_attr( $it['color'] ) . '"' : '';
	printf(
		'<li class="zh-c zh-c--%1$s"%2$s><a href="%3$s"%4$s data-zh-goal="%5$s"><span class="zh-c__ico">%6$s</span><span class="zh-c__label%7$s">%8$s</span></a></li>',
		esc_attr( $key ),
		$color, // phpcs:ignore
		esc_url( $it['url'] ),
		$ext ? ' target="_blank" rel="noopener"' : '',
		esc_attr( zhdanov_core_goal_for( $key ) ),
		zhdanov_core_icon( $key, 'buttons' === $style ? 22 : 20 ), // phpcs:ignore
		'icons' === $style ? ' screen-reader-text' : '',
		esc_html( $it['label'] )
	);
}
echo '</ul></div>';
