<?php
/**
 * Fallback template for blog listings and archives.
 *
 * @package Nordlys
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<header class="nb-archive__head">
	<h1 class="nb-archive__title">
		<?php
		if ( is_home() && ! is_front_page() ) {
			single_post_title();
		} elseif ( is_search() ) {
			printf( esc_html__( 'Suche nach „%s“', 'nordlys' ), esc_html( get_search_query() ) );
		} else {
			the_archive_title();
		}
		?>
	</h1>

	<?php the_archive_description( '<p class="nb-archive__meta">', '</p>' ); ?>
</header>

<?php if ( have_posts() ) : ?>

	<div class="nb-archive__grid">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class( 'nb-tile' ); ?> data-nb-reveal>
				<a class="nb-tile__link" href="<?php the_permalink(); ?>">
					<?php if ( has_post_thumbnail() ) : ?>
						<div class="nb-tile__media">
							<?php the_post_thumbnail( 'nordlys-card', array( 'loading' => 'lazy', 'alt' => '' ) ); ?>
						</div>
					<?php endif; ?>

					<div class="nb-tile__body">
						<p class="nb-tile__date"><?php echo esc_html( get_the_date() ); ?></p>
						<h2 class="nb-tile__title"><?php the_title(); ?></h2>
						<p class="nb-tile__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 24 ) ); ?></p>
					</div>
				</a>
			</article>
			<?php
		endwhile;
		?>
	</div>

	<?php
	the_posts_pagination(
		array(
			'mid_size'  => 1,
			'prev_text' => esc_html__( 'Zurück', 'nordlys' ),
			'next_text' => esc_html__( 'Weiter', 'nordlys' ),
			'class'     => 'nb-pagination',
		)
	);
	?>

<?php else : ?>

	<section class="nb-empty">
		<h2><?php esc_html_e( 'Nichts gefunden', 'nordlys' ); ?></h2>
		<p><?php esc_html_e( 'Hier ist noch nichts – vielleicht hilft die Übersicht aller Reisetage weiter.', 'nordlys' ); ?></p>
		<?php get_search_form(); ?>
	</section>

<?php endif; ?>

<?php
get_footer();
