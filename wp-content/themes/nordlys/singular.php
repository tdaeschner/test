<?php
/**
 * Single posts and pages.
 *
 * @package Nordlys
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( 'nb-page' ); ?>>
		<header class="nb-page__head">
			<h1 class="nb-page__title"><?php the_title(); ?></h1>

			<?php if ( is_single() ) : ?>
				<p class="nb-page__meta"><?php echo esc_html( get_the_date() ); ?></p>
			<?php endif; ?>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<div class="nb-page__media">
				<?php the_post_thumbnail( 'nordlys-hero', array( 'alt' => '' ) ); ?>
			</div>
		<?php endif; ?>

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
