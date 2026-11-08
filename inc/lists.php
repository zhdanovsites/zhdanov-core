<?php
/**
 * Вывод разделов: Специалисты, Меню, Филиалы, Акции.
 * Блок «Раздел» (collection) сам вызывает zhdanov_core_list_<тип>(), если такая функция есть.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Акции: истёкшие не показываем нигде ---------- */
function zhdanov_core_promo_meta_query() {
	return array(
		'relation' => 'OR',
		array( 'key' => '_zh_until', 'value' => wp_date( 'Y-m-d\TH:i' ), 'compare' => '>=' ),
		array( 'key' => '_zh_until', 'value' => '', 'compare' => '=' ),
		array( 'key' => '_zh_until', 'compare' => 'NOT EXISTS' ),
	);
}
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() ) {
		return;
	}
	$pt = $q->get( 'post_type' );
	if ( 'zh_promo' === $pt || ( $q->is_main_query() && $q->is_post_type_archive( 'zh_promo' ) ) ) {
		$q->set( 'meta_query', zhdanov_core_promo_meta_query() );
	}
	if ( $q->is_main_query() && ( $q->is_post_type_archive( array( 'zh_dish', 'zh_person', 'zh_branch' ) ) || $q->is_tax( array( 'zh_dish_cat', 'zh_person_cat' ) ) ) ) {
		$q->set( 'posts_per_page', 200 );
	}
}, 30 );

/* Истёкшая акция по прямой ссылке: показываем, но с пометкой (не 404, чтобы не терять ссылки). */

/* ---------- Специалисты ---------- */
function zhdanov_core_list_zh_person( $posts, $hl, $use_main ) {
	// Фильтр по языку (на странице, без перезагрузки).
	$langs = array();
	foreach ( $posts as $p ) {
		foreach ( array_filter( array_map( 'trim', explode( ',', zhdanov_core_m( $p->ID, '_zh_langs' ) ) ) ) as $l ) {
			$langs[ mb_strtolower( $l ) ] = $l;
		}
	}
	$h = '';
	if ( $use_main && count( $langs ) > 1 ) {
		$h .= '<div class="zh-filter" role="group" aria-label="' . esc_attr( zhdanov_core__( 'Язык' ) ) . '"><button type="button" class="zh-chip is-active" data-zh-lang="" aria-pressed="true">' . esc_html( zhdanov_core__( 'Все языки' ) ) . '</button>';
		foreach ( $langs as $k => $l ) {
			$h .= '<button type="button" class="zh-chip" data-zh-lang="' . esc_attr( $k ) . '" aria-pressed="false">' . esc_html( mb_strtoupper( mb_substr( $l, 0, 1 ) ) . mb_substr( $l, 1 ) ) . '</button>';
		}
		$h .= '</div>';
	}
	$h .= '<ul class="zh-items zh-people" role="list">';
	foreach ( $posts as $p ) {
		$id    = $p->ID;
		$url   = get_permalink( $p );
		$name  = get_the_title( $p );
		$plang = mb_strtolower( zhdanov_core_m( $id, '_zh_langs' ) );
		$h    .= '<li class="zh-item zh-person" data-langs="' . esc_attr( $plang ) . '">';
		$h    .= '<a class="zh-person__img" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . ( has_post_thumbnail( $p ) ? get_the_post_thumbnail( $p, 'zhdanov-card', array( 'loading' => 'lazy', 'alt' => '' ) ) : '<span class="zh-person__ini">' . esc_html( zhdanov_core_initials( $name ) ) . '</span>' ) . '</a>';
		$h    .= '<div class="zh-item__body">';
		$h    .= sprintf( '<%1$s class="zh-item__title"><a href="%2$s">%3$s</a></%1$s>', $hl, esc_url( $url ), esc_html( $name ) );
		$meta  = array_filter( array( zhdanov_core_m( $id, '_zh_role' ), zhdanov_core_m( $id, '_zh_exp' ) ) );
		if ( $meta ) {
			$h .= '<p class="zh-item__meta">' . esc_html( implode( ' · ', $meta ) ) . '</p>';
		}
		if ( zhdanov_core_m( $id, '_zh_langs' ) ) {
			$h .= '<p class="zh-item__meta">' . esc_html( zhdanov_core__( 'Языки' ) . ': ' . zhdanov_core_m( $id, '_zh_langs' ) ) . '</p>';
		}
		$h .= '</div><div class="zh-item__side">';
		if ( zhdanov_core_m( $id, '_zh_price' ) ) {
			$h .= '<span class="zh-price zh-nw">' . esc_html( zhdanov_core_m( $id, '_zh_price' ) ) . '</span>';
		}
		$book = zhdanov_core_m( $id, '_zh_book' );
		$h   .= $book ? '<a class="wp-element-button zh-btn-sm" href="' . esc_url( $book ) . '" target="_blank" rel="noopener" data-zh-goal="booking">' . esc_html( zhdanov_core__( 'Записаться' ) ) . '<span class="screen-reader-text">: ' . esc_html( $name ) . '</span></a>'
			: zhdanov_core_book_button( 'Здравствуйте! Хочу записаться к специалисту: ' . $name . '.', $name );
		$h .= '</div></li>';
	}
	return $h . '</ul>';
}

/* ---------- Меню ---------- */
function zhdanov_core_list_zh_dish( $posts, $hl, $use_main ) {
	$groups = array();
	$order  = array();
	foreach ( $posts as $p ) {
		$terms = get_the_terms( $p, 'zh_dish_cat' );
		$t     = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0] : null;
		$key   = $t ? $t->term_id : 0;
		if ( ! isset( $groups[ $key ] ) ) {
			$groups[ $key ] = array( 'name' => $t ? $t->name : '', 'slug' => $t ? $t->slug : 'menu', 'items' => array() );
			$order[ $key ]  = $t ? (int) $t->term_order + (int) $t->term_id / 100000 : PHP_INT_MAX;
		}
		$groups[ $key ]['items'][] = $p;
	}
	asort( $order );
	$h = '';
	if ( count( $groups ) > 1 ) {
		$h .= '<nav class="zh-filter zh-menu-nav" aria-label="' . esc_attr( zhdanov_core__( 'Разделы меню' ) ) . '">';
		foreach ( array_keys( $order ) as $k ) {
			if ( $groups[ $k ]['name'] ) {
				$h .= '<a class="zh-chip" href="#menu-' . esc_attr( $groups[ $k ]['slug'] ) . '">' . esc_html( $groups[ $k ]['name'] ) . '</a>';
			}
		}
		$h .= '</nav>';
	}
	foreach ( array_keys( $order ) as $k ) {
		$g  = $groups[ $k ];
		$h .= '<section class="zh-menu-sec" id="menu-' . esc_attr( $g['slug'] ) . '">';
		if ( $g['name'] ) {
			$h .= sprintf( '<%1$s class="zh-menu-sec__title">%2$s</%1$s>', $hl, esc_html( $g['name'] ) );
		}
		$h .= '<ul class="zh-dishes" role="list">';
		foreach ( $g['items'] as $p ) {
			$id   = $p->ID;
			$tags = array_filter( array_map( 'trim', explode( ',', zhdanov_core_m( $id, '_zh_tags' ) ) ) );
			$h   .= '<li class="zh-dish">';
			if ( has_post_thumbnail( $p ) ) {
				$h .= '<span class="zh-dish__img">' . get_the_post_thumbnail( $p, 'thumbnail', array( 'loading' => 'lazy', 'alt' => '' ) ) . '</span>';
			}
			$h .= '<span class="zh-dish__body"><span class="zh-dish__name">' . esc_html( get_the_title( $p ) ) . '</span>';
			if ( has_excerpt( $p ) ) {
				$h .= '<span class="zh-dish__desc">' . esc_html( get_the_excerpt( $p ) ) . '</span>';
			}
			$info = array_filter( array( zhdanov_core_m( $id, '_zh_weight' ), zhdanov_core_m( $id, '_zh_allergens' ) ? zhdanov_core__( 'Аллергены' ) . ': ' . zhdanov_core_m( $id, '_zh_allergens' ) : '' ) );
			if ( $info ) {
				$h .= '<span class="zh-dish__info">' . esc_html( implode( ' · ', $info ) ) . '</span>';
			}
			if ( $tags ) {
				$h .= '<span class="zh-dish__tags">';
				foreach ( $tags as $t ) {
					$h .= '<span class="zh-badge">' . esc_html( $t ) . '</span> ';
				}
				$h .= '</span>';
			}
			$h .= '</span><span class="zh-dish__side">';
			if ( zhdanov_core_m( $id, '_zh_price' ) ) {
				$h .= '<span class="zh-price zh-nw">' . esc_html( zhdanov_core_m( $id, '_zh_price' ) ) . '</span>';
			}
			if ( zhdanov_core_m( $id, '_zh_buy' ) ) {
				$h .= '<a class="zh-more" href="' . esc_url( zhdanov_core_m( $id, '_zh_buy' ) ) . '" target="_blank" rel="noopener" data-zh-goal="order">' . esc_html( zhdanov_core__( 'Заказать' ) ) . '<span class="screen-reader-text">: ' . esc_html( get_the_title( $p ) ) . '</span></a>';
			}
			$h .= '</span></li>';
		}
		$h .= '</ul></section>';
	}
	return $h;
}

/* ---------- Филиалы ---------- */

/** Часы из текста «Пн-Пт 10:00-20:00» → [1..7 => [[open, close]]]. */
function zhdanov_core_parse_hours( $text ) {
	$map  = array( 'пн' => 1, 'вт' => 2, 'ср' => 3, 'чт' => 4, 'пт' => 5, 'сб' => 6, 'вс' => 7, 'mo' => 1, 'tu' => 2, 'we' => 3, 'th' => 4, 'fr' => 5, 'sa' => 6, 'su' => 7 );
	$week = array();
	foreach ( preg_split( '/\r?\n|;/', (string) $text ) as $line ) {
		$line = mb_strtolower( trim( $line ) );
		if ( ! $line ) {
			continue;
		}
		$days = array();
		if ( preg_match( '/^(ежедневно|каждый день|daily)/u', $line ) ) {
			$days = range( 1, 7 );
		} elseif ( preg_match_all( '/(пн|вт|ср|чт|пт|сб|вс|mo|tu|we|th|fr|sa|su)\s*(?:[-–]\s*(пн|вт|ср|чт|пт|сб|вс|mo|tu|we|th|fr|sa|su))?/u', $line, $mm, PREG_SET_ORDER ) ) {
			foreach ( $mm as $m ) {
				$a = $map[ $m[1] ];
				$b = ! empty( $m[2] ) ? $map[ $m[2] ] : $a;
				for ( $d = $a; ; $d = $d % 7 + 1 ) {
					$days[] = $d;
					if ( $d === $b ) {
						break;
					}
				}
			}
		}
		$spans = array();
		if ( preg_match_all( '/(\d{1,2})[:.](\d{2})\s*[-–]\s*(\d{1,2})[:.](\d{2})/', $line, $tm, PREG_SET_ORDER ) ) {
			foreach ( $tm as $t ) {
				$spans[] = array( sprintf( '%02d:%s', $t[1], $t[2] ), sprintf( '%02d:%s', $t[3], $t[4] ) );
			}
		}
		if ( preg_match( '/круглосуточно|24\/7/u', $line ) ) {
			$spans = array( array( '00:00', '24:00' ) );
		}
		foreach ( $days as $d ) {
			$week[ $d ] = $spans; // пусто = выходной
		}
	}
	return $week;
}

function zhdanov_core_list_zh_branch( $posts, $hl, $use_main ) {
	$pts = array();
	$h   = '<ul class="zh-items zh-branches" role="list">';
	foreach ( $posts as $p ) {
		$id    = $p->ID;
		$addr  = zhdanov_core_m( $id, '_zh_address' );
		$lat   = str_replace( ',', '.', zhdanov_core_m( $id, '_zh_lat' ) );
		$lng   = str_replace( ',', '.', zhdanov_core_m( $id, '_zh_lng' ) );
		$hours = zhdanov_core_m( $id, '_zh_hours' );
		$has   = is_numeric( $lat ) && is_numeric( $lng );
		if ( $has ) {
			$pts[] = $lng . ',' . $lat . ',pm2rdl' . ( count( $pts ) + 1 );
		}
		$h .= '<li class="zh-item zh-branch">';
		$h .= sprintf( '<%1$s class="zh-item__title"><a href="%2$s">%3$s</a></%1$s>', $hl, esc_url( get_permalink( $p ) ), esc_html( get_the_title( $p ) ) );
		if ( $addr ) {
			$h .= '<p class="zh-branch__addr">' . esc_html( $addr ) . '</p>';
		}
		if ( $hours ) {
			$h .= '<p class="zh-open" data-zh-hours="' . esc_attr( wp_json_encode( zhdanov_core_parse_hours( $hours ) ) ) . '" data-zh-tz="' . esc_attr( (int) ( wp_timezone()->getOffset( new DateTime( 'now' ) ) / 60 ) ) . '"></p>';
			$h .= '<p class="zh-branch__hours">' . nl2br( esc_html( $hours ) ) . '</p>';
		}
		$h .= '<p class="zh-branch__btns">';
		if ( zhdanov_core_m( $id, '_zh_phone' ) ) {
			$h .= '<a class="zh-btn-ghost" href="tel:' . esc_attr( zhdanov_core_tel( zhdanov_core_m( $id, '_zh_phone' ) ) ) . '" data-zh-goal="call">' . esc_html( zhdanov_core_m( $id, '_zh_phone' ) ) . '</a> ';
		}
		$route = $has ? 'https://yandex.ru/maps/?rtext=~' . $lat . ',' . $lng . '&rtt=auto' : ( $addr ? 'https://yandex.ru/maps/?text=' . rawurlencode( $addr ) : '' );
		if ( $route ) {
			$h .= '<a class="wp-element-button zh-btn-sm" href="' . esc_url( $route ) . '" target="_blank" rel="noopener" data-zh-goal="route">' . esc_html( zhdanov_core__( 'Маршрут' ) ) . '<span class="screen-reader-text">: ' . esc_html( get_the_title( $p ) ) . '</span></a>';
		}
		$h .= '</p></li>';
	}
	$h .= '</ul>';
	if ( count( $pts ) > 1 ) {
		$h .= '<div class="zh-map" data-src="' . esc_url( 'https://yandex.ru/map-widget/v1/?pt=' . implode( '~', $pts ) ) . '"><button type="button" class="zh-map__btn">' . esc_html( zhdanov_core__( 'Показать все адреса на карте' ) ) . '</button><p class="zh-map__note">' . esc_html( zhdanov_core__( 'Карта загрузится с сервиса Яндекс Карты.' ) ) . '</p></div>';
	}
	return $h;
}

/* ---------- Акции ---------- */
function zhdanov_core_list_zh_promo( $posts, $hl, $use_main ) {
	$h = '<ul class="zh-items zh-promos" role="list">';
	foreach ( $posts as $p ) {
		$id    = $p->ID;
		$until = zhdanov_core_m( $id, '_zh_until' );
		$ts    = 0;
		if ( $until ) {
			$dt = date_create_immutable( $until, wp_timezone() );
			$ts = $dt ? $dt->getTimestamp() : 0;
		}
		$link = zhdanov_core_m( $id, '_zh_link' );
		$h   .= '<li class="zh-item zh-promo">';
		if ( has_post_thumbnail( $p ) ) {
			$h .= '<span class="zh-item__img">' . get_the_post_thumbnail( $p, 'zhdanov-card', array( 'loading' => 'lazy', 'alt' => '' ) ) . '</span>';
		}
		if ( zhdanov_core_m( $id, '_zh_badge' ) ) {
			$h .= '<span class="zh-promo__badge">' . esc_html( zhdanov_core_m( $id, '_zh_badge' ) ) . '</span>';
		}
		$h .= '<div class="zh-item__body">' . sprintf( '<%1$s class="zh-item__title"><a href="%2$s">%3$s</a></%1$s>', $hl, esc_url( get_permalink( $p ) ), esc_html( get_the_title( $p ) ) );
		if ( has_excerpt( $p ) ) {
			$h .= '<p class="zh-item__text">' . esc_html( get_the_excerpt( $p ) ) . '</p>';
		}
		if ( $ts ) {
			$h .= '<p class="zh-promo__until">' . esc_html( zhdanov_core__( 'До' ) . ' ' . wp_date( 'j F', $ts ) ) . ' <span class="zh-timer" data-zh-until="' . (int) $ts . '"></span></p>';
		}
		$h .= '</div><p class="zh-item__side">';
		$h .= $link ? '<a class="wp-element-button zh-btn-sm" href="' . esc_url( $link ) . '" data-zh-goal="promo">' . esc_html( zhdanov_core__( 'Подробнее' ) ) . '<span class="screen-reader-text">: ' . esc_html( get_the_title( $p ) ) . '</span></a>'
			: zhdanov_core_book_button( 'Здравствуйте! Хочу воспользоваться акцией: ' . get_the_title( $p ) . '.', get_the_title( $p ), 'Хочу акцию' );
		$h .= '</p></li>';
	}
	return $h . '</ul>';
}

function zhdanov_core_initials( $name ) {
	$w = array_slice( preg_split( '/\s+/u', trim( $name ) ), 0, 2 );
	$o = '';
	foreach ( $w as $x ) {
		$o .= mb_strtoupper( mb_substr( $x, 0, 1 ) );
	}
	return $o;
}

/* Секция главной с выключенным или пустым разделом не показываем целиком (заголовок без содержимого выглядит недоделанным). */
add_filter( 'render_block_core/group', function ( $html, $block ) {
	if ( empty( $block['innerBlocks'] ) || wp_is_serving_rest_request() || is_admin() ) {
		return $html;
	}
	foreach ( $block['innerBlocks'] as $in ) {
		if ( 'zhdanov/collection' !== $in['blockName'] || ! empty( $in['attrs']['useMainQuery'] ) || ! empty( $in['attrs']['excludeCurrent'] ) ) {
			continue;
		}
		$type = isset( $in['attrs']['type'] ) ? $in['attrs']['type'] : 'zh_service';
		if ( ! zhdanov_core_type_on( $type ) ) {
			return '';
		}
		$c = wp_count_posts( $type );
		if ( ! $c || ! (int) $c->publish ) {
			return '';
		}
		if ( 'zh_promo' === $type && ! get_posts( array( 'post_type' => 'zh_promo', 'numberposts' => 1, 'fields' => 'ids', 'meta_query' => zhdanov_core_promo_meta_query() ) ) ) {
			return '';
		}
	}
	return $html;
}, 10, 2 );

/* ---------- Туры ---------- */
/** Будущие даты заезда: [['ts'=>, 'label'=>, 'full'=>bool]]. */
function zhdanov_core_tour_dates( $id ) {
	$out   = array();
	$today = wp_date( 'Y-m-d' );
	foreach ( preg_split( '/\r?\n/', zhdanov_core_m( $id, '_zh_dates' ) ) as $l ) {
		$l = trim( $l );
		if ( ! $l ) {
			continue;
		}
		$d = substr( zhdanov_import_date( $l ), 0, 10 );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) || $d < $today ) {
			continue;
		}
		$out[] = array( 'd' => $d, 'full' => (bool) preg_match( '/мест нет|нет мест|sold|full/ui', $l ), 'note' => trim( preg_replace( '/^[\d.\/-]+/', '', $l ) ) );
	}
	usort( $out, function ( $a, $b ) {
		return strcmp( $a['d'], $b['d'] );
	} );
	return $out;
}

function zhdanov_core_list_zh_tour( $posts, $hl, $use_main ) {
	$h = '<ul class="zh-items zh-tours" role="list">';
	foreach ( $posts as $p ) {
		$id    = $p->ID;
		$dates = zhdanov_core_tour_dates( $id );
		$h    .= '<li class="zh-item zh-tour">';
		$h    .= '<a class="zh-item__img" href="' . esc_url( get_permalink( $p ) ) . '" tabindex="-1" aria-hidden="true">' . ( has_post_thumbnail( $p ) ? get_the_post_thumbnail( $p, 'zhdanov-card', array( 'loading' => 'lazy', 'alt' => '' ) ) : '<span class="zh-tile__ph"></span>' ) . '</a>';
		$h    .= '<div class="zh-item__body">' . sprintf( '<%1$s class="zh-item__title"><a href="%2$s">%3$s</a></%1$s>', $hl, esc_url( get_permalink( $p ) ), esc_html( get_the_title( $p ) ) );
		$meta  = array_filter( array( zhdanov_core_m( $id, '_zh_duration' ), zhdanov_core_m( $id, '_zh_group' ) ) );
		if ( $meta ) {
			$h .= '<p class="zh-item__meta">' . esc_html( implode( ' · ', $meta ) ) . '</p>';
		}
		if ( $dates ) {
			$free = array_values( array_filter( $dates, function ( $x ) {
				return ! $x['full'];
			} ) );
			$h   .= '<p class="zh-item__meta">' . ( $free ? esc_html( zhdanov_core__( 'Ближайший заезд' ) . ': ' . wp_date( 'j F', strtotime( $free[0]['d'] . ' 12:00' ) ) ) : esc_html( zhdanov_core__( 'Мест нет на все даты' ) ) ) . '</p>';
		}
		$h .= '</div><p class="zh-item__side">';
		if ( zhdanov_core_m( $id, '_zh_price' ) ) {
			$h .= '<span class="zh-price zh-nw">' . esc_html( zhdanov_core_m( $id, '_zh_price' ) ) . '</span> ';
		}
		$h .= '<a class="zh-more" href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( zhdanov_core__( 'Программа' ) ) . '<span class="screen-reader-text">: ' . esc_html( get_the_title( $p ) ) . '</span></a></p></li>';
	}
	return $h . '</ul>';
}

function zhdanov_core_single_zh_tour( $id ) {
	$title = get_the_title( $id );
	$rows  = array_filter( array( zhdanov_core__( 'Цена' ) => zhdanov_core_m( $id, '_zh_price' ), zhdanov_core__( 'Длительность' ) => zhdanov_core_m( $id, '_zh_duration' ), zhdanov_core__( 'Группа' ) => zhdanov_core_m( $id, '_zh_group' ), zhdanov_core__( 'Сложность' ) => zhdanov_core_m( $id, '_zh_level' ) ) );
	$h     = '<dl class="zh-emeta__list">';
	foreach ( $rows as $l => $v ) {
		$h .= '<div><dt>' . esc_html( $l ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>';
	}
	$h    .= '</dl>';
	$dates = zhdanov_core_tour_dates( $id );
	if ( $dates ) {
		$h .= '<h2 class="zh-imeta__h">' . esc_html( zhdanov_core__( 'Даты заездов' ) ) . '</h2><ul class="zh-tour__dates" role="list">';
		foreach ( $dates as $d ) {
			$when = wp_date( 'j F Y', strtotime( $d['d'] . ' 12:00' ) );
			$h   .= '<li class="' . ( $d['full'] ? 'is-full' : '' ) . '"><span>' . esc_html( $when ) . ( $d['full'] ? ' · ' . esc_html( zhdanov_core__( 'мест нет' ) ) : '' ) . '</span>';
			if ( ! $d['full'] ) {
				$h .= zhdanov_core_book_button( 'Здравствуйте! Хочу в тур «' . $title . '», заезд ' . $when . '.', $title . ', ' . $when, 'Забронировать' );
			}
			$h .= '</li>';
		}
		$h .= '</ul>';
	}
	$days = array_filter( array_map( 'trim', preg_split( '/\r?\n/', zhdanov_core_m( $id, '_zh_days' ) ) ) );
	if ( $days ) {
		$h .= '<h2 class="zh-imeta__h">' . esc_html( zhdanov_core__( 'Программа по дням' ) ) . '</h2><ol class="zh-tour__days">';
		foreach ( $days as $i => $d ) {
			$parts = explode( ':', $d, 2 );
			$h    .= '<li><span class="zh-tour__n">' . esc_html( zhdanov_core__( 'День' ) . ' ' . ( $i + 1 ) ) . '</span><b>' . esc_html( trim( $parts[0] ) ) . '</b>' . ( isset( $parts[1] ) ? '<span>' . esc_html( trim( $parts[1] ) ) . '</span>' : '' ) . '</li>';
		}
		$h .= '</ol>';
	}
	foreach ( array( '_zh_incl' => 'Входит в стоимость', '_zh_excl' => 'Оплачивается отдельно' ) as $k => $l ) {
		$items = array_filter( array_map( 'trim', explode( ';', zhdanov_core_m( $id, $k ) ) ) );
		if ( $items ) {
			$h .= '<h2 class="zh-imeta__h">' . esc_html( zhdanov_core__( $l ) ) . '</h2><ul class="' . ( '_zh_incl' === $k ? 'is-style-zhdanov-checks' : 'zh-tour__excl' ) . '">';
			foreach ( $items as $it ) {
				$h .= '<li>' . esc_html( $it ) . '</li>';
			}
			$h .= '</ul>';
		}
	}
	return $h;
}

/* ---------- Залы и локации ---------- */
function zhdanov_core_list_zh_room( $posts, $hl, $use_main ) {
	$h = '<ul class="zh-items zh-rooms" role="list">';
	foreach ( $posts as $p ) {
		$id  = $p->ID;
		$h  .= '<li class="zh-item zh-room">';
		$h  .= '<a class="zh-item__img" href="' . esc_url( get_permalink( $p ) ) . '" tabindex="-1" aria-hidden="true">' . ( has_post_thumbnail( $p ) ? get_the_post_thumbnail( $p, 'zhdanov-card', array( 'loading' => 'lazy', 'alt' => '' ) ) : '<span class="zh-tile__ph"></span>' ) . '</a>';
		$h  .= '<div class="zh-item__body">' . sprintf( '<%1$s class="zh-item__title"><a href="%2$s">%3$s</a></%1$s>', $hl, esc_url( get_permalink( $p ) ), esc_html( get_the_title( $p ) ) );
		$meta = array_filter( array( zhdanov_core_m( $id, '_zh_area' ), zhdanov_core_m( $id, '_zh_capacity' ) ) );
		if ( $meta ) {
			$h .= '<p class="zh-item__meta">' . esc_html( implode( ' · ', $meta ) ) . '</p>';
		}
		$h .= '</div><p class="zh-item__side">';
		if ( zhdanov_core_m( $id, '_zh_price' ) ) {
			$h .= '<span class="zh-price zh-nw">' . esc_html( zhdanov_core_m( $id, '_zh_price' ) ) . '</span> ';
		}
		$book = zhdanov_core_m( $id, '_zh_book' );
		$h   .= $book ? '<a class="wp-element-button zh-btn-sm" href="' . esc_url( $book ) . '" target="_blank" rel="noopener" data-zh-goal="booking">' . esc_html( zhdanov_core__( 'Забронировать' ) ) . '<span class="screen-reader-text">: ' . esc_html( get_the_title( $p ) ) . '</span></a>'
			: zhdanov_core_book_button( 'Здравствуйте! Хочу забронировать «' . get_the_title( $p ) . '». Дата и время: ', get_the_title( $p ), 'Забронировать' );
		$h .= '</p></li>';
	}
	return $h . '</ul>';
}

function zhdanov_core_single_zh_room( $id ) {
	$h     = zhdanov_core_list_zh_room( array( get_post( $id ) ), 'h2', false );
	$items = array_filter( array_map( 'trim', explode( ';', zhdanov_core_m( $id, '_zh_features' ) ) ) );
	if ( $items ) {
		$h .= '<h2 class="zh-imeta__h">' . esc_html( zhdanov_core__( 'Что есть' ) ) . '</h2><ul class="is-style-zhdanov-checks">';
		foreach ( $items as $it ) {
			$h .= '<li>' . esc_html( $it ) . '</li>';
		}
		$h .= '</ul>';
	}
	return $h;
}

/* ---------- Книги ---------- */
function zhdanov_core_book_free( $id ) {
	$p = trim( mb_strtolower( zhdanov_core_m( $id, '_zh_price' ) ) );
	return '' === $p || 'бесплатно' === $p || '0' === $p || 'free' === $p;
}

function zhdanov_core_list_zh_book( $posts, $hl, $use_main ) {
	$h = '<ul class="zh-items zh-books" role="list">';
	foreach ( $posts as $p ) {
		$id = $p->ID;
		$h .= '<li class="zh-item zh-book">';
		$h .= '<a class="zh-book__cover" href="' . esc_url( get_permalink( $p ) ) . '" tabindex="-1" aria-hidden="true">' . ( has_post_thumbnail( $p ) ? get_the_post_thumbnail( $p, 'medium', array( 'loading' => 'lazy', 'alt' => '' ) ) : '<span class="zh-tile__ph"></span>' ) . '</a>';
		$h .= '<div class="zh-item__body">' . sprintf( '<%1$s class="zh-item__title"><a href="%2$s">%3$s</a></%1$s>', $hl, esc_url( get_permalink( $p ) ), esc_html( get_the_title( $p ) ) );
		$m  = array_filter( array( zhdanov_core_m( $id, '_zh_author' ), zhdanov_core_m( $id, '_zh_year' ) ) );
		if ( $m ) {
			$h .= '<p class="zh-item__meta">' . esc_html( implode( ', ', $m ) ) . '</p>';
		}
		$h .= '</div></li>';
	}
	return $h . '</ul>';
}

function zhdanov_core_single_zh_book( $id ) {
	$rows = array_filter( array( zhdanov_core__( 'Автор' ) => zhdanov_core_m( $id, '_zh_author' ), zhdanov_core__( 'Год' ) => zhdanov_core_m( $id, '_zh_year' ), zhdanov_core__( 'Страниц' ) => zhdanov_core_m( $id, '_zh_pages' ), 'ISBN' => zhdanov_core_m( $id, '_zh_isbn' ), zhdanov_core__( 'Цена' ) => zhdanov_core_book_free( $id ) ? '' : zhdanov_core_m( $id, '_zh_price' ) ) );
	$h    = '<dl class="zh-emeta__list">';
	foreach ( $rows as $l => $v ) {
		$h .= '<div><dt>' . esc_html( $l ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>';
	}
	$h  .= '</dl><p class="zh-book__btns">';
	$free = zhdanov_core_book_free( $id );
	foreach ( array( '_zh_pdf' => 'PDF', '_zh_epub' => 'EPUB' ) as $k => $l ) {
		if ( $free && zhdanov_core_m( $id, $k ) ) {
			$h .= '<a class="wp-element-button" href="' . esc_url( zhdanov_core_m( $id, $k ) ) . '" download data-zh-goal="download">' . esc_html( zhdanov_core__( 'Скачать' ) . ' ' . $l ) . '</a> ';
		}
	}
	if ( zhdanov_core_m( $id, '_zh_buy' ) ) {
		$h .= '<a class="zh-btn-ghost" href="' . esc_url( zhdanov_core_m( $id, '_zh_buy' ) ) . '" target="_blank" rel="noopener" data-zh-goal="order">' . esc_html( zhdanov_core__( 'Где купить' ) ) . '</a> ';
	}
	if ( ! $free && function_exists( 'zhdanov_pay_button_html' ) ) {
		$h .= zhdanov_pay_button_html( $id, 'p', zhdanov_pay_amount( zhdanov_core_m( $id, '_zh_price' ) ), get_the_title( $id ) );
	}
	return $h . '</p>';
}

/* Числовая цена для сортировки каталога: пишется сама при сохранении цены. */
function zhdanov_core_price_num_sync( $mid, $post_id, $key, $val ) {
	if ( '_zh_price' === $key ) {
		$n = (float) str_replace( array( ' ', ' ', ',' ), array( '', '', '.' ), preg_replace( '/[^\d,. ]/u', '', (string) $val ) );
		update_post_meta( $post_id, '_zh_price_num', $n );
	}
}
add_action( 'added_post_meta', 'zhdanov_core_price_num_sync', 10, 4 );
add_action( 'updated_post_meta', 'zhdanov_core_price_num_sync', 10, 4 );

add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() || ! ( $q->is_post_type_archive( 'zh_product' ) || $q->is_tax( 'zh_product_cat' ) ) ) {
		return;
	}
	$sort = isset( $_GET['sort'] ) ? sanitize_key( $_GET['sort'] ) : ''; // phpcs:ignore
	if ( in_array( $sort, array( 'deshevle', 'dorozhe' ), true ) ) {
		$q->set( 'meta_key', '_zh_price_num' );
		$q->set( 'orderby', 'meta_value_num' );
		$q->set( 'order', 'deshevle' === $sort ? 'ASC' : 'DESC' );
	}
	if ( ! empty( $_GET['nalichie'] ) ) { // phpcs:ignore
		$mq   = (array) $q->get( 'meta_query' );
		$mq[] = array( 'relation' => 'OR', array( 'key' => '_zh_stock', 'value' => 'out', 'compare' => '!=' ), array( 'key' => '_zh_stock', 'compare' => 'NOT EXISTS' ) );
		$q->set( 'meta_query', $mq );
	}
}, 40 );
add_action( 'init', function () {
	if ( get_option( 'zhdanov_price_num_v2' ) ) {
		return;
	}
	foreach ( get_posts( array( 'post_type' => array( 'zh_product', 'zh_estate' ), 'post_status' => 'any', 'numberposts' => 2000, 'fields' => 'ids' ) ) as $pid ) {
		zhdanov_core_price_num_sync( 0, $pid, '_zh_price', get_post_meta( $pid, '_zh_price', true ) );
	}
	update_option( 'zhdanov_price_num_v2', 1 );
}, 100 );
