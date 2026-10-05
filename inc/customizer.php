<?php
/**
 * Customizer: dealer contact details (vehicle action bar, enquiry emails) and the header images.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the "Dealer contact" section.
 *
 * @param WP_Customize_Manager $wp_customize Customizer.
 */
function eda_customize_register( $wp_customize ) {
	$wp_customize->add_section(
		'eda_dealer',
		array(
			'title'    => __( 'Dealer contact', 'elite-auto-dealer' ),
			'priority' => 30,
		)
	);

	$settings = array(
		'eda_city'          => array( __( 'City', 'elite-auto-dealer' ), 'sanitize_text_field', 'text', __( 'Shown with the dealership name, e.g. Brussels.', 'elite-auto-dealer' ) ),
		'eda_country'       => array( __( 'Country', 'elite-auto-dealer' ), 'sanitize_text_field', 'text', '' ),
		'eda_phone'         => array( __( 'Phone number', 'elite-auto-dealer' ), 'eda_sanitize_phone', 'tel', '' ),
		'eda_whatsapp'      => array( __( 'WhatsApp number', 'elite-auto-dealer' ), 'eda_sanitize_phone', 'tel', __( 'International format, e.g. +32 470 12 34 56. Leave empty to hide WhatsApp.', 'elite-auto-dealer' ) ),
		'eda_enquiry_email' => array( __( 'Enquiry email', 'elite-auto-dealer' ), 'sanitize_email', 'email', __( 'Receives enquiry notifications. Defaults to the site admin email.', 'elite-auto-dealer' ) ),
	);

	foreach ( $settings as $id => list( $label, $sanitize, $type, $description ) ) {
		$wp_customize->add_setting( $id, array( 'sanitize_callback' => $sanitize ) );
		$wp_customize->add_control(
			$id,
			array(
				'label'       => $label,
				'description' => $description,
				'section'     => 'eda_dealer',
				'type'        => $type,
			)
		);
	}

	// Site images: attachment IDs (docs/WEBSITE-IMAGE-ROADMAP.md). Pages use their featured image.
	$wp_customize->add_section(
		'eda_hero',
		array(
			'title'    => __( 'Header images', 'elite-auto-dealer' ),
			'priority' => 31,
		)
	);
	$images = array(
		'eda_hero_image'        => array( __( 'Hero image (desktop and landscape)', 'elite-auto-dealer' ), __( '2560 × 1138 px JPEG. Keep the left side calm for the headline.', 'elite-auto-dealer' ) ),
		'eda_hero_image_mobile' => array( __( 'Hero image (portrait mobile)', 'elite-auto-dealer' ), __( '1200 × 1800 px JPEG, subject in the upper third. Optional.', 'elite-auto-dealer' ) ),
		'eda_inventory_image'   => array( __( 'Vehicles page header image', 'elite-auto-dealer' ), __( '1536 × 1024 px JPEG (3:2). Other pages use their featured image.', 'elite-auto-dealer' ) ),
	);
	foreach ( $images as $id => list( $label, $description ) ) {
		$wp_customize->add_setting( $id, array( 'sanitize_callback' => 'absint' ) );
		$wp_customize->add_control(
			new WP_Customize_Media_Control(
				$wp_customize,
				$id,
				array(
					'label'       => $label,
					'description' => $description,
					'section'     => 'eda_hero',
					'mime_type'   => 'image',
				)
			)
		);
	}
}
add_action( 'customize_register', 'eda_customize_register' );
