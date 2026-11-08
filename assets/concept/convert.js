/**
 * Перенос концепта: HTML-страница → блоки «Раздел концепта».
 * Работает в браузере (DOMParser = настоящий HTML5-разбор, svg и регистр атрибутов не ломаются).
 *
 * Маркеры в шаблоне раздела:
 *   ⟪t5⟫        текст (HTML внутри элемента)
 *   ⟪a6⟫        значение атрибута (ссылка, подпись, data-*)
 *   ⟪i7:src⟫    картинка (src | srcset | sizes | alt)
 *   ⟪r8⟫        повторяющиеся карточки (шаблон карточки в tpls.r8, значения в v.r8 = [ {...}, ... ])
 *   ⟦A⟧         адрес папки с файлами концепта (стили, шрифты, фото)
 *   ⟦H⟧         адрес сайта (для ссылок между страницами)
 */
( function ( root ) {
	'use strict';

	var INLINE = 'a abbr b bdi bdo br cite code data del dfn em i ins kbd mark q s samp small span strong sub sup time u var wbr font nobr'.split( ' ' );
	var TEXTUAL = 'h1 h2 h3 h4 h5 h6 p li a button span b strong em i small label td th dt dd figcaption blockquote cite q time summary legend option caption address pre div'.split( ' ' );
	var OPAQUE = 'svg math script style noscript template canvas object select textarea'.split( ' ' );
	var URL_ATTRS = { src: 1, href: 1, poster: 1, action: 1, data: 1, 'xlink:href': 1, background: 1, formaction: 1 };
	var TEXT_ATTRS = { 'aria-label': 1, title: 1, placeholder: 1, alt: 0 };

	function lc( el ) { return el.tagName ? el.tagName.toLowerCase() : ''; }
	function inA( a, x ) { return a.indexOf( x ) !== -1; }
	function isPagePath( p ) {
		var last = p.split( '/' ).pop();
		return p.slice( -1 ) === '/' || /\.html?$/i.test( last ) || last.indexOf( '.' ) === -1;
	}
	function wpPath( p ) {
		p = p.replace( /index\.html?$/i, '' ).replace( /\.html?$/i, '/' );
		if ( p.slice( -1 ) !== '/' ) { p += '/'; }
		return p;
	}

	function Ctx( pageUrl, opts ) {
		this.page = new URL( pageUrl );
		this.hosts = [ this.page.host ].concat( ( opts && opts.hosts ) || [] );
		this.assets = {};
		this.pages = {};
		this.orig = {};
		this.n = 0;
		this.rep = {};
	}
	Ctx.prototype.own = function ( u ) { return inA( this.hosts, u.host ); };
	/** Переписать адрес: страница → ⟦H⟧/путь/, файл → ⟦A⟧/путь (и запомнить файл для скачивания). */
	Ctx.prototype.url = function ( v, forcePage ) {
		if ( ! v ) { return v; }
		var t = v.trim();
		if ( /^(#|mailto:|tel:|sms:|javascript:|data:|blob:|about:|\{|⟦|⟪)/i.test( t ) ) { return v; }
		var u;
		try { u = new URL( t, this.page ); } catch ( e ) { return v; }
		if ( ! /^https?:$/.test( u.protocol ) || ! this.own( u ) ) { return v; }
		var p = decodeURIComponent( u.pathname );
		if ( forcePage || isPagePath( p ) ) {
			var w = wpPath( p );
			this.pages[ w ] = 1;
			if ( ! this.orig[ w ] ) { this.orig[ w ] = u.origin + u.pathname; }
			if ( w === wpPath( decodeURIComponent( this.page.pathname ) ) && u.hash ) { return u.hash; }
			return '⟦H⟧' + w + u.search + u.hash;
		}
		this.assets[ p ] = 1;
		return '⟦A⟧' + p;
	};
	Ctx.prototype.srcset = function ( v ) {
		var self = this;
		return v.split( ',' ).map( function ( part ) {
			var m = part.trim().match( /^(\S+)(\s+.*)?$/ );
			return m ? self.url( m[ 1 ] ) + ( m[ 2 ] || '' ) : part;
		} ).join( ', ' );
	};
	Ctx.prototype.css = function ( v ) {
		var self = this;
		return v.replace( /url\(\s*(['"]?)([^'")]+)\1\s*\)/g, function ( m, q, u ) {
			return 'url(' + q + self.url( u ) + q + ')';
		} );
	};
	Ctx.prototype.looksPath = function ( v ) {
		return /^(\/|\.\.?\/)[^\s<>"']*$/.test( v ) || /^https?:\/\/[^\s]+$/.test( v ) || /^[\w\-./]+\.(pdf|jpe?g|png|webp|avif|gif|svg|mp4|webm|mp3|docx?|xlsx?|zip|json|ics|epub)$/i.test( v );
	};

	/** Переписать все адреса внутри дерева (до разметки слотов). */
	function rewriteTree( el, ctx ) {
		var all = [ el ].concat( Array.prototype.slice.call( el.querySelectorAll( '*' ) ) );
		all.forEach( function ( x ) {
			var tag = lc( x );
			Array.prototype.slice.call( x.attributes ).forEach( function ( at ) {
				var n = at.name, v = at.value, nv = v;
				if ( n === 'srcset' || n === 'imagesrcset' ) { nv = ctx.srcset( v ); }
				else if ( n === 'style' ) { nv = ctx.css( v ); }
				else if ( URL_ATTRS[ n ] ) { nv = ctx.url( v, ( tag === 'a' || tag === 'area' || n === 'action' || n === 'formaction' ) && ! /\.\w{2,5}$/.test( v.split( /[?#]/ )[ 0 ] ) ); }
				else if ( n.indexOf( 'data-' ) === 0 && ctx.looksPath( v.trim() ) ) { nv = ctx.url( v ); }
				if ( nv !== v ) { x.setAttribute( n, nv ); }
			} );
			if ( tag === 'style' ) { x.textContent = ctx.css( x.textContent ); }
		} );
	}

	/** Структурный отпечаток элемента: тег, классы, отпечатки детей (повторы схлопнуты). */
	function sig( el, memo ) {
		if ( el.__zs ) { return el.__zs; }
		var tag = lc( el );
		var cls = ( el.getAttribute( 'class' ) || '' ).split( /\s+/ ).filter( function ( c ) {
			// классы-состояния и цвета карточек не делают карточки «разными»
			return c && ! /^(on|is-|has-|active|current|open|show|hide|hidden|selected|sel|cur|first|last|odd|even|v\d|c\d|n\d|t\d|tone|alt|dark|light|soon|new|hot|sale|sold|full|done|wide|big|small|featured)/i.test( c );
		} ).sort().join( '.' );
		var s = tag + ( cls ? '.' + cls : '' );
		if ( inA( OPAQUE, tag ) || tag === 'img' ) { el.__zs = s; return s; }
		var kids = [], prev = null;
		Array.prototype.slice.call( el.children ).forEach( function ( c ) {
			var k = sig( c );
			if ( k === prev ) { return; }
			kids.push( k );
			prev = k;
		} );
		s += kids.length ? '(' + kids.join( ',' ) + ')' : '';
		el.__zs = s;
		return s;
	}
	function head( el ) {
		var tag = lc( el );
		var cls = ( el.getAttribute( 'class' ) || '' ).split( /\s+/ )[ 0 ];
		return tag + ( cls ? '.' + cls : '' );
	}

	function isInlineOnly( el ) {
		for ( var i = 0; i < el.children.length; i++ ) {
			var c = el.children[ i ], t = lc( c );
			if ( ! inA( INLINE, t ) ) { return false; }
			if ( c.hasAttribute( 'data-lead' ) || ( t === 'a' && /\bbtn\b/.test( c.className ) && el.children.length > 1 ) ) { return false; }
			if ( ! isInlineOnly( c ) ) { return false; }
		}
		return true;
	}
	function directText( el ) {
		for ( var i = 0; i < el.childNodes.length; i++ ) {
			var n = el.childNodes[ i ];
			if ( n.nodeType === 3 && n.nodeValue.trim() ) { return true; }
		}
		return false;
	}
	function isLeaf( el ) {
		var t = lc( el );
		if ( inA( OPAQUE, t ) || t === 'img' || t === 'input' || t === 'iframe' || t === 'video' || t === 'audio' || t === 'picture' || t === 'br' || t === 'hr' ) { return false; }
		if ( ! isInlineOnly( el ) ) { return false; }
		if ( ! el.textContent.trim() && ! el.children.length ) { return directText( el ) || ( inA( TEXTUAL, t ) && t !== 'div' && t !== 'span' ); }
		if ( ! el.textContent.trim() ) { return false; }
		return inA( TEXTUAL, t ) || directText( el );
	}

	function key( ctx, p ) { ctx.n++; return p + ctx.n; }
	function human( v ) { return /[A-Za-zА-Яа-яЁё]/.test( v ) && ( /\s/.test( v ) || /[А-Яа-яЁё]/.test( v ) ) && ! /^[\[{]/.test( v.trim() ) && v.length < 3000; }

	/** Атрибуты самого элемента → слоты. */
	function attrSlots( el, vals, ctx, meta ) {
		var t = lc( el );
		if ( t === 'img' ) {
			var k = key( ctx, 'i' ), o = {};
			[ 'src', 'srcset', 'sizes', 'alt' ].forEach( function ( a ) {
				if ( el.hasAttribute( a ) ) { o[ a ] = el.getAttribute( a ); el.setAttribute( a, '⟪' + k + ':' + a + '⟫' ); }
			} );
			if ( ! el.hasAttribute( 'alt' ) ) { o.alt = ''; el.setAttribute( 'alt', '⟪' + k + ':alt⟫' ); }
			vals[ k ] = o;
			meta[ k ] = { type: 'img', label: o.alt || 'Фото' };
			// <picture>: источники тоже редактируемые, чтобы замена фото не показывала старое
			var pic = el.parentElement && lc( el.parentElement ) === 'picture' ? el.parentElement : null;
			if ( pic ) {
				Array.prototype.slice.call( pic.querySelectorAll( 'source[srcset]' ) ).forEach( function ( s ) {
					var sk = key( ctx, 'a' );
					vals[ sk ] = s.getAttribute( 'srcset' );
					s.setAttribute( 'srcset', '⟪' + sk + '⟫' );
					meta[ sk ] = { type: 'src', of: k };
				} );
			}
			return;
		}
		Array.prototype.slice.call( el.attributes ).forEach( function ( at ) {
			var n = at.name, v = at.value, label = null, type = 'attr';
			if ( v.indexOf( '⟪' ) !== -1 ) { return; }
			if ( ( t === 'a' || t === 'area' ) && n === 'href' ) { type = 'link'; label = 'Ссылка'; }
			else if ( ( t === 'iframe' || t === 'video' || t === 'audio' || t === 'embed' ) && n === 'src' ) { type = 'url'; label = t === 'iframe' ? 'Адрес встраиваемого (карта, видео)' : 'Файл'; }
			else if ( t === 'video' && n === 'poster' ) { type = 'url'; label = 'Обложка видео'; }
			else if ( TEXT_ATTRS[ n ] && v.trim() ) { label = n === 'placeholder' ? 'Подсказка в поле' : n === 'title' ? 'Всплывающая подсказка' : 'Подпись для незрячих'; }
			else if ( t === 'input' && n === 'value' && /^(submit|button)$/i.test( el.getAttribute( 'type' ) || '' ) ) { label = 'Текст кнопки'; }
			else if ( n.indexOf( 'data-' ) === 0 && ( human( v ) || ctx.looksPath( v.trim() ) || v.indexOf( '⟦' ) === 0 ) && n !== 'data-zh' ) { label = n.slice( 5 ); type = ctx.looksPath( v.trim() ) || v.indexOf( '⟦' ) === 0 ? 'url' : 'attr'; }
			else if ( n === 'style' && /url\(/.test( v ) ) {
				var ik = key( ctx, 'i' ), first = null;
				var nv = v.replace( /url\(\s*(['"]?)([^'")]+)\1\s*\)/, function ( m, q, u ) { first = u; return 'url(' + q + '⟪' + ik + ':src⟫' + q + ')'; } );
				vals[ ik ] = { src: first, alt: '' };
				meta[ ik ] = { type: 'bg', label: 'Фоновое фото' };
				el.setAttribute( 'style', nv );
				return;
			}
			if ( ! label ) { return; }
			var k2 = key( ctx, 'a' );
			vals[ k2 ] = v;
			meta[ k2 ] = { type: type, label: label, attr: n };
			el.setAttribute( n, '⟪' + k2 + '⟫' );
		} );
	}

	/** Обойти элемент: слоты, повторы. vals/meta наполняются, el мутирует в шаблон. */
	function walk( el, vals, ctx, meta, tpls ) {
		var t = lc( el );
		if ( t === 'script' || t === 'style' || t === 'template' || t === 'noscript' ) { return; }
		attrSlots( el, vals, ctx, meta );
		if ( inA( OPAQUE, t ) ) { return; }
		if ( isLeaf( el ) ) {
			var k = key( ctx, 't' );
			vals[ k ] = el.innerHTML;
			meta[ k ] = { type: 'text', tag: t, label: el.textContent.trim().slice( 0, 40 ) };
			el.innerHTML = '⟪' + k + '⟫';
			return;
		}
		// прямые тексты рядом с блоками: «Текст <ul>…</ul>»
		Array.prototype.slice.call( el.childNodes ).forEach( function ( n ) {
			if ( n.nodeType === 3 && n.nodeValue.trim() ) {
				var k3 = key( ctx, 't' );
				var lead = n.nodeValue.match( /^\s*/ )[ 0 ], tail = n.nodeValue.match( /\s*$/ )[ 0 ];
				vals[ k3 ] = esc( n.nodeValue.trim() );
				meta[ k3 ] = { type: 'text', tag: '#', label: n.nodeValue.trim().slice( 0, 40 ) };
				n.nodeValue = lead + '⟪' + k3 + '⟫' + tail;
			}
		} );
		// повторы среди детей
		var kids = Array.prototype.slice.call( el.children ), i = 0, ph = head( el );
		while ( i < kids.length ) {
			var s = sig( kids[ i ] ), hd0 = head( kids[ i ] ), classed = hd0.indexOf( '.' ) !== -1, j = i + 1;
			// карточки одного класса = один список, даже если внутри немного отличаются
			while ( j < kids.length && ( sig( kids[ j ] ) === s || ( classed && head( kids[ j ] ) === hd0 ) ) ) { j++; }
			var run = kids.slice( i, j ), rkey = ph + '>' + head( kids[ i ] );
			var repeatable = ( run.length >= 2 || ctx.rep[ rkey ] ) && ! inA( OPAQUE, lc( kids[ i ] ) ) && lc( kids[ i ] ) !== 'br' && run.every( function ( x ) { return ! x.id; } );
			if ( repeatable && repeater( el, run, vals, ctx, meta, tpls ) ) { i = j; continue; }
			run.forEach( function ( x ) { walk( x, vals, ctx, meta, tpls ); } );
			i = j;
		}
	}

	/**
	 * Список карточек. Карточки могут немного отличаться (значок есть / нет):
	 * каждая форма карточки = свой вариант шаблона (tpls[rN_0], tpls[rN_1]…), у карточки v._t = номер варианта.
	 * «Добавить карточку» в админке копирует выбранную карточку вместе с её вариантом.
	 */
	function hash( s ) {
		var h = 2166136261;
		for ( var i = 0; i < s.length; i++ ) { h ^= s.charCodeAt( i ); h = ( h * 16777619 ) >>> 0; }
		return h.toString( 36 );
	}
	function repeater( parent, run, vals, ctx, meta, tpls ) {
		var nmax = ctx.n, items = [], variants = [], itpls = {}, imeta = {};
		run.forEach( function ( it ) {
			var s = sig( it ), c, v, m, out, hit = null;
			for ( var q = 0; q < variants.length && hit === null; q++ ) {
				if ( variants[ q ].s !== s ) { continue; }
				ctx.n = variants[ q ].n0;
				c = it.cloneNode( true ); v = {}; m = {};
				copySig( it, c );
				walk( c, v, ctx, m, itpls );
				if ( c.outerHTML === variants[ q ].tpl ) { hit = q; }
			}
			if ( hit === null ) {
				ctx.n = nmax;
				var n0 = ctx.n;
				c = it.cloneNode( true ); v = {}; m = {};
				copySig( it, c );
				walk( c, v, ctx, m, itpls );
				variants.push( { s: s, n0: n0, tpl: c.outerHTML, id: hash( c.outerHTML ) } );
				Object.keys( m ).forEach( function ( k ) { imeta[ k ] = m[ k ]; } );
				hit = variants.length - 1;
			}
			nmax = Math.max( nmax, ctx.n );
			v._t = variants[ hit ].id;
			items.push( v );
		} );
		ctx.n = nmax;
		var rk = key( ctx, 'r' );
		Object.keys( itpls ).forEach( function ( k ) { tpls[ k ] = itpls[ k ]; } );
		variants.forEach( function ( vr ) { tpls[ rk + '_' + vr.id ] = vr.tpl; } );
		vals[ rk ] = items;
		meta[ rk ] = { type: 'list', label: guessLabel( run[ 0 ] ), item: imeta, variants: variants.length };
		var mark = parent.ownerDocument.createTextNode( '⟪' + rk + '⟫' );
		parent.insertBefore( mark, run[ 0 ] );
		// пробелы между карточками сохраняем: они влияют на inline-block вёрстку
		var gap = run.length > 1 && run[ 0 ].nextSibling && run[ 0 ].nextSibling.nodeType === 3 ? run[ 0 ].nextSibling.nodeValue : '';
		meta[ rk ].gap = /^\s+$/.test( gap ) ? gap : '';
		if ( meta[ rk ].gap ) { tpls[ rk + '_g' ] = meta[ rk ].gap; }
		run.forEach( function ( x ) {
			var nx = x.nextSibling;
			if ( nx && nx.nodeType === 3 && /^\s*$/.test( nx.nodeValue ) && x !== run[ run.length - 1 ] ) { parent.removeChild( nx ); }
			parent.removeChild( x );
		} );
		return true;
	}
	function copySig( a, b ) {
		b.__zs = a.__zs;
		for ( var i = 0; i < a.children.length; i++ ) { copySig( a.children[ i ], b.children[ i ] ); }
	}
	function guessLabel( el ) {
		var h = el.querySelector( 'h1,h2,h3,h4,h5,b,strong,img[alt]' );
		var cls = ( el.getAttribute( 'class' ) || '' ).split( /\s+/ )[ 0 ];
		return 'Карточки' + ( cls ? ' (' + cls + ')' : '' );
	}
	function esc( s ) { return s.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ); }

	/** Отметить повторяемые пары «родитель > ребёнок» по всему разделу (чтобы 1 значок в одной карточке и 2 в другой считались списком). */
	function markRepeatable( el, ctx ) {
		Array.prototype.slice.call( el.querySelectorAll( '*' ) ).concat( [ el ] ).forEach( function ( p ) {
			var kids = p.children, cnt = {};
			for ( var i = 0; i < kids.length; i++ ) {
				var k = head( kids[ i ] ) + '|' + sig( kids[ i ] );
				cnt[ k ] = ( cnt[ k ] || 0 ) + 1;
				if ( cnt[ k ] >= 2 && i > 0 && sig( kids[ i - 1 ] ) === sig( kids[ i ] ) ) { ctx.rep[ head( p ) + '>' + head( kids[ i ] ) ] = 1; }
			}
		} );
	}

	function label( el ) {
		var h = el.querySelector( 'h1,h2' ) || el.querySelector( 'h3' );
		var tag = lc( el );
		if ( tag === 'header' ) { return 'Шапка'; }
		if ( tag === 'footer' ) { return 'Подвал'; }
		if ( h && h.textContent.trim() ) { return h.innerHTML.replace( /<br\s*\/?>/gi, ' ' ).replace( /<[^>]+>/g, '' ).replace( /&nbsp;/g, ' ' ).replace( /&amp;/g, '&' ).replace( /\s+/g, ' ' ).trim().slice( 0, 60 ); }
		var a = el.getAttribute( 'aria-label' );
		if ( a ) { return a.slice( 0, 60 ); }
		return ( el.id ? '#' + el.id : '' ) || ( el.getAttribute( 'class' ) || tag ).split( ' ' )[ 0 ];
	}

	function section( el, ctx, role ) {
		var c = el.cloneNode( true );
		rewriteTree( c, ctx );
		ctx.n = 0;
		ctx.rep = {};
		markRepeatable( c, ctx );
		var vals = {}, meta = {}, tpls = {};
		walk( c, vals, ctx, meta, tpls );
		return { type: 'csec', role: role || '', label: label( el ), tpl: c.outerHTML, tpls: tpls, v: vals, m: meta };
	}

	function raw( el, ctx ) {
		var c = el.cloneNode( true );
		rewriteTree( c, ctx );
		return { type: 'craw', html: c.outerHTML, label: lc( el ) };
	}

	function isWrapper( el ) {
		var t = lc( el );
		if ( t === 'section' || t === 'header' || t === 'footer' || t === 'nav' || t === 'dialog' || t === 'form' ) { return false; }
		var big = Array.prototype.filter.call( el.children, function ( c ) {
			var ct = lc( c );
			return inA( [ 'section', 'article', 'aside', 'header', 'footer', 'nav', 'div', 'main', 'figure', 'form' ], ct ) && c.querySelector( 'h1,h2,h3,p,img,ul,a' );
		} );
		return ( t === 'main' || t === 'div' ) && big.length >= 2 && Array.prototype.some.call( el.children, function ( c ) { return lc( c ) === 'section'; } );
	}

	function blocks( parent, ctx ) {
		var out = [];
		Array.prototype.slice.call( parent.childNodes ).forEach( function ( n ) {
			if ( n.nodeType !== 1 ) { return; }
			var t = lc( n );
			if ( inA( [ 'script', 'noscript', 'template', 'style', 'link', 'meta' ], t ) ) { out.push( raw( n, ctx ) ); return; }
			if ( isWrapper( n ) ) {
				var c = n.cloneNode( false );
				rewriteTree( c, ctx );
				var open = c.outerHTML.replace( new RegExp( '</' + t + '>$' ), '' );
				out.push( { type: 'cwrap', open: open, close: '</' + t + '>', label: t === 'main' ? 'Основное' : label( n ), inner: blocks( n, ctx ) } );
				return;
			}
			out.push( section( n, ctx, t === 'header' ? 'header' : ( t === 'footer' ? 'footer' : '' ) ) );
		} );
		return out;
	}

	function attrs( el ) {
		var o = {};
		Array.prototype.slice.call( el.attributes ).forEach( function ( a ) { o[ a.name ] = a.value; } );
		return o;
	}

	/** Главная функция. html: исходник страницы, pageUrl: её адрес. */
	function convert( html, pageUrl, opts ) {
		var doc = new DOMParser().parseFromString( html, 'text/html' );
		var canon = doc.querySelector( 'link[rel=canonical]' );
		var hosts = [];
		if ( canon ) { try { hosts.push( new URL( canon.href, pageUrl ).host ); } catch ( e ) {} }
		var ctx = new Ctx( pageUrl, { hosts: hosts.concat( ( opts && opts.hosts ) || [] ) } );
		var hd = doc.head.cloneNode( true );
		var title = doc.title || '';
		var dm = doc.querySelector( 'meta[name=description]' );
		// запрет индексации концепта (черновик на Cloudflare) на боевой сайт не переносим
		var noindex = false;
		Array.prototype.slice.call( hd.querySelectorAll( 'meta[name]' ) ).forEach( function ( x ) {
			if ( /^(robots|googlebot|yandex|bingbot)$/i.test( x.getAttribute( 'name' ) ) && /noindex|nofollow|none/i.test( x.getAttribute( 'content' ) || '' ) ) { noindex = true; x.remove(); }
		} );
		[ 'title', 'meta[charset]', 'link[rel=canonical]', 'meta[name=description]', 'base' ].forEach( function ( q ) {
			Array.prototype.slice.call( hd.querySelectorAll( q ) ).forEach( function ( x ) { x.remove(); } );
		} );
		rewriteTree( hd, ctx );
		// абсолютные адреса концепта и боевого домена в мета и JSON-LD → адрес сайта
		var headHtml = hd.innerHTML;
		ctx.hosts.forEach( function ( h ) {
			headHtml = headHtml.split( 'https://' + h + '/' ).join( '⟦H⟧/' ).split( 'https://' + h + '"' ).join( '⟦H⟧/"' );
		} );
		var bl = blocks( doc.body, ctx );
		// черновые пометки концепта
		var warnings = [];
		bl.forEach( function ( b ) {
			if ( b.type === 'csec' && /^<\w+[^>]*\bclass="[^"]*\b(concept|concept-bar|demo-bar|draft)\b/.test( b.tpl ) ) {
				b.hide = true;
				b.label = 'Плашка «Концепт» (скрыта)';
				warnings.push( 'Плашка «Концепт Zhdanov Sites» сверху страницы скрыта (её можно вернуть: раздел → «Скрыть раздел на сайте»).' );
			}
		} );
		var cleanT = function ( t ) { return t.replace( /\s*[(\[]\s*(концепт|concept)[^)\]]*[)\]]/gi, '' ).replace( /\s*[·|—–-]\s*(концепт сайта|website concept|concept)\s*$/i, '' ).trim(); };
		var cleanD = function ( t ) { return t.split( /(?<=[.!?])\s+/ ).filter( function ( x ) { return ! /(концепт|concept)/i.test( x ); } ).join( ' ' ).trim(); };
		var title0 = title;
		title = cleanT( title );
		var desc = dm ? dm.getAttribute( 'content' ) : '';
		var desc0 = desc;
		desc = cleanD( desc );
		if ( title !== title0 ) { warnings.push( 'Из заголовка окна убрано: «' + title0.replace( title, '' ).trim() + '»' ); }
		if ( desc !== desc0 ) { warnings.push( 'Из описания для поиска убраны фразы про концепт.' ); }
		headHtml = headHtml.replace( /(<meta[^>]+(?:property|name)="(?:og:title|twitter:title)"[^>]*content=")([^"]*)/gi, function ( m, a, v ) { return a + cleanT( v ); } )
			.replace( /(<meta[^>]+(?:property|name)="(?:og:description|twitter:description)"[^>]*content=")([^"]*)/gi, function ( m, a, v ) { return a + cleanD( v ); } );
		// остальные упоминания в тексте: показать человеку
		var seen = {};
		( function scan( list ) {
			list.forEach( function ( b ) {
				if ( b.type === 'cwrap' ) { return scan( b.inner ); }
				if ( b.type !== 'csec' || b.hide ) { return; }
				var txt = render( b ).replace( /<script[\s\S]*?<\/script>|<style[\s\S]*?<\/style>/gi, '' ).replace( /<[^>]+>/g, ' ' ).replace( /\s+/g, ' ' );
				( txt.match( /[^.!?]{0,60}(концепт|concept)[^.!?]{0,60}/gi ) || [] ).forEach( function ( m ) {
					m = m.trim();
					if ( ! seen[ m ] ) { seen[ m ] = 1; warnings.push( 'В разделе «' + b.label + '» есть слово «концепт»: «' + m + '». Проверьте и исправьте текст.' ); }
				} );
			} );
		}( bl ) );
		var p = wpPath( decodeURIComponent( ctx.page.pathname ) );
		// служебная страница о самом концепте (разбор, как думал дизайнер): на сайт клиента не переносим
		// метка Alex 1: <meta name="zh-skip" content="почему">; без метки узнаём по адресу
		var skipMeta = doc.querySelector( 'meta[name="zh-skip"]' );
		var skip = skipMeta ? ( skipMeta.getAttribute( 'content' ) || 'помечена как служебная' ) : ( /(^|\/)(razbor|razbor-kontsepta|kontsept|about-concept|concept-notes|design-notes)(\/|$)/i.test( p ) ? 'похоже на разбор концепта' : '' );
		return {
			skip: skip,
			path: p,
			title: title,
			description: desc,
			warnings: warnings,
			htmlAttrs: attrs( doc.documentElement ),
			bodyAttrs: attrs( doc.body ),
			head: headHtml.trim(),
			blocks: bl,
			assets: Object.keys( ctx.assets ),
			noindex: noindex,
			links: Object.keys( ctx.pages ),
			linkUrls: ctx.orig
		};
	}

	/** Собрать HTML раздела из шаблона и значений (то же делает render.php на сайте). */
	function escA( s ) { return String( s == null ? '' : s ).replace( /&/g, '&amp;' ).replace( /"/g, '&quot;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ); }
	function fill( tpl, v, tpls ) {
		return tpl.replace( /⟪([tair]\d+)(?::(\w+))?⟫/g, function ( m, k, sub ) {
			var x = v[ k ], c = k.charAt( 0 );
			if ( c === 'r' ) {
				return ( x || [] ).map( function ( it ) { return fill( tpls[ k + '_' + ( it._t || 0 ) ] || '', it, tpls ); } ).join( tpls[ k + '_g' ] || '' );
			}
			if ( c === 'i' ) { return escA( x ? x[ sub ] : '' ); }
			if ( c === 'a' ) { return escA( x ); }
			return x == null ? '' : x;
		} );
	}
	function render( b ) {
		if ( b.type === 'craw' ) { return b.html; }
		if ( b.type === 'cwrap' ) { return b.open + b.inner.map( render ).join( '' ) + b.close; }
		if ( b.hide ) { return ''; }
		return fill( b.tpl, b.v, b.tpls );
	}

	/** Проверка без потерь: эталон (body с переписанными адресами) против сборки из блоков. */
	function selftest( html, pageUrl ) {
		var r = convert( html, pageUrl );
		var doc = new DOMParser().parseFromString( html, 'text/html' );
		var canon = doc.querySelector( 'link[rel=canonical]' ), hosts = [];
		if ( canon ) { try { hosts.push( new URL( canon.href, pageUrl ).host ); } catch ( e ) {} }
		var ctx = new Ctx( pageUrl, { hosts: hosts } );
		var b = doc.body.cloneNode( true );
		rewriteTree( b, ctx );
		var unhide = function ( l ) { l.forEach( function ( b ) { b.hide = false; if ( b.inner ) { unhide( b.inner ); } } ); };
		unhide( r.blocks );
		var built = new DOMParser().parseFromString( '<body>' + r.blocks.map( render ).join( '' ) + '</body>', 'text/html' ).body;
		var diffs = [];
		cmp( b, built, 'body', diffs );
		diffs = diffs.filter( function ( x ) { return x.indexOf( 'body @' ) !== 0; } );
		return { diffs: diffs.slice( 0, 20 ), count: diffs.length };
	}
	function nodes( el ) {
		return Array.prototype.filter.call( el.childNodes, function ( n ) { return n.nodeType === 1 || ( n.nodeType === 3 && n.nodeValue.trim() ); } );
	}
	function cmp( a, b, path, d ) {
		if ( d.length > 50 ) { return; }
		if ( a.nodeType !== b.nodeType ) { d.push( path + ': тип узла' ); return; }
		if ( a.nodeType === 3 ) {
			if ( a.nodeValue.replace( /\s+/g, ' ' ).trim() !== b.nodeValue.replace( /\s+/g, ' ' ).trim() ) { d.push( path + ': текст «' + a.nodeValue.trim().slice( 0, 40 ) + '» ≠ «' + b.nodeValue.trim().slice( 0, 40 ) + '»' ); }
			return;
		}
		if ( a.tagName !== b.tagName ) { d.push( path + ': тег ' + a.tagName + ' ≠ ' + b.tagName ); return; }
		var aa = attrs( a ), bb = attrs( b );
		Object.keys( aa ).concat( Object.keys( bb ) ).forEach( function ( k ) {
			if ( aa[ k ] !== bb[ k ] ) { d.push( path + ' @' + k + ': «' + String( aa[ k ] ).slice( 0, 50 ) + '» ≠ «' + String( bb[ k ] ).slice( 0, 50 ) + '»' ); }
		} );
		var na = nodes( a ), nb = nodes( b );
		if ( lc( a ) === 'script' || lc( a ) === 'style' ) { if ( a.textContent.trim() !== b.textContent.trim() ) { d.push( path + ': код' ); } return; }
		if ( na.length !== nb.length ) { d.push( path + ': детей ' + na.length + ' ≠ ' + nb.length ); }
		for ( var i = 0; i < Math.min( na.length, nb.length ); i++ ) { cmp( na[ i ], nb[ i ], path + '>' + ( na[ i ].tagName ? lc( na[ i ] ) + ( na[ i ].className && typeof na[ i ].className === 'string' ? '.' + na[ i ].className.split( ' ' )[ 0 ] : '' ) : '#t' ) + '[' + i + ']', d ); }
	}

	root.ZhConcept = { selftest: selftest, convert: convert, wpPath: wpPath, fill: fill, render: render, escA: escA };
}( typeof window !== 'undefined' ? window : this ) );
