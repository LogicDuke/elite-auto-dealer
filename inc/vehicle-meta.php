<?php
/**
 * Vehicle metadata: one schema drives registration, REST, sanitising, the
 * admin meta box, the front-end spec table and structured data.
 *
 * Stored as post meta `_eda_{key}`. Make, model, body type, fuel and
 * transmission are taxonomies, not meta (see vehicle-taxonomies.php).
 * Description is post content; the main photo is the featured image.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Meta field schema, in admin display order.
 *
 * Keys:
 * - type: integer (whole, >= 0) | number (decimal, >= 0) | boolean | string | array (attachment IDs)
 * - label
 * - options: enum, machine value => translated label (stored value is the key)
 * - format: date | vin (string fields), year (integer shown without digit grouping)
 * - decimals: rounding for number fields
 * - unit: translated display unit
 * - placeholder: admin input hint
 *
 * @return array
 */
function eda_vehicle_meta_fields() {
	return array(
		'stock_id'           => array(
			'type'  => 'string',
			'label' => __( 'Stock ID', 'elite-auto-dealer' ),
		),
		'availability'       => array(
			'type'    => 'string',
			'label'   => __( 'Availability', 'elite-auto-dealer' ),
			'options' => array(
				'available' => __( 'Available', 'elite-auto-dealer' ),
				'reserved'  => __( 'Reserved', 'elite-auto-dealer' ),
				'sold'      => __( 'Sold', 'elite-auto-dealer' ),
			),
		),
		'featured'           => array(
			'type'  => 'boolean',
			'label' => __( 'Featured', 'elite-auto-dealer' ),
		),
		'variant'            => array(
			'type'        => 'string',
			'label'       => __( 'Variant / trim', 'elite-auto-dealer' ),
			'placeholder' => __( 'e.g. 40 TFSI S line', 'elite-auto-dealer' ),
		),
		'price'              => array(
			'type'  => 'integer',
			'label' => __( 'Price', 'elite-auto-dealer' ),
			'unit'  => '€',
		),
		'finance_monthly'    => array(
			'type'  => 'integer',
			'label' => __( 'Finance example per month', 'elite-auto-dealer' ),
			'unit'  => '€',
		),
		'vat_regime'         => array(
			'type'    => 'string',
			'label'   => __( 'VAT regime', 'elite-auto-dealer' ),
			'options' => array(
				'deductible' => __( 'VAT deductible', 'elite-auto-dealer' ),
				'margin'     => __( 'Margin scheme (VAT not deductible)', 'elite-auto-dealer' ),
			),
		),
		'year'               => array(
			'type'   => 'integer',
			'format' => 'year',
			'label'  => __( 'Year', 'elite-auto-dealer' ),
		),
		'first_registration' => array(
			'type'   => 'string',
			'format' => 'date',
			'label'  => __( 'First registration', 'elite-auto-dealer' ),
		),
		'mileage'            => array(
			'type'  => 'integer',
			'label' => __( 'Mileage', 'elite-auto-dealer' ),
			'unit'  => _x( 'km', 'unit', 'elite-auto-dealer' ),
		),
		'power_kw'           => array(
			'type'  => 'integer',
			'label' => __( 'Power (kW)', 'elite-auto-dealer' ),
			'unit'  => _x( 'kW', 'unit', 'elite-auto-dealer' ),
		),
		'power_hp'           => array(
			'type'  => 'integer',
			'label' => __( 'Power (hp)', 'elite-auto-dealer' ),
			'unit'  => _x( 'hp', 'unit', 'elite-auto-dealer' ),
		),
		'engine_cc'          => array(
			'type'  => 'integer',
			'label' => __( 'Engine size', 'elite-auto-dealer' ),
			'unit'  => _x( 'cc', 'unit', 'elite-auto-dealer' ),
		),
		'battery_kwh'        => array(
			'type'     => 'number',
			'decimals' => 1,
			'label'    => __( 'Battery capacity (EV)', 'elite-auto-dealer' ),
			'unit'     => _x( 'kWh', 'unit', 'elite-auto-dealer' ),
		),
		'ev_range_km'        => array(
			'type'  => 'integer',
			'label' => __( 'Electric range (WLTP)', 'elite-auto-dealer' ),
			'unit'  => _x( 'km', 'unit', 'elite-auto-dealer' ),
		),
		'exterior_colour'    => array(
			'type'  => 'string',
			'label' => __( 'Exterior colour', 'elite-auto-dealer' ),
		),
		'interior_colour'    => array(
			'type'  => 'string',
			'label' => __( 'Interior colour', 'elite-auto-dealer' ),
		),
		'doors'              => array(
			'type'  => 'integer',
			'label' => __( 'Doors', 'elite-auto-dealer' ),
		),
		'seats'              => array(
			'type'  => 'integer',
			'label' => __( 'Seats', 'elite-auto-dealer' ),
		),
		'co2'                => array(
			'type'  => 'integer',
			'label' => __( 'CO2 emissions (WLTP)', 'elite-auto-dealer' ),
			'unit'  => _x( 'g/km', 'unit', 'elite-auto-dealer' ),
		),
		'euro_norm'          => array(
			'type'    => 'string',
			'label'   => __( 'Euro emissions standard', 'elite-auto-dealer' ),
			'options' => array(
				'euro-1'       => 'Euro 1',
				'euro-2'       => 'Euro 2',
				'euro-3'       => 'Euro 3',
				'euro-4'       => 'Euro 4',
				'euro-5'       => 'Euro 5',
				'euro-6'       => 'Euro 6',
				'euro-6b'      => 'Euro 6b',
				'euro-6c'      => 'Euro 6c',
				'euro-6d-temp' => 'Euro 6d-TEMP',
				'euro-6d'      => 'Euro 6d',
				'euro-6e'      => 'Euro 6e',
				'euro-7'       => 'Euro 7',
			),
		),
		'carpass'            => array(
			'type'    => 'string',
			'label'   => __( 'Car-Pass', 'elite-auto-dealer' ),
			'options' => array(
				'available'    => __( 'Available', 'elite-auto-dealer' ),
				'pending'      => __( 'Pending', 'elite-auto-dealer' ),
				'not_required' => __( 'Not required', 'elite-auto-dealer' ),
			),
		),
		'warranty_months'    => array(
			'type'  => 'integer',
			'label' => __( 'Warranty (months)', 'elite-auto-dealer' ),
		),
		'vin'                => array(
			'type'   => 'string',
			'format' => 'vin',
			'label'  => __( 'VIN', 'elite-auto-dealer' ),
		),
		'gallery'            => array(
			'type'  => 'array',
			'label' => __( 'Image gallery', 'elite-auto-dealer' ),
		),
	);
}

/**
 * Sanitise one value against its field schema. Invalid input becomes '' (= not set).
 *
 * @param mixed $value Raw value.
 * @param array $field Field schema.
 * @return mixed
 */
function eda_sanitize_vehicle_meta_value( $value, $field ) {
	switch ( $field['type'] ) {
		case 'integer':
			// Whole, non-negative numbers only: "12.500" or "12,5" are rejected rather than silently truncated.
			$value = trim( (string) $value );
			return preg_match( '/^\d+$/', $value ) ? (int) $value : '';
		case 'number':
			// Accept the Belgian decimal comma ("77,4") as well as a dot.
			$value = str_replace( ',', '.', trim( (string) $value ) );
			return is_numeric( $value ) && (float) $value >= 0 ? round( (float) $value, $field['decimals'] ?? 2 ) : '';
		case 'boolean':
			return rest_sanitize_boolean( $value );
		case 'array':
			$ids = is_array( $value ) ? $value : explode( ',', (string) $value );
			return array_values( array_filter( array_map( 'absint', $ids ) ) );
	}

	$value = sanitize_text_field( (string) $value );

	if ( isset( $field['options'] ) ) {
		return isset( $field['options'][ $value ] ) ? $value : '';
	}

	$format = $field['format'] ?? '';
	if ( 'date' === $format ) {
		$date = DateTime::createFromFormat( '!Y-m-d', $value );
		return ( $date && $date->format( 'Y-m-d' ) === $value ) ? $value : '';
	}
	if ( 'vin' === $format ) {
		return strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $value ) );
	}

	return $value;
}

/**
 * Register every field as post meta on `vehicle`, exposed in REST.
 */
function eda_register_vehicle_meta() {
	foreach ( eda_vehicle_meta_fields() as $key => $field ) {
		$schema = array();
		if ( 'array' === $field['type'] ) {
			$schema = array( 'items' => array( 'type' => 'integer' ) );
		} elseif ( isset( $field['options'] ) ) {
			$schema = array( 'enum' => array_merge( array( '' ), array_keys( $field['options'] ) ) );
		}

		register_post_meta(
			'vehicle',
			'_eda_' . $key,
			array(
				'type'              => $field['type'],
				'description'       => $field['label'],
				'single'            => true,
				'show_in_rest'      => $schema ? array( 'schema' => $schema ) : true,
				'sanitize_callback' => static fn( $value ) => eda_sanitize_vehicle_meta_value( $value, $field ),
				// Underscore-prefixed (protected) meta needs an explicit auth callback for REST writes.
				'auth_callback'     => static fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', $post_id ),
			)
		);
	}
}
add_action( 'init', 'eda_register_vehicle_meta' );

/**
 * Save the make/model pair. The model is only stored when it belongs to the chosen make.
 *
 * @param int $post_id  Vehicle ID.
 * @param int $make_id  Submitted make term ID (0 = none).
 * @param int $model_id Submitted model term ID (0 = none).
 */
function eda_save_make_model( $post_id, $make_id, $model_id ) {
	$make_ok  = $make_id && get_term( $make_id, 'vehicle_make' ) instanceof WP_Term;
	$model_ok = $make_ok && $model_id && get_term( $model_id, 'vehicle_model' ) instanceof WP_Term
		&& (int) get_term_meta( $model_id, 'eda_make', true ) === $make_id;

	wp_set_object_terms( $post_id, $make_ok ? array( $make_id ) : array(), 'vehicle_make' );
	wp_set_object_terms( $post_id, $model_ok ? array( $model_id ) : array(), 'vehicle_model' );
}
