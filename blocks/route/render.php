<?php
/**
 * Блок «Адрес и маршрут». Карта Яндекса загружается только по кнопке (152-ФЗ, скорость).
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
$r    = zhdanov_core_routes();
$addr = zhdanov_core_opt( 'address' );
if ( ! $r ) {
	if ( wp_is_serving_rest_request() ) {
		echo '<p>Укажите адрес: Консоль → Жданов → Контакты.</p>';
	}
	return;
}
echo '<div ' . get_block_wrapper_attributes( array( 'class' => 'zh-route' ) ) . '>'; // phpcs:ignore
if ( $addr ) {
	echo '<p class="zh-route__addr">' . esc_html( $addr ) . '</p>';
}
if ( zhdanov_core_opt( 'hours' ) ) {
	echo '<p class="zh-route__hours">' . esc_html( zhdanov_core_opt( 'hours' ) ) . '</p>';
}
echo '<div class="zh-route__btns">';
foreach ( array( 'yandex', '2gis', 'google' ) as $k ) {
	printf(
		'<a class="%1$s" href="%2$s" target="_blank" rel="noopener" data-zh-goal="route">%3$s</a>',
		'yandex' === $k ? 'wp-element-button' : 'zh-btn-ghost',
		esc_url( $r[ $k ]['url'] ),
		esc_html( $r[ $k ]['label'] )
	);
}
echo '</div>';
if ( ! empty( $r['embed'] ) ) {
	printf(
		'<div class="zh-map" data-src="%s"><button type="button" class="zh-map__btn">Показать карту здесь</button><p class="zh-map__note">Карта загрузится с сервиса Яндекс Карты.</p></div>',
		esc_url( $r['embed'] )
	);
}
echo '</div>';
