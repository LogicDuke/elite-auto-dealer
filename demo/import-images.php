<?php
/**
 * Demo vehicle image importer (Aurelis Motors). Reads demo/image-roadmap.json and the approved
 * JPEGs in demo/images/{stock-id}/ (local, Git-ignored). Offline only: no remote fetches.
 *
 * Run from the WordPress root with the theme active, after demo/seed.php:
 *
 *   wp eval-file wp-content/themes/elite-auto-dealer/demo/import-images.php            # import / update
 *   wp eval-file wp-content/themes/elite-auto-dealer/demo/import-images.php validate   # QA gate only
 *   wp eval-file wp-content/themes/elite-auto-dealer/demo/import-images.php cleanup    # remove demo images
 *
 * Every attachment is tagged `_eda_demo` = dataset id and `_eda_demo_image` = canonical filename,
 * with `_eda_demo_image_checksum` (SHA-256 of the source). Re-running reuses tagged attachments,
 * replaces one in place when its source checksum changed, and never touches untagged media.
 * See docs/VEHICLE-IMAGE-ROADMAP.md (section 7).
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'EDA_DEMO_LIBRARY' ) ) {
	define( 'EDA_DEMO_LIBRARY', true );
}
require_once __DIR__ . '/seed.php';

/**
 * Load the image roadmap manifest.
 *
 * @return array
 */
function eda_images_manifest() {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
	$data = json_decode( (string) file_get_contents( __DIR__ . '/image-roadmap.json' ), true );
	return is_array( $data ) ? $data : array();
}

/**
 * Pre-import QA gate for one source file. Never modifies the file.
 *
 * @param string $file     Absolute path.
 * @param array  $contract width, height, extension, weight_preferred_max_kb (e.g. manifest `image_contract`).
 * @return string[] Errors (empty = passes).
 */
function eda_images_check_file( $file, array $contract ) {
	if ( ! is_file( $file ) || ! is_readable( $file ) ) {
		return array( 'missing or unreadable' );
	}
	$errors = array();
	if ( strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) !== $contract['extension'] ) {
		$errors[] = 'extension is not .' . $contract['extension'];
	}
	$max = (int) $contract['weight_preferred_max_kb'] * 1024;
	if ( filesize( $file ) > $max ) {
		$errors[] = sprintf( 'file size %d KB exceeds %d KB', ceil( filesize( $file ) / 1024 ), $contract['weight_preferred_max_kb'] );
	}
	$info = getimagesize( $file, $segments );
	if ( ! $info || IMAGETYPE_JPEG !== $info[2] || 'image/jpeg' !== $info['mime'] ) {
		return array_merge( $errors, array( 'not a JPEG' ) );
	}
	if ( (int) $contract['width'] !== $info[0] || (int) $contract['height'] !== $info[1] ) {
		$errors[] = "dimensions {$info[0]}x{$info[1]}, expected exactly {$contract['width']}x{$contract['height']}; never upscaled or resized";
	}
	// sRGB: an embedded ICC profile must be an RGB profile named sRGB; without one, a 3-channel
	// JPEG is treated as sRGB (the web default). CMYK/greyscale always fails.
	$icc = isset( $segments['APP2'] ) && str_starts_with( $segments['APP2'], 'ICC_PROFILE' ) ? substr( $segments['APP2'], 14 ) : '';
	if ( 3 !== ( $info['channels'] ?? 3 ) || ( $icc && ( 'RGB ' !== substr( $icc, 16, 4 ) || ! ( str_contains( $icc, 'sRGB' ) || str_contains( $icc, "s\0R\0G\0B" ) ) ) ) ) {
		$errors[] = 'colour space is not sRGB';
	}
	$editor = wp_get_image_editor( $file ); // Full decode: catches truncated/corrupt data.
	if ( is_wp_error( $editor ) ) {
		$errors[] = 'does not decode: ' . $editor->get_error_message();
	}
	return $errors;
}

/**
 * Run the QA gate over every manifest slot.
 *
 * @param array  $manifest Manifest.
 * @param string $dir      Source root containing {stock-id}/{filename}.
 * @return array{valid: array<string, string>, missing: string[], failed: array<string, string[]>}
 */
function eda_images_validate( array $manifest, $dir ) {
	$result = array(
		'valid'   => array(),
		'missing' => array(),
		'failed'  => array(),
	);
	foreach ( $manifest['slots'] as $slot ) {
		$file = $dir . '/' . strtolower( $slot['stock_id'] ) . '/' . $slot['filename'];
		if ( ! file_exists( $file ) ) {
			$result['missing'][] = $slot['filename'];
			continue;
		}
		$errors = eda_images_check_file( $file, $manifest['image_contract'] );
		if ( $errors ) {
			$result['failed'][ $slot['filename'] ] = $errors;
		} else {
			$result['valid'][ $slot['filename'] ] = $file;
		}
	}
	return $result;
}

/**
 * Tagged demo attachments for one canonical filename (identity is meta, never title or file name).
 *
 * @param string $filename Canonical filename.
 * @param string $dataset  Dataset id.
 * @param string $key      Identity meta key: `_eda_demo_image` (vehicles) or `_eda_demo_site_image`.
 * @return int[]
 */
function eda_images_find( $filename, $dataset, $key = '_eda_demo_image' ) {
	return get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- CLI importer.
				array(
					'key'   => $key,
					'value' => $filename,
				),
				array(
					'key'   => '_eda_demo',
					'value' => $dataset,
				),
			),
		)
	);
}

/**
 * The single demo vehicle for a stock ID, or 0 when a real vehicle (or a duplicate) holds it.
 *
 * @param string $stock_id Stock ID.
 * @param string $dataset  Dataset id.
 * @return int
 */
function eda_images_vehicle( $stock_id, $dataset ) {
	$found = eda_demo_find( $stock_id );
	$demo  = array_filter( $found, static fn( $id ) => get_post_meta( $id, '_eda_demo', true ) === $dataset );
	return 1 === count( $found ) && 1 === count( $demo ) ? (int) reset( $demo ) : 0;
}

/**
 * Copy a source into a temporary file WordPress can move into uploads (the source stays intact).
 *
 * @param string $file Source path.
 * @return string Temporary path.
 */
function eda_images_temp_copy( $file ) {
	$tmp = wp_tempnam( basename( $file ) );
	copy( $file, $tmp );
	return $tmp;
}

/**
 * Create, reuse or replace-in-place the one tagged attachment for a validated source file.
 *
 * Identity is `$key` = filename plus `_eda_demo` = dataset; `{$key}_checksum` holds the source
 * SHA-256. A changed checksum swaps the file and derivatives under the same ID and path, so every
 * reference to the attachment survives. Duplicated identities are left untouched.
 *
 * @param string $file     Validated source path.
 * @param string $filename Canonical filename.
 * @param string $key      Identity meta key.
 * @param string $dataset  Dataset id.
 * @param array  $fields   post_title / post_parent (caption and description are always empty).
 * @param string $alt      Alt text.
 * @return array{0: int, 1: string}|string [ID, created|replaced|reused], or the reason it was skipped.
 */
function eda_images_upsert( $file, $filename, $key, $dataset, array $fields, $alt ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$checksum = hash_file( 'sha256', $file );
	$found    = eda_images_find( $filename, $dataset, $key );
	$id       = (int) reset( $found );
	$fields   = array(
		'post_excerpt' => '',
		'post_content' => '',
	) + $fields;

	if ( count( $found ) > 1 ) {
		return count( $found ) . ' tagged attachments share this identity; left untouched, resolve manually';
	}

	if ( ! $id ) {
		$id = media_handle_sideload(
			array(
				'name'     => $filename,
				'tmp_name' => eda_images_temp_copy( $file ),
			),
			$fields['post_parent'] ?? 0,
			$fields['post_title'],
			$fields
		);
		if ( is_wp_error( $id ) ) {
			return $id->get_error_message();
		}
		$action = 'created';
	} elseif ( get_post_meta( $id, $key . '_checksum', true ) !== $checksum ) {
		// Changed source: swap the file and derivatives in place, keeping the attachment ID.
		$target = get_attached_file( $id );
		$backup = get_post_meta( $id, '_wp_attachment_backup_sizes', true ); // '' when none: core needs an array.
		wp_delete_attachment_files( $id, wp_get_attachment_metadata( $id ), is_array( $backup ) ? $backup : array(), $target );
		copy( $file, $target );
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $target ) );
		$action = 'replaced';
	} else {
		$action = 'reused';
	}

	wp_update_post( array( 'ID' => $id ) + $fields );
	update_post_meta( $id, '_eda_demo', $dataset );
	update_post_meta( $id, $key, $filename );
	update_post_meta( $id, $key . '_checksum', $checksum );
	update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	return array( $id, $action );
}

/**
 * Import or update all valid demo images, then assign featured image and gallery per vehicle.
 *
 * @param array  $manifest Manifest.
 * @param string $dir      Source root.
 * @return array{created: int, replaced: int, reused: int, skipped: array<string, string>, vehicles: int}
 */
function eda_images_import( array $manifest, $dir ) {
	$dataset = $manifest['dataset'];
	$check   = eda_images_validate( $manifest, $dir );
	$result  = array(
		'created'  => 0,
		'replaced' => 0,
		'reused'   => 0,
		'skipped'  => array_map( static fn( $errors ) => implode( '; ', $errors ), $check['failed'] ),
		'vehicles' => 0,
	);

	foreach ( $manifest['slots'] as $slot ) {
		$file = $check['valid'][ $slot['filename'] ] ?? '';
		if ( ! $file ) {
			continue;
		}
		$vehicle = eda_images_vehicle( $slot['stock_id'], $dataset );
		if ( ! $vehicle ) {
			$result['skipped'][ $slot['filename'] ] = 'no single demo vehicle for ' . $slot['stock_id'] . ' (missing, real or duplicated)';
			continue;
		}
		$done = eda_images_upsert(
			$file,
			$slot['filename'],
			'_eda_demo_image',
			$dataset,
			array(
				'post_title'  => $slot['alt_text'],
				'post_parent' => $vehicle,
			),
			$slot['alt_text']
		);
		if ( is_string( $done ) ) {
			$result['skipped'][ $slot['filename'] ] = $done;
			continue;
		}
		++$result[ $done[1] ];
	}

	// Relationships from the tagged attachments, so a partial run never drops existing ones.
	$by_vehicle = array();
	foreach ( $manifest['slots'] as $slot ) {
		$by_vehicle[ $slot['stock_id'] ][ $slot['role'] ] = (int) current( eda_images_find( $slot['filename'], $dataset ) );
	}
	foreach ( $by_vehicle as $stock_id => $roles ) {
		$vehicle = eda_images_vehicle( $stock_id, $dataset );
		if ( ! $vehicle || ! array_filter( $roles ) ) {
			continue;
		}
		if ( $roles['hero'] ) {
			set_post_thumbnail( $vehicle, $roles['hero'] );
		}
		$gallery = array( $roles['rear'], $roles['cockpit'], $roles['interior'], $roles['detail'] );
		update_post_meta( $vehicle, '_eda_gallery', eda_sanitize_vehicle_meta_value( $gallery, eda_vehicle_meta_fields()['gallery'] ) );
		++$result['vehicles'];
	}

	return $result;
}

// Library mode for tests: define EDA_IMAGES_LIBRARY before including this file.
if ( defined( 'EDA_IMAGES_LIBRARY' ) ) {
	return;
}

if ( ! defined( 'WP_CLI' ) || ! function_exists( 'eda_vehicle_meta_fields' ) ) {
	exit( "Run with: wp eval-file demo/import-images.php (theme must be active)\n" );
}

$eda_mode     = $args[0] ?? 'import';
$eda_manifest = eda_images_manifest();
$eda_dir      = __DIR__ . '/images';

if ( 'cleanup' === $eda_mode ) {
	$eda_removed = eda_demo_images_cleanup( $eda_manifest['dataset'] );
	WP_CLI::success( "Removed $eda_removed tagged demo image attachments; featured-image and gallery references cleared." );
} elseif ( 'validate' === $eda_mode ) {
	$eda_check = eda_images_validate( $eda_manifest, $eda_dir );
	foreach ( $eda_check['failed'] as $eda_file => $eda_errors ) {
		WP_CLI::warning( "$eda_file: " . implode( '; ', $eda_errors ) );
	}
	$eda_summary = count( $eda_check['valid'] ) . ' valid, ' . count( $eda_check['failed'] ) . ' failed, ' . count( $eda_check['missing'] ) . ' missing of ' . count( $eda_manifest['slots'] );
	$eda_check['failed'] || $eda_check['missing'] ? WP_CLI::error( $eda_summary ) : WP_CLI::success( $eda_summary );
} elseif ( 'import' === $eda_mode ) {
	$eda_result = eda_images_import( $eda_manifest, $eda_dir );
	foreach ( $eda_result['skipped'] as $eda_file => $eda_reason ) {
		WP_CLI::warning( "$eda_file skipped: $eda_reason" );
	}
	WP_CLI::success( "Demo images: {$eda_result['created']} created, {$eda_result['replaced']} replaced, {$eda_result['reused']} reused, " . count( $eda_result['skipped'] ) . " skipped; {$eda_result['vehicles']} vehicles assigned." );
} else {
	WP_CLI::error( "Unknown mode '$eda_mode'. Use: (none) | validate | cleanup" );
}
