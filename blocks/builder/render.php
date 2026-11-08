<?php
/**
 * Конструктор: группы вариантов «Название: вариант цена | вариант +доплата». Цена без «+»: базовая цена (заменяет),
 * с «+»: доплата. Итог и текст заказа собираются на странице.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
static $bn = 0;
++$bn;
$groups = array();
foreach ( preg_split( '/\r?\n/', (string) $attributes['groups'] ) as $line ) {
	if ( false === strpos( $line, ':' ) ) {
		continue;
	}
	list( $name, $rest ) = array_map( 'trim', explode( ':', $line, 2 ) );
	$opts = array();
	foreach ( array_filter( array_map( 'trim', explode( '|', $rest ) ) ) as $o ) {
		$add = 0;
		$set = null;
		if ( preg_match( '/^(.*?)\s+(\+?)(\d[\d\s]*)\s*(?:₽|руб\.?)?$/u', $o, $m ) ) {
			$o = trim( $m[1] );
			$v = (int) preg_replace( '/\D/', '', $m[3] );
			if ( 0 === $v ) {
				$add = 0;
			} elseif ( '+' === $m[2] ) {
				$add = $v;
			} else {
				$set = $v;
			}
		}
		$opts[] = array( 'l' => $o, 'add' => $add, 'set' => $set );
	}
	if ( $name && $opts ) {
		$groups[] = array( 'n' => $name, 'o' => $opts );
	}
}
if ( ! $groups ) {
	return;
}
$fmt = function ( $o ) use ( $attributes ) {
	if ( null !== $o['set'] && $o['set'] ) {
		return number_format( $o['set'], 0, ',', ' ' ) . ' ' . $attributes['unit'];
	}
	return $o['add'] ? '+' . number_format( $o['add'], 0, ',', ' ' ) . ' ' . $attributes['unit'] : '';
};
echo '<div ' . get_block_wrapper_attributes( array( 'class' => 'zh-builder', 'data-zh-builder' => '1', 'data-base' => (int) $attributes['base'], 'data-unit' => $attributes['unit'] ) ) . '>'; // phpcs:ignore
echo '<h2 class="zh-builder__title">' . esc_html( zhdanov_core__( $attributes['title'] ) ) . '</h2>';
foreach ( $groups as $gi => $g ) {
	echo '<fieldset class="zh-builder__g"><legend>' . esc_html( $g['n'] ) . '</legend><div class="zh-builder__opts">';
	foreach ( $g['o'] as $oi => $o ) {
		printf(
			'<label class="zh-quiz__opt"><input type="radio" name="zhb%1$d-%2$d" value="%3$s" data-add="%4$d" data-set="%5$s"%6$s> <span>%3$s</span> <small>%7$s</small></label>',
			(int) $bn, (int) $gi, esc_attr( $o['l'] ), (int) $o['add'], null === $o['set'] ? '' : (int) $o['set'], 0 === $oi ? ' checked' : '', esc_html( $fmt( $o ) )
		);
	}
	echo '</div></fieldset>';
}
echo '<p class="zh-builder__sum" aria-live="polite">' . esc_html( zhdanov_core__( 'Итого' ) ) . ': <b class="zh-builder__total"></b></p>';
if ( $attributes['note'] ) {
	echo '<p class="zh-builder__note">' . esc_html( zhdanov_core__( $attributes['note'] ) ) . '</p>';
}
echo '<p>' . zhdanov_core_book_button( '', '', $attributes['button'] ? $attributes['button'] : 'Заказать' ) . '</p>'; // phpcs:ignore
echo '</div>';
