/* Declutter settings screen. No dependencies. */
( function () {
	'use strict';

	var STORAGE_KEY = 'declutter-sections';

	function getState() {
		try {
			var raw = localStorage.getItem( STORAGE_KEY );
			return raw ? JSON.parse( raw ) : {};
		} catch ( e ) {
			return {};
		}
	}

	function saveState() {
		var state = {};
		document.querySelectorAll( '.declutter-wrap .declutter-section[data-section]' ).forEach( function ( box ) {
			state[ box.getAttribute( 'data-section' ) ] = box.classList.contains( 'is-open' );
		} );
		try {
			localStorage.setItem( STORAGE_KEY, JSON.stringify( state ) );
		} catch ( e ) {}
	}

	function setOpen( box, open ) {
		box.classList.toggle( 'is-open', open );
		var btn = box.querySelector( '.declutter-toggle' );
		if ( btn ) {
			btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}
	}

	document.querySelectorAll( '.declutter-section[data-section]' ).forEach( function ( box ) {
		var enable = box.querySelector( '.declutter-enable' );
		if ( enable ) {
			enable.addEventListener( 'change', function () {
				box.classList.toggle( 'is-off', ! enable.checked );
			} );
		}

		var roles = box.querySelector( '.declutter-roles' );
		box.querySelectorAll( '.declutter-scope' ).forEach( function ( radio ) {
			radio.addEventListener( 'change', function () {
				if ( roles ) {
					roles.classList.toggle( 'is-hidden', radio.value !== 'roles' || ! radio.checked );
				}
			} );
		} );

		var filter = box.querySelector( '.declutter-filter' );
		if ( filter ) {
			filter.addEventListener( 'input', function () {
				var q = filter.value.toLowerCase().trim();
				box.querySelectorAll( '.declutter-row' ).forEach( function ( row ) {
					row.style.display = ! q || row.textContent.toLowerCase().indexOf( q ) !== -1 ? '' : 'none';
				} );
			} );
		}

		box.querySelectorAll( '.declutter-all' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var hide = btn.getAttribute( 'data-hide' ) === '1';
				box.querySelectorAll( '.declutter-row' ).forEach( function ( row ) {
					var cb = row.querySelector( '.declutter-hide' );
					if ( cb && row.style.display !== 'none' ) {
						cb.checked = hide;
					}
				} );
			} );
		} );

		var head = box.querySelector( '.declutter-head' );
		if ( head ) {
			head.addEventListener( 'click', function ( e ) {
				if ( e.target.closest( '.declutter-switch' ) ) {
					return;
				}
				setOpen( box, ! box.classList.contains( 'is-open' ) );
				saveState();
			} );
		}
	} );

	var expandAll = document.querySelector( '.declutter-expand-all' );
	if ( expandAll ) {
		expandAll.addEventListener( 'click', function () {
			document.querySelectorAll( '.declutter-wrap .declutter-section[data-section]' ).forEach( function ( box ) {
				setOpen( box, true );
			} );
			saveState();
		} );
	}

	var collapseAll = document.querySelector( '.declutter-collapse-all' );
	if ( collapseAll ) {
		collapseAll.addEventListener( 'click', function () {
			document.querySelectorAll( '.declutter-wrap .declutter-section[data-section]' ).forEach( function ( box ) {
				setOpen( box, false );
			} );
			saveState();
		} );
	}

	var reset = document.querySelector( '.declutter-reset-form' );
	if ( reset ) {
		reset.addEventListener( 'submit', function ( e ) {
			if ( ! window.confirm( ( window.declutterL10n && window.declutterL10n.confirmReset ) || 'Reset?' ) ) {
				e.preventDefault();
			}
		} );
	}
}() );
