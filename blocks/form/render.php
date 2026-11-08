<?php
/**
 * Блок «Форма заявки».
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
static $n = 0;
++$n;
$uid = 'zhf' . $n;
$pp  = (int) zhdanov_core_opt( 'privacy_page' );
if ( ! $pp ) {
	$pp = (int) get_option( 'wp_page_for_privacy_policy' );
}
$policy = $pp && 'publish' === get_post_status( $pp ) ? get_permalink( $pp ) : '';
$state  = isset( $_GET['zayavka'] ) ? sanitize_key( $_GET['zayavka'] ) : ''; // phpcs:ignore
$anchor = empty( $attributes['anchor'] ) ? 'zayavka' : $attributes['anchor'];
$cap    = zhdanov_core_opt( 'captcha_client' );

echo '<div ' . get_block_wrapper_attributes( array( 'class' => 'zh-form-wrap', 'id' => esc_attr( $anchor ) ) ) . '>'; // phpcs:ignore
if ( ! empty( $attributes['title'] ) ) {
	echo '<h2 class="zh-form__title">' . esc_html( $attributes['title'] ) . '</h2>';
}
printf(
	'<form class="zh-form" method="post" action="%1$s" data-rest="%2$s" novalidate>',
	esc_url( admin_url( 'admin-post.php' ) ),
	esc_url( rest_url( 'zhdanov/v1/lead' ) )
);
echo '<input type="hidden" name="action" value="zh_lead"><input type="hidden" name="zh_t" value="' . time() . '"><input type="hidden" name="page" value="">';
echo '<p class="zh-hp" aria-hidden="true"><label>Сайт<input type="text" name="website" tabindex="-1" autocomplete="off"></label></p>';
printf( '<p class="zh-field"><label for="%1$s-name">Имя</label><input id="%1$s-name" name="name" type="text" autocomplete="name" required></p>', esc_attr( $uid ) );
printf( '<p class="zh-field"><label for="%1$s-phone">Телефон</label><input id="%1$s-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" required placeholder="+7"></p>', esc_attr( $uid ) );
if ( ! empty( $attributes['email'] ) ) {
	printf( '<p class="zh-field"><label for="%1$s-email">Почта <span class="zh-opt">(необязательно)</span></label><input id="%1$s-email" name="email" type="email" autocomplete="email"></p>', esc_attr( $uid ) );
}
if ( ! empty( $attributes['message'] ) ) {
	printf( '<p class="zh-field zh-field--wide"><label for="%1$s-msg">Сообщение <span class="zh-opt">(необязательно)</span></label><textarea id="%1$s-msg" name="message" rows="3"></textarea></p>', esc_attr( $uid ) );
}
printf(
	'<p class="zh-consent"><input id="%1$s-ok" name="consent" type="checkbox" value="1" required><label for="%1$s-ok">Я согласен(на) на обработку персональных данных%2$s</label></p>',
	esc_attr( $uid ),
	$policy ? ' в соответствии с <a href="' . esc_url( $policy ) . '" target="_blank">политикой конфиденциальности</a>' : ''
);
if ( $cap ) {
	echo '<div class="smart-captcha zh-captcha" data-sitekey="' . esc_attr( $cap ) . '" data-hl="ru"></div>';
}
echo '<p class="zh-form__submit"><button type="submit" class="wp-element-button">' . esc_html( $attributes['button'] ? $attributes['button'] : 'Отправить' ) . '</button></p>';
$msg = '';
if ( 'ok' === $state ) {
	$msg = zhdanov_core_opt( 'form_done' );
} elseif ( 'error' === $state ) {
	$msg = 'Не получилось отправить. Позвоните нам или напишите в мессенджер.';
}
echo '<p class="zh-form__status' . ( 'ok' === $state ? ' is-ok' : ( $state ? ' is-error' : '' ) ) . '" role="status" aria-live="polite">' . esc_html( $msg ) . '</p>';
echo '</form>';
$c = zhdanov_core_contacts();
$alt = array_intersect_key( $c, array_flip( array( 'max', 'telegram', 'whatsapp', 'phone' ) ) );
if ( $alt ) {
	echo '<p class="zh-form__alt">Или напишите сразу: ';
	$links = array();
	foreach ( $alt as $k => $it ) {
		$links[] = '<a href="' . esc_url( $it['url'] ) . '"' . ( 0 === strpos( $it['url'], 'http' ) ? ' target="_blank" rel="noopener"' : '' ) . ' data-zh-goal="' . esc_attr( zhdanov_core_goal_for( $k ) ) . '">' . esc_html( $it['label'] ) . '</a>';
	}
	echo implode( ' · ', $links ) . '</p>'; // phpcs:ignore
}
echo '</div>';
