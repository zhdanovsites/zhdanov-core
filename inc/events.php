<?php
/**
 * Афиша: тип записи «Событие». Дата, время, площадка, цена, возраст, статус, билеты.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zhdanov_core_events_on() {
	return (bool) zhdanov_core_opt( 'mod_events' );
}

function zhdanov_core_event_statuses() {
	return array(
		'scheduled' => 'По плану',
		'soldout'   => 'Билеты проданы',
		'postponed' => 'Перенесено',
		'cancelled' => 'Отменено',
	);
}

function zhdanov_core_event_meta_keys() {
	return array(
		'_zh_start'   => 'string', // 2026-11-20T19:00 (местное время сайта).
		'_zh_end'     => 'string',
		'_zh_venue'   => 'string',
		'_zh_address' => 'string',
		'_zh_price'   => 'string',
		'_zh_age'     => 'string',
		'_zh_status'  => 'string',
		'_zh_tickets' => 'string',
	);
}

function zhdanov_core_register_events() {
	if ( ! zhdanov_core_events_on() ) {
		return;
	}
	register_post_type(
		'zh_event',
		array(
			'labels'        => array(
				'name'               => 'Афиша',
				'singular_name'      => 'Событие',
				'add_new'            => 'Добавить событие',
				'add_new_item'       => 'Новое событие',
				'edit_item'          => 'Изменить событие',
				'new_item'           => 'Новое событие',
				'view_item'          => 'Открыть событие',
				'search_items'       => 'Найти событие',
				'not_found'          => 'Событий пока нет',
				'not_found_in_trash' => 'В корзине пусто',
				'all_items'          => 'Все события',
				'menu_name'          => 'Афиша',
			),
			'public'        => true,
			'has_archive'   => 'afisha',
			'rewrite'       => array( 'slug' => 'afisha', 'with_front' => false ),
			'menu_icon'     => 'dashicons-tickets-alt',
			'menu_position' => 5,
			'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions' ),
			'show_in_rest'  => true,
		)
	);
	register_taxonomy(
		'zh_event_cat',
		'zh_event',
		array(
			'labels'            => array(
				'name'          => 'Виды событий',
				'singular_name' => 'Вид события',
				'add_new_item'  => 'Добавить вид',
				'menu_name'     => 'Виды событий',
			),
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'afisha/vid', 'with_front' => false ),
		)
	);
	foreach ( zhdanov_core_event_meta_keys() as $key => $type ) {
		register_post_meta(
			'zh_event',
			$key,
			array(
				'type'          => $type,
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
}
add_action( 'init', 'zhdanov_core_register_events' );

/* ---------- Поля события в редакторе ---------- */

add_action( 'add_meta_boxes', function () {
	// Старый вид полей нужен только в классическом редакторе. В блочном поля справа (event-panel.js).
	add_meta_box( 'zh_event_box', 'Дата, место, билеты', 'zhdanov_core_event_box', 'zh_event', 'normal', 'high', array( '__back_compat_meta_box' => true ) );
} );

/* Поля Афиши в редакторе: общая панель (inc/types.php, assets/meta-panel.js). */

function zhdanov_core_event_box( $post ) {
	wp_nonce_field( 'zh_event_save', 'zh_event_nonce' );
	$m = array();
	foreach ( array_keys( zhdanov_core_event_meta_keys() ) as $k ) {
		$m[ $k ] = get_post_meta( $post->ID, $k, true );
	}
	$row = function ( $label, $html, $hint = '' ) {
		echo '<p style="margin:0 0 14px"><label style="display:block;font-weight:600;margin-bottom:4px">' . esc_html( $label ) . '</label>' . $html; // phpcs:ignore
		if ( $hint ) {
			echo '<span class="description" style="display:block">' . esc_html( $hint ) . '</span>';
		}
		echo '</p>';
	};
	echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:0 24px">';
	$row( 'Начало *', '<input type="datetime-local" name="zh[_zh_start]" value="' . esc_attr( $m['_zh_start'] ) . '" required>', 'Дата и время начала.' );
	$row( 'Окончание', '<input type="datetime-local" name="zh[_zh_end]" value="' . esc_attr( $m['_zh_end'] ) . '">', 'Необязательно.' );
	$row( 'Площадка', '<input class="widefat" type="text" name="zh[_zh_venue]" value="' . esc_attr( $m['_zh_venue'] ) . '">', 'Например: Большой зал.' );
	$row( 'Адрес площадки', '<input class="widefat" type="text" name="zh[_zh_address]" value="' . esc_attr( $m['_zh_address'] ) . '">', 'Если пусто: адрес из настроек.' );
	$row( 'Цена', '<input class="widefat" type="text" name="zh[_zh_price]" value="' . esc_attr( $m['_zh_price'] ) . '">', 'Например: от 1 500 ₽ или «Вход свободный».' );
	$ages = array( '', '0+', '6+', '12+', '16+', '18+' );
	$sel  = '<select name="zh[_zh_age]">';
	foreach ( $ages as $a ) {
		$sel .= '<option value="' . esc_attr( $a ) . '"' . selected( $m['_zh_age'], $a, false ) . '>' . ( $a ? esc_html( $a ) : '(не указан)' ) . '</option>';
	}
	$sel .= '</select>';
	$row( 'Возраст', $sel );
	$st = '<select name="zh[_zh_status]">';
	foreach ( zhdanov_core_event_statuses() as $v => $l ) {
		$st .= '<option value="' . esc_attr( $v ) . '"' . selected( $m['_zh_status'] ? $m['_zh_status'] : 'scheduled', $v, false ) . '>' . esc_html( $l ) . '</option>';
	}
	$st .= '</select>';
	$row( 'Статус', $st, '«Отменено» зачеркнёт событие и сообщит поисковикам.' );
	$row( 'Ссылка на билеты', '<input class="widefat" type="url" name="zh[_zh_tickets]" value="' . esc_attr( $m['_zh_tickets'] ) . '">', 'Касса или онлайн-запись.' );
	echo '</div>';
}

add_action( 'save_post_zh_event', function ( $post_id ) {
	if ( ! isset( $_POST['zh_event_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zh_event_nonce'] ) ), 'zh_event_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$in = isset( $_POST['zh'] ) ? (array) wp_unslash( $_POST['zh'] ) : array(); // phpcs:ignore
	foreach ( array_keys( zhdanov_core_event_meta_keys() ) as $k ) {
		$v = isset( $in[ $k ] ) ? $in[ $k ] : '';
		$v = '_zh_tickets' === $k ? esc_url_raw( $v ) : sanitize_text_field( $v );
		update_post_meta( $post_id, $k, $v );
	}
} );

/* Колонка «Дата» в списке событий. */
add_filter( 'manage_zh_event_posts_columns', function ( $c ) {
	$new = array();
	foreach ( $c as $k => $v ) {
		$new[ $k ] = $v;
		if ( 'title' === $k ) {
			$new['zh_start']  = 'Когда';
			$new['zh_status'] = 'Статус';
		}
	}
	unset( $new['date'] );
	return $new;
} );
add_action( 'manage_zh_event_posts_custom_column', function ( $col, $id ) {
	$e = zhdanov_core_event( $id );
	if ( 'zh_start' === $col ) {
		echo esc_html( $e['date_label'] . ( $e['time'] ? ', ' . $e['time'] : '' ) );
	}
	if ( 'zh_status' === $col ) {
		echo esc_html( $e['status_label'] );
	}
}, 10, 2 );
add_filter( 'manage_edit-zh_event_sortable_columns', function ( $c ) {
	$c['zh_start'] = 'zh_start';
	return $c;
} );

/* ---------- Данные события ---------- */

function zhdanov_core_event( $post = null ) {
	$post  = get_post( $post );
	$start = (string) get_post_meta( $post->ID, '_zh_start', true );
	$tz    = wp_timezone();
	$ts    = 0;
	if ( $start ) {
		$dt = date_create_immutable( $start, $tz );
		$ts = $dt ? $dt->getTimestamp() : 0;
	}
	$status   = get_post_meta( $post->ID, '_zh_status', true );
	$status   = $status ? $status : 'scheduled';
	$statuses = zhdanov_core_event_statuses();
	return array(
		'id'           => $post->ID,
		'title'        => get_the_title( $post ),
		'url'          => get_permalink( $post ),
		'start'        => $start,
		'end'          => (string) get_post_meta( $post->ID, '_zh_end', true ),
		'ts'           => $ts,
		'day'          => $ts ? wp_date( 'j', $ts ) : '',
		'month'        => $ts ? wp_date( 'M', $ts ) : '',
		'weekday'      => $ts ? wp_date( 'D', $ts ) : '',
		'time'         => ( $ts && false !== strpos( $start, 'T' ) ) ? wp_date( 'H:i', $ts ) : '',
		'date_label'   => $ts ? ( zhdanov_core_is_en() ? wp_date( 'l, j F Y', $ts ) : wp_date( 'j F Y', $ts ) . ', ' . mb_strtolower( wp_date( 'l', $ts ) ) ) : zhdanov_core__( 'Дата уточняется' ),
		'venue'        => (string) get_post_meta( $post->ID, '_zh_venue', true ),
		'address'      => (string) get_post_meta( $post->ID, '_zh_address', true ),
		'price'        => (string) get_post_meta( $post->ID, '_zh_price', true ),
		'age'          => (string) get_post_meta( $post->ID, '_zh_age', true ),
		'status'       => $status,
		'status_label' => isset( $statuses[ $status ] ) ? $statuses[ $status ] : '',
		'tickets'      => (string) get_post_meta( $post->ID, '_zh_tickets', true ),
		'past'         => $ts && $ts < time(),
	);
}

/* ---------- Порядок на странице «Афиша» ---------- */

add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() ) {
		if ( $q->is_main_query() && 'zh_event' === $q->get( 'post_type' ) && ! $q->get( 'orderby' ) ) {
			$q->set( 'orderby', 'zh_start' );
			$q->set( 'order', 'DESC' );
		}
		if ( $q->is_main_query() && 'zh_start' === $q->get( 'orderby' ) ) {
			$q->set( 'meta_key', '_zh_start' );
			$q->set( 'orderby', 'meta_value' );
		}
		return;
	}
	if ( ! $q->is_main_query() || ! ( $q->is_post_type_archive( 'zh_event' ) || $q->is_tax( 'zh_event_cat' ) ) ) {
		return;
	}
	zhdanov_core_apply_event_filters( $q );
} );

/** Фильтры афиши из адреса: ?mesyac=2026-11, ?proshedshie=1, ?s=поиск. */
function zhdanov_core_apply_event_filters( $q ) {
	$now   = wp_date( 'Y-m-d\T00:00' );
	$month = isset( $_GET['mesyac'] ) ? sanitize_text_field( wp_unslash( $_GET['mesyac'] ) ) : ''; // phpcs:ignore
	$past  = ! empty( $_GET['proshedshie'] ); // phpcs:ignore

	$q->set( 'post_type', 'zh_event' );
	$q->set( 'meta_key', '_zh_start' );
	$q->set( 'orderby', 'meta_value' );
	$q->set( 'posts_per_page', 12 );

	$mq = array();
	if ( preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
		$mq[] = array( 'key' => '_zh_start', 'value' => array( $month . '-01', $month . '-31T23:59' ), 'compare' => 'BETWEEN' );
		$q->set( 'order', 'ASC' );
	} elseif ( $past ) {
		$mq[] = array( 'key' => '_zh_start', 'value' => $now, 'compare' => '<' );
		$q->set( 'order', 'DESC' );
	} else {
		$mq[] = array( 'key' => '_zh_start', 'value' => $now, 'compare' => '>=' );
		$q->set( 'order', 'ASC' );
	}
	$q->set( 'meta_query', $mq );

	$search = isset( $_GET['poisk'] ) ? sanitize_text_field( wp_unslash( $_GET['poisk'] ) ) : ''; // phpcs:ignore
	if ( '' !== $search ) {
		$q->set( 's', $search );
		// Поиск по всем событиям, не только ближайшим.
		if ( ! $month && ! $past ) {
			$q->set( 'meta_query', array( array( 'key' => '_zh_start', 'compare' => 'EXISTS' ) ) );
		}
	}
}

/** Месяцы, в которых есть будущие события (для фильтра). */
function zhdanov_core_event_months() {
	global $wpdb;
	$now  = wp_date( 'Y-m' );
	$rows = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT LEFT(pm.meta_value,7) m FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE pm.meta_key='_zh_start' AND p.post_type='zh_event' AND p.post_status='publish' AND LEFT(pm.meta_value,7) >= %s ORDER BY m ASC LIMIT 18", $now ) ); // phpcs:ignore
	$out  = array();
	foreach ( $rows as $m ) {
		$ts        = strtotime( $m . '-01 12:00' );
		$out[ $m ] = wp_date( 'F Y', $ts );
	}
	return $out;
}

/* ---------- Шаблоны афиши: работают с любой блочной темой ---------- */

add_action( 'init', function () {
	if ( ! zhdanov_core_events_on() || ! function_exists( 'register_block_template' ) ) {
		return;
	}
	$head = '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->';
	$foot = '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->';
	$open = '<!-- wp:group {"tagName":"main","style":{"spacing":{"padding":{"top":"var:preset|spacing|40"}}},"layout":{"type":"constrained"}} --><main class="wp-block-group" style="padding-top:var(--wp--preset--spacing--40)">';
	$close = '</main><!-- /wp:group -->';

	register_block_template(
		'zhdanov-core//archive-zh_event',
		array(
			'title'       => 'Афиша: все события',
			'description' => 'Список событий с фильтром по месяцам и поиском.',
			'content'     => $head . $open . '<!-- wp:zhdanov/breadcrumbs {"align":"wide"} /--><!-- wp:query-title {"type":"archive","showPrefix":false,"align":"wide"} /--><!-- wp:zhdanov/events {"useMainQuery":true,"filters":true,"align":"wide"} /-->' . $close . $foot,
			'post_types'  => array(),
		)
	);
	register_block_template(
		'zhdanov-core//taxonomy-zh_event_cat',
		array(
			'title'       => 'Афиша: вид событий',
			'description' => 'События одного вида.',
			'content'     => $head . $open . '<!-- wp:zhdanov/breadcrumbs {"align":"wide"} /--><!-- wp:query-title {"type":"archive","showPrefix":false,"align":"wide"} /--><!-- wp:zhdanov/events {"useMainQuery":true,"filters":true,"align":"wide"} /-->' . $close . $foot,
		)
	);
	register_block_template(
		'zhdanov-core//single-zh_event',
		array(
			'title'       => 'Афиша: событие',
			'description' => 'Страница одного события.',
			'content'     => $head . $open . '<!-- wp:zhdanov/breadcrumbs {"align":"wide"} /--><!-- wp:post-title {"level":1} /--><!-- wp:zhdanov/event-meta /--><!-- wp:post-featured-image {"align":"wide","sizeSlug":"large"} /--><!-- wp:post-content {"layout":{"type":"constrained"}} /--><!-- wp:zhdanov/share /--><!-- wp:zhdanov/events {"count":3,"excludeCurrent":true,"showMoreLink":true,"heading":"Другие события","align":"wide"} /-->' . $close . $foot,
			'post_types'  => array( 'zh_event' ),
		)
	);
}, 20 );
