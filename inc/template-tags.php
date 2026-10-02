<?php
/**
 * Template helpers.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get a vehicle meta value by schema key (without the `_eda_` prefix).
 *
 * @param string $key     Schema key, e.g. 'price'.
 * @param int    $post_id Vehicle ID; defaults to the current post.
 * @return mixed
 */
function eda_vehicle_meta( $key, $post_id = 0 ) {
	return get_post_meta( $post_id ? $post_id : get_the_ID(), '_eda_' . $key, true );
}

/**
 * Format a whole-euro amount for the site locale. Symbol position is translatable
 * (nl_BE "€ 24.950", fr_BE "24 950 €").
 *
 * @param int $amount Amount in euros.
 * @return string
 */
function eda_format_price( $amount ) {
	/* translators: %s: formatted amount in euros. */
	return sprintf( _x( '€ %s', 'price format', 'elite-auto-dealer' ), number_format_i18n( (int) $amount ) );
}

/**
 * Format a schema field value for display (localised numbers, option labels, units).
 *
 * @param mixed $value Stored value.
 * @param array $field Field schema.
 * @return string
 */
function eda_format_vehicle_meta_value( $value, $field ) {
	if ( isset( $field['options'] ) ) {
		$value = $field['options'][ $value ] ?? $value;
	} elseif ( 'boolean' === $field['type'] ) {
		$value = $value ? __( 'Yes', 'elite-auto-dealer' ) : __( 'No', 'elite-auto-dealer' );
	} elseif ( 'integer' === $field['type'] ) {
		$value = number_format_i18n( (int) $value );
	} elseif ( 'number' === $field['type'] ) {
		$value = number_format_i18n( (float) $value, $field['decimals'] ?? 2 );
	} elseif ( 'date' === ( $field['format'] ?? '' ) ) {
		$value = date_i18n( get_option( 'date_format' ), strtotime( $value ) );
	}

	return isset( $field['unit'] ) ? $value . ' ' . $field['unit'] : (string) $value;
}

/**
 * Human-readable spec rows for a vehicle: label => display value. Empty fields are skipped.
 *
 * @param int $post_id Vehicle ID; defaults to the current post.
 * @return array
 */
function eda_vehicle_specs( $post_id = 0 ) {
	$skip  = array( 'stock_id', 'availability', 'featured', 'variant', 'price', 'finance_monthly', 'gallery' );
	$specs = array();

	foreach ( eda_vehicle_meta_fields() as $key => $field ) {
		$value = eda_vehicle_meta( $key, $post_id );
		if ( ! in_array( $key, $skip, true ) && '' !== $value ) {
			$specs[ $field['label'] ] = eda_format_vehicle_meta_value( $value, $field );
		}
	}

	return $specs;
}

/**
 * Keep only characters valid in a phone number.
 *
 * @param string $phone Raw input.
 * @return string
 */
function eda_sanitize_phone( $phone ) {
	return substr( trim( preg_replace( '/[^0-9+()\s.\/-]/', '', (string) $phone ) ), 0, 30 );
}

/**
 * Digits (and leading +) only, for tel: and wa.me links.
 *
 * @param string $phone Phone number as entered.
 * @return string
 */
function eda_phone_digits( $phone ) {
	return preg_replace( '/[^0-9+]/', '', (string) $phone );
}

/**
 * Breadcrumb trail for the current request: list of [ label, url ]. The last item is the
 * current page (url may be ''). Shared by the visible breadcrumb and BreadcrumbList JSON-LD.
 *
 * @return array<int, array{0: string, 1: string}>
 */
function eda_breadcrumb_trail() {
	if ( is_front_page() ) {
		return array();
	}

	$trail        = array( array( __( 'Home', 'elite-auto-dealer' ), home_url( '/' ) ) );
	$vehicle_taxs = array_keys( eda_vehicle_taxonomy_definitions() );

	if ( is_singular( 'vehicle' ) || is_post_type_archive( 'vehicle' ) || is_tax( $vehicle_taxs ) ) {
		$trail[] = array( get_post_type_object( 'vehicle' )->labels->name, get_post_type_archive_link( 'vehicle' ) );
	}

	if ( is_tax( $vehicle_taxs ) ) {
		// Only real term archives get a term crumb; query-string filter states stay at "Vehicles".
		if ( eda_is_vehicle_term_landing() ) {
			$trail[] = array( single_term_title( '', false ), get_term_link( get_queried_object() ) );
		}
	} elseif ( is_singular( 'vehicle' ) ) {
		$makes = get_the_terms( get_queried_object_id(), 'vehicle_make' );
		if ( $makes && ! is_wp_error( $makes ) ) {
			$trail[] = array( $makes[0]->name, get_term_link( $makes[0] ) );
		}
		$trail[] = array( single_post_title( '', false ), get_permalink( get_queried_object_id() ) );
	} elseif ( is_singular() ) {
		$trail[] = array( single_post_title( '', false ), get_permalink( get_queried_object_id() ) );
	} elseif ( is_search() ) {
		/* translators: %s: search query. */
		$trail[] = array( sprintf( __( 'Search results for “%s”', 'elite-auto-dealer' ), get_search_query() ), '' );
	} elseif ( is_404() ) {
		$trail[] = array( __( 'Page not found', 'elite-auto-dealer' ), '' );
	} elseif ( is_archive() && ! is_post_type_archive( 'vehicle' ) ) {
		$trail[] = array( wp_strip_all_tags( get_the_archive_title() ), '' );
	}

	// A trail is only useful with at least Home + the current page.
	return count( $trail ) > 1 ? $trail : array();
}
