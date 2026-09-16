<?php
/**
 * Plugin Name:       Bearius SEO Light
 * Description:       Lightweight SEO meta tags and Schema.org structured data. No bloat, no upsells. Built for news sites and small publishers.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Bearius
 * Author URI:        https://bearius.org
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bearius-seo-light
 *
 * @package BeariusSEOLight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BEARIUS_SEO_VERSION', '1.0.0' );
define( 'BEARIUS_SEO_FILE', __FILE__ );
define( 'BEARIUS_SEO_PATH', plugin_dir_path( __FILE__ ) );
define( 'BEARIUS_SEO_URL', plugin_dir_url( __FILE__ ) );
define( 'BEARIUS_SEO_OPTION', 'bearius_seo_settings' );

require_once BEARIUS_SEO_PATH . 'includes/helpers.php';
require_once BEARIUS_SEO_PATH . 'includes/class-bearius-seo-compat.php';
require_once BEARIUS_SEO_PATH . 'includes/class-bearius-seo-settings.php';
require_once BEARIUS_SEO_PATH . 'includes/class-bearius-seo-metabox.php';
require_once BEARIUS_SEO_PATH . 'includes/class-bearius-seo-frontend.php';
require_once BEARIUS_SEO_PATH . 'includes/class-bearius-seo-schema.php';

/**
 * Boots the plugin once WordPress and all other plugins are loaded.
 *
 * The compatibility check has to run after every plugin is available,
 * otherwise a conflicting SEO plugin loaded later would go unnoticed.
 *
 * @return void
 */
function bearius_seo_bootstrap() {
	$compat = new Bearius_SEO_Compat();
	$compat->register();

	$settings = new Bearius_SEO_Settings();
	$settings->register();

	if ( $compat->has_conflict() ) {
		return;
	}

	$metabox = new Bearius_SEO_Metabox();
	$metabox->register();

	$frontend = new Bearius_SEO_Frontend();
	$frontend->register();

	$schema = new Bearius_SEO_Schema();
	$schema->register();
}
add_action( 'plugins_loaded', 'bearius_seo_bootstrap' );

/**
 * Stores default settings on activation without touching an existing configuration.
 *
 * @return void
 */
function bearius_seo_activate() {
	if ( false === get_option( BEARIUS_SEO_OPTION ) ) {
		add_option( BEARIUS_SEO_OPTION, bearius_seo_default_settings() );
	}
}
register_activation_hook( __FILE__, 'bearius_seo_activate' );
