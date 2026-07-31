<?php
/**
 * A single day of the trip.
 *
 * @package Nordlys
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$post_id  = get_the_ID();
	$number   = nordlys_day_number( $post_id );
	$planned  = nordlys_plugin_active() ? (int) NB_Settings::get( 'days_planned' ) : 0;
	$pins     = nordlys_plugin_active() ? NB_Meta::get_pins( $post_id ) : array();
	$hero     = get_the_post_thumbnail_url( $post_id, 'nordlys-hero' );
	$previous = nordlys_adjacent_day( $post_id, 'prev' );
	$next     = nordlys_adjacent_day( $post_id, 'next' );
	?>

	<article <?php post_class( 'nb-day' ); ?>>

		<header class="nb-day__hero <?php echo $hero ? 'has-image' : ''; ?>">
			<?php if ( $hero ) : ?>
				<div class="nb-day__hero-media">
					<img src="<?php echo esc_url( $hero ); ?>" alt="" fetchpriority="high" decoding="async" />
				</div>
			<?php endif; ?>

			<div class="nb-day__hero-inner">
				<p class="nb-day__kicker">
					<?php
					if ( $planned ) {
						printf(
							/* translators: 1: day number, 2: planned days */
							esc_html__( 'Tag %1$d von %2$d', 'nordlys' ),
							(int) $number,
							(int) $planned
						);
					} else {
						printf( esc_html__( 'Tag %d', 'nordlys' ), (int) $number );
					}
					?>
					<span class="nb-day__kicker-sep">·</span>
					<?php echo esc_html( nordlys_day_date( $post_id ) ); ?>
				</p>

				<h1 class="nb-day__title"><?php the_title(); ?></h1>

				<?php nordlys_day_facts( $post_id ); ?>
			</div>
		</header>

		<div class="nb-day__body">
			<div class="nb-article">
				<?php
				the_content();

				wp_link_pages(
					array(
						'before' => '<nav class="nb-page-links">' . esc_html__( 'Seiten:', 'nordlys' ),
						'after'  => '</nav>',
					)
				);
				?>
			</div>

			<?php if ( nordlys_plugin_active() ) : ?>
				<section class="nb-day-map-wrap" aria-labelledby="nb-day-map-title">
					<h2 class="nb-section-title" id="nb-day-map-title"><?php esc_html_e( 'Die Etappe', 'nordlys' ); ?></h2>
					<div class="nb-day-map" id="nb-day-map" role="application" aria-label="<?php esc_attr_e( 'Karte der Tagesetappe', 'nordlys' ); ?>"></div>
				</section>
			<?php endif; ?>

			<?php if ( $pins ) : ?>
				<section class="nb-highlights" aria-labelledby="nb-highlights-title">
					<h2 class="nb-section-title" id="nb-highlights-title"><?php esc_html_e( 'Fähnchen an diesem Tag', 'nordlys' ); ?></h2>

					<ul class="nb-highlights__list">
						<?php foreach ( $pins as $index => $pin ) : ?>
							<li class="nb-highlight">
								<button type="button" class="nb-highlight__button" data-nb-focus-pin="<?php echo esc_attr( $index ); ?>">
									<span class="nb-highlight__icon nb-highlight__icon--<?php echo esc_attr( $pin['icon'] ); ?>">
										<?php echo nordlys_pin_glyph( $pin['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
									</span>

									<span class="nb-highlight__text">
										<span class="nb-highlight__title"><?php echo esc_html( $pin['title'] ); ?></span>
										<?php if ( $pin['text'] ) : ?>
											<span class="nb-highlight__note"><?php echo esc_html( $pin['text'] ); ?></span>
										<?php endif; ?>
									</span>
								</button>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>
		</div>

		<nav class="nb-day-nav" aria-label="<?php esc_attr_e( 'Weitere Reisetage', 'nordlys' ); ?>">
			<?php if ( $previous ) : ?>
				<a class="nb-day-nav__item nb-day-nav__item--prev" href="<?php echo esc_url( get_permalink( $previous ) ); ?>">
					<span class="nb-day-nav__label"><?php esc_html_e( 'Vorheriger Tag', 'nordlys' ); ?></span>
					<span class="nb-day-nav__title"><?php echo esc_html( get_the_title( $previous ) ); ?></span>
				</a>
			<?php else : ?>
				<span class="nb-day-nav__item nb-day-nav__item--empty"></span>
			<?php endif; ?>

			<a class="nb-day-nav__all" href="<?php echo esc_url( get_post_type_archive_link( 'nb_day' ) ); ?>">
				<?php esc_html_e( 'Alle Tage', 'nordlys' ); ?>
			</a>

			<?php if ( $next ) : ?>
				<a class="nb-day-nav__item nb-day-nav__item--next" href="<?php echo esc_url( get_permalink( $next ) ); ?>">
					<span class="nb-day-nav__label"><?php esc_html_e( 'Nächster Tag', 'nordlys' ); ?></span>
					<span class="nb-day-nav__title"><?php echo esc_html( get_the_title( $next ) ); ?></span>
				</a>
			<?php else : ?>
				<span class="nb-day-nav__item nb-day-nav__item--empty"></span>
			<?php endif; ?>
		</nav>

		<?php
		if ( comments_open() || get_comments_number() ) {
			echo '<div class="nb-comments">';
			comments_template();
			echo '</div>';
		}
		?>
	</article>

	<?php
endwhile;

get_footer();
