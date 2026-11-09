<?php
/**
 * Замечания клиента прямо на сайте (как Atarim, SureFeedback, Simple Commenter).
 *
 * Клиент открывает сайт по ссылке «для замечаний» (без пароля) или входит в админку,
 * жмёт «Замечание», кликает по месту на странице и пишет текст. Студии: Telegram + список
 * Жданов → «Замечания» со статусами «новое / в работе / готово». Кнопка «Страница согласована».
 *
 * @package zhdanov-core
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', function () {
	register_post_type( 'zh_note', array(
		'labels'       => array( 'name' => 'Замечания', 'singular_name' => 'Замечание' ),
		'public'       => false,
		'show_ui'      => false,
		'supports'     => array( 'title' ),
		'map_meta_cap' => true,
	) );
} );

function zhdanov_fb_token() {
	$t = get_option( 'zh_fb_token' );
	if ( ! $t ) {
		$t = wp_generate_password( 20, false );
		update_option( 'zh_fb_token', $t, false );
	}
	return $t;
}

function zhdanov_fb_on() {
	return (bool) get_option( 'zh_fb_on', 1 );
}

/** Ссылка для клиента. */
function zhdanov_fb_link() {
	return add_query_arg( 'zh_review', zhdanov_fb_token(), home_url( '/' ) );
}

/** Можно ли оставлять замечания: вошедший с правом правки или гость с куки из ссылки. */
function zhdanov_fb_can() {
	if ( ! zhdanov_fb_on() ) {
		return false;
	}
	if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
		return true;
	}
	$c = isset( $_COOKIE['zh_review'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['zh_review'] ) ) : '';
	return $c && hash_equals( zhdanov_fb_token(), $c );
}

function zhdanov_fb_can_manage() {
	return current_user_can( 'edit_others_pages' );
}

/* Ссылка ?zh_review=… ставит куки на 60 дней и убирает параметр из адреса */
add_action( 'template_redirect', function () {
	if ( empty( $_GET['zh_review'] ) || ! zhdanov_fb_on() ) { // phpcs:ignore
		return;
	}
	$t = sanitize_text_field( wp_unslash( $_GET['zh_review'] ) ); // phpcs:ignore
	if ( hash_equals( zhdanov_fb_token(), $t ) ) {
		setcookie( 'zh_review', $t, array( 'expires' => time() + 60 * DAY_IN_SECONDS, 'path' => COOKIEPATH ? COOKIEPATH : '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
		$_COOKIE['zh_review'] = $t;
		nocache_headers();
		wp_safe_redirect( remove_query_arg( 'zh_review' ) );
		exit;
	}
}, 0 );

/* Страницы с куки замечаний не кешировать */
add_action( 'send_headers', function () {
	if ( ! empty( $_COOKIE['zh_review'] ) ) {
		nocache_headers();
	}
} );

/* Скрипт на сайте */
add_action( 'wp_footer', function () {
	if ( is_admin() || ! zhdanov_fb_can() ) {
		return;
	}
	$u    = wp_get_current_user();
	$conf = array(
		'api'    => esc_url_raw( rest_url( 'zhdanov/v1/fb' ) ),
		'nonce'  => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
		'name'   => $u && $u->exists() ? $u->display_name : '',
		'manage' => zhdanov_fb_can_manage(),
		'admin'  => admin_url( 'admin.php?page=zhdanov-notes' ),
	);
	echo '<script>window.zhFb=' . wp_json_encode( $conf ) . ';</script>';
	echo '<script src="' . esc_url( ZHDANOV_CORE_URL . 'assets/feedback.js?v=' . ZHDANOV_CORE_VERSION ) . '" defer></script>'; // phpcs:ignore
}, 100 );

/* ---------- REST ---------- */

function zhdanov_fb_page_key( $url ) {
	$p = wp_parse_url( (string) $url );
	$path = isset( $p['path'] ) ? $p['path'] : '/';
	return '/' . trim( $path, '/' ) . ( '/' === $path || '' === trim( $path, '/' ) ? '' : '/' );
}

function zhdanov_fb_out( $p ) {
	return array(
		'id'     => $p->ID,
		'text'   => $p->post_content,
		'page'   => get_post_meta( $p->ID, '_zh_page', true ),
		'sel'    => get_post_meta( $p->ID, '_zh_sel', true ),
		'snip'   => get_post_meta( $p->ID, '_zh_snip', true ),
		'x'      => (float) get_post_meta( $p->ID, '_zh_x', true ),
		'y'      => (float) get_post_meta( $p->ID, '_zh_y', true ),
		'dev'    => get_post_meta( $p->ID, '_zh_dev', true ),
		'who'    => get_post_meta( $p->ID, '_zh_who', true ),
		'kind'   => get_post_meta( $p->ID, '_zh_kind', true ) ? get_post_meta( $p->ID, '_zh_kind', true ) : 'note',
		'status' => get_post_meta( $p->ID, '_zh_status', true ) ? get_post_meta( $p->ID, '_zh_status', true ) : 'new',
		'date'   => get_the_date( 'd.m H:i', $p ),
	);
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'zhdanov/v1', '/fb', array(
		array(
			'methods'             => 'GET',
			'permission_callback' => 'zhdanov_fb_can',
			'callback'            => function ( $req ) {
				$key = zhdanov_fb_page_key( $req->get_param( 'page' ) );
				$q   = get_posts( array( 'post_type' => 'zh_note', 'post_status' => 'publish', 'numberposts' => 200, 'meta_key' => '_zh_page', 'meta_value' => $key, 'orderby' => 'date', 'order' => 'ASC' ) ); // phpcs:ignore
				return array_map( 'zhdanov_fb_out', $q );
			},
		),
		array(
			'methods'             => 'POST',
			'permission_callback' => 'zhdanov_fb_can',
			'callback'            => 'zhdanov_fb_create',
		),
	) );
	register_rest_route( 'zhdanov/v1', '/fb/(?P<id>\d+)', array(
		'methods'             => 'POST',
		'permission_callback' => 'zhdanov_fb_can_manage',
		'callback'            => function ( $req ) {
			$p = get_post( (int) $req['id'] );
			if ( ! $p || 'zh_note' !== $p->post_type ) {
				return new WP_Error( 'nf', 'Нет такого замечания', array( 'status' => 404 ) );
			}
			$st = sanitize_key( (string) $req->get_param( 'status' ) );
			if ( 'delete' === $st ) {
				wp_delete_post( $p->ID, true );
				return array( 'ok' => true );
			}
			if ( in_array( $st, array( 'new', 'work', 'done' ), true ) ) {
				if ( 'done' === $st && 'done' !== get_post_meta( $p->ID, '_zh_status', true ) && function_exists( 'zhdanov_stat' ) ) {
					zhdanov_stat( 'notes_done' );
				}
				update_post_meta( $p->ID, '_zh_status', $st );
			}
			return zhdanov_fb_out( get_post( $p->ID ) );
		},
	) );
} );

function zhdanov_fb_create( $req ) {
	// не больше 60 замечаний в час с одного адреса
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'zh_fb_rate_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n > 60 ) {
		return new WP_Error( 'rate', 'Слишком много замечаний подряд, попробуйте через час.', array( 'status' => 429 ) );
	}
	set_transient( $key, $n + 1, HOUR_IN_SECONDS );

	$kind = 'approve' === $req->get_param( 'kind' ) ? 'approve' : 'note';
	$text = trim( sanitize_textarea_field( (string) $req->get_param( 'text' ) ) );
	if ( 'note' === $kind && '' === $text ) {
		return new WP_Error( 'empty', 'Напишите, что поменять.', array( 'status' => 400 ) );
	}
	$text = mb_substr( $text, 0, 3000 );
	$page = zhdanov_fb_page_key( $req->get_param( 'page' ) );
	$u    = wp_get_current_user();
	$who  = $u && $u->exists() ? $u->display_name : mb_substr( sanitize_text_field( (string) $req->get_param( 'who' ) ), 0, 60 );
	if ( '' === $who ) {
		$who = 'Клиент';
	}
	$title = mb_substr( 'approve' === $kind ? 'Страница согласована' : $text, 0, 80 );
	$id    = wp_insert_post( array( 'post_type' => 'zh_note', 'post_status' => 'publish', 'post_title' => $title, 'post_content' => 'approve' === $kind ? ( $text ? $text : 'Согласовано' ) : $text ), true );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	$meta = array(
		'_zh_page'   => $page,
		'_zh_sel'    => mb_substr( sanitize_text_field( (string) $req->get_param( 'sel' ) ), 0, 500 ),
		'_zh_snip'   => mb_substr( sanitize_text_field( (string) $req->get_param( 'snip' ) ), 0, 120 ),
		'_zh_x'      => max( 0, min( 100, (float) $req->get_param( 'x' ) ) ),
		'_zh_y'      => max( 0, (float) $req->get_param( 'y' ) ),
		'_zh_dev'    => mb_substr( sanitize_text_field( (string) $req->get_param( 'dev' ) ), 0, 80 ),
		'_zh_who'    => $who,
		'_zh_kind'   => $kind,
		'_zh_status' => 'approve' === $kind ? 'done' : 'new',
	);
	foreach ( $meta as $k => $v ) {
		update_post_meta( $id, $k, $v );
	}
	// уведомление студии
	$token = trim( (string) zhdanov_core_opt( 'studio_tg_token' ) );
	$chat  = trim( (string) zhdanov_core_opt( 'studio_tg_chat' ) );
	if ( $token && $chat && function_exists( 'zhdanov_core_tg_send' ) ) {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$msg  = 'approve' === $kind
			? '✅ ' . $host . $page . ': ' . $who . ' согласовал(а) страницу' . ( $text ? ': ' . $text : '' )
			: '📝 Замечание ' . $host . $page . "\n" . $who . ': ' . $text . ( $meta['_zh_snip'] ? "\nГде: «" . $meta['_zh_snip'] . '»' : '' ) . ( $meta['_zh_dev'] ? "\n" . $meta['_zh_dev'] : '' ) . "\n" . admin_url( 'admin.php?page=zhdanov-notes' );
		zhdanov_core_tg_send( $token, $chat, $msg );
	}
	if ( function_exists( 'zhdanov_log' ) ) {
		zhdanov_log( ( 'approve' === $kind ? 'Согласована страница ' : 'Замечание на ' ) . $page . ' (' . $who . ')' );
	}
	return zhdanov_fb_out( get_post( $id ) );
}

/* ---------- Админка ---------- */

add_action( 'admin_menu', function () {
	$n = count( get_posts( array( 'post_type' => 'zh_note', 'numberposts' => 99, 'fields' => 'ids', 'meta_key' => '_zh_status', 'meta_value' => 'new' ) ) ); // phpcs:ignore
	add_submenu_page( 'zhdanov-core', 'Замечания клиента', 'Замечания' . ( $n ? ' <span class="awaiting-mod">' . $n . '</span>' : '' ), 'edit_others_pages', 'zhdanov-notes', 'zhdanov_fb_page' );
}, 25 );

add_action( 'admin_post_zhdanov_fb_opts', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Нет прав.', 403 );
	}
	check_admin_referer( 'zhdanov_fb_opts' );
	update_option( 'zh_fb_on', empty( $_POST['zh_fb_on'] ) ? 0 : 1, false );
	if ( ! empty( $_POST['zh_fb_new'] ) ) {
		update_option( 'zh_fb_token', wp_generate_password( 20, false ), false );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=zhdanov-notes' ) );
	exit;
} );

function zhdanov_fb_page() {
	$filter = isset( $_GET['st'] ) ? sanitize_key( $_GET['st'] ) : 'open'; // phpcs:ignore
	$all    = get_posts( array( 'post_type' => 'zh_note', 'numberposts' => 500, 'orderby' => 'date', 'order' => 'DESC' ) );
	$cnt    = array( 'new' => 0, 'work' => 0, 'done' => 0 );
	$pages  = array();
	foreach ( $all as $p ) {
		$o = zhdanov_fb_out( $p );
		if ( 'note' === $o['kind'] ) {
			$cnt[ $o['status'] ] = isset( $cnt[ $o['status'] ] ) ? $cnt[ $o['status'] ] + 1 : 1;
		}
		$show = 'all' === $filter || ( 'open' === $filter && 'done' !== $o['status'] ) || $filter === $o['status'] || ( 'approve' === $filter && 'approve' === $o['kind'] );
		if ( $show ) {
			$pages[ $o['page'] ][] = $o;
		}
	}
	echo '<div class="wrap"><h1>Замечания клиента</h1>';
	echo '<p>Клиент открывает сайт по ссылке ниже (пароль не нужен), жмёт «Замечание» внизу слева, кликает по месту на странице и пишет, что поменять. Вы видите замечания здесь и в Telegram студии. Вошедшие в админку тоже могут оставлять замечания на сайте.</p>';
	if ( current_user_can( 'manage_options' ) ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="background:#fff;border:1px solid #dcdcde;padding:12px 16px;max-width:900px">';
		wp_nonce_field( 'zhdanov_fb_opts' );
		echo '<input type="hidden" name="action" value="zhdanov_fb_opts">';
		echo '<p><b>Ссылка для клиента:</b><br><input type="text" readonly class="large-text code" id="zh-fb-link" value="' . esc_attr( zhdanov_fb_link() ) . '" onclick="this.select()"></p>';
		echo '<p><label><input type="checkbox" name="zh_fb_on" value="1"' . checked( zhdanov_fb_on(), true, false ) . '> Замечания включены</label> &nbsp; <label><input type="checkbox" name="zh_fb_new" value="1"> Сделать новую ссылку (старая перестанет работать)</label></p>';
		submit_button( 'Сохранить', 'secondary', 'zh_fb_save', false );
		echo '</form>';
	}
	$tabs = array( 'open' => 'Открытые (' . ( $cnt['new'] + $cnt['work'] ) . ')', 'new' => 'Новые (' . $cnt['new'] . ')', 'work' => 'В работе (' . $cnt['work'] . ')', 'done' => 'Готово (' . $cnt['done'] . ')', 'approve' => 'Согласования', 'all' => 'Все' );
	echo '<p style="margin-top:18px">';
	foreach ( $tabs as $k => $l ) {
		echo $k === $filter ? '<b>' . esc_html( $l ) . '</b>' : '<a href="' . esc_url( admin_url( 'admin.php?page=zhdanov-notes&st=' . $k ) ) . '">' . esc_html( $l ) . '</a>';
		echo ' &nbsp;·&nbsp; ';
	}
	echo '</p>';
	if ( ! $pages ) {
		echo '<p><i>Здесь пока пусто.</i></p>';
	}
	$labels = array( 'new' => 'Новое', 'work' => 'В работе', 'done' => 'Готово' );
	foreach ( $pages as $page => $items ) {
		echo '<h2 style="margin-top:22px"><a href="' . esc_url( home_url( $page ) ) . '" target="_blank">' . esc_html( $page ) . '</a></h2>';
		echo '<table class="widefat striped" style="max-width:1100px"><tbody>';
		foreach ( $items as $o ) {
			echo '<tr data-id="' . (int) $o['id'] . '"><td style="width:40px">#' . (int) $o['id'] . '</td><td>';
			if ( 'approve' === $o['kind'] ) {
				echo '✅ <b>Страница согласована</b> ' . esc_html( 'Согласовано' !== $o['text'] ? $o['text'] : '' );
			} else {
				echo esc_html( $o['text'] );
				if ( $o['snip'] ) {
					echo '<br><small style="color:#666">Где: «' . esc_html( $o['snip'] ) . '»</small>';
				}
			}
			echo '<br><small style="color:#666">' . esc_html( $o['who'] . ' · ' . $o['date'] . ( $o['dev'] ? ' · ' . $o['dev'] : '' ) ) . '</small></td>';
			echo '<td style="width:300px;white-space:nowrap">';
			if ( 'note' === $o['kind'] ) {
				echo '<a class="button button-small" target="_blank" href="' . esc_url( home_url( $o['page'] ) . '#zh-note-' . $o['id'] ) . '">Показать на странице</a> ';
				echo '<select class="zh-fb-st">';
				foreach ( $labels as $k => $l ) {
					echo '<option value="' . esc_attr( $k ) . '"' . selected( $o['status'], $k, false ) . '>' . esc_html( $l ) . '</option>';
				}
				echo '</select> ';
			}
			echo '<button type="button" class="button-link zh-fb-del" style="color:#b32d2e">Удалить</button></td></tr>';
		}
		echo '</tbody></table>';
	}
	echo '</div>';
	wp_enqueue_script( 'wp-api-fetch' );
	?>
	<script>
	document.addEventListener('DOMContentLoaded',function(){
		function send(tr,st){ return wp.apiFetch({path:'/zhdanov/v1/fb/'+tr.dataset.id,method:'POST',data:{status:st}}); }
		document.querySelectorAll('.zh-fb-st').forEach(function(s){ s.addEventListener('change',function(){ var tr=s.closest('tr'); send(tr,s.value).then(function(){ tr.style.opacity=s.value==='done'?.55:1; }); }); });
		document.querySelectorAll('.zh-fb-del').forEach(function(b){ b.addEventListener('click',function(){ if(!confirm('Удалить замечание?'))return; var tr=b.closest('tr'); send(tr,'delete').then(function(){ tr.remove(); }); }); });
	});
	</script>
	<?php
}
