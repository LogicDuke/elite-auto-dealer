<?php
/**
 * Site Settings: one simple screen for the dealership's name and contact details.
 *
 * No new storage: it edits the same values the theme already reads. Name and tagline are the
 * WordPress Site Title and Tagline; city, country, phone, WhatsApp and enquiry email are the
 * Customizer theme mods (Customizer → Dealer contact keeps working for administrators).
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fields, in display order: key => storage, label, help, input type, autocomplete.
 * The key is the existing option (storage "option") or theme mod (storage "mod").
 *
 * @return array<string, array>
 */
function eda_site_settings_fields() {
	return array(
		'blogname'          => array(
			'storage' => 'option',
			'label'   => __( 'Dealership name', 'elite-auto-dealer' ),
			'help'    => __( 'Shown in the header, the footer and enquiry emails.', 'elite-auto-dealer' ),
			'type'    => 'text',
			'auto'    => 'organization',
		),
		'blogdescription'   => array(
			'storage' => 'option',
			'label'   => __( 'Tagline', 'elite-auto-dealer' ),
			'help'    => __( 'A short line shown in the footer and on the Vehicles page, e.g. "Premium pre-owned automobiles".', 'elite-auto-dealer' ),
			'type'    => 'text',
			'auto'    => 'off',
		),
		'eda_city'          => array(
			'storage' => 'mod',
			'label'   => __( 'City', 'elite-auto-dealer' ),
			'help'    => __( 'Shown with the dealership name, e.g. Brussels.', 'elite-auto-dealer' ),
			'type'    => 'text',
			'auto'    => 'address-level2',
		),
		'eda_country'       => array(
			'storage' => 'mod',
			'label'   => __( 'Country', 'elite-auto-dealer' ),
			'help'    => __( 'Shown after the city, e.g. Belgium.', 'elite-auto-dealer' ),
			'type'    => 'text',
			'auto'    => 'country-name',
		),
		'eda_phone'         => array(
			'storage' => 'mod',
			'label'   => __( 'Phone number', 'elite-auto-dealer' ),
			'help'    => __( 'Shown as you type it and used for the Call buttons, e.g. +32 2 123 45 67. Leave empty to hide the Call buttons.', 'elite-auto-dealer' ),
			'type'    => 'tel',
			'auto'    => 'tel',
		),
		'eda_whatsapp'      => array(
			'storage' => 'mod',
			'label'   => __( 'WhatsApp number', 'elite-auto-dealer' ),
			'help'    => __( 'Start with + and the country code, e.g. +32 470 12 34 56. Leave empty to hide the WhatsApp buttons.', 'elite-auto-dealer' ),
			'type'    => 'tel',
			'auto'    => 'tel',
		),
		'eda_enquiry_email' => array(
			'storage' => 'mod',
			'label'   => __( 'Enquiry email', 'elite-auto-dealer' ),
			'help'    => __( 'Enquiries from the website are sent to this address. If it is empty, enquiries are only stored under Enquiries and not emailed.', 'elite-auto-dealer' ),
			'type'    => 'email',
			'auto'    => 'email',
		),
	);
}

/**
 * Current stored value of a setting.
 *
 * @param string $key Field key.
 * @return string
 */
function eda_site_setting( $key ) {
	return (string) ( 'option' === eda_site_settings_fields()[ $key ]['storage'] ? get_option( $key ) : get_theme_mod( $key, '' ) );
}

/**
 * Validate submitted settings.
 *
 * @param array $input Submitted values (unslashed), keyed like eda_site_settings_fields().
 * @return array{0: array<string, string>, 1: array<string, string>} Clean values and messages per field.
 */
function eda_validate_site_settings( array $input ) {
	$values = array();
	$errors = array();
	foreach ( array_keys( eda_site_settings_fields() ) as $key ) {
		$values[ $key ] = sanitize_text_field( (string) ( $input[ $key ] ?? '' ) );
	}
	$digits = static fn( $phone ) => strlen( preg_replace( '/\D/', '', $phone ) );
	$phone  = '/^\+?[0-9\s().\/-]+$/';

	if ( '' === $values['blogname'] ) {
		$errors['blogname'] = __( 'Please enter the dealership name.', 'elite-auto-dealer' );
	}
	if ( '' !== $values['eda_phone'] && ( ! preg_match( $phone, $values['eda_phone'] ) || $digits( $values['eda_phone'] ) < 6 || $digits( $values['eda_phone'] ) > 15 ) ) {
		$errors['eda_phone'] = __( 'Please enter a valid phone number, using digits, spaces and an optional + at the start, e.g. +32 2 123 45 67.', 'elite-auto-dealer' );
	}
	if ( '' !== $values['eda_whatsapp'] && ( ! preg_match( $phone, $values['eda_whatsapp'] ) || ! str_starts_with( $values['eda_whatsapp'], '+' ) || $digits( $values['eda_whatsapp'] ) < 8 || $digits( $values['eda_whatsapp'] ) > 15 ) ) {
		$errors['eda_whatsapp'] = __( 'Please enter the WhatsApp number with + and the country code, e.g. +32 470 12 34 56.', 'elite-auto-dealer' );
	}
	if ( '' !== $values['eda_enquiry_email'] && ! is_email( $values['eda_enquiry_email'] ) ) {
		$errors['eda_enquiry_email'] = __( 'Please enter a valid enquiry email.', 'elite-auto-dealer' ); // The typed value is shown again to correct.
	} elseif ( '' !== $values['eda_enquiry_email'] ) {
		$values['eda_enquiry_email'] = sanitize_email( $values['eda_enquiry_email'] );
	}
	return array( $values, $errors );
}

/**
 * Store validated values in their existing places. Only changed values are written; an emptied
 * theme mod is removed, so the theme's own defaults apply again (no enquiry email: enquiries are stored, not emailed).
 *
 * @param array $values Clean values from eda_validate_site_settings().
 * @return string[] Keys that changed.
 */
function eda_save_site_settings( array $values ) {
	$changed = array();
	foreach ( eda_site_settings_fields() as $key => $field ) {
		// Options are compared as WordPress stores them (the Site Title is saved HTML-escaped).
		if ( ! array_key_exists( $key, $values ) || eda_site_setting( $key ) === ( 'option' === $field['storage'] ? sanitize_option( $key, $values[ $key ] ) : $values[ $key ] ) ) {
			continue;
		}
		if ( 'option' === $field['storage'] ) {
			update_option( $key, $values[ $key ] );
		} elseif ( '' === $values[ $key ] ) {
			remove_theme_mod( $key );
		} else {
			set_theme_mod( $key, $values[ $key ] ); // Validated: phone numbers are stored exactly as typed (+ and spaces kept).
		}
		$changed[] = $key;
	}
	return $changed;
}

/**
 * Menu entry (the capability check also protects the page itself).
 */
function eda_site_settings_menu() {
	$hook = add_menu_page(
		__( 'Site Settings', 'elite-auto-dealer' ),
		__( 'Site Settings', 'elite-auto-dealer' ),
		'eda_manage_site_settings',
		'eda-site-settings',
		'eda_render_site_settings',
		'dashicons-store',
		7
	);
	add_action( 'load-' . $hook, 'eda_handle_site_settings' );
	add_action( 'admin_print_styles-' . $hook, 'eda_site_settings_styles' );
}
add_action( 'admin_menu', 'eda_site_settings_menu' );

/**
 * Save on POST (nonce + capability); on success redirect with a message, on errors re-show the form.
 */
function eda_handle_site_settings() {
	if ( ! isset( $_POST['eda_settings'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- routing only; the nonce is checked next.
		return;
	}
	check_admin_referer( 'eda_site_settings' );
	if ( ! current_user_can( 'eda_manage_site_settings' ) ) {
		wp_die( esc_html__( 'You are not allowed to change the site settings.', 'elite-auto-dealer' ), 403 );
	}
	$input                   = isset( $_POST['eda_settings'] ) && is_array( $_POST['eda_settings'] ) ? wp_unslash( $_POST['eda_settings'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per field in eda_validate_site_settings().
	list( $values, $errors ) = eda_validate_site_settings( $input );
	if ( $errors ) {
		$GLOBALS['eda_site_settings_submitted'] = array( $values, $errors ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- request state for the form.
		return;
	}
	eda_save_site_settings( $values );
	wp_safe_redirect( add_query_arg( 'saved', '1', menu_page_url( 'eda-site-settings', false ) ) );
	exit;
}

/**
 * Styles: the vehicle editor's look.
 */
function eda_site_settings_styles() {
	wp_enqueue_style( 'eda-admin-vehicle', EDA_URI . '/assets/css/admin-vehicle.css', array(), eda_asset_version( 'assets/css/admin-vehicle.css' ) );
}

/**
 * The page.
 */
function eda_render_site_settings() {
	list( $values, $errors ) = $GLOBALS['eda_site_settings_submitted'] ?? array( array(), array() );
	$fields                  = eda_site_settings_fields();
	$sections                = array(
		'eda-dealership' => array( __( 'Dealership', 'elite-auto-dealer' ), array( 'blogname', 'blogdescription', 'eda_city', 'eda_country' ) ),
		'eda-contact'    => array( __( 'Contact', 'elite-auto-dealer' ), array( 'eda_phone', 'eda_whatsapp', 'eda_enquiry_email' ) ),
	);

	echo '<div class="wrap"><div class="eda-editor eda-settings"><h1 class="eda-editor__title">' . esc_html__( 'Site Settings', 'elite-auto-dealer' ) . '</h1>';
	if ( isset( $_GET['saved'] ) && ! $errors ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		echo '<div class="notice notice-success inline eda-editor__summary" role="status"><p>' . esc_html__( 'Your settings have been saved. The website now shows the new details.', 'elite-auto-dealer' ) . '</p></div>';
	}
	if ( $errors ) {
		echo '<div class="notice notice-error inline eda-editor__summary" role="alert" tabindex="-1" id="eda-settings-errors"><p><strong>' . esc_html__( 'Nothing was saved yet. Please correct the following:', 'elite-auto-dealer' ) . '</strong></p><ul>';
		foreach ( $errors as $key => $message ) {
			echo '<li><a href="#eda-setting-' . esc_attr( $key ) . '">' . esc_html( $message ) . '</a></li>';
		}
		echo '</ul></div>';
	}

	echo '<form method="post" action="" novalidate>';
	wp_nonce_field( 'eda_site_settings' );
	foreach ( $sections as $id => list( $title, $keys ) ) {
		echo '<section class="eda-editor__section" aria-labelledby="' . esc_attr( $id ) . '"><h2 class="eda-editor__heading" id="' . esc_attr( $id ) . '">' . esc_html( $title ) . '</h2><div class="eda-grid">';
		foreach ( $keys as $key ) {
			$field = $fields[ $key ];
			$id    = 'eda-setting-' . $key;
			$error = $errors[ $key ] ?? '';
			$value = array_key_exists( $key, $values ) ? $values[ $key ] : eda_site_setting( $key );
			echo '<div class="eda-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] );
			if ( 'blogname' === $key ) {
				echo ' <span class="eda-field__required">' . esc_html__( 'Required', 'elite-auto-dealer' ) . '</span>';
			}
			printf(
				'</label><div class="eda-field__control"><input type="%1$s" id="%2$s" name="eda_settings[%3$s]" value="%4$s" autocomplete="%5$s" aria-describedby="%2$s-help%6$s"%7$s%8$s></div>',
				esc_attr( $field['type'] ),
				esc_attr( $id ),
				esc_attr( $key ),
				esc_attr( 'blogname' === $key || 'blogdescription' === $key ? wp_specialchars_decode( $value, ENT_QUOTES ) : $value ),
				esc_attr( $field['auto'] ),
				$error ? ' ' . esc_attr( $id ) . '-error' : '',
				'blogname' === $key ? ' required' : '',
				$error ? ' aria-invalid="true"' : ''
			);
			echo '<p class="eda-field__help" id="' . esc_attr( $id ) . '-help">' . esc_html( $field['help'] ) . '</p>';
			if ( $error ) {
				echo '<p class="eda-field__error" id="' . esc_attr( $id ) . '-error">' . esc_html( $error ) . '</p>';
			}
			echo '</div>';
		}
		echo '</div></section>';
	}
	echo '<div class="eda-editor__actions eda-editor__actions--bottom"><button type="submit" class="button button-primary button-large">' . esc_html__( 'Save settings', 'elite-auto-dealer' ) . '</button></div>';
	echo '</form></div></div>';
	if ( $errors ) {
		echo '<script>document.getElementById("eda-settings-errors").focus();</script>';
	}
}
