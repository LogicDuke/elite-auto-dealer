<?php
/**
 * Website image test: homepage hero, inner-page header heroes (Vehicles, About, Finance, Contact)
 * and the body images of About, Finance and Contact. Run with the approved JPEGs in
 * demo/images/site/ and the demo seeded. Header images not yet produced are reported as PENDING
 * (they may be missing; a present file must pass):
 *
 * Commands:
 *   wp eval-file wp-content/themes/elite-auto-dealer/tests/site-image-test.php
 *
 * Covers the QA gate, import, idempotency, checksum replacement, assignment, cleanup safety and the
 * rendered output. Leaves the website images imported and assigned.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput -- CLI test script.

define( 'EDA_SITE_IMAGES_LIBRARY', true );
require_once get_template_directory() . '/demo/import-site-images.php';

$eda_failures = 0;
$eda_check    = static function ( $condition, $message ) use ( &$eda_failures ) {
	echo ( $condition ? 'PASS ' : 'FAIL ' ) . $message . PHP_EOL;
	$eda_failures += $condition ? 0 : 1;
};

$eda_dataset = eda_images_manifest()['dataset'];
$eda_dir     = get_template_directory() . '/demo/images/site';
$eda_tmp     = get_temp_dir() . 'eda-site-test-' . wp_generate_password( 6, false );
$eda_files   = eda_site_images();
$eda_tagged  = static fn( $key ) => get_posts(
	array(
		'post_type'      => 'attachment',
		'post_status'    => 'any',
		'fields'         => 'ids',
		'posts_per_page' => -1,
		'meta_key'       => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	)
);
$eda_slots   = static fn() => array(
	'hero'      => (int) get_theme_mod( 'eda_hero_image' ),
	'mobile'    => (int) get_theme_mod( 'eda_hero_image_mobile' ),
	'inventory' => (int) get_theme_mod( 'eda_inventory_image' ),
	'about'     => (int) get_post_thumbnail_id( get_page_by_path( 'about' ) ),
	'finance'   => (int) get_post_thumbnail_id( get_page_by_path( 'finance' ) ),
	'contact'   => (int) get_post_thumbnail_id( get_page_by_path( 'contact' ) ),
	'h_about'   => (int) get_post_meta( get_page_by_path( 'about' )->ID, '_eda_header_image', true ),
	'h_finance' => (int) get_post_meta( get_page_by_path( 'finance' )->ID, '_eda_header_image', true ),
	'h_contact' => (int) get_post_meta( get_page_by_path( 'contact' )->ID, '_eda_header_image', true ),
);
$eda_names   = static fn( $slots ) => array_map( static fn( $id ) => (string) get_post_meta( $id, '_eda_demo_site_image', true ), $slots );
$eda_fetch   = static fn( $path ) => wp_remote_retrieve_body( wp_remote_get( home_url( $path ), array( 'timeout' => 30 ) ) );
$eda_vehicle = count( $eda_tagged( '_eda_demo_image' ) );
wp_mkdir_p( $eda_tmp );

// A. Source QA.
$eda_valid = eda_site_images_validate( $eda_dir );
$eda_n     = count( $eda_valid['valid'] );
$eda_check( 9 === count( $eda_files ) && ! $eda_valid['failed'] && ! array_diff( $eda_valid['missing'], array( 'aurelis-about-header.jpg', 'aurelis-finance-header.jpg', 'aurelis-contact-header.jpg' ) ) && $eda_n >= 6, "every present website image passes the QA gate ($eda_n of 9 present; dimensions, JPEG, sRGB, decode, weight)" );
foreach ( $eda_valid['missing'] as $eda_file ) {
	echo "PENDING $eda_file: not produced yet (header hero falls back to the text header)" . PHP_EOL;
}
$eda_check( array( 'aurelis-home-hero-desktop.jpg', 'aurelis-home-hero-mobile.jpg', 'aurelis-vehicles-forecourt.jpg', 'aurelis-about-showroom.jpg', 'aurelis-finance-consultation.jpg', 'aurelis-contact-entrance.jpg', 'aurelis-about-header.jpg', 'aurelis-finance-header.jpg', 'aurelis-contact-header.jpg' ) === array_keys( $eda_files ), 'canonical filenames (9 slots)' );
$eda_check( array( '2560x1138', '1200x1800', '1536x1024', '1536x1024', '1536x1024', '1200x1500', '1536x1024', '1536x1024', '1536x1024' ) === array_map( static fn( $c ) => "{$c['width']}x{$c['height']}", array_values( $eda_files ) ), 'contract dimensions match the roadmap' );
$eda_check( ! array_diff( array_map( 'basename', (array) glob( "$eda_dir/*" ) ), array_keys( $eda_files ) ), 'demo/images/site holds only contract files' );
copy( get_template_directory() . '/demo/images/aur-26001/aur-26001-hero.jpg', "$eda_tmp/aurelis-home-hero-desktop.jpg" ); // Valid JPEG, wrong size.
$eda_check( str_contains( implode( ';', eda_images_check_file( "$eda_tmp/aurelis-home-hero-desktop.jpg", $eda_files['aurelis-home-hero-desktop.jpg'] ) ), 'expected exactly 2560x1138' ), 'QA gate rejects a wrong-size file (never resized)' );
$eda_check( 300 === $eda_files['aurelis-home-hero-mobile.jpg']['weight_preferred_max_kb'] && 8 === count( array_filter( $eda_files, static fn( $c ) => 450 === $c['weight_preferred_max_kb'] ) ), 'weight maxima: mobile hero 300 KB, the others 450 KB' );

// B. Import (an already imported site creates nothing new).
$eda_before = count( $eda_tagged( '_eda_demo_site_image' ) );
$eda_first  = eda_site_images_import( $eda_dir, $eda_dataset );
$eda_ids    = $eda_tagged( '_eda_demo_site_image' );
$eda_check( ! $eda_first['skipped'] && $eda_n - $eda_before === $eda_first['created'] && $eda_n === $eda_first['assigned'], "import: {$eda_first['created']} created, {$eda_first['reused']} reused, $eda_n assigned" );
$eda_names_all = array_map( static fn( $id ) => get_post_meta( $id, '_eda_demo_site_image', true ), $eda_ids );
$eda_check( count( $eda_ids ) === $eda_n && count( array_unique( $eda_names_all ) ) === $eda_n && count( $eda_tagged( '_eda_demo_image' ) ) === $eda_vehicle, "$eda_n website attachments," . ' no duplicate identities; vehicle attachments unchanged (' . $eda_vehicle . ')' );
$eda_bad = array();
foreach ( $eda_ids as $eda_id ) {
	$eda_name = get_post_meta( $eda_id, '_eda_demo_site_image', true );
	$eda_meta = wp_get_attachment_metadata( $eda_id );
	if ( ! isset( $eda_files[ $eda_name ] ) || get_post_meta( $eda_id, '_eda_demo', true ) !== $eda_dataset || hash_file( 'sha256', "$eda_dir/$eda_name" ) !== get_post_meta( $eda_id, '_eda_demo_site_image_checksum', true )
		|| get_post_meta( $eda_id, '_wp_attachment_image_alt', true ) !== $eda_files[ $eda_name ]['alt'] || 'image/jpeg' !== get_post_mime_type( $eda_id )
		|| $eda_meta['width'] !== $eda_files[ $eda_name ]['width'] || $eda_meta['height'] !== $eda_files[ $eda_name ]['height'] || ! empty( $eda_meta['original_image'] ) ) {
		$eda_bad[] = $eda_name;
	}
	foreach ( $eda_meta['sizes'] as $eda_size ) {
		if ( $eda_size['width'] > $eda_meta['width'] || $eda_size['height'] > $eda_meta['height'] ) {
			$eda_bad[] = "$eda_name upscaled";
		}
	}
}
$eda_check( ! $eda_bad, 'tagged with dataset + filename + checksum, exact alt, image/jpeg, original size kept (no -scaled), no derivative above the source' . ( $eda_bad ? ': ' . implode( ', ', $eda_bad ) : '' ) );

// E. Assignment.
$eda_assigned = $eda_slots();
$eda_expect   = array_map( static fn( $f ) => isset( $eda_valid['valid'][ $f ] ) ? $f : '', array( 'aurelis-home-hero-desktop.jpg', 'aurelis-home-hero-mobile.jpg', 'aurelis-vehicles-forecourt.jpg', 'aurelis-about-showroom.jpg', 'aurelis-finance-consultation.jpg', 'aurelis-contact-entrance.jpg', 'aurelis-about-header.jpg', 'aurelis-finance-header.jpg', 'aurelis-contact-header.jpg' ) );
$eda_check( array_values( $eda_names( $eda_assigned ) ) === $eda_expect, 'theme mods (hero, mobile hero, Vehicles), featured images (About, Finance, Contact body) and _eda_header_image (About, Finance, Contact header) point to the right files' );

// Responsive candidates: same-ratio derivatives only, no landscape candidate in the portrait source.
$eda_srcset   = static fn( $id ) => array_map( static fn( $c ) => trim( $c ), explode( ',', (string) wp_get_attachment_image_srcset( $id, 'full' ) ) );
$eda_widths   = static fn( $id ) => array_map( static fn( $c ) => (int) substr( strrchr( $c, ' ' ), 1 ), $eda_srcset( $id ) );
$eda_check( array( 300, 768, 1024, 1536, 2048, 2560 ) === array_values( array_intersect( array( 300, 768, 1024, 1536, 2048, 2560 ), $eda_widths( $eda_assigned['hero'] ) ) ), 'desktop hero srcset: 300…2560 (' . implode( ', ', $eda_widths( $eda_assigned['hero'] ) ) . ')' );
$eda_portrait = array_filter( $eda_srcset( $eda_assigned['mobile'] ), static fn( $c ) => ! preg_match( '/-(\d+)x(\d+)\.jpg/', $c, $m ) || (int) $m[2] > (int) $m[1] );
$eda_check( count( $eda_portrait ) === count( $eda_srcset( $eda_assigned['mobile'] ) ) && max( $eda_widths( $eda_assigned['mobile'] ) ) === 1200, 'mobile hero srcset is portrait-only, up to the 1200 original (' . implode( ', ', $eda_widths( $eda_assigned['mobile'] ) ) . ')' );
$eda_check( max( $eda_widths( $eda_assigned['about'] ) ) === 1536 && max( $eda_widths( $eda_assigned['contact'] ) ) === 1200, 'About (3:2) and Contact (4:5) candidates top out at their source width' );

// C. Idempotency.
$eda_rerun = eda_site_images_import( $eda_dir, $eda_dataset );
$eda_check( 0 === $eda_rerun['created'] + $eda_rerun['replaced'] && $eda_n === $eda_rerun['reused'] && $eda_ids === $eda_tagged( '_eda_demo_site_image' ) && $eda_assigned === $eda_slots(), "rerun: 0 created, $eda_n reused, same IDs and assignments" );

// D. Checksum replacement: changed About source updates in place.
wp_mkdir_p( "$eda_tmp/src" );
copy( get_template_directory() . '/demo/images/aur-26007/aur-26007-interior.jpg', "$eda_tmp/src/aurelis-about-showroom.jpg" ); // Valid 1536x1024, different bytes.
$eda_path = get_attached_file( $eda_assigned['about'] );
$eda_swap = eda_site_images_import( "$eda_tmp/src", $eda_dataset );
$eda_check( 1 === $eda_swap['replaced'] && 0 === $eda_swap['created'] && $eda_assigned === $eda_slots() && get_attached_file( $eda_assigned['about'] ) === $eda_path && hash_file( 'sha256', $eda_path ) === hash_file( 'sha256', "$eda_tmp/src/aurelis-about-showroom.jpg" ), 'changed source replaced in place (same ID, path, assignment)' );
$eda_back = eda_site_images_import( $eda_dir, $eda_dataset );
$eda_check( 1 === $eda_back['replaced'] && hash_file( 'sha256', $eda_path ) === hash_file( 'sha256', "$eda_dir/aurelis-about-showroom.jpg" ), 'approved About image restored by checksum' );

// G. Frontend output.
$eda_home = $eda_fetch( '/' );
$eda_hero = substr( $eda_home, (int) strpos( $eda_home, 'class="hero__visual"' ), 4000 );
$eda_hero = substr( $eda_hero, 0, (int) strpos( $eda_hero, '</picture>' ) + 10 );
$eda_check( str_contains( $eda_hero, '<picture><source media="(orientation: portrait) and (max-width: 63.99em)"' ) && str_contains( $eda_hero, 'aurelis-home-hero-mobile' ) && ! str_contains( explode( '<img', $eda_hero )[0], 'aurelis-home-hero-desktop' ), 'homepage hero: <picture> with the portrait-mobile <source> (mobile file only)' );
preg_match( '/<img [^>]*>/', $eda_hero, $eda_img );
$eda_img = $eda_img[0] ?? '';
$eda_check( str_contains( $eda_img, 'aurelis-home-hero-desktop' ) && str_contains( $eda_img, 'alt=""' ) && str_contains( $eda_img, 'sizes="100vw"' ) && str_contains( $eda_img, 'loading="eager"' ) && str_contains( $eda_img, 'fetchpriority="high"' ) && ! str_contains( $eda_hero, 'lazy' ) && ! str_contains( $eda_img, 'aurelis-home-hero-mobile' ), 'hero <img>: desktop file, alt="", sizes=100vw, eager, fetchpriority=high, never lazy' );
$eda_check( 1 === substr_count( $eda_home, 'fetchpriority="high"' ), 'only the hero is high priority on the homepage' );

// Inner pages: header hero on top (when a header image is set), body images back in the content.
$eda_main   = static function ( $html ) {
	$from = (int) strpos( $html, '<main' );
	return substr( $html, $from, (int) strpos( $html, '</main>' ) - $from );
};
$eda_bodies = array(
	'/about/'   => array( 'aurelis-about-showroom.jpg', 'page-figure', 'h_about' ),
	'/finance/' => array( 'aurelis-finance-consultation.jpg', 'page-figure', 'h_finance' ),
	'/contact/' => array( 'aurelis-contact-entrance.jpg', 'contact-layout__figure', 'h_contact' ),
);
$eda_bad    = array();
foreach ( $eda_bodies as $eda_url => list( $eda_file, $eda_figure, $eda_slot ) ) {
	$eda_html = $eda_main( $eda_fetch( $eda_url ) );
	$eda_body = substr( $eda_html, (int) strpos( $eda_html, '</h1>' ) );
	$eda_base = pathinfo( $eda_file, PATHINFO_FILENAME );
	$eda_hero = (bool) preg_match( '/class="[^"]*\bpage-intro--hero\b/', $eda_html ) === (bool) $eda_assigned[ $eda_slot ]; // Class attribute: the inline palette CSS names it too.
	preg_match( '/<figure class="' . $eda_figure . '">\s*(<img [^>]*>)/', $eda_body, $eda_img );
	if ( ! $eda_hero || ! isset( $eda_img[1] ) || ! str_contains( $eda_img[1], $eda_base ) || ! str_contains( $eda_img[1], 'alt="' . $eda_files[ $eda_file ]['alt'] . '"' ) || 1 !== substr_count( $eda_html, '<figure' ) ) {
		$eda_bad[] = $eda_url;
	}
}
$eda_check( ! $eda_bad, 'About, Finance, Contact: body images restored in the content (featured image), header hero only when a header image is set' . ( $eda_bad ? ': ' . implode( ', ', $eda_bad ) : '' ) );
$eda_about = $eda_main( $eda_fetch( '/about/' ) );
$eda_check( (int) strrpos( substr( $eda_about, 0, (int) strpos( $eda_about, 'page-figure' ) ), '</p>' ) > (int) strpos( $eda_about, 'prose section-tight' ) && str_contains( $eda_about, 'sizes="(min-width: 46rem) 44rem, 92vw"' ), 'About: showroom image after the body copy, text-column sizes' );
$eda_contact = $eda_main( $eda_fetch( '/contact/' ) );
$eda_grid    = substr( $eda_contact, (int) strpos( $eda_contact, 'contact-layout' ) );
$eda_check( str_contains( $eda_contact, 'class="container contact-layout section-tight"' ) && (int) strpos( $eda_grid, 'class="panel"' ) < (int) strpos( $eda_grid, 'contact-layout__figure' ) && str_contains( $eda_grid, 'sizes="(min-width: 56em) 40vw, 92vw"' ) && ! preg_match( '/<figure class="contact-layout__figure">\s*<img [^>]*fetchpriority="high"/', $eda_grid ), 'Contact: entrance image back in the left column (after the form in source order), never high priority' );

// Header hero markup, proven with a temporary header image on About (works before the new files exist).
$eda_page  = get_page_by_path( 'about' )->ID;
$eda_saved = get_post_meta( $eda_page, '_eda_header_image', true );
update_post_meta( $eda_page, '_eda_header_image', $eda_assigned['inventory'] );
$eda_html = $eda_main( $eda_fetch( '/about/' ) );
preg_match( '/<div class="page-intro__backdrop">\s*(<img [^>]*>)/', $eda_html, $eda_img );
$eda_img = $eda_img[1] ?? '';
$eda_check( str_contains( $eda_html, '<div class="page-intro page-intro--hero ' ) && str_contains( $eda_img, 'sizes="100vw"' ) && str_contains( $eda_img, 'loading="eager"' ) && str_contains( $eda_img, 'fetchpriority="high"' ) && 1 === substr_count( $eda_html, 'fetchpriority="high"' ) && 2 === substr_count( $eda_html, '<img' ) && (int) strpos( $eda_html, 'page-intro__backdrop' ) < (int) strpos( $eda_html, '<h1 class="page-title">' ) && (int) strpos( $eda_html, '<h1' ) < (int) strpos( $eda_html, 'page-figure' ), 'page header hero: full-width image (100vw, eager, the only high-priority image) behind the title; body image still below' );
$eda_saved ? update_post_meta( $eda_page, '_eda_header_image', $eda_saved ) : delete_post_meta( $eda_page, '_eda_header_image' );

// Vehicles: hero, then filters and grid.
$eda_vehicles = $eda_fetch( '/vehicles/' );
$eda_hero     = substr( $eda_vehicles, (int) strpos( $eda_vehicles, '<div class="page-intro page-intro--hero ' ), (int) strpos( $eda_vehicles, 'inventory__filters' ) - (int) strpos( $eda_vehicles, '<div class="page-intro page-intro--hero ' ) );
preg_match( '/<div class="page-intro__backdrop">\s*(<img [^>]*>)/', $eda_hero, $eda_img );
$eda_img   = $eda_img[1] ?? '';
$eda_cards = substr( $eda_vehicles, (int) strpos( $eda_vehicles, 'class="vehicle-grid"' ) );
$eda_check( str_contains( $eda_img, 'aurelis-vehicles-forecourt' ) && str_contains( $eda_img, 'alt="' . $eda_files['aurelis-vehicles-forecourt.jpg']['alt'] . '"' ) && str_contains( $eda_img, 'sizes="100vw"' ) && str_contains( $eda_img, 'fetchpriority="high"' ) && 1 === substr_count( $eda_vehicles, 'fetchpriority="high"' ), 'Vehicles: full-width hero image (alt, sizes=100vw, eager, the only high-priority image)' );
$eda_check( strpos( $eda_hero, 'page-intro__backdrop' ) < strpos( $eda_hero, 'breadcrumbs' ) && str_contains( $eda_hero, '<p class="eyebrow eyebrow--light">' ) && str_contains( $eda_hero, '<h1 class="page-title">' ) && str_contains( $eda_hero, 'class="page-lead"' ), 'Vehicles: breadcrumb, eyebrow, H1 and subtitle sit on the hero image' );
$eda_check( (int) strpos( $eda_vehicles, 'page-intro--hero' ) < (int) strpos( $eda_vehicles, 'inventory__filters' ) && (int) strpos( $eda_vehicles, 'inventory__filters' ) < (int) strpos( $eda_vehicles, 'class="vehicle-grid"' ) && 12 === substr_count( $eda_cards, 'loading="lazy"' ) && ! str_contains( $eda_cards, 'fetchpriority="high"' ), 'Vehicles: hero, then filters, then the grid; inventory cards keep lazy loading' );
$eda_bad = array_filter( array( 'page.php', 'page-templates/contact.php', 'archive-vehicle.php' ), static fn( $t ) => ! str_contains( (string) file_get_contents( get_template_directory() . "/$t" ), "'template-parts/page-intro'" ) );
$eda_check( ! $eda_bad && str_contains( (string) file_get_contents( get_template_directory() . '/page.php' ), 'eda_page_header_image()' ) && str_contains( (string) file_get_contents( get_template_directory() . '/page-templates/contact.php' ), 'eda_page_header_image()' ), 'page.php, contact.php and archive-vehicle.php use template-parts/page-intro.php; pages pass their own header image' );
$eda_css = (string) file_get_contents( get_template_directory() . '/assets/css/main.css' );
$eda_check( (bool) preg_match( '/\.page-intro--hero \{[^}]*min-height: 16\.25rem;/s', $eda_css ) && (bool) preg_match( '/@media \(min-width: 40em\)[^@]*\.page-intro--hero \{\s*min-height: 20rem;/s', $eda_css ) && (bool) preg_match( '/@media \(min-width: 64em\)[^@]*\.page-intro--hero \{\s*min-height: clamp\(25rem, 22vw, 32rem\);/s', $eda_css ) && (bool) preg_match( '/\.page-intro__backdrop-image \{[^}]*object-fit: cover;\s*object-position: 50% 45%;/s', $eda_css ) && ! str_contains( $eda_css, 'page-intro__media' ) && (bool) preg_match( '/@media \(orientation: portrait\) and \(max-width: 63\.99em\)[^@]*object-position: 50% 30%/s', $eda_css ), 'CSS: header hero 260 / 320 / 400-512 px, cover at 50% 45%; no split layout left; mobile homepage hero at 50% 30%' );

// Hero motion: image-only, CSS only, clipped by the wrapper, off for reduced motion.
$eda_motion = static function ( $css ) {
	preg_match( '/\.hero__image,\s*\.page-intro__backdrop-image \{\s*transform-origin: var\(--hero-origin, 50% 50%\);\s*animation: eda-hero-motion ([^;]+);/', $css, $m );
	return $m[1] ?? '';
};
$eda_check( str_contains( $eda_motion( $eda_css ), 'infinite alternate' ) && (bool) preg_match( '/@keyframes eda-hero-motion \{\s*from \{\s*transform: scale\(1\) translate3d\(0, 0, 0\);\s*\}\s*to \{\s*transform: scale\(var\(--hero-scale, 1\.06\)\) translate3d\(0, 0, 0\);/', $eda_css ) && (bool) preg_match( '/\.hero__visual,\s*\.page-intro__backdrop \{\s*overflow: hidden;/', $eda_css ), 'motion: one transform-only keyframe on the two hero image classes; wrappers clip' );
preg_match_all( '/--hero-scale: ([\d.]+);/', $eda_css, $eda_scales );
$eda_check( $eda_scales[1] && max( array_map( 'floatval', $eda_scales[1] ) ) <= 1.08 && ! str_contains( $eda_css, '--hero-x' ) && ! preg_match( '/--hero-origin: (?!50% )/', $eda_css ) && 1 === substr_count( $eda_css, 'animation: eda-hero-motion' ) && 1 === substr_count( $eda_css, '@keyframes' ), 'motion: centred push-in only (no drift, origins centred horizontally), scale never above 1.08 (' . implode( ', ', $eda_scales[1] ) . '); no other animation' );
$eda_check( (bool) preg_match( '/@media \(prefers-reduced-motion: reduce\) \{.*\.hero__image,\s*\.page-intro__backdrop-image \{\s*animation: none;\s*transform: none;/s', $eda_css ) && strrpos( $eda_css, 'prefers-reduced-motion' ) > strpos( $eda_css, 'animation: eda-hero-motion' ), 'reduced motion: hero animation switched off (after the motion rules, so it wins)' );
$eda_check( str_contains( $eda_fetch( '/vehicles/' ), 'page-intro--hero page-intro--motion-vehicles' ) && str_contains( $eda_fetch( '/about/' ), 'page-intro--hero page-intro--motion-about' ) && str_contains( $eda_fetch( '/contact/' ), 'page-intro--motion-contact' ), 'per-page motion variant classes on the hero wrappers' );
$eda_check( ! preg_match( '/\.(page-figure|contact-layout__figure|vehicle-media|page-title|breadcrumbs|page-lead)[^{]*\{[^}]*animation/', $eda_css ), 'no animation on body images, cards, titles, breadcrumbs or subtitles' );

// A dealer's own image in a slot is never overwritten.
copy( get_template_directory() . '/demo/images/aur-26012/aur-26012-hero.jpg', "$eda_tmp/dealer-photo.jpg" );
$eda_client = media_handle_sideload(
	array(
		'name'     => 'aurelis-contact-entrance.jpg', // Look-alike name, untagged.
		'tmp_name' => "$eda_tmp/dealer-photo.jpg",
	),
	0
);
set_post_thumbnail( get_page_by_path( 'contact' ), $eda_client );
$eda_keep = eda_site_images_import( $eda_dir, $eda_dataset );
$eda_check( (int) get_post_thumbnail_id( get_page_by_path( 'contact' ) ) === $eda_client && isset( $eda_keep['skipped']['aurelis-contact-entrance.jpg'] ), "a dealer's own Contact image is kept, not overwritten" );

// F. Cleanup: only tagged website images; assignments cleared; vehicle images and client media untouched.
$eda_paths = array();
foreach ( $eda_ids as $eda_id ) {
	$eda_paths[] = get_attached_file( $eda_id );
	foreach ( wp_get_attachment_metadata( $eda_id )['sizes'] as $eda_size ) {
		$eda_paths[] = dirname( get_attached_file( $eda_id ) ) . '/' . $eda_size['file'];
	}
}
set_post_thumbnail( get_page_by_path( 'contact' ), $eda_assigned['contact'] );
foreach ( array( 'about', 'finance', 'contact' ) as $eda_slug ) { // Make sure header meta exists to be cleared (pending files).
	if ( ! get_post_meta( get_page_by_path( $eda_slug )->ID, '_eda_header_image', true ) ) {
		update_post_meta( get_page_by_path( $eda_slug )->ID, '_eda_header_image', $eda_assigned['inventory'] );
	}
}
$eda_removed = eda_demo_images_cleanup( $eda_dataset, '_eda_demo_site_image' );
$eda_check( $eda_n === $eda_removed && ! $eda_tagged( '_eda_demo_site_image' ) && count( $eda_tagged( '_eda_demo_image' ) ) === $eda_vehicle, "cleanup removes exactly the $eda_n website images; vehicle images untouched" );
$eda_check( get_post( $eda_client ) && file_exists( get_attached_file( $eda_client ) ), 'untagged look-alike upload survives' );
$eda_check( array( 0, 0, 0, 0, 0, 0, 0, 0, 0 ) === array_values( $eda_slots() ), 'theme mods, featured images and page header images all cleared' );
$eda_hero_markup = static fn( $html ) => (bool) preg_match( '/class="[^"]*\bpage-intro--hero\b/', $html ); // The class also appears as a selector in the inline palette CSS.
$eda_check( ! $eda_hero_markup( $eda_fetch( '/vehicles/' ) ) && ! $eda_hero_markup( $eda_fetch( '/about/' ) ) && ! str_contains( $eda_fetch( '/about/' ), 'page-figure' ), 'inner pages fall back to the text-only header and no body image' );
$eda_check( ! array_filter( $eda_paths, 'file_exists' ), 'originals and derivatives removed from uploads (' . count( $eda_paths ) . ' files)' );
$eda_check( str_contains( $eda_fetch( '/' ), 'class="media-placeholder"' ), 'homepage falls back to the placeholder without a hero image' );
eda_demo_delete_attachment( $eda_client );

// Restore.
$eda_restore = eda_site_images_import( $eda_dir, $eda_dataset );
$eda_check( $eda_restore['created'] === $eda_n && $eda_restore['assigned'] === $eda_n && count( array_filter( $eda_slots() ) ) === $eda_n, "website images restored ($eda_n created, $eda_n assigned)" );

array_map( 'unlink', array_filter( (array) glob( "$eda_tmp/{,src/}*", GLOB_BRACE ), 'is_file' ) );
@rmdir( "$eda_tmp/src" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
@rmdir( $eda_tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

echo PHP_EOL . ( $eda_failures ? "$eda_failures FAILED" : 'ALL PASSED' ) . PHP_EOL;
if ( $eda_failures ) {
	exit( 1 );
}
