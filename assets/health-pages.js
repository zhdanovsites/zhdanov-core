/**
 * «Здоровье сайта» → «Проверка страниц»: обходит страницы сайта глазами посетителя
 * (без входа в админку) и показывает, что поправить перед сдачей.
 */
( function () {
	var box = document.getElementById( 'zh-hp' );
	var btn = document.getElementById( 'zh-hp-go' );
	var api = window.wp && window.wp.apiFetch;
	if ( ! box || ! btn || ! api ) { return; }
	var home = ( window.zhHealth && window.zhHealth.home ) || location.origin + '/';
	var origin = new URL( home ).origin;

	function el( tag, attrs, kids ) {
		var e = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( k ) { if ( k === 'text' ) { e.textContent = attrs[ k ]; } else { e.setAttribute( k, attrs[ k ] ); } } );
		( kids || [] ).forEach( function ( c ) { if ( c ) { e.appendChild( typeof c === 'string' ? document.createTextNode( c ) : c ); } } );
		return e;
	}
	function status( t ) { box.querySelector( '.zh-hp-st' ).textContent = t; }

	/** Все опубликованные страницы и записи публичных типов. */
	async function listUrls() {
		var types = await api( { path: '/wp/v2/types?context=edit' } ).catch( function () { return api( { path: '/wp/v2/types' } ); } );
		var out = [];
		var bases = Object.keys( types ).map( function ( k ) { return types[ k ]; } ).filter( function ( t ) {
			return t.rest_base && t.viewable !== false && [ 'attachment', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_font_family', 'wp_font_face', 'wp_global_styles', 'nav_menu_item' ].indexOf( t.slug ) === -1 && ! /^zh_(lead|booking|order|wait)$/.test( t.slug );
		} );
		for ( var i = 0; i < bases.length; i++ ) {
			for ( var page = 1; page <= 3; page++ ) {
				var items;
				try { items = await api( { path: '/' + ( bases[ i ].rest_namespace || 'wp/v2' ) + '/' + bases[ i ].rest_base + '?per_page=100&page=' + page + '&status=publish&_fields=id,link,title' } ); } catch ( e ) { break; }
				items.forEach( function ( p ) { out.push( { id: p.id, url: p.link, title: ( p.title && ( p.title.rendered || p.title.raw ) ) || p.link, type: bases[ i ].name } ); } );
				if ( items.length < 100 ) { break; }
			}
		}
		return out;
	}

	var headCache = {};
	/** Есть ли файл или страница по адресу (HEAD, с переходом по переадресациям). */
	function exists( u ) {
		if ( headCache[ u ] ) { return headCache[ u ]; }
		headCache[ u ] = fetch( u, { method: 'HEAD', credentials: 'omit', redirect: 'follow' } ).then( function ( r ) {
			if ( r.status === 405 ) { return fetch( u, { credentials: 'omit' } ).then( function ( r2 ) { return r2.status; } ); }
			return r.status;
		} ).catch( function () { return 0; } );
		return headCache[ u ];
	}
	async function pool( list, n, fn ) {
		var i = 0;
		async function w() { while ( i < list.length ) { var k = i++; await fn( list[ k ], k ); } }
		var ws = [];
		for ( var j = 0; j < n; j++ ) { ws.push( w() ); }
		await Promise.all( ws );
	}

	function same( u ) { try { return new URL( u, home ).origin === origin; } catch ( e ) { return false; } }
	function abs( u ) { return new URL( u, home ).href.split( '#' )[ 0 ]; }

	async function checkPage( p ) {
		var issues = [];
		var r, html;
		try { r = await fetch( p.url, { credentials: 'omit' } ); html = await r.text(); } catch ( e ) { issues.push( [ 'bad', 'Страница не открывается' ] ); return issues; }
		if ( r.status >= 400 ) { issues.push( [ 'bad', 'Ответ сервера ' + r.status ] ); return issues; }
		var d = new DOMParser().parseFromString( html, 'text/html' );
		var title = ( d.title || '' ).trim();
		var desc = ( d.querySelector( 'meta[name=description]' ) || {} ).content || '';
		p._title = title; p._desc = desc.trim();
		if ( ! title ) { issues.push( [ 'bad', 'Нет заголовка для поиска (title)' ] ); }
		else if ( title.length > 70 ) { issues.push( [ 'warn', 'Заголовок для поиска длинный: ' + title.length + ' знаков (лучше до 60–70)' ] ); }
		if ( ! desc.trim() ) { issues.push( [ 'warn', 'Нет описания для поиска (description)' ] ); }
		else if ( desc.length < 50 || desc.length > 180 ) { issues.push( [ 'warn', 'Описание для поиска ' + desc.length + ' знаков (лучше 120–160)' ] ); }
		var robots = d.querySelector( 'meta[name=robots]' );
		if ( robots && /noindex/i.test( robots.content ) ) { issues.push( [ 'bad', 'Страница закрыта от поисковиков (noindex)' ] ); }
		var h1 = d.querySelectorAll( 'h1' );
		if ( ! h1.length ) { issues.push( [ 'warn', 'Нет главного заголовка h1' ] ); }
		else if ( h1.length > 1 ) { issues.push( [ 'warn', 'Несколько главных заголовков h1: ' + h1.length ] ); }
		// фото
		var imgs = Array.prototype.slice.call( d.querySelectorAll( 'img' ) ).filter( function ( i ) { return ! i.closest( '#wpadminbar' ); } );
		var noAlt = imgs.filter( function ( i ) { return ! i.hasAttribute( 'alt' ); } );
		if ( noAlt.length ) { issues.push( [ 'warn', 'Фото без описания (alt): ' + noAlt.length ] ); }
		var srcs = {};
		imgs.forEach( function ( i ) { var s = i.getAttribute( 'src' ); if ( s && ! /^data:/.test( s ) && same( s ) ) { srcs[ abs( s ) ] = 1; } } );
		Array.prototype.forEach.call( d.querySelectorAll( '[style*="url("]' ), function ( e ) {
			( e.getAttribute( 'style' ).match( /url\(\s*['"]?([^'")]+)/g ) || [] ).forEach( function ( m ) { var s = m.replace( /^url\(\s*['"]?/, '' ); if ( ! /^data:/.test( s ) && same( s ) ) { srcs[ abs( s ) ] = 1; } } );
		} );
		var badImg = [];
		await pool( Object.keys( srcs ), 6, async function ( s ) { var st = await exists( s ); if ( st >= 400 || st === 0 ) { badImg.push( s ); } } );
		if ( badImg.length ) { issues.push( [ 'bad', 'Битые фото: ' + badImg.length, badImg ] ); }
		// ссылки
		var links = {};
		var empty = 0;
		Array.prototype.forEach.call( d.querySelectorAll( 'a' ), function ( a ) {
			if ( a.closest( '#wpadminbar' ) ) { return; }
			var h = a.getAttribute( 'href' );
			if ( h === null ) { if ( ! a.getAttribute( 'role' ) ) { empty++; } return; }
			h = h.trim();
			if ( ! h || /^(#|mailto:|tel:|sms:|javascript:|whatsapp:|viber:|tg:)/i.test( h ) ) { return; }
			if ( same( h ) && ! /\/wp-(admin|login)/.test( h ) ) { links[ abs( h ) ] = 1; }
		} );
		if ( empty ) { issues.push( [ 'warn', 'Ссылки без адреса (href): ' + empty + '. Поисковики их не видят.' ] ); }
		var badLink = [];
		await pool( Object.keys( links ), 6, async function ( s ) { var st = await exists( s ); if ( st >= 400 || st === 0 ) { badLink.push( s ); } } );
		if ( badLink.length ) { issues.push( [ 'bad', 'Битые ссылки: ' + badLink.length, badLink ] ); }
		return issues;
	}

	btn.addEventListener( 'click', async function () {
		btn.disabled = true;
		box.innerHTML = '<p class="zh-hp-st">Собираю список страниц…</p>';
		var pages = await listUrls();
		var done = 0, res = [];
		await pool( pages, 3, async function ( p ) {
			status( 'Проверено ' + done + ' из ' + pages.length + '…' );
			p.issues = await checkPage( p );
			done++;
			res.push( p );
		} );
		// одинаковые заголовки и описания на разных страницах
		var tCount = {}, dCount = {};
		res.forEach( function ( p ) { if ( p._title ) { tCount[ p._title ] = ( tCount[ p._title ] || 0 ) + 1; } if ( p._desc ) { dCount[ p._desc ] = ( dCount[ p._desc ] || 0 ) + 1; } } );
		res.forEach( function ( p ) {
			if ( p._title && tCount[ p._title ] > 1 ) { p.issues.push( [ 'warn', 'Такой же заголовок для поиска ещё на ' + ( tCount[ p._title ] - 1 ) + ' стр.' ] ); }
			if ( p._desc && dCount[ p._desc ] > 1 ) { p.issues.push( [ 'warn', 'Такое же описание для поиска ещё на ' + ( dCount[ p._desc ] - 1 ) + ' стр.' ] ); }
		} );
		res.sort( function ( a, b ) { var w = function ( p ) { return p.issues.some( function ( i ) { return i[ 0 ] === 'bad'; } ) ? 0 : ( p.issues.length ? 1 : 2 ); }; return w( a ) - w( b ) || a.url.localeCompare( b.url ); } );
		var bad = res.filter( function ( p ) { return p.issues.some( function ( i ) { return i[ 0 ] === 'bad'; } ); } ).length;
		var warn = res.filter( function ( p ) { return p.issues.length && ! p.issues.some( function ( i ) { return i[ 0 ] === 'bad'; } ); } ).length;
		box.innerHTML = '';
		box.appendChild( el( 'p', { style: 'font-size:15px' }, [ 'Проверено страниц: ', el( 'b', { text: String( res.length ) } ), ' · Всё хорошо: ', el( 'b', { text: String( res.length - bad - warn ) } ), ' · Стоит поправить: ', el( 'b', { text: String( warn ) } ), ' · Важно: ', el( 'b', { text: String( bad ) } ) ] ) );
		var t = el( 'table', { class: 'widefat striped', style: 'max-width:980px' } );
		var tb = el( 'tbody' );
		res.forEach( function ( p ) {
			var ok = ! p.issues.length;
			var isBad = p.issues.some( function ( i ) { return i[ 0 ] === 'bad'; } );
			var td = el( 'td' );
			td.appendChild( el( 'b', { text: String( p.title ).replace( /<[^>]+>/g, '' ).replace( /&#8211;|&#8212;/g, '-' ).replace( /&[a-z#0-9]+;/gi, ' ' ) || p.url } ) );
			td.appendChild( el( 'br' ) );
			td.appendChild( el( 'a', { href: p.url, target: '_blank', rel: 'noopener', style: 'color:#646970', text: p.url.replace( origin, '' ) || '/' } ) );
			if ( ! ok ) {
				var ul = el( 'ul', { style: 'margin:6px 0 0 18px;list-style:disc' } );
				p.issues.forEach( function ( i ) {
					var li = el( 'li', { text: i[ 1 ] } );
					if ( i[ 2 ] ) {
						var det = el( 'details', { style: 'margin:2px 0' }, [ el( 'summary', { text: 'показать', style: 'cursor:pointer;color:#2271b1' } ) ] );
						i[ 2 ].slice( 0, 30 ).forEach( function ( u ) { det.appendChild( el( 'div', { style: 'font:12px monospace;color:#50575e', text: u.replace( origin, '' ) } ) ); } );
						li.appendChild( det );
					}
					ul.appendChild( li );
				} );
				td.appendChild( ul );
			}
			var edit = el( 'td', { style: 'width:110px' } );
			if ( ! ok ) { edit.appendChild( el( 'a', { class: 'button', href: 'post.php?post=' + p.id + '&action=edit', text: 'Исправить' } ) ); }
			tb.appendChild( el( 'tr', null, [ el( 'td', { style: 'width:28px;font-size:18px', text: ok ? '✅' : ( isBad ? '❌' : '⚠️' ) } ), td, edit ] ) );
		} );
		t.appendChild( tb );
		box.appendChild( t );
		btn.disabled = false;
		btn.textContent = 'Проверить ещё раз';
	} );
}() );
