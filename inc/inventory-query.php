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
	$make      = isset( $_GET['make'] ) ? sanitize_title( wp_unslash( $_GET['make'] ) ) : '';
	$model     = isset( $_GET['model'] ) ? sanitize_title( wp_unslash( $_GET['model'] ) ) : '';
	// phpcs:enable

	// A model from another make is ignored (the form resets it too), so ?make=bmw&model=rs-6 shows BMWs.
	if ( $make && $model && eda_model_conflicts_with_make( $make, $model ) ) {
		$query->set( 'model', '' );
	}

	$meta = array();
	if ( $price_max ) {
		$meta[] = array(
			'key'     => '_eda_price',
			'value'   => $price_max,
			'compare' => '<=',
			'type'    => 'NUMERIC',
		);
	}

	$sort_args = eda_inventory_sort_args( $sort );
	if ( isset( $sort_args['meta_query'] ) ) {
		$meta = array_merge( $meta, $sort_args['meta_query'] );
	}
	if ( isset( $sort_args['orderby'] ) ) {
		$query->set( 'orderby', $sort_args['orderby'] );
	}
	if ( $meta ) {
		$query->set( 'meta_query', $meta ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- inventory filtering, small dataset.
	}

	// Available first, then reserved, then sold, whatever the chosen sort.
	$query->set( 'eda_status_order', true );
}
add_action( 'pre_get_posts', 'eda_inventory_query' );

/**
 * Query args for a sort option: a named meta clause plus orderby (empty for "newest").
 *
 * @param string $sort Sort key from eda_inventory_sorts().
 * @return array
 */
function eda_inventory_sort_args( $sort ) {
	$sorts = eda_inventory_sorts();
	if ( empty( $sorts[ $sort ][1] ) ) {
		return array();
	}
	return array(
		// OR + NOT EXISTS keeps vehicles without the value (e.g. price on request) in the results.
		'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- inventory sorting, small dataset.
			array(
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
			),
		),
		'orderby'    => array(
			'sort_value' => $sorts[ $sort ][2],
			'date'       => 'DESC',
		),
	);
}

/**
 * Status groups for queries flagged with `eda_status_order`: available (and not stated) first,
 * reserved next, sold last. The chosen sort then applies inside each group, so a sold car's
 * low stored price can never put it at the top of "price: low to high".
 *
 * @param array    $clauses SQL clauses.
 * @param WP_Query $query   Query.
 * @return array
 */
function eda_inventory_status_order( $clauses, $query ) {
	if ( ! $query->get( 'eda_status_order' ) ) {
		return $clauses;
	}
	global $wpdb;
	$clauses['join']   .= " LEFT JOIN {$wpdb->postmeta} AS eda_status ON ( eda_status.post_id = {$wpdb->posts}.ID AND eda_status.meta_key = '_eda_availability' )";
	$clauses['orderby'] = "CASE eda_status.meta_value WHEN 'sold' THEN 2 WHEN 'reserved' THEN 1 ELSE 0 END ASC" . ( $clauses['orderby'] ? ', ' . $clauses['orderby'] : '' );
	return $clauses;
}
add_filter( 'posts_clauses', 'eda_inventory_status_order', 10, 2 );

/**
 * Statuses that keep a make/model visible in the public filters. Sold vehicles stay published
 * (and their pages reachable) but never keep a filter choice alive on their own.
 *
 * @return string[]
 */
function eda_filter_stock_statuses() {
	return array( 'available', 'reserved' );
}

/**
 * IDs of published vehicles that count as browseable stock for the public filters.
 * One indexed meta query; cached until posts, post meta or term relationships change.
 *
 * @return int[]
 */
function eda_browseable_vehicle_ids() {
	$key = 'browseable:' . wp_cache_get_last_changed( 'posts' ) . ':' . wp_cache_get_last_changed( 'terms' );
	$ids = wp_cache_get( $key, 'eda_inventory' );
	if ( false === $ids ) {
		$ids = get_posts(
			array(
				'post_type'      => 'vehicle',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one cached query for the filter choices.
					array(
						'key'     => '_eda_availability',
						'value'   => eda_filter_stock_statuses(),
						'compare' => 'IN',
					),
				),
			)
		);
		wp_cache_set( $key, $ids, 'eda_inventory' );
	}
	return array_map( 'intval', $ids );
}

/**
 * Make/model choices with their relationship, shared by the public search and the admin selector.
 *
 * Public search: $inventory_only = true → only makes/models of browseable stock (available or
 * reserved; sold-only terms drop out), values = slugs.
 * Admin selector: $inventory_only = false → the full catalogue, values = term IDs.
 *
 * @param bool   $inventory_only Only makes/models that have vehicles.
 * @param string $field          'slug' or 'term_id' as option values.
 * @return array{makes: array<string, string>, models: array<int, array{v: string, l: string, m: string, g: string}>}
 */
function eda_make_model_data( $inventory_only, $field = 'slug' ) {
	$args = array(
		'hide_empty' => false,
		'orderby'    => 'name',
	);
	if ( $inventory_only ) {
		$ids = eda_browseable_vehicle_ids();
		if ( ! $ids ) {
			return array(
				'makes'  => array(),
				'models' => array(),
			);
		}
		// Only terms attached to browseable vehicles (not term counts, which include sold cars).
		$args['object_ids'] = $ids;
	}
	$makes = array();
	$by_id = array();
	foreach ( get_terms( array( 'taxonomy' => 'vehicle_make' ) + $args ) as $make ) {
		$makes[ (string) $make->$field ] = $make->name;
		$by_id[ $make->term_id ]         = $make;
	}

	$models = array();
	foreach ( get_terms( array( 'taxonomy' => 'vehicle_model' ) + $args ) as $model ) {
		$make     = $by_id[ (int) get_term_meta( $model->term_id, 'eda_make', true ) ] ?? null;
		$models[] = array(
			'v' => (string) $model->$field,
			'l' => $model->name,
			'm' => $make ? (string) $make->$field : '',
			'g' => $make ? $make->name : '',
		);
	}
	usort( $models, static fn( $a, $b ) => strcasecmp( $a['g'] . ' ' . $a['l'], $b['g'] . ' ' . $b['l'] ) );

	return array(
		'makes'  => $makes,
		'models' => $models,
	);
}

/**
 * Models offered for a make: all models when $make is '', otherwise only that make's models.
 *
 * @param array  $models Models from eda_make_model_data().
 * @param string $make   Selected make value.
 * @return array
 */
function eda_models_for_make( array $models, $make ) {
	return '' === $make ? $models : array_values( array_filter( $models, static fn( $model ) => $model['m'] === $make ) );
}

/**
 * Validated search state from request parameters. Unknown makes are ignored; a model that does
 * not belong to the selected make is reset, so the form never shows an impossible combination.
 *
 * @param array $params Request parameters (usually $_GET).
 * @return array{make: string, model: string, fuel: string, price_max: int, sort: string}
 */
function eda_vehicle_search_state( array $params ) {
	$data  = eda_make_model_data( true );
	$make  = sanitize_title( (string) ( $params['make'] ?? '' ) );
	$make  = isset( $data['makes'][ $make ] ) ? $make : '';
	$model = sanitize_title( (string) ( $params['model'] ?? '' ) );
	$model = in_array( $model, array_column( eda_models_for_make( $data['models'], $make ), 'v' ), true ) ? $model : '';
	$sort  = sanitize_key( (string) ( $params['sort'] ?? '' ) );

	return array(
		'make'      => $make,
		'model'     => $model,
		'fuel'      => sanitize_title( (string) ( $params['fuel'] ?? '' ) ),
		'price_max' => absint( $params['price_max'] ?? 0 ),
		'sort'      => isset( eda_inventory_sorts()[ $sort ] ) ? $sort : '',
	);
}

/**
 * Whether a model term belongs to a different make than the one requested (?make=bmw&model=rs-6).
 *
 * @param string $make_slug  Make slug.
 * @param string $model_slug Model slug.
 * @return bool
 */
function eda_model_conflicts_with_make( $make_slug, $model_slug ) {
	$make  = get_term_by( 'slug', $make_slug, 'vehicle_make' );
	$model = get_term_by( 'slug', $model_slug, 'vehicle_model' );
	return $make && $model && (int) get_term_meta( $model->term_id, 'eda_make', true ) !== (int) $make->term_id;
}
