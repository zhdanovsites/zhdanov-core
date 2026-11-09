<?php
/**
 * Быстрая загрузка: кеш готовых страниц для посетителей (как WP Rocket / LiteSpeed Cache, только простое и безопасное).
 * Включается галочкой (Жданов → «Отчёт разработчику» → «Быстрая загрузка страниц»). Страница собирается один раз,
 * дальше посетители получают готовый HTML. Кеш сбрасывается сам при любой правке, смене настроек, меню, обновлениях.
 * Не кешируется: вошедшие, клиенты со ссылкой замечаний или доступа, страницы с формами на одноразовом ключе,
 * поиск, 404, предпросмотр, ответы с cookie.
 *
 * @package zhdanov-core
 */

defined( 'ABSPATH' ) || exit;

define( 'ZHDANOV_CACHE_TTL', 6 * HOUR_IN_SECONDS );

function zhdanov_cache_on() {
	return (bool) zhdanov_core_opt( 'page_cache' );
}

function zhdanov_cache_dir() {
	return WP_CONTENT_DIR . '/cache/zh-pages';
}

/** Можно ли этот запрос отдать из кеша или положить в кеш. Возвращает ключ или ''. */
function zhdanov_cache_key() {
	if ( ! in_array( $_SERVER['REQUEST_METHOD'] ?? '', array( 'GET', 'HEAD' ), true ) || ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_ajax() || wp_doing_cron() ) {
		return '';
	}
	$uri  = (string) ( $_SERVER['REQUEST_URI'] ?? '/' ); // phpcs:ignore
	$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
	if ( preg_match( '#^/(wp-admin|wp-json|wp-login|wp-cron|xmlrpc|feed)|\.(xml|txt|php|json)$|/feed/?$#i', $path ) ) {
		return '';
	}
	$q = (string) wp_parse_url( $uri, PHP_URL_QUERY );
	if ( '' !== $q ) {
		parse_str( $q, $qa );
		foreach ( array_keys( $qa ) as $k ) {
			if ( ! preg_match( '/^(utm_\w+|yclid|gclid|fbclid|_openstat|from)$/', $k ) ) {
				return ''; // любые другие параметры: живая страница
			}
		}
	}
	foreach ( array_keys( $_COOKIE ) as $c ) {
		if ( preg_match( '/^(wordpress_logged_in_|wordpress_sec_|wp-postpass_|comment_author_|zh_review|zh_acc_|woocommerce_|wp_woocommerce_session_)/', $c ) ) {
			return '';
		}
	}
	$host = strtolower( (string) ( $_SERVER['HTTP_HOST'] ?? '' ) );
	return md5( $host . '|' . $path . '|' . ( is_ssl() ? 's' : '' ) );
}

/* Отдать из кеша как можно раньше */
add_action( 'plugins_loaded', function () {
	if ( ! zhdanov_cache_on() || zhdanov_core_opt( 'maint_on' ) ) {
		return;
	}
	$k = zhdanov_cache_key();
	if ( ! $k ) {
		return;
	}
	$f = zhdanov_cache_dir() . '/' . $k . '.html';
	if ( ! is_file( $f ) || filemtime( $f ) < time() - ZHDANOV_CACHE_TTL ) {
		$GLOBALS['zh_cache_key'] = $k;
		return;
	}
	header( 'Content-Type: text/html; charset=UTF-8' );
	header( 'X-Zh-Cache: HIT' );
	header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );
	if ( 'HEAD' !== $_SERVER['REQUEST_METHOD'] ) {
		readfile( $f ); // phpcs:ignore
	}
	exit;
}, 1 );

/* Положить готовую страницу в кеш */
add_action( 'template_redirect', function () {
	if ( empty( $GLOBALS['zh_cache_key'] ) || is_user_logged_in() || is_404() || is_search() || is_preview() || is_feed() || ! empty( $GLOBALS['zh_pp_id'] ) || post_password_required() ) {
		return;
	}
	ob_start( function ( $html ) {
		if ( ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) || 200 !== http_response_code() || strlen( $html ) < 500 || false === stripos( $html, '</html>' ) ) {
			return $html;
		}
		foreach ( headers_list() as $h ) {
			if ( 0 === stripos( $h, 'Set-Cookie' ) || ( 0 === stripos( $h, 'Content-Type' ) && false === stripos( $h, 'text/html' ) ) ) {
				return $html;
			}
		}
		// одноразовые ключи форм нельзя отдавать всем: такую страницу не кешируем
		if ( preg_match( '/name="(_wpnonce|_zhpr|zh_event_nonce)"|wpApiSettings|"nonce":"[a-f0-9]{10}"/i', $html ) ) {
			return $html;
		}
		$dir = zhdanov_cache_dir();
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			@file_put_contents( $dir . '/index.php', '<?php // Silence.' ); // phpcs:ignore
		}
		$tmp = $dir . '/' . $GLOBALS['zh_cache_key'] . '.' . wp_generate_password( 6, false ) . '.tmp';
		if ( false !== @file_put_contents( $tmp, $html . "\n<!-- zh-cache " . gmdate( 'c' ) . ' -->' ) ) { // phpcs:ignore
			@rename( $tmp, $dir . '/' . $GLOBALS['zh_cache_key'] . '.html' ); // phpcs:ignore
		}
		return $html;
	} );
}, 9999 );

/** Сбросить весь кеш. */
function zhdanov_cache_flush() {
	$dir = zhdanov_cache_dir();
	if ( ! is_dir( $dir ) ) {
		return 0;
	}
	$n = 0;
	foreach ( array_merge( (array) glob( $dir . '/*.html' ), (array) glob( $dir . '/*.tmp' ) ) as $f ) {
		$n += @unlink( $f ) ? 1 : 0; // phpcs:ignore
	}
	return $n;
}

/* Когда сбрасывать */
add_action( 'save_post', function ( $id, $p ) {
	if ( wp_is_post_revision( $id ) || in_array( $p->post_type, array( 'zh_lead', 'zh_note', 'zh_csnap', 'customize_changeset' ), true ) ) {
		return;
	}
	zhdanov_cache_flush();
}, 10, 2 );
foreach ( array( 'deleted_post', 'trashed_post', 'switch_theme', 'customize_save_after', 'wp_update_nav_menu', 'upgrader_process_complete', 'activated_plugin', 'deactivated_plugin', 'edited_term', 'created_term', 'delete_term', 'wp_set_comment_status', 'comment_post', 'update_option_zhdanov_core', 'update_option_zhdanov_redirects', 'update_option_blogname', 'update_option_blogdescription', 'update_option_page_on_front', 'update_option_show_on_front', 'update_option_permalink_structure', 'update_option_zh_concept_data' ) as $h ) {
	add_action( $h, 'zhdanov_cache_flush' );
}
add_action( 'updated_option', function ( $name ) {
	if ( 0 === strpos( $name, 'zh_concept' ) || 0 === strpos( $name, 'zhdanov_cdata' ) || 'sidebars_widgets' === $name ) {
		zhdanov_cache_flush();
	}
} );

/* Кнопка «Очистить кеш» в админ-баре */
add_action( 'admin_bar_menu', function ( $bar ) {
	if ( ! zhdanov_cache_on() || ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	$bar->add_node( array( 'id' => 'zh-cache', 'title' => 'Очистить кеш', 'href' => wp_nonce_url( admin_url( 'admin-post.php?action=zhdanov_cache_flush' ), 'zhdanov_cache_flush' ) ) );
}, 100 );
add_action( 'admin_post_zhdanov_cache_flush', function () {
	if ( ! current_user_can( 'edit_pages' ) ) {
		wp_die( 'Нет прав.', 403 );
	}
	check_admin_referer( 'zhdanov_cache_flush' );
	zhdanov_cache_flush();
	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
	exit;
} );

/** Для Здоровья сайта: сколько страниц в кеше. */
function zhdanov_cache_count() {
	return is_dir( zhdanov_cache_dir() ) ? count( (array) glob( zhdanov_cache_dir() . '/*.html' ) ) : 0;
}
