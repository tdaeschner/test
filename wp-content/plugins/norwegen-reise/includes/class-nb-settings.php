<?php
/**
 * Settings page: trip facts, map look and the planned route.
 *
 * @package NorwegenReise
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles the option page under "Norwegen".
 */
class NB_Settings {

	const OPTION = 'nb_settings';
	const GROUP  = 'nb_settings_group';

	/**
	 * Hooks the settings page.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_nb_upload_planned', array( __CLASS__, 'handle_planned_upload' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Loads the small stylesheet the settings page needs.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function enqueue( $hook ) {
		if ( false === strpos( $hook, 'nb-settings' ) ) {
			return;
		}

		wp_enqueue_style( 'nb-admin', NB_URL . 'assets/admin.css', array(), NB_VERSION );
	}

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'trip_title'    => __( 'Norwegen', 'norwegen-reise' ),
			'trip_subtitle' => __( 'Siebzehn Tage zwischen Fjorden, Pässen und Mitternachtslicht', 'norwegen-reise' ),
			'start_date'    => '',
			'days_planned'  => 17,
			'basemap'       => 'dark',
			'basemaps_offered' => array( 'dark', 'light', 'satellite', 'topo' ),
			'style_url'     => '',
			'maptiler_key'  => '',
			'terrain'       => 0,
			'center_lng'    => 8.4689,
			'center_lat'    => 62.4720,
			'zoom'          => 4.4,
		);
	}

	/**
	 * Reads one setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$settings = wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );

		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	}

	/**
	 * Reads all settings.
	 *
	 * @return array
	 */
	public static function all() {
		return wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
	}

	/**
	 * Available keyless base maps.
	 *
	 * @return array
	 */
	public static function basemaps() {
		return array(
			'dark'      => array(
				'label'       => __( 'Dunkel (CARTO Dark Matter)', 'norwegen-reise' ),
				'tiles'       => array(
					'https://a.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}@2x.png',
					'https://b.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}@2x.png',
					'https://c.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}@2x.png',
				),
				'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>, &copy; <a href="https://carto.com/attributions">CARTO</a>',
				'tileSize'    => 512,
				'maxzoom'     => 20,
				'swatch'      => '#141d2b',
			),
			'light'     => array(
				'label'       => __( 'Hell (CARTO Positron)', 'norwegen-reise' ),
				'tiles'       => array(
					'https://a.basemaps.cartocdn.com/light_all/{z}/{x}/{y}@2x.png',
					'https://b.basemaps.cartocdn.com/light_all/{z}/{x}/{y}@2x.png',
					'https://c.basemaps.cartocdn.com/light_all/{z}/{x}/{y}@2x.png',
				),
				'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>, &copy; <a href="https://carto.com/attributions">CARTO</a>',
				'tileSize'    => 512,
				'maxzoom'     => 20,
				'swatch'      => '#e8eaed',
			),
			'satellite' => array(
				'label'       => __( 'Satellit (Esri World Imagery)', 'norwegen-reise' ),
				'tiles'       => array( 'https://services.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}' ),
				'attribution' => 'Esri, Maxar, Earthstar Geographics',
				'tileSize'    => 256,
				'maxzoom'     => 19,
				'swatch'      => '#3f5d3a',
			),
			'topo'      => array(
				'label'       => __( 'Topografisch (OpenTopoMap)', 'norwegen-reise' ),
				'tiles'       => array( 'https://a.tile.opentopomap.org/{z}/{x}/{y}.png' ),
				'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>, <a href="https://opentopomap.org">OpenTopoMap</a> (CC-BY-SA)',
				'tileSize'    => 256,
				'maxzoom'     => 17,
				'swatch'      => '#d9d2c0',
			),
		);
	}

	/**
	 * Map configuration handed to the JavaScript side.
	 *
	 * @return array
	 */
	public static function style_config() {
		$settings  = self::all();
		$available = self::basemaps();

		$offered = array();

		foreach ( (array) $settings['basemaps_offered'] as $offered_key ) {
			if ( isset( $available[ $offered_key ] ) ) {
				$offered[] = $offered_key;
			}
		}

		if ( ! $offered ) {
			$offered = array( 'dark' );
		}

		$key = in_array( $settings['basemap'], $offered, true ) ? $settings['basemap'] : $offered[0];

		// Jeder angebotene Stil wird als eigene Ebene geladen; umgeschaltet wird
		// über die Sichtbarkeit, damit die Route dabei stehen bleibt.
		$basemaps = array();

		foreach ( $offered as $offered_key ) {
			$basemap = $available[ $offered_key ];

			$basemaps[] = array(
				'key'         => $offered_key,
				'label'       => $basemap['label'],
				'tiles'       => $basemap['tiles'],
				'tileSize'    => $basemap['tileSize'],
				'maxzoom'     => $basemap['maxzoom'],
				'attribution' => $basemap['attribution'],
				'dark'        => in_array( $offered_key, array( 'dark', 'satellite' ), true ),
				// Luftbilder und topografische Karten sind unruhig; die Route
				// braucht dort mehr Kontrast, um überhaupt aufzufallen.
				'busy'        => in_array( $offered_key, array( 'satellite', 'topo' ), true ),
				'swatch'      => $basemap['swatch'],
			);
		}

		$active = $available[ $key ];

		return array(
			'basemap'     => $key,
			'basemaps'    => $basemaps,
			'tiles'       => $active['tiles'],
			'tileSize'    => $active['tileSize'],
			'maxzoom'     => $active['maxzoom'],
			'attribution' => $active['attribution'],
			'styleUrl'    => $settings['style_url'],
			'maptilerKey' => $settings['maptiler_key'],
			'terrain'     => (bool) $settings['terrain'] && $settings['maptiler_key'],
			'center'      => array( (float) $settings['center_lng'], (float) $settings['center_lat'] ),
			'zoom'        => (float) $settings['zoom'],
			'dark'        => in_array( $key, array( 'dark', 'satellite' ), true ),
		);
	}

	/**
	 * Fallback centre for the admin picker.
	 *
	 * @return array
	 */
	public static function default_center() {
		return array( (float) self::get( 'center_lng' ), (float) self::get( 'center_lat' ) );
	}

	/**
	 * Fallback bounds covering Norway.
	 *
	 * @return array
	 */
	public static function default_bounds() {
		return array( array( 4.5, 57.9 ), array( 31.2, 71.3 ) );
	}

	/**
	 * The optional planned route drawn as a dashed line.
	 *
	 * @return array List of [lng, lat] pairs.
	 */
	public static function planned_route() {
		$raw = get_option( 'nb_planned_route', '' );

		if ( ! $raw ) {
			return array();
		}

		$track = json_decode( $raw, true );

		return is_array( $track ) ? $track : array();
	}

	/**
	 * Adds the settings page below the post type menu.
	 */
	public static function add_page() {
		add_submenu_page(
			'edit.php?post_type=' . NB_Post_Types::DAY,
			__( 'Reise-Einstellungen', 'norwegen-reise' ),
			__( 'Einstellungen', 'norwegen-reise' ),
			'manage_options',
			'nb-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Registers the option with its sanitiser.
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Sanitises the settings form.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$clean    = array();

		$clean['trip_title']    = isset( $input['trip_title'] ) ? sanitize_text_field( $input['trip_title'] ) : $defaults['trip_title'];
		$clean['trip_subtitle'] = isset( $input['trip_subtitle'] ) ? sanitize_text_field( $input['trip_subtitle'] ) : '';
		$clean['start_date']    = isset( $input['start_date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $input['start_date'] ) ? $input['start_date'] : '';
		$clean['days_planned']  = isset( $input['days_planned'] ) ? max( 1, absint( $input['days_planned'] ) ) : $defaults['days_planned'];
		$available = self::basemaps();
		$offered   = array();

		foreach ( (array) ( isset( $input['basemaps_offered'] ) ? $input['basemaps_offered'] : array() ) as $key ) {
			if ( array_key_exists( $key, $available ) && ! in_array( $key, $offered, true ) ) {
				$offered[] = $key;
			}
		}

		// Ohne Auswahl bliebe die Karte leer.
		if ( ! $offered ) {
			$offered = array( 'dark' );
		}

		$clean['basemaps_offered'] = $offered;

		$default = isset( $input['basemap'] ) && array_key_exists( $input['basemap'], $available ) ? $input['basemap'] : 'dark';

		// Der Standard muss zu den angebotenen Stilen gehören.
		$clean['basemap'] = in_array( $default, $offered, true ) ? $default : $offered[0];
		$clean['style_url']     = isset( $input['style_url'] ) ? esc_url_raw( trim( $input['style_url'] ) ) : '';
		$clean['maptiler_key']  = isset( $input['maptiler_key'] ) ? sanitize_text_field( $input['maptiler_key'] ) : '';
		$clean['terrain']       = empty( $input['terrain'] ) ? 0 : 1;
		$clean['center_lng']    = isset( $input['center_lng'] ) ? (float) str_replace( ',', '.', $input['center_lng'] ) : $defaults['center_lng'];
		$clean['center_lat']    = isset( $input['center_lat'] ) ? (float) str_replace( ',', '.', $input['center_lat'] ) : $defaults['center_lat'];
		$clean['zoom']          = isset( $input['zoom'] ) ? min( 18, max( 1, (float) str_replace( ',', '.', $input['zoom'] ) ) ) : $defaults['zoom'];

		NB_Trip::flush_cache();

		return $clean;
	}

	/**
	 * Handles the upload of the planned route.
	 */
	public static function handle_planned_upload() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Fehlende Berechtigung.', 'norwegen-reise' ) );
		}

		check_admin_referer( 'nb_planned_route' );

		$redirect = add_query_arg(
			array(
				'post_type' => NB_Post_Types::DAY,
				'page'      => 'nb-settings',
			),
			admin_url( 'edit.php' )
		);

		if ( ! empty( $_POST['nb_planned_delete'] ) ) {
			delete_option( 'nb_planned_route' );
			NB_Trip::flush_cache();

			wp_safe_redirect( add_query_arg( 'nb_message', 'planned-deleted', $redirect ) );
			exit;
		}

		if ( empty( $_FILES['nb_planned_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['nb_planned_file']['tmp_name'] ) ) {
			wp_safe_redirect( add_query_arg( 'nb_message', 'planned-error', $redirect ) );
			exit;
		}

		$name     = sanitize_file_name( wp_unslash( $_FILES['nb_planned_file']['name'] ) );
		$contents = file_get_contents( $_FILES['nb_planned_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.Security.ValidatedSanitizedInput
		$parsed   = NB_GPX::parse( (string) $contents, $name );

		if ( is_wp_error( $parsed ) || empty( $parsed['track'] ) ) {
			wp_safe_redirect( add_query_arg( 'nb_message', 'planned-error', $redirect ) );
			exit;
		}

		$track = NB_Geo::limit_points( $parsed['track'], 2000 );

		update_option( 'nb_planned_route', wp_json_encode( $track ), false );
		NB_Trip::flush_cache();

		wp_safe_redirect( add_query_arg( 'nb_message', 'planned-saved', $redirect ) );
		exit;
	}

	/**
	 * Renders the settings page.
	 */
	public static function render_page() {
		$settings = self::all();
		$planned  = self::planned_route();
		$message  = isset( $_GET['nb_message'] ) ? sanitize_key( wp_unslash( $_GET['nb_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<div class="wrap nb-settings">
			<h1><?php esc_html_e( 'Reise-Einstellungen', 'norwegen-reise' ); ?></h1>

			<?php if ( 'planned-saved' === $message ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Geplante Route gespeichert.', 'norwegen-reise' ); ?></p></div>
			<?php elseif ( 'planned-deleted' === $message ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Geplante Route entfernt.', 'norwegen-reise' ); ?></p></div>
			<?php elseif ( 'planned-error' === $message ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Die Datei konnte nicht gelesen werden.', 'norwegen-reise' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<h2 class="title"><?php esc_html_e( 'Die Reise', 'norwegen-reise' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="nb_trip_title"><?php esc_html_e( 'Titel', 'norwegen-reise' ); ?></label></th>
						<td><input type="text" class="regular-text" id="nb_trip_title" name="<?php echo esc_attr( self::OPTION ); ?>[trip_title]" value="<?php echo esc_attr( $settings['trip_title'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="nb_trip_subtitle"><?php esc_html_e( 'Untertitel', 'norwegen-reise' ); ?></label></th>
						<td><input type="text" class="large-text" id="nb_trip_subtitle" name="<?php echo esc_attr( self::OPTION ); ?>[trip_subtitle]" value="<?php echo esc_attr( $settings['trip_subtitle'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="nb_start_date"><?php esc_html_e( 'Abreise', 'norwegen-reise' ); ?></label></th>
						<td><input type="date" id="nb_start_date" name="<?php echo esc_attr( self::OPTION ); ?>[start_date]" value="<?php echo esc_attr( $settings['start_date'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="nb_days_planned"><?php esc_html_e( 'Geplante Reisetage', 'norwegen-reise' ); ?></label></th>
						<td>
							<input type="number" min="1" id="nb_days_planned" name="<?php echo esc_attr( self::OPTION ); ?>[days_planned]" value="<?php echo esc_attr( $settings['days_planned'] ); ?>" class="small-text" />
							<p class="description"><?php esc_html_e( 'Wird als „Tag 8 von 17“ angezeigt.', 'norwegen-reise' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Karte', 'norwegen-reise' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Kartenstile', 'norwegen-reise' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Angebotene Kartenstile und Standard', 'norwegen-reise' ); ?></legend>

								<table class="nb-basemap-table">
									<thead>
										<tr>
											<th scope="col"><?php esc_html_e( 'Anbieten', 'norwegen-reise' ); ?></th>
											<th scope="col"><?php esc_html_e( 'Standard', 'norwegen-reise' ); ?></th>
											<th scope="col"><?php esc_html_e( 'Stil', 'norwegen-reise' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( self::basemaps() as $key => $basemap ) : ?>
											<tr>
												<td>
													<input type="checkbox" id="nb_offer_<?php echo esc_attr( $key ); ?>"
														name="<?php echo esc_attr( self::OPTION ); ?>[basemaps_offered][]"
														value="<?php echo esc_attr( $key ); ?>"
														<?php checked( in_array( $key, (array) $settings['basemaps_offered'], true ) ); ?> />
												</td>
												<td>
													<input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[basemap]"
														value="<?php echo esc_attr( $key ); ?>" <?php checked( $settings['basemap'], $key ); ?> />
												</td>
												<td>
													<label for="nb_offer_<?php echo esc_attr( $key ); ?>">
														<span class="nb-basemap-swatch" style="background: <?php echo esc_attr( $basemap['swatch'] ); ?>"></span>
														<?php echo esc_html( $basemap['label'] ); ?>
													</label>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</fieldset>

							<p class="description">
								<?php esc_html_e( 'Angehakte Stile können Besucher über einen Schalter in der Karte umschalten. Ist nur einer angehakt, erscheint kein Schalter. Der Standard bestimmt, womit die Karte startet.', 'norwegen-reise' ); ?>
							</p>
							<p class="description">
								<?php esc_html_e( 'Alle Stile funktionieren ohne Schlüssel. Die Kacheln werden vom jeweiligen Anbieter geladen – bitte im Datenschutzhinweis erwähnen.', 'norwegen-reise' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="nb_style_url"><?php esc_html_e( 'Eigene Style-URL', 'norwegen-reise' ); ?></label></th>
						<td>
							<input type="url" class="large-text code" id="nb_style_url" name="<?php echo esc_attr( self::OPTION ); ?>[style_url]" value="<?php echo esc_attr( $settings['style_url'] ); ?>" placeholder="https://api.maptiler.com/maps/outdoor/style.json?key=…" />
							<p class="description"><?php esc_html_e( 'Optional. Überschreibt den Kartenstil oben, z. B. mit einem Vektorstil von MapTiler.', 'norwegen-reise' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="nb_maptiler_key"><?php esc_html_e( 'MapTiler-Schlüssel', 'norwegen-reise' ); ?></label></th>
						<td>
							<input type="text" class="regular-text code" id="nb_maptiler_key" name="<?php echo esc_attr( self::OPTION ); ?>[maptiler_key]" value="<?php echo esc_attr( $settings['maptiler_key'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Nur nötig für das 3D-Gelände.', 'norwegen-reise' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( '3D-Gelände', 'norwegen-reise' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[terrain]" value="1" <?php checked( $settings['terrain'], 1 ); ?> />
								<?php esc_html_e( 'Berge plastisch darstellen (benötigt MapTiler-Schlüssel)', 'norwegen-reise' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Startansicht', 'norwegen-reise' ); ?></th>
						<td>
							<label><?php esc_html_e( 'Länge', 'norwegen-reise' ); ?>
								<input type="text" class="small-text" name="<?php echo esc_attr( self::OPTION ); ?>[center_lng]" value="<?php echo esc_attr( $settings['center_lng'] ); ?>" />
							</label>
							<label><?php esc_html_e( 'Breite', 'norwegen-reise' ); ?>
								<input type="text" class="small-text" name="<?php echo esc_attr( self::OPTION ); ?>[center_lat]" value="<?php echo esc_attr( $settings['center_lat'] ); ?>" />
							</label>
							<label><?php esc_html_e( 'Zoom', 'norwegen-reise' ); ?>
								<input type="text" class="small-text" name="<?php echo esc_attr( self::OPTION ); ?>[zoom]" value="<?php echo esc_attr( $settings['zoom'] ); ?>" />
							</label>
							<p class="description"><?php esc_html_e( 'Wird nur genutzt, solange noch keine Route existiert.', 'norwegen-reise' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr />

			<h2 class="title"><?php esc_html_e( 'Geplante Route', 'norwegen-reise' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Optional: die komplette Rundreise als GPX. Sie liegt als feine gestrichelte Linie unter der Route – so sieht man von Anfang an, wohin es noch geht.', 'norwegen-reise' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="nb_upload_planned" />
				<?php wp_nonce_field( 'nb_planned_route' ); ?>

				<p>
					<?php if ( $planned ) : ?>
						<strong>
							<?php
							printf(
								/* translators: 1: number of points, 2: distance */
								esc_html__( 'Hinterlegt: %1$d Punkte, %2$s km.', 'norwegen-reise' ),
								count( $planned ),
								esc_html( number_format_i18n( NB_Geo::track_length_km( $planned ), 1 ) )
							);
							?>
						</strong>
					<?php else : ?>
						<em><?php esc_html_e( 'Noch keine geplante Route hinterlegt.', 'norwegen-reise' ); ?></em>
					<?php endif; ?>
				</p>

				<p><input type="file" name="nb_planned_file" accept=".gpx,.geojson,.json" /></p>

				<?php if ( $planned ) : ?>
					<p><label><input type="checkbox" name="nb_planned_delete" value="1" /> <?php esc_html_e( 'Geplante Route löschen', 'norwegen-reise' ); ?></label></p>
				<?php endif; ?>

				<?php submit_button( __( 'Geplante Route speichern', 'norwegen-reise' ), 'secondary' ); ?>
			</form>
		</div>
		<?php
	}
}
