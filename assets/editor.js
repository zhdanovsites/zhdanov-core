/* Блоки «Жданов» в редакторе: показываем настоящий вид с сервера + простые настройки справа. */
( function ( wp ) {
	var el = wp.element.createElement;
	var SSR = wp.serverSideRender;
	var be = wp.blockEditor;
	var c = wp.components;

	function preview( name ) {
		return function ( props ) {
			return el( 'div', be.useBlockProps(), el( SSR, { block: name, attributes: props.attributes, urlQueryArgs: {} } ) );
		};
	}

	var simple = [ 'zhdanov/event-meta', 'zhdanov/route', 'zhdanov/breadcrumbs', 'zhdanov/share', 'zhdanov/footer-meta', 'zhdanov/item-meta', 'zhdanov/lang-switcher', 'zhdanov/blog-title' ];
	simple.forEach( function ( name ) {
		wp.blocks.registerBlockType( name, { edit: preview( name ), save: function () { return null; } } );
	} );

	wp.blocks.registerBlockType( 'zhdanov/events', {
		edit: function ( props ) {
			var a = props.attributes, set = props.setAttributes;
			return el( wp.element.Fragment, null,
				el( be.InspectorControls, null,
					el( c.PanelBody, { title: 'Афиша' },
						el( c.RangeControl, { label: 'Сколько событий', min: 1, max: 24, value: a.count, onChange: function ( v ) { set( { count: v } ); } } ),
						el( c.ToggleControl, { label: 'Фильтр по месяцам и поиск', checked: a.filters, onChange: function ( v ) { set( { filters: v } ); } } ),
						el( c.ToggleControl, { label: 'Кнопка «Вся афиша»', checked: a.showMoreLink, onChange: function ( v ) { set( { showMoreLink: v } ); } } ),
						el( c.TextControl, { label: 'Только вид событий (ярлык)', help: 'Например: kontserty. Пусто: все.', value: a.category, onChange: function ( v ) { set( { category: v } ); } } )
					)
				),
				preview( 'zhdanov/events' )( props )
			);
		},
		save: function () { return null; }
	} );

	wp.blocks.registerBlockType( 'zhdanov/contacts', {
		edit: function ( props ) {
			var a = props.attributes, set = props.setAttributes;
			return el( wp.element.Fragment, null,
				el( be.InspectorControls, null,
					el( c.PanelBody, { title: 'Вид' },
						el( c.SelectControl, {
							label: 'Как показать', value: a.style,
							options: [ { label: 'Круглые иконки', value: 'icons' }, { label: 'Список с подписями', value: 'list' }, { label: 'Крупные кнопки', value: 'buttons' } ],
							onChange: function ( v ) { set( { style: v } ); }
						} ),
						el( 'p', null, 'Сами контакты: Консоль → Жданов.' )
					)
				),
				preview( 'zhdanov/contacts' )( props )
			);
		},
		save: function () { return null; }
	} );

	wp.blocks.registerBlockType( 'zhdanov/collection', {
		variations: [
			{ name: 'courses', title: 'Курсы и программы', icon: 'welcome-learn-more', attributes: { type: 'zh_course', count: 3 }, scope: [ 'inserter', 'transform' ], isActive: [ 'type' ] },
			{ name: 'schedule', title: 'Расписание и запись', icon: 'calendar', attributes: { type: 'zh_class' }, scope: [ 'inserter', 'transform' ], isActive: [ 'type' ] },
			{ name: 'nearest', title: 'Ближайшие свободные окна', icon: 'clock', attributes: { type: 'zh_class', layout: 'nearest', count: 4 }, scope: [ 'inserter' ], isActive: function ( a ) { return 'zh_class' === a.type && 'nearest' === a.layout; } },
			{ name: 'team', title: 'Специалисты', icon: 'groups', attributes: { type: 'zh_person', count: 6 }, scope: [ 'inserter', 'transform' ], isActive: [ 'type' ] },
			{ name: 'menu', title: 'Меню', icon: 'food', attributes: { type: 'zh_dish' }, scope: [ 'inserter', 'transform' ], isActive: [ 'type' ] },
			{ name: 'branches', title: 'Филиалы', icon: 'location-alt', attributes: { type: 'zh_branch' }, scope: [ 'inserter', 'transform' ], isActive: [ 'type' ] },
			{ name: 'promos', title: 'Акции', icon: 'megaphone', attributes: { type: 'zh_promo', count: 3 }, scope: [ 'inserter', 'transform' ], isActive: [ 'type' ] },
			{ name: 'tours', title: 'Туры', icon: 'palmtree', attributes: { type: 'zh_tour', count: 3 }, scope: [ 'inserter', 'transform' ], isActive: [ 'type' ] },
			{ name: 'rooms', title: 'Залы и локации', icon: 'camera', attributes: { type: 'zh_room', count: 6 }, scope: [ 'inserter', 'transform' ], isActive: [ 'type' ] },
			{ name: 'books', title: 'Книги', icon: 'book', attributes: { type: 'zh_book', count: 8 }, scope: [ 'inserter', 'transform' ], isActive: [ 'type' ] },
			{ name: 'jobs', title: 'Вакансии', icon: 'id-alt', attributes: { type: 'zh_job', count: 6 }, scope: [ 'inserter', 'transform' ], isActive: [ 'type' ] },
			{ name: 'docs', title: 'Документы', icon: 'media-document', attributes: { type: 'zh_doc', count: 50 }, scope: [ 'inserter', 'transform' ], isActive: [ 'type' ] },
			{ name: 'estate', title: 'Недвижимость', icon: 'building', attributes: { type: 'zh_estate', count: 6 }, scope: [ 'inserter', 'transform' ], isActive: [ 'type' ] },
			{ name: 'services', title: 'Услуги и цены', icon: 'clipboard', attributes: { type: 'zh_service' }, isDefault: true, scope: [ 'inserter', 'transform' ], isActive: [ 'type' ] },
			{ name: 'reviews', title: 'Отзывы', icon: 'format-quote', attributes: { type: 'zh_review', count: 3 }, scope: [ 'inserter', 'transform' ], isActive: [ 'type' ] },
			{ name: 'albums', title: 'Альбомы', icon: 'format-gallery', attributes: { type: 'zh_album' }, scope: [ 'inserter', 'transform' ], isActive: [ 'type' ] },
			{ name: 'catalog', title: 'Каталог', icon: 'products', attributes: { type: 'zh_product', count: 8 }, scope: [ 'inserter', 'transform' ], isActive: [ 'type' ] }
		],
		edit: function ( props ) {
			var a = props.attributes, set = props.setAttributes;
			return el( wp.element.Fragment, null,
				el( be.InspectorControls, null,
					el( c.PanelBody, { title: 'Настройки' },
						el( c.SelectControl, { label: 'Раздел', value: a.type, options: [ { label: 'Курсы', value: 'zh_course' }, { label: 'Расписание', value: 'zh_class' }, { label: 'Специалисты', value: 'zh_person' }, { label: 'Меню', value: 'zh_dish' }, { label: 'Филиалы', value: 'zh_branch' }, { label: 'Акции', value: 'zh_promo' }, { label: 'Туры', value: 'zh_tour' }, { label: 'Залы', value: 'zh_room' }, { label: 'Книги', value: 'zh_book' }, { label: 'Вакансии', value: 'zh_job' }, { label: 'Документы', value: 'zh_doc' }, { label: 'Недвижимость', value: 'zh_estate' }, { label: 'Услуги и цены', value: 'zh_service' }, { label: 'Отзывы', value: 'zh_review' }, { label: 'Альбомы', value: 'zh_album' }, { label: 'Каталог', value: 'zh_product' } ], onChange: function ( v ) { set( { type: v } ); } } ),
						el( c.RangeControl, { label: 'Сколько показать', min: 1, max: 48, value: a.count, onChange: function ( v ) { set( { count: v } ); } } ),
						el( c.SelectControl, { label: 'Вид', value: a.layout, options: [ { label: 'Авто', value: 'auto' }, { label: 'Список', value: 'list' }, { label: 'Карточки', value: 'grid' }, { label: 'Ближайшие окна (для расписания)', value: 'nearest' } ], onChange: function ( v ) { set( { layout: v } ); } } ),
						el( c.ToggleControl, { label: 'Кнопка «Все…»', checked: a.showMoreLink, onChange: function ( v ) { set( { showMoreLink: v } ); } } ),
						el( c.TextControl, { label: 'Только группа/раздел (ярлык)', help: 'Можно несколько через запятую. Пусто: все.', value: a.category, onChange: function ( v ) { set( { category: v } ); } } )
					)
				),
				preview( 'zhdanov/collection' )( props )
			);
		},
		save: function () { return null; }
	} );

	wp.blocks.registerBlockType( 'zhdanov/rating', {
		edit: function ( props ) {
			var a = props.attributes, set = props.setAttributes;
			var t = function ( k, l, h ) { return el( c.TextControl, { label: l, help: h, value: a[ k ], onChange: function ( v ) { var o = {}; o[ k ] = v; set( o ); } } ); };
			return el( wp.element.Fragment, null,
				el( be.InspectorControls, null,
					el( c.PanelBody, { title: 'Рейтинг' },
						t( 'service', 'Где', 'Как в фразе «Оценка на …»: Яндекс Картах, 2ГИС.' ), t( 'rating', 'Оценка', 'Например 4,9: как на карточке сейчас.' ), t( 'count', 'Число отзывов', '' ),
						t( 'checked', 'Дата проверки', 'Например: ноябрь 2026. Обновляйте раз в пару месяцев.' ), t( 'url', 'Ссылка на карточку с отзывами', '' ), t( 'reviewUrl', 'Ссылка «Оставить отзыв»', 'Необязательно. Пусто: та же карточка.' )
					)
				),
				preview( 'zhdanov/rating' )( props )
			);
		},
		save: function () { return null; }
	} );

	wp.blocks.registerBlockType( 'zhdanov/locked', {
		edit: function ( props ) {
			var a = props.attributes, set = props.setAttributes;
			return el( 'div', be.useBlockProps( { className: 'zh-locked is-open', style: { outline: '2px dashed #999', padding: '12px' } } ),
				el( be.InspectorControls, null,
					el( c.PanelBody, { title: 'Закрытая часть' },
						el( c.TextControl, { label: 'Текст для тех, у кого нет доступа', value: a.note, onChange: function ( v ) { set( { note: v } ); } } ),
						el( 'p', null, 'Доступ: оплата тарифа курса онлайн (ссылка на почту) или «Код доступа к урокам» в полях курса. Вы как администратор видите всё.' )
					)
				),
				el( 'p', { style: { fontWeight: 700, margin: '0 0 8px' } }, '🔒 Закрытая часть: видят только ученики' ),
				el( be.InnerBlocks, { template: [ [ 'core/heading', { content: 'Урок 1', level: 3 } ], [ 'core/paragraph', { placeholder: 'Текст урока, видео, файлы…' } ] ] } )
			);
		},
		save: function () { return el( be.InnerBlocks.Content ); }
	} );

	wp.blocks.registerBlockType( 'zhdanov/booking', {
		edit: function ( props ) {
			var a = props.attributes, set = props.setAttributes;
			return el( wp.element.Fragment, null,
				el( be.InspectorControls, null,
					el( c.PanelBody, { title: 'Онлайн-запись' },
						el( c.TextControl, { label: 'Заголовок', value: a.title, onChange: function ( v ) { set( { title: v } ); } } ),
						el( c.TextControl, { label: 'Только одно занятие (номер записи)', type: 'number', help: 'Пусто: все занятия из «Расписания», у которых указано число мест.', value: a.classId || '', onChange: function ( v ) { set( { classId: parseInt( v, 10 ) || 0 } ); } } ),
						el( 'p', null, 'Включается в Жданов → Настройки → «Своя онлайн-запись». Записи: Жданов → Записи.' )
					)
				),
				preview( 'zhdanov/booking' )( props )
			);
		},
		save: function () { return null; }
	} );

	wp.blocks.registerBlockType( 'zhdanov/calc', {
		edit: function ( props ) {
			var a = props.attributes, set = props.setAttributes;
			return el( wp.element.Fragment, null,
				el( be.InspectorControls, null,
					el( c.PanelBody, { title: 'Калькулятор' },
						el( c.TextControl, { label: 'Заголовок', value: a.title, onChange: function ( v ) { set( { title: v } ); } } ),
						el( c.TextareaControl, { label: 'Ползунки', rows: 6, help: 'Каждый с новой строки: имя | подпись | от | до | по умолчанию | шаг. Имя латиницей, например s.', value: a.fields, onChange: function ( v ) { set( { fields: v } ); } } ),
						el( c.TextControl, { label: 'Формула', help: 'Имена ползунков, числа, + - * / ( ). Например: s * 3500 + k * 15000', value: a.formula, onChange: function ( v ) { set( { formula: v } ); } } ),
						el( c.TextControl, { label: 'Единица', value: a.unit, onChange: function ( v ) { set( { unit: v } ); } } ),
						el( c.TextControl, { label: 'Примечание', value: a.note, onChange: function ( v ) { set( { note: v } ); } } ),
						el( c.TextControl, { label: 'Текст кнопки', value: a.button, onChange: function ( v ) { set( { button: v } ); } } )
					)
				),
				preview( 'zhdanov/calc' )( props )
			);
		},
		save: function () { return null; }
	} );

	wp.blocks.registerBlockType( 'zhdanov/tgfeed', {
		edit: function ( props ) {
			var a = props.attributes, set = props.setAttributes;
			return el( wp.element.Fragment, null,
				el( be.InspectorControls, null,
					el( c.PanelBody, { title: 'Лента Telegram' },
						el( c.TextControl, { label: 'Канал', help: 'Публичный канал: имя без @ или ссылка t.me/… Пусто: Telegram из настроек «Жданов».', value: a.channel, onChange: function ( v ) { set( { channel: v } ); } } ),
						el( c.RangeControl, { label: 'Сколько постов', min: 1, max: 6, value: a.count, onChange: function ( v ) { set( { count: v } ); } } ),
						el( c.TextControl, { label: 'Заголовок', value: a.title, onChange: function ( v ) { set( { title: v } ); } } )
					)
				),
				preview( 'zhdanov/tgfeed' )( props )
			);
		},
		save: function () { return null; }
	} );

	wp.blocks.registerBlockType( 'zhdanov/builder', {
		edit: function ( props ) {
			var a = props.attributes, set = props.setAttributes;
			return el( wp.element.Fragment, null,
				el( be.InspectorControls, null,
					el( c.PanelBody, { title: 'Конструктор' },
						el( c.TextControl, { label: 'Заголовок', value: a.title, onChange: function ( v ) { set( { title: v } ); } } ),
						el( c.TextareaControl, { label: 'Варианты', rows: 9, help: 'Каждая группа с новой строки: «Начинка: Ягодная 0 | Карамель +300». Число без «+» задаёт цену (например, вес), с «+» добавляет к ней.', value: a.groups, onChange: function ( v ) { set( { groups: v } ); } } ),
						el( c.TextControl, { label: 'Начальная цена', type: 'number', value: a.base, onChange: function ( v ) { set( { base: parseInt( v, 10 ) || 0 } ); } } ),
						el( c.TextControl, { label: 'Валюта', value: a.unit, onChange: function ( v ) { set( { unit: v } ); } } ),
						el( c.TextControl, { label: 'Примечание', value: a.note, onChange: function ( v ) { set( { note: v } ); } } ),
						el( c.TextControl, { label: 'Текст кнопки', value: a.button, onChange: function ( v ) { set( { button: v } ); } } )
					)
				),
				preview( 'zhdanov/builder' )( props )
			);
		},
		save: function () { return null; }
	} );

	wp.blocks.registerBlockType( 'zhdanov/compare', {
		edit: function ( props ) {
			var a = props.attributes, set = props.setAttributes;
			var pick = function ( key, label ) {
				return el( be.MediaUploadCheck, null, el( be.MediaUpload, {
					onSelect: function ( m ) { var o = {}; o[ key ] = m.id; set( o ); }, allowedTypes: [ 'image' ], value: a[ key ],
					render: function ( o ) { return el( c.Button, { variant: 'secondary', onClick: o.open, style: { marginBottom: '8px' } }, ( a[ key ] ? 'Заменить: ' : 'Выбрать: ' ) + label ); }
				} ) );
			};
			return el( wp.element.Fragment, null,
				el( be.InspectorControls, null,
					el( c.PanelBody, { title: 'Было / стало' },
						pick( 'beforeId', 'фото «Было»' ), pick( 'afterId', 'фото «Стало»' ),
						el( c.TextControl, { label: 'Подпись слева', value: a.before, onChange: function ( v ) { set( { before: v } ); } } ),
						el( c.TextControl, { label: 'Подпись справа', value: a.after, onChange: function ( v ) { set( { after: v } ); } } ),
						el( c.TextControl, { label: 'Подпись под фото', value: a.caption, onChange: function ( v ) { set( { caption: v } ); } } )
					)
				),
				preview( 'zhdanov/compare' )( props )
			);
		},
		save: function () { return null; }
	} );

	wp.blocks.registerBlockType( 'zhdanov/gift', {
		edit: function ( props ) {
			var a = props.attributes, set = props.setAttributes;
			return el( wp.element.Fragment, null,
				el( be.InspectorControls, null,
					el( c.PanelBody, { title: 'Сертификат' },
						el( c.TextControl, { label: 'Заголовок', value: a.title, onChange: function ( v ) { set( { title: v } ); } } ),
						el( c.TextareaControl, { label: 'Текст', value: a.text, onChange: function ( v ) { set( { text: v } ); } } ),
						el( c.TextControl, { label: 'Суммы через запятую', value: a.sums, onChange: function ( v ) { set( { sums: v } ); } } ),
						el( c.ToggleControl, { label: 'Можно ввести свою сумму', checked: a.custom, onChange: function ( v ) { set( { custom: v } ); } } ),
						el( c.SelectControl, { label: 'Что продаём', value: a.mode, options: [ { label: 'Подарочный сертификат', value: 'gift' }, { label: 'Пожертвование', value: 'donate' } ], onChange: function ( v ) { set( { mode: v } ); } } ),
						el( 'p', null, 'Оплата настраивается в Жданов → Настройки → Онлайн-оплата. Без неё кнопка ведёт в мессенджер.' )
					)
				),
				preview( 'zhdanov/gift' )( props )
			);
		},
		save: function () { return null; }
	} );

	wp.blocks.registerBlockType( 'zhdanov/quiz', {
		edit: function ( props ) {
			var a = props.attributes, set = props.setAttributes;
			return el( wp.element.Fragment, null,
				el( be.InspectorControls, null,
					el( c.PanelBody, { title: 'Квиз' },
						el( c.TextControl, { label: 'Заголовок', value: a.title, onChange: function ( v ) { set( { title: v } ); } } ),
						el( c.TextareaControl, { label: 'Подзаголовок', value: a.intro, onChange: function ( v ) { set( { intro: v } ); } } ),
						el( c.TextareaControl, { label: 'Вопросы', rows: 8, help: 'Каждый вопрос с новой строки: Вопрос? | вариант | вариант | вариант. Лучше 3-5 вопросов.', value: a.questions, onChange: function ( v ) { set( { questions: v } ); } } ),
						el( c.TextControl, { label: 'Заголовок последнего шага', value: a.final, onChange: function ( v ) { set( { final: v } ); } } ),
						el( c.TextControl, { label: 'Текст кнопки', value: a.button, onChange: function ( v ) { set( { button: v } ); } } )
					)
				),
				preview( 'zhdanov/quiz' )( props )
			);
		},
		save: function () { return null; }
	} );

	wp.blocks.registerBlockType( 'zhdanov/form', {
		edit: function ( props ) {
			var a = props.attributes, set = props.setAttributes;
			return el( wp.element.Fragment, null,
				el( be.InspectorControls, null,
					el( c.PanelBody, { title: 'Форма' },
						el( c.TextControl, { label: 'Заголовок', value: a.title, onChange: function ( v ) { set( { title: v } ); } } ),
						el( c.TextControl, { label: 'Текст кнопки', value: a.button, onChange: function ( v ) { set( { button: v } ); } } ),
						el( c.ToggleControl, { label: 'Поле «Сообщение»', checked: a.message, onChange: function ( v ) { set( { message: v } ); } } ),
						el( c.ToggleControl, { label: 'Поле «Почта»', checked: a.email, onChange: function ( v ) { set( { email: v } ); } } ),
						el( 'p', null, 'Куда приходят заявки: Консоль → Жданов → Заявки с сайта.' )
					)
				),
				preview( 'zhdanov/form' )( props )
			);
		},
		save: function () { return null; }
	} );

	wp.blocks.registerBlockType( 'zhdanov/video', {
		edit: function ( props ) {
			var a = props.attributes, set = props.setAttributes;
			return el( wp.element.Fragment, null,
				el( be.InspectorControls, null,
					el( c.PanelBody, { title: 'Видео' },
						el( c.TextControl, { label: 'Ссылка на видео', help: 'YouTube, Rutube или VK Видео: скопируйте адрес из браузера.', value: a.url, onChange: function ( v ) { set( { url: v } ); } } ),
						el( c.TextControl, { label: 'Название', value: a.title, onChange: function ( v ) { set( { title: v } ); } } ),
						el( be.MediaUploadCheck, null, el( be.MediaUpload, {
							onSelect: function ( m ) { set( { posterId: m.id, poster: m.url } ); }, allowedTypes: [ 'image' ], value: a.posterId,
							render: function ( o ) { return el( c.Button, { variant: 'secondary', onClick: o.open }, a.posterId ? 'Заменить обложку' : 'Выбрать обложку' ); }
						} ) )
					)
				),
				preview( 'zhdanov/video' )( props )
			);
		},
		save: function () { return null; }
	} );
} )( window.wp );
