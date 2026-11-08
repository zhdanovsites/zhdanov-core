<?php
/**
 * Страница концепта: только разметка, стили и скрипты концепта.
 *
 * @package zhdanov-core
 */

defined( 'ABSPATH' ) || exit;
$zh_id = ! empty( $GLOBALS['zh_concept_force'] ) ? (int) $GLOBALS['zh_concept_force'] : get_queried_object_id();
$zh_p  = (array) get_post_meta( $zh_id, '_zh_cpage', true );
$zh_p += array( 'head' => '', 'htmlAttrs' => array(), 'bodyAttrs' => array(), 'description' => '' );
$zh_seo  = zhdanov_concept_seo( $zh_id );
$zh_head = zhdanov_concept_tokens( $zh_p['head'] );
if ( $zh_seo['custom'] ) {
	// карточки соцсетей берут те же заголовок и описание
	$zh_head = preg_replace( '#(<meta[^>]+(?:property|name)="(?:og:title|twitter:title)"[^>]*content=")[^"]*#i', '${1}' . esc_attr( $zh_seo['title'] ), $zh_head );
	if ( '' !== $zh_seo['description'] ) {
		$zh_head = preg_replace( '#(<meta[^>]+(?:property|name)="(?:og:description|twitter:description)"[^>]*content=")[^"]*#i', '${1}' . esc_attr( $zh_seo['description'] ), $zh_head );
	}
}
$zh_p['description'] = $zh_seo['description'];
?><!doctype html>
<html<?php echo zhdanov_concept_attrs( $zh_p['htmlAttrs'] ); // phpcs:ignore ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<?php
if ( $zh_p['description'] && false === stripos( $zh_p['head'], 'name="description"' ) && ! defined( 'WPSEO_VERSION' ) && ! class_exists( 'RankMath' ) ) {
	echo '<meta name="description" content="' . esc_attr( $zh_p['description'] ) . '">' . "\n";
}
echo $zh_head . "\n"; // phpcs:ignore
if ( function_exists( 'zhdanov_core_verify_meta' ) && false === stripos( $zh_head, 'yandex-verification' ) && false === stripos( $zh_head, 'google-site-verification' ) ) {
	zhdanov_core_verify_meta(); // подтверждение для Вебмастера (вывод плагина на этих страницах отключён)
}
wp_head();
?>
</head>
<body<?php echo zhdanov_concept_attrs( $zh_p['bodyAttrs'], is_admin_bar_showing() ? 'admin-bar' : '' ); // phpcs:ignore ?>>
<?php
wp_body_open();
if ( empty( $GLOBALS['zh_concept_force'] ) ) {
	the_post();
}
echo apply_filters( 'zhdanov_concept_content', do_blocks( get_post_field( 'post_content', $zh_id ) ) ); // phpcs:ignore
wp_footer();
// Яндекс Метрика из настроек «Жданов» (если в самом концепте счётчика нет): после согласия в баннере cookie концепта
$zh_ym = function_exists( 'zhdanov_core_opt' ) ? preg_replace( '/\D/', '', (string) zhdanov_core_opt( 'metrika' ) ) : '';
if ( $zh_ym && false === strpos( $zh_head, 'mc.yandex' ) ) :
	?>
<script>(function(){var id=<?php echo (int) $zh_ym; ?>,done=0;function load(){if(done)return;done=1;(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};m[i].l=1*new Date();k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})(window,document,"script","https://mc.yandex.ru/metrika/tag.js","ym");ym(id,"init",{clickmap:true,trackLinks:true,accurateTrackBounce:true,webvisor:true});}
try{if(localStorage.getItem('ck_ok')||localStorage.getItem('zh_cookie')==='all'){load();return;}}catch(e){}
var b=document.querySelector('.cookie .btn,.cookie button,[data-cookie-accept],#cookie button');if(b){b.addEventListener('click',load);}else{load();}})();</script>
	<?php
endif;
?>
</body>
</html>
