<?php
/**
 * Настройки: Консоль → «Жданов: настройки». Всё без кода.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Все поля настроек: ключ => [раздел, подпись, тип, подсказка, значение по умолчанию]. */
function zhdanov_core_fields() {
	return array(
		// Разделы сайта.
		'mod_events'      => array( 'modules', 'Афиша (события)', 'checkbox', 'Концерты, спектакли, мастер-классы: дата, время, цена, возраст, статус «Отменено».', 1 ),
		'mod_schedule'    => array( 'modules', 'Расписание и запись', 'checkbox', 'Занятия и приёмы по дням недели, кнопка «Записаться» с готовым текстом.', 0 ),
		'mod_courses'     => array( 'modules', 'Курсы и программы', 'checkbox', 'Модули, старт потока, тарифы с ценой, которая меняется после даты.', 0 ),
		'mod_team'        => array( 'modules', 'Специалисты', 'checkbox', 'Команда, врачи, преподаватели: фильтр по специальности и языку, запись к специалисту.', 0 ),
		'mod_menu'        => array( 'modules', 'Меню (ресторан, кафе)', 'checkbox', 'Блюда по разделам: цена, вес, аллергены, фото.', 0 ),
		'mod_branches'    => array( 'modules', 'Филиалы', 'checkbox', 'Несколько адресов: часы, «Открыто сейчас», все точки на карте.', 0 ),
		'mod_promos'      => array( 'modules', 'Акции', 'checkbox', 'Акции с датой окончания: таймер, после даты исчезают сами.', 0 ),
		'mod_tours'       => array( 'modules', 'Туры', 'checkbox', 'Программа по дням, даты заездов (прошедшие скрываются), что входит, оплата онлайн.', 0 ),
		'mod_rooms'       => array( 'modules', 'Залы и локации', 'checkbox', 'Фотостудия, коворкинг, банкетный зал: площадь, вместимость, цена за час, бронь.', 0 ),
		'mod_books'       => array( 'modules', 'Книги', 'checkbox', 'Писатели и издательства: обложка, аннотация, PDF/EPUB для скачивания, где купить.', 0 ),
		'mod_jobs'        => array( 'modules', 'Вакансии', 'checkbox', 'Зарплата, график, кнопка «Откликнуться»; вакансии видны в поиске Яндекса и Google по работе.', 0 ),
		'mod_docs'        => array( 'modules', 'Документы', 'checkbox', 'Лицензии, уставы, отчёты, договоры: файлы PDF по группам.', 0 ),
		'mod_estate'      => array( 'modules', 'Недвижимость', 'checkbox', 'Квартиры, дома, коммерция: фильтр по комнатам, цене и району.', 0 ),
		'mod_services'    => array( 'modules', 'Услуги и цены', 'checkbox', 'Список услуг с ценой, длительностью и кнопкой записи.', 1 ),
		'mod_reviews'     => array( 'modules', 'Отзывы', 'checkbox', 'Только настоящие отзывы, со ссылкой на оригинал (Яндекс Карты, 2ГИС).', 1 ),
		'mod_gallery'     => array( 'modules', 'Альбомы (галерея)', 'checkbox', 'Фотоальбомы: концерты, события, работы.', 1 ),
		'mod_catalog'     => array( 'modules', 'Каталог', 'checkbox', 'Простой каталог товаров с ценой и кнопкой «Заказать». Для полноценного магазина: WooCommerce.', 0 ),
		'entity'          => array( 'modules', 'Чей это сайт', 'select', 'Для поисковиков: компания (организация, студия, зал) или человек (эксперт, артист, автор).', 'org', array( 'org' => 'Компания', 'person' => 'Человек' ) ),
		'person_job'      => array( 'modules', 'Если человек: кто он', 'text', 'Например: певица, психолог, писатель.', '' ),
		// Контакты.
		'region'          => array( 'contacts', 'Страна сайта', 'select', 'Порядок кнопок: Россия: MAX и телефон первыми, Telegram вторым. Беларусь и зарубеж: можно WhatsApp. ЕС и Великобритания: включается европейский режим (GDPR): баннер с выбором категорий, журнал согласий, форма «удалить мои данные».', 'ru', array( 'ru' => 'Россия', 'by' => 'Беларусь', 'eu' => 'Евросоюз / Великобритания (GDPR)', 'intl' => 'Другая страна' ) ),
		'phone'           => array( 'contacts', 'Телефон', 'text', 'Например: +7 812 123-45-67', '' ),
		'phone2'          => array( 'contacts', 'Второй телефон', 'text', '', '' ),
		'max'             => array( 'contacts', 'MAX: ссылка', 'url', 'Ссылка на профиль или канал в MAX.', '' ),
		'telegram'        => array( 'contacts', 'Telegram', 'text', 'Имя без @ или ссылка t.me/…', '' ),
		'whatsapp'        => array( 'contacts', 'WhatsApp: номер', 'text', 'Только цифры с кодом страны, например 375291234567.', '' ),
		'vk'              => array( 'contacts', 'ВКонтакте: ссылка', 'url', '', '' ),
		'instagram'       => array( 'contacts', 'Instagram: ссылка', 'url', 'На российских сайтах появится пометка о Meta внизу.', '' ),
		'youtube'         => array( 'contacts', 'YouTube: ссылка', 'url', '', '' ),
		'ok'              => array( 'contacts', 'Одноклассники: ссылка', 'url', '', '' ),
		'email'           => array( 'contacts', 'Почта', 'email', '', '' ),
		'address'         => array( 'contacts', 'Адрес', 'text', 'Город, улица, дом. Так, как ищут в картах.', '' ),
		'hours'           => array( 'contacts', 'Часы работы', 'text', 'Например: Пн-Пт 10:00-20:00', '' ),
		'lat'             => array( 'contacts', 'Широта (карта)', 'text', 'Из Яндекс Карт: правый клик по точке → «Что здесь?». Например 59.9343', '' ),
		'lng'             => array( 'contacts', 'Долгота (карта)', 'text', 'Например 30.3351', '' ),
		// Нижняя панель на телефоне.
		'bar_on'          => array( 'mobile', 'Показывать нижнюю панель на телефоне', 'checkbox', 'Две крупные кнопки внизу экрана: звонок и маршрут (или запись).', 1 ),
		'bar_label'       => array( 'mobile', 'Текст второй кнопки', 'text', 'Если пусто: «Маршрут».', '' ),
		'bar_url'         => array( 'mobile', 'Ссылка второй кнопки', 'text', 'Если пусто: маршрут в Яндекс Картах. Можно ссылку на онлайн-запись или #kontakty.', '' ),
		// Метрика и cookie.
		'metrika'         => array( 'stats', 'Яндекс Метрика: номер счётчика', 'text', 'Только цифры. Счётчик включится после согласия посетителя на cookie.', '' ),
		'ya_verify'       => array( 'stats', 'Яндекс Вебмастер: код подтверждения', 'text', 'Вставьте весь метатег из Вебмастера (<meta name="yandex-verification" …>) или только код. Подходит и для страниц, перенесённых из концепта.', '' ),
		'g_verify'        => array( 'stats', 'Google Search Console: код подтверждения', 'text', 'Метатег google-site-verification или только код.', '' ),
		'indexnow_on'     => array( 'stats', 'Сообщать Яндексу о новых страницах (IndexNow)', 'checkbox', 'При публикации и правке страницы сайт сразу сообщает её адрес Яндексу (и Bing). Новые туры и новости попадают в поиск быстрее. Работает, когда сайт открыт для поисковиков.', 1 ),
		'ga4'             => array( 'stats', 'Google Analytics 4: идентификатор', 'text', 'Вида G-XXXXXXX. Загружается только после согласия посетителя (Google Consent Mode v2).', '' ),
		'cookie_on'       => array( 'stats', 'Баннер cookie (152-ФЗ)', 'checkbox', 'Кнопки «Принять» и «Только необходимые». Метрика и карты загружаются только после согласия или по клику.', 1 ),
		'privacy_page'    => array( 'stats', 'Страница политики конфиденциальности', 'page', 'Выберите страницу. Шаблон политики есть в разделе «Готовые страницы».', 0 ),
		// Заявки.
		'form_email'      => array( 'form', 'Куда присылать заявки', 'email', 'Если пусто: почта администратора сайта. Все заявки также сохраняются в Консоль → Заявки.', '' ),
		'form_done'       => array( 'form', 'Текст после отправки', 'text', '', 'Спасибо! Заявка получена, мы свяжемся с вами в ближайшее время.' ),
		'smtp'            => array( 'form', 'Отправлять письма через почту', 'select', 'Обычная почта хостинга часто попадает в спам. Для Яндекса, Mail.ru и Gmail нужен «пароль приложения» из настроек почты (не обычный пароль). Gmail: выберите «Другая», сервер smtp.gmail.com.', '', array( '' => 'Нет (почта хостинга)', 'yandex' => 'Яндекс Почта', 'mailru' => 'Mail.ru', 'custom' => 'Другая (свой сервер)' ) ),
		'smtp_user'       => array( 'form', 'Почта отправителя', 'email', 'Например: site@yandex.ru. С неё будут уходить письма о заявках.', '' ),
		'smtp_pass'       => array( 'form', 'Пароль приложения', 'password', 'Хранится только в базе сайта.', '' ),
		'smtp_host'       => array( 'form', 'Сервер (только для «Другая»)', 'text', 'Например: smtp.gmail.com (для Gmail), smtp.beget.com. Порт 465 по умолчанию; другой через двоеточие: smtp.example.ru:587.', '' ),
		'captcha_client'  => array( 'form', 'Yandex SmartCaptcha: ключ клиента', 'text', 'Из консоли Yandex Cloud. Если пусто: защита от спама скрытым полем и проверкой времени.', '' ),
		'captcha_server'  => array( 'form', 'Yandex SmartCaptcha: ключ сервера', 'text', 'Секретный ключ. Хранится только в базе сайта.', '' ),
		// Превью ссылок.
		'og_default'      => array( 'stats', 'Картинка для превью ссылок по умолчанию', 'url', 'Ссылка на картинку 1200×630 из Медиафайлов. Используется, если у записи нет обложки.', '' ),
		// Закон: продавец, оферта, медицина.
		'legal_name'      => array( 'legal', 'Продавец: ИП или ООО', 'text', 'Как в документах: «ИП Иванова Анна Сергеевна» или «ООО «Ромашка»». Появится в подвале и в разметке для поисковиков.', '' ),
		'legal_inn'       => array( 'legal', 'ИНН (УНП в Беларуси)', 'text', '', '' ),
		'legal_ogrn'      => array( 'legal', 'ОГРН или ОГРНИП', 'text', '', '' ),
		'legal_address'   => array( 'legal', 'Юридический адрес', 'text', 'Если отличается от адреса для клиентов. Для ИП можно не указывать.', '' ),
		'offer_page'      => array( 'legal', 'Страница публичной оферты', 'page', 'Нужна, если продаёте услуги или товары с оплатой на сайте. Шаблон есть в «Готовых страницах».', 0 ),
		'med_on'          => array( 'legal', 'Плашка «Имеются противопоказания»', 'checkbox', 'Для медицинских и косметологических услуг: внизу каждой страницы и на страницах услуг и специалистов.', 0 ),
		'med_text'        => array( 'legal', 'Текст плашки', 'text', '', 'Имеются противопоказания, необходима консультация специалиста' ),
		'license'         => array( 'legal', 'Лицензия', 'text', 'Например: «Лицензия № Л041-01148-77/00368329 от 01.02.2023». Для клиник и школ.', '' ),
		// Удобство.
		'ask_on'          => array( 'extra', 'Кнопка «Спросить про это»', 'checkbox', 'На страницах услуг, товаров, курсов и событий: открывает мессенджер с названием страницы в сообщении.', 1 ),
		'booking_on'      => array( 'extra', 'Своя онлайн-запись по расписанию', 'checkbox', 'Без YCLIENTS: человек выбирает дату и время из «Расписания», видит свободные места, записывается. Вам приходит уведомление, ему письмо с напоминанием за день и ссылкой отмены. Места: поле «Мест для онлайн-записи» у занятия.', 0 ),
		'booking_days'    => array( 'extra', 'Запись на сколько дней вперёд', 'text', '', '14' ),
		'booking_cutoff'  => array( 'extra', 'Закрывать запись за сколько часов до начала', 'text', '', '2' ),
		'book_widget'     => array( 'extra', 'Онлайн-запись: ссылка виджета', 'url', 'Ссылка на запись в YCLIENTS, DIKIDI или Altegio. Кнопки «Записаться» без своей ссылки откроют её. Виджет загружается только по нажатию.', '' ),
		'lead_file'       => array( 'extra', 'Подарок за заявку: ссылка на файл', 'url', 'PDF из Медиафайлов (чек-лист, гайд, прайс). После отправки формы человек получит ссылку на скачивание.', '' ),
		'lead_title'      => array( 'extra', 'Подарок: как называется', 'text', 'Например: «Чек-лист: 10 вопросов перед свадьбой».', '' ),
		'tg_token'        => array( 'extra', 'Заявки в Telegram: токен бота', 'text', 'Создайте бота у @BotFather и вставьте токен. Хранится только в базе сайта.', '' ),
		'tg_chat'         => array( 'extra', 'Заявки в Telegram: номер чата', 'text', 'Напишите боту любое сообщение, затем нажмите «Проверить» на странице «Здоровье сайта»: номер подставится сам.', '' ),
		'lead_remind'     => array( 'extra', 'Напоминать о заявках без ответа', 'checkbox', 'Раз в день в 10:00 в тот же Telegram: заявки со статусом «Новая» старше суток. Статус ставится в списке заявок.', 1 ),
		'backup_tg'       => array( 'extra', 'Копия сайта в Telegram по понедельникам', 'checkbox', 'Рецепт сайта (без паролей) приходит файлом в тот же чат. Копии также лежат в Жданов → Рецепт сайта.', 1 ),
		'tg_digest'       => array( 'extra', 'Сводка в Telegram по понедельникам', 'checkbox', 'Раз в неделю: сколько заявок и откуда, закончившиеся акции, пустая афиша, что поправить на сайте.', 1 ),
		// Оплата.
		'pay_provider'    => array( 'pay', 'Касса на сайте (обычно не нужна)', 'select', 'Проще без кассы: у услуги, товара и тура есть поле «Ссылка на оплату». Клиент делает ссылку в кабинете своего банка или сервиса (ЮKassa, Т-Банк, Продамус, СБП), и кнопка «Оплатить онлайн» ведёт на неё. Кассу включайте только если нужна оплата прямо на сайте с чеком: договор с банком заключает клиент.', '', array( '' => 'Выключена', 'yookassa' => 'ЮKassa', 'tbank' => 'Т-Банк (интернет-эквайринг)' ) ),
		'yk_shop'         => array( 'pay', 'ЮKassa: shopId', 'text', 'Личный кабинет ЮKassa → Интеграция → Ключи API.', '' ),
		'yk_secret'       => array( 'pay', 'ЮKassa: секретный ключ', 'password', 'Там же. В ЮKassa в «HTTP-уведомлениях» укажите адрес: /wp-json/zhdanov/v1/pay-hook/yookassa (события payment.succeeded и payment.canceled).', '' ),
		'tb_terminal'     => array( 'pay', 'Т-Банк: TerminalKey', 'text', 'Личный кабинет Т-Бизнес → Интернет-эквайринг → Магазины → Терминалы.', '' ),
		'tb_password'     => array( 'pay', 'Т-Банк: пароль терминала', 'password', 'Там же.', '' ),
		'pay_vat'         => array( 'pay', 'НДС в чеке', 'select', 'У ИП и самозанятых на УСН обычно «Без НДС».', '1', array( '1' => 'Без НДС', '2' => 'НДС 0%', '3' => 'НДС 10%', '4' => 'НДС 20%' ) ),
		'pay_tax'         => array( 'pay', 'Т-Банк: система налогообложения', 'select', '', 'usn_income', array( 'usn_income' => 'УСН доходы', 'usn_income_outcome' => 'УСН доходы минус расходы', 'osn' => 'ОСН', 'patent' => 'Патент', 'esn' => 'ЕСХН' ) ),
		'gift_min'        => array( 'pay', 'Сертификат: минимальная сумма, ₽', 'text', '', '1000' ),
		'gift_days'       => array( 'pay', 'Сертификат: срок действия, дней', 'text', '', '365' ),
		'a11y_on'         => array( 'legal', 'Кнопка «Версия для слабовидящих»', 'checkbox', 'Вверху каждой страницы: крупный шрифт, чёрно-белая схема, без картинок. Обязательна для школ и госучреждений, полезна всем.', 0 ),
		// Админка для клиента.
		'client_mode'     => array( 'client', 'Упрощённая админка для клиента', 'checkbox', 'Всем, кто не администратор: без плагинов, тем, настроек и уведомлений об обновлениях; экран входа с логотипом сайта. Доступ без пароля: Жданов → «Вход по ссылке».', 1 ),
		'maint_on'        => array( 'client', 'Режим «Сайт готовится»', 'checkbox', 'Пока сайт собирают или переносят: посетители видят заглушку с контактами (поисковики понимают, что это временно), вошедшие в админку видят сайт как есть.', 0 ),
		'maint_text'      => array( 'client', 'Текст заглушки', 'text', 'Пусто: «Сайт обновляется. Скоро всё заработает.»', '' ),
		'support_contact' => array( 'client', 'Поддержка: контакт для клиента', 'text', 'Показывается внизу админки. Например: Telegram @zhdanovsites или +7 900 000-00-00.', '' ),
		'client_report'       => array( 'client', 'Отчёт клиенту раз в месяц', 'checkbox', '1-го числа клиент получает короткий отчёт: заявки, что опубликовано, проверки, обновления, копии. Предпросмотр: Жданов → «Отчёт клиенту».', 0 ),
		'client_report_email' => array( 'client', 'Почта клиента для отчёта', 'text', 'Например owner@example.ru. Пусто: письмо не отправляется.', '' ),
		'client_report_tg'    => array( 'client', 'Отчёт и в Telegram клиента', 'checkbox', 'В тот же чат, куда приходят заявки (бот заявок).', 0 ),
		// Отчёт разработчику.
		'studio_report'   => array( 'studio', 'Присылать отчёт по понедельникам', 'checkbox', 'Сайт сам проверяет страницы, ссылки, фото, поиск, обновления и присылает итог разработчику. Клиенту ничего не приходит.', 1 ),
		'studio_email'    => array( 'studio', 'Почта разработчика', 'email', '', '' ),
		'studio_tg_token' => array( 'studio', 'Telegram: токен бота студии', 'text', 'Один бот студии на все сайты. Хранится только в базе этого сайта.', '' ),
		'studio_tg_chat'  => array( 'studio', 'Telegram: номер чата студии', 'text', 'Пусто: подставится сам при первом отчёте, если боту уже писали.', '' ),
		'backup_studio'   => array( 'studio', 'Копия сайта студии в Telegram', 'checkbox', 'Каждый понедельник рецепт сайта (страницы, фото, настройки; без паролей и заявок) приходит в бот студии. Нужен для восстановления, если у клиента что-то сломают.', 1 ),
		'wipe_on_uninstall' => array( 'credit', 'При удалении плагина стереть всё', 'checkbox', 'Заявки, оплаты, записи, настройки и перенаправления. Без галочки при удалении всё сохраняется (разделы с записями остаются в базе и вернутся, если поставить плагин снова).', 0 ),
		// Подпись в подвале.
		'credit_on'       => array( 'credit', 'Показывать подпись дизайнера', 'checkbox', '', 1 ),
		'credit_text'     => array( 'credit', 'Текст подписи', 'text', 'Своя фраза из двух слов на каждом сайте (для поисковиков так лучше). По умолчанию выбирается сама по адресу сайта.', zhdanov_core_credit_default() ),
		'credit_url'      => array( 'credit', 'Ссылка подписи', 'url', '', 'https://zhdanovsites.com/' ),
	) + zhdanov_core_label_fields();
}

/** Поля «Названия разделов» по всем типам. */
function zhdanov_core_label_fields() {
	$out = array();
	if ( ! function_exists( 'zhdanov_core_types' ) ) {
		return $out;
	}
	foreach ( zhdanov_core_types() as $pt => $t ) {
		$out[ 'label_' . $pt ] = array( 'labels', $t['many'], 'text', '', '' );
	}
	return $out;
}

function zhdanov_core_sections() {
	return array(
		'modules'  => array( 'Разделы сайта', 'Включите то, что нужно этому сайту. Лишнее не появится в меню консоли.' ),
		'contacts' => array( 'Контакты', 'Заполните один раз: они появятся в подвале, в блоке «Контакты», в кнопках и в разметке для Яндекса и Google.' ),
		'mobile'   => array( 'Телефон: нижняя панель', '' ),
		'form'     => array( 'Заявки с сайта', 'Блок «Форма заявки» есть в редакторе (раздел «Жданов»).' ),
		'stats'    => array( 'Метрика, cookie, политика, превью ссылок', '' ),
		'legal'    => array( 'Продавец и закон', 'Реквизиты, оферта, плашка для медицинских услуг. Сайт с ценами и записью обязан показывать продавца (Закон о защите прав потребителей, ст. 8–9).' ),
		'extra'    => array( 'Запись, подарок за заявку, Telegram', '' ),
		'pay'      => array( 'Онлайн-оплата', 'ЮKassa или Т-Банк. Все оплаты видны в Жданов → Оплаты, уведомление приходит в Telegram и на почту.' ),
		'labels'   => array( 'Названия разделов', 'Можно назвать раздел по-своему: «Врачи» вместо «Специалисты», «Прайс» вместо «Услуги». Пусто: стандартное название.' ),
		'client'   => array( 'Админка для клиента', 'Роль «Клиент сайта» правит тексты, фото, новости и туры, но не может сломать сайт.' ),
		'studio'   => array( 'Отчёт разработчику', 'Раз в неделю сайт присылает студии отчёт о проблемах. Кнопка «Прислать отчёт сейчас»: Жданов → Здоровье сайта.' ),
		'credit'   => array( 'Подпись в подвале', '' ),
	);
}

/** Значение настройки. */
function zhdanov_core_opt( $key ) {
	static $opts = null;
	if ( null === $opts ) {
		$opts = get_option( 'zhdanov_core', array() );
		if ( ! is_array( $opts ) ) {
			$opts = array();
		}
	}
	if ( array_key_exists( $key, $opts ) ) {
		$v = $opts[ $key ];
		if ( $v && function_exists( 'pll__' ) && ! is_admin() && function_exists( 'zhdanov_core_translatable_opts' ) && in_array( $key, zhdanov_core_translatable_opts(), true ) ) {
			$v = pll__( $v );
		}
		return $v;
	}
	$f = zhdanov_core_fields();
	return isset( $f[ $key ] ) ? $f[ $key ][4] : '';
}

add_action( 'admin_menu', function () {
	add_menu_page( 'Жданов: настройки', 'Жданов', 'manage_options', 'zhdanov-core', 'zhdanov_core_settings_page', 'dashicons-admin-generic', 59 );
} );

add_action( 'admin_init', function () {
	register_setting( 'zhdanov_core', 'zhdanov_core', array( 'sanitize_callback' => 'zhdanov_core_sanitize' ) );
} );

function zhdanov_core_sanitize( $in ) {
	$out = array();
	foreach ( zhdanov_core_fields() as $key => $f ) {
		$v = isset( $in[ $key ] ) ? $in[ $key ] : '';
		switch ( $f[2] ) {
			case 'checkbox':
				$out[ $key ] = $v ? 1 : 0;
				break;
			case 'url':
				$out[ $key ] = esc_url_raw( trim( $v ) );
				break;
			case 'email':
				$out[ $key ] = sanitize_email( $v );
				break;
			case 'page':
				$out[ $key ] = absint( $v );
				break;
			default:
				$out[ $key ] = sanitize_text_field( $v );
		}
	}
	$out['metrika'] = preg_replace( '/\D/', '', $out['metrika'] );
	foreach ( array( 'ya_verify', 'g_verify' ) as $vk ) {
		$raw = isset( $in[ $vk ] ) ? wp_unslash( (string) $in[ $vk ] ) : ( isset( $out[ $vk ] ) ? (string) $out[ $vk ] : '' );
		if ( preg_match( '/content=["\']([^"\']+)/i', $raw, $vm ) ) {
			$raw = $vm[1];
		}
		$out[ $vk ] = preg_replace( '/[^A-Za-z0-9_\-]/', '', $raw );
	}
	$prev           = (array) get_option( 'zhdanov_core', array() );
	foreach ( zhdanov_core_fields() as $key => $f ) {
		if ( 'password' === $f[2] && '' === $out[ $key ] && ! empty( $prev[ $key ] ) ) {
			$out[ $key ] = $prev[ $key ]; // пустое поле = оставить прежний пароль
		}
	}
	if ( isset( $prev['preset'] ) ) {
		$out['preset'] = $prev['preset'];
	}
	$out['whatsapp'] = preg_replace( '/\D/', '', $out['whatsapp'] );
	// Афиша включилась или выключилась: обновим адреса.
	update_option( 'zhdanov_core_flush', 1 );
	return $out;
}

add_action( 'init', function () {
	if ( get_option( 'zhdanov_core_flush' ) ) {
		delete_option( 'zhdanov_core_flush' );
		flush_rewrite_rules();
	}
}, 99 );

function zhdanov_core_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$fields   = zhdanov_core_fields();
	$sections = zhdanov_core_sections();
	?>
	<div class="wrap">
		<h1>Жданов: настройки сайта</h1>
		<p>Здесь всё, что меняется без кода. Цвета, шрифты и логотип: <a href="<?php echo esc_url( admin_url( 'site-editor.php?p=%2Fstyles' ) ); ?>">Внешний вид → Редактор → Стили</a>.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'zhdanov_core' ); ?>
			<?php foreach ( $sections as $sid => $s ) : ?>
				<h2 id="<?php echo esc_attr( $sid ); ?>" style="margin-top:2em"><?php echo esc_html( $s[0] ); ?></h2>
				<?php if ( $s[1] ) : ?><p><?php echo esc_html( $s[1] ); ?></p><?php endif; ?>
				<table class="form-table" role="presentation"><tbody>
				<?php
				foreach ( $fields as $key => $f ) :
					if ( $f[0] !== $sid ) {
						continue;
					}
					$id  = 'zh-' . $key;
					$val = zhdanov_core_opt( $key );
					$nm  = 'zhdanov_core[' . $key . ']';
					?>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $f[1] ); ?></label></th>
						<td>
						<?php if ( 'checkbox' === $f[2] ) : ?>
							<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $nm ); ?>" value="1" <?php checked( $val, 1 ); ?>>
						<?php elseif ( 'select' === $f[2] ) : ?>
							<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $nm ); ?>">
								<?php foreach ( $f[5] as $ov => $ol ) : ?>
									<option value="<?php echo esc_attr( $ov ); ?>" <?php selected( $val, $ov ); ?>><?php echo esc_html( $ol ); ?></option>
								<?php endforeach; ?>
							</select>
						<?php elseif ( 'page' === $f[2] ) : ?>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => $nm,
									'id'                => $id,
									'selected'          => (int) $val,
									'show_option_none'  => '(не выбрана)',
									'option_none_value' => 0,
								)
							);
							?>
						<?php elseif ( 'password' === $f[2] ) : ?>
							<input class="regular-text" type="password" autocomplete="new-password" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $nm ); ?>" value="" placeholder="<?php echo $val ? esc_attr( 'сохранён, введите новый, чтобы заменить' ) : ''; ?>">
						<?php else : ?>
							<input class="regular-text" type="<?php echo 'url' === $f[2] ? 'url' : ( 'email' === $f[2] ? 'email' : 'text' ); ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $nm ); ?>" value="<?php echo esc_attr( $val ); ?>">
						<?php endif; ?>
						<?php if ( $f[3] ) : ?><p class="description"><?php echo esc_html( $f[3] ); ?></p><?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody></table>
			<?php endforeach; ?>
			<?php submit_button( 'Сохранить' ); ?>
		</form>
	</div>
	<?php
}

/** Метатеги подтверждения сайта для Яндекс Вебмастера и Google Search Console. */
function zhdanov_core_verify_meta() {
	$y = zhdanov_core_opt( 'ya_verify' );
	$g = zhdanov_core_opt( 'g_verify' );
	if ( $y ) {
		echo '<meta name="yandex-verification" content="' . esc_attr( $y ) . '">' . "\n";
	}
	if ( $g ) {
		echo '<meta name="google-site-verification" content="' . esc_attr( $g ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'zhdanov_core_verify_meta', 2 );

/** Подпись в подвале: своя фраза на каждом сайте (выбор по адресу сайта, стабильный). */
function zhdanov_core_credit_default() {
	$v = array( 'Разработка сайта', 'Дизайн сайта', 'Создание сайта', 'Дизайн проекта', 'Разработка проекта', 'Веб-дизайн проекта' );
	$h = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	return $v[ abs( crc32( $h ) ) % count( $v ) ];
}
