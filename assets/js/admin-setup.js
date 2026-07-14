/* Clubhouse Setup — progressive enhancement: tabs, live re-skin, media pickers. */
( function () {
	'use strict';
	var root = document.querySelector( '.clubhouse-setup' );
	if ( ! root ) { return; }
	root.classList.add( 'clubhouse-setup--js' );

	// Top tabs.
	var tabs = [].slice.call( root.querySelectorAll( '.clubhouse-tab' ) );
	var panels = [].slice.call( root.querySelectorAll( '.clubhouse-panel' ) );
	tabs.forEach( function ( tab ) {
		tab.addEventListener( 'click', function () {
			var key = tab.getAttribute( 'data-tab' );
			tabs.forEach( function ( t ) {
				t.classList.toggle( 'is-active', t === tab );
				t.setAttribute( 'aria-selected', t === tab ? 'true' : 'false' );
			} );
			panels.forEach( function ( p ) { p.classList.toggle( 'is-active', p.getAttribute( 'data-panel' ) === key ); } );
		} );
	} );

	// Visibility sub-tabs.
	var vtabs = [].slice.call( root.querySelectorAll( '.clubhouse-vistab' ) );
	var vpanels = [].slice.call( root.querySelectorAll( '.clubhouse-vispanel' ) );
	vtabs.forEach( function ( tab ) {
		tab.addEventListener( 'click', function () {
			var key = tab.getAttribute( 'data-vistab' );
			vtabs.forEach( function ( t ) {
				t.classList.toggle( 'is-active', t === tab );
				t.setAttribute( 'aria-selected', t === tab ? 'true' : 'false' );
			} );
			vpanels.forEach( function ( p ) { p.classList.toggle( 'is-active', p.getAttribute( 'data-vispanel' ) === key ); } );
		} );
	} );

	// Live re-skin on look selection.
	var tokenEl = document.getElementById( 'clubhouse-look-tokens' );
	var tokens = {};
	if ( tokenEl ) { try { tokens = JSON.parse( tokenEl.textContent || '{}' ); } catch ( e ) { tokens = {}; } }
	function applyLook( slug ) {
		var map = tokens[ slug ];
		if ( ! map ) { return; }
		Object.keys( map ).forEach( function ( name ) { root.style.setProperty( name, map[ name ] ); } );
	}
	[].slice.call( root.querySelectorAll( 'input[name="clubhouse_look"]' ) ).forEach( function ( radio ) {
		radio.addEventListener( 'change', function () { if ( radio.checked ) { applyLook( radio.value ); } } );
	} );

	// Accent swatch mirrors the hex field.
	var accent = document.getElementById( 'clubhouse_accent' );
	var swatch = document.getElementById( 'clubhouse-accent-swatch' );
	if ( accent && swatch ) {
		accent.addEventListener( 'input', function () { swatch.style.background = accent.value; } );
	}

	// Media pickers (logo + favicon) via wp.media.
	[].slice.call( root.querySelectorAll( '.clubhouse-media' ) ).forEach( function ( box ) {
		var field = box.querySelector( 'input[type="hidden"]' );
		var pick = box.querySelector( '[data-media-pick]' );
		var clear = box.querySelector( '[data-media-clear]' );
		var preview = box.querySelector( '.clubhouse-media__preview' );
		if ( ! field || ! pick || ! window.wp || ! window.wp.media ) { return; }
		var frame;
		pick.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			if ( ! frame ) {
				frame = window.wp.media( { title: 'Select an image', button: { text: 'Use this image' }, multiple: false } );
				frame.on( 'select', function () {
					var att = frame.state().get( 'selection' ).first().toJSON();
					field.value = String( att.id );
					if ( preview ) { preview.innerHTML = '<img class="clubhouse-media__img" src="' + att.url + '" alt="">'; }
				} );
			}
			frame.open();
		} );
		if ( clear ) {
			clear.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				field.value = '';
				if ( preview ) { preview.innerHTML = '<span class="clubhouse-media__empty" aria-hidden="true"></span>'; }
			} );
		}
	} );
}() );
