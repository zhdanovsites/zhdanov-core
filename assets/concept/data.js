/**
 * «Жданов → Данные концепта»: списки из скриптов (события, расписание, цены) как форма.
 */
( function () {
	var app = document.getElementById( 'zh-cdata-app' );
	var api = window.wp && window.wp.apiFetch;
	var CFG = window.zhCdata || {};
	if ( ! app || ! api ) { return; }

	function h( tag, attrs, kids ) {
		var e = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( k ) {
			if ( k === 'text' ) { e.textContent = attrs[ k ]; }
			else if ( k.indexOf( 'on' ) === 0 ) { e.addEventListener( k.slice( 2 ), attrs[ k ] ); }
			else if ( attrs[ k ] !== null && attrs[ k ] !== undefined && attrs[ k ] !== false ) { e.setAttribute( k, attrs[ k ] === true ? '' : attrs[ k ] ); }
		} );
		( kids || [] ).forEach( function ( c ) { if ( c ) { e.appendChild( typeof c === 'string' ? document.createTextNode( c ) : c ); } } );
		return e;
	}
	function clone( o ) { return JSON.parse( JSON.stringify( o ) ); }
	function isImg( v ) { return typeof v === 'string' && /\.(webp|jpe?g|png|gif|avif|svg)(\?.*)?$/i.test( v ); }
	function imgUrl( v ) {
		if ( /^https?:|^\/\//.test( v ) ) { return v; }
		return CFG.base + '/' + v.replace( /^\.?\//, '' );
	}
	function blank( v ) {
		if ( Array.isArray( v ) ) { return []; }
		if ( v && typeof v === 'object' ) { var o = {}; Object.keys( v ).forEach( function ( k ) { o[ k ] = blank( v[ k ] ); } ); return o; }
		if ( typeof v === 'number' ) { return 0; }
		if ( typeof v === 'boolean' ) { return false; }
		return '';
	}
	var LABELS = { title: 'Название', name: 'Название', tag: 'Метка', start: 'Начало', end: 'Конец', date: 'Дата', time: 'Время', when: 'Когда (текстом)', img: 'Фото', image: 'Фото', text: 'Описание', desc: 'Описание', inside: 'Что внутри (пункты)', price: 'Цена', note: 'Примечание', btn: 'Текст кнопки', url: 'Ссылка', link: 'Ссылка', place: 'Где', day: 'День недели (0 = вс … 6 = сб)', preacher: 'Проповедник', reading: 'Чтение' };

	/** Поле для значения любого типа. set(newValue) сохраняет в модель. */
	function field( key, v, set ) {
		var label = LABELS[ key ] || key;
		var box;
		if ( typeof v === 'boolean' ) {
			box = h( 'input', { type: 'checkbox', checked: v, onchange: function ( e ) { set( e.target.checked ); } } );
		} else if ( typeof v === 'number' ) {
			box = h( 'input', { type: 'number', value: String( v ), step: 'any', class: 'small-text', oninput: function ( e ) { set( e.target.value === '' ? 0 : +e.target.value ); } } );
		} else if ( Array.isArray( v ) && v.every( function ( x ) { return typeof x === 'string' || typeof x === 'number'; } ) ) {
			box = h( 'div', { class: 'zh-cd-sub' } );
			var draw = function () {
				box.innerHTML = '';
				v.forEach( function ( x, i ) {
					box.appendChild( h( 'div', { class: 'zh-cd-li' }, [
						h( 'input', { type: 'text', value: String( x ), class: 'regular-text', oninput: function ( e ) { v[ i ] = typeof x === 'number' ? +e.target.value : e.target.value; set( v ); } } ),
						h( 'button', { type: 'button', class: 'button', 'aria-label': 'Удалить пункт', text: '×', onclick: function () { v.splice( i, 1 ); set( v ); draw(); } } )
					] ) );
				} );
				box.appendChild( h( 'button', { type: 'button', class: 'button button-small', text: '+ пункт', onclick: function () { v.push( '' ); set( v ); draw(); } } ) );
			};
			draw();
		} else if ( v === null || typeof v === 'object' ) {
			box = h( 'textarea', { rows: 2, class: 'code', oninput: function ( e ) { try { set( e.target.value.trim() === '' ? null : JSON.parse( e.target.value ) ); e.target.style.borderColor = ''; } catch ( x ) { e.target.style.borderColor = '#d63638'; } } } );
			box.value = v === null ? '' : JSON.stringify( v );
			label += ' (пусто = нет)';
		} else if ( isImg( v ) ) {
			var im = h( 'img', { src: imgUrl( v ), alt: '' } );
			var inp = h( 'input', { type: 'text', value: v, class: 'regular-text', oninput: function ( e ) { set( e.target.value ); im.src = imgUrl( e.target.value ); } } );
			box = h( 'div', { class: 'zh-cd-img' }, [ im, inp, h( 'button', { type: 'button', class: 'button', text: 'Выбрать фото', onclick: function () {
				var f = window.wp.media( { title: 'Фото', library: { type: 'image' }, multiple: false } );
				f.on( 'select', function () { var a = f.state().get( 'selection' ).first().toJSON(); inp.value = a.url; im.src = a.url; set( a.url ); } );
				f.open();
			} } ) ] );
		} else {
			var long = String( v ).length > 70 || /\n/.test( v );
			box = h( long ? 'textarea' : 'input', long ? { rows: 3, oninput: function ( e ) { set( e.target.value ); } } : { type: 'text', value: String( v ), class: 'regular-text', oninput: function ( e ) { set( e.target.value ); } } );
			if ( long ) { box.value = String( v ); }
			if ( /^\d{4}-\d{2}-\d{2}/.test( String( v ) ) ) { box.setAttribute( 'title', 'Формат: 2026-12-25 или 2026-12-25T19:00:00+03:00' ); }
		}
		return h( 'div', { class: 'zh-cd-f' }, [ h( 'label', { text: label } ), box ] );
	}

	/** Фото строки по шаблону скрипта (images/${p.id}.webp): миниатюра и «Загрузить фото». */
	function photoField( ph, r ) {
		var val = r[ ph.key ] == null ? '' : String( r[ ph.key ] );
		var path = ph.prefix + val + ph.suffix;
		var note = h( 'span', { class: 'description', text: val ? 'Файл: ' + path.replace( /^\//, '' ) : 'Сначала заполните поле «' + ph.key + '» и сохраните: по нему фото получает имя.' } );
		var im = h( 'img', { src: val ? CFG.base + path + '?t=' + Date.now() : '', alt: '' } );
		im.addEventListener( 'error', function () { im.removeAttribute( 'src' ); note.textContent = 'Фото ещё нет: загрузите.'; } );
		var btn = h( 'button', { type: 'button', class: 'button', text: 'Загрузить фото', disabled: ! val, onclick: function () {
			var f = window.wp.media( { title: 'Фото для «' + ( r.name || r.title || val ) + '»', library: { type: 'image' }, multiple: false, button: { text: 'Поставить это фото' } } );
			f.on( 'select', function () {
				var a = f.state().get( 'selection' ).first().toJSON();
				note.textContent = 'Сохраняю фото…';
				btn.disabled = true;
				api( { path: '/zhdanov/v1/concept/data/photo', method: 'POST', data: { slug: CFG.slug, path: path, att: a.id } } )
					.then( function ( x ) { im.src = x.url; note.textContent = 'Фото на сайте ✅ (' + path.replace( /^\//, '' ) + ')'; btn.disabled = false; } )
					.catch( function ( e ) { note.textContent = 'Ошибка: ' + ( e.message || e.code ); btn.disabled = false; } );
			} );
			f.open();
		} } );
		return h( 'div', { class: 'zh-cd-f' }, [ h( 'label', { text: 'Фото' } ), h( 'div', { class: 'zh-cd-img zh-cd-photo' }, [ im, btn, note ] ) ] );
	}

	function rowTitle( r, i ) {
		var t = r && ( r.title || r.name || r.date || r.tag );
		return ( i + 1 ) + '. ' + ( t ? String( t ).slice( 0, 70 ) : 'строка' );
	}

	function list( id, meta ) {
		var wrap = h( 'div', { class: 'zh-cd-list' } );
		var head = h( 'h2', null, [ meta.name, h( 'small', { text: meta.file } ) ] );
		wrap.appendChild( head );
		if ( meta.hint ) { wrap.appendChild( h( 'p', { class: 'zh-cd-hint', text: meta.hint } ) ); }
		var body = h( 'div', { class: 'zh-cd-rows' }, [ h( 'p', { text: 'Загрузка…' } ) ] );
		wrap.appendChild( body );
		var status = h( 'span', { class: 'zh-cd-st' } );
		var save = h( 'button', { type: 'button', class: 'button button-primary', text: 'Сохранить', disabled: true } );
		wrap.appendChild( h( 'div', { class: 'zh-cd-bar' }, [ save, status ] ) );
		var model = null;
		function dirty() { save.disabled = false; status.textContent = 'Есть несохранённые изменения'; }
		function draw() {
			body.innerHTML = '';
			if ( meta.shape === 'value' ) { body.appendChild( field( meta.name, model, function ( x ) { model = x; dirty(); } ) ); return; }
			if ( meta.shape === 'map' ) {
				Object.keys( model ).forEach( function ( k ) { body.appendChild( field( k, model[ k ], function ( x ) { model[ k ] = x; dirty(); } ) ); } );
				return;
			}
			if ( meta.shape === 'list' ) {
				model.forEach( function ( x, i ) { body.appendChild( field( String( i ), x, function ( y ) { model[ i ] = y; dirty(); } ) ); } );
				return;
			}
			var keys = {};
			model.concat( meta.example ? [ meta.example ] : [] ).forEach( function ( r ) { Object.keys( r || {} ).forEach( function ( k ) { keys[ k ] = 1; } ); } );
			if ( ! model.length ) { body.appendChild( h( 'p', { text: 'Пока пусто.' } ) ); }
			model.forEach( function ( r, i ) {
				var card = h( 'div', { class: 'zh-cd-row' } );
				card.appendChild( h( 'div', { class: 'zh-cd-tools' }, [
					h( 'button', { type: 'button', class: 'button button-small', text: '↑', 'aria-label': 'Выше', disabled: i === 0, onclick: function () { var t = model[ i - 1 ]; model[ i - 1 ] = model[ i ]; model[ i ] = t; dirty(); draw(); } } ),
					h( 'button', { type: 'button', class: 'button button-small', text: '↓', 'aria-label': 'Ниже', disabled: i === model.length - 1, onclick: function () { var t = model[ i + 1 ]; model[ i + 1 ] = model[ i ]; model[ i ] = t; dirty(); draw(); } } ),
					h( 'button', { type: 'button', class: 'button button-small', text: 'Копия', onclick: function () { model.splice( i + 1, 0, clone( r ) ); dirty(); draw(); } } ),
					h( 'button', { type: 'button', class: 'button button-small button-link-delete', text: 'Удалить', onclick: function () { if ( window.confirm( 'Удалить строку «' + rowTitle( r, i ) + '»?' ) ) { model.splice( i, 1 ); dirty(); draw(); } } } )
				] ) );
				card.appendChild( h( 'h3', { text: rowTitle( r, i ) } ) );
				if ( meta.photo ) { card.appendChild( photoField( meta.photo, r ) ); }
				Object.keys( keys ).forEach( function ( k ) {
					var has = Object.prototype.hasOwnProperty.call( r, k );
					var v = has ? r[ k ] : blank( ( meta.example && meta.example[ k ] !== undefined ) ? meta.example[ k ] : '' );
					card.appendChild( field( k, v, function ( x ) { r[ k ] = x; dirty(); } ) );
				} );
				body.appendChild( card );
			} );
			body.appendChild( h( 'button', { type: 'button', class: 'button', text: '+ Добавить строку', onclick: function () {
				var tpl = model.length ? blank( model[ model.length - 1 ] ) : ( meta.example ? clone( meta.example ) : {} );
				model.push( tpl ); dirty(); draw();
			} } ) );
		}
		save.addEventListener( 'click', function () {
			save.disabled = true;
			status.textContent = 'Сохраняю…';
			api( { path: '/zhdanov/v1/concept/data', method: 'POST', data: { slug: CFG.slug, id: id, value: model } } )
				.then( function () { status.textContent = 'Сохранено ✅ Сайт обновлён.'; if ( meta.photo ) { draw(); } } )
				.catch( function ( e ) { save.disabled = false; status.textContent = 'Ошибка: ' + ( e.message || e.code ); } );
		} );
		api( { path: '/zhdanov/v1/concept/data?slug=' + encodeURIComponent( CFG.slug ) + '&id=' + id } ).then( function ( r ) { model = r.value; draw(); } );
		return wrap;
	}

	api( { path: '/zhdanov/v1/concept/data?rescan=1&slug=' + encodeURIComponent( CFG.slug ) } ).then( function ( r ) {
		app.innerHTML = '';
		var ids = Object.keys( r.lists || {} ).sort( function ( a, b ) { return ( r.lists[ a ].file + r.lists[ a ].order ).localeCompare( r.lists[ b ].file + r.lists[ b ].order, undefined, { numeric: true } ); } );
		if ( ! ids.length ) { app.appendChild( h( 'p', { text: 'В скриптах этого концепта нет списков данных: все тексты правятся прямо на страницах.' } ) ); return; }
		ids.forEach( function ( id ) { app.appendChild( list( id, r.lists[ id ] ) ); } );
	} ).catch( function ( e ) { app.textContent = 'Ошибка: ' + ( e.message || e.code ); } );
}() );
