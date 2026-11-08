<?php
/**
 * Часть 3: школы РФ («Сведения об образовательной организации» + версия для слабовидящих),
 * базовая защита, памятка клиенту в консоли, пожертвования, лента Telegram-канала.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ===================== Сведения об образовательной организации ===================== */
/* Структура по приказу Рособрнадзора № 1493 от 04.08.2023: адреса подразделов фиксированы. */
function zhdanov_sveden_sections() {
	return array(
		'common'       => array( 'Основные сведения', 'Полное и сокращённое наименование: [ ]. Дата создания: [ ]. Учредитель: [ ]. Место нахождения: [адрес]. Режим и график работы: [ ]. Телефон: [ ], почта: [ ]. Места осуществления образовательной деятельности: [ ].' ),
		'struct'       => array( 'Структура и органы управления образовательной организацией', 'Структурные подразделения и органы управления: [название, руководитель, адрес, почта, положение о подразделении (ссылка на документ)].' ),
		'document'     => array( 'Документы', 'Устав, лицензия на образовательную деятельность (выписка из реестра), правила внутреннего распорядка, локальные нормативные акты, отчёт о самообследовании, предписания органов надзора и отчёты об их исполнении. Добавьте файлы в раздел «Документы» и вставьте его блок сюда.' ),
		'education'    => array( 'Образование', 'Реализуемые образовательные программы: [название, уровень, форма обучения, срок, язык, учебный план, рабочие программы, календарный график]. Численность обучающихся: [ ].' ),
		'managers'     => array( 'Руководство', 'Руководитель: [ФИО, должность, телефон, почта]. Заместители: [ ]. Руководители филиалов: [ ].' ),
		'employees'    => array( 'Педагогический состав', 'Для каждого педагога: ФИО, должность, преподаваемые дисциплины, уровень образования, квалификация, учёная степень и звание (при наличии), повышение квалификации, стаж работы по специальности.' ),
		'objects'      => array( 'Материально-техническое обеспечение и оснащённость образовательного процесса. Доступная среда', 'Оборудованные учебные кабинеты, средства обучения, библиотека, спортивные объекты, питание, охрана здоровья, доступ к информационным системам. Условия для инвалидов и лиц с ОВЗ: [ ].' ),
		'paid_edu'     => array( 'Платные образовательные услуги', 'Порядок оказания платных услуг, образец договора, стоимость обучения: [ ].' ),
		'budget'       => array( 'Финансово-хозяйственная деятельность', 'Объём деятельности за счёт бюджета и по договорам. Поступление и расходование средств по итогам года: [ ].' ),
		'vacant'       => array( 'Вакантные места для приёма (перевода) обучающихся', 'Количество вакантных мест по каждой программе: [ ].' ),
		'grants'       => array( 'Стипендии и меры поддержки обучающихся', 'Стипендии, общежитие, меры поддержки: [ ] (или «не предусмотрены»).' ),
		'inter'        => array( 'Международное сотрудничество', 'Договоры с иностранными организациями: [ ] (или «не заключались»).' ),
		'catering'     => array( 'Организация питания в образовательной организации', 'Условия питания, меню, поставщик: [ ] (для школ и детских садов).' ),
		'eduStandarts' => array( 'Образовательные стандарты и требования', 'Ссылки на применяемые ФГОС или самостоятельно установленные стандарты: [ ].' ),
	);
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'zhdanov-core', 'Школа: обязательный раздел', 'Школа (сведения)', 'manage_options', 'zhdanov-sveden', 'zhdanov_sveden_page' );
}, 24 );

function zhdanov_sveden_page() {
	$done = '';
	if ( isset( $_POST['zh_sveden'] ) && check_admin_referer( 'zhdanov_sveden' ) ) {
		$parent = get_page_by_path( 'sveden' );
		$pid    = $parent ? $parent->ID : wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Сведения об образовательной организации', 'post_name' => 'sveden' ) );
		$list   = '';
		$n      = 0;
		foreach ( zhdanov_sveden_sections() as $slug => $s ) {
			$ex = get_page_by_path( 'sveden/' . $slug );
			if ( ! $ex ) {
				wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_parent' => $pid, 'post_title' => $s[0], 'post_name' => $slug, 'menu_order' => $n, 'post_content' => '<!-- wp:paragraph --><p>' . esc_html( $s[1] ) . '</p><!-- /wp:paragraph -->' ) );
			}
			$list .= '<li><a href="' . esc_url( home_url( '/sveden/' . $slug . '/' ) ) . '">' . esc_html( $s[0] ) . '</a></li>';
			$n++;
		}
		wp_update_post( array( 'ID' => $pid, 'post_content' => '<!-- wp:list --><ul class="wp-block-list">' . $list . '</ul><!-- /wp:list -->' ) );
		$o           = (array) get_option( 'zhdanov_core', array() );
		$o['a11y_on'] = 1;
		update_option( 'zhdanov_core', $o );
		$done = 'Создано: раздел /sveden/ и ' . count( zhdanov_sveden_sections() ) . ' подразделов; включена кнопка «Версия для слабовидящих». Заполните все [поля] и приложите документы.';
	}
	?>
	<div class="wrap"><h1>Школа: «Сведения об образовательной организации»</h1>
	<?php if ( $done ) : ?><div class="notice notice-success"><p><?php echo esc_html( $done ); ?></p></div><?php endif; ?>
	<p>Обязательный раздел для сайтов образовательных организаций в России (закон «Об образовании», ст. 29; приказ Рособрнадзора № 1493). Кнопка создаст страницу <code>/sveden/</code> и 14 подразделов с правильными адресами и подсказками, что в них должно быть, и включит версию для слабовидящих. Существующие страницы не трогаются.</p>
	<p>Частные курсы без образовательной лицензии этот раздел делать не обязаны.</p>
	<form method="post"><?php wp_nonce_field( 'zhdanov_sveden' ); ?><input type="hidden" name="zh_sveden" value="1"><?php submit_button( 'Создать раздел «Сведения»' ); ?></form>
	</div>
	<?php
}

/* ===================== Версия для слабовидящих ===================== */
add_action( 'wp_body_open', function () {
	if ( ! zhdanov_core_opt( 'a11y_on' ) ) {
		return;
	}
	echo '<div class="zh-a11y" role="region" aria-label="' . esc_attr( zhdanov_core__( 'Настройки отображения' ) ) . '"><button type="button" class="zh-a11y__toggle" aria-expanded="false">👁 ' . esc_html( zhdanov_core__( 'Версия для слабовидящих' ) ) . '</button>';
	echo '<div class="zh-a11y__panel" hidden><span>' . esc_html( zhdanov_core__( 'Шрифт' ) ) . ':</span> <button type="button" data-a11y="fs" data-v="1">A</button><button type="button" data-a11y="fs" data-v="2" style="font-size:1.2em">A</button><button type="button" data-a11y="fs" data-v="3" style="font-size:1.4em">A</button>';
	echo ' <span>' . esc_html( zhdanov_core__( 'Цвет' ) ) . ':</span> <button type="button" data-a11y="c" data-v="">' . esc_html( zhdanov_core__( 'Обычный' ) ) . '</button><button type="button" data-a11y="c" data-v="bw">' . esc_html( zhdanov_core__( 'Чёрный на белом' ) ) . '</button><button type="button" data-a11y="c" data-v="wb">' . esc_html( zhdanov_core__( 'Белый на чёрном' ) ) . '</button>';
	echo ' <button type="button" data-a11y="img" data-v="toggle">' . esc_html( zhdanov_core__( 'Картинки: вкл/выкл' ) ) . '</button> <button type="button" data-a11y="reset">' . esc_html( zhdanov_core__( 'Сбросить' ) ) . '</button></div></div>';
	// Применяем сохранённые настройки до отрисовки, чтобы страница не мигала.
	echo "<script>try{var z=JSON.parse(localStorage.getItem('zh_a11y')||'{}'),d=document.documentElement;if(z.fs)d.setAttribute('data-a11y-fs',z.fs);if(z.c)d.setAttribute('data-a11y-c',z.c);if(z.img)d.setAttribute('data-a11y-img','off');}catch(e){}</script>";
}, 1 );

/* ===================== Базовая защита ===================== */
// XML-RPC выключен (через него перебирают пароли), авторов не видно по ?author=1 и через REST.
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'wp_headers', function ( $h ) {
	unset( $h['X-Pingback'] );
	$h['X-Content-Type-Options'] = 'nosniff';
	$h['Referrer-Policy']        = 'strict-origin-when-cross-origin';
	$h['X-Frame-Options']        = 'SAMEORIGIN';
	$h['Permissions-Policy']     = 'camera=(), microphone=(), geolocation=(), interest-cohort=()';
	return $h;
} );
add_action( 'template_redirect', function () {
	if ( ! is_admin() && isset( $_GET['author'] ) && ! current_user_can( 'list_users' ) ) { // phpcs:ignore
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
}, 0 );
add_filter( 'rest_endpoints', function ( $e ) {
	if ( ! current_user_can( 'list_users' ) ) {
		unset( $e['/wp/v2/users'], $e['/wp/v2/users/(?P<id>[\d]+)'] );
	}
	return $e;
} );
// Одинаковая ошибка входа: не подсказываем, что логин существует. Не больше 6 неудачных входов за 15 минут с адреса.
add_filter( 'login_errors', function ( $e ) {
	// понятные подсказки оставляем: нужен код из приложения, код не подошёл, слишком много попыток
	if ( preg_match( '/код из приложения|Код не подошёл|Слишком много попыток/u', (string) $e ) ) {
		return $e;
	}
	return 'Неверный логин или пароль.';
} );
function zhdanov_login_key() {
	return 'zh_login_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
}
add_action( 'wp_login_failed', function () {
	$k = zhdanov_login_key();
	set_transient( $k, (int) get_transient( $k ) + 1, 15 * MINUTE_IN_SECONDS );
} );
add_filter( 'authenticate', function ( $user ) {
	if ( (int) get_transient( zhdanov_login_key() ) >= 6 ) {
		return new WP_Error( 'zh_locked', 'Слишком много попыток входа. Подождите 15 минут.' );
	}
	return $user;
}, 30 );
add_action( 'wp_login', function () {
	delete_transient( zhdanov_login_key() );
} );
remove_action( 'wp_head', 'wp_generator' );

/* ===================== Памятка клиенту в консоли ===================== */
add_action( 'wp_dashboard_setup', function () {
	wp_add_dashboard_widget( 'zhdanov_memo', 'Как пользоваться сайтом', 'zhdanov_memo_widget', null, null, 'normal', 'high' );
} );
function zhdanov_memo_widget() {
	$links = array();
	foreach ( zhdanov_core_types() as $type => $t ) {
		if ( zhdanov_core_type_on( $type ) ) {
			$links[] = '<a href="' . esc_url( admin_url( 'post-new.php?post_type=' . $type ) ) . '">' . esc_html( $t['add'] ) . '</a>';
		}
	}
	if ( zhdanov_core_events_on() ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'post-new.php?post_type=zh_event' ) ) . '">Добавить событие</a>' );
	}
	$leads = wp_count_posts( 'zh_lead' );
	?>
	<p><b>Добавить:</b> <a href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>">Новость</a>, <?php echo implode( ', ', $links ); // phpcs:ignore ?>.</p>
	<p><b>Заявки:</b> <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=zh_lead' ) ); ?>">все заявки</a><?php echo $leads ? ' (' . (int) $leads->private . ')' : ''; ?>. Они приходят на почту<?php echo zhdanov_core_opt( 'tg_chat' ) ? ' и в Telegram' : ''; ?>.</p>
	<p><b>Поправить текст на странице:</b> Страницы → нужная страница → щёлкнуть по тексту → исправить → «Обновить».</p>
	<p><b>Фото:</b> перед загрузкой уменьшите до 2000 px по длинной стороне. Описание фото (alt) заполняйте: так их находит Яндекс.</p>
	<p><b>Контакты, часы, адрес:</b> <a href="<?php echo esc_url( admin_url( 'admin.php?page=zhdanov-core' ) ); ?>">Жданов → Настройки</a>, меняются сразу на всём сайте.</p>
	<p><b>Раз в месяц:</b> загляните в <a href="<?php echo esc_url( admin_url( 'admin.php?page=zhdanov-health' ) ); ?>">Здоровье сайта</a>. Копия сайта делается сама каждый понедельник.</p>
	<p style="color:#50575e">Не нашли, как сделать? Напишите Zhdanov Sites: <a href="https://zhdanovsites.com/" target="_blank">zhdanovsites.com</a>.</p>
	<?php
}

/* ===================== Пожертвования ===================== */
add_filter( 'zhdanov_pay_resolve_extra', function ( $r, $key, $custom ) {
	if ( 'donate' === $key && (int) $custom >= 50 && (int) $custom <= 1000000 ) {
		return array( (int) $custom, zhdanov_core__( 'Пожертвование' ) . ' ' . get_bloginfo( 'name' ) );
	}
	return $r;
}, 10, 3 );

/* ===================== Лента Telegram-канала ===================== */
/** Последние посты публичного канала со страницы t.me/s/<канал>. Кэш на 1 час, без скриптов Telegram на сайте. */
function zhdanov_tg_feed( $channel, $count = 3 ) {
	$channel = preg_replace( '#^(https?://)?(t\.me/)?(s/)?@?#', '', trim( $channel ) );
	$channel = preg_replace( '/[^A-Za-z0-9_]/', '', $channel );
	if ( ! $channel ) {
		return array();
	}
	$key   = 'zh_tgf_' . md5( $channel );
	$posts = get_transient( $key );
	if ( false === $posts ) {
		$posts = array();
		$r     = wp_remote_get( 'https://t.me/s/' . $channel, array( 'timeout' => 10 ) );
		if ( ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r ) ) {
			$html = wp_remote_retrieve_body( $r );
			if ( preg_match_all( '#data-post="([^"]+)".*?(?:<div class="tgme_widget_message_text[^"]*"[^>]*>(.*?)</div>.*?)?<time[^>]+datetime="([^"]+)"#s', $html, $m, PREG_SET_ORDER ) ) {
				foreach ( array_reverse( $m ) as $x ) {
					$text = trim( wp_strip_all_tags( str_replace( array( '<br/>', '<br>' ), "\n", (string) $x[2] ) ) );
					if ( '' === $text ) {
						continue;
					}
					$posts[] = array( 'url' => 'https://t.me/' . $x[1], 'text' => html_entity_decode( $text, ENT_QUOTES ), 'date' => $x[3] );
					if ( count( $posts ) >= 10 ) {
						break;
					}
				}
			}
		}
		set_transient( $key, $posts, $posts ? HOUR_IN_SECONDS : 10 * MINUTE_IN_SECONDS );
	}
	return array_slice( $posts, 0, $count );
}
