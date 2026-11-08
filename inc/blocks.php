<?php
/**
 * Регистрация блоков плагина. Каждый блок рисуется на сервере: быстро и без сборки.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'block_categories_all', function ( $cats ) {
	array_unshift( $cats, array( 'slug' => 'zhdanov', 'title' => 'Жданов' ) );
	return $cats;
} );

add_action( 'init', function () {
	wp_register_script(
		'zhdanov-core-editor',
		ZHDANOV_CORE_URL . 'assets/editor.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render' ),
		ZHDANOV_CORE_VERSION,
		true
	);
	wp_register_style( 'zhdanov-core-front', ZHDANOV_CORE_URL . 'assets/front.css', array(), ZHDANOV_CORE_VERSION );

	foreach ( glob( ZHDANOV_CORE_DIR . 'blocks/*/block.json' ) as $json ) {
		register_block_type( dirname( $json ) );
	}
} );

/** Хлебные крошки: [ [название, ссылка], … ]. */
function zhdanov_core_breadcrumbs() {
	$c = array( array( zhdanov_core__( 'Главная' ), home_url( '/' ) ) );

	if ( is_singular( 'zh_event' ) || is_post_type_archive( 'zh_event' ) || is_tax( 'zh_event_cat' ) ) {
		$c[] = array( zhdanov_core__( 'Афиша' ), get_post_type_archive_link( 'zh_event' ) );
		if ( is_tax( 'zh_event_cat' ) ) {
			$c[] = array( single_term_title( '', false ), get_term_link( get_queried_object() ) );
		}
		if ( is_singular() ) {
			$c[] = array( get_the_title(), get_permalink() );
		}
		if ( is_post_type_archive( 'zh_event' ) ) {
			array_pop( $c );
			$c[] = array( zhdanov_core__( 'Афиша' ), get_post_type_archive_link( 'zh_event' ) );
		}
		return $c;
	}

	$blog_id  = (int) get_option( 'page_for_posts' );
	$blog_url = $blog_id ? get_permalink( $blog_id ) : '';
	$blog_nm  = $blog_id ? get_the_title( $blog_id ) : zhdanov_core__( 'Новости' );

	if ( is_home() && $blog_id ) {
		$c[] = array( $blog_nm, $blog_url );
	} elseif ( is_singular( 'post' ) ) {
		if ( $blog_url ) {
			$c[] = array( $blog_nm, $blog_url );
		}
		$cats = get_the_category();
		if ( $cats ) {
			$c[] = array( $cats[0]->name, get_category_link( $cats[0] ) );
		}
		$c[] = array( get_the_title(), get_permalink() );
	} elseif ( is_page() ) {
		foreach ( array_reverse( get_post_ancestors( get_the_ID() ) ) as $a ) {
			$c[] = array( get_the_title( $a ), get_permalink( $a ) );
		}
		$c[] = array( get_the_title(), get_permalink() );
	} elseif ( is_tax( array( 'zh_service_cat', 'zh_product_cat' ) ) ) {
		$tax = get_queried_object();
		$pt  = 'zh_service_cat' === $tax->taxonomy ? 'zh_service' : 'zh_product';
		$pto = get_post_type_object( $pt );
		$c[] = array( $pto->labels->name, get_post_type_archive_link( $pt ) );
		$c[] = array( $tax->name, get_term_link( $tax ) );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		if ( $blog_url ) {
			$c[] = array( $blog_nm, $blog_url );
		}
		$c[] = array( single_term_title( '', false ), get_term_link( get_queried_object() ) );
	} elseif ( is_search() ) {
		$c[] = array( zhdanov_core__( 'Поиск' ), get_search_link() );
	} elseif ( is_singular() ) {
		$pto = get_post_type_object( get_post_type() );
		if ( $pto && $pto->has_archive ) {
			$c[] = array( $pto->labels->name, get_post_type_archive_link( get_post_type() ) );
		}
		$c[] = array( get_the_title(), get_permalink() );
	} elseif ( is_post_type_archive() ) {
		$c[] = array( post_type_archive_title( '', false ), get_post_type_archive_link( get_query_var( 'post_type' ) ) );
	} elseif ( is_archive() ) {
		$c[] = array( wp_strip_all_tags( get_the_archive_title() ), '' );
	}
	return $c;
}
