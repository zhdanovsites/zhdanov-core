<?php
/**
 * Пресеты ниш: одна кнопка включает нужные разделы, дизайн, главную, страницы и меню.
 * Нишу выбирает Alex 1 при аудите. Потом сайт доводится под клиента и сохраняется «Рецептом сайта».
 * Существующие страницы не трогаем: создаём только недостающие.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zhdanov_presets() {
	// modules: какие разделы включить. style: палитра (styles/*.json, '' = светлая) + шрифты (styles/type-*.json, '' = по умолчанию).
	return array(
		'expert'     => array(
			'title'   => 'Коуч, психолог, эксперт',
			'modules' => array( 'mod_team' => 0, 'mod_menu' => 0, 'mod_branches' => 0, 'mod_promos' => 0, 'mod_courses' => 1, 'mod_schedule' => 1, 'mod_services' => 1, 'mod_reviews' => 1, 'mod_events' => 0, 'mod_gallery' => 0, 'mod_catalog' => 0 ),
			'entity'  => 'person',
			'palette' => 'warm',
			'type'    => '',
			'home'    => 'zhdanov/page-home-expert',
			'pages'   => array( 'uslugi' => 'Услуги и цены', 'novosti' => 'Статьи', 'kontakty' => 'Контакты' ),
			'menu'    => array( 'Обо мне' => '#o-nas', 'Услуги' => '/uslugi/', 'Программы' => '/kursy/', 'Отзывы' => '/otzyvy/', 'Статьи' => 'page:novosti', 'Контакты' => 'page:kontakty' ),
			'bar'     => array( 'Записаться', '#zayavka' ),
		),
		'salon'      => array(
			'title'   => 'Салон, клиника, студия услуг',
			'modules' => array( 'mod_team' => 1, 'mod_menu' => 0, 'mod_branches' => 0, 'mod_promos' => 1, 'mod_courses' => 0, 'mod_schedule' => 1, 'mod_services' => 1, 'mod_reviews' => 1, 'mod_events' => 0, 'mod_gallery' => 1, 'mod_catalog' => 0 ),
			'entity'  => 'org',
			'palette' => '',
			'type'    => '',
			'home'    => 'zhdanov/page-home-services',
			'pages'   => array( 'novosti' => 'Новости', 'kontakty' => 'Контакты' ),
			'menu'    => array( 'Услуги и цены' => '/uslugi/', 'Специалисты' => '/specialisty/', 'Расписание' => '/raspisanie/', 'Отзывы' => '/otzyvy/', 'Фото' => '/albomy/', 'Новости' => 'page:novosti', 'Контакты' => 'page:kontakty' ),
			'bar'     => array( 'Записаться', '#zayavka' ),
		),
		'school'     => array(
			'extra'   => array( 'label_zh_person' => 'Преподаватели' ),
			'title'   => 'Школа, курсы, студия обучения',
			'modules' => array( 'mod_team' => 1, 'mod_menu' => 0, 'mod_branches' => 0, 'mod_promos' => 1, 'mod_courses' => 1, 'mod_schedule' => 1, 'mod_services' => 1, 'mod_reviews' => 1, 'mod_events' => 1, 'mod_gallery' => 1, 'mod_catalog' => 0 ),
			'entity'  => 'org',
			'palette' => 'bright',
			'type'    => 'modern',
			'home'    => 'zhdanov/page-home-services',
			'pages'   => array( 'novosti' => 'Новости', 'kontakty' => 'Контакты' ),
			'menu'    => array( 'Курсы' => '/kursy/', 'Расписание' => '/raspisanie/', 'Преподаватели' => '/specialisty/', 'События' => '/afisha/', 'Отзывы' => '/otzyvy/', 'Новости' => 'page:novosti', 'Контакты' => 'page:kontakty' ),
			'bar'     => array( 'Записаться', '#zayavka' ),
		),
		'food'       => array(
			'title'   => 'Ресторан, кафе, кондитерская',
			'modules' => array( 'mod_team' => 0, 'mod_menu' => 1, 'mod_branches' => 0, 'mod_promos' => 1, 'mod_courses' => 0, 'mod_schedule' => 0, 'mod_services' => 0, 'mod_reviews' => 1, 'mod_events' => 1, 'mod_gallery' => 1, 'mod_catalog' => 0 ),
			'entity'  => 'org',
			'palette' => 'warm',
			'type'    => '',
			'home'    => 'zhdanov/page-home-food',
			'pages'   => array( 'novosti' => 'Новости', 'kontakty' => 'Контакты' ),
			'menu'    => array( 'Меню' => '/menu/', 'Акции' => '/akcii/', 'События' => '/afisha/', 'Фото' => '/albomy/', 'Отзывы' => '/otzyvy/', 'Контакты' => 'page:kontakty' ),
			'bar'     => array( 'Маршрут', '' ),
		),
		'artist'     => array(
			'title'   => 'Артист, музыкант, концертный зал',
			'modules' => array( 'mod_team' => 0, 'mod_menu' => 0, 'mod_branches' => 0, 'mod_promos' => 0, 'mod_courses' => 0, 'mod_schedule' => 0, 'mod_services' => 0, 'mod_reviews' => 0, 'mod_events' => 1, 'mod_gallery' => 1, 'mod_catalog' => 1 ),
			'entity'  => 'org',
			'palette' => 'dark',
			'type'    => 'modern',
			'home'    => 'zhdanov/page-home-events',
			'pages'   => array( 'novosti' => 'Новости', 'kontakty' => 'Контакты' ),
			'menu'    => array( 'Афиша' => '/afisha/', 'Новости' => 'page:novosti', 'Фото' => '/albomy/', 'Мерч' => '/katalog/', 'Контакты' => 'page:kontakty' ),
			'bar'     => array( 'Афиша', '/afisha/' ),
		),
		'shop'       => array(
			'title'   => 'Магазин, бренд, производитель',
			'modules' => array( 'mod_team' => 0, 'mod_menu' => 0, 'mod_branches' => 0, 'mod_promos' => 1, 'mod_courses' => 0, 'mod_schedule' => 0, 'mod_services' => 0, 'mod_reviews' => 1, 'mod_events' => 0, 'mod_gallery' => 0, 'mod_catalog' => 1 ),
			'entity'  => 'org',
			'palette' => 'bright',
			'type'    => '',
			'home'    => 'zhdanov/page-home-shop',
			'pages'   => array( 'novosti' => 'Новости', 'kontakty' => 'Контакты' ),
			'menu'    => array( 'Каталог' => '/katalog/', 'Отзывы' => '/otzyvy/', 'Новости' => 'page:novosti', 'Контакты' => 'page:kontakty' ),
			'bar'     => array( 'Каталог', '/katalog/' ),
		),
		'tours'      => array(
			'title'   => 'Туры, путешествия, ретриты',
			'modules' => array( 'mod_tours' => 1, 'mod_team' => 1, 'mod_reviews' => 1, 'mod_gallery' => 1, 'mod_promos' => 1, 'mod_events' => 0, 'mod_services' => 0, 'mod_catalog' => 0, 'mod_courses' => 0, 'mod_schedule' => 0, 'mod_menu' => 0, 'mod_branches' => 0, 'mod_rooms' => 0, 'mod_books' => 0 ),
			'extra'   => array( 'label_zh_person' => 'Гиды' ),
			'entity'  => 'org',
			'palette' => 'bright',
			'type'    => 'modern',
			'home'    => 'zhdanov/page-home-tours',
			'pages'   => array( 'novosti' => 'Блог', 'kontakty' => 'Контакты' ),
			'menu'    => array( 'Туры' => '/tury/', 'Гиды' => '/specialisty/', 'Фото' => '/albomy/', 'Отзывы' => '/otzyvy/', 'Блог' => 'page:novosti', 'Контакты' => 'page:kontakty' ),
			'bar'     => array( 'Подобрать тур', '#zayavka' ),
		),
		'studio'     => array(
			'title'   => 'Фотостудия, зал, коворкинг',
			'modules' => array( 'mod_rooms' => 1, 'mod_services' => 1, 'mod_reviews' => 1, 'mod_gallery' => 1, 'mod_promos' => 1, 'mod_events' => 0, 'mod_catalog' => 0, 'mod_courses' => 0, 'mod_schedule' => 0, 'mod_menu' => 0, 'mod_branches' => 0, 'mod_tours' => 0, 'mod_books' => 0, 'mod_team' => 0 ),
			'entity'  => 'org',
			'palette' => 'dark',
			'type'    => 'modern',
			'home'    => 'zhdanov/page-home-rooms',
			'pages'   => array( 'novosti' => 'Новости', 'kontakty' => 'Контакты' ),
			'menu'    => array( 'Залы' => '/zaly/', 'Услуги' => '/uslugi/', 'Работы' => '/albomy/', 'Отзывы' => '/otzyvy/', 'Контакты' => 'page:kontakty' ),
			'bar'     => array( 'Забронировать', '/zaly/' ),
		),
		'writer'     => array(
			'title'   => 'Писатель, издательство',
			'modules' => array( 'mod_books' => 1, 'mod_events' => 1, 'mod_reviews' => 1, 'mod_gallery' => 0, 'mod_services' => 0, 'mod_catalog' => 0, 'mod_courses' => 0, 'mod_schedule' => 0, 'mod_menu' => 0, 'mod_branches' => 0, 'mod_tours' => 0, 'mod_rooms' => 0, 'mod_team' => 0, 'mod_promos' => 0 ),
			'entity'  => 'person',
			'palette' => 'warm',
			'type'    => '',
			'home'    => 'zhdanov/page-home-books',
			'pages'   => array( 'novosti' => 'Дневник', 'kontakty' => 'Контакты' ),
			'menu'    => array( 'Книги' => '/knigi/', 'Встречи' => '/afisha/', 'Дневник' => 'page:novosti', 'Отзывы' => '/otzyvy/', 'Контакты' => 'page:kontakty' ),
			'bar'     => array( 'Книги', '/knigi/' ),
		),
		'estate'     => array(
			'title'   => 'Недвижимость, застройщик',
			'modules' => array( 'mod_estate' => 1, 'mod_docs' => 1, 'mod_reviews' => 1, 'mod_promos' => 1, 'mod_team' => 1, 'mod_events' => 0, 'mod_services' => 0, 'mod_catalog' => 0, 'mod_courses' => 0, 'mod_schedule' => 0, 'mod_menu' => 0, 'mod_branches' => 0, 'mod_tours' => 0, 'mod_rooms' => 0, 'mod_books' => 0, 'mod_gallery' => 0, 'mod_jobs' => 0 ),
			'extra'   => array( 'label_zh_person' => 'Агенты' ),
			'entity'  => 'org',
			'palette' => '',
			'type'    => 'modern',
			'home'    => 'zhdanov/page-home-estate',
			'pages'   => array( 'novosti' => 'Новости', 'kontakty' => 'Контакты' ),
			'menu'    => array( 'Объекты' => '/obekty/', 'Агенты' => '/specialisty/', 'Документы' => '/dokumenty/', 'Отзывы' => '/otzyvy/', 'Контакты' => 'page:kontakty' ),
			'bar'     => array( 'Подобрать', '#zayavka' ),
		),
		'ngo'        => array(
			'title'   => 'Фонд, храм, общественная организация',
			'modules' => array( 'mod_events' => 1, 'mod_docs' => 1, 'mod_schedule' => 1, 'mod_gallery' => 1, 'mod_reviews' => 0, 'mod_services' => 0, 'mod_catalog' => 0, 'mod_courses' => 0, 'mod_menu' => 0, 'mod_branches' => 0, 'mod_tours' => 0, 'mod_rooms' => 0, 'mod_books' => 0, 'mod_team' => 0, 'mod_promos' => 0, 'mod_estate' => 0, 'mod_jobs' => 0 ),
			'extra'   => array( 'label_zh_class' => 'Расписание богослужений', 'label_zh_doc' => 'Отчёты и документы' ),
			'entity'  => 'org',
			'palette' => 'warm',
			'type'    => '',
			'home'    => 'zhdanov/page-home-ngo',
			'pages'   => array( 'novosti' => 'Новости', 'kontakty' => 'Контакты' ),
			'menu'    => array( 'События' => '/afisha/', 'Расписание' => '/raspisanie/', 'Новости' => 'page:novosti', 'Фото' => '/albomy/', 'Отчёты' => '/dokumenty/', 'Контакты' => 'page:kontakty' ),
			'bar'     => array( 'Помочь', '#pomoch' ),
		),
		'media'      => array(
			'title'   => 'Блог, медиа, архив, писатель',
			'modules' => array( 'mod_team' => 0, 'mod_menu' => 0, 'mod_branches' => 0, 'mod_promos' => 0, 'mod_courses' => 0, 'mod_schedule' => 0, 'mod_services' => 0, 'mod_reviews' => 0, 'mod_events' => 1, 'mod_gallery' => 1, 'mod_catalog' => 0 ),
			'entity'  => 'person',
			'palette' => '',
			'type'    => '',
			'home'    => 'zhdanov/page-home-blog',
			'pages'   => array( 'novosti' => 'Статьи', 'kontakty' => 'Контакты' ),
			'menu'    => array( 'Статьи' => 'page:novosti', 'События' => '/afisha/', 'Фото' => '/albomy/', 'Контакты' => 'page:kontakty' ),
			'bar'     => array( 'Написать', '#zayavka' ),
		),
	);
}

/** Применить пресет. Возвращает текст отчёта. */
function zhdanov_preset_apply( $key ) {
	$all = zhdanov_presets();
	if ( empty( $all[ $key ] ) ) {
		return new WP_Error( 'zh_preset', 'Нет такого пресета.' );
	}
	$p    = $all[ $key ];
	$done = array();

	// 1. Разделы и настройки.
	$o                 = (array) get_option( 'zhdanov_core', array() );
	$o                 = array_merge( $o, $p['modules'] );
	$o['entity']       = $p['entity'];
	$o['bar_label']    = $p['bar'][0];
	$o['bar_url']      = $p['bar'][1];
	$o['preset']       = $key;
	if ( ! empty( $p['extra'] ) ) {
		$o = array_merge( $o, $p['extra'] );
	}
	update_option( 'zhdanov_core', $o );
	update_option( 'zhdanov_core_flush', 1 );
	$done[] = 'разделы включены';

	// 2. Дизайн: палитра + шрифты из вариантов темы (только если активна «Тема Жданов»).
	if ( 'zhdanov-theme' === get_template() ) {
		$dir  = get_template_directory() . '/styles/';
		$user = array( 'version' => 3, 'isGlobalStylesUserThemeJSON' => true );
		if ( $p['palette'] && file_exists( $dir . $p['palette'] . '.json' ) ) {
			$pal                                       = json_decode( file_get_contents( $dir . $p['palette'] . '.json' ), true ); // phpcs:ignore
			$user['settings']['color']['palette']['theme'] = $pal['settings']['color']['palette'];
		}
		if ( $p['type'] && file_exists( $dir . 'type-' . $p['type'] . '.json' ) ) {
			$ty             = json_decode( file_get_contents( $dir . 'type-' . $p['type'] . '.json' ), true ); // phpcs:ignore
			$user['styles'] = $ty['styles'];
		}
		$gs = zhdanov_core_styles_post_id();
		if ( $gs ) {
			wp_update_post( array( 'ID' => $gs, 'post_content' => wp_slash( wp_json_encode( $user, JSON_UNESCAPED_UNICODE ) ) ) );
			$done[] = 'дизайн применён';
		}
	}

	// 3. Страницы: только недостающие.
	$ids  = array();
	$home = get_page_by_path( 'glavnaya' );
	if ( ! $home ) {
		$hid = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Главная', 'post_name' => 'glavnaya', 'page_template' => 'page-landing', 'post_content' => '<!-- wp:pattern {"slug":"' . $p['home'] . '"} /-->' ) );
		$done[] = 'главная создана';
	} else {
		$hid = $home->ID;
	}
	foreach ( $p['pages'] as $slug => $title ) {
		$ex = get_page_by_path( $slug );
		if ( $ex ) {
			$ids[ $slug ] = $ex->ID;
			continue;
		}
		$content = '';
		if ( 'kontakty' === $slug ) {
			$content = '<!-- wp:pattern {"slug":"zhdanov/page-contacts"} /-->';
		} elseif ( 'uslugi' === $slug ) {
			continue; // Услуги: это раздел /uslugi/, страница не нужна.
		}
		$ids[ $slug ] = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => $content ) );
	}
	if ( ! get_page_by_path( 'politika' ) ) {
		$pp = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Политика конфиденциальности', 'post_name' => 'politika', 'post_content' => '<!-- wp:pattern {"slug":"zhdanov/page-privacy"} /-->' ) );
		$o  = (array) get_option( 'zhdanov_core', array() );
		$o['privacy_page'] = $pp;
		update_option( 'zhdanov_core', $o );
		update_option( 'wp_page_for_privacy_policy', $pp );
		$done[] = 'политика создана черновиком (заполните [скобки] и опубликуйте)';
	}
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $hid );
	if ( ! empty( $ids['novosti'] ) ) {
		update_option( 'page_for_posts', $ids['novosti'] );
	}
	$done[] = 'страницы готовы';

	// 4. Меню.
	foreach ( get_posts( array( 'post_type' => 'wp_navigation', 'post_status' => 'any', 'numberposts' => -1 ) ) as $n ) {
		wp_delete_post( $n->ID, true );
	}
	$nav = '';
	foreach ( $p['menu'] as $label => $target ) {
		if ( 0 === strpos( $target, 'page:' ) ) {
			$slug = substr( $target, 5 );
			$url  = ! empty( $ids[ $slug ] ) ? get_permalink( $ids[ $slug ] ) : home_url( '/' . $slug . '/' );
		} elseif ( 0 === strpos( $target, '#' ) ) {
			$url = home_url( '/' ) . $target;
		} else {
			$url = home_url( $target );
		}
		$nav .= '<!-- wp:navigation-link ' . wp_json_encode( array( 'label' => $label, 'url' => $url, 'kind' => 'custom', 'isTopLevelLink' => true ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ' /-->';
	}
	wp_insert_post( array( 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_title' => 'Главное меню', 'post_content' => wp_slash( $nav ) ) );
	$done[] = 'меню собрано';

	flush_rewrite_rules();
	return 'Пресет «' . $p['title'] . '»: ' . implode( ', ', $done ) . '.';
}

/* Страница в админке: Жданов → Пресет ниши. */
add_action( 'admin_menu', function () {
	add_submenu_page( 'zhdanov-core', 'Пресет ниши', 'Пресет ниши', 'manage_options', 'zhdanov-preset', 'zhdanov_preset_page' );
}, 15 );

function zhdanov_preset_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$msg = get_transient( 'zhdanov_preset_msg' );
	delete_transient( 'zhdanov_preset_msg' );
	$cur = zhdanov_core_opt( 'preset' );
	?>
	<div class="wrap">
		<h1>Пресет ниши</h1>
		<?php if ( $msg ) : ?>
			<div class="notice notice-<?php echo esc_attr( $msg[0] ); ?>"><p><?php echo esc_html( $msg[1] ); ?></p></div>
		<?php endif; ?>
		<p>Выберите, какой это сайт. Включатся нужные разделы, дизайн (палитра и шрифты), главная страница, «Контакты», черновик политики и меню.<br>
		Существующие страницы не изменятся. Потом всё можно поменять: цвета в «Стилях», разделы в «Жданов».</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="zhdanov_preset">
			<?php wp_nonce_field( 'zhdanov_preset' ); ?>
			<fieldset>
			<?php foreach ( zhdanov_presets() as $k => $p ) : ?>
				<p><label style="font-size:15px"><input type="radio" name="preset" value="<?php echo esc_attr( $k ); ?>" <?php checked( $cur ? $cur : 'expert', $k ); ?>> <?php echo esc_html( $p['title'] ); ?></label></p>
			<?php endforeach; ?>
			</fieldset>
			<?php submit_button( 'Применить пресет' ); ?>
		</form>
		<?php echo zhdanov_samples_box(); // phpcs:ignore ?>
	</div>
	<?php
}

add_action( 'admin_post_zhdanov_preset', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'zhdanov_preset' ) ) {
		wp_die( 'Нет доступа.' );
	}
	$key = isset( $_POST['preset'] ) ? sanitize_key( $_POST['preset'] ) : '';
	$res = zhdanov_preset_apply( $key );
	set_transient( 'zhdanov_preset_msg', is_wp_error( $res ) ? array( 'error', $res->get_error_message() ) : array( 'success', $res ), 120 );
	wp_safe_redirect( admin_url( 'admin.php?page=zhdanov-preset' ) );
	exit;
} );
