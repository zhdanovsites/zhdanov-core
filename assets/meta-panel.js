/* Поля раздела в правой колонке редактора (Афиша, Услуги, Отзывы, Альбомы, Каталог). Настройки приходят из PHP (zhMeta). */
( function ( wp, cfg ) {
	if ( ! cfg ) { return; }
	var el = wp.element.createElement;
	var c = wp.components;
	var Panel = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );
	var NAME = 'zhdanov-meta-panel', PANEL = 'zh-meta';

	function Fields() {
		var postType = wp.data.useSelect( function ( s ) { return s( 'core/editor' ).getCurrentPostType(); }, [] );
		var res = wp.coreData.useEntityProp( 'postType', postType, 'meta' );
		var meta = res[ 0 ] || {}, setMeta = res[ 1 ];
		wp.element.useEffect( function () {
			var id = NAME + '/' + PANEL, ed = wp.data.select( 'core/editor' );
			if ( ed.isEditorPanelOpened && ! ed.isEditorPanelOpened( id ) ) { wp.data.dispatch( 'core/editor' ).toggleEditorPanelOpened( id ); }
		}, [] );
		function set( key ) { return function ( v ) { var o = {}; o[ key ] = v; setMeta( Object.assign( {}, meta, o ) ); }; }
		return el( Panel, { name: PANEL, title: cfg.title, className: 'zh-meta-panel' },
			cfg.fields.map( function ( f ) {
				var common = { key: f.key, label: f.label, help: f.help, value: meta[ f.key ] || ( f.options.length ? f.options[ 0 ].value : '' ), onChange: set( f.key ), __nextHasNoMarginBottom: true };
				if ( 'select' === f.type ) { return el( c.SelectControl, Object.assign( common, { options: f.options } ) ); }
				if ( 'textarea' === f.type ) { return el( c.TextareaControl, Object.assign( common, { value: meta[ f.key ] || '', rows: 6 } ) ); }
				return el( c.TextControl, Object.assign( common, { type: 'datetime' === f.type ? 'datetime-local' : ( 'date' === f.type || 'time' === f.type || 'url' === f.type ? f.type : 'text' ), value: meta[ f.key ] || '' } ) );
			} )
		);
	}
	wp.plugins.registerPlugin( NAME, { render: Fields } );
} )( window.wp, window.zhMeta );
