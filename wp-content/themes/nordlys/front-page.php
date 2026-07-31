<?php
/**
 * Front page: the trip hero plus the scroll driven map.
 *
 * @package Nordlys
 */

defined( 'ABSPATH' ) || exit;

get_header();

$days  = nordlys_plugin_active() ? nordlys_get_days() : array();
$stats = nordlys_trip_stats();
$title = nordlys_plugin_active() ? NB_Settings::get( 'trip_title' ) : get_bloginfo( 'name' );
$sub   = nordlys_plugin_active() ? NB_Settings::get( 'trip_subtitle' ) : get_bloginfo( 'description' );
?>

<section class="nb-hero">
	<div class="nb-hero__aurora" aria-hidden="true"></div>

	<div class="nb-hero__inner">
		<p class="nb-hero__kicker">
			<?php
			printf(
				/* translators: 1: days online, 2: days planned */
				esc_html__( 'Tag %1$d von %2$d', 'nordlys' ),
				(int) $stats['daysOnline'],
				(int) $stats['daysPlanned']
			);
			?>
		</p>

		<h1 class="nb-hero__title"><?php echo esc_html( $title ); ?></h1>

		<?php if ( $sub ) : ?>
			<p class="nb-hero__subtitle"><?php echo esc_html( $sub ); ?></p>
		<?php endif; ?>

		<ul class="nb-hero__stats">
			<li>
				<span class="nb-hero__stat-value"><?php echo esc_html( number_format_i18n( (int) $stats['daysOnline'] ) ); ?></span>
				<span class="nb-hero__stat-label"><?php esc_html_e( 'Tage erzählt', 'nordlys' ); ?></span>
			</li>
			<li>
				<span class="nb-hero__stat-value"><?php echo esc_html( number_format_i18n( (float) $stats['totalKm'], 0 ) ); ?></span>
				<span class="nb-hero__stat-label"><?php esc_html_e( 'Kilometer', 'nordlys' ); ?></span>
			</li>
			<li>
				<span class="nb-hero__stat-value"><?php echo esc_html( number_format_i18n( (int) $stats['pinCount'] ) ); ?></span>
				<span class="nb-hero__stat-label"><?php esc_html_e( 'Fähnchen', 'nordlys' ); ?></span>
			</li>
		</ul>

		<?php if ( $days ) : ?>
			<a class="nb-hero__scroll" href="#nb-trip">
				<span><?php esc_html_e( 'Der Reise folgen', 'nordlys' ); ?></span>
				<span class="nb-hero__scroll-line" aria-hidden="true"></span>
			</a>
		<?php endif; ?>
	</div>
</section>

<?php if ( ! nordlys_plugin_active() ) : ?>

	<section class="nb-empty">
		<p><?php esc_html_e( 'Bitte aktiviere das Plugin „Norwegen Reise“, damit Karte und Reisetage erscheinen.', 'nordlys' ); ?></p>
	</section>

<?php elseif ( ! $days ) : ?>

	<section class="nb-empty">
		<h2><?php esc_html_e( 'Noch geht es nicht los', 'nordlys' ); ?></h2>
		<p><?php esc_html_e( 'Sobald der erste Reisetag veröffentlicht ist, zeichnet sich hier die Route über Norwegen.', 'nordlys' ); ?></p>
	</section>

<?php else : ?>

	<section class="nb-trip" id="nb-trip">
		<div class="nb-trip__stage">
			<div class="nb-trip__map" id="nb-trip-map" role="application" aria-label="<?php esc_attr_e( 'Karte der Reiseroute', 'nordlys' ); ?>"></div>

			<div class="nb-trip__hud">
				<div class="nb-hud__day">
					<span class="nb-hud__badge" id="nb-hud-badge"><?php esc_html_e( 'Tag 1', 'nordlys' ); ?></span>
					<span class="nb-hud__title" id="nb-hud-title"></span>
				</div>

				<div class="nb-hud__progress" aria-hidden="true">
					<span class="nb-hud__bar" id="nb-hud-bar"></span>
				</div>

				<div class="nb-hud__meta">
					<span id="nb-hud-distance"></span>
					<button type="button" class="nb-hud__follow is-active" id="nb-hud-follow" aria-pressed="true">
						<?php echo nordlys_icon( 'compass' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<span><?php esc_html_e( 'Kamera folgt', 'nordlys' ); ?></span>
					</button>
				</div>
			</div>

			<noscript>
				<p class="nb-trip__noscript"><?php esc_html_e( 'Für die interaktive Karte wird JavaScript benötigt. Alle Reisetage finden sich auch in der Übersicht.', 'nordlys' ); ?></p>
			</noscript>
		</div>

		<div class="nb-trip__scroller" id="nb-trip-scroller">
			<div class="nb-trip__intro">
				<p class="nb-trip__intro-kicker"><?php esc_html_e( 'Die Route', 'nordlys' ); ?></p>
				<p class="nb-trip__intro-text">
					<?php esc_html_e( 'Scroll dich durch die Reise – die Linie füllt sich Tag für Tag, und die Fähnchen tauchen dort auf, wo etwas passiert ist.', 'nordlys' ); ?>
				</p>
			</div>

			<?php
			foreach ( $days as $index => $day ) {
				set_query_var( 'nordlys_day', $day );
				set_query_var( 'nordlys_day_index', $index );
				get_template_part( 'template-parts/day-card' );
			}
			?>

			<div class="nb-trip__outro">
				<p><?php esc_html_e( 'Das war der bisherige Weg.', 'nordlys' ); ?></p>
				<a class="nb-button" href="<?php echo esc_url( get_post_type_archive_link( 'nb_day' ) ); ?>">
					<?php esc_html_e( 'Alle Tage in der Übersicht', 'nordlys' ); ?>
					<?php echo nordlys_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</a>
			</div>
		</div>
	</section>

<?php endif; ?>

<?php
$intro_page = get_page_by_path( 'ueber-die-reise' );

if ( $intro_page && $intro_page->post_content ) :
	?>
	<section class="nb-about">
		<div class="nb-about__inner nb-article">
			<h2><?php echo esc_html( get_the_title( $intro_page ) ); ?></h2>
			<?php echo apply_filters( 'the_content', $intro_page->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
	</section>
	<?php
endif;

get_footer();
