<?php
/**
 * Elite Auto Dealer theme bootstrap.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

define( 'EDA_VERSION', '0.1.0' );
define( 'EDA_DIR', get_template_directory() );
define( 'EDA_URI', get_template_directory_uri() );

require EDA_DIR . '/inc/vehicle-post-type.php';
require EDA_DIR . '/inc/vehicle-taxonomies.php';
require EDA_DIR . '/inc/vehicle-meta.php';
require EDA_DIR . '/inc/vehicle-catalogue.php';
require EDA_DIR . '/inc/template-tags.php';
require EDA_DIR . '/inc/seo.php';
require EDA_DIR . '/inc/inventory-query.php';
require EDA_DIR . '/inc/enquiries.php';
require EDA_DIR . '/inc/enquiry-privacy.php';
require EDA_DIR . '/inc/customizer.php';
require EDA_DIR . '/inc/palette.php';
require EDA_DIR . '/inc/palette-customizer.php';
require EDA_DIR . '/inc/page-header.php';
require EDA_DIR . '/inc/admin-roles.php';
require EDA_DIR . '/inc/admin-vehicle-editor.php';
require EDA_DIR . '/inc/admin-vehicle-list.php';
require EDA_DIR . '/inc/admin-site-settings.php';

/**
 * Theme supports, menus and image sizes.
 */
function eda_setup() {
	load_theme_textdomain( 'elite-auto-dealer', EDA_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
	);

	register_nav_menus(
		array(
			'primary' => __( 'Primary menu', 'elite-auto-dealer' ),
			'footer'  => __( 'Footer menu', 'elite-auto-dealer' ),
		)
	);

	// Vehicle photos are 3:2, delivered at 1536x1024 (docs/VEHICLE-IMAGE-ROADMAP.md). All three
	// sizes share that ratio, so wp_get_attachment_image() emits them together in one srcset.
	// None exceeds the source: "large" equals it, so WordPress serves the original, never an upscale.
	add_image_size( 'eda-vehicle-card', 640, 427, true );
	add_image_size( 'eda-vehicle-medium', 960, 640, true );
	add_image_size( 'eda-vehicle-large', 1536, 1024, true );
}
add_action( 'after_setup_theme', 'eda_setup' );

/**
 * Cache-busting version for a theme asset: file mtime, falling back to theme version.
 *
 * @param string $path Path relative to the theme root.
 * @return string
 */
function eda_asset_version( $path ) {
	$file = EDA_DIR . '/' . $path;
	return file_exists( $file ) ? (string) filemtime( $file ) : EDA_VERSION;
}

/**
 * Front-end assets. Add scripts here with array( 'strategy' => 'defer' ).
 */
function eda_enqueue_assets() {
	wp_enqueue_style( 'eda-main', EDA_URI . '/assets/css/main.css', array(), eda_asset_version( 'assets/css/main.css' ) );
	wp_enqueue_script(
		'eda-navigation',
		EDA_URI . '/assets/js/navigation.js',
		array(),
		eda_asset_version( 'assets/js/navigation.js' ),
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);
	if ( is_singular( 'vehicle' ) ) {
		wp_enqueue_script(
			'eda-vehicle-gallery',
			EDA_URI . '/assets/js/vehicle-gallery.js',
			array(),
			eda_asset_version( 'assets/js/vehicle-gallery.js' ),
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'eda_enqueue_assets' );

// Core emoji polyfill (~22 KB on every page): modern browsers render emoji natively.
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );

/**
 * On theme activation: register content types, seed fixed vocabularies, flush permalinks.
 */
function eda_activate() {
	eda_register_vehicle_post_type();
	eda_register_vehicle_taxonomies();
	eda_seed_vehicle_terms();
	eda_maybe_seed_catalogue();
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'eda_activate' );
