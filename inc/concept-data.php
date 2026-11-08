<?php
/**
 * Данные внутри скриптов концепта (const EVENTS = [ {...}, ... ]).
 * Плагин находит такие списки в JS-файлах концепта, показывает в «Жданов → Данные концепта»
 * таблицей и после правки пересобирает JS-файл. Исходник хранится рядом (*.zh-orig).
 *
 * @package zhdanov-core
 */

defined( 'ABSPATH' ) || exit;

/* ---------- Разбор литерала JavaScript (без выполнения кода) ---------- */

class Zhdanov_Js_Literal {
	private $s;
	private $i = 0;
	private $n;
	public function __construct( $s, $pos ) {
		$this->s = $s;
		$this->i = $pos;
		$this->n = strlen( $s );
	}
	public function pos() {
		return $this->i;
	}
	private function ws() {
		while ( $this->i < $this->n ) {
			$c = $this->s[ $this->i ];
			if ( ' ' === $c || "\n" === $c || "\r" === $c || "\t" === $c ) {
				$this->i++;
			} elseif ( '/' === $c && '/' === ( $this->s[ $this->i + 1 ] ?? '' ) ) {
				$e       = strpos( $this->s, "\n", $this->i );
				$this->i = false === $e ? $this->n : $e;
			} elseif ( '/' === $c && '*' === ( $this->s[ $this->i + 1 ] ?? '' ) ) {
				$e = strpos( $this->s, '*/', $this->i + 2 );
				if ( false === $e ) {
					throw new Exception( 'comment' );
				}
				$this->i = $e + 2;
			} else {
				break;
			}
		}
	}
	public function value() {
		$this->ws();
		if ( $this->i >= $this->n ) {
			throw new Exception( 'eof' );
		}
		$c = $this->s[ $this->i ];
		if ( '{' === $c ) {
			return $this->obj();
		}
		if ( '[' === $c ) {
			return $this->arr();
		}
		if ( '"' === $c || "'" === $c || '`' === $c ) {
			return $this->str( $c );
		}
		if ( preg_match( '/\G-?(?:0x[0-9a-f]+|\d[\d_]*(?:\.\d+)?(?:e[+-]?\d+)?|\.\d+)/Ai', $this->s, $m, 0, $this->i ) ) {
			$this->i += strlen( $m[0] );
			$v        = str_replace( '_', '', $m[0] );
			return false !== stripos( $v, '0x' ) ? hexdec( $v ) : ( 0 + $v );
		}
		foreach ( array( 'true' => true, 'false' => false, 'null' => null ) as $w => $v ) {
			if ( substr( $this->s, $this->i, strlen( $w ) ) === $w && ! preg_match( '/[\w$]/', $this->s[ $this->i + strlen( $w ) ] ?? '' ) ) {
				$this->i += strlen( $w );
				return $v;
			}
		}
		throw new Exception( 'value' );
	}
	private function str( $q ) {
		$this->i++;
		$out = '';
		while ( $this->i < $this->n ) {
			$c = $this->s[ $this->i ];
			if ( $c === $q ) {
				$this->i++;
				return $out;
			}
			if ( '`' === $q && '$' === $c && '{' === ( $this->s[ $this->i + 1 ] ?? '' ) ) {
				throw new Exception( 'template' ); // ${...} внутри строки = код, не данные
			}
			if ( "\n" === $c && '`' !== $q ) {
				throw new Exception( 'newline' );
			}
			if ( '\\' === $c ) {
				$d        = $this->s[ $this->i + 1 ] ?? '';
				$map      = array( 'n' => "\n", 't' => "\t", 'r' => "\r", 'b' => "\x08", 'f' => "\f", 'v' => "\x0B", '0' => "\0" );
				$this->i += 2;
				if ( isset( $map[ $d ] ) ) {
					$out .= $map[ $d ];
				} elseif ( 'u' === $d ) {
					if ( '{' === ( $this->s[ $this->i ] ?? '' ) ) {
						$e        = strpos( $this->s, '}', $this->i );
						$cp       = hexdec( substr( $this->s, $this->i + 1, $e - $this->i - 1 ) );
						$this->i  = $e + 1;
					} else {
						$cp       = hexdec( substr( $this->s, $this->i, 4 ) );
						$this->i += 4;
					}
					$out .= mb_chr( (int) $cp, 'UTF-8' );
				} elseif ( 'x' === $d ) {
					$out     .= chr( hexdec( substr( $this->s, $this->i, 2 ) ) );
					$this->i += 2;
				} elseif ( "\n" === $d ) {
					continue; // перенос строки внутри строки
				} else {
					$out .= $d;
				}
				continue;
			}
			$out .= $c;
			$this->i++;
		}
		throw new Exception( 'string' );
	}
	private function key() {
		$this->ws();
		$c = $this->s[ $this->i ] ?? '';
		if ( '"' === $c || "'" === $c ) {
			return $this->str( $c );
		}
		if ( preg_match( '/\G[A-Za-z_$][\w$]*|\G\d+/A', $this->s, $m, 0, $this->i ) ) {
			$this->i += strlen( $m[0] );
			return $m[0];
		}
		throw new Exception( 'key' );
	}
	private function obj() {
		$this->i++;
		$o = array();
		while ( true ) {
			$this->ws();
			if ( '}' === ( $this->s[ $this->i ] ?? '' ) ) {
				$this->i++;
				return (object) $o;
			}
			$k = $this->key();
			$this->ws();
			if ( ':' !== ( $this->s[ $this->i ] ?? '' ) ) {
				throw new Exception( 'colon' ); // короткая запись { a } или метод = код
			}
			$this->i++;
			$o[ $k ] = $this->value();
			$this->ws();
			$c = $this->s[ $this->i ] ?? '';
			if ( ',' === $c ) {
				$this->i++;
			} elseif ( '}' !== $c ) {
				throw new Exception( 'obj' );
			}
		}
	}
	private function arr() {
		$this->i++;
		$a = array();
		while ( true ) {
			$this->ws();
			if ( ']' === ( $this->s[ $this->i ] ?? '' ) ) {
				$this->i++;
				return $a;
			}
			$a[] = $this->value();
			$this->ws();
			$c = $this->s[ $this->i ] ?? '';
			if ( ',' === $c ) {
				$this->i++;
			} elseif ( ']' !== $c ) {
				throw new Exception( 'arr' );
			}
		}
	}
}

function zhdanov_cdata_is_list( $a ) {
	return array() === $a || array_keys( $a ) === range( 0, count( $a ) - 1 );
}

/** Похоже на данные для людей: есть строки с буквами. */
function zhdanov_cdata_human( $v, $depth = 0 ) {
	if ( $depth > 6 ) {
		return false;
	}
	if ( is_string( $v ) ) {
		return (bool) preg_match( '/\p{L}{2,}/u', $v ) && preg_match( '/[\s\p{Cyrillic}]/u', $v );
	}
	if ( is_array( $v ) || is_object( $v ) ) {
		foreach ( (array) $v as $x ) {
			if ( zhdanov_cdata_human( $x, $depth + 1 ) ) {
				return true;
			}
		}
	}
	return false;
}

/** Найти списки данных в JS-коде. Возвращает [ [name, start, end, value, shape, decl, example], ... ]. */
function zhdanov_cdata_scan( $src ) {
	$out = array();
	if ( ! preg_match_all( '/(?:\b(?:const|let|var)\s+|\bwindow\.)([A-Za-z_$][\w$]*)\s*=\s*(?=[\[{\'"`\d-]|true\b|false\b|null\b)/', $src, $m, PREG_OFFSET_CAPTURE ) ) {
		return $out;
	}
	foreach ( $m[0] as $i => $hit ) {
		$name  = $m[1][ $i ][0];
		$start = $hit[1] + strlen( $hit[0] );
		try {
			$p   = new Zhdanov_Js_Literal( $src, $start );
			$val = $p->value();
		} catch ( Exception $e ) {
			continue;
		}
		$end = $p->pos();
		// после литерала должен быть конец выражения, иначе это часть кода: x = [1,2].map(...)
		if ( ! preg_match( '/\G\s*(?:;|,\s*(?:const|let|var)?|\n|\/\/|\/\*|$)/A', $src, $mm, 0, $end ) ) {
			continue;
		}
		$arr      = json_decode( wp_json_encode( $val ), true );
		$settings = (bool) preg_match( '/^[A-Z][A-Z0-9_]*$/', $name ) && strlen( $name ) > 1; // НАСТРОЙКИ заглавными
		$rows     = is_array( $arr ) && zhdanov_cdata_is_list( $arr ) ? $arr : null;
		$human    = zhdanov_cdata_human( $arr );
		if ( ! $settings ) {
			$ok = is_array( $arr ) && $human && ( ( $rows && is_array( $rows[0] ?? null ) && count( $rows ) >= 2 ) );
			if ( ! $ok ) {
				continue;
			}
		}
		$lit   = substr( $src, $start, $end - $start );
		$ex    = null;
		if ( is_array( $arr ) && preg_match( '#(?://|/\*)\s*(\{.*?\})\s*,?\s*(?:\*/)?\s*$#m', $lit, $em ) ) {
			try {
				$ep = new Zhdanov_Js_Literal( $em[1], 0 );
				$ex = json_decode( wp_json_encode( $ep->value() ), true );
			} catch ( Exception $e ) {
				$ex = null;
			}
		}
		$shape = ! is_array( $arr ) ? 'value' : ( $rows !== null ? ( ( $rows && ! is_array( $rows[0] ) ) ? 'list' : 'rows' ) : 'map' );
		$out[] = array( 'name' => $name, 'start' => $start, 'end' => $end, 'decl' => $hit[1], 'value' => $arr, 'shape' => $shape, 'example' => $ex );
	}
	return $out;
}

/** Комментарий прямо над объявлением (часто там подсказка от дизайнера: «start / end: время по Москве»). */
function zhdanov_cdata_hint( $src, $decl ) {
	$before = rtrim( substr( $src, 0, $decl ) );
	$lines  = array();
	if ( '*/' === substr( $before, -2 ) ) {
		$o = strrpos( $before, '/*' );
		if ( false !== $o ) {
			return trim( preg_replace( '/^[\s=*]+|[\s=*]+$/m', '', substr( $before, $o + 2, -2 ) ) );
		}
	}
	$parts = explode( "\n", $before );
	while ( $parts ) {
		$l = trim( end( $parts ) );
		if ( 0 === strpos( $l, '//' ) ) {
			array_unshift( $lines, trim( substr( $l, 2 ) ) );
			array_pop( $parts );
		} else {
			break;
		}
	}
	return implode( "\n", $lines );
}

/** Просканировать все JS концепта и запомнить найденные списки. */
function zhdanov_cdata_index( $slug ) {
	$dir = zhdanov_concept_base_dir( $slug );
	$res = array();
	if ( ! is_dir( $dir ) ) {
		return $res;
	}
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		if ( ! preg_match( '/\.m?js$/i', $f->getFilename() ) || false !== strpos( $f->getFilename(), '.min.' ) || $f->getSize() > 400000 ) {
			continue;
		}
		$path = $f->getPathname();
		$rel  = substr( wp_normalize_path( $path ), strlen( wp_normalize_path( $dir ) ) );
		$orig = $path . '.zh-orig';
		$src  = is_file( $orig ) ? file_get_contents( $orig ) : file_get_contents( $path ); // phpcs:ignore
		$pats = function_exists( 'zhdanov_concept_tpl_patterns' ) ? zhdanov_concept_tpl_patterns( (string) $src ) : array();
		foreach ( zhdanov_cdata_scan( (string) $src ) as $d ) {
			// фото строки по шаблону скрипта: images/${p.id}.webp → у каждой строки своё фото по её id
			$photo = null;
			if ( 'rows' === $d['shape'] && is_array( $d['value'] ) ) {
				foreach ( $pats as $pt ) {
					$has = 0;
					foreach ( $d['value'] as $row ) {
						if ( is_array( $row ) && isset( $row[ $pt[1] ] ) && is_scalar( $row[ $pt[1] ] ) && '' !== (string) $row[ $pt[1] ] ) {
							$has++;
						}
					}
					if ( $has && $has === count( $d['value'] ) ) {
						$photo = array( 'prefix' => '/' === $pt[0][0] ? $pt[0] : '/' . $pt[0], 'key' => $pt[1], 'suffix' => $pt[2] );
						break;
					}
				}
			}
			$id   = md5( $rel . '|' . $d['name'] );
			$hint = zhdanov_cdata_hint( (string) $src, $d['decl'] );
			if ( null === $d['example'] && 'rows' === $d['shape'] && preg_match( '/\{[^{}]*:[^{}]*\}/', $hint, $hm ) ) {
				try {
					$hp           = new Zhdanov_Js_Literal( $hm[0], 0 );
					$d['example'] = json_decode( wp_json_encode( $hp->value() ), true );
				} catch ( Exception $e ) {
					$d['example'] = null;
				}
			}
			$res[ $id ] = array(
				'file'  => $rel,
				'name'  => $d['name'],
				'shape' => $d['shape'],
				'hint'  => $hint,
				'example' => $d['example'],
				'count' => is_array( $d['value'] ) ? count( $d['value'] ) : 0,
				'order' => $d['decl'],
				'photo' => $photo,
			);
		}
	}
	update_option( 'zh_cdata_' . $slug, $res, false );
	return $res;
}

/** Текущее значение списка (из файла: он всегда отражает последнюю правку). */
function zhdanov_cdata_get( $slug, $id ) {
	$idx = get_option( 'zh_cdata_' . $slug, array() );
	if ( empty( $idx[ $id ] ) ) {
		return null;
	}
	$path = zhdanov_concept_base_dir( $slug ) . $idx[ $id ]['file'];
	if ( ! is_file( $path ) ) {
		return null;
	}
	foreach ( zhdanov_cdata_scan( (string) file_get_contents( $path ) ) as $d ) { // phpcs:ignore
		if ( $d['name'] === $idx[ $id ]['name'] ) {
			return $d['value'];
		}
	}
	return null;
}

/** Записать новое значение списка в JS-файл (исходник сохраняем один раз рядом). */
function zhdanov_cdata_save( $slug, $id, $value ) {
	$idx = get_option( 'zh_cdata_' . $slug, array() );
	if ( empty( $idx[ $id ] ) ) {
		return new WP_Error( 'zh_cdata', 'Список не найден' );
	}
	$path = zhdanov_concept_base_dir( $slug ) . $idx[ $id ]['file'];
	$src  = (string) file_get_contents( $path ); // phpcs:ignore
	foreach ( zhdanov_cdata_scan( $src ) as $d ) {
		if ( $d['name'] !== $idx[ $id ]['name'] ) {
			continue;
		}
		if ( ! is_file( $path . '.zh-orig' ) ) {
			copy( $path, $path . '.zh-orig' );
		}
		$json = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
		if ( false === $json ) {
			return new WP_Error( 'zh_cdata', 'Не удалось сохранить данные' );
		}
		$json = str_replace( array( "\u{2028}", "\u{2029}", '</script' ), array( ' ', ' ', '<\/script' ), $json );
		$new  = substr( $src, 0, $d['start'] ) . $json . substr( $src, $d['end'] );
		file_put_contents( $path, $new ); // phpcs:ignore
		$idx[ $id ]['count'] = is_array( $value ) ? count( $value ) : 0;
		update_option( 'zh_cdata_' . $slug, $idx, false );
		// версия файла для сброса кеша браузера
		update_option( 'zh_cdata_ver_' . $slug, time(), false );
		return true;
	}
	return new WP_Error( 'zh_cdata', 'Список не найден в файле' );
}

/** После правки данных браузеры должны взять новый JS: добавляем ?v= к ссылкам на js концепта. */
add_filter( 'zhdanov_concept_content', function ( $html ) {
	$ver = get_option( 'zh_cdata_ver_' . zhdanov_concept_current() );
	if ( ! $ver ) {
		return $html;
	}
	$base = preg_quote( zhdanov_concept_base_url( zhdanov_concept_current() ), '#' );
	return preg_replace( '#(<script\b[^>]*\bsrc="' . $base . '[^"?]+\.m?js)"#', '$1?v=' . (int) $ver . '"', $html );
} );

/* ---------- REST ---------- */

add_action( 'rest_api_init', function () {
	$admin = function () {
		return current_user_can( 'edit_pages' );
	};
	register_rest_route( 'zhdanov/v1', '/concept/data', array(
		array(
			'methods'             => 'GET',
			'permission_callback' => $admin,
			'callback'            => function ( $req ) {
				$slug = sanitize_title( $req->get_param( 'slug' ) );
				$id   = (string) $req->get_param( 'id' );
				if ( $id ) {
					return array( 'value' => zhdanov_cdata_get( $slug, $id ) );
				}
				return array( 'lists' => $req->get_param( 'rescan' ) ? zhdanov_cdata_index( $slug ) : get_option( 'zh_cdata_' . $slug, array() ) );
			},
		),
		array(
			'methods'             => 'POST',
			'permission_callback' => $admin,
			'callback'            => function ( $req ) {
				$slug = sanitize_title( $req->get_param( 'slug' ) );
				$val  = $req->get_param( 'value' );
				$val  = zhdanov_cdata_clean( $val );
				$r    = zhdanov_cdata_save( $slug, (string) $req->get_param( 'id' ), $val );
				return is_wp_error( $r ) ? $r : array( 'ok' => true );
			},
		),
	) );
} );

/** Значения из формы: строки без тегов-скриптов, числа как числа. */
function zhdanov_cdata_clean( $v, $depth = 0 ) {
	if ( $depth > 8 ) {
		return null;
	}
	if ( is_array( $v ) ) {
		$o = array();
		foreach ( $v as $k => $x ) {
			$o[ is_int( $k ) ? $k : sanitize_text_field( $k ) ] = zhdanov_cdata_clean( $x, $depth + 1 );
		}
		return $o;
	}
	if ( is_string( $v ) ) {
		return current_user_can( 'unfiltered_html' ) ? $v : wp_kses_post( $v );
	}
	return is_bool( $v ) || is_int( $v ) || is_float( $v ) || null === $v ? $v : (string) $v;
}

/** Фото строки: файл из медиатеки кладём в папку концепта под нужным именем (bms5.webp). */
function zhdanov_cdata_photo( $slug, $path, $att ) {
	$path = '/' . ltrim( (string) $path, '/' );
	if ( false !== strpos( $path, '..' ) || ! preg_match( '#^/[\w\-./%@+~ ]+\.(webp|jpe?g|png|gif|avif)$#iu', $path, $m ) ) {
		return new WP_Error( 'zh_photo', 'Неверное имя файла' );
	}
	$src = get_attached_file( (int) $att );
	if ( ! $src || ! is_file( $src ) || ! wp_attachment_is_image( (int) $att ) ) {
		return new WP_Error( 'zh_photo', 'Это не фото' );
	}
	$dest = zhdanov_concept_base_dir( $slug ) . $path;
	wp_mkdir_p( dirname( $dest ) );
	$ext  = strtolower( $m[1] );
	$mime = array( 'webp' => 'image/webp', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'avif' => 'image/avif' );
	$ed   = wp_get_image_editor( $src );
	$ok   = false;
	if ( ! is_wp_error( $ed ) ) {
		$size = $ed->get_size();
		if ( $size['width'] > 1800 || $size['height'] > 1800 ) {
			$ed->resize( 1800, 1800 );
		}
		$tmp = $dest . '.zh-tmp.' . $ext;
		$r   = $ed->save( $tmp, $mime[ $ext ] );
		if ( ! is_wp_error( $r ) && is_file( $r['path'] ) ) {
			$ok = rename( $r['path'], $dest );
		}
	}
	if ( ! $ok ) { // сервер не умеет этот формат: кладём как есть (браузеры показывают по содержимому)
		$ok = copy( $src, $dest );
	}
	if ( ! $ok ) {
		return new WP_Error( 'zh_photo', 'Не удалось сохранить файл в папку сайта' );
	}
	update_option( 'zh_cdata_ver_' . $slug, time(), false );
	return array( 'url' => zhdanov_concept_base_url( $slug ) . $path . '?v=' . time() );
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'zhdanov/v1', '/concept/data/photo', array(
		'methods'             => 'POST',
		'permission_callback' => function () {
			return current_user_can( 'edit_pages' ) && current_user_can( 'upload_files' );
		},
		'callback'            => function ( $req ) {
			return zhdanov_cdata_photo( sanitize_title( $req->get_param( 'slug' ) ), (string) $req->get_param( 'path' ), (int) $req->get_param( 'att' ) );
		},
	) );
} );

/* ---------- Страница «Данные концепта» ---------- */

add_action( 'admin_menu', function () {
	if ( ! get_option( 'zh_concepts' ) ) {
		return;
	}
	add_submenu_page( 'zhdanov-core', 'Данные концепта', 'Данные концепта', 'edit_pages', 'zhdanov-cdata', 'zhdanov_cdata_page' );
}, 31 );

function zhdanov_cdata_page() {
	$all  = array_keys( (array) get_option( 'zh_concepts', array() ) );
	$slug = isset( $_GET['slug'] ) ? sanitize_title( wp_unslash( $_GET['slug'] ) ) : zhdanov_concept_current(); // phpcs:ignore
	if ( ! in_array( $slug, $all, true ) && $all ) {
		$slug = $all[0];
	}
	wp_enqueue_media();
	wp_enqueue_script( 'zhdanov-cdata', ZHDANOV_CORE_URL . 'assets/concept/data.js', array( 'wp-api-fetch' ), ZHDANOV_CORE_VERSION, true );
	wp_localize_script( 'zhdanov-cdata', 'zhCdata', array( 'slug' => $slug, 'base' => zhdanov_concept_base_url( $slug ), 'home' => home_url( '/' ) ) );
	?>
	<div class="wrap">
		<h1>Данные концепта</h1>
		<?php if ( count( $all ) > 1 ) : ?>
			<p>Концепт: <?php foreach ( $all as $s ) : ?><a class="button<?php echo $s === $slug ? ' button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'slug', $s ) ); ?>"><?php echo esc_html( $s ); ?></a> <?php endforeach; ?></p>
		<?php endif; ?>
		<p style="max-width:760px">Здесь списки, которые дизайнер записал внутри скриптов сайта: события, расписание, цены, часы работы. Измените строку, добавьте новую кнопкой «Добавить строку» или удалите лишнюю, затем нажмите «Сохранить». Сайт обновится сразу.</p>
		<div id="zh-cdata-app"><p>Загрузка…</p></div>
	</div>
	<style>
	.zh-cd-list{background:#fff;border:1px solid #dcdcde;border-radius:4px;margin:16px 0;max-width:1200px}
	.zh-cd-list>h2{margin:0;padding:12px 16px;border-bottom:1px solid #f0f0f1;font-size:15px;display:flex;gap:10px;align-items:center}
	.zh-cd-list>h2 small{color:#646970;font-weight:400}
	.zh-cd-hint{margin:0;padding:8px 16px;background:#f6f7f7;color:#50575e;white-space:pre-line;font-size:12px}
	.zh-cd-rows{padding:8px 16px 16px}
	.zh-cd-row{border:1px solid #e0e0e0;border-radius:4px;padding:10px 12px;margin:10px 0;position:relative}
	.zh-cd-row>.zh-cd-tools{position:absolute;top:8px;right:8px;display:flex;gap:4px}
	.zh-cd-row h3{margin:0 0 8px;font-size:13px}
	.zh-cd-f{display:grid;grid-template-columns:160px 1fr;gap:6px 12px;align-items:start;margin:6px 0}
	.zh-cd-f label{font-weight:600;padding-top:6px;word-break:break-word}
	.zh-cd-f input[type=text],.zh-cd-f textarea{width:100%}
	.zh-cd-f .zh-cd-img{display:flex;gap:8px;align-items:center}
	.zh-cd-f .zh-cd-img img{width:56px;height:56px;object-fit:cover;border-radius:4px;background:#f0f0f1}
	.zh-cd-photo img{width:96px;height:96px}
	.zh-cd-sub{border-left:3px solid #f0f0f1;padding-left:8px}
	.zh-cd-sub .zh-cd-li{display:flex;gap:6px;margin:4px 0}
	.zh-cd-bar{position:sticky;bottom:0;background:#fff;padding:10px 16px;border-top:1px solid #dcdcde;display:flex;gap:10px;align-items:center}
	</style>
	<?php
}

/* после переноса концепта сразу ищем данные в скриптах */
add_action( 'zhdanov_concept_imported', 'zhdanov_cdata_index' );
