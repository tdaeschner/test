<?php
/**
 * Assembles the trip payload the map runs on.
 *
 * @package NorwegenReise
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds and caches the full trip data structure.
 */
class NB_Trip {

	const CACHE_KEY = 'nb_trip_data_v1';

	/**
	 * Returns the trip payload, cached in a transient.
	 *
	 * @param bool $force Skip the cache.
	 * @return array
	 */
	public static function get_data( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( self::CACHE_KEY );

			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$data = self::build();

		set_transient( self::CACHE_KEY, $data, DAY_IN_SECONDS );

		return $data;
	}

	/**
	 * Drops the cached payload.
	 */
	public static function flush_cache() {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Builds the payload from the published days.
	 *
	 * @return array
	 */
	private static function build() {
		$posts = get_posts(
			array(
				'post_type'      => NB_Post_Types::DAY,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_key'       => '_nb_day_number',
				'orderby'        => array(
					'meta_value_num' => 'ASC',
					'date'           => 'ASC',
				),
			)
		);

		$days        = array();
		$all_points  = array();
		$lengths     = array();
		$total_km    = 0.0;
		$travelled   = 0.0;
		$pin_count   = 0;

		foreach ( $posts as $index => $post ) {
			$track  = NB_Meta::get_track( $post->ID );
			$pins   = NB_Meta::get_pins( $post->ID );
			$length = NB_Geo::track_length_km( $track );

			$lengths[]  = $length;
			$total_km  += $length;
			$pin_count += count( $pins );
			$all_points = array_merge( $all_points, $track );

			$prepared_pins = array();

			foreach ( $pins as $pin ) {
				$point        = array( (float) $pin['lng'], (float) $pin['lat'] );
				$all_points[] = $point;

				$prepared_pins[] = array(
					'title'    => $pin['title'],
					'text'     => $pin['text'],
					'icon'     => $pin['icon'],
					'lngLat'   => $point,
					'image'    => $pin['image'] ? wp_get_attachment_image_url( (int) $pin['image'], 'medium_large' ) : '',
					'local'    => $track ? NB_Geo::fraction_along_track( $point, $track ) : 0.0,
					'dayIndex' => $index,
				);
			}

			$distance = get_post_meta( $post->ID, '_nb_distance_km', true );

			// The line on the map bridges the gaps between recordings; the
			// distance shown to readers counts only what was really travelled.
			$travelled += '' === $distance ? $length : (float) $distance;

			$days[] = array(
				'id'        => $post->ID,
				'number'    => (int) get_post_meta( $post->ID, '_nb_day_number', true ),
				'date'      => get_post_meta( $post->ID, '_nb_date', true ),
				'dateLabel' => self::date_label( $post ),
				'title'     => get_the_title( $post ),
				'place'     => get_post_meta( $post->ID, '_nb_place', true ),
				'weather'   => get_post_meta( $post->ID, '_nb_weather', true ),
				'distance'  => '' === $distance ? null : (float) $distance,
				'excerpt'   => self::excerpt( $post ),
				'permalink' => get_permalink( $post ),
				'image'     => get_the_post_thumbnail_url( $post, 'large' ),
				'thumb'     => get_the_post_thumbnail_url( $post, 'medium' ),
				'track'     => $track,
				'lengthKm'  => round( $length, 2 ),
				'start'     => $track ? $track[0] : null,
				'end'       => $track ? end( $track ) : null,
				'bounds'    => NB_Geo::bounds( $track ),
				'pins'      => $prepared_pins,
			);
		}

		// Each day owns the share of the line that matches its distance, so the
		// route fills at a believable speed while scrolling.
		$cumulative = array( 0.0 );
		$running    = 0.0;

		foreach ( $lengths as $length ) {
			$running     += $length;
			$cumulative[] = $total_km > 0 ? $running / $total_km : 0.0;
		}

		foreach ( $days as $index => $day ) {
			$from = $cumulative[ $index ];
			$to   = $cumulative[ $index + 1 ];

			$days[ $index ]['from'] = round( $from, 6 );
			$days[ $index ]['to']   = round( $to, 6 );

			foreach ( $days[ $index ]['pins'] as $pin_index => $pin ) {
				$days[ $index ]['pins'][ $pin_index ]['progress'] = round( $from + ( $to - $from ) * $pin['local'], 6 );
			}
		}

		$planned = NB_Settings::planned_route();
		$bounds  = NB_Geo::merge_bounds(
			array(
				NB_Geo::bounds( $all_points ),
				NB_Geo::bounds( $planned ),
			)
		);

		return array(
			'trip'    => array(
				'title'       => NB_Settings::get( 'trip_title' ),
				'subtitle'    => NB_Settings::get( 'trip_subtitle' ),
				'startDate'   => NB_Settings::get( 'start_date' ),
				'daysPlanned' => (int) NB_Settings::get( 'days_planned' ),
				'daysOnline'  => count( $days ),
				'totalKm'     => round( $travelled, 1 ),
				'drawnKm'     => round( $total_km, 1 ),
				'pinCount'    => $pin_count,
			),
			'style'   => NB_Settings::style_config(),
			'bounds'  => $bounds ? $bounds : NB_Settings::default_bounds(),
			'planned' => $planned,
			'days'    => $days,
		);
	}

	/**
	 * Human readable date for a day.
	 *
	 * @param WP_Post $post Day post.
	 * @return string
	 */
	private static function date_label( $post ) {
		$date = get_post_meta( $post->ID, '_nb_date', true );

		if ( ! $date ) {
			return get_the_date( '', $post );
		}

		$timestamp = strtotime( $date . ' 12:00:00' );

		return $timestamp ? wp_date( 'D, j. F Y', $timestamp ) : $date;
	}

	/**
	 * Short teaser for the map cards.
	 *
	 * @param WP_Post $post Day post.
	 * @return string
	 */
	private static function excerpt( $post ) {
		if ( $post->post_excerpt ) {
			return wp_strip_all_tags( $post->post_excerpt );
		}

		return wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 32 );
	}
}

/**
 * Clears the cache whenever a day or an attachment changes.
 */
function nb_flush_trip_cache() {
	NB_Trip::flush_cache();
}
add_action( 'save_post_' . NB_Post_Types::DAY, 'nb_flush_trip_cache', 20 );
add_action( 'deleted_post', 'nb_flush_trip_cache' );
add_action( 'trashed_post', 'nb_flush_trip_cache' );
add_action( 'untrashed_post', 'nb_flush_trip_cache' );
add_action( 'switch_theme', 'nb_flush_trip_cache' );
