/* Blueworx Clubhouse — Demo mode look switcher. Site-wide demo state is toggled
 * by admins via a server link (admin-post); this file only handles the per-viewer
 * look choice: set a cookie and reload so the server re-renders in that look.
 * No dependencies. */
( function () {
	'use strict';

	var LOOK = 'clubhouse_demo_look';

	document.addEventListener( 'click', function ( e ) {
		var look = e.target.closest( '[data-clubhouse-look]' );
		if ( ! look ) {
			return;
		}
		e.preventDefault();
		document.cookie = LOOK + '=' + encodeURIComponent( look.getAttribute( 'data-clubhouse-look' ) ) + '; path=/; SameSite=Lax';
		window.location.reload();
	} );
}() );
