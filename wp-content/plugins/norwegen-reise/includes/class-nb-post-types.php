<?php
/**
 * Custom post type for a single day of the trip.
 *
 * @package NorwegenReise
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the "Reisetag" post type.
 */
class NB_Post_Types {

	const DAY = 'nb_day';

	/**
	 * Hooks registration.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'pre_get_posts', array( __CLASS__, 'order_archive_by_day' ) );
		add_filter( 'pre_get_posts', array( __CLASS__, 'order_admin_by_day' ) );
		add_filter( 'manage_' . self::DAY . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::DAY . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
		add_filter( 'manage_edit-' . self::DAY . '_sortable_columns', array( __CLASS__, 'sortable_columns' ) );
	}

	/**
	 * Registers the post type itself.
	 */
	public static function register() {
		$labels = array(
			'name'               => __( 'Reisetage', 'norwegen-reise' ),
			'singular_name'      => __( 'Reisetag', 'norwegen-reise' ),
			'add_new'            => __( 'Neuer Tag', 'norwegen-reise' ),
			'add_new_item'       => __( 'Neuen Reisetag anlegen', 'norwegen-reise' ),
			'edit_item'          => __( 'Reisetag bearbeiten', 'norwegen-reise' ),
			'new_item'           => __( 'Neuer Reisetag', 'norwegen-reise' ),
			'view_item'          => __( 'Reisetag ansehen', 'norwegen-reise' ),
			'search_items'       => __( 'Reisetage durchsuchen', 'norwegen-reise' ),
			'not_found'          => __( 'Noch keine Reisetage angelegt', 'norwegen-reise' ),
			'not_found_in_trash' => __( 'Keine Reisetage im Papierkorb', 'norwegen-reise' ),
			'all_items'          => __( 'Alle Reisetage', 'norwegen-reise' ),
			'menu_name'          => __( 'Norwegen', 'norwegen-reise' ),
		);

		register_post_type(
			self::DAY,
			array(
				'labels'        => $labels,
				'public'        => true,
				'has_archive'   => true,
				'menu_icon'     => 'dashicons-location-alt',
				'menu_position' => 5,
				'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'author', 'custom-fields' ),
				'rewrite'       => array(
					'slug'       => 'reisetag',
					'with_front' => false,
				),
				'show_in_rest'  => true,
				'rest_base'     => 'reisetage',
				'template'      => array(
					array( 'core/paragraph', array( 'placeholder' => __( 'Wie war der Tag?', 'norwegen-reise' ) ) ),
				),
			)
		);
	}

	/**
	 * Archives and feeds should follow the trip, not the publishing order.
	 *
	 * @param WP_Query $query Current query.
	 */
	public static function order_archive_by_day( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( ! $query->is_post_type_archive( self::DAY ) ) {
			return;
		}

		$query->set( 'meta_key', '_nb_day_number' );
		$query->set( 'orderby', 'meta_value_num' );
		$query->set( 'order', 'ASC' );
		$query->set( 'posts_per_page', -1 );
	}

	/**
	 * Sorts the admin list table by day number by default.
	 *
	 * @param WP_Query $query Current query.
	 */
	public static function order_admin_by_day( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( self::DAY !== $query->get( 'post_type' ) ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		if ( ! $orderby || 'nb_day_number' === $orderby ) {
			$query->set( 'meta_key', '_nb_day_number' );
			$query->set( 'orderby', 'meta_value_num' );

			if ( ! $query->get( 'order' ) ) {
				$query->set( 'order', 'ASC' );
			}
		}
	}

	/**
	 * Adds trip specific columns to the admin list table.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		$new = array();

		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$new['nb_day_number'] = __( 'Tag', 'norwegen-reise' );
			}
			$new[ $key ] = $label;

			if ( 'title' === $key ) {
				$new['nb_place']    = __( 'Etappe', 'norwegen-reise' );
				$new['nb_track']    = __( 'Track', 'norwegen-reise' );
				$new['nb_pins']     = __( 'Fähnchen', 'norwegen-reise' );
			}
		}

		return $new;
	}

	/**
	 * Renders the custom columns.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public static function column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'nb_day_number':
				$number = get_post_meta( $post_id, '_nb_day_number', true );
				echo $number ? esc_html( $number ) : '—';
				break;

			case 'nb_place':
				$place = get_post_meta( $post_id, '_nb_place', true );
				echo $place ? esc_html( $place ) : '—';
				break;

			case 'nb_track':
				$track = NB_Meta::get_track( $post_id );
				if ( $track ) {
					printf(
						/* translators: 1: number of points, 2: distance in km */
						esc_html__( '%1$d Punkte · %2$s km', 'norwegen-reise' ),
						count( $track ),
						esc_html( number_format_i18n( NB_Geo::track_length_km( $track ), 1 ) )
					);
				} else {
					echo '—';
				}
				break;

			case 'nb_pins':
				$pins = NB_Meta::get_pins( $post_id );
				echo $pins ? count( $pins ) : '—';
				break;
		}
	}

	/**
	 * Makes the day column sortable.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public static function sortable_columns( $columns ) {
		$columns['nb_day_number'] = 'nb_day_number';

		return $columns;
	}
}
