<?php
/**
 * Адреса латиницей: «Новость номер 1» → /novost-nomer-1/. Так ссылки читаются в мессенджерах и лучше для поиска.
 * Работает для новых записей и файлов. Если стоит плагин Cyr-To-Lat, наш не мешает ему.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zhdanov_core_translit( $s ) {
	static $map = array(
		'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
		'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
		'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
		'э' => 'e', 'ю' => 'yu', 'я' => 'ya', 'і' => 'i', 'ў' => 'u', 'ї' => 'yi', 'є' => 'ye', 'ґ' => 'g',
	);
	$s = mb_strtolower( (string) $s, 'UTF-8' );
	return strtr( $s, $map );
}

if ( ! class_exists( 'Cyr_To_Lat\Main' ) && ! function_exists( 'ctl_sanitize_title' ) ) {
	add_filter( 'sanitize_title', function ( $title, $raw = '', $context = 'display' ) {
		if ( 'save' !== $context || ! preg_match( '/[\x{0400}-\x{04FF}]/u', (string) $raw ) ) {
			return $title;
		}
		return sanitize_title_with_dashes( zhdanov_core_translit( $raw ), '', 'save' );
	}, 9, 3 );
	add_filter( 'sanitize_file_name', function ( $name ) {
		return preg_match( '/[\x{0400}-\x{04FF}]/u', $name ) ? zhdanov_core_translit( $name ) : $name;
	}, 9 );
}
