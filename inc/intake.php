<?php
/**
 * Анкета «Материалы для сайта» (как Content Snare): клиент по ссылке присылает логотип, фото,
 * тексты, цены, контакты, реквизиты. Файлы сразу в медиатеку, студии уведомление в Telegram.
 * Жданов → «Материалы от клиента»: ссылка, список пунктов (можно править), ответы, «принято».
 *
 * @package zhdanov-core
 */

defined( 'ABSPATH' ) || exit;

function zhdanov_in_default_items() {
	return array(
		array( 'id' => 'logo', 'title' => 'Логотип', 'hint' => 'Файл в лучшем качестве: PNG с прозрачным фоном или PDF (SVG пришлите в архиве или почтой). Если логотипа нет, напишите «нет».', 'type' => 'both' ),
		array( 'id' => 'photos', 'title' => 'Фото', 'hint' => 'Ваши фото: команда, помещение, работы, товары. Чем больше, тем лучше. Можно сразу много файлов.', 'type' => 'both' ),
		array( 'id' => 'about', 'title' => 'О вас', 'hint' => 'Кто вы, чем занимаетесь, чем отличаетесь от других. Можно списком, мы оформим.', 'type' => 'text' ),
		array( 'id' => 'services', 'title' => 'Услуги или товары и цены', 'hint' => 'Название, что входит, цена. Можно прислать прайс файлом.', 'type' => 'both' ),
		array( 'id' => 'contacts', 'title' => 'Контакты', 'hint' => 'Телефон, MAX, Telegram, WhatsApp, почта, адрес, часы работы.', 'type' => 'text' ),
		array( 'id' => 'legal', 'title' => 'Реквизиты', 'hint' => 'Для подвала и политики: ИП или ООО, ИНН, ОГРН(ИП). Если самозанятый: ФИО и ИНН.', 'type' => 'text' ),
		array( 'id' => 'pay', 'title' => 'Ссылка на оплату', 'hint' => 'Если нужна кнопка «Оплатить»: ссылка из вашего банка или сервиса (ЮKassa, Т-Банк, Продамус, СБП). Если не нужна, напишите «нет».', 'type' => 'text' ),
		array( 'id' => 'reviews', 'title' => 'Отзывы', 'hint' => 'Тексты отзывов или скриншоты, ссылки на Яндекс Карты, 2ГИС.', 'type' => 'both' ),
		array( 'id' => 'social', 'title' => 'Соцсети и ссылки', 'hint' => 'Канал в Telegram, ВКонтакте, YouTube, другие ссылки, которые показать на сайте.', 'type' => 'text' ),
	);
}

function zhdanov_in_items() {
	$it = get_option( 'zh_intake_items' );
	return is_array( $it ) && $it ? $it : zhdanov_in_default_items();
}

function zhdanov_in_data() {
	$d = get_option( 'zh_intake_data' );
	return is_array( $d ) ? $d : array();
}

function zhdanov_in_token() {
	$t = get_option( 'zh_intake_token' );
	if ( ! $t ) {
		$t = wp_generate_password( 20, false );
		update_option( 'zh_intake_token', $t, false );
	}
	return $t;
}

function zhdanov_in_link() {
	return add_query_arg( 'zh_intake', zhdanov_in_token(), home_url( '/' ) );
}

function zhdanov_in_done( $row ) {
	return is_array( $row ) && ( '' !== trim( (string) ( $row['text'] ?? '' ) ) || ! empty( $row['files'] ) );
}

function zhdanov_in_notify( $text ) {
	$token = trim( (string) zhdanov_core_opt( 'studio_tg_token' ) );
	$chat  = trim( (string) zhdanov_core_opt( 'studio_tg_chat' ) );
	if ( $token && $chat && function_exists( 'zhdanov_core_tg_send' ) ) {
		zhdanov_core_tg_send( $token, $chat, '📦 ' . wp_parse_url( home_url(), PHP_URL_HOST ) . ': ' . $text . "\n" . admin_url( 'admin.php?page=zhdanov-intake' ) );
	}
}

/** Сохранить загруженные файлы пункта в медиатеку. */
function zhdanov_in_upload( $field, $title ) {
	if ( empty( $_FILES[ $field ] ) || empty( $_FILES[ $field ]['name'] ) ) { // phpcs:ignore
		return array( array(), array() );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$f     = $_FILES[ $field ]; // phpcs:ignore
	$ids   = array();
	$errs  = array();
	$names = (array) $f['name'];
	$mimes = array(
		'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'heic' => 'image/heic', 'avif' => 'image/avif',
		'pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'txt' => 'text/plain',
	);
	foreach ( array_slice( array_keys( $names ), 0, 40 ) as $i ) {
		if ( empty( $names[ $i ] ) || UPLOAD_ERR_OK !== (int) ( (array) $f['error'] )[ $i ] ) {
			if ( ! empty( $names[ $i ] ) ) {
				$errs[] = $names[ $i ] . ': не загрузился (возможно, слишком большой)';
			}
			continue;
		}
		if ( (int) ( (array) $f['size'] )[ $i ] > 25 * MB_IN_BYTES ) {
			$errs[] = $names[ $i ] . ': больше 25 МБ';
			continue;
		}
		$one = array(
			'name'     => sanitize_file_name( $names[ $i ] ),
			'type'     => ( (array) $f['type'] )[ $i ],
			'tmp_name' => ( (array) $f['tmp_name'] )[ $i ],
			'error'    => 0,
			'size'     => ( (array) $f['size'] )[ $i ],
		);
		$up = wp_handle_upload( $one, array( 'test_form' => false, 'mimes' => $mimes ) );
		if ( empty( $up['file'] ) ) {
			$errs[] = $names[ $i ] . ': ' . ( $up['error'] ?? 'такой тип файла не принимаем' );
			continue;
		}
		$aid = wp_insert_attachment( array( 'post_title' => $title . ': ' . preg_replace( '/\.[^.]+$/', '', $one['name'] ), 'post_mime_type' => $up['type'], 'post_status' => 'inherit' ), $up['file'] );
		if ( $aid && ! is_wp_error( $aid ) ) {
			wp_update_attachment_metadata( $aid, wp_generate_attachment_metadata( $aid, $up['file'] ) );
			update_post_meta( $aid, '_zh_intake', 1 );
			$ids[] = (int) $aid;
		}
	}
	return array( $ids, $errs );
}

/* Страница клиента */
add_action( 'template_redirect', function () {
	if ( empty( $_GET['zh_intake'] ) ) { // phpcs:ignore
		return;
	}
	$t = sanitize_text_field( wp_unslash( $_GET['zh_intake'] ) ); // phpcs:ignore
	if ( ! get_option( 'zh_intake_on', 1 ) || ! hash_equals( zhdanov_in_token(), $t ) ) {
		status_header( 403 );
		nocache_headers();
		echo '<!doctype html><meta charset="utf-8"><meta name="robots" content="noindex"><title>Ссылка не работает</title><p style="font:16px sans-serif;padding:24px">Ссылка устарела. Попросите новую у студии.</p>';
		exit;
	}
	nocache_headers();
	$items = zhdanov_in_items();
	$data  = zhdanov_in_data();
	$msg   = '';
	if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && ! empty( $_POST['zh_item'] ) ) { // phpcs:ignore
		$id   = sanitize_key( wp_unslash( $_POST['zh_item'] ) ); // phpcs:ignore
		$item = null;
		foreach ( $items as $x ) {
			if ( $x['id'] === $id ) {
				$item = $x;
			}
		}
		if ( 'all' === $id ) {
			$n = 0;
			foreach ( $items as $x ) {
				$n += zhdanov_in_done( $data[ $x['id'] ] ?? null ) ? 1 : 0;
			}
			update_option( 'zh_intake_sent', time(), false );
			zhdanov_in_notify( 'клиент нажал «Всё отправил» (' . $n . ' из ' . count( $items ) . ').' );
			$msg = 'Спасибо! Студия получила материалы и скоро напишет вам.';
		} elseif ( $item ) {
			$row  = $data[ $id ] ?? array( 'text' => '', 'files' => array() );
			$text = isset( $_POST['zh_text'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['zh_text'] ) ) ) : null; // phpcs:ignore
			if ( null !== $text ) {
				$row['text'] = mb_substr( $text, 0, 20000 );
			}
			list( $ids, $errs ) = zhdanov_in_upload( 'zh_files', $item['title'] );
			$row['files'] = array_values( array_unique( array_merge( (array) ( $row['files'] ?? array() ), $ids ) ) );
			$row['time']  = time();
			$row['ok']    = false;
			$data[ $id ]  = $row;
			update_option( 'zh_intake_data', $data, false );
			$msg = 'Сохранено: «' . $item['title'] . '»' . ( $ids ? ', файлов: ' . count( $ids ) : '' ) . '.' . ( $errs ? ' Не приняты: ' . implode( '; ', $errs ) . '.' : '' );
			zhdanov_in_notify( '«' . $item['title'] . '»' . ( $ids ? ' + ' . count( $ids ) . ' файл(ов)' : '' ) . ( null !== $text && '' !== $text ? ': ' . mb_substr( $text, 0, 300 ) : '' ) );
			if ( function_exists( 'zhdanov_log' ) ) {
				zhdanov_log( 'Материалы от клиента: ' . $item['title'] );
			}
		}
		set_transient( 'zh_in_msg_' . md5( $t ), $msg, 120 );
		wp_safe_redirect( add_query_arg( 'zh_intake', $t, home_url( '/' ) ) . ( $id && 'all' !== $id ? '#i-' . $id : '' ) );
		exit;
	}
	$msg  = get_transient( 'zh_in_msg_' . md5( $t ) );
	delete_transient( 'zh_in_msg_' . md5( $t ) );
	$done = 0;
	foreach ( $items as $x ) {
		$done += zhdanov_in_done( $data[ $x['id'] ] ?? null ) ? 1 : 0;
	}
	$name = get_bloginfo( 'name' );
	$self = esc_url( add_query_arg( 'zh_intake', $t, home_url( '/' ) ) );
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'X-Robots-Tag: noindex, nofollow' );
	?>
<!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Материалы для сайта · <?php echo esc_html( $name ); ?></title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f4f2ee;color:#1d2327;font:16px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif}
.w{max-width:760px;margin:0 auto;padding:24px 16px 60px}h1{font-size:26px;line-height:1.2;margin:0 0 8px}
.lead{color:#50575e;margin:0 0 18px}.prog{background:#fff;border-radius:12px;padding:14px 16px;margin:0 0 18px;position:sticky;top:0;z-index:2;box-shadow:0 2px 10px rgba(0,0,0,.06)}
.bar{height:10px;border-radius:5px;background:#e6e2da;overflow:hidden;margin-top:8px}.bar i{display:block;height:100%;background:#2e7d32}
.msg{background:#e7f6ea;border:1px solid #9bd3a5;border-radius:10px;padding:12px 14px;margin:0 0 16px}
.it{background:#fff;border-radius:12px;padding:16px;margin:0 0 14px;border:2px solid transparent}.it.ok{border-color:#9bd3a5}
.it h2{font-size:18px;margin:0 0 4px;display:flex;gap:8px;align-items:center}.it .h{color:#50575e;font-size:14px;margin:0 0 10px}
.tick{display:inline-flex;width:22px;height:22px;border-radius:50%;background:#e6e2da;color:#fff;font-size:13px;align-items:center;justify-content:center;flex:none}.ok .tick{background:#2e7d32}
textarea{width:100%;min-height:96px;border:1px solid #c3c4c7;border-radius:8px;padding:10px;font:inherit;resize:vertical}
input[type=file]{display:block;margin:10px 0;max-width:100%}
.files{display:flex;flex-wrap:wrap;gap:8px;margin:10px 0}.files a{display:block;width:72px;height:72px;border-radius:8px;overflow:hidden;background:#eee;font-size:11px;color:#333;text-decoration:none;word-break:break-all;padding:4px}.files img{width:100%;height:100%;object-fit:cover;display:block;margin:-4px;width:calc(100% + 8px);height:calc(100% + 8px)}
button{appearance:none;border:0;border-radius:999px;background:#1d2327;color:#fff;font:600 15px/1 inherit;padding:12px 20px;cursor:pointer;min-height:44px}button:focus-visible,textarea:focus-visible{outline:3px solid #72aee6;outline-offset:2px}
.all{text-align:center;margin-top:24px}.all button{background:#2e7d32;font-size:17px;padding:16px 28px}.note{font-size:13px;color:#646970;margin-top:6px}
</style></head><body><main class="w">
<h1>Материалы для сайта</h1>
<p class="lead"><?php echo esc_html( $name ); ?>. Заполняйте в любом порядке и сколько угодно раз: каждый пункт сохраняется своей кнопкой, к ссылке можно вернуться позже.</p>
<div class="prog" aria-live="polite">Готово <b><?php echo (int) $done; ?></b> из <?php echo count( $items ); ?><div class="bar"><i style="width:<?php echo (int) round( 100 * $done / max( 1, count( $items ) ) ); ?>%"></i></div></div>
	<?php if ( $msg ) : ?><div class="msg" role="status"><?php echo esc_html( $msg ); ?></div><?php endif; ?>
	<?php
	foreach ( $items as $x ) :
		$row = $data[ $x['id'] ] ?? array();
		$ok  = zhdanov_in_done( $row );
		?>
<form class="it<?php echo $ok ? ' ok' : ''; ?>" id="i-<?php echo esc_attr( $x['id'] ); ?>" method="post" action="<?php echo $self; // phpcs:ignore ?>" enctype="multipart/form-data">
<h2><span class="tick" aria-hidden="true">✓</span><?php echo esc_html( $x['title'] ); ?><?php echo $ok ? '<span class="screen-reader-text" style="position:absolute;left:-9999px">(готово)</span>' : ''; ?></h2>
<p class="h"><?php echo esc_html( $x['hint'] ); ?></p>
<input type="hidden" name="zh_item" value="<?php echo esc_attr( $x['id'] ); ?>">
		<?php if ( 'files' !== $x['type'] ) : ?>
<textarea name="zh_text" aria-label="<?php echo esc_attr( $x['title'] ); ?>"><?php echo esc_textarea( $row['text'] ?? '' ); ?></textarea>
		<?php endif; ?>
		<?php if ( 'text' !== $x['type'] ) : ?>
<input type="file" name="zh_files[]" multiple aria-label="Файлы: <?php echo esc_attr( $x['title'] ); ?>" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.heic">
<p class="note">Фото, PDF, Word, Excel. До 25 МБ каждый.</p>
		<?php endif; ?>
		<?php if ( ! empty( $row['files'] ) ) : ?>
<div class="files"><?php foreach ( $row['files'] as $aid ) { $u = wp_get_attachment_url( $aid ); if ( ! $u ) { continue; } echo '<a href="' . esc_url( $u ) . '" target="_blank" title="' . esc_attr( basename( $u ) ) . '">' . ( wp_attachment_is_image( $aid ) ? '<img src="' . esc_url( wp_get_attachment_image_url( $aid, 'thumbnail' ) ) . '" alt="' . esc_attr( basename( $u ) ) . '" loading="lazy">' : esc_html( basename( $u ) ) ) . '</a>'; } ?></div>
		<?php endif; ?>
<button type="submit">Сохранить</button>
</form>
	<?php endforeach; ?>
<form class="all" method="post" action="<?php echo $self; // phpcs:ignore ?>"><input type="hidden" name="zh_item" value="all"><button type="submit">Всё отправил</button><p class="note">Студия получит уведомление. Добавить материалы можно и после.</p></form>
</main></body></html>
	<?php
	exit;
}, 0 );

/* Админка */
add_action( 'admin_menu', function () {
	add_submenu_page( 'zhdanov-core', 'Материалы от клиента', 'Материалы от клиента', 'edit_others_pages', 'zhdanov-intake', 'zhdanov_in_page' );
}, 26 );

add_action( 'admin_post_zhdanov_intake', function () {
	if ( ! current_user_can( 'edit_others_pages' ) ) {
		wp_die( 'Нет прав.', 403 );
	}
	check_admin_referer( 'zhdanov_intake' );
	$data = zhdanov_in_data();
	if ( isset( $_POST['zh_ok'] ) ) {
		$id = sanitize_key( wp_unslash( $_POST['zh_ok'] ) );
		if ( isset( $data[ $id ] ) ) {
			$data[ $id ]['ok'] = empty( $data[ $id ]['ok'] );
			update_option( 'zh_intake_data', $data, false );
		}
	}
	if ( current_user_can( 'manage_options' ) && isset( $_POST['zh_items'] ) ) {
		$items = array();
		$old   = array();
		foreach ( zhdanov_in_items() as $x ) {
			$old[ mb_strtolower( $x['title'] ) ] = $x['id'];
		}
		foreach ( preg_split( '/\R/u', (string) wp_unslash( $_POST['zh_items'] ) ) as $line ) {
			$p = array_map( 'trim', explode( '|', $line ) );
			if ( '' === $p[0] ) {
				continue;
			}
			$type = isset( $p[2] ) && in_array( $p[2], array( 'text', 'files', 'both' ), true ) ? $p[2] : 'both';
			$id   = $old[ mb_strtolower( $p[0] ) ] ?? sanitize_key( zhdanov_in_slug( $p[0] ) );
			$items[] = array( 'id' => $id ? $id : 'i' . count( $items ), 'title' => sanitize_text_field( $p[0] ), 'hint' => sanitize_text_field( $p[1] ?? '' ), 'type' => $type );
		}
		update_option( 'zh_intake_items', $items, false );
		update_option( 'zh_intake_on', empty( $_POST['zh_on'] ) ? 0 : 1, false );
		if ( ! empty( $_POST['zh_new'] ) ) {
			update_option( 'zh_intake_token', wp_generate_password( 20, false ), false );
		}
	}
	wp_safe_redirect( admin_url( 'admin.php?page=zhdanov-intake' ) );
	exit;
} );

/** id пункта из названия: «Логотип» → logotip. */
function zhdanov_in_slug( $s ) {
	$map = array( 'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya' );
	return substr( strtr( mb_strtolower( $s ), $map ), 0, 30 );
}

function zhdanov_in_page() {
	$items = zhdanov_in_items();
	$data  = zhdanov_in_data();
	$done  = 0;
	foreach ( $items as $x ) {
		$done += zhdanov_in_done( $data[ $x['id'] ] ?? null ) ? 1 : 0;
	}
	$sent = get_option( 'zh_intake_sent' );
	echo '<div class="wrap"><h1>Материалы от клиента</h1>';
	echo '<p>Отправьте клиенту ссылку: он откроет простую страницу со списком «что прислать» и загрузит логотип, фото, тексты, цены. Файлы сразу в медиатеке, вам уведомление в Telegram студии. Пароль клиенту не нужен.</p>';
	echo '<p><b>Ссылка для клиента:</b><br><input type="text" readonly class="large-text code" id="zh-in-link" value="' . esc_attr( zhdanov_in_link() ) . '" onclick="this.select()"></p>';
	echo '<p>Прислано: <b>' . (int) $done . ' из ' . count( $items ) . '</b>' . ( $sent ? ' · клиент нажал «Всё отправил» ' . esc_html( wp_date( 'd.m.Y H:i', $sent ) ) : '' ) . '</p>';
	echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th style="width:200px">Пункт</th><th>Ответ клиента</th><th style="width:120px"></th></tr></thead><tbody>';
	foreach ( $items as $x ) {
		$row = $data[ $x['id'] ] ?? array();
		echo '<tr><td><b>' . esc_html( $x['title'] ) . '</b>' . ( ! empty( $row['ok'] ) ? '<br><span style="color:#00a32a">✓ принято</span>' : '' ) . '</td><td>';
		if ( ! zhdanov_in_done( $row ) ) {
			echo '<span style="color:#999">пока нет</span>';
		} else {
			if ( '' !== trim( (string) ( $row['text'] ?? '' ) ) ) {
				echo '<div style="white-space:pre-wrap">' . esc_html( $row['text'] ) . '</div>';
			}
			if ( ! empty( $row['files'] ) ) {
				echo '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px">';
				foreach ( $row['files'] as $aid ) {
					$u = wp_get_attachment_url( $aid );
					if ( $u ) {
						echo '<a href="' . esc_url( admin_url( 'post.php?post=' . (int) $aid . '&action=edit' ) ) . '" title="' . esc_attr( basename( $u ) ) . '">' . ( wp_attachment_is_image( $aid ) ? wp_get_attachment_image( $aid, array( 64, 64 ) ) : esc_html( basename( $u ) ) ) . '</a>';
					}
				}
				echo '</div>';
			}
			echo '<small style="color:#666">' . esc_html( ! empty( $row['time'] ) ? wp_date( 'd.m.Y H:i', $row['time'] ) : '' ) . '</small>';
		}
		echo '</td><td>';
		if ( zhdanov_in_done( $row ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'zhdanov_intake' );
			echo '<input type="hidden" name="action" value="zhdanov_intake"><button class="button" name="zh_ok" value="' . esc_attr( $x['id'] ) . '">' . ( ! empty( $row['ok'] ) ? 'Снять «принято»' : 'Принято' ) . '</button></form>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
	if ( current_user_can( 'manage_options' ) ) {
		$lines = array();
		foreach ( $items as $x ) {
			$lines[] = $x['title'] . ' | ' . $x['hint'] . ' | ' . $x['type'];
		}
		echo '<h2 style="margin-top:28px">Что спрашиваем</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'zhdanov_intake' );
		echo '<input type="hidden" name="action" value="zhdanov_intake">';
		echo '<p>Каждая строка: <code>Название | Подсказка клиенту | text, files или both</code> (text: только текст, files: только файлы, both: и то и другое).</p>';
		echo '<textarea name="zh_items" class="large-text code" rows="' . ( count( $items ) + 2 ) . '">' . esc_textarea( implode( "\n", $lines ) ) . '</textarea>';
		echo '<p><label><input type="checkbox" name="zh_on" value="1"' . checked( (bool) get_option( 'zh_intake_on', 1 ), true, false ) . '> Анкета включена</label> &nbsp; <label><input type="checkbox" name="zh_new" value="1"> Сделать новую ссылку (старая перестанет работать)</label></p>';
		submit_button( 'Сохранить список' );
		echo '</form>';
	}
	echo '</div>';
}
