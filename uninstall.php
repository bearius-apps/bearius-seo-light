<?php
/**
 * Removes plugin data when the plugin is deleted.
 *
 * @package BeariusSEOLight
 * @author  Bearius
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

foreach ( array(
	'bearius_seo_settings',
	'bearius_seo_version',
	'bearius_seo_health',
	'bearius_seo_notified',
	'bearius_seo_mail_failed',
	'bearius_seo_sm_ver',
	'bearius_seo_indexnow_queue',
	'bearius_seo_indexnow_log',
	'bearius_seo_checklist',
) as $bearius_seo_option ) {
	delete_option( $bearius_seo_option );
}

// Cached sitemap page maps and content audit counts.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( 'bearius_seo_smp_' ) . '%', $wpdb->esc_like( '_transient_bearius_seo_' ) . '%', $wpdb->esc_like( '_transient_timeout_bearius_seo_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

foreach ( array(
	'_bearius_seo_title',
	'_bearius_seo_desc',
	'_bearius_seo_social',
	'_bearius_seo_image',
	'_bearius_seo_noindex',
	'_bearius_seo_news_exclude',
) as $bearius_seo_meta_key ) {
	delete_post_meta_by_key( $bearius_seo_meta_key );
}

foreach ( array( '_bearius_seo_title', '_bearius_seo_desc', '_bearius_seo_noindex' ) as $bearius_seo_meta_key ) {
	$wpdb->delete( $wpdb->termmeta, array( 'meta_key' => $bearius_seo_meta_key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
}

delete_metadata( 'user', 0, 'bearius_seo_dismissed', '', true );
delete_metadata( 'user', 0, 'bearius_seo_ui_locale', '', true );

wp_clear_scheduled_hook( 'bearius_seo_daily_check' );
wp_clear_scheduled_hook( 'bearius_seo_indexnow_flush' );
