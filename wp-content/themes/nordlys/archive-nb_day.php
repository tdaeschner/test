<?php
/**
 * Overview of all travel days.
 *
 * @package Nordlys
 */

defined( 'ABSPATH' ) || exit;

get_header();

$stats = nordlys_trip_stats();
?>

<header class="nb-archive__head">
	<p class="nb-archive__kicker"><?php esc_html_e( 'Die Reise, Tag für Tag', 'nordlys' ); ?></p>
	<h1 class="nb-archive__title"><?php post_type_archive_title(); ?></h1>

	<p class="nb-archive__meta">
		<?php
		printf(
			/* translators: 1: days online, 2: planned days, 3: kilometres */
			esc_html__( '%1$d von %2$d Tagen · %3$s km', 'nordlys' ),
			(int) $stats['daysOnline'],
			(int) $stats['daysPlanned'],
			esc_html( number_format_i18n( (float) $stats['totalKm'], 0 ) )
		);
		?>
	</p>
</header>

<?php if ( have_posts() ) : ?>

	<div class="nb-archive__grid">
		<?php
		while ( have_posts() ) :
			the_post();

			$post_id = get_the_ID();
			?>
			<article <?php post_class( 'nb-tile' ); ?> data-nb-reveal>
				<a class="nb-tile__link" href="<?php the_permalink(); ?>">
					<div class="nb-tile__media">
						<?php if ( has_post_thumbnail() ) : ?>
							<?php the_post_thumbnail( 'nordlys-card', array( 'loading' => 'lazy', 'decoding' => 'async', 'alt' => '' ) ); ?>
						<?php else : ?>
							<span class="nb-tile__placeholder" aria-hidden="true"></span>
						<?php endif; ?>

						<span class="nb-tile__badge">
							<?php printf( esc_html__( 'Tag %d', 'nordlys' ), (int) nordlys_day_number( $post_id ) ); ?>
						</span>
					</div>

					<div class="nb-tile__body">
						<p class="nb-tile__date"><?php echo esc_html( nordlys_day_date( $post_id ) ); ?></p>
						<h2 class="nb-tile__title"><?php the_title(); ?></h2>
						<?php nordlys_day_facts( $post_id, true ); ?>
						<p class="nb-tile__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 24 ) ); ?></p>
					</div>
				</a>
			</article>
			<?php
		endwhile;
		?>
	</div>

<?php else : ?>

	<section class="nb-empty">
		<h2><?php esc_html_e( 'Noch keine Reisetage', 'nordlys' ); ?></h2>
		<p><?php esc_html_e( 'Sobald der erste Abend geschrieben ist, steht er hier.', 'nordlys' ); ?></p>
	</section>

<?php endif; ?>

<?php
get_footer();
