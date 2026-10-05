<?php
/**
 * Palette test: the 41-palette library (Aurelis + 40), Aurelis freeze, uniqueness and near-duplicate
 * guard, build freshness and contrast gate, CSS output for every palette, sanitising and fallbacks,
 * section patterns, Accent-tone safety, and the separation of the fixed functional colours.
 *
 * Run from the WordPress root:  wp eval-file wp-content/themes/elite-auto-dealer/tests/palette-test.php
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

// ---------- Registry.
$eda_registry = eda_palette_registry();
$eda_check( 41 === count( $eda_registry ) && 'aurelis' === array_key_first( $eda_registry ), 'registry has 41 palettes, Aurelis first' );
$eda_check( 40 === count( array_filter( $eda_registry, static fn( $p ) => ! $p['default'] ) ) && 1 === count( array_filter( array_column( $eda_registry, 'default' ) ) ), '40 additional palettes, Aurelis the only default' );
$eda_aurelis = $eda_registry['aurelis'];
$eda_check( 'Aurelis' === $eda_aurelis['name'] && true === $eda_aurelis['default'] && true === $eda_aurelis['selectable'], 'Aurelis is the offered default' );
$eda_check( 5 === count( $eda_aurelis['colors'] ) && array() === array_diff( $eda_aurelis['colors'], $eda_aurelis['slots'] ), 'five card swatches, all Aurelis colours' );
$eda_check( 'aurelis' === eda_palette_active_slug() && 'original' === eda_pattern_active(), 'saved default: Aurelis, Original' );

// ---------- Aurelis freeze: base slots byte-identical to the approved values.
$eda_approved = array(
	'ink'          => '#121316',
	'graphite'     => '#1c1e22',
	'graphite-2'   => '#2b2e33',
	'paper'        => '#f5f3ee',
	'paper-2'      => '#ebe7de',
	'surface'      => '#ffffff',
	'line'         => '#dfdad0',
	'text'         => '#16171a',
	'muted'        => '#5d5f64',
	'muted-light'  => '#b8bbc1',
	'accent'       => '#a88a55',
	'accent-light' => '#d2bd94',
);
$eda_check( eda_palette_aurelis() === $eda_approved, 'eda_palette_aurelis() holds the approved values' );
$eda_check( array_intersect_key( $eda_aurelis['slots'], $eda_approved ) === $eda_approved, 'registry Aurelis base is byte-identical' );
$eda_sources = json_decode( (string) file_get_contents( EDA_DIR . '/bin/palette-sources.json' ), true );
$eda_check( array_map( 'strtolower', $eda_sources['palettes']['aurelis']['base'] ) === $eda_approved, 'source JSON Aurelis base is byte-identical' );
foreach ( array( 'accent-ink', 'on-accent', 'field-line' ) as $eda_helper ) {
	$eda_check( preg_match( '/^#[0-9a-f]{6}$/', $eda_aurelis['slots'][ $eda_helper ] ?? '' ), "derived helper {$eda_helper} present" );
}

// ---------- The library: uniqueness, completeness, near-duplicates.
$eda_raw = (string) file_get_contents( EDA_DIR . '/bin/palette-sources.json' );
preg_match_all( '/^\t\t"([^"]+)": \{$/m', $eda_raw, $eda_ids );
$eda_check( 41 === count( $eda_ids[1] ) && count( $eda_ids[1] ) === count( array_unique( $eda_ids[1] ) ) && array_keys( $eda_registry ) === $eda_ids[1], 'source has 41 unique palette IDs, in registry order' );
$eda_check( array() === array_filter( array_keys( $eda_registry ), static fn( $id ) => sanitize_key( $id ) !== $id || ! preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*$/', $id ) ), 'every slug is a strict lowercase-hyphen key' );
$eda_names = array_column( $eda_registry, 'name' );
$eda_check( count( $eda_names ) === count( array_unique( $eda_names ) ), 'all names unique' );
$eda_bad = array();
foreach ( $eda_registry as $eda_id => $eda_p ) {
	if ( 5 !== count( $eda_p['colors'] ) || preg_grep( '/^#[0-9a-f]{6}$/', $eda_p['colors'], PREG_GREP_INVERT ) ) {
		$eda_bad[] = "{$eda_id} swatches";
	}
	if ( array_keys( $eda_approved ) !== array_keys( array_intersect_key( $eda_p['slots'], $eda_approved ) ) || array_keys( $eda_approved ) !== array_keys( $eda_sources['palettes'][ $eda_id ]['base'] ) ) {
		$eda_bad[] = "{$eda_id} base slots";
	}
	if ( ! $eda_p['selectable'] || ! $eda_p['sections']['light'] || ! $eda_p['sections']['dark'] ) {
		$eda_bad[] = "{$eda_id} light/dark";
	}
	if ( (bool) preg_grep( '/^Accent tone off/', $eda_p['notes'] ) === $eda_p['sections']['accent'] ) {
		$eda_bad[] = "{$eda_id} accent flag";
	}
	$eda_v = eda_palette_vars( $eda_id );
	if ( 25 !== count( $eda_v ) || preg_grep( '/^(#[0-9a-f]{6}|var\(--eda-[a-z0-9-]+\))$/', $eda_v, PREG_GREP_INVERT ) ) {
		$eda_bad[] = "{$eda_id} vars";
	}
	if ( eda_sanitize_palette( $eda_id ) !== $eda_id ) {
		$eda_bad[] = "{$eda_id} sanitising";
	}
}
$eda_check( array() === $eda_bad, 'every palette: 5 hex swatches, the 12 base slots, offered with Light and Dark, Accent flag matches the gate, 25 CSS vars, sanitises to itself' . ( $eda_bad ? ': ' . implode( ', ', $eda_bad ) : '' ) );
$eda_check( 41 === count( array_unique( array_map( static fn( $p ) => implode( '', $p['colors'] ), $eda_registry ) ) ) && 41 === count( array_unique( array_map( 'wp_json_encode', array_column( $eda_registry, 'slots' ) ) ) ), 'no identical swatch sets or palette definitions' );
// Near-duplicates: accent ΔE (CIE76, Lab) + 1.5 × mean ΔE of ink, graphite-2, paper, paper-2 must stay >= 12.
$eda_lab  = static function ( $hex ) {
	$c = array_map( static fn( $v ) => ( $v /= 255 ) > 0.04045 ? ( ( $v + 0.055 ) / 1.055 ) ** 2.4 : $v / 12.92, array_map( 'hexdec', str_split( ltrim( $hex, '#' ), 2 ) ) );
	$f = static fn( $t ) => $t > 0.008856 ? $t ** ( 1 / 3 ) : 7.787 * $t + 16 / 116;
	$x = $f( ( $c[0] * 0.4124 + $c[1] * 0.3576 + $c[2] * 0.1805 ) / 0.95047 );
	$y = $f( $c[0] * 0.2126 + $c[1] * 0.7152 + $c[2] * 0.0722 );
	$z = $f( ( $c[0] * 0.0193 + $c[1] * 0.1192 + $c[2] * 0.9505 ) / 1.08883 );
	return array( 116 * $y - 16, 500 * ( $x - $y ), 200 * ( $y - $z ) );
};
$eda_de   = static fn( $a, $b ) => sqrt( array_sum( array_map( static fn( $p, $q ) => ( $p - $q ) ** 2, $eda_lab( $a ), $eda_lab( $b ) ) ) );
$eda_near = array();
$eda_ids  = array_keys( $eda_registry );
foreach ( $eda_ids as $eda_i => $eda_a ) {
	foreach ( array_slice( $eda_ids, $eda_i + 1 ) as $eda_b ) {
		$eda_x = $eda_registry[ $eda_a ]['slots'];
		$eda_y = $eda_registry[ $eda_b ]['slots'];
		$eda_n = array_sum( array_map( static fn( $k ) => $eda_de( $eda_x[ $k ], $eda_y[ $k ] ), array( 'ink', 'graphite-2', 'paper', 'paper-2' ) ) ) / 4;
		if ( $eda_de( $eda_x['accent'], $eda_y['accent'] ) + 1.5 * $eda_n < 12 ) {
			$eda_near[] = "{$eda_a}/{$eda_b}";
		}
	}
}
$eda_check( array() === $eda_near, 'no near-duplicate palettes (accent ΔE + 1.5 × neutral ΔE >= 12)' . ( $eda_near ? ': ' . implode( ', ', $eda_near ) : '' ) );

// ---------- Build freshness and contrast gate (the real scripts).
$eda_php = escapeshellarg( PHP_BINARY );
exec( $eda_php . ' ' . escapeshellarg( EDA_DIR . '/bin/palette-build.php' ) . ' --check 2>&1', $eda_out, $eda_code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- runs the build check.
$eda_check( 0 === $eda_code && str_contains( implode( "\n", $eda_out ), 'Registry up to date.' ), 'php bin/palette-build.php --check passes (registry fresh, gate green)' );
$eda_out = array();
exec( $eda_php . ' ' . escapeshellarg( EDA_DIR . '/bin/contrast-gate.php' ) . ' 2>&1', $eda_out, $eda_code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- runs the gate.
$eda_check( 0 === $eda_code && str_contains( implode( "\n", $eda_out ), 'All pairs pass' ), 'php bin/contrast-gate.php passes' );
$eda_check( 41 === count( preg_grep( '/  (PASS|HEADROOM WARNING \(\d+\))$/', $eda_out ) ), 'every palette carries an advisory headroom flag (internal target 4.70 / 3.20)' );
$eda_check( 41 === count( preg_grep( '/^ok\s/', $eda_out ) ) && count( preg_grep( '/accent:yes/', $eda_out ) ) === count( array_filter( array_column( array_column( $eda_registry, 'sections' ), 'accent' ) ) ), 'gate lists all 41 palettes, Accent availability as in the registry' );
$eda_lib = (string) file_get_contents( EDA_DIR . '/bin/palette-lib.php' );
$eda_check( str_contains( $eda_lib, 'const EDA_GATE_TEXT = 4.5;' ) && str_contains( $eda_lib, 'const EDA_GATE_UI   = 3.0;' ), 'the gate thresholds stay at the WCAG 4.5 / 3.0' );
$eda_out = array();
exec( $eda_php . ' ' . escapeshellarg( EDA_DIR . '/bin/contrast-gate.php' ) . ' --headroom 2>&1', $eda_out, $eda_code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- runs the headroom report.
$eda_check( 0 === $eda_code && str_contains( implode( "\n", $eda_out ), 'Advisory only.' ), 'headroom report runs and never fails the build' );

// ---------- Accent tone safety.
$eda_check( true === $eda_aurelis['sections']['accent'] && array( 'dark', 'light', 'accent' ) === eda_palette_roles( 'aurelis' ), 'Aurelis offers Dark, Light and Accent tones' );
$eda_check( ! preg_grep( '/^Accent tone off/', $eda_aurelis['notes'] ), 'no Accent-off note for Aurelis' );
$eda_check( array( 'dark', 'light', 'accent' ) === eda_pattern_sequence( 'aurelis', 'dark-light-accent' ) && array( 'light', 'dark', 'accent' ) === eda_pattern_sequence( 'aurelis', 'light-dark-accent' ), 'Accent patterns keep their Accent step' );
$eda_vars = eda_palette_vars( 'aurelis' );
$eda_check( 'var(--eda-c-accent)' === $eda_vars['--eda-a-bg'] && 'var(--eda-c-on-accent)' === $eda_vars['--eda-a-ink'] && 'var(--eda-c-accent-focus)' === $eda_vars['--eda-a-focus'], 'Accent sections use the gold fill, its label colour and the Accent focus ring' );
$eda_check( '#121316' === $eda_aurelis['slots']['accent-focus'], 'Accent focus ring is the Aurelis ink' );
$eda_tones = eda_tones();
$eda_check( '@focus-ring' === $eda_tones['light']['focus'] && '@focus-ring-dark' === $eda_tones['dark']['focus'] && 'a-focus' === $eda_tones['accent']['focus'], 'focus is a role per tone: blue, light blue, Accent ring' );
// Safety path for a palette whose Accent fails (none in the current library): patterns skip it, sections fall back to light.
$eda_check( array( 'dark', 'light' ) === eda_pattern_sequence( 'no-such-palette', 'dark-light-accent' ), 'patterns skip an unavailable Accent tone' );
$eda_fallback = eda_palette_accent_sources( false );
$eda_check( 'paper' === $eda_fallback['a-bg'] && 'text' === $eda_fallback['a-ink'] && '@focus-ring' === $eda_fallback['a-focus'], 'unavailable Accent falls back to light colours and the blue focus ring' );

// ---------- CSS output.
$eda_css = eda_palette_css( 'aurelis', 'original' );
$eda_check( str_starts_with( $eda_css, ':root{--eda-c-ink:#121316;' ), 'slots printed on :root' );
foreach ( array( 'bg', 'surface', 'surface-alt', 'text', 'heading', 'text-muted', 'line', 'line-strong', 'field-line', 'eyebrow', 'accent', 'accent-ink', 'button-bg', 'button-text', 'button-hover', 'focus' ) as $eda_role ) {
	$eda_check( str_contains( $eda_css, ':root,.eda-tone-light{' ) && substr_count( $eda_css, "--eda-{$eda_role}:" ) >= 3, "role --eda-{$eda_role} in every tone" );
}
foreach ( array( 'header-glass', 'photo-bg', 'footer-bg', 'footer-text' ) as $eda_chrome ) {
	$eda_check( str_contains( $eda_css, "--eda-{$eda_chrome}:" ), "chrome --eda-{$eda_chrome} printed" );
}
$eda_check( str_contains( $eda_css, '.section--dark,.hero,.page-intro--hero,.eda-tone-dark{' ), 'dark tone on dark sections, heroes and .eda-tone-dark' );
$eda_check( ! str_contains( $eda_css, 'nth-child' ), 'Original emits no section-pattern rules' );
$eda_css = eda_palette_css( 'aurelis', 'light-dark' );
$eda_check( str_contains( $eda_css, '.site-main>section.section:not([class*="eda-tone-"]):nth-child(2n+1 of section.section){--eda-bg:var(--eda-c-paper)' ) && str_contains( $eda_css, 'nth-child(2n+2 of section.section){--eda-bg:var(--eda-c-graphite)' ), 'Light / Dark assigns tones by position to section.section' );
$eda_css = eda_palette_css( 'aurelis', 'mostly-dark' );
$eda_check( str_contains( $eda_css, 'nth-child(4n+3 of section.section){--eda-bg:var(--eda-c-graphite)' ) && str_contains( $eda_css, 'nth-child(4n+4 of section.section){--eda-bg:var(--eda-c-paper)' ), 'Mostly dark: three dark, one light' );
do_action( 'wp_enqueue_scripts' );
$eda_check( str_contains( implode( '', (array) wp_styles()->get_data( 'eda-main', 'after' ) ), '--eda-c-ink:#121316' ), 'palette CSS added inline after main.css' );

// ---------- Patterns and sanitising.
$eda_check( array( 'original', 'dark-light', 'light-dark', 'dark-light-accent', 'light-dark-accent', 'mostly-light', 'mostly-dark' ) === array_keys( eda_patterns() ), 'seven section patterns registered' );
$eda_cycles = array_map( static fn( $p ) => implode( ',', $p['cycle'] ), eda_patterns() );
$eda_check( array( '', 'dark,light', 'light,dark', 'dark,light,accent', 'light,dark,accent', 'light,light,light,dark', 'dark,dark,dark,light' ) === array_values( $eda_cycles ), 'exact pattern cycles' );
$eda_check( 'aurelis' === eda_sanitize_palette( 'neon' ) && 'aurelis' === eda_sanitize_palette( 'aurelis' ), 'palette sanitising: unknown → Aurelis' );
$eda_check( 'original' === eda_sanitize_section_pattern( 'zebra' ) && 'mostly-dark' === eda_sanitize_section_pattern( 'mostly-dark' ), 'pattern sanitising: unknown → Original' );
set_theme_mod( 'eda_palette', 'neon' );
set_theme_mod( 'eda_section_pattern', 'zebra' );
$eda_check( 'aurelis' === eda_palette_active_slug() && 'original' === eda_pattern_active() && eda_palette_css() === eda_palette_css( 'aurelis', 'original' ), 'a stale saved value falls back safely' );
remove_theme_mod( 'eda_palette' );
remove_theme_mod( 'eda_section_pattern' );

require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
$eda_manager = new WP_Customize_Manager();
eda_palette_customize_register( $eda_manager );
$eda_check( $eda_manager->get_section( 'eda_colours' ) && 'edit_theme_options' === $eda_manager->get_section( 'eda_colours' )->capability, 'Colours section, administrators only (edit_theme_options)' );
$eda_check( 'postMessage' === $eda_manager->get_setting( 'eda_palette' )->transport && $eda_manager->selective_refresh->get_partial( 'eda_colours' ), 'live preview through a selective-refresh partial' );
$eda_check( ! get_role( 'eda_dealer_staff' )->has_cap( 'edit_theme_options' ), 'Dealership staff cannot reach Colours' );

// ---------- Functional colours stay outside the palette.
$eda_main = (string) file_get_contents( EDA_DIR . '/assets/css/main.css' );
$eda_keys = implode( ' ', array_keys( $eda_vars ) );
$eda_check( ! preg_match( '/status|focus-ring|on-photo/', $eda_keys ) && ! preg_match( '/--eda-(status-[a-z-]+|focus-ring[a-z-]*|on-photo):/', eda_palette_css( 'aurelis', 'dark-light' ) ), 'palette output never sets status, focus-ring or photo-text colours' );
foreach ( eda_palette_fixed_colors() as $eda_name => $eda_hex ) {
	$eda_short = '#ffffff' === $eda_hex ? '#fff' : $eda_hex;
	$eda_check( str_contains( $eda_main, "--eda-{$eda_name}: {$eda_short};" ), "main.css fixes --eda-{$eda_name} ({$eda_short}) as the gate assumes" );
}
$eda_check( ! preg_match( '/var\(--(ink|graphite|paper|accent|focus|reserved-bg|muted-light|line-dark)\)/', $eda_main ), 'no component uses the old raw colour tokens' );

echo PHP_EOL . ( $eda_failures ? "$eda_failures FAILED" : 'ALL PASSED' ) . PHP_EOL;
