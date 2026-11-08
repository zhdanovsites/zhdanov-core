<?php
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
$ch = $attributes['channel'] ? $attributes['channel'] : zhdanov_core_opt( 'telegram' );
$ch = preg_replace( '#^(https?://)?(t\.me/)?(s/)?@?#', '', trim( (string) $ch ) );
if ( ! $ch ) {
	if ( wp_is_serving_rest_request() ) {
		echo '<p>Укажите публичный канал справа (например, zhdanovsites) или Telegram в настройках «Жданов».</p>';
	}
	return;
}
$posts = zhdanov_tg_feed( $ch, max( 1, (int) $attributes['count'] ) );
echo '<div ' . get_block_wrapper_attributes( array( 'class' => 'zh-tgfeed' ) ) . '>'; // phpcs:ignore
if ( $attributes['title'] ) {
	echo '<h2 class="zh-tgfeed__title">' . esc_html( zhdanov_core__( $attributes['title'] ) ) . '</h2>';
}
if ( $posts ) {
	echo '<ul class="zh-tgfeed__list" role="list">';
	foreach ( $posts as $p ) {
		echo '<li class="zh-tgfeed__it"><time datetime="' . esc_attr( $p['date'] ) . '">' . esc_html( wp_date( 'j F', strtotime( $p['date'] ) ) ) . '</time><p>' . nl2br( esc_html( wp_trim_words( $p['text'], 40, '…' ) ) ) . '</p><a href="' . esc_url( $p['url'] ) . '" target="_blank" rel="noopener">' . esc_html( zhdanov_core__( 'Читать в Telegram' ) ) . '</a></li>';
	}
	echo '</ul>';
}
echo '<p><a class="wp-element-button" href="' . esc_url( 'https://t.me/' . $ch ) . '" target="_blank" rel="noopener" data-zh-goal="messenger">' . esc_html( zhdanov_core__( 'Подписаться на канал' ) ) . '</a></p></div>';
