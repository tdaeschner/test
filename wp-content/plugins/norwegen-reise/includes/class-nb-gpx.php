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
	 *     @type array $track     All points as [lng, lat] pairs, segments joined.
	 *     @type array $segments  One entry per recording: ['points' => array, 'time' => int].
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
		$time  = 0;

		foreach ( $xml->xpath( '//*[local-name()="trkpt"]' ) as $point ) {
			$coord = self::point_from_attributes( $point );

			if ( ! $coord ) {
				continue;
			}

			$track[] = $coord;

			// The first timestamp decides where this recording sits in the day.
			if ( ! $time ) {
				$time = self::time_from_point( $point );
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

		if ( ! $time ) {
			// Some exports carry the recording time only in the track header.
			foreach ( $xml->xpath( '//*[local-name()="metadata"]/*[local-name()="time"] | //*[local-name()="trk"]/*[local-name()="time"]' ) as $node ) {
				$time = self::to_timestamp( (string) $node );

				if ( $time ) {
					break;
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
			'segments'  => $track ? array(
				array(
					'points' => $track,
					'time'   => $time,
				),
			) : array(),
			'waypoints' => $waypoints,
		);
	}

	/**
	 * Reads the <time> child of a track point.
	 *
	 * @param SimpleXMLElement $point Track point.
	 * @return int Unix timestamp, 0 when absent.
	 */
	private static function time_from_point( $point ) {
		$nodes = $point->xpath( '*[local-name()="time"]' );

		if ( ! $nodes ) {
			return 0;
		}

		return self::to_timestamp( (string) $nodes[0] );
	}

	/**
	 * Converts an ISO 8601 timestamp into a Unix timestamp.
	 *
	 * @param string $value Raw value.
	 * @return int
	 */
	private static function to_timestamp( $value ) {
		$value = trim( $value );

		if ( '' === $value ) {
			return 0;
		}

		$timestamp = strtotime( $value );

		return $timestamp ? (int) $timestamp : 0;
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

		$lines     = array();
		$waypoints = array();

		self::collect_geojson( $data, $lines, $waypoints );

		if ( ! $lines && ! $waypoints ) {
			return new WP_Error( 'nb_no_points', __( 'In der GeoJSON-Datei wurden keine Koordinaten gefunden.', 'norwegen-reise' ) );
		}

		$track    = array();
		$segments = array();

		foreach ( $lines as $line ) {
			if ( ! $line['points'] ) {
				continue;
			}

			$track      = array_merge( $track, $line['points'] );
			$segments[] = array(
				'points' => $line['points'],
				'time'   => $line['time'],
			);
		}

		return array(
			'track'     => $track,
			'segments'  => $segments,
			'waypoints' => $waypoints,
		);
	}

	/**
	 * Walks a GeoJSON structure and collects coordinates.
	 *
	 * @param array $node      Current node.
	 * @param array $lines     Collected lines as ['points' => array, 'time' => int], by reference.
	 * @param array $waypoints Collected point features, by reference.
	 */
	private static function collect_geojson( $node, &$lines, &$waypoints ) {
		if ( ! is_array( $node ) ) {
			return;
		}

		if ( isset( $node['features'] ) && is_array( $node['features'] ) ) {
			foreach ( $node['features'] as $feature ) {
				self::collect_geojson( $feature, $lines, $waypoints );
			}

			return;
		}

		if ( isset( $node['geometries'] ) && is_array( $node['geometries'] ) ) {
			foreach ( $node['geometries'] as $geometry ) {
				self::collect_geojson( $geometry, $lines, $waypoints );
			}

			return;
		}

		$title = '';
		$time  = 0;

		if ( isset( $node['properties'] ) && is_array( $node['properties'] ) ) {
			foreach ( array( 'name', 'title', 'Name' ) as $key ) {
				if ( ! empty( $node['properties'][ $key ] ) ) {
					$title = sanitize_text_field( (string) $node['properties'][ $key ] );
					break;
				}
			}

			// Some exporters keep per-point times in coordTimes, others a start time.
			if ( ! empty( $node['properties']['coordTimes'][0] ) ) {
				$time = self::to_timestamp( (string) $node['properties']['coordTimes'][0] );
			} elseif ( ! empty( $node['properties']['time'] ) ) {
				$time = self::to_timestamp( (string) $node['properties']['time'] );
			}
		}

		if ( isset( $node['geometry'] ) && is_array( $node['geometry'] ) ) {
			$geometry            = $node['geometry'];
			$geometry['__title'] = $title;
			$geometry['__time']  = $time;
			self::collect_geojson( $geometry, $lines, $waypoints );

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
				$points = array();

				foreach ( $node['coordinates'] as $pair ) {
					$coord = self::clean_pair( $pair );

					if ( $coord ) {
						$points[] = $coord;
					}
				}

				if ( $points ) {
					$lines[] = array(
						'points' => $points,
						'time'   => isset( $node['__time'] ) ? (int) $node['__time'] : $time,
					);
				}
				break;

			case 'MultiLineString':
				foreach ( $node['coordinates'] as $line ) {
					$points = array();

					foreach ( $line as $pair ) {
						$coord = self::clean_pair( $pair );

						if ( $coord ) {
							$points[] = $coord;
						}
					}

					if ( $points ) {
						$lines[] = array(
							'points' => $points,
							'time'   => isset( $node['__time'] ) ? (int) $node['__time'] : $time,
						);
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
