/* Жданов: cookie (152-ФЗ), Метрика после согласия, цели, карта по клику, копирование ссылки. Без jQuery. */
( function () {
	var cfg = window.zhCore || {};
	var KEY = 'zh_consent';
	var EN = {
		'Мы используем cookie и сервисы статистики, чтобы сайт работал удобнее.': 'We use cookies and analytics to make this site work better.',
		'Подробнее': 'Learn more', 'Принять': 'Accept', 'Только необходимые': 'Essential only', 'Согласие на cookie': 'Cookie consent',
		'Мы используем cookie: необходимые для работы сайта, а с вашего согласия ещё аналитику и маркетинг. Выбор можно изменить в любой момент.': 'We use cookies: essential ones to run the site and, with your consent, analytics and marketing. You can change your choice at any time.',
		'Принять все': 'Accept all', 'Отклонить': 'Reject', 'Настроить': 'Customize', 'Сохранить выбор': 'Save choice',
		'Необходимые: работа сайта (всегда включены)': 'Essential: site functionality (always on)', 'Аналитика: статистика посещений': 'Analytics: visit statistics', 'Маркетинг: реклама и ретаргетинг': 'Marketing: ads and retargeting',
		'Карта: как нас найти': 'Map: how to find us', 'Ссылка скопирована': 'Link copied', 'Скопируйте ссылку': 'Copy the link',
		'Отметьте согласие на обработку персональных данных.': 'Please tick the consent box.', 'Заполните имя и телефон.': 'Please enter your name and phone.',
		'Отправляем…': 'Sending…', 'Не получилось отправить. Позвоните нам или напишите в мессенджер.': 'Could not send. Please call us or send a message.',
		'Нет связи. Позвоните нам или напишите в мессенджер.': 'No connection. Please call us or send a message.',
		'Текст скопирован: вставьте его в чат.': 'Text copied: paste it into the chat.',
		'Открыто': 'Open', 'Сообщить мне': 'Notify me', 'Добавить в календарь': 'Add to calendar', 'Ссылка для отмены': 'Cancel link', 'Здравствуйте! Посчитал(а) на сайте': 'Hello! I calculated on the website', 'Примерно': 'About', 'Здравствуйте! Хочу заказать': 'Hello! I would like to order', 'Итого': 'Total', 'Ответы квиза:': 'Quiz answers:', 'Онлайн-запись': 'Online booking', 'Закрыть': 'Close', 'до': 'until', 'Сейчас закрыто': 'Closed now', 'осталось': 'left', 'дн.': 'd', 'ч': 'h', 'мин': 'min'
	};
	var isEn = '1' === String( cfg.en ), cookieOn = '1' === String( cfg.cookie ), gdpr = '1' === String( cfg.gdpr );
	function t( s ) { return isEn && EN[ s ] ? EN[ s ] : s; }
	var tr = t;
	var store = {
		get: function () { try { return localStorage.getItem( KEY ); } catch ( e ) { return null; } },
		set: function ( v ) { try { localStorage.setItem( KEY, v ); } catch ( e ) {} }
	};

	function loadMetrika() {
		var id = cfg.metrika;
		if ( ! id || window.ym ) { return; }
		( function ( m, e, t, r, i, k, a ) {
			m[ i ] = m[ i ] || function () { ( m[ i ].a = m[ i ].a || [] ).push( arguments ); };
			m[ i ].l = 1 * new Date();
			k = e.createElement( t ); a = e.getElementsByTagName( t )[ 0 ];
			k.async = 1; k.src = r; a.parentNode.insertBefore( k, a );
		} )( window, document, 'script', 'https://mc.yandex.ru/metrika/tag.js', 'ym' );
		window.ym( Number( id ), 'init', { clickmap: true, trackLinks: true, accurateTrackBounce: true, webvisor: false } );
	}

	/** Согласие: 'all' / 'min' (старый формат) или {"a":1,"m":0} (аналитика, маркетинг). */
	function state() {
		var c = store.get();
		if ( ! c ) { return null; }
		if ( 'all' === c ) { return { a: 1, m: 1 }; }
		if ( 'min' === c ) { return { a: 0, m: 0 }; }
		try { var o = JSON.parse( c ); return { a: o.a ? 1 : 0, m: o.m ? 1 : 0 }; } catch ( e ) { return null; }
	}
	function gtag() { window.dataLayer = window.dataLayer || []; window.dataLayer.push( arguments ); }
	/** Google Analytics 4 с Google Consent Mode v2: по умолчанию всё запрещено, разрешаем по выбору посетителя. */
	function loadGA( st ) {
		var id = cfg.ga;
		if ( ! id ) { return; }
		if ( ! window.zhGaOn ) {
			window.zhGaOn = 1;
			gtag( 'consent', 'default', { ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'denied', wait_for_update: 500 } );
			var k = document.createElement( 'script' );
			k.async = 1; k.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent( id );
			document.head.appendChild( k );
			gtag( 'js', new Date() );
			gtag( 'config', id, { anonymize_ip: true } );
		}
		gtag( 'consent', 'update', { analytics_storage: st.a ? 'granted' : 'denied', ad_storage: st.m ? 'granted' : 'denied', ad_user_data: st.m ? 'granted' : 'denied', ad_personalization: st.m ? 'granted' : 'denied' } );
	}
	function apply( st ) {
		if ( st && st.a ) { loadMetrika(); }
		if ( st && ( st.a || st.m ) ) { loadGA( st ); }
	}
	/** Журнал согласий (GDPR): что выбрал посетитель, без личных данных. */
	function logConsent( st ) {
		if ( ! cfg.consentUrl ) { return; }
		try {
			var fd = new FormData();
			fd.append( 'c', st.a && st.m ? 'all' : ( st.a ? 'analytics' : ( st.m ? 'marketing' : 'necessary' ) ) );
			fd.append( 'u', location.pathname );
			if ( ! ( navigator.sendBeacon && navigator.sendBeacon( cfg.consentUrl, fd ) ) ) { fetch( cfg.consentUrl, { method: 'POST', body: fd, keepalive: true } ); }
		} catch ( e ) {}
	}
	function choose( st ) {
		store.set( JSON.stringify( { a: st.a, m: st.m, t: Date.now() } ) );
		logConsent( st );
		apply( st );
	}

	// Цель Метрики с параметрами: какая кнопка, в каком блоке, на какой странице.
	function goal( name, el ) {
		var params = { page: location.pathname };
		if ( el ) {
			params.button = ( el.textContent || '' ).replace( /\s+/g, ' ' ).trim().slice( 0, 60 );
			var sec = el.closest && el.closest( '[id]' );
			if ( sec ) { params.block = sec.id; }
			try { sessionStorage.setItem( 'zh_src', name + ': «' + params.button + '»' + ( params.block ? ' (#' + params.block + ')' : '' ) + ', ' + location.pathname ); } catch ( e ) {}
		}
		if ( cfg.metrika && window.ym ) { window.ym( Number( cfg.metrika ), 'reachGoal', name, params ); }
	}
	// Метки рекламы (utm) запоминаем с первой страницы визита, чтобы приложить к заявке.
	try {
		if ( /utm_|yclid|gclid/.test( location.search ) && ! sessionStorage.getItem( 'zh_utm' ) ) {
			sessionStorage.setItem( 'zh_utm', location.search.slice( 1 ).split( '&' ).filter( function ( x ) { return /^(utm_|yclid|gclid)/.test( x ); } ).join( '&' ) );
		}
		if ( document.referrer && document.referrer.indexOf( location.host ) < 0 && ! sessionStorage.getItem( 'zh_ref' ) ) {
			sessionStorage.setItem( 'zh_ref', document.referrer.split( '?' )[ 0 ] );
		}
	} catch ( e ) {}

	function banner() {
		if ( gdpr ) { return bannerGdpr(); }
		var b = document.createElement( 'div' );
		b.className = 'zh-cookie';
		b.setAttribute( 'role', 'dialog' );
		b.setAttribute( 'aria-live', 'polite' );
		b.setAttribute( 'aria-label', t( 'Согласие на cookie' ) );
		var more = cfg.privacy ? ' <a href="' + cfg.privacy + '">' + t( 'Подробнее' ) + '</a>.' : '';
		b.innerHTML = '<p>' + t( 'Мы используем cookie и сервисы статистики, чтобы сайт работал удобнее.' ) + more + '</p>' +
			'<div class="zh-cookie__btns"><button type="button" class="wp-element-button" data-v="all">' + t( 'Принять' ) + '</button>' +
			'<button type="button" class="zh-btn-ghost" data-v="min">' + t( 'Только необходимые' ) + '</button></div>';
		b.addEventListener( 'click', function ( ev ) {
			var v = ev.target.getAttribute && ev.target.getAttribute( 'data-v' );
			if ( ! v ) { return; }
			b.remove();
			choose( 'all' === v ? { a: 1, m: 1 } : { a: 0, m: 0 } );
		} );
		document.body.appendChild( b );
	}

	/** Баннер для Европы (GDPR): «Принять все», «Отклонить» и «Настроить» одинаково заметны, выбор по категориям. */
	function bannerGdpr() {
		var cur = state() || { a: 0, m: 0 };
		var b = document.createElement( 'div' );
		b.className = 'zh-cookie zh-cookie--gdpr';
		b.setAttribute( 'role', 'dialog' );
		b.setAttribute( 'aria-live', 'polite' );
		b.setAttribute( 'aria-label', t( 'Согласие на cookie' ) );
		var more = cfg.privacy ? ' <a href="' + cfg.privacy + '">' + t( 'Подробнее' ) + '</a>.' : '';
		b.innerHTML = '<p>' + t( 'Мы используем cookie: необходимые для работы сайта, а с вашего согласия ещё аналитику и маркетинг. Выбор можно изменить в любой момент.' ) + more + '</p>' +
			'<div class="zh-cookie__cats" hidden>' +
			'<label><input type="checkbox" checked disabled> ' + t( 'Необходимые: работа сайта (всегда включены)' ) + '</label>' +
			'<label><input type="checkbox" data-c="a"' + ( cur.a ? ' checked' : '' ) + '> ' + t( 'Аналитика: статистика посещений' ) + '</label>' +
			'<label><input type="checkbox" data-c="m"' + ( cur.m ? ' checked' : '' ) + '> ' + t( 'Маркетинг: реклама и ретаргетинг' ) + '</label></div>' +
			'<div class="zh-cookie__btns"><button type="button" class="wp-element-button" data-v="all">' + t( 'Принять все' ) + '</button>' +
			'<button type="button" class="wp-element-button" data-v="none">' + t( 'Отклонить' ) + '</button>' +
			'<button type="button" class="zh-btn-ghost" data-v="set">' + t( 'Настроить' ) + '</button></div>';
		b.addEventListener( 'click', function ( ev ) {
			var v = ev.target.getAttribute && ev.target.getAttribute( 'data-v' );
			if ( ! v ) { return; }
			if ( 'set' === v ) {
				var cats = b.querySelector( '.zh-cookie__cats' );
				if ( cats.hidden ) { cats.hidden = false; ev.target.textContent = t( 'Сохранить выбор' ); return; }
				var a = b.querySelector( '[data-c="a"]' ).checked ? 1 : 0, m = b.querySelector( '[data-c="m"]' ).checked ? 1 : 0;
				b.remove();
				return choose( { a: a, m: m } );
			}
			b.remove();
			choose( 'all' === v ? { a: 1, m: 1 } : { a: 0, m: 0 } );
		} );
		document.body.appendChild( b );
		var f = b.querySelector( 'button' );
		if ( f && f.focus ) { try { f.focus( { preventScroll: true } ); } catch ( e ) {} }
	}

	function init() {
		var st = state();
		if ( ! cookieOn && ! gdpr ) {
			apply( { a: 1, m: 1 } );
		} else if ( st ) {
			apply( st );
		} else {
			banner();
		}

		document.addEventListener( 'click', function ( ev ) {
			var t = ev.target.closest ? ev.target.closest( '[data-zh-goal],[data-zh-cookie-open],.zh-map__btn,.zh-copy,.zh-video__btn' ) : null;
			if ( ! t ) { return; }
			if ( t.hasAttribute( 'data-zh-widget' ) && window.HTMLDialogElement ) {
				// Онлайн-запись (YCLIENTS, DIKIDI, Altegio): грузим только по нажатию.
				ev.preventDefault();
				var dlg = document.querySelector( '.zh-widget' );
				if ( ! dlg ) {
					dlg = document.createElement( 'dialog' );
					dlg.className = 'zh-widget';
					dlg.setAttribute( 'aria-label', tr( 'Онлайн-запись' ) );
					dlg.innerHTML = '<button type="button" class="zh-widget__x" aria-label="' + tr( 'Закрыть' ) + '">×</button><iframe title="' + tr( 'Онлайн-запись' ) + '" loading="lazy"></iframe>';
					document.body.appendChild( dlg );
					dlg.querySelector( '.zh-widget__x' ).addEventListener( 'click', function () { dlg.close(); } );
					dlg.addEventListener( 'click', function ( e ) { if ( e.target === dlg ) { dlg.close(); } } );
				}
				var fr = dlg.querySelector( 'iframe' );
				if ( fr.getAttribute( 'src' ) !== t.href ) { fr.setAttribute( 'src', t.href ); }
				dlg.showModal();
			}
			if ( t.hasAttribute( 'data-zh-book' ) ) {
				var txt = t.getAttribute( 'data-zh-book' );
				var form = document.querySelector( '.zh-form-wrap:not(.zh-quiz .zh-form-wrap) .zh-form' );
				var href = t.getAttribute( 'href' ) || '';
				if ( form ) {
					// На странице есть форма: подставляем текст и ведём к ней.
					ev.preventDefault();
					var msg = form.querySelector( 'textarea[name=message]' );
					if ( msg ) { msg.value = txt; }
					form.scrollIntoView( { behavior: 'smooth', block: 'start' } );
					var nm = form.querySelector( 'input[name=name]' );
					if ( nm ) { setTimeout( function () { nm.focus( { preventScroll: true } ); }, 400 ); }
				} else if ( /wa\.me\//.test( href ) ) {
					t.setAttribute( 'href', href.split( '?' )[ 0 ] + '?text=' + encodeURIComponent( txt ) );
				} else if ( /^https?:/.test( href ) && navigator.clipboard ) {
					// Telegram, MAX: готовый текст копируем, чат открывается.
					navigator.clipboard.writeText( txt ).then( function () {
						var tip = document.createElement( 'div' );
						tip.className = 'zh-toast'; tip.setAttribute( 'role', 'status' );
						tip.textContent = tr( 'Текст скопирован: вставьте его в чат.' );
						document.body.appendChild( tip );
						setTimeout( function () { tip.remove(); }, 4000 );
					} );
				}
			}
			if ( t.hasAttribute( 'data-zh-goal' ) ) { goal( t.getAttribute( 'data-zh-goal' ), t ); }
			if ( t.hasAttribute( 'data-zh-cookie-open' ) ) {
				if ( ! document.querySelector( '.zh-cookie' ) ) { banner(); }
			}
			if ( t.classList.contains( 'zh-map__btn' ) ) {
				var box = t.parentNode;
				var f = document.createElement( 'iframe' );
				f.src = box.getAttribute( 'data-src' );
				f.title = t( 'Карта: как нас найти' );
				f.loading = 'lazy';
				f.setAttribute( 'allowfullscreen', '' );
				box.innerHTML = '';
				box.appendChild( f );
				box.classList.add( 'is-loaded' );
				goal( 'map' );
			}
			if ( t.classList.contains( 'zh-video__btn' ) ) {
				var wrap = t.parentNode;
				var fr = document.createElement( 'iframe' );
				fr.src = wrap.getAttribute( 'data-embed' );
				fr.title = t.textContent.trim() || 'Video';
				fr.setAttribute( 'allow', 'autoplay; encrypted-media; fullscreen; picture-in-picture' );
				fr.setAttribute( 'allowfullscreen', '' );
				wrap.replaceChild( fr, t );
				wrap.classList.add( 'is-loaded' );
				fr.focus();
				goal( 'video' );
			}
			if ( t.classList.contains( 'zh-copy' ) ) {
				var url = t.getAttribute( 'data-url' );
				var done = function () { t.textContent = t( 'Ссылка скопирована' ); };
				if ( navigator.clipboard ) { navigator.clipboard.writeText( url ).then( done ); } else { window.prompt( t( 'Скопируйте ссылку' ), url ); }
			}
		} );


		// Формы заявки: отправка без перезагрузки, капча подгружается при первом касании формы.
		document.querySelectorAll( '.zh-form:not(.zh-bookform):not(.zh-payform)' ).forEach( function ( f ) {
			var page = f.querySelector( 'input[name=page]' );
			if ( page ) { page.value = location.href.split( '#' )[ 0 ]; }
			var capLoaded = false;
			f.addEventListener( 'focusin', function () {
				if ( capLoaded || ! f.querySelector( '.smart-captcha' ) ) { return; }
				capLoaded = true;
				var s = document.createElement( 'script' );
				s.src = 'https://smartcaptcha.yandexcloud.net/captcha.js';
				s.defer = true;
				document.head.appendChild( s );
			} );
			f.addEventListener( 'submit', function ( ev ) {
				var status = f.querySelector( '.zh-form__status' );
				var bad = null;
				f.querySelectorAll( '[required]' ).forEach( function ( i ) {
					var ok = 'checkbox' === i.type ? i.checked : i.value.trim() !== '';
					i.setAttribute( 'aria-invalid', ok ? 'false' : 'true' );
					if ( ! ok && ! bad ) { bad = i; }
				} );
				if ( bad ) {
					ev.preventDefault();
					status.className = 'zh-form__status is-error';
					status.textContent = 'checkbox' === bad.type ? t( 'Отметьте согласие на обработку персональных данных.' ) : t( 'Заполните имя и телефон.' );
					bad.focus();
					return;
				}
				if ( ! window.fetch || ! window.FormData ) { return; }
				ev.preventDefault();
				var btn = f.querySelector( 'button[type=submit]' );
				btn.disabled = true;
				status.className = 'zh-form__status';
				status.textContent = t( 'Отправляем…' );
				var fd = new FormData( f );
				try { fd.append( 'src', [ sessionStorage.getItem( 'zh_src' ), sessionStorage.getItem( 'zh_utm' ), sessionStorage.getItem( 'zh_ref' ) ].filter( Boolean ).join( ' | ' ) ); } catch ( e ) {}
				fetch( f.getAttribute( 'data-rest' ), { method: 'POST', body: fd } )
					.then( function ( r ) { return r.json().then( function ( j ) { return { ok: r.ok, j: j }; } ); } )
					.then( function ( res ) {
						if ( res.ok && res.j.ok ) {
							status.className = 'zh-form__status is-ok';
							status.textContent = res.j.message;
							if ( res.j.file ) {
								var a = document.createElement( 'a' );
								a.href = res.j.file; a.className = 'wp-element-button zh-lead-file'; a.setAttribute( 'download', '' );
								a.textContent = res.j.file_title;
								status.appendChild( document.createElement( 'br' ) ); status.appendChild( a );
							}
							f.reset();
							goal( 'form' );
						} else {
							status.className = 'zh-form__status is-error';
							status.textContent = ( res.j && res.j.message ) || t( 'Не получилось отправить. Позвоните нам или напишите в мессенджер.' );
						}
					} )
					.catch( function () {
						status.className = 'zh-form__status is-error';
						status.textContent = t( 'Нет связи. Позвоните нам или напишите в мессенджер.' );
					} )
					.then( function () { btn.disabled = false; } );
			} );
		} );

		// Оплата онлайн: короткая форма в окне, затем переход в банк.
		var dlg = document.querySelector( '.zh-paydlg' );
		if ( dlg && window.HTMLDialogElement ) {
			var pf = dlg.querySelector( 'form' ), pst = pf.querySelector( '.zh-form__status' );
			document.addEventListener( 'click', function ( e ) {
				var b = e.target.closest && e.target.closest( '[data-zh-pay]' );
				if ( ! b ) { return; }
				var d; try { d = JSON.parse( b.getAttribute( 'data-zh-pay' ) ); } catch ( x ) { return; }
				var gift = 'gift' === d.key, money = gift || 'donate' === d.key;
				if ( money ) {
					var box = b.closest( '.zh-gift' ), own = box && box.querySelector( '[name=zh_gift_own]' ), ch = box && box.querySelector( '[name=zh_gift_sum]:checked' );
					d.sum = own && own.value ? parseInt( own.value, 10 ) : ( ch ? parseInt( ch.value, 10 ) : d.sum );
				}
				pf.id.value = d.id; pf.key.value = d.key; pf.sum.value = d.sum;
				dlg.querySelector( '.zh-paydlg__what' ).textContent = d.title + ': ' + String( d.sum ).replace( /\B(?=(\d{3})+(?!\d))/g, ' ' ) + ' ₽';
				dlg.querySelector( '.zh-paydlg__to' ).hidden = ! gift;
				pf.email.required = gift;
				pst.textContent = ''; pst.className = 'zh-form__status';
				dlg.showModal(); goal( 'pay_open', b );
			} );
			dlg.querySelector( '.zh-paydlg__x' ).addEventListener( 'click', function () { dlg.close(); } );
			pf.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				var bad = ! pf.name.value.trim() || ! pf.consent.checked || ( pf.email.required && ! pf.email.value ) || ( ! pf.email.value && pf.phone.value.replace( /\D/g, '' ).length < 10 );
				if ( bad ) { pst.className = 'zh-form__status is-error'; pst.textContent = tr( 'Заполните имя, почту или телефон и отметьте согласие.' ); return; }
				var btn = pf.querySelector( '[type=submit]' ); btn.disabled = true; pst.className = 'zh-form__status'; pst.textContent = tr( 'Отправляем…' );
				fetch( pf.getAttribute( 'data-rest' ), { method: 'POST', body: new FormData( pf ) } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( j ) {
						if ( j && j.url ) { goal( 'pay_start' ); location.href = j.url; return; }
						pst.className = 'zh-form__status is-error'; pst.textContent = ( j && j.message ) || tr( 'Не получилось. Напишите нам.' ); btn.disabled = false;
					} )
					.catch( function () { pst.className = 'zh-form__status is-error'; pst.textContent = tr( 'Нет связи. Позвоните нам или напишите в мессенджер.' ); btn.disabled = false; } );
			} );
		}
		// Своя онлайн-запись: вкладки дней и окно записи.
		document.querySelectorAll( '[data-zh-booking]' ).forEach( function ( bx ) {
			bx.querySelectorAll( '.zh-booking__days [data-day]' ).forEach( function ( b ) {
				b.addEventListener( 'click', function () {
					var d = b.getAttribute( 'data-day' );
					bx.querySelectorAll( '.zh-booking__days [data-day]' ).forEach( function ( x ) { x.classList.toggle( 'is-active', x === b ); x.setAttribute( 'aria-selected', x === b ? 'true' : 'false' ); } );
					bx.querySelectorAll( '.zh-booking__slots' ).forEach( function ( u ) { u.hidden = u.getAttribute( 'data-day' ) !== d; } );
				} );
			} );
		} );
		var bdlg = document.querySelector( '.zh-bookdlg' );
		if ( bdlg && window.HTMLDialogElement ) {
			var bf = bdlg.querySelector( 'form' ), bst = bf.querySelector( '.zh-form__status' ), lastBtn = null;
			document.addEventListener( 'click', function ( e ) {
				var b = e.target.closest && e.target.closest( '[data-zh-slot]' );
				if ( ! b ) { return; }
				var d; try { d = JSON.parse( b.getAttribute( 'data-zh-slot' ) ); } catch ( x ) { return; }
				lastBtn = b; bf.class.value = d.class; bf.date.value = d.date; bf.wait.value = d.wait ? '1' : '';
				bdlg.querySelector( '.zh-bookdlg__waitnote' ).hidden = ! d.wait; bf.email.required = !! d.wait;
				bf.querySelector( '[type=submit]' ).textContent = d.wait ? tr( 'Сообщить мне' ) : tr( 'Записаться' );
				bf.setAttribute( 'data-rest', bf.getAttribute( 'data-rest' ).replace( /\/(book|wait)$/, d.wait ? '/wait' : '/book' ) );
				bdlg.querySelector( '.zh-paydlg__what' ).textContent = d.label;
				bst.textContent = ''; bst.className = 'zh-form__status';
				bf.querySelector( '[type=submit]' ).hidden = false; bf.querySelector( '[type=submit]' ).disabled = false;
				bdlg.showModal(); goal( 'booking_open', b );
			} );
			bdlg.querySelector( '.zh-paydlg__x' ).addEventListener( 'click', function () { bdlg.close(); } );
			bf.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				var isWait = '1' === bf.wait.value;
				if ( ! bf.name.value.trim() || ( isWait ? ! bf.email.value : bf.phone.value.replace( /\D/g, '' ).length < 10 ) || ! bf.consent.checked ) {
					bst.className = 'zh-form__status is-error'; bst.textContent = tr( 'Укажите имя, телефон и отметьте согласие.' ); return;
				}
				var btn = bf.querySelector( '[type=submit]' ); btn.disabled = true; bst.className = 'zh-form__status'; bst.textContent = tr( 'Отправляем…' );
				fetch( bf.getAttribute( 'data-rest' ), { method: 'POST', body: new FormData( bf ) } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( j ) {
						if ( j && j.ok && ! j.ics ) { bst.className = 'zh-form__status is-ok'; bst.textContent = j.message; btn.hidden = true; goal( 'waitlist', lastBtn ); return; }
						if ( j && j.ok ) {
							bst.className = 'zh-form__status is-ok'; bst.textContent = j.message + ' ';
							var a = document.createElement( 'a' ); a.href = j.ics; a.textContent = tr( 'Добавить в календарь' ); bst.appendChild( a );
							bst.appendChild( document.createTextNode( ' · ' ) );
							var c = document.createElement( 'a' ); c.href = j.cancel; c.textContent = tr( 'Ссылка для отмены' ); bst.appendChild( c );
							btn.hidden = true; goal( 'booking', lastBtn );
							try { localStorage.setItem( 'zh_who', JSON.stringify( { n: bf.name.value, p: bf.phone.value, e: bf.email.value } ) ); } catch ( x ) {}
							return;
						}
						bst.className = 'zh-form__status is-error'; bst.textContent = ( j && j.message ) || tr( 'Не получилось. Напишите нам.' ); btn.disabled = false;
					} )
					.catch( function () { bst.className = 'zh-form__status is-error'; bst.textContent = tr( 'Нет связи. Позвоните нам или напишите в мессенджер.' ); btn.disabled = false; } );
			} );
			// Повторная запись: имя и телефон подставляются сами (хранятся только в этом браузере).
			try { var w = JSON.parse( localStorage.getItem( 'zh_who' ) || 'null' ); if ( w ) { bf.name.value = w.n || ''; bf.phone.value = w.p || ''; bf.email.value = w.e || ''; } } catch ( x ) {}
		}

		var payToast = document.querySelector( '.zh-toast--pay' );
		if ( payToast ) { setTimeout( function () { payToast.classList.add( 'is-fading' ); }, 12000 ); }

		// Калькулятор: формула из настроек блока, только числа и имена полей.
		document.querySelectorAll( '[data-zh-calc]' ).forEach( function ( bx ) {
			var f = bx.getAttribute( 'data-zh-calc' ), unit = bx.getAttribute( 'data-unit' ) || '', btn = bx.querySelector( '[data-zh-book]' ), title = bx.querySelector( '.zh-builder__title' ).textContent;
			var calc = function () {
				var expr = f, lines = [];
				bx.querySelectorAll( 'input[type=range]' ).forEach( function ( i ) {
					i.parentNode.querySelector( 'output' ).textContent = i.value;
					expr = expr.replace( new RegExp( '\\b' + i.name + '\\b', 'g' ), '(' + Number( i.value ) + ')' );
					lines.push( i.parentNode.querySelector( 'label' ).firstChild.textContent.replace( /:\s*$/, '' ) + ': ' + i.value );
				} );
				expr = expr.replace( /\b(min|max|round)\b/g, 'Math.$1' );
				var val = NaN;
				if ( /^[\d\s+\-*\/().,Mathinaxroudm]*$/.test( expr ) ) { try { val = Function( '"use strict";return (' + expr + ')' )(); } catch ( e ) {} }
				var txt = isFinite( val ) ? String( Math.round( val ) ).replace( /\B(?=(\d{3})+(?!\d))/g, ' ' ) + ' ' + unit : '-';
				bx.querySelector( '.zh-builder__total' ).textContent = txt;
				if ( btn ) { btn.setAttribute( 'data-zh-book', tr( 'Здравствуйте! Посчитал(а) на сайте' ) + ' (' + title + '):\n' + lines.join( '\n' ) + '\n' + tr( 'Примерно' ) + ': ' + txt ); }
			};
			bx.addEventListener( 'input', calc ); calc();
		} );

		// Версия для слабовидящих.
		var a11y = document.querySelector( '.zh-a11y' );
		if ( a11y ) {
			var de = document.documentElement, st = {};
			try { st = JSON.parse( localStorage.getItem( 'zh_a11y' ) || '{}' ); } catch ( e ) {}
			var save = function () { try { localStorage.setItem( 'zh_a11y', JSON.stringify( st ) ); } catch ( e ) {} };
			a11y.querySelector( '.zh-a11y__toggle' ).addEventListener( 'click', function () {
				var p = a11y.querySelector( '.zh-a11y__panel' ); p.hidden = ! p.hidden; this.setAttribute( 'aria-expanded', p.hidden ? 'false' : 'true' );
			} );
			a11y.addEventListener( 'click', function ( e ) {
				var b = e.target.closest( '[data-a11y]' ); if ( ! b ) { return; }
				var k = b.getAttribute( 'data-a11y' ), v = b.getAttribute( 'data-v' );
				if ( 'fs' === k ) { st.fs = v; de.setAttribute( 'data-a11y-fs', v ); }
				if ( 'c' === k ) { st.c = v; if ( v ) { de.setAttribute( 'data-a11y-c', v ); } else { de.removeAttribute( 'data-a11y-c' ); } }
				if ( 'img' === k ) { st.img = ! st.img; if ( st.img ) { de.setAttribute( 'data-a11y-img', 'off' ); } else { de.removeAttribute( 'data-a11y-img' ); } }
				if ( 'reset' === k ) { st = {}; [ 'data-a11y-fs', 'data-a11y-c', 'data-a11y-img' ].forEach( function ( a ) { de.removeAttribute( a ); } ); }
				save();
			} );
		}

		// Конструктор заказа: сумма и текст заказа.
		document.querySelectorAll( '[data-zh-builder]' ).forEach( function ( bx ) {
			var unit = bx.getAttribute( 'data-unit' ) || '₽', btn = bx.querySelector( '[data-zh-book]' ), title = bx.querySelector( '.zh-builder__title' ).textContent;
			var calc = function () {
				var sum = parseInt( bx.getAttribute( 'data-base' ), 10 ) || 0, lines = [];
				bx.querySelectorAll( 'input:checked' ).forEach( function ( i ) {
					if ( '' !== i.getAttribute( 'data-set' ) ) { sum = parseInt( i.getAttribute( 'data-set' ), 10 ) + ( sum - ( parseInt( bx.getAttribute( 'data-base' ), 10 ) || 0 ) ); }
					sum += parseInt( i.getAttribute( 'data-add' ), 10 ) || 0;
					lines.push( i.closest( 'fieldset' ).querySelector( 'legend' ).textContent + ': ' + i.value );
				} );
				var txt = String( sum ).replace( /\B(?=(\d{3})+(?!\d))/g, ' ' ) + ' ' + unit;
				bx.querySelector( '.zh-builder__total' ).textContent = txt;
				if ( btn ) { btn.setAttribute( 'data-zh-book', tr( 'Здравствуйте! Хочу заказать' ) + ' (' + title + '):\n' + lines.join( '\n' ) + '\n' + tr( 'Итого' ) + ': ' + txt ); }
			};
			bx.addEventListener( 'change', calc ); calc();
		} );

		// Было / стало.
		document.querySelectorAll( '.zh-compare__range' ).forEach( function ( r ) {
			var box = r.parentNode, upd = function () { box.style.setProperty( '--pos', r.value + '%' ); };
			r.addEventListener( 'input', upd ); upd();
		} );

		// Квиз: вопрос за вопросом, ответы в текст заявки.
		document.querySelectorAll( '[data-zh-quiz]' ).forEach( function ( qz ) {
			var steps = [].slice.call( qz.querySelectorAll( '.zh-quiz__step' ) ), cur = 0;
			var bar = qz.querySelector( '.zh-quiz__bar span' ), cnt = qz.querySelector( '.zh-quiz__count' );
			var show = function ( n ) {
				cur = n;
				steps.forEach( function ( s, i ) { s.hidden = i !== n; } );
				bar.style.width = Math.round( ( n + 1 ) * 100 / steps.length ) + '%';
				cnt.textContent = ( n + 1 ) + ' / ' + steps.length;
				if ( n === steps.length - 1 ) {
					var txt = [];
					steps.slice( 0, -1 ).forEach( function ( s ) {
						var c = s.querySelector( 'input:checked' );
						txt.push( s.querySelector( 'legend' ).textContent + ' ' + ( c ? c.value : '-' ) );
					} );
					var ta = qz.querySelector( 'textarea[name=message]' );
					if ( ta ) { ta.value = tr( 'Ответы квиза:' ) + '\n' + txt.join( '\n' ); }
				}
				var f = steps[ n ].querySelector( 'input,button,textarea' );
				if ( f ) { f.focus( { preventScroll: true } ); }
				goal( 'quiz_step_' + ( n + 1 ) );
			};
			qz.addEventListener( 'change', function ( e ) {
				if ( 'radio' === e.target.type ) { setTimeout( function () { show( Math.min( cur + 1, steps.length - 1 ) ); }, 220 ); }
			} );
			qz.querySelectorAll( '.zh-quiz__back' ).forEach( function ( b ) { b.addEventListener( 'click', function () { show( Math.max( 0, cur - 1 ) ); } ); } );
		} );

		// Специалисты: фильтр по языку.
		document.querySelectorAll( '[data-zh-lang]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var v = b.getAttribute( 'data-zh-lang' ), box = b.parentNode;
				box.querySelectorAll( '[data-zh-lang]' ).forEach( function ( x ) { x.classList.toggle( 'is-active', x === b ); x.setAttribute( 'aria-pressed', x === b ? 'true' : 'false' ); } );
				var list = box.nextElementSibling;
				if ( list ) { list.querySelectorAll( '[data-langs]' ).forEach( function ( li ) { li.hidden = !! v && -1 === li.getAttribute( 'data-langs' ).indexOf( v ); } ); }
			} );
		} );

		// Филиалы: «Открыто сейчас» по времени сайта (не по часам посетителя).
		document.querySelectorAll( '[data-zh-hours]' ).forEach( function ( el ) {
			var week; try { week = JSON.parse( el.getAttribute( 'data-zh-hours' ) ); } catch ( e ) { return; }
			if ( ! week || Array.isArray( week ) && ! week.length ) { return; }
			var tz = parseInt( el.getAttribute( 'data-zh-tz' ), 10 ) || 0;
			var now = new Date( Date.now() + tz * 60000 ), d = now.getUTCDay() || 7;
			var hm = ( '0' + now.getUTCHours() ).slice( -2 ) + ':' + ( '0' + now.getUTCMinutes() ).slice( -2 );
			var spans = week[ d ], open = false, till = '';
			if ( undefined === spans ) { return; }
			( spans || [] ).forEach( function ( s ) { if ( hm >= s[0] && hm < s[1] ) { open = true; till = s[1]; } } );
			el.classList.add( open ? 'is-open' : 'is-closed' );
			el.textContent = open ? t( 'Открыто' ) + ( '24:00' === till ? '' : ' ' + t( 'до' ) + ' ' + till ) : t( 'Сейчас закрыто' );
		} );

		// Акции: обратный отсчёт; по окончании карточка прячется.
		var timers = document.querySelectorAll( '[data-zh-until]' );
		if ( timers.length ) {
			var tick = function () {
				timers.forEach( function ( el ) {
					var left = parseInt( el.getAttribute( 'data-zh-until' ), 10 ) * 1000 - Date.now();
					if ( left <= 0 ) { var li = el.closest( '.zh-promo' ); if ( li && li.parentNode && ! li.closest( '.zh-imeta' ) ) { li.hidden = true; } el.textContent = ''; return; }
					var dd = Math.floor( left / 864e5 ), hh = Math.floor( left / 36e5 ) % 24, mm = Math.floor( left / 6e4 ) % 60;
					el.textContent = '· ' + t( 'осталось' ) + ' ' + ( dd ? dd + ' ' + t( 'дн.' ) + ' ' : '' ) + hh + ' ' + t( 'ч' ) + ' ' + mm + ' ' + t( 'мин' );
				} );
			};
			tick(); setInterval( tick, 30000 );
		}

		// Все ссылки tel: на сайте считаем звонками, даже без отметки.
		document.querySelectorAll( 'a[href^="tel:"]:not([data-zh-goal])' ).forEach( function ( a ) { a.setAttribute( 'data-zh-goal', 'call' ); } );
	}

	if ( 'loading' === document.readyState ) { document.addEventListener( 'DOMContentLoaded', init ); } else { init(); }
} )();
