/**
 * Breadcrumbs block for the editor.
 *
 * Shows a static placeholder; the real trail is rendered on the front end.
 *
 * @package BeariusSEOLight
 * @author  Bearius
 */

( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.element ) {
		return;
	}

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var useBlockProps = wp.blockEditor && wp.blockEditor.useBlockProps ? wp.blockEditor.useBlockProps : function ( props ) {
		return props || {};
	};

	wp.blocks.registerBlockType( 'bearius-seo/breadcrumbs', {
		apiVersion: 2,
		title: __( 'Breadcrumbs', 'bearius-seo-light' ),
		description: __( 'Shows the path to the current page. Uses the same trail as the structured data.', 'bearius-seo-light' ),
		category: 'theme',
		icon: 'arrow-right-alt2',
		keywords: [ 'breadcrumbs', 'navigation', 'seo' ],
		supports: {
			align: [ 'wide', 'full' ],
			typography: { fontSize: true },
			color: { text: true, link: true, background: false },
			spacing: { margin: true }
		},
		edit: function () {
			var props = useBlockProps( { className: 'bearius-breadcrumbs' } );

			return el(
				'nav',
				props,
				el(
					'ol',
					{ style: { display: 'flex', gap: '.35em', margin: 0, padding: 0, listStyle: 'none', flexWrap: 'wrap' } },
					el( 'li', null, el( 'a', { href: '#' }, __( 'Home', 'bearius-seo-light' ) ) ),
					el( 'li', null, '/ ', el( 'a', { href: '#' }, __( 'Category', 'bearius-seo-light' ) ) ),
					el( 'li', { style: { opacity: 0.75 } }, '/ ', __( 'Current page', 'bearius-seo-light' ) )
				)
			);
		},
		save: function () {
			return null;
		}
	} );
}( window.wp ) );
