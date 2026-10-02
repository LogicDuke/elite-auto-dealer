<?php
/**
 * Demo inventory test. Run after seeding:
 *
 * Commands:
 *   wp eval-file wp-content/themes/elite-auto-dealer/demo/seed.php
 *   wp eval-file wp-content/themes/elite-auto-dealer/tests/demo-test.php
 *
 * Validates demo/vehicles.json, compares the seeded vehicles with it, re-runs the seeder
 * (idempotency) and runs a cleanup safety drill with a temporary non-demo vehicle.
 * Leaves the 15 demo vehicles in place.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput -- CLI test script.

define( 'EDA_DEMO_LIBRARY', true );
require get_template_directory() . '/demo/seed.php';

$eda_failures = 0;
$eda_check    = static function ( $condition, $message ) use ( &$eda_failures ) {
	echo ( $condition ? 'PASS ' : 'FAIL ' ) . $message . PHP_EOL;
	$eda_failures += $condition ? 0 : 1;
};

$eda_data     = eda_demo_load();
$eda_vehicles = $eda_data['vehicles'];
$eda_dataset  = $eda_data['dataset'];
$eda_column   = static fn( $key ) => array_map( static fn( $v ) => $v[ $key ] ?? ( $v['meta'][ $key ] ?? null ), $eda_vehicles );
$eda_counts   = static fn( $key ) => array_count_values( array_map( 'strval', $eda_column( $key ) ) );

// Dataset rules.
$eda_errors = eda_demo_validate( $eda_data );
$eda_check( array() === $eda_errors, 'vehicles.json passes validation' . ( $eda_errors ? ': ' . implode( '; ', $eda_errors ) : '' ) );
$eda_check( 15 === count( $eda_vehicles ), 'exactly 15 demo records' );
$eda_check( 15 === count( array_unique( $eda_column( 'stock_id' ) ) ), 'stock IDs unique' );
$eda_check( 15 === count( array_unique( $eda_column( 'vin' ) ) ), 'VINs unique' );
$eda_check( ! array_diff( $eda_column( 'vat_regime' ), array( 'deductible', 'margin' ) ) && 2 === count( $eda_counts( 'vat_regime' ) ), 'VAT regimes valid and mixed' );
$eda_avail = $eda_counts( 'availability' );
$eda_check( ! array_diff( array_keys( $eda_avail ), array( 'available', 'reserved', 'sold' ) ), 'availability values valid' );
$eda_check( 1 === ( $eda_avail['sold'] ?? 0 ) && in_array( $eda_avail['reserved'] ?? 0, array( 1, 2 ), true ) && $eda_avail['available'] > 10, 'availability mix: mostly available, 1–2 reserved, 1 sold' );
$eda_cond = $eda_counts( 'condition' );
$eda_check( ! isset( $eda_cond['new'] ) && ( $eda_cond['used'] ?? 0 ) >= 12 && ( $eda_cond['demo'] ?? 0 ) >= 1, 'conditions: mostly used, some demo, no new' );
$eda_featured = count( array_filter( $eda_column( 'featured' ) ) );
$eda_check( $eda_featured >= 4 && $eda_featured <= 6, "about 5 featured ($eda_featured)" );
$eda_fuel = $eda_counts( 'fuel_type' );
$eda_check( isset( $eda_fuel['petrol'], $eda_fuel['plug-in-hybrid'], $eda_fuel['electric'] ), 'fuel mix includes petrol, plug-in hybrid and electric' );
$eda_check( isset( $eda_counts( 'transmission' )['manual'], $eda_counts( 'transmission' )['automatic'] ), 'manual and automatic both present' );
$eda_check( count( $eda_data['equipment'] ) >= 25 && count( $eda_data['equipment'] ) <= 40, 'equipment vocabulary 25–40 terms (' . count( $eda_data['equipment'] ) . ')' );
$eda_used = array_unique( array_merge( ...$eda_column( 'equipment' ) ) );
$eda_check( ! array_diff( array_keys( $eda_data['equipment'] ), $eda_used ), 'every equipment term is used by at least one vehicle' );
$eda_prices = $eda_column( 'price' );
$eda_check( min( $eda_prices ) >= 20000 && max( $eda_prices ) <= 150000 && max( $eda_prices ) / min( $eda_prices ) > 3, 'price spread plausible' );
$eda_check( 15 === count( array_unique( $eda_column( 'mileage' ) ) ), 'no identical mileages' );

// Seeded state.
$eda_ids = get_posts(
	array(
		'post_type'      => 'vehicle',
		'post_status'    => 'any',
		'fields'         => 'ids',
		'posts_per_page' => -1,
		'meta_key'       => '_eda_demo', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'     => $eda_dataset, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	)
);
$eda_check( 15 === count( $eda_ids ), 'exactly 15 demo vehicles in the database' );

$eda_fields = eda_vehicle_meta_fields();
$eda_slug   = static function ( $post_id, $taxonomy ) {
	$terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'slugs' ) );
	sort( $terms );
	return $terms;
};
foreach ( $eda_vehicles as $eda_v ) {
	$eda_found = eda_demo_find( $eda_v['stock_id'] );
	$eda_id    = (int) ( $eda_found[0] ?? 0 );
	$eda_ok    = 1 === count( $eda_found ) && 'publish' === get_post_status( $eda_id ) && get_post_field( 'post_title', $eda_id ) === $eda_v['title'] && get_post_field( 'post_excerpt', $eda_id ) === $eda_v['excerpt'];

	foreach ( $eda_fields as $eda_key => $eda_field ) {
		if ( 'gallery' === $eda_key ) {
			continue;
		}
		$eda_expected = 'stock_id' === $eda_key ? $eda_v['stock_id'] : eda_sanitize_vehicle_meta_value( $eda_v['meta'][ $eda_key ] ?? '', $eda_field );
		$eda_stored   = get_post_meta( $eda_id, '_eda_' . $eda_key, true );
		// Booleans are stored as '1'; empty/false values must not be stored at all.
		$eda_want = ( '' === $eda_expected || false === $eda_expected ) ? '' : (string) ( true === $eda_expected ? '1' : $eda_expected );
		$eda_ok   = $eda_ok && (string) $eda_stored === $eda_want;
	}

	$eda_make  = get_term_by( 'slug', $eda_v['make'], 'vehicle_make' );
	$eda_model = get_term_by( 'slug', (string) $eda_v['model'], 'vehicle_model' );
	$eda_equip = $eda_v['equipment'];
	sort( $eda_equip );
	$eda_ok = $eda_ok
		&& array( $eda_v['make'] ) === $eda_slug( $eda_id, 'vehicle_make' )
		&& array( (string) $eda_v['model'] ) === $eda_slug( $eda_id, 'vehicle_model' )
		&& (int) get_term_meta( $eda_model->term_id, 'eda_make', true ) === (int) $eda_make->term_id
		&& array( $eda_v['body_type'] ) === $eda_slug( $eda_id, 'vehicle_body_type' )
		&& array( $eda_v['fuel_type'] ) === $eda_slug( $eda_id, 'vehicle_fuel_type' )
		&& array( $eda_v['transmission'] ) === $eda_slug( $eda_id, 'vehicle_transmission' )
		&& array( $eda_v['condition'] ) === $eda_slug( $eda_id, 'vehicle_condition' )
		&& $eda_equip === $eda_slug( $eda_id, 'vehicle_equipment' )
		&& count( $eda_v['description'] ) === substr_count( get_post_field( 'post_content', $eda_id ), '<!-- wp:paragraph -->' );

	$eda_check( $eda_ok, "{$eda_v['stock_id']} {$eda_v['title']}: meta, taxonomies, model→make, equipment, content match JSON" );
}

// Equipment terms: no near-duplicate labels in the database.
$eda_labels = array_map(
	static fn( $name ) => strtolower( preg_replace( '/[^a-z0-9]/i', '', $name ) ),
	get_terms(
		array(
			'taxonomy'   => 'vehicle_equipment',
			'hide_empty' => false,
			'fields'     => 'names',
		)
	)
);
$eda_check( count( $eda_labels ) === count( array_unique( $eda_labels ) ), 'no duplicate equipment labels in the database' );

// Idempotency: re-running the seeder updates, never duplicates.
$eda_rerun = eda_demo_seed( $eda_data );
$eda_check( 0 === $eda_rerun['created'] && 15 === $eda_rerun['updated'] && ! $eda_rerun['skipped'], 'second seed run: 0 created, 15 updated' );
$eda_check( 1 === count( eda_demo_find( 'AUR-26001' ) ) && 15 === (int) wp_count_posts( 'vehicle' )->publish, 'still exactly 15 vehicles after re-run (no duplicates)' );

// Safety drill: a real (non-demo) vehicle survives cleanup, keeps its terms, and blocks its stock ID.
$eda_real = wp_insert_post(
	array(
		'post_type'   => 'vehicle',
		'post_status' => 'draft',
		'post_title'  => 'Real dealership vehicle',
	)
);
update_post_meta( $eda_real, '_eda_stock_id', 'AUR-26001' );
wp_set_object_terms( $eda_real, array( 'navigation' ), 'vehicle_equipment' );
wp_set_object_terms( $eda_real, array( 'bmw' ), 'vehicle_make' );
$eda_blocked = eda_demo_seed( $eda_data );
$eda_check( array( 'AUR-26001' ) === $eda_blocked['skipped'] && 'Real dealership vehicle' === get_the_title( $eda_real ), 'seeder never overwrites a real vehicle with the same stock ID' );
$eda_clean = eda_demo_cleanup( $eda_dataset );
$eda_check( 15 === $eda_clean['vehicles'] && null !== get_post( $eda_real ), 'cleanup removes the 15 demo vehicles and keeps the real one' );
$eda_check( get_term_by( 'slug', 'navigation', 'vehicle_equipment' ) && get_term_by( 'slug', 'bmw', 'vehicle_make' ), 'cleanup keeps demo-created terms still used by a real vehicle' );
$eda_check( ! get_term_by( 'slug', 'sport-exhaust', 'vehicle_equipment' ) && ! get_term_by( 'slug', 'model-y', 'vehicle_model' ), 'cleanup removes unused demo-created terms' );
$eda_check( get_term_by( 'slug', 'electric', 'vehicle_fuel_type' ) && get_term_by( 'slug', 'estate', 'vehicle_body_type' ), 'cleanup never removes base vocabulary terms' );
wp_delete_post( $eda_real, true );

// Restore the demo inventory.
$eda_restore = eda_demo_seed( $eda_data );
$eda_check( 15 === $eda_restore['created'] && 15 === (int) wp_count_posts( 'vehicle' )->publish, 'demo inventory restored (15 created)' );

echo PHP_EOL . ( $eda_failures ? "$eda_failures FAILED" : 'ALL PASSED' ) . PHP_EOL;
if ( $eda_failures ) {
	exit( 1 );
}
