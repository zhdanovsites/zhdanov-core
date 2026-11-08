<?php
/**
 * Блок «Видео по клику»: обложка + кнопка, плеер загружается только после нажатия.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
$url = isset( $attributes['url'] ) ? trim( $attributes['url'] ) : '';
$v   = zhdanov_core_video_embed( $url );
if ( ! $v ) {
	if ( wp_is_serving_rest_request() ) {
		echo '<p>Вставьте ссылку на видео YouTube, Rutube или VK Видео (панель справа).</p>';
	}
	return;
}
$title  = $attributes['title'] ? $attributes['title'] : 'Видео';
$poster = '';
if ( ! empty( $attributes['posterId'] ) ) {
	$poster = wp_get_attachment_image( (int) $attributes['posterId'], 'large', false, array( 'class' => 'zh-video__poster', 'alt' => '', 'loading' => 'lazy' ) );
} elseif ( ! empty( $attributes['poster'] ) ) {
	$poster = '<img class="zh-video__poster" src="' . esc_url( $attributes['poster'] ) . '" alt="" loading="lazy">';
}
printf(
	'<div %1$s data-embed="%2$s"><button type="button" class="zh-video__btn">%3$s<span class="zh-video__play" aria-hidden="true"></span><span class="zh-video__title">%4$s</span></button><p class="zh-video__note">Видео загрузится с сервиса %5$s</p></div>',
	get_block_wrapper_attributes( array( 'class' => 'zh-video' . ( $poster ? '' : ' no-poster' ) ) ), // phpcs:ignore
	esc_url( $v['embed'] ),
	$poster, // phpcs:ignore
	esc_html( $title ),
	esc_html( $v['service'] )
);
