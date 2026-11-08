<?php
/**
 * Режим клиента и вход по ссылке.
 * - Роль «Клиент сайта» (zh_client): правит страницы, записи, разделы «Жданов», фото, видит заявки; не видит плагины, темы,
 *   настройки, инструменты, пользователей. Сломать сайт нельзя.
 * - Упрощённая админка для всех, кто не администратор: без уведомлений об обновлениях, без новостей WordPress,
 *   подпись поддержки внизу, свой экран входа (логотип и название сайта).
 * - «Вход по ссылке»: администратор выдаёт ссылку на 1–7 дней (по желанию одноразовую) для студии, помощника,
 *   дизайнера: вход без пароля, отзыв одной кнопкой, все входы записываются в журнал.
 *
 * @package zhdanov-core
 */

defined( 'ABSPATH' ) || exit;

/* ---------- Роль «Клиент сайта» ---------- */

add_action( 'init', function () {
	if ( get_role( 'zh_client' ) && '2' === get_option( 'zh_client_role_v' ) ) {
		return;
	}
	remove_role( 'zh_client' );
	$caps = get_role( 'editor' ) ? get_role( 'editor' )->capabilities : array( 'read' => true );
	unset( $caps['unfiltered_html'] ); // клиент не вставляет скрипты
	add_role( 'zh_client', 'Клиент сайта', $caps + array( 'upload_files' => true, 'edit_theme_options' => false ) );
	update_option( 'zh_client_role_v', '2', false );
} );

/** Упрощать ли админку для этого пользователя: всем, кроме администраторов. */
function zhdanov_client_simple() {
	return zhdanov_core_opt( 'client_mode' ) && is_user_logged_in() && ! current_user_can( 'manage_options' );
}

add_action( 'admin_menu', function () {
	if ( ! zhdanov_client_simple() ) {
		return;
	}
	foreach ( array( 'tools.php', 'edit-comments.php', 'themes.php', 'plugins.php', 'users.php', 'options-general.php' ) as $m ) {
		remove_menu_page( $m );
	}
}, 999 );

// уведомления об обновлениях и чужие баннеры клиенту не показываем
add_action( 'admin_head', function () {
	if ( ! zhdanov_client_simple() ) {
		return;
	}
	remove_action( 'admin_notices', 'update_nag', 3 );
	remove_action( 'network_admin_notices', 'update_nag', 3 );
	remove_action( 'admin_notices', 'maintenance_nag', 10 );
	echo '<style>#wp-admin-bar-wp-logo,#wp-admin-bar-updates,#wp-admin-bar-comments,.update-nag,#footer-upgrade{display:none!important}</style>';
}, 1 );

add_action( 'wp_dashboard_setup', function () {
	if ( ! zhdanov_client_simple() ) {
		return;
	}
	foreach ( array( 'dashboard_primary', 'dashboard_quick_press', 'dashboard_site_health', 'dashboard_activity', 'dashboard_right_now' ) as $w ) {
		remove_meta_box( $w, 'dashboard', 'normal' );
		remove_meta_box( $w, 'dashboard', 'side' );
	}
	remove_action( 'welcome_panel', 'wp_welcome_panel' );
}, 99 );

add_filter( 'admin_footer_text', function ( $t ) {
	if ( ! zhdanov_core_opt( 'client_mode' ) ) {
		return $t;
	}
	$c = trim( (string) zhdanov_core_opt( 'support_contact' ) );
	return 'Сайт сделан в <a href="https://zhdanovsites.com/" target="_blank" rel="noopener">Zhdanov Sites</a>' . ( $c ? ' · Поддержка: ' . esc_html( $c ) : '' );
} );

/* ---------- Экран входа: логотип и название сайта ---------- */

add_action( 'login_enqueue_scripts', function () {
	if ( ! zhdanov_core_opt( 'client_mode' ) ) {
		return;
	}
	$logo = '';
	$lid  = (int) get_theme_mod( 'custom_logo' );
	if ( $lid ) {
		$logo = wp_get_attachment_image_url( $lid, 'medium' );
	} elseif ( has_site_icon() ) {
		$logo = get_site_icon_url( 192 );
	}
	echo '<style>';
	if ( $logo ) {
		echo '#login h1 a{background-image:url(' . esc_url( $logo ) . ');background-size:contain;width:180px;height:90px}';
	} else {
		echo '#login h1 a{background:none;text-indent:0;width:auto;height:auto;font-size:22px;font-weight:600;color:#1d2327}';
	}
	echo 'body.login{background:#f6f7f7}</style>';
} );
add_filter( 'login_headerurl', function ( $u ) {
	return zhdanov_core_opt( 'client_mode' ) ? home_url( '/' ) : $u;
} );
add_filter( 'login_headertext', function ( $t ) {
	return zhdanov_core_opt( 'client_mode' ) ? get_bloginfo( 'name' ) : $t;
} );

/* ---------- Вход по ссылке ---------- */

function zhdanov_magic_list() {
	return get_users( array( 'meta_key' => '_zh_magic', 'number' => 50 ) ); // phpcs:ignore
}

/** Создать ссылку: [ 'url' => …, 'until' => ts ] */
function zhdanov_magic_create( $user_id, $days, $once ) {
	$token = wp_generate_password( 40, false, false );
	$until = time() + max( 1, min( 30, (int) $days ) ) * DAY_IN_SECONDS;
	update_user_meta( $user_id, '_zh_magic', array( 'h' => wp_hash_password( $token ), 'until' => $until, 'once' => $once ? 1 : 0, 'by' => get_current_user_id(), 'made' => time() ) );
	return array( 'url' => add_query_arg( 'zh_login', $user_id . '.' . $token, home_url( '/' ) ), 'until' => $until );
}

add_action( 'init', function () {
	if ( empty( $_GET['zh_login'] ) || is_user_logged_in() && ! isset( $_GET['zh_force'] ) ) { // phpcs:ignore
		if ( ! empty( $_GET['zh_login'] ) && is_user_logged_in() ) { // phpcs:ignore
			wp_safe_redirect( admin_url() );
			exit;
		}
		return;
	}
	$ipk = 'zh_ml_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
	if ( (int) get_transient( $ipk ) >= 10 ) {
		wp_die( 'Слишком много попыток входа по ссылке. Подождите час.', 'Вход', array( 'response' => 429 ) );
	}
	$raw = sanitize_text_field( wp_unslash( $_GET['zh_login'] ) ); // phpcs:ignore
	$ok  = false;
	if ( preg_match( '/^(\d+)\.([A-Za-z0-9]{40})$/', $raw, $m ) ) {
		$uid = (int) $m[1];
		$d   = get_user_meta( $uid, '_zh_magic', true );
		if ( is_array( $d ) && ! empty( $d['h'] ) && $d['until'] > time() && wp_check_password( $m[2], $d['h'] ) ) {
			$ok = $uid;
			if ( ! empty( $d['once'] ) ) {
				delete_user_meta( $uid, '_zh_magic' );
			}
		}
	}
	if ( ! $ok ) {
		set_transient( $ipk, (int) get_transient( $ipk ) + 1, HOUR_IN_SECONDS );
		wp_die( 'Ссылка для входа устарела или отозвана. Попросите новую у администратора сайта.', 'Вход', array( 'response' => 403 ) );
	}
	wp_set_current_user( $ok );
	wp_set_auth_cookie( $ok, false );
	do_action( 'zhdanov_magic_login', $ok );
	do_action( 'wp_login', get_userdata( $ok )->user_login, get_userdata( $ok ) );
	wp_safe_redirect( admin_url() );
	exit;
}, 1 );

add_action( 'admin_menu', function () {
	add_submenu_page( 'zhdanov-core', 'Вход по ссылке и доступ клиента', 'Вход по ссылке', 'manage_options', 'zhdanov-access', 'zhdanov_access_page' );
}, 45 );

add_action( 'admin_post_zhdanov_magic', function () {
	check_admin_referer( 'zh_magic' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Нет доступа' );
	}
	$do = isset( $_POST['do'] ) ? sanitize_key( $_POST['do'] ) : ''; // phpcs:ignore
	if ( 'revoke' === $do ) {
		delete_user_meta( (int) $_POST['uid'], '_zh_magic' ); // phpcs:ignore
		wp_safe_redirect( admin_url( 'admin.php?page=zhdanov-access&m=revoked' ) );
		exit;
	}
	$uid = (int) ( $_POST['uid'] ?? 0 ); // phpcs:ignore
	if ( 'new' === $do ) { // новый пользователь: студия, помощник, дизайнер или клиент
		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ); // phpcs:ignore
		$role  = in_array( $_POST['role'] ?? '', array( 'zh_client', 'editor', 'administrator' ), true ) ? $_POST['role'] : 'zh_client'; // phpcs:ignore
		$name  = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ); // phpcs:ignore
		if ( ! is_email( $email ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=zhdanov-access&m=bademail' ) );
			exit;
		}
		$ex = get_user_by( 'email', $email );
		if ( $ex ) {
			$uid = $ex->ID;
		} else {
			$login = sanitize_user( strtok( $email, '@' ), true ) ?: 'user';
			while ( username_exists( $login ) ) {
				$login .= wp_rand( 1, 9 );
			}
			$uid = wp_insert_user( array( 'user_login' => $login, 'user_email' => $email, 'user_pass' => wp_generate_password( 24 ), 'role' => $role, 'display_name' => $name ? $name : $login, 'first_name' => $name ) );
			if ( is_wp_error( $uid ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=zhdanov-access&m=err' ) );
				exit;
			}
		}
	}
	if ( ! $uid || ! get_userdata( $uid ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=zhdanov-access&m=err' ) );
		exit;
	}
	$r = zhdanov_magic_create( $uid, (int) ( $_POST['days'] ?? 3 ), ! empty( $_POST['once'] ) ); // phpcs:ignore
	set_transient( 'zh_magic_show_' . get_current_user_id(), array( 'url' => $r['url'], 'who' => get_userdata( $uid )->display_name, 'until' => $r['until'] ), 10 * MINUTE_IN_SECONDS );
	wp_safe_redirect( admin_url( 'admin.php?page=zhdanov-access&m=made' ) );
	exit;
} );

function zhdanov_access_page() {
	$m    = isset( $_GET['m'] ) ? sanitize_key( $_GET['m'] ) : ''; // phpcs:ignore
	$show = get_transient( 'zh_magic_show_' . get_current_user_id() );
	echo '<div class="wrap"><h1>Вход по ссылке и доступ клиента</h1>';
	if ( 'made' === $m && $show ) {
		delete_transient( 'zh_magic_show_' . get_current_user_id() );
		echo '<div class="notice notice-success"><p><b>Ссылка для входа (' . esc_html( $show['who'] ) . ', действует до ' . esc_html( wp_date( 'd.m.Y H:i', $show['until'] ) ) . '):</b></p><p><input type="text" readonly style="width:100%;max-width:760px" value="' . esc_attr( $show['url'] ) . '" onclick="this.select()"></p><p>Скопируйте и отправьте лично (в мессенджер). Ссылка показывается один раз. Кто знает ссылку, тот входит без пароля: не публикуйте её.</p></div>';
	} elseif ( 'revoked' === $m ) {
		echo '<div class="notice notice-success"><p>Ссылка отозвана: по ней больше не войти.</p></div>';
	} elseif ( $m ) {
		echo '<div class="notice notice-error"><p>Не получилось: проверьте почту и попробуйте ещё раз.</p></div>';
	}
	$f = function ( $inner ) {
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="background:#fff;border:1px solid #dcdcde;padding:12px 16px;max-width:760px;margin:12px 0"><input type="hidden" name="action" value="zhdanov_magic">' . wp_nonce_field( 'zh_magic', '_wpnonce', true, false ) . $inner . '</form>';
	};
	$days = '<select name="days"><option value="1">1 день</option><option value="3" selected>3 дня</option><option value="7">7 дней</option><option value="30">30 дней</option></select> <label><input type="checkbox" name="once" value="1"> одноразовая (после входа сгорает)</label>';
	echo '<p style="max-width:760px">Ссылка позволяет войти без пароля: удобно для дизайнера, помощника или клиента на созвоне. Пароль никому сообщать не нужно. Срок задаёте вы, отозвать можно в любой момент.</p>';
	echo '<h2>Новый человек</h2>' . $f( '<input type="hidden" name="do" value="new"><p><input type="text" name="name" placeholder="Имя (например, Alex 1 или клиент Сергей)" style="width:260px"> <input type="email" name="email" required placeholder="почта" style="width:220px"></p><p>Права: <select name="role"><option value="zh_client">Клиент сайта (только содержимое, ничего не сломает)</option><option value="editor">Редактор</option><option value="administrator">Администратор (всё, включая плагины)</option></select></p><p>Срок: ' . $days . '</p><p><button class="button button-primary">Создать ссылку для входа</button></p>' ); // phpcs:ignore
	$opts = '';
	foreach ( get_users( array( 'number' => 200, 'orderby' => 'display_name' ) ) as $u ) {
		$opts .= '<option value="' . (int) $u->ID . '">' . esc_html( $u->display_name . ' (' . implode( ', ', array_map( 'translate_user_role', array_map( function ( $r ) {
			$n = wp_roles()->role_names;
			return isset( $n[ $r ] ) ? $n[ $r ] : $r;
		}, $u->roles ) ) ) . ')' ) . '</option>';
	}
	echo '<h2>Уже есть на сайте</h2>' . $f( '<input type="hidden" name="do" value="link"><p><select name="uid">' . $opts . '</select></p><p>Срок: ' . $days . '</p><p><button class="button">Создать ссылку</button></p>' ); // phpcs:ignore
	$rows = '';
	foreach ( zhdanov_magic_list() as $u ) {
		$d = get_user_meta( $u->ID, '_zh_magic', true );
		if ( ! is_array( $d ) ) {
			continue;
		}
		$rows .= '<tr><td>' . esc_html( $u->display_name ) . '</td><td>' . esc_html( wp_date( 'd.m.Y H:i', $d['until'] ) ) . ( $d['until'] < time() ? ' (истекла)' : '' ) . '</td><td>' . ( ! empty( $d['once'] ) ? 'одноразовая' : 'многоразовая' ) . '</td><td>' . $f( '<input type="hidden" name="do" value="revoke"><input type="hidden" name="uid" value="' . (int) $u->ID . '"><button class="button button-link-delete">Отозвать</button>' ) . '</td></tr>'; // phpcs:ignore
	}
	if ( $rows ) {
		echo '<h2>Действующие ссылки</h2><table class="widefat striped" style="max-width:760px"><thead><tr><th>Кто</th><th>До</th><th>Тип</th><th></th></tr></thead><tbody>' . $rows . '</tbody></table>'; // phpcs:ignore
	}
	echo '</div>';
}
