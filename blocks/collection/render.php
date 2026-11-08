<?php
/**
 * Блок «Раздел»: услуги, отзывы, альбомы, каталог.
 *
 * @var array $attributes
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
$type  = isset( $attributes['type'] ) ? $attributes['type'] : 'zh_service';
$types = zhdanov_core_types();
if ( empty( $types[ $type ] ) || ! zhdanov_core_type_on( $type ) ) {
	if ( wp_is_serving_rest_request() ) {
		echo '<p>Этот раздел выключен: Консоль → Жданов → Разделы сайта.</p>';
	}
	return;
}
$t        = $types[ $type ];
$use_main = ! empty( $attributes['useMainQuery'] );
if ( $use_main ) {
	global $wp_query;
	$q = $wp_query;
} else {
	$args = array(
		'post_type'      => $type,
		'posts_per_page' => in_array( $type, array( 'zh_class', 'zh_dish', 'zh_branch', 'zh_doc' ), true ) && empty( $attributes['excludeCurrent'] ) ? 200 : max( 1, (int) $attributes['count'] ),
		'no_found_rows'  => true,
		'orderby'        => 'menu_order' === $t['order'] ? array( 'menu_order' => 'ASC', 'title' => 'ASC' ) : 'date',
		'order'          => 'menu_order' === $t['order'] ? 'ASC' : 'DESC',
	);
	if ( ! empty( $attributes['excludeCurrent'] ) && get_the_ID() ) {
		$args['post__not_in'] = array( get_the_ID() );
	}
	if ( ! empty( $attributes['category'] ) && $t['tax'] ) {
		$args['tax_query'] = array( array( 'taxonomy' => $t['tax'][0], 'field' => 'slug', 'terms' => array_map( 'trim', explode( ',', $attributes['category'] ) ) ) );
	}
	$q = new WP_Query( $args );
}
$archive = get_post_type_archive_link( $type );
// «Похожие» без записей не показываем совсем.
if ( ! $use_main && ! empty( $attributes['excludeCurrent'] ) && ! $q->have_posts() ) {
	return;
}
$hl      = $use_main ? 'h2' : 'h3';
$layout  = $attributes['layout'];
if ( 'auto' === $layout ) {
	$layout = 'zh_service' === $type ? 'list' : 'grid';
}
if ( in_array( $type, array( 'zh_person', 'zh_dish', 'zh_branch', 'zh_promo', 'zh_tour', 'zh_room', 'zh_book', 'zh_job', 'zh_doc', 'zh_estate' ), true ) ) {
	$layout = 'own';
}

echo '<div ' . get_block_wrapper_attributes( array( 'class' => 'zh-col zh-col--' . esc_attr( str_replace( 'zh_', '', $type ) ) . ' zh-col--' . esc_attr( $layout ) ) ) . '>'; // phpcs:ignore
if ( ! empty( $attributes['heading'] ) ) {
	echo '<h2 class="zh-col__heading">' . esc_html( zhdanov_core__( $attributes['heading'] ) ) . '</h2>';
}

// Фильтр по группам/разделам.
if ( ! empty( $attributes['filters'] ) && $t['tax'] && 'zh_dish' !== $type ) {
	$terms = get_terms( array( 'taxonomy' => $t['tax'][0], 'hide_empty' => true ) );
	if ( $terms && ! is_wp_error( $terms ) && count( $terms ) > 1 ) {
		$cur = is_tax( $t['tax'][0] ) ? get_queried_object_id() : 0;
		echo '<nav class="zh-filter" aria-label="' . esc_attr( $t['tax'][1] ) . '">';
		printf( '<a class="zh-chip%s" href="%s"%s>Все</a>', $cur ? '' : ' is-active', esc_url( $archive ), $cur ? '' : ' aria-current="page"' );
		foreach ( $terms as $term ) {
			printf( '<a class="zh-chip%s" href="%s"%s>%s</a>', $cur === $term->term_id ? ' is-active' : '', esc_url( get_term_link( $term ) ), $cur === $term->term_id ? ' aria-current="page"' : '', esc_html( $term->name ) );
		}
		echo '</nav>';
	}
}

// Каталог: сортировка по цене и «в наличии» (на странице раздела).
if ( $use_main && 'zh_product' === $type ) {
	$base = remove_query_arg( array( 'sort', 'nalichie', 'paged' ) );
	$cur  = isset( $_GET['sort'] ) ? sanitize_key( $_GET['sort'] ) : ''; // phpcs:ignore
	$st   = ! empty( $_GET['nalichie'] ); // phpcs:ignore
	echo '<nav class="zh-filter zh-sort" aria-label="' . esc_attr( zhdanov_core__( 'Сортировка' ) ) . '">';
	foreach ( array( '' => 'Сначала новые', 'deshevle' => 'Дешевле', 'dorozhe' => 'Дороже' ) as $k => $l ) {
		$u = $k ? add_query_arg( 'sort', $k, $base ) : $base;
		if ( $st ) {
			$u = add_query_arg( 'nalichie', 1, $u );
		}
		printf( '<a class="zh-chip%s" href="%s"%s>%s</a>', $cur === $k ? ' is-active' : '', esc_url( $u ), $cur === $k ? ' aria-current="true"' : '', esc_html( zhdanov_core__( $l ) ) );
	}
	$u = $st ? remove_query_arg( 'nalichie' ) : add_query_arg( 'nalichie', 1 );
	printf( '<a class="zh-chip%s" href="%s"%s>%s%s</a>', $st ? ' is-active' : '', esc_url( $u ), $st ? ' aria-current="true"' : '', $st ? '✓ ' : '', esc_html( zhdanov_core__( 'Только в наличии' ) ) );
	echo '</nav>';
}

if ( function_exists( 'zhdanov_core_list_' . $type ) ) {
	if ( $q->have_posts() || ( $use_main && 'zh_estate' === $type ) ) {
		echo call_user_func( 'zhdanov_core_list_' . $type, $q->posts, $hl, $use_main ); // phpcs:ignore
	} else {
		echo '<p class="zh-empty">' . esc_html( zhdanov_core__( 'Здесь пока пусто.' ) ) . '</p>';
	}
	if ( ! $use_main && ! empty( $attributes['showMoreLink'] ) && $archive ) {
		echo '<p class="zh-events-more"><a class="wp-element-button" href="' . esc_url( $archive ) . '">' . esc_html( zhdanov_core__( 'Смотреть все' ) ) . '</a></p>';
	}
	echo '</div>';
	return;
}
if ( 'zh_class' === $type && 'nearest' === $attributes['layout'] ) {
	echo zhdanov_core_nearest_html( $q->posts, max( 1, (int) $attributes['count'] ) ); // phpcs:ignore
	if ( ! empty( $attributes['showMoreLink'] ) && $archive ) {
		echo '<p class="zh-events-more"><a class="wp-element-button" href="' . esc_url( $archive ) . '">' . esc_html( zhdanov_core__( 'Всё расписание' ) ) . '</a></p>';
	}
	echo '</div>';
	return;
}
if ( 'zh_class' === $type && $use_main && function_exists( 'zhdanov_booking_on' ) && zhdanov_booking_on() ) {
	echo zhdanov_booking_html( 0, 'Запишитесь онлайн' ); // phpcs:ignore
}
if ( 'zh_class' === $type ) {
	echo zhdanov_core_schedule_html( $q->posts, $use_main ? 'h2' : 'h3' ); // phpcs:ignore
	if ( ! $use_main && ! empty( $attributes['showMoreLink'] ) && $archive ) {
		echo '<p class="zh-events-more"><a class="wp-element-button" href="' . esc_url( $archive ) . '">' . esc_html( zhdanov_core__( 'Всё расписание' ) ) . '</a></p>';
	}
	echo '</div>';
	return;
}
if ( $q->have_posts() ) {
	echo '<ul class="zh-items" role="list">';
	while ( $q->have_posts() ) {
		$q->the_post();
		$id  = get_the_ID();
		$url = get_permalink();
		$tt  = get_the_title();
		echo '<li class="zh-item">';
		switch ( $type ) {
			case 'zh_service':
				$price = zhdanov_core_m( $id, '_zh_price' );
				$old   = zhdanov_core_m( $id, '_zh_price_old' );
				$dur   = zhdanov_core_m( $id, '_zh_duration' );
				$book  = zhdanov_core_m( $id, '_zh_book' );
				if ( 'grid' === $layout && has_post_thumbnail() ) {
					echo '<a class="zh-item__img" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . get_the_post_thumbnail( null, 'zhdanov-card', array( 'loading' => 'lazy', 'alt' => '' ) ) . '</a>'; // phpcs:ignore
				}
				echo '<div class="zh-item__body">';
				printf( '<%1$s class="zh-item__title"><a href="%2$s">%3$s</a></%1$s>', $hl, esc_url( $url ), esc_html( $tt ) ); // phpcs:ignore
				if ( has_excerpt() ) {
					echo '<p class="zh-item__text">' . esc_html( get_the_excerpt() ) . '</p>';
				}
				if ( $dur ) {
					echo '<p class="zh-item__meta">' . esc_html( $dur ) . '</p>';
				}
				echo '</div><div class="zh-item__side">';
				if ( $price ) {
					echo '<p class="zh-price">' . ( $old ? '<s class="zh-price__old"><span class="screen-reader-text">Было: </span>' . esc_html( $old ) . '</s> ' : '' ) . '<span class="zh-price__now">' . esc_html( $price ) . '</span></p>';
				}
				$href = $book ? $book : zhdanov_core_fallback_action();
				printf( '<a class="wp-element-button zh-btn-sm" href="%s"%s data-zh-goal="booking">Записаться<span class="screen-reader-text">: %s</span></a>', esc_url( $href ), 0 === strpos( $href, 'http' ) ? ' target="_blank" rel="noopener"' : '', esc_html( $tt ) );
				echo '</div>';
				break;

			case 'zh_course':
				$tar   = zhdanov_core_course_tariffs( $id );
				$start = zhdanov_core_m( $id, '_zh_start' );
				if ( has_post_thumbnail() ) {
					echo '<a class="zh-item__img" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . get_the_post_thumbnail( null, 'zhdanov-card', array( 'loading' => 'lazy', 'alt' => '' ) ) . '</a>'; // phpcs:ignore
				}
				echo '<div class="zh-item__body">';
				echo '<p class="zh-item__meta zh-course__start">' . esc_html( $start ? zhdanov_core__( 'Старт' ) . ' ' . wp_date( 'j F', strtotime( $start . ' 12:00' ) ) : zhdanov_core__( 'Старт по набору группы' ) ) . '</p>';
				printf( '<%1$s class="zh-item__title"><a href="%2$s">%3$s</a></%1$s>', $hl, esc_url( $url ), esc_html( $tt ) ); // phpcs:ignore
				$info = array_filter( array( zhdanov_core_m( $id, '_zh_duration' ), zhdanov_core_m( $id, '_zh_format' ) ) );
				if ( $info ) {
					echo '<p class="zh-item__meta">' . esc_html( implode( ' · ', $info ) ) . '</p>';
				}
				if ( $tar ) {
					echo '<p class="zh-price">' . esc_html( ( count( $tar ) > 1 ? zhdanov_core__( 'от' ) . ' ' : '' ) . $tar[0]['price'] ) . '</p>';
					if ( $tar[0]['until'] ) {
						echo '<p class="zh-course__until">' . esc_html( sprintf( zhdanov_core__( 'Цена до %s' ), wp_date( 'j F', strtotime( $tar[0]['until'] . ' 12:00' ) ) ) ) . '</p>';
					}
				}
				echo '</div>';
				break;

			case 'zh_review':
				$author = zhdanov_core_m( $id, '_zh_author' );
				$rating = (int) zhdanov_core_m( $id, '_zh_rating' );
				$src    = zhdanov_core_m( $id, '_zh_source' );
				$srcu   = zhdanov_core_m( $id, '_zh_source_url' );
				echo '<figure class="zh-review">';
				if ( $rating ) {
					echo '<p class="zh-stars" aria-label="Оценка ' . (int) $rating . ' из 5">' . str_repeat( '★', $rating ) . '<span class="zh-stars__off">' . str_repeat( '★', 5 - $rating ) . '</span></p>'; // phpcs:ignore
				}
				echo '<blockquote class="zh-review__text">' . wp_kses_post( wpautop( wp_trim_words( wp_strip_all_tags( get_the_content() ), 70, '…' ) ) ) . '</blockquote>';
				// Фото к отзыву (из галереи в тексте) и видео-отзыв по клику.
				$rimgs = get_attached_media( 'image', $id );
				if ( ! $rimgs && preg_match_all( '/wp-image-(\d+)/', get_post_field( 'post_content', $id ), $rm ) ) {
					$rimgs = array_map( 'get_post', array_slice( array_unique( $rm[1] ), 0, 4 ) );
				}
				$rimgs = array_filter( (array) $rimgs, function ( $a ) use ( $id ) {
					return $a && (int) get_post_thumbnail_id( $id ) !== (int) $a->ID;
				} );
				if ( $rimgs ) {
					echo '<p class="zh-review__photos">';
					foreach ( array_slice( $rimgs, 0, 4 ) as $ri ) {
						echo '<a href="' . esc_url( wp_get_attachment_url( $ri->ID ) ) . '" target="_blank">' . wp_get_attachment_image( $ri->ID, 'thumbnail', false, array( 'loading' => 'lazy', 'alt' => esc_attr( zhdanov_core__( 'Фото к отзыву' ) ) ) ) . '</a>'; // phpcs:ignore
					}
					echo '</p>';
				}
				$rv = zhdanov_core_video_embed( zhdanov_core_m( $id, '_zh_video' ) );
				if ( $rv ) {
					echo '<div class="zh-video zh-video--review no-poster" data-embed="' . esc_url( $rv['embed'] ) . '"><button type="button" class="zh-video__btn"><span class="zh-video__play" aria-hidden="true"></span><span class="zh-video__title">' . esc_html( zhdanov_core__( 'Видео-отзыв' ) ) . '</span></button><p class="zh-video__note">' . esc_html( zhdanov_core__( 'Видео загрузится с сервиса' ) . ' ' . $rv['service'] ) . '</p></div>';
				}
				echo '<figcaption class="zh-review__who">';
				if ( has_post_thumbnail() ) {
					echo get_the_post_thumbnail( null, 'thumbnail', array( 'class' => 'zh-review__ava', 'loading' => 'lazy', 'alt' => '' ) ); // phpcs:ignore
				}
				echo '<span><strong>' . esc_html( $author ? $author : $tt ) . '</strong>';
				if ( zhdanov_core_m( $id, '_zh_role' ) ) {
					echo '<br><span class="zh-review__role">' . esc_html( zhdanov_core_m( $id, '_zh_role' ) ) . '</span>';
				}
				if ( $src ) {
					echo '<br><span class="zh-review__src">' . ( $srcu ? 'Отзыв на <a href="' . esc_url( $srcu ) . '" target="_blank" rel="noopener nofollow">' . esc_html( $src ) . '</a>' : 'Отзыв: ' . esc_html( $src ) ) . '</span>';
				}
				echo '</span></figcaption></figure>';
				break;

			case 'zh_album':
				echo '<a class="zh-tile" href="' . esc_url( $url ) . '">';
				echo has_post_thumbnail() ? get_the_post_thumbnail( null, 'zhdanov-card', array( 'loading' => 'lazy', 'alt' => '' ) ) : '<span class="zh-tile__ph" aria-hidden="true"></span>'; // phpcs:ignore
				printf( '<%1$s class="zh-tile__title">%2$s</%1$s>', $hl, esc_html( $tt ) ); // phpcs:ignore
				if ( zhdanov_core_m( $id, '_zh_when' ) ) {
					echo '<span class="zh-tile__meta">' . esc_html( zhdanov_core_m( $id, '_zh_when' ) ) . '</span>';
				}
				echo '</a>';
				break;

			case 'zh_product':
				$price = zhdanov_core_m( $id, '_zh_price' );
				$old   = zhdanov_core_m( $id, '_zh_price_old' );
				$stock = zhdanov_core_m( $id, '_zh_stock' );
				echo '<a class="zh-item__img" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . ( has_post_thumbnail() ? get_the_post_thumbnail( null, 'zhdanov-card', array( 'loading' => 'lazy', 'alt' => '' ) ) : '<span class="zh-tile__ph"></span>' ) . '</a>'; // phpcs:ignore
				echo '<div class="zh-item__body">';
				printf( '<%1$s class="zh-item__title"><a href="%2$s">%3$s</a></%1$s>', $hl, esc_url( $url ), esc_html( $tt ) ); // phpcs:ignore
				if ( $price ) {
					echo '<p class="zh-price">' . ( $old ? '<s class="zh-price__old"><span class="screen-reader-text">Было: </span>' . esc_html( $old ) . '</s> ' : '' ) . '<span class="zh-price__now">' . esc_html( $price ) . '</span></p>';
				}
				if ( 'in' !== $stock && $stock ) {
					echo '<p class="zh-stock zh-stock--' . esc_attr( $stock ) . '">' . esc_html( zhdanov_core_stock_label( $stock ) ) . '</p>';
				}
				echo '</div>';
				break;
		}
		echo '</li>';
	}
	echo '</ul>';
	if ( $use_main && $q->max_num_pages > 1 ) {
		echo '<nav class="zh-pages" aria-label="Страницы">' . paginate_links( array( 'total' => $q->max_num_pages, 'prev_text' => '← Назад', 'next_text' => 'Дальше →' ) ) . '</nav>'; // phpcs:ignore
	}
} else {
	echo '<p class="zh-empty">Здесь пока пусто.</p>';
}
if ( ! $use_main ) {
	wp_reset_postdata();
	if ( ! empty( $attributes['showMoreLink'] ) && $archive ) {
		$more = array( 'zh_course' => 'Все программы', 'zh_service' => 'Все услуги и цены', 'zh_review' => 'Все отзывы', 'zh_album' => 'Все альбомы', 'zh_product' => 'Весь каталог' );
		echo '<p class="zh-events-more"><a class="wp-element-button" href="' . esc_url( $archive ) . '">' . esc_html( $more[ $type ] ) . '</a></p>';
	}
}
echo '</div>';
