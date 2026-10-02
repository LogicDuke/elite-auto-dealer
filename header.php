<?php
/**
 * Site header.
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
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#main"><?php esc_html_e( 'Skip to content', 'elite-auto-dealer' ); ?></a>

<header class="site-header">
	<a class="site-title" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
	<?php
	wp_nav_menu(
		array(
			'theme_location'       => 'primary',
			'container'            => 'nav',
			'container_aria_label' => __( 'Primary', 'elite-auto-dealer' ),
			'fallback_cb'          => false,
		)
	);
	?>
</header>

<main id="main" class="site-main">
