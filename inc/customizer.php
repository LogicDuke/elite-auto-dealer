<?php
/**
 * Customizer: dealer contact details used by the vehicle action bar and enquiry emails.
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
}
add_action( 'customize_register', 'eda_customize_register' );
