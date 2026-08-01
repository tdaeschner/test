<?php
/**
 * Plugin Name:       Norwegen Reise
 * Plugin URI:        https://github.com/tdaeschner/norwegen-blog
 * Description:       Reisetagebuch-Datenmodell für den Norwegen-Travelblog: Reisetage mit GPX-Track, Highlight-Fähnchen, Karten-Daten und REST-Schnittstelle für die scrollbare Route.
 * Version:           1.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Tobias Däschner
 * License:           GPL-2.0-or-later
 * Text Domain:       norwegen-reise
 *
 * @package NorwegenReise
 */

defined( 'ABSPATH' ) || exit;

define( 'NB_VERSION', '1.1.0' );
define( 'NB_FILE', __FILE__ );
define( 'NB_PATH', plugin_dir_path( __FILE__ ) );
define( 'NB_URL', plugin_dir_url( __FILE__ ) );

require_once NB_PATH . 'includes/class-nb-post-types.php';
require_once NB_PATH . 'includes/class-nb-geo.php';
require_once NB_PATH . 'includes/class-nb-gpx.php';
require_once NB_PATH . 'includes/class-nb-trip.php';
require_once NB_PATH . 'includes/class-nb-meta.php';
require_once NB_PATH . 'includes/class-nb-settings.php';
require_once NB_PATH . 'includes/class-nb-rest.php';

/**
 * Bootstraps every part of the plugin.
 */
function nb_bootstrap() {
	NB_Post_Types::init();
	NB_Meta::init();
	NB_Settings::init();
	NB_REST::init();

	add_action( 'wp_enqueue_scripts', 'nb_register_shared_assets', 1 );
	add_action( 'admin_enqueue_scripts', 'nb_register_shared_assets', 1 );
}
add_action( 'plugins_loaded', 'nb_bootstrap' );

/**
 * Registers MapLibre GL once, so both the admin picker and the theme can depend
 * on the same local copy. Nothing is loaded from a CDN.
 */
function nb_register_shared_assets() {
	wp_register_script(
		'maplibre-gl',
		NB_URL . 'assets/vendor/maplibre-gl/maplibre-gl.js',
		array(),
		'4.7.1',
		true
	);

	wp_register_style(
		'maplibre-gl',
		NB_URL . 'assets/vendor/maplibre-gl/maplibre-gl.css',
		array(),
		'4.7.1'
	);

	wp_register_script(
		'nb-map-style',
		NB_URL . 'assets/map-style.js',
		array( 'maplibre-gl' ),
		NB_VERSION,
		true
	);

	// Bedienelemente der Karte, im Frontend wie im Backend gleich.
	wp_register_style(
		'nb-map',
		NB_URL . 'assets/map.css',
		array( 'maplibre-gl' ),
		NB_VERSION
	);
}

/**
 * Labels for the base map switcher, shared by every map on the site.
 *
 * @return array
 */
function nb_map_labels() {
	return array(
		'title' => __( 'Kartenstil', 'norwegen-reise' ),
	);
}

/**
 * Flushes rewrite rules on activation so /reisetag/... works right away.
 */
function nb_activate() {
	require_once NB_PATH . 'includes/class-nb-post-types.php';
	NB_Post_Types::register();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'nb_activate' );

/**
 * Cleans up rewrite rules on deactivation.
 */
function nb_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'nb_deactivate' );
