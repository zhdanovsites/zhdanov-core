<?php
/**
 * Разметка для Яндекса и Google (Schema.org) и превью ссылок в мессенджерах (Open Graph).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** HTML записи после сборки блоков (паттерны развёрнуты). Считается один раз за страницу. */
function zhdanov_core_rendered( $post ) {
	static $cache = array();
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	if ( ! isset( $cache[ $post->ID ] ) ) {
		$content = $post->post_content;
		// Блоки плагина внутри описания не нужны (и могут вызвать сами себя).
		$content            = preg_replace( '~<!-- wp:zhdanov/[^>]*/-->~', '', $content );
		$cache[ $post->ID ] = do_blocks( $content );
	}
	return $cache[ $post->ID ];
}

function zhdanov_core_jsonld( $data ) {
	echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
}

/* Организация: на главной. */
add_action( 'wp_head', function () {
	if ( ! is_front_page() ) {
		return;
	}
	$addr = zhdanov_core_opt( 'address' );
	$d    = array(
		'@context' => 'https://schema.org',
		'@type'    => 'person' === zhdanov_core_opt( 'entity' ) ? 'Person' : ( $addr ? 'LocalBusiness' : 'Organization' ),
		'name'     => get_bloginfo( 'name' ),
		'url'      => home_url( '/' ),
	);
	if ( get_bloginfo( 'description' ) ) {
		$d['description'] = get_bloginfo( 'description' );
	}
	if ( 'Person' === $d['@type'] && zhdanov_core_opt( 'person_job' ) ) {
		$d['jobTitle'] = zhdanov_core_opt( 'person_job' );
	}
	$logo = get_theme_mod( 'custom_logo' ) ? wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' ) : get_site_icon_url( 512 );
	if ( $logo ) {
		$d['logo']  = $logo;
		$d['image'] = $logo;
	}
	if ( zhdanov_core_opt( 'phone' ) ) {
		$d['telephone'] = zhdanov_core_tel( zhdanov_core_opt( 'phone' ) );
	}
	if ( zhdanov_core_opt( 'email' ) ) {
		$d['email'] = zhdanov_core_opt( 'email' );
	}
	if ( $addr && 'Person' !== $d['@type'] ) {
		$d['address'] = array( '@type' => 'PostalAddress', 'streetAddress' => $addr );
		if ( is_numeric( zhdanov_core_opt( 'lat' ) ) ) {
			$d['geo'] = array( '@type' => 'GeoCoordinates', 'latitude' => (float) zhdanov_core_opt( 'lat' ), 'longitude' => (float) zhdanov_core_opt( 'lng' ) );
		}
	}
	$same = array();
	foreach ( zhdanov_core_contacts() as $k => $it ) {
		if ( 0 === strpos( $it['url'], 'http' ) ) {
			$same[] = $it['url'];
		}
	}
	if ( $same ) {
		$d['sameAs'] = $same;
	}
	zhdanov_core_jsonld( apply_filters( 'zhdanov_org_schema', $d ) );
}, 20 );

/* Событие: Event + статус «Отменено» / «Перенесено». */
add_action( 'wp_head', function () {
	if ( ! is_singular( 'zh_event' ) ) {
		return;
	}
	$e   = zhdanov_core_event();
	$map = array(
		'scheduled' => 'EventScheduled',
		'soldout'   => 'EventScheduled',
		'postponed' => 'EventPostponed',
		'cancelled' => 'EventCancelled',
	);
	if ( ! $e['ts'] ) {
		return;
	}
	$tz = wp_timezone();
	$d  = array(
		'@context'            => 'https://schema.org',
		'@type'               => 'Event',
		'name'                => $e['title'],
		'url'                 => $e['url'],
		'startDate'           => wp_date( 'c', $e['ts'], $tz ),
		'eventStatus'         => 'https://schema.org/' . $map[ $e['status'] ],
		'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
		'location'            => array(
			'@type'   => 'Place',
			'name'    => $e['venue'] ? $e['venue'] : get_bloginfo( 'name' ),
			'address' => $e['address'] ? $e['address'] : zhdanov_core_opt( 'address' ),
		),
		'organizer'           => array( '@type' => 'Organization', 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ) ),
	);
	if ( $e['end'] ) {
		$end = date_create_immutable( $e['end'], $tz );
		if ( $end ) {
			$d['endDate'] = $end->format( 'c' );
		}
	}
	$desc = get_the_excerpt();
	if ( $desc ) {
		$d['description'] = wp_strip_all_tags( $desc );
	}
	if ( has_post_thumbnail() ) {
		$d['image'] = get_the_post_thumbnail_url( null, 'full' );
	}
	if ( $e['tickets'] || $e['price'] ) {
		$offer = array( '@type' => 'Offer', 'url' => $e['tickets'] ? $e['tickets'] : $e['url'] );
		if ( preg_match( '/(\d[\d\s]*)/u', $e['price'], $m ) ) {
			$offer['price']         = (int) preg_replace( '/\D/', '', $m[1] );
			$offer['priceCurrency'] = zhdanov_core_currency();
		}
		$offer['availability'] = 'soldout' === $e['status'] ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock';
		$d['offers']           = $offer;
	}
	zhdanov_core_jsonld( $d );
}, 20 );

/* Описание страницы (meta description), если не стоит SEO-плагин. В Сессии 2 сюда же og:image. */
function zhdanov_core_has_seo_plugin() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'THE_SEO_FRAMEWORK_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' );
}

function zhdanov_core_description() {
	$d = '';
	if ( is_front_page() ) {
		$d = get_bloginfo( 'description' );
		if ( is_page() && has_excerpt( get_queried_object_id() ) ) {
			$d = get_the_excerpt( get_queried_object_id() );
		}
	} elseif ( is_singular() ) {
		$post = get_queried_object();
		if ( has_excerpt( $post ) ) {
			$d = $post->post_excerpt;
		} else {
			$html = zhdanov_core_rendered( $post );
			// Первые абзацы текста, без заголовков и кнопок.
			preg_match_all( '~<p(\s[^>]*)?>(.*?)</p>~su', $html, $pm, PREG_SET_ORDER );
			$parts = array();
			foreach ( $pm as $m ) {
				$attrs = isset( $m[1] ) ? $m[1] : '';
				if ( preg_match( '~eyebrow|zh-|screen-reader~', $attrs ) ) {
					continue;
				}
				$t = trim( wp_strip_all_tags( $m[2] ) );
				if ( mb_strlen( $t ) > 25 && false === strpos( $t, '[' ) ) {
					$parts[] = $t;
				}
				if ( count( $parts ) >= 3 ) {
					break;
				}
			}
			$d = $parts ? wp_trim_words( implode( ' ', $parts ), 28, '…' ) : get_the_title( $post ) . '. ' . get_bloginfo( 'name' ) . '. ' . get_bloginfo( 'description' );
		}
		if ( 'zh_event' === get_post_type( $post ) ) {
			$e = zhdanov_core_event( $post );
			$d = trim( $e['date_label'] . ( $e['time'] ? ', ' . $e['time'] : '' ) . ( $e['venue'] ? '. ' . $e['venue'] : '' ) . '. ' . $d );
		}
	} elseif ( is_post_type_archive( 'zh_event' ) ) {
		$d = 'Афиша: ближайшие события, даты, цены и билеты. ' . get_bloginfo( 'name' ) . '.';
	} elseif ( is_post_type_archive() ) {
		$lead = array( 'zh_service' => 'Услуги и цены', 'zh_review' => 'Отзывы клиентов', 'zh_album' => 'Фотоальбомы', 'zh_product' => 'Каталог: цены и наличие' );
		$pt   = get_query_var( 'post_type' );
		$pt   = is_array( $pt ) ? reset( $pt ) : $pt;
		$d    = ( isset( $lead[ $pt ] ) ? $lead[ $pt ] : post_type_archive_title( '', false ) ) . '. ' . get_bloginfo( 'name' ) . '. ' . get_bloginfo( 'description' );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$d = term_description();
		if ( ! $d ) {
			$d = single_term_title( '', false ) . ': все записи. ' . get_bloginfo( 'name' ) . '.';
		}
	} elseif ( is_home() ) {
		$d = 'Новости: ' . get_bloginfo( 'name' ) . '. ' . get_bloginfo( 'description' );
	}
	$d = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $d ) ) );
	return mb_substr( $d, 0, 200 );
}

add_action( 'wp_head', function () {
	if ( zhdanov_core_has_seo_plugin() ) {
		return;
	}
	$d = zhdanov_core_description();
	if ( $d ) {
		echo '<meta name="description" content="' . esc_attr( $d ) . '">' . "\n";
	}
}, 2 );


/* ---------- Превью ссылок: Telegram, ВК, WhatsApp, MAX (Open Graph) ---------- */

function zhdanov_core_og_image() {
	$id = 0;
	if ( is_singular() && has_post_thumbnail() ) {
		$id = get_post_thumbnail_id();
	} elseif ( is_front_page() && get_option( 'page_on_front' ) && has_post_thumbnail( get_option( 'page_on_front' ) ) ) {
		$id = get_post_thumbnail_id( get_option( 'page_on_front' ) );
	}
	if ( $id ) {
		$img = wp_get_attachment_image_src( $id, 'zhdanov-og' );
		// Если обложка меньше 1200×630, WordPress отдаст оригинал: тоже подойдёт.
		if ( $img ) {
			return array( $img[0], $img[1], $img[2] );
		}
	}
	if ( zhdanov_core_opt( 'og_default' ) ) {
		return array( zhdanov_core_opt( 'og_default' ), 1200, 630 );
	}
	$icon = get_site_icon_url( 512 );
	return $icon ? array( $icon, 512, 512 ) : array();
}

add_action( 'wp_head', function () {
	if ( zhdanov_core_has_seo_plugin() ) {
		return;
	}
	$title = wp_get_document_title();
	$desc  = zhdanov_core_description();
	$url   = is_singular() ? get_permalink() : ( is_front_page() ? home_url( '/' ) : '' );
	if ( ! $url ) {
		global $wp;
		$url = home_url( add_query_arg( array(), trailingslashit( $wp->request ) ) );
	}
	$type = is_singular( 'post' ) ? 'article' : 'website';
	$img  = zhdanov_core_og_image();
	$out  = array(
		'og:locale'    => str_replace( '-', '_', get_bloginfo( 'language' ) ),
		'og:type'      => $type,
		'og:site_name' => get_bloginfo( 'name' ),
		'og:title'     => is_front_page() ? get_bloginfo( 'name' ) : wp_strip_all_tags( is_singular() ? get_the_title() : $title ),
		'og:description' => $desc,
		'og:url'       => $url,
	);
	foreach ( $out as $k => $v ) {
		if ( $v ) {
			echo '<meta property="' . esc_attr( $k ) . '" content="' . esc_attr( $v ) . '">' . "\n";
		}
	}
	if ( $img ) {
		echo '<meta property="og:image" content="' . esc_url( $img[0] ) . '">' . "\n";
		echo '<meta property="og:image:width" content="' . (int) $img[1] . '">' . "\n";
		echo '<meta property="og:image:height" content="' . (int) $img[2] . '">' . "\n";
	}
	echo '<meta name="twitter:card" content="' . ( $img && $img[1] >= 1000 ? 'summary_large_image' : 'summary' ) . '">' . "\n";
	if ( 'article' === $type ) {
		echo '<meta property="article:published_time" content="' . esc_attr( get_the_date( 'c' ) ) . '">' . "\n";
		echo '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( 'c' ) ) . '">' . "\n";
	}
	// Канонический адрес для страниц, где WordPress его не ставит (архивы).
	if ( ! is_singular() && ! is_paged() && ! is_search() ) {
		echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
	}
}, 3 );

/* ---------- Article: новости и статьи ---------- */
add_action( 'wp_head', function () {
	if ( ! is_singular( 'post' ) || zhdanov_core_has_seo_plugin() ) {
		return;
	}
	$img = zhdanov_core_og_image();
	$pub = 'person' === zhdanov_core_opt( 'entity' ) ? 'Person' : 'Organization';
	$d   = array(
		'@context'         => 'https://schema.org',
		'@type'            => 'Article',
		'headline'         => wp_strip_all_tags( get_the_title() ),
		'description'      => zhdanov_core_description(),
		'datePublished'    => get_the_date( 'c' ),
		'dateModified'     => get_the_modified_date( 'c' ),
		'mainEntityOfPage' => get_permalink(),
		'author'           => array( '@type' => $pub, 'name' => 'Person' === $pub ? get_bloginfo( 'name' ) : get_the_author(), 'url' => home_url( '/' ) ),
		'publisher'        => array( '@type' => $pub, 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ) ),
	);
	if ( 'Organization' === $pub ) {
		$d['author'] = array( '@type' => 'Organization', 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ) );
	}
	if ( $img ) {
		$d['image'] = $img[0];
	}
	zhdanov_core_jsonld( $d );
}, 20 );

/* ---------- FAQPage: из блоков «Вопрос-ответ» (details) на странице ---------- */
add_action( 'wp_head', function () {
	if ( ! is_singular() ) {
		return;
	}
	$post = get_queried_object();
	if ( ! $post || ( false === strpos( $post->post_content, 'wp:details' ) && false === strpos( $post->post_content, 'wp:pattern' ) ) ) {
		return;
	}
	// Паттерны внутри страницы разворачиваем, чтобы найти вопросы из «Готовых страниц».
	$html = zhdanov_core_rendered( $post );
	if ( ! preg_match_all( '~<details[^>]*>\s*<summary[^>]*>(.*?)</summary>(.*?)</details>~su', $html, $m, PREG_SET_ORDER ) ) {
		return;
	}
	$items = array();
	foreach ( $m as $qa ) {
		$q = trim( wp_strip_all_tags( $qa[1] ) );
		$a = trim( wp_strip_all_tags( $qa[2] ) );
		if ( $q && $a ) {
			$items[] = array( '@type' => 'Question', 'name' => $q, 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $a ) );
		}
	}
	if ( $items ) {
		zhdanov_core_jsonld( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items ) );
	}
}, 20 );

/* ---------- Товар каталога: Product + Offer ---------- */
add_action( 'wp_head', function () {
	if ( ! is_singular( 'zh_product' ) ) {
		return;
	}
	$id    = get_the_ID();
	$price = (int) preg_replace( '/\D/', '', zhdanov_core_m( $id, '_zh_price' ) );
	$d     = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'Product',
		'name'        => wp_strip_all_tags( get_the_title() ),
		'description' => zhdanov_core_description(),
		'url'         => get_permalink(),
	);
	if ( has_post_thumbnail() ) {
		$d['image'] = get_the_post_thumbnail_url( null, 'full' );
	}
	if ( zhdanov_core_m( $id, '_zh_sku' ) ) {
		$d['sku'] = zhdanov_core_m( $id, '_zh_sku' );
	}
	if ( $price ) {
		$av          = array( 'in' => 'InStock', 'order' => 'PreOrder', 'out' => 'OutOfStock' );
		$st          = zhdanov_core_m( $id, '_zh_stock' );
		$d['offers'] = array(
			'@type'         => 'Offer',
			'price'         => $price,
			'priceCurrency' => zhdanov_core_currency(),
			'availability'  => 'https://schema.org/' . ( isset( $av[ $st ] ) ? $av[ $st ] : 'InStock' ),
			'url'           => get_permalink(),
		);
	}
	zhdanov_core_jsonld( $d );
}, 20 );

/* Заголовок вкладки: без длинного тире (наше правило). «Название · Сайт». */
add_filter( 'document_title_separator', function () {
	return '·';
} );

/* Курс: Schema Course с ценой текущего тарифа. */
add_action( 'wp_head', function () {
	if ( ! is_singular( 'zh_course' ) ) {
		return;
	}
	$id  = get_the_ID();
	$d   = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'Course',
		'name'        => wp_strip_all_tags( get_the_title() ),
		'description' => zhdanov_core_description(),
		'url'         => get_permalink(),
		'provider'    => array( '@type' => 'person' === zhdanov_core_opt( 'entity' ) ? 'Person' : 'Organization', 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ) ),
	);
	$tar = zhdanov_core_course_tariffs( $id );
	if ( $tar && preg_match( '/\d[\d\s]*/u', $tar[0]['price'], $m ) ) {
		$d['offers'] = array( '@type' => 'Offer', 'category' => 'Paid', 'price' => (int) preg_replace( '/\D/', '', $m[0] ), 'priceCurrency' => zhdanov_core_currency() );
	}
	$start = zhdanov_core_m( $id, '_zh_start' );
	if ( $start ) {
		$d['hasCourseInstance'] = array( '@type' => 'CourseInstance', 'startDate' => $start, 'courseMode' => stripos( zhdanov_core_m( $id, '_zh_format' ), 'онлайн' ) !== false ? 'online' : 'onsite' );
	}
	zhdanov_core_jsonld( $d );
}, 20 );
