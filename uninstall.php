<?php
/**
 * Удаление плагина: убираем только служебное (расписание задач, временные данные).
 * Записи, заявки, оплаты, настройки и копии сайта НЕ удаляются: их можно вернуть, поставив плагин снова.
 * Полная очистка: Жданов → Настройки → «При удалении плагина стереть всё» (галочка).
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
foreach ( array( 'zhdanov_weekly', 'zhdanov_booking_remind' ) as $hook ) {
	wp_clear_scheduled_hook( $hook );
}
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_zh\_%' OR option_name LIKE '\_transient\_timeout\_zh\_%'" ); // phpcs:ignore
$o = (array) get_option( 'zhdanov_core', array() );
if ( ! empty( $o['wipe_on_uninstall'] ) ) {
	foreach ( array( 'zh_lead', 'zh_order', 'zh_booking' ) as $pt ) {
		foreach ( get_posts( array( 'post_type' => $pt, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
			wp_delete_post( $id, true );
		}
	}
	foreach ( array( 'zhdanov_core', 'zhdanov_redirects', 'zhdanov_redirect_hits', 'zhdanov_404', 'zhdanov_rewrite_hash', 'zhdanov_backup_key', 'zhdanov_backup_last', 'zhdanov_design_backup', 'zhdanov_price_num_v1', 'zhdanov_price_num_v2' ) as $opt ) {
		delete_option( $opt );
	}
}
