<?php
/**
 * Закон и удобство: реквизиты продавца, оферта, медицинская плашка,
 * свои названия разделов, «Спросить про это», онлайн-запись по клику,
 * подарок за заявку, заявки в Telegram.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Свои названия разделов ---------- */
add_filter( 'register_post_type_args', function ( $args, $type ) {
	if ( 0 !== strpos( $type, 'zh_' ) || ! function_exists( 'zhdanov_core_opt' ) ) {
		return $args;
	}
	$name = trim( (string) zhdanov_core_opt( 'label_' . $type ) );
	if ( $name ) {
		$args['labels']['name']      = $name;
		$args['labels']['menu_name'] = $name;
		$args['labels']['all_items'] = $name;
		$args['label']               = $name;
	}
	return $args;
}, 10, 2 );

/** Название раздела для вывода (с учётом переименования). */
function zhdanov_core_type_label( $type ) {
	$o = get_post_type_object( $type );
	return $o ? $o->labels->name : '';
}

/* ---------- Реквизиты продавца ---------- */
function zhdanov_core_requisites_line() {
	$bits = array();
	if ( zhdanov_core_opt( 'legal_name' ) ) {
		$bits[] = esc_html( zhdanov_core_opt( 'legal_name' ) );
	}
	$by = 'by' === zhdanov_core_opt( 'region' );
	if ( zhdanov_core_opt( 'legal_inn' ) ) {
		$bits[] = ( $by ? 'УНП ' : 'ИНН ' ) . esc_html( zhdanov_core_opt( 'legal_inn' ) );
	}
	if ( zhdanov_core_opt( 'legal_ogrn' ) ) {
		$o      = preg_replace( '/\D/', '', zhdanov_core_opt( 'legal_ogrn' ) );
		$bits[] = ( 15 === strlen( $o ) ? 'ОГРНИП ' : 'ОГРН ' ) . esc_html( zhdanov_core_opt( 'legal_ogrn' ) );
	}
	if ( zhdanov_core_opt( 'legal_address' ) ) {
		$bits[] = esc_html( zhdanov_core_opt( 'legal_address' ) );
	}
	if ( zhdanov_core_opt( 'license' ) ) {
		$bits[] = esc_html( zhdanov_core_opt( 'license' ) );
	}
	return implode( ', ', $bits );
}

/** Плашка «Имеются противопоказания». */
function zhdanov_core_med_notice( $class = '' ) {
	if ( ! zhdanov_core_opt( 'med_on' ) || ! zhdanov_core_opt( 'med_text' ) ) {
		return '';
	}
	return '<p class="zh-med' . ( $class ? ' ' . esc_attr( $class ) : '' ) . '">' . esc_html( zhdanov_core_opt( 'med_text' ) ) . '</p>';
}

/* Реквизиты, оферта и плашка в низ подвала; плашка и «Спросить» на страницах услуг. */
add_filter( 'render_block', function ( $html, $block ) {
	$name = isset( $block['blockName'] ) ? $block['blockName'] : '';
	if ( 'zhdanov/footer-meta' === $name ) {
		$add = '';
		$req = zhdanov_core_requisites_line();
		if ( $req ) {
			$add .= '<p class="zh-fmeta__req">' . $req . '</p>';
		}
		$op = (int) zhdanov_core_opt( 'offer_page' );
		if ( $op && 'publish' === get_post_status( $op ) ) {
			$add .= '<p class="zh-fmeta__req"><a href="' . esc_url( get_permalink( $op ) ) . '">' . esc_html( zhdanov_core__( 'Публичная оферта' ) ) . '</a></p>';
		}
		$add .= zhdanov_core_med_notice( 'zh-med--footer' );
		if ( $add ) {
			$html = preg_replace( '/<\/div>\s*$/', $add . '</div>', $html, 1 );
		}
		return $html;
	}
	if ( in_array( $name, array( 'zhdanov/item-meta', 'zhdanov/event-meta' ), true ) && is_singular() ) {
		$type = get_post_type();
		$add  = '';
		if ( in_array( $type, array( 'zh_service', 'zh_person', 'zh_course', 'zh_class' ), true ) ) {
			$add .= zhdanov_core_med_notice();
		}
		if ( zhdanov_core_opt( 'ask_on' ) && in_array( $type, array( 'zh_service', 'zh_product', 'zh_course', 'zh_event', 'zh_dish', 'zh_person', 'zh_promo' ), true ) ) {
			$add .= '<p class="zh-ask">' . zhdanov_core_ask_button( get_the_title() ) . '</p>';
		}
		if ( $add ) {
			$html = $html ? preg_replace( '/<\/div>\s*$/', $add . '</div>', $html, 1 ) : '<div class="zh-imeta">' . $add . '</div>';
		}
	}
	return $html;
}, 10, 2 );

/** Кнопка «Спросить про это»: мессенджер или форма с готовым текстом. */
function zhdanov_core_ask_button( $title ) {
	$c    = zhdanov_core_contacts();
	$href = '#zayavka';
	foreach ( array( 'whatsapp', 'max', 'telegram' ) as $k ) {
		if ( ! empty( $c[ $k ] ) ) {
			$href = $c[ $k ]['url'];
			break;
		}
	}
	$text = sprintf( zhdanov_core__( 'Здравствуйте! Вопрос про «%s»:' ), $title ) . ' ';
	return '<a class="zh-btn-ghost zh-ask__btn" href="' . esc_url( $href ) . '" data-zh-book="' . esc_attr( $text ) . '" data-zh-goal="ask">' . esc_html( zhdanov_core__( 'Спросить про это' ) ) . '</a>';
}

/* ---------- Подарок за заявку и Telegram ---------- */
add_filter( 'zhdanov_lead_response', function ( $res ) {
	$f = zhdanov_core_opt( 'lead_file' );
	if ( $f ) {
		$res['file']       = esc_url_raw( $f );
		$res['file_title'] = zhdanov_core_opt( 'lead_title' ) ? zhdanov_core_opt( 'lead_title' ) : zhdanov_core__( 'Скачать подарок' );
	}
	return $res;
} );

add_action( 'zhdanov_lead_saved', function ( $id, $data ) {
	$token = trim( (string) zhdanov_core_opt( 'tg_token' ) );
	$chat  = trim( (string) zhdanov_core_opt( 'tg_chat' ) );
	if ( ! $token || ! $chat ) {
		return;
	}
	zhdanov_core_tg_send( $token, $chat, $data['text'] );
}, 10, 2 );

function zhdanov_core_tg_send( $token, $chat, $text ) {
	return wp_remote_post(
		'https://api.telegram.org/bot' . rawurlencode( $token ) . '/sendMessage',
		array(
			'timeout'  => 6,
			'blocking' => true,
			'body'     => array( 'chat_id' => $chat, 'text' => mb_substr( $text, 0, 4000 ), 'disable_web_page_preview' => 'true' ),
		)
	);
}

/** Номер чата: последний, кто написал боту. */
function zhdanov_core_tg_detect( $token ) {
	$r = wp_remote_get( 'https://api.telegram.org/bot' . rawurlencode( $token ) . '/getUpdates', array( 'timeout' => 8 ) );
	if ( is_wp_error( $r ) ) {
		return '';
	}
	$j = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( empty( $j['result'] ) ) {
		return '';
	}
	$last = end( $j['result'] );
	$msg  = isset( $last['message'] ) ? $last['message'] : ( isset( $last['channel_post'] ) ? $last['channel_post'] : array() );
	return isset( $msg['chat']['id'] ) ? (string) $msg['chat']['id'] : '';
}

/* ---------- Schema: продавец ---------- */
add_filter( 'zhdanov_org_schema', function ( $d ) {
	if ( zhdanov_core_opt( 'legal_name' ) && 'Person' !== $d['@type'] ) {
		$d['legalName'] = zhdanov_core_opt( 'legal_name' );
	}
	if ( zhdanov_core_opt( 'legal_inn' ) && 'Person' !== $d['@type'] ) {
		$d['taxID'] = preg_replace( '/\D/', '', zhdanov_core_opt( 'legal_inn' ) );
	}
	if ( zhdanov_core_opt( 'hours' ) && 'Person' !== $d['@type'] ) {
		$d['openingHours'] = zhdanov_core_opt( 'hours' );
	}
	// Филиалы: каждый как отделение.
	if ( zhdanov_core_opt( 'mod_branches' ) ) {
		$br = get_posts( array( 'post_type' => 'zh_branch', 'numberposts' => 50, 'post_status' => 'publish' ) );
		foreach ( $br as $b ) {
			$x = array( '@type' => 'LocalBusiness', 'name' => get_bloginfo( 'name' ) . ': ' . get_the_title( $b ), 'url' => get_permalink( $b ) );
			if ( zhdanov_core_m( $b->ID, '_zh_address' ) ) {
				$x['address'] = array( '@type' => 'PostalAddress', 'streetAddress' => zhdanov_core_m( $b->ID, '_zh_address' ) );
			}
			if ( zhdanov_core_m( $b->ID, '_zh_phone' ) ) {
				$x['telephone'] = zhdanov_core_tel( zhdanov_core_m( $b->ID, '_zh_phone' ) );
			}
			$lat = str_replace( ',', '.', zhdanov_core_m( $b->ID, '_zh_lat' ) );
			$lng = str_replace( ',', '.', zhdanov_core_m( $b->ID, '_zh_lng' ) );
			if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
				$x['geo'] = array( '@type' => 'GeoCoordinates', 'latitude' => (float) $lat, 'longitude' => (float) $lng );
			}
			$d['department'][] = $x;
		}
	}
	return $d;
} );

/* Schema: специалист (Person), меню (Menu). */
add_action( 'wp_head', function () {
	if ( is_singular( 'zh_person' ) ) {
		$id = get_queried_object_id();
		$d  = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Person',
			'name'     => get_the_title( $id ),
			'url'      => get_permalink( $id ),
			'worksFor' => array( '@type' => 'Organization', 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ) ),
		);
		if ( zhdanov_core_m( $id, '_zh_role' ) ) {
			$d['jobTitle'] = zhdanov_core_m( $id, '_zh_role' );
		}
		if ( has_post_thumbnail( $id ) ) {
			$d['image'] = get_the_post_thumbnail_url( $id, 'large' );
		}
		if ( zhdanov_core_m( $id, '_zh_langs' ) ) {
			$d['knowsLanguage'] = array_values( array_filter( array_map( 'trim', explode( ',', zhdanov_core_m( $id, '_zh_langs' ) ) ) ) );
		}
		zhdanov_core_jsonld( $d );
	}
	if ( is_post_type_archive( 'zh_dish' ) ) {
		$secs = array();
		foreach ( get_posts( array( 'post_type' => 'zh_dish', 'numberposts' => 300, 'post_status' => 'publish' ) ) as $p ) {
			$t   = get_the_terms( $p, 'zh_dish_cat' );
			$sec = ( $t && ! is_wp_error( $t ) ) ? $t[0]->name : zhdanov_core__( 'Меню' );
			$it  = array( '@type' => 'MenuItem', 'name' => get_the_title( $p ) );
			if ( has_excerpt( $p ) ) {
				$it['description'] = get_the_excerpt( $p );
			}
			$price = (int) preg_replace( '/\D/', '', zhdanov_core_m( $p->ID, '_zh_price' ) );
			if ( $price ) {
				$it['offers'] = array( '@type' => 'Offer', 'price' => $price, 'priceCurrency' => zhdanov_core_currency() );
			}
			$secs[ $sec ][] = $it;
		}
		$d = array( '@context' => 'https://schema.org', '@type' => 'Menu', 'name' => zhdanov_core_type_label( 'zh_dish' ), 'url' => get_post_type_archive_link( 'zh_dish' ), 'hasMenuSection' => array() );
		foreach ( $secs as $n => $items ) {
			$d['hasMenuSection'][] = array( '@type' => 'MenuSection', 'name' => $n, 'hasMenuItem' => $items );
		}
		zhdanov_core_jsonld( $d );
	}
}, 25 );

/* Картинки: уменьшенные копии сохраняем в WebP (легче в 2-3 раза). Оригинал не трогаем. */
add_filter( 'image_editor_output_format', function ( $formats ) {
	if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
		return $formats;
	}
	$formats['image/jpeg'] = 'image/webp';
	$formats['image/png']  = 'image/webp';
	return $formats;
} );

/* Schema: тур (TouristTrip) и книга (Book). */
add_action( 'wp_head', function () {
	if ( is_singular( 'zh_tour' ) ) {
		$id = get_queried_object_id();
		$d  = array( '@context' => 'https://schema.org', '@type' => 'TouristTrip', 'name' => get_the_title( $id ), 'url' => get_permalink( $id ), 'provider' => array( '@type' => 'Organization', 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ) ) );
		if ( has_excerpt( $id ) ) {
			$d['description'] = get_the_excerpt( $id );
		}
		if ( has_post_thumbnail( $id ) ) {
			$d['image'] = get_the_post_thumbnail_url( $id, 'large' );
		}
		$days = array_filter( array_map( 'trim', preg_split( '/\r?\n/', zhdanov_core_m( $id, '_zh_days' ) ) ) );
		foreach ( array_values( $days ) as $i => $x ) {
			$d['itinerary'][] = array( '@type' => 'TouristAttraction', 'name' => trim( explode( ':', $x )[0] ) );
		}
		$sum = function_exists( 'zhdanov_pay_amount' ) ? zhdanov_pay_amount( zhdanov_core_m( $id, '_zh_price' ) ) : 0;
		if ( $sum ) {
			$d['offers'] = array( '@type' => 'Offer', 'price' => $sum, 'priceCurrency' => 'RUB', 'url' => get_permalink( $id ) );
		}
		zhdanov_core_jsonld( $d );
	}
	if ( is_singular( 'zh_book' ) ) {
		$id = get_queried_object_id();
		$d  = array( '@context' => 'https://schema.org', '@type' => 'Book', 'name' => get_the_title( $id ), 'url' => get_permalink( $id ) );
		foreach ( array( '_zh_isbn' => 'isbn', '_zh_pages' => 'numberOfPages', '_zh_year' => 'datePublished' ) as $k => $f ) {
			if ( zhdanov_core_m( $id, $k ) ) {
				$d[ $f ] = '_zh_pages' === $k ? (int) zhdanov_core_m( $id, $k ) : zhdanov_core_m( $id, $k );
			}
		}
		if ( zhdanov_core_m( $id, '_zh_author' ) ) {
			$d['author'] = array( '@type' => 'Person', 'name' => zhdanov_core_m( $id, '_zh_author' ) );
		}
		if ( has_post_thumbnail( $id ) ) {
			$d['image'] = get_the_post_thumbnail_url( $id, 'large' );
		}
		zhdanov_core_jsonld( $d );
	}
}, 26 );
