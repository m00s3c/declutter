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

	var resetOrder = document.getElementById( 'declutter-reset-order' );
	if ( resetOrder ) {
		resetOrder.addEventListener( 'submit', function ( e ) {
			var message = ( window.declutterL10n && window.declutterL10n.confirmResetOrder ) || 'Reset the sidebar to the default order?';
			if ( ! window.confirm( message ) ) {
				e.preventDefault();
			}
		} );
	}

	var orderList = document.querySelector( '.declutter-order' );
	if ( orderList ) {
		initMenuOrder( orderList );
	}

	function l10n( key, fallback ) {
		return ( window.declutterL10n && window.declutterL10n[ key ] ) || fallback;
	}

	function announce( text ) {
		var live = document.getElementById( 'declutter-order-live' );
		if ( ! live ) {
			return;
		}
		live.textContent = '';
		window.setTimeout( function () {
			live.textContent = text;
		}, 30 );
	}

	function orderItems( list ) {
		return Array.prototype.filter.call( list.children, function ( el ) {
			return el.classList && el.classList.contains( 'declutter-order-item' );
		} );
	}

	function refreshOrder( list ) {
		var items = orderItems( list );
		items.forEach( function ( item, index ) {
			var up = item.querySelector( '.declutter-move-up' );
			var down = item.querySelector( '.declutter-move-down' );
			if ( up ) {
				up.disabled = index === 0;
			}
			if ( down ) {
				down.disabled = index === items.length - 1;
			}
		} );
		if ( window.jQuery ) {
			var $list = window.jQuery( list );
			if ( $list.data( 'ui-sortable' ) ) {
				$list.sortable( 'refresh' );
			}
		}
	}

	function moveItem( item, dir ) {
		var list = item.parentNode;
		var items = orderItems( list );
		var index = items.indexOf( item );
		var next = index + dir;
		if ( index < 0 || next < 0 || next >= items.length ) {
			return;
		}
		if ( dir < 0 ) {
			list.insertBefore( item, items[ next ] );
		} else {
			list.insertBefore( item, items[ next ].nextSibling );
		}
		refreshOrder( list );
		var label = item.getAttribute( 'data-label' ) || item.querySelector( '.declutter-order-title' ).textContent;
		var template = l10n( 'announceMoved', '%1$s moved %2$s. Position %3$s of %4$s.' );
		var direction = dir < 0 ? l10n( 'directionUp', 'up' ) : l10n( 'directionDown', 'down' );
		announce( template.replace( '%1$s', label ).replace( '%2$s', direction ).replace( '%3$s', String( next + 1 ) ).replace( '%4$s', String( items.length ) ) );
	}

	function cssEscape( value ) {
		if ( window.CSS && window.CSS.escape ) {
			return window.CSS.escape( value );
		}
		return String( value ).replace( /["\\]/g, '\\$&' );
	}

	function syncHiddenBadge( row ) {
		var box = row.closest( '.declutter-section' );
		var input = row.querySelector( 'input[type="hidden"][name$="[id]"]' );
		var checkbox = row.querySelector( '.declutter-hide' );
		if ( ! box || ! input || ! checkbox ) {
			return;
		}
		var item = box.querySelector( '.declutter-order-item[data-id="' + cssEscape( input.value ) + '"]' );
		if ( ! item ) {
			return;
		}
		item.classList.toggle( 'is-hidden', checkbox.checked );
		var badge = item.querySelector( '.declutter-badge' );
		if ( checkbox.checked ) {
			if ( ! badge ) {
				badge = document.createElement( 'span' );
				badge.className = 'declutter-badge';
				badge.textContent = l10n( 'hiddenBadge', 'Hidden' );
				var label = item.querySelector( '.declutter-order-label' );
				if ( label ) {
					label.appendChild( badge );
				}
			}
		} else if ( badge ) {
			badge.parentNode.removeChild( badge );
		}
	}

	function initMenuOrder( list ) {
		refreshOrder( list );

		list.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.declutter-move' );
			if ( ! btn || btn.disabled || ! list.contains( btn ) ) {
				return;
			}
			var item = btn.closest( '.declutter-order-item' );
			if ( ! item ) {
				return;
			}
			var dir = btn.classList.contains( 'declutter-move-up' ) ? -1 : 1;
			var active = document.activeElement;
			moveItem( item, dir );
			if ( active && item.contains( active ) ) {
				if ( active.disabled ) {
					var other = item.querySelector( dir < 0 ? '.declutter-move-down' : '.declutter-move-up' );
					( other && ! other.disabled ? other : item.querySelector( '.declutter-handle' ) ).focus();
				} else {
					active.focus();
				}
			}
		} );

		list.addEventListener( 'keydown', function ( e ) {
			if ( e.key !== 'ArrowUp' && e.key !== 'ArrowDown' ) {
				return;
			}
			var handle = e.target.closest( '.declutter-handle' );
			if ( ! handle || ! list.contains( handle ) ) {
				return;
			}
			var item = handle.closest( '.declutter-order-item' );
			if ( ! item ) {
				return;
			}
			e.preventDefault();
			moveItem( item, e.key === 'ArrowUp' ? -1 : 1 );
			handle.focus();
		} );

		var section = list.closest( '.declutter-section' );
		if ( section ) {
			section.addEventListener( 'change', function ( e ) {
				var row = e.target.closest( '.declutter-row' );
				if ( row && e.target.classList.contains( 'declutter-hide' ) ) {
					syncHiddenBadge( row );
				}
			} );
		}

		if ( window.jQuery && window.jQuery.fn.sortable ) {
			var wrap = document.querySelector( '.declutter-wrap' );
			window.jQuery( list ).sortable( {
				handle: '.declutter-handle',
				axis: 'y',
				items: '> .declutter-order-item',
				placeholder: 'declutter-order-placeholder',
				forcePlaceholderSize: true,
				tolerance: 'pointer',
				cancel: '.declutter-move, input, a',
				start: function ( e, ui ) {
					if ( wrap ) {
						wrap.classList.add( 'is-sorting' );
					}
					ui.placeholder.height( ui.item.outerHeight() );
					ui.helper.width( ui.item.outerWidth() );
				},
				stop: function () {
					if ( wrap ) {
						wrap.classList.remove( 'is-sorting' );
					}
					refreshOrder( list );
				},
				update: function () {
					announce( l10n( 'orderChanged', 'Menu order changed. Save to keep it.' ) );
				}
			} );
		}
	}

	document.querySelectorAll( '.declutter-all' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var box = btn.closest( '.declutter-section' );
			if ( ! box ) {
				return;
			}
			box.querySelectorAll( '.declutter-row' ).forEach( function ( row ) {
				if ( row.style.display !== 'none' ) {
					syncHiddenBadge( row );
				}
			} );
		} );
	} );
}() );
