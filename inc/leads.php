<?php
/**
 * Заявки как мини-CRM (как в Bitrix24, FluentCRM, только самое нужное): статус «новая / в работе /
 * готово / спам», заметка, фильтр по статусу, счётчик новых в меню, напоминание в Telegram владельцу,
 * если заявка без ответа больше суток.
 *
 * @package zhdanov-core
 */

defined( 'ABSPATH' ) || exit;

function zhdanov_ls_labels() {
	return array( 'new' => 'Новая', 'work' => 'В работе', 'done' => 'Готово', 'spam' => 'Спам' );
}

function zhdanov_ls_get( $id ) {
	$s = get_post_meta( $id, '_zh_lstatus', true );
	return isset( zhdanov_ls_labels()[ $s ] ) ? $s : 'new';
}

/* Колонки «Статус» и «Заметка» */
add_filter( 'manage_zh_lead_posts_columns', function ( $c ) {
	$out = array();
	foreach ( $c as $k => $v ) {
		$out[ $k ] = $v;
		if ( 'title' === $k ) {
			$out['zh_ls']   = 'Статус';
			$out['zh_note'] = 'Заметка';
		}
	}
	return $out;
}, 20 );

add_action( 'manage_zh_lead_posts_custom_column', function ( $col, $id ) {
	if ( 'zh_ls' === $col ) {
		$cur = zhdanov_ls_get( $id );
		echo '<select class="zh-ls" data-id="' . (int) $id . '" data-s="' . esc_attr( $cur ) . '" aria-label="Статус заявки">';
		foreach ( zhdanov_ls_labels() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $cur, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select>';
		$t = (int) get_post_meta( $id, '_zh_lstatus_t', true );
		if ( $t && 'new' !== $cur ) {
			echo '<br><small style="color:#646970">' . esc_html( wp_date( 'd.m H:i', $t ) ) . '</small>';
		}
	}
	if ( 'zh_note' === $col ) {
		echo '<textarea class="zh-ln" data-id="' . (int) $id . '" rows="2" style="width:100%;min-width:160px" placeholder="Перезвонить в среду…" aria-label="Заметка к заявке">' . esc_textarea( get_post_meta( $id, '_zh_lnote', true ) ) . '</textarea>';
	}
}, 10, 2 );

add_action( 'wp_ajax_zhdanov_lead_set', function () {
	check_ajax_referer( 'zhdanov_lead_set' );
	$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	if ( ! $id || 'zh_lead' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
		wp_send_json_error( 'Нет прав', 403 );
	}
	if ( isset( $_POST['s'] ) ) {
		$s = sanitize_key( wp_unslash( $_POST['s'] ) );
		if ( isset( zhdanov_ls_labels()[ $s ] ) ) {
			update_post_meta( $id, '_zh_lstatus', $s );
			update_post_meta( $id, '_zh_lstatus_t', time() );
			if ( function_exists( 'zhdanov_stat' ) && 'done' === $s ) {
				zhdanov_stat( 'leads_done' );
			}
		}
	}
	if ( isset( $_POST['note'] ) ) {
		update_post_meta( $id, '_zh_lnote', mb_substr( sanitize_textarea_field( wp_unslash( $_POST['note'] ) ), 0, 2000 ) );
	}
	wp_send_json_success( array( 'new' => zhdanov_ls_count( 'new' ) ) );
} );

function zhdanov_ls_count( $s ) {
	$q = array( 'post_type' => 'zh_lead', 'post_status' => 'any', 'numberposts' => 500, 'fields' => 'ids' );
	if ( 'new' === $s ) {
		$q['meta_query'] = array( 'relation' => 'OR', array( 'key' => '_zh_lstatus', 'compare' => 'NOT EXISTS' ), array( 'key' => '_zh_lstatus', 'value' => 'new' ) ); // phpcs:ignore
	} else {
		$q['meta_query'] = array( array( 'key' => '_zh_lstatus', 'value' => $s ) ); // phpcs:ignore
	}
	return count( get_posts( $q ) );
}

/* Фильтр по статусу над списком */
add_filter( 'views_edit-zh_lead', function ( $views ) {
	$cur = isset( $_GET['zh_ls'] ) ? sanitize_key( $_GET['zh_ls'] ) : ''; // phpcs:ignore
	$out = array( 'all' => '<a href="' . esc_url( admin_url( 'edit.php?post_type=zh_lead' ) ) . '"' . ( $cur ? '' : ' class="current"' ) . '>Все</a>' );
	foreach ( zhdanov_ls_labels() as $k => $l ) {
		$out[ 'zh_' . $k ] = '<a href="' . esc_url( admin_url( 'edit.php?post_type=zh_lead&zh_ls=' . $k ) ) . '"' . ( $cur === $k ? ' class="current"' : '' ) . '>' . esc_html( $l ) . ' <span class="count">(' . zhdanov_ls_count( $k ) . ')</span></a>';
	}
	return $out;
} );

add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() || ! $q->is_main_query() || 'zh_lead' !== $q->get( 'post_type' ) || empty( $_GET['zh_ls'] ) ) { // phpcs:ignore
		return;
	}
	$s = sanitize_key( $_GET['zh_ls'] ); // phpcs:ignore
	if ( 'new' === $s ) {
		$q->set( 'meta_query', array( 'relation' => 'OR', array( 'key' => '_zh_lstatus', 'compare' => 'NOT EXISTS' ), array( 'key' => '_zh_lstatus', 'value' => 'new' ) ) );
	} elseif ( isset( zhdanov_ls_labels()[ $s ] ) ) {
		$q->set( 'meta_query', array( array( 'key' => '_zh_lstatus', 'value' => $s ) ) );
	}
} );

/* Счётчик новых в меню «Заявки» */
add_action( 'admin_menu', function () {
	global $menu;
	$n = zhdanov_ls_count( 'new' );
	if ( ! $n || ! is_array( $menu ) ) {
		return;
	}
	foreach ( $menu as $i => $m ) {
		if ( isset( $m[2] ) && 'edit.php?post_type=zh_lead' === $m[2] ) {
			$menu[ $i ][0] .= ' <span class="awaiting-mod"><span class="zh-lead-count">' . (int) $n . '</span></span>'; // phpcs:ignore
		}
	}
}, 99 );

/* Скрипт списка */
add_action( 'admin_footer-edit.php', function () {
	if ( 'zh_lead' !== ( get_current_screen()->post_type ?? '' ) ) {
		return;
	}
	$nonce = wp_create_nonce( 'zhdanov_lead_set' );
	?>
	<style>.zh-ls[data-s=new]{background:#fcf0f1;border-color:#d63638}.zh-ls[data-s=work]{background:#fcf9e8;border-color:#dba617}.zh-ls[data-s=done]{background:#edfaef;border-color:#00a32a}.zh-ls[data-s=spam]{color:#8c8f94}.column-zh_ls{width:120px}.column-zh_note{width:24%}</style>
	<script>
	(function(){
		function send(d){ d.action='zhdanov_lead_set'; d._ajax_nonce=<?php echo wp_json_encode( $nonce ); ?>; return fetch(ajaxurl,{method:'POST',credentials:'same-origin',body:new URLSearchParams(d)}).then(function(r){return r.json();}).then(function(j){ var c=document.querySelector('.zh-lead-count'); if(c&&j.success){ c.textContent=j.data.new; c.parentNode.style.display=j.data.new?'':'none'; } return j; }); }
		document.querySelectorAll('.zh-ls').forEach(function(s){ s.addEventListener('change',function(){ s.dataset.s=s.value; send({id:s.dataset.id,s:s.value}); }); });
		document.querySelectorAll('.zh-ln').forEach(function(t){ var v=t.value; t.addEventListener('blur',function(){ if(t.value===v)return; v=t.value; send({id:t.dataset.id,note:t.value}).then(function(j){ t.style.borderColor=j.success?'#00a32a':'#d63638'; }); }); });
	})();
	</script>
	<?php
} );

/* Напоминание владельцу: заявки без ответа больше суток (раз в день, в его бот заявок) */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'zhdanov_lead_remind' ) ) {
		$t = new DateTime( 'tomorrow 10:00', wp_timezone() );
		wp_schedule_event( $t->getTimestamp(), 'daily', 'zhdanov_lead_remind' );
	}
} );

function zhdanov_ls_overdue() {
	return get_posts( array(
		'post_type'   => 'zh_lead',
		'post_status' => 'any',
		'numberposts' => 50,
		'date_query'  => array( array( 'before' => wp_date( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ), 'after' => wp_date( 'Y-m-d H:i:s', time() - 14 * DAY_IN_SECONDS ) ) ),
		'meta_query'  => array( 'relation' => 'OR', array( 'key' => '_zh_lstatus', 'compare' => 'NOT EXISTS' ), array( 'key' => '_zh_lstatus', 'value' => 'new' ) ), // phpcs:ignore
	) );
}

add_action( 'zhdanov_lead_remind', function () {
	$o = (array) get_option( 'zhdanov_core', array() );
	if ( empty( $o['tg_token'] ) || empty( $o['tg_chat'] ) || ( isset( $o['lead_remind'] ) && ! $o['lead_remind'] ) ) {
		return;
	}
	$list = zhdanov_ls_overdue();
	if ( ! $list ) {
		return;
	}
	$lines = array();
	foreach ( array_slice( $list, 0, 10 ) as $p ) {
		$lines[] = '• ' . $p->post_title . ' (' . get_the_date( 'd.m H:i', $p ) . ')';
	}
	zhdanov_core_tg_send( $o['tg_token'], $o['tg_chat'], '⏰ Заявки без ответа больше суток: ' . count( $list ) . "\n" . implode( "\n", $lines ) . "\n\nОтметьте статус: " . admin_url( 'edit.php?post_type=zh_lead&zh_ls=new' ) );
} );
