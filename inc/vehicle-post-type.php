<?php
/**
 * Vehicle custom post type.
 *
 * Single: /vehicle/{slug}/   Archive: /vehicles/
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

/**
 * URL bases for vehicle permalinks. Language-neutral by default; a multilingual
 * setup or child theme can change them via the `eda_url_bases` filter
 * (re-save permalinks afterwards).
 *
 * @return array{vehicle: string, vehicles: string}
 */
function eda_url_bases() {
	return apply_filters(
		'eda_url_bases',
		array(
			'vehicle'  => 'vehicle',
			'vehicles' => 'vehicles',
		)
	);
}

/**
 * Register the `vehicle` post type.
 */
function eda_register_vehicle_post_type() {
	register_post_type(
		'vehicle',
		array(
			'labels'        => array(
				'name'               => __( 'Vehicles', 'elite-auto-dealer' ),
				'singular_name'      => __( 'Vehicle', 'elite-auto-dealer' ),
				'add_new_item'       => __( 'Add new vehicle', 'elite-auto-dealer' ),
				'edit_item'          => __( 'Edit vehicle', 'elite-auto-dealer' ),
				'new_item'           => __( 'New vehicle', 'elite-auto-dealer' ),
				'view_item'          => __( 'View vehicle', 'elite-auto-dealer' ),
				'search_items'       => __( 'Search vehicles', 'elite-auto-dealer' ),
				'not_found'          => __( 'No vehicles found.', 'elite-auto-dealer' ),
				'not_found_in_trash' => __( 'No vehicles found in Trash.', 'elite-auto-dealer' ),
				'all_items'          => __( 'All vehicles', 'elite-auto-dealer' ),
			),
			'public'        => true,
			'show_in_rest'  => true,
			'menu_position' => 5,
			'menu_icon'     => 'dashicons-car',
			// custom-fields is required for registered meta to appear in the REST API.
			'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ),
			'has_archive'   => eda_url_bases()['vehicles'],
			'rewrite'       => array(
				'slug'       => eda_url_bases()['vehicle'],
				'with_front' => false,
			),
		)
	);
}
add_action( 'init', 'eda_register_vehicle_post_type' );

/**
 * Vehicle taxonomy archives (/vehicles/make/bmw/) reuse the inventory template.
 *
 * @param string[] $templates Candidate templates.
 * @return string[]
 */
function eda_vehicle_taxonomy_template( $templates ) {
	if ( is_tax( array_keys( eda_vehicle_taxonomy_definitions() ) ) ) {
		$templates[] = 'archive-vehicle.php';
	}
	return $templates;
}
add_filter( 'taxonomy_template_hierarchy', 'eda_vehicle_taxonomy_template' );
