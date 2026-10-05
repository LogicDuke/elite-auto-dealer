<?php
/**
 * Standalone WCAG gate over the generated registry (inc/palette-registry.php): every offered
 * palette in every tone a section can take (Light; Dark, also the photographic heroes; the Accent
 * tone it actually renders: its own, or the light fallback when its Accent did not pass), plus the
 * chrome (footer, photo scrim) and the fixed functional colours. Text 4.5:1, UI 3:1.
 *
 * Each palette line also carries an advisory headroom flag against the internal design target
 * (text 4.70:1, UI 3.20:1): PASS, or HEADROOM WARNING when it is compliant but below target.
 * Only the WCAG gate decides the exit code.
 *
 * Usage:  php bin/contrast-gate.php             one line per palette, exit 1 on any failure
 *         php bin/contrast-gate.php aurelis     every pair of one palette
 *         php bin/contrast-gate.php --headroom  every pair below the internal target
 *
 * @package EliteAutoDealer
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

// phpcs:disable WordPress.WP.AlternativeFunctions -- CLI script reading and writing local theme files.
require __DIR__ . '/palette-lib.php';

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
$eda_headroom = in_array( '--headroom', $argv, true );
$eda_only     = $eda_headroom ? '' : ( $argv[1] ?? '' );
$eda_failures = 0;
$eda_warned   = 0;

foreach ( eda_palette_registry() as $eda_slug => $eda_p ) {
	if ( empty( $eda_p['selectable'] ) || ( $eda_only && $eda_only !== $eda_slug ) ) {
		continue;
	}
	$eda_sources = eda_palette_accent_sources( ! empty( $eda_p['sections']['accent'] ) );
	$eda_rows    = eda_pal_chrome_ratios( $eda_p['slots'] );
	foreach ( array_keys( eda_tones() ) as $eda_tone ) {
		$eda_rows = array_merge( $eda_rows, eda_pal_tone_ratios( $eda_p['slots'], $eda_sources, $eda_tone ) );
	}
	$eda_fail      = eda_pal_failures( $eda_rows );
	$eda_failures += count( $eda_fail );
	$eda_low       = eda_pal_below_target( $eda_rows );
	$eda_warned   += $eda_low ? 1 : 0;

	if ( $eda_headroom ) {
		foreach ( $eda_low as $eda_line ) {
			printf( "%-24s %s\n", $eda_p['name'], $eda_line );
		}
		continue;
	}
	if ( $eda_only ) {
		foreach ( $eda_rows as $eda_row ) {
			$eda_ok = $eda_row['ratio'] + 1e-9 >= $eda_row['min'];
			printf( "%-4s %-48s %6.2f:1 (min %.1f)\n", $eda_ok ? 'ok' : 'FAIL', $eda_row['label'], $eda_row['ratio'], $eda_row['min'] );
		}
		continue;
	}
	$eda_min = array();
	foreach ( $eda_rows as $eda_row ) {
		$eda_key             = strtok( $eda_row['label'], ' ' ) . ( EDA_GATE_UI === $eda_row['min'] ? ' ui' : ' text' );
		$eda_min[ $eda_key ] = min( $eda_min[ $eda_key ] ?? 99, $eda_row['ratio'] );
	}
	$eda_tones = array_intersect_key( $eda_min, array_flip( array( 'light text', 'light ui', 'dark text', 'dark ui', 'accent text', 'accent ui' ) ) );
	printf( "%-4s %-24s accent:%-3s %s  %s\n", $eda_fail ? 'FAIL' : 'ok', $eda_p['name'], empty( $eda_p['sections']['accent'] ) ? 'no' : 'yes', implode( '  ', array_map( static fn( $k, $v ) => sprintf( '%s %.2f', $k, $v ), array_keys( $eda_tones ), $eda_tones ) ), $eda_low ? 'HEADROOM WARNING (' . count( $eda_low ) . ')' : 'PASS' );
	foreach ( $eda_fail as $eda_line ) {
		echo "       {$eda_line}\n";
	}
}

if ( $eda_headroom ) {
	printf( "\n%d palette(s) below the internal target (text %.2f, UI %.2f). Advisory only.\n", $eda_warned, EDA_TARGET_TEXT, EDA_TARGET_UI );
}
echo $eda_failures ? "\n{$eda_failures} pair(s) failed.\n" : "\nAll pairs pass in every tone (minimum ratios shown).\n";
exit( $eda_failures ? 1 : 0 );
