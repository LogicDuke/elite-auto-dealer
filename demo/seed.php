<?php
/**
 * Demo inventory seeder (Aurelis Motors). Reads demo/vehicles.json; no network. Images are
 * imported separately by demo/import-images.php and demo/import-site-images.php; cleanup here
 * removes them too.
 *
 * Run from the WordPress root with the theme active:
 *
 *   wp eval-file wp-content/themes/elite-auto-dealer/demo/seed.php            # import / update (+ legal pages)
 *   wp eval-file wp-content/themes/elite-auto-dealer/demo/seed.php legal      # legal pages + footer legal menu only
 *   wp eval-file wp-content/themes/elite-auto-dealer/demo/seed.php validate   # check the JSON only
 *   wp eval-file wp-content/themes/elite-auto-dealer/demo/seed.php cleanup    # remove demo vehicles + images
 *
 * Demo records are marked with post meta `_eda_demo` = dataset id and matched by stock ID, so
 * re-running updates instead of duplicating, and real dealership vehicles are never touched.
 * See docs/ARCHITECTURE.md ("Demo inventory").
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Load the canonical demo dataset.
 *
 * @return array
 */
function eda_demo_load() {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
	$data = json_decode( (string) file_get_contents( __DIR__ . '/vehicles.json' ), true );
	return is_array( $data ) ? $data : array();
}

/**
 * Validate the dataset against the theme schema and data rules. Pure apart from term lookups.
 *
 * @param array $data Dataset.
 * @return string[] Errors (empty = valid).
 */
function eda_demo_validate( array $data ) {
	$errors   = array();
	$fields   = eda_vehicle_meta_fields();
	$vehicles = $data['vehicles'] ?? array();
	$required = array( 'availability', 'featured', 'variant', 'price', 'finance_monthly', 'vat_regime', 'year', 'first_registration', 'mileage', 'power_kw', 'power_hp', 'exterior_colour', 'interior_colour', 'doors', 'seats', 'co2', 'carpass', 'warranty_months', 'vin' );
	$plugin   = array( 'electric', 'plug-in-hybrid' );
	$seen     = array(
		'stock' => array(),
		'vin'   => array(),
		'label' => array(),
	);

	if ( empty( $data['dataset'] ) ) {
		$errors[] = 'dataset id missing';
	}
	$orders = array_filter( array_column( $vehicles, 'featured_order' ) );
	if ( count( $orders ) !== count( array_unique( $orders ) ) ) {
		$errors[] = 'featured_order values must be unique';
	}
	if ( 15 !== count( $vehicles ) ) {
		$errors[] = 'expected exactly 15 vehicles, found ' . count( $vehicles );
	}
	foreach ( $data['equipment'] ?? array() as $slug => $label ) {
		$norm = strtolower( preg_replace( '/[^a-z0-9]/i', '', $label ) );
		if ( isset( $seen['label'][ $norm ] ) ) {
			$errors[] = "duplicate equipment label: $label";
		}
		$seen['label'][ $norm ] = true;
		if ( sanitize_title( $slug ) !== (string) $slug ) {
			$errors[] = "equipment slug not normalised: $slug";
		}
	}
	foreach ( $data['models'] ?? array() as $slug => $model ) {
		if ( ! isset( $data['makes'][ $model['make'] ] ) ) {
			$errors[] = "model $slug links to unknown make {$model['make']}";
		}
	}

	foreach ( $vehicles as $i => $v ) {
		$id   = $v['stock_id'] ?? "#$i";
		$meta = $v['meta'] ?? array();
		$err  = static function ( $message ) use ( &$errors, $id ) {
			$errors[] = "$id: $message";
		};

		if ( ! preg_match( '/^AUR-26\d{3}$/', $id ) ) {
			$err( 'stock ID format' );
		}
		if ( isset( $seen['stock'][ $id ] ) ) {
			$err( 'duplicate stock ID' );
		}
		$seen['stock'][ $id ] = true;

		$vin = $meta['vin'] ?? '';
		if ( ! preg_match( '/^DEMAUR[A-HJ-NPR-Z0-9]{11}$/', $vin ) ) {
			$err( "VIN must be 17 chars, DEMAUR prefix, no I/O/Q: $vin" );
		}
		if ( isset( $seen['vin'][ $vin ] ) ) {
			$err( 'duplicate VIN' );
		}
		$seen['vin'][ $vin ] = true;

		if ( empty( $v['title'] ) || empty( $v['excerpt'] ) || count( $v['description'] ?? array() ) < 2 || count( $v['description'] ) > 4 ) {
			$err( 'title, excerpt and 2–4 description paragraphs are required' );
		}

		// Make / model.
		if ( ! isset( $data['makes'][ $v['make'] ?? '' ] ) ) {
			$err( 'unknown make' );
		}
		if ( ( $data['models'][ $v['model'] ?? '' ]['make'] ?? null ) !== ( $v['make'] ?? '' ) ) {
			$err( 'model is not linked to this make' );
		}

		// Fixed vocabularies must use existing (seeded) term slugs.
		foreach ( array(
			'body_type'    => 'vehicle_body_type',
			'fuel_type'    => 'vehicle_fuel_type',
			'transmission' => 'vehicle_transmission',
			'condition'    => 'vehicle_condition',
		) as $key => $taxonomy ) {
			if ( empty( $v[ $key ] ) || ! get_term_by( 'slug', $v[ $key ], $taxonomy ) ) {
				$err( "missing or unknown $key" );
			}
		}

		$equipment = $v['equipment'] ?? array();
		if ( count( $equipment ) !== count( array_unique( $equipment ) ) ) {
			$err( 'duplicate equipment' );
		}
		foreach ( $equipment as $slug ) {
			if ( ! isset( $data['equipment'][ $slug ] ) ) {
				$err( "unknown equipment $slug" );
			}
		}

		// Meta: known keys, required keys, values survive sanitising unchanged.
		foreach ( $required as $key ) {
			if ( ! array_key_exists( $key, $meta ) ) {
				$err( "missing meta $key" );
			}
		}
		foreach ( $meta as $key => $value ) {
			if ( ! isset( $fields[ $key ] ) || in_array( $key, array( 'stock_id', 'gallery' ), true ) ) {
				$err( "unexpected meta key $key" );
				continue;
			}
			if ( false === $value ) {
				continue;
			}
			$clean = eda_sanitize_vehicle_meta_value( $value, $fields[ $key ] );
			if ( '' === $clean || ( is_numeric( $value ) ? (float) $clean !== (float) $value : $clean !== $value ) ) {
				$err( "meta $key does not survive sanitising: " . wp_json_encode( $value ) );
			}
		}

		// Plausibility rules.
		$fuel        = $v['fuel_type'] ?? '';
		$has_plug    = in_array( $fuel, $plugin, true );
		$has_ev_data = isset( $meta['battery_kwh'] ) && isset( $meta['ev_range_km'] );
		$has_any_ev  = isset( $meta['battery_kwh'] ) || isset( $meta['ev_range_km'] );
		if ( ( $has_plug && ! $has_ev_data ) || ( ! $has_plug && $has_any_ev ) ) {
			$err( 'battery/range must be set exactly for electric and plug-in hybrid vehicles' );
		}
		if ( 'electric' === $fuel && ( isset( $meta['engine_cc'] ) || isset( $meta['euro_norm'] ) || 0 !== ( $meta['co2'] ?? null ) ) ) {
			$err( 'electric vehicles have no engine size or Euro norm, and 0 g/km CO2' );
		}
		if ( 'electric' !== $fuel && ( empty( $meta['engine_cc'] ) || empty( $meta['euro_norm'] ) || empty( $meta['co2'] ) ) ) {
			$err( 'combustion/hybrid vehicles need engine size, Euro norm and CO2' );
		}
		if ( isset( $meta['power_kw'], $meta['power_hp'] ) && abs( $meta['power_kw'] * 1.35962 - $meta['power_hp'] ) > 1 ) {
			$err( 'kW and hp do not match' );
		}
		if ( isset( $meta['first_registration'], $meta['year'] ) && (int) substr( $meta['first_registration'], 0, 4 ) !== $meta['year'] ) {
			$err( 'year and first registration differ' );
		}
		$featured  = ! empty( $meta['featured'] );
		$has_order = isset( $v['featured_order'] );
		$order_ok  = $has_order && is_int( $v['featured_order'] ) && $v['featured_order'] >= 1;
		if ( ( $featured && ! $order_ok ) || ( ! $featured && $has_order ) ) {
			$err( 'featured vehicles need a positive featured_order, others none' );
		}
		if ( 'new' === ( $v['condition'] ?? '' ) ) {
			$err( 'demo stock is used or ex-demo, never "new"' );
		}
	}

	return $errors;
}

/**
 * Get or create a term; terms created here are marked as demo-created.
 *
 * @param string $taxonomy Taxonomy.
 * @param string $slug     Slug.
 * @param string $name     Name.
 * @param string $dataset  Dataset id.
 * @return int Term ID.
 */
function eda_demo_term( $taxonomy, $slug, $name, $dataset ) {
	$term = get_term_by( 'slug', $slug, $taxonomy );
	if ( $term ) {
		return (int) $term->term_id;
	}
	$created = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
	if ( is_wp_error( $created ) ) {
		WP_CLI::error( "Could not create $taxonomy term $slug: " . $created->get_error_message() );
	}
	update_term_meta( $created['term_id'], '_eda_demo', $dataset );
	return (int) $created['term_id'];
}

/**
 * Find a vehicle (any status) by stock ID.
 *
 * @param string $stock_id Stock ID.
 * @return int[] Post IDs.
 */
function eda_demo_find( $stock_id ) {
	return get_posts(
		array(
			'post_type'      => 'vehicle',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'meta_key'       => '_eda_stock_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- CLI seeder.
			'meta_value'     => $stock_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- CLI seeder.
		)
	);
}

/**
 * Import or update all demo vehicles.
 *
 * @param array $data Validated dataset.
 * @return array{created: int, updated: int, skipped: string[]}
 */
function eda_demo_seed( array $data ) {
	$dataset = $data['dataset'];
	$fields  = eda_vehicle_meta_fields();
	$result  = array(
		'created' => 0,
		'updated' => 0,
		'skipped' => array(),
	);

	$makes = array();
	foreach ( $data['makes'] as $slug => $name ) {
		$makes[ $slug ] = eda_demo_term( 'vehicle_make', $slug, $name, $dataset );
	}
	$models = array();
	foreach ( $data['models'] as $slug => $model ) {
		$models[ $slug ] = eda_demo_term( 'vehicle_model', $slug, $model['name'], $dataset );
		$linked          = (int) get_term_meta( $models[ $slug ], 'eda_make', true );
		if ( ! $linked ) {
			update_term_meta( $models[ $slug ], 'eda_make', $makes[ $model['make'] ] );
		} elseif ( $linked !== $makes[ $model['make'] ] ) {
			WP_CLI::warning( "Model $slug already belongs to another make; link left unchanged." );
		}
	}
	$equipment = array();
	foreach ( $data['equipment'] as $slug => $name ) {
		$equipment[ $slug ] = eda_demo_term( 'vehicle_equipment', $slug, $name, $dataset );
	}

	foreach ( $data['vehicles'] as $v ) {
		$found = eda_demo_find( $v['stock_id'] );
		$ids   = array_filter( $found, static fn( $id ) => get_post_meta( $id, '_eda_demo', true ) === $dataset );
		if ( count( $found ) !== count( $ids ) || count( $ids ) > 1 ) {
			// A real vehicle (or an ambiguous duplicate) uses this stock ID: never touch it.
			$result['skipped'][] = $v['stock_id'];
			continue;
		}
		$existing = (int) reset( $ids );

		$content = implode(
			"\n\n",
			array_map(
				static fn( $paragraph ) => "<!-- wp:paragraph -->\n<p>" . esc_html( $paragraph ) . "</p>\n<!-- /wp:paragraph -->",
				$v['description']
			)
		);

		$post_id = wp_insert_post(
			array(
				'ID'           => $existing,
				'post_type'    => 'vehicle',
				'post_status'  => 'publish',
				'post_title'   => $v['title'],
				'menu_order'   => (int) ( $v['featured_order'] ?? 0 ), // Curated featured order (0 = not ordered).
				'post_excerpt' => $v['excerpt'],
				'post_content' => $content,
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			WP_CLI::error( "{$v['stock_id']}: " . $post_id->get_error_message() );
		}
		++$result[ $existing ? 'updated' : 'created' ];

		update_post_meta( $post_id, '_eda_demo', $dataset );
		update_post_meta( $post_id, '_eda_stock_id', $v['stock_id'] );

		// Every schema field (except gallery, which images will own) is synced, so a value
		// removed from the JSON is removed from the vehicle too.
		foreach ( $fields as $key => $field ) {
			if ( in_array( $key, array( 'stock_id', 'gallery' ), true ) ) {
				continue;
			}
			$value = eda_sanitize_vehicle_meta_value( $v['meta'][ $key ] ?? '', $field );
			if ( '' === $value || false === $value ) {
				delete_post_meta( $post_id, '_eda_' . $key );
			} else {
				update_post_meta( $post_id, '_eda_' . $key, $value );
			}
		}

		$terms = array(
			'vehicle_make'         => array( $makes[ $v['make'] ] ),
			'vehicle_model'        => array( $models[ $v['model'] ] ),
			'vehicle_body_type'    => array( (int) get_term_by( 'slug', $v['body_type'], 'vehicle_body_type' )->term_id ),
			'vehicle_fuel_type'    => array( (int) get_term_by( 'slug', $v['fuel_type'], 'vehicle_fuel_type' )->term_id ),
			'vehicle_transmission' => array( (int) get_term_by( 'slug', $v['transmission'], 'vehicle_transmission' )->term_id ),
			'vehicle_condition'    => array( (int) get_term_by( 'slug', $v['condition'], 'vehicle_condition' )->term_id ),
			'vehicle_equipment'    => array_map( static fn( $slug ) => $equipment[ $slug ], $v['equipment'] ),
		);
		foreach ( $terms as $taxonomy => $term_ids ) {
			wp_set_object_terms( $post_id, $term_ids, $taxonomy );
		}
	}

	return $result;
}

/**
 * Delete one attachment with all its files.
 *
 * Core skips derivatives when uploads use a "C:/" path (path_is_absolute() wants "C:\", e.g. LocalWP
 * on Windows), so this attachment's own listed sizes are removed too, confined to its folder.
 * On other hosts core has already deleted them and the extra pass is a no-op.
 *
 * @param int $id Attachment ID.
 * @return bool Deleted.
 */
function eda_demo_delete_attachment( $id ) {
	$dir   = dirname( (string) get_attached_file( $id ) );
	$sizes = wp_list_pluck( wp_get_attachment_metadata( $id )['sizes'] ?? array(), 'file' );
	if ( ! wp_delete_attachment( $id, true ) ) {
		return false;
	}
	foreach ( $sizes as $file ) {
		wp_delete_file_from_directory( "$dir/$file", $dir );
	}
	return true;
}

/**
 * Remove imported demo images: only attachments tagged with BOTH `_eda_demo` = dataset and the
 * identity key (`_eda_demo_image` for vehicle photos, `_eda_demo_site_image` for website images).
 * Untagged media (client uploads, look-alike file names) is never touched.
 * Featured-image references go with the attachment (core); gallery references and the homepage
 * hero / inventory image settings and page header images are cleared here, so nothing keeps a
 * broken attachment ID.
 *
 * @param string $dataset Dataset id.
 * @param string $key     Identity meta key.
 * @return int Attachments deleted.
 */
function eda_demo_images_cleanup( $dataset, $key = '_eda_demo_image' ) {
	$ids     = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- CLI seeder.
				array(
					'key'   => '_eda_demo',
					'value' => $dataset,
				),
				array(
					'key'     => $key,
					'compare' => 'EXISTS',
				),
			),
		)
	);
	$deleted = count( array_filter( array_map( 'eda_demo_delete_attachment', $ids ) ) );

	$vehicles = get_posts(
		array(
			'post_type'      => 'vehicle',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'meta_key'       => '_eda_gallery', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- CLI seeder.
		)
	);
	foreach ( $vehicles as $vehicle ) {
		$gallery = (array) get_post_meta( $vehicle, '_eda_gallery', true );
		$kept    = array_values( array_diff( $gallery, $ids ) );
		if ( ! $kept ) {
			delete_post_meta( $vehicle, '_eda_gallery' );
		} elseif ( $kept !== $gallery ) {
			update_post_meta( $vehicle, '_eda_gallery', $kept );
		}
	}
	foreach ( array( 'eda_hero_image', 'eda_hero_image_mobile', 'eda_inventory_image' ) as $mod ) {
		if ( in_array( (int) get_theme_mod( $mod ), $ids, true ) ) {
			remove_theme_mod( $mod );
		}
	}
	foreach ( $ids as $id ) {
		delete_metadata( 'post', 0, '_eda_header_image', $id, true ); // Page header images.
	}
	return $deleted;
}

/**
 * Remove demo images (vehicle and website) and demo vehicles, then demo-created make/model/equipment terms that no vehicle uses any more.
 * Seeded base terms (fuel, body, …), catalogue terms, terms that existed before seeding and terms
 * still in use stay.
 * Enquiries are never deleted here (they follow the retention policy).
 *
 * @param string $dataset Dataset id.
 * @return array{images: int, site_images: int, vehicles: int, terms: int}
 */
function eda_demo_cleanup( $dataset ) {
	$removed = array(
		'images'      => eda_demo_images_cleanup( $dataset ),
		'site_images' => eda_demo_images_cleanup( $dataset, '_eda_demo_site_image' ),
		'vehicles'    => 0,
		'terms'       => 0,
	);

	$ids = get_posts(
		array(
			'post_type'      => 'vehicle',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'meta_key'       => '_eda_demo', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- CLI seeder.
			'meta_value'     => $dataset, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- CLI seeder.
		)
	);
	foreach ( $ids as $id ) {
		$removed['vehicles'] += wp_delete_post( $id, true ) ? 1 : 0;
	}

	foreach ( array( 'vehicle_make', 'vehicle_model', 'vehicle_equipment' ) as $taxonomy ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'meta_key'   => '_eda_demo', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- CLI seeder.
				'meta_value' => $dataset, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- CLI seeder.
			)
		);
		foreach ( $terms as $term ) {
			// Catalogue terms belong to the product, not the demo: never delete them.
			if ( get_term_meta( $term->term_id, '_eda_catalogue', true ) ) {
				continue;
			}
			$in_use = get_objects_in_term( $term->term_id, $taxonomy );
			if ( ! $in_use && ! is_wp_error( wp_delete_term( $term->term_id, $taxonomy ) ) ) {
				++$removed['terms'];
			}
		}
	}

	return $removed;
}

/**
 * Create or update one legal page from demo/<file> (plain, editable block content).
 *
 * Content written by the seeder is fingerprinted (`_eda_demo_legal` = md5 of the saved content),
 * so re-seeding refreshes untouched pages but never overwrites wording the owner has edited.
 * A page without a fingerprint is only filled while unpublished (WordPress's default draft
 * privacy page).
 *
 * @param string $slug  Slug for a new page.
 * @param string $title Title.
 * @param string $file  Source file in demo/.
 * @param int    $id    Existing page ID (0: find by slug).
 * @return array{id: int, action: string}
 */
function eda_demo_legal_page( $slug, $title, $file, $id = 0 ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
	$content = (string) file_get_contents( __DIR__ . '/' . $file );
	$page    = $id ? get_post( $id ) : get_page_by_path( $slug );
	$action  = 'created';

	if ( $page ) {
		$hash = get_post_meta( $page->ID, '_eda_demo_legal', true );
		if ( $hash ? md5( $page->post_content ) !== $hash : 'publish' === $page->post_status ) {
			return array(
				'id'     => $page->ID,
				'action' => 'kept (edited)',
			);
		}
		$action = $content === $page->post_content && 'publish' === $page->post_status ? 'unchanged' : 'updated';
		wp_update_post(
			wp_slash(
				array(
					'ID'           => $page->ID,
					'post_title'   => $title,
					'post_status'  => 'publish',
					'post_content' => $content,
				)
			)
		);
		$id = $page->ID;
	} else {
		$id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => 'page',
					'post_name'    => $slug,
					'post_title'   => $title,
					'post_status'  => 'publish',
					'post_content' => $content,
				)
			)
		);
	}
	update_post_meta( $id, '_eda_demo_legal', md5( get_post( $id )->post_content ) );

	return array(
		'id'     => (int) $id,
		'action' => $action,
	);
}

/**
 * Legal pages (Legal Notice, Cookie Policy, Privacy Notice as WordPress's privacy page) and the
 * "Footer legal" menu with Cookie preferences. Idempotent: no duplicate pages, privacy pages or
 * menu items on re-runs. Vehicles and images are not touched.
 *
 * @return array<string, array{id: int, action: string}>
 */
function eda_demo_seed_legal() {
	$privacy = (int) get_option( 'wp_page_for_privacy_policy' );
	$privacy = $privacy && 'page' === get_post_type( $privacy ) && 'trash' !== get_post_status( $privacy ) ? $privacy : 0;
	$pages   = array(
		'legal-notice'  => eda_demo_legal_page( 'legal-notice', 'Legal Notice', 'legal-notice.html' ),
		'cookie-policy' => eda_demo_legal_page( 'cookie-policy', 'Cookie Policy', 'cookie-policy.html' ),
		'privacy'       => eda_demo_legal_page( 'privacy-notice', 'Privacy Notice', 'privacy-notice.html', $privacy ),
	);
	update_option( 'wp_page_for_privacy_policy', $pages['privacy']['id'] );

	$menu = wp_get_nav_menu_object( 'Footer legal' );
	$menu = $menu ? $menu->term_id : wp_create_nav_menu( 'Footer legal' );
	$have = wp_get_nav_menu_items( $menu );
	$have = is_array( $have ) ? $have : array();
	$want = array(
		array( 'Privacy Notice', $pages['privacy']['id'] ),
		array( 'Legal Notice', $pages['legal-notice']['id'] ),
		array( 'Cookie Policy', $pages['cookie-policy']['id'] ),
		array( 'Cookie preferences', '#eds-consent' ),
	);
	foreach ( $want as $position => list( $label, $target ) ) {
		$exists = array_filter( $have, static fn( $item ) => is_int( $target ) ? (int) $item->object_id === $target && 'page' === $item->object : $item->url === $target );
		if ( $exists ) {
			continue;
		}
		wp_update_nav_menu_item(
			$menu,
			0,
			is_int( $target ) ? array(
				'menu-item-title'     => $label,
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $target,
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
				'menu-item-position'  => $position + 1,
			) : array(
				'menu-item-title'    => $label,
				'menu-item-url'      => $target,
				'menu-item-type'     => 'custom',
				'menu-item-status'   => 'publish',
				'menu-item-position' => $position + 1,
			)
		);
	}
	$locations          = (array) get_theme_mod( 'nav_menu_locations', array() );
	$locations['legal'] = $menu;
	set_theme_mod( 'nav_menu_locations', $locations );

	return $pages;
}

// Library mode for tests: define EDA_DEMO_LIBRARY before including this file.
if ( defined( 'EDA_DEMO_LIBRARY' ) ) {
	return;
}

if ( ! defined( 'WP_CLI' ) || ! function_exists( 'eda_vehicle_meta_fields' ) ) {
	exit( "Run with: wp eval-file demo/seed.php (theme must be active)\n" );
}

$eda_mode = $args[0] ?? 'seed';
$eda_data = eda_demo_load();
$eda_errs = eda_demo_validate( $eda_data );

if ( $eda_errs ) {
	foreach ( $eda_errs as $eda_error ) {
		WP_CLI::warning( $eda_error );
	}
	WP_CLI::error( count( $eda_errs ) . ' validation error(s) in demo/vehicles.json; nothing was changed.' );
}

if ( 'validate' === $eda_mode ) {
	WP_CLI::success( 'demo/vehicles.json is valid (' . count( $eda_data['vehicles'] ) . ' vehicles).' );
} elseif ( 'cleanup' === $eda_mode ) {
	$eda_removed = eda_demo_cleanup( $eda_data['dataset'] );
	WP_CLI::success( "Removed {$eda_removed['images']} demo vehicle images, {$eda_removed['site_images']} demo website images, {$eda_removed['vehicles']} demo vehicles and {$eda_removed['terms']} unused demo-created terms." );
} elseif ( 'seed' === $eda_mode ) {
	$eda_result = eda_demo_seed( $eda_data );
	foreach ( $eda_result['skipped'] as $eda_stock ) {
		WP_CLI::warning( "$eda_stock skipped: stock ID belongs to a non-demo or duplicated vehicle." );
	}
	WP_CLI::success( "Demo inventory: {$eda_result['created']} created, {$eda_result['updated']} updated, " . count( $eda_result['skipped'] ) . ' skipped.' );
}

if ( in_array( $eda_mode, array( 'seed', 'legal' ), true ) ) {
	foreach ( eda_demo_seed_legal() as $eda_page => $eda_legal ) {
		WP_CLI::log( "Legal page $eda_page (#{$eda_legal['id']}): {$eda_legal['action']}." );
	}
	WP_CLI::success( 'Legal pages, privacy page and "Footer legal" menu are in place.' );
} elseif ( ! in_array( $eda_mode, array( 'validate', 'cleanup' ), true ) ) {
	WP_CLI::error( "Unknown mode '$eda_mode'. Use: (none) | legal | validate | cleanup" );
}
