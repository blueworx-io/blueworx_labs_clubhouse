/* Blueworx Clubhouse — Demo mode switcher. Site-wide demo state is toggled
 * by admins via a server link (admin-post); this file only handles the per-viewer
 * choices: the look (cookie + reload, so the server re-renders) and the accent
 * (applied live, no reload). No dependencies. */
( function () {
	'use strict';

	var LOOK = 'clubhouse_demo_look';
	var ACCENT = 'clubhouse_demo_accent';

	function setCookie( name, value ) {
		document.cookie = name + '=' + encodeURIComponent( value ) + '; path=/; SameSite=Lax';
	}

	function palettes() {
		return window.clubhouseDemoPalettes || {};
	}

	// The swatch's own colour comes from the palettes global, so the server markup
	// stays free of colour literals.
	function paintSwatches() {
		var all = palettes();
		var nodes = document.querySelectorAll( '[data-clubhouse-accent]' );
		Array.prototype.forEach.call( nodes, function ( node ) {
			var p = all[ node.getAttribute( 'data-clubhouse-accent' ) ];
			if ( p ) {
				node.style.background = p.hex;
			}
		} );
	}

	function applyAccent( slug ) {
		var p = palettes()[ slug ];
		if ( ! p ) {
			return;
		}
		var root = document.documentElement.style;
		Object.keys( p.tokens ).forEach( function ( token ) {
			root.setProperty( token, p.tokens[ token ] );
		} );
	}

	document.addEventListener( 'click', function ( e ) {
		var look = e.target.closest( '[data-clubhouse-look]' );
		if ( look ) {
			e.preventDefault();
			setCookie( LOOK, look.getAttribute( 'data-clubhouse-look' ) );
			window.location.reload();
			return;
		}
		var accent = e.target.closest( '[data-clubhouse-accent]' );
		if ( accent ) {
			e.preventDefault();
			var slug = accent.getAttribute( 'data-clubhouse-accent' );
			// Live: no reload. The cookie only makes it survive navigation — the head
			// script re-applies it on the next page, re-derived for that look.
			applyAccent( slug );
			setCookie( ACCENT, slug );
		}
	} );

	paintSwatches();
}() );
