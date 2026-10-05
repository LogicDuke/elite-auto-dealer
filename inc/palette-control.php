<?php
/**
 * Palette control: one radio swatch card per palette (five swatches, name, the section tones it
 * offers), the section pattern directly under the selected card, and a reset to the Aurelis
 * default. Customizer only (the Elite Nail Studio control, adapted).
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Swatch cards + section pattern (settings: 'default' = eda_palette, 'pattern' = eda_section_pattern).
 */
class EDA_Palette_Control extends WP_Customize_Control {

	/**
	 * Control type.
	 *
	 * @var string
	 */
	public $type = 'eda-palette';

	/**
	 * Cards.
	 */
	public function render_content() {
		$current = $this->value();
		$names   = array(
			'dark'   => __( 'Dark', 'elite-auto-dealer' ),
			'light'  => __( 'Light', 'elite-auto-dealer' ),
			'accent' => __( 'Accent', 'elite-auto-dealer' ),
		);
		?>
		<span class="customize-control-title" aria-hidden="true"><?php echo esc_html( $this->label ); ?></span>
		<?php if ( $this->description ) : ?>
			<span class="description customize-control-description"><?php echo esc_html( $this->description ); ?></span>
		<?php endif; ?>
		<p class="eda-style-reset">
			<button type="button" class="button-link" data-eda-style-reset><?php esc_html_e( 'Restore the Aurelis default (Aurelis, Original)', 'elite-auto-dealer' ); ?></button>
		</p>
		<fieldset class="eda-palette-cards">
			<legend class="screen-reader-text"><?php echo esc_html( $this->label ); ?></legend>
			<?php
			foreach ( eda_palettes() as $slug => $palette ) :
				$roles = array_intersect_key( $names, array_flip( eda_palette_roles( $slug ) ) );
				?>
				<label class="eda-palette-card" data-roles="<?php echo esc_attr( implode( ' ', array_keys( $roles ) ) ); ?>">
					<input type="radio" name="<?php echo esc_attr( '_customize-radio-' . $this->id ); ?>" value="<?php echo esc_attr( $slug ); ?>" <?php $this->link(); ?> <?php checked( $current, $slug ); ?>>
					<span class="eda-palette-card__swatches" aria-hidden="true">
						<?php foreach ( $palette['colors'] as $color ) : ?>
							<span style="background:<?php echo esc_attr( $color ); ?>"></span>
						<?php endforeach; ?>
					</span>
					<span class="eda-palette-card__name"><?php echo esc_html( $palette['name'] ); ?><?php echo $palette['default'] ? ' <em>' . esc_html__( '(default)', 'elite-auto-dealer' ) . '</em>' : ''; ?></span>
					<span class="eda-palette-card__roles"><?php echo esc_html( __( 'Sections:', 'elite-auto-dealer' ) . ' ' . implode( ' · ', $roles ) ); ?></span>
				</label>
				<?php
				if ( $slug === $current ) {
					$this->render_pattern();
				}
			endforeach;
			if ( ! isset( eda_palettes()[ $current ] ) ) {
				$this->render_pattern();
			}
			?>
		</fieldset>
		<?php
	}

	/**
	 * The section pattern (linked to the 'pattern' setting).
	 */
	private function render_pattern() {
		$pattern = $this->value( 'pattern' );
		?>
		<fieldset class="eda-pattern" id="eda-pattern">
			<legend class="eda-pattern__title"><?php esc_html_e( 'Section pattern', 'elite-auto-dealer' ); ?></legend>
			<p class="eda-pattern__description"><?php esc_html_e( 'Sets the colour of every page section by its position. Photographic heroes keep their design; give a section the class eda-tone-dark, eda-tone-light or eda-tone-accent to set it on its own.', 'elite-auto-dealer' ); ?></p>
			<?php foreach ( eda_patterns() as $key => $definition ) : ?>
				<label class="eda-pattern__option">
					<input type="radio" name="_customize-radio-eda_section_pattern" value="<?php echo esc_attr( $key ); ?>" <?php $this->link( 'pattern' ); ?> <?php checked( $pattern, $key ); ?>>
					<?php echo esc_html( $definition['label'] ); ?>
				</label>
			<?php endforeach; ?>
			<p class="eda-pattern__note" aria-live="polite" hidden></p>
		</fieldset>
		<?php
	}
}
