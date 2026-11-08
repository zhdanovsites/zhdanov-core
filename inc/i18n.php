<?php
/**
 * Двуязычные сайты (Polylang): на английской версии надписи плагина автоматически по-английски.
 * Тексты из настроек (подпись, текст после заявки, часы, адрес) переводятся в Polylang → Переводы строк.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zhdanov_core_is_en() {
	if ( is_admin() && ! wp_doing_ajax() && ! wp_is_serving_rest_request() ) {
		return false;
	}
	// Русские надписи для русского и близких языков; для всех остальных (en, de, zh, …) английские:
	// иностранцу английский понятнее русского. Свой словарь языка: фильтр zhdanov_core_dict.
	return ! preg_match( '/^(ru|be|uk|kk)/', zhdanov_core_locale() );
}

function zhdanov_core_locale() {
	return (string) ( function_exists( 'pll_current_language' ) && pll_current_language( 'locale' ) ? pll_current_language( 'locale' ) : get_locale() );
}

function zhdanov_core_en_dict() {
	return array(
		// Афиша.
		'Ближайшие' => 'Upcoming', 'Прошедшие' => 'Past', 'Поиск по афише' => 'Search events', 'Найти' => 'Search', 'Билеты' => 'Tickets',
		'Подробнее' => 'Details', 'Вся афиша' => 'All events', 'Билеты проданы' => 'Sold out', 'Перенесено' => 'Postponed', 'Отменено' => 'Cancelled',
		'Пока нет событий. Загляните позже или посмотрите' => 'No upcoming events yet. Check back later or see', 'прошедшие' => 'past events',
		'Когда' => 'When', 'Где' => 'Where', 'Цена' => 'Price', 'Возраст' => 'Age', 'Купить билет' => 'Buy tickets', 'Дата уточняется' => 'Date to be announced',
		'Фильтр афиши' => 'Event filter', 'Страницы афиши' => 'Pages', 'Страницы' => 'Pages', '← Назад' => '← Back', 'Дальше →' => 'Next →',
		// Контакты и маршрут.
		'Показать карту здесь' => 'Show map here', 'Карта загрузится с сервиса Яндекс Карты.' => 'The map loads from Yandex Maps.', 'Карта: как нас найти' => 'Map: how to find us',
		'Маршрут в Яндекс Картах' => 'Directions (Yandex Maps)', 'Маршрут' => 'Directions', 'Позвонить' => 'Call', 'Быстрая связь' => 'Quick contact',
		'ВКонтакте' => 'VK', 'Одноклассники' => 'OK', 'Почта' => 'Email', 'Телефон' => 'Phone',
		// Общие.
		'Поделиться:' => 'Share:', 'Скопировать ссылку' => 'Copy link', 'Ссылка скопирована' => 'Link copied', 'Политика конфиденциальности' => 'Privacy policy',
		'Настройки cookie' => 'Cookie settings', 'Главная' => 'Home', 'Афиша' => 'Events', 'Новости' => 'News', 'Вы здесь' => 'You are here', 'Язык сайта' => 'Language',
		'Поиск' => 'Search', 'Услуги' => 'Services', 'Отзывы' => 'Reviews', 'Альбомы' => 'Albums', 'Каталог' => 'Catalogue',
		// Разделы.
		'Оценка на %s' => 'Rating on %s', 'отзывов' => 'reviews', 'на' => 'as of', 'Читать отзывы' => 'Read reviews', 'Оставить отзыв' => 'Leave a review',
		'Сообщить, если освободится' => 'Notify me if a spot opens', 'Мест сейчас нет. Оставьте почту: если кто-то отменит запись, мы сразу напишем, и вы сможете записаться.' => 'No spots right now. Leave your email and we will write as soon as someone cancels.',
		'Укажите имя, почту и отметьте согласие.' => 'Enter your name, email and tick the consent box.', 'Готово: напишем на почту, как только освободится место.' => 'Done: we will email you as soon as a spot opens.', 'Освободилось место' => 'A spot has opened',
		'Закрытые материалы' => 'Members only', 'Уроки открываются после оплаты курса.' => 'Lessons unlock after you pay for the course.', 'Есть код доступа?' => 'Have an access code?', 'Открыть' => 'Unlock', 'Код не подошёл.' => 'Wrong code.', 'Уже оплатили? Откройте ссылку из письма на этом устройстве.' => 'Already paid? Open the link from the email on this device.', 'Доступ к курсу' => 'Course access',
		'Запишитесь онлайн' => 'Book online', 'Запись' => 'Booking', 'Дата' => 'Date', 'свободно %d из %d' => '%d of %d free', 'Почта для напоминания (необязательно)' => 'Email for a reminder (optional)', 'Комментарий (необязательно)' => 'Comment (optional)',
		'Согласен(на) на обработку персональных данных' => 'I agree to the processing of personal data', 'политика' => 'policy', 'Вы записаны: %s.' => 'You are booked: %s.', 'Вы записаны' => 'You are booked',
		'Укажите имя, телефон и отметьте согласие.' => 'Enter your name, phone and tick the consent box.', 'На это время уже нельзя записаться. Выберите другое.' => 'This time is no longer available. Please choose another.',
		'Вы уже записаны на это время.' => 'You are already booked for this time.', 'Места на это время только что закончились. Выберите другое.' => 'This slot has just filled up. Please choose another.',
		'Запись отменена. Спасибо, что предупредили!' => 'Booking cancelled. Thank you for letting us know!', 'Эта запись уже отменена.' => 'This booking is already cancelled.', 'Занятие уже прошло.' => 'This session has already passed.', 'Отменить запись' => 'Cancel booking', 'Ваша запись' => 'Your booking', 'Напоминание: завтра' => 'Reminder: tomorrow',
		'Версия для слабовидящих' => 'Accessible version', 'Настройки отображения' => 'Display settings', 'Шрифт' => 'Font', 'Цвет' => 'Colour', 'Обычный' => 'Default', 'Чёрный на белом' => 'Black on white', 'Белый на чёрном' => 'White on black', 'Картинки: вкл/выкл' => 'Images on/off', 'Сбросить' => 'Reset',
		'Пожертвование' => 'Donation', 'Поддержать' => 'Donate', 'Читать в Telegram' => 'Read in Telegram', 'Подписаться на канал' => 'Subscribe', 'Свежее в нашем Telegram' => 'Latest from our Telegram', 'Рассчитайте стоимость' => 'Estimate the cost', 'Примерно' => 'About',
		'Откликнуться' => 'Apply', 'Зарплата' => 'Salary', 'График' => 'Schedule', 'Опыт' => 'Experience', 'Занятость' => 'Employment', 'Комнат' => 'Rooms', 'Любое' => 'Any', 'Цена до, ₽' => 'Max price', 'Показать проданные' => 'Show sold',
		'Показать' => 'Show', 'комн.' => 'room', 'этаж' => 'floor', 'Площадь' => 'Area', 'Этаж' => 'Floor', 'Сдача' => 'Completion', 'Адрес' => 'Address', 'Статус' => 'Status', 'В продаже' => 'For sale', 'Аренда' => 'For rent', 'Бронь' => 'Reserved', 'Продано' => 'Sold',
		'Записаться на просмотр' => 'Book a viewing', 'Под эти условия сейчас ничего нет. Напишите нам: подберём.' => 'Nothing matches right now. Message us and we will find options.',
		'Итого' => 'Total', 'Соберите свой торт' => 'Build your cake', 'Итоговую цену подтвердим в сообщении.' => 'We will confirm the final price in a message.',
		'Сортировка' => 'Sort', 'Сначала новые' => 'Newest', 'Дешевле' => 'Price: low to high', 'Дороже' => 'Price: high to low', 'Только в наличии' => 'In stock only',
		'Было' => 'Before', 'Стало' => 'After', 'Сравнить: было и стало' => 'Compare before and after',
		'Ближайший заезд' => 'Next departure', 'Мест нет на все даты' => 'Fully booked', 'Программа' => 'Program', 'Даты заездов' => 'Departure dates', 'мест нет' => 'fully booked', 'Забронировать' => 'Book',
		'Программа по дням' => 'Day by day', 'День' => 'Day', 'Входит в стоимость' => 'Included', 'Оплачивается отдельно' => 'Not included', 'Группа' => 'Group', 'Сложность' => 'Difficulty', 'Что есть' => 'Facilities',
		'Автор' => 'Author', 'Год' => 'Year', 'Страниц' => 'Pages', 'Цена' => 'Price', 'Скачать' => 'Download', 'Где купить' => 'Where to buy', 'Фото к отзыву' => 'Review photo', 'Видео-отзыв' => 'Video review', 'Видео загрузится с сервиса' => 'Video loads from',
		'Оплатить онлайн' => 'Pay online', 'Оплата онлайн' => 'Online payment', 'Почта для чека' => 'Email for the receipt', 'Перейти к оплате' => 'Proceed to payment', 'Отмена' => 'Cancel',
		'Подарочный сертификат' => 'Gift certificate', 'Купить сертификат' => 'Buy a certificate', 'Своя сумма' => 'Custom amount', 'Сумма' => 'Amount', 'Распечатать' => 'Print', 'Действует до' => 'Valid until', 'Для' => 'For',
		'Оплата прошла. Спасибо! Мы свяжемся с вами.' => 'Payment received. Thank you! We will contact you.', 'Оплата не прошла. Попробуйте ещё раз или напишите нам.' => 'Payment failed. Please try again or message us.',
		'Ждём подтверждения оплаты от банка. Обычно это минута.' => 'Waiting for the bank to confirm. Usually a minute.', 'Открыть сертификат' => 'Open the certificate', 'Согласен(на) с условиями' => 'I agree to the terms',
		'Кому сертификат (имя на сертификате)' => 'Recipient name (printed on the certificate)', 'Заказать сертификат' => 'Order a certificate',
		'← Назад' => '← Back', 'Подберём программу за 1 минуту' => 'Find your program in 1 minute', 'Ответьте на несколько вопросов: пришлём подходящий вариант и цену.' => 'Answer a few questions and we will send a matching option and price.',
		'Сегодня' => 'Today', 'Завтра' => 'Tomorrow', 'Ближайших свободных окон нет. Напишите нам: подберём время.' => 'No open slots soon. Message us and we will find a time.',
		'Публичная оферта' => 'Public offer', 'Спросить про это' => 'Ask about this', 'Здравствуйте! Вопрос про «%s»:' => 'Hello! A question about “%s”:', 'Скачать подарок' => 'Download the gift', 'Меню' => 'Menu',
		'Язык' => 'Language', 'Все языки' => 'All languages', 'Языки' => 'Languages', 'Разделы меню' => 'Menu sections', 'Аллергены' => 'Allergens',
		'Маршрут' => 'Directions', 'Показать все адреса на карте' => 'Show all locations on the map', 'Показать на карте' => 'Show on the map', 'Карта загрузится с сервиса Яндекс Карты.' => 'The map loads from Yandex Maps.',
		'До' => 'Until', 'Хочу акцию' => 'Get the offer', 'Эта акция уже закончилась.' => 'This offer has ended.', 'Образование и сертификаты' => 'Education and certificates',
		'Записаться' => 'Book now', 'Заказать' => 'Order', 'Узнать о поступлении' => 'Notify me', 'В наличии' => 'In stock', 'Под заказ' => 'Made to order',
		'Нет в наличии' => 'Out of stock', 'Наличие' => 'Availability', 'Артикул' => 'SKU', 'Длительность' => 'Duration', 'Все' => 'All',
		'Все услуги и цены' => 'All services and prices', 'Все отзывы' => 'All reviews', 'Все альбомы' => 'All albums', 'Весь каталог' => 'Full catalogue',
		'Здесь пока пусто.' => 'Nothing here yet.', 'Было:' => 'Was:', 'Автор' => 'Author', 'Оценка' => 'Rating', 'Источник' => 'Source', 'Фото' => 'Photos',
		'Отзыв на' => 'Review on', 'Отзыв:' => 'Review:', 'Другие события' => 'More events', 'Другие услуги' => 'More services', 'Ещё отзывы' => 'More reviews',
		'Другие альбомы' => 'More albums', 'Похожие товары' => 'Similar products', 'Читайте также' => 'Read more', 'Предыдущая' => 'Previous', 'Следующая' => 'Next',
		// Курсы.
		'Старт' => 'Starts', 'Старт по набору группы' => 'Starts when the group is full', 'по набору группы' => 'when the group is full', 'от' => 'from',
		'Цена до %s' => 'Price until %s', 'до %1$s, потом %2$s' => 'until %1$s, then %2$s', 'Программа' => 'Programme', 'Тарифы' => 'Plans',
		'Выбрать' => 'Choose', 'Формат' => 'Format', 'Все программы' => 'All programmes', 'Другие программы' => 'More programmes',
		// Расписание.
		'Расписание скоро появится.' => 'The schedule is coming soon.', 'сегодня' => 'today', 'Всё расписание' => 'Full schedule', 'Расписание' => 'Schedule',
		'Текст скопирован: вставьте его в чат.' => 'Text copied: paste it into the chat.',
		// Форма.
		'Оставьте заявку' => 'Get in touch', 'Имя' => 'Name', 'Сообщение' => 'Message', '(необязательно)' => '(optional)', 'Отправить' => 'Send',
		'Я согласен(на) на обработку персональных данных' => 'I agree to the processing of my personal data',
		'Я согласен(на) на обработку персональных данных в соответствии с' => 'I agree to the processing of my personal data in accordance with the',
		'политикой конфиденциальности' => 'privacy policy', 'Или напишите сразу:' => 'Or message us directly:',
		'Не получилось отправить. Позвоните нам или напишите в мессенджер.' => 'Could not send. Please call us or send a message.',
	);
}

/** Перевод одной строки (для PHP-кода). */
function zhdanov_core_dict() {
	static $cache = array();
	$loc = zhdanov_core_locale();
	if ( ! isset( $cache[ $loc ] ) ) {
		$cache[ $loc ] = apply_filters( 'zhdanov_core_dict', zhdanov_core_en_dict(), $loc );
	}
	return $cache[ $loc ];
}

function zhdanov_core__( $s ) {
	if ( ! zhdanov_core_is_en() ) {
		return $s;
	}
	$d = zhdanov_core_dict();
	return isset( $d[ $s ] ) ? $d[ $s ] : $s;
}

/** Перевод готового HTML: только целые надписи (текст между тегами и подписи кнопок), без замены кусков слов. */
function zhdanov_core_translate_html( $html ) {
	if ( ! zhdanov_core_is_en() || '' === $html ) {
		return $html;
	}
	$d    = zhdanov_core_dict();
	$html = preg_replace_callback(
		'/>([^<>]+)</u',
		function ( $m ) use ( $d ) {
			$t = trim( $m[1] );
			if ( isset( $d[ $t ] ) ) {
				return '>' . str_replace( $t, $d[ $t ], $m[1] ) . '<';
			}
			return $m[0];
		},
		$html
	);
	return preg_replace_callback(
		'/(aria-label|placeholder|title)="([^"]+)"/u',
		function ( $m ) use ( $d ) {
			return isset( $d[ $m[2] ] ) ? $m[1] . '="' . esc_attr( $d[ $m[2] ] ) . '"' : $m[0];
		},
		$html
	);
}

add_filter( 'render_block', function ( $html, $block ) {
	if ( empty( $block['blockName'] ) || in_array( $block['blockName'], array( 'zhdanov/csec', 'zhdanov/cwrap', 'zhdanov/craw' ), true ) ) {
		return $html; // разделы концепта переводятся самим концептом, не трогаем
	}
	if ( 0 === strpos( $block['blockName'], 'zhdanov/' ) || in_array( $block['blockName'], array( 'core/query-title', 'core/post-navigation-link', 'core/heading' ), true ) ) {
		return zhdanov_core_translate_html( $html );
	}
	return $html;
}, 20, 2 );

add_filter( 'post_type_archive_title', 'zhdanov_core__' );

/* Тексты из настроек: в Polylang → Переводы строк. */
function zhdanov_core_translatable_opts() {
	return array( 'form_done', 'credit_text', 'bar_label', 'hours', 'address', 'person_job' );
}
add_action( 'init', function () {
	if ( ! function_exists( 'pll_register_string' ) ) {
		return;
	}
	foreach ( zhdanov_core_translatable_opts() as $k ) {
		$v = zhdanov_core_opt( $k );
		if ( $v ) {
			pll_register_string( 'zhdanov_' . $k, $v, 'Жданов', false );
		}
	}
} );

/* Polylang: все разделы «Жданов» переводимые сами, без ручной настройки (заявки и оплаты не переводятся). */
add_filter( 'pll_get_post_types', function ( $types, $is_settings ) {
	$add = array( 'zh_event' => 'zh_event' );
	foreach ( zhdanov_core_types() as $t => $cfg ) {
		$add[ $t ] = $t;
	}
	return $is_settings ? $types : array_merge( $types, $add );
}, 10, 2 );
add_filter( 'pll_get_taxonomies', function ( $tax, $is_settings ) {
	$add = array( 'zh_event_cat' => 'zh_event_cat' );
	foreach ( zhdanov_core_types() as $cfg ) {
		if ( ! empty( $cfg['tax'][0] ) ) {
			$add[ $cfg['tax'][0] ] = $cfg['tax'][0];
		}
	}
	return $is_settings ? $tax : array_merge( $tax, $add );
}, 10, 2 );
