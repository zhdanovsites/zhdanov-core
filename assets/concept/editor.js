/**
 * Редактор разделов концепта.
 * Раздел показан в админке так же, как на сайте. Щелчок по тексту: правка прямо на месте.
 * Щелчок по фото: замена из медиатеки. Карточки: копия, выше, ниже, удалить (панель блока сверху).
 * Ссылки, подписи, скрытые тексты: справа в настройках блока.
 */
( function ( wp ) {
	if ( ! wp || ! wp.blocks ) { return; }
	var el = wp.element.createElement;
	var useRef = wp.element.useRef, useEffect = wp.element.useEffect, useState = wp.element.useState, Fragment = wp.element.Fragment;
	var be = wp.blockEditor, C = wp.components;
	var CFG = window.zhConceptEditor || { base: {}, home: '' };

	/* ---------- данные ---------- */
	function clone( o ) { return JSON.parse( JSON.stringify( o ) ); }
	function getAt( v, path ) {
		var x = v;
		for ( var i = 0; i < path.length && x != null; i++ ) { x = x[ path[ i ] ]; }
		return x;
	}
	function setAt( v, path, val ) {
		var n = clone( v ), x = n;
		for ( var i = 0; i < path.length - 1; i++ ) {
			if ( x[ path[ i ] ] == null ) { x[ path[ i ] ] = {}; }
			x = x[ path[ i ] ];
		}
		x[ path[ path.length - 1 ] ] = val;
		return n;
	}
	function P( s ) { return s ? s.split( '.' ).map( function ( p ) { return /^\d+$/.test( p ) ? +p : p; } ) : []; }
	function tok( html, site ) {
		return html.split( '⟦A⟧' ).join( ( CFG.base && CFG.base[ site ] ) || '' ).split( '⟦H⟧' ).join( CFG.home || '' );
	}
	function escA( s ) { return String( s == null ? '' : s ).replace( /&/g, '&amp;' ).replace( /"/g, '&quot;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ); }

	/** Как fill() на сайте, но с пометками для редактора: data-zk (текст), data-zi (фото), data-za-N (атрибут), data-zr (карточка). */
	function fillEdit( tpl, v, tpls, pre ) {
		var t = tpl.replace( /(<[a-zA-Z][^<>]*?)(\s*\/?)>(\s*)⟪(t\d+)⟫/g, function ( m, open, sl, sp, k ) {
			return open + ' data-zk="' + pre + k + '"' + sl + '>' + sp + '⟪' + k + '⟫';
		} );
		return t.replace( /(url\(\s*(?:&quot;|['"])?)?⟪([tair]\d+)(?::(\w+))?⟫/g, function ( m, bg, k, sub ) {
			var x = v[ k ], c = k.charAt( 0 ), path = pre + k;
			if ( c === 'r' ) {
				return ( x || [] ).map( function ( it, i ) {
					if ( it._hide ) { return ''; }
					var itpl = tpls[ k + '_' + ( it._t || 0 ) ] || '';
					itpl = itpl.replace( /^(\s*<[a-zA-Z][\w-]*)/, '$1 data-zr="' + path + '.' + i + '"' );
					return fillEdit( itpl, it, tpls, path + '.' + i + '.' );
				} ).join( tpls[ k + '_g' ] || '' );
			}
			if ( c === 'i' ) {
				var val = escA( x ? x[ sub ] : '' );
				if ( bg ) { return bg + val; }
				return sub === 'src' ? val + '" data-zi="' + path : val;
			}
			if ( c === 'a' ) { return escA( x ) + '" data-za-' + k + '="' + path; }
			// текст вне элемента (рядом с блоками): своя рамка для правки
			return x == null ? '' : x;
		} ).replace( /⟪(t\d+)⟫/g, '' );
	}
	window.ZhConceptEdit = { fillEdit: fillEdit }; // для автотестов

	/* ---------- медиатека ---------- */
	function pickImage( cb ) {
		if ( ! wp.media ) { window.alert( 'Медиатека недоступна на этой странице.' ); return; }
		var f = wp.media( { title: 'Выберите фото', button: { text: 'Поставить это фото' }, library: { type: 'image' }, multiple: false } );
		f.on( 'select', function () {
			var a = f.state().get( 'selection' ).first().toJSON();
			cb( { src: a.url, alt: a.alt || '', id: a.id, w: a.width, h: a.height } );
		} );
		f.open();
	}

	/* ---------- блок «Раздел концепта» ---------- */
	function SectionEdit( props ) {
		var a = props.attributes, set = props.setAttributes;
		var ref = useRef( null ), last = useRef( '' ), typing = useRef( false );
		var st = useState( null ), cur = st[ 0 ], setCur = st[ 1 ]; // выбранная карточка: "r5.2"
		var sk = useState( null ), curKey = sk[ 0 ], setCurKey = sk[ 1 ]; // выбранный текст/фото
		var bp = be.useBlockProps( { className: 'zh-csec-edit' + ( a.hide ? ' is-hidden-section' : '' ) } );
		var html = a.tpl ? tok( fillEdit( a.tpl, a.v || {}, a.tpls || {}, '' ), a.site ) : '';

		useEffect( function () {
			var box = ref.current;
			if ( ! box ) { return; }
			if ( typing.current ) { typing.current = false; last.current = html; return; }
			if ( html === last.current ) { return; }
			last.current = html;
			box.innerHTML = html;
			// ссылки в редакторе не уводят со страницы, формы не отправляются
			Array.prototype.forEach.call( box.querySelectorAll( '[data-zk]' ), function ( x ) { x.setAttribute( 'contenteditable', 'true' ); x.setAttribute( 'spellcheck', 'true' ); } );
			Array.prototype.forEach.call( box.querySelectorAll( 'img[loading=lazy]' ), function ( x ) { x.loading = 'eager'; } );
		}, [ html ] );

		function onInput( e ) {
			var t = e.target.closest && e.target.closest( '[data-zk]' );
			if ( ! t ) { return; }
			typing.current = true;
			set( { v: setAt( a.v || {}, P( t.getAttribute( 'data-zk' ) ), t.innerHTML ) } );
		}
		function onClick( e ) {
			var t = e.target;
			var a1 = t.closest && t.closest( 'a,button,form,label,summary' );
			if ( a1 && ! t.closest( '[data-zk]' ) ) { e.preventDefault(); }
			if ( t.closest && t.closest( '[data-zk]' ) ) { var lnk = t.closest( 'a' ); if ( lnk ) { e.preventDefault(); } }
			var it = t.closest && t.closest( '[data-zr]' );
			setCur( it ? it.getAttribute( 'data-zr' ) : null );
			var img = t.closest && t.closest( '[data-zi]' );
			if ( ! img && ! ( t.closest && t.closest( '[data-zk],a,button,input,select,textarea' ) ) ) {
				// фото под затемнением или подписью: ищем его под точкой щелчка
				var doc = ref.current.ownerDocument, under = doc.elementsFromPoint ? doc.elementsFromPoint( e.clientX, e.clientY ) : [];
				for ( var u = 0; u < under.length; u++ ) {
					if ( under[ u ].hasAttribute && under[ u ].hasAttribute( 'data-zi' ) && ref.current.contains( under[ u ] ) ) { img = under[ u ]; break; }
				}
				// щелчок по рамке, внутри которой одно фото
				if ( ! img && t !== ref.current && t.querySelectorAll ) {
					var inner = t.querySelectorAll( '[data-zi]' );
					if ( inner.length === 1 ) { img = inner[ 0 ]; }
				}
			}
			if ( img ) {
				e.preventDefault();
				var path = P( img.getAttribute( 'data-zi' ) );
				setCurKey( img.getAttribute( 'data-zi' ) );
				pickImage( function ( m ) {
					var old = getAt( a.v || {}, path ) || {};
					var nv = { src: m.src, alt: m.alt || old.alt || '' };
					if ( old.sizes !== undefined ) { nv.sizes = ''; }
					if ( old.srcset !== undefined ) { nv.srcset = ''; }
					var v2 = setAt( a.v || {}, path, nv );
					// источники <picture> этого фото больше не нужны
					var meta = a.m || {};
					Object.keys( meta ).forEach( function ( k ) { if ( meta[ k ] && meta[ k ].type === 'src' && meta[ k ].of === path[ path.length - 1 ] ) { v2 = setAt( v2, path.slice( 0, -1 ).concat( [ k ] ), '' ); } } );
					set( { v: v2 } );
				} );
				return;
			}
			var tk = t.closest && t.closest( '[data-zk]' );
			setCurKey( tk ? tk.getAttribute( 'data-zk' ) : null );
		}
		function onKey( e ) {
			if ( e.target.closest && e.target.closest( '[data-zk]' ) ) {
				if ( e.ctrlKey || e.metaKey || e.altKey ) { return; } // Ctrl+S, Ctrl+Z и другие сочетания работают как обычно
				e.stopPropagation();
				if ( e.key === 'Enter' && ! e.shiftKey && /^(H\d|A|BUTTON|SPAN|B|STRONG|EM|SMALL|LABEL)$/.test( e.target.closest( '[data-zk]' ).tagName ) ) { e.preventDefault(); }
			}
		}
		function onPaste( e ) {
			if ( ! ( e.target.closest && e.target.closest( '[data-zk]' ) ) ) { return; }
			e.preventDefault();
			var txt = ( e.clipboardData || window.clipboardData ).getData( 'text/plain' );
			document.execCommand( 'insertText', false, txt );
		}

		/* операции с карточками */
		function listOp( op ) {
			if ( ! cur ) { return; }
			var p = P( cur ), idx = p.pop(), list = getAt( a.v || {}, p );
			if ( ! Array.isArray( list ) ) { return; }
			var n = clone( list ), target = idx;
			if ( op === 'dup' ) { n.splice( idx + 1, 0, clone( list[ idx ] ) ); target = idx + 1; }
			if ( op === 'up' && idx > 0 ) { var u = n[ idx - 1 ]; n[ idx - 1 ] = n[ idx ]; n[ idx ] = u; target = idx - 1; }
			if ( op === 'down' && idx < n.length - 1 ) { var d = n[ idx + 1 ]; n[ idx + 1 ] = n[ idx ]; n[ idx ] = d; target = idx + 1; }
			if ( op === 'del' ) {
				if ( n.length <= 1 && ! window.confirm( 'Это последняя карточка в списке. Удалить её?' ) ) { return; }
				n.splice( idx, 1 ); target = null;
			}
			set( { v: setAt( a.v || {}, p, n ) } );
			setCur( target === null ? null : p.concat( [ target ] ).join( '.' ) );
		}
		var curInfo = null;
		if ( cur ) {
			var cp = P( cur ), ci = cp[ cp.length - 1 ], cl = getAt( a.v || {}, cp.slice( 0, -1 ) );
			if ( Array.isArray( cl ) ) { curInfo = { i: ci, n: cl.length }; }
		}

		/* боковая панель: ссылки, подписи, фото, скрытые тексты текущей карточки или раздела */
		function fields() {
			var scopePath = curInfo ? P( cur ) : [];
			var scopeV = curInfo ? getAt( a.v || {}, scopePath ) : ( a.v || {} );
			var meta = a.m || {};
			var out = [];
			function metaOf( k ) {
				if ( meta[ k ] ) { return meta[ k ]; }
				for ( var mk in meta ) { if ( meta[ mk ] && meta[ mk ].item && meta[ mk ].item[ k ] ) { return meta[ mk ].item[ k ]; } }
				return { label: k };
			}
			Object.keys( scopeV || {} ).forEach( function ( k ) {
				var c = k.charAt( 0 ), path = scopePath.concat( [ k ] ), mt = metaOf( k );
				if ( c === 'a' ) {
					if ( mt.type === 'src' ) { return; }
					out.push( el( C.TextControl, { key: k, label: mt.label + ( mt.attr && mt.type !== 'link' ? ' (' + mt.attr + ')' : '' ), value: String( scopeV[ k ] || '' ).replace( '⟦H⟧', '' ).replace( '⟦A⟧', '' ), help: mt.type === 'link' ? 'Страница сайта: /tury/bali/ · раздел: #program · почта: mailto:… · телефон: tel:+7…' : undefined, onChange: function ( nv ) {
						var orig = String( scopeV[ k ] || '' ), pre = orig.indexOf( '⟦H⟧' ) === 0 ? '⟦H⟧' : ( orig.indexOf( '⟦A⟧' ) === 0 ? '⟦A⟧' : '' );
						if ( pre && nv.charAt( 0 ) !== '/' ) { pre = ''; }
						set( { v: setAt( a.v || {}, path, pre + nv ) } );
					} } ) );
				} else if ( c === 'i' ) {
					var im = scopeV[ k ] || {};
					out.push( el( 'div', { key: k, className: 'zh-cimg' },
						el( 'img', { src: tok( im.src || '', a.site ), alt: '' } ),
						el( C.TextControl, { label: 'Описание фото (alt)', value: im.alt || '', onChange: function ( nv ) { set( { v: setAt( a.v || {}, path.concat( [ 'alt' ] ), nv ) } ); } } ),
						el( C.Button, { variant: 'secondary', onClick: function () { pickImage( function ( m ) { var nv2 = clone( im ); nv2.src = m.src; if ( nv2.srcset !== undefined ) { nv2.srcset = ''; } if ( nv2.sizes !== undefined ) { nv2.sizes = ''; } if ( ! nv2.alt ) { nv2.alt = m.alt; } set( { v: setAt( a.v || {}, path, nv2 ) } ); } ); } }, 'Заменить фото' )
					) );
				} else if ( c === 't' && ( curInfo || out.length < 400 ) ) {
					var box = ref.current && ref.current.querySelector( '[data-zk="' + path.join( '.' ) + '"]' );
					var hidden = ! box || ! box.offsetParent;
					if ( hidden ) {
						out.push( el( C.TextareaControl, { key: k, label: ( box ? 'Скрытый текст: ' : 'Текст: ' ) + ( mt.label || k ), help: box ? 'Показывается на сайте по кнопке или во вкладке' : undefined, value: String( scopeV[ k ] || '' ), rows: 2, onChange: function ( nv ) { set( { v: setAt( a.v || {}, path, nv ) } ); } } ) );
					}
				}
			} );
			return out;
		}

		return el( Fragment, null,
			el( be.BlockControls, { group: 'block' },
				curInfo ? el( C.ToolbarGroup, { label: 'Карточка' },
					el( C.ToolbarButton, { icon: 'admin-page', label: 'Копия карточки', onClick: function () { listOp( 'dup' ); } } ),
					el( C.ToolbarButton, { icon: 'arrow-up-alt2', label: 'Выше', disabled: curInfo.i === 0, onClick: function () { listOp( 'up' ); } } ),
					el( C.ToolbarButton, { icon: 'arrow-down-alt2', label: 'Ниже', disabled: curInfo.i >= curInfo.n - 1, onClick: function () { listOp( 'down' ); } } ),
					el( C.ToolbarButton, { icon: 'trash', label: 'Удалить карточку', onClick: function () { listOp( 'del' ); } } )
				) : null
			),
			el( be.InspectorControls, null,
				el( C.PanelBody, { title: curInfo ? 'Карточка ' + ( curInfo.i + 1 ) + ' из ' + curInfo.n : 'Раздел', initialOpen: true },
					curInfo ? el( 'div', { className: 'zh-cbtns' },
						el( C.Button, { variant: 'secondary', icon: 'admin-page', onClick: function () { listOp( 'dup' ); } }, 'Копия' ),
						el( C.Button, { variant: 'secondary', icon: 'arrow-up-alt2', disabled: curInfo.i === 0, onClick: function () { listOp( 'up' ); } }, 'Выше' ),
						el( C.Button, { variant: 'secondary', icon: 'arrow-down-alt2', disabled: curInfo.i >= curInfo.n - 1, onClick: function () { listOp( 'down' ); } }, 'Ниже' ),
						el( C.Button, { variant: 'secondary', isDestructive: true, icon: 'trash', onClick: function () { listOp( 'del' ); } }, 'Удалить' ),
						el( 'p', { className: 'zh-chint' }, 'Копия появится сразу после этой карточки. Тексты правьте прямо на карточке, фото меняйте щелчком по фото.' )
					) : el( 'p', { className: 'zh-chint' }, 'Щёлкните по тексту, чтобы исправить его. Щёлкните по фото, чтобы заменить. Щёлкните по карточке (тур, отзыв, услуга), чтобы скопировать, переставить или удалить её.' ),
					curInfo ? null : el( C.ToggleControl, { label: 'Скрыть раздел на сайте', checked: !! a.hide, onChange: function ( x ) { set( { hide: x } ); } } )
				),
				el( C.PanelBody, { title: curInfo ? 'Ссылки и фото карточки' : 'Ссылки, подписи и фото раздела', initialOpen: true }, fields() )
			),
			el( 'div', bp,
				el( 'div', { ref: ref, className: 'zh-csec-box', onInput: onInput, onClickCapture: onClick, onKeyDown: onKey, onPaste: onPaste, onSubmit: function ( e ) { e.preventDefault(); } } ),
				a.hide ? el( 'div', { className: 'zh-csec-hidden' }, 'Раздел скрыт на сайте' ) : null
			)
		);
	}

	wp.blocks.registerBlockType( 'zhdanov/csec', {
		apiVersion: 3,
		title: 'Раздел концепта',
		icon: 'layout',
		category: 'design',
		attributes: { tpl: { type: 'string' }, tpls: { type: 'object' }, v: { type: 'object' }, m: { type: 'object' }, role: { type: 'string' }, hide: { type: 'boolean' }, site: { type: 'string' }, metadata: { type: 'object' } },
		supports: { html: false, className: false, customClassName: false, reusable: true },
		__experimentalLabel: function ( a ) { return ( a.metadata && a.metadata.name ) || 'Раздел'; },
		edit: SectionEdit,
		save: function () { return null; }
	} );

	wp.blocks.registerBlockType( 'zhdanov/cwrap', {
		apiVersion: 3,
		title: 'Обёртка концепта',
		icon: 'editor-contract',
		category: 'design',
		attributes: { open: { type: 'string' }, close: { type: 'string' }, site: { type: 'string' }, metadata: { type: 'object' } },
		supports: { html: false, className: false, customClassName: false },
		edit: function ( props ) {
			var m = ( props.attributes.open || '' ).match( /class="([^"]*)"/ );
			var bp = be.useBlockProps( { className: 'zh-cwrap-edit ' + ( m ? m[ 1 ] : '' ) } );
			var ib = be.useInnerBlocksProps( bp, { templateLock: false, renderAppender: false } );
			return el( 'div', ib );
		},
		save: function () { return el( be.InnerBlocks.Content ); }
	} );

	wp.blocks.registerBlockType( 'zhdanov/craw', {
		apiVersion: 3,
		title: 'Код концепта',
		icon: 'editor-code',
		category: 'design',
		attributes: { html: { type: 'string' }, site: { type: 'string' }, metadata: { type: 'object' } },
		supports: { html: false, inserter: false },
		edit: function () {
			var bp = be.useBlockProps( { className: 'zh-craw-edit' } );
			return el( 'div', bp, 'Служебный код концепта (скрипт), на сайте не виден' );
		},
		save: function () { return null; }
	} );

	// Панель «Поиск и соцсети»: заголовок окна и описание страницы
	var PDS = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );
	if ( CFG.postOnly && PDS && wp.plugins && wp.data ) {
		wp.plugins.registerPlugin( 'zh-concept-seo', { render: function () {
			var meta = wp.data.useSelect( function ( s ) { return s( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {}; }, [] );
			var edit = wp.data.useDispatch( 'core/editor' ).editPost;
			var def = CFG.seo || {};
			function set( k, v ) { var m = {}; m[ k ] = v; edit( { meta: m } ); }
			var t = meta._zh_seo_title || '', d = meta._zh_seo_desc || '';
			return el( PDS, { name: 'zh-concept-seo', title: 'Поиск и соцсети' },
				el( C.TextControl, { label: 'Заголовок в поиске и во вкладке', value: t, placeholder: def.title || '', help: ( t || def.title || '' ).length + ' знаков, лучше до 60. Пусто: как в концепте.', onChange: function ( v ) { set( '_zh_seo_title', v ); } } ),
				el( C.TextareaControl, { label: 'Описание в поиске', value: d, placeholder: def.description || '', rows: 4, help: ( d || def.description || '' ).length + ' знаков, лучше 120–160. Пусто: как в концепте.', onChange: function ( v ) { set( '_zh_seo_desc', v ); } } )
			);
		} } );
	}

	// На страницах концепта показываем только содержимое, без шаблона темы вокруг.
	if ( CFG.postOnly && wp.data ) {
		var tries = 0;
		var timer = setInterval( function () {
			tries++;
			var ed = wp.data.dispatch( 'core/editor' );
			if ( ed && ed.setRenderingMode ) { ed.setRenderingMode( 'post-only' ); clearInterval( timer ); }
			if ( tries > 40 ) { clearInterval( timer ); }
		}, 250 );
	}
}( window.wp ) );
