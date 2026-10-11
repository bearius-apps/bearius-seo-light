/**
 * Settings screen behaviour: unsaved changes, busy buttons, printing,
 * the score ring entrance and the batched importer.
 *
 * @package BeariusSEOLight
 * @author  Bearius
 */

( function () {
	'use strict';

	var config = window.beariusSeoSettings || {};
	var i18n = config.i18n || {};
	var reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	/* Unsaved changes ---------------------------------------------------- */

	var dirty = false;
	var submitting = false;

	document.querySelectorAll( '[data-bs-form]' ).forEach( function ( form ) {
		var bar = form.querySelector( '.bs-savebar' );
		var state = form.querySelector( '[data-bs-state]' );

		function markDirty() {
			if ( dirty || ! bar ) {
				return;
			}

			dirty = true;
			bar.classList.add( 'is-dirty' );

			if ( state && state.getAttribute( 'data-dirty' ) ) {
				state.textContent = state.getAttribute( 'data-dirty' );
			}
		}

		form.addEventListener( 'input', markDirty );
		form.addEventListener( 'change', markDirty );
		form.addEventListener( 'submit', function () {
			submitting = true;
		} );
	} );

	window.addEventListener( 'beforeunload', function ( event ) {
		if ( dirty && ! submitting ) {
			event.preventDefault();
			event.returnValue = i18n.confirm || '';
		}
	} );

	/* Busy buttons and printing ----------------------------------------- */

	document.addEventListener( 'click', function ( event ) {
		var busy = event.target.closest( '[data-bs-busy]' );
		var print = event.target.closest( '[data-bs-print]' );
		var confirmLink = event.target.closest( '[data-bs-confirm]' );

		if ( confirmLink && ! window.confirm( confirmLink.getAttribute( 'data-bs-confirm' ) ) ) {
			event.preventDefault();
			return;
		}

		if ( busy ) {
			var label = busy.querySelector( 'span' );
			busy.classList.add( 'is-busy' );

			if ( label ) {
				label.textContent = busy.getAttribute( 'data-bs-busy' );
			}
		}

		if ( print ) {
			event.preventDefault();
			window.print();
		}
	} );

	/* Score ring entrance ------------------------------------------------ */

	if ( ! reduceMotion ) {
		document.querySelectorAll( '.bs-hero .bs-ring__value' ).forEach( function ( ring ) {
			var target = ring.getAttribute( 'stroke-dashoffset' );
			var full = ring.getAttribute( 'stroke-dasharray' );

			ring.style.transition = 'none';
			ring.setAttribute( 'stroke-dashoffset', full );

			window.requestAnimationFrame( function () {
				window.requestAnimationFrame( function () {
					ring.style.transition = '';
					ring.setAttribute( 'stroke-dashoffset', target );
				} );
			} );
		} );
	}

	/* AI crawler presets ------------------------------------------------- */

	var presets = document.querySelector( '[data-bs-presets]' );

	if ( presets ) {
		var form = presets.closest( 'form' );
		var switches = form ? form.querySelectorAll( 'input[data-purpose]' ) : [];

		presets.addEventListener( 'change', function ( event ) {
			var value = event.target.value;

			if ( 'custom' === value ) {
				return;
			}

			switches.forEach( function ( input ) {
				var purpose = input.getAttribute( 'data-purpose' );
				input.checked = 'none' === value || ( 'training' === value && 'training' !== purpose );
			} );
		} );

		switches.forEach( function ( input ) {
			input.addEventListener( 'change', function () {
				var custom = presets.querySelector( 'input[value="custom"]' );

				if ( custom ) {
					custom.checked = true;
				}
			} );
		} );
	}

	/* Importer ----------------------------------------------------------- */

	var importer = document.querySelector( '[data-bs-import]' );

	if ( ! importer ) {
		return;
	}

	var start = importer.querySelector( '[data-bs-import-start]' );
	var progress = importer.querySelector( '.bs-progress' );
	var bar = progress ? progress.querySelector( 'span' ) : null;
	var status = importer.querySelector( '.bs-import__status' );
	var result = importer.querySelector( '.bs-import__result' );

	function post( data ) {
		var body = new window.FormData();

		Object.keys( data ).forEach( function ( key ) {
			body.append( key, data[ key ] );
		} );

		return window.fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	function showTotals( totals, processed ) {
		var parts = [ processed + ' ' + ( i18n.processed || '' ) ];

		[ 'title', 'desc', 'noindex', 'social', 'image' ].forEach( function ( key ) {
			if ( totals[ key ] ) {
				parts.push( totals[ key ] + ' ' + ( i18n[ key ] || key ) );
			}
		} );

		result.innerHTML = '';
		parts.forEach( function ( text ) {
			var li = document.createElement( 'li' );
			li.textContent = text;
			result.appendChild( li );
		} );
	}

	start.addEventListener( 'click', function () {
		var choice = importer.querySelector( 'input[name="bs_import_source"]:checked' );
		var overwrite = importer.querySelector( 'input[name="bs_import_overwrite"]' );

		if ( ! choice ) {
			return;
		}

		var source = choice.value;
		var total = parseInt( choice.getAttribute( 'data-total' ), 10 ) || 1;
		var totals = { title: 0, desc: 0, noindex: 0, social: 0, image: 0 };
		var processed = 0;

		start.disabled = true;
		progress.hidden = false;
		bar.style.width = '2%';
		status.textContent = i18n.importing || '';

		function add( counts ) {
			Object.keys( counts || {} ).forEach( function ( key ) {
				totals[ key ] += parseInt( counts[ key ], 10 ) || 0;
			} );
		}

		function finish( ok ) {
			start.disabled = false;
			status.textContent = ok ? i18n.done : i18n.failed;
			bar.style.width = ok ? '100%' : bar.style.width;
			showTotals( totals, processed );
		}

		function terms() {
			status.textContent = i18n.terms || '';

			post( {
				action: 'bearius_seo_import',
				nonce: config.importNonce,
				source: source,
				stage: 'terms',
				overwrite: overwrite && overwrite.checked ? 1 : 0
			} ).then( function ( response ) {
				if ( ! response || ! response.success ) {
					finish( false );
					return;
				}

				add( response.data.counts );
				finish( true );
			} ).catch( function () {
				finish( false );
			} );
		}

		function step( lastId ) {
			post( {
				action: 'bearius_seo_import',
				nonce: config.importNonce,
				source: source,
				stage: 'posts',
				last_id: lastId,
				overwrite: overwrite && overwrite.checked ? 1 : 0
			} ).then( function ( response ) {
				if ( ! response || ! response.success ) {
					finish( false );
					return;
				}

				processed += response.data.processed;
				add( response.data.counts );
				bar.style.width = Math.min( 96, Math.max( 2, Math.round( 100 * processed / total ) ) ) + '%';
				showTotals( totals, processed );

				if ( response.data.done ) {
					if ( 'aioseo' === source ) {
						finish( true );
					} else {
						terms();
					}
				} else {
					step( response.data.last_id );
				}
			} ).catch( function () {
				finish( false );
			} );
		}

		step( 0 );
	} );
}() );
