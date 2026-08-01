<?php
/**
 * Erzeugt fünf Beispiel-Reisetage, damit sich die Karte sofort testen lässt.
 *
 * Aufruf:  wp eval-file tools/demo-data.php
 * Löschen: wp eval-file tools/demo-data.php -- --delete
 *
 * Die Tage sind als Demo markiert und werden bei jedem Lauf neu angelegt.
 * Die Tracks sind aus echten Ortskoordinaten interpoliert, also plausibel,
 * aber keine echten GPS-Aufzeichnungen.
 *
 * @package NorwegenReise
 */

defined( 'ABSPATH' ) || exit;

/**
 * Gibt eine Zeile aus - über WP-CLI, wenn vorhanden, sonst als Text.
 *
 * @param string $message Meldung.
 * @param string $level   log|success|warning|error.
 */
function nb_demo_log( $message, $level = 'log' ) {
	if ( class_exists( 'WP_CLI' ) ) {
		call_user_func( array( 'WP_CLI', 'error' === $level ? 'warning' : $level ), $message );
	} else {
		echo esc_html( $message ) . "\n";
	}

	if ( 'error' === $level ) {
		exit( 1 );
	}
}

if ( ! class_exists( 'NB_Post_Types' ) ) {
	nb_demo_log( 'Das Plugin „Norwegen Reise“ ist nicht aktiv. Erst aktivieren: wp plugin activate norwegen-reise', 'error' );
}

// --delete kommt entweder von WP-CLI ($args) oder von der Kommandozeile.
$nb_demo_delete = ( isset( $args ) && in_array( '--delete', (array) $args, true ) )
	|| ( isset( $argv ) && in_array( '--delete', (array) $argv, true ) );

/**
 * Baut aus Stützpunkten eine Linie, die wie eine gefahrene Straße aussieht.
 *
 * @param array $waypoints Liste von [lng, lat].
 * @param int   $per_leg   Punkte je Teilstück.
 * @return array
 */
function nb_demo_line( $waypoints, $per_leg = 40 ) {
	$line = array();

	for ( $i = 1; $i < count( $waypoints ); $i++ ) {
		$a = $waypoints[ $i - 1 ];
		$b = $waypoints[ $i ];

		$dx = $b[0] - $a[0];
		$dy = $b[1] - $a[1];

		for ( $step = 0; $step <= $per_leg; $step++ ) {
			$t = $step / $per_leg;

			// Eine sanfte Auslenkung quer zur Fahrtrichtung: Straßen sind nicht gerade.
			$swing = sin( $t * M_PI ) * 0.06 + sin( $t * M_PI * 5 ) * 0.012;

			$line[] = array(
				round( $a[0] + $dx * $t - $dy * $swing, 6 ),
				round( $a[1] + $dy * $t + $dx * $swing, 6 ),
			);
		}
	}

	return $line;
}

$days = array(
	array(
		'number'  => 1,
		'date'    => '2026-06-27',
		'title'   => 'Über Schweden ins Licht',
		'place'   => 'Oslo → Lillehammer',
		'weather' => '19 °C, Sonne mit Wolken',
		'route'   => array( array( 10.7522, 59.9139 ), array( 10.6800, 60.1900 ), array( 10.8700, 60.7900 ), array( 10.4662, 61.1153 ) ),
		'excerpt' => 'Ankunft in Oslo, ein erster Kaffee am Hafen — und dann immer nach Norden, bis die Häuser rot werden und die Wälder kein Ende nehmen.',
		'pins'    => array(
			array( 'title' => 'Vigelandpark', 'text' => 'Zweihundert Skulpturen und kein Mensch, der es eilig hat.', 'icon' => 'photo', 'lng' => 10.7008, 'lat' => 59.9270 ),
			array( 'title' => 'Erster Elch-Verdacht', 'text' => 'Etwas Großes, Braunes am Waldrand. Beweisfoto: unscharf.', 'icon' => 'wildlife', 'lng' => 10.7900, 'lat' => 60.6100 ),
		),
	),
	array(
		'number'  => 2,
		'date'    => '2026-06-28',
		'title'   => 'Der Tag, an dem die Berge anfingen',
		'place'   => 'Lillehammer → Otta → Geiranger',
		'weather' => '13 °C, Nieselregen auf der Passhöhe',
		'route'   => array( array( 10.4662, 61.1153 ), array( 9.5390, 61.7710 ), array( 8.5700, 62.0400 ), array( 7.2050, 62.1010 ) ),
		'excerpt' => 'Dreihundert Kilometer, auf denen sich die Landschaft dreimal komplett neu erfindet. Am Ende steht man über dem Geirangerfjord und sagt erst mal gar nichts.',
		'pins'    => array(
			array( 'title' => 'Maihaugen', 'text' => 'Freilichtmuseum im Regen — überraschend die richtige Entscheidung.', 'icon' => 'star', 'lng' => 10.4750, 'lat' => 61.1120 ),
			array( 'title' => 'Dalsnibba, 1.500 m', 'text' => 'Schnee im Juni, und darunter der Fjord wie eine blaue Rinne.', 'icon' => 'view', 'lng' => 7.2670, 'lat' => 62.0430 ),
			array( 'title' => 'Sieben Schwestern', 'text' => 'Der Wasserfall stürzt in sieben Strähnen. Gezählt haben wir sechs.', 'icon' => 'water', 'lng' => 7.1400, 'lat' => 62.1000 ),
		),
	),
	array(
		'number'  => 3,
		'date'    => '2026-06-29',
		'title'   => 'Elf Kehren und ein Adlerblick',
		'place'   => 'Geiranger → Trollstigen → Åndalsnes',
		'weather' => '11 °C, Wolken tief im Tal',
		'route'   => array( array( 7.2050, 62.1010 ), array( 7.1000, 62.2500 ), array( 7.6710, 62.4580 ), array( 7.6870, 62.5670 ) ),
		'excerpt' => 'Adlerstraße hinauf, Fähre über den Fjord, Trollstigen hinunter. Ein Tag, der fast nur aus Kurven besteht.',
		'pins'    => array(
			array( 'title' => 'Ørnesvingen', 'text' => 'Der Adlerblick. Von hier sieht der Fjord aus wie gemalt.', 'icon' => 'view', 'lng' => 7.1800, 'lat' => 62.1250 ),
			array( 'title' => 'Fähre nach Eidsdal', 'text' => 'Zwanzig Minuten Motorbrummen und Möwen.', 'icon' => 'camp', 'lng' => 7.1200, 'lat' => 62.2600 ),
			array( 'title' => 'Trollstigen-Plattform', 'text' => 'Die Straße unter uns wie ein hingeworfenes Band.', 'icon' => 'hike', 'lng' => 7.6710, 'lat' => 62.4580 ),
		),
	),
	array(
		'number'  => 4,
		'date'    => '2026-06-30',
		'title'   => 'Jugendstil und Stockfisch',
		'place'   => 'Åndalsnes → Ålesund',
		'weather' => '16 °C, Sonne am Nachmittag',
		'route'   => array( array( 7.6870, 62.5670 ), array( 7.1500, 62.5500 ), array( 6.5000, 62.4900 ), array( 6.1495, 62.4722 ) ),
		'excerpt' => 'Eine Stadt, die nach einem Brand komplett neu gebaut wurde — und zwar in Jugendstil. Abends 418 Stufen hoch auf den Aksla.',
		'pins'    => array(
			array( 'title' => 'Aksla-Treppe', 'text' => '418 Stufen. Oben liegt Ålesund auf seinen Inseln wie ausgelegt.', 'icon' => 'hike', 'lng' => 6.1600, 'lat' => 62.4740 ),
			array( 'title' => 'Fischsuppe am Hafen', 'text' => 'Sahnig, mit Safran. Zweimal bestellt.', 'icon' => 'food', 'lng' => 6.1520, 'lat' => 62.4715 ),
		),
	),
	array(
		'number'  => 5,
		'date'    => '2026-07-01',
		'title'   => 'Sieben Fähren bis Bergen',
		'place'   => 'Ålesund → Førde → Bergen',
		'weather' => '14 °C, wechselhaft',
		'route'   => array( array( 6.1495, 62.4722 ), array( 6.0000, 62.0000 ), array( 5.8560, 61.4520 ), array( 5.5000, 60.9000 ), array( 5.3221, 60.3913 ) ),
		'excerpt' => 'Der längste Tag der Reise. Fjord, Fähre, Tunnel, wieder Fjord — und abends die Bryggen im Abendlicht.',
		'pins'    => array(
			array( 'title' => 'Kaffee auf dem Autodeck', 'text' => 'Der beste Kaffee ist der, den man im Stehen trinkt.', 'icon' => 'camp', 'lng' => 6.0200, 'lat' => 62.1000 ),
			array( 'title' => 'Bryggen', 'text' => 'Schiefe Holzhäuser, seit 900 Jahren im selben Winkel.', 'icon' => 'star', 'lng' => 5.3240, 'lat' => 60.3975 ),
		),
	),
);

// Alte Demo-Tage entfernen.
$existing = get_posts(
	array(
		'post_type'      => NB_Post_Types::DAY,
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'meta_key'       => '_nb_demo',
		'meta_value'     => '1',
		'fields'         => 'ids',
	)
);

foreach ( $existing as $id ) {
	wp_delete_post( $id, true );
}

if ( $existing ) {
	nb_demo_log( sprintf( '%d alte Demo-Tage entfernt.', count( $existing ) ) );
}

if ( $nb_demo_delete ) {
	NB_Trip::flush_cache();
	nb_demo_log( 'Demo-Daten gelöscht.', 'success' );

	return;
}

foreach ( $days as $day ) {
	$track = nb_demo_line( $day['route'] );

	$content  = '<!-- wp:paragraph --><p>' . $day['excerpt'] . '</p><!-- /wp:paragraph -->';
	$content .= '<!-- wp:heading --><h2>Wie der Tag lief</h2><!-- /wp:heading -->';
	$content .= '<!-- wp:paragraph --><p>Hier steht später der Text, den ihr abends schreibt. '
		. 'Fotos, Videos und Galerien fügt ihr einfach als Block ein — die Bilder bekommen im Frontend automatisch eine Lightbox.</p><!-- /wp:paragraph -->';
	$content .= '<!-- wp:quote --><blockquote class="wp-block-quote"><p>Ein Satz, der von diesem Tag hängen geblieben ist.</p></blockquote><!-- /wp:quote -->';

	$post_id = wp_insert_post(
		array(
			'post_type'    => NB_Post_Types::DAY,
			'post_status'  => 'publish',
			'post_title'   => $day['title'],
			'post_excerpt' => $day['excerpt'],
			'post_content' => $content,
			'post_date'    => $day['date'] . ' 20:00:00',
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		nb_demo_log( 'Tag ' . $day['number'] . ': ' . $post_id->get_error_message(), 'warning' );

		continue;
	}

	$pins = array();

	foreach ( $day['pins'] as $pin ) {
		$pins[] = array(
			'title' => $pin['title'],
			'text'  => $pin['text'],
			'icon'  => $pin['icon'],
			'lat'   => $pin['lat'],
			'lng'   => $pin['lng'],
			'image' => 0,
		);
	}

	update_post_meta( $post_id, '_nb_day_number', $day['number'] );
	update_post_meta( $post_id, '_nb_date', $day['date'] );
	update_post_meta( $post_id, '_nb_place', $day['place'] );
	update_post_meta( $post_id, '_nb_weather', $day['weather'] );
	update_post_meta( $post_id, '_nb_track', wp_json_encode( $track ) );
	update_post_meta( $post_id, '_nb_track_source', 'demo.gpx' );
	update_post_meta( $post_id, '_nb_distance_km', (string) round( NB_Geo::track_length_km( $track ), 1 ) );
	update_post_meta( $post_id, '_nb_pins', wp_slash( $pins ) );
	update_post_meta( $post_id, '_nb_demo', '1' );

	nb_demo_log(
		sprintf(
			'Tag %d angelegt: %s (%d Punkte, %s km, %d Fähnchen)',
			$day['number'],
			$day['title'],
			count( $track ),
			number_format_i18n( NB_Geo::track_length_km( $track ), 1 ),
			count( $pins )
		)
	);
}

NB_Trip::flush_cache();

$data = NB_Trip::get_data( true );

nb_demo_log(
	sprintf(
		'%d Tage, %s km, %d Fähnchen angelegt.',
		$data['trip']['daysOnline'],
		number_format_i18n( $data['trip']['totalKm'], 1 ),
		$data['trip']['pinCount']
	),
	'success'
);
