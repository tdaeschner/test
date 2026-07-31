<?php
/**
 * REST endpoint that feeds the map.
 *
 * @package NorwegenReise
 */

defined( 'ABSPATH' ) || exit;

/**
 * Exposes the trip payload under /wp-json/norwegen/v1/trip.
 */
class NB_REST {

	const NAMESPACE_V1 = 'norwegen/v1';

	/**
	 * Hooks the route.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Registers the routes.
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/trip',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_trip' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/trip.geojson',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_geojson' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Returns the full trip payload.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_trip() {
		$response = rest_ensure_response( NB_Trip::get_data() );
		$response->header( 'Cache-Control', 'public, max-age=300' );

		return $response;
	}

	/**
	 * Returns the trip as a plain GeoJSON FeatureCollection, handy for exports
	 * or for pulling the route into another tool.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_geojson() {
		$data     = NB_Trip::get_data();
		$features = array();

		foreach ( $data['days'] as $day ) {
			if ( $day['track'] ) {
				$features[] = array(
					'type'       => 'Feature',
					'properties' => array(
						'kind'      => 'day',
						'day'       => $day['number'],
						'title'     => $day['title'],
						'date'      => $day['date'],
						'distance'  => $day['distance'],
						'permalink' => $day['permalink'],
					),
					'geometry'   => array(
						'type'        => 'LineString',
						'coordinates' => $day['track'],
					),
				);
			}

			foreach ( $day['pins'] as $pin ) {
				$features[] = array(
					'type'       => 'Feature',
					'properties' => array(
						'kind'  => 'pin',
						'day'   => $day['number'],
						'title' => $pin['title'],
						'text'  => $pin['text'],
						'icon'  => $pin['icon'],
					),
					'geometry'   => array(
						'type'        => 'Point',
						'coordinates' => $pin['lngLat'],
					),
				);
			}
		}

		return rest_ensure_response(
			array(
				'type'     => 'FeatureCollection',
				'features' => $features,
			)
		);
	}
}
