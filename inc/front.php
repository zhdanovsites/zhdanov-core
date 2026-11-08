<?php
/**
 * На сайте: баннер cookie (152-ФЗ), Яндекс Метрика после согласия, нижняя панель на телефоне.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'zhdanov-core-front' );
	wp_enqueue_script( 'zhdanov-core-front', ZHDANOV_CORE_URL . 'assets/front.js', array(), ZHDANOV_CORE_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	$pp = (int) zhdanov_core_opt( 'privacy_page' );
	if ( ! $pp ) {
		$pp = (int) get_option( 'wp_page_for_privacy_policy' );
	}
	wp_localize_script(
		'zhdanov-core-front',
		'zhCore',
		array(
			'metrika' => zhdanov_core_opt( 'metrika' ),
			'cookie'  => (int) zhdanov_core_opt( 'cookie_on' ),
			'privacy' => $pp && 'publish' === get_post_status( $pp ) ? get_permalink( $pp ) : '',
			'en'      => zhdanov_core_is_en() ? 1 : 0,
		)
	);
} );

/* Нижняя панель на телефоне: звонок + маршрут / запись. */
add_action( 'wp_footer', function () {
	if ( ! zhdanov_core_opt( 'bar_on' ) ) {
		return;
	}
	$phone  = zhdanov_core_opt( 'phone' );
	$routes = zhdanov_core_routes();
	$label  = zhdanov_core_opt( 'bar_label' ) ? zhdanov_core_opt( 'bar_label' ) : 'Маршрут';
	$url    = zhdanov_core_opt( 'bar_url' ) ? zhdanov_core_opt( 'bar_url' ) : ( $routes ? $routes['yandex']['url'] : '' );
	if ( ! $phone && ! $url ) {
		return;
	}
	ob_start();
	echo '<nav class="zh-bar" aria-label="Быстрая связь">';
	if ( $phone ) {
		echo '<a class="zh-bar__call" href="tel:' . esc_attr( zhdanov_core_tel( $phone ) ) . '" data-zh-goal="call">' . zhdanov_core_icon( 'phone', 20 ) . '<span>Позвонить</span></a>'; // phpcs:ignore
	}
	if ( $url ) {
		$ext = 0 === strpos( $url, 'http' );
		echo '<a class="zh-bar__second" href="' . esc_url( $url ) . '"' . ( $ext ? ' target="_blank" rel="noopener"' : '' ) . ' data-zh-goal="' . ( zhdanov_core_opt( 'bar_url' ) ? 'booking' : 'route' ) . '"><span>' . esc_html( $label ) . '</span></a>';
	}
	echo '</nav>';
	echo zhdanov_core_translate_html( ob_get_clean() ); // phpcs:ignore
} );
