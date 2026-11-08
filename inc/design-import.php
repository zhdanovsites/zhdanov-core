<?php
/**
 * «Дизайн из концепта»: вставить HTML/CSS концепта (или ссылку на него) →
 * плагин находит цвета и шрифты, раскладывает по 8 цветам темы, проверяет контраст
 * и после «Применить» записывает в Стили. Ничего не меняет без подтверждения.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'zhdanov-core', 'Дизайн из концепта', 'Дизайн из концепта', 'manage_options', 'zhdanov-design', 'zhdanov_design_page' );
}, 22 );

/* ---------- цвета ---------- */
function zhdanov_hex_norm( $h ) {
	$h = strtolower( ltrim( trim( $h ), '#' ) );
	if ( 3 === strlen( $h ) ) {
		$h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
	}
	return preg_match( '/^[0-9a-f]{6}$/', $h ) ? '#' . $h : '';
}
function zhdanov_rgb( $hex ) {
	return array( hexdec( substr( $hex, 1, 2 ) ), hexdec( substr( $hex, 3, 2 ) ), hexdec( substr( $hex, 5, 2 ) ) );
}
function zhdanov_lum( $hex ) {
	$c = array_map(
		function ( $v ) {
			$v /= 255;
			return $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
		},
		zhdanov_rgb( $hex )
	);
	return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}
function zhdanov_contrast( $a, $b ) {
	$x = zhdanov_lum( $a );
	$y = zhdanov_lum( $b );
	return ( max( $x, $y ) + 0.05 ) / ( min( $x, $y ) + 0.05 );
}
function zhdanov_sat( $hex ) {
	list( $r, $g, $b ) = zhdanov_rgb( $hex );
	$mx = max( $r, $g, $b );
	$mn = min( $r, $g, $b );
	return $mx ? ( $mx - $mn ) / $mx : 0;
}
function zhdanov_mix( $a, $b, $t ) {
	$x = zhdanov_rgb( $a );
	$y = zhdanov_rgb( $b );
	return sprintf( '#%02x%02x%02x', round( $x[0] + ( $y[0] - $x[0] ) * $t ), round( $x[1] + ( $y[1] - $x[1] ) * $t ), round( $x[2] + ( $y[2] - $x[2] ) * $t ) );
}
/** Сдвигаем цвет к чёрному/белому, пока контраст с фоном не станет нужным. */
function zhdanov_fix_contrast( $fg, $bg, $need ) {
	$to = zhdanov_lum( $bg ) > 0.4 ? '#000000' : '#ffffff';
	for ( $t = 0; $t <= 1.001 && zhdanov_contrast( $fg, $bg ) < $need; $t += 0.05 ) {
		$fg = zhdanov_mix( $fg, $to, 0.05 );
	}
	return $fg;
}

/** Разбор концепта: цвета (с весом) и шрифты. */
function zhdanov_design_parse( $src ) {
	$colors = array();
	$vars   = array();
	// CSS-переменные :root { --bg: #fff } получают больший вес и подсказку по имени.
	if ( preg_match_all( '/--([a-z0-9_-]+)\s*:\s*(#[0-9a-f]{3,8})\b/i', $src, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $x ) {
			$h = zhdanov_hex_norm( substr( $x[2], 0, 7 ) );
			if ( $h ) {
				$vars[ strtolower( $x[1] ) ] = $h;
				$colors[ $h ]                = ( isset( $colors[ $h ] ) ? $colors[ $h ] : 0 ) + 5;
			}
		}
	}
	if ( preg_match_all( '/#([0-9a-f]{6}|[0-9a-f]{3})\b/i', $src, $m ) ) {
		foreach ( $m[0] as $x ) {
			$h = zhdanov_hex_norm( $x );
			if ( $h ) {
				$colors[ $h ] = ( isset( $colors[ $h ] ) ? $colors[ $h ] : 0 ) + 1;
			}
		}
	}
	if ( preg_match_all( '/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i', $src, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $x ) {
			$h            = sprintf( '#%02x%02x%02x', min( 255, $x[1] ), min( 255, $x[2] ), min( 255, $x[3] ) );
			$colors[ $h ] = ( isset( $colors[ $h ] ) ? $colors[ $h ] : 0 ) + 1;
		}
	}
	$fonts = array();
	// короткие имена шрифтов (font-family: C) → настоящее имя по файлу в @font-face (cormorant-garamond-latin-500.woff2)
	$alias = array();
	if ( preg_match_all( '/@font-face\s*\{([^}]*)\}/i', $src, $ff ) ) {
		foreach ( $ff[1] as $body ) {
			if ( preg_match( '/font-family\s*:\s*[\'"]?([^;\'"]+)/i', $body, $fn ) && preg_match( '/url\(\s*[\'"]?[^)\'"]*?([a-z][a-z0-9\-]+?)(?:-(?:latin|cyrillic|greek|vietnamese|ext)[^\/)\'"]*)?\.(?:woff2?|ttf|otf)/i', $body, $fu ) ) {
				$real = ucwords( str_replace( '-', ' ', preg_replace( '/-(\d{3}|regular|normal|italic|bold|variable|vf)(?=-|$).*$/i', '', $fu[1] ) ) );
				if ( strlen( trim( $fn[1] ) ) <= 3 && strlen( $real ) > 3 ) {
					$alias[ trim( $fn[1] ) ] = $real;
				}
			}
		}
	}
	if ( preg_match_all( '/font-family\s*:\s*([^;}{]+)/i', $src, $m ) ) {
		foreach ( $m[1] as $f ) {
			$first = trim( explode( ',', $f )[0], " \t\n'\"" );
			if ( isset( $alias[ $first ] ) ) {
				$first = $alias[ $first ];
			}
			if ( $first && ! preg_match( '/^(var\(|inherit|system-ui|sans-serif|serif|-apple-system|monospace)/i', $first ) ) {
				$fonts[ $first ] = ( isset( $fonts[ $first ] ) ? $fonts[ $first ] : 0 ) + 1;
			}
		}
	}
	if ( preg_match_all( '/family=([A-Za-z0-9+]+)/', $src, $m ) ) { // ссылки на Google Fonts в концепте
		foreach ( $m[1] as $f ) {
			$f           = str_replace( '+', ' ', $f );
			$fonts[ $f ] = ( isset( $fonts[ $f ] ) ? $fonts[ $f ] : 0 ) + 3;
		}
	}
	arsort( $colors );
	arsort( $fonts );
	return array( 'colors' => $colors, 'vars' => $vars, 'fonts' => $fonts );
}

/** Раскладка по 8 цветам темы. */
function zhdanov_design_palette( $p ) {
	$v    = $p['vars'];
	$pick = function ( $names ) use ( $v ) {
		foreach ( $v as $k => $h ) {
			foreach ( $names as $n ) {
				if ( preg_match( '/(^|-)' . $n . '($|-)/', $k ) ) {
					return $h;
				}
			}
		}
		return '';
	};
	$all  = array_keys( $p['colors'] );
	$calm = array_values( array_filter( $all, function ( $h ) {
		return zhdanov_sat( $h ) < 0.25;
	} ) );
	if ( count( $calm ) >= 2 ) {
		$all = $calm; // фон и текст ищем среди спокойных цветов, не среди ярких акцентов
	}
	usort( $all, function ( $a, $b ) {
		return zhdanov_lum( $b ) <=> zhdanov_lum( $a );
	} );
	$light = $all ? $all[0] : '#ffffff';
	$dark  = $all ? end( $all ) : '#111111';

	// короткие имена из концептов Alex 1: --ink, --ter, --mut, --ln, --bg2, --card, --ol
	$base     = $pick( array( 'bg', 'background', 'base', 'paper', 'surface', 'light', 'cream', 'sand' ) );
	$contrast = $pick( array( 'text', 'fg', 'ink', 'contrast', 'dark', 'foreground', 'tx', 'txt' ) );
	$accent   = $pick( array( 'accent', 'primary', 'brand', 'main', 'cta', 'ter', 'terra', 'acc', 'ac', 'gold' ) );
	$accent2  = $pick( array( 'accent-2', 'accent2', 'secondary', 'second', 'ol', 'olive', 'acc2' ) );
	$base2    = $pick( array( 'bg-2', 'bg2', 'surface-2', 'card', 'muted-bg', 'soft', 'paper2', 'bg-alt' ) );
	$muted    = $pick( array( 'muted', 'text-2', 'gray', 'grey', 'secondary-text', 'mut', 'mute', 'sub' ) );
	$line     = $pick( array( 'border', 'line', 'stroke', 'divider', 'ln', 'bd', 'rule' ) );

	// Самый частый фон: светлый или тёмный (тёмные концепты тоже бывают).
	if ( ! $base ) {
		$top  = array_slice( array_keys( $p['colors'] ), 0, 6 );
		$base = $light;
		foreach ( $top as $h ) {
			if ( zhdanov_lum( $h ) > 0.75 || zhdanov_lum( $h ) < 0.02 ) {
				$base = $h;
				break;
			}
		}
	}
	$is_dark = zhdanov_lum( $base ) < 0.2;
	if ( ! $contrast ) {
		$contrast = $is_dark ? $light : $dark;
	}
	if ( ! $accent ) {
		$best = 0;
		foreach ( $p['colors'] as $h => $w ) {
			$score = zhdanov_sat( $h ) * ( 1 + log( 1 + $w ) );
			if ( zhdanov_sat( $h ) > 0.25 && $score > $best ) {
				$best   = $score;
				$accent = $h;
			}
		}
		if ( ! $accent ) {
			$accent = $contrast;
		}
	}
	if ( ! $accent2 ) {
		$accent2 = zhdanov_mix( $accent, $base, 0.82 );
	}
	if ( ! $base2 ) {
		$base2 = zhdanov_mix( $base, $contrast, 0.05 );
	}
	if ( ! $muted ) {
		$muted = zhdanov_mix( $contrast, $base, 0.35 );
	}
	if ( ! $line ) {
		$line = zhdanov_mix( $base, $contrast, 0.15 );
	}
	// Контраст по WCAG AA: текст 4.5, вторичный текст 4.5, кнопка 4.5.
	$contrast = zhdanov_fix_contrast( $contrast, $base, 7 );
	$muted    = zhdanov_fix_contrast( $muted, $base, 4.6 );
	$ac       = zhdanov_contrast( '#ffffff', $accent ) >= zhdanov_contrast( '#111111', $accent ) ? '#ffffff' : '#111111';
	if ( zhdanov_contrast( $ac, $accent ) < 4.5 ) {
		$accent = zhdanov_fix_contrast( $accent, $ac, 4.6 );
	}
	return array(
		'base'            => array( $base, 'Фон' ),
		'base-2'          => array( $base2, 'Фон 2 (карточки, полосы)' ),
		'contrast'        => array( $contrast, 'Текст' ),
		'contrast-2'      => array( $muted, 'Текст второстепенный' ),
		'accent'          => array( $accent, 'Акцент (кнопки)' ),
		'accent-contrast' => array( $ac, 'Текст на кнопке' ),
		'accent-2'        => array( $accent2, 'Акцент светлый' ),
		'border'          => array( $line, 'Линии и рамки' ),
	);
}

/** Шрифты концепта → наборы темы. */
function zhdanov_design_fonts( $fonts ) {
	$known = array( 'cormorant garamond' => 'heading', 'cormorant' => 'heading', 'unbounded' => 'display', 'manrope' => 'body' );
	$out   = array( 'heading' => '', 'body' => '', 'missing' => array() );
	$i     = 0;
	foreach ( array_keys( $fonts ) as $f ) {
		$k = strtolower( $f );
		if ( isset( $known[ $k ] ) ) {
			if ( 'body' === $known[ $k ] ) {
				$out['body'] = $out['body'] ? $out['body'] : 'body';
			} elseif ( ! $out['heading'] ) {
				$out['heading'] = $known[ $k ];
			}
		} elseif ( $i < 2 ) {
			$out['missing'][] = $f;
		}
		$i++;
	}
	return $out;
}

add_action( 'admin_post_zhdanov_design_apply', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'zhdanov_design_apply' ) ) {
		wp_die( 'Нет доступа.' );
	}
	$pal = isset( $_POST['pal'] ) ? (array) wp_unslash( $_POST['pal'] ) : array(); // phpcs:ignore
	$names = zhdanov_design_palette( array( 'colors' => array(), 'vars' => array(), 'fonts' => array() ) );
	$list  = array();
	foreach ( $names as $slug => $def ) {
		$h = isset( $pal[ $slug ] ) ? zhdanov_hex_norm( $pal[ $slug ] ) : '';
		if ( $h ) {
			$list[] = array( 'slug' => $slug, 'color' => $h, 'name' => $def[1] );
		}
	}
	$gs = zhdanov_core_styles_post_id();
	if ( $gs && count( $list ) === 8 ) {
		$cur = json_decode( get_post_field( 'post_content', $gs ), true );
		if ( ! is_array( $cur ) ) {
			$cur = array( 'version' => 3, 'isGlobalStylesUserThemeJSON' => true );
		}
		// Сохраняем прошлую версию стилей, чтобы можно было откатить.
		update_option( 'zhdanov_design_backup', get_post_field( 'post_content', $gs ), false );
		$cur['settings']['color']['palette']['theme'] = $list;
		$heading = isset( $_POST['heading'] ) ? sanitize_key( $_POST['heading'] ) : ''; // phpcs:ignore
		if ( in_array( $heading, array( 'heading', 'display', 'body' ), true ) ) {
			$cur['styles']['elements']['heading']['typography']['fontFamily'] = 'var:preset|font-family|' . $heading;
			$cur['styles']['blocks']['core/site-title']['typography']['fontFamily'] = 'var:preset|font-family|' . $heading;
		}
		wp_update_post( array( 'ID' => $gs, 'post_content' => wp_slash( wp_json_encode( $cur, JSON_UNESCAPED_UNICODE ) ) ) );
		$msg = 'ok';
	} else {
		$msg = 'fail';
	}
	wp_safe_redirect( admin_url( 'admin.php?page=zhdanov-design&done=' . $msg ) );
	exit;
} );

add_action( 'admin_post_zhdanov_design_undo', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'zhdanov_design_undo' ) ) {
		wp_die( 'Нет доступа.' );
	}
	$b  = get_option( 'zhdanov_design_backup' );
	$gs = zhdanov_core_styles_post_id();
	if ( $b && $gs ) {
		wp_update_post( array( 'ID' => $gs, 'post_content' => wp_slash( $b ) ) );
		delete_option( 'zhdanov_design_backup' );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=zhdanov-design&done=undo' ) );
	exit;
} );

function zhdanov_design_page() {
	$src = '';
	$err = '';
	if ( isset( $_POST['zh_src'] ) && check_admin_referer( 'zhdanov_design_parse' ) ) { // phpcs:ignore
		$src = wp_unslash( $_POST['zh_src'] ); // phpcs:ignore
		if ( preg_match( '#^https?://\S+$#', trim( $src ) ) ) {
			$r   = wp_remote_get( trim( $src ), array( 'timeout' => 15 ) );
			$src = is_wp_error( $r ) ? '' : wp_remote_retrieve_body( $r );
			if ( ! $src ) {
				$err = 'Не получилось открыть ссылку. Откройте концепт в браузере, нажмите Ctrl+U (код страницы), скопируйте всё и вставьте сюда.';
			} elseif ( preg_match_all( '/<link[^>]+href="([^"]+\.css[^"]*)"/i', $src, $lm ) ) { // подтягиваем внешние CSS
				foreach ( array_slice( $lm[1], 0, 4 ) as $css ) {
					$css = 0 === strpos( $css, 'http' ) ? $css : trailingslashit( trim( wp_unslash( $_POST['zh_src'] ) ) ) . ltrim( $css, '/' ); // phpcs:ignore
					$cr  = wp_remote_get( $css, array( 'timeout' => 10 ) );
					if ( ! is_wp_error( $cr ) ) {
						$src .= "\n" . wp_remote_retrieve_body( $cr );
					}
				}
			}
		}
	}
	$done = isset( $_GET['done'] ) ? sanitize_key( $_GET['done'] ) : ''; // phpcs:ignore
	?>
	<div class="wrap">
		<h1>Дизайн из концепта</h1>
		<?php if ( 'ok' === $done ) : ?><div class="notice notice-success"><p>Цвета применены. Посмотрите сайт; шрифты и мелочи доводятся во «Внешний вид → Редактор → Стили».</p></div><?php endif; ?>
		<?php if ( 'undo' === $done ) : ?><div class="notice notice-success"><p>Вернули прежние стили.</p></div><?php endif; ?>
		<?php if ( 'fail' === $done ) : ?><div class="notice notice-error"><p>Не получилось сохранить. Включена ли «Тема Жданов»?</p></div><?php endif; ?>
		<?php if ( $err ) : ?><div class="notice notice-error"><p><?php echo esc_html( $err ); ?></p></div><?php endif; ?>
		<p>Вставьте ссылку на концепт (например, на Cloudflare) или весь его HTML/CSS. Плагин найдёт цвета и шрифты, разложит их по 8 цветам темы и проверит, что текст читается (WCAG AA). Ничего не меняется, пока вы не нажмёте «Применить».</p>
		<form method="post">
			<?php wp_nonce_field( 'zhdanov_design_parse' ); ?>
			<textarea name="zh_src" rows="6" style="width:100%;max-width:980px;font-family:monospace" placeholder="https://… или <html>…"><?php echo esc_textarea( mb_substr( $src, 0, 200 ) === $src ? $src : '' ); ?></textarea>
			<?php submit_button( 'Найти цвета и шрифты', 'primary', 'submit', false ); ?>
		</form>
		<?php
		if ( $src && ! $err ) :
			$parsed = zhdanov_design_parse( $src );
			$pal    = zhdanov_design_palette( $parsed );
			$fonts  = zhdanov_design_fonts( $parsed['fonts'] );
			?>
			<h2>Что нашлось</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="zhdanov_design_apply"><?php wp_nonce_field( 'zhdanov_design_apply' ); ?>
				<table class="widefat" style="max-width:720px"><tbody>
				<?php foreach ( $pal as $slug => $c ) : ?>
					<tr><td style="width:44px"><span style="display:block;width:36px;height:36px;border-radius:6px;border:1px solid #ccc;background:<?php echo esc_attr( $c[0] ); ?>"></span></td>
						<td><b><?php echo esc_html( $c[1] ); ?></b></td>
						<td><input type="text" name="pal[<?php echo esc_attr( $slug ); ?>]" value="<?php echo esc_attr( $c[0] ); ?>" size="9" pattern="#[0-9a-fA-F]{6}"></td></tr>
				<?php endforeach; ?>
				</tbody></table>
				<?php
				$cr = zhdanov_contrast( $pal['contrast'][0], $pal['base'][0] );
				$cb = zhdanov_contrast( $pal['accent-contrast'][0], $pal['accent'][0] );
				?>
				<p>Контраст текста: <b><?php echo esc_html( number_format( $cr, 1, ',', '' ) ); ?>:1</b>, кнопки: <b><?php echo esc_html( number_format( $cb, 1, ',', '' ) ); ?>:1</b> (нужно не меньше 4,5:1; если было меньше, цвет чуть подправлен).</p>
				<p>Шрифты в концепте: <?php echo esc_html( $parsed['fonts'] ? implode( ', ', array_slice( array_keys( $parsed['fonts'] ), 0, 4 ) ) : 'не найдены' ); ?>.</p>
				<p><label>Заголовки шрифтом:
					<select name="heading">
						<option value="">не менять</option>
						<option value="heading" <?php selected( $fonts['heading'], 'heading' ); ?>>Cormorant Garamond (классика)</option>
						<option value="display" <?php selected( $fonts['heading'], 'display' ); ?>>Unbounded (современный)</option>
						<option value="body">Manrope (как текст)</option>
					</select></label></p>
				<?php if ( $fonts['missing'] ) : ?>
					<p>Шрифтов <b><?php echo esc_html( implode( ', ', $fonts['missing'] ) ); ?></b> в теме нет: загрузите их во «Внешний вид → Редактор → Стили → Типографика → Управление шрифтами» (они сохранятся на сервере сайта, без Google Fonts) и выберите для заголовков или текста.</p>
				<?php endif; ?>
				<?php submit_button( 'Применить цвета к сайту' ); ?>
			</form>
		<?php endif; ?>
		<?php if ( get_option( 'zhdanov_design_backup' ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="zhdanov_design_undo"><?php wp_nonce_field( 'zhdanov_design_undo' ); ?>
				<?php submit_button( 'Вернуть прежние стили', 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>
	</div>
	<?php
}
