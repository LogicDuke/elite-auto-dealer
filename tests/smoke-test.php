<?php
/**
 * Smoke test for the vehicle data model, enquiries and SEO helpers. Requires the theme to be active.
 *
 * Run from the WordPress root:  wp eval-file wp-content/themes/elite-auto-dealer/tests/smoke-test.php
 *
 * Creates one draft vehicle and one enquiry, and deletes both again.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput, WordPress.WP.GlobalVariablesOverride -- CLI test script; globals are swapped to simulate requests and restored.

$eda_failures = 0;

$eda_check = static function ( $condition, $message ) use ( &$eda_failures ) {
	echo ( $condition ? 'PASS ' : 'FAIL ' ) . $message . PHP_EOL;
	$eda_failures += $condition ? 0 : 1;
};

// Post type and URLs.
$eda_type = get_post_type_object( 'vehicle' );
$eda_check( $eda_type && $eda_type->public, 'vehicle post type registered and public' );
$eda_check( 'vehicles' === $eda_type->has_archive, 'archive slug is /vehicles/' );
$eda_check( 'vehicle' === $eda_type->rewrite['slug'], 'single slug is /vehicle/{slug}/' );
$eda_check( str_ends_with( get_post_type_archive_link( 'vehicle' ), '/vehicles/' ), 'archive link ends in /vehicles/' );

// Single-dealer scope: no public write path to inventory.
wp_set_current_user( 0 );
$eda_rest = rest_do_request(
	new WP_REST_Request(
		'POST',
		'/wp/v2/vehicle'
	)
);
$eda_check( in_array( $eda_rest->get_status(), array( 401, 403 ), true ), 'anonymous REST cannot create vehicles' );
$eda_check( ! get_role( 'subscriber' )->has_cap( $eda_type->cap->create_posts ), 'subscribers cannot create vehicles' );
$eda_public = array_filter(
	array_keys( $GLOBALS['wp_filter'] ),
	static fn( $hook ) => str_starts_with( $hook, 'admin_post_nopriv_' ) || str_starts_with( $hook, 'wp_ajax_nopriv_' )
);
sort( $eda_public );
$eda_check( array( 'admin_post_nopriv_eda_enquiry', 'wp_ajax_nopriv_eda_enquiry_nonce' ) === array_values( $eda_public ), 'public handlers: enquiry form (write) + nonce refresh (read-only) only' );
$eda_check( ! array_filter( array_keys( rest_get_server()->get_routes() ), static fn( $route ) => str_contains( $route, 'eda' ) ), 'theme registers no custom REST routes' );

// Taxonomies.
foreach ( array( 'vehicle_make', 'vehicle_model', 'vehicle_body_type', 'vehicle_fuel_type', 'vehicle_transmission', 'vehicle_condition', 'vehicle_equipment' ) as $eda_tax ) {
	$eda_check( is_object_in_taxonomy( 'vehicle', $eda_tax ), "taxonomy $eda_tax attached to vehicle" );
}
$eda_check( term_exists( 'diesel', 'vehicle_fuel_type' ), 'fuel types seeded' );
$eda_check( isset( get_registered_meta_keys( 'term', 'vehicle_model' )['eda_make'] ), 'model -> make term meta registered' );
$eda_check( 'vehicles/make' === get_taxonomy( 'vehicle_make' )->rewrite['slug'], 'make archive under /vehicles/make/' );

// Meta registration.
$eda_registered = get_registered_meta_keys( 'post', 'vehicle' );
foreach ( array_keys( eda_vehicle_meta_fields() ) as $eda_key ) {
	$eda_check( isset( $eda_registered[ '_eda_' . $eda_key ] ), "meta _eda_$eda_key registered" );
}
$eda_check( ! isset( $eda_registered['_eda_vat_deductible'] ), 'old vat_deductible boolean removed' );
$eda_check( 'number' === $eda_registered['_eda_battery_kwh']['type'], 'battery_kwh registered as number' );

// Sanitising.
$eda_fields = eda_vehicle_meta_fields();
$eda_s      = static fn( $value, $key ) => eda_sanitize_vehicle_meta_value( $value, $eda_fields[ $key ] );
$eda_check( 24950 === $eda_s( '24950', 'price' ), 'integer kept' );
$eda_check( 7 === $eda_s( 7, 'doors' ), 'integer from int input kept' );
$eda_check( '' === $eda_s( 'abc', 'price' ), 'non-numeric integer rejected' );
$eda_check( '' === $eda_s( '12.500', 'mileage' ), 'integer with thousands dot rejected, not truncated' );
$eda_check( '' === $eda_s( '4.5', 'doors' ), 'decimal rejected for integer field' );
$eda_check( '' === $eda_s( '-5', 'seats' ), 'negative integer rejected' );
$eda_check( 77.4 === $eda_s( '77.4', 'battery_kwh' ), 'decimal with dot kept' );
$eda_check( 77.4 === $eda_s( '77,4', 'battery_kwh' ), 'decimal comma accepted' );
$eda_check( 64.8 === $eda_s( '64.83', 'battery_kwh' ), 'decimal rounded to field precision' );
$eda_check( 82.0 === $eda_s( '82', 'battery_kwh' ), 'whole number accepted for decimal field' );
$eda_check( '' === $eda_s( '-1', 'battery_kwh' ), 'negative decimal rejected' );
$eda_check( '' === $eda_s( '1.234,5', 'battery_kwh' ), 'ambiguous separators rejected' );
$eda_check( 450 === $eda_s( '450', 'ev_range_km' ), 'EV range integer kept' );
$eda_check( 'margin' === $eda_s( 'margin', 'vat_regime' ), 'VAT regime margin kept' );
$eda_check( 'deductible' === $eda_s( 'deductible', 'vat_regime' ), 'VAT regime deductible kept' );
$eda_check( '' === $eda_s( 'yes', 'vat_regime' ), 'unknown VAT regime rejected' );
$eda_check( 'Competition xDrive' === $eda_s( ' Competition xDrive ', 'variant' ), 'variant trimmed text' );
$eda_check( '' === $eda_s( '2020-13-45', 'first_registration' ), 'invalid date rejected' );
$eda_check( '2020-03-15' === $eda_s( '2020-03-15', 'first_registration' ), 'valid date kept' );
$eda_check( 'WVWZZZ1KZ6W000001' === $eda_s( ' wvwzzz1kz6w-000001 ', 'vin' ), 'VIN normalised' );
$eda_check( '' === $eda_s( 'stolen', 'availability' ), 'unknown enum rejected' );
$eda_check( array( 3, 7 ) === $eda_s( '3,x,7,0', 'gallery' ), 'gallery IDs cleaned' );

// Image sizes and srcset (fake attachment metadata, no files needed).
$eda_sizes = wp_get_registered_image_subsizes();
foreach ( array(
	'eda-vehicle-card'   => array( 640, 427 ),
	'eda-vehicle-medium' => array( 960, 640 ),
	'eda-vehicle-large'  => array( 1536, 1024 ),
) as $eda_size => list( $eda_w, $eda_h ) ) {
	$eda_check( isset( $eda_sizes[ $eda_size ] ) && $eda_w === $eda_sizes[ $eda_size ]['width'] && $eda_h === $eda_sizes[ $eda_size ]['height'] && $eda_sizes[ $eda_size ]['crop'], "image size $eda_size {$eda_w}x{$eda_h} cropped" );
}
$eda_check( 1536 === $eda_sizes['eda-vehicle-large']['width'] && 0 === $eda_sizes['eda-vehicle-large']['width'] * 2 - $eda_sizes['eda-vehicle-large']['height'] * 3, 'largest vehicle size equals the 1536x1024 3:2 source contract (never above it)' );
// A 1536x1024 upload: WordPress skips "large" (same size as the original) and lists the original itself.
$eda_meta   = array(
	'width'  => 1536,
	'height' => 1024,
	'file'   => '2026/10/car.jpg',
	'sizes'  => array(
		'eda-vehicle-card'   => array(
			'file'   => 'car-640x427.jpg',
			'width'  => 640,
			'height' => 427,
		),
		'eda-vehicle-medium' => array(
			'file'   => 'car-960x640.jpg',
			'width'  => 960,
			'height' => 640,
		),
	),
);
$eda_srcset = (string) wp_calculate_image_srcset( array( 640, 427 ), content_url( 'uploads/2026/10/car-640x427.jpg' ), $eda_meta );
$eda_check( str_contains( $eda_srcset, '640w' ) && str_contains( $eda_srcset, '960w' ) && str_contains( $eda_srcset, 'car.jpg 1536w' ) && ! preg_match( '/\b(1[6-9]\d\d|[2-9]\d{3})w\b/', $eda_srcset ), '3:2 sizes and the 1536 original combine into one srcset, nothing wider' );

// Enquiry validation (pure).
$eda_ok = eda_validate_enquiry(
	array(
		'type'    => 'test_drive',
		'name'    => ' Jan Peeters ',
		'email'   => 'jan@example.be',
		'phone'   => '+32 470 12 34 56<script>',
		'message' => 'Zaterdag?',
		'consent' => '1',
	)
);
$eda_check( array() === $eda_ok['errors'], 'valid enquiry passes' );
$eda_check( 'test_drive' === $eda_ok['data']['type'] && 'Jan Peeters' === $eda_ok['data']['name'], 'enquiry type and name kept' );
$eda_check( '+32 470 12 34 56' === $eda_ok['data']['phone'], 'phone stripped to valid characters' );
$eda_bad = eda_validate_enquiry(
	array(
		'type'       => 'credit_application',
		'email'      => 'nope',
		'vehicle_id' => 999999,
	)
);
$eda_check( array( 'name', 'email', 'consent' ) === $eda_bad['errors'], 'missing name, bad email, missing consent rejected' );
$eda_check( 'general' === $eda_bad['data']['type'] && 0 === $eda_bad['data']['vehicle_id'], 'unknown type -> general, unknown vehicle -> 0' );
$eda_check( array( 'vehicle_id', 'type', 'name', 'email', 'phone', 'message', 'consent' ) === array_keys( $eda_ok['data'] ), 'enquiry collects only the minimal field set' );

// Inventory heading rule (pure).
$eda_electric = get_term_by( 'slug', 'electric', 'vehicle_fuel_type' );
$eda_check( 'Electric vehicles' === eda_inventory_heading( $eda_electric, array() ), 'term archive heading: "Electric vehicles"' );
$eda_check( 'Vehicles' === eda_inventory_heading( $eda_electric, array( 'fuel' => 'electric' ) ), 'query-string filter heading stays neutral' );
$eda_check(
	'Vehicles' === eda_inventory_heading(
		null,
		array(
			'make'      => 'bmw',
			'price_max' => '30000',
		)
	),
	'multi-filter heading stays neutral'
);
$eda_check( 'Vehicles' === eda_inventory_heading( null, array() ), 'plain inventory heading' );
$eda_check( 'Electric vehicles' === eda_inventory_heading( $eda_electric, array( 'utm_source' => 'x' ) ), 'tracking params keep the term heading' );

// Breadcrumb on the inventory archive: Home > Vehicles, no duplicate archive crumb.
$eda_saved_query         = $GLOBALS['wp_query'];
$GLOBALS['wp_query']     = new WP_Query( array( 'post_type' => 'vehicle' ) );
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
$eda_check( array( 'Home', 'Vehicles' ) === wp_list_pluck( eda_breadcrumb_trail(), 0 ), 'inventory breadcrumb is Home > Vehicles' );
$GLOBALS['wp_query']     = $eda_saved_query;
$GLOBALS['wp_the_query'] = $eda_saved_query;

// Canonical/robots decision (pure).
$eda_check( ! eda_is_filtered_request( array() ), 'clean listing is not filtered' );
$eda_check( ! eda_is_filtered_request( array( 'utm_source' => 'x' ) ), 'tracking params do not count as filters' );
$eda_check( ! eda_is_filtered_request( array( 'make' => '' ) ), 'empty filter param ignored' );
$eda_check( eda_is_filtered_request( array( 'fuel' => 'diesel' ) ), 'taxonomy filter param detected' );
$eda_check( eda_is_filtered_request( array( 'price_max' => '30000' ) ), 'range filter param detected' );

// Round trip through the database + structured data.
$eda_post_id = wp_insert_post(
	array(
		'post_type'    => 'vehicle',
		'post_title'   => 'Smoke test vehicle',
		'post_status'  => 'publish',
		'post_excerpt' => 'Test excerpt.',
	)
);
update_post_meta( $eda_post_id, '_eda_mileage', '86000' );
update_post_meta( $eda_post_id, '_eda_vin', 'abc 123' );
update_post_meta( $eda_post_id, '_eda_gallery', array( '5', '9' ) );
update_post_meta( $eda_post_id, '_eda_battery_kwh', '77,4' );
update_post_meta( $eda_post_id, '_eda_vat_regime', 'margin' );
update_post_meta( $eda_post_id, '_eda_variant', 'Competition xDrive' );
update_post_meta( $eda_post_id, '_eda_price', '45900' );
update_post_meta( $eda_post_id, '_eda_power_kw', '375' );
$eda_check( '86000' === eda_vehicle_meta( 'mileage', $eda_post_id ), 'mileage round trip' );
$eda_check( 'ABC123' === eda_vehicle_meta( 'vin', $eda_post_id ), 'sanitize callback applied on write' );
$eda_check( array( 5, 9 ) === eda_vehicle_meta( 'gallery', $eda_post_id ), 'gallery stored as int array' );
$eda_check( 77.4 === (float) eda_vehicle_meta( 'battery_kwh', $eda_post_id ), 'decimal comma stored as 77.4' );
$eda_specs = eda_vehicle_specs( $eda_post_id );
$eda_check( isset( $eda_specs['Mileage'] ) && isset( $eda_specs['VAT regime'] ), 'spec table includes mileage and VAT regime' );
$eda_check( '2024' === eda_format_vehicle_meta_value( '2024', $eda_fields['year'] ), 'year shown without digit grouping' );
$eda_check( '86,000 km' === eda_format_vehicle_meta_value( '86000', $eda_fields['mileage'] ), 'mileage keeps digit grouping and unit' );
$eda_check( ! isset( $eda_specs['Variant / trim'] ), 'variant shown as subtitle, not duplicated in specs' );

$eda_schema = eda_vehicle_schema( $eda_post_id );
$eda_check( 'Car' === $eda_schema['@type'] && 'Competition xDrive' === $eda_schema['vehicleConfiguration'], 'schema: Car with variant' );
$eda_check( 86000 === $eda_schema['mileageFromOdometer']['value'] && 'KMT' === $eda_schema['mileageFromOdometer']['unitCode'], 'schema: mileage in km' );
$eda_check( 45900 === $eda_schema['offers']['price'] && 'EUR' === $eda_schema['offers']['priceCurrency'], 'schema: offer from real price' );
$eda_check( ! isset( $eda_schema['offers']['availability'] ), 'schema: no availability invented when not set' );
$eda_check( ! isset( $eda_schema['brand'], $eda_schema['itemCondition'], $eda_schema['aggregateRating'], $eda_schema['image'] ), 'schema: no brand, condition, rating or images invented' );
$eda_check( 375 === $eda_schema['vehicleEngine']['enginePower']['value'], 'schema: engine power from kW' );
update_post_meta( $eda_post_id, '_eda_availability', 'sold' );
$eda_check( 'https://schema.org/SoldOut' === eda_vehicle_schema( $eda_post_id )['offers']['availability'], 'schema: sold -> SoldOut' );
foreach ( array(
	'new'  => 'https://schema.org/NewCondition',
	'used' => 'https://schema.org/UsedCondition',
	'demo' => 'https://schema.org/UsedCondition',
) as $eda_condition => $eda_expected ) {
	wp_set_object_terms( $eda_post_id, $eda_condition, 'vehicle_condition' );
	$eda_check( eda_vehicle_schema( $eda_post_id )['itemCondition'] === $eda_expected, "schema: condition $eda_condition -> " . basename( $eda_expected ) );
}
$eda_check( 'Demo' === get_term_by( 'slug', 'demo', 'vehicle_condition' )->name, 'demo condition label stays "Demo"' );
wp_set_object_terms( $eda_post_id, array(), 'vehicle_condition' );
$eda_check( ! isset( eda_vehicle_schema( $eda_post_id )['itemCondition'] ), 'schema: no condition inferred when none stored' );
delete_post_meta( $eda_post_id, '_eda_price' );
$eda_check( ! isset( eda_vehicle_schema( $eda_post_id )['offers'] ), 'schema: no offer without price' );
$eda_json = wp_json_encode( array( 'name' => '</script><b>' ), JSON_HEX_TAG );
$eda_check( ! str_contains( $eda_json, '</script>' ), 'JSON-LD encoding cannot close the script tag' );

// Enquiry storage.
$eda_ok_vehicle = eda_validate_enquiry( array_merge( $eda_ok['data'], array( 'vehicle_id' => $eda_post_id ) ) );
$eda_enquiry_id = eda_store_enquiry( $eda_ok_vehicle['data'] );
$eda_check( $eda_enquiry_id && 'eda_enquiry' === get_post_type( $eda_enquiry_id ) && 'private' === get_post_status( $eda_enquiry_id ), 'enquiry stored as private eda_enquiry' );
$eda_check( (int) get_post_meta( $eda_enquiry_id, '_eda_enquiry_vehicle_id', true ) === $eda_post_id, 'enquiry keeps vehicle ID' );
$eda_check( str_contains( get_post_meta( $eda_enquiry_id, '_eda_enquiry_vehicle_label', true ), 'Smoke test vehicle' ), 'enquiry keeps vehicle snapshot' );
$eda_check( eda_enquiry_consent_text() === get_post_meta( $eda_enquiry_id, '_eda_enquiry_consent_text', true ), 'consent text stored with enquiry' );
$eda_check( (bool) get_post_field( 'post_date_gmt', $eda_enquiry_id ), 'enquiry timestamp set' );
$eda_check( ! get_post_type_object( 'eda_enquiry' )->public && ! get_post_type_object( 'eda_enquiry' )->show_in_rest, 'enquiries not public, not in REST' );
wp_delete_post( $eda_enquiry_id, true );

// Privacy export / erase.
$eda_email   = 'privacy-test@example.be';
$eda_private = eda_store_enquiry(
	eda_validate_enquiry(
		array(
			'vehicle_id' => $eda_post_id,
			'type'       => 'trade_in',
			'name'       => 'Privacy Test',
			'email'      => $eda_email,
			'phone'      => '0470 00 00 00',
			'message'    => 'BMW 320d, 2019, 85 000 km',
			'consent'    => '1',
		)
	)['data']
);
$eda_other   = eda_store_enquiry( array_merge( $eda_ok['data'], array( 'email' => 'someone-else@example.be' ) ) );
$eda_check( isset( apply_filters( 'wp_privacy_personal_data_exporters', array() )['elite-auto-dealer-enquiries'] ), 'exporter registered with WordPress' );
$eda_check( isset( apply_filters( 'wp_privacy_personal_data_erasers', array() )['elite-auto-dealer-enquiries'] ), 'eraser registered with WordPress' );
$eda_export = eda_privacy_export_enquiries( $eda_email, 1 );
$eda_values = wp_list_pluck( $eda_export['data'][0]['data'] ?? array(), 'value', 'name' );
$eda_check( 1 === count( $eda_export['data'] ) && $eda_export['done'], 'export returns exactly this person\'s enquiry' );
$eda_check( array( 'Received', 'Type', 'Vehicle', 'Name', 'Email', 'Phone', 'Message', 'Consent' ) === array_keys( $eda_values ), 'export contains date, type, vehicle, name, email, phone, message, consent' );
$eda_check( 'Trade-in' === $eda_values['Type'] && $eda_email === $eda_values['Email'] && eda_enquiry_consent_text() === $eda_values['Consent'], 'export values match stored data' );
$eda_erase = eda_privacy_erase_enquiries( $eda_email );
$eda_check( $eda_erase['items_removed'] && ! $eda_erase['items_retained'] && $eda_erase['done'], 'eraser reports removal' );
foreach ( array( 'name', 'email', 'phone', 'message', 'consent', 'consent_text' ) as $eda_key ) {
	$eda_check( '' === get_post_meta( $eda_private, '_eda_enquiry_' . $eda_key, true ), "erased: $eda_key" );
}
$eda_check( 'trade_in' === get_post_meta( $eda_private, '_eda_enquiry_type', true ) && (int) get_post_meta( $eda_private, '_eda_enquiry_vehicle_id', true ) === $eda_post_id && get_post_meta( $eda_private, '_eda_enquiry_vehicle_label', true ), 'kept: type, vehicle ID, vehicle label' );
$eda_check( ! str_contains( get_the_title( $eda_private ), 'Privacy Test' ) && get_post_meta( $eda_private, '_eda_enquiry_anonymised', true ), 'name removed from title, anonymised timestamp set' );
$eda_check( array() === eda_privacy_export_enquiries( $eda_email, 1 )['data'], 'nothing left to export after erasure' );
$eda_check( 'someone-else@example.be' === get_post_meta( $eda_other, '_eda_enquiry_email', true ), 'other people\'s enquiries untouched' );
wp_delete_post( $eda_private, true );
wp_delete_post( $eda_other, true );

// Retention clean-up.
$eda_old    = eda_store_enquiry( $eda_ok['data'] );
$eda_new    = eda_store_enquiry( $eda_ok['data'] );
$eda_old_ts = gmdate( 'Y-m-d H:i:s', strtotime( '-13 months' ) );
$eda_new_ts = gmdate( 'Y-m-d H:i:s', strtotime( '-11 months' ) );
$eda_old_wp = wp_insert_post(
	array(
		'post_type'     => 'post',
		'post_status'   => 'draft',
		'post_title'    => 'Retention control post',
		'post_date'     => get_date_from_gmt( $eda_old_ts ),
		'post_date_gmt' => $eda_old_ts,
	)
);
foreach ( array(
	$eda_old => $eda_old_ts,
	$eda_new => $eda_new_ts,
) as $eda_id => $eda_ts ) {
	wp_update_post(
		array(
			'ID'            => $eda_id,
			'post_date'     => get_date_from_gmt( $eda_ts ),
			'post_date_gmt' => $eda_ts,
		)
	);
}
$eda_check( 12 === eda_enquiry_retention_months(), 'default retention is 12 months' );
$eda_check( (bool) wp_next_scheduled( 'eda_purge_expired_enquiries' ), 'one recurring clean-up job scheduled' );
$eda_check( 'daily' === wp_get_schedule( 'eda_purge_expired_enquiries' ), 'clean-up runs daily' );
do_action( 'eda_purge_expired_enquiries' );
$eda_check( null === get_post( $eda_old ), '13-month-old enquiry deleted' );
$eda_check( null !== get_post( $eda_new ), '11-month-old enquiry kept' );
$eda_check( null !== get_post( $eda_old_wp ), 'old non-enquiry post untouched' );
$eda_check( null !== get_post( $eda_post_id ), 'vehicle untouched' );
add_filter( 'eda_enquiry_retention_months', '__return_zero' );
$eda_check( 0 === eda_purge_expired_enquiries(), 'retention 0 disables deletion' );
remove_filter( 'eda_enquiry_retention_months', '__return_zero' );
$eda_six = static fn() => 6;
add_filter( 'eda_enquiry_retention_months', $eda_six );
$eda_check( 1 === eda_purge_expired_enquiries() && null === get_post( $eda_new ), 'retention is filterable (6 months removes the 11-month enquiry)' );
remove_filter( 'eda_enquiry_retention_months', $eda_six );
wp_delete_post( $eda_old_wp, true );
wp_delete_post( $eda_post_id, true );

echo PHP_EOL . ( $eda_failures ? "$eda_failures FAILED" : 'ALL PASSED' ) . PHP_EOL;
if ( $eda_failures ) {
	exit( 1 );
}
