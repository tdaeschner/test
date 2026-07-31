<?php
/**
 * Meta boxes for a travel day: facts, track and highlight flags.
 *
 * @package NorwegenReise
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers, renders and saves everything attached to a "Reisetag".
 */
class NB_Meta {

	const NONCE = 'nb_day_meta';

	/**
	 * Hooks meta boxes and saving.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_' . NB_Post_Types::DAY, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'post_edit_form_tag', array( __CLASS__, 'form_enctype' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_notices' ) );
	}

	/**
	 * Prints the notices queued during the last save and clears them.
	 */
	public static function render_notices() {
		$screen = get_current_screen();

		if ( ! $screen || 'post' !== $screen->base || NB_Post_Types::DAY !== $screen->post_type ) {
			return;
		}

		$post_id = get_the_ID();
		$notices = $post_id ? get_post_meta( $post_id, '_nb_notices', true ) : array();

		if ( ! is_array( $notices ) || ! $notices ) {
			return;
		}

		delete_post_meta( $post_id, '_nb_notices' );

		foreach ( $notices as $notice ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				'error' === $notice['type'] ? 'error' : 'success',
				esc_html( $notice['message'] )
			);
		}
	}

	/**
	 * Icon set available for the flags.
	 *
	 * @return array Icon key => label.
	 */
	public static function pin_icons() {
		return array(
			'flag'     => __( 'Fähnchen', 'norwegen-reise' ),
			'view'     => __( 'Aussichtspunkt', 'norwegen-reise' ),
			'hike'     => __( 'Wanderung', 'norwegen-reise' ),
			'water'    => __( 'Wasserfall / Fjord', 'norwegen-reise' ),
			'wildlife' => __( 'Tiere', 'norwegen-reise' ),
			'food'     => __( 'Essen', 'norwegen-reise' ),
			'camp'     => __( 'Übernachtung', 'norwegen-reise' ),
			'photo'    => __( 'Foto-Spot', 'norwegen-reise' ),
			'star'     => __( 'Highlight', 'norwegen-reise' ),
		);
	}

	/**
	 * Registers meta keys so they are available in the REST API too.
	 */
	public static function register_meta() {
		$scalars = array(
			'_nb_day_number'  => 'integer',
			'_nb_date'        => 'string',
			'_nb_place'       => 'string',
			'_nb_distance_km' => 'number',
			'_nb_weather'     => 'string',
		);

		foreach ( $scalars as $key => $type ) {
			register_post_meta(
				NB_Post_Types::DAY,
				$key,
				array(
					'type'          => $type,
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
		}
	}

	/**
	 * The track upload needs a multipart form.
	 */
	public static function form_enctype() {
		global $post;

		if ( $post && NB_Post_Types::DAY === $post->post_type ) {
			echo ' enctype="multipart/form-data"';
		}
	}

	/**
	 * Registers the meta boxes.
	 */
	public static function add_meta_boxes() {
		add_meta_box(
			'nb-day-facts',
			__( 'Tages-Eckdaten', 'norwegen-reise' ),
			array( __CLASS__, 'render_facts' ),
			NB_Post_Types::DAY,
			'side',
			'high'
		);

		add_meta_box(
			'nb-day-track',
			__( 'Route des Tages', 'norwegen-reise' ),
			array( __CLASS__, 'render_track' ),
			NB_Post_Types::DAY,
			'normal',
			'high'
		);

		add_meta_box(
			'nb-day-pins',
			__( 'Fähnchen & Highlights', 'norwegen-reise' ),
			array( __CLASS__, 'render_pins' ),
			NB_Post_Types::DAY,
			'normal',
			'high'
		);
	}

	/**
	 * Loads the admin styles, scripts and map data.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function enqueue( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || NB_Post_Types::DAY !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'maplibre-gl' );
		wp_enqueue_style( 'nb-admin', NB_URL . 'assets/admin.css', array( 'maplibre-gl' ), NB_VERSION );
		wp_enqueue_script( 'nb-admin', NB_URL . 'assets/admin.js', array( 'maplibre-gl', 'nb-map-style', 'jquery' ), NB_VERSION, true );

		$post_id = get_the_ID();

		wp_localize_script(
			'nb-admin',
			'nbAdmin',
			array(
				'style'    => NB_Settings::style_config(),
				'track'    => self::get_track( $post_id ),
				'pins'     => self::get_pins( $post_id ),
				'fallback' => NB_Settings::default_center(),
				'i18n'     => array(
					'pickHint'   => __( 'Klicke in die Karte, um die Position zu setzen.', 'norwegen-reise' ),
					'pickCancel' => __( 'Abbrechen', 'norwegen-reise' ),
					'pickStart'  => __( 'Position in Karte wählen', 'norwegen-reise' ),
					'confirmRow' => __( 'Dieses Fähnchen wirklich entfernen?', 'norwegen-reise' ),
					'chooseImage' => __( 'Bild auswählen', 'norwegen-reise' ),
				),
			)
		);
	}

	/**
	 * Renders the facts box.
	 *
	 * @param WP_Post $post Current post.
	 */
	public static function render_facts( $post ) {
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );

		$number   = get_post_meta( $post->ID, '_nb_day_number', true );
		$date     = get_post_meta( $post->ID, '_nb_date', true );
		$place    = get_post_meta( $post->ID, '_nb_place', true );
		$distance = get_post_meta( $post->ID, '_nb_distance_km', true );
		$weather  = get_post_meta( $post->ID, '_nb_weather', true );
		?>
		<p>
			<label class="nb-label" for="nb_day_number"><?php esc_html_e( 'Tag Nr.', 'norwegen-reise' ); ?></label>
			<input type="number" min="1" step="1" id="nb_day_number" name="nb_day_number" class="widefat" value="<?php echo esc_attr( $number ); ?>" />
			<span class="description"><?php esc_html_e( 'Bestimmt die Reihenfolge auf der Karte.', 'norwegen-reise' ); ?></span>
		</p>
		<p>
			<label class="nb-label" for="nb_date"><?php esc_html_e( 'Datum', 'norwegen-reise' ); ?></label>
			<input type="date" id="nb_date" name="nb_date" class="widefat" value="<?php echo esc_attr( $date ); ?>" />
		</p>
		<p>
			<label class="nb-label" for="nb_place"><?php esc_html_e( 'Etappe', 'norwegen-reise' ); ?></label>
			<input type="text" id="nb_place" name="nb_place" class="widefat" value="<?php echo esc_attr( $place ); ?>" placeholder="<?php esc_attr_e( 'Geiranger → Trollstigen', 'norwegen-reise' ); ?>" />
		</p>
		<p>
			<label class="nb-label" for="nb_distance_km"><?php esc_html_e( 'Distanz (km)', 'norwegen-reise' ); ?></label>
			<input type="number" step="0.1" min="0" id="nb_distance_km" name="nb_distance_km" class="widefat" value="<?php echo esc_attr( $distance ); ?>" />
			<span class="description"><?php esc_html_e( 'Leer lassen: wird aus dem Track berechnet.', 'norwegen-reise' ); ?></span>
		</p>
		<p>
			<label class="nb-label" for="nb_weather"><?php esc_html_e( 'Wetter', 'norwegen-reise' ); ?></label>
			<input type="text" id="nb_weather" name="nb_weather" class="widefat" value="<?php echo esc_attr( $weather ); ?>" placeholder="<?php esc_attr_e( '12 °C, Nieselregen', 'norwegen-reise' ); ?>" />
		</p>
		<?php
	}

	/**
	 * Renders the track box.
	 *
	 * @param WP_Post $post Current post.
	 */
	public static function render_track( $post ) {
		$track  = self::get_track( $post->ID );
		$source = get_post_meta( $post->ID, '_nb_track_source', true );
		?>
		<div class="nb-box">
			<?php if ( $track ) : ?>
				<p class="nb-track-status nb-track-status--ok">
					<?php
					printf(
						/* translators: 1: number of points, 2: distance, 3: file name */
						esc_html__( 'Track vorhanden: %1$d Punkte, %2$s km %3$s', 'norwegen-reise' ),
						count( $track ),
						esc_html( number_format_i18n( NB_Geo::track_length_km( $track ), 1 ) ),
						$source ? esc_html( '(' . $source . ')' ) : ''
					);
					?>
				</p>
			<?php else : ?>
				<p class="nb-track-status"><?php esc_html_e( 'Für diesen Tag ist noch keine Route hinterlegt.', 'norwegen-reise' ); ?></p>
			<?php endif; ?>

			<p>
				<label class="nb-label" for="nb_track_file"><?php esc_html_e( 'GPX- oder GeoJSON-Datei hochladen', 'norwegen-reise' ); ?></label>
				<input type="file" id="nb_track_file" name="nb_track_file" accept=".gpx,.geojson,.json" />
				<span class="description"><?php esc_html_e( 'Track aus Komoot, Strava, Garmin oder einer Handy-App. Der Track wird beim Speichern eingelesen und automatisch vereinfacht.', 'norwegen-reise' ); ?></span>
			</p>

			<?php if ( $track ) : ?>
				<p>
					<label>
						<input type="checkbox" name="nb_track_delete" value="1" />
						<?php esc_html_e( 'Vorhandenen Track beim Speichern löschen', 'norwegen-reise' ); ?>
					</label>
				</p>
			<?php endif; ?>

			<p>
				<label>
					<input type="checkbox" name="nb_track_waypoints" value="1" checked="checked" />
					<?php esc_html_e( 'Wegpunkte aus der Datei als Fähnchen übernehmen', 'norwegen-reise' ); ?>
				</label>
			</p>

			<div id="nb-track-map" class="nb-map" aria-label="<?php esc_attr_e( 'Vorschau der Tagesroute', 'norwegen-reise' ); ?>"></div>
			<p class="description"><?php esc_html_e( 'In der Karte kannst du Fähnchen verschieben. Die Vorschau zeigt den gespeicherten Stand.', 'norwegen-reise' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Renders the repeatable flags box.
	 *
	 * @param WP_Post $post Current post.
	 */
	public static function render_pins( $post ) {
		$pins = self::get_pins( $post->ID );
		?>
		<div class="nb-box nb-pins" id="nb-pins">
			<p class="description">
				<?php esc_html_e( 'Fähnchen markieren die besonderen Momente des Tages. Sie tauchen auf der großen Karte auf, sobald die Route beim Scrollen dort ankommt.', 'norwegen-reise' ); ?>
			</p>

			<div class="nb-pins__list" id="nb-pins-list">
				<?php
				foreach ( $pins as $index => $pin ) {
					self::render_pin_row( $index, $pin );
				}
				?>
			</div>

			<p>
				<button type="button" class="button button-secondary" id="nb-pins-add"><?php esc_html_e( '+ Fähnchen hinzufügen', 'norwegen-reise' ); ?></button>
			</p>

			<script type="text/html" id="nb-pin-template">
				<?php self::render_pin_row( '__index__', array() ); ?>
			</script>
		</div>
		<?php
	}

	/**
	 * Renders a single flag row.
	 *
	 * @param int|string $index Row index or the template placeholder.
	 * @param array      $pin   Pin data.
	 */
	private static function render_pin_row( $index, $pin ) {
		$pin = wp_parse_args(
			$pin,
			array(
				'title' => '',
				'text'  => '',
				'icon'  => 'flag',
				'lat'   => '',
				'lng'   => '',
				'image' => 0,
			)
		);

		$name  = 'nb_pins[' . $index . ']';
		$image = $pin['image'] ? wp_get_attachment_image_url( (int) $pin['image'], 'thumbnail' ) : '';
		?>
		<div class="nb-pin" data-index="<?php echo esc_attr( $index ); ?>">
			<div class="nb-pin__handle" aria-hidden="true">
				<span class="nb-pin__number"></span>
			</div>

			<div class="nb-pin__fields">
				<div class="nb-pin__row">
					<label class="nb-pin__field nb-pin__field--grow">
						<span class="nb-label"><?php esc_html_e( 'Titel', 'norwegen-reise' ); ?></span>
						<input type="text" name="<?php echo esc_attr( $name ); ?>[title]" value="<?php echo esc_attr( $pin['title'] ); ?>" placeholder="<?php esc_attr_e( 'Elch am Straßenrand', 'norwegen-reise' ); ?>" />
					</label>

					<label class="nb-pin__field">
						<span class="nb-label"><?php esc_html_e( 'Symbol', 'norwegen-reise' ); ?></span>
						<select name="<?php echo esc_attr( $name ); ?>[icon]">
							<?php foreach ( self::pin_icons() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $pin['icon'], $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</div>

				<div class="nb-pin__row">
					<label class="nb-pin__field nb-pin__field--grow">
						<span class="nb-label"><?php esc_html_e( 'Kurzbeschreibung', 'norwegen-reise' ); ?></span>
						<textarea name="<?php echo esc_attr( $name ); ?>[text]" rows="2" placeholder="<?php esc_attr_e( 'Ein bis zwei Sätze für das Popup auf der Karte.', 'norwegen-reise' ); ?>"><?php echo esc_textarea( $pin['text'] ); ?></textarea>
					</label>
				</div>

				<div class="nb-pin__row nb-pin__row--tight">
					<label class="nb-pin__field nb-pin__field--small">
						<span class="nb-label"><?php esc_html_e( 'Breite (lat)', 'norwegen-reise' ); ?></span>
						<input type="text" inputmode="decimal" class="nb-pin__lat" name="<?php echo esc_attr( $name ); ?>[lat]" value="<?php echo esc_attr( $pin['lat'] ); ?>" placeholder="62.1049" />
					</label>

					<label class="nb-pin__field nb-pin__field--small">
						<span class="nb-label"><?php esc_html_e( 'Länge (lng)', 'norwegen-reise' ); ?></span>
						<input type="text" inputmode="decimal" class="nb-pin__lng" name="<?php echo esc_attr( $name ); ?>[lng]" value="<?php echo esc_attr( $pin['lng'] ); ?>" placeholder="7.0055" />
					</label>

					<div class="nb-pin__field nb-pin__field--actions">
						<button type="button" class="button nb-pin__pick"><?php esc_html_e( 'Position in Karte wählen', 'norwegen-reise' ); ?></button>
					</div>
				</div>

				<div class="nb-pin__row nb-pin__row--tight">
					<div class="nb-pin__field nb-pin__field--media">
						<span class="nb-label"><?php esc_html_e( 'Bild im Popup', 'norwegen-reise' ); ?></span>
						<div class="nb-pin__media">
							<span class="nb-pin__preview"><?php if ( $image ) : ?><img src="<?php echo esc_url( $image ); ?>" alt="" /><?php endif; ?></span>
							<input type="hidden" class="nb-pin__image" name="<?php echo esc_attr( $name ); ?>[image]" value="<?php echo esc_attr( (int) $pin['image'] ); ?>" />
							<button type="button" class="button nb-pin__image-select"><?php esc_html_e( 'Bild wählen', 'norwegen-reise' ); ?></button>
							<button type="button" class="button-link nb-pin__image-clear"><?php esc_html_e( 'entfernen', 'norwegen-reise' ); ?></button>
						</div>
					</div>
				</div>
			</div>

			<button type="button" class="button-link nb-pin__remove" aria-label="<?php esc_attr_e( 'Fähnchen entfernen', 'norwegen-reise' ); ?>">&times;</button>
		</div>
		<?php
	}

	/**
	 * Saves all meta of a travel day.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$nonce = isset( $_POST[ self::NONCE . '_nonce' ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		self::save_facts( $post_id );
		$imported = self::save_track( $post_id );
		self::save_pins( $post_id, $imported );

		NB_Trip::flush_cache();
	}

	/**
	 * Stores the simple text fields.
	 *
	 * @param int $post_id Post ID.
	 */
	private static function save_facts( $post_id ) {
		$number = isset( $_POST['nb_day_number'] ) ? absint( wp_unslash( $_POST['nb_day_number'] ) ) : 0;
		$date   = isset( $_POST['nb_date'] ) ? sanitize_text_field( wp_unslash( $_POST['nb_date'] ) ) : '';
		$place  = isset( $_POST['nb_place'] ) ? sanitize_text_field( wp_unslash( $_POST['nb_place'] ) ) : '';
		$weather = isset( $_POST['nb_weather'] ) ? sanitize_text_field( wp_unslash( $_POST['nb_weather'] ) ) : '';

		$distance_raw = isset( $_POST['nb_distance_km'] ) ? sanitize_text_field( wp_unslash( $_POST['nb_distance_km'] ) ) : '';
		$distance     = '' === $distance_raw ? '' : (float) str_replace( ',', '.', $distance_raw );

		if ( $number ) {
			update_post_meta( $post_id, '_nb_day_number', $number );
		} else {
			delete_post_meta( $post_id, '_nb_day_number' );
		}

		if ( $date && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			update_post_meta( $post_id, '_nb_date', $date );
		} else {
			delete_post_meta( $post_id, '_nb_date' );
		}

		self::update_or_delete( $post_id, '_nb_place', $place );
		self::update_or_delete( $post_id, '_nb_weather', $weather );
		self::update_or_delete( $post_id, '_nb_distance_km', '' === $distance ? '' : (string) $distance );
	}

	/**
	 * Handles the track upload, deletion and distance fallback.
	 *
	 * @param int $post_id Post ID.
	 * @return array Waypoints that came with the uploaded file.
	 */
	private static function save_track( $post_id ) {
		$waypoints = array();

		if ( ! empty( $_POST['nb_track_delete'] ) ) {
			delete_post_meta( $post_id, '_nb_track' );
			delete_post_meta( $post_id, '_nb_track_source' );
		}

		if ( isset( $_FILES['nb_track_file'] ) && ! empty( $_FILES['nb_track_file']['tmp_name'] ) && UPLOAD_ERR_OK === (int) $_FILES['nb_track_file']['error'] ) {
			$tmp  = $_FILES['nb_track_file']['tmp_name']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$name = sanitize_file_name( wp_unslash( $_FILES['nb_track_file']['name'] ) );
			$type = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );

			if ( ! in_array( $type, array( 'gpx', 'geojson', 'json' ), true ) ) {
				self::add_notice( $post_id, __( 'Nur GPX-, GeoJSON- oder JSON-Dateien können importiert werden.', 'norwegen-reise' ), 'error' );
			} elseif ( ! is_uploaded_file( $tmp ) ) {
				self::add_notice( $post_id, __( 'Die hochgeladene Datei konnte nicht gelesen werden.', 'norwegen-reise' ), 'error' );
			} else {
				$contents = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				$parsed   = NB_GPX::parse( (string) $contents, $name );

				if ( is_wp_error( $parsed ) ) {
					self::add_notice( $post_id, $parsed->get_error_message(), 'error' );
				} else {
					if ( $parsed['track'] ) {
						$track = NB_Geo::limit_points( $parsed['track'] );

						update_post_meta( $post_id, '_nb_track', wp_json_encode( $track ) );
						update_post_meta( $post_id, '_nb_track_source', $name );

						self::add_notice(
							$post_id,
							sprintf(
								/* translators: 1: number of points, 2: distance */
								__( 'Track importiert: %1$d Punkte, %2$s km.', 'norwegen-reise' ),
								count( $track ),
								number_format_i18n( NB_Geo::track_length_km( $track ), 1 )
							),
							'success'
						);
					}

					if ( ! empty( $_POST['nb_track_waypoints'] ) ) {
						$waypoints = $parsed['waypoints'];
					}
				}
			}
		}

		// Without a manual distance, the track decides.
		$manual = get_post_meta( $post_id, '_nb_distance_km', true );

		if ( '' === $manual || null === $manual ) {
			$track = self::get_track( $post_id );

			if ( $track ) {
				update_post_meta( $post_id, '_nb_distance_km', (string) round( NB_Geo::track_length_km( $track ), 1 ) );
			}
		}

		return $waypoints;
	}

	/**
	 * Saves the flags, optionally merged with imported waypoints.
	 *
	 * @param int   $post_id   Post ID.
	 * @param array $waypoints Waypoints from an uploaded file.
	 */
	private static function save_pins( $post_id, $waypoints = array() ) {
		$pins = array();
		$raw  = isset( $_POST['nb_pins'] ) && is_array( $_POST['nb_pins'] ) ? wp_unslash( $_POST['nb_pins'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$lat = isset( $row['lat'] ) ? self::to_float( $row['lat'] ) : null;
			$lng = isset( $row['lng'] ) ? self::to_float( $row['lng'] ) : null;
			$title = isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '';

			// A flag without a position or a name is an empty row.
			if ( null === $lat || null === $lng || ( '' === $title && '' === trim( (string) ( $row['text'] ?? '' ) ) ) ) {
				continue;
			}

			$pins[] = array(
				'title' => $title,
				'text'  => isset( $row['text'] ) ? sanitize_textarea_field( $row['text'] ) : '',
				'icon'  => isset( $row['icon'] ) && array_key_exists( $row['icon'], self::pin_icons() ) ? $row['icon'] : 'flag',
				'lat'   => round( $lat, 6 ),
				'lng'   => round( $lng, 6 ),
				'image' => isset( $row['image'] ) ? absint( $row['image'] ) : 0,
			);
		}

		foreach ( $waypoints as $waypoint ) {
			if ( self::pin_exists( $pins, $waypoint['lng'], $waypoint['lat'] ) ) {
				continue;
			}

			$pins[] = array(
				'title' => $waypoint['title'] ? $waypoint['title'] : __( 'Wegpunkt', 'norwegen-reise' ),
				'text'  => '',
				'icon'  => 'flag',
				'lat'   => round( (float) $waypoint['lat'], 6 ),
				'lng'   => round( (float) $waypoint['lng'], 6 ),
				'image' => 0,
			);
		}

		if ( $pins ) {
			update_post_meta( $post_id, '_nb_pins', wp_slash( $pins ) );
		} else {
			delete_post_meta( $post_id, '_nb_pins' );
		}
	}

	/**
	 * Checks whether a position is already taken by a flag.
	 *
	 * @param array $pins Existing pins.
	 * @param float $lng  Longitude.
	 * @param float $lat  Latitude.
	 * @return bool
	 */
	private static function pin_exists( $pins, $lng, $lat ) {
		foreach ( $pins as $pin ) {
			if ( NB_Geo::distance_km( array( $pin['lng'], $pin['lat'] ), array( $lng, $lat ) ) < 0.03 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Parses a coordinate that may use a comma as decimal separator.
	 *
	 * @param string $value Raw value.
	 * @return float|null
	 */
	private static function to_float( $value ) {
		$value = trim( str_replace( ',', '.', (string) $value ) );

		if ( '' === $value || ! is_numeric( $value ) ) {
			return null;
		}

		return (float) $value;
	}

	/**
	 * Writes or removes a meta value depending on emptiness.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param string $value   Value.
	 */
	private static function update_or_delete( $post_id, $key, $value ) {
		if ( '' === $value ) {
			delete_post_meta( $post_id, $key );
			return;
		}

		update_post_meta( $post_id, $key, $value );
	}

	/**
	 * Queues an admin notice for the next page load.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $message Message.
	 * @param string $type    success|error.
	 */
	private static function add_notice( $post_id, $message, $type = 'success' ) {
		$notices   = get_post_meta( $post_id, '_nb_notices', true );
		$notices   = is_array( $notices ) ? $notices : array();
		$notices[] = array(
			'message' => $message,
			'type'    => $type,
		);

		update_post_meta( $post_id, '_nb_notices', wp_slash( $notices ) );
	}

	/**
	 * Returns the stored track of a day.
	 *
	 * @param int $post_id Post ID.
	 * @return array List of [lng, lat] pairs.
	 */
	public static function get_track( $post_id ) {
		$raw = get_post_meta( $post_id, '_nb_track', true );

		if ( ! $raw ) {
			return array();
		}

		$track = is_array( $raw ) ? $raw : json_decode( $raw, true );

		if ( ! is_array( $track ) ) {
			return array();
		}

		$clean = array();

		foreach ( $track as $pair ) {
			if ( is_array( $pair ) && isset( $pair[0], $pair[1] ) ) {
				$clean[] = array( (float) $pair[0], (float) $pair[1] );
			}
		}

		return $clean;
	}

	/**
	 * Returns the flags of a day.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function get_pins( $post_id ) {
		$pins = get_post_meta( $post_id, '_nb_pins', true );

		if ( ! is_array( $pins ) ) {
			return array();
		}

		$clean = array();

		foreach ( $pins as $pin ) {
			if ( ! isset( $pin['lat'], $pin['lng'] ) ) {
				continue;
			}

			$clean[] = wp_parse_args(
				$pin,
				array(
					'title' => '',
					'text'  => '',
					'icon'  => 'flag',
					'image' => 0,
				)
			);
		}

		return $clean;
	}
}
