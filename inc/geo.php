<?php
/**
 * Готовность к нейросетям (GEO): чтобы Алиса AI, GigaChat, ChatGPT, Perplexity находили сайт и называли его в ответах.
 * 1) /llms.txt собирается сам: кто вы, контакты, главные страницы и разделы (новый стандарт для нейросетей);
 * 2) карточка в Яндекс Картах / 2ГИС добавляется в разметку Schema.org (sameAs);
 * 3) в Здоровье сайта группа «Нейросети»: роботы не закрыты, контакты текстом, карточка в картах, вопрос-ответ, llms.txt.
 *
 * @package zhdanov-core
 */

defined( 'ABSPATH' ) || exit;

/** Текст llms.txt. */
function zhdanov_llms_text() {
	$name = get_bloginfo( 'name' );
	$desc = trim( (string) get_bloginfo( 'description' ) );
	$o    = function ( $k ) {
		return trim( (string) zhdanov_core_opt( $k ) );
	};
	$front = (int) get_option( 'page_on_front' );
	if ( $front ) {
		$md = trim( (string) get_post_meta( $front, '_zh_seo_desc', true ) );
		if ( $md ) {
			$desc = $md;
		} elseif ( has_excerpt( $front ) ) {
			$desc = get_the_excerpt( $front );
		}
	}
	$lines   = array( '# ' . $name, '' );
	if ( $desc ) {
		$lines[] = '> ' . preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $desc ) );
		$lines[] = '';
	}
	$contact = array();
	foreach ( array( 'phone' => 'Телефон', 'email' => 'Почта', 'address' => 'Адрес', 'hours' => 'Часы работы', 'max' => 'MAX', 'telegram' => 'Telegram', 'map_card' => 'Карточка в картах' ) as $k => $l ) {
		if ( $o( $k ) ) {
			$v         = 'telegram' === $k && false === strpos( $o( $k ), '/' ) ? 'https://t.me/' . ltrim( $o( $k ), '@' ) : $o( $k );
			$contact[] = '- ' . $l . ': ' . $v;
		}
	}
	if ( $contact ) {
		$lines[] = '## Контакты';
		$lines   = array_merge( $lines, $contact, array( '' ) );
	}
	$item = function ( $p ) {
		$ex = has_excerpt( $p ) ? get_the_excerpt( $p ) : wp_trim_words( wp_strip_all_tags( str_replace( '><', '> <', strip_shortcodes( $p->post_content ) ) ), 22, '…' );
		$ex = trim( preg_replace( '/\s+/u', ' ', (string) $ex ) );
		return '- [' . get_the_title( $p ) . '](' . get_permalink( $p ) . ')' . ( $ex ? ': ' . $ex : '' );
	};
	$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 25, 'orderby' => 'menu_order title', 'order' => 'ASC', 'has_password' => false ) );
	if ( $pages ) {
		$lines[] = '## Главные страницы';
		foreach ( $pages as $p ) {
			if ( ! preg_match( '/политик|соглас|оферт|cookie|privacy|policy|terms/iu', $p->post_title ) ) {
				$lines[] = $item( $p );
			}
		}
		$lines[] = '';
	}
	foreach ( get_post_types( array( 'public' => true, '_builtin' => false ), 'objects' ) as $pt ) {
		if ( in_array( $pt->name, array( 'zh_lead', 'zh_note' ), true ) ) {
			continue;
		}
		$ps = get_posts( array( 'post_type' => $pt->name, 'post_status' => 'publish', 'numberposts' => 30 ) );
		if ( ! $ps ) {
			continue;
		}
		$lines[] = '## ' . $pt->labels->name;
		foreach ( $ps as $p ) {
			$lines[] = $item( $p );
		}
		$lines[] = '';
	}
	$news = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 10 ) );
	if ( $news ) {
		$lines[] = '## Новости и статьи';
		foreach ( $news as $p ) {
			$lines[] = $item( $p );
		}
		$lines[] = '';
	}
	return (string) apply_filters( 'zhdanov_llms_txt', implode( "\n", $lines ) );
}

/* Отдаём /llms.txt, если на хостинге нет своего файла */
add_action( 'init', function () {
	$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ); // phpcs:ignore
	$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	if ( rtrim( $home, '/' ) . '/llms.txt' !== $path || file_exists( ABSPATH . 'llms.txt' ) ) {
		return;
	}
	if ( ! get_option( 'blog_public' ) || zhdanov_core_opt( 'maint_on' ) ) {
		status_header( 404 );
		exit;
	}
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );
	header( 'Cache-Control: public, max-age=3600' );
	echo zhdanov_llms_text(); // phpcs:ignore -- простой текст
	exit;
}, 1 );

/* Карточка в картах: в разметку Schema.org как sameAs */
add_filter( 'zhdanov_org_schema', function ( $d ) {
	$c = trim( (string) zhdanov_core_opt( 'map_card' ) );
	if ( $c ) {
		$d['sameAs'] = array_values( array_unique( array_merge( (array) ( $d['sameAs'] ?? array() ), array( $c ) ) ) );
	}
	return $d;
} );

/** Какие роботы нейросетей закрыты в robots.txt (свой файл на хостинге или виртуальный WordPress). */
function zhdanov_geo_robots_blocked() {
	$file = ABSPATH . 'robots.txt';
	if ( file_exists( $file ) ) {
		$txt = (string) file_get_contents( $file ); // phpcs:ignore
	} else {
		ob_start();
		do_robots();
		$txt = (string) ob_get_clean();
		if ( ! headers_sent() ) {
			header_remove( 'Content-Type' );
		}
	}
	$groups = array();
	$cur    = null;
	foreach ( preg_split( '/\R/u', $txt ) as $line ) {
		$line = trim( preg_replace( '/#.*/', '', $line ) );
		if ( ! preg_match( '/^([A-Za-z-]+)\s*:\s*(.*)$/', $line, $m ) ) {
			continue;
		}
		$k = strtolower( $m[1] );
		$v = trim( $m[2] );
		if ( 'user-agent' === $k ) {
			if ( ! $cur || $cur['rules'] ) {
				$groups[] = array( 'a' => array(), 'rules' => array() );
				$cur      = &$groups[ count( $groups ) - 1 ];
			}
			$cur['a'][] = strtolower( $v );
		} elseif ( $cur && in_array( $k, array( 'disallow', 'allow' ), true ) ) {
			$cur['rules'][] = array( $k, $v );
		}
	}
	unset( $cur );
	$out = array();
	foreach ( array( 'yandexbot' => 'Яндекс и Алиса', 'googlebot' => 'Google', 'oai-searchbot' => 'ChatGPT', 'gptbot' => 'ChatGPT', 'perplexitybot' => 'Perplexity' ) as $bot => $label ) {
		$g = null;
		foreach ( $groups as $x ) {
			foreach ( $x['a'] as $a ) {
				if ( '*' !== $a && 0 === strpos( $bot, $a ) ) {
					$g = $x;
				}
			}
		}
		if ( ! $g ) {
			foreach ( $groups as $x ) {
				if ( in_array( '*', $x['a'], true ) ) {
					$g = $x;
				}
			}
		}
		if ( ! $g ) {
			continue;
		}
		$dis = false;
		$all = false;
		foreach ( $g['rules'] as $r ) {
			$dis = $dis || ( 'disallow' === $r[0] && '/' === $r[1] );
			$all = $all || ( 'allow' === $r[0] && in_array( $r[1], array( '/', '/$' ), true ) );
		}
		if ( $dis && ! $all ) {
			$out[ $label ] = $label;
		}
	}
	return array_values( $out );
}

/** Есть ли где-то на сайте блок «вопрос-ответ». */
function zhdanov_geo_has_faq() {
	global $wpdb;
	return (bool) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_status='publish' AND post_type NOT IN ('revision','zh_lead','zh_note','zh_csnap') AND (post_content LIKE '%<details%' OR post_content LIKE '%wp:details%' OR post_content LIKE '%zh-faq%') LIMIT 1" ); // phpcs:ignore
}

/* Группа в Здоровье сайта */
add_filter( 'zhdanov_health_items', function ( $items ) {
	if ( ! get_option( 'blog_public' ) ) {
		return $items;
	}
	$o       = function ( $k ) {
		return trim( (string) zhdanov_core_opt( $k ) );
	};
	$set     = admin_url( 'admin.php?page=zhdanov-core#contacts' );
	$blocked = zhdanov_geo_robots_blocked();
	$items[] = array( ! $blocked, 'Нейросети: роботы Яндекса, Google и ChatGPT допущены', 'В robots.txt закрыт доступ: ' . implode( ', ', $blocked ) . '. Эти системы не назовут сайт в ответах. Уберите «Disallow: /» для них.', home_url( '/robots.txt' ), $blocked && array_intersect( $blocked, array( 'Яндекс и Алиса', 'Google' ) ) ? 'bad' : 'warn' );
	$items[] = array( (bool) ( $o( 'phone' ) && ( $o( 'address' ) || 'person' === $o( 'entity' ) ) ), 'Нейросети: контакты и адрес заполнены', 'Заполните телефон и адрес в настройках: они попадут в разметку для роботов и в llms.txt, и Алиса сможет назвать, как с вами связаться.', $set, 'warn' );
	$items[] = array( (bool) $o( 'map_card' ), 'Нейросети: есть ссылка на карточку в Яндекс Картах или 2ГИС', 'Алиса берёт адрес, часы и отзывы из Яндекс Бизнеса. Вставьте ссылку на свою карточку в поле «Карточка в Яндекс Картах».', $set, 'warn' );
	$items[] = array( zhdanov_geo_has_faq(), 'Нейросети: на сайте есть вопрос-ответ', 'Нейросети отвечают людям на вопросы. Добавьте блок «Вопрос-ответ» (5–8 вопросов клиентов: цена, запись, где вы): плагин сам сделает для него разметку FAQPage.', admin_url( 'edit.php?post_type=page' ), 'warn' );
	$items[] = array( ! file_exists( ABSPATH . 'llms.txt' ) || filesize( ABSPATH . 'llms.txt' ) > 30, 'Нейросети: файл llms.txt', 'Плагин сам отдаёт ' . home_url( '/llms.txt' ) . ': кто вы, контакты, главные страницы.', home_url( '/llms.txt' ), 'warn' );
	return $items;
} );
