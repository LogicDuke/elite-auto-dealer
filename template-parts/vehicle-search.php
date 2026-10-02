<?php
/**
 * Vehicle search form: a plain GET form to /vehicles/ (works without JS).
 * Args: 'context' => 'home' (strip) | 'inventory' (filter bar with sort and reset).
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

$eda_context = $args['context'] ?? 'home';
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- prefilling a public GET form.
$eda_current = array(
	'make'      => isset( $_GET['make'] ) ? sanitize_title( wp_unslash( $_GET['make'] ) ) : '',
	'model'     => isset( $_GET['model'] ) ? sanitize_title( wp_unslash( $_GET['model'] ) ) : '',
	'fuel'      => isset( $_GET['fuel'] ) ? sanitize_title( wp_unslash( $_GET['fuel'] ) ) : '',
	'price_max' => isset( $_GET['price_max'] ) ? absint( $_GET['price_max'] ) : 0,
	'sort'      => isset( $_GET['sort'] ) ? sanitize_key( $_GET['sort'] ) : '',
);
// phpcs:enable

// A real term archive (e.g. /vehicles/fuel/electric/) preselects its own term.
if ( eda_is_vehicle_term_landing() ) {
	$eda_term = get_queried_object();
	$eda_base = eda_vehicle_taxonomy_definitions()[ $eda_term->taxonomy ][2] ?? '';
	if ( isset( $eda_current[ $eda_base ] ) ) {
		$eda_current[ $eda_base ] = $eda_term->slug;
	}
}

$eda_makes  = get_terms( array( 'taxonomy' => 'vehicle_make' ) );
$eda_models = get_terms( array( 'taxonomy' => 'vehicle_model' ) );
$eda_fuels  = get_terms( array( 'taxonomy' => 'vehicle_fuel_type' ) );
$eda_groups = array();
foreach ( $eda_models as $eda_model ) {
	$eda_make_term              = get_term( (int) get_term_meta( $eda_model->term_id, 'eda_make', true ) );
	$eda_group                  = $eda_make_term instanceof WP_Term ? $eda_make_term->name : '';
	$eda_groups[ $eda_group ][] = $eda_model;
}
ksort( $eda_groups );
$eda_id = 'vehicle-search-' . $eda_context;
?>
<form class="vehicle-search vehicle-search--<?php echo esc_attr( $eda_context ); ?>" method="get" action="<?php echo esc_url( get_post_type_archive_link( 'vehicle' ) ); ?>" role="search" aria-label="<?php esc_attr_e( 'Search vehicles', 'elite-auto-dealer' ); ?>" data-clean-get>
	<div class="vehicle-search__field">
		<label for="<?php echo esc_attr( $eda_id ); ?>-make"><?php echo esc_html( get_taxonomy( 'vehicle_make' )->labels->singular_name ); ?></label>
		<select id="<?php echo esc_attr( $eda_id ); ?>-make" name="make">
			<option value=""><?php esc_html_e( 'All makes', 'elite-auto-dealer' ); ?></option>
			<?php foreach ( $eda_makes as $eda_term ) : ?>
				<option value="<?php echo esc_attr( $eda_term->slug ); ?>" <?php selected( $eda_current['make'], $eda_term->slug ); ?>><?php echo esc_html( $eda_term->name ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<div class="vehicle-search__field">
		<label for="<?php echo esc_attr( $eda_id ); ?>-model"><?php echo esc_html( get_taxonomy( 'vehicle_model' )->labels->singular_name ); ?></label>
		<select id="<?php echo esc_attr( $eda_id ); ?>-model" name="model">
			<option value=""><?php esc_html_e( 'All models', 'elite-auto-dealer' ); ?></option>
			<?php foreach ( $eda_groups as $eda_group => $eda_list ) : ?>
				<optgroup label="<?php echo esc_attr( $eda_group ); ?>">
					<?php foreach ( $eda_list as $eda_term ) : ?>
						<option value="<?php echo esc_attr( $eda_term->slug ); ?>" <?php selected( $eda_current['model'], $eda_term->slug ); ?>><?php echo esc_html( $eda_term->name ); ?></option>
					<?php endforeach; ?>
				</optgroup>
			<?php endforeach; ?>
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
		<?php if ( 'inventory' === $eda_context && eda_request_is_filtered() ) : ?>
			<a class="button button--quiet" href="<?php echo esc_url( get_post_type_archive_link( 'vehicle' ) ); ?>"><?php esc_html_e( 'Clear filters', 'elite-auto-dealer' ); ?></a>
		<?php endif; ?>
	</div>
</form>
