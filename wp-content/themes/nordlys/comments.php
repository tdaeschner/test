<?php
/**
 * Comments below a travel day.
 *
 * @package Nordlys
 */

defined( 'ABSPATH' ) || exit;

if ( post_password_required() ) {
	return;
}
?>

<section class="nb-comments__inner">
	<?php if ( have_comments() ) : ?>
		<h2 class="nb-section-title">
			<?php
			$count = get_comments_number();

			printf(
				/* translators: %s: comment count */
				esc_html( _n( '%s Gruß von zu Hause', '%s Grüße von zu Hause', $count, 'nordlys' ) ),
				esc_html( number_format_i18n( $count ) )
			);
			?>
		</h2>

		<ol class="nb-comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 44,
				)
			);
			?>
		</ol>

		<?php
		the_comments_pagination(
			array(
				'prev_text' => esc_html__( 'Zurück', 'nordlys' ),
				'next_text' => esc_html__( 'Weiter', 'nordlys' ),
			)
		);
		?>
	<?php endif; ?>

	<?php if ( ! comments_open() && get_comments_number() ) : ?>
		<p class="nb-comments__closed"><?php esc_html_e( 'Die Kommentare sind geschlossen.', 'nordlys' ); ?></p>
	<?php endif; ?>

	<?php
	comment_form(
		array(
			'title_reply'        => esc_html__( 'Schreib uns etwas', 'nordlys' ),
			'class_submit'       => 'nb-button',
			'comment_notes_before' => '',
		)
	);
	?>
</section>
