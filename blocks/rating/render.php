<?php
/**
 * Рейтинг на картах: только настоящие цифры, вписанные вручную, с датой проверки и ссылкой на источник.
 * Отзывы с Яндекс Карт автоматически не забираем: это запрещено условиями Яндекса.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
$r   = (float) str_replace( ',', '.', (string) $attributes['rating'] );
$url = esc_url( $attributes['url'] );
if ( ! $r || ! $url ) {
	if ( wp_is_serving_rest_request() ) {
		echo '<p>Впишите справа оценку, число отзывов и ссылку на карточку организации на Яндекс Картах или в 2ГИС.</p>';
	}
	return;
}
$full = (int) floor( $r );
$half = $r - $full >= 0.5;
$st   = str_repeat( '★', $full ) . ( $half ? '⯪' : '' ) . str_repeat( '☆', max( 0, 5 - $full - ( $half ? 1 : 0 ) ) );
echo '<div ' . get_block_wrapper_attributes( array( 'class' => 'zh-rating' ) ) . '>'; // phpcs:ignore
echo '<p class="zh-rating__score"><span class="zh-rating__num">' . esc_html( number_format( $r, 1, ',', '' ) ) . '</span> <span class="zh-rating__stars" aria-hidden="true">' . esc_html( $st ) . '</span></p>';
echo '<p class="zh-rating__meta">' . esc_html( sprintf( zhdanov_core__( 'Оценка на %s' ), $attributes['service'] ) . ( $attributes['count'] ? ', ' . $attributes['count'] . ' ' . zhdanov_core__( 'отзывов' ) : '' ) . ( $attributes['checked'] ? ' · ' . zhdanov_core__( 'на' ) . ' ' . $attributes['checked'] : '' ) ) . '</p>';
echo '<p class="zh-rating__btns"><a class="zh-btn-ghost" href="' . $url . '" target="_blank" rel="noopener">' . esc_html( zhdanov_core__( 'Читать отзывы' ) ) . '</a>';
$rv = $attributes['reviewUrl'] ? esc_url( $attributes['reviewUrl'] ) : $url;
echo ' <a class="wp-element-button zh-btn-sm" href="' . $rv . '" target="_blank" rel="noopener" data-zh-goal="review">' . esc_html( zhdanov_core__( 'Оставить отзыв' ) ) . '</a></p></div>'; // phpcs:ignore
