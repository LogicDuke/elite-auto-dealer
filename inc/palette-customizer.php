<?php
/**
 * Colours (Appearance → Customize): palette and section pattern. Administrators only: the
 * Customizer needs `edit_theme_options`, which Dealership staff do not have (their Site Settings
 * page holds business details only).
 *
 * Two allowlisted theme_mods, `eda_palette` (a slug from the contrast-gated registry) and
 * `eda_section_pattern`. Everything they change is the one inline style after main.css
 * (#eda-main-inline-css, eda_palette_css()); the preview re-renders it through a selective-refresh
 * partial, so no front-end palette script is needed.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Print the palette CSS after main.css.
 */
function eda_enqueue_palette_css() {
	wp_add_inline_style( 'eda-main', eda_palette_css() );
}
add_action( 'wp_enqueue_scripts', 'eda_enqueue_palette_css', 20 );

/**
 * Sanitise a palette slug (unknown → Aurelis).
 *
 * @param string $slug Submitted slug.
 * @return string
 */
function eda_sanitize_palette( $slug ) {
	return isset( eda_palettes()[ $slug ] ) ? $slug : 'aurelis';
}

/**
 * Sanitise a section pattern (unknown → Original).
 *
 * @param string $pattern Submitted pattern.
 * @return string
 */
function eda_sanitize_section_pattern( $pattern ) {
	return isset( eda_patterns()[ $pattern ] ) ? $pattern : 'original';
}

/**
 * Register the section, settings, control and live-preview partial.
 *
 * @param WP_Customize_Manager $wp_customize Customizer.
 */
function eda_palette_customize_register( $wp_customize ) {
	require_once __DIR__ . '/palette-control.php';

	$wp_customize->add_section(
		'eda_colours',
		array(
			'title'       => __( 'Colours', 'elite-auto-dealer' ),
			'description' => __( 'Colours for the whole site. Every palette is checked for readable contrast (WCAG AA). Status badges and focus outlines keep their fixed colours.', 'elite-auto-dealer' ),
			'priority'    => 25,
		)
	);
	$wp_customize->add_setting(
		'eda_palette',
		array(
			'type'              => 'theme_mod',
			'default'           => 'aurelis',
			'transport'         => 'postMessage',
			'sanitize_callback' => 'eda_sanitize_palette',
		)
	);
	$wp_customize->add_setting(
		'eda_section_pattern',
		array(
			'type'              => 'theme_mod',
			'default'           => 'original',
			'transport'         => 'postMessage',
			'sanitize_callback' => 'eda_sanitize_section_pattern',
		)
	);
	$wp_customize->add_control(
		new EDA_Palette_Control(
			$wp_customize,
			'eda_palette',
			array(
				'label'       => __( 'Colour palette', 'elite-auto-dealer' ),
				'description' => __( 'Choose a palette and a section pattern; the preview updates with your real pages.', 'elite-auto-dealer' ),
				'section'     => 'eda_colours',
				'settings'    => array(
					'default' => 'eda_palette',
					'pattern' => 'eda_section_pattern',
				),
			)
		)
	);
	$wp_customize->selective_refresh->add_partial(
		'eda_colours',
		array(
			'selector'            => '#eda-main-inline-css',
			'settings'            => array( 'eda_palette', 'eda_section_pattern' ),
			'container_inclusive' => false,
			'fallback_refresh'    => true,
			'render_callback'     => static fn() => eda_palette_css(),
		)
	);
}
add_action( 'customize_register', 'eda_palette_customize_register' );

/**
 * Control styles.
 */
function eda_palette_control_styles() {
	?>
	<style>
		.eda-style-reset{margin:4px 0 8px}
		.eda-palette-cards{display:grid;gap:8px;min-width:0;margin:8px 0 0;padding:0;border:0}
		.eda-palette-card{position:relative;display:grid;gap:6px;padding:8px;border:1px solid #c3c4c7;border-radius:6px;background:#fff;cursor:pointer}
		.eda-palette-card:has(input:checked){border-color:#2271b1;box-shadow:0 0 0 1px #2271b1}
		.eda-palette-card:has(input:focus-visible){outline:2px solid #2271b1;outline-offset:2px}
		.eda-palette-card input{position:absolute;opacity:0;pointer-events:none}
		.eda-palette-card__swatches{display:grid;grid-auto-flow:column;grid-auto-columns:1fr;height:28px;border-radius:4px;overflow:hidden;box-shadow:inset 0 0 0 1px rgba(0,0,0,.12)}
		.eda-palette-card__name{font-weight:600}
		.eda-palette-card__name em{font-weight:400;color:#646970}
		.eda-palette-card__roles{font-size:12px;color:#646970}
		.eda-pattern{min-width:0;margin:-2px 0 4px;padding:8px 10px 10px;border:1px solid #2271b1;border-radius:6px;background:#f6f7f7}
		.eda-pattern__title{float:left;width:100%;margin:0 0 2px;padding:0;font-size:14px;font-weight:600;line-height:1.5}
		.eda-pattern__description{clear:both;margin:0 0 6px;font-size:12px;font-style:italic;color:#646970}
		.eda-pattern__option{display:flex;align-items:center;gap:8px;padding:4px 0;cursor:pointer}
		.eda-pattern__option input{margin:0}
		.eda-pattern__note{margin:6px 0 0;font-size:12px;color:#646970}
	</style>
	<?php
}
add_action( 'customize_controls_print_styles', 'eda_palette_control_styles' );

/**
 * Keep the pattern group under the selected card, note skipped tones, wire the reset.
 */
function eda_palette_control_script() {
	/* translators: %s: section tone names, e.g. "Accent". */
	$text = array( 'skip' => __( 'This palette has no readable %s sections, so the pattern skips them.', 'elite-auto-dealer' ) );
	?>
	<script>
	wp.customize( 'eda_palette', 'eda_section_pattern', function ( palette, pattern ) {
		var text = <?php echo wp_json_encode( $text ); ?>;
		function sync() {
			var group = document.getElementById( 'eda-pattern' );
			var input = document.querySelector( '.eda-palette-card input[value="' + palette.get() + '"]' );
			if ( ! group || ! input ) {
				return;
			}
			var card = input.closest( '.eda-palette-card' );
			var roles = card.dataset.roles.split( ' ' );
			var pat = pattern.get();
			var missing = 'original' === pat ? [] : [ 'Accent' ].filter( function ( role ) { return pat.indexOf( role.toLowerCase() ) > -1 && roles.indexOf( role.toLowerCase() ) < 0; } );
			var note = group.querySelector( '.eda-pattern__note' );
			note.textContent = missing.length ? text.skip.replace( '%s', missing.join( ' / ' ) ) : '';
			note.hidden = ! note.textContent;
			if ( card.nextElementSibling !== group ) {
				var pane = card.closest( '.wp-full-overlay-sidebar-content' );
				var top = card.getBoundingClientRect().top;
				card.after( group );
				if ( pane ) {
					pane.scrollTop += card.getBoundingClientRect().top - top;
				}
			}
		}
		palette.bind( sync );
		pattern.bind( sync );
		wp.customize.control( 'eda_palette', function ( control ) {
			control.deferred.embedded.done( function () {
				sync();
				var reset = control.container[0].querySelector( '[data-eda-style-reset]' );
				if ( reset ) {
					reset.addEventListener( 'click', function () {
						palette.set( 'aurelis' );
						pattern.set( 'original' );
					} );
				}
			} );
		} );
		wp.customize.section( 'eda_colours', function ( section ) {
			section.expanded.bind( sync );
		} );
	} );
	</script>
	<?php
}
add_action( 'customize_controls_print_footer_scripts', 'eda_palette_control_script' );
