<?php
/**
 * Plugin Name:       Bearius SEO Light
 * Description:       SEO for publishers and multilingual sites: meta tags, Schema.org graph, multilingual XML and Google News sitemaps, AI crawler control, IndexNow and a fact based audit with one click fixes.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Bearius
 * Author URI:        https://bearius.org
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bearius-seo-light
 * Domain Path:       /languages
 *
 * @package BeariusSEOLight
 * @author  Bearius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BEARIUS_SEO_VERSION', '1.1.0' );
define( 'BEARIUS_SEO_FILE', __FILE__ );
define( 'BEARIUS_SEO_PATH', plugin_dir_path( __FILE__ ) );
define( 'BEARIUS_SEO_URL', plugin_dir_url( __FILE__ ) );
define( 'BEARIUS_SEO_OPTION', 'bearius_seo_settings' );

require_once BEARIUS_SEO_PATH . 'includes/helpers.php';
require_once BEARIUS_SEO_PATH . 'includes/class-bearius-seo-languages.php';
require_once BEARIUS_SEO_PATH . 'includes/class-bearius-seo-compat.php';
require_once BEARIUS_SEO_PATH . 'includes/class-bearius-seo-metabox.php';
require_once BEARIUS_SEO_PATH . 'includes/class-bearius-seo-terms.php';
require_once BEARIUS_SEO_PATH . 'includes/class-bearius-seo-frontend.php';
require_once BEARIUS_SEO_PATH . 'includes/class-bearius-seo-schema.php';
require_once BEARIUS_SEO_PATH . 'includes/class-bearius-seo-sitemaps.php';
require_once BEARIUS_SEO_PATH . 'includes/class-bearius-seo-robots.php';
require_once BEARIUS_SEO_PATH . 'includes/class-bearius-seo-indexnow.php';
require_once BEARIUS_SEO_PATH . 'includes/class-bearius-seo-breadcrumbs.php';
require_once BEARIUS_SEO_PATH . 'includes/class-bearius-seo-site.php';
require_once BEARIUS_SEO_PATH . 'includes/class-bearius-seo-page-scan.php';
require_once BEARIUS_SEO_PATH . 'includes/class-bearius-seo-health.php';
require_once BEARIUS_SEO_PATH . 'includes/class-bearius-seo-fixes.php';
require_once BEARIUS_SEO_PATH . 'includes/class-bearius-seo-content-audit.php';
require_once BEARIUS_SEO_PATH . 'includes/class-bearius-seo-importer.php';
require_once BEARIUS_SEO_PATH . 'includes/class-bearius-seo-dashboard.php';
require_once BEARIUS_SEO_PATH . 'includes/class-bearius-seo-ui.php';
require_once BEARIUS_SEO_PATH . 'includes/class-bearius-seo-settings.php';
require_once BEARIUS_SEO_PATH . 'includes/class-bearius-seo-admin-tools.php';

/**
 * Boots the plugin once WordPress and all other plugins are loaded.
 *
 * The compatibility check has to run after every plugin is available,
 * otherwise a conflicting SEO plugin loaded later would go unnoticed.
 * Only the modules that would duplicate another plugin are switched off.
 *
 * @return void
 */
function bearius_seo_bootstrap() {
	Bearius_SEO_Compat::instance()->register();

	if ( bearius_seo_module_active( 'meta' ) ) {
		( new Bearius_SEO_Metabox() )->register();
		( new Bearius_SEO_Frontend() )->register();
	}

	if ( bearius_seo_module_active( 'schema' ) ) {
		( new Bearius_SEO_Schema() )->register();
	}

	if ( bearius_seo_module_active( 'terms' ) ) {
		( new Bearius_SEO_Terms() )->register();
	}

	if ( bearius_seo_module_active( 'verification' ) && ! bearius_seo_module_active( 'meta' ) ) {
		// Verification tags are printed by the front end class, so it has to
		// run in a reduced mode when the meta module itself is switched off.
		( new Bearius_SEO_Frontend( true ) )->register();
	}

	( new Bearius_SEO_Sitemaps() )->register();
	( new Bearius_SEO_Robots() )->register();
	( new Bearius_SEO_IndexNow() )->register();

	( new Bearius_SEO_Site() )->register();

	if ( bearius_seo_module_active( 'breadcrumbs' ) ) {
		( new Bearius_SEO_Breadcrumbs() )->register();
	}

	Bearius_SEO_Health::instance()->register();

	if ( bearius_seo_get_setting( 'perf_eager_image' ) ) {
		add_filter( 'wp_get_attachment_image_attributes', 'bearius_seo_eager_featured_image', 20, 2 );
	}

	if ( is_admin() ) {
		( new Bearius_SEO_Settings() )->register();
		( new Bearius_SEO_Admin_Tools() )->register();
		( new Bearius_SEO_Importer() )->register();
		( new Bearius_SEO_Dashboard() )->register();
		( new Bearius_SEO_Fixes() )->register();
	}
}
add_action( 'plugins_loaded', 'bearius_seo_bootstrap' );

/**
 * Loads the bundled translations in the language of the dashboard user.
 *
 * @return void
 */
function bearius_seo_translations() {
	bearius_seo_load_translation( 'bearius-seo-light', BEARIUS_SEO_PATH . 'languages' );
}
add_action( 'init', 'bearius_seo_translations', 1 );

/**
 * Stores default settings on activation without touching an existing configuration.
 *
 * @return void
 */
function bearius_seo_activate() {
	if ( false === get_option( BEARIUS_SEO_OPTION ) ) {
		add_option( BEARIUS_SEO_OPTION, bearius_seo_default_settings() );
	}

	if ( ! wp_next_scheduled( 'bearius_seo_daily_check' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'bearius_seo_daily_check' );
	}

}
register_activation_hook( __FILE__, 'bearius_seo_activate' );

/**
 * Removes scheduled events on deactivation.
 *
 * @return void
 */
function bearius_seo_deactivate() {
	wp_clear_scheduled_hook( 'bearius_seo_daily_check' );
	wp_clear_scheduled_hook( 'bearius_seo_indexnow_flush' );
}
register_deactivation_hook( __FILE__, 'bearius_seo_deactivate' );

/**
 * Runs the upgrade routine after the plugin files were replaced.
 *
 * @return void
 */
function bearius_seo_maybe_upgrade() {
	$stored = get_option( 'bearius_seo_version' );

	if ( BEARIUS_SEO_VERSION === $stored ) {
		return;
	}

	// A network activation runs the activation hook on the main site only.
	if ( false === get_option( BEARIUS_SEO_OPTION ) ) {
		add_option( BEARIUS_SEO_OPTION, bearius_seo_default_settings() );
	}

	if ( ! wp_next_scheduled( 'bearius_seo_daily_check' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'bearius_seo_daily_check' );
	}

	update_option( 'bearius_seo_version', BEARIUS_SEO_VERSION, false );
}
add_action( 'admin_init', 'bearius_seo_maybe_upgrade' );
