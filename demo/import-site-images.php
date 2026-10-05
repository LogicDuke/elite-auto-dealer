<?php
/**
 * Demo website image importer (Aurelis Motors): homepage hero (desktop + mobile) and the inner-page
 * header images (Vehicles, About, Finance, Contact).
 * Reads the approved JPEGs in demo/images/site/ (local, Git-ignored). Offline only.
 *
 * Run from the WordPress root with the theme active:
 *
 *   wp eval-file wp-content/themes/elite-auto-dealer/demo/import-site-images.php            # import / update
 *   wp eval-file wp-content/themes/elite-auto-dealer/demo/import-site-images.php validate   # QA gate only
 *   wp eval-file wp-content/themes/elite-auto-dealer/demo/import-site-images.php cleanup    # remove them
 *
 * Attachments are tagged `_eda_demo` = dataset id and `_eda_demo_site_image` = filename, with
 * `_eda_demo_site_image_checksum`. Assignment uses normal WordPress storage: theme mods
 * `eda_hero_image` / `eda_hero_image_mobile` / `eda_inventory_image` and the About / Finance / Contact
 * page featured images. A slot the
 * dealer has filled with their own (untagged) image is never overwritten.
 * See docs/WEBSITE-IMAGE-ROADMAP.md.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'EDA_IMAGES_LIBRARY' ) ) {
	define( 'EDA_IMAGES_LIBRARY', true );
}
require_once __DIR__ . '/import-images.php'; // QA gate, upsert; loads demo/seed.php (cleanup).

/**
 * The approved website-image contract (docs/WEBSITE-IMAGE-ROADMAP.md §1).
 * `target` is `mod:{theme mod}`, `page:{page slug}` (featured image = body image) or
 * `header:{page slug}` (page header image, meta `_eda_header_image`).
 *
 * @return array<string, array{width: int, height: int, extension: string, weight_preferred_max_kb: int, target: string, alt: string}>
 */
function eda_site_images() {
	$file = static fn( $width, $height, $max, $target, $alt ) => array(
		'width'                   => $width,
		'height'                  => $height,
		'extension'               => 'jpg',
		'weight_preferred_max_kb' => $max,
		'target'                  => $target,
		'alt'                     => $alt,
	);
	return array(
		// Decorative: the H1 carries the meaning, so the hero alt stays empty.
		'aurelis-home-hero-desktop.jpg'    => $file( 2560, 1138, 450, 'mod:eda_hero_image', '' ),
		'aurelis-home-hero-mobile.jpg'     => $file( 1200, 1800, 300, 'mod:eda_hero_image_mobile', '' ),
		// Inner pages: Vehicles hero, then body images (featured images) and header heroes per page.
		// Header images are decorative (alt=""): the H1 carries the meaning, the body image the description.
		'aurelis-vehicles-forecourt.jpg'   => $file( 1536, 1024, 450, 'mod:eda_inventory_image', 'Aurelis Motors premium vehicle forecourt' ),
		'aurelis-about-showroom.jpg'       => $file( 1536, 1024, 450, 'page:about', 'Aurelis Motors showroom' ),
		'aurelis-finance-consultation.jpg' => $file( 1536, 1024, 450, 'page:finance', 'Aurelis Motors consultation area' ),
		'aurelis-contact-entrance.jpg'     => $file( 1200, 1500, 450, 'page:contact', 'Aurelis Motors entrance' ),
		'aurelis-about-header.jpg'         => $file( 1536, 1024, 450, 'header:about', '' ),
		'aurelis-finance-header.jpg'       => $file( 1536, 1024, 450, 'header:finance', '' ),
		'aurelis-contact-header.jpg'       => $file( 1536, 1024, 450, 'header:contact', '' ),
	);
}

/**
 * QA gate for every website image.
 *
 * @param string $dir Source folder.
 * @return array{valid: array<string, string>, missing: string[], failed: array<string, string[]>}
 */
function eda_site_images_validate( $dir ) {
	$result = array(
		'valid'   => array(),
		'missing' => array(),
		'failed'  => array(),
	);
	foreach ( eda_site_images() as $name => $contract ) {
		$file = "$dir/$name";
		if ( ! file_exists( $file ) ) {
			$result['missing'][] = $name;
			continue;
		}
		$errors = eda_images_check_file( $file, $contract );
		if ( $errors ) {
			$result['failed'][ $name ] = $errors;
		} else {
			$result['valid'][ $name ] = $file;
		}
	}
	return $result;
}

/**
 * Current attachment ID in a slot, and whether the importer may (re)assign it: only when the slot is
 * empty, points to nothing, or already holds a tagged demo website image.
 *
 * @param string $target `mod:…` or `page:…`.
 * @return array{0: int, 1: bool, 2: int} [current ID, assignable, page ID]
 */
function eda_site_images_slot( $target ) {
	list( $type, $name ) = explode( ':', $target, 2 );
	$page                = 'mod' === $type ? null : get_page_by_path( $name );
	if ( 'mod' !== $type && ! $page ) {
		return array( 0, false, 0 );
	}
	if ( 'mod' === $type ) {
		$current = (int) get_theme_mod( $name );
	} elseif ( 'header' === $type ) {
		$current = (int) get_post_meta( $page->ID, '_eda_header_image', true );
	} else {
		$current = (int) get_post_thumbnail_id( $page );
	}
	$free = ! $current || ! wp_attachment_is_image( $current ) || get_post_meta( $current, '_eda_demo_site_image', true );
	return array( $current, (bool) $free, $page ? $page->ID : 0 );
}

/**
 * Import or update the website images, then assign them.
 *
 * @param string $dir     Source folder.
 * @param string $dataset Dataset id.
 * @return array{created: int, replaced: int, reused: int, assigned: int, skipped: array<string, string>}
 */
function eda_site_images_import( $dir, $dataset ) {
	$check  = eda_site_images_validate( $dir );
	$result = array(
		'created'  => 0,
		'replaced' => 0,
		'reused'   => 0,
		'assigned' => 0,
		'skipped'  => array_map( static fn( $errors ) => implode( '; ', $errors ), $check['failed'] ),
	);

	foreach ( eda_site_images() as $name => $contract ) {
		$file = $check['valid'][ $name ] ?? '';
		if ( ! $file ) {
			continue;
		}
		list( , , $page ) = eda_site_images_slot( $contract['target'] );
		$done             = eda_images_upsert(
			$file,
			$name,
			'_eda_demo_site_image',
			$dataset,
			array(
				'post_title'  => $contract['alt'] ? $contract['alt'] : ucwords( str_replace( '-', ' ', pathinfo( $name, PATHINFO_FILENAME ) ) ),
				'post_parent' => $page,
			),
			$contract['alt']
		);
		if ( is_string( $done ) ) {
			$result['skipped'][ $name ] = $done;
			continue;
		}
		++$result[ $done[1] ];
	}

	// Assignment from the tagged attachments, so a partial run keeps existing assignments.
	foreach ( eda_site_images() as $name => $contract ) {
		$id                            = (int) current( eda_images_find( $name, $dataset, '_eda_demo_site_image' ) );
		list( $current, $free, $page ) = eda_site_images_slot( $contract['target'] );
		if ( ! $id ) {
			continue;
		}
		if ( ! $free ) {
			$result['skipped'][ $name ] = "{$contract['target']} already holds a non-demo image ($current); left unchanged";
			continue;
		}
		if ( str_starts_with( $contract['target'], 'header:' ) ) {
			update_post_meta( $page, '_eda_header_image', $id );
		} elseif ( $page ) {
			set_post_thumbnail( $page, $id );
		} else {
			set_theme_mod( substr( $contract['target'], 4 ), $id );
		}
		++$result['assigned'];
	}

	return $result;
}

// Library mode for tests: define EDA_SITE_IMAGES_LIBRARY before including this file.
if ( defined( 'EDA_SITE_IMAGES_LIBRARY' ) ) {
	return;
}

if ( ! defined( 'WP_CLI' ) || ! function_exists( 'eda_vehicle_meta_fields' ) ) {
	exit( "Run with: wp eval-file demo/import-site-images.php (theme must be active)\n" );
}

$eda_mode    = $args[0] ?? 'import';
$eda_dataset = eda_images_manifest()['dataset'];
$eda_dir     = __DIR__ . '/images/site';

if ( 'cleanup' === $eda_mode ) {
	$eda_removed = eda_demo_images_cleanup( $eda_dataset, '_eda_demo_site_image' );
	WP_CLI::success( "Removed $eda_removed tagged demo website images; hero settings and featured images cleared." );
} elseif ( 'validate' === $eda_mode ) {
	$eda_check = eda_site_images_validate( $eda_dir );
	foreach ( $eda_check['failed'] as $eda_file => $eda_errors ) {
		WP_CLI::warning( "$eda_file: " . implode( '; ', $eda_errors ) );
	}
	$eda_summary = count( $eda_check['valid'] ) . ' valid, ' . count( $eda_check['failed'] ) . ' failed, ' . count( $eda_check['missing'] ) . ' missing of ' . count( eda_site_images() );
	$eda_check['failed'] || $eda_check['missing'] ? WP_CLI::error( $eda_summary ) : WP_CLI::success( $eda_summary );
} elseif ( 'import' === $eda_mode ) {
	$eda_result = eda_site_images_import( $eda_dir, $eda_dataset );
	foreach ( $eda_result['skipped'] as $eda_file => $eda_reason ) {
		WP_CLI::warning( "$eda_file skipped: $eda_reason" );
	}
	WP_CLI::success( "Website images: {$eda_result['created']} created, {$eda_result['replaced']} replaced, {$eda_result['reused']} reused, " . count( $eda_result['skipped'] ) . " skipped; {$eda_result['assigned']} assigned." );
} else {
	WP_CLI::error( "Unknown mode '$eda_mode'. Use: (none) | validate | cleanup" );
}
