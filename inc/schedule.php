<?php
/**
 * Расписание: занятия по дням недели (и разовые по дате) + кнопка «Записаться» с готовым текстом.
 * Кнопка: своя ссылка на онлайн-запись → она; иначе текст подставляется в форму заявки на странице,
 * а если формы нет: WhatsApp получает текст сразу, для Telegram/MAX текст копируется и открывается чат.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zhdanov_core_weekdays() {
	return array( 1 => 'Понедельник', 2 => 'Вторник', 3 => 'Среда', 4 => 'Четверг', 5 => 'Пятница', 6 => 'Суббота', 7 => 'Воскресенье' );
}

/* На странице «Расписание» показываем все занятия сразу (до 200), сортировка в PHP по дню и времени. */
add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() && $q->is_main_query() && ( $q->is_post_type_archive( 'zh_class' ) || $q->is_tax( 'zh_class_cat' ) ) ) {
		$q->set( 'posts_per_page', 200 );
	}
}, 20 );

/** HTML расписания из списка записей. */
function zhdanov_core_schedule_html( $posts, $heading_level = 'h3' ) {
	$days  = zhdanov_core_weekdays();
	$week  = array();
	$dated = array();
	$today = wp_date( 'Y-m-d' );
	foreach ( $posts as $p ) {
		$id   = $p->ID;
		$day  = (int) zhdanov_core_m( $id, '_zh_day' );
		$date = zhdanov_core_m( $id, '_zh_date' );
		$row  = array( 'post' => $p, 'time' => zhdanov_core_m( $id, '_zh_time' ) );
		if ( $day >= 1 && $day <= 7 ) {
			$week[ $day ][] = $row;
		} elseif ( $date && $date >= $today ) {
			$dated[ $date ][] = $row;
		}
	}
	if ( ! $week && ! $dated ) {
		return '<p class="zh-empty">' . esc_html( zhdanov_core__( 'Расписание скоро появится.' ) ) . '</p>';
	}
	ksort( $week );
	ksort( $dated );
	$sort = function ( $a, $b ) {
		return strcmp( $a['time'], $b['time'] );
	};
	$html = '<div class="zh-sched">';
	$now  = (int) wp_date( 'N' );
	foreach ( $week as $d => $rows ) {
		usort( $rows, $sort );
		$html .= '<section class="zh-sched__day' . ( $d === $now ? ' is-today' : '' ) . '"><' . $heading_level . ' class="zh-sched__title">' . esc_html( $days[ $d ] ) . ( $d === $now ? ' <span class="zh-badge">' . esc_html( zhdanov_core__( 'сегодня' ) ) . '</span>' : '' ) . '</' . $heading_level . '><ul role="list">';
		foreach ( $rows as $r ) {
			$html .= zhdanov_core_schedule_row( $r['post'], $days[ $d ] );
		}
		$html .= '</ul></section>';
	}
	foreach ( $dated as $date => $rows ) {
		usort( $rows, $sort );
		$ts    = strtotime( $date . ' 12:00' );
		$label = wp_date( 'j F, l', $ts );
		$html .= '<section class="zh-sched__day"><' . $heading_level . ' class="zh-sched__title">' . esc_html( $label ) . '</' . $heading_level . '><ul role="list">';
		foreach ( $rows as $r ) {
			$html .= zhdanov_core_schedule_row( $r['post'], wp_date( 'j F', $ts ) );
		}
		$html .= '</ul></section>';
	}
	return $html . '</div>';
}

function zhdanov_core_schedule_row( $p, $when ) {
	$id    = $p->ID;
	$time  = zhdanov_core_m( $id, '_zh_time' );
	$meta  = array_filter( array( zhdanov_core_m( $id, '_zh_who' ), zhdanov_core_m( $id, '_zh_place' ), zhdanov_core_m( $id, '_zh_duration' ) ) );
	$price = zhdanov_core_m( $id, '_zh_price' );
	$seats = zhdanov_core_m( $id, '_zh_seats' );
	$full  = $seats && preg_match( '/нет мест|мест нет|запись закрыта|no seats|full/ui', $seats );
	$title = get_the_title( $p );
	$text  = 'Здравствуйте! Хочу записаться: ' . $title . ', ' . mb_strtolower( $when ) . ( $time ? ' ' . $time : '' ) . '.';

	$out  = '<li class="zh-sched__row' . ( $full ? ' is-full' : '' ) . '">';
	$out .= '<span class="zh-sched__time">' . esc_html( $time ? $time : '-' ) . '</span>';
	$out .= '<span class="zh-sched__body"><a class="zh-sched__name" href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( $title ) . '</a>';
	if ( $meta ) {
		$out .= '<span class="zh-sched__meta">' . esc_html( implode( ' · ', $meta ) ) . '</span>';
	}
	if ( $seats ) {
		$out .= '<span class="zh-sched__seats">' . esc_html( $seats ) . '</span>';
	}
	$out .= '</span><span class="zh-sched__side">';
	if ( $price ) {
		$out .= '<span class="zh-price zh-nw">' . esc_html( $price ) . '</span>';
	}
	if ( ! $full ) {
		$book = zhdanov_core_m( $id, '_zh_book' );
		if ( $book ) {
			$out .= '<a class="wp-element-button zh-btn-sm" href="' . esc_url( $book ) . '" target="_blank" rel="noopener" data-zh-goal="booking">' . esc_html( zhdanov_core__( 'Записаться' ) ) . '<span class="screen-reader-text">: ' . esc_html( $title . ', ' . $when . ' ' . $time ) . '</span></a>';
		} else {
			$out .= zhdanov_core_book_button( $text, $title . ', ' . $when . ' ' . $time );
		}
	}
	return $out . '</span></li>';
}

/** Кнопка «Записаться» с готовым текстом (используется и в услугах, специалистах, курсах). */
function zhdanov_core_book_button( $text, $sr = '', $label = 'Записаться' ) {
	$w = zhdanov_core_opt( 'book_widget' );
	if ( $w && 'Записаться' === $label ) {
		return '<a class="wp-element-button zh-btn-sm zh-book" href="' . esc_url( $w ) . '" target="_blank" rel="noopener" data-zh-widget="1" data-zh-goal="booking">' . esc_html( zhdanov_core__( $label ) ) . ( $sr ? '<span class="screen-reader-text">: ' . esc_html( $sr ) . '</span>' : '' ) . '</a>';
	}
	$c    = zhdanov_core_contacts();
	$href = '#zayavka';
	foreach ( array( 'whatsapp', 'max', 'telegram', 'phone' ) as $k ) {
		if ( ! empty( $c[ $k ] ) ) {
			$href = $c[ $k ]['url'];
			break;
		}
	}
	return '<a class="wp-element-button zh-btn-sm zh-book" href="' . esc_url( $href ) . '" data-zh-book="' . esc_attr( $text ) . '" data-zh-goal="booking">' . esc_html( zhdanov_core__( $label ) ) . ( $sr ? '<span class="screen-reader-text">: ' . esc_html( $sr ) . '</span>' : '' ) . '</a>';
}

/** Ближайшие свободные окна: следующие N занятий от текущего момента, без «мест нет». */
function zhdanov_core_nearest_html( $posts, $n = 4 ) {
	$tz    = wp_timezone();
	$now   = new DateTimeImmutable( 'now', $tz );
	$slots = array();
	foreach ( $posts as $p ) {
		$id    = $p->ID;
		$seats = zhdanov_core_m( $id, '_zh_seats' );
		if ( $seats && preg_match( '/нет мест|мест нет|запись закрыта|no seats|full/ui', $seats ) ) {
			continue;
		}
		$time = zhdanov_core_m( $id, '_zh_time' );
		$time = preg_match( '/^\d{1,2}:\d{2}$/', $time ) ? $time : '00:00';
		$day  = (int) zhdanov_core_m( $id, '_zh_day' );
		$cand = array();
		if ( $day >= 1 && $day <= 7 ) {
			for ( $i = 0; $i < 14; $i++ ) {
				$d = $now->modify( '+' . $i . ' day' );
				if ( (int) $d->format( 'N' ) === $day ) {
					$cand[] = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $d->format( 'Y-m-d' ) . ' ' . $time, $tz );
				}
			}
		} elseif ( zhdanov_core_m( $id, '_zh_date' ) ) {
			$cand[] = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', zhdanov_core_m( $id, '_zh_date' ) . ' ' . $time, $tz );
		}
		foreach ( $cand as $c ) {
			if ( $c && $c > $now ) {
				$slots[] = array( 'ts' => $c->getTimestamp(), 'post' => $p, 'time' => $time );
			}
		}
	}
	if ( ! $slots ) {
		return '<p class="zh-empty">' . esc_html( zhdanov_core__( 'Ближайших свободных окон нет. Напишите нам: подберём время.' ) ) . '</p>';
	}
	usort( $slots, function ( $a, $b ) { return $a['ts'] - $b['ts']; } );
	$today = $now->format( 'Y-m-d' );
	$tom   = $now->modify( '+1 day' )->format( 'Y-m-d' );
	$h     = '<ul class="zh-nearest" role="list">';
	foreach ( array_slice( $slots, 0, $n ) as $s ) {
		$dk    = wp_date( 'Y-m-d', $s['ts'] );
		$when  = $dk === $today ? zhdanov_core__( 'Сегодня' ) : ( $dk === $tom ? zhdanov_core__( 'Завтра' ) : wp_date( 'l, j F', $s['ts'] ) );
		$title = get_the_title( $s['post'] );
		$h    .= '<li class="zh-nearest__it"><span class="zh-nearest__when"><span class="zh-nearest__day">' . esc_html( mb_strtoupper( mb_substr( $when, 0, 1 ) ) . mb_substr( $when, 1 ) ) . '</span> <span class="zh-nearest__time">' . esc_html( $s['time'] ) . '</span></span>';
		$h    .= '<a class="zh-nearest__name" href="' . esc_url( get_permalink( $s['post'] ) ) . '">' . esc_html( $title ) . '</a>';
		$cap   = function_exists( 'zhdanov_booking_on' ) && zhdanov_booking_on() ? (int) zhdanov_core_m( $s['post']->ID, '_zh_cap' ) : 0;
		if ( $cap && zhdanov_booking_taken( $s['post']->ID, wp_date( 'Y-m-d', $s['ts'] ) ) >= $cap ) {
			$h .= '<small>' . esc_html( zhdanov_core__( 'мест нет' ) ) . '</small></li>';
			continue;
		}
		$h    .= $cap ? '<button type="button" class="wp-element-button zh-btn-sm zh-book" data-zh-slot="' . esc_attr( wp_json_encode( array( 'class' => $s['post']->ID, 'date' => wp_date( 'Y-m-d', $s['ts'] ), 'label' => $title . ', ' . zhdanov_booking_day( $s['ts'] ) . ', ' . $s['time'] ), JSON_UNESCAPED_UNICODE ) ) . '">' . esc_html( zhdanov_core__( 'Записаться' ) ) . '<span class="screen-reader-text">: ' . esc_html( $title . ', ' . $when . ' ' . $s['time'] ) . '</span></button></li>'
			: zhdanov_core_book_button( 'Здравствуйте! Хочу записаться: ' . $title . ', ' . mb_strtolower( $when ) . ' ' . $s['time'] . '.', $title . ', ' . $when . ' ' . $s['time'] ) . '</li>';
	}
	return $h . '</ul>';
}
