<?php
/**
 * Помощники: контакты, иконки, ссылки маршрута.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zhdanov_core_icon( $name, $size = 24 ) {
	$p = zhdanov_core_icon_paths();
	if ( empty( $p[ $name ] ) ) {
		return '';
	}
	return sprintf( '<svg class="zh-ico" width="%1$d" height="%1$d" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="%2$s"/></svg>', (int) $size, esc_attr( $p[ $name ] ) );
}

function zhdanov_core_tel( $phone ) {
	$d = preg_replace( '/[^\d+]/', '', (string) $phone );
	if ( 0 === strpos( $d, '8' ) && 11 === strlen( $d ) ) {
		$d = '+7' . substr( $d, 1 );
	}
	return $d;
}

/**
 * Список контактов в правильном порядке для страны сайта.
 * Россия: MAX, телефон, Telegram, ВК… WhatsApp не показываем.
 * Беларусь и зарубеж: телефон, WhatsApp, Telegram…
 */
function zhdanov_core_contacts() {
	$region = zhdanov_core_opt( 'region' );
	$items  = array();

	$phone = zhdanov_core_opt( 'phone' );
	$tg    = trim( (string) zhdanov_core_opt( 'telegram' ) );
	if ( $tg && ! preg_match( '~^https?://~', $tg ) ) {
		$tg = 'https://t.me/' . ltrim( $tg, '@' );
	}
	$wa = zhdanov_core_opt( 'whatsapp' );

	$all = array(
		'max'       => array( 'MAX', zhdanov_core_opt( 'max' ), '#7357FF' ),
		'phone'     => array( $phone ? $phone : 'Телефон', $phone ? 'tel:' . zhdanov_core_tel( $phone ) : '', '' ),
		'telegram'  => array( 'Telegram', $tg, '#229ED9' ),
		'whatsapp'  => array( 'WhatsApp', $wa ? 'https://wa.me/' . $wa : '', '#25D366' ),
		'vk'        => array( 'ВКонтакте', zhdanov_core_opt( 'vk' ), '#0077FF' ),
		'instagram' => array( 'ru' === $region ? 'Instagram*' : 'Instagram', zhdanov_core_opt( 'instagram' ), '#E4405F' ),
		'youtube'   => array( 'YouTube', zhdanov_core_opt( 'youtube' ), '#FF0000' ),
		'ok'        => array( 'Одноклассники', zhdanov_core_opt( 'ok' ), '#EE8208' ),
		'email'     => array( zhdanov_core_opt( 'email' ) ? zhdanov_core_opt( 'email' ) : 'Почта', zhdanov_core_opt( 'email' ) ? 'mailto:' . zhdanov_core_opt( 'email' ) : '', '' ),
	);

	$order = 'ru' === $region
		? array( 'max', 'phone', 'telegram', 'vk', 'instagram', 'youtube', 'ok', 'email' )
		: array( 'phone', 'whatsapp', 'telegram', 'max', 'vk', 'instagram', 'youtube', 'ok', 'email' );

	foreach ( $order as $key ) {
		if ( ! empty( $all[ $key ][1] ) ) {
			$items[ $key ] = array(
				'label' => $all[ $key ][0],
				'url'   => $all[ $key ][1],
				'color' => $all[ $key ][2],
			);
		}
	}
	return $items;
}

/** Ссылки маршрута во все карты. */
function zhdanov_core_routes() {
	$addr = trim( (string) zhdanov_core_opt( 'address' ) );
	$lat  = str_replace( ',', '.', trim( (string) zhdanov_core_opt( 'lat' ) ) );
	$lng  = str_replace( ',', '.', trim( (string) zhdanov_core_opt( 'lng' ) ) );
	$has  = is_numeric( $lat ) && is_numeric( $lng );
	if ( ! $has && ! $addr ) {
		return array();
	}
	return array(
		'yandex' => array(
			'label' => 'Маршрут в Яндекс Картах',
			'url'   => $has ? 'https://yandex.ru/maps/?rtext=~' . $lat . ',' . $lng . '&rtt=auto' : 'https://yandex.ru/maps/?text=' . rawurlencode( $addr ),
		),
		'2gis'   => array(
			'label' => '2ГИС',
			'url'   => $has ? 'https://2gis.ru/routeSearch/rsType/car/to/' . $lng . ',' . $lat : 'https://2gis.ru/search/' . rawurlencode( $addr ),
		),
		'google' => array(
			'label' => 'Google Maps',
			'url'   => $has ? 'https://www.google.com/maps/dir/?api=1&destination=' . $lat . ',' . $lng : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $addr ),
		),
		'embed'  => $has ? 'https://yandex.ru/map-widget/v1/?ll=' . $lng . '%2C' . $lat . '&z=16&pt=' . $lng . '%2C' . $lat . '%2Cpm2rdm' : '',
	);
}

/** Цель Метрики для ссылки контакта. */
function zhdanov_core_goal_for( $key ) {
	if ( 'phone' === $key ) {
		return 'call';
	}
	if ( 'email' === $key ) {
		return 'email';
	}
	if ( in_array( $key, array( 'max', 'telegram', 'whatsapp' ), true ) ) {
		return 'messenger';
	}
	return 'social';
}

/** Ссылка на видео → адрес встраивания. YouTube (без cookie), Rutube, VK Видео. */
function zhdanov_core_video_embed( $url ) {
	if ( ! $url ) {
		return null;
	}
	if ( preg_match( '~(?:youtube\.com/(?:watch\?v=|shorts/|embed/|live/)|youtu\.be/)([\w-]{11})~', $url, $m ) ) {
		return array( 'service' => 'YouTube', 'embed' => 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?autoplay=1&rel=0' );
	}
	if ( preg_match( '~rutube\.ru/(?:video|play/embed|shorts)/([a-f0-9]{32})~', $url, $m ) ) {
		return array( 'service' => 'Rutube', 'embed' => 'https://rutube.ru/play/embed/' . $m[1] . '/?autoplay=1' );
	}
	if ( preg_match( '~(?:vk\.com|vk\.ru|vkvideo\.ru)/(?:video|clip)(-?\d+)_(\d+)~', $url, $m ) ) {
		return array( 'service' => 'VK Видео', 'embed' => 'https://vkvideo.ru/video_ext.php?oid=' . $m[1] . '&id=' . $m[2] . '&autoplay=1&hd=2' );
	}
	return null;
}

/** ID записи «Стилей» (глобальные стили). На свежем сайте её ещё нет: создаём. */
function zhdanov_core_styles_post_id() {
	$id = WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
	if ( ! $id ) {
		$data = WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles( wp_get_theme(), true );
		$id   = isset( $data['ID'] ) ? (int) $data['ID'] : 0;
	}
	return $id;
}

/** Валюта цен для разметки поисковиков по стране сайта. */
function zhdanov_core_currency() {
	$r = zhdanov_core_opt( 'region' );
	return 'by' === $r ? 'BYN' : ( 'eu' === $r ? 'EUR' : 'RUB' );
}
