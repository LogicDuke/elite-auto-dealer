<?php
/**
 * Site header. The menu is plain HTML and fully usable without JavaScript; navigation.js
 * turns it into a toggled drawer on small screens (CSS breakpoint, no UA detection).
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<script>document.documentElement.classList.add( 'js' );</script>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#main"><?php esc_html_e( 'Skip to content', 'elite-auto-dealer' ); ?></a>

<header class="site-header">
	<div class="container site-header__inner">
		<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>

		<button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-navigation">
			<span class="nav-toggle__icon" aria-hidden="true"></span>
			<span class="nav-toggle__label"><?php esc_html_e( 'Menu', 'elite-auto-dealer' ); ?></span>
		</button>

		<div class="site-nav" id="site-navigation">
			<nav class="site-nav__menu" aria-label="<?php esc_attr_e( 'Primary', 'elite-auto-dealer' ); ?>">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'site-nav__list',
						'depth'          => 1,
						'fallback_cb'    => 'eda_primary_menu_fallback',
					)
				);
				?>
			</nav>
			<a class="button button--primary site-nav__cta" href="<?php echo esc_url( get_post_type_archive_link( 'vehicle' ) ); ?>"><?php esc_html_e( 'View vehicles', 'elite-auto-dealer' ); ?></a>
		</div>
	</div>
</header>

<main id="main" class="site-main" tabindex="-1">
