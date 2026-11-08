<?php
/**
 * Перенос концепта «до пикселя».
 *
 * Страница концепта = обычная страница WordPress, в ней блоки «Раздел концепта» (zhdanov/csec).
 * Блок хранит исходную разметку раздела (шаблон) и значения полей (тексты, фото, ссылки, карточки),
 * на сайте выводит ровно ту же разметку. Стили, шрифты, скрипты и фото концепта лежат
 * в uploads/zh-concept/<сайт>/ с той же структурой папок, что на Cloudflare.
 * На страницах концепта стили темы и WordPress отключены: виден только дизайн концепта.
 *
 * @package zhdanov-core
 */

defined( 'ABSPATH' ) || exit;

/* ---------- Сборка разметки ---------- */

/** Заполнить шаблон значениями. Маркеры описаны в assets/concept/convert.js. */
function zhdanov_concept_fill( $tpl, $v, $tpls ) {
	return preg_replace_callback(
		'/⟪([tair]\d+)(?::(\w+))?⟫/u',
		function ( $m ) use ( $v, $tpls ) {
			$k = $m[1];
			$x = isset( $v[ $k ] ) ? $v[ $k ] : null;
			switch ( $k[0] ) {
				case 'r':
					$out = array();
					foreach ( (array) $x as $it ) {
						if ( ! is_array( $it ) || ! empty( $it['_hide'] ) ) {
							continue;
						}
						$tk    = $k . '_' . ( isset( $it['_t'] ) ? $it['_t'] : '0' );
						$out[] = isset( $tpls[ $tk ] ) ? zhdanov_concept_fill( $tpls[ $tk ], $it, $tpls ) : '';
					}
					return implode( isset( $tpls[ $k . '_g' ] ) ? $tpls[ $k . '_g' ] : '', $out );
				case 'i':
					$sub = isset( $m[2] ) ? $m[2] : 'src';
					return esc_attr( is_array( $x ) && isset( $x[ $sub ] ) ? $x[ $sub ] : '' );
				case 'a':
					return esc_attr( is_scalar( $x ) ? (string) $x : '' );
				default:
					return is_scalar( $x ) ? (string) $x : '';
			}
		},
		(string) $tpl
	);
}

/** ⟦A⟧ → папка файлов концепта, ⟦H⟧ → адрес сайта. */
function zhdanov_concept_tokens( $html, $slug = null ) {
	if ( null === $slug ) {
		$slug = zhdanov_concept_current();
	}
	return strtr(
		$html,
		array(
			'⟦A⟧' => zhdanov_concept_base_url( $slug ),
			'⟦H⟧' => untrailingslashit( home_url() ),
		)
	);
}

function zhdanov_concept_base_dir( $slug ) {
	$u = wp_upload_dir( null, false );
	return trailingslashit( $u['basedir'] ) . 'zh-concept/' . sanitize_file_name( $slug );
}
function zhdanov_concept_base_url( $slug ) {
	$u = wp_upload_dir( null, false );
	return set_url_scheme( trailingslashit( $u['baseurl'] ) . 'zh-concept/' . sanitize_file_name( $slug ) );
}

/** Сайт концепта, к которому относится текущая страница (или первый перенесённый). */
function zhdanov_concept_current() {
	static $cur = null;
	if ( null !== $cur ) {
		return $cur;
	}
	$id = get_queried_object_id();
	if ( ! $id && is_admin() && isset( $_GET['post'] ) ) { // phpcs:ignore
		$id = (int) $_GET['post']; // phpcs:ignore
	}
	$s = $id ? get_post_meta( $id, '_zh_concept', true ) : '';
	if ( ! $s ) {
		$all = get_option( 'zh_concepts', array() );
		$s   = $all ? (string) array_key_first( $all ) : '';
	}
	$cur = $s;
	return $cur;
}

function zhdanov_concept_is_page() {
	return is_singular() && get_post_meta( get_queried_object_id(), '_zh_concept', true );
}

/* ---------- Блоки ---------- */

add_action( 'init', function () {
	$any = array( 'type' => 'object', 'default' => array() );
	register_block_type(
		'zhdanov/csec',
		array(
			'api_version'     => 3,
			'title'           => 'Раздел концепта',
			'category'        => 'design',
			'attributes'      => array(
				'tpl'      => array( 'type' => 'string', 'default' => '' ),
				'tpls'     => $any,
				'v'        => $any,
				'm'        => $any,
				'role'     => array( 'type' => 'string', 'default' => '' ),
				'hide'     => array( 'type' => 'boolean', 'default' => false ),
				'site'     => array( 'type' => 'string', 'default' => '' ),
				'metadata' => $any,
			),
			'supports'        => array( 'html' => false, 'className' => false, 'customClassName' => false, 'reusable' => true ),
			'render_callback' => function ( $a ) {
				if ( ! empty( $a['hide'] ) || empty( $a['tpl'] ) ) {
					return '';
				}
				return zhdanov_concept_tokens( zhdanov_concept_fill( $a['tpl'], (array) $a['v'], (array) $a['tpls'] ), $a['site'] ? $a['site'] : null );
			},
			'editor_script'   => 'zhdanov-concept-editor',
		)
	);
	register_block_type(
		'zhdanov/cwrap',
		array(
			'api_version'     => 3,
			'title'           => 'Обёртка концепта',
			'category'        => 'design',
			'attributes'      => array(
				'open'     => array( 'type' => 'string', 'default' => '' ),
				'close'    => array( 'type' => 'string', 'default' => '' ),
				'site'     => array( 'type' => 'string', 'default' => '' ),
				'metadata' => $any,
			),
			'supports'        => array( 'html' => false, 'className' => false, 'customClassName' => false ),
			'render_callback' => function ( $a, $content ) {
				return zhdanov_concept_tokens( $a['open'], $a['site'] ? $a['site'] : null ) . $content . $a['close'];
			},
			'editor_script'   => 'zhdanov-concept-editor',
		)
	);
	register_block_type(
		'zhdanov/craw',
		array(
			'api_version'     => 3,
			'title'           => 'Код концепта',
			'category'        => 'design',
			'attributes'      => array(
				'html'     => array( 'type' => 'string', 'default' => '' ),
				'site'     => array( 'type' => 'string', 'default' => '' ),
				'metadata' => $any,
			),
			'supports'        => array( 'html' => false, 'className' => false, 'customClassName' => false, 'inserter' => false ),
			'render_callback' => function ( $a ) {
				return zhdanov_concept_tokens( $a['html'], $a['site'] ? $a['site'] : null );
			},
			'editor_script'   => 'zhdanov-concept-editor',
		)
	);
	wp_register_script( 'zhdanov-concept-editor', ZHDANOV_CORE_URL . 'assets/concept/editor.js', array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-data', 'wp-i18n', 'wp-plugins', 'wp-editor' ), ZHDANOV_CORE_VERSION, true );
} );

/* ---------- Страница концепта на сайте ---------- */

add_filter( 'template_include', function ( $t ) {
	if ( zhdanov_concept_is_page() ) {
		// WordPress добавляет свой viewport, когда находит блочный шаблон темы (это происходит до нашего фильтра)
		remove_action( 'wp_head', '_block_template_viewport_meta_tag', 0 );
		return ZHDANOV_CORE_DIR . 'templates/concept-page.php';
	}
	return $t;
}, 99 );

/** Заголовок окна: как в концепте. */
add_filter( 'pre_get_document_title', function ( $t ) {
	if ( zhdanov_concept_is_page() ) {
		$seo = zhdanov_concept_seo( get_queried_object_id() );
		if ( '' !== $seo['title'] ) {
			return $seo['title'];
		}
	}
	return $t;
}, 99 );

/** На странице концепта оставляем только его дизайн: стили и скрипты темы и WordPress снимаем. */
function zhdanov_concept_strip_assets() {
	global $wp_styles, $wp_scripts;
	$keep = array( 'admin-bar', 'dashicons' );
	if ( $wp_styles ) {
		foreach ( (array) $wp_styles->queue as $h ) {
			if ( ! in_array( $h, $keep, true ) ) {
				wp_dequeue_style( $h );
			}
		}
	}
	if ( $wp_scripts ) {
		foreach ( (array) $wp_scripts->queue as $h ) {
			if ( ! in_array( $h, $keep, true ) ) {
				wp_dequeue_script( $h );
			}
		}
	}
}
add_action( 'template_redirect', function () {
	if ( ! zhdanov_concept_is_page() ) {
		return;
	}
	zhdanov_concept_prepare();
}, 1 );

/** Убрать с страницы концепта стили и вывод темы, WordPress и плагина. */
function zhdanov_concept_prepare() {
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
	remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_classic_theme_styles' );
	remove_action( 'wp_footer', 'wp_enqueue_stored_styles', 1 );
	remove_action( 'wp_head', 'wp_print_auto_sizes_contain_css_fix', 1 );
	remove_action( 'wp_head', 'wp_custom_css_cb', 101 );
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_head', 'wp_print_font_faces', 50 );               // шрифты темы смешивались бы со шрифтами концепта
	remove_action( 'wp_head', 'wp_print_font_faces_from_style_variations', 50 );
	remove_action( 'wp_head', '_block_template_viewport_meta_tag', 0 ); // у концепта свой viewport
	remove_action( 'wp_head', 'wp_print_speculation_rules' );
	remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_footer', 'wp_print_speculation_rules' );
	remove_action( 'wp_head', 'rel_canonical' );
	remove_action( 'wp_head', 'wp_generator' );
	add_action( 'wp_head', 'rel_canonical', 1 ); // канонический адрес = адрес на этом сайте
	add_action( 'wp_enqueue_scripts', 'zhdanov_concept_strip_assets', PHP_INT_MAX );
	add_action( 'wp_footer', 'zhdanov_concept_strip_assets', 0 );
	// Вывод самого плагина (баннеры, окна, разметка) на страницах концепта не нужен: у концепта свой.
	foreach ( array( 'wp_head', 'wp_footer', 'wp_body_open' ) as $hook ) {
		zhdanov_concept_unhook_own( $hook );
	}
}

/** Своя страница «не найдено» из концепта (404.html). */
function zhdanov_concept_404_page() {
	$slug = zhdanov_concept_current();
	if ( ! $slug ) {
		return 0;
	}
	$q = get_posts( array( 'post_type' => 'page', 'post_status' => array( 'publish', 'private' ), 'numberposts' => 50, 'fields' => 'ids', 'meta_key' => '_zh_concept', 'orderby' => 'meta_value', 'order' => 'ASC' ) );
	usort( $q, function ( $a, $b ) use ( $slug ) { // сначала страницы текущего концепта
		return ( get_post_meta( $b, '_zh_concept', true ) === $slug ) - ( get_post_meta( $a, '_zh_concept', true ) === $slug );
	} );
	foreach ( $q as $id ) {
		$m = get_post_meta( $id, '_zh_cpage', true );
		if ( isset( $m['path'] ) && '/404/' === $m['path'] ) {
			return $id;
		}
	}
	return 0;
}

function zhdanov_concept_unhook_own( $hook ) {
	global $wp_filter;
	if ( empty( $wp_filter[ $hook ] ) ) {
		return;
	}
	$dir   = wp_normalize_path( ZHDANOV_CORE_DIR . 'inc/' );
	$theme = wp_normalize_path( trailingslashit( get_template_directory() ) );
	$child = wp_normalize_path( trailingslashit( get_stylesheet_directory() ) );
	foreach ( $wp_filter[ $hook ]->callbacks as $prio => $cbs ) {
		foreach ( $cbs as $id => $cb ) {
			try {
				$f = $cb['function'];
				if ( $f instanceof Closure ) {
					$r = new ReflectionFunction( $f );
				} elseif ( is_string( $f ) && function_exists( $f ) ) {
					$r = new ReflectionFunction( $f );
				} else {
					continue;
				}
				$file = wp_normalize_path( (string) $r->getFileName() );
				// вывод плагина (кроме самого переноса) и вывод темы: у концепта всё своё
				if ( ( 0 === strpos( $file, $dir ) && false === strpos( $file, 'concept' ) ) || 0 === strpos( $file, $theme ) || 0 === strpos( $file, $child ) ) {
					remove_action( $hook, $f, $prio );
				}
			} catch ( ReflectionException $e ) {
				continue;
			}
		}
	}
}

/** Атрибуты <html>/<body> концепта (lang, class, data-*). */
function zhdanov_concept_attrs( $a, $extra_class = '' ) {
	$out = '';
	$a   = (array) $a;
	if ( $extra_class ) {
		$a['class'] = trim( ( isset( $a['class'] ) ? $a['class'] : '' ) . ' ' . $extra_class );
	}
	foreach ( $a as $k => $v ) {
		if ( ! preg_match( '/^[a-zA-Z_:][a-zA-Z0-9_:.\-]*$/', (string) $k ) ) {
			continue;
		}
		$out .= ' ' . $k . '="' . esc_attr( zhdanov_concept_tokens( $v ) ) . '"';
	}
	return $out;
}

/* ---------- Файлы концепта по их старым адресам ---------- */

/**
 * Скрипты концепта могут обращаться к файлам по абсолютному пути (/img/x.webp, /data.json).
 * Если такой адрес не нашёлся в WordPress, отдаём файл из папки концепта.
 * А /api/lead (функция Cloudflare) превращаем в заявку плагина.
 */
add_action( 'template_redirect', function () {
	if ( ! is_404() ) {
		return;
	}
	$path = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ); // phpcs:ignore
	$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	if ( $home && '/' !== $home && 0 === strpos( $path, $home ) ) {
		$path = '/' . substr( $path, strlen( $home ) );
	}
	$path = rawurldecode( $path );
	if ( preg_match( '#^/api/(lead|subscribe|order|contact|form)/?$#', $path, $m ) ) {
		zhdanov_concept_api( $m[1] );
	}
	if ( false !== strpos( $path, '..' ) ) {
		return;
	}
	$tries = array( $path );
	$segs  = explode( '/', trim( $path, '/' ) );
	for ( $k = 1; $k < count( $segs ) && $k < 4; $k++ ) { // images/x.webp со страницы /tury/bali/ → /images/x.webp
		$tries[] = '/' . implode( '/', array_slice( $segs, $k ) );
	}
	foreach ( array_keys( (array) get_option( 'zh_concepts', array() ) ) as $slug ) {
		$f = '';
		foreach ( $tries as $t ) {
			if ( is_file( zhdanov_concept_base_dir( $slug ) . $t ) ) {
				$f = zhdanov_concept_base_dir( $slug ) . $t;
				break;
			}
		}
		if ( $f && ! preg_match( '/\.(php\d?|phtml|zh-orig)$/i', $f ) ) {
			$type = wp_check_filetype( $f );
			$mime = $type['type'] ? $type['type'] : ( preg_match( '/\.(js|mjs)$/', $f ) ? 'text/javascript' : ( preg_match( '/\.css$/', $f ) ? 'text/css' : ( preg_match( '/\.json$/', $f ) ? 'application/json' : 'application/octet-stream' ) ) );
			status_header( 200 );
			header( 'Content-Type: ' . $mime );
			header( 'Cache-Control: public, max-age=2592000' );
			header( 'Content-Length: ' . filesize( $f ) );
			readfile( $f ); // phpcs:ignore
			exit;
		}
	}
}, 0 );

/** Своя 404 концепта: после переадресаций со старых адресов (Жданов → Переезд), они срабатывают раньше. */
add_action( 'template_redirect', function () {
	if ( ! is_404() ) {
		return;
	}
	$path = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ); // phpcs:ignore
	if ( ! preg_match( '/\.[a-z0-9]{2,5}$/i', $path ) ) {
		$p404 = zhdanov_concept_404_page();
		if ( $p404 ) {
			$GLOBALS['zh_concept_force'] = $p404;
			zhdanov_concept_prepare();
			status_header( 404 );
			nocache_headers();
			include ZHDANOV_CORE_DIR . 'templates/concept-page.php';
			exit;
		}
	}
}, 20 );

/** Формы концепта, которые отправлялись в функцию Cloudflare, сохраняем как заявки. */
function zhdanov_concept_api( $kind ) {
	global $wp_query;
	if ( $wp_query ) {
		$wp_query->is_404 = false;
	}
	status_header( 200 );
	if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) { // phpcs:ignore
		status_header( 405 );
		exit;
	}
	$raw  = file_get_contents( 'php://input' ); // phpcs:ignore
	$data = json_decode( (string) $raw, true );
	if ( ! is_array( $data ) ) {
		$data = wp_unslash( $_POST ); // phpcs:ignore
	}
	$data = is_array( $data ) ? $data : array();
	$flat = array();
	foreach ( $data as $k => $v ) {
		$flat[ sanitize_key( $k ) ] = is_scalar( $v ) ? sanitize_textarea_field( (string) $v ) : sanitize_textarea_field( wp_json_encode( $v, JSON_UNESCAPED_UNICODE ) );
	}
	$name  = isset( $flat['name'] ) ? $flat['name'] : ( isset( $flat['email'] ) ? $flat['email'] : 'Без имени' );
	$phone = isset( $flat['phone'] ) ? $flat['phone'] : ( isset( $flat['tel'] ) ? $flat['tel'] : '' );
	$email = isset( $flat['email'] ) ? sanitize_email( $flat['email'] ) : '';
	if ( '' === trim( $phone . $email ) || ! empty( $flat['website'] ) ) {
		wp_send_json( array( 'ok' => '' === trim( $phone . $email ) ? false : true ), '' === trim( $phone . $email ) ? 400 : 200 );
	}
	$ip_key = 'zh_api_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
	$count  = (int) get_transient( $ip_key );
	if ( $count >= 8 ) {
		wp_send_json( array( 'ok' => false, 'error' => 'rate' ), 429 );
	}
	set_transient( $ip_key, $count + 1, 10 * MINUTE_IN_SECONDS );
	$lines = array();
	$skip  = array( 'name', 'phone', 'email', 'page' );
	foreach ( $flat as $k => $v ) {
		if ( ! in_array( $k, $skip, true ) && '' !== $v ) {
			$lines[] = $k . ': ' . $v;
		}
	}
	$label = 'subscribe' === $kind ? 'Подписка' : 'Заявка';
	$id    = wp_insert_post(
		array(
			'post_type'    => 'zh_lead',
			'post_status'  => 'private',
			'post_title'   => $label . ': ' . $name . ( $phone ? ' · ' . $phone : '' ),
			'post_content' => implode( "\n", $lines ),
		)
	);
	$page = isset( $flat['page'] ) ? home_url( $flat['page'] ) : '';
	if ( $id ) {
		update_post_meta( $id, '_zh_name', $name );
		update_post_meta( $id, '_zh_phone', $phone );
		update_post_meta( $id, '_zh_email', $email );
		update_post_meta( $id, '_zh_page', esc_url_raw( $page ) );
		update_post_meta( $id, '_zh_src', 'Форма концепта /api/' . $kind );
		update_post_meta( $id, '_zh_consent', current_time( 'mysql' ) );
	}
	$to   = zhdanov_core_opt( 'form_email' ) ? zhdanov_core_opt( 'form_email' ) : get_option( 'admin_email' );
	$body = $label . ' с сайта ' . get_bloginfo( 'name' ) . "\n\nИмя: $name\n" . ( $phone ? "Телефон: $phone\n" : '' ) . ( $email ? "Почта: $email\n" : '' ) . ( $lines ? "\n" . implode( "\n", $lines ) . "\n" : '' ) . ( $page ? "\nСтраница: $page" : '' ) . "\n\nВсе заявки: " . admin_url( 'edit.php?post_type=zh_lead' );
	wp_mail( $to, $label . ' с сайта: ' . $name, $body, $email ? array( 'Reply-To: ' . $email ) : array() );
	do_action( 'zhdanov_lead_saved', $id, array( 'name' => $name, 'phone' => $phone, 'email' => $email, 'message' => implode( "\n", $lines ), 'page' => $page, 'text' => $body ) );
	wp_send_json( array( 'ok' => true ), 200 );
}

/* ---------- Импорт ---------- */

/** Блок из результата convert.js → массив для serialize_block(). */
function zhdanov_concept_block( $b, $slug ) {
	$meta = array( 'name' => isset( $b['label'] ) ? (string) $b['label'] : '' );
	if ( 'cwrap' === $b['type'] ) {
		$inner = array();
		foreach ( (array) $b['inner'] as $x ) {
			$inner[] = zhdanov_concept_block( $x, $slug );
		}
		return array(
			'blockName'    => 'zhdanov/cwrap',
			'attrs'        => array( 'open' => $b['open'], 'close' => $b['close'], 'site' => $slug, 'metadata' => $meta ),
			'innerBlocks'  => $inner,
			'innerHTML'    => '',
			'innerContent' => array_fill( 0, count( $inner ), null ),
		);
	}
	if ( 'craw' === $b['type'] ) {
		return array( 'blockName' => 'zhdanov/craw', 'attrs' => array( 'html' => $b['html'], 'site' => $slug, 'metadata' => $meta ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() );
	}
	return array(
		'blockName'    => 'zhdanov/csec',
		'attrs'        => array(
			'tpl'      => $b['tpl'],
			'tpls'     => (object) ( isset( $b['tpls'] ) ? $b['tpls'] : array() ),
			'v'        => (object) ( isset( $b['v'] ) ? $b['v'] : array() ),
			'm'        => (object) ( isset( $b['m'] ) ? $b['m'] : array() ),
			'role'     => isset( $b['role'] ) ? $b['role'] : '',
			'hide'     => ! empty( $b['hide'] ),
			'site'     => $slug,
			'metadata' => $meta,
		),
		'innerBlocks'  => array(),
		'innerHTML'    => '',
		'innerContent' => array(),
	);
}

/**
 * Создать или обновить страницы концепта.
 * $pages: результат convert.js по каждой странице. Разделы верхнего уровня, одинаковые на нескольких
 * страницах (шапка, подвал, окно заявки), становятся общими: правка в одном месте меняет все страницы.
 * Возвращает [ 'pages' => [путь => id], 'shared' => [...], 'front' => id ].
 */
function zhdanov_concept_import( $slug, $origin, $pages, $replace = false, $opts = array() ) {
	$slug   = sanitize_title( $slug );
	// skip: пути страниц, которые не трогать (правки клиента или галочка снята); общие разделы считаются по всем страницам
	$skip        = array_flip( array_map( function ( $x ) { return trim( (string) $x, '/' ); }, isset( $opts['skip'] ) ? (array) $opts['skip'] : array() ) );
	$keep_shared = array_flip( array_map( 'intval', isset( $opts['keep_shared'] ) ? (array) $opts['keep_shared'] : array() ) );
	$write       = array_values( array_filter( $pages, function ( $p ) use ( $skip ) { return ! isset( $skip[ trim( (string) $p['path'], '/' ) ] ); } ) );
	// Страницы сайта, которые сделаны не этим концептом и стоят по тем же адресам: без разрешения не трогаем.
	$conflicts = zhdanov_concept_conflicts( $slug, $write );
	if ( $conflicts && ! $replace ) {
		return new WP_Error( 'zh_conflict', 'На сайте уже есть страницы с такими адресами', array( 'status' => 409, 'conflicts' => $conflicts ) );
	}
	$snap   = zhdanov_concept_snap_begin( $slug, ( get_option( 'zh_concept_shared_' . $slug ) || zhdanov_concept_find_front( $slug ) ) ? 'Перед повторным переносом' : 'Перед первым переносом' );
	$all    = get_option( 'zh_concepts', array() );
	$first  = ! $all; // первый концепт на этом сайте: уберём заглушки WordPress
	$all[ $slug ] = array( 'origin' => esc_url_raw( $origin ), 'time' => time(), 'pages' => count( $pages ) );
	update_option( 'zh_concepts', $all, false );

	// какие разделы верхнего уровня повторяются на страницах
	$count = array();
	foreach ( $pages as $p ) {
		$seen = array();
		foreach ( (array) $p['blocks'] as $b ) {
			if ( 'csec' !== $b['type'] ) {
				continue;
			}
			$h = md5( $b['tpl'] . wp_json_encode( $b['v'] ) );
			if ( empty( $seen[ $h ] ) ) {
				$count[ $h ] = ( isset( $count[ $h ] ) ? $count[ $h ] : 0 ) + 1;
				$seen[ $h ]  = 1;
			}
		}
	}
	$shared  = get_option( 'zh_concept_shared_' . $slug, array() );
	$used    = array();
	$out     = array( 'pages' => array(), 'shared' => array(), 'front' => 0, 'kept' => array(), 'kept_shared' => array(), 'same' => array() );
	$total   = count( $pages );

	// сначала родительские страницы (короткие пути), чтобы /tury/bali/ встала под /tury/
	usort( $pages, function ( $a, $b ) {
		return substr_count( $a['path'], '/' ) - substr_count( $b['path'], '/' );
	} );
	foreach ( $pages as $p ) {
		$blocks = array();
		foreach ( (array) $p['blocks'] as $b ) {
			$h = 'csec' === $b['type'] ? md5( $b['tpl'] . wp_json_encode( $b['v'] ) ) : '';
			if ( $h && $total > 1 && $count[ $h ] >= 2 ) {
				$name = ( isset( $b['label'] ) && $b['label'] ? $b['label'] : 'Раздел' ) . ' (общий)';
				$ref  = isset( $shared[ $h ] ) ? (int) $shared[ $h ] : 0;
				$data = array(
					'post_type'    => 'wp_block',
					'post_status'  => 'publish',
					'post_title'   => $name,
					'post_content' => serialize_block( zhdanov_concept_block( $b, $slug ) ),
				);
				if ( $ref && get_post( $ref ) && 'trash' !== get_post_status( $ref ) ) {
					if ( isset( $keep_shared[ $ref ] ) ) {
						$out['kept_shared'][ $ref ] = get_the_title( $ref ); // раздел правили на сайте: оставляем как есть
					} elseif ( $data['post_content'] !== get_post_field( 'post_content', $ref ) || $name !== get_post_field( 'post_title', $ref ) ) {
						zhdanov_concept_snap_add( $snap, $ref );
						$data['ID'] = $ref;
						wp_update_post( wp_slash( $data ) );
						zhdanov_concept_mark( $ref );
					} elseif ( ! get_post_meta( $ref, '_zh_ihash', true ) ) {
						zhdanov_concept_mark( $ref );
					}
				} else {
					$ref = wp_insert_post( wp_slash( $data ) );
					zhdanov_concept_snap_created( $snap, $ref );
					zhdanov_concept_mark( $ref );
				}
				$shared[ $h ]       = $ref;
				$used[ $h ]         = $ref;
				$out['shared'][ $ref ] = $ref;
				$blocks[]           = array( 'blockName' => 'core/block', 'attrs' => array( 'ref' => $ref ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() );
				continue;
			}
			$blocks[] = zhdanov_concept_block( $b, $slug );
		}
		$content = serialize_blocks( $blocks );
		$path    = trim( $p['path'], '/' );
		$parts   = '' === $path ? array() : explode( '/', $path );
		$leaf    = $parts ? array_pop( $parts ) : '';
		$parent  = 0;
		$acc     = '';
		foreach ( $parts as $seg ) {
			$acc .= ( $acc ? '/' : '' ) . $seg;
			$pp   = get_page_by_path( $acc );
			if ( ! $pp ) {
				// у концепта нет страницы /tury/: создаём черновик-папку, адреса /tury/bali/ работают
				$pid = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => $seg, 'post_name' => $seg, 'post_parent' => $parent ) );
				zhdanov_concept_snap_created( $snap, $pid );
			} else {
				$pid = $pp->ID;
			}
			$parent = (int) $pid;
		}
		$title    = isset( $p['title'] ) && $p['title'] ? $p['title'] : ( $leaf ? $leaf : 'Главная' );
		$h1       = zhdanov_concept_h1( $p['blocks'] );
		$existing = '' === $path ? zhdanov_concept_find_front( $slug ) : ( ( $e = get_page_by_path( $path ) ) ? $e->ID : 0 );
		if ( isset( $skip[ $path ] ) ) {
			if ( $existing ) {
				$out['kept'][ $p['path'] ] = $existing;
				$out['pages'][ $p['path'] ] = $existing;
				if ( '' === $path && 'trash' !== get_post_status( $existing ) ) {
					update_option( 'show_on_front', 'page' );
					update_option( 'page_on_front', $existing );
					$out['front'] = $existing;
				}
			}
			continue;
		}
		$data     = array(
			'post_type'    => 'page',
			'post_status'  => '404' === $path ? 'private' : 'publish', // страница «не найдено» видна только вместо ненайденных адресов
			'post_title'   => $h1 ? $h1 : $title,
			'post_name'    => $leaf ? $leaf : 'glavnaya',
			'post_parent'  => $parent,
			'post_content' => $content,
		);
		$cpage = array(
			'title'       => $title,
			'description' => isset( $p['description'] ) ? $p['description'] : '',
			'head'        => isset( $p['head'] ) ? $p['head'] : '',
			'htmlAttrs'   => isset( $p['htmlAttrs'] ) ? $p['htmlAttrs'] : array(),
			'bodyAttrs'   => isset( $p['bodyAttrs'] ) ? $p['bodyAttrs'] : array(),
			'path'        => $p['path'],
		);
		if ( $existing ) {
			$cur = get_post( $existing );
			// в концепте ничего не поменялось: страницу не трогаем (быстрее, снимок меньше, дата правки не сбивается)
			if ( $cur && 'trash' !== $cur->post_status && $cur->post_content === $content && $cur->post_title === $data['post_title'] && $cur->post_status === $data['post_status'] && (int) $cur->post_parent === (int) $parent && $cur->post_name === $data['post_name'] && get_post_meta( $existing, '_zh_cpage', true ) == $cpage && get_post_meta( $existing, '_zh_concept', true ) === $slug ) { // phpcs:ignore
				if ( ! get_post_meta( $existing, '_zh_ihash', true ) ) {
					zhdanov_concept_mark( $existing );
				}
				$out['pages'][ $p['path'] ] = $existing;
				$out['same'][]              = $p['path'];
				if ( '' === $path ) {
					update_option( 'show_on_front', 'page' );
					update_option( 'page_on_front', $existing );
					$out['front'] = $existing;
				}
				continue;
			}
			zhdanov_concept_snap_add( $snap, $existing );
			if ( 'trash' === get_post_status( $existing ) ) {
				wp_untrash_post( $existing );
			}
			$data['ID'] = $existing;
			wp_update_post( wp_slash( $data ) );
			$id = $existing;
		} else {
			$id = wp_insert_post( wp_slash( $data ) );
			zhdanov_concept_snap_created( $snap, $id );
		}
		zhdanov_concept_mark( $id );
		update_post_meta( $id, '_zh_concept', $slug );
		update_post_meta( $id, '_zh_cpage', wp_slash( $cpage ) );
		$out['pages'][ $p['path'] ] = $id;
		if ( '' === $path ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $id );
			$out['front'] = $id;
		}
	}
	$out['shared'] = array_values( $out['shared'] );
	// прежние разделы остаются в карте: на них могут ссылаться не тронутые страницы, их правки тоже защищены
	update_option( 'zh_concept_shared_' . $slug, $used + (array) $shared, false );
	if ( $first ) {
		$out['removed'] = zhdanov_concept_remove_stubs( $snap );
	}
	zhdanov_concept_snap_end( $snap );
	$out['snap'] = $snap && get_post( $snap ) ? $snap : 0;
	do_action( 'zhdanov_concept_imported', $slug );
	if ( ! get_option( 'permalink_structure' ) ) {
		update_option( 'permalink_structure', '/%postname%/' );
	}
	flush_rewrite_rules( false );
	return $out;
}

/** Существующие страницы по адресам концепта, созданные не им. */
function zhdanov_concept_conflicts( $slug, $pages ) {
	$out = array();
	foreach ( (array) $pages as $p ) {
		$path = trim( (string) $p['path'], '/' );
		if ( '' === $path ) {
			continue; // главная клиента не перезаписывается: у концепта своя страница, главной она станет только по галочке
		}
		$e = get_page_by_path( $path );
		if ( $e && 'trash' !== $e->post_status && get_post_meta( $e->ID, '_zh_concept', true ) !== $slug ) {
			$out[] = array( 'path' => '/' . $path . '/', 'title' => $e->post_title, 'id' => $e->ID );
		}
	}
	return $out;
}

function zhdanov_concept_find_front( $slug ) {
	$q = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'meta_query' => array( array( 'key' => '_zh_concept', 'value' => $slug ) ), 'numberposts' => 50, 'fields' => 'ids' ) );
	foreach ( $q as $id ) {
		$m = get_post_meta( $id, '_zh_cpage', true );
		if ( isset( $m['path'] ) && '/' === $m['path'] ) {
			return $id;
		}
	}
	return 0;
}

/** Заголовок страницы для списка «Страницы» в админке: первый h1 концепта. */
function zhdanov_concept_h1( $blocks ) {
	foreach ( (array) $blocks as $b ) {
		if ( 'cwrap' === $b['type'] ) {
			$r = zhdanov_concept_h1( $b['inner'] );
			if ( $r ) {
				return $r;
			}
			continue;
		}
		if ( 'csec' !== $b['type'] || 'header' === $b['role'] ) {
			continue;
		}
		$html = zhdanov_concept_fill( $b['tpl'], (array) $b['v'], (array) $b['tpls'] );
		if ( preg_match( '#<h1[^>]*>(.*?)</h1>#is', $html, $m ) ) {
			return trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( str_replace( array( '<br>', '<br/>', '<br />' ), ' ', $m[1] ) ) ) );
		}
	}
	return '';
}

/* ---------- Редактор ---------- */

/** Концепт текущей редактируемой записи (страница или общий раздел). */
function zhdanov_concept_editing_slug() {
	$id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; // phpcs:ignore
	if ( $id ) {
		$s = get_post_meta( $id, '_zh_concept', true );
		if ( $s ) {
			return $s;
		}
		if ( 'wp_block' === get_post_type( $id ) && preg_match( '/"site":"([a-z0-9\-]+)"/', (string) get_post_field( 'post_content', $id ), $m ) ) {
			return $m[1];
		}
	}
	return '';
}

/** Стили концепта (ссылки и <style> из head главной) для окна редактора. */
function zhdanov_concept_editor_css( $slug ) {
	$front = zhdanov_concept_find_front( $slug );
	$p     = $front ? (array) get_post_meta( $front, '_zh_cpage', true ) : array();
	$head  = zhdanov_concept_tokens( isset( $p['head'] ) ? $p['head'] : '', $slug );
	$out   = array( 'links' => array(), 'inline' => '' );
	if ( preg_match_all( '#<link\b[^>]*rel=["\']?stylesheet[^>]*>#i', $head, $m ) ) {
		foreach ( $m[0] as $tag ) {
			if ( preg_match( '#href=["\']([^"\']+)#i', $tag, $h ) ) {
				$out['links'][] = html_entity_decode( $h[1] );
			}
		}
	}
	if ( preg_match_all( '#<style[^>]*>(.*?)</style>#is', $head, $m ) ) {
		$out['inline'] = implode( "\n", $m[1] );
	}
	return $out;
}

add_action( 'enqueue_block_assets', function () {
	if ( ! is_admin() ) {
		return;
	}
	$slug = zhdanov_concept_editing_slug();
	if ( ! $slug ) {
		return;
	}
	$css = zhdanov_concept_editor_css( $slug );
	foreach ( $css['links'] as $i => $href ) {
		wp_enqueue_style( 'zh-concept-' . $i, $href, array(), null ); // phpcs:ignore
	}
	wp_register_style( 'zh-concept-edit', false, array(), ZHDANOV_CORE_VERSION );
	wp_enqueue_style( 'zh-concept-edit' );
	wp_add_inline_style( 'zh-concept-edit', $css['inline'] . "\n" . '
.editor-styles-wrapper{padding:0!important}
.editor-styles-wrapper .is-root-container{padding:0!important;max-width:none!important}
.editor-styles-wrapper .is-root-container>*,.zh-cwrap-edit>*{max-width:none!important;margin-left:0!important;margin-right:0!important;margin-top:0!important;margin-bottom:0!important}
.zh-csec-edit{position:relative;outline-offset:-2px}
.zh-csec-edit.is-hidden-section{opacity:.35}
.zh-csec-hidden{position:absolute;top:8px;left:8px;background:#1e1e1e;color:#fff;font:600 13px/1.4 system-ui;padding:4px 10px;border-radius:4px;z-index:5}
.zh-csec-edit [data-zk]{cursor:text}
.zh-csec-edit [data-zk]:hover{outline:1px dashed rgba(56,88,233,.6);outline-offset:2px}
.zh-csec-edit [data-zk]:focus{outline:2px solid #3858e9;outline-offset:2px}
.zh-csec-edit [data-zi]{cursor:pointer}
.zh-csec-edit [data-zi]:hover{outline:3px solid #3858e9;outline-offset:-3px}
.zh-csec-edit [data-zr]:hover{box-shadow:0 0 0 1px rgba(56,88,233,.35)}
.zh-csec-edit [hidden]{display:none}
.zh-craw-edit{font:12px/1.4 system-ui;color:#757575;padding:4px 8px;background:#f0f0f0}
' );
}, 20 );

add_action( 'enqueue_block_editor_assets', function () {
	$slug  = zhdanov_concept_editing_slug();
	$base  = array();
	foreach ( array_keys( (array) get_option( 'zh_concepts', array() ) ) as $s ) {
		$base[ $s ] = zhdanov_concept_base_url( $s );
	}
	wp_localize_script(
		'zhdanov-concept-editor',
		'zhConceptEditor',
		array(
			'base'     => $base,
			'home'     => untrailingslashit( home_url() ),
			'postOnly' => (bool) $slug,
			'seo'      => ( $slug && isset( $_GET['post'] ) && 'page' === get_post_type( (int) $_GET['post'] ) ) ? array_intersect_key( (array) get_post_meta( (int) $_GET['post'], '_zh_cpage', true ), array( 'title' => 1, 'description' => 1 ) ) : array(), // phpcs:ignore
		)
	);
	wp_enqueue_script( 'zhdanov-concept-editor' );
	wp_enqueue_media();
	wp_add_inline_style( 'wp-edit-blocks', '.zh-cimg img{max-width:100%;height:auto;border-radius:4px;margin-bottom:6px;display:block}.zh-chint{color:#757575;font-size:12px;margin-top:8px}.zh-cbtns{display:flex;flex-wrap:wrap;gap:6px}' );
} );

/* ---------- Страница «Перенос концепта» ---------- */

add_action( 'admin_menu', function () {
	add_submenu_page( 'zhdanov-core', 'Перенос концепта', 'Перенос концепта', 'manage_options', 'zhdanov-concept', 'zhdanov_concept_page' );
}, 30 );

function zhdanov_concept_page() {
	$all = (array) get_option( 'zh_concepts', array() );
	wp_enqueue_script( 'zhdanov-concept-convert', ZHDANOV_CORE_URL . 'assets/concept/convert.js', array(), ZHDANOV_CORE_VERSION, true );
	wp_enqueue_script( 'zhdanov-concept-import', ZHDANOV_CORE_URL . 'assets/concept/import.js', array( 'zhdanov-concept-convert', 'wp-api-fetch' ), ZHDANOV_CORE_VERSION, true );
	?>
	<div class="wrap zh-cimp">
		<h1>Перенос концепта</h1>
		<p style="max-width:760px">Переносит сайт-концепт (Cloudflare или папка с файлами) в WordPress один в один: тот же вид, те же адреса страниц. Тексты, фото, ссылки и карточки после переноса правятся в обычном редакторе страниц. Повторный перенос того же концепта обновляет страницы, но страницы, которые уже правили на сайте, по умолчанию не трогает. Перед каждым переносом сохраняется снимок: откатить можно кнопкой «Вернуть как было».</p>
		<div class="card" style="max-width:760px;padding:16px 20px">
			<p><label for="zh-curl"><b>Ссылка на концепт</b></label><br>
			<input id="zh-curl" type="url" class="regular-text" style="width:100%" placeholder="https://tours-zarubin.pages.dev/" value=""></p>
			<p style="margin:4px 0 4px;color:#646970">или <label><input id="zh-czip" type="file" accept=".zip"> архив .zip с файлами концепта</label></p>
			<p style="margin:0 0 12px;color:#646970">или папка уже на хостинге (для больших концептов и если хостинг не достаёт до Cloudflare): <code>wp-content/uploads/zh-concept/</code><input id="zh-cdir" type="text" placeholder="tours-zarubin" style="width:160px"><code>/</code></p>
			<p><label><input id="zh-cfront" type="checkbox" checked> Сделать главную концепта главной страницей сайта</label></p>
			<p><button class="button button-primary button-hero" id="zh-cgo">Найти страницы</button></p>
			<div id="zh-cpages" hidden></div>
			<div id="zh-cprog" hidden><div class="zh-cbar"><span></span></div><p class="zh-cstat" aria-live="polite"></p></div>
			<div id="zh-clog" class="zh-clog" hidden></div>
		</div>
		<?php if ( $all ) : ?>
			<h2>Уже перенесены</h2>
			<table class="widefat striped" style="max-width:760px"><thead><tr><th>Концепт</th><th>Откуда</th><th>Страниц</th><th>Когда</th></tr></thead><tbody>
			<?php foreach ( $all as $slug => $c ) : ?>
				<tr><td><b><?php echo esc_html( $slug ); ?></b></td><td><?php echo esc_html( isset( $c['origin'] ) ? $c['origin'] : '' ); ?></td><td><?php echo (int) ( isset( $c['pages'] ) ? $c['pages'] : 0 ); ?></td><td><?php echo esc_html( isset( $c['time'] ) ? wp_date( 'd.m.Y H:i', $c['time'] ) : '' ); ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
		<?php endif; ?>
		<?php zhdanov_concept_snaps_html(); ?>
		<style>.zh-cbar{height:10px;background:#f0f0f1;border-radius:5px;overflow:hidden;margin:14px 0 6px}.zh-cbar span{display:block;height:100%;width:0;background:#2271b1;transition:width .2s}.zh-clog{max-height:220px;overflow:auto;background:#f6f7f7;padding:8px 10px;font:12px/1.5 monospace;margin-top:10px}.zh-cplist{max-height:260px;overflow:auto;border:1px solid #dcdcde;padding:6px 10px;margin:10px 0}.zh-cplist label{display:block;padding:2px 0}.zh-ctag{color:#646970;margin-left:4px}.zh-ctag-same{color:#2271b1}.zh-ctag-new{color:#00a32a}.zh-ctag-edited,.zh-ctag-deleted,.zh-ctag-conflict{color:#b32d2e}.zh-ctag-skip{color:#8a6d00}.zh-csafe{background:#f0f6fc;border-left:3px solid #2271b1;padding:6px 10px}</style>
	</div>
	<?php
}

/** Только http(s), без адресов локальной сети. */
function zhdanov_concept_safe_url( $u ) {
	$u = esc_url_raw( trim( (string) $u ) );
	if ( ! preg_match( '#^https?://#i', $u ) ) {
		return '';
	}
	return wp_http_validate_url( $u ) ? $u : '';
}

add_action( 'rest_api_init', function () {
	$admin = function () {
		return current_user_can( 'manage_options' );
	};
	// 1. Получить HTML страницы концепта (сервер забирает сам, CORS не мешает)
	register_rest_route( 'zhdanov/v1', '/concept/fetch', array(
		'methods'             => 'POST',
		'permission_callback' => $admin,
		'callback'            => function ( $req ) {
			$slug = sanitize_title( $req->get_param( 'slug' ) );
			$file = (string) $req->get_param( 'file' );
			if ( $file ) { // из загруженного архива
				$path = zhdanov_concept_base_dir( $slug ) . '/' . ltrim( str_replace( '..', '', $file ), '/' );
				if ( is_dir( $path ) ) {
					$path = trailingslashit( $path ) . 'index.html';
				} elseif ( ! is_file( $path ) && is_file( $path . '.html' ) ) {
					$path .= '.html';
				} elseif ( ! is_file( $path ) && is_file( rtrim( $path, '/' ) . '/index.html' ) ) {
					$path = rtrim( $path, '/' ) . '/index.html';
				}
				if ( ! is_file( $path ) ) {
					return new WP_Error( 'zh_nofile', 'Нет файла ' . $file, array( 'status' => 404 ) );
				}
				return array( 'html' => file_get_contents( $path ), 'url' => $req->get_param( 'url' ) ); // phpcs:ignore
			}
			$url = zhdanov_concept_safe_url( $req->get_param( 'url' ) );
			if ( ! $url ) {
				return new WP_Error( 'zh_url', 'Неверная ссылка', array( 'status' => 400 ) );
			}
			$r = wp_remote_get( $url, array( 'timeout' => 15, 'redirection' => 5, 'user-agent' => 'Mozilla/5.0 (WordPress; zhdanov-core concept import)' ) );
			if ( is_wp_error( $r ) ) { // перебои связи с Cloudflare: второй заход
				usleep( 700000 );
				$r = wp_remote_get( $url, array( 'timeout' => 15, 'redirection' => 5, 'user-agent' => 'Mozilla/5.0 (WordPress; zhdanov-core concept import)' ) );
			}
			if ( is_wp_error( $r ) ) {
				return new WP_Error( 'zh_net', 'Сайт не ответил: ' . $r->get_error_message(), array( 'status' => 502 ) );
			}
			$code = wp_remote_retrieve_response_code( $r );
			$type = (string) wp_remote_retrieve_header( $r, 'content-type' );
			if ( 200 !== $code || ( false === stripos( $type, 'html' ) && ! ( $req->get_param( 'any' ) && false !== stripos( $type, 'xml' ) ) ) ) {
				return new WP_Error( 'zh_http', 'Страница не найдена (' . $code . ')', array( 'status' => 404 ) );
			}
			return array( 'html' => wp_remote_retrieve_body( $r ), 'url' => $url );
		},
	) );
	// 2. Создать страницы из разобранного концепта
	register_rest_route( 'zhdanov/v1', '/concept/import', array(
		'methods'             => 'POST',
		'permission_callback' => $admin,
		'callback'            => function ( $req ) {
			$slug  = sanitize_title( $req->get_param( 'slug' ) );
			$pages = $req->get_param( 'pages' );
			if ( ! $slug || ! is_array( $pages ) || ! $pages ) {
				return new WP_Error( 'zh_data', 'Нет страниц для переноса', array( 'status' => 400 ) );
			}
			$front_before = (int) get_option( 'page_on_front' );
			$show_before  = get_option( 'show_on_front' );
			$r            = zhdanov_concept_import( $slug, (string) $req->get_param( 'origin' ), $pages, (bool) $req->get_param( 'replace' ), array( 'skip' => (array) $req->get_param( 'skip' ), 'keep_shared' => (array) $req->get_param( 'keep_shared' ) ) );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			if ( ! $req->get_param( 'front' ) ) {
				update_option( 'show_on_front', $show_before );
				update_option( 'page_on_front', $front_before );
			}
			$r['edit'] = $r['front'] ? get_edit_post_link( $r['front'], 'raw' ) : admin_url( 'edit.php?post_type=page' );
			$r['view'] = $r['front'] ? get_permalink( $r['front'] ) : home_url( '/' );
			return $r;
		},
	) );
	// 3. Скачать файлы концепта (порциями). В CSS и JS ищем новые файлы и возвращаем их в очередь.
	register_rest_route( 'zhdanov/v1', '/concept/assets', array(
		'methods'             => 'POST',
		'permission_callback' => $admin,
		'callback'            => function ( $req ) {
			$slug   = sanitize_title( $req->get_param( 'slug' ) );
			$origin = zhdanov_concept_safe_url( $req->get_param( 'origin' ) );
			$files  = array_slice( (array) $req->get_param( 'files' ), 0, 25 );
			if ( ! $slug || ! $origin ) {
				return new WP_Error( 'zh_data', 'Нет адреса концепта', array( 'status' => 400 ) );
			}
			$dir  = zhdanov_concept_base_dir( $slug );
			$GLOBALS['zh_t0'] = microtime( true );
			$done  = array();
			$fail  = array();
			$more  = array();
			$maybe = array();
			$quiet = array_flip( (array) $req->get_param( 'quiet' ) );
			foreach ( $files as $f ) {
				$f = '/' . ltrim( (string) $f, '/' );
				if ( false !== strpos( $f, '..' ) || ! preg_match( '#^/[\w\-./%@+~ ]+$#u', $f ) ) {
					$fail[] = $f;
					continue;
				}
				$dest = $dir . $f;
				if ( ! is_file( $dest ) || $req->get_param( 'force' ) ) {
					$furl = untrailingslashit( $origin ) . str_replace( '%2F', '/', rawurlencode( $f ) );
					$r    = wp_remote_get( $furl, array( 'timeout' => 20 ) );
					if ( is_wp_error( $r ) && ( microtime( true ) - $GLOBALS['zh_t0'] ) < 18 ) {
						$r = wp_remote_get( $furl, array( 'timeout' => 10 ) );
					}
					if ( is_wp_error( $r ) || 200 !== wp_remote_retrieve_response_code( $r ) ) {
						if ( ! isset( $quiet[ $f ] ) ) { // «возможный» файл из шаблона скрипта: нет, и ладно
							$fail[] = $f;
						}
						continue;
					}
					wp_mkdir_p( dirname( $dest ) );
					file_put_contents( $dest, wp_remote_retrieve_body( $r ) ); // phpcs:ignore
				}
				$done[] = $f;
				if ( preg_match( '/\.(css|js|mjs)$/i', $f ) ) {
					$more = array_merge( $more, zhdanov_concept_refs( $dest, $f, $maybe ) );
				}
			}
			return array( 'done' => $done, 'fail' => $fail, 'more' => array_values( array_unique( $more ) ), 'maybe' => array_values( array_unique( $maybe ) ) );
		},
	) );
	// Папка концепта уже на хостинге (залита через файловый менеджер или FTP): список страниц
	register_rest_route( 'zhdanov/v1', '/concept/scan', array(
		'methods'             => 'POST',
		'permission_callback' => $admin,
		'callback'            => function ( $req ) {
			$slug = sanitize_title( $req->get_param( 'slug' ) );
			$dir  = zhdanov_concept_base_dir( $slug );
			if ( ! $slug || ! is_dir( $dir ) ) {
				return new WP_Error( 'zh_nodir', 'Папка не найдена: wp-content/uploads/zh-concept/' . $slug . '/', array( 'status' => 404 ) );
			}
			$pages = array();
			$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $f ) {
				$rel = substr( wp_normalize_path( $f->getPathname() ), strlen( wp_normalize_path( $dir ) ) );
				if ( preg_match( '/\.html?$/i', $rel ) ) {
					$pages[] = $rel;
				} elseif ( preg_match( '/\.css$/i', $rel ) ) {
					zhdanov_concept_refs( $f->getPathname(), $rel ); // пути от корня в CSS → относительные
				} elseif ( preg_match( '/\.(php\d?|phtml|phar|cgi|pl|py|sh)$/i', $rel ) ) {
					@unlink( $f->getPathname() ); // phpcs:ignore -- исполняемые файлы в папке концепта не нужны
				}
			}
			sort( $pages );
			return array( 'pages' => $pages );
		},
	) );
	// Архив с файлами концепта
	register_rest_route( 'zhdanov/v1', '/concept/upload', array(
		'methods'             => 'POST',
		'permission_callback' => $admin,
		'callback'            => function ( $req ) {
			$slug  = sanitize_title( $req->get_param( 'slug' ) );
			$files = $req->get_file_params();
			if ( ! $slug || empty( $files['zip']['tmp_name'] ) ) {
				return new WP_Error( 'zh_zip', 'Нет архива', array( 'status' => 400 ) );
			}
			if ( ! class_exists( 'ZipArchive' ) ) {
				return new WP_Error( 'zh_zip', 'На хостинге нет PHP zip. Попросите поддержку хостинга включить его или перенесите по ссылке.', array( 'status' => 500 ) );
			}
			$zip = new ZipArchive();
			if ( true !== $zip->open( $files['zip']['tmp_name'] ) ) {
				return new WP_Error( 'zh_zip', 'Архив не открывается', array( 'status' => 400 ) );
			}
			// общая верхняя папка в архиве (concept/index.html) не нужна
			$names = array();
			for ( $i = 0; $i < $zip->numFiles; $i++ ) {
				$names[] = $zip->getNameIndex( $i );
			}
			$prefix = '';
			$first  = explode( '/', $names[0] );
			if ( count( $first ) > 1 ) {
				$cand = $first[0] . '/';
				$all  = true;
				foreach ( $names as $n ) {
					if ( 0 !== strpos( $n, $cand ) && '__MACOSX/' !== substr( $n, 0, 9 ) ) {
						$all = false;
						break;
					}
				}
				$prefix = $all ? $cand : '';
			}
			$dir   = zhdanov_concept_base_dir( $slug );
			$pages = array();
			$deny  = '/\.(php\d?|phtml|phar|htaccess|cgi|pl|py|sh|exe)$/i';
			foreach ( $names as $i => $n ) {
				if ( '/' === substr( $n, -1 ) || 0 === strpos( $n, '__MACOSX/' ) || false !== strpos( $n, '..' ) || preg_match( $deny, $n ) || false !== strpos( basename( $n ), '.DS_Store' ) ) {
					continue;
				}
				$rel  = substr( $n, strlen( $prefix ) );
				$dest = $dir . '/' . $rel;
				wp_mkdir_p( dirname( $dest ) );
				file_put_contents( $dest, $zip->getFromIndex( $i ) ); // phpcs:ignore
				if ( preg_match( '/\.html?$/i', $rel ) ) {
					$pages[] = '/' . $rel;
				}
			}
			$zip->close();
			// пути от корня в CSS → относительные
			$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $f ) {
				if ( preg_match( '/\.css$/i', $f->getFilename() ) ) {
					zhdanov_concept_refs( $f->getPathname(), substr( wp_normalize_path( $f->getPathname() ), strlen( wp_normalize_path( $dir ) ) ) );
				}
			}
			sort( $pages );
			return array( 'pages' => $pages );
		},
	) );
} );

/** Файлы, на которые ссылается CSS (url(), @import) или JS ('/img/x.webp'). Пути от корня концепта. */
function zhdanov_concept_refs( $file, $rel, &$maybe = null ) {
	$src  = (string) file_get_contents( $file ); // phpcs:ignore
	$base = dirname( $rel );
	$out  = array();
	$list = array();
	if ( preg_match( '/\.m?js$/i', $rel ) ) {
		$maybe = array_merge( (array) $maybe, zhdanov_concept_tpl_refs( $src ) );
	}
	if ( preg_match( '/\.css$/i', $rel ) ) {
		if ( preg_match_all( '/url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', $src, $m ) ) {
			$list = array_merge( $list, $m[1] );
		}
		if ( preg_match_all( '/@import\s+[\'"]([^\'"]+)[\'"]/i', $src, $m ) ) {
			$list = array_merge( $list, $m[1] );
		}
		// пути от корня (/fonts/x.woff2) в CSS делаем относительными: папка концепта лежит не в корне сайта
		$depth = max( 0, substr_count( trim( $rel, '/' ), '/' ) );
		$up    = str_repeat( '../', $depth );
		$new   = preg_replace( '/url\(\s*([\'"]?)\/(?!\/)/i', 'url($1' . ( $up ? $up : './' ), $src );
		if ( $new !== $src ) {
			file_put_contents( $file, $new ); // phpcs:ignore
		}
	} elseif ( preg_match_all( '#[\'"`]((?:/|\./|\.\./)?[\w\-][\w\-./%]*\.(?:webp|jpe?g|png|svg|gif|avif|mp4|webm|json|pdf|woff2?))[\'"`]#i', $src, $m ) ) {
		// в скриптах относительные пути (images/x.webp) считаются от корня сайта: скрипт работает на страницах
		foreach ( $m[1] as $u ) {
			$list[] = '/' === $u[0] ? $u : '/' . preg_replace( '#^(\./|\.\./)+#', '', $u );
		}
	}
	foreach ( $list as $u ) {
		$u = trim( $u );
		if ( '' === $u || preg_match( '#^(data:|https?:|//|\#)#i', $u ) ) {
			continue;
		}
		$u = preg_replace( '/[?#].*$/', '', $u );
		$p = '/' === $u[0] ? $u : $base . '/' . $u;
		// нормализация ../
		$parts = array();
		foreach ( explode( '/', $p ) as $seg ) {
			if ( '..' === $seg ) {
				array_pop( $parts );
			} elseif ( '.' !== $seg && '' !== $seg ) {
				$parts[] = $seg;
			}
		}
		$out[] = '/' . implode( '/', $parts );
	}
	return $out;
}

/**
 * Шаблоны адресов фото в скрипте: `images/${p.id}.webp`, 'img/' + item.slug + '.jpg'.
 * Возвращает [ [ начало пути, ключ (id, slug, i …), конец пути ] ].
 */
function zhdanov_concept_tpl_patterns( $src ) {
	$ext = '(?:webp|jpe?g|png|svg|gif|avif|mp4|webm|pdf)';
	$tpl = array();
	if ( preg_match_all( '#((?:/|\./)?[\w\-./%]*)\$\{\s*([\w.$\[\]\'"]+?)\s*\}([\w\-./%]*\.' . $ext . ')(?![\w])#i', $src, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $x ) {
			$tpl[] = array( $x[1], $x[2], $x[3] );
		}
	}
	if ( preg_match_all( '#([\'"])((?:/|\./)?[\w\-./%]*)\1\s*\+\s*([\w.$]+)\s*\+\s*([\'"])([\w\-./%]*\.' . $ext . ')\4#i', $src, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $x ) {
			$tpl[] = array( $x[2], $x[3], $x[5] );
		}
	}
	$out = array();
	foreach ( $tpl as $t ) {
		$key = preg_replace( '/^.*[.\[\'"]/', '', rtrim( $t[1], "]'\"" ) );
		if ( '' !== $key ) {
			$out[ $t[0] . '|' . $key . '|' . $t[2] ] = array( preg_replace( '#^(\./)+#', '', $t[0] ), $key, $t[2] );
		}
	}
	return array_values( $out );
}

/**
 * Адреса, которые скрипт собирает на ходу: `images/${p.id}.webp`, 'img/' + item.slug + '.jpg', `review-${i}.webp`.
 * Подставляем значения этого ключа из данных того же скрипта (id: 'bms2-k3'), а для счётчиков (i, n, idx) номера 0–30.
 * Это «возможные» файлы: каких нет на концепте, те молча пропускаются.
 */
function zhdanov_concept_tpl_refs( $src ) {
	$out = array();
	foreach ( zhdanov_concept_tpl_patterns( $src ) as $t ) {
		$key  = $t[1];
		$vals = array();
		if ( preg_match( '/^(i|j|k|n|idx|index|num|no|nr)$/i', $key ) ) {
			$vals = range( 0, 30 );
		} elseif ( preg_match_all( '/(?:[\s{,]|^)["\']?' . preg_quote( $key, '/' ) . '["\']?\s*:\s*(["\'])([\w\-.%@+~ ]{1,80})\1/u', $src, $mv ) ) {
			$vals = array_unique( $mv[2] );
		}
		foreach ( array_slice( $vals, 0, 200 ) as $v ) {
			$u     = $t[0] . $v . $t[2];
			$out[] = '/' === $u[0] ? $u : '/' . $u;
		}
	}
	return array_values( array_unique( $out ) );
}

/**
 * Заглушки свежего WordPress («Пример страницы», «Привет, мир!») в корзину, если их никто не правил.
 * Попадают в снимок переноса: «Вернуть как было» достаёт их обратно.
 */
function zhdanov_concept_remove_stubs( $snap ) {
	$out = array();
	foreach ( array( array( 'page', array( 'sample-page', 'primer-stranitsy', 'пример-страницы' ) ), array( 'post', array( 'hello-world', 'privet-mir', 'привет-мир' ) ) ) as $t ) {
		foreach ( get_posts( array( 'post_type' => $t[0], 'post_status' => array( 'publish', 'draft' ), 'post_name__in' => $t[1], 'numberposts' => 5 ) ) as $p ) {
			// не трогаем, если правили (дата изменения позже создания) или у записи больше одного комментария
			if ( strtotime( $p->post_modified_gmt ) - strtotime( $p->post_date_gmt ) > 60 || (int) $p->comment_count > 1 || get_post_meta( $p->ID, '_zh_concept', true ) ) {
				continue;
			}
			zhdanov_concept_snap_add( $snap, $p->ID );
			if ( wp_trash_post( $p->ID ) ) {
				$out[] = $p->post_title;
			}
		}
	}
	return $out;
}

/* ---------- Копия страницы (новый тур из готового) ---------- */

add_filter( 'page_row_actions', function ( $actions, $post ) {
	if ( current_user_can( 'edit_pages' ) && get_post_meta( $post->ID, '_zh_concept', true ) ) {
		$url                 = wp_nonce_url( admin_url( 'admin-post.php?action=zh_concept_copy&post=' . $post->ID ), 'zh_concept_copy_' . $post->ID );
		$actions['zh_copy'] = '<a href="' . esc_url( $url ) . '">Копия страницы</a>';
	}
	return $actions;
}, 10, 2 );

add_action( 'admin_post_zh_concept_copy', function () {
	$id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; // phpcs:ignore
	check_admin_referer( 'zh_concept_copy_' . $id );
	$src = get_post( $id );
	if ( ! $src || ! current_user_can( 'edit_page', $id ) ) {
		wp_die( 'Нет доступа' );
	}
	$new = wp_insert_post(
		wp_slash(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => $src->post_title . ' (копия)',
				'post_name'    => $src->post_name . '-kopiya',
				'post_parent'  => $src->post_parent,
				'post_content' => $src->post_content,
				'menu_order'   => $src->menu_order,
			)
		)
	);
	foreach ( array( '_zh_concept', '_zh_cpage' ) as $k ) {
		$v = get_post_meta( $id, $k, true );
		if ( '_zh_cpage' === $k && is_array( $v ) ) {
			$v['path'] = ''; // копия не главная
		}
		update_post_meta( $new, $k, wp_slash( $v ) );
	}
	wp_safe_redirect( admin_url( 'post.php?post=' . $new . '&action=edit&zh_copied=1' ) );
	exit;
} );

/* ---------- Заголовок и описание для поиска ---------- */

add_action( 'init', function () {
	foreach ( array( '_zh_seo_title', '_zh_seo_desc' ) as $k ) {
		register_post_meta( 'page', $k, array(
			'type'          => 'string',
			'single'        => true,
			'show_in_rest'  => true,
			'default'       => '',
			'auth_callback' => function () {
				return current_user_can( 'edit_pages' );
			},
			'sanitize_callback' => 'sanitize_text_field',
		) );
	}
} );

/** Заголовок и описание страницы концепта с учётом правок клиента. */
function zhdanov_concept_seo( $id ) {
	$p = (array) get_post_meta( $id, '_zh_cpage', true );
	$t = (string) get_post_meta( $id, '_zh_seo_title', true );
	$d = (string) get_post_meta( $id, '_zh_seo_desc', true );
	return array(
		'title'       => '' !== $t ? $t : ( isset( $p['title'] ) ? $p['title'] : get_the_title( $id ) ),
		'description' => '' !== $d ? $d : ( isset( $p['description'] ) ? $p['description'] : '' ),
		'custom'      => '' !== $t || '' !== $d,
	);
}

/* ---------- Найти и заменить по всему сайту концепта ---------- */

/** Заменить строку в значениях блоков (тексты, ссылки, подписи), не трогая разметку дизайна. */
function zhdanov_concept_replace_blocks( &$blocks, $find, $repl, &$n ) {
	foreach ( $blocks as &$b ) {
		if ( ! empty( $b['innerBlocks'] ) ) {
			zhdanov_concept_replace_blocks( $b['innerBlocks'], $find, $repl, $n );
		}
		if ( 'zhdanov/csec' === $b['blockName'] && isset( $b['attrs']['v'] ) ) {
			$b['attrs']['v'] = zhdanov_concept_replace_val( $b['attrs']['v'], $find, $repl, $n );
			foreach ( array( 'tpls', 'v', 'm' ) as $ok ) { // пустые объекты остаются объектами, не списками
				if ( isset( $b['attrs'][ $ok ] ) && array() === $b['attrs'][ $ok ] ) {
					$b['attrs'][ $ok ] = new stdClass();
				}
			}
		} elseif ( 'zhdanov/craw' === $b['blockName'] && isset( $b['attrs']['html'] ) ) {
			$c                   = substr_count( $b['attrs']['html'], $find );
			$n                  += $c;
			$b['attrs']['html']  = str_replace( $find, $repl, $b['attrs']['html'] );
		}
	}
}
function zhdanov_concept_replace_val( $v, $find, $repl, &$n ) {
	if ( is_array( $v ) ) {
		foreach ( $v as $k => $x ) {
			if ( '_t' === $k ) {
				continue;
			}
			$v[ $k ] = zhdanov_concept_replace_val( $x, $find, $repl, $n );
		}
		return $v;
	}
	if ( is_string( $v ) && '' !== $find ) {
		// и в обычном виде, и в виде HTML-сущностей (& → &amp;)
		$variants = array_unique( array( $find, esc_html( $find ) ) );
		foreach ( $variants as $f ) {
			$c = substr_count( $v, $f );
			if ( $c ) {
				$n += $c;
				$v  = str_replace( $f, $f === $find ? $repl : esc_html( $repl ), $v );
			}
		}
	}
	return $v;
}

/** $dry = true: только посчитать. Возвращает [ [id, title, count], ... ]. */
function zhdanov_concept_replace( $slug, $find, $repl, $dry ) {
	$ids  = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_zh_concept', 'value' => $slug ) ) ) );
	$ids  = array_merge( $ids, array_values( (array) get_option( 'zh_concept_shared_' . $slug, array() ) ) );
	$out  = array();
	$snap = 0;
	foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
		$post = get_post( $id );
		if ( ! $post ) {
			continue;
		}
		$n      = 0;
		$blocks = parse_blocks( $post->post_content );
		zhdanov_concept_replace_blocks( $blocks, $find, $repl, $n );
		$meta = get_post_meta( $id, '_zh_cpage', true );
		$hn   = 0;
		if ( is_array( $meta ) && isset( $meta['head'] ) ) {
			$hn                = substr_count( $meta['head'], $find );
			$meta['head']      = str_replace( $find, $repl, $meta['head'] );
			foreach ( array( 'title', 'description' ) as $k ) {
				if ( isset( $meta[ $k ] ) ) {
					$hn         += substr_count( $meta[ $k ], $find );
					$meta[ $k ]  = str_replace( $find, $repl, $meta[ $k ] );
				}
			}
		}
		if ( ! $n && ! $hn ) {
			continue;
		}
		$out[] = array( 'id' => $id, 'title' => 'wp_block' === $post->post_type ? $post->post_title : get_the_title( $id ), 'count' => $n + $hn, 'shared' => 'wp_block' === $post->post_type );
		if ( ! $dry ) {
			if ( ! $snap ) { // снимок перед заменой: «Вернуть как было» одной кнопкой
				$snap = zhdanov_concept_snap_begin( $slug, 'Перед заменой «' . mb_substr( $find, 0, 40 ) . '» → «' . mb_substr( $repl, 0, 40 ) . '»' );
				update_post_meta( $snap, '_zh_snap_kind', 'replace' );
			}
			zhdanov_concept_snap_add( $snap, $id );
			if ( $n ) {
				wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => serialize_blocks( $blocks ) ) ) );
			}
			if ( $hn ) {
				update_post_meta( $id, '_zh_cpage', wp_slash( $meta ) );
			}
		}
	}
	zhdanov_concept_snap_end( $snap );
	return $out;
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'zhdanov/v1', '/concept/replace', array(
		'methods'             => 'POST',
		'permission_callback' => function () {
			return current_user_can( 'edit_pages' ) && current_user_can( 'edit_others_pages' );
		},
		'callback'            => function ( $req ) {
			$slug = sanitize_title( $req->get_param( 'slug' ) );
			$find = (string) $req->get_param( 'find' );
			$repl = (string) $req->get_param( 'replace' );
			if ( mb_strlen( $find ) < 3 ) {
				return new WP_Error( 'zh_find', 'Слишком короткий текст для поиска: нужно хотя бы 3 знака', array( 'status' => 400 ) );
			}
			if ( ! current_user_can( 'unfiltered_html' ) ) {
				$repl = wp_kses_post( $repl );
			}
			$r = zhdanov_concept_replace( $slug, $find, $repl, ! $req->get_param( 'apply' ) );
			return array( 'pages' => $r, 'total' => array_sum( wp_list_pluck( $r, 'count' ) ) );
		},
	) );
} );

add_action( 'admin_menu', function () {
	if ( ! get_option( 'zh_concepts' ) ) {
		return;
	}
	add_submenu_page( 'zhdanov-core', 'Заменить по всему сайту', 'Заменить везде', 'edit_others_pages', 'zhdanov-creplace', 'zhdanov_concept_replace_page' );
}, 32 );

function zhdanov_concept_replace_page() {
	$all  = array_keys( (array) get_option( 'zh_concepts', array() ) );
	$slug = isset( $_GET['slug'] ) ? sanitize_title( wp_unslash( $_GET['slug'] ) ) : zhdanov_concept_current(); // phpcs:ignore
	wp_enqueue_script( 'wp-api-fetch' );
	?>
	<div class="wrap">
		<h1>Заменить по всему сайту</h1>
		<p style="max-width:760px">Сменился телефон, почта, ссылка на WhatsApp или цена? Впишите старое и новое значение: замена пройдёт по всем страницам, шапке, подвалу и окнам сайта. Сначала покажем, где и сколько раз это встречается.</p>
		<?php if ( count( $all ) > 1 ) : ?>
			<p>Концепт: <?php foreach ( $all as $s ) : ?><a class="button<?php echo $s === $slug ? ' button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'slug', $s ) ); ?>"><?php echo esc_html( $s ); ?></a> <?php endforeach; ?></p>
		<?php endif; ?>
		<div class="card" style="max-width:760px;padding:16px 20px">
			<p><label for="zh-rf"><b>Найти</b> (точно так, как написано на сайте)</label><br><input id="zh-rf" class="regular-text" style="width:100%" placeholder="+7 900 123-45-67 или wa.me/79001234567"></p>
			<p><label for="zh-rr"><b>Заменить на</b></label><br><input id="zh-rr" class="regular-text" style="width:100%"></p>
			<p><button class="button" id="zh-rcheck">Показать, где встречается</button> <button class="button button-primary" id="zh-rgo" disabled>Заменить везде</button></p>
			<div id="zh-rres" aria-live="polite"></div>
			<p style="color:#646970;font-size:12px">Телефон часто записан двумя способами: для показа (+7 900 123-45-67) и в ссылке (tel:+79001234567, wa.me/79001234567). Замените оба. Перед каждой заменой сохраняется снимок: ниже кнопка «Вернуть как было».</p>
		</div>
		<?php zhdanov_concept_snaps_html( 'replace' ); ?>
	</div>
	<script>
	( function () {
		var f = document.getElementById( 'zh-rf' ), r = document.getElementById( 'zh-rr' ), out = document.getElementById( 'zh-rres' ), go = document.getElementById( 'zh-rgo' );
		function run( apply ) {
			out.textContent = apply ? 'Заменяю…' : 'Ищу…';
			return wp.apiFetch( { path: '/zhdanov/v1/concept/replace', method: 'POST', data: { slug: <?php echo wp_json_encode( $slug ); ?>, find: f.value, replace: r.value, apply: apply ? 1 : 0 } } ).then( function ( res ) {
				out.innerHTML = '';
				var p = document.createElement( 'p' );
				p.innerHTML = '<b>' + ( apply ? 'Заменено: ' : 'Найдено: ' ) + res.total + '</b>' + ( res.total ? ' на ' + res.pages.length + ' стр.' : ' Проверьте написание: пробелы, дефисы, +7 или 8.' ) + ( apply && res.total ? ' <a class="button" href="admin.php?page=zhdanov-creplace&amp;r=' + Date.now() + '#zh-snaps">Ошиблись? Вернуть как было</a>' : '' );
				out.appendChild( p );
				var ul = document.createElement( 'ul' );
				res.pages.forEach( function ( x ) { var li = document.createElement( 'li' ); li.textContent = x.title + ( x.shared ? ' (общий раздел)' : '' ) + ': ' + x.count; ul.appendChild( li ); } );
				out.appendChild( ul );
				go.disabled = apply || ! res.total;
			} ).catch( function ( e ) { out.textContent = 'Ошибка: ' + ( e.message || e.code ); } );
		}
		document.getElementById( 'zh-rcheck' ).addEventListener( 'click', function () { run( false ); } );
		go.addEventListener( 'click', function () { if ( window.confirm( 'Заменить «' + f.value + '» на «' + r.value + '» по всему сайту?' ) ) { run( true ); } } );
		[ f, r ].forEach( function ( x ) { x.addEventListener( 'input', function () { go.disabled = true; } ); } );
	}() );
	</script>
	<?php
}
