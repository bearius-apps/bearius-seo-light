/**
 * Admin behaviour: media pickers, character counters and live previews.
 *
 * Written without a framework dependency so it runs the same in the block
 * editor and the classic editor.
 *
 * @package BeariusSEOLight
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
			thumb.style.backgroundImage = event.target.value ? 'url("' + event.target.value + '")' : '';
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
			if ( image ) {
				out.socialImage.style.backgroundImage = 'url("' + image + '")';
				out.socialImage.classList.remove( 'is-empty' );
				out.socialImage.textContent = '';
			} else {
				out.socialImage.style.backgroundImage = '';
				out.socialImage.classList.add( 'is-empty' );
				out.socialImage.textContent = data.noImage || '';
			}
		}

		updateCounter(
			panel.querySelector( '[data-counter="title"]' ),
			panel.querySelector( '[data-meter="title"]' ),
			( ( fields.title && fields.title.value ) || postTitle ).length,
			60,
			70
		);

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
