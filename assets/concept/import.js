/**
 * «Жданов → Перенос концепта»: найти страницы, разобрать в браузере, создать страницы, скачать файлы.
 */
( function () {
	var $ = function ( id ) { return document.getElementById( id ); };
	var api = window.wp && window.wp.apiFetch;
	var go = $( 'zh-cgo' ), state = null;
	if ( ! go || ! api ) { return; }

	function log( t, bad ) {
		var l = $( 'zh-clog' );
		l.hidden = false;
		var d = document.createElement( 'div' );
		d.textContent = t;
		if ( bad ) { d.style.color = '#b32d2e'; }
		l.appendChild( d );
		l.scrollTop = l.scrollHeight;
	}
	function prog( done, total, text ) {
		$( 'zh-cprog' ).hidden = false;
		$( 'zh-cprog' ).querySelector( 'span' ).style.width = ( total ? Math.round( 100 * done / total ) : 0 ) + '%';
		$( 'zh-cprog' ).querySelector( '.zh-cstat' ).textContent = text;
	}
	function slugOf( u ) {
		try { return new URL( u ).host.split( '.' )[ 0 ].toLowerCase().replace( /[^a-z0-9-]/g, '' ) || 'concept'; } catch ( e ) { return 'concept'; }
	}
	function err( e ) { return ( e && ( e.message || e.code ) ) || 'ошибка'; }
	function sleep( ms ) { return new Promise( function ( r ) { setTimeout( r, ms ); } ); }
	/** Запрос к серверу с повторами: с российских хостингов Cloudflare отвечает с перебоями. */
	async function apiRetry( opts, tries ) {
		tries = tries || 4;
		for ( var t = 1; ; t++ ) {
			try { return await api( opts ); } catch ( e ) {
				var temporary = ! e || ! e.code || /zh_net|fetch_error|rest_|http_request/.test( e.code ) || ( e.data && e.data.status >= 500 );
				if ( t >= tries || ! temporary || e.code === 'zh_http' || e.code === 'zh_conflict' ) { throw e; }
				await sleep( 1500 * t );
			}
		}
	}

	/* шаг 1: найти страницы (обход ссылок с главной) */
	async function discover() {
		var zip = $( 'zh-czip' ).files[ 0 ];
		var url = $( 'zh-curl' ).value.trim();
		state = { pages: {}, order: [], slug: '', origin: '', zip: false, files: null };
		$( 'zh-clog' ).innerHTML = '';
		var dirName = ( $( 'zh-cdir' ) && $( 'zh-cdir' ).value.trim().toLowerCase().replace( /[^a-z0-9-]/g, '' ) ) || '';
		if ( ! zip && ! url && dirName ) {
			state.zip = true; // файлы уже на месте: скачивать нечего
			state.slug = dirName;
			state.origin = 'https://' + dirName + '.concept.local';
			var sc = await api( { path: '/zhdanov/v1/concept/scan', method: 'POST', data: { slug: dirName } } );
			sc.pages.forEach( function ( f ) {
				var w = window.ZhConcept.wpPath( f );
				state.pages[ w ] = { url: state.origin + f, file: f };
				state.order.push( w );
			} );
			log( 'Папка найдена, страниц: ' + sc.pages.length );
		} else if ( zip ) {
			state.zip = true;
			state.slug = zip.name.replace( /\.zip$/i, '' ).toLowerCase().replace( /[^a-z0-9-]+/g, '-' ).replace( /^-|-$/g, '' ) || 'concept';
			state.origin = 'https://' + state.slug + '.concept.local';
			prog( 0, 1, 'Загружаю архив…' );
			var fd = new FormData();
			fd.append( 'zip', zip );
			fd.append( 'slug', state.slug );
			var r = await api( { path: '/zhdanov/v1/concept/upload', method: 'POST', body: fd } );
			state.files = r.pages;
			r.pages.forEach( function ( f ) {
				var w = window.ZhConcept.wpPath( f );
				state.pages[ w ] = { url: state.origin + f, file: f };
				state.order.push( w );
			} );
			log( 'Архив распакован, страниц: ' + r.pages.length );
		} else {
			if ( ! /^https?:\/\//.test( url ) ) { window.alert( 'Вставьте ссылку на концепт, например https://tours-zarubin.pages.dev/' ); return false; }
			var u0 = new URL( url );
			state.origin = u0.origin;
			state.slug = slugOf( url );
			var queue = [ u0.origin + '/' ], seen = {};
			if ( u0.pathname !== '/' ) { queue.push( u0.href ); }
			// sitemap.xml: страницы, на которые нет ссылок с главной; 404.html: своя страница «не найдено»
			try {
				var sm = await api( { path: '/zhdanov/v1/concept/fetch', method: 'POST', data: { url: u0.origin + '/sitemap.xml', slug: state.slug, any: 1 } } );
				( sm.html.match( /<loc>\s*([^<\s]+)\s*<\/loc>/g ) || [] ).forEach( function ( l ) {
					var lu = l.replace( /<\/?loc>/g, '' ).trim();
					try { var x = new URL( lu ); queue.push( u0.origin + x.pathname ); } catch ( e ) {}
				} );
			} catch ( e ) {}
			queue.push( u0.origin + '/404.html' );
			while ( queue.length && state.order.length < 80 ) {
				var pu = queue.shift(), w0 = window.ZhConcept.wpPath( new URL( pu ).pathname );
				if ( seen[ w0 ] ) { continue; }
				seen[ w0 ] = 1;
				prog( state.order.length, state.order.length + queue.length + 1, 'Ищу страницы: ' + w0 );
				try {
					var res = await apiRetry( { path: '/zhdanov/v1/concept/fetch', method: 'POST', data: { url: pu, slug: state.slug } } );
					if ( w0 !== '/' && state.pages[ '/' ] && res.html === state.pages[ '/' ].html ) { continue; } // Cloudflare отдаёт главную вместо несуществующей страницы
					var conv = window.ZhConcept.convert( res.html, pu );
					state.pages[ w0 ] = { url: pu, html: res.html, conv: conv };
					state.order.push( w0 );
					Object.keys( conv.linkUrls || {} ).forEach( function ( lw ) { if ( ! seen[ lw ] ) { queue.push( conv.linkUrls[ lw ] ); } } );
				} catch ( e ) {
					if ( w0 !== '/404/' ) { log( 'Не открылась ' + w0 + ': ' + err( e ), true ); }
				}
			}
		}
		// архив или папка: разбираем страницы сразу, чтобы увидеть служебные (zh-skip); при переносе разбор не повторяется
		if ( state.zip ) {
			var todo = state.order.slice(), nDone = 0;
			var w5 = async function () {
				while ( todo.length ) {
					var w = todo.shift(), pg = state.pages[ w ];
					prog( nDone++, state.order.length, 'Читаю страницы: ' + w );
					try {
						var res = await apiRetry( { path: '/zhdanov/v1/concept/fetch', method: 'POST', data: { file: pg.file, url: pg.url, slug: state.slug } } );
						pg.conv = window.ZhConcept.convert( res.html, pg.url );
					} catch ( e ) {}
				}
			};
			await Promise.all( [ w5(), w5(), w5(), w5(), w5() ] );
		}
		// что уже есть на сайте: страницы, которые клиент правил, по умолчанию не трогаем
		var st = { pages: {}, shared: [], snaps: 0 };
		try { st = await apiRetry( { path: '/zhdanov/v1/concept/status', method: 'POST', data: { slug: state.slug, paths: state.order } } ); } catch ( e ) {}
		var cnt = { new: 0, same: 0, edited: 0, deleted: 0, conflict: 0 };
		var box = $( 'zh-cpages' );
		box.hidden = false;
		var list = document.createElement( 'div' );
		list.className = 'zh-cplist';
		state.order.forEach( function ( w ) {
			var s = st.pages[ w ] || { state: 'new' }, skipWhy = state.pages[ w ].conv && state.pages[ w ].conv.skip;
			var off = s.state === 'edited' || s.state === 'deleted' || ( skipWhy && s.state === 'new' );
			cnt[ s.state ] = ( cnt[ s.state ] || 0 ) + 1;
			var l = document.createElement( 'label' );
			l.innerHTML = '<input type="checkbox"' + ( off ? '' : ' checked' ) + '> ';
			l.firstChild.dataset.path = w;
			l.appendChild( document.createTextNode( w + ' ' ) );
			var tag = document.createElement( 'small' );
			tag.className = 'zh-ctag zh-ctag-' + s.state;
			tag.textContent = {
				new: 'новая',
				same: 'обновится',
				edited: '⚠ изменена на сайте ' + ( s.modified || '' ) + ': не трогаем. Галочка: заменить версией концепта',
				deleted: '⚠ удалена на сайте (в корзине): не трогаем. Галочка: вернуть',
				conflict: 'есть такая же страница не из концепта'
			}[ s.state ] || '';
			if ( skipWhy && s.state === 'new' ) { tag.className = 'zh-ctag zh-ctag-skip'; tag.textContent = 'страница о концепте (' + skipWhy + '): не переносим. Галочка: перенести всё равно'; cnt.skip = ( cnt.skip || 0 ) + 1; }
			l.appendChild( tag );
			list.appendChild( l );
		} );
		var head = '<p><b>Найдено страниц: ' + state.order.length + '</b>';
		if ( cnt.same || cnt.edited || cnt.deleted ) {
			head += ' (новых: ' + cnt.new + ', обновятся: ' + cnt.same + ( cnt.edited ? ', <span style="color:#b32d2e">правили на сайте: ' + cnt.edited + '</span>' : '' ) + ( cnt.deleted ? ', удалены: ' + cnt.deleted : '' ) + ')';
		}
		if ( cnt.skip ) {
			head += ', <span style="color:#8a6d00">служебных страниц о концепте: ' + cnt.skip + ' (без галочки)</span>';
		}
		head += '. Снимите галочки со страниц, которые переносить не нужно.</p>';
		if ( cnt.same || cnt.edited || cnt.deleted || ( st.shared && st.shared.length ) ) {
			head += '<p class="zh-csafe">🛟 Перед переносом сохранится снимок сайта. Если что-то пойдёт не так: ниже на этой странице «Снимки» → <b>«Вернуть как было»</b>.</p>';
		}
		box.innerHTML = head;
		box.appendChild( list );
		if ( st.shared && st.shared.length ) {
			var sh = document.createElement( 'div' );
			sh.className = 'zh-cplist';
			sh.innerHTML = '<p style="margin:2px 0 4px"><b>Общие разделы, которые правили на сайте</b> (меняются сразу на всех страницах). Без галочки остаются как есть:</p>';
			st.shared.forEach( function ( x ) {
				var l = document.createElement( 'label' );
				l.innerHTML = '<input type="checkbox" class="zh-cshared"> ';
				l.firstChild.dataset.id = x.id;
				l.appendChild( document.createTextNode( x.title + ' ' ) );
				var t = document.createElement( 'small' );
				t.className = 'zh-ctag zh-ctag-edited';
				t.textContent = '⚠ изменён ' + x.modified + '. Галочка: заменить версией концепта';
				l.appendChild( t );
				sh.appendChild( l );
			} );
			box.appendChild( sh );
		}
		go.textContent = 'Перенести ' + state.order.length + ' стр.';
		prog( 1, 1, 'Готово к переносу. Концепт: ' + state.slug );
		return true;
	}

	/* шаг 2: разобрать, создать страницы, скачать файлы */
	async function run() {
		var boxes = Array.prototype.slice.call( document.querySelectorAll( '#zh-cpages input[data-path]' ) );
		var chosen = boxes.filter( function ( c ) { return c.checked; } ).map( function ( c ) { return c.dataset.path; } );
		var skip = boxes.filter( function ( c ) { return ! c.checked; } ).map( function ( c ) { return c.dataset.path; } );
		var keepShared = Array.prototype.filter.call( document.querySelectorAll( '#zh-cpages input.zh-cshared' ), function ( c ) { return ! c.checked; } ).map( function ( c ) { return +c.dataset.id; } );
		if ( ! chosen.length ) { window.alert( 'Не выбрано ни одной страницы.' ); return; }
		go.disabled = true;
		var pages = [], assets = {};
		// разбираем и не выбранные страницы: по ним видно, какие разделы общие (шапка, подвал)
		for ( var i = 0; i < state.order.length; i++ ) {
			var w = state.order[ i ], pg = state.pages[ w ], on = chosen.indexOf( w ) >= 0;
			prog( i, state.order.length, 'Разбираю ' + w );
			if ( ! pg.conv ) {
				try {
					var res = await apiRetry( { path: '/zhdanov/v1/concept/fetch', method: 'POST', data: { file: pg.file, url: pg.url, slug: state.slug } } );
					pg.conv = window.ZhConcept.convert( res.html, pg.url );
				} catch ( e ) {
					if ( on ) { throw e; }
					continue;
				}
			}
			if ( ! on ) { pages.push( pg.conv ); continue; }
			pg.conv.assets.forEach( function ( a ) { assets[ a ] = 1; } );
			( pg.conv.warnings || [] ).forEach( function ( wtxt ) { if ( ! state.warned ) { state.warned = {}; } if ( ! state.warned[ wtxt ] ) { state.warned[ wtxt ] = 1; log( '⚠ ' + w + ': ' + wtxt, true ); } } );
			if ( pg.conv.noindex && ! state.noindexLogged ) { state.noindexLogged = 1; log( 'В концепте стоял запрет индексации (noindex). На сайте он снят: поисковики увидят сайт. Пока сайт не готов, включите «Попросить поисковики не индексировать» в Настройки → Чтение.' ); }
			pages.push( pg.conv );
		}
		prog( 0, 1, 'Создаю страницы в WordPress…' );
		var imp;
		try {
			imp = await api( { path: '/zhdanov/v1/concept/import', method: 'POST', data: { slug: state.slug, origin: state.origin, pages: pages, skip: skip, keep_shared: keepShared, front: $( 'zh-cfront' ).checked ? 1 : 0, replace: state.replace ? 1 : 0 } } );
		} catch ( e ) {
			if ( e && e.code === 'zh_conflict' && e.data && e.data.conflicts ) {
				var list = e.data.conflicts.map( function ( c ) { return '• ' + c.path + '  «' + c.title + '»'; } ).join( '\n' );
				var ok = window.confirm( 'На сайте уже есть страницы с такими же адресами, их сделали не из этого концепта:\n\n' + list + '\n\nЗаменить их страницами концепта? Старый текст останется в «Редакциях» каждой страницы.\n\nОтмена: ничего не менять, снимите галочки с этих страниц и нажмите «Перенести» снова.' );
				if ( ! ok ) {
					e.data.conflicts.forEach( function ( c ) { log( 'Уже есть на сайте, не тронута: ' + c.path, true ); } );
					go.disabled = false;
					prog( 0, 1, 'Перенос остановлен: страницы клиента не тронуты.' );
					return;
				}
				state.replace = true;
				imp = await api( { path: '/zhdanov/v1/concept/import', method: 'POST', data: { slug: state.slug, origin: state.origin, pages: pages, skip: skip, keep_shared: keepShared, front: $( 'zh-cfront' ).checked ? 1 : 0, replace: 1 } } );
			} else {
				throw e;
			}
		}
		var kept = Object.keys( imp.kept || {} );
		var same = ( imp.same || [] ).length;
		log( 'Страниц создано или обновлено: ' + ( Object.keys( imp.pages ).length - kept.length - same ) + ( same ? ', без изменений в концепте (не тронуты): ' + same : '' ) + ', общих разделов (шапка, подвал и т. п.): ' + imp.shared.length );
		kept.forEach( function ( k ) { log( 'Не тронута (правки на сайте сохранены): ' + k ); } );
		Object.keys( imp.kept_shared || {} ).forEach( function ( k ) { log( 'Общий раздел не тронут: ' + imp.kept_shared[ k ] ); } );
		if ( imp.removed && imp.removed.length ) { log( 'Заглушки WordPress убраны в корзину: ' + imp.removed.join( ', ' ) ); }
		if ( imp.snap ) { log( '🛟 Снимок до переноса сохранён: «Снимки» → «Вернуть как было» ниже на этой странице.' ); }
		if ( ! state.zip ) {
			var queue = Object.keys( assets ), seen = {}, done = 0, fails = [], quiet = {};
			// «возможные» файлы из шаблонов в скриптах (images/${p.id}.webp): отсутствующие молча пропускаем
			var addMaybe = function ( list, q ) { ( list || [] ).forEach( function ( m ) { if ( ! seen[ m ] ) { seen[ m ] = 1; quiet[ m ] = 1; q.push( m ); total++; } } ); };
			var quietOf = function ( b ) { return b.filter( function ( f ) { return quiet[ f ]; } ); };
			queue.forEach( function ( a ) { seen[ a ] = 1; } );
			// 5 потоков по 4 файла: зависшее соединение с Cloudflare не держит всю очередь
			var total = queue.length;
			var worker = async function () {
				while ( queue.length ) {
					var batch = queue.splice( 0, 4 );
					prog( done, total, 'Скачиваю файлы концепта: ' + done + ' из ' + total );
					try {
						var r = await apiRetry( { path: '/zhdanov/v1/concept/assets', method: 'POST', data: { slug: state.slug, origin: state.origin, files: batch, quiet: quietOf( batch ) } }, 2 );
						done += r.done.length;
						fails = fails.concat( r.fail );
						r.more.forEach( function ( m ) { if ( ! seen[ m ] ) { seen[ m ] = 1; queue.push( m ); total++; } } );
						addMaybe( r.maybe, queue );
					} catch ( e ) {
						fails = fails.concat( batch );
					}
				}
			};
			await Promise.all( [ worker(), worker(), worker(), worker(), worker() ] );
			// не скачавшиеся файлы: ещё 3 круга мелкими порциями с паузой
			for ( var round = 1; round <= 3 && fails.length; round++ ) {
				var again = fails; fails = [];
				log( 'Повтор ' + round + ': не скачались ' + again.length + ' файлов, пробую ещё раз…' );
				await sleep( 3000 * round );
				while ( again.length ) {
					var b2 = again.splice( 0, 5 );
					prog( done, done + b2.length + again.length, 'Повтор ' + round + ': ' + again.length + ' осталось' );
					try {
						var r2 = await api( { path: '/zhdanov/v1/concept/assets', method: 'POST', data: { slug: state.slug, origin: state.origin, files: b2, quiet: quietOf( b2 ) } } );
						done += r2.done.length;
						fails = fails.concat( r2.fail );
						r2.more.forEach( function ( m ) { if ( ! seen[ m ] ) { seen[ m ] = 1; again.push( m ); } } );
					} catch ( e ) { fails = fails.concat( b2 ); }
				}
			}
			log( 'Файлов скачано: ' + done + ( fails.length ? ', не скачались: ' + fails.length : '' ) );
			fails.slice( 0, 30 ).forEach( function ( f ) { log( '  нет файла: ' + f, true ); } );
		}
		try {
			var cd = await api( { path: '/zhdanov/v1/concept/data?rescan=1&slug=' + encodeURIComponent( state.slug ) } );
			var n = Object.keys( cd.lists || {} ).length;
			if ( n ) { log( 'Списков данных в скриптах (события, расписание, цены): ' + n + '. Правятся в «Жданов → Данные концепта».' ); }
		} catch ( e ) {}
		prog( 1, 1, 'Готово ✅' );
		var box = $( 'zh-cpages' );
		box.innerHTML = '<p style="font-size:15px"><b>Перенос закончен.</b> <a class="button button-primary" href="' + imp.view + '" target="_blank">Открыть сайт</a> <a class="button" href="' + imp.edit + '">Редактировать главную</a> <a class="button" href="edit.php?post_type=page">Все страницы</a>' + ( imp.snap ? ' <a class="button" href="admin.php?page=zhdanov-concept#zh-snaps">Снимки: вернуть как было</a>' : '' ) + '</p>';
		go.hidden = true;
	}

	go.addEventListener( 'click', async function ( e ) {
		e.preventDefault();
		try {
			if ( ! state || ! $( 'zh-cpages' ).querySelector( 'input' ) ) {
				go.disabled = true;
				await discover();
				go.disabled = false;
			} else {
				await run();
			}
		} catch ( x ) {
			go.disabled = false;
			log( 'Ошибка: ' + err( x ), true );
		}
	} );
	[ 'zh-curl', 'zh-czip', 'zh-cdir' ].forEach( function ( id ) {
		$( id ).addEventListener( 'change', function () { state = null; go.textContent = 'Найти страницы'; $( 'zh-cpages' ).hidden = true; $( 'zh-cpages' ).innerHTML = ''; } );
	} );
}() );
