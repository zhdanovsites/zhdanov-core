<?php
/**
 * «Рецепт сайта»: весь сайт одним файлом.
 * Скачать рецепт: дизайн («Стили»), настройки «Жданов», страницы, записи, разделы, меню, картинки → один .zip.
 * Загрузить рецепт: на новом сайте одной кнопкой получается копия.
 * Не переносит: пароли, ключи капчи, заявки, пользователей.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZHDANOV_RECIPE_VERSION', 1 );

function zhdanov_recipe_types() {
	return array( 'page', 'post', 'zh_event', 'zh_service', 'zh_review', 'zh_album', 'zh_product', 'zh_class', 'zh_course', 'zh_person', 'zh_dish', 'zh_branch', 'zh_promo', 'zh_tour', 'zh_room', 'zh_book', 'zh_job', 'zh_doc', 'zh_estate' );
}

function zhdanov_recipe_taxonomies() {
	$out = array( 'category', 'post_tag', 'zh_event_cat' );
	if ( function_exists( 'zhdanov_core_types' ) ) {
		foreach ( zhdanov_core_types() as $t ) {
			if ( ! empty( $t['tax'][0] ) ) {
				$out[] = $t['tax'][0];
			}
		}
	}
	return array_values( array_unique( $out ) );
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'zhdanov-core', 'Рецепт сайта', 'Рецепт сайта', 'manage_options', 'zhdanov-recipe', 'zhdanov_recipe_page' );
}, 20 );

function zhdanov_recipe_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$msg = get_transient( 'zhdanov_recipe_msg' );
	delete_transient( 'zhdanov_recipe_msg' );
	?>
	<div class="wrap">
		<h1>Рецепт сайта</h1>
		<?php if ( $msg ) : ?>
			<div class="notice notice-<?php echo esc_attr( $msg[0] ); ?>"><p><?php echo esc_html( $msg[1] ); ?></p></div>
		<?php endif; ?>
		<p>Весь сайт одним файлом: дизайн, настройки «Жданов», страницы, записи, афиша, услуги, отзывы, альбомы, каталог, меню и картинки.<br>
		Не переносятся: пароли, ключи капчи, заявки, пользователи.</p>

		<h2>1. Скачать рецепт с этого сайта</h2>
		<p>Например, с демо в Playground, где Alex 1 собрал сайт клиента.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="zhdanov_recipe_export">
			<?php wp_nonce_field( 'zhdanov_recipe_export' ); ?>
			<?php submit_button( 'Скачать рецепт (.zip)', 'primary', 'submit', false ); ?>
		</form>

		<h2 style="margin-top:2.5em">2. Загрузить рецепт на этот сайт</h2>
		<p>Сначала включите тему «Тема Жданов». Страницы и записи с такими же адресами будут заменены, остальное останется.</p>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="zhdanov_recipe_import">
			<?php wp_nonce_field( 'zhdanov_recipe_import' ); ?>
			<p><input type="file" name="recipe" accept=".zip" required></p>
			<p><label><input type="checkbox" name="clean" value="1" checked> Удалить стандартные «Привет, мир!» и «Пример страницы»</label></p>
			<?php submit_button( 'Загрузить рецепт', 'primary', 'submit', false ); ?>
		</form>
		<?php echo zhdanov_backup_list_html(); // phpcs:ignore ?>
	</div>
	<?php
}

/* ---------------- Скачать ---------------- */

add_action( 'admin_post_zhdanov_recipe_export', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'zhdanov_recipe_export' ) ) {
		wp_die( 'Нет доступа.' );
	}
	$zip_path = zhdanov_recipe_build();
	if ( is_wp_error( $zip_path ) ) {
		wp_die( esc_html( $zip_path->get_error_message() ) );
	}
	$name = 'recept-' . sanitize_title( zhdanov_core_translit( get_bloginfo( 'name' ) ) ) . '-' . wp_date( 'Y-m-d' ) . '.zip';
	header( 'Content-Type: application/zip' );
	header( 'Content-Disposition: attachment; filename="' . $name . '"' );
	header( 'Content-Length: ' . filesize( $zip_path ) );
	readfile( $zip_path ); // phpcs:ignore
	@unlink( $zip_path ); // phpcs:ignore
	exit;
} );

/** Собрать рецепт в zip. Возвращает путь к временному файлу. */
function zhdanov_recipe_build() {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'zh_zip', 'На хостинге нет ZipArchive (PHP zip). Попросите поддержку хостинга включить его.' );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$opts = (array) get_option( 'zhdanov_core', array() );
	// Ключи, пароли и счётчик у каждого сайта свои: в рецепт не попадают никогда.
	foreach ( array( 'captcha_server', 'captcha_client', 'metrika', 'tg_token', 'tg_chat', 'smtp_pass', 'smtp_user', 'yk_shop', 'yk_secret', 'tb_terminal', 'tb_password' ) as $secret ) {
		unset( $opts[ $secret ] );
	}

	$data = array(
		'recipe'  => ZHDANOV_RECIPE_VERSION,
		'made'    => wp_date( 'c' ),
		'from'    => home_url( '/' ),
		'theme'   => get_stylesheet(),
		'site'    => array(
			'blogname'        => get_option( 'blogname' ),
			'blogdescription' => get_option( 'blogdescription' ),
			'timezone_string' => get_option( 'timezone_string' ),
			'date_format'     => get_option( 'date_format' ),
			'show_on_front'   => get_option( 'show_on_front' ),
			'page_on_front'   => zhdanov_recipe_ref( get_option( 'page_on_front' ) ),
			'page_for_posts'  => zhdanov_recipe_ref( get_option( 'page_for_posts' ) ),
			'privacy'         => zhdanov_recipe_ref( get_option( 'wp_page_for_privacy_policy' ) ),
		),
		'core'    => $opts,
		'core_privacy' => zhdanov_recipe_ref( isset( $opts['privacy_page'] ) ? $opts['privacy_page'] : 0 ),
		'styles'  => null,
		'menus'   => array(),
		'terms'   => array(),
		'posts'   => array(),
		'images'  => array(),
	);

	// Дизайн из «Стилей».
	$gs = zhdanov_core_styles_post_id();
	if ( $gs ) {
		$data['styles'] = json_decode( get_post_field( 'post_content', $gs ), true );
	}

	// Меню.
	foreach ( get_posts( array( 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'numberposts' => -1 ) ) as $n ) {
		$data['menus'][] = array( 'title' => $n->post_title, 'content' => $n->post_content );
	}

	// Рубрики и метки.
	foreach ( zhdanov_recipe_taxonomies() as $tax ) {
		if ( ! taxonomy_exists( $tax ) ) {
			continue;
		}
		foreach ( get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) ) as $t ) {
			$data['terms'][] = array( 'tax' => $tax, 'name' => $t->name, 'slug' => $t->slug, 'desc' => $t->description, 'parent' => $t->parent ? get_term( $t->parent )->slug : '' );
		}
	}

	// Записи всех разделов.
	$image_ids = array();
	$types     = array_filter( zhdanov_recipe_types(), 'post_type_exists' );
	foreach ( get_posts( array( 'post_type' => $types, 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $p ) {
		$meta = array();
		foreach ( get_post_meta( $p->ID ) as $k => $v ) {
			if ( 0 === strpos( $k, '_zh_' ) || '_wp_page_template' === $k ) {
				$meta[ $k ] = maybe_unserialize( $v[0] );
			}
		}
		$terms = array();
		foreach ( get_object_taxonomies( $p->post_type ) as $tax ) {
			$slugs = wp_get_object_terms( $p->ID, $tax, array( 'fields' => 'slugs' ) );
			if ( $slugs && ! is_wp_error( $slugs ) && in_array( $tax, zhdanov_recipe_taxonomies(), true ) ) {
				$terms[ $tax ] = $slugs;
			}
		}
		$thumb = (int) get_post_thumbnail_id( $p->ID );
		if ( $thumb ) {
			$image_ids[ $thumb ] = true;
		}
		// Картинки внутри текста.
		if ( preg_match_all( '/wp-image-(\d+)|"(?:id|mediaId)":(\d+)/', $p->post_content, $m ) ) {
			foreach ( array_merge( $m[1], $m[2] ) as $iid ) {
				if ( $iid && 'attachment' === get_post_type( (int) $iid ) ) {
					$image_ids[ (int) $iid ] = true;
				}
			}
		}
		$data['posts'][] = array(
			'id'      => $p->ID,
			'type'    => $p->post_type,
			'title'   => $p->post_title,
			'slug'    => $p->post_name,
			'status'  => $p->post_status,
			'date'    => $p->post_date,
			'content' => $p->post_content,
			'excerpt' => $p->post_excerpt,
			'order'   => $p->menu_order,
			'parent'  => $p->post_parent ? zhdanov_recipe_ref( $p->post_parent ) : null,
			'meta'    => $meta,
			'terms'   => $terms,
			'thumb'   => $thumb,
		);
	}

	$tmp = wp_tempnam( 'zh-recipe.zip' );
	$zip = new ZipArchive();
	if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
		return new WP_Error( 'zh_zip', 'Не удалось создать архив.' );
	}
	foreach ( array_keys( $image_ids ) as $iid ) {
		$file = get_attached_file( $iid );
		if ( ! $file || ! file_exists( $file ) ) {
			continue;
		}
		$name               = $iid . '-' . basename( $file );
		$data['images'][] = array(
			'id'   => $iid,
			'file' => $name,
			'url'  => wp_get_attachment_url( $iid ),
			'alt'  => get_post_meta( $iid, '_wp_attachment_image_alt', true ),
		);
		$zip->addFile( $file, 'images/' . $name );
	}
	$zip->addFromString( 'recipe.json', wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );
	$zip->addFromString( 'README.txt', "Рецепт сайта «" . get_bloginfo( 'name' ) . "» для «Темы Жданов».\nЗагрузить: Консоль → Жданов → Рецепт сайта → Загрузить рецепт.\n" );
	$zip->close();
	return $tmp;
}

/** Ссылка на запись: тип + адрес (ID на новом сайте будет другим). */
function zhdanov_recipe_ref( $id ) {
	$id = (int) $id;
	if ( ! $id || ! get_post( $id ) ) {
		return null;
	}
	return array( 'type' => get_post_type( $id ), 'slug' => get_post_field( 'post_name', $id ) );
}

/* ---------------- Загрузить ---------------- */

add_action( 'admin_post_zhdanov_recipe_import', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'zhdanov_recipe_import' ) ) {
		wp_die( 'Нет доступа.' );
	}
	$back = admin_url( 'admin.php?page=zhdanov-recipe' );
	if ( empty( $_FILES['recipe']['tmp_name'] ) ) { // phpcs:ignore
		set_transient( 'zhdanov_recipe_msg', array( 'error', 'Выберите файл рецепта (.zip).' ), 60 );
		wp_safe_redirect( $back );
		exit;
	}
	$res = zhdanov_recipe_apply( $_FILES['recipe']['tmp_name'], ! empty( $_POST['clean'] ) ); // phpcs:ignore
	if ( is_wp_error( $res ) ) {
		set_transient( 'zhdanov_recipe_msg', array( 'error', $res->get_error_message() ), 60 );
	} else {
		set_transient( 'zhdanov_recipe_msg', array( 'success', sprintf( 'Готово! Загружено: записей и страниц %d, картинок %d, меню %d. Откройте сайт и проверьте. Не забудьте вписать номер Метрики и ключи капчи в «Жданов».', $res['posts'], $res['images'], $res['menus'] ) ), 120 );
	}
	wp_safe_redirect( $back );
	exit;
} );

/**
 * Применить рецепт из zip. Используется и кнопкой, и скриптами (Claude, Playground).
 *
 * @return array|WP_Error
 */
function zhdanov_recipe_apply( $zip_file, $clean = true ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'zh_zip', 'На хостинге нет ZipArchive (PHP zip). Попросите поддержку хостинга включить его.' );
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_file ) ) {
		return new WP_Error( 'zh_zip', 'Это не похоже на рецепт: файл не открывается как .zip.' );
	}
	$json = $zip->getFromName( 'recipe.json' );
	$data = $json ? json_decode( $json, true ) : null;
	if ( ! is_array( $data ) || empty( $data['recipe'] ) ) {
		$zip->close();
		return new WP_Error( 'zh_recipe', 'В архиве нет recipe.json: это не рецепт «Темы Жданов».' );
	}
	@set_time_limit( 300 ); // phpcs:ignore
	$count = array( 'posts' => 0, 'images' => 0, 'menus' => 0 );

	if ( $clean ) {
		foreach ( array( array( 'post', 'hello-world' ), array( 'page', 'sample-page' ), array( 'page', 'primer-stranitsy' ) ) as $d ) {
			$old = get_page_by_path( $d[1], OBJECT, $d[0] );
			if ( $old ) {
				wp_delete_post( $old->ID, true );
			}
		}
	}

	// 1. Настройки «Жданов» (ключи и Метрику этого сайта сохраняем).
	$cur  = (array) get_option( 'zhdanov_core', array() );
	$keep = array_intersect_key( $cur, array_flip( array( 'captcha_server', 'captcha_client', 'metrika', 'tg_token', 'tg_chat', 'smtp', 'smtp_pass', 'smtp_user', 'smtp_host', 'pay_provider', 'yk_shop', 'yk_secret', 'tb_terminal', 'tb_password' ) ) );
	update_option( 'zhdanov_core', array_merge( (array) $data['core'], $keep ) );
	// Разделы могли быть выключены: регистрируем типы сейчас, чтобы записи легли правильно.
	foreach ( zhdanov_core_types() as $type => $t ) {
		if ( ! post_type_exists( $type ) && ! empty( $data['core'][ $t['module'] ] ) ) {
			register_post_type( $type, array( 'public' => true ) );
			if ( $t['tax'] ) {
				register_taxonomy( $t['tax'][0], $type, array( 'hierarchical' => true ) );
			}
		}
	}
	if ( ! post_type_exists( 'zh_event' ) && ! empty( $data['core']['mod_events'] ) ) {
		register_post_type( 'zh_event', array( 'public' => true ) );
		register_taxonomy( 'zh_event_cat', 'zh_event', array( 'hierarchical' => true ) );
	}

	// 2. Название, описание, часовой пояс.
	foreach ( array( 'blogname', 'blogdescription', 'timezone_string', 'date_format' ) as $k ) {
		if ( ! empty( $data['site'][ $k ] ) ) {
			update_option( $k, $data['site'][ $k ] );
		}
	}

	// 3. Картинки → медиатека.
	$img_map = array(); // старый ID => новый ID.
	$url_map = array(); // старый URL без расширения => новый.
	foreach ( (array) $data['images'] as $im ) {
		$bytes = $zip->getFromName( 'images/' . $im['file'] );
		if ( false === $bytes ) {
			continue;
		}
		$orig = preg_replace( '/^\d+-/', '', $im['file'] );
		$tmp  = wp_tempnam( $orig );
		file_put_contents( $tmp, $bytes ); // phpcs:ignore
		$new = media_handle_sideload( array( 'name' => sanitize_file_name( $orig ), 'tmp_name' => $tmp ), 0 );
		if ( is_wp_error( $new ) ) {
			continue;
		}
		if ( ! empty( $im['alt'] ) ) {
			update_post_meta( $new, '_wp_attachment_image_alt', $im['alt'] );
		}
		$img_map[ (int) $im['id'] ] = $new;
		$old_base                   = preg_replace( '/\.[a-z0-9]+$/i', '', $im['url'] );
		$new_base                   = preg_replace( '/\.[a-z0-9]+$/i', '', wp_get_attachment_url( $new ) );
		$url_map[ $old_base ]       = $new_base;
		++$count['images'];
	}
	$zip->close();

	$fix = function ( $content ) use ( $img_map, $url_map ) {
		// Длинные адреса первыми, чтобы «hero» не заменил кусок «hero-2».
		uksort( $url_map, function ( $a, $b ) {
			return strlen( $b ) - strlen( $a );
		} );
		$content = strtr( $content, $url_map );
		return preg_replace_callback(
			'/(wp-image-|"id":|"mediaId":)(\d+)/',
			function ( $m ) use ( $img_map ) {
				return isset( $img_map[ (int) $m[2] ] ) ? $m[1] . $img_map[ (int) $m[2] ] : $m[0];
			},
			$content
		);
	};

	// 4. Рубрики.
	foreach ( (array) $data['terms'] as $t ) {
		if ( taxonomy_exists( $t['tax'] ) && ! term_exists( $t['slug'], $t['tax'] ) ) {
			wp_insert_term( $t['name'], $t['tax'], array( 'slug' => $t['slug'], 'description' => $t['desc'] ) );
		}
	}

	// 5. Записи и страницы (одинаковый тип + адрес → заменяем).
	$post_map = array();
	foreach ( (array) $data['posts'] as $p ) {
		$existing = get_page_by_path( $p['slug'], OBJECT, $p['type'] );
		$arr      = array(
			'post_type'    => $p['type'],
			'post_title'   => $p['title'],
			'post_name'    => $p['slug'],
			'post_status'  => $p['status'],
			'post_date'    => $p['date'],
			'post_content' => wp_slash( $fix( $p['content'] ) ),
			'post_excerpt' => wp_slash( $p['excerpt'] ),
			'menu_order'   => (int) $p['order'],
		);
		if ( $existing ) {
			$arr['ID'] = $existing->ID;
			$id        = wp_update_post( $arr );
		} else {
			$id = wp_insert_post( $arr );
		}
		if ( ! $id || is_wp_error( $id ) ) {
			continue;
		}
		$post_map[ (int) $p['id'] ] = $id;
		foreach ( (array) $p['meta'] as $k => $v ) {
			update_post_meta( $id, $k, wp_slash( $v ) );
		}
		foreach ( (array) $p['terms'] as $tax => $slugs ) {
			if ( taxonomy_exists( $tax ) ) {
				wp_set_object_terms( $id, $slugs, $tax );
			}
		}
		if ( $p['thumb'] && isset( $img_map[ (int) $p['thumb'] ] ) ) {
			set_post_thumbnail( $id, $img_map[ (int) $p['thumb'] ] );
		}
		++$count['posts'];
	}
	// Родители страниц.
	foreach ( (array) $data['posts'] as $p ) {
		if ( ! empty( $p['parent'] ) && isset( $post_map[ (int) $p['id'] ] ) ) {
			$parent = get_page_by_path( $p['parent']['slug'], OBJECT, $p['parent']['type'] );
			if ( $parent ) {
				wp_update_post( array( 'ID' => $post_map[ (int) $p['id'] ], 'post_parent' => $parent->ID ) );
			}
		}
	}

	// 6. Главная, лента записей, политика.
	$find = function ( $ref ) {
		if ( empty( $ref['slug'] ) ) {
			return 0;
		}
		$p = get_page_by_path( $ref['slug'], OBJECT, $ref['type'] );
		return $p ? $p->ID : 0;
	};
	if ( 'page' === $data['site']['show_on_front'] ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $find( $data['site']['page_on_front'] ) );
		update_option( 'page_for_posts', $find( $data['site']['page_for_posts'] ) );
	}
	$priv = $find( $data['site']['privacy'] ? $data['site']['privacy'] : $data['core_privacy'] );
	if ( $priv ) {
		update_option( 'wp_page_for_privacy_policy', $priv );
		$o                 = (array) get_option( 'zhdanov_core', array() );
		$o['privacy_page'] = $priv;
		update_option( 'zhdanov_core', $o );
	}

	// 7. Меню: заменяем имеющиеся на меню из рецепта.
	if ( ! empty( $data['menus'] ) ) {
		foreach ( get_posts( array( 'post_type' => 'wp_navigation', 'post_status' => 'any', 'numberposts' => -1 ) ) as $n ) {
			wp_delete_post( $n->ID, true );
		}
		$old_home = untrailingslashit( (string) $data['from'] );
		foreach ( $data['menus'] as $m ) {
			$content = str_replace( $old_home, untrailingslashit( home_url() ), $m['content'] );
			wp_insert_post( array( 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_title' => $m['title'], 'post_content' => wp_slash( $content ) ) );
			++$count['menus'];
		}
	}

	// 8. Дизайн («Стили»): от имени администратора, иначе WordPress вырежет.
	if ( ! empty( $data['styles'] ) && get_stylesheet() === $data['theme'] ) {
		$gs = zhdanov_core_styles_post_id();
		if ( $gs ) {
			wp_update_post( array( 'ID' => $gs, 'post_content' => wp_slash( wp_json_encode( $data['styles'], JSON_UNESCAPED_UNICODE ) ) ) );
		}
	}

	update_option( 'zhdanov_core_flush', 1 );
	flush_rewrite_rules();
	return $count;
}


/* ---------- Автокопия рецепта раз в неделю ---------- */
function zhdanov_backup_dir() {
	$up  = wp_upload_dir();
	$key = get_option( 'zhdanov_backup_key' );
	if ( ! $key ) {
		$key = wp_generate_password( 20, false );
		update_option( 'zhdanov_backup_key', $key, false );
	}
	$dir = trailingslashit( $up['basedir'] ) . 'zhdanov-kopii-' . $key;
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
		file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore
		file_put_contents( $dir . '/index.php', '<?php // Silence.' ); // phpcs:ignore
	}
	return $dir;
}

function zhdanov_backup_make() {
	$tmp = zhdanov_recipe_build();
	if ( is_wp_error( $tmp ) || ! file_exists( $tmp ) ) {
		return $tmp;
	}
	$dir  = zhdanov_backup_dir();
	$file = $dir . '/recept-' . wp_date( 'Y-m-d' ) . '.zip';
	copy( $tmp, $file );
	@unlink( $tmp ); // phpcs:ignore
	$all = glob( $dir . '/recept-*.zip' );
	sort( $all );
	while ( count( $all ) > 4 ) { // храним 4 последние недели
		@unlink( array_shift( $all ) ); // phpcs:ignore
	}
	// Копия в Telegram: владельцу (бот заявок) и студии (бот отчётов). Заявок и паролей в копии нет.
	$o       = (array) get_option( 'zhdanov_core', array() );
	$targets = array();
	if ( ! empty( $o['tg_token'] ) && ! empty( $o['tg_chat'] ) && ( ! isset( $o['backup_tg'] ) || $o['backup_tg'] ) ) {
		$targets['владельцу'] = array( $o['tg_token'], $o['tg_chat'] );
	}
	if ( ! empty( $o['studio_tg_token'] ) && ! empty( $o['studio_tg_chat'] ) && ( ! isset( $o['backup_studio'] ) || $o['backup_studio'] ) ) {
		$targets['студии'] = array( $o['studio_tg_token'], $o['studio_tg_chat'] );
	}
	$status = array();
	foreach ( $targets as $who => $t ) {
		if ( isset( $targets['владельцу'] ) && 'студии' === $who && $t === $targets['владельцу'] ) {
			continue;
		}
		if ( filesize( $file ) >= 45 * MB_IN_BYTES ) {
			$status[] = $who . ': файл ' . size_format( filesize( $file ) ) . ', Telegram принимает до 45 МБ, копия только на сервере';
			continue;
		}
		$bnd  = wp_generate_password( 24, false );
		$body = "--$bnd\r\nContent-Disposition: form-data; name=\"chat_id\"\r\n\r\n" . $t[1] . "\r\n";
		$body .= "--$bnd\r\nContent-Disposition: form-data; name=\"caption\"\r\n\r\n💾 Копия сайта " . get_bloginfo( 'name' ) . ' (' . wp_parse_url( home_url(), PHP_URL_HOST ) . ') от ' . wp_date( 'j F Y' ) . ". Восстановить: Жданов → Рецепт сайта → Загрузить.\r\n";
		$body .= "--$bnd\r\nContent-Disposition: form-data; name=\"document\"; filename=\"" . basename( $file ) . "\"\r\nContent-Type: application/zip\r\n\r\n" . file_get_contents( $file ) . "\r\n--$bnd--\r\n"; // phpcs:ignore
		$r    = wp_remote_post( 'https://api.telegram.org/bot' . rawurlencode( $t[0] ) . '/sendDocument', array( 'timeout' => 60, 'headers' => array( 'Content-Type' => 'multipart/form-data; boundary=' . $bnd ), 'body' => $body ) );
		$j    = is_wp_error( $r ) ? null : json_decode( wp_remote_retrieve_body( $r ), true );
		$status[] = $who . ': ' . ( ! empty( $j['ok'] ) ? 'отправлена в Telegram' : 'не ушла (' . ( is_wp_error( $r ) ? $r->get_error_message() : ( $j['description'] ?? 'ответ ' . wp_remote_retrieve_response_code( $r ) ) ) . ')' );
	}
	update_option( 'zhdanov_backup_status', array( 'time' => time(), 'size' => size_format( filesize( $file ) ), 'tg' => $status ), false );
	if ( function_exists( 'zhdanov_log' ) ) {
		zhdanov_log( 'Копия сайта: ' . size_format( filesize( $file ) ) . ( $status ? '; ' . implode( '; ', $status ) : '' ) );
	}
	update_option( 'zhdanov_backup_last', wp_date( 'j F Y, H:i' ), false );
	return $file;
}
add_action( 'zhdanov_weekly', 'zhdanov_backup_make', 5 );

/* Скачать копию (только администратор). */
add_action( 'admin_post_zhdanov_backup_get', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'zhdanov_backup_get' ) ) {
		wp_die( 'Нет доступа.' );
	}
	$f = isset( $_GET['f'] ) ? basename( sanitize_file_name( wp_unslash( $_GET['f'] ) ) ) : ''; // phpcs:ignore
	$p = zhdanov_backup_dir() . '/' . $f;
	if ( ! preg_match( '/^recept-[\d-]+\.zip$/', $f ) || ! file_exists( $p ) ) {
		wp_die( 'Нет такой копии.' );
	}
	header( 'Content-Type: application/zip' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_title( get_bloginfo( 'name' ) ) . '-' . $f . '"' );
	header( 'Content-Length: ' . filesize( $p ) );
	readfile( $p ); // phpcs:ignore
	exit;
} );
add_action( 'admin_post_zhdanov_backup_now', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'zhdanov_backup_now' ) ) {
		wp_die( 'Нет доступа.' );
	}
	$r = zhdanov_backup_make();
	wp_safe_redirect( admin_url( 'admin.php?page=zhdanov-recipe&backup=' . ( is_wp_error( $r ) ? 'fail' : 'ok' ) ) );
	exit;
} );

function zhdanov_backup_list_html() {
	$files = glob( zhdanov_backup_dir() . '/recept-*.zip' );
	rsort( $files );
	$h = '<h2 style="margin-top:2em">Автокопии (каждый понедельник, хранятся 4 последние)</h2>';
	if ( isset( $_GET['backup'] ) ) { // phpcs:ignore
		$h .= 'ok' === $_GET['backup'] ? '<div class="notice notice-success"><p>Копия сделана.</p></div>' : '<div class="notice notice-error"><p>Не получилось сделать копию.</p></div>'; // phpcs:ignore
	}
	$h .= '<p>Копия = рецепт сайта: настройки, стили, страницы, записи, картинки, меню (без паролей, ключей и заявок клиентов). Копия приходит в Telegram владельцу (если включены заявки в Telegram) и студии (бот отчётов).</p>';
	$bs = get_option( 'zhdanov_backup_status' );
	if ( is_array( $bs ) ) {
		$h .= '<p>Последняя: ' . esc_html( wp_date( 'd.m.Y H:i', $bs['time'] ) . ', ' . $bs['size'] . ( $bs['tg'] ? ' · ' . implode( ' · ', $bs['tg'] ) : ' · в Telegram не отправлялась (не настроен)' ) ) . '</p>';
	}
	$h .= '<ul>';
	foreach ( $files as $f ) {
		$h .= '<li><a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=zhdanov_backup_get&f=' . rawurlencode( basename( $f ) ) ), 'zhdanov_backup_get' ) ) . '">' . esc_html( basename( $f ) ) . '</a> · ' . esc_html( size_format( filesize( $f ) ) ) . '</li>';
	}
	$h .= $files ? '' : '<li>Пока нет: первая копия будет в понедельник.</li>';
	$h .= '</ul><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="zhdanov_backup_now">' . wp_nonce_field( 'zhdanov_backup_now', '_wpnonce', true, false ) . '<button class="button">Сделать копию сейчас</button></form>';
	return $h;
}
