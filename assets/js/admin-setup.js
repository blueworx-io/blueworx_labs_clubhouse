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

	// ---- Colour pickers ----
	//
	// Each .clubhouse-color field is a working text input on its own; here it is
	// upgraded in place to WordPress core's Iris picker (spectrum, swatch,
	// palette, clear) and wired to two things the picker does not do itself: a
	// live preview that repaints this panel as the colour moves, and a WCAG
	// contrast check against the active look's surfaces.
	//
	// The static swatch beside each field is the no-JS preview; Iris renders its
	// own button, so ours is hidden once the picker is up.
	var pickerCfg = { palette: [], shell: { bg: '#ffffff', ink: '#000000' } };
	var pickerEl = document.getElementById( 'clubhouse-color-picker' );
	if ( pickerEl ) {
		try { pickerCfg = JSON.parse( pickerEl.textContent || '{}' ); } catch ( e ) {}
	}

	// WCAG relative luminance and contrast ratio — the same maths the PHP colour
	// engine uses, so the warning shown live and the one shown after save agree.
	function toRgb( hex ) {
		var h = String( hex || '' ).replace( '#', '' );
		if ( h.length === 3 ) { h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2]; }
		if ( ! /^[0-9a-f]{6}$/i.test( h ) ) { return null; }
		return [ parseInt( h.slice( 0, 2 ), 16 ), parseInt( h.slice( 2, 4 ), 16 ), parseInt( h.slice( 4, 6 ), 16 ) ];
	}
	function luminance( hex ) {
		var rgb = toRgb( hex );
		if ( ! rgb ) { return null; }
		var lin = rgb.map( function ( c ) {
			var s = c / 255;
			return s <= 0.03928 ? s / 12.92 : Math.pow( ( s + 0.055 ) / 1.055, 2.4 );
		} );
		return 0.2126 * lin[0] + 0.7152 * lin[1] + 0.0722 * lin[2];
	}
	function ratio( a, b ) {
		var la = luminance( a ), lb = luminance( b );
		if ( la === null || lb === null ) { return null; }
		return ( Math.max( la, lb ) + 0.05 ) / ( Math.min( la, lb ) + 0.05 );
	}

	// A colour is workable when SOMETHING legible can sit on it — the better of
	// the look's ink or white must clear AA against the fill. This mirrors
	// Color_Engine::accent_is_legible: a desaturated mid-luminance colour clears
	// neither pole and has no legible text colour at all.
	function contrastNote( value ) {
		if ( ! value ) { return ''; }
		var onFill = Math.max( ratio( pickerCfg.shell.ink, value ) || 0, ratio( '#ffffff', value ) || 0 );
		if ( ! onFill ) { return ''; }
		if ( onFill >= 4.5 ) { return ''; }
		return 'Low contrast: no text colour reads clearly on this colour (best is '
			+ onFill.toFixed( 1 ) + ':1, WCAG AA needs 4.5:1). Try a stronger or darker shade.';
	}

	function showContrast( name, value ) {
		var note = root.querySelector( '[data-contrast-for="' + name + '"]' );
		if ( ! note ) { return; }
		var text = contrastNote( value );
		note.textContent = text;
		note.hidden = text === '';
	}

	function applyColor( field, value ) {
		var token = field.getAttribute( 'data-token' );
		var swatch = root.querySelector( '[data-color-swatch="' + field.name + '"]' );
		if ( value ) {
			// Live preview: the panel inherits from this root, so setting the token
			// here repaints every surface on the screen that consumes it.
			if ( token ) { root.style.setProperty( token, value ); }
			if ( swatch ) { swatch.style.background = value; }
		} else if ( token ) {
			// Cleared — drop back to whatever the look/derived value already was.
			root.style.removeProperty( token );
		}
		showContrast( field.name, value );
	}

	var colorFields = [].slice.call( root.querySelectorAll( '.clubhouse-color' ) );
	var $ = window.jQuery;
	if ( $ && $.fn && $.fn.wpColorPicker ) {
		colorFields.forEach( function ( field ) {
			$( field ).wpColorPicker( {
				palettes: pickerCfg.palette && pickerCfg.palette.length ? pickerCfg.palette : true,
				// Fires on every spectrum drag, so the preview tracks the pointer.
				change: function ( event, ui ) { applyColor( field, ui.color.toString() ); },
				clear: function () { applyColor( field, '' ); }
			} );
			root.classList.add( 'clubhouse-setup--picker' );
			showContrast( field.name, field.value );
		} );
	} else {
		// No jQuery or no picker: the plain hex field still previews and still warns.
		colorFields.forEach( function ( field ) {
			field.addEventListener( 'input', function () { applyColor( field, field.value ); } );
			showContrast( field.name, field.value );
		} );
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
