<?php
/**
 * Квиз: вопросы по одному, в конце форма заявки. Ответы уходят в текст заявки.
 * Вопросы: каждая строка «Вопрос? | вариант | вариант | …».
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
static $qn = 0;
++$qn;
$qs = array();
foreach ( preg_split( '/\r?\n/', (string) $attributes['questions'] ) as $line ) {
	$parts = array_values( array_filter( array_map( 'trim', explode( '|', $line ) ), 'strlen' ) );
	if ( count( $parts ) >= 3 ) {
		$qs[] = array( 'q' => array_shift( $parts ), 'a' => $parts );
	}
}
if ( ! $qs ) {
	return;
}
$total = count( $qs ) + 1;
echo '<div ' . get_block_wrapper_attributes( array( 'class' => 'zh-quiz', 'data-zh-quiz' => '1' ) ) . '>'; // phpcs:ignore
if ( $attributes['title'] ) {
	echo '<h2 class="zh-quiz__title">' . esc_html( zhdanov_core__( $attributes['title'] ) ) . '</h2>';
}
if ( $attributes['intro'] ) {
	echo '<p class="zh-quiz__intro">' . esc_html( zhdanov_core__( $attributes['intro'] ) ) . '</p>';
}
echo '<p class="zh-quiz__progress" aria-live="polite"><span class="zh-quiz__bar"><span style="width:' . round( 100 / $total ) . '%"></span></span> <span class="zh-quiz__count">1 / ' . (int) $total . '</span></p>';
foreach ( $qs as $i => $q ) {
	$id = 'zq' . $qn . '-' . $i;
	echo '<fieldset class="zh-quiz__step" data-step="' . (int) $i . '"' . ( $i ? ' hidden' : '' ) . '><legend class="zh-quiz__q">' . esc_html( $q['q'] ) . '</legend><div class="zh-quiz__opts">';
	foreach ( $q['a'] as $j => $a ) {
		printf( '<label class="zh-quiz__opt" for="%1$s-%2$d"><input type="radio" id="%1$s-%2$d" name="%1$s" value="%3$s"> <span>%3$s</span></label>', esc_attr( $id ), (int) $j, esc_attr( $a ) );
	}
	echo '</div>';
	if ( $i ) {
		echo '<button type="button" class="zh-linkbtn zh-quiz__back">' . esc_html( zhdanov_core__( '← Назад' ) ) . '</button>';
	}
	echo '</fieldset>';
}
echo '<div class="zh-quiz__step zh-quiz__final" data-step="' . count( $qs ) . '" hidden>';
echo render_block(
	array(
		'blockName' => 'zhdanov/form',
		'attrs'     => array( 'title' => $attributes['final'], 'button' => $attributes['button'], 'message' => true, 'email' => false, 'anchor' => 'kviz-' . $qn ),
		'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array(),
	)
); // phpcs:ignore
echo '<button type="button" class="zh-linkbtn zh-quiz__back">' . esc_html( zhdanov_core__( '← Назад' ) ) . '</button></div>';
echo '</div>';
