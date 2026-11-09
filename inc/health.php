<?php
/**
 * «Здоровье сайта»: чек-лист перед сдачей и раз в месяц.
 * Каждая строка: что не так, почему важно, куда нажать. Ничего не меняет сама.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'zhdanov-core', 'Здоровье сайта', 'Здоровье сайта', 'manage_options', 'zhdanov-health', 'zhdanov_health_page' );
}, 25 );

/** Проверка Telegram: найти чат и отправить пробное сообщение. */
add_action( 'admin_post_zhdanov_tg_test', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'zhdanov_tg_test' ) ) {
		wp_die( 'Нет доступа.' );
	}
	$o     = (array) get_option( 'zhdanov_core', array() );
	$token = isset( $o['tg_token'] ) ? trim( $o['tg_token'] ) : '';
	$msg   = 'tg_none';
	if ( $token ) {
		if ( empty( $o['tg_chat'] ) ) {
			$chat = zhdanov_core_tg_detect( $token );
			if ( $chat ) {
				$o['tg_chat'] = $chat;
				update_option( 'zhdanov_core', $o );
			}
		}
		$msg = 'tg_fail';
		if ( ! empty( $o['tg_chat'] ) ) {
			$r = zhdanov_core_tg_send( $token, $o['tg_chat'], 'Проверка: заявки с сайта ' . get_bloginfo( 'name' ) . ' будут приходить сюда.' );
			if ( ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r ) ) {
				$msg = 'tg_ok';
			}
		}
	}
	wp_safe_redirect( admin_url( 'admin.php?page=zhdanov-health&msg=' . $msg ) );
	exit;
} );

function zhdanov_health_checks() {
	$c     = array(); // [статус ok|warn|bad, что, почему/как, ссылка]
	$set   = admin_url( 'admin.php?page=zhdanov-core' );
	$o     = (array) get_option( 'zhdanov_core', array() );
	$opt   = function ( $k ) use ( $o ) {
		return isset( $o[ $k ] ) ? $o[ $k ] : zhdanov_core_opt( $k );
	};
	$add   = function ( $ok, $title, $fix, $url = '', $level = 'bad' ) use ( &$c ) {
		$c[] = array( $ok ? 'ok' : $level, $title, $ok ? '' : $fix, $ok ? '' : $url );
	};
	// «Продаёт»: включён раздел с ценами И в нём есть опубликованные позиции; «берёт оплату»: касса или кнопка «Оплатить онлайн»
	$sell_types = array();
	foreach ( zhdanov_core_types() as $pt => $t ) {
		if ( isset( $t['module'] ) && in_array( $t['module'], array( 'mod_services', 'mod_catalog', 'mod_courses', 'mod_menu' ), true ) && $opt( $t['module'] ) ) {
			$sell_types[] = $pt;
		}
	}
	$sells = $sell_types && get_posts( array( 'post_type' => $sell_types, 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids' ) );
	$takes = $opt( 'pay_provider' ) || ( $sells && get_posts( array( 'post_type' => $sell_types, 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_zh_paylink', 'meta_compare' => '!=', 'meta_value' => '' ) ) ); // phpcs:ignore

	// Основное.
	$add( (bool) get_option( 'blog_public' ), 'Сайт открыт для поисковиков', 'Сейчас стоит «Попросить поисковые системы не индексировать сайт». На время разработки это правильно, перед сдачей снимите галочку.', admin_url( 'options-reading.php' ), 'warn' );
	$add( is_ssl() || 0 === strpos( home_url(), 'https' ), 'Сайт работает по https', 'Без https браузер пишет «Не защищено», формы теряют доверие. Включите SSL у хостинга и поменяйте адрес в Настройки → Общие.', admin_url( 'options-general.php' ) );
	$add( '' !== get_option( 'permalink_structure' ), 'Понятные адреса страниц', 'Адреса вида ?p=123 хуже для поиска. Выберите «Название записи».', admin_url( 'options-permalink.php' ) );
	$tz = get_option( 'timezone_string' );
	$add( $tz && 'UTC' !== $tz, 'Часовой пояс задан городом', 'Без него «Открыто сейчас», таймеры акций и расписание показывают не то время. Выберите город, например «Москва».', admin_url( 'options-general.php' ) );
	$add( (bool) get_bloginfo( 'description' ), 'Есть короткое описание сайта', 'Одна фраза о том, что это и где. Нужна для поиска и превью ссылок.', admin_url( 'options-general.php' ), 'warn' );
	$add( (bool) get_option( 'site_icon' ), 'Есть значок сайта (фавикон)', 'Квадратная картинка 512×512: видна во вкладке браузера и в поиске Яндекса.', admin_url( 'customize.php?autofocus[section]=title_tagline' ), 'warn' );
	$add( (bool) $opt( 'og_default' ), 'Есть картинка для превью ссылок', 'Без неё ссылка в мессенджере без картинки. Загрузите 1200×630 и вставьте ссылку в настройках.', $set . '#stats', 'warn' );

	// Контакты и заявки.
	$has_contact = $opt( 'phone' ) || $opt( 'max' ) || $opt( 'telegram' ) || $opt( 'whatsapp' );
	$add( $has_contact, 'Есть телефон или мессенджер', 'Кнопки «Записаться» и нижняя панель на телефоне не работают без контактов.', $set . '#contacts' );
	$smtp = ( function_exists( 'zhdanov_mail_server' ) && zhdanov_mail_server() ) || defined( 'WPMS_ON' ) || zhdanov_plugin_active( 'wp-mail-smtp/wp_mail_smtp.php' ) || zhdanov_plugin_active( 'fluent-smtp/fluent-smtp.php' );
	$merr = get_option( 'zhdanov_mail_error' );
	if ( $merr && ! empty( $merr['msg'] ) ) {
		$add( false, 'Письма с сайта уходят', 'Последнее письмо не ушло (' . wp_date( 'd.m H:i', $merr['time'] ) . '): ' . zhdanov_mail_error_human( $merr['msg'] ) . '.', $set . '#form' );
	}
	$tg   = $opt( 'tg_token' ) && $opt( 'tg_chat' );
	$add( $smtp || $tg, 'Заявки точно дойдут', 'Обычная почта WordPress часто попадает в спам или не уходит. Подключите заявки в Telegram (раздел «Запись, подарок, Telegram») или почту Яндекса / Mail.ru в разделе «Заявки с сайта». Заявки в любом случае сохраняются в «Заявки с сайта».', $set . '#extra', 'warn' );
	$add( ! $opt( 'tg_token' ) || $opt( 'tg_chat' ), 'Telegram-бот знает, куда писать', 'Токен есть, номера чата нет. Напишите боту любое сообщение и нажмите «Проверить Telegram» ниже.', '', 'warn' );

	// Закон.
	$pp = (int) $opt( 'privacy_page' ) ? (int) $opt( 'privacy_page' ) : (int) get_option( 'wp_page_for_privacy_policy' );
	$add( $pp && 'publish' === get_post_status( $pp ), 'Опубликована политика конфиденциальности', 'Обязательна, если на сайте есть форма или Метрика (152-ФЗ). Шаблон: Страницы → Добавить → «Готовые страницы».', $set . '#stats' );
	if ( $pp && 'publish' === get_post_status( $pp ) ) {
		$add( false === strpos( get_post_field( 'post_content', $pp ), '[' ), 'В политике заполнены все [поля]', 'В тексте остались квадратные скобки из шаблона.', get_edit_post_link( $pp, 'raw' ) );
	}
	if ( function_exists( 'zhdanov_indexnow_status' ) && $opt( 'indexnow_on' ) && get_option( 'blog_public' ) ) {
		$il = get_option( 'zh_indexnow_last' );
		$add( ! $il || in_array( (int) $il['code'], array( 200, 202 ), true ), 'Яндекс узнаёт о новых страницах сразу (IndexNow)', zhdanov_indexnow_status(), '', 'warn' );
	}
	$sec = get_option( 'zh_sec_last' );
	if ( function_exists( 'zhdanov_sec_scan' ) ) {
		if ( is_array( $sec ) && $sec['bad'] ) {
			$add( false, 'Проверка безопасности без находок', implode( '; ', $sec['bad'] ), admin_url( 'admin.php?page=zhdanov-security' ), 'bad' );
		} else {
			$add( is_array( $sec ) && $sec['time'] > time() - 10 * DAY_IN_SECONDS, 'Проверка безопасности без находок', is_array( $sec ) ? 'Последняя проверка давно: ' . wp_date( 'd.m.Y', $sec['time'] ) . '.' : 'Проверок ещё не было: Жданов → «Безопасность» → «Проверить сейчас».', admin_url( 'admin.php?page=zhdanov-security' ), 'warn' );
		}
	}
	$pcl = get_option( 'zh_pc_last' );
	if ( is_array( $pcl ) ) {
		$add( ! $pcl['bad'], 'Страницы в порядке после обновлений', 'Проверка ' . wp_date( 'd.m.Y H:i', $pcl['time'] ) . ': ' . implode( '; ', array_map( function ( $k, $v ) { return $k . ' ' . implode( ', ', $v ); }, array_keys( (array) $pcl['bad'] ), (array) $pcl['bad'] ) ), admin_url( 'admin.php?page=zhdanov-selfupdate' ), 'bad' );
	}
	if ( function_exists( 'zhdanov_backup_dir' ) ) {
		$bf = glob( zhdanov_backup_dir() . '/recept-*.zip' );
		$bt = $bf ? max( array_map( 'filemtime', $bf ) ) : 0;
		$bs = get_option( 'zhdanov_backup_status' );
		$tg = is_array( $bs ) && $bs['tg'] && ! preg_grep( '/не ушла/u', $bs['tg'] );
		$add( $bt && $bt > time() - 10 * DAY_IN_SECONDS, 'Есть свежая копия сайта', $bt ? 'Последняя копия ' . wp_date( 'd.m.Y', $bt ) . '. Сделайте копию в «Рецепте сайта».' : 'Копий ещё нет: сделайте в «Рецепте сайта» (кнопка «Сделать копию сейчас»).', admin_url( 'admin.php?page=zhdanov-recipe' ), 'warn' );
		if ( is_array( $bs ) && $bs['tg'] && ! $tg ) {
			$add( false, 'Копия сайта уходит в Telegram', implode( '; ', $bs['tg'] ), admin_url( 'admin.php?page=zhdanov-recipe' ), 'warn' );
		}
	}
	if ( function_exists( 'zhdanov_redirect_check' ) && zhdanov_redirects() ) {
		$rb = zhdanov_redirect_check();
		$add( ! $rb, 'Старые адреса ведут на существующие страницы', count( $rb ) . ' перенаправлений ведут на страницы, которых нет, например /' . (string) key( $rb ) . '. Поправьте в «Переезде».', admin_url( 'admin.php?page=zhdanov-migrate' ), 'warn' );
	}
	if ( function_exists( 'zhdanov_2fa_on' ) ) {
		$no2fa = array();
		foreach ( get_users( array( 'role' => 'administrator', 'number' => 20 ) ) as $au ) {
			if ( ! zhdanov_2fa_on( $au->ID ) ) {
				$no2fa[] = $au->display_name;
			}
		}
		$add( ! $no2fa, 'Администраторы входят с кодом из приложения (2FA)', 'Без кода: ' . implode( ', ', array_slice( $no2fa, 0, 5 ) ) . '. Включается в Профиль → «Вход с кодом из приложения» за минуту: даже если пароль узнают, без телефона не войти.', admin_url( 'profile.php#zh-2fa' ), 'warn' );
	}
	if ( $opt( 'maint_on' ) ) {
		$add( false, 'Сайт открыт для посетителей', 'Включён режим «Сайт готовится»: посетители видят заглушку. Когда сайт готов, снимите галочку в Жданов → «Админка для клиента».', $set . '#client' );
	}
	$add( (bool) $opt( 'cookie_on' ), 'Баннер cookie включён', 'Метрика и карты без согласия посетителя нарушают 152-ФЗ.', $set . '#stats' );
	if ( function_exists( 'zhdanov_gdpr_on' ) && zhdanov_gdpr_on() ) {
		$gp = $pp ? (string) get_post_field( 'post_content', $pp ) : '';
		$add( false !== strpos( $gp, 'zhdanov_privacy_request' ), 'GDPR: на странице политики есть форма «выгрузить / удалить мои данные»', 'Вставьте шаблон «Privacy Policy (GDPR)» из «Готовых страниц» или блок «Шорткод» с [zhdanov_privacy_request].', $pp ? get_edit_post_link( $pp, 'raw' ) : $set . '#stats' );
		$add( (bool) preg_match( '/cookie/i', $gp ), 'GDPR: в политике описаны cookie и аналитика', 'Опишите, какие cookie и сервисы аналитики работают и на каком основании (согласие).', $pp ? get_edit_post_link( $pp, 'raw' ) : '', 'warn' );
	}
	if ( $sells ) {
		$add( $opt( 'legal_name' ) && $opt( 'legal_inn' ), 'Указан продавец (ИП/ООО и ИНН)', $takes ? 'На сайте можно оплатить: покупатель должен видеть, кто продаёт (Закон о защите прав потребителей, ст. 8–9). Заполните «Продавец и закон».' : 'На сайте есть услуги или товары с ценами. Реквизиты продавца в подвале повышают доверие и нужны по закону, если вы продаёте людям напрямую. Заполните «Продавец и закон».', $set . '#legal', $takes ? 'bad' : 'warn' );
	}
	if ( $takes ) { // оферта нужна, когда на сайте можно оплатить
		$op = (int) $opt( 'offer_page' );
		$add( $op && 'publish' === get_post_status( $op ), 'Есть публичная оферта', 'На сайте можно оплатить: условия оплаты, возврата и сроки должны быть в оферте, а ссылка на неё рядом с кнопкой оплаты. Шаблон: «Готовые страницы» → «Публичная оферта».', $set . '#legal', 'warn' );
	}
	if ( in_array( $opt( 'preset' ), array( 'salon' ), true ) ) {
		$add( (bool) $opt( 'med_on' ), 'Плашка «Имеются противопоказания»', 'Для медицинских и косметологических услуг обязательна (ФЗ «О рекламе», ст. 24). Если услуги не медицинские, пункт можно пропустить.', $set . '#legal', 'warn' );
	}
	if ( $opt( 'instagram' ) && 'ru' === $opt( 'region' ) ) {
		$c[] = array( 'ok', 'Пометка о Meta рядом с Instagram выводится сама', '', '' );
	}

	// Содержимое.
	global $wpdb;
	$dash = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status='publish' AND post_type NOT IN ('revision','nav_menu_item','wp_global_styles') AND ( post_content LIKE '%—%' OR post_title LIKE '%—%' )" ); // phpcs:ignore
	$add( 0 === $dash, 'Нет длинных тире «—» в текстах', $dash . ' стр. с длинным тире. Наше правило: заменить на двоеточие, запятую или короткое «-».', admin_url( 'edit.php?post_type=page' ), 'warn' );
	$samples = get_posts( array( 'post_type' => 'any', 'post_status' => 'publish', 'numberposts' => 50, 'fields' => 'ids', 'meta_key' => '_zh_sample', 'meta_value' => 1 ) );
	$add( ! $samples, 'Нет примеров вместо настоящих записей', 'На сайте ' . count( $samples ) . ' примеров из «Наполнить примерами». Замените их настоящими или удалите: Жданов → Пресет ниши → «Удалить все примеры».', admin_url( 'admin.php?page=zhdanov-preset' ) );
	$hello = get_page_by_path( 'hello-world', OBJECT, 'post' );
	$sample = get_page_by_path( 'sample-page' );
	$add( ! ( $hello && 'publish' === $hello->post_status ) && ! ( $sample && 'publish' === $sample->post_status ), 'Удалены «Привет, мир!» и «Пример страницы»', 'Стандартные записи WordPress остались на сайте.', admin_url( 'edit.php' ), 'warn' );
	$noalt = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key='_wp_attachment_image_alt' WHERE p.post_type='attachment' AND p.post_mime_type LIKE 'image/%' AND ( m.meta_value IS NULL OR m.meta_value='' )" ); // phpcs:ignore
	$add( $noalt <= 3, 'У картинок есть описания (alt)', $noalt . ' картинок без описания. Это важно для поиска по картинкам и незрячих посетителей. Медиафайлы → картинка → «Альтернативный текст».', admin_url( 'upload.php?mode=list' ), 'warn' );
	$big   = 0;
	$atts  = get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'numberposts' => 300, 'fields' => 'ids' ) );
	foreach ( $atts as $a ) {
		$f = get_attached_file( $a );
		if ( $f && file_exists( $f ) && filesize( $f ) > 600 * 1024 ) {
			$big++;
		}
	}
	$add( 0 === $big, 'Нет тяжёлых картинок', $big . ' картинок больше 600 КБ. Сайт на телефоне грузится медленнее. Сожмите в WebP (squoosh.app) и загрузите заново.', admin_url( 'upload.php?mode=list' ), 'warn' );
	foreach ( zhdanov_core_types() as $type => $t ) {
		if ( ! $opt( $t['module'] ) || ! post_type_supports( $type, 'thumbnail' ) || in_array( $type, array( 'zh_review', 'zh_class', 'zh_branch', 'zh_dish' ), true ) ) {
			continue;
		}
		$ids = get_posts( array( 'post_type' => $type, 'numberposts' => 200, 'fields' => 'ids', 'post_status' => 'publish' ) );
		$no  = 0;
		foreach ( $ids as $i ) {
			if ( ! has_post_thumbnail( $i ) ) {
				$no++;
			}
		}
		if ( ! $ids ) {
			$c[] = array( 'warn', 'Раздел «' . zhdanov_core_type_label( $type ) . '» включён, но пуст', 'Добавьте записи или выключите раздел в настройках: пустой раздел в меню выглядит недоделанным.', admin_url( 'edit.php?post_type=' . $type ) );
		} elseif ( $no ) {
			$c[] = array( 'warn', '«' . zhdanov_core_type_label( $type ) . '»: ' . $no . ' без обложки', 'Без картинки карточка и превью ссылки выглядят пусто.', admin_url( 'edit.php?post_type=' . $type ) );
		}
	}
	if ( $opt( 'mod_promos' ) ) {
		$old = get_posts( array( 'post_type' => 'zh_promo', 'numberposts' => 50, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_zh_until', 'value' => wp_date( 'Y-m-d\TH:i' ), 'compare' => '<' ) ) ) );
		$add( ! $old, 'Нет закончившихся акций', 'Закончившихся акций: ' . count( $old ) . '. На сайте они уже скрыты; уберите их в черновики или продлите.', admin_url( 'edit.php?post_type=zh_promo' ), 'warn' );
	}
	if ( zhdanov_core_events_on() ) {
		$future = get_posts( array( 'post_type' => 'zh_event', 'numberposts' => 1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_zh_start', 'value' => wp_date( 'Y-m-d' ), 'compare' => '>=' ) ) ) );
		$add( (bool) $future, 'В афише есть будущие события', 'Все события в прошлом: афиша на главной пустая. Добавьте новые или уберите блок афиши.', admin_url( 'edit.php?post_type=zh_event' ), 'warn' );
	}
	if ( $opt( 'booking_on' ) ) {
		$withcap = get_posts( array( 'post_type' => 'zh_class', 'numberposts' => 1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_zh_cap', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ) ) ) );
		$add( (bool) $withcap, 'Онлайн-запись: у занятий указаны места', 'Запись включена, но ни у одного занятия нет «Мест для онлайн-записи»: записаться нельзя.', admin_url( 'edit.php?post_type=zh_class' ) );
	}
	$add( (bool) $opt( 'metrika' ), 'Подключена Яндекс Метрика', 'Без неё не видно, откуда люди и сколько заявок. Номер счётчика: metrika.yandex.ru.', $set . '#stats', 'warn' );
	return $c;
}

function zhdanov_plugin_active( $p ) {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	return is_plugin_active( $p );
}

function zhdanov_health_page() {
	$checks = zhdanov_health_checks();
	$cnt    = array( 'ok' => 0, 'warn' => 0, 'bad' => 0 );
	foreach ( $checks as $ch ) {
		$cnt[ $ch[0] ]++;
	}
	usort( $checks, function ( $a, $b ) {
		$w = array( 'bad' => 0, 'warn' => 1, 'ok' => 2 );
		return $w[ $a[0] ] - $w[ $b[0] ];
	} );
	$msgs = array(
		'tg_ok'   => array( 'success', 'Telegram подключён: пробное сообщение отправлено.' ),
		'tg_fail' => array( 'error', 'Не получилось. Проверьте токен и напишите боту любое сообщение, затем нажмите ещё раз.' ),
		'tg_none' => array( 'warning', 'Сначала вставьте токен бота в настройках (раздел «Запись, подарок, Telegram»).' ),
	);
	$m = isset( $_GET['msg'] ) ? sanitize_key( $_GET['msg'] ) : ''; // phpcs:ignore
	$icon = array( 'ok' => '✅', 'warn' => '⚠️', 'bad' => '❌' );
	?>
	<div class="wrap">
		<h1>Здоровье сайта</h1>
		<?php if ( isset( $msgs[ $m ] ) ) : ?>
			<div class="notice notice-<?php echo esc_attr( $msgs[ $m ][0] ); ?>"><p><?php echo esc_html( $msgs[ $m ][1] ); ?></p></div>
		<?php endif; ?>
		<p style="font-size:15px">Готово: <b><?php echo (int) $cnt['ok']; ?></b> · Стоит поправить: <b><?php echo (int) $cnt['warn']; ?></b> · Важно: <b><?php echo (int) $cnt['bad']; ?></b></p>
		<table class="widefat striped" style="max-width:980px">
			<tbody>
			<?php foreach ( $checks as $ch ) : ?>
				<tr>
					<td style="width:28px;font-size:18px"><?php echo esc_html( $icon[ $ch[0] ] ); ?></td>
					<td><b><?php echo esc_html( $ch[1] ); ?></b>
						<?php if ( $ch[2] ) : ?><br><span style="color:#50575e"><?php echo esc_html( $ch[2] ); ?></span><?php endif; ?></td>
					<td style="width:110px"><?php if ( $ch[3] ) : ?><a class="button" href="<?php echo esc_url( $ch[3] ); ?>">Исправить</a><?php endif; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php zhdanov_health_pages_section(); ?>
		<?php zhdanov_studio_report_section(); ?>
		<h2>Заявки в Telegram</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="zhdanov_tg_test"><?php wp_nonce_field( 'zhdanov_tg_test' ); ?>
			<p>1. В Telegram откройте @BotFather → /newbot → скопируйте токен в настройки. 2. Напишите своему боту «привет» (или добавьте его в рабочий чат). 3. Нажмите кнопку: номер чата подставится сам, придёт пробное сообщение.</p>
			<?php submit_button( 'Проверить Telegram', 'secondary', 'submit', false ); ?>
		</form>
		<details style="margin-top:1.5em;max-width:980px"><summary style="cursor:pointer">Как выглядит сводка по понедельникам</summary><pre style="white-space:pre-wrap;background:#fff;padding:12px;border:1px solid #dcdcde"><?php echo esc_html( zhdanov_weekly_text() ); ?></pre></details>
		<p style="margin-top:2em;color:#50575e">Скорость и доступность проверяйте на pagespeed.web.dev (цель: телефон 95+). Эта страница ничего не меняет на сайте сама.</p>
	</div>
	<?php
}

/* ---------- Сводка в Telegram раз в неделю ---------- */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'zhdanov_weekly' ) ) {
		$next = new DateTimeImmutable( 'next monday 10:00', wp_timezone() );
		wp_schedule_event( $next->getTimestamp(), 'weekly', 'zhdanov_weekly' );
	}
} );

function zhdanov_weekly_text() {
	$since = gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS );
	$leads = get_posts( array( 'post_type' => 'zh_lead', 'post_status' => 'private', 'numberposts' => 500, 'date_query' => array( array( 'after' => $since, 'column' => 'post_date_gmt' ) ) ) );
	$lines = array( 'Сайт ' . get_bloginfo( 'name' ) . ': итоги недели', '' );
	$lines[] = 'Заявок: ' . count( $leads );
	$from = array();
	foreach ( $leads as $l ) {
		$s = (string) get_post_meta( $l->ID, '_zh_src', true );
		$k = $s ? preg_replace( '/:.*$/u', '', $s ) : 'форма';
		if ( preg_match( '/utm_source=([^&|]+)/', $s, $m ) ) {
			$k .= ' / ' . $m[1];
		}
		$from[ $k ] = isset( $from[ $k ] ) ? $from[ $k ] + 1 : 1;
	}
	arsort( $from );
	foreach ( array_slice( $from, 0, 5, true ) as $k => $n ) {
		$lines[] = '  ' . $k . ': ' . $n;
	}
	$todo = array();
	// Сайт «молчит»: давно нет новостей или событий.
	$last = get_posts( array( 'post_type' => array( 'post', 'zh_event', 'zh_promo' ), 'numberposts' => 1, 'post_status' => 'publish' ) );
	if ( $last && ( time() - get_post_time( 'U', true, $last[0] ) ) > 30 * DAY_IN_SECONDS ) {
		$todo[] = '• Сайт молчит больше месяца: добавьте новость, событие или акцию (поисковики любят живые сайты).';
	}
	foreach ( zhdanov_health_checks() as $c ) {
		if ( 'ok' !== $c[0] ) {
			$todo[] = ( 'bad' === $c[0] ? '❗ ' : '• ' ) . $c[1] . ( $c[2] ? ': ' . mb_substr( $c[2], 0, 120 ) : '' );
		}
	}
	if ( $todo ) {
		$lines[] = '';
		$lines[] = 'Стоит поправить (' . count( $todo ) . '):';
		$lines   = array_merge( $lines, array_slice( $todo, 0, 8 ) );
	}
	$lines[] = '';
	$lines[] = 'Все заявки: ' . admin_url( 'edit.php?post_type=zh_lead' );
	return implode( "\n", $lines );
}

add_action( 'zhdanov_weekly', function () {
	$o = (array) get_option( 'zhdanov_core', array() );
	if ( empty( $o['tg_token'] ) || empty( $o['tg_chat'] ) || ( isset( $o['tg_digest'] ) && ! $o['tg_digest'] ) ) {
		return;
	}
	zhdanov_core_tg_send( $o['tg_token'], $o['tg_chat'], zhdanov_weekly_text() );
} );

register_deactivation_hook( ZHDANOV_CORE_DIR . 'zhdanov-core.php', function () {
	wp_clear_scheduled_hook( 'zhdanov_weekly' );
} );

/* ---------- Проверка страниц и журнал «не найдено» ---------- */

function zhdanov_health_pages_section() {
	wp_enqueue_script( 'zhdanov-health-pages', ZHDANOV_CORE_URL . 'assets/health-pages.js', array( 'wp-api-fetch' ), ZHDANOV_CORE_VERSION, true );
	wp_localize_script( 'zhdanov-health-pages', 'zhHealth', array( 'home' => home_url( '/' ) ) );
	$msg = isset( $_GET['redir'] ) ? sanitize_key( $_GET['redir'] ) : ''; // phpcs:ignore
	?>
	<h2 id="pages">Проверка страниц</h2>
	<?php if ( ! get_option( 'blog_public' ) ) : ?>
		<div class="notice notice-warning inline"><p><b>Сайт закрыт от поисковиков</b> (Настройки → Чтение → «Попросить поисковые системы не индексировать сайт»). Это правильно, пока сайт собирается. Перед сдачей снимите галочку. <a href="<?php echo esc_url( admin_url( 'options-reading.php' ) ); ?>">Открыть настройку</a></p></div>
	<?php endif; ?>
	<p style="max-width:780px">Обходит все опубликованные страницы так, как их видит посетитель, и показывает: битые фото и ссылки, страницы без заголовка или описания для поиска, повторяющиеся заголовки, фото без описания (alt), запрет индексации, несколько h1 или ни одного. Ничего не меняет, только показывает. Для сайта на 20–30 страниц это 1–2 минуты.</p>
	<p><button type="button" class="button button-primary" id="zh-hp-go">Проверить страницы сайта</button></p>
	<div id="zh-hp"></div>

	<h2 id="notfound">Адреса, по которым попадают на «не найдено»</h2>
	<?php
	if ( 'ok' === $msg ) {
		echo '<div class="notice notice-success inline"><p>Переадресация добавлена.</p></div>';
	}
	$log   = (array) get_option( 'zhdanov_404', array() );
	$map   = function_exists( 'zhdanov_redirects' ) ? zhdanov_redirects() : array();
	$rows  = array();
	foreach ( $log as $path => $v ) {
		if ( isset( $map[ $path ] ) || preg_match( '#(\.(php|env|git|sql|bak|zip)|wp-login|xmlrpc|/\.well-known/)#i', $path ) ) {
			continue; // уже переадресовано или явно боты
		}
		$rows[ $path ] = $v;
	}
	uasort( $rows, function ( $a, $b ) {
		return $b[0] - $a[0];
	} );
	if ( ! $rows ) {
		echo '<p>Пока нет: все адреса, по которым приходили люди, открываются. Журнал пополняется сам.</p>';
		return;
	}
	?>
	<p style="max-width:780px">Сюда попадают адреса, по которым люди или поисковики пришли и не нашли страницу: старые ссылки, опечатки в рассылках, удалённые страницы. Впишите, куда вести (например <code>/tury/bali/</code> или <code>/</code>), и нажмите «Сохранить»: адрес начнёт переадресовывать (301), поисковики перенесут на новую страницу и позиции.</p>
	<table class="widefat striped" style="max-width:980px"><thead><tr><th>Адрес</th><th style="width:90px">Раз</th><th style="width:140px">Последний</th><th style="width:330px">Куда вести</th></tr></thead><tbody>
	<?php foreach ( array_slice( $rows, 0, 50, true ) as $path => $v ) : ?>
		<tr><td><code>/<?php echo esc_html( $path ); ?></code></td><td><?php echo (int) $v[0]; ?></td><td><?php echo esc_html( $v[1] ); ?></td>
		<td><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:6px">
			<input type="hidden" name="action" value="zhdanov_add_redirect"><input type="hidden" name="from" value="/<?php echo esc_attr( $path ); ?>"><?php wp_nonce_field( 'zhdanov_add_redirect' ); ?>
			<input type="text" name="to" placeholder="/tury/bali/" style="flex:1" required> <button class="button">Сохранить</button>
		</form></td></tr>
	<?php endforeach; ?>
	</tbody></table>
	<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=zhdanov-migrate' ) ); ?>">Все переадресации (Жданов → Переезд)</a></p>
	<?php
}

add_action( 'admin_post_zhdanov_add_redirect', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Нет доступа' );
	}
	check_admin_referer( 'zhdanov_add_redirect' );
	$from = isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '';
	$to   = isset( $_POST['to'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['to'] ) ) ) : '';
	if ( $from && $to && false === strpos( $from, ' ' ) && false === strpos( $to, ' ' ) ) {
		if ( 0 !== strpos( $to, 'http' ) && '/' !== $to[0] ) {
			$to = '/' . $to;
		}
		$text = rtrim( (string) get_option( 'zhdanov_redirects', '' ) );
		update_option( 'zhdanov_redirects', ( $text ? $text . "\n" : '' ) . $from . ' ' . $to, false );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=zhdanov-health&redir=ok#notfound' ) );
	exit;
} );
