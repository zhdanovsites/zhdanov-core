<?php
/**
 * «Помощь» в админке (как White Label CMS / WPMU DEV Integrated Tutorials): инструкции по шагам
 * из памятки клиенту (assets/help/pamyatka.md) с поиском, картинками и кнопкой «Написать в поддержку».
 * Плюс вкладка «Помощь» (?) на экранах правки страниц и записей.
 *
 * @package zhdanov-core
 */

defined( 'ABSPATH' ) || exit;

/** Памятка → [ [title, html], … ]. Простой разбор Markdown: заголовки ##, списки, жирный, код, картинки. */
function zhdanov_help_sections() {
	$md = (string) @file_get_contents( ZHDANOV_CORE_DIR . 'assets/help/pamyatka.md' ); // phpcs:ignore
	$md = (string) apply_filters( 'zhdanov_help_md', $md );
	$parts = preg_split( '/^## /m', $md );
	array_shift( $parts );
	$out = array();
	foreach ( $parts as $p ) {
		$lines = explode( "\n", $p );
		$title = trim( array_shift( $lines ) );
		$html  = '';
		$list  = '';
		foreach ( $lines as $l ) {
			$l = rtrim( $l );
			if ( preg_match( '/^(\d+)\.\s+(.*)$/u', $l, $m ) || preg_match( '/^-\s+(.*)$/u', $l, $m2 ) ) {
				$tag = isset( $m[2] ) && $m ? 'ol' : 'ul';
				if ( $list !== $tag ) {
					$html .= $list ? '</' . $list . '>' : '';
					$html .= '<' . $tag . '>';
					$list  = $tag;
				}
				$html .= '<li>' . zhdanov_help_inline( $m ? $m[2] : $m2[1] ) . '</li>';
				$m     = null;
				$m2    = null;
				continue;
			}
			$m = null;
			if ( $list ) {
				$html .= '</' . $list . '>';
				$list  = '';
			}
			if ( preg_match( '/^!\[([^\]]*)\]\(img\/([\w\-]+)\.png\)$/u', $l, $im ) ) {
				$f = 'assets/help/' . $im[2] . '.webp';
				if ( file_exists( ZHDANOV_CORE_DIR . $f ) ) {
					$html .= '<figure><img src="' . esc_url( ZHDANOV_CORE_URL . $f ) . '" alt="' . esc_attr( $im[1] ) . '" loading="lazy" width="1100"><figcaption>' . esc_html( $im[1] ) . '</figcaption></figure>';
				}
				continue;
			}
			if ( '' !== trim( $l ) ) {
				$html .= '<p>' . zhdanov_help_inline( $l ) . '</p>';
			}
		}
		$html .= $list ? '</' . $list . '>' : '';
		$out[] = array( preg_replace( '/^\d+\.\s*/u', '', $title ), $html );
	}
	return $out;
}

function zhdanov_help_inline( $s ) {
	$s = esc_html( $s );
	$s = preg_replace( '/\*\*(.+?)\*\*/u', '<b>$1</b>', $s );
	$s = preg_replace( '/`(.+?)`/u', '<code>$1</code>', $s );
	return $s;
}

/** Ссылка «Написать в поддержку» из поля «Поддержка: контакт» (Telegram @ник, телефон, почта, ссылка). */
function zhdanov_help_support_link() {
	$c = trim( (string) zhdanov_core_opt( 'support_contact' ) );
	if ( '' === $c ) {
		return array( 'https://zhdanovsites.com/', 'zhdanovsites.com' );
	}
	if ( preg_match( '~(https?://\S+)~', $c, $m ) ) {
		return array( $m[1], $c );
	}
	if ( preg_match( '/@([A-Za-z0-9_]{4,})/', $c, $m ) && ! is_email( $c ) ) {
		return array( 'https://t.me/' . $m[1], $c );
	}
	if ( is_email( $c ) ) {
		return array( 'mailto:' . $c, $c );
	}
	$d = preg_replace( '/\D/', '', $c );
	if ( strlen( $d ) >= 10 ) {
		return array( 'tel:+' . ( 11 === strlen( $d ) && '8' === $d[0] ? '7' . substr( $d, 1 ) : $d ), $c );
	}
	return array( '', $c );
}

add_action( 'admin_menu', function () {
	add_menu_page( 'Помощь', 'Помощь', 'edit_posts', 'zhdanov-help', 'zhdanov_help_page', 'dashicons-sos', 3 );
}, 40 );

function zhdanov_help_page() {
	$secs = zhdanov_help_sections();
	list( $href, $label ) = zhdanov_help_support_link();
	?>
	<div class="wrap zh-help">
		<h1>Помощь: как менять свой сайт</h1>
		<p>Короткие инструкции по шагам. Не нашли ответ: <?php echo $href ? '<a class="button button-primary" id="zh-help-support" href="' . esc_url( $href ) . '" target="_blank" rel="noopener">Написать в поддержку: ' . esc_html( $label ) . '</a>' : '<b>' . esc_html( $label ) . '</b>'; ?></p>
		<p><input type="search" id="zh-help-q" class="regular-text" placeholder="Поиск: фото, телефон, заявки…" aria-label="Поиск по инструкциям"></p>
		<?php foreach ( $secs as $i => $s ) : ?>
			<details class="zh-help-s" <?php echo 0 === $i ? 'open' : ''; ?>>
				<summary><?php echo esc_html( $s[0] ); ?></summary>
				<div class="zh-help-b"><?php echo $s[1]; // phpcs:ignore -- собрано из своей памятки с экранированием ?></div>
			</details>
		<?php endforeach; ?>
		<p id="zh-help-none" hidden>Ничего не нашлось. Напишите в поддержку, ответим.</p>
	</div>
	<style>
		.zh-help-s{background:#fff;border:1px solid #dcdcde;border-radius:6px;margin:0 0 10px;max-width:980px}
		.zh-help-s summary{cursor:pointer;padding:12px 16px;font-size:15px;font-weight:600}
		.zh-help-b{padding:0 16px 14px;font-size:14px;line-height:1.6}
		.zh-help-b figure{margin:10px 0}.zh-help-b img{max-width:100%;height:auto;border:1px solid #dcdcde;border-radius:4px}
		.zh-help-b figcaption{color:#646970;font-size:12px}
	</style>
	<script>
	(function(){var q=document.getElementById('zh-help-q'),s=[].slice.call(document.querySelectorAll('.zh-help-s')),n=document.getElementById('zh-help-none');
	q.addEventListener('input',function(){var v=q.value.trim().toLowerCase(),k=0;s.forEach(function(d){var hit=!v||d.textContent.toLowerCase().indexOf(v)>-1;d.hidden=!hit;if(hit){k++;if(v)d.open=true;}});n.hidden=k>0;});})();
	</script>
	<?php
}

/* Вкладка «Помощь» на экранах правки */
add_action( 'current_screen', function ( $screen ) {
	if ( ! $screen || ! in_array( $screen->base, array( 'post', 'edit' ), true ) ) {
		return;
	}
	$want = array( 'Исправить текст', 'Заменить фото', 'Добавить, убрать', 'Новая страница по образцу', 'Как сайт выглядит в поиске' );
	if ( 'edit' === $screen->base ) {
		$want = array( 'Новая страница по образцу', 'Заявки с сайта' );
	}
	foreach ( zhdanov_help_sections() as $i => $s ) {
		foreach ( $want as $w ) {
			if ( 0 === mb_strpos( $s[0], $w ) ) {
				$screen->add_help_tab( array( 'id' => 'zh-help-' . $i, 'title' => $s[0], 'content' => preg_replace( '#<figure>.*?</figure>#s', '', $s[1] ) ) );
			}
		}
	}
	$screen->set_help_sidebar( '<p><a href="' . esc_url( admin_url( 'admin.php?page=zhdanov-help' ) ) . '">Все инструкции</a></p>' );
} );

/* В памятке на первом экране: ссылка на «Помощь» */
add_action( 'wp_dashboard_setup', function () {
	global $wp_meta_boxes;
	if ( isset( $wp_meta_boxes['dashboard']['normal']['high']['zhdanov_memo'] ) ) {
		$cb = $wp_meta_boxes['dashboard']['normal']['high']['zhdanov_memo']['callback'];
		$wp_meta_boxes['dashboard']['normal']['high']['zhdanov_memo']['callback'] = function () use ( $cb ) {
			call_user_func( $cb );
			list( $href, $label ) = zhdanov_help_support_link();
			echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=zhdanov-help' ) ) . '">Все инструкции по шагам</a>' . ( $href ? ' <a class="button button-primary" href="' . esc_url( $href ) . '" target="_blank" rel="noopener">Написать в поддержку</a>' : '' ) . '</p>';
		};
	}
}, 20 );
