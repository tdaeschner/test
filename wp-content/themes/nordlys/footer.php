<?php
/**
 * Site footer.
 *
 * @package Nordlys
 */

defined( 'ABSPATH' ) || exit;
?>
</main>

<footer class="nb-footer">
	<div class="nb-footer__inner">
		<p class="nb-footer__brand"><?php bloginfo( 'name' ); ?></p>

		<?php if ( get_bloginfo( 'description' ) ) : ?>
			<p class="nb-footer__tagline"><?php bloginfo( 'description' ); ?></p>
		<?php endif; ?>

		<?php
		if ( has_nav_menu( 'footer' ) ) {
			wp_nav_menu(
				array(
					'theme_location' => 'footer',
					'container'      => false,
					'menu_class'     => 'nb-footer__menu',
					'depth'          => 1,
				)
			);
		}
		?>

		<p class="nb-footer__meta">
			<?php
			printf(
				/* translators: %s: current year */
				esc_html__( '© %s · Kartendaten von OpenStreetMap und Partnern', 'nordlys' ),
				esc_html( wp_date( 'Y' ) )
			);
			?>
		</p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
