<?php
/**
 * Блок «Афиша: список событий».
 *
 * @var array $attributes
 * @var WP_Block $block
 */
if ( ! defined( 'ABSPATH' ) || ! zhdanov_core_events_on() ) {
	return;
}
$use_main = ! empty( $attributes['useMainQuery'] );
if ( $use_main ) {
	global $wp_query;
	$q = $wp_query;
} else {
	$args = array(
		'post_type'      => 'zh_event',
		'posts_per_page' => max( 1, (int) $attributes['count'] ),
		'meta_key'       => '_zh_start',
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
		'no_found_rows'  => true,
		'meta_query'     => array( array( 'key' => '_zh_start', 'value' => wp_date( 'Y-m-d\T00:00' ), 'compare' => '>=' ) ),
	);
	if ( ! empty( $attributes['excludeCurrent'] ) && get_the_ID() ) {
		$args['post__not_in'] = array( get_the_ID() );
	}
	if ( ! empty( $attributes['category'] ) ) {
		$args['tax_query'] = array( array( 'taxonomy' => 'zh_event_cat', 'field' => 'slug', 'terms' => $attributes['category'] ) );
	}
	$q = new WP_Query( $args );
}
$archive = get_post_type_archive_link( 'zh_event' );
if ( ! $use_main && ! empty( $attributes['excludeCurrent'] ) && ! $q->have_posts() ) {
	return;
}
$hl      = $use_main ? 'h2' : 'h3';

$wrapper = get_block_wrapper_attributes( array( 'class' => 'zh-events-wrap' ) );
echo '<div ' . $wrapper . '>'; // phpcs:ignore
if ( ! empty( $attributes['heading'] ) ) {
	echo '<h2 class="zh-col__heading">' . esc_html( zhdanov_core__( $attributes['heading'] ) ) . '</h2>';
}

if ( ! empty( $attributes['filters'] ) ) {
	$cur_m   = isset( $_GET['mesyac'] ) ? sanitize_text_field( wp_unslash( $_GET['mesyac'] ) ) : ''; // phpcs:ignore
	$is_past = ! empty( $_GET['proshedshie'] ); // phpcs:ignore
	$search  = isset( $_GET['poisk'] ) ? sanitize_text_field( wp_unslash( $_GET['poisk'] ) ) : ''; // phpcs:ignore
	$base    = is_tax( 'zh_event_cat' ) ? get_term_link( get_queried_object() ) : $archive;
	echo '<nav class="zh-filter" aria-label="Фильтр афиши">';
	printf( '<a class="zh-chip%s" href="%s"%s>Ближайшие</a>', ( ! $cur_m && ! $is_past ) ? ' is-active' : '', esc_url( $base ), ( ! $cur_m && ! $is_past ) ? ' aria-current="page"' : '' );
	foreach ( zhdanov_core_event_months() as $m => $label ) {
		printf( '<a class="zh-chip%s" href="%s"%s>%s</a>', $cur_m === $m ? ' is-active' : '', esc_url( add_query_arg( 'mesyac', $m, $base ) ), $cur_m === $m ? ' aria-current="page"' : '', esc_html( $label ) );
	}
	printf( '<a class="zh-chip%s" href="%s"%s>Прошедшие</a>', $is_past ? ' is-active' : '', esc_url( add_query_arg( 'proshedshie', 1, $base ) ), $is_past ? ' aria-current="page"' : '' );
	echo '</nav>';
	printf(
		'<form class="zh-search" role="search" method="get" action="%1$s"><label class="screen-reader-text" for="zh-poisk">Поиск по афише</label><input id="zh-poisk" type="search" name="poisk" value="%2$s" placeholder="Поиск по афише"><button type="submit" class="wp-element-button">Найти</button></form>',
		esc_url( $base ),
		esc_attr( $search )
	);
}

if ( $q->have_posts() ) {
	echo '<ul class="zh-events" role="list">';
	while ( $q->have_posts() ) {
		$q->the_post();
		$e    = zhdanov_core_event();
		$cls  = 'zh-event is-' . $e['status'] . ( $e['past'] ? ' is-past' : '' );
		$meta = array_filter( array( esc_html( $e['venue'] ), $e['price'] ? '<span class="zh-nw">' . esc_html( $e['price'] ) . '</span>' : '' ) );
		echo '<li class="' . esc_attr( $cls ) . '">';
		echo '<div class="zh-event__date" aria-hidden="true"><span class="zh-event__d">' . esc_html( $e['day'] ) . '</span><span class="zh-event__m">' . esc_html( $e['month'] ) . '</span><span class="zh-event__w">' . esc_html( trim( $e['weekday'] . ( $e['time'] ? ' · ' . $e['time'] : '' ) ) ) . '</span></div>';
		echo '<div class="zh-event__body">';
		echo '<p class="screen-reader-text">' . esc_html( $e['date_label'] . ( $e['time'] ? ', ' . $e['time'] : '' ) ) . '</p>';
		printf( '<%1$s class="zh-event__title"><a href="%2$s">%3$s</a></%1$s>', $hl, esc_url( $e['url'] ), esc_html( $e['title'] ) ); // phpcs:ignore
		if ( $meta || $e['age'] ) {
			echo '<p class="zh-event__meta">' . implode( ' · ', $meta ); // phpcs:ignore
			if ( $e['age'] ) {
				echo ' <span class="zh-badge">' . esc_html( $e['age'] ) . '</span>';
			}
			echo '</p>';
		}
		if ( 'scheduled' !== $e['status'] ) {
			echo '<p class="zh-status zh-status--' . esc_attr( $e['status'] ) . '">' . esc_html( $e['status_label'] ) . '</p>';
		}
		echo '</div><div class="zh-event__act">';
		if ( $e['tickets'] && 'scheduled' === $e['status'] && ! $e['past'] ) {
			printf( '<a class="wp-element-button zh-btn-sm" href="%s" target="_blank" rel="noopener" data-zh-goal="tickets">Билеты<span class="screen-reader-text">: %s</span></a>', esc_url( $e['tickets'] ), esc_html( $e['title'] ) );
		} else {
			printf( '<a class="zh-more" href="%s">Подробнее<span class="screen-reader-text">: %s</span></a>', esc_url( $e['url'] ), esc_html( $e['title'] ) );
		}
		echo '</div></li>';
	}
	echo '</ul>';
	if ( $use_main && $q->max_num_pages > 1 ) {
		echo '<nav class="zh-pages" aria-label="Страницы афиши">' . paginate_links( array( 'total' => $q->max_num_pages, 'prev_text' => '← Назад', 'next_text' => 'Дальше →' ) ) . '</nav>'; // phpcs:ignore
	}
} else {
	echo '<p class="zh-empty">Пока нет событий. Загляните позже или посмотрите <a href="' . esc_url( add_query_arg( 'proshedshie', 1, $archive ) ) . '">прошедшие</a>.</p>';
}
if ( ! $use_main ) {
	wp_reset_postdata();
	if ( ! empty( $attributes['showMoreLink'] ) && $archive ) {
		echo '<p class="zh-events-more"><a class="wp-element-button" href="' . esc_url( $archive ) . '">Вся афиша</a></p>';
	}
}
echo '</div>';
