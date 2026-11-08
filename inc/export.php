<?php
/**
 * Выгрузка заявок, записей и оплат в CSV (Excel и Google Таблицы, разделитель «;», UTF-8 с BOM).
 * Кнопка над списком в админке.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zhdanov_export_cols( $type ) {
	$c = array(
		'zh_lead'    => array( 'Дата' => 'date', 'Имя' => '_zh_name', 'Телефон' => '_zh_phone', 'Почта' => '_zh_email', 'Сообщение' => 'content', 'Страница' => '_zh_page', 'Откуда' => '_zh_src' ),
		'zh_booking' => array( 'Создана' => 'date', 'Занятие' => 'class', 'Дата занятия' => '_zh_bdate', 'Время' => '_zh_btime', 'Имя' => '_zh_name', 'Телефон' => '_zh_phone', 'Почта' => '_zh_email', 'Комментарий' => '_zh_note', 'Статус' => '_zh_bst' ),
		'zh_order'   => array( 'Дата' => 'date', 'Что' => 'title', 'Сумма' => '_zh_sum', 'Статус' => '_zh_st', 'Имя' => '_zh_name', 'Телефон' => '_zh_phone', 'Почта' => '_zh_email', 'Сертификат' => '_zh_code', 'Номер в банке' => '_zh_pid' ),
		'zh_wait'    => array( 'Дата' => 'date', 'Занятие' => 'class', 'Дата занятия' => '_zh_bdate', 'Имя' => '_zh_name', 'Телефон' => '_zh_phone', 'Почта' => '_zh_email', 'Сообщили' => '_zh_notified' ),
	);
	return isset( $c[ $type ] ) ? $c[ $type ] : array();
}

add_action( 'admin_post_zhdanov_export', function () {
	$type = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : ''; // phpcs:ignore
	if ( ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'zh_export_' . $type ) || ! zhdanov_export_cols( $type ) ) {
		wp_die( 'Нет доступа.' );
	}
	$cols = zhdanov_export_cols( $type );
	$st   = array( 'new' => 'записан', 'came' => 'пришёл', 'noshow' => 'не пришёл', 'canceled' => 'отменено', 'pending' => 'ждёт оплаты', 'paid' => 'оплачено' );
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $type . '-' . wp_date( 'Y-m-d' ) . '.csv"' );
	$out = fopen( 'php://output', 'w' ); // phpcs:ignore
	fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore
	fputcsv( $out, array_keys( $cols ), ';', '"', '\\' );
	foreach ( get_posts( array( 'post_type' => $type, 'post_status' => 'any', 'numberposts' => 5000, 'orderby' => 'date', 'order' => 'DESC' ) ) as $p ) {
		$row = array();
		foreach ( $cols as $k ) {
			if ( 'date' === $k ) {
				$v = get_the_date( 'd.m.Y H:i', $p );
			} elseif ( 'content' === $k ) {
				$v = $p->post_content;
			} elseif ( '_zh_name' === $k && 'zh_lead' === $type && ! get_post_meta( $p->ID, '_zh_name', true ) ) {
				$v = trim( explode( '·', $p->post_title )[0] ); // старые заявки: имя в заголовке
			} elseif ( 'title' === $k ) {
				$v = $p->post_title;
			} elseif ( 'class' === $k ) {
				$v = get_the_title( (int) get_post_meta( $p->ID, '_zh_class', true ) );
			} else {
				$v = (string) get_post_meta( $p->ID, $k, true );
				if ( in_array( $k, array( '_zh_bst', '_zh_st' ), true ) && isset( $st[ $v ] ) ) {
					$v = $st[ $v ];
				}
				if ( '_zh_bdate' === $k && $v ) {
					$v = wp_date( 'd.m.Y', strtotime( $v . ' 12:00' ) );
				}
			}
			if ( preg_match( '/^[=@]/', $v ) || ( preg_match( '/^[+\-]/', $v ) && ! preg_match( '/^\+?[\d\s()\-]+$/', $v ) ) ) {
				$v = "'" . $v; // защита от формул в Excel (телефоны вида +7 999 не трогаем)
			}
			$row[] = $v;
		}
		fputcsv( $out, $row, ';', '"', '\\' );
	}
	fclose( $out ); // phpcs:ignore
	exit;
} );

/* Кнопка «Скачать в Excel» над списками. */
add_action( 'admin_notices', function () {
	$s = get_current_screen();
	if ( ! $s || 'edit' !== $s->base || ! zhdanov_export_cols( $s->post_type ) ) {
		return;
	}
	$u = wp_nonce_url( admin_url( 'admin-post.php?action=zhdanov_export&type=' . $s->post_type ), 'zh_export_' . $s->post_type );
	echo '<div class="notice notice-info" style="display:flex;align-items:center;gap:12px"><p><a class="button button-primary" href="' . esc_url( $u ) . '">Скачать всё в Excel (CSV)</a></p><p>Открывается в Excel и Google Таблицах (Файл → Импорт).</p></div>';
} );
