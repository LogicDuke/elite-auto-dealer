<?php
/**
 * Demo inventory seeder (Aurelis Motors). Reads demo/vehicles.json; no network, no images.
 *
 * Run from the WordPress root with the theme active:
 *
 *   wp eval-file wp-content/themes/elite-auto-dealer/demo/seed.php            # import / update
 *   wp eval-file wp-content/themes/elite-auto-dealer/demo/seed.php validate   # check the JSON only
 *   wp eval-file wp-content/themes/elite-auto-dealer/demo/seed.php cleanup    # remove demo vehicles
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
 * Remove demo vehicles, then demo-created make/model/equipment terms that no vehicle uses any more.
 * Seeded base terms (fuel, body, …), terms that existed before seeding and terms still in use stay.
 * Enquiries are never deleted here (they follow the retention policy).
 *
 * @param string $dataset Dataset id.
 * @return array{vehicles: int, terms: int}
 */
function eda_demo_cleanup( $dataset ) {
	$removed = array(
		'vehicles' => 0,
		'terms'    => 0,
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
			$in_use = get_objects_in_term( $term->term_id, $taxonomy );
			if ( ! $in_use && ! is_wp_error( wp_delete_term( $term->term_id, $taxonomy ) ) ) {
				++$removed['terms'];
			}
		}
	}

	return $removed;
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
	WP_CLI::success( "Removed {$eda_removed['vehicles']} demo vehicles and {$eda_removed['terms']} unused demo-created terms." );
} elseif ( 'seed' === $eda_mode ) {
	$eda_result = eda_demo_seed( $eda_data );
	foreach ( $eda_result['skipped'] as $eda_stock ) {
		WP_CLI::warning( "$eda_stock skipped: stock ID belongs to a non-demo or duplicated vehicle." );
	}
	WP_CLI::success( "Demo inventory: {$eda_result['created']} created, {$eda_result['updated']} updated, " . count( $eda_result['skipped'] ) . ' skipped.' );
} else {
	WP_CLI::error( "Unknown mode '$eda_mode'. Use: (none) | validate | cleanup" );
}
