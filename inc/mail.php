<?php
/**
 * Отправка писем через почтовый ящик (SMTP): заявки, отчёт разработчику, любые письма WordPress.
 * Настройки: Жданов → «Заявки с сайта» (smtp: yandex / mailru / custom, smtp_user, smtp_pass, smtp_host).
 * Для «Другая»: сервер вида smtp.gmail.com (порт 465) или smtp.example.ru:587.
 * Последняя ошибка отправки запоминается (zhdanov_mail_error) и показывается в «Здоровье сайта».
 *
 * @package zhdanov-core
 */

defined( 'ABSPATH' ) || exit;

/** Сервер, порт и шифрование по настройкам. null: отправка через ящик не настроена. */
function zhdanov_mail_server() {
	$o    = (array) get_option( 'zhdanov_core', array() );
	$kind = isset( $o['smtp'] ) ? $o['smtp'] : '';
	$user = isset( $o['smtp_user'] ) ? trim( $o['smtp_user'] ) : '';
	$pass = isset( $o['smtp_pass'] ) ? (string) $o['smtp_pass'] : '';
	if ( ! $kind || ! $user || '' === $pass ) {
		return null;
	}
	$host = '';
	$port = 465;
	if ( 'yandex' === $kind ) {
		$host = 'smtp.yandex.ru';
	} elseif ( 'mailru' === $kind ) {
		$host = 'smtp.mail.ru';
	} else {
		$raw = isset( $o['smtp_host'] ) ? trim( preg_replace( '#^[a-z]+://#i', '', (string) $o['smtp_host'] ) ) : '';
		if ( preg_match( '/^([^:\s,]+)(?:[:\s,]+(?:порт\s*)?(\d{2,5}))?/iu', $raw, $m ) ) {
			$host = $m[1];
			$port = ! empty( $m[2] ) ? (int) $m[2] : 465;
		}
		if ( ! $host && preg_match( '/@gmail\.com$/i', $user ) ) {
			$host = 'smtp.gmail.com';
		}
	}
	if ( ! $host ) {
		return null;
	}
	// пароль приложения Google показывают группами через пробел: пробелы не часть пароля
	if ( false !== stripos( $host, 'gmail' ) || false !== stripos( $host, 'google' ) ) {
		$pass = str_replace( ' ', '', $pass );
	}
	return array( 'host' => $host, 'port' => $port, 'secure' => 465 === $port ? 'ssl' : 'tls', 'user' => $user, 'pass' => $pass );
}

add_action( 'phpmailer_init', function ( $m ) {
	$s = zhdanov_mail_server();
	if ( ! $s ) {
		return;
	}
	$m->isSMTP();
	$m->Host       = $s['host'];
	$m->Port       = $s['port'];
	$m->SMTPSecure = $s['secure'];
	$m->SMTPAuth   = true;
	$m->Username   = $s['user'];
	$m->Password   = $s['pass'];
	$m->Timeout    = 12;
	$m->CharSet    = 'UTF-8';
	// почтовые сервисы принимают письмо только от адреса своего ящика
	$name = $m->FromName && 'WordPress' !== $m->FromName ? $m->FromName : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$m->setFrom( $s['user'], $name, false );
	$m->Sender = $s['user'];
}, 20 );

/** Почему письмо не ушло: запоминаем для «Здоровья сайта» и отчёта. */
add_action( 'wp_mail_failed', function ( $err ) {
	$msg = is_wp_error( $err ) ? $err->get_error_message() : '';
	update_option( 'zhdanov_mail_error', array( 'time' => time(), 'msg' => wp_strip_all_tags( $msg ) ), false );
} );

add_action( 'wp_mail_succeeded', function () {
	delete_option( 'zhdanov_mail_error' );
	update_option( 'zhdanov_mail_ok', time(), false );
} );

/** Понятное объяснение ошибки отправки. */
function zhdanov_mail_error_human( $msg ) {
	$m = mb_strtolower( (string) $msg );
	if ( false !== strpos( $m, 'authenticate' ) || false !== strpos( $m, '535' ) || false !== strpos( $m, 'password' ) ) {
		return 'почтовый сервис не принял логин или пароль. Нужен «пароль приложения» из настроек почты (не обычный пароль), проверьте адрес отправителя';
	}
	if ( false !== strpos( $m, 'connect' ) || false !== strpos( $m, 'timed out' ) || false !== strpos( $m, 'timeout' ) ) {
		return 'сайт не смог подключиться к почтовому серверу: проверьте сервер и порт; некоторые хостинги закрывают внешнюю почту, тогда спросите в поддержке хостинга, какой порт открыт (обычно 465 или 587)';
	}
	if ( false !== strpos( $m, 'could not instantiate mail' ) || false !== strpos( $m, 'mail()' ) ) {
		return 'хостинг не отправляет почту сам: настройте отправку через почтовый ящик (Жданов → «Заявки с сайта»)';
	}
	return $msg;
}
