<?php
/**
 * Inventory listing query: page size, maximum price and sorting.
 * Taxonomy filters (make, model, fuel, …) already work natively as query vars.
 * These parameters are part of eda_vehicle_filter_params(), so filtered views are noindex.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sort options: key => [ label, meta key (empty = publish date), order ].
 *
 * @return array
 */
function eda_inventory_sorts() {
	return array(
		''            => array( __( 'Newest first', 'elite-auto-dealer' ), '', 'DESC' ),
		'price_asc'   => array( __( 'Price: low to high', 'elite-auto-dealer' ), '_eda_price', 'ASC' ),
		'price_desc'  => array( __( 'Price: high to low', 'elite-auto-dealer' ), '_eda_price', 'DESC' ),
		'mileage_asc' => array( __( 'Mileage: lowest first', 'elite-auto-dealer' ), '_eda_mileage', 'ASC' ),
		'year_desc'   => array( __( 'Year: newest first', 'elite-auto-dealer' ), '_eda_year', 'DESC' ),
	);
}

/**
 * Price ceilings offered in the search form (whole euros).
 *
 * @return int[]
 */
function eda_inventory_price_steps() {
	return array( 30000, 40000, 50000, 75000, 100000 );
}

/**
 * Apply page size, price ceiling and sort to the main vehicle listing query.
 *
 * @param WP_Query $query Query.
 */
function eda_inventory_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! ( $query->is_post_type_archive( 'vehicle' ) || $query->is_tax( array_keys( eda_vehicle_taxonomy_definitions() ) ) ) ) {
		return;
	}

	$query->set( 'posts_per_page', 12 );

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- public read-only listing parameters.
	$price_max = isset( $_GET['price_max'] ) ? absint( $_GET['price_max'] ) : 0;
	$sort      = isset( $_GET['sort'] ) ? sanitize_key( $_GET['sort'] ) : '';
	// phpcs:enable

	$meta = array();
	if ( $price_max ) {
		$meta[] = array(
			'key'     => '_eda_price',
			'value'   => $price_max,
			'compare' => '<=',
			'type'    => 'NUMERIC',
		);
	}

	$sorts = eda_inventory_sorts();
	if ( ! empty( $sorts[ $sort ][1] ) ) {
		// OR + NOT EXISTS keeps vehicles without the value (e.g. price on request) in the results.
		$meta[] = array(
			'relation'   => 'OR',
			'sort_value' => array(
				'key'     => $sorts[ $sort ][1],
				'compare' => 'EXISTS',
				'type'    => 'NUMERIC',
			),
			array(
				'key'     => $sorts[ $sort ][1],
				'compare' => 'NOT EXISTS',
			),
		);
		$query->set(
			'orderby',
			array(
				'sort_value' => $sorts[ $sort ][2],
				'date'       => 'DESC',
			)
		);
	}

	if ( $meta ) {
		$query->set( 'meta_query', $meta ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- inventory filtering, small dataset.
	}
}
add_action( 'pre_get_posts', 'eda_inventory_query' );
