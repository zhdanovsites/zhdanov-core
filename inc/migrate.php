<?php
/**
 * Переезд: 301-перенаправления (со старого сайта Tilda/Taplink/WordPress) и импорт из таблицы.
 * Жданов → Переезд.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'zhdanov-core', 'Переезд: адреса и таблицы', 'Переезд', 'manage_options', 'zhdanov-migrate', 'zhdanov_migrate_page' );
}, 23 );

/* ---------- 301 ---------- */

/** Путь без домена, без слэшей по краям, в нижнем регистре, без кодирования. */
function zhdanov_path( $u ) {
	$p = wp_parse_url( trim( $u ), PHP_URL_PATH );
	$q = wp_parse_url( trim( $u ), PHP_URL_QUERY );
	$p = trim( rawurldecode( (string) $p ), '/' );
	return mb_strtolower( $p . ( $q ? '?' . $q : '' ) );
}

/** Правила: [старый путь => новый адрес]. */
function zhdanov_redirects() {
	$out = array();
	foreach ( preg_split( '/\r?\n/', (string) get_option( 'zhdanov_redirects', '' ) ) as $line ) {
		$parts = preg_split( '/\s+/', trim( $line ) );
		if ( count( $parts ) >= 2 && '#' !== $parts[0][0] ) {
			$out[ zhdanov_path( $parts[0] ) ] = $parts[1];
		}
	}
	return $out;
}

add_action( 'template_redirect', function () {
	if ( ! is_404() && ! is_front_page() ) {
		return;
	}
	$req = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore
	$key = zhdanov_path( $req );
	if ( '' === $key ) {
		return;
	}
	$map = zhdanov_redirects();
	$to  = isset( $map[ $key ] ) ? $map[ $key ] : '';
	if ( ! $to && false !== strpos( $key, '?' ) ) {
		$k2 = strtok( $key, '?' );
		$to = isset( $map[ $k2 ] ) ? $map[ $k2 ] : '';
	}
	if ( $to ) {
		$to = 0 === strpos( $to, 'http' ) ? $to : home_url( '/' . ltrim( $to, '/' ) );
		// Счётчик срабатываний: видно, какие старые ссылки ещё живут.
		$hits         = (array) get_option( 'zhdanov_redirect_hits', array() );
		$hits[ $key ] = ( isset( $hits[ $key ] ) ? $hits[ $key ] : 0 ) + 1;
		update_option( 'zhdanov_redirect_hits', $hits, false );
		wp_redirect( $to, 301, 'Zhdanov' ); // phpcs:ignore
		exit;
	}
	// Журнал 404: последние 100 адресов, которых нет. Помогает дописать перенаправления.
	if ( is_404() ) {
		$log = (array) get_option( 'zhdanov_404', array() );
		$log[ $key ] = array( ( isset( $log[ $key ] ) ? $log[ $key ][0] : 0 ) + 1, current_time( 'Y-m-d H:i' ) );
		if ( count( $log ) > 100 ) {
			$log = array_slice( $log, -100, null, true );
		}
		update_option( 'zhdanov_404', $log, false );
	}
}, 1 );

/** Автоподбор: для каждого старого адреса ищем запись с похожим адресом или названием. */
function zhdanov_redirect_guess( $old_urls ) {
	$types = array_merge( array( 'page', 'post' ), array_keys( zhdanov_core_types() ), array( 'zh_event' ) );
	$posts = get_posts( array( 'post_type' => $types, 'post_status' => 'publish', 'numberposts' => 2000 ) );
	$index = array();
	foreach ( $posts as $p ) {
		$index[ $p->post_name ] = $p;
		$index[ zhdanov_core_translit( mb_strtolower( $p->post_title ) ) ] = $p;
	}
	$lines   = array();
	$fetched = 0;
	foreach ( $old_urls as $u ) {
		$path = zhdanov_path( $u );
		if ( '' === $path ) {
			continue;
		}
		$last = basename( strtok( $path, '?' ) );
		$last = preg_replace( '/\.(html?|php)$/', '', $last );
		$slug = sanitize_title( zhdanov_core_translit( $last ) );
		$hit  = isset( $index[ $slug ] ) ? $index[ $slug ] : null;
		if ( ! $hit ) {
			$best = 0;
			foreach ( $index as $k => $p ) { // ближайший по написанию (и по началу адреса: altai → altay-5-dney)
				similar_text( $slug, $k, $pc );
				if ( strlen( $slug ) >= 4 ) {
					similar_text( $slug, substr( $k, 0, strlen( $slug ) ), $pc2 );
					$pc = max( $pc, $pc2 - 5 );
				}
				if ( $pc > 72 && $pc > $best ) {
					$best = $pc;
					$hit  = $p;
				}
			}
		}
		$why = '';
		// Не нашли по адресу: открываем старую страницу и ищем новую с тем же названием (Tilda: /page12345.html)
		if ( ! $hit && preg_match( '#^https?://#i', trim( $u ) ) && $fetched < 80 ) {
			$fetched++;
			$title = zhdanov_old_title( trim( $u ) );
			if ( $title ) {
				$hit = zhdanov_match_title( $title, $posts );
				$why = $hit ? '   # по названию «' . $title . '»' : '   # не нашлось, старая страница: «' . $title . '»';
			}
		}
		$to      = $hit ? wp_make_link_relative( get_permalink( $hit ) ) : '/';
		$lines[] = '/' . $path . ' ' . $to . ( $hit ? $why : ( $why ? $why . ', ведём на главную' : '   # не нашлось: ведём на главную, поправьте' ) );
	}
	return $lines;
}

/** Название старой страницы: h1, иначе title без «| Название сайта». */
function zhdanov_old_title( $url ) {
	$r = wp_remote_get( $url, array( 'timeout' => 6, 'redirection' => 3, 'limit_response_size' => 400000 ) );
	if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		return '';
	}
	$h = wp_remote_retrieve_body( $r );
	$t = '';
	if ( preg_match( '#<h1[^>]*>(.*?)</h1>#is', $h, $m ) ) {
		$t = $m[1];
	}
	if ( '' === trim( wp_strip_all_tags( $t ) ) && preg_match( '#<title[^>]*>(.*?)</title>#is', $h, $m ) ) {
		$t = preg_split( '/\s+[|\x{2013}\x{2014}\-\x{00B7}]\s+/u', html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) )[0];
	}
	$t = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $t ), ENT_QUOTES, 'UTF-8' ) ) );
	return mb_substr( $t, 0, 120 );
}

function zhdanov_norm_title( $s ) {
	$s = mb_strtolower( str_replace( 'ё', 'е', (string) $s ) );
	return trim( preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $s ) );
}

/** Запись с самым похожим названием (точное совпадение, вхождение, похожесть больше 80%). */
function zhdanov_match_title( $title, $posts ) {
	$t    = zhdanov_norm_title( $title );
	$best = 0;
	$hit  = null;
	if ( mb_strlen( $t ) < 3 ) {
		return null;
	}
	foreach ( $posts as $p ) {
		$n = zhdanov_norm_title( $p->post_title );
		if ( '' === $n ) {
			continue;
		}
		if ( $n === $t ) {
			return $p;
		}
		$pc = 0;
		if ( mb_strlen( $n ) >= 5 && ( false !== mb_strpos( $t, $n ) || false !== mb_strpos( $n, $t ) ) ) {
			$pc = 85 + min( 10, 10 * min( mb_strlen( $n ), mb_strlen( $t ) ) / max( mb_strlen( $n ), mb_strlen( $t ) ) );
		} else {
			similar_text( $t, $n, $pc );
		}
		if ( $pc > 80 && $pc > $best ) {
			$best = $pc;
			$hit  = $p;
		}
	}
	return $hit;
}

/** Правила, которые ведут на несуществующие страницы своего сайта: [старый => новый]. */
function zhdanov_redirect_check() {
	$bad  = array();
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	foreach ( zhdanov_redirects() as $from => $to ) {
		if ( 0 === strpos( $to, 'http' ) && wp_parse_url( $to, PHP_URL_HOST ) !== $host ) {
			continue;
		}
		$path = trim( (string) wp_parse_url( $to, PHP_URL_PATH ), '/' );
		if ( '' === $path ) {
			continue;
		}
		$id = url_to_postid( home_url( '/' . $path . '/' ) );
		if ( $id && 'publish' === get_post_status( $id ) ) {
			continue;
		}
		$segs = explode( '/', $path );
		$ok   = false;
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
			$slug = is_string( $pt->has_archive ) ? $pt->has_archive : ( $pt->has_archive && ! empty( $pt->rewrite['slug'] ) ? $pt->rewrite['slug'] : '' );
			if ( $slug && trim( $slug, '/' ) === $path ) {
				$ok = true;
			}
		}
		if ( ! $ok && term_exists( sanitize_title( urldecode( end( $segs ) ) ) ) ) {
			$ok = true;
		}
		if ( ! $ok ) {
			$bad[ $from ] = $to;
		}
	}
	return $bad;
}

/** Адреса из sitemap.xml старого сайта (Tilda, Taplink, WordPress). */
function zhdanov_sitemap_urls( $url, $depth = 0 ) {
	$r = wp_remote_get( $url, array( 'timeout' => 15 ) );
	if ( is_wp_error( $r ) ) {
		return array();
	}
	preg_match_all( '#<loc>\s*([^<\s]+)\s*</loc>#i', wp_remote_retrieve_body( $r ), $m );
	$out = array();
	foreach ( $m[1] as $loc ) {
		$loc = html_entity_decode( $loc );
		if ( preg_match( '/\.xml(\?|$)/', $loc ) && $depth < 2 ) {
			$out = array_merge( $out, zhdanov_sitemap_urls( $loc, $depth + 1 ) );
		} else {
			$out[] = $loc;
		}
	}
	return array_slice( array_unique( $out ), 0, 1000 );
}

/* ---------- Импорт из таблицы (CSV) ---------- */

/** Поля, которые понимает импорт: [ключ => [названия колонок через |]]. */
function zhdanov_import_fields( $type ) {
	$f = array(
		'post_title'   => 'название|заголовок|title|name|наименование|блюдо|товар|услуга|событие',
		'post_content' => 'описание|текст|content|description|подробно',
		'post_excerpt' => 'кратко|анонс|excerpt|короткое описание|подзаголовок',
		'post_date'    => 'дата публикации|опубликовано|published',
		'_term'        => 'раздел|категория|рубрика|группа|вид|category|специальность',
		'_image'       => 'картинка|фото|изображение|image|photo|обложка',
		'post_name'    => 'адрес|slug|ссылка на странице',
	);
	if ( 'zh_event' === $type ) {
		$f += array(
			'_zh_start' => 'начало|дата|дата и время|start|когда', '_zh_end' => 'окончание|конец|end', '_zh_venue' => 'площадка|зал|место|venue',
			'_zh_address' => 'адрес площадки|адрес', '_zh_price' => 'цена|стоимость|price', '_zh_age' => 'возраст|age', '_zh_tickets' => 'билеты|ссылка на билеты|tickets',
			'_zh_status' => 'статус|status',
		);
	}
	$cfg = zhdanov_core_meta_config();
	if ( isset( $cfg[ $type ] ) ) {
		foreach ( $cfg[ $type ] as $k => $c ) {
			$label = mb_strtolower( preg_replace( '/\s*\(.*\)$/u', '', $c[0] ) );
			$f[ $k ] = isset( $f[ $k ] ) ? $f[ $k ] : $label . '|' . ltrim( str_replace( '_zh_', '', $k ), '_' );
		}
	}
	return $f;
}

/** Читаем CSV (запятая или точка с запятой, UTF-8 или Windows-1251). */
function zhdanov_csv_read( $file ) {
	$raw = file_get_contents( $file ); // phpcs:ignore
	if ( ! $raw ) {
		return array();
	}
	if ( ! mb_check_encoding( $raw, 'UTF-8' ) ) {
		$raw = mb_convert_encoding( $raw, 'UTF-8', 'Windows-1251' );
	}
	$raw   = preg_replace( '/^\xEF\xBB\xBF/', '', $raw );
	$first = strtok( $raw, "\n" );
	$sep   = substr_count( $first, ';' ) > substr_count( $first, ',' ) ? ';' : ( substr_count( $first, "\t" ) > substr_count( $first, ',' ) ? "\t" : ',' );
	$h     = fopen( 'php://temp', 'r+' ); // phpcs:ignore
	fwrite( $h, $raw ); // phpcs:ignore
	rewind( $h );
	$rows = array();
	while ( ( $r = fgetcsv( $h, 0, $sep, '"', '\\' ) ) !== false ) {
		if ( array_filter( $r, 'strlen' ) ) {
			$rows[] = array_map( 'trim', $r );
		}
	}
	fclose( $h ); // phpcs:ignore
	return $rows;
}

/** Заголовок колонки → поле. */
function zhdanov_import_map( $header, $type ) {
	$fields = zhdanov_import_fields( $type );
	$map    = array();
	foreach ( $header as $i => $col ) {
		$c = mb_strtolower( trim( $col ) );
		foreach ( $fields as $k => $names ) {
			if ( in_array( $c, explode( '|', $names ), true ) && ! in_array( $k, $map, true ) ) {
				$map[ $i ] = $k;
				break;
			}
		}
	}
	return $map;
}

/** Дата из «20.11.2026 19:00», «2026-11-20 19:00», «20.11.26». */
function zhdanov_import_date( $s ) {
	$s = trim( $s );
	if ( preg_match( '/^(\d{1,2})[.\/](\d{1,2})[.\/](\d{2,4})(?:[ T,]+(\d{1,2})[:.](\d{2}))?/', $s, $m ) ) {
		$y = strlen( $m[3] ) === 2 ? '20' . $m[3] : $m[3];
		return sprintf( '%04d-%02d-%02dT%02d:%02d', $y, $m[2], $m[1], isset( $m[4] ) ? $m[4] : 0, isset( $m[5] ) ? $m[5] : 0 );
	}
	if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{1,2}):(\d{2}))?/', $s, $m ) ) {
		return sprintf( '%s-%s-%sT%02d:%02d', $m[1], $m[2], $m[3], isset( $m[4] ) ? $m[4] : 0, isset( $m[5] ) ? $m[5] : 0 );
	}
	return $s;
}

function zhdanov_import_rows( $rows, $type, $dry = true ) {
	$header = array_shift( $rows );
	$map    = zhdanov_import_map( $header, $type );
	$tax    = 'zh_event' === $type ? 'zh_event_cat' : ( 'post' === $type ? 'category' : '' );
	$types  = zhdanov_core_types();
	if ( isset( $types[ $type ]['tax'][0] ) ) {
		$tax = $types[ $type ]['tax'][0];
	}
	$status = array_flip( array( 'scheduled' => 'по плану', 'soldout' => 'билеты проданы', 'postponed' => 'перенесено', 'cancelled' => 'отменено' ) );
	$report = array( 'map' => array(), 'made' => 0, 'skipped' => 0, 'preview' => array() );
	foreach ( $map as $i => $k ) {
		$report['map'][ $header[ $i ] ] = $k;
	}
	if ( ! in_array( 'post_title', $map, true ) ) {
		$report['error'] = 'Не нашёл колонку с названием. Назовите первую колонку «Название».';
		return $report;
	}
	foreach ( $rows as $n => $r ) {
		$d = array();
		foreach ( $map as $i => $k ) {
			$d[ $k ] = isset( $r[ $i ] ) ? $r[ $i ] : '';
		}
		if ( '' === $d['post_title'] ) {
			$report['skipped']++;
			continue;
		}
		if ( $n < 5 ) {
			$report['preview'][] = $d;
		}
		if ( $dry ) {
			continue;
		}
		// Повторный импорт той же таблицы не создаёт дублей: ищем по названию.
		$exists = get_posts( array( 'post_type' => $type, 'title' => $d['post_title'], 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
		$arr    = array(
			'post_type'    => $type,
			'post_status'  => 'publish',
			'post_title'   => $d['post_title'],
			'post_content' => isset( $d['post_content'] ) ? wp_kses_post( wpautop( $d['post_content'] ) ) : '',
			'post_excerpt' => isset( $d['post_excerpt'] ) ? $d['post_excerpt'] : '',
			'menu_order'   => $n,
		);
		if ( ! empty( $d['post_date'] ) ) {
			$arr['post_date'] = str_replace( 'T', ' ', zhdanov_import_date( $d['post_date'] ) ) . ':00';
		}
		if ( ! empty( $d['post_name'] ) ) {
			$arr['post_name'] = sanitize_title( $d['post_name'] );
		}
		if ( $exists ) {
			$arr['ID'] = $exists[0];
			$id        = wp_update_post( $arr );
		} else {
			$id = wp_insert_post( $arr );
		}
		if ( ! $id || is_wp_error( $id ) ) {
			continue;
		}
		foreach ( $d as $k => $v ) {
			if ( 0 !== strpos( $k, '_zh_' ) ) {
				continue;
			}
			if ( in_array( $k, array( '_zh_start', '_zh_end', '_zh_until' ), true ) ) {
				$v = zhdanov_import_date( $v );
			} elseif ( '_zh_date' === $k ) {
				$v = substr( zhdanov_import_date( $v ), 0, 10 );
			} elseif ( '_zh_status' === $k ) {
				$v = isset( $status[ mb_strtolower( $v ) ] ) ? $status[ mb_strtolower( $v ) ] : ( $v ? $v : 'scheduled' );
			}
			update_post_meta( $id, $k, sanitize_textarea_field( $v ) );
		}
		if ( $tax && ! empty( $d['_term'] ) && taxonomy_exists( $tax ) ) {
			wp_set_object_terms( $id, array_map( 'trim', explode( ',', $d['_term'] ) ), $tax );
		}
		if ( ! empty( $d['_image'] ) && preg_match( '#^https?://#', $d['_image'] ) && ! has_post_thumbnail( $id ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$att = media_sideload_image( $d['_image'], $id, $d['post_title'], 'id' );
			if ( ! is_wp_error( $att ) ) {
				set_post_thumbnail( $id, $att );
				update_post_meta( $att, '_wp_attachment_image_alt', $d['post_title'] );
			}
		}
		$report['made']++;
	}
	return $report;
}

/* ---------- Страница ---------- */
function zhdanov_migrate_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$note    = '';
	$guess   = array();
	$imp     = null;
	$type    = isset( $_POST['zh_type'] ) ? sanitize_key( $_POST['zh_type'] ) : 'zh_event'; // phpcs:ignore
	if ( isset( $_POST['zh_redirects'] ) && check_admin_referer( 'zhdanov_redirects' ) ) {
		update_option( 'zhdanov_redirects', sanitize_textarea_field( wp_unslash( $_POST['zh_redirects'] ) ), false );
		$note = 'Перенаправления сохранены: ' . count( zhdanov_redirects() ) . '.';
	}
	if ( isset( $_POST['zh_old'] ) && check_admin_referer( 'zhdanov_guess' ) ) {
		$old = trim( wp_unslash( $_POST['zh_old'] ) ); // phpcs:ignore
		$urls = preg_match( '#^https?://\S+\.xml$#', $old ) ? zhdanov_sitemap_urls( $old ) : preg_split( '/\s+/', $old );
		$guess = zhdanov_redirect_guess( array_filter( $urls ) );
		$note  = $guess ? 'Подобрал ' . count( $guess ) . ' адресов. Проверьте строки с пометкой # и нажмите «Сохранить».' : 'Не нашёл адресов. Вставьте их списком, по одному в строке.';
	}
	if ( ! empty( $_FILES['zh_csv']['tmp_name'] ) && check_admin_referer( 'zhdanov_import' ) ) {
		$rows = zhdanov_csv_read( $_FILES['zh_csv']['tmp_name'] ); // phpcs:ignore
		$dry  = empty( $_POST['zh_go'] );
		if ( ! $dry ) {
			// Импорт: файл из предпросмотра лежит во временной папке.
			$rows = zhdanov_csv_read( $_FILES['zh_csv']['tmp_name'] ); // phpcs:ignore
		}
		$imp = $rows ? zhdanov_import_rows( $rows, $type, $dry ) : array( 'error' => 'Файл пустой или не CSV.' );
		$imp['dry'] = $dry;
	}
	$types = array( 'zh_event' => 'Афиша', 'post' => 'Новости (записи)', 'page' => 'Страницы' );
	foreach ( zhdanov_core_types() as $k => $t ) {
		if ( zhdanov_core_type_on( $k ) ) {
			$types[ $k ] = zhdanov_core_type_label( $k );
		}
	}
	$text = (string) get_option( 'zhdanov_redirects', '' );
	if ( $guess ) {
		$text = trim( $text . "\n" . implode( "\n", $guess ) );
	}
	$hits = (array) get_option( 'zhdanov_redirect_hits', array() );
	$log  = array_reverse( (array) get_option( 'zhdanov_404', array() ), true );
	?>
	<div class="wrap">
		<h1>Переезд: адреса и таблицы</h1>
		<?php if ( $note ) : ?><div class="notice notice-info"><p><?php echo esc_html( $note ); ?></p></div><?php endif; ?>

		<h2>1. Старые адреса → новые (301)</h2>
		<p>Чтобы при переезде с Tilda, Taplink или старого WordPress не потерять позиции в Яндексе и ссылки из соцсетей. Вставьте ссылку на <b>sitemap.xml старого сайта</b> или список старых адресов: плагин сам подберёт новые страницы.</p>
		<form method="post"><?php wp_nonce_field( 'zhdanov_guess' ); ?>
			<textarea name="zh_old" rows="3" style="width:100%;max-width:980px" placeholder="https://old-site.ru/sitemap.xml или адреса по одному в строке"></textarea>
			<?php submit_button( 'Подобрать новые адреса', 'secondary', 'submit', false ); ?>
		</form>
		<form method="post"><?php wp_nonce_field( 'zhdanov_redirects' ); ?>
			<p>Строка: <code>/старый-адрес /новый-адрес/</code>. Строки с <code>#</code> в начале не работают (заметки).</p>
			<textarea name="zh_redirects" rows="10" style="width:100%;max-width:980px;font-family:monospace"><?php echo esc_textarea( $text ); ?></textarea>
			<?php submit_button( 'Сохранить перенаправления' ); ?>
		</form>
		<?php if ( $hits ) : ?>
			<p><b>Сработали:</b> <?php
			arsort( $hits );
			$x = array();
			foreach ( array_slice( $hits, 0, 10, true ) as $k => $n ) {
				$x[] = '/' . $k . ' (' . (int) $n . ')';
			}
			echo esc_html( implode( ', ', $x ) );
			?></p>
		<?php endif; ?>
		<?php $bad = zhdanov_redirect_check(); if ( $bad ) : ?>
			<div class="notice notice-warning inline" style="max-width:980px"><p><b>Ведут на страницы, которых нет (<?php echo count( $bad ); ?>):</b> поправьте новый адрес в строках выше.</p>
			<ul style="font-family:monospace;margin-left:1em"><?php foreach ( array_slice( $bad, 0, 30, true ) as $k => $v ) : ?><li>/<?php echo esc_html( $k ); ?> → <?php echo esc_html( $v ); ?></li><?php endforeach; ?></ul></div>
		<?php elseif ( zhdanov_redirects() ) : ?>
			<p style="color:#007017">✓ Все <?php echo count( zhdanov_redirects() ); ?> перенаправлений ведут на существующие страницы.</p>
		<?php endif; ?>
		<?php if ( $log ) : ?>
			<details><summary><b>Не найдено (404), последние адреса:</b> добавьте нужные в перенаправления</summary>
			<ul style="font-family:monospace"><?php foreach ( array_slice( $log, 0, 30, true ) as $k => $v ) : ?><li>/<?php echo esc_html( $k ); ?> · <?php echo (int) $v[0]; ?> раз · <?php echo esc_html( $v[1] ); ?></li><?php endforeach; ?></ul></details>
		<?php endif; ?>

		<h2 style="margin-top:2em">2. Импорт из таблицы</h2>
		<p>Афиша, каталог, меню, услуги, архив новостей из Google Таблицы или Excel: Файл → Скачать → CSV. Первая строка: названия колонок, например <code>Название; Дата; Площадка; Цена; Возраст; Билеты; Картинка</code>. Плагин сам поймёт колонки, сначала покажет, что получится. Повторная загрузка той же таблицы обновит записи, а не создаст копии.</p>
		<form method="post" enctype="multipart/form-data"><?php wp_nonce_field( 'zhdanov_import' ); ?>
			<p><label>Куда: <select name="zh_type"><?php foreach ( $types as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $type, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></label>
			<input type="file" name="zh_csv" accept=".csv,text/csv" required></p>
			<p><label><input type="checkbox" name="zh_go" value="1"> Сразу загрузить (без галочки: только предпросмотр)</label></p>
			<?php submit_button( 'Проверить таблицу', 'primary', 'submit', false ); ?>
		</form>
		<?php if ( $imp ) : ?>
			<div style="background:#fff;border:1px solid #dcdcde;padding:12px 16px;max-width:980px;margin-top:1em">
			<?php if ( ! empty( $imp['error'] ) ) : ?>
				<p style="color:#b32d2e"><?php echo esc_html( $imp['error'] ); ?></p>
			<?php else : ?>
				<p><b>Колонки:</b> <?php
				$x = array();
				foreach ( $imp['map'] as $col => $k ) {
					$x[] = $col . ' → ' . $k;
				}
				echo esc_html( implode( ', ', $x ) );
				?></p>
				<?php if ( $imp['dry'] ) : ?>
					<p>Первые строки:</p><ol><?php foreach ( $imp['preview'] as $p ) : ?><li><?php echo esc_html( implode( ' · ', array_filter( $p ) ) ); ?></li><?php endforeach; ?></ol>
					<p>Всё верно? Выберите файл ещё раз, поставьте галочку «Сразу загрузить» и нажмите кнопку.</p>
				<?php else : ?>
					<p style="color:#007017"><b>Готово:</b> создано или обновлено <?php echo (int) $imp['made']; ?>, пропущено пустых строк <?php echo (int) $imp['skipped']; ?>.</p>
				<?php endif; ?>
			<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
}
