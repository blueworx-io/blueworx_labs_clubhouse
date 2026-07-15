/* Clubhouse Site Content — progressive enhancement: single-panel page/section
 * switching, tab-strip drag-scroll, per-field media pickers, dirty tracking.
 * Every no-JS path (anchor navigation, form submit, Add/Remove submit buttons)
 * keeps working untouched — this file only layers behaviour on top. */
( function () {
	'use strict';
	var root = document.querySelector( '.clubhouse-content' );
	if ( ! root ) { return; }
	root.classList.add( 'clubhouse-content--js' );

	/**
	 * Generic single-active-item tablist wiring shared by the page tabs and the
	 * per-page section nav. Both groups are real <a href> links in the no-JS
	 * markup (so a disabled-JS user still reaches every page/section by
	 * anchor-jump or full navigation); here we intercept the click to switch
	 * locally instead, and lay proper ARIA tab semantics on top since this is
	 * the only place that can honestly claim single-panel-at-a-time behaviour.
	 *
	 * @param {Element[]} tabs   the nav <a> elements (a single group)
	 * @param {Element[]} panels the panels they control, in the same order
	 * @param {string} tabKeyAttr  data-* attribute on each tab holding its key
	 * @param {string} panelKeyAttr data-* attribute on each panel holding its key
	 * @param {string} idPrefix  prefix used to mint ids for aria-controls/aria-labelledby
	 */
	function wireTablist( tabs, panels, tabKeyAttr, panelKeyAttr, idPrefix ) {
		if ( ! tabs.length || ! panels.length ) { return; }
		var list = tabs[ 0 ].parentElement;
		if ( list ) { list.setAttribute( 'role', 'tablist' ); }

		function panelFor( tab ) {
			var key = tab.getAttribute( tabKeyAttr );
			for ( var i = 0; i < panels.length; i++ ) {
				if ( panels[ i ].getAttribute( panelKeyAttr ) === key ) { return panels[ i ]; }
			}
			return null;
		}

		tabs.forEach( function ( tab, i ) {
			var panel = panelFor( tab );
			var tabId = tab.id || idPrefix + '-tab-' + i;
			var panelId = panel ? ( panel.id || idPrefix + '-panel-' + i ) : '';
			tab.id = tabId;
			if ( panel ) {
				panel.id = panelId;
				panel.setAttribute( 'role', 'tabpanel' );
				panel.setAttribute( 'aria-labelledby', tabId );
				tab.setAttribute( 'aria-controls', panelId );
			}
			tab.setAttribute( 'role', 'tab' );
			tab.setAttribute( 'aria-selected', tab.classList.contains( 'is-active' ) ? 'true' : 'false' );
			tab.setAttribute( 'tabindex', tab.classList.contains( 'is-active' ) ? '0' : '-1' );
		} );

		function activate( tab ) {
			tabs.forEach( function ( t ) {
				var active = t === tab;
				t.classList.toggle( 'is-active', active );
				t.setAttribute( 'aria-selected', active ? 'true' : 'false' );
				t.setAttribute( 'tabindex', active ? '0' : '-1' );
			} );
			panels.forEach( function ( p ) {
				p.classList.toggle( 'is-active', p.getAttribute( panelKeyAttr ) === tab.getAttribute( tabKeyAttr ) );
			} );
			tab.focus();
		}

		tabs.forEach( function ( tab, i ) {
			tab.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				activate( tab );
			} );
			tab.addEventListener( 'keydown', function ( e ) {
				var target = null;
				if ( 'ArrowRight' === e.key || 'ArrowDown' === e.key ) {
					target = tabs[ ( i + 1 ) % tabs.length ];
				} else if ( 'ArrowLeft' === e.key || 'ArrowUp' === e.key ) {
					target = tabs[ ( i - 1 + tabs.length ) % tabs.length ];
				} else if ( 'Home' === e.key ) {
					target = tabs[ 0 ];
				} else if ( 'End' === e.key ) {
					target = tabs[ tabs.length - 1 ];
				}
				if ( target ) {
					e.preventDefault();
					activate( target );
				}
			} );
		} );
	}

	// Page tabs (one per catalogue page/form) — already carry static role="tab"/
	// "tablist"/"tabpanel" from Content_Screen, so this only needs to keep the
	// ARIA state honest as it drives single-panel visibility.
	var pageTabs = [].slice.call( root.querySelectorAll( '.clubhouse-pagetab' ) );
	var pagePanels = [].slice.call( root.querySelectorAll( '.clubhouse-pagepanel' ) );
	wireTablist( pageTabs, pagePanels, 'data-tab', 'data-pagepanel', 'clubhouse-page' );

	// Section nav, scoped per page panel (never globally — a section key can
	// repeat across pages, and cross-wiring panels from different forms would
	// silently show/hide the wrong page's content).
	pagePanels.forEach( function ( pagePanel, pageIndex ) {
		var secTabs = [].slice.call( pagePanel.querySelectorAll( '.clubhouse-secnav__item' ) );
		var secPanels = [].slice.call( pagePanel.querySelectorAll( '.clubhouse-panel' ) );
		wireTablist( secTabs, secPanels, 'data-sec', 'data-panel', 'clubhouse-sec-' + pageIndex );
	} );

	// Horizontal drag-scroll on the page tab strip (pointer events cover mouse,
	// touch and pen; native overflow-x scroll remains the fallback either way).
	var strip = root.querySelector( '.clubhouse-pagetabs' );
	if ( strip && window.PointerEvent ) {
		var dragging = false;
		var startX = 0;
		var startScroll = 0;
		var moved = false;
		strip.addEventListener( 'pointerdown', function ( e ) {
			dragging = true;
			moved = false;
			startX = e.clientX;
			startScroll = strip.scrollLeft;
			strip.classList.add( 'is-dragging' );
		} );
		strip.addEventListener( 'pointermove', function ( e ) {
			if ( ! dragging ) { return; }
			var dx = e.clientX - startX;
			if ( Math.abs( dx ) > 3 ) { moved = true; }
			strip.scrollLeft = startScroll - dx;
		} );
		function endDrag() {
			dragging = false;
			strip.classList.remove( 'is-dragging' );
		}
		strip.addEventListener( 'pointerup', endDrag );
		strip.addEventListener( 'pointerleave', endDrag );
		strip.addEventListener( 'pointercancel', endDrag );
		// A drag that actually moved the strip must not also fire the tab's
		// click handler (that would activate whichever tab happened to be
		// under the pointer when the drag ended).
		strip.addEventListener(
			'click',
			function ( e ) {
				if ( moved ) {
					e.preventDefault();
					e.stopPropagation();
				}
			},
			true
		);
	}

	// Per-field media pickers (image fields) via wp.media. Bound individually
	// per .clubhouse-media box — a single shared frame/selector would cross-wire
	// whichever field was clicked most recently into every "Choose image" button.
	[].slice.call( root.querySelectorAll( '.clubhouse-media' ) ).forEach( function ( box ) {
		var field = box.querySelector( 'input[type="hidden"]' );
		var pick = box.querySelector( '[data-media-pick]' );
		var clear = box.querySelector( '[data-media-clear]' );
		var hint = box.querySelector( '.clubhouse-media__hint' );
		if ( ! field || ! pick || ! window.wp || ! window.wp.media ) { return; }

		function setPreview( url ) {
			var preview = box.querySelector( '.clubhouse-media__preview' );
			if ( url ) {
				if ( ! preview ) {
					preview = document.createElement( 'span' );
					preview.className = 'clubhouse-media__preview';
					box.insertBefore( preview, box.firstChild );
				}
				preview.innerHTML = '<img class="clubhouse-media__img" src="' + url + '" alt="">';
			} else if ( preview ) {
				preview.parentNode.removeChild( preview );
			}
		}

		var frame;
		pick.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			if ( ! frame ) {
				frame = window.wp.media( { title: 'Select an image', button: { text: 'Use this image' }, multiple: false } );
				frame.on( 'select', function () {
					var att = frame.state().get( 'selection' ).first().toJSON();
					field.value = String( att.id );
					field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					if ( hint ) { hint.textContent = 'Image set (#' + att.id + ')'; }
					setPreview( att.url );
				} );
			}
			frame.open();
		} );
		if ( clear ) {
			clear.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				field.value = '';
				field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				if ( hint ) { hint.textContent = 'No image set'; }
				setPreview( '' );
			} );
		}
	} );

	// Dirty tracking: reveal "You have unsaved changes." on the first real edit
	// within each page's own form — the hint ships `hidden` so a no-JS or
	// unedited visit never claims changes exist.
	[].slice.call( root.querySelectorAll( '.clubhouse-form' ) ).forEach( function ( form ) {
		var hint = form.querySelector( '.clubhouse-bar__hint' );
		if ( ! hint ) { return; }
		var reveal = function () { hint.removeAttribute( 'hidden' ); };
		form.addEventListener( 'input', reveal );
		form.addEventListener( 'change', reveal );
	} );
}() );
