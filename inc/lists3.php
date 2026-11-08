<?php
/**
 * Часть 3: вывод вакансий, документов, объектов недвижимости (с фильтром).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Вакансии: истёкшие скрываем, как акции ---------- */
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() ) {
		return;
	}
	if ( 'zh_job' === $q->get( 'post_type' ) || ( $q->is_main_query() && $q->is_post_type_archive( 'zh_job' ) ) ) {
		$q->set( 'meta_query', zhdanov_core_promo_meta_query() );
	}
	if ( $q->is_main_query() && ( $q->is_post_type_archive( 'zh_doc' ) || $q->is_tax( 'zh_doc_cat' ) ) ) {
		$q->set( 'posts_per_page', 300 );
	}
	// Недвижимость: фильтр ?komnat=2&do=15000000&rayon=slug, проданные в конце.
	if ( $q->is_main_query() && ( $q->is_post_type_archive( 'zh_estate' ) || $q->is_tax( 'zh_estate_cat' ) ) ) {
		$mq = array();
		if ( ! empty( $_GET['komnat'] ) ) { // phpcs:ignore
			$mq[] = array( 'key' => '_zh_rooms', 'value' => sanitize_key( $_GET['komnat'] ) ); // phpcs:ignore
		}
		if ( ! empty( $_GET['do'] ) ) { // phpcs:ignore
			$mq[] = array( 'key' => '_zh_price_num', 'value' => (int) $_GET['do'], 'compare' => '<=', 'type' => 'NUMERIC' ); // phpcs:ignore
		}
		if ( empty( $_GET['prodannye'] ) ) { // phpcs:ignore
			$mq[] = array( 'relation' => 'OR', array( 'key' => '_zh_deal', 'value' => 'sold', 'compare' => '!=' ), array( 'key' => '_zh_deal', 'compare' => 'NOT EXISTS' ) );
		}
		if ( $mq ) {
			$q->set( 'meta_query', $mq );
		}
		$q->set( 'posts_per_page', 24 );
	}
}, 31 );

function zhdanov_core_list_zh_job( $posts, $hl, $use_main ) {
	$h = '<ul class="zh-items zh-jobs" role="list">';
	foreach ( $posts as $p ) {
		$id   = $p->ID;
		$meta = array_filter( array( zhdanov_core_m( $id, '_zh_schedule' ), zhdanov_core_m( $id, '_zh_exp' ) ) );
		$h   .= '<li class="zh-item zh-job"><div class="zh-item__body">' . sprintf( '<%1$s class="zh-item__title"><a href="%2$s">%3$s</a></%1$s>', $hl, esc_url( get_permalink( $p ) ), esc_html( get_the_title( $p ) ) );
		if ( $meta ) {
			$h .= '<p class="zh-item__meta">' . esc_html( implode( ' · ', $meta ) ) . '</p>';
		}
		$h .= '</div><p class="zh-item__side">' . ( zhdanov_core_m( $id, '_zh_salary' ) ? '<span class="zh-price zh-nw">' . esc_html( zhdanov_core_m( $id, '_zh_salary' ) ) . '</span> ' : '' );
		$h .= zhdanov_core_book_button( 'Здравствуйте! Хочу откликнуться на вакансию «' . get_the_title( $p ) . '». Коротко о себе: ', get_the_title( $p ), 'Откликнуться' ) . '</p></li>';
	}
	return $h . '</ul>';
}
function zhdanov_core_single_zh_job( $id ) {
	$emp  = zhdanov_core_meta_config()['zh_job']['_zh_employ'][3];
	$rows = array_filter( array( zhdanov_core__( 'Зарплата' ) => zhdanov_core_m( $id, '_zh_salary' ), zhdanov_core__( 'График' ) => zhdanov_core_m( $id, '_zh_schedule' ), zhdanov_core__( 'Опыт' ) => zhdanov_core_m( $id, '_zh_exp' ), zhdanov_core__( 'Занятость' ) => isset( $emp[ zhdanov_core_m( $id, '_zh_employ' ) ] ) ? $emp[ zhdanov_core_m( $id, '_zh_employ' ) ] : '' ) );
	$h    = '<dl class="zh-emeta__list">';
	foreach ( $rows as $l => $v ) {
		$h .= '<div><dt>' . esc_html( $l ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>';
	}
	return $h . '</dl><p>' . zhdanov_core_book_button( 'Здравствуйте! Хочу откликнуться на вакансию «' . get_the_title( $id ) . '». Коротко о себе: ', '', 'Откликнуться' ) . '</p>';
}

/* ---------- Документы ---------- */
function zhdanov_core_doc_row( $p ) {
	$id   = $p->ID;
	$file = zhdanov_core_m( $id, '_zh_file' );
	$ext  = $file ? strtoupper( pathinfo( wp_parse_url( $file, PHP_URL_PATH ), PATHINFO_EXTENSION ) ) : '';
	$meta = array_filter( array( zhdanov_core_m( $id, '_zh_number' ), zhdanov_core_m( $id, '_zh_date' ) ? wp_date( 'd.m.Y', strtotime( zhdanov_core_m( $id, '_zh_date' ) . ' 12:00' ) ) : '' ) );
	$size = '';
	if ( $file && 0 === strpos( $file, home_url() ) ) {
		$aid = attachment_url_to_postid( $file );
		if ( $aid && file_exists( get_attached_file( $aid ) ) ) {
			$size = size_format( filesize( get_attached_file( $aid ) ), 1 );
		}
	}
	$h  = '<li class="zh-doc"><span class="zh-doc__ext" aria-hidden="true">' . esc_html( $ext ? $ext : 'DOC' ) . '</span><span class="zh-doc__body">';
	$h .= $file ? '<a class="zh-doc__name" href="' . esc_url( $file ) . '" target="_blank" rel="noopener" data-zh-goal="download">' . esc_html( get_the_title( $p ) ) . '<span class="screen-reader-text"> (' . esc_html( trim( $ext . ' ' . $size ) ) . ')</span></a>' : '<span class="zh-doc__name">' . esc_html( get_the_title( $p ) ) . '</span>';
	if ( $meta || $size ) {
		$h .= '<span class="zh-doc__meta">' . esc_html( implode( ' · ', array_filter( array_merge( $meta, array( $size ) ) ) ) ) . '</span>';
	}
	if ( has_excerpt( $p ) ) {
		$h .= '<span class="zh-doc__meta">' . esc_html( get_the_excerpt( $p ) ) . '</span>';
	}
	return $h . '</span></li>';
}
function zhdanov_core_list_zh_doc( $posts, $hl, $use_main ) {
	$groups = array();
	foreach ( $posts as $p ) {
		$t                     = get_the_terms( $p, 'zh_doc_cat' );
		$n                     = ( $t && ! is_wp_error( $t ) ) ? $t[0]->name : '';
		$groups[ $n ][]        = $p;
	}
	$h = '';
	foreach ( $groups as $n => $ps ) {
		if ( $n && count( $groups ) > 1 ) {
			$h .= sprintf( '<%1$s class="zh-menu-sec__title">%2$s</%1$s>', $hl, esc_html( $n ) );
		}
		$h .= '<ul class="zh-docs" role="list">';
		foreach ( $ps as $p ) {
			$h .= zhdanov_core_doc_row( $p );
		}
		$h .= '</ul>';
	}
	return $h;
}
function zhdanov_core_single_zh_doc( $id ) {
	return '<ul class="zh-docs" role="list">' . zhdanov_core_doc_row( get_post( $id ) ) . '</ul>';
}

/* ---------- Недвижимость ---------- */
function zhdanov_core_estate_rooms_label( $v ) {
	$o = zhdanov_core_meta_config()['zh_estate']['_zh_rooms'][3];
	if ( ctype_digit( (string) $v ) ) {
		return ( '4' === (string) $v ? '4+' : $v ) . '-' . zhdanov_core__( 'комн.' );
	}
	return isset( $o[ $v ] ) && $v ? $o[ $v ] : '';
}
function zhdanov_core_list_zh_estate( $posts, $hl, $use_main ) {
	$h = '';
	if ( $use_main ) {
		$rooms = zhdanov_core_meta_config()['zh_estate']['_zh_rooms'][3];
		$h    .= '<form class="zh-estate-filter" method="get" action=""><label>' . esc_html( zhdanov_core__( 'Комнат' ) ) . ' <select name="komnat">';
		foreach ( $rooms as $k => $l ) {
			$h .= '<option value="' . esc_attr( $k ) . '"' . selected( isset( $_GET['komnat'] ) ? sanitize_key( $_GET['komnat'] ) : '', $k, false ) . '>' . esc_html( $k ? $l : zhdanov_core__( 'Любое' ) ) . '</option>'; // phpcs:ignore
		}
		$h .= '</select></label><label>' . esc_html( zhdanov_core__( 'Цена до, ₽' ) ) . ' <input type="number" name="do" min="0" step="100000" inputmode="numeric" value="' . esc_attr( isset( $_GET['do'] ) ? (int) $_GET['do'] : '' ) . '"></label>'; // phpcs:ignore
		$h .= '<label class="zh-estate-filter__cb"><input type="checkbox" name="prodannye" value="1"' . checked( ! empty( $_GET['prodannye'] ), true, false ) . '> ' . esc_html( zhdanov_core__( 'Показать проданные' ) ) . '</label>'; // phpcs:ignore
		$h .= '<button type="submit" class="wp-element-button zh-btn-sm">' . esc_html( zhdanov_core__( 'Показать' ) ) . '</button></form>';
		if ( ! $posts ) {
			return $h . '<p class="zh-empty">' . esc_html( zhdanov_core__( 'Под эти условия сейчас ничего нет. Напишите нам: подберём.' ) ) . '</p>';
		}
	}
	$deal = zhdanov_core_meta_config()['zh_estate']['_zh_deal'][3];
	$h   .= '<ul class="zh-items zh-estates" role="list">';
	foreach ( $posts as $p ) {
		$id  = $p->ID;
		$d   = zhdanov_core_m( $id, '_zh_deal' );
		$sub = array_filter( array( zhdanov_core_estate_rooms_label( zhdanov_core_m( $id, '_zh_rooms' ) ), zhdanov_core_m( $id, '_zh_area' ) ? str_replace( '.', ',', zhdanov_core_m( $id, '_zh_area' ) ) . ' м²' : '', zhdanov_core_m( $id, '_zh_floor' ) ? zhdanov_core__( 'этаж' ) . ' ' . zhdanov_core_m( $id, '_zh_floor' ) : '' ) );
		$h  .= '<li class="zh-item zh-tour zh-estate' . ( 'sold' === $d ? ' is-sold' : '' ) . '">';
		$h  .= '<a class="zh-item__img" href="' . esc_url( get_permalink( $p ) ) . '" tabindex="-1" aria-hidden="true">' . ( has_post_thumbnail( $p ) ? get_the_post_thumbnail( $p, 'zhdanov-card', array( 'loading' => 'lazy', 'alt' => '' ) ) : '<span class="zh-tile__ph"></span>' ) . '</a>';
		if ( $d && 'sale' !== $d ) {
			$h .= '<span class="zh-promo__badge">' . esc_html( zhdanov_core__( $deal[ $d ] ) ) . '</span>';
		}
		$h .= '<div class="zh-item__body">' . sprintf( '<%1$s class="zh-item__title"><a href="%2$s">%3$s</a></%1$s>', $hl, esc_url( get_permalink( $p ) ), esc_html( get_the_title( $p ) ) );
		if ( $sub ) {
			$h .= '<p class="zh-item__meta">' . esc_html( implode( ' · ', $sub ) ) . '</p>';
		}
		if ( zhdanov_core_m( $id, '_zh_address' ) ) {
			$h .= '<p class="zh-item__meta">' . esc_html( zhdanov_core_m( $id, '_zh_address' ) ) . '</p>';
		}
		$h .= '</div><p class="zh-item__side">' . ( zhdanov_core_m( $id, '_zh_price' ) ? '<span class="zh-price zh-nw">' . esc_html( zhdanov_core_m( $id, '_zh_price' ) ) . '</span>' : '' ) . '</p></li>';
	}
	return $h . '</ul>';
}
function zhdanov_core_single_zh_estate( $id ) {
	$deal = zhdanov_core_meta_config()['zh_estate']['_zh_deal'][3];
	$rows = array_filter( array( zhdanov_core__( 'Цена' ) => zhdanov_core_m( $id, '_zh_price' ), zhdanov_core__( 'Комнат' ) => zhdanov_core_estate_rooms_label( zhdanov_core_m( $id, '_zh_rooms' ) ), zhdanov_core__( 'Площадь' ) => zhdanov_core_m( $id, '_zh_area' ) ? str_replace( '.', ',', zhdanov_core_m( $id, '_zh_area' ) ) . ' м²' : '', zhdanov_core__( 'Этаж' ) => zhdanov_core_m( $id, '_zh_floor' ), zhdanov_core__( 'Сдача' ) => zhdanov_core_m( $id, '_zh_ready' ), zhdanov_core__( 'Адрес' ) => zhdanov_core_m( $id, '_zh_address' ), zhdanov_core__( 'Статус' ) => zhdanov_core_m( $id, '_zh_deal' ) ? zhdanov_core__( $deal[ zhdanov_core_m( $id, '_zh_deal' ) ] ) : '' ) );
	$h    = '<dl class="zh-emeta__list">';
	foreach ( $rows as $l => $v ) {
		$h .= '<div><dt>' . esc_html( $l ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>';
	}
	$h .= '</dl>';
	if ( 'sold' !== zhdanov_core_m( $id, '_zh_deal' ) ) {
		$h .= '<p>' . zhdanov_core_book_button( 'Здравствуйте! Интересует объект «' . get_the_title( $id ) . '». Хочу посмотреть.', '', 'Записаться на просмотр' ) . '</p>';
	}
	return $h;
}
/* Цена числом для фильтра: тот же механизм, что у каталога (_zh_price_num пишется сам). */

/* ---------- Schema: вакансия (JobPosting), объект (Offer) ---------- */
add_action( 'wp_head', function () {
	if ( is_singular( 'zh_job' ) ) {
		$id  = get_queried_object_id();
		$sal = zhdanov_core_m( $id, '_zh_salary' );
		$d   = array(
			'@context'           => 'https://schema.org',
			'@type'              => 'JobPosting',
			'title'              => get_the_title( $id ),
			'description'        => wp_strip_all_tags( get_post_field( 'post_content', $id ) ) ? wpautop( wp_strip_all_tags( get_post_field( 'post_content', $id ) ) ) : get_the_title( $id ),
			'datePosted'         => get_the_date( 'Y-m-d', $id ),
			'employmentType'     => zhdanov_core_m( $id, '_zh_employ' ) ? zhdanov_core_m( $id, '_zh_employ' ) : 'FULL_TIME',
			'hiringOrganization' => array( '@type' => 'Organization', 'name' => get_bloginfo( 'name' ), 'sameAs' => home_url( '/' ) ),
			'jobLocation'        => array( '@type' => 'Place', 'address' => array( '@type' => 'PostalAddress', 'streetAddress' => zhdanov_core_opt( 'address' ), 'addressCountry' => ( 'by' === zhdanov_core_opt( 'region' ) ? 'BY' : ( 'eu' === zhdanov_core_opt( 'region' ) ? '' : 'RU' ) ) ) ),
		);
		if ( zhdanov_core_m( $id, '_zh_until' ) ) {
			$d['validThrough'] = zhdanov_core_m( $id, '_zh_until' );
		}
		if ( preg_match_all( '/\d[\d\s ]{3,}/u', $sal, $m ) ) {
			$nums = array_map( function ( $x ) {
				return (int) preg_replace( '/\D/', '', $x );
			}, $m[0] );
			$d['baseSalary'] = array( '@type' => 'MonetaryAmount', 'currency' => zhdanov_core_currency(), 'value' => array( '@type' => 'QuantitativeValue', 'unitText' => 'MONTH' ) + ( count( $nums ) > 1 ? array( 'minValue' => min( $nums ), 'maxValue' => max( $nums ) ) : array( 'value' => $nums[0] ) ) );
		}
		zhdanov_core_jsonld( $d );
	}
	if ( is_singular( 'zh_estate' ) ) {
		$id  = get_queried_object_id();
		$sum = (int) get_post_meta( $id, '_zh_price_num', true );
		$d   = array( '@context' => 'https://schema.org', '@type' => 'Product', 'name' => get_the_title( $id ), 'url' => get_permalink( $id ) );
		if ( has_post_thumbnail( $id ) ) {
			$d['image'] = get_the_post_thumbnail_url( $id, 'large' );
		}
		if ( $sum ) {
			$d['offers'] = array( '@type' => 'Offer', 'price' => $sum, 'priceCurrency' => 'RUB', 'availability' => 'sold' === zhdanov_core_m( $id, '_zh_deal' ) ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock' );
		}
		zhdanov_core_jsonld( $d );
	}
}, 27 );
