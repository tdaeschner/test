<?php
/**
 * Nordlys — theme setup, assets and helpers.
 *
 * @package Nordlys
 */

defined( 'ABSPATH' ) || exit;

define( 'NORDLYS_VERSION', '1.1.0' );

require_once get_template_directory() . '/inc/template-tags.php';

/**
 * Theme supports and registrations.
 */
function nordlys_setup() {
	load_theme_textdomain( 'nordlys', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'custom-logo', array( 'height' => 48, 'width' => 220, 'flex-width' => true, 'flex-height' => true ) );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'align-wide' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/editor.css' );

	add_image_size( 'nordlys-hero', 2000, 1120, true );
	add_image_size( 'nordlys-card', 960, 640, true );

	register_nav_menus(
		array(
			'primary' => __( 'Hauptmenü', 'nordlys' ),
			'footer'  => __( 'Fußzeile', 'nordlys' ),
		)
	);
}
add_action( 'after_setup_theme', 'nordlys_setup' );

/**
 * Content width used by embeds.
 */
function nordlys_content_width() {
	$GLOBALS['content_width'] = 820;
}
add_action( 'after_setup_theme', 'nordlys_content_width', 0 );

/**
 * Front-end assets.
 */
function nordlys_assets() {
	wp_enqueue_style( 'nordlys', get_stylesheet_uri(), array(), NORDLYS_VERSION );

	wp_enqueue_script( 'nordlys-site', get_template_directory_uri() . '/assets/js/site.js', array(), NORDLYS_VERSION, true );

	if ( ! nordlys_plugin_active() ) {
		return;
	}

	$needs_map = is_front_page() || is_singular( 'nb_day' ) || is_post_type_archive( 'nb_day' );

	if ( ! $needs_map ) {
		return;
	}

	wp_enqueue_style( 'maplibre-gl' );
	wp_enqueue_style( 'nb-map' );
	wp_enqueue_script( 'maplibre-gl' );
	wp_enqueue_script( 'nb-map-style' );

	if ( is_singular( 'nb_day' ) ) {
		wp_enqueue_script( 'nordlys-lightbox', get_template_directory_uri() . '/assets/js/lightbox.js', array(), NORDLYS_VERSION, true );

		wp_enqueue_script( 'nordlys-day-map', get_template_directory_uri() . '/assets/js/day-map.js', array( 'maplibre-gl', 'nb-map-style' ), NORDLYS_VERSION, true );

		wp_localize_script( 'nordlys-day-map', 'nordlysDay', nordlys_day_map_data( get_the_ID() ) );

		return;
	}

	wp_enqueue_script( 'nordlys-trip-map', get_template_directory_uri() . '/assets/js/trip-map.js', array( 'maplibre-gl', 'nb-map-style' ), NORDLYS_VERSION, true );

	wp_localize_script( 'nordlys-trip-map', 'nordlysTrip', nordlys_trip_data() );
}
add_action( 'wp_enqueue_scripts', 'nordlys_assets' );

/**
 * Whether the companion plugin is available.
 *
 * @return bool
 */
function nordlys_plugin_active() {
	return class_exists( 'NB_Trip' );
}

/**
 * Trip payload for the big map, including the labels the JavaScript prints.
 *
 * @return array
 */
function nordlys_trip_data() {
	$data = NB_Trip::get_data();

	$data['i18n'] = array(
		'follow'    => __( 'Route folgen', 'nordlys' ),
		'following' => __( 'Kamera folgt', 'nordlys' ),
		'free'      => __( 'Freie Sicht', 'nordlys' ),
		'readMore'  => __( 'Zum Tag', 'nordlys' ),
		'day'       => __( 'Tag', 'nordlys' ),
		'of'        => __( 'von', 'nordlys' ),
		'km'        => __( 'km', 'nordlys' ),
		'start'     => __( 'Start', 'nordlys' ),
		'here'      => __( 'Bis hierher', 'nordlys' ),
		'reset'     => __( 'Gesamte Reise zeigen', 'nordlys' ),
		'basemap'   => __( 'Kartenstil', 'nordlys' ),
	);

	$data['reduceMotion'] = false;

	return $data;
}

/**
 * Map payload for a single day.
 *
 * @param int $post_id Day post ID.
 * @return array
 */
function nordlys_day_map_data( $post_id ) {
	$track = NB_Meta::get_track( $post_id );
	$pins  = array();

	foreach ( NB_Meta::get_pins( $post_id ) as $pin ) {
		$pins[] = array(
			'title'  => $pin['title'],
			'text'   => $pin['text'],
			'icon'   => $pin['icon'],
			'lngLat' => array( (float) $pin['lng'], (float) $pin['lat'] ),
			'image'  => $pin['image'] ? wp_get_attachment_image_url( (int) $pin['image'], 'medium_large' ) : '',
		);
	}

	return array(
		'style'   => NB_Settings::style_config(),
		'track'   => $track,
		'pins'    => $pins,
		'planned' => array(),
		'i18n'    => nb_map_labels(),
	);
}

/**
 * Reminds the admin to activate the plugin.
 */
function nordlys_plugin_notice() {
	if ( nordlys_plugin_active() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html__( 'Das Theme „Nordlys“ braucht das Plugin „Norwegen Reise“ für Reisetage und Karte. Bitte aktiviere es.', 'nordlys' )
	);
}
add_action( 'admin_notices', 'nordlys_plugin_notice' );

/**
 * Adds helpful classes to the body tag.
 *
 * @param array $classes Body classes.
 * @return array
 */
function nordlys_body_classes( $classes ) {
	if ( is_front_page() && nordlys_plugin_active() ) {
		$classes[] = 'nordlys-has-trip';
	}

	if ( is_singular( 'nb_day' ) ) {
		$classes[] = 'nordlys-day';
	}

	return $classes;
}
add_filter( 'body_class', 'nordlys_body_classes' );

/**
 * Lets the lightbox find images that are wrapped in a link to the file.
 *
 * @param string $content Post content.
 * @return string
 */
function nordlys_mark_content_images( $content ) {
	if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	return str_replace( '<figure class="wp-block-image', '<figure data-nb-zoom="1" class="wp-block-image', $content );
}
add_filter( 'the_content', 'nordlys_mark_content_images' );

/**
 * Excerpt suffix.
 *
 * @return string
 */
function nordlys_excerpt_more() {
	return '…';
}
add_filter( 'excerpt_more', 'nordlys_excerpt_more' );
