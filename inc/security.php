<?php
/**
 * Проверка безопасности (как Wordfence / Solid Security, только самое нужное):
 * 1) файлы WordPress сверяются с эталоном wordpress.org (изменённые и лишние PHP в wp-admin, wp-includes);
 * 2) плагины с wordpress.org сверяются с их эталоном;
 * 3) PHP-файлы в папке загрузок (там их быть не должно: частый признак взлома);
 * 4) новые администраторы за 14 дней.
 * Раз в неделю по расписанию; при находках сообщение студии в Telegram. Пункт в Здоровье сайта.
 *
 * @package zhdanov-core
 */

defined( 'ABSPATH' ) || exit;

function zhdanov_sec_core() {
	global $wp_version, $wp_local_package;
	$locale = isset( $wp_local_package ) ? $wp_local_package : 'en_US';
	$r      = wp_remote_get( 'https://api.wordpress.org/core/checksums/1.0/?version=' . rawurlencode( $wp_version ) . '&locale=' . rawurlencode( $locale ), array( 'timeout' => 20 ) );
	$j      = is_wp_error( $r ) ? null : json_decode( wp_remote_retrieve_body( $r ), true );
	if ( empty( $j['checksums'] ) && 'en_US' !== $locale ) {
		$r = wp_remote_get( 'https://api.wordpress.org/core/checksums/1.0/?version=' . rawurlencode( $wp_version ) . '&locale=en_US', array( 'timeout' => 20 ) );
		$j = is_wp_error( $r ) ? null : json_decode( wp_remote_retrieve_body( $r ), true );
	}
	$sums = $j['checksums'] ?? null;
	if ( isset( $sums[ $wp_version ] ) ) {
		$sums = $sums[ $wp_version ];
	}
	if ( ! is_array( $sums ) || ! $sums ) {
		return array( 'error' => 'нет связи с wordpress.org для сверки' );
	}
	$changed = array();
	foreach ( $sums as $file => $md5 ) {
		if ( 0 === strpos( $file, 'wp-content/' ) || in_array( $file, array( 'readme.html', 'license.txt', 'wp-config-sample.php', 'licencia.txt' ), true ) ) {
			continue;
		}
		$p = ABSPATH . $file;
		if ( file_exists( $p ) && md5_file( $p ) !== $md5 ) {
			$changed[] = $file;
		}
	}
	// лишние PHP-файлы в wp-admin и wp-includes
	$extra = array();
	foreach ( array( 'wp-admin', 'wp-includes' ) as $dir ) {
		if ( ! is_dir( ABSPATH . $dir ) ) {
			continue;
		}
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( ABSPATH . $dir, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $f ) {
			if ( 'php' !== strtolower( $f->getExtension() ) ) {
				continue;
			}
			$rel = str_replace( '\\', '/', substr( $f->getPathname(), strlen( ABSPATH ) ) );
			if ( ! isset( $sums[ $rel ] ) ) {
				$extra[] = $rel;
			}
			if ( count( $extra ) > 50 ) {
				break 2;
			}
		}
	}
	return array( 'changed' => $changed, 'extra' => $extra );
}

function zhdanov_sec_plugins() {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$out = array();
	foreach ( get_plugins() as $file => $data ) {
		$slug = dirname( $file );
		if ( '.' === $slug || 'zhdanov-core' === $slug || empty( $data['Version'] ) ) {
			continue;
		}
		$r = wp_remote_get( 'https://downloads.wordpress.org/plugin-checksums/' . rawurlencode( $slug ) . '/' . rawurlencode( $data['Version'] ) . '.json', array( 'timeout' => 15 ) );
		if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
			continue; // не с wordpress.org (платный, свой): сверять не с чем
		}
		$j = json_decode( wp_remote_retrieve_body( $r ), true );
		if ( empty( $j['files'] ) ) {
			continue;
		}
		$bad = array();
		foreach ( $j['files'] as $rel => $h ) {
			$p = WP_PLUGIN_DIR . '/' . $slug . '/' . $rel;
			if ( ! file_exists( $p ) ) {
				continue;
			}
			$md5 = is_array( $h['md5'] ) ? $h['md5'] : array( $h['md5'] );
			if ( ! in_array( md5_file( $p ), $md5, true ) ) {
				$bad[] = $rel;
			}
		}
		if ( $bad ) {
			$out[ $data['Name'] ] = $bad;
		}
	}
	return $out;
}

function zhdanov_sec_uploads_php() {
	$base = wp_upload_dir()['basedir'];
	$out  = array();
	if ( ! is_dir( $base ) ) {
		return $out;
	}
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		$ext = strtolower( $f->getExtension() );
		if ( ! in_array( $ext, array( 'php', 'phtml', 'php5', 'php7', 'phar', 'pht' ), true ) ) {
			continue;
		}
		$rel = str_replace( '\\', '/', substr( $f->getPathname(), strlen( $base ) + 1 ) );
		// наши заглушки «тишины» в служебных папках
		if ( 'index.php' === $f->getFilename() && $f->getSize() < 100 && false !== strpos( (string) file_get_contents( $f->getPathname() ), 'ilence' ) ) { // phpcs:ignore
			continue;
		}
		$out[] = $rel;
		if ( count( $out ) > 30 ) {
			break;
		}
	}
	return $out;
}

function zhdanov_sec_new_admins() {
	$out = array();
	foreach ( get_users( array( 'role' => 'administrator', 'number' => 50 ) ) as $u ) {
		if ( strtotime( $u->user_registered ) > time() - 14 * DAY_IN_SECONDS ) {
			$out[] = $u->user_login . ' (' . wp_date( 'd.m.Y', strtotime( $u->user_registered ) ) . ')';
		}
	}
	return $out;
}

/** Полная проверка. Пишет итог в zh_sec_last, при находках сообщает студии. */
function zhdanov_sec_scan( $notify = true ) {
	@set_time_limit( 300 ); // phpcs:ignore
	$t0  = microtime( true );
	$res = array(
		'time'    => time(),
		'core'    => zhdanov_sec_core(),
		'plugins' => zhdanov_sec_plugins(),
		'uploads' => zhdanov_sec_uploads_php(),
		'admins'  => zhdanov_sec_new_admins(),
	);
	$res['sec']  = round( microtime( true ) - $t0, 1 );
	$res['bad']  = zhdanov_sec_problems( $res );
	$prev        = get_option( 'zh_sec_last' );
	update_option( 'zh_sec_last', $res, false );
	if ( function_exists( 'zhdanov_log' ) ) {
		zhdanov_log( 'Проверка безопасности: ' . ( $res['bad'] ? implode( '; ', $res['bad'] ) : 'всё чисто' ) );
	}
	// сообщаем, если есть находки и они новые (не повторяем то же самое каждую неделю)
	if ( $notify && $res['bad'] && ( ! is_array( $prev ) || $prev['bad'] !== $res['bad'] ) ) {
		$token = trim( (string) zhdanov_core_opt( 'studio_tg_token' ) );
		$chat  = trim( (string) zhdanov_core_opt( 'studio_tg_chat' ) );
		if ( $token && $chat && function_exists( 'zhdanov_core_tg_send' ) ) {
			zhdanov_core_tg_send( $token, $chat, '🛡 ' . wp_parse_url( home_url(), PHP_URL_HOST ) . ": проверка безопасности\n" . implode( "\n", $res['bad'] ) . "\n" . admin_url( 'admin.php?page=zhdanov-security' ) );
		}
	}
	return $res;
}

/** Список проблем человеческим языком. */
function zhdanov_sec_problems( $res ) {
	$bad = array();
	if ( ! empty( $res['core']['changed'] ) ) {
		$bad[] = 'Изменены файлы WordPress (' . count( $res['core']['changed'] ) . '): ' . implode( ', ', array_slice( $res['core']['changed'], 0, 5 ) );
	}
	if ( ! empty( $res['core']['extra'] ) ) {
		$bad[] = 'Лишние PHP-файлы в WordPress (' . count( $res['core']['extra'] ) . '): ' . implode( ', ', array_slice( $res['core']['extra'], 0, 5 ) );
	}
	foreach ( (array) $res['plugins'] as $name => $files ) {
		$bad[] = 'Изменён плагин «' . $name . '» (' . count( $files ) . ' файлов): ' . implode( ', ', array_slice( $files, 0, 3 ) );
	}
	if ( ! empty( $res['uploads'] ) ) {
		$bad[] = 'PHP-файлы в папке загрузок (там их быть не должно): ' . implode( ', ', array_slice( $res['uploads'], 0, 5 ) );
	}
	if ( ! empty( $res['admins'] ) ) {
		$bad[] = 'Новые администраторы за 14 дней: ' . implode( ', ', $res['admins'] ) . '. Если это не вы, срочно проверьте.';
	}
	return $bad;
}

/* Раз в неделю */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'zhdanov_security_weekly' ) ) {
		wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'weekly', 'zhdanov_security_weekly' );
	}
} );
add_action( 'zhdanov_security_weekly', function () {
	zhdanov_sec_scan( true );
} );
add_action( 'zhdanov_security_bg', function () {
	zhdanov_sec_scan( true );
} );

/* Страница */
add_action( 'admin_menu', function () {
	add_submenu_page( 'zhdanov-core', 'Безопасность', 'Безопасность', 'manage_options', 'zhdanov-security', 'zhdanov_sec_page' );
}, 29 );

add_action( 'admin_post_zhdanov_security', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Нет прав.', 403 );
	}
	check_admin_referer( 'zhdanov_security' );
	zhdanov_sec_scan( true );
	wp_safe_redirect( admin_url( 'admin.php?page=zhdanov-security&done=1' ) );
	exit;
} );

function zhdanov_sec_page() {
	$l = get_option( 'zh_sec_last' );
	echo '<div class="wrap"><h1>Безопасность</h1>';
	echo '<p>Раз в неделю плагин сверяет файлы WordPress и плагинов с эталоном wordpress.org, ищет PHP-файлы в папке загрузок (частый признак взлома) и новых администраторов. Если что-то нашлось, студии приходит сообщение в Telegram. Вход защищают: ограничение попыток, вход с кодом из приложения (Профиль), выключенный XML-RPC.</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'zhdanov_security' );
	echo '<input type="hidden" name="action" value="zhdanov_security">';
	submit_button( 'Проверить сейчас', 'primary', 'zh_sec_go', false );
	echo ' <span style="color:#646970">займёт 10–60 секунд</span></form>';
	if ( is_array( $l ) ) {
		echo '<h2>Последняя проверка: ' . esc_html( wp_date( 'd.m.Y H:i', $l['time'] ) ) . ' (' . esc_html( $l['sec'] ) . ' с)</h2>';
		if ( ! empty( $l['core']['error'] ) ) {
			echo '<p style="color:#996800">Файлы WordPress: ' . esc_html( $l['core']['error'] ) . '.</p>';
		}
		if ( $l['bad'] ) {
			echo '<div class="notice notice-error inline" id="zh-sec-bad"><ul style="list-style:disc;margin-left:1.5em">';
			foreach ( $l['bad'] as $b ) {
				echo '<li>' . esc_html( $b ) . '</li>';
			}
			echo '</ul><p>Что делать: напишите студии. Изменённые файлы WordPress чинит «Обновления → Переустановить», плагины: удалить и поставить заново; PHP в загрузках удалить, сменить пароли администраторов.</p></div>';
		} else {
			echo '<p id="zh-sec-ok" style="color:#007017;font-size:15px">✓ Всё чисто: файлы WordPress и плагинов совпадают с эталоном, в загрузках нет PHP, новых администраторов нет.</p>';
		}
	} else {
		echo '<p><i>Проверок ещё не было.</i></p>';
	}
	echo '</div>';
}
