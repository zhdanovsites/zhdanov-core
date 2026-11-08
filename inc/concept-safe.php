<?php
/**
 * Безопасное обновление концепта.
 *
 * 1. Отпечаток: после переноса у каждой страницы и общего раздела запоминается отпечаток (_zh_ihash).
 *    Если отпечаток не совпадает, страницу правили на сайте: при повторном переносе она по умолчанию не трогается.
 * 2. Снимок: перед каждым переносом прежнее состояние всего, что будет перезаписано, сохраняется
 *    (скрытый тип записей zh_csnap). «Вернуть как было» восстанавливает его одной кнопкой; перед откатом
 *    тоже делается снимок, так что откат можно отменить.
 *
 * @package zhdanov-core
 */

defined( 'ABSPATH' ) || exit;

const ZHDANOV_SNAP_KEEP = 5; // сколько последних снимков хранить на один концепт

add_action( 'init', function () {
	register_post_type( 'zh_csnap', array(
		'public'       => false,
		'show_ui'      => false,
		'show_in_rest' => false,
		'rewrite'      => false,
		'query_var'    => false,
		'supports'     => array( 'title' ),
		'label'        => 'Снимки концепта',
	) );
} );

/* ---------- Отпечаток ---------- */

function zhdanov_concept_ihash( $post ) {
	$post = get_post( $post );
	return $post ? md5( $post->post_content . "\n" . $post->post_title ) : '';
}

function zhdanov_concept_mark( $id ) {
	update_post_meta( $id, '_zh_ihash', zhdanov_concept_ihash( $id ) );
}

/**
 * Правили ли страницу (или общий раздел) после последнего переноса.
 * Страницы из старых версий плагина без отпечатка: сравниваем дату изменения со временем переноса.
 */
function zhdanov_concept_edited( $id, $slug ) {
	$p = get_post( $id );
	if ( ! $p ) {
		return false;
	}
	if ( 'trash' === $p->post_status ) {
		return true;
	}
	$h = get_post_meta( $id, '_zh_ihash', true );
	if ( $h ) {
		return $h !== zhdanov_concept_ihash( $p );
	}
	$all = (array) get_option( 'zh_concepts', array() );
	$t   = isset( $all[ $slug ]['time'] ) ? (int) $all[ $slug ]['time'] : 0;
	return $t && strtotime( $p->post_modified_gmt . ' UTC' ) > $t + 300;
}

/** Существующая страница концепта по пути ('' = главная). */
function zhdanov_concept_page_at( $slug, $path ) {
	$path = trim( (string) $path, '/' );
	if ( '' === $path ) {
		return zhdanov_concept_find_front( $slug );
	}
	$e = get_page_by_path( $path );
	return $e ? $e->ID : 0;
}

/**
 * Состояние страниц перед повторным переносом: new | same | edited | deleted | conflict.
 */
function zhdanov_concept_status( $slug, $paths ) {
	$out = array( 'pages' => array(), 'shared' => array(), 'snaps' => count( zhdanov_concept_snaps( $slug ) ) );
	foreach ( (array) $paths as $w ) {
		$id = zhdanov_concept_page_at( $slug, $w );
		if ( ! $id ) {
			$out['pages'][ $w ] = array( 'state' => 'new' );
			continue;
		}
		$p = get_post( $id );
		if ( get_post_meta( $id, '_zh_concept', true ) !== $slug ) {
			$state = 'trash' === $p->post_status ? 'new' : 'conflict';
		} elseif ( 'trash' === $p->post_status ) {
			$state = 'deleted';
		} else {
			$state = zhdanov_concept_edited( $id, $slug ) ? 'edited' : 'same';
		}
		$out['pages'][ $w ] = array(
			'state'    => $state,
			'id'       => $id,
			'title'    => $p->post_title,
			'modified' => wp_date( 'd.m.Y H:i', strtotime( $p->post_modified_gmt . ' UTC' ) ),
		);
	}
	foreach ( array_unique( array_map( 'intval', (array) get_option( 'zh_concept_shared_' . $slug, array() ) ) ) as $ref ) {
		$p = get_post( $ref );
		if ( $p && 'trash' !== $p->post_status && zhdanov_concept_edited( $ref, $slug ) ) {
			$out['shared'][] = array( 'id' => $ref, 'title' => $p->post_title, 'modified' => wp_date( 'd.m.Y H:i', strtotime( $p->post_modified_gmt . ' UTC' ) ) );
		}
	}
	return $out;
}

/* ---------- Снимки ---------- */

/** Начать снимок: запоминает главную страницу сайта и карту общих разделов. */
function zhdanov_concept_snap_begin( $slug, $label ) {
	$id = wp_insert_post( array(
		'post_type'    => 'zh_csnap',
		'post_status'  => 'publish',
		'post_title'   => $slug,
		'post_excerpt' => $label,
	) );
	if ( ! $id || is_wp_error( $id ) ) {
		return 0;
	}
	update_post_meta( $id, '_zh_snap_slug', $slug );
	update_post_meta( $id, '_zh_snap_front', array( 'show' => get_option( 'show_on_front' ), 'page' => (int) get_option( 'page_on_front' ) ) );
	update_post_meta( $id, '_zh_snap_shared', wp_slash( (array) get_option( 'zh_concept_shared_' . $slug, array() ) ) );
	update_post_meta( $id, '_zh_snap_created', array() );
	return $id;
}

/** Запомнить текущее состояние записи перед перезаписью (один раз на снимок). */
function zhdanov_concept_snap_add( $snap, $target ) {
	static $done = array();
	$p = get_post( $target );
	if ( ! $snap || ! $p || isset( $done[ $snap ][ $target ] ) ) {
		return;
	}
	$done[ $snap ][ $target ] = 1;
	$c = wp_insert_post( wp_slash( array(
		'post_type'    => 'zh_csnap',
		'post_status'  => 'publish',
		'post_parent'  => $snap,
		'post_title'   => $p->post_title,
		'post_content' => $p->post_content,
	) ) );
	if ( ! $c || is_wp_error( $c ) ) {
		return;
	}
	update_post_meta( $c, '_zh_snap_target', $p->ID );
	update_post_meta( $c, '_zh_snap_data', wp_slash( array(
		'title'  => $p->post_title,
		'name'   => $p->post_name,
		'parent' => $p->post_parent,
		'status' => $p->post_status,
		'cpage'  => get_post_meta( $p->ID, '_zh_cpage', true ),
		'ihash'  => get_post_meta( $p->ID, '_zh_ihash', true ),
	) ) );
}

/** Запись, созданная этим переносом: при откате уходит в корзину. */
function zhdanov_concept_snap_created( $snap, $id ) {
	if ( ! $snap || ! $id ) {
		return;
	}
	$c   = (array) get_post_meta( $snap, '_zh_snap_created', true );
	$c[] = (int) $id;
	update_post_meta( $snap, '_zh_snap_created', $c );
}

/** Закончить снимок: пустой удаляется, старые сверх ZHDANOV_SNAP_KEEP тоже. */
function zhdanov_concept_snap_end( $snap ) {
	if ( ! $snap ) {
		return;
	}
	$slug = get_post_meta( $snap, '_zh_snap_slug', true );
	$kids = get_posts( array( 'post_type' => 'zh_csnap', 'post_parent' => $snap, 'numberposts' => 1, 'fields' => 'ids', 'post_status' => 'any' ) );
	if ( ! $kids && ! get_post_meta( $snap, '_zh_snap_created', true ) && ! get_post_meta( $snap, '_zh_snap_trashed', true ) ) {
		wp_delete_post( $snap, true );
		return;
	}
	update_post_meta( $snap, '_zh_snap_items', count( get_posts( array( 'post_type' => 'zh_csnap', 'post_parent' => $snap, 'numberposts' => -1, 'fields' => 'ids', 'post_status' => 'any' ) ) ) + count( (array) get_post_meta( $snap, '_zh_snap_created', true ) ) );
	foreach ( array_slice( zhdanov_concept_snaps( $slug ), ZHDANOV_SNAP_KEEP ) as $old ) {
		zhdanov_concept_snap_delete( $old->ID );
	}
}

function zhdanov_concept_snap_delete( $snap ) {
	foreach ( get_posts( array( 'post_type' => 'zh_csnap', 'post_parent' => $snap, 'numberposts' => -1, 'fields' => 'ids', 'post_status' => 'any' ) ) as $k ) {
		wp_delete_post( $k, true );
	}
	wp_delete_post( $snap, true );
}

/** Снимки концепта, новые сверху. */
function zhdanov_concept_snaps( $slug ) {
	return get_posts( array(
		'post_type'   => 'zh_csnap',
		'post_parent' => 0,
		'post_status' => 'any',
		'numberposts' => 50,
		'orderby'     => 'ID',
		'order'       => 'DESC',
		'meta_query'  => array( array( 'key' => '_zh_snap_slug', 'value' => $slug ) ), // phpcs:ignore
	) );
}

/**
 * Вернуть сайт к снимку. Перед этим делается новый снимок «Перед откатом», им откат можно отменить.
 * Возвращает [ 'restored' => n, 'trashed' => n, 'undo' => id ] или WP_Error.
 */
function zhdanov_concept_rollback( $snap ) {
	$h = get_post( $snap );
	if ( ! $h || 'zh_csnap' !== $h->post_type || $h->post_parent ) {
		return new WP_Error( 'zh_snap', 'Снимок не найден' );
	}
	$slug    = get_post_meta( $snap, '_zh_snap_slug', true );
	$kids    = get_posts( array( 'post_type' => 'zh_csnap', 'post_parent' => $snap, 'numberposts' => -1, 'post_status' => 'any', 'orderby' => 'ID', 'order' => 'ASC' ) );
	$created = array_filter( array_map( 'intval', (array) get_post_meta( $snap, '_zh_snap_created', true ) ) );
	$trashed = array_filter( array_map( 'intval', (array) get_post_meta( $snap, '_zh_snap_trashed', true ) ) );

	$undo = zhdanov_concept_snap_begin( $slug, 'Перед откатом к ' . wp_date( 'd.m.Y H:i', strtotime( $h->post_date_gmt . ' UTC' ) ) );
	if ( get_post_meta( $snap, '_zh_snap_kind', true ) ) {
		update_post_meta( $undo, '_zh_snap_kind', get_post_meta( $snap, '_zh_snap_kind', true ) );
	}
	foreach ( $kids as $k ) {
		zhdanov_concept_snap_add( $undo, (int) get_post_meta( $k->ID, '_zh_snap_target', true ) );
	}
	$trash_now = array();
	foreach ( $created as $id ) {
		$p = get_post( $id );
		if ( $p && 'trash' !== $p->post_status ) {
			zhdanov_concept_snap_add( $undo, $id );
			$trash_now[] = $id;
		}
	}
	// записи, которые прошлый откат убрал в корзину: достать (статус вернёт снимок ниже)
	foreach ( $trashed as $id ) {
		if ( 'trash' === get_post_status( $id ) ) {
			wp_untrash_post( $id );
		}
		zhdanov_concept_snap_created( $undo, $id );
	}

	$n = 0;
	foreach ( $kids as $k ) {
		$target = (int) get_post_meta( $k->ID, '_zh_snap_target', true );
		$d      = (array) get_post_meta( $k->ID, '_zh_snap_data', true );
		if ( ! $target || ! get_post( $target ) ) {
			continue;
		}
		if ( 'trash' === get_post_status( $target ) && 'trash' !== ( isset( $d['status'] ) ? $d['status'] : '' ) ) {
			wp_untrash_post( $target );
		}
		wp_update_post( wp_slash( array(
			'ID'           => $target,
			'post_content' => $k->post_content,
			'post_title'   => isset( $d['title'] ) ? $d['title'] : $k->post_title,
			'post_name'    => isset( $d['name'] ) ? $d['name'] : '',
			'post_parent'  => isset( $d['parent'] ) ? (int) $d['parent'] : 0,
			'post_status'  => isset( $d['status'] ) ? $d['status'] : 'publish',
		) ) );
		if ( ! empty( $d['cpage'] ) ) {
			update_post_meta( $target, '_zh_cpage', wp_slash( $d['cpage'] ) );
		}
		if ( ! empty( $d['ihash'] ) ) {
			update_post_meta( $target, '_zh_ihash', $d['ihash'] );
		} else {
			delete_post_meta( $target, '_zh_ihash' );
		}
		$n++;
	}
	foreach ( $trash_now as $id ) {
		wp_trash_post( $id );
	}
	update_post_meta( $undo, '_zh_snap_trashed', $trash_now );

	$f = (array) get_post_meta( $snap, '_zh_snap_front', true );
	if ( isset( $f['show'] ) ) {
		update_option( 'show_on_front', $f['show'] );
		update_option( 'page_on_front', (int) $f['page'] );
	}
	update_option( 'zh_concept_shared_' . $slug, (array) get_post_meta( $snap, '_zh_snap_shared', true ), false );
	update_post_meta( $snap, '_zh_snap_used', time() );
	zhdanov_concept_snap_end( $undo );
	flush_rewrite_rules( false );
	return array( 'restored' => $n, 'trashed' => count( $trash_now ), 'undo' => $undo );
}

/* ---------- REST и кнопки ---------- */

add_action( 'rest_api_init', function () {
	register_rest_route( 'zhdanov/v1', '/concept/status', array(
		'methods'             => 'POST',
		'permission_callback' => function () {
			return current_user_can( 'manage_options' );
		},
		'callback'            => function ( $req ) {
			return zhdanov_concept_status( sanitize_title( $req->get_param( 'slug' ) ), (array) $req->get_param( 'paths' ) );
		},
	) );
} );

add_action( 'admin_post_zhdanov_concept_rollback', function () {
	$snap = isset( $_POST['snap'] ) ? (int) $_POST['snap'] : 0; // phpcs:ignore
	check_admin_referer( 'zh_rollback_' . $snap );
	$kind = get_post_meta( $snap, '_zh_snap_kind', true );
	// откат замены текста может сделать тот, кто может заменять; откат переноса: только администратор
	if ( ! current_user_can( 'replace' === $kind ? 'edit_others_pages' : 'manage_options' ) ) {
		wp_die( 'Нет доступа' );
	}
	$r = zhdanov_concept_rollback( $snap );
	$q = is_wp_error( $r ) ? array( 'rb' => 'err' ) : array( 'rb' => 'ok', 'rbn' => $r['restored'], 'rbt' => $r['trashed'] );
	wp_safe_redirect( add_query_arg( $q, admin_url( 'admin.php?page=' . ( 'replace' === $kind ? 'zhdanov-creplace' : 'zhdanov-concept' ) ) ) . '#zh-snaps' );
	exit;
} );

/** Блок «Снимки» на странице «Перенос концепта». */
function zhdanov_concept_snaps_html( $kind = '' ) {
	$all = (array) get_option( 'zh_concepts', array() );
	if ( isset( $_GET['rb'] ) ) { // phpcs:ignore
		if ( 'ok' === $_GET['rb'] ) { // phpcs:ignore
			echo '<div class="notice notice-success"><p><b>Готово: сайт возвращён к снимку.</b> Восстановлено записей: ' . (int) $_GET['rbn'] . ( (int) $_GET['rbt'] ? ', новые страницы того переноса убраны в корзину: ' . (int) $_GET['rbt'] : '' ) . '. Если передумали, сверху списка появился снимок «Перед откатом»: им всё возвращается обратно.</p></div>'; // phpcs:ignore
		} else {
			echo '<div class="notice notice-error"><p>Снимок не найден.</p></div>';
		}
	}
	$rows = '';
	foreach ( array_keys( $all ) as $slug ) {
		foreach ( zhdanov_concept_snaps( $slug ) as $s ) {
			if ( $kind && get_post_meta( $s->ID, '_zh_snap_kind', true ) !== $kind ) {
				continue;
			}
			$used  = get_post_meta( $s->ID, '_zh_snap_used', true );
			$rows .= '<tr><td><b>' . esc_html( $slug ) . '</b></td><td>' . esc_html( wp_date( 'd.m.Y H:i', strtotime( $s->post_date_gmt . ' UTC' ) ) ) . '</td><td>' . esc_html( $s->post_excerpt ) . ( $used ? '<br><small style="color:#646970">к нему уже возвращались ' . esc_html( wp_date( 'd.m.Y H:i', $used ) ) . '</small>' : '' ) . '</td><td>' . (int) get_post_meta( $s->ID, '_zh_snap_items', true ) . '</td><td>'
				. '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'Вернуть страницы концепта к состоянию на ' . esc_js( wp_date( 'd.m.Y H:i', strtotime( $s->post_date_gmt . ' UTC' ) ) ) . '? Перед этим сохранится снимок текущего состояния, так что откат можно отменить.\')">'
				. '<input type="hidden" name="action" value="zhdanov_concept_rollback"><input type="hidden" name="snap" value="' . (int) $s->ID . '">' . wp_nonce_field( 'zh_rollback_' . $s->ID, '_wpnonce', true, false )
				. '<button class="button">Вернуть как было</button></form></td></tr>';
		}
	}
	if ( ! $rows ) {
		return;
	}
	echo '<h2 id="zh-snaps">Снимки: вернуть как было</h2><p style="max-width:760px;color:#646970">' . ( 'replace' === $kind ? 'Перед каждой заменой плагин сохраняет, какими были страницы, шапка и подвал. «Вернуть как было» возвращает текст до замены.' : 'Перед каждым переносом, заменой и откатом плагин сохраняет, какими были страницы, шапка, подвал и главная. «Вернуть как было» восстанавливает это состояние; новые страницы того переноса уходят в корзину.' ) . ' Хранятся последние ' . (int) ZHDANOV_SNAP_KEEP . ' снимков каждого концепта.</p>';
	echo '<table class="widefat striped" style="max-width:760px"><thead><tr><th>Концепт</th><th>Когда</th><th>Что это</th><th>Записей</th><th></th></tr></thead><tbody>' . $rows . '</tbody></table>'; // phpcs:ignore
}
