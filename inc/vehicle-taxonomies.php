<?php
/**
 * Vehicle taxonomies.
 *
 * Every filterable, finite attribute is a taxonomy (not meta) so it gets
 * indexed term queries, term counts for facets and SEO landing pages.
 * See docs/ARCHITECTURE.md.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Taxonomy definitions: name => [ plural, singular, query var / URL base, hierarchical ].
 *
 * Hierarchical only affects the admin UI here: checkbox list for short fixed
 * vocabularies, tag-style autocomplete for long open lists.
 *
 * @return array
 */
function eda_vehicle_taxonomy_definitions() {
	return array(
		'vehicle_make'         => array( __( 'Makes', 'elite-auto-dealer' ), __( 'Make', 'elite-auto-dealer' ), 'make', false ),
		'vehicle_model'        => array( __( 'Models', 'elite-auto-dealer' ), __( 'Model', 'elite-auto-dealer' ), 'model', false ),
		'vehicle_body_type'    => array( __( 'Body types', 'elite-auto-dealer' ), __( 'Body type', 'elite-auto-dealer' ), 'body', true ),
		'vehicle_fuel_type'    => array( __( 'Fuel types', 'elite-auto-dealer' ), __( 'Fuel type', 'elite-auto-dealer' ), 'fuel', true ),
		'vehicle_transmission' => array( __( 'Transmissions', 'elite-auto-dealer' ), __( 'Transmission', 'elite-auto-dealer' ), 'transmission', true ),
		'vehicle_condition'    => array( __( 'Conditions', 'elite-auto-dealer' ), __( 'Condition', 'elite-auto-dealer' ), 'condition', true ),
		'vehicle_equipment'    => array( __( 'Equipment', 'elite-auto-dealer' ), __( 'Equipment item', 'elite-auto-dealer' ), 'equipment', false ),
	);
}

/**
 * Register all vehicle taxonomies.
 */
function eda_register_vehicle_taxonomies() {
	foreach ( eda_vehicle_taxonomy_definitions() as $taxonomy => list( $plural, $singular, $base, $hierarchical ) ) {
		register_taxonomy(
			$taxonomy,
			'vehicle',
			array(
				'labels'             => array(
					'name'          => $plural,
					'singular_name' => $singular,
					'menu_name'     => $plural,
				),
				'public'             => true,
				'hierarchical'       => $hierarchical,
				'show_in_rest'       => true,
				'show_admin_column'  => 'vehicle_equipment' !== $taxonomy,
				'query_var'          => $base,
				// Make and model are chosen together in the "Vehicle details" box (make → model selector),
				// so their free-text tag boxes and quick-edit fields are hidden.
				'meta_box_cb'        => in_array( $taxonomy, array( 'vehicle_make', 'vehicle_model' ), true ) ? false : null,
				'show_in_quick_edit' => ! in_array( $taxonomy, array( 'vehicle_make', 'vehicle_model' ), true ),
				'rewrite'            => array(
					'slug'       => eda_url_bases()['vehicles'] . '/' . $base,
					'with_front' => false,
				),
			)
		);
	}

	// A model belongs to one make. Stored on the model term so filters can cascade make -> model.
	register_term_meta(
		'vehicle_model',
		'eda_make',
		array(
			'type'              => 'integer',
			'single'            => true,
			'sanitize_callback' => 'absint',
			'show_in_rest'      => true,
		)
	);
}
add_action( 'init', 'eda_register_vehicle_taxonomies' );

/**
 * Seed the fixed vocabularies. Makes, models and equipment are dealer-entered.
 * Safe to run repeatedly.
 */
function eda_seed_vehicle_terms() {
	// Slugs are fixed, language-neutral identifiers (code, filters and URLs rely on them).
	// Names are translated, so a nl_BE or fr_BE site seeds Dutch or French labels.
	$defaults = array(
		'vehicle_body_type'    => array(
			'hatchback'   => __( 'Hatchback', 'elite-auto-dealer' ),
			'sedan'       => __( 'Sedan', 'elite-auto-dealer' ),
			'estate'      => __( 'Estate', 'elite-auto-dealer' ),
			'suv'         => __( 'SUV', 'elite-auto-dealer' ),
			'coupe'       => __( 'Coupé', 'elite-auto-dealer' ),
			'convertible' => __( 'Convertible', 'elite-auto-dealer' ),
			'mpv'         => __( 'MPV', 'elite-auto-dealer' ),
			'van'         => __( 'Van', 'elite-auto-dealer' ),
			'pick-up'     => __( 'Pick-up', 'elite-auto-dealer' ),
		),
		'vehicle_fuel_type'    => array(
			'petrol'         => __( 'Petrol', 'elite-auto-dealer' ),
			'diesel'         => __( 'Diesel', 'elite-auto-dealer' ),
			'hybrid'         => __( 'Hybrid', 'elite-auto-dealer' ),
			'plug-in-hybrid' => __( 'Plug-in hybrid', 'elite-auto-dealer' ),
			'electric'       => __( 'Electric', 'elite-auto-dealer' ),
			'lpg'            => __( 'LPG', 'elite-auto-dealer' ),
			'cng'            => __( 'CNG', 'elite-auto-dealer' ),
		),
		'vehicle_transmission' => array(
			'manual'    => __( 'Manual', 'elite-auto-dealer' ),
			'automatic' => __( 'Automatic', 'elite-auto-dealer' ),
		),
		'vehicle_condition'    => array(
			'used' => __( 'Used', 'elite-auto-dealer' ),
			'new'  => __( 'New', 'elite-auto-dealer' ),
			'demo' => __( 'Demo', 'elite-auto-dealer' ),
		),
	);

	foreach ( $defaults as $taxonomy => $terms ) {
		foreach ( $terms as $slug => $name ) {
			if ( ! term_exists( $slug, $taxonomy ) ) {
				wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
			}
		}
	}
}

/**
 * Model = model family. Remind editors that trims belong in the vehicle's Variant field.
 */
function eda_model_family_notice() {
	echo '<p>' . esc_html__( 'Models are model families only (e.g. A4, 3 Series, M4). Put trims and engine variants (e.g. 40 TFSI S line) in the vehicle\'s Variant / trim field.', 'elite-auto-dealer' ) . '</p>';
}
add_action( 'vehicle_model_pre_add_form', 'eda_model_family_notice' );

/**
 * Make selector on the "Add model" screen.
 */
function eda_model_add_make_field() {
	?>
	<div class="form-field form-required">
		<label for="eda-make"><?php esc_html_e( 'Make', 'elite-auto-dealer' ); ?></label>
		<?php eda_make_dropdown( 0 ); ?>
	</div>
	<?php
}
add_action( 'vehicle_model_add_form_fields', 'eda_model_add_make_field' );

/**
 * Make selector on the "Edit model" screen.
 *
 * @param WP_Term $term Model term.
 */
function eda_model_edit_make_field( $term ) {
	?>
	<tr class="form-field form-required">
		<th scope="row"><label for="eda-make"><?php esc_html_e( 'Make', 'elite-auto-dealer' ); ?></label></th>
		<td><?php eda_make_dropdown( (int) get_term_meta( $term->term_id, 'eda_make', true ) ); ?></td>
	</tr>
	<?php
}
add_action( 'vehicle_model_edit_form_fields', 'eda_model_edit_make_field' );

/**
 * Print the make <select>.
 *
 * @param int $selected Selected make term ID.
 */
function eda_make_dropdown( $selected ) {
	wp_dropdown_categories(
		array(
			'taxonomy'          => 'vehicle_make',
			'name'              => 'eda_make',
			'id'                => 'eda-make',
			'selected'          => $selected,
			'hide_empty'        => false,
			'show_option_none'  => __( '— Select make —', 'elite-auto-dealer' ),
			'option_none_value' => '',
			'orderby'           => 'name',
			'required'          => true,
		)
	);
}

/**
 * Save the model's make. Core verifies the term-form nonce and capability before these hooks fire.
 *
 * @param int $term_id Model term ID.
 */
function eda_save_model_make( $term_id ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by core term handlers.
	$make = isset( $_POST['eda_make'] ) ? absint( $_POST['eda_make'] ) : 0;
	// Only a real make is saved; an empty choice never wipes an existing link.
	if ( $make && current_user_can( 'edit_term', $term_id ) && get_term( $make, 'vehicle_make' ) instanceof WP_Term ) {
		update_term_meta( $term_id, 'eda_make', $make );
	}
}
add_action( 'created_vehicle_model', 'eda_save_model_make' );
add_action( 'edited_vehicle_model', 'eda_save_model_make' );

/**
 * Models added on the Models admin screen must name their make (no accidental orphans).
 * Programmatic inserts (catalogue, demo seeder) link the make themselves and are not affected.
 *
 * @param string|WP_Error $term     Term name.
 * @param string          $taxonomy Taxonomy.
 * @return string|WP_Error
 */
function eda_require_model_make( $term, $taxonomy ) {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- core verifies the add-tag nonce.
	$is_admin_form = wp_doing_ajax() && isset( $_POST['action'] ) && 'add-tag' === $_POST['action'];
	$make          = isset( $_POST['eda_make'] ) ? absint( $_POST['eda_make'] ) : 0;
	// phpcs:enable
	if ( 'vehicle_model' === $taxonomy && $is_admin_form && ! ( $make && get_term( $make, 'vehicle_make' ) instanceof WP_Term ) ) {
		return new WP_Error( 'eda_missing_make', __( 'Choose the make this model belongs to.', 'elite-auto-dealer' ) );
	}
	return $term;
}
add_filter( 'pre_insert_term', 'eda_require_model_make', 10, 2 );

/**
 * "Make" column on the Models list, so orphans and wrong links are visible at a glance.
 *
 * @param array $columns Columns.
 * @return array
 */
function eda_model_columns( $columns ) {
	return array_slice( $columns, 0, 2, true ) + array( 'eda_make' => __( 'Make', 'elite-auto-dealer' ) ) + array_slice( $columns, 2, null, true );
}
add_filter( 'manage_edit-vehicle_model_columns', 'eda_model_columns' );

/**
 * Make column content.
 *
 * @param string $content Column content.
 * @param string $column  Column name.
 * @param int    $term_id Model term ID.
 * @return string
 */
function eda_model_column_content( $content, $column, $term_id ) {
	if ( 'eda_make' !== $column ) {
		return $content;
	}
	$make = get_term( (int) get_term_meta( $term_id, 'eda_make', true ), 'vehicle_make' );
	return $make instanceof WP_Term ? esc_html( $make->name ) : '<strong>' . esc_html__( 'No make', 'elite-auto-dealer' ) . '</strong>';
}
add_filter( 'manage_vehicle_model_custom_column', 'eda_model_column_content', 10, 3 );

/**
 * Hide the Make / Model tag panels in the block editor (the Vehicle details box replaces them).
 *
 * @param WP_REST_Response $response Response.
 * @param WP_Taxonomy      $taxonomy Taxonomy.
 * @param WP_REST_Request  $request  Request.
 * @return WP_REST_Response
 */
function eda_hide_make_model_panels( $response, $taxonomy, $request ) {
	if ( in_array( $taxonomy->name, array( 'vehicle_make', 'vehicle_model' ), true ) && 'edit' === $request['context'] ) {
		$data                          = $response->get_data();
		$data['visibility']['show_ui'] = false;
		$response->set_data( $data );
	}
	return $response;
}
add_filter( 'rest_prepare_taxonomy', 'eda_hide_make_model_panels', 10, 3 );
