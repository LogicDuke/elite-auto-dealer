<?php
/**
 * Vehicle search form: a plain GET form to /vehicles/ (works without JS).
 * Args: 'context' => 'home' (strip) | 'inventory' (filter bar with sort and reset).
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

$eda_context = $args['context'] ?? 'home';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- prefilling a public GET form.
$eda_params = wp_unslash( $_GET );

// A real term archive (e.g. /vehicles/make/bmw/) preselects its own term.
if ( eda_is_vehicle_term_landing() ) {
	$eda_term = get_queried_object();
	$eda_base = eda_vehicle_taxonomy_definitions()[ $eda_term->taxonomy ][2] ?? '';
	if ( $eda_base ) {
		$eda_params[ $eda_base ] = $eda_term->slug;
	}
}

// Only makes/models with vehicles in stock; an unknown make or a model of another make is reset.
$eda_current = eda_vehicle_search_state( $eda_params );
$eda_data    = eda_make_model_data( true );
$eda_fuels   = get_terms( array( 'taxonomy' => 'vehicle_fuel_type' ) );
$eda_id      = 'vehicle-search-' . $eda_context;

wp_enqueue_script(
	'eda-make-model',
	EDA_URI . '/assets/js/make-model.js',
	array(),
	eda_asset_version( 'assets/js/make-model.js' ),
	array(
		'strategy'  => 'defer',
		'in_footer' => true,
	)
);
?>
<form class="vehicle-search vehicle-search--<?php echo esc_attr( $eda_context ); ?>" method="get" action="<?php echo esc_url( get_post_type_archive_link( 'vehicle' ) ); ?>" role="search" aria-label="<?php esc_attr_e( 'Search vehicles', 'elite-auto-dealer' ); ?>" data-clean-get data-make-model>
	<div class="vehicle-search__field">
		<label for="<?php echo esc_attr( $eda_id ); ?>-make"><?php echo esc_html( get_taxonomy( 'vehicle_make' )->labels->singular_name ); ?></label>
		<select id="<?php echo esc_attr( $eda_id ); ?>-make" name="make" data-role="make">
			<option value=""><?php esc_html_e( 'All makes', 'elite-auto-dealer' ); ?></option>
			<?php foreach ( $eda_data['makes'] as $eda_value => $eda_label ) : ?>
				<option value="<?php echo esc_attr( $eda_value ); ?>" <?php selected( $eda_current['make'], (string) $eda_value ); ?>><?php echo esc_html( $eda_label ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<div class="vehicle-search__field">
		<label for="<?php echo esc_attr( $eda_id ); ?>-model"><?php echo esc_html( get_taxonomy( 'vehicle_model' )->labels->singular_name ); ?></label>
		<select id="<?php echo esc_attr( $eda_id ); ?>-model" name="model" data-role="model" data-options="<?php echo esc_attr( wp_json_encode( $eda_data['models'] ) ); ?>">
			<option value=""><?php esc_html_e( 'All models', 'elite-auto-dealer' ); ?></option>
			<?php eda_model_options( $eda_data['models'], $eda_current['make'], $eda_current['model'] ); ?>
		</select>
	</div>
	<div class="vehicle-search__field">
		<label for="<?php echo esc_attr( $eda_id ); ?>-fuel"><?php echo esc_html( get_taxonomy( 'vehicle_fuel_type' )->labels->singular_name ); ?></label>
		<select id="<?php echo esc_attr( $eda_id ); ?>-fuel" name="fuel">
			<option value=""><?php esc_html_e( 'All fuel types', 'elite-auto-dealer' ); ?></option>
			<?php foreach ( $eda_fuels as $eda_term ) : ?>
				<option value="<?php echo esc_attr( $eda_term->slug ); ?>" <?php selected( $eda_current['fuel'], $eda_term->slug ); ?>><?php echo esc_html( $eda_term->name ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<div class="vehicle-search__field">
		<label for="<?php echo esc_attr( $eda_id ); ?>-price"><?php esc_html_e( 'Maximum price', 'elite-auto-dealer' ); ?></label>
		<select id="<?php echo esc_attr( $eda_id ); ?>-price" name="price_max">
			<option value=""><?php esc_html_e( 'Any price', 'elite-auto-dealer' ); ?></option>
			<?php foreach ( eda_inventory_price_steps() as $eda_step ) : ?>
				<?php /* translators: %s: formatted price. */ ?>
				<option value="<?php echo esc_attr( $eda_step ); ?>" <?php selected( $eda_current['price_max'], $eda_step ); ?>><?php echo esc_html( sprintf( __( 'Up to %s', 'elite-auto-dealer' ), eda_format_price( $eda_step ) ) ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<?php if ( 'inventory' === $eda_context ) : ?>
		<div class="vehicle-search__field">
			<label for="<?php echo esc_attr( $eda_id ); ?>-sort"><?php esc_html_e( 'Sort by', 'elite-auto-dealer' ); ?></label>
			<select id="<?php echo esc_attr( $eda_id ); ?>-sort" name="sort">
				<?php foreach ( eda_inventory_sorts() as $eda_value => $eda_sort ) : ?>
					<option value="<?php echo esc_attr( $eda_value ); ?>" <?php selected( $eda_current['sort'], $eda_value ); ?>><?php echo esc_html( $eda_sort[0] ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	<?php endif; ?>
	<div class="vehicle-search__actions">
		<button type="submit" class="button button--primary"><?php esc_html_e( 'Search vehicles', 'elite-auto-dealer' ); ?></button>
		<?php if ( 'inventory' === $eda_context && eda_request_has_filters() ) : ?>
			<a class="button button--quiet" href="<?php echo esc_url( get_post_type_archive_link( 'vehicle' ) ); ?>"><?php esc_html_e( 'Clear filters', 'elite-auto-dealer' ); ?></a>
		<?php endif; ?>
	</div>
</form>
