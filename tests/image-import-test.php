<?php
/**
 * Demo image importer test. Run after seeding, with the approved JPEGs in demo/images/:
 *
 * Commands:
 *   wp eval-file wp-content/themes/elite-auto-dealer/demo/seed.php
 *   wp eval-file wp-content/themes/elite-auto-dealer/tests/image-import-test.php
 *
 * Covers the QA gate, first import, idempotency, checksum replacement, cleanup safety (with a
 * deliberately created non-demo attachment), alt text and derivative sizes.
 * Leaves the 75 demo images imported.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput -- CLI test script.

define( 'EDA_IMAGES_LIBRARY', true );
require_once get_template_directory() . '/demo/import-images.php';

$eda_failures = 0;
$eda_check    = static function ( $condition, $message ) use ( &$eda_failures ) {
	echo ( $condition ? 'PASS ' : 'FAIL ' ) . $message . PHP_EOL;
	$eda_failures += $condition ? 0 : 1;
};

$eda_manifest = eda_images_manifest();
$eda_dataset  = $eda_manifest['dataset'];
$eda_contract = $eda_manifest['image_contract'];
$eda_dir      = get_template_directory() . '/demo/images';
$eda_tmp      = get_temp_dir() . 'eda-image-test-' . wp_generate_password( 6, false );
$eda_roles    = array( 'hero', 'rear', 'cockpit', 'interior', 'detail' );
$eda_tagged   = static fn() => get_posts(
	array(
		'post_type'      => 'attachment',
		'post_status'    => 'any',
		'fields'         => 'ids',
		'posts_per_page' => -1,
		'meta_key'       => '_eda_demo_image', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	)
);
// Stock ID => [ featured ID, gallery IDs ] for every demo vehicle.
$eda_read_links = static function () use ( $eda_manifest, $eda_dataset ) {
	$links = array();
	foreach ( array_unique( array_column( $eda_manifest['slots'], 'stock_id' ) ) as $stock_id ) {
		$vehicle            = eda_images_vehicle( $stock_id, $eda_dataset );
		$links[ $stock_id ] = array( (int) get_post_thumbnail_id( $vehicle ), array_filter( (array) get_post_meta( $vehicle, '_eda_gallery', true ) ) );
	}
	return $links;
};
wp_mkdir_p( $eda_tmp );

// A. Source contract and QA gate.
$eda_valid = eda_images_validate( $eda_manifest, $eda_dir );
$eda_check( 75 === count( $eda_manifest['slots'] ) && 75 === count( $eda_valid['valid'] ) && ! $eda_valid['failed'] && ! $eda_valid['missing'], '75 canonical source files pass the QA gate (' . count( $eda_valid['valid'] ) . ' valid)' );
$eda_check( ! array_diff( array_keys( $eda_valid['valid'] ), array_column( $eda_manifest['slots'], 'filename' ) ), 'only manifest filenames are accepted' );
$eda_sizes = array_map( 'filesize', $eda_valid['valid'] );
$eda_check( max( $eda_sizes ) <= 450 * 1024, sprintf( 'every source <= 450 KB (largest %d KB; 351–450 KB is allowed)', ceil( max( $eda_sizes ) / 1024 ) ) );

$eda_small = imagecreatetruecolor( 1200, 800 );
imagejpeg( $eda_small, "$eda_tmp/small.jpg", 90 );
imagepng( $eda_small, "$eda_tmp/fake.jpg" );
copy( $eda_valid['valid']['aur-26001-hero.jpg'], "$eda_tmp/heavy.jpg" );
file_put_contents( "$eda_tmp/heavy.jpg", str_repeat( "\0", 120 * 1024 ), FILE_APPEND ); // Trailing bytes: still decodes, now > 450 KB.
copy( $eda_valid['valid']['aur-26001-hero.jpg'], "$eda_tmp/wrong.jpeg" );
$eda_errors = static fn( $name ) => implode( '; ', eda_images_check_file( "$eda_tmp/$name", $eda_contract ) );
$eda_check( str_contains( $eda_errors( 'small.jpg' ), 'dimensions 1200x800' ), 'QA gate rejects a smaller image (no upscaling): ' . $eda_errors( 'small.jpg' ) );
$eda_check( str_contains( $eda_errors( 'fake.jpg' ), 'not a JPEG' ), 'QA gate rejects a PNG named .jpg' );
$eda_check( str_contains( $eda_errors( 'heavy.jpg' ), 'exceeds 450 KB' ), 'QA gate rejects a file over 450 KB' );
$eda_check( str_contains( $eda_errors( 'wrong.jpeg' ), 'extension' ), 'QA gate rejects a non-.jpg extension' );
$eda_check( str_contains( $eda_errors( 'missing.jpg' ), 'missing' ), 'QA gate reports a missing file' );

// B. First import (or reconcile: an already imported site creates nothing new).
$eda_before = count( $eda_tagged() );
$eda_first  = eda_images_import( $eda_manifest, $eda_dir );
$eda_ids    = $eda_tagged();
$eda_check( ! $eda_first['skipped'] && 75 - $eda_before === $eda_first['created'] && 15 === $eda_first['vehicles'], "import: {$eda_first['created']} created, {$eda_first['reused']} reused, {$eda_first['replaced']} replaced, 0 skipped, 15 vehicles" );
$eda_check( 75 === count( $eda_ids ), '75 tagged demo attachments' );

$eda_links = $eda_read_links();
$eda_slot  = array_column( $eda_manifest['slots'], null, 'filename' );
$eda_bad   = array();
foreach ( $eda_links as $eda_stock => list( $eda_hero, $eda_gallery ) ) {
	$eda_names = array_map( static fn( $id ) => get_post_meta( $id, '_eda_demo_image', true ), array_merge( array( $eda_hero ), $eda_gallery ) );
	$eda_want  = array_map( static fn( $role ) => strtolower( $eda_stock ) . "-$role.jpg", $eda_roles );
	if ( $eda_names !== $eda_want ) {
		$eda_bad[] = $eda_stock;
	}
}
$eda_check( ! $eda_bad && 15 === count( array_filter( array_column( $eda_links, 0 ) ) ), '15 vehicles: featured = hero, gallery = rear, cockpit, interior, detail (hero not repeated)' . ( $eda_bad ? ': ' . implode( ', ', $eda_bad ) : '' ) );
$eda_check( 15 === count( array_filter( $eda_links, static fn( $l ) => 4 === count( $l[1] ) ) ), '15 vehicles have exactly 4 gallery IDs' );

// F. Attachment data.
$eda_bad = array();
foreach ( $eda_ids as $eda_id ) {
	$eda_s       = $eda_slot[ get_post_meta( $eda_id, '_eda_demo_image', true ) ] ?? null;
	$eda_post    = get_post( $eda_id );
	$eda_vehicle = $eda_s ? eda_images_vehicle( $eda_s['stock_id'], $eda_dataset ) : -1;
	if ( ! $eda_s || get_post_meta( $eda_id, '_wp_attachment_image_alt', true ) !== $eda_s['alt_text'] || $eda_post->post_title !== $eda_s['alt_text'] || '' !== $eda_post->post_excerpt . $eda_post->post_content
		|| 'image/jpeg' !== $eda_post->post_mime_type || $eda_post->post_parent !== $eda_vehicle || get_post_meta( $eda_id, '_eda_demo', true ) !== $eda_dataset
		|| hash_file( 'sha256', $eda_valid['valid'][ $eda_s['filename'] ] ) !== get_post_meta( $eda_id, '_eda_demo_image_checksum', true ) ) {
		$eda_bad[] = $eda_id;
	}
}
$eda_check( ! $eda_bad, 'exact roadmap alt text and title, empty caption/description, image/jpeg, parented to its vehicle, tagged with dataset and checksum' );

// G. Derivatives: original 1536x1024, card + medium present, nothing larger than the source.
$eda_bad = array();
foreach ( $eda_ids as $eda_id ) {
	$eda_meta  = wp_get_attachment_metadata( $eda_id );
	$eda_sizes = $eda_meta['sizes'] ?? array();
	$eda_base  = dirname( get_attached_file( $eda_id ) ) . '/';
	$eda_ok    = 1536 === $eda_meta['width'] && 1024 === $eda_meta['height'] && ! isset( $eda_sizes['eda-vehicle-large'] ) && empty( $eda_meta['original_image'] )
		&& 640 === ( $eda_sizes['eda-vehicle-card']['width'] ?? 0 ) && 427 === $eda_sizes['eda-vehicle-card']['height']
		&& 960 === ( $eda_sizes['eda-vehicle-medium']['width'] ?? 0 ) && 640 === $eda_sizes['eda-vehicle-medium']['height']
		&& file_exists( $eda_base . $eda_sizes['eda-vehicle-card']['file'] ) && file_exists( $eda_base . $eda_sizes['eda-vehicle-medium']['file'] );
	foreach ( $eda_sizes as $eda_size ) {
		$eda_ok = $eda_ok && $eda_size['width'] <= 1536 && $eda_size['height'] <= 1024;
	}
	if ( ! $eda_ok ) {
		$eda_bad[] = $eda_id;
	}
}
$eda_check( ! $eda_bad, 'all 75: original 1536x1024, card 640x427 + medium 960x640 on disk, no "large" copy, no derivative above the source' );
$eda_large = wp_get_attachment_image_src( $eda_links['AUR-26001'][0], 'eda-vehicle-large' );
$eda_check( 1536 === $eda_large[1] && str_ends_with( $eda_large[0], '.jpg' ) && ! preg_match( '/-\d+x\d+\.jpg$/', $eda_large[0] ), 'eda-vehicle-large serves the 1536 original' );
$eda_srcset = (string) wp_get_attachment_image_srcset( $eda_links['AUR-26001'][0], 'eda-vehicle-medium' );
$eda_check( str_contains( $eda_srcset, ' 640w' ) && str_contains( $eda_srcset, ' 960w' ) && str_contains( $eda_srcset, ' 1536w' ) && ! preg_match( '/ (1[6-9]\d\d|[2-9]\d{3})w/', $eda_srcset ), 'card srcset offers 640/960/1536, nothing wider' );

// Vehicle page gallery (server markup; the slideshow itself is progressive enhancement).
$eda_page = wp_remote_retrieve_body( wp_remote_get( get_permalink( eda_images_vehicle( 'AUR-26001', $eda_dataset ) ), array( 'timeout' => 30 ) ) );
$eda_gal  = substr( $eda_page, (int) strpos( $eda_page, '<div class="vehicle-gallery" data-gallery' ), (int) strpos( $eda_page, 'vehicle-layout__summary' ) - (int) strpos( $eda_page, '<div class="vehicle-gallery" data-gallery' ) );
preg_match_all( '/data-gallery-slide( hidden)?>\s*<img [^>]*src="[^"]*\/(aur-26001-[a-z]+)/', $eda_gal, $eda_slides );
$eda_check( array( 'aur-26001-hero', 'aur-26001-rear', 'aur-26001-cockpit', 'aur-26001-interior', 'aur-26001-detail' ) === $eda_slides[2] && array( '', ' hidden', ' hidden', ' hidden', ' hidden' ) === $eda_slides[1], 'gallery: 5 slides in order hero, rear, cockpit, interior, detail; only the first visible without JS' );
$eda_check( 1 === substr_count( $eda_gal, 'fetchpriority="high"' ) && 4 === preg_match_all( '/data-gallery-slide hidden>\s*<img [^>]*loading="lazy"/', $eda_gal ), 'gallery: first slide eager/high priority, the others lazy (fetched only when shown)' );
$eda_check( 2 === preg_match_all( '/<button type="button" class="vehicle-gallery__nav [^"]+" data-gallery-(prev|next) hidden aria-label="(Previous|Next) vehicle image">/', $eda_gal ) && str_contains( $eda_gal, 'aria-live="polite" data-gallery-status' ), 'gallery: real prev/next buttons with labels, hidden until JS runs; polite status region' );
$eda_check( 5 === preg_match_all( '/<a class="vehicle-gallery__thumb" href="[^"]+\/aur-26001-[a-z]+\.jpg" data-gallery-thumb/', $eda_gal ) && 1 === substr_count( $eda_gal, 'aria-current="true"' ), 'gallery: 5 thumbnail links to the full images (work without JS), the first marked aria-current' );
$eda_js = (string) file_get_contents( get_template_directory() . '/assets/js/vehicle-gallery.js' );
$eda_check( str_contains( $eda_page, 'vehicle-gallery.js' ) && ! str_contains( wp_remote_retrieve_body( wp_remote_get( home_url( '/' ), array( 'timeout' => 30 ) ) ), 'vehicle-gallery.js' ) && ! preg_match( '/jQuery|\$\(/', $eda_js ) && str_contains( $eda_js, "'ArrowLeft'" ) && str_contains( $eda_js, 'pointerup' ), 'gallery script: vanilla, vehicle pages only, keyboard and swipe' );

// C. Idempotency.
$eda_rerun = eda_images_import( $eda_manifest, $eda_dir );
$eda_after = $eda_tagged();
$eda_names = array_map( static fn( $id ) => get_post_meta( $id, '_eda_demo_image', true ), $eda_after );
$eda_check( 0 === $eda_rerun['created'] + $eda_rerun['replaced'] && 75 === $eda_rerun['reused'], 'rerun: 0 created, 0 replaced, 75 reused' );
$eda_check( 75 === count( $eda_after ) && 75 === count( array_unique( $eda_names ) ) && array() === array_diff( $eda_after, $eda_ids ), 'still 75 attachments, same IDs, no duplicate _eda_demo_image values' );
$eda_check( $eda_links === $eda_read_links(), 'featured images and galleries unchanged by the rerun' );

// D. Checksum replacement: a changed source updates the same attachment, no orphan.
$eda_target = $eda_links['AUR-26001'][0];
$eda_old    = get_attached_file( $eda_target );
wp_mkdir_p( "$eda_tmp/src/aur-26001" );
copy( $eda_valid['valid']['aur-26002-hero.jpg'], "$eda_tmp/src/aur-26001/aur-26001-hero.jpg" ); // Valid, different bytes.
$eda_swap = eda_images_import( $eda_manifest, "$eda_tmp/src" );
$eda_check( 1 === $eda_swap['replaced'] && 0 === $eda_swap['created'] && 75 === count( $eda_tagged() ) && $eda_links === $eda_read_links(), 'changed source: replaced in place (same ID, 75 attachments, relationships kept)' );
$eda_check( hash_file( 'sha256', get_attached_file( $eda_target ) ) === hash_file( 'sha256', "$eda_tmp/src/aur-26001/aur-26001-hero.jpg" ) && get_attached_file( $eda_target ) === $eda_old, 'file replaced at the same path, checksum updated' );
$eda_back = eda_images_import( $eda_manifest, $eda_dir );
$eda_check( 1 === $eda_back['replaced'] && hash_file( 'sha256', get_attached_file( $eda_target ) ) === hash_file( 'sha256', $eda_valid['valid']['aur-26001-hero.jpg'] ), 'approved file restored by checksum' );

// Conflict: a real vehicle sharing a stock ID blocks image assignment.
$eda_real = wp_insert_post(
	array(
		'post_type'   => 'vehicle',
		'post_status' => 'draft',
		'post_title'  => 'Real dealership vehicle',
	)
);
update_post_meta( $eda_real, '_eda_stock_id', 'AUR-26001' );
$eda_check( 0 === eda_images_vehicle( 'AUR-26001', $eda_dataset ) && 0 !== eda_images_vehicle( 'AUR-26002', $eda_dataset ), 'a real vehicle with the same stock ID blocks import for that stock ID only' );
wp_delete_post( $eda_real, true );

// E. Cleanup: only tagged demo images go; a non-demo upload with a demo-like name stays.
copy( $eda_valid['valid']['aur-26003-hero.jpg'], "$eda_tmp/aur-26001-hero.jpg" );
$eda_client = media_handle_sideload(
	array(
		'name'     => 'aur-26001-hero.jpg',
		'tmp_name' => "$eda_tmp/aur-26001-hero.jpg",
	),
	0
);
$eda_half   = media_handle_sideload(
	array(
		'name'     => 'aur-26002-rear.jpg',
		'tmp_name' => eda_images_temp_copy( $eda_valid['valid']['aur-26003-rear.jpg'] ),
	),
	0
);
update_post_meta( $eda_half, '_eda_demo_image', 'aur-26002-rear.jpg' ); // Half-tagged: no dataset marker.
$eda_vehicle = eda_images_vehicle( 'AUR-26005', $eda_dataset );
update_post_meta( $eda_vehicle, '_eda_gallery', array_merge( (array) get_post_meta( $eda_vehicle, '_eda_gallery', true ), array( $eda_client ) ) );
$eda_files = array();
foreach ( $eda_ids as $eda_id ) {
	$eda_files[] = get_attached_file( $eda_id );
	foreach ( wp_get_attachment_metadata( $eda_id )['sizes'] as $eda_size ) {
		$eda_files[] = dirname( get_attached_file( $eda_id ) ) . '/' . $eda_size['file'];
	}
}
$eda_removed     = eda_demo_images_cleanup( $eda_dataset );
$eda_links_after = $eda_read_links();
$eda_check( 75 === $eda_removed && array( $eda_half ) === $eda_tagged(), 'cleanup removes exactly the 75 tagged demo images' );
$eda_check( get_post( $eda_client ) && file_exists( get_attached_file( $eda_client ) ) && get_post( $eda_half ), 'untagged and half-tagged look-alike uploads survive with their files' );
$eda_check( ! array_filter( array_column( $eda_links_after, 0 ) ), 'featured-image references cleared on all 15 vehicles' );
$eda_check( array( $eda_client ) === $eda_links_after['AUR-26005'][1] && 1 === count( array_filter( array_column( $eda_links_after, 1 ) ) ), 'gallery references cleared; the non-demo image in a gallery is kept' );
$eda_check( ! array_filter( $eda_files, 'file_exists' ), 'demo originals and every derivative removed from uploads (' . count( $eda_files ) . ' files)' );
eda_demo_delete_attachment( $eda_client );
eda_demo_delete_attachment( $eda_half );
delete_post_meta( $eda_vehicle, '_eda_gallery' );

// Restore the imported demo images.
$eda_restore = eda_images_import( $eda_manifest, $eda_dir );
$eda_check( 75 === $eda_restore['created'] && 75 === count( $eda_tagged() ) && 15 === $eda_restore['vehicles'], 'demo images restored (75 created, 15 vehicles)' );

array_map( 'unlink', array_filter( (array) glob( "$eda_tmp/{,src/aur-26001/}*", GLOB_BRACE ), 'is_file' ) );
@rmdir( "$eda_tmp/src/aur-26001" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
@rmdir( "$eda_tmp/src" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
@rmdir( $eda_tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

echo PHP_EOL . ( $eda_failures ? "$eda_failures FAILED" : 'ALL PASSED' ) . PHP_EOL;
if ( $eda_failures ) {
	exit( 1 );
}
