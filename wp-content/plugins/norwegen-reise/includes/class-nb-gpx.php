<?php
/**
 * Imports GPX and GeoJSON tracks.
 *
 * @package NorwegenReise
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turns uploaded track files into plain [lng, lat] arrays.
 */
class NB_GPX {

	/**
	 * Parses a track file and returns points plus waypoints.
	 *
	 * @param string $contents Raw file contents.
	 * @param string $filename Original file name, used to pick the parser.
	 * @return array|WP_Error {
	 *     @type array $track     List of [lng, lat] pairs.
	 *     @type array $waypoints List of ['title' => string, 'lng' => float, 'lat' => float].
	 * }
	 */
	public static function parse( $contents, $filename = '' ) {
		$contents  = trim( $contents );
		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( '' === $contents ) {
			return new WP_Error( 'nb_empty_file', __( 'Die Datei ist leer.', 'norwegen-reise' ) );
		}

		if ( 'json' === $extension || 'geojson' === $extension || '{' === $contents[0] ) {
			return self::parse_geojson( $contents );
		}

		return self::parse_gpx( $contents );
	}

	/**
	 * Parses a GPX file: track points first, route points as fallback.
	 *
	 * @param string $contents Raw XML.
	 * @return array|WP_Error
	 */
	private static function parse_gpx( $contents ) {
		$previous = libxml_use_internal_errors( true );
		$xml      = simplexml_load_string( $contents, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOENT );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( false === $xml ) {
			return new WP_Error( 'nb_invalid_gpx', __( 'Die GPX-Datei konnte nicht gelesen werden.', 'norwegen-reise' ) );
		}

		$track = array();

		foreach ( $xml->xpath( '//*[local-name()="trkpt"]' ) as $point ) {
			$coord = self::point_from_attributes( $point );

			if ( $coord ) {
				$track[] = $coord;
			}
		}

		if ( ! $track ) {
			foreach ( $xml->xpath( '//*[local-name()="rtept"]' ) as $point ) {
				$coord = self::point_from_attributes( $point );

				if ( $coord ) {
					$track[] = $coord;
				}
			}
		}

		$waypoints = array();

		foreach ( $xml->xpath( '//*[local-name()="wpt"]' ) as $point ) {
			$coord = self::point_from_attributes( $point );

			if ( ! $coord ) {
				continue;
			}

			$waypoints[] = array(
				'title' => isset( $point->name ) ? sanitize_text_field( (string) $point->name ) : '',
				'lng'   => $coord[0],
				'lat'   => $coord[1],
			);
		}

		if ( ! $track && ! $waypoints ) {
			return new WP_Error( 'nb_no_points', __( 'In der GPX-Datei wurden keine Punkte gefunden.', 'norwegen-reise' ) );
		}

		return array(
			'track'     => $track,
			'waypoints' => $waypoints,
		);
	}

	/**
	 * Parses GeoJSON: every LineString and MultiLineString is concatenated,
	 * Points become waypoints.
	 *
	 * @param string $contents Raw JSON.
	 * @return array|WP_Error
	 */
	private static function parse_geojson( $contents ) {
		$data = json_decode( $contents, true );

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'nb_invalid_json', __( 'Die GeoJSON-Datei konnte nicht gelesen werden.', 'norwegen-reise' ) );
		}

		$track     = array();
		$waypoints = array();

		self::collect_geojson( $data, $track, $waypoints );

		if ( ! $track && ! $waypoints ) {
			return new WP_Error( 'nb_no_points', __( 'In der GeoJSON-Datei wurden keine Koordinaten gefunden.', 'norwegen-reise' ) );
		}

		return array(
			'track'     => $track,
			'waypoints' => $waypoints,
		);
	}

	/**
	 * Walks a GeoJSON structure and collects coordinates.
	 *
	 * @param array $node      Current node.
	 * @param array $track     Collected line points, by reference.
	 * @param array $waypoints Collected point features, by reference.
	 */
	private static function collect_geojson( $node, &$track, &$waypoints ) {
		if ( ! is_array( $node ) ) {
			return;
		}

		if ( isset( $node['features'] ) && is_array( $node['features'] ) ) {
			foreach ( $node['features'] as $feature ) {
				self::collect_geojson( $feature, $track, $waypoints );
			}

			return;
		}

		if ( isset( $node['geometries'] ) && is_array( $node['geometries'] ) ) {
			foreach ( $node['geometries'] as $geometry ) {
				self::collect_geojson( $geometry, $track, $waypoints );
			}

			return;
		}

		$title = '';

		if ( isset( $node['properties'] ) && is_array( $node['properties'] ) ) {
			foreach ( array( 'name', 'title', 'Name' ) as $key ) {
				if ( ! empty( $node['properties'][ $key ] ) ) {
					$title = sanitize_text_field( (string) $node['properties'][ $key ] );
					break;
				}
			}
		}

		if ( isset( $node['geometry'] ) && is_array( $node['geometry'] ) ) {
			$geometry         = $node['geometry'];
			$geometry['__title'] = $title;
			self::collect_geojson( $geometry, $track, $waypoints );

			return;
		}

		if ( ! isset( $node['type'], $node['coordinates'] ) ) {
			return;
		}

		switch ( $node['type'] ) {
			case 'Point':
				$coord = self::clean_pair( $node['coordinates'] );

				if ( $coord ) {
					$waypoints[] = array(
						'title' => isset( $node['__title'] ) ? $node['__title'] : $title,
						'lng'   => $coord[0],
						'lat'   => $coord[1],
					);
				}
				break;

			case 'LineString':
				foreach ( $node['coordinates'] as $pair ) {
					$coord = self::clean_pair( $pair );

					if ( $coord ) {
						$track[] = $coord;
					}
				}
				break;

			case 'MultiLineString':
				foreach ( $node['coordinates'] as $line ) {
					foreach ( $line as $pair ) {
						$coord = self::clean_pair( $pair );

						if ( $coord ) {
							$track[] = $coord;
						}
					}
				}
				break;
		}
	}

	/**
	 * Reads lat/lon attributes from a GPX node.
	 *
	 * @param SimpleXMLElement $node Node with lat/lon attributes.
	 * @return array|null [lng, lat] or null when invalid.
	 */
	private static function point_from_attributes( $node ) {
		$attributes = $node->attributes();

		if ( ! isset( $attributes['lat'], $attributes['lon'] ) ) {
			return null;
		}

		return self::clean_pair( array( (float) $attributes['lon'], (float) $attributes['lat'] ) );
	}

	/**
	 * Validates and rounds a coordinate pair.
	 *
	 * @param mixed $pair Raw pair, [lng, lat] with optional elevation.
	 * @return array|null
	 */
	private static function clean_pair( $pair ) {
		if ( ! is_array( $pair ) || count( $pair ) < 2 ) {
			return null;
		}

		$lng = (float) $pair[0];
		$lat = (float) $pair[1];

		if ( $lng < -180 || $lng > 180 || $lat < -90 || $lat > 90 ) {
			return null;
		}

		// Six decimals is roughly 10 cm — far beyond what the map needs.
		return array( round( $lng, 6 ), round( $lat, 6 ) );
	}
}
