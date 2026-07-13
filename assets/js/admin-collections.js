/* Clubhouse collection meta-boxes: wp.media image picker for hidden-id fields. */
( function () {
	document.addEventListener( 'click', function ( e ) {
		var t = e.target;
		if ( ! t || ! t.classList ) {
			return;
		}
		if ( t.classList.contains( 'clubhouse-meta__pick' ) ) {
			e.preventDefault();
			var target = document.getElementById( t.getAttribute( 'data-target' ) );
			if ( ! target || ! window.wp || ! window.wp.media ) {
				return;
			}
			var frame = window.wp.media( { title: 'Choose image', multiple: false } );
			frame.on( 'select', function () {
				var att = frame.state().get( 'selection' ).first().toJSON();
				target.value = att.id;
				var img = target.parentNode.querySelector( '.clubhouse-meta__preview' );
				if ( img ) {
					img.src = ( att.sizes && att.sizes.thumbnail ) ? att.sizes.thumbnail.url : att.url;
					img.style.display = '';
				}
			} );
			frame.open();
		}
		if ( t.classList.contains( 'clubhouse-meta__clear' ) ) {
			e.preventDefault();
			var tgt = document.getElementById( t.getAttribute( 'data-target' ) );
			if ( ! tgt ) {
				return;
			}
			tgt.value = '';
			var im = tgt.parentNode.querySelector( '.clubhouse-meta__preview' );
			if ( im ) {
				im.style.display = 'none';
			}
		}
	} );
} )();
