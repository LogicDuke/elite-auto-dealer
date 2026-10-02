<?php
/**
 * SEO: vehicle structured data, breadcrumb structured data and the canonical /
 * robots strategy for inventory listings. See docs/ARCHITECTURE.md ("SEO").
 *
 * If an SEO plugin that outputs canonicals/schema is installed later, disable
 * the overlapping parts here to avoid duplicates.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Query parameters that turn a vehicle listing into a filtered/sorted view.
 * Taxonomy query vars plus the reserved range/sort params from the filtering plan.
 *
 * @return string[]
 */
function eda_vehicle_filter_params() {
	return array_merge(
		array_column( eda_vehicle_taxonomy_definitions(), 2 ),
		array( 'price_min', 'price_max', 'year_min', 'year_max', 'km_max', 'sort' )
	);
}

/**
 * Whether request parameters contain at least one non-empty filter/sort param.
 *
 * @param array $params Query parameters (usually $_GET).
 * @return bool
 */
function eda_is_filtered_request( array $params ) {
	foreach ( eda_vehicle_filter_params() as $param ) {
		if ( isset( $params[ $param ] ) && '' !== $params[ $param ] ) {
			return true;
		}
	}
	return false;
}

/**
 * Whether the current request carries filter/sort parameters.
 *
 * @return bool
 */
function eda_request_is_filtered() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of parameter presence.
	return eda_is_filtered_request( wp_unslash( $_GET ) );
}

/**
 * Whether the main query is a vehicle listing (inventory archive or vehicle term archive).
 * Note: /vehicles/?fuel=petrol also reports is_tax() in WordPress; use
 * eda_is_vehicle_term_landing() to tell real term archives apart.
 *
 * @return bool
 */
function eda_is_vehicle_listing() {
	return is_post_type_archive( 'vehicle' ) || is_tax( array_keys( eda_vehicle_taxonomy_definitions() ) );
}

/**
 * A real term archive (/vehicles/make/bmw/) without filter parameters: an indexable landing page.
 *
 * @return bool
 */
function eda_is_vehicle_term_landing() {
	return is_tax( array_keys( eda_vehicle_taxonomy_definitions() ) ) && ! eda_request_is_filtered();
}

/**
 * Inventory H1. A real term archive gets "{Term} vehicles"; any filter/sort state gets the
 * neutral "Vehicles". Filters are never turned into heading text (they become chips later).
 *
 * @param WP_Term|null $term   Queried term, if any.
 * @param array        $params Request parameters.
 * @return string
 */
function eda_inventory_heading( $term, array $params ) {
	if ( $term instanceof WP_Term && ! eda_is_filtered_request( $params ) ) {
		/* translators: %s: make, model, fuel type, etc. Example: "BMW vehicles". */
		return sprintf( _x( '%s vehicles', 'inventory heading', 'elite-auto-dealer' ), $term->name );
	}
	return get_post_type_object( 'vehicle' )->labels->name;
}

/**
 * Inventory heading for the current request.
 *
 * @return string
 */
function eda_current_inventory_heading() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of parameter presence.
	return eda_inventory_heading( is_tax() ? get_queried_object() : null, wp_unslash( $_GET ) );
}

/**
 * Keep the document <title> in line with the inventory H1 (no filter-built titles).
 *
 * @param array $parts Title parts.
 * @return array
 */
function eda_inventory_document_title( $parts ) {
	if ( eda_is_vehicle_listing() ) {
		$parts['title'] = eda_current_inventory_heading();
	}
	return $parts;
}
add_filter( 'document_title_parts', 'eda_inventory_document_title' );

/**
 * Clean (parameter-free) URL of the current vehicle listing, including the page number.
 *
 * @return string
 */
function eda_vehicle_listing_url() {
	global $wp_rewrite;

	$url   = eda_is_vehicle_term_landing() ? get_term_link( get_queried_object() ) : get_post_type_archive_link( 'vehicle' );
	$paged = (int) get_query_var( 'paged' );

	if ( $paged > 1 ) {
		$url = user_trailingslashit( trailingslashit( $url ) . $wp_rewrite->pagination_base . '/' . $paged, 'paged' );
	}

	return $url;
}

/**
 * Filtered/sorted listings: noindex, follow. Crawlers still follow links to vehicles,
 * but filter combinations never become an indexable surface.
 *
 * @param array $robots Robots directives.
 * @return array
 */
function eda_vehicle_listing_robots( $robots ) {
	if ( eda_is_vehicle_listing() && eda_request_is_filtered() ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
	}
	return $robots;
}
add_filter( 'wp_robots', 'eda_vehicle_listing_robots' );

/**
 * Unfiltered listings: self-referencing canonical without tracking/unknown parameters.
 * Filtered listings get no canonical (noindex already applies; mixing both sends mixed signals).
 * Core already handles singular canonicals.
 */
function eda_vehicle_listing_canonical() {
	if ( eda_is_vehicle_listing() && ! eda_request_is_filtered() ) {
		echo '<link rel="canonical" href="' . esc_url( eda_vehicle_listing_url() ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'eda_vehicle_listing_canonical' );

/**
 * Remove empty values (null, '', []) recursively so schema contains only real data.
 *
 * @param array $data Data.
 * @return array
 */
function eda_schema_filter( array $data ) {
	foreach ( $data as $key => $value ) {
		if ( is_array( $value ) ) {
			$value        = eda_schema_filter( $value );
			$data[ $key ] = $value;
		}
		if ( null === $value || '' === $value || array() === $value ) {
			unset( $data[ $key ] );
		}
	}
	return $data;
}

/**
 * Schema.org Car (+ Offer when a price exists) built only from stored vehicle data.
 * Nothing is inferred: no ratings, no finance, no availability unless explicitly set.
 *
 * @param int $post_id Vehicle ID.
 * @return array
 */
function eda_vehicle_schema( $post_id ) {
	$meta = static fn( $key ) => eda_vehicle_meta( $key, $post_id );
	$term = static function ( $taxonomy ) use ( $post_id ) {
		$terms = get_the_terms( $post_id, $taxonomy );
		return $terms && ! is_wp_error( $terms ) ? $terms[0] : null;
	};
	$qv   = static fn( $value, $unit ) => '' === $value ? null : array(
		'@type'    => 'QuantitativeValue',
		'value'    => 0 + $value, // Numeric string -> int or float, so 86000 is not printed as 86000.0.
		'unitCode' => $unit,
	);

	$fields     = eda_vehicle_meta_fields();
	$make       = $term( 'vehicle_make' );
	$condition  = $term( 'vehicle_condition' );
	$conditions = array(
		'used' => 'https://schema.org/UsedCondition',
		'new'  => 'https://schema.org/NewCondition',
		// Demo cars have been registered and driven: used in schema.org terms, "Demo" on screen.
		'demo' => 'https://schema.org/UsedCondition',
	);
	$stock      = array(
		'available' => 'https://schema.org/InStock',
		'sold'      => 'https://schema.org/SoldOut',
	);
	$gallery    = $meta( 'gallery' );
	$images     = array_map( 'wp_get_attachment_url', array_merge( array( get_post_thumbnail_id( $post_id ) ), is_array( $gallery ) ? $gallery : array() ) );
	$price      = $meta( 'price' );
	$euro       = $meta( 'euro_norm' );

	$schema = array(
		'@type'                       => 'Car',
		'name'                        => get_the_title( $post_id ),
		'url'                         => get_permalink( $post_id ),
		'image'                       => array_values( array_unique( array_filter( $images ) ) ),
		'description'                 => wp_strip_all_tags( get_the_excerpt( $post_id ) ),
		'brand'                       => $make ? array(
			'@type' => 'Brand',
			'name'  => $make->name,
		) : null,
		'model'                       => $term( 'vehicle_model' )?->name,
		'vehicleConfiguration'        => $meta( 'variant' ),
		'bodyType'                    => $term( 'vehicle_body_type' )?->name,
		'fuelType'                    => $term( 'vehicle_fuel_type' )?->name,
		'vehicleTransmission'         => $term( 'vehicle_transmission' )?->name,
		'itemCondition'               => $condition ? ( $conditions[ $condition->slug ] ?? null ) : null,
		'vehicleModelDate'            => $meta( 'year' ),
		'dateVehicleFirstRegistered'  => $meta( 'first_registration' ),
		'mileageFromOdometer'         => $qv( $meta( 'mileage' ), 'KMT' ),
		'color'                       => $meta( 'exterior_colour' ),
		'vehicleInteriorColor'        => $meta( 'interior_colour' ),
		'numberOfDoors'               => '' === $meta( 'doors' ) ? null : (int) $meta( 'doors' ),
		'seatingCapacity'             => '' === $meta( 'seats' ) ? null : (int) $meta( 'seats' ),
		'vehicleIdentificationNumber' => $meta( 'vin' ),
		'sku'                         => $meta( 'stock_id' ),
		'emissionsCO2'                => '' === $meta( 'co2' ) ? null : (int) $meta( 'co2' ),
		'meetsEmissionStandard'       => $fields['euro_norm']['options'][ $euro ] ?? null,
		'vehicleEngine'               => array(
			'@type'              => 'EngineSpecification',
			'enginePower'        => $qv( $meta( 'power_kw' ), 'KWT' ),
			'engineDisplacement' => $qv( $meta( 'engine_cc' ), 'CMQ' ),
		),
		'offers'                      => '' === $price ? null : array(
			'@type'         => 'Offer',
			'price'         => (int) $price,
			'priceCurrency' => 'EUR',
			'url'           => get_permalink( $post_id ),
			'availability'  => $stock[ $meta( 'availability' ) ] ?? null,
		),
	);

	// An engine block with only its @type carries no information.
	if ( 1 === count( eda_schema_filter( $schema['vehicleEngine'] ) ) ) {
		$schema['vehicleEngine'] = null;
	}

	return eda_schema_filter( $schema );
}

/**
 * Schema.org BreadcrumbList from the shared breadcrumb trail.
 *
 * @param array $trail Output of eda_breadcrumb_trail().
 * @return array
 */
function eda_breadcrumb_schema( array $trail ) {
	$items = array();
	foreach ( array_values( $trail ) as $i => list( $label, $url ) ) {
		$items[] = eda_schema_filter(
			array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => $label,
				'item'     => $url,
			)
		);
	}
	return array(
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $items,
	);
}

/**
 * Print JSON-LD on vehicle pages and vehicle listings.
 */
function eda_print_structured_data() {
	$graph = array();

	if ( is_singular( 'vehicle' ) ) {
		$graph[] = eda_vehicle_schema( get_queried_object_id() );
	}
	if ( ( is_singular( 'vehicle' ) || eda_is_vehicle_listing() ) && eda_breadcrumb_trail() ) {
		$graph[] = eda_breadcrumb_schema( eda_breadcrumb_trail() );
	}
	if ( ! $graph ) {
		return;
	}

	$json = wp_json_encode(
		array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		),
		JSON_HEX_TAG | JSON_UNESCAPED_UNICODE
	);
	// JSON_HEX_TAG escapes < and >, so the payload cannot close the script element.
	echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'wp_head', 'eda_print_structured_data' );
