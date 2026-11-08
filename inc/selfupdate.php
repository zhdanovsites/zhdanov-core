<?php
/**
 * Обновление плагина и темы по ссылке (без загрузки файла с компьютера).
 *
 * Жданов → «Обновить по ссылке»: вставили ссылку на архив (Google Drive, GitHub, zhdanovsites.com),
 * плагин скачивает его на сервер, проверяет, что внутри zhdanov-core или zhdanov-theme, и ставит поверх.
 * Настройки, страницы, тексты не меняются: они в базе.
 *
 * @package zhdanov-core
 */

defined( 'ABSPATH' ) || exit;

/** Хосты, с которых можно брать архивы. */
function zhdanov_su_hosts() {
	return (array) apply_filters( 'zhdanov_selfupdate_hosts', array( 'drive.google.com', 'drive.usercontent.google.com', 'docs.google.com', 'github.com', 'raw.githubusercontent.com', 'objects.githubusercontent.com', 'release-assets.githubusercontent.com', 'zhdanovsites.com', 'www.zhdanovsites.com' ) );
}

/** Ссылка «Открыть в Drive» → прямая ссылка на скачивание. */
function zhdanov_su_normalize( $url ) {
	$url = trim( (string) $url );
	if ( preg_match( '~drive\.google\.com/(?:file/d/|open\?id=|uc\?(?:[^#]*&)?id=)([A-Za-z0-9_\-]{10,})~', $url, $m ) ) {
		return 'https://drive.usercontent.google.com/download?id=' . $m[1] . '&export=download&confirm=t';
	}
	return $url;
}

/** Что внутри архива: [ 'type' => core|theme, 'version' => '…' ] или WP_Error. */
function zhdanov_su_inspect( $file ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'zip', 'На хостинге нет модуля ZipArchive, обновите плагин вручную.' );
	}
	$z = new ZipArchive();
	if ( true !== $z->open( $file ) ) {
		return new WP_Error( 'zip', 'Скачался не архив. Проверьте, что в Google Drive доступ «Все, у кого есть ссылка».' );
	}
	$out = new WP_Error( 'zip', 'В архиве нет плагина zhdanov-core или темы zhdanov-theme.' );
	foreach ( array( 'core' => 'zhdanov-core/zhdanov-core.php', 'theme' => 'zhdanov-theme/style.css' ) as $type => $path ) {
		$src = $z->getFromName( $path );
		if ( false !== $src && preg_match( '/^[ \t\/*#@]*Version:\s*([0-9a-z.\-]+)/mi', $src, $m ) ) {
			$out = array( 'type' => $type, 'version' => $m[1] );
			break;
		}
	}
	$z->close();
	return $out;
}

/** Скачать и поставить. Возвращает текст результата или WP_Error. */
function zhdanov_su_run( $url ) {
	$url  = zhdanov_su_normalize( $url );
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	if ( 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) || ! in_array( $host, zhdanov_su_hosts(), true ) ) {
		return new WP_Error( 'host', 'Ссылка должна вести на Google Drive, GitHub или zhdanovsites.com (https).' );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	$tmp = download_url( $url, 120 );
	if ( is_wp_error( $tmp ) ) {
		return new WP_Error( 'dl', 'Не скачалось: ' . $tmp->get_error_message() );
	}
	$info = zhdanov_su_inspect( $tmp );
	if ( is_wp_error( $info ) ) {
		@unlink( $tmp ); // phpcs:ignore
		return $info;
	}
	// download_url даёт .tmp, установщику нужен .zip
	$zip = $tmp . '.zip';
	@rename( $tmp, $zip ); // phpcs:ignore
	if ( ! file_exists( $zip ) ) {
		$zip = $tmp;
	}

	$skin = new Automatic_Upgrader_Skin();
	if ( 'core' === $info['type'] ) {
		$was      = ZHDANOV_CORE_VERSION;
		$basename = plugin_basename( ZHDANOV_CORE_DIR . 'zhdanov-core.php' );
		$active   = is_plugin_active( $basename );
		$up       = new Plugin_Upgrader( $skin );
		$res      = $up->install( $zip, array( 'overwrite_package' => true ) );
		if ( $active && ! is_plugin_active( $basename ) ) {
			activate_plugin( $basename );
		}
		$name = 'Плагин';
	} else {
		$t    = wp_get_theme( 'zhdanov-theme' );
		$was  = $t->exists() ? $t->get( 'Version' ) : '';
		$up   = new Theme_Upgrader( $skin );
		$res  = $up->install( $zip, array( 'overwrite_package' => true ) );
		$name = 'Тема';
	}
	@unlink( $zip ); // phpcs:ignore
	if ( is_wp_error( $res ) || ! $res ) {
		$msg = is_wp_error( $res ) ? $res->get_error_message() : implode( ' ', array_map( 'wp_strip_all_tags', (array) $skin->get_upgrade_messages() ) );
		return new WP_Error( 'install', 'Не поставилось: ' . ( $msg ? $msg : 'хостинг не дал записать файлы.' ) );
	}
	delete_site_transient( 'zhdanov_update_info' );
	$text = $name . ' ' . ( $was ? $was . ' → ' : '' ) . $info['version'];
	update_option( 'zh_selfupdate_last', array( 'time' => time(), 'text' => $text, 'url' => $url ), false );
	if ( function_exists( 'zhdanov_log' ) ) {
		zhdanov_log( 'Обновление по ссылке: ' . $text );
	}
	return $text;
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'zhdanov-core', 'Обновить плагин по ссылке', 'Обновить по ссылке', 'update_plugins', 'zhdanov-selfupdate', 'zhdanov_su_page' );
}, 30 );

add_action( 'admin_post_zhdanov_selfupdate', function () {
	if ( ! current_user_can( 'update_plugins' ) || ! current_user_can( 'install_plugins' ) ) {
		wp_die( 'Нет прав на обновление плагинов.', 403 );
	}
	check_admin_referer( 'zhdanov_selfupdate' );
	@set_time_limit( 300 ); // phpcs:ignore
	$r = zhdanov_su_run( isset( $_POST['zh_url'] ) ? esc_url_raw( wp_unslash( $_POST['zh_url'] ) ) : '' );
	set_transient( 'zh_su_msg_' . get_current_user_id(), is_wp_error( $r ) ? array( 'err', $r->get_error_message() ) : array( 'ok', 'Готово: ' . $r ), 300 );
	wp_safe_redirect( admin_url( 'admin.php?page=zhdanov-selfupdate' ) );
	exit;
} );

function zhdanov_su_page() {
	$msg  = get_transient( 'zh_su_msg_' . get_current_user_id() );
	$last = get_option( 'zh_selfupdate_last' );
	delete_transient( 'zh_su_msg_' . get_current_user_id() );
	echo '<div class="wrap"><h1>Обновить плагин по ссылке</h1>';
	if ( $msg ) {
		echo '<div id="zh-su-result" class="notice notice-' . ( 'ok' === $msg[0] ? 'success' : 'error' ) . '"><p>' . esc_html( $msg[1] ) . '</p></div>';
	}
	echo '<p>Сейчас стоит: плагин <b>' . esc_html( ZHDANOV_CORE_VERSION ) . '</b>';
	$t = wp_get_theme( 'zhdanov-theme' );
	if ( $t->exists() ) {
		echo ', тема <b>' . esc_html( $t->get( 'Version' ) ) . '</b>';
	}
	echo '.</p>';
	echo '<p>Вставьте ссылку на архив zhdanov-core-vX.zip или zhdanov-theme-vX.zip: Google Drive (доступ «Все, у кого есть ссылка»), GitHub или zhdanovsites.com. Плагин скачает архив, проверит, что внутри наш плагин или тема, и поставит поверх. Страницы, тексты и настройки не меняются.</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'zhdanov_selfupdate' );
	echo '<input type="hidden" name="action" value="zhdanov_selfupdate">';
	echo '<p><input type="url" name="zh_url" id="zh_url" class="large-text" required placeholder="https://drive.google.com/file/d/…/view"></p>';
	submit_button( 'Обновить', 'primary', 'zh_su_go', false );
	echo '</form>';
	if ( is_array( $last ) ) {
		echo '<p style="margin-top:20px;color:#555">Последнее обновление по ссылке: ' . esc_html( wp_date( 'd.m.Y H:i', $last['time'] ) . ' · ' . $last['text'] ) . '</p>';
	}
	do_action( 'zhdanov_su_page_after' );
	echo '</div>';
}

/* ---------- Автообновление с GitHub (своё, не зависит от хостинга) ---------- */

add_action( 'init', function () {
	if ( get_option( 'zh_auto_update' ) && ! wp_next_scheduled( 'zhdanov_auto_update' ) ) {
		wp_schedule_event( time() + 300, 'twicedaily', 'zhdanov_auto_update' );
	} elseif ( ! get_option( 'zh_auto_update' ) && wp_next_scheduled( 'zhdanov_auto_update' ) ) {
		wp_clear_scheduled_hook( 'zhdanov_auto_update' );
	}
} );

/** Проверить GitHub и поставить новое. Возвращает список строк о сделанном. */
function zhdanov_su_auto() {
	if ( ! function_exists( 'zhdanov_update_info' ) ) {
		return array();
	}
	$info = zhdanov_update_info( true );
	$done = array();
	if ( ! empty( $info['core'] ) && version_compare( $info['core']['version'], ZHDANOV_CORE_VERSION, '>' ) ) {
		$r      = zhdanov_su_run( $info['core']['package'] );
		$done[] = is_wp_error( $r ) ? '❗ Плагин ' . $info['core']['version'] . ': ' . $r->get_error_message() : $r;
	}
	$t = wp_get_theme( 'zhdanov-theme' );
	if ( $t->exists() && ! empty( $info['theme'] ) && version_compare( $info['theme']['version'], $t->get( 'Version' ), '>' ) ) {
		$r      = zhdanov_su_run( $info['theme']['package'] );
		$done[] = is_wp_error( $r ) ? '❗ Тема ' . $info['theme']['version'] . ': ' . $r->get_error_message() : $r;
	}
	if ( $done ) {
		update_option( 'zh_auto_update_last', array( 'time' => time(), 'text' => implode( '; ', $done ) ), false );
		$token = trim( (string) zhdanov_core_opt( 'studio_tg_token' ) );
		$chat  = trim( (string) zhdanov_core_opt( 'studio_tg_chat' ) );
		if ( $token && $chat && function_exists( 'zhdanov_core_tg_send' ) ) {
			zhdanov_core_tg_send( $token, $chat, '🔄 ' . wp_parse_url( home_url(), PHP_URL_HOST ) . ': ' . implode( '; ', $done ) );
		}
	}
	return $done;
}

add_action( 'zhdanov_auto_update', function () {
	@set_time_limit( 300 ); // phpcs:ignore
	zhdanov_su_auto();
} );

add_action( 'admin_post_zhdanov_autoupdate_opt', function () {
	if ( ! current_user_can( 'update_plugins' ) ) {
		wp_die( 'Нет прав.', 403 );
	}
	check_admin_referer( 'zhdanov_autoupdate_opt' );
	update_option( 'zh_auto_update', empty( $_POST['zh_auto'] ) ? 0 : 1, false );
	$msg = array( 'ok', empty( $_POST['zh_auto'] ) ? 'Автообновление выключено.' : 'Автообновление включено: проверка дважды в день.' );
	if ( ! empty( $_POST['zh_now'] ) ) {
		@set_time_limit( 300 ); // phpcs:ignore
		$d   = zhdanov_su_auto();
		$msg = array( 'ok', $d ? 'Готово: ' . implode( '; ', $d ) : 'Новых версий нет, стоит последняя.' );
	}
	set_transient( 'zh_su_msg_' . get_current_user_id(), $msg, 300 );
	wp_safe_redirect( admin_url( 'admin.php?page=zhdanov-selfupdate' ) );
	exit;
} );

add_action( 'zhdanov_su_page_after', function () {
	$on   = (bool) get_option( 'zh_auto_update' );
	$last = get_option( 'zh_auto_update_last' );
	$info = function_exists( 'zhdanov_update_info' ) ? zhdanov_update_info() : array();
	echo '<h2 style="margin-top:28px">Автообновление</h2>';
	echo '<p>Плагин сам проверяет новые версии студии (GitHub zhdanovsites/zhdanov-core) дважды в день и ставит их. О каждом обновлении приходит сообщение в Telegram студии.';
	if ( ! empty( $info['core']['version'] ) ) {
		echo ' Последняя версия: <b>' . esc_html( $info['core']['version'] ) . '</b>.';
	}
	echo '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'zhdanov_autoupdate_opt' );
	echo '<input type="hidden" name="action" value="zhdanov_autoupdate_opt">';
	echo '<p><label><input type="checkbox" name="zh_auto" id="zh_auto" value="1"' . checked( $on, true, false ) . '> Обновляться автоматически</label></p>';
	echo '<p><button class="button button-primary" name="zh_save" value="1">Сохранить</button> <button class="button" name="zh_now" id="zh_now" value="1">Сохранить и проверить сейчас</button></p></form>';
	if ( is_array( $last ) ) {
		echo '<p style="color:#555">Последнее автообновление: ' . esc_html( wp_date( 'd.m.Y H:i', $last['time'] ) . ' · ' . $last['text'] ) . '</p>';
	}
} );
