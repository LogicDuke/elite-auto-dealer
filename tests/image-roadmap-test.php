<?php
/**
 * Validates demo/image-roadmap.json against demo/vehicles.json and the image contract.
 * No WordPress needed:
 *
 * Commands:
 *   php tests/image-roadmap-test.php        (from the theme root)
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || 'cli' === PHP_SAPI || exit;

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput -- CLI test script.

$eda_root     = dirname( __DIR__ );
$eda_raw      = (string) file_get_contents( $eda_root . '/demo/image-roadmap.json' );
$eda_manifest = json_decode( $eda_raw, true );
$eda_vehicles = array_column( json_decode( (string) file_get_contents( $eda_root . '/demo/vehicles.json' ), true )['vehicles'], null, 'stock_id' );
$eda_doc      = (string) file_get_contents( $eda_root . '/docs/VEHICLE-IMAGE-ROADMAP.md' );
$eda_roles    = array( 'hero', 'rear', 'cockpit', 'interior', 'detail' );
$eda_failures = 0;

$eda_check = static function ( $condition, $message ) use ( &$eda_failures ) {
	echo ( $condition ? 'PASS ' : 'FAIL ' ) . $message . PHP_EOL;
	$eda_failures += $condition ? 0 : 1;
};

$eda_slots = $eda_manifest['slots'] ?? array();
$eda_check( is_array( $eda_manifest ), 'manifest is valid JSON' );
$eda_check( 15 === count( $eda_vehicles ), 'vehicles.json has exactly 15 vehicles' );
$eda_check( 75 === count( $eda_slots ), 'exactly 75 image slots' );
$eda_declared = $eda_manifest['roles'] ?? array();
$eda_check( 5 === ( $eda_manifest['images_per_vehicle'] ?? 0 ) && $eda_declared === $eda_roles, 'manifest declares 5 roles per vehicle in order' );
$eda_check( 75 === count( array_unique( array_column( $eda_slots, 'filename' ) ) ), 'filenames unique' );
$eda_check( 75 === count( array_unique( array_column( $eda_slots, 'path' ) ) ), 'paths unique' );
$eda_check( ! preg_match( '#https?://|www\.#i', $eda_raw ), 'no remote URLs anywhere in the manifest' );

// One canonical image contract for all 75 vehicle photographs (no per-slot copies that can drift).
$eda_c = $eda_manifest['image_contract'] ?? array();
$eda_check( 1536 === ( $eda_c['width'] ?? 0 ) && 1024 === ( $eda_c['height'] ?? 0 ), 'image_contract is exactly 1536 x 1024 px' );
$eda_check( '3:2' === ( $eda_c['aspect_ratio'] ?? '' ) && 'landscape' === ( $eda_c['orientation'] ?? '' ) && $eda_c['width'] * 2 === $eda_c['height'] * 3, 'image_contract is exact 3:2 landscape' );
$eda_check( 'jpeg' === ( $eda_c['format'] ?? '' ) && 'jpg' === ( $eda_c['extension'] ?? '' ) && 'sRGB' === ( $eda_c['color_space'] ?? '' ) && str_ends_with( $eda_manifest['directory_pattern'] ?? '', '.jpg' ), 'image_contract is JPEG (.jpg), sRGB' );
$eda_w = $eda_c['weight_target_kb'] ?? array();
$eda_check( 180 === ( $eda_w['min'] ?? 0 ) && 350 === ( $eda_w['max'] ?? 0 ) && 450 === ( $eda_c['weight_preferred_max_kb'] ?? 0 ), 'weight target 180–350 KB, preferred maximum 450 KB (declared; bytes are checked at import QA)' );
$eda_check( 'never' === ( $eda_c['upscaling'] ?? '' ) && 'reject' === ( $eda_c['smaller_than_contract'] ?? '' ), 'no upscaling: smaller images are rejected' );
$eda_check( ! isset( $eda_manifest['master'] ) && str_contains( $eda_doc, '1536 × 1024' ) && ! preg_match( '/2400\s*[×x]\s*1600/u', $eda_doc . $eda_raw ), 'roadmap doc and manifest carry no superseded 2400 x 1600 master' );

$eda_by_vehicle = array();
foreach ( $eda_slots as $eda_slot ) {
	$eda_by_vehicle[ $eda_slot['stock_id'] ][] = $eda_slot;
}
$eda_check( array_keys( $eda_vehicles ) == array_keys( $eda_by_vehicle ), 'slot stock IDs are exactly the 15 vehicles in vehicles.json' ); // phpcs:ignore Universal.Operators.StrictComparisons -- key sets, order-insensitive.

foreach ( $eda_by_vehicle as $eda_id => $eda_list ) {
	$eda_v      = $eda_vehicles[ $eda_id ] ?? null;
	$eda_lower  = strtolower( $eda_id );
	$eda_errors = array();

	if ( ! $eda_v ) {
		$eda_check( false, "$eda_id exists in vehicles.json" );
		continue;
	}
	if ( 5 !== count( $eda_list ) ) {
		$eda_errors[] = 'not 5 slots';
	}
	if ( array_column( $eda_list, 'role' ) !== $eda_roles ) {
		$eda_errors[] = 'roles not exactly hero, rear, cockpit, interior, detail in order';
	}
	foreach ( $eda_list as $eda_i => $eda_slot ) {
		$eda_role = $eda_slot['role'];
		if ( "$eda_lower-$eda_role.jpg" !== $eda_slot['filename'] || ! preg_match( '/^aur-26\d{3}-(hero|rear|cockpit|interior|detail)\.jpg$/', $eda_slot['filename'] ) ) {
			$eda_errors[] = "filename convention: {$eda_slot['filename']}";
		}
		if ( "demo/images/$eda_lower/{$eda_slot['filename']}" !== $eda_slot['path'] ) {
			$eda_errors[] = "path: {$eda_slot['path']}";
		}
		if ( preg_match( '/placeholder|todo|tbd|sample|example|dummy/i', $eda_slot['filename'] ) ) {
			$eda_errors[] = 'placeholder filename';
		}
		if ( array_intersect_key( $eda_slot, array_flip( array( 'aspect_ratio', 'target_width', 'target_height', 'width', 'height', 'format', 'color_space' ) ) ) ) {
			$eda_errors[] = "$eda_role overrides the global image_contract";
		}
		if ( $eda_i + 1 !== $eda_slot['order'] || ( 'hero' === $eda_role ) !== $eda_slot['featured'] ) {
			$eda_errors[] = "$eda_role order/featured";
		}
		if ( ! str_starts_with( $eda_slot['alt_text'], $eda_v['title'] . ' — ' ) || preg_match( '/\b(image|photo|picture) of\b/i', $eda_slot['alt_text'] ) ) {
			$eda_errors[] = "$eda_role alt text: {$eda_slot['alt_text']}";
		}
		if ( $eda_slot['vehicle_title'] !== $eda_v['title'] || strlen( $eda_slot['shot_description'] ) < 40 ) {
			$eda_errors[] = "$eda_role title or shot description";
		}
		// Continuity: every slot carries the exact recorded colours, LHD and plate rule.
		foreach ( array( $eda_v['meta']['exterior_colour'], $eda_v['meta']['interior_colour'], 'left-hand drive', 'no characters' ) as $eda_needle ) {
			if ( ! str_contains( $eda_slot['continuity_notes'], $eda_needle ) ) {
				$eda_errors[] = "$eda_role continuity missing: $eda_needle";
			}
		}
		if ( ! str_contains( $eda_slot['must_not'], 'right-hand drive' ) ) {
			$eda_errors[] = "$eda_role must_not lacks right-hand-drive guard";
		}
		if ( ! str_contains( $eda_doc, '`' . $eda_slot['filename'] . '`' ) || ! str_contains( $eda_doc, $eda_slot['alt_text'] ) ) {
			$eda_errors[] = "{$eda_slot['filename']} missing from docs/VEHICLE-IMAGE-ROADMAP.md";
		}
	}
	$eda_check( ! $eda_errors, "$eda_id {$eda_v['title']}: 5 valid slots" . ( $eda_errors ? ' — ' . implode( '; ', $eda_errors ) : '' ) );
}

// Cross-vehicle diversity: detail shots, hero angles/zones vary; no two exterior colours repeat.
$eda_info = array_column( $eda_manifest['vehicles'] ?? array(), null, 'stock_id' );
$eda_check( 15 === count( $eda_info ), 'vehicle summaries present for all 15' );
$eda_check( count( array_unique( array_column( $eda_info, 'detail_shot' ) ) ) >= 10, 'detail shots are varied (at least 10 distinct)' );
$eda_check( count( array_unique( array_column( $eda_info, 'forecourt_zone' ) ) ) >= 3 && 2 === count( array_unique( array_column( $eda_info, 'hero_angle' ) ) ), 'hero zones and angles vary' );
$eda_check( 15 === count( array_unique( array_map( static fn( $v ) => $v['meta']['exterior_colour'], $eda_vehicles ) ) ), 'all exterior colours distinct' );
$eda_check( ! preg_grep( '/\.(jpe?g|png|webp)$/i', (array) glob( $eda_root . '/demo/images/*/*' ) ), 'no image files present yet (planning phase)' );

echo PHP_EOL . ( $eda_failures ? "$eda_failures FAILED" : 'ALL PASSED' ) . PHP_EOL;
exit( $eda_failures ? 1 : 0 );
