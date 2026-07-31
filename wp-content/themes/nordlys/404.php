<?php
/**
 * Nothing here.
 *
 * @package Nordlys
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<section class="nb-empty nb-empty--404">
	<p class="nb-empty__code">404</p>
	<h1><?php esc_html_e( 'Hier war wohl noch niemand', 'nordlys' ); ?></h1>
	<p><?php esc_html_e( 'Diese Seite gibt es nicht. Zurück auf die Karte?', 'nordlys' ); ?></p>

	<p>
		<a class="nb-button" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php esc_html_e( 'Zur Karte', 'nordlys' ); ?>
		</a>
	</p>
</section>

<?php
get_footer();
