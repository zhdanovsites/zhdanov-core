<?php
/**
 * Онлайн-оплата: ЮKassa или Т-Банк (эквайринг). Кнопка «Оплатить онлайн» у услуг, товаров,
 * тарифов курсов и подарочных сертификатов. Цена всегда берётся на сервере из записи,
 * статус оплаты подтверждается запросом к платёжной системе (не верим браузеру).
 * Чек по 54-ФЗ: отправляет платёжная система (почта или телефон покупателя).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Заказы ---------- */
add_action( 'init', function () {
	register_post_type(
		'zh_order',
		array(
			'labels'       => array( 'name' => 'Оплаты', 'singular_name' => 'Оплата', 'menu_name' => 'Оплаты' ),
			'public'       => false,
			'show_ui'      => (bool) zhdanov_core_opt( 'pay_provider' ),
			'show_in_menu' => 'zhdanov-core',
			'supports'     => array( 'title' ),
			'capabilities' => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap' => true,
		)
	);
} );
add_filter( 'manage_zh_order_posts_columns', function () {
	return array( 'cb' => '<input type="checkbox">', 'title' => 'Что', 'zh_sum' => 'Сумма', 'zh_st' => 'Статус', 'zh_who' => 'Покупатель', 'date' => 'Когда' );
} );
add_action( 'manage_zh_order_posts_custom_column', function ( $col, $id ) {
	$st = array( 'pending' => 'ждёт оплаты', 'paid' => '✅ оплачено', 'canceled' => 'отменено' );
	if ( 'zh_sum' === $col ) {
		echo esc_html( number_format( (float) get_post_meta( $id, '_zh_sum', true ), 0, ',', ' ' ) . ' ₽' );
	} elseif ( 'zh_st' === $col ) {
		$s = get_post_meta( $id, '_zh_st', true );
		echo esc_html( isset( $st[ $s ] ) ? $st[ $s ] : $s );
		if ( get_post_meta( $id, '_zh_code', true ) ) {
			echo '<br><small>Сертификат ' . esc_html( get_post_meta( $id, '_zh_code', true ) ) . ( get_post_meta( $id, '_zh_used', true ) ? ' · использован' : '' ) . '</small>';
		}
	} elseif ( 'zh_who' === $col ) {
		echo esc_html( implode( ', ', array_filter( array( get_post_meta( $id, '_zh_name', true ), get_post_meta( $id, '_zh_phone', true ), get_post_meta( $id, '_zh_email', true ) ) ) ) );
	}
}, 10, 2 );

/** Сумма из текста цены: «3 500 ₽» → 3500. «от 2 000», «по запросу» → 0 (онлайн не продаём). */
function zhdanov_pay_amount( $price ) {
	$price = (string) $price;
	if ( '' === trim( $price ) || preg_match( '/(^|\s)(от|до|по запросу|договор)/ui', $price ) ) {
		return 0;
	}
	$n = (float) str_replace( array( ' ', ' ', ',' ), array( '', '', '.' ), preg_replace( '/[^\d,. ]/u', '', $price ) );
	return $n > 0 && $n < 1000000 ? round( $n, 2 ) : 0;
}

function zhdanov_pay_on() {
	$p = zhdanov_core_opt( 'pay_provider' );
	if ( 'yookassa' === $p ) {
		return zhdanov_core_opt( 'yk_shop' ) && zhdanov_core_opt( 'yk_secret' );
	}
	if ( 'tbank' === $p ) {
		return zhdanov_core_opt( 'tb_terminal' ) && zhdanov_core_opt( 'tb_password' );
	}
	return false;
}

/** Кнопка «Оплатить онлайн» (открывает короткую форму). $key: что оплачиваем на сервере. */
function zhdanov_pay_button_html( $post_id, $key, $amount, $label ) {
	// Основной способ: ссылка на оплату из кабинета банка или сервиса (без договоров с нашим плагином).
	$link = (string) get_post_meta( $post_id, '_zh_paylink', true );
	if ( $link ) {
		return '<a class="zh-btn-ghost zh-pay__btn" href="' . esc_url( $link ) . '" target="_blank" rel="noopener">' . esc_html( zhdanov_core__( 'Оплатить онлайн' ) ) . '<span class="screen-reader-text">: ' . esc_html( $label ) . ' (' . esc_html( zhdanov_core__( 'откроется в новом окне' ) ) . ')</span></a>';
	}
	if ( ! zhdanov_pay_on() || ! $amount ) {
		return '';
	}
	return '<button type="button" class="zh-btn-ghost zh-pay__btn" data-zh-pay="' . esc_attr( wp_json_encode( array( 'id' => (int) $post_id, 'key' => (string) $key, 'title' => $label, 'sum' => $amount ) ) ) . '">' . esc_html( zhdanov_core__( 'Оплатить онлайн' ) ) . '<span class="screen-reader-text">: ' . esc_html( $label ) . '</span></button>';
}

/* Тарифы курса. */
add_filter( 'zhdanov_pay_button', function ( $h, $id, $i, $price, $name ) {
	return $h . zhdanov_pay_button_html( $id, 't' . $i, zhdanov_pay_amount( $price ), get_the_title( $id ) . ', ' . $name );
}, 10, 5 );

/* Услуги и товары: под ценой. */
add_filter( 'render_block', function ( $html, $block ) {
	if ( 'zhdanov/item-meta' !== ( isset( $block['blockName'] ) ? $block['blockName'] : '' ) || ! is_singular( array( 'zh_service', 'zh_product', 'zh_tour' ) ) || ( ! zhdanov_pay_on() && ! get_post_meta( get_the_ID(), '_zh_paylink', true ) ) ) {
		return $html;
	}
	$id = get_the_ID();
	if ( 'zh_product' === get_post_type( $id ) && 'out' === zhdanov_core_m( $id, '_zh_stock' ) ) {
		return $html;
	}
	$b = zhdanov_pay_button_html( $id, 'p', zhdanov_pay_amount( zhdanov_core_m( $id, '_zh_price' ) ), get_the_title( $id ) );
	return $b ? preg_replace( '/<\/div>\s*$/', '<p class="zh-pay">' . $b . '</p></div>', $html, 1 ) : $html;
}, 20, 2 );

/** Сумма на сервере по тому, что оплачивают. */
function zhdanov_pay_resolve( $id, $key, $custom = 0 ) {
	$type = get_post_type( $id );
	if ( 'p' === $key && in_array( $type, array( 'zh_service', 'zh_product', 'zh_tour', 'zh_book' ), true ) ) {
		return array( zhdanov_pay_amount( zhdanov_core_m( $id, '_zh_price' ) ), get_the_title( $id ) );
	}
	if ( 'zh_course' === $type && preg_match( '/^t(\d+)$/', $key, $m ) ) {
		$t = zhdanov_core_course_tariffs( $id );
		if ( isset( $t[ $m[1] ] ) ) {
			return array( zhdanov_pay_amount( $t[ $m[1] ]['price'] ), get_the_title( $id ) . ', ' . $t[ $m[1] ]['name'] );
		}
	}
	if ( 0 === strpos( $key, 'gift' ) ) {
		$sum = (int) $custom;
		$min = (int) zhdanov_core_opt( 'gift_min' ) ? (int) zhdanov_core_opt( 'gift_min' ) : 1000;
		if ( $sum >= $min && $sum <= 300000 ) {
			return array( $sum, zhdanov_core__( 'Подарочный сертификат' ) . ' ' . number_format( $sum, 0, ',', ' ' ) . ' ₽' );
		}
	}
	return apply_filters( 'zhdanov_pay_resolve_extra', array( 0, '' ), $key, $custom );
}

/* ---------- REST: создать оплату ---------- */
add_action( 'rest_api_init', function () {
	register_rest_route( 'zhdanov/v1', '/pay', array( 'methods' => 'POST', 'callback' => 'zhdanov_pay_create', 'permission_callback' => '__return_true' ) );
	register_rest_route( 'zhdanov/v1', '/pay-hook/(?P<p>yookassa|tbank)', array( 'methods' => 'POST', 'callback' => 'zhdanov_pay_hook', 'permission_callback' => '__return_true' ) );
} );

function zhdanov_pay_create( $req ) {
	if ( ! zhdanov_pay_on() ) {
		return new WP_Error( 'zh_pay', 'Оплата не настроена.', array( 'status' => 400 ) );
	}
	$name  = sanitize_text_field( $req->get_param( 'name' ) );
	$phone = sanitize_text_field( $req->get_param( 'phone' ) );
	$email = sanitize_email( $req->get_param( 'email' ) );
	if ( ! $name || ( ! $email && strlen( preg_replace( '/\D/', '', $phone ) ) < 10 ) || ! $req->get_param( 'consent' ) || $req->get_param( 'website' ) ) {
		return new WP_Error( 'zh_pay', zhdanov_core__( 'Заполните имя, почту или телефон и отметьте согласие.' ), array( 'status' => 400 ) );
	}
	$ip  = 'zh_pay_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
	$cnt = (int) get_transient( $ip );
	if ( $cnt > 10 ) {
		return new WP_Error( 'zh_pay', 'Слишком много попыток. Попробуйте через 10 минут.', array( 'status' => 429 ) );
	}
	set_transient( $ip, $cnt + 1, 10 * MINUTE_IN_SECONDS );
	$pid = (int) $req->get_param( 'id' );
	$key = sanitize_key( $req->get_param( 'key' ) );
	if ( 0 === strpos( $key, 'gift' ) && ! $email ) {
		return new WP_Error( 'zh_pay', zhdanov_core__( 'Укажите почту: на неё придёт сертификат.' ), array( 'status' => 400 ) );
	}
	list( $sum, $title ) = zhdanov_pay_resolve( $pid, $key, (int) $req->get_param( 'sum' ) );
	if ( ! $sum ) {
		return new WP_Error( 'zh_pay', 'Эту позицию нельзя оплатить онлайн. Напишите нам.', array( 'status' => 400 ) );
	}
	$order = wp_insert_post( array( 'post_type' => 'zh_order', 'post_status' => 'private', 'post_title' => $title ) );
	foreach ( array( '_zh_sum' => $sum, '_zh_st' => 'pending', '_zh_name' => $name, '_zh_phone' => $phone, '_zh_email' => $email, '_zh_item' => $pid, '_zh_key' => $key, '_zh_to' => sanitize_text_field( $req->get_param( 'to' ) ) ) as $k => $v ) {
		update_post_meta( $order, $k, $v );
	}
	$back = add_query_arg( array( 'oplata' => $order, 'k' => wp_hash( 'zh_order_' . $order ) ), $pid ? get_permalink( $pid ) : home_url( '/' ) );
	if ( 0 === strpos( $key, 'gift' ) ) {
		$back = add_query_arg( array( 'oplata' => $order, 'k' => wp_hash( 'zh_order_' . $order ) ), wp_get_referer() ? wp_get_referer() : home_url( '/' ) );
	}
	$url = 'yookassa' === zhdanov_core_opt( 'pay_provider' ) ? zhdanov_pay_yk_init( $order, $sum, $title, $email, $phone, $back ) : zhdanov_pay_tb_init( $order, $sum, $title, $email, $phone, $back );
	if ( is_wp_error( $url ) ) {
		update_post_meta( $order, '_zh_st', 'canceled' );
		update_post_meta( $order, '_zh_err', $url->get_error_message() );
		return new WP_Error( 'zh_pay', zhdanov_core__( 'Платёжная система не ответила. Попробуйте ещё раз или напишите нам.' ), array( 'status' => 502 ) );
	}
	return array( 'ok' => true, 'url' => $url );
}

/** Позиция чека 54-ФЗ. НДС по умолчанию «без НДС» (УСН/самозанятые/ИП); меняется в настройках. */
function zhdanov_pay_vat() {
	$v = (int) zhdanov_core_opt( 'pay_vat' );
	return $v ? $v : 1;
}

/* ЮKassa: https://yookassa.ru/developers/api#create_payment */
function zhdanov_pay_yk_init( $order, $sum, $title, $email, $phone, $back ) {
	$customer = $email ? array( 'email' => $email ) : array( 'phone' => preg_replace( '/\D/', '', $phone ) );
	$body     = array(
		'amount'       => array( 'value' => number_format( $sum, 2, '.', '' ), 'currency' => 'RUB' ),
		'capture'      => true,
		'confirmation' => array( 'type' => 'redirect', 'return_url' => $back ),
		'description'  => mb_substr( $title, 0, 128 ),
		'metadata'     => array( 'order' => (string) $order ),
		'receipt'      => array(
			'customer' => $customer,
			'items'    => array( array( 'description' => mb_substr( $title, 0, 128 ), 'quantity' => '1.00', 'amount' => array( 'value' => number_format( $sum, 2, '.', '' ), 'currency' => 'RUB' ), 'vat_code' => zhdanov_pay_vat(), 'payment_mode' => 'full_payment', 'payment_subject' => 0 === strpos( get_post_meta( $order, '_zh_key', true ), 'gift' ) ? 'payment' : 'service' ) ),
		),
	);
	$r = wp_remote_post(
		'https://api.yookassa.ru/v3/payments',
		array(
			'timeout' => 20,
			'headers' => array( 'Authorization' => 'Basic ' . base64_encode( zhdanov_core_opt( 'yk_shop' ) . ':' . zhdanov_core_opt( 'yk_secret' ) ), 'Idempotence-Key' => wp_generate_uuid4(), 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $body ),
		)
	);
	$j = is_wp_error( $r ) ? array() : json_decode( wp_remote_retrieve_body( $r ), true );
	if ( empty( $j['confirmation']['confirmation_url'] ) ) {
		return new WP_Error( 'zh_pay', is_wp_error( $r ) ? $r->get_error_message() : ( isset( $j['description'] ) ? $j['description'] : 'ЮKassa: нет ссылки' ) );
	}
	update_post_meta( $order, '_zh_pid', $j['id'] );
	return $j['confirmation']['confirmation_url'];
}

function zhdanov_pay_yk_status( $pid ) {
	$r = wp_remote_get( 'https://api.yookassa.ru/v3/payments/' . rawurlencode( $pid ), array( 'timeout' => 15, 'headers' => array( 'Authorization' => 'Basic ' . base64_encode( zhdanov_core_opt( 'yk_shop' ) . ':' . zhdanov_core_opt( 'yk_secret' ) ) ) ) );
	$j = is_wp_error( $r ) ? array() : json_decode( wp_remote_retrieve_body( $r ), true );
	return isset( $j['status'] ) ? $j['status'] : '';
}

/* Т-Банк: https://www.tbank.ru/kassa/dev/payments/ (Init, подпись Token = SHA-256). */
function zhdanov_pay_tb_token( $data ) {
	$data['Password'] = zhdanov_core_opt( 'tb_password' );
	$flat = array();
	foreach ( $data as $k => $v ) {
		if ( ! is_array( $v ) && 'Token' !== $k ) {
			$flat[ $k ] = is_bool( $v ) ? ( $v ? 'true' : 'false' ) : (string) $v;
		}
	}
	ksort( $flat );
	return hash( 'sha256', implode( '', $flat ) );
}

function zhdanov_pay_tb_init( $order, $sum, $title, $email, $phone, $back ) {
	$kop  = (int) round( $sum * 100 );
	$data = array(
		'TerminalKey'     => zhdanov_core_opt( 'tb_terminal' ),
		'Amount'          => $kop,
		'OrderId'         => (string) $order,
		'Description'     => mb_substr( $title, 0, 140 ),
		'SuccessURL'      => $back,
		'FailURL'         => $back,
		'NotificationURL' => rest_url( 'zhdanov/v1/pay-hook/tbank' ),
	);
	$data['Token']   = zhdanov_pay_tb_token( $data );
	$data['Receipt'] = array_filter(
		array(
			'Email'    => $email,
			'Phone'    => $email ? '' : '+' . preg_replace( '/\D/', '', $phone ),
			'Taxation' => zhdanov_core_opt( 'pay_tax' ) ? zhdanov_core_opt( 'pay_tax' ) : 'usn_income',
			'Items'    => array( array( 'Name' => mb_substr( $title, 0, 128 ), 'Price' => $kop, 'Quantity' => 1, 'Amount' => $kop, 'Tax' => array( 1 => 'none', 2 => 'vat0', 3 => 'vat10', 4 => 'vat20' )[ zhdanov_pay_vat() ] ?? 'none', 'PaymentMethod' => 'full_payment', 'PaymentObject' => 'service' ) ),
		)
	);
	$r = wp_remote_post( 'https://securepay.tinkoff.ru/v2/Init', array( 'timeout' => 20, 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $data ) ) );
	$j = is_wp_error( $r ) ? array() : json_decode( wp_remote_retrieve_body( $r ), true );
	if ( empty( $j['Success'] ) || empty( $j['PaymentURL'] ) ) {
		return new WP_Error( 'zh_pay', is_wp_error( $r ) ? $r->get_error_message() : ( isset( $j['Message'] ) ? $j['Message'] . ' ' . ( $j['Details'] ?? '' ) : 'Т-Банк: нет ссылки' ) );
	}
	update_post_meta( $order, '_zh_pid', $j['PaymentId'] );
	return $j['PaymentURL'];
}

function zhdanov_pay_tb_status( $pid ) {
	$data          = array( 'TerminalKey' => zhdanov_core_opt( 'tb_terminal' ), 'PaymentId' => (string) $pid );
	$data['Token'] = zhdanov_pay_tb_token( $data );
	$r             = wp_remote_post( 'https://securepay.tinkoff.ru/v2/GetState', array( 'timeout' => 15, 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $data ) ) );
	$j             = is_wp_error( $r ) ? array() : json_decode( wp_remote_retrieve_body( $r ), true );
	$s             = isset( $j['Status'] ) ? $j['Status'] : '';
	return 'CONFIRMED' === $s ? 'succeeded' : ( in_array( $s, array( 'CANCELED', 'REJECTED', 'DEADLINE_EXPIRED', 'REVERSED', 'REFUNDED' ), true ) ? 'canceled' : 'pending' );
}

/** Проверить оплату у платёжной системы и отметить заказ. */
function zhdanov_pay_check( $order ) {
	if ( 'paid' === get_post_meta( $order, '_zh_st', true ) ) {
		return 'paid';
	}
	$pid = get_post_meta( $order, '_zh_pid', true );
	if ( ! $pid ) {
		return 'pending';
	}
	$s = 'yookassa' === zhdanov_core_opt( 'pay_provider' ) ? zhdanov_pay_yk_status( $pid ) : zhdanov_pay_tb_status( $pid );
	if ( 'succeeded' === $s ) {
		update_post_meta( $order, '_zh_st', 'paid' );
		do_action( 'zhdanov_paid', $order );
		return 'paid';
	}
	if ( 'canceled' === $s ) {
		update_post_meta( $order, '_zh_st', 'canceled' );
		return 'canceled';
	}
	return 'pending';
}

/* Уведомление платёжной системы: не верим телу, сверяем статус запросом. */
function zhdanov_pay_hook( $req ) {
	$b = $req->get_json_params();
	if ( 'tbank' === $req['p'] ) {
		$order = isset( $b['OrderId'] ) ? (int) $b['OrderId'] : 0;
		if ( $order && isset( $b['Token'] ) && hash_equals( zhdanov_pay_tb_token( $b ), (string) $b['Token'] ) && get_post_meta( $order, '_zh_pid', true ) === (string) ( $b['PaymentId'] ?? '' ) ) {
			zhdanov_pay_check( $order );
		}
		return new WP_REST_Response( 'OK', 200 );
	}
	$order = isset( $b['object']['metadata']['order'] ) ? (int) $b['object']['metadata']['order'] : 0;
	if ( $order && 'zh_order' === get_post_type( $order ) ) {
		zhdanov_pay_check( $order );
	}
	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/* Оплачено: уведомить владельца, сертификату выдать код. */
add_action( 'zhdanov_paid', function ( $order ) {
	$key  = get_post_meta( $order, '_zh_key', true );
	$code = '';
	if ( 0 === strpos( $key, 'gift' ) && ! get_post_meta( $order, '_zh_code', true ) ) {
		$code = strtoupper( substr( str_replace( array( '0', 'O', 'I', '1' ), '', wp_generate_password( 16, false ) ), 0, 4 ) . '-' . substr( str_replace( array( '0', 'O', 'I', '1' ), '', wp_generate_password( 16, false ) ), 0, 4 ) );
		update_post_meta( $order, '_zh_code', $code );
	}
	$text = 'Оплата на сайте ' . get_bloginfo( 'name' ) . ': ' . get_the_title( $order ) . ', ' . number_format( (float) get_post_meta( $order, '_zh_sum', true ), 0, ',', ' ' ) . " ₽\n" .
		'Покупатель: ' . implode( ', ', array_filter( array( get_post_meta( $order, '_zh_name', true ), get_post_meta( $order, '_zh_phone', true ), get_post_meta( $order, '_zh_email', true ) ) ) ) .
		( $code ? "\nСертификат: " . $code . ( get_post_meta( $order, '_zh_to', true ) ? ' (для: ' . get_post_meta( $order, '_zh_to', true ) . ')' : '' ) : '' ) . "\nВсе оплаты: " . admin_url( 'edit.php?post_type=zh_order' );
	$to = zhdanov_core_opt( 'form_email' ) ? zhdanov_core_opt( 'form_email' ) : get_option( 'admin_email' );
	wp_mail( $to, 'Оплата: ' . get_the_title( $order ), $text );
	if ( zhdanov_core_opt( 'tg_token' ) && zhdanov_core_opt( 'tg_chat' ) ) {
		zhdanov_core_tg_send( zhdanov_core_opt( 'tg_token' ), zhdanov_core_opt( 'tg_chat' ), $text );
	}
	$email = get_post_meta( $order, '_zh_email', true );
	if ( $code && $email ) {
		wp_mail( $email, zhdanov_core__( 'Подарочный сертификат' ) . ' ' . get_bloginfo( 'name' ), "Спасибо за покупку!\n\nСертификат на " . number_format( (float) get_post_meta( $order, '_zh_sum', true ), 0, ',', ' ' ) . " ₽, код: $code\nОткрыть и распечатать: " . zhdanov_gift_url( $order ) . "\n\n" . get_bloginfo( 'name' ) . ', ' . home_url( '/' ) );
	}
} );

function zhdanov_gift_url( $order ) {
	return add_query_arg( array( 'sertifikat' => $order, 'k' => wp_hash( 'zh_gift_' . $order ) ), home_url( '/' ) );
}

/* Возврат с оплаты: проверяем статус и показываем плашку. */
add_action( 'wp_footer', function () {
	if ( empty( $_GET['oplata'] ) || empty( $_GET['k'] ) ) { // phpcs:ignore
		return;
	}
	$order = (int) $_GET['oplata']; // phpcs:ignore
	if ( ! hash_equals( wp_hash( 'zh_order_' . $order ), (string) wp_unslash( $_GET['k'] ) ) ) { // phpcs:ignore
		return;
	}
	$s   = zhdanov_pay_check( $order );
	$msg = 'paid' === $s ? zhdanov_core__( 'Оплата прошла. Спасибо! Мы свяжемся с вами.' ) : ( 'canceled' === $s ? zhdanov_core__( 'Оплата не прошла. Попробуйте ещё раз или напишите нам.' ) : zhdanov_core__( 'Ждём подтверждения оплаты от банка. Обычно это минута.' ) );
	$gift = 'paid' === $s && get_post_meta( $order, '_zh_code', true ) ? ' <a href="' . esc_url( zhdanov_gift_url( $order ) ) . '">' . esc_html( zhdanov_core__( 'Открыть сертификат' ) ) . '</a>' : '';
	echo '<div class="zh-toast zh-toast--pay is-' . esc_attr( $s ) . '" role="status">' . esc_html( $msg ) . $gift . '</div>'; // phpcs:ignore
	if ( 'paid' === $s ) {
		echo '<script>document.addEventListener("DOMContentLoaded",function(){var c=window.zhCore||{};if(c.metrika&&window.ym){ym(Number(c.metrika),"reachGoal","payment",{order_price:' . (float) get_post_meta( $order, '_zh_sum', true ) . ',currency:"RUB"});}});</script>';
	}
} );

/* ---------- Подарочный сертификат: страница для печати ---------- */
add_action( 'template_redirect', function () {
	if ( empty( $_GET['sertifikat'] ) || empty( $_GET['k'] ) ) { // phpcs:ignore
		return;
	}
	$o = (int) $_GET['sertifikat']; // phpcs:ignore
	if ( ! hash_equals( wp_hash( 'zh_gift_' . $o ), (string) wp_unslash( $_GET['k'] ) ) || 'paid' !== get_post_meta( $o, '_zh_st', true ) ) { // phpcs:ignore
		return;
	}
	$sum  = number_format( (float) get_post_meta( $o, '_zh_sum', true ), 0, ',', ' ' );
	$to   = get_post_meta( $o, '_zh_to', true );
	$code = get_post_meta( $o, '_zh_code', true );
	$till = wp_date( 'j F Y', get_post_time( 'U', true, $o ) + (int) ( zhdanov_core_opt( 'gift_days' ) ? zhdanov_core_opt( 'gift_days' ) : 365 ) * DAY_IN_SECONDS );
	$c    = function ( $slug, $def ) {
		return 'var(--wp--preset--color--' . $slug . ',' . $def . ')';
	};
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );
	?><!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title><?php echo esc_html( zhdanov_core__( 'Подарочный сертификат' ) . ' · ' . get_bloginfo( 'name' ) ); ?></title>
	<?php wp_print_styles( 'global-styles' ); ?>
	<style>body{margin:0;font-family:var(--wp--preset--font-family--body,system-ui,sans-serif);background:#eee;display:flex;min-height:100vh;align-items:center;justify-content:center;padding:16px;box-sizing:border-box}.g{width:min(100%,720px);aspect-ratio:1.6;background:<?php echo esc_html( $c( 'base', '#fff' ) ); ?>;color:<?php echo esc_html( $c( 'contrast', '#111' ) ); ?>;border-radius:20px;padding:clamp(20px,5vw,48px);box-sizing:border-box;display:flex;flex-direction:column;justify-content:space-between;border:3px solid <?php echo esc_html( $c( 'accent', '#a33' ) ); ?>}.g h1{font-family:var(--wp--preset--font-family--heading,Georgia,serif);font-size:clamp(24px,5vw,44px);margin:0}.s{font-size:clamp(36px,9vw,80px);font-weight:800;color:<?php echo esc_html( $c( 'accent', '#a33' ) ); ?>;line-height:1}.k{font-family:monospace;font-size:clamp(18px,4vw,28px);letter-spacing:.15em}.m{font-size:14px;opacity:.8}button{margin-top:16px}@media print{body{background:#fff}button{display:none}}</style></head><body><div><div class="g"><div><p class="m"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p><h1><?php echo esc_html( zhdanov_core__( 'Подарочный сертификат' ) ); ?></h1><?php if ( $to ) : ?><p><?php echo esc_html( zhdanov_core__( 'Для' ) . ': ' . $to ); ?></p><?php endif; ?></div>
	<div class="s"><?php echo esc_html( $sum ); ?> ₽</div>
	<div><p class="k"><?php echo esc_html( $code ); ?></p><p class="m"><?php echo esc_html( zhdanov_core__( 'Действует до' ) . ' ' . $till . ' · ' . preg_replace( '#^https?://#', '', home_url() ) . ( zhdanov_core_opt( 'phone' ) ? ' · ' . zhdanov_core_opt( 'phone' ) : '' ) ); ?></p></div></div>
	<button type="button" onclick="print()"><?php echo esc_html( zhdanov_core__( 'Распечатать' ) ); ?></button></div></body></html>
	<?php
	exit;
} );

/* ---------- Форма оплаты (одна на страницу, открывается по кнопке) ---------- */
add_action( 'wp_footer', function () {
	if ( ! zhdanov_pay_on() ) {
		return;
	}
	$pp     = (int) zhdanov_core_opt( 'privacy_page' );
	$op     = (int) zhdanov_core_opt( 'offer_page' );
	$links  = array();
	if ( $op && 'publish' === get_post_status( $op ) ) {
		$links[] = '<a href="' . esc_url( get_permalink( $op ) ) . '" target="_blank">' . esc_html( zhdanov_core__( 'офертой' ) ) . '</a>';
	}
	if ( $pp && 'publish' === get_post_status( $pp ) ) {
		$links[] = '<a href="' . esc_url( get_permalink( $pp ) ) . '" target="_blank">' . esc_html( zhdanov_core__( 'политикой конфиденциальности' ) ) . '</a>';
	}
	?>
	<dialog class="zh-paydlg" aria-labelledby="zh-paydlg-t">
		<form class="zh-form zh-payform" data-rest="<?php echo esc_url( rest_url( 'zhdanov/v1/pay' ) ); ?>" novalidate>
			<h2 id="zh-paydlg-t" class="zh-form__title"><?php echo esc_html( zhdanov_core__( 'Оплата онлайн' ) ); ?></h2>
			<p class="zh-paydlg__what"></p>
			<input type="hidden" name="id"><input type="hidden" name="key"><input type="hidden" name="sum">
			<p class="zh-hp" aria-hidden="true"><label>Сайт<input type="text" name="website" tabindex="-1" autocomplete="off"></label></p>
			<p class="zh-field"><label for="zhp-name"><?php echo esc_html( zhdanov_core__( 'Имя' ) ); ?></label><input id="zhp-name" name="name" autocomplete="name" required></p>
			<p class="zh-field"><label for="zhp-email"><?php echo esc_html( zhdanov_core__( 'Почта для чека' ) ); ?></label><input id="zhp-email" name="email" type="email" autocomplete="email"></p>
			<p class="zh-field"><label for="zhp-phone"><?php echo esc_html( zhdanov_core__( 'Телефон' ) ); ?></label><input id="zhp-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="+7"></p>
			<p class="zh-field zh-paydlg__to" hidden><label for="zhp-to"><?php echo esc_html( zhdanov_core__( 'Кому сертификат (имя на сертификате)' ) ); ?></label><input id="zhp-to" name="to"></p>
			<p class="zh-consent"><input id="zhp-ok" name="consent" type="checkbox" value="1" required><label for="zhp-ok"><?php echo esc_html( zhdanov_core__( 'Согласен(на) с условиями' ) ); ?><?php echo $links ? ' (' . implode( ', ', $links ) . ')' : ''; // phpcs:ignore ?></label></p>
			<p class="zh-form__submit"><button type="submit" class="wp-element-button"><?php echo esc_html( zhdanov_core__( 'Перейти к оплате' ) ); ?></button> <button type="button" class="zh-linkbtn zh-paydlg__x"><?php echo esc_html( zhdanov_core__( 'Отмена' ) ); ?></button></p>
			<p class="zh-form__status" role="status" aria-live="polite"></p>
			<p class="zh-paydlg__note"><?php echo esc_html( 'yookassa' === zhdanov_core_opt( 'pay_provider' ) ? zhdanov_core__( 'Оплата через ЮKassa: карта, СБП, SberPay. Чек придёт на почту или телефон.' ) : zhdanov_core__( 'Оплата через Т-Банк: карта, СБП, T-Pay. Чек придёт на почту или телефон.' ) ); ?></p>
		</form>
	</dialog>
	<?php
}, 30 );

/* Отметить сертификат использованным (в списке оплат). */
add_filter( 'post_row_actions', function ( $a, $p ) {
	if ( 'zh_order' === $p->post_type && get_post_meta( $p->ID, '_zh_code', true ) ) {
		$used        = get_post_meta( $p->ID, '_zh_used', true );
		$a['zh_used'] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=zhdanov_gift_used&id=' . $p->ID ), 'zh_gift_used_' . $p->ID ) ) . '">' . ( $used ? 'Снять отметку «использован»' : 'Отметить: использован' ) . '</a>';
		$a['zh_view'] = '<a href="' . esc_url( zhdanov_gift_url( $p->ID ) ) . '" target="_blank">Сертификат</a>';
	}
	return $a;
}, 10, 2 );
add_action( 'admin_post_zhdanov_gift_used', function () {
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore
	if ( ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'zh_gift_used_' . $id ) ) {
		wp_die( 'Нет доступа.' );
	}
	update_post_meta( $id, '_zh_used', get_post_meta( $id, '_zh_used', true ) ? '' : current_time( 'Y-m-d H:i' ) );
	wp_safe_redirect( admin_url( 'edit.php?post_type=zh_order' ) );
	exit;
} );
/* Поиск по коду сертификата в списке оплат. */
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() && $q->is_main_query() && 'zh_order' === $q->get( 'post_type' ) && preg_match( '/^[A-Z0-9]{4}-[A-Z0-9]{4}$/i', (string) $q->get( 's' ) ) ) {
		$q->set( 'meta_query', array( array( 'key' => '_zh_code', 'value' => strtoupper( $q->get( 's' ) ) ) ) );
		$q->set( 's', '' );
	}
} );
