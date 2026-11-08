<?php
/**
 * Европейский режим (GDPR): для сайтов в ЕС и Великобритании.
 * - Баннер cookie с категориями (необходимые / аналитика / маркетинг), «Отклонить» наравне с «Принять».
 * - Google Analytics 4 с Google Consent Mode v2, Метрика и GA только после согласия.
 * - Журнал согласий (без личных данных: хеш адреса, выбор, время, страница) с выгрузкой CSV, хранится 12 месяцев.
 * - Форма «Выгрузить / удалить мои данные» ([zhdanov_privacy_request]) на штатных инструментах WordPress.
 * - Заявки с сайта попадают в выгрузку и удаление персональных данных WordPress.
 * - Шаблон политики конфиденциальности по GDPR (английский).
 *
 * @package zhdanov-core
 */

defined( 'ABSPATH' ) || exit;

/** Включён ли европейский режим: страна сайта «ЕС / Великобритания». */
function zhdanov_gdpr_on() {
	return 'eu' === zhdanov_core_opt( 'region' );
}

/* ---------- Данные для баннера на сайте ---------- */

add_action( 'wp_enqueue_scripts', function () {
	$extra = array(
		'ga'   => preg_match( '/^G-[A-Z0-9]{4,}$/', (string) zhdanov_core_opt( 'ga4' ) ) ? zhdanov_core_opt( 'ga4' ) : '',
		'gdpr' => zhdanov_gdpr_on() ? 1 : 0,
	);
	if ( zhdanov_gdpr_on() ) {
		$extra['consentUrl'] = rest_url( 'zhdanov/v1/consent' );
	}
	wp_add_inline_script( 'zhdanov-core-front', 'window.zhCore=Object.assign(window.zhCore||{},' . wp_json_encode( $extra ) . ');', 'before' );
}, 20 );

/* ---------- Журнал согласий ---------- */

function zhdanov_gdpr_table() {
	global $wpdb;
	return $wpdb->prefix . 'zh_consent';
}

add_action( 'admin_init', function () {
	if ( '1' === get_option( 'zh_consent_db' ) ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( 'CREATE TABLE ' . zhdanov_gdpr_table() . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		t datetime NOT NULL,
		who char(16) NOT NULL DEFAULT '',
		choice varchar(20) NOT NULL DEFAULT '',
		url varchar(190) NOT NULL DEFAULT '',
		PRIMARY KEY  (id),
		KEY t (t)
	) " . $wpdb->get_charset_collate() . ';' );
	update_option( 'zh_consent_db', '1', false );
} );

add_action( 'rest_api_init', function () {
	register_rest_route( 'zhdanov/v1', '/consent', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( $req ) {
			if ( ! zhdanov_gdpr_on() || '1' !== get_option( 'zh_consent_db' ) ) {
				return array( 'ok' => false );
			}
			$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
			$who = substr( hash_hmac( 'sha256', $ip . '|' . gmdate( 'Y-m' ), wp_salt( 'auth' ) ), 0, 16 ); // адрес не хранится, только его хеш
			$k   = 'zh_cs_' . $who;
			if ( (int) get_transient( $k ) > 20 ) { // защита от засорения журнала
				return array( 'ok' => false );
			}
			set_transient( $k, (int) get_transient( $k ) + 1, HOUR_IN_SECONDS );
			$c = (string) $req->get_param( 'c' );
			if ( ! in_array( $c, array( 'all', 'analytics', 'marketing', 'necessary' ), true ) ) {
				return array( 'ok' => false );
			}
			global $wpdb;
			$wpdb->insert( zhdanov_gdpr_table(), array( 't' => current_time( 'mysql', true ), 'who' => $who, 'choice' => $c, 'url' => mb_substr( sanitize_text_field( (string) $req->get_param( 'u' ) ), 0, 190 ) ) ); // phpcs:ignore
			return array( 'ok' => true );
		},
	) );
} );

/* ---------- Страница «Согласия (GDPR)» ---------- */

add_action( 'admin_menu', function () {
	if ( ! zhdanov_gdpr_on() ) {
		return;
	}
	add_submenu_page( 'zhdanov-core', 'Согласия на cookie (GDPR)', 'Согласия (GDPR)', 'manage_options', 'zhdanov-consent', 'zhdanov_gdpr_page' );
}, 40 );

function zhdanov_gdpr_page() {
	global $wpdb;
	$tb = zhdanov_gdpr_table();
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$tb} WHERE t < %s", gmdate( 'Y-m-d H:i:s', time() - YEAR_IN_SECONDS ) ) ); // phpcs:ignore -- храним 12 месяцев
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT choice, COUNT(*) n FROM {$tb} WHERE t > %s GROUP BY choice", gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ), OBJECT_K ); // phpcs:ignore
	$tot  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tb}" ); // phpcs:ignore
	$name = array( 'all' => 'Приняли всё', 'analytics' => 'Только аналитика', 'marketing' => 'Только маркетинг', 'necessary' => 'Отклонили (только необходимые)' );
	echo '<div class="wrap"><h1>Согласия на cookie (GDPR)</h1>';
	echo '<p style="max-width:760px">Журнал хранит выбор каждого посетителя в баннере: что выбрал, когда и на какой странице. Адрес посетителя не хранится, только его хеш: личных данных в журнале нет. Записи старше 12 месяцев удаляются сами. Журнал нужен, чтобы доказать проверяющему, что согласие спрашивали правильно.</p>';
	echo '<table class="widefat striped" style="max-width:520px"><thead><tr><th>За 30 дней</th><th>Сколько</th></tr></thead><tbody>';
	foreach ( $name as $k => $l ) {
		echo '<tr><td>' . esc_html( $l ) . '</td><td>' . (int) ( isset( $rows[ $k ] ) ? $rows[ $k ]->n : 0 ) . '</td></tr>';
	}
	echo '</tbody></table><p>Всего записей: ' . (int) $tot . '. <a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=zhdanov_consent_csv' ), 'zh_consent_csv' ) ) . '">Скачать журнал (CSV)</a></p>';
	echo '<h2>Запросы «выгрузить / удалить мои данные»</h2><p style="max-width:760px">Форма для посетителей: вставьте на страницу политики блок «Шорткод» с текстом <code>[zhdanov_privacy_request]</code> (в шаблоне политики GDPR она уже есть). Человек получает письмо для подтверждения, а запрос появляется в <a href="' . esc_url( admin_url( 'export-personal-data.php' ) ) . '">Инструменты → Экспорт персональных данных</a> и <a href="' . esc_url( admin_url( 'erase-personal-data.php' ) ) . '">Удаление персональных данных</a>. По закону ответить нужно в течение месяца. Заявки с сайта тоже попадают в выгрузку и удаление.</p></div>';
}

add_action( 'admin_post_zhdanov_consent_csv', function () {
	check_admin_referer( 'zh_consent_csv' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Нет доступа' );
	}
	global $wpdb;
	$tb = zhdanov_gdpr_table();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=consent-log-' . gmdate( 'Y-m-d' ) . '.csv' );
	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array( 'time_utc', 'visitor_hash', 'choice', 'page' ) );
	foreach ( (array) $wpdb->get_results( "SELECT t, who, choice, url FROM {$tb} ORDER BY id", ARRAY_N ) as $r ) { // phpcs:ignore
		fputcsv( $out, $r );
	}
	exit;
} );

/* ---------- Форма запроса по персональным данным ---------- */

add_shortcode( 'zhdanov_privacy_request', function () {
	$en  = function_exists( 'zhdanov_core_is_en' ) ? zhdanov_core_is_en() : false;
	$tr  = function ( $ru, $enx ) use ( $en ) {
		return $en || zhdanov_gdpr_on() ? $enx : $ru;
	};
	$msg = '';
	if ( isset( $_GET['zh_pr'] ) ) { // phpcs:ignore
		$msg = 'ok' === $_GET['zh_pr'] // phpcs:ignore
			? $tr( 'Готово: мы отправили письмо для подтверждения запроса. Откройте его и нажмите ссылку.', 'Done: we have sent you an email to confirm the request. Please open it and click the link.' )
			: $tr( 'Не получилось: проверьте адрес почты.', 'Something went wrong: please check the email address.' );
	}
	ob_start();
	echo '<form class="zh-privacy-req" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	if ( $msg ) {
		echo '<p role="status"><b>' . esc_html( $msg ) . '</b></p>';
	}
	echo '<input type="hidden" name="action" value="zhdanov_privacy_request">';
	wp_nonce_field( 'zh_pr', '_zhpr' );
	echo '<input type="hidden" name="back" value="' . esc_attr( get_permalink() ) . '">';
	echo '<p><label>' . esc_html( $tr( 'Ваша почта', 'Your email' ) ) . '<br><input type="email" name="email" required autocomplete="email" style="width:100%;max-width:360px"></label></p>';
	echo '<p><label><input type="radio" name="kind" value="export_personal_data" checked> ' . esc_html( $tr( 'Выгрузить мои данные', 'Send me a copy of my data' ) ) . '</label><br>';
	echo '<label><input type="radio" name="kind" value="remove_personal_data"> ' . esc_html( $tr( 'Удалить мои данные', 'Delete my data' ) ) . '</label></p>';
	echo '<p style="position:absolute;left:-9999px" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></p>';
	echo '<p><button type="submit" class="wp-element-button">' . esc_html( $tr( 'Отправить запрос', 'Send request' ) ) . '</button></p></form>';
	return ob_get_clean();
} );

$zhdanov_pr = function () {
	$back = isset( $_POST['back'] ) ? esc_url_raw( wp_unslash( $_POST['back'] ) ) : home_url( '/' ); // phpcs:ignore
	if ( ! isset( $_POST['_zhpr'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_zhpr'] ) ), 'zh_pr' ) || ! empty( $_POST['website'] ) ) { // phpcs:ignore
		wp_safe_redirect( add_query_arg( 'zh_pr', 'err', $back ) );
		exit;
	}
	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$kind  = isset( $_POST['kind'] ) && 'remove_personal_data' === $_POST['kind'] ? 'remove_personal_data' : 'export_personal_data'; // phpcs:ignore
	$id    = is_email( $email ) ? wp_create_user_request( $email, $kind ) : 0;
	if ( $id && ! is_wp_error( $id ) ) {
		wp_send_user_request( $id );
	}
	// существующий запрос тоже считаем успехом: не подсказываем, есть ли адрес в базе
	wp_safe_redirect( add_query_arg( 'zh_pr', is_email( $email ) ? 'ok' : 'err', $back ) . '#zh-pr' );
	exit;
};
add_action( 'admin_post_nopriv_zhdanov_privacy_request', $zhdanov_pr );
add_action( 'admin_post_zhdanov_privacy_request', $zhdanov_pr );

/* ---------- Заявки с сайта в выгрузке и удалении персональных данных WordPress ---------- */

function zhdanov_gdpr_leads( $email, $page ) {
	return get_posts( array( 'post_type' => 'zh_lead', 'post_status' => 'any', 'numberposts' => 50, 'paged' => $page, 'meta_key' => '_zh_email', 'meta_value' => $email ) ); // phpcs:ignore
}

add_filter( 'wp_privacy_personal_data_exporters', function ( $ex ) {
	$ex['zhdanov-leads'] = array(
		'exporter_friendly_name' => 'Заявки с сайта',
		'callback'               => function ( $email, $page = 1 ) {
			$data  = array();
			$leads = zhdanov_gdpr_leads( $email, $page );
			foreach ( $leads as $l ) {
				$data[] = array(
					'group_id'    => 'zh-leads',
					'group_label' => 'Заявки с сайта',
					'item_id'     => 'lead-' . $l->ID,
					'data'        => array(
						array( 'name' => 'Дата', 'value' => $l->post_date ),
						array( 'name' => 'Имя', 'value' => get_post_meta( $l->ID, '_zh_name', true ) ),
						array( 'name' => 'Телефон', 'value' => get_post_meta( $l->ID, '_zh_phone', true ) ),
						array( 'name' => 'Почта', 'value' => get_post_meta( $l->ID, '_zh_email', true ) ),
						array( 'name' => 'Сообщение', 'value' => wp_strip_all_tags( $l->post_content ) ),
					),
				);
			}
			return array( 'data' => $data, 'done' => count( $leads ) < 50 );
		},
	);
	return $ex;
} );

add_filter( 'wp_privacy_personal_data_erasers', function ( $er ) {
	$er['zhdanov-leads'] = array(
		'eraser_friendly_name' => 'Заявки с сайта',
		'callback'             => function ( $email, $page = 1 ) {
			$leads = zhdanov_gdpr_leads( $email, 1 );
			foreach ( $leads as $l ) {
				wp_delete_post( $l->ID, true );
			}
			return array( 'items_removed' => count( $leads ), 'items_retained' => false, 'messages' => array(), 'done' => count( $leads ) < 50 );
		},
	);
	return $er;
} );

/* ---------- Шаблон политики по GDPR ---------- */

add_action( 'init', function () {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}
	$h = function ( $t, $l = 2 ) {
		return '<!-- wp:heading {"level":' . $l . '} --><h' . $l . ' class="wp-block-heading">' . $t . '</h' . $l . '><!-- /wp:heading -->';
	};
	$p = function ( $t ) {
		return '<!-- wp:paragraph --><p>' . $t . '</p><!-- /wp:paragraph -->';
	};
	$c = $h( 'Privacy Policy', 1 )
		. $p( 'Last updated: [date]. This policy explains how [Company name], [address], registration number [number] ("we") processes personal data on [website] under the EU General Data Protection Regulation (GDPR) and the UK GDPR.' )
		. $h( 'Who is responsible' ) . $p( 'Controller: [Company name], [address]. Contact for privacy questions: [email]. [If appointed: Data Protection Officer: name, email.]' )
		. $h( 'What data we collect and why' )
		. $p( '<strong>Contact and booking forms.</strong> Name, phone, email and your message, to answer your request and provide the service. Legal basis: steps prior to a contract and performance of a contract (Art. 6(1)(b) GDPR). Kept for [24 months] after the last contact.' )
		. $p( '<strong>Essential cookies.</strong> Remember your language and your cookie choice. Legal basis: our legitimate interest in a working website (Art. 6(1)(f)). No consent needed.' )
		. $p( '<strong>Analytics</strong> ([Google Analytics 4 / Yandex Metrica]): only if you allow «Analytics» in the cookie banner. Legal basis: your consent (Art. 6(1)(a)). IP addresses are shortened. Kept for [14 months].' )
		. $p( '<strong>Marketing</strong> ([advertising pixels]): only if you allow «Marketing». Legal basis: your consent.' )
		. $h( 'Who receives the data' ) . $p( 'Our hosting provider [name, country]; email provider [name]; [Google Ireland Ltd. for analytics]. Transfers outside the EU/EEA are protected by the EU Standard Contractual Clauses or an adequacy decision.' )
		. $h( 'Your rights' ) . $p( 'You can ask for access to your data, correction, deletion, restriction, data portability, and object to processing. You can withdraw consent at any time with the «Cookie settings» link at the bottom of every page; this does not affect earlier processing. You can also complain to your data protection authority.' )
		. $h( 'Request a copy or deletion of your data' ) . $p( 'Use the form below. We will email you to confirm the request and answer within one month.' )
		. '<!-- wp:shortcode -->[zhdanov_privacy_request]<!-- /wp:shortcode -->'
		. $h( 'Changes' ) . $p( 'We may update this policy. The date at the top shows the latest version.' );
	register_block_pattern( 'zhdanov/page-privacy-gdpr', array(
		'title'       => 'Готовая страница: Privacy Policy (GDPR, английский)',
		'categories'  => array( 'zhdanov-pages' ),
		'postTypes'   => array( 'page' ),
		'keywords'    => array( 'gdpr', 'privacy', 'политика', 'европа' ),
		'description' => 'Политика для сайтов в ЕС и Великобритании, с формой «выгрузить / удалить мои данные». Замените [квадратные скобки], покажите юристу клиента.',
		'content'     => '<!-- wp:group {"layout":{"type":"constrained","contentSize":"760px"}} --><div class="wp-block-group">' . $c . '</div><!-- /wp:group -->',
	) );
} );
