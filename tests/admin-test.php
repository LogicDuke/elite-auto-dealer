<?php
/**
 * Dealer admin test: roles and capabilities, the simplified vehicle editor (save, partial update,
 * publishing guard, photos), the All Vehicles list helpers, and a no-change round trip of the
 * rendered form through WordPress's own edit_post() for six existing demo vehicles.
 *
 * Run from the WordPress root:  wp eval-file wp-content/themes/elite-auto-dealer/tests/admin-test.php
 *
 * Creates a temporary staff user and test vehicles, and deletes them again.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput, WordPress.WP.GlobalVariablesOverride, WordPress.Security.NonceVerification, WordPress.DB.DirectDatabaseQuery, WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase, WordPress.NamingConventions.ValidHookName.UseUnderscores -- CLI test script (DOM properties, core hook names); $_POST is set to simulate the edit screen and restored.

require_once ABSPATH . 'wp-admin/includes/post.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

$eda_failures = 0;
$eda_check    = static function ( $condition, $message ) use ( &$eda_failures ) {
	echo ( $condition ? 'PASS ' : 'FAIL ' ) . $message . PHP_EOL;
	$eda_failures += $condition ? 0 : 1;
};

// ---------- Roles and capabilities.
$eda_staff_id = wp_insert_user(
	array(
		'user_login' => 'eda_test_staff_' . wp_rand(),
		'user_pass'  => wp_generate_password(),
		'role'       => 'eda_dealer_staff',
	)
);
$eda_staff    = get_user_by( 'id', $eda_staff_id );
foreach ( array( 'read', 'upload_files', 'edit_vehicles', 'edit_others_vehicles', 'publish_vehicles', 'delete_vehicles', 'edit_enquiries', 'eda_manage_site_settings' ) as $eda_cap ) {
	$eda_check( $eda_staff->has_cap( $eda_cap ), "staff can $eda_cap" );
}
foreach ( array( 'edit_posts', 'edit_pages', 'manage_options', 'edit_theme_options', 'activate_plugins', 'list_users', 'manage_categories', 'edit_users' ) as $eda_cap ) {
	$eda_check( ! $eda_staff->has_cap( $eda_cap ), "staff cannot $eda_cap" );
}
foreach ( array( 'administrator', 'editor' ) as $eda_role ) {
	$eda_check( get_role( $eda_role )->has_cap( 'publish_vehicles' ) && get_role( $eda_role )->has_cap( 'edit_enquiries' ) && get_role( $eda_role )->has_cap( 'eda_manage_site_settings' ), "$eda_role keeps vehicle, enquiry and site-settings capabilities" );
}
$eda_check( ! get_role( 'author' )->has_cap( 'edit_vehicles' ) && ! get_role( 'subscriber' )->has_cap( 'edit_vehicles' ), 'authors and subscribers cannot edit vehicles' );
$eda_page = (int) get_option( 'page_on_front' );
$eda_m4   = get_posts(
	array(
		'post_type' => 'vehicle',
		'title'     => 'BMW M4 Competition xDrive',
		'fields'    => 'ids',
	)
)[0] ?? 0;
$eda_check( user_can( $eda_staff, 'edit_post', $eda_m4 ) && user_can( $eda_staff, 'delete_post', $eda_m4 ), 'staff can edit and trash a vehicle' );
$eda_check( ! user_can( $eda_staff, 'edit_post', $eda_page ), 'staff cannot edit a page' );
$eda_check( ! user_can( $eda_staff, get_post_type_object( 'enquiry' )->cap->create_posts ), 'nobody creates enquiries in the admin' );
$eda_check( user_can( $eda_staff, get_taxonomy( 'vehicle_fuel_type' )->cap->assign_terms ) && ! user_can( $eda_staff, get_taxonomy( 'vehicle_fuel_type' )->cap->manage_terms ), 'staff assign classifications but do not manage them' );
$eda_check( ! use_block_editor_for_post_type( 'vehicle' ) && use_block_editor_for_post_type( 'page' ), 'block editor off for vehicles only' );

// ---------- Titles and publishing requirements.
$eda_bmw   = get_term_by( 'slug', 'bmw', 'vehicle_make' );
$eda_audi  = get_term_by( 'slug', 'audi', 'vehicle_make' );
$eda_model = (int) wp_get_object_terms( $eda_m4, 'vehicle_model', array( 'fields' => 'ids' ) )[0];
$eda_check( 'BMW M4 Competition xDrive' === eda_vehicle_auto_title( $eda_bmw->term_id, $eda_model, ' Competition xDrive ' ), 'auto title = Make + Model + Variant' );
$eda_check( 'BMW' === eda_vehicle_auto_title( $eda_bmw->term_id, 0, '' ), 'auto title skips empty parts' );

$eda_fuel   = (int) wp_get_object_terms( $eda_m4, 'vehicle_fuel_type', array( 'fields' => 'ids' ) )[0];
$eda_trans  = (int) wp_get_object_terms( $eda_m4, 'vehicle_transmission', array( 'fields' => 'ids' ) )[0];
$eda_photos = array_merge( array( (int) get_post_thumbnail_id( $eda_m4 ) ), array_slice( (array) get_post_meta( $eda_m4, '_eda_gallery', true ), 0, 2 ) );
$eda_full   = array(
	'eda_vehicle_make'      => $eda_bmw->term_id,
	'eda_vehicle_model'     => $eda_model,
	'eda_meta'              => array(
		'variant'      => 'Test Edition',
		'year'         => '2023',
		'mileage'      => '12000',
		'price'        => '45900',
		'availability' => 'available',
		'featured'     => '1',
		'power_kw'     => '375',
		'power_hp'     => '510',
		'vin'          => 'WBS000TEST0000001',
	),
	'eda_tax'               => array(
		'vehicle_fuel_type'    => $eda_fuel,
		'vehicle_transmission' => $eda_trans,
		'vehicle_body_type'    => '',
	),
	'eda_equipment_present' => '1',
	'eda_equipment_new'     => array( 'EDA Test Option' ),
	'eda_photos'            => implode( ',', $eda_photos ),
);
$eda_check( array() === eda_vehicle_publish_errors( $eda_full ), 'a complete vehicle can be published' );
$eda_check( array_keys( eda_vehicle_required_messages() ) === array_keys( eda_vehicle_publish_errors( array() ) ), 'an empty vehicle lists all nine missing fields' );
$eda_check( array( 'model' ) === array_keys( eda_vehicle_publish_errors( array_merge( $eda_full, array( 'eda_vehicle_make' => $eda_audi->term_id ) ) ) ), 'a model that does not belong to the make is refused' );
$eda_bad                      = $eda_full;
$eda_bad['eda_meta']['price'] = '0';
$eda_bad['eda_photos']        = '';
$eda_check( array( 'price', 'photos' ) === array_keys( eda_vehicle_publish_errors( $eda_bad ) ), 'price 0 and no photo are refused' );
$eda_check( 'Please add a price before publishing.' === eda_vehicle_publish_errors( $eda_bad )['price'], 'messages are written for people' );

// ---------- Saving as staff: full save, partial update, photos.
wp_set_current_user( $eda_staff_id );
$eda_new = wp_insert_post(
	array(
		'post_type'   => 'vehicle',
		'post_status' => 'draft',
		'post_title'  => 'Draft',
	)
);
eda_save_vehicle_input( $eda_new, $eda_full );
$eda_option = get_term_by( 'name', 'EDA Test Option', 'vehicle_equipment' );
$eda_check( 45900 === (int) get_post_meta( $eda_new, '_eda_price', true ) && '2023' === (string) get_post_meta( $eda_new, '_eda_year', true ) && get_post_meta( $eda_new, '_eda_featured', true ), 'meta saved from the form' );
$eda_check( array( $eda_bmw->term_id ) === wp_get_object_terms( $eda_new, 'vehicle_make', array( 'fields' => 'ids' ) ) && array( $eda_model ) === wp_get_object_terms( $eda_new, 'vehicle_model', array( 'fields' => 'ids' ) ), 'make and model saved as a pair' );
$eda_check( array( $eda_fuel ) === wp_get_object_terms( $eda_new, 'vehicle_fuel_type', array( 'fields' => 'ids' ) ), 'classification saved' );
$eda_check( $eda_option && has_term( $eda_option->term_id, 'vehicle_equipment', $eda_new ), 'new equipment option created and ticked' );
$eda_check( (int) get_post_thumbnail_id( $eda_new ) === $eda_photos[0] && array_slice( $eda_photos, 1 ) === array_map( 'intval', get_post_meta( $eda_new, '_eda_gallery', true ) ), 'photo 1 = featured image, the rest = gallery in order' );

eda_save_vehicle_input( $eda_new, array( 'eda_meta' => array( 'price' => '43900' ) ) );
$eda_check( 43900 === (int) get_post_meta( $eda_new, '_eda_price', true ) && 'WBS000TEST0000001' === get_post_meta( $eda_new, '_eda_vin', true ) && (int) get_post_thumbnail_id( $eda_new ) === $eda_photos[0] && has_term( $eda_fuel, 'vehicle_fuel_type', $eda_new ) && has_term( $eda_option->term_id, 'vehicle_equipment', $eda_new ), 'partial update changes only the submitted field' );

eda_save_vehicle_input( $eda_new, array( 'eda_photos' => implode( ',', array( $eda_photos[2], $eda_photos[0] ) ) ) );
$eda_check( (int) get_post_thumbnail_id( $eda_new ) === $eda_photos[2] && array( $eda_photos[0] ) === array_map( 'intval', get_post_meta( $eda_new, '_eda_gallery', true ) ), 'reordering photos moves the main photo' );
eda_save_vehicle_input( $eda_new, array( 'eda_photos' => (string) $eda_photos[1] ) );
$eda_check( ! metadata_exists( 'post', $eda_new, '_eda_gallery' ), 'a single photo leaves no gallery' );
eda_save_vehicle_input( $eda_new, array( 'eda_vehicle_make' => $eda_audi->term_id, 'eda_vehicle_model' => $eda_model ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
$eda_check( ! wp_get_object_terms( $eda_new, 'vehicle_model', array( 'fields' => 'ids' ) ), 'a mismatched model is not saved' );
eda_save_vehicle_input( $eda_new, array( 'eda_meta' => array( 'featured' => '' ) ) );
$eda_check( ! get_post_meta( $eda_new, '_eda_featured', true ), 'unticking featured removes it' );

// ---------- Publishing guard (server side, through the real save path).
$eda_post_as_form = static function ( array $input ) {
	$_POST = array_merge( $input, array( 'eda_vehicle_nonce' => wp_create_nonce( 'eda_save_vehicle' ) ) );
};
$eda_post_as_form( array_merge( $eda_bad, array( 'eda_title_override' => '' ) ) );
$eda_guarded = wp_insert_post(
	array(
		'post_type'   => 'vehicle',
		'post_status' => 'publish',
		'post_title'  => 'Untitled',
	)
);
$eda_errors  = get_transient( 'eda_vehicle_errors_' . $eda_staff_id );
$eda_check( 'draft' === get_post_status( $eda_guarded ), 'an incomplete vehicle stays a draft' );
$eda_check( is_array( $eda_errors ) && 2 === count( $eda_errors ) && ! empty( $GLOBALS['eda_vehicle_publish_blocked'] ), 'the reasons are kept for the notice' );
$eda_check( str_contains( eda_vehicle_editor_redirect( 'post.php?post=1&message=6' ), 'message=10' ), 'redirect says "draft updated", not "published"' );
$eda_check( 'BMW M4 Test Edition' === get_the_title( $eda_guarded ), 'title set automatically from Make + Model + Variant' );
unset( $GLOBALS['eda_vehicle_publish_blocked'] );
delete_transient( 'eda_vehicle_errors_' . $eda_staff_id );
$eda_post_as_form( array_merge( $eda_full, array( 'eda_title_override' => 'BMW M4 – one owner' ) ) );
wp_update_post(
	array(
		'ID'          => $eda_guarded,
		'post_status' => 'publish',
	)
);
$eda_check( 'publish' === get_post_status( $eda_guarded ) && empty( $GLOBALS['eda_vehicle_publish_blocked'] ), 'a complete vehicle is published' );
$eda_check( 'BMW M4 – one owner' === get_post_field( 'post_title', $eda_guarded ), 'the optional title override wins' );
$eda_check( str_starts_with( get_post_field( 'post_name', $eda_guarded ), 'bmw-m4' ), 'slug generated from the title (no slug field)' );
$_POST = array();

// ---------- All Vehicles list.
$eda_check( array( 'cb', 'eda_photo', 'title', 'eda_year', 'eda_mileage', 'eda_price', 'eda_status', 'eda_featured', 'eda_modified' ) === array_keys( apply_filters( 'manage_vehicle_posts_columns', array( 'cb' => '' ) ) ), 'list columns: Photo, Vehicle, Year, Mileage, Price, Status, Featured, Last updated' );
$eda_check( array( 'eda_year', 'eda_mileage', 'eda_price', 'eda_modified' ) === array_keys( apply_filters( 'manage_edit-vehicle_sortable_columns', array() ) ), 'sortable by year, mileage, price and last updated' );
ob_start();
eda_vehicle_list_column( 'eda_status', $eda_guarded );
$eda_html = ob_get_clean();
$eda_check( str_contains( $eda_html, 'eda-status--available' ) && 2 === substr_count( $eda_html, 'form="eda-status-form"' ) && ! str_contains( $eda_html, 'href=' ), 'status badge plus two POST buttons, no GET links' );
$eda_check( eda_set_vehicle_status( $eda_guarded, 'reserved' ) && 'reserved' === get_post_meta( $eda_guarded, '_eda_availability', true ), 'quick status: reserved' );
$eda_check( ! eda_set_vehicle_status( $eda_guarded, 'bogus' ) && ! eda_set_vehicle_status( $eda_page, 'sold' ), 'quick status refuses unknown values and non-vehicles' );
$eda_check( ! empty( $GLOBALS['wp_filter']['admin_post_eda_vehicle_status'] ) && empty( $GLOBALS['wp_filter']['admin_post_nopriv_eda_vehicle_status'] ), 'quick status is logged-in POST only' );
wp_set_current_user( 0 );
ob_start();
eda_vehicle_list_column( 'eda_status', $eda_guarded );
$eda_check( ! str_contains( ob_get_clean(), '<button' ), 'no status buttons without permission' );
wp_set_current_user( $eda_staff_id );
ob_start();
eda_vehicle_list_column( 'eda_price', $eda_m4 );
$eda_check( str_contains( ob_get_clean(), '€' ), 'price column formatted in euro' );

// ---------- Existing vehicles: open, change nothing, save as staff → nothing changes.
$eda_snapshot  = static function ( $id ) {
	clean_post_cache( $id );
	$post = get_post( $id );
	$meta = get_post_meta( $id );
	unset( $meta['_edit_lock'], $meta['_edit_last'] );
	ksort( $meta );
	$terms = array();
	foreach ( get_object_taxonomies( 'vehicle' ) as $taxonomy ) {
		$terms[ $taxonomy ] = wp_get_object_terms( $id, $taxonomy, array( 'fields' => 'ids' ) );
		sort( $terms[ $taxonomy ] );
	}
	return array( $post->post_title, $post->post_name, $post->post_status, $post->post_content, $post->menu_order, $post->post_author, $post->post_date, $meta, $terms );
};
$eda_form_post = static function ( $id ) {
	add_filter( 'user_can_richedit', '__return_true' ); // As in a browser (CLI has no user agent).
	ob_start();
	eda_render_vehicle_editor( get_post( $id ) );
	$html = ob_get_clean();
	$dom  = new DOMDocument();
	libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="utf-8"?>' . $html );
	$pairs = array();
	foreach ( ( new DOMXPath( $dom ) )->query( '//input[@name] | //select[@name] | //textarea[@name]' ) as $el ) {
		$name = $el->getAttribute( 'name' );
		if ( 'select' === $el->nodeName ) {
			$value = '';
			foreach ( $el->getElementsByTagName( 'option' ) as $option ) {
				$value = $option->hasAttribute( 'selected' ) ? $option->getAttribute( 'value' ) : $value;
			}
		} elseif ( 'textarea' === $el->nodeName ) {
			$value = $el->textContent;
		} elseif ( 'checkbox' === $el->getAttribute( 'type' ) && ! $el->hasAttribute( 'checked' ) ) {
			continue;
		} else {
			$value = $el->getAttribute( 'value' );
		}
		$pairs[] = rawurlencode( $name ) . '=' . rawurlencode( $value );
	}
	parse_str( implode( '&', $pairs ), $data );
	return $data;
};
global $wpdb;
foreach ( array( 'BMW M4 Competition xDrive', 'Audi RS6 Avant', 'Mercedes-Benz A250e', 'Porsche 911 Carrera', 'Tesla Model Y Long Range', 'BMW 330e Touring' ) as $eda_title ) {
	$eda_id = get_posts(
		array(
			'post_type' => 'vehicle',
			'title'     => $eda_title,
			'fields'    => 'ids',
		)
	)[0] ?? 0;
	if ( ! $eda_id ) {
		$eda_check( false, "$eda_title exists" );
		continue;
	}
	$eda_dates  = $wpdb->get_row( $wpdb->prepare( "SELECT post_modified, post_modified_gmt FROM $wpdb->posts WHERE ID = %d", $eda_id ), ARRAY_A );
	$eda_before = $eda_snapshot( $eda_id );
	$_POST      = array_merge(
		$eda_form_post( $eda_id ),
		array(
			'post_ID'              => $eda_id,
			'post_type'            => 'vehicle',
			'action'               => 'editpost',
			'original_post_status' => get_post_status( $eda_id ),
			'save'                 => 'update',
			'post_author'          => get_post_field( 'post_author', $eda_id ), // Hidden field on the real screen.
			'user_ID'              => get_current_user_id(),
		)
	);
	$eda_saved  = edit_post();
	$_POST      = array();
	$eda_after  = $eda_snapshot( $eda_id );
	$wpdb->update( $wpdb->posts, $eda_dates, array( 'ID' => $eda_id ) );
	clean_post_cache( $eda_id );
	$eda_diff   = array_keys( array_filter( array_map( static fn( $a, $b ) => $a !== $b, $eda_before, $eda_after ) ) );
	$eda_status = get_post_meta( $eda_id, '_eda_availability', true );
	$eda_check( $eda_id === $eda_saved && ! $eda_diff, "$eda_title ($eda_status) saved unchanged by staff" . ( $eda_diff ? ' – changed: ' . implode( ',', $eda_diff ) : '' ) );
	if ( $eda_diff && isset( $eda_diff[0] ) && 7 === $eda_diff[0] ) {
		foreach ( $eda_before[7] as $eda_key => $eda_value ) {
			if ( ( $eda_after[7][ $eda_key ] ?? null ) !== $eda_value ) {
				echo "     $eda_key: " . wp_json_encode( $eda_value ) . ' → ' . wp_json_encode( $eda_after[7][ $eda_key ] ?? null ) . PHP_EOL;
			}
		}
	}
}
$eda_check( 'sold' === get_post_meta( get_posts( array( 'post_type' => 'vehicle', 'title' => 'Mercedes-Benz A250e', 'fields' => 'ids' ) )[0], '_eda_availability', true ), 'A250e is still sold' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound

// ---------- Clean up.
wp_set_current_user( 0 );
wp_delete_post( $eda_new, true );
wp_delete_post( $eda_guarded, true );
wp_delete_term( $eda_option->term_id, 'vehicle_equipment' );
wp_delete_user( $eda_staff_id );
$eda_check( 15 === (int) wp_count_posts( 'vehicle' )->publish, 'still exactly 15 published vehicles' );

echo PHP_EOL . ( $eda_failures ? "$eda_failures FAILED" : 'ALL PASSED' ) . PHP_EOL;
