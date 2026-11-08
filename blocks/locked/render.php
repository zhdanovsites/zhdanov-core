<?php
/**
 * Закрытая часть: ученику показываем содержимое, остальным замок, тарифы и поле для кода.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
$cid = (int) $attributes['courseId'] ? (int) $attributes['courseId'] : (int) get_the_ID();
if ( zhdanov_access_has( $cid ) || current_user_can( 'edit_post', $cid ) ) {
	echo '<div ' . get_block_wrapper_attributes( array( 'class' => 'zh-locked is-open' ) ) . '>' . $content . '</div>'; // phpcs:ignore
	return;
}
$h  = '<div ' . get_block_wrapper_attributes( array( 'class' => 'zh-locked' ) ) . '><p class="zh-locked__title">🔒 ' . esc_html( zhdanov_core__( 'Закрытые материалы' ) ) . '</p>';
$h .= '<p>' . esc_html( zhdanov_core__( $attributes['note'] ) ) . '</p>';
if ( 'zh_course' === get_post_type( $cid ) && function_exists( 'zhdanov_pay_button_html' ) ) {
	foreach ( zhdanov_core_course_tariffs( $cid ) as $i => $t ) {
		$b = zhdanov_pay_button_html( $cid, 't' . $i, zhdanov_pay_amount( $t['price'] ), get_the_title( $cid ) . ', ' . $t['name'] );
		$h .= $b ? '<p>' . esc_html( $t['name'] . ': ' . $t['price'] ) . ' ' . $b . '</p>' : '';
	}
}
if ( zhdanov_core_m( $cid, '_zh_access' ) ) {
	$h .= '<form class="zh-locked__code" method="post"><label for="zhl-' . (int) $cid . '">' . esc_html( zhdanov_core__( 'Есть код доступа?' ) ) . '</label> <input id="zhl-' . (int) $cid . '" name="zh_code" autocomplete="off"> <input type="hidden" name="zh_lc" value="' . (int) $cid . '">' . wp_nonce_field( 'zh_code', '_zhcode', true, false ) . '<button type="submit" class="zh-btn-ghost">' . esc_html( zhdanov_core__( 'Открыть' ) ) . '</button></form>';
}
if ( ! empty( $GLOBALS['zh_code_wrong'] ) ) {
	$h .= '<p class="zh-form__status is-error">' . esc_html( zhdanov_core__( 'Код не подошёл.' ) ) . '</p>';
}
echo $h . '<p class="zh-locked__help">' . esc_html( zhdanov_core__( 'Уже оплатили? Откройте ссылку из письма на этом устройстве.' ) ) . '</p></div>'; // phpcs:ignore
