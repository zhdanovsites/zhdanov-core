<?php
/**
 * Режим «Сайт готовится»: пока сайт собирают или переносят, посетители видят аккуратную заглушку
 * с контактами (ответ 503: поисковики понимают, что это временно), а вошедшие в админку видят сайт как есть.
 *
 * @package zhdanov-core
 */

defined( 'ABSPATH' ) || exit;

add_action( 'template_redirect', function () {
	if ( ! zhdanov_core_opt( 'maint_on' ) || current_user_can( 'edit_posts' ) || is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return;
	}
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore
	if ( preg_match( '#^/(wp-login\.php|wp-json/|api/lead|robots\.txt|wp-sitemap|favicon)#', wp_parse_url( $uri, PHP_URL_PATH ) ?? '' ) || isset( $_GET['zh_login'] ) ) { // phpcs:ignore
		return;
	}
	$name  = get_bloginfo( 'name' );
	$text  = trim( (string) zhdanov_core_opt( 'maint_text' ) );
	$phone = (string) zhdanov_core_opt( 'phone' );
	$tg    = (string) zhdanov_core_opt( 'telegram' );
	$max   = (string) zhdanov_core_opt( 'max' );
	$mail  = (string) zhdanov_core_opt( 'email' );
	$en    = function_exists( 'zhdanov_core_is_en' ) && zhdanov_core_is_en();
	$links = array();
	if ( $max ) {
		$links[] = '<a href="' . esc_url( $max ) . '">MAX</a>';
	}
	if ( $phone ) {
		$links[] = '<a href="tel:' . esc_attr( preg_replace( '/[^\d+]/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a>';
	}
	if ( $tg ) {
		$links[] = '<a href="' . esc_url( 0 === strpos( $tg, 'http' ) ? $tg : 'https://t.me/' . ltrim( $tg, '@' ) ) . '">Telegram</a>';
	}
	if ( $mail ) {
		$links[] = '<a href="mailto:' . esc_attr( $mail ) . '">' . esc_html( $mail ) . '</a>';
	}
	status_header( 503 );
	header( 'Retry-After: 3600' );
	nocache_headers();
	$icon = has_site_icon() ? '<img src="' . esc_url( get_site_icon_url( 96 ) ) . '" width="64" height="64" alt="">' : '';
	echo '<!doctype html><html lang="' . ( $en ? 'en' : 'ru' ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>' . esc_html( $name ) . '</title>';
	echo '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;font:17px/1.55 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f6f5f2;color:#1d1d1f}main{max-width:520px;padding:32px 24px;text-align:center}h1{font-size:26px;margin:16px 0 8px}p{margin:0 0 16px;color:#4a4a4f}nav{display:flex;flex-wrap:wrap;gap:10px;justify-content:center}nav a{display:inline-block;padding:10px 16px;border-radius:999px;background:#1d1d1f;color:#fff;text-decoration:none;min-height:24px}img{border-radius:16px}</style></head><body><main>';
	echo $icon . '<h1>' . esc_html( $name ) . '</h1><p>' . esc_html( $text ? $text : ( $en ? 'We are updating the website. Back very soon.' : 'Сайт обновляется. Скоро всё заработает.' ) ) . '</p>'; // phpcs:ignore
	if ( $links ) {
		echo '<p>' . esc_html( $en ? 'Contact us:' : 'Связаться с нами:' ) . '</p><nav>' . implode( '', $links ) . '</nav>'; // phpcs:ignore
	}
	echo '</main></body></html>';
	exit;
}, 1 );

add_action( 'admin_bar_menu', function ( $bar ) {
	if ( zhdanov_core_opt( 'maint_on' ) && current_user_can( 'edit_posts' ) ) {
		$bar->add_node( array( 'id' => 'zh-maint', 'title' => '⚠ Режим «Сайт готовится»: посетители видят заглушку', 'href' => admin_url( 'admin.php?page=zhdanov-core#client' ) ) );
	}
}, 100 );
