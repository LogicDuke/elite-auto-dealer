<?php
/**
 * EDS Demo Palette Switcher integration (optional plugin, LogicDuke/eds-demo-palette-switcher).
 *
 * Hands the plugin the palette registry as it is: ids, names and Customizer swatches from
 * eda_palettes(), variables from eda_palette_vars(). Colours only: section patterns, fixed
 * functional colours and photo colours stay with the theme. The plugin previews a visitor's
 * choice client-side (localStorage); Reset returns to the palette saved in the Customizer.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

/**
 * The registration for eds_dps_register_theme_palettes().
 *
 * @return array
 */
function eda_demo_palette_config() {
	$palettes = array();
	foreach ( eda_palettes() as $slug => $palette ) {
		$palettes[] = array(
			'id'       => $slug,
			'name'     => $palette['name'],
			'swatches' => $palette['colors'],
			'vars'     => eda_palette_vars( $slug ),
		);
	}
	return array(
		'integration_id'  => 'elite-auto-dealer',
		'default_palette' => eda_palette_active_slug(),
		'palettes'        => $palettes,
	);
}

/**
 * Register with the plugin when it is active; otherwise do nothing.
 */
function eda_register_demo_palettes() {
	if ( function_exists( 'eds_dps_register_theme_palettes' ) ) {
		eds_dps_register_theme_palettes( eda_demo_palette_config() );
	}
}
add_action( 'after_setup_theme', 'eda_register_demo_palettes' );
