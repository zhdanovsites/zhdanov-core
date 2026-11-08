<?php
/**
 * Переключатель языка (Polylang). Без Polylang ничего не выводит.
 */
if ( ! defined( 'ABSPATH' ) || ! function_exists( 'pll_the_languages' ) ) {
	return;
}
$langs = pll_the_languages( array( 'raw' => 1, 'hide_if_empty' => 0 ) );
if ( ! $langs || count( $langs ) < 2 ) {
	return;
}
echo '<nav ' . get_block_wrapper_attributes( array( 'class' => 'zh-lang' ) ) . ' aria-label="Язык сайта"><ul role="list">'; // phpcs:ignore
foreach ( $langs as $l ) {
	printf(
		'<li><a href="%1$s" lang="%2$s" hreflang="%2$s"%3$s>%4$s</a></li>',
		esc_url( $l['url'] ),
		esc_attr( $l['locale'] ? str_replace( '_', '-', $l['locale'] ) : $l['slug'] ),
		$l['current_lang'] ? ' aria-current="true" class="is-active"' : '',
		esc_html( strtoupper( $l['slug'] ) )
	);
}
echo '</ul></nav>';
