<?php
/**
 * Форма заявки: согласие на обработку ПДн (152-ФЗ), защита от спама, письмо + копия в Консоль → Заявки.
 * Заявка не потеряется, даже если хостинг не отправил письмо.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	register_post_type(
		'zh_lead',
		array(
			'labels'          => array( 'name' => 'Заявки', 'singular_name' => 'Заявка', 'menu_name' => 'Заявки', 'all_items' => 'Все заявки', 'edit_item' => 'Заявка', 'not_found' => 'Заявок пока нет' ),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'menu_icon'       => 'dashicons-email-alt',
			'menu_position'   => 4,
			'supports'        => array( 'title', 'editor' ),
			'capability_type' => 'post',
			'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'    => true,
		)
	);
} );

add_filter( 'manage_zh_lead_posts_columns', function ( $c ) {
	return array( 'cb' => $c['cb'], 'title' => 'Кто', 'zh_phone' => 'Телефон', 'zh_page' => 'Со страницы', 'date' => 'Когда' );
} );
add_action( 'manage_zh_lead_posts_custom_column', function ( $col, $id ) {
	if ( 'zh_phone' === $col ) {
		$p = get_post_meta( $id, '_zh_phone', true );
		echo $p ? '<a href="tel:' . esc_attr( zhdanov_core_tel( $p ) ) . '">' . esc_html( $p ) . '</a>' : '-';
	}
	if ( 'zh_page' === $col ) {
		$u = get_post_meta( $id, '_zh_page', true );
		echo $u ? '<a href="' . esc_url( $u ) . '" target="_blank">' . esc_html( wp_parse_url( $u, PHP_URL_PATH ) ) . '</a>' : '-';
		$s = get_post_meta( $id, '_zh_src', true );
		if ( $s ) {
			echo '<br><small style="color:#50575e">' . esc_html( $s ) . '</small>';
		}
	}
}, 10, 2 );

add_action( 'rest_api_init', function () {
	register_rest_route(
		'zhdanov/v1',
		'/lead',
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => 'zhdanov_core_handle_lead',
		)
	);
} );

/* Без JavaScript форма отправляется обычным способом. */
add_action( 'admin_post_nopriv_zh_lead', 'zhdanov_core_lead_fallback' );
add_action( 'admin_post_zh_lead', 'zhdanov_core_lead_fallback' );
function zhdanov_core_lead_fallback() {
	$req = new WP_REST_Request( 'POST' );
	$req->set_body_params( wp_unslash( $_POST ) ); // phpcs:ignore
	$res  = zhdanov_core_handle_lead( $req );
	$back = wp_get_referer() ? wp_get_referer() : home_url( '/' );
	$ok   = ! is_wp_error( $res ) && ! empty( $res['ok'] );
	wp_safe_redirect( add_query_arg( 'zayavka', $ok ? 'ok' : 'error', $back ) . '#zayavka' );
	exit;
}

function zhdanov_core_handle_lead( $req ) {
	$p = function ( $k ) use ( $req ) {
		return trim( (string) $req->get_param( $k ) );
	};
	// Ловушки для ботов: скрытое поле и слишком быстрая отправка.
	if ( '' !== $p( 'website' ) || ( (int) $p( 'zh_t' ) && time() - (int) $p( 'zh_t' ) < 3 ) ) {
		return array( 'ok' => true, 'message' => zhdanov_core_opt( 'form_done' ) ); // боту делаем вид, что всё хорошо
	}
	// Не чаще 5 заявок за 10 минут с одного адреса.
	$ip_key = 'zh_lead_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
	$count  = (int) get_transient( $ip_key );
	if ( $count >= 5 ) {
		return new WP_Error( 'zh_rate', 'Слишком много заявок подряд. Попробуйте через несколько минут или позвоните нам.', array( 'status' => 429 ) );
	}

	$name    = sanitize_text_field( $p( 'name' ) );
	$phone   = sanitize_text_field( $p( 'phone' ) );
	$email   = sanitize_email( $p( 'email' ) );
	$message = sanitize_textarea_field( $p( 'message' ) );
	$page    = esc_url_raw( $p( 'page' ) );
	$src     = mb_substr( sanitize_text_field( $p( 'src' ) ), 0, 400 );
	if ( '' === $name || strlen( preg_replace( '/\D/', '', $phone ) ) < 6 ) {
		return new WP_Error( 'zh_fields', 'Укажите имя и телефон.', array( 'status' => 400 ) );
	}
	if ( '1' !== $p( 'consent' ) ) {
		return new WP_Error( 'zh_consent', 'Нужно согласие на обработку персональных данных.', array( 'status' => 400 ) );
	}

	// Yandex SmartCaptcha, если ключи заданы.
	$secret = zhdanov_core_opt( 'captcha_server' );
	if ( $secret && zhdanov_core_opt( 'captcha_client' ) ) {
		$token = $p( 'smart-token' );
		$res   = wp_remote_post(
			'https://smartcaptcha.yandexcloud.net/validate',
			array(
				'timeout' => 8,
				'body'    => array( 'secret' => $secret, 'token' => $token, 'ip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' ),
			)
		);
		$ok    = ! is_wp_error( $res ) && 'ok' === ( json_decode( wp_remote_retrieve_body( $res ), true )['status'] ?? '' );
		// Если сервис капчи недоступен, заявку не теряем: сохраняем с пометкой.
		if ( ! $ok && ! is_wp_error( $res ) ) {
			return new WP_Error( 'zh_captcha', 'Подтвердите, что вы не робот.', array( 'status' => 400 ) );
		}
	}

	$id = wp_insert_post(
		array(
			'post_type'    => 'zh_lead',
			'post_status'  => 'private',
			'post_title'   => $name . ' · ' . $phone,
			'post_content' => $message,
		)
	);
	if ( $id ) {
		update_post_meta( $id, '_zh_name', $name );
		update_post_meta( $id, '_zh_phone', $phone );
		update_post_meta( $id, '_zh_email', $email );
		update_post_meta( $id, '_zh_page', $page );
		update_post_meta( $id, '_zh_src', $src );
		update_post_meta( $id, '_zh_consent', current_time( 'mysql' ) );
	}
	set_transient( $ip_key, $count + 1, 10 * MINUTE_IN_SECONDS );

	$to   = zhdanov_core_opt( 'form_email' ) ? zhdanov_core_opt( 'form_email' ) : get_option( 'admin_email' );
	$body = "Новая заявка с сайта " . get_bloginfo( 'name' ) . "\n\nИмя: $name\nТелефон: $phone\n" . ( $email ? "Почта: $email\n" : '' ) . ( $message ? "\nСообщение:\n$message\n" : '' ) . "\nСтраница: $page" . ( $src ? "\nОткуда: $src" : '' ) . "\nСогласие на обработку ПДн: да, " . current_time( 'd.m.Y H:i' ) . "\n\nВсе заявки: " . admin_url( 'edit.php?post_type=zh_lead' );
	$headers = $email ? array( 'Reply-To: ' . $name . ' <' . $email . '>' ) : array();
	wp_mail( $to, 'Заявка с сайта: ' . $name, $body, $headers );
	do_action( 'zhdanov_lead_saved', $id, array( 'name' => $name, 'phone' => $phone, 'email' => $email, 'message' => $message, 'page' => $page, 'text' => $body ) );

	return apply_filters( 'zhdanov_lead_response', array( 'ok' => true, 'message' => zhdanov_core_opt( 'form_done' ) ) );
}
