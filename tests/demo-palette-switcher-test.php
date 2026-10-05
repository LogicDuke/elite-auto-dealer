<?php
/**
 * Demo Palette Switcher test: the registration handed to the optional EDS Demo Palette Switcher
 * plugin is the palette registry as it is (ids, names, swatches, eda_palette_vars()), colours only,
 * with the saved palette as its default. With the plugin active, also checks the plugin accepts it.
 *
 * Run from the WordPress root:  wp eval-file wp-content/themes/elite-auto-dealer/tests/demo-palette-switcher-test.php
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput -- CLI test script.

$eda_failures = 0;
$eda_check    = static function ( $condition, $message ) use ( &$eda_failures ) {
	echo ( $condition ? 'PASS ' : 'FAIL ' ) . $message . PHP_EOL;
	$eda_failures += $condition ? 0 : 1;
};

// ---------- Loading and the guard.
$eda_source = (string) file_get_contents( EDA_DIR . '/inc/demo-palette-switcher.php' );
$eda_check( function_exists( 'eda_demo_palette_config' ) && function_exists( 'eda_register_demo_palettes' ), 'integration file is loaded' );
$eda_check( 10 === has_action( 'after_setup_theme', 'eda_register_demo_palettes' ), 'registers on after_setup_theme' );
$eda_check( str_contains( $eda_source, "if ( function_exists( 'eds_dps_register_theme_palettes' ) )" ), 'registration is guarded by function_exists()' );
$eda_check( ! str_contains( $eda_source, '_eds_dps_' ), 'only the public plugin API is used' );
$eda_check( ! preg_match( '/#[0-9a-f]{3,8}\b/i', $eda_source ), 'no colour literals: no second copy of the palette data' );

// ---------- The registration.
$eda_config   = eda_demo_palette_config();
$eda_palettes = eda_palettes();
$eda_check( array( 'integration_id', 'default_palette', 'palettes' ) === array_keys( $eda_config ), 'only integration_id, default_palette and palettes (no section patterns)' );
$eda_check( 'elite-auto-dealer' === $eda_config['integration_id'], 'integration_id is elite-auto-dealer' );
$eda_check( eda_palette_active_slug() === $eda_config['default_palette'], 'default_palette is the saved palette' );
$eda_check( 41 === count( $eda_config['palettes'] ) && array_column( $eda_config['palettes'], 'id' ) === array_keys( $eda_palettes ), '41 palettes, ids = registry slugs in registry order' );
$eda_check( count( array_unique( array_column( $eda_config['palettes'], 'id' ) ) ) === 41, '41 unique ids' );

$eda_bad = array();
foreach ( $eda_config['palettes'] as $eda_palette ) {
	$eda_slug = $eda_palette['id'];
	if ( array( 'id', 'name', 'swatches', 'vars' ) !== array_keys( $eda_palette )
		|| $eda_palettes[ $eda_slug ]['name'] !== $eda_palette['name']
		|| $eda_palettes[ $eda_slug ]['colors'] !== $eda_palette['swatches']
		|| eda_palette_vars( $eda_slug ) !== $eda_palette['vars'] ) {
		$eda_bad[] = $eda_slug;
	}
}
$eda_check( array() === $eda_bad, 'every palette: name, swatches and vars come from the registry / eda_palette_vars()' . ( $eda_bad ? ' — ' . implode( ', ', $eda_bad ) : '' ) );

$eda_names = array();
$eda_hexes = array();
foreach ( $eda_config['palettes'] as $eda_palette ) {
	$eda_names = array_merge( $eda_names, array_keys( $eda_palette['vars'] ) );
	$eda_hexes = array_merge( $eda_hexes, $eda_palette['swatches'] );
}
$eda_check( array() === array_filter( $eda_names, static fn( $n ) => ! preg_match( '/^--eda-(c|a)-[a-z0-9-]+\z/', $n ) ), 'only palette slot variables (--eda-c-*, --eda-a-*)' );
$eda_fixed = array_map( static fn( $n ) => "--eda-{$n}", array_keys( eda_palette_fixed_colors() ) );
$eda_check( array() === array_intersect( $eda_fixed, $eda_names ) && ! preg_grep( '/scrim|on-photo|status|focus-ring|success|warning|error/', $eda_names ), 'no fixed functional or photo colours registered' );
$eda_check( array() === array_filter( $eda_hexes, static fn( $h ) => ! preg_match( '/^#[0-9a-f]{6}\z/', $h ) ), 'every swatch is 6-digit hex' );
$eda_check( ! preg_match( '/cycle|pattern|eda-tone|section/i', (string) wp_json_encode( $eda_config ) ), 'no section-pattern data in the registration' );

// ---------- Default follows the Customizer.
$eda_saved = get_theme_mod( 'eda_palette' );
set_theme_mod( 'eda_palette', 'british-racing' );
$eda_check( 'british-racing' === eda_demo_palette_config()['default_palette'], 'default_palette follows a newly saved palette (British Racing)' );
set_theme_mod( 'eda_palette', 'no-such-palette' );
$eda_check( 'aurelis' === eda_demo_palette_config()['default_palette'], 'an unknown saved palette falls back to Aurelis, like the theme' );
false === $eda_saved ? remove_theme_mod( 'eda_palette' ) : set_theme_mod( 'eda_palette', $eda_saved );

// ---------- The plugin, when active.
if ( function_exists( 'eds_dps_register_theme_palettes' ) ) {
	$eda_check( true === eds_dps_register_theme_palettes( $eda_config ), 'plugin accepts the registration' );
	$eda_integration = eds_dps_get_integration();
	$eda_check( is_array( $eda_integration ) && 'elite-auto-dealer' === $eda_integration['integration_id'] && $eda_config['default_palette'] === $eda_integration['default_palette'], 'plugin integration valid, default kept' );
	$eda_check( is_array( $eda_integration ) && $eda_config['palettes'] === $eda_integration['palettes'], 'all 41 palettes accepted unchanged (none dropped or rewritten)' );
	$eda_rejected = array();
	foreach ( array_keys( $eda_palettes ) as $eda_slug ) {
		if ( ! eds_dps_register_theme_palettes( array_merge( $eda_config, array( 'default_palette' => $eda_slug ) ) ) ) {
			$eda_rejected[] = $eda_slug;
		}
	}
	$eda_check( array() === $eda_rejected, 'every palette is accepted as the default' );
	eds_dps_register_theme_palettes( $eda_config );
} else {
	echo 'SKIP plugin checks: EDS Demo Palette Switcher is not active' . PHP_EOL;
	eda_register_demo_palettes();
	$eda_check( true, 'registration without the plugin does nothing and raises nothing' );
}

echo PHP_EOL . ( $eda_failures ? "$eda_failures FAILED" : 'ALL PASSED' ) . PHP_EOL;
