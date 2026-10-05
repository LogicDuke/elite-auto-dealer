<?php
/**
 * Colour maths, helper-slot derivation and the contrast gate, shared by bin/palette-build.php and
 * bin/contrast-gate.php. The maths is the Elite Nail Studio palette library's.
 *
 * Thresholds (WCAG 2.2 AA): text 4.5:1, UI (focus rings, field and control edges) 3:1. The build fails
 * only on these. The internal design target (text 4.70:1, UI 3.20:1) is advisory headroom.
 *
 * @package EliteAutoDealer
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

defined( 'EDA_PALETTE_CLI' ) || define( 'EDA_PALETTE_CLI', true );
require_once __DIR__ . '/../inc/palette.php';

const EDA_GATE_TEXT = 4.5;
const EDA_GATE_UI   = 3.0;

// Internal design target (advisory headroom above the gate; never a build failure).
const EDA_TARGET_TEXT = 4.70;
const EDA_TARGET_UI   = 3.20;

/* Colour helpers --------------------------------------------------------- */

/**
 * Hex to [r, g, b].
 *
 * @param string $hex #rrggbb.
 * @return int[]
 */
function eda_pal_rgb( $hex ) {
	return array_map( 'hexdec', str_split( ltrim( $hex, '#' ), 2 ) );
}

/**
 * [r, g, b] to hex.
 *
 * @param float[] $rgb Channels.
 * @return string
 */
function eda_pal_hex( array $rgb ) {
	return '#' . implode( '', array_map( static fn( $c ) => sprintf( '%02x', (int) max( 0, min( 255, round( $c ) ) ) ), $rgb ) );
}

/**
 * Mix two colours; $t is the share of $b.
 *
 * @param string $a Colour.
 * @param string $b Colour.
 * @param float  $t Share of $b (0–1).
 * @return string
 */
function eda_pal_mix( $a, $b, $t ) {
	$x = eda_pal_rgb( $a );
	$y = eda_pal_rgb( $b );
	return eda_pal_hex( array( $x[0] + ( $y[0] - $x[0] ) * $t, $x[1] + ( $y[1] - $x[1] ) * $t, $x[2] + ( $y[2] - $x[2] ) * $t ) );
}

/**
 * Relative luminance (WCAG).
 *
 * @param string $hex Colour.
 * @return float
 */
function eda_pal_lum( $hex ) {
	$c = array_map(
		static function ( $v ) {
			$v /= 255;
			return $v <= 0.03928 ? $v / 12.92 : ( ( $v + 0.055 ) / 1.055 ) ** 2.4;
		},
		eda_pal_rgb( $hex )
	);
	return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}

/**
 * Contrast ratio (WCAG).
 *
 * @param string $a Colour.
 * @param string $b Colour.
 * @return float
 */
function eda_pal_ratio( $a, $b ) {
	$la = eda_pal_lum( $a );
	$lb = eda_pal_lum( $b );
	return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
}

/**
 * Whether $fg reaches $min on every background.
 *
 * @param string   $fg  Colour.
 * @param string[] $bgs Backgrounds.
 * @param float    $min Minimum ratio.
 * @return bool
 */
function eda_pal_reads( $fg, array $bgs, $min = EDA_GATE_TEXT ) {
	foreach ( $bgs as $bg ) {
		if ( eda_pal_ratio( $fg, $bg ) + 1e-9 < $min ) {
			return false;
		}
	}
	return true;
}

/**
 * Smallest push of $c toward each target in turn until $ok passes.
 *
 * @param string   $c       Colour.
 * @param string[] $targets Targets, in order.
 * @param callable $ok      Test.
 * @return array{0: string, 1: float} Colour and push (0 = unchanged, -1 = impossible).
 */
function eda_pal_push( $c, array $targets, callable $ok ) {
	if ( $ok( $c ) ) {
		return array( $c, 0.0 );
	}
	$from = $c;
	foreach ( $targets as $i => $target ) {
		for ( $t = 0.02; $t <= 1.0001; $t += 0.02 ) {
			$try = eda_pal_mix( $from, $target, $t );
			if ( $ok( $try ) ) {
				return array( $try, $i + $t );
			}
		}
		$from = $target;
	}
	return array( $c, -1.0 );
}

/**
 * Largest share t of $b in mix($a, $b, t) that still passes.
 *
 * @param string   $a  Colour.
 * @param string   $b  Colour.
 * @param callable $ok Test.
 * @return string
 */
function eda_pal_dimmest( $a, $b, callable $ok ) {
	for ( $t = 0.98; $t >= 0; $t -= 0.02 ) {
		$try = eda_pal_mix( $a, $b, $t );
		if ( $ok( $try ) ) {
			return $try;
		}
	}
	return $a;
}

/* Derivation ------------------------------------------------------------- */

/**
 * Helper slots derived from the twelve base slots (never changes a base slot).
 *   accent-ink   accent darkened until it reads as text on paper, surface and paper-2
 *   field-line   line darkened until it is a 3:1 boundary on paper, surface and paper-2
 *   on-accent    ink or surface, whichever reads on the accent fill (pushed if neither)
 *   accent-card  accent 8% toward on-accent (cards in an Accent section)
 *   accent-muted dimmest on-accent that still reads on the accent and its card
 *   accent-focus focus ring on an accent fill: ink if it reaches 3:1 on the accent and its card,
 *                else surface, else pushed toward on-accent
 *   accent-hover button hover in an Accent section: 85% on-accent over the accent, strengthened
 *                toward on-accent until the button label (the accent) reads on it
 *
 * Helpers aim at $text / $ui. The build passes the internal design target (4.70 / 3.20) so new
 * palettes get headroom, and the WCAG minimum (4.5 / 3.0) for Aurelis, whose approved helpers are
 * frozen. Either way the gate itself checks the WCAG minimum.
 *
 * @param array<string, string> $b    Base slots.
 * @param float                 $text Text contrast the helpers aim at.
 * @param float                 $ui   UI contrast the helpers aim at.
 * @return array{slots: array<string, string>, notes: string[]}
 */
function eda_pal_helpers( array $b, $text = EDA_GATE_TEXT, $ui = EDA_GATE_UI ) {
	$notes = array();
	$note  = static function ( $slot, $push, $what ) use ( &$notes ) {
		if ( 0.0 !== (float) $push ) {
			$notes[] = sprintf( '%s: %s (push %.2f)', $slot, $what, $push );
		}
	};
	$light = array( $b['paper'], $b['surface'], $b['paper-2'] );

	list( $accent_ink, $p ) = eda_pal_push( $b['accent'], array( $b['ink'], '#000000' ), static fn( $c ) => eda_pal_reads( $c, $light, $text ) );
	$note( 'accent-ink', $p, 'accent darkened for text on light surfaces' );
	list( $field_line, $p ) = eda_pal_push( $b['line'], array( $b['text'], '#000000' ), static fn( $c ) => eda_pal_reads( $c, $light, $ui ) );
	$note( 'field-line', $p, 'line darkened to a field boundary' );

	if ( eda_pal_reads( $b['ink'], array( $b['accent'] ), $text ) ) {
		$on = $b['ink'];
	} elseif ( eda_pal_reads( $b['surface'], array( $b['accent'] ), $text ) ) {
		$on = $b['surface'];
	} else {
		$dark           = eda_pal_ratio( '#000000', $b['accent'] ) >= eda_pal_ratio( '#ffffff', $b['accent'] );
		list( $on, $p ) = eda_pal_push( $dark ? $b['ink'] : $b['surface'], array( $dark ? '#000000' : '#ffffff' ), static fn( $c ) => eda_pal_reads( $c, array( $b['accent'] ), $text ) );
		$note( 'on-accent', $p < 0 ? 1 : $p, 'label pushed to read on the accent' );
	}
	$card              = eda_pal_mix( $b['accent'], $on, 0.08 );
	list( $focus, $p ) = eda_pal_push( eda_pal_reads( $b['ink'], array( $b['accent'], $card ), $ui ) ? $b['ink'] : $b['surface'], array( $on ), static fn( $c ) => eda_pal_reads( $c, array( $b['accent'], $card ), $ui ) );
	$note( 'accent-focus', $p, 'focus ring pushed to read on the accent fill' );
	$muted = eda_pal_dimmest( $on, $b['accent'], static fn( $c ) => eda_pal_reads( $c, array( $b['accent'], $card ), $text ) );
	$share = 0.85;
	while ( $share < 1.0 && ! eda_pal_reads( $b['accent'], array( eda_pal_mix( $b['accent'], $on, $share ) ), $text ) ) {
		$share += 0.01;
	}

	return array(
		'slots' => array(
			'accent-ink'   => $accent_ink,
			'on-accent'    => $on,
			'field-line'   => $field_line,
			'accent-card'  => $card,
			'accent-muted' => $muted,
			'accent-focus' => $focus,
			'accent-hover' => eda_pal_mix( $b['accent'], $on, min( 1.0, $share ) ),
		),
		'notes' => $notes,
	);
}

/* Gate ------------------------------------------------------------------- */

/**
 * Hex of a tone reference (eda_tones()) over a backdrop (alpha composited).
 *
 * @param string|array $ref     Reference.
 * @param array        $slots   Palette slots.
 * @param array        $sources Accent-tone sources.
 * @param string       $over    Backdrop for transparent mixes.
 * @return string
 */
function eda_pal_resolve( $ref, array $slots, array $sources, $over ) {
	$hex = static function ( $name ) use ( $slots, $sources ) {
		if ( str_starts_with( $name, '@' ) ) {
			return eda_palette_fixed_colors()[ substr( $name, 1 ) ];
		}
		$name = $sources[ $name ] ?? $name;
		return str_starts_with( $name, '@' ) ? eda_palette_fixed_colors()[ substr( $name, 1 ) ] : $slots[ $name ];
	};
	if ( is_string( $ref ) ) {
		return $hex( $ref );
	}
	return eda_pal_mix( isset( $ref[2] ) ? $hex( $ref[2] ) : $over, $hex( $ref[0] ), $ref[1] / 100 );
}

/**
 * Tone pairs: [ fg role, bg roles, minimum ].
 * Text and headings, muted copy, eyebrows and accent text on every surface; button labels on the
 * button and its hover; label on a strong fill; focus ring, field boundary and button edge 3:1.
 *
 * @return array<int, array{0: string, 1: string[], 2: float}>
 */
function eda_pal_pairs() {
	$surfaces = array( 'bg', 'surface', 'surface-alt' );
	return array(
		array( 'text', $surfaces, EDA_GATE_TEXT ),
		array( 'heading', $surfaces, EDA_GATE_TEXT ),
		array( 'text-muted', $surfaces, EDA_GATE_TEXT ),
		array( 'eyebrow', $surfaces, EDA_GATE_TEXT ),
		array( 'accent-ink', $surfaces, EDA_GATE_TEXT ),
		array( 'button-text', array( 'button-bg', 'button-hover' ), EDA_GATE_TEXT ),
		array( 'on-strong', array( 'strong' ), EDA_GATE_TEXT ),
		array( 'focus', $surfaces, EDA_GATE_UI ),
		array( 'field-line', $surfaces, EDA_GATE_UI ),
		array( 'button-bg', $surfaces, EDA_GATE_UI ),
	);
}

/**
 * Pairs of one tone with their ratios.
 *
 * @param array  $slots   Palette slots.
 * @param array  $sources Accent-tone sources.
 * @param string $tone    light|dark|accent.
 * @return array<int, array{label: string, ratio: float, min: float}>
 */
function eda_pal_tone_ratios( array $slots, array $sources, $tone ) {
	$roles = eda_tones()[ $tone ];
	$out   = array();
	foreach ( eda_pal_pairs() as list( $fg, $bgs, $min ) ) {
		foreach ( $bgs as $bg ) {
			$back  = eda_pal_resolve( $roles[ $bg ], $slots, $sources, eda_pal_resolve( $roles['bg'], $slots, $sources, $slots['paper'] ) );
			$out[] = array(
				'label' => "{$tone} {$fg} on {$bg}",
				'ratio' => eda_pal_ratio( eda_pal_resolve( $roles[ $fg ], $slots, $sources, $back ), $back ),
				'min'   => $min,
			);
		}
	}
	return $out;
}

/**
 * Chrome and functional pairs (independent of section tones).
 * Footer copy and headings on the footer; focus ring in the footer; white text on the photo scrim
 * and the photo background, whose base must also be dark (luminance <= 0.04, as a ratio row
 * against a 0.04-luminance grey); the fixed Reserved / Sold badges on their own fills and on
 * every light card surface (Sold's dark fill 3:1, Reserved's label 4.5:1).
 *
 * @param array $slots Palette slots.
 * @return array<int, array{label: string, ratio: float, min: float}>
 */
function eda_pal_chrome_ratios( array $slots ) {
	$fixed  = eda_palette_fixed_colors();
	$chrome = array_map( static fn( $ref ) => eda_pal_resolve( $ref, $slots, array(), $slots['paper'] ), eda_palette_chrome() );
	$rows   = array(
		array( 'footer text on footer', $chrome['footer-text'], $chrome['footer-bg'], EDA_GATE_TEXT ),
		array( 'footer heading on footer', $chrome['footer-heading'], $chrome['footer-bg'], EDA_GATE_TEXT ),
		array( 'footer focus ring on footer', $fixed['focus-ring-dark'], $chrome['footer-bg'], EDA_GATE_UI ),
		array( 'photo text on scrim', $fixed['on-photo'], $fixed['scrim'], EDA_GATE_TEXT ),
		array( 'photo text on photo background', $fixed['on-photo'], $chrome['photo-bg'], EDA_GATE_TEXT ),
		array( 'photo focus ring on scrim', $fixed['focus-ring-dark'], $fixed['scrim'], EDA_GATE_UI ),
		array( 'Reserved label on Reserved fill', $fixed['status-reserved-ink'], $fixed['status-reserved-bg'], EDA_GATE_TEXT ),
		array( 'Sold label on Sold fill', $fixed['status-sold-ink'], $fixed['status-sold-bg'], EDA_GATE_TEXT ),
	);
	foreach ( array( 'paper', 'surface', 'paper-2' ) as $surface ) {
		$rows[] = array( "Reserved label on {$surface}", $fixed['status-reserved-ink'], $slots[ $surface ], EDA_GATE_TEXT );
		$rows[] = array( "Sold fill on {$surface}", $fixed['status-sold-bg'], $slots[ $surface ], EDA_GATE_UI );
	}
	$out = array_map(
		static fn( $row ) => array(
			'label' => $row[0],
			'ratio' => eda_pal_ratio( $row[1], $row[2] ),
			'min'   => $row[3],
		),
		$rows
	);
	// A dark scrim base: luminance <= 0.04, so white text stays readable at every overlay strength used.
	$out[] = array(
		'label' => 'scrim base is dark (luminance ' . sprintf( '%.3f', eda_pal_lum( $fixed['scrim'] ) ) . ' <= 0.040)',
		'ratio' => eda_pal_lum( $fixed['scrim'] ) <= 0.04 ? 1.0 : 0.0,
		'min'   => 1.0,
	);
	return $out;
}

/**
 * Failures among ratio rows.
 *
 * @param array $rows Rows from eda_pal_tone_ratios() / eda_pal_chrome_ratios().
 * @return string[]
 */
function eda_pal_failures( array $rows ) {
	$fail = array();
	foreach ( $rows as $row ) {
		if ( $row['ratio'] + 1e-9 < $row['min'] ) {
			$fail[] = sprintf( '%s %.2f:1 (min %.1f)', $row['label'], $row['ratio'], $row['min'] );
		}
	}
	return $fail;
}

/**
 * Rows that pass the gate but sit below the internal design target (advisory headroom).
 *
 * @param array $rows Rows from eda_pal_tone_ratios() / eda_pal_chrome_ratios().
 * @return string[]
 */
function eda_pal_below_target( array $rows ) {
	$low = array();
	foreach ( $rows as $row ) {
		$target = EDA_GATE_TEXT === $row['min'] ? EDA_TARGET_TEXT : ( EDA_GATE_UI === $row['min'] ? EDA_TARGET_UI : 0 );
		if ( $row['ratio'] + 1e-9 >= $row['min'] && $row['ratio'] + 1e-9 < $target ) {
			$low[] = sprintf( '%s %.2f:1 (target %.2f)', $row['label'], $row['ratio'], $target );
		}
	}
	return $low;
}
