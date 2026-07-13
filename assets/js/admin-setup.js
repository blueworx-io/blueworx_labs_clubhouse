/* Clubhouse Setup — logo media picker (progressive enhancement). */
( function () {
	'use strict';
	var pick = document.getElementById( 'clubhouse-logo-pick' );
	var clear = document.getElementById( 'clubhouse-logo-clear' );
	var field = document.getElementById( 'clubhouse_logo' );
	if ( ! pick || ! field || ! window.wp || ! window.wp.media ) {
		return;
	}
	var frame;
	pick.addEventListener( 'click', function ( e ) {
		e.preventDefault();
		if ( ! frame ) {
			frame = window.wp.media( { title: 'Select a logo', button: { text: 'Use this logo' }, multiple: false } );
			frame.on( 'select', function () {
				var att = frame.state().get( 'selection' ).first().toJSON();
				field.value = String( att.id );
				var img = document.querySelector( '.clubhouse-logo-preview' );
				if ( img && img.tagName === 'IMG' ) {
					img.src = att.url;
				}
			} );
		}
		frame.open();
	} );
	if ( clear ) {
		clear.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			field.value = '';
		} );
	}
}() );
