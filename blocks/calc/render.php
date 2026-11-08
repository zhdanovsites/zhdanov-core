<?php
/**
 * Калькулятор: строки полей «имя | подпись | от | до | по умолчанию | шаг», формула с этими именами.
 * Формула считается в браузере; допускаются только числа, имена полей, + - * / ( ) и min/max/round.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
static $kn = 0;
++$kn;
$fields = array();
foreach ( preg_split( '/\r?\n/', (string) $attributes['fields'] ) as $line ) {
	$c = array_map( 'trim', explode( '|', $line ) );
	if ( count( $c ) >= 5 && preg_match( '/^[a-z]\w*$/i', $c[0] ) ) {
		$fields[] = array( 'n' => $c[0], 'l' => $c[1], 'min' => (float) $c[2], 'max' => (float) $c[3], 'v' => (float) $c[4], 'step' => isset( $c[5] ) && '' !== $c[5] ? (float) $c[5] : 1 );
	}
}
$formula = preg_replace( '/[^\w\s+\-*\/().,]/u', '', (string) $attributes['formula'] );
if ( ! $fields || ! $formula ) {
	return;
}
echo '<div ' . get_block_wrapper_attributes( array( 'class' => 'zh-calc zh-builder', 'data-zh-calc' => $formula, 'data-unit' => $attributes['unit'] ) ) . '>'; // phpcs:ignore
echo '<h2 class="zh-builder__title">' . esc_html( zhdanov_core__( $attributes['title'] ) ) . '</h2>';
foreach ( $fields as $f ) {
	$id = 'zk' . $kn . '-' . $f['n'];
	printf( '<p class="zh-calc__f"><label for="%1$s">%2$s: <output for="%1$s">%3$s</output></label><input id="%1$s" type="range" name="%4$s" min="%5$s" max="%6$s" step="%7$s" value="%3$s"></p>', esc_attr( $id ), esc_html( $f['l'] ), esc_attr( $f['v'] ), esc_attr( $f['n'] ), esc_attr( $f['min'] ), esc_attr( $f['max'] ), esc_attr( $f['step'] ) );
}
echo '<p class="zh-builder__sum" aria-live="polite">' . esc_html( zhdanov_core__( 'Примерно' ) ) . ': <b class="zh-builder__total"></b></p>';
if ( $attributes['note'] ) {
	echo '<p class="zh-builder__note">' . esc_html( $attributes['note'] ) . '</p>';
}
echo '<p>' . zhdanov_core_book_button( '', '', $attributes['button'] ? $attributes['button'] : 'Отправить расчёт' ) . '</p></div>'; // phpcs:ignore
