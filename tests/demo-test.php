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
$eda_check( 6 === $eda_featured, "exactly 6 featured (3 × 2 homepage grid): $eda_featured" );
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

// Inventory status ordering: available first, reserved next, sold last, for every sort.
$eda_rank   = static fn( $id ) => array(
	'available' => 0,
	''          => 0,
	'reserved'  => 1,
	'sold'      => 2,
)[ (string) get_post_meta( $id, '_eda_availability', true ) ];
$eda_number = static fn( $id, $key ) => (int) get_post_meta( $id, '_eda_' . $key, true );
foreach ( array(
	''            => null,
	'price_asc'   => array( 'price', 1 ),
	'price_desc'  => array( 'price', -1 ),
	'mileage_asc' => array( 'mileage', 1 ),
	'year_desc'   => array( 'year', -1 ),
) as $eda_sort => $eda_rule ) {
	$eda_ordered = ( new WP_Query(
		array(
			'post_type'        => 'vehicle',
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'eda_status_order' => true,
		) + eda_inventory_sort_args( $eda_sort )
	) )->posts;
	$eda_ranks   = array_map( $eda_rank, $eda_ordered );
	$eda_sorted  = $eda_ranks;
	sort( $eda_sorted );
	$eda_inside = true;
	if ( $eda_rule ) {
		$eda_total = count( $eda_ordered );
		for ( $eda_i = 1; $eda_i < $eda_total; $eda_i++ ) {
			if ( $eda_ranks[ $eda_i ] === $eda_ranks[ $eda_i - 1 ] ) {
				$eda_delta  = $eda_number( $eda_ordered[ $eda_i ], $eda_rule[0] ) - $eda_number( $eda_ordered[ $eda_i - 1 ], $eda_rule[0] );
				$eda_inside = $eda_inside && $eda_delta * $eda_rule[1] >= 0;
			}
		}
	}
	$eda_label = '' === $eda_sort ? 'newest' : $eda_sort;
	$eda_check( 15 === count( $eda_ordered ) && $eda_ranks === $eda_sorted && 2 === end( $eda_ranks ), "sort $eda_label: available → reserved → sold (sold last)" );
	if ( $eda_rule ) {
		$eda_check( $eda_inside, "sort $eda_label: correct order inside each status group" );
	}
}
$eda_asc = ( new WP_Query(
	array(
		'post_type'        => 'vehicle',
		'posts_per_page'   => -1,
		'fields'           => 'ids',
		'eda_status_order' => true,
	) + eda_inventory_sort_args( 'price_asc' )
) )->posts;
$eda_check( 'AUR-26014' === get_post_meta( $eda_asc[0], '_eda_stock_id', true ) && 'AUR-26006' === get_post_meta( end( $eda_asc ), '_eda_stock_id', true ), 'price low → high starts with the cheapest available car (Golf GTI), not the sold A250e' );

// Sold presentation: "Sold" + last asking price; no enquiry form, no action bar.
$eda_sold_id = eda_demo_find( 'AUR-26006' )[0];
$eda_m4_id   = eda_demo_find( 'AUR-26001' )[0];
$eda_check( 'Sold' === eda_vehicle_display_price( $eda_sold_id ) && 'Last asking price ' . eda_format_price( 27450 ) === eda_vehicle_last_asking_price( $eda_sold_id ), 'sold price: "Sold" + "Last asking price € 27,450"' );
$eda_check( '' === eda_vehicle_last_asking_price( $eda_m4_id ) && eda_format_price( 84900 ) === eda_vehicle_display_price( $eda_m4_id ), 'available price unchanged, no last-asking line' );
$eda_html = wp_remote_retrieve_body( wp_remote_get( get_permalink( $eda_sold_id ), array( 'timeout' => 20 ) ) );
$eda_check( str_contains( $eda_html, 'Last asking price' ) && str_contains( $eda_html, 'badge--sold' ) && ! str_contains( $eda_html, 'enquiry-form' ) && ! str_contains( $eda_html, 'vehicle-action-bar' ) && ! str_contains( $eda_html, '/ month' ), 'sold page: badge, last asking price, no enquiry form, no action bar, no finance' );
$eda_html = wp_remote_retrieve_body( wp_remote_get( get_permalink( $eda_m4_id ), array( 'timeout' => 20 ) ) );
$eda_check( str_contains( $eda_html, 'enquiry-form' ) && str_contains( $eda_html, 'vehicle-action-bar' ) && ! str_contains( $eda_html, 'Last asking price' ), 'available page: enquiry form and action bar present' );
$eda_check( ! str_contains( $eda_html, 'media-placeholder__label' ) && ! str_contains( $eda_html, 'Photography in production' ), 'placeholders carry no text' );

// Curated featured order on the homepage (merchandising order, not status order).
$eda_expected = array( 'BMW M4 Competition xDrive', 'Audi RS6 Avant', 'Porsche 911 Carrera', 'BMW i4 M50', 'Audi e-tron GT quattro', 'Range Rover Sport P440e' );
$eda_seeded   = array();
foreach ( $eda_vehicles as $eda_v ) {
	if ( isset( $eda_v['featured_order'] ) ) {
		$eda_seeded[ $eda_v['featured_order'] ] = (int) get_post_field( 'menu_order', eda_demo_find( $eda_v['stock_id'] )[0] );
	}
}
ksort( $eda_seeded );
$eda_check( array( 1, 2, 3, 4, 5, 6 ) === array_values( $eda_seeded ), 'featured_order seeded into the vehicle "Order" attribute (1–6)' );
$eda_home = wp_remote_retrieve_body( wp_remote_get( home_url( '/' ), array( 'timeout' => 20 ) ) );
$eda_grid = substr( $eda_home, (int) strpos( $eda_home, 'id="featured-title"' ) );
$eda_grid = substr( $eda_grid, 0, (int) strpos( $eda_grid, '</section>' ) );
preg_match_all( '#vehicle-card__title">\s*<a [^>]*>([^<]+)</a>#', $eda_grid, $eda_found );
$eda_titles = array_map( 'html_entity_decode', $eda_found[1] );
$eda_check( $eda_titles === $eda_expected, 'homepage featured cars in the curated order: ' . implode( ', ', $eda_titles ) );
$eda_check( 1 === substr_count( $eda_grid, 'badge--reserved' ) && strpos( $eda_grid, 'badge--reserved' ) > strpos( $eda_grid, 'Audi RS6 Avant' ) && strpos( $eda_grid, 'badge--reserved' ) < strpos( $eda_grid, 'BMW i4 M50' ), 'reserved 911 keeps its curated 3rd position and shows its badge' );

// "Clear filters": only for real filters, never for sorting alone.
$eda_clear = static fn( $query ) => str_contains( wp_remote_retrieve_body( wp_remote_get( get_post_type_archive_link( 'vehicle' ) . $query, array( 'timeout' => 20 ) ) ), 'Clear filters' );
$eda_check( ! $eda_clear( '' ) && ! $eda_clear( '?sort=price_asc' ), 'no "Clear filters" for the plain or sorted-only inventory' );
$eda_check( $eda_clear( '?make=bmw' ) && $eda_clear( '?make=bmw&sort=price_asc' ) && $eda_clear( '?price_max=50000' ), '"Clear filters" shown when a real filter is active' );
$eda_check( ! eda_is_filtered_request( array_diff_key( array( 'sort' => 'price_asc' ), array( 'sort' => true ) ) ) && eda_is_filtered_request( array( 'sort' => 'price_asc' ) ), 'sorting is not a filter for the button, but sorted views stay noindex' );

// Inventory archive keeps availability ordering (the curated order is homepage-only).
$eda_page2 = wp_remote_retrieve_body( wp_remote_get( get_post_type_archive_link( 'vehicle' ) . 'page/2/?sort=price_asc', array( 'timeout' => 20 ) ) );
preg_match_all( '#<article class="([^"]*vehicle-card[^"]*)"#', $eda_page2, $eda_cards );
$eda_check( $eda_cards[1] && str_contains( end( $eda_cards[1] ), 'vehicle-card--sold' ), 'inventory: sold car still last under price low → high' );

// Sold cards: no greyscale / dimming of the photograph.
$eda_css = file_get_contents( get_template_directory() . '/assets/css/main.css' );
$eda_check( ! preg_match( '/vehicle-card--sold[^{]*\{[^}]*(grayscale|opacity)/', $eda_css ), 'sold card image is not greyed or dimmed' );

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
$eda_check( ! get_term_by( 'slug', 'sport-exhaust', 'vehicle_equipment' ), 'cleanup removes unused demo-created terms' );
$eda_check( get_term_by( 'slug', 'model-y', 'vehicle_model' ) && get_term_by( 'slug', 'tesla', 'vehicle_make' ), 'cleanup keeps catalogue makes/models even when unused' );
$eda_check( get_term_by( 'slug', 'electric', 'vehicle_fuel_type' ) && get_term_by( 'slug', 'estate', 'vehicle_body_type' ), 'cleanup never removes base vocabulary terms' );
wp_delete_post( $eda_real, true );

// Restore the demo inventory.
$eda_restore = eda_demo_seed( $eda_data );
$eda_check( 15 === $eda_restore['created'] && 15 === (int) wp_count_posts( 'vehicle' )->publish, 'demo inventory restored (15 created)' );

echo PHP_EOL . ( $eda_failures ? "$eda_failures FAILED" : 'ALL PASSED' ) . PHP_EOL;
if ( $eda_failures ) {
	exit( 1 );
}
