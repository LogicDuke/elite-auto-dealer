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
 * - format: date | vin (string fields)
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
			'type'  => 'integer',
			'label' => __( 'Year', 'elite-auto-dealer' ),
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
 * Add the vehicle details meta box.
 */
function eda_add_vehicle_meta_box() {
	add_meta_box( 'eda-vehicle-details', __( 'Vehicle details', 'elite-auto-dealer' ), 'eda_render_vehicle_meta_box', 'vehicle', 'normal', 'high' );
}
add_action( 'add_meta_boxes_vehicle', 'eda_add_vehicle_meta_box' );

/**
 * Render the meta box from the schema.
 *
 * @param WP_Post $post Vehicle.
 */
function eda_render_vehicle_meta_box( $post ) {
	wp_nonce_field( 'eda_save_vehicle', 'eda_vehicle_nonce' );
	echo '<table class="form-table" role="presentation"><tbody>';

	foreach ( eda_vehicle_meta_fields() as $key => $field ) {
		$id    = 'eda-meta-' . $key;
		$name  = 'eda_meta[' . $key . ']';
		$value = get_post_meta( $post->ID, '_eda_' . $key, true );

		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label></th><td>';

		if ( isset( $field['options'] ) ) {
			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"><option value="">—</option>';
			foreach ( $field['options'] as $option => $label ) {
				echo '<option value="' . esc_attr( $option ) . '"' . selected( $value, $option, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
		} elseif ( 'boolean' === $field['type'] ) {
			echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( (bool) $value, true, false ) . '>';
		} elseif ( 'array' === $field['type'] ) {
			$ids = is_array( $value ) ? $value : array();
			echo '<input type="hidden" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( implode( ',', $ids ) ) . '">';
			echo '<div class="eda-gallery-preview">';
			foreach ( $ids as $attachment_id ) {
				echo wp_get_attachment_image( $attachment_id, 'thumbnail', false, array( 'style' => 'width:80px;height:auto;margin:0 4px 4px 0' ) );
			}
			echo '</div><button type="button" class="button eda-gallery-select" data-target="' . esc_attr( $id ) . '">' . esc_html__( 'Select images', 'elite-auto-dealer' ) . '</button>';
		} else {
			$attrs = array(
				'type'        => 'text',
				'class'       => 'regular-text',
				'id'          => $id,
				'name'        => $name,
				'value'       => $value,
				'placeholder' => $field['placeholder'] ?? '',
			);
			if ( 'integer' === $field['type'] || 'number' === $field['type'] ) {
				$attrs['type'] = 'number';
				$attrs['min']  = '0';
				$attrs['step'] = 'number' === $field['type'] ? (string) ( 1 / ( 10 ** ( $field['decimals'] ?? 2 ) ) ) : '1';
			} elseif ( 'date' === ( $field['format'] ?? '' ) ) {
				$attrs['type'] = 'date';
			}

			echo '<input';
			foreach ( array_filter( $attrs, 'strlen' ) as $attr => $attr_value ) {
				echo ' ' . esc_attr( $attr ) . '="' . esc_attr( $attr_value ) . '"';
			}
			echo '>';
			if ( isset( $field['unit'] ) ) {
				echo ' ' . esc_html( $field['unit'] );
			}
		}

		echo '</td></tr>';
	}

	echo '</tbody></table>';
}

/**
 * Save meta box input. Values pass through the registered sanitize callbacks
 * in update_post_meta(); empty values delete the key so "not set" is never stored.
 *
 * @param int $post_id Vehicle ID.
 */
function eda_save_vehicle_meta( $post_id ) {
	if ( ! isset( $_POST['eda_vehicle_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['eda_vehicle_nonce'] ) ), 'eda_save_vehicle' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per field below.
	$input = isset( $_POST['eda_meta'] ) && is_array( $_POST['eda_meta'] ) ? wp_unslash( $_POST['eda_meta'] ) : array();

	foreach ( eda_vehicle_meta_fields() as $key => $field ) {
		$value = eda_sanitize_vehicle_meta_value( $input[ $key ] ?? '', $field );

		if ( '' === $value || false === $value || array() === $value ) {
			delete_post_meta( $post_id, '_eda_' . $key );
		} else {
			update_post_meta( $post_id, '_eda_' . $key, $value );
		}
	}
}
add_action( 'save_post_vehicle', 'eda_save_vehicle_meta' );

/**
 * Media picker for the gallery field.
 *
 * @param string $hook Admin page hook.
 */
function eda_enqueue_vehicle_admin_assets( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || 'vehicle' !== get_current_screen()->post_type ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_script( 'eda-admin-vehicle', EDA_URI . '/assets/js/admin-vehicle.js', array(), eda_asset_version( 'assets/js/admin-vehicle.js' ), true );
}
add_action( 'admin_enqueue_scripts', 'eda_enqueue_vehicle_admin_assets' );
