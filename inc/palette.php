<?php
/**
 * Colour palettes and section tones: the single source of brand colour.
 *
 * A palette is a set of colour slots (ink, paper, accent, …). Aurelis, the approved Elite Auto
 * Dealer colours, is the fixed default. bin/palette-build.php derives the helper slots, gates
 * every tone for contrast and writes inc/palette-registry.php. Slots are printed once, on :root,
 * as --eda-c-{slot} (eda_palette_vars()); every role token (--eda-text, --eda-bg, …) of every tone
 * only references those, so re-pointing the --eda-c-* map recolours the whole site.
 *
 * Functional colours (status badges, focus rings, white text on photographs) are fixed in
 * main.css and are never part of a palette.
 *
 * Free of WordPress calls (bar the guarded theme_mod reads) so the bin/ scripts can load it.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || defined( 'EDA_PALETTE_CLI' ) || exit;

/**
 * Aurelis base colours: approved, frozen byte for byte (main.css :root carries the same fallbacks).
 *
 * @return array<string, string>
 */
function eda_palette_aurelis() {
	return array(
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
}

/**
 * Fixed functional colours (main.css :root). Not palette slots: they never change with a palette,
 * but the contrast gate checks them against every palette.
 *
 * @return array<string, string>
 */
function eda_palette_fixed_colors() {
	return array(
		'focus-ring'          => '#1f5fe0',
		'focus-ring-dark'     => '#8db2ff',
		'on-photo'            => '#ffffff',
		'scrim'               => '#121316', // Photo overlay: neutral in every palette (the Aurelis ink), so photos are never tinted.
		'status-reserved-bg'  => '#efe4cb',
		'status-reserved-ink' => '#5a4413',
		'status-sold-bg'      => '#121316',
		'status-sold-ink'     => '#ffffff',
	);
}

/**
 * The generated registry (bin/palette-build.php).
 *
 * @return array<string, array>
 */
function eda_palette_registry() {
	static $registry = null;
	if ( null === $registry ) {
		$file     = __DIR__ . '/palette-registry.php';
		$registry = is_file( $file ) ? require $file : array();
	}
	return $registry;
}

/**
 * Palettes offered (registry order: Aurelis first).
 *
 * @return array<string, array>
 */
function eda_palettes() {
	return array_filter( eda_palette_registry(), static fn( $palette ) => ! empty( $palette['selectable'] ) );
}

/**
 * Slug of the saved palette (Customizer theme_mod; Aurelis by default and for unknown values).
 *
 * @return string
 */
function eda_palette_active_slug() {
	$slug = function_exists( 'get_theme_mod' ) ? (string) get_theme_mod( 'eda_palette', 'aurelis' ) : 'aurelis';
	return isset( eda_palettes()[ $slug ] ) ? $slug : 'aurelis';
}

/**
 * Colour slots of a palette (slot => hex), helpers included; Aurelis when unknown.
 *
 * @param string|null $slug Palette slug; the active one by default.
 * @return array<string, string>
 */
function eda_palette_slots( $slug = null ) {
	$registry = eda_palette_registry();
	return $registry[ $slug ?? eda_palette_active_slug() ]['slots'] ?? $registry['aurelis']['slots'] ?? eda_palette_aurelis();
}

/**
 * Section tones: every role token as a colour reference.
 *   'slot'                  var(--eda-c-slot)
 *   'a-…'                   var(--eda-a-…), the Accent-tone sources (eda_palette_accent_sources())
 *   '@name'                 var(--eda-name), a fixed functional colour (eda_palette_fixed_colors())
 *   array( 'slot', 14 )     slot at 14% over transparent
 *   array( 'a', 85, 'b' )   85% a mixed with b
 * `light` is the page default (:root); `dark` is .section--dark, the photographic heroes and
 * .eda-tone-dark; `accent` (the palette accent as a fill) is .eda-tone-accent.
 *
 * @return array<string, array<string, string|array>>
 */
function eda_tones() {
	return array(
		'light'  => array(
			'bg'           => 'paper',
			'surface'      => 'surface',
			'surface-alt'  => 'paper-2',
			'text'         => 'text',
			'heading'      => 'text',
			'text-muted'   => 'muted',
			'line'         => 'line',
			'line-strong'  => 'ink',
			'field-line'   => 'field-line',
			'eyebrow'      => 'muted',
			'accent'       => 'accent',
			'accent-ink'   => 'accent-ink',
			'strong'       => 'ink',
			'on-strong'    => 'surface',
			'button-bg'    => 'ink',
			'button-text'  => 'paper',
			'button-hover' => 'graphite-2',
			'focus'        => '@focus-ring',
		),
		'dark'   => array(
			'bg'           => 'graphite',
			'surface'      => 'graphite-2',
			'surface-alt'  => 'ink',
			'text'         => 'surface',
			'heading'      => 'surface',
			'text-muted'   => 'muted-light',
			'line'         => array( 'surface', 14 ),
			'line-strong'  => array( 'surface', 45 ),
			'field-line'   => array( 'surface', 45 ),
			'eyebrow'      => 'accent-light',
			'accent'       => 'accent-light',
			'accent-ink'   => 'accent-light',
			'strong'       => 'surface',
			'on-strong'    => 'ink',
			'button-bg'    => 'paper',
			'button-text'  => 'ink',
			'button-hover' => 'surface',
			'focus'        => '@focus-ring-dark',
		),
		'accent' => array(
			'bg'           => 'a-bg',
			'surface'      => 'a-card',
			'surface-alt'  => 'a-card',
			'text'         => 'a-ink',
			'heading'      => 'a-ink',
			'text-muted'   => 'a-muted',
			'line'         => array( 'a-ink', 22 ),
			'line-strong'  => 'a-ink',
			'field-line'   => 'a-ink',
			'eyebrow'      => 'a-ink',
			'accent'       => 'a-ink',
			'accent-ink'   => 'a-ink',
			'strong'       => 'a-ink',
			'on-strong'    => 'a-bg',
			'button-bg'    => 'a-ink',
			'button-text'  => 'a-bg',
			'button-hover' => 'a-hover',
			'focus'        => 'a-focus',
		),
	);
}

/**
 * Chrome outside the section tones (header, photographs, footer), as tone references on :root.
 * Photographs are never tinted by a palette: their overlay (--eda-scrim) and text are fixed colours.
 *
 * @return array<string, string|array>
 */
function eda_palette_chrome() {
	return array(
		'header-glass'      => array( 'paper', 94 ),
		'header-glass-blur' => array( 'paper', 82 ),
		'bar-glass'         => array( 'paper', 97 ),
		'photo-bg'          => 'graphite',
		'footer-bg'         => 'ink',
		'footer-text'       => 'muted-light',
		'footer-heading'    => 'surface',
		'footer-line'       => array( 'surface', 14 ),
	);
}

/**
 * Accent-tone sources: the palette's own accent slots, or light slots when its Accent tone failed
 * the contrast gate (an .eda-tone-accent section then renders light instead of unreadable).
 * Focus is a role in every tone: the fixed blue rings on light and dark, and on an accent fill the
 * derived accent-focus (a dark neutral where it reaches 3:1), because the blues cannot read on every
 * accent colour. '@name' points at a fixed functional colour.
 *
 * @param bool $accent Whether the palette's Accent tone passed.
 * @return array<string, string>
 */
function eda_palette_accent_sources( $accent ) {
	return $accent
		? array(
			'a-bg'    => 'accent',
			'a-card'  => 'accent-card',
			'a-ink'   => 'on-accent',
			'a-muted' => 'accent-muted',
			'a-focus' => 'accent-focus',
			'a-hover' => 'accent-hover',
		)
		: array(
			'a-bg'    => 'paper',
			'a-card'  => 'surface',
			'a-ink'   => 'text',
			'a-muted' => 'muted',
			'a-focus' => '@focus-ring',
			'a-hover' => 'graphite-2',
		);
}

/**
 * Every palette-specific value as one flat custom-property map (printed once on :root).
 * Functional colours are deliberately absent.
 *
 * @param string|null $slug Palette slug; the active one by default.
 * @return array<string, string>
 */
function eda_palette_vars( $slug = null ) {
	$slug = $slug ?? eda_palette_active_slug();
	$vars = array();
	foreach ( eda_palette_slots( $slug ) as $key => $hex ) {
		$vars[ "--eda-c-{$key}" ] = $hex;
	}
	foreach ( eda_palette_accent_sources( ! empty( eda_palette_registry()[ $slug ]['sections']['accent'] ) ) as $key => $slot ) {
		$vars[ "--eda-{$key}" ] = str_starts_with( $slot, '@' ) ? 'var(--eda-' . substr( $slot, 1 ) . ')' : "var(--eda-c-{$slot})";
	}
	return $vars;
}

/**
 * CSS value of a tone reference (see eda_tones()).
 *
 * @param string|array $ref Reference.
 * @return string
 */
function eda_tone_value( $ref ) {
	$var = static function ( $name ) {
		if ( str_starts_with( $name, '@' ) ) {
			return 'var(--eda-' . substr( $name, 1 ) . ')';
		}
		return str_starts_with( $name, 'a-' ) ? "var(--eda-{$name})" : "var(--eda-c-{$name})";
	};
	if ( is_string( $ref ) ) {
		return $var( $ref );
	}
	return sprintf( 'color-mix(in srgb, %s %d%%, %s)', $var( $ref[0] ), $ref[1], isset( $ref[2] ) ? $var( $ref[2] ) : 'transparent' );
}

/**
 * Role-token declarations of one tone.
 *
 * @param string $tone light|dark|accent.
 * @return string
 */
function eda_tone_decl( $tone ) {
	$decl = '';
	foreach ( eda_tones()[ $tone ] as $role => $ref ) {
		$decl .= "--eda-{$role}:" . eda_tone_value( $ref ) . ';';
	}
	return $decl;
}

/**
 * Section patterns: label and repeating tone cycle.
 *
 * @return array<string, array{label: string, cycle: string[]}>
 */
function eda_patterns() {
	return array(
		'original'          => array(
			'label' => 'Original — the approved Aurelis design',
			'cycle' => array(),
		),
		'dark-light'        => array(
			'label' => 'Dark / Light alternating',
			'cycle' => array( 'dark', 'light' ),
		),
		'light-dark'        => array(
			'label' => 'Light / Dark alternating',
			'cycle' => array( 'light', 'dark' ),
		),
		'dark-light-accent' => array(
			'label' => 'Dark / Light / Accent',
			'cycle' => array( 'dark', 'light', 'accent' ),
		),
		'light-dark-accent' => array(
			'label' => 'Light / Dark / Accent',
			'cycle' => array( 'light', 'dark', 'accent' ),
		),
		'mostly-light'      => array(
			'label' => 'Mostly light',
			'cycle' => array( 'light', 'light', 'light', 'dark' ),
		),
		'mostly-dark'       => array(
			'label' => 'Mostly dark',
			'cycle' => array( 'dark', 'dark', 'dark', 'light' ),
		),
	);
}

/**
 * Saved section pattern (Original by default and for unknown values).
 *
 * @return string
 */
function eda_pattern_active() {
	$saved = function_exists( 'get_theme_mod' ) ? (string) get_theme_mod( 'eda_section_pattern', 'original' ) : 'original';
	return isset( eda_patterns()[ $saved ] ) ? $saved : 'original';
}

/**
 * Tones a palette offers: Dark and Light always, Accent only when it passed the gate.
 *
 * @param string $slug Palette slug.
 * @return string[]
 */
function eda_palette_roles( $slug ) {
	return array_keys(
		array_filter(
			eda_palette_registry()[ $slug ]['sections'] ?? array(
				'dark'  => true,
				'light' => true,
			)
		)
	);
}

/**
 * The pattern's cycle without the tones the palette does not offer.
 *
 * @param string $slug    Palette slug.
 * @param string $pattern Pattern key.
 * @return string[]
 */
function eda_pattern_sequence( $slug, $pattern ) {
	return array_values( array_intersect( eda_patterns()[ $pattern ]['cycle'] ?? array(), eda_palette_roles( $slug ) ) );
}

/**
 * Every palette-dependent style: palette variables, the role tokens of each tone and the section
 * pattern. One inline style after main.css; values only reference the --eda-c-* map.
 *
 * Patterned sections: the top-level `section.section` elements of the page (photographic heroes
 * are `section.hero` / `div.page-intro`, so never counted), except those with their own
 * `eda-tone-{light|dark|accent}` class. Original adds nothing.
 *
 * @param string|null $slug    Palette slug; the active one by default.
 * @param string|null $pattern Pattern key; the active one by default.
 * @return string
 */
function eda_palette_css( $slug = null, $pattern = null ) {
	$slug    = $slug ?? eda_palette_active_slug();
	$pattern = $pattern ?? eda_pattern_active();
	$paint   = 'background-color:var(--eda-bg);color:var(--eda-text);';

	$root = '';
	foreach ( eda_palette_vars( $slug ) as $name => $value ) {
		$root .= "{$name}:{$value};";
	}
	foreach ( eda_palette_chrome() as $name => $ref ) {
		$root .= "--eda-{$name}:" . eda_tone_value( $ref ) . ';';
	}
	$css  = ":root{{$root}}";
	$css .= ':root,.eda-tone-light{' . eda_tone_decl( 'light' ) . '}';
	$css .= '.section--dark,.hero,.page-intro--hero,.eda-tone-dark{' . eda_tone_decl( 'dark' ) . '}';
	$css .= '.eda-tone-accent{' . eda_tone_decl( 'accent' ) . '}';
	$css .= ".eda-tone-light,.eda-tone-dark,.eda-tone-accent{{$paint}}";

	$sequence = eda_pattern_sequence( $slug, $pattern );
	foreach ( array_unique( $sequence ) as $tone ) {
		$selectors = array();
		foreach ( array_keys( $sequence, $tone, true ) as $i ) {
			$selectors[] = sprintf( '.site-main>section.section:not([class*="eda-tone-"]):nth-child(%1$dn+%2$d of section.section)', count( $sequence ), $i + 1 );
		}
		$css .= implode( ',', $selectors ) . '{' . eda_tone_decl( $tone ) . $paint . '}';
	}
	return $css;
}
