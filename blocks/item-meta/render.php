<?php
/**
 * Блок «Раздел: цена, кнопка, детали» на странице услуги, товара, отзыва, альбома.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
$id   = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : get_the_ID();
$type = get_post_type( $id );
if ( $id && 'zh_course' === $type ) {
	echo zhdanov_core_course_html( $id ); // phpcs:ignore
	return;
}
if ( $id && function_exists( 'zhdanov_core_single_' . $type ) ) {
	echo '<div ' . get_block_wrapper_attributes( array( 'class' => 'zh-imeta zh-emeta zh-imeta--' . $type ) ) . '>' . call_user_func( 'zhdanov_core_single_' . $type, $id ) . '</div>'; // phpcs:ignore
	return;
}
if ( $id && in_array( $type, array( 'zh_person', 'zh_dish', 'zh_branch', 'zh_promo' ), true ) ) {
	$post = get_post( $id );
	if ( 'zh_promo' === $type ) {
		$u = zhdanov_core_m( $id, '_zh_until' );
		if ( $u && $u < wp_date( 'Y-m-d\TH:i' ) ) {
			echo '<p class="zh-note">' . esc_html( zhdanov_core__( 'Эта акция уже закончилась.' ) ) . '</p>';
			return;
		}
	}
	$html = call_user_func( 'zhdanov_core_list_' . $type, array( $post ), 'h2', false );
	if ( 'zh_person' === $type && zhdanov_core_m( $id, '_zh_edu' ) ) {
		$html .= '<h2 class="zh-imeta__h">' . esc_html( zhdanov_core__( 'Образование и сертификаты' ) ) . '</h2><ul class="zh-edu">';
		foreach ( array_filter( array_map( 'trim', explode( "\n", zhdanov_core_m( $id, '_zh_edu' ) ) ) ) as $l ) {
			$html .= '<li>' . esc_html( $l ) . '</li>';
		}
		$html .= '</ul>';
	}
	if ( 'zh_branch' === $type ) {
		$lat = str_replace( ',', '.', zhdanov_core_m( $id, '_zh_lat' ) );
		$lng = str_replace( ',', '.', zhdanov_core_m( $id, '_zh_lng' ) );
		if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
			$html .= '<div class="zh-map" data-src="' . esc_url( 'https://yandex.ru/map-widget/v1/?pt=' . $lng . ',' . $lat . ',pm2rdl&z=16&ll=' . $lng . ',' . $lat ) . '"><button type="button" class="zh-map__btn">' . esc_html( zhdanov_core__( 'Показать на карте' ) ) . '</button><p class="zh-map__note">' . esc_html( zhdanov_core__( 'Карта загрузится с сервиса Яндекс Карты.' ) ) . '</p></div>';
		}
	}
	echo '<div ' . get_block_wrapper_attributes( array( 'class' => 'zh-imeta zh-imeta--' . $type ) ) . '>' . $html . '</div>'; // phpcs:ignore
	return;
}
if ( ! $id || ! in_array( $type, array( 'zh_service', 'zh_product', 'zh_review', 'zh_album' ), true ) ) {
	return;
}
$rows = array();
$btn  = '';
switch ( $type ) {
	case 'zh_service':
	case 'zh_product':
		$price = zhdanov_core_m( $id, '_zh_price' );
		$old   = zhdanov_core_m( $id, '_zh_price_old' );
		if ( $price ) {
			$rows['Цена'] = ( $old ? '<s class="zh-price__old"><span class="screen-reader-text">Было: </span>' . esc_html( $old ) . '</s> ' : '' ) . '<span class="zh-price__now">' . esc_html( $price ) . '</span>';
		}
		if ( 'zh_service' === $type && zhdanov_core_m( $id, '_zh_duration' ) ) {
			$rows['Длительность'] = esc_html( zhdanov_core_m( $id, '_zh_duration' ) );
		}
		if ( 'zh_product' === $type ) {
			$rows['Наличие'] = esc_html( zhdanov_core_stock_label( zhdanov_core_m( $id, '_zh_stock' ) ) );
			if ( zhdanov_core_m( $id, '_zh_sku' ) ) {
				$rows['Артикул'] = esc_html( zhdanov_core_m( $id, '_zh_sku' ) );
			}
		}
		$link  = zhdanov_core_m( $id, 'zh_service' === $type ? '_zh_book' : '_zh_buy' );
		$href  = $link ? $link : zhdanov_core_fallback_action();
		$label = 'zh_service' === $type ? 'Записаться' : ( 'out' === zhdanov_core_m( $id, '_zh_stock' ) ? 'Узнать о поступлении' : 'Заказать' );
		$btn   = sprintf( '<a class="wp-element-button" href="%s"%s data-zh-goal="%s">%s</a>', esc_url( $href ), 0 === strpos( $href, 'http' ) ? ' target="_blank" rel="noopener"' : '', 'zh_service' === $type ? 'booking' : 'order', esc_html( $label ) );
		break;
	case 'zh_review':
		$r = (int) zhdanov_core_m( $id, '_zh_rating' );
		if ( zhdanov_core_m( $id, '_zh_author' ) ) {
			$rows['Автор'] = esc_html( zhdanov_core_m( $id, '_zh_author' ) . ( zhdanov_core_m( $id, '_zh_role' ) ? ', ' . zhdanov_core_m( $id, '_zh_role' ) : '' ) );
		}
		if ( $r ) {
			$rows['Оценка'] = '<span class="zh-stars" aria-label="' . $r . ' из 5">' . str_repeat( '★', $r ) . '<span class="zh-stars__off">' . str_repeat( '★', 5 - $r ) . '</span></span>';
		}
		if ( zhdanov_core_m( $id, '_zh_source' ) ) {
			$u              = zhdanov_core_m( $id, '_zh_source_url' );
			$rows['Источник'] = $u ? '<a href="' . esc_url( $u ) . '" target="_blank" rel="noopener nofollow">' . esc_html( zhdanov_core_m( $id, '_zh_source' ) ) . '</a>' : esc_html( zhdanov_core_m( $id, '_zh_source' ) );
		}
		break;
	case 'zh_album':
		foreach ( array( '_zh_when' => 'Когда', '_zh_place' => 'Где', '_zh_photo' => 'Фото' ) as $k => $l ) {
			if ( zhdanov_core_m( $id, $k ) ) {
				$rows[ $l ] = esc_html( zhdanov_core_m( $id, $k ) );
			}
		}
		break;
}
if ( ! $rows && ! $btn ) {
	return;
}
echo '<div ' . get_block_wrapper_attributes( array( 'class' => 'zh-emeta zh-imeta' ) ) . '>'; // phpcs:ignore
if ( $rows ) {
	echo '<dl class="zh-emeta__list">';
	foreach ( $rows as $l => $v ) {
		echo '<div><dt>' . esc_html( $l ) . '</dt><dd>' . $v . '</dd></div>'; // phpcs:ignore
	}
	echo '</dl>';
}
if ( $btn ) {
	echo '<p>' . $btn . '</p>'; // phpcs:ignore
}
echo '</div>';
