<?php
/**
 * Подарочный сертификат: номиналы + своя сумма → оплата онлайн.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
$sums = array_filter( array_map( 'intval', explode( ',', (string) $attributes['sums'] ) ) );
echo '<div ' . get_block_wrapper_attributes( array( 'class' => 'zh-gift' ) ) . '>'; // phpcs:ignore
echo '<h2 class="zh-gift__title">' . esc_html( zhdanov_core__( $attributes['title'] ) ) . '</h2>';
if ( $attributes['text'] ) {
	echo '<p>' . esc_html( zhdanov_core__( $attributes['text'] ) ) . '</p>';
}
if ( ! zhdanov_pay_on() ) {
	// Оплата не подключена: сертификат заказывают через мессенджер.
	if ( 'donate' === $attributes['mode'] ) {
		echo '</div>';
		return;
	}
	echo '<p>' . zhdanov_core_book_button( 'Здравствуйте! Хочу подарочный сертификат на сумму: ', '', 'Заказать сертификат' ) . '</p></div>'; // phpcs:ignore
	return;
}
echo '<fieldset class="zh-gift__sums"><legend class="screen-reader-text">' . esc_html( zhdanov_core__( 'Сумма' ) ) . '</legend>';
foreach ( $sums as $i => $s ) {
	printf( '<label class="zh-chip"><input type="radio" name="zh_gift_sum" value="%1$d"%2$s> %3$s ₽</label>', (int) $s, 0 === $i ? ' checked' : '', esc_html( number_format( $s, 0, ',', ' ' ) ) );
}
if ( $attributes['custom'] ) {
	echo '<label class="zh-gift__own">' . esc_html( zhdanov_core__( 'Своя сумма' ) ) . ' <input type="number" min="' . (int) ( zhdanov_core_opt( 'gift_min' ) ? zhdanov_core_opt( 'gift_min' ) : 1000 ) . '" step="500" inputmode="numeric" name="zh_gift_own" placeholder="₽"></label>';
}
echo '</fieldset>';
$don = 'donate' === $attributes['mode'];
echo '<p><button type="button" class="wp-element-button zh-gift__buy" data-zh-pay="' . esc_attr( wp_json_encode( array( 'id' => 0, 'key' => $don ? 'donate' : 'gift', 'title' => $don ? zhdanov_core__( 'Пожертвование' ) : zhdanov_core__( 'Подарочный сертификат' ), 'sum' => $sums ? reset( $sums ) : 0 ) ) ) . '">' . esc_html( $don ? zhdanov_core__( 'Поддержать' ) : zhdanov_core__( 'Купить сертификат' ) ) . '</button></p>';
echo '</div>';
