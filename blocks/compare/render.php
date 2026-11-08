<?php
/**
 * Было / стало: ползунок поверх двух фото. Без JS: два фото рядом.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
$b = (int) $attributes['beforeId'];
$a = (int) $attributes['afterId'];
if ( ! $b || ! $a ) {
	if ( wp_is_serving_rest_request() ) {
		echo '<p>Выберите два фото справа: «Было» и «Стало». Лучше одного размера и ракурса.</p>';
	}
	return;
}
static $cn = 0;
++$cn;
$lb = $attributes['before'] ? $attributes['before'] : 'Было';
$la = $attributes['after'] ? $attributes['after'] : 'Стало';
echo '<figure ' . get_block_wrapper_attributes( array( 'class' => 'zh-compare' ) ) . '>'; // phpcs:ignore
echo '<div class="zh-compare__box" style="--pos:50%">';
echo wp_get_attachment_image( $a, 'large', false, array( 'class' => 'zh-compare__after', 'loading' => 'lazy', 'alt' => esc_attr( zhdanov_core__( $la ) ) ) ); // phpcs:ignore
echo '<div class="zh-compare__before">' . wp_get_attachment_image( $b, 'large', false, array( 'loading' => 'lazy', 'alt' => esc_attr( zhdanov_core__( $lb ) ) ) ) . '</div>'; // phpcs:ignore
echo '<span class="zh-compare__tag zh-compare__tag--b" aria-hidden="true">' . esc_html( zhdanov_core__( $lb ) ) . '</span><span class="zh-compare__tag zh-compare__tag--a" aria-hidden="true">' . esc_html( zhdanov_core__( $la ) ) . '</span>';
echo '<span class="zh-compare__line" aria-hidden="true"></span>';
echo '<label class="screen-reader-text" for="zhc' . (int) $cn . '">' . esc_html( zhdanov_core__( 'Сравнить: было и стало' ) ) . '</label><input class="zh-compare__range" id="zhc' . (int) $cn . '" type="range" min="0" max="100" value="50">';
echo '</div>';
if ( $attributes['caption'] ) {
	echo '<figcaption>' . esc_html( $attributes['caption'] ) . '</figcaption>';
}
echo '</figure>';
