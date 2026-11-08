<?php
/**
 * Вход с кодом из приложения (2FA, TOTP): по желанию в профиле. После пароля нужен 6-значный код из
 * Google Authenticator, Яндекс Ключа, 1Password и т. п. Запасные одноразовые коды на случай потери телефона.
 * Администратор может выключить 2FA у другого пользователя. Вход по ссылке («Вход по ссылке») работает без кода:
 * ссылку выдаёт администратор.
 *
 * @package zhdanov-core
 */

defined( 'ABSPATH' ) || exit;

/* ---------- TOTP (RFC 6238): 30 секунд, 6 цифр, SHA1 ---------- */

function zhdanov_2fa_b32( $len = 16 ) {
	$a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
	$s = '';
	for ( $i = 0; $i < $len; $i++ ) {
		$s .= $a[ random_int( 0, 31 ) ];
	}
	return $s;
}

function zhdanov_2fa_b32dec( $s ) {
	$a    = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
	$s    = strtoupper( preg_replace( '/[^A-Za-z2-7]/', '', $s ) );
	$bits = '';
	foreach ( str_split( $s ) as $c ) {
		$bits .= str_pad( decbin( strpos( $a, $c ) ), 5, '0', STR_PAD_LEFT );
	}
	$out = '';
	foreach ( str_split( $bits, 8 ) as $b ) {
		if ( 8 === strlen( $b ) ) {
			$out .= chr( bindec( $b ) );
		}
	}
	return $out;
}

function zhdanov_2fa_code( $secret, $step ) {
	$h = hash_hmac( 'sha1', pack( 'N*', 0, $step ), zhdanov_2fa_b32dec( $secret ), true );
	$o = ord( $h[19] ) & 0xf;
	$n = ( ( ord( $h[ $o ] ) & 0x7f ) << 24 ) | ( ord( $h[ $o + 1 ] ) << 16 ) | ( ord( $h[ $o + 2 ] ) << 8 ) | ord( $h[ $o + 3 ] );
	return str_pad( (string) ( $n % 1000000 ), 6, '0', STR_PAD_LEFT );
}

/** Код верный (с запасом ±30 секунд) и ещё не использован. */
function zhdanov_2fa_check( $uid, $secret, $code ) {
	$code = preg_replace( '/\D/', '', (string) $code );
	if ( 6 !== strlen( $code ) ) {
		return false;
	}
	$now  = (int) floor( time() / 30 );
	$last = (int) get_user_meta( $uid, '_zh_2fa_last', true );
	for ( $i = -1; $i <= 1; $i++ ) {
		if ( $now + $i > $last && hash_equals( zhdanov_2fa_code( $secret, $now + $i ), $code ) ) {
			update_user_meta( $uid, '_zh_2fa_last', $now + $i ); // повторно тот же код не пройдёт
			return true;
		}
	}
	return false;
}

/** Запасной код (одноразовый). */
function zhdanov_2fa_backup( $uid, $code ) {
	$code  = strtolower( preg_replace( '/[^a-z0-9]/i', '', (string) $code ) );
	$codes = (array) get_user_meta( $uid, '_zh_2fa_backup', true );
	foreach ( $codes as $i => $h ) {
		if ( strlen( $code ) >= 8 && wp_check_password( $code, $h ) ) {
			unset( $codes[ $i ] );
			update_user_meta( $uid, '_zh_2fa_backup', array_values( $codes ) );
			return true;
		}
	}
	return false;
}

function zhdanov_2fa_on( $uid ) {
	return (bool) get_user_meta( $uid, '_zh_2fa', true );
}

/* ---------- Вход: поле «Код из приложения» ---------- */

add_action( 'login_form', function () {
	echo '<p><label for="zh_2fa">Код из приложения <span style="font-weight:400;color:#646970">(если включён вход с кодом)</span></label><input type="text" name="zh_2fa" id="zh_2fa" class="input" inputmode="numeric" autocomplete="one-time-code" size="20" style="letter-spacing:.2em"></p>';
} );

add_filter( 'authenticate', function ( $user, $login ) {
	if ( ! ( $user instanceof WP_User ) || ! zhdanov_2fa_on( $user->ID ) || did_action( 'zhdanov_magic_login' ) ) {
		return $user;
	}
	$code = isset( $_POST['zh_2fa'] ) ? sanitize_text_field( wp_unslash( $_POST['zh_2fa'] ) ) : ''; // phpcs:ignore
	if ( '' === $code ) {
		return new WP_Error( 'zh_2fa', 'Введите код из приложения (Google Authenticator, Яндекс Ключ) или запасной код.' );
	}
	$secret = (string) get_user_meta( $user->ID, '_zh_2fa', true );
	if ( zhdanov_2fa_check( $user->ID, $secret, $code ) || zhdanov_2fa_backup( $user->ID, $code ) ) {
		return $user;
	}
	do_action( 'wp_login_failed', $user->user_login, new WP_Error( 'zh_2fa_bad' ) );
	return new WP_Error( 'zh_2fa', 'Код не подошёл. Проверьте время на телефоне и введите новый код.' );
}, 40, 2 );

/* ---------- Профиль: включить, выключить ---------- */

$zhdanov_2fa_profile = function ( $u ) {
	$self = get_current_user_id() === $u->ID;
	echo '<h2 id="zh-2fa">Вход с кодом из приложения (двухфакторная защита)</h2><table class="form-table" role="presentation"><tr><th>Состояние</th><td>';
	if ( zhdanov_2fa_on( $u->ID ) ) {
		$left = count( (array) get_user_meta( $u->ID, '_zh_2fa_backup', true ) );
		echo '<p><b>Включено ✅</b> При входе после пароля нужен код из приложения. Запасных кодов осталось: ' . (int) $left . '.</p>';
		echo '<p><label><input type="checkbox" name="zh_2fa_off" value="1"> Выключить вход с кодом</label></p>';
	} elseif ( $self ) {
		$secret = (string) get_user_meta( $u->ID, '_zh_2fa_pending', true );
		if ( ! $secret ) {
			$secret = zhdanov_2fa_b32( 16 );
			update_user_meta( $u->ID, '_zh_2fa_pending', $secret );
		}
		$label = rawurlencode( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . ':' . $u->user_login );
		$uri   = 'otpauth://totp/' . $label . '?secret=' . $secret . '&issuer=' . rawurlencode( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ) . '&digits=6&period=30';
		echo '<p>Защита от взлома: даже если пароль узнают, без вашего телефона войти нельзя.</p><ol style="margin-left:1.2em">';
		echo '<li>Установите на телефон приложение для кодов: Google Authenticator, Яндекс Ключ или 1Password.</li>';
		echo '<li>В приложении нажмите «+» → «Ввести ключ вручную» и введите ключ: <code style="font-size:15px;letter-spacing:.08em">' . esc_html( trim( chunk_split( $secret, 4, ' ' ) ) ) . '</code> (тип: по времени). На телефоне можно просто нажать: <a href="' . esc_attr( $uri ) . '">добавить в приложение</a>.</li>';
		echo '<li>Введите 6 цифр из приложения: <input type="text" name="zh_2fa_confirm" inputmode="numeric" autocomplete="one-time-code" size="8" style="letter-spacing:.2em"> и нажмите «Обновить профиль» внизу.</li></ol>';
	} else {
		echo '<p>Выключено. Включить может только сам пользователь в своём профиле.</p>';
	}
	echo '</td></tr></table>';
	$codes = get_transient( 'zh_2fa_codes_' . $u->ID );
	if ( $codes && $self ) {
		delete_transient( 'zh_2fa_codes_' . $u->ID );
		echo '<div class="notice notice-warning inline"><p><b>Запасные коды</b> (каждый срабатывает один раз, если телефона нет под рукой). Сохраните их сейчас: больше они не покажутся.</p><p><code style="font-size:15px">' . esc_html( implode( '   ', $codes ) ) . '</code></p></div>';
	}
};
add_action( 'show_user_profile', $zhdanov_2fa_profile );
add_action( 'edit_user_profile', $zhdanov_2fa_profile );

$zhdanov_2fa_save = function ( $uid ) {
	if ( ! current_user_can( 'edit_user', $uid ) ) {
		return;
	}
	if ( ! empty( $_POST['zh_2fa_off'] ) ) { // phpcs:ignore
		delete_user_meta( $uid, '_zh_2fa' );
		delete_user_meta( $uid, '_zh_2fa_backup' );
		if ( function_exists( 'zhdanov_log' ) ) {
			zhdanov_log( '2FA выключена', get_userdata( $uid )->display_name );
		}
		return;
	}
	$code = isset( $_POST['zh_2fa_confirm'] ) ? sanitize_text_field( wp_unslash( $_POST['zh_2fa_confirm'] ) ) : ''; // phpcs:ignore
	$pend = (string) get_user_meta( $uid, '_zh_2fa_pending', true );
	if ( '' === $code || ! $pend || get_current_user_id() !== $uid ) {
		return;
	}
	if ( ! zhdanov_2fa_check( $uid, $pend, $code ) ) {
		add_action( 'user_profile_update_errors', function ( $errors ) {
			$errors->add( 'zh_2fa', 'Код из приложения не подошёл: вход с кодом не включён. Проверьте ключ и время на телефоне.' );
		} );
		return;
	}
	update_user_meta( $uid, '_zh_2fa', $pend );
	delete_user_meta( $uid, '_zh_2fa_pending' );
	$plain = array();
	$hash  = array();
	for ( $i = 0; $i < 8; $i++ ) {
		$c       = strtolower( wp_generate_password( 10, false, false ) );
		$plain[] = $c;
		$hash[]  = wp_hash_password( $c );
	}
	update_user_meta( $uid, '_zh_2fa_backup', $hash );
	set_transient( 'zh_2fa_codes_' . $uid, $plain, 10 * MINUTE_IN_SECONDS );
	if ( function_exists( 'zhdanov_log' ) ) {
		zhdanov_log( '2FA включена', get_userdata( $uid )->display_name );
	}
};
add_action( 'personal_options_update', $zhdanov_2fa_save );
add_action( 'edit_user_profile_update', $zhdanov_2fa_save );
