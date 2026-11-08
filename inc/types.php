<?php
/**
 * Разделы сайта, которые включаются галочкой: Услуги, Отзывы, Альбомы, Каталог.
 * Одна общая схема: тип записи + поля + шаблоны + блок «Коллекция».
 * Афиша описана отдельно (inc/events.php), но её поля тоже берутся отсюда (zhdanov_core_meta_config).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zhdanov_core_types() {
	return array(
		'zh_service' => array(
			'module'   => 'mod_services',
			'many'     => 'Услуги',
			'one'      => 'Услуга',
			'add'      => 'Добавить услугу',
			'slug'     => 'uslugi',
			'icon'     => 'dashicons-clipboard',
			'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes', 'custom-fields' ),
			'tax'      => array( 'zh_service_cat', 'Группы услуг', 'Группа услуг', 'uslugi/gruppa' ),
			'order'    => 'menu_order',
		),
		'zh_class'   => array(
			'module'   => 'mod_schedule',
			'many'     => 'Расписание',
			'one'      => 'Занятие',
			'add'      => 'Добавить занятие',
			'slug'     => 'raspisanie',
			'icon'     => 'dashicons-calendar',
			'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			'tax'      => array( 'zh_class_cat', 'Направления', 'Направление', 'raspisanie/napravlenie' ),
			'order'    => 'menu_order',
		),
		'zh_course'  => array(
			'module'   => 'mod_courses',
			'many'     => 'Курсы и программы',
			'one'      => 'Курс',
			'add'      => 'Добавить курс',
			'slug'     => 'kursy',
			'icon'     => 'dashicons-welcome-learn-more',
			'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes', 'custom-fields' ),
			'tax'      => null,
			'order'    => 'menu_order',
		),
		'zh_person'  => array(
			'module'   => 'mod_team',
			'many'     => 'Специалисты',
			'one'      => 'Специалист',
			'add'      => 'Добавить специалиста',
			'slug'     => 'specialisty',
			'icon'     => 'dashicons-groups',
			'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes', 'custom-fields' ),
			'tax'      => array( 'zh_person_cat', 'Специальности', 'Специальность', 'specialisty/specialnost' ),
			'order'    => 'menu_order',
		),
		'zh_dish'    => array(
			'module'   => 'mod_menu',
			'many'     => 'Меню',
			'one'      => 'Блюдо',
			'add'      => 'Добавить блюдо',
			'slug'     => 'menu',
			'icon'     => 'dashicons-carrot',
			'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes', 'custom-fields' ),
			'tax'      => array( 'zh_dish_cat', 'Разделы меню', 'Раздел меню', 'menu/razdel' ),
			'order'    => 'menu_order',
		),
		'zh_branch'  => array(
			'module'   => 'mod_branches',
			'many'     => 'Филиалы',
			'one'      => 'Филиал',
			'add'      => 'Добавить филиал',
			'slug'     => 'filialy',
			'icon'     => 'dashicons-location-alt',
			'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes', 'custom-fields' ),
			'tax'      => null,
			'order'    => 'menu_order',
		),
		'zh_tour'    => array(
			'module'   => 'mod_tours',
			'many'     => 'Туры',
			'one'      => 'Тур',
			'add'      => 'Добавить тур',
			'slug'     => 'tury',
			'icon'     => 'dashicons-palmtree',
			'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			'tax'      => array( 'zh_tour_cat', 'Направления', 'Направление', 'tury/napravlenie' ),
			'order'    => 'menu_order',
		),
		'zh_room'    => array(
			'module'   => 'mod_rooms',
			'many'     => 'Залы',
			'one'      => 'Зал',
			'add'      => 'Добавить зал',
			'slug'     => 'zaly',
			'icon'     => 'dashicons-camera',
			'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			'tax'      => null,
			'order'    => 'menu_order',
		),
		'zh_book'    => array(
			'module'   => 'mod_books',
			'many'     => 'Книги',
			'one'      => 'Книга',
			'add'      => 'Добавить книгу',
			'slug'     => 'knigi',
			'icon'     => 'dashicons-book',
			'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			'tax'      => array( 'zh_book_cat', 'Серии и жанры', 'Серия или жанр', 'knigi/seriya' ),
			'order'    => 'menu_order',
		),
		'zh_job'     => array(
			'module'   => 'mod_jobs',
			'many'     => 'Вакансии',
			'one'      => 'Вакансия',
			'add'      => 'Добавить вакансию',
			'slug'     => 'vakansii',
			'icon'     => 'dashicons-id-alt',
			'supports' => array( 'title', 'editor', 'excerpt', 'custom-fields' ),
			'tax'      => null,
			'order'    => 'date',
		),
		'zh_doc'     => array(
			'module'   => 'mod_docs',
			'many'     => 'Документы',
			'one'      => 'Документ',
			'add'      => 'Добавить документ',
			'slug'     => 'dokumenty',
			'icon'     => 'dashicons-media-document',
			'supports' => array( 'title', 'excerpt', 'custom-fields' ),
			'tax'      => array( 'zh_doc_cat', 'Группы документов', 'Группа документов', 'dokumenty/gruppa' ),
			'order'    => 'menu_order',
		),
		'zh_estate'  => array(
			'module'   => 'mod_estate',
			'many'     => 'Объекты',
			'one'      => 'Объект',
			'add'      => 'Добавить объект',
			'slug'     => 'obekty',
			'icon'     => 'dashicons-building',
			'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			'tax'      => array( 'zh_estate_cat', 'Районы и проекты', 'Район или проект', 'obekty/rayon' ),
			'order'    => 'date',
		),
		'zh_promo'   => array(
			'module'   => 'mod_promos',
			'many'     => 'Акции',
			'one'      => 'Акция',
			'add'      => 'Добавить акцию',
			'slug'     => 'akcii',
			'icon'     => 'dashicons-megaphone',
			'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			'tax'      => null,
			'order'    => 'date',
		),
		'zh_review'  => array(
			'module'   => 'mod_reviews',
			'many'     => 'Отзывы',
			'one'      => 'Отзыв',
			'add'      => 'Добавить отзыв',
			'slug'     => 'otzyvy',
			'icon'     => 'dashicons-format-quote',
			'supports' => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			'tax'      => null,
			'order'    => 'date',
		),
		'zh_album'   => array(
			'module'   => 'mod_gallery',
			'many'     => 'Альбомы',
			'one'      => 'Альбом',
			'add'      => 'Добавить альбом',
			'slug'     => 'albomy',
			'icon'     => 'dashicons-format-gallery',
			'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			'tax'      => null,
			'order'    => 'date',
		),
		'zh_product' => array(
			'module'   => 'mod_catalog',
			'many'     => 'Каталог',
			'one'      => 'Товар',
			'add'      => 'Добавить товар',
			'slug'     => 'katalog',
			'icon'     => 'dashicons-products',
			'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes', 'custom-fields' ),
			'tax'      => array( 'zh_product_cat', 'Разделы каталога', 'Раздел каталога', 'katalog/razdel' ),
			'order'    => 'menu_order',
		),
	);
}

/** Поля каждого типа: ключ => [подпись, вид, подсказка, варианты]. */
function zhdanov_core_meta_config() {
	return array(
		'zh_event'   => array(
			'_zh_start'   => array( 'Начало *', 'datetime', 'Обязательно: без даты событие не попадёт в афишу.' ),
			'_zh_end'     => array( 'Окончание', 'datetime', '' ),
			'_zh_venue'   => array( 'Площадка', 'text', 'Например: Большой зал' ),
			'_zh_address' => array( 'Адрес площадки', 'text', 'Если пусто: адрес из настроек «Жданов».' ),
			'_zh_price'   => array( 'Цена', 'text', 'Например: от 1 500 ₽ или «Вход свободный»' ),
			'_zh_age'     => array( 'Возраст', 'select', '', array( '' => '(не указан)', '0+' => '0+', '6+' => '6+', '12+' => '12+', '16+' => '16+', '18+' => '18+' ) ),
			'_zh_status'  => array( 'Статус', 'select', '«Отменено» зачеркнёт событие и сообщит поисковикам.', array( 'scheduled' => 'По плану', 'soldout' => 'Билеты проданы', 'postponed' => 'Перенесено', 'cancelled' => 'Отменено' ) ),
			'_zh_tickets' => array( 'Ссылка на билеты', 'url', 'Касса или онлайн-запись.' ),
		),
		'zh_service' => array(
			'_zh_price'     => array( 'Цена', 'text', 'Например: 3 500 ₽ или «от 2 000 ₽».' ),
			'_zh_price_old' => array( 'Старая цена', 'text', 'Если есть скидка: покажем зачёркнутой.' ),
			'_zh_duration'  => array( 'Длительность', 'text', 'Например: 60 минут.' ),
			'_zh_book'      => array( 'Ссылка на запись', 'url', 'Онлайн-запись (YCLIENTS, DIKIDI и т. п.). Если пусто: кнопка ведёт к форме заявки.' ),
			'_zh_paylink'   => array( 'Ссылка на оплату', 'url', 'Ссылка на оплату из кабинета банка или сервиса (ЮKassa, Т-Банк, Продамус, СБП). Появится кнопка «Оплатить онлайн».' ),
		),
		'zh_class'   => array(
			'_zh_day'      => array( 'День недели', 'select', 'Повторяется каждую неделю. Для разового занятия выберите «по дате» и укажите дату ниже.', array( '' => '(по дате)', '1' => 'Понедельник', '2' => 'Вторник', '3' => 'Среда', '4' => 'Четверг', '5' => 'Пятница', '6' => 'Суббота', '7' => 'Воскресенье' ) ),
			'_zh_date'     => array( 'Дата (разовое занятие)', 'date', '' ),
			'_zh_time'     => array( 'Время начала', 'time', 'Например 19:00.' ),
			'_zh_duration' => array( 'Длительность', 'text', 'Например: 60 минут.' ),
			'_zh_who'      => array( 'Кто ведёт', 'text', '' ),
			'_zh_place'    => array( 'Зал, место или «онлайн»', 'text', '' ),
			'_zh_seats'    => array( 'Места', 'text', 'Например: «осталось 3 места» или «мест нет».' ),
			'_zh_cap'      => array( 'Мест для онлайн-записи', 'text', 'Число. Если включена своя запись (Жданов → Настройки → Запись), сайт сам считает свободные места на каждую дату и закрывает запись, когда мест нет. 1 = индивидуальный приём.' ),
			'_zh_price'    => array( 'Цена', 'text', '' ),
			'_zh_book'     => array( 'Ссылка на онлайн-запись', 'url', 'Если пусто: кнопка подставит готовый текст в форму заявки или мессенджер.' ),
		),
		'zh_course'  => array(
			'_zh_start'    => array( 'Старт потока', 'date', 'Если пусто: «Старт по набору группы».' ),
			'_zh_access'   => array( 'Код доступа к урокам', 'text', 'Необязательно. Для блока «Закрытая часть»: ученик вводит код и видит уроки (например, для очных учеников). Смена кода закрывает доступ всем, кто входил по старому коду; оплатившие онлайн не теряют доступ.' ),
			'_zh_duration' => array( 'Длительность', 'text', 'Например: 8 недель, 16 занятий.' ),
			'_zh_format'   => array( 'Формат', 'text', 'Например: онлайн, записи + 4 живые встречи.' ),
			'_zh_lessons'  => array( 'Программа (модули)', 'textarea', 'Каждый модуль с новой строки. Например: «Неделя 1. Знакомство с собой».' ),
			'_zh_tariffs'  => array( 'Тарифы', 'textarea', 'Каждый тариф с новой строки, части через «|»: Название | Цена сейчас | Цена потом | Дата смены цены | Что входит (через «;»). Пример: Базовый | 15 000 ₽ | 19 000 ₽ | 2026-12-15 | Все уроки; Чат группы' ),
			'_zh_book'     => array( 'Ссылка на оплату или запись', 'url', 'Если пусто: кнопка подставит текст в форму заявки или мессенджер.' ),
		),
		'zh_person'  => array(
			'_zh_role'  => array( 'Должность', 'text', 'Например: клинический психолог, преподаватель вокала.' ),
			'_zh_langs' => array( 'Языки', 'text', 'Через запятую: русский, английский. Появится фильтр по языку.' ),
			'_zh_exp'   => array( 'Опыт', 'text', 'Например: 12 лет практики.' ),
			'_zh_edu'   => array( 'Образование и сертификаты', 'textarea', 'Каждый пункт с новой строки.' ),
			'_zh_price' => array( 'Цена приёма', 'text', '' ),
			'_zh_book'  => array( 'Ссылка на онлайн-запись', 'url', 'Если пусто: «Записаться» подставит текст в форму или мессенджер.' ),
		),
		'zh_dish'    => array(
			'_zh_price'     => array( 'Цена', 'text', 'Например: 650 ₽.' ),
			'_zh_weight'    => array( 'Вес или объём', 'text', 'Например: 250 г, 0,4 л.' ),
			'_zh_allergens' => array( 'Аллергены', 'text', 'Например: глютен, орехи, молоко.' ),
			'_zh_tags'      => array( 'Метки', 'text', 'Через запятую: острое, вегетарианское, хит, новинка.' ),
			'_zh_buy'       => array( 'Ссылка на заказ', 'url', 'Доставка или онлайн-заказ. Если пусто: кнопки заказа нет.' ),
		),
		'zh_branch'  => array(
			'_zh_address' => array( 'Адрес *', 'text', 'Город, улица, дом.' ),
			'_zh_lat'     => array( 'Широта', 'text', 'Из Яндекс Карт: правый клик → «Что здесь?».' ),
			'_zh_lng'     => array( 'Долгота', 'text', '' ),
			'_zh_phone'   => array( 'Телефон филиала', 'text', '' ),
			'_zh_hours'   => array( 'Часы работы', 'textarea', 'Каждый день или группа дней с новой строки: «Пн-Пт 10:00-20:00», «Сб 11:00-18:00», «Вс выходной». Так сайт покажет «Открыто сейчас».' ),
		),
		'zh_tour'    => array(
			'_zh_price'    => array( 'Цена', 'text', 'Например: 45 000 ₽ за человека. Точная цена: можно оплатить онлайн.' ),
			'_zh_duration' => array( 'Длительность', 'text', 'Например: 5 дней / 4 ночи.' ),
			'_zh_dates'    => array( 'Даты заездов', 'textarea', 'Каждая дата с новой строки: 2027-05-01 или 01.05.2027. Прошедшие скрываются сами. Можно дописать «мест нет».' ),
			'_zh_days'     => array( 'Программа по дням', 'textarea', 'Каждый день с новой строки: «Заголовок дня: что делаем». Например: «Прилёт: встреча в аэропорту, ужин».' ),
			'_zh_incl'     => array( 'Входит', 'textarea', 'Через точку с запятой: проживание; завтраки; трансферы.' ),
			'_zh_excl'     => array( 'Не входит', 'textarea', 'Через точку с запятой: перелёт; страховка.' ),
			'_zh_level'    => array( 'Сложность', 'text', 'Например: лёгкий, подходит для детей с 7 лет.' ),
			'_zh_group'    => array( 'Группа', 'text', 'Например: до 12 человек.' ),
			'_zh_paylink'   => array( 'Ссылка на оплату', 'url', 'Ссылка на оплату из кабинета банка или сервиса (ЮKassa, Т-Банк, Продамус, СБП). Появится кнопка «Оплатить онлайн».' ),
		),
		'zh_room'    => array(
			'_zh_price'    => array( 'Цена', 'text', 'Например: 2 500 ₽/час.' ),
			'_zh_area'     => array( 'Площадь', 'text', 'Например: 80 м², потолки 4 м.' ),
			'_zh_capacity' => array( 'Вместимость', 'text', 'Например: до 15 человек.' ),
			'_zh_features' => array( 'Что есть', 'textarea', 'Через точку с запятой: циклорама; окна на юг; гримёрка; импульсный свет.' ),
			'_zh_book'     => array( 'Ссылка на бронь', 'url', 'Онлайн-календарь брони (например, Appevent, YCLIENTS). Если пусто: кнопка ведёт в мессенджер.' ),
		),
		'zh_book'    => array(
			'_zh_author'   => array( 'Автор', 'text', '' ),
			'_zh_year'     => array( 'Год', 'text', '' ),
			'_zh_pages'    => array( 'Страниц', 'text', '' ),
			'_zh_isbn'     => array( 'ISBN', 'text', '' ),
			'_zh_price'    => array( 'Цена', 'text', 'Пусто или «бесплатно»: книгу можно скачать.' ),
			'_zh_pdf'      => array( 'Файл PDF', 'url', 'Ссылка на файл из Медиафайлов.' ),
			'_zh_epub'     => array( 'Файл EPUB', 'url', 'Для электронных читалок.' ),
			'_zh_buy'      => array( 'Где купить', 'url', 'Ссылка на Литрес, Ozon или магазин издательства.' ),
		),
		'zh_job'     => array(
			'_zh_salary'   => array( 'Зарплата', 'text', 'Например: от 60 000 ₽ или 50 000-70 000 ₽ на руки.' ),
			'_zh_schedule' => array( 'График', 'text', 'Например: 5/2, с 9 до 18; сменный 2/2.' ),
			'_zh_exp'      => array( 'Опыт', 'text', 'Например: от 1 года; без опыта, обучим.' ),
			'_zh_employ'   => array( 'Занятость', 'select', '', array( 'FULL_TIME' => 'Полная', 'PART_TIME' => 'Частичная', 'CONTRACTOR' => 'Договор / самозанятость', 'TEMPORARY' => 'Временная', 'INTERN' => 'Стажировка' ) ),
			'_zh_until'    => array( 'Актуальна до', 'datetime', 'После даты вакансия сама скроется. Пусто: пока не закроете.' ),
		),
		'zh_doc'     => array(
			'_zh_file'     => array( 'Файл *', 'url', 'PDF из Медиафайлов (Медиафайлы → Добавить → скопировать ссылку).' ),
			'_zh_number'   => array( 'Номер', 'text', 'Например: № Л035-01298-78/00179305.' ),
			'_zh_date'     => array( 'Дата', 'date', '' ),
		),
		'zh_estate'  => array(
			'_zh_price'    => array( 'Цена', 'text', 'Например: 12 500 000 ₽.' ),
			'_zh_rooms'    => array( 'Комнат', 'select', '', array( '' => '(не указано)', 'studio' => 'Студия', '1' => '1', '2' => '2', '3' => '3', '4' => '4+', 'house' => 'Дом', 'land' => 'Участок', 'commercial' => 'Коммерция' ) ),
			'_zh_area'     => array( 'Площадь, м²', 'text', 'Только число: 54.3' ),
			'_zh_floor'    => array( 'Этаж', 'text', 'Например: 7 из 16.' ),
			'_zh_address'  => array( 'Адрес', 'text', '' ),
			'_zh_deal'     => array( 'Статус', 'select', '', array( 'sale' => 'В продаже', 'rent' => 'Аренда', 'reserved' => 'Бронь', 'sold' => 'Продано' ) ),
			'_zh_ready'    => array( 'Сдача', 'text', 'Для новостроек: IV кв. 2027 или «дом сдан».' ),
		),
		'zh_promo'   => array(
			'_zh_until' => array( 'Действует до *', 'datetime', 'После этой даты акция сама исчезнет с сайта.' ),
			'_zh_badge' => array( 'Наклейка', 'text', 'Коротко: -20%, 1+1, Подарок.' ),
			'_zh_link'  => array( 'Ссылка кнопки', 'url', 'Если пусто: кнопка ведёт к форме заявки.' ),
		),
		'zh_review'  => array(
			'_zh_author'     => array( 'Имя автора *', 'text', 'Только настоящие отзывы. Как подписался человек.' ),
			'_zh_role'       => array( 'Кто это', 'text', 'Например: клиентка, 2025 или «родитель ученика».' ),
			'_zh_source'     => array( 'Где оставлен', 'text', 'Например: Яндекс Карты, 2ГИС, ВКонтакте.' ),
			'_zh_source_url' => array( 'Ссылка на оригинал', 'url', 'Ссылка на отзыв: так ему больше доверяют.' ),
			'_zh_video'      => array( 'Видео-отзыв', 'url', 'Ссылка на YouTube, Rutube или VK Видео. Фото к отзыву: блоки «Изображение» или «Галерея» в тексте.' ),
			'_zh_rating'     => array( 'Оценка', 'select', '', array( '' => '(без оценки)', '5' => '5', '4' => '4', '3' => '3', '2' => '2', '1' => '1' ) ),
		),
		'zh_album'   => array(
			'_zh_when'  => array( 'Когда', 'text', 'Например: 12 октября 2026 или «Весна 2025».' ),
			'_zh_place' => array( 'Где', 'text', '' ),
			'_zh_photo' => array( 'Фотограф', 'text', 'Имя автора фото (по желанию).' ),
		),
		'zh_product' => array(
			'_zh_price'     => array( 'Цена', 'text', 'Например: 4 900 ₽.' ),
			'_zh_price_old' => array( 'Старая цена', 'text', 'Если есть скидка.' ),
			'_zh_sku'       => array( 'Артикул', 'text', '' ),
			'_zh_stock'     => array( 'Наличие', 'select', '', array( 'in' => 'В наличии', 'order' => 'Под заказ', 'out' => 'Нет в наличии' ) ),
			'_zh_buy'       => array( 'Ссылка «Купить»', 'url', 'Маркетплейс, оплата или мессенджер. Если пусто: кнопка ведёт к форме заявки.' ),
			'_zh_paylink'   => array( 'Ссылка на оплату', 'url', 'Ссылка на оплату из кабинета банка или сервиса (ЮKassa, Т-Банк, Продамус, СБП). Появится кнопка «Оплатить онлайн».' ),
		),
	);
}

function zhdanov_core_type_on( $type ) {
	if ( 'zh_event' === $type ) {
		return zhdanov_core_events_on();
	}
	$t = zhdanov_core_types();
	return isset( $t[ $type ] ) && zhdanov_core_opt( $t[ $type ]['module'] );
}

add_action( 'init', function () {
	foreach ( zhdanov_core_types() as $type => $t ) {
		if ( ! zhdanov_core_opt( $t['module'] ) ) {
			continue;
		}
		register_post_type(
			$type,
			array(
				'labels'        => array(
					'name'          => $t['many'],
					'singular_name' => $t['one'],
					'add_new'       => $t['add'],
					'add_new_item'  => $t['add'],
					'edit_item'     => 'Изменить: ' . mb_strtolower( $t['one'] ),
					'view_item'     => 'Открыть на сайте',
					'search_items'  => 'Найти',
					'not_found'     => 'Пока пусто',
					'all_items'     => 'Все ' . mb_strtolower( $t['many'] ),
					'menu_name'     => $t['many'],
				),
				'public'        => true,
				'has_archive'   => $t['slug'],
				'rewrite'       => array( 'slug' => $t['slug'], 'with_front' => false ),
				'menu_icon'     => $t['icon'],
				'menu_position' => 6,
				'supports'      => $t['supports'],
				'show_in_rest'  => true,
			)
		);
		if ( $t['tax'] ) {
			register_taxonomy(
				$t['tax'][0],
				$type,
				array(
					'labels'            => array( 'name' => $t['tax'][1], 'singular_name' => $t['tax'][2], 'add_new_item' => 'Добавить', 'menu_name' => $t['tax'][1] ),
					'hierarchical'      => true,
					'show_admin_column' => true,
					'show_in_rest'      => true,
					'rewrite'           => array( 'slug' => $t['tax'][3], 'with_front' => false ),
				)
			);
		}
	}
	// Поля всех типов (включая Афишу) для редактора и импорта.
	foreach ( zhdanov_core_meta_config() as $type => $fields ) {
		if ( 'zh_event' === $type ) {
			continue; // Афиша регистрирует свои поля сама.
		}
		foreach ( array_keys( $fields ) as $key ) {
			register_post_meta(
				$type,
				$key,
				array(
					'type'          => 'string',
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}
}, 9 );

/* Порядок на страницах разделов: услуги и каталог по «Порядку» (как вы их расставили), остальное по дате. */
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() ) {
		return;
	}
	foreach ( zhdanov_core_types() as $type => $t ) {
		if ( $q->is_post_type_archive( $type ) || ( $t['tax'] && $q->is_tax( $t['tax'][0] ) ) ) {
			$q->set( 'posts_per_page', 'zh_album' === $type ? 12 : 24 );
			if ( 'menu_order' === $t['order'] ) {
				$q->set( 'orderby', array( 'menu_order' => 'ASC', 'title' => 'ASC' ) );
			}
		}
	}
} );

/* Панель полей в правой колонке редактора: одна на все разделы. */
add_action( 'enqueue_block_editor_assets', function () {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$cfg    = zhdanov_core_meta_config();
	if ( ! $screen || empty( $cfg[ $screen->post_type ] ) ) {
		return;
	}
	$fields = array();
	foreach ( $cfg[ $screen->post_type ] as $key => $f ) {
		$opts = array();
		if ( ! empty( $f[3] ) ) {
			foreach ( $f[3] as $v => $l ) {
				$opts[] = array( 'value' => (string) $v, 'label' => $l );
			}
		}
		$fields[] = array( 'key' => $key, 'label' => $f[0], 'type' => $f[1], 'help' => $f[2], 'options' => $opts );
	}
	$titles = array( 'zh_job' => 'Зарплата, график, опыт', 'zh_doc' => 'Файл, номер, дата', 'zh_estate' => 'Цена, комнаты, площадь', 'zh_tour' => 'Цена, даты, программа', 'zh_room' => 'Цена, площадь, бронь', 'zh_book' => 'Автор, файлы, где купить', 'zh_person' => 'О специалисте', 'zh_dish' => 'Цена, вес, аллергены', 'zh_branch' => 'Адрес и часы', 'zh_promo' => 'Срок и наклейка', 'zh_course' => 'Старт, программа, тарифы', 'zh_class' => 'День, время, запись', 'zh_event' => 'Дата, место, билеты', 'zh_service' => 'Цена и запись', 'zh_review' => 'Автор и источник', 'zh_album' => 'О съёмке', 'zh_product' => 'Цена и наличие' );
	wp_enqueue_script( 'zhdanov-meta-panel', ZHDANOV_CORE_URL . 'assets/meta-panel.js', array( 'wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data' ), ZHDANOV_CORE_VERSION, true );
	wp_localize_script( 'zhdanov-meta-panel', 'zhMeta', array( 'title' => $titles[ $screen->post_type ], 'fields' => $fields ) );
} );

/* Шаблоны разделов: работают с любой блочной темой. */
add_action( 'init', function () {
	if ( ! function_exists( 'register_block_template' ) ) {
		return;
	}
	$head  = '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->';
	$foot  = '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->';
	$open  = '<!-- wp:group {"tagName":"main","style":{"spacing":{"padding":{"top":"var:preset|spacing|40"}}},"layout":{"type":"constrained"}} --><main class="wp-block-group" style="padding-top:var(--wp--preset--spacing--40)">';
	$close = '</main><!-- /wp:group -->';
	$more  = array( 'zh_job' => 'Другие вакансии', 'zh_doc' => 'Другие документы', 'zh_estate' => 'Похожие объекты', 'zh_tour' => 'Другие туры', 'zh_room' => 'Другие залы', 'zh_book' => 'Другие книги', 'zh_person' => 'Другие специалисты', 'zh_dish' => 'Ещё в меню', 'zh_branch' => 'Другие филиалы', 'zh_promo' => 'Другие акции', 'zh_course' => 'Другие программы', 'zh_class' => 'Ещё занятия', 'zh_service' => 'Другие услуги', 'zh_review' => 'Ещё отзывы', 'zh_album' => 'Другие альбомы', 'zh_product' => 'Похожие товары' );
	foreach ( zhdanov_core_types() as $type => $t ) {
		if ( ! zhdanov_core_opt( $t['module'] ) ) {
			continue;
		}
		$list = '<!-- wp:zhdanov/collection {"type":"' . $type . '","useMainQuery":true,"filters":true,"align":"wide"} /-->';
		register_block_template(
			'zhdanov-core//archive-' . $type,
			array(
				'title'   => $t['many'] . ': все',
				'content' => $head . $open . '<!-- wp:zhdanov/breadcrumbs {"align":"wide"} /--><!-- wp:query-title {"type":"archive","showPrefix":false,"align":"wide"} /-->' . $list . $close . $foot,
			)
		);
		if ( $t['tax'] ) {
			register_block_template(
				'zhdanov-core//taxonomy-' . $t['tax'][0],
				array(
					'title'   => $t['tax'][2],
					'content' => $head . $open . '<!-- wp:zhdanov/breadcrumbs {"align":"wide"} /--><!-- wp:query-title {"type":"archive","showPrefix":false,"align":"wide"} /--><!-- wp:term-description {"align":"wide"} /-->' . $list . $close . $foot,
				)
			);
		}
		$media = 'zh_album' === $type ? '' : '<!-- wp:post-featured-image {"align":"wide","sizeSlug":"large"} /-->';
		register_block_template(
			'zhdanov-core//single-' . $type,
			array(
				'title'      => $t['one'],
				'post_types' => array( $type ),
				'content'    => $head . $open . '<!-- wp:zhdanov/breadcrumbs {"align":"wide"} /--><!-- wp:post-title {"level":1} /-->' . ( 'zh_course' === $type ? $media . '<!-- wp:post-content {"layout":{"type":"constrained"}} /--><!-- wp:zhdanov/item-meta /-->' : '<!-- wp:zhdanov/item-meta /-->' . $media . '<!-- wp:post-content {"layout":{"type":"constrained"}} /-->' ) . '<!-- wp:zhdanov/share /--><!-- wp:zhdanov/collection {"type":"' . $type . '","count":3,"excludeCurrent":true,"showMoreLink":true,"heading":"' . esc_attr( $more[ $type ] ) . '","align":"wide","style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}}} /-->' . $close . $foot,
			)
		);
	}
}, 20 );

/* ---------- Данные для вывода ---------- */

function zhdanov_core_m( $id, $key ) {
	return (string) get_post_meta( $id, $key, true );
}

function zhdanov_core_stock_label( $v ) {
	$l = array( 'in' => 'В наличии', 'order' => 'Под заказ', 'out' => 'Нет в наличии' );
	return isset( $l[ $v ] ) ? $l[ $v ] : $l['in'];
}

/** Куда ведёт кнопка, если своей ссылки нет: форма заявки на странице, иначе мессенджер, иначе телефон. */
function zhdanov_core_fallback_action() {
	if ( zhdanov_core_opt( 'book_widget' ) ) {
		return zhdanov_core_opt( 'book_widget' );
	}
	$c = zhdanov_core_contacts();
	foreach ( array( 'max', 'telegram', 'whatsapp', 'phone' ) as $k ) {
		if ( ! empty( $c[ $k ] ) ) {
			return $c[ $k ]['url'];
		}
	}
	return '#zayavka';
}


/**
 * Тарифы курса из текста. Цена сама меняется после даты.
 * Строка: Название | Цена сейчас | Цена потом | Дата смены (ГГГГ-ММ-ДД) | Что входит; через; точку с запятой
 */
function zhdanov_core_course_tariffs( $id ) {
	$out   = array();
	$today = wp_date( 'Y-m-d' );
	foreach ( preg_split( '/\r?\n/', zhdanov_core_m( $id, '_zh_tariffs' ) ) as $line ) {
		$c = array_map( 'trim', explode( '|', $line ) );
		if ( '' === $c[0] ) {
			continue;
		}
		$c        = array_pad( $c, 5, '' );
		$switch   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $c[3] ) ? $c[3] : '';
		$after    = $switch && $today > $switch;
		$out[]    = array(
			'name'     => $c[0],
			'price'    => $after && $c[2] ? $c[2] : $c[1],
			'next'     => ( ! $after && $switch && $c[2] ) ? $c[2] : '',
			'until'    => ( ! $after && $switch ) ? $switch : '',
			'features' => array_filter( array_map( 'trim', explode( ';', $c[4] ) ) ),
		);
	}
	return $out;
}

/** Блок курса на его странице: старт, формат, программа, тарифы с кнопками. */
function zhdanov_core_course_html( $id ) {
	$title = get_the_title( $id );
	$start = zhdanov_core_m( $id, '_zh_start' );
	$rows  = array(
		zhdanov_core__( 'Старт' )        => $start ? wp_date( 'j F Y', strtotime( $start . ' 12:00' ) ) : zhdanov_core__( 'по набору группы' ),
		zhdanov_core__( 'Длительность' ) => zhdanov_core_m( $id, '_zh_duration' ),
		zhdanov_core__( 'Формат' )       => zhdanov_core_m( $id, '_zh_format' ),
	);
	$h = '<div class="wp-block-zhdanov-item-meta zh-course">';
	$h .= '<dl class="zh-emeta zh-emeta__list">';
	foreach ( array_filter( $rows ) as $l => $v ) {
		$h .= '<div><dt>' . esc_html( $l ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>';
	}
	$h .= '</dl>';
	$lessons = array_filter( array_map( 'trim', preg_split( '/\r?\n/', zhdanov_core_m( $id, '_zh_lessons' ) ) ) );
	if ( $lessons ) {
		$h .= '<h2 class="zh-course__h">' . esc_html( zhdanov_core__( 'Программа' ) ) . '</h2><ol class="zh-course__lessons">';
		foreach ( $lessons as $l ) {
			$h .= '<li>' . esc_html( $l ) . '</li>';
		}
		$h .= '</ol>';
	}
	$tar = zhdanov_core_course_tariffs( $id );
	if ( $tar ) {
		$book = zhdanov_core_m( $id, '_zh_book' );
		$h   .= '<h2 class="zh-course__h">' . esc_html( zhdanov_core__( 'Тарифы' ) ) . '</h2><ul class="zh-tariffs" role="list">';
		foreach ( $tar as $i => $t ) {
			$h .= '<li class="zh-tariff' . ( 1 === $i && count( $tar ) > 2 ? ' is-main' : '' ) . '"><h3 class="zh-tariff__name">' . esc_html( $t['name'] ) . '</h3>';
			$h .= '<p class="zh-tariff__price">' . esc_html( $t['price'] ) . '</p>';
			if ( $t['next'] ) {
				$h .= '<p class="zh-tariff__next">' . esc_html( sprintf( zhdanov_core__( 'до %1$s, потом %2$s' ), wp_date( 'j F', strtotime( $t['until'] . ' 12:00' ) ), $t['next'] ) ) . '</p>';
			}
			if ( $t['features'] ) {
				$h .= '<ul class="is-style-zhdanov-checks zh-tariff__list">';
				foreach ( $t['features'] as $f ) {
					$h .= '<li>' . esc_html( $f ) . '</li>';
				}
				$h .= '</ul>';
			}
			if ( $book ) {
				$h .= '<a class="wp-element-button" href="' . esc_url( $book ) . '" target="_blank" rel="noopener" data-zh-goal="booking">' . esc_html( zhdanov_core__( 'Выбрать' ) ) . '<span class="screen-reader-text">: ' . esc_html( $t['name'] ) . '</span></a>';
			} else {
				$h .= zhdanov_core_book_button( 'Здравствуйте! Хочу на «' . $title . '», тариф «' . $t['name'] . '» (' . $t['price'] . ').', $t['name'], 'Выбрать' );
			}
			$h .= apply_filters( 'zhdanov_pay_button', '', $id, $i, $t['price'], $t['name'] );
			$h .= '</li>';
		}
		$h .= '</ul>';
	}
	return $h . '</div>';
}

/* Включили или выключили раздел любым способом (настройки, пресет, рецепт, импорт): обновляем адреса один раз. */
add_action( 'init', function () {
	$on = array();
	foreach ( zhdanov_core_types() as $type => $t ) {
		$on[] = $type . '=' . ( zhdanov_core_opt( $t['module'] ) ? 1 : 0 );
	}
	$on[]  = 'ev=' . ( zhdanov_core_events_on() ? 1 : 0 );
	$hash  = md5( implode( ',', $on ) . get_option( 'permalink_structure' ) );
	if ( get_option( 'zhdanov_rewrite_hash' ) !== $hash ) {
		flush_rewrite_rules( false );
		update_option( 'zhdanov_rewrite_hash', $hash, true );
	}
}, 99 );
