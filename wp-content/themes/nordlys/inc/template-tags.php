<?php
/**
 * Template helpers.
 *
 * @package Nordlys
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the ordered list of published travel days.
 *
 * @return WP_Post[]
 */
function nordlys_get_days() {
	static $days = null;

	if ( null !== $days ) {
		return $days;
	}

	$days = get_posts(
		array(
			'post_type'      => 'nb_day',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_key'       => '_nb_day_number',
			'orderby'        => array(
				'meta_value_num' => 'ASC',
				'date'           => 'ASC',
			),
		)
	);

	return $days;
}

/**
 * Number of a day, falling back to its position in the list.
 *
 * @param int $post_id Day post ID.
 * @return int
 */
function nordlys_day_number( $post_id ) {
	$number = (int) get_post_meta( $post_id, '_nb_day_number', true );

	if ( $number ) {
		return $number;
	}

	foreach ( nordlys_get_days() as $index => $day ) {
		if ( $day->ID === $post_id ) {
			return $index + 1;
		}
	}

	return 0;
}

/**
 * Formatted date of a day.
 *
 * @param int $post_id Day post ID.
 * @return string
 */
function nordlys_day_date( $post_id ) {
	$date = get_post_meta( $post_id, '_nb_date', true );

	if ( ! $date ) {
		return get_the_date( '', $post_id );
	}

	$timestamp = strtotime( $date . ' 12:00:00' );

	return $timestamp ? wp_date( 'j. F Y', $timestamp ) : $date;
}

/**
 * Prints the small fact row of a day.
 *
 * @param int  $post_id Day post ID.
 * @param bool $compact Leave out the softer facts.
 */
function nordlys_day_facts( $post_id, $compact = false ) {
	$place    = get_post_meta( $post_id, '_nb_place', true );
	$distance = get_post_meta( $post_id, '_nb_distance_km', true );
	$weather  = get_post_meta( $post_id, '_nb_weather', true );
	$pins     = function_exists( 'nordlys_plugin_active' ) && nordlys_plugin_active() ? NB_Meta::get_pins( $post_id ) : array();

	$facts = array();

	if ( $place ) {
		$facts[] = array( 'route', $place );
	}

	if ( $distance ) {
		$facts[] = array( 'distance', sprintf( '%s km', number_format_i18n( (float) $distance, 0 ) ) );
	}

	if ( ! $compact && $weather ) {
		$facts[] = array( 'weather', $weather );
	}

	if ( ! $compact && $pins ) {
		$facts[] = array(
			'pin',
			sprintf(
				/* translators: %d: number of highlights */
				_n( '%d Highlight', '%d Highlights', count( $pins ), 'nordlys' ),
				count( $pins )
			),
		);
	}

	if ( ! $facts ) {
		return;
	}

	echo '<ul class="nb-facts">';

	foreach ( $facts as $fact ) {
		printf(
			'<li class="nb-facts__item nb-facts__item--%1$s">%2$s%3$s</li>',
			esc_attr( $fact[0] ),
			nordlys_icon( $fact[0] ), // phpcs:ignore WordPress.Security.EscapeOutput
			esc_html( $fact[1] )
		);
	}

	echo '</ul>';
}

/**
 * Returns an inline SVG icon.
 *
 * @param string $name Icon name.
 * @return string
 */
function nordlys_icon( $name ) {
	$paths = array(
		'route'    => '<path d="M6 3a3 3 0 0 0-3 3c0 2.2 3 5.5 3 5.5S9 8.2 9 6a3 3 0 0 0-3-3Zm0 4.2A1.2 1.2 0 1 1 6 4.8a1.2 1.2 0 0 1 0 2.4ZM18 12.5a3 3 0 0 0-3 3c0 2.2 3 5.5 3 5.5s3-3.3 3-5.5a3 3 0 0 0-3-3Zm0 4.2a1.2 1.2 0 1 1 0-2.4 1.2 1.2 0 0 1 0 2.4Z"/><path d="M9.5 6.8c2.2.4 3.6 1.3 4.3 2.6.8 1.5.4 3-.6 4.3-.7.9-1.6 1.6-2.5 2.2" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-dasharray="2 2.4"/>',
		'distance' => '<path d="M3 8h18a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1Zm3 1v3m4-3v4m4-4v3m4-3v4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',
		'weather'  => '<path d="M7 16a4 4 0 0 1 .6-8 5 5 0 0 1 9.5 1.4A3.3 3.3 0 0 1 17 16H7Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>',
		'pin'      => '<path d="M12 2a6 6 0 0 0-6 6c0 4.4 6 14 6 14s6-9.6 6-14a6 6 0 0 0-6-6Zm0 8.4A2.4 2.4 0 1 1 12 5.6a2.4 2.4 0 0 1 0 4.8Z"/>',
		'calendar' => '<path d="M4 5h16v16H4z" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M4 10h16M8 3v4m8-4v4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',
		'arrow'    => '<path d="M5 12h13m-5-5 5 5-5 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>',
		'compass'  => '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="m15 9-2 4-4 2 2-4z"/>',
	);

	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}

	return '<svg class="nb-icon nb-icon--' . esc_attr( $name ) . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

/**
 * Returns the SVG shape used for a flag on the map.
 *
 * @param string $icon Icon key.
 * @return string
 */
function nordlys_pin_glyph( $icon ) {
	$glyphs = array(
		'flag'     => '<path d="M6 3v18M6 4h11l-2.2 3.4L17 11H6z"/>',
		'view'     => '<path d="M3 17 9 8l4 5 2.5-3L21 17z"/><circle cx="17" cy="6" r="2"/>',
		'hike'     => '<circle cx="13" cy="4.5" r="2"/><path d="m9 21 3-6-2.5-3L7 15H4m8-6 3 2 1 4 3 6"/>',
		'water'    => '<path d="M12 3c3 4 5 6.5 5 9a5 5 0 0 1-10 0c0-2.5 2-5 5-9Z"/>',
		'wildlife' => '<path d="M5 10c0-3 2-5 4-5s3 1 3 3-1 3-3 3-4-1-4-1Zm14 0c0-3-2-5-4-5m-3 8v6m-4 0h8"/>',
		'food'     => '<path d="M6 3v8a2 2 0 0 0 4 0V3M8 11v10M17 3c-1.5 1-2 3-2 5s.5 3 2 3v10"/>',
		'camp'     => '<path d="m12 4 8 16H4zM12 4v16"/>',
		'photo'    => '<path d="M3 7h4l1.5-2h7L17 7h4v12H3z"/><circle cx="12" cy="13" r="3.5"/>',
		'star'     => '<path d="m12 3 2.7 5.6 6.3.9-4.5 4.3 1 6.2-5.5-3-5.5 3 1-6.2L3 9.5l6.3-.9z"/>',
	);

	$glyph = isset( $glyphs[ $icon ] ) ? $glyphs[ $icon ] : $glyphs['flag'];

	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $glyph . '</svg>';
}

/**
 * Previous or next day in trip order.
 *
 * @param int    $post_id   Current day.
 * @param string $direction prev|next.
 * @return WP_Post|null
 */
function nordlys_adjacent_day( $post_id, $direction = 'next' ) {
	$days = nordlys_get_days();

	foreach ( $days as $index => $day ) {
		if ( $day->ID !== $post_id ) {
			continue;
		}

		$target = 'next' === $direction ? $index + 1 : $index - 1;

		return isset( $days[ $target ] ) ? $days[ $target ] : null;
	}

	return null;
}

/**
 * Trip-wide numbers for the intro block.
 *
 * @return array
 */
function nordlys_trip_stats() {
	if ( ! nordlys_plugin_active() ) {
		return array(
			'daysOnline'  => 0,
			'daysPlanned' => 17,
			'totalKm'     => 0,
			'pinCount'    => 0,
		);
	}

	$data = NB_Trip::get_data();

	return $data['trip'];
}
