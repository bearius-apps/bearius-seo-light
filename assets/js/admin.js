/**
 * Admin behaviour: media pickers, character counters and live previews.
 *
 * Written without a framework dependency so it runs the same in the block
 * editor and the classic editor.
 *
 * @package BeariusSEOLight
 * @author  Bearius
 */

( function () {
	'use strict';

	var l10n = window.beariusSeoL10n || {};

	/**
	 * Opens the media library and writes the chosen URL into a field.
	 *
	 * @param {HTMLElement} field Target input.
	 * @return {void}
	 */
	function openMediaFrame( field ) {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}

		var frame = window.wp.media( {
			title: l10n.frameTitle || 'Select an image',
			button: { text: l10n.frameButton || 'Use this image' },
			library: { type: 'image' },
			multiple: false
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();

			field.value = attachment.url;
			field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		} );

		frame.open();
	}

	/**
	 * Returns the CSS filter that cancels the filters of the element's ancestors.
	 *
	 * Dark modes of the dashboard often invert the whole screen with a CSS
	 * filter and turn images back with a second filter. That second filter
	 * is rarely exact (an invert of 0.88 undone with an invert of 1 leaves
	 * pale, washed out colours), so the previews compute the exact inverse
	 * of everything above them instead. Filters that cannot be undone, such
	 * as blur or sepia, are left alone.
	 *
	 * @param {HTMLElement} node Element.
	 * @return {string} Filter value, empty when nothing above is filtered.
	 */
	function cancelFilter( node ) {
		var layers = [];
		var parts = [];

		for ( var el = node.parentElement; el; el = el.parentElement ) {
			var value = window.getComputedStyle( el ).filter;

			if ( value && 'none' !== value ) {
				layers.unshift( value );
			}
		}

		var amount = function ( arg, fallback ) {
			var n = parseFloat( arg );

			if ( isNaN( n ) ) {
				return fallback;
			}

			return /%$/.test( arg ) ? n / 100 : n;
		};

		var degrees = function ( arg ) {
			var n = parseFloat( arg ) || 0;

			if ( /grad$/.test( arg ) ) {
				return n * 0.9;
			}
			if ( /turn$/.test( arg ) ) {
				return n * 360;
			}
			if ( /rad$/.test( arg ) ) {
				return n * 180 / Math.PI;
			}

			return n;
		};

		var inverse = function ( name, arg ) {
			var a;

			switch ( name ) {
				case 'invert':
					a = Math.min( 1, Math.max( 0, amount( arg, 1 ) ) );
					if ( Math.abs( a - 0.5 ) < 0.02 ) {
						return [];
					}
					// invert(a) maps x to a + (1 - 2a) x; the inverse is an invert plus a contrast.
					return a < 0.5 ? [ 'contrast(' + ( 1 / ( 1 - 2 * a ) ).toFixed( 4 ) + ')' ] : [ 'invert(1)', 'contrast(' + ( 1 / ( 2 * a - 1 ) ).toFixed( 4 ) + ')' ];
				case 'hue-rotate':
					return [ 'hue-rotate(' + ( -degrees( arg ) ).toFixed( 3 ) + 'deg)' ];
				case 'brightness':
				case 'contrast':
				case 'saturate':
					a = amount( arg, 1 );
					return a > 0.01 ? [ name + '(' + ( 1 / a ).toFixed( 4 ) + ')' ] : [];
				case 'grayscale':
					a = Math.min( 1, Math.max( 0, amount( arg, 1 ) ) );
					return a < 0.99 ? [ 'saturate(' + ( 1 / ( 1 - a ) ).toFixed( 4 ) + ')' ] : [];
			}

			return [];
		};

		// The outermost filter is applied last, so it is undone first.
		layers.forEach( function ( value ) {
			var found = [];
			var re = /([a-z-]+)\(([^)]*)\)/g;
			var match;

			while ( ( match = re.exec( value ) ) ) {
				found.push( inverse( match[ 1 ], match[ 2 ].trim() ) );
			}

			found.reverse().forEach( function ( list ) {
				parts = parts.concat( list );
			} );
		} );

		return parts.join( ' ' );
	}

	/**
	 * Keeps the preview images in their true colours under a dark mode.
	 *
	 * @return {void}
	 */
	function trueColor() {
		document.querySelectorAll( 'img[data-bs-true-color]' ).forEach( function ( img ) {
			var value = cancelFilter( img );

			if ( img.bsFilter !== value ) {
				img.bsFilter = value;
				img.style.filter = value;
			}
		} );
	}

	/**
	 * Shows an image address in a preview image, or hides the image when empty.
	 *
	 * Previews are real images rather than CSS backgrounds, so dark modes of
	 * the dashboard that invert the screen and turn images back leave them
	 * in their true colours.
	 *
	 * @param {HTMLElement} holder Element holding the image.
	 * @param {string}      url    Image address.
	 * @return {boolean} Whether an image is shown.
	 */
	function showImage( holder, url ) {
		var img = holder.querySelector( 'img' );

		if ( ! img ) {
			img = document.createElement( 'img' );
			img.alt = '';
			img.setAttribute( 'data-bs-true-color', '' );
			holder.insertBefore( img, holder.firstChild );
			trueColor();
		}

		if ( ! img.bsWatched ) {
			img.bsWatched = true;
			// An address that does not load shows the empty state instead of a broken image.
			img.addEventListener( 'error', function () {
				if ( img.getAttribute( 'src' ) ) {
					img.hidden = true;
					holder.classList.add( 'is-empty' );
				}
			} );
			img.addEventListener( 'load', function () {
				if ( img.getAttribute( 'src' ) ) {
					img.hidden = false;
					holder.classList.remove( 'is-empty' );
				}
			} );
		}

		url = url ? String( url ) : '';

		if ( ! url ) {
			img.removeAttribute( 'src' );
			img.hidden = true;
			holder.classList.add( 'is-empty' );
			return false;
		}

		if ( img.getAttribute( 'src' ) !== url ) {
			img.setAttribute( 'src', url );
		} else if ( img.complete && 0 === img.naturalWidth ) {
			// Failed before this script was running.
			img.hidden = true;
			holder.classList.add( 'is-empty' );
			return false;
		}

		img.hidden = false;
		holder.classList.remove( 'is-empty' );

		return true;
	}

	// Dark modes switch by a class or attribute on the root or the body, or
	// follow the system setting; recheck whenever one of those changes.
	if ( document.querySelector( 'img[data-bs-true-color]' ) ) {
		trueColor();

		if ( window.MutationObserver ) {
			var watcher = new window.MutationObserver( trueColor );

			watcher.observe( document.documentElement, { attributes: true } );

			if ( document.body ) {
				watcher.observe( document.body, { attributes: true } );
			}
		}

		if ( window.matchMedia ) {
			var scheme = window.matchMedia( '(prefers-color-scheme: dark)' );

			if ( scheme.addEventListener ) {
				scheme.addEventListener( 'change', trueColor );
			}
		}

		// Styles of a dark mode can arrive after this script.
		window.addEventListener( 'load', trueColor );
	}

	document.addEventListener( 'click', function ( event ) {
		var pick = event.target.closest( '.bearius-seo-image-pick' );
		var clear = event.target.closest( '.bearius-seo-image-clear' );

		if ( pick ) {
			event.preventDefault();

			var wrap = pick.closest( '.bearius-seo-image-row' ) || pick.parentNode;
			var field = wrap.querySelector( '.bearius-seo-image-field' );

			if ( field ) {
				openMediaFrame( field );
			}

			return;
		}

		if ( clear ) {
			event.preventDefault();

			var clearWrap = clear.closest( '.bearius-seo-image-row' );
			var clearField = clearWrap ? clearWrap.querySelector( '.bearius-seo-image-field' ) : null;

			if ( clearField ) {
				clearField.value = '';
				clearField.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			}
		}
	} );

	// Thumbnail next to any image field.
	document.addEventListener( 'input', function ( event ) {
		if ( ! event.target.classList.contains( 'bearius-seo-image-field' ) ) {
			return;
		}

		var row = event.target.closest( '.bearius-seo-image-row' );
		var thumb = row ? row.querySelector( '.bearius-seo-thumb' ) : null;

		if ( thumb ) {
			showImage( thumb, event.target.value.trim() );
		}
	} );

	var panel = document.getElementById( 'bearius-seo-panel' );

	if ( ! panel ) {
		return;
	}

	var data = window.beariusSeoPreview || {};

	var fields = {
		title: document.getElementById( 'bearius-seo-title' ),
		desc: document.getElementById( 'bearius-seo-desc' ),
		social: document.getElementById( 'bearius-seo-social' ),
		image: document.getElementById( 'bearius-seo-image' )
	};

	var out = {
		serpTitle: panel.querySelector( '.bearius-seo-serp__title' ),
		serpDesc: panel.querySelector( '.bearius-seo-serp__desc' ),
		socialTitle: panel.querySelector( '.bearius-seo-social__title' ),
		socialDesc: panel.querySelector( '.bearius-seo-social__desc' ),
		socialImage: panel.querySelector( '.bearius-seo-social__image' )
	};

	/**
	 * Reads the post title from whichever editor is running.
	 *
	 * @return {string}
	 */
	function editorTitle() {
		if ( window.wp && window.wp.data && window.wp.data.select( 'core/editor' ) ) {
			var value = window.wp.data.select( 'core/editor' ).getEditedPostAttribute( 'title' );

			if ( typeof value === 'string' ) {
				return value;
			}
		}

		var classic = document.getElementById( 'title' );

		return classic ? classic.value : '';
	}

	/**
	 * Shortens a string on a word boundary.
	 *
	 * @param {string} text  Source text.
	 * @param {number} limit Maximum length.
	 * @return {string}
	 */
	function trimOnWord( text, limit ) {
		text = ( text || '' ).trim();

		if ( text.length <= limit ) {
			return text;
		}

		var cut = text.slice( 0, limit - 1 );
		var space = cut.lastIndexOf( ' ' );

		if ( space > limit * 0.6 ) {
			cut = cut.slice( 0, space );
		}

		return cut.replace( /[\s,;:.!?-]+$/, '' ) + '\u2026';
	}

	/**
	 * Updates a counter element and its warning state.
	 *
	 * @param {HTMLElement} node   Counter element.
	 * @param {number}      length Current length.
	 * @param {number}      ideal  Recommended maximum.
	 * @param {number}      hard   Length treated as too long.
	 * @return {void}
	 */
	function updateCounter( node, bar, length, ideal, hard ) {
		if ( node ) {
			node.textContent = length + ' / ' + ideal;
			node.classList.remove( 'is-warning', 'is-over' );

			if ( length > hard ) {
				node.classList.add( 'is-over' );
			} else if ( length > ideal ) {
				node.classList.add( 'is-warning' );
			}
		}

		if ( ! bar ) {
			return;
		}

		bar.style.width = Math.min( 100, ( length / ideal ) * 100 ) + '%';
		bar.classList.remove( 'is-good', 'is-warning', 'is-over' );

		if ( length > hard ) {
			bar.classList.add( 'is-over' );
		} else if ( length > ideal ) {
			bar.classList.add( 'is-warning' );
		} else if ( length > ideal * 0.5 ) {
			bar.classList.add( 'is-good' );
		}
	}

	/**
	 * Redraws both previews from the current field values.
	 *
	 * @return {void}
	 */
	/**
	 * Updates the live checks and the summary of the checks panel.
	 *
	 * @param {string} title Effective SEO title.
	 * @param {string} desc  Effective description.
	 */
	function updateChecks( title, desc ) {
		var box = panel.querySelector( '[data-bs-checks]' );
		var texts = data.checks || {};

		if ( ! box ) {
			return;
		}

		var set = function ( key, state, text ) {
			var row = box.querySelector( '[data-check="' + key + '"]' );

			if ( row ) {
				row.className = 'is-' + state;
				row.querySelector( '[data-check-text]' ).textContent = text;
			}
		};

		var format = function ( template, value ) {
			return String( template || '' ).replace( '%d', value );
		};

		var titleLength = title.length;
		if ( titleLength > 60 ) {
			set( 'title', 'warn', format( texts.titleLong, titleLength ) );
		} else if ( titleLength < 25 ) {
			set( 'title', 'warn', format( texts.titleShort, titleLength ) );
		} else {
			set( 'title', 'ok', format( texts.titleOk, titleLength ) );
		}

		var descLength = desc.length;
		if ( ! descLength ) {
			set( 'desc', 'warn', texts.descNone || '' );
		} else if ( descLength > 160 ) {
			set( 'desc', 'warn', format( texts.descLong, descLength ) );
		} else if ( descLength < 110 ) {
			set( 'desc', 'warn', format( texts.descShort, descLength ) );
		} else {
			set( 'desc', 'ok', format( texts.descOk, descLength ) );
		}

		var noindex = panel.querySelector( 'input[name="bearius_seo_noindex"]' );
		set( 'index', noindex && noindex.checked ? 'warn' : 'ok', noindex && noindex.checked ? texts.indexNo : texts.indexOk );

		var rows = box.querySelectorAll( '[data-check]' );
		var counted = 0;
		var fine = 0;

		rows.forEach( function ( row ) {
			if ( row.classList.contains( 'is-info' ) ) {
				return;
			}
			counted++;
			if ( row.classList.contains( 'is-ok' ) ) {
				fine++;
			}
		} );

		var score = box.querySelector( '[data-bs-checks-score]' );
		if ( score ) {
			score.textContent = String( texts.score || '%1$d / %2$d' ).replace( '%1$d', fine ).replace( '%2$d', counted );
			score.className = 'bearius-seo-checks__score' + ( fine === counted ? ' is-ok' : '' );
		}
	}

	function refresh() {
		var postTitle = editorTitle() || data.fallbackTitle || '';
		var seoTitle = ( fields.title && fields.title.value ) || postTitle;
		var desc = ( fields.desc && fields.desc.value ) || data.fallbackDesc || '';
		var social = ( fields.social && fields.social.value ) || seoTitle;
		var image = ( fields.image && fields.image.value ) || data.fallbackImage || '';

		if ( out.serpTitle ) {
			out.serpTitle.textContent = trimOnWord( seoTitle, 60 ) || data.untitled || '';
		}

		if ( out.serpDesc ) {
			out.serpDesc.textContent = trimOnWord( desc, 160 ) || data.noDescription || '';
		}

		if ( out.socialTitle ) {
			out.socialTitle.textContent = trimOnWord( social, 70 ) || data.untitled || '';
		}

		if ( out.socialDesc ) {
			out.socialDesc.textContent = trimOnWord( desc, 120 );
		}

		if ( fields.title && data.fallbackTitle !== undefined ) {
			fields.title.placeholder = postTitle;
		}

		if ( fields.social ) {
			fields.social.placeholder = seoTitle;
		}

		if ( out.socialImage ) {
			showImage( out.socialImage, image.trim() );
		}

		updateCounter(
			panel.querySelector( '[data-counter="title"]' ),
			panel.querySelector( '[data-meter="title"]' ),
			( ( fields.title && fields.title.value ) || postTitle ).length,
			60,
			70
		);

		updateChecks( ( fields.title && fields.title.value ) || postTitle, desc );

		updateCounter(
			panel.querySelector( '[data-counter="desc"]' ),
			panel.querySelector( '[data-meter="desc"]' ),
			desc.length,
			155,
			160
		);
	}

	Object.keys( fields ).forEach( function ( key ) {
		if ( fields[ key ] ) {
			fields[ key ].addEventListener( 'input', refresh );
		}
	} );

	var noindexBox = panel.querySelector( 'input[name="bearius_seo_noindex"]' );
	if ( noindexBox ) {
		noindexBox.addEventListener( 'change', refresh );
	}

	// The post title lives outside this box, so it is polled rather than bound.
	var lastTitle = editorTitle();

	setInterval( function () {
		var current = editorTitle();

		if ( current !== lastTitle ) {
			lastTitle = current;
			refresh();
		}
	}, 700 );

	refresh();
} )();
