<?php
/**
 * Dealership roles and admin navigation.
 *
 * Vehicles and enquiries have their own capabilities (`edit_vehicles`, `edit_enquiries`, …), so the
 * "Dealership staff" role can manage stock and leads without the post/page/theme/plugin/user
 * capabilities. Hiding menus is only cosmetics on top: the capabilities are the security.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

/** Bump to re-apply role capabilities. */
const EDA_ROLES_VERSION = 1;

/**
 * Vehicle capabilities (capability_type vehicle/vehicles, map_meta_cap).
 *
 * @return string[]
 */
function eda_vehicle_caps() {
	return array( 'edit_vehicles', 'edit_others_vehicles', 'edit_published_vehicles', 'edit_private_vehicles', 'publish_vehicles', 'read_private_vehicles', 'delete_vehicles', 'delete_others_vehicles', 'delete_published_vehicles', 'delete_private_vehicles' );
}

/**
 * Enquiry capabilities (enquiries are private posts; nobody creates them in the admin).
 *
 * @return string[]
 */
function eda_enquiry_caps() {
	return array( 'edit_enquiries', 'edit_others_enquiries', 'edit_private_enquiries', 'edit_published_enquiries', 'read_private_enquiries', 'delete_enquiries', 'delete_others_enquiries', 'delete_private_enquiries', 'delete_published_enquiries' );
}

/**
 * Create the Dealership staff role and give administrators and editors the vehicle, enquiry and
 * Site Settings capabilities. Runs once per EDA_ROLES_VERSION.
 */
function eda_setup_roles() {
	if ( (int) get_option( 'eda_roles_version' ) >= EDA_ROLES_VERSION ) {
		return;
	}
	$caps = array_merge( eda_vehicle_caps(), eda_enquiry_caps(), array( 'eda_manage_site_settings' ) );
	foreach ( array( 'administrator', 'editor' ) as $name ) {
		$role = get_role( $name );
		foreach ( $role ? $caps : array() as $cap ) {
			$role->add_cap( $cap );
		}
	}
	remove_role( 'eda_dealer_staff' );
	add_role( 'eda_dealer_staff', __( 'Dealership staff', 'elite-auto-dealer' ), array_fill_keys( array_merge( array( 'read', 'upload_files' ), $caps ), true ) );
	update_option( 'eda_roles_version', EDA_ROLES_VERSION );
}
add_action( 'init', 'eda_setup_roles', 1 );

/**
 * Whether the current user is dealership staff (not an administrator).
 *
 * @return bool
 */
function eda_is_dealer_staff() {
	return in_array( 'eda_dealer_staff', (array) wp_get_current_user()->roles, true ) && ! current_user_can( 'manage_options' );
}

/**
 * Staff menu: Dashboard, Vehicles (All / Add), Enquiries, Site Settings, Profile. Posts, Pages, Comments,
 * Appearance, Plugins, Users, Tools and Settings already need capabilities staff do not have;
 * Media is managed inside the vehicle editor.
 */
function eda_dealer_staff_menu() {
	if ( eda_is_dealer_staff() ) {
		remove_menu_page( 'upload.php' );
	}
}
add_action( 'admin_menu', 'eda_dealer_staff_menu', 999 );
