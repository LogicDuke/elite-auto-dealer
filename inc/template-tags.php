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
	} elseif ( 'year' === ( $field['format'] ?? '' ) ) {
		$value = (string) (int) $value;
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

/**
 * Availability of a vehicle ('available', 'reserved', 'sold' or '' when not stated).
 *
 * @param int $post_id Vehicle ID; defaults to the current post.
 * @return string
 */
function eda_vehicle_status( $post_id = 0 ) {
	return (string) eda_vehicle_meta( 'availability', $post_id );
}

/**
 * Status badge for reserved / sold vehicles (available vehicles need no badge).
 *
 * @param int $post_id Vehicle ID; defaults to the current post.
 */
function eda_vehicle_status_badge( $post_id = 0 ) {
	$status = eda_vehicle_status( $post_id );
	if ( in_array( $status, array( 'reserved', 'sold' ), true ) ) {
		printf(
			'<span class="badge badge--%1$s">%2$s</span>',
			esc_attr( $status ),
			esc_html( eda_vehicle_meta_fields()['availability']['options'][ $status ] )
		);
	}
}

/**
 * Display price: the price, "Price on request", or "Sold" for sold vehicles.
 * Sold prices stay stored (and in structured data) but are not shown.
 *
 * @param int $post_id Vehicle ID; defaults to the current post.
 * @return string
 */
function eda_vehicle_display_price( $post_id = 0 ) {
	if ( 'sold' === eda_vehicle_status( $post_id ) ) {
		return __( 'Sold', 'elite-auto-dealer' );
	}
	$price = eda_vehicle_meta( 'price', $post_id );
	return '' !== $price ? eda_format_price( $price ) : __( 'Price on request', 'elite-auto-dealer' );
}

/**
 * Secondary line for sold vehicles: "Last asking price € 27,450", or '' when not sold or no price.
 *
 * @param int $post_id Vehicle ID; defaults to the current post.
 * @return string
 */
function eda_vehicle_last_asking_price( $post_id = 0 ) {
	$price = eda_vehicle_meta( 'price', $post_id );
	if ( 'sold' !== eda_vehicle_status( $post_id ) || '' === $price ) {
		return '';
	}
	/* translators: %s: formatted price, e.g. "€ 27,450". */
	return sprintf( __( 'Last asking price %s', 'elite-auto-dealer' ), eda_format_price( $price ) );
}

/**
 * Name of the first term of a vehicle taxonomy, or ''.
 *
 * @param string $taxonomy Taxonomy.
 * @param int    $post_id  Vehicle ID; defaults to the current post.
 * @return string
 */
function eda_vehicle_term_name( $taxonomy, $post_id = 0 ) {
	$terms = get_the_terms( $post_id ? $post_id : get_the_ID(), $taxonomy );
	return $terms && ! is_wp_error( $terms ) ? $terms[0]->name : '';
}

/**
 * Key facts for cards and the vehicle header: label => display value (empty values skipped).
 *
 * @param int $post_id Vehicle ID; defaults to the current post.
 * @return array
 */
function eda_vehicle_key_facts( $post_id = 0 ) {
	$fields = eda_vehicle_meta_fields();
	$value  = static fn( $key ) => '' === eda_vehicle_meta( $key, $post_id ) ? '' : eda_format_vehicle_meta_value( eda_vehicle_meta( $key, $post_id ), $fields[ $key ] );
	$power  = '';
	if ( '' !== eda_vehicle_meta( 'power_kw', $post_id ) ) {
		/* translators: 1: power in kW, 2: power in hp. */
		$power = sprintf( __( '%1$s (%2$s)', 'elite-auto-dealer' ), $value( 'power_kw' ), $value( 'power_hp' ) );
	}

	return array_filter(
		array(
			$fields['year']['label']           => $value( 'year' ),
			$fields['mileage']['label']        => $value( 'mileage' ),
			get_taxonomy( 'vehicle_fuel_type' )->labels->singular_name => eda_vehicle_term_name( 'vehicle_fuel_type', $post_id ),
			get_taxonomy( 'vehicle_transmission' )->labels->singular_name => eda_vehicle_term_name( 'vehicle_transmission', $post_id ),
			__( 'Power', 'elite-auto-dealer' ) => $power,
		)
	);
}

/**
 * Vehicle image: the real attachment when it exists, otherwise the 3:2 placeholder.
 * Once images are imported, every template switches to real media automatically.
 *
 * @param int    $attachment_id Attachment ID (0 = none).
 * @param string $size          Registered image size.
 * @param string $sizes         `sizes` attribute for responsive images.
 * @param bool   $eager         True for the main above-the-fold image.
 */
function eda_vehicle_image( $attachment_id, $size, $sizes, $eager = false ) {
	if ( $attachment_id && wp_attachment_is_image( $attachment_id ) ) {
		echo wp_get_attachment_image(
			$attachment_id,
			$size,
			false,
			array(
				'class'         => 'vehicle-media',
				'sizes'         => $sizes,
				'loading'       => $eager ? 'eager' : 'lazy',
				'fetchpriority' => $eager ? 'high' : 'auto',
			)
		);
		return;
	}
	// Intentionally empty, decorative stand-in (no text): hidden from assistive tech, the vehicle
	// title carries the meaning. Replaced automatically once the attachment exists.
	echo '<span class="media-placeholder" aria-hidden="true"></span>';
}

/**
 * Number of vehicles currently offered (published and not sold).
 *
 * @return int
 */
function eda_available_vehicle_count() {
	$query = new WP_Query(
		array(
			'post_type'      => 'vehicle',
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- single small count query.
				'relation' => 'OR',
				array(
					'key'     => '_eda_availability',
					'value'   => 'sold',
					'compare' => '!=',
				),
				array(
					'key'     => '_eda_availability',
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);
	return (int) $query->found_posts;
}

/**
 * Fallback navigation when no menu is assigned: Home and Vehicles only.
 *
 * @param array $args wp_nav_menu() arguments (menu_class is respected).
 */
function eda_primary_menu_fallback( $args = array() ) {
	printf(
		'<ul class="' . esc_attr( $args['menu_class'] ?? 'site-nav__list' ) . '"><li><a href="%1$s">%2$s</a></li><li><a href="%3$s">%4$s</a></li></ul>',
		esc_url( home_url( '/' ) ),
		esc_html__( 'Home', 'elite-auto-dealer' ),
		esc_url( get_post_type_archive_link( 'vehicle' ) ),
		esc_html( get_post_type_object( 'vehicle' )->labels->name )
	);
}

/**
 * Dealer location from the Customizer ("Brussels, Belgium"), or '' when not set.
 *
 * @return string
 */
function eda_dealer_location() {
	return implode( ', ', array_filter( array( get_theme_mod( 'eda_city' ), get_theme_mod( 'eda_country' ) ) ) );
}

/**
 * Variant shown under the title, unless the title already contains it
 * ("Porsche 911 Carrera" + variant "Carrera" would repeat itself).
 *
 * @param int $post_id Vehicle ID; defaults to the current post.
 * @return string
 */
function eda_vehicle_subtitle( $post_id = 0 ) {
	$variant = (string) eda_vehicle_meta( 'variant', $post_id );
	return '' === $variant || false !== stripos( get_the_title( $post_id ? $post_id : get_the_ID() ), $variant ) ? '' : $variant;
}

/**
 * Print <option>s for a model select: the models of $make, or all models grouped by make.
 * assets/js/make-model.js rebuilds the same structure in the browser when the make changes.
 *
 * @param array  $models   Models from eda_make_model_data().
 * @param string $make     Selected make value ('' = all).
 * @param string $selected Selected model value.
 */
function eda_model_options( array $models, $make, $selected ) {
	$group = null;
	foreach ( eda_models_for_make( $models, (string) $make ) as $model ) {
		if ( '' === (string) $make && $model['g'] !== $group ) {
			echo null === $group ? '' : '</optgroup>';
			$group = $model['g'];
			echo '<optgroup label="' . esc_attr( '' !== $group ? $group : __( 'Other', 'elite-auto-dealer' ) ) . '">';
		}
		echo '<option value="' . esc_attr( $model['v'] ) . '"' . selected( (string) $selected, $model['v'], false ) . '>' . esc_html( $model['l'] ) . '</option>';
	}
	echo null === $group ? '' : '</optgroup>';
}
