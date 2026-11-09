<?php
/**
 * Отчёт разработчику: раз в неделю (понедельник, 9:00 по времени сайта) сайт сам проверяет себя
 * и присылает студии письмо и сообщение в Telegram: битые ссылки и фото, заголовки и описания
 * для поиска, адреса «не найдено», закрыт ли сайт от поисковиков, обновления, заявки.
 * Кнопка «Прислать отчёт сейчас»: Жданов → Здоровье сайта.
 *
 * Настройки: Жданов → «Отчёт разработчику» (studio_email, studio_tg_token, studio_tg_chat, studio_report).
 *
 * @package zhdanov-core
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'zhdanov_studio_report' ) ) {
		$next = new DateTimeImmutable( 'next monday 09:00', wp_timezone() );
		wp_schedule_event( $next->getTimestamp(), 'weekly', 'zhdanov_studio_report' );
	}
} );

register_deactivation_hook( ZHDANOV_CORE_DIR . 'zhdanov-core.php', function () {
	wp_clear_scheduled_hook( 'zhdanov_studio_report' );
} );

add_action( 'zhdanov_studio_report_bg', function () {
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 600 ); // phpcs:ignore
	}
	ignore_user_abort( true );
	zhdanov_studio_report_send();
	delete_option( 'zhdanov_studio_report_queued' );
} );

add_action( 'zhdanov_studio_report', function () {
	if ( ! zhdanov_core_opt( 'studio_report' ) ) {
		return;
	}
	zhdanov_studio_report_send();
} );

/** Опубликованные страницы и записи публичных типов (без заявок, бронирований и т. п.). */
function zhdanov_report_urls( $limit = 200 ) {
	$types = array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment', 'zh_lead', 'zh_booking', 'zh_order', 'zh_wait' ) );
	$ids   = get_posts( array( 'post_type' => array_values( $types ), 'post_status' => 'publish', 'numberposts' => $limit, 'fields' => 'ids', 'orderby' => 'menu_order date', 'order' => 'ASC', 'has_password' => false ) );
	$out   = array();
	$front = (int) get_option( 'page_on_front' );
	if ( $front && ! in_array( $front, $ids, true ) && 'publish' === get_post_status( $front ) ) {
		array_unshift( $ids, $front );
	}
	foreach ( $ids as $id ) {
		$out[ $id ] = get_permalink( $id );
	}
	return $out;
}

/** Код ответа адреса (HEAD, если сервер не умеет: GET). 0: не ответил. */
function zhdanov_report_status( $url ) {
	static $cache = array();
	if ( isset( $cache[ $url ] ) ) {
		return $cache[ $url ];
	}
	$args = array( 'timeout' => 10, 'redirection' => 5, 'sslverify' => false, 'user-agent' => 'ZhdanovSiteCheck/1.0' );
	$r    = wp_remote_head( $url, $args );
	$code = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
	if ( in_array( $code, array( 0, 403, 405, 501 ), true ) ) {
		$r    = wp_remote_get( $url, $args + array( 'limit_response_size' => 2048 ) );
		$code = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
	}
	$cache[ $url ] = $code;
	return $code;
}

/**
 * Проверка страниц так, как их видит посетитель.
 * Возвращает [ 'checked' => n, 'issues' => [ [уровень, адрес, что не так] ], 'unreachable' => bool ].
 */
function zhdanov_report_pages() {
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 600 ); // phpcs:ignore
	}
	$home   = home_url( '/' );
	$host   = wp_parse_url( $home, PHP_URL_HOST );
	$urls   = zhdanov_report_urls();
	$issues = array();
	$titles = array();
	$descs  = array();
	$links  = array(); // адрес => [ на какой странице встретился, это фото? ]
	$fails  = 0;
	$n      = 0;
	foreach ( $urls as $id => $url ) {
		$r = wp_remote_get( $url, array( 'timeout' => 20, 'sslverify' => false, 'user-agent' => 'ZhdanovSiteCheck/1.0', 'limit_response_size' => 3 * MB_IN_BYTES ) );
		$n++;
		$path = wp_make_link_relative( $url );
		if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
			$fails++;
			$issues[] = array( 'bad', $path, 'страница не открывается (' . ( is_wp_error( $r ) ? $r->get_error_message() : wp_remote_retrieve_response_code( $r ) ) . ')' );
			continue;
		}
		$html = wp_remote_retrieve_body( $r );
		$t    = preg_match( '~<title[^>]*>(.*?)</title>~is', $html, $m ) ? trim( html_entity_decode( wp_strip_all_tags( $m[1] ), ENT_QUOTES, 'UTF-8' ) ) : '';
		$d    = '';
		if ( preg_match( '~<meta[^>]+name=["\']description["\'][^>]*>~i', $html, $m ) && preg_match( '~content=(["\'])(.*?)\1~is', $m[0], $c ) ) {
			$d = trim( html_entity_decode( $c[2], ENT_QUOTES, 'UTF-8' ) );
		}
		if ( '' === $t ) {
			$issues[] = array( 'bad', $path, 'нет заголовка для поиска (title)' );
		} elseif ( mb_strlen( $t ) > 70 ) {
			$issues[] = array( 'warn', $path, 'длинный заголовок для поиска: ' . mb_strlen( $t ) . ' знаков (лучше до 70)' );
		}
		if ( '' === $d ) {
			$issues[] = array( 'warn', $path, 'нет описания для поиска (description)' );
		} elseif ( mb_strlen( $d ) < 50 || mb_strlen( $d ) > 180 ) {
			$issues[] = array( 'warn', $path, 'описание для поиска ' . mb_strlen( $d ) . ' знаков (лучше 50–180)' );
		}
		if ( preg_match( '~<meta[^>]+name=["\']robots["\'][^>]+noindex~i', $html ) ) {
			$issues[] = array( 'bad', $path, 'страница закрыта от поисковиков (noindex)' );
		}
		$h1 = preg_match_all( '~<h1[\s>]~i', $html );
		if ( 1 !== $h1 ) {
			$issues[] = array( 'warn', $path, $h1 ? 'несколько главных заголовков h1: ' . $h1 : 'нет главного заголовка h1' );
		}
		$noalt = 0;
		if ( preg_match_all( '~<img\b[^>]*>~i', $html, $imgs ) ) {
			foreach ( $imgs[0] as $img ) {
				if ( ! preg_match( '~\salt=~i', $img ) ) {
					$noalt++;
				}
				if ( preg_match( '~\ssrc=(["\'])(.*?)\1~i', $img, $s ) ) {
					$links[ html_entity_decode( $s[2] ) ][] = array( $path, true );
				}
			}
		}
		if ( $noalt ) {
			$issues[] = array( 'warn', $path, 'фото без подписи (alt): ' . $noalt );
		}
		if ( preg_match_all( '~<a\b[^>]*\shref=(["\'])(.*?)\1~i', $html, $as ) ) {
			foreach ( $as[2] as $h ) {
				$links[ html_entity_decode( $h ) ][] = array( $path, false );
			}
		}
		if ( $t ) {
			$titles[ $t ][] = $path;
		}
		if ( $d ) {
			$descs[ $d ][] = $path;
		}
	}
	foreach ( array( 'заголовок' => $titles, 'описание' => $descs ) as $what => $list ) {
		foreach ( $list as $v => $paths ) {
			if ( count( $paths ) > 1 ) {
				$issues[] = array( 'warn', implode( ', ', array_slice( $paths, 0, 3 ) ) . ( count( $paths ) > 3 ? ' и ещё ' . ( count( $paths ) - 3 ) : '' ), ( 'описание' === $what ? 'одинаковое ' : 'одинаковый ' ) . $what . ' для поиска на ' . count( $paths ) . ' стр.' );
			}
		}
	}
	// битые ссылки и фото своего сайта (не больше 400 адресов за раз)
	$k = 0;
	foreach ( $links as $href => $where ) {
		if ( '' === $href || '#' === $href[0] || preg_match( '~^(mailto|tel|javascript|data|sms|viber|whatsapp|tg|max):~i', $href ) ) {
			continue;
		}
		$abs = WP_Http::make_absolute_url( $href, $home );
		if ( wp_parse_url( $abs, PHP_URL_HOST ) !== $host || false !== strpos( $abs, '/wp-admin' ) || false !== strpos( $abs, '/wp-json' ) ) {
			continue;
		}
		$abs = strtok( $abs, '#' );
		if ( ++$k > 400 ) {
			break;
		}
		$code = zhdanov_report_status( $abs );
		if ( $code >= 400 ) {
			$issues[] = array( 'bad', $where[0][0] . ( count( $where ) > 1 ? ' и ещё ' . ( count( $where ) - 1 ) : '' ), ( $where[0][1] ? 'битое фото' : 'битая ссылка' ) . ' ' . wp_make_link_relative( $abs ) . ' (' . $code . ')' );
		}
	}
	return array( 'checked' => $n, 'issues' => $issues, 'unreachable' => $n && $fails === $n );
}

/** Текст отчёта и краткий итог. */
function zhdanov_studio_report_build() {
	$site  = get_bloginfo( 'name' );
	$home  = home_url( '/' );
	$pages = zhdanov_report_pages();
	$lines = array( 'Отчёт сайта ' . $site . ' · ' . preg_replace( '~^https?://~', '', untrailingslashit( $home ) ), wp_date( 'd.m.Y H:i' ), '' );
	$bad   = 0;
	$warn  = 0;

	$top = array();
	if ( ! get_option( 'blog_public' ) ) {
		$top[] = '❗ Сайт закрыт от поисковиков (Настройки → Чтение). Если сайт уже запущен: снять галочку.';
		$bad++;
	}
	if ( $pages['unreachable'] ) {
		$top[] = '❗ Сайт не смог открыть сам себя: проверка страниц не прошла (хостинг блокирует запросы к себе или сайт лежит).';
		$bad++;
	}
	if ( function_exists( 'zhdanov_health_checks' ) ) {
		foreach ( zhdanov_health_checks() as $c ) {
			// пункт «Здоровья сайта» назван как цель («Есть значок сайта»): в отчёте пишем, что он не выполнен
			$fix = $c[2] ? ': ' . mb_substr( preg_replace( '/\s+/u', ' ', $c[2] ), 0, 110 ) : '';
			if ( 'bad' === $c[0] ) {
				$top[] = '❗ Не готово «' . $c[1] . '»' . $fix;
				$bad++;
			} elseif ( 'warn' === $c[0] ) {
				$top[] = '• Стоит сделать «' . $c[1] . '»' . $fix;
				$warn++;
			}
		}
	}
	if ( $top ) {
		$lines[] = 'Настройки сайта:';
		$lines   = array_merge( $lines, array_slice( $top, 0, 10 ) );
		$lines[] = '';
	}

	$pi   = $pages['issues'];
	$pbad = array_filter( $pi, function ( $x ) { return 'bad' === $x[0]; } );
	$bad += count( $pbad );
	$warn += count( $pi ) - count( $pbad );
	$lines[] = 'Страницы: проверено ' . $pages['checked'] . ( $pi ? ', замечаний ' . count( $pi ) . ( $pbad ? ' (важных ' . count( $pbad ) . ')' : '' ) : ', всё в порядке ✅' );
	usort( $pi, function ( $a, $b ) { return ( 'bad' === $a[0] ? 0 : 1 ) - ( 'bad' === $b[0] ? 0 : 1 ); } );
	foreach ( array_slice( $pi, 0, 15 ) as $x ) {
		$lines[] = ( 'bad' === $x[0] ? '❗ ' : '• ' ) . $x[1] . ': ' . $x[2];
	}
	if ( count( $pi ) > 15 ) {
		$lines[] = '…и ещё ' . ( count( $pi ) - 15 ) . '. Все: Здоровье сайта → «Проверить страницы сайта».';
	}

	// срок SSL-сертификата: истечёт, браузеры покажут «Небезопасно»
	$ssl = zhdanov_report_ssl_days();
	if ( null !== $ssl ) {
		if ( $ssl < 14 ) {
			$bad++;
			array_splice( $lines, 4, 0, array( '❗ SSL-сертификат истекает через ' . $ssl . ' дн.: продлите в панели хостинга (обычно бесплатный Let\'s Encrypt продлевается сам, значит что-то сломалось)' ) );
		} else {
			$lines[] = '';
			$lines[] = 'SSL-сертификат: ещё ' . $ssl . ' дн. ✅';
		}
	}

	// PHP-ошибки и что меняли на сайте
	if ( function_exists( 'zhdanov_activity_week' ) ) {
		$aw = zhdanov_activity_week();
		if ( $aw['errors'] ) {
			$bad++;
			$lines[] = '';
			$lines[] = '❗ Страницы падали с ошибкой PHP: ' . count( $aw['errors'] ) . ' раз за неделю. Последняя: ' . $aw['errors'][ count( $aw['errors'] ) - 1 ]['msg'] . ' (' . $aw['errors'][ count( $aw['errors'] ) - 1 ]['where'] . ')';
		}
		if ( $aw['count'] ) {
			$parts = array();
			foreach ( $aw['count'] as $k => $n ) {
				$parts[] = $k . ': ' . $n;
			}
			$lines[] = '';
			$lines[] = 'Что делали на сайте за неделю: ' . implode( ', ', array_slice( $parts, 0, 8 ) );
			foreach ( $aw['important'] as $l ) {
				$lines[] = '• ' . wp_date( 'd.m H:i', $l['t'] ) . ' ' . $l['who'] . ': ' . $l['act'] . ( $l['obj'] ? ' (' . $l['obj'] . ')' : '' );
			}
		}
	}

	// «не найдено» за неделю
	$week = array();
	foreach ( (array) get_option( 'zhdanov_404', array() ) as $path => $v ) {
		if ( is_array( $v ) && isset( $v[1] ) && strtotime( $v[1] ) > current_time( 'timestamp' ) - WEEK_IN_SECONDS ) { // phpcs:ignore
			$week[ $path ] = (int) $v[0];
		}
	}
	if ( $week ) {
		arsort( $week );
		$lines[] = '';
		$lines[] = 'Адреса «не найдено» за неделю: ' . count( $week ) . ' (можно добавить переадресацию в Здоровье сайта)';
		foreach ( array_slice( $week, 0, 5, true ) as $p => $c ) {
			$lines[] = '• ' . $p . ' (' . $c . ' раз)';
		}
	}

	// обновления
	$upd = array();
	$c   = get_site_transient( 'update_core' );
	if ( $c && ! empty( $c->updates ) && 'upgrade' === $c->updates[0]->response ) {
		$upd[] = 'WordPress ' . $c->updates[0]->current;
	}
	$p = get_site_transient( 'update_plugins' );
	if ( $p && ! empty( $p->response ) ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all = get_plugins();
		foreach ( $p->response as $file => $r ) {
			$upd[] = ( isset( $all[ $file ]['Name'] ) ? $all[ $file ]['Name'] : $file ) . ' ' . $r->new_version;
		}
	}
	$t = get_site_transient( 'update_themes' );
	if ( $t && ! empty( $t->response ) ) {
		foreach ( $t->response as $slug => $r ) {
			$upd[] = 'тема ' . $slug . ' ' . $r['new_version'];
		}
	}
	if ( $upd ) {
		$warn++;
		$lines[] = '';
		$lines[] = 'Есть обновления: ' . implode( ', ', array_slice( $upd, 0, 8 ) );
	}

	// заявки
	if ( post_type_exists( 'zh_lead' ) ) {
		$leads = get_posts( array( 'post_type' => 'zh_lead', 'post_status' => 'any', 'numberposts' => 500, 'fields' => 'ids', 'date_query' => array( array( 'after' => gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS ), 'column' => 'post_date_gmt' ) ) ) );
		$lines[] = '';
		$lines[] = 'Заявок за неделю: ' . count( $leads );
	}

	$lines[] = '';
	$lines[] = 'WordPress ' . get_bloginfo( 'version' ) . ' · Жданов ' . ZHDANOV_CORE_VERSION . ' · PHP ' . PHP_VERSION;
	$lines[] = 'Здоровье сайта: ' . admin_url( 'admin.php?page=zhdanov-health' );
	$sum     = $bad ? '❗ важных: ' . $bad : ( $warn ? 'замечаний: ' . $warn : 'всё в порядке' );
	array_splice( $lines, 2, 0, array( 'Итог: ' . $sum ) );
	return array( 'text' => implode( "\n", $lines ), 'bad' => $bad, 'warn' => $warn, 'summary' => $sum );
}

/** Сколько дней до конца SSL-сертификата сайта (null: сайт не на https или проверить не удалось). */
function zhdanov_report_ssl_days() {
	$u = wp_parse_url( home_url( '/' ) );
	if ( empty( $u['scheme'] ) || 'https' !== $u['scheme'] || empty( $u['host'] ) || ! function_exists( 'openssl_x509_parse' ) ) {
		return null;
	}
	$ctx = stream_context_create( array( 'ssl' => array( 'capture_peer_cert' => true, 'verify_peer' => false, 'verify_peer_name' => false, 'SNI_enabled' => true, 'peer_name' => $u['host'] ) ) );
	$c   = @stream_socket_client( 'ssl://' . $u['host'] . ':' . ( isset( $u['port'] ) ? (int) $u['port'] : 443 ), $en, $es, 8, STREAM_CLIENT_CONNECT, $ctx ); // phpcs:ignore
	if ( ! $c ) {
		return null;
	}
	$p = stream_context_get_params( $c );
	fclose( $c ); // phpcs:ignore
	if ( empty( $p['options']['ssl']['peer_certificate'] ) ) {
		return null;
	}
	$x = openssl_x509_parse( $p['options']['ssl']['peer_certificate'] );
	return isset( $x['validTo_time_t'] ) ? (int) floor( ( $x['validTo_time_t'] - time() ) / DAY_IN_SECONDS ) : null;
}

/** Собрать и отправить. Возвращает [ 'mail' => bool|null, 'tg' => bool|null, 'summary' => '' ]. */
function zhdanov_studio_report_send() {
	$rep   = zhdanov_studio_report_build();
	$email = trim( (string) zhdanov_core_opt( 'studio_email' ) );
	$token = trim( (string) zhdanov_core_opt( 'studio_tg_token' ) );
	$chat  = trim( (string) zhdanov_core_opt( 'studio_tg_chat' ) );
	$res   = array( 'mail' => null, 'tg' => null, 'tg_err' => '', 'mail_err' => '', 'summary' => $rep['summary'] );
	if ( $token && ! $chat && function_exists( 'zhdanov_core_tg_detect' ) ) {
		$chat = (string) zhdanov_core_tg_detect( $token );
		if ( $chat ) {
			$o                   = (array) get_option( 'zhdanov_core', array() );
			$o['studio_tg_chat'] = $chat;
			update_option( 'zhdanov_core', $o );
		}
	}
	if ( $email && is_email( $email ) ) {
		delete_option( 'zhdanov_mail_error' );
		$res['mail'] = wp_mail( $email, '[' . get_bloginfo( 'name' ) . '] Отчёт сайта: ' . $rep['summary'], $rep['text'] );
		$me          = get_option( 'zhdanov_mail_error' );
		if ( ! $res['mail'] && $me && function_exists( 'zhdanov_mail_error_human' ) ) {
			$res['mail_err'] = zhdanov_mail_error_human( $me['msg'] );
		}
	}
	if ( $token && $chat && function_exists( 'zhdanov_core_tg_send' ) ) {
		$text = $rep['text'];
		if ( mb_strlen( $text ) > 3900 ) {
			$text = mb_substr( $text, 0, 3850 ) . "\n…";
		}
		$r = wp_remote_post( 'https://api.telegram.org/bot' . rawurlencode( $token ) . '/sendMessage', array( 'timeout' => 15, 'body' => array( 'chat_id' => $chat, 'text' => $text, 'disable_web_page_preview' => 'true' ) ) );
		if ( is_wp_error( $r ) ) { // повтор: хостинг иногда не дозванивается с первого раза
			sleep( 2 );
			$r = wp_remote_post( 'https://api.telegram.org/bot' . rawurlencode( $token ) . '/sendMessage', array( 'timeout' => 15, 'body' => array( 'chat_id' => $chat, 'text' => $text, 'disable_web_page_preview' => 'true' ) ) );
		}
		if ( is_wp_error( $r ) ) {
			$res['tg']     = false;
			$res['tg_err'] = 'сайт не смог подключиться к Telegram (' . $r->get_error_message() . '). Возможно, хостинг не пускает к api.telegram.org: спросите поддержку хостинга';
		} else {
			$j             = json_decode( wp_remote_retrieve_body( $r ), true );
			$res['tg']     = ! empty( $j['ok'] );
			$res['tg_err'] = $res['tg'] ? '' : 'Telegram ответил: ' . ( isset( $j['description'] ) ? $j['description'] : wp_remote_retrieve_response_code( $r ) ) . ' (проверьте токен и что боту писали)';
		}
	}
	update_option( 'zhdanov_studio_report_last', array( 'time' => time(), 'res' => $res, 'text' => $rep['text'] ), false );
	return $res;
}

add_action( 'admin_post_zhdanov_studio_report_now', function () {
	check_admin_referer( 'zhdanov_studio_report_now' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Нет доступа' );
	}
	// собираем в фоне: проверка всех страниц и ожидание почты могут занять больше минуты, а хостинг обрывает долгие запросы
	update_option( 'zhdanov_studio_report_queued', time(), false );
	wp_schedule_single_event( time(), 'zhdanov_studio_report_bg' );
	spawn_cron();
	wp_safe_redirect( admin_url( 'admin.php?page=zhdanov-health&rep=queued#zh-report' ) );
	exit;
} );

/** Блок на странице «Здоровье сайта». */
function zhdanov_studio_report_section() {
	$email = zhdanov_core_opt( 'studio_email' );
	$tg    = zhdanov_core_opt( 'studio_tg_token' );
	$last  = get_option( 'zhdanov_studio_report_last' );
	$next  = wp_next_scheduled( 'zhdanov_studio_report' );
	echo '<h2 id="zh-report">Отчёт разработчику (раз в неделю)</h2>';
	$queued = (int) get_option( 'zhdanov_studio_report_queued' );
	if ( $queued && ( ! $last || $last['time'] < $queued ) ) {
		echo '<div class="notice notice-info"><p>Отчёт собирается на сервере: сайт проверяет все страницы. Через 1–2 минуты он придёт' . ( zhdanov_core_opt( 'studio_tg_token' ) ? ' в Telegram' : '' ) . ( zhdanov_core_opt( 'studio_email' ) ? ' и на почту' : '' ) . '. <a href="' . esc_url( admin_url( 'admin.php?page=zhdanov-health&rep=1#zh-report' ) ) . '">Обновить страницу</a>, чтобы увидеть результат.</p></div>';
	} elseif ( isset( $_GET['rep'] ) && $last ) { // phpcs:ignore
		$r   = $last['res'];
		$msg = array();
		if ( null !== $r['mail'] ) {
			$me    = get_option( 'zhdanov_mail_error' );
			$msg[] = 'письмо ' . ( $r['mail'] ? 'отправлено' : 'не ушло: ' . ( $me && ! empty( $me['msg'] ) && function_exists( 'zhdanov_mail_error_human' ) ? zhdanov_mail_error_human( $me['msg'] ) : 'проверьте отправку почты в разделе «Заявки с сайта»' ) );
		}
		if ( null !== $r['tg'] ) {
			$msg[] = 'Telegram ' . ( $r['tg'] ? 'отправлен ✅' : 'не ушёл: ' . ( ! empty( $r['tg_err'] ) ? $r['tg_err'] : 'проверьте токен; напишите боту любое сообщение' ) );
		}
		echo '<div class="notice notice-' . ( $msg ? 'success' : 'warning' ) . '"><p>' . ( $msg ? 'Отчёт: ' . esc_html( $r['summary'] ) . '; ' . esc_html( implode( ', ', $msg ) ) . '.' : 'Отчёт собран, но отправлять некуда: заполните почту или Telegram в Жданов → «Отчёт разработчику».' ) . '</p></div>';
	}
	echo '<p style="max-width:760px">Каждый понедельник в 9:00 сайт проверяет себя и присылает разработчику письмо и сообщение в Telegram: битые ссылки и фото, заголовки и описания для поиска, адреса «не найдено», закрыт ли сайт от поисковиков, обновления, заявки за неделю. ';
	echo 'Куда: ' . ( $email ? '<b>' . esc_html( $email ) . '</b>' : 'почта не указана' ) . ', ' . ( $tg ? 'Telegram подключён' : 'Telegram не подключён' ) . ' (<a href="' . esc_url( admin_url( 'admin.php?page=zhdanov-core#studio' ) ) . '">настроить</a>).';
	if ( $next ) {
		echo ' Следующий: ' . esc_html( wp_date( 'd.m.Y H:i', $next ) ) . '.';
	}
	if ( $last ) {
		echo ' Последний: ' . esc_html( wp_date( 'd.m.Y H:i', $last['time'] ) . ', ' . $last['res']['summary'] ) . '.';
	}
	echo '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="zhdanov_studio_report_now">';
	wp_nonce_field( 'zhdanov_studio_report_now' );
	echo '<button class="button button-primary" onclick="this.textContent=\'Проверяю сайт, около минуты…\'">Прислать отчёт сейчас</button></form>';
	if ( $last && ! empty( $last['text'] ) ) {
		echo '<details style="margin-top:10px;max-width:980px"' . ( isset( $_GET['rep'] ) ? ' open' : '' ) . '><summary style="cursor:pointer">Последний отчёт целиком</summary><pre style="white-space:pre-wrap;background:#fff;padding:12px;border:1px solid #dcdcde">' . esc_html( $last['text'] ) . '</pre></details>'; // phpcs:ignore
	}
}
