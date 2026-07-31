<?php
/**
 * Site header.
 *
 * @package Nordlys
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<link rel="profile" href="https://gmpg.org/xfn/11" />
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="nb-skip" href="#nb-content"><?php esc_html_e( 'Zum Inhalt springen', 'nordlys' ); ?></a>

<header class="nb-header" id="nb-header">
	<div class="nb-header__inner">
		<div class="nb-header__brand">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a class="nb-header__title" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
					<span class="nb-header__mark" aria-hidden="true"></span>
					<?php bloginfo( 'name' ); ?>
				</a>
			<?php endif; ?>
		</div>

		<nav class="nb-header__nav" aria-label="<?php esc_attr_e( 'Hauptmenü', 'nordlys' ); ?>">
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'nb-menu',
						'depth'          => 1,
					)
				);
			} else {
				?>
				<ul class="nb-menu">
					<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Karte', 'nordlys' ); ?></a></li>
					<?php if ( post_type_exists( 'nb_day' ) ) : ?>
						<li><a href="<?php echo esc_url( get_post_type_archive_link( 'nb_day' ) ); ?>"><?php esc_html_e( 'Alle Tage', 'nordlys' ); ?></a></li>
					<?php endif; ?>
				</ul>
				<?php
			}
			?>
		</nav>
	</div>
</header>

<main id="nb-content" class="nb-main">
