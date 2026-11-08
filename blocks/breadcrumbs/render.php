<?php
/**
 * Блок «Хлебные крошки» + разметка BreadcrumbList.
 */
if ( ! defined( 'ABSPATH' ) || is_front_page() ) {
	return;
}
$crumbs = zhdanov_core_breadcrumbs();
if ( count( $crumbs ) < 2 ) {
	return;
}
echo '<nav ' . get_block_wrapper_attributes( array( 'class' => 'zh-crumbs' ) ) . ' aria-label="Вы здесь"><ol>'; // phpcs:ignore
$last = count( $crumbs ) - 1;
foreach ( $crumbs as $i => $c ) {
	if ( $i === $last ) {
		echo '<li aria-current="page">' . esc_html( $c[0] ) . '</li>';
	} else {
		echo '<li><a href="' . esc_url( $c[1] ) . '">' . esc_html( $c[0] ) . '</a></li>';
	}
}
echo '</ol></nav>';
zhdanov_core_jsonld(
	array(
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'itemListElement' => array_map(
			function ( $c, $i ) {
				return array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1] );
			},
			$crumbs,
			array_keys( $crumbs )
		),
	)
);
