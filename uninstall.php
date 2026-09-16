<?php
/**
 * Removes plugin data when the plugin is deleted.
 *
 * @package BeariusSEOLight
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'bearius_seo_settings' );

$bearius_seo_meta_keys = array(
	'_bearius_seo_title',
	'_bearius_seo_desc',
	'_bearius_seo_social',
	'_bearius_seo_image',
	'_bearius_seo_noindex',
);

foreach ( $bearius_seo_meta_keys as $bearius_seo_meta_key ) {
	delete_post_meta_by_key( $bearius_seo_meta_key );
}
