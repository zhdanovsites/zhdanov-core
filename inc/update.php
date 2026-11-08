<?php
/**
 * Автообновление темы и плагина с сайта студии.
 *
 * Раз в 6 часов WordPress клиента читает файл update.json (по умолчанию последний релиз на GitHub zhdanovsites/zhdanov-core):
 * {
 *   "core":  { "version": "0.7.1", "package": "https://zhdanovsites.com/wp/zhdanov-core-v0.7.1.zip", "requires": "6.7", "tested": "6.9", "requires_php": "7.4", "notes": "Что нового" },
 *   "theme": { "version": "0.7.1", "package": "https://zhdanovsites.com/wp/zhdanov-theme-v0.7.1.zip", "notes": "Что нового" }
 * }
 * Если версия новее установленной, в «Обновлениях» появляется обычная кнопка «Обновить».
 * Архивы принимаются только с того же сайта, что и update.json.
 * Настройки сайта, тексты, цвета, страницы при обновлении не меняются: они в базе, не в файлах.
 *
 * @package zhdanov-core
 */

defined( 'ABSPATH' ) || exit;

function zhdanov_update_url() {
	$u = defined( 'ZHDANOV_UPDATE_URL' ) ? ZHDANOV_UPDATE_URL : 'https://github.com/zhdanovsites/zhdanov-core/releases/latest/download/update.json';
	return (string) apply_filters( 'zhdanov_update_url', $u );
}

/** Данные о новых версиях (кеш 6 часов, при ошибке сети: 1 час). */
function zhdanov_update_info( $force = false ) {
	$cached = get_site_transient( 'zhdanov_update_info' );
	if ( ! $force && is_array( $cached ) ) {
		return $cached;
	}
	$url = zhdanov_update_url();
	$r   = wp_remote_get( $url, array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/json' ) ) );
	$out = array();
	if ( ! is_wp_error( $r ) && 200 === wp_remote_retrieve_response_code( $r ) ) {
		$j    = json_decode( wp_remote_retrieve_body( $r ), true );
		$host = wp_parse_url( $url, PHP_URL_HOST );
		foreach ( array( 'core', 'theme' ) as $k ) {
			if ( empty( $j[ $k ]['version'] ) || empty( $j[ $k ]['package'] ) ) {
				continue;
			}
			// архив только с сайта студии и только по https (или с того же адреса, откуда update.json)
			$p = (string) $j[ $k ]['package'];
			if ( wp_parse_url( $p, PHP_URL_HOST ) !== $host || ( 'https' !== wp_parse_url( $p, PHP_URL_SCHEME ) && 'https' === wp_parse_url( $url, PHP_URL_SCHEME ) ) ) {
				continue;
			}
			$out[ $k ] = array(
				'version'      => preg_replace( '/[^0-9a-z.\-]/i', '', (string) $j[ $k ]['version'] ),
				'package'      => esc_url_raw( $p ),
				'requires'     => isset( $j[ $k ]['requires'] ) ? sanitize_text_field( $j[ $k ]['requires'] ) : '',
				'tested'       => isset( $j[ $k ]['tested'] ) ? sanitize_text_field( $j[ $k ]['tested'] ) : '',
				'requires_php' => isset( $j[ $k ]['requires_php'] ) ? sanitize_text_field( $j[ $k ]['requires_php'] ) : '',
				'notes'        => isset( $j[ $k ]['notes'] ) ? wp_kses_post( $j[ $k ]['notes'] ) : '',
			);
		}
		set_site_transient( 'zhdanov_update_info', $out, 6 * HOUR_IN_SECONDS );
	} else {
		set_site_transient( 'zhdanov_update_info', $out, HOUR_IN_SECONDS );
	}
	return $out;
}

/* Плагин */
add_filter( 'pre_set_site_transient_update_plugins', function ( $t ) {
	if ( ! is_object( $t ) ) {
		return $t;
	}
	$info = zhdanov_update_info();
	$file = plugin_basename( ZHDANOV_CORE_DIR . 'zhdanov-core.php' );
	if ( ! empty( $info['core'] ) && version_compare( $info['core']['version'], ZHDANOV_CORE_VERSION, '>' ) ) {
		$t->response[ $file ] = (object) array(
			'id'           => 'zhdanovsites.com/zhdanov-core',
			'slug'         => 'zhdanov-core',
			'plugin'       => $file,
			'new_version'  => $info['core']['version'],
			'url'          => 'https://zhdanovsites.com/',
			'package'      => $info['core']['package'],
			'tested'       => $info['core']['tested'],
			'requires_php' => $info['core']['requires_php'],
		);
	} elseif ( isset( $t->response[ $file ] ) ) {
		unset( $t->response[ $file ] );
	}
	return $t;
} );

/* Окно «Подробнее» у обновления плагина */
add_filter( 'plugins_api', function ( $res, $action, $args ) {
	if ( 'plugin_information' !== $action || empty( $args->slug ) || 'zhdanov-core' !== $args->slug ) {
		return $res;
	}
	$info = zhdanov_update_info();
	if ( empty( $info['core'] ) ) {
		return $res;
	}
	return (object) array(
		'name'          => 'Тема Жданов: ядро',
		'slug'          => 'zhdanov-core',
		'version'       => $info['core']['version'],
		'author'        => '<a href="https://zhdanovsites.com/">Zhdanov Sites</a>',
		'homepage'      => 'https://zhdanovsites.com/',
		'requires'      => $info['core']['requires'],
		'tested'        => $info['core']['tested'],
		'requires_php'  => $info['core']['requires_php'],
		'download_link' => $info['core']['package'],
		'sections'      => array( 'changelog' => $info['core']['notes'] ? wpautop( $info['core']['notes'] ) : 'Исправления и улучшения.' ),
	);
}, 10, 3 );

/* Тема (обновляется, пока стоит плагин) */
add_filter( 'pre_set_site_transient_update_themes', function ( $t ) {
	if ( ! is_object( $t ) ) {
		return $t;
	}
	$theme = wp_get_theme( 'zhdanov-theme' );
	if ( ! $theme->exists() ) {
		return $t;
	}
	$info = zhdanov_update_info();
	if ( ! empty( $info['theme'] ) && version_compare( $info['theme']['version'], $theme->get( 'Version' ), '>' ) ) {
		$t->response['zhdanov-theme'] = array(
			'theme'        => 'zhdanov-theme',
			'new_version'  => $info['theme']['version'],
			'url'          => 'https://zhdanovsites.com/',
			'package'      => $info['theme']['package'],
			'requires'     => $info['theme']['requires'],
			'requires_php' => $info['theme']['requires_php'],
		);
	} elseif ( isset( $t->response['zhdanov-theme'] ) ) {
		unset( $t->response['zhdanov-theme'] );
	}
	return $t;
} );

/* Кнопка «Проверить обновления сейчас» на экране «Обновления» сбрасывает и наш кеш */
add_action( 'load-update-core.php', function () {
	if ( isset( $_GET['force-check'] ) ) { // phpcs:ignore
		delete_site_transient( 'zhdanov_update_info' );
	}
} );

/* После обновления плагина или темы: сбросить кеш версий */
add_action( 'upgrader_process_complete', function () {
	delete_site_transient( 'zhdanov_update_info' );
} );
