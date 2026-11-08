<?php
/**
 * Журнал действий: кто, когда и что поменял на сайте. Последние 400 записей.
 * Входы (и по ссылке), неудачные входы, публикация / правка / удаление страниц и записей,
 * плагины и темы, настройки «Жданов», новые пользователи и смена ролей, перенос концепта, откат, «Заменить везде».
 * Плюс PHP-ошибки, из-за которых падали страницы (последние 30). Сводка попадает в еженедельный отчёт.
 *
 * @package zhdanov-core
 */

defined( 'ABSPATH' ) || exit;

function zhdanov_log( $act, $obj = '', $uid = null ) {
	$uid = null === $uid ? get_current_user_id() : (int) $uid;
	$u   = $uid ? get_userdata( $uid ) : null;
	$log = (array) get_option( 'zh_activity', array() );
	$log[] = array(
		't'   => time(),
		'who' => $u ? $u->display_name : ( wp_doing_cron() ? 'по расписанию' : 'гость' ),
		'act' => $act,
		'obj' => mb_substr( wp_strip_all_tags( (string) $obj ), 0, 160 ),
	);
	if ( count( $log ) > 400 ) {
		$log = array_slice( $log, -400 );
	}
	update_option( 'zh_activity', $log, false );
}

/* ---------- Что записываем ---------- */

add_action( 'wp_login', function ( $login, $user ) {
	zhdanov_log( did_action( 'zhdanov_magic_login' ) ? 'вход по ссылке' : 'вход', '', $user->ID );
}, 10, 2 );
add_action( 'wp_login_failed', function ( $login ) {
	zhdanov_log( 'неудачный вход', 'логин «' . sanitize_user( $login ) . '»', 0 );
} );

add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) || in_array( $post->post_type, array( 'zh_lead', 'zh_csnap', 'revision', 'customize_changeset', 'oembed_cache', 'wp_global_styles', 'user_request' ), true ) || 'auto-draft' === $new ) {
		return;
	}
	$type = get_post_type_object( $post->post_type );
	$what = ( $type ? $type->labels->singular_name : $post->post_type ) . ' «' . get_the_title( $post ) . '»';
	if ( 'publish' === $new && 'publish' !== $old ) {
		zhdanov_log( 'опубликовано', $what );
	} elseif ( 'trash' === $new ) {
		zhdanov_log( 'в корзину', $what );
	} elseif ( 'publish' === $old && 'publish' !== $new ) {
		zhdanov_log( 'снято с публикации', $what );
	}
}, 10, 3 );
add_action( 'post_updated', function ( $id, $after, $before ) {
	if ( 'publish' === $after->post_status && 'publish' === $before->post_status && ! wp_is_post_revision( $id ) && ! in_array( $after->post_type, array( 'zh_lead', 'zh_csnap', 'wp_global_styles' ), true ) && ( $after->post_content !== $before->post_content || $after->post_title !== $before->post_title ) ) {
		$type = get_post_type_object( $after->post_type );
		zhdanov_log( 'изменено', ( $type ? $type->labels->singular_name : $after->post_type ) . ' «' . get_the_title( $after ) . '»' );
	}
}, 10, 3 );
add_action( 'before_delete_post', function ( $id ) {
	$p = get_post( $id );
	if ( $p && ! in_array( $p->post_type, array( 'revision', 'zh_lead', 'zh_csnap', 'customize_changeset', 'oembed_cache', 'user_request' ), true ) ) {
		zhdanov_log( 'удалено навсегда', $p->post_type . ' «' . $p->post_title . '»' );
	}
} );

add_action( 'activated_plugin', function ( $p ) {
	zhdanov_log( 'плагин включён', dirname( $p ) );
} );
add_action( 'deactivated_plugin', function ( $p ) {
	zhdanov_log( 'плагин выключен', dirname( $p ) );
} );
add_action( 'upgrader_process_complete', function ( $up, $o ) {
	if ( isset( $o['type'] ) && in_array( $o['type'], array( 'plugin', 'theme', 'core' ), true ) ) {
		$items = isset( $o['plugins'] ) ? $o['plugins'] : ( isset( $o['themes'] ) ? $o['themes'] : array() );
		zhdanov_log( 'обновление: ' . $o['type'], $items ? implode( ', ', array_map( 'dirname', (array) $items ) ) : ( isset( $up->new_plugin_data['Name'] ) ? $up->new_plugin_data['Name'] : '' ) );
	}
}, 10, 2 );
add_action( 'switch_theme', function ( $name ) {
	zhdanov_log( 'смена темы', $name );
} );
add_action( 'update_option_zhdanov_core', function () {
	zhdanov_log( 'настройки «Жданов» изменены' );
} );
add_action( 'user_register', function ( $id ) {
	$u = get_userdata( $id );
	zhdanov_log( 'новый пользователь', $u ? $u->display_name . ' (' . implode( ', ', $u->roles ) . ')' : '' );
} );
add_action( 'set_user_role', function ( $id, $role, $old ) {
	if ( $old ) {
		zhdanov_log( 'смена прав', get_userdata( $id )->display_name . ': ' . implode( ', ', $old ) . ' → ' . $role );
	}
}, 10, 3 );
add_action( 'zhdanov_concept_imported', function ( $slug ) {
	zhdanov_log( 'перенос концепта', $slug );
} );

/* ---------- PHP-ошибки, из-за которых падала страница ---------- */

register_shutdown_function( function () {
	$e = error_get_last();
	if ( ! $e || ! in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR ), true ) || ! function_exists( 'update_option' ) ) {
		return;
	}
	$list   = (array) get_option( 'zh_php_errors', array() );
	$file   = str_replace( wp_normalize_path( ABSPATH ), '', wp_normalize_path( $e['file'] ) );
	$list[] = array( 't' => time(), 'msg' => mb_substr( strtok( $e['message'], "\n" ), 0, 200 ), 'where' => $file . ':' . $e['line'], 'url' => isset( $_SERVER['REQUEST_URI'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), 0, 120 ) : '' );
	update_option( 'zh_php_errors', array_slice( $list, -30 ), false );
} );

/* ---------- Страница «Журнал действий» ---------- */

add_action( 'admin_menu', function () {
	add_submenu_page( 'zhdanov-core', 'Журнал действий', 'Журнал действий', 'manage_options', 'zhdanov-activity', function () {
		$log = array_reverse( (array) get_option( 'zh_activity', array() ) );
		$err = array_reverse( (array) get_option( 'zh_php_errors', array() ) );
		echo '<div class="wrap"><h1>Журнал действий</h1><p style="max-width:760px">Кто и что менял на сайте: входы, публикации, удаления, плагины, настройки. Хранятся последние 400 записей. Если что-то сломалось, здесь видно, что делали перед этим.</p>';
		if ( $err ) {
			echo '<h2>Ошибки PHP (страница падала)</h2><table class="widefat striped" style="max-width:1100px"><thead><tr><th>Когда</th><th>Ошибка</th><th>Где</th><th>Адрес</th></tr></thead><tbody>';
			foreach ( array_slice( $err, 0, 10 ) as $e ) {
				echo '<tr><td>' . esc_html( wp_date( 'd.m H:i', $e['t'] ) ) . '</td><td>' . esc_html( $e['msg'] ) . '</td><td><code>' . esc_html( $e['where'] ) . '</code></td><td>' . esc_html( $e['url'] ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '<h2>Действия</h2><table class="widefat striped" style="max-width:1100px"><thead><tr><th style="width:110px">Когда</th><th style="width:180px">Кто</th><th style="width:200px">Что</th><th>Подробно</th></tr></thead><tbody>';
		if ( ! $log ) {
			echo '<tr><td colspan="4">Пока пусто.</td></tr>';
		}
		foreach ( array_slice( $log, 0, 200 ) as $l ) {
			echo '<tr><td>' . esc_html( wp_date( 'd.m H:i', $l['t'] ) ) . '</td><td>' . esc_html( $l['who'] ) . '</td><td>' . esc_html( $l['act'] ) . '</td><td>' . esc_html( $l['obj'] ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	} );
}, 46 );

/** Сводка за неделю для отчёта. */
function zhdanov_activity_week() {
	$since = time() - WEEK_IN_SECONDS;
	$log   = array_filter( (array) get_option( 'zh_activity', array() ), function ( $l ) use ( $since ) {
		return $l['t'] > $since;
	} );
	$err   = array_filter( (array) get_option( 'zh_php_errors', array() ), function ( $e ) use ( $since ) {
		return $e['t'] > $since;
	} );
	$cnt   = array();
	foreach ( $log as $l ) {
		$cnt[ $l['act'] ] = isset( $cnt[ $l['act'] ] ) ? $cnt[ $l['act'] ] + 1 : 1;
	}
	$important = array_values( array_filter( $log, function ( $l ) {
		return (bool) preg_match( '/плагин|обновление|тема|удалено|новый пользователь|смена прав|по ссылке/u', $l['act'] );
	} ) );
	return array( 'count' => $cnt, 'important' => array_slice( array_reverse( $important ), 0, 6 ), 'errors' => array_values( $err ) );
}
