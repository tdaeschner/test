<?php
/**
 * Small geo toolbox: distances, simplification, bounds.
 *
 * Coordinates are always handled as [lng, lat] pairs, i.e. GeoJSON order.
 *
 * @package NorwegenReise
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stateless geo helpers.
 */
class NB_Geo {

	const EARTH_RADIUS_KM = 6371.0088;

	/**
	 * Great circle distance between two points in kilometres.
	 *
	 * @param array $a Point as [lng, lat].
	 * @param array $b Point as [lng, lat].
	 * @return float
	 */
	public static function distance_km( $a, $b ) {
		$lat1 = deg2rad( (float) $a[1] );
		$lat2 = deg2rad( (float) $b[1] );
		$d_lat = $lat2 - $lat1;
		$d_lng = deg2rad( (float) $b[0] - (float) $a[0] );

		$h = sin( $d_lat / 2 ) ** 2 + cos( $lat1 ) * cos( $lat2 ) * sin( $d_lng / 2 ) ** 2;

		return 2 * self::EARTH_RADIUS_KM * asin( min( 1.0, sqrt( $h ) ) );
	}

	/**
	 * Total length of a track in kilometres.
	 *
	 * @param array $track List of [lng, lat] pairs.
	 * @return float
	 */
	public static function track_length_km( $track ) {
		$length = 0.0;
		$count  = is_array( $track ) ? count( $track ) : 0;

		for ( $i = 1; $i < $count; $i++ ) {
			$length += self::distance_km( $track[ $i - 1 ], $track[ $i ] );
		}

		return $length;
	}

	/**
	 * Bounding box of a list of points.
	 *
	 * @param array $points List of [lng, lat] pairs.
	 * @return array|null [[west, south], [east, north]] or null when empty.
	 */
	public static function bounds( $points ) {
		if ( empty( $points ) ) {
			return null;
		}

		$west  = INF;
		$south = INF;
		$east  = -INF;
		$north = -INF;

		foreach ( $points as $point ) {
			$lng = (float) $point[0];
			$lat = (float) $point[1];

			$west  = min( $west, $lng );
			$east  = max( $east, $lng );
			$south = min( $south, $lat );
			$north = max( $north, $lat );
		}

		return array(
			array( round( $west, 6 ), round( $south, 6 ) ),
			array( round( $east, 6 ), round( $north, 6 ) ),
		);
	}

	/**
	 * Merges several bounding boxes into one.
	 *
	 * @param array $boxes List of bounds.
	 * @return array|null
	 */
	public static function merge_bounds( $boxes ) {
		$points = array();

		foreach ( $boxes as $box ) {
			if ( ! $box ) {
				continue;
			}
			$points[] = $box[0];
			$points[] = $box[1];
		}

		return self::bounds( $points );
	}

	/**
	 * Ramer-Douglas-Peucker simplification with a tolerance in degrees.
	 *
	 * GPS logs easily contain tens of thousands of points; the map only needs a
	 * fraction of that. Runs iteratively to avoid deep recursion on long tracks.
	 *
	 * @param array $points    List of [lng, lat] pairs.
	 * @param float $tolerance Tolerance in degrees (≈ 0.0001 → 11 m).
	 * @return array
	 */
	public static function simplify( $points, $tolerance = 0.00012 ) {
		$count = count( $points );

		if ( $count < 3 ) {
			return $points;
		}

		$keep       = array_fill( 0, $count, false );
		$keep[0]    = true;
		$keep[ $count - 1 ] = true;
		$stack      = array( array( 0, $count - 1 ) );
		$tolerance2 = $tolerance * $tolerance;

		while ( $stack ) {
			list( $first, $last ) = array_pop( $stack );

			$max_dist  = 0.0;
			$max_index = 0;

			for ( $i = $first + 1; $i < $last; $i++ ) {
				$dist = self::squared_segment_distance( $points[ $i ], $points[ $first ], $points[ $last ] );

				if ( $dist > $max_dist ) {
					$max_dist  = $dist;
					$max_index = $i;
				}
			}

			if ( $max_dist > $tolerance2 && $max_index > 0 ) {
				$keep[ $max_index ] = true;
				$stack[]            = array( $first, $max_index );
				$stack[]            = array( $max_index, $last );
			}
		}

		$result = array();

		foreach ( $points as $index => $point ) {
			if ( $keep[ $index ] ) {
				$result[] = $point;
			}
		}

		return $result;
	}

	/**
	 * Reduces a track until it fits into a maximum number of points.
	 *
	 * @param array $points List of [lng, lat] pairs.
	 * @param int   $max    Maximum number of points.
	 * @return array
	 */
	public static function limit_points( $points, $max = 1200 ) {
		$tolerance = 0.00008;
		$result    = self::simplify( $points, $tolerance );

		// Each round quadruples the tolerance; ten rounds cover any realistic log.
		for ( $i = 0; $i < 10 && count( $result ) > $max; $i++ ) {
			$tolerance *= 4;
			$result     = self::simplify( $points, $tolerance );
		}

		return $result;
	}

	/**
	 * Squared distance of a point to a segment, in degree space.
	 *
	 * Longitude is scaled by cos(lat) so the simplification behaves the same at
	 * Norwegian latitudes as it would near the equator.
	 *
	 * @param array $p Point.
	 * @param array $a Segment start.
	 * @param array $b Segment end.
	 * @return float
	 */
	private static function squared_segment_distance( $p, $a, $b ) {
		$scale = cos( deg2rad( (float) $a[1] ) );

		$px = ( (float) $p[0] ) * $scale;
		$py = (float) $p[1];
		$ax = ( (float) $a[0] ) * $scale;
		$ay = (float) $a[1];
		$bx = ( (float) $b[0] ) * $scale;
		$by = (float) $b[1];

		$dx = $bx - $ax;
		$dy = $by - $ay;

		if ( 0.0 !== $dx || 0.0 !== $dy ) {
			$t = ( ( $px - $ax ) * $dx + ( $py - $ay ) * $dy ) / ( $dx * $dx + $dy * $dy );

			if ( $t > 1 ) {
				$ax = $bx;
				$ay = $by;
			} elseif ( $t > 0 ) {
				$ax += $dx * $t;
				$ay += $dy * $t;
			}
		}

		$dx = $px - $ax;
		$dy = $py - $ay;

		return $dx * $dx + $dy * $dy;
	}

	/**
	 * Finds the position of a point along a track, as a fraction between 0 and 1.
	 *
	 * Used to decide when a flag pops up while the route fills on scroll.
	 *
	 * @param array $point Point as [lng, lat].
	 * @param array $track List of [lng, lat] pairs.
	 * @return float
	 */
	public static function fraction_along_track( $point, $track ) {
		$count = is_array( $track ) ? count( $track ) : 0;

		if ( $count < 2 ) {
			return 0.0;
		}

		$best_index = 0;
		$best_dist  = INF;

		foreach ( $track as $index => $vertex ) {
			$dist = self::distance_km( $point, $vertex );

			if ( $dist < $best_dist ) {
				$best_dist  = $dist;
				$best_index = $index;
			}
		}

		$total   = 0.0;
		$partial = 0.0;

		for ( $i = 1; $i < $count; $i++ ) {
			$segment = self::distance_km( $track[ $i - 1 ], $track[ $i ] );
			$total  += $segment;

			if ( $i <= $best_index ) {
				$partial += $segment;
			}
		}

		return $total > 0 ? min( 1.0, $partial / $total ) : 0.0;
	}
}
