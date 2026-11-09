<?php
/**
 * Блок «Сайт на сопровождении» на первом экране админки (как white-label виджет WP Umbrella / ManageWP):
 * клиент видит, что за сайтом следят: проверка страниц, копия, обновления, безопасность, SSL.
 *
 * @package zhdanov-core
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_dashboard_setup', function () {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	wp_add_dashboard_widget( 'zhdanov_care', 'Сайт на сопровождении Zhdanov Sites', 'zhdanov_care_widget', null, null, 'side', 'high' );
}, 30 );

function zhdanov_care_rows() {
	$rows = array();
	$ago  = function ( $t ) {
		$d = (int) floor( ( time() - $t ) / DAY_IN_SECONDS );
		return 0 === $d ? 'сегодня' : ( 1 === $d ? 'вчера' : $d . ' дн. назад' );
	};
	$pc = get_option( 'zh_pc_last' );
	if ( is_array( $pc ) ) {
		$rows[] = array( ! $pc['bad'], 'Страницы сайта проверены ' . $ago( $pc['time'] ) . ( $pc['bad'] ? ': есть замечания, студия разбирается' : ', всё открывается' ) );
	}
	$bf = function_exists( 'zhdanov_backup_dir' ) ? glob( zhdanov_backup_dir() . '/recept-*.zip' ) : array();
	if ( $bf ) {
		$t      = max( array_map( 'filemtime', $bf ) );
		$rows[] = array( $t > time() - 10 * DAY_IN_SECONDS, 'Резервная копия: ' . $ago( $t ) );
	}
	$sec = get_option( 'zh_sec_last' );
	if ( is_array( $sec ) ) {
		$rows[] = array( ! $sec['bad'], 'Проверка безопасности ' . $ago( $sec['time'] ) . ( $sec['bad'] ? ': студия разбирается' : ': чисто' ) );
	}
	$last = get_option( 'zh_selfupdate_last' );
	if ( is_array( $last ) ) {
		$rows[] = array( true, 'Плагин сайта обновлён ' . $ago( $last['time'] ) . ' (' . ZHDANOV_CORE_VERSION . ')' );
	}
	$ssl = function_exists( 'zhdanov_report_ssl_days' ) ? zhdanov_report_ssl_days() : null;
	if ( is_int( $ssl ) && $ssl > 0 ) {
		$rows[] = array( $ssl > 14, 'Сертификат https действует ещё ' . $ssl . ' дн.' );
	}
	if ( function_exists( 'zhdanov_ls_count' ) ) {
		$n = zhdanov_ls_count( 'new' );
		if ( $n ) {
			$rows[] = array( false, 'Новых заявок без статуса: ' . $n . ' (откройте «Заявки»)' );
		}
	}
	return $rows;
}

function zhdanov_care_widget() {
	$rows = zhdanov_care_rows();
	if ( ! $rows ) {
		echo '<p>Проверки, копии и обновления начнутся автоматически в ближайшие дни.</p>';
	} else {
		echo '<ul style="margin:0">';
		foreach ( $rows as $r ) {
			echo '<li style="display:flex;gap:8px;align-items:flex-start;margin:0 0 8px"><span aria-hidden="true" style="flex:none;width:18px;height:18px;border-radius:50%;background:' . ( $r[0] ? '#00a32a' : '#dba617' ) . ';color:#fff;font-size:12px;line-height:18px;text-align:center">' . ( $r[0] ? '✓' : '!' ) . '</span><span>' . esc_html( $r[1] ) . '</span></li>';
		}
		echo '</ul>';
	}
	$sup = function_exists( 'zhdanov_help_support_link' ) ? zhdanov_help_support_link() : array( '', '' );
	echo '<p style="margin:10px 0 0;color:#50575e">Обновления, копии и проверки делаются сами. Вопросы и правки: ' . ( $sup[0] ? '<a href="' . esc_url( $sup[0] ) . '" target="_blank" rel="noopener">' . esc_html( $sup[1] ) . '</a>' : esc_html( $sup[1] ) ) . '.</p>';
}
