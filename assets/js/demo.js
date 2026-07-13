/* Blueworx Clubhouse — admin Demo mode. Per-admin, cookie-driven, reload to
 * re-render. No dependencies. Server honours cookies only for capable admins. */
( function () {
	'use strict';

	var FLAG = 'clubhouse_demo';
	var LOOK = 'clubhouse_demo_look';

	function setCookie( name, value ) {
		document.cookie = name + '=' + encodeURIComponent( value ) + '; path=/; SameSite=Lax';
	}
	function clearCookie( name ) {
		document.cookie = name + '=; path=/; Max-Age=0; SameSite=Lax';
	}
	function getCookie( name ) {
		var m = document.cookie.match( '(?:^|; )' + name + '=([^;]*)' );
		return m ? decodeURIComponent( m[ 1 ] ) : '';
	}

	// Admin-bar toggle: flip the on/off flag and reload.
	document.addEventListener( 'click', function ( e ) {
		var toggle = e.target.closest( '#wp-admin-bar-clubhouse-demo-toggle a, #wp-admin-bar-clubhouse-demo-toggle' );
		if ( toggle ) {
			e.preventDefault();
			if ( '1' === getCookie( FLAG ) ) {
				clearCookie( FLAG );
			} else {
				setCookie( FLAG, '1' );
			}
			window.location.reload();
			return;
		}

		// Switcher: choose a look.
		var look = e.target.closest( '[data-clubhouse-look]' );
		if ( look ) {
			e.preventDefault();
			setCookie( LOOK, look.getAttribute( 'data-clubhouse-look' ) );
			window.location.reload();
			return;
		}

		// Switcher: exit demo (clear both cookies).
		var exit = e.target.closest( '[data-clubhouse-demo-exit]' );
		if ( exit ) {
			e.preventDefault();
			clearCookie( FLAG );
			clearCookie( LOOK );
			window.location.reload();
		}
	} );
}() );
