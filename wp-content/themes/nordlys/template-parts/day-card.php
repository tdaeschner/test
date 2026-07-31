<?php
/**
 * One day card inside the scrolling column.
 *
 * @package Nordlys
 */

defined( 'ABSPATH' ) || exit;

$day   = get_query_var( 'nordlys_day' );
$index = (int) get_query_var( 'nordlys_day_index' );

if ( ! $day instanceof WP_Post ) {
	return;
}

$number = nordlys_day_number( $day->ID );
$image  = get_the_post_thumbnail_url( $day, 'nordlys-card' );
$pins   = nordlys_plugin_active() ? NB_Meta::get_pins( $day->ID ) : array();
?>

<article class="nb-day-card" data-index="<?php echo esc_attr( $index ); ?>" data-day-id="<?php echo esc_attr( $day->ID ); ?>" id="tag-<?php echo esc_attr( $number ); ?>">
	<a class="nb-day-card__link" href="<?php echo esc_url( get_permalink( $day ) ); ?>">
		<div class="nb-day-card__head">
			<span class="nb-day-card__number"><?php echo esc_html( sprintf( __( 'Tag %d', 'nordlys' ), $number ) ); ?></span>
			<span class="nb-day-card__date"><?php echo esc_html( nordlys_day_date( $day->ID ) ); ?></span>
		</div>

		<?php if ( $image ) : ?>
			<div class="nb-day-card__media">
				<img src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy" decoding="async" width="960" height="640" />
			</div>
		<?php endif; ?>

		<h2 class="nb-day-card__title"><?php echo esc_html( get_the_title( $day ) ); ?></h2>

		<?php nordlys_day_facts( $day->ID, true ); ?>

		<p class="nb-day-card__excerpt">
			<?php
			echo esc_html(
				$day->post_excerpt
					? wp_strip_all_tags( $day->post_excerpt )
					: wp_trim_words( wp_strip_all_tags( strip_shortcodes( $day->post_content ) ), 34 )
			);
			?>
		</p>

		<span class="nb-day-card__more">
			<?php esc_html_e( 'Weiterlesen', 'nordlys' ); ?>
			<?php echo nordlys_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</span>
	</a>

	<?php if ( $pins ) : ?>
		<ul class="nb-day-card__pins">
			<?php foreach ( $pins as $pin ) : ?>
				<li>
					<span class="nb-pin-chip nb-pin-chip--<?php echo esc_attr( $pin['icon'] ); ?>">
						<?php echo nordlys_pin_glyph( $pin['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php echo esc_html( $pin['title'] ); ?>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</article>
