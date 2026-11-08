<?php
/**
 * Блок «Поделиться».
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
$url   = get_permalink();
$title = wp_strip_all_tags( get_the_title() );
if ( ! $url ) {
	return;
}
$u  = rawurlencode( $url );
$t  = rawurlencode( $title );
$ru = 'ru' === zhdanov_core_opt( 'region' );
$links = array(
	'telegram' => array( 'Telegram', 'https://t.me/share/url?url=' . $u . '&text=' . $t, '#229ED9' ),
	'vk'       => array( 'ВКонтакте', 'https://vk.com/share.php?url=' . $u . '&title=' . $t, '#0077FF' ),
);
if ( ! $ru ) {
	$links['whatsapp'] = array( 'WhatsApp', 'https://wa.me/?text=' . $t . '%20' . $u, '#25D366' );
}
echo '<div ' . get_block_wrapper_attributes( array( 'class' => 'zh-share' ) ) . '><p class="zh-share__t">Поделиться:</p><ul role="list">'; // phpcs:ignore
foreach ( $links as $k => $l ) {
	printf( '<li class="zh-c zh-c--%1$s" style="--zh-brand:%4$s"><a href="%2$s" target="_blank" rel="noopener" data-zh-goal="share"><span class="zh-c__ico">%5$s</span><span class="screen-reader-text">%3$s</span></a></li>', esc_attr( $k ), esc_url( $l[1] ), esc_html( $l[0] ), esc_attr( $l[2] ), zhdanov_core_icon( $k, 20 ) ); // phpcs:ignore
}
printf( '<li><button type="button" class="zh-copy" data-url="%s">Скопировать ссылку</button></li>', esc_url( $url ) );
echo '</ul></div>';
