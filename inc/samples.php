<?php
/**
 * «Наполнить примерами»: для включённых разделов создаёт по 3 примера (с картинками-заглушками в цветах сайта),
 * чтобы показать клиенту живую админку. Все примеры помечены и удаляются одной кнопкой.
 * Отзывы не создаются никогда (только настоящие).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zhdanov_samples_data() {
	$d1 = wp_date( 'Y-m-d', time() + 10 * DAY_IN_SECONDS );
	$d2 = wp_date( 'Y-m-d', time() + 24 * DAY_IN_SECONDS );
	$d3 = wp_date( 'Y-m-d', time() + 40 * DAY_IN_SECONDS );
	$p  = 'Здесь будет ваш текст: что это, для кого, что входит, сколько длится. Два-три коротких абзаца.';
	return array(
		'zh_service' => array(
			array( 'Первичная консультация', array( '_zh_price' => '3 000 ₽', '_zh_duration' => '60 минут' ), 'Знакомство и план работы' ),
			array( 'Пакет из 5 встреч', array( '_zh_price' => '13 500 ₽', '_zh_duration' => '5 × 60 минут' ), 'Выгоднее на 10%' ),
			array( 'Онлайн-сессия', array( '_zh_price' => '2 500 ₽', '_zh_duration' => '50 минут' ), 'Из любого города' ),
		),
		'zh_event'   => array(
			array( 'Открытая встреча', array( '_zh_start' => $d1 . 'T19:00', '_zh_venue' => 'Главный зал', '_zh_price' => 'Вход свободный', '_zh_age' => '12+', '_zh_status' => 'scheduled' ), 'Знакомство, ответы на вопросы' ),
			array( 'Мастер-класс', array( '_zh_start' => $d2 . 'T12:00', '_zh_venue' => 'Студия', '_zh_price' => '1 500 ₽', '_zh_age' => '16+', '_zh_status' => 'scheduled' ), 'Практика в небольшой группе' ),
			array( 'Большой концерт', array( '_zh_start' => $d3 . 'T19:30', '_zh_venue' => 'Концертный зал', '_zh_price' => 'от 1 200 ₽', '_zh_age' => '6+', '_zh_status' => 'scheduled' ), 'Программа вечера' ),
		),
		'zh_class'   => array(
			array( 'Утреннее занятие', array( '_zh_day' => '1', '_zh_time' => '09:00', '_zh_duration' => '60 минут', '_zh_place' => 'Зал 1', '_zh_cap' => '8' ), '' ),
			array( 'Вечерняя группа', array( '_zh_day' => '3', '_zh_time' => '19:00', '_zh_duration' => '90 минут', '_zh_place' => 'Зал 2', '_zh_cap' => '10' ), '' ),
			array( 'Индивидуальный приём', array( '_zh_day' => '5', '_zh_time' => '15:00', '_zh_duration' => '60 минут', '_zh_cap' => '1' ), '' ),
		),
		'zh_course'  => array(
			array( 'Базовый курс', array( '_zh_start' => $d2, '_zh_duration' => '8 недель', '_zh_format' => 'Онлайн, записи + живые встречи', '_zh_tariffs' => "Самостоятельный | 9 900 ₽\nС поддержкой | 19 900 ₽ | | | Все уроки; Проверка заданий; Чат" ), 'Для начинающих' ),
		),
		'zh_person'  => array(
			array( 'Имя Фамилия', array( '_zh_role' => 'Ведущий специалист', '_zh_exp' => 'Опыт 10 лет', '_zh_langs' => 'русский, английский', '_zh_price' => 'от 3 000 ₽' ), '' ),
			array( 'Имя Фамилия', array( '_zh_role' => 'Специалист', '_zh_exp' => 'Опыт 5 лет', '_zh_langs' => 'русский' ), '' ),
		),
		'zh_dish'    => array(
			array( 'Салат с печёными овощами', array( '_zh_price' => '450 ₽', '_zh_weight' => '220 г', '_zh_tags' => 'вегетарианское' ), 'Описание блюда в одну строку' ),
			array( 'Паста с грибами', array( '_zh_price' => '590 ₽', '_zh_weight' => '300 г', '_zh_allergens' => 'глютен, молоко' ), 'Описание блюда в одну строку' ),
			array( 'Десерт дня', array( '_zh_price' => '350 ₽', '_zh_weight' => '150 г', '_zh_tags' => 'хит' ), 'Описание блюда в одну строку' ),
		),
		'zh_promo'   => array(
			array( 'Скидка на первое посещение', array( '_zh_until' => wp_date( 'Y-m-d', time() + 14 * DAY_IN_SECONDS ) . 'T23:59', '_zh_badge' => '-15%' ), 'Условия акции коротко' ),
		),
		'zh_product' => array(
			array( 'Товар 1', array( '_zh_price' => '2 900 ₽' ), 'Короткое описание' ),
			array( 'Товар 2', array( '_zh_price' => '4 500 ₽' ), 'Короткое описание' ),
			array( 'Товар 3', array( '_zh_price' => '1 200 ₽' ), 'Короткое описание' ),
		),
		'zh_tour'    => array(
			array( 'Тур выходного дня', array( '_zh_price' => '18 000 ₽', '_zh_duration' => '2 дня', '_zh_group' => 'до 12 человек', '_zh_dates' => wp_date( 'd.m.Y', time() + 20 * DAY_IN_SECONDS ), '_zh_days' => "Выезд: встреча, дорога, заселение\nПрограмма: прогулки и ужин", '_zh_incl' => 'проживание; завтраки; гид' ), 'Коротко о туре' ),
		),
		'zh_room'    => array(
			array( 'Зал «Светлый»', array( '_zh_price' => '2 000 ₽/час', '_zh_area' => '60 м²', '_zh_capacity' => 'до 10 человек', '_zh_features' => 'циклорама; окна; гримёрка' ), '' ),
		),
		'zh_book'    => array(
			array( 'Название книги', array( '_zh_author' => 'Автор', '_zh_year' => wp_date( 'Y' ) ), '' ),
		),
		'zh_job'     => array(
			array( 'Администратор', array( '_zh_salary' => 'от 50 000 ₽', '_zh_schedule' => '2/2', '_zh_exp' => 'без опыта, обучим', '_zh_employ' => 'FULL_TIME' ), '' ),
		),
		'zh_estate'  => array(
			array( '2-комнатная квартира', array( '_zh_price' => '9 500 000 ₽', '_zh_rooms' => '2', '_zh_area' => '58', '_zh_floor' => '5 из 12', '_zh_deal' => 'sale' ), '' ),
			array( 'Студия', array( '_zh_price' => '5 200 000 ₽', '_zh_rooms' => 'studio', '_zh_area' => '27', '_zh_floor' => '9 из 16', '_zh_deal' => 'sale' ), '' ),
		),
		'zh_branch'  => array(
			array( 'Филиал в центре', array( '_zh_address' => 'Город, улица, дом', '_zh_hours' => "Пн-Пт 10:00-20:00\nСб 11:00-18:00\nВс выходной" ), '' ),
		),
		'post'       => array(
			array( 'Первая новость', array(), 'Здесь будет короткий анонс новости.' ),
		),
	) + array( '_text' => $p );
}

/** Картинка-заглушка в цветах сайта (без платной генерации), WebP или JPEG. */
function zhdanov_samples_image( $title, $n ) {
	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		return 0;
	}
	$pal = array();
	$gs  = wp_get_global_settings( array( 'color', 'palette', 'theme' ) );
	foreach ( (array) $gs as $c ) {
		$pal[ $c['slug'] ] = $c['color'];
	}
	$a   = isset( $pal['accent'] ) ? $pal['accent'] : '#8a4b2f';
	$b   = isset( $pal['base-2'] ) ? $pal['base-2'] : '#f1ebe3';
	$rgb = function ( $h ) {
		$h = ltrim( $h, '#' );
		return array( hexdec( substr( $h, 0, 2 ) ), hexdec( substr( $h, 2, 2 ) ), hexdec( substr( $h, 4, 2 ) ) );
	};
	list( $r1, $g1, $b1 ) = $rgb( $a );
	list( $r2, $g2, $b2 ) = $rgb( $b );
	$w  = 1200;
	$h  = 800;
	$im = imagecreatetruecolor( $w, $h );
	$k0 = ( $n % 3 ) / 3;
	for ( $y = 0; $y < $h; $y++ ) {
		$t   = min( 1, max( 0, $y / $h * 0.8 + $k0 * 0.2 ) );
		$col = imagecolorallocate( $im, (int) ( $r1 + ( $r2 - $r1 ) * $t ), (int) ( $g1 + ( $g2 - $g1 ) * $t ), (int) ( $b1 + ( $b2 - $b1 ) * $t ) );
		imageline( $im, 0, $y, $w, $y, $col );
	}
	$soft = imagecolorallocatealpha( $im, 255, 255, 255, 100 );
	imagefilledellipse( $im, 300 + ( $n * 230 ) % 700, 260 + ( $n * 90 ) % 300, 520, 520, $soft );
	imagefilledellipse( $im, 950 - ( $n * 170 ) % 500, 620, 380, 380, $soft );
	$up   = wp_upload_dir();
	$name = 'primer-' . sanitize_title( $title ) . '-' . $n;
	if ( function_exists( 'imagewebp' ) ) {
		$file = trailingslashit( $up['path'] ) . $name . '.webp';
		imagewebp( $im, $file, 80 );
		$mime = 'image/webp';
	} else {
		$file = trailingslashit( $up['path'] ) . $name . '.jpg';
		imagejpeg( $im, $file, 82 );
		$mime = 'image/jpeg';
	}
	imagedestroy( $im );
	$att = wp_insert_attachment( array( 'post_mime_type' => $mime, 'post_title' => 'Пример: ' . $title, 'post_status' => 'inherit' ), $file );
	if ( ! $att || is_wp_error( $att ) ) {
		return 0;
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $file ) );
	update_post_meta( $att, '_wp_attachment_image_alt', 'Здесь будет фото: ' . $title );
	update_post_meta( $att, '_zh_sample', 1 );
	return $att;
}

function zhdanov_samples_apply() {
	$data = zhdanov_samples_data();
	$text = $data['_text'];
	unset( $data['_text'] );
	$made = 0;
	$n    = 0;
	foreach ( $data as $type => $items ) {
		$on = 'post' === $type ? true : ( 'zh_event' === $type ? zhdanov_core_events_on() : zhdanov_core_type_on( $type ) );
		if ( ! $on || ! post_type_exists( $type ) ) {
			continue;
		}
		if ( get_posts( array( 'post_type' => $type, 'numberposts' => 1, 'fields' => 'ids', 'post_status' => 'publish' ) ) ) {
			continue; // в разделе уже есть настоящие записи: не трогаем
		}
		foreach ( $items as $i => $it ) {
			$id = wp_insert_post( array( 'post_type' => $type, 'post_status' => 'publish', 'post_title' => $it[0], 'post_excerpt' => $it[2], 'menu_order' => $i, 'post_content' => '<!-- wp:paragraph --><p>' . esc_html( $text ) . '</p><!-- /wp:paragraph -->' ) );
			if ( ! $id || is_wp_error( $id ) ) {
				continue;
			}
			foreach ( $it[1] as $k => $v ) {
				update_post_meta( $id, $k, $v );
			}
			update_post_meta( $id, '_zh_sample', 1 );
			if ( post_type_supports( $type, 'thumbnail' ) && ! in_array( $type, array( 'zh_class', 'zh_branch', 'zh_dish', 'zh_job' ), true ) ) {
				$att = zhdanov_samples_image( $it[0], $n++ );
				if ( $att ) {
					set_post_thumbnail( $id, $att );
				}
			}
			$made++;
		}
	}
	return $made;
}

function zhdanov_samples_remove() {
	$n = 0;
	foreach ( get_posts( array( 'post_type' => 'any', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_zh_sample', 'meta_value' => 1 ) ) as $id ) {
		wp_delete_post( $id, true );
		$n++;
	}
	foreach ( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_zh_sample', 'meta_value' => 1 ) ) as $id ) {
		wp_delete_attachment( $id, true );
	}
	return $n;
}

add_action( 'admin_post_zhdanov_samples', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'zhdanov_samples' ) ) {
		wp_die( 'Нет доступа.' );
	}
	$do = isset( $_POST['zh_do'] ) ? sanitize_key( $_POST['zh_do'] ) : '';
	$n  = 'remove' === $do ? zhdanov_samples_remove() : zhdanov_samples_apply();
	wp_safe_redirect( admin_url( 'admin.php?page=zhdanov-preset&samples=' . ( 'remove' === $do ? 'del' : 'add' ) . '&n=' . (int) $n ) );
	exit;
} );

/** Блок кнопок для страницы «Пресет ниши». */
function zhdanov_samples_box() {
	$has = get_posts( array( 'post_type' => 'any', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_zh_sample', 'meta_value' => 1 ) );
	$h   = '<h2 style="margin-top:2em">Примеры для показа клиенту</h2>';
	if ( isset( $_GET['samples'] ) ) { // phpcs:ignore
		$h .= '<div class="notice notice-success"><p>' . ( 'del' === $_GET['samples'] ? 'Примеры удалены: ' : 'Создано примеров: ' ) . (int) ( $_GET['n'] ?? 0 ) . '.</p></div>'; // phpcs:ignore
	}
	$h .= '<p>Заполняет включённые разделы примерами (по 1-3 записи, картинки-заглушки в цветах сайта), чтобы на созвоне показать, как сайт выглядит и как его править. Разделы, где уже есть записи, не трогаются. Отзывы не создаются: только настоящие. Перед сдачей сайта примеры удаляются одной кнопкой.</p>';
	$h .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:8px"><input type="hidden" name="action" value="zhdanov_samples"><input type="hidden" name="zh_do" value="add">' . wp_nonce_field( 'zhdanov_samples', '_wpnonce', true, false ) . '<button class="button button-primary">Наполнить примерами</button></form>';
	if ( $has ) {
		$h .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block"><input type="hidden" name="action" value="zhdanov_samples"><input type="hidden" name="zh_do" value="remove">' . wp_nonce_field( 'zhdanov_samples', '_wpnonce', true, false ) . '<button class="button">Удалить все примеры</button></form>';
	}
	return $h;
}

/* Пометка в списках и «Здоровье сайта»: примеры нельзя забыть на живом сайте. */
add_filter( 'display_post_states', function ( $st, $p ) {
	if ( get_post_meta( $p->ID, '_zh_sample', true ) ) {
		$st['zh_sample'] = 'Пример';
	}
	return $st;
}, 10, 2 );
